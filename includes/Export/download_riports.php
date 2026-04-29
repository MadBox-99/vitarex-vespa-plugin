<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;


add_action('init', 'init_download_riports');
function init_download_riports()
{
    if (isset($_GET['download_riports'])) {
        $type       = $_GET['download_riports'];

        if(!current_user_can(VESPA_Roles::riportalas)){
            wp_send_json_error(array("message" => "Jogosulatlan hozzáférés"), 403);
        }

        if ('iskola_sportoltatott_diakok' == $type || 
            'tanev_diakolimpia_diakok' == $type) { 
            vespa_download_riport_tanev($type); 
        } else if ('verseny_versenyszam' == $type || 
            'tanev_diakolimpia_versenyszam' == $type || 
            'tanev_diakolimpia_versenyszam_sportag' == $type) {
            vespa_download_riport_verseny_versenyszam();
        } else if ('verseny_diak' == $type) { 
            vespa_download_riport_verseny_diak();
        }else if ('tanev_versenyen_indult_iskolak' == $type) {
            vespa_download_riport_versenyen_resztvevo_iskolak_szama();
        } else if ('legnepszerubb_sportag' == $type) {
            vespa_download_riport_legnepszerubb_sportagak();
        } else if ('szezon_riport' == $type) {
            vespa_download_riport_szezon_riport();
        }

        exit;
    }
}

