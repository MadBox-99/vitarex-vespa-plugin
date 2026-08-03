# Riport-szűrők összehangolása — implementációs terv

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A riport-felületen mutatott minden szűrő ténylegesen szűrjön, és egyetlen `$_GET` olvasás se futhasson definiálatlan indexre.

**Architecture:** A szűrőblokkok kézi másolatai egyetlen, tiszta és unit-tesztelt helper-párra cserélődnek a `includes/Core/vespa.riport.periodus.php`-ban — a query-aliasok mentén szétvágva (`vi` = intézmény, `va` = sportoló). A `download_riports.php` hat riportfüggvénye ezeket hívja, és minden `$_GET` olvasása `isset`-védetté válik.

**Tech Stack:** PHP (WordPress plugin, `$wpdb`), PhpSpreadsheet, Vue 3 (CDN, Options API), sima PHP szkript-tesztek (`php tests/test-*.php`).

**Spec:** [docs/superpowers/specs/2026-08-03-riport-szuro-osszehangolas-design.md](../specs/2026-08-03-riport-szuro-osszehangolas-design.md)

## Global Constraints

- **Nincs helyi WordPress fejlesztői környezet.** Csak a tiszta helperek tesztelhetők automatikusan (`php tests/test-*.php`). A riportfüggvényeknél a `php -l` + kód-átolvasás az elérhető ellenőrzés; a tényleges XLSX-kimenetet kézi QA igazolja élesben. **Az automatikus teszt hiánya a riportfüggvényeknél nem hiányosság.**
- **A `$params` tömb elemeinek sorrendje pontosan kövesse a `%d`/`%s` helyőrzők sorrendjét a `$sql`-ben.** Ha elcsúszik, a `$wpdb->prepare()` rossz értékeket helyettesít be, és a riport némán rossz számokat ad. Ez a terv legfontosabb helyességi szempontja.
- **Tábla-aliasok:** `vi` = `vespa_institutions`, `va` = `vespa_athletes`, `vc` = `vespa_contests`. A helperek ezekre az aliasokra épülnek.
- **A nem fehérlistája:** csak a pontosan `'nő'` és `'férfi'` érték szűr. Minden más (`'összes'`, üres, tetszőleges szöveg) nem szűr — ez a meglévő viselkedés, marad.
- **Minden felhasználónak látszó szöveg és minden kódkomment magyar**, a kommentek a MIÉRT-et magyarázzák.
- **A `:370`, `:540`, `:650` `else die;` ágakhoz nem nyúlunk** (a felületről elérhetetlenek). Kizárólag a `legnepszerubb_sportagak` `:982` `die`-ja szűnik meg.
- **A sorszámok a terv írásakori állapotra vonatkoznak**, és a taskok során csúsznak. A kódrészletekre támaszkodj, ne a sorszámokra.
- **Gyakori commit:** minden task saját committal zárul.

---

## File Structure

| Fájl | Felelősség | Művelet |
|---|---|---|
| `includes/Core/vespa.riport.periodus.php` | +2 tiszta helper az intézmény- és sportoló-szűréshez | Módosítás |
| `tests/test-riport-szurok.php` | A két új helper unit tesztjei | Létrehozás |
| `includes/Export/download_riports.php` | `isset`-védelem mindenhol + hat függvény átállítása | Módosítás |
| `templates/riports_dashboard.php` | a „Legnépszerűbb sportágak" URL-je kiegészül négy paraméterrel | Módosítás |
| `CHANGELOG.md` | felhasználónak szóló változásleírás | Módosítás |

---

### Task 1: A két szűrő-helper és unit tesztjei

**Files:**
- Modify: `includes/Core/vespa.riport.periodus.php` (a fájl végére, a meglévő helperek után)
- Test: `tests/test-riport-szurok.php`

**Interfaces:**
- Consumes: `vespa_riport_pozitiv_egesz($ertek): bool` — a fájlban már létező helper
- Produces:
  - `vespa_riport_intezmeny_szuro($schoolDistrictId, $institutionId): array` → `array('sql' => string, 'params' => array)`
  - `vespa_riport_sportolo_szuro($disabilityGroupId, $gender): array` → `array('sql' => string, 'params' => array)`

- [ ] **Step 1: Write the failing test**

Hozd létre a `tests/test-riport-szurok.php` fájlt:

```php
<?php
/**
 * A riportok intézmény- és sportoló-szűrőjének unit tesztjei.
 * Futtatás: php tests/test-riport-szurok.php
 * WordPress nem kell hozzá: a tesztelt függvények tiszták.
 */

require_once __DIR__ . '/../includes/Core/vespa.riport.periodus.php';

$hibak = 0;

function allit($feltetel, $leiras)
{
    global $hibak;
    if ($feltetel) {
        echo "OK    " . $leiras . "\n";
    } else {
        echo "HIBA  " . $leiras . "\n";
        $hibak++;
    }
}

// ---- Intézmény-szűrő: csak tankerület ----------------------------------

$r = vespa_riport_intezmeny_szuro('7', '0');
allit($r['sql'] === ' AND vi.school_district_id=%d', 'csak tankerulet: sql');
allit($r['params'] === array(7), 'csak tankerulet: params');

// ---- Intézmény-szűrő: csak intézmény -----------------------------------

$r = vespa_riport_intezmeny_szuro('0', '42');
allit($r['sql'] === ' AND vi.institution_id=%d', 'csak intezmeny: sql');
allit($r['params'] === array(42), 'csak intezmeny: params');

// ---- Intézmény-szűrő: mindkettő ----------------------------------------
// A sorrend kötött: előbb a tankerület helyőrzője, utána az intézményé.

$r = vespa_riport_intezmeny_szuro('7', '42');
allit(
    $r['sql'] === ' AND vi.school_district_id=%d AND vi.institution_id=%d',
    'tankerulet+intezmeny: sql, tankerulet eloszor'
);
allit($r['params'] === array(7, 42), 'tankerulet+intezmeny: params sorrend');

// ---- Intézmény-szűrő: egyik sem ----------------------------------------

$r = vespa_riport_intezmeny_szuro('0', '0');
allit($r['sql'] === '' && $r['params'] === array(), 'intezmeny-szuro: egyik sem');

// ---- Intézmény-szűrő: érvénytelen bemenetek ----------------------------

foreach (array('', 'abc', '-1', 0, null, false) as $rossz) {
    $r = vespa_riport_intezmeny_szuro($rossz, $rossz);
    allit(
        $r['sql'] === '' && $r['params'] === array(),
        'intezmeny-szuro ervenytelen bemenet: ' . var_export($rossz, true)
    );
}

// ---- Sportoló-szűrő: csak fogyatékossági csoport -----------------------

$r = vespa_riport_sportolo_szuro('3', 'összes');
allit($r['sql'] === ' AND va.disability_type=%d', 'csak fogyatekossag: sql');
allit($r['params'] === array(3), 'csak fogyatekossag: params');

// ---- Sportoló-szűrő: csak nem ------------------------------------------

$r = vespa_riport_sportolo_szuro('0', 'nő');
allit($r['sql'] === ' AND va.gender=%s', 'csak nem: sql');
allit($r['params'] === array('nő'), 'csak nem: params');

$r = vespa_riport_sportolo_szuro('0', 'férfi');
allit($r['params'] === array('férfi'), 'csak nem: ferfi is szur');

// ---- Sportoló-szűrő: mindkettő -----------------------------------------
// A sorrend kötött: előbb a fogyatékossági csoport, utána a nem.

$r = vespa_riport_sportolo_szuro('3', 'nő');
allit(
    $r['sql'] === ' AND va.disability_type=%d AND va.gender=%s',
    'fogyatekossag+nem: sql, fogyatekossag eloszor'
);
allit($r['params'] === array(3, 'nő'), 'fogyatekossag+nem: params sorrend');

// ---- Sportoló-szűrő: a nem fehérlistája --------------------------------
// A felület 'összes'-t is küld, és a GET-ből bármi jöhet. Csak a pontosan
// egyező 'nő' és 'férfi' szűrhet — a fehérlista véd attól, hogy szemét érték
// kerüljön a lekérdezésbe.

foreach (array('összes', '', 'FÉRFI', 'No', 'nő ', 'egyeb', null, 0) as $rossz) {
    $r = vespa_riport_sportolo_szuro('0', $rossz);
    allit(
        $r['sql'] === '' && $r['params'] === array(),
        'nem fehErlista elutasit: ' . var_export($rossz, true)
    );
}

// ---- Sportoló-szűrő: egyik sem -----------------------------------------

$r = vespa_riport_sportolo_szuro('0', 'összes');
allit($r['sql'] === '' && $r['params'] === array(), 'sportolo-szuro: egyik sem');

// ---- Összegzés ---------------------------------------------------------

echo "\n";
if ($hibak === 0) {
    echo "Minden teszt sikeres.\n";
    exit(0);
}

echo $hibak . " teszt elbukott.\n";
exit(1);
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php tests/test-riport-szurok.php
```

Elvárt: fatális hiba — `Call to undefined function vespa_riport_intezmeny_szuro()`.

- [ ] **Step 3: Write minimal implementation**

A `includes/Core/vespa.riport.periodus.php` VÉGÉRE, a meglévő `vespa_riport_get_results()` UTÁN:

```php
/**
 * Az intézményre vonatkozó szűrés SQL-töredéke és prepared paraméterei.
 *
 * A `vi` (vespa_institutions) táblára szűr, ezért csak olyan lekérdezésben
 * használható, amely ezt az aliast ismeri. A sorrend kötött: előbb a
 * tankerület helyőrzője, utána az intézményé.
 *
 * @param mixed $schoolDistrictId  GET-ből jövő nyers érték; csak a pozitív egész szűr
 * @param mixed $institutionId     GET-ből jövő nyers érték; csak a pozitív egész szűr
 * @return array {sql: string, params: array}
 */
function vespa_riport_intezmeny_szuro($schoolDistrictId, $institutionId)
{
    $sql    = '';
    $params = array();

    if (vespa_riport_pozitiv_egesz($schoolDistrictId)) {
        $sql .= ' AND vi.school_district_id=%d';
        $params[] = (int) $schoolDistrictId;
    }

    if (vespa_riport_pozitiv_egesz($institutionId)) {
        $sql .= ' AND vi.institution_id=%d';
        $params[] = (int) $institutionId;
    }

    return array('sql' => $sql, 'params' => $params);
}

/**
 * A sportolóra vonatkozó szűrés SQL-töredéke és prepared paraméterei.
 *
 * A `va` (vespa_athletes) táblára szűr. Szándékosan külön áll az
 * intézmény-szűrőtől: a tanév-riport intézmény-listázó lekérdezése csak a `vi`
 * táblát ismeri, ott ez a töredék hibás SQL-t adna. A vágás a query-aliasok
 * mentén fut, nem tetszőlegesen.
 *
 * @param mixed $disabilityGroupId csak a pozitív egész szűr
 * @param mixed $gender            csak a pontosan 'nő' vagy 'férfi' szűr
 * @return array {sql: string, params: array}
 */
function vespa_riport_sportolo_szuro($disabilityGroupId, $gender)
{
    $sql    = '';
    $params = array();

    if (vespa_riport_pozitiv_egesz($disabilityGroupId)) {
        $sql .= ' AND va.disability_type=%d';
        $params[] = (int) $disabilityGroupId;
    }

    // Fehérlista, nem feketelista: a felület 'összes'-t is küld, a GET-ből
    // pedig bármi jöhet. Csak a két ismert érték kerülhet a lekérdezésbe.
    if ($gender === 'nő' || $gender === 'férfi') {
        $sql .= ' AND va.gender=%s';
        $params[] = $gender;
    }

    return array('sql' => $sql, 'params' => $params);
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php tests/test-riport-szurok.php
php -l includes/Core/vespa.riport.periodus.php
```

Elvárt: minden sor `OK`, a végén `Minden teszt sikeres.`, kilépési kód 0; `No syntax errors detected`.

- [ ] **Step 5: Regresszió-ellenőrzés**

```bash
for f in tests/test-*.php; do php "$f" > /dev/null || echo "ELBUKOTT: $f"; done
```

Elvárt: egyetlen `ELBUKOTT:` sor sem.

- [ ] **Step 6: Commit**

```bash
git add includes/Core/vespa.riport.periodus.php tests/test-riport-szurok.php
git commit -m "feat(riport): intezmeny- es sportolo-szuro tiszta logikaja es unit tesztjei"
```

---

### Task 2: Minden `$_GET` olvasás `isset`-védetté tétele

**Files:**
- Modify: `includes/Export/download_riports.php:346-349`, `:515-518`, `:625-631`, `:804-808`, `:963-968`

**Interfaces:**
- Consumes: semmit
- Produces: semmit további task számára

Tisztán mechanikus task: viselkedés nem változik, csak a definiálatlan indexre futó olvasások szűnnek meg. Ezek PHP-figyelmeztetést váltanak ki, ami bekapcsolt hibamegjelenítésnél a binárisan írt XLSX-be íródik.

- [ ] **Step 1: Az öt blokk átírása**

Minta a `szezon_riport` (`:53-59`) már meglévő alakja. A hiányzó paraméter alapértéke minden helyen „nincs szűrés"-t jelent, ami a mai tényleges viselkedés.

`vespa_download_riport_verseny_versenyszam()` (`:346-349`):

```php
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
    $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
    $seriesId = isset($_GET['series']) ? $_GET['series'] : 0;
```

`vespa_download_riport_versenyen_resztvevo_iskolak_szama()` (`:515-518`):

```php
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $schoolDistrict = isset($_GET['schoolDistrict']) ? $_GET['schoolDistrict'] : 0;

    $seriesId = isset($_GET['series']) ? $_GET['series'] : 0;
```

