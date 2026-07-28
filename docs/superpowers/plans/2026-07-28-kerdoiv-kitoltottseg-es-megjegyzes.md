# Beszámoló kérdőív: kitöltöttség-jelzés és megjegyzés kiválasztás nélkül — implementációs terv

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A versenyek listája mutassa három állapotban a beszámoló kitöltöttségét, a beszámoló legyen utólag szerkeszthető, és a megjegyzés kiválasztás nélkül is rögzíthető legyen — közben megszüntetve a mentési út három hibáját (kérdésvesztés, duplikálódás, hiányzó jogosultság-ellenőrzés).

**Architecture:** Az állapotlogika egy tiszta, WordPress-független fájlba kerül a `Core` rétegbe, így sima `php` szkripttel unit-tesztelhető. Egy verziókapus migráció felveszi és feltölti a `question_id` oszlopot, amire a párosító mentés és az aggregáló számláló épül. A mentés a kérdéseken iterál `question_id` szerint, nem egy számlálón.

**Tech Stack:** PHP 7+, WordPress plugin API (options, admin-ajax, nonce, capability), `$wpdb` közvetlen SQL-lel, a plugin saját `vespa-ajax-form.js` rétege. Nincs composer/phpunit — a unit tesztek sima `php` szkripttel futnak.

## Global Constraints

- Az `includes/{Core,Datalist,Admin,Ajax,Export,Api}/*.php` fájlokat a `vitarex-vespa-plugin.php:51-63` **automatikusan** betölti `glob`-bal, ebben a könyvtár-sorrendben (`Core` az `Datalist` előtt). Új fájlt sehol nem kell kézzel `require`-elni.
- Az `includes/Core/vespa.kerdoiv.php` **csak függvényeket definiálhat** — se top-level hook, se `defined('ABSPATH') || exit;` guard, se WP-függvényhívás betöltéskor. Ez teszi lehetővé, hogy a teszt sima PHP-vel betöltse.
- Az adatbázistáblák neve `$wpdb->prefix` **NÉLKÜL** értendő (a plugin minden `vespa_*` táblája így hivatkozott).
- A migráció **nem** `dbDelta`: a `vespa_questions_answered` táblát nem ez a plugin hozza létre. Oszlop-létezés ellenőrzés (`information_schema`) + sima `ALTER TABLE`, a `vespa_kerdoiv_db_version` option kapuja mögött.
- A verziókaput **csak akkor** zárjuk le, ha az oszlop tényleg létrejött — különben egy elbukott migráció után soha többé nem futna újra.
- Az űrlapmezők nevében a kérdés **`ordernum`**-a áll (`answer7`, `qnote7`), és az `ordernum` értékek **nem folytonosak** (`0, 1, 7, 8 … 26, 28`). Sehol nem szabad `0..count-1` számlálóval olvasni őket.
- Egy kérdés akkor számít megválaszoltnak, ha **van válasza vagy megjegyzése**.
- Jogosultság a beszámoló mentéséhez és a szerkesztő oldal betöltéséhez: `VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles`. Nonce: `vespa_nonce`, `nonce` néven a POST-ban.
- Az AJAX űrlap szerződése (`js/vespa-ajax-form.js`): a form `class="ajax-form"`, rejtett `action` mezővel; a JS `new FormData(form)`-ot küld, tehát **a nonce-t rejtett mezőként kell kirenderelni**. Siker = `wp_send_json_success(array('modal' => ..., 'modalId' => 'succesModal'))`; mezőhiba = `wp_send_json_error(array('errors' => array('mezonev' => 'üzenet')))`.
- Hibamező-név nem tartalmazhat szögletes zárójelet: a `vespa-ajax-form.js` idézőjel nélküli `[name=...]` szelektort épít.
- Magyar nyelvű felhasználói szövegek és kódkommentek, a kódbázis stílusában.
- Minden kimenet escape-elve: `esc_html`, `esc_attr`.
- Unit teszt futtatása: `php tests/test-kerdoiv.php` — 0-s kilépőkód a siker.

---

## File Structure

| Fájl | Felelősség |
|---|---|
| `includes/Core/vespa.kerdoiv.php` | *új* — tiszta állapotlogika: megválaszoltság, állapot, cellaadat |
| `tests/test-kerdoiv.php` | *új* — a tiszta logika unit tesztjei |
| `includes/Core/vespa.kerdoiv.install.php` | *új* — `question_id` oszlop, egyszeri feltöltés, verziókapu |
| `includes/Core/functions.php` | *módosul* — aggregáló számláló és kérdésszám helper |
| `includes/Datalist/datalist.questions_answered.php` | *módosul* — nonce + jogosultság, párosító mentés, holt kód törlése |
| `includes/Admin/menu.masterdata.php` | *módosul* — a `question` ág jogosultság-ellenőrzése |
| `templates/questions_answered_editor.php` | *módosul* — nonce mező, előre kitöltés, egyopciós kérdés, történelmi blokk |
| `templates/contest_view.php` | *módosul* — a gomb mindig látszik, állapotfüggő felirattal |
| `templates/contest_list.php` | *módosul* — „Beszámoló" oszlop mind a négy táblázatban |

**Task-sorrend és függőségek:** 1 → 2 → 3 → 4 → 5 → 6. A 4. (mentés) a 2-re épül (`question_id` létezik), az 5. a 4-re (mit vár a mentés), a 6. a 3-ra (aggregáló helper) és az 1-re (címke).

---

### Task 1: Tiszta állapotlogika + unit tesztek

**Files:**
- Create: `includes/Core/vespa.kerdoiv.php`
- Test: `tests/test-kerdoiv.php`

