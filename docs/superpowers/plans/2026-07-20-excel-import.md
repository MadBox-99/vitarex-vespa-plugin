# A1 — Excel-alapú tömeges diákimport — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A sportolók egyesével rögzítése helyett Excel/CSV sablonból, kétlépéses (előnézet → jóváhagyás) importfolyamattal, soronkénti validációval, duplikátum-kihagyással és naplózással.

**Architecture:** Az üzleti logika egy új `VESPA_Athlete_Importer` osztályba kerül (`parse` → `validate` → `commit`), a `templates/import_athletes.php` csak felület. A két lépés között a validált sorokat + a `school_id`-t egy WP transient viszi át (nem kell újra feltölteni). A PhpSpreadsheet `IOFactory` egységesen olvassa az xlsx-et és a CSV-t.

**Tech Stack:** WordPress plugin, PHP 8.4, `$wpdb`, PhpSpreadsheet (`lib/vendor`), WP transient API, natív wp-admin felület.

**Spec:** `docs/superpowers/specs/2026-07-20-excel-import-design.md`

**Megjegyzés a tesztelésről:** Nincs automatizált tesztkészlet. A „test" lépések: `php -l` szintaxis-ellenőrzés, `grep` szerkezeti ellenőrzés, a tiszta (DB-független) helpereknél önálló `php -r` futtatás, és manuális böngészős végigjátszás. Minden PHP-módosítás után `php -l` fut.

## Global Constraints

- **PHP fájl módosítás után mindig:** `php -l <fájl>` → `No syntax errors detected`.
- **Nyelv:** minden felhasználónak látszó szöveg és kódkomment **magyar**.
- **Oszlopsorrend (0..15), változatlan** (a régi CSV-k kompatibilisek): 0=Sportoló neve, 1=Születési hely, 2=Születési dátum, 3=Anyja neve, 4=Telefonszám, 5=Email, 6=Irányítószám, 7=Település, 8=Lakcím, 9=Állampolgárság, 10=Igazolványszám, 11=Nem, 12=Fogyatékosság típusa, 13=Nyilvántartásba vétele, 14=Megjegyzés, 15=Aktív.
- **Kötelező mezők:** Sportoló neve (0), Születési dátum (2), Nem (11), Fogyatékosság típusa (12). A többi opcionális.
- **`gender` kanonikus tárolt forma: kisbetűs `férfi` / `nő`** (a teljes kódbázis így hasonlítja). A bemenet kis/nagybetű tűrve, a tárolt érték kisbetűs.
- **`active`:** `1`/`Igen`/üres → 1 (aktív, a séma default), `0`/`Nem` → 0 (inaktív).
- **Duplikátum** (név+szül.hely+szül.dátum+anyja neve, `is_deleted=0`): kihagyva + jelezve, NEM hiba, NEM blokkol.
- **Ismeretlen fogyatékosság** → a sor **hibás** (nincs szentinel-id-1 hack).
- **Validátorok:** `vespa_validate_email()`, `vespa_validate_phone()` (az 1. csomagból, `includes/Core/functions.php`).
- **Naplózás:** egy `vitarex_log('athlete_import', ...)` a commit után (nem soronként).
- **Biztonság:** nonce (`vespa_nonce`) mindkét lépésen; explicit `current_user_can(VESPA_Roles::sportolok_letrehozasa_modositasa)`; a `school_id` a 2. lépésben a transientből (nem újraküldött POST-ból).
- **PhpSpreadsheet betöltése:** `require VITAREX_VESPA_PLUGIN_DIR . '/lib/vendor/autoload.php';` a metóduson belül, a hívás előtt (a meglévő Export-fájlok mintája).

---

## File Structure

- `includes/Core/vespa.athlete_import.php` — **ÚJ**: `VESPA_Athlete_Importer` osztály (`parse`, `validate`, `commit` + tiszta helperek + belső DB-helperek). A `Core` könyvtár töltődik be elsőként (`vitarex-vespa-plugin.php:51`), így az osztály a template-ből elérhető.
- `templates/import_athletes.php` — **teljes átírás**: kétlépéses UI (feltöltés+előnézet / jóváhagyás), üzleti logika nélkül; nonce + cap + iskola-szűrés + transient + naplózás.
- `includes/Export/csv.athletes.php` — **kiegészítés**: xlsx-minta generátor + cap-ellenőrzés a minta-letöltésen.