`vespa_download_riport_verseny_diak()` (`:625-631`):

```php
    $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
    $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $schoolDistrict = isset($_GET['schoolDistrict']) ? $_GET['schoolDistrict'] : 0;
    $gender = isset($_GET['gender']) ? $_GET['gender'] : '';
    $disabilityGroupId = isset($_GET['disabilityGroupId']) ? $_GET['disabilityGroupId'] : 0;
    $institutionId = isset($_GET['institutionId']) ? $_GET['institutionId'] : 0;
```

`vespa_download_riport_tanev($type)` (`:804-808`):

```php
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $schoolDistrictId = isset($_GET['schoolDistrict']) ? $_GET['schoolDistrict'] : 0;
    $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
    $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
    $seriesId = isset($_GET['series']) ? $_GET['series'] : 0;
```

`vespa_download_riport_legnepszerubb_sportagak()` (`:963-968`) — itt egy ÚJ olvasás is bekerül, az `institutionId`, mert a Task 5 használni fogja:

```php
    $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
    $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $schoolDistrictId = isset($_GET['schoolDistrict']) ? $_GET['schoolDistrict'] : 0;
    $institutionId = isset($_GET['institutionId']) ? $_GET['institutionId'] : 0;
    $gender = isset($_GET['gender']) ? $_GET['gender'] : '';
    $disabilityGroupId = isset($_GET['disabilityGroupId']) ? $_GET['disabilityGroupId'] : 0;
```

Az `:45` `$type = $_GET['download_riports'];` sorhoz **ne nyúlj** — azt a dispatcher `:11` `isset` ága már védi.

- [ ] **Step 2: Ellenőrzés — nem maradt védtelen olvasás**

```bash
php -l includes/Export/download_riports.php
grep -n '\$_GET' includes/Export/download_riports.php | grep -v 'isset('
```

Elvárt: `No syntax errors detected`, és a `grep` **pontosan két sort** ad vissza: a `:12` és a `:45` `$_GET['download_riports']` olvasását. (A `:11` maga tartalmazza az `isset`-et, ezért kiesik a szűrőből.) Ha bármi más sor megjelenik, ott maradt védtelen olvasás.

- [ ] **Step 3: Regresszió-ellenőrzés**

```bash
for f in tests/test-*.php; do php "$f" > /dev/null || echo "ELBUKOTT: $f"; done
```

- [ ] **Step 4: Commit**

```bash
git add includes/Export/download_riports.php
git commit -m "fix(riport): minden GET parameter olvasasa isset-vedett"
```

---

### Task 3: A három helyes függvény átállítása a helperekre

**Files:**
- Modify: `includes/Export/download_riports.php` — `vespa_download_riport_szezon_riport()` (`:129-152` környéke), `vespa_download_riport_versenyen_resztvevo_iskolak_szama()` (`:592-595` környéke), `vespa_download_riport_verseny_diak()` (`:697-712` környéke)

**Interfaces:**
- Consumes: `vespa_riport_intezmeny_szuro()`, `vespa_riport_sportolo_szuro()` (Task 1)
- Produces: semmit további task számára

**Ez a task tiszta refaktor: a viselkedés NEM változhat.** Ez a három függvény ma is helyesen szűr; a cél a kézi másolatok megszüntetése, hogy ne tudjanak újra elcsúszni. Ha bármelyik lépésnél viselkedésváltozást veszel észre, állj meg és jelezd.

- [ ] **Step 1: `vespa_download_riport_szezon_riport()`**

Cseréld a négy egymást követő szűrőblokkot — `vi.school_district_id`, `vi.institution_id = %d`, `va.disability_type`, `va.gender` (a `$schoolDistrict`, `$institutionId`, `$disabilityGroupId`, `$gender` változókkal) — erre:

```php
    // A szűrőblokkok kézi másolatai helyett közös helper: így nem tud egyik
    // riportban elgépelt változónévvel vagy hiányzó feltétellel elcsúszni.
    $intezmeny = vespa_riport_intezmeny_szuro($schoolDistrict, $institutionId);
    $sql      .= $intezmeny['sql'];
    $params    = array_merge($params, $intezmeny['params']);

    $sportolo = vespa_riport_sportolo_szuro($disabilityGroupId, $gender);
    $sql     .= $sportolo['sql'];
    $params   = array_merge($params, $sportolo['params']);
```

A blokk maradjon pontosan ott, ahol a régi négy blokk volt — a `$filter` ág UTÁN és a `GROUP BY` ELŐTT.

- [ ] **Step 2: `vespa_download_riport_versenyen_resztvevo_iskolak_szama()`**

Ennek a riportnak a felülete csak tankerületet mutat, intézményt nem — ezért az intézmény-argumentum `0`. Cseréld a `vi.school_district_id` blokkot erre:

