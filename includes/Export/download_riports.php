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

    // Minden paraméter isset-védett: a hiányzó GET paraméter PHP warningot
    // írna a válaszba, ami a binárisan írt XLSX-et is elronthatja.
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $seriesId = isset($_GET['series']) ? $_GET['series'] : 0;
    $schoolDistrict = isset($_GET['schoolDistrict']) ? $_GET['schoolDistrict'] : 0;
    $gender = isset($_GET['gender']) ? $_GET['gender'] : '';
    $disabilityGroupId = isset($_GET['disabilityGroupId']) ? $_GET['disabilityGroupId'] : 0;
    $institutionId = isset($_GET['institutionId']) ? $_GET['institutionId'] : 0;
    $year = isset($_GET['year']) ? $_GET['year'] : 0;
    $filterType = '';

    // A szűrés leírása az XLSX fejlécébe. Az ágak sorrendje kötött: a 0
    // ("Összes megye") ellenőrzése a > 0 ág UTÁN áll.
    // A $stateRow változónév szándékos: a lenti tanév-blokk a $st-t használja.
    if ($filter === 'country') {
        $filterType = 'Csak országos versenyek';
    } elseif (is_numeric($filter) && $filter > 0) {
        $stateRow = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_states WHERE state_id=%d", intval($filter)));
        $filterType = 'Megyei versenyek - ' . ($stateRow ? $stateRow->state_name : $filter);
    } elseif (is_numeric($filter) && intval($filter) === 0) {
        $filterType = 'Összes megyei verseny';
    } else {
        $filterType = 'Összes verseny (országos + regionális + megyei)';
    }

    // A szezon és a naptári év külön-külön elhagyható, ezért a fejléc-írás
    // nem függhet attól, hogy van-e szezon. Korábban itt egy die() állt: szezon
    // nélkül a riport némán megszakadt, üres választ adva.
    $seriesName = vespa_riport_szezon_neve($seriesId);

    $sheet
        ->setCellValue('A' . $ind, 'Időszak')
        ->setCellValue('B' . $ind, vespa_riport_periodus_felirat($seriesName, $year));
    $ind++;
    $sheet
        ->setCellValue('A' . $ind, 'Szűrés')
        ->setCellValue('B' . $ind, $filterType);
    $ind += 2;

    $sql = "SELECT va.athlete_id, va.gender, va.disability_type, vdg.disability_group_name, va.birth_date, vi.institution_id, vc.contest_name, vc.contest_type, vc.state_id, vce.contest_id, vce.id as contest_event_id, vce.sport_id, vce.event_id, vs.sport_name, vse.sport_event_name FROM `vespa_athletes` as va
            JOIN vespa_athlete_entries as vae ON va.athlete_id=vae.athlete_id
            JOIN vespa_institutions as vi ON va.school_id=vi.institution_id
            JOIN vespa_contests as vc ON vae.contest_id=vc.contest_id
            JOIN vespa_constest_events as vce ON vae.contest_event_id=vce.id
            LEFT JOIN vespa_sports as vs ON vce.sport_id=vs.sport_id
            LEFT JOIN vespa_sport_events as vse ON vce.event_id=vse.sport_event_id
            JOIN vespa_disability_groups as vdg ON va.disability_type=vdg.disability_group_id
            WHERE 1";

    // Az időszak-feltétel MINDEN további feltétel ELŐTT kerül be, hogy a %d
    // helyőrzők sorrendje egyezzen a $params sorrendjével.
    $periodus = vespa_riport_periodus_szuro($seriesId, $year);
    $sql   .= $periodus['sql'];
    $params = $periodus['params'];

    // Mind a NÉGY ág kötelező. Korábban az 'all' és a 0 ("Összes megye") ág
    // hiányzott, ezért ezekben az esetekben SEMMILYEN contest_type feltétel nem
    // került a lekérdezésbe -> a szabadidős (4) versenyek is beleszámítottak, és
    // az "Összes megye" ugyanazt adta, mint az "Összes".
    // Az ágak sorrendje kötött: a 0 ellenőrzése a > 0 ág UTÁN áll.
    if ($filter === 'country') {
        $sql .= " AND vc.contest_type = %d";
        $params[] = VespaContestType::ORSZAGOS;
    } elseif (is_numeric($filter) && $filter > 0) {
        $sql .= " AND vc.contest_type = %d AND vc.state_id = %d";
        $params[] = VespaContestType::MEGYEI;
        $params[] = intval($filter);
    } elseif (is_numeric($filter) && intval($filter) === 0) {
        // "Összes megye"
        $sql .= " AND vc.contest_type = %d";
        $params[] = VespaContestType::MEGYEI;
    } else {
        // 'all' — a riport pontosan ezt a három vödröt jeleníti meg,
        // a szabadidős (4) szándékosan kimarad.
        $sql .= " AND vc.contest_type IN (%d,%d,%d)";
        $params[] = VespaContestType::ORSZAGOS;
        $params[] = VespaContestType::REGIONALIS;
        $params[] = VespaContestType::MEGYEI;
    }

    // A szűrőblokkok kézi másolatai helyett közös helper: így nem tud egyik
    // riportban elgépelt változónévvel vagy hiányzó feltétellel elcsúszni.
    $intezmeny = vespa_riport_intezmeny_szuro($schoolDistrict, $institutionId);
    $sql      .= $intezmeny['sql'];
    $params    = array_merge($params, $intezmeny['params']);

    $sportolo = vespa_riport_sportolo_szuro($disabilityGroupId, $gender);
    $sql     .= $sportolo['sql'];
    $params   = array_merge($params, $sportolo['params']);

