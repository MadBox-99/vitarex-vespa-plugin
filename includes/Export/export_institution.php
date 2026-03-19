<?php 
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\IOFactory;


    add_action('init', 'vespa_export_institution_init');
    function vespa_export_institution_init(){
        if( isset($_GET['export_institution']) ){
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
            
            $sql  = "SELECT * FROM vespa_disability_groups";
            $groups = $wpdb->get_results( $sql );

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $spreadsheet->setActiveSheetIndex(0)
                        ->setCellValue('A1', 'Intézményi adatok')
                        ->setCellValue('A2', 'Típus')
                        ->setCellValue('B2', 'OM azonosító/Nyilvántartási szám')
                        ->setCellValue('C2', 'Neve')
                        ->setCellValue('D2', 'Megye')
                        ->setCellValue('E2', 'Irányítószám')
                        ->setCellValue('F2', 'Település')
                        ->setCellValue('G2', 'Cím')
                        ->setCellValue('H2', 'Tankerület azonosítója(csak iskola)')
                        ->setCellValue('I2', 'Központi email cím')
                        ->setCellValue('J2', 'Központi telefonszám')
                        ->setCellValue('K2', 'Vezető neve')
                        ->setCellValue('L2', 'Vezető email címe')
                        ->setCellValue('M2', 'Vezető telefonszáma')
                        ->setCellValue('N2', 'Felelős neve')
                        ->setCellValue('O2', 'Felelős email címe')
                        ->setCellValue('P2', 'Felelős telefonszáma')
                        ->setCellValue('Q2', 'Testnevelő/edzők neve és elérhetősége')   
                        ->setCellValue('R2', 'Iskola diákjainak/sportolók szám')
                        ->setCellValue('S2', 'Tagja-e a Fovesznek(csak egyesület)')
                        ->setCellValueByColumnAndRow('20', '1', 'Fogyatékossági csoportok létszámmegoszlása'); // T

            $startCol = 20;
            $colIndex = $startCol;
            foreach($groups as $group){
                $spreadsheet->getActiveSheet()->setCellValueByColumnAndRow($colIndex, 2, $group->disability_group_name);
                $colIndex++;
            }

            $sheet->getCellByColumnAndRow(2, 1)->getParent()->getCurrentCoordinate();

            $spreadsheet->getActiveSheet()->mergeCells("A1:S1");
            $spreadsheet->getActiveSheet()->mergeCellsByColumnAndRow($startCol, 1, $startCol + count($groups) - 1, 1);

            $ind = 3;
            $sql = "SELECT * FROM vespa_institutions 
            JOIN vespa_states ON vespa_states.state_id = vespa_institutions.ins_state
            LEFT JOIN vespa_school_districts ON vespa_school_districts.school_district_id = vespa_institutions.school_district_id
            WHERE 1";
    
            $params = [];
            
            if (isset($_GET['ins_type']) && trim($_GET['ins_type']) != '') {
                $sql .= " AND institution_type = %s";
                $params[] = trim($_GET['ins_type']);
            }
            
            if (isset($_GET['state']) && is_numeric($_GET['state'])) {
                $sql .= " AND ins_state = %d";
                $params[] = (int) $_GET['state'];
            }
            
            if (isset($_GET['ins_city']) && trim($_GET['ins_city']) != '') {
                $sql .= " AND ins_city = %s";
                $params[] = trim($_GET['ins_city']);
            }
            
            if (isset($_GET['ins_name']) && trim($_GET['ins_name']) != '') {
                $sql .= " AND ins_name LIKE %s";
                $params[] = "%" . trim($_GET['ins_name']) . "%"; 
            }
            
            if (isset($_GET['ins_id']) && trim($_GET['ins_id']) != '') {
                $sql .= " AND id_number = %s";
                $params[] = trim($_GET['ins_id']);
            }
            
            if (isset($_GET['person_data']) && trim($_GET['person_data']) != '') {
                $sql .= " AND (vespaman_name = %s OR vespaman_email = %s OR vespaman_phone = %s)";
                $params[] = trim($_GET['person_data']);
                $params[] = trim($_GET['person_data']);
                $params[] = trim($_GET['person_data']);
            }
            

            if (!empty($params)) {
                $items = $wpdb->get_results($wpdb->prepare($sql, ...$params));
            } else {
                $items = $wpdb->get_results($sql);
            }
    

            $sql = "SELECT * FROM vespa_institution_disability_groups";
            $insDisabilitys = $wpdb->get_results( $sql );

            foreach( $items as $item ){
            
                $spreadsheet->setActiveSheetIndex(0)
                    ->setCellValue('A' . $ind, $item->institution_type)
                    ->setCellValue('B' . $ind, $item->id_number)
                    ->setCellValue('C' . $ind, $item->ins_name)
                    ->setCellValue('D' . $ind, $item->state_name)
                    ->setCellValue('E' . $ind, $item->ins_zipcode)
                    
                    ->setCellValue('F' . $ind, $item->ins_city)
                    ->setCellValue('G' . $ind, $item->ins_address)
                    ->setCellValue('H' . $ind, $item->institution_type == 'iskola' ? $item->school_district_name : '')
                    ->setCellValue('I' . $ind, $item->ins_email)
                    ->setCellValue('J' . $ind, $item->ins_phone)
                    
                    ->setCellValue('K' . $ind, $item->leader_name)
                    ->setCellValue('L' . $ind, $item->leader_email)
                    ->setCellValue('M' . $ind, $item->leader_phone)
                    ->setCellValue('N' . $ind, $item->vespaman_name)
                    ->setCellValue('O' . $ind, $item->vespaman_email)   
                    
                    ->setCellValue('P' . $ind, $item->vespaman_phone)
                    ->setCellValue('Q' . $ind, $item->trainers_data)
                    ->setCellValue('R' . $ind, $item->numberof_students)
                    ->setCellValue('S' . $ind, $item->institution_type == 'iskola' ? '' : ($item->member_of_fodesz == 0 ? 'nem' : 'igen'));

                    $colIndex = $startCol;
                    foreach($groups as $group){
                        $num = 0;
                        foreach($insDisabilitys as $insDis){
                            if($insDis->disability_group_id == $group->disability_group_id && $insDis->institution_id == $item->institution_id){
                                $num = $insDis->numberof;
                                break;
                            }
                        }
                        $spreadsheet->getActiveSheet()->setCellValueByColumnAndRow($colIndex, $ind, $num);
                        $colIndex++;
                    }

                //---
                $ind += 1;
            }

            autosize_columns($spreadsheet->getActiveSheet());
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="export_institutions.xlsx"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: max-age=1');
            
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');

            // -----------------
            exit;
        }

    }