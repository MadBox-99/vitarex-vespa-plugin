<?php
global $wpdb;

$site_title = 'Új intézmény felvétele';
$id         = $_GET['id'];
$record     = null;

if (
    VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::TESTNEVELO) ||
    VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ISKOLAIGAZGATO)
) {
    $id =  get_user_meta(get_current_user_id(), 'school_id', true);

    if (!isset($id) || !is_numeric($id))
        die;
}

if (is_numeric($id) && $id > 0) {
    $record = $GLOBALS['VESPA_Institution']->load($id);
    $site_title = $record->ins_name . ' - szerkesztése';
}

$sql = 'SELECT * FROM vespa_states';
$states = $wpdb->get_results($sql);

$sql = 'SELECT * FROM vespa_disability_groups';
$disabilityGroups = $wpdb->get_results($sql);

$sql = 'SELECT * FROM vespa_school_districts';
$schoolDistrics = $wpdb->get_results($sql);

$sql = 'SELECT * FROM vespa_institution_disability_groups WHERE institution_id =%d' ;
$institutionDisablilitys = $wpdb->get_results($sql,$id);
?>

<style>
    .hidden .child {
        display: none;
    }
</style>

<div class="wrap">
    <div class="row">
        <div class="col-md-12">
            <h1 class="site-title"><?php echo $site_title; ?></h1>
        </div>
    </div>


    <div class="row">
        <div class="col-md-6">

            <form action="" class="ajax-form" method="POST">
                <input type="hidden" name="action" autocomplete="off" value="save_institution">
                <input type="hidden" name="institution_id" id="institution_id" autocomplete="off" value="<?php echo $id; ?>">

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Típus</label>
                        <select onchange="changeClass(this.value)" name="institution_type" id="institution_type" class="form-control input-sm">
                            <option value="iskola" <?php echo ($record == null || ($record != null && $record->institution_type == "iskola")  ? 'selected' : ''); ?>>
                                Iskola
                            </option>
                            <option value="egyesulet" <?php echo ($record != null && $record->institution_type == "egyesulet"  ? 'selected' : ''); ?>>
                                Egyesület
                            </option>
                        </select>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="form-group">
                        <label class="school">OM azonosító</label>
                        <label class="association">Nyilvántartási szám</label>
                        <input type="text" class="form-control" name="id_number" id="id_number" autocomplete="off" value="<?php echo ($record == null ? '' : $record->id_number); ?>">
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label class="school">Intézmény neve</label>
                        <label class="association">Egyesület neve</label>
                        <input type="text" class="form-control" name="ins_name" id="ins_name" autocomplete="off" value="<?php echo ($record == null ? '' : $record->ins_name); ?>">
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label class="school">Intézmény megye</label>
                        <label class="association">Egyesület megye</label>
                        <select name="ins_state" id="ins_state" class="form-control input-sm">
                            <?php foreach ($states as $state) : ?>
                                <option value="<?php echo $state->state_id ?>" <?php echo ($state != null && $record->ins_state == $state->state_id  ? 'selected' : ''); ?>>
                                    <?php echo $state->state_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label class="school">Intézmény irányítószám</label>
                        <label class="association">Egyesület irányítószám</label>
                        <input type="text" class="form-control" name="ins_zipcode" id="ins_zipcode" autocomplete="off" value="<?php echo ($record == null ? '' : $record->ins_zipcode); ?>">
                    </div>
                </div>


                <div class="col-md-6">
                    <div class="form-group">
                        <label class="school">Intézmény település</label>
                        <label class="association">Egyesület település</label>
                        <input type="text" class="form-control" name="ins_city" id="ins_city" autocomplete="off" value="<?php echo ($record == null ? '' : $record->ins_city); ?>">
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label class="school">Intézmény cím</label>
                        <label class="association">Egyesület cím</label>
                        <input type="text" class="form-control" name="ins_address" id="ins_address" autocomplete="off" value="<?php echo ($record == null ? '' : $record->ins_address); ?>">
                    </div>
                </div>

                <div class="col-md-12 school">
                    <div class="form-group">
                        <label>Tankerület azonosítója</label>
                        <select name="school_district_id" id="school_district_id" class="form-control input-sm">
                            <option value="0">Nincs kitöltve</option>
                            <?php foreach ($schoolDistrics as $schoolDistric) : ?>
                                <option value="<?php echo $schoolDistric->school_district_id ?>" <?php echo ($record != null && $record->school_district_id == $schoolDistric->school_district_id  ? 'selected' : ''); ?>>
                                    <?php echo $schoolDistric->school_district_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label>Központi email cím</label>
                        <input type="email" class="form-control" name="ins_email" id="ins_email" autocomplete="off" value="<?php echo ($record == null ? '' : $record->ins_email); ?>">
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label>Központi telefonszám</label>
                        <input type="text" class="form-control" name="ins_phone" id="ins_phone" autocomplete="off" value="<?php echo ($record == null ? '' : $record->ins_phone); ?>">
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label class="school">Intézményvezető neve</label>
                        <label class="association">Egyesületi vezető neve</label>
                        <input type="text" class="form-control" name="leader_name" id="leader_name" autocomplete="off" value="<?php echo ($record == null ? '' : $record->leader_name); ?>">
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label class="school">Intézményvezető email címe</label>
                        <label class="association">Egyesületi vezető email címe</label>
                        <input type="text" class="form-control" name="leader_email" id="leader_email" autocomplete="off" value="<?php echo ($record == null ? '' : $record->leader_email); ?>">
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label class="school">Intézményvezető telefonszáma</label>
                        <label class="association">Egyesületi vezető telefonszáma</label>
                        <input type="text" class="form-control" name="leader_phone" id="leader_phone" autocomplete="off" value="<?php echo ($record == null ? '' : $record->leader_phone); ?>">
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label class="school">Vespa felelős neve</label>
                        <label class="association">Nevezésért felelős neve</label>
                        <input type="text" class="form-control" name="vespaman_name" id="vespaman_name" autocomplete="off" value="<?php echo ($record == null ? '' : $record->vespaman_name); ?>">
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label class="school">Vespa felelős email címe</label>
                        <label class="association">Nevezésért felelős email címe</label>
                        <input type="text" class="form-control" name="vespaman_email" id="vespaman_email" autocomplete="off" value="<?php echo ($record == null ? '' : $record->vespaman_email); ?>">
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label class="school">Vespa felelős telefonszáma</label>
                        <label class="association">Nevezésért felelős telefonszáma</label>
                        <input type="text" class="form-control" name="vespaman_phone" id="vespaman_phone" autocomplete="off" value="<?php echo ($record == null ? '' : $record->vespaman_phone); ?>">
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label class="school">Testnevelő neve és elérhetősége</label>
                        <label class="association">Edzők neve, elérhetőségeik</label>
                        <textarea class="form-control" name="trainers_data" id="trainers_data" autocomplete="off"><?php echo ($record == null ? '' : $record->trainers_data); ?></textarea>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label class="school">Iskola diákjainak száma</label>
                        <label class="association">Sportolók száma</label>
                        <input type="number" min="0" class="form-control" name="numberof_students" id="numberof_students" autocomplete="off" value="<?php echo ($record == null ? '' : $record->numberof_students); ?>">
                    </div>
                </div>

                <div class="col-md-12 association">
                    <div class="form-group ">
                        <label>Tagja-e a Fovesznek</label>
                        <input type="checkbox" class="form-control" name="member_of_fodesz" id="member_of_fodesz" checked=<?php echo (isset($record->member_of_fodesz) ? 'checked' : ''); ?>>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label>Fogyatékossági csoportok létszámmegoszlása</label>
                    </div>
                </div>

                <?php
                $searchedIndex = null;
                foreach ($disabilityGroups as $dGroup) :

                    if ($institutionDisablilitys != null) {
                        $searchedIndex = array_search($dGroup->disability_group_id, array_column($institutionDisablilitys, 'disability_group_id'));
                    }
                ?>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><?php echo ($dGroup->disability_group_name); ?></label>
                            <input type="number" min="0" class="form-control" name="<?php echo ('group_' . $dGroup->disability_group_id); ?>" id="<?php echo ('group_' . $dGroup->disability_group_id); ?>" value="<?php echo (is_numeric($searchedIndex) ? $institutionDisablilitys[$searchedIndex]->numberof : 0); ?>">
                            <input type="hidden" name="<?php echo ('group_' . $dGroup->disability_group_id) . '_id' ?>" id="<?php echo ('group_' . $dGroup->disability_group_id) . '_id' ?>" value="<?php echo (is_numeric($searchedIndex) ? $institutionDisablilitys[$searchedIndex]->vidg_id : 0); ?>">
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">Mentés</button>
                    <a href="#" onclick="history.back();" class="btn btn-cancel">Mégsem</a>
                </div>
            </form>

        </div>
    </div>

</div>
<script>
    jQuery(document).ready(function() {
        if (jQuery('#institution_type').find(":selected").attr('value') == 'iskola') {
            jQuery('.association').addClass('hidden');
        } else {
            jQuery('.school').addClass('hidden');
        }
    });

    function changeClass(val) {
        jQuery('.school').removeClass('hidden')
        jQuery('.association').removeClass('hidden')
        if (val == 'iskola') {
            jQuery('.association').addClass('hidden');
        } else {
            jQuery('.school').addClass('hidden');
        }
    }
</script>