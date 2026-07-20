# Szezon riport javítás + kötelező mezők + arculat (1. csomag) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A szezon riport helyesen különítse el az országos/regionális/megyei versenyeket (A5); a kísérő e-mail/telefon formátuma valóban ellenőrzött legyen és a testnevelőnek legyen kötelező telefonszáma (A3); a FODISZ logó és a VESPA arculat jelenjen meg a felületen (A6).

**Architecture:** „Célzott alap" megközelítés — három apró megosztott darab (`VespaContestType` konstansok, `vespa_validate_email()` / `vespa_validate_phone()` helperek, `:root` CSS paletta), de **kizárólag ott bevezetve, amit ez a csomag úgyis megfog**. A meglévő ~40 `contest_type` magic numberhez nem nyúlunk.

**Tech Stack:** WordPress plugin, PHP 8.4, `$wpdb`, PhpSpreadsheet (`lib/vendor`), natív wp-admin felület, jQuery.

**Spec:** `docs/superpowers/specs/2026-07-17-riport-validacio-arculat-design.md`

**Megjegyzés a tesztelésről:** Nincs automatizált tesztkészlet. A „test" lépések PHP szintaxis-ellenőrzés (`php -l`), `grep`-es ellenőrzés és manuális böngészős végigjátszás. Minden PHP-módosítás után `php -l` fut.

## Global Constraints

- **PHP fájl módosítás után mindig:** `php -l <fájl>` → `No syntax errors detected`.
- **Nyelv:** minden felhasználónak látszó szöveg és kódkomment **magyar**.
- **`contest_type` értékek:** 1=országos, 2=regionális, 3=megyei, 4=szabadidősport. Ebben a csomagban **csak a szezon riportban** használunk konstanst; máshol a magic number marad.
- **„Összes" (`filter=all`) jelentése a szezon riportban:** `contest_type IN (1,2,3)`. A szabadidős (4) **kimarad**.
- **„Összes megye" (`filter=0`):** `contest_type = 3`.
- **Dupla induló:** aki több típuson indult, **minden érintett vödörbe** beleszámít, az összesenbe **egyszer**. A vödrök összege ezért lehet több az összesennél.
- **HTML5 `required` a kísérő-mezőkre TILOS** — 5 fix sorból elég egyet kitölteni, a `required` blokkolná a beküldést.
- **Hibakulcs-konvenció** (AJAX form): `'"mezőnév[index]"'` — az idézőjelek a kulcs részei, a `js/vespa-ajax-form.js:78` ebből épít `[name="..."]` szelektort.
- **Hatókörön kívül:** nonce bevezetése, a `legnepszerubb_sportagak` riport, a többi riport GROUP BY-a, a `bootstrap.min.css` betöltési sorrendje, a legacy escort-út (`ajax.contest_escorts.php`).

---

## Korrekciók a spec-hez képest

A terv írásakor a tényleges kód két ponton eltért attól, amit a spec feltételezett. **A terv az alábbi, helyes változatot követi:**

1. **`#e12d3b` előfordulások száma.** A spec „öt hardkódolt" előfordulást ír (`:170, :178, :185, :190, :345`). Valójában **19 db** van (`grep -c`), plusz 2 db `#1d2327`. A Task 10 **mindet** lecseréli.
2. **A kísérő mezők `name` attribútuma.** A spec szerint a hibakulcs `'"kiserok_email['.$key.']"'` lenne. De a `templates/contest_view_entering.php:396-404` a mobil/e-mail/allergia mezőket **index nélkül** rendereli (`name="kiserok_email[]"`), csak a név indexelt (`name="kiserok_nev[0]"`). Egy `[name="kiserok_email[0]"]` szelektor **nem találna semmit**, a hiba nem jelenne meg a mezőn. Ezért a **Task 6 előbb indexeli a `name` attribútumokat**, és csak utána (Task 7) épül rá a mezőszintű hibaüzenet.

## Pre-flight döntések (a végrehajtás előtt egyeztetve)

