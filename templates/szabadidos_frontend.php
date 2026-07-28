<?php
global $wpdb;
$nonce = wp_create_nonce('vespa_szabadidos');
$bejelentkezve = is_user_logged_in();
$kulso = $bejelentkezve && vespa_szabadidos_is_participant();

// Ismétlődő Tailwind osztálykészletek. A 'tw-' prefix a preflight kikapcsolása
// miatt kell (lásd vespa.szabadidos.frontend.php), hogy ne ütközzünk a témával.
$k_kartya   = 'tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-p-6 tw-shadow-sm';
$k_cim      = 'tw-mb-4 tw-text-xl tw-font-semibold tw-text-slate-900';
$k_mezo     = 'tw-block tw-mb-4';
$k_cimke    = 'tw-block tw-mb-1 tw-text-sm tw-font-medium tw-text-slate-700';
$k_input    = 'tw-w-full tw-rounded-lg tw-border tw-border-slate-300 tw-bg-white tw-px-3 tw-py-2 tw-text-slate-900 tw-shadow-sm focus:tw-border-sky-500 focus:tw-outline-none focus:tw-ring-2 focus:tw-ring-sky-200';
$k_gomb     = 'tw-inline-flex tw-items-center tw-justify-center tw-rounded-lg tw-bg-sky-600 tw-px-5 tw-py-2.5 tw-text-sm tw-font-medium tw-text-white tw-shadow-sm hover:tw-bg-sky-700 focus:tw-outline-none focus:tw-ring-2 focus:tw-ring-sky-300 tw-transition tw-cursor-pointer';
$k_gomb_alt = 'tw-inline-flex tw-items-center tw-justify-center tw-rounded-lg tw-border tw-border-slate-300 tw-bg-white tw-px-3 tw-py-1.5 tw-text-xs tw-font-medium tw-text-slate-700 hover:tw-bg-slate-50 focus:tw-outline-none focus:tw-ring-2 focus:tw-ring-slate-200 tw-transition tw-cursor-pointer';
$k_link     = 'tw-text-sm tw-text-sky-700 tw-underline hover:tw-text-sky-900';
$k_uzenet   = 'tw-mt-3 tw-text-sm tw-font-medium tw-text-slate-700';
?>
<div class="vespa-szabadidos tw-mx-auto tw-max-w-2xl tw-space-y-6 tw-text-slate-800" data-nonce="<?php echo esc_attr($nonce); ?>">