**Interfaces:**
- Consumes: semmi (ez az első task)
- Produces:
  - `vespa_kerdoiv_kerdes_megvalaszolt($answer, $qnote)` → `bool`
  - `vespa_kerdoiv_allapot($megvalaszolt, $osszes)` → `'nincs' | 'reszleges' | 'kesz'`
  - `vespa_kerdoiv_cella($megvalaszolt, $osszes)` → `array('allapot' => string, 'cimke' => string, 'szin' => string)`

- [ ] **Step 1: Write the failing test**

Hozd létre a `tests/test-kerdoiv.php` fájlt:

```php
<?php
/**
 * A beszámoló kérdőív kitöltöttség-logikájának unit tesztjei.
 * Futtatás: php tests/test-kerdoiv.php
 * WordPress nem kell hozzá: a tesztelt függvények tiszták.
 */

require_once __DIR__ . '/../includes/Core/vespa.kerdoiv.php';

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

// ---- Megválaszoltság --------------------------------------------------

allit(
    vespa_kerdoiv_kerdes_megvalaszolt('nyitott sportpálya', '') === true,
    'valasz onmagaban megvalaszolt'
);
allit(
    vespa_kerdoiv_kerdes_megvalaszolt('', '12 fo') === true,
    'megjegyzes onmagaban megvalaszolt'
);
allit(
    vespa_kerdoiv_kerdes_megvalaszolt('nyitott sportpálya', '12 fo') === true,
    'mindketto megvalaszolt'
);
allit(
    vespa_kerdoiv_kerdes_megvalaszolt('', '') === false,
    'ures mindketto nem megvalaszolt'
);
allit(
    vespa_kerdoiv_kerdes_megvalaszolt('   ', "\n\t ") === false,
    'csak whitespace nem megvalaszolt'
);
allit(
    vespa_kerdoiv_kerdes_megvalaszolt(null, null) === false,
    'null nem megvalaszolt'
);
allit(
    vespa_kerdoiv_kerdes_megvalaszolt('0', '') === true,
    'a nulla szoveg ervenyes valasz'
);

// ---- Állapot ----------------------------------------------------------

allit(vespa_kerdoiv_allapot(0, 23) === 'nincs', 'nulla valasz -> nincs');
allit(vespa_kerdoiv_allapot(1, 23) === 'reszleges', 'egy valasz -> reszleges');
allit(vespa_kerdoiv_allapot(22, 23) === 'reszleges', '22/23 -> reszleges');
allit(vespa_kerdoiv_allapot(23, 23) === 'kesz', '23/23 -> kesz');
allit(vespa_kerdoiv_allapot(0, 0) === 'nincs', 'nulla kerdes -> nincs');
allit(vespa_kerdoiv_allapot(5, 0) === 'nincs', 'nulla kerdes akkor is nincs, ha van sor');
allit(
    vespa_kerdoiv_allapot(30, 23) === 'kesz',
    'a szuksegesnel tobb valasz is kesz (regi, azota szukult kerdessor)'
);
allit(vespa_kerdoiv_allapot(-2, 23) === 'nincs', 'negativ bemenet nincs-nek szamit');

// ---- Cella ------------------------------------------------------------

$c = vespa_kerdoiv_cella(0, 23);
allit($c['allapot'] === 'nincs', 'cella allapota nulla valasznal');
allit($c['cimke'] === 'Nincs kitöltve', 'cella cimkeje nulla valasznal');
allit($c['szin'] === '#b32d2e', 'a hianyzo beszamolo piros');

$c = vespa_kerdoiv_cella(17, 23);
allit($c['allapot'] === 'reszleges', 'cella allapota reszlegesnel');
allit($c['cimke'] === '17/23', 'a reszleges cimke szamlalo');
allit($c['szin'] === '#646970', 'a reszleges semleges szurke');

$c = vespa_kerdoiv_cella(23, 23);
allit($c['allapot'] === 'kesz', 'cella allapota keszen');
allit($c['cimke'] === 'Kitöltve', 'cella cimkeje keszen');
allit($c['szin'] === '#1a7f37', 'a kesz zold');

$c = vespa_kerdoiv_cella(0, 0);
allit($c['cimke'] === '—', 'kerdes nelkul gondolatjel a cimke');
allit($c['szin'] === '#646970', 'kerdes nelkul semleges szin, nem piros');

echo "\n" . ($hibak === 0 ? "Minden teszt sikeres.\n" : $hibak . " teszt elbukott.\n");
exit($hibak === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test-kerdoiv.php`
Expected: FAIL — `Failed opening required '.../includes/Core/vespa.kerdoiv.php'`

- [ ] **Step 3: Write minimal implementation**

Hozd létre az `includes/Core/vespa.kerdoiv.php` fájlt:

```php
<?php

/**
 * A beszámoló kérdőív kitöltöttségének tiszta, WordPress-független logikája.
 *
 * Ez a fájl KIZÁRÓLAG függvényeket definiál — se hook, se ABSPATH-guard, se
 * WP-hívás betöltéskor. Így a tests/test-kerdoiv.php sima PHP-vel betöltheti.
 */

/**
 * Megválaszoltnak számít-e a kérdés?
 *
 * A 23 közös kérdésből 17-nek egyetlen válaszlehetősége van, jellemzően
 * "válasz a megjegyzésben" — ezeknél a megjegyzés MAGA a válasz, ezért a
 * megjegyzés önmagában is megválaszoltnak számít.
 */
function vespa_kerdoiv_kerdes_megvalaszolt($answer, $qnote)
{
    return trim((string) $answer) !== '' || trim((string) $qnote) !== '';
}

/**
 * A beszámoló állapota a megválaszolt és az összes kérdés arányából.
 * Kérdés nélküli rendszerben nincs mit mérni.
 */
function vespa_kerdoiv_allapot($megvalaszolt, $osszes)
{
    $megvalaszolt = intval($megvalaszolt);
    $osszes = intval($osszes);

    if ($osszes <= 0 || $megvalaszolt <= 0) {
        return 'nincs';
    }
    if ($megvalaszolt >= $osszes) {
        return 'kesz';
    }
    return 'reszleges';
}

/**
 * A listaoszlop cellájának megjelenítési adatai.
 *
 * A részleges állapot szándékosan semleges: a közös kérdéskészlet bővült,
 * ezért a régi beszámolók szinte mind részlegesek — figyelmet csak a
 * teljesen hiányzó beszámoló kér.
 */
function vespa_kerdoiv_cella($megvalaszolt, $osszes)
{
    $megvalaszolt = intval($megvalaszolt);
    $osszes = intval($osszes);

    if ($osszes <= 0) {
        return array('allapot' => 'nincs', 'cimke' => '—', 'szin' => '#646970');
    }

    $allapot = vespa_kerdoiv_allapot($megvalaszolt, $osszes);

    if ($allapot === 'nincs') {
        return array('allapot' => 'nincs', 'cimke' => 'Nincs kitöltve', 'szin' => '#b32d2e');
    }
    if ($allapot === 'kesz') {
        return array('allapot' => 'kesz', 'cimke' => 'Kitöltve', 'szin' => '#1a7f37');
    }

    return array(
        'allapot' => 'reszleges',
        'cimke'   => $megvalaszolt . '/' . $osszes,
        'szin'    => '#646970',
    );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test-kerdoiv.php`
Expected: PASS — minden sor `OK`, záró sor `Minden teszt sikeres.`, kilépőkód 0

- [ ] **Step 5: Commit**

```bash
git add includes/Core/vespa.kerdoiv.php tests/test-kerdoiv.php
git commit -m "feat(kerdoiv): kitöltöttség tiszta logikája és unit tesztjei"
```

---

### Task 2: `question_id` oszlop migrációja

**Files:**
- Create: `includes/Core/vespa.kerdoiv.install.php`

**Interfaces:**
- Consumes: semmi
- Produces: a `vespa_questions_answered.question_id` oszlop, feltöltve; a `vespa_kerdoiv_db_version` option értéke `'1'`

Ehhez a taskhoz nincs unit teszt: `$wpdb` és futó WordPress kell hozzá, helyi WP-környezet pedig nincs. A verifikáció szintaxis-ellenőrzés; a funkcionális próba a Task 6 végén.

- [ ] **Step 1: Write the migration**

Hozd létre az `includes/Core/vespa.kerdoiv.install.php` fájlt:

```php
<?php

/**
 * A beszámoló-válaszok question_id oszlopának egyszeri felvétele és feltöltése.
 *
 * A vespa_questions_answered táblát NEM ez a plugin hozza létre — a séma a
 * dumpban él —, ezért itt nem dbDelta-t használunk: az teljes CREATE TABLE-t
 * várna, és egy jövőbeli kézi séma-változást visszaírhatna. Helyette
 * megnézzük, létezik-e már az oszlop, és csak akkor futtatunk ALTER TABLE-t.
 *
 * A séma a dumpban él, aktivációs hook nincs — ezért, a szerepekhez és a
 * szabadidős telepítőhöz hasonlóan, init-en, verzió-kapuval fut.
 */
add_action('init', 'vespa_kerdoiv_install', 5);

function vespa_kerdoiv_install()
{
    if (get_option('vespa_kerdoiv_db_version') === '1') {
        return;
    }

    global $wpdb;

    if (!vespa_kerdoiv_oszlop_letezik()) {
        $wpdb->query(
            "ALTER TABLE vespa_questions_answered
               ADD COLUMN question_id int(11) NOT NULL DEFAULT 0,
               ADD KEY contest_question (contest_id, question_id)"
        );
    }

    // A kaput csak akkor zárjuk le, ha az oszlop tényleg létrejött. Enélkül
    // egy elbukott migráció után soha többé nem futna újra.
    if (!vespa_kerdoiv_oszlop_letezik()) {
        return;
    }

    // A meglévő sorok párosítása a közös kérdéskészlethez a kérdés SZÖVEGE
    // alapján — ez az egyetlen kapocs, ami a régi adatban létezik. Ami nem
    // talál (azóta törölt vagy átírt kérdés), az 0 marad, és a beszámoló
    // történelmi részeként megőrződik.
    $wpdb->query(
        "UPDATE vespa_questions_answered AS qa
         INNER JOIN vespa_contests_questions AS q ON q.question = qa.question
         SET qa.question_id = q.question_id
         WHERE qa.question_id = 0"
    );

    update_option('vespa_kerdoiv_db_version', '1');
}

/**
 * Létezik-e már a question_id oszlop? A migráció ez alapján idempotens akkor
 * is, ha az option valamiért elveszett.
 */
function vespa_kerdoiv_oszlop_letezik()
{
    global $wpdb;

    $db = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        'vespa_questions_answered',
        'question_id'
    ));

    return intval($db) > 0;
}
```

- [ ] **Step 2: Szintaxis-ellenőrzés és a meglévő tesztek**

Run: `php -l includes/Core/vespa.kerdoiv.install.php && php tests/test-kerdoiv.php && php tests/test-szabadidos-fields.php && php tests/test-frontend-access.php`
Expected: `No syntax errors detected`, majd mindhárom tesztfájl `Minden teszt sikeres.`

