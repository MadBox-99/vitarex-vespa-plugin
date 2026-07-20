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
}
