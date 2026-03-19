<?php
//vespa_store_results
function vespa_store_results()
{
    global $wpdb;
    // AJAX: contest_id, race_id, athlete_id, field, value
    // DB: contest_id, event_id, athlete_id, result, user_id, entry_date        

    $contest_id = $_POST['contest_id'];
    $contest_event_id   = $_POST['race_id'];
    $result = $_POST['result'];

    if (!(current_user_can(VESPA_Roles::verseny_eredmenyek_kezelese_rogzites_modositas))) {
        wp_send_json_error(array("message" => "Jogosulatlan hozzáférés"), 403);
    }

    $record = $wpdb->get_row($wpdb->prepare(
        "  SELECT * FROM vespa_constest_events_results 
                                    WHERE contest_id=%d AND contest_event_id=%d", $contest_id,$contest_event_id));

    $contest = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM vespa_contests WHERE contest_id=%d",$contest_id
    ));

    if (!($contest->is_final == 1)) {
        wp_send_json_error(array("message" => "A verseny nincs véglegesítve"), 404);
    }

    if (!isset($record)) {
        $wpdb->insert('vespa_constest_events_results', array(
            'contest_id' => $contest_id,
            'contest_event_id'   => $contest_event_id,
            'result'     => json_encode($result),
            'user_id'    => get_current_user_id(),
            'entry_date' => date('Y-m-d H:i:s')
        ), array('%s'));
    } else {
        $wpdb->update('vespa_constest_events_results', array(
            'result'     => json_encode($result),
            'user_id'    => get_current_user_id(),
            'entry_date' => date('Y-m-d H:i:s')
        ), array(
            'result_id' => $record->result_id
        ), array('%s'));
    }

    $vars = array(
        "{=TEXT=}" => 'Eredmények mentése sikeres volt.',
        "{=URL=}" => admin_url("admin.php?page=contests&action=view&id=$contest_id")
    );
    wp_send_json_success(array('modal' => vespa_load_template_with_vars('success-modal.php', $vars), 'modalId' => 'succesModal'));
    // wp_send_json( array('success' => true) );
}
add_action('wp_ajax_vespa_store_results', 'vespa_store_results');


