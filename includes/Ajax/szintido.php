<?php

/**
 * A szintidő (qualifying time) AJAX végpontjai.
 *
 * Négy végpont: a tanár rögzíti/törli a sportoló idejét (első kettő), az
 * admin beállítja/törli egy versenyszám minimumát (harmadik), a tanuló-
 * szerkesztő pedig újra lekérheti a listát (negyedik). Mind a négy csak
 * wp_ajax_ (a szintidő nem nyilvános adat), és mind ugyanazt a vespa_nonce
 * nonce-ot használja, mint a plugin többi admin AJAX hívása.
 */

add_action('wp_ajax_vespa_szintido_save', 'vespa_szintido_ajax_save');

function vespa_szintido_ajax_save()
{
    check_ajax_referer('vespa_nonce', 'nonce');

    if (!current_user_can(VESPA_Roles::sportolok_letrehozasa_modositasa)) {
        wp_send_json_error(array('message' => 'Adott művelethez a felhasználó nem jogosult.'));
    }

    $athlete_id     = isset($_POST['athlete_id']) ? intval($_POST['athlete_id']) : 0;
    $sport_event_id = isset($_POST['sport_event_id']) ? intval($_POST['sport_event_id']) : 0;
    $ido_szoveg     = isset($_POST['ido']) ? sanitize_text_field(wp_unslash($_POST['ido'])) : '';

    if ($athlete_id <= 0 || $sport_event_id <= 0) {
        wp_send_json_error(array('message' => 'Hiányzó sportoló vagy sportesemény.'));
    }

    $masodperc = vespa_szintido_parse($ido_szoveg);
    if ($masodperc === null) {
        wp_send_json_error(array('message' => 'Érvénytelen időformátum. Elfogadott formák: 14.84, 14,84, 1:02.5.'));
    }

    global $wpdb;
    $most    = current_time('mysql');
    $user_id = get_current_user_id();

    // A unique key (athlete_id, sport_event_id) miatt ez upsert: ha már van
    // idő erre az eseményre, felülírja, egyébként új sort szúr be.
    $eredmeny = $wpdb->query($wpdb->prepare(
        "INSERT INTO vespa_athlete_qualifying_times
            (athlete_id, sport_event_id, seconds, raw_input, recorded_at, recorded_by)
         VALUES (%d, %d, %f, %s, %s, %d)
         ON DUPLICATE KEY UPDATE
            seconds = VALUES(seconds),
            raw_input = VALUES(raw_input),
            recorded_at = VALUES(recorded_at),
            recorded_by = VALUES(recorded_by)",
        $athlete_id,
        $sport_event_id,
        $masodperc,
        $ido_szoveg,
        $most,
        $user_id
    ));

    if ($eredmeny === false) {
        wp_send_json_error(array('message' => 'Az idő mentése nem sikerült.'));
    }

    wp_send_json_success(array(
        'message' => 'Az idő mentve.',
        'times'   => vespa_szintido_athlete_times($athlete_id),
    ));
}

add_action('wp_ajax_vespa_szintido_delete', 'vespa_szintido_ajax_delete');

function vespa_szintido_ajax_delete()
{
    check_ajax_referer('vespa_nonce', 'nonce');

    if (!current_user_can(VESPA_Roles::sportolok_letrehozasa_modositasa)) {
        wp_send_json_error(array('message' => 'Adott művelethez a felhasználó nem jogosult.'));
    }

    $athlete_id     = isset($_POST['athlete_id']) ? intval($_POST['athlete_id']) : 0;
    $sport_event_id = isset($_POST['sport_event_id']) ? intval($_POST['sport_event_id']) : 0;

    global $wpdb;
    $wpdb->delete(
        'vespa_athlete_qualifying_times',
        array('athlete_id' => $athlete_id, 'sport_event_id' => $sport_event_id),
        array('%d', '%d')
    );

    wp_send_json_success(array(
        'message' => 'Az idő törölve.',
        'times'   => vespa_szintido_athlete_times($athlete_id),
    ));
}

