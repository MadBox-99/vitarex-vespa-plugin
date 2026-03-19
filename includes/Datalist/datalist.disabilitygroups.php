<?php

class VESPA_DisabilityGroups extends VESPA_Datalist
{
    protected $source            = 'disability_groups';
    protected $tablename         = 'vespa_disability_groups';
    protected $id_field          = "disability_group_id";
    protected $default_order_by  = "disability_group_name";
    protected $default_order_dir = 'ASC';
    protected $columns           = array('disability_group_id', 'disability_group_name');



    public function save()
    {
        global $wpdb;

        $errors = array();
        if (empty($_POST["disability_group_name"])) {
            $errors["disability_group_name"] = 'Kötelező mező';
        }

        if (!empty($errors)) {
            wp_send_json_error(array('errors' => $errors));
        }

        // insert or update
        if (intval($_POST["disability_group_id"]) == 0) {

            $success = $wpdb->insert($this->tablename, array(
                "disability_group_name"    => sanitize_text_field($_POST["disability_group_name"])
            ), array(
                '%s'
            ));
        } else {
            $success = $wpdb->update(
                $this->tablename,
                array(
                    "disability_group_name"    => sanitize_text_field($_POST["disability_group_name"])
                ),
                array(
                    "disability_group_id" => intval($_POST["disability_group_id"])
                ),
                array(
                    '%s'
                )
            );
        }

        $vars = array(
            "{=TEXT=}" => 'Fogyatékossági csoport mentése sikeres volt.',
            "{=URL=}" => admin_url('admin.php?page=disability_groups')
        );

        wp_send_json_success(array('modal' => vespa_load_template_with_vars('success-modal.php', $vars), 'modalId' => 'succesModal'));
    }

    public function checkDelete( $id ){
        return current_user_can( 'manage_options' ) || current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles );
    }

    public function getFilters()
    {
        global $wpdb;
        $filters = '1';

        if (isset($_REQUEST['search']) && isset($_REQUEST['search']['value']) && trim($_REQUEST['search']['value']) != '') {
            $filters = $wpdb->prepare("disability_group_name LIKE %s", '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['search']['value'])) . '%');
        }

        return $filters;
    }

    function addActionButtons($item)
    {
        $btns = '';
        $btns .= '<a href="' . admin_url('admin.php?page=disability_groups') . '&id=' . $item->{$this->id_field}  . '" class="btn btn-sm btn-default">';
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


$GLOBALS['VESPA_DisabilityGroups'] = new VESPA_DisabilityGroups();
