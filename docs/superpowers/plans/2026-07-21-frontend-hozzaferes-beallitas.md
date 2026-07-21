# Front-end hozzáférés beállítása — implementációs terv

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Adminisztrátor által kezelhető felület, amelyen kijelölhető, mely WP-oldalak érhetők el publikusan a front-end redirect ellenére — és ezzel együtt a szabadidős résztvevők végtelen átirányítási hurkának megszüntetése.

**Architecture:** A döntési logika egy tiszta (WordPress-független) függvénybe kerül a `Core` rétegbe, így egyszerű PHP-szkripttel unit-tesztelhető. Köré vékony WP-wrapperek épülnek (option olvasás/írás, oldal-listázás). A `login.customiser.php` és a `szabadidos.isolation.php` ezt a logikát hívja. Az admin felület a plugin meglévő mintáját követi: `add_menu_page` + `vespa_load_template()` + nonce-olt AJAX mentés a `vespa-ajax-form.js`-sel.

**Tech Stack:** PHP 7+, WordPress plugin API (options, admin menu, admin-ajax), a plugin saját `vespa-ajax-form.js` rétege. Nincs composer/phpunit — a unit tesztek sima `php` szkripttel futnak.

## Global Constraints

- Az `includes/{Core,Datalist,Admin,Ajax,Export,Api}/*.php` fájlokat a `vitarex-vespa-plugin.php:51-63` **automatikusan** betölti `glob`-bal, ebben a könyvtár-sorrendben. Új fájlt sehol nem kell kézzel `require`-elni; a `Core` mindig az `Admin` előtt tölt be.
- Az `includes/Core/vespa.frontend.access.php` **csak függvényeket definiálhat** — se top-level hook, se `defined('ABSPATH') || exit;` guard, se WP-konstans használat betöltéskor. Ez teszi lehetővé, hogy a teszt sima PHP-vel betöltse.
- A beállítás egyetlen WP option: `vespa_frontend_access`.
- Jogosultság mindenhol: `manage_options` (oldal megjelenítés és AJAX mentés is).
- Az AJAX űrlap szerződése (`js/vespa-ajax-form.js`): a form `class="ajax-form"`, rejtett `action` és `nonce` mezővel; siker = `wp_send_json_success(array('modal' => ..., 'modalId' => 'succesModal'))`; mezőhiba = `wp_send_json_error(array('errors' => array('mezonev' => 'üzenet')))`.
- Hibamező-név **nem tartalmazhat szögletes zárójelet**: a `vespa-ajax-form.js:78` idézőjel nélküli `[name=...]` szelektort épít, amit a `public_page_ids[]` eltörne. Ezért csak a `szabadidos_landing_page_id` mezőre adunk vissza hibát.
- Magyar nyelvű felhasználói szövegek és kódkommentek, a kódbázis stílusában.
- Minden kimenet escape-elve: `esc_html`, `esc_attr`.

---

## File Structure

| Fájl | Felelősség |
|---|---|
| `includes/Core/vespa.frontend.access.php` | *új* — tiszta döntési függvény + option-olvasó/sanitizáló helperek |
| `tests/test-frontend-access.php` | *új* — a tiszta függvény unit tesztjei, sima PHP-vel futtatva |
| `includes/Admin/frontend.access.settings.php` | *új* — admin menü, oldal-callback, AJAX mentés |
| `templates/frontend_access_settings.php` | *új* — pipa-lista, legördülő, Mentés gomb |
| `includes/Admin/login.customiser.php` | *módosul* — `redirect_to_admin()` a döntési logikát hívja |
| `includes/Admin/szabadidos.isolation.php` | *módosul* — a beállított landing page-re irányít |

---

### Task 1: Tiszta döntési logika + teszt-futtató

**Files:**
- Create: `includes/Core/vespa.frontend.access.php`
- Test: `tests/test-frontend-access.php`

**Interfaces:**
- Consumes: semmi (ez az első task)
- Produces: `vespa_frontend_access_decide($is_admin_area, $is_logged_in, $is_participant, $post_id, $public_page_ids)` → string, értéke `'pass'` | `'admin'` | `'login'`

