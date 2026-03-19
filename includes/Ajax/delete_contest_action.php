<?php

	add_action( 'wp_ajax_delete_contest' , 'vespa_delete_contest_action' );
	function vespa_delete_contest_action(){
		global $wpdb;

		check_ajax_referer( 'vespa_nonce', 'nonce' );

		$id = intval( $_POST['id'] );

        $model = new VespaContest( null );
        $model->load( $id );

        if( ! $model->can_delete() ){
        	wp_send_json( array("success" => false ) );
        	exit;
        }

        $tables = array(
            'vespa_contests',
            'vespa_contests_escorts',
            'vespa_contest_agegroups',
            'vespa_constest_events_results',
            'vespa_constest_events',
            'vespa_athlete_entries',
        );

        foreach( $tables as $table ){
            $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE contest_id = %d", $id ) );
        }

        wp_send_json( array("success" => true ) );
	}