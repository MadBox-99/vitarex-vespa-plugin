<?php
global $wpdb;
$nonce = wp_create_nonce('vespa_szabadidos');
$bejelentkezve = is_user_logged_in();
$kulso = $bejelentkezve && vespa_szabadidos_is_participant();
?>
<div class="vespa-szabadidos" data-nonce="<?php echo esc_attr($nonce); ?>">

<?php if (!$bejelentkezve) : ?>

    <?php if (isset($_GET['vespa_szabadidos_confirmed'])) : ?>
        <?php if ($_GET['vespa_szabadidos_confirmed'] === '1') : ?>
            <p class="vespa-szabadidos-uzenet ok">A fiókod megerősítve. Most már beléphetsz.</p>
        <?php else : ?>
            <p class="vespa-szabadidos-uzenet hiba">Érvénytelen vagy lejárt megerősítő link.</p>
        <?php endif; ?>
    <?php endif; ?>

    <h2>Belépés</h2>
    <form id="vespa-szabadidos-login">
        <label>E-mail <input type="email" name="email" required></label>
        <label>Jelszó <input type="password" name="password" required></label>
        <button type="submit">Belépés</button>
        <a href="<?php echo esc_url(wp_lostpassword_url()); ?>">Elfelejtett jelszó</a>
    </form>
    <div class="vespa-szabadidos-login-uzenet"></div>

    <h2>Regisztráció</h2>
    <form id="vespa-szabadidos-register">
        <label>Teljes név <input type="text" name="full_name" required></label>
        <label>Születési dátum <input type="date" name="birth_date" required></label>
        <label>Nem
            <select name="gender" required>
                <option value="">—</option>
                <option value="férfi">férfi</option>
                <option value="nő">nő</option>
            </select>
        </label>
        <label>E-mail <input type="email" name="email" required></label>
        <label>Telefonszám <input type="text" name="phone" required></label>
        <label>Jelszó <input type="password" name="password" required></label>
        <label>Jelszó újra <input type="password" name="password2" required></label>
        <label><input type="checkbox" name="consent" value="1" required> Elfogadom az adatkezelési tájékoztatót</label>
        <button type="submit">Regisztráció</button>
    </form>
    <div class="vespa-szabadidos-register-uzenet"></div>

<?php elseif (!$kulso) : ?>

    <p class="vespa-szabadidos-uzenet">Ez a felület a szabadidős külső résztvevőké. A jelenlegi fiókod nem külső résztvevő.</p>

