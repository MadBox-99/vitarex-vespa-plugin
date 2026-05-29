# Pedagógusok a nevezésnél + háromállapotú színkódolás — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A verseny-nevezésnél tetszőleges számú pedagógus megadható legyen (és csak utána lehessen sportolót nevezni), valamint a verseny-listában háromállapotú (piros/kék/zöld) színkódolás működjön.

**Architecture:** Új `vespa_contest_teachers` tábla pedagógusonként egy sorral; dedikált `save_teachers` AJAX végpont; a sportoló-nevezés backend-oldali blokkolása pedagógus hiányában; a verseny-lista 4× ismétlődő inline színfeltétele egy `vespa_contest_status_color()` segédfüggvénybe szervezve.

**Tech Stack:** WordPress plugin, PHP 8.4, `$wpdb`, jQuery (vespa-admin.js), kézzel futtatott SQL changelog (`database/changes.sql`).

**Megjegyzés a tesztelésről:** A pluginban nincs automatizált tesztkészlet. Ahol „test" lépés szerepel, az PHP szintaxis-ellenőrzés (`php -l`) és/vagy manuális böngészős ellenőrzés. Minden kódlépés után `php -l` fut a módosított PHP fájlon.

---

## File Structure

- `database/changes.sql` — **módosít**: új `vespa_contest_teachers` tábla CREATE utasítása dátumozott bejegyzésként a fájl végén.
- `includes/Core/functions.php` — **módosít**: 3 új segédfüggvény (`vespa_contest_status_color`, `vespa_get_teachers`, `vespa_school_has_teachers`).
- `templates/contest_list.php` — **módosít**: 4 helyen az inline `background-color` feltétel cseréje `vespa_contest_status_color($race)` hívásra.
- `includes/Ajax/ajax.save_teachers.php` — **új**: `save_teachers` AJAX action (automatikusan betöltődik a `glob`-os loader miatt).
- `templates/contest_view_entering.php` — **módosít**: új „Pedagógusok" szekció a „Versenyszámok" elé.
- `js/vespa-admin.js` — **módosít**: `addTeacherRow()`, `removeTeacherRow()`, `saveTeachers()` függvények.
- `includes/Ajax/contest.signup.php` — **módosít**: pedagógus-feltétel az `athletes_signup()`-ban.

---

## Task 1: Adatbázis — `vespa_contest_teachers` tábla

**Files:**
- Modify: `database/changes.sql` (fájl vége)

- [ ] **Step 1: A CREATE TABLE bejegyzés hozzáfűzése a changelog végéhez**

Fűzd a `database/changes.sql` **végére**:

```sql

--2026.05.29.
-- Pedagógusok tárolása verseny-nevezéshez (versenyenként+iskolánként tetszőleges számú sor)
CREATE TABLE IF NOT EXISTS `vespa_contest_teachers` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `contest_id` INT NOT NULL,
    `school_id` INT NOT NULL,
    `teljes_nev` VARCHAR(200) NOT NULL,
    `mobil` VARCHAR(50) NOT NULL,
    `email` VARCHAR(200) NOT NULL,
    `szuletesi_hely` VARCHAR(200) NOT NULL,
    `szuletesi_ido` DATE NOT NULL,
    `iskola_neve` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `contest_school` (`contest_id`, `school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_hungarian_ci;
