<?php
if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly


function dt_custom_login_defaults() {
    $defaults = get_option( 'dt_custom_login_defaults' );
    if ( empty( $defaults) ) {
        $defaults = [
            'users_can_register' => get_option( 'users_can_register' ),
            'default_role' => 'registered',
            'login_url' => 'login',
            'redirect_url' => 'settings',
        ];
        update_option( 'dt_custom_login_defaults', $defaults, true );
    }
    return $defaults;
}


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

        add_filter( 'register_dt_custom_login_vars', [ $this, 'register_dt_custom_login_vars'], 10, 1 );
        if ( is_admin() ) {
            add_action( 'dt_custom_login_admin_fields', [ $this, 'dt_custom_login_admin_fields' ], 5, 1 );
            add_filter( 'dt_custom_login_admin_update_fields', [ $this, 'dt_custom_login_admin_update_fields' ], 10, 1 );
        }

        $url = dt_get_url_path();
        if ( ( 'login' === substr( $url, 0, 5 ) )  ) {
            add_action( "template_redirect", [ $this, 'theme_redirect' ] );

            add_filter( 'dt_blank_access', function(){ return true;
            } );
            add_filter( 'dt_allow_non_login_access', function(){ return true;
            }, 100, 1 );

            add_filter( "dt_blank_title", [ $this, "_browser_tab_title" ] );
            add_action( 'dt_blank_head', [ $this, '_header' ] );
            add_action( 'dt_blank_footer', [ $this, '_footer' ] );
            add_action( 'dt_blank_body', [ $this, 'body' ] ); // body for no post key

            // load page elements
            add_action( 'wp_print_scripts', [ $this, '_print_scripts' ], 1500 );
            add_action( 'wp_print_styles', [ $this, '_print_styles' ], 1500 );

            add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );
        }

    }

    public function register_dt_custom_login_vars( $vars ) {
        $defaults = dt_custom_login_defaults();
        foreach( $defaults as $k => $v ) {
            $vars[$k] = $v;
        }
        return $vars;
    }

    public function dt_custom_login_admin_update_fields( $post_vars ) {
        $defaults = dt_custom_login_defaults();

        // user register
        if ( isset( $post_vars['users_can_register'] ) ) {
            if ( $post_vars['users_can_register'] !== $defaults['users_can_register'] ) {
                $defaults['users_can_register'] = $post_vars['users_can_register'];
                update_option( 'dt_custom_login_defaults', $defaults, true );
                update_option( 'users_can_register', 1, true );
            }
        } else {
            if ( ! empty( $defaults['users_can_register'] ) ) {
                $defaults['users_can_register'] = 0;
                update_option( 'dt_custom_login_defaults', $defaults, true );
                update_option( 'users_can_register', 0, true );
            }
        }

        // roles
        if ( isset( $post_vars['default_role'] ) ) {
            if ( $post_vars['default_role'] !== $defaults['default_role'] ) {
                $defaults['default_role'] = $post_vars['default_role'];
                update_option( 'dt_custom_login_defaults', $defaults, true );
            }
        }

        // login
        if ( isset( $post_vars['login_url'] ) ) {
            if ( $post_vars['login_url'] !== $defaults['login_url'] ) {
                $defaults['login_url'] = $post_vars['login_url'];
                update_option( 'dt_custom_login_defaults', $defaults, true );
            }
        }

        // redirect
        if ( isset( $post_vars['redirect_url'] ) ) {
            if ( $post_vars['redirect_url'] !== $defaults['redirect_url'] ) {
                $defaults['redirect_url'] = $post_vars['redirect_url'];
                update_option( 'dt_custom_login_defaults', $defaults, true );
            }
        }



        return $post_vars;
    }

    public function dt_custom_login_admin_fields( $dt_custom_login ) {
        ?>
        <tr>
            <td colspan="2">
                <strong>Global Settings</strong>
            </td>
        </tr>
        <tr>
            <th scope="row"></th>
            <td> <fieldset><legend class="screen-reader-text"><span><?php _e( 'Membership' ); ?></span></legend><label for="users_can_register">
                        <input name="users_can_register" type="checkbox" id="users_can_register" value="1" <?php checked( '1', get_option( 'users_can_register' ) ); ?> />
                        <?php _e( 'Anyone can register' ); ?></label>
                </fieldset>
            </td>
        </tr>
        <tr>
            <th scope="row"></th>
            <td>
                Default role for new registrations. (Recommended: registered, multiplier, partner)<br>
                <select name="default_role">
                    <?php wp_dropdown_roles( $dt_custom_login['default_role'] ?? 'registered' ); ?>
                </select>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <strong>Navigation</strong>
            </td>
        </tr>
        <tr>
            <td style="font-size:1.2em; text-align: center;">
                <?php
                if ( empty( $dt_custom_login['login_url'] ) ) {
                    echo '&#10060;';
                } else {
                    echo '&#9989;';
                }
                ?>
            </td>
            <td>
                <strong>Login URL</strong><br>
                <strong><?php echo esc_url( site_url('/')) ?></strong><input class="regular-text" name="login_url" placeholder="Login Page" value="<?php echo $dt_custom_login['login_url'] ?>"/> <br>
            </td>
        </tr>
        <tr>
            <td style="font-size:1.2em; text-align: center;">
                <?php
                if ( empty( $dt_custom_login['redirect_url'] ) ) {
                    echo '&#10060;';
                } else {
                    echo '&#9989;';
                }
                ?>
            </td>
            <td>
                <strong>Success URL</strong> <br>(when someone successfully logs in, where do they get redirected)<br>
                <strong><?php echo esc_url( site_url('/')) ?></strong><input class="regular-text" name="redirect_url" placeholder="Redirect Page" value="<?php echo $dt_custom_login['redirect_url'] ?>"/> <br>
            </td>
        </tr>
        <?php
    }

    public function theme_redirect() {
        $path = get_theme_file_path('template-blank.php');
        include( $path );
        die();
    }

    public function _header(){
        do_action( 'dt_custom_login_head_top' );
        wp_head();
        $this->header_style();
        $this->header_javascript();
        do_action( 'dt_custom_login_head_bottom' );

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
        $dt_custom_login = dt_custom_login_vars();
        ?>
        <!--Google Sign in-->
        <?php // @codingStandardsIgnoreStart
        if ( ! empty( $dt_custom_login['google_sso_key'] ) ) :
            ?>
            <script src="https://apis.google.com/js/platform.js?onload=start" async defer></script>
        <?php // @codingStandardsIgnoreEnd
        endif;
        ?>

        <script>
            function start() {
                gapi.load('auth2', function() {
                    auth2 = gapi.auth2.init({
                        client_id: '<?php echo esc_attr( $dt_custom_login['google_sso_key'] ); ?>',
                        scope: 'profile email'
                    });
                    if ( typeof gapi !== "undefined" ) {
                        jQuery('.google_elements').show()
                    }
                });
            }
        </script>
        <!-- Google Captcha -->
        <script>
            var verifyCallback = function(response) {
                jQuery('#submit').prop("disabled", false);
            };
            var onloadCallback = function() {
                grecaptcha.render('g-recaptcha', {
                    'sitekey' : '<?php echo esc_attr( $dt_custom_login['google_captcha_client_key'] ); ?>',
                    'callback' : verifyCallback,
                });
            };
        </script>
        <?php
        ?>
        <!-- script
        ================================================== -->
        <script>
            let jsObject = [<?php echo json_encode([
                'map_key' => DT_Mapbox_API::get_key(),
                'root' => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
//                'details' => $content = get_option('landing_content'),
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
//            'captcha'
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

    public function body(){
        require_once( plugin_dir_path(__DIR__) . '/login/template.php');
    }
}
Disciple_Tools_Custom_Login_Base::instance();
