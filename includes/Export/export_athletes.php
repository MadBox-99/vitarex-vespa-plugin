<?php 
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\IOFactory;


    add_action('init', 'vespa_export_athletes_init');
    function vespa_export_athletes_init(){
        if( isset($_GET['export_athletes']) ){
            global $wpdb;

            set_time_limit(0);
            ini_set('memory_limit', '512M');


            // check rights
            if( 
                false
                //vespa_user_has_role('') || vespa_user_has_role('') || vespa_user_has_role('')       
            ){
                echo 'Nincs megfelelő jogosultságod a művelethez.';
                exit;
            }

            require VITAREX_VESPA_PLUGIN_DIR  . '/lib/vendor/autoload.php';
            

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $spreadsheet->setActiveSheetIndex(0)
                        ->setCellValue('A1', 'Név')
                        ->setCellValue('A2', 'Intézmény')
                        ->setCellValue('B2', 'Születési hely')
                        ->setCellValue('C2', 'Születési idő')
                        ->setCellValue('D2', 'Anyja neve')
                        ->setCellValue('E2', 'Telefonszám')
                        ->setCellValue('F2', 'Email')
                        ->setCellValue('G2', 'Irányítószám')
                        ->setCellValue('H2', 'Település')
                        ->setCellValue('I2', 'Utca, házszám')
                        ->setCellValue('J2', 'Állampolgárság')
                        ->setCellValue('K2', 'Személyiszám/Útlevélszám')
                        ->setCellValue('L2', 'Neme')
                        ->setCellValue('M2', 'Fogyatékosság típusa')
                        ->setCellValue('N2', 'Megjegyzés')
                        ->setCellValue('O2', 'Aktív?');
                        

            $ind = 3;
            $sql = "SELECT vespa_athletes.*, vespa_institutions.ins_name, vespa_disability_groups.disability_group_name 
            FROM vespa_athletes 
            JOIN vespa_institutions ON vespa_institutions.institution_id = vespa_athletes.school_id 
            JOIN vespa_disability_groups ON vespa_disability_groups.disability_group_id = vespa_athletes.disability_type
            WHERE vespa_athletes.is_deleted=0";
    
            $params = [];
            
            if (isset($_GET['birth_place']) && trim($_GET['birth_place']) != '') {
                $sql .= " AND birth_place = %s";
                $params[] = trim($_GET['birth_place']);
            }
            
            if (isset($_GET['birth_date']) && is_numeric($_GET['birth_date'])) {
                $sql .= " AND birth_date = %d";
                $params[] = (int) $_GET['birth_date'];
            }
            
            if (isset($_GET['school_id']) && trim($_GET['school_id']) != '0') {
                $sql .= " AND school_id = %s";
                $params[] = trim($_GET['school_id']);
            }

            if (!empty($params)) {
                $items = $wpdb->get_results($wpdb->prepare($sql, ...$params));
            } else {
                $items = $wpdb->get_results($sql);
            }
    


            foreach( $items as $item ){
            
                $spreadsheet->setActiveSheetIndex(0)
                    ->setCellValue('A' . $ind, $item->athlete_name)
                    ->setCellValue('B' . $ind, $item->ins_name)
                    ->setCellValue('C' . $ind, $item->birth_place)
                    ->setCellValue('D' . $ind, $item->birth_date)
                    ->setCellValue('E' . $ind, $item->mothers_name)
                    
                    ->setCellValue('F' . $ind, $item->phone)
                    ->setCellValue('G' . $ind, $item->email)
                    ->setCellValue('H' . $ind, $item->home_zipcode)
                    ->setCellValue('I' . $ind, $item->home_city)
                    ->setCellValue('J' . $ind, $item->home_address)
                    
                    ->setCellValue('K' . $ind, $item->nationality)
                    ->setCellValue('L' . $ind, $item->personal_id)
                    ->setCellValue('M' . $ind, $item->gender)
                    ->setCellValue('N' . $ind, $item->disability_group_name)
                    ->setCellValue('O' . $ind, $item->note)   
                    
                    ->setCellValue('P' . $ind, ($item->active == 1 ? 'igen' : 'nem' ) );

                $ind += 1;
            }
            autosize_columns($spreadsheet->getActiveSheet());
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="export_athletes.xlsx"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: max-age=1');
            
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');

            // -----------------
            exit;
        }

    }