<?php
global $wpdb;

$site_title = 'Új sportoló felvétele';
$id         = $_GET['id'];
$record     = null;
$school     = null;
$schoolId   = null;
$schoolName = null;
$sql = "SELECT * FROM vespa_institutions ";

if (!(VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::FOVESZ_FODISZ_SPORTIGAZGATO) ||
    VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::ADMINISZTRATOR) ||
    is_super_admin())) {
    $schoolId = get_user_meta(get_current_user_id(), 'school_id', true);
    $sql = $sql . "WHERE institution_id=%d";
}

if (is_numeric($id) && $id > 0) {
    $record = $GLOBALS['VESPA_Athletes']->load($id);
    //print_r($record);
    $site_title = $record->athlete_name . ' - szerkesztése';
    if (!is_null($schoolId)) {
        if ($record->school_id != $schoolId) {
            echo '<h1 class="site-title">Nincs jogosultsága ehhez az oldalhoz!</h1>';
            die;
        }
    }
}

$institutions = $wpdb->get_results($wpdb->prepare($sql, $schoolId));


if (!is_null($schoolId)) {
    foreach ($institutions as $ins) {
        if ($ins->institution_id == $schoolId) {
            $schoolName = $ins->ins_name;
            break;
        }
    }
}

$instarray = array();
foreach ($institutions as $institution) {
    if ($record != null && $record->school_id == $institution->institution_id) {
        $school = $institution;
    }

    $element = array(
        'value' => $institution->institution_id,
        'label' => $institution->ins_name,
    );
    $instarray[] = $element;
}

$sql2 = 'SELECT * FROM vespa_disability_groups';
$disabilities = $wpdb->get_results($sql2);
?>



<?php
if (!current_user_can(VESPA_Roles::sportolok_letrehozasa_modositasa)) :
?>
    <div class="wrap">
        <div class="row">
            <div class="col-md-12">
                <h1 class="site-title">Nincs jogosultsága ehhez az oldalhoz!</h1>
            </div>
        </div>
    </div>
<?php
    exit;
endif;
?>



