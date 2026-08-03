# Riport: szezon és naptári év külön kikapcsolható — implementációs terv

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A tanév-alapú riportoknál a versenysorozat és a naptári év egymástól függetlenül kikapcsolható legyen, és a legenerált XLSX fejlécéből egyértelműen kiderüljön, melyik időszak-szűrés volt érvényben.

**Architecture:** Egy új, tiszta (adatbázistól független, ezért WordPress nélkül tesztelhető) helper-fájl állítja elő az időszak-szűrés SQL-töredékét, paramétereit és a fejléc-feliratot. A `download_riports.php` négy riportfüggvénye a saját, kézzel írt szezon-feltétele helyett ezt hívja. A felületen a versenysorozat legördülő kap egy „Összes szezon" elemet, és a naptári év mező mind az öt tanév-riportnál megjelenik.

**Tech Stack:** PHP (WordPress plugin, `$wpdb`), PhpSpreadsheet (XLSX írás), Vue 3 (CDN, Options API) a riport-felületen, sima PHP szkript-tesztek (`php tests/test-*.php`).

**Spec:** [docs/superpowers/specs/2026-08-03-riport-szezon-naptari-ev-design.md](../specs/2026-08-03-riport-szezon-naptari-ev-design.md)

## Global Constraints

- **A projektben nincs helyi WordPress fejlesztői környezet.** Csak a tiszta függvények tesztelhetők automatikusan (`php tests/test-*.php`). A riportfüggvények módosításának ellenőrzése `php -l` szintaxis-ellenőrzés + kód-átolvasás; a tényleges XLSX-kimenet kézi QA-t igényel élesben.
- **Minden felhasználónak látszó szöveg magyar.** A kódkommentek is magyarul íródnak, a meglévő fájlok stílusában (miért, nem mit).
- **A `vespa_contests` tábla aliasa mindenhol `vc`.** A helper által adott SQL-töredék erre az aliasra épül.
- **A helper paraméter-sorrendje kötött: előbb a szezon, utána a naptári év.** A hívó ebben a sorrendben fűzi a saját `$params` tömbjéhez, és a töredéket az SQL-be is ezen a helyen szúrja be — a `%d` helyőrzők sorrendjének egyeznie kell a `$params` sorrendjével, különben a `$wpdb->prepare()` rossz értékeket helyettesít be.
- **A négy intervallumos riport (`verseny_versenyszam`, `verseny_diak`, `legnepszerubb_sportag`, `iskola_sportoltatott_diakok`) kimenete nem változhat.** Két backend függvény *közösen* szolgálja ki az intervallumos és a tanév-alapú riportokat, ezért minden módosításnál a típus szerinti ágat kell eltalálni.
- **Nem része a hatókörnek** a `$_GET` paraméterek hiányzó `isset`-védelme a `szezon_riport`-on kívüli függvényekben (pl. `download_riports.php:352` `$seriesId = $_GET['series'];`, ami az intervallumos `verseny_versenyszam` hívásnál ma is definiálatlan indexre fut). Ezek meglévő, ettől a változtatástól független problémák. Az **újonnan bevezetett** `$year` olvasás viszont mindenhol `isset`-védett legyen.
- **Gyakori commit:** minden task saját committal zárul.

---

## File Structure

| Fájl | Felelősség | Művelet |
|---|---|---|
| `includes/Core/vespa.riport.periodus.php` | Az időszak-szűrés tiszta logikája (SQL-töredék, paraméterek, fejléc-felirat) + a `prepare()` burkoló | **Létrehozás** |
| `tests/test-riport-periodus.php` | A tiszta függvények unit tesztjei | **Létrehozás** |
| `includes/Export/download_riports.php` | Négy riportfüggvény átállítása a helperre | **Módosítás** |
| `templates/riports_dashboard.php` | „Összes szezon" opció, naptári év mező mind az öt riportnál, `&year=` a letöltési URL-ben | **Módosítás** |
| `CHANGELOG.md` | Felhasználónak szóló változásleírás | **Módosítás** |

A `includes/Core/` alatti fájlokat a plugin automatikusan betölti (`vitarex-vespa-plugin.php:51-60` könyvtár-bejárás), és a `Core` a `$include_dirs` első eleme, tehát az `Export` előtt töltődik be — külön `require` nem kell sehova.

---

### Task 1: Az időszak-szűrő helper és unit tesztjei

**Files:**
- Create: `includes/Core/vespa.riport.periodus.php`
- Test: `tests/test-riport-periodus.php`

**Interfaces:**
- Consumes: semmit (ez az első task)
- Produces:
  - `vespa_riport_pozitiv_egesz($ertek): bool`
  - `vespa_riport_periodus_szuro($seriesId, $year, $szabadidosKivetel = false): array` — visszatérés: `array('sql' => string, 'params' => array<int>)`
  - `vespa_riport_periodus_felirat($seriesName, $year): string`
  - `vespa_riport_get_results($sql, $params): array|null` — a `$wpdb->get_results()` burkolója

- [ ] **Step 1: Write the failing test**

Hozd létre a `tests/test-riport-periodus.php` fájlt. A `allit()` segédfüggvény és a záró összegzés a meglévő tesztek (`tests/test-docs-access.php`, `tests/test-filename.php`) bevált mintája — szó szerint ez a szerkezet:

```php
<?php
/**
 * A riportok időszak-szűrőjének (szezon + naptári év) unit tesztjei.
 * Futtatás: php tests/test-riport-periodus.php
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

// ---- Csak szezon -------------------------------------------------------

$r = vespa_riport_periodus_szuro('3', '0');
allit($r['sql'] === ' AND vc.contest_series=%d', 'csak szezon: sql toredek');
allit($r['params'] === array(3), 'csak szezon: params egesz szamma alakitva');

// ---- Csak naptári év ---------------------------------------------------

$r = vespa_riport_periodus_szuro('0', '2025');
allit($r['sql'] === ' AND YEAR(vc.start_at)=%d', 'csak ev: sql toredek');
allit($r['params'] === array(2025), 'csak ev: params');

// ---- Mindkettő ---------------------------------------------------------
// A sorrend kötött: előbb a szezon helyőrzője, utána az évé. Ha ez elcsúszik,
// a prepare() a szezon-azonosítót helyettesíti be évszámként.

$r = vespa_riport_periodus_szuro('3', '2025');
allit(
    $r['sql'] === ' AND vc.contest_series=%d AND YEAR(vc.start_at)=%d',
    'szezon+ev: mindket toredek, szezon eloszor'
);
allit($r['params'] === array(3, 2025), 'szezon+ev: params sorrendje szezon, majd ev');

// ---- Egyik sem ---------------------------------------------------------

$r = vespa_riport_periodus_szuro('0', '0');
allit($r['sql'] === '', 'egyik sem: ures sql');
allit($r['params'] === array(), 'egyik sem: ures params');

// ---- Szabadidős kivétel (tanev_diakolimpia_versenyszam_sportag) ---------
// Ez a riport a szabadidős versenyeket szezontól függetlenül beszámolja.

$r = vespa_riport_periodus_szuro('3', '0', true);
allit(
    $r['sql'] === ' AND (vc.contest_series=%d OR vc.contest_type=4)',
    'szabadidos kivetel + szezon: vagyos szezon-feltetel'
);
allit($r['params'] === array(3), 'szabadidos kivetel + szezon: params');

$r = vespa_riport_periodus_szuro('0', '2025', true);
allit(
    $r['sql'] === ' AND YEAR(vc.start_at)=%d',
    'szabadidos kivetel szezon nelkul: nincs szezon-toredek'
);
allit($r['params'] === array(2025), 'szabadidos kivetel szezon nelkul: params');

$r = vespa_riport_periodus_szuro('3', '2025', true);
allit(
    $r['sql'] === ' AND (vc.contest_series=%d OR vc.contest_type=4) AND YEAR(vc.start_at)=%d',
    'szabadidos kivetel + szezon + ev: az ev a teljes kifejezesre AND-elodik'
);
allit($r['params'] === array(3, 2025), 'szabadidos kivetel + szezon + ev: params');

// ---- Érvénytelen bemenetek ---------------------------------------------
// A GET-ből bármi jöhet; ezek mind "nincs szűrés"-ként viselkedjenek.

$rosszErtekek = array('', 'abc', '-1', '0', 0, null, false);
foreach ($rosszErtekek as $rossz) {
    $r = vespa_riport_periodus_szuro($rossz, $rossz);
    allit(
        $r['sql'] === '' && $r['params'] === array(),
        'ervenytelen bemenet nem szur: ' . var_export($rossz, true)
    );
}

// Vegyes eset: érvénytelen szezon mellett érvényes év.
$r = vespa_riport_periodus_szuro('abc', '2025');
allit($r['sql'] === ' AND YEAR(vc.start_at)=%d', 'ervenytelen szezon + ervenyes ev: csak ev');
allit($r['params'] === array(2025), 'ervenytelen szezon + ervenyes ev: params');

// ---- Fejléc-felirat ----------------------------------------------------

allit(
    vespa_riport_periodus_felirat('2025/2026', 0) === '2025/2026',
    'felirat: csak szezon'
);
allit(
    vespa_riport_periodus_felirat('2025/2026', '2025') === '2025/2026 — naptári év: 2025',
    'felirat: szezon + ev'
);
allit(
    vespa_riport_periodus_felirat('', '2025') === 'Naptári év: 2025',
    'felirat: csak ev'
);
allit(
    vespa_riport_periodus_felirat(null, 0) === 'Nincs időszakszűrés (összes verseny)',
    'felirat: egyik sem'
);
allit(
    vespa_riport_periodus_felirat(null, 'abc') === 'Nincs időszakszűrés (összes verseny)',
    'felirat: ervenytelen ev ugy szamit, mintha nem lenne'
);

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
php tests/test-riport-periodus.php
```

Elvárt: fatális hiba — `Failed opening required '.../includes/Core/vespa.riport.periodus.php'`, mert a tesztelt fájl még nem létezik.

- [ ] **Step 3: Write minimal implementation**

Hozd létre a `includes/Core/vespa.riport.periodus.php` fájlt:

```php
<?php
/**
 * A riportok időszak-szűrője: versenysorozat (tanév/szezon) és naptári év.
 *
 * A két szűrő független egymástól: bármelyik elhagyható, és mind a négy
 * kombináció érvényes (csak szezon / csak év / mindkettő / egyik sem).
 * A tiszta függvények WordPress nélkül tesztelhetők — lásd
 * tests/test-riport-periodus.php.
 */

/**
 * Igaz, ha a GET-ből érkező nyers érték pozitív azonosítóként használható.
 * A riport-felület a "nincs szűrés" állapotot 0-val jelzi, de a GET-ből
 * elvileg bármilyen szöveg érkezhet.
 */
function vespa_riport_pozitiv_egesz($ertek)
{
    return is_numeric($ertek) && (int) $ertek > 0;
}

/**
 * Az időszak-szűrés SQL-töredéke és prepared paraméterei.
 *
 * A visszaadott 'sql' a hívó lekérdezésének WHERE ágához fűzhető, a 'params'
 * pedig a hívó paramétertömbjéhez. A SORREND KÖTÖTT: előbb a szezon
 * helyőrzője, utána az évé — a hívónak ugyanebben a sorrendben kell fűznie.
 *
 * @param mixed $seriesId  versenysorozat azonosítója; csak a pozitív egész szűr
 * @param mixed $year      naptári év; csak a pozitív egész szűr
 * @param bool  $szabadidosKivetel  ha true, a szezon-feltétel a szabadidős
 *        (contest_type=4) versenyeket szezontól függetlenül beengedi. Ezt
 *        egyedül a tanev_diakolimpia_versenyszam_sportag riport használja.
 * @return array {sql: string, params: array}
 */
function vespa_riport_periodus_szuro($seriesId, $year, $szabadidosKivetel = false)
{
    $sql    = '';
    $params = array();

    if (vespa_riport_pozitiv_egesz($seriesId)) {
        $sql .= $szabadidosKivetel
            ? ' AND (vc.contest_series=%d OR vc.contest_type=4)'
            : ' AND vc.contest_series=%d';
        $params[] = (int) $seriesId;
    }

    // A naptári év a verseny KEZDŐNAPJÁRA vonatkozik. Szándékosan a teljes
    // szezon-kifejezésre AND-elődik: a szabadidős kivétellel együtt is
    // érvényes marad az évszűrés.
    if (vespa_riport_pozitiv_egesz($year)) {
        $sql .= ' AND YEAR(vc.start_at)=%d';
        $params[] = (int) $year;
    }

    return array('sql' => $sql, 'params' => $params);
}

/**
 * A riport XLSX fejlécébe írandó időszak-felirat.
 *
 * A szezon nevét a hívó nézi ki az adatbázisból, hogy ez a függvény tiszta
 * maradhasson. Ez a felirat teszi egyértelművé a letöltött fájlból, hogy
 * melyik időszak-értelmezés volt érvényben.
 *
 * @param string|null $seriesName a szezon neve, vagy üres/null ha nincs szezonszűrés
 * @param mixed       $year
 * @return string
 */
function vespa_riport_periodus_felirat($seriesName, $year)
{
    $vanSzezon = is_string($seriesName) && $seriesName !== '';
    $vanEv     = vespa_riport_pozitiv_egesz($year);

    if ($vanSzezon && $vanEv) {
        return $seriesName . ' — naptári év: ' . (int) $year;
    }
    if ($vanSzezon) {
        return $seriesName;
    }
    if ($vanEv) {
        return 'Naptári év: ' . (int) $year;
    }

    return 'Nincs időszakszűrés (összes verseny)';
}

/**
 * A riport-lekérdezések futtatása üres paramétertömb esetén is biztonságosan.
 *
 * A $wpdb->prepare() helyőrző nélküli lekérdezésre _doing_it_wrong()
 * figyelmeztetést vált ki, ami WP_DEBUG_DISPLAY mellett beleíródik a válasz
 * törzsébe — a riport viszont bináris XLSX-et ír a php://output-ra, így a
 * letöltött fájl megsérülne. Szűrő nélküli riport most már előfordulhat,
 * ezért a prepare() csak akkor fut, ha ténylegesen van behelyettesítendő érték.
 *
 * Ez a függvény a $wpdb-t használja, ezért nem tiszta és nincs unit tesztje.
 */
function vespa_riport_get_results($sql, $params)
{
    global $wpdb;

    return $wpdb->get_results($params ? $wpdb->prepare($sql, ...$params) : $sql);
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php tests/test-riport-periodus.php
```

Elvárt: minden sor `OK`-kal kezdődik, a végén `Minden teszt sikeres.`, kilépési kód 0.

Futtasd a szintaxis-ellenőrzést is:

```bash
php -l includes/Core/vespa.riport.periodus.php
```

Elvárt: `No syntax errors detected`.

- [ ] **Step 5: Ellenőrizd, hogy a többi teszt is fut még**

```bash
for f in tests/test-*.php; do echo "== $f"; php "$f" > /dev/null || echo "ELBUKOTT: $f"; done
```

Elvárt: egyetlen `ELBUKOTT:` sor sem jelenik meg. (Az új fájl globális névtérbe tesz függvényeket; ez a lépés azt igazolja, hogy nincs névütközés a meglévő helperekkel.)

- [ ] **Step 6: Commit**

```bash
git add includes/Core/vespa.riport.periodus.php tests/test-riport-periodus.php
git commit -m "feat(riport): idoszak-szuro tiszta logikaja es unit tesztjei"
```

---

### Task 2: Szezon riport átállítása a helperre

**Files:**
- Modify: `includes/Export/download_riports.php:76-90` (fejléc-blokk), `:100` (WHERE ág), `:102` (`$params` inicializálás), `:152-155` (külön év-feltétel), `:163` (lekérdezés futtatása)

