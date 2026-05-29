<div class="wrap">
    <div class="row">
        <div class="col-md-8">
            <h1 class="site-title">Versenyek</h1>
        </div>

        <?php if (current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles)) : ?>
            <div class="col-md-4">
                <a href="<?php echo admin_url('admin.php?page=contests&id=0'); ?>" class="btn btn-sm btn-primary pull-right">
                    <i class="fa fa-plus" aria-hidden="true"></i>
                    ÚJ
                </a>
            </div>
        <?php endif; ?>



    </div>


    <?php
    global $wpdb;
    date_default_timezone_set('Europe/Budapest');
    #region frissítések
    //korcsoport frissítés
    // if (isset($_REQUEST['contest_id'])) {
    //     global $wpdb;
    //     $contest_id = $_REQUEST["contest_id"];
    //     $contest_events
    //         =
    //         $wpdb
    //         ->get_results(
    //             "SELECT id, contest_id, dfrom, dto FROM vespa_constest_events WHERE contest_id=$contest_id"
    //         );

    //     //echo print_r($contest_events);
    //     foreach ($contest_events as $contest_event) {
    //         $agegroups = $wpdb
    //             ->get_results(
    //                 "SELECT ag.agegroup_name, cag.date_from, cag.date_to FROM vespa_contest_agegroups cag JOIN vespa_agegroups ag ON cag.agegroup_id=ag.agegroup_id  WHERE contest_id=$contest_event->contest_id"
    //             );
    //         $keresett_agegroup_tomb = array_filter($agegroups, function ($agegroup) use ($contest_event) {
    //             return strtotime($agegroup->date_from) >= strtotime($contest_event->dfrom)
    //                 && strtotime($agegroup->date_to) <= strtotime($contest_event->dto);
    //         });
    //         //echo print_r($keresett_agegroup_tomb);
    //         $ag_nevek = implode(", ", array_column($keresett_agegroup_tomb, "agegroup_name"));
    //         $contest_event->agnames = $ag_nevek;
    //         //save
    //         echo $ag_nevek . "<br/>";
    //         $success = $wpdb->update(
    //             "vespa_constest_events",
    //             array(
    //                 'agnames'              => $ag_nevek
    //             ),
    //             array(
    //                 'id' =>  $contest_event->id
    //             ),
    //             array(
    //                 '%s'
    //             )
    //         );
    //     }
    // }
    //fogyatékosság frissítés
    //

    // if (isset($_REQUEST['contest_id'])) {
    //     global $wpdb;
    //     $contest_id = $_REQUEST["contest_id"];
    //     $query_select
    //         =
    //         $wpdb
    //         ->prepare(
    //             "SELECT id FROM

    // vespa_constest_events WHERE contest_id = %d",
    //             $contest_id
    //         );
    //     $ce_ids
    //         =
    //         $wpdb
    //         ->get_col(
    //             $query_select
    //         );
    //     // Loop through the result set
    //     foreach ($ce_ids
    //         as
    //         $ce_id) {
    //         // Prepare and execute UPDATE query
    //         $query_update
    //             =
    //             $wpdb
    //             ->prepare(
    //                 "UPDATE    
    // vespa_constest_events vce SET sport_id = (SELECT se.sport_id FROM    
    // vespa_sport_events se JOIN    
    // vespa_constest_events ce ON se.sport_event_id = ce.event_id AND ce.id = %d WHERE ce.contest_id = %d) WHERE vce.contest_id = %d",
    //                 $ce_id,
    //                 $contest_id,
    //                 $contest_id
    //             );
    //         $wpdb
    //             ->query(
    //                 $query_update
    //             );
    //     }
    //     echo "UPDATE 1 KÉSZ";

    //     $query_select
    //         =
    //         $wpdb
    //         ->prepare(
    //             "SELECT id FROM        
    //     vespa_athlete_entries WHERE contest_id = %d",
    //             $contest_id
    //         );
    //     $ae_ids
    //         =
    //         $wpdb
    //         ->get_col(
    //             $query_select
    //         );
    //     // Loop through the result set
    //     foreach ($ae_ids
    //         as
    //         $ae_id) {
    //         // Prepare and execute UPDATE query
    //         $query_update
    //             =
    //             $wpdb
    //             ->prepare(
    //                 "UPDATE        
    //     vespa_athlete_entries vae SET vae.contest_event_id = (SELECT ce.id FROM

    //     vespa_athlete_entries ae JOIN

    //     vespa_constest_events ce ON ae.event_id = ce.event_id AND ae.contest_id = ce.contest_id JOIN

    //     vespa_athletes a ON a.athlete_id = ae.athlete_id WHERE FIND_IN_SET(a.disability_type, ce.disability_groups) AND a.birth_date >= ce.dfrom AND a.birth_date <= ce.dto AND ae.id = %d ORDER BY ce.id LIMIT 1) WHERE vae.contest_id = %d AND vae.id = %d",
    //                 $ae_id,
    //                 $contest_id,
    //                 $ae_id
    //             );
    //         $wpdb
    //             ->query(
    //                 $query_update
    //             );
    //     }
    //     echo "UPDATE 2 KÉSZ";
    // }

    #endregion

    $list = array();
    $filter = '1';

    $date = date('Y-m-d H:i:s');

    if (
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::TESTNEVELO) ||
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ISKOLAIGAZGATO)
    ) {
        $school_id =  get_user_meta(get_current_user_id(), 'school_id', true);
        $school = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_institutions WHERE institution_id =%d",$school_id));
        $state_id = isset($school) ? $school->ins_state : 0;
        $filter = " ((vc.contest_type = 3 AND vc.state_id = $state_id) OR vc.contest_type != 3) AND vc.end_at >= '$date' AND vc.is_final = 1"; // contest_type 3 megyei
    }

    if (
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::MEGYEI_VERSENYIGAZGATO) ||
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::MEGYEI_VEZETO)
    ) {
        $state_id =  get_user_meta(get_current_user_id(), 'state_id', true) ?? 0;
        $state_id = isset($state_id) && is_numeric($state_id) ? $state_id : 0;
        $filter = " (vc.contest_type = 3 AND vc.state_id = $state_id) OR vc.contest_type != 3";
    }
    $series = $wpdb->get_results("SELECT * FROM `vespa_series`");
    foreach ($series as $s) {
        $sId = $s->series_id;
        
        if(isset($_POST["orszagos_szures_submit_$sId"]) || isset($_POST["megyei_szures_submit_$sId"])  || isset($_POST["regionalis_szures_submit_$sId"])){
            $_POST["orszagos_fogyatekossagi_csoport_id"] = $_POST["orszagos_fogyatekossagi_csoport_id_$sId"];
            $_POST["orszagos_sportag_id"] = $_POST["orszagos_sportag_id_$sId"];
            $_POST["megyei_sportag_id"] = $_POST["megyei_sportag_id_$sId"];
            $_POST["regionalis_sportag_id"] = $_POST["regionalis_sportag_id_$sId"];
            $_POST["megyei_fogyatekossagi_csoport_id"] = $_POST["megyei_fogyatekossagi_csoport_id_$sId"];
            $_POST["megye_id"] = $_POST["megye_id_$sId"];
        }
    }
    $orszagos_sportag_id = 0;
    $megyei_sportag_id = 0;
    $regionalis_sportag_id = 0;

    $megye_id = $orszagos_fogyatekossagi_csoport_id = $megyei_fogyatekossagi_csoport_id = 0;

    
    if (isset($_POST["orszagos_szures_torles"])){
        $_POST["orszagos_fogyatekossagi_csoport_id"] = 0;
        $_POST["orszagos_sportag_id"] = 0;
    }

    if (isset($_POST["megyei_szures_torles"])){
        $_POST["megyei_fogyatekossagi_csoport_id"] = 0;
        $_POST["megyei_sportag_id"] = 0;
    }

    if (isset($_POST["regionalis_szures_torles"])){
        $_POST["regionalis_sportag_id"] = 0;
    }

    if (isset($_POST["megye_id"]) && !isset($_POST["megyei_szures_torles"])) {
        $megye_id = $_POST['megye_id'];
        if ($megye_id > 0){
            $megye_id = intval($megye_id);
            $filter .= " AND (vc.contest_type=1 OR vc.contest_type=2 OR vc.state_id=$megye_id)";
        }
            
    }

    $orszagos_sportag_id = $_POST["orszagos_sportag_id"];
    $megyei_sportag_id = $_POST["megyei_sportag_id"];
    $regionalis_sportag_id = $_POST["regionalis_sportag_id"];
    $orszagos_fogyatekossagi_csoport_id = $_POST['orszagos_fogyatekossagi_csoport_id'];
    $megyei_fogyatekossagi_csoport_id = $_POST['megyei_fogyatekossagi_csoport_id'];

    $orszagos_fogyatekossagi_csoport_id_filter = esc_sql($_POST['orszagos_fogyatekossagi_csoport_id']);
    $megyei_fogyatekossagi_csoport_id_filter = esc_sql($_POST['megyei_fogyatekossagi_csoport_id']);

    $megyei_fogyatekossagi_csoport_id_filter = "%,$megyei_fogyatekossagi_csoport_id_filter,%";
    $orszagos_fogyatekossagi_csoport_id_filter = "%,$orszagos_fogyatekossagi_csoport_id_filter,%";
    
    $orszagosSportFilter = '';
    if ($orszagos_sportag_id > 0) {
        $orszagos_sportag_id = intval($orszagos_sportag_id) ;
        $orszagosSportFilter = " AND sport_id = $orszagos_sportag_id ";
    }

    $megyeiSportFilter = '';
    if ($megyei_sportag_id > 0) {
        $megyei_sportag_id = intval($megyei_sportag_id);
        $megyeiSportFilter = " AND sport_id = $orszagos_sportag_id ";
    }
    $regionalisSportFilter = '';
    if ($regionalis_sportag_id > 0) {
        $regionalis_sportag_id = intval($regionalis_sportag_id) ;
        $regionalisSportFilter =  "AND sport_id = $regionalis_sportag_id" ;
    }

    if ($orszagos_fogyatekossagi_csoport_id > 0 && $megyei_fogyatekossagi_csoport_id > 0) {

        $filter .= " 
            AND (
                vc.contest_id IN (
                    SELECT contest_id 
                    FROM vespa_constest_events 
                    WHERE contest_type=1 
                    $orszagosSportFilter
                    AND CONCAT(',',disability_groups,',') LIKE '$orszagos_fogyatekossagi_csoport_id_filter'
                )
                OR 
                vc.contest_id IN (
                    SELECT contest_id 
                    FROM vespa_constest_events 
                    WHERE contest_type=3 
                    $megyeiSportFilter
                    AND CONCAT(',',disability_groups,',') LIKE '$megyei_fogyatekossagi_csoport_id_filter'
                )            OR 
                vc.contest_id IN (
                    SELECT contest_id 
                    FROM vespa_constest_events 
                    WHERE contest_type=2 
                    AND sport_id = $regionálisSportFilter
                    
                )
            )
        ";
    }else if ($orszagos_fogyatekossagi_csoport_id > 0 && $megye_fogyatekossagi_csoport_id == 0) {

        $filter .= "
            AND (
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=3
                     $megyeiSportFilter
                )
                OR
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=1
                    $orszagosSportFilter
                    AND CONCAT(',',disability_groups,',') LIKE '$orszagos_fogyatekossagi_csoport_id_filter'
                )            OR 
                vc.contest_id IN (
                    SELECT contest_id 
                    FROM vespa_constest_events 
                    WHERE contest_type=2 
                    $regionálisSportFilter
                    
                )
            )
        ";
    }else if ($megyei_fogyatekossagi_csoport_id > 0 && $orszagos_fogyatekossagi_csoport_id == 0) {

        $filter .= "
            AND (
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=1
                    $orszagosSportFilter
                )
                OR
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=3
                    $megyeiSportFilter
                    AND CONCAT(',',disability_groups,',') LIKE '$megyei_fogyatekossagi_csoport_id_filter'
                )            OR 
                vc.contest_id IN (
                    SELECT contest_id 
                    FROM vespa_constest_events 
                    WHERE contest_type=2 
                    $regionálisSportFilter
                    
                )
            )
        ";
    }else if ($orszagos_sportag_id > 0 && $megyei_sportag_id == 0 && $regionalis_sportag_id == 0) {
        $filter .= "
            AND (
                vc.contest_type=3
                OR
                vc.contest_type=2
                OR
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=1
                    $orszagosSportFilter
                )
            )
        ";
    }else if ($megyei_sportag_id > 0 && $orszagos_sportag_id == 0 && $regionalis_sportag_id == 0) {
        $filter .= "
            AND (
                vc.contest_type=1
                OR
                vc.contest_type=2
                OR
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=3
                    $megyeiSportFilter
                )
            )
        ";
    }else if ($megyei_sportag_id > 0 && $orszagos_sportag_id > 0 && $regionalis_sportag_id == 0) {
        $filter .= " 
            AND (            
                vc.contest_type=2
                OR
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=1
                    $orszagosSportFilter
                )
                OR
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=3
                    $megyeiSportFilter
                )
            )
        ";
    }else if ($orszagos_sportag_id == 0 && $megyei_sportag_id == 0 && $regionalis_sportag_id > 0) {
        $filter .= "
            AND (
                vc.contest_type=3
                OR
                vc.contest_type=1
                OR
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=2
                    $regionalisSportFilter
                )
            )
        ";
    }else if ($megyei_sportag_id > 0 && $orszagos_sportag_id == 0 && $regionalis_sportag_id > 0) {
        $filter .= "
            AND (
                vc.contest_type=1
                OR
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=2
                    $regionalisSportFilter
                )
                OR
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=3
                    $megyeiSportFilter
                )
            )
        ";
    }else if ($megyei_sportag_id == 0 && $orszagos_sportag_id > 0 && $regionalis_sportag_id > 0) {
        $filter .= " 
            AND (
                vc.contest_type=3
                OR
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=1
                    $orszagosSportFilter
                )
                OR
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=2
                    $regionalisSportFilter
                )
            )
        ";
    }else if ($megyei_sportag_id > 0 && $orszagos_sportag_id > 0 && $regionalis_sportag_id > 0) {
        $filter .= " 
            AND (
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=1
                    $orszagosSportFilter
                )
                OR
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=2
                     $regionalisSportFilter
                )
                OR
                vc.contest_id IN (
                    SELECT contest_id
                    FROM vespa_constest_events
                    WHERE contest_type=3
                    $megyeiSportFilter
                )
            )
        ";
    }

    if (!empty($filter)) {
        $filter .= " OR vc.contest_type=4";
    } else {
        $filter = "vc.contest_type=4";
    }
    
    $sql = "SELECT vc.*, vct.contest_type_name, vs.state_name, count(ae.id) as resztvevok_szama  
            FROM vespa_contests as vc 
            LEFT JOIN vespa_contest_types as vct ON ( vct.contest_type_id=vc.contest_type) 
            LEFT JOIN vespa_states as vs ON ( vs.state_id=vc.state_id)
            LEFT JOIN vespa_athlete_entries ae on ae.contest_id=vc.contest_id
            WHERE $filter
            GROUP BY vc.contest_id
            ORDER BY vct.contest_type_id ASC, vc.start_at DESC, vs.state_name ASC, vc.contest_name ASC";
    $list = $wpdb->get_results($wpdb->prepare($sql));
    $megye_lista = $wpdb->get_results("SELECT * FROM vespa_states ORDER BY state_name ASC");
    $fogyatekossag_lista = $wpdb->get_results("SELECT * FROM vespa_disability_groups ORDER BY disability_group_name ASC");
    $sportag_lista = $wpdb->get_results("SELECT * FROM vespa_sports WHERE is_deleted=0 ORDER BY vespa_sports.sport_name");

    $versenyek_ev_szerint = [];
    $aktualis_ev = end($series);
    if (isset($_POST["selected_tab"])) {
        foreach ($series as $s) {
            if ($s->series_id == $_POST["selected_tab"]) {
                $aktualis_ev = $s;
                break;
            }
        }
    }
    foreach ($list as $verseny) {
    $versenyek_ev_szerint[$verseny->contest_series][] = $verseny;
    }
    //rendezés
    ksort($versenyek_ev_szerint);
    $disablityList = $wpdb->get_results("SELECT * FROM vespa_disability_groups");

    function mapIdsToNames($splitString, $objects)
    {
        $result = [];
        foreach ($splitString as $id) {
            foreach ($objects as $object) {
                if ($object->disability_group_id == $id) {
                    $result[] = $object->disability_group_name;
                    break;
                }
            }
        }
        return $result;
    }


    function getContestsByType($versenyek, $typeName) {
        return array_filter($versenyek, function($item) use ($typeName) {
            return $item->contest_type_name === $typeName;
        });
    }


    ?>

    <form action="admin.php?page=contests" method="post">
    <input type="hidden" name="selected_tab" id="selected_tab" value="<?php echo $_POST['selected_tab'] ?>">

        <div class="w3-bar w3-black">
            <?php 
 
            foreach ($series as $ev) : ?>
                <?php
                
                $btn_class = "w3-bar-item w3-button tablink";
                if ($ev->series_id == $aktualis_ev->series_id) {
                    $btn_class .= " w3-red";
                }
                ?>
                <button 
                type="button" 
                class="<?php echo $btn_class; ?>" 
                onclick="tabKivalasztas(event, <?php echo $ev->series_id; ?>)"
                data-tab-id="<?php echo $ev->series_id; ?>"
                >
                <?php echo $ev->series_name; ?>
                </button>

            <?php endforeach; ?>

        </div>
            <button 
                type="button" 
                class="export_button btn btn-primary mb-2 mt-2" 
            >
                Export
            </button>

        <?php foreach ($series as $ev) : ?>
            <?php
            $displayStyle = ($ev->series_id == $aktualis_ev->series_id) ? "display: block" : "display: none";
            ?>
            <div id="<?php echo $ev->series_id; ?>" class="w3-container w3-border w3-tabarea" style="<?php echo $displayStyle; ?>">
                <?php 
                if (!isset($versenyek_ev_szerint[$ev->series_id])) {
                    $versenyek_ev_szerint[$ev->series_id] = [];
                }

                // 1) Országos táblázat 
                $orszagosVersenyek = getContestsByType($versenyek_ev_szerint[$ev->series_id], 'Országos');
                ?>
                <h1 class="title">Országos</h1>
                <div class="m2 szurok orszagos_szuro" style="display: flex; align-items: center;">

                    Fogyatékossági csoport
                    <select name="orszagos_fogyatekossagi_csoport_id_<?php echo $ev->series_id; ?>" id="orszagos_fogyatekossagi_csoport_id" class="form-control" style="width: 150px;margin: 8px 16px;">
                        <option value="0">Nincs szűrés</option>
                        <?php
                        foreach ($fogyatekossag_lista as $fogyatekossagi_csoport) {
                            $selected = (isset($orszagos_fogyatekossagi_csoport_id) && $orszagos_fogyatekossagi_csoport_id == $fogyatekossagi_csoport->disability_group_id ? 'selected' : '');
                            echo '<option value="' . $fogyatekossagi_csoport->disability_group_id . '" ' . $selected . '>' . $fogyatekossagi_csoport->disability_group_name . '</option>';
                        }
                        ?>
                    </select>
                    Sportág
                    <select name="orszagos_sportag_id_<?php echo $ev->series_id; ?>" id="orszagos_sportag_id" class="form-control" style="width: 150px;margin: 8px 16px;">
                        <option value="0">Nincs szűrés</option>
                        <?php
                        foreach ($sportag_lista as $sportag) {
                            $selected = (isset($orszagos_sportag_id) && $orszagos_sportag_id == $sportag->sport_id ? 'selected' : '');
                            echo '<option value="' . $sportag->sport_id . '" ' . $selected . '>' . $sportag->sport_name . '</option>';
                        }
                        ?>
                    </select>
                    <button class="btn ml-2 mr-1 " type="submit" name="orszagos_szures_submit_<?php echo $ev->series_id; ?>">Szűrés</button>
                    <button class="btn my-2" type="submit" id="orszagos_szures_torles" name="orszagos_szures_torles">Szűrés törlése</button>
                </div>
                <?php if (count($orszagosVersenyek) > 0) : ?>

                    <table 
                        id="orszagos_table_<?php echo $ev->series_id; ?>" 
                        class="table table-striped display" 
                        style="width:100%"
                        data-type="Országos"
                    >
                        <thead>

                            <tr>
                                <th>Azon.</th>
                                <th>Versenynév</th>
                                <th>Kiírás</th>
                                <th>Típus</th>
                                <th>Kezdete</th>
                                <th>Vége</th>
                                <th>Helyszín</th>
                                <th>Cím</th>
                                <th>Fogy. csoport</th>
                                <th class="no-export">Műveletek</th>
                            </tr>

                        </thead>
                        <tbody>
                            <?php foreach ($orszagosVersenyek as $race) : ?>
                                <?php
                                $res = $wpdb->get_results($wpdb->prepare("SELECT disability_groups FROM vespa_constest_events WHERE contest_id=%d",$race->contest_id));
                                $disablities = [];
                                foreach ($res as $row) {
                                    foreach (explode(',', $row->disability_groups) as $group) {
                                        $disablities[] = $group;
                                    }
                                }
                                $disablities = array_unique($disablities);

                                $resztvevok_szama = $race->resztvevok_szama;
                                ?>
                                <tr id="contest<?php echo $race->contest_id; ?>">
                                    <td><?php echo $race->contest_id; ?></td>
                                    <td style="background-color: <?php echo vespa_contest_status_color($race); ?>">
                                        <a style="color:black;" href="<?php echo admin_url('admin.php?page=contests&action=view&id=' . $race->contest_id); ?>">
                                            <?php echo stripslashes($race->contest_name); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="<?php echo home_url(); ?>?download_contest_docs=listing&contest_id=<?php echo $race->contest_id; ?>" download>Kiírás</a>
                                    </td>
                                    <td><?php echo $race->contest_type_name; ?></td>
                                    <td><?php echo $race->start_at; ?></td>
                                    <td><?php echo $race->end_at; ?></td>
                                    <td><?php echo $race->place_name; ?></td>
                                    <td><?php echo $race->place_address; ?></td>
                                    <td>
                                        <?php 
                                            $mapped = mapIdsToNames($disablities, $fogyatekossag_lista);
                                            echo implode(', ', $mapped); 
                                        ?>
                                    </td>
                                    <td class="no-export">
                                        <div style="display: flex;">
                                            <div style="font-size: 20px; cursor: pointer;" class="tooltip mx-2">
                                                &#128712;
                                                <span class="tooltiptext">
                                                    Résztvevők száma: <?php echo $resztvevok_szama; ?>
                                                </span>
                                            </div>
                                                <button class="btn btn-danger delete-contest-btn" data-id="<?php echo $race->contest_id; ?>">Törlés</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <h3 class="no_result">Nincsen országos találat.</h3>
                <?php endif; ?>

                <hr>

                <!-- 2) Megyei táblázat -->
                <?php 
                
                $megyeiVersenyek = getContestsByType($versenyek_ev_szerint[$ev->series_id], 'Megyei'); 
                
                ?>
                
                <h1  class="title">Megyei</h1>
                <div class="m2 szurok megyei_szuro" style="display: flex; align-items: center;">
                    Fogyatékossági csoport
                    <select name="megyei_fogyatekossagi_csoport_id_<?php echo $ev->series_id; ?>" id="megyei_fogyatekossagi_csoport_id" class="form-control" style="width: 150px;margin: 8px 16px;">
                        <option value="0">Nincs szűrés</option>
                        <?php
                        foreach ($fogyatekossag_lista as $fogyatekossagi_csoport) {
                            $selected = (isset($megyei_fogyatekossagi_csoport_id) && $megyei_fogyatekossagi_csoport_id == $fogyatekossagi_csoport->disability_group_id ? 'selected' : '');
                            echo '<option value="' . $fogyatekossagi_csoport->disability_group_id . '" ' . $selected . '>' . $fogyatekossagi_csoport->disability_group_name . '</option>';
                        }
                        ?>
                    </select>
                    Megye

                    <select name="megye_id_<?php echo $ev->series_id; ?>" id="megye_id" class="form-control" style="width: 150px;margin: 8px 16px;">
                        <option value="0">Nincs szűrés</option>
                        <?php
                        foreach ($megye_lista as $megye) {
                            $selected = (isset($megye_id) && $megye_id == $megye->state_id ? 'selected' : '');
                            echo '<option value="' . $megye->state_id . '" ' . $selected . '>' . $megye->state_name . '</option>';
                        }
                        ?>
                    </select>
                    Sportág
                    <select name="megyei_sportag_id_<?php echo $ev->series_id; ?>" id="megyei_sportag_id" class="form-control" style="width: 150px;margin: 8px 16px;">
                        <option value="0">Nincs szűrés</option>
                        <?php
                        foreach ($sportag_lista as $sportag) {
                            $selected = (isset($megyei_sportag_id) && $megyei_sportag_id == $sportag->sport_id ? 'selected' : '');
                            echo '<option value="' . $sportag->sport_id . '" ' . $selected . '>' . $sportag->sport_name . '</option>';
                        }
                        ?>
                    </select>
                    <button class="btn ml-2 mr-1 " type="submit" name="megyei_szures_submit_<?php echo $ev->series_id; ?>">Szűrés</button>
                    <button class="btn my-2" type="submit" id="megyei_szures_torles" name="megyei_szures_torles">Szűrés törlése</button>
                </div>
                <?php if (count($megyeiVersenyek) > 0) : ?>
                    <table 
                        id="megyei_table_<?php echo $ev->series_id; ?>" 
                        class="table table-striped display" 
                        style="width:100%"
                        data-type="Megyei"
                    >
                        <thead>
                            <tr>
                                <th>Azon.</th>
                                <th>Versenynév</th>
                                <th>Kiírás</th>
                                <th>Típus</th>
                                <th>Kezdete</th>
                                <th>Vége</th>
                                <th>Helyszín</th>
                                <th>Cím</th>
                                <th>Fogy. csoport</th>
                                <th class="no-export">Műveletek</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($megyeiVersenyek as $race) : ?>
                                <?php
                                
                                $res = $wpdb->get_results($wpdb->prepare("SELECT disability_groups FROM vespa_constest_events WHERE contest_id=%d",$race->contest_id));
                                $disablities = [];
                                foreach ($res as $row) {
                                    foreach (explode(',', $row->disability_groups) as $group) {
                                        $disablities[] = $group;
                                    }
                                }
                                $disablities = array_unique($disablities);
                                $resztvevok_szama = $race->resztvevok_szama;
                                ?>
                                <tr>
                                    <td><?php echo $race->contest_id; ?></td>
                                    <td style="background-color: <?php echo vespa_contest_status_color($race); ?>">
                                        <a style="color:black;" href="<?php echo admin_url('admin.php?page=contests&action=view&id=' . $race->contest_id); ?>">
                                            <?php echo stripslashes($race->contest_name); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="<?php echo home_url(); ?>?download_contest_docs=listing&contest_id=<?php echo $race->contest_id; ?>" download>Kiírás</a>
                                    </td>
                                    <td><?php echo $race->contest_type_name; ?></td>
                                    <td><?php echo $race->start_at; ?></td>
                                    <td><?php echo $race->end_at; ?></td>
                                    <td><?php echo $race->place_name; ?></td>
                                    <td><?php echo $race->place_address; ?></td>
                                    <td>
                                        <?php 
                                            $mapped = mapIdsToNames($disablities, $fogyatekossag_lista);
                                            echo implode(', ', $mapped);
                                        ?>
                                    </td>
                                    <td class="no-export">
                                        <div style="display: flex;">
                                            <div style="font-size: 20px; cursor: pointer;" class="tooltip mx-2">
                                                &#128712;
                                                <span class="tooltiptext">
                                                    Résztvevők száma: <?php echo $resztvevok_szama; ?>
                                                </span>
                                            </div>
                                                <button class="btn btn-danger delete-contest-btn" data-id="<?php echo $race->contest_id; ?>">Törlés</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <h3 class="no_result">Nincsen megyei találat.</h3>
                <?php endif; ?>

                <hr>

                <!-- 3) Regionális táblázat -->
                <?php $regionalisVersenyek = getContestsByType($versenyek_ev_szerint[$ev->series_id], 'Regionális'); ?>
                <h1  class="title">Regionális</h1>
                <div class="m2 szurok regionalis_szuro" style="display: flex; align-items: center;">
                    Sportág
                    <select name="regionalis_sportag_id_<?php echo $ev->series_id; ?>" id="regionalis_sportag_id" class="form-control" style="width: 150px;margin: 8px 16px;">
                        <option value="0">Nincs szűrés</option>
                        <?php
                        foreach ($sportag_lista as $sportag) {
                            $selected = (isset($regionalis_sportag_id) && $regionalis_sportag_id == $sportag->sport_id ? 'selected' : '');
                            echo '<option value="' . $sportag->sport_id . '" ' . $selected . '>' . $sportag->sport_name . '</option>';
                        }
                        ?>
                    </select>
                    <button class="btn ml-2 mr-1 " type="submit" name="regionalis_szures_submit_<?php echo $ev->series_id; ?>">Szűrés</button>
                    <button class="btn my-2" type="submit" id="regionalis_szures_torles" name="regionalis_szures_torles">Szűrés törlése</button>
                </div>
                <?php if (count($regionalisVersenyek) > 0) : ?>
                    <table 
                        id="regionalis_table_<?php echo $ev->series_id; ?>" 
                        class="table table-striped display" 
                        style="width:100%"
                        data-type="Regionális"
                    >
                        <thead>
                            <tr>
                                <th>Azon.</th>
                                <th>Versenynév</th>
                                <th>Kiírás</th>
                                <th>Típus</th>
                                <th>Kezdete</th>
                                <th>Vége</th>
                                <th>Helyszín</th>
                                <th>Cím</th>
                                <th>Fogy. csoport</th>
                                <th class="no-export">Műveletek</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($regionalisVersenyek as $race) : ?>
                                <?php
                                $res = $wpdb->get_results($wpdb->prepare("SELECT disability_groups FROM vespa_constest_events WHERE contest_id=%d",$race->contest_id));
                                $disablities = [];
                                foreach ($res as $row) {
                                    foreach (explode(',', $row->disability_groups) as $group) {
                                        $disablities[] = $group;
                                    }
                                }
                                $disablities = array_unique($disablities);
                                $resztvevok_szama = $race->resztvevok_szama;
                                ?>
                                <tr>
                                    <td><?php echo $race->contest_id; ?></td>
                                    <td style="background-color: <?php echo vespa_contest_status_color($race); ?>">
                                        <a style="color:black;" href="<?php echo admin_url('admin.php?page=contests&action=view&id=' . $race->contest_id); ?>">
                                            <?php echo stripslashes($race->contest_name); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="<?php echo home_url(); ?>?download_contest_docs=listing&contest_id=<?php echo $race->contest_id; ?>" download>Kiírás</a>
                                    </td>
                                    <td><?php echo $race->contest_type_name; ?></td>
                                    <td><?php echo $race->start_at; ?></td>
                                    <td><?php echo $race->end_at; ?></td>
                                    <td><?php echo $race->place_name; ?></td>
                                    <td><?php echo $race->place_address; ?></td>
                                    <td>
                                        <?php 
                                            $mapped = mapIdsToNames($disablities, $fogyatekossag_lista);
                                            echo implode(', ', $mapped);
                                        ?>
                                    </td>
                                    <td class="no-export">
                                        <div style="display: flex;">
                                            <div style="font-size: 20px; cursor: pointer;" class="tooltip mx-2">
                                                &#128712;
                                                <span class="tooltiptext">
                                                    Résztvevők száma: <?php echo $resztvevok_szama; ?>
                                                </span>
                                            </div>
                                                <button class="btn btn-danger delete-contest-btn" data-id="<?php echo $race->contest_id; ?>">Törlés</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <h3 class="no_result">Nincsen regionális találat.</h3>
                <?php endif; ?>

                <hr>

                <!-- 4) Szabadidősport táblázat -->
                <?php $szabadidosVersenyek = getContestsByType($versenyek_ev_szerint[$ev->series_id], 'Szabadidősport'); ?>
                <h1 class="title">Szabadidősport</h1>
                <?php if (count($szabadidosVersenyek) > 0) : ?>
                    <table 
                        id="szabadidos_table_<?php echo $ev->series_id; ?>" 
                        class="table table-striped display" 
                        style="width:100%"
                        data-type="Szabadidősport"
                    >
                        <thead>
                            <tr>
                                <th>Azon.</th>
                                <th>Versenynév</th>
                                <th>Kiírás</th>
                                <th>Típus</th>
                                <th>Kezdete</th>
                                <th>Vége</th>
                                <th>Helyszín</th>
                                <th>Cím</th>
                                <th>Fogy. csoport</th>
                                <th class="no-export">Műveletek</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($szabadidosVersenyek as $race) : ?>
                                <?php
                                $res = $wpdb->get_results($wpdb->prepare("SELECT disability_groups FROM vespa_constest_events WHERE contest_id=%d",$race->contest_id));
                                $disablities = [];
                                foreach ($res as $row) {
                                    foreach (explode(',', $row->disability_groups) as $group) {
                                        $disablities[] = $group;
                                    }
                                }
                                $disablities = array_unique($disablities);
                                $resztvevok_szama = $race->resztvevok_szama;
                                ?>
                                <tr>
                                    <td><?php echo $race->contest_id; ?></td>
                                    <td style="background-color: <?php echo vespa_contest_status_color($race); ?>">
                                        <a style="color:black;" href="<?php echo admin_url('admin.php?page=contests&action=view&id=' . $race->contest_id); ?>">
                                            <?php echo stripslashes($race->contest_name); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="<?php echo home_url(); ?>?download_contest_docs=listing&contest_id=<?php echo $race->contest_id; ?>" download>Kiírás</a>
                                    </td>
                                    <td><?php echo $race->contest_type_name; ?></td>
                                    <td><?php echo $race->start_at; ?></td>
                                    <td><?php echo $race->end_at; ?></td>
                                    <td><?php echo $race->place_name; ?></td>
                                    <td><?php echo $race->place_address; ?></td>
                                    <td>
                                        <?php 
                                            $mapped = mapIdsToNames($disablities, $fogyatekossag_lista);
                                            echo implode(', ', $mapped);
                                        ?>
                                    </td>
                                    <td class="no-export">
                                        <div style="display: flex;">
                                            <div style="font-size: 20px; cursor: pointer;" class="tooltip mx-2">
                                                &#128712;
                                                <span class="tooltiptext">
                                                    Résztvevők száma: <?php echo $resztvevok_szama; ?>
                                                </span>
                                            </div>
                                                <button class="btn btn-danger delete-contest-btn" data-id="<?php echo $race->contest_id; ?>">Törlés</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <h3 class="no_result">Nincsen szabadidősport találat.</h3>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>

    </form>

    <?php
    echo vespa_load_template_with_vars(
        'confirm-box.php',
        array(
            "{=TEXT=}"   => "Biztosan törlöd ezt a versenyt?",
            "{=MODALID=}"   => "",
            "{=ACTION=}" => 'delete_contest'
        )
    );
    ?>
