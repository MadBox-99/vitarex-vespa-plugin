<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;


add_action('init', 'init_download_contest_docs');
function init_download_contest_docs()
{
    if (isset($_GET['download_contest_docs'])) {
        $type       = sanitize_key($_GET['download_contest_docs']);
        $contest_id = isset($_GET['contest_id']) ? intval($_GET['contest_id']) : 0;

        // A dokumentumok szerephez kötöttek: a felületen eddig is csak a
        // jogosultaknak jelent meg a menüpont, a közvetlen URL viszont
        // bárkinek — akár bejelentkezés nélkül is — kiszolgálta őket.
        if ($contest_id <= 0 || !vespa_user_can_download_contest_doc($type)) {
            wp_die(
                'Nincs jogosultságod a dokumentum letöltéséhez.',
                'Jogosulatlan hozzáférés',
                array('response' => 403)
            );
        }

        if ('listing' == $type) { // Kiiras
            vespa_download_listing($contest_id);
        } else if ('answers' == $type) { // Beszámoló
            vespa_download_answers($contest_id);
        } else if ('logistic' == $type) { // Sportolói logisztika
            vespa_download_logistic($contest_id);
        } else if ('athletes' == $type) { // Nevezesi lista
            vespa_download_athletes($contest_id);
        } else if ('medical_approval' == $type) { // Orvosi igazolas
            vespa_download_medical_approval($contest_id);
        } else if ('emails' == $type) { // Email lista
            vespa_download_emails($contest_id);
        }

        exit;
    }
}


