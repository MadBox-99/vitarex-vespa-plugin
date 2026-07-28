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
    // Szintidő-feltételek: opcionális, versenyszámonkénti minimum. Az
    // aktuális versenyszámok listáját ugyanazzal a join-nal kérdezzük le,
    // mint az ajax_table_contest_races (lásd includes/Ajax/ajax.contest_races.php).
    $vespa_szintido_admin_nonce = wp_create_nonce('vespa_nonce');
    $vespa_szintido_esemenyek = $wpdb->get_results($wpdb->prepare(
        "SELECT e.id, e.min_qualifying_seconds, p.sport_name, s.sport_event_name
         FROM vespa_constest_events as e
         LEFT JOIN vespa_sports as p ON p.sport_id = e.sport_id
         LEFT JOIN vespa_sport_events as s ON s.sport_event_id = e.event_id
         WHERE e.contest_id = %d
         ORDER BY p.sport_name ASC, s.sport_event_name ASC",
        $id
    ));
    ?>

    <div class="vespa-box" id="vespa-szintido-feltetelek" data-nonce="<?php echo esc_attr($vespa_szintido_admin_nonce); ?>">
        <div class="row">
            <div class="col-md-12">
                <h3 style="margin-top:0">Szintidő-feltételek</h3>
                <p>
                    Az üresen hagyott mező azt jelenti, hogy a versenyszámhoz nincs szintidő-feltétel: bárki
                    nevezhető. Ha megadsz egy időt, a nevezés attól kezdve elutasítja azokat a tanulókat, akiknek
                    nincs rögzített ideje az adott sporteseményre, vagy a rögzített idejük lassabb a megadottnál.
                </p>
            </div>
        </div>

        <?php if (empty($vespa_szintido_esemenyek)) : ?>
            <div class="row">
                <div class="col-md-12">
                    <p>Ehhez a versenyhez még nincs felvéve versenyszám.</p>
                </div>
            </div>
        <?php else : ?>
            <div class="row">
                <div class="col-md-12">
                    <table class="table table-striped vespa-szintido-tabla">
                        <thead>
                            <tr>
                                <th>Sport</th>
                                <th>Versenyszám</th>
                                <th width="200">Minimum szintidő</th>
                                <th width="150">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vespa_szintido_esemenyek as $cse) : ?>
                                <tr data-contest-event="<?php echo esc_attr($cse->id); ?>">
                                    <td><?php echo esc_html($cse->sport_name); ?></td>
                                    <td><?php echo esc_html($cse->sport_event_name); ?></td>
                                    <td>
                                        <input type="text" class="form-control vespa-szintido-min-input"
                                               placeholder="pl. 14.84 vagy 1:02.5"
                                               value="<?php echo $cse->min_qualifying_seconds !== null ? esc_attr(vespa_szintido_format($cse->min_qualifying_seconds)) : ''; ?>">
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-primary vespa-szintido-min-ment">Mentés</button>
                                        <span class="vespa-szintido-min-uzenet"></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        var doboz = document.getElementById('vespa-szintido-feltetelek');
        if (!doboz) return;

        var nonce = doboz.getAttribute('data-nonce');
        var url = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';

        Array.prototype.forEach.call(doboz.querySelectorAll('.vespa-szintido-min-ment'), function (gomb) {
            gomb.addEventListener('click', function () {
                var sor = gomb.closest('tr');
                var contestEventId = sor.getAttribute('data-contest-event');
                var input = sor.querySelector('.vespa-szintido-min-input');
                var uzenetEl = sor.querySelector('.vespa-szintido-min-uzenet');

                gomb.disabled = true;
                uzenetEl.textContent = '';

                var fd = new FormData();
                fd.append('action', 'vespa_szintido_set_min');
                fd.append('nonce', nonce);
                fd.append('contest_event_id', contestEventId);
                fd.append('ido', input.value);

                fetch(url, { method: 'POST', credentials: 'same-origin', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (resp) {
                        gomb.disabled = false;
                        if (!resp.success) { uzenetEl.textContent = resp.data.message; return; }
                        input.value = resp.data.formatted || '';
                        uzenetEl.textContent = resp.data.message;
                    })
                    .catch(function () { gomb.disabled = false; uzenetEl.textContent = 'Hálózati hiba.'; });
            });
        });
    })();
    </script>

<?php
endif;
?>