- [ ] **Step 3: Commit**

```bash
git add includes/Core/vespa.kerdoiv.install.php
git commit -m "feat(kerdoiv): question_id oszlop migrációja a beszámoló-válaszokhoz"
```

---

### Task 3: Aggregáló helperek

**Files:**
- Modify: `includes/Core/functions.php` — a `vespa_contest_has_answers()` (`:145-157`) **után**, a `// WordPress hookok` elválasztó **elé**

**Interfaces:**
- Consumes: Task 2 `question_id` oszlopa
- Produces:
  - `vespa_contest_answer_counts($contest_ids = null)` → `array(contest_id => int)`
  - `vespa_contest_question_count()` → `int`

Ehhez a taskhoz sincs unit teszt: mindkét függvény `$wpdb`-t használ.

- [ ] **Step 1: A két helper beszúrása**

Az `includes/Core/functions.php`-ban, közvetlenül a `vespa_contest_has_answers()` függvény záró `}` sora után illeszd be:

```php
/**
 * Versenyenként hány AKTUÁLIS kérdésre van érdemi válasz (válasz vagy
 * megjegyzés)? Egyetlen lekérdezés — a versenylista soronkénti hívás helyett,
 * ahol ez négy táblázatnyi versenyre N lekérdezést jelentene.
 *
 * A question_id szerinti INNER JOIN egyben kiszűri a 0-s történelmi sorokat
 * is: azok azóta törölt vagy átírt kérdéshez tartoznak, és nem részei a mai
 * mércének.
 *
 * $contest_ids = null esetén minden versenyre. Visszatérés: contest_id => int.
 */
function vespa_contest_answer_counts($contest_ids = null)
{
    global $wpdb;

    $sql = "SELECT qa.contest_id, COUNT(DISTINCT qa.question_id) AS db
            FROM vespa_questions_answered AS qa
            INNER JOIN vespa_contests_questions AS q ON q.question_id = qa.question_id
            WHERE (TRIM(qa.answer) <> '' OR TRIM(qa.qnote) <> '')";

    if (is_array($contest_ids)) {
        // Az intval miatt az interpoláció itt biztonságos; a $wpdb->prepare
        // nem tud változó hosszúságú IN() listát kezelni.
        $tisztitott = array_filter(array_map('intval', $contest_ids));
        if (empty($tisztitott)) {
            return array();
        }
        $sql .= ' AND qa.contest_id IN (' . implode(',', $tisztitott) . ')';
    }

    $sql .= ' GROUP BY qa.contest_id';

    $eredmeny = array();
    foreach ((array) $wpdb->get_results($sql) as $sor) {
        $eredmeny[intval($sor->contest_id)] = intval($sor->db);
    }

    return $eredmeny;
}

/**
 * Az aktuális közös kérdések száma — a kitöltöttség osztója.
 */
function vespa_contest_question_count()
{
    global $wpdb;

    return intval($wpdb->get_var("SELECT COUNT(*) FROM vespa_contests_questions"));
}
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Core/functions.php && php tests/test-kerdoiv.php`
Expected: `No syntax errors detected`, majd `Minden teszt sikeres.`

- [ ] **Step 3: Commit**

```bash
git add includes/Core/functions.php
git commit -m "feat(kerdoiv): aggregáló kitöltöttség-számláló helperek"
```

---

### Task 4: Mentés átírása

**Files:**
- Modify: `includes/Datalist/datalist.questions_answered.php` — a `save()` metódus (`:11-55`)
- Modify: `includes/Admin/menu.masterdata.php` — a `vespa_menu_contests()` `question` ága

**Interfaces:**
- Consumes: Task 1 `vespa_kerdoiv_kerdes_megvalaszolt()`; Task 2 `question_id` oszlopa
- Produces: a `save_questions_answered` végpont ezentúl `nonce` mezőt vár, `versenyek_kezelese_kiiras_modositas_torles` jogosultságot követel, és `question_id` szerint párosítva szúr be / frissít / töröl

- [ ] **Step 1: A `save()` metódus cseréje**

Az `includes/Datalist/datalist.questions_answered.php`-ban cseréld a teljes `save()` metódust (a `public function save(){` sortól a hozzá tartozó záró `}` sorig, a `public function checkDelete` elé) erre:

```php
        public function save(){
            global $wpdb;

            check_ajax_referer( 'vespa_nonce', 'nonce' );

            if( ! current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles ) ){
                wp_send_json_error( array('errors' => array('contest_id' => 'Nincs jogosultságod a beszámoló mentéséhez.')) );
            }

            $contest_id = isset($_POST['contest_id']) ? intval($_POST['contest_id']) : 0;
            if( $contest_id <= 0 ){
                wp_send_json_error( array('errors' => array('contest_id' => 'Hibás verseny.')) );
            }

            $kerdesek = $wpdb->get_results("SELECT * FROM vespa_contests_questions ORDER BY ordernum ASC");

            // A verseny már mentett sorai question_id szerint indexelve. A 0-s
            // sorok azóta megszűnt kérdésekhez tartoznak — azokhoz nem nyúlunk.
            $meglevo = array();
            $sorok = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM vespa_questions_answered WHERE contest_id=%d",
                $contest_id
            ));
            foreach( $sorok as $sor ){
                if( intval($sor->question_id) > 0 ){
                    $meglevo[ intval($sor->question_id) ] = $sor;
                }
            }

            // FONTOS: az űrlapmezők a kérdés ordernum-áról kapják a nevüket, és
            // az ordernum értékek NEM folytonosak (0, 1, 7, 8 ... 26, 28). Ezért
            // a kérdéseken iterálunk, nem egy 0..count-1 számlálón — az utóbbi
            // néma kérdésvesztést okozott.
            foreach( $kerdesek as $kerdes ){
                $ordernum    = intval($kerdes->ordernum);
                $question_id = intval($kerdes->question_id);

                // A be nem jelölt rádiógombot a böngésző nem küldi el.
                $answer = isset($_POST['answer' . $ordernum])
                    ? sanitize_text_field( wp_unslash($_POST['answer' . $ordernum]) )
                    : '';
                $qnote = isset($_POST['qnote' . $ordernum])
                    ? sanitize_textarea_field( wp_unslash($_POST['qnote' . $ordernum]) )
                    : '';

                $van_adat = vespa_kerdoiv_kerdes_megvalaszolt($answer, $qnote);
                $regi     = isset($meglevo[$question_id]) ? $meglevo[$question_id] : null;

                if( $regi === null ){
                    // Üres kérdéshez nem hozunk létre sort — attól lenne hazug
                    // a kitöltöttség-számláló.
                    if( ! $van_adat ){
                        continue;
                    }

                    $wpdb->insert( $this->tablename, array(
                        'contest_id'  => $contest_id,
                        'question_id' => $question_id,
                        'question'    => $kerdes->question,
                        'answer'      => $answer,
                        'qnote'       => $qnote,
                    ), array( '%d', '%d', '%s', '%s', '%s' ));
                    continue;
                }

                if( ! $van_adat ){
                    // Üresre szerkesztett sorban nincs adat, amit őrizni kellene.
                    $wpdb->delete( $this->tablename, array( 'qa_id' => intval($regi->qa_id) ), array('%d') );
                    continue;
                }

                $wpdb->update( $this->tablename, array(
                    'question' => $kerdes->question,
                    'answer'   => $answer,
                    'qnote'    => $qnote,
                ), array(
                    'qa_id' => intval($regi->qa_id),
                ),
                array( '%s', '%s', '%s' ),
                array( '%d' ));
            }

            $vars = array(
                "{=TEXT=}" => 'A beszámoló mentése sikeres volt.',
                "{=URL=}" => admin_url('admin.php?page=contests') . '&action=view&id='. $contest_id
            );

            wp_send_json_success( array('modal' => vespa_load_template_with_vars( 'success-modal.php', $vars ), 'modalId' => 'succesModal' ) );
        }
```

A `question` mezőt frissítéskor is felülírjuk: ha a kérdés szövegét azóta átfogalmazták, a beszámoló a mostani szöveget rögzíti — a `question_id` tartja a kapcsolatot, a szöveg csak pillanatkép.

- [ ] **Step 2: A szerkesztő oldal jogosultság-ellenőrzése**

Az `includes/Admin/menu.masterdata.php`-ban a `vespa_menu_contests()` függvényben cseréld ezt:

```php
        } else if ('question' == $_GET['action']) {
            vespa_load_template('questions_answered_editor.php');
        }
```

erre:

```php
        } else if ('question' == $_GET['action']) {
            // A mentés is ezt a jogosultságot követeli; enélkül a link
            // látszana, de a mentés elszállna.
            if (!current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles)) {
                echo 'Nincs megfelelő jogosultságod az oldal megtekintéséhez.';
                return;
            }
            vespa_load_template('questions_answered_editor.php');
        }
```

- [ ] **Step 3: Szintaxis-ellenőrzés**

Run: `php -l includes/Datalist/datalist.questions_answered.php && php -l includes/Admin/menu.masterdata.php && php tests/test-kerdoiv.php`
Expected: mindkét `php -l` `No syntax errors detected`, majd `Minden teszt sikeres.`

- [ ] **Step 4: Commit**

```bash
git add includes/Datalist/datalist.questions_answered.php includes/Admin/menu.masterdata.php
git commit -m "fix(kerdoiv): párosító beszámoló-mentés nonce-szal és jogosultság-ellenőrzéssel"
```

---

### Task 5: Szerkesztő űrlap

**Files:**
- Modify: `templates/questions_answered_editor.php` (a teljes fájl átírása)
- Modify: `templates/contest_view.php:98-100`

**Interfaces:**
- Consumes: Task 4 mentési szerződése (`nonce` mező, `answer<ordernum>`, `qnote<ordernum>`)
- Produces: semmi, amire későbbi task épül

- [ ] **Step 1: A szerkesztő sablon átírása**

Cseréld a `templates/questions_answered_editor.php` teljes tartalmát erre:

