<?php

// reopen_contest
function ajax_reopen_contest()
{
    global $wpdb;
    if (current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles)) {
        vitarex_log_function_call("Felhasználó: " . $_POST['caller'], true);
        $success = $wpdb->update('vespa_contests', array(
            'is_final'    => 0
        ), array(
            'contest_id' => $_POST['contest_id']
        ));
        wp_send_json_success();
    } else
        wp_send_json_error(null, 403);
}

add_action('wp_ajax_reopen_contest', 'ajax_reopen_contest');