function vespa_download_riport_szezon_riport()
{
    global $wpdb;

    require VITAREX_VESPA_PLUGIN_DIR  . '/lib/vendor/autoload.php';

    $type = $_GET['download_riports'];

    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $ind = 1;

    $filter = $_GET['filter'];
    $seriesId = $_GET['series'];
    $schoolDistrict = $_GET['schoolDistrict'];
    $gender = $_GET['gender'];
    $disabilityGroupId = $_GET['disabilityGroupId'];
    $year = isset($_GET['year']) ? $_GET['year'] : 0;
    $filterType = '';

    // if($filter == 'all') $filterType = 'Összes verseny';
    // else if ($filter == 'country') $filterType = "Csak országos versenyek";
    // else if (is_numeric($filter) && $filter > 0) {
    //     $st = $wpdb->get_row("SELECT * FROM vespa_states WHERE state_id=$filter");
    //     $filterType = "Megyei versenyek - $st->state_name";
    // }
    // else if (is_numeric($schoolDistrict) && $schoolDistrict > 0) {
    //     $sd = $wpdb->get_row("SELECT * FROM vespa_school_districts WHERE school_district_id=$schoolDistrict");
    //     $filterType = "Tankerületi versenyek - $sd->school_district_name";
    // }
    // else die;

    if(is_numeric($seriesId) && $seriesId > 0){
        $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_series WHERE series_id=%d",$seriesId));
        $sheet
            ->setCellValue('A' . $ind, 'Tanév/Diákolimpia szezon')
            ->setCellValue('B' . $ind, $st->series_name);
        if (is_numeric($year) && $year > 0) {
            $sheet->setCellValue('C' . $ind, "Naptári év: $year");
        }
        $ind += 2;
    }
    else die;

    $sql = "SELECT va.athlete_id, va.gender, va.disability_type, vdg.disability_group_name, va.birth_date, vi.institution_id, vc.contest_name, vc.contest_type, vc.state_id, vce.contest_id, vce.id as contest_event_id, vce.sport_id, vce.event_id, vs.sport_name, vse.sport_event_name FROM `vespa_athletes` as va
            JOIN vespa_athlete_entries as vae ON va.athlete_id=vae.athlete_id
            JOIN vespa_institutions as vi ON va.school_id=vi.institution_id
            JOIN vespa_contests as vc ON vae.contest_id=vc.contest_id
            JOIN vespa_constest_events as vce ON vae.contest_event_id=vce.id
            LEFT JOIN vespa_sports as vs ON vce.sport_id=vs.sport_id
            LEFT JOIN vespa_sport_events as vse ON vce.event_id=vse.sport_event_id
            JOIN vespa_disability_groups as vdg ON va.disability_type=vdg.disability_group_id
            WHERE vc.contest_series=%d";

    $params = [$seriesId];

    if ($filter == 'country') {
        $sql .= " AND vc.contest_type=1";
    } elseif (is_numeric($filter) && $filter > 0) { 
        $sql .= " AND vc.contest_type=3 AND vc.state_id=%d";
        $params[] = $filter;
    }

    if (is_numeric($schoolDistrict) && $schoolDistrict > 0) {
        $sql .= " AND vi.school_district_id=%d";
        $params[] = $schoolDistrict;
    }

    if (is_numeric($disabilityGroupId) && $disabilityGroupId > 0) { 
        $sql .= " AND va.disability_type=%d";
        $params[] = $disabilityGroupId;
    }

    if ($gender == 'nő' || $gender == 'férfi') {
        $sql .= " AND va.gender=%s";
        $params[] = $gender;
    }

    if (is_numeric($year) && $year > 0) {
        $sql .= " AND YEAR(vc.start_at)=%d";
        $params[] = $year;
    }

$sql .= " GROUP BY va.athlete_id";

$data = $wpdb->get_results($wpdb->prepare($sql, ...$params));

    $ferfi = 0;
    $no = 0;
    foreach ($data as $row) {
        if($row->gender == 'férfi') $ferfi++;
        else if($row->gender == 'nő') $no++;
    }

    # összesen
    $sheet
        ->setCellValue('A' . $ind, 'Diákolimpian részvett:')
        ->setCellValue('B' . $ind, 'Összesen')
        ->setCellValue('C' . $ind, 'Létszám')
        ->setCellValue('E' . $ind, 'Fiú')
        ->setCellValue('F' . $ind, 'Lány');
    $ind++;
    $sheet
        ->setCellValue('A' . $ind, 'Fogyatékkal élő diák össz:')
        ->setCellValue('C' . $ind, count($data))
        ->setCellValue('E' . $ind, $ferfi)
        ->setCellValue('F' . $ind, $no);
    $ind += 2;

    $sheet
        ->setCellValue('B' . $ind, 'Fogyatékossági csoport')
        ->setCellValue('C' . $ind, 'Létszám')
        ->setCellValue('E' . $ind, 'Fiú')
        ->setCellValue('F' . $ind, 'Lány');
    $ind++;

    riportPartDiakok($sheet, $ind, $data, function ($fn){
        return $fn->contest_type == 1;
    }, 'országos');
    riportPartDiakok($sheet, $ind, $data, function ($fn){
        return $fn->contest_type == 2;
    }, 'regionális');
    riportPartDiakok($sheet, $ind, $data, function ($fn){
        return $fn->contest_type == 3;
    }, 'megyei');

    $data = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    $iskArr = array();
    foreach ($data as $row) {
        array_push($iskArr, $row->institution_id);
    }
    $sheet
        ->setCellValue('A' . $ind, 'Diákolimpian részvett:')
        ->setCellValue('C' . $ind, 'Létszám');
    $ind++;
    $sheet
        ->setCellValue('A' . $ind, 'Iskolák száma össz:')
        ->setCellValue('C' . $ind, count(array_unique($iskArr)));
    $ind += 2;

    $iskArr = array();
    foreach ($data as $row) {
        if($row->contest_type == 1) array_push($iskArr, $row->institution_id);
    }
    $sheet
        ->setCellValue('A' . $ind, 'Diákolimpian részvett:')
        ->setCellValue('B' . $ind, 'Országos')
        ->setCellValue('C' . $ind, 'Létszám');
    $ind++;
    $sheet
        ->setCellValue('A' . $ind, 'Iskolák száma megye mind:')
        ->setCellValue('C' . $ind, count(array_unique($iskArr)));
    $ind += 2;

    $iskArr = array();
    foreach ($data as $row) {
        if($row->contest_type == 2) array_push($iskArr, $row->institution_id);
    }
    $sheet
        ->setCellValue('A' . $ind, 'Diákolimpian részvett:')
        ->setCellValue('B' . $ind, 'Regionális')
        ->setCellValue('C' . $ind, 'Létszám');
    $ind++;
    $sheet
        ->setCellValue('A' . $ind, 'Iskolák száma megye mind:')
        ->setCellValue('C' . $ind, count(array_unique($iskArr)));
    $ind += 2;

    $iskArr = array();
    foreach ($data as $row) {
        if($row->contest_type == 3) array_push($iskArr, $row->institution_id);
    }
    $sheet
        ->setCellValue('A' . $ind, 'Diákolimpian részvett:')
        ->setCellValue('B' . $ind, 'Megyei')
        ->setCellValue('C' . $ind, 'Létszám');
    $ind++;
    $sheet
        ->setCellValue('A' . $ind, 'Iskolák száma megye mind:')
        ->setCellValue('C' . $ind, count(array_unique($iskArr)));
    $ind += 2;

    $sqlContests = str_replace('GROUP BY va.athlete_id', 'GROUP BY vc.contest_id', $sql);
    $data = $wpdb->get_results($wpdb->prepare($sqlContests, ...$params));
    $sheet
        ->setCellValue('B' . $ind, 'Összesen')
        ->setCellValue('C' . $ind, 'Országos')
        ->setCellValue('E' . $ind, 'Regionális')
        ->setCellValue('F' . $ind, 'Megyei');
    $ind += 1;
    $sheet
        ->setCellValue('A' . $ind, 'Diákolimpia versenyek száma:')
        ->setCellValue('B' . $ind, count($data))
        ->setCellValue('C' . $ind, count(array_filter($data, function ($fn) {
            return $fn->contest_type == 1;
        })))
        ->setCellValue('E' . $ind, count(array_filter($data, function ($fn) {
            return $fn->contest_type == 2;
        })))
        ->setCellValue('F' . $ind, count(array_filter($data, function ($fn) {
            return $fn->contest_type == 3;
        })));
    $ind += 2;

    autosize_columns($spreadsheet->getActiveSheet());

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="szezon_riport.xlsx"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
}

