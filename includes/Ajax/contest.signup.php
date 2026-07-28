<?php
class VespaContestSignups
{
    public function __construct()
    {
        add_action('wp_ajax_school_signup', array($this, 'school_signup'));
        add_action('wp_ajax_athletes_signup', array($this, 'athletes_signup'));
    }


    public function school_signup()
    {
        global $wpdb;

        $response = array("success" => true, "message" => "");

        // TODO: check if user has right to signup

        // TODO: check if contest is open for signup

        // TODO: validate input
        $success = false;
        $contest_id = $_POST['contest_id'];
        $school_id = $_POST['school_id'];

        if ($school_id == 0) {
            $school_id = vespa_get_my_school_id();
        }

        if (!vespa_school_entered_contest($contest_id, $school_id)) {
            $when = date('Y-m-d H:i:s');
            $cuser = wp_get_current_user();

            $success = $wpdb->insert('vespa_school_entries', array(
                'contest_id' => $contest_id,
                'school_id'  => $school_id,
                'user_id'    => get_current_user_id(),
                'entry_date' => $when

            ), array(
                '%s'
            ));
        }

        if (!$success) {
            $response['success'] = false;
            $response['message'] = 'Sikertelen feliratkozás!';
        } else {
            $response['message'] = $when . ', ' . $cuser->display_name;
        }

        wp_send_json($response);
    }

    ///--------
    public function athletes_signup()
    {
        global $wpdb;

        $response = array("success" => true, "pplnum" => 0);
        $success    = false;
        $contest_id = $_POST['contest_id'];
        $contest_event_id = $_POST['contest_event_id'];
        $school_id  = $_POST['school_id'];
        $event_id   = $_POST['event_id'];
        $athlete_id = $_POST['athlete_ids'];
        $selejtezo_verseny_id = $_POST['selejtezo_verseny_id'];
        $errors = array();

        $req_fields = array('contest_id', 'contest_event_id', 'athlete_ids');
        foreach ($req_fields as $fieldname) {
            if (empty($_POST[$fieldname]) || !is_numeric($_POST[$fieldname])) {
                $errors[$fieldname] = 'Kötelező mező';
            }
        }
        if (count($errors)) {
            $response['success'] = false;
            $response['message'] = 'Érvénytelen adatok.';

            wp_send_json($response);
        }

        if (!current_user_can(VESPA_Roles::sportolot_nevezhet)) {
            $response['success'] = false;
            $response['message'] = 'Adott művelettre a felhasználó nem jogosult.';

            wp_send_json($response);
        }
        date_default_timezone_set('Europe/Budapest');
        $currentDate = date("Y-m-d H:i:s");
        //$sql = $wpdb->prepare("SELECT * FROM vespa_contests WHERE contest_id=$contest_id AND school_entry_start_at <= '$currentDate' AND school_entry_end_at >= '$currentDate'");
        $sql = $wpdb->prepare("SELECT * FROM vespa_contests WHERE contest_id=%d AND is_final=1", $contest_id);
        $contest = $wpdb->get_row($sql);
        if (!isset($contest)) {
            $response['success'] = false;
            $response['message'] = 'A verseny nem véglegesített, így nem jelentkezhető.';

            wp_send_json($response);
        }
        if (!($contest->school_entry_start_at < $currentDate && $contest->school_entry_end_at > $currentDate)) {
            $response['success'] = false;
            if ($contest->school_entry_end_at < $currentDate)
                $response['message'] = 'A versenyre való jelentkezés határideje lejárt';
            else
                $response['message'] = 'A versenyre való jelentkezés még nem kezdődött meg';
            wp_send_json($response);
        }

        if ($school_id == 0) {
            $school_id = vespa_get_my_school_id();
        }

        $entered_ids = array();
        $filter = "1";
        $params = [];
        if (VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::TESTNEVELO)) {
            $userId = get_current_user_id();
            $filter = "user_id=%d";
            $params[] = $userId;
        }
        $sql_entered = "SELECT athlete_id FROM vespa_athlete_entries WHERE contest_id=%d AND contest_event_id=%d AND $filter";
        $entered = $wpdb->get_results($wpdb->prepare($sql_entered, $contest_id, $contest_event_id, ...$params));

        foreach ($entered as $e) {
            $entered_ids[] = $e->athlete_id;
        }
        unset($entered);

        $response['pplnum'] = count($entered_ids);

        $data = $wpdb->get_row($wpdb->prepare("SELECT id FROM vespa_athlete_entries WHERE contest_id=%d AND contest_event_id=%d AND athlete_id=%d",$contest_id,$contest_event_id,$athlete_id));