```

- [ ] **Step 2: A táblát létrehozni az adatbázisban**

Futtasd az új SQL utasítást az élő/fejlesztői adatbázison (a plugin nem futtatja automatikusan a `changes.sql`-t). Pl. Herd/TablePlus/CLI:

Run: `php -r "require '<wp-load.php elérése>'; global \$wpdb; \$wpdb->query(file_get_contents('database/changes.sql'));"` **csak ha** a teljes changelog újrafuttatható nálad; egyébként futtasd kézzel csak az új CREATE TABLE blokkot.
Expected: a `vespa_contest_teachers` tábla létrejön (`SHOW TABLES LIKE 'vespa_contest_teachers';` egy sort ad).

- [ ] **Step 3: Commit**

```bash
git add database/changes.sql
git commit -m "db: vespa_contest_teachers tábla a pedagógusokhoz"
```

---

## Task 2: Segédfüggvények a `functions.php`-ban

**Files:**
- Modify: `includes/Core/functions.php`

- [ ] **Step 1: A három segédfüggvény hozzáadása**

Add hozzá a `includes/Core/functions.php` **végéhez** (a záró sor elé / a fájl aljára):

```php
/**
 * Verseny státusz szerinti háttérszín a verseny-listához.
 * PIROS: a verseny napja már elmúlt. ZÖLD: véglegesített és épp nyitva a nevezés.
 * KÉK: minden más jövőbeli állapot (nincs véglegesítve, vagy a nevezés még nem indult,
 * vagy a nevezés már lezárult, de a verseny napja még nem volt meg).
 */
function vespa_contest_status_color($contest)
{
    $now = date('Y-m-d H:i:s');

    if ($contest->end_at < $now) {
        return '#ec5a64'; // piros – elmúlt verseny
    }

    if ($contest->is_final
        && $contest->school_entry_start_at <= $now
        && $contest->school_entry_end_at  >= $now) {
        return '#63c27c'; // zöld – nevezhető
    }

    return '#5bc0de'; // kék – létrehozva, de még nem nyitott
}

/**
 * Egy iskola pedagógusai egy adott versenyre.
 * @return array vespa_contest_teachers sorok
 */
function vespa_get_teachers($school_id, $contest_id)
{
    global $wpdb;

    if (!is_numeric($school_id) || !is_numeric($contest_id)) {
        return array();
    }

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM vespa_contest_teachers WHERE school_id=%d AND contest_id=%d ORDER BY id ASC",
        $school_id,
        $contest_id
    ));
}

/**
 * Van-e legalább egy pedagógus megadva az adott iskola+verseny párosra.
 */
function vespa_school_has_teachers($school_id, $contest_id)
{
    global $wpdb;

    if (!is_numeric($school_id) || !is_numeric($contest_id)) {
        return false;
    }

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM vespa_contest_teachers WHERE school_id=%d AND contest_id=%d",
        $school_id,
        $contest_id
    ));

    return intval($count) > 0;
}
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Core/functions.php`
Expected: `No syntax errors detected in includes/Core/functions.php`

- [ ] **Step 3: Commit**

```bash
git add includes/Core/functions.php
git commit -m "feat: vespa_contest_status_color + pedagógus segédfüggvények"
```

---

## Task 3: Háromállapotú színkódolás a verseny-listában

**Files:**
- Modify: `templates/contest_list.php` (4 hely: Országos ~636, Megyei ~756, Regionális ~849, Szabadidősport ~928)

- [ ] **Step 1: A 4 inline színfeltétel cseréje**

A `templates/contest_list.php`-ben **mind a négy** előforduló sor pontosan így néz ki jelenleg:

```php
<td style="background-color: <?php echo ($race->is_final && $race->school_entry_start_at <= date('Y-m-d H:i:s') && $race->school_entry_end_at >= date('Y-m-d H:i:s')) ? '#63c27c' : '#ec5a64'; ?>">
```

Cseréld **mind a négyet** erre (használd a `replace_all`-t, mivel a sorok azonosak):

```php
<td style="background-color: <?php echo vespa_contest_status_color($race); ?>">
```

- [ ] **Step 2: Ellenőrizd, hogy nem maradt régi feltétel**

Run: `grep -c "63c27c.*ec5a64" templates/contest_list.php`
Expected: `0`

Run: `grep -c "vespa_contest_status_color" templates/contest_list.php`
Expected: `4`

- [ ] **Step 3: Szintaxis-ellenőrzés**

Run: `php -l templates/contest_list.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Manuális ellenőrzés (böngésző)**

Nyisd meg a Versenyek listát és ellenőrizd:
- elmúlt verseny (`end_at` < most) → **piros** (`#ec5a64`)
- véglegesített, nyitott nevezésű → **zöld** (`#63c27c`)
- jövőbeli, nem véglegesített / még nem nyitott / lezárult nevezésű de jövőbeli → **kék** (`#5bc0de`)
- mind a 4 táblázatban (Országos/Megyei/Regionális/Szabadidősport) egységes.

