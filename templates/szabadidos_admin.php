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

    <h2>Külső nevezők</h2>
    <?php
    $valasztott = isset($_GET['contest_id']) ? intval($_GET['contest_id']) : 0;
    $nyitott_versenyek = $wpdb->get_results($wpdb->prepare(
        "SELECT vc.contest_id, vc.contest_name
         FROM vespa_szabadidos_open_contests AS o
         INNER JOIN vespa_contests AS vc ON vc.contest_id=o.contest_id
         WHERE vc.contest_type=%d
         ORDER BY vc.contest_name",
        4
    ));
    ?>
    <form method="get">
        <input type="hidden" name="page" value="szabadidos_kulso">
        <label>Verseny:
            <select name="contest_id" onchange="this.form.submit()">
                <option value="0">— válassz —</option>
                <?php foreach ($nyitott_versenyek as $nv) : ?>
                    <option value="<?php echo esc_attr($nv->contest_id); ?>" <?php selected($valasztott, intval($nv->contest_id)); ?>>
                        <?php echo esc_html($nv->contest_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

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
