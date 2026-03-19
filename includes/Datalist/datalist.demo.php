<?php
    
    class VESPA_Demo extends VESPA_Datalist {
        protected $source            = 'demo';
        protected $tablename         = 'vespa_contest_types';
        protected $id_field          = 'contest_type_id';
        protected $default_order_by  = 'contest_type_name';
        protected $default_order_dir = 'ASC';
        protected $columns           = array('contest_type_id','contest_type_name');

        public function save(){
            global $wpdb;

            $errors = array();
            if(empty($_POST['contest_type_name'])){
                $errors['contest_type_name'] = 'Kötelező mező';
            }        

            if(!empty($errors)){
                wp_send_json_error( array('errors' => $errors) );
            }            

            // insert or update
            if( intval($_POST['contest_type_id']) == 0 ){
                $success = $wpdb->insert( $this->tablename, array(
                                'contest_type_name'    => sanitize_text_field($_POST['contest_type_name'])
                            ), array(
                                '%s'
                            ));
            }
            else {
                $success = $wpdb->update( $this->tablename, array(
                    'contest_type_name'    => sanitize_text_field($_POST['contest_type_name'])
                ), array(
                    'contest_type_id' => intval($_POST['contest_type_id'])
                ),
                array(
                    '%s'
                ));
            }

            $vars = array(
                "{=TEXT=}" => 'Versenytípus mentése sikeres volt.',
                "{=URL=}" => admin_url('admin.php?page=demo')
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
                $filters = $wpdb->prepare("contest_type_name LIKE %s", '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['search']['value'])) . '%');
            }

            return $filters;
        }

        function addActionButtons( $item ){
            $btns = '';
            $btns .= '<a href="' . admin_url('admin.php?page=demo') . '&id=' . $item->{$this->id_field}  . '" class="btn btn-sm btn-default">';
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


    $GLOBALS['VESPA_Demo'] = new VESPA_Demo();