<?php
    
    class VESPA_Sports extends VESPA_Datalist {
        protected $source            = 'sports';
        protected $tablename         = 'vespa_sports';
        protected $id_field          = 'sport_id';
        protected $default_order_by  = 'sport_name';
        protected $default_order_dir = 'ASC';
        protected $columns           = array('sport_id','sport_name');

        public function checkDelete( $id ){
            return current_user_can( 'manage_options' ) || current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles );
        }

        public function save(){
            global $wpdb;

            $errors = array();
            if(empty($_POST['sport_name'])){
                $errors['sport_name'] = 'Kötelező mező';
            }        

            if(!empty($errors)){
                wp_send_json_error( array('errors' => $errors) );
            }            

            // insert or update
            if( intval($_POST['sport_id']) == 0 ){
                $success = $wpdb->insert( $this->tablename, array(
                                'sport_name'    => sanitize_text_field($_POST['sport_name']),
                                'result_type'    => sanitize_text_field($_POST['result_type'])
                            ), array(
                                '%s',
                                '%s'
                            ));
            }
            else {
                $success = $wpdb->update( $this->tablename, array(
                    'sport_name'    => sanitize_text_field($_POST['sport_name']),
                    'result_type'    => sanitize_text_field($_POST['result_type'])
                ), array(
                    'sport_id' => intval($_POST['sport_id'])
                ),
                array(
                    '%s',
                    '%s'
                ));
            }

            $vars = array(
                "{=TEXT=}" => 'Sport mentése sikeres volt.',
                "{=URL=}" => admin_url('admin.php?page=sports')
            );

            wp_send_json_success( array('modal' => vespa_load_template_with_vars( 'success-modal.php', $vars ), 'modalId' => 'succesModal' ) );    	   		
        }        

        public function getFilters(){
            global $wpdb;
            $filters = '1';

            if( isset($_REQUEST['search']) && isset($_REQUEST['search']['value']) && trim($_REQUEST['search']['value']) != '' ){
                $search = '%' . $wpdb->esc_like( sanitize_text_field($_REQUEST['search']['value']) ) . '%';
                $filters = $wpdb->prepare("sport_name LIKE %s", $search);
            }

            return $filters;
        }

        function addActionButtons( $item ){
            $btns = '';
            $btns .= '<a href="' . admin_url('admin.php?page=sports') . '&id=' . $item->{$this->id_field}  . '" class="btn btn-sm btn-default">';
            $btns .= '  <i class="fa fa-pencil" aria-hidden="true"></i>';
            $btns .= '</a>&nbsp;';

            if( $this->checkDelete( $item->{$this->id_field} ) ){
                $btns .= '<button class="btn btn-sm btn-default color-red delete-entity" data-modalid="" data-id="' . $item->{$this->id_field} . '">';
                $btns .= '  <i class="fa fa-trash" aria-hidden="true"></i>';
                $btns .= '</button>';
            }

            return $btns;
        }

    }


    $GLOBALS['VESPA_Sports'] = new VESPA_Sports();