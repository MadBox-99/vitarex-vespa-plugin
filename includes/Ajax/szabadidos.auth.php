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
