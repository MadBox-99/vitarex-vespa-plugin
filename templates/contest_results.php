
<script>
    var visszajelzes = "Sikeres nevezések:\n";
    var sikeres_nevezesek_szama = 0;
    var osszes_nevezesek_szama = 0;

    function tovabbjuto_nevezes(adatok) {
        //e.preventDefault();

        console.log(`nevezés: ${sikeres_nevezesek_szama+1}/${osszes_nevezesek_szama})`);

        jQuery.ajax({
            type: "POST",
            dataType: "json",
            url: vitarex_vespa_ajaxurl,
            data: {
                action: "athletes_signup",
                contest_id: adatok["contest_id"],
                event_id: adatok["event_id"],
                contest_event_id: adatok["contest_event_id"],
                athlete_ids: adatok["athlete_ids"],
                school_id: adatok["school_id"],
                selejtezo_verseny_id: adatok['selejtezo_verseny_id']
            },
            success: function(resp) {
                if (resp.success) {
                    visszajelzes += `${adatok['nev']}\n`;
                    sikeres_nevezesek_szama++;
                    if (sikeres_nevezesek_szama == osszes_nevezesek_szama) {
                        alert(visszajelzes);
                        location.reload();
                    }
                } else {
                    alert(`Nem megfelelő működés: ${resp.message}`)
                }
            },
            error: function(resp) {
                alert(`Hiba történt: ${resp.message}`)
            }
        });
    }
</script>
<style>
    input[type="time"]::-webkit-calendar-picker-indicator {
        display: none;
    }

    input[type="time"]::-webkit-inner-spin-button,
    input[type="time"]::-webkit-clear-button {
        display: none;
    }
</style>
<?php
global $wpdb;

if (!isset($_GET['id'])) {
    wp_redirect(admin_url('admin.php?page=vespa_contests'));
    exit;
}

$id         = $_GET['id'];
$race_id         = $_GET['raceId'];
$record     = null;
$tovabbjutok = $_POST["tovabbjutok"];
$tovabbjutasi_verseny_id = $_POST["tovabbjutasi_verseny_id"];





