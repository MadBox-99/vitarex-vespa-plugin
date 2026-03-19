<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

add_action('wp_ajax_vespa_export_contest', 'vespa_export_contest_init');
function vespa_export_contest_init(){
    wp_die('Funkció nem elérhető.');
    global $wpdb;
if (!isset($_POST['versenyekk'])) {
    wp_die('Nincsenek exportálható adatok.');
}
require VITAREX_VESPA_PLUGIN_DIR  . '/lib/vendor/autoload.php';
$versenyek = json_decode(stripslashes($_POST['versenyekk']), true);
$disabilityList = $wpdb->get_results("SELECT * FROM vespa_disability_groups");

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Adatok");
$sheet->setCellValue('A1', 'Azon.');
$sheet->setCellValue('B1', 'Versenynév');
$sheet->setCellValue('C1', 'Típus');
$sheet->setCellValue('D1', 'Kezdete');
$sheet->setCellValue('E1', 'Vége');
$sheet->setCellValue('F1', 'Helyszín');
$sheet->setCellValue('G1', 'Cím');
$sheet->setCellValue('H1', 'Fogyatékossági csoport');
$sheet->setCellValue('I1', 'Résztvevők száma');

$index = 2;
foreach ($versenyek as $verseny) {
    $sheet->setCellValue('A' . $index, $verseny['contest_id'] ?? 'N/A');
    $sheet->setCellValue('B' . $index, stripslashes($verseny['contest_name'] ?? 'N/A'));
    $sheet->setCellValue('C' . $index, $verseny['contest_type_name'] ?? 'N/A');
    $sheet->setCellValue('D' . $index, $verseny['start_at'] ?? 'N/A');
    $sheet->setCellValue('E' . $index, $verseny['end_at'] ?? 'N/A');
    $sheet->setCellValue('F' . $index, $verseny['place_name'] ?? 'N/A');
    $sheet->setCellValue('G' . $index, $verseny['place_address'] ?? 'N/A');

    $contest_id = $verseny['contest_id'];

    $eventResults = $wpdb->get_results($wpdb->prepare("SELECT disability_groups FROM vespa_constest_events WHERE contest_id =%d",$contest_id));
    
    $groups = [];
    if ($eventResults) {
        foreach ($eventResults as $row) {
            $ids = explode(',', $row->disability_groups);
            $groups = array_merge($groups, $ids);
        }
        $groups = array_unique(array_filter($groups));
    }

    $groupNames = [];
    foreach ($groups as $groupId) {
        foreach ($disabilityList as $dgroup) {
            if ($dgroup->disability_group_id == $groupId) {
                $groupNames[] = $dgroup->disability_group_name;
                break;
            }
        }
    }
    $disabilityDisplay = count($groupNames) > 0 ? implode(", ", $groupNames) : 'N/A';
    $sheet->setCellValue('H' . $index, $disabilityDisplay);

 
    
    $sheet->setCellValue('I' . $index, $verseny['resztvevok_szama'] ?? '0');
    $index++;
}

foreach (range('A', 'I') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

autosize_columns($spreadsheet->getActiveSheet());

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="exported_contests.xlsx"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save('php://output');
exit;
}
