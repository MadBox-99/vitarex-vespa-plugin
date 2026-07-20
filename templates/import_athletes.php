<h1>Sportoló import</h1>
<?php
global $wpdb;

// --- Jogosultság ---
if (!current_user_can(VESPA_Roles::sportolok_letrehozasa_modositasa)) {
    echo '<p style="color:red;">Nincs jogosultságod a sportoló importhoz.</p>';
    return;
}

$is_teacher = VESPA_Roles::getInstance()->current_user_has_role(VESPA_Roles::TESTNEVELO);

// --- Az iskola-legördülő adatai (testnevelőnél csak a saját iskolája) ---
if ($is_teacher) {
    $my_school_id = (int) vespa_get_my_school_id();
    $institutions = $wpdb->get_results($wpdb->prepare('SELECT * FROM vespa_institutions WHERE institution_id=%d', $my_school_id));
} else {
    $institutions = $wpdb->get_results('SELECT * FROM vespa_institutions ORDER BY ins_name');
}

$notice = '';   // sikeres/összegző üzenet
$error  = '';   // felső szintű hibaüzenet
$preview = null; // az előnézet adatai

// --- 2. LÉPÉS: jóváhagyás + commit ---
if (isset($_POST['vespa_import_confirm'])) {
    if (!isset($_POST['vespa_import_nonce']) || !wp_verify_nonce($_POST['vespa_import_nonce'], 'vespa_import')) {
        $error = 'Érvénytelen kérés (nonce).';
    } else {
        $token = isset($_POST['vespa_import_token']) ? sanitize_text_field($_POST['vespa_import_token']) : '';
        $payload = $token !== '' ? get_transient('vespa_import_' . $token) : false;

        if ($payload === false || !isset($payload['valid'], $payload['school_id'])) {
            $error = 'Az előnézet lejárt vagy nem található. Kérlek, tölts fel újra.';
        } else {
            // A school_id a transientből jön (nem újraküldött POST-ból).
            $school_id = (int) $payload['school_id'];
            $inserted = VESPA_Athlete_Importer::commit($payload['valid'], $school_id);
            delete_transient('vespa_import_' . $token);

            $skipped = isset($payload['duplicate_count']) ? (int) $payload['duplicate_count'] : 0;
            $errored = isset($payload['error_count']) ? (int) $payload['error_count'] : 0;

            $school = $wpdb->get_row($wpdb->prepare('SELECT ins_name FROM vespa_institutions WHERE institution_id=%d', $school_id));
            $school_name = $school ? $school->ins_name : ('#' . $school_id);

            vitarex_log(
                'athlete_import',
                "Sportoló import — iskola: $school_name (#$school_id). Importálva: $inserted, kihagyott duplikátum: $skipped, hibás: $errored.",
                'vespa_athletes'
            );

            $notice = "Az import lefutott. $inserted sportoló importálva" .
                ($skipped > 0 ? ", $skipped már létező kihagyva" : '') .
                ($errored > 0 ? ", $errored hibás sor kihagyva" : '') . '.';
        }
    }
}

// --- 1. LÉPÉS: feltöltés + előnézet ---
if (isset($_POST['vespa_import_upload'])) {
    if (!isset($_POST['vespa_import_nonce']) || !wp_verify_nonce($_POST['vespa_import_nonce'], 'vespa_import')) {
        $error = 'Érvénytelen kérés (nonce).';
    } elseif (!isset($_POST['school_id']) || !is_numeric($_POST['school_id'])) {
        $error = 'Válassz iskolát.';
    } else {
        $school_id = (int) $_POST['school_id'];

        // Testnevelő csak a saját iskoláját választhatja.
        if ($is_teacher && $school_id !== (int) vespa_get_my_school_id()) {
            $error = 'Csak a saját iskoládba importálhatsz.';
        } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Nem sikerült a fájl feltöltése.';
        } else {
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, array('xlsx', 'csv'), true)) {
                $error = 'Csak .xlsx vagy .csv fájl tölthető fel.';
            } else {
                try {
                    $rows = VESPA_Athlete_Importer::parse($_FILES['file']['tmp_name']);
                    $res = VESPA_Athlete_Importer::validate($rows, $school_id);

                    $token = wp_generate_password(20, false);
                    set_transient('vespa_import_' . $token, array(
                        'valid'           => $res['valid'],
                        'school_id'       => $school_id,
                        'duplicate_count' => count($res['duplicate']),
                        'error_count'     => count($res['error']),
                    ), 15 * MINUTE_IN_SECONDS);

                    $preview = array('res' => $res, 'token' => $token, 'school_id' => $school_id);
                } catch (\Exception $e) {
                    $error = 'A fájl nem olvasható vagy nem támogatott formátum.';
                }
            }
        }
    }
}
?>

