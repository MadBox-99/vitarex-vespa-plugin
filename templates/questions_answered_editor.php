<?php 
    $site_title = 'Kérdések kitöltése';
    $id         = $_GET['id'];
    $record     = null;

    global $wpdb;

    if( ! isset($_GET['id']) ){
        wp_redirect( admin_url('admin.php?page=contests') );
        exit;
    }
   
    $questions = $wpdb->get_results("SELECT * FROM vespa_contests_questions ORDER BY ordernum ASC");
?>


<div class="wrap">
        <div class="row">
            <div class="col-md-12">
                <h1 class="site-title"><?php echo $site_title; ?></h1>
            </div>
        </div>


        <div class="row">
            <div class="col-md-12">

            <form action="" class="ajax-form" method="POST">
                    <input type="hidden" name="action" autocomplete="off" value="save_questions_answered">
                    <input type="hidden" name="contest_id" id="contest_id" autocomplete="off" value="<?php echo $id; ?>">
                    <input type="hidden" name="data_count" id="data_count" autocomplete="off" value="<?php echo count($questions); ?>">

                    <?php 
                        foreach($questions as $question ):
                    ?>

                <div class="row">
                    <input type="hidden" name="<?php echo 'question'. $question->ordernum ?>" id="<?php echo 'question'. $question->ordernum ?>" autocomplete="off" value="<?php echo $question->question; ?>">
                    <div class="col-md-6">
                        <h3><?php echo $question->ordernum + 1 . '.' . $question->question ?></h3>

                        <?php foreach(explode(';', $question->answers) as $ind => $answer): ?>
                        <div class="form-group form-checkbox">
                            <input type="radio" name="<?php echo 'answer'. $question->ordernum ?>" id="<?php echo 'answer'. $question->ordernum . '-' . $ind; ?>" autocomplete="off" value="<?php echo $answer; ?>">
                            <label for="<?php echo 'answer'. $question->ordernum . '-' . $ind; ?>">
                                <?php echo $answer; ?>
                            </label>                           
                        </div>
                        <?php endforeach; ?>
                    </div>   
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Megjegyzés</label>
                            <textarea name="<?php echo 'qnote'. $question->ordernum ?>" id="<?php echo 'qnote'. $question->ordernum ?>" cols="30" rows="10" autocomplete="off" class="form-control"></textarea>
                        </div>
                    </div>     
                </div> 
                    <?php 
                        endforeach;
                    ?>
                    
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">Mentés</button> 
                        <a href="#" onclick="history.back();" class="btn btn-cancel">Mégsem</a>           
                    </div>
                </form>
                
            </div>
        </div>

</div>
