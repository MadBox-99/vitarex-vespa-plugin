<?php 
    $site_title = 'Új sport felvétele';
    $id         = $_GET['id'];
    $record     = null;

    if( is_numeric($id) && $id > 0 ){
        $record = $GLOBALS['VESPA_Sports']->load( $id );
        $site_title = $record->sport_name . ' - szerkesztése';
    }

    $results = [
        'helyezes' => 'Csak helyezés',
        'helyezes_ido_k' => 'Idő (másodperc) - kisebb a jobb',
        'helyezes_ido_n' => 'Idő (másodperc) - nagyobb a jobb',
        'helyezes_tav_k' => 'Távolság (cm) - kisebb a jobb',
        'helyezes_tav_n' => 'Távolság (cm) - nagyobb a jobb',
        'helyezes_suly_k' => 'Súly (kg) - kisebb a jobb',
        'helyezes_suly_n' => 'Súly (kg) - nagyobb a jobb',
    ]
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
                    <input type="hidden" name="action" autocomplete="off" value="save_sports">
                    <input type="hidden" name="sport_id" id="sport_id" autocomplete="off" value="<?php echo $id; ?>">

                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Megnevezés</label>
                            <input type="text" class="form-control" name="sport_name" id="sport_name" autocomplete="off" value="<?php echo ( $record == null ? '' : $record->sport_name ); ?>">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Eredmény típusa</label>
                            <select name="result_type" id="result_type" class="form-control input-sm">
                                <?php foreach ($results as $key => $value) : ?>
                                    <option value="<?php echo $key ?>" <?php echo ($record != null && $record->result_type == $key  ? 'selected' : ''); ?>>
                                        <?php echo $value; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
