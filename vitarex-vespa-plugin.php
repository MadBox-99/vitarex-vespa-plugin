<?php
/*
    Plugin Name: VESPA
    Plugin URI: https://cegem360.hu
    Description: VESPA
    Author: Cegem360 Kft.
    Version: 2.3.1
    Author URI: https://cegem360.hu
    */

$currentLocale = get_locale();
setlocale(LC_ALL, $currentLocale . '.utf8');

define('VITAREX_VESPA_VERSION', '2.3.1');
define('VITAREX_VESPA_PLUGIN_DIR', WP_PLUGIN_DIR . '/vitarex-vespa-plugin');
define('VITAREX_VESPA_PLUGIN_URI', plugin_dir_url(__FILE__));
define('VITAREX_VESPA_PLUGIN_TEXTDOMAIN', 'vitarex_vespa');

function vespa_load_template($tplname)
{
    if (!file_exists(VITAREX_VESPA_PLUGIN_DIR . '/templates/' . $tplname)) {
        echo '<h4>nem létezik a sablonfájlt:<br>/templates/' . $tplname . '</h4>';
        return;
    }

    load_template(VITAREX_VESPA_PLUGIN_DIR . '/templates/' . $tplname);
}

function vespa_load_template_with_vars($tplname, $vars)
{
    ob_start();

    if (!file_exists(VITAREX_VESPA_PLUGIN_DIR . '/templates/' . $tplname)) {
        echo '<h4>nem létezik a sablonfájlt:<br>/templates/' . $tplname . '</h4>';
    } else {
        require VITAREX_VESPA_PLUGIN_DIR . '/templates/' . $tplname;
    }

    $html = ob_get_contents();
    ob_clean();

    foreach ($vars as $key => $value) {
        $html = str_replace($key, $value, $html);
    }

    return $html;
}

// ---- Fájlok betöltése strukturált sorrendben ----
// A betöltési sorrend fontos: Core (alap osztályok, roles) -> Datalist -> Admin -> Ajax -> Export -> Api
$include_dirs = array('Core', 'Datalist', 'Admin', 'Ajax', 'Export', 'Api');

foreach ($include_dirs as $subdir) {
    $dir = VITAREX_VESPA_PLUGIN_DIR . '/includes/' . $subdir;
    if (is_dir($dir)) {
        $files = glob($dir . '/*.php');
        if (is_array($files)) {
            foreach ($files as $file) {
                require_once $file;
            }
        }
    }
}


// -----------------------------------------------------
add_action('wp_head', 'vitarex_vespa_ajaxurl');
add_action('admin_head', 'vitarex_vespa_ajaxurl');
function vitarex_vespa_ajaxurl()
{
?>
    <script type="text/javascript">
        var vitarex_vespa_ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var vitarex_vespa_ajaxurl_default = '<?php echo home_url(); ?>';
        var vespa_nonce = '<?php echo wp_create_nonce('vespa_nonce'); ?>';
    </script>
<?php
}

// -----------------------------------------------------
add_action('init', 'vitarex_vespa_init');
function vitarex_vespa_init()
{
    global $wpdb;
    VESPA_Roles::getInstance()->init_custom_roles();
}

?>