- [ ] **Step 5: Commit**

```bash
git add templates/contest_list.php
git commit -m "feat: háromállapotú (piros/kék/zöld) színkódolás a verseny-listában"
```

---

## Task 4: `save_teachers` AJAX végpont

**Files:**
- Create: `includes/Ajax/ajax.save_teachers.php`

- [ ] **Step 1: Az AJAX handler létrehozása**

Hozd létre a `includes/Ajax/ajax.save_teachers.php` fájlt a következő tartalommal:

```php
<?php

/**
 * Pedagógusok mentése egy adott iskola+verseny párosra.
 * A kliens a teljes aktuális listát küldi parallel tömbökben; a meglévő
 * sorokat töröljük, majd az érvényeseket beszúrjuk. Mind a 6 mező kötelező.
 */
function ajax_save_teachers()
{
    global $wpdb;

    $contest_id = $_POST['contest_id'] ?? '';
    $school_id  = $_POST['school_id'] ?? '';

    if (!is_numeric($school_id) || !is_numeric($contest_id)) {
        wp_send_json_error(array('errors' => array(), 'success' => false), 400);
    }

    // jogosultság: csak testnevelő, és csak a saját iskolájára
    $my_school_id = vespa_get_my_school_id();
    if (!VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::TESTNEVELO) || $my_school_id != $school_id) {
        wp_send_json_error(array('errors' => array(), 'success' => false), 403);
    }

    $nevek    = isset($_POST['teacher_teljes_nev']) && is_array($_POST['teacher_teljes_nev']) ? $_POST['teacher_teljes_nev'] : array();
    $mobilok  = isset($_POST['teacher_mobil']) && is_array($_POST['teacher_mobil']) ? $_POST['teacher_mobil'] : array();
    $emailek  = isset($_POST['teacher_email']) && is_array($_POST['teacher_email']) ? $_POST['teacher_email'] : array();
    $helyek   = isset($_POST['teacher_szuletesi_hely']) && is_array($_POST['teacher_szuletesi_hely']) ? $_POST['teacher_szuletesi_hely'] : array();
    $idok     = isset($_POST['teacher_szuletesi_ido']) && is_array($_POST['teacher_szuletesi_ido']) ? $_POST['teacher_szuletesi_ido'] : array();
    $iskolak  = isset($_POST['teacher_iskola_neve']) && is_array($_POST['teacher_iskola_neve']) ? $_POST['teacher_iskola_neve'] : array();

    $rows = array();
    $has_partial = false;

    foreach ($nevek as $i => $nev) {
        $row = array(
            'teljes_nev'     => trim($nev ?? ''),
            'mobil'          => trim($mobilok[$i] ?? ''),
            'email'          => trim($emailek[$i] ?? ''),
            'szuletesi_hely' => trim($helyek[$i] ?? ''),
            'szuletesi_ido'  => trim($idok[$i] ?? ''),
            'iskola_neve'    => trim($iskolak[$i] ?? ''),
        );

        $filled = array_filter($row, function ($v) {
            return $v !== '';
        });

        if (count($filled) === 0) {
            continue; // teljesen üres sor – kihagyjuk
        }

        if (count($filled) < 6) {
            $has_partial = true; // részben kitöltött sor
            continue;
        }

        $rows[] = $row;
    }

    if ($has_partial) {
        wp_send_json_error(array('errors' => array(
            'teacher_teljes_nev' => 'Minden pedagógusnál mind a 6 mező kötelező.',
        )));
    }

    if (count($rows) === 0) {
        wp_send_json_error(array('errors' => array(
            'teacher_teljes_nev' => 'Legalább egy pedagógust meg kell adnod.',
        )));
    }

    // teljes csere: meglévő sorok törlése, majd újrabeszúrás
    $wpdb->delete('vespa_contest_teachers', array(
        'contest_id' => intval($contest_id),
        'school_id'  => intval($school_id),
    ), array('%d', '%d'));

    foreach ($rows as $row) {
        $wpdb->insert('vespa_contest_teachers', array(
            'contest_id'     => intval($contest_id),
            'school_id'      => intval($school_id),
            'teljes_nev'     => sanitize_text_field($row['teljes_nev']),
            'mobil'          => sanitize_text_field($row['mobil']),
            'email'          => sanitize_text_field($row['email']),
            'szuletesi_hely' => sanitize_text_field($row['szuletesi_hely']),
            'szuletesi_ido'  => sanitize_text_field($row['szuletesi_ido']),
            'iskola_neve'    => sanitize_text_field($row['iskola_neve']),
        ), array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s'));
    }

    wp_send_json_success(array(
        'content' => 'Pedagógusok mentése sikeres volt.',
        'target'  => 'teacher_success',
    ));
}
add_action('wp_ajax_save_teachers', 'ajax_save_teachers');
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Ajax/ajax.save_teachers.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add includes/Ajax/ajax.save_teachers.php
git commit -m "feat: save_teachers AJAX végpont a pedagógusok mentéséhez"
```

