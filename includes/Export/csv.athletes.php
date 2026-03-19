<?php
add_action('init', 'download_csv');
function download_csv()
{
    if (isset($_GET['vespa_athletes_csv_sample']) && is_user_logged_in()) {

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
?>