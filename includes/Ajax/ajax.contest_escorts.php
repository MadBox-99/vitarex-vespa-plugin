<?php
    function ajax_table_contest_escorts_display( $id = null ){
        global $wpdb;

        if( $id == null ) {
            $id = intval($_POST['contest_id']);
        }

        ob_start();
        $sqlList = "SELECT e.*, ins.ins_name
                                    FROM vespa_contests_escorts as e
                                    JOIN vespa_institutions as ins ON e.school_id = ins.institution_id
                                    WHERE e.contest_id= %d";
        $list = $wpdb->get_results($wpdb->prepare($sqlList, $id));


        if( empty($list) ){
            wp_send_json( array("success" => true, 'html' => '<table class="table table-striped" id="ajax_table_contest_escorts"><tr><td>Nincs megjeleníthető adat</td></tr></table>' ) );
            exit;
        }
        ?>
            <table class="table table-striped" id="ajax_table_contest_escorts">
            <thead>
            <tr>
                <th>Iskola</th>
                <th>Kísérő neve</th>
                <th>Telefonszám</th>
                <th>Email cím</th>
                <th width="300">&nbsp;</th>
            </tr>
            </thead>

            <tbody>
            <?php
                foreach($list as $item):
            ?>
                <tr class="contest_escorts-item"
                    data-id="<?php echo intval($item->contest_escort_id); ?>"
                >
                    <td><?php echo esc_html($item->ins_name); ?></td>
                    <td><?php echo esc_html($item->escort_name); ?></td>
                    <td><?php echo esc_html($item->escort_phone); ?></td>
                    <td><?php echo esc_html($item->escort_email); ?></td>
                    <td>
                        <?php if( current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles ) ): ?>
                            <button class="btn btn-default btn-sm" onclick="showContestEscortEditor( <?php echo intval($item->contest_escort_id); ?> );" >Szerkeszt</button>
                            <button class="btn btn-default btn-sm" onclick="deleteContestEscort( <?php echo intval($item->contest_escort_id); ?> );">Töröl</button>
                        <?php endif; ?>
                    </td>

                </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
        <?php

        $html = ob_get_contents();
        ob_get_clean();

        wp_send_json( array("success" => true, 'html' => $html ) );
    }

    add_action( 'wp_ajax_vespa_ajax_table_contest_escorts', 'ajax_table_contest_escorts_display' );

    //---------------------
    function ajax_table_contest_escorts_add_record(){
        global $wpdb;

        if( ! current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles ) ){
            wp_send_json_error( array("message" => "Jogosulatlan hozzáférés"), 403 );
        }

        $success = $wpdb->insert('vespa_contests_escorts', array(
            'contest_id'    => intval($_POST['contest_id']),
            'school_id'     => vespa_get_my_school_id(),
            'escort_name'   => sanitize_text_field($_POST['escort_name']),
            'escort_phone'  => sanitize_text_field($_POST['escort_phone']),
            'escort_email'  => sanitize_email($_POST['escort_email']),
        ), array('%d', '%d', '%s', '%s', '%s'));

        wp_send_json( array("success" => ( $success == 1 ? true : false) ) );
    }
    add_action( 'wp_ajax_vespa_ajax_table_contest_escorts_add', 'ajax_table_contest_escorts_add_record' );

    //---------------------
    function ajax_table_contest_escorts_modify_record(){
        global $wpdb;

        if( ! current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles ) ){
            wp_send_json_error( array("message" => "Jogosulatlan hozzáférés"), 403 );
        }

        $success = $wpdb->update('vespa_contests_escorts', array(
            'school_id'     => vespa_get_my_school_id(),
            'escort_name'   => sanitize_text_field($_POST['escort_name']),
            'escort_phone'  => sanitize_text_field($_POST['escort_phone']),
            'escort_email'  => sanitize_email($_POST['escort_email']),
        ), array( 'contest_escort_id' => intval($_POST['contest_escort_id'])), array('%d', '%s', '%s', '%s'));

        wp_send_json( array("success" => ( $success == 1 ? true : false) ) );
    }
    add_action( 'wp_ajax_vespa_ajax_table_contest_escorts_modify', 'ajax_table_contest_escorts_modify_record' );

    function ajax_table_contest_escorts_get_record(){
        global $wpdb;

        $id = intval($_POST['id']);

        $sqlData = "SELECT * FROM vespa_contests_escorts WHERE contest_escort_id= %d";
        $data = $wpdb->get_results($wpdb->prepare($sqlData, $id));

        wp_send_json( array("data" => $data[0] ) );
    }
    add_action( 'wp_ajax_vespa_ajax_table_contest_escorts_get', 'ajax_table_contest_escorts_get_record' );

    //---------------------
    function ajax_table_contest_escorts_delete_record(){
        global $wpdb;

        if( ! current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles ) ){
            wp_send_json_error( array("message" => "Jogosulatlan hozzáférés"), 403 );
        }

        $success = $wpdb->delete('vespa_contests_escorts', array( 'contest_escort_id' => intval($_POST['id'])), array('%d') );

        wp_send_json( array("success" => ( $success == 1 ? true : false) ) );
    }
    add_action( 'wp_ajax_vespa_ajax_table_contest_escorts_delete', 'ajax_table_contest_escorts_delete_record' );