```php
    // Ennek a riportnak a felülete intézményt nem kínál, ezért az
    // intézmény-argumentum fixen 0.
    $intezmeny = vespa_riport_intezmeny_szuro($schoolDistrict, 0);
    $sql      .= $intezmeny['sql'];
    $params    = array_merge($params, $intezmeny['params']);
```

- [ ] **Step 3: `vespa_download_riport_verseny_diak()`**

Ez volt az utolsó kézi másolat. Cseréld a négy blokkot (`vi.school_district_id`, `va.disability_type`, `vi.institution_id = %d`, `va.gender`) erre:

```php
    $intezmeny = vespa_riport_intezmeny_szuro($schoolDistrict, $institutionId);
    $sql      .= $intezmeny['sql'];
    $params    = array_merge($params, $intezmeny['params']);

    $sportolo = vespa_riport_sportolo_szuro($disabilityGroupId, $gender);
    $sql     .= $sportolo['sql'];
    $params   = array_merge($params, $sportolo['params']);
```

**Figyelem:** ebben a függvényben a szűrőblokkok után egy külön `$sqlAgeGroups`
lekérdezés következik, saját paraméterekkel (`$filterFrom`, `$filterTo`). Ahhoz
NE nyúlj, és győződj meg róla, hogy a `$params` tömböt nem az érinti.

- [ ] **Step 4: Ellenőrzés**

```bash
php -l includes/Export/download_riports.php
grep -n 'va.gender=%s\|va.disability_type=%d\|vi.institution_id' includes/Export/download_riports.php
```

Elvárt: `No syntax errors detected`. A `download_riports.php`-ban ezekre a mintákra **kizárólag a `vespa_download_riport_legnepszerubb_sportagak()` függvényen belül maradhat találat** — azt a Task 5 állítja át. Sorold fel a megmaradt találatokat, és igazold, hogy mind ebbe az egy függvénybe esik; ha bármelyik máshova esik, egy blokk kimaradt a cseréből.

- [ ] **Step 5: A paraméter-sorrend átolvasása**

Mindhárom módosított függvényben olvasd végig a `$sql` felépítését, és győződj meg róla, hogy a `$params` elemeinek sorrendje pontosan követi a `%d`/`%s` helyőrzők sorrendjét. Írd le függvényenként a sorrendet.

- [ ] **Step 6: Regresszió-ellenőrzés és commit**

```bash
for f in tests/test-*.php; do php "$f" > /dev/null || echo "ELBUKOTT: $f"; done
git add includes/Export/download_riports.php
git commit -m "refactor(riport): a mukodo szuroblokkok kozos helperre allitasa"
```

---

### Task 4: A tanév-riport három hiányzó szűrőjének pótlása

**Files:**
- Modify: `includes/Export/download_riports.php` — `vespa_download_riport_tanev($type)` fő lekérdezése (`:886-889` környéke) és az `$allSchool` lekérdezése (`:901-912` környéke)

**Interfaces:**
- Consumes: `vespa_riport_intezmeny_szuro()`, `vespa_riport_sportolo_szuro()` (Task 1)
- Produces: semmit további task számára

**Ez a task VISELKEDÉST változtat.** A `tanev_diakolimpia_diakok` és az
`iskola_sportoltatott_diakok` riport felülete ma is mutatja az Intézmény, a Nem és
a Fogyatékossági csoport mezőt, és a felület el is küldi az értéküket — a backend
viszont soha nem olvasta ki őket, tehát némán figyelmen kívül maradtak. A javítás
után ezek a riportok más (helyes) számokat fognak adni, ha a felhasználó él
ezekkel a szűrőkkel.

- [ ] **Step 1: A fő lekérdezés kiegészítése**

A `vespa_download_riport_tanev()` fő lekérdezésében a meglévő
`if($schoolDistrictId > 0) { ... vi.school_district_id ... }` blokkot cseréld erre:

```php
    // Az intézmény, a nem és a fogyatékossági csoport eddig hiányzott ebből a
    // riportból: a felület mutatta és el is küldte őket, a backend viszont
    // soha nem olvasta ki, ezért némán szűretlen számok jöttek ki.
    $intezmeny = vespa_riport_intezmeny_szuro($schoolDistrictId, $institutionId);
    $sql      .= $intezmeny['sql'];
    $params    = array_merge($params, $intezmeny['params']);

    $sportolo = vespa_riport_sportolo_szuro($disabilityGroupId, $gender);
    $sql     .= $sportolo['sql'];
    $params   = array_merge($params, $sportolo['params']);
```