// --------------------------------------------------
function vespa_download_medical_approval($contest_id)
{
    global $wpdb;

    error_reporting(E_ERROR | E_PARSE);

    require VITAREX_VESPA_PLUGIN_DIR  . '/lib/vendor/autoload.php';

    $school_id = vespa_get_my_school_id();
    if (!is_numeric($school_id)) {
        die;
    }

    // get data for contest
    $record = $GLOBALS['VESPA_Contests']->load($contest_id);
    $school = $GLOBALS['VESPA_Institution']->load($school_id);

    $subtype = $GLOBALS['VESPA_Contest_Subtypes']->load($record->contest_subtypes);
    $series = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_series WHERE series_id= %d", $record->contest_series));

    $type = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_contest_types WHERE contest_type_id= %d", $record->contest_type));

    $age_groups = $wpdb->get_results($wpdb->prepare("SELECT * FROM vespa_contest_agegroups WHERE contest_id=%d ORDER BY agegroup_id ASC", $contest_id));

    // ---------------------------------------
    $upload_dir   = wp_upload_dir();
    $path = $upload_dir['basedir'] . '/medical_approval';

    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }

    $filename = $path . '/nevezes_' . $contest_id . '.pdf';

    $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];

    $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    $mpdf = new Mpdf\Mpdf([
        'mode' => 'utf8',
        'fontDir' => array_merge($fontDirs, [
            VITAREX_VESPA_PLUGIN_DIR . '/fonts',
        ]),
        'fontdata' => $fontData + [
            'myriadpro' => [
                'R'  => 'MyriadPro-Regular.ttf',
                'B'  => 'MyriadPro-Bold.ttf',
            ]
        ],
        'default_font' => 'myriadpro'
    ]);
    $stylesheet = file_get_contents(VITAREX_VESPA_PLUGIN_DIR . '/css/pdf-style.css');
    $mpdf->WriteHTML($stylesheet, Mpdf\HTMLParserMode::HEADER_CSS);

    $html = '';
    $html .= '<table border="0" width="100%">';
    $html .= '<tr>';
    $html .= '  <td width="50%" style="vertical-align:middle;"><h1>Nevezési lap</h1></td>';
    $html .= '  <td width="50%" align="right"><img src="' . VITAREX_VESPA_PLUGIN_DIR . '/images/FODISZ_fekvo_logo_color.jpg" style="float:right; height:70px;"></td>';
    $html .= '</tr>';
    $html .= '</table>';


    $html .= '<hr>';


    $html .= '<table border="0" width="100%">';

    $html .= '<tr>';
    $html .= '  <td width="40%">Verseny neve:</td>';
    $html .= '  <td>' . stripslashes($record->contest_name)  . '</td>';
    $html .= '</tr> ';

    $html .= '<tr>';
    $html .= '  <td width="40%">Verseny szervezője:</td>';
    $html .= '  <td>' . $record->organiser  . '</td>';
    $html .= '</tr> ';

    $html .= '<tr>';
    $html .= '  <td width="40%">Verseny ideje:</td>';
    $html .= '  <td>' . $record->start_at . ' - ' . $record->end_at  . '</td>';
    $html .= '</tr> ';

    $html .= '<tr>';
    $html .= '  <td width="40%">Verseny jellege:</td>';
    $html .= '  <td>' . $type->contest_type_name  . '</td>';
    $html .= '</tr> ';

    $html .= '<tr>';
    $html .= '  <td width="40%">Verseny helyszíne:</td>';
    $html .= '  <td>' . $record->place_name  . '</td>';
    $html .= '</tr> ';

    $html .= '<tr>';
    $html .= '  <td width="40%">Verseny címe:</td>';
    $html .= '  <td>' . $record->place_address  . '</td>';
    $html .= '</tr> ';

    $html .= '<tr>';
    $html .= '  <td width="40%">Nevező iskola / egyesület neve:</td>';
    $html .= '  <td>' . $school->ins_name  . '</td>';
    $html .= '</tr> ';

    $html .= '<tr>';
    $html .= '  <td width="40%">Testnevelő tanár / edző neve:</td>';
    $html .= '  <td>' . vespa_get_my_name()  . '</td>';
    $html .= '</tr> ';

    $html .= '</table>';


    $html .= '<hr style="margin-bottom:20px;">';

    $sql_list = "SELECT e.*, p.sport_name, s.sport_event_name, p.sport_id
                                    FROM vespa_constest_events as e
                                    LEFT JOIN vespa_sports as p ON p.sport_id = e.sport_id
                                    LEFT JOIN vespa_sport_events as s ON s.sport_event_id = e.event_id
                                    WHERE e.contest_id=%d";

    $list=$wpdb->get_results($wpdb->prepare($sql_list,$record->contest_id));
    
    if (!empty($list)) {
        foreach ($list as $race) {

            $disability_ids = array_map('intval', explode(',', $race->disability_groups));

            $placeholders = implode(',', array_fill(0, count($disability_ids), '%d'));

            $sql = "SELECT GROUP_CONCAT(disability_group_name SEPARATOR ',') as name 
                    FROM vespa_disability_groups 
                    WHERE disability_group_id IN ($placeholders)";

            $dis = $wpdb->get_results($wpdb->prepare($sql, ...$disability_ids));


            $athletes = $wpdb->get_results($wpdb->prepare("SELECT a.*, e.entry_date 
            FROM vespa_athletes as a 
            JOIN vespa_athlete_entries as e ON (e.athlete_id=a.athlete_id) 
            WHERE a.school_id=%d AND e.contest_id=%d AND e.contest_event_id=%d 
            ORDER BY a.athlete_name ASC",$school_id, $contest_id,$race->id ));
            
            if (count($athletes) == 0)
                continue;

            $html .= '<table border="0" width="100%" class="table" style=" margin-bottom:15px;">';

            $html .= '<tr>';
            $html .= '  <td colspan="4">';
            $html .=    $race->sport_name . ' / ' . $race->sport_event_name . ' (' . $dis[0]->name . ')';
            $html .= '  </td>';
            $html .= '</tr> ';

            $ind = 0;
            $html .= '<tr>';
            foreach ($athletes as $athlete) {
                if ($ind != 0 && $ind % 2 == 0) {
                    $html .= '</tr><tr>';
                }

                $html .= '<td width="25%">&nbsp;</td>';
                $html .= '<td width="25%">';
                $html .= $athlete->athlete_name . '<br>';
                $html .= $athlete->birth_date . '<br>';
                $html .= '</td>';



                $ind++;
            }

            if ($ind != 0 && $ind % 2 == 1) {
                $html .= '<td width="25%">&nbsp;</td>';
                $html .= '<td width="25%">&nbsp;</td>';
            }

            $html .= '</tr>';

            $html .= '</table>';
        }
    }

    $html .= '<p>A nevezési lapon felsorolt diákok sportolásra alkalmias egészségi állapotban vannak, versenyen részt vehetnek.</p>';

    $html .= '<table border="0" width="100%">';
    $html .= '<tr>';
    $html .= '  <td width="33%" style="height:100px;">Hely:</td>';
    $html .= '  <td width="33%">Dátum:</td>';
    $html .= '  <td width="33%">Orvos neve:</td>';
    $html .= '</tr> ';

    $html .= '<tr>';
    $html .= '  <td width="33%">Pecsét:</td>';
    $html .= '  <td width="33%"></td>';
    $html .= '  <td width="33%">Orvos aláírása:</td>';
    $html .= '</tr> ';
    $html .= '</table>';

    $mpdf->WriteHTML($html);

    $mpdf->Output($filename, 'F');

    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/pdf');
    // A lemezen maradhat az azonosítós név (ütközésmentes), a letöltött fájl
    // viszont a verseny nevét kapja.
    vespa_send_contest_download_header($contest_id, 'orvosi engedély', 'pdf');
    header('Content-Length: ' . filesize($filename));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Read and output the PDF file
    readfile($filename);
    exit;
}


// --------------------------------------------------
function vespa_download_listing($contest_id, $isBase64 = false, $saveFile = false)
{
    global $wpdb;

    error_reporting(E_ERROR | E_PARSE);

    require VITAREX_VESPA_PLUGIN_DIR  . '/lib/vendor/autoload.php';

    // get data for contest
    $record = $GLOBALS['VESPA_Contests']->load($contest_id);

    $subtype = $GLOBALS['VESPA_Contest_Subtypes']->load($record->contest_subtypes);
    $series = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_series WHERE series_id= %d", $record->contest_series));

    $type = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_contest_types WHERE contest_type_id= %d", $record->contest_type));

    $age_groups = $wpdb->get_results($wpdb->prepare("SELECT * FROM vespa_contest_agegroups WHERE contest_id=%d ORDER BY agegroup_id ASC", $contest_id));

    $upload_dir   = wp_upload_dir();
    $path = $upload_dir['basedir'] . '/listings';

    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }

    $filename = $path . '/' . $contest_id . '.pdf';

    $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];

    $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    $mpdf = new Mpdf\Mpdf([
        'mode' => 'utf8',
        'fontDir' => array_merge($fontDirs, [
            VITAREX_VESPA_PLUGIN_DIR . '/fonts',
        ]),
        'fontdata' => $fontData + [
            'myriadpro' => [
                'R'  => 'MyriadPro-Regular.ttf',
                'B'  => 'MyriadPro-Bold.ttf',
            ]
        ],
        'default_font' => 'myriadpro'
    ]);
    $mpdf->showImageErrors = false;
    $stylesheet = file_get_contents(VITAREX_VESPA_PLUGIN_DIR . '/css/pdf-style.css');
    $mpdf->WriteHTML($stylesheet, Mpdf\HTMLParserMode::HEADER_CSS);

    $html = '';
    $html .= '<div style="text-align:center;">';
    $html .= '  <img src="' . VITAREX_VESPA_PLUGIN_DIR . '/images/FODISZ_fekvo_logo_color.jpg">';
    $html .= '</div>';
    $html .= '<div style="position:fixed; top:40%; margin-top: -65px; width:100%; text-align: center;">';
    $html .= '  <h1 style="margin-top:50px; margin-bottom:50px;">' . stripslashes($record->contest_name) . '</h1>';
    $html .= '  <h1 style="margin-bottom:50px; font-size: 35px;">VERSENYKIÍRÁS</h1>';
    $html .= '</div>';

    $html .= '<div class="image-footer">';
    $html .= '  <table class="" border=0 width="100%">';
    $html .= '  <tr>';
    $html .= '      <td align="left"><img height="70" src="' . VITAREX_VESPA_PLUGIN_DIR . '/images/KK_logo_cmyk.jpg"></td>';
    $html .= '      <td align="center"><img height="70" src="' . VITAREX_VESPA_PLUGIN_DIR . '/images/MPBK_logo_color.jpg"></td>';
    $html .= '      <td align="right"><img height="70" src="' . VITAREX_VESPA_PLUGIN_DIR . '/images/hm.jpg"></td>';
    $html .= '  </tr>';
    $html .= '  </table>';
    $html .= '</div>';


    $mpdf->WriteHTML($html);
    $mpdf->AddPage();



    $html = '';
    $html .= '<table>';
    $html .= '<tr>';
    $html .= '<td class="label bp">Verseny rendezője és szakmai megvalósítója:</td>';
    $html .= '<td class="bp">' .  $record->organiser .'</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td class="label bp">Helyszín, időpont:</td>';
    $html .= '<td class="bp">' .  $record->place_name . '<br>' . $record->place_address . '<br>' . $record->start_at . '</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td class="label bp">Korosztályok:</td>';
    $html .= '<td class="bp">';
    $labels = array('I.', 'II.', 'III.', 'IV.', 'V.', 'VI.');
    foreach ($age_groups as $group) {
        $html .= $labels[($group->agegroup_id - 1)] . ' ' . $group->date_from . ' - ' . $group->date_to . '<br>';
    }
    $html .= '</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td class="label bp">Lebonyolítás:</td>';
    $html .= '<td class="bp">' . $subtype->contest_subtype_name . ' / ' . $series->series_name . '</td>';
    $html .= '</tr>';

    // nevezési határidő + fix nevezési blokk
    $html .= '<tr>';
    $html .= '<td class="label bp">Nevezési határidő:</td>';
    $html .= '<td class="bp2 border-title" align="center">' . vespa_get_date_str($record->school_entry_end_at) . '</td>';
    $html .= '</tr>';

    // versenyszámok

    $list = $wpdb->get_results($wpdb->prepare("SELECT e.*, p.sport_name, s.sport_event_name, p.sport_id
    FROM vespa_constest_events as e
    LEFT JOIN vespa_sports as p ON p.sport_id = e.sport_id
    LEFT JOIN vespa_sport_events as s ON s.sport_event_id = e.event_id
    WHERE e.contest_id=%d",$record->contest_id ));

    
    $html .= '<tr>';
    $html .= '<td class="label bp">Versenyszámok:</td>';
    $html .= '<td class="bp">';
    foreach ($list as $race) {
        if ($race->sport_event_name) {
            $html .= $race->sport_name . ' / ' . $race->sport_event_name . ' ' . $race->sport_id . '<br>';
        } else {
            $html .= $race->sport_name . ' ' . $race->sport_id . '<br>';
        }
    }
    $html .= '</td>';
    $html .= '</tr>';
   
    $html .= '</table>';

    $html .= stripslashes($record->listing_text);

    $sport_id = $list[0]->sport_id;

    if($isBase64){
        return base64_encode($mpdf->Output('', 'S'));
    }

    $mpdf->WriteHTML($html);
    $mpdf->Output($filename, 'F');
    if($saveFile)
        return $filename;
    else {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        vespa_send_contest_download_header($contest_id, 'kiírás', 'pdf');
        header('Content-Length: ' . filesize($filename));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        // Read and output the PDF file
        readfile($filename);
        exit;
    }
}


// --------------------------------------------------
function vespa_get_date_str($date)
{
    $str = date("Y. M d. H:i", strtotime($date));

    $str = str_replace("Jan", "Január", $str);
    $str = str_replace("Feb", "Február", $str);
    $str = str_replace("Mar", "Március", $str);
    $str = str_replace("Apr", "Április", $str);
    $str = str_replace("May", "Május", $str);
    $str = str_replace("Jun", "Június", $str);
    $str = str_replace("Jul", "Július", $str);
    $str = str_replace("Aug", "Augusztus", $str);
    $str = str_replace("Sep", "Szeptember", $str);
    $str = str_replace("Oct", "Október", $str);
    $str = str_replace("Nov", "November", $str);
    $str = str_replace("Dec", "December", $str);

    return $str;
}

// --------------------------------------------------
function vespa_download_logistic($contest_id)
{
    global $wpdb;

    require VITAREX_VESPA_PLUGIN_DIR  . '/lib/vendor/autoload.php';

    if (!(
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::MEGYEI_VERSENYIGAZGATO)
        || VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::FOVESZ_FODISZ_SPORTIGAZGATO)
        || VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ADMINISZTRATOR)
        || is_super_admin()
    )) {
        echo 'Nincs megfelelő jogosultságod a művelethez.';
        die;
    }

    $total_no    = 0;
    $total_ferfi = 0;
    $total       = vespa_get_default_escort_data();
    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    $styleArray = array(
        'font'  => array(
            'bold'  => true,
    ));
    
    $sql = "SELECT vi.ins_name, vi.ins_zipcode, vi.ins_city, vi.ins_address, vs.state_name, vi.institution_id as school_id 
                FROM vespa_athlete_entries as ae 
                JOIN vespa_athletes as a ON (a.athlete_id=ae.athlete_id)                
                JOIN vespa_contests as c ON (c.contest_id=ae.contest_id)
                JOIN vespa_institutions as vi ON (vi.institution_id=a.school_id)
                JOIN vespa_states as vs ON (vs.state_id=vi.ins_state) 
                WHERE c.contest_id=%d AND ae.contest_id=%d 
                GROUP BY a.school_id 
                ORDER BY ins_name ASC";

    $list = $wpdb->get_results($wpdb->prepare($sql, $contest_id, $contest_id));
    
    $escortList = $wpdb->get_results($wpdb->prepare("SELECT * FROM vespa_contests_escorts WHERE contest_id=%d", $contest_id));
    
    $ind = 6;
    foreach ($list as $item) {
        $escortItem = '';
        foreach ($escortList as $elem) {
            if ($elem->school_id == $item->school_id) {
                $escortItem = $elem->escort_data;
                break;
            }
        }
        $data = unserialize($escortItem);
        $dataValid = !is_bool($data);

        $spreadsheet->setActiveSheetIndex(0)
            ->setCellValue('A' . $ind, $item->ins_name)
            ->setCellValue('B' . $ind, $item->state_name)
            ->setCellValue('C' . $ind, $item->ins_city)
            ->setCellValue('D' . $ind, $item->ins_address)
            ->setCellValue('E' . $ind, $item->ins_zipcode);
        $ind++;

        $spreadsheet->setActiveSheetIndex(0)
            ->setCellValue('A' . $ind, '')
            ->setCellValue('B' . $ind, 'Létszám:')
            ->setCellValue('C' . $ind, 'Résztvevõ Férfi(ak) / Fiú(k):')
            ->setCellValue('D' . $ind, 'Résztvevõ Nõ(k)/lány(ok):')
            ->setCellValue('E' . $ind, 'Alvás létszám:')
            ->setCellValue('F' . $ind, 'Alvás Férfi(ak) / Fiú(k):')
            ->setCellValue('G' . $ind, 'Alvás Nõ(k)/lány(ok):')
            ->setCellValue('H' . $ind, 'Házastársak:')
            ->setCellValue('I' . $ind, 'Egyéb információ:')
            ->setCellValue('J' . $ind, 'Étkezés reggeli:')
            ->setCellValue('K' . $ind, 'Étkezés ebéd:')
            ->setCellValue('L' . $ind, 'Étkezés vacsora:')
            ->setCellValue('M' . $ind, 'Úti csomag:')
            ->setCellValue('N' . $ind, 'Étkezés egyéb infó:');
        $ind++;

        $lszind = $ind;
        $no     = 0;
        $ferfi  = 0;

        $spreadsheet->setActiveSheetIndex(0)
            ->setCellValue('A' . $ind, 'Diák adatok')
            ->setCellValue('B' . $ind, 0) //$data['diak']['letszam'])
            ->setCellValue('C' . $ind, 0) //$data['diak']['fiu'])
            ->setCellValue('D' . $ind, 0) //$data['diak']['lany'])

            ->setCellValue('E' . $ind, $dataValid ? $data['diak']['letszam'] : 0)
            ->setCellValue('F' . $ind, $dataValid ? $data['diak']['fiu'] : 0)
            ->setCellValue('G' . $ind, $dataValid ? $data['diak']['lany'] : 0)
            ->setCellValue('H' . $ind, 0) // házas
            ->setCellValue('I' . $ind, $dataValid ? $data['diak']['igeny'] : 0)
            ->setCellValue('J' . $ind, $dataValid ? $data['etkezes']['diak']['reggeli'] : 0)
            ->setCellValue('K' . $ind, $dataValid ? $data['etkezes']['diak']['ebed'] : 0)
            ->setCellValue('L' . $ind, $dataValid ? $data['etkezes']['diak']['vacsora'] : 0)
            ->setCellValue('M' . $ind, $dataValid ? $data['etkezes']['diak']['uti'] : 0)
            ->setCellValue('N' . $ind, $dataValid ? $data['etkezes']['diak']['igeny'] : 0);
        $ind++;

        $spreadsheet->setActiveSheetIndex(0)
            ->setCellValue('A' . $ind, 'Kísérő adatok')
            ->setCellValue('B' . $ind, $dataValid ? $data['kisero']['letszam'] : 0)
            ->setCellValue('C' . $ind, $dataValid ? $data['kisero']['ferfi'] : 0)
            ->setCellValue('D' . $ind, $dataValid ? $data['kisero']['no'] : 0)
            ->setCellValue('E' . $ind, $dataValid ? $data['kisero']['letszam'] : 0)
            ->setCellValue('F' . $ind, $dataValid ? $data['kisero']['ferfi'] : 0)
            ->setCellValue('G' . $ind, $dataValid ? $data['kisero']['no'] : 0)
            ->setCellValue('H' . $ind, $dataValid ? $data['kisero']['hazas'] : 0) // házas
            ->setCellValue('I' . $ind, $dataValid ? $data['kisero']['igeny'] : 0)
            ->setCellValue('J' . $ind, $dataValid ? $data['etkezes']['kisero']['reggeli'] : 0)
            ->setCellValue('K' . $ind, $dataValid ? $data['etkezes']['kisero']['ebed'] : 0)
            ->setCellValue('L' . $ind, $dataValid ? $data['etkezes']['kisero']['vacsora'] : 0)
            ->setCellValue('M' . $ind, $dataValid ? $data['etkezes']['kisero']['uti'] : 0)
            ->setCellValue('N' . $ind, $dataValid ? $data['etkezes']['kisero']['igeny'] : 0);
        $ind++;

        $usersTable = $wpdb->prefix . 'users';

        $diak_sql = "SELECT a.*, vg.disability_group_name, u.display_name as felhasznalo, u.user_email
                FROM vespa_athletes as a 
                JOIN vespa_athlete_entries as ae ON (ae.athlete_id=a.athlete_id) 
                JOIN vespa_disability_groups as vg ON (vg.disability_group_id=a.disability_type)
                JOIN $usersTable u ON u.id=ae.user_id
                WHERE a.school_id=%d AND ae.contest_id=%d
                GROUP BY a.athlete_id
                ORDER BY a.athlete_name ASC";

        $diak_list = $wpdb->get_results($wpdb->prepare($diak_sql,$item->school_id,$contest_id));
        

        if (!empty($diak_list)) {

        $spreadsheet->setActiveSheetIndex(0)
            ->setCellValue('A' . $ind, 'Név')
            ->setCellValue('B' . $ind, 'Nem')
            ->setCellValue('C' . $ind, 'Születési idő')
            ->setCellValue('D' . $ind, 'Sérülés specifikum')
            ->setCellValue('E' . $ind, 'Bővebb sérülés specifikum')
            ->setCellValue('F' . $ind, 'Nevező testnevelő')
            ->setCellValue('G' . $ind, 'Nevező email címe')
            ->setCellValue('H' . $ind, 'Egyéb információ');
        $ind++;

        foreach ($diak_list as $diak) {
            if ('férfi' == $diak->gender) {
                $ferfi++;
                $total_ferfi++;
            } else {
                $no++;
                $total_no++;
            }

            $spreadsheet->setActiveSheetIndex(0)
                ->setCellValue('A' . $ind, $diak->athlete_name)
                ->setCellValue('B' . $ind, $diak->gender)
                ->setCellValue('C' . $ind, $diak->birth_date)
                ->setCellValue('D' . $ind, $diak->disability_group_name)
                ->setCellValue('E' . $ind, $diak->gender)
                ->setCellValue('F' . $ind, $diak->felhasznalo ?? 'Ismeretlen felhasználó')
                ->setCellValue('G' . $ind, $diak->user_email)
                ->setCellValue('H' . $ind, $diak->note);
            $ind++;
        }

        $spreadsheet->setActiveSheetIndex(0)
            ->setCellValue('B' . $lszind, ($ferfi + $no))
            ->setCellValue('C' . $lszind, $ferfi)
            ->setCellValue('D' . $lszind, $no);
        }

        if ($dataValid && !empty($data['kiserok'])) {

            $spreadsheet->setActiveSheetIndex(0)
                ->setCellValue('A' . $ind, 'Kísérő neve:')
                ->setCellValue('B' . $ind, 'Telefon:')
                ->setCellValue('C' . $ind, 'Email:')
                ->setCellValue('D' . $ind, 'Allergia:');
            $ind++;

            foreach ($data['kiserok'] as $gk) {
                // nev, mobil, email, allergia                
                $spreadsheet->setActiveSheetIndex(0)
                    ->setCellValue('A' . $ind, $gk['nev'])
                    ->setCellValue('B' . $ind, $gk['mobil'])
                    ->setCellValue('C' . $ind, $gk['email'])
                    ->setCellValue('D' . $ind, $gk['allergia']);
                $ind++;
            }
        }

        // gépkocsi vezetők felsorolása
        // Gépkocsivezetõ neve: Telefon:    Email:  Allergia:

        if ($dataValid && !empty($data['gepkocsivezetok'])) {

            $spreadsheet->setActiveSheetIndex(0)
                ->setCellValue('A' . $ind, 'Gépkocsivezetõ neve:')
                ->setCellValue('B' . $ind, 'Telefon:')
                ->setCellValue('C' . $ind, 'Email:')
                ->setCellValue('D' . $ind, 'Allergia:');
            $ind++;

            foreach ($data['gepkocsivezetok'] as $gk) {
                // nev, mobil, email, allergia                
                $spreadsheet->setActiveSheetIndex(0)
                    ->setCellValue('A' . $ind, $gk['nev'])
                    ->setCellValue('B' . $ind, $gk['mobil'])
                    ->setCellValue('C' . $ind, $gk['email'])
                    ->setCellValue('D' . $ind, $gk['allergia']);
                $ind++;
            }
        }

        // handle total
        if ($dataValid) {
            $total = vespa_sum_arrays($total, $data);
        }

        $ind++;
    }

    $ind = 2;
    $data = $total;

    $spreadsheet->setActiveSheetIndex(0)
        ->setCellValue('A' . $ind, 'Összesen:')
        ->setCellValue('B' . $ind, 'Létszám:')
        ->setCellValue('C' . $ind, 'Résztvevõ Férfi(ak) / Fiú(k):')
        ->setCellValue('D' . $ind, 'Résztvevõ Nõ(k)/lány(ok):')
        ->setCellValue('E' . $ind, 'Alvás létszám:')
        ->setCellValue('F' . $ind, 'Alvás Férfi(ak) / Fiú(k):')
        ->setCellValue('G' . $ind, 'Alvás Nõ(k)/lány(ok):')
        ->setCellValue('H' . $ind, 'Házastársak:')
        ->setCellValue('I' . $ind, 'Egyéb információ:')
        ->setCellValue('J' . $ind, 'Étkezés reggeli:')
        ->setCellValue('K' . $ind, 'Étkezés ebéd:')
        ->setCellValue('L' . $ind, 'Étkezés vacsora:')
        ->setCellValue('M' . $ind, 'Úti csomag:')
        ->setCellValue('N' . $ind, 'Étkezés egyéb infó:');
    $ind++;

    $spreadsheet->setActiveSheetIndex(0)
        ->setCellValue('A' . $ind, 'Diák adatok')
        ->setCellValue('B' . $ind, ($total_ferfi + $total_no))
        ->setCellValue('C' . $ind, $total_ferfi)
        ->setCellValue('D' . $ind, $total_no)
        ->setCellValue('E' . $ind, $data['diak']['letszam'])
        ->setCellValue('F' . $ind, $data['diak']['fiu'])
        ->setCellValue('G' . $ind, $data['diak']['lany'])
        ->setCellValue('H' . $ind, 0) // házas
        ->setCellValue('I' . $ind, $data['diak']['igeny'])
        ->setCellValue('J' . $ind, $data['etkezes']['diak']['reggeli'])
        ->setCellValue('K' . $ind, $data['etkezes']['diak']['ebed'])
        ->setCellValue('L' . $ind, $data['etkezes']['diak']['vacsora'])
        ->setCellValue('M' . $ind, $data['etkezes']['diak']['uti'])
        ->setCellValue('N' . $ind, $data['etkezes']['diak']['igeny']);
    $ind++;

    $spreadsheet->setActiveSheetIndex(0)
        ->setCellValue('A' . $ind, 'Kísérő adatok')
        ->setCellValue('B' . $ind, $data['kisero']['letszam'])
        ->setCellValue('C' . $ind, $data['kisero']['ferfi'])
        ->setCellValue('D' . $ind, $data['kisero']['no'])
        ->setCellValue('E' . $ind, $data['kisero']['letszam'])
        ->setCellValue('F' . $ind, $data['kisero']['ferfi'])
        ->setCellValue('G' . $ind, $data['kisero']['no'])
        ->setCellValue('H' . $ind, $data['kisero']['hazas']) // házas
        ->setCellValue('I' . $ind, $data['kisero']['igeny'])
        ->setCellValue('J' . $ind, $data['etkezes']['kisero']['reggeli'])
        ->setCellValue('K' . $ind, $data['etkezes']['kisero']['ebed'])
        ->setCellValue('L' . $ind, $data['etkezes']['kisero']['vacsora'])
        ->setCellValue('M' . $ind, $data['etkezes']['kisero']['uti'])
        ->setCellValue('N' . $ind, $data['etkezes']['kisero']['igeny']);
    $ind++;

    autosize_columns($spreadsheet->getActiveSheet());
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    vespa_send_contest_download_header($contest_id, 'sportolói logisztika', 'xlsx');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
}