<div class="wrap">
    <div class="row">
        <div class="col-md-12">
            <h1 class="site-title"><?php echo $site_title; ?></h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <h3>Alapadatok</h3>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <form action="" class="ajax-form" method="POST">
                <input type="hidden" name="action" autocomplete="off" value="save_athletes">
                <input type="hidden" name="athlete_id" id="athlete_id" autocomplete="off" value="<?php echo $id; ?>">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Sportoló neve <span class="redstar">*</span></label>
                        <input type="text" class="form-control" name="athlete_name" id="athlete_name" autocomplete="off" value="<?php echo ($record == null ? '' : $record->athlete_name); ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Anyja neve <span class="redstar">*</span></label>
                        <input type="text" class="form-control" name="mothers_name" id="mothers_name" autocomplete="off" value="<?php echo ($record == null ? '' : $record->mothers_name); ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Születési hely <span class="redstar">*</span></label>
                        <input type="text" class="form-control" name="birth_place" id="birth_place" autocomplete="off" value="<?php echo ($record == null ? '' : $record->birth_place); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Születési idő <span class="redstar">*</span></label>
                        <input type="text" class="form-control birth-dpicker" name="birth_date" id="birth_date" autocomplete="off" value="<?php echo ($record == null ? '' : $record->birth_date); ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Iskola / egyesület <span class="redstar">*</span></label>

                        <input type="hidden" class="form-control" name="school_id" id="school_id" value="<?php echo ($record == null ? (is_null($schoolId) ?  '' : $schoolId) : $record->school_id); ?>">
                        <input type="text" class="form-control" name="school_name" id="school_name" <?php echo is_null($schoolId) ?  '' : 'disabled' ?> value="<?php echo ($school == null ? (is_null($schoolId) ?  '' : $schoolName) : $school->ins_name); ?>">
                    </div>
                </div>

                <script>
                    var institutions = <?php echo json_encode($instarray) ?>;

                    jQuery(document).ready(function() {
                        jQuery("#school_name").autocomplete({
                            source: institutions,
                            select: function(event, ui) {
                                event.preventDefault();

                                jQuery('#school_id').val(ui.item.value);
                                jQuery('#school_name').val(ui.item.label);
                            }
                        });
                    });
                </script>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Aktív</label>
                        <select name="active" id="active" class="form-control input-sm">
                            <?php if ($record != null && $record->active == null) : ?> // mivel a DB enged null-t, inkább megjelenítem, esetleges import hibák miatt
                                <option value="">
                                    Válasszon!
                                </option>
                            <?php endif ?>
                            <option value="1" <?php echo ($record != null && $record->active == 1 ? 'selected' : ''); ?>>
                                Igen
                            </option>
                            <option value="0" <?php echo ($record != null && $record->active != null && $record->active == 0 ? 'selected' : ''); ?>>
                                Nem
                            </option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <h3>Kapcsolati adatok</h3>
                    </div>
                </div>


                <div class="col-md-6">
                    <div class="form-group">
                        <label>Telefonszám</label>
                        <input type="text" pattern="(\+{0,1}[0-9]+)" title="A telefonszámok csak számokat tartalmazhatnak, illetve kezdődhet 1 darab + jellel." class="form-control" name="phone" id="phone" autocomplete="off" value="<?php echo ($record == null ? '' : $record->phone); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="text" class="form-control" name="email" id="email" autocomplete="off" value="<?php echo ($record == null ? '' : $record->email); ?>">
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label>Irányítószám</label>
                        <input type="text" class="form-control" name="home_zipcode" id="home_zipcode" autocomplete="off" value="<?php echo ($record == null ? '' : $record->home_zipcode); ?>">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label>Település</label>
                        <input type="text" class="form-control" name="home_city" id="home_city" autocomplete="off" value="<?php echo ($record == null ? '' : $record->home_city); ?>">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label>Lakcím</label>
                        <input type="text" class="form-control" name="home_address" id="home_address" autocomplete="off" value="<?php echo ($record == null ? '' : $record->home_address); ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <h3>Személyi adatok</h3>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Állampolgárság</label>
                        <input type="text" class="form-control" name="nationality" id="nationality" autocomplete="off" value="<?php echo ($record == null ? '' : $record->nationality); ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>Nyilvántartásba vétele</label>
                        <input type="text" class="form-control dpicker" name="registered_at" id="registered_at" autocomplete="off" value="<?php echo ($record == null ? '' : $record->registered_at); ?>">
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Személyiszám/Útlevélszám</label>
                        <input type="text" class="form-control" name="personal_id" id="personal_id" autocomplete="off" value="<?php echo ($record == null ? '' : $record->personal_id); ?>">
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Nem (férfi/nő) <span class="redstar">*</span></label>
                        <select name="gender" id="gender" class="form-control">
                            <?php
                            $selected = fn ($gender) => $record != null && $record->gender == $gender ? "value='$gender' selected" : "value='$gender'";
                            echo "<option " . $selected(null) . " >nincs kitöltve</option>";
                            echo "<option " . $selected('férfi') . ">férfi</option>";
                            echo "<option " . $selected('nő') . ">nő</option>";
                            ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Fogyatékosság típusa</label>
                        <select name="disability_type" id="disability_type" class="form-control input-sm">
                            <?php foreach ($disabilities as $disability) : ?>
                                <option value="<?php echo $disability->disability_group_id ?>" <?php echo ($record != null && $record->disability_type == $disability->disability_group_id  ? 'selected' : ''); ?>>
                                    <?php echo $disability->disability_group_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <h3>Megjegyzés</h3>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label>Publikus megjegyzés</label>
                        <textarea class="form-control" name="note" id="note" autocomplete="off"><?php echo ($record == null ? '' : $record->note); ?></textarea>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Privát megjegyzés</label>
                        <textarea class="form-control" name="private_note" id="private_note" autocomplete="off"><?php echo ($record == null ? '' : $record->private_note); ?></textarea>
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

<?php
// A Szintidők szekció szándékosan a fő <form>-on KÍVÜL él, hogy semmiképp
// ne zavarhassa a sportoló-mentést. Csak meglévő sportolónál jelenik meg
// (új felvételnél még nincs athlete_id, amihez az időt kötni lehetne).
if ($record !== null && current_user_can(VESPA_Roles::sportolok_letrehozasa_modositasa)) :
    $vespa_szintido_nonce = wp_create_nonce('vespa_nonce');
    $vespa_szintido_esemenyek = $wpdb->get_results("SELECT * FROM vespa_sport_events WHERE is_deleted=0 ORDER BY sport_event_name ASC");
    $vespa_szintido_lista = vespa_szintido_athlete_times($record->athlete_id);
