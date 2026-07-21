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
        // A beállított nevezési oldalra küldjük, nem vakon a kezdőlapra.
        wp_safe_redirect(vespa_frontend_access_landing_url());
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