function vespa_download_emails($contest_id)
{
    global $wpdb;

    require VITAREX_VESPA_PLUGIN_DIR  . '/lib/vendor/autoload.php';

    if (!(
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::MEGYEI_VERSENYIGAZGATO)
        || VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::FOVESZ_FODISZ_SPORTIGAZGATO)
        || VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ADMINISZTRATOR)
        || is_super_admin()
    )) {
        echo 'Nincs megfelelő jogosultságod a művelethez.';
        die;
    }

    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    $usersTable = $wpdb->prefix . 'users';
    $usersMetaTable = $wpdb->prefix . 'usermeta';
    $sql = "SELECT wpu.user_email, wpu.display_name, vi.institution_id, vi.ins_name FROM vespa_athlete_entries as ae 
            JOIN $usersTable as wpu ON (wpu.ID=ae.user_id)
            JOIN $usersMetaTable as wpm ON (wpu.ID=wpm.user_id)
            JOIN vespa_institutions as vi ON (vi.institution_id=wpm.meta_value)
            WHERE ae.contest_id=%d AND wpm.meta_key='school_id'
            GROUP BY ae.user_id
            ORDER BY vi.ins_name ASC";

    $users = $wpdb->get_results($wpdb->prepare($sql, $contest_id));
    
    $escortList = null;
    if(is_numeric($contest_id)){
    $escortList = $wpdb->get_results($wpdb->prepare("SELECT * FROM vespa_contests_escorts WHERE contest_id=%d",$contest_id));
    }
    $list = array();
    foreach ($users as $user) {
        if (!isset($list[$user->institution_id])) {
            $list[$user->institution_id] = [
                'ins_name' => $user->ins_name,
                'users' => [],
                'kiserok' => [],
                'gepkocsivezetok' => []
            ];
        }
        $list[$user->institution_id]['users'][] = $user;
    }
    foreach ($escortList as $escortItem) {
        $data = unserialize($escortItem->escort_data);
        $dataValid = !is_bool($data);

        if ($dataValid && !empty($data['kiserok']) && isset($list[$escortItem->school_id])) {
            array_push($list[$escortItem->school_id]['kiserok'], ...$data['kiserok']);
        }   
        if ($dataValid && !empty($data['gepkocsivezetok']) && isset($list[$escortItem->school_id])) {
            array_push($list[$escortItem->school_id]['gepkocsivezetok'], ...$data['gepkocsivezetok']);
        }
    }

    $ind = 1;
    foreach ($list as $instId => $inst) {
        $spreadsheet->setActiveSheetIndex(0)
            ->setCellValue('A' . $ind, "Intézmény:")
            ->setCellValue('B' . $ind, $inst['ins_name']);
        $ind++;

        foreach ($inst["users"] as $item) {
            $spreadsheet->setActiveSheetIndex(0)
                ->setCellValue('A' . $ind, $item->display_name)
                ->setCellValue('B' . $ind, $item->user_email);
            $ind++;
        }
        if (!empty($inst['kiserok'])) {
            $spreadsheet->setActiveSheetIndex(0)
                ->setCellValue('A' . $ind, 'Kísérő neve')
                ->setCellValue('B' . $ind, 'Email');
            $ind++;
            foreach ($inst['kiserok'] as $gk) {            
                $spreadsheet->setActiveSheetIndex(0)
                    ->setCellValue('A' . $ind, $gk['nev'])
                    ->setCellValue('B' . $ind, $gk['email']);
                $ind++;
            }
        }   
        if (!empty($inst['gepkocsivezetok'])) {
            $spreadsheet->setActiveSheetIndex(0)
                ->setCellValue('A' . $ind, 'Gépkocsivezetõ neve')
                ->setCellValue('B' . $ind, 'Email');
            $ind++;
            foreach ($inst['gepkocsivezetok'] as $gk) {            
                $spreadsheet->setActiveSheetIndex(0)
                    ->setCellValue('A' . $ind, $gk['nev'])
                    ->setCellValue('B' . $ind, $gk['email']);
                $ind++;
            }
        }   
        $ind++;
    }

    autosize_columns($spreadsheet->getActiveSheet());

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    vespa_send_contest_download_header($contest_id, 'nevezettek email lista', 'xlsx');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
}

