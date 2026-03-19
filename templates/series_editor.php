<?php 
    $site_title = 'Új versenysorozat felvétele';
    $id         = $_GET['id'];
    $record     = null;

    if( is_numeric($id) && $id > 0 ){
        $record = $GLOBALS['VESPA_Series']->load( $id );
        $site_title = $record->series_name . ' - szerkesztése';
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
                    <input type="hidden" name="action" autocomplete="off" value="save_series">
                    <input type="hidden" name="series_id" id="series_id" autocomplete="off" value="<?php echo $id; ?>">

                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Megnevezés</label>
                            <input type="text" class="form-control" name="series_name" id="series_name" autocomplete="off" value="<?php echo ( $record == null ? '' : $record->series_name ); ?>">
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