**Interfaces:**
- Consumes: `vespa_riport_periodus_szuro()`, `vespa_riport_periodus_felirat()`, `vespa_riport_get_results()` (Task 1)
- Produces: semmit további task számára

Ez a task szünteti meg a `szezon_riport` `die`-ját: eddig szezon nélkül egyáltalán nem generált riportot.

- [ ] **Step 1: A fejléc-blokk átírása**

A `vespa_download_riport_szezon_riport()` függvényben cseréld le ezt a blokkot (`:76-90`):

```php
    if(is_numeric($seriesId) && $seriesId > 0){
        $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_series WHERE series_id=%d",$seriesId));
        $sheet
            ->setCellValue('A' . $ind, 'Tanév/Diákolimpia szezon')
            ->setCellValue('B' . $ind, $st->series_name);
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

erre:

```php
    // A szezon és a naptári év külön-külön elhagyható, ezért a fejléc-írás
    // nem függhet attól, hogy van-e szezon. Korábban itt egy die() állt: szezon
    // nélkül a riport némán megszakadt, üres választ adva.
    $seriesName = '';
    if (vespa_riport_pozitiv_egesz($seriesId)) {
        $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_series WHERE series_id=%d", (int) $seriesId));
        $seriesName = $st ? $st->series_name : '';
    }

    $sheet
        ->setCellValue('A' . $ind, 'Időszak')
        ->setCellValue('B' . $ind, vespa_riport_periodus_felirat($seriesName, $year));
    $ind++;
    $sheet
        ->setCellValue('A' . $ind, 'Szűrés')
        ->setCellValue('B' . $ind, $filterType);
    $ind += 2;
```

- [ ] **Step 2: A WHERE ág és a paraméterek átállítása**

Cseréld a `$sql` lezáró sorát (`:100`):

```php
            WHERE vc.contest_series=%d";

    $params = [$seriesId];
```

erre:

```php
            WHERE 1";

    // Az időszak-feltétel MINDEN további feltétel ELŐTT kerül be, hogy a %d
    // helyőrzők sorrendje egyezzen a $params sorrendjével.
    $periodus = vespa_riport_periodus_szuro($seriesId, $year);
    $sql   .= $periodus['sql'];
    $params = $periodus['params'];
```

- [ ] **Step 3: A külön év-feltétel törlése**

Töröld ezt a blokkot (`:152-155` a módosítás előtti számozás szerint) — az évszűrést innentől a helper adja:

```php
    if (is_numeric($year) && $year > 0) {
        $sql .= " AND YEAR(vc.start_at)=%d";
        $params[] = $year;
    }
```

- [ ] **Step 4: A lekérdezés futtatása a burkolón át**

Cseréld ezt a sort (`:163`):

```php
$data = $wpdb->get_results($wpdb->prepare($sql, ...$params));
```

erre:

```php
$data = vespa_riport_get_results($sql, $params);
```

- [ ] **Step 5: Szintaxis-ellenőrzés**

```bash
php -l includes/Export/download_riports.php
```

Elvárt: `No syntax errors detected`.

- [ ] **Step 6: A négy kombináció ellenőrzése kód-átolvasással**

Nincs helyi WordPress, ezért ez a lépés kézi átolvasás. Olvasd végig a módosított függvényt és győződj meg róla, hogy:

- `$seriesId = 0, $year = 0` esetén a `$sql` `WHERE 1`-gyel indul, és az első `%d` a `$filter` ágból származik;
- a `$params` elemeinek sorrendje pontosan követi a `%d`/`%s` helyőrzők sorrendjét a `$sql`-ben;
- nincs több `die` a függvényben;
- a `$year` változó a `:59` sorban továbbra is `isset`-védetten olvasódik.

- [ ] **Step 7: Commit**

```bash
git add includes/Export/download_riports.php
git commit -m "feat(riport): a szezon riport szezon nelkul es tiszta naptari evre is fut"
```

---

### Task 3: Tanévi versenyszám- és sportág-riport átállítása

**Files:**
- Modify: `includes/Export/download_riports.php:341-356` (`$year` beolvasása), `:381-392` (fejléc), `:404-415` (szezon-feltételek), `:445` (lekérdezés futtatása)

**Interfaces:**
- Consumes: `vespa_riport_periodus_szuro()`, `vespa_riport_periodus_felirat()`, `vespa_riport_get_results()` (Task 1)
- Produces: semmit további task számára

A `vespa_download_riport_verseny_versenyszam()` **három** riporttípust szolgál ki: `tanev_diakolimpia_versenyszam`, `tanev_diakolimpia_versenyszam_sportag` (tanév-alapúak, ezek változnak) és `verseny_versenyszam` (intervallumos, **érintetlen**).

- [ ] **Step 1: A `$year` beolvasása**

A függvény elején, a `$seriesId = $_GET['series'];` sor (`:352`) UTÁN szúrd be:

```php
    $year = isset($_GET['year']) ? $_GET['year'] : 0;
```

- [ ] **Step 2: A fejléc időszak-cellájának átírása**

Cseréld ezt a blokkot (`:381-392`):

```php
    $sheet
        ->setCellValue('A' . $ind, 'Szűrés:')
        ->setCellValue('B' . $ind, $filterType)
        ->setCellValue('C' . $ind, 'Időszak:');
    if($seriesId > 0){
        $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_series WHERE series_id=%d", $seriesId));
        $sheet->setCellValue('D' . $ind, $st->series_name);
    }
    else {
        $sheet->setCellValue('D' . $ind, $dateFrom)->setCellValue('E' . $ind, $dateTo);
    }