add_action('wp_ajax_vespa_szintido_set_min', 'vespa_szintido_ajax_set_min');

function vespa_szintido_ajax_set_min()
{
    check_ajax_referer('vespa_nonce', 'nonce');

    if (!current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles)) {
        wp_send_json_error(array('message' => 'Adott művelethez a felhasználó nem jogosult.'));
    }

    $contest_event_id = isset($_POST['contest_event_id']) ? intval($_POST['contest_event_id']) : 0;
    $ido_szoveg        = isset($_POST['ido']) ? trim(sanitize_text_field(wp_unslash($_POST['ido']))) : '';

    global $wpdb;

    $letezik = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM vespa_constest_events WHERE id=%d",
        $contest_event_id
    ));
    if (!intval($letezik)) {
        wp_send_json_error(array('message' => 'A versenyszám nem található.'));
    }

    // Üres mező -> a feltétel törlése (NULL): ez az opt-in alapállapota,
    // ilyenkor mindenki nevezhető.
    if ($ido_szoveg === '') {
        $wpdb->update(
            'vespa_constest_events',
            array('min_qualifying_seconds' => null),
            array('id' => $contest_event_id),
            array('%s'),
            array('%d')
        );

        wp_send_json_success(array(
            'message'   => 'A szintidő-feltétel törölve, a versenyszám mostantól feltétel nélkül nevezhető.',
            'formatted' => '',
        ));
    }

    $masodperc = vespa_szintido_parse($ido_szoveg);
    if ($masodperc === null) {
        wp_send_json_error(array('message' => 'Érvénytelen időformátum. Elfogadott formák: 14.84, 14,84, 1:02.5.'));
    }

    $wpdb->query($wpdb->prepare(
        "UPDATE vespa_constest_events SET min_qualifying_seconds=%f WHERE id=%d",
        $masodperc,
        $contest_event_id
    ));

    wp_send_json_success(array(
        'message'   => 'A szintidő-feltétel mentve.',
        'formatted' => vespa_szintido_format($masodperc),
    ));
}

add_action('wp_ajax_vespa_szintido_list', 'vespa_szintido_ajax_list');

function vespa_szintido_ajax_list()
{
    check_ajax_referer('vespa_nonce', 'nonce');

    if (!current_user_can(VESPA_Roles::sportolok_letrehozasa_modositasa)) {
        wp_send_json_error(array('message' => 'Adott művelethez a felhasználó nem jogosult.'));
    }

    $athlete_id = isset($_POST['athlete_id']) ? intval($_POST['athlete_id']) : 0;

    wp_send_json_success(array(
        'times' => vespa_szintido_athlete_times($athlete_id),
    ));
}

/**
 * Egy sportoló összes rögzített szintideje, a sportesemény nevével együtt.
 * Ugyanez az alak megy vissza a mentés/törlés/lista válaszban is, hogy a
 * kliens mindig ugyanazzal a rajzoló-függvénnyel építhesse fel a táblát.
 */
function vespa_szintido_athlete_times($athlete_id)
{
    global $wpdb;

    $sorok = $wpdb->get_results($wpdb->prepare(
        "SELECT qt.sport_event_id, qt.seconds, qt.raw_input, se.sport_event_name
         FROM vespa_athlete_qualifying_times AS qt
         INNER JOIN vespa_sport_events AS se ON se.sport_event_id = qt.sport_event_id
         WHERE qt.athlete_id = %d
         ORDER BY se.sport_event_name ASC",
        $athlete_id
    ));

    $lista = array();
    foreach ($sorok as $sor) {
        $lista[] = array(
            'sport_event_id'   => intval($sor->sport_event_id),
            'sport_event_name' => $sor->sport_event_name,
            'seconds'          => (float) $sor->seconds,
            'formatted'        => vespa_szintido_format($sor->seconds),
            'raw_input'        => $sor->raw_input,
        );
    }

    return $lista;
}
