<?php
if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

// @todo

function dt_custom_login_facebook_enabled() : bool {
    $dt_custom_login = dt_custom_login_vars();
    if ( isset( $dt_custom_login['facebook_public_key'] ) && ! empty( $dt_custom_login['facebook_public_key'] ) ) {
        return true;
    }
    return false;
}

function dt_custom_login_facebook_defaults() {
    $defaults = get_option( 'dt_custom_login_facebook' );
    if ( empty( $defaults) ) {
        $defaults = [
            'facebook_public_key' => '',
            'facebook_secret_key' => '',
        ];
        update_option( 'dt_custom_login_facebook', $defaults, true );
    }
    return $defaults;
}


class DT_Custom_Login_Facebook {
    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ){
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_filter( 'register_dt_custom_login_vars', [ $this, 'register_dt_custom_login_vars'], 10, 1 );
        if ( is_admin() ) {
            add_action('dt_custom_login_admin_fields', [ $this, 'dt_custom_login_admin_fields' ], 40, 1 );
            add_filter( 'dt_custom_login_admin_update_fields', [ $this, 'dt_custom_login_admin_update_fields'], 10, 1 );
        }

        if ( dt_custom_login_facebook_enabled() ) {
            require_once( plugin_dir_path(__DIR__) . 'vendor/autoload.php' );
            add_filter( 'dt_allow_rest_access', [ $this, '_authorize_url' ], 10, 1 );
            add_action( 'rest_api_init', array( $this,  'add_api_routes' ) );

            add_action( 'additional_login_buttons', 30, 1 );
        }
    }

    public function register_dt_custom_login_vars( $vars ) {
        $defaults = dt_custom_login_facebook_defaults();
        foreach( $defaults as $k => $v ) {
            $vars[$k] = $v;
        }
        return $vars;
    }

    public function dt_custom_login_admin_fields( $dt_custom_login ) {
        ?>
        <tr>
            <td colspan="2">
                <strong>Facebook</strong>
            </td>
        </tr>
        <tr>
            <td style="font-size:1.2em; text-align: center;">
                <?php
                if ( empty( $dt_custom_login['facebook_public_key'] ) ) {
                    echo '&#10060;';
                } else {
                    echo '&#9989;';
                }
                ?>
            </td>
            <td>
                <strong>Facebook SSO Login/Registration Secret Key</strong><br>
                <input class="regular-text" name="facebook_public_key" placeholder="Facebook Public Key" value="<?php echo $dt_custom_login['facebook_public_key'] ?>"/><br>
                <input class="regular-text" name="facebook_secret_key" placeholder="Facebook Secret Key" value="<?php echo $dt_custom_login['facebook_secret_key'] ?>"/><br>
            </td>
        </tr>
        <?php
    }

    public function additional_login_buttons( $dt_custom_login ) {
        ?>
        <?php if ( isset( $dt_custom_login['facebook_public_key'] ) && ! empty( $dt_custom_login['facebook_public_key'] ) ) : ?>
            <p class="facebook_elements" style="display:none;">
                <?php dt_custom_login_facebook_login_button(); ?>
            </p>
        <?php endif; ?>
        <?php
    }

    public function dt_custom_login_admin_update_fields( $post_vars ) {
        if ( isset( $post_vars['facebook_public_key'] ) ) {
            $defaults = dt_custom_login_facebook_defaults();
            if ( $post_vars['facebook_public_key'] !== $defaults['facebook_public_key'] ) {
                $defaults['facebook_public_key'] = $post_vars['facebook_public_key'];
                update_option( 'dt_custom_login_facebook', $defaults, true );
            }
        }
        if ( isset( $post_vars['facebook_secret_key'] ) ) {
            $defaults = dt_custom_login_facebook_defaults();
            if ( $post_vars['facebook_secret_key'] !== $defaults['facebook_secret_key'] ) {
                $defaults['facebook_secret_key'] = $post_vars['facebook_secret_key'];
                update_option( 'dt_custom_login_facebook', $defaults, true );
            }
        }

        return $post_vars;
    }

    public function _authorize_url( $authorized ){
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'location_grid/v1/' ) !== false ) {
            $authorized = true;
        }
        return $authorized;
    }

    public function add_api_routes() {
        $namespace = 'location_grid/v1';
        register_rest_route( $namespace, '/register_via_facebook', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'register_via_facebook' ),
                'permission_callback' => '__return_true',
            ),
        ) );
        register_rest_route( $namespace, '/link_account_via_facebook', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'link_account_via_facebook' ),
                'permission_callback' => '__return_true',
            ),
        ) );
    }

    public function register_via_facebook( WP_REST_Request $request ) {
        dt_write_log( __METHOD__ );
        $dt_custom_login = dt_custom_login_vars();
        $params = $request->get_json_params();

        if ( empty( $params['token'] ) ) {
            return new WP_Error( __METHOD__, __( 'You are missing your sign-in token. Try signing in again.', 'location_grid' ) );
        }

        try {
            $fb = new \Facebook\Facebook(array(
                'app_id' => $dt_custom_login['facebook_public_key'],
                'app_secret' => $dt_custom_login['facebook_secret_key'],
                'default_graph_version' => 'v3.2',
            ));
        } catch ( Exception $exception ) {
            return new WP_Error( __METHOD__, __( 'Failed to connect with Facebook. Try again.', 'location_grid' ), $exception );
        }

        try {
            $fb_response = $fb->get( '/me?fields=name,email,first_name,last_name', $params['token'] );

            $payload = $fb_response->getDecodedBody();

            $facebook_user_id = $payload['id'];
            $user_email = $payload['email'];
            $user_nicename = $payload['name'];
            $first_name = $payload['first_name'];
            $last_name = $payload['last_name'];

            $random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
            $username = str_replace( ' ', '_', $payload['name'] );

        } catch ( \Facebook\Exceptions\FacebookResponseException $e ) {
            // When Graph returns an error
            return new WP_Error( __METHOD__, __( 'Facebook user lookup error. Sorry for the inconvenience.', 'location_grid' ), $e->getMessage() );
        } catch ( \Facebook\Exceptions\FacebookSDKException $e ) {
            // When validation fails or other local issues
            return new WP_Error( __METHOD__, __( 'Error with looking up user with Facebook. Sorry for the inconvenience.', 'location_grid' ), $e->getMessage() );
        }

        if ( empty( $user_email ) ) {
            return new WP_Error( __METHOD__, __( 'Email is a required permission for login and registration.', 'location_grid' ) );
        }

        $user_id = $this->query_facebook_email( $user_email );

        // if no google_sso_email found and user with email does not exist
        if ( empty( $user_id ) && ! email_exists( $user_email ) ) {

            // create a user from Google data
            $userdata = array(
                'user_login'      => sanitize_user( $username, false ) . '_'. rand( 100, 999 ),
                'user_pass'       => $random_password,  // When creating an user, `user_pass` is expected.
                'user_nicename'   => sanitize_text_field( $user_nicename ),
                'user_email'      => sanitize_email( $user_email ),
                'display_name'    => sanitize_text_field( $user_nicename ),
                'nickname'        => sanitize_text_field( $user_nicename ),
                'first_name'      => sanitize_text_field( $first_name ),
                'last_name'       => sanitize_text_field( $last_name ),
                'user_registered' => current_time( 'mysql' ),
            );

            $user_id = wp_insert_user( $userdata );

            if ( is_wp_error( $user_id ) || 0 === $user_id ) {
                dt_write_log( $user_id );
                // return WP_Error
                return $user_id;
            }

//            location_grid_update_user_ip_address_and_location( $user_id ); // record ip address and location

//            add_user_meta( $user_id, 'location_grid_language', 'en', true );
//            add_user_meta( $user_id, 'location_grid_phone_number', null, true );
//            add_user_meta( $user_id, 'location_grid_address', null, true );
//            add_user_meta( $user_id, 'location_grid_affiliation_key', null, true );
            add_user_meta( $user_id, 'location_grid_meta', get_location_grid_meta_array(), true );

            add_user_meta( $user_id, 'facebook_sso_email', $user_email, true );

            add_user_to_blog( get_current_blog_id(), $user_id, 'subscriber' ); // add user to ZumeProject site.
//            add_user_to_blog( '12', $user_id, 'subscriber' ); // add user to Zume Vision

        }
        // if no facebook_sso_email found but user with email does exist
        else if ( empty( $user_id ) && email_exists( $user_email ) ) {
            $user_id = email_exists( $user_email );

            add_user_meta( $user_id, 'facebook_sso_email', $user_email, true );

            if ( empty( get_user_meta( $user_id, 'first_name' ) ) ) {
                update_user_meta( $user_id, 'first_name', $first_name );
            }
            if ( empty( get_user_meta( $user_id, 'last_name' ) ) ) {
                update_user_meta( $user_id, 'last_name', $last_name );
            }
            if ( empty( get_user_meta( $user_id, 'nickname' ) ) ) {
                update_user_meta( $user_id, 'nickname', $user_nicename );
            }

            add_user_to_blog( get_current_blog_id(), $user_id, 'subscriber' ); // add user to ZumeProject site.
            add_user_to_blog( '12', $user_id, 'subscriber' ); // add user to Zume Vision
        }


        // add google id if needed
        if ( ! get_user_meta( $user_id, 'facebook_sso_id' ) ) {
            update_user_meta( $user_id, 'facebook_sso_id', $facebook_user_id );
        }

        // store google session token
        update_user_meta( $user_id, 'facebook_session_token', $params['token'] );

        // log user in
        $user = get_user_by( 'id', $user_id );
        if ( $user ) {
            dt_write_log( 'User exists ' );
            wp_set_current_user( $user_id, $user->user_login );
            wp_set_auth_cookie( $user_id );
            do_action( 'wp_login', $user->user_login, $user );
            return true;
        } else {
            return new WP_Error( __METHOD__, 'No user found.' );
        }

    }

    public function link_account_via_facebook( WP_REST_Request $request ) {

        dt_write_log( __METHOD__ );
        $dt_custom_login = dt_custom_login_vars();
        $params = $request->get_json_params();

        if ( empty( $params['token'] ) ) {
            return new WP_Error( __METHOD__, __( 'You are missing your sign-in token. Try signing in again.', 'location_grid' ) );
        }

        try {
            $fb = new \Facebook\Facebook(array(
                'app_id' => $dt_custom_login['facebook_public_key'],
                'app_secret' => $dt_custom_login['facebook_secret_key'],
                'default_graph_version' => 'v3.2',
            ));
        } catch ( Exception $exception ) {
            return new WP_Error( __METHOD__, __( 'Failed to connect with Facebook. Try again.', 'location_grid' ), $exception );
        }

        try {
            $fb_response = $fb->get( '/me?fields=name,email,first_name,last_name', $params['token'] );

            $payload = $fb_response->getDecodedBody();

            $facebook_user_id = $payload['id'];
            $facebook_email = $payload['email'];
            $user_nicename = $payload['name'];
            $first_name = $payload['first_name'];
            $last_name = $payload['last_name'];

        } catch ( \Facebook\Exceptions\FacebookResponseException $e ) {
            // When Graph returns an error
            return new WP_Error( __METHOD__, __( 'Facebook user lookup error. Sorry for the inconvenience.', 'location_grid' ), $e->getMessage() );
        } catch ( \Facebook\Exceptions\FacebookSDKException $e ) {
            // When validation fails or other local issues
            return new WP_Error( __METHOD__, __( 'Error with looking up user with Facebook. Sorry for the inconvenience.', 'location_grid' ), $e->getMessage() );
        }

        $current_user_id = get_current_user_id();
        $current_userdata = get_userdata( $current_user_id );
        if ( empty( $current_user_id ) || empty( $current_userdata ) ) {
            return new WP_Error( __METHOD__, __( 'No user found.', 'location_grid' ) );
        }

        if ( ! ( $facebook_email === $current_userdata->user_email ) ) { // if current user email is not the same as the facebook email

            // test if another wp user account is established with the facebook email
            $another_user_id_with_facebook_email = email_exists( $facebook_email );
            if ( $another_user_id_with_facebook_email ) {
                return new WP_Error( __METHOD__, __( 'Facebook email already linked with another account. Login to this account or use forgot password tool to access account.', 'location_grid' ) );
            }

            // test if another wp user is linked with the facebook email account
            $existing_link = $this->query_facebook_email( $facebook_email );
            if ( $existing_link ) {
                return new WP_Error( __METHOD__, __( 'Facebook already linked with another account.', 'location_grid' ) );
            }
        }

        add_user_meta( $current_user_id, 'facebook_sso_email', $facebook_email, true );

        if ( empty( get_user_meta( $current_user_id, 'first_name' ) ) ) {
            update_user_meta( $current_user_id, 'first_name', $first_name );
        }
        if ( empty( get_user_meta( $current_user_id, 'last_name' ) ) ) {
            update_user_meta( $current_user_id, 'last_name', $last_name );
        }
        if ( empty( get_user_meta( $current_user_id, 'nickname' ) ) ) {
            update_user_meta( $current_user_id, 'nickname', $user_nicename );
        }

        // add google id if needed
        if ( ! ( get_user_meta( $current_user_id, 'facebook_sso_id' ) ) ) {
            update_user_meta( $current_user_id, 'facebook_sso_id', $facebook_user_id );
        }

        // store google session token
        update_user_meta( $current_user_id, 'facebook_session_token', $params['token'] );

        return true;

    }

    public function query_facebook_email( $email_address ) {
        global $wpdb;
        $result = $wpdb->get_var( $wpdb->prepare( "
    SELECT user_id
    FROM $wpdb->usermeta
    WHERE meta_key = 'facebook_sso_email'
      AND meta_value = %s
      LIMIT 1
    ", $email_address ) );

        if ( ! empty( $result ) ) {
            return $result;
        } else {
            return false;
        }
    }
}
DT_Custom_Login_Facebook::instance();


function dt_custom_login_facebook_login_button() {
    $dt_custom_login = dt_custom_login_vars();
    // @see https://developers.facebook.com/apps/762591594092101/fb-login/quickstart/
    ?>

    <!--Facebook signin-->
    <script>
        window.fbAsyncInit = function() {
            FB.init({
                appId      : '<?php echo esc_attr($dt_custom_login['facebook_public_key'] ) ?>',
                cookie     : true,
                xfbml      : true,
                version    : 'v3.2'
            });

            FB.AppEvents.logPageView();
            checkLoginState()
        };

        (function(d, s, id){
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) {return;}
            js = d.createElement(s); js.id = id;
            js.src = "https://connect.facebook.net/en_US/sdk.js";
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));

        function checkLoginState() {
            FB.getLoginStatus(function(response) {
                if (response.status === 'connected') {
                    // Logged into your app and Facebook.
                    console.log('checkLoginState facebook connected')
                    console.log(response)
                    jQuery('.facebook_elements').show()
                } else {
                    // The person is not logged into this app or we are unable to tell.
                    console.log(' checkLoginState facebook not connected')
                    jQuery('.facebook_elements').show()
                }
            });
        }

        function facebook_signin() {
            FB.login(function(response) {
                if (response.status === 'connected') {
                    // Logged into your app and Facebook.
                    console.log('fbLogIn facebook connected')

                    let data = {
                        "token": response.authResponse.accessToken
                    };
                    jQuery.ajax({
                        type: "POST",
                        data: JSON.stringify(data),
                        contentType: "application/json; charset=utf-8",
                        dataType: "json",
                        url: '<?php echo esc_url( rest_url( '/dt_custom_login/v1/register_via_facebook' ) ) ?>',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ) ?>');
                        },
                    })
                        .done(function (data) {
                            console.log(data)
                            if ( data ) {
                                window.location = "<?php echo esc_url( home_url('/profile') ) ?>"
                            }
                        })
                        .fail(function (err) {
                            if ( err.responseJSON['message'] ) {
                                jQuery('#google_error').text( err.responseJSON['message'] )
                            } else {
                                jQuery('#google_error').html( '<?php esc_html_e( 'Oops. Something went wrong.', 'dt_custom_login' ); ?>' )
                            }
                            console.log("error")
                            console.log(err)
                        })
                } else {
                    // The person is not logged into this app or we are unable to tell.
                    console.log('fbLogIn facebook not connected')
                }
            }, {scope: 'email'} )
        }

        jQuery('#facebook_login').click(function() {
            facebook_signin()
        })

    </script>
    <div class="button hollow facebook_elements" onclick="facebook_signin()" id="facebook_login" style="width:100%; background-color:#3b5998; color:white; display:none;">
        <span style="float:left;">
            <img src="<?php echo esc_url( get_theme_file_uri( '/assets/images/flogo-HexRBG-Wht-72.png' ) ) ?>" style="width:20px;" />
        </span>
        <?php esc_attr_e( 'Facebook', 'dt_custom_login' ) ?>
    </div>
    <div id="facebook_error"></div>
    <?php
}