```php
<?php
    $site_title = 'Beszámoló kitöltése';
    $id         = isset($_GET['id']) ? intval($_GET['id']) : 0;

    global $wpdb;

    if( $id <= 0 ){
        wp_redirect( admin_url('admin.php?page=contests') );
        exit;
    }

    $questions = $wpdb->get_results("SELECT * FROM vespa_contests_questions ORDER BY ordernum ASC");

    // A verseny meglévő válaszai. A question_id > 0 sorok az aktuális
    // kérdésekhez tartoznak; a 0-sok azóta megszűnt kérdésekhez, azokat csak
    // olvashatóan mutatjuk meg a lap alján.
    $valaszok    = array();
    $tortenelmi  = array();
    $mentett     = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM vespa_questions_answered WHERE contest_id=%d ORDER BY qa_id ASC",
        $id
    ));
    foreach( $mentett as $sor ){
        if( intval($sor->question_id) > 0 ){
            $valaszok[ intval($sor->question_id) ] = $sor;
        } else {
            $tortenelmi[] = $sor;
        }
    }

    $van_mar_valasz = ! empty($valaszok) || ! empty($tortenelmi);
?>


<div class="wrap">
        <div class="row">
            <div class="col-md-12">
                <h1 class="site-title"><?php echo esc_html($site_title); ?></h1>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">

            <?php if( empty($questions) ) : ?>
                <p>Nincs felvéve egyetlen beszámoló-kérdés sem.</p>
            <?php else : ?>

            <form action="" class="ajax-form" method="POST">
                    <input type="hidden" name="action" autocomplete="off" value="save_questions_answered">
                    <input type="hidden" name="nonce" autocomplete="off" value="<?php echo esc_attr( wp_create_nonce('vespa_nonce') ); ?>">
                    <input type="hidden" name="contest_id" id="contest_id" autocomplete="off" value="<?php echo esc_attr($id); ?>">

                    <?php foreach($questions as $sorszam => $question ): ?>
                        <?php
                            $ordernum = intval($question->ordernum);
                            $mentett_sor = isset($valaszok[ intval($question->question_id) ])
                                ? $valaszok[ intval($question->question_id) ]
                                : null;

                            $mentett_answer = $mentett_sor ? $mentett_sor->answer : '';
                            $mentett_qnote  = $mentett_sor ? $mentett_sor->qnote  : '';

                            $lehetosegek = array_values( array_filter( array_map('trim', explode(';', $question->answers)), function($v){
                                return $v !== '';
                            }));

                            // Egyetlen lehetőség nem választás: a 23 kérdésből 17-nek
                            // csak egy "válasz a megjegyzésben" opciója van. Ilyenkor
                            // rádiógombot sem rajzolunk, csak a megjegyzés mezőt.
                            $van_valasztas = count($lehetosegek) > 1;
                        ?>

                <div class="row">
                    <div class="col-md-<?php echo $van_valasztas ? '6' : '12'; ?>">
                        <h3><?php echo esc_html( ($sorszam + 1) . '. ' . $question->question ); ?></h3>

                        <?php if( $van_valasztas ) : ?>
                            <?php foreach($lehetosegek as $ind => $answer): ?>
                            <div class="form-group form-checkbox">
                                <input type="radio" name="<?php echo 'answer'. $ordernum; ?>" id="<?php echo 'answer'. $ordernum . '-' . $ind; ?>" autocomplete="off" value="<?php echo esc_attr($answer); ?>" <?php checked($mentett_answer, $answer); ?>>
                                <label for="<?php echo 'answer'. $ordernum . '-' . $ind; ?>">
                                    <?php echo esc_html($answer); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-<?php echo $van_valasztas ? '6' : '12'; ?>">
                        <div class="form-group">
                            <label>Megjegyzés</label>
                            <textarea name="<?php echo 'qnote'. $ordernum; ?>" id="<?php echo 'qnote'. $ordernum; ?>" cols="30" rows="<?php echo $van_valasztas ? '10' : '4'; ?>" autocomplete="off" class="form-control"><?php echo esc_textarea($mentett_qnote); ?></textarea>
                        </div>
                    </div>
                </div>
                    <?php endforeach; ?>

                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">Mentés</button>
                        <a href="#" onclick="history.back();" class="btn btn-cancel">Mégsem</a>
                    </div>
                </form>

            <?php endif; ?>

            <?php if( ! empty($tortenelmi) ) : ?>
                <div class="row" style="margin-top:40px; opacity:.7;">
                    <div class="col-md-12">
                        <h3>Korábbi kérdések</h3>
                        <p class="description">
                            Ezek a válaszok olyan kérdésekhez tartoznak, amelyek azóta
                            kikerültek a kérdéssorból. Megmaradnak, de már nem
                            szerkeszthetők.
                        </p>
                        <table class="table table-striped">
                            <thead><tr><th>Kérdés</th><th>Válasz</th><th>Megjegyzés</th></tr></thead>
                            <tbody>
                            <?php foreach( $tortenelmi as $sor ) : ?>
                                <tr>
                                    <td><?php echo esc_html($sor->question); ?></td>
                                    <td><?php echo esc_html($sor->answer); ?></td>
                                    <td><?php echo esc_html($sor->qnote); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            </div>
        </div>

</div>
```

Két dolog, ami a mai sablonhoz képest szándékosan változik: a `data_count` rejtett mező **eltűnik** (a mentés a kérdéseken iterál, nem számlálón), és a `question<ordernum>` rejtett mezők is **eltűnnek** (a kérdés szövegét a mentés az adatbázisból veszi, nem a kliens által küldött értékből).

- [ ] **Step 2: A gomb a verseny részletei oldalon**

A `templates/contest_view.php`-ban cseréld ezt:

```php
            <?php if (!vespa_contest_has_answers($id)) : ?>
                <a href="<?php echo admin_url('admin.php?page=contests&action=question&id=') . $id; ?>" class="btn btn-default btn-sm">Beszámoló rögzítése</a>
            <?php endif; ?>
```

erre:

```php
            <?php if (current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles)) : ?>
                <a href="<?php echo admin_url('admin.php?page=contests&action=question&id=') . $id; ?>" class="btn btn-default btn-sm">
                    <?php echo vespa_contest_has_answers($id) ? 'Beszámoló szerkesztése' : 'Beszámoló rögzítése'; ?>
                </a>
            <?php endif; ?>
```

A letöltés-menü „Beszámoló" tétele (`:110`) változatlan marad.

- [ ] **Step 3: Szintaxis-ellenőrzés**

Run: `php -l templates/questions_answered_editor.php && php -l templates/contest_view.php && php tests/test-kerdoiv.php`
Expected: mindkét `php -l` `No syntax errors detected`, majd `Minden teszt sikeres.`

- [ ] **Step 4: Commit**

