# Szabadidős nevezési mezők — implementációs terv

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A szabadidős (type-4) versenyekhez az adminban versenyenként szabadon összeállítható nevezési mezőkészlet, amit a külső résztvevő a nevezéskor tölt ki, és ami megjelenik az admin nevezőlistában és az XLSX exportban.

**Architecture:** A mezőlogika (típusok, validálás, formázás) egy tiszta, WordPress-független fájlba kerül a `Core` rétegbe, így sima `php` szkripttel unit-tesztelhető. Köré épülnek az adatbázist érintő helperek, majd négy admin AJAX végpont és a meglévő `signup` végpont kiterjesztése. Két új tábla tárolja a meződefiníciókat és a válaszokat, `is_active` alapú puha törléssel.

**Tech Stack:** PHP 7+, WordPress plugin API (`dbDelta`, options, admin-ajax, nonce), `$wpdb` közvetlen SQL-lel, natív `fetch` + `FormData` a front-enden és az adminban, PhpSpreadsheet az exporthoz. Nincs composer/phpunit — a unit tesztek sima `php` szkripttel futnak.

## Global Constraints

- Az `includes/{Core,Datalist,Admin,Ajax,Export,Api}/*.php` fájlokat a `vitarex-vespa-plugin.php:51-63` **automatikusan** betölti `glob`-bal, ebben a könyvtár-sorrendben. Új fájlt sehol nem kell kézzel `require`-elni.
- Az `includes/Core/vespa.szabadidos.fields.php` **csak függvényeket definiálhat** — se top-level hook, se `defined('ABSPATH') || exit;` guard, se WP-függvényhívás betöltéskor. Ez teszi lehetővé, hogy a teszt sima PHP-vel betöltse.
- Az adatbázistáblák neve `$wpdb->prefix` **NÉLKÜL** értendő (a plugin minden `vespa_*` táblája így hivatkozott).
- A séma a `vespa_szabadidos_install()` verziókapuján keresztül jön létre; a `vespa_szabadidos_db_version` option értéke `'2'` → `'3'`.
- `field_type` megengedett értékei pontosan: `egyvalasztos`, `tobbvalasztos`, `szoveg`, `hosszu_szoveg`, `szam`, `datum`, `nyilatkozat`.
- `field_options`: soronként egy válaszlehetőség, `\n` elválasztóval; csak `egyvalasztos` és `tobbvalasztos` típusnál, egyébként `NULL`.
- `answer_value`: `tobbvalasztos` esetén JSON tömb (`JSON_UNESCAPED_UNICODE`-dal kódolva), minden más típusnál sima skalár szöveg.
- A `nyilatkozat` típus **mindig** kötelező; a szerver mentéskor felülírja `is_required = 1`-re.
- Admin oldali jogosultság mindenhol: `VESPA_Roles::riportalas`. Admin nonce: `vespa_szabadidos_admin`. Front-end nonce: `vespa_szabadidos`.
- Minden admin AJAX végpont ellenőrzi, hogy a `contest_id` valóban `contest_type = 4`, és hogy a `field_id` ahhoz a versenyhez tartozik (IDOR-védelem, a `vespa_szabadidos_withdraw` mintájára).
- Magyar nyelvű felhasználói szövegek és kódkommentek, a kódbázis stílusában.
- Minden kimenet escape-elve: `esc_html`, `esc_attr`, `esc_url`. A JS az értékeket `value`/`textContent` beállítással teszi be, **nem** `innerHTML` interpolációval.
- Unit teszt futtatása: `php tests/test-szabadidos-fields.php` — 0-s kilépőkód a siker.

---

## File Structure

| Fájl | Felelősség |
|---|---|
| `includes/Core/vespa.szabadidos.fields.php` | *új* — tiszta mezőlogika: típuslista, definíció- és válaszvalidálás, megjelenítési formázás |
| `tests/test-szabadidos-fields.php` | *új* — a tiszta logika unit tesztjei |
| `includes/Core/vespa.szabadidos.install.php` | *módosul* — két új tábla, verzió `2` → `3` |
| `includes/Core/vespa.szabadidos.helpers.php` | *módosul* — adatbázist érintő mező- és válaszkezelés, közös nevező-lekérdezés |
| `includes/Ajax/szabadidos.fields.php` | *új* — négy admin AJAX végpont (save, delete, restore, move) |
| `includes/Ajax/szabadidos.entries.php` | *módosul* — a `signup` válaszokat is fogad, ellenőriz és ment |
| `templates/szabadidos_admin.php` | *módosul* — átrendezés, mezőszerkesztő blokk, dinamikus oszlopok a nevezőlistában |
| `templates/szabadidos_frontend.php` | *módosul* — nevezési űrlap a „Nevezek" gomb mögött |
| `includes/Export/szabadidos.export.php` | *módosul* — dinamikus oszlopok, közös lekérdezés használata |

**Task-sorrend és függőségek:** 1 → 2 → 3 → (4, 5) → 6 → 7. A 4. (admin UI) és az 5. (válaszkezelés) egymástól függetlenek, mindkettő a 3-ra épül.

---

### Task 1: Tiszta mezőlogika + unit tesztek

**Files:**
- Create: `includes/Core/vespa.szabadidos.fields.php`
- Test: `tests/test-szabadidos-fields.php`

**Interfaces:**
- Consumes: semmi (ez az első task)
- Produces:
  - `vespa_szabadidos_field_types()` → `array` (típuskulcs => magyar címke)
  - `vespa_szabadidos_field_type_valid($type)` → `bool`
  - `vespa_szabadidos_type_has_options($type)` → `bool`
  - `vespa_szabadidos_parse_options($text)` → `array` (válaszlehetőség-szövegek)
  - `vespa_szabadidos_validate_field($label, $type, $options_text, $is_required)` → `array('ok' => bool, 'error' => string, 'field' => array|null)`; a `field` kulcsai: `label`, `field_type`, `field_options`, `is_required`
  - `vespa_szabadidos_validate_answer($type, $is_required, $options, $raw)` → `array('ok' => bool, 'error' => string, 'value' => string|null)`
  - `vespa_szabadidos_format_answer($type, $stored)` → `string`
  - `vespa_szabadidos_decode_answer($type, $stored)` → `string|array`

- [ ] **Step 1: Write the failing test**

Hozd létre a `tests/test-szabadidos-fields.php` fájlt:

```php
<?php
/**
 * A szabadidős nevezési mezők tiszta logikájának unit tesztjei.
 * Futtatás: php tests/test-szabadidos-fields.php
 * WordPress nem kell hozzá: a tesztelt függvények tiszták.
 */

require_once __DIR__ . '/../includes/Core/vespa.szabadidos.fields.php';

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

// ---- Típusok -------------------------------------------------------------

allit(count(vespa_szabadidos_field_types()) === 7, 'het mezotipus van');
allit(vespa_szabadidos_field_type_valid('egyvalasztos'), 'egyvalasztos ervenyes tipus');
allit(vespa_szabadidos_field_type_valid('nyilatkozat'), 'nyilatkozat ervenyes tipus');
allit(!vespa_szabadidos_field_type_valid('valami'), 'ismeretlen tipus nem ervenyes');
allit(!vespa_szabadidos_field_type_valid(''), 'ures tipus nem ervenyes');
allit(vespa_szabadidos_type_has_options('tobbvalasztos'), 'tobbvalasztosnak van valaszlehetosege');
allit(!vespa_szabadidos_type_has_options('szoveg'), 'szovegnek nincs valaszlehetosege');

// ---- Válaszlehetőségek felbontása ---------------------------------------

allit(
    vespa_szabadidos_parse_options("S\nM\nL") === array('S', 'M', 'L'),
    'sortoresekkel elvalasztott lehetosegek'
);
allit(
    vespa_szabadidos_parse_options("S\r\nM\r\nL") === array('S', 'M', 'L'),
    'windows sorvegek is mukodnek'
);
allit(
    vespa_szabadidos_parse_options("  S  \n\n  M  \n") === array('S', 'M'),
    'ures sorok eldobva, whitespace levagva'
);
allit(
    vespa_szabadidos_parse_options("S\nM\nS") === array('S', 'M'),
    'ismetlodo lehetoseg csak egyszer szerepel'
);
allit(vespa_szabadidos_parse_options('') === array(), 'ures szovegbol ures tomb');
allit(vespa_szabadidos_parse_options(null) === array(), 'nullbol ures tomb');
allit(
    vespa_szabadidos_parse_options("Boccia; tollaslabda\nDarts") === array('Boccia; tollaslabda', 'Darts'),
    'a pontosvesszo a lehetoseg szovegeben megmarad'
);

// ---- Meződefiníció ellenőrzése ------------------------------------------

$e = vespa_szabadidos_validate_field('', 'szoveg', '', 0);
allit($e['ok'] === false, 'ures cimke elbukik');

$e = vespa_szabadidos_validate_field('   ', 'szoveg', '', 0);
allit($e['ok'] === false, 'csak whitespace cimke elbukik');

$e = vespa_szabadidos_validate_field('Cimke', 'ismeretlen', '', 0);
allit($e['ok'] === false, 'ismeretlen tipus elbukik');

$e = vespa_szabadidos_validate_field('Polomeret', 'egyvalasztos', '', 0);
allit($e['ok'] === false, 'valasztos tipus valaszlehetoseg nelkul elbukik');

$e = vespa_szabadidos_validate_field('Polomeret', 'egyvalasztos', "S\nM\nL", 1);
allit($e['ok'] === true, 'ervenyes egyvalasztos mezo atmegy');
allit($e['field']['field_options'] === "S\nM\nL", 'a lehetosegek normalizaltan mentodnek');
allit($e['field']['is_required'] === 1, 'a kotelezo jelzes megmarad');

$e = vespa_szabadidos_validate_field('  Csapatnev  ', 'szoveg', "figyelmen kivul", 0);
allit($e['field']['label'] === 'Csapatnev', 'a cimke whitespace-e levagva');
allit($e['field']['field_options'] === null, 'nem valasztos tipusnal nincs lehetosegek');
allit($e['field']['is_required'] === 0, 'nem kotelezo mezo jelzese megmarad');

$e = vespa_szabadidos_validate_field('Elfogadom a GDPR-t', 'nyilatkozat', '', 0);
allit($e['ok'] === true, 'nyilatkozat lehetosegek nelkul is ervenyes');
allit($e['field']['is_required'] === 1, 'a nyilatkozat mindig kotelezo lesz');

// ---- Válasz ellenőrzése --------------------------------------------------

$meretek = array('S', 'M', 'L');

$v = vespa_szabadidos_validate_answer('egyvalasztos', 1, $meretek, 'M');
allit($v['ok'] === true && $v['value'] === 'M', 'ervenyes egyvalasztos valasz');

$v = vespa_szabadidos_validate_answer('egyvalasztos', 1, $meretek, 'XXL');
allit($v['ok'] === false, 'listan kivuli egyvalasztos valasz elbukik');

$v = vespa_szabadidos_validate_answer('egyvalasztos', 1, $meretek, '');
allit($v['ok'] === false, 'kotelezo egyvalasztos ures valasza elbukik');

$v = vespa_szabadidos_validate_answer('egyvalasztos', 0, $meretek, '');
allit($v['ok'] === true && $v['value'] === null, 'nem kotelezo mezo uresen hagyhato');

$v = vespa_szabadidos_validate_answer('tobbvalasztos', 1, $meretek, array('S', 'L'));
allit($v['ok'] === true, 'ervenyes tobbvalasztos valasz');
allit(json_decode($v['value'], true) === array('S', 'L'), 'a tobbvalasztos valasz JSON tombkent tarolodik');

$v = vespa_szabadidos_validate_answer('tobbvalasztos', 1, $meretek, array('S', 'S'));
allit(json_decode($v['value'], true) === array('S'), 'az ismetlodo tobbvalasztos ertek egyszer tarolodik');

$v = vespa_szabadidos_validate_answer('tobbvalasztos', 1, $meretek, array('S', 'XXL'));
allit($v['ok'] === false, 'listan kivuli tobbvalasztos ertek elbukik');

$v = vespa_szabadidos_validate_answer('tobbvalasztos', 1, $meretek, array());
allit($v['ok'] === false, 'kotelezo tobbvalasztos ures valasza elbukik');

$v = vespa_szabadidos_validate_answer('tobbvalasztos', 0, $meretek, array());
allit($v['ok'] === true && $v['value'] === null, 'nem kotelezo tobbvalasztos uresen hagyhato');

$v = vespa_szabadidos_validate_answer('nyilatkozat', 0, array(), '1');
allit($v['ok'] === true && $v['value'] === '1', 'elfogadott nyilatkozat atmegy');

$v = vespa_szabadidos_validate_answer('nyilatkozat', 0, array(), '');
allit($v['ok'] === false, 'el nem fogadott nyilatkozat elbukik a nem kotelezo jelzes ellenere is');

$v = vespa_szabadidos_validate_answer('szam', 1, array(), '12');
allit($v['ok'] === true && $v['value'] === '12', 'ervenyes szam');

$v = vespa_szabadidos_validate_answer('szam', 1, array(), 'tizenketto');
allit($v['ok'] === false, 'nem numerikus szam elbukik');

$v = vespa_szabadidos_validate_answer('datum', 1, array(), '2026-09-26');
allit($v['ok'] === true && $v['value'] === '2026-09-26', 'ervenyes datum');

$v = vespa_szabadidos_validate_answer('datum', 1, array(), '2026-02-30');
allit($v['ok'] === false, 'nem letezo naptari nap elbukik');

$v = vespa_szabadidos_validate_answer('datum', 1, array(), '2026.09.26.');
allit($v['ok'] === false, 'rossz formatumu datum elbukik');

$v = vespa_szabadidos_validate_answer('szoveg', 1, array(), '  Sasok  ');
allit($v['ok'] === true && $v['value'] === 'Sasok', 'a szoveges valasz whitespace-e levagva');

$v = vespa_szabadidos_validate_answer('szoveg', 1, array(), '   ');
allit($v['ok'] === false, 'csak whitespace kotelezo szoveg elbukik');

// ---- Megjelenítés és visszaolvasás ---------------------------------------

allit(
    vespa_szabadidos_format_answer('tobbvalasztos', json_encode(array('Boccia', 'Darts'))) === 'Boccia, Darts',
    'tobbvalasztos valasz vesszovel osszefuzve jelenik meg'
);
allit(
    vespa_szabadidos_format_answer('tobbvalasztos', 'nem json') === 'nem json',
    'romlott JSON eseten a nyers ertek jelenik meg'
);
allit(vespa_szabadidos_format_answer('nyilatkozat', '1') === 'igen', 'elfogadott nyilatkozat megjelenitese');
allit(vespa_szabadidos_format_answer('nyilatkozat', '0') === '', 'el nem fogadott nyilatkozat uresen jelenik meg');
allit(vespa_szabadidos_format_answer('szoveg', null) === '', 'null valasz uresen jelenik meg');
allit(vespa_szabadidos_format_answer('szoveg', 'Sasok') === 'Sasok', 'szoveges valasz valtozatlanul jelenik meg');

allit(
    vespa_szabadidos_decode_answer('tobbvalasztos', json_encode(array('S', 'M'))) === array('S', 'M'),
    'tobbvalasztos valasz tombkent olvashato vissza'
);
allit(
    vespa_szabadidos_decode_answer('tobbvalasztos', null) === array(),
    'null tobbvalasztos valaszbol ures tomb'
);
allit(vespa_szabadidos_decode_answer('szoveg', null) === '', 'null skalar valaszbol ures szoveg');
allit(vespa_szabadidos_decode_answer('szoveg', 'Sasok') === 'Sasok', 'skalar valasz valtozatlanul olvashato vissza');

echo "\n" . ($hibak === 0 ? "Minden teszt sikeres.\n" : $hibak . " teszt elbukott.\n");
exit($hibak === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test-szabadidos-fields.php`
Expected: FAIL — `Failed opening required '.../includes/Core/vespa.szabadidos.fields.php'`

