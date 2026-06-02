<?php

// =============================================
// Logolás
// =============================================

const log_table = "vitarex_log";

function vitarex_log($action, $description, $table_name = null, $level = "info")
{
  global $wpdb;
  $user = wp_get_current_user();
  try {
    $wpdb->insert(
      log_table,
      array(
        "user_id" => isset($user) ? $user->ID : -1,
        "user_login" => isset($user) ? $user->user_login : "ismeretlen login",
        "action" => $action,
        "table_name" => $table_name,
        "level" => $level,
        "description" => $description
      )
    );
  } catch (Exception $ex) {
    throw new ErrorException("Hiba történt logolás közben.", 0, 1);
  }
}

function vitarex_log_function_call($description = null, $full_stack_log = false)
{
  $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
  $caller = isset($dbt[1]['function']) ? $dbt[1]['function'] : "ismeretlen függvény";
  vitarex_log($caller, $description . (!$full_stack_log ? null : "\n" . str_replace("\/", "/", json_encode($dbt, JSON_PRETTY_PRINT))));
}

// =============================================
// VESPA segédfüggvények
// =============================================

function vespa_get_contest_athlete_filter($filter, $contest = null)
{
    if (
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::TESTNEVELO)
        || VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ISKOLAIGAZGATO)
    ) {
        $school_id = vespa_get_my_school_id();
        $filter .= " AND school_id = " . intval($school_id);
    }

    return $filter;
}

function vespa_school_entered_contest($contest_id, $school_id = 0, $return = false)
{
    global $wpdb;

    if ($school_id == 0) {
        $school_id = vespa_get_my_school_id();
    }

    $sql = "SELECT * FROM vespa_school_entries WHERE contest_id = %d AND school_id = %d";
    $sql = $wpdb->prepare($sql, $contest_id, $school_id);

    $result = $wpdb->get_results($sql);

    if (count($result) == 0) {
        return false;
    }

    if ($return) {
        return $result[0];
    }

    return true;
}

function vespa_get_my_name()
{
    $user = wp_get_current_user();
    $name = $user->user_lastname . ' ' . $user->user_firstname;
    return $name;
}

function vespa_get_my_school_id()
{
    $user_id   = get_current_user_id();
    $school_id = get_user_meta($user_id, 'school_id', true);
    return $school_id;
}

function vespa_get_my_state_id()
{
    $user_id   = get_current_user_id();
    $state_id = get_user_meta($user_id, 'state_id', true);
    return $state_id;
}

function vespa_contest_has_answers($contest_id)
{
    global $wpdb;

    $sql = "SELECT * FROM vespa_questions_answered WHERE contest_id = %d";
    $result = $wpdb->get_results($wpdb->prepare($sql, $contest_id));

    if (count($result) == 0) {
        return false;
    }

    return true;
}

// =============================================
// WordPress hookok
// =============================================

add_action('user_register', 'vespa_new_user_registration', 10, 1);

function vespa_new_user_registration($user_id)
{
    if (isset($_POST['school_id']) && !empty($_POST['school_id'])) {
        $school_id = intval($_POST['school_id']);
        update_user_meta($user_id, 'school_id', $school_id);
    }
}

add_action('beszamolo_figyelmeztetes', 'vespa_beszamolo_figyelmeztetes', 10, 1);

function vespa_beszamolo_figyelmeztetes($logolas_bekapcsolva_json)
{
    try {
        $logolas_bekapcsolva = isset($logolas_bekapcsolva_json) ? json_decode($logolas_bekapcsolva_json) : false;
        global $wpdb;
        $user_table = $wpdb->prefix . "users";
        $sql = "SELECT us.display_name, us.user_email, vc.contest_id, vc.contest_name FROM $user_table AS us
        JOIN `vespa_contests` AS vc ON vc.creator_user_id=us.ID
        WHERE DATEDIFF(CURRENT_DATE,vc.end_at)>3
            AND vc.contest_id NOT IN
            (SELECT contest_id FROM vespa_questions_answered);";
        $eredmeny = $wpdb->get_results($sql);

        if (!empty($eredmeny)) {
            $csoportositott_adat = [];
            $mai_datum = date("Y.m.d");
            foreach ($eredmeny as $adat) {
                $csoportositott_adat[$adat->user_email][] = $adat;
            }
            foreach ($csoportositott_adat as $kulcs => $ertek) {
                $to = $kulcs;
                $versenyek = "<ul>";
                foreach ($ertek as $verseny_adat) {
                    $verseny_id = intval($verseny_adat->contest_id);
                    $verseny_nev = esc_html($verseny_adat->contest_name);
                    $url = admin_url("admin.php?page=contests&action=question&id=$verseny_id");
                    $versenyek .= "<li><a href='" . esc_url($url) . "'>$verseny_nev</a></li>";
                }
                $versenyek .= "</ul>";
                $subject = "Verseny beszámoló emlékeztető ($mai_datum)";
                $nev = esc_html(array_values($ertek)[0]->display_name);
                $body = "Kedves $nev!<br/><br/>Az alábbi versenyek befejezése óta legalább 3 nap telt el, az ezekhez tartozó beszámolót kérjük, töltse fel a VESPA rendszerben:<br/>$versenyek<br/>VESPA rendszer";
                $headers = array('Content-Type: text/html; charset=UTF-8');
                $sent = wp_mail($to, $subject, $body, $headers);
                if ($logolas_bekapcsolva)
                    vitarex_log("Beszámoló emlékeztető", "Email küldve ($to): $sent");
            }
        }
    } catch (Exception $ex) {
        $hiba = $ex->getMessage();
        vitarex_log("Beszámoló emlékeztető", "Hiba történt: $hiba");
    }
}

/**
 * Verseny státusz szerinti háttérszín a verseny-listához.
 * PIROS: a verseny napja már elmúlt. ZÖLD: véglegesített és épp nyitva a nevezés.
 * KÉK: minden más jövőbeli állapot (nincs véglegesítve, vagy a nevezés még nem indult,
 * vagy a nevezés már lezárult, de a verseny napja még nem volt meg).
 */
function vespa_contest_status_color($contest)
{
    $now = date('Y-m-d H:i:s');

    if ($contest->end_at < $now) {
        return '#ec5a64'; // piros – a verseny véget ért
    }

    if ($contest->is_final
        && $contest->school_entry_start_at <= $now) {
        return '#63c27c'; // zöld – a nevezés megnyílt (a verseny végéig)
    }

    return '#5bc0de'; // kék – még nem nyitott a nevezés (vagy nincs véglegesítve)
}

/**
 * Egy iskola pedagógusai egy adott versenyre.
 * @return array vespa_contest_teachers sorok
 */
function vespa_get_teachers($school_id, $contest_id)
{
    global $wpdb;

    if (!is_numeric($school_id) || !is_numeric($contest_id)) {
        return array();
    }

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM vespa_contest_teachers WHERE school_id=%d AND contest_id=%d ORDER BY id ASC",
        $school_id,
        $contest_id
    ));
}

/**
 * Van-e legalább egy pedagógus megadva az adott iskola+verseny párosra.
 */
function vespa_school_has_teachers($school_id, $contest_id)
{
    global $wpdb;

    if (!is_numeric($school_id) || !is_numeric($contest_id)) {
        return false;
    }

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM vespa_contest_teachers WHERE school_id=%d AND contest_id=%d",
        $school_id,
        $contest_id
    ));

    return intval($count) > 0;
}