```

erre:

```php
    // A tanév- és az intervallumos ág megkülönböztetése a RIPORTTÍPUS alapján
    // történik, nem a $seriesId értéke alapján: a szezon már elhagyható, ezért
    // a $seriesId === 0 tanév-riportnál is érvényes állapot, és ilyenkor
    // nem a dateFrom/dateTo tartományt kell kiírni.
    $tanevRiport = ('tanev_diakolimpia_versenyszam' == $type
        || 'tanev_diakolimpia_versenyszam_sportag' == $type);

    $sheet
        ->setCellValue('A' . $ind, 'Szűrés:')
        ->setCellValue('B' . $ind, $filterType)
        ->setCellValue('C' . $ind, 'Időszak:');
    if ($tanevRiport) {
        $seriesName = '';
        if (vespa_riport_pozitiv_egesz($seriesId)) {
            $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_series WHERE series_id=%d", (int) $seriesId));
            $seriesName = $st ? $st->series_name : '';
        }
        $sheet->setCellValue('D' . $ind, vespa_riport_periodus_felirat($seriesName, $year));
    }
    else {
        $sheet->setCellValue('D' . $ind, $dateFrom)->setCellValue('E' . $ind, $dateTo);
    }
```

- [ ] **Step 3: A szezon-feltételek átállítása**

Cseréld ezt a blokkot (`:404-415`):

```php
    if('tanev_diakolimpia_versenyszam' == $type){
        if (is_numeric($seriesId) && $seriesId > 0) {
            $sql .= " AND vc.contest_series=%d AND vc.contest_type IN (1,2,3)";
            $params[] = $seriesId;
        }
    }
    else if('tanev_diakolimpia_versenyszam_sportag' == $type){
        if (is_numeric($seriesId) && $seriesId > 0) {
            $sql .= " AND (vc.contest_series=%d OR vc.contest_type=4)";
            $params[] = $seriesId;
        }
    }
```

erre:

```php
    if('tanev_diakolimpia_versenyszam' == $type){
        // A contest_type megkötés nem időszak-, hanem versenytípus-szűrés,
        // ezért szezon nélkül is érvényben marad: ez a riport a diákolimpiai
        // versenyekről szól, a szabadidős (4) szándékosan kimarad.
        $periodus = vespa_riport_periodus_szuro($seriesId, $year);
        $sql     .= $periodus['sql'] . " AND vc.contest_type IN (1,2,3)";
        $params   = array_merge($params, $periodus['params']);
    }
    else if('tanev_diakolimpia_versenyszam_sportag' == $type){
        // Ez a riport a szabadidős versenyeket szezontól függetlenül
        // beszámolja — ezt a helper $szabadidosKivetel ága adja.
        $periodus = vespa_riport_periodus_szuro($seriesId, $year, true);
        $sql     .= $periodus['sql'];
        $params   = array_merge($params, $periodus['params']);
    }
```

Az `else` ág (az intervallumos `verseny_versenyszam` `start_at`/`end_at` feltétele) **változatlan marad**.

- [ ] **Step 4: A lekérdezés futtatása a burkolón át**

Cseréld ezt a sort (`:445`):

```php
    $data = $wpdb->get_results($wpdb->prepare($sql, ...$params));
```

erre:

```php
    $data = vespa_riport_get_results($sql, $params);
```

- [ ] **Step 5: Szintaxis-ellenőrzés**

```bash
php -l includes/Export/download_riports.php
```

Elvárt: `No syntax errors detected`.

- [ ] **Step 6: Az intervallumos ág érintetlenségének ellenőrzése**

Olvasd át a függvényt és győződj meg róla, hogy `verseny_versenyszam` típus esetén:

- a `$tanevRiport` hamis, tehát a fejléc `D`/`E` cellájába továbbra is a `$dateFrom`/`$dateTo` kerül;
- az SQL `else` ága fut, a `$params` a `$filterFrom`/`$filterTo` értékekkel indul;
- a `$year` beolvasása nem okoz warningot (az `isset` miatt), és sehol nem kerül be a lekérdezésbe.

- [ ] **Step 7: Commit**

```bash
git add includes/Export/download_riports.php
git commit -m "feat(riport): tanevi versenyszam- es sportag-riport idoszak-szuroje"
```

---

### Task 4: „Tanévben versenyen indult iskolák" riport átállítása

**Files:**
- Modify: `includes/Export/download_riports.php:497-510` (`$year` beolvasása), `:536-539` (fejléc), `:547-553` (WHERE ág), `:572` (lekérdezés futtatása)

**Interfaces:**
- Consumes: `vespa_riport_periodus_szuro()`, `vespa_riport_periodus_felirat()`, `vespa_riport_get_results()` (Task 1)
- Produces: semmit további task számára

Ez a task szünteti meg a nyers SQL-interpolációt is: a `$seriesId` ma közvetlenül, prepared paraméter nélkül kerül a lekérdezésbe.

- [ ] **Step 1: A `$year` beolvasása**

A `vespa_download_riport_versenyen_resztvevo_iskolak_szama()` függvényben a `$seriesId = $_GET["series"];` sor (`:506`) UTÁN szúrd be:

```php
    $year = isset($_GET['year']) ? $_GET['year'] : 0;