- [ ] **Step 3: Write minimal implementation**

Hozd létre az `includes/Core/vespa.szabadidos.fields.php` fájlt:

```php
<?php

/**
 * A szabadidős nevezési mezők tiszta, WordPress-független logikája.
 *
 * Ez a fájl KIZÁRÓLAG függvényeket definiál — se hook, se ABSPATH-guard, se
 * WP-hívás betöltéskor. Így a tests/test-szabadidos-fields.php sima PHP-vel
 * betöltheti. Az adatbázist érintő rész a vespa.szabadidos.helpers.php-ban van.
 */

/**
 * A választható mezőtípusok: gépi kulcs => admin felületen megjelenő címke.
 */
function vespa_szabadidos_field_types()
{
    return array(
        'egyvalasztos'  => 'Egyválasztós (rádiógomb)',
        'tobbvalasztos' => 'Többválasztós (jelölőnégyzetek)',
        'szoveg'        => 'Szöveg (egysoros)',
        'hosszu_szoveg' => 'Szöveg (többsoros)',
        'szam'          => 'Szám',
        'datum'         => 'Dátum',
        'nyilatkozat'   => 'Nyilatkozat (kötelező elfogadás)',
    );
}

function vespa_szabadidos_field_type_valid($type)
{
    return is_string($type) && array_key_exists($type, vespa_szabadidos_field_types());
}

/**
 * Igaz, ha a típushoz válaszlehetőség-listát kell megadni.
 */
function vespa_szabadidos_type_has_options($type)
{
    return $type === 'egyvalasztos' || $type === 'tobbvalasztos';
}

/**
 * A soronként egy válaszlehetőséget tartalmazó szövegből tömb.
 * Az üres sorokat és az ismétlődéseket eldobja, a whitespace-t levágja.
 */
function vespa_szabadidos_parse_options($text)
{
    if (!is_string($text) || $text === '') {
        return array();
    }

    $eredmeny = array();
    foreach (preg_split('/\r\n|\r|\n/', $text) as $sor) {
        $sor = trim($sor);
        if ($sor !== '' && !in_array($sor, $eredmeny, true)) {
            $eredmeny[] = $sor;
        }
    }

    return $eredmeny;
}

/**
 * Meződefiníció ellenőrzése és normalizálása mentés előtt.
 * Siker esetén a 'field' kulcs alatt adja vissza a menthető sort.
 */
function vespa_szabadidos_validate_field($label, $type, $options_text, $is_required)
{
    $label = trim((string) $label);
    if ($label === '') {
        return array('ok' => false, 'error' => 'A címke nem lehet üres.', 'field' => null);
    }

    if (!vespa_szabadidos_field_type_valid($type)) {
        return array('ok' => false, 'error' => 'Ismeretlen mezőtípus.', 'field' => null);
    }

    $opciok = array();
    if (vespa_szabadidos_type_has_options($type)) {
        $opciok = vespa_szabadidos_parse_options($options_text);
        if (count($opciok) === 0) {
            return array('ok' => false, 'error' => 'Adj meg legalább egy válaszlehetőséget.', 'field' => null);
        }
    }

    // A "nem kötelező nyilatkozat" értelmetlen: az elfogadást mindig ki kell pipálni.
    $kotelezo = ($type === 'nyilatkozat') ? 1 : ($is_required ? 1 : 0);

    return array('ok' => true, 'error' => '', 'field' => array(
        'label'         => $label,
        'field_type'    => $type,
        'field_options' => $opciok ? implode("\n", $opciok) : null,
        'is_required'   => $kotelezo,
    ));
}

/**
 * Naptárilag is létező, éééé-hh-nn alakú dátum?
 */
function vespa_szabadidos_valid_date($ertek)
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ertek, $m)) {
        return false;
    }
    return checkdate(intval($m[2]), intval($m[3]), intval($m[1]));
}

/**
 * Egy beküldött válasz ellenőrzése és tárolási alakra hozása.
 *
 * $raw: többválasztósnál tömb, minden más típusnál szöveg.
 * $options: a mező válaszlehetőségei (vespa_szabadidos_parse_options eredménye).
 * A 'value' null, ha a mező üresen maradhatott — ilyenkor nincs mit tárolni.
 */
function vespa_szabadidos_validate_answer($type, $is_required, $options, $raw)
{
    $kotelezo = ($type === 'nyilatkozat') ? true : (bool) $is_required;

    if ($type === 'tobbvalasztos') {
        $tisztitott = array();
        foreach ((is_array($raw) ? $raw : array()) as $elem) {
            $elem = trim((string) $elem);
            if ($elem === '') {
                continue;
            }
            if (!in_array($elem, $options, true)) {
                return array('ok' => false, 'error' => 'Érvénytelen válaszlehetőség.', 'value' => null);
            }
            if (!in_array($elem, $tisztitott, true)) {
                $tisztitott[] = $elem;
            }
        }
        if (count($tisztitott) === 0) {
            if ($kotelezo) {
                return array('ok' => false, 'error' => 'Ezt a mezőt ki kell tölteni.', 'value' => null);
            }
            return array('ok' => true, 'error' => '', 'value' => null);
        }
        return array(
            'ok'    => true,
            'error' => '',
            'value' => json_encode($tisztitott, JSON_UNESCAPED_UNICODE),
        );
    }

    $ertek = is_array($raw) ? '' : trim((string) $raw);

    if ($type === 'nyilatkozat') {
        if ($ertek !== '1') {
            return array('ok' => false, 'error' => 'Ezt a nyilatkozatot el kell fogadni.', 'value' => null);
        }
        return array('ok' => true, 'error' => '', 'value' => '1');
    }

    if ($ertek === '') {
        if ($kotelezo) {
            return array('ok' => false, 'error' => 'Ezt a mezőt ki kell tölteni.', 'value' => null);
        }
        return array('ok' => true, 'error' => '', 'value' => null);
    }

    if ($type === 'egyvalasztos' && !in_array($ertek, $options, true)) {
        return array('ok' => false, 'error' => 'Érvénytelen válaszlehetőség.', 'value' => null);
    }
    if ($type === 'szam' && !is_numeric($ertek)) {
        return array('ok' => false, 'error' => 'Csak számot adhatsz meg.', 'value' => null);
    }
    if ($type === 'datum' && !vespa_szabadidos_valid_date($ertek)) {
        return array('ok' => false, 'error' => 'A dátum formátuma éééé-hh-nn.', 'value' => null);
    }

    return array('ok' => true, 'error' => '', 'value' => $ertek);
}

/**
 * A tárolt válasz megjelenítési alakja (admin lista, XLSX export).
 */
function vespa_szabadidos_format_answer($type, $stored)
{
    if ($stored === null || $stored === '') {
        return '';
    }

    if ($type === 'tobbvalasztos') {
        $tomb = json_decode($stored, true);
        // Romlott vagy régi formátumú adatot nyersen mutatunk, nem nyelünk el.
        return is_array($tomb) ? implode(', ', $tomb) : (string) $stored;
    }

    if ($type === 'nyilatkozat') {
        return $stored === '1' ? 'igen' : '';
    }

    return (string) $stored;
}

/**
 * A tárolt válasz beviteli-mező alakja (űrlap előre kitöltése).
 */
function vespa_szabadidos_decode_answer($type, $stored)
{
    if ($type === 'tobbvalasztos') {
        if ($stored === null || $stored === '') {
            return array();
        }
        $tomb = json_decode($stored, true);
        return is_array($tomb) ? $tomb : array();
    }

    return $stored === null ? '' : (string) $stored;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test-szabadidos-fields.php`