?>
<div class="wrap vespa-szintido" data-nonce="<?php echo esc_attr($vespa_szintido_nonce); ?>" data-athlete="<?php echo esc_attr($record->athlete_id); ?>">
    <div class="row">
        <div class="col-md-12">
            <h3>Szintidők</h3>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <table class="table table-striped vespa-szintido-tabla">
                <thead>
                    <tr>
                        <th>Sportesemény</th>
                        <th>Idő</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody class="vespa-szintido-sorok"></tbody>
            </table>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label>Sportesemény</label>
                <select class="form-control vespa-szintido-esemeny">
                    <?php foreach ($vespa_szintido_esemenyek as $se) : ?>
                        <option value="<?php echo esc_attr($se->sport_event_id); ?>"><?php echo esc_html($se->sport_event_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>Idő</label>
                <input type="text" class="form-control vespa-szintido-ido" placeholder="pl. 14.84 vagy 1:02.5" autocomplete="off">
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="button" class="btn btn-primary form-control vespa-szintido-ment">Mentés</button>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <p class="vespa-szintido-uzenet"></p>
        </div>
    </div>
</div>

<script>
(function () {
    var doboz = document.querySelector('.vespa-szintido');
    if (!doboz) return;

    var nonce = doboz.getAttribute('data-nonce');
    var athleteId = doboz.getAttribute('data-athlete');
    var url = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';
    var sorok = doboz.querySelector('.vespa-szintido-sorok');
    var uzenetEl = doboz.querySelector('.vespa-szintido-uzenet');
    var idok = <?php echo json_encode($vespa_szintido_lista, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE); ?>;

    function uzen(szoveg) {
        uzenetEl.textContent = szoveg || '';
    }

    function kuld(action, adatok) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        fd.append('athlete_id', athleteId);
        Object.keys(adatok).forEach(function (k) { fd.append(k, adatok[k]); });
        return fetch(url, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); });
    }

    // Minden szöveg value/textContent útján kerül be, sehol nincs
    // innerHTML-interpoláció.
    function rajzol() {
        sorok.textContent = '';

        if (idok.length === 0) {
            var ures = document.createElement('tr');
            var td = document.createElement('td');
            td.colSpan = 3;
            td.textContent = 'Még nincs rögzített szintidő.';
            ures.appendChild(td);
            sorok.appendChild(ures);
            return;
        }

        idok.forEach(function (ido) {
            var tr = document.createElement('tr');

            var tdNev = document.createElement('td');
            tdNev.textContent = ido.sport_event_name;
            tr.appendChild(tdNev);

            var tdIdo = document.createElement('td');
            tdIdo.textContent = ido.formatted;
            tr.appendChild(tdIdo);

            var tdMuvelet = document.createElement('td');
            var torol = document.createElement('button');
            torol.type = 'button';
            torol.className = 'btn btn-cancel';
            torol.textContent = 'Törlés';
            torol.addEventListener('click', function () {
                torol.disabled = true;
                kuld('vespa_szintido_delete', { sport_event_id: ido.sport_event_id })
                    .then(function (resp) {
                        torol.disabled = false;
                        if (!resp.success) { uzen(resp.data.message); return; }
                        idok = resp.data.times;
                        rajzol();
                        uzen(resp.data.message);
                    }).catch(function () { torol.disabled = false; uzen('Hálózati hiba.'); });
            });
            tdMuvelet.appendChild(torol);
            tr.appendChild(tdMuvelet);

            sorok.appendChild(tr);
        });
    }

    doboz.querySelector('.vespa-szintido-ment').addEventListener('click', function () {
        var gomb = doboz.querySelector('.vespa-szintido-ment');
        var esemenySelect = doboz.querySelector('.vespa-szintido-esemeny');
        var idoInput = doboz.querySelector('.vespa-szintido-ido');

        gomb.disabled = true;
        kuld('vespa_szintido_save', {
            sport_event_id: esemenySelect.value,
            ido: idoInput.value
        }).then(function (resp) {
            gomb.disabled = false;
            if (!resp.success) { uzen(resp.data.message); return; }
            idok = resp.data.times;
            idoInput.value = '';
            rajzol();
            uzen(resp.data.message);
        }).catch(function () { gomb.disabled = false; uzen('Hálózati hiba.'); });
    });

    rajzol();
})();
</script>
<?php endif; ?>