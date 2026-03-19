<?php
// Add your custom bulk action
add_filter('bulk_actions-users', 'register_my_custom_bulk_actions');
function register_my_custom_bulk_actions($bulk_actions) {
	$bulk_actions['export_selected_users'] = 'Kijelöltek exportálása';
	$bulk_actions['export_listed_users'] = 'Lista exportálása';
	
	return $bulk_actions;
}


add_action('admin_action_export_listed_users', 'my_custom_bulk_action_handler_listed_users');
function my_custom_bulk_action_handler_listed_users() {
	if (!current_user_can('export')) {
		return;
	}

	global $wpdb;

	$schools = array();
	$sql  = "SELECT * FROM vespa_institutions";
	$list = $wpdb->get_results( $sql );

	foreach( $list as $item ){
		$schools[ $item->institution_id ] = $item->ins_name;
	}	

    $args = array();
    if (!empty($_GET['role'])) {
        $args['role'] = $_GET['role'];
    }
    if (!empty($_GET['s'])) {
        $args['search'] = '*' . esc_attr($_GET['s']) . '*';
        $args['search_columns'] = array('user_login', 'user_email', 'user_nicename');
    }
    // Add other filters as needed

    $user_query = new WP_User_Query($args);	


    $filename = "users_export_" . date('Y-m-d') . ".csv";
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename={$filename}");

    $output = fopen('php://output', 'w');

    // Add CSV headers
    fputcsv($output, array( 'Felhasználó név', 'Email', 'Szerepkör', 'Intézmény'));

    if (!empty($user_query->get_results())) {
        foreach ($user_query->get_results() as $user) {

        $school_id = get_user_meta( $user->ID, 'school_id', true );

        // Write user data to CSV
        fputcsv($output, array(
        						$user->user_login, 
        						$user->user_email, 
        						implode(', ', $user->roles),
        						( isset($schools[$school_id]) ? $schools[$school_id] : '-' )
        				)
   				);
    	}
    }

    // Close the output stream
    fclose($output);

    // Make sure nothing else is added to the output
    exit;

}


//----------------------------------------------------------------
// Handle the custom bulk action
add_action('admin_action_export_selected_users', 'my_custom_bulk_action_handler');
function my_custom_bulk_action_handler() {
	// Verify if this is an export action and if the user has the right capability
	if (!current_user_can('export')) {
		return;
	}

	global $wpdb;

	$schools = array();
	$sql  = "SELECT * FROM vespa_institutions";
	$list = $wpdb->get_results( $sql );

	foreach( $list as $item ){
		$schools[ $item->institution_id ] = $item->ins_name;
	}

	// Get the selected users
	if(isset($_REQUEST['users'])) {
		$user_ids = array_map('intval', $_REQUEST['users']);
		// Your export logic goes here
		// For example, create a CSV file with user data

	    $filename = "users_export_" . date('Y-m-d') . ".csv";
	    header("Content-Type: text/csv");
	    header("Content-Disposition: attachment; filename={$filename}");

	    $output = fopen('php://output', 'w');

	    // Add CSV headers
	    fputcsv($output, array( 'Felhasználó név', 'Email', 'Szerepkör', 'Intézmény'));

	    foreach ($user_ids as $user_id) {
	        // Fetch user data
	        $user = get_userdata($user_id);

	        $school_id = get_user_meta( $user_id, 'school_id', true );

	        // Write user data to CSV
	        fputcsv($output, array(
	        						$user->user_login, 
	        						$user->user_email, 
	        						implode(', ', $user->roles),
	        						( isset($schools[$school_id]) ? $schools[$school_id] : '-' )
	        				)
	   				);
	    }

	    // Close the output stream
	    fclose($output);

	    // Make sure nothing else is added to the output
	    exit;

	}

	// Redirect back to the users page
//	wp_redirect(add_query_arg('exported', count($user_ids), $_SERVER['HTTP_REFERER']));
	exit;
}

// Add notice after export is done
add_action('admin_notices', 'custom_bulk_action_admin_notice');
function custom_bulk_action_admin_notice() {
	if(empty($_GET['exported'])) {
		return;
	}

	$count = intval($_GET['exported']);
	printf('<div id="message" class="updated fade"><p>%s users exported.</p></div>', $count);
}