Expected: PASS — minden sor `OK`, a záró sor `Minden teszt sikeres.`, kilépőkód 0

- [ ] **Step 5: Commit**

```bash
git add includes/Core/vespa.szabadidos.fields.php tests/test-szabadidos-fields.php
git commit -m "feat(szabadidos): nevezési mezők tiszta logikája és unit tesztjei"
```

---

### Task 2: Adatbázis-séma

**Files:**
- Modify: `includes/Core/vespa.szabadidos.install.php:12` (verziókapu) és `:54-58` (dbDelta hívások)

**Interfaces:**
- Consumes: semmi
- Produces: `vespa_szabadidos_fields` és `vespa_szabadidos_answers` táblák; a `vespa_szabadidos_db_version` option értéke `'3'`

Ehhez a taskhoz nincs unit teszt: a `dbDelta` futó WordPress-t igényel, helyi WP-környezet pedig nincs. A verifikáció a szintaxis-ellenőrzés és a Task 7 végén leírt telepítés utáni kézi próba.

- [ ] **Step 1: Verziókapu léptetése**

Az `includes/Core/vespa.szabadidos.install.php` fájlban cseréld ki ezt:

```php
    $telepitett = get_option('vespa_szabadidos_db_version');
    if ($telepitett === '2') {
        return;
    }
```

erre:

```php
    $telepitett = get_option('vespa_szabadidos_db_version');
    if ($telepitett === '3') {
        return;
    }
```

- [ ] **Step 2: A két új tábla hozzáadása**

Ugyanebben a fájlban, a `$sql_open` definíciója **után**, a `dbDelta($sql_participants);` sor **elé** illeszd be:

```php
    // A nevezési mezők definíciója versenyenként. Az is_active a puha törlés:
    // a kikapcsolt mező eltűnik a front-endről, de a rá adott válaszok maradnak.
    $sql_fields = "CREATE TABLE vespa_szabadidos_fields (
  field_id      bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  contest_id    bigint(20) unsigned NOT NULL,
  label         varchar(255) NOT NULL,
  field_type    varchar(20) NOT NULL,
  field_options text DEFAULT NULL,
  is_required   tinyint(1) NOT NULL DEFAULT 0,
  ordernum      int(11) NOT NULL DEFAULT 0,
  is_active     tinyint(1) NOT NULL DEFAULT 1,
  created_at    datetime NOT NULL,
  PRIMARY KEY  (field_id),
  KEY contest_id (contest_id, is_active, ordernum)
) $charset_collate;";

    // Résztvevőnként és mezőnként egy válasz. A contest_id szándékosan
    // redundáns (a field_id-ból levezethető) — nélküle az export minden
    // sorhoz plusz join-t igényelne.
    $sql_answers = "CREATE TABLE vespa_szabadidos_answers (
  answer_id      bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  participant_id bigint(20) unsigned NOT NULL,
  contest_id     bigint(20) unsigned NOT NULL,
  field_id       bigint(20) unsigned NOT NULL,
  answer_value   text DEFAULT NULL,
  updated_at     datetime NOT NULL,
  PRIMARY KEY  (answer_id),
  UNIQUE KEY uniq_reszt_mezo (participant_id, field_id),
  KEY contest_id (contest_id)
) $charset_collate;";
```

Majd a meglévő `dbDelta($sql_open);` sor **után** illeszd be:

```php
    dbDelta($sql_fields);
    dbDelta($sql_answers);
```

Végül cseréld a záró sort:

```php
    update_option('vespa_szabadidos_db_version', '3');
```

- [ ] **Step 3: Szintaxis-ellenőrzés**

Run: `php -l includes/Core/vespa.szabadidos.install.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
git add includes/Core/vespa.szabadidos.install.php
git commit -m "feat(szabadidos): nevezési mezők és válaszok táblái (db verzió 3)"
```

---

### Task 3: Adatbázis-helperek

**Files:**
- Modify: `includes/Core/vespa.szabadidos.helpers.php` (a fájl végére fűzve)

**Interfaces:**
- Consumes: Task 1 `vespa_szabadidos_parse_options()`, `vespa_szabadidos_format_answer()`
- Produces:
  - `vespa_szabadidos_contest_is_type4($contest_id)` → `bool`
  - `vespa_szabadidos_get_fields($contest_id, $csak_aktiv = true)` → `array` of `stdClass` (oszlopok: `field_id`, `contest_id`, `label`, `field_type`, `field_options`, `is_required`, `ordernum`, `is_active`)
  - `vespa_szabadidos_get_field($field_id)` → `stdClass|null`
  - `vespa_szabadidos_field_answer_count($field_id)` → `int`
  - `vespa_szabadidos_next_ordernum($contest_id)` → `int`
  - `vespa_szabadidos_has_answers($participant_id, $contest_id)` → `bool`
  - `vespa_szabadidos_save_answers($participant_id, $contest_id, $ertekek)` → `void` (`$ertekek`: `field_id => string|null`)
  - `vespa_szabadidos_entry_table($contest_id)` → `array('columns' => array, 'rows' => array)`

A `columns` elemei: `array('field_id' => int, 'label' => string, 'field_type' => string, 'archived' => bool)`.
A `rows` elemei: `array('nevezo' => stdClass, 'answers' => array(field_id => string), 'missing' => bool)`, ahol a `nevezo` oszlopai `full_name`, `birth_date`, `gender`, `email`, `phone`, `entry_date`, `sport_name`, `sport_event_name`.

Ehhez a taskhoz sincs unit teszt: minden függvény `$wpdb`-t használ. A verifikáció szintaxis-ellenőrzés, a funkcionális próba a Task 7 végén.

- [ ] **Step 1: Mezőolvasó helperek**

Fűzd az `includes/Core/vespa.szabadidos.helpers.php` végére:

```php
/**
 * Igaz, ha a megadott verseny létezik és szabadidős (type-4).
 * Minden mezőkezelő végpont ezzel kapuz — csak type-4 versenynek lehet
 * nevezési mezője.
 */
function vespa_szabadidos_contest_is_type4($contest_id)
{
    global $wpdb;
    $tipus = $wpdb->get_var($wpdb->prepare(
        "SELECT contest_type FROM vespa_contests WHERE contest_id=%d",
        intval($contest_id)
    ));
    return $tipus !== null && intval($tipus) === 4;
}

/**
 * A verseny nevezési mezői sorrendben.
 * $csak_aktiv = false esetén az archivált (puhán törölt) mezőket is hozza.
 */
function vespa_szabadidos_get_fields($contest_id, $csak_aktiv = true)
{
    global $wpdb;
    $contest_id = intval($contest_id);
    if ($contest_id <= 0) {
        return array();
    }

    if ($csak_aktiv) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM vespa_szabadidos_fields
             WHERE contest_id=%d AND is_active=1
             ORDER BY ordernum ASC, field_id ASC",
            $contest_id
        ));
    }

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM vespa_szabadidos_fields
         WHERE contest_id=%d
         ORDER BY is_active DESC, ordernum ASC, field_id ASC",
        $contest_id
    ));
}

/**
 * Egyetlen mező sora, vagy null.
 */
function vespa_szabadidos_get_field($field_id)
{
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM vespa_szabadidos_fields WHERE field_id=%d",
        intval($field_id)
    ));
    return $row ? $row : null;
}

/**
 * Hány válasz érkezett már erre a mezőre? A törlés ez alapján dönt puha és
 * végleges törlés között.
 */
function vespa_szabadidos_field_answer_count($field_id)
{
    global $wpdb;
    return intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM vespa_szabadidos_answers WHERE field_id=%d",
        intval($field_id)
    )));
}

/**
 * A verseny következő szabad sorszáma (új mező a lista végére kerül).
 */
function vespa_szabadidos_next_ordernum($contest_id)
{
    global $wpdb;
    $max = $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(ordernum) FROM vespa_szabadidos_fields WHERE contest_id=%d",
        intval($contest_id)
    ));
    return $max === null ? 0 : intval($max) + 1;
}
```

- [ ] **Step 2: Válaszkezelő helperek**

Fűzd ugyanennek a fájlnak a végére:

```php
/**
 * Igaz, ha a résztvevő erre a versenyre már kitöltötte a nevezési mezőket.
 * Versenyenként egyszer kérdezünk: ha van bármilyen válasza, nem kérdezünk újra.
 */
function vespa_szabadidos_has_answers($participant_id, $contest_id)
{
    global $wpdb;
    $db = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM vespa_szabadidos_answers WHERE participant_id=%d AND contest_id=%d",
        intval($participant_id),
        intval($contest_id)
    ));
    return intval($db) > 0;
}

/**
 * A résztvevő válaszai egy versenyre: field_id => tárolt érték.
 */
function vespa_szabadidos_get_answers($participant_id, $contest_id)
{
    global $wpdb;
    $sorok = $wpdb->get_results($wpdb->prepare(
        "SELECT field_id, answer_value FROM vespa_szabadidos_answers
         WHERE participant_id=%d AND contest_id=%d",
        intval($participant_id),
        intval($contest_id)
    ));

    $eredmeny = array();
    foreach ((array) $sorok as $sor) {
        $eredmeny[intval($sor->field_id)] = $sor->answer_value;
    }
    return $eredmeny;
}

/**
 * Válaszok mentése. $ertekek: field_id => tárolt érték (null = nincs mit menteni).
 * Az uniq_reszt_mezo index miatt az ismételt mentés frissít, nem duplikál.
 */
function vespa_szabadidos_save_answers($participant_id, $contest_id, $ertekek)
{
    global $wpdb;
    $most = current_time('mysql');

    foreach ($ertekek as $field_id => $ertek) {
        if ($ertek === null) {
            continue;
        }
        $wpdb->query($wpdb->prepare(
            "INSERT INTO vespa_szabadidos_answers
                (participant_id, contest_id, field_id, answer_value, updated_at)
             VALUES (%d, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE answer_value=VALUES(answer_value), updated_at=VALUES(updated_at)",
            intval($participant_id),
            intval($contest_id),
            intval($field_id),
            $ertek,
            $most
        ));
    }
}
```

- [ ] **Step 3: Közös nevező-lekérdezés**

Fűzd ugyanennek a fájlnak a végére. Ezt hívja az admin lista és az XLSX export is — a lekérdezés ma két helyen, egymással megegyező SQL-ként szerepel, és a dinamikus oszlopok után garantáltan szétcsúszna:

```php
/**
 * A verseny külső nevezői a nevezési mezőkre adott válaszokkal együtt.
 * Egy sor = egy nevezés (versenyszám), tehát aki két versenyszámra nevezett,
 * két sorral szerepel — mindkettőnél ugyanazokkal a válaszokkal.
 *
 * Az oszlopok közé bekerül minden aktív mező, továbbá minden archivált mező,
 * amire van válasz. Ez a puha törlés lényege: a régi adat az exportból sem
 * tűnik el.
 */
function vespa_szabadidos_entry_table($contest_id)
{
    global $wpdb;
    $contest_id = intval($contest_id);

    $nevezok = $wpdb->get_results($wpdb->prepare(
        "SELECT e.participant_id, p.full_name, p.birth_date, p.gender, p.email, p.phone,
                e.entry_date, vse.sport_event_name, vs.sport_name
         FROM vespa_external_entries AS e
         INNER JOIN vespa_external_participants AS p ON p.participant_id=e.participant_id
         LEFT JOIN vespa_constest_events AS vce ON vce.id=e.contest_event_id
         LEFT JOIN vespa_sport_events AS vse ON vse.sport_event_id=vce.event_id
         LEFT JOIN vespa_sports AS vs ON vs.sport_id=vce.sport_id
         WHERE e.contest_id=%d
         ORDER BY p.full_name",
        $contest_id
    ));

    $mezok = $wpdb->get_results($wpdb->prepare(
        "SELECT f.* FROM vespa_szabadidos_fields AS f
         WHERE f.contest_id=%d
           AND (f.is_active=1
                OR EXISTS (SELECT 1 FROM vespa_szabadidos_answers AS a WHERE a.field_id=f.field_id))
         ORDER BY f.ordernum ASC, f.field_id ASC",
        $contest_id
    ));

    $valaszsorok = $wpdb->get_results($wpdb->prepare(
        "SELECT participant_id, field_id, answer_value
         FROM vespa_szabadidos_answers WHERE contest_id=%d",
        $contest_id
    ));

    // participant_id => field_id => nyers érték
    $valaszok = array();
    foreach ((array) $valaszsorok as $v) {
        $valaszok[intval($v->participant_id)][intval($v->field_id)] = $v->answer_value;
    }

    $columns = array();
    foreach ((array) $mezok as $m) {
        $columns[] = array(
            'field_id'   => intval($m->field_id),
            'label'      => $m->label,
            'field_type' => $m->field_type,
            'archived'   => intval($m->is_active) === 0,
        );
    }

    $rows = array();
    foreach ((array) $nevezok as $n) {
        $pid = intval($n->participant_id);
        $sajat = isset($valaszok[$pid]) ? $valaszok[$pid] : array();

        $megjelenitett = array();
        $hianyzik = false;
        foreach ((array) $mezok as $m) {
            $fid = intval($m->field_id);
            $nyers = isset($sajat[$fid]) ? $sajat[$fid] : null;
            $megjelenitett[$fid] = vespa_szabadidos_format_answer($m->field_type, $nyers);

            // Csak az aktív, kötelező mező hiánya számít pótolandónak: az
            // archivált mezőt már senkitől nem várjuk el.
            if (intval($m->is_active) === 1 && intval($m->is_required) === 1 && $megjelenitett[$fid] === '') {
                $hianyzik = true;
            }
        }

        $rows[] = array(
            'nevezo'  => $n,
            'answers' => $megjelenitett,
            'missing' => $hianyzik,
        );
    }

    return array('columns' => $columns, 'rows' => $rows);
}
```

- [ ] **Step 4: Szintaxis-ellenőrzés és a meglévő tesztek**

Run: `php -l includes/Core/vespa.szabadidos.helpers.php && php tests/test-szabadidos-fields.php && php tests/test-frontend-access.php`
Expected: `No syntax errors detected`, majd mindkét tesztfájl `Minden teszt sikeres.`

- [ ] **Step 5: Commit**

```bash
git add includes/Core/vespa.szabadidos.helpers.php
git commit -m "feat(szabadidos): mező- és válaszkezelő adatbázis-helperek"
```

---

### Task 4: Admin AJAX végpontok

**Files:**
- Create: `includes/Ajax/szabadidos.fields.php`

**Interfaces:**
- Consumes: Task 1 `vespa_szabadidos_validate_field()`, `vespa_szabadidos_field_types()`; Task 3 `vespa_szabadidos_contest_is_type4()`, `vespa_szabadidos_get_field()`, `vespa_szabadidos_get_fields()`, `vespa_szabadidos_field_answer_count()`, `vespa_szabadidos_next_ordernum()`
- Produces: négy `wp_ajax_` végpont — `vespa_szabadidos_field_save`, `vespa_szabadidos_field_delete`, `vespa_szabadidos_field_restore`, `vespa_szabadidos_field_move`. Mindegyik `wp_send_json_success(array('message' => string, 'fields' => array))` alakban válaszol, ahol a `fields` a verseny **teljes**, frissített mezőlistája (aktív + archivált), minden elemben `answer_count`-tal.

A teljes lista visszaadása szándékos: a kliensnek így soha nem kell kitalálnia, mi lett a sorrend vagy az archivált állapot — egyszerűen újrarajzol.

- [ ] **Step 1: A fájl váza és a közös kapuőr**

Hozd létre az `includes/Ajax/szabadidos.fields.php` fájlt:

```php
<?php

/**
 * A szabadidős nevezési mezők admin AJAX végpontjai.
 *
 * Mind a négy végpont ugyanazt a kaput használja: vespa_szabadidos_admin
 * nonce + VESPA_Roles::riportalas jogosultság + type-4 verseny. A field_id-t
 * mindig a contest_id-hoz kötve ellenőrizzük (IDOR-védelem), ahogy a
 * vespa_szabadidos_withdraw is teszi a saját sorával.
 */

/**
 * A közös belépési ellenőrzés. Hiba esetén maga küldi a JSON választ és kilép.
 * Visszatérés: az ellenőrzött contest_id.
 */
function vespa_szabadidos_fields_gate()
{
    check_ajax_referer('vespa_szabadidos_admin', 'nonce');

    if (!current_user_can(VESPA_Roles::riportalas)) {
        wp_send_json_error(array('message' => 'Jogosulatlan hozzáférés.'));
    }

    $contest_id = isset($_POST['contest_id']) ? intval($_POST['contest_id']) : 0;
    if ($contest_id <= 0 || !vespa_szabadidos_contest_is_type4($contest_id)) {
        wp_send_json_error(array('message' => 'Csak szabadidős versenyhez vehető fel nevezési mező.'));
    }

    return $contest_id;
}

/**
 * A verseny mezőlistája a kliensnek: aktív és archivált mezők, válaszszámmal.
 */
function vespa_szabadidos_fields_payload($contest_id)
{
    $lista = array();
    foreach (vespa_szabadidos_get_fields($contest_id, false) as $mezo) {
        $lista[] = array(
            'field_id'      => intval($mezo->field_id),
            'label'         => $mezo->label,
            'field_type'    => $mezo->field_type,
            'field_options' => $mezo->field_options === null ? '' : $mezo->field_options,
            'is_required'   => intval($mezo->is_required),
            'ordernum'      => intval($mezo->ordernum),
            'is_active'     => intval($mezo->is_active),
            'answer_count'  => vespa_szabadidos_field_answer_count($mezo->field_id),
        );
    }
    return $lista;
}

/**
 * A megadott mező ellenőrzése: létezik-e, és tényleg ehhez a versenyhez tartozik-e.
 * Hiba esetén maga küldi a JSON választ és kilép.
 */
function vespa_szabadidos_fields_require_own($field_id, $contest_id)
{
    $mezo = vespa_szabadidos_get_field($field_id);
    if (!$mezo || intval($mezo->contest_id) !== intval($contest_id)) {
        wp_send_json_error(array('message' => 'A mező nem található.'));
    }
    return $mezo;
}
```

- [ ] **Step 2: A mentő végpont**

Fűzd ugyanennek a fájlnak a végére:

```php
add_action('wp_ajax_vespa_szabadidos_field_save', 'vespa_szabadidos_field_save');

function vespa_szabadidos_field_save()
{
    $contest_id = vespa_szabadidos_fields_gate();

    $field_id = isset($_POST['field_id']) ? intval($_POST['field_id']) : 0;
    $label    = isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '';
    $type     = isset($_POST['field_type']) ? sanitize_text_field(wp_unslash($_POST['field_type'])) : '';
    $opciok   = isset($_POST['field_options']) ? sanitize_textarea_field(wp_unslash($_POST['field_options'])) : '';
    $kotelezo = isset($_POST['is_required']) && $_POST['is_required'] === '1';

    $ellenorzes = vespa_szabadidos_validate_field($label, $type, $opciok, $kotelezo);
    if (!$ellenorzes['ok']) {
        wp_send_json_error(array('message' => $ellenorzes['error']));
    }
    $adat = $ellenorzes['field'];

    global $wpdb;

    if ($field_id > 0) {
        vespa_szabadidos_fields_require_own($field_id, $contest_id);
        $wpdb->update(
            'vespa_szabadidos_fields',
            $adat,
            array('field_id' => $field_id),
            array('%s', '%s', '%s', '%d'),
            array('%d')
        );
        $uzenet = 'Mentve.';
    } else {
        $adat['contest_id'] = $contest_id;
        $adat['ordernum']   = vespa_szabadidos_next_ordernum($contest_id);
        $adat['is_active']  = 1;
        $adat['created_at'] = current_time('mysql');

        $siker = $wpdb->insert('vespa_szabadidos_fields', $adat);
        if ($siker === false) {
            wp_send_json_error(array('message' => 'A mező mentése nem sikerült.'));
        }
        $field_id = intval($wpdb->insert_id);
        $uzenet = 'Az új mező mentve.';
    }

    wp_send_json_success(array(
        'message'  => $uzenet,
        'field_id' => $field_id,
        'fields'   => vespa_szabadidos_fields_payload($contest_id),
    ));
}
```

- [ ] **Step 3: A törlő és visszaállító végpont**

Fűzd ugyanennek a fájlnak a végére:

```php
add_action('wp_ajax_vespa_szabadidos_field_delete', 'vespa_szabadidos_field_delete');

function vespa_szabadidos_field_delete()
{
    $contest_id = vespa_szabadidos_fields_gate();

    $field_id = isset($_POST['field_id']) ? intval($_POST['field_id']) : 0;
    vespa_szabadidos_fields_require_own($field_id, $contest_id);

    global $wpdb;

    // Ha még nincs rá válasz, nincs mit megőrizni — a sor valóban törölhető.
    if (vespa_szabadidos_field_answer_count($field_id) === 0) {
        $wpdb->delete('vespa_szabadidos_fields', array('field_id' => $field_id), array('%d'));
        $uzenet = 'A mező törölve.';
    } else {
        $wpdb->update(
            'vespa_szabadidos_fields',
            array('is_active' => 0),
            array('field_id' => $field_id),
            array('%d'),
            array('%d')
        );
        $uzenet = 'A mező archiválva. A beérkezett válaszok megmaradnak.';
    }

    wp_send_json_success(array(
        'message' => $uzenet,
        'fields'  => vespa_szabadidos_fields_payload($contest_id),
    ));
}

add_action('wp_ajax_vespa_szabadidos_field_restore', 'vespa_szabadidos_field_restore');

function vespa_szabadidos_field_restore()
{
    $contest_id = vespa_szabadidos_fields_gate();

    $field_id = isset($_POST['field_id']) ? intval($_POST['field_id']) : 0;
    vespa_szabadidos_fields_require_own($field_id, $contest_id);

    global $wpdb;
    $wpdb->update(
        'vespa_szabadidos_fields',
        array('is_active' => 1, 'ordernum' => vespa_szabadidos_next_ordernum($contest_id)),
        array('field_id' => $field_id),
        array('%d', '%d'),
        array('%d')
    );

    wp_send_json_success(array(
        'message' => 'A mező visszakapcsolva.',
        'fields'  => vespa_szabadidos_fields_payload($contest_id),
    ));
}
```

- [ ] **Step 4: A sorrendező végpont**

Fűzd ugyanennek a fájlnak a végére:

```php
add_action('wp_ajax_vespa_szabadidos_field_move', 'vespa_szabadidos_field_move');

function vespa_szabadidos_field_move()
{
    $contest_id = vespa_szabadidos_fields_gate();

    $field_id = isset($_POST['field_id']) ? intval($_POST['field_id']) : 0;
    $irany    = (isset($_POST['direction']) && $_POST['direction'] === 'up') ? 'up' : 'down';
    vespa_szabadidos_fields_require_own($field_id, $contest_id);

    // Csak az aktív mezőket rendezzük: az archiváltak a lista végén ülnek.
    $aktivak = vespa_szabadidos_get_fields($contest_id, true);
    $index = -1;
    foreach ($aktivak as $i => $mezo) {
        if (intval($mezo->field_id) === $field_id) {
            $index = $i;
            break;
        }
    }

    $szomszed = ($irany === 'up') ? $index - 1 : $index + 1;
    if ($index < 0 || $szomszed < 0 || $szomszed >= count($aktivak)) {
        // A lista szélén álló mező mozgatása nem hiba, csak nincs mit tenni.
        wp_send_json_success(array(
            'message' => '',
            'fields'  => vespa_szabadidos_fields_payload($contest_id),
        ));
    }

    // A tárolt ordernum értékek lehetnek hézagosak vagy azonosak (régi adat,
    // párhuzamos szerkesztés), ezért nem cserélgetünk: az egész aktív listát
    // újraszámozzuk 0-tól, a megcserélt sorrend szerint.
    $sorrend = $aktivak;
    $ideiglenes = $sorrend[$index];
    $sorrend[$index] = $sorrend[$szomszed];
    $sorrend[$szomszed] = $ideiglenes;

    global $wpdb;
    foreach ($sorrend as $uj_index => $mezo) {
        $wpdb->update(
            'vespa_szabadidos_fields',
            array('ordernum' => $uj_index),
            array('field_id' => intval($mezo->field_id)),
            array('%d'),
            array('%d')
        );
    }

    wp_send_json_success(array(
        'message' => '',
        'fields'  => vespa_szabadidos_fields_payload($contest_id),
    ));
}
```

- [ ] **Step 5: Szintaxis-ellenőrzés**

Run: `php -l includes/Ajax/szabadidos.fields.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add includes/Ajax/szabadidos.fields.php
git commit -m "feat(szabadidos): admin AJAX végpontok a nevezési mezőkhöz"
```

---

### Task 5: Admin mezőszerkesztő felület

**Files:**
- Modify: `templates/szabadidos_admin.php` — a versenyválasztó felmozgatása és az új „Nevezési mezők" blokk

**Interfaces:**
- Consumes: Task 1 `vespa_szabadidos_field_types()`; Task 3 `vespa_szabadidos_get_fields()`; Task 4 mind a négy AJAX végpont
- Produces: a `#vespa-mezok` konténer, `data-contest`, `data-types`, `data-fields` attribútumokkal

- [ ] **Step 1: A versenyválasztó felmozgatása**

A `templates/szabadidos_admin.php`-ban a `<h2>Külső nevezők</h2>` és az azt követő `<form method="get">` blokk (a mai `:91-128` sorok) kerül feljebb, önálló szakaszként, és a legördülő ezentúl **az összes** type-4 versenyt hozza — a mezőket a megnyitás előtt kell tudni beállítani.

Cseréld a mai blokkot:

```php
    <h2>Külső nevezők</h2>
    <?php
    $valasztott = isset($_GET['contest_id']) ? intval($_GET['contest_id']) : 0;
    $nyitott_versenyek = $wpdb->get_results($wpdb->prepare(
        "SELECT vc.contest_id, vc.contest_name, vc.start_at, vc.end_at
         FROM vespa_szabadidos_open_contests AS o
         INNER JOIN vespa_contests AS vc ON vc.contest_id=o.contest_id
         WHERE vc.contest_type=%d
         ORDER BY vc.start_at DESC",
        4
    ));
    ?>
    <form method="get">
        <input type="hidden" name="page" value="szabadidos_kulso">
        <label>Verseny:
            <select name="contest_id" onchange="this.form.submit()">
                <option value="0">— válassz —</option>
                <?php foreach ($nyitott_versenyek as $nv) : ?>
                    <?php
                    // Az azonos nevű versenyek csak a dátumukban különböznek, ezért
                    // a név mellé kiírjuk azt is — enélkül nem megkülönböztethetők.
                    $nv_nincs_vege = $ures_datum($nv->end_at);
                    $nv_lejart     = !$nv_nincs_vege && $nv->end_at < $most;

                    $cimke = $nv->contest_name . ' — ' . $datum($nv->start_at);
                    if ($nv_nincs_vege) {
                        $cimke .= ' (nincs végdátuma, a résztvevők nem látják)';
                    } elseif ($nv_lejart) {
                        $cimke .= ' (lejárt, a résztvevők nem látják)';
                    }
                    ?>
                    <option value="<?php echo esc_attr($nv->contest_id); ?>" <?php selected($valasztott, intval($nv->contest_id)); ?>>
                        <?php echo esc_html($cimke); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
```

erre:

```php
    <h2>Verseny kiválasztása</h2>
    <?php
    $valasztott = isset($_GET['contest_id']) ? intval($_GET['contest_id']) : 0;

    // Az ÖSSZES type-4 verseny (nem csak a megnyitottak): a nevezési mezőket
    // a megnyitás előtt kell tudni beállítani.
    $valaszthato_versenyek = $wpdb->get_results($wpdb->prepare(
        "SELECT vc.contest_id, vc.contest_name, vc.start_at, vc.end_at,
                (SELECT COUNT(*) FROM vespa_szabadidos_open_contests o WHERE o.contest_id=vc.contest_id) AS nyitva
         FROM vespa_contests AS vc
         WHERE vc.contest_type=%d
         ORDER BY vc.start_at DESC",
        4
    ));
    ?>
    <form method="get">
        <input type="hidden" name="page" value="szabadidos_kulso">
        <label>Verseny:
            <select name="contest_id" onchange="this.form.submit()">
                <option value="0">— válassz —</option>
                <?php foreach ($valaszthato_versenyek as $nv) : ?>
                    <?php
                    // Az azonos nevű versenyek csak a dátumukban különböznek, ezért
                    // a név mellé kiírjuk azt is — enélkül nem megkülönböztethetők.
                    $nv_nincs_vege = $ures_datum($nv->end_at);
                    $nv_lejart     = !$nv_nincs_vege && $nv->end_at < $most;

                    $cimke = $nv->contest_name . ' — ' . $datum($nv->start_at);
                    if (intval($nv->nyitva) === 0) {
                        $cimke .= ' (nincs megnyitva)';
                    } elseif ($nv_nincs_vege) {
                        $cimke .= ' (nincs végdátuma, a résztvevők nem látják)';
                    } elseif ($nv_lejart) {
                        $cimke .= ' (lejárt, a résztvevők nem látják)';
                    }
                    ?>
                    <option value="<?php echo esc_attr($nv->contest_id); ?>" <?php selected($valasztott, intval($nv->contest_id)); ?>>
                        <?php echo esc_html($cimke); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
```

- [ ] **Step 2: A mezőszerkesztő blokk beillesztése**

A most átalakított versenyválasztó `</form>`-ja **után**, a nevezőlista `<?php if ($valasztott > 0) : ?>` blokkja **elé** illeszd be:

```php
    <?php if ($valasztott > 0) : ?>
        <?php
        $mezo_lista = array();
        foreach (vespa_szabadidos_get_fields($valasztott, false) as $mezo) {
            $mezo_lista[] = array(
                'field_id'      => intval($mezo->field_id),
                'label'         => $mezo->label,
                'field_type'    => $mezo->field_type,
                'field_options' => $mezo->field_options === null ? '' : $mezo->field_options,
                'is_required'   => intval($mezo->is_required),
                'ordernum'      => intval($mezo->ordernum),
                'is_active'     => intval($mezo->is_active),
                'answer_count'  => vespa_szabadidos_field_answer_count($mezo->field_id),
            );
        }
        ?>
        <h2>Nevezési mezők</h2>
        <p class="description">
            Ezeket a mezőket a külső résztvevő a nevezéskor tölti ki, versenyenként
            egyszer. Minden sor külön mentődik. A törölt mező archiválódik, ha már
            érkezett rá válasz — a beérkezett adat nem vész el.
        </p>
        <div id="vespa-mezok"
             data-contest="<?php echo esc_attr($valasztott); ?>"
             data-types="<?php echo esc_attr(wp_json_encode(vespa_szabadidos_field_types())); ?>"
             data-fields="<?php echo esc_attr(wp_json_encode($mezo_lista)); ?>">
            <div class="vespa-mezo-lista"></div>
            <p>
                <button type="button" class="button button-secondary vespa-mezo-uj">+ Új mező</button>
                <span class="vespa-mezo-globalis-uzenet" style="margin-left:10px;"></span>
            </p>
        </div>
    <?php endif; ?>

    <h2>Külső nevezők</h2>
```

- [ ] **Step 3: A mezőszerkesztő JavaScript**

A fájl legvégére, a meglévő `<script>` blokk **után** illeszd be:

```html
<style>
.vespa-mezo-sor { border:1px solid #c3c4c7; background:#fff; padding:12px; margin-bottom:10px; }
.vespa-mezo-sor.archivalt { background:#f6f7f7; opacity:.75; }
.vespa-mezo-sor label { display:inline-block; margin-right:12px; }
.vespa-mezo-sor .vespa-mezo-cimke { width:100%; max-width:520px; }
.vespa-mezo-sor .vespa-mezo-opciok { width:100%; max-width:520px; }
.vespa-mezo-allapot { margin-left:10px; font-style:italic; color:#646970; }
.vespa-mezo-allapot.hiba { color:#b32d2e; font-style:normal; font-weight:600; }
.vespa-mezo-lab { margin-top:8px; }
</style>
<script>
(function () {
    var doboz = document.getElementById('vespa-mezok');
    if (!doboz) return;

    var burok = document.querySelector('.wrap[data-admin-nonce]');
    var nonce = burok.getAttribute('data-admin-nonce');
    var url = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';
    var contestId = doboz.getAttribute('data-contest');
    var tipusok = JSON.parse(doboz.getAttribute('data-types'));
    var mezok = JSON.parse(doboz.getAttribute('data-fields'));
    var lista = doboz.querySelector('.vespa-mezo-lista');
    var globalisUzenet = doboz.querySelector('.vespa-mezo-globalis-uzenet');

    function valasztosE(tipus) {
        return tipus === 'egyvalasztos' || tipus === 'tobbvalasztos';
    }

    function kuld(action, adatok) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        fd.append('contest_id', contestId);
        Object.keys(adatok).forEach(function (k) { fd.append(k, adatok[k]); });
        return fetch(url, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); });
    }

    // Egy sor felépítése. Minden szöveg value/textContent útján kerül be,
    // sehol nincs innerHTML-interpoláció.
    function sortEpit(mezo, index, aktivDb) {
        var sor = document.createElement('div');
        sor.className = 'vespa-mezo-sor' + (mezo.is_active ? '' : ' archivalt');

        var fej = document.createElement('p');

        if (mezo.is_active && mezo.field_id > 0) {
            var fel = document.createElement('button');
            fel.type = 'button';
            fel.className = 'button';
            fel.textContent = '▲';
            fel.disabled = (index === 0);
            fel.addEventListener('click', function () { mozgat(mezo.field_id, 'up'); });
            fej.appendChild(fel);

            var le = document.createElement('button');
            le.type = 'button';
            le.className = 'button';
            le.textContent = '▼';
            le.disabled = (index === aktivDb - 1);
            le.addEventListener('click', function () { mozgat(mezo.field_id, 'down'); });
            fej.appendChild(le);
            fej.appendChild(document.createTextNode(' '));
        }

        var cimke = document.createElement('input');
        cimke.type = 'text';
        cimke.className = 'vespa-mezo-cimke';
        cimke.placeholder = 'A kérdés szövege, pl. Milyen kerékpárral érkezel?';
        cimke.value = mezo.label || '';
        cimke.disabled = !mezo.is_active;
        fej.appendChild(cimke);
        sor.appendChild(fej);

        var tipusSor = document.createElement('p');

        var tipusCimke = document.createElement('label');
        tipusCimke.appendChild(document.createTextNode('Típus: '));
        var tipus = document.createElement('select');
        Object.keys(tipusok).forEach(function (kulcs) {
            var opt = document.createElement('option');
            opt.value = kulcs;
            opt.textContent = tipusok[kulcs];
            if (kulcs === mezo.field_type) opt.selected = true;
            tipus.appendChild(opt);
        });
        tipus.disabled = !mezo.is_active;
        tipusCimke.appendChild(tipus);
        tipusSor.appendChild(tipusCimke);

        var kotCimke = document.createElement('label');
        var kot = document.createElement('input');
        kot.type = 'checkbox';
        kot.checked = mezo.is_required === 1;
        kot.disabled = !mezo.is_active || mezo.field_type === 'nyilatkozat';
        kotCimke.appendChild(kot);
        kotCimke.appendChild(document.createTextNode(' Kötelező kitölteni'));
        tipusSor.appendChild(kotCimke);
        sor.appendChild(tipusSor);

        var opciokSor = document.createElement('p');
        var opciokCimke = document.createElement('label');
        opciokCimke.style.display = 'block';
        opciokCimke.appendChild(document.createTextNode('Válaszlehetőségek (soronként egy):'));
        var opciok = document.createElement('textarea');
        opciok.className = 'vespa-mezo-opciok';
        opciok.rows = 4;
        opciok.value = mezo.field_options || '';
        opciok.disabled = !mezo.is_active;
        opciokCimke.appendChild(document.createElement('br'));
        opciokCimke.appendChild(opciok);
        opciokSor.appendChild(opciokCimke);
        opciokSor.style.display = valasztosE(mezo.field_type) ? '' : 'none';
        sor.appendChild(opciokSor);

        // A nyilatkozat mindig kötelező, a válaszlehetőség pedig csak a két
        // választós típusnál értelmes — a felület ezt azonnal követi.
        tipus.addEventListener('change', function () {
            opciokSor.style.display = valasztosE(tipus.value) ? '' : 'none';
            if (tipus.value === 'nyilatkozat') {
                kot.checked = true;
                kot.disabled = true;
            } else {
                kot.disabled = false;
            }
            allapot('Nem mentett módosítás', false);
        });
        cimke.addEventListener('input', function () { allapot('Nem mentett módosítás', false); });
        opciok.addEventListener('input', function () { allapot('Nem mentett módosítás', false); });
        kot.addEventListener('change', function () { allapot('Nem mentett módosítás', false); });

        var lab = document.createElement('p');
        lab.className = 'vespa-mezo-lab';

        var allapotJel = document.createElement('span');
        allapotJel.className = 'vespa-mezo-allapot';

        function allapot(szoveg, hibaE) {
            allapotJel.textContent = szoveg;
            allapotJel.className = 'vespa-mezo-allapot' + (hibaE ? ' hiba' : '');
        }

        if (mezo.is_active) {
            var ment = document.createElement('button');
            ment.type = 'button';
            ment.className = 'button button-primary';
            ment.textContent = 'Mentés';
            ment.addEventListener('click', function () {
                allapot('Mentés…', false);
                kuld('vespa_szabadidos_field_save', {
                    field_id: mezo.field_id,
                    label: cimke.value,
                    field_type: tipus.value,
                    field_options: opciok.value,
                    is_required: kot.checked ? '1' : '0'
                }).then(function (resp) {
                    if (!resp.success) { allapot(resp.data.message, true); return; }
                    mezok = resp.data.fields;
                    rajzol();
                    uzen(resp.data.message);
                }).catch(function () { allapot('Hálózati hiba.', true); });
            });
            lab.appendChild(ment);

            var torol = document.createElement('button');
            torol.type = 'button';
            torol.className = 'button';
            torol.style.marginLeft = '6px';
            torol.textContent = 'Törlés';
            torol.addEventListener('click', function () {
                if (mezo.field_id === 0) { mezok.splice(index, 1); rajzol(); return; }
                var kerdes = mezo.answer_count > 0
                    ? 'Erre a mezőre már ' + mezo.answer_count + ' válasz érkezett. A mező archiválódik, a válaszok megmaradnak. Folytatod?'
                    : 'Biztosan törlöd ezt a mezőt?';
                if (!window.confirm(kerdes)) return;
                kuld('vespa_szabadidos_field_delete', { field_id: mezo.field_id })
                    .then(function (resp) {
                        if (!resp.success) { allapot(resp.data.message, true); return; }
                        mezok = resp.data.fields;
                        rajzol();
                        uzen(resp.data.message);
                    }).catch(function () { allapot('Hálózati hiba.', true); });
            });
            lab.appendChild(torol);
        } else {
            var jel = document.createElement('span');
            jel.textContent = 'Archivált — ' + mezo.answer_count + ' válasz megmarad. ';
            lab.appendChild(jel);

            var vissza = document.createElement('button');
            vissza.type = 'button';
            vissza.className = 'button';
            vissza.textContent = 'Visszakapcsolás';
            vissza.addEventListener('click', function () {
                kuld('vespa_szabadidos_field_restore', { field_id: mezo.field_id })
                    .then(function (resp) {
                        if (!resp.success) { allapot(resp.data.message, true); return; }
                        mezok = resp.data.fields;
                        rajzol();
                        uzen(resp.data.message);
                    }).catch(function () { allapot('Hálózati hiba.', true); });
            });
            lab.appendChild(vissza);
        }

        lab.appendChild(allapotJel);
        sor.appendChild(lab);

        if (mezo.field_id === 0) {
            allapot('Még nincs mentve', false);
        }

        return sor;
    }

    function mozgat(fieldId, irany) {
        kuld('vespa_szabadidos_field_move', { field_id: fieldId, direction: irany })
            .then(function (resp) {
                if (!resp.success) { uzen(resp.data.message); return; }
                mezok = resp.data.fields;
                rajzol();
            }).catch(function () { uzen('Hálózati hiba.'); });
    }

    function uzen(szoveg) {
        globalisUzenet.textContent = szoveg || '';
    }

    function rajzol() {
        lista.textContent = '';
        var aktivDb = mezok.filter(function (m) { return m.is_active === 1; }).length;
        mezok.forEach(function (mezo, index) {
            lista.appendChild(sortEpit(mezo, index, aktivDb));
        });
        if (mezok.length === 0) {
            var ures = document.createElement('p');
            ures.textContent = 'Ehhez a versenyhez még nincs nevezési mező. A résztvevő ilyenkor a mai módon, egy kattintással nevez.';
            lista.appendChild(ures);
        }
    }

    doboz.querySelector('.vespa-mezo-uj').addEventListener('click', function () {
        // Az új sor a listába kerül, de csak a Mentés gomb rögzíti — a többi
        // sor félkész állapota így nem vész el.
        mezok.push({
            field_id: 0,
            label: '',
            field_type: 'egyvalasztos',
            field_options: '',
            is_required: 0,
            ordernum: 0,
            is_active: 1,
            answer_count: 0
        });
        rajzol();
        uzen('');
    });

    rajzol();
})();
</script>
```

- [ ] **Step 4: Szintaxis-ellenőrzés**

Run: `php -l templates/szabadidos_admin.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add templates/szabadidos_admin.php
git commit -m "feat(szabadidos): admin mezőszerkesztő soronkénti AJAX mentéssel"
```

---

### Task 6: Front-end nevezési űrlap és a signup kiterjesztése

**Files:**
- Modify: `includes/Ajax/szabadidos.entries.php:70` környéke (a beszúrás előtti ellenőrzés és utána a válaszok mentése)
- Modify: `templates/szabadidos_frontend.php` (űrlap a verseny kártyáján + JS)

**Interfaces:**
- Consumes: Task 1 `vespa_szabadidos_parse_options()`, `vespa_szabadidos_validate_answer()`; Task 3 `vespa_szabadidos_get_fields()`, `vespa_szabadidos_has_answers()`, `vespa_szabadidos_save_answers()`
- Produces: a `vespa_szabadidos_signup` végpont ezentúl elfogadja a `mezok[<field_id>]` (skalár) és `mezok[<field_id>][]` (többválasztós) POST-adatokat, hiba esetén `wp_send_json_error(array('message' => string, 'field_errors' => array(field_id => string)))` alakban válaszol

- [ ] **Step 1: A signup végpont ellenőrző szakasza**

Az `includes/Ajax/szabadidos.entries.php`-ban, a létszámkeret-ellenőrzés (a `// Globális létszámkeret (type-4)` blokk) **után**, a `$beszurva = $wpdb->insert(...)` **elé** illeszd be:

```php
    // A nevezési mezők ellenőrzése. Versenyenként egyszer kérdezünk: aki már
    // válaszolt erre a versenyre, annak a második nevezésekor nincs mit
    // kitölteni.
    $mentendo_valaszok = array();
    $mezok = vespa_szabadidos_get_fields($contest_id);
    if (!empty($mezok) && !vespa_szabadidos_has_answers($resztvevo->participant_id, $contest_id)) {
        $bejovo = (isset($_POST['mezok']) && is_array($_POST['mezok']))
            ? wp_unslash($_POST['mezok'])
            : array();

        $mezo_hibak = array();
        foreach ($mezok as $mezo) {
            $field_id = intval($mezo->field_id);
            $nyers = isset($bejovo[$field_id]) ? $bejovo[$field_id] : '';

            if (is_array($nyers)) {
                $nyers = array_map('sanitize_text_field', $nyers);
            } elseif ($mezo->field_type === 'hosszu_szoveg') {
                $nyers = sanitize_textarea_field($nyers);
            } else {
                $nyers = sanitize_text_field($nyers);
            }

            $eredmeny = vespa_szabadidos_validate_answer(
                $mezo->field_type,
                intval($mezo->is_required),
                vespa_szabadidos_parse_options($mezo->field_options),
                $nyers
            );

            if (!$eredmeny['ok']) {
                $mezo_hibak[$field_id] = $eredmeny['error'];
                continue;
            }
            $mentendo_valaszok[$field_id] = $eredmeny['value'];
        }

        if (!empty($mezo_hibak)) {
            wp_send_json_error(array(
                'message'      => 'Hiányzó vagy hibás adat a nevezési űrlapon.',
                'field_errors' => $mezo_hibak,
            ));
        }
    }
```

- [ ] **Step 2: A válaszok mentése a nevezés után**

Ugyanebben a fájlban cseréld ki ezt a záró szakaszt:

```php
    if ($beszurva === false) {
        // Az egyedi index elkaphat egy versenyfutási dupla nevezést.
        wp_send_json_error(array('message' => 'A nevezés nem sikerült. Lehet, hogy erre a versenyszámra már neveztél.'));
    }

    wp_send_json_success(array('message' => 'Sikeres nevezés.'));
```

erre:

```php
    if ($beszurva === false) {
        // Az egyedi index elkaphat egy versenyfutási dupla nevezést.
        wp_send_json_error(array('message' => 'A nevezés nem sikerült. Lehet, hogy erre a versenyszámra már neveztél.'));
    }

    // A válaszok csak sikeres nevezés után kerülnek be — így nem marad
    // válaszhalmaz nevezés nélkül.
    if (!empty($mentendo_valaszok)) {
        vespa_szabadidos_save_answers($resztvevo->participant_id, $contest_id, $mentendo_valaszok);
    }

    wp_send_json_success(array('message' => 'Sikeres nevezés.'));
```

- [ ] **Step 3: Az űrlap kirajzolása a verseny kártyáján**