---

## Task 5: „Pedagógusok" szekció a nevezési oldalon

**Files:**
- Modify: `templates/contest_view_entering.php` (a `<div class="vespa-races">` blokk **elé**, kb. a 73. sor előtt)

- [ ] **Step 1: A pedagógus-szekció beszúrása a „Versenyszámok" elé**

A `templates/contest_view_entering.php`-ben keresd meg ezt a sort (kb. 73.):

```php
            <div class="vespa-races">
```

És **közvetlenül elé** szúrd be a következő blokkot:

```php
            <div class="vespa-teachers" style="margin-bottom:40px;">
                <h1>Pedagógusok</h1>
                <p>A nevezéshez legalább egy pedagógus megadása kötelező. Mind a hat mező kitöltése szükséges pedagógusonként.</p>

                <?php
                $teachers = vespa_get_teachers(vespa_get_my_school_id(), $record->contest_id);
                if (empty($teachers)) {
                    // egy üres sablonsor, hogy legyen mit kitölteni
                    $teachers = array(null);
                }
                ?>

                <form action="" class="ajax-form" id="vespa-teachers-form">
                    <input type="hidden" name="action" value="save_teachers" autocomplete="off">
                    <input type="hidden" name="school_id" value="<?php echo vespa_get_my_school_id(); ?>" autocomplete="off">
                    <input type="hidden" name="contest_id" value="<?php echo $record->contest_id; ?>" autocomplete="off">

                    <div class="row kisero-row" style="font-weight:bold;">
                        <div class="col-md-2">Teljes név</div>
                        <div class="col-md-2">Mobil telefonszám</div>
                        <div class="col-md-2">E-mail</div>
                        <div class="col-md-2">Születési hely</div>
                        <div class="col-md-2">Születési idő</div>
                        <div class="col-md-2">Iskola neve</div>
                    </div>

                    <div id="teacher_rows">
                        <?php foreach ($teachers as $t) : ?>
                            <div class="row kisero-row teacher-row">
                                <div class="col-md-2">
                                    <input type="text" class="form-control" name="teacher_teljes_nev[]" value="<?php echo $t ? esc_attr($t->teljes_nev) : ''; ?>">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control" name="teacher_mobil[]" value="<?php echo $t ? esc_attr($t->mobil) : ''; ?>">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control" name="teacher_email[]" value="<?php echo $t ? esc_attr($t->email) : ''; ?>">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control" name="teacher_szuletesi_hely[]" value="<?php echo $t ? esc_attr($t->szuletesi_hely) : ''; ?>">
                                </div>
                                <div class="col-md-2">
                                    <input type="date" class="form-control" name="teacher_szuletesi_ido[]" value="<?php echo $t ? esc_attr($t->szuletesi_ido) : ''; ?>">
                                </div>
                                <div class="col-md-1">
                                    <input type="text" class="form-control" name="teacher_iskola_neve[]" value="<?php echo $t ? esc_attr($t->iskola_neve) : ''; ?>">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="button btn-remove-teacher" onclick="removeTeacherRow(this);">✕</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-12">
                            <button type="button" class="button" onclick="addTeacherRow();">➕ Pedagógus hozzáadása</button>
                        </div>
                    </div>

                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-12">
                            <div id="teacher_success"></div>
                            <button type="submit" class="button">Pedagógusok mentése</button>
                        </div>
                    </div>
                </form>
            </div>
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l templates/contest_view_entering.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add templates/contest_view_entering.php
git commit -m "feat: Pedagógusok szekció a nevezési oldalon"
```