```

- [ ] **Step 2: Időszak-cella a fejlécbe**

Ennek a riportnak ma **nincs** időszak-felirata a fejlécben. Cseréld ezt a blokkot (`:536-539`):

```php
    $sheet
        ->setCellValue('A' . $ind, 'Szűrés:')
        ->setCellValue('B' . $ind, $filterType);
    $ind+=2;
```

erre:

```php
    $seriesName = '';
    if (vespa_riport_pozitiv_egesz($seriesId)) {
        $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_series WHERE series_id=%d", (int) $seriesId));
        $seriesName = $st ? $st->series_name : '';
    }

    $sheet
        ->setCellValue('A' . $ind, 'Szűrés:')
        ->setCellValue('B' . $ind, $filterType)
        ->setCellValue('C' . $ind, 'Időszak:')
        ->setCellValue('D' . $ind, vespa_riport_periodus_felirat($seriesName, $year));
    $ind+=2;
```

- [ ] **Step 3: A nyers SQL-interpoláció lecserélése**

Cseréld a `$sql` lezáró sorát (`:553`):

```php
            WHERE vc.contest_series = $seriesId";
```

erre:

```php
            WHERE 1";

    // A $seriesId eddig nyersen, prepared paraméter nélkül került az SQL-be.
    // Az időszak-feltétel MINDEN további feltétel ELŐTT kerül be, hogy a
    // helyőrzők sorrendje egyezzen a $params sorrendjével.
    $periodus = vespa_riport_periodus_szuro($seriesId, $year);
    $sql     .= $periodus['sql'];
    $params   = array_merge($params, $periodus['params']);
```

- [ ] **Step 4: A lekérdezés futtatása a burkolón át**

Cseréld ezt a sort (`:572`):

```php
    $data = $wpdb->get_results($wpdb->prepare($sql, ...$params));
```

erre:

```php
    $data = vespa_riport_get_results($sql, $params);
```

- [ ] **Step 5: Szintaxis-ellenőrzés és az interpoláció eltűnésének igazolása**

```bash
php -l includes/Export/download_riports.php
grep -n 'contest_series = \$seriesId' includes/Export/download_riports.php
```

Elvárt: `No syntax errors detected`, és a `grep` **nem ad találatot** (a nyers interpoláció megszűnt).

- [ ] **Step 6: Commit**

```bash
git add includes/Export/download_riports.php
git commit -m "feat(riport): tanevben versenyen indult iskolak idoszak-szuroje, prepared szezon"
```

---

### Task 5: „Tanévi diákolimpia diákok" riport átállítása

**Files:**
- Modify: `includes/Export/download_riports.php:771-790` (`$year` beolvasása), `:806-818` (fejléc), `:833-839` (szezon-feltétel), `:866` és `:879` (lekérdezések futtatása)

**Interfaces:**
- Consumes: `vespa_riport_periodus_szuro()`, `vespa_riport_periodus_felirat()`, `vespa_riport_get_results()` (Task 1)
- Produces: semmit további task számára

A `vespa_download_riport_tanev($type)` **két** riporttípust szolgál ki: `tanev_diakolimpia_diakok` (tanév-alapú, ez változik) és `iskola_sportoltatott_diakok` (intervallumos, **érintetlen**).

- [ ] **Step 1: A `$year` beolvasása**

A `$seriesId = $_GET['series'];` sor (`:781`) UTÁN szúrd be:

```php
    $year = isset($_GET['year']) ? $_GET['year'] : 0;
```

- [ ] **Step 2: A fejléc időszak-cellájának átírása**

Cseréld ezt a blokkot (`:812-818`):

```php
    if(is_numeric($seriesId) && $seriesId > 0){
        $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_series WHERE series_id=%d",$seriesId));
        $sheet->setCellValue('D' . $ind, $st->series_name);
    }
    else {
        $sheet->setCellValue('D' . $ind, $dateFrom)->setCellValue('E' . $ind, $dateTo);
    }
```

erre:

```php
    // Az ágválasztás a RIPORTTÍPUSRA épül, nem a $seriesId értékére: a szezon
    // már elhagyható, és tanév-riportnál akkor sem a dateFrom/dateTo
    // tartományt kell kiírni, ha nincs kiválasztva szezon.
    if('tanev_diakolimpia_diakok' == $type){
        $seriesName = '';
        if (vespa_riport_pozitiv_egesz($seriesId)) {
            $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_series WHERE series_id=%d", (int) $seriesId));
            $seriesName = $st ? $st->series_name : '';
        }
        $sheet->setCellValue('D' . $ind, vespa_riport_periodus_felirat($seriesName, $year));
    }
    else {
        $sheet->setCellValue('D' . $ind, $dateFrom)->setCellValue('E' . $ind, $dateTo);
    }
```

- [ ] **Step 3: A szezon-feltétel átállítása**

Cseréld ezt a blokkot (`:833-839`):

```php
    if('tanev_diakolimpia_diakok' == $type){
        if ($seriesId > 0) {
            $sql .= " AND vc.contest_series=%d";
            $params[] = $seriesId;
        }
    }
```

erre:

```php
    if('tanev_diakolimpia_diakok' == $type){
        $periodus = vespa_riport_periodus_szuro($seriesId, $year);
        $sql     .= $periodus['sql'];
        $params   = array_merge($params, $periodus['params']);
    }
