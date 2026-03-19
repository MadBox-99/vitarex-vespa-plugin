<?php

class VESPA_Contests extends VESPA_Datalist
{
    protected $source            = 'contests';
    protected $tablename         = 'vespa_contests';
    protected $id_field          = 'contest_id';
    protected $default_order_by  = 'contest_name';
    protected $default_order_dir = 'ASC';
    protected $columns           = array(
        'contest_id',
        'contest_name',
        'listing_id',
        'contest_type_name',
        'start_at',
        'end_at',
        'place_name',
        'place_address',
    );

    public function save()
    {
        global $wpdb;

        $errors = array();
        $req_fields = array(
            'contest_name', 'contest_subtypes', 'contest_types', 'contest_series', 'organiser',
            'place_name', 'place_address', 'start_at', 'end_at',
            'school_entry_start_at', 'school_entry_end_at'
        );

        if ($_POST['contest_types'] == 3) { // megyei
            if (empty($_POST['state_id'])) {
                $errors['state_id'] = 'Kötelező mező';
            }
        }

        foreach ($req_fields as $fieldname) {
            if (empty($_POST[$fieldname])) {
                $errors[$fieldname] = 'Kötelező mező';
            }
        }

        if (!empty($_POST['contest_subtypes']) && !empty($_POST['contest_types']) && !empty($_POST['contest_series']) && 
            is_numeric($_POST['contest_subtypes']) && is_numeric($_POST['contest_types']) && is_numeric($_POST['contest_series'])) {
            if( ($_POST['contest_types'] == 4 && ($_POST['contest_subtypes'] != 3 || $_POST['contest_series'] != 1)) || 
                ($_POST['contest_subtypes'] == 3 && ($_POST['contest_types'] != 4 || $_POST['contest_series'] != 1)) ||
                ($_POST['contest_series'] == 1 && ($_POST['contest_types'] != 4 || $_POST['contest_subtypes'] != 3))
            ){
                $errors['contest_types'] = "Szabadidő versenytípusnak a lebonyolítása is csak szabadidősport lehet!";
            }
        }

        if (!empty($_POST['organiser']) && strlen($_POST['organiser']) > 100) {
            $errors['organiser'] = 'Maximum hossz 100 karakter';
        }
        if (!empty($_POST['place_name']) && strlen($_POST['place_name']) > 200) {
            $errors['place_name'] = 'Maximum hossz 200 karakter';
        }
        if (!empty($_POST['place_address']) && strlen($_POST['place_address']) > 200) {
            $errors['place_address'] = 'Maximum hossz 200 karakter';
        }
        if (!empty($_POST['contest_name']) && strlen($_POST['contest_name']) > 255) {
            $errors['contest_name'] = 'Maximum hossz 255 karakter';
        }

        if (str_contains($_POST['contest_name'], "'")) {
            $errors['contest_name'] = 'A verseny neve nem tartalmazhat \' karaktert';
        }

        if (isset($_POST['start_at']) && isset($_POST['end_at'])) {
            $curtimestamp1 = strtotime($_POST['start_at']);
            $curtimestamp2 = strtotime($_POST['end_at']);
            if ($curtimestamp2 < $curtimestamp1)
                $errors['end_at'] = 'A kezdőidőpont nem lehet nagyobb mint a záró időpont';
        }

        if (isset($_POST['school_entry_start_at']) && isset($_POST['school_entry_end_at'])) {
            $curtimestamp1 = strtotime($_POST['school_entry_start_at']);
            $curtimestamp2 = strtotime($_POST['school_entry_end_at']);
            if ($curtimestamp2 < $curtimestamp1)
                $errors['school_entry_end_at'] = 'A kezdőidőpont nem lehet nagyobb mint a záró időpont';
        }

        for ($i = 1; $i <= 6; $i++) {
            if (!empty($_POST['ag' . $i . '_from']) && !empty($_POST['ag' . $i . '_to'])) {
                if (is_numeric($_POST['ag' . $i . '_from']) && is_numeric($_POST['ag' . $i . '_to'])) {
                    if ($_POST['ag' . $i . '_from'] > $_POST['ag' . $i . '_to']) {
                        $errors['ag' . $i . '_to'] = 'A kezdődátum nem lehet nagyobb mint a záró dátum';
                    }
                } else {
                    if (!is_numeric($_POST['ag' . $i . '_from'])) {
                        $errors['ag' . $i . '_from'] = 'A dátum értéke csak szám lehet';
                    }
                    if (!is_numeric($_POST['ag' . $i . '_to'])) {
                        $errors['ag' . $i . '_to'] = 'A dátum értéke csak szám lehet';
                    }
                }
            }
        }

        if (isset($_POST['ppl_num_min']) && is_numeric($_POST['ppl_num_min']) && isset($_POST['ppl_num_max']) && is_numeric($_POST['ppl_num_max'])) {
            if ($_POST['ppl_num_min'] > $_POST['ppl_num_max'])
                $errors['ppl_num_max'] = 'A minimum érték nem lehet nagyobb, mint a megengedett maximum értéke';
        }

        if ($_POST['contest_subtypes'] == 1) { // továbbjutásos
            if (empty($_POST['qualifier_num_max'])) {
                $errors['qualifier_num_max'] = 'Kötelező mező';
            }
        }

        if (!empty($errors)) {
            wp_send_json_error(array('errors' => $errors));
        }

        $contest_id = intval($_POST['contest_id']);

        // insert or update
        if (intval($_POST['contest_id']) == 0) {
            //$wpdb->show_errors();

            $success = $wpdb->insert($this->tablename, array(
                'creator_user_id'           => get_current_user_id(),
                'contest_name'              => sanitize_text_field($_POST['contest_name']),
                'contest_subtypes'          => intval($_POST['contest_subtypes']),
                'contest_type'              => intval($_POST['contest_types']),
                'contest_series'            => intval($_POST['contest_series']),
                'organiser'                 => sanitize_text_field($_POST['organiser']),
                'state_id'                  => intval($_POST['state_id']),
                'place_name'                => sanitize_text_field($_POST['place_name']),
                'place_address'             => sanitize_text_field($_POST['place_address']),
                'start_at'                  => sanitize_text_field($_POST['start_at']),
                'end_at'                    => sanitize_text_field($_POST['end_at']),
                'school_entry_start_at'     => sanitize_text_field($_POST['school_entry_start_at']),
                'school_entry_end_at'       => sanitize_text_field($_POST['school_entry_end_at']),
                'listing_id'                => 0,
                'logo_id'                   => 0,
                'accompanying_entry_end_at' => sanitize_text_field($_POST['accompanying_entry_end_at']),
                'ppl_num_min'               => intval($_POST['ppl_num_min']),
                'ppl_num_max'               => intval($_POST['ppl_num_max']),
                'ppl_num_extra_max'         => intval($_POST['ppl_num_extra_max']),
                'listing_text'              => wp_kses_post($_POST['listing_text']),
                'qualifier_num_max'         => intval($_POST['qualifier_num_max'])
            ), array(
                '%s'
            ));


            //$wpdb->print_error();


            // save age groups
            if ($success) {
                $contest_id = $wpdb->insert_id;

                for ($i = 1; $i <= 6; $i++) {
                    if (!empty($_POST['ag' . $i . '_from']) && !empty($_POST['ag' . $i . '_to'])) {
                        $wpdb->insert('vespa_contest_agegroups', array(
                            'contest_id'  => $contest_id,
                            'agegroup_id' => $i,
                            'date_from'   => intval($_POST['ag' . $i . '_from']) . '-01-01',
                            'date_to'     => intval($_POST['ag' . $i . '_to']) . '-12-31'
                        ), array('%s'));
                    }
                }
            }
        } else {

            $success = $wpdb->update(
                $this->tablename,
                array(
                    //'creator_user_id'           => get_current_user_id(),
                    'contest_name'              => sanitize_text_field($_POST['contest_name']),
                    'contest_subtypes'          => intval($_POST['contest_subtypes']),
                    'contest_type'              => intval($_POST['contest_types']),
                    'contest_series'            => intval($_POST['contest_series']),
                    'organiser'                 => sanitize_text_field($_POST['organiser']),
                    'state_id'                  => intval($_POST['state_id']),
                    'place_name'                => sanitize_text_field($_POST['place_name']),
                    'place_address'             => sanitize_text_field($_POST['place_address']),
                    'start_at'                  => sanitize_text_field($_POST['start_at']),
                    'end_at'                    => sanitize_text_field($_POST['end_at']),
                    'school_entry_start_at'     => sanitize_text_field($_POST['school_entry_start_at']),
                    'school_entry_end_at'       => sanitize_text_field($_POST['school_entry_end_at']),
                    'ppl_num_min'               => intval($_POST['ppl_num_min']),
                    'ppl_num_max'               => intval($_POST['ppl_num_max']),
                    'ppl_num_extra_max'         => intval($_POST['ppl_num_extra_max']),
                    'listing_text'              => wp_kses_post($_POST['listing_text']),
                    // 'custom_enter_text'         => $_POST['custom_enter_text'],
                    'qualifier_num_max'         => intval($_POST['qualifier_num_max'])
                ),
                array(
                    'contest_id' => intval($_POST['contest_id'])
                ),
                array(
                    '%s'
                )
            );

            //adatok lekérdezése
            $sql = "SELECT * FROM vespa_contest_agegroups WHERE contest_id= %d";

            $rows = $wpdb->get_results($wpdb->prepare($sql,$contest_id));
            

            //delete vagy update kulcs és felküldött értékek alapján
            for ($i = 1; $i <= 6; $i++) {
                $id = null;

                foreach ($rows as $row) {
                    if ($row->agegroup_id == $i) {
                        $id = $row->id;
                        break;
                    }
                }

                //ha egy konkrét csoportot üresen küldünk fel, és amúgy létezik (van id), akkor törölni kell!
                if (empty($_POST['ag' . $i . '_from']) && empty($_POST['ag' . $i . '_to']) && $id != null) {
                    $success = $wpdb->delete(
                        "vespa_contest_agegroups",
                        array("id" => $id),
                        array('%d')
                    );
                } else {
                    if (!empty($_POST['ag' . $i . '_from']) && !empty($_POST['ag' . $i . '_to'])) {
                        $wpdb->replace(
                            'vespa_contest_agegroups',
                            array(
                                'date_from'   => intval($_POST['ag' . $i . '_from']) . '-01-01',
                                'date_to'     => intval($_POST['ag' . $i . '_to']) . '-12-31',
                                'contest_id'  => intval($_POST['contest_id']),
                                'agegroup_id' => $i,
                                'id' => $id
                            )
                        );
                    }
                }
            }
        }

        // if it was edit we must return to datapage
        // 
        // $contest_id
        $vars = array(
            "{=TEXT=}" => 'Verseny mentése sikeres volt.',
            "{=URL=}" => admin_url('admin.php?page=contests&action=view&id=') . $contest_id . '#versenyszamok'
        );

        wp_send_json_success(array('modal' => vespa_load_template_with_vars('success-modal.php', $vars), 'modalId' => 'succesModal'));
    }

