<?php
global $wpdb;
$admin_nonce = wp_create_nonce('vespa_szabadidos_admin');

$versenyek = $wpdb->get_results($wpdb->prepare(
    "SELECT vc.contest_id, vc.contest_name, vc.start_at, vc.end_at,
            (SELECT COUNT(*) FROM vespa_szabadidos_open_contests o WHERE o.contest_id=vc.contest_id) AS nyitva
     FROM vespa_contests AS vc
     WHERE vc.contest_type=%d
     ORDER BY vc.start_at DESC",
    4
));

// A front-end oldal `end_at >= most` feltétellel szűr, ezért a lejárt és a
// végdátum nélküli verseny akkor sem jelenik meg a résztvevőnél, ha itt meg
// van nyitva. Ezt jelezzük, különben a zöld pipa félrevezet.
$most = current_time('mysql');

// Zárványként, nem függvényként: a sablon többszöri betöltése esetén a
// függvénydefiníció "Cannot redeclare" fatális hibát adna.
$ures_datum = function ($ertek) {
    return empty($ertek) || $ertek === '0000-00-00 00:00:00';
};
$datum = function ($ertek) use ($ures_datum) {
    return $ures_datum($ertek) ? '—' : mysql2date('Y.m.d. H:i', $ertek);
};
?>
<div class="wrap" data-admin-nonce="<?php echo esc_attr($admin_nonce); ?>">
    <h1>Szabadidős külső nevezés</h1>

    <h2>Versenyek megnyitása külső regisztrációra</h2>
    <?php if (empty($versenyek)) : ?>
        <p>Nincs szabadidős (type-4) verseny.</p>
    <?php else : ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Verseny</th>
                    <th style="width:170px;">Kezdet</th>
                    <th style="width:170px;">Vége</th>
                    <th style="width:150px;">Külső regisztráció</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($versenyek as $v) : ?>
                <?php
                $nincs_vege = $ures_datum($v->end_at);
                $lejart     = !$nincs_vege && $v->end_at < $most;
                $rejtve     = $nincs_vege || $lejart;
                ?>
                <tr>
                    <td>
                        <?php echo esc_html($v->contest_name); ?>
                        <?php if ($rejtve && intval($v->nyitva) > 0) : ?>
                            <br>
                            <span style="color:#b32d2e; font-weight:600;">
                                <?php if ($nincs_vege) : ?>
                                    Nincs végdátuma — a résztvevők nem látják
                                <?php else : ?>
                                    Lejárt — a résztvevők nem látják
                                <?php endif; ?>
                            </span>
                        <?php elseif ($rejtve) : ?>
                            <br>
                            <span style="color:#8c8f94;">
                                <?php echo $nincs_vege ? 'Nincs végdátuma' : 'Lejárt'; ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($datum($v->start_at)); ?></td>
                    <td><?php echo esc_html($datum($v->end_at)); ?></td>
                    <td>
                        <label>
                            <input type="checkbox" class="vespa-szabadidos-toggle"
                                   data-contest="<?php echo esc_attr($v->contest_id); ?>"
                                   <?php checked(intval($v->nyitva) > 0); ?>>
                            Engedélyezve
                        </label>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">
            A résztvevők csak azokat a megnyitott versenyeket látják, amelyeknek a
            vége a jövőben van. A lejárt vagy végdátum nélküli verseny akkor sem
            jelenik meg nekik, ha itt be van pipálva.
        </p>
    <?php endif; ?>

    <h2>Verseny kiválasztása</h2>
    <?php
    $valasztott = isset($_GET['contest_id']) ? intval($_GET['contest_id']) : 0;

    // Az ÖSSZES type-4 verseny (nem csak a megnyitottak): a nevezési mezőket
    // a megnyitás előtt kell tudni beállítani.
    $valaszthato_versenyek = $wpdb->get_results($wpdb->prepare(
        "SELECT vc.contest_id, vc.contest_name, vc.start_at, vc.end_at,
                (SELECT COUNT(*) FROM vespa_szabadidos_open_contests o WHERE o.contest_id=vc.contest_id) AS nyitva
         FROM vespa_contests AS vc
         WHERE vc.contest_type=%d
         ORDER BY vc.start_at DESC",
        4
    ));
    ?>
    <form method="get">
        <input type="hidden" name="page" value="szabadidos_kulso">
        <label>Verseny:
            <select name="contest_id" onchange="this.form.submit()">
                <option value="0">— válassz —</option>
                <?php foreach ($valaszthato_versenyek as $nv) : ?>
                    <?php
                    // Az azonos nevű versenyek csak a dátumukban különböznek, ezért
                    // a név mellé kiírjuk azt is — enélkül nem megkülönböztethetők.
                    $nv_nincs_vege = $ures_datum($nv->end_at);
                    $nv_lejart     = !$nv_nincs_vege && $nv->end_at < $most;

                    $cimke = $nv->contest_name . ' — ' . $datum($nv->start_at);
                    if (intval($nv->nyitva) === 0) {
                        $cimke .= ' (nincs megnyitva)';
                    } elseif ($nv_nincs_vege) {
                        $cimke .= ' (nincs végdátuma, a résztvevők nem látják)';
                    } elseif ($nv_lejart) {
                        $cimke .= ' (lejárt, a résztvevők nem látják)';
                    }
                    ?>
                    <option value="<?php echo esc_attr($nv->contest_id); ?>" <?php selected($valasztott, intval($nv->contest_id)); ?>>
                        <?php echo esc_html($cimke); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <?php if ($valasztott > 0) : ?>
        <?php
        $mezo_lista = array();
        foreach (vespa_szabadidos_get_fields($valasztott, false) as $mezo) {
            $mezo_lista[] = array(
                'field_id'      => intval($mezo->field_id),
                'label'         => $mezo->label,
                'field_type'    => $mezo->field_type,
                'field_options' => $mezo->field_options === null ? '' : $mezo->field_options,
                'is_required'   => intval($mezo->is_required),
                'ordernum'      => intval($mezo->ordernum),
                'is_active'     => intval($mezo->is_active),
                'answer_count'  => vespa_szabadidos_field_answer_count($mezo->field_id),
            );
        }
        ?>
        <h2>Nevezési mezők</h2>
        <p class="description">
            Ezeket a mezőket a külső résztvevő a nevezéskor tölti ki, versenyenként
            egyszer. Minden sor külön mentődik. A törölt mező archiválódik, ha már
            érkezett rá válasz — a beérkezett adat nem vész el.
        </p>
        <div id="vespa-mezok"
             data-contest="<?php echo esc_attr($valasztott); ?>"
             data-types="<?php echo esc_attr(wp_json_encode(vespa_szabadidos_field_types())); ?>"
             data-fields="<?php echo esc_attr(wp_json_encode($mezo_lista)); ?>">
            <div class="vespa-mezo-lista"></div>
            <p>
                <button type="button" class="button button-secondary vespa-mezo-uj">+ Új mező</button>
                <span class="vespa-mezo-globalis-uzenet" style="margin-left:10px;"></span>
            </p>
        </div>
    <?php endif; ?>

    <h2>Külső nevezők</h2>

    <?php if ($valasztott > 0) : ?>
        <?php
        $nevezok = $wpdb->get_results($wpdb->prepare(
            "SELECT p.full_name, p.birth_date, p.gender, p.email, p.phone, e.entry_date, vse.sport_event_name, vs.sport_name
             FROM vespa_external_entries AS e
             INNER JOIN vespa_external_participants AS p ON p.participant_id=e.participant_id
             LEFT JOIN vespa_constest_events AS vce ON vce.id=e.contest_event_id
             LEFT JOIN vespa_sport_events AS vse ON vse.sport_event_id=vce.event_id
             LEFT JOIN vespa_sports AS vs ON vs.sport_id=vce.sport_id
             WHERE e.contest_id=%d
             ORDER BY p.full_name",
            $valasztott
        ));
        ?>
        <p>
            <a class="button" href="<?php echo esc_url(add_query_arg('vespa_szabadidos_export', $valasztott, home_url('/'))); ?>">XLSX export</a>
        </p>
        <?php if (empty($nevezok)) : ?>
            <p>Erre a versenyre még nincs külső nevező.</p>
        <?php else : ?>
            <table class="widefat">
                <thead><tr><th>Név</th><th>Szül. dátum</th><th>Nem</th><th>E-mail</th><th>Telefon</th><th>Versenyszám</th><th>Nevezés dátuma</th></tr></thead>
                <tbody>
                <?php foreach ($nevezok as $n) : ?>
                    <tr>
                        <td><?php echo esc_html($n->full_name); ?></td>
                        <td><?php echo esc_html($n->birth_date); ?></td>
                        <td><?php echo esc_html($n->gender); ?></td>
                        <td><?php echo esc_html($n->email); ?></td>
                        <td><?php echo esc_html($n->phone); ?></td>
                        <td><?php echo esc_html(trim(($n->sport_name ?: '') . ' ' . ($n->sport_event_name ?: ''))); ?></td>
                        <td><?php echo esc_html($n->entry_date); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    var wrap = document.querySelector('.wrap[data-admin-nonce]');
    if (!wrap) return;
    var nonce = wrap.getAttribute('data-admin-nonce');
    var url = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';

    wrap.querySelectorAll('.vespa-szabadidos-toggle').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var fd = new FormData();
            fd.append('action', 'vespa_szabadidos_toggle_open');
            fd.append('nonce', nonce);
            fd.append('contest_id', cb.getAttribute('data-contest'));
            fd.append('open', cb.checked ? '1' : '0');
            fetch(url, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (resp) { if (!resp.success) { alert(resp.data.message); cb.checked = !cb.checked; } })
                .catch(function () { alert('Hálózati hiba.'); cb.checked = !cb.checked; });
        });
    });
})();
</script>

