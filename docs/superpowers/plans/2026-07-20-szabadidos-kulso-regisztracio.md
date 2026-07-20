# Szabadidősport külső regisztráció (A4) — Implementációs terv

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Külső, iskola/OM nélküli résztvevők önálló front-end regisztrációja, bejelentkezése, versenyszám-szintű nevezése szabadidős (type-4) versenyekre, teljes wp-admin elkülönítéssel és adatvédelmi izolációval.

**Architecture:** Teljesen elkülönített alrendszer — három új tábla (`vespa_external_participants`, `vespa_external_entries`, `vespa_szabadidos_open_contests`), új `szabadidos_resztvevo` szerep, `[vespa_szabadidos]` front-end shortcode, admin-ajax végpontok. A diák-táblákhoz (`vespa_athletes`/`vespa_athlete_entries`) nem nyúl.

**Tech Stack:** PHP (WordPress plugin, `$wpdb`, `dbDelta`, `wp_insert_user`/`wp_signon`/`wp_mail`), shortcode, vanilla JS `fetch` (a front-end nem függ jQuery-től), PhpSpreadsheet (export).

## Global Constraints

- Minden UI-szöveg és kód-komment **magyar**.
- Táblanevek `$wpdb->prefix` **nélkül** (kódbázis-konvenció): `vespa_external_participants`, `vespa_external_entries`, `vespa_szabadidos_open_contests`, `vespa_contests`, `vespa_constest_events`, `vespa_sport_events`.
- `gender` kanonikus tárolása kisbetűs: `'férfi'` / `'nő'`.
- Minden változót tartalmazó SQL `$wpdb->prepare`-en át (`%d` egész, `%s` sztring); kimenet `esc_html`/`esc_attr`/`esc_url`.
- Minden AJAX/űrlap **nonce**-szal (`check_ajax_referer('vespa_szabadidos', 'nonce')`); a publikus végpontok `wp_ajax_nopriv_` + `wp_ajax_` párban regisztrálva.
- Token összevetése `hash_equals`-szel; e-mail `is_email`; egész `intval`; szöveg `sanitize_text_field`.
- A külső szerep neve: `szabadidos_resztvevo`; a fájlok az auto-betöltött `includes/{Core,Admin,Ajax,Export}` könyvtárakba kerülnek; a shortcode `[vespa_szabadidos]`.
- Auth kizárólag WP-natívan (`wp_insert_user`/`wp_signon`) — nincs saját jelszókezelés.
- `contest_type=4` = szabadidősport (`VespaContestType::SZABADIDOS`).
- **Nincs automata teszt-suite.** Ellenőrzés: `php -l` + `grep` + manuális böngésző. Minden task „teszt" lépései ezt jelentik.
- A meglévő diák-folyamatokat és a meglévő táblák sémáját NEM módosítjuk (nincs `ALTER TABLE`).

---

## FÁZIS A — Alap (táblák, szerep, izoláció)

### Task 1: Adatbázis-installer (három új tábla)

**Files:**
- Create: `includes/Core/vespa.szabadidos.install.php`

**Interfaces:**
- Produces: a három tábla létrejötte; `vespa_szabadidos_install()` idempotens, `init`-en fut, `get_option('vespa_szabadidos_db_version')` kapuval.

- [ ] **Step 1: Az installer fájl létrehozása**

Hozd létre `includes/Core/vespa.szabadidos.install.php` tartalommal:

```php
<?php

/**
 * A szabadidős külső regisztráció három táblájának idempotens létrehozása.
 * A plugin nem használ aktivációs hookot; a séma a dumpban él, ezért — a
 * szerepekhez hasonlóan (init_custom_roles) — init-en, verzió-kapuval fut.
 */
add_action('init', 'vespa_szabadidos_install', 5);

function vespa_szabadidos_install()
{
    $telepitett = get_option('vespa_szabadidos_db_version');
    if ($telepitett === '1') {
        return;
    }

    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();

    // A táblák szándékosan $wpdb->prefix NÉLKÜL (a plugin minden vespa_* táblája így hivatkozott).
    $sql_participants = "CREATE TABLE vespa_external_participants (
  participant_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  full_name varchar(190) NOT NULL,
  birth_date date DEFAULT NULL,
  gender varchar(10) DEFAULT NULL,
  email varchar(190) NOT NULL,
  phone varchar(50) DEFAULT NULL,
  consent_at datetime DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (participant_id),
  KEY user_id (user_id)
) $charset_collate;";

    $sql_entries = "CREATE TABLE vespa_external_entries (
  entry_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  participant_id bigint(20) unsigned NOT NULL,
  contest_id bigint(20) unsigned NOT NULL,
  contest_event_id bigint(20) unsigned NOT NULL,
  entry_date datetime NOT NULL,
  PRIMARY KEY  (entry_id),
  KEY participant_id (participant_id),
  KEY contest_event_id (contest_event_id)
) $charset_collate;";

    $sql_open = "CREATE TABLE vespa_szabadidos_open_contests (
  contest_id bigint(20) unsigned NOT NULL,
  opened_at datetime NOT NULL,
  opened_by bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY  (contest_id)
) $charset_collate;";

    dbDelta($sql_participants);
    dbDelta($sql_entries);
    dbDelta($sql_open);

    update_option('vespa_szabadidos_db_version', '1');
}
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Core/vespa.szabadidos.install.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Manuális ellenőrzés (böngésző)**

Töltsd be a wp-admint egyszer (hogy az `init` lefusson), majd nézd meg a három tábla létrejöttét (pl. phpMyAdmin/Adminer): `vespa_external_participants`, `vespa_external_entries`, `vespa_szabadidos_open_contests`. Ellenőrizd, hogy a `wp_options`-ban a `vespa_szabadidos_db_version` = `1`.

- [ ] **Step 4: Commit**

```bash
git add includes/Core/vespa.szabadidos.install.php
git commit -m "feat(A4): szabadidős külső regisztráció adatbázis-installer"
```

---

### Task 2: Szerepkör + segédfüggvények + wp-admin izoláció

**Files:**
- Modify: `includes/Core/vespa_roles.php` (szerep-konstans + `$custom_roles_array` + `get_role_capabilites`)
- Create: `includes/Core/vespa.szabadidos.helpers.php`
- Create: `includes/Admin/szabadidos.isolation.php`

**Interfaces:**
- Produces: `VESPA_Roles::SZABADIDOS_RESZTVEVO` (= `"szabadidos_resztvevo"`); `vespa_szabadidos_is_participant($user_id = null): bool`; `vespa_szabadidos_get_participant_by_user($user_id): ?object`; `vespa_szabadidos_current_participant(): ?object`. Ezekre a 3–8. taskok építenek.

- [ ] **Step 1: Szerep-konstans hozzáadása**

`includes/Core/vespa_roles.php` — a szerep-konstansok blokkjában (a `const ADMINISZTRATOR = "adminisztrator";` sor UTÁN) add hozzá:

```php
    const SZABADIDOS_RESZTVEVO        = "szabadidos_resztvevo";
