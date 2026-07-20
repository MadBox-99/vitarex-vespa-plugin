<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

add_action('init', 'download_csv');
function download_csv()
{
    if (isset($_GET['vespa_athletes_csv_sample']) && current_user_can(VESPA_Roles::sportolok_letrehozasa_modositasa)) {

        $filename = "vespa_athletes.csv";
        $lines = array();
        $lines[] = 'Sportoló neve;Születési hely;Születési dátum;Anyja neve;Telefonszám;Email;Irányítószám;Település;Lakcím;Állampolgárság;Igazolványszám;Nem;Fogyatékosság típusa;Nyilvántartásba vétele;Megjegyzés;Aktív;';

        $tmp = array();        
        $tmp[] = '"Sportoló 1"';
        $tmp[] = '"Budapest"';
        $tmp[] = '"2000-05-01"';
        $tmp[] = '"Sportoló 1 anyja neve"';
        $tmp[] = '"+1235468213"';
        $tmp[] = '"valaki@gmail.com"';
        $tmp[] = '"2194"';
        $tmp[] = '"Tura"';
        $tmp[] = '"Verseny utca, 12."';
        $tmp[] = '"Magyar"';
        $tmp[] = '"935486HK"';
        $tmp[] = '"Férfi"';
        $tmp[] = '"Vak"';
        $tmp[] = '"2014-11-09"';
        $tmp[] = '"Kék a szeme"';
        $tmp[] = '"1"';
        $lines[] = implode(';', $tmp);
        $tmp = array();        
        $tmp[] = '"Sportoló 2"';
        $tmp[] = '"Budapest"';
        $tmp[] = '"2000-06-01"';
        $tmp[] = '"Sportoló 2 anyja neve"';
        $tmp[] = '"001235468213"';
        $tmp[] = '"sportolo2@gmail.com"';
        $tmp[] = '"5600"';
        $tmp[] = '"Békéscsaba"';
        $tmp[] = '"Báthory utca, 25."';
        $tmp[] = '"Magyar"';
        $tmp[] = '"684539KH"';
        $tmp[] = '"Nő"';
        $tmp[] = '"Néma"';
        $tmp[] = '"2013-02-25"';
        $tmp[] = '"Barna a szeme"';
        $tmp[] = '"1"';
        $lines[] = implode(';', $tmp);
        $tmp = array();        
        $tmp[] = '"Sportoló 3"';
        $tmp[] = '"Budapest"';
        $tmp[] = '"2000-07-01"';
        $tmp[] = '"Sportoló 3 anyja neve"';
        $tmp[] = '"001235468213"';
        $tmp[] = '"sportolo3@gmail.hu"';
        $tmp[] = '"94301"';
        $tmp[] = '"Párkány"';
        $tmp[] = '"Petőfi utca, 42."';
        $tmp[] = '"Szlovák"';
        $tmp[] = '"KH684539"';
        $tmp[] = '"Férfi"';
        $tmp[] = '"Süket"';
        $tmp[] = '"2015-07-21"';
        $tmp[] = '""';
        $tmp[] = '"0"';
        $lines[] = implode(';', $tmp);

        $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF));

        header('Content-Type: text/csv; charset=UTF-8');        
        header('Content-Disposition: attachment; filename="' . $filename . '";');
        header("Pragma: no-cache");
        header("Expires: 0");        
        echo $bom;
        echo implode("\n", $lines);        
        exit;
    }
}

add_action('init', 'vespa_athletes_xlsx_sample');
function vespa_athletes_xlsx_sample()
{
    if (!isset($_GET['vespa_athletes_xlsx_sample']) || !current_user_can(VESPA_Roles::sportolok_letrehozasa_modositasa)) {
        return;
    }

    require_once VITAREX_VESPA_PLUGIN_DIR . '/lib/vendor/autoload.php';

    $header = array('Sportoló neve', 'Születési hely', 'Születési dátum', 'Anyja neve', 'Telefonszám', 'Email', 'Irányítószám', 'Település', 'Lakcím', 'Állampolgárság', 'Igazolványszám', 'Nem', 'Fogyatékosság típusa', 'Nyilvántartásba vétele', 'Megjegyzés', 'Aktív');
    $sample = array('Sportoló 1', 'Budapest', '2000-05-01', 'Sportoló 1 anyja neve', '+3630123456', 'valaki@pelda.hu', '2194', 'Tura', 'Verseny utca, 12.', 'Magyar', '935486HK', 'Férfi', 'Vak', '2014-11-09', 'Kék a szeme', '1');

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray($header, null, 'A1');
    $sheet->fromArray($sample, null, 'A2');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="vespa_athletes.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>