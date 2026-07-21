<?php

class VESPA_Assets extends Singleton
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'addAdminScriptsStyles'));
        add_filter('admin_footer_text', array($this, 'adminFooterText'));
        add_filter('update_footer', '__return_empty_string', 11);
    }

    /**
     * FODISZ logó + VESPA verzió a wp-admin láblécében.
     * A #wpfooter rejtését a css/vespa-admin.css oldja fel.
     */
    public function adminFooterText($text)
    {
        return '<span class="vespa-footer">'
            . '<img src="' . esc_url(VITAREX_VESPA_PLUGIN_URI . 'images/FODISZ_fekvo_logo_color.jpg') . '" alt="FODISZ">'
            . '<span>VESPA ' . esc_html(VITAREX_VESPA_VERSION) . '</span>'
            . '</span>';
    }

    public function addAdminScriptsStyles()
    {
        wp_enqueue_style('jquery-ui-datepicker-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css');


        wp_enqueue_style('woobs_fontawesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css');
        wp_enqueue_style('datatables_css', '//cdn.datatables.net/1.11.0/css/jquery.dataTables.min.css');
        wp_enqueue_style('datetimepicker_css', VITAREX_VESPA_PLUGIN_URI . 'css/jquery.datetimepicker.min.css');
        wp_enqueue_style('vespa_palette_css', VITAREX_VESPA_PLUGIN_URI . 'css/vespa-palette.css?v=' . time());
        wp_enqueue_style('vespa_admin_css', VITAREX_VESPA_PLUGIN_URI . 'css/vespa-admin.css?v=' . time());

        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('jquery-ui-autocomplete');

        wp_enqueue_script('boostrap_js', VITAREX_VESPA_PLUGIN_URI . 'js/bootstrap.min.js', ['jquery'], '1.0', true);
        wp_enqueue_script('bootbox_js', '//cdnjs.cloudflare.com/ajax/libs/bootbox.js/6.0.0/bootbox.min.js', ['boostrap_js'], '1.0', true);
        wp_enqueue_script('datatables_js', '//cdn.datatables.net/1.11.0/js/jquery.dataTables.min.js', ['jquery'], '1.0', true);
        wp_enqueue_script('datetimepicker_js', VITAREX_VESPA_PLUGIN_URI . 'js/jquery.datetimepicker.full.min.js', ['jquery'], '1.0', true);

        // A szövegszerkesztőt a WordPress beépített TinyMCE-je adja (wp_editor()
        // a contest_editor.php-ben). Külön CDN-es TinyMCE-t NEM töltünk be: az
        // felülírná a window.tinymce.baseURL-t, amitől a WP saját szerkesztője
        // a rossz útvonalról kérné a témáját és összeomlana a post.php-n.

        wp_enqueue_script('vespa_ajax_form_js', VITAREX_VESPA_PLUGIN_URI . 'js/vespa-ajax-form.js?v=' . time(), [], '1.0', true);
        wp_enqueue_script('vespa_admin_js', VITAREX_VESPA_PLUGIN_URI . 'js/vespa-admin.js?v=' . time(), ['jquery', 'datatables_js', 'vespa_ajax_form_js'], '1.0', true);

        wp_enqueue_style('bootstrap_min_css', VITAREX_VESPA_PLUGIN_URI . 'css/bootstrap.min.css?v=2');
    }
}

VESPA_Assets::getInstance();
