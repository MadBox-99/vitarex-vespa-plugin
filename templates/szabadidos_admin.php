<?php
global $wpdb;
$admin_nonce = wp_create_nonce('vespa_szabadidos_admin');

$versenyek = $wpdb->get_results($wpdb->prepare(
    "SELECT vc.contest_id, vc.contest_name,
            (SELECT COUNT(*) FROM vespa_szabadidos_open_contests o WHERE o.contest_id=vc.contest_id) AS nyitva
     FROM vespa_contests AS vc
     WHERE vc.contest_type=%d
     ORDER BY vc.start_at DESC",
    4
));
?>
<div class="wrap" data-admin-nonce="<?php echo esc_attr($admin_nonce); ?>">
    <h1>Szabadidős külső nevezés</h1>

    <h2>Versenyek megnyitása külső regisztrációra</h2>
    <?php if (empty($versenyek)) : ?>
        <p>Nincs szabadidős (type-4) verseny.</p>
    <?php else : ?>
        <table class="widefat">
            <thead><tr><th>Verseny</th><th>Külső regisztráció</th></tr></thead>
            <tbody>
            <?php foreach ($versenyek as $v) : ?>
                <tr>
                    <td><?php echo esc_html($v->contest_name); ?></td>
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