3. **A paletta külön fájlba kerül: `css/vespa-palette.css`.** A terv korábbi változata a `vespa-login.css`-ben **újra definiálta** volna a palettát, mert a login oldal nem tölti be a `vespa-admin.css`-t (`includes/Admin/login.customiser.php:18` csak a `vespa-login.css`-t enqueue-olja). Ez **ellentmondott a spec saját céljának** („egy blokkban cserélhető"): a márkaszínt két helyen kellett volna kézzel szinkronban tartani. Helyette a `:root` blokk saját fájlba kerül, és **mindkét helyen** enqueue-oljuk (Task 10: wp-admin, Task 12: login).
4. **A kísérő-inputok `id`-je is indexelt lesz** (Task 6). Ma mind az 5 sor azonos `id`-t kap (`id="kiserok_mobil[]"`), ami érvénytelen HTML. Ellenőrizve: **semmilyen CSS vagy JS nem hivatkozik ezekre az id-kre**, a javítás ingyen van, mert a sorokat úgyis átírjuk.

---

## File Structure

**Közös alap:**
- `includes/Core/vespa.model.contest.php` — **módosít**: `VespaContestType` konstans-osztály a meglévő `VespaContest` mellé. A `Core` könyvtár töltődik be elsőként (`vitarex-vespa-plugin.php:51`), így az `Export`/`Ajax` fájlokból elérhető.
- `includes/Core/functions.php` — **módosít**: `vespa_validate_email()`, `vespa_validate_phone()` a „VESPA segédfüggvények" szekcióba.
- `css/vespa-palette.css` — **ÚJ**: az egyetlen `:root` arculati paletta. A wp-admin és a login is ezt tölti be, így az arculatváltás **egy fájl** módosítása.
- `css/vespa-admin.css` — **módosít**: a 19 `#e12d3b` és 2 `#1d2327` cseréje `var(--vespa-*)`-ra.

**A5 (szezon riport):**
- `includes/Export/download_riports.php` — **módosít**: `vespa_download_riport_szezon_riport()` (`:39-252`) és `riportPartDiakok()` (`:254-284`).

**A3 (kötelező mezők):**
- `templates/contest_view_entering.php` — **módosít**: kísérő/gépkocsivezető input `name` indexelése + `type="email"` / `type="tel"`.
- `includes/Ajax/ajax.save_escorts.php` — **módosít**: formátum-validáció + `sanitize_text_field`.
- `includes/Admin/user.fields.php` — **módosít**: telefon mező (megjelenítő + mentő függvénypár a tankerület mintájára), `validate_extra()` szerepkör-forrás + telefon kötelezőség.

**A6 (arculat):**
- `css/vespa-admin.css` — **módosít**: FODISZ logó a menü tetején, `#wpcontent` háttér, `#wpfooter` rejtésének feloldása.
- `css/vespa-login.css` — **módosít**: FODISZ logó, `body.login` háttér.
- `includes/Admin/plugin.assets.php` — **módosít**: `admin_footer_text` filter.

---

## Task 1: `VespaContestType` konstansok

**Files:**
- Modify: `includes/Core/vespa.model.contest.php` (fájl eleje, a `VespaContest` osztály **elé**)

**Interfaces:**
- Produces: `VespaContestType::ORSZAGOS` (1), `::REGIONALIS` (2), `::MEGYEI` (3), `::SZABADIDOS` (4) — a Task 3, 4, 5 használja.

- [ ] **Step 1: A konstans-osztály beszúrása**

A `includes/Core/vespa.model.contest.php` elején keresd meg:

```php
<?php

class VespaContest
{
```

És cseréld erre (az új osztály a `VespaContest` **elé** kerül):

```php
<?php

/**
 * A vespa_contest_types tábla azonosítói.
 *
 * A pluginban ezek az értékek ~40 helyen magic numberként szerepelnek; ez az
 * osztály egyelőre CSAK a szezon riportban (includes/Export/download_riports.php)
 * van használatban. A többi előfordulás átírása szándékosan nem része ennek a
 * csomagnak.
 */
class VespaContestType
{
	const ORSZAGOS   = 1;
	const REGIONALIS = 2;
	const MEGYEI     = 3;
	const SZABADIDOS = 4;
}

class VespaContest
{
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Core/vespa.model.contest.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Ellenőrzés — a konstansok tényleg megvannak**

Run: `grep -n "const ORSZAGOS\|const REGIONALIS\|const MEGYEI\|const SZABADIDOS" includes/Core/vespa.model.contest.php`
Expected: 4 találat.

- [ ] **Step 4: Commit**

```bash
git add includes/Core/vespa.model.contest.php
git commit -m "feat: VespaContestType konstansok (a szezon riporthoz)"
```

---

## Task 2: Validációs helperek — e-mail és telefon

**Files:**
- Modify: `includes/Core/functions.php` (a „VESPA segédfüggvények" szekció elejére, a `vespa_get_contest_athlete_filter()` **elé**)

**Interfaces:**
- Produces: `vespa_validate_email($value): bool`, `vespa_validate_phone($value): bool` — a Task 7 és Task 9 használja.

- [ ] **Step 1: A két helper beszúrása**

A `includes/Core/functions.php`-ben keresd meg:

```php
// =============================================
// VESPA segédfüggvények
// =============================================

function vespa_get_contest_athlete_filter($filter, $contest = null)
```

És cseréld erre (a két új függvény a `vespa_get_contest_athlete_filter` **elé** kerül):

```php
// =============================================
// VESPA segédfüggvények
// =============================================

/**
 * E-mail cím formátum-ellenőrzése.
 *
 * Üres értékre false-t ad. Az "üres-e" és a "jó formátumú-e" kérdést a hívó
 * külön kezeli (a kísérőnél az üres sor legális, a testnevelő telefonjánál nem).
 */
function vespa_validate_email($value)
{
    if (!is_string($value) || trim($value) === '') {
        return false;
    }

    return (bool) is_email(trim($value));
}

/**
 * Telefonszám formátum-ellenőrzése.
 *
 * SZÁNDÉKOSAN MEGENGEDŐ: a cél a nyilvánvaló szemét ("asdf", "12") kiszűrése,
 * nem a formátum-rendészet. Egy szigorú magyar minta valós, helyes számokat
 * utasítana el (külföldi kísérő, mellék stb.).
 *
 * Átmegy:  +36 30 123 4567 | 06-30/123-4567 | 0630 1234567 | +43 664 1234567
 * Elbukik: "asdf" | "12" | "telefon: kérdezd Marit"
 */
function vespa_validate_phone($value)
{
    if (!is_string($value) || trim($value) === '') {
        return false;
    }

    // Az elválasztók (szóköz, kötőjel, zárójel, pont, perjel) eltávolítása.
    $cleaned = preg_replace('/[\s\-\(\)\.\/]/', '', trim($value));

    // Vezető + megengedett, utána csak számjegy.
    if (!preg_match('/^\+?[0-9]+$/', $cleaned)) {
        return false;
    }

    // A számjegyek darabszáma: 7..15 (a 15 az E.164 felső korlátja,
    // a 7 megenged egy rövid vezetékes számot körzet nélkül).
    $digits = strlen(preg_replace('/[^0-9]/', '', $cleaned));

    return $digits >= 7 && $digits <= 15;
}

function vespa_get_contest_athlete_filter($filter, $contest = null)
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Core/functions.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: A helperek viselkedésének ellenőrzése (önálló PHP futtatás)**

A `vespa_validate_phone()` nem függ WordPresstől, így közvetlenül tesztelhető. Futtasd:

```bash
php -r '
function vespa_validate_phone($value) {
    if (!is_string($value) || trim($value) === "") { return false; }
    $cleaned = preg_replace("/[\s\-\(\)\.\/]/", "", trim($value));
    if (!preg_match("/^\+?[0-9]+$/", $cleaned)) { return false; }
    $digits = strlen(preg_replace("/[^0-9]/", "", $cleaned));
    return $digits >= 7 && $digits <= 15;
}
$jo   = ["+36 30 123 4567", "06-30/123-4567", "0630 1234567", "+43 664 1234567"];
$rossz = ["asdf", "12", "telefon: kerdezd Marit", "", "++3630"];
foreach ($jo as $v)    { echo (vespa_validate_phone($v) ? "OK  " : "BUKO") . " (vart: OK)   [$v]\n"; }
foreach ($rossz as $v) { echo (vespa_validate_phone($v) ? "OK  " : "BUKO") . " (vart: BUKO) [$v]\n"; }
'
```

Expected: az első 4 sor `OK (vart: OK)`, az utolsó 5 sor `BUKO (vart: BUKO)`. Ha bármelyik eltér, a regexet javítsd, mielőtt továbbmész.

> A `vespa_validate_email()` a WP `is_email()`-jére épül, ezért WordPress nélkül nem futtatható — a böngészős végigjátszáskor (Záró ellenőrzés) ellenőrizzük.

- [ ] **Step 4: Commit**

```bash
git add includes/Core/functions.php
git commit -m "feat: vespa_validate_email + vespa_validate_phone helperek"
```

---

## Task 3: A5 — a szűrési logika (négy ág + institutionId + isset-védelem)

**Files:**
- Modify: `includes/Export/download_riports.php:51-120` (`vespa_download_riport_szezon_riport()`)

**Interfaces:**
- Consumes: `VespaContestType::*` (Task 1).

- [ ] **Step 1: Paraméter-olvasás isset-védelemmel + institutionId**

Keresd meg (`:51-57`):

```php
    $filter = $_GET['filter'];
    $seriesId = $_GET['series'];
    $schoolDistrict = $_GET['schoolDistrict'];
    $gender = $_GET['gender'];
    $disabilityGroupId = $_GET['disabilityGroupId'];
    $year = isset($_GET['year']) ? $_GET['year'] : 0;
    $filterType = '';
```

Cseréld erre:

```php
    // Minden paraméter isset-védett: a hiányzó GET paraméter PHP warningot
    // írna a válaszba, ami a binárisan írt XLSX-et is elronthatja.
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $seriesId = isset($_GET['series']) ? $_GET['series'] : 0;
    $schoolDistrict = isset($_GET['schoolDistrict']) ? $_GET['schoolDistrict'] : 0;
    $gender = isset($_GET['gender']) ? $_GET['gender'] : '';
    $disabilityGroupId = isset($_GET['disabilityGroupId']) ? $_GET['disabilityGroupId'] : 0;
    $institutionId = isset($_GET['institutionId']) ? $_GET['institutionId'] : 0;
    $year = isset($_GET['year']) ? $_GET['year'] : 0;
    $filterType = '';
```

- [ ] **Step 2: A `filterType` (Szűrés-leírás) kiszámítása**

Keresd meg a kikommentezett blokkot (`:59-69`):

```php
    // if($filter == 'all') $filterType = 'Összes verseny';
    // else if ($filter == 'country') $filterType = "Csak országos versenyek";
    // else if (is_numeric($filter) && $filter > 0) {
    //     $st = $wpdb->get_row("SELECT * FROM vespa_states WHERE state_id=$filter");
    //     $filterType = "Megyei versenyek - $st->state_name";
    // }
    // else if (is_numeric($schoolDistrict) && $schoolDistrict > 0) {
    //     $sd = $wpdb->get_row("SELECT * FROM vespa_school_districts WHERE school_district_id=$schoolDistrict");
    //     $filterType = "Tankerületi versenyek - $sd->school_district_name";
    // }
    // else die;
```

Cseréld erre:

```php
    // A szűrés leírása az XLSX fejlécébe. Az ágak sorrendje kötött: a 0
    // ("Összes megye") ellenőrzése a > 0 ág UTÁN áll.
    // A $stateRow változónév szándékos: a lenti tanév-blokk a $st-t használja.
    if ($filter === 'country') {
        $filterType = 'Csak országos versenyek';
    } elseif (is_numeric($filter) && $filter > 0) {
        $stateRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_states WHERE state_id=%d", intval($filter)));
        $filterType = 'Megyei versenyek - ' . ($stateRow ? $stateRow->state_name : $filter);
    } elseif (is_numeric($filter) && intval($filter) === 0) {
        $filterType = 'Összes megyei verseny';
    } else {
        $filterType = 'Összes verseny (országos + regionális + megyei)';
    }
```

- [ ] **Step 3: A `Szűrés` sor kiírása a fejlécbe**

Keresd meg (`:76-81`):

```php
        if (is_numeric($year) && $year > 0) {
            $sheet->setCellValue('C' . $ind, "Naptári év: $year");
        }
        $ind += 2;
    }
    else die;
```

Cseréld erre:

```php
        if (is_numeric($year) && $year > 0) {
            $sheet->setCellValue('C' . $ind, "Naptári év: $year");
        }
        $ind++;
        $sheet
            ->setCellValue('A' . $ind, 'Szűrés')
            ->setCellValue('B' . $ind, $filterType);
        $ind += 2;
    }
    else die;
```

- [ ] **Step 4: A szűrő négy ága**

Keresd meg (`:95-100`):

```php
    if ($filter == 'country') {
        $sql .= " AND vc.contest_type=1";
    } elseif (is_numeric($filter) && $filter > 0) { 
        $sql .= " AND vc.contest_type=3 AND vc.state_id=%d";
        $params[] = $filter;
    }
```

Cseréld erre:

```php
    // Mind a NÉGY ág kötelező. Korábban az 'all' és a 0 ("Összes megye") ág
    // hiányzott, ezért ezekben az esetekben SEMMILYEN contest_type feltétel nem
    // került a lekérdezésbe -> a szabadidős (4) versenyek is beleszámítottak, és
    // az "Összes megye" ugyanazt adta, mint az "Összes".
    // Az ágak sorrendje kötött: a 0 ellenőrzése a > 0 ág UTÁN áll.
    if ($filter === 'country') {
        $sql .= " AND vc.contest_type = %d";
        $params[] = VespaContestType::ORSZAGOS;
    } elseif (is_numeric($filter) && $filter > 0) {
        $sql .= " AND vc.contest_type = %d AND vc.state_id = %d";
        $params[] = VespaContestType::MEGYEI;
        $params[] = intval($filter);
    } elseif (is_numeric($filter) && intval($filter) === 0) {
        // "Összes megye"
        $sql .= " AND vc.contest_type = %d";
        $params[] = VespaContestType::MEGYEI;
    } else {
        // 'all' — a riport pontosan ezt a három vödröt jeleníti meg,
        // a szabadidős (4) szándékosan kimarad.
        $sql .= " AND vc.contest_type IN (%d,%d,%d)";
        $params[] = VespaContestType::ORSZAGOS;
        $params[] = VespaContestType::REGIONALIS;
        $params[] = VespaContestType::MEGYEI;
    }
```

- [ ] **Step 5: Az `institutionId` szűrő bekötése**

Keresd meg (`:102-105`):

```php
    if (is_numeric($schoolDistrict) && $schoolDistrict > 0) {
        $sql .= " AND vi.school_district_id=%d";
        $params[] = $schoolDistrict;
    }
```

Cseréld erre (az új blokk a meglévő **után**):

```php
    if (is_numeric($schoolDistrict) && $schoolDistrict > 0) {
        $sql .= " AND vi.school_district_id=%d";
        $params[] = $schoolDistrict;
    }

    // Az intézmény-választást a felület eddig is elküldte
    // (templates/riports_dashboard.php:235) és a mezőt is megjelenítette,
    // de a backend soha nem olvasta ki -> némán hatástalan volt.
    if (is_numeric($institutionId) && $institutionId > 0) {
        $sql .= " AND vi.institution_id = %d";
        $params[] = intval($institutionId);
    }
```

- [ ] **Step 6: Szintaxis-ellenőrzés**

Run: `php -l includes/Export/download_riports.php`
Expected: `No syntax errors detected`

- [ ] **Step 7: Ellenőrzés — mind a négy ág megvan**

Run: `grep -c "VespaContestType::" includes/Export/download_riports.php`
Expected: **pontosan `6`** — `country` ág 1 db (ORSZAGOS), `>0` ág 1 db (MEGYEI), `0` ág 1 db (MEGYEI), `all` ág 3 db (ORSZAGOS+REGIONALIS+MEGYEI). A Task 4 ezt később 15-re növeli.

Run: `grep -c "institutionId" includes/Export/download_riports.php`
Expected: `2` — a paraméter-olvasás és a szűrő.

- [ ] **Step 8: Commit**

```bash
git add includes/Export/download_riports.php
git commit -m "fix: szezon riport szűrő - hiányzó 'all' és 'Összes megye' ág + institutionId"
```

---

## Task 4: A5 — GROUP BY és aggregálás

**Files:**
- Modify: `includes/Export/download_riports.php:122-240`

**Interfaces:**
- Consumes: `VespaContestType::*` (Task 1); a Task 3-ban felépített `$sql` / `$params`.

> **A task lényege:** a `GROUP BY va.athlete_id` sportolónként egyetlen sorra redukál, és a nem aggregált `vc.contest_type` értéket a MySQL **nemdeterminisztikusan** választja. Aki megyein és országoson is indult, önkényesen az egyik vödörbe kerül. A `(athlete_id, contest_type)` páronkénti csoportosítás ezt megszünteti.

- [ ] **Step 1: A GROUP BY módosítása**

Keresd meg (`:122`):

```php
$sql .= " GROUP BY va.athlete_id";
```

Cseréld erre:

```php
// (diák, versenytípus) páronként egy sor. Így a típus szerinti vödrök
// determinisztikusak; a több típuson induló diák minden érintett vödörbe
// beleszámít. Az "össz" és a nemek szerinti bontás ezért athlete_id szerint
// egyedivé tesz (lásd lent).
$sql .= " GROUP BY va.athlete_id, vc.contest_type";
```

- [ ] **Step 2: Az összesen és a nemek szerinti bontás egyedivé tétele**

Keresd meg (`:126-145`):

```php
    $ferfi = 0;
    $no = 0;
    foreach ($data as $row) {
        if($row->gender == 'férfi') $ferfi++;
        else if($row->gender == 'nő') $no++;
    }
```

Cseréld erre:

```php
    // A GROUP BY (athlete_id, contest_type) miatt a több típuson induló diák
    // több sorban szerepel. Az "össz" és a nemek szerinti bontás EGYEDI diákot
    // számol; a típusonkénti vödrök viszont a tényleges részvételt mutatják,
    // ezért azok összege TÖBB lehet, mint az összesen.
    $egyediDiakok = array();
    foreach ($data as $row) {
        $egyediDiakok[$row->athlete_id] = $row;
    }

    $ferfi = 0;
    $no = 0;
    foreach ($egyediDiakok as $row) {
        if($row->gender == 'férfi') $ferfi++;
        else if($row->gender == 'nő') $no++;
    }
```

- [ ] **Step 3: Az összesen-cella egyedi diákot számoljon**

Keresd meg (`:141-145`):

```php
    $sheet
        ->setCellValue('A' . $ind, 'Fogyatékkal élő diák össz:')
        ->setCellValue('C' . $ind, count($data))
        ->setCellValue('E' . $ind, $ferfi)
        ->setCellValue('F' . $ind, $no);
```

Cseréld erre:

```php
    $sheet
        ->setCellValue('A' . $ind, 'Fogyatékkal élő diák össz:')
        ->setCellValue('C' . $ind, count($egyediDiakok))
        ->setCellValue('E' . $ind, $ferfi)
        ->setCellValue('F' . $ind, $no);
```

- [ ] **Step 4: A vödrök konstansra állítása**

Keresd meg (`:155-163`):

```php
    riportPartDiakok($sheet, $ind, $data, function ($fn){
        return $fn->contest_type == 1;
    }, 'országos');
    riportPartDiakok($sheet, $ind, $data, function ($fn){
        return $fn->contest_type == 2;
    }, 'regionális');
    riportPartDiakok($sheet, $ind, $data, function ($fn){
        return $fn->contest_type == 3;
    }, 'megyei');
```

Cseréld erre:

```php
    riportPartDiakok($sheet, $ind, $data, function ($fn){
        return $fn->contest_type == VespaContestType::ORSZAGOS;
    }, 'országos');
    riportPartDiakok($sheet, $ind, $data, function ($fn){
        return $fn->contest_type == VespaContestType::REGIONALIS;
    }, 'regionális');
    riportPartDiakok($sheet, $ind, $data, function ($fn){
        return $fn->contest_type == VespaContestType::MEGYEI;
    }, 'megyei');
```

- [ ] **Step 5: A redundáns lekérdezés törlése**

Keresd meg (`:165-169`):

```php
    $data = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    $iskArr = array();
    foreach ($data as $row) {
        array_push($iskArr, $row->institution_id);
    }
```

Cseréld erre (az első sor **törlendő** — azonos `$sql`-lel és `$params`-szal futott, mint a `:124`, az eredmény szükségszerűen ugyanaz):

```php
    $iskArr = array();
    foreach ($data as $row) {
        array_push($iskArr, $row->institution_id);
    }
```

- [ ] **Step 6: A típusonkénti iskolaszámok konstansra állítása**

Keresd meg (a `:181` körül):

```php
        if($row->contest_type == 1) array_push($iskArr, $row->institution_id);
```

Cseréld erre:

```php
        if($row->contest_type == VespaContestType::ORSZAGOS) array_push($iskArr, $row->institution_id);
```

Keresd meg (a `:195` körül):

```php
        if($row->contest_type == 2) array_push($iskArr, $row->institution_id);
```

Cseréld erre:

```php
        if($row->contest_type == VespaContestType::REGIONALIS) array_push($iskArr, $row->institution_id);
```

Keresd meg (a `:209` körül):

```php
        if($row->contest_type == 3) array_push($iskArr, $row->institution_id);
```

Cseréld erre:

```php
        if($row->contest_type == VespaContestType::MEGYEI) array_push($iskArr, $row->institution_id);
```

- [ ] **Step 7: A `str_replace` keresési mintájának frissítése — NÉMA TÖRÉS KOCKÁZATA**

Keresd meg (`:221`):

```php
    $sqlContests = str_replace('GROUP BY va.athlete_id', 'GROUP BY vc.contest_id', $sql);
```

Cseréld erre:

```php
    // FIGYELEM: a keresett szövegnek PONTOSAN egyeznie kell a fenti GROUP BY-jal.
    // Ha nem talál egyezést, a str_replace némán az eredeti $sql-t adja vissza,
    // a versenyszámlálás a diák szerinti GROUP BY-jal fut, és HIBAÜZENET NÉLKÜL
    // rossz értéket ad.
    $sqlContests = str_replace('GROUP BY va.athlete_id, vc.contest_type', 'GROUP BY vc.contest_id', $sql);
```

- [ ] **Step 8: A versenyszám-vödrök konstansra állítása**

Keresd meg (`:232-240`):

```php
        ->setCellValue('C' . $ind, count(array_filter($data, function ($fn) {
            return $fn->contest_type == 1;
        })))
        ->setCellValue('E' . $ind, count(array_filter($data, function ($fn) {
            return $fn->contest_type == 2;
        })))
        ->setCellValue('F' . $ind, count(array_filter($data, function ($fn) {
            return $fn->contest_type == 3;
        })));
```

Cseréld erre:

```php
        ->setCellValue('C' . $ind, count(array_filter($data, function ($fn) {
            return $fn->contest_type == VespaContestType::ORSZAGOS;
        })))
        ->setCellValue('E' . $ind, count(array_filter($data, function ($fn) {
            return $fn->contest_type == VespaContestType::REGIONALIS;
        })))
        ->setCellValue('F' . $ind, count(array_filter($data, function ($fn) {
            return $fn->contest_type == VespaContestType::MEGYEI;
        })));
```

- [ ] **Step 9: Szintaxis-ellenőrzés**

Run: `php -l includes/Export/download_riports.php`
Expected: `No syntax errors detected`

- [ ] **Step 10: Ellenőrzés — a str_replace mintája egyezik a GROUP BY-jal**

Run:
```bash
grep -n "GROUP BY va.athlete_id, vc.contest_type" includes/Export/download_riports.php
```
Expected: **pontosan 2 találat** — egy a `$sql .= " GROUP BY ..."` sorban, egy a `str_replace` első argumentumában. Ha csak 1 jön, a `str_replace` mintája nem egyezik → néma törés.

Run: `grep -c "contest_type == 1\|contest_type == 2\|contest_type == 3" includes/Export/download_riports.php`
Expected: **`0`** a teljes fájlban. (A kiinduló állapotban 9 ilyen sor van — `:156`, `:159`, `:162`, `:181`, `:195`, `:209`, `:233`, `:236`, `:239` — és **mind a 9 a szezon riporton belül** van. A fájl többi riportja más formában hivatkozik a típusra, ezt a `grep` nem érinti.)

Run: `grep -c "VespaContestType::" includes/Export/download_riports.php`
Expected: **`15`** — a Task 3-ból 6, plusz itt 9 (3 vödör + 3 iskolaszám + 3 versenyszám).

- [ ] **Step 11: Commit**

```bash
git add includes/Export/download_riports.php
git commit -m "fix: szezon riport GROUP BY (athlete_id, contest_type) - determinisztikus vödrök"
```

---

## Task 5: A5 — kozmetika (címkék, átfedés-megjegyzés, változónév)

**Files:**
- Modify: `includes/Export/download_riports.php:148-219` és `:254-256`

- [ ] **Step 1: Az átfedés-megjegyzés beszúrása a bontás fölé**

Keresd meg (`:148-153`):

```php
    $sheet
        ->setCellValue('B' . $ind, 'Fogyatékossági csoport')
        ->setCellValue('C' . $ind, 'Létszám')
        ->setCellValue('E' . $ind, 'Fiú')
        ->setCellValue('F' . $ind, 'Lány');
    $ind++;
```

Cseréld erre:

```php
    $sheet
        ->setCellValue('A' . $ind, 'Megjegyzés: aki több versenytípuson is indult, minden érintett bontásban szerepel, ezért a bontások összege több lehet, mint a fenti összesen.');
    $ind++;

    $sheet
        ->setCellValue('B' . $ind, 'Fogyatékossági csoport')
        ->setCellValue('C' . $ind, 'Létszám')
        ->setCellValue('E' . $ind, 'Fiú')
        ->setCellValue('F' . $ind, 'Lány');
    $ind++;
```

- [ ] **Step 2: Az „Országos" iskolaszám-címke javítása**

Keresd meg (a `:188-190` körül, az `'Országos'` feliratú blokk alatt):

```php
    $sheet
        ->setCellValue('A' . $ind, 'Iskolák száma megye mind:')
        ->setCellValue('C' . $ind, count(array_unique($iskArr)));
    $ind += 2;

    $iskArr = array();
    foreach ($data as $row) {
        if($row->contest_type == VespaContestType::REGIONALIS) array_push($iskArr, $row->institution_id);
    }
```

Cseréld erre:

```php
    $sheet
        ->setCellValue('A' . $ind, 'Iskolák száma országos:')
        ->setCellValue('C' . $ind, count(array_unique($iskArr)));
    $ind += 2;

    $iskArr = array();
    foreach ($data as $row) {
        if($row->contest_type == VespaContestType::REGIONALIS) array_push($iskArr, $row->institution_id);
    }
```

- [ ] **Step 3: A „Regionális" iskolaszám-címke javítása**

Keresd meg (a `:202-204` körül, a `'Regionális'` feliratú blokk alatt):

```php
    $sheet
        ->setCellValue('A' . $ind, 'Iskolák száma megye mind:')
        ->setCellValue('C' . $ind, count(array_unique($iskArr)));
    $ind += 2;

    $iskArr = array();
    foreach ($data as $row) {
        if($row->contest_type == VespaContestType::MEGYEI) array_push($iskArr, $row->institution_id);
    }
```

Cseréld erre:

```php
    $sheet
        ->setCellValue('A' . $ind, 'Iskolák száma regionális:')
        ->setCellValue('C' . $ind, count(array_unique($iskArr)));
    $ind += 2;

    $iskArr = array();
    foreach ($data as $row) {
        if($row->contest_type == VespaContestType::MEGYEI) array_push($iskArr, $row->institution_id);
    }
```

- [ ] **Step 4: A „Megyei" iskolaszám-címke javítása**

Keresd meg (a `:216-219` körül — ez az utolsó ilyen blokk, közvetlenül a `$sqlContests = str_replace(...)` sor **előtt**):

```php
    $sheet
        ->setCellValue('A' . $ind, 'Iskolák száma megye mind:')
        ->setCellValue('C' . $ind, count(array_unique($iskArr)));
    $ind += 2;
```

Cseréld erre:

```php
    $sheet
        ->setCellValue('A' . $ind, 'Iskolák száma megyei:')
        ->setCellValue('C' . $ind, count(array_unique($iskArr)));
    $ind += 2;
```

- [ ] **Step 5: A `riportPartDiakok()` félrevezető változónevének javítása**

Keresd meg (`:254-265`):

```php
function riportPartDiakok($sheet, &$ind, $dataArr, $filter, $typeLabel){
    $megyei = array_filter($dataArr, $filter);
    $sheet
        ->setCellValue('A' . $ind, "Fogyatékossági csoport bontás $typeLabel:")
        ->setCellValue('B' . $ind, $typeLabel)
        ->setCellValue('C' . $ind, count($megyei))
        ->setCellValue('E' . $ind, count(array_filter($megyei, function ($fn) {
            return $fn->gender == 'férfi';
        })))
        ->setCellValue('F' . $ind,count(array_filter($megyei, function ($fn) {
            return $fn->gender == 'nő';
        })));
```

Cseréld erre (a `$megyei` → `$rows`; a függvény országos/regionális bontásnál is fut):

```php
function riportPartDiakok($sheet, &$ind, $dataArr, $filter, $typeLabel){
    $rows = array_filter($dataArr, $filter);
    $sheet
        ->setCellValue('A' . $ind, "Fogyatékossági csoport bontás $typeLabel:")
        ->setCellValue('B' . $ind, $typeLabel)
        ->setCellValue('C' . $ind, count($rows))
        ->setCellValue('E' . $ind, count(array_filter($rows, function ($fn) {
            return $fn->gender == 'férfi';
        })))
        ->setCellValue('F' . $ind,count(array_filter($rows, function ($fn) {
            return $fn->gender == 'nő';
        })));
```

- [ ] **Step 6: A `$megyei` maradék előfordulásainak átnevezése ugyanabban a függvényben**

Keresd meg (`:267-270`):

```php
    $megyeiDis = array();
    foreach ($megyei as $row) {
        $megyeiDis[$row->disability_group_name][] = $row;
    }
    foreach ($megyeiDis as $group => $arr){
```

Cseréld erre:

```php
    $rowsDis = array();
    foreach ($rows as $row) {
        $rowsDis[$row->disability_group_name][] = $row;
    }
    foreach ($rowsDis as $group => $arr){
```

- [ ] **Step 7: Szintaxis-ellenőrzés**

Run: `php -l includes/Export/download_riports.php`
Expected: `No syntax errors detected`

- [ ] **Step 8: Ellenőrzés — nem maradt átnevezetlen változó és duplikált címke**

Run: `sed -n '254,290p' includes/Export/download_riports.php | grep -c "megyei"`
Expected: `0` (a `riportPartDiakok()`-ban már nincs `$megyei` / `$megyeiDis`).

Run: `grep -c "Iskolák száma megye mind:" includes/Export/download_riports.php`
Expected: `0`.

Run: `grep -n "Iskolák száma" includes/Export/download_riports.php`
Expected: 4 találat — `össz:`, `országos:`, `regionális:`, `megyei:`.

- [ ] **Step 9: Commit**

```bash
git add includes/Export/download_riports.php
git commit -m "fix: szezon riport címkék + átfedés-megjegyzés + változónév"
```

---

## Task 6: A3 — kísérő input `name` indexelése + `type="email"` / `type="tel"`

**Files:**
- Modify: `templates/contest_view_entering.php:389-440`

**Interfaces:**
- Produces: `name="kiserok_mobil[0]"`, `name="kiserok_email[0]"`, `name="kiserok_allergia[0]"` (és `gepkocsivezeto_*` párjaik) — a Task 7 hibakulcsai ezekre a `name`-ekre mutatnak.

> **Miért kell ez a task a Task 7 ELŐTT:** a mobil/e-mail/allergia mezők ma **index nélküli** `name`-mel renderelődnek (`name="kiserok_email[]"`), csak a név indexelt. A Task 7 mezőszintű hibaüzenete `[name="kiserok_email[0]"]` szelektorral keresné a mezőt — ami **semmit nem találna**. A `js/`-ben semmi nem hivatkozik ezekre a `name`-ekre (ellenőrizve), a PHP pedig ugyanazt a 0..4 indexű tömböt kapja, így a változás biztonságos.
>
> **`required` NEM kerül rájuk:** 5 fix sorból elég egyet kitölteni, a `required` a négy üres sor miatt blokkolná a beküldést.

- [ ] **Step 1: A kísérő-sor mezőinek átírása**

Keresd meg (`:395-405`):

```php
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="kiserok_mobil[]" id="kiserok_mobil[]" value="<?php echo (isset($kisero_data['kiserok'][$i]) ? $kisero_data['kiserok'][$i]['mobil'] : ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="kiserok_email[]" id="kiserok_email[]" value="<?php echo (isset($kisero_data['kiserok'][$i]) ? $kisero_data['kiserok'][$i]['email'] : ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="kiserok_allergia[]" id="kiserok_allergia[]" value="<?php echo (isset($kisero_data['kiserok'][$i]) ? $kisero_data['kiserok'][$i]['allergia'] : ''); ?>">
                                    </div>
```

Cseréld erre:

```php
                                    <div class="col-md-3">
                                        <input type="tel" class="form-control" name="<?php echo "kiserok_mobil[$i]" ?>" id="<?php echo "kiserok_mobil[$i]" ?>" value="<?php echo (isset($kisero_data['kiserok'][$i]) ? $kisero_data['kiserok'][$i]['mobil'] : ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <input type="email" class="form-control" name="<?php echo "kiserok_email[$i]" ?>" id="<?php echo "kiserok_email[$i]" ?>" value="<?php echo (isset($kisero_data['kiserok'][$i]) ? $kisero_data['kiserok'][$i]['email'] : ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="<?php echo "kiserok_allergia[$i]" ?>" id="<?php echo "kiserok_allergia[$i]" ?>" value="<?php echo (isset($kisero_data['kiserok'][$i]) ? $kisero_data['kiserok'][$i]['allergia'] : ''); ?>">
                                    </div>
```

> Az `id` is indexelt lesz (pre-flight 4. döntés): ma mind az 5 sor azonos
> `id="kiserok_mobil[]"`-t kap, ami érvénytelen HTML. Ellenőrizve: semmilyen
> CSS vagy JS nem hivatkozik ezekre az id-kre.

- [ ] **Step 2: A gépkocsivezető-sor mezőinek átírása**

Keresd meg (`:428-438`):

```php
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="gepkocsivezeto_mobil[]" id="gepkocsivezeto_mobil[]" value="<?php echo (isset($kisero_data['gepkocsivezetok'][$i]) ? $kisero_data['gepkocsivezetok'][$i]['mobil'] : ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="gepkocsivezeto_email[]" id="gepkocsivezeto_email[]" value="<?php echo (isset($kisero_data['gepkocsivezetok'][$i]) ? $kisero_data['gepkocsivezetok'][$i]['email'] : ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="gepkocsivezeto_allergia[]" id="gepkocsivezeto_allergia[]" value="<?php echo (isset($kisero_data['gepkocsivezetok'][$i]) ? $kisero_data['gepkocsivezetok'][$i]['allergia'] : ''); ?>">
                                    </div>
```

Cseréld erre:

```php
                                    <div class="col-md-3">
                                        <input type="tel" class="form-control" name="<?php echo "gepkocsivezeto_mobil[$i]" ?>" id="<?php echo "gepkocsivezeto_mobil[$i]" ?>" value="<?php echo (isset($kisero_data['gepkocsivezetok'][$i]) ? $kisero_data['gepkocsivezetok'][$i]['mobil'] : ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <input type="email" class="form-control" name="<?php echo "gepkocsivezeto_email[$i]" ?>" id="<?php echo "gepkocsivezeto_email[$i]" ?>" value="<?php echo (isset($kisero_data['gepkocsivezetok'][$i]) ? $kisero_data['gepkocsivezetok'][$i]['email'] : ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="<?php echo "gepkocsivezeto_allergia[$i]" ?>" id="<?php echo "gepkocsivezeto_allergia[$i]" ?>" value="<?php echo (isset($kisero_data['gepkocsivezetok'][$i]) ? $kisero_data['gepkocsivezetok'][$i]['allergia'] : ''); ?>">
                                    </div>
```

- [ ] **Step 3: Szintaxis-ellenőrzés**

Run: `php -l templates/contest_view_entering.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Ellenőrzés — nem maradt index nélküli `name`, és nincs `required`**

Run: `grep -n 'name="kiserok_mobil\[\]"\|name="kiserok_email\[\]"\|name="kiserok_allergia\[\]"\|name="gepkocsivezeto_mobil\[\]"\|name="gepkocsivezeto_email\[\]"\|name="gepkocsivezeto_allergia\[\]"' templates/contest_view_entering.php`
Expected: **nincs találat** (a `grep` `1`-es kilépési kóddal tér vissza).

Run: `sed -n '389,441p' templates/contest_view_entering.php | grep -c "required"`
Expected: `0` — a `required` a feltételesen kötelező sorok miatt tilos.

Run: `sed -n '389,441p' templates/contest_view_entering.php | grep -c 'type="email"\|type="tel"'`
Expected: `4` (2 e-mail + 2 telefon).

- [ ] **Step 5: Commit**

```bash
git add templates/contest_view_entering.php
git commit -m "feat: kísérő/gépkocsivezető mezők - indexelt name + type=email/tel"
```

---

## Task 7: A3 — kísérő szerver oldali formátum-validáció + sanitizálás

**Files:**
- Modify: `includes/Ajax/ajax.save_escorts.php:82-126`

**Interfaces:**
- Consumes: `vespa_validate_email()`, `vespa_validate_phone()` (Task 2); a Task 6 indexelt `name`-jei.

> A kötelezőség **ma is működik** (`:83-103`: mind a 4 mező kell, ha bármelyik ki van töltve; `:128-134`: legalább 1 kísérő + 1 gépkocsivezető). Ez a task **csak a formátum-ellenőrzést és a sanitizálást** teszi hozzá.

- [ ] **Step 1: A kísérő-ág kiegészítése**

Keresd meg (`:82-103`):

```php
        foreach( $_POST['kiserok_nev'] as $key => $knev ){
        	if( 
        		! empty($knev) 
        		&& ! empty( $_POST['kiserok_mobil'][$key] )
        		&& ! empty( $_POST['kiserok_email'][$key] )
        		&& ! empty( $_POST['kiserok_allergia'][$key] )
        	){
        		$has_escort = true;

        		$data['kiserok'][] = array(
					'nev' => $_POST['kiserok_nev'][$key],
					'mobil' => $_POST['kiserok_mobil'][$key],
					'email' => $_POST['kiserok_email'][$key],
					'allergia' => $_POST['kiserok_allergia'][$key],
        		);
        	}
					else if(! empty($knev) 
					|| ! empty( $_POST['kiserok_mobil'][$key] )
					|| ! empty( $_POST['kiserok_email'][$key] )
					|| ! empty( $_POST['kiserok_allergia'][$key] ))
					$errors_kiserok['"kiserok_nev['.$key.']"'] = 'A kísérő összes adatát szükséges kitölteni';
        }
```

Cseréld erre:

```php
        foreach( $_POST['kiserok_nev'] as $key => $knev ){
        	if( 
        		! empty($knev) 
        		&& ! empty( $_POST['kiserok_mobil'][$key] )
        		&& ! empty( $_POST['kiserok_email'][$key] )
        		&& ! empty( $_POST['kiserok_allergia'][$key] )
        	){
        		// A $has_escort hibás formátumnál is true marad: a lenti
        		// "Legalább egy kísérőt" hiba empty($errors_kiserok)-ra van kötve,
        		// így a formátum-hibát nem írná felül egy félrevezető üzenet.
        		$has_escort = true;

        		if( ! vespa_validate_email( $_POST['kiserok_email'][$key] ) ){
        			$errors_kiserok['"kiserok_email['.$key.']"'] = 'Érvénytelen e-mail cím';
        		}

        		if( ! vespa_validate_phone( $_POST['kiserok_mobil'][$key] ) ){
        			$errors_kiserok['"kiserok_mobil['.$key.']"'] = 'Érvénytelen telefonszám';
        		}

        		$data['kiserok'][] = array(
					'nev' => sanitize_text_field( $_POST['kiserok_nev'][$key] ),
					'mobil' => sanitize_text_field( $_POST['kiserok_mobil'][$key] ),
					'email' => sanitize_text_field( $_POST['kiserok_email'][$key] ),
					'allergia' => sanitize_text_field( $_POST['kiserok_allergia'][$key] ),
        		);
        	}
					else if(! empty($knev) 
					|| ! empty( $_POST['kiserok_mobil'][$key] )
					|| ! empty( $_POST['kiserok_email'][$key] )
					|| ! empty( $_POST['kiserok_allergia'][$key] ))
					$errors_kiserok['"kiserok_nev['.$key.']"'] = 'A kísérő összes adatát szükséges kitölteni';
        }
```

- [ ] **Step 2: A gépkocsivezető-ág kiegészítése**

Keresd meg (`:105-126`):

```php
        foreach( $_POST['gepkocsivezeto_nev'] as $key => $knev ){
        	if( 
        		! empty($knev) 
        		&& ! empty( $_POST['gepkocsivezeto_mobil'][$key] )
        		&& ! empty( $_POST['gepkocsivezeto_email'][$key] )
        		&& ! empty( $_POST['gepkocsivezeto_allergia'][$key] )
        	){
        		$has_driver = true;

        		$data['gepkocsivezetok'][] = array(
					'nev' => $_POST['gepkocsivezeto_nev'][$key],
					'mobil' => $_POST['gepkocsivezeto_mobil'][$key],
					'email' => $_POST['gepkocsivezeto_email'][$key],
					'allergia' => $_POST['gepkocsivezeto_allergia'][$key],
        		);
        	}
					else if(! empty($knev) 
					|| ! empty( $_POST['gepkocsivezeto_mobil'][$key] )
					|| ! empty( $_POST['gepkocsivezeto_email'][$key] )
					|| ! empty( $_POST['gepkocsivezeto_allergia'][$key] ))
					$errors_gkvezetok['"gepkocsivezeto_nev['.$key.']"'] = 'A gépkocsivezető összes adatát szükséges kitölteni';					
        }       
```

Cseréld erre:

```php
        foreach( $_POST['gepkocsivezeto_nev'] as $key => $knev ){
        	if( 
        		! empty($knev) 
        		&& ! empty( $_POST['gepkocsivezeto_mobil'][$key] )
        		&& ! empty( $_POST['gepkocsivezeto_email'][$key] )
        		&& ! empty( $_POST['gepkocsivezeto_allergia'][$key] )
        	){
        		$has_driver = true;

        		if( ! vespa_validate_email( $_POST['gepkocsivezeto_email'][$key] ) ){
        			$errors_gkvezetok['"gepkocsivezeto_email['.$key.']"'] = 'Érvénytelen e-mail cím';
        		}

        		if( ! vespa_validate_phone( $_POST['gepkocsivezeto_mobil'][$key] ) ){
        			$errors_gkvezetok['"gepkocsivezeto_mobil['.$key.']"'] = 'Érvénytelen telefonszám';
        		}

        		$data['gepkocsivezetok'][] = array(
					'nev' => sanitize_text_field( $_POST['gepkocsivezeto_nev'][$key] ),
					'mobil' => sanitize_text_field( $_POST['gepkocsivezeto_mobil'][$key] ),
					'email' => sanitize_text_field( $_POST['gepkocsivezeto_email'][$key] ),
					'allergia' => sanitize_text_field( $_POST['gepkocsivezeto_allergia'][$key] ),
        		);
        	}
					else if(! empty($knev) 
					|| ! empty( $_POST['gepkocsivezeto_mobil'][$key] )
					|| ! empty( $_POST['gepkocsivezeto_email'][$key] )
					|| ! empty( $_POST['gepkocsivezeto_allergia'][$key] ))
					$errors_gkvezetok['"gepkocsivezeto_nev['.$key.']"'] = 'A gépkocsivezető összes adatát szükséges kitölteni';					
        }       
```

- [ ] **Step 3: Szintaxis-ellenőrzés**

Run: `php -l includes/Ajax/ajax.save_escorts.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Ellenőrzés — a validáció és a sanitizálás a helyén van**

Run: `grep -c "vespa_validate_email\|vespa_validate_phone" includes/Ajax/ajax.save_escorts.php`
Expected: `4` (2 kísérő + 2 gépkocsivezető).

Run: `grep -c "sanitize_text_field" includes/Ajax/ajax.save_escorts.php`
Expected: `8` (2 × 4 mező).

Run: `grep -n 'errors_kiserok\[.\"kiserok_email\|errors_kiserok\[.\"kiserok_mobil' includes/Ajax/ajax.save_escorts.php`
Expected: 2 találat — a hibakulcsok a Task 6-ban indexelt `name`-ekre mutatnak.

- [ ] **Step 5: Commit**

```bash
git add includes/Ajax/ajax.save_escorts.php
git commit -m "feat: kísérő/gépkocsivezető e-mail + telefon formátum-validáció és sanitizálás"
```

---

## Task 8: A3 — telefon mező a felhasználói profilon

**Files:**
- Modify: `includes/Admin/user.fields.php` (a „Tankerület" szekció **után**, a `### közös` komment **elé**)

**Interfaces:**
- Consumes: `vespa_validate_phone()` (Task 2) — a Task 9 használja a validációhoz.
- Produces: `phone` user meta; `#phone` wrapper id (a Task 9 JS-e ezt mutogatja); `vespa_extra_user_profile_fields4()`, `vespa_save_extra_user_profile_fields4()`.

> **A tankerület (`...3`) mintáját követjük, NEM az iskoláét.** Az iskola mentője
> (`vespa_save_extra_user_profile_fields:110`) a `testnevelok_letrehozas_modositasa`
> caphez van kötve — amivel a **testnevelő nem rendelkezik**. Ha a telefon mentése
> ezt a mintát másolná, a testnevelő soha nem tudná elmenteni a saját
> telefonszámát, miközben a Task 9 validációja követelné → **kizárná magát a
> profiljából**. A `...3` (tankerület) mentője csak `edit_user`-t néz — ez a helyes.
>
> Külön függvénypár kell, mert a `vespa_save_extra_user_profile_fields:119` a
> school_id mentése után **`return;`-nel kilép** — az utána fűzött kód sosem futna.

- [ ] **Step 1: A megjelenítő és mentő függvénypár beszúrása**

A `includes/Admin/user.fields.php`-ben keresd meg (`:297-300`):

```php
    return false;
}

    ### közös
    add_action( 'user_profile_update_errors', 'validate_extra' );
```

És cseréld erre (az új szekció a `### közös` **elé** kerül):

```php
    return false;
}

##############
## TELEFON  ##
##############


//------------------------------
add_action( 'show_user_profile', 'vespa_extra_user_profile_fields4' );
add_action( 'edit_user_profile', 'vespa_extra_user_profile_fields4' );
add_action( 'user_new_form', 'vespa_extra_user_profile_fields4' );

function vespa_extra_user_profile_fields4( $user ) {

        // Input hiba esetén ne vesszen el a már beírt szám!
        $phone = isset($_POST['phone']) ? $_POST['phone'] : '';

        // Új felhasználónál a $user objektum még nem létezik!
        if ( isset( $user ) && is_object( $user ) && isset( $user->ID ) ) {
            $phone = get_user_meta( $user->ID, 'phone', true );
        }
    ?>
        <table class="form-table" id="phone_row" style="display: none;">
        <tr>
            <th><label for="phone">Telefonszám: (kötelező)</label></th>
            <td>
                <input type="tel" class="regular-text" name="phone" id="phone" value="<?php echo esc_attr($phone); ?>">
            </td>
        </tr>
        </table>
    <?php
}

add_action( 'user_register', 'vespa_save_extra_user_profile_fields4');
add_action( 'personal_options_update', 'vespa_save_extra_user_profile_fields4' );
add_action( 'edit_user_profile_update', 'vespa_save_extra_user_profile_fields4' );

function vespa_save_extra_user_profile_fields4( $user_id ) {
    if ( !current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }

    // FIGYELEM: itt SZÁNDÉKOSAN nincs extra capability-feltétel (szemben az
    // iskola mentőjével). A testnevelőnek a saját telefonszámát el kell tudnia
    // menteni, különben a validate_extra() örökre kizárná a profiljából.
    if( isset($_POST['phone']) ){
        update_user_meta( $user_id, 'phone', sanitize_text_field( $_POST['phone'] ) );
        return true;
    }

    return false;
}

    ### közös
    add_action( 'user_profile_update_errors', 'validate_extra' );
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Admin/user.fields.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Ellenőrzés — a függvénypár és a hookok megvannak**

Run: `grep -c "vespa_extra_user_profile_fields4\|vespa_save_extra_user_profile_fields4" includes/Admin/user.fields.php`
Expected: `8` (3 + 3 hook regisztráció + 2 függvénydefiníció).

Run: `grep -n "testnevelok_letrehozas_modositasa" includes/Admin/user.fields.php`
Expected: **csak 1 találat**, a `:110` körül (az iskola mentőjében). Ha a telefon mentőjében is megjelenik, a testnevelő nem tudná menteni a saját számát.

- [ ] **Step 4: Commit**

```bash
git add includes/Admin/user.fields.php
git commit -m "feat: telefonszám mező a felhasználói profilon (phone user meta)"
```

---

## Task 9: A3 — `validate_extra()` szerepkör-forrás + telefon kötelezőség

**Files:**
- Modify: `includes/Admin/user.fields.php` (`validate_extra()` és `handleFields()`)

**Interfaces:**
- Consumes: `vespa_validate_phone()` (Task 2); `#phone_row` wrapper (Task 8).

> **A `$user` paraméter NEM `WP_User`.** A WP core az `user_profile_update_errors`
> hookot az `edit_user()`-ből (`wp-admin/includes/user.php`) hívja, és a `$user` ott
> egy **`stdClass`**, amit a POST-ból épít. Nincs rajta `->roles` tömb; a `->role`
> mező pedig **csak akkor** kap értéket, ha `$_POST['role']` létezik — vagyis pont
> abban az esetben nem, amiért az egészet javítjuk. `$update === true` esetén
> viszont a `->ID` mindig ki van töltve, és abból a valódi szerepkör lekérdezhető.
>
> **Miért számít:** a `role` legördülő csak a `user-new.php`-n és a `user-edit.php`-n
> létezik. Saját profil mentésekor (`profile.php`) nincs `$_POST['role']` → a
> validáció némán kimaradna, és a „csak mentéskor kényszerít" döntés pont a
> leggyakoribb esetben (a testnevelő maga tölti ki) nem érvényesülne.

- [ ] **Step 1: A `validate_extra()` átírása**

Keresd meg (`:300-324`):

```php
    add_action( 'user_profile_update_errors', 'validate_extra' );
    function validate_extra(&$errors, $update = null, &$user  = null)
    {
        if(($_POST['role'] == VESPA_Roles::MEGYEI_VEZETO || 
            $_POST['role'] == VESPA_Roles::MEGYEI_VERSENYIGAZGATO) &&
            !(isset($_POST['state_id']) && is_numeric($_POST['state_id'])))
        {
            $errors->add('state', "<strong>Hiba</strong>: Megye megadása kötelező.");
        }

        if(($_POST['role'] == VESPA_Roles::TESTNEVELO || 
            $_POST['role'] == VESPA_Roles::TANULO || 
            $_POST['role'] == VESPA_Roles::ISKOLAIGAZGATO || 
            $_POST['role'] == VESPA_Roles::SPORTOLO) &&
            !(isset($_POST['school_id']) && is_numeric($_POST['school_id'])))
        {
            $errors->add('school', "<strong>Hiba</strong>: Iskola megadása kötelező.");
        }

        if(($_POST['role'] == VESPA_Roles::TANKERULETI_IGAZGATO) &&
            !(isset($_POST['school_district_id']) && is_numeric($_POST['school_district_id'])))
        {
            $errors->add('district', "<strong>Hiba</strong>: Tankerület megadása kötelező.");
        }
    }
```

Cseréld erre:

```php
    add_action( 'user_profile_update_errors', 'validate_extra' );
    function validate_extra(&$errors, $update = null, &$user  = null)
    {
        // A szerepkör forrása NEM lehet pusztán a $_POST['role']: a role legördülő
        // csak a user-new.php-n és a user-edit.php-n létezik. Saját profil
        // mentésekor (profile.php) nincs a POST-ban, ilyenkor a ténylegesen
        // szerkesztett userből olvassuk ki.
        // FIGYELEM: a $user itt stdClass (az edit_user() építi a POST-ból),
        // NEM WP_User -- nincs rajta ->roles tömb, ezért kell a get_userdata().
        $role = '';
        if (isset($_POST['role']) && $_POST['role'] !== '') {
            $role = $_POST['role'];                      // user-new.php / user-edit.php
        } elseif (!empty($user->ID)) {
            $u = get_userdata($user->ID);                // profile.php (saját profil)
            if ($u && !empty($u->roles)) {
                $role = $u->roles[0];
            }
        }

        if(($role == VESPA_Roles::MEGYEI_VEZETO || 
            $role == VESPA_Roles::MEGYEI_VERSENYIGAZGATO) &&
            !(isset($_POST['state_id']) && is_numeric($_POST['state_id'])))
        {
            $errors->add('state', "<strong>Hiba</strong>: Megye megadása kötelező.");
        }

        if(($role == VESPA_Roles::TESTNEVELO || 
            $role == VESPA_Roles::TANULO || 
            $role == VESPA_Roles::ISKOLAIGAZGATO || 
            $role == VESPA_Roles::SPORTOLO) &&
            !(isset($_POST['school_id']) && is_numeric($_POST['school_id'])))
        {
            $errors->add('school', "<strong>Hiba</strong>: Iskola megadása kötelező.");
        }

        if(($role == VESPA_Roles::TANKERULETI_IGAZGATO) &&
            !(isset($_POST['school_district_id']) && is_numeric($_POST['school_district_id'])))
        {
            $errors->add('district', "<strong>Hiba</strong>: Tankerület megadása kötelező.");
        }

        // A telefonszám csak a testnevelőnek kötelező. A meglévő, telefon nélküli
        // testnevelők érintetlenek maradnak: a kényszer csak mentéskor lép életbe.
        if($role == VESPA_Roles::TESTNEVELO &&
            !(isset($_POST['phone']) && vespa_validate_phone($_POST['phone'])))
        {
            $errors->add('phone', "<strong>Hiba</strong>: Telefonszám megadása kötelező, érvényes formátumban.");
        }
    }
```

- [ ] **Step 2: A `handleFields()` JS kiegészítése — a telefon blokk mutogatása**

Keresd meg (`:333-336` körül, a `vespa_extra_user_profile_fields_scripts()`-en belül):

```php
        <script>
            const schoolRoles = ['testnevelo', 'sportolo', 'iskolaigazgato', 'tanulo']
            const stateRoles = ['megyei_vezeto', 'megyei_versenyigazgato']
            const district = ['tankeruleti_igazgato']
```

Cseréld erre:

```php
        <script>
            const schoolRoles = ['testnevelo', 'sportolo', 'iskolaigazgato', 'tanulo']
            const stateRoles = ['megyei_vezeto', 'megyei_versenyigazgato']
            const district = ['tankeruleti_igazgato']
            const phoneRoles = ['testnevelo']
```

- [ ] **Step 3: A telefon blokk mutogatásának bekötése a `handleFields()`-be**

Keresd meg (`:344-346` körül):

```php
            function handleFields(){
                const role = jQuery('#role').val()
                if(schoolRoles.includes(role)){
```

Cseréld erre:

```php
            function handleFields(){
                const role = jQuery('#role').val()

                // A telefon blokk a szerepkör-ágaktól függetlenül kezelhető:
                // csak a testnevelőnél jelenik meg.
                if(phoneRoles.includes(role)){
                    jQuery('#phone_row').show()
                } else {
                    jQuery('#phone_row').hide()
                    jQuery('#phone').val('')
                }

                if(schoolRoles.includes(role)){
```

- [ ] **Step 4: Szintaxis-ellenőrzés**

Run: `php -l includes/Admin/user.fields.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Ellenőrzés — nem maradt `$_POST['role']` a feltételekben**

Run: `sed -n '300,375p' includes/Admin/user.fields.php | grep -c "\$_POST\['role'\]"`
Expected: `1` — kizárólag a `$role` kiszámításánál. Ha több, maradt átírtatlan feltétel.

Run: `grep -c "phoneRoles\|#phone_row" includes/Admin/user.fields.php`
Expected: `4` (a `phoneRoles` definíció + használat, a `#phone_row` show + hide).

- [ ] **Step 6: Commit**

```bash
git add includes/Admin/user.fields.php
git commit -m "fix: validate_extra szerepkör-forrás (profile.php) + testnevelő telefon kötelezőség"
```

---

## Task 10: A6 — külön paletta-fájl + a hardkódolt színek cseréje (wp-admin)

**Files:**
- Create: `css/vespa-palette.css` (az egyetlen `:root` blokk)
- Modify: `css/vespa-admin.css` (19 db `#e12d3b` + 2 db `#1d2327` cseréje)
- Modify: `includes/Admin/plugin.assets.php` (a paletta enqueue-olása a wp-adminban)

**Interfaces:**
- Produces: `css/vespa-palette.css` a `--vespa-brand`, `--vespa-brand-dark`, `--vespa-dark`, `--vespa-bg`, `--vespa-bg-image` változókkal — a Task 11 (admin) és Task 12 (login) is ezt tölti be. Az arculatváltás így **egyetlen fájl** módosítása (a spec „egy blokkban cserélhető" célja).

> **Pre-flight 3. döntés:** a `:root` NEM a `vespa-admin.css`-be kerül, hanem külön
> `vespa-palette.css`-be, mert a login oldal nem tölti be a `vespa-admin.css`-t
> (`login.customiser.php:18`). Külön fájllal a paletta egy helyen él, és a wp-admin
> és a login is ugyanazt tölti be.

- [ ] **Step 1: A paletta-fájl létrehozása**

Hozd létre a `css/vespa-palette.css` fájlt ezzel a tartalommal:

```css
/* =============================================
   VESPA arculati paletta — EGYETLEN forrás
   A márkaszín és a háttér itt, egy helyen cserélhető. A wp-admin
   (css/vespa-admin.css) és a login (css/vespa-login.css) is ezeket a
   változókat használja; ezt a fájlt mindkét felület betölti.
   Ha megjön a tényleges arculati anyag, csak ezt a fájlt kell módosítani.
   ============================================= */
:root {
  --vespa-brand: #e12d3b;         /* a meglévő márkapiros, változatlanul */
  --vespa-brand-dark: #b02430;    /* ~20%-kal sötétebb, hover/aktív állapothoz */
  --vespa-dark: #1d2327;          /* a meglévő WP-menü sötét, változatlanul */
  --vespa-bg: #f0f0f1;            /* a WP alap admin háttér */
  --vespa-bg-image: linear-gradient(160deg, rgba(225, 45, 59, 0.04) 0%, rgba(225, 45, 59, 0) 55%);
}
```

- [ ] **Step 2: A kiinduló állapot rögzítése**

Run: `grep -c "e12d3b" css/vespa-admin.css`
Expected: `19`

Run: `grep -c "1d2327" css/vespa-admin.css`
Expected: `2`

> Ha az értékek eltérnek, **ne** futtasd a lenti `sed`-et — nézd meg `grep -n`-nel, mi változott.

- [ ] **Step 3: A színek cseréje változó-hivatkozásra**

Run:
```bash
sed -i '' 's/#e12d3b/var(--vespa-brand)/g' css/vespa-admin.css
sed -i '' 's/#1d2327/var(--vespa-dark)/g' css/vespa-admin.css
```

> A `!important`-os előfordulásokkal (`:69`, `:452`) is működik:
> `color: var(--vespa-brand) !important;` érvényes CSS.
> A `vespa-admin.css`-be **nem** kerül `:root` — a paletta a külön fájlban van.

- [ ] **Step 4: A csere ellenőrzése**

Run: `grep -c "e12d3b\|1d2327" css/vespa-admin.css`
Expected: `0` — egyetlen hardkódolt érték sem maradt.

Run: `grep -c "var(--vespa-brand)" css/vespa-admin.css`
Expected: `19`

Run: `grep -c "var(--vespa-dark)" css/vespa-admin.css`
Expected: `2`

Run: `grep -c ":root" css/vespa-admin.css`
Expected: `0` — a `:root` a külön paletta-fájlban van, nem itt.

- [ ] **Step 5: A paletta enqueue-olása a wp-adminban**

A `includes/Admin/plugin.assets.php`-ben keresd meg (`:17-18`):

```php
        wp_enqueue_style('datetimepicker_css', VITAREX_VESPA_PLUGIN_URI . 'css/jquery.datetimepicker.min.css');
        wp_enqueue_style('vespa_admin_css', VITAREX_VESPA_PLUGIN_URI . 'css/vespa-admin.css?v=' . time());
```

Cseréld erre (a paletta **a `vespa-admin.css` előtt** töltődik, hogy a változók készen álljanak):

```php
        wp_enqueue_style('datetimepicker_css', VITAREX_VESPA_PLUGIN_URI . 'css/jquery.datetimepicker.min.css');
        wp_enqueue_style('vespa_palette_css', VITAREX_VESPA_PLUGIN_URI . 'css/vespa-palette.css?v=' . time());
        wp_enqueue_style('vespa_admin_css', VITAREX_VESPA_PLUGIN_URI . 'css/vespa-admin.css?v=' . time());
```

> A CSS `var()` a betöltési sorrendtől függetlenül feloldódik (a `:root`
> változók globálisak), de a paletta előre sorolása egyértelművé teszi a szándékot.

- [ ] **Step 6: Szintaxis-ellenőrzés**

Run: `php -l includes/Admin/plugin.assets.php`
Expected: `No syntax errors detected`

- [ ] **Step 7: Ellenőrzés — a paletta-fájl és az enqueue a helyén van**

Run: `head -12 css/vespa-palette.css | grep -c "vespa-brand:\|vespa-brand-dark:\|vespa-dark:\|vespa-bg:\|vespa-bg-image:"`
Expected: `5`

Run: `grep -c "vespa-palette.css" includes/Admin/plugin.assets.php`
Expected: `1`

- [ ] **Step 8: Vizuális regresszió-ellenőrzés (böngésző)**

Nyisd meg a wp-admin bármelyik VESPA oldalát (pl. Versenyek). A piros kiemelésnek **pontosan ugyanúgy** kell kinéznie, mint a változtatás előtt: az aktív menüpont háttere, a menü hover, a `.btn-primary`, a checkboxok, a táblázat-fejléc.

Expected: nincs látható különbség (a `--vespa-brand` értéke azonos a korábbi hardkódolt színnel).

- [ ] **Step 9: Commit**

```bash
git add css/vespa-palette.css css/vespa-admin.css includes/Admin/plugin.assets.php
git commit -m "refactor: külön vespa-palette.css - a hardkódolt színek változóra cserélve"
```

---

## Task 11: A6 — FODISZ logó (menü teteje + lábléc) és admin háttér

**Files:**
- Modify: `css/vespa-admin.css` (a `/* arculat */` szekció végére, és a `#wpfooter` rejtés)
- Modify: `includes/Admin/plugin.assets.php` (`admin_footer_text` filter)

**Interfaces:**
- Consumes: `--vespa-bg`, `--vespa-bg-image` (Task 10); `VITAREX_VESPA_VERSION` (`vitarex-vespa-plugin.php:14`); `images/FODISZ_fekvo_logo_color.jpg`.

> **Az admin bar logó szándékosan kimarad** (8. döntés): az admin menü tetejétől
> kb. 40 pixelre lenne, két FODISZ logó közvetlenül egymás alatt. A
> `#wp-admin-bar-wp-logo` rejtése **változatlan marad**.

- [ ] **Step 1: A `#wpfooter` rejtésének feloldása**

Keresd meg (`css/vespa-admin.css`, a `:root` beszúrása után kb. `:56`):

```css
#wp-admin-bar-wp-logo,
#wpfooter {
  display: none;
}
```

Cseréld erre (a `#wpfooter` kikerül a rejtésből — a lábléc a FODISZ logót fogja hordozni):

```css
/* A WP saját logója rejtve marad; a lábléc viszont a FODISZ logót hordozza,
   ezért újra láthatóvá tesszük (lásd includes/Admin/plugin.assets.php). */
#wp-admin-bar-wp-logo {
  display: none;
}
```

- [ ] **Step 2: A FODISZ logó, a háttér és a lábléc-stílus hozzáadása**

Fűzd a `css/vespa-admin.css` **végére**:

```css

/* =============================================
   VESPA arculat — FODISZ logó és háttér
   ============================================= */

/* FODISZ logó az admin menü tetején. Tiszta CSS, PHP hook nélkül. */
#adminmenuwrap::before {
  content: "";
  display: block;
  height: 56px;
  margin: 0;
  background-color: var(--vespa-dark);
  background-image: url('../images/FODISZ_fekvo_logo_color.jpg');
  background-size: 120px auto;
  background-position: center center;
  background-repeat: no-repeat;
}

/* Összecsukott menünél (WP .folded, ill. a szűk képernyős auto-fold)
   a fekvő logó nem férne ki -> elrejtjük. */
.folded #adminmenuwrap::before,
.auto-fold #adminmenuwrap::before {
  display: none;
}

@media screen and (max-width: 960px) {
  #adminmenuwrap::before {
    display: none;
  }
}

/* Visszafogott VESPA háttér. SZÁNDÉKOSAN alig érzékelhető: a tartalom
   táblázat, űrlap és riport - egy erős minta olvashatatlanná tenné. */
#wpcontent,
#wpbody-content {
  background-color: var(--vespa-bg);
  background-image: var(--vespa-bg-image);
  background-repeat: no-repeat;
  background-attachment: fixed;
}

/* Lábléc a FODISZ logóval. */
#wpfooter {
  display: block;
  padding: 10px 20px;
  color: #646970;
}

#wpfooter .vespa-footer {
  display: flex;
  align-items: center;
  gap: 10px;
}

#wpfooter .vespa-footer img {
  height: 24px;
  width: auto;
}
```

- [ ] **Step 3: Az `admin_footer_text` filter hozzáadása**

A `includes/Admin/plugin.assets.php`-ben keresd meg:

```php
class VESPA_Assets extends Singleton
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'addAdminScriptsStyles'));
    }
```

Cseréld erre:

```php
class VESPA_Assets extends Singleton
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'addAdminScriptsStyles'));
        add_filter('admin_footer_text', array($this, 'adminFooterText'));
        add_filter('update_footer', '__return_empty_string', 11);
    }

    /**
     * FODISZ logó + VESPA verzió a wp-admin láblécében.
     * A #wpfooter rejtését a css/vespa-admin.css oldja fel.
     */
    public function adminFooterText($text)
    {
        return '<span class="vespa-footer">'
            . '<img src="' . esc_url(VITAREX_VESPA_PLUGIN_URI . 'images/FODISZ_fekvo_logo_color.jpg') . '" alt="FODISZ">'
            . '<span>VESPA ' . esc_html(VITAREX_VESPA_VERSION) . '</span>'
            . '</span>';
    }
```

- [ ] **Step 4: Szintaxis-ellenőrzés**

Run: `php -l includes/Admin/plugin.assets.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Ellenőrzés — a logófájl létezik és a hivatkozás stimmel**

Run: `ls -la images/FODISZ_fekvo_logo_color.jpg`
Expected: a fájl létezik (~57 KB).

Run: `grep -c "FODISZ_fekvo_logo_color.jpg" css/vespa-admin.css includes/Admin/plugin.assets.php`
Expected: `css/vespa-admin.css:1` és `includes/Admin/plugin.assets.php:1`.

Run: `grep -n "wp-admin-bar-wp-logo" css/vespa-admin.css`
Expected: 1 találat, továbbra is `display: none` — az admin bar logó szándékosan kimarad.

- [ ] **Step 6: Vizuális ellenőrzés (böngésző)**

1. Nyiss meg egy VESPA admin oldalt → a FODISZ logó megjelenik a menü tetején.
2. Görgess le → a lábléc a FODISZ logót és a `VESPA 2.3.5` szöveget mutatja.
3. A tartalom (táblázatok, űrlapok) **olvasható** marad a háttéren.
4. Csukd össze az admin menüt (a menü alján a „Menü összecsukása") → a logó eltűnik, a menü nem torzul.
5. Szűkítsd az ablakot 960px alá → a logó eltűnik, a felület nem törik.

- [ ] **Step 7: Commit**

```bash
git add css/vespa-admin.css includes/Admin/plugin.assets.php
git commit -m "feat: FODISZ logó az admin menü tetején és a láblécben + VESPA háttér"
```

---

## Task 12: A6 — login képernyő (FODISZ logó + VESPA háttér)

**Files:**
- Modify: `includes/Admin/login.customiser.php` (a paletta enqueue-olása a login oldalon)
- Modify: `css/vespa-login.css` (FODISZ logó + háttér, `:root` nélkül)

**Interfaces:**
- Consumes: `css/vespa-palette.css` (`--vespa-brand`, `--vespa-brand-dark`) — Task 10.

> **Pre-flight 3. döntés:** a login oldal `css/vespa-palette.css`-t is betölti,
> ezért a `vespa-login.css`-ben **NINCS** `:root` — ugyanazokat a változókat
> használja, mint a wp-admin. A paletta egyetlen forrásból él.

- [ ] **Step 1: A paletta enqueue-olása a login oldalon**

A `includes/Admin/login.customiser.php`-ben keresd meg a `load_login_stylesheet()` metódust:

```php
    public function load_login_stylesheet(){
        wp_enqueue_style('custom-login', VITAREX_VESPA_PLUGIN_URI . '/css/vespa-login.css');   
    }
```

Cseréld erre (a paletta **a login CSS előtt** töltődik):

```php
    public function load_login_stylesheet(){
        wp_enqueue_style('vespa-palette', VITAREX_VESPA_PLUGIN_URI . '/css/vespa-palette.css');
        wp_enqueue_style('custom-login', VITAREX_VESPA_PLUGIN_URI . '/css/vespa-login.css');   
    }
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Admin/login.customiser.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: A login FODISZ logó és a háttér hozzáadása**

Fűzd a `css/vespa-login.css` **végére**:

```css

/* =============================================
   VESPA arculat — login képernyő
   A paletta a css/vespa-palette.css-ből jön (a login.customiser.php tölti be),
   ezért itt NINCS :root — a var(--vespa-*) változók onnan oldódnak fel.
   ============================================= */
body.login {
	background-color: #f0f0f1;
	background-image:
		radial-gradient(circle at 15% 12%, rgba(225, 45, 59, 0.16) 0%, rgba(225, 45, 59, 0) 42%),
		radial-gradient(circle at 85% 88%, rgba(176, 36, 48, 0.14) 0%, rgba(176, 36, 48, 0) 46%),
		linear-gradient(150deg, #ffffff 0%, #f2f3f5 100%);
	background-repeat: no-repeat;
	background-attachment: fixed;
	background-size: cover;
}

/* FODISZ logó a VESPA logó alatt. */
.login h1 {
	margin-bottom: 12px;
}

.login h1::after {
	content: "";
	display: block;
	width: 200px;
	height: 40px;
	margin: 0 auto 16px;
	background-image: url('../images/FODISZ_fekvo_logo_color.jpg');
	background-size: contain;
	background-position: center center;
	background-repeat: no-repeat;
}

/* A bejelentkező doboz a mintás háttéren is olvasható maradjon. */
.login form {
	box-shadow: 0 2px 12px rgba(0, 0, 0, 0.12);
	border: 1px solid rgba(0, 0, 0, 0.06);
}

.login .button-primary {
	background: var(--vespa-brand);
	border-color: var(--vespa-brand);
}

.login .button-primary:hover,
.login .button-primary:focus {
	background: var(--vespa-brand-dark);
	border-color: var(--vespa-brand-dark);
}
```

- [ ] **Step 4: Ellenőrzés — a fájl hivatkozásai helyesek, és nincs duplikált `:root`**

Run: `grep -c "FODISZ_fekvo_logo_color.jpg" css/vespa-login.css`
Expected: `1`

Run: `grep -c "vespa_logo.png" css/vespa-login.css`
Expected: `2` — a meglévő VESPA logó (a `.login h1 a` két `background-image` sora) érintetlen maradt.

Run: `grep -c ":root" css/vespa-login.css`
Expected: `0` — a paletta a `vespa-palette.css`-ből jön, itt nincs újradefiniálva.

- [ ] **Step 5: Vizuális ellenőrzés (böngésző)**

1. Jelentkezz ki, és nyisd meg a `wp-login.php`-t.
2. A VESPA logó a helyén, alatta a FODISZ logó, a háttér márkaszínű, lágy.
3. A bejelentkező doboz olvasható, a „Bejelentkezés" gomb piros.
4. **A bejelentkezés működik** — a stílus nem takar el semmit.

- [ ] **Step 6: Commit**

```bash
git add includes/Admin/login.customiser.php css/vespa-login.css
git commit -m "feat: login képernyő - FODISZ logó, VESPA háttér, közös paletta"
```

---

## Záró ellenőrzés (manuális, böngészőben)

- [ ] **Step 1: Minden módosított PHP fájl szintaxis-ellenőrzése**

Run:
```bash
php -l includes/Core/vespa.model.contest.php
php -l includes/Core/functions.php
php -l includes/Export/download_riports.php
php -l templates/contest_view_entering.php
php -l includes/Ajax/ajax.save_escorts.php
php -l includes/Admin/user.fields.php
php -l includes/Admin/plugin.assets.php
```
Expected: mindegyik `No syntax errors detected`.

- [ ] **Step 2: A5 — a szűrő-mátrix végigjátszása**

A Riportok → Szezon riport felületen, ugyanazzal a tanévvel, **négy** letöltés:

1. **Összes** → a szabadidős (4) versenyek diákjai **nem** szerepelnek; a „Szűrés" sor: `Összes verseny (országos + regionális + megyei)`.
2. **Csak országos** → csak `contest_type=1` adatok; a „Szűrés" sor: `Csak országos versenyek`.
3. **Összes megye** → **eltér az „Összes"-től** (ez volt a bug!); csak megyei adatok; a „Szűrés" sor: `Összes megyei verseny`.
4. **Konkrét megye** → csak az adott megye; a „Szűrés" sor: `Megyei versenyek - <megye neve>`.

- [ ] **Step 3: A5 — a dupla induló ellenőrzése (a javítás lényege)**

Keress (vagy készíts elő a DB-ben) egy diákot, aki ugyanabban a szezonban **megyein ÉS országoson is** indult. Az „Összes" riportban:

- a diák **mindkét** bontásban (országos ÉS megyei) szerepel;
- a „Fogyatékkal élő diák össz" **egyszer** számolja;
- a bontások összege ezért **több**, mint az összesen — és a fölötte lévő megjegyzés ezt megmagyarázza.

> Ez ma nemdeterminisztikus (a MySQL választ vödröt), ezért célzottan tesztelendő.

- [ ] **Step 4: A5 — a versenyszám-sor (néma törés kockázata)**

Ellenőrizd a „Diákolimpia versenyek száma" sort: a `B` oszlop (Összesen) = a `C` + `E` + `F` (Országos + Regionális + Megyei) összege. Ha nem, a `str_replace` mintája nem egyezik a GROUP BY-jal.

- [ ] **Step 5: A5 — intézmény-szűrő és hiányzó paraméter**

1. Válassz egy konkrét intézményt → a riport **szűkül** (ma némán hatástalan volt).
2. Hívd meg kézzel: `?download_riports=szezon_riport&series=<létező id>` (a többi paraméter nélkül) → az XLSX letöltődik és **megnyitható** (nincs PHP warning a fájlban).

- [ ] **Step 6: A3 — kísérő validáció**

Testnevelőként, egy nyitott verseny nevezési felületén:

1. Kísérő `"asdf"` e-maillel → **hibaüzenet az e-mail mezőn**, nincs mentés.
2. Kísérő `"12"` telefonnal → **hibaüzenet a telefon mezőn**.
3. Kísérő valós adatokkal (`+36 30 123 4567`, `nev@pelda.hu`) → **mentés sikeres**.
4. **Regresszió:** 1 kitöltött + 4 üres kísérő-sor → **mentés sikeres** (a `required` elhagyásának lényege).
5. Ugyanez a gépkocsivezetőkre.
6. Újratöltés után a mentett adatok visszatöltődnek a mezőkbe (az indexelt `name` nem törte el a visszatöltést).

- [ ] **Step 7: A3 — telefon mező**

1. Új testnevelő létrehozása (`user-new.php`) telefon nélkül → **hiba**.
2. Új testnevelő `"asdf"` telefonnal → **hiba**.
3. Új testnevelő valós számmal → **létrejön**, a szám elmentve.
4. **A kritikus eset:** testnevelőként lépj be, nyisd meg a **saját profilod** (`profile.php`), és mentsd telefon nélkül → **hiba**. Add meg a számot → **mentés sikeres, és a szám megmarad** (újratöltés után is látszik).
5. Iskolaigazgató mentése telefon nélkül → **átmegy**, a mező rejtve.
6. A `#role` legördülőn váltva a telefon blokk megjelenik/eltűnik.
7. Meglévő, telefon nélküli testnevelő: a nevezés, listák, riportok **változatlanul működnek**, amíg a profilját nem menti.

- [ ] **Step 8: A6 — arculat**

1. Login: VESPA logó + FODISZ logó + háttér, a **bejelentkezés működik**.
2. wp-admin: FODISZ logó a menü tetején, lábléc logóval + `VESPA 2.3.5`.
3. A meglévő piros kiemelés **vizuálisan azonos** a korábbival.
4. Összecsukott menü és <960px képernyő: nem törik.
5. A tartalom olvasható a háttéren.

---

## Self-Review jegyzet

**Spec-lefedettség:**

| Spec pont | Task |
|---|---|
| 0.1 `VespaContestType` | Task 1 |
| 0.2 validációs helperek | Task 2 |
| 0.3 `:root` paletta | Task 10 |
| 1.1 szűrő négy ága | Task 3 |
| 1.2 GROUP BY | Task 4 |
| 1.3 `str_replace` csapda | Task 4 (Step 7) |
| 1.4 redundáns lekérdezés | Task 4 (Step 5) |
| 1.5 `institutionId` | Task 3 (Step 5) |
| 1.6 paraméter-olvasás | Task 3 (Step 1) |
| 1.7 kozmetika | Task 5 |
| 2.1 kísérő formátum-validáció | Task 7 |
| 2.2 sanitizálás | Task 7 |
| 2.3 kliens oldal (`type=email/tel`, **nincs** `required`) | Task 6 |
| 2.4 telefon mező | Task 8 |
| 2.5 `validate_extra` szerepkör-forrás | Task 9 |
| 3.1 FODISZ logó (menü teteje, lábléc, login) | Task 11, Task 12 |
| 3.2 VESPA háttér (admin, login) | Task 11, Task 12 |
| 3.3 fájlméret | Task 11 (Step 6 vizuális ellenőrzés); ha lassulást okoz, külön átméretezés |

**Eltérések a spec-től (a terv a helyes változatot követi, indoklással a fenti „Korrekciók" és „Pre-flight döntések" szakaszban):**
1. `#e12d3b` — 19 előfordulás (a spec 5-öt írt). Task 10 mindet cseréli.
2. A kísérő `name` attribútumok indexelése (Task 6) **új, a specben nem szereplő lépés** — enélkül a Task 7 hibaüzenetei nem jelennének meg a mezőkön. Az `id`-k is indexeltek lesznek (pre-flight 4.).
3. A telefon mentője a **tankerület (`...3`) mintáját** követi, nem az iskoláét — az iskola mintája (`testnevelok_letrehozas_modositasa` cap) kizárná a testnevelőt a saját profiljából. A spec ezt nem részletezte.
4. **A paletta külön `css/vespa-palette.css`-be kerül** (Task 10), amit a wp-admin és a login is betölt (pre-flight 3.). A spec „egy blokkban cserélhető" célját a terv korábbi, duplikáló változata nem teljesítette volna; ez igen.

**Konzisztencia:**
- `VespaContestType::ORSZAGOS|REGIONALIS|MEGYEI` — definiálva Task 1, használva Task 3, 4, 5. A `SZABADIDOS` definiálva, de ebben a csomagban szándékosan nincs használva (a 4-es típus kimarad a riportból).
- `vespa_validate_email()` / `vespa_validate_phone()` — definiálva Task 2, használva Task 7 (kísérő) és Task 9 (telefon).
- `--vespa-brand` / `--vespa-dark` / `--vespa-bg` / `--vespa-bg-image` — definiálva Task 10, használva Task 11. A `--vespa-brand-dark` Task 10-ben definiálva, használva Task 12-ben (login gomb).
- A `css/vespa-login.css` **külön** definiálja a palettát (Task 12), mert a login oldal nem tölti be a `vespa-admin.css`-t — ezt a Task 12 kommentje rögzíti.
- `#phone_row` (wrapper, Task 8) vs `#phone` (input, Task 8) — a Task 9 JS-e a `#phone_row`-ot mutogatja és a `#phone`-t üríti. A `name="phone"` → `$_POST['phone']` a Task 8 mentőjében és a Task 9 validációjában.
- Hibakulcsok (Task 7): `'"kiserok_email['.$key.']"'` → `[name="kiserok_email[0]"]` — egyezik a Task 6-ban indexelt `name="kiserok_email[0]"`-val.

**Sorrendi függések (kötelező betartani):**
- Task 1, 2 → Task 3, 4, 5, 7, 9 (a konstansok és helperek előbb kellenek).
- **Task 6 → Task 7** (előbb az indexelt `name`, utána a rá mutató hibakulcs).
- **Task 10 Step 2 → Task 10 Step 4** (előbb `sed`, utána `:root` — fordítva rekurzió).
- Task 10 → Task 11, 12 (a változók előbb kellenek).
