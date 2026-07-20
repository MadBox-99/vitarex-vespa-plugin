<?php

add_shortcode('vespa_szabadidos', 'vespa_szabadidos_shortcode');

function vespa_szabadidos_shortcode()
{
    ob_start();
    require VITAREX_VESPA_PLUGIN_DIR . '/templates/szabadidos_frontend.php';
    return ob_get_clean();
}