---

## Task 1: `VESPA_Athlete_Importer` — osztályváz + oszlop-konstansok + tiszta helperek

**Files:**
- Create: `includes/Core/vespa.athlete_import.php`

**Interfaces:**
- Produces: `VESPA_Athlete_Importer` osztály; `COL_*` konstansok (0..15); `normalize_active($raw): int`, `normalize_gender($raw): ?string` (kisbetűs `férfi`/`nő` vagy null), `is_valid_date($raw): bool` — statikus, tiszta metódusok. A Task 3 (`validate`) használja őket.

- [ ] **Step 1: Az osztály + konstansok + tiszta helperek létrehozása**

Hozd létre a `includes/Core/vespa.athlete_import.php` fájlt:

```php
<?php

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
}
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Core/vespa.athlete_import.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: A tiszta helperek viselkedésének ellenőrzése (önálló futtatás)**

A három helper nem függ WordPresstől, így közvetlenül tesztelhető:

```bash
php -r '
require "includes/Core/vespa.athlete_import.php";
$C = "VESPA_Athlete_Importer";
// normalize_active
$a = ["1"=>1, "Igen"=>1, ""=>1, "0"=>0, "Nem"=>0, "nem"=>0, "igaz"=>1];
foreach ($a as $in=>$exp) { $r=$C::normalize_active($in); echo ($r===$exp?"OK  ":"BUKO")." active(\"$in\")=$r (vart:$exp)\n"; }
// normalize_gender
$g = ["Férfi"=>"férfi", "férfi"=>"férfi", "NŐ"=>"nő", "nő"=>"nő", "x"=>null, ""=>null];
foreach ($g as $in=>$exp) { $r=$C::normalize_gender($in); echo (($r===$exp)?"OK  ":"BUKO")." gender(\"$in\")=".var_export($r,true)." (vart:".var_export($exp,true).")\n"; }
// is_valid_date
$d = ["2000-05-01"=>true, "2000-13-01"=>false, "2000-5-1"=>false, ""=>false, "hello"=>false];
foreach ($d as $in=>$exp) { $r=$C::is_valid_date($in); echo ($r===$exp?"OK  ":"BUKO")." date(\"$in\")=".var_export($r,true)." (vart:".var_export($exp,true).")\n"; }
'
```

Expected: minden sor `OK`. Ha bármelyik `BUKO`, javítsd a helpert, mielőtt továbbmész.

- [ ] **Step 4: Commit**

```bash
git add includes/Core/vespa.athlete_import.php
git commit -m "feat: VESPA_Athlete_Importer osztályváz + tiszta normalizáló helperek"
```

---

## Task 2: `parse()` + belső DB-helperek (fogyatékosság-keresés, dedup)

**Files:**
- Modify: `includes/Core/vespa.athlete_import.php` (a `VESPA_Athlete_Importer` osztályba)

**Interfaces:**
- Consumes: `COL_*` (Task 1); PhpSpreadsheet `IOFactory` (vendor autoload).
- Produces: `parse(string $file_path): array` (soronkénti 0-alapú tömbök, fejléc nélkül; `Exception` sérült fájlnál); `lookup_disability_id(string $name): ?int`; `is_duplicate(string $name, string $birth_place, string $birth_date, string $mothers_name): bool`. A Task 3 és Task 4 használja őket.

- [ ] **Step 1: A `use` importok hozzáadása a fájl tetejére**

A `includes/Core/vespa.athlete_import.php`-ben keresd meg:

```php
<?php

