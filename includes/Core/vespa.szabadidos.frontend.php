<?php

add_shortcode('vespa_szabadidos', 'vespa_szabadidos_shortcode');

add_action('wp_enqueue_scripts', 'vespa_szabadidos_frontend_assets');

/**
 * A Tailwind CDN-t KIZÁRÓLAG azokon az oldalakon töltjük be, ahol a shortcode
 * tényleg szerepel — nem terheljük vele az egész front-endet.
 */
function vespa_szabadidos_frontend_assets()
{
    if (!is_singular()) {
        return;
    }

    $bejegyzes = get_post();
    if (!$bejegyzes || !has_shortcode($bejegyzes->post_content, 'vespa_szabadidos')) {
        return;
    }

    wp_enqueue_script('vespa-tailwind', 'https://cdn.tailwindcss.com', array(), null, false);

    // A preflight KI van kapcsolva: az az egész oldalt resetelné (a téma
    // fejlécét, tipográfiáját is), nem csak a mi űrlapunkat. A 'tw-' prefix
    // pedig megakadályozza, hogy az utility-nevek (block, grid, hidden, fixed)
    // ütközzenek a téma vagy a WordPress saját osztályaival.
    wp_add_inline_script(
        'vespa-tailwind',
        "tailwind.config={prefix:'tw-',corePlugins:{preflight:false}};",
        'after'
    );
}

function vespa_szabadidos_shortcode()
{
    ob_start();
    require VITAREX_VESPA_PLUGIN_DIR . '/templates/szabadidos_frontend.php';
    return ob_get_clean();
}
