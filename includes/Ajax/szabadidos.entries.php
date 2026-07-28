<?php

add_action('wp_ajax_vespa_szabadidos_signup', 'vespa_szabadidos_signup');

function vespa_szabadidos_signup()
{
    check_ajax_referer('vespa_szabadidos', 'nonce');

    if (!vespa_szabadidos_is_participant()) {
        wp_send_json_error(array('message' => 'Jogosulatlan hozzáférés.'));
    }
    $resztvevo = vespa_szabadidos_current_participant();
    if (!$resztvevo) {
        wp_send_json_error(array('message' => 'Hiányzó résztvevő-profil.'));
    }

    $contest_id = isset($_POST['contest_id']) ? intval($_POST['contest_id']) : 0;
    $event_id   = isset($_POST['contest_event_id']) ? intval($_POST['contest_event_id']) : 0;
    if ($contest_id <= 0 || $event_id <= 0) {
        wp_send_json_error(array('message' => 'Hibás nevezési adatok.'));
    }

    global $wpdb;

    // A verseny type-4, megnyitott és nem lejárt.
    $verseny = $wpdb->get_row($wpdb->prepare(
        "SELECT vc.contest_id, vc.ppl_num_max
         FROM vespa_szabadidos_open_contests AS o
         INNER JOIN vespa_contests AS vc ON vc.contest_id=o.contest_id
         WHERE o.contest_id=%d AND vc.contest_type=%d AND vc.end_at >= %s",
        $contest_id,
        4,
        current_time('mysql')
    ));
    if (!$verseny) {
        wp_send_json_error(array('message' => 'Erre a versenyre jelenleg nem lehet nevezni.'));
    }

    // A versenyszám valóban ehhez a versenyhez tartozik.
    $esemeny_ok = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM vespa_constest_events WHERE id=%d AND contest_id=%d",
        $event_id,
        $contest_id
    ));
    if (intval($esemeny_ok) === 0) {
        wp_send_json_error(array('message' => 'Érvénytelen versenyszám.'));
    }

    // Dupla nevezés kizárása ugyanarra a versenyszámra.
    $mar_van = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM vespa_external_entries WHERE participant_id=%d AND contest_event_id=%d",
        $resztvevo->participant_id,
        $event_id
    ));
    if (intval($mar_van) > 0) {
        wp_send_json_error(array('message' => 'Erre a versenyszámra már neveztél.'));
    }

    // Globális létszámkeret (type-4): a verseny összes külső nevezése vs ppl_num_max.
    if (is_numeric($verseny->ppl_num_max) && intval($verseny->ppl_num_max) > 0) {
        $jelenlegi = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM vespa_external_entries WHERE contest_id=%d",
            $contest_id
        ));
        if (intval($jelenlegi) >= intval($verseny->ppl_num_max)) {
            wp_send_json_error(array('message' => 'A helyek száma betelt.'));
        }
    }

    // A nevezési mezők ellenőrzése. Versenyenként egyszer kérdezünk: aki már
    // válaszolt erre a versenyre, annak a második nevezésekor nincs mit
    // kitölteni.
    $mentendo_valaszok = array();
    $mezok = vespa_szabadidos_get_fields($contest_id);
    if (!empty($mezok) && !vespa_szabadidos_has_answers($resztvevo->participant_id, $contest_id)) {
        $bejovo = (isset($_POST['mezok']) && is_array($_POST['mezok']))
            ? wp_unslash($_POST['mezok'])
            : array();

        $mezo_hibak = array();
        foreach ($mezok as $mezo) {
            $field_id = intval($mezo->field_id);
            $nyers = isset($bejovo[$field_id]) ? $bejovo[$field_id] : '';

            if (is_array($nyers)) {
                $nyers = array_map('sanitize_text_field', $nyers);
            } elseif ($mezo->field_type === 'hosszu_szoveg') {
                $nyers = sanitize_textarea_field($nyers);
            } else {
                $nyers = sanitize_text_field($nyers);
            }

            $eredmeny = vespa_szabadidos_validate_answer(
                $mezo->field_type,
                intval($mezo->is_required),
                vespa_szabadidos_parse_options($mezo->field_options),
                $nyers
            );

            if (!$eredmeny['ok']) {
                $mezo_hibak[$field_id] = $eredmeny['error'];
                continue;
            }
            $mentendo_valaszok[$field_id] = $eredmeny['value'];
        }

        if (!empty($mezo_hibak)) {
            wp_send_json_error(array(
                'message'      => 'Hiányzó vagy hibás adat a nevezési űrlapon.',
                'field_errors' => $mezo_hibak,
            ));
        }
    }

    $beszurva = $wpdb->insert('vespa_external_entries', array(
        'participant_id'   => $resztvevo->participant_id,
        'contest_id'       => $contest_id,
        'contest_event_id' => $event_id,
        'entry_date'       => current_time('mysql'),
    ));

    if ($beszurva === false) {
        // Az egyedi index elkaphat egy versenyfutási dupla nevezést.
        wp_send_json_error(array('message' => 'A nevezés nem sikerült. Lehet, hogy erre a versenyszámra már neveztél.'));
    }

    // A válaszok csak sikeres nevezés után kerülnek be — így nem marad
    // válaszhalmaz nevezés nélkül.
    if (!empty($mentendo_valaszok)) {
        vespa_szabadidos_save_answers($resztvevo->participant_id, $contest_id, $mentendo_valaszok);
    }

    wp_send_json_success(array('message' => 'Sikeres nevezés.'));
}

add_action('wp_ajax_vespa_szabadidos_withdraw', 'vespa_szabadidos_withdraw');

function vespa_szabadidos_withdraw()
{
    check_ajax_referer('vespa_szabadidos', 'nonce');

    if (!vespa_szabadidos_is_participant()) {
        wp_send_json_error(array('message' => 'Jogosulatlan hozzáférés.'));
    }
    $resztvevo = vespa_szabadidos_current_participant();
    if (!$resztvevo) {
        wp_send_json_error(array('message' => 'Hiányzó résztvevő-profil.'));
    }

    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    if ($entry_id <= 0) {
        wp_send_json_error(array('message' => 'Hibás nevezés-azonosító.'));
    }

    global $wpdb;
    // IDOR-védelem: kizárólag a SAJÁT participant_id-hoz tartozó sor törölhető.
    $torolt = $wpdb->delete('vespa_external_entries', array(
        'entry_id'       => $entry_id,
        'participant_id' => $resztvevo->participant_id,
    ), array('%d', '%d'));

    if ($torolt) {
        wp_send_json_success(array('message' => 'A nevezést visszavontuk.'));
    }
    wp_send_json_error(array('message' => 'A nevezés nem található.'));
}
