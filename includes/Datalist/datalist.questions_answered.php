<?php
    
    class VESPA_Questions_Answered extends VESPA_Datalist {
        protected $source            = 'questions_answered';
        protected $tablename         = 'vespa_questions_answered';
        protected $id_field          = 'qa_id';
        protected $default_order_by  = 'contest_id';
        protected $default_order_dir = 'ASC';
        protected $columns           = array('qa_id','contest_id', 'question', 'answer', 'qnote');

        public function save(){
            global $wpdb;

            $errors = array();
            // for($i = 0; $i < intval($_POST['data_count']); $i++){
            //     if(empty($_POST['answer' . $i])){
            //         $errors['answer' . $i] = 'Kötelező mező';
            //     } 
            // }

            if(!empty($errors)){
                wp_send_json_error( array('errors' => $errors) );
            }            

            // insert or update
            if( intval($_POST['qa_id']) == 0 ){
                for($i = 0; $i < intval($_POST['data_count']); $i++){
                    $success = $wpdb->insert( $this->tablename, array(
                        'contest_id'    => intval($_POST['contest_id']),
                        'question'    => sanitize_text_field($_POST['question' . $i]),
                        'answer'    => sanitize_text_field($_POST['answer' . $i]),
                        'qnote'    => sanitize_text_field($_POST['qnote' . $i]),
                    ), array(
                        '%s'
                    ));
                }
            }
            // else {
            //     $success = $wpdb->update( $this->tablename, array(
            //         'series_name'    => $_POST['series_name']
            //     ), array(
            //         'series_id' => $_POST['series_id']
            //     ),
            //     array(
            //         '%s'
            //     ));                
            // }

            $vars = array(
                "{=TEXT=}" => 'A kérdések kitöltése sikeres volt.',
                "{=URL=}" => admin_url('admin.php?page=contests') . '&action=view&id='. intval($_POST['contest_id'])
            );

            wp_send_json_success( array('modal' => vespa_load_template_with_vars( 'success-modal.php', $vars ), 'modalId' => 'succesModal' ) );    	   		
        }        

        public function checkDelete( $id ){
            return current_user_can( 'manage_options' ) || current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles );
        }

        public function getFilters(){
            global $wpdb;
            $filters = '1';

            if( isset($_REQUEST['search']) && isset($_REQUEST['search']['value']) && trim($_REQUEST['search']['value']) != '' ){
                $filters = $wpdb->prepare("series_name LIKE %s", '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['search']['value'])) . '%');
            }

            return $filters;
        }

        function addActionButtons( $item ){
            $btns = '';
            $btns .= '<a href="' . admin_url('admin.php?page=series') . '&id=' . $item->{$this->id_field}  . '" class="btn btn-sm btn-default">';
            $btns .= '  <i class="fa fa-pencil" aria-hidden="true"></i>';
            $btns .= '</a>&nbsp;';

            if( $this->checkDelete(0) ){
                $btns .= '<button class="btn btn-sm btn-default color-red delete-entity" data-modalid="" data-id="' . $item->{$this->id_field} . '">';
                $btns .= '  <i class="fa fa-trash" aria-hidden="true"></i>';
                $btns .= '</button>';
            }

            return $btns;
        }

    }


    $GLOBALS['VESPA_Questions_Answered'] = new VESPA_Questions_Answered();