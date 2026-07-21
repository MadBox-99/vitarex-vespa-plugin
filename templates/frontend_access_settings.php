<?php
$beallitasok = vespa_frontend_access_get_settings();
$publikus    = $beallitasok['public_page_ids'];
$landing     = $beallitasok['szabadidos_landing_page_id'];
$nonce       = wp_create_nonce('vespa_frontend_access_save');

$oldalak = get_pages(array(
    'post_status' => 'publish',
    'sort_column' => 'post_title',
));

// Figyelmeztetés, ha a beállított kezdőoldal időközben eltűnt.
$landing_hianyzik = ($landing > 0 && get_post_status($landing) !== 'publish');
?>
<div class="wrap">
    <h1>Front-end hozzáférés</h1>

    <p>
        A VESPA alapértelmezés szerint minden front-end oldalt elzár: a bejelentkezett
        látogatót a wp-adminba, a többit a bejelentkező oldalra irányítja. Az itt
        bepipált oldalak ez alól kivételt kapnak, és bárki számára elérhetők lesznek.
    </p>

    <?php if ($landing_hianyzik) : ?>
        <div class="notice notice-warning">
            <p>
                A korábban beállított szabadidős kezdőoldal már nem elérhető (törölve lett
                vagy piszkozat). A résztvevők jelenleg a kezdőlapra érkeznek.
            </p>
        </div>
    <?php endif; ?>

    <form class="ajax-form" method="post">
        <input type="hidden" name="action" value="vespa_frontend_access_save">
        <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">

        <h2>Publikusan elérhető oldalak</h2>

        <?php if (empty($oldalak)) : ?>
            <p>Nincs publikált oldal.</p>
        <?php else : ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th style="width:120px;">Publikus</th>
                        <th>Oldal</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($oldalak as $oldal) : ?>
                    <tr>
                        <td>
                            <input type="checkbox"
                                   name="public_page_ids[]"
                                   value="<?php echo esc_attr($oldal->ID); ?>"
                                   <?php checked(in_array(intval($oldal->ID), $publikus, true)); ?>>
                        </td>
                        <td><?php echo esc_html($oldal->post_title); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2>Szabadidős kezdőoldal</h2>

        <p>
            Ide érkezik a szabadidős külső résztvevő bejelentkezés után, és ide kerül
            akkor is, ha a wp-admint próbálja megnyitni. Csak olyan oldalt válassz,
            amelyet fent publikusnak is bepipáltál.
        </p>

        <select name="szabadidos_landing_page_id">
            <option value="0" <?php selected($landing, 0); ?>>— Kezdőlap —</option>
            <?php foreach ($oldalak as $oldal) : ?>
                <option value="<?php echo esc_attr($oldal->ID); ?>" <?php selected($landing, intval($oldal->ID)); ?>>
                    <?php echo esc_html($oldal->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <p style="margin-top:20px;">
            <button type="submit" class="btn btn-primary">Mentés</button>
        </p>
    </form>
</div>