```

- [ ] **Step 2: A szerep felvétele a `$custom_roles_array`-be**

Ugyanebben a fájlban a `$custom_roles_array` tömb utolsó eleme után (a `[VESPA_Roles::TANKERULETI_IGAZGATO, "Tankerületi igazgató"]` UTÁN, vesszővel) vedd fel:

```php
        , [VESPA_Roles::SZABADIDOS_RESZTVEVO, "Szabadidős résztvevő"]
```

- [ ] **Step 3: Üres capability-halmaz a szerephez**

Ugyanebben a fájlban a `get_role_capabilites()` `return`-ölt tömbjében, a `VESPA_Roles::ADMINISZTRATOR => array(),` sor UTÁN add hozzá:

```php
                    VESPA_Roles::SZABADIDOS_RESZTVEVO => array(),
```

(A külső résztvevőnek NINCS admin-capabilityje; a front-end hozzáférést a saját szerep-ellenőrzéseink adják.)

- [ ] **Step 4: Segédfüggvények**

Hozd létre `includes/Core/vespa.szabadidos.helpers.php`:

```php
<?php

/**
 * Igaz, ha a felhasználó szabadidős külső résztvevő szerepű.
 * $user_id = null esetén a bejelentkezett felhasználót vizsgálja.
 */
function vespa_szabadidos_is_participant($user_id = null)
{
    $user = $user_id === null ? wp_get_current_user() : get_user_by('id', $user_id);
    if (!$user || !$user->exists()) {
        return false;
    }
    return in_array(VESPA_Roles::SZABADIDOS_RESZTVEVO, (array) $user->roles, true);
}

/**
 * A megadott WP-felhasználóhoz tartozó külső-résztvevő sor, vagy null.
 */
function vespa_szabadidos_get_participant_by_user($user_id)
{
    global $wpdb;
    if (!is_numeric($user_id) || intval($user_id) <= 0) {
        return null;
    }
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM vespa_external_participants WHERE user_id=%d",
        intval($user_id)
    ));
    return $row ? $row : null;
}

/**
 * A bejelentkezett felhasználóhoz tartozó külső-résztvevő sor, vagy null.
 */
function vespa_szabadidos_current_participant()
{
    if (!is_user_logged_in()) {
        return null;
    }
    return vespa_szabadidos_get_participant_by_user(get_current_user_id());
}
```

- [ ] **Step 5: wp-admin izoláció**

Hozd létre `includes/Admin/szabadidos.isolation.php`:

```php
<?php

/**
 * A külső résztvevő KIZÁRÓLAG a front-end nevezési felületet láthatja:
 * a wp-admin bármely oldalának megnyitása visszairányít a nyitóoldalra,
 * és a felső admin-sáv sem jelenik meg neki.
 */
add_action('admin_init', 'vespa_szabadidos_block_admin');

function vespa_szabadidos_block_admin()
{
    // Az AJAX (admin-ajax.php) is admin_init-et fut; azt NEM tiltjuk, mert a
    // front-end végpontjai azon keresztül mennek.
    if (wp_doing_ajax()) {
        return;
    }
    if (is_user_logged_in() && vespa_szabadidos_is_participant()) {
        wp_safe_redirect(home_url('/'));
        exit;
    }
}

add_filter('show_admin_bar', 'vespa_szabadidos_hide_admin_bar');

function vespa_szabadidos_hide_admin_bar($show)
{
    if (is_user_logged_in() && vespa_szabadidos_is_participant()) {
        return false;
    }
    return $show;
}
```

- [ ] **Step 6: Szintaxis-ellenőrzés**

Run: `php -l includes/Core/vespa_roles.php && php -l includes/Core/vespa.szabadidos.helpers.php && php -l includes/Admin/szabadidos.isolation.php`
Expected: mindháromra `No syntax errors detected`

- [ ] **Step 7: Grep-ellenőrzés**

Run: `grep -n "SZABADIDOS_RESZTVEVO\|szabadidos_resztvevo" includes/Core/vespa_roles.php`
Expected: a konstans, a `$custom_roles_array` elem és a capability-sor is megjelenik.

- [ ] **Step 8: Manuális ellenőrzés**

Töltsd be a wp-admint (init lefut → a szerep létrejön). A WP „Felhasználók → Új" felületén a szerepek között megjelenik a „Szabadidős résztvevő".

- [ ] **Step 9: Commit**

```bash
git add includes/Core/vespa_roles.php includes/Core/vespa.szabadidos.helpers.php includes/Admin/szabadidos.isolation.php
git commit -m "feat(A4): szabadidős résztvevő szerep, helperek, wp-admin izoláció"
```

---

## FÁZIS B — Regisztráció, megerősítés, belépés

### Task 3: Regisztráció (nopriv AJAX) + e-mail megerősítő link

**Files:**
- Create: `includes/Ajax/szabadidos.auth.php`

**Interfaces:**
- Consumes: `vespa_validate_email`/`vespa_validate_phone` (functions.php), `vespa_szabadidos_is_participant`.
- Produces: `wp_ajax_nopriv_vespa_szabadidos_register` / `wp_ajax_vespa_szabadidos_register`. Létrehoz egy WP-felhasználót `szabadidos_resztvevo` szereppel + egy `vespa_external_participants` sort; `user_meta`: `vespa_szabadidos_confirmed=0`, `vespa_szabadidos_confirm_token=<32 hex>`; e-mailben megerősítő link. A 4. task a `confirm`/`login`/`authenticate` részt teszi ugyanebbe a fájlba.

- [ ] **Step 1: A regisztrációs végpont fájl létrehozása**

Hozd létre `includes/Ajax/szabadidos.auth.php`:

```php
<?php

add_action('wp_ajax_nopriv_vespa_szabadidos_register', 'vespa_szabadidos_register');
add_action('wp_ajax_vespa_szabadidos_register', 'vespa_szabadidos_register');