- [ ] **Step 1: Write the failing test**

Hozd létre a `tests/test-frontend-access.php` fájlt:

```php
<?php
/**
 * A front-end hozzáférési döntés unit tesztjei.
 * Futtatás: php tests/test-frontend-access.php
 * WordPress nem kell hozzá: a tesztelt függvény tiszta.
 */

require_once __DIR__ . '/../includes/Core/vespa.frontend.access.php';

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

$publikus = array(204, 310);

// A wp-admin felülettel nincs dolgunk.
allit(
    vespa_frontend_access_decide(true, true, false, 0, $publikus) === 'pass',
    'wp-admin felület mindig átmegy'
);

// Publikusra jelölt oldal mindenkinek elérhető.
allit(
    vespa_frontend_access_decide(false, false, false, 204, $publikus) === 'pass',
    'publikus oldal kijelentkezve átmegy'
);
allit(
    vespa_frontend_access_decide(false, true, false, 204, $publikus) === 'pass',
    'publikus oldal bejelentkezve is átmegy'
);

// Nem publikus oldalon marad a mai viselkedés.
allit(
    vespa_frontend_access_decide(false, true, false, 999, $publikus) === 'admin',
    'nem publikus oldal bejelentkezve wp-adminba megy'
);
allit(
    vespa_frontend_access_decide(false, false, false, 999, $publikus) === 'login',
    'nem publikus oldal kijelentkezve loginra megy'
);

// A szabadidős résztvevőt SOHA nem küldjük wp-adminba — ez töri meg a hurkot.
allit(
    vespa_frontend_access_decide(false, true, true, 999, $publikus) === 'pass',
    'szabadidos resztvevo nem publikus oldalon is atmegy (hurokvedelem)'
);
allit(
    vespa_frontend_access_decide(false, true, true, 0, array()) === 'pass',
    'szabadidos resztvevo ures publikus listaval is atmegy'
);

// Nem egyedi bejegyzés (archívum, 404): post_id = 0, nem publikus.
allit(
    vespa_frontend_access_decide(false, false, false, 0, $publikus) === 'login',
    'nem singular oldal kijelentkezve loginra megy'
);

// Típusbiztonság: a listában szöveges ID is elfogadott, a 0 viszont soha nem talál.
allit(
    vespa_frontend_access_decide(false, false, false, 204, array('204')) === 'pass',
    'szoveges ID a listaban is talal'
);
allit(
    vespa_frontend_access_decide(false, false, false, 0, array(0)) === 'login',
    'a 0 post_id soha nem szamit publikusnak'
);

echo "\n" . ($hibak === 0 ? "Minden teszt sikeres.\n" : $hibak . " teszt elbukott.\n");
exit($hibak === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test-frontend-access.php`

Expected: FAIL — `PHP Warning: require_once(...vespa.frontend.access.php): Failed to open stream: No such file or directory`, majd fatális hiba. A fájl még nem létezik.

- [ ] **Step 3: Write minimal implementation**

Hozd létre az `includes/Core/vespa.frontend.access.php` fájlt. **Csak ezt a függvényt** — a WP-függő helperek a 2. taskban jönnek:

```php
<?php

/**
 * Eldönti, mi történjen egy front-end kéréssel.
 *
 * Szándékosan tiszta függvény: nem hív WordPress API-t, így önmagában
 * tesztelhető (lásd tests/test-frontend-access.php).
 *
 * @param bool  $is_admin_area   igaz, ha a kérés a wp-admin felületre megy
 * @param bool  $is_logged_in    igaz, ha van bejelentkezett felhasználó
 * @param bool  $is_participant  igaz, ha a felhasználó szabadidős külső résztvevő
 * @param int   $post_id         az aktuális egyedi bejegyzés ID-ja, vagy 0
 * @param array $public_page_ids publikusan elérhetőnek jelölt oldal-ID-k
 *
 * @return string 'pass' (engedjük), 'admin' (wp-adminba) vagy 'login' (login oldalra)
 */
function vespa_frontend_access_decide($is_admin_area, $is_logged_in, $is_participant, $post_id, $public_page_ids)
{
    if ($is_admin_area) {
        return 'pass';
    }

    $post_id = intval($post_id);
    if ($post_id > 0) {
        foreach ((array) $public_page_ids as $publikus_id) {
            if (intval($publikus_id) === $post_id) {
                return 'pass';
            }
        }
    }

    // A szabadidős résztvevőnek nincs dolga a wp-adminban, ezért őt sosem
    // küldjük oda: a szabadidos.isolation.php onnan visszairányítaná, és a
    // két szabály végtelen hurkot zárna. Ez a lépés vágja el a hurkot.
    if ($is_logged_in && $is_participant) {
        return 'pass';
    }

    return $is_logged_in ? 'admin' : 'login';
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test-frontend-access.php`

Expected: PASS — 10 sor `OK`, majd `Minden teszt sikeres.`, kilépési kód 0.

- [ ] **Step 5: Commit**

```bash
git add includes/Core/vespa.frontend.access.php tests/test-frontend-access.php
git commit -m "feat(front-end): tiszta döntési logika a front-end hozzáféréshez + tesztek"
```

---

### Task 2: Beállítás-olvasó és sanitizáló helperek

**Files:**
- Modify: `includes/Core/vespa.frontend.access.php` (bővítés a fájl végén)

**Interfaces:**
- Consumes: `vespa_frontend_access_decide()` (Task 1) — közvetlenül nem hívja, csak egy fájlban él vele
- Produces:
  - `vespa_frontend_access_get_settings()` → `array('public_page_ids' => int[], 'szabadidos_landing_page_id' => int)`
  - `vespa_frontend_access_public_page_ids()` → `int[]`
  - `vespa_frontend_access_landing_url()` → string (permalink vagy `home_url('/')`)
  - `vespa_frontend_access_sanitize_page_ids($ids)` → `int[]`

- [ ] **Step 1: Írd meg a helpereket**

Fűzd hozzá az `includes/Core/vespa.frontend.access.php` **végéhez**:

```php

/** A beállítás option neve. */
function vespa_frontend_access_option_name()
{
    return 'vespa_frontend_access';
}

/**
 * A mentett beállítások, mindig teljes és normalizált szerkezetben.
 */
function vespa_frontend_access_get_settings()
{
    $mentett = get_option(vespa_frontend_access_option_name(), array());
    if (!is_array($mentett)) {
        $mentett = array();
    }

    $oldalak = array();
    if (isset($mentett['public_page_ids']) && is_array($mentett['public_page_ids'])) {
        foreach ($mentett['public_page_ids'] as $id) {
            $oldalak[] = intval($id);
        }
    }

    return array(
        'public_page_ids'            => $oldalak,
        'szabadidos_landing_page_id' => isset($mentett['szabadidos_landing_page_id'])
            ? intval($mentett['szabadidos_landing_page_id'])
            : 0,
    );
}

/**
 * A publikusan elérhetőnek jelölt oldal-ID-k.
 */
function vespa_frontend_access_public_page_ids()
{
    $beallitasok = vespa_frontend_access_get_settings();
    return $beallitasok['public_page_ids'];
}

/**
 * A szabadidős résztvevő kezdőoldalának URL-je.
 *
 * Ha nincs beállítva, vagy az oldalt időközben törölték/piszkozatba tették,
 * csendben a kezdőlapra esik vissza.
 */
function vespa_frontend_access_landing_url()
{
    $beallitasok = vespa_frontend_access_get_settings();
    $id = $beallitasok['szabadidos_landing_page_id'];

    if ($id > 0 && get_post_status($id) === 'publish') {
        $link = get_permalink($id);
        if ($link) {
            return $link;
        }
    }

    return home_url('/');
}

/**
 * Csak létező, publikált oldalak ID-jait engedi át, duplikátum nélkül.
 */
function vespa_frontend_access_sanitize_page_ids($ids)
{
    $eredmeny = array();

    foreach ((array) $ids as $id) {
        $id = intval($id);
        if ($id <= 0) {
            continue;
        }
        if (get_post_type($id) !== 'page' || get_post_status($id) !== 'publish') {
            continue;
        }
        if (!in_array($id, $eredmeny, true)) {
            $eredmeny[] = $id;
        }
    }

    return $eredmeny;
}
```