if (is_numeric($id) && $id > 0) {
    $record = $GLOBALS['VESPA_Contests']->load($id);

    if ( !(current_user_can(VESPA_Roles::verseny_eredmenyek_kezelese_rogzites_modositas) && $record->is_final == 1)) {
        echo 'Nincs megfelelő jogosultságod az oldal megtekintéséhez.';
        exit;
    }

    $site_title = $record->contest_name . ' - eredmények';
    $race = $wpdb->get_row($wpdb->prepare("SELECT e.*, p.sport_name, p.result_type, s.sport_event_name, p.sport_id, s.result_type as event_result_type
    FROM vespa_constest_events as e 
    JOIN vespa_sports as p ON p.sport_id = e.sport_id 
    LEFT JOIN vespa_sport_events as s ON s.sport_event_id = e.event_id   
    WHERE e.id=%d",$race_id));
    $record = $GLOBALS['VESPA_Contests']->load($id);
    $event = $wpdb->get_results($wpdb->prepare("SELECT event_id FROM vespa_constest_events WHERE id= %d", $race_id));
    $tovabbjutas_contest_event = $wpdb->get_results($wpdb->prepare("SELECT event_id FROM vespa_constest_events WHERE id= %d", $race_id));
    $subtype = $GLOBALS['VESPA_Contest_Subtypes']->load($record->contest_subtypes);
    $series = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_contest_series WHERE series_id= %d", $record->contest_series));
    $type = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_contest_types WHERE contest_type_id= %d", $record->contest_type));

    $age_groups = $wpdb->get_results($wpdb->prepare("SELECT * FROM vespa_contest_agegroups WHERE contest_id=%d ORDER BY agegroup_id ASC", $id));
    //azok lehetnek a továbbjutási versenyek, amelyek: országosak és van bennük olyan versenyszám, mint a forrás megyei versenyen
    $tovabbjutasi_fogy_csoport = $race->disability_groups;
    $tovabbjutasi_korcsoport = $race->agnames;
    $tovabbjutasi_datumtol = $race->dfrom;
    $tovabbjutasi_datumig = $race->dto;
    $tovabbjutasi_nem = $race->gender;
    $tovabbjutasi_lista_sql = "SELECT vc.*, vce.id AS vce_id FROM vespa_contests AS vc 
    JOIN vespa_constest_events AS vce ON vce.contest_id=vc.contest_id 
    WHERE vc.contest_type=1 AND vc.contest_id IN 
    (SELECT contest_id from vespa_constest_events WHERE sport_id=(SELECT sport_id FROM vespa_constest_events WHERE id=%d) 
    AND event_id=(SELECT event_id FROM vespa_constest_events WHERE id=%d)) 
    AND vce.disability_groups=%s AND vce.dfrom=%s AND vce.dto=%d 
    AND vce.gender=%d";
    $tovabbjutasi_verseny_lista = $wpdb->get_results($wpdb->prepare($tovabbjutasi_lista_sql,$race_id,$race_id,$tovabbjutasi_fogy_csoport,$tovabbjutasi_datumtol,$tovabbjutasi_datumig,$tovabbjutasi_nem));
    //vannak-e már továbbjutók (azaz ahol a nevezések között van olyan, ahol a selejtező verseny id ki van töltve, és az az aktuális verseny)
    $tovabbjutok_lista = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `vespa_athlete_entries` AS vae 
        WHERE vae.selejtezo_vespa_athlete_entries_id=%d", $id
    ));

    $upldata = wp_upload_dir();

    $listing = null;
    if ($record->listing_id > 0) {
        $listing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM vespa_files WHERE file_id=%d", $record->listing_id)
        );

        $listing = $upldata['baseurl'] . '/files/contest/' . $record->contest_id . '/' . $listing->hashed_name;
    }

    $logo = null;
    if ($record->logo_id > 0) {
        $logo = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM vespa_files WHERE file_id=%d", $record->logo_id)
        );

        $logo = $upldata['baseurl'] . '/files/contest/' . $record->contest_id . '/' . $logo->hashed_name;
    }
}


function mertekegyseg($result_type)
{
    switch ($result_type) {
        case 'helyezes_ido_k':
        case 'helyezes_ido_n':
            return 'Idő';
        case 'helyezes_tav_k':
        case 'helyezes_tav_n':
            return 'Távolság';
        case 'helyezes_suly_k':
        case 'helyezes_suly_n':
            return 'Súly';
        default:
            return $result_type;
    }
}

?>

<input type="hidden" name="contest_id" id="contest_id" autocomplete="off" value="<?php echo $id; ?>">
<input type="hidden" name="race_id" id="race_id" autocomplete="off" value="<?php echo $race_id; ?>">

<?php

$sql = "SELECT e.*, a.school_id, a.athlete_name, a.birth_date,p.sport_name, s.sport_event_name, i.ins_name, i.id_number, i.institution_id, a.gender,d.disability_group_name, a.disability_type, e.contest_event_id, st.state_name              
        FROM vespa_athlete_entries as e 
        JOIN vespa_athletes as a ON a.athlete_id = e.athlete_id 
        JOIN vespa_constest_events as e2 ON e2.id=%d AND e2.contest_id=e.contest_id AND FIND_IN_SET(a.disability_type, e2.disability_groups) 
        JOIN vespa_sports as p ON p.sport_id = e2.sport_id 
        LEFT JOIN vespa_sport_events as s ON s.sport_event_id = e2.event_id   
        JOIN vespa_disability_groups as d ON d.disability_group_id = a.disability_type
        LEFT JOIN vespa_institutions as i ON i.institution_id = a.school_id
        LEFT JOIN vespa_states as st ON st.state_id = i.ins_state
        WHERE e.contest_id=%d AND e.contest_event_id=%d
        ORDER BY ins_name ASC, a.gender ASC, a.birth_date ASC, athlete_name ASC";
$athletes = $wpdb->get_results($wpdb->prepare($sql,$race_id,$id,$race_id));

$orderedAthletes = [];
foreach ($athletes as $athlete) {
    $orderedAthletes[$athlete->disability_group_name][$athlete->gender][] = $athlete;
}
// echo $sql;

