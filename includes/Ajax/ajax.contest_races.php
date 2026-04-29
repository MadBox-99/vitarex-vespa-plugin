<?php
function ajax_table_contest_races_display($id = null)
{
    global $wpdb;

    if ($id == null) {
        $id = $_POST['contest_id'];
    }



    ob_start();
    $sqlList = "SELECT e.*, p.sport_name, s.sport_event_name, p.sport_id, (SELECT GROUP_CONCAT(disability_group_name SEPARATOR ',') FROM vespa_disability_groups WHERE FIND_IN_SET(disability_group_id, e.disability_groups) ) as disgroup
                                    FROM vespa_constest_events as e
                                    LEFT JOIN vespa_sports as p ON p.sport_id = e.sport_id
                                    LEFT JOIN vespa_sport_events as s ON s.sport_event_id = e.event_id
                                    WHERE e.contest_id= %d";

    $list = $wpdb->get_results($wpdb->prepare($sqlList, $id));
    

    if (empty($list)) {
        wp_send_json(array("success" => true, 'html' => '<table class="table table-striped" id="ajax_table_contest_races"><tr><td>Nincs megjeleníthető adat</td></tr></table>'));
        exit;
    }
?>
    <table class="table table-striped" id="ajax_table_contest_races">
        <thead>
            <tr>
                <th>Sport</th>
                <th>Versenyszám</th>
                <th width="300">&nbsp;</th>
            </tr>
        </thead>

        <tbody>
            <?php
            foreach ($list as $item) :
                $result_url = admin_url('admin.php?page=contests') . '&action=results&id=' . $id . '&race_id=' . $item->id;
            ?>
                <tr class="contest_races-item" data-event_id="<?php echo $item->event_id; ?>" data-sport_id="<?php echo $item->sport_id; ?>" data-id="<?php echo $item->id; ?>">
                    <td><?php echo $item->sport_name ?: '<em>(törölt sportág #' . (int)$item->sport_id . ')</em>'; ?></td>
                    <td><?php echo $item->sport_event_name ?: '<em>(törölt versenyszám #' . (int)$item->event_id . ')</em>'; ?></td>
                    <td><?php echo $item->gender; ?></td>
                    <td><?php echo $item->disgroup; ?></td>
                    <td><?php echo $item->dfrom . ' - ' . $item->dto; ?></td>
                    <td>
                        <?php if (current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles)) : ?>
                            <button class="btn btn-default btn-sm" onclick="deleteContestRace( <?php echo $item->id; ?> );">Töröl</button>
                        <?php endif; ?>
                    </td>

                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php

    $html = ob_get_contents();
    ob_get_clean();

    wp_send_json(array("success" => true, 'html' => $html));
}

add_action('wp_ajax_vespa_ajax_table_contest_races', 'ajax_table_contest_races_display');