    public function joins()
    {
        //return ' LEFT JOIN vespa_files ON (vespa_files.file_id=vespa_contests.listing_id) ';
        return ' LEFT JOIN   vespa_contest_types ON ( vespa_contest_types.contest_type_id=vespa_contests.contest_type) ';
    }

    public function formatColumn($colname, $colvalue, $item)
    {
        if ($colname == 'contest_name') {
            $colvalue = '<a href="' . admin_url('admin.php?page=contests') . '&action=view&id=' . $item->{$this->id_field}  . '" class="">' . stripslashes($colvalue) . '</a>';
        }

        if ($colname == 'school_entry_start_at') {
            $colvalue = $item->school_entry_start_at . ' -tól ' . $item->school_entry_end_at . ' -ig';
        }

        if ($colname == 'athlete_entry_start_at') {
            $colvalue = $item->athlete_entry_start_at . ' -tól ' . $item->athlete_entry_end_at . ' -ig';
        }

        if ($colname == 'listing_id') {
            if ($item->listing_id != 0) {
                $upldata = wp_upload_dir();
                $url = $upldata['baseurl'] . '/files/contest/' . $item->constest_id . '/' . $item->hashed_name;
                $colvalue = '<a href="' . $url . '" target="_blank">Kiírás</a>';
            } else {
                $colvalue = '';
            }
        }

        return $colvalue;
    }

