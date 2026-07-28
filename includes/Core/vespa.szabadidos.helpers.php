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

/**
 * Igaz, ha a megadott verseny létezik és szabadidős (type-4).
 * Minden mezőkezelő végpont ezzel kapuz — csak type-4 versenynek lehet
 * nevezési mezője.
 */
function vespa_szabadidos_contest_is_type4($contest_id)
{
    global $wpdb;
    $tipus = $wpdb->get_var($wpdb->prepare(
        "SELECT contest_type FROM vespa_contests WHERE contest_id=%d",
        intval($contest_id)
    ));
    return $tipus !== null && intval($tipus) === 4;
}

/**
 * A verseny nevezési mezői sorrendben.
 * $csak_aktiv = false esetén az archivált (puhán törölt) mezőket is hozza.
 */
function vespa_szabadidos_get_fields($contest_id, $csak_aktiv = true)
{
    global $wpdb;
    $contest_id = intval($contest_id);
    if ($contest_id <= 0) {
        return array();
    }

    if ($csak_aktiv) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM vespa_szabadidos_fields
             WHERE contest_id=%d AND is_active=1
             ORDER BY ordernum ASC, field_id ASC",
            $contest_id
        ));
    }

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM vespa_szabadidos_fields
         WHERE contest_id=%d
         ORDER BY is_active DESC, ordernum ASC, field_id ASC",
        $contest_id
    ));
}

/**
 * Egyetlen mező sora, vagy null.
 */
function vespa_szabadidos_get_field($field_id)
{
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM vespa_szabadidos_fields WHERE field_id=%d",
        intval($field_id)
    ));
    return $row ? $row : null;
}

/**
 * Hány válasz érkezett már erre a mezőre? A törlés ez alapján dönt puha és
 * végleges törlés között.
 */
function vespa_szabadidos_field_answer_count($field_id)
{
    global $wpdb;
    return intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM vespa_szabadidos_answers WHERE field_id=%d",
        intval($field_id)
    )));
}

/**
 * A verseny következő szabad sorszáma (új mező a lista végére kerül).
 */
function vespa_szabadidos_next_ordernum($contest_id)
{
    global $wpdb;
    $max = $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(ordernum) FROM vespa_szabadidos_fields WHERE contest_id=%d",
        intval($contest_id)
    ));
    return $max === null ? 0 : intval($max) + 1;
}

/**
 * Igaz, ha a résztvevő erre a versenyre már kitöltötte a nevezési mezőket.
 * Versenyenként egyszer kérdezünk: ha van bármilyen válasza, nem kérdezünk újra.
 */
function vespa_szabadidos_has_answers($participant_id, $contest_id)
{
    global $wpdb;
    $db = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM vespa_szabadidos_answers WHERE participant_id=%d AND contest_id=%d",
        intval($participant_id),
        intval($contest_id)
    ));
    return intval($db) > 0;
}

/**
 * A résztvevő válaszai egy versenyre: field_id => tárolt érték.
 */
function vespa_szabadidos_get_answers($participant_id, $contest_id)
{
    global $wpdb;
    $sorok = $wpdb->get_results($wpdb->prepare(
        "SELECT field_id, answer_value FROM vespa_szabadidos_answers
         WHERE participant_id=%d AND contest_id=%d",
        intval($participant_id),
        intval($contest_id)
    ));

    $eredmeny = array();
    foreach ((array) $sorok as $sor) {
        $eredmeny[intval($sor->field_id)] = $sor->answer_value;
    }
    return $eredmeny;
}

/**
 * Válaszok mentése. $ertekek: field_id => tárolt érték (null = nincs mit menteni).
 * Az uniq_reszt_mezo index miatt az ismételt mentés frissít, nem duplikál.
 */