<?php else : ?>

    <?php
    $resztvevo = vespa_szabadidos_current_participant();

    // Megnyitott, nem lejárt type-4 versenyek.
    $versenyek = $wpdb->get_results($wpdb->prepare(
        "SELECT vc.contest_id, vc.contest_name
         FROM vespa_szabadidos_open_contests AS o
         INNER JOIN vespa_contests AS vc ON vc.contest_id=o.contest_id
         WHERE vc.contest_type=%d AND vc.end_at >= %s
         ORDER BY vc.start_at ASC",
        4,
        current_time('mysql')
    ));

    // A saját nevezéseim (participant_id szerint — adatvédelmi izoláció).
    $sajat = array();
    if ($resztvevo) {
        $sajat = $wpdb->get_results($wpdb->prepare(
            "SELECT e.entry_id, e.contest_id, e.contest_event_id, vc.contest_name, vse.sport_event_name, vs.sport_name
             FROM vespa_external_entries AS e
             INNER JOIN vespa_contests AS vc ON vc.contest_id=e.contest_id
             LEFT JOIN vespa_constest_events AS vce ON vce.id=e.contest_event_id
             LEFT JOIN vespa_sport_events AS vse ON vse.sport_event_id=vce.event_id
             LEFT JOIN vespa_sports AS vs ON vs.sport_id=vce.sport_id
             WHERE e.participant_id=%d
             ORDER BY e.entry_date DESC",
            $resztvevo->participant_id
        ));
    }
    // A saját nevezett contest_event_id-k (a gombok elrejtéséhez).
    $sajat_event_idk = array_map(function ($s) { return intval($s->contest_event_id); }, $sajat);
    ?>

    <h2>Üdv, <?php echo esc_html($resztvevo ? $resztvevo->full_name : ''); ?>!</h2>
    <p><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Kilépés</a></p>

    <h3>Megnyitott szabadidős versenyek</h3>
    <?php if (empty($versenyek)) : ?>
        <p>Jelenleg nincs elérhető szabadidős verseny.</p>
    <?php else : ?>
        <?php foreach ($versenyek as $v) : ?>
            <div class="vespa-szabadidos-verseny">
                <h4><?php echo esc_html($v->contest_name); ?></h4>
                <?php
                $esemenyek = $wpdb->get_results($wpdb->prepare(
                    "SELECT vce.id AS contest_event_id, vse.sport_event_name, vs.sport_name
                     FROM vespa_constest_events AS vce
                     LEFT JOIN vespa_sport_events AS vse ON vse.sport_event_id=vce.event_id
                     LEFT JOIN vespa_sports AS vs ON vs.sport_id=vce.sport_id
                     WHERE vce.contest_id=%d
                     ORDER BY vs.sport_name, vse.sport_event_name",
                    $v->contest_id
                ));
                ?>
                <?php if (empty($esemenyek)) : ?>
                    <p>Ehhez a versenyhez még nincs versenyszám.</p>
                <?php else : ?>
                    <ul>
                    <?php foreach ($esemenyek as $e) : ?>
                        <li>
                            <?php echo esc_html(trim(($e->sport_name ?: '') . ' – ' . ($e->sport_event_name ?: ''), ' –')); ?>
                            <?php if (in_array(intval($e->contest_event_id), $sajat_event_idk, true)) : ?>
                                <span class="vespa-szabadidos-nevezve">(nevezve)</span>
                            <?php else : ?>
                                <button type="button" class="vespa-szabadidos-nevez"
                                        data-contest="<?php echo esc_attr($v->contest_id); ?>"
                                        data-event="<?php echo esc_attr($e->contest_event_id); ?>">Nevezek</button>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h3>Nevezéseim</h3>
    <?php if (empty($sajat)) : ?>
        <p>Még nincs nevezésed.</p>
    <?php else : ?>
        <ul>
        <?php foreach ($sajat as $s) : ?>
            <li>
                <?php echo esc_html($s->contest_name . ' — ' . trim(($s->sport_name ?: '') . ' ' . ($s->sport_event_name ?: ''))); ?>
                <button type="button" class="vespa-szabadidos-visszavon" data-entry="<?php echo esc_attr($s->entry_id); ?>">Visszavonás</button>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>

<?php endif; ?>

</div>

<script>
(function () {
    var gyoker = document.querySelector('.vespa-szabadidos');
    if (!gyoker) return;
    var nonce = gyoker.getAttribute('data-nonce');
    var url = (typeof vitarex_vespa_ajaxurl !== 'undefined') ? vitarex_vespa_ajaxurl : '/wp-admin/admin-ajax.php';

    function kuld(action, adatok, kesz) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        Object.keys(adatok).forEach(function (k) { fd.append(k, adatok[k]); });
        fetch(url, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(kesz)
            .catch(function () { kesz({ success: false, data: { message: 'Hálózati hiba.' } }); });
    }

    var regForm = document.getElementById('vespa-szabadidos-register');
    if (regForm) {
        regForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var f = regForm;
            kuld('vespa_szabadidos_register', {
                full_name: f.full_name.value, birth_date: f.birth_date.value, gender: f.gender.value,
                email: f.email.value, phone: f.phone.value, password: f.password.value,
                password2: f.password2.value, consent: f.consent.checked ? '1' : '0'
            }, function (resp) {
                document.querySelector('.vespa-szabadidos-register-uzenet').textContent = resp.data.message;
                if (resp.success) regForm.reset();
            });
        });
    }

    var loginForm = document.getElementById('vespa-szabadidos-login');
    if (loginForm) {
        loginForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            kuld('vespa_szabadidos_login', { email: loginForm.email.value, password: loginForm.password.value }, function (resp) {
                document.querySelector('.vespa-szabadidos-login-uzenet').textContent = resp.data.message;
                if (resp.success) location.reload();
            });
        });
    }

    document.querySelectorAll('.vespa-szabadidos-nevez').forEach(function (b) {
        b.addEventListener('click', function () {
            kuld('vespa_szabadidos_signup', { contest_id: b.getAttribute('data-contest'), contest_event_id: b.getAttribute('data-event') }, function (resp) {
                alert(resp.data.message);
                if (resp.success) location.reload();
            });
        });
    });

    document.querySelectorAll('.vespa-szabadidos-visszavon').forEach(function (b) {
        b.addEventListener('click', function () {
            kuld('vespa_szabadidos_withdraw', { entry_id: b.getAttribute('data-entry') }, function (resp) {
                alert(resp.data.message);
                if (resp.success) location.reload();
            });
        });
    });
})();
</script>
