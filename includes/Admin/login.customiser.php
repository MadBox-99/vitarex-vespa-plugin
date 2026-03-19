<?php

Class VESPA_LoginCustomiser extends Singleton
{
    public function __construct()
    {
        add_filter('login_headerurl', array( $this, 'replace_login_logo_url') );

        add_action('login_enqueue_scripts', array( $this, 'load_login_stylesheet') );
        add_action( 'template_redirect', array($this, 'redirect_to_admin') );
    }

    public function replace_login_logo_url(){
        return home_url();
    }

    public function load_login_stylesheet(){
        wp_enqueue_style('custom-login', VITAREX_VESPA_PLUGIN_URI . '/css/vespa-login.css');   
    }

    public function redirect_to_admin(){
        if( isset($_GET['mvk_download_document']) && is_user_logged_in() ){
            return false;
        }

        if( isset($_GET['mvk_export_voluntaries']) && is_user_logged_in() ){
            return false;
        }

        if( isset($_GET['mvk_import_voluntaries']) && is_user_logged_in() ){
            return false;
        }

        if( !is_admin() ){
            if( is_user_logged_in() )
                wp_redirect( admin_url() );
            else
                wp_redirect( wp_login_url() );
            die();
        }
    }    

}

VESPA_LoginCustomiser::getInstance();