/**
 * Sportoló tömeges import motor (xlsx/CSV).
```

És cseréld erre:

```php
<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Sportoló tömeges import motor (xlsx/CSV).
```

> A `use` a namespace-t oldja fel; a tényleges osztály a vendor autoload
> betöltésekor válik elérhetővé (lásd `parse()`). A `use` önmagában nem tölt be
> semmit, ezért biztonságos a fájl tetején, autoload nélkül is.

- [ ] **Step 2: A `parse()` és a belső DB-helperek hozzáadása**

A `is_valid_date()` metódus záró `}`-e **után**, az osztály záró `}`-e **elé** szúrd be:

```php

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
```

- [ ] **Step 3: Szintaxis-ellenőrzés**

Run: `php -l includes/Core/vespa.athlete_import.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Ellenőrzés — a metódusok és a use megvannak**

Run: `grep -c "public static function parse\|public static function lookup_disability_id\|public static function is_duplicate\|use PhpOffice" includes/Core/vespa.athlete_import.php`
Expected: `4`

- [ ] **Step 5: Commit**

```bash
git add includes/Core/vespa.athlete_import.php
git commit -m "feat: VESPA_Athlete_Importer parse() + fogyatékosság-keresés + dedup helper"
```

---

## Task 3: `validate()` — soronkénti elbírálás (valid / duplicate / error)

**Files:**
- Modify: `includes/Core/vespa.athlete_import.php`

**Interfaces:**
- Consumes: `COL_*`, `normalize_active`, `normalize_gender`, `is_valid_date` (Task 1); `lookup_disability_id`, `is_duplicate` (Task 2); `vespa_validate_email()`, `vespa_validate_phone()` (1. csomag).
- Produces: `validate(array $rows, int $school_id): array` — a `['valid'=>[], 'duplicate'=>[], 'error'=>[], 'total'=>int]` szerkezet. A `valid[]` elemei beszúrásra kész asszociatív tömbök (a `school_id`/`modified_*` NÉLKÜL — azt a `commit` teszi hozzá). A Task 4 és Task 6 használja.

- [ ] **Step 1: A `validate()` hozzáadása**

Az `is_duplicate()` metódus záró `}`-e **után**, az osztály záró `}`-e **elé** szúrd be:

```php

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
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Core/vespa.athlete_import.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Ellenőrzés — a validate megvan és a helpereket használja**

Run: `grep -c "public static function validate\|self::is_valid_date\|self::normalize_gender\|self::lookup_disability_id\|self::is_duplicate\|vespa_validate_email\|vespa_validate_phone" includes/Core/vespa.athlete_import.php`
Expected: legalább `7` (mindegyik hivatkozás legalább egyszer).

- [ ] **Step 4: Commit**

```bash
git add includes/Core/vespa.athlete_import.php
git commit -m "feat: VESPA_Athlete_Importer validate() - soronkénti elbírálás"
```

---

## Task 4: `commit()` — tranzakciós beszúrás + biztonsági újra-dedup

**Files:**
- Modify: `includes/Core/vespa.athlete_import.php`

**Interfaces:**
- Consumes: `is_duplicate` (Task 2); a `validate()` `valid[]` elemeinek szerkezete (Task 3).
- Produces: `commit(array $valid_rows, int $school_id): int` — a ténylegesen beszúrt sorok száma. A Task 6 használja.

> **Megjegyzés a tranzakcióról:** a `START TRANSACTION`/`ROLLBACK` csak akkor véd
> a fél-import ellen, ha a `vespa_athletes` tábla **InnoDB** (MyISAM-on a
> parancsok no-op-ok, a beszúrások azonnal véglegesülnek). A biztonsági
> újra-dedup és a soronkénti validáció miatt a részleges import ekkor is
> lényegében ártalmatlan (nem keletkezik duplikátum, csak kevesebb sor megy be).
> A tábla motorjának átállítása nem része ennek a csomagnak.

- [ ] **Step 1: A `commit()` hozzáadása**

A `validate()` metódus záró `}`-e **után**, az osztály záró `}`-e **elé** szúrd be:

```php

    /**
     * A jó sorok beszúrása a vespa_athletes-be, tranzakcióban. Minden sorra
     * BIZTONSÁGI újra-dedup közvetlenül a beszúrás előtt (az előnézet óta
     * létrejöhetett ütközés) — a közben duplikálttá vált sort átugorja.
     *
     * @param array $valid_rows a validate() 'valid' tömbje
     * @param int   $school_id  a transientből (nem újraküldött POST-ból)
     * @return int a ténylegesen beszúrt sorok száma
     */
    public static function commit(array $valid_rows, $school_id)
    {
        global $wpdb;

        $inserted = 0;
        $now = date('Y-m-d H:i:s');
        $user_id = get_current_user_id();

        $wpdb->query('START TRANSACTION');
        try {
            foreach ($valid_rows as $row) {
                if (self::is_duplicate($row['athlete_name'], $row['birth_place'], $row['birth_date'], $row['mothers_name'])) {
                    continue; // az előnézet óta duplikálttá vált
                }

                $data = $row;
                $data['school_id']   = (int) $school_id;
                $data['modified_at'] = $now;
                $data['modified_by'] = $user_id;

                $ok = $wpdb->insert('vespa_athletes', $data);
                if ($ok === false) {
                    throw new \Exception('Beszúrási hiba: ' . $wpdb->last_error);
                }
                $inserted++;
            }
            $wpdb->query('COMMIT');
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return 0;
        }

        return $inserted;
    }
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Core/vespa.athlete_import.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Ellenőrzés — tranzakció + újra-dedup**