        if (isset($data)) {
            $response['action'] = 'remove';
            $success = $wpdb->delete(
                'vespa_athlete_entries',
                array('id' => $data->id),
                array('%d')
            );

            $response['success'] = $success;
            if (!$success) {
                $response['success'] = false;
                $response['message'] = 'Sikertelen feliratkozás!';
            } else {
                $response['pplnum'] -= 1;
            }
        } else {
            $response['action'] = 'add';

            // Pedagógus-feltétel: csak akkor nevezhető új sportoló, ha az iskola
            // megadott legalább egy pedagógust erre a versenyre.
            if (!vespa_school_has_teachers($school_id, $contest_id)) {
                $response['success'] = false;
                $response['message'] = 'Előbb add meg legalább egy pedagógust a "Pedagógusok" szekcióban a nevezéshez!';
                wp_send_json($response);
                die();
            }

            // check if can be entered
            $contest = $GLOBALS['VESPA_Contests']->load($contest_id);
            $isSzabadidosport = $contest->contest_type == 4;
            $sql = "SELECT count(a.athlete_id) as num 
                    FROM vespa_athlete_entries as ae 
                    JOIN vespa_athletes as a ON (a.athlete_id=ae.athlete_id) 
                    WHERE ae.contest_id=%d AND ae.contest_event_id=%d";
            $sql = $wpdb->prepare($sql, $contest_id, $contest_event_id);
            if (!$isSzabadidosport && is_numeric($school_id)) {
                $sql .= " AND a.school_id=%d";
                $sql = $wpdb->prepare($sql, $contest_id, $contest_event_id, $school_id);
            }

            $all_kids = $wpdb->get_results($sql);
            


            if (!empty($all_kids) && $all_kids[0]->num >= $contest->ppl_num_max) {
                $response['success'] = false;
                $response['message'] = $isSzabadidosport ? 'A szabadidősportra a helyek száma betelt.' : 'Nem iratkozhatsz fel több tanulóval.';

                wp_send_json($response);
                die();
            } else {

                //if( vespa_school_entered_contest( $contest_id, $school_id ) ) {
                $success;
                $list = explode(',', $_POST['athlete_ids']);

                // Szintidő-ellenőrzés. Ha a versenyszámhoz nincs minimum
                // beállítva (a leggyakoribb eset, minden meglévő sor ilyen),
                // ez az egyetlen lekérdezés fut le, és senki nem esik ki —
                // ez teszi a feltételt opt-in-né.
                $szintido_esemeny = $wpdb->get_row($wpdb->prepare(
                    "SELECT event_id, min_qualifying_seconds FROM vespa_constest_events WHERE id=%d",
                    $contest_event_id
                ));

                if ($szintido_esemeny !== null && $szintido_esemeny->min_qualifying_seconds !== null) {
                    // A $list a POST-ból jön (vesszővel elválasztott azonosítók),
                    // ezért intval-lel térképezzük az IN (...) lista elemeit —
                    // ez egy változó hosszúságú lista, nem prepare-elhető egyetlen paraméterként.
                    $szintido_athlete_ids = array_map('intval', $list);

                    $szintido_idok_by_id = array();
                    if (count($szintido_athlete_ids) > 0) {
                        $helyorzok = implode(',', array_fill(0, count($szintido_athlete_ids), '%d'));
                        $szintido_sql = "SELECT a.athlete_id, a.athlete_name, qt.seconds
                                          FROM vespa_athletes AS a
                                          LEFT JOIN vespa_athlete_qualifying_times AS qt
                                            ON qt.athlete_id = a.athlete_id AND qt.sport_event_id = %d
                                          WHERE a.athlete_id IN ($helyorzok)";
                        $szintido_parameterek = array_merge(array($szintido_esemeny->event_id), $szintido_athlete_ids);
                        $szintido_sorok = $wpdb->get_results($wpdb->prepare($szintido_sql, ...$szintido_parameterek));
                        foreach ($szintido_sorok as $szintido_sor) {
                            $szintido_idok_by_id[intval($szintido_sor->athlete_id)] = $szintido_sor;
                        }
                    }

                    $szintido_sikertelenek = array();
                    foreach ($szintido_athlete_ids as $szintido_aid) {
                        $szintido_sor = isset($szintido_idok_by_id[$szintido_aid]) ? $szintido_idok_by_id[$szintido_aid] : null;
                        $szintido_ido = $szintido_sor ? $szintido_sor->seconds : null;
                        if (!vespa_szintido_megfelel($szintido_ido, $szintido_esemeny->min_qualifying_seconds)) {
                            $szintido_sikertelenek[] = $szintido_sor ? $szintido_sor->athlete_name : ('#' . $szintido_aid);
                        }
                    }

                    if (count($szintido_sikertelenek) > 0) {
                        // Mindent-vagy-semmit: ha bárki nem felel meg, egyetlen
                        // sor sem kerül be ebből a beküldésből.
                        $response['success'] = false;
                        $response['message'] = 'A következő tanuló(k) nem felelnek meg a szintidő-feltételnek (minimum '
                            . vespa_szintido_format($szintido_esemeny->min_qualifying_seconds) . '): '
                            . implode(', ', $szintido_sikertelenek) . '.';
                        wp_send_json($response);
                        die();
                    }
                }

                foreach ($list as $athlete_id) {
                    if (!in_array($athlete_id, $entered_ids)) {
                        $success = $wpdb->insert('vespa_athlete_entries', array(
                            'contest_id' => $contest_id,
                            'contest_event_id' => $contest_event_id,
                            'event_id'  => $event_id ? $event_id : null,
                            'athlete_id' => $athlete_id,
                            'user_id'    => get_current_user_id(),
                            'entry_date' => date('Y-m-d H:i:s'),
                            'selejtezo_vespa_athlete_entries_id' => $selejtezo_verseny_id,
                        ), array(
                            '%s'
                        ));
                    }
                }
                //}

            }

            if (!$success) {
                $response['success'] = false;
                $response['message'] = 'Sikertelen feliratkozás!';
            } else {
                $response['pplnum'] += 1;
            }
        }



        wp_send_json($response);
    }
}

$GLOBALS['VespaContestSignups'] = new VespaContestSignups();
