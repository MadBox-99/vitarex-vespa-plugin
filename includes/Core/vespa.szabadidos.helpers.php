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
