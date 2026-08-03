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

    $tabla = vespa_szabadidos_entry_table($contest_id);

    $fejlec = array('Név', 'Születési dátum', 'Nem', 'E-mail', 'Telefon', 'Versenyszám', 'Nevezés dátuma');
    foreach ($tabla['columns'] as $oszlop) {
        $fejlec[] = $oszlop['label'] . ($oszlop['archived'] ? ' (archivált)' : '');
    }
    // A hiányzó adat jelzése csak akkor kap oszlopot, ha van egyáltalán mező.
    if (!empty($tabla['columns'])) {
        $fejlec[] = 'Hiányzó adat';
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray($fejlec, null, 'A1');

    $sor = 2;
    foreach ($tabla['rows'] as $adat) {
        $n = $adat['nevezo'];
        $ertekek = array(
            $n->full_name,
            $n->birth_date,
            $n->gender,
            $n->email,
            $n->phone,
            trim(($n->sport_name ?: '') . ' ' . ($n->sport_event_name ?: '')),
            $n->entry_date,
        );
        foreach ($tabla['columns'] as $oszlop) {
            $ertekek[] = $adat['answers'][$oszlop['field_id']];
        }
        if (!empty($tabla['columns'])) {
            $ertekek[] = $adat['missing'] ? 'igen' : '';
        }

        $sheet->fromArray($ertekek, null, 'A' . $sor);
        $sor++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    vespa_send_contest_download_header($contest_id, 'szabadidős nevezők', 'xlsx');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
