<?php
global $wpdb;

if (!isset($_GET['id'])) {
    wp_redirect(admin_url('admin.php?page=vespa_contests'));
    exit;
}

$id         = $_GET['id'];
$record     = null;
$model     = null;

if (is_numeric($id) && $id > 0) {
    date_default_timezone_set('Europe/Budapest');
    $record = $GLOBALS['VESPA_Contests']->load($id);

    $model = new VespaContest($record);

    $site_title = stripslashes($record->contest_name) . ' - adatlap';

    $record = $GLOBALS['VESPA_Contests']->load($id);

    $subtype = $GLOBALS['VESPA_Contest_Subtypes']->load($record->contest_subtypes);
    $series = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_series WHERE series_id= %d", $record->contest_series));

    $type = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_contest_types WHERE contest_type_id= %d", $record->contest_type));

    $age_groups = $wpdb->get_results($wpdb->prepare("SELECT * FROM vespa_contest_agegroups WHERE contest_id=%d ORDER BY agegroup_id ASC", $id));

    $date = date('Y-m-d H:i:s');
    if (
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ISKOLAIGAZGATO) ||
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::TESTNEVELO)
    ) {
        $schoolId = get_user_meta(get_current_user_id(), 'school_id', true);
        $school = $wpdb->get_row($wpdb->prepare("SELECT * FROM vespa_institutions WHERE institution_id=%d",$schoolId));
        if (
            ($record->contest_type == 3 && $school->ins_state != $record->state_id) ||
            $record->end_at <= $date
        ){
            echo '<h1 class="site-title">Nincs jogosultsága ehhez az oldalhoz!</h1>';
            die;
        }
    }

    if (
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::MEGYEI_VEZETO) ||
        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::MEGYEI_VERSENYIGAZGATO)
    ) {
        $stateId = get_user_meta(get_current_user_id(), 'state_id', true);
        if ($record->contest_type == 3 && $stateId != $record->state_id) {
            echo '<h1 class="site-title">Nincs jogosultsága ehhez az oldalhoz!</h1>';
            die;
        }
    }

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

?>
<input type="hidden" name="contest_id" id="contest_id" autocomplete="off" value="<?php echo $id; ?>">

<div class="wrap">
    <div class="row">
        <div class="col-md-12">
            <h1 class="site-title"><?php echo $site_title; ?></h1>
        </div>
    </div>

    <div class="row" style="margin-bottom:30px;">
        <div class="col-md-10">
            <a href="#alapadatok" class="btn btn-default btn-sm">Alapadatok</a>
            <a href="#hataridok" class="btn btn-default btn-sm">Határidők, létszámok</a>
            <a href="#korosztalyok" class="btn btn-default btn-sm">Korosztályok</a>
            <a href="#versenyszamok" class="btn btn-default btn-sm">Versenyszámok</a>

            <!--            
            <a href="#jelentkezok" class="btn btn-default btn-sm">Nevezettek</a>