function riportPartDiakok($sheet, &$ind, $dataArr, $filter, $typeLabel){
    $megyei = array_filter($dataArr, $filter);
    $sheet
        ->setCellValue('A' . $ind, "Fogyatékossági csoport bontás $typeLabel:")
        ->setCellValue('B' . $ind, $typeLabel)
        ->setCellValue('C' . $ind, count($megyei))
        ->setCellValue('E' . $ind, count(array_filter($megyei, function ($fn) {
            return $fn->gender == 'férfi';
        })))
        ->setCellValue('F' . $ind,count(array_filter($megyei, function ($fn) {
            return $fn->gender == 'nő';
        })));
    $ind += 1;
    $megyeiDis = array();
    foreach ($megyei as $row) {
        $megyeiDis[$row->disability_group_name][] = $row;
    }
    foreach ($megyeiDis as $group => $arr){
        $sheet
        ->setCellValue('B' . $ind, $group)
        ->setCellValue('C' . $ind, count($arr))
        ->setCellValue('E' . $ind, count(array_filter($arr, function ($fn) {
            return $fn->gender == 'férfi';
        })))
        ->setCellValue('F' . $ind,count(array_filter($arr, function ($fn) {
            return $fn->gender == 'nő';
        })));
        $ind += 1;
    }
    $ind += 1;
}

