

<div class="wrap">
    <div class="row">
        <div class="col-md-8">
            <h1 class="site-title">Sportolók</h1>
        </div>

        <?php if(current_user_can('sportolok_letrehozasa_modositasa')){  ?>
        <div class="col-md-4">
            <a href="<?php echo admin_url('admin.php?page=athletes&id=0'); ?>" class="btn btn-sm btn-primary pull-right">
                <i class="fa fa-plus" aria-hidden="true"></i>
                ÚJ
            </a>

            <a href="#" class="btn btn-sm btn-primary pull-right" onclick="downloadXLS('athletes')" style="margin-right:10px">
                <i class="fa fa-download" aria-hidden="true"></i>
                Export
            </a>

            <a href="#" class="btn btn-sm btn-primary pull-right" onclick="downloadXLS('athlete_results')" style="margin-right:10px">
                <i class="fa fa-download" aria-hidden="true"></i>
                Eredmények
            </a>

            <a href="<?php echo admin_url('admin.php?page=import_athletes'); ?>" class="btn btn-sm btn-primary pull-right" style="margin-right:10px">
                <i class="fa fa-plus" aria-hidden="true"></i>
                Importálás
            </a>
        </div>

        <?php } ?>
    </div>
</div>

<div class="athletes-extra-filters">
    <div class="row">
        <div class="col-md-12">
            <a href="#" onclick="toggleExtraFilters( event,'athletes' );">További szűrési opciók megjelenítése</a>
        </div>
    </div>
    <div class="row" style="display:none;" id="extrafilters">
        <div class="col-md-12">
            <h4>További szűrők</h4>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label for="">Születési hely</label>
                <input id="birth_place">
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label for="">Születési idő</label>
                <input class="dpicker" id="birth_date">
            </div>
        </div>


        <?php 
            global $wpdb;
            
            $institutions = $wpdb->get_results('SELECT * FROM vespa_institutions ORDER BY ins_name ASC');

            $school_list = array();
            foreach ($institutions as $institution){
                if( ! empty($school_id) && $school_id == $institution->institution_id ){
                    $school_name = $institution->ins_name;
                }

                $school_list[] = array(
                    'value' => $institution->institution_id,
                    'label' => $institution->ins_name,
                );
            }            

        ?>

        <div class="col-md-2">
            <div class="form-group">
                <label for="">Intézmény</label>
                <input type="hidden" name="school_id" id="school_id" value="0">
                <input type="text" class="regular-text" name="school_name" id="school_name" value="">
            </div>
        </div>   
        <script>
            var institutions =  <?php echo json_encode($school_list) ?>;

            jQuery( document ).ready( function() {
                jQuery( "#school_name" ).autocomplete({
                    source: institutions,
                    select: function(event, ui){
                        event.preventDefault();
                        
                        jQuery('#school_id').val(ui.item.value);
                        jQuery('#school_name').val(ui.item.label);
                    }
                });
            });
        </script> 



        <div class="col-md-12">
            <div class="form-group">
                <label for="">&nbsp;</label><br>
                <button class="btn btn-sm btn-primary" onclick="doExtraFilters('athletes');">Szűrés</button>
                <button class="btn btn-sm btn-default" onclick="cancelExtraFilters('athletes');">Mégsem</button>
            </div>
        </div>
    </div>
</div>

<table class="table table-striped datatables" data-source="athletes">
    <thead>
        <tr>
            <th>Azon.</th>
            <th>Név</th>
            <th>Iskola / egyesület</th>
            <th>Születési hely</th>
            <th>Születési idő</th>
            <th>Anyja neve</th>
            <?php if(current_user_can('sportolok_letrehozasa_modositasa')){  ?>
            <th>&nbsp;</th>
            <?php } ?>
        </tr>
    </thead>
    <tbody>

    </tbody>
</table>
</div>

<?php
echo vespa_load_template_with_vars(
    'confirm-box.php',
    array(
        "{=TEXT=}"   => "Biztosan törlöd ezt a sportolót?",
        "{=MODALID=}"   => "",
        "{=ACTION=}" => 'delete_athletes'
    )
);
?>