Run: `grep -c "START TRANSACTION\|ROLLBACK\|COMMIT\|self::is_duplicate" includes/Core/vespa.athlete_import.php`
Expected: legalább `4` (a `commit()`-ban a három tranzakció-parancs + a `commit`-beli `is_duplicate` hívás; a `validate`-beli `is_duplicate` ezt növeli).

Run: `grep -c "public static function commit" includes/Core/vespa.athlete_import.php`
Expected: `1`

- [ ] **Step 4: Commit**

```bash
git add includes/Core/vespa.athlete_import.php
git commit -m "feat: VESPA_Athlete_Importer commit() - tranzakciós beszúrás + újra-dedup"
```

---

## Task 5: xlsx-minta generátor + cap-ellenőrzés a minta-letöltésen

**Files:**
- Modify: `includes/Export/csv.athletes.php`

**Interfaces:**
- Consumes: PhpSpreadsheet `Spreadsheet` + `Writer\Xlsx` (vendor autoload); `VESPA_Roles::sportolok_letrehozasa_modositasa`.
- Produces: `?vespa_athletes_xlsx_sample=1` letöltési útvonal. A Task 6 template a letöltő linkeket kirakja.

> A meglévő CSV-minta (`?vespa_athletes_csv_sample=1`) **megmarad**. Ez a task egy
> xlsx-mintát ad hozzá **azonos fejléc-oszlopokkal**, és mindkét minta-letöltésre
> cap-ellenőrzést tesz (ma csak `is_user_logged_in()`).

- [ ] **Step 1: A cap-ellenőrzés hozzáadása a meglévő CSV-mintához**

A `includes/Export/csv.athletes.php`-ben keresd meg:

```php
    if (isset($_GET['vespa_athletes_csv_sample']) && is_user_logged_in()) {
```

Cseréld erre:

```php
    if (isset($_GET['vespa_athletes_csv_sample']) && current_user_can(VESPA_Roles::sportolok_letrehozasa_modositasa)) {
```

- [ ] **Step 2: Az xlsx-minta hozzáadása**

A fájl tetején keresd meg:

```php
<?php
add_action('init', 'download_csv');
function download_csv()
{
```

Cseréld erre (a `use` importok + egy második `add_action` + az xlsx-generátor):

```php
<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

add_action('init', 'download_csv');
function download_csv()
{
```

Majd a fájl **legvégén** keresd meg a záró `?>`-t (a `download_csv()` függvény záró `}`-e után):

```php
    }
}
?>
```

És cseréld erre (az xlsx-generátor függvény + a hook a `download_csv()` után):

```php
    }
}

add_action('init', 'vespa_athletes_xlsx_sample');
function vespa_athletes_xlsx_sample()
{
    if (!isset($_GET['vespa_athletes_xlsx_sample']) || !current_user_can(VESPA_Roles::sportolok_letrehozasa_modositasa)) {
        return;
    }

    require_once VITAREX_VESPA_PLUGIN_DIR . '/lib/vendor/autoload.php';

    $header = array('Sportoló neve', 'Születési hely', 'Születési dátum', 'Anyja neve', 'Telefonszám', 'Email', 'Irányítószám', 'Település', 'Lakcím', 'Állampolgárság', 'Igazolványszám', 'Nem', 'Fogyatékosság típusa', 'Nyilvántartásba vétele', 'Megjegyzés', 'Aktív');
    $sample = array('Sportoló 1', 'Budapest', '2000-05-01', 'Sportoló 1 anyja neve', '+3630123456', 'valaki@pelda.hu', '2194', 'Tura', 'Verseny utca, 12.', 'Magyar', '935486HK', 'Férfi', 'Vak', '2014-11-09', 'Kék a szeme', '1');

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray($header, null, 'A1');
    $sheet->fromArray($sample, null, 'A2');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="vespa_athletes.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>
```

