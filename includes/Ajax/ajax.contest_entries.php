<?php
    function ajax_table_contest_entries_display( $id = null ){
        global $wpdb;

        if( $id == null ) {
            $id = $_POST['contest_id'];
        }

        ob_start();

        // TODO: csak a saját iskolám és a saját nevezettjeimet lássam
        $sqlList = "SELECT e.*, i.ins_name, i.id_number   
                                    FROM vespa_school_entries as e
                                    JOIN vespa_institutions as i ON i.institution_id = e.school_id
                                    WHERE contest_id= %d";

        $list = $wpdb->get_results($wpdb->prepare($sqlList, $id));
        
        

        if( empty($list) ){
            wp_send_json( array("success" => true, 'html' => '<table class="table table-striped" id="ajax_table_contest_entries"><tr><td>Nincs megjeleníthető adat</td></tr></table>' ) );
            exit;
        }

        // get the athletes entries too
        $datalist = array();
        foreach($list as $item){
            $datalist[ $item->school_id ] = array(
                'school' => $item,
                'athletes' => array()
            );
        }

/*
        $list = $wpdb->get_results("SELECT e.*, p.sport_name, s.sport_event_name, p.sport_id 
                                    FROM vespa_constest_events as e 
                                    JOIN vespa_sport_events as s ON s.sport_event_id = e.event_id 
                                    JOIN vespa_sports as p ON p.sport_id = s.sport_id 
                                    WHERE e.contest_id=" . $id);
*/        
        $sqlAthletes = "SELECT e.*, a.school_id, a.athlete_name, a.birth_date, a.gender, p.sport_name, s.sport_event_name
                                        FROM vespa_athlete_entries as e
                                        JOIN vespa_athletes as a ON a.athlete_id = e.athlete_id
                                        JOIN vespa_constest_events as e2 ON e2.id = e.contest_event_id
                                        JOIN vespa_sports as p ON p.sport_id = e2.sport_id
                                        LEFT JOIN vespa_sport_events as s ON s.sport_event_id = e2.event_id
                                        WHERE e.contest_id= %d";
        $sqlAthletes = $wpdb->prepare($sqlAthletes, $id);

        $athletes = $wpdb->get_results($sqlAthletes);
        
        foreach( $athletes as $athlete ){
            if( ! empty($datalist[ $athlete->school_id ]) ){
                $datalist[ $athlete->school_id ]['athletes'][] = $athlete;
            }
        }

        ?>
            <table class="table table-striped" id="ajax_table_contest_entries">
            <thead>
                <tr>
                    <th>Iskola / sportoló</th>
                    <th width="300">Versenyszám</th>
                    <th width="180">Nevezés ideje</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($datalist as $item): ?>
                <tr class="contest_entries-school">
                    <td><?php echo $item['school']->ins_name . ' (' . $item['school']->id_number . ')';  ?></td>
                    <td>&nbsp;</td>
                    <td><?php echo $item['school']->entry_date; ?></td>
                </tr>

                <?php foreach($item['athletes'] as $athlete): ?>
                <tr class="contest_entries-athlete">
                    <td><?php echo $athlete->athlete_name . ', ' . $athlete->birth_date . ' (' . $athlete->gender . ')'; ?></td>
                    <td><?php echo $athlete->sport_name . ' / ' . $athlete->sport_event_name ; ?></td>
                    <td><?php echo $athlete->entry_date; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
            </table>
        <?php

        $html = ob_get_contents();
        ob_get_clean();

        wp_send_json( array("success" => true, 'html' => $html ) );
    }

    add_action( 'wp_ajax_vespa_ajax_table_contest_entries', 'ajax_table_contest_entries_display' );
