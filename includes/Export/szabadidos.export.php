<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

add_action('init', 'vespa_szabadidos_export');

function vespa_szabadidos_export()
{
    if (!isset($_GET['vespa_szabadidos_export'])) {
        return;
    }
    if (!current_user_can(VESPA_Roles::riportalas)) {
        wp_die('Jogosulatlan hozzáférés.');
    }
    $contest_id = intval($_GET['vespa_szabadidos_export']);
    if ($contest_id <= 0) {
        wp_die('Hibás verseny.');
    }

    require_once VITAREX_VESPA_PLUGIN_DIR . '/lib/vendor/autoload.php';

    global $wpdb;
    $nevezok = $wpdb->get_results($wpdb->prepare(
        "SELECT p.full_name, p.birth_date, p.gender, p.email, p.phone, e.entry_date, vse.sport_event_name, vs.sport_name
         FROM vespa_external_entries AS e
         INNER JOIN vespa_external_participants AS p ON p.participant_id=e.participant_id
         LEFT JOIN vespa_constest_events AS vce ON vce.id=e.contest_event_id
         LEFT JOIN vespa_sport_events AS vse ON vse.sport_event_id=vce.event_id
         LEFT JOIN vespa_sports AS vs ON vs.sport_id=vce.sport_id
         WHERE e.contest_id=%d
         ORDER BY p.full_name",
        $contest_id
    ));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(array('Név', 'Születési dátum', 'Nem', 'E-mail', 'Telefon', 'Versenyszám', 'Nevezés dátuma'), null, 'A1');

    $sor = 2;
    foreach ($nevezok as $n) {
        $versenyszam = trim(($n->sport_name ?: '') . ' ' . ($n->sport_event_name ?: ''));
        $sheet->fromArray(array(
            $n->full_name, $n->birth_date, $n->gender, $n->email, $n->phone, $versenyszam, $n->entry_date
        ), null, 'A' . $sor);
        $sor++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="szabadidos_nevezok.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