function vespa_szabadidos_register()
{
    check_ajax_referer('vespa_szabadidos', 'nonce');

    $nev      = isset($_POST['full_name']) ? sanitize_text_field(wp_unslash($_POST['full_name'])) : '';
    $szul     = isset($_POST['birth_date']) ? sanitize_text_field($_POST['birth_date']) : '';
    $nem_raw  = isset($_POST['gender']) ? sanitize_text_field($_POST['gender']) : '';
    $email    = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $tel      = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $jelszo   = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $jelszo2  = isset($_POST['password2']) ? (string) $_POST['password2'] : '';
    $consent  = isset($_POST['consent']) && $_POST['consent'] === '1';

    // A nem kisbetűs, kanonikus tárolása.
    $nem = null;
    if ($nem_raw === 'férfi' || $nem_raw === 'nő') {
        $nem = $nem_raw;
    }

    $hibak = array();
    if ($nev === '') {
        $hibak[] = 'A név megadása kötelező.';
    }
    if ($szul === '' || !DateTime::createFromFormat('Y-m-d', $szul)) {
        $hibak[] = 'Érvényes születési dátum megadása kötelező (ÉÉÉÉ-HH-NN).';
    }
    if ($nem === null) {
        $hibak[] = 'A nem megadása kötelező.';
    }
    if (!$email || !is_email($email)) {
        $hibak[] = 'Érvényes e-mail cím megadása kötelező.';
    } elseif (email_exists($email)) {
        $hibak[] = 'Ezzel az e-mail címmel már regisztráltak.';
    }
    if (!vespa_validate_phone($tel)) {
        $hibak[] = 'Érvényes telefonszám megadása kötelező.';
    }
    if (strlen($jelszo) < 8) {
        $hibak[] = 'A jelszó legalább 8 karakter legyen.';
    }
    if ($jelszo !== $jelszo2) {
        $hibak[] = 'A két jelszó nem egyezik.';
    }
    if (!$consent) {
        $hibak[] = 'Az adatkezelési hozzájárulás elfogadása kötelező.';
    }

    if (!empty($hibak)) {
        wp_send_json_error(array('message' => implode(' ', $hibak)));
    }

    // WP-felhasználó — kizárólag a külső szereppel.
    $user_id = wp_insert_user(array(
        'user_login' => $email,
        'user_email' => $email,
        'user_pass'  => $jelszo,
        'display_name' => $nev,
        'role'       => VESPA_Roles::SZABADIDOS_RESZTVEVO,
    ));
    if (is_wp_error($user_id)) {
        wp_send_json_error(array('message' => 'A regisztráció nem sikerült: ' . esc_html($user_id->get_error_message())));
    }

    $token = wp_generate_password(32, false);
    update_user_meta($user_id, 'vespa_szabadidos_confirmed', '0');
    update_user_meta($user_id, 'vespa_szabadidos_confirm_token', $token);

    global $wpdb;
    $wpdb->insert('vespa_external_participants', array(
        'user_id'    => $user_id,
        'full_name'  => $nev,
        'birth_date' => $szul,
        'gender'     => $nem,
        'email'      => $email,
        'phone'      => $tel,
        'consent_at' => current_time('mysql'),
        'created_at' => current_time('mysql'),
    ));

    // Megerősítő link.
    $link = add_query_arg(array(
        'vespa_szabadidos_confirm' => $token,
        'uid' => $user_id,
    ), home_url('/'));

    $targy = 'Szabadidős regisztráció megerősítése';
    $body  = "Kedves " . esc_html($nev) . "!\n\n"
        . "Köszönjük a regisztrációt. A fiók aktiválásához kattints az alábbi megerősítő linkre:\n"
        . esc_url_raw($link) . "\n\n"
        . "Ha nem te regisztráltál, hagyd figyelmen kívül ezt az üzenetet.";
    wp_mail($email, $targy, $body);

    wp_send_json_success(array('message' => 'Elküldtük a megerősítő e-mailt. Kérjük, erősítsd meg a fiókodat a levélben található linkkel.'));
}
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Ajax/szabadidos.auth.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Grep-ellenőrzés (biztonság)**

Run: `grep -n "check_ajax_referer\|email_exists\|is_email\|wp_insert_user\|wp_generate_password\|wp_send_json" includes/Ajax/szabadidos.auth.php`
Expected: nonce-ellenőrzés, e-mail-egyediség, felhasználó-létrehozás, token, JSON-válasz mind jelen.

- [ ] **Step 4: Commit**

```bash
git add includes/Ajax/szabadidos.auth.php
git commit -m "feat(A4): külső résztvevő regisztráció + megerősítő e-mail"
```

---

### Task 4: E-mail megerősítés + belépés-gátlás + belépés (AJAX)

**Files:**
- Modify: `includes/Ajax/szabadidos.auth.php` (a fájl végére, a `vespa_szabadidos_register()` UTÁN)

**Interfaces:**
- Consumes: a Task 3 által beállított `vespa_szabadidos_confirm_token` / `vespa_szabadidos_confirmed` user_meta; `VESPA_Roles::SZABADIDOS_RESZTVEVO`.
- Produces: `init`-en futó megerősítés-kezelő (`?vespa_szabadidos_confirm=<token>&uid=<id>`); `authenticate` szűrő, amely meg nem erősített külső fiók belépését blokkolja; `wp_ajax_nopriv_vespa_szabadidos_login` végpont (`wp_signon`).

- [ ] **Step 1: Megerősítés-kezelő, belépés-gátló szűrő és belépés-végpont hozzáadása**

Illeszd be `includes/Ajax/szabadidos.auth.php` VÉGÉRE (a `vespa_szabadidos_register()` függvény záró `}`-e után):

```php

/**
 * Megerősítő link kezelése: a token egyeztetése a uid-hoz.
 */
add_action('init', 'vespa_szabadidos_confirm', 20);

function vespa_szabadidos_confirm()
{
    if (!isset($_GET['vespa_szabadidos_confirm']) || !isset($_GET['uid'])) {
        return;
    }
    $token = sanitize_text_field(wp_unslash($_GET['vespa_szabadidos_confirm']));
    $uid   = intval($_GET['uid']);
    if ($uid <= 0 || $token === '') {
        return;
    }

    $tarolt = get_user_meta($uid, 'vespa_szabadidos_confirm_token', true);
    if (!empty($tarolt) && hash_equals($tarolt, $token) && vespa_szabadidos_is_participant($uid)) {
        update_user_meta($uid, 'vespa_szabadidos_confirmed', '1');
        delete_user_meta($uid, 'vespa_szabadidos_confirm_token');
        wp_safe_redirect(add_query_arg('vespa_szabadidos_confirmed', '1', home_url('/')));
        exit;
    }

    wp_safe_redirect(add_query_arg('vespa_szabadidos_confirmed', '0', home_url('/')));
    exit;
}

/**
 * Meg nem erősített külső fiók nem léphet be.
 */
add_filter('authenticate', 'vespa_szabadidos_block_unconfirmed', 30, 3);

function vespa_szabadidos_block_unconfirmed($user, $username, $password)
{
    if ($user instanceof WP_User && in_array(VESPA_Roles::SZABADIDOS_RESZTVEVO, (array) $user->roles, true)) {
        if (get_user_meta($user->ID, 'vespa_szabadidos_confirmed', true) !== '1') {
            return new WP_Error('vespa_szabadidos_unconfirmed', 'Előbb erősítsd meg a fiókodat az e-mailben kapott linkkel.');
        }
    }
    return $user;
}

/**
 * Front-end belépés (nopriv AJAX).
 */
add_action('wp_ajax_nopriv_vespa_szabadidos_login', 'vespa_szabadidos_login');

function vespa_szabadidos_login()
{
    check_ajax_referer('vespa_szabadidos', 'nonce');

    $email  = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $jelszo = isset($_POST['password']) ? (string) $_POST['password'] : '';

    if (!$email || $jelszo === '') {
        wp_send_json_error(array('message' => 'Add meg az e-mail címet és a jelszót.'));
    }

    $user = wp_signon(array(
        'user_login'    => $email,
        'user_password' => $jelszo,
        'remember'      => true,
    ), is_ssl());

    if (is_wp_error($user)) {
        wp_send_json_error(array('message' => esc_html($user->get_error_message())));
    }

    wp_send_json_success(array('message' => 'Sikeres belépés.'));
}
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Ajax/szabadidos.auth.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Grep-ellenőrzés**

Run: `grep -n "hash_equals\|authenticate\|wp_signon\|vespa_szabadidos_confirmed\|WP_Error" includes/Ajax/szabadidos.auth.php`
Expected: token-egyeztetés, authenticate-szűrő, wp_signon, megerősítés-flag, WP_Error mind jelen.

- [ ] **Step 4: Commit**

```bash
git add includes/Ajax/szabadidos.auth.php
git commit -m "feat(A4): e-mail megerősítés, belépés-gátlás, front-end belépés"
```

---

## FÁZIS C — Front-end felület

### Task 5: Shortcode + teljes front-end sablon (regisztráció/belépés + saját nézet)

**Files:**
- Create: `includes/Core/vespa.szabadidos.frontend.php` (shortcode-regisztráció + render)
- Create: `templates/szabadidos_frontend.php` (markup + JS)

**Interfaces:**
- Consumes: `vespa_szabadidos_is_participant`, `vespa_szabadidos_current_participant`, a Task 3/4 AJAX-végpontok, a Task 6 által biztosított `vespa_szabadidos_signup`/`vespa_szabadidos_withdraw` végpontok (a JS ezeket hívja).
- Produces: `[vespa_szabadidos]` shortcode. Kijelentkezve: Regisztráció + Belépés űrlap; belépve (külső szerep): saját nézet (megnyitott type-4 versenyek versenyszámokkal + „Nevezek", és a saját nevezéseim + „Visszavonás"). A nevezés/visszavonás gombok a Task 6 végpontjait hívják.

- [ ] **Step 1: Shortcode-regisztráció**

Hozd létre `includes/Core/vespa.szabadidos.frontend.php`:

```php
<?php