//nevezés ellenőrzés
if (isset($_POST["nevezes"])) {
    if (empty($tovabbjutok))
        $nevezes_hiba = "Nincsenek kiválasztott továbbjutók";
    else if (count($tovabbjutok) > $record->qualifier_num_max)
        $nevezes_hiba = "A kiválasztott továbbjutók száma maximum <b>$record->qualifier_num_max</b> lehet";
    if ($tovabbjutasi_verseny_id < 1)
        $nevezes_hiba = isset($nevezes_hiba) ? "$nevezes_hiba<br/>Nincsen kiválasztott továbbjutási verseny" : "Nincsen kiválasztott továbbjutási verseny";
    if (empty($nevezes_hiba)) {
        $event_id = $event[0]->event_id;
        $tovabbjutasi_contest_event_id = array_values(array_filter($tovabbjutasi_verseny_lista, fn ($elem) => $elem->id = $tovabbjutasi_verseny_id))[0]->vce_id;
        $index = 0;
        $max_meret = count($tovabbjutok);
        foreach ($tovabbjutok as $tovabbjuto_id) {
            $keresett_athlete_informaciok = array_values(array_filter($athletes, fn ($elem) => $elem->athlete_id == $tovabbjuto_id))[0];
            $school_id = $keresett_athlete_informaciok->school_id;
            $nev = $keresett_athlete_informaciok->athlete_name;
            // $index++;
            // $utolso_elem = $index == $max_meret;
            echo "<script>osszes_nevezesek_szama=$max_meret;  tovabbjuto_nevezes({'nev':'$nev','contest_id':$tovabbjutasi_verseny_id,'contest_event_id':$tovabbjutasi_contest_event_id,'school_id':$school_id, 'event_id':$event_id,'athlete_ids':$tovabbjuto_id,'selejtezo_verseny_id': $id},$utolso_elem);</script>";
        }
        //echo '<script>location.reload();</script>';
    }
}

if (isset($_POST["nevezes_visszavonas"])) {
    $wpdb->delete(
        'vespa_athlete_entries',
        array('selejtezo_vespa_athlete_entries_id' => $id),
        array('%d')
    );
    unset($_POST["nevezes_visszavonas"]);
    echo '<script>location.reload();</script>';
}
?>

<div id="app">

    <div class="wrap">
        <div class="row">
            <div class="col-md-12">
                <h1 class="site-title"><?php echo $site_title; ?></h1>
            </div>
        </div>


        <div class="vespa-box" id="alapadatok">
            <div class="row">
                <div class="col-md-6">
                    <h3 style="margin-top:0">Alapadatok</h3>
                </div>
                <div class="col-md-12">
                    <table class="table table-striped">
                       <!-- <tr>
                            <td width="250"><strong>Verseny lebonyolítás:</strong></td>
                            <td>
                                <?php
                                echo $subtype->contest_subtype_name . ' / ';
                                echo $series->series_name;
                                ?>
                            </td>
                        </tr>
