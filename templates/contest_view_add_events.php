<?php

if (current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles)) :
    $sports       = $wpdb->get_results("SELECT * FROM vespa_sports WHERE is_deleted=0 ORDER BY sport_name ASC");
    $events       = $wpdb->get_results("SELECT * FROM vespa_sport_events WHERE is_deleted=0 ORDER BY sport_event_name ASC");
    $disabilities = $wpdb->get_results("SELECT * FROM vespa_disability_groups ORDER BY disability_group_name ASC");

?>

    <div class="vespa-box" id="versenyszamok">
        <div class="row">
            <div class="col-md-6">
                <h3 style="margin-top:0">Versenyszámok hozzáadása</h3>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <label class="hl" id="fogyi">Fogyatékossági csoport</label> <br>


                <?php foreach ($disabilities as $dis) : ?>
                    <label class="chlist-label">
                        <input type="checkbox" class="ch-disability" data-id="<?php echo $dis->disability_group_id; ?>">
                        <?php echo $dis->disability_group_name; ?>
                    </label>

                <?php endforeach; ?>

            </div>

            <div class="col-md-3">
                <label class="hl">Sport</label> <br>

                <select class="form-control" id="sport_id" name="sport_id" autocomplete="off" onchange="filterEvents();" style="margin-bottom:10px">
                    <?php foreach ($sports as $sport) : ?>
                        <option value="<?php echo $sport->sport_id; ?>"><?php echo $sport->sport_name; ?></option>
                    <?php endforeach; ?>
                </select>

                <?php foreach ($events  as $event) : ?>
                    <label class="chlist-label">
                        <input type="checkbox" class="ch-sport" data-id="<?php echo $event->sport_event_id; ?>" data-sport_id="<?php echo $event->sport_id; ?>">
                        <?php echo $event->sport_event_name; ?>
                    </label>
                <?php endforeach; ?>

            </div>

            <div class="col-md-4">
                <label class="hl" id="agh">Korcsoportok</label> <br>

                <div class="row">
                    <div class="col-md-12" style="margin-bottom: 20px;">
                        <?php foreach ($age_groups as $ag) : ?>
                            <label class="chlist-label">
                                <input type="checkbox" class="ch-agegroup" data-id="<?php echo $ag->id; ?>">
                                <?php echo $ag->date_from . ' - ' . $ag->date_to; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                </div>

                <div class="row">
                    <div class="col-md-12" style="margin-top:10px">
                        <select id="gender" class="form-control">
                            <option value="Fiú Lány">Fiú Lány</option>
                            <option value="Fiú">Fiú</option>
                            <option value="Lány">Lány</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="col-md-2" id="add_event_feedback">
                <button class="btn btn-primary form-control" onclick="saveContestRace();">Hozzáad</button>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <table class="table table-striped" id="ajax_table_contest_races">
                    <tr>
                        <td>Betöltés...</td>
                    </tr>
                </table>

                <script>
                    jQuery(document).ready(function($) {
                        ajax_table_contest_races_reload();
                    });
                </script>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12" style="text-align:center; margin-top:30px">
                <button class="btn btn-primary" style="font-size:16px;" onclick="finalizeContest();">Véglegesítés, verseny nevezhetővé tétele</button>
            </div>
        </div>
    </div>

<?php
endif;
?>