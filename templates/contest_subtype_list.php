<div class="wrap">
    <div class="row">
        <div class="col-md-8">
            <h1 class="site-title">Lebonyolítás rendje</h1>
        </div>

        <div class="col-md-4">
            <a href="<?php echo admin_url('admin.php?page=contest_subtypes&id=0'); ?>" class="btn btn-sm btn-primary pull-right">
                <i class="fa fa-plus" aria-hidden="true"></i>
                ÚJ
            </a>
        </div>            
    </div>


    <table class="table table-striped datatables" data-source="contest_subtypes">
    <thead>
        <tr>
            <th>Azon.</th>
            <th>Lebonyolítás rendje</th>
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
                    "{=TEXT=}"   => "Biztosan törlöd ezt a lebonyolítás rendje típust?",
                    "{=MODALID=}"=> "",
                    "{=ACTION=}" => 'delete_contest_subtypes'
                ) 
            ); 
?>