    public function checkDelete( $id ){
        return current_user_can( 'manage_options' ) || current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles );
    }

    public function getFilters()
    {
        global $wpdb;
        $filters = '1';

        if (isset($_REQUEST['search']) && isset($_REQUEST['search']['value']) && trim($_REQUEST['search']['value']) != '') {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['search']['value'])) . '%';
            $filters = $wpdb->prepare("contest_name LIKE %s", $search);
        }

        return $filters;
    }

    function addActionButtons($item)
    {
        $btns = '';

        /*
            $btns .= '<a href="' . admin_url('admin.php?page=contests') . '&action=view&id=' . $item->{$this->id_field}  . '" class="btn btn-sm btn-default">';
            $btns .= '  <i class="fa fa-eye" aria-hidden="true"></i>';
            $btns .= '</a>&nbsp;';

            $btns .= '<a href="' . admin_url('admin.php?page=agegroups') . '&id=' . $item->{$this->id_field}  . '" class="btn btn-sm btn-default">';
            $btns .= '  <i class="fa fa-pencil" aria-hidden="true"></i>';
            $btns .= '</a>&nbsp;';

            $btns .= '<button class="btn btn-sm btn-default color-red delete-entity" data-modalid="" data-id="' . $item->{$this->id_field} . '">';
            $btns .= '  <i class="fa fa-trash" aria-hidden="true"></i>';
            $btns .= '</button>';
*/

        return $btns;
    }
}


$GLOBALS['VESPA_Contests'] = new VESPA_Contests();