// --------------------------------------------------
function vespa_sum_arrays($a, $b)
{
    $a['diak']['letszam'] += is_numeric($b['diak']['letszam']) ? $b['diak']['letszam'] : 0;
    $a['diak']['fiu'] += is_numeric($b['diak']['fiu']) ? $b['diak']['fiu'] : 0;
    $a['diak']['lany'] += is_numeric($b['diak']['lany']) ? $b['diak']['lany'] : 0;

    $a['kisero']['letszam'] += is_numeric($b['kisero']['letszam']) ? $b['kisero']['letszam'] : 0;
    $a['kisero']['ferfi'] += is_numeric($b['kisero']['ferfi']) ? $b['kisero']['ferfi'] : 0;
    $a['kisero']['no'] += is_numeric($b['kisero']['no']) ? $b['kisero']['no'] : 0;
    $a['kisero']['hazas'] += is_numeric($b['kisero']['hazas']) ? $b['kisero']['hazas'] : 0;

    $a['etkezes']['diak']['letszam']+= is_numeric($b['etkezes']['diak']['letszam']) ? $b['etkezes']['diak']['letszam']: 0;
    $a['etkezes']['diak']['reggeli'] += is_numeric($b['etkezes']['diak']['reggeli']) ? $b['etkezes']['diak']['reggeli'] : 0;
    $a['etkezes']['diak']['ebed']      += is_numeric($b['etkezes']['diak']['ebed']) ? $b['etkezes']['diak']['ebed'] : 0;
    $a['etkezes']['diak']['vacsora']   += is_numeric($b['etkezes']['diak']['vacsora']) ? $b['etkezes']['diak']['vacsora'] : 0;
    $a['etkezes']['diak']['uti']       += is_numeric($b['etkezes']['diak']['uti']) ? $b['etkezes']['diak']['uti'] : 0;

    $a['etkezes']['kisero']['letszam'] += is_numeric($b['etkezes']['kisero']['letszam']) ? $b['etkezes']['kisero']['letszam'] : 0;
    $a['etkezes']['kisero']['reggeli'] += is_numeric($b['etkezes']['kisero']['reggeli']) ? $b['etkezes']['kisero']['reggeli'] : 0;
    $a['etkezes']['kisero']['ebed']    += is_numeric($b['etkezes']['kisero']['ebed']) ? $b['etkezes']['kisero']['ebed'] : 0;
    $a['etkezes']['kisero']['vacsora'] += is_numeric($b['etkezes']['kisero']['vacsora']) ? $b['etkezes']['kisero']['vacsora'] : 0;
    $a['etkezes']['kisero']['uti']     += is_numeric($b['etkezes']['kisero']['uti']) ? $b['etkezes']['kisero']['uti'] : 0;

    return $a;
}