<?php if (!$bejelentkezve) : ?>

    <?php if (isset($_GET['vespa_szabadidos_confirmed'])) : ?>
        <?php if ($_GET['vespa_szabadidos_confirmed'] === '1') : ?>
            <p class="vespa-szabadidos-uzenet ok tw-rounded-lg tw-border tw-border-emerald-200 tw-bg-emerald-50 tw-px-4 tw-py-3 tw-text-sm tw-font-medium tw-text-emerald-800">A fiókod megerősítve. Most már beléphetsz.</p>
        <?php else : ?>
            <p class="vespa-szabadidos-uzenet hiba tw-rounded-lg tw-border tw-border-red-200 tw-bg-red-50 tw-px-4 tw-py-3 tw-text-sm tw-font-medium tw-text-red-800">Érvénytelen vagy lejárt megerősítő link.</p>
        <?php endif; ?>
    <?php endif; ?>

    <section class="<?php echo esc_attr($k_kartya); ?>">
        <h2 class="<?php echo esc_attr($k_cim); ?>">Belépés</h2>
        <form id="vespa-szabadidos-login">
            <label class="<?php echo esc_attr($k_mezo); ?>">
                <span class="<?php echo esc_attr($k_cimke); ?>">E-mail</span>
                <input type="email" name="email" required class="<?php echo esc_attr($k_input); ?>">
            </label>
            <label class="<?php echo esc_attr($k_mezo); ?>">
                <span class="<?php echo esc_attr($k_cimke); ?>">Jelszó</span>
                <input type="password" name="password" required class="<?php echo esc_attr($k_input); ?>">
            </label>
            <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-4">
                <button type="submit" class="<?php echo esc_attr($k_gomb); ?>">Belépés</button>
                <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="<?php echo esc_attr($k_link); ?>">Elfelejtett jelszó</a>
            </div>
        </form>
        <div class="vespa-szabadidos-login-uzenet <?php echo esc_attr($k_uzenet); ?>"></div>
    </section>

    <section class="<?php echo esc_attr($k_kartya); ?>">
        <h2 class="<?php echo esc_attr($k_cim); ?>">Regisztráció</h2>
        <form id="vespa-szabadidos-register">
            <label class="<?php echo esc_attr($k_mezo); ?>">
                <span class="<?php echo esc_attr($k_cimke); ?>">Teljes név</span>
                <input type="text" name="full_name" required class="<?php echo esc_attr($k_input); ?>">
            </label>

            <div class="tw-grid tw-gap-4 sm:tw-grid-cols-2">
                <label class="<?php echo esc_attr($k_mezo); ?>">
                    <span class="<?php echo esc_attr($k_cimke); ?>">Születési dátum</span>
                    <input type="date" name="birth_date" required class="<?php echo esc_attr($k_input); ?>">
                </label>
                <label class="<?php echo esc_attr($k_mezo); ?>">
                    <span class="<?php echo esc_attr($k_cimke); ?>">Nem</span>
                    <select name="gender" required class="<?php echo esc_attr($k_input); ?>">
                        <option value="">—</option>
                        <option value="férfi">férfi</option>
                        <option value="nő">nő</option>
                    </select>
                </label>
            </div>

            <div class="tw-grid tw-gap-4 sm:tw-grid-cols-2">
                <label class="<?php echo esc_attr($k_mezo); ?>">
                    <span class="<?php echo esc_attr($k_cimke); ?>">E-mail</span>
                    <input type="email" name="email" required class="<?php echo esc_attr($k_input); ?>">
                </label>
                <label class="<?php echo esc_attr($k_mezo); ?>">
                    <span class="<?php echo esc_attr($k_cimke); ?>">Telefonszám</span>
                    <input type="text" name="phone" required class="<?php echo esc_attr($k_input); ?>">
                </label>
            </div>

            <div class="tw-grid tw-gap-4 sm:tw-grid-cols-2">
                <label class="<?php echo esc_attr($k_mezo); ?>">
                    <span class="<?php echo esc_attr($k_cimke); ?>">Jelszó</span>
                    <input type="password" name="password" required class="<?php echo esc_attr($k_input); ?>">
                </label>
                <label class="<?php echo esc_attr($k_mezo); ?>">
                    <span class="<?php echo esc_attr($k_cimke); ?>">Jelszó újra</span>
                    <input type="password" name="password2" required class="<?php echo esc_attr($k_input); ?>">
                </label>
            </div>

            <label class="tw-mb-5 tw-flex tw-items-start tw-gap-2 tw-text-sm tw-text-slate-700">
                <input type="checkbox" name="consent" value="1" required class="tw-mt-0.5 tw-h-4 tw-w-4 tw-rounded tw-border-slate-300 tw-text-sky-600 focus:tw-ring-2 focus:tw-ring-sky-200">
                <span>Elfogadom az adatkezelési tájékoztatót</span>
            </label>

            <button type="submit" class="<?php echo esc_attr($k_gomb); ?>">Regisztráció</button>
        </form>
        <div class="vespa-szabadidos-register-uzenet <?php echo esc_attr($k_uzenet); ?>"></div>
    </section>

<?php elseif (!$kulso) : ?>

    <p class="vespa-szabadidos-uzenet tw-rounded-lg tw-border tw-border-amber-200 tw-bg-amber-50 tw-px-4 tw-py-3 tw-text-sm tw-font-medium tw-text-amber-900">Ez a felület a szabadidős külső résztvevőké. A jelenlegi fiókod nem külső résztvevő.</p>

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

    <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-3">
        <h2 class="tw-text-xl tw-font-semibold tw-text-slate-900">Üdv, <?php echo esc_html($resztvevo ? $resztvevo->full_name : ''); ?>!</h2>
        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="<?php echo esc_attr($k_link); ?>">Kilépés</a>
    </div>

    <section class="<?php echo esc_attr($k_kartya); ?>">
        <h3 class="<?php echo esc_attr($k_cim); ?>">Megnyitott szabadidős versenyek</h3>
        <?php if (empty($versenyek)) : ?>
            <p class="tw-text-sm tw-text-slate-500">Jelenleg nincs elérhető szabadidős verseny.</p>
        <?php else : ?>
            <div class="tw-space-y-4">
            <?php foreach ($versenyek as $v) : ?>
                <?php
                $verseny_mezok = vespa_szabadidos_get_fields($v->contest_id);
                $mar_valaszolt = $resztvevo
                    ? vespa_szabadidos_has_answers($resztvevo->participant_id, $v->contest_id)
                    : false;
                $urlap_kell = !empty($verseny_mezok) && !$mar_valaszolt;
                ?>
                <div class="vespa-szabadidos-verseny tw-rounded-lg tw-border tw-border-slate-200 tw-p-4"
                     data-contest="<?php echo esc_attr($v->contest_id); ?>">
                    <h4 class="tw-mb-3 tw-font-semibold tw-text-slate-900"><?php echo esc_html($v->contest_name); ?></h4>
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
                        <p class="tw-text-sm tw-text-slate-500">Ehhez a versenyhez még nincs versenyszám.</p>
                    <?php else : ?>
                        <ul class="tw-m-0 tw-list-none tw-space-y-2 tw-p-0">
                        <?php foreach ($esemenyek as $e) : ?>
                            <li class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-2 tw-border-b tw-border-slate-100 tw-pb-2 last:tw-border-0 last:tw-pb-0">
                                <span class="tw-text-sm tw-text-slate-700"><?php echo esc_html(trim(($e->sport_name ?: '') . ' – ' . ($e->sport_event_name ?: ''), ' –')); ?></span>
                                <?php if (in_array(intval($e->contest_event_id), $sajat_event_idk, true)) : ?>
                                    <span class="vespa-szabadidos-nevezve tw-rounded-full tw-bg-emerald-50 tw-px-3 tw-py-1 tw-text-xs tw-font-medium tw-text-emerald-700">nevezve</span>
                                <?php else : ?>
                                    <button type="button" class="vespa-szabadidos-nevez <?php echo esc_attr($k_gomb_alt); ?>"
                                            data-contest="<?php echo esc_attr($v->contest_id); ?>"
                                            data-event="<?php echo esc_attr($e->contest_event_id); ?>"
                                            data-urlap="<?php echo $urlap_kell ? '1' : '0'; ?>">Nevezek</button>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($urlap_kell) : ?>
                        <form class="vespa-szabadidos-urlap tw-mt-4 tw-hidden tw-rounded-lg tw-border tw-border-sky-200 tw-bg-sky-50 tw-p-4">
                            <p class="tw-mb-3 tw-text-sm tw-font-medium tw-text-slate-800">
                                A nevezéshez töltsd ki az alábbiakat. Ezeket versenyenként csak egyszer kérjük.
                            </p>
                            <?php foreach ($verseny_mezok as $mezo) : ?>
                                <?php $opciok = vespa_szabadidos_parse_options($mezo->field_options); ?>
                                <div class="vespa-szabadidos-mezo tw-mb-4" data-field="<?php echo esc_attr($mezo->field_id); ?>">
                                    <?php if ($mezo->field_type !== 'nyilatkozat') : ?>
                                        <span class="<?php echo esc_attr($k_cimke); ?>">
                                            <?php echo esc_html($mezo->label); ?><?php echo intval($mezo->is_required) === 1 ? ' *' : ''; ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($mezo->field_type === 'egyvalasztos') : ?>
                                        <?php foreach ($opciok as $opcio) : ?>
                                            <label class="tw-mb-1 tw-flex tw-items-center tw-gap-2 tw-text-sm tw-text-slate-700">
                                                <input type="radio" name="mezok[<?php echo esc_attr($mezo->field_id); ?>]"
                                                       value="<?php echo esc_attr($opcio); ?>"
                                                       class="tw-h-4 tw-w-4 tw-border-slate-300 tw-text-sky-600">
                                                <span><?php echo esc_html($opcio); ?></span>
                                            </label>
                                        <?php endforeach; ?>

                                    <?php elseif ($mezo->field_type === 'tobbvalasztos') : ?>
                                        <?php foreach ($opciok as $opcio) : ?>
                                            <label class="tw-mb-1 tw-flex tw-items-center tw-gap-2 tw-text-sm tw-text-slate-700">
                                                <input type="checkbox" name="mezok[<?php echo esc_attr($mezo->field_id); ?>][]"
                                                       value="<?php echo esc_attr($opcio); ?>"
                                                       class="tw-h-4 tw-w-4 tw-rounded tw-border-slate-300 tw-text-sky-600">
                                                <span><?php echo esc_html($opcio); ?></span>
                                            </label>
                                        <?php endforeach; ?>

                                    <?php elseif ($mezo->field_type === 'nyilatkozat') : ?>
                                        <label class="tw-flex tw-items-start tw-gap-2 tw-text-sm tw-text-slate-700">
                                            <input type="checkbox" name="mezok[<?php echo esc_attr($mezo->field_id); ?>]" value="1"
                                                   class="tw-mt-0.5 tw-h-4 tw-w-4 tw-rounded tw-border-slate-300 tw-text-sky-600">
                                            <span><?php echo esc_html($mezo->label); ?> *</span>
                                        </label>

                                    <?php elseif ($mezo->field_type === 'hosszu_szoveg') : ?>
                                        <textarea name="mezok[<?php echo esc_attr($mezo->field_id); ?>]" rows="3"
                                                  class="<?php echo esc_attr($k_input); ?>"></textarea>

                                    <?php elseif ($mezo->field_type === 'szam') : ?>
                                        <input type="number" name="mezok[<?php echo esc_attr($mezo->field_id); ?>]"
                                               class="<?php echo esc_attr($k_input); ?>">

                                    <?php elseif ($mezo->field_type === 'datum') : ?>
                                        <input type="date" name="mezok[<?php echo esc_attr($mezo->field_id); ?>]"
                                               class="<?php echo esc_attr($k_input); ?>">

                                    <?php else : ?>
                                        <input type="text" name="mezok[<?php echo esc_attr($mezo->field_id); ?>]"
                                               class="<?php echo esc_attr($k_input); ?>">
                                    <?php endif; ?>

                                    <p class="vespa-szabadidos-mezo-hiba tw-mt-1 tw-text-sm tw-font-medium tw-text-red-700"></p>
                                </div>
                            <?php endforeach; ?>

                            <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-3">
                                <button type="submit" class="<?php echo esc_attr($k_gomb); ?>">Nevezés véglegesítése</button>
                                <button type="button" class="vespa-szabadidos-megse <?php echo esc_attr($k_gomb_alt); ?>">Mégsem</button>
                            </div>
                            <p class="vespa-szabadidos-urlap-uzenet <?php echo esc_attr($k_uzenet); ?>"></p>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="<?php echo esc_attr($k_kartya); ?>">
        <h3 class="<?php echo esc_attr($k_cim); ?>">Nevezéseim</h3>
        <?php if (empty($sajat)) : ?>
            <p class="tw-text-sm tw-text-slate-500">Még nincs nevezésed.</p>
        <?php else : ?>
            <ul class="tw-m-0 tw-list-none tw-space-y-2 tw-p-0">
            <?php foreach ($sajat as $s) : ?>
                <li class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-2 tw-border-b tw-border-slate-100 tw-pb-2 last:tw-border-0 last:tw-pb-0">
                    <span class="tw-text-sm tw-text-slate-700"><?php echo esc_html($s->contest_name . ' — ' . trim(($s->sport_name ?: '') . ' ' . ($s->sport_event_name ?: ''))); ?></span>
                    <button type="button" class="vespa-szabadidos-visszavon <?php echo esc_attr($k_gomb_alt); ?>" data-entry="<?php echo esc_attr($s->entry_id); ?>">Visszavonás</button>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

<?php endif; ?>

</div>

<script>
(function () {
    var gyoker = document.querySelector('.vespa-szabadidos');
    if (!gyoker) return;
    var nonce = gyoker.getAttribute('data-nonce');
    var url = (typeof vitarex_vespa_ajaxurl !== 'undefined') ? vitarex_vespa_ajaxurl : '/wp-admin/admin-ajax.php';

    // Az adatok lehet sima objektum vagy kész FormData (a nevezési űrlaphoz,
    // ahol a többválasztós mezők tömbként érkeznek).
    function kuld(action, adatok, kesz) {
        var fd;
        if (adatok instanceof FormData) {
            fd = adatok;
        } else {
            fd = new FormData();
            Object.keys(adatok).forEach(function (k) { fd.append(k, adatok[k]); });
        }
        fd.append('action', action);
        fd.append('nonce', nonce);
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

    function hibakTorlese(urlap) {
        urlap.querySelectorAll('.vespa-szabadidos-mezo-hiba').forEach(function (p) { p.textContent = ''; });
        urlap.querySelector('.vespa-szabadidos-urlap-uzenet').textContent = '';
    }

    document.querySelectorAll('.vespa-szabadidos-nevez').forEach(function (b) {
        b.addEventListener('click', function () {
            var kartya = b.closest('.vespa-szabadidos-verseny');
            var urlap = kartya ? kartya.querySelector('.vespa-szabadidos-urlap') : null;

            // Ha nincs kitöltendő mező (vagy a résztvevő már válaszolt erre a
            // versenyre), a gomb a mai módon, egy kattintással nevez.
            if (b.getAttribute('data-urlap') !== '1' || !urlap) {
                kuld('vespa_szabadidos_signup', {
                    contest_id: b.getAttribute('data-contest'),
                    contest_event_id: b.getAttribute('data-event')
                }, function (resp) {
                    alert(resp.data.message);
                    if (resp.success) location.reload();
                });
                return;
            }

            hibakTorlese(urlap);
            urlap.setAttribute('data-event', b.getAttribute('data-event'));
            urlap.classList.remove('tw-hidden');
            urlap.scrollIntoView({ block: 'nearest' });
        });
    });

    document.querySelectorAll('.vespa-szabadidos-megse').forEach(function (b) {
        b.addEventListener('click', function () {
            var urlap = b.closest('.vespa-szabadidos-urlap');
            hibakTorlese(urlap);
            urlap.classList.add('tw-hidden');
        });
    });

    document.querySelectorAll('.vespa-szabadidos-urlap').forEach(function (urlap) {
        urlap.addEventListener('submit', function (ev) {
            ev.preventDefault();
            hibakTorlese(urlap);

            var kartya = urlap.closest('.vespa-szabadidos-verseny');
            var fd = new FormData(urlap);
            fd.append('contest_id', kartya.getAttribute('data-contest'));
            fd.append('contest_event_id', urlap.getAttribute('data-event'));

            kuld('vespa_szabadidos_signup', fd, function (resp) {
                if (resp.success) {
                    alert(resp.data.message);
                    location.reload();
                    return;
                }
                urlap.querySelector('.vespa-szabadidos-urlap-uzenet').textContent = resp.data.message;
                var hibak = resp.data.field_errors || {};
                Object.keys(hibak).forEach(function (fieldId) {
                    var mezo = urlap.querySelector('.vespa-szabadidos-mezo[data-field="' + fieldId + '"]');
                    if (mezo) mezo.querySelector('.vespa-szabadidos-mezo-hiba').textContent = hibak[fieldId];
                });
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