- [ ] **Step 2: Ellenőrizd, hogy a Task 1 tesztje továbbra is fut**

Run: `php tests/test-frontend-access.php`

Expected: PASS — `Minden teszt sikeres.`, kilépési kód 0.

Ez azt igazolja, hogy az új, WP-függő függvények puszta *definíciója* nem töri el a WordPress nélküli betöltést. (A tesztek ezeket nem hívják, mert `get_option` nélkül nem futnának.)

- [ ] **Step 3: Ellenőrizd a szintaxist**

Run: `php -l includes/Core/vespa.frontend.access.php`

Expected: `No syntax errors detected in includes/Core/vespa.frontend.access.php`

- [ ] **Step 4: Commit**

```bash
git add includes/Core/vespa.frontend.access.php
git commit -m "feat(front-end): beállítás-olvasó és sanitizáló helperek"
```

---

### Task 3: Admin menü és beállító felület

**Files:**
- Create: `includes/Admin/frontend.access.settings.php`
- Create: `templates/frontend_access_settings.php`

**Interfaces:**
- Consumes: `vespa_frontend_access_get_settings()` (Task 2)
- Produces: `vespa_frontend_access` nevű admin oldal (`admin.php?page=vespa_frontend_access`); a `vespa_frontend_access_save` AJAX action a 4. taskban készül el

- [ ] **Step 1: Hozd létre az admin menüt**

Hozd létre az `includes/Admin/frontend.access.settings.php` fájlt:

```php
<?php

add_action('admin_menu', 'vespa_frontend_access_admin_menu');

function vespa_frontend_access_admin_menu()
{
    add_menu_page(
        'Front-end hozzáférés',
        'Front-end hozzáférés',
        'manage_options',
        'vespa_frontend_access',
        'vespa_frontend_access_admin_page',
        'dashicons-lock',
        6
    );
}

function vespa_frontend_access_admin_page()
{
    if (!current_user_can('manage_options')) {
        echo 'Nincs megfelelő jogosultságod az oldal megtekintéséhez.';
        return;
    }
    vespa_load_template('frontend_access_settings.php');
}
```

- [ ] **Step 2: Hozd létre a sablont**

Hozd létre a `templates/frontend_access_settings.php` fájlt:

```php
<?php
$beallitasok = vespa_frontend_access_get_settings();
$publikus    = $beallitasok['public_page_ids'];
$landing     = $beallitasok['szabadidos_landing_page_id'];
$nonce       = wp_create_nonce('vespa_frontend_access_save');

$oldalak = get_pages(array(
    'post_status' => 'publish',
    'sort_column' => 'post_title',
));

// Figyelmeztetés, ha a beállított kezdőoldal időközben eltűnt.
$landing_hianyzik = ($landing > 0 && get_post_status($landing) !== 'publish');
?>
<div class="wrap">
    <h1>Front-end hozzáférés</h1>

    <p>
        A VESPA alapértelmezés szerint minden front-end oldalt elzár: a bejelentkezett
        látogatót a wp-adminba, a többit a bejelentkező oldalra irányítja. Az itt
        bepipált oldalak ez alól kivételt kapnak, és bárki számára elérhetők lesznek.
    </p>

    <?php if ($landing_hianyzik) : ?>
        <div class="notice notice-warning">
            <p>
                A korábban beállított szabadidős kezdőoldal már nem elérhető (törölve lett
                vagy piszkozat). A résztvevők jelenleg a kezdőlapra érkeznek.
            </p>
        </div>
    <?php endif; ?>

    <form class="ajax-form" method="post">
        <input type="hidden" name="action" value="vespa_frontend_access_save">
        <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">

        <h2>Publikusan elérhető oldalak</h2>

        <?php if (empty($oldalak)) : ?>
            <p>Nincs publikált oldal.</p>
        <?php else : ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th style="width:120px;">Publikus</th>
                        <th>Oldal</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($oldalak as $oldal) : ?>
                    <tr>
                        <td>
                            <input type="checkbox"
                                   name="public_page_ids[]"
                                   value="<?php echo esc_attr($oldal->ID); ?>"
                                   <?php checked(in_array(intval($oldal->ID), $publikus, true)); ?>>
                        </td>
                        <td><?php echo esc_html($oldal->post_title); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2>Szabadidős kezdőoldal</h2>

        <p>
            Ide érkezik a szabadidős külső résztvevő bejelentkezés után, és ide kerül
            akkor is, ha a wp-admint próbálja megnyitni. Csak olyan oldalt válassz,
            amelyet fent publikusnak is bepipáltál.
        </p>

        <select name="szabadidos_landing_page_id">
            <option value="0" <?php selected($landing, 0); ?>>— Kezdőlap —</option>
            <?php foreach ($oldalak as $oldal) : ?>
                <option value="<?php echo esc_attr($oldal->ID); ?>" <?php selected($landing, intval($oldal->ID)); ?>>
                    <?php echo esc_html($oldal->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <p style="margin-top:20px;">
            <button type="submit" class="btn btn-primary">Mentés</button>
        </p>
    </form>
</div>
```

