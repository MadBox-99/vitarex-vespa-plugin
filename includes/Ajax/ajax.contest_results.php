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
    if (!current_user_can(VESPA_Roles::riportalas)) {
        wp_send_json_error(array("message" => "Jogosulatlan hozzáférés"), 403);
    }
    $rendezes_mezo = "helyezes";
    $rendezes_irany = "asc";
    $contest_id = $_POST["contest_id"];
    $tab_id = $_POST["tab_id"];
    if (isset($_POST["rendezes_mezo"]))
        $rendezes_mezo = $_POST["rendezes_mezo"];
    if (isset($_POST["rendezes_irany"]))
        $rendezes_irany = $_POST["rendezes_irany"];

    // A2 szűrők. Üres/0 = nincs szűrés az adott mezőre.
    $szuro_sport_event_id = isset($_POST["sport_event_id"]) && is_numeric($_POST["sport_event_id"]) ? intval($_POST["sport_event_id"]) : 0;
    $szuro_nev            = isset($_POST["athlete_name"]) ? trim(wp_unslash($_POST["athlete_name"])) : '';
    $szuro_van_eredmeny   = isset($_POST["van_eredmeny"]) ? $_POST["van_eredmeny"] : '';
    $szuro_helyezes_tol   = isset($_POST["helyezes_tol"]) && $_POST["helyezes_tol"] !== '' && is_numeric($_POST["helyezes_tol"]) ? intval($_POST["helyezes_tol"]) : null;
    $szuro_helyezes_ig    = isset($_POST["helyezes_ig"]) && $_POST["helyezes_ig"] !== '' && is_numeric($_POST["helyezes_ig"]) ? intval($_POST["helyezes_ig"]) : null;
    $szuro_eredmeny       = isset($_POST["eredmeny_kereses"]) ? trim(wp_unslash($_POST["eredmeny_kereses"])) : '';
    $event_sql = "SELECT vcer.*, vce.event_id AS sport_event_id, vse.sport_event_name AS sport_event_name, vse.result_type as sport_event_result_type,
    vs.result_type as sport_result_type
    FROM vespa_constest_events_results AS vcer
    INNER JOIN vespa_constest_events AS vce ON vce.id=vcer.contest_event_id
    LEFT JOIN vespa_sport_events AS vse ON vse.sport_event_id=vce.event_id
    LEFT JOIN vespa_sports AS vs ON vs.sport_id=vce.sport_id
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


    // Versenyszám-legördülő opciói a verseny összes eseményéből (szűrés előtti halmaz).
    $versenyszam_opciok = array();
    foreach ($event_results as $er) {
        if (!empty($er->sport_event_id) && !isset($versenyszam_opciok[$er->sport_event_id])) {
            $versenyszam_opciok[$er->sport_event_id] = $er->sport_event_name;
        }
    }

    // Szűrő-sor. A vezérlők a Task 2 JS-függvényeit hívják; az értékek a válaszban
    // megőrződnek (esc_attr), így szűrés+rendezés után is láthatóak maradnak.
    $szuro_html  = "<div class='vespa-eredmeny-szuro' style='margin-bottom:8px;'>";
    $szuro_html .= "<select class='vespa-szuro-versenyszam' onchange='vespaSzures($tab_id,$contest_id)'>";
    $szuro_html .= "<option value=''>Összes versenyszám</option>";
    foreach ($versenyszam_opciok as $ev_id => $ev_nev) {
        $kivalasztva = ($szuro_sport_event_id === intval($ev_id)) ? " selected" : "";
        $szuro_html .= "<option value='" . esc_attr($ev_id) . "'$kivalasztva>" . esc_html($ev_nev) . "</option>";
    }
    $szuro_html .= "</select> ";
    $szuro_html .= "<input type='text' class='vespa-szuro-nev' placeholder='Gyermek neve' value='" . esc_attr($szuro_nev) . "'> ";
    $szuro_html .= "<select class='vespa-szuro-eredmeny-letezik'>";
    $szuro_html .= "<option value=''" . ($szuro_van_eredmeny === '' ? ' selected' : '') . ">Van-e eredmény: mind</option>";
    $szuro_html .= "<option value='1'" . ($szuro_van_eredmeny === '1' ? ' selected' : '') . ">Van eredmény</option>";
    $szuro_html .= "<option value='0'" . ($szuro_van_eredmeny === '0' ? ' selected' : '') . ">Nincs eredmény</option>";
    $szuro_html .= "</select> ";
    $szuro_html .= "<input type='number' class='vespa-szuro-helyezes-tol' placeholder='Helyezés-tól' style='width:110px' value='" . esc_attr($szuro_helyezes_tol === null ? '' : $szuro_helyezes_tol) . "'> ";
    $szuro_html .= "<input type='number' class='vespa-szuro-helyezes-ig' placeholder='Helyezés-ig' style='width:110px' value='" . esc_attr($szuro_helyezes_ig === null ? '' : $szuro_helyezes_ig) . "'> ";
    $szuro_html .= "<input type='text' class='vespa-szuro-eredmeny' placeholder='Eredmény' value='" . esc_attr($szuro_eredmeny) . "'> ";
    $szuro_html .= "<button type='button' onclick='vespaSzures($tab_id,$contest_id)'>Szűrés</button> ";
    $szuro_html .= "<button type='button' onclick='vespaSzuroTorles($tab_id,$contest_id)'>Szűrők törlése</button>";
    $szuro_html .= "<input type='hidden' class='vespa-szuro-rendezes-mezo' value='" . esc_attr($rendezes_mezo) . "'>";
    $szuro_html .= "<input type='hidden' class='vespa-szuro-rendezes-irany' value='" . esc_attr($rendezes_irany) . "'>";
    $szuro_html .= "</div>";

    $tabla_html = "<table width='100%'>";
    $volt_talalat = false;
    foreach ($event_results as $event_result) {
        // Versenyszám-szűrő: csak a kiválasztott esemény-blokk marad.
        if ($szuro_sport_event_id > 0 && intval($event_result->sport_event_id) !== $szuro_sport_event_id) {
            continue;
        }
        $eredmenyek = json_decode($event_result->result);
        if (!is_array($eredmenyek)) {
            continue;
        }

        // Soronkénti szűrés (név / van-e eredmény / helyezés-tartomány / eredmény-szöveg).
        $szurt = array();
        foreach ($eredmenyek as $eredmeny) {
            $sportolo = $sportolok[array_search($eredmeny->athlete_id, array_column($sportolok, 'athlete_id'))];

            if ($szuro_nev !== '') {
                $nev = isset($sportolo->athlete_name) ? $sportolo->athlete_name : '';
                if (mb_stripos($nev, $szuro_nev) === false) {
                    continue;
                }
            }
            $van_helyezes = isset($eredmeny->helyezes) && $eredmeny->helyezes !== '' && $eredmeny->helyezes !== null;
            $van_ertek    = isset($eredmeny->eredmeny) && $eredmeny->eredmeny !== '' && $eredmeny->eredmeny !== null;
            if ($szuro_van_eredmeny === '1' && !($van_helyezes || $van_ertek)) {
                continue;
            }
            if ($szuro_van_eredmeny === '0' && ($van_helyezes || $van_ertek)) {
                continue;
            }
            if ($szuro_helyezes_tol !== null || $szuro_helyezes_ig !== null) {
                if (!$van_helyezes || !is_numeric($eredmeny->helyezes)) {
                    continue;
                }
                $h = intval($eredmeny->helyezes);
                if ($szuro_helyezes_tol !== null && $h < $szuro_helyezes_tol) {
                    continue;
                }
                if ($szuro_helyezes_ig !== null && $h > $szuro_helyezes_ig) {
                    continue;
                }
            }
            if ($szuro_eredmeny !== '') {
                $ertek = isset($eredmeny->eredmeny) ? (string) $eredmeny->eredmeny : '';
                if (mb_stripos($ertek, $szuro_eredmeny) === false) {
                    continue;
                }
            }
            $szurt[] = $eredmeny;
        }

        if (empty($szurt)) {
            continue; // üres blokk fejlécét nem írjuk ki
        }
        $volt_talalat = true;
        $eredmenyek = $szurt;

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
        $tabla_html .= "<tr><td colspan=6><h4 class='pl-1'>$event_result->sport_event_name</h4></td></tr>";
        $mertekegyseg = !empty($event_result->sport_event_result_type) ? $event_result->sport_event_result_type : $event_result->sport_result_type;
        $eredmeny_tipus = mertekegyseg_ertek($mertekegyseg);
        $tabla_html .= "<tr>
                <th width='7%' style='cursor: pointer;' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'helyezes', $rendezes_irany, 'Helyezés') . "</th>
                <th width='28%' style='cursor: pointer;' class='pl-1' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'athlete_name', $rendezes_irany, 'Név') . "</th>
                <th width='10%' style='cursor: pointer;' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'birth_date', $rendezes_irany, 'Születési dátum') . "</th>
                <th width='20%' style='cursor: pointer;' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'ins_name', $rendezes_irany, 'Intézmény') . "</th>
                <th width='15%' style='cursor: pointer;' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'ins_state', $rendezes_irany, 'Megye') . "</th>";

        if ($mertekegyseg != 'helyezes') {
            $felirat = "Eredmény ($eredmeny_tipus)";
            $tabla_html .= "<th width='20%' onclick=rendezes($tab_id,$contest_id," . rendezes_irany_kalkulacio($rendezes_mezo, 'eredmeny', $rendezes_irany, $felirat) . "</th>";
        }
        $tabla_html .= "</tr>";
        foreach ($eredmenyek as $kulcs => $eredmeny) :
            $sportolo = $sportolok[array_search($eredmeny->athlete_id, array_column($sportolok, 'athlete_id'))];
            $tabla_html .= "<tr style='border-top: thin solid; border-bottom: thin solid;'><td class='pl-2'>$eredmeny->helyezes</td>
            <td class='pl-1'>$sportolo->athlete_name</td>
            <td>$sportolo->birth_date</td>
            <td>$sportolo->ins_name</td>
            <td>$sportolo->state_name";

            if ($mertekegyseg != 'helyezes')
                $tabla_html .= "<td>$eredmeny->eredmeny</td>";
            $tabla_html .= "</tr>";
        endforeach;
        $tabla_html .= "<tr/>";
    }
    $tabla_html .= "</table>";

    if (!$volt_talalat) {
        $tabla_html = "<div class='pl-1'>Nincs a szűrőknek megfelelő eredmény.</div>";
    }

    wp_send_json(array("success" => true, "html" => $szuro_html . $tabla_html));
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
