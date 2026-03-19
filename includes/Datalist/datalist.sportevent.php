<?php
    
    class VESPA_SportEvents extends VESPA_Datalist {
        protected $source            = 'sport_events';
        protected $tablename         = 'vespa_sport_events';
        protected $id_field          = 'sport_event_id';
        protected $default_order_by  = 'sport_event_name';
        protected $default_order_dir = 'ASC';
        protected $columns           = array('sport_event_id','sport_event_name', 'sport_name');

        public function joins()
        {
            return ' JOIN vespa_sports ON (vespa_sports.sport_id=vespa_sport_events.sport_id) ';
        }

        public function checkDelete( $id ){
            return current_user_can( 'manage_options' ) || current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles );
        }

        public function save(){
            global $wpdb;

            $errors = array();
            if(empty($_POST['sport_event_name'])){
                $errors['sport_event_name'] = 'Kötelező mező';
            }        
            if(empty($_POST['sport_id'])){
                $errors['sport_id'] = 'Kötelező mező';
            }   

            if(!empty($errors)){
                wp_send_json_error( array('errors' => $errors) );
            }            

            // insert or update
            if( intval($_POST['sport_event_id']) == 0 ){
                $success = $wpdb->insert( $this->tablename, array(
                                'sport_event_name'      => sanitize_text_field($_POST['sport_event_name']),
                                'sport_id'              => intval($_POST['sport_id']),
                                'result_type'    => sanitize_text_field($_POST['result_type'])
                            ), array(
                                '%s',
                                '%d',
                                '%s'
                            ));
            }
            else {
                $success = $wpdb->update( $this->tablename, array(
                    'sport_event_name'      => sanitize_text_field($_POST['sport_event_name']),
                    'sport_id'              => intval($_POST['sport_id']),
                    'result_type'    => sanitize_text_field($_POST['result_type'])
                ), array(
                    'sport_event_id' => intval($_POST['sport_event_id'])
                ),
                array(
                    '%s',
                    '%d',
                    '%s'
                ));
            }

            $vars = array(
                "{=TEXT=}" => 'Sportesemény mentése sikeres volt.',
                "{=URL=}" => admin_url('admin.php?page=sportevents')
            );

            wp_send_json_success( array('modal' => vespa_load_template_with_vars( 'success-modal.php', $vars ), 'modalId' => 'succesModal' ) );    	   		
        }        

        public function getFilters(){
            global $wpdb;
            $filters = '1';

            if( isset($_REQUEST['search']) && isset($_REQUEST['search']['value']) && trim($_REQUEST['search']['value']) != '' ){
                $search = '%' . $wpdb->esc_like( sanitize_text_field($_REQUEST['search']['value']) ) . '%';
                $filters = $wpdb->prepare("sport_event_name LIKE %s", $search);
            }

            return $filters;
        }

        function addActionButtons( $item ){
            $btns = '';
            $btns .= '<a href="' . admin_url('admin.php?page=sportevents') . '&id=' . $item->{$this->id_field}  . '" class="btn btn-sm btn-default">';
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


    $GLOBALS['VESPA_SportEvents'] = new VESPA_SportEvents();