```bash
git add templates/questions_answered_editor.php templates/contest_view.php
git commit -m "feat(kerdoiv): szerkeszthető beszámoló előre kitöltéssel és egyopciós kérdésekkel"
```

---

### Task 6: „Beszámoló" oszlop a versenylistában

**Files:**
- Modify: `templates/contest_list.php`

**Interfaces:**
- Consumes: Task 1 `vespa_kerdoiv_cella()`; Task 3 `vespa_contest_answer_counts()`, `vespa_contest_question_count()`
- Produces: semmi, amire későbbi task épül

- [ ] **Step 1: Az adatok egyszeri betöltése**

A `templates/contest_list.php` fájl elején egy hosszú PHP-blokk áll, ami a `?>`-vel zárul a `<form action="admin.php?page=contests" ...>` előtt. Ennek a blokknak a végére, a `getContestsByType` függvény **záró `}` sora után** illeszd be az adatok betöltését. A horgony (egyedi a fájlban):

```php
    function getContestsByType($versenyek, $typeName) {
        return array_filter($versenyek, function($item) use ($typeName) {
            return $item->contest_type_name === $typeName;
        });
    }
```

erre:

```php
    function getContestsByType($versenyek, $typeName) {
        return array_filter($versenyek, function($item) use ($typeName) {
            return $item->contest_type_name === $typeName;
        });
    }

    // A beszámoló kitöltöttsége EGYETLEN lekérdezésből, a táblázatok
    // kirajzolása előtt. Soronkénti hívás négy táblázatnyi versenyre N
    // lekérdezést jelentene.
    $beszamolo_szamlalok  = vespa_contest_answer_counts();
    $beszamolo_kerdesszam = vespa_contest_question_count();
```

- [ ] **Step 2: A fejléc-oszlop mind a négy táblázatban**

Ugyanebben a fájlban cseréld ki **minden előfordulást** (négy darab van, mind azonos):

```php
                                <th>Fogy. csoport</th>
                                <th class="no-export">Műveletek</th>
```

erre:

```php
                                <th>Fogy. csoport</th>
                                <th>Beszámoló</th>
                                <th class="no-export">Műveletek</th>
```

- [ ] **Step 3: A cella mind a négy táblázatban**

Ugyanebben a fájlban cseréld ki **minden előfordulást** (négy darab van, mind azonos; a `$race` a ciklusváltozó mind a négy táblázatban).

Figyelem: a fölötte álló `mapIdsToNames(...)` blokk **nem** használható horgonyként, mert a négy másolat nem bájtazonos — az elsőben egy sorvégi szóköz van. Az alábbi három sor viszont pontosan négyszer szerepel a fájlban, ezért ez a horgony:

```php
                                        ?>
                                    </td>
                                    <td class="no-export">
```

erre:

```php
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                            $beszamolo_db = isset($beszamolo_szamlalok[intval($race->contest_id)])
                                                ? $beszamolo_szamlalok[intval($race->contest_id)]
                                                : 0;
                                            $beszamolo_cella = vespa_kerdoiv_cella($beszamolo_db, $beszamolo_kerdesszam);
                                        ?>
                                        <span style="color: <?php echo esc_attr($beszamolo_cella['szin']); ?>; font-weight: <?php echo $beszamolo_cella['allapot'] === 'reszleges' ? '400' : '600'; ?>;">
                                            <?php echo esc_html($beszamolo_cella['cimke']); ?>
                                        </span>
                                    </td>
                                    <td class="no-export">
```

A „Műveletek" oszlop `no-export` osztályt visel, az új oszlop nem — így a lista exportjába bekerül.

- [ ] **Step 4: Szintaxis-ellenőrzés és teljes tesztfuttatás**

Run: `php -l templates/contest_list.php && php tests/test-kerdoiv.php && php tests/test-szabadidos-fields.php && php tests/test-frontend-access.php`
Expected: `No syntax errors detected`, majd mindhárom teszt `Minden teszt sikeres.`

- [ ] **Step 5: Az oszlopszám ellenőrzése**

A négy táblázat fejlécében és törzsében ugyanannyi cellának kell lennie, különben a DataTables eldobja a táblát.

Run: `grep -c '<th>Beszámoló</th>' templates/contest_list.php && grep -c 'vespa_kerdoiv_cella(\$beszamolo_db' templates/contest_list.php`
Expected: mindkettő `4`

- [ ] **Step 6: Commit**

```bash
git add templates/contest_list.php
git commit -m "feat(kerdoiv): beszámoló kitöltöttség oszlop a versenylistában"
```

- [ ] **Step 7: Telepítés utáni kézi ellenőrzés**

Ez a lista a teljes funkció végigpróbálása éles/teszt WordPress-en.

