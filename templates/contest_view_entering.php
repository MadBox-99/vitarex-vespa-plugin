    <div class="vespa-box">
        <div class="row">
            <div class="col-md-12">
                <h3 style="margin-top:0">Nevezés</h3>
            </div>


            <div class="message">
                <div class="col-md-12">
                    <h1 style="color:red;">Nyilatkozat fertőzés mentességről</h1>

                    <p>Az iskolam igazgatója, vezetoje által adott jogosultság alapján kaptam a FODISZ VESPA rendszerébe, az iskolam diákjainak FODISZ diákolimpiai és diáksport versenyeire való nevezési jogot.</p>

                    <p>Ennek kapcsán buntetojogi felelosségem tudatában kijelentem, hogy általam az aktuális FODISZ diákolimpiai, diáksportversenveire a VESPA-ban benevezett diákok és kisérök is részt vehetnek, mert fertozo betegségekben a versenyt megelözo 3 napban nem szenvedtek. Az aktuális FODISZ diáksport versenyre nevezett iskolám diákjain, kisérökön nem észlelhetök az alábbi tünetek:</p>

                    <p>
                    <ul class="vespa-list">
                        <li>láz</li>
                        <li>torokfáiás</li>
                        <li>hányás</li>
                        <li>hasmenés</li>
                        <li>börkiutés</li>
                        <li>sárgasag egyéb súlyosabb bôrelváltozás, börgennyezés</li>
                        <li>váladékozó szembetegség, gennyes fül- és orrfolyás</li>
                        <li>A diákok tetú- és rühmentesek</li>
                    </ul>
                    </p>

                    <p>
                        Tudomásul veszem, hogy a meghirdetett FODISZ diákolimpiai és diáksportversenyein sak jelen nyilatkozatban leírtak elfogadása, arról valo nyilatkozat alapján vehetnek részt iskolam diákjai, kísérok és magam is. Jelen nyilatkozat nem helyettesíti a diákokról a FODISZ versenyekre kötelezöen leadandó orvosi igazolást!
                    </p>

                </div>
            </div>

            <div class="message-box">
                <?php
                $confirm = vespa_school_entered_contest($record->contest_id, 0, true);
                ?>

                <p class="nyilatkozom-p">
                    <label class="chlist-label" for="nyilatkozom">
                        <input type="checkbox" id="nyilatkozom" onchange="storeContestEntering();" <?php echo ($confirm ? 'checked' : ''); ?>>
                        Elfogadom és nyilatkozom!
                    </label>
                </p>

                <?php
                $currentDate = date("Y-m-d H:i:s");

                $sql = $wpdb->prepare(
                    "SELECT * FROM vespa_contests 
                    WHERE contest_id = %d 
                    AND school_entry_start_at <= %s 
                    AND school_entry_end_at >= %s",
                    $record->contest_id, $currentDate, $currentDate
                );
                
                $contest = $wpdb->get_row($sql);
 
                if ($confirm) {
                    $usr = get_user_by('id', $confirm->user_id);

                    if ($usr) {
                        echo '<p>' . $confirm->entry_date . ", " . $usr->display_name . '</p>';
                    }
                }
                ?>

            </div>


            <div class="vespa-teachers" style="margin-bottom:40px;">
                <h1>Pedagógusok</h1>
                <p>A nevezéshez legalább egy pedagógus megadása kötelező. Mind a hat mező kitöltése szükséges pedagógusonként.</p>

                <?php
                $teachers = vespa_get_teachers(vespa_get_my_school_id(), $record->contest_id);
                if (empty($teachers)) {
                    // egy üres sablonsor, hogy legyen mit kitölteni
                    $teachers = array(null);
                }
                ?>

                <form action="" class="ajax-form" id="vespa-teachers-form">
                    <input type="hidden" name="action" value="save_teachers" autocomplete="off">
                    <input type="hidden" name="school_id" value="<?php echo vespa_get_my_school_id(); ?>" autocomplete="off">
                    <input type="hidden" name="contest_id" value="<?php echo $record->contest_id; ?>" autocomplete="off">

                    <div class="row kisero-row" style="font-weight:bold;">
                        <div class="col-md-2">Teljes név</div>
                        <div class="col-md-2">Mobil telefonszám</div>
                        <div class="col-md-2">E-mail</div>
                        <div class="col-md-2">Születési hely</div>
                        <div class="col-md-2">Születési idő</div>
                        <div class="col-md-2">Iskola neve</div>
                    </div>

                    <div id="teacher_rows">
                        <?php foreach ($teachers as $t) : ?>
                            <div class="row kisero-row teacher-row">
                                <div class="col-md-2">
                                    <input type="text" class="form-control" name="teacher_teljes_nev[]" value="<?php echo $t ? esc_attr($t->teljes_nev) : ''; ?>">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control" name="teacher_mobil[]" value="<?php echo $t ? esc_attr($t->mobil) : ''; ?>">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control" name="teacher_email[]" value="<?php echo $t ? esc_attr($t->email) : ''; ?>">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control" name="teacher_szuletesi_hely[]" value="<?php echo $t ? esc_attr($t->szuletesi_hely) : ''; ?>">
                                </div>
                                <div class="col-md-2">
                                    <input type="date" class="form-control" name="teacher_szuletesi_ido[]" value="<?php echo $t ? esc_attr($t->szuletesi_ido) : ''; ?>">
                                </div>
                                <div class="col-md-1">
                                    <input type="text" class="form-control" name="teacher_iskola_neve[]" value="<?php echo $t ? esc_attr($t->iskola_neve) : ''; ?>">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="button btn-remove-teacher" onclick="removeTeacherRow(this);">✕</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-12">
                            <button type="button" class="button" onclick="addTeacherRow();">➕ Pedagógus hozzáadása</button>
                        </div>
                    </div>

                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-12">
                            <div id="teacher_success"></div>
                            <button type="submit" class="button">Pedagógusok mentése</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="vespa-races">
                <?php
                $school_id = vespa_get_my_school_id();

                $list = $wpdb->get_results($wpdb->prepare("SELECT e.*, p.sport_name, s.sport_event_name, p.sport_id
                                        FROM vespa_constest_events as e
                                        LEFT JOIN vespa_sports as p ON p.sport_id = e.sport_id
                                        LEFT JOIN vespa_sport_events as s ON s.sport_event_id = e.event_id
                                        WHERE e.contest_id=%d",$record->contest_id));

                

                if (!empty($list)) :
                ?>

                    <h1>Versenyszámok</h1>

                    <div class="vespa-race-item-header">
                        <div class="row">
                            <div class="col-md-3">
                                <span class="">Sportág</span>
                            </div>

                            <div class="col-md-3">
                                <span class="">Versenyszám</span>
                            </div>

                            <div class="col-md-3">
                                <span class="">Fogyatékossági csoport</span>
                            </div>

                            <div class="col-md-2">
                                <span class="">Nevezhető iskolánkként / nevezett iskolámból</span>
                            </div>

                            <div class="col-md-1">
                                &nbsp;
                            </div>
                        </div>
                    </div>


                    <?php
                    $disability_groups = $wpdb->get_results("SELECT * FROM vespa_disability_groups");
                    $index = 0; // foreach végén növelve

                    function mapIdsToNames($string, $objects)
                    {
                        $result = [];
                        $splitString = explode(',', $string);
                        foreach ($splitString as $id) {
                            foreach ($objects as $object) {
                                if ($object->disability_group_id == $id) {
                                    $result[] = $object->disability_group_name;
                                    break;
                                }
                            }
                        }
                        return $result;
                    }
                    foreach ($list as $race) :
                        $params = [];
                        $params[]= $record->contest_id;
                        $params[] = $race->id;
                        // vespa_athlete_entries : contest_id, event_id, athlete_id, user_id, entry_date
                        // vespa_athletes : school_id, athlete_name, birth_date, gender, active, disability_type
                        // contest: disability_groups, dfrom, dto
                        // TODO: filter to dates and disabilities and school and gender
                        $disabilityGroups = explode(',', $race->disability_groups); 
                        $placeholders = implode(',', array_fill(0, count($disabilityGroups), '%s')); 
                    
                        $filters = "a.school_id=%d AND a.active=1 AND a.is_deleted=0 AND a.disability_type IN ($placeholders) ";
                        $filters .= " AND DATE(a.birth_date) >= DATE('%s') ";
                        $filters .= " AND DATE(a.birth_date) <= DATE('%s') ";
                        $params[] = $school_id;
                        $params = array_merge($params, $disabilityGroups);
                        $params[] = $race->dfrom;
                        $params[] = $race->dto;

                        if (strtolower($race->gender) != 'fiú lány') {
                            if (strtolower($race->gender) == 'fiú') {
                                $filters .= " AND gender='férfi'";
                            } else {
                                $filters .= " AND gender='nő'";
                            }
                        }

                        $query = "  SELECT a.*, e.entry_date 
                                    FROM vespa_athletes as a 
                                    LEFT JOIN vespa_athlete_entries as e ON (e.athlete_id=a.athlete_id AND contest_id=%d AND contest_event_id=%d) 
                                    WHERE $filters 
                                    ORDER BY a.athlete_name ASC";
                        

                        //echo "<pre>";
                       //  echo $query;
                        // echo "</pre>";
                        $athletes = $wpdb->get_results($wpdb->prepare($query, ...$params));
                        $userId = get_current_user_id();
                        $entered_sql = "SELECT id FROM vespa_athlete_entries e
                        JOIN vespa_athletes a ON a.athlete_id = e.athlete_id
                        WHERE contest_id=%d AND contest_event_id=%d AND user_id=%d 
                        AND FIND_IN_SET(a.disability_type, %s)";
                       
                       
                        $entered = $wpdb->get_results($wpdb->prepare($entered_sql, $record->contest_id, $race->id, $userId, $race->disability_groups));
                        
        
                    ?>
                        <div class="vespa-race-item">
                            <div class="row">
                                <div class="col-md-3">
                                    <span class=""><?php echo $race->sport_name ?: '<em>(törölt sportág #' . (int)$race->sport_id . ')</em>'; ?></span>
                                </div>

                                <div class="col-md-3">
                                    <span class=""><?php echo $race->sport_event_name ?: '<em>(törölt versenyszám #' . (int)$race->event_id . ')</em>'; ?></span>
                                </div>

                                <div class="col-md-3">
                                    <span class=""><?php foreach(mapIdsToNames($race->disability_groups, $disability_groups) as $elem) echo "$elem "; ?></span>
                                </div>

                                <div class="col-md-2">
                                    <span class=""><?php echo $record->ppl_num_max; ?></span>
                                    <span> / </span>
                                    <span id="<?php echo "entered-num-$index"?>"><?php echo count($entered); ?></span>
                                </div>

                                <div class="col-md-1">
                                    <button type="button" class="button btn-signup">Nevezés</button>
                                </div>
                            </div>
                        </div>


                        <div class="vespa-race-item-athletes" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <h4>Nevezhető</h4>

                                    <table class="table table-striped" data-row-id="<?php echo $index; ?>" data-contest_id="<?php echo $record->contest_id; ?>" data-contest_event_id="<?php echo $race->id; ?>" data-event_id="<?php echo $race->event_id; ?>" id="<?php echo "can_enter_list_$index"?>">
                                        <tr>
                                            <td colspan="3">
                                                <input type="text" placeholder="A kereséshez gépelj" autocomplete="off" class="form-control entry-search">
                                            </td>
                                        </tr>
                                        <?php
                                        foreach ($athletes as $a) :
                                            if (empty($a->entry_date)) :

                                        ?>
                                                <tr class="athlete-row">
                                                    <td>
                                                        <a href="#" class="athlete-entry" data-id="<?php echo $a->athlete_id; ?>">
                                                            <?php echo $a->athlete_name; ?>
                                                        </a>

                                                    </td>

                                                    <td>
                                                        <?php echo $a->gender; ?>
                                                    </td>

                                                    <td>
                                                        <?php echo $a->birth_date; ?>
                                                    </td>

                                                </tr>
                                        <?php
                                            endif;
                                        endforeach;
                                        ?>
                                    </table>
                                </div>

                                <div class="col-md-6">
                                    <h4>Nevezett</h4>


                                    <table class="table table-striped" data-row-id="<?php echo $index; ?>" id="<?php echo "entered_list_$index"?>" data-contest_id="<?php echo $record->contest_id; ?>" data-contest_event_id="<?php echo $race->id; ?>" data-event_id="<?php echo $race->event_id; ?>">
                                        <tbody>



                                            <?php
                                            foreach ($athletes as $a) :
                                                if (!empty($a->entry_date)) :

                                            ?>
                                                    <tr>
                                                        <td>
                                                            <a href="#" class="athlete-entry" data-id="<?php echo $a->athlete_id; ?>">
                                                                <?php echo $a->athlete_name; ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo $a->gender; ?></td>
                                                        <td><?php echo $a->birth_date; ?></td>
                                                    </tr>
                                            <?php
                                                endif;
                                            endforeach;
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>


                <?php
                    $index++;
                    endforeach;
                endif;
                ?>
            </div>


            <div class="vespa-escorts" style="margin-top:40px;">
                <h1>Kísérők</h1>

                <?php
                $kisero_data = vespa_get_default_escort_data();
                $kr = vespa_get_escorts(vespa_get_my_school_id(), $record->contest_id);
                if ($kr != null) {
                    $kisero_data = unserialize($kr->escort_data);
                }
                ?>

                <button class="button" type="button" onclick="editEscorts( this );">Kísérők, egyéb adatok megadása</button>

                <form action="" class="ajax-form" id="vespa-escorts-form" style="display:none;">
                    <input type="hidden" name="action" value="save_escorts" autocomplete="off">
                    <input type="hidden" name="school_id" value="<?php echo vespa_get_my_school_id(); ?>" autocomplete="off">
                    <input type="hidden" name="contest_id" value="<?php echo $record->contest_id; ?>" autocomplete="off">

                    <div class="row">
                        <div class="col-md-12">
                            <h4 class="text-center">Kísérők</h4>

                            <div class="row kisero-row">
                                <div class="col-md-3">Név</div>
                                <div class="col-md-3">Mobil telefonszám</div>
                                <div class="col-md-3">E-mail</div>
                                <div class="col-md-3">Étel allergia</div>
                            </div>

                            <?php for ($i = 0; $i < 5; $i++) : ?>
                                <div class="row kisero-row">
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="<?php echo "kiserok_nev[$i]" ?>" id="kiserok_nev[]" value="<?php echo (isset($kisero_data['kiserok'][$i]) ? $kisero_data['kiserok'][$i]['nev'] : ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="kiserok_mobil[]" id="kiserok_mobil[]" value="<?php echo (isset($kisero_data['kiserok'][$i]) ? $kisero_data['kiserok'][$i]['mobil'] : ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="kiserok_email[]" id="kiserok_email[]" value="<?php echo (isset($kisero_data['kiserok'][$i]) ? $kisero_data['kiserok'][$i]['email'] : ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="kiserok_allergia[]" id="kiserok_allergia[]" value="<?php echo (isset($kisero_data['kiserok'][$i]) ? $kisero_data['kiserok'][$i]['allergia'] : ''); ?>">
                                    </div>
                                </div>
                            <?php endfor; ?>

                        </div>

                        <div class="col-md-12">
                            <h4 class="text-center">Gépkocsi vezetők</h4>


                            <div class="row kisero-row">
                                <div class="col-md-3">Név</div>
                                <div class="col-md-3">Mobil telefonszám</div>
                                <div class="col-md-3">E-mail</div>
                                <div class="col-md-3">Étel allergia</div>
                            </div>

                            <?php for ($i = 0; $i < 5; $i++) : ?>
                                <div class="row kisero-row">
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="<?php echo "gepkocsivezeto_nev[$i]" ?>" id="gepkocsivezeto_nev[]" value="<?php echo (isset($kisero_data['gepkocsivezetok'][$i]) ? $kisero_data['gepkocsivezetok'][$i]['nev'] : ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="gepkocsivezeto_mobil[]" id="gepkocsivezeto_mobil[]" value="<?php echo (isset($kisero_data['gepkocsivezetok'][$i]) ? $kisero_data['gepkocsivezetok'][$i]['mobil'] : ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="gepkocsivezeto_email[]" id="gepkocsivezeto_email[]" value="<?php echo (isset($kisero_data['gepkocsivezetok'][$i]) ? $kisero_data['gepkocsivezetok'][$i]['email'] : ''); ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="gepkocsivezeto_allergia[]" id="gepkocsivezeto_allergia[]" value="<?php echo (isset($kisero_data['gepkocsivezetok'][$i]) ? $kisero_data['gepkocsivezetok'][$i]['allergia'] : ''); ?>">
                                    </div>
                                </div>
                            <?php endfor; ?>

                        </div>
                    </div>


                    <div class="row">
                        <div class="col-md-12">
                            <h4 class="text-center">Verseny helyszínén történő alvás esetén:</h4>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="">Helyszínen alvó diákok létszáma</label>
                                        <input type="text" name="diak_ossz" id="diak_ossz" class="form-control" value="<?php echo $kisero_data['diak']['letszam']; ?>">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="">Ebből fiú:</label>
                                        <input type="text" name="diak_fiu" id="diak_fiu" class="form-control" value="<?php echo $kisero_data['diak']['fiu']; ?>">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="">Ebből lány:</label>
                                        <input type="text" name="diak_lany" id="diak_lany" class="form-control" value="<?php echo $kisero_data['diak']['lany']; ?>">
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="">Diákok alvással kapcsolatos egyéb igény, kérés:</label>
                                        <textarea name="diak_igeny" id="diak_igeny" cols="30" rows="10" class="form-control"><?php echo $kisero_data['diak']['igeny']; ?></textarea>
                                    </div>
                                </div>
                            </div>


                            <div class="col-md-6">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="">Helyszínen alvó kísérők létszáma</label>
                                        <input type="text" name="kiserok_ossz" id="kiserok_ossz" class="form-control" value="<?php echo $kisero_data['kisero']['letszam']; ?>">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="">Ebből férfi:</label>
                                        <input type="text" name="kiserok_ferfi" id="kiserok_ferfi" class="form-control" value="<?php echo $kisero_data['kisero']['ferfi']; ?>">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="">Ebből nő:</label>
                                        <input type="text" name="kiserok_no" id="kiserok_no" class="form-control" value="<?php echo $kisero_data['kisero']['no']; ?>">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="">Ebből házastársak:</label>
                                        <input type="text" name="kiserok_hazas" id="kiserok_hazas" class="form-control" value="<?php echo $kisero_data['kisero']['hazas']; ?>">
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="">Kísérők alvással kapcsolatos egyéb igény, kérés:</label>
                                        <textarea name="kiserok_igeny" id="kiserok_igeny" cols="30" rows="10" class="form-control"><?php echo $kisero_data['kisero']['igeny']; ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-md-12">
                            <h4 class="text-center">Verseny helyszínén történő étkezés esetén:</h4>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="">Diákok létszáma</label>
                                        <input type="text" name="etkezes_diak_ossz" id="etkezes_diak_ossz" class="form-control" value="<?php echo $kisero_data['etkezes']['diak']['letszam']; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="">Helyszini reggeli</label>
                                        <input type="text" name="etkezes_diak_reggeli" id="etkezes_diak_reggeli" class="form-control" value="<?php echo $kisero_data['etkezes']['diak']['reggeli']; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="">Helyszini ebéd</label>
                                        <input type="text" name="etkezes_diak_ebed" id="etkezes_diak_ebed" class="form-control" value="<?php echo $kisero_data['etkezes']['diak']['ebed']; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="">Helyszini vacsora</label>
                                        <input type="text" name="etkezes_diak_vacsora" id="etkezes_diak_vacsora" class="form-control" value="<?php echo $kisero_data['etkezes']['diak']['vacsora']; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="">Úti csomag</label>
                                        <input type="text" name="etkezes_diak_uti" id="etkezes_diak_uti" class="form-control" value="<?php echo $kisero_data['etkezes']['diak']['uti']; ?>">
                                    </div>
                                </div>

                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="">Diákok étkezésével kapcsolatos kérés:</label>
                                        <textarea name="etkezes_diak_igeny" id="etkezes_diak_igeny" cols="30" rows="10" class="form-control"><?php echo $kisero_data['etkezes']['diak']['igeny']; ?></textarea>

                                        <p>
                                            A diákok étkezésével kapcsolatos egyedi igényeket, érzékenység, alergia adatokat a diakok személyi adatlapján kell megadni!
                                        </p>
                                    </div>
                                </div>
                            </div>



                            <div class="col-md-6">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="">Kísérők létszáma</label>
                                        <input type="text" name="etkezes_kisero_ossz" id="etkezes_kisero_ossz" class="form-control" value="<?php echo $kisero_data['etkezes']['kisero']['letszam']; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="">Helyszini reggeli</label>
                                        <input type="text" name="etkezes_kisero_reggeli" id="etkezes_kisero_reggeli" class="form-control" value="<?php echo $kisero_data['etkezes']['kisero']['reggeli']; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="">Helyszini ebéd</label>
                                        <input type="text" name="etkezes_kisero_ebed" id="etkezes_kisero_ebed" class="form-control" value="<?php echo $kisero_data['etkezes']['kisero']['ebed']; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="">Helyszini vacsora</label>
                                        <input type="text" name="etkezes_kisero_vacsora" id="etkezes_kisero_vacsora" class="form-control" value="<?php echo $kisero_data['etkezes']['kisero']['vacsora']; ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="">Úti csomag</label>
                                        <input type="text" name="etkezes_kisero_uti" id="etkezes_kisero_uti" class="form-control" value="<?php echo $kisero_data['etkezes']['kisero']['uti']; ?>">
                                    </div>
                                </div>

                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="">Kísérők étkezésével kapcsolatos kérés:</label>
                                        <textarea name="etkezes_kisero_igeny" id="etkezes_kisero_igeny" cols="30" rows="10" class="form-control"><?php echo $kisero_data['etkezes']['kisero']['igeny']; ?></textarea>

                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div id="escort_success"></div>
                            <button type="submit" class="button">Adatok mentése</button>
                        </div>
                    </div>

                </form>
            </div>


        </div>
    </div>