- [ ] **Step 3: Ellenőrizd a szintaxist**

Run: `php -l includes/Admin/frontend.access.settings.php && php -l templates/frontend_access_settings.php`

Expected: mindkettőre `No syntax errors detected in ...`

- [ ] **Step 4: Ellenőrizd a felületet böngészőben**

Nyisd meg wp-adminban a bal oldali **Front-end hozzáférés** menüpontot.

Expected: megjelenik a publikált oldalak pipa-listája és a kezdőoldal-legördülő. A Mentés gomb ilyenkor még **nem** működik (az AJAX action a következő taskban készül el) — ez ebben a lépésben elvárt.

- [ ] **Step 5: Commit**

```bash
git add includes/Admin/frontend.access.settings.php templates/frontend_access_settings.php
git commit -m "feat(front-end): admin menü és beállító felület a publikus oldalakhoz"
```

---

### Task 4: AJAX mentés hurokvédelemmel

**Files:**
- Modify: `includes/Admin/frontend.access.settings.php` (bővítés a fájl végén)

**Interfaces:**
- Consumes: `vespa_frontend_access_sanitize_page_ids()` (Task 2), `vespa_load_template_with_vars()` (`vitarex-vespa-plugin.php:29`)
- Produces: `wp_ajax_vespa_frontend_access_save` action; a `vespa_frontend_access` option mentett értéke

- [ ] **Step 1: Írd meg a mentés-kezelőt**

Fűzd hozzá az `includes/Admin/frontend.access.settings.php` **végéhez**:

```php

add_action('wp_ajax_vespa_frontend_access_save', 'vespa_frontend_access_save');

function vespa_frontend_access_save()
{
    check_ajax_referer('vespa_frontend_access_save', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Jogosulatlan hozzáférés.'));
    }

    $publikus = vespa_frontend_access_sanitize_page_ids(
        isset($_POST['public_page_ids']) ? (array) $_POST['public_page_ids'] : array()
    );

    $landing = isset($_POST['szabadidos_landing_page_id'])
        ? intval($_POST['szabadidos_landing_page_id'])
        : 0;

    // Hurokvédelem: ha a kezdőoldal nem publikus, a résztvevő oda érkezne,
    // onnan viszont a redirect továbbdobná — vagyis pont azt a hurkot
    // állítanánk vissza, amit ez a funkció megszüntet. Nem engedjük elmenteni.
    if ($landing > 0 && !in_array($landing, $publikus, true)) {
        wp_send_json_error(array('errors' => array(
            'szabadidos_landing_page_id' => 'Ezt az oldalt publikusnak is be kell pipálnod, különben a résztvevők átirányítási hurokba kerülnének.',
        )));
    }

    update_option(vespa_frontend_access_option_name(), array(
        'public_page_ids'            => $publikus,
        'szabadidos_landing_page_id' => $landing,
    ));

    wp_send_json_success(array(
        'modal' => vespa_load_template_with_vars('success-modal.php', array(
            '{=TEXT=}' => 'A front-end hozzáférési beállítások elmentve.',
            '{=URL=}'  => admin_url('admin.php?page=vespa_frontend_access'),
        )),
        'modalId' => 'succesModal',
    ));
}
```

