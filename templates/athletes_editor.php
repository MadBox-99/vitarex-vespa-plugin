<?php
global $wpdb;

$site_title = 'Új sportoló felvétele';
$id         = $_GET['id'];
$record     = null;
$school     = null;
$schoolId   = null;
$schoolName = null;
$sql = "SELECT * FROM vespa_institutions ";

if (!(VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::FOVESZ_FODISZ_SPORTIGAZGATO) ||
    VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ADMINISZTRATOR) ||
    is_super_admin())) {
    $schoolId = get_user_meta(get_current_user_id(), 'school_id', true);
    $sql = $sql . "WHERE institution_id=%d";
}

if (is_numeric($id) && $id > 0) {
    $record = $GLOBALS['VESPA_Athletes']->load($id);
    //print_r($record);
    $site_title = $record->athlete_name . ' - szerkesztése';
    if (!is_null($schoolId)) {
        if ($record->school_id != $schoolId) {
            echo '<h1 class="site-title">Nincs jogosultsága ehhez az oldalhoz!</h1>';
            die;
        }
    }
}

$institutions = $wpdb->get_results($wpdb->prepare($sql, $schoolId));


if (!is_null($schoolId)) {
    foreach ($institutions as $ins) {
        if ($ins->institution_id == $schoolId) {
            $schoolName = $ins->ins_name;
            break;
        }
    }
}

$instarray = array();
foreach ($institutions as $institution) {
    if ($record != null && $record->school_id == $institution->institution_id) {
        $school = $institution;
    }

    $element = array(
        'value' => $institution->institution_id,
        'label' => $institution->ins_name,
    );
    $instarray[] = $element;
}

$sql2 = 'SELECT * FROM vespa_disability_groups';
$disabilities = $wpdb->get_results($sql2);
?>



<?php
if (!current_user_can(VESPA_Roles::sportolok_letrehozasa_modositasa)) :
?>
    <div class="wrap">
        <div class="row">
            <div class="col-md-12">
                <h1 class="site-title">Nincs jogosultsága ehhez az oldalhoz!</h1>
            </div>
        </div>
    </div>
<?php
    exit;
endif;
?>



