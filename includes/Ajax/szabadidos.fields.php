<?php

/**
 * A szabadidős nevezési mezők admin AJAX végpontjai.
 *
 * Mind a négy végpont ugyanazt a kaput használja: vespa_szabadidos_admin
 * nonce + VESPA_Roles::riportalas jogosultság + type-4 verseny. A field_id-t
 * mindig a contest_id-hoz kötve ellenőrizzük (IDOR-védelem), ahogy a
 * vespa_szabadidos_withdraw is teszi a saját sorával.
 */

/**
 * A közös belépési ellenőrzés. Hiba esetén maga küldi a JSON választ és kilép.
 * Visszatérés: az ellenőrzött contest_id.
 */
function vespa_szabadidos_fields_gate()
{
    check_ajax_referer('vespa_szabadidos_admin', 'nonce');

    if (!current_user_can(VESPA_Roles::riportalas)) {
        wp_send_json_error(array('message' => 'Jogosulatlan hozzáférés.'));
    }

    $contest_id = isset($_POST['contest_id']) ? intval($_POST['contest_id']) : 0;
    if ($contest_id <= 0 || !vespa_szabadidos_contest_is_type4($contest_id)) {
        wp_send_json_error(array('message' => 'Csak szabadidős versenyhez vehető fel nevezési mező.'));
    }

    return $contest_id;
}

/**
 * A verseny mezőlistája a kliensnek: aktív és archivált mezők, válaszszámmal.
 */
function vespa_szabadidos_fields_payload($contest_id)
{
    $lista = array();
    foreach (vespa_szabadidos_get_fields($contest_id, false) as $mezo) {
        $lista[] = array(
            'field_id'      => intval($mezo->field_id),
            'label'         => $mezo->label,
            'field_type'    => $mezo->field_type,
            'field_options' => $mezo->field_options === null ? '' : $mezo->field_options,
            'is_required'   => intval($mezo->is_required),
            'ordernum'      => intval($mezo->ordernum),
            'is_active'     => intval($mezo->is_active),
            'answer_count'  => vespa_szabadidos_field_answer_count($mezo->field_id),
        );
    }
    return $lista;
}

/**
 * A megadott mező ellenőrzése: létezik-e, és tényleg ehhez a versenyhez tartozik-e.
 * Hiba esetén maga küldi a JSON választ és kilép.
 */
function vespa_szabadidos_fields_require_own($field_id, $contest_id)
{
    $mezo = vespa_szabadidos_get_field($field_id);
    if (!$mezo || intval($mezo->contest_id) !== intval($contest_id)) {
        wp_send_json_error(array('message' => 'A mező nem található.'));
    }
    return $mezo;
}

add_action('wp_ajax_vespa_szabadidos_field_save', 'vespa_szabadidos_field_save');

function vespa_szabadidos_field_save()
{
    $contest_id = vespa_szabadidos_fields_gate();

    $field_id = isset($_POST['field_id']) ? intval($_POST['field_id']) : 0;
    $label    = isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '';
    $type     = isset($_POST['field_type']) ? sanitize_text_field(wp_unslash($_POST['field_type'])) : '';
    $opciok   = isset($_POST['field_options']) ? sanitize_textarea_field(wp_unslash($_POST['field_options'])) : '';
    $kotolezo = isset($_POST['is_required']) && $_POST['is_required'] === '1';

    $ellenorzes = vespa_szabadidos_validate_field($label, $type, $opciok, $kotolezo);
    if (!$ellenorzes['ok']) {
        wp_send_json_error(array('message' => $ellenorzes['error']));
    }
    $adat = $ellenorzes['field'];

    global $wpdb;

    if ($field_id > 0) {
        vespa_szabadidos_fields_require_own($field_id, $contest_id);
        $wpdb->update(
            'vespa_szabadidos_fields',
            $adat,
            array('field_id' => $field_id),
            array('%s', '%s', '%s', '%d'),
            array('%d')
        );
        $uzenet = 'Mentve.';
    } else {
        $adat['contest_id'] = $contest_id;
        $adat['ordernum']   = vespa_szabadidos_next_ordernum($contest_id);
        $adat['is_active']  = 1;
        $adat['created_at'] = current_time('mysql');

        $siker = $wpdb->insert('vespa_szabadidos_fields', $adat, array('%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s'));
        if ($siker === false) {
            wp_send_json_error(array('message' => 'A mező mentése nem sikerült.'));
        }
        $field_id = intval($wpdb->insert_id);
        $uzenet = 'Az új mező mentve.';
    }

    wp_send_json_success(array(
        'message'  => $uzenet,
        'field_id' => $field_id,
        'fields'   => vespa_szabadidos_fields_payload($contest_id),
    ));
}

add_action('wp_ajax_vespa_szabadidos_field_delete', 'vespa_szabadidos_field_delete');

function vespa_szabadidos_field_delete()
{
    $contest_id = vespa_szabadidos_fields_gate();

    $field_id = isset($_POST['field_id']) ? intval($_POST['field_id']) : 0;
    vespa_szabadidos_fields_require_own($field_id, $contest_id);

    global $wpdb;

    // Ha még nincs rá válasz, nincs mit megőrizni — a sor valóban törölhető.
    if (vespa_szabadidos_field_answer_count($field_id) === 0) {
        $wpdb->delete('vespa_szabadidos_fields', array('field_id' => $field_id), array('%d'));
        $uzenet = 'A mező törölve.';
    } else {
        $wpdb->update(
            'vespa_szabadidos_fields',
            array('is_active' => 0),
            array('field_id' => $field_id),
            array('%d'),
            array('%d')
        );
        $uzenet = 'A mező archiválva. A beérkezett válaszok megmaradnak.';
    }

    wp_send_json_success(array(
        'message' => $uzenet,
        'fields'  => vespa_szabadidos_fields_payload($contest_id),
    ));
}

add_action('wp_ajax_vespa_szabadidos_field_restore', 'vespa_szabadidos_field_restore');

function vespa_szabadidos_field_restore()
{
    $contest_id = vespa_szabadidos_fields_gate();

    $field_id = isset($_POST['field_id']) ? intval($_POST['field_id']) : 0;
    vespa_szabadidos_fields_require_own($field_id, $contest_id);

    global $wpdb;
    $wpdb->update(
        'vespa_szabadidos_fields',
        array('is_active' => 1, 'ordernum' => vespa_szabadidos_next_ordernum($contest_id)),
        array('field_id' => $field_id),
        array('%d', '%d'),
        array('%d')
    );

    wp_send_json_success(array(
        'message' => 'A mező visszakapcsolva.',
        'fields'  => vespa_szabadidos_fields_payload($contest_id),
    ));
}

add_action('wp_ajax_vespa_szabadidos_field_move', 'vespa_szabadidos_field_move');

function vespa_szabadidos_field_move()
{
    $contest_id = vespa_szabadidos_fields_gate();

    $field_id = isset($_POST['field_id']) ? intval($_POST['field_id']) : 0;
    $irany    = (isset($_POST['direction']) && $_POST['direction'] === 'up') ? 'up' : 'down';
    vespa_szabadidos_fields_require_own($field_id, $contest_id);

    // Csak az aktív mezőket rendezzük: az archiváltak a lista végén ülnek.
    $aktivak = vespa_szabadidos_get_fields($contest_id, true);
    $index = -1;
    foreach ($aktivak as $i => $mezo) {
        if (intval($mezo->field_id) === $field_id) {
            $index = $i;
            break;
        }
    }

    $szomszed = ($irany === 'up') ? $index - 1 : $index + 1;
    if ($index < 0 || $szomszed < 0 || $szomszed >= count($aktivak)) {
        // A lista szélén álló mező mozgatása nem hiba, csak nincs mit tenni.
        wp_send_json_success(array(
            'message' => '',
            'fields'  => vespa_szabadidos_fields_payload($contest_id),
        ));
    }

    // A tárolt ordernum értékek lehetnek hézagosak vagy azonosak (régi adat,
    // párhuzamos szerkesztés), ezért nem cserélgetünk: az egész aktív listát
    // újraszámozzuk 0-tól, a megcserélt sorrend szerint.
    $sorrend = $aktivak;
    $ideiglenes = $sorrend[$index];
    $sorrend[$index] = $sorrend[$szomszed];
    $sorrend[$szomszed] = $ideiglenes;

    global $wpdb;
    foreach ($sorrend as $uj_index => $mezo) {
        $wpdb->update(
            'vespa_szabadidos_fields',
            array('ordernum' => $uj_index),
            array('field_id' => intval($mezo->field_id)),
            array('%d'),
            array('%d')
        );
    }

    wp_send_json_success(array(
        'message' => '',
        'fields'  => vespa_szabadidos_fields_payload($contest_id),
    ));
}