- [ ] **Step 3: Szintaxis-ellenőrzés**

Run: `php -l includes/Export/csv.athletes.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Ellenőrzés — az xlsx-minta és a cap a helyén**

Run: `grep -c "vespa_athletes_xlsx_sample\|Writer\\\\Xlsx\|sportolok_letrehozasa_modositasa" includes/Export/csv.athletes.php`
Expected: legalább `4` (2× a hook-név/paraméter, a Writer use + példány, 2× a cap a két mintánál).

Run: `grep -c "is_user_logged_in" includes/Export/csv.athletes.php`
Expected: `0` — a lazább ellenőrzést cap váltotta fel.

- [ ] **Step 5: Manuális ellenőrzés (böngésző)**

Bejelentkezve (a `sportolok_letrehozasa_modositasa` joggal): `?vespa_athletes_xlsx_sample=1` → letöltődik egy `vespa_athletes.xlsx`, ami Excelben megnyílik, a fejléc és 1 mintasor a helyén. A CSV-minta (`?vespa_athletes_csv_sample=1`) továbbra is működik.

- [ ] **Step 6: Commit**

```bash
git add includes/Export/csv.athletes.php
git commit -m "feat: xlsx minta-sablon letöltés + cap-ellenőrzés a minta-letöltésen"
```

---

## Task 6: `import_athletes.php` — kétlépéses UI (feltöltés+előnézet / jóváhagyás)

**Files:**
- Modify: `templates/import_athletes.php` (teljes átírás)

**Interfaces:**
- Consumes: `VESPA_Athlete_Importer::parse/validate/commit` (Task 1-4); `vespa_get_my_school_id()`, `VESPA_Roles::sportolok_letrehozasa_modositasa`, `current_user_has_role`; `vitarex_log()`; a Task 5 minta-letöltési útvonalak.

> **A template ma minden logikát kever (parse+validáció+DB+HTML).** Ez a task
> teljesen lecseréli egy vékony, kétlépéses felületre, ami csak a
> `VESPA_Athlete_Importer`-t hívja. A `getDisabilityId`/`getDuplicates` régi
> template-függvények törlődnek (a logikájuk az importer osztályban van).

- [ ] **Step 1: A teljes template lecserélése**

Írd felül a `templates/import_athletes.php` teljes tartalmát ezzel:

```php
<h1>Sportoló import</h1>
<?php
global $wpdb;

// --- Jogosultság ---
if (!current_user_can(VESPA_Roles::sportolok_letrehozasa_modositasa)) {
    echo '<p style="color:red;">Nincs jogosultságod a sportoló importhoz.</p>';
    return;
}

$is_teacher = VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::TESTNEVELO);

// --- Az iskola-legördülő adatai (testnevelőnél csak a saját iskolája) ---
if ($is_teacher) {
    $my_school_id = (int) vespa_get_my_school_id();
    $institutions = $wpdb->get_results($wpdb->prepare('SELECT * FROM vespa_institutions WHERE institution_id=%d', $my_school_id));
} else {
    $institutions = $wpdb->get_results('SELECT * FROM vespa_institutions ORDER BY ins_name');
}

$notice = '';   // sikeres/összegző üzenet
$error  = '';   // felső szintű hibaüzenet
$preview = null; // az előnézet adatai

// --- 2. LÉPÉS: jóváhagyás + commit ---
if (isset($_POST['vespa_import_confirm'])) {
    if (!isset($_POST['vespa_import_nonce']) || !wp_verify_nonce($_POST['vespa_import_nonce'], 'vespa_import')) {
        $error = 'Érvénytelen kérés (nonce).';
    } else {
        $token = isset($_POST['vespa_import_token']) ? sanitize_text_field($_POST['vespa_import_token']) : '';
        $payload = $token !== '' ? get_transient('vespa_import_' . $token) : false;

        if ($payload === false || !isset($payload['valid'], $payload['school_id'])) {
            $error = 'Az előnézet lejárt vagy nem található. Kérlek, tölts fel újra.';
        } else {
            // A school_id a transientből jön (nem újraküldött POST-ból).
            $school_id = (int) $payload['school_id'];
            $inserted = VESPA_Athlete_Importer::commit($payload['valid'], $school_id);
            delete_transient('vespa_import_' . $token);

            $skipped = isset($payload['duplicate_count']) ? (int) $payload['duplicate_count'] : 0;
            $errored = isset($payload['error_count']) ? (int) $payload['error_count'] : 0;

            $school = $wpdb->get_row($wpdb->prepare('SELECT ins_name FROM vespa_institutions WHERE institution_id=%d', $school_id));
            $school_name = $school ? $school->ins_name : ('#' . $school_id);

            vitarex_log(
                'athlete_import',
                "Sportoló import — iskola: $school_name (#$school_id). Importálva: $inserted, kihagyott duplikátum: $skipped, hibás: $errored.",
                'vespa_athletes'
            );

            $notice = "Az import lefutott. $inserted sportoló importálva" .
                ($skipped > 0 ? ", $skipped már létező kihagyva" : '') .
                ($errored > 0 ? ", $errored hibás sor kihagyva" : '') . '.';
        }
    }
}

