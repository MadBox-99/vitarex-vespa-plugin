<?php 
    $site_title = 'Új tankerület felvétele';
    $id         = $_GET['id'];
    $record     = null;

    if( is_numeric($id) && $id > 0 ){
        $record = $GLOBALS['VESPA_SchoolDistrict']->load( $id );
        $site_title = $record->school_district_name . ' - szerkesztése';
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
                    <input type="hidden" name="action" autocomplete="off" value="save_school_district">
                    <input type="hidden" name="school_district_id" id="school_district_id" autocomplete="off" value="<?php echo $id; ?>">

                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Megnevezés</label>
                            <input type="text" class="form-control" name="school_district_name" id="school_district_name" autocomplete="off" value="<?php echo ( $record == null ? '' : $record->school_district_name ); ?>">
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
