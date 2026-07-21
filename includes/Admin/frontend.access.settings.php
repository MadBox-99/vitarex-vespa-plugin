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