1. **Migráció.** Telepítés után nyiss meg egy wp-admin oldalt (hogy az `init` lefusson), majd nézd meg az adatbázisban: a `vespa_questions_answered` táblának van `question_id` oszlopa, a `vespa_kerdoiv_db_version` option értéke `1`, és a régi sorok többségén a `question_id` nem 0.
2. **Oszlop megjelenése.** Versenyek menü. → Mind a négy táblázatban (Országos, Megyei, Regionális, Szabadidősport) ott a „Beszámoló" oszlop, a Fogy. csoport után.
3. **Három állapot.** Keress egy beszámoló nélküli versenyt → piros *Nincs kitöltve*. Egy régi, kitöltött versenyt → szürke `17/23` alakú számláló. (A *Kitöltve* zöld állapotot a 6. pont után látod.)
4. **Gomb felirata.** Nyiss meg egy beszámoló nélküli versenyt → *Beszámoló rögzítése*. Egy kitöltöttet → *Beszámoló szerkesztése*, és a gomb **látszik** (ma eltűnne).
5. **Előre kitöltés.** Nyisd meg egy kitöltött verseny beszámolóját. → A rádiógombok a mentett válasszal bejelölve, a megjegyzések kitöltve.
6. **Egyopciós kérdés.** Az egyetlen válaszlehetőségű kérdéseknél nincs rádiógomb, csak a megjegyzés mező, teljes szélességben.
7. **Megjegyzés kiválasztás nélkül.** Egy több lehetőséges kérdésnél hagyd üresen a rádiógombot, írj a megjegyzésbe, majd Mentés. → Sikeres mentés, a megjegyzés visszatöltődik, és a kérdés beleszámít a kitöltöttségbe.
8. **Nincs duplikálódás.** Mentsd el ugyanazt a beszámolót kétszer, majd nézd meg az adatbázisban a sorok számát erre a versenyre. → Nem nőtt.
9. **Kérdésvesztés megszűnt.** Tölts ki egy magas `ordernum`-ú kérdést (a lista vége felé), mentsd, és nyisd meg újra. → A válasz megmaradt. (Ma ez elveszne.)
10. **Üres kérdés nem kap sort.** Hagyj néhány kérdést teljesen üresen, mentsd. → Ezekhez nem keletkezik sor, és a számláló nem őket számolja.
11. **Üresre szerkesztés.** Egy kitöltött kérdésből töröld a választ és a megjegyzést is, mentsd. → A sor eltűnik, a számláló csökken.
12. **Jogosultság.** Lépj be tankerületi igazgató vagy felettes szerv szerepű felhasználóval. → A „Beszámoló rögzítése" gomb nem látszik, és a `?page=contests&action=question&id=…` URL közvetlen megnyitása is elutasít.
13. **Korábbi kérdések blokk.** Ha van olyan verseny, aminek `question_id = 0`-s sora maradt, annak a beszámolójában a lap alján megjelenik a „Korábbi kérdések" táblázat, csak olvashatóan.
14. **Kitöltve állapot.** Tölts ki egy versenyen minden kérdést. → A listában zöld *Kitöltve*.
15. **Lista exportja.** Exportáld a versenylistát. → A „Beszámoló" oszlop szerepel benne, a „Műveletek" nem.

---

## Self-Review

**Spec-lefedettség.** Végigmentem a spec szakaszain:

| Spec-követelmény | Task |
|---|---|
| `question_id` oszlop, index, egyszeri szövegalapú feltöltés | 2 |
| Verziókapu csak sikeres oszlop-létrehozás után zárul | 2 |
| Nem `dbDelta`, hanem oszlop-ellenőrzés + `ALTER TABLE` | 2 |
| `question_id = 0` történelmi sorok megőrzése, csak olvasható megjelenítés | 4 (nem nyúl hozzájuk), 5 (blokk) |
| Nonce + `versenyek_kezelese_kiiras_modositas_torles` a mentésen és az oldalon | 4 |
| Párosító mentés: insert / update / delete / nincs művelet | 4 |
| Üres kérdéshez nem keletkezik sor | 4 |
| Kérdésvesztés javítása (ordernum ≠ tömbindex) | 4 |
| Holt kód (kikommentelt kötelezőség-ellenőrzés) törlése | 4 |
| `isset()` védelem a be nem jelölt rádiógombra | 4 |
| Előre kitöltött űrlap | 5 |
| Egyetlen válaszlehetőségnél nincs rádiógomb | 5 |
| A gomb mindig látszik, állapotfüggő felirattal | 5 |
| Három állapotú oszlop mind a négy táblázatban | 6 |
| Egyetlen aggregáló lekérdezés, nem soronkénti | 3, 6 |
| Az új oszlop exportálható, a Műveletek nem | 6 |
| `vespa_contest_has_answers()` változatlanul marad | — (egyik task sem nyúl hozzá) |
| Peremeset: nulla kérdés → `—`, nincs nullával osztás | 1 (teszt), 5 (üres űrlap) |
| Peremeset: árva sor törölt versenyhez | 3 (az aggregálás a versenyekből indul, így nem jelenik meg) |
| Unit tesztek a tiszta logikára | 1 |

Nincs lefedetlen követelmény.

**Placeholder-ellenőrzés.** Nincs „TBD", „hasonlóan a Task N-hez", vagy kód nélküli kódlépés; minden lépés a beillesztendő tartalommal együtt szerepel.

**Típus-egyezés.** A Task 1 `vespa_kerdoiv_cella()` `allapot` / `cimke` / `szin` kulcsokat ad — a Task 6 pontosan ezeket olvassa. A Task 3 `vespa_contest_answer_counts()` `contest_id => int` térképe a Task 6-ban `intval($race->contest_id)` kulccsal indexelődik, ugyanazzal a típussal, amivel a helper feltölti. A Task 4 által várt `answer<ordernum>` / `qnote<ordernum>` mezőneveket a Task 5 sablonja pontosan így rendereli, és a `nonce` mezőnév egyezik a `check_ajax_referer` második argumentumával.

**Egy tudatos eltérés a spec szövegétől.** A spec a `vespa_kerdoiv_allapot()` és `vespa_kerdoiv_cimke()` függvényeket külön nevezte meg, plusz egy `vespa_kerdoiv_kerdes_megvalaszolt()`-at. A terv a címkét és a színt egyetlen `vespa_kerdoiv_cella()` függvénybe vonja össze, mert a nulla kérdés esete (`—`, semleges szín) különben két helyen, egymástól függetlenül kezelt kivétel lenne, és a kettő elcsúszhatna.
