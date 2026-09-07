<?php

// reopen_contest
function ajax_reopen_contest()
{
    global $wpdb;
    // Az országos versenyt csak az adminisztrátor nyithatja vissza szerkesztésre.
    if (vespa_user_can_edit_contest(intval($_POST['contest_id']))) {
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