//---------------------
function ajax_table_contest_races_add_record()
{
    global $wpdb;

    if(! current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles )){
        wp_send_json_error(array("message" => "Jogosulatlan hozzáférés"), 403);
    }

    // find min and max date
    $agnames = array();
    $date_from  = '';
    $date_to    = '';

    $age_groups = array_map('intval', explode(',', $_POST['agegroups']));
    $placeholders = implode(',', array_fill(0, count($age_groups), '%d'));

    $sqlAgeGroups = "SELECT vca.*, va.agegroup_name 
                        FROM vespa_contest_agegroups as vca 
                        JOIN vespa_agegroups as va ON va.agegroup_id = vca.agegroup_id 
                        WHERE vca.id IN ($placeholders) 
                        ORDER BY vca.agegroup_id ASC";
    
    $age_groups = $wpdb->get_results($wpdb->prepare($sqlAgeGroups, ...$age_groups));
    
    

    $sport_id = $_POST['sport_id'];
    $gender = $_POST['gender'];
    $actual_contest_id = $_POST['contest_id'];
    $disabilities = $_POST['disabilities'];

    $reqData = array('sport_id', 'gender', 'contest_id', 'disabilities');
    foreach($reqData as $row){
        if(!isset($_POST[$row])){
            wp_send_json_error(array("message" => "Érvénytelen beviteli adat: $row"), 400);
        }
    }

    $contest = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_contests WHERE contest_id=%d",$actual_contest_id));
    if(!isset($contest)){
        wp_send_json_error(array("message" => "Érvénytelen verseny"), 404);
    }
    if($contest->contest_type == 3 && // megyei
        (VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::MEGYEI_VEZETO) || 
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::MEGYEI_VERSENYIGAZGATO))
    ){ 
        $stateId = vespa_get_my_state_id();
        if($contest->state_id != $stateId)
            wp_send_json_error(array("message" => "Jogosulatlan hozzáférés"), 403);
    } 

    foreach ($age_groups as $ag) {
        if ($date_from == '' || $date_from != '' && strtotime($date_from) > strtotime($ag->date_from)) {
            $date_from = $ag->date_from;

            if (!in_array($ag->agegroup_name, $agnames)) {
                $agnames[] = $ag->agegroup_name;
            }
        }

        if ($date_to == '' || $date_to != '' && strtotime($date_to) < strtotime($ag->date_to)) {
            $date_to = $ag->date_to;

            if (!in_array($ag->agegroup_name, $agnames)) {
                $agnames[] = $ag->agegroup_name;
            }
        }
    }
    //létező rekordok ellenőrzése
    $sql_list = "SELECT e.*, p.sport_name, s.sport_event_name, p.sport_id,
    (SELECT GROUP_CONCAT(disability_group_name SEPARATOR ',') FROM vespa_disability_groups WHERE FIND_IN_SET(disability_group_id, e.disability_groups) ) as disgroup
     FROM vespa_constest_events as e
     LEFT JOIN vespa_sports as p ON p.sport_id = e.sport_id
     LEFT JOIN vespa_sport_events as s ON s.sport_event_id = e.event_id
     WHERE e.contest_id= %d";
     

    $list = $wpdb->get_results($wpdb->prepare($sql_list, $actual_contest_id));
     


    $eventIds = $_POST['event_ids'];
    $ids = explode(',', $eventIds);
    
     $sql_sport_events = "SELECT * FROM vespa_sport_events WHERE sport_id= %d AND is_deleted=0";
     $sportEvents = $wpdb->get_results($wpdb->prepare($sql_sport_events, $sport_id));
     
    if(isset($sportEvents) && count($sportEvents) > 0){
        if(empty($eventIds) || count($ids) == 0){
            wp_send_json_error(array("message" => "Az adott verenyhez versenyszám választása kötelező"), 400);
        }
    }

    if($eventIds){
        foreach ($ids as $event_id) {
            if (array_reduce($list, function ($previous, $current) use ($date_from, $date_to, $event_id, $agnames, $gender, $disabilities, $sport_id) {
                if (
                    $current->dfrom == $date_from
                    && $current->dto == $date_to
                    && $current->gender == $gender
                    && $current->disability_groups == $disabilities
                    && $current->sport_id == $sport_id
                    && $current->event_id == $event_id
                    && $current->agnames == implode(",", $agnames)
                )
                    wp_send_json_error(array("message" => "A versenyek között már szerepel ilyen kiírás: $current->sport_event_name, " . implode(",", $agnames) . ", $current->disgroup"), 500);
            })) {
            }
        }
    
        foreach ($ids as $event_id) {
            $success = $wpdb->insert('vespa_constest_events', array(
                'contest_id'        => $actual_contest_id,
                'sport_id'          => $sport_id,
                'dfrom'             => $date_from,
                'dto'               => $date_to,
                'gender'            => $gender,
                'disability_groups' => $disabilities,
                'event_id'          => $event_id,
                'agnames'          => implode(",", $agnames),
            ), array('%s'));
        }
    }
    else{
        if (array_reduce($list, function ($previous, $current) use ($date_from, $date_to, $agnames, $gender, $disabilities, $sport_id) {
            if (
                $current->dfrom == $date_from
                && $current->dto == $date_to
                && $current->gender == $gender
                && $current->disability_groups == $disabilities
                && $current->sport_id == $sport_id
                && $current->event_id == null
                && $current->agnames == implode(",", $agnames)
            )
                wp_send_json_error(array("message" => "A versenyek között már szerepel ilyen kiírás: $current->sport_event_name, " . implode(",", $agnames) . ", $current->disgroup"), 500);
        })) {
        }
        $success = $wpdb->insert('vespa_constest_events', array(
            'contest_id'        => $actual_contest_id,
            'sport_id'          => $sport_id,
            'dfrom'             => $date_from,
            'dto'               => $date_to,
            'gender'            => $gender,
            'disability_groups' => $disabilities,
            'event_id'          => null,
            'agnames'          => implode(",", $agnames),
        ), array('%s'));
    }

    wp_send_json(array("success" => true));
}
add_action('wp_ajax_vespa_ajax_table_contest_races_add', 'ajax_table_contest_races_add_record');

//---------------------
function ajax_table_contest_races_modify_record()
{
    global $wpdb;

    if( ! current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles ) ){
        wp_send_json_error( array("message" => "Jogosulatlan hozzáférés"), 403 );
    }

    $success = $wpdb->update('vespa_constest_events', array(
        'event_id'    => intval($_POST['event_id']),
    ), array('id' => intval($_POST['id'])), array('%d'));

    wp_send_json(array("success" => ($success == 1 ? true : false)));
}
add_action('wp_ajax_vespa_ajax_table_contest_races_modify', 'ajax_table_contest_races_modify_record');

//---------------------
function ajax_table_contest_races_delete_record()
{
    global $wpdb;

    if( ! current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles ) ){
        wp_send_json_error( array("message" => "Jogosulatlan hozzáférés"), 403 );
    }

    $success = $wpdb->delete('vespa_constest_events', array('id' => intval($_POST['id'])), array('%d'));

    wp_send_json(array("success" => ($success == 1 ? true : false)));
}
add_action('wp_ajax_vespa_ajax_table_contest_races_delete', 'ajax_table_contest_races_delete_record');


//---------------------

function ajax_table_contest_races_finalize()
{
    global $wpdb;

    $contest_id = is_numeric($_POST['contest_id']) ? $_POST['contest_id'] : -1;
    $sendEmail = is_numeric($_POST['emails']) ? true : false;

    if(! current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles )){
        wp_send_json_error(array("message" => "Jogosulatlan hozzáférés"), 403);
    }
    $contest = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_contests WHERE contest_id=%d",$contest_id));
    if(!isset($contest)){
        wp_send_json_error(array("message" => "Érvénytelen verseny"), 404);
    }

    if (!(
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::MEGYEI_VERSENYIGAZGATO)
        || VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::FOVESZ_FODISZ_SPORTIGAZGATO)
        || VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ADMINISZTRATOR)
        || is_super_admin()
    )) {
        if($contest->contest_type == 3 && // megyei
        (VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::MEGYEI_VEZETO) || 
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::MEGYEI_VERSENYIGAZGATO))
        ){ 
            $stateId = vespa_get_my_state_id();
            if($contest->state_id != $stateId) {
                echo 'Nincs megfelelő jogosultságod a művelethez.';
                die;
            }
        }      
    }

    $success = $wpdb->update('vespa_contests', array(
        'is_final'    => 1,
    ), array('contest_id' => $_POST['contest_id']), array('%s'));

    if($sendEmail) {
        $usersTable = $wpdb->prefix . 'users';
        $sql = "SELECT wpu.user_email, wpu.display_name FROM vespa_athlete_entries as ae 
                    JOIN $usersTable as wpu ON (wpu.ID=ae.user_id)                
                    WHERE ae.contest_id=%d
                    GROUP BY ae.user_id";

        $list = $wpdb->get_results($wpdb->prepare($sql, $contest_id));
        



        $sql_escort_list = "SELECT * FROM vespa_contests_escorts WHERE contest_id= %d";
        $escortList = $wpdb->get_results($wpdb->prepare($sql_escort_list, $contest_id));
        


        $emailList = array();
        foreach ($list as $item) {
            if(filter_var($item->user_email, FILTER_VALIDATE_EMAIL)) {
                array_push($emailList, $item->user_email);
            }
        }
        foreach ($escortList as $escortItem) {
            $data = unserialize($escortItem->escort_data);
            $dataValid = !is_bool($data);

            if ($dataValid && !empty($data['kiserok'])) {
                foreach ($data['kiserok'] as $gk) {   
                    if(filter_var($gk['email'], FILTER_VALIDATE_EMAIL)) {
                        array_push($emailList, $gk['email']);
                    }         
                }
            }   
            if ($dataValid && !empty($data['gepkocsivezetok'])) {
                foreach ($data['gepkocsivezetok'] as $gk) {
                    if(filter_var($gk['email'], FILTER_VALIDATE_EMAIL)) {
                        array_push($emailList, $gk['email']);
                    }
                }
            }
        }
        $emailList = array_unique($emailList);
        $filepath = vespa_download_listing($contest_id, false, true);
        $sendSucc = 0;
        try {
            foreach ($emailList as $to){
                $subject = "Változás a '$contest->contest_name' versenyben!";
                $body = "
                <h3>Kedves jelentkezett!</h3>
                <p>Csatolmányban elérhető az új versenykiírás.</p>
                </br>
                VESPA csapata
                ";
                $headers = array( 'Content-Type: text/html; charset=UTF-8' );
                wp_mail( $to, $subject, $body, $headers, array( $filepath ) );
                $sendSucc++;
            }
            vitarex_log("send-email", json_encode([
                "action" => "Versenykiírás email küldés - $contest_id verseny",
                "sikeres" => $sendSucc,
                "ossz" => count($emailList),
            ]));
        } catch (\Throwable $ex) {
            vitarex_log("send-email-error", json_encode([
                "action" => "Versenykiírás email küldés - $contest_id verseny",
                "sikeres" => $sendSucc,
                "ossz" => count($emailList),
                "error" => $ex->getMessage()
            ]));
            $err = $ex->getMessage();
            $all = count($emailList);
            wp_send_json_error(array("message" => "Hiba az email küldés során. Sikeresen elküldött emailek száma: $sendSucc/$all. Hiba: $err"), 500);
        }
    }

    wp_send_json(array("success" => ($success == 1 ? true : false), "url" =>  admin_url('admin.php?page=contests&action=view&id=') . $_POST['contest_id']));
}
add_action('wp_ajax_vespa_ajax_table_contest_races_finalize', 'ajax_table_contest_races_finalize');