add_shortcode('vespa_szabadidos', 'vespa_szabadidos_shortcode');

function vespa_szabadidos_shortcode()
{
    ob_start();
    require VITAREX_VESPA_PLUGIN_DIR . '/templates/szabadidos_frontend.php';
    return ob_get_clean();
}
```

- [ ] **Step 2: A front-end sablon létrehozása**

Hozd létre `templates/szabadidos_frontend.php`. A sablon a bejelentkezés-állapottól függően renderel. A `vitarex_vespa_ajaxurl` JS-változó a `wp_head`-en már elérhető (a plugin bootstrapje adja).

```php
<?php
global $wpdb;
$nonce = wp_create_nonce('vespa_szabadidos');
$bejelentkezve = is_user_logged_in();
$kulso = $bejelentkezve && vespa_szabadidos_is_participant();
?>
<div class="vespa-szabadidos" data-nonce="<?php echo esc_attr($nonce); ?>">

<?php if (!$bejelentkezve) : ?>

    <?php if (isset($_GET['vespa_szabadidos_confirmed'])) : ?>
        <?php if ($_GET['vespa_szabadidos_confirmed'] === '1') : ?>
            <p class="vespa-szabadidos-uzenet ok">A fiókod megerősítve. Most már beléphetsz.</p>
        <?php else : ?>
            <p class="vespa-szabadidos-uzenet hiba">Érvénytelen vagy lejárt megerősítő link.</p>
        <?php endif; ?>
    <?php endif; ?>

    <h2>Belépés</h2>
    <form id="vespa-szabadidos-login">
        <label>E-mail <input type="email" name="email" required></label>
        <label>Jelszó <input type="password" name="password" required></label>
        <button type="submit">Belépés</button>
        <a href="<?php echo esc_url(wp_lostpassword_url()); ?>">Elfelejtett jelszó</a>
    </form>
    <div class="vespa-szabadidos-login-uzenet"></div>

    <h2>Regisztráció</h2>
    <form id="vespa-szabadidos-register">
        <label>Teljes név <input type="text" name="full_name" required></label>
        <label>Születési dátum <input type="date" name="birth_date" required></label>
        <label>Nem
            <select name="gender" required>
                <option value="">—</option>
                <option value="férfi">férfi</option>
                <option value="nő">nő</option>
            </select>
        </label>
        <label>E-mail <input type="email" name="email" required></label>
        <label>Telefonszám <input type="text" name="phone" required></label>
        <label>Jelszó <input type="password" name="password" required></label>
        <label>Jelszó újra <input type="password" name="password2" required></label>
        <label><input type="checkbox" name="consent" value="1" required> Elfogadom az adatkezelési tájékoztatót</label>
        <button type="submit">Regisztráció</button>
    </form>
    <div class="vespa-szabadidos-register-uzenet"></div>

<?php elseif (!$kulso) : ?>

    <p class="vespa-szabadidos-uzenet">Ez a felület a szabadidős külső résztvevőké. A jelenlegi fiókod nem külső résztvevő.</p>