function vespa_get_contest_results()
{
    global $wpdb;
    $rendezes_mezo = "helyezes";
    $rendezes_irany = "asc";
    $contest_id = $_POST["contest_id"];
    $tab_id = $_POST["tab_id"];
    if (isset($_POST["rendezes_mezo"]))
        $rendezes_mezo = $_POST["rendezes_mezo"];
    if (isset($_POST["rendezes_irany"]))
        $rendezes_irany = $_POST["rendezes_irany"];
    $event_sql = "SELECT vcer.*, vse.sport_event_name AS sport_event_name, vse.result_type as sport_event_result_type,
    vs.result_type as sport_result_type   
    FROM vespa_constest_events_results AS vcer
    INNER JOIN vespa_constest_events AS vce ON vce.id=vcer.contest_event_id
    INNER JOIN vespa_sport_events AS vse ON vse.sport_event_id=vce.event_id
    INNER JOIN vespa_sports AS vs ON vs.sport_id=vse.sport_id
    WHERE vcer.contest_id=%d";

    $event_results = $wpdb->get_results($wpdb->prepare($event_sql, $contest_id));
    

    if (!isset($event_results)) {
        wp_send_json_error(array("message" => "Érvénytelen verseny eredmény"), 404);
    }
    $sportolo_sql = "SELECT va.athlete_id, va.athlete_name, va.birth_date, vi.ins_name, vs.state_name 
    FROM vespa_athletes AS va 
    INNER JOIN vespa_institutions AS vi ON vi.institution_id=va.school_id
    INNER JOIN vespa_states as vs ON vs.state_id=vi.ins_state
    INNER JOIN vespa_athlete_entries AS vae ON vae.athlete_id=va.athlete_id
    WHERE vae.contest_id =%d";

    $sportolok = $wpdb->get_results($wpdb->prepare($sportolo_sql, $contest_id));
    

    $html = "<table width='100%'>";
    foreach ($event_results as $event_result) {
        $eredmenyek = json_decode($event_result->result);
        usort($eredmenyek, function ($eredmeny1, $eredmeny2) use ($rendezes_mezo, $rendezes_irany, $sportolok) {
            //ha egyedi rendezés kell
            $sportolo1 = $sportolok[array_search($eredmeny1->athlete_id, array_column($sportolok, 'athlete_id'))];
            $sportolo2 = $sportolok[array_search($eredmeny2->athlete_id, array_column($sportolok, 'athlete_id'))];
            if ($rendezes_irany == "desc") {
                $tmp = $sportolo1;
                $sportolo1 = $sportolo2;
                $sportolo2 = $tmp;
            }
            switch ($rendezes_mezo) {
                case 'athlete_name':
                    return strcmp($sportolo1->athlete_name, $sportolo2->athlete_name);
                case 'birth_date':
                    return strcmp($sportolo1->birth_date, $sportolo2->birth_date);
                case 'ins_name':
                    return strcmp($sportolo1->ins_name, $sportolo2->ins_name);
                case 'ins_state':
                    return strcmp($sportolo1->ins_state, $sportolo2->ins_state);
                case 'helyezes':
                    $helyezes1 = $eredmeny1->helyezes;
                    $helyezes2 = $eredmeny2->helyezes;
                    if ($rendezes_irany == "desc") {
                        $tmp = $helyezes1;
                        $helyezes1 = $helyezes2;
                        $helyezes2 = $tmp;
                    }
                    if (
                        !empty($helyezes1) && !empty($helyezes2)
                        && is_numeric($helyezes1 && is_numeric($helyezes2))
                    )
                        return intval($helyezes1) - intval($helyezes2);
                    return strcmp($helyezes1, $helyezes2);
                case 'eredmeny':
                    $eredmeny1 = $eredmeny1->eredmeny;
                    $eredmeny2 = $eredmeny2->eredmeny;
                    if ($rendezes_irany == "desc") {
                        $tmp = $eredmeny1;
                        $eredmeny1 = $eredmeny2;
                        $eredmeny2 = $tmp;
                    }
                    if (
                        !empty($eredmeny1) && !empty($eredmeny2)
                        && is_numeric($eredmeny1 && is_numeric($eredmeny2))
                    )
                        return intval($eredmeny1) - intval($eredmeny2);
                    return strcmp($eredmeny1, $eredmeny2);
            }
        });
        //rendezett output
        $html .= "<tr><td colspan=6><h4 class='pl-1'>$event_result->sport_event_name</h4></td></tr>";
        $mertekegyseg = !empty($event_result->sport_event_result_type) ? $event_result->sport_event_result_type : $event_result->sport_result_type;
        $eredmeny_tipus = mertekegyseg_ertek($mertekegyseg);
        $html .= "<tr>
                <th width='7%' style='cursor: pointer;' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'helyezes', $rendezes_irany, 'Helyezés') . "</th>
                <th width='28%' style='cursor: pointer;' class='pl-1' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'athlete_name', $rendezes_irany, 'Név') . "</th>
                <th width='10%' style='cursor: pointer;' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'birth_date', $rendezes_irany, 'Születési dátum') . "</th>
                <th width='20%' style='cursor: pointer;' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'ins_name', $rendezes_irany, 'Intézmény') . "</th>
                <th width='15%' style='cursor: pointer;' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'ins_state', $rendezes_irany, 'Megye') . "</th>";

        if ($mertekegyseg != 'helyezes') {
            $felirat = "Eredmény ($eredmeny_tipus)";
            $html .= "<th width='20%' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'eredmeny', $rendezes_irany, $felirat) . "</th>";
        }
        $html .= "</tr>";
        foreach ($eredmenyek as $kulcs => $eredmeny) :
            $sportolo = $sportolok[array_search($eredmeny->athlete_id, array_column($sportolok, 'athlete_id'))];
            $html .= "<tr style='border-top: thin solid; border-bottom: thin solid;'><td class='pl-2'>$eredmeny->helyezes</td>
            <td class='pl-1'>$sportolo->athlete_name</td>
            <td>$sportolo->birth_date</td>
            <td>$sportolo->ins_name</td>
            <td>$sportolo->state_name";

            if ($mertekegyseg != 'helyezes')
                $html .= "<td>$eredmeny->eredmeny</td>";
            $html .= "</tr>";
        endforeach;
        $html .= "<tr/>";
    }
    $html .= "</table>";
    wp_send_json(array("success" => true, "html" => $html));
}
add_action('wp_ajax_vespa_get_contest_results', 'vespa_get_contest_results');


function rendezes_irany_kalkulacio($rendezes_mezo, $rend_mezo, $rendezes_irany, $felirat)
{
    if ($rendezes_mezo == $rend_mezo) {
        $rend_irany = $rendezes_irany == "asc" ? "desc" : "asc";
        $ikon = $rendezes_irany == "asc" ? "up" : "down";
        return "'$rend_mezo','$rend_irany')>$felirat<span class='dashicons dashicons-arrow-$ikon-alt'/>";
    }
    return "'$rend_mezo','asc')>$felirat";
}

function mertekegyseg_ertek($mertekegyseg_tipus)
{
    switch ($mertekegyseg_tipus) {
        case 'helyezes':
            return 'helyezés';
        case 'helyezes_ido_k':
        case 'helyezes_ido_n':
            return 'idő - másodperc';
        case 'helyezes_tav_k':
        case 'helyezes_tav_n':
            return 'távolság - cm';
        case 'helyezes_suly_k':
        case 'helyezes_suly_n':
            return 'súly - kg';
        default:
            return $mertekegyseg_tipus;
    }
}
