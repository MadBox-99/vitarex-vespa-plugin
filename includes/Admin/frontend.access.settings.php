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