// --------------------------------------------------
// download athletes list for contest
function vespa_download_athletes($contest_id)
{
    global $wpdb;
    
    // disability_groups
    /*        
        $sql = "SELECT a.*, s.sport_event_name, p.sport_name, vi.ins_name, vs.state_name, e.gender as egender, e.dfrom, e.dto, e.agnames, (SELECT GROUP_CONCAT(disability_group_name SEPARATOR ',') FROM vespa_disability_groups WHERE FIND_IN_SET(disability_group_id, e.disability_groups) ) as disgroup  
                FROM vespa_athletes as a 
                JOIN vespa_athlete_entries as ae ON (ae.athlete_id=a.athlete_id) 
                        JOIN vespa_constest_events as e  ON (e.event_id=ae.event_id AND e.contest_id=ae.contest_id)
                        JOIN vespa_sport_events as s ON s.sport_event_id = e.event_id 
                        JOIN vespa_sports as p ON p.sport_id = s.sport_id 
                        JOIN vespa_institutions as vi ON (vi.institution_id=a.school_id)
                        JOIN vespa_states as vs ON (vs.state_id=vi.ins_state)
                        
                 WHERE $filter AND ae.contest_id=" . $contest_id . " 
                 ORDER BY CONCAT(s.sport_event_name, ' ', p.sport_name ) ASC, ins_name ASC, a.gender ASC, a.athlete_name ASC";
*/
    $filter = '1';
    $params = [];
    $params[] = $contest_id;
    $params[] = $contest_id;
    
    if (
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::TESTNEVELO)
        || VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ISKOLAIGAZGATO)
    ) {
        $school_id = vespa_get_my_school_id();
        $filter = ' school_id=%d';
        $params[] = $school_id;
    }
    $params[] = $contest_id;
    $sql = "
