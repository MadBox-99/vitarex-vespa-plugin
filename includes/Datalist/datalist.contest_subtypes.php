<?php
    
    class VESPA_Contest_Subtypes extends VESPA_Datalist {
        protected $source            = 'contest_subtypes';
        protected $tablename         = 'vespa_contest_subtypes';
        protected $id_field          = 'contest_subtype_id';
        protected $default_order_by  = 'contest_subtype_name';
        protected $default_order_dir = 'ASC';
        protected $columns           = array('contest_subtype_id', 'contest_subtype_name');

        public function save(){
            global $wpdb;

            $errors = array();
            if(empty($_POST['contest_subtype_name'])){
                $errors['contest_subtype_name'] = 'Kötelező mező';
            }        

            if(!empty($errors)){
                wp_send_json_error( array('errors' => $errors) );
            }            

            // insert or update
            if( intval($_POST['contest_subtype_id']) == 0 ){
                $success = $wpdb->insert( $this->tablename, array(
                                'contest_subtype_name' => sanitize_text_field($_POST['contest_subtype_name'])
                            ), array(
                                '%s'
                            ));
            }
            else {
                $success = $wpdb->update( $this->tablename, array(
                    'contest_subtype_name' => sanitize_text_field($_POST['contest_subtype_name'])
                ), array(
                    'contest_subtype_id' => intval($_POST['contest_subtype_id'])
                ),
                array(
                    '%s'
                ));
            }

            $vars = array(
                "{=TEXT=}" => 'Lebonyolítás rendje típus mentése sikeres volt.',
                "{=URL=}" => admin_url('admin.php?page=contest_subtypes')
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
                $filters = $wpdb->prepare("contest_subtype_name LIKE %s", '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['search']['value'])) . '%');
            }

            return $filters;
        }

        function addActionButtons( $item ){
            $btns = '';
            $btns .= '<a href="' . admin_url('admin.php?page=contest_subtypes') . '&id=' . $item->{$this->id_field}  . '" class="btn btn-sm btn-default">';
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

    $GLOBALS['VESPA_Contest_Subtypes'] = new VESPA_Contest_Subtypes();