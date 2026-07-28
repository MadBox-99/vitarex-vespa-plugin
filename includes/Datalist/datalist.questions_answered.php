<?php
    
    class VESPA_Questions_Answered extends VESPA_Datalist {
        protected $source            = 'questions_answered';
        protected $tablename         = 'vespa_questions_answered';
        protected $id_field          = 'qa_id';
        protected $default_order_by  = 'contest_id';
        protected $default_order_dir = 'ASC';
        protected $columns           = array('qa_id','contest_id', 'question', 'answer', 'qnote');

        public function save(){
            global $wpdb;

            check_ajax_referer( 'vespa_nonce', 'nonce' );

            if( ! current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles ) ){
                wp_send_json_error( array('errors' => array('contest_id' => 'Nincs jogosultságod a beszámoló mentéséhez.')) );
            }

            $contest_id = isset($_POST['contest_id']) ? intval($_POST['contest_id']) : 0;
            if( $contest_id <= 0 ){
                wp_send_json_error( array('errors' => array('contest_id' => 'Hibás verseny.')) );
            }

            $kerdesek = $wpdb->get_results("SELECT * FROM vespa_contests_questions ORDER BY ordernum ASC");

            // A verseny már mentett sorai question_id szerint indexelve. A 0-s
            // sorok azóta megszűnt kérdésekhez tartoznak — azokhoz nem nyúlunk.
            // ORDER BY qa_id: duplikáció esetén ugyanazt a sort válassza, mint
            // a szerkesztő sablon lekérdezése (az is qa_id ASC szerint rendez).
            $meglevo = array();
            $sorok = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM vespa_questions_answered WHERE contest_id=%d ORDER BY qa_id ASC",
                $contest_id
            ));
            foreach( $sorok as $sor ){
                if( intval($sor->question_id) > 0 ){
                    $meglevo[ intval($sor->question_id) ] = $sor;
                }
            }

            // Első kör: beolvasás + hosszellenőrzés, írás nélkül. A
            // sanitize_*_field nem vág le semmit, viszont a tábla `answer`
            // oszlopa varchar(200), a `qnote` varchar(400) — egy túl hosszú
            // értéket a wpdb csendben levágna, insert/update esetén pedig
            // akár hibát is adhat. Ha bármelyik mező túl hosszú, itt állunk
            // meg: SEMMIT nem írunk, és a hiba a hibás mezőre kerül.
            $adatok = array();
            foreach( $kerdesek as $kerdes ){
                $ordernum    = intval($kerdes->ordernum);
                $question_id = intval($kerdes->question_id);

                // A be nem jelölt rádiógombot a böngésző nem küldi el.
                $answer = isset($_POST['answer' . $ordernum])
                    ? sanitize_text_field( wp_unslash($_POST['answer' . $ordernum]) )
                    : '';
                $qnote = isset($_POST['qnote' . $ordernum])
                    ? sanitize_textarea_field( wp_unslash($_POST['qnote' . $ordernum]) )
                    : '';

                if( mb_strlen($answer) > 200 ){
                    wp_send_json_error( array('errors' => array(
                        'answer' . $ordernum => 'A válasz legfeljebb 200 karakter lehet.',
                    )) );
                }
                if( mb_strlen($qnote) > 400 ){
                    wp_send_json_error( array('errors' => array(
                        'qnote' . $ordernum => 'A megjegyzés legfeljebb 400 karakter lehet.',
                    )) );
                }

                $adatok[] = array(
                    'kerdes'      => $kerdes,
                    'question_id' => $question_id,
                    'answer'      => $answer,
                    'qnote'       => $qnote,
                );
            }

            // FONTOS: az űrlapmezők a kérdés ordernum-áról kapják a nevüket, és
            // az ordernum értékek NEM folytonosak (0, 1, 7, 8 ... 26, 28). Ezért
            // a kérdéseken iterálunk, nem egy 0..count-1 számlálón — az utóbbi
            // néma kérdésvesztést okozott.
            foreach( $adatok as $adat ){
                $kerdes      = $adat['kerdes'];
                $question_id = $adat['question_id'];
                $answer      = $adat['answer'];
                $qnote       = $adat['qnote'];

                $regi = isset($meglevo[$question_id]) ? $meglevo[$question_id] : null;

                // Egyopciós kérdésnél ("válasz a megjegyzésben") a sablon nem
                // rajzol rádiógombot, tehát answer sosem érkezik posztban. Az
                // egyetlen lehetőség csak helykitöltő szöveg, nem az admin
                // által választott érték — ezt nem az admin dolga törölni
                // azzal, hogy egyszerűen megnyitja és Mentésre kattint.
                // Ezért egy meglévő sor válaszát megőrizzük felülírás helyett.
                if( $regi !== null && vespa_kerdoiv_egyopcios($kerdes->answers) ){
                    $answer = $regi->answer;
                }

                $van_adat = vespa_kerdoiv_kerdes_megvalaszolt($answer, $qnote);

                if( $regi === null ){
                    // Üres kérdéshez nem hozunk létre sort — attól lenne hazug
                    // a kitöltöttség-számláló.
                    if( ! $van_adat ){
                        continue;
                    }

                    $siker = $wpdb->insert( $this->tablename, array(
                        'contest_id'  => $contest_id,
                        'question_id' => $question_id,
                        'question'    => $kerdes->question,
                        'answer'      => $answer,
                        'qnote'       => $qnote,
                    ), array( '%d', '%d', '%s', '%s', '%s' ));

                    if( $siker === false ){
                        wp_send_json_error( array('errors' => array(
                            'contest_id' => 'Hiba történt a beszámoló mentése közben. Kérjük, próbáld újra.',
                        )) );
                    }
                    continue;
                }

                if( ! $van_adat ){
                    // Üresre szerkesztett sorban nincs adat, amit őrizni kellene.
                    $siker = $wpdb->delete( $this->tablename, array( 'qa_id' => intval($regi->qa_id) ), array('%d') );

                    if( $siker === false ){
                        wp_send_json_error( array('errors' => array(
                            'contest_id' => 'Hiba történt a beszámoló mentése közben. Kérjük, próbáld újra.',
                        )) );
                    }
                    continue;
                }

                $siker = $wpdb->update( $this->tablename, array(
                    'question' => $kerdes->question,
                    'answer'   => $answer,
                    'qnote'    => $qnote,
                ), array(
                    'qa_id' => intval($regi->qa_id),
                ),
                array( '%s', '%s', '%s' ),
                array( '%d' ));

                if( $siker === false ){
                    wp_send_json_error( array('errors' => array(
                        'contest_id' => 'Hiba történt a beszámoló mentése közben. Kérjük, próbáld újra.',
                    )) );
                }
            }

            $vars = array(
                "{=TEXT=}" => 'A beszámoló mentése sikeres volt.',
                "{=URL=}" => admin_url('admin.php?page=contests') . '&action=view&id='. $contest_id
            );

            wp_send_json_success( array('modal' => vespa_load_template_with_vars( 'success-modal.php', $vars ), 'modalId' => 'succesModal' ) );
        }

        public function checkDelete( $id ){
            return current_user_can( 'manage_options' ) || current_user_can( VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles );
        }

        public function getFilters(){
            global $wpdb;
            $filters = '1';

            if( isset($_REQUEST['search']) && isset($_REQUEST['search']['value']) && trim($_REQUEST['search']['value']) != '' ){
                $filters = $wpdb->prepare("series_name LIKE %s", '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['search']['value'])) . '%');
            }

            return $filters;
        }

        function addActionButtons( $item ){
            $btns = '';
            $btns .= '<a href="' . admin_url('admin.php?page=series') . '&id=' . $item->{$this->id_field}  . '" class="btn btn-sm btn-default">';
            $btns .= '  <i class="fa fa-pencil" aria-hidden="true"></i>';
            $btns .= '</a>&nbsp;';

            if( $this->checkDelete(0) ){
                $btns .= '<button class="btn btn-sm btn-default color-red delete-entity" data-modalid="" data-id="' . $item->{$this->id_field} . '">';
                $btns .= '  <i class="fa fa-trash" aria-hidden="true"></i>';
                $btns .= '</button>';
            }

            return $btns;
        }

    }


    $GLOBALS['VESPA_Questions_Answered'] = new VESPA_Questions_Answered();