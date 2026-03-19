<?php
	function vespa_get_default_escort_data(){
		return array(
        	'kiserok' => array(),
        	'gepkocsivezetok' => array(),
        	'diak' => array(
        		'letszam' => 0,
        		'fiu' => 0,
        		'lany' => 0,
        		'igeny' => '',
        	),
        	'kisero' => array(
        		'letszam' => 0,
        		'ferfi' => 0,
        		'no' => 0,
        		'hazas' => 0,
        		'igeny' => '',
        	),        
        	'etkezes' => array(
        		'diak' => array(
					'letszam' => 0,
					'reggeli' => 0,
					'ebed'    => 0,
					'vacsora' => 0,
					'uti'     => 0,
					'igeny'   => '',
        		),
        		'kisero' => array(
					'letszam' => 0,
					'reggeli' => 0,
					'ebed'    => 0,
					'vacsora' => 0,
					'uti'     => 0,
					'igeny'   => '',
        		),        		
        	)	
        );
	}

	function vespa_get_escorts( $school_id, $contest_id ){
        global $wpdb;

		// SELECT * FROM `vespa_contests_escorts`
		// contest_escort_id, contest_id, school_id, escort_data
		if(!is_numeric($school_id) || !is_numeric($contest_id)){
			wp_send_json_error( array('errors' => [], 'succes' => false), 400 ); 
		}
		$sql = "SELECT * FROM vespa_contests_escorts WHERE school_id=%d AND contest_id=%d";
		$items = $wpdb->get_results($wpdb->prepare($sql, $school_id,  $contest_id));
		if( count($items) == 0 ){
			return null;
		}

		return $items[0];
	}

    function ajax_save_escorts(){
        global $wpdb;

				$school_id=$_POST['school_id'];
				$contest_id=$_POST['contest_id'];

				if(!is_numeric($school_id) || !is_numeric($contest_id)){
					wp_send_json_error( array('errors' => [], 'succes' => false), 400 ); 
				}
        
				$my_school_id = vespa_get_my_school_id();
				if(!VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::TESTNEVELO) || $my_school_id != $school_id){
					wp_send_json_error( array('errors' => [], 'succes' => false), 403 ); 
				}

        $data = vespa_get_default_escort_data();
				
        $has_driver = false;
        $has_escort = false;
				$errors_kiserok=[];
				$errors_gkvezetok=[];

				$contest = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_contests WHERE contest_id=%d",$contest_id));

        // kiserok_nev, kiserok_mobil, kiserok_email, kiserok_allergia
        foreach( $_POST['kiserok_nev'] as $key => $knev ){
        	if( 
        		! empty($knev) 
        		&& ! empty( $_POST['kiserok_mobil'][$key] )
        		&& ! empty( $_POST['kiserok_email'][$key] )
        		&& ! empty( $_POST['kiserok_allergia'][$key] )
        	){
        		$has_escort = true;

        		$data['kiserok'][] = array(
					'nev' => $_POST['kiserok_nev'][$key],
					'mobil' => $_POST['kiserok_mobil'][$key],
					'email' => $_POST['kiserok_email'][$key],
					'allergia' => $_POST['kiserok_allergia'][$key],
        		);
        	}
					else if(! empty($knev) 
					|| ! empty( $_POST['kiserok_mobil'][$key] )
					|| ! empty( $_POST['kiserok_email'][$key] )
					|| ! empty( $_POST['kiserok_allergia'][$key] ))
					$errors_kiserok['"kiserok_nev['.$key.']"'] = 'A kísérő összes adatát szükséges kitölteni';
        }

        foreach( $_POST['gepkocsivezeto_nev'] as $key => $knev ){
        	if( 
        		! empty($knev) 
        		&& ! empty( $_POST['gepkocsivezeto_mobil'][$key] )
        		&& ! empty( $_POST['gepkocsivezeto_email'][$key] )
        		&& ! empty( $_POST['gepkocsivezeto_allergia'][$key] )
        	){
        		$has_driver = true;

        		$data['gepkocsivezetok'][] = array(
					'nev' => $_POST['gepkocsivezeto_nev'][$key],
					'mobil' => $_POST['gepkocsivezeto_mobil'][$key],
					'email' => $_POST['gepkocsivezeto_email'][$key],
					'allergia' => $_POST['gepkocsivezeto_allergia'][$key],
        		);
        	}
					else if(! empty($knev) 
					|| ! empty( $_POST['gepkocsivezeto_mobil'][$key] )
					|| ! empty( $_POST['gepkocsivezeto_email'][$key] )
					|| ! empty( $_POST['gepkocsivezeto_allergia'][$key] ))
					$errors_gkvezetok['"gepkocsivezeto_nev['.$key.']"'] = 'A gépkocsivezető összes adatát szükséges kitölteni';					
        }       
        
        if( ! $has_driver && empty($errors_gkvezetok)){
        	$errors_gkvezetok['"gepkocsivezeto_nev[0]"'] = 'Legalább egy gépkocsivezetőt meg kell adnod';
        } 
        
        if( ! $has_escort && empty($errors_kiserok)){
        	$errors_kiserok['"kiserok_nev[0]"'] = 'Legalább egy kísérőt meg kell adnod';	
        } 

				if( count($data['kiserok']) > $contest->ppl_num_extra_max ){
					$errors_kiserok['"kiserok_nev[0]"'] = "Túl sok megadott kísérő. Jelen versenyre a maximális kísérőszám: $contest->ppl_num_extra_max db";	
				}

        if(!empty($errors_kiserok) || !empty($errors_gkvezetok)){
            wp_send_json_error( array('errors' => array_merge($errors_kiserok,$errors_gkvezetok)) );
        }

        // process other data points
        $data['diak']['letszam'] = $_POST['diak_ossz'];
        $data['diak']['fiu'] = $_POST['diak_fiu'];
        $data['diak']['lany'] = $_POST['diak_lany'];
        $data['diak']['igeny'] = $_POST['diak_igeny'];

        $data['kisero']['letszam'] = $_POST['kiserok_ossz'];
        $data['kisero']['ferfi'] = $_POST['kiserok_ferfi'];
        $data['kisero']['no'] = $_POST['kiserok_no'];
        $data['kisero']['hazas'] = $_POST['kiserok_hazas'];
        $data['kisero']['igeny'] = $_POST['kiserok_igeny'];

        $data['etkezes']['diak']['letszam'] = $_POST['etkezes_diak_ossz'];
        $data['etkezes']['diak']['reggeli'] = $_POST['etkezes_diak_reggeli'];
        $data['etkezes']['diak']['ebed'] = $_POST['etkezes_diak_ebed'];
        $data['etkezes']['diak']['vacsora'] = $_POST['etkezes_diak_vacsora'];
        $data['etkezes']['diak']['uti'] = $_POST['etkezes_diak_uti'];
        $data['etkezes']['diak']['igeny'] = $_POST['etkezes_diak_igeny'];
        
        $data['etkezes']['kisero']['letszam'] = $_POST['etkezes_kisero_ossz'];
        $data['etkezes']['kisero']['reggeli'] = $_POST['etkezes_kisero_reggeli'];
        $data['etkezes']['kisero']['ebed'] = $_POST['etkezes_kisero_ebed'];
        $data['etkezes']['kisero']['vacsora'] = $_POST['etkezes_kisero_vacsora'];
        $data['etkezes']['kisero']['uti'] = $_POST['etkezes_kisero_uti'];
        $data['etkezes']['kisero']['igeny'] = $_POST['etkezes_kisero_igeny'];

        $record = vespa_get_escorts( $_POST['school_id'], $_POST['contest_id'] );

        if( $record == null ){
            $success = $wpdb->insert( 'vespa_contests_escorts', array(
                            'contest_id'    => $_POST['contest_id'],
                            'school_id'    => $_POST['school_id'],
                            'escort_data'    => serialize($data),
                        ));

        }
        else {
            $success = $wpdb->update( 'vespa_contests_escorts', array(
                    'contest_id'    => $_POST['contest_id'],
                    'school_id'    => $_POST['school_id'],
                    'escort_data'    => serialize($data),
	            ), array(
	                'contest_escort_id' => $record->contest_escort_id
	            ));  
        }

       
        wp_send_json_success( array('content' => 'Adatok mentése sikeres volt', 'target' => 'escort_success') );
        //wp_send_json( array("success" => ( $success == 1 ? true : false) ) );
    }
    add_action( 'wp_ajax_save_escorts', 'ajax_save_escorts' );

    //---------