SELECT a.*, s.sport_event_name, p.sport_name, vi.ins_name, vs.state_name, e.gender as egender, e.dfrom, e.dto, e.agnames, ae.contest_event_id,
(SELECT GROUP_CONCAT(disability_group_name SEPARATOR ',') 
FROM vespa_disability_groups WHERE FIND_IN_SET(disability_group_id, e.disability_groups) ) as disgroup, 
(SELECT disability_group_name  FROM vespa_disability_groups WHERE FIND_IN_SET(disability_group_id, a.disability_type) ) as athlete_disgroup, 
(SELECT agegroup_name FROM vespa_contest_agegroups as vca JOIN vespa_agegroups as va ON (va.agegroup_id=vca.agegroup_id) WHERE vca.contest_id=%d AND a.birth_date BETWEEN vca.date_from AND vca.date_to LIMIT 1) as agname,
(SELECT vca.agegroup_id FROM vespa_contest_agegroups as vca JOIN vespa_agegroups as va ON (va.agegroup_id=vca.agegroup_id) WHERE vca.contest_id=%d AND a.birth_date BETWEEN vca.date_from AND vca.date_to LIMIT 1) as ag_id
FROM vespa_athletes as a 
                JOIN vespa_athlete_entries as ae ON (ae.athlete_id=a.athlete_id)
                        LEFT JOIN vespa_constest_events as e  ON (e.contest_id=ae.contest_id AND ae.contest_event_id = e.id)
                        LEFT JOIN vespa_sports as p ON p.sport_id = e.sport_id
                        LEFT JOIN vespa_sport_events as s ON s.sport_event_id = e.event_id
                        LEFT JOIN vespa_institutions as vi ON (vi.institution_id=a.school_id)
                        LEFT JOIN vespa_states as vs ON (vs.state_id=vi.ins_state)

                WHERE $filter AND ae.contest_id=%d
                ORDER BY CONCAT(COALESCE(s.sport_event_name, p.sport_name, ''),' ', athlete_disgroup,' ',a.gender,' ', agname, ' ', a.athlete_name) ASC";

    // echo $sql;
    // exit;
    require VITAREX_VESPA_PLUGIN_DIR  . '/lib/vendor/autoload.php';

    // versenyszám, tanuló, születési idő, intézmény, megye
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    

    $list = $wpdb->get_results($wpdb->prepare($sql,...$params));


    // versenyszám, tanuló, születési idő, intézmény, megye
    // Terem Atlétika, I korcsoport, Tanak, Férfi
    $ind = 1;
    $prev = '';
    foreach ($list as $item) {
        $disgroups = explode(",", $item->disgroup ?? '');
        foreach ($disgroups as $group) {
            if ($item->disgroup === null) {
                // contest event not found (orphaned entry) - include athlete
                $group = $item->athlete_disgroup ?? '';
            } elseif ($group === '' && ($item->athlete_disgroup === null || $item->athlete_disgroup === '')) {
                // both empty - allow
            } elseif ($group != $item->athlete_disgroup) {
                continue;
            }
            $sport = $item->sport_event_name ?? $item->sport_name ?? '';
            $event_name =  "$sport, $group, $item->gender, $item->agname";

            if ($prev != $event_name) {
                $cellindex = 'A' . $ind;
                $spreadsheet->setActiveSheetIndex(0)->setCellValue($cellindex, $event_name);
                $spreadsheet->getActiveSheet()->getStyle($cellindex)->getFont()->setBold(true);
                $ind++;
            }
            $spreadsheet->getActiveSheet()->getStyle('A1:A' . $ind)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFDDDDDD');
            $spreadsheet->getActiveSheet()->getStyle('A1:E' . $ind)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $spreadsheet->setActiveSheetIndex(0)
                ->setCellValue('B' . $ind, $item->athlete_name)
                ->setCellValue('C' . $ind, $item->birth_date)
                ->setCellValue('D' . $ind, $item->ins_name)
                ->setCellValue('E' . $ind, $item->state_name);

            $prev = $event_name;
            $ind++;
        }
    }

    autosize_columns($spreadsheet->getActiveSheet());
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    vespa_send_contest_download_header($contest_id, 'nevezési lista', 'xlsx');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
}


