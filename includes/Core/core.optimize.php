<?php

Class Optimize
{
    public function __construct()
    {
        # Remove wp version from the head
        remove_action( 'wp_head', 'wp_generator' ); 
        add_filter( 'the_generator', '__return_empty_string' );

        # Theme supports - https://www.daddydesign.com/wordpress/how-to-add-features-in-wordpress-using-add_theme_support-function/
        add_theme_support( 'post-thumbnails' );
        add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption' ) );

        if ( isset($_GET['debug']) && $_GET['debug'] == 1 ) {
            show_admin_bar( true );
        } else {
            show_admin_bar( false );
        }

        # slow hearthbeat
        add_filter( 'heartbeat_settings', function($settings) {
            $settings['interval'] = 60; 
            return $settings;
        } );

        # clear uploaded filename
        add_filter( 'sanitize_file_name', array($this, 'clearUploadedFileName') );

        # disable author search for security reasons
        add_action( 'wp', array($this, 'disableAuthorSearch') );

        # disable emojis
        $this->disableEmojis();

        # disable xmlrpc - https://wpengine.com/resources/xmlrpc-php/
        $this->disableXMLrpc();

        # disable comments
        $this->disableComments();

        # disable embed - https://kinsta.com/knowledgebase/disable-embeds-wordpress/
        $this->disableEmbed();

        # disable JSON rest api
        //$this->disableRestApi();

        //add_action('wp_dashboard_setup', array($this, 'removeDashboardWidgets') );
    }

    function clearUploadedFileName($filename)
    {
        $sanitized_filename = remove_accents( $filename );
        
        $invalid = [
            ' '   => '-',
            '%20' => '-',
            '_'   => '-',
        ];
        $sanitized_filename = str_replace( array_keys( $invalid ), array_values( $invalid ), $sanitized_filename );
        $sanitized_filename = preg_replace( '/[^A-Za-z0-9-\. ]/', '', $sanitized_filename ); // Remove all non-alphanumeric except .
        $sanitized_filename = preg_replace( '/\.(?=.*\.)/', '', $sanitized_filename ); // Remove all but last .
        $sanitized_filename = preg_replace( '/-+/', '-', $sanitized_filename ); // Replace any more than one - in a row
        $sanitized_filename = str_replace( '-.', '.', $sanitized_filename ); // Remove last - if at the end
        $sanitized_filename = strtolower( $sanitized_filename ); // Lowercase
        $sanitized_filename = apply_filters( 'wp_tweaks_sanitize_file_name', $sanitized_filename, $filename );
        return $sanitized_filename;
    }

    public function removeDashboardWidgets()
    {
        global $wp_meta_boxes;
        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press']);
        unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_incoming_links']);
        unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']);
        unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_plugins']);
        unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_drafts']);
        unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_comments']);
        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_primary']);
        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_secondary']);
    }
    
    public function disableAuthorSearch()
    {
        $disable_author_page = apply_filters( 'wp_tweaks_disable_author_page', true );
        $disable_author_query = apply_filters( 'wp_tweaks_disable_author_query', true );
        
        global $wp_query;
        
        if ( $disable_author_query && isset( $_GET['author'] ) ) {
            $wp_query->set_404();
            status_header( 404 );
        } else if ( $disable_author_page && is_author() ) {
            $wp_query->set_404();
            status_header( 404 );
        }
    }

    public function disableEmojis()
    {
        remove_action( 'admin_print_styles', 'print_emoji_styles' );
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    }

    public function disableXMLrpc()
    {
        add_filter( 'xmlrpc_enabled', '__return_false' );
        remove_action( 'wp_head', 'rsd_link' ); 
        remove_action( 'wp_head', 'wlwmanifest_link' ); 
        remove_action( 'wp_head', 'wp_shortlink_wp_head' );
        remove_action( 'wp_head', 'wp_resource_hints', 2 );
    }

    public function disableComments()
    {
        if( is_admin() ) {
            update_option( 'default_comment_status', 'closed' ); 
        }
        add_filter( 'comments_open', '__return_false', 20, 2 );
        add_filter( 'pings_open', '__return_false', 20, 2 );
        add_action( 'admin_init', function() {
            $post_types     = get_post_types();
            foreach($post_types as $post_type) {
                if (post_type_supports($post_type, 'comments') ) {
                    remove_post_type_support($post_type, 'comments');
                    remove_post_type_support($post_type, 'trackbacks');
                }
            }
        }); 
        add_action( 'admin_menu', function() {
            remove_menu_page('edit-comments.php');
        });
        
        add_action( 'wp_before_admin_bar_render', function() {
            global $wp_admin_bar;
            $wp_admin_bar->remove_menu('comments');
        });
        add_action('init', function(){
            wp_deregister_script( 'comment-reply' );
        });
    }

    public function disableEmbed()
    {
        add_action( 'wp_enqueue_scripts', function() {
            wp_deregister_script('wp-embed');
        }, 100 );
        add_action( 'init', function() {
            remove_action( 'wp_head', 'wp_oembed_add_host_js' ); 
            remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );        
            //remove_action( 'rest_api_init', 'wp_oembed_register_route' );
            remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
            add_filter( 'embed_oembed_discover', '__return_false' );            
        });
    }

    public function tweakDisableFeed() {
        wp_die( __( 'No feed available, please visit the <a href="'. esc_url( home_url( '/' ) ) .'">homepage</a>!' ) );
    }

    public function disableFeeds()
    {
        remove_action( 'wp_head', 'feed_links_extra', 3 );
        remove_action( 'wp_head', 'feed_links', 2 );
        add_action('do_feed', array($this, 'tweakDisableFeed'), 1);
        add_action('do_feed_rdf', array($this, 'tweakDisableFeed'), 1);
        add_action('do_feed_rss', array($this, 'tweakDisableFeed'), 1);
        add_action('do_feed_rss2', array($this, 'tweakDisableFeed'), 1);
        add_action('do_feed_atom', array($this, 'tweakDisableFeed'), 1);
        add_action('do_feed_rss2_comments', array($this, 'tweakDisableFeed'), 1);
        add_action('do_feed_atom_comments', array($this, 'tweakDisableFeed'), 1);
    }

    public function disableRestApi()
    {
        // Remove the references to the JSON api
        remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
        remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );
        remove_action( 'rest_api_init', 'wp_oembed_register_route' );
        add_filter( 'embed_oembed_discover', '__return_false' );
        remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
        remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
        remove_action( 'wp_head', 'wp_oembed_add_host_js' );
        remove_action( 'template_redirect', 'rest_output_link_header', 11, 0 );

        // Disable the API completely - some plugin required, so not disable completly
        /*add_filter('json_enabled', '__return_false');
        add_filter('json_jsonp_enabled', '__return_false');
        add_filter('rest_enabled', '__return_false');
        add_filter('rest_jsonp_enabled', '__return_false');*/
    }
    
}

$optimize = new Optimize();