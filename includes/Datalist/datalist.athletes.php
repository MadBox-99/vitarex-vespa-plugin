<?php

class VESPA_Athletes extends VESPA_Datalist
{
    protected $source            = 'athletes';
    protected $tablename         = 'vespa_athletes';
    protected $id_field          = 'athlete_id';
    protected $default_order_by  = 'athlete_name';
    protected $default_order_dir = 'ASC';
    protected $columns           = array('athlete_id', 'athlete_name', 'ins_name', 'birth_place', 'birth_date', 'mothers_name');
    protected $soft_delete       = true;

    public function joins()
    {
        return ' JOIN vespa_institutions ON (vespa_institutions.institution_id=vespa_athletes.school_id) ';
    }

    public function save()
    {
        global $wpdb;


        $errors = array();
        if (empty($_POST['athlete_name'])) {
            $errors['athlete_name'] = 'Kötelező mező';
        }
        if (empty($_POST['school_id'])) {
            $errors['school_id'] = 'Kötelező mező';
        }
        if (empty($_POST['birth_place'])) {
            $errors['birth_place'] = 'Kötelező mező';
        }
        if (empty($_POST['birth_date'])) {
            $errors['birth_date'] = 'Kötelező mező';
        }
        if (empty($_POST['mothers_name'])) {
            $errors['mothers_name'] = 'Kötelező mező';
        }
        if (empty($_POST['school_id'])) {
            $errors['school_id'] = 'Kötelező mező';
        }
        if (empty($_POST['gender'])) {
            $errors['gender'] = 'Kötelező mező';
        }

        if (!empty($errors)) {
            wp_send_json_error(array('errors' => $errors));
        }

        $aktivertek = 1;
        if ($_POST["active"] == 0) {
            $aktivertek = 0;
        }

        // insert or update
        if (intval($_POST['athlete_id']) == 0) {
            $success = $wpdb->insert($this->tablename, array(
                'athlete_name'      => sanitize_text_field($_POST['athlete_name']),
                'birth_place'       => sanitize_text_field($_POST['birth_place']),
                'birth_date'        => sanitize_text_field($_POST['birth_date']),
                'school_id'         => intval($_POST['school_id']),
                'mothers_name'      => sanitize_text_field($_POST['mothers_name']),
                'phone'             => sanitize_text_field($_POST['phone']),
                'email'             => sanitize_email($_POST['email']),
                'home_zipcode'      => sanitize_text_field($_POST['home_zipcode']),
                'home_city'         => sanitize_text_field($_POST['home_city']),
                'home_address'      => sanitize_text_field($_POST['home_address']),
                'nationality'       => sanitize_text_field($_POST['nationality']),
                'personal_id'       => sanitize_text_field($_POST['personal_id']),
                'gender'            => sanitize_text_field($_POST['gender']),
                'disability_type'   => intval($_POST['disability_type']),
                'registered_at'     => sanitize_text_field($_POST['registered_at']),
                'note'              => sanitize_text_field($_POST['note']),
                'private_note'      => sanitize_text_field($_POST['private_note']),
                'active'            => $aktivertek,
                'modified_at'       => date("Y-m-d H:i:s"),
                'modified_by'       => get_current_user_id()
            ), array(
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
                '%d',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%d'
            ));
        } else {
            $success = $wpdb->update(
                $this->tablename,
                array(
                    'athlete_name'      => sanitize_text_field($_POST['athlete_name']),
                    'birth_place'       => sanitize_text_field($_POST['birth_place']),
                    'birth_date'        => sanitize_text_field($_POST['birth_date']),
                    'school_id'         => intval($_POST['school_id']),
                    'mothers_name'      => sanitize_text_field($_POST['mothers_name']),
                    'phone'             => sanitize_text_field($_POST['phone']),
                    'email'             => sanitize_email($_POST['email']),
                    'home_zipcode'      => sanitize_text_field($_POST['home_zipcode']),
                    'home_city'         => sanitize_text_field($_POST['home_city']),
                    'home_address'      => sanitize_text_field($_POST['home_address']),
                    'nationality'       => sanitize_text_field($_POST['nationality']),
                    'personal_id'       => sanitize_text_field($_POST['personal_id']),
                    'gender'            => sanitize_text_field($_POST['gender']),
                    'disability_type'   => intval($_POST['disability_type']),
                    'registered_at'     => sanitize_text_field($_POST['registered_at']),
                    'note'              => sanitize_text_field($_POST['note']),
                    'private_note'      => sanitize_text_field($_POST['private_note']),
                    'active'            => $aktivertek,
                    'modified_at'       => date("Y-m-d H:i:s"),
                    'modified_by'       => get_current_user_id()
                ),
                array(
                    'athlete_id' => intval($_POST['athlete_id'])
                ),
                array(
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
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%d'
                )
            );
        }

        $vars = array(
            "{=TEXT=}" => 'Sportoló mentése sikeres volt.',
            "{=URL=}" => admin_url('admin.php?page=athletes')
        );

        wp_send_json_success(array('modal' => vespa_load_template_with_vars('success-modal.php', $vars), 'modalId' => 'succesModal'));
    }

