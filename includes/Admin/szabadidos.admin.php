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
