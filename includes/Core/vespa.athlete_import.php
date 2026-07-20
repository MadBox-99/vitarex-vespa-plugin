<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Sportoló tömeges import motor (xlsx/CSV).
 *
 * Felelősség: parse (fájl -> sorok), validate (soronkénti elbírálás, DB-írás
 * NÉLKÜL), commit (a jó sorok beszúrása tranzakcióban). A felület
 * (templates/import_athletes.php) csak ezt hívja.
 */
class VESPA_Athlete_Importer
{
    // Oszlopindexek a sablonban (0-alapú), a fejléc sorrendjével egyezően.
    const COL_NAME         = 0;
    const COL_BIRTH_PLACE  = 1;
    const COL_BIRTH_DATE   = 2;
    const COL_MOTHERS_NAME = 3;
    const COL_PHONE        = 4;
    const COL_EMAIL        = 5;
    const COL_ZIP          = 6;
    const COL_CITY         = 7;
    const COL_ADDRESS      = 8;
    const COL_NATIONALITY  = 9;
    const COL_PERSONAL_ID  = 10;
    const COL_GENDER       = 11;
    const COL_DISABILITY   = 12;
    const COL_REGISTERED   = 13;
    const COL_NOTE         = 14;
    const COL_ACTIVE       = 15;

    /**
     * Az "Aktív" oszlop normalizálása. Üres/1/Igen -> 1 (aktív, a séma
     * default); 0/Nem -> 0 (inaktív). A régi import fordított logikáját javítja.
     */
    public static function normalize_active($raw)
    {
        $v = mb_strtolower(trim((string) $raw), 'UTF-8');
        if ($v === '0' || $v === 'nem') {
            return 0;
        }
        return 1;
    }

    /**
     * A "Nem" oszlop normalizálása a kanonikus, KISBETŰS tárolt formára
     * ('férfi' / 'nő') — a teljes kódbázis így hasonlítja. Bemenet kis/nagybetű
     * tűrve. Érvénytelen érték -> null (a hívó ezt hibaként kezeli).
     */
    public static function normalize_gender($raw)
    {
        $v = mb_strtolower(trim((string) $raw), 'UTF-8');
        if ($v === 'férfi') {
            return 'férfi';
        }
        if ($v === 'nő') {
            return 'nő';
        }
        return null;
    }