```

Az `else` ág (az intervallumos `iskola_sportoltatott_diakok` `start_at`/`end_at` feltétele) **változatlan marad**.

- [ ] **Step 4: Mindkét lekérdezés futtatása a burkolón át**

Cseréld ezt a sort (`:866`):

```php
    $data = $wpdb->get_results($wpdb->prepare($sql, ...$params));
```

erre:

```php
    $data = vespa_riport_get_results($sql, $params);
```

Majd ezt a sort (`:879`):

```php
    $allSchool = $wpdb->get_results($wpdb->prepare($sql, ...$params));
```

erre:

```php
    // Ennek a lekérdezésnek a $params tömbje már ma is üresen maradhat, ha se
    // tankerület, se megye nincs szűrve — a burkoló ilyenkor prepare() nélkül fut.
    $allSchool = vespa_riport_get_results($sql, $params);
```

- [ ] **Step 5: Szintaxis-ellenőrzés**

```bash
php -l includes/Export/download_riports.php
```

Elvárt: `No syntax errors detected`.

- [ ] **Step 6: Az intervallumos ág érintetlenségének ellenőrzése**

Olvasd át a függvényt és győződj meg róla, hogy `iskola_sportoltatott_diakok` típus esetén a fejléc `D`/`E` cellájába továbbra is a `$dateFrom`/`$dateTo` kerül, és az SQL `else` ága fut.

- [ ] **Step 7: Commit**

```bash
git add includes/Export/download_riports.php
git commit -m "feat(riport): tanevi diakolimpia diakok idoszak-szuroje"
```

---

### Task 6: Riport-felület — „Összes szezon" opció és naptári év mező

**Files:**
- Modify: `templates/riports_dashboard.php:236-260` (`getSubmitUri`), `:273-275` (`getSeriesList`), `:312-326` (`showedInputs` ágak)

**Interfaces:**
- Consumes: a backend `year` GET paramétere (Task 2–5)
- Produces: semmit további task számára

A `defaultRiportState.series` a `mounted()`-ben (`:204`) a `this.series` **nyers** tömbből veszi az utolsó szezont — ez a computed `getSeriesList()`-től független, ezért az alapértelmezés változatlanul a legutóbbi szezon marad.

- [ ] **Step 1: „Összes szezon" elem a versenysorozat legördülőbe**

Cseréld ezt a computed-et (`:273-275`):

```javascript
            getSeriesList() {
                return [...this.series]
            },
```

erre — a többi legördülő („Összes megye", „Összes tankerület", „Összes intézmény") mintájára:

```javascript
            getSeriesList() {
                return [{series_id: 0, series_name: 'Összes szezon (nincs szűrés)'}, ...this.series]
            },
```

- [ ] **Step 2: Naptári év mező a négy tanév-riportnál**

A `watch.selectedRiportType` switch-ében egészítsd ki `year: 1`-gyel a négy tanév-riport ágát (`:312-326`). A `szezon_riport` ága már tartalmazza. A módosított négy sor:

```javascript
                    case 'tanev_diakolimpia_diakok':
                        this.showedInputs = {...this.defaultShowState, filter: 1, series: 1, year: 1, gender: 1, disabilityGroup: 1, schoolDistrict: 1, institution: 1, sport: 1, sportEvent: 1}
                        break;
```

```javascript
                    case 'tanev_versenyen_indult_iskolak':
                        this.showedInputs = {...this.defaultShowState, filter: 1, series: 1, year: 1, schoolDistrict: 1}
                        break;
```

```javascript
                    case 'tanev_diakolimpia_versenyszam':
                        this.showedInputs = {...this.defaultShowState, filter: 1, series: 1, year: 1}
                        break;
```

```javascript
                    case 'tanev_diakolimpia_versenyszam_sportag':
                        this.showedInputs = {...this.defaultShowState, filter: 1, series: 1, year: 1}
                        break;
```

- [ ] **Step 3: A `year` paraméter a letöltési URL-be**

A `getSubmitUri` computed-ben (`:236-260`) egészítsd ki a négy tanév-riport ágát `&year=`-tal. A módosított ágak:

```javascript
                    case 'tanev_diakolimpia_diakok':
                        return `${baseUrl}&series=${this.selectedRiportData.series}&year=${this.selectedRiportData.year}&schoolDistrict=${this.selectedRiportData.schoolDistrict}&institutionId=${this.selectedRiportData.institutionId}&disabilityGroupId=${this.selectedRiportData.disabilityGroupId}&gender=${this.selectedRiportData.gender}&sport=${this.selectedRiportData.sport}&sportEventId=${this.selectedRiportData.sportEvent}`
```

```javascript
                    case 'tanev_diakolimpia_versenyszam':
                        return `${baseUrl}&series=${this.selectedRiportData.series}&year=${this.selectedRiportData.year}`;
```

```javascript
                    case 'tanev_diakolimpia_versenyszam_sportag':
                        return `${baseUrl}&series=${this.selectedRiportData.series}&year=${this.selectedRiportData.year}`;
```

```javascript
                    case 'tanev_versenyen_indult_iskolak':
                        return `${baseUrl}&series=${this.selectedRiportData.series}&year=${this.selectedRiportData.year}&schoolDistrict=${this.selectedRiportData.schoolDistrict}`