-->
            <?php if (current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles)) : ?>
                <a href="<?php echo admin_url('admin.php?page=contests&action=question&id=') . $id; ?>" class="btn btn-default btn-sm">
                    <?php echo vespa_contest_has_answers($id) ? 'Beszámoló szerkesztése' : 'Beszámoló rögzítése'; ?>
                </a>
            <?php endif; ?>

        </div>

        <div class="col-md-2">
            <select id="doc_downloader" class="form-control input-sm" data-url="<?php echo home_url('/'); ?>?download_contest_docs=">
                <option value="">Dokumentum letöltése</option>

                <option value="listing">Kiírás</option>

                <?php if (vespa_contest_has_answers($id)) : ?>
                    <option value="answers">Beszámoló</option>
                <?php endif; ?>

                
                <?php if (
                        VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::MEGYEI_VERSENYIGAZGATO)
                        || VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::FOVESZ_FODISZ_SPORTIGAZGATO)
                        || VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ADMINISZTRATOR)
                        || is_super_admin()
                    )  : ?>
                    <option value="logistic">Sportolói logisztika</option>
                    <option value="emails">Nevezettek email lista</option>
                <?php endif; ?>    

                <?php if (!VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::TESTNEVELO)) : ?>
                    <option value="athletes">Nevezési lista</option>
                <?php endif; ?>


                <?php if (VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::TESTNEVELO)) : ?>
                    <option value="medical_approval">Orvosi engedély</option>
                <?php endif; ?>
            </select>
        </div>
    </div>


    <div class="vespa-box" id="alapadatok">
        <div class="row">
            <div class="col-md-6">
                <h3 style="margin-top:0">Alapadatok</h3>
            </div>
            <div class="col-md-6">
                <?php if (current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles) && $record->is_final == 0) : ?>
                    <a href="<?php echo admin_url('admin.php?page=contests&id=') . $id; ?>" class="btn btn-default pull-right">Szerkesztés</a>
                <?php endif ?>
                <?php if (current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles) && $record->is_final == 1) : ?>
                    <a href="#" class="btn btn-default pull-right" onclick="reopenContest(event,'<?php echo wp_get_current_user()->display_name ?>')">Szerkeszthetővé tesz</a>
                <?php endif; ?>
            </div>

            <div class="col-md-12">
                <table class="table table-striped">
                     <!--  <tr>
                        <td width="250"><strong>Verseny lebonyolítás:</strong></td>
                        <td>
                            <?php
                            echo $subtype->contest_subtype_name . ' / ';
                            echo $series->series_name;
                            ?>
                        </td>
                    </tr> -->

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

                    <tr>
                        <td><strong>Kiírás:</strong></td>
                        <td>
                            <a href="<?php echo home_url(); ?>?download_contest_docs=listing&contest_id=<?php echo $record->contest_id; ?>" download="">Letöltés</a>
                        </td>
                    </tr>

                    <?php
                    $disablityList = $wpdb->get_results("SELECT * FROM vespa_disability_groups");

                    $races = $wpdb->get_results($wpdb->prepare("SELECT * FROM vespa_constest_events WHERE contest_id=%d",$record->contest_id));
                    

                    $disablities = [];
                    foreach ($races as $row) {
                        foreach (explode(',', $row->disability_groups) as $group) {
                            array_push($disablities, $group);
                        }
                    }
                    $disablities = array_unique($disablities);

                    function mapIdsToNames2($splitString, $objects)
                    {
                        $result = [];
                        foreach ($splitString as $id) {
                            foreach ($objects as $object) {
                                if ($object->disability_group_id == $id) {
                                    $result[] = $object->disability_group_name;
                                    break;
                                }
                            }
                        }
                        return $result;
                    }
                    ?>
                    <tr>
                        <td><strong>Fogyatékossági csoportok:</strong></td>
                        <td><?php foreach (mapIdsToNames2($disablities, $disablityList) as $elem) echo "$elem ";  ?></td>
                    </tr>


                </table>

            </div>
        </div>
    </div>



    <div class="vespa-box" id="hataridok">
        <div class="row">
            <div class="col-md-12">
                <h3 style="margin-top:0">Határidők, létszámok</h3>

                <table class="table table-striped">
                    <tr>
                        <th></th>
                        <th>Kezdete</th>
                        <th>Vége</th>
                        <th></th>
                    </tr>
                    <tr>
                        <td width="250"><strong>Verseny időtartama:</strong></td>
                        <td width="200"><?php echo $record->start_at; ?></td>
                        <td width="200"><?php echo $record->end_at; ?></td>
                        <td>&nbsp;</td>
                    </tr>

                    <tr>
                        <td><strong>Nevezés időtartama:</strong></td>
                        <td><?php echo $record->school_entry_start_at; ?></td>
                        <td><?php echo $record->school_entry_end_at; ?></td>
                        <td>&nbsp;</td>
                    </tr>
                    <tr>
                        <th></th>
                        <th>Minimum</th>
                        <th>Maximum</th>
                        <th></th>
                    </tr>
                    <?php if($record->contest_type == 4): ?>
                    <tr>
                        <td><strong>Sportolói létszám:</strong></td>
                        <td>&nbsp;</td>
                        <td><?php echo $record->ppl_num_max; ?></td>
                        <td>&nbsp;</td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td><strong>Sportolói létszám per intézmény:</strong></td>
                        <td><?php echo $record->ppl_num_min; ?></td>
                        <td><?php echo $record->ppl_num_max; ?></td>
                        <td>&nbsp;</td>
                    </tr>
                    <?php endif; ?>
                    <?php if($record->contest_type != 4): ?>
                    <tr>
                        <td><strong>Kísérők max. létszáma per intézmény:</strong></td>
                        <td>&nbsp;</td>
                        <td><?php echo $record->ppl_num_extra_max; ?></td>
                        <td>&nbsp;</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>


    <div class="vespa-box" id="korosztalyok">
        <div class="row">
            <div class="col-md-12">
                <h3 style="margin-top:0">Korosztályok</h3>

                <table class="table table-striped">
                    <?php
                    $labels = array('I.', 'II.', 'III.', 'IV.', 'V.', 'VI.');

                    foreach ($age_groups as $group) :
                    ?>
                        <tr>
                            <td width="50" style="text-align:right"><?php echo $labels[($group->agegroup_id - 1)]; ?>:</td>
                            <td><?php echo $group->date_from; ?> - <?php echo $group->date_to; ?></td>
                        </tr>
                    <?php
                    endforeach;
                    ?>
                </table>

            </div>
        </div>
    </div>


    <!-- =========================================== -->
    <?php

    if ($record->is_final == 0) {
        require_once VITAREX_VESPA_PLUGIN_DIR . '/templates/contest_view_add_events.php';
        //vespa_load_template('contest_view_add_events.php' );
    } else {
        /*
                ha testnevelő akkor
                    - ha még lehet nevezni akkor nevezhet
                    - ha már nevezett akkor módosíthatja az adatait        
    
                ha admin user akkor
                    - listázni a nevezetteket a versenyekre   
            */

        if (
            VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::TESTNEVELO)
            || VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ISKOLAIGAZGATO)
        ) {
            require_once VITAREX_VESPA_PLUGIN_DIR . '/templates/contest_view_entering.php';
        } else {
            require_once VITAREX_VESPA_PLUGIN_DIR . '/templates/contest_view_racelist.php';
        }
    }

    ?>










    <!-- =========================================== -->
</div>