<?php

add_action( 'rest_api_init', function () {
    register_rest_route( 'vespa/v1', '/contests', array(
      'methods' => 'GET',
      'callback' => 'get_contests_list',
    ) );
} );
add_action( 'rest_api_init', function () {
    register_rest_route( 'vespa/v1', '/contest', array(
      'methods' => 'GET',
      'callback' => 'get_contest',
    ) );
} );
add_action( 'rest_api_init', function () {
    register_rest_route( 'vespa/v1', '/search', array(
      'methods' => 'GET',
      'callback' => 'search_contests',
    ) );
} );

function get_contests_list(WP_REST_Request $request )
{
    global $wpdb;

    $apiKey = 'svU3VuyEGrc5fTg9fLc7gkyLEAir2gED';

    $params = $request->get_query_params('vespa-api-key');
    $headerKey = $request->get_header('x-vespa-api-key');

    if( $params['vespa-api-key'] != $apiKey && $headerKey != $apiKey ) { 
        return new WP_Error( 'unauthorized', 'Unauthorized', array( 'status' => 401 ) );
    }

    date_default_timezone_set('Europe/Budapest');

    $current_time = date('Y-m-d H:i:s', time());

    $data = $wpdb->get_results("SELECT * FROM `vespa_contests` as vc
                                WHERE vc.is_final = 1 AND vc.end_at >= '$current_time'
                            ");
    $series = $wpdb->get_results("SELECT vce.contest_id, vce.gender, vce.disability_groups, vce.agnames, 
                                    vs.sport_id, vs.sport_name, vse.sport_event_id, vse.sport_event_name,
                                    (SELECT GROUP_CONCAT(disability_group_name SEPARATOR ',') FROM vespa_disability_groups WHERE FIND_IN_SET(disability_group_id, vce.disability_groups) ) as disgroupname
                                FROM `vespa_constest_events` as vce
                                JOIN vespa_sports as vs ON vce.sport_id=vs.sport_id
                                LEFT JOIN vespa_sport_events as vse ON vce.event_id=vse.sport_event_id
                                WHERE contest_id IN (SELECT contest_id FROM vespa_contests as vc WHERE vc.is_final = 1 AND vc.end_at >= '$current_time')
                                ");

    $tipus = [
        1 => 'orszagos',
        2 => 'teruleti',
        3 => 'megyei'
    ];
    $lebonyolitas = [
        1 => 'sorozat', // tovabbjutasos
        2 => 'bajnoksag' // nem tovabbjutasos
    ];
    $data = array_map(function ($contest) use($tipus, $lebonyolitas, $series) {
        return [
            'id_verseny' => $contest->contest_id,
            'nev' => $contest->contest_name,
            'tipus' => $tipus[$contest->contest_type],
            'kezdes' => $contest->start_at,
            'vege' => $contest->end_at,
            'lebonyolitas' => $lebonyolitas[$contest->contest_subtypes],
            'helyszin' => $contest->place_name,
            'cim' => $contest->place_address,
            'versenyek' => array_map(function ($event) {
                return [
                    'sport_id' => $event->sport_id,
                    'sport_name' => $event->sport_name,
                    'sport_event_id' => $event->sport_event_id,
                    'sport_event_name' => $event->sport_event_name,
                    'gender' => $event->gender,
                    'agnames' => $event->agnames,
                    'disability_groups' => $event->disability_groups,
                    'disability_group_names' => $event->disgroupname
                ];
            }, array_values(array_filter($series, function ($s) use($contest) {
                return $s->contest_id == $contest->contest_id;
            }))),
            'lat' => 0.000000,
            'lng' => 0.000000,
            'rendezo' => $contest->organiser,
            'modositva' => $contest->modified_at,
            'letrehozva' => $contest->created_at,
            'isk_nevezes_kezdete' => $contest->school_entry_start_at,
            'isk_nevezes_vege' => $contest->school_entry_end_at,
            'sportolo_nevezes_kezdete' => $contest->athlete_entry_start_at ? $contest->athlete_entry_start_at : $contest->school_entry_start_at,
            'sportolo_nevezes_vege' => $contest->athlete_entry_end_at ? $contest->athlete_entry_end_at : $contest->school_entry_end_at,
            'min_letszam' => $contest->ppl_num_min,
            'max_letszam' => $contest->ppl_num_max
        ];
    }, $data);

    return $data;
}

function get_contest(WP_REST_Request $request )
{
    global $wpdb;

    $apiKey = 'svU3VuyEGrc5fTg9fLc7gkyLEAir2gED';

    $params = $request->get_query_params('vespa-api-key');
    $headerKey = $request->get_header('x-vespa-api-key');

    $contestId = $_GET['id'];
    $attachments= $_GET['attachments'];

    if( !is_numeric($contestId) )
        return new WP_Error( 'bad request', 'Bad request', array( 'status' => 400 ) );

    if( $params['vespa-api-key'] != $apiKey && $headerKey != $apiKey) { 
        return new WP_Error( 'unauthorized', 'Unauthorized', array( 'status' => 401 ) );
    }

    $contest = $wpdb->get_row($wpdb->prepare("SELECT * FROM `vespa_contests` as vc
                                JOIN vespa_states as vs ON vc.state_id=vs.state_id
                                WHERE vc.is_final = 1 AND vc.contest_id =%d",$contestId));

    $tipus = [
        1 => 'orszagos',
        2 => 'teruleti',
        3 => 'megyei'
    ];
    $lebonyolitas = [
        1 => 'Tovabbjutásos', // tovabbjutasos
        2 => 'Nem tovabbjutásos' // nem tovabbjutasos
    ];

    $contest_events = null;
    $contAgegroups = null;
    if(is_numeric($contest->contest_id)){
        $contest_events =   $wpdb->get_results($wpdb->prepare("SELECT vce.*, vs.sport_name,
        (SELECT GROUP_CONCAT(disability_group_name SEPARATOR ',') FROM vespa_disability_groups WHERE FIND_IN_SET(disability_group_id, vce.disability_groups) ) as disgroup 
        FROM `vespa_constest_events` as vce
        JOIN vespa_sports as vs ON vce.sport_id=vs.sport_id
        WHERE vce.contest_id = %d",$contest->contest_id));
    $contAgegroups = $wpdb->get_results($wpdb->prepare("SELECT vca.agegroup_id as k_szam, vca.date_from as tol, vca.date_to as ig FROM `vespa_contest_agegroups` as vca WHERE vca.contest_id =%d",$contest->contest_id));

    }
$disGroup = [];
    foreach($contest_events as $event) {
        foreach (explode(",", $event->disgroup) as $value) {
            array_push($disGroup, $value);
        }
    }
    $genderList = [];
    foreach (explode(" ", $event->gender) as $gender) {
        array_push($genderList, $gender);
    }
    $sportList = [];
    foreach ($contest_events as $event) {
        array_push($sportList, $event->sport_name);
    }

    $attachmentData = null;
    if($attachments == 'add'){
        $pdfContent = vespa_download_listing($contest->contest_id, true);
        $attachmentData = [
            "kiiras"=> [
                "size"=> strlen($pdfContent), 
                "base64"=> $pdfContent
            ], "logo"=> [
                "size" => 0
            ]
        ];
    }

    return [
        "sportagak" => array_unique($sportList),
        "fcsop" => array_unique($disGroup),
        "kcsop" => $contAgegroups,
        "nem"=> array_unique($genderList),
        "nev"=> $contest->contest_name,
        "rendezo"=> $contest->organiser,
        "allapot"=> "aktiv",
        "modositva"=> $contest->modified_at,
        "helyszin"=> $contest->place_name,
        "cim"=> $contest->place_address,
        "kezdes"=> $contest->start_at,
        "vege"=> $contest->end_at,
        "tipus"=> $tipus[$contest->contest_type],
        "kiiro_info"=> "0",
        "megye"=> $contest->state_name,
        "id_terulet"=> "0",
        "lebonyolitas"=> $lebonyolitas[$contest->contest_subtypes],
        "lebonyolitas_nev"=> "",
        "id_sorozat"=> null, //$contest->contest_series,
        "tipusInfo"=> "",
        "id"=> $contest->contest_id,
        "attachments"=> $attachmentData
    ];
}

function search_contests(WP_REST_Request $request )
{
    global $wpdb;

    $query = $request->get_query_params();

    $apiKey = 'svU3VuyEGrc5fTg9fLc7gkyLEAir2gED';
    $headerKey = $request->get_header('x-vespa-api-key');
    if( !isset($query['vespa-api-key']) || ($query['vespa-api-key'] != $apiKey && $headerKey != $apiKey)) { 
        return new WP_Error( 'unauthorized', 'Unauthorized', array( 'status' => 401 ) );
    }

    date_default_timezone_set('Europe/Budapest');

    $prev_month = (new DateTime())->modify('-1 month');
    $next_month = (new DateTime())->modify('+1 month');

    $name = isset($query['name']) ? sanitize_text_field($query['name']) : null;
    $from = isset($query['from']) ? sanitize_text_field($query['from']) : $prev_month->format('Y-m-d H:i:s');
    $to = isset($query['to']) ? sanitize_text_field($query['to']) : $next_month->format('Y-m-d H:i:s');
    $place = isset($query['place']) ? sanitize_text_field($query['place']) : null;

    $contestType = isset($query['contestType']) && is_numeric($query['contestType']) ? $query['contestType'] : null;
    $state = isset($query['state']) && is_numeric($query['state']) ? $query['state'] : null;
    $disGroup = isset($query['disGroup']) && is_numeric($query['disGroup']) ? $query['disGroup'] : null;
    $sport = isset($query['sport']) && is_numeric($query['sport']) ? $query['sport'] : null;
    $sportEvent = isset($query['sportEvent']) && is_numeric($query['sportEvent']) ? $query['sportEvent'] : null;

    $sql = "SELECT vc.*, vs.state_name 
            FROM `vespa_contests` as vc 
            LEFT JOIN vespa_states as vs ON vc.state_id=vs.state_id 
            WHERE vc.is_final = 1";
    $sqlParams = array();
    if($contestType) {
        $sql .= " AND vc.contest_type = %d";
        array_push($sqlParams, $contestType);
    };
    if($from) {
        $sql .= " AND vc.start_at >= %s";
        array_push($sqlParams, $from);
    }
    if($to) {
        $sql .= " AND vc.end_at <= %s";
        array_push($sqlParams, $to);
    }
    if($name) {
        $sql .= " AND vc.contest_name LIKE %s";
        array_push($sqlParams, '%' . $wpdb->esc_like($name) . '%');
    }
    if($place) {
        $sql .= " AND vc.place_name LIKE %s";
        array_push($sqlParams, '%' . $wpdb->esc_like($place) . '%');
    }
    if($state) {
        $sql .= " AND vc.state_id = %d";
        array_push($sqlParams, $state);
    }
    $sql = $wpdb->prepare($sql, $sqlParams);
    $data = $wpdb->get_results($sql);
  
    $idList = join(",",array_map(function ($data) { return $data->contest_id;}, $data));
    if(strlen($idList) < 1)
        $idList = '0';
    
    $sqlParams = array();
    $sql = "SELECT vce.contest_id, vce.gender, vce.disability_groups, vce.agnames, vs.sport_id, vs.sport_name, vse.sport_event_id, vse.sport_event_name, (SELECT GROUP_CONCAT(disability_group_name SEPARATOR ',') FROM vespa_disability_groups WHERE FIND_IN_SET(disability_group_id, vce.disability_groups) ) as disgroupname
            FROM `vespa_constest_events` as vce
            JOIN vespa_sports as vs ON vce.sport_id=vs.sport_id
            LEFT JOIN vespa_sport_events as vse ON vce.event_id=vse.sport_event_id
            WHERE contest_id IN ($idList)";
    if($disGroup) {
        $sql .= " AND FIND_IN_SET(%d, vce.disability_groups)";
        array_push($sqlParams, $disGroup);
    }
    if($sport) {
        $sql .= " AND vce.sport_id = %d";
        array_push($sqlParams, $sport);
    }
    if($sportEvent) {
        $sql .= " AND vce.sport_event_id = %d";
        array_push($sqlParams, $sportEvent);
    }
    $sql = $wpdb->prepare($sql, $sqlParams);
    $series = $wpdb->get_results($sql);

    $tipus = [
        1 => 'orszagos',
        2 => 'teruleti',
        3 => 'megyei'
    ];
    $lebonyolitas = [
        1 => 'sorozat', // tovabbjutasos
        2 => 'bajnoksag' // nem tovabbjutasos
    ];

    $data = array_map(function ($contest) use($tipus, $lebonyolitas, $series) {
        return [
            'id_verseny' => $contest->contest_id,
            'nev' => $contest->contest_name,
            'tipus' => $contest->contest_type, //$tipus[$contest->contest_type],
            'kezdes' => $contest->start_at,
            'vege' => $contest->end_at,
            'lebonyolitas' => $lebonyolitas[$contest->contest_subtypes],
            'helyszin' => $contest->place_name,
            'cim' => $contest->place_address,
            // 'megye_id' => $contest->state_id,
            'megye_nev' => $contest->state_name,
            'versenyek' => array_map(function ($event) {
                return [
                    'sport_id' => $event->sport_id,
                    'sport_name' => $event->sport_name,
                    'sport_event_id' => $event->sport_event_id,
                    'sport_event_name' => $event->sport_event_name,
                    'gender' => $event->gender,
                    'agnames' => $event->agnames,
                    'disability_groups' => $event->disability_groups,
                    'disability_group_names' => $event->disgroupname
                ];
            }, array_values(array_filter($series, function ($s) use($contest) {
                return $s->contest_id == $contest->contest_id;
            }))),
            'lat' => 0.000000,
            'lng' => 0.000000,
            'rendezo' => $contest->organiser,
            'modositva' => $contest->modified_at,
            'letrehozva' => $contest->created_at,
            'isk_nevezes_kezdete' => $contest->school_entry_start_at,
            'isk_nevezes_vege' => $contest->school_entry_end_at,
            'sportolo_nevezes_kezdete' => $contest->athlete_entry_start_at ? $contest->athlete_entry_start_at : $contest->school_entry_start_at,
            'sportolo_nevezes_vege' => $contest->athlete_entry_end_at ? $contest->athlete_entry_end_at : $contest->school_entry_end_at,
            'min_letszam' => $contest->ppl_num_min,
            'max_letszam' => $contest->ppl_num_max
        ];
    }, $data);

    $data = array_values(array_filter($data, function ($s) {
        return count($s['versenyek']) > 0;
    }));

    return $data;
}