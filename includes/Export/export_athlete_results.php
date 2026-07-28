<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

add_action('init', 'vespa_export_athlete_results_init');
function vespa_export_athlete_results_init()
{
    if (!isset($_GET['export_athlete_results'])) {
        return;
    }

    global $wpdb;

    set_time_limit(0);
    ini_set('memory_limit', '512M');

    // jogosultság ellenőrzés
    if (!current_user_can(VESPA_Roles::riportalas)) {
        wp_die('Jogosulatlan hozzáférés.');
    }

    require VITAREX_VESPA_PLUGIN_DIR . '/lib/vendor/autoload.php';

    // 1. lekérdezés: sportolók (a lista szűrőivel), csak a nem törölt sportolók
    $sql = "SELECT va.athlete_id, va.athlete_name, va.birth_date, va.gender, vi.ins_name
            FROM vespa_athletes AS va
            LEFT JOIN vespa_institutions AS vi ON vi.institution_id = va.school_id
            WHERE va.is_deleted=0";

    $params = array();

    if (isset($_GET['birth_place']) && trim($_GET['birth_place']) != '') {
        $sql .= " AND va.birth_place = %s";
        $params[] = trim($_GET['birth_place']);
    }

    if (isset($_GET['birth_date']) && is_numeric($_GET['birth_date'])) {
        $sql .= " AND va.birth_date = %d";
        $params[] = (int) $_GET['birth_date'];
    }

    if (isset($_GET['school_id']) && trim($_GET['school_id']) != '0' && trim($_GET['school_id']) != '') {
        $sql .= " AND va.school_id = %s";
        $params[] = trim($_GET['school_id']);
    }

    if (!empty($params)) {
        $athletes = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    } else {
        $athletes = $wpdb->get_results($sql);
    }

    // gyors elérésű map az athlete_id alapján
    $athletes_map = array();
    foreach ($athletes as $athlete) {
        $athletes_map[(string) $athlete->athlete_id] = $athlete;
    }

    // 2. lekérdezés: eredmény-sorok a verseny / versenyszám / sportág / sport-esemény adataival együtt
    $eredmeny_sql = "SELECT vcer.result, vc.contest_name, vc.start_at,
                        vse.sport_event_name, vs.sport_name
                    FROM vespa_constest_events_results AS vcer
                    INNER JOIN vespa_constest_events AS vce ON vce.id = vcer.contest_event_id
                    LEFT JOIN vespa_sport_events AS vse ON vse.sport_event_id = vce.event_id
                    LEFT JOIN vespa_sports AS vs ON vs.sport_id = vce.sport_id
                    INNER JOIN vespa_contests AS vc ON vc.contest_id = vcer.contest_id";

    $eredmeny_sorok = $wpdb->get_results($eredmeny_sql);

    // sorok kibontása: minden eredmény-blob egy tömb, athlete-enként egy exportsor
    $export_sorok = array();

    foreach ($eredmeny_sorok as $sor) {
        $eredmenyek = json_decode($sor->result, true);
        if (!is_array($eredmenyek)) {
            // hibás/nem tömb JSON, ezt a sort kihagyjuk
            continue;
        }

        $versenyszam = trim(($sor->sport_name ?: '') . ' ' . ($sor->sport_event_name ?: ''));

        foreach ($eredmenyek as $eredmeny) {
            if (!is_array($eredmeny) || !isset($eredmeny['athlete_id'])) {
                continue;
            }

            $athlete_id = (string) $eredmeny['athlete_id'];
            if (!isset($athletes_map[$athlete_id])) {
                // vagy nincs ilyen sportoló, vagy a szűrők miatt esett ki, vagy törölt
                continue;
            }

            $athlete = $athletes_map[$athlete_id];

            $export_sorok[] = array(
                'sportolo_neve'      => $athlete->athlete_name,
                'iskola'             => $athlete->ins_name,
                'szuletesi_ido'      => $athlete->birth_date,
                'neme'               => $athlete->gender,
                'verseny'            => $sor->contest_name,
                'versenyszam'        => $versenyszam,
                'megjelent'          => (isset($eredmeny['megjelent']) && $eredmeny['megjelent'] === 'true') ? 'igen' : 'nem',
                'helyezes'           => isset($eredmeny['helyezes']) ? $eredmeny['helyezes'] : '',
                'eredmeny'           => isset($eredmeny['eredmeny']) ? $eredmeny['eredmeny'] : '',
                'verseny_kezdete'    => $sor->start_at,
            );
        }
    }

    // rendezés: sportoló neve, majd verseny kezdete szerint
    usort($export_sorok, function ($a, $b) {
        $nev_cmp = strcmp($a['sportolo_neve'], $b['sportolo_neve']);
        if ($nev_cmp !== 0) {
            return $nev_cmp;
        }
        return strcmp((string) $a['verseny_kezdete'], (string) $b['verseny_kezdete']);
    });

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $fejlec = array(
        'Sportoló neve',
        'Iskola / egyesület',
        'Születési idő',
        'Neme',
        'Verseny',
        'Versenyszám',
        'Megjelent',
        'Helyezés',
        'Eredmény',
        'Verseny kezdete',
    );
    $sheet->fromArray($fejlec, null, 'A1');

    $ind = 2;
    foreach ($export_sorok as $sor) {
        $sheet->fromArray(array(
            $sor['sportolo_neve'],
            $sor['iskola'],
            $sor['szuletesi_ido'],
            $sor['neme'],
            $sor['verseny'],
            $sor['versenyszam'],
            $sor['megjelent'],
            $sor['helyezes'],
            $sor['eredmeny'],
            $sor['verseny_kezdete'],
        ), null, 'A' . $ind);
        $ind++;
    }

    autosize_columns($spreadsheet->getActiveSheet());

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="export_sportolo_eredmenyek.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');

    exit;
}