---

## Task 6: JS — dinamikus sorok + mentés

**Files:**
- Modify: `js/vespa-admin.js` (új függvények a fájl globális szintjén, a meglévő `editEscorts`/`saveEscort` mintára)

> A `#vespa-teachers-form` a `.ajax-form` osztályt használja, így a beküldést a meglévő `js/vespa-ajax-form.js` kezeli (FormData → `save_teachers`, siker esetén a `teacher_success` divbe írja a visszajelzést, hiba esetén a `teacher_teljes_nev[]` mezőket `is-invalid`-dá teszi). Itt csak a sorok dinamikus hozzáadását/törlését kell megírni.

- [ ] **Step 1: `addTeacherRow` és `removeTeacherRow` hozzáadása**

Add hozzá a `js/vespa-admin.js` **végéhez** (globális függvényként, nem a `jQuery(document).ready` blokkon belül):

```javascript
function addTeacherRow() {
  var rows = jQuery("#teacher_rows");
  var html =
    '<div class="row kisero-row teacher-row">' +
    '  <div class="col-md-2"><input type="text" class="form-control" name="teacher_teljes_nev[]"></div>' +
    '  <div class="col-md-2"><input type="text" class="form-control" name="teacher_mobil[]"></div>' +
    '  <div class="col-md-2"><input type="text" class="form-control" name="teacher_email[]"></div>' +
    '  <div class="col-md-2"><input type="text" class="form-control" name="teacher_szuletesi_hely[]"></div>' +
    '  <div class="col-md-2"><input type="date" class="form-control" name="teacher_szuletesi_ido[]"></div>' +
    '  <div class="col-md-1"><input type="text" class="form-control" name="teacher_iskola_neve[]"></div>' +
    '  <div class="col-md-1"><button type="button" class="button btn-remove-teacher" onclick="removeTeacherRow(this);">✕</button></div>' +
    "</div>";
  rows.append(html);
}

function removeTeacherRow(btn) {
  var rows = jQuery("#teacher_rows");
  // ne lehessen az utolsó sort is törölni – maradjon legalább egy üres sor
  if (rows.find(".teacher-row").length <= 1) {
    jQuery(btn).closest(".teacher-row").find("input").val("");
    return;
  }
  jQuery(btn).closest(".teacher-row").remove();
}
```

- [ ] **Step 2: Szintaxis-ellenőrzés (Node, ha elérhető)**

Run: `node --check js/vespa-admin.js`
Expected: nincs hiba (üres kimenet). Ha nincs `node`, hagyd ki ezt a lépést és ellenőrizd böngészőben.

- [ ] **Step 3: Manuális ellenőrzés (böngésző)**

A nevezési oldalon:
- „➕ Pedagógus hozzáadása" új üres sort ad.
- „✕" törli a sort; az utolsó sor nem tűnik el, csak kiürül.
- „Pedagógusok mentése" elküldi az adatokat; siker esetén megjelenik a „Pedagógusok mentése sikeres volt." üzenet a `#teacher_success`-ben.
- Hiányos sor mentésekor a név mezők `is-invalid` jelölést kapnak, és megjelenik a hibaüzenet.
- Újratöltés után a mentett pedagógusok visszatöltődnek a sorokba.

- [ ] **Step 4: Commit**

```bash
git add js/vespa-admin.js
git commit -m "feat: pedagógus sorok dinamikus hozzáadása/törlése"
```

---