<div class="wrap">
    <div class="row">
        <div class="col-md-12">
            <h1 class="site-title"><?php echo $site_title; ?></h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <h3>Alapadatok</h3>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <form action="" class="ajax-form" method="POST">
                <input type="hidden" name="action" autocomplete="off" value="save_athletes">
                <input type="hidden" name="athlete_id" id="athlete_id" autocomplete="off" value="<?php echo $id; ?>">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Sportoló neve <span class="redstar">*</span></label>
                        <input type="text" class="form-control" name="athlete_name" id="athlete_name" autocomplete="off" value="<?php echo ($record == null ? '' : $record->athlete_name); ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Anyja neve <span class="redstar">*</span></label>
                        <input type="text" class="form-control" name="mothers_name" id="mothers_name" autocomplete="off" value="<?php echo ($record == null ? '' : $record->mothers_name); ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Születési hely <span class="redstar">*</span></label>
                        <input type="text" class="form-control" name="birth_place" id="birth_place" autocomplete="off" value="<?php echo ($record == null ? '' : $record->birth_place); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Születési idő <span class="redstar">*</span></label>
                        <input type="text" class="form-control birth-dpicker" name="birth_date" id="birth_date" autocomplete="off" value="<?php echo ($record == null ? '' : $record->birth_date); ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Iskola / egyesület <span class="redstar">*</span></label>

                        <input type="hidden" class="form-control" name="school_id" id="school_id" value="<?php echo ($record == null ? (is_null($schoolId) ?  '' : $schoolId) : $record->school_id); ?>">
                        <input type="text" class="form-control" name="school_name" id="school_name" <?php echo is_null($schoolId) ?  '' : 'disabled' ?> value="<?php echo ($school == null ? (is_null($schoolId) ?  '' : $schoolName) : $school->ins_name); ?>">
                    </div>
                </div>

                <script>
                    var institutions = <?php echo json_encode($instarray) ?>;

                    jQuery(document).ready(function() {
                        jQuery("#school_name").autocomplete({
                            source: institutions,
                            select: function(event, ui) {
                                event.preventDefault();

                                jQuery('#school_id').val(ui.item.value);
                                jQuery('#school_name').val(ui.item.label);
                            }
                        });
                    });
                </script>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Aktív</label>
                        <select name="active" id="active" class="form-control input-sm">
                            <?php if ($record != null && $record->active == null) : ?> // mivel a DB enged null-t, inkább megjelenítem, esetleges import hibák miatt
                                <option value="">
                                    Válasszon!
                                </option>
                            <?php endif ?>
                            <option value="1" <?php echo ($record != null && $record->active == 1 ? 'selected' : ''); ?>>
                                Igen
                            </option>
                            <option value="0" <?php echo ($record != null && $record->active != null && $record->active == 0 ? 'selected' : ''); ?>>
                                Nem
                            </option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <h3>Kapcsolati adatok</h3>
                    </div>
                </div>


                <div class="col-md-6">
                    <div class="form-group">
                        <label>Telefonszám</label>
                        <input type="text" pattern="(\+{0,1}[0-9]+)" title="A telefonszámok csak számokat tartalmazhatnak, illetve kezdődhet 1 darab + jellel." class="form-control" name="phone" id="phone" autocomplete="off" value="<?php echo ($record == null ? '' : $record->phone); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="text" class="form-control" name="email" id="email" autocomplete="off" value="<?php echo ($record == null ? '' : $record->email); ?>">
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>Irányítószám</label>
                        <input type="text" class="form-control" name="home_zipcode" id="home_zipcode" autocomplete="off" value="<?php echo ($record == null ? '' : $record->home_zipcode); ?>">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label>Település</label>
                        <input type="text" class="form-control" name="home_city" id="home_city" autocomplete="off" value="<?php echo ($record == null ? '' : $record->home_city); ?>">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label>Lakcím</label>
                        <input type="text" class="form-control" name="home_address" id="home_address" autocomplete="off" value="<?php echo ($record == null ? '' : $record->home_address); ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <h3>Személyi adatok</h3>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Állampolgárság</label>
                        <input type="text" class="form-control" name="nationality" id="nationality" autocomplete="off" value="<?php echo ($record == null ? '' : $record->nationality); ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Nyilvántartásba vétele</label>
                        <input type="text" class="form-control dpicker" name="registered_at" id="registered_at" autocomplete="off" value="<?php echo ($record == null ? '' : $record->registered_at); ?>">
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Személyiszám/Útlevélszám</label>
                        <input type="text" class="form-control" name="personal_id" id="personal_id" autocomplete="off" value="<?php echo ($record == null ? '' : $record->personal_id); ?>">
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Nem (férfi/nő) <span class="redstar">*</span></label>
                        <select name="gender" id="gender" class="form-control">
                            <?php
                            $selected = fn ($gender) => $record != null && $record->gender == $gender ? "value='$gender' selected" : "value='$gender'";
                            echo "<option " . $selected(null) . " >nincs kitöltve</option>";
                            echo "<option " . $selected('férfi') . ">férfi</option>";
                            echo "<option " . $selected('nő') . ">nő</option>";
                            ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Fogyatékosság típusa</label>
                        <select name="disability_type" id="disability_type" class="form-control input-sm">
                            <?php foreach ($disabilities as $disability) : ?>
                                <option value="<?php echo $disability->disability_group_id ?>" <?php echo ($record != null && $record->disability_type == $disability->disability_group_id  ? 'selected' : ''); ?>>
                                    <?php echo $disability->disability_group_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <h3>Megjegyzés</h3>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label>Publikus megjegyzés</label>
                        <textarea class="form-control" name="note" id="note" autocomplete="off"><?php echo ($record == null ? '' : $record->note); ?></textarea>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Privát megjegyzés</label>
                        <textarea class="form-control" name="private_note" id="private_note" autocomplete="off"><?php echo ($record == null ? '' : $record->private_note); ?></textarea>
                    </div>
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">Mentés</button>
                    <a href="#" onclick="history.back();" class="btn btn-cancel">Mégsem</a>
                </div>
            </form>

        </div>
    </div>

</div>