Ehhez a függvény elején be kell olvasni a három új paramétert — a Task 2 mintája
szerint, `isset`-védetten, a `$sportEventId` olvasása UTÁN:

```php
    $institutionId = isset($_GET['institutionId']) ? $_GET['institutionId'] : 0;
    $disabilityGroupId = isset($_GET['disabilityGroupId']) ? $_GET['disabilityGroupId'] : 0;
    $gender = isset($_GET['gender']) ? $_GET['gender'] : '';
```

- [ ] **Step 2: Az `$allSchool` lekérdezés kiegészítése**

Ez a lekérdezés listázza az összes intézményt, hogy a 0 diákot indító iskolák is
megjelenjenek a riportban. Cseréld a meglévő
`if($schoolDistrictId > 0) { ... }` blokkját erre:

```php
    // Az intézmény-szűrőnek ITT IS érvényesülnie kell: e nélkül egy intézményt
    // kiválasztva egyetlen adatsor mellett több száz nullás sor jönne ki.
    // A sportoló-szűrő viszont NEM kerül ide: a nem és a fogyatékossági csoport
    // a diák tulajdonsága, nem az intézményé, és ez a lekérdezés nem is ismeri
    // a `va` aliast — csak a darabszámokat befolyásolják, a lista tagjait nem.
    $intezmeny = vespa_riport_intezmeny_szuro($schoolDistrictId, $institutionId);
    $sql      .= $intezmeny['sql'];
    $params    = array_merge($params, $intezmeny['params']);
```

A `vi.ins_state` (`$stateId`) szűrés a blokk UTÁN, változatlanul marad.

- [ ] **Step 3: Ellenőrzés**

```bash
php -l includes/Export/download_riports.php
for f in tests/test-*.php; do php "$f" > /dev/null || echo "ELBUKOTT: $f"; done
```

- [ ] **Step 4: Kód-átolvasásos ellenőrzés**

Olvasd végig a módosított függvényt, és írd le:

- mindkét lekérdezésnél a `%d`/`%s` helyőrzők sorrendjét és a `$params` elemeit;
- hogy az `$allSchool` lekérdezésbe NEM került `va.` hivatkozás (az a lekérdezés
  csak a `vi` táblát ismeri, `va.`-ra hivatkozva hibás SQL lenne);
- hogy az intervallumos ág (`iskola_sportoltatott_diakok`) `dateFrom`/`dateTo`
  feltétele változatlan.

- [ ] **Step 5: Commit**

```bash
git add includes/Export/download_riports.php
git commit -m "fix(riport): a tanev-riport vegre szur intezmenyre, nemre es fogyatekossagra"
```

---

### Task 5: A „Legnépszerűbb sportágak" négy hibájának javítása

**Files:**
- Modify: `includes/Export/download_riports.php` — `vespa_download_riport_legnepszerubb_sportagak()` (`:957`-től), a `filterType` if-lánc (`:972-982`), a `filter` SQL-ág (`:1018-1023`) és a szűrőblokkok (`:1025-1038`)

**Interfaces:**
- Consumes: `vespa_riport_intezmeny_szuro()`, `vespa_riport_sportolo_szuro()` (Task 1); a Task 2-ben bevezetett `$institutionId` olvasás
- Produces: semmit további task számára

Négy különböző hiba egy függvényben. **Ez a task is viselkedést változtat.**

- [ ] **Step 1: A `die`-ág megszüntetése a szűrés-feliratban**

Cseréld a teljes `filterType` if-láncot (`:972-982`) erre — a `szezon_riport`-nál
már bevált négyágú alakra:

```php
    // Az ágak sorrendje kötött: a 0 ("Összes megye") ellenőrzése a > 0 ág UTÁN
    // áll. Korábban a 0 egyik ágra sem illett, ezért a függvény die()-olt: az
    // "Összes megye" szűrés üres választ adott, fájl nélkül.
    if ($filter === 'country') {
        $filterType = 'Legnépszerűbb országos sportágak';
    } elseif (is_numeric($filter) && $filter > 0) {
        $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_states WHERE state_id=%d", (int) $filter));
        $filterType = 'Legnépszerűbb megyei sportágak - ' . ($st ? $st->state_name : $filter);
    } elseif (is_numeric($filter) && intval($filter) === 0) {
        $filterType = 'Legnépszerűbb megyei sportágak - összes';
    } else {
        $filterType = 'Összes verseny';
    }
```

A záró `else die;` teljesen eltűnik.

- [ ] **Step 2: A `filter == 0` SQL-ágának pótlása**

A `filter` SQL-ága (`:1018-1023`) ma csak a `'country'` és a `> 0` esetet kezeli,
ezért a „megyei — összes" felirat olyan adat fölött állna, amiben az országos és
a szabadidős versenyek is benne vannak. Cseréld erre:

```php
    if ($filter === 'country') {
        $sql .= " AND vc.contest_type=1";
    } elseif (is_numeric($filter) && $filter > 0) {
        $sql .= " AND vc.contest_type=3 AND vc.state_id=%d";
        $params[] = (int) $filter;
    } elseif (is_numeric($filter) && intval($filter) === 0) {
        // "Összes megye": a felirat megyei versenyekről szól, tehát a
        // lekérdezésnek is arra kell szűrnie.
        $sql .= " AND vc.contest_type=3";
    }
```

Az `'all'` ág **szándékosan nem kap `contest_type` megkötést**: ennél a riportnál
az „Összes verseny" tényleg mindent jelent, a szabadidős versenyeket is. Ez
eltér a `szezon_riport`-tól, és így is marad — ne egységesítsd.

- [ ] **Step 3: A szűrőblokkok cseréje helperre**

Cseréld a `:1025-1038` három blokkját (a `$schoolDistrict` elgépelt változónevet
használó tankerület-blokkot, a fogyatékossági és a nem blokkot) erre:

```php
    // A tankerület-szűrés eddig halott volt: a blokk a $schoolDistrict
    // változóra hivatkozott, miközben a beolvasott érték a $schoolDistrictId-ben
    // van. Az intézmény-szűrés pedig teljesen hiányzott, pedig a lekérdezés
    // már ma is join-olja a vi táblát.
    $intezmeny = vespa_riport_intezmeny_szuro($schoolDistrictId, $institutionId);
    $sql      .= $intezmeny['sql'];
    $params    = array_merge($params, $intezmeny['params']);

    $sportolo = vespa_riport_sportolo_szuro($disabilityGroupId, $gender);
    $sql     .= $sportolo['sql'];
    $params   = array_merge($params, $sportolo['params']);
```

- [ ] **Step 4: Ellenőrzés**

```bash
php -l includes/Export/download_riports.php
grep -n '\$schoolDistrict\b' includes/Export/download_riports.php
grep -c 'else die;' includes/Export/download_riports.php
for f in tests/test-*.php; do php "$f" > /dev/null || echo "ELBUKOTT: $f"; done
```

Elvárt: `No syntax errors detected`; a `$schoolDistrict` (Id nélkül) csak azokban
a függvényekben fordul elő, ahol a beolvasott változó tényleg így hívják
(`versenyen_resztvevo`, `verseny_diak`) — a `legnepszerubb_sportagak`-ban
egyetlen előfordulása sem maradhat; az `else die;` darabszáma **4-ről 3-ra** csökkent.

- [ ] **Step 5: Kód-átolvasásos ellenőrzés**

Írd le a `%d`/`%s` helyőrzők sorrendjét és a `$params` elemeit ezekre a
bemenetekre: (a) `filter='all'`, minden szűrő 0; (b) `filter=0`, tankerület=7,
nem='nő'; (c) `filter=5`, intézmény=42, fogyatékosság=3.

- [ ] **Step 6: Commit**

```bash
git add includes/Export/download_riports.php
git commit -m "fix(riport): legnepszerubb sportagak - ures valasz, halott tankerulet-szures, hianyzo intezmeny"
```

---

### Task 6: A „Legnépszerűbb sportágak" URL-je a felületen

**Files:**
- Modify: `templates/riports_dashboard.php` — a `getSubmitUri` `legnepszerubb_sportag` ága (`:241-242` környéke)

**Interfaces:**
- Consumes: a Task 2 és Task 5 által beolvasott backend paraméterek
- Produces: semmit további task számára

A felület ehhez a riporthoz mutatja a Tankerület, Intézmény, Nem és Fogyatékossági
csoport mezőt (`showedInputs`), de az URL-be egyiket sem teszi bele.

- [ ] **Step 1: Az URL kiegészítése**

Cseréld ezt az ágat:

```javascript
                    case 'legnepszerubb_sportag':
                        return `${baseUrl}&dateFrom=${this.selectedRiportData.dateFrom}&dateTo=${this.selectedRiportData.dateTo}&sport=${this.selectedRiportData.sport}&sportEventId=${this.selectedRiportData.sportEvent}`
```

erre — a paraméternevek pontosan egyezzenek az `iskola_sportoltatott_diakok`
ágban használtakkal, mert a backend ezeket olvassa:

```javascript
                    case 'legnepszerubb_sportag':
                        return `${baseUrl}&dateFrom=${this.selectedRiportData.dateFrom}&dateTo=${this.selectedRiportData.dateTo}&schoolDistrict=${this.selectedRiportData.schoolDistrict}&institutionId=${this.selectedRiportData.institutionId}&disabilityGroupId=${this.selectedRiportData.disabilityGroupId}&gender=${this.selectedRiportData.gender}&sport=${this.selectedRiportData.sport}&sportEventId=${this.selectedRiportData.sportEvent}`
```