<div id="import">
    <?php if ($error !== '') : ?>
        <p style="color:red; font-size:16px;"><strong><?php echo esc_html($error); ?></strong></p>
    <?php endif; ?>

    <?php if ($notice !== '') : ?>
        <p style="font-size:16px; color:#137333;"><strong><?php echo esc_html($notice); ?></strong></p>
    <?php endif; ?>

    <?php if ($preview !== null) : $res = $preview['res']; ?>
        <div class="col-md-12">
            <p style="font-size:16px;">
                <?php echo (int) $res['total']; ?> sor beolvasva —
                <strong><?php echo count($res['valid']); ?> importálható</strong>,
                <?php echo count($res['duplicate']); ?> már létezik (kihagyva),
                <?php echo count($res['error']); ?> hibás.
            </p>

            <?php if (!empty($res['error'])) : ?>
                <p style="color:red; font-size:15px;">Hibás sorok:</p>
                <ul>
                    <?php foreach ($res['error'] as $e) : ?>
                        <li><?php echo (int) $e['row']; ?>. sor: <?php echo esc_html(implode(' ', $e['messages'])); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($res['duplicate'])) : ?>
                <p style="font-size:15px;">Már létező (kihagyott) sorok:</p>
                <ul>
                    <?php foreach ($res['duplicate'] as $d) : ?>
                        <li><?php echo (int) $d['row']; ?>. sor: <?php echo esc_html($d['name']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (count($res['valid']) > 0) : ?>
                <form action="" method="POST" style="margin-top:15px;">
                    <?php wp_nonce_field('vespa_import', 'vespa_import_nonce'); ?>
                    <input type="hidden" name="vespa_import_token" value="<?php echo esc_attr($preview['token']); ?>">
                    <button type="submit" class="btn btn-sm btn-primary" name="vespa_import_confirm">
                        <i class="fa fa-check" aria-hidden="true"></i>
                        Jóváhagyom és importálom (<?php echo count($res['valid']); ?> sportoló)
                    </button>
                    <a href="<?php echo esc_url(remove_query_arg('x')); ?>" class="btn btn-default btn-sm" style="margin-left:10px;">Mégse</a>
                </form>
            <?php else : ?>
                <p>Nincs importálható sor. Javítsd a fájlt, és tölts fel újra.</p>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="col-md-4">
            <form action="" method="POST" enctype="multipart/form-data">
                <?php wp_nonce_field('vespa_import', 'vespa_import_nonce'); ?>
                <div class="form-group">
                    <label>Iskola / egyesület</label>
                    <select name="school_id" id="school_id" class="form-control input-sm" required>
                        <?php foreach ($institutions as $institution) : ?>
                            <option value="<?php echo (int) $institution->institution_id; ?>">
                                <?php echo esc_html($institution->ins_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:20px;">
                    <input type="file" name="file" id="file" accept=".xlsx,.csv" required>
                </div>

                <button type="submit" class="btn btn-sm btn-primary" name="vespa_import_upload">
                    <i class="fa fa-upload" aria-hidden="true"></i>
                    FELTÖLTÉS ÉS ELŐNÉZET
                </button>
            </form>

            <p style="margin-top:15px;">
                Mintafájl:
                <a href="<?php echo esc_url(add_query_arg('vespa_athletes_xlsx_sample', 1, home_url())); ?>">Excel (.xlsx)</a>
                &nbsp;|&nbsp;
                <a href="<?php echo esc_url(add_query_arg('vespa_athletes_csv_sample', 1, home_url())); ?>">CSV</a>
            </p>
        </div>
    <?php endif; ?>
</div>