<?php else : ?>

    <?php
    $resztvevo = vespa_szabadidos_current_participant();

    // Megnyitott, nem lejárt type-4 versenyek.
    $versenyek = $wpdb->get_results($wpdb->prepare(
        "SELECT vc.contest_id, vc.contest_name
         FROM vespa_szabadidos_open_contests AS o
         INNER JOIN vespa_contests AS vc ON vc.contest_id=o.contest_id
         WHERE vc.contest_type=%d AND vc.end_at >= %s
         ORDER BY vc.start_at ASC",
        4,
        current_time('mysql')
    ));

    // A saját nevezéseim (participant_id szerint — adatvédelmi izoláció).
    $sajat = array();
    if ($resztvevo) {
        $sajat = $wpdb->get_results($wpdb->prepare(
            "SELECT e.entry_id, e.contest_id, e.contest_event_id, vc.contest_name, vse.sport_event_name, vs.sport_name
             FROM vespa_external_entries AS e
             INNER JOIN vespa_contests AS vc ON vc.contest_id=e.contest_id
             LEFT JOIN vespa_constest_events AS vce ON vce.id=e.contest_event_id
             LEFT JOIN vespa_sport_events AS vse ON vse.sport_event_id=vce.event_id
             LEFT JOIN vespa_sports AS vs ON vs.sport_id=vce.sport_id
             WHERE e.participant_id=%d
             ORDER BY e.entry_date DESC",
            $resztvevo->participant_id
        ));
    }
    // A saját nevezett contest_event_id-k (a gombok elrejtéséhez).
    $sajat_event_idk = array_map(function ($s) { return intval($s->contest_event_id); }, $sajat);
    ?>

    <h2>Üdv, <?php echo esc_html($resztvevo ? $resztvevo->full_name : ''); ?>!</h2>
    <p><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Kilépés</a></p>

    <h3>Megnyitott szabadidős versenyek</h3>
    <?php if (empty($versenyek)) : ?>
        <p>Jelenleg nincs elérhető szabadidős verseny.</p>
    <?php else : ?>
        <?php foreach ($versenyek as $v) : ?>
            <div class="vespa-szabadidos-verseny">
                <h4><?php echo esc_html($v->contest_name); ?></h4>
                <?php
                $esemenyek = $wpdb->get_results($wpdb->prepare(
                    "SELECT vce.id AS contest_event_id, vse.sport_event_name, vs.sport_name
                     FROM vespa_constest_events AS vce
                     LEFT JOIN vespa_sport_events AS vse ON vse.sport_event_id=vce.event_id
                     LEFT JOIN vespa_sports AS vs ON vs.sport_id=vce.sport_id
                     WHERE vce.contest_id=%d
                     ORDER BY vs.sport_name, vse.sport_event_name",
                    $v->contest_id
                ));
                ?>
                <?php if (empty($esemenyek)) : ?>
                    <p>Ehhez a versenyhez még nincs versenyszám.</p>
                <?php else : ?>
                    <ul>
                    <?php foreach ($esemenyek as $e) : ?>
                        <li>
                            <?php echo esc_html(trim(($e->sport_name ?: '') . ' – ' . ($e->sport_event_name ?: ''), ' –')); ?>
                            <?php if (in_array(intval($e->contest_event_id), $sajat_event_idk, true)) : ?>
                                <span class="vespa-szabadidos-nevezve">(nevezve)</span>
                            <?php else : ?>
                                <button type="button" class="vespa-szabadidos-nevez"
                                        data-contest="<?php echo esc_attr($v->contest_id); ?>"
                                        data-event="<?php echo esc_attr($e->contest_event_id); ?>">Nevezek</button>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h3>Nevezéseim</h3>
    <?php if (empty($sajat)) : ?>
        <p>Még nincs nevezésed.</p>
    <?php else : ?>
        <ul>
        <?php foreach ($sajat as $s) : ?>
            <li>
                <?php echo esc_html($s->contest_name . ' — ' . trim(($s->sport_name ?: '') . ' ' . ($s->sport_event_name ?: ''))); ?>
                <button type="button" class="vespa-szabadidos-visszavon" data-entry="<?php echo esc_attr($s->entry_id); ?>">Visszavonás</button>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>

<?php endif; ?>

</div>

<script>
(function () {
    var gyoker = document.querySelector('.vespa-szabadidos');
    if (!gyoker) return;
    var nonce = gyoker.getAttribute('data-nonce');
    var url = (typeof vitarex_vespa_ajaxurl !== 'undefined') ? vitarex_vespa_ajaxurl : '/wp-admin/admin-ajax.php';

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

    var regForm = document.getElementById('vespa-szabadidos-register');
    if (regForm) {
        regForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var f = regForm;
            kuld('vespa_szabadidos_register', {
                full_name: f.full_name.value, birth_date: f.birth_date.value, gender: f.gender.value,
                email: f.email.value, phone: f.phone.value, password: f.password.value,
                password2: f.password2.value, consent: f.consent.checked ? '1' : '0'
            }, function (resp) {
                document.querySelector('.vespa-szabadidos-register-uzenet').textContent = resp.data.message;
                if (resp.success) regForm.reset();
            });
        });
    }

    var loginForm = document.getElementById('vespa-szabadidos-login');
    if (loginForm) {
        loginForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            kuld('vespa_szabadidos_login', { email: loginForm.email.value, password: loginForm.password.value }, function (resp) {
                document.querySelector('.vespa-szabadidos-login-uzenet').textContent = resp.data.message;
                if (resp.success) location.reload();
            });
        });
    }

    document.querySelectorAll('.vespa-szabadidos-nevez').forEach(function (b) {
        b.addEventListener('click', function () {
            kuld('vespa_szabadidos_signup', { contest_id: b.getAttribute('data-contest'), contest_event_id: b.getAttribute('data-event') }, function (resp) {
                alert(resp.data.message);
                if (resp.success) location.reload();
            });
        });
    });

    document.querySelectorAll('.vespa-szabadidos-visszavon').forEach(function (b) {
        b.addEventListener('click', function () {
            kuld('vespa_szabadidos_withdraw', { entry_id: b.getAttribute('data-entry') }, function (resp) {
                alert(resp.data.message);
                if (resp.success) location.reload();
            });
        });
    });
})();
</script>
```

- [ ] **Step 3: Szintaxis-ellenőrzés**

Run: `php -l includes/Core/vespa.szabadidos.frontend.php && php -l templates/szabadidos_frontend.php`
Expected: mindkettőre `No syntax errors detected`

- [ ] **Step 4: Grep-ellenőrzés**

Run: `grep -n "add_shortcode\|vespa_szabadidos_signup\|vespa_szabadidos_withdraw\|data-nonce\|participant_id=%d" includes/Core/vespa.szabadidos.frontend.php templates/szabadidos_frontend.php`
Expected: a shortcode, a JS-végponthívások, a nonce-attribútum és a saját-nevezés izoláció (`participant_id=%d`) jelen.

- [ ] **Step 5: Manuális ellenőrzés (böngésző)**

Hozz létre egy WP-oldalt a `[vespa_szabadidos]` shortcode-dal. Kijelentkezve: a Regisztráció és Belépés űrlap látszik. Regisztrálj → „Elküldtük a megerősítő e-mailt" üzenet; megerősítés előtt belépve → „Előbb erősítsd meg…"; megerősítés után belépve → a saját nézet. (A nevezés/visszavonás gombok a Task 6 után működnek.)

- [ ] **Step 6: Commit**

```bash
git add includes/Core/vespa.szabadidos.frontend.php templates/szabadidos_frontend.php
git commit -m "feat(A4): [vespa_szabadidos] front-end (regisztráció/belépés + saját nézet)"
```

---

### Task 6: Nevezés + visszavonás végpontok (bejelentkezett AJAX)

**Files:**
- Create: `includes/Ajax/szabadidos.entries.php`

**Interfaces:**
- Consumes: `vespa_szabadidos_is_participant`, `vespa_szabadidos_current_participant`; a Task 5 front-end JS ezeket a végpontokat hívja (`contest_id`, `contest_event_id`, `entry_id` paraméterekkel).
- Produces: `wp_ajax_vespa_szabadidos_signup`, `wp_ajax_vespa_szabadidos_withdraw` (csak bejelentkezett, csak külső szerep). Nevezés a `vespa_external_entries`-be a saját `participant_id`-vel; visszavonás kizárólag a saját `entry_id`-re (IDOR-védelem).

- [ ] **Step 1: A végpont fájl létrehozása**

Hozd létre `includes/Ajax/szabadidos.entries.php`:

```php
<?php

add_action('wp_ajax_vespa_szabadidos_signup', 'vespa_szabadidos_signup');