// --- 1. LÉPÉS: feltöltés + előnézet ---
if (isset($_POST['vespa_import_upload'])) {
    if (!isset($_POST['vespa_import_nonce']) || !wp_verify_nonce($_POST['vespa_import_nonce'], 'vespa_import')) {
        $error = 'Érvénytelen kérés (nonce).';
    } elseif (!isset($_POST['school_id']) || !is_numeric($_POST['school_id'])) {
        $error = 'Válassz iskolát.';
    } else {
        $school_id = (int) $_POST['school_id'];

        // Testnevelő csak a saját iskoláját választhatja.
        if ($is_teacher && $school_id !== (int) vespa_get_my_school_id()) {
            $error = 'Csak a saját iskoládba importálhatsz.';
        } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Nem sikerült a fájl feltöltése.';
        } else {
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, array('xlsx', 'csv'), true)) {
                $error = 'Csak .xlsx vagy .csv fájl tölthető fel.';
            } else {
                try {
                    $rows = VESPA_Athlete_Importer::parse($_FILES['file']['tmp_name']);
                    $res = VESPA_Athlete_Importer::validate($rows, $school_id);

                    $token = wp_generate_password(20, false);
                    set_transient('vespa_import_' . $token, array(
                        'valid'           => $res['valid'],
                        'school_id'       => $school_id,
                        'duplicate_count' => count($res['duplicate']),
                        'error_count'     => count($res['error']),
                    ), 15 * MINUTE_IN_SECONDS);

                    $preview = array('res' => $res, 'token' => $token, 'school_id' => $school_id);
                } catch (\Exception $e) {
                    $error = 'A fájl nem olvasható vagy nem támogatott formátum.';
                }
            }
        }
    }
}
?>