function dt_custom_login_facebook_link_account_button() {
    $dt_custom_login = dt_custom_login_vars();
    ?>

    <!--Facebook signin-->
    <script>
        window.fbAsyncInit = function() {
            FB.init({
                appId      : '<?php echo esc_attr( $dt_custom_login['facebook_public_key'] ) ?>',
                cookie     : true,
                xfbml      : true,
                version    : 'v3.2'
            });

            FB.AppEvents.logPageView();
            checkLoginState()

        };

        (function(d, s, id){
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) {return;}
            js = d.createElement(s); js.id = id;
            js.src = "https://connect.facebook.net/en_US/sdk.js";
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));

        function checkLoginState() {
            FB.getLoginStatus(function(response) {
                if (response.status === 'connected') {
                    // Logged into your app and Facebook.
                    jQuery('.facebook_elements').show()
                    console.log('checkLoginState facebook connected')
                    console.log(response)
                } else {
                    // The person is not logged into this app or we are unable to tell.
                    jQuery('.facebook_elements').show()
                    console.log(' checkLoginState facebook not connected')
                }
            });
        }

        function facebook_signin() {
            FB.login(function(response) {
                if (response.status === 'connected') {
                    // Logged into your app and Facebook.
                    console.log('fbLogIn facebook connected')

                    jQuery('#facebook_login').attr('style', 'background-color: grey; width:100%;').append(' <img src="<?php echo dt_custom_login_spinner() ?>" width="15px" />')

                    let data = {
                        "token": response.authResponse.accessToken
                    };
                    jQuery.ajax({
                        type: "POST",
                        data: JSON.stringify(data),
                        contentType: "application/json; charset=utf-8",
                        dataType: "json",
                        url: '<?php echo esc_url( rest_url( '/dt_custom_login/v1/link_account_via_facebook' ) ) ?>',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ) ?>');
                        },
                    })
                        .done(function (data) {
                            window.location = "<?php echo esc_url( home_url('/profile') ) ?>"
                        })
                        .fail(function (err) {
                            if ( err.responseJSON['message'] ) {
                                jQuery('#google_error').text( err.responseJSON['message'] )
                            } else {
                                jQuery('#google_error').html( 'Oops. Something went wrong.' )
                            }
                            console.log("error")
                            console.log(err)
                        })
                } else {
                    // The person is not logged into this app or we are unable to tell.
                    console.log('fbLogIn facebook not connected')
                }
            }, {scope: 'email'} )
        }

        jQuery('#facebook_login').click(function() {
            facebook_signin()
        })

    </script>
    <div class="button hollow facebook_elements" onclick="facebook_signin()"  id="facebook_login" style="width:100%; background-color:#3b5998; color:white; display:none;">
        <span style="float:left;">
            <img src="<?php echo esc_url( get_theme_file_uri( '/assets/images/flogo-HexRBG-Wht-72.png' ) ) ?>" style="width:20px;" />
        </span>
        <?php esc_attr_e( 'Facebook', 'dt_custom_login' ) ?>
    </div>
    <div id="facebook_error"></div>

    <?php
}



function dt_custom_login_unlink_facebook_account( $user_id ) {
    if ( isset( $_POST['unlink_facebook'] ) ) {
        if ( isset( $_POST['user_update_nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['user_update_nonce'] ), 'user_' . $user_id. '_update' ) ) {
            return new WP_Error( 'fail_nonce_verification', 'The form requires a valid nonce, in order to process.' );
        }

        delete_user_meta( $user_id, 'facebook_sso_email' );
        delete_user_meta( $user_id, 'facebook_session_token' );
        delete_user_meta( $user_id, 'facebook_sso_id' );
    }
    return 1;
}
add_action( 'dt_custom_login_update_profile', 'dt_custom_login_unlink_facebook_account' );


