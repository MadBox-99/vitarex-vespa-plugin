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
