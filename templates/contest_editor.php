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
                <?php if ($record == null) : ?>
                    <p class="description" style="margin-top:5px;">A megnevezés automatikusan kitöltésre kerül, amennyiben ettől a névtől eltérőt szeretne, kérjük csak abban az esetben töltse ki a mezőt!</p>
                <?php endif; ?>
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
                <?php
                // meglévő rendező-értékek lekérdezése legördülőhöz (statikus oszlopnév, nincs user input)
                $organiser_options  = $wpdb->get_col("SELECT DISTINCT organiser FROM vespa_contests WHERE organiser IS NOT NULL AND organiser <> '' ORDER BY organiser ASC");
                $organiser_value    = ($record == null ? '' : $record->organiser);
                $organiser_in_list  = ($organiser_value !== '' && in_array($organiser_value, $organiser_options, true));
                ?>
                <select id="organiser_select" class="form-control">
                    <option value="">— válassz —</option>
                    <?php foreach ($organiser_options as $organiser_option) : ?>
                        <option value="<?php echo esc_attr($organiser_option); ?>" <?php echo ($organiser_in_list && $organiser_option === $organiser_value ? 'selected' : ''); ?>><?php echo esc_html($organiser_option); ?></option>
                    <?php endforeach; ?>
                    <option value="__uj__" <?php echo (!$organiser_in_list && $organiser_value !== '' ? 'selected' : ''); ?>>+ Új érték megadása…</option>
                </select>
                <input type="text" class="form-control" name="organiser" id="organiser" autocomplete="off" style="margin-top:5px;<?php echo ($organiser_in_list || $organiser_value === '' ? 'display:none;' : ''); ?>" value="<?php echo esc_attr($organiser_value); ?>">
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

        <?php if ($record == null) : ?>
        <div class="col-md-4">
            <div class="form-group">
                <label>Sportág (a névhez)</label>
                <select id="sportag_nev" class="form-control">
                    <option value="">— válassz —</option>
                    <?php
                    $sport_list = $wpdb->get_results("SELECT * FROM vespa_sports WHERE is_deleted=0 ORDER BY sport_name ASC");
                    foreach ($sport_list as $sport_item) {
                        echo '<option value="' . esc_attr($sport_item->sport_name) . '">' . esc_html($sport_item->sport_name) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>

        <?php endif; ?>

        <div class="col-md-12">
            <div class="form-group">
                <label>Helyszín neve</label>
                <?php
                // meglévő helyszín-értékek lekérdezése legördülőhöz (statikus oszlopnév, nincs user input)
                $place_name_options  = $wpdb->get_col("SELECT DISTINCT place_name FROM vespa_contests WHERE place_name IS NOT NULL AND place_name <> '' ORDER BY place_name ASC");
                $place_name_value    = ($record == null ? '' : $record->place_name);
                $place_name_in_list  = ($place_name_value !== '' && in_array($place_name_value, $place_name_options, true));
                ?>
                <select id="place_name_select" class="form-control">
                    <option value="">— válassz —</option>
                    <?php foreach ($place_name_options as $place_name_option) : ?>
                        <option value="<?php echo esc_attr($place_name_option); ?>" <?php echo ($place_name_in_list && $place_name_option === $place_name_value ? 'selected' : ''); ?>><?php echo esc_html($place_name_option); ?></option>
                    <?php endforeach; ?>
                    <option value="__uj__" <?php echo (!$place_name_in_list && $place_name_value !== '' ? 'selected' : ''); ?>>+ Új érték megadása…</option>
                </select>
                <input type="text" class="form-control" name="place_name" id="place_name" autocomplete="off" style="margin-top:5px;<?php echo ($place_name_in_list || $place_name_value === '' ? 'display:none;' : ''); ?>" value="<?php echo esc_attr($place_name_value); ?>">
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

            <?php
            wp_editor(
                isset($record->listing_text) ? wp_kses_post(stripslashes($record->listing_text)) : '',
                'listing_text',
                array(
                    'textarea_name' => 'listing_text',
                    'textarea_rows' => 10,
                    'media_buttons' => false,
                    'tinymce'       => array(
                        'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,outdent,indent,alignleft,aligncenter,alignright,link,unlink,charmap,removeformat,undo,redo',
                        'toolbar2' => '',
                    ),
                )
            );
            ?>

        </div>

        <div class="col-md-12" style="margin-top:30px">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="#" onclick="history.back();" class="btn btn-cancel">Mégsem</a>
        </div>
    </form>


</div>

<?php
// A szabadidősportról való visszaváltáshoz kell egy "rendes" sorozat: a
// legfrissebb olyan, ami nem a Szabadidősport sorozat (series_id=1).
$vespa_default_series_id = $wpdb->get_var("SELECT series_id FROM vespa_series WHERE series_id <> 1 ORDER BY series_id DESC LIMIT 1");
$vespa_default_series_id = intval($vespa_default_series_id) > 0 ? intval($vespa_default_series_id) : 1;
?>
<script>
    // A típus, a lebonyolítás és a sorozat szabadidősport esetén együtt jár —
    // a mentés is ezt követeli meg. Ezért nem elég ODAFELÉ szinkronizálni:
    // szabadidősportról egy másik típusra váltva a lebonyolítást és a sorozatot
    // is ki kell léptetni belőle, különben a mentés "Szabadidő versenytípusnak
    // a lebonyolítása is csak szabadidősport lehet!" hibával elszáll, és a
    // tévesen szabadidősként felvett verseny nem minősíthető át megyeivé.
    var VESPA_DEFAULT_SERIES_ID = <?php echo $vespa_default_series_id; ?>;

    jQuery('#contest_types').on('change', function(e) {
        if (this.value == 4) {
            jQuery('#contest_subtypes').val(3)
            jQuery('#contest_series').val(1)
        } else {
            if (jQuery('#contest_subtypes').val() == 3) {
                jQuery('#contest_subtypes').val(2) // Nem továbbjutásos
            }
            if (jQuery('#contest_series').val() == 1) {
                jQuery('#contest_series').val(VESPA_DEFAULT_SERIES_ID)
            }
        }
        updateVisibility(this.value)
    });
    jQuery('#contest_subtypes').on('change', function(e) {
        const contestSeries = jQuery('#contest_series').val()
        const contestType = jQuery('#contest_types').val()
        if(this.value == 3 && (contestType != 4 || contestSeries != 1)){
            jQuery("#contest_types").val(4)
            jQuery('#contest_series').val(1)
            updateVisibility(4)
        }
    });
    jQuery('#contest_series').on('change', function(e) {
        const contestType = jQuery('#contest_types').val()
        const contestSubtype = jQuery('#contest_subtypes').val()
        if(this.value == 1 && (contestType != 4 || contestSubtype != 3)){
            jQuery("#contest_types").val(4);
            jQuery("#contest_subtypes").val(3);
            updateVisibility(4)
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

<script>
    // Rendező / Helyszín neve legördülők: kiválasztott érték átmásolása a valódi (submitolt) szöveges mezőbe,
    // "+ Új érték megadása…" választásakor pedig a szöveges mező megjelenítése és fókuszba állítása.
    jQuery(function($) {
        function bindDropdownFallback(selectId, inputId) {
            var $select = $('#' + selectId);
            var $input = $('#' + inputId);

            $select.on('change', function() {
                if (this.value === '__uj__') {
                    $input.val('').show().focus();
                } else {
                    $input.val(this.value).hide();
                }
            });
        }

        bindDropdownFallback('organiser_select', 'organiser');
        bindDropdownFallback('place_name_select', 'place_name');
    });
</script>

<?php if ($record == null) : ?>
<script>
    // Verseny nevének automatikus összeállítása új verseny felvételekor.
    // A szórend típusonként más, mert magyarul csak így helyes:
    //   megyei     -> "Nógrád megyei Atlétika Diákolimpia"
    //   országos   -> "Atlétika Diákolimpia Országos Döntő"
    //   regionális -> "Atlétika Diákolimpia Regionális"
    //   szabadidős -> "Atlétika" (nem Diákolimpia-verseny)
    // A "Döntő" szót kizárólag az országos verseny kapja meg, automatikusan.
    // Meglévő versenynél ez a blokk nem is töltődik be, a contest_name mezőt
    // így soha nem érinti.
    jQuery(function($) {
        var stateNames = <?php
            $state_map = array();
            $state_list = $wpdb->get_results("SELECT * FROM vespa_states");
            foreach ($state_list as $st) {
                $state_map[$st->state_id] = $st->state_name;
            }
            echo wp_json_encode($state_map);
        ?>;

        var userEditedName = false;

        // ha a felhasználó saját maga gépel a mezőbe, onnantól nem írjuk felül
        $('#contest_name').on('input', function(e) {
            if (!e.originalEvent) {
                return; // saját (script által kiváltott) esemény, nem számít kézi szerkesztésnek
            }
            userEditedName = true;
        });

        function composeContestName() {
            if (userEditedName) {
                return;
            }

            var typeId = $('#contest_types').val();
            var stateId = $('#state_id').val();
            var sport = ($('#sportag_nev').val() || '').toString().trim();

            // Sportág nélkül nincs értelmes név — ilyenkor a mezőt üresen
            // hagyjuk, nem tesszük bele a puszta "Diákolimpia" szót.
            if (sport === '') {
                $('#contest_name').val('');
                return;
            }

            var parts;
            if (typeId == 3) {
                // megyei: megye neve + "megyei" + sportág + "Diákolimpia"
                var stateName = stateNames[stateId] || '';
                parts = [stateName === '' ? '' : stateName + ' megyei', sport, 'Diákolimpia'];
            } else if (typeId == 1) {
                parts = [sport, 'Diákolimpia', 'Országos', 'Döntő'];
            } else if (typeId == 2) {
                parts = [sport, 'Diákolimpia', 'Regionális'];
            } else {
                // szabadidősport: nem Diákolimpia-verseny, csak a sportág neve
                parts = [sport];
            }

            var name = parts.join(' ').replace(/\s+/g, ' ').trim();
            $('#contest_name').val(name);
        }

        $('#contest_types, #contest_subtypes, #contest_series, #state_id, #sportag_nev')
            .on('change', composeContestName);

        composeContestName();
    });
</script>
<?php endif; ?>