function vespa_download_riport_verseny_versenyszam()
{
    global $wpdb;

    require VITAREX_VESPA_PLUGIN_DIR  . '/lib/vendor/autoload.php';

    $type = $_GET['download_riports'];

    $filter = $_GET['filter'];
    $dateFrom = $_GET['dateFrom'];
    $dateTo = $_GET['dateTo'];
    $seriesId = $_GET['series'];

    $filterType = '';

    $params = [];

    if($filter == 'all') $filterType = 'Összes verseny';
    else if ($filter == 'country') $filterType = "Csak országos versenyek";
    else if (is_numeric($filter)) {
        if ($filter > 0){
            $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_states WHERE state_id=%d", $filter));
            $filterType = "Megyei versenyek - $st->state_name";
        }
        else if ($filter == 0){
            $st = $wpdb->get_row("SELECT * FROM vespa_states");
            $filterType = "Megyei versenyek - összes";
        }  
    }
    else die;

    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    $ind = 1;

    $sheet
        ->setCellValue('A' . $ind, 'Riport:')
        ->setCellValue('B' . $ind, "Versenyek száma");
    $ind++;
    $sheet
        ->setCellValue('A' . $ind, 'Szűrés:')
        ->setCellValue('B' . $ind, $filterType)
        ->setCellValue('C' . $ind, 'Időszak:');
    if($seriesId > 0){
        $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_series WHERE series_id=%d", $seriesId));
        $sheet->setCellValue('D' . $ind, $st->series_name);
    }
    else {
        $sheet->setCellValue('D' . $ind, $dateFrom)->setCellValue('E' . $ind, $dateTo);
    }
    $ind++;

    date_default_timezone_set('Europe/Budapest');

    $filterFrom = date('Y-m-d', strtotime("$dateFrom"));
    $filterTo = date('Y-m-d', strtotime("$dateTo"));

    $sql = "SELECT vc.contest_id, vc.contest_type, vc.state_id, vs.sport_id, vs.sport_name, vse.sport_event_id, vse.sport_event_name FROM `vespa_contests` as vc
            JOIN vespa_constest_events as vce ON vc.contest_id=vce.contest_id
            LEFT JOIN vespa_sports as vs ON vce.sport_id=vs.sport_id
            LEFT JOIN vespa_sport_events as vse ON vce.event_id=vse.sport_event_id
            WHERE 1";
    if('tanev_diakolimpia_versenyszam' == $type){
        if (is_numeric($seriesId) && $seriesId > 0) {
            $sql .= " AND vc.contest_series=%d AND vc.contest_type IN (1,2,3)";
            $params[] = $seriesId;
        }
    }
    else if('tanev_diakolimpia_versenyszam_sportag' == $type){
        if (is_numeric($seriesId) && $seriesId > 0) {
            $sql .= " AND (vc.contest_series=%d OR vc.contest_type=4)";
            $params[] = $seriesId;
        }
    }
    else {
        $sql .= " AND vc.start_at >= %s AND vc.end_at <= %s";
        $params[] = $filterFrom;
        $params[] = $filterTo;
    }
    //megyei szűrés ha van kiválasztva
    if ($filter == 'country') {
        if ('tanev_diakolimpia_versenyszam_sportag' == $type) {
            $sql .= " AND vc.contest_type IN (1,4)";
        } else {
            $sql .= " AND vc.contest_type=1";
        }
    }
    else if (is_numeric($filter) && $filter > 0) {
        if ('tanev_diakolimpia_versenyszam_sportag' == $type) {
            $sql .= " AND (vc.contest_type=3 AND vc.state_id=%d OR vc.contest_type=4)";
        } else {
            $sql .= " AND vc.contest_type=3 AND vc.state_id=%d";
        }
        $params[] = $filter;
    }
    else if (is_numeric($filter) && $filter == 0) {
        if ('tanev_diakolimpia_versenyszam_sportag' == $type) {
            $sql .= " AND vc.contest_type IN (3,4)";
        } else {
            $sql .= " AND vc.contest_type=3";
        }
    }

    $data = $wpdb->get_results($wpdb->prepare($sql, ...$params));

    $sheet
        ->setCellValue('A' . $ind, 'Sportág')
        ->setCellValue('B' . $ind, 'Versenyszám')
        ->setCellValue('C' . $ind, 'Összesen');
    $ind++;

    $sportArr = array();
    foreach ($data as $row) {
        $sportArr[$row->sport_id][] = $row;
    }

    foreach ($sportArr as $row){
        $sportEvents = array();
        foreach( $row as $event ) {
            if($event->sport_event_name)
                array_push($sportEvents, $event->sport_event_name );
        }
        $sportEvents = array_unique($sportEvents);
        $uniqueContests = count(array_unique(array_column($row, 'contest_id')));
        $sheet
            ->setCellValue('A' . $ind, $row[0]->sport_name)
            ->setCellValue('C' . $ind, $uniqueContests);
        $ind++;
        foreach( $sportEvents as $event ) {
            $result = array_filter($row, function($f) use($event) {
                return $f->sport_event_name == $event;
            });
            $sheet
                ->setCellValue('B' . $ind, $event)
                ->setCellValue('C' . $ind, count(array_unique(array_column(array_values($result), 'contest_id'))));
            $ind++;
        }
    }

    $ind++;
    $sheet
        ->setCellValue('A' . $ind, "Összes verseny a tanévben:")
        ->setCellValue('B' . $ind, count(array_unique(array_column($data, 'contest_id'))));
    $ind++;

    autosize_columns($spreadsheet->getActiveSheet());

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="versenyszam_diakok.xlsx"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
}
function vespa_download_riport_versenyen_resztvevo_iskolak_szama()
{
    global $wpdb;

    require VITAREX_VESPA_PLUGIN_DIR  . '/lib/vendor/autoload.php';

    $filter = $_GET['filter'];
    $schoolDistrict = $_GET['schoolDistrict'];

    $seriesId = $_GET["series"];
    $filterType = '';
    $params = [];
    if($filter == 'all') $filterType = 'Összes verseny';
    else if ($filter == 'country') $filterType = "Csak országos versenyek";
    else if (is_numeric($filter)) {
        if($filter > 0){
            $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_states WHERE state_id=%d",$filter));
            $filterType = "Megyei versenyek - $st->state_name";
        }
        else if ($filter == 0){
            $st = $wpdb->get_row("SELECT * FROM vespa_states");
            $filterType = "Megyei versenyek - összes";
        }
    }
    else if (is_numeric($schoolDistrict) && $schoolDistrict > 0) {
        $sd = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_school_districts WHERE school_district_id=%d", $schoolDistrict));
        $filterType = "Tankerületi versenyek - $sd->school_district_name";
    }
    else die;
 
    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    $ind = 1;

    $sheet
        ->setCellValue('A' . $ind, 'Riport:')
        ->setCellValue('B' . $ind, "Versenyre jelentkezett iskolák száma");
    $ind++;
    $sheet
        ->setCellValue('A' . $ind, 'Szűrés:')
        ->setCellValue('B' . $ind, $filterType);
    $ind+=2;
    $sheet
    ->setCellValue('A' . $ind, 'Iskola neve')
    ->setCellValue('B' . $ind, 'Versenyek száma');
    $ind++;

    date_default_timezone_set('Europe/Budapest');

    $sql = "SELECT vi.institution_id, vi.ins_name, COUNT(DISTINCT vc.contest_id) as contest_count
            FROM vespa_athletes as va
            JOIN vespa_athlete_entries as vae ON va.athlete_id=vae.athlete_id
            JOIN vespa_institutions as vi ON va.school_id=vi.institution_id
            JOIN vespa_contests as vc ON vae.contest_id=vc.contest_id
            JOIN vespa_constest_events as vce ON vae.contest_event_id=vce.id
            WHERE vc.contest_series = $seriesId";

    if ($filter == 'country') {
        $sql .= " AND vc.contest_type=1";
    }
    else if (is_numeric($filter) && $filter > 0){
         $sql .= " AND vc.contest_type=3 AND vc.state_id=%d";
         $params[] = $filter;
    }
    else if (is_numeric($filter) && $filter == 0) {
        $sql .= " AND vc.contest_type=3";
    }
    if (is_numeric($schoolDistrict) && $schoolDistrict > 0) {
        $sql .= " AND vi.school_district_id=%d";
        $params[] = $schoolDistrict ;
    }

    $sql .= " GROUP BY vi.institution_id ORDER BY vi.ins_name ASC";

    $data = $wpdb->get_results($wpdb->prepare($sql, ...$params));

    foreach ($data as $row) {
        $sheet->setCellValue('A' . $ind, $row->ins_name);
        $sheet->setCellValue('B' . $ind, $row->contest_count);
        $ind++;
    }


    autosize_columns($spreadsheet->getActiveSheet());

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="tanevben_versenyen_indult_iskolak.xlsx"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    
}
function vespa_download_riport_verseny_diak()
{
    global $wpdb;

    require VITAREX_VESPA_PLUGIN_DIR  . '/lib/vendor/autoload.php';

    $dateFrom = $_GET['dateFrom'];
    $dateTo = $_GET['dateTo'];
    $filter = $_GET['filter'];
    $schoolDistrict = $_GET['schoolDistrict'];
    $gender = $_GET['gender'];
    $disabilityGroupId = $_GET['disabilityGroupId'];
    $institutionId = $_GET['institutionId'];
    $filterType = '';
    $params = [];
    if($filter == 'all') $filterType = 'Összes verseny';
    else if ($filter == 'country') $filterType = "Csak országos versenyek";
    else if (is_numeric($filter)) {
        if($filter > 0){
            $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_states WHERE state_id=%d",$filter));
            $filterType = "Megyei versenyek - $st->state_name";
        }
        else if ($filter == 0){
            $st = $wpdb->get_row("SELECT * FROM vespa_states");
            $filterType = "Megyei versenyek - összes";
        }
    }
    else if (is_numeric($schoolDistrict) && $schoolDistrict > 0) {
        $sd = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_school_districts WHERE school_district_id=%d", $schoolDistrict));
        $filterType = "Tankerületi versenyek - $sd->school_district_name";
    }
    else die;
 
    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    $ind = 1;

    $sheet
        ->setCellValue('A' . $ind, 'Riport:')
        ->setCellValue('B' . $ind, "Versenyre jelentkeztetett diákok száma");
    $ind++;
    $sheet
        ->setCellValue('A' . $ind, 'Szűrés:')
        ->setCellValue('B' . $ind, $filterType);
    $ind++;

    date_default_timezone_set('Europe/Budapest');

    $filterFrom = date('Y-m-d', strtotime("$dateFrom"));
    $filterTo = date('Y-m-d', strtotime("$dateTo"));

    $sql = "SELECT va.athlete_id, va.gender, va.disability_type, vdg.disability_group_name, va.birth_date, vc.contest_name, vc.contest_type, vc.state_id, vce.contest_id, vce.id as contest_event_id, vce.sport_id, vce.event_id, vs.sport_name, vse.sport_event_name FROM `vespa_athletes` as va
            JOIN vespa_athlete_entries as vae ON va.athlete_id=vae.athlete_id
            JOIN vespa_institutions as vi ON va.school_id=vi.institution_id
            JOIN vespa_contests as vc ON vae.contest_id=vc.contest_id
            JOIN vespa_constest_events as vce ON vae.contest_event_id=vce.id
            LEFT JOIN vespa_sports as vs ON vce.sport_id=vs.sport_id
            LEFT JOIN vespa_sport_events as vse ON vce.event_id=vse.sport_event_id
            JOIN vespa_disability_groups as vdg ON va.disability_type=vdg.disability_group_id
            WHERE vc.start_at >= '$filterFrom' AND vc.end_at <= '$filterTo'";

    if ($filter == 'country') {
        $sql .= " AND vc.contest_type=1";
    }
    else if (is_numeric($filter) && $filter > 0){
         $sql .= " AND vc.contest_type=3 AND vc.state_id=%d";
         $params[] = $filter;
    }
    else if (is_numeric($filter) && $filter == 0) {
        $sql .= " AND vc.contest_type=3";
    }
    if (is_numeric($schoolDistrict) && $schoolDistrict > 0) {
        $sql .= " AND vi.school_district_id=%d";
        $params[] = $schoolDistrict ;
    }
    if (is_numeric($disabilityGroupId) && $disabilityGroupId > 0) {
        $sql .= " AND va.disability_type=%d";
        $params[] = $disabilityGroupId;
    }
    if (is_numeric($institutionId) && $institutionId > 0) {
        $sql .= " AND vi.institution_id = %d";
        $params[] = $institutionId;
    }
    if ($gender == 'nő' || $gender == 'férfi') {
        $sql .= " AND va.gender=%s";
        $params[] = $gender;
    }
    $sqlAgeGroups = "
    SELECT vca.*, va.agegroup_name 
    FROM vespa_contest_agegroups as vca
    JOIN vespa_contests as vc ON vca.contest_id = vc.contest_id
    JOIN vespa_agegroups as va ON vca.agegroup_id = va.agegroup_id
    WHERE vc.start_at >= %s 
    AND vc.end_at <= %s
";
    $params[] = $filterFrom;
    $params[] = $filterTo;

    $data = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    $ageGroups = $wpdb->get_results($wpdb->prepare($sqlAgeGroups, $filterFrom, $filterTo));

    $sheet
        ->setCellValue('A' . $ind, 'Verseny')
        ->setCellValue('B' . $ind, 'Versenyszám')
        ->setCellValue('C' . $ind, 'Korcsoport')
        ->setCellValue('D' . $ind, 'Fogyatékossági csoport')
        ->setCellValue('E' . $ind, 'Darabszám');
    $ind++;
    $sumVerseny = 0;
    $contestArr = array();
    foreach ($data as $row) {
        $contestArr[$row->contest_id][$row->contest_event_id][] = $row;
    }

    $sumJelentkezes = 0;

    foreach ($contestArr as $actualContestId => $contestEvents){
        $sheet->setCellValue('A' . $ind, $contestEvents[array_key_first($contestEvents)][0]->contest_name);
        $ind++;

        $actualAgegroups = array_filter($ageGroups, function($f) use($actualContestId) {
            return $f->contest_id == $actualContestId;
        });

        foreach ($contestEvents as $contestEventId => $lines) {
            $sportName = $lines[0]->sport_name;
            $eventName = $lines[0]->sport_event_name;
            $sheet->setCellValue('B' . $ind, $eventName ? "$sportName - $eventName" : $sportName);
            $ind++;

            $korcsoportArr = array();
            foreach ($lines as $line) {
                $korcsoportArr[array_values(array_filter($actualAgegroups, function($f) use($line) {
                    return $f->date_from <= $line->birth_date && $f->date_to >= $line->birth_date;
                }))[0]->agegroup_id][$line->disability_group_name][] = $row;
            }

            foreach ($korcsoportArr as $id => $korcsoportLista) {
                $sheet->setCellValue('C' . $ind, array_values(array_filter($actualAgegroups, function($f) use($id) {
                    return $f->agegroup_id == $id;
                }))[0]->agegroup_name);
                $ind++;
                
                foreach ($korcsoportLista as $disName => $disList) {
                    $sumVerseny += count($disList);
                    $sheet->setCellValue('D' . $ind, $disName);
                    $sheet->setCellValue('E' . $ind, count($disList));
                    $ind++;
                }
            }
        }


    }

    $ind++;
    $sheet
        ->setCellValue('A' . $ind, "Összes verseny a tanévben:")
        ->setCellValue('E' . $ind, $sumVerseny);
    $ind++;

    autosize_columns($spreadsheet->getActiveSheet());

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="versenyszam_diakok.xlsx"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
}