// --------------------------------------------------
// beszámoló kérdőív letöltése
function vespa_download_answers($contest_id)
{
    global $wpdb;
    // get data for contest
    $record = $GLOBALS['VESPA_Contests']->load($contest_id);
    // A kérdés ordernum-ja szerint rendezünk, nem qa_id szerint: a szerkeszthető
    // beszámoló egy utólag megválaszolt, korábban kihagyott kérdéshez a sor
    // végén szúr be új sort, ezért a qa_id sorrend már nem egyezik a
    // kérdéssorrenddel. A LEFT JOIN miatt a már megszűnt kérdéshez tartozó
    // (question_id=0) történelmi és a teljesen üres szellemsoroknak nincs
    // q.ordernum-juk (NULL) -- a "(q.ordernum IS NULL)" rendezőkulcs ezeket
    // a végére teszi, nem az elejére, ahova a MySQL az ASC rendezésnél
    // alapból tenné a NULL-t.
    $beszamolo_kerdesek = $wpdb->get_results($wpdb->prepare("SELECT qa.* FROM vespa_questions_answered qa LEFT JOIN vespa_contests_questions q ON q.question_id = qa.question_id WHERE qa.contest_id=%d ORDER BY (q.ordernum IS NULL), q.ordernum ASC, qa.qa_id ASC", $contest_id));
    require VITAREX_VESPA_PLUGIN_DIR  . '/lib/vendor/autoload.php';
    $upload_dir   = wp_upload_dir();
    $path = $upload_dir['basedir'] . '/answers';
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }

    $filename = $path . '/' . $contest_id . '.pdf';

    $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];

    $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    $mpdf = new Mpdf\Mpdf([
        'mode' => 'utf8',
        'fontDir' => array_merge($fontDirs, [
            VITAREX_VESPA_PLUGIN_DIR . '/fonts',
        ]),
        'fontdata' => $fontData + [
            'myriadpro' => [
                'R'  => 'MyriadPro-Regular.ttf',
                'B'  => 'MyriadPro-Bold.ttf',
            ]
        ],
        'default_font' => 'myriadpro'
    ]);
    $stylesheet = file_get_contents(VITAREX_VESPA_PLUGIN_DIR . '/css/pdf-style.css');
    $mpdf->simpleTables = false;
    $mpdf->WriteHTML($stylesheet, Mpdf\HTMLParserMode::HEADER_CSS);
    $html = '<style>table, td, th {border: 1px solid black}</style>';
    $html .= "<h2>Beszámoló kérdések és válaszok ($record->contest_name)</h2>";
    $datumtol = date("Y.m.d", strtotime($record->start_at));
    $datumig = date("Y.m.d", strtotime($record->end_at));
    $html .= "<h4>Helyszín: $record->place_name, dátum: $datumtol - $datumig </h4>";
    $html .= '<table>';
    $html .= '<tr><th>Kérdés</th><th>Válasz</th><th>Megjegyzés</th>';
    foreach ($beszamolo_kerdesek as $beszamolo_kerdes) {
        $html .= "<tr><td>$beszamolo_kerdes->question</td><td>$beszamolo_kerdes->answer</td><td>$beszamolo_kerdes->qnote</td></tr>";
    }

    $html .= '</table>';
    $mpdf->WriteHTML($html);

    $mpdf->Output($filename, 'F');

    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/pdf');
    vespa_send_contest_download_header($contest_id, 'beszámoló', 'pdf');
    header('Content-Length: ' . filesize($filename));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Read and output the PDF file
    readfile($filename);
    exit;
}


function autosize_columns($sheet)
{
    if ($sheet != null)
        foreach ($sheet->getColumnIterator() as $column)
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
}