-->
                        <tr>
                            <td><strong>Rendező:</strong></td>
                            <td><?php echo $record->organiser; ?></td>
                        </tr>

                        <tr>
                            <td><strong>Típus:</strong></td>
                            <td><?php echo $type->contest_type_name; ?></td>
                        </tr>

                        <tr>
                            <td><strong>Helyszín neve:</strong></td>
                            <td><?php echo $record->place_name; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Helyszín címe:</strong></td>
                            <td><?php echo $record->place_address; ?></td>
                        </tr>
                        <?php if ($record->contest_subtypes == 1) : ?>
                            <tr>
                                <td><strong>Továbbjutók max. száma:</strong></td>
                                <td><?php echo $record->qualifier_num_max; ?></td>
                            </tr>
                        <?php endif ?>
                        <?php if ($listing != null) : ?>
                            <tr>
                                <td><strong>Kiírás:</strong></td>
                                <td>
                                    <a href="<?php echo $listing; ?>" download="">Letöltés</a>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php if ($logo != null) : ?>
                            <tr>
                                <td><strong>Logó:</strong></td>
                                <td>
                                    <img src="<?php echo $logo; ?>" style="height:100px; width:auto;">
                                </td>
                            </tr>
                        <?php endif; ?>

                    </table>

                </div>
            </div>
        </div>

        <?php


        $result_type = $race->event_result_type ? $race->event_result_type : $race->result_type;
        $tovabbjutasos_verseny = $subtype->contest_subtype_name == "Továbbjutásos";

        if ($race != null) :

            $result_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_constest_events_results WHERE contest_id=%d AND contest_event_id=%d",$id,$race_id));
        ?>
            <div class="vespa-box" id="versenyszamok">
                <div class="row">
                    <div class="col-md-12">
                        <h3 style="margin-top:0">Eredmények - <?php echo $race->sport_name . ' / ' . $race->sport_event_name; ?></h3>
                    </div>

                    <div class="col-md-12">
                        <form action="<?php echo "admin.php?page=contests&id=$id&raceId=$race->id&action=results" ?>" method="post">

                        <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <?php if ($tovabbjutasos_verseny) : ?>
                                                    <th width="100">Továbbjutó</th>
                                                <?php endif ?>
                                                <th>Sportoló</th>
                                                <th width="180">Születési dátum</th>
                                                <th>Intézmény</th>
                                                <th width="100">Megjelent</th>
                                                <th width="150" style="cursor: pointer;" @click="orderData()">Helyezés<span :class="filterClass"></span></th> 
                                                <?php if ($result_type != 'helyezes') : ?>
                                                    <th width="150"><?php echo mertekegyseg($result_type) ?></th>
                                                <?php endif; ?>

                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($orderedAthletes as $disGroup => $genderList): ?>
                                            <?php foreach ($genderList as $gender => $list): ?>
                                                <tr id="row-<?php echo "$disGroup-$gender"; ?>"><td style="font-weight: bold;"><?php echo "$disGroup - $gender"; ?></td></tr>
                                                <?php
                                                    foreach ($list as $index => $athlete) :
                                                        $place = '';
                                                        $time = '';
                                                    ?>
                                                        <tr id="row-<?php echo $athlete->athlete_id; ?>">
                                                            <?php if ($tovabbjutasos_verseny) : ?>
                                                                <td><input type="checkbox" name="tovabbjutok[]" value="<?php echo $athlete->athlete_id; ?>" 
                                                                <?php
                                                                    if (count($tovabbjutok_lista) > 0) {
                                                                        echo "disabled ";
                                                                        echo in_array("$athlete->athlete_id", array_column($tovabbjutok_lista, "athlete_id")) ? "checked" : "";
                                                                    } else
                                                                        echo isset($tovabbjutok) && in_array("$athlete->athlete_id", $tovabbjutok) ? "checked" : "" ?> /></td>
                                                            <?php endif ?>
                                                            <?php $index = array_search($athlete->athlete_id, array_column($athletes, "athlete_id")) ?>
                                                            <td><?php echo $athlete->athlete_name;  ?></td>
                                                            <td><?php echo $athlete->birth_date;  ?></td>
                                                            <td><?php echo $athlete->ins_name;  ?></td>
                                                            <td>
                                                                <input type="checkbox" v-model="eredmenyData[<?php echo $index; ?>].megjelent" class="form-control input-sm result-field" autocomplete="off">
                                                            </td>
                                                            <td>
                                                                <input type="number" min="1" v-model="eredmenyData[<?php echo $index; ?>].helyezes" class="form-control input-sm result-field" autocomplete="off">
                                                            </td>
                                                            <?php if ($result_type != 'helyezes') : ?>
                                                                <?php if ($result_type == 'helyezes_ido_k' || $result_type == 'helyezes_ido_n') : ?>
                                                                    <td>
                                                                        <input type="time" step="0.001" v-model="eredmenyData[<?php echo $index; ?>].eredmeny" class="form-control input-sm result-field" autocomplete="off">
                                                                    </td>
                                                     
                                                                <?php else: ?>
                                                                    <td>
                                                                        <input type="number" step="0.001" min="0" id="row-<?php echo $athlete->athlete_id; ?>-eredmeny" v-model="eredmenyData[<?php echo $index; ?>].eredmeny" class="form-control input-sm result-field" autocomplete="off">
                                                                    </td>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                            
                                                        </tr>
                                                    <?php
                                                    endforeach;
                                                    ?>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>

                                            
                                        </tbody>
                                    </table>
                            
                            

                            <button class="btn btn-primary" type="button" @click="submit">Mentés</button>
                            <?php if ($result_type != 'helyezes') : ?>
                                <button class="btn btn-secundary mx-1" type="button" @click="calcPlace">Helyezés számitás</button>
                            <?php endif; ?>
                            <button class="btn btn-secundary" mx-1 type="button" @click="saveToExcel">Excel export</button>
                    </div>
                </div>
            </div>
        <?php
        endif;
        ?>
    </div>