A `templates/szabadidos_frontend.php`-ban a versenyeket felsoroló `foreach` belsejében, a `<h4>` után és az `$esemenyek` lekérdezés előtt szúrd be a mezők betöltését. Cseréld ezt:

```php
                <div class="vespa-szabadidos-verseny tw-rounded-lg tw-border tw-border-slate-200 tw-p-4">
                    <h4 class="tw-mb-3 tw-font-semibold tw-text-slate-900"><?php echo esc_html($v->contest_name); ?></h4>
                    <?php
                    $esemenyek = $wpdb->get_results($wpdb->prepare(
```

erre:

```php
                <?php
                $verseny_mezok = vespa_szabadidos_get_fields($v->contest_id);
                $mar_valaszolt = $resztvevo
                    ? vespa_szabadidos_has_answers($resztvevo->participant_id, $v->contest_id)
                    : false;
                $urlap_kell = !empty($verseny_mezok) && !$mar_valaszolt;
                ?>
                <div class="vespa-szabadidos-verseny tw-rounded-lg tw-border tw-border-slate-200 tw-p-4"
                     data-contest="<?php echo esc_attr($v->contest_id); ?>">
                    <h4 class="tw-mb-3 tw-font-semibold tw-text-slate-900"><?php echo esc_html($v->contest_name); ?></h4>
                    <?php
                    $esemenyek = $wpdb->get_results($wpdb->prepare(
```

- [ ] **Step 4: A „Nevezek" gomb megjelölése és az űrlap beszúrása**

Ugyanebben a fájlban cseréld a „Nevezek" gombot:

```php
                                    <button type="button" class="vespa-szabadidos-nevez <?php echo esc_attr($k_gomb_alt); ?>"
                                            data-contest="<?php echo esc_attr($v->contest_id); ?>"
                                            data-event="<?php echo esc_attr($e->contest_event_id); ?>">Nevezek</button>
```

erre:

```php
                                    <button type="button" class="vespa-szabadidos-nevez <?php echo esc_attr($k_gomb_alt); ?>"
                                            data-contest="<?php echo esc_attr($v->contest_id); ?>"
                                            data-event="<?php echo esc_attr($e->contest_event_id); ?>"
                                            data-urlap="<?php echo $urlap_kell ? '1' : '0'; ?>">Nevezek</button>
```

Majd a versenykártya lezáró `</div>`-je **elé** (a `<?php endif; ?>` után, ami az `$esemenyek` üres ágát zárja) illeszd be az űrlapot:

```php
                    <?php if ($urlap_kell) : ?>
                        <form class="vespa-szabadidos-urlap tw-mt-4 tw-hidden tw-rounded-lg tw-border tw-border-sky-200 tw-bg-sky-50 tw-p-4">
                            <p class="tw-mb-3 tw-text-sm tw-font-medium tw-text-slate-800">
                                A nevezéshez töltsd ki az alábbiakat. Ezeket versenyenként csak egyszer kérjük.
                            </p>
                            <?php foreach ($verseny_mezok as $mezo) : ?>
                                <?php $opciok = vespa_szabadidos_parse_options($mezo->field_options); ?>
                                <div class="vespa-szabadidos-mezo tw-mb-4" data-field="<?php echo esc_attr($mezo->field_id); ?>">
                                    <?php if ($mezo->field_type !== 'nyilatkozat') : ?>
                                        <span class="<?php echo esc_attr($k_cimke); ?>">
                                            <?php echo esc_html($mezo->label); ?><?php echo intval($mezo->is_required) === 1 ? ' *' : ''; ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($mezo->field_type === 'egyvalasztos') : ?>
                                        <?php foreach ($opciok as $opcio) : ?>
                                            <label class="tw-mb-1 tw-flex tw-items-center tw-gap-2 tw-text-sm tw-text-slate-700">
                                                <input type="radio" name="mezok[<?php echo esc_attr($mezo->field_id); ?>]"
                                                       value="<?php echo esc_attr($opcio); ?>"
                                                       class="tw-h-4 tw-w-4 tw-border-slate-300 tw-text-sky-600">
                                                <span><?php echo esc_html($opcio); ?></span>
                                            </label>
                                        <?php endforeach; ?>

                                    <?php elseif ($mezo->field_type === 'tobbvalasztos') : ?>
                                        <?php foreach ($opciok as $opcio) : ?>
                                            <label class="tw-mb-1 tw-flex tw-items-center tw-gap-2 tw-text-sm tw-text-slate-700">
                                                <input type="checkbox" name="mezok[<?php echo esc_attr($mezo->field_id); ?>][]"
                                                       value="<?php echo esc_attr($opcio); ?>"
                                                       class="tw-h-4 tw-w-4 tw-rounded tw-border-slate-300 tw-text-sky-600">
                                                <span><?php echo esc_html($opcio); ?></span>
                                            </label>
                                        <?php endforeach; ?>

                                    <?php elseif ($mezo->field_type === 'nyilatkozat') : ?>
                                        <label class="tw-flex tw-items-start tw-gap-2 tw-text-sm tw-text-slate-700">
                                            <input type="checkbox" name="mezok[<?php echo esc_attr($mezo->field_id); ?>]" value="1"
                                                   class="tw-mt-0.5 tw-h-4 tw-w-4 tw-rounded tw-border-slate-300 tw-text-sky-600">
                                            <span><?php echo esc_html($mezo->label); ?> *</span>
                                        </label>

                                    <?php elseif ($mezo->field_type === 'hosszu_szoveg') : ?>
                                        <textarea name="mezok[<?php echo esc_attr($mezo->field_id); ?>]" rows="3"
                                                  class="<?php echo esc_attr($k_input); ?>"></textarea>

                                    <?php elseif ($mezo->field_type === 'szam') : ?>
                                        <input type="number" name="mezok[<?php echo esc_attr($mezo->field_id); ?>]"
                                               class="<?php echo esc_attr($k_input); ?>">

                                    <?php elseif ($mezo->field_type === 'datum') : ?>
                                        <input type="date" name="mezok[<?php echo esc_attr($mezo->field_id); ?>]"
                                               class="<?php echo esc_attr($k_input); ?>">

                                    <?php else : ?>
                                        <input type="text" name="mezok[<?php echo esc_attr($mezo->field_id); ?>]"
                                               class="<?php echo esc_attr($k_input); ?>">
                                    <?php endif; ?>

                                    <p class="vespa-szabadidos-mezo-hiba tw-mt-1 tw-text-sm tw-font-medium tw-text-red-700"></p>
                                </div>
                            <?php endforeach; ?>

                            <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-3">
                                <button type="submit" class="<?php echo esc_attr($k_gomb); ?>">Nevezés véglegesítése</button>
                                <button type="button" class="vespa-szabadidos-megse <?php echo esc_attr($k_gomb_alt); ?>">Mégsem</button>
                            </div>
                            <p class="vespa-szabadidos-urlap-uzenet <?php echo esc_attr($k_uzenet); ?>"></p>
                        </form>
                    <?php endif; ?>
```

- [ ] **Step 5: A front-end JavaScript kiterjesztése**

Ugyanebben a fájlban cseréld ki a `kuld` függvényt, hogy FormData-t is elfogadjon:

```js
    function kuld(action, adatok, kesz) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        Object.keys(adatok).forEach(function (k) { fd.append(k, adatok[k]); });
        fetch(url, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(kesz)
            .catch(function () { kesz({ success: false, data: { message: 'Hálózati hiba.' } }); });
    }
```

erre:

```js
    // Az adatok lehet sima objektum vagy kész FormData (a nevezési űrlaphoz,
    // ahol a többválasztós mezők tömbként érkeznek).
    function kuld(action, adatok, kesz) {
        var fd;
        if (adatok instanceof FormData) {
            fd = adatok;
        } else {
            fd = new FormData();
            Object.keys(adatok).forEach(function (k) { fd.append(k, adatok[k]); });
        }
        fd.append('action', action);
        fd.append('nonce', nonce);
        fetch(url, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(kesz)
            .catch(function () { kesz({ success: false, data: { message: 'Hálózati hiba.' } }); });
    }
```

Majd cseréld ki a „Nevezek" gomb kezelőjét:

```js
    document.querySelectorAll('.vespa-szabadidos-nevez').forEach(function (b) {
        b.addEventListener('click', function () {
            kuld('vespa_szabadidos_signup', { contest_id: b.getAttribute('data-contest'), contest_event_id: b.getAttribute('data-event') }, function (resp) {
                alert(resp.data.message);
                if (resp.success) location.reload();
            });
        });
    });
```

erre:

```js
    function hibakTorlese(urlap) {
        urlap.querySelectorAll('.vespa-szabadidos-mezo-hiba').forEach(function (p) { p.textContent = ''; });
        urlap.querySelector('.vespa-szabadidos-urlap-uzenet').textContent = '';
    }

    document.querySelectorAll('.vespa-szabadidos-nevez').forEach(function (b) {
        b.addEventListener('click', function () {
            var kartya = b.closest('.vespa-szabadidos-verseny');
            var urlap = kartya ? kartya.querySelector('.vespa-szabadidos-urlap') : null;

            // Ha nincs kitöltendő mező (vagy a résztvevő már válaszolt erre a
            // versenyre), a gomb a mai módon, egy kattintással nevez.
            if (b.getAttribute('data-urlap') !== '1' || !urlap) {
                kuld('vespa_szabadidos_signup', {
                    contest_id: b.getAttribute('data-contest'),
                    contest_event_id: b.getAttribute('data-event')
                }, function (resp) {
                    alert(resp.data.message);
                    if (resp.success) location.reload();
                });
                return;
            }

            hibakTorlese(urlap);
            urlap.setAttribute('data-event', b.getAttribute('data-event'));
            urlap.classList.remove('tw-hidden');
            urlap.scrollIntoView({ block: 'nearest' });
        });
    });

    document.querySelectorAll('.vespa-szabadidos-megse').forEach(function (b) {
        b.addEventListener('click', function () {
            var urlap = b.closest('.vespa-szabadidos-urlap');
            hibakTorlese(urlap);
            urlap.classList.add('tw-hidden');
        });
    });

    document.querySelectorAll('.vespa-szabadidos-urlap').forEach(function (urlap) {
        urlap.addEventListener('submit', function (ev) {
            ev.preventDefault();
            hibakTorlese(urlap);

            var kartya = urlap.closest('.vespa-szabadidos-verseny');
            var fd = new FormData(urlap);
            fd.append('contest_id', kartya.getAttribute('data-contest'));
            fd.append('contest_event_id', urlap.getAttribute('data-event'));

            kuld('vespa_szabadidos_signup', fd, function (resp) {
                if (resp.success) {
                    alert(resp.data.message);
                    location.reload();
                    return;
                }
                urlap.querySelector('.vespa-szabadidos-urlap-uzenet').textContent = resp.data.message;
                var hibak = resp.data.field_errors || {};
                Object.keys(hibak).forEach(function (fieldId) {
                    var mezo = urlap.querySelector('.vespa-szabadidos-mezo[data-field="' + fieldId + '"]');
                    if (mezo) mezo.querySelector('.vespa-szabadidos-mezo-hiba').textContent = hibak[fieldId];
                });
            });
        });
    });
```

- [ ] **Step 6: Szintaxis-ellenőrzés**

Run: `php -l includes/Ajax/szabadidos.entries.php && php -l templates/szabadidos_frontend.php`
Expected: mindkettőre `No syntax errors detected`

- [ ] **Step 7: Commit**

```bash
git add includes/Ajax/szabadidos.entries.php templates/szabadidos_frontend.php
git commit -m "feat(szabadidos): nevezési űrlap a front-enden, válaszok mentése a nevezéssel"
```

---

### Task 7: Dinamikus oszlopok az admin listában és az exportban