<style>
.vespa-mezo-sor { border:1px solid #c3c4c7; background:#fff; padding:12px; margin-bottom:10px; }
.vespa-mezo-sor.archivalt { background:#f6f7f7; opacity:.75; }
.vespa-mezo-sor label { display:inline-block; margin-right:12px; }
.vespa-mezo-sor .vespa-mezo-cimke { width:100%; max-width:520px; }
.vespa-mezo-sor .vespa-mezo-opciok { width:100%; max-width:520px; }
.vespa-mezo-allapot { margin-left:10px; font-style:italic; color:#646970; }
.vespa-mezo-allapot.hiba { color:#b32d2e; font-style:normal; font-weight:600; }
.vespa-mezo-lab { margin-top:8px; }
</style>
<script>
(function () {
    var doboz = document.getElementById('vespa-mezok');
    if (!doboz) return;

    var burok = document.querySelector('.wrap[data-admin-nonce]');
    var nonce = burok.getAttribute('data-admin-nonce');
    var url = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';
    var contestId = doboz.getAttribute('data-contest');
    var tipusok = JSON.parse(doboz.getAttribute('data-types'));
    var mezok = JSON.parse(doboz.getAttribute('data-fields'));
    var lista = doboz.querySelector('.vespa-mezo-lista');
    var globalisUzenet = doboz.querySelector('.vespa-mezo-globalis-uzenet');

    function valasztosE(tipus) {
        return tipus === 'egyvalasztos' || tipus === 'tobbvalasztos';
    }

    function kuld(action, adatok) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        fd.append('contest_id', contestId);
        Object.keys(adatok).forEach(function (k) { fd.append(k, adatok[k]); });
        return fetch(url, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); });
    }

    // Egy sor felépítése. Minden szöveg value/textContent útján kerül be,
    // sehol nincs innerHTML-interpoláció.
    function sortEpit(mezo, index, aktivDb) {
        var sor = document.createElement('div');
        sor.className = 'vespa-mezo-sor' + (mezo.is_active ? '' : ' archivalt');

        var fej = document.createElement('p');

        if (mezo.is_active && mezo.field_id > 0) {
            var fel = document.createElement('button');
            fel.type = 'button';
            fel.className = 'button';
            fel.textContent = '▲';
            fel.disabled = (index === 0);
            fel.addEventListener('click', function () { mozgat(mezo.field_id, 'up'); });
            fej.appendChild(fel);

            var le = document.createElement('button');
            le.type = 'button';
            le.className = 'button';
            le.textContent = '▼';
            le.disabled = (index === aktivDb - 1);
            le.addEventListener('click', function () { mozgat(mezo.field_id, 'down'); });
            fej.appendChild(le);
            fej.appendChild(document.createTextNode(' '));
        }

        var cimke = document.createElement('input');
        cimke.type = 'text';
        cimke.className = 'vespa-mezo-cimke';
        cimke.placeholder = 'A kérdés szövege, pl. Milyen kerékpárral érkezel?';
        cimke.value = mezo.label || '';
        cimke.disabled = !mezo.is_active;
        fej.appendChild(cimke);
        sor.appendChild(fej);

        var tipusSor = document.createElement('p');

        var tipusCimke = document.createElement('label');
        tipusCimke.appendChild(document.createTextNode('Típus: '));
        var tipus = document.createElement('select');
        Object.keys(tipusok).forEach(function (kulcs) {
            var opt = document.createElement('option');
            opt.value = kulcs;
            opt.textContent = tipusok[kulcs];
            if (kulcs === mezo.field_type) opt.selected = true;
            tipus.appendChild(opt);
        });
        tipus.disabled = !mezo.is_active;
        tipusCimke.appendChild(tipus);
        tipusSor.appendChild(tipusCimke);

        var kotCimke = document.createElement('label');
        var kot = document.createElement('input');
        kot.type = 'checkbox';
        kot.checked = mezo.is_required === 1;
        kot.disabled = !mezo.is_active || mezo.field_type === 'nyilatkozat';
        kotCimke.appendChild(kot);
        kotCimke.appendChild(document.createTextNode(' Kötelező kitölteni'));
        tipusSor.appendChild(kotCimke);
        sor.appendChild(tipusSor);

        var opciokSor = document.createElement('p');
        var opciokCimke = document.createElement('label');
        opciokCimke.style.display = 'block';
        opciokCimke.appendChild(document.createTextNode('Válaszlehetőségek (soronként egy):'));
        var opciok = document.createElement('textarea');
        opciok.className = 'vespa-mezo-opciok';
        opciok.rows = 4;
        opciok.value = mezo.field_options || '';
        opciok.disabled = !mezo.is_active;
        opciokCimke.appendChild(document.createElement('br'));
        opciokCimke.appendChild(opciok);
        opciokSor.appendChild(opciokCimke);
        opciokSor.style.display = valasztosE(mezo.field_type) ? '' : 'none';
        sor.appendChild(opciokSor);

        // A nyilatkozat mindig kötelező, a válaszlehetőség pedig csak a két
        // választós típusnál értelmes — a felület ezt azonnal követi.
        tipus.addEventListener('change', function () {
            opciokSor.style.display = valasztosE(tipus.value) ? '' : 'none';
            if (tipus.value === 'nyilatkozat') {
                kot.checked = true;
                kot.disabled = true;
            } else {
                kot.disabled = false;
            }
            allapot('Nem mentett módosítás', false);
        });
        cimke.addEventListener('input', function () { allapot('Nem mentett módosítás', false); });
        opciok.addEventListener('input', function () { allapot('Nem mentett módosítás', false); });
        kot.addEventListener('change', function () { allapot('Nem mentett módosítás', false); });

        var lab = document.createElement('p');
        lab.className = 'vespa-mezo-lab';

        var allapotJel = document.createElement('span');
        allapotJel.className = 'vespa-mezo-allapot';

        function allapot(szoveg, hibaE) {
            allapotJel.textContent = szoveg;
            allapotJel.className = 'vespa-mezo-allapot' + (hibaE ? ' hiba' : '');
        }

        if (mezo.is_active) {
            var ment = document.createElement('button');
            ment.type = 'button';
            ment.className = 'button button-primary';
            ment.textContent = 'Mentés';
            ment.addEventListener('click', function () {
                allapot('Mentés…', false);
                kuld('vespa_szabadidos_field_save', {
                    field_id: mezo.field_id,
                    label: cimke.value,
                    field_type: tipus.value,
                    field_options: opciok.value,
                    is_required: kot.checked ? '1' : '0'
                }).then(function (resp) {
                    if (!resp.success) { allapot(resp.data.message, true); return; }
                    mezok = resp.data.fields;
                    rajzol();
                    uzen(resp.data.message);
                }).catch(function () { allapot('Hálózati hiba.', true); });
            });
            lab.appendChild(ment);

            var torol = document.createElement('button');
            torol.type = 'button';
            torol.className = 'button';
            torol.style.marginLeft = '6px';
            torol.textContent = 'Törlés';
            torol.addEventListener('click', function () {
                if (mezo.field_id === 0) { mezok.splice(index, 1); rajzol(); return; }
                var kerdes = mezo.answer_count > 0
                    ? 'Erre a mezőre már ' + mezo.answer_count + ' válasz érkezett. A mező archiválódik, a válaszok megmaradnak. Folytatod?'
                    : 'Biztosan törlöd ezt a mezőt?';
                if (!window.confirm(kerdes)) return;
                kuld('vespa_szabadidos_field_delete', { field_id: mezo.field_id })
                    .then(function (resp) {
                        if (!resp.success) { allapot(resp.data.message, true); return; }
                        mezok = resp.data.fields;
                        rajzol();
                        uzen(resp.data.message);
                    }).catch(function () { allapot('Hálózati hiba.', true); });
            });
            lab.appendChild(torol);
        } else {
            var jel = document.createElement('span');
            jel.textContent = 'Archivált — ' + mezo.answer_count + ' válasz megmarad. ';
            lab.appendChild(jel);

            var vissza = document.createElement('button');
            vissza.type = 'button';
            vissza.className = 'button';
            vissza.textContent = 'Visszakapcsolás';
            vissza.addEventListener('click', function () {
                kuld('vespa_szabadidos_field_restore', { field_id: mezo.field_id })
                    .then(function (resp) {
                        if (!resp.success) { allapot(resp.data.message, true); return; }
                        mezok = resp.data.fields;
                        rajzol();
                        uzen(resp.data.message);
                    }).catch(function () { allapot('Hálózati hiba.', true); });
            });
            lab.appendChild(vissza);
        }

        lab.appendChild(allapotJel);
        sor.appendChild(lab);

        if (mezo.field_id === 0) {
            allapot('Még nincs mentve', false);
        }

        return sor;
    }

    function mozgat(fieldId, irany) {
        kuld('vespa_szabadidos_field_move', { field_id: fieldId, direction: irany })
            .then(function (resp) {
                if (!resp.success) { uzen(resp.data.message); return; }
                mezok = resp.data.fields;
                rajzol();
            }).catch(function () { uzen('Hálózati hiba.'); });
    }

    function uzen(szoveg) {
        globalisUzenet.textContent = szoveg || '';
    }

    function rajzol() {
        lista.textContent = '';
        var aktivDb = mezok.filter(function (m) { return m.is_active === 1; }).length;
        mezok.forEach(function (mezo, index) {
            lista.appendChild(sortEpit(mezo, index, aktivDb));
        });
        if (mezok.length === 0) {
            var ures = document.createElement('p');
            ures.textContent = 'Ehhez a versenyhez még nincs nevezési mező. A résztvevő ilyenkor a mai módon, egy kattintással nevez.';
            lista.appendChild(ures);
        }
    }

    doboz.querySelector('.vespa-mezo-uj').addEventListener('click', function () {
        // Az új sor a listába kerül, de csak a Mentés gomb rögzíti — a többi
        // sor félkész állapota így nem vész el.
        mezok.push({
            field_id: 0,
            label: '',
            field_type: 'egyvalasztos',
            field_options: '',
            is_required: 0,
            ordernum: 0,
            is_active: 1,
            answer_count: 0
        });
        rajzol();
        uzen('');
    });

    rajzol();
})();
</script>
