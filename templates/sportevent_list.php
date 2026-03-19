<div class="wrap">
    <div class="row">
        <div class="col-md-8">
            <h1 class="site-title">Sportesemények</h1>
        </div>

        <div class="col-md-4">
            <a href="<?php echo admin_url('admin.php?page=sportevents&id=0'); ?>" class="btn btn-sm btn-primary pull-right">
                <i class="fa fa-plus" aria-hidden="true"></i>
                ÚJ
            </a>
        </div>            
    </div>


    <table class="table table-striped datatables" data-source="sport_events">
    <thead>
        <tr>
            <th>Azon.</th>
            <th>Sportesemény neve</th>
            <th>Sport neve</th>
            <th>&nbsp;</th>
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
                    "{=TEXT=}"   => "Biztosan törlöd ezt a sporteseményt?",
                    "{=MODALID=}"   => "",
                    "{=ACTION=}" => 'delete_sport_events'
                ) 
            ); 
?>