```

A `szezon_riport` ága már küld `&year=`-t — az változatlan marad.

- [ ] **Step 4: Szintaxis-ellenőrzés**

```bash
php -l templates/riports_dashboard.php
```

Elvárt: `No syntax errors detected`. (A JavaScript szintaxisát ez nem ellenőrzi; a következő lépés arra való.)

- [ ] **Step 5: A JavaScript-ágak ellenőrzése**

```bash
grep -n "year" templates/riports_dashboard.php
```

Ellenőrizd a találatokban, hogy:

- `getSeriesList()` a `series_id: 0` elemmel kezdődik;
- mind az **öt** tanév-riport `showedInputs` ága tartalmaz `year: 1`-et;
- mind az **öt** tanév-riport `getSubmitUri` ága tartalmaz `&year=`-t;
- a négy intervallumos riport (`verseny_versenyszam`, `verseny_diak`, `legnepszerubb_sportag`, `iskola_sportoltatott_diakok`) ága **nem** kapott `year`-t.

- [ ] **Step 6: Commit**

```bash
git add templates/riports_dashboard.php
git commit -m "feat(riport): osszes szezon opcio es naptari ev mezo a tanev-riportoknal"
```

---

### Task 7: CHANGELOG bejegyzés

**Files:**
- Modify: `CHANGELOG.md`

**Interfaces:**
- Consumes: semmit
- Produces: semmit

A `build.sh` minden buildnél +1 patch verziót lép, a jelenlegi verzió `vitarex-vespa-plugin.php:7` szerint `2.3.20`, tehát a következő kiadás `2.3.21`.

- [ ] **Step 1: Bejegyzés beszúrása**

A `CHANGELOG.md` `# Changelog` sora UTÁN, a `## [2.3.18]` blokk ELÉ szúrd be:

```markdown
## [2.3.21] - 2026-08-03

### Új funkciók
- **Szezon és naptári év külön szűrhető a riportokban:** A tanév-alapú riportoknál a versenysorozat eddig kötelező volt, a naptári év pedig csak azon belül szűkített — tiszta naptári éves kimutatás így nem volt készíthető. Mostantól a versenysorozat legördülőben van „Összes szezon (nincs szűrés)" opció, és a naptári év mező mind az öt tanév-riportnál megjelenik. Mind a négy kombináció használható: csak szezon, csak naptári év, mindkettő (a szezon adott évbe eső fele), vagy egyik sem. Az alapértelmezés továbbra is a legutóbbi szezon.
- **Egyértelmű időszak-felirat az XLSX fejlécében:** Minden érintett riport fejléce megmondja, mi volt beállítva — „2025/2026", „2025/2026 — naptári év: 2025", „Naptári év: 2025" vagy „Nincs időszakszűrés (összes verseny)". Eddig a szezon és a naptári év összemosódott a fájlban.

### Javítások
- **Szezon riport szezon nélkül:** A Szezon riport eddig némán megszakadt (üres válasz), ha nem volt kiválasztva versenysorozat. Mostantól szezon nélkül is lefut.
- **Versenysorozat prepared paraméterként:** A „Tanévben versenyen indult iskolák" riport a kiválasztott versenysorozat azonosítóját közvetlenül, előkészített paraméter nélkül fűzte a lekérdezésbe.
- **Sérült XLSX szűrő nélküli riportnál:** Ha egy riport minden szűrője „összes" állásban volt, az adatbázis-réteg figyelmeztetést írhatott a válaszba, ami a bináris XLSX-fájlt megsértette. Érintett volt a „Tanévi diákolimpia diákok" riport intézmény-listája is.
```

- [ ] **Step 2: Commit**

```bash
git add CHANGELOG.md
git commit -m "docs: CHANGELOG a riport idoszak-szuro valtozasairol"
```

---

## Kézi QA — élesben, telepítés után

Automatizálva nem ellenőrizhető (nincs helyi WordPress). A build és telepítés után futtasd végig:

**Az öt tanév-riport, mind a négy időszak-kombinációval** (Riportok admin oldal → riporttípus → Versenysorozat / Naptári év beállítás → „Riport generálás"):

- [ ] Szezon riport — konkrét szezon + „Összes" év: a fejléc `Időszak` sorában a szezon neve; a számok egyeznek a változtatás előtti kimenettel
- [ ] Szezon riport — konkrét szezon + konkrét év
- [ ] Szezon riport — „Összes szezon" + konkrét év: a fejlécben `Naptári év: <év>`
- [ ] Szezon riport — „Összes szezon" + „Összes": a fejlécben `Nincs időszakszűrés (összes verseny)`, és a fájl megnyitható (nem sérült)
- [ ] Tanévi diákolimpia diákok — mind a négy kombináció
- [ ] Tanévben versenyen indult iskolák — mind a négy kombináció
- [ ] Tanévi versenyszámok — mind a négy kombináció
- [ ] Tanévi sportágankénti versenyek — mind a négy kombináció; ellenőrizd, hogy a szabadidős versenyek szezon nélkül is benne vannak, konkrét évnél viszont csak az adott éviek

**A négy intervallumos riport változatlansága** (ezeket a változtatás nem érintheti):

- [ ] Verseny és versenyszámok statisztika időszakra
- [ ] Verseny és diákok statisztika időszakra
- [ ] Adott időszakra a sportágak népszerűsége
- [ ] Adott időszakban versenyen indult diákok száma iskolánként

Mindegyiknél a fejléc `Időszak` cellájában továbbra is a dátumtól/dátumig értékek szerepeljenek, és a számok egyezzenek a változtatás előtti kimenettel.