    /**
     * Érvényes-e a dátum szigorúan YYYY-MM-DD formátumban.
     */
    public static function is_valid_date($raw)
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return false;
        }
        $d = DateTime::createFromFormat('Y-m-d', $s);
        return $d instanceof DateTime && $d->format('Y-m-d') === $s;
    }

    /**
     * A fájl beolvasása soronkénti, 0-alapú indexű tömbbé (a fejléc sort
     * kihagyva). Az IOFactory az xlsx-et és a CSV-t is felismeri.
     *
     * @throws \Exception ha a fájl nem olvasható / nem támogatott formátum.
     */
    public static function parse($file_path)
    {
        require_once VITAREX_VESPA_PLUGIN_DIR . '/lib/vendor/autoload.php';

        $spreadsheet = IOFactory::load($file_path);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = array();
        $first = true;
        foreach ($sheet->toArray(null, true, false, false) as $row) {
            if ($first) {
                $first = false; // fejléc kihagyása
                continue;
            }
            // Teljesen üres sor kihagyása (a táblázatok végén gyakori).
            $joined = trim(implode('', array_map(function ($c) {
                return (string) $c;
            }, $row)));
            if ($joined === '') {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * A fogyatékossági csoport neve -> id. Nincs találat -> null (a hívó ezt
     * hibaként kezeli; NINCS szentinel-id-1 hack).
     */
    public static function lookup_disability_id($name)
    {
        global $wpdb;
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT disability_group_id FROM vespa_disability_groups WHERE disability_group_name=%s",
            trim((string) $name)
        ));
        return $id === null ? null : (int) $id;
    }

    /**
     * Van-e már ilyen ÉLŐ (is_deleted=0) sportoló (4 mezős egyezés).
     */
    public static function is_duplicate($name, $birth_place, $birth_date, $mothers_name)
    {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM vespa_athletes
             WHERE is_deleted=0 AND athlete_name=%s AND birth_place=%s AND birth_date=%s AND mothers_name=%s",
            trim((string) $name), trim((string) $birth_place), trim((string) $birth_date), trim((string) $mothers_name)
        ));
        return (int) $count > 0;
    }

    /**
     * Soronkénti elbírálás, DB-ÍRÁS NÉLKÜL. A jó, a duplikált és a hibás sorokat
     * három csoportba sorolja. A jó és a hibás állapot kizáró: egy hibás sor nem
     * kerül a valid-ba, akkor sem, ha egyébként duplikátum lenne.
     *
     * @return array{valid: array, duplicate: array, error: array, total: int}
     */
    public static function validate(array $rows, $school_id)
    {
        $result = array('valid' => array(), 'duplicate' => array(), 'error' => array(), 'total' => 0);
        $line = 1; // a fejléc az 1. sor; az első adatsor a 2.

        foreach ($rows as $row) {
            $line++;
            $result['total']++;

            // A sor 0..15 indexűre normalizálása (hiányzó cellák üres stringgé).
            $r = array();
            for ($i = 0; $i <= self::COL_ACTIVE; $i++) {
                $r[$i] = isset($row[$i]) ? trim((string) $row[$i]) : '';
            }

            $errors = array();

            // Kötelező: név
            if ($r[self::COL_NAME] === '') {
                $errors[] = 'A sportoló neve kötelező.';
            }

            // Kötelező: születési dátum (YYYY-MM-DD)
            if (!self::is_valid_date($r[self::COL_BIRTH_DATE])) {
                $errors[] = 'A születési dátum hiányzik vagy nem érvényes (YYYY-MM-DD).';
            }

            // Kötelező: nem
            $gender = self::normalize_gender($r[self::COL_GENDER]);
            if ($gender === null) {
                $errors[] = 'A nem hiányzik vagy nem érvényes (Nő / Férfi).';
            }

            // Kötelező: fogyatékosság típusa (léteznie kell)
            $disability_id = null;
            if ($r[self::COL_DISABILITY] === '') {
                $errors[] = 'A fogyatékosság típusa kötelező.';
            } else {
                $disability_id = self::lookup_disability_id($r[self::COL_DISABILITY]);
                if ($disability_id === null) {
                    $errors[] = 'A fogyatékosság típusa nem szerepel az adatbázisban (' . $r[self::COL_DISABILITY] . ').';
                }
            }

            // Opcionális, de ha kitöltve: e-mail formátum
            if ($r[self::COL_EMAIL] !== '' && !vespa_validate_email($r[self::COL_EMAIL])) {
                $errors[] = 'Az e-mail cím formátuma érvénytelen.';
            }

            // Opcionális, de ha kitöltve: telefon formátum
            if ($r[self::COL_PHONE] !== '' && !vespa_validate_phone($r[self::COL_PHONE])) {
                $errors[] = 'A telefonszám formátuma érvénytelen.';
            }

            // Opcionális, de ha kitöltve: nyilvántartásba vétel dátuma
            if ($r[self::COL_REGISTERED] !== '' && !self::is_valid_date($r[self::COL_REGISTERED])) {
                $errors[] = 'A nyilvántartásba vétel dátuma nem érvényes (YYYY-MM-DD).';
            }

            if (!empty($errors)) {
                $result['error'][] = array('row' => $line, 'messages' => $errors);
                continue;
            }

            // Duplikátum-ellenőrzés (csak hibátlan sorra).
            if (self::is_duplicate($r[self::COL_NAME], $r[self::COL_BIRTH_PLACE], $r[self::COL_BIRTH_DATE], $r[self::COL_MOTHERS_NAME])) {
                $result['duplicate'][] = array('row' => $line, 'name' => $r[self::COL_NAME]);
                continue;
            }

            // Beszúrásra kész, sanitizált sor (school_id/modified_* a commitban).
            $result['valid'][] = array(
                'athlete_name'    => sanitize_text_field($r[self::COL_NAME]),
                'birth_place'     => sanitize_text_field($r[self::COL_BIRTH_PLACE]),
                'birth_date'      => $r[self::COL_BIRTH_DATE],
                'mothers_name'    => sanitize_text_field($r[self::COL_MOTHERS_NAME]),
                'phone'           => sanitize_text_field($r[self::COL_PHONE]),
                'email'           => sanitize_text_field($r[self::COL_EMAIL]),
                'home_zipcode'    => sanitize_text_field($r[self::COL_ZIP]),
                'home_city'       => sanitize_text_field($r[self::COL_CITY]),
                'home_address'    => sanitize_text_field($r[self::COL_ADDRESS]),
                'nationality'     => sanitize_text_field($r[self::COL_NATIONALITY]),
                'personal_id'     => sanitize_text_field($r[self::COL_PERSONAL_ID]),
                'gender'          => $gender,
                'disability_type' => $disability_id,
                'registered_at'   => $r[self::COL_REGISTERED] !== '' ? $r[self::COL_REGISTERED] : null,
                'note'            => sanitize_text_field($r[self::COL_NOTE]),
                'active'          => self::normalize_active($r[self::COL_ACTIVE]),
            );
        }

        return $result;
    }
}
