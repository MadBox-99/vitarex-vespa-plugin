<?php 
    $site_title = 'Új verseny típus';
    $id         = $_GET['id'];
    $record     = null;

    if( is_numeric($id) && $id > 0 ){
        $record = $GLOBALS['VESPA_Demo']->load( $id );
        $site_title = $record->contest_type_name . ' - szerkesztése';
    }

?>


<div class="wrap">
        <div class="row">
            <div class="col-md-12">
                <h1 class="site-title"><?php echo $site_title; ?></h1>
            </div>
        </div>


        <div class="row">
            <div class="col-md-6">

                <form action="" class="ajax-form" method="POST">
                    <input type="hidden" name="action" autocomplete="off" value="save_demo">
                    <input type="hidden" name="contest_type_id" id="contest_type_id" autocomplete="off" value="<?php echo $id; ?>">

                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Megnevezés</label>
                            <input type="text" class="form-control" name="contest_type_name" id="contest_type_name" autocomplete="off" value="<?php echo ( $record == null ? '' : $record->contest_type_name ); ?>">
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