function vespa_download_riport_tanev($type)
{
    global $wpdb;

    require VITAREX_VESPA_PLUGIN_DIR  . '/lib/vendor/autoload.php';

    $filter = $_GET['filter'];
    $schoolDistrictId = $_GET['schoolDistrict'];
    $dateFrom = $_GET['dateFrom'];
    $dateTo = $_GET['dateTo'];
    $seriesId = $_GET['series'];
    $stateId = is_numeric($filter) ? (int)$filter : 0;
    $filterType = '';
    if($filter == 'all') $filterType = 'Összes verseny';
    else if ($filter == 'country') $filterType = 'Csak országos versenyek';
    else if ($stateId == 0 && $schoolDistrictId == 0) $filterType = 'Országos';
    else if ($schoolDistrictId > 0) {
        $tk = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_school_districts WHERE school_district_id=%d",$schoolDistrictId));
        $filterType = "Tankerület - $tk->school_district_name";
    }
    else if ($stateId > 0) {
        $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_states WHERE state_id=%d",$stateId));
        $filterType = "Megyei - $st->state_name";
    }

    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    $ind = 1;

    $sheet
        ->setCellValue('A' . $ind, 'Riport:')
        ->setCellValue('B' . $ind, 'Tanévben versenyen indult iskolák száma');
    $ind++;
    $sheet
        ->setCellValue('A' . $ind, 'Szűrés:')
        ->setCellValue('B' . $ind, $filterType)
        ->setCellValue('C' . $ind, 'Időszak:');

    if(is_numeric($seriesId) && $seriesId > 0){
        $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_series WHERE series_id=%d",$seriesId));
        $sheet->setCellValue('D' . $ind, $st->series_name);
    }
    else {
        $sheet->setCellValue('D' . $ind, $dateFrom)->setCellValue('E' . $ind, $dateTo);
    }
    $ind++;

    date_default_timezone_set('Europe/Budapest');

    $filterFrom = date('Y-m-d', strtotime("$dateFrom"));
    $filterTo = date('Y-m-d', strtotime("$dateTo"));
    $params = [];
    $sql = "SELECT COUNT(DISTINCT va.athlete_id) as diakok, vi.institution_id, vi.ins_name FROM `vespa_institutions` as vi
            LEFT JOIN vespa_athletes as va ON va.school_id=vi.institution_id
            LEFT JOIN vespa_athlete_entries as vae ON va.athlete_id=vae.athlete_id
            LEFT JOIN vespa_contests as vc ON vae.contest_id=vc.contest_id
            WHERE 1";
 
    if('tanev_diakolimpia_diakok' == $type){
        if ($seriesId > 0) {
            $sql .= " AND vc.contest_series=%d";
            $params[] = $seriesId;
        }
    }
    else {
        $sql .= " AND vc.start_at >= %s AND vc.end_at <= %s";
        $params[] = $filterFrom;
        $params[] = $filterTo;
    }

    if ($filter == 'country') {
        $sql .= " AND vc.contest_type=1";
    } elseif (is_numeric($filter) && $stateId > 0) {
        $sql .= " AND vc.contest_type=3 AND vc.state_id=%d";
        $params[] = $stateId;
    } elseif (is_numeric($filter) && $stateId == 0) {
        $sql .= " AND vc.contest_type=3";
    }
    if($schoolDistrictId > 0) {
         $sql .= " AND vi.school_district_id=%d";
         $params[] = $schoolDistrictId;
    }
    $sql .= " GROUP BY vi.institution_id;";
    $data = $wpdb->get_results($wpdb->prepare($sql, ...$params));

    $params = [];
    $sql = "SELECT vi.institution_id, vi.ins_name FROM `vespa_institutions` as vi
            WHERE 1";
    if($schoolDistrictId > 0) {
        $sql .= " AND vi.school_district_id=%d";
        $params[] = $schoolDistrictId;
    }
    if($stateId > 0) {
        $sql .= " AND vi.ins_state=%d";
        $params[] = $stateId;
    }
    $allSchool = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    foreach ($allSchool as $school) {
        $school->diakok = 0;
        foreach ($data as $validData) {
            if($school->institution_id == $validData->institution_id){
                $school->diakok = $validData->diakok;
                break;
            }
        }
    }

    $sheet
        ->setCellValue('A' . $ind, 'Intézmény')
        ->setCellValue('B' . $ind, 'Indított diákok');
    $ind++;

    $sum = 0;
    foreach ($allSchool as $row) {


        $sheet
            ->setCellValue('A' . $ind, $row->ins_name)
            ->setCellValue('B' . $ind, $row->diakok);
        $ind++;
        $sum += $row->diakok;
    }

    $sheet
        ->setCellValue('A' . $ind, 'Összesen')
        ->setCellValue('B' . $ind, $sum);
    $ind++;

    autosize_columns($spreadsheet->getActiveSheet());

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="tanevben_versenyen_indult_iskolak.xlsx"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
}