<div id="import">
    <?php if ($error !== '') : ?>
        <p style="color:red; font-size:16px;"><strong><?php echo esc_html($error); ?></strong></p>
    <?php endif; ?>

    <?php if ($notice !== '') : ?>
        <p style="font-size:16px; color:#137333;"><strong><?php echo esc_html($notice); ?></strong></p>
    <?php endif; ?>

    <?php if ($preview !== null) : $res = $preview['res']; ?>
        <div class="col-md-12">
            <p style="font-size:16px;">
                <?php echo (int) $res['total']; ?> sor beolvasva —
                <strong><?php echo count($res['valid']); ?> importálható</strong>,
                <?php echo count($res['duplicate']); ?> már létezik (kihagyva),
                <?php echo count($res['error']); ?> hibás.
            </p>

            <?php if (!empty($res['error'])) : ?>
                <p style="color:red; font-size:15px;">Hibás sorok:</p>
                <ul>
                    <?php foreach ($res['error'] as $e) : ?>
                        <li><?php echo (int) $e['row']; ?>. sor: <?php echo esc_html(implode(' ', $e['messages'])); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($res['duplicate'])) : ?>
                <p style="font-size:15px;">Már létező (kihagyott) sorok:</p>
                <ul>
                    <?php foreach ($res['duplicate'] as $d) : ?>
                        <li><?php echo (int) $d['row']; ?>. sor: <?php echo esc_html($d['name']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (count($res['valid']) > 0) : ?>
                <form action="" method="POST" style="margin-top:15px;">
                    <?php wp_nonce_field('vespa_import', 'vespa_import_nonce'); ?>
                    <input type="hidden" name="vespa_import_token" value="<?php echo esc_attr($preview['token']); ?>">
                    <button type="submit" class="btn btn-sm btn-primary" name="vespa_import_confirm">
                        <i class="fa fa-check" aria-hidden="true"></i>
                        Jóváhagyom és importálom (<?php echo count($res['valid']); ?> sportoló)
                    </button>
                    <a href="<?php echo esc_url(remove_query_arg('x')); ?>" class="btn btn-default btn-sm" style="margin-left:10px;">Mégse</a>
                </form>
            <?php else : ?>
                <p>Nincs importálható sor. Javítsd a fájlt, és tölts fel újra.</p>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="col-md-4">
            <form action="" method="POST" enctype="multipart/form-data">
                <?php wp_nonce_field('vespa_import', 'vespa_import_nonce'); ?>
                <div class="form-group">
                    <label>Iskola / egyesület</label>
                    <select name="school_id" id="school_id" class="form-control input-sm" required>
                        <?php foreach ($institutions as $institution) : ?>
                            <option value="<?php echo (int) $institution->institution_id; ?>">
                                <?php echo esc_html($institution->ins_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:20px;">
                    <input type="file" name="file" id="file" accept=".xlsx,.csv" required>
                </div>

                <button type="submit" class="btn btn-sm btn-primary" name="vespa_import_upload">
                    <i class="fa fa-upload" aria-hidden="true"></i>
                    FELTÖLTÉS ÉS ELŐNÉZET
                </button>
            </form>

            <p style="margin-top:15px;">
                Mintafájl:
                <a href="<?php echo esc_url(add_query_arg('vespa_athletes_xlsx_sample', 1, home_url())); ?>">Excel (.xlsx)</a>
                &nbsp;|&nbsp;
                <a href="<?php echo esc_url(add_query_arg('vespa_athletes_csv_sample', 1, home_url())); ?>">CSV</a>
            </p>
        </div>
    <?php endif; ?>
</div>
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l templates/import_athletes.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Ellenőrzés — a régi template-logika eltűnt, az új a helyén**

Run: `grep -c "VESPA_Athlete_Importer::\|wp_verify_nonce\|set_transient\|get_transient\|vitarex_log\|current_user_can" templates/import_athletes.php`
Expected: legalább `7`.

Run: `grep -c "function getDisabilityId\|function getDuplicates\|fgetcsv\|\$_FILES\['file'\]\['type'\]" templates/import_athletes.php`
Expected: `0` — a régi, template-be ágyazott logika és a böngésző-MIME-ellenőrzés eltűnt.

- [ ] **Step 4: Manuális végigjátszás (böngésző) — lásd a Záró ellenőrzés szakaszt**

- [ ] **Step 5: Commit**

```bash
git add templates/import_athletes.php
git commit -m "feat: kétlépéses (előnézet -> jóváhagyás) sportoló import UI"
```

---

## Záró ellenőrzés (manuális, böngészőben)

- [ ] **Step 1: Minden módosított PHP fájl szintaxis-ellenőrzése**

Run:
```bash
php -l includes/Core/vespa.athlete_import.php
php -l includes/Export/csv.athletes.php
php -l templates/import_athletes.php
```
Expected: mindegyik `No syntax errors detected`.

- [ ] **Step 2: Minta-letöltés**

A Sportoló import oldalon: az „Excel (.xlsx)" és a „CSV" mintalink is letölt egy fájlt, mindkettő azonos fejléc-oszlopokkal.

- [ ] **Step 3: Jó fájl (xlsx ÉS csv)**

Tölts fel egy fájlt, ahol minden sor hibátlan és új:
- Az előnézet: „N sor beolvasva — N importálható, 0 már létezik, 0 hibás."
- Jóváhagyás → „N sportoló importálva."
- A Sportolók listában megjelennek, és **aktívak** (a fordított-active bug javítva).
- A `gender` a DB-ben **kisbetűs** (`férfi`/`nő`), így a riportokban is számít.
- A `vitarex_log`-ban egy `athlete_import` bejegyzés a helyes darabszámokkal.

- [ ] **Step 4: Vegyes fájl (jó + duplikátum + hibás)**

Készíts egy fájlt, ahol pl. a 2. sor jó, a 3. sor hibás (üres nem), a 4. sor duplikátum (egy már létező diák), az 5. sor jó:
- Az előnézet a hármat helyesen szétbontja, a hibás sornál konkrét üzenettel.
- Jóváhagyás → **csak a 2. és 5. sor** kerül be; a 3. (hibás) és a 4. (dup) kimarad.
- **Kritikus:** az 5. sor (a hibás 3. UTÁN) is bemegy — a néma-kihagyás bug javítva.

- [ ] **Step 5: Kötelező mezők és formátumok**

- Üres név / rossz dátum / üres vagy rossz nem / üres vagy ismeretlen fogyatékosság → a sor hibás, konkrét üzenettel; a jó sorok bemennek.
- Kitöltött, de rossz e-mail (`"asdf"`) vagy telefon (`"12"`) → a sor hibás. Üresen hagyva → átmegy.

- [ ] **Step 6: Biztonság és élettartam**

- **Idegen iskola:** testnevelőként a legördülő csak a saját iskoládat kínálja; más `school_id` beküldése (pl. kézzel) → „Csak a saját iskoládba importálhatsz."
- **Sérült fájl / rossz kiterjesztés** (pl. `.txt`) → barátságos hiba, semmi nem íródik be.
- **Lejárt transient:** a jóváhagyás (15 percnél régebbi token) → „Az előnézet lejárt… tölts fel újra."
- **Régi CSV kompatibilitás:** a korábbi `;`-elválasztású CSV ugyanúgy importálódik.

---

## Self-Review jegyzet

**Spec-lefedettség:**

| Spec pont | Task |
|---|---|
| 1. `VESPA_Athlete_Importer` osztály | Task 1-4 |
| `parse()` (xlsx+CSV, IOFactory) | Task 2 |
| `validate()` (soronkénti, valid/dup/error) | Task 3 |
| `commit()` (tranzakció + újra-dedup) | Task 4 |
| Kötelező mezők (név, dátum, nem, fogyatékosság) | Task 3 (Step 1) |
| Formátum-validáció (dátum, nem, e-mail, telefon) | Task 3 (Step 1) |
| Duplikátum kihagyva+jelezve | Task 3 (dup) + Task 6 (előnézet) |
| Fordított-active javítás | Task 1 (`normalize_active`) |
| Fogyatékosság-szentinel javítás | Task 2 (`lookup_disability_id` → null) + Task 3 |
| `gender` kisbetűs normalizálás | Task 1 (`normalize_gender`) |
| xlsx-minta generátor + cap | Task 5 |
| Kétlépéses UI (előnézet → jóváhagyás) | Task 6 |
| Transient (valid + school_id) | Task 6 |
| Nonce + cap + iskola-szűrés | Task 6 |
| Naplózás (`vitarex_log`) | Task 6 |
| Néma-kihagyás javítás | Task 3 (soronkénti) + Task 6 (csak valid commitál) |

**Eltérés a spec-től / kiegészítés:**
- A `gender` kisbetűs normalizálása (Task 1) egyben javít egy latens hibát: a régi
  import nagybetűvel tárolta, ami nem egyezett a riportok `== 'férfi'`
  összehasonlításával. A spec ezt nem részletezte, de a „gender kanonikus forma"
  Global Constraint rögzíti.

**Konzisztencia:**
- `COL_*` konstansok — definiálva Task 1, használva Task 2 (nem) / Task 3.
- `normalize_active`/`normalize_gender`/`is_valid_date` — Task 1, használva Task 3.
- `parse`/`lookup_disability_id`/`is_duplicate` — Task 2, használva Task 3 (validate) és Task 4 (commit, is_duplicate).
- `validate()` `valid[]` szerkezete (16 kulcs, school_id/modified_* nélkül) — Task 3, fogyasztva Task 4 (`commit` teszi hozzá a `school_id`/`modified_*`) és Task 6.
- A transient kulcsa `vespa_import_<token>`, tartalma `['valid','school_id','duplicate_count','error_count']` — Task 6-ban set és get konzisztens.
- A nonce action `vespa_import`, mező `vespa_import_nonce` — a feltöltő és a jóváhagyó formban is ugyanaz.

**Sorrendi függések:**
- Task 1 → 2 → 3 → 4 (a helperek és metódusok egymásra épülnek, ugyanabban a fájlban).
- Task 1-4 → Task 6 (a template az importert hívja).
- Task 5 független (a minta-letöltés), de a Task 6 template kirakja a linkjeit — Task 5 a Task 6 előtt fusson.
