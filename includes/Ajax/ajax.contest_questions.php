<?php

    //---------------------
    function ajax_list_contest_questions_record(){
        global $wpdb;

        //$wpdb->show_errors(); 
        $data = $wpdb->get_results("SELECT * FROM vespa_contests_questions");
        foreach($data as $oldQuestion){
            $matched = false;
            foreach ($_POST['data'] as $question) {
                if($question["question_id"] == $oldQuestion->question_id){
                    $matched = true;
                    break;
                }
            }
            if( !$matched ){
                $wpdb->delete('vespa_contests_questions', array( 'question_id' => $oldQuestion->question_id ), array('%s') );      
            }
        }

        foreach ($_POST['data'] as $question) {
            if($question['question_id'] == 0){
                $success = $wpdb->insert('vespa_contests_questions', array(
                    'ordernum'  => $question['ordernum'],
                    'question'    => $question['question'],
                    'answers'    => $question['answers']
                ), array('%s'));  
            }
            else{
                $success = $wpdb->update('vespa_contests_questions', array(
                    'ordernum'  => $question['ordernum'],
                    'question'    => $question['question'],
                    'answers'    => $question['answers']
                ), array( 'question_id' => $question['question_id']), array('%s'));       
            }
        }

        $data = $wpdb->get_results("SELECT * FROM vespa_contests_questions ORDER BY ordernum ASC");

        $vars = array(
            "{=TEXT=}" => 'Kérdések mentése sikeres volt.',
            "{=URL=}" => admin_url('admin.php?page=questions') 
        );

        wp_send_json_success( array('modal' => vespa_load_template_with_vars( 'success-modal.php', $vars ), 'modalId' => 'succesModal' ) );    
        //wp_send_json( array("success" => ( $data )));
    }
    add_action( 'wp_ajax_vespa_ajax_list_contest_questions_save', 'ajax_list_contest_questions_record' );    


    function ajax_list_contest_questions_get_records(){
        global $wpdb;

        //$wpdb->show_errors(); 
        $data = $wpdb->get_results("SELECT * FROM vespa_contests_questions ORDER BY ordernum ASC");
        //$wpdb->print_error();
        
        wp_send_json( array("data" => $data) );
    }
    add_action( 'wp_ajax_vespa_ajax_list_contest_questions_get', 'ajax_list_contest_questions_get_records' );