function vespa_download_riport_legnepszerubb_sportagak()
{
    global $wpdb;

    require VITAREX_VESPA_PLUGIN_DIR  . '/lib/vendor/autoload.php';

    $dateFrom = $_GET['dateFrom'];
    $dateTo = $_GET['dateTo'];
    $filter = $_GET['filter'];
    $schoolDistrictId = $_GET['schoolDistrict'];
    $gender = $_GET['gender'];
    $disabilityGroupId = $_GET['disabilityGroupId'];
    $filterType = '';
    if($filter == 'all') $filterType = 'Összes verseny';
    else if ($filter == 'country') $filterType = "Legnépszerűbb országos sportágak";
    else if (is_numeric($filter) && $filter > 0) {
        $st = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_states WHERE state_id=%d", $filter));
        $filterType = "Legnépszerűbb megyei sportágak - $st->state_name";
    }
    else if (is_numeric($schoolDistrictId) && $schoolDistrictId > 0) {
        $sd = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_school_districts WHERE school_district_id=%d",$schoolDistrictId));
        $filterType = "Legnépszerűbb tankerületi sportágak - $sd->school_district_name";
    }
    else die;

    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    $ind = 1;

    $sheet
        ->setCellValue('A' . $ind, 'Riport:')
        ->setCellValue('B' . $ind, 'Legnépszerűbb sportágak');
    $ind++;
    $sheet
        ->setCellValue('A' . $ind, 'Szűrés:')
        ->setCellValue('B' . $ind, $filterType);
    $ind++;

    date_default_timezone_set('Europe/Budapest');

    $filterFrom = date('Y-m-d', strtotime("$dateFrom"));
    $filterTo = date('Y-m-d', strtotime("$dateTo"));
    $params = [];

    $sql = "SELECT COUNT(va.athlete_id) as nevezettek, vce.id as contest_event_id, vce.sport_id, vce.event_id, vs.sport_name, vse.sport_event_name
            FROM vespa_athletes as va
            JOIN vespa_athlete_entries as vae ON va.athlete_id=vae.athlete_id
            JOIN vespa_institutions as vi ON va.school_id=vi.institution_id
            JOIN vespa_contests as vc ON vae.contest_id=vc.contest_id
            JOIN vespa_constest_events as vce ON vae.contest_event_id=vce.id
            LEFT JOIN vespa_sports as vs ON vce.sport_id=vs.sport_id
            LEFT JOIN vespa_sport_events as vse ON vce.event_id=vse.sport_event_id
            JOIN vespa_disability_groups as vdg ON va.disability_type=vdg.disability_group_id
            WHERE vc.start_at >= %s AND vc.end_at <= %s";
    
    $params[] = date('Y-m-d', strtotime($filterFrom));
    $params[] = date('Y-m-d', strtotime($filterTo));

    if ($filter == 'country') {
        $sql .= " AND vc.contest_type=1";
    } elseif (is_numeric($filter) && $filter > 0) {
        $sql .= " AND vc.contest_type=3 AND vc.state_id=%d";
        $params[] = $filter;
    }
    
    if (is_numeric($schoolDistrict) && $schoolDistrict > 0) {
        $sql .= " AND vi.school_district_id=%d";
        $params[] = $schoolDistrict;
    }
    
    if (is_numeric($disabilityGroupId) && $disabilityGroupId > 0) {
        $sql .= " AND va.disability_type=%d";
        $params[] = $disabilityGroupId;
    }
    
    if ($gender == 'nő' || $gender == 'férfi') {
        $sql .= " AND va.gender=%s";
        $params[] = $gender;
    }
    
    $sql .= " GROUP BY contest_event_id";

    $data = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    
    $sportArr = array();
    foreach ($data as $row) {
        $sportArr[$row->sport_id][] = $row;
    }

    $sheet
        ->setCellValue('A' . $ind, 'Sportág')
        ->setCellValue('B' . $ind, 'Versenyek száma')
        ->setCellValue('C' . $ind, 'Nevezettek száma')
        ->setCellValue('D' . $ind, 'Átlagos nevezett/verseny');
    $ind++;

    $sum = 0;
    foreach ($sportArr as $row) {
        $count = array_sum(array_map(function($f) {
            return $f->nevezettek;
        }, $row));
        $sheet
            ->setCellValue('A' . $ind, $row[0]->sport_name)
            ->setCellValue('B' . $ind, count($row))
            ->setCellValue('C' . $ind, $count)
            ->setCellValue('D' . $ind, round($count/count($row), 1)); 
        $ind++;
        $sum += $count;
    }

    $sheet
        ->setCellValue('A' . $ind, 'Összesenen:')
        ->setCellValue('C' . $ind, $sum);
    $ind++;

    autosize_columns($spreadsheet->getActiveSheet());

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="legnepszerubb_sportagak.xlsx"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
}