- [ ] **Step 2: Ellenőrizd a szintaxist**

Run: `php -l includes/Admin/frontend.access.settings.php`

Expected: `No syntax errors detected in includes/Admin/frontend.access.settings.php`

- [ ] **Step 3: Ellenőrizd a hurokvédelmet böngészőben**

A **Front-end hozzáférés** oldalon válaszd ki a legördülőben a nevezési oldalt, de **ne** pipáld be publikusnak, majd nyomj Mentést.

Expected: a legördülő piros keretet kap, alatta a szöveg: „Ezt az oldalt publikusnak is be kell pipálnod, különben a résztvevők átirányítási hurokba kerülnének." A beállítás **nem** mentődik el.

- [ ] **Step 4: Ellenőrizd a sikeres mentést**

Pipáld be ugyanazt az oldalt publikusnak is, majd nyomj Mentést.

Expected: felugrik a „Sikeres mentés" modal. Az oldal újratöltése után a pipa és a legördülő értéke megmarad.

- [ ] **Step 5: Commit**

```bash
git add includes/Admin/frontend.access.settings.php
git commit -m "feat(front-end): AJAX mentés hurokvédelemmel"
```

---

### Task 5: A front-end redirect bekötése

**Files:**
- Modify: `includes/Admin/login.customiser.php:22-42`

**Interfaces:**
- Consumes: `vespa_frontend_access_decide()` (Task 1), `vespa_frontend_access_public_page_ids()` (Task 2), `vespa_szabadidos_is_participant()` (`includes/Core/vespa.szabadidos.helpers.php`)
- Produces: semmi új — a meglévő `redirect_to_admin()` viselkedése változik

- [ ] **Step 1: Cseréld le a `redirect_to_admin()` metódust**

Az `includes/Admin/login.customiser.php` fájlban cseréld le a teljes `redirect_to_admin()` metódust erre:

```php
    public function redirect_to_admin(){
        if( isset($_GET['mvk_download_document']) && is_user_logged_in() ){
            return false;
        }

        if( isset($_GET['mvk_export_voluntaries']) && is_user_logged_in() ){
            return false;
        }

        if( isset($_GET['mvk_import_voluntaries']) && is_user_logged_in() ){
            return false;
        }

        $resztvevo = function_exists('vespa_szabadidos_is_participant')
            && vespa_szabadidos_is_participant();

        $dontes = vespa_frontend_access_decide(
            is_admin(),
            is_user_logged_in(),
            $resztvevo,
            is_singular() ? get_queried_object_id() : 0,
            vespa_frontend_access_public_page_ids()
        );

        if( $dontes === 'pass' ){
            return;
        }

        if( $dontes === 'admin' ){
            wp_redirect( admin_url() );
        } else {
            wp_redirect( wp_login_url() );
        }
        die();
    }
```

- [ ] **Step 2: Ellenőrizd a szintaxist**

Run: `php -l includes/Admin/login.customiser.php`

Expected: `No syntax errors detected in includes/Admin/login.customiser.php`

- [ ] **Step 3: Ellenőrizd, hogy a publikus oldal elérhető**

Pipáld be a nevezési oldalt publikusnak (ha még nem tetted), majd nyisd meg **kijelentkezve** (privát ablakban) az oldal URL-jét.

Expected: az oldal betölt, a `[vespa_szabadidos]` shortcode tartalma látszik. **Nincs** átirányítás a bejelentkező oldalra.

- [ ] **Step 4: Ellenőrizd, hogy a többi oldal továbbra is zárt**