**Files:**
- Modify: `templates/szabadidos_admin.php` (a „Külső nevezők" táblázat)
- Modify: `includes/Export/szabadidos.export.php`

**Interfaces:**
- Consumes: Task 3 `vespa_szabadidos_entry_table()`
- Produces: semmi, amire későbbi task épül

- [ ] **Step 1: Az admin nevezőlista átírása**

A `templates/szabadidos_admin.php`-ban cseréld a nevezőlista teljes blokkját (a `<?php if ($valasztott > 0) : ?>` sortól a hozzá tartozó `<?php endif; ?>`-ig, ami a „Külső nevezők" cím után áll) erre:

```php
    <?php if ($valasztott > 0) : ?>
        <?php $tabla = vespa_szabadidos_entry_table($valasztott); ?>
        <p>
            <a class="button" href="<?php echo esc_url(add_query_arg('vespa_szabadidos_export', $valasztott, home_url('/'))); ?>">XLSX export</a>
        </p>
        <?php if (empty($tabla['rows'])) : ?>
            <p>Erre a versenyre még nincs külső nevező.</p>
        <?php else : ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Név</th>
                        <th>Szül. dátum</th>
                        <th>Nem</th>
                        <th>E-mail</th>
                        <th>Telefon</th>
                        <th>Versenyszám</th>
                        <th>Nevezés dátuma</th>
                        <?php foreach ($tabla['columns'] as $oszlop) : ?>
                            <th>
                                <?php echo esc_html($oszlop['label']); ?>
                                <?php if ($oszlop['archived']) : ?>
                                    <br><span style="color:#8c8f94; font-weight:400;">(archivált)</span>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tabla['rows'] as $sor) : ?>
                    <?php $n = $sor['nevezo']; ?>
                    <tr>
                        <td>
                            <?php echo esc_html($n->full_name); ?>
                            <?php if ($sor['missing']) : ?>
                                <br><span style="color:#b32d2e; font-weight:600;">hiányzó adat</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($n->birth_date); ?></td>
                        <td><?php echo esc_html($n->gender); ?></td>
                        <td><?php echo esc_html($n->email); ?></td>
                        <td><?php echo esc_html($n->phone); ?></td>
                        <td><?php echo esc_html(trim(($n->sport_name ?: '') . ' ' . ($n->sport_event_name ?: ''))); ?></td>
                        <td><?php echo esc_html($n->entry_date); ?></td>
                        <?php foreach ($tabla['columns'] as $oszlop) : ?>
                            <td><?php echo esc_html($sor['answers'][$oszlop['field_id']]); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
```

- [ ] **Step 2: Az XLSX export átírása**

Az `includes/Export/szabadidos.export.php`-ban cseréld a `$nevezok = $wpdb->get_results(...)` lekérdezéstől a `$sor++; }` blokk végéig tartó részt erre:

```php
    $tabla = vespa_szabadidos_entry_table($contest_id);

    $fejlec = array('Név', 'Születési dátum', 'Nem', 'E-mail', 'Telefon', 'Versenyszám', 'Nevezés dátuma');
    foreach ($tabla['columns'] as $oszlop) {
        $fejlec[] = $oszlop['label'] . ($oszlop['archived'] ? ' (archivált)' : '');
    }
    // A hiányzó adat jelzése csak akkor kap oszlopot, ha van egyáltalán mező.
    if (!empty($tabla['columns'])) {
        $fejlec[] = 'Hiányzó adat';
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray($fejlec, null, 'A1');

    $sor = 2;
    foreach ($tabla['rows'] as $adat) {
        $n = $adat['nevezo'];
        $ertekek = array(
            $n->full_name,
            $n->birth_date,
            $n->gender,
            $n->email,
            $n->phone,
            trim(($n->sport_name ?: '') . ' ' . ($n->sport_event_name ?: '')),
            $n->entry_date,
        );
        foreach ($tabla['columns'] as $oszlop) {
            $ertekek[] = $adat['answers'][$oszlop['field_id']];
        }
        if (!empty($tabla['columns'])) {
            $ertekek[] = $adat['missing'] ? 'igen' : '';
        }

        $sheet->fromArray($ertekek, null, 'A' . $sor);
        $sor++;
    }
```

Ezzel a `global $wpdb;` sor feleslegessé válik az `includes/Export/szabadidos.export.php`-ban — töröld.

- [ ] **Step 3: Szintaxis-ellenőrzés és teljes tesztfuttatás**

Run: `php -l templates/szabadidos_admin.php && php -l includes/Export/szabadidos.export.php && php tests/test-szabadidos-fields.php && php tests/test-frontend-access.php`
Expected: mindkét `php -l` `No syntax errors detected`, mindkét teszt `Minden teszt sikeres.`

- [ ] **Step 4: Commit**

```bash
git add templates/szabadidos_admin.php includes/Export/szabadidos.export.php
git commit -m "feat(szabadidos): nevezési mezők válaszai az admin listában és az exportban"
```

- [ ] **Step 5: Csomag építése**

Run: `./build.sh`
Expected: hibamentes lefutás, a `build/` alatt friss plugin-csomag

- [ ] **Step 6: Telepítés utáni kézi ellenőrzés**

Ez a lista a teljes funkció végigpróbálása éles/teszt WordPress-en. Minden pont után az elvárt eredmény.

1. **Séma.** Telepítés után nyisd meg bármelyik wp-admin oldalt (hogy az `init` lefusson), majd nézd meg az adatbázisban: létezik a `vespa_szabadidos_fields` és a `vespa_szabadidos_answers` tábla, a `vespa_szabadidos_db_version` option értéke `3`.
2. **Mező felvétele.** Szabadidős külső nevezés → válassz egy type-4 versenyt → *+ Új mező* → címke „Milyen kerékpárral érkezel?", típus Egyválasztós, kötelező bepipálva, lehetőségek: `Hagyományos kerékpár`, `Elektromos kerékpár (e-bike)`, `Tandem kerékpár`, `Handbike`, `Egyéb` → Mentés. → *Az új mező mentve.* üzenet, a sor megmarad.
3. **Oldalfrissítés.** Töltsd újra az oldalt. → A mező ugyanazokkal az értékekkel jelenik meg.
4. **Validálás.** Vegyél fel egy új mezőt üres címkével → Mentés. → *A címke nem lehet üres.* a sor mellett, a többi sor érintetlen.
5. **Nyilatkozat.** Új mező, típus Nyilatkozat. → A „Kötelező kitölteni" pipa magától bekapcsol és letiltódik; a válaszlehetőség-doboz eltűnik.
6. **Sorrend.** Vegyél fel legalább három mezőt, majd nyomj ▲/▼-t. → A sorrend azonnal változik, újratöltés után is megmarad; az első soron a ▲, az utolsón a ▼ inaktív.
7. **Front-end nevezés.** Nyisd meg a versenyt külső regisztrációra, lépj be résztvevőként, nyomj *Nevezek*-et. → Az űrlap kinyílik a versenykártyán belül, a mezőkkel.
8. **Kötelező mező kihagyása.** Küldd el üresen. → Nevezés nem jön létre, a hiányzó mező alatt piros hibaüzenet.
9. **Sikeres nevezés.** Töltsd ki, küldd el. → *Sikeres nevezés.*, az oldal újratölt, a versenyszámnál *nevezve* jelzés.
10. **Második versenyszám.** Nevezz ugyanennek a versenynek egy másik versenyszámára. → Kérdés nélkül lemegy, űrlap nem nyílik.
11. **Admin lista.** Szabadidős külső nevezés → ugyanaz a verseny. → A nevezőlistában megjelenik a mező oszlopa a beírt válasszal, minden nevezési sorban.
12. **Export.** Kattints az *XLSX export*-ra. → A letöltött fájlban a hét alaposzlop után a mező oszlopa, majd a *Hiányzó adat* oszlop.
13. **Puha törlés.** Töröld a kitöltött mezőt. → Megerősítést kér a válaszszámmal, utána a sor *Archivált — 1 válasz megmarad* állapotba kerül; a front-end űrlapról eltűnik, az admin listában és az exportban `(archivált)` fejléccel megmarad.
14. **Visszakapcsolás.** Nyomj *Visszakapcsolás*-t. → A mező újra szerkeszthető és megjelenik a front-enden.
15. **Új mező meglévő nevezés után.** Vegyél fel egy új kötelező mezőt. → A korábbi nevezőnél a listában a cella üres, a neve alatt piros *hiányzó adat*, az exportban a *Hiányzó adat* oszlopban `igen`.
16. **Mező nélküli verseny.** Egy olyan type-4 versenynél, aminek nincs mezője, nyomj *Nevezek*-et. → A mai viselkedés: azonnal nevez, űrlap nélkül.

---

## Self-Review

**Spec-lefedettség.** Végigmentem a spec szakaszain:

| Spec-követelmény | Task |
|---|---|
| Két új tábla, `dbDelta`, verzió `2` → `3` | 2 |
| Hét mezőtípus | 1 |
| `field_options` soronként, `answer_value` JSON többválasztósnál | 1 |
| Nyilatkozat mindig kötelező | 1 (validálás), 5 (felület) |
| Puha törlés `is_active`-vel, visszakapcsolás | 4 (végpont), 5 (felület) |
| Oldal új tagolása, versenyválasztó minden type-4 versenyre | 5 |
| Soronkénti AJAX mentés, „+ Új mező" újratöltés nélkül, ▲▼ | 5 |
| Négy AJAX végpont, nonce + jogosultság + type-4 + IDOR | 4 |
| Mentéskori ellenőrzés (címke, típus, lehetőségek, nyilatkozat) | 1, 4 |
| Front-end űrlap a kártyán belül, egyetlen kérés | 6 |
| Szerver oldali válaszellenőrzés, mezőnkénti hibaüzenet | 1, 6 |
| Versenyenként egyszer kérdezünk | 3 (`has_answers`), 6 |
| Dinamikus oszlopok, archivált mezők, `hiányzó adat` | 3, 7 |
| Közös nevező-lekérdezés helperbe emelve | 3, 7 |
| Peremeset: visszavonás után a válaszok megmaradnak | 3 (a `withdraw` érintetlen), 7/16. kézi pont |
| Peremeset: mező nélküli verseny változatlan | 6, 7/16. kézi pont |
| Unit tesztek a tiszta logikára | 1 |

Nincs lefedetlen követelmény.

**Placeholder-ellenőrzés.** Nincs „TBD", „hasonlóan a Task N-hez", vagy kód nélküli kódlépés; minden lépés a beillesztendő tartalommal együtt szerepel.

**Típus-egyezés.** A Task 1 `vespa_szabadidos_validate_field()` a `field` kulcs alatt `label` / `field_type` / `field_options` / `is_required` kulcsokat ad — a Task 4 pontosan ezeket adja át a `$wpdb->update()`/`insert()` hívásnak, és a formátumtömbök (`%s, %s, %s, %d`) ehhez a sorrendhez illeszkednek. A Task 3 `vespa_szabadidos_entry_table()` `columns`/`rows` szerkezetét a Task 7 mindkét fogyasztója (admin lista, export) azonos kulcsnevekkel olvassa. A Task 6 `field_errors` kulcsa megegyezik a front-end JS által olvasott `resp.data.field_errors`-szal.

**Egy tudatos eltérés a spec szövegétől.** A Task 4 `field_move` végpontja nem két sor `ordernum`-át cseréli, hanem az egész aktív listát újraszámozza 0-tól. A spec „két szomszédos sor `ordernum` cseréje" megfogalmazása hézagos vagy azonos sorszámoknál (régi adat, párhuzamos szerkesztés) rossz sorrendet adna; az újraszámozás mindig konzisztens állapotot hagy maga után.