- [ ] **Step 2: Ellenőrzés — a felület és a backend paraméterei fedik egymást**

```bash
php -l templates/riports_dashboard.php
grep -n "case 'legnepszerubb_sportag'" -A 2 templates/riports_dashboard.php
```

Vesd össze a `legnepszerubb_sportag` ág paraméterneveit a
`vespa_download_riport_legnepszerubb_sportagak()` `$_GET` olvasásaival, és sorold
fel őket párba állítva. Mind a hét paraméternek (`filter`, `dateFrom`, `dateTo`,
`schoolDistrict`, `institutionId`, `disabilityGroupId`, `gender`, `sport`,
`sportEventId`) egyeznie kell.

- [ ] **Step 3: Commit**

```bash
git add templates/riports_dashboard.php
git commit -m "fix(riport): a legnepszerubb sportagak riport elkuldi a mutatott szuroket"
```

---

### Task 7: CHANGELOG bejegyzés

**Files:**
- Modify: `CHANGELOG.md`

A `vitarex-vespa-plugin.php:7` szerinti verzió `2.3.20`, az előző ág már felvett
egy `[2.3.21]` bejegyzést, tehát ez a `[2.3.22]`.

- [ ] **Step 1: Bejegyzés beszúrása**

A `# Changelog` sor UTÁN, a `## [2.3.21]` blokk ELÉ:

```markdown
## [2.3.22] - 2026-08-03

### Javítások
- **A riportokban mutatott szűrők végre tényleg szűrnek:** Több riportnál a felület felkínált egy szűrőt (Intézmény, Nem, Fogyatékossági csoport, Tankerület), a riport viszont némán figyelmen kívül hagyta, és szűretlen számokat adott — hibajelzés nélkül. Érintett volt az „Adott tanévben FODISZ diákolimpián részt vett fogyatékkal élő diákok száma", az „Adott időszakban versenyen indult diákok száma iskolánként" és az „Adott időszakra a sportágak népszerűsége" riport. Ezek a riportok mostantól más — helyes — számokat adnak, ha élsz ezekkel a szűrőkkel.
- **A sportág-népszerűségi riport üres választ adott:** Az „Adott időszakra a sportágak népszerűsége" riport „Összes megye" szűréssel nem generált fájlt, csak egy üres választ. Mostantól lefut, és a megyei versenyekre szűr, ahogy a felirata ígéri.
- **Halott tankerület-szűrés:** Ugyanennél a riportnál a tankerület szerinti szűrés egy elgépelt változónév miatt soha nem érvényesült.
- **Hiányzó GET paraméterek:** A riportok több paramétert is ellenőrzés nélkül olvastak ki, ami PHP-figyelmeztetést írhatott a válaszba, és a binárisan írt XLSX-fájlt megsérthette. Mostantól minden paraméter olvasása védett.
```

- [ ] **Step 2: Commit**

```bash
git add CHANGELOG.md
git commit -m "docs: CHANGELOG a riport-szurok osszehangolasarol"
```

---

## Kézi QA — élesben, telepítés után

Automatizálva nem ellenőrizhető (nincs helyi WordPress).

**A javított szűrők (a számoknak VÁLTOZNIUK kell, ha a szűrő fog):**

- [ ] Tanévi diákolimpia diákok — Nem: „nő" → csak lányok; „férfi" → csak fiúk; a kettő összege ≈ az „összes"
- [ ] Tanévi diákolimpia diákok — Fogyatékossági csoport kiválasztva → csak az adott csoport
- [ ] Tanévi diákolimpia diákok — Intézmény kiválasztva → **egyetlen sor**, nem több száz nullás
- [ ] Versenyen indult diákok iskolánként — ugyanez a három szűrő
- [ ] Legnépszerűbb sportágak — Tankerület, Intézmény, Nem, Fogyatékossági csoport mind hat
- [ ] Legnépszerűbb sportágak — „Összes megye" szűréssel **letöltődik a fájl** (eddig üres válasz jött), és a fejléce „Legnépszerűbb megyei sportágak - összes"

**Változatlanság (ezeknek ugyanazt kell adniuk, mint a javítás előtt):**

- [ ] Szezon riport — mind a négy időszak-kombinációval
- [ ] Verseny és diákok statisztika időszakra
- [ ] Verseny és versenyszámok statisztika időszakra
- [ ] Tanévi versenyszámok / sportágankénti versenyek
- [ ] Tanévben versenyen indult iskolák
