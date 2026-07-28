<?php
    $site_title = 'Beszámoló kitöltése';
    $id         = isset($_GET['id']) ? intval($_GET['id']) : 0;

    global $wpdb;

    if( $id <= 0 ){
        wp_redirect( admin_url('admin.php?page=contests') );
        exit;
    }

    $questions = $wpdb->get_results("SELECT * FROM vespa_contests_questions ORDER BY ordernum ASC");

    // A verseny meglévő válaszai. A question_id > 0 sorok az aktuális
    // kérdésekhez tartoznak; a 0-sok azóta megszűnt kérdésekhez, azokat csak
    // olvashatóan mutatjuk meg a lap alján.
    $valaszok    = array();
    $tortenelmi  = array();
    $mentett     = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM vespa_questions_answered WHERE contest_id=%d ORDER BY qa_id ASC",
        $id
    ));
    foreach( $mentett as $sor ){
        if( intval($sor->question_id) > 0 ){
            $valaszok[ intval($sor->question_id) ] = $sor;
        } else {
            $tortenelmi[] = $sor;
        }
    }

    $van_mar_valasz = ! empty($valaszok) || ! empty($tortenelmi);
?>


<div class="wrap">
        <div class="row">
            <div class="col-md-12">
                <h1 class="site-title"><?php echo esc_html($site_title); ?></h1>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">

            <?php if( empty($questions) ) : ?>
                <p>Nincs felvéve egyetlen beszámoló-kérdés sem.</p>
            <?php else : ?>

            <form action="" class="ajax-form" method="POST">
                    <input type="hidden" name="action" autocomplete="off" value="save_questions_answered">
                    <input type="hidden" name="nonce" autocomplete="off" value="<?php echo esc_attr( wp_create_nonce('vespa_nonce') ); ?>">
                    <input type="hidden" name="contest_id" id="contest_id" autocomplete="off" value="<?php echo esc_attr($id); ?>">

                    <?php foreach($questions as $sorszam => $question ): ?>
                        <?php
                            $ordernum = intval($question->ordernum);
                            $mentett_sor = isset($valaszok[ intval($question->question_id) ])
                                ? $valaszok[ intval($question->question_id) ]
                                : null;

                            $mentett_answer = $mentett_sor ? $mentett_sor->answer : '';
                            $mentett_qnote  = $mentett_sor ? $mentett_sor->qnote  : '';

                            $lehetosegek = array_values( array_filter( array_map('trim', explode(';', $question->answers)), function($v){
                                return $v !== '';
                            }));

                            // Egyetlen lehetőség nem választás: a 23 kérdésből 17-nek
                            // csak egy "válasz a megjegyzésben" opciója van. Ilyenkor
                            // rádiógombot sem rajzolunk, csak a megjegyzés mezőt.
                            $van_valasztas = count($lehetosegek) > 1;
                        ?>

                <div class="row">
                    <div class="col-md-<?php echo $van_valasztas ? '6' : '12'; ?>">
                        <h3><?php echo esc_html( ($sorszam + 1) . '. ' . $question->question ); ?></h3>

                        <?php if( $van_valasztas ) : ?>
                            <?php foreach($lehetosegek as $ind => $answer): ?>
                            <div class="form-group form-checkbox">
                                <input type="radio" name="<?php echo 'answer'. $ordernum; ?>" id="<?php echo 'answer'. $ordernum . '-' . $ind; ?>" autocomplete="off" value="<?php echo esc_attr($answer); ?>" <?php checked($mentett_answer, $answer); ?>>
                                <label for="<?php echo 'answer'. $ordernum . '-' . $ind; ?>">
                                    <?php echo esc_html($answer); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-<?php echo $van_valasztas ? '6' : '12'; ?>">
                        <div class="form-group">
                            <label>Megjegyzés</label>
                            <textarea name="<?php echo 'qnote'. $ordernum; ?>" id="<?php echo 'qnote'. $ordernum; ?>" cols="30" rows="<?php echo $van_valasztas ? '10' : '4'; ?>" autocomplete="off" class="form-control"><?php echo esc_textarea($mentett_qnote); ?></textarea>
                        </div>
                    </div>
                </div>
                    <?php endforeach; ?>

                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">Mentés</button>
                        <a href="#" onclick="history.back();" class="btn btn-cancel">Mégsem</a>
                    </div>
                </form>

            <?php endif; ?>

            <?php if( ! empty($tortenelmi) ) : ?>
                <div class="row" style="margin-top:40px; opacity:.7;">
                    <div class="col-md-12">
                        <h3>Korábbi kérdések</h3>
                        <p class="description">
                            Ezek a válaszok olyan kérdésekhez tartoznak, amelyek azóta
                            kikerültek a kérdéssorból. Megmaradnak, de már nem
                            szerkeszthetők.
                        </p>
                        <table class="table table-striped">
                            <thead><tr><th>Kérdés</th><th>Válasz</th><th>Megjegyzés</th></tr></thead>
                            <tbody>
                            <?php foreach( $tortenelmi as $sor ) : ?>
                                <tr>
                                    <td><?php echo esc_html($sor->question); ?></td>
                                    <td><?php echo esc_html($sor->answer); ?></td>
                                    <td><?php echo esc_html($sor->qnote); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            </div>
        </div>

</div>
