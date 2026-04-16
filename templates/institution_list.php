<?php

global $wpdb;

$sql = 'SELECT * FROM vespa_states';
$states = $wpdb->get_results($sql);

?>

<div class="wrap">
    <div class="row">
        <div class="col-md-9">
            <h1 class="site-title">Intézmények</h1>
        </div>   
        <div class="col-md-3">
            <a style="margin-left: 10px;" href="<?php echo admin_url('admin.php?page=institution&id=0'); ?>" class="btn btn-sm btn-primary pull-right">
                <i class="fa fa-plus" aria-hidden="true"></i>
                ÚJ
            </a>
            <a href="#" class="btn btn-sm btn-primary pull-right" onclick="downloadXLS('institution')">
                <i class="fa fa-download" aria-hidden="true"></i>
                Export
            </a>
        </div>     

        <div class="institution-extra-filters">
            <div class="row">
                <div class="col-md-12">    
                    <a href="#" onclick="toggleExtraFilters( event, 'institution' );">További szűrési opciók megjelenítése</a>
                </div>
            </div> 
            <div class="row" style="display:none;" id="extrafilters">
                <div class="col-md-12">
                    <h4>További szűrők</h4>
                </div>
                <div class="col-md-2">    
                    <div class="form-group">
                        <label for="">Típus</label>
                        <select id="ins_type" class="form-control input-sm" autocomplete="off">
                            <option value="">Összes</option>
                            <option value="iskola">Iskola</option>
                            <option value="egyesulet">Egyesület</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-3"> 
                    <div class="form-group">
                        <label for="">Megye</label>
                        <select id="state" class="form-control input-sm" autocomplete="off">
                            <option value="" selected>Összes</option>
                            <?php foreach ($states as $state) : ?>                         
                                <option value="<?php echo $state->state_id ?>">
                                    <?php echo $state->state_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>  
                    </div>            
                </div>            

                <div class="col-md-3"> 
                    <div class="form-group">
                        <label for="">Település</label>
                        <input type="text" id="ins_city" class="form-control input-sm" autocomplete="off">
                    </div>            
                </div>   

                <div class="col-md-3"> 
                    <div class="form-group">
                        <label for="">Intézmény neve</label>
                        <input type="text" id="ins_name" class="form-control input-sm" autocomplete="off">
                    </div>            
                </div>
                
                <div class="col-md-3"> 
                    <div class="form-group">
                        <label for="">OM azonosító</label>
                        <input type="text" id="id_number" class="form-control input-sm" autocomplete="off">
                    </div>            
                </div>   

                <div class="col-md-4"> 
                    <div class="form-group">
                        <label for="">Kapcsolattartó neve/elérhetősége</label>
                        <input type="text" id="person_data" class="form-control input-sm" autocomplete="off">
                    </div>            
                </div>   
                
                <div class="col-md-3"> 
                    <div class="form-group">
                        <label for="">&nbsp;</label><br>
                        <button class="btn btn-sm btn-primary" onclick="doExtraFilters('institution');">Szűrés</button>
                        <button class="btn btn-sm btn-default" onclick="cancelExtraFilters('institution');">Mégsem</button>
                    </div>            
                </div>                        
            </div>
        </div>
       
    </div>


    <table class="table table-striped datatables" data-source="institution">
    <thead>
        <tr>
            <th>Azon.</th>
            <th>Neve</th>
            <th>Típusa</th>
            <th>Megye</th>
            <th>Település</th>
            <th>&nbsp;</th>
        </tr>
    </thead>
    <tbody>

    </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var stateSelect = document.getElementById('state');
    var insTypeSelect = document.getElementById('ins_type');
    var needsReload = (stateSelect && stateSelect.value !== '') || (insTypeSelect && insTypeSelect.value !== '');
    if (stateSelect) stateSelect.value = '';
    if (insTypeSelect) insTypeSelect.value = '';
    if (needsReload && typeof vespa_tables !== 'undefined' && vespa_tables['institution']) {
        vespa_tables['institution'].ajax.reload();
    }
});
</script>

<?php
    echo vespa_load_template_with_vars(
                'confirm-box.php',
                array(
                    "{=TEXT=}"   => "Biztosan törlöd ezt az intézményt?",
                    "{=MODALID=}"   => "",
                    "{=ACTION=}" => 'delete_institution'
                )
            );
?>