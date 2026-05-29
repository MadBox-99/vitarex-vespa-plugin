    <div class="vespa-box" id="versenyszamok">
        <div class="row">
            <div class="col-md-6">
                <h3 style="margin-top:0">Versenyszámok</h3>
            </div>

            <div class="col-md-6">
                <?php if (current_user_can(VESPA_Roles::versenyek_kezelese_kiiras_modositas_torles)) : ?>
                    <button class="btn btn-default pull-right" onclick="showContestRaceEditor( 0 );">Hozzáad</button>
                <?php endif; ?>
            </div>

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
    </div>


    <!-- Modal -->
    <?php
    $sports = $wpdb->get_results("SELECT * FROM vespa_sports WHERE is_deleted=0 ORDER BY sport_name ASC");
    $events = $wpdb->get_results("SELECT * FROM vespa_sport_events WHERE is_deleted=0 ORDER BY sport_event_name ASC");
    ?>
    <div class="modal fade" id="contest_race_editor" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"></h4>
                </div>

                <input type="hidden" id="action" name="action" value="edit_contest_race" autocomplete="off">
                <input type="hidden" id="record_id" name="record_id" value="0" autocomplete="off">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="">Sport</label>
                        <select class="form-control" id="sport_id" name="sport_id" autocomplete="off" onchange="filterEvents();">
                            <?php foreach ($sports as $sport) : ?>
                                <option value="<?php echo $sport->sport_id; ?>"><?php echo $sport->sport_name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="">Versenyszám</label>
                        <select class="form-control" id="event_id" name="event_id" autocomplete="off">
                        </select>

                        <select class="form-control" id="event_tpl" name="event_tpl" autocomplete="off" style="display:none;">
                            <?php foreach ($events  as $event) : ?>
                                <option value="<?php echo $event->sport_event_id; ?>" data-sport_id="<?php echo $event->sport_id; ?>">
                                    <?php echo $event->sport_event_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Mégsem</button>
                    <button type="button" class="btn btn-danger" onclick="saveContestRace();">Mentés</button>
                </div>
            </div>
        </div>
    </div>













    <div class="vespa-box" id="jelentkezok">
        <div class="row">
            <div class="col-md-6">
                <h3 style="margin-top:0">Nevezettek</h3>
            </div>

            <div class="col-md-6">
                <!-- iskola igazgató jelentkezhet -->

                <?php if (VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ISKOLAIGAZGATO) && !vespa_school_entered_contest($id)) : ?>
                    <button class="btn btn-default pull-right" onclick="vespa_school_entry(<?php echo $id; ?>);">Nevezés</button>
                <?php endif; ?>
            </div>

            <div class="col-md-12">
                <table class="table table-striped" id="ajax_table_contest_entries">
                    <tr>
                        <td>Betöltés...</td>
                    </tr>
                </table>

                <script>
                    jQuery(document).ready(function($) {
                        ajax_table_contest_entries_reload();
                    });
                </script>
            </div>
        </div>
    </div>


    <!-- Modal -->
    <?php
    $filter = vespa_get_contest_athlete_filter("1", $record);

    $athletes = $wpdb->get_results("SELECT * FROM vespa_athletes WHERE $filter AND is_deleted=0 ORDER BY athlete_name ASC");
    ?>
    <div class="modal fade" id="contest_entry_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Sportolók nevezése</h4>
                </div>


                <div class="modal-body" style="height:50vh; overflow:auto;">
                    <div class="form-group" style="display:none">
                        <input type="text" class="form-control" placeholder="A szűréshez gépelj" autocomplete="off">
                    </div>

                    <input type="hidden" id="entry_event_id" autocomplete="off" value="0">

                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th width="50">&nbsp;</th>
                                <th>Név</th>
                                <th width="150">Születési idő</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($athletes as $athlete) :
                            ?>
                                <tr data-name="<?php echo $athlete->athlete_name; ?>">
                                    <td><input type="checkbox" data-id="<?php echo $athlete->athlete_id; ?>" class="athlete-ch" autocomplete="off"></td>
                                    <td><?php echo $athlete->athlete_name; ?></td>
                                    <td><?php echo $athlete->birth_date; ?></td>
                                </tr>
                            <?php
                            endforeach;
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Mégsem</button>
                    <button type="button" class="btn btn-primary" onclick="vespa_entry_athletes();">Kijelöltek nevezése</button>
                </div>
            </div>
        </div>
    </div>












    <!-- Modal -->
    <?php
    $insts = $wpdb->get_results("SELECT * FROM vespa_institutions ORDER BY ins_name ASC");
    ?>
    <div class="modal fade" id="contest_escort_editor" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"></h4>
                </div>

                <input type="hidden" id="action" name="action" value="edit_contest_escort" autocomplete="off">
                <input type="hidden" id="record_id" name="record_id" value="0" autocomplete="off">

                <div class="modal-body">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Kísérő neve</label>
                            <input type="text" class="form-control" required name="escort_name" id="escort_name" autocomplete="off" value="">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Kísérő email</label>
                            <input type="email" class="form-control" name="escort_email" id="escort_email" autocomplete="off" value="">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Kísérő telefon</label>
                            <input type="text" class="form-control" name="escort_phone" id="escort_phone" autocomplete="off" value="">
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Mégsem</button>
                    <button type="button" class="btn btn-danger" onclick="saveContestEscort();">Mentés</button>
                </div>
            </div>
        </div>
    </div>