function vespa_szabadidos_signup()
{
    check_ajax_referer('vespa_szabadidos', 'nonce');

    if (!vespa_szabadidos_is_participant()) {
        wp_send_json_error(array('message' => 'Jogosulatlan hozzáférés.'));
    }
    $resztvevo = vespa_szabadidos_current_participant();
    if (!$resztvevo) {
        wp_send_json_error(array('message' => 'Hiányzó résztvevő-profil.'));
    }

    $contest_id = isset($_POST['contest_id']) ? intval($_POST['contest_id']) : 0;
    $event_id   = isset($_POST['contest_event_id']) ? intval($_POST['contest_event_id']) : 0;
    if ($contest_id <= 0 || $event_id <= 0) {
        wp_send_json_error(array('message' => 'Hibás nevezési adatok.'));
    }

    global $wpdb;

    // A verseny type-4, megnyitott és nem lejárt.
    $verseny = $wpdb->get_row($wpdb->prepare(
        "SELECT vc.contest_id, vc.ppl_num_max
         FROM vespa_szabadidos_open_contests AS o
         INNER JOIN vespa_contests AS vc ON vc.contest_id=o.contest_id
         WHERE o.contest_id=%d AND vc.contest_type=%d AND vc.end_at >= %s",
        $contest_id,
        4,
        current_time('mysql')
    ));
    if (!$verseny) {
        wp_send_json_error(array('message' => 'Erre a versenyre jelenleg nem lehet nevezni.'));
    }

    // A versenyszám valóban ehhez a versenyhez tartozik.
    $esemeny_ok = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM vespa_constest_events WHERE id=%d AND contest_id=%d",
        $event_id,
        $contest_id
    ));
    if (intval($esemeny_ok) === 0) {
        wp_send_json_error(array('message' => 'Érvénytelen versenyszám.'));
    }

    // Dupla nevezés kizárása ugyanarra a versenyszámra.
    $mar_van = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM vespa_external_entries WHERE participant_id=%d AND contest_event_id=%d",
        $resztvevo->participant_id,
        $event_id
    ));
    if (intval($mar_van) > 0) {
        wp_send_json_error(array('message' => 'Erre a versenyszámra már neveztél.'));
    }

    // Globális létszámkeret (type-4): a verseny összes külső nevezése vs ppl_num_max.
    if (is_numeric($verseny->ppl_num_max) && intval($verseny->ppl_num_max) > 0) {
        $jelenlegi = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM vespa_external_entries WHERE contest_id=%d",
            $contest_id
        ));
        if (intval($jelenlegi) >= intval($verseny->ppl_num_max)) {
            wp_send_json_error(array('message' => 'A helyek száma betelt.'));
        }
    }

    $wpdb->insert('vespa_external_entries', array(
        'participant_id'   => $resztvevo->participant_id,
        'contest_id'       => $contest_id,
        'contest_event_id' => $event_id,
        'entry_date'       => current_time('mysql'),
    ));

    wp_send_json_success(array('message' => 'Sikeres nevezés.'));
}

add_action('wp_ajax_vespa_szabadidos_withdraw', 'vespa_szabadidos_withdraw');

function vespa_szabadidos_withdraw()
{
    check_ajax_referer('vespa_szabadidos', 'nonce');

    if (!vespa_szabadidos_is_participant()) {
        wp_send_json_error(array('message' => 'Jogosulatlan hozzáférés.'));
    }
    $resztvevo = vespa_szabadidos_current_participant();
    if (!$resztvevo) {
        wp_send_json_error(array('message' => 'Hiányzó résztvevő-profil.'));
    }

    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    if ($entry_id <= 0) {
        wp_send_json_error(array('message' => 'Hibás nevezés-azonosító.'));
    }

    global $wpdb;
    // IDOR-védelem: kizárólag a SAJÁT participant_id-hoz tartozó sor törölhető.
    $torolt = $wpdb->delete('vespa_external_entries', array(
        'entry_id'       => $entry_id,
        'participant_id' => $resztvevo->participant_id,
    ), array('%d', '%d'));

    if ($torolt) {
        wp_send_json_success(array('message' => 'A nevezést visszavontuk.'));
    }
    wp_send_json_error(array('message' => 'A nevezés nem található.'));
}
```

- [ ] **Step 2: Szintaxis-ellenőrzés**

Run: `php -l includes/Ajax/szabadidos.entries.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Grep-ellenőrzés (izoláció/biztonság)**

Run: `grep -n "check_ajax_referer\|vespa_szabadidos_is_participant\|participant_id=%d\|participant_id.*=> \$resztvevo->participant_id\|ppl_num_max" includes/Ajax/szabadidos.entries.php`
Expected: nonce, szerep-ellenőrzés, a saját participant_id szerinti szűrés/beszúrás, a keret-ellenőrzés mind jelen.

- [ ] **Step 4: Manuális ellenőrzés (böngésző)**

Belépve a saját nézeten: „Nevezek" egy versenyszámra → sikeres, a gomb „(nevezve)"-re vált; ugyanarra újra → „Erre a versenyszámra már neveztél."; „Visszavonás" → eltűnik. Egy másik böngészőben/másik fiókkal nézve más nevezését nem látod.

- [ ] **Step 5: Commit**

```bash
git add includes/Ajax/szabadidos.entries.php
git commit -m "feat(A4): külső nevezés + visszavonás végpontok (IDOR-védelemmel)"
```

---

## FÁZIS D — Admin-oldal

### Task 7: Admin menü + verseny nyitás/zárás

**Files:**
- Create: `includes/Admin/szabadidos.admin.php` (menü + toggle AJAX)
- Create: `templates/szabadidos_admin.php` (markup)

**Interfaces:**
- Consumes: `VESPA_Roles::riportalas` cap; `vespa_szabadidos_open_contests` tábla.
- Produces: „Szabadidős külső nevezés" admin-menü (`riportalas` mögött); `wp_ajax_vespa_szabadidos_toggle_open` végpont (sor be/kivétele). A 8. task ugyanezt a sablont bővíti a nevezők listájával/exporttal.

- [ ] **Step 1: Az admin-menü és a toggle-végpont**

Hozd létre `includes/Admin/szabadidos.admin.php`:

```php
<?php

add_action('admin_menu', 'vespa_szabadidos_admin_menu');

function vespa_szabadidos_admin_menu()
{
    add_menu_page(
        'Szabadidős külső nevezés',
        'Szabadidős külső nevezés',
        VESPA_Roles::riportalas,
        'szabadidos_kulso',
        'vespa_szabadidos_admin_page',
        'dashicons-groups',
        5
    );
}

function vespa_szabadidos_admin_page()
{
    if (!current_user_can(VESPA_Roles::riportalas)) {
        echo 'Nincs megfelelő jogosultságod az oldal megtekintéséhez.';
        return;
    }
    vespa_load_template('szabadidos_admin.php');
}

add_action('wp_ajax_vespa_szabadidos_toggle_open', 'vespa_szabadidos_toggle_open');

function vespa_szabadidos_toggle_open()
{
    check_ajax_referer('vespa_szabadidos_admin', 'nonce');
    if (!current_user_can(VESPA_Roles::riportalas)) {
        wp_send_json_error(array('message' => 'Jogosulatlan hozzáférés.'));
    }

    $contest_id = isset($_POST['contest_id']) ? intval($_POST['contest_id']) : 0;
    $nyit = isset($_POST['open']) && $_POST['open'] === '1';
    if ($contest_id <= 0) {
        wp_send_json_error(array('message' => 'Hibás verseny.'));
    }

    global $wpdb;
    // Csak valódi type-4 versenyre engedjük a nyitást.
    $type = $wpdb->get_var($wpdb->prepare("SELECT contest_type FROM vespa_contests WHERE contest_id=%d", $contest_id));
    if (intval($type) !== 4) {
        wp_send_json_error(array('message' => 'Csak szabadidős verseny nyitható meg.'));
    }

    if ($nyit) {
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO vespa_szabadidos_open_contests (contest_id, opened_at, opened_by) VALUES (%d, %s, %d)",
            $contest_id,
            current_time('mysql'),
            get_current_user_id()
        ));
        wp_send_json_success(array('message' => 'Megnyitva a külső regisztrációra.'));
    }

    $wpdb->delete('vespa_szabadidos_open_contests', array('contest_id' => $contest_id), array('%d'));
    wp_send_json_success(array('message' => 'Lezárva a külső regisztráció.'));
}
```

- [ ] **Step 2: Az admin-sablon (nyitás/zárás lista)**

Hozd létre `templates/szabadidos_admin.php`:

```php
<?php
global $wpdb;
$admin_nonce = wp_create_nonce('vespa_szabadidos_admin');

$versenyek = $wpdb->get_results($wpdb->prepare(
    "SELECT vc.contest_id, vc.contest_name,
            (SELECT COUNT(*) FROM vespa_szabadidos_open_contests o WHERE o.contest_id=vc.contest_id) AS nyitva
     FROM vespa_contests AS vc
     WHERE vc.contest_type=%d
     ORDER BY vc.start_at DESC",
    4
));
?>
<div class="wrap" data-admin-nonce="<?php echo esc_attr($admin_nonce); ?>">
    <h1>Szabadidős külső nevezés</h1>

    <h2>Versenyek megnyitása külső regisztrációra</h2>
    <?php if (empty($versenyek)) : ?>
        <p>Nincs szabadidős (type-4) verseny.</p>
    <?php else : ?>
        <table class="widefat">
            <thead><tr><th>Verseny</th><th>Külső regisztráció</th></tr></thead>
            <tbody>
            <?php foreach ($versenyek as $v) : ?>
                <tr>
                    <td><?php echo esc_html($v->contest_name); ?></td>
                    <td>
                        <label>
                            <input type="checkbox" class="vespa-szabadidos-toggle"
                                   data-contest="<?php echo esc_attr($v->contest_id); ?>"
                                   <?php checked(intval($v->nyitva) > 0); ?>>
                            Engedélyezve
                        </label>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
(function () {
    var wrap = document.querySelector('.wrap[data-admin-nonce]');
    if (!wrap) return;
    var nonce = wrap.getAttribute('data-admin-nonce');
    var url = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';

    wrap.querySelectorAll('.vespa-szabadidos-toggle').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var fd = new FormData();
            fd.append('action', 'vespa_szabadidos_toggle_open');
            fd.append('nonce', nonce);
            fd.append('contest_id', cb.getAttribute('data-contest'));
            fd.append('open', cb.checked ? '1' : '0');
            fetch(url, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (resp) { if (!resp.success) { alert(resp.data.message); cb.checked = !cb.checked; } })
                .catch(function () { alert('Hálózati hiba.'); cb.checked = !cb.checked; });
        });
    });
})();
</script>
```

- [ ] **Step 3: Szintaxis-ellenőrzés**

Run: `php -l includes/Admin/szabadidos.admin.php && php -l templates/szabadidos_admin.php`
Expected: mindkettőre `No syntax errors detected`

- [ ] **Step 4: Grep-ellenőrzés**

Run: `grep -n "add_menu_page\|vespa_szabadidos_toggle_open\|check_ajax_referer\|current_user_can(VESPA_Roles::riportalas)\|contest_type=%d" includes/Admin/szabadidos.admin.php templates/szabadidos_admin.php`
Expected: menü, toggle-végpont, nonce, cap-ellenőrzés, type-4 szűrés jelen.

- [ ] **Step 5: Manuális ellenőrzés (böngésző)**

Adminként megjelenik a „Szabadidős külső nevezés" menü; a type-4 versenyek listája; a kapcsoló be/ki állítja a külső regisztrációt (a front-end saját nézetben csak a bekapcsolt versenyek látszanak).

- [ ] **Step 6: Commit**

```bash
git add includes/Admin/szabadidos.admin.php templates/szabadidos_admin.php
git commit -m "feat(A4): admin menü + verseny nyitás/zárás külső regisztrációra"
```

---

### Task 8: Nevezők listája + XLSX export

**Files:**
- Modify: `templates/szabadidos_admin.php` (nevezők-szekció + export-link a lista alá)
- Create: `includes/Export/szabadidos.export.php` (XLSX export)

**Interfaces:**
- Consumes: `VESPA_Roles::riportalas`; `vespa_external_entries` ⨝ `vespa_external_participants` ⨝ `vespa_constest_events`/`vespa_sport_events`.
- Produces: a kiválasztott megnyitott verseny külső nevezőinek táblázata; `?vespa_szabadidos_export=<contest_id>` XLSX-letöltés (`init`-en, `riportalas` cap mögött).

- [ ] **Step 1: Nevezők-szekció az admin-sablonba**

`templates/szabadidos_admin.php` — a nyitás/zárás tábla záró `</table>`/`<?php endif; ?>` UTÁN, a `</div>` (a `.wrap` zárása) ELÉ illeszd be:

```php
    <h2>Külső nevezők</h2>
    <?php
    $valasztott = isset($_GET['contest_id']) ? intval($_GET['contest_id']) : 0;
    $nyitott_versenyek = $wpdb->get_results($wpdb->prepare(
        "SELECT vc.contest_id, vc.contest_name
         FROM vespa_szabadidos_open_contests AS o
         INNER JOIN vespa_contests AS vc ON vc.contest_id=o.contest_id
         WHERE vc.contest_type=%d
         ORDER BY vc.contest_name",
        4
    ));
    ?>
    <form method="get">
        <input type="hidden" name="page" value="szabadidos_kulso">
        <label>Verseny:
            <select name="contest_id" onchange="this.form.submit()">
                <option value="0">— válassz —</option>
                <?php foreach ($nyitott_versenyek as $nv) : ?>
                    <option value="<?php echo esc_attr($nv->contest_id); ?>" <?php selected($valasztott, intval($nv->contest_id)); ?>>
                        <?php echo esc_html($nv->contest_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <?php if ($valasztott > 0) : ?>
        <?php
        $nevezok = $wpdb->get_results($wpdb->prepare(
            "SELECT p.full_name, p.birth_date, p.gender, p.email, p.phone, e.entry_date, vse.sport_event_name, vs.sport_name
             FROM vespa_external_entries AS e
             INNER JOIN vespa_external_participants AS p ON p.participant_id=e.participant_id
             LEFT JOIN vespa_constest_events AS vce ON vce.id=e.contest_event_id
             LEFT JOIN vespa_sport_events AS vse ON vse.sport_event_id=vce.event_id
             LEFT JOIN vespa_sports AS vs ON vs.sport_id=vce.sport_id
             WHERE e.contest_id=%d
             ORDER BY p.full_name",
            $valasztott
        ));
        ?>
        <p>
            <a class="button" href="<?php echo esc_url(add_query_arg('vespa_szabadidos_export', $valasztott, home_url('/'))); ?>">XLSX export</a>
        </p>
        <?php if (empty($nevezok)) : ?>
            <p>Erre a versenyre még nincs külső nevező.</p>
        <?php else : ?>
            <table class="widefat">
                <thead><tr><th>Név</th><th>Szül. dátum</th><th>Nem</th><th>E-mail</th><th>Telefon</th><th>Versenyszám</th><th>Nevezés dátuma</th></tr></thead>
                <tbody>
                <?php foreach ($nevezok as $n) : ?>
                    <tr>
                        <td><?php echo esc_html($n->full_name); ?></td>
                        <td><?php echo esc_html($n->birth_date); ?></td>
                        <td><?php echo esc_html($n->gender); ?></td>
                        <td><?php echo esc_html($n->email); ?></td>
                        <td><?php echo esc_html($n->phone); ?></td>
                        <td><?php echo esc_html(trim(($n->sport_name ?: '') . ' ' . ($n->sport_event_name ?: ''))); ?></td>
                        <td><?php echo esc_html($n->entry_date); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
```

- [ ] **Step 2: Az XLSX export**

Hozd létre `includes/Export/szabadidos.export.php`:

```php
<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

add_action('init', 'vespa_szabadidos_export');

function vespa_szabadidos_export()
{
    if (!isset($_GET['vespa_szabadidos_export'])) {
        return;
    }
    if (!current_user_can(VESPA_Roles::riportalas)) {
        wp_die('Jogosulatlan hozzáférés.');
    }
    $contest_id = intval($_GET['vespa_szabadidos_export']);
    if ($contest_id <= 0) {
        wp_die('Hibás verseny.');
    }

    require_once VITAREX_VESPA_PLUGIN_DIR . '/lib/vendor/autoload.php';

    global $wpdb;
    $nevezok = $wpdb->get_results($wpdb->prepare(
        "SELECT p.full_name, p.birth_date, p.gender, p.email, p.phone, e.entry_date, vse.sport_event_name, vs.sport_name
         FROM vespa_external_entries AS e
         INNER JOIN vespa_external_participants AS p ON p.participant_id=e.participant_id
         LEFT JOIN vespa_constest_events AS vce ON vce.id=e.contest_event_id
         LEFT JOIN vespa_sport_events AS vse ON vse.sport_event_id=vce.event_id
         LEFT JOIN vespa_sports AS vs ON vs.sport_id=vce.sport_id
         WHERE e.contest_id=%d
         ORDER BY p.full_name",
        $contest_id
    ));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(array('Név', 'Születési dátum', 'Nem', 'E-mail', 'Telefon', 'Versenyszám', 'Nevezés dátuma'), null, 'A1');

    $sor = 2;
    foreach ($nevezok as $n) {
        $versenyszam = trim(($n->sport_name ?: '') . ' ' . ($n->sport_event_name ?: ''));
        $sheet->fromArray(array(
            $n->full_name, $n->birth_date, $n->gender, $n->email, $n->phone, $versenyszam, $n->entry_date
        ), null, 'A' . $sor);
        $sor++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="szabadidos_nevezok.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
```

- [ ] **Step 3: Szintaxis-ellenőrzés**

Run: `php -l templates/szabadidos_admin.php && php -l includes/Export/szabadidos.export.php`
Expected: mindkettőre `No syntax errors detected`

- [ ] **Step 4: Grep-ellenőrzés**

Run: `grep -n "vespa_szabadidos_export\|current_user_can(VESPA_Roles::riportalas)\|external_participants\|contest_id=%d" includes/Export/szabadidos.export.php templates/szabadidos_admin.php`
Expected: az export-handler, a cap-ellenőrzés, a nevezők-join és a verseny-szűrés jelen.

- [ ] **Step 5: Manuális ellenőrzés (böngésző)**

Adminként a „Külső nevezők" szekcióban válassz megnyitott versenyt → a nevezők táblája megjelenik; „XLSX export" → a fájl a helyes oszlopokkal és sorokkal töltődik le.

- [ ] **Step 6: Commit**

```bash
git add templates/szabadidos_admin.php includes/Export/szabadidos.export.php
git commit -m "feat(A4): külső nevezők listája + XLSX export"
```

---

## Önellenőrzés (a terv írója tölti ki)

- **Spec-lefedettség:** új táblák → Task 1; szerep + wp-admin izoláció → Task 2; publikus regisztráció + e-mail megerősítés → Task 3–4; front-end belépés + saját nézet (versenyszám-szintű nevezés, izoláció) → Task 5–6; admin nyitás/zárás → Task 7; nevezők lista + export → Task 8. Minden spec-komponens leképezve.
- **Placeholder-scan:** nincs TBD/TODO; minden lépés teljes kódot ad.
- **Típus-konzisztencia:** a szerep-konstans `VESPA_Roles::SZABADIDOS_RESZTVEVO` (Task 2) végig egységes; a nonce-action `'vespa_szabadidos'` (front-end) és `'vespa_szabadidos_admin'` (admin) következetes; az AJAX-action-nevek a JS-hívások és a `add_action` között egyeznek (`vespa_szabadidos_register/login/signup/withdraw/toggle_open`); a táblák oszlopnevei a beszúrás/lekérdezés között egyeznek (`participant_id`, `contest_event_id`, `entry_id`).
- **Izoláció:** minden bejelentkezett végpont (Task 6) ellenőrzi a szerepet és a saját `participant_id`-t; a törlés `entry_id`+`participant_id` páron (IDOR). A front-end lekérdezések a saját `participant_id`-hez kötöttek.
- **Kockázat / megjegyzés a végrehajtáshoz:** a `home_url('/')` a wp-admin-redirect és a megerősítő/kilépő linkek célja — feltételezi, hogy a `[vespa_szabadidos]` shortcode elérhető a nyitóoldalról vagy a felhasználó onnan navigál; ha a shortcode külön aloldalon van, a redirect célját érdemes arra állítani (a záró review mérlegelje).
