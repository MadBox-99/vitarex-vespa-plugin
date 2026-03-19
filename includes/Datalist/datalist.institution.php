<?php
    
    class VESPA_Institution extends VESPA_Datalist {
        protected $source            = 'institution';
        protected $tablename         = 'vespa_institutions';
        protected $id_field          = 'institution_id';
        protected $default_order_by  = 'ins_name';
        protected $default_order_dir = 'ASC';
        protected $columns           = array('institution_id','ins_name','institution_type','state_name','ins_city');

        public function joins()
        {
            return ' JOIN vespa_states ON (vespa_states.state_id=vespa_institutions.ins_state) ';
        }

        public function formatColumn( $colname, $colvalue, $item ){
            if($colname == 'institution_type'){
                return $colvalue == 'iskola' ? 'Iskola' : 'Egyesület';
            }
            return $colvalue;
        }

        public function save(){
            global $wpdb;

            $reqProps = [
                'institution_type', 
                'id_number', 
                'ins_name', 
                'ins_zipcode', 
                'ins_state',
                'ins_city',
                'ins_address',
                'ins_email',
                'ins_phone',
                'leader_name',
                'leader_email',
                'leader_phone',
                'vespaman_name',
                'vespaman_email',
                'vespaman_phone',
                'trainers_data',
                'numberof_students'
            ];
            $errors = array();
            foreach($reqProps as $reqProp){
                if(empty($_POST[$reqProp])){
                    $errors[$reqProp] = 'Kötelező mező';
                }  
            }
            if(!empty($_POST['institution_type'])){
                if($_POST['institution_type'] == 'iskola'){
                    if(empty($_POST['school_district_id'])){
                        $errors['school_district_id'] = 'Kötelező mező';
                    }  
                }
            }
            if(!empty($errors)){
                wp_send_json_error( array('errors' => $errors) );
            }      

            $emailProps = [
                'ins_email',
                'leader_email',
                'vespaman_email',
            ];
            foreach($emailProps as $prop){
                if(!empty($_POST[$prop]) && !filter_var($_POST[$prop], FILTER_VALIDATE_EMAIL)){
                    $errors[$prop] = 'Érvénytelen email cím formátum';
                }  
            }
            if(!empty($errors)){
                wp_send_json_error( array('errors' => $errors) );
            }           

            // insert or update
            if( intval($_POST['institution_id']) == 0 ){
                $success = $wpdb->insert( $this->tablename, array(
                                'ins_name'          => sanitize_text_field($_POST['ins_name']),
                                'institution_type'  => sanitize_text_field($_POST['institution_type']),
                                'id_number'         => sanitize_text_field($_POST['id_number']),
                                'ins_zipcode'       => sanitize_text_field($_POST['ins_zipcode']),
                                'ins_state'         => intval($_POST['ins_state']),
                                'ins_city'          => sanitize_text_field($_POST['ins_city']),
                                'ins_address'       => sanitize_text_field($_POST['ins_address']),
                                'ins_email'         => sanitize_email($_POST['ins_email']),
                                'ins_phone'         => sanitize_text_field($_POST['ins_phone']),
                                'leader_name'       => sanitize_text_field($_POST['leader_name']),
                                'leader_email'      => sanitize_email($_POST['leader_email']),
                                'leader_phone'      => sanitize_text_field($_POST['leader_phone']),
                                'vespaman_name'     => sanitize_text_field($_POST['vespaman_name']),
                                'vespaman_email'    => sanitize_email($_POST['vespaman_email']),
                                'vespaman_phone'    => sanitize_text_field($_POST['vespaman_phone']),
                                'trainers_data'     => sanitize_text_field($_POST['trainers_data']),
                                'school_district_id'=> intval($_POST['school_district_id']),
                                'member_of_fodesz'  => isset($_POST['member_of_fodesz']),
                                'numberof_students'  => intval($_POST['numberof_students']),
                            ), array(
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%d',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%s',
                                '%d',
                                '%d',
                                '%d',
                            ));
            }
            else {
                $success = $wpdb->update( $this->tablename, array(
                    'ins_name'          => sanitize_text_field($_POST['ins_name']),
                    'institution_type'  => sanitize_text_field($_POST['institution_type']),
                    'id_number'         => sanitize_text_field($_POST['id_number']),
                    'ins_zipcode'       => sanitize_text_field($_POST['ins_zipcode']),
                    'ins_state'         => intval($_POST['ins_state']),
                    'ins_city'          => sanitize_text_field($_POST['ins_city']),
                    'ins_address'       => sanitize_text_field($_POST['ins_address']),
                    'ins_email'         => sanitize_email($_POST['ins_email']),
                    'ins_phone'         => sanitize_text_field($_POST['ins_phone']),
                    'leader_name'       => sanitize_text_field($_POST['leader_name']),
                    'leader_email'      => sanitize_email($_POST['leader_email']),
                    'leader_phone'      => sanitize_text_field($_POST['leader_phone']),
                    'vespaman_name'     => sanitize_text_field($_POST['vespaman_name']),
                    'vespaman_email'    => sanitize_email($_POST['vespaman_email']),
                    'vespaman_phone'    => sanitize_text_field($_POST['vespaman_phone']),
                    'trainers_data'     => sanitize_text_field($_POST['trainers_data']),
                    'school_district_id'=> intval($_POST['school_district_id']),
                    'member_of_fodesz'  => isset($_POST['member_of_fodesz']),
                    'numberof_students'  => intval($_POST['numberof_students']),
                ), array(
                    'institution_id' => intval($_POST['institution_id'])
                ),
                array(
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%d',
                    '%d',
                ));
            }

            $insId = intval($_POST['institution_id']) == 0 ? $wpdb->insert_id : intval($_POST['institution_id']);
            $sql = 'SELECT * FROM vespa_disability_groups';
            $disabilityGroups = $wpdb->get_results($sql);

            foreach($disabilityGroups as $dGroup){
                if(intval($_POST['group_'.$dGroup->disability_group_id.'_id']) == 0){
                    $wpdb->insert( 'vespa_institution_disability_groups', array(
                        'disability_group_id'   => intval($dGroup->disability_group_id),
                        'institution_id'        => intval($insId),
                        'numberof'              => intval($_POST['group_'.$dGroup->disability_group_id])
                    ), array(
                        '%d',
                        '%d',
                        '%d'
                    ));
                }
                else{
                    $wpdb->update( 'vespa_institution_disability_groups', array(
                        'disability_group_id'   => intval($dGroup->disability_group_id),
                        'institution_id'        => intval($insId),
                        'numberof'              => intval($_POST['group_'.$dGroup->disability_group_id])
                    ),
                    array(
                        'vidg_id' => intval($_POST['group_'.$dGroup->disability_group_id.'_id'])
                    ),
                    array(
                        '%d',
                        '%d',
                        '%d'
                    ));
                }
            }

            $vars = array(
                "{=TEXT=}" => 'Intézmény mentése sikeres volt.',
                "{=URL=}" => admin_url('admin.php?page=institution')
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
                $filters .= $wpdb->prepare(" AND ins_name LIKE %s", '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['search']['value'])) . '%');
            }

            if( isset($_REQUEST['ins_type']) && trim($_REQUEST['ins_type']) != '' ){
                $filters .= $wpdb->prepare(" AND institution_type = %s", sanitize_text_field(trim($_REQUEST['ins_type'])));
            }

            if( isset($_REQUEST['state']) && is_numeric($_REQUEST['state']) ){
                $filters .= $wpdb->prepare(" AND ins_state = %d", intval($_REQUEST['state']));
            }

            if( isset($_REQUEST['ins_city']) && trim($_REQUEST['ins_city']) != '' ){
                $filters .= $wpdb->prepare(" AND ins_city = %s", sanitize_text_field(trim($_REQUEST['ins_city'])));
            }

            if( isset($_REQUEST['ins_name']) && trim($_REQUEST['ins_name']) != '' ){
                $filters .= $wpdb->prepare(" AND ins_name LIKE %s", '%' . $wpdb->esc_like(sanitize_text_field(trim($_REQUEST['ins_name']))) . '%');
            }

            if( isset($_REQUEST['id_number']) && trim($_REQUEST['id_number']) != '' ){
                $filters .= $wpdb->prepare(" AND id_number = %s", sanitize_text_field(trim($_REQUEST['id_number'])));
            }

            if( isset($_REQUEST['person_data']) && trim($_REQUEST['person_data']) != '' ){
                $pd = sanitize_text_field(trim($_REQUEST['person_data']));
                $filters .= $wpdb->prepare(" AND (vespaman_name = %s OR vespaman_email = %s OR vespaman_phone = %s)", $pd, $pd, $pd);
            }

            return $filters;
        }

        function addActionButtons( $item ){
            $btns = '';
            $btns .= '<a href="' . admin_url('admin.php?page=institution') . '&id=' . $item->{$this->id_field}  . '" class="btn btn-sm btn-default">';
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

    $GLOBALS['VESPA_Institution'] = new VESPA_Institution();