    public function checkDelete( $id ){
        // admin / versenykezelő: változatlanul törölhet
        if ( current_user_can( 'manage_options' ) || current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles ) ) {
            return true;
        }

        // testnevelő / iskolaigazgató: csak a saját iskolája diákját
        $is_school_user = VESPA_Roles::getInstance()->current_user_has_role( VESPA_Roles::TESTNEVELO )
            || VESPA_Roles::getInstance()->current_user_has_role( VESPA_Roles::ISKOLAIGAZGATO );

        if ( ! $is_school_user ) {
            return false;
        }

        // $id == 0: csak a gomb megjelenítési próbája (addActionButtons) –
        // a valódi tulajdonlás a tényleges törléskor ($id > 0) dől el.
        if ( intval( $id ) === 0 ) {
            return true;
        }

        $athlete = $this->load( $id );

        return $athlete && intval( $athlete->school_id ) === intval( vespa_get_my_school_id() );
    }

    public function getFilters()
    {
        global $wpdb;
        $filters = 'vespa_athletes.is_deleted=0';

        if (isset($_REQUEST['search']) && isset($_REQUEST['search']['value']) && trim($_REQUEST['search']['value']) != '') {
            $search = '%' . $wpdb->esc_like( sanitize_text_field($_REQUEST['search']['value']) ) . '%';
            $filters .= $wpdb->prepare(" AND (athlete_name LIKE %s OR mothers_name LIKE %s OR phone LIKE %s OR personal_id LIKE %s)", $search, $search, $search, $search);
        }

        // extra filters
        // birt_place, birth_date
        if ($_REQUEST['birth_place'] != '') {
            $filters .= $wpdb->prepare(" AND birth_place LIKE %s", '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['birth_place'])) . '%');
        }

        if ($_REQUEST['birth_date'] != '') {
            $filters .= $wpdb->prepare(" AND birth_date LIKE %s", '%' . $wpdb->esc_like(str_replace('.', '-', sanitize_text_field($_REQUEST['birth_date']))) . '%');
        }

        if ($_REQUEST['school_id'] != '0') {
            $filters .= $wpdb->prepare(" AND school_id = %d", intval($_REQUEST['school_id']));
        }


        $filters = vespa_get_contest_athlete_filter($filters);

        return $filters;
    }

    function addActionButtons($item)
    {
        $btns = '';
        $btns .= '<a href="' . admin_url('admin.php?page=athletes') . '&id=' . $item->{$this->id_field}  . '" class="btn btn-sm btn-default">';
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


$GLOBALS['VESPA_Athletes'] = new VESPA_Athletes();