Nyiss meg kijelentkezve egy **nem** bepipált oldalt.

Expected: átirányít a bejelentkező oldalra — a mai viselkedés változatlan.

- [ ] **Step 5: Ellenőrizd, hogy az admin felület sértetlen**

Jelentkezz be adminként, és nyisd meg a `/wp-admin/` felületet, majd néhány VESPA menüpontot (Versenyek, Törzsadatok).

Expected: minden a megszokott módon működik.

- [ ] **Step 6: Commit**

```bash
git add includes/Admin/login.customiser.php
git commit -m "feat(front-end): a redirect figyelembe veszi a publikus oldalakat"
```

---

### Task 6: A szabadidős izoláció bekötése és a hurok igazolása

**Files:**
- Modify: `includes/Admin/szabadidos.isolation.php:14-21`

**Interfaces:**
- Consumes: `vespa_frontend_access_landing_url()` (Task 2)
- Produces: semmi új — a `vespa_szabadidos_block_admin()` célja változik

- [ ] **Step 1: Cseréld le az átirányítás célját**

Az `includes/Admin/szabadidos.isolation.php` fájlban cseréld le a `vespa_szabadidos_block_admin()` függvény törzsét erre:

```php
function vespa_szabadidos_block_admin()
{
    // Az AJAX (admin-ajax.php) is admin_init-et fut; azt NEM tiltjuk, mert a
    // front-end végpontjai azon keresztül mennek.
    if (wp_doing_ajax()) {
        return;
    }
    if (is_user_logged_in() && vespa_szabadidos_is_participant()) {
        // A beállított nevezési oldalra küldjük, nem vakon a kezdőlapra.
        wp_safe_redirect(vespa_frontend_access_landing_url());
        exit;
    }
}
```

- [ ] **Step 2: Ellenőrizd a szintaxist**

Run: `php -l includes/Admin/szabadidos.isolation.php`

Expected: `No syntax errors detected in includes/Admin/szabadidos.isolation.php`

- [ ] **Step 3: Futtasd újra a unit teszteket**

Run: `php tests/test-frontend-access.php`

Expected: PASS — `Minden teszt sikeres.`, kilépési kód 0.

- [ ] **Step 4: Igazold, hogy megszűnt a hurok**

Hozz létre egy teszt szabadidős résztvevő fiókot (`szabadidos_resztvevo` szerep), jelentkezz be vele, majd:

1. Nézd meg, hova érkezel bejelentkezés után.
2. Próbáld meg megnyitni a `/wp-admin/` címet.
3. Nyiss meg egy nem publikus front-end oldalt.

Expected:
1. A beállított szabadidős kezdőoldalra érkezel.
2. A `/wp-admin/` a kezdőoldalra irányít — **egyetlen** átirányítással, `ERR_TOO_MANY_REDIRECTS` nélkül.
3. Az oldal betölt (a résztvevőt nem tereljük el), szintén hurok nélkül.

Ez a lépés a terv legfontosabb ellenőrzése: eddig ez a forgatókönyv végtelen hurokba futott.

- [ ] **Step 5: Commit**

```bash
git add includes/Admin/szabadidos.isolation.php
git commit -m "fix(szabadidos): a beállított nevezési oldalra irányítás, hurok megszüntetése"
```

---

## Záró ellenőrzőlista

Élesítés után, böngészőből lefuttatva:

- [ ] Kijelentkezve: publikusra jelölt oldal betölt; nem publikus oldal a bejelentkező oldalra irányít.
- [ ] Adminként: publikus oldal betölt, nem pattan a wp-adminba.
- [ ] Szabadidős fiókkal: belépés után a landing page jön; a wp-admin a landing page-re irányít; nincs átirányítási hurok.
- [ ] Adminként a `/wp-admin/` és a meglévő VESPA menüpontok változatlanul működnek.
- [ ] Mentés-validáció: nem publikus oldal kezdőoldalnak választása hibát ad, és nem mentődik el.
- [ ] `php tests/test-frontend-access.php` sikeres.