## Task 7: Sportoló-nevezés blokkolása pedagógus hiányában

**Files:**
- Modify: `includes/Ajax/contest.signup.php` (`athletes_signup()` metódus, az „add" ág **előtt**)

- [ ] **Step 1: A pedagógus-feltétel beszúrása az „add" ág elejére**

Csak az **új nevezést** (`add`) blokkoljuk; a már nevezett sportoló **levételét** (`remove`) nem. Ezért a feltétel a `$data` lekérdezés utáni `else` (= „add") ág elejére kerül.

A `includes/Ajax/contest.signup.php` `athletes_signup()` metódusában keresd meg ezt a meglévő blokkot:

```php
        } else {
            $response['action'] = 'add';
            // check if can be entered     
            $contest = $GLOBALS['VESPA_Contests']->load($contest_id);
```

És az `$response['action'] = 'add';` sor **után** szúrd be:

```php
            // Pedagógus-feltétel: csak akkor nevezhető új sportoló, ha az iskola
            // megadott legalább egy pedagógust erre a versenyre.
            if (!vespa_school_has_teachers($school_id, $contest_id)) {
                $response['success'] = false;
                $response['message'] = 'Előbb add meg legalább egy pedagógust a "Pedagógusok" szekcióban a nevezéshez!';
                wp_send_json($response);
                die();
            }
```

> A `$school_id` ekkorra már fel van oldva (a fentebbi `if ($school_id == 0) { $school_id = vespa_get_my_school_id(); }` blokk lefutott), tehát a `vespa_school_has_teachers($school_id, $contest_id)` helyes iskolára kérdez.

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Ajax/contest.signup.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Manuális ellenőrzés (böngésző)**

- Pedagógus nélkül egy sportoló nevezése → `alert`: „Előbb add meg legalább egy pedagógust…", a nevezés nem jön létre.
- Egy pedagógus mentése után ugyanaz a nevezés → sikeres.
- Már nevezett sportoló **levétele** pedagógus nélkül is működik (a levételt nem blokkoljuk).

- [ ] **Step 4: Commit**

```bash
git add includes/Ajax/contest.signup.php
git commit -m "feat: sportoló-nevezés blokkolása pedagógus hiányában"
```

---

## Záró ellenőrzés (mindkét funkció együtt)

- [ ] **Step 1: Teljes körű manuális végigjátszás**

1. **Színkódolás:** a Versenyek listán mind a 3 állapot helyes színnel jelenik meg mind a 4 táblázatban.
2. **Pedagógusok:** új verseny nevezési oldalán pedagógus hozzáadása/törlése/mentése működik; újratöltés után visszatöltődik.
3. **Gating:** pedagógus nélkül nincs sportoló-nevezés; pedagógus megadása után van; levétel mindig megy.

- [ ] **Step 2: Az ág-helyesség ellenőrzése a signup kódban**

Run: `grep -n "vespa_school_has_teachers" includes/Ajax/contest.signup.php`
Expected: **pontosan 1** találat, az „add" ágban (a `$response['action'] = 'add';` után).

---

## Self-Review jegyzet

- **Spec-lefedettség:** külön tábla (Task 1), segédfüggvények (Task 2), színkódolás 4 helyen (Task 3), `save_teachers` (Task 4), UI szekció (Task 5), dinamikus sorok (Task 6), nevezés-gating csak az „add" ágon (Task 7). Minden spec-pont le van fedve.
- **Mezőnevek konzisztensek** a PHP (Task 4/5) és a JS (Task 6) között: `teacher_teljes_nev[]`, `teacher_mobil[]`, `teacher_email[]`, `teacher_szuletesi_hely[]`, `teacher_szuletesi_ido[]`, `teacher_iskola_neve[]`.
- **Függvénynevek konzisztensek:** `vespa_contest_status_color`, `vespa_get_teachers`, `vespa_school_has_teachers` (Task 2) megegyeznek a hívási helyekkel (Task 3, 5, 7).
- **Gating ág:** a Task 7 kifejezetten az „add" ágra korlátozza a blokkolást, hogy a levétel ne sérüljön.
