<div class="vespa-box" id="versenyszamok">
    <div class="row">
        <div class="col-md-12">
            <h3 style="margin-top:0">Versenyszámok</h3>

            <?php
            $list = null;
            if(is_numeric($record->contest_id)){

            $list = $wpdb->get_results($wpdb->prepare("SELECT e.*, p.sport_name, s.sport_event_name, p.sport_id 
                                FROM vespa_constest_events as e 
                                JOIN vespa_sports as p ON p.sport_id = e.sport_id 
                                LEFT JOIN vespa_sport_events as s ON s.sport_event_id = e.event_id    
                                WHERE e.contest_id=%d", $record->contest_id));
            }
            $disablityList = $wpdb->get_results("SELECT * FROM vespa_disability_groups");

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

            if (!empty($list)) :
            ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Sportág</th>
                            <th>Versenyszám</th>
                            <th>Korosztály</th>
                            <th>Fogyatékossági csoportok</th>
                            <th>Nevezhető / nevezett sportolók</th>
                            <th width="150">&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($list as $race) :
                            $entered_sql = "
                            SELECT a.athlete_id 
                            FROM vespa_athlete_entries e 
                            JOIN vespa_athletes a ON a.athlete_id = e.athlete_id 
                            WHERE e.contest_id=%d AND e.contest_event_id=%d AND FIND_IN_SET(a.disability_type, '%s')";

                            $entered = $wpdb->get_results($wpdb->prepare($entered_sql,$record->contest_id,$race->id,$race->disability_groups ));



                            $sql = "SELECT e.*, a.school_id, a.athlete_name, a.birth_date,p.sport_name, s.sport_event_name, i.ins_name, i.id_number, i.institution_id, a.gender,d.disability_group_name, a.disability_type, e.contest_event_id, st.state_name              
                                        FROM vespa_athlete_entries as e 
                                        JOIN vespa_athletes as a ON a.athlete_id = e.athlete_id 
                                        JOIN vespa_constest_events as e2 ON e2.id=%d AND e2.contest_id=e.contest_id AND FIND_IN_SET(a.disability_type, e2.disability_groups) 
                                        JOIN vespa_sports as p ON p.sport_id = e2.sport_id 
                                        LEFT JOIN vespa_sport_events as s ON s.sport_event_id = e2.event_id   
                                        JOIN vespa_disability_groups as d ON d.disability_group_id = a.disability_type
                                        LEFT JOIN vespa_institutions as i ON i.institution_id = a.school_id
                                        LEFT JOIN vespa_states as st ON st.state_id = i.ins_state
                                        WHERE e.contest_id=%d AND e.contest_event_id=%d
                                        ORDER BY ins_name ASC, a.gender ASC, a.birth_date ASC, athlete_name ASC";

                            $athletes = $wpdb->get_results($wpdb->prepare($sql,$race->id,$record->contest_id,$race->id));
                            

                            $inst_ids = [];
                            foreach ($athletes as $ath) {
                                array_push($inst_ids, $ath->institution_id);
                            }
                            $inst_ids = array_unique($inst_ids);
                            $multi = count($inst_ids) > 0 ? count($inst_ids) : 1;
                            $sql2 = "SELECT DISTINCT cag.*, ag.agegroup_name 
                                        FROM vespa_contest_agegroups cag
                                        JOIN vespa_agegroups ag ON cag.agegroup_id = ag.agegroup_id
                                        WHERE cag.contest_id=%d 
                                        ORDER BY cag.date_from ASC, cag.date_to ASC";

                            $agegroups = $wpdb->get_results($wpdb->prepare($sql2, $record->contest_id));
                            
                            // echo $sql2 . '<br>';
                        ?>
                            <tr>
                                <td><?php echo $race->sport_name; ?></td>
                                <td><?php echo $race->sport_event_name; ?></td>
                                <td><?php echo $race->agnames; ?></td>
                                <td><?php foreach (mapIdsToNames($race->disability_groups, $disablityList) as $val) {
                                        echo "$val ";
                                    } ?></td>
                                <td>
                                    <span class=""><?php echo $multi * $record->ppl_num_max; ?></span>
                                    <span> / </span>
                                    <span id="entered-num"><?php echo count($entered); ?></span>

                                </td>

                                <td>
                                    <?php if (current_user_can(VESPA_Roles::verseny_eredmenyek_kezelese_rogzites_modositas) && $record->is_final == 1) : ?>
                                        <a href="<?php echo admin_url("admin.php?page=contests&id=$id&raceId=$race->id&action=results"); ?>">Eredmények rögzítése</a>
                                    <?php endif ?>
                                    <a href="#" onclick="showEntered(this, event);">Nevezettek listája</a>
                                </td>
                                <!-- <td>
                                    <a href="#" onclick="tovabbjutas(this, event);">Továbbjutók</a>
                                </td> -->
                            </tr>

                            <tr class="entered-athletes" style="display:none;">
                                <td colspan="5">
                                    <table class="table">
                                        <?php
                                        $athletesagegroups = [];
                                        $athletesGroups = [];
                                        foreach ($agegroups as $agegroup) {

                                            //csak azokat a versenyzőket keressük, akiknek az életkora az adott korcsoporton belülre esik, 
                                            //illetve a fogyatékossági csoportja is az adott versenyszámban van
                                            $athletesagegroups[$agegroup->agegroup_name] = array_filter($athletes, function ($athlete) use ($agegroup, $race) {
                                                return strtotime($athlete->birth_date) >= strtotime($agegroup->date_from)
                                                    && strtotime($athlete->birth_date) <= strtotime($agegroup->date_to);
                                                //&& str_contains($race->disability_groups, $athlete->disability_type);
                                            });
                                            if (isset($athletesagegroups[$agegroup->agegroup_name])) {
                                                //disgroup lista
                                                $disgroups = array_unique(array_map(fn ($element) => $element->disability_group_name, $athletesagegroups[$agegroup->agegroup_name]));
                                                foreach ($disgroups as $disgroup) {
                                                    $res = array_filter($athletesagegroups[$agegroup->agegroup_name], function ($athlete) use ($disgroup) {
                                                        return $athlete->gender == 'férfi' && $athlete->disability_group_name == $disgroup;
                                                    });
                                                    $athletesGroups[$agegroup->agegroup_name][$disgroup]['férfi'] = $res;

                                                    array_multisort(array_column($athletesGroups[$agegroup->agegroup_name][$disgroup]['férfi'], 'athlete_name'), SORT_ASC, $athletesGroups[$agegroup->agegroup_name][$disgroup]['férfi']);
                                                    $athletesGroups[$agegroup->agegroup_name][$disgroup]['nő'] = array_filter($athletesagegroups[$agegroup->agegroup_name], function ($athlete) use ($disgroup) {
                                                        return $athlete->gender === 'nő' && $athlete->disability_group_name == $disgroup;
                                                    });
                                                    array_multisort(array_column($athletesGroups[$agegroup->agegroup_name][$disgroup]['nő'], 'athlete_name'), SORT_ASC, $athletesGroups[$agegroup->agegroup_name][$disgroup]['nő']);
                                                }
                                            }
                                        }
                                        foreach ($athletesGroups as $agegroupKey => $athletesagegroup) :

                                            // csak azok számítanak, ahol van résztvevő
                                            if (!empty($athletesagegroup) && empty(array_values($athletesGroups[key($athletesGroups)])))
                                                continue;

                                            echo '<tr class="contest_entries-athlete">';
                                            echo '  <td colspan="4" style="font-weight:bold;">' . $agegroupKey . '</td>';
                                            echo '</tr>';
                                            foreach ($athletesagegroup as $athletedisgroupkey => $athletes_disgroup_filtered) :
                                                echo '<tr class="contest_entries-athlete">';
                                                echo '<td></td>';
                                                echo '  <td colspan="4" style="font-weight:bold;">' . $athletedisgroupkey . '</td>';
                                                echo '</tr>';
                                                foreach ($athletes_disgroup_filtered as $athletekey => $athletes_filtered) :
                                                    if (empty($athletes_filtered) || array_reduce(
                                                        $athletes_filtered,
                                                        function ($result, $athlete) use ($race) {
                                                            return !str_contains($race->disability_groups, $athlete->disability_type);
                                                        },
                                                        false
                                                    ))
                                                        continue;

                                                    echo '<tr class="contest_entries-athlete">';
                                                    echo '<td></td>';
                                                    echo '<td></td>';
                                                    echo '  <td colspan="4" style="font-weight:bold;">' . $athletekey . '</td>';
                                                    echo '</tr>';
                                                    foreach ($athletes_filtered as $athlete) :
                                                        if (!str_contains($race->disability_groups, $athlete->disability_type))
                                                            continue;
                                        ?>
                                                        <tr class="contest_entries-athlete">
                                                            <td></td>
                                                            <td></td>
                                                            <td><?php echo $athlete->athlete_name; ?></td>
                                                            <td><?php echo $athlete->birth_date; ?></td>
                                                            <td><?php echo $athlete->state_name; ?></td>
                                                            <td><?php echo $athlete->disability_group_name; ?></td>
                                                            <td><?php echo $athlete->ins_name . ' (' . $athlete->id_number . ')'; ?></td>
                                                            <td></td>
                                                        </tr>

                                        <?php
                                                        $prev = $athlete->gender;
                                                    endforeach;
                                                endforeach;
                                            endforeach;
                                        endforeach;
                                        ?>
                                    </table>
                                </td>
                            </tr>

                            <tr class="tovabbjutok" style="display:none;">
                                <td colspan="7">
                                    <?php echo "Résztvevők száma: " . count($athletes) ?>
                                    <table class="table">
                                        <th>Továbbjutó</th>
                                        <th>Sportoló neve</th>
                                        <th>Születési dátum</th>
                                        <th>Továbbjutási verseny</th>

                                        <?php
                                        //nem lehet UTF-8 rendezni :/
                                        // $coll = new Collator('hu_HU');
                                        //usort($athletes, fn ($a, $b) => $coll->compare($a->athlete_name,  $b->athlete_name));                                        
                                        // array_multisort(
                                        //     array_map(fn ($v) => iconv('utf-8', 'ascii//TRANSLIT', $v), $athletes),
                                        //     $athletes
                                        // );
                                        usort($athletes, fn ($a, $b) => strcasecmp($a->athlete_name,  $b->athlete_name));
                                        $kozos_verseny_megjelenitve = false;
                                        foreach ($athletes as $ath) : ?>
                                            <tr class="contest_entries-athlete">
                                                <td><input type="checkbox" /></td>
                                                <td><?php echo $ath->athlete_name; ?></td>
                                                <td><?php echo $ath->birth_date; ?></td>
                                                <?php if (!$kozos_verseny_megjelenitve) : ?>
                                                    <td rowspan="<?php echo count($athletes) ?>" style='text-align:center; vertical-align: middle;'>
                                                        <select name="tovabbjutasi_verseny_id" id="tovabbjutasi_verseny_id" class="form-control" style="width: 250px;margin: 8px 16px;">
                                                            <option value="0">Nincs kiválasztott verseny</option>
                                                            <?php
                                                            foreach ($fogyatekossag_lista as $fogyatekossagi_csoport) {
                                                                $selected = (isset($fogyatekossagi_csoport_id) && $fogyatekossagi_csoport_id == $fogyatekossagi_csoport->disability_group_id ? 'selected' : '');
                                                                echo '<option value="' . $fogyatekossagi_csoport->disability_group_id . '" ' . $selected . '>' . $fogyatekossagi_csoport->disability_group_name . '</option>';
                                                            }
                                                            ?>
                                                        </select>
                                                    </td>
                                                <?php
                                                    $kozos_verseny_megjelenitve = true;
                                                endif ?>
                                            </tr>
                                        <?php endforeach ?>
                                    </table>
                                </td>
                            </tr>
                        <?php
                        endforeach;
                        ?>
                    </tbody>
                </table>

            <?php
            endif;

            ?>
        </div>
    </div>
</div>