function vespa_szabadidos_save_answers($participant_id, $contest_id, $ertekek)
{
    global $wpdb;
    $most = current_time('mysql');

    foreach ($ertekek as $field_id => $ertek) {
        if ($ertek === null) {
            continue;
        }
        $wpdb->query($wpdb->prepare(
            "INSERT INTO vespa_szabadidos_answers
                (participant_id, contest_id, field_id, answer_value, updated_at)
             VALUES (%d, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE answer_value=VALUES(answer_value), updated_at=VALUES(updated_at)",
            intval($participant_id),
            intval($contest_id),
            intval($field_id),
            $ertek,
            $most
        ));
    }
}

/**
 * A verseny külső nevezői a nevezési mezőkre adott válaszokkal együtt.
 * Egy sor = egy nevezés (versenyszám), tehát aki két versenyszámra nevezett,
 * két sorral szerepel — mindkettőnél ugyanazokkal a válaszokkal.
 *
 * Az oszlopok közé bekerül minden aktív mező, továbbá minden archivált mező,
 * amire van válasz. Ez a puha törlés lényege: a régi adat az exportból sem
 * tűnik el.
 */
function vespa_szabadidos_entry_table($contest_id)
{
    global $wpdb;
    $contest_id = intval($contest_id);

    $nevezok = $wpdb->get_results($wpdb->prepare(
        "SELECT e.participant_id, p.full_name, p.birth_date, p.gender, p.email, p.phone,
                e.entry_date, vse.sport_event_name, vs.sport_name
         FROM vespa_external_entries AS e
         INNER JOIN vespa_external_participants AS p ON p.participant_id=e.participant_id
         LEFT JOIN vespa_constest_events AS vce ON vce.id=e.contest_event_id
         LEFT JOIN vespa_sport_events AS vse ON vse.sport_event_id=vce.event_id
         LEFT JOIN vespa_sports AS vs ON vs.sport_id=vce.sport_id
         WHERE e.contest_id=%d
         ORDER BY p.full_name",
        $contest_id
    ));

    $mezok = $wpdb->get_results($wpdb->prepare(
        "SELECT f.* FROM vespa_szabadidos_fields AS f
         WHERE f.contest_id=%d
           AND (f.is_active=1
                OR EXISTS (SELECT 1 FROM vespa_szabadidos_answers AS a WHERE a.field_id=f.field_id))
         ORDER BY f.ordernum ASC, f.field_id ASC",
        $contest_id
    ));

    $valaszsorok = $wpdb->get_results($wpdb->prepare(
        "SELECT participant_id, field_id, answer_value
         FROM vespa_szabadidos_answers WHERE contest_id=%d",
        $contest_id
    ));

    // participant_id => field_id => nyers érték
    $valaszok = array();
    foreach ((array) $valaszsorok as $v) {
        $valaszok[intval($v->participant_id)][intval($v->field_id)] = $v->answer_value;
    }

    $columns = array();
    foreach ((array) $mezok as $m) {
        $columns[] = array(
            'field_id'   => intval($m->field_id),
            'label'      => $m->label,
            'field_type' => $m->field_type,
            'archived'   => intval($m->is_active) === 0,
        );
    }

    $rows = array();
    foreach ((array) $nevezok as $n) {
        $pid = intval($n->participant_id);
        $sajat = isset($valaszok[$pid]) ? $valaszok[$pid] : array();

        $megjelenitett = array();
        $hianyzik = false;
        foreach ((array) $mezok as $m) {
            $fid = intval($m->field_id);
            $nyers = isset($sajat[$fid]) ? $sajat[$fid] : null;
            $megjelenitett[$fid] = vespa_szabadidos_format_answer($m->field_type, $nyers);

            // Csak az aktív, kötelező mező hiánya számít pótolandónak: az
            // archivált mezőt már senkitől nem várjuk el.
            if (intval($m->is_active) === 1 && intval($m->is_required) === 1 && $megjelenitett[$fid] === '') {
                $hianyzik = true;
            }
        }

        $rows[] = array(
            'nevezo'  => $n,
            'answers' => $megjelenitett,
            'missing' => $hianyzik,
        );
    }

    return array('columns' => $columns, 'rows' => $rows);
}
