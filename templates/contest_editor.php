<?php
global $wpdb;

$site_title = 'Új verseny felvétele';
$id         = isset($_GET['id']) ? intval($_GET['id']) : 0;
$record     = null;
$groups = array(6);

if (is_numeric($id) && $id > 0) {
    $record = $GLOBALS['VESPA_Contests']->load($id);
    $site_title = stripslashes($record->contest_name) . ' - szerkesztése';

    $groups_temp = $wpdb->get_results($wpdb->prepare("SELECT * FROM vespa_contest_agegroups WHERE contest_id=%d ORDER BY agegroup_id ASC", $id));
    foreach ($groups_temp as $gt) {
        $groups[$gt->agegroup_id - 1] = $gt;
    }
}

?>


<div class="wrap">
    <div class="row">
        <div class="col-md-12">
            <h1 class="site-title"><?php echo $site_title; ?></h1>
        </div>
    </div>


    <form action="" class="ajax-form row" method="POST">
        <input type="hidden" name="action" autocomplete="off" value="save_contests">
        <input type="hidden" name="contest_id" id="contest_id" autocomplete="off" value="<?php echo $id; ?>">

        <div class="col-md-12">
            <h3>Alapadatok</h3>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <label>Megnevezés</label>
                <input type="text" class="form-control" name="contest_name" id="contest_name" autocomplete="off" value='<?php echo ($record == null ? '' : esc_attr(stripslashes($record->contest_name))); ?>'>
            </div>
        </div>

        <div class="col-md-6" style="display:none;">
            <div class="form-group">
                <label>Verseny lebonyolítás</label>

                <select name="contest_subtypes" id="contest_subtypes" class="form-control">
                    <?php
                    $list = $wpdb->get_results("SELECT * FROM vespa_contest_subtypes WHERE contest_subtype_id = 2 OR contest_subtype_id = 3"
                            );

                    foreach ($list as $item) {
                        $selected = ($record != null && $record->contest_subtypes == $item->contest_subtype_id ? 'selected' : '');
                        echo '<option value="' . $item->contest_subtype_id . '" ' . $selected . '>' . $item->contest_subtype_name . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Sorozat</label>

                <select name="contest_series" id="contest_series" class="form-control">

                    <?php
                    $list = $wpdb->get_results("SELECT * FROM vespa_series ORDER BY series_id DESC");
                    foreach ($list as $item) {
                        $selected = ($record != null && $record->contest_series == $item->series_id ? 'selected' : '');
                        echo '<option value="' . $item->series_id . '" ' . $selected . '>' . $item->series_name . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Rendező</label>
                <input type="text" class="form-control" name="organiser" id="organiser" autocomplete="off" value="<?php echo ($record == null ? '' : esc_attr($record->organiser)); ?>">
            </div>
        </div>

        <div class="col-md-2">
            <div class="form-group">
                <label>Típus</label>
                <select name="contest_types" id="contest_types" class="form-control">
                    <?php
                    $list = $wpdb->get_results("SELECT * FROM vespa_contest_types ORDER BY contest_type_name ASC");

                    foreach ($list as $item) {
                        $selected = ($record != null && $record->contest_type == $item->contest_type_id) ? 'selected' : '';
                        echo '<option value="' . $item->contest_type_id . '" ' . $selected . '>' . $item->contest_type_name . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>Megye</label>

                <select name="state_id" id="state_id" class="form-control">
                    <option value="0">Nincs kitöltve</option>
                    <?php
                    $list = $wpdb->get_results("SELECT * FROM vespa_states ORDER BY state_name ASC");
                    foreach ($list as $item) {
                        $selected = ($record != null && $record->state_id == $item->state_id ? 'selected' : '');
                        echo '<option value="' . $item->state_id . '" ' . $selected . '>' . $item->state_name . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>


        <div class="col-md-12">
            <div class="form-group">
                <label>Helyszín neve</label>
                <input type="text" class="form-control" name="place_name" id="place_name" autocomplete="off" value="<?php echo ($record == null ? '' : esc_attr($record->place_name)); ?>">
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <label>Helyszín címe</label>
                <input type="text" class="form-control" name="place_address" id="place_address" autocomplete="off" value="<?php echo ($record == null ? '' : esc_attr($record->place_address)); ?>">
            </div>
        </div>

        <!--
            <div class="col-md-12">
                <div class="form-group">
                    <label>Kiírás</label>
                    <input type="file" name="listing_file">
                </div>
            </div>             

            <div class="col-md-12">
                <div class="form-group">
                    <label>Logó</label>
                    <input type="file" name="logo_file">
                </div>
            </div>                         
-->

        <div class="col-md-12">
            <h3>Határidők, létszámok</h3>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Verseny kezdete</label>
                <input type="text" class="form-control dtpicker" name="start_at" id="start_at" autocomplete="off" value="<?php echo ($record == null ? '' : $record->start_at); ?>">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>vége</label>
                <input type="text" class="form-control dtpicker" name="end_at" id="end_at" autocomplete="off" value="<?php echo ($record == null ? '' : $record->end_at); ?>">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Nevezés kezdete</label>
                <input type="text" class="form-control dtpicker" name="school_entry_start_at" id="school_entry_end_at" autocomplete="off" value="<?php echo ($record == null ? '' : $record->school_entry_start_at); ?>">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Nevezés vége</label>
                <input type="text" class="form-control dtpicker" name="school_entry_end_at" id="school_entry_end_at" autocomplete="off" value="<?php echo ($record == null ? '' : $record->school_entry_end_at); ?>">
            </div>
        </div>

        <div class="col-md-6" id="ppl_num_min_line">
            <div class="form-group">
                <label>Sportolók min. létszám per iskola</label>
                <input type="text" class="form-control" name="ppl_num_min" id="ppl_num_min" autocomplete="off" value="<?php echo ($record == null ? '' : $record->ppl_num_min); ?>">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label id="ppl_num_max_line">Sportolók max. létszám per iskola</label>
                <input type="text" class="form-control" name="ppl_num_max" id="ppl_num_max" autocomplete="off" value="<?php echo ($record == null ? '' : $record->ppl_num_max); ?>">
            </div>
        </div>

        <div class="col-md-6" id="ppl_num_extra_max_line">
            <div class="form-group">
                <label>Kísérők max. létszáma per iskola</label>
                <input type="text" class="form-control" name="ppl_num_extra_max" id="ppl_num_extra_max" autocomplete="off" value="<?php echo ($record == null ? '' : $record->ppl_num_extra_max); ?>">
            </div>
        </div>
        <div class="col-md-6" id="qualifier_num_max_line">
            <div class="form-group">
                <label>Továbbjutók max. száma per versenyszám</label>
                <input type="text" class="form-control" name="qualifier_num_max" id="qualifier_num_max" autocomplete="off" value="<?php echo ($record == null ? '' : $record->qualifier_num_max); ?>">
            </div>
        </div>


        <div class="col-md-12">
            <h3>Korosztályok</h3>


            <table class="table table-striped">
                <?php
                $labels = array('I.', 'II.', 'III.', 'IV.', 'V.', 'VI.');
                for ($i = 1; $i <= 6; $i++) :
                    $group = null;
                    if (isset($groups[($i - 1)])) {
                        $group = $groups[($i - 1)];
                    }
                ?>
                    <tr>
                        <td width="150" style="text-align:right;vertical-align:middle;"><?php echo $labels[($i - 1)]; ?>:</td>

                        <td width="120">
                            <input type="text" class="form-control" name="ag<?php echo $i; ?>_from" autocomplete="off" value="<?php echo ($group != null ? substr($group->date_from, 0, 4) : ''); ?>">
                        </td>
                        <td width="70" style="vertical-align:middle;">.01.01 - </td>

                        <td width="120">
                            <input type="text" class="form-control" name="ag<?php echo $i; ?>_to" autocomplete="off" value="<?php echo ($group != null ? substr($group->date_to, 0, 4) : ''); ?>">
                        </td>
                        <td width="70" style="vertical-align:middle;">.12.31.</td>
                        <td>&nbsp;</td>
                    </tr>
                <?php
                endfor;
                ?>
            </table>
        </div>

        <div class="col-md-12">
            <h3>Kiírás - extra szöveg</h3>

            <textarea name="listing_text" id="listing_text" class="editor form-control" rows="10"><?php echo isset($record->listing_text) ? wp_kses_post(stripslashes($record->listing_text)) : ''; ?></textarea>

        </div>

        <div class="col-md-12" style="margin-top:30px">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="#" onclick="history.back();" class="btn btn-cancel">Mégsem</a>
        </div>
    </form>


</div>

<script>
    // szabadidősport beállitása a másik mezőn is tipus vagy lebonyolitás választásnál, ha szabadidősport kerül kiválasztásra
    jQuery('#contest_types').on('change', function(e) {
        const contestSubtype = jQuery('#contest_subtypes').val()
        const contestSeries = jQuery('#contest_series').val()
        if(this.value == 4 && (contestSubtype != 3 || contestSeries != 1)){
            jQuery('#contest_subtypes').val(3)
            jQuery('#contest_series').val(1)
            updateVisibility(contestType)
        }
    });
    jQuery('#contest_subtypes').on('change', function(e) { 
        const contestType = jQuery('#contest_types').val()
        const contestSeries = jQuery('#contest_series').val()
        if(this.value == 3 && (contestType != 4 || contestSeries != 1)){
            jQuery("#contest_types").val(4)
            jQuery('#contest_series').val(1)
            updateVisibility(contestType)
        }
    });
    jQuery('#contest_series').on('change', function(e) { 
        const contestType = jQuery('#contest_series').val()
        const contestSubtype = jQuery('#contest_subtypes').val()
        if(this.value == 1 && (contestType != 4 || contestSubtype != 3)){
            jQuery("#contest_types").val(4);
            jQuery("#contest_subtypes").val(3);
            updateVisibility(contestType)
        }
    });
    function updateVisibility(type){
        if(type == 4){
            jQuery('#ppl_num_min_line').hide()
            jQuery('#ppl_num_extra_max_line').hide()
            jQuery('#qualifier_num_max_line').hide()
            jQuery('#ppl_num_max_line').text("Sportolók max. létszám") 
        }
        else {
            jQuery('#ppl_num_min_line').show()
            jQuery('#ppl_num_extra_max_line').show()
            jQuery('#qualifier_num_max_line').show()
            jQuery('#ppl_num_max_line').text("Sportolók max. létszám per iskola") 
        }
    }
    // oldal betöltésnél a mezők és szövegek beállitás
    jQuery(function() {
        const contestType = jQuery('#contest_types').val()
        updateVisibility(contestType)
    });
</script>