</div>
<style>
    .no_result, .szurok{
        margin-left: 20px !important;
    }
    .title{
        padding-left: 20px !important;
        color:white;
        background-color:black;
        margin-bottom: 5px !important;
    }
    .dataTables_filter {
    margin-right: 20px !important;
    }
    .w3-container{
        padding:0px;
    }

</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/jquery.dataTables.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>


document.querySelectorAll('.export_button').forEach(function(button) {
    button.addEventListener('click', function () {
        var activeTab = document.querySelector('.w3-tabarea[style*="display: block"]');
        if (!activeTab) {
            alert('Nem található aktív tab.');
            return;
        }
        var tables = activeTab.querySelectorAll('table');
        var workbook = XLSX.utils.book_new();
        var mergedData = [];

        tables.forEach(function(table, index) {
            var tableType = table.getAttribute('data-type');
            var clone = table.cloneNode(true);
            clone.querySelectorAll('th.no-export, td.no-export').forEach(function(cell) {
                cell.parentNode.removeChild(cell);
            });
            
            
            var sheet = XLSX.utils.table_to_sheet(clone, { raw: false, cellDates: true });
            
            var sheetData = XLSX.utils.sheet_to_json(sheet, { header: 1 });
            
            if (index > 0) {
                mergedData.push([]);
            }
            mergedData.push([tableType]);
            mergedData = mergedData.concat(sheetData);
        });

        if (mergedData.length === 0) {
            alert('Nincs exportálható adat az aktív tabon.');
            return;
        }
        
        var worksheet = XLSX.utils.aoa_to_sheet(mergedData);
        XLSX.utils.book_append_sheet(workbook, worksheet, "Versenyek");
        XLSX.writeFile(workbook, 'export_active_tab.xlsx');
    });
});

jQuery(document).ready(function($) {
    $('table.display').DataTable({
        paging: false,
        info: false,
        ordering: true,
        language: {
            search: "Keresés:" 
        },
        order: [[4, 'desc']]
    });
    

});


</script>