// (diák, versenytípus) páronként egy sor. Így a típus szerinti vödrök
// determinisztikusak; a több típuson induló diák minden érintett vödörbe
// beleszámít. Az "össz" és a nemek szerinti bontás ezért athlete_id szerint
// egyedivé tesz (lásd lent).
$sql .= " GROUP BY va.athlete_id, vc.contest_type";

$data = vespa_riport_get_results($sql, $params);

    // A GROUP BY (athlete_id, contest_type) miatt a több típuson induló diák
    // több sorban szerepel. Az "össz" és a nemek szerinti bontás EGYEDI diákot
    // számol; a típusonkénti vödrök viszont a tényleges részvételt mutatják,
    // ezért azok összege TÖBB lehet, mint az összesen.
    $egyediDiakok = array();
    foreach ($data as $row) {
        $egyediDiakok[$row->athlete_id] = $row;
    }

    $ferfi = 0;
    $no = 0;
    foreach ($egyediDiakok as $row) {
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
        ->setCellValue('C' . $ind, count($egyediDiakok))
        ->setCellValue('E' . $ind, $ferfi)
        ->setCellValue('F' . $ind, $no);
    $ind += 2;

    $sheet
        ->setCellValue('A' . $ind, 'Megjegyzés: aki több versenytípuson is indult, minden érintett bontásban szerepel, ezért a bontások összege több lehet, mint a fenti összesen.');
    $ind++;

    $sheet
        ->setCellValue('B' . $ind, 'Fogyatékossági csoport')
        ->setCellValue('C' . $ind, 'Létszám')
        ->setCellValue('E' . $ind, 'Fiú')
        ->setCellValue('F' . $ind, 'Lány');
    $ind++;

    riportPartDiakok($sheet, $ind, $data, function ($fn){
        return $fn->contest_type == VespaContestType::ORSZAGOS;
    }, 'országos');
    riportPartDiakok($sheet, $ind, $data, function ($fn){
        return $fn->contest_type == VespaContestType::REGIONALIS;
    }, 'regionális');
    riportPartDiakok($sheet, $ind, $data, function ($fn){
        return $fn->contest_type == VespaContestType::MEGYEI;
    }, 'megyei');

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
        if($row->contest_type == VespaContestType::ORSZAGOS) array_push($iskArr, $row->institution_id);
    }
    $sheet
        ->setCellValue('A' . $ind, 'Diákolimpian részvett:')
        ->setCellValue('B' . $ind, 'Országos')
        ->setCellValue('C' . $ind, 'Létszám');
    $ind++;
    $sheet
        ->setCellValue('A' . $ind, 'Iskolák száma országos:')
        ->setCellValue('C' . $ind, count(array_unique($iskArr)));
    $ind += 2;

    $iskArr = array();
    foreach ($data as $row) {
        if($row->contest_type == VespaContestType::REGIONALIS) array_push($iskArr, $row->institution_id);
    }
    $sheet
        ->setCellValue('A' . $ind, 'Diákolimpian részvett:')
        ->setCellValue('B' . $ind, 'Regionális')
        ->setCellValue('C' . $ind, 'Létszám');
    $ind++;
    $sheet
        ->setCellValue('A' . $ind, 'Iskolák száma regionális:')
        ->setCellValue('C' . $ind, count(array_unique($iskArr)));
    $ind += 2;

    $iskArr = array();
    foreach ($data as $row) {
        if($row->contest_type == VespaContestType::MEGYEI) array_push($iskArr, $row->institution_id);
    }
    $sheet
        ->setCellValue('A' . $ind, 'Diákolimpian részvett:')
        ->setCellValue('B' . $ind, 'Megyei')
        ->setCellValue('C' . $ind, 'Létszám');
    $ind++;
    $sheet
        ->setCellValue('A' . $ind, 'Iskolák száma megyei:')
        ->setCellValue('C' . $ind, count(array_unique($iskArr)));
    $ind += 2;

    // FIGYELEM: a keresett szövegnek PONTOSAN egyeznie kell a fenti GROUP BY-jal.
    // Ha nem talál egyezést, a str_replace némán az eredeti $sql-t adja vissza,
    // a versenyszámlálás a diák szerinti GROUP BY-jal fut, és HIBAÜZENET NÉLKÜL
    // rossz értéket ad.
    $sqlContests = str_replace('GROUP BY va.athlete_id, vc.contest_type', 'GROUP BY vc.contest_id', $sql);
    $data = vespa_riport_get_results($sqlContests, $params);
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
            return $fn->contest_type == VespaContestType::ORSZAGOS;
        })))
        ->setCellValue('E' . $ind, count(array_filter($data, function ($fn) {
            return $fn->contest_type == VespaContestType::REGIONALIS;
        })))
        ->setCellValue('F' . $ind, count(array_filter($data, function ($fn) {
            return $fn->contest_type == VespaContestType::MEGYEI;
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
    $rows = array_filter($dataArr, $filter);
    $sheet
        ->setCellValue('A' . $ind, "Fogyatékossági csoport bontás $typeLabel:")
        ->setCellValue('B' . $ind, $typeLabel)
        ->setCellValue('C' . $ind, count($rows))
        ->setCellValue('E' . $ind, count(array_filter($rows, function ($fn) {
            return $fn->gender == 'férfi';
        })))
        ->setCellValue('F' . $ind,count(array_filter($rows, function ($fn) {
            return $fn->gender == 'nő';
        })));
    $ind += 1;
    $rowsDis = array();
    foreach ($rows as $row) {
        $rowsDis[$row->disability_group_name][] = $row;
    }
    foreach ($rowsDis as $group => $arr){
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

    // Minden paraméter isset-védett: a hiányzó GET paraméter PHP warningot
    // írna a válaszba, ami a binárisan írt XLSX-et is elronthatja.
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
    $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
    $seriesId = isset($_GET['series']) ? $_GET['series'] : 0;
    // A tanév-riportoknál (lásd lent) a naptári év is szűrhet; a hiányzó GET
    // paraméter warningot okozna, ezért isset-védett.
    $year = isset($_GET['year']) ? $_GET['year'] : 0;

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
    // A tanév- és az intervallumos ág megkülönböztetése a RIPORTTÍPUS alapján
    // történik, nem a $seriesId értéke alapján: a szezon már elhagyható, ezért
    // a $seriesId === 0 tanév-riportnál is érvényes állapot, és ilyenkor
    // nem a dateFrom/dateTo tartományt kell kiírni.
    $tanevRiport = ('tanev_diakolimpia_versenyszam' == $type
        || 'tanev_diakolimpia_versenyszam_sportag' == $type);

    $sheet
        ->setCellValue('A' . $ind, 'Szűrés:')
        ->setCellValue('B' . $ind, $filterType)
        ->setCellValue('C' . $ind, 'Időszak:');
    if ($tanevRiport) {
        $sheet->setCellValue('D' . $ind, vespa_riport_periodus_felirat(
            vespa_riport_szezon_neve($seriesId),
            $year
        ));
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
        // A contest_type megkötés nem időszak-, hanem versenytípus-szűrés,
        // ezért szezon nélkül is érvényben marad: ez a riport a diákolimpiai
        // versenyekről szól, a szabadidős (4) szándékosan kimarad.
        $periodus = vespa_riport_periodus_szuro($seriesId, $year);
        $sql     .= $periodus['sql'] . " AND vc.contest_type IN (1,2,3)";
        $params   = array_merge($params, $periodus['params']);
    }
    else if('tanev_diakolimpia_versenyszam_sportag' == $type){
        // Ez a riport a szabadidős versenyeket szezontól függetlenül
        // beszámolja — ezt a helper $szabadidosKivetel ága adja.
        $periodus = vespa_riport_periodus_szuro($seriesId, $year, true);
        $sql     .= $periodus['sql'];
        $params   = array_merge($params, $periodus['params']);
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

    $data = vespa_riport_get_results($sql, $params);

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

    // Minden paraméter isset-védett: a hiányzó GET paraméter PHP warningot
    // írna a válaszba, ami a binárisan írt XLSX-et is elronthatja.
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $schoolDistrict = isset($_GET['schoolDistrict']) ? $_GET['schoolDistrict'] : 0;

    $seriesId = isset($_GET['series']) ? $_GET['series'] : 0;
    // Az időszak-szűrőhöz szükséges év; ha nincs megadva, a helper alapértelmezés
    // szerint kezeli (0 = nincs időszak-szűrés).
    $year = isset($_GET['year']) ? $_GET['year'] : 0;
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
        ->setCellValue('B' . $ind, $filterType)
        ->setCellValue('C' . $ind, 'Időszak:')
        ->setCellValue('D' . $ind, vespa_riport_periodus_felirat(
            vespa_riport_szezon_neve($seriesId),
            $year
        ));
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
            WHERE 1";

    // A $seriesId eddig nyersen, prepared paraméter nélkül került az SQL-be.
    // Az időszak-feltétel MINDEN további feltétel ELŐTT kerül be, hogy a
    // helyőrzők sorrendje egyezzen a $params sorrendjével.
    $periodus = vespa_riport_periodus_szuro($seriesId, $year);
    $sql     .= $periodus['sql'];
    $params   = array_merge($params, $periodus['params']);

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
    // Ennek a riportnak a felülete intézményt nem kínál, ezért az
    // intézmény-argumentum fixen 0.
    $intezmeny = vespa_riport_intezmeny_szuro($schoolDistrict, 0);
    $sql      .= $intezmeny['sql'];
    $params    = array_merge($params, $intezmeny['params']);

    $sql .= " GROUP BY vi.institution_id ORDER BY vi.ins_name ASC";

    $data = vespa_riport_get_results($sql, $params);

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

    // Minden paraméter isset-védett: a hiányzó GET paraméter PHP warningot
    // írna a válaszba, ami a binárisan írt XLSX-et is elronthatja.
    $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
    $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $schoolDistrict = isset($_GET['schoolDistrict']) ? $_GET['schoolDistrict'] : 0;
    $gender = isset($_GET['gender']) ? $_GET['gender'] : '';
    $disabilityGroupId = isset($_GET['disabilityGroupId']) ? $_GET['disabilityGroupId'] : 0;
    $institutionId = isset($_GET['institutionId']) ? $_GET['institutionId'] : 0;
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

    if ($filter == 'all') {
        // Az "Összes" csak az országos (1) és megyei (3) versenyeket tartalmazza,
        // így megegyezik az országos + összes megyei lekérdezés összegével.
        // (A regionális [2] és szabadidős [4] versenyek nem szerepelnek ebben a riportban.)
        $sql .= " AND vc.contest_type IN (1,3)";
    }
    else if ($filter == 'country') {
        $sql .= " AND vc.contest_type=1";
    }
    else if (is_numeric($filter) && $filter > 0){
         $sql .= " AND vc.contest_type=3 AND vc.state_id=%d";
         $params[] = $filter;
    }
    else if (is_numeric($filter) && $filter == 0) {
        $sql .= " AND vc.contest_type=3";
    }
    $intezmeny = vespa_riport_intezmeny_szuro($schoolDistrict, $institutionId);
    $sql      .= $intezmeny['sql'];
    $params    = array_merge($params, $intezmeny['params']);

    $sportolo = vespa_riport_sportolo_szuro($disabilityGroupId, $gender);
    $sql     .= $sportolo['sql'];
    $params   = array_merge($params, $sportolo['params']);

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

    // Minden paraméter isset-védett: a hiányzó GET paraméter PHP warningot
    // írna a válaszba, ami a binárisan írt XLSX-et is elronthatja.
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $schoolDistrictId = isset($_GET['schoolDistrict']) ? $_GET['schoolDistrict'] : 0;
    $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
    $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
    $seriesId = isset($_GET['series']) ? $_GET['series'] : 0;
    // A naptári év szűrő új paraméter.
    $year = isset($_GET['year']) ? $_GET['year'] : 0;
    $sportId = isset($_GET['sport']) ? $_GET['sport'] : 0;
    $sportEventId = isset($_GET['sportEventId']) ? $_GET['sportEventId'] : 0;
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

    // Az ágválasztás a RIPORTTÍPUSRA épül, nem a $seriesId értékére: a szezon
    // már elhagyható, és tanév-riportnál akkor sem a dateFrom/dateTo
    // tartományt kell kiírni, ha nincs kiválasztva szezon.
    if('tanev_diakolimpia_diakok' == $type){
        $sheet->setCellValue('D' . $ind, vespa_riport_periodus_felirat(
            vespa_riport_szezon_neve($seriesId),
            $year
        ));
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
            LEFT JOIN vespa_constest_events as vce ON vae.contest_event_id=vce.id
            WHERE 1";
 
    if('tanev_diakolimpia_diakok' == $type){
        $periodus = vespa_riport_periodus_szuro($seriesId, $year);
        $sql     .= $periodus['sql'];
        $params   = array_merge($params, $periodus['params']);
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
    if (is_numeric($sportId) && $sportId > 0) {
        $sql .= " AND vce.sport_id=%d";
        $params[] = intval($sportId);
    }
    if (is_numeric($sportEventId) && $sportEventId > 0) {
        $sql .= " AND vce.event_id=%d";
        $params[] = intval($sportEventId);
    }
    $sql .= " GROUP BY vi.institution_id;";
    $data = vespa_riport_get_results($sql, $params);

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
    // Ennek a lekérdezésnek a $params tömbje már ma is üresen maradhat, ha se
    // tankerület, se megye nincs szűrve — a burkoló ilyenkor prepare() nélkül fut.
    $allSchool = vespa_riport_get_results($sql, $params);
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

    // Minden paraméter isset-védett: a hiányzó GET paraméter PHP warningot
    // írna a válaszba, ami a binárisan írt XLSX-et is elronthatja.
    // Az institutionId itt új olvasás - a szűrőhasználata egy későbbi taskban kerül be.
    $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
    $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $schoolDistrictId = isset($_GET['schoolDistrict']) ? $_GET['schoolDistrict'] : 0;
    $institutionId = isset($_GET['institutionId']) ? $_GET['institutionId'] : 0;
    $gender = isset($_GET['gender']) ? $_GET['gender'] : '';
    $disabilityGroupId = isset($_GET['disabilityGroupId']) ? $_GET['disabilityGroupId'] : 0;
    $sportId = isset($_GET['sport']) ? $_GET['sport'] : 0;
    $sportEventId = isset($_GET['sportEventId']) ? $_GET['sportEventId'] : 0;
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

    if (is_numeric($sportId) && $sportId > 0) {
        $sql .= " AND vce.sport_id=%d";
        $params[] = intval($sportId);
    }
    if (is_numeric($sportEventId) && $sportEventId > 0) {
        $sql .= " AND vce.event_id=%d";
        $params[] = intval($sportEventId);
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