</div>
<script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
<!-- <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script> -->
<script>
    const {
        createApp,
        ref
    } = Vue


    createApp({
        setup() {
            const resztvevok = ref(<?php echo json_encode($athletes) ?>)
            const verseny = ref(<?php echo json_encode($race) ?>)
            const results = ref(<?php echo $result_data->result ?>)
            const result_type = ref("<?php echo $result_type ?>")
            const eredmenyData = ref([])
            const contestTitle = "<?php echo $record->contest_name ?>";
            const eredmenyek = []
            resztvevok.value.forEach(f => {
                const megjelent = results.value?.find(f2 => f.athlete_id == f2.athlete_id)?.megjelent
                const helyezes = results.value?.find(f2 => f.athlete_id == f2.athlete_id)?.helyezes
                const eredmeny = results.value?.find(f2 => f.athlete_id == f2.athlete_id)?.eredmeny
                const type = "<?php echo $result_type ?>"
                eredmenyek.push({
                    ...f,
                    megjelent: typeof megjelent == 'string' ? (megjelent == 'true' ? true : false) : true,
                    helyezes: helyezes ? Number(helyezes) : null,
                    eredmeny: type == 'helyezes_ido_k' || type == 'helyezes_ido_n' ? eredmeny : (eredmeny ? Number(eredmeny) : null),
                })
            })
            eredmenyData.value = eredmenyek

            return {
                resztvevok,
                verseny,
                results,
                result_type,
                eredmenyData,
                contestTitle
            }
        },
        data(){
            return {
                filterClass: {
                    dashicons: true,
                    'dashicons-arrow-up-alt': false,
                    'dashicons-arrow-down-alt': false
                },
                orderedAthletes: <?php echo json_encode($orderedAthletes) ?>
            }
        },
        mounted(){

        },
        methods: {
            submit() {
                let isValid = true;
                this.eredmenyData.forEach(f => {
                    
                })
                if(!isValid) return

                jQuery.ajax({
                    type: "POST",
                    dataType: "json",
                    url: vitarex_vespa_ajaxurl,
                    data: {
                        action: "vespa_store_results",
                        contest_id: jQuery("#contest_id").val(),
                        race_id: jQuery("#race_id").val(),
                        result: this.eredmenyData.map(f => {
                            return {
                                athlete_id: f.athlete_id,
                                megjelent: f.megjelent,
                                helyezes: f.helyezes,
                                eredmeny: f.eredmeny
                            }
                        })
                    },
                    success: function(resp) {
                        if (resp.success) {
                            console.log('success')
                            if (resp.data.modal) {
                                document.body.insertAdjacentHTML('beforeend', resp.data.modal);
                                jQuery('#' + resp.data.modalId).modal('show');
                            }
                        }
                    }
                });
            },
            orderData(){
                let data = null
                if(this.filterClass['dashicons-arrow-up-alt']){ // sort desc
                    this.filterClass['dashicons-arrow-up-alt'] = false
                    this.filterClass['dashicons-arrow-down-alt'] = true
                    data = this.sortedByHelyezesArrayDesc   
                }         
                else if (this.filterClass['dashicons-arrow-down-alt']){ // default
                    this.filterClass['dashicons-arrow-up-alt'] = false
                    this.filterClass['dashicons-arrow-down-alt'] = false
                    data = this.orderedAthletes
                }
                else { // sort asc
                    this.filterClass['dashicons-arrow-up-alt'] = true
                    this.filterClass['dashicons-arrow-down-alt'] = false
                    data = this.sortedByHelyezesArrayAsc
                }
                Object.keys(data).forEach(disGrop => {
                    Object.keys(data[disGrop]).forEach(gender => {                   
                        const dataArr = data[disGrop][gender]      
                        let rowId = `${disGrop}-${gender}`
                        for (let index = 0; index < dataArr.length; index++) {
                            const current = dataArr[index];
                            jQuery(`#row-${rowId}`).after(jQuery(`#row-${current.athlete_id}`));
                            rowId = current.athlete_id
                        }               
                    })
                })    
            },
            calcPlace() {
                console.log('type', this.result_type)
                const data = this.eredmenyData.filter(f => f.eredmeny)
                let sortBy = ''
                switch (this.result_type) {
                    case 'helyezes_ido_k':
                    case 'helyezes_tav_k':
                    case 'helyezes_suly_k':
                        sortBy = 'k'
                        break;
                    case 'helyezes_ido_n':
                    case 'helyezes_tav_n':
                    case 'helyezes_suly_n':
                        sortBy = 'n'
                        break;
                    default:
                        break;
                }
                const sorted = sortBy == 'k' ? this.sortedArrayAsc : this.sortedArrayDesc
                this.eredmenyData.forEach(f => {
                    f.helyezes = null
                    if (!f.megjelent) f.eredmeny = null
                })

                Object.keys(sorted).forEach(disGrop => {
                    Object.keys(sorted[disGrop]).forEach(gender => {                   
                        const dataArr = sorted[disGrop][gender]
                        for (let index = 0; index < dataArr.length; index++) {
                            const element = dataArr[index];
                            this.eredmenyData.find(f => f.athlete_id == element.athlete_id).helyezes = index + 1
                        }               
                    })
                })    
            },
            async saveToExcel(){
                const HEADER_ROW = [
                    { value: "Verseny", fontWeight: "bold" },
                    { value: this.contestTitle, fontWeight: "bold" },   
                ];
                const HEADER_ROW_2 = [
                    { value: "Versenyszám", fontWeight: "bold" },
                    { value: `${this.verseny.sport_name} - ${this.verseny.sport_event_name ?? ''}` , fontWeight: "bold" },   
                ];
                const HEADER_ROW_3 = [
                    { value: "Diák", fontWeight: "bold" },
                    { value: "Születési dátum", fontWeight: "bold" },
                    { value: "Helyezés", fontWeight: "bold" },        
                ];
                if(this.result_type != 'helyezes'){
                    HEADER_ROW_3.push({ value: "Eredmény", fontWeight: "bold" })
                }
                const data = [HEADER_ROW, HEADER_ROW_2, HEADER_ROW_3];
                
                const orderedAthletes = JSON.parse(JSON.stringify(this.orderedAthletes));
                Object.keys(orderedAthletes).forEach(disGrop => {
                    Object.keys(orderedAthletes[disGrop]).forEach(gender => {
                        data.push([
                            { value: disGrop, fontWeight: "bold" },
                            { value: gender, fontWeight: "bold" },
                        ])
                        orderedAthletes[disGrop][gender] = orderedAthletes[disGrop][gender].forEach(ath => {
                            const item = this.eredmenyData.find(f2 => f2.athlete_id == ath.athlete_id)
                            const rowData = [
                                { type: String, value: ath.athlete_name },
                                { type: Date, value: new Date(ath.birth_date) },
                                { type: Number, value: item?.helyezes },       
                            ]
                            if(this.result_type != 'helyezes'){
                                if(this.result_type != 'helyezes_ido_k' || this.result_type != 'helyezes_ido_n'){
                                    rowData.push({ type: String, value: item.eredmeny }) 
                                }
                                else {
                                    rowData.push({ type: Number, value: item.eredmeny })
                                }
                            }
                            data.push(rowData);
                        })   
                    })
                }) 

                await writeXlsxFile(data, {
                    fileName: "export.xlsx",
                    dateFormat: "yyyy.mm.dd",
                    stickyRowsCount: 0,
                    sheet: "Eredmények",
                    columns: [
                        { width: 20 },
                        { width: 20 },
                        { width: 20 },
                        { width: 8 },
                        { width: 8 },
                    ],
                });
            }
        },
        computed: {
            sortedArrayAsc: function() {
                function compare(a, b) {
                    if (a.eredmeny < b.eredmeny)
                        return -1;
                    if (a.eredmeny > b.eredmeny)
                        return 1;
                    return 0;
                }

                const orderedAthletes = JSON.parse(JSON.stringify(this.orderedAthletes));
                Object.keys(orderedAthletes).forEach(disGrop => {
                    Object.keys(orderedAthletes[disGrop]).forEach(gender => {                   
                        orderedAthletes[disGrop][gender] = orderedAthletes[disGrop][gender].map(f => {
                            const eData = this.eredmenyData.find(f2 => f2.athlete_id == f.athlete_id)
                            return {
                                ...f,
                                ...eData
                            }
                        }).filter(f => !!f.eredmeny).sort(compare)             
                    })
                })         
                return orderedAthletes
            },
            sortedArrayDesc: function() {
                function compare(a, b) {
                    if (a.eredmeny < b.eredmeny)
                        return 1;
                    if (a.eredmeny > b.eredmeny)
                        return -1;
                    return 0;
                }

                const orderedAthletes = JSON.parse(JSON.stringify(this.orderedAthletes));
                Object.keys(orderedAthletes).forEach(disGrop => {
                    Object.keys(orderedAthletes[disGrop]).forEach(gender => {                   
                        orderedAthletes[disGrop][gender] = orderedAthletes[disGrop][gender].map(f => {
                            const eData = this.eredmenyData.find(f2 => f2.athlete_id == f.athlete_id)
                            return {
                                ...f,
                                ...eData
                            }
                        }).filter(f => !!f.eredmeny).sort(compare)             
                    })
                })         
                return orderedAthletes
            },
            sortedByHelyezesArrayAsc: function() {
                function compare(a, b) {
                    if (a.helyezes < b.helyezes)
                        return -1;
                    if (a.helyezes > b.helyezes)
                        return 1;
                    return 0;
                }

                const orderedAthletes = JSON.parse(JSON.stringify(this.orderedAthletes));
                Object.keys(orderedAthletes).forEach(disGrop => {
                    Object.keys(orderedAthletes[disGrop]).forEach(gender => {                   
                        const eredmeny = this.eredmenyData.filter(f => f.megjelent && f.helyezes)
                        orderedAthletes[disGrop][gender] = orderedAthletes[disGrop][gender].filter(f => eredmeny.find(f2 => f2.athlete_id == f.athlete_id)).map(f => {
                            const eData = eredmeny.find(f2 => f2.athlete_id == f.athlete_id)
                            return {
                                ...f,
                                ...eData
                            }
                        }).sort(compare)             
                    })
                })         
                return orderedAthletes
            },
            sortedByHelyezesArrayDesc: function() {
                function compare(a, b) {
                    if (a.helyezes < b.helyezes)
                        return 1;
                    if (a.helyezes > b.helyezes)
                        return -1;
                    return 0;
                }
                
                const orderedAthletes = JSON.parse(JSON.stringify(this.orderedAthletes));
                Object.keys(orderedAthletes).forEach(disGrop => {
                    Object.keys(orderedAthletes[disGrop]).forEach(gender => {                   
                        const eredmeny = this.eredmenyData.filter(f => f.megjelent && f.helyezes)
                        orderedAthletes[disGrop][gender] = orderedAthletes[disGrop][gender].filter(f => eredmeny.find(f2 => f2.athlete_id == f.athlete_id)).map(f => {
                            const eData = eredmeny.find(f2 => f2.athlete_id == f.athlete_id)
                            return {
                                ...f,
                                ...eData
                            }
                        }).sort(compare)             
                    })
                })         
                return orderedAthletes
            }
        }
    }).mount('#app')
</script>
<script src="https://unpkg.com/write-excel-file@1.x/bundle/write-excel-file.min.js"></script>