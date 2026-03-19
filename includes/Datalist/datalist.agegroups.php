<?php
    
    class VESPA_AgeGroups extends VESPA_Datalist {
        protected $source            = 'agegroups';
        protected $tablename         = 'vespa_agegroups';
        protected $id_field          = 'agegroup_id';
        protected $default_order_by  = 'agegroup_name';
        protected $default_order_dir = 'ASC';
        protected $columns           = array('agegroup_id','agegroup_name');

        public function save(){
            global $wpdb;

            $errors = array();
            if(empty($_POST['agegroup_name'])){
                $errors['agegroup_name'] = 'Kötelező mező';
            }        

            if(!empty($errors)){
                wp_send_json_error( array('errors' => $errors) );
            }            

            // insert or update
            if( intval($_POST['agegroup_id']) == 0 ){
                $success = $wpdb->insert( $this->tablename, array(
                                'agegroup_name'    => sanitize_text_field($_POST['agegroup_name'])
                            ), array(
                                '%s'
                            ));
            }
            else {
                $success = $wpdb->update( $this->tablename, array(
                    'agegroup_name'    => sanitize_text_field($_POST['agegroup_name'])
                ), array(
                    'agegroup_id' => intval($_POST['agegroup_id'])
                ),
                array(
                    '%s'
                ));
            }

            $vars = array(
                "{=TEXT=}" => 'Korcsoport mentése sikeres volt.',
                "{=URL=}" => admin_url('admin.php?page=agegroups')
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
                $filters = $wpdb->prepare("agegroup_name LIKE %s", '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['search']['value'])) . '%');
            }

            return $filters;
        }

        function addActionButtons( $item ){
            $btns = '';
            $btns .= '<a href="' . admin_url('admin.php?page=agegroups') . '&id=' . $item->{$this->id_field}  . '" class="btn btn-sm btn-default">';
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


    $GLOBALS['VESPA_AgeGroups'] = new VESPA_AgeGroups();