<?php
if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

class Disciple_Tools_Custom_Login_Base
{
    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        add_filter( 'dt_non_standard_front_page', [ $this, 'dt_non_standard_front_page' ], 10, 1 );
    }

    public function theme_redirect() {
        $path = get_theme_file_path('template-blank.php');
        include( $path );
        die();
    }

    public function dt_non_standard_front_page( $url ) {
        if ( dt_is_rest() ) {
            return $url;
        }
        /**
         * This handles a logged in persons urls
         */
        if ( user_can( get_current_user_id(), 'grid_contributor')) {
            $current_url = dt_get_url_path();
            // home
            if ( empty($current_url) ) {
                $url = home_url( '/' );
            }
            else if ( 'examples' === $current_url ) {
                $url = home_url( '/examples' );
            }
            else if ( 'projects' === $current_url ) {
                $url = home_url( '/projects' );
            }
            else if ( 'login' === $current_url ) {
                $url = home_url( '/login' );
            }
            else if ( 'facts' === $current_url ) {
                $url = home_url( '/facts' );
            }
        }
        return $url;
    }

    public function _header(){
        wp_head();
        $this->header_style();
        $this->header_javascript();
        location_grid_signup_header();
    }
    public function header_style(){
        ?>
        <style>
            body {
                background: white;
            }
        </style>
        <?php
    }
    public function _browser_tab_title( $title ){
        return 'Location Grid';
    }
    public function header_javascript(){
        ?>
        <!-- script
        ================================================== -->
        <script>
            let jsObject = [<?php echo json_encode([
                'map_key' => DT_Mapbox_API::get_key(),
                'root' => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'details' => $content = get_option('landing_content'),
                'translations' => [
                    'add' => __( 'Add Magic', 'disciple_tools' ),
                ],
            ]) ?>][0]

            jQuery(document).ready(function(){
                clearInterval(window.fiveMinuteTimer)
            })
        </script>
        <?php
        return true;
    }
    public function _footer(){
        wp_footer();
    }
    public function scripts() {
    }
    public function _print_scripts(){
        // @link /disciple-tools-theme/dt-assets/functions/enqueue-scripts.php
        $allowed_js = [
            'jquery',
            'jquery-ui',
            'site-js',
            'lodash',
            'moment',
            'mapbox-gl',
            'mapbox-cookie',
            'mapbox-search-widget',
            'google-search-widget',
        ];

        global $wp_scripts;

        if ( isset( $wp_scripts ) ){
            foreach ( $wp_scripts->queue as $key => $item ){
                if ( ! in_array( $item, $allowed_js ) ){
                    unset( $wp_scripts->queue[$key] );
                }
            }
        }
        unset( $wp_scripts->registered['mapbox-search-widget']->extra['group'] );
    }
    public function _print_styles(){
        // @link /disciple-tools-theme/dt-assets/functions/enqueue-scripts.php
        $allowed_css = [
            'foundation-css',
            'jquery-ui-site-css',
            'site-css',
            'mapbox-gl-css',
        ];

        global $wp_styles;
        if ( isset( $wp_styles ) ) {
            foreach ($wp_styles->queue as $key => $item) {
                if ( !in_array( $item, $allowed_css )) {
                    unset( $wp_styles->queue[$key] );
                }
            }
        }
    }
}
Disciple_Tools_Custom_Login_Base::instance();
