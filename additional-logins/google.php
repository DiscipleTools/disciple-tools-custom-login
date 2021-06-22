<?php
if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

// @todo

function dt_custom_login_google_enabled() : bool {
    $dt_custom_login = dt_custom_login_vars();
    if ( isset( $dt_custom_login['google_sso_key'] ) && ! empty( $dt_custom_login['google_sso_key'] ) ) {
        return true;
    }
    return false;
}

function dt_custom_login_google_defaults() {
    $defaults = get_option( 'dt_custom_login_google' );
    if ( empty( $defaults) ) {
        $defaults = [
            'google_sso_key' => '',
        ];
        update_option( 'dt_custom_login_google', $defaults, true );
    }
    return $defaults;
}


class DT_Custom_Login_Google {
    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ){
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
//        add_filter( 'register_dt_custom_login_vars', [ $this, 'register_dt_custom_login_vars'], 10, 1 );
//        if ( is_admin() ) {
//            add_action('dt_custom_login_admin_fields', [ $this, 'dt_custom_login_admin_fields' ], 30, 1 );
//            add_filter( 'dt_custom_login_admin_update_fields', [ $this, 'dt_custom_login_admin_update_fields'], 10, 1 );
//        }

        if ( dt_custom_login_google_enabled() ) {
            require_once( plugin_dir_path(__DIR__) . 'vendor/autoload.php' );
            add_filter( 'dt_allow_rest_access', [ $this, '_authorize_url' ], 10, 1 );
            add_action( 'rest_api_init', array( $this,  'add_api_routes' ) );

            add_action( 'additional_login_buttons', [ $this, 'additional_login_buttons'], 20, 1 );
            add_action( 'dt_custom_login_head_top', [ $this, 'dt_custom_login_head_top' ], 20 );
        }
    }
    public function register_dt_custom_login_vars( $vars ) {
        $defaults = dt_custom_login_google_defaults();
        foreach( $defaults as $k => $v ) {
            $vars[$k] = $v;
        }
        return $vars;
    }
    public function dt_custom_login_admin_fields( $dt_custom_login ) {
        ?>
        <tr>
            <td colspan="2">
                <strong>Google</strong>
            </td>
        </tr>
        <tr>
            <td style="font-size:1.2em; text-align: center;">
                <?php
                if ( empty( $dt_custom_login['google_sso_key'] ) ) {
                    echo '&#10060;';
                } else {
                    echo '&#9989;';
                }
                ?>
            </td>
            <td>
                <strong>Add Google API oAuth Login Key</strong><br>
                <input class="regular-text" name="google_sso_key" placeholder="Google SSO Login/Registration oAuth Key" value="<?php echo $dt_custom_login['google_sso_key'] ?>"/><br>
            </td>
        </tr>
        <?php
    }
    public function dt_custom_login_admin_update_fields( $post_vars ) {
        if ( isset( $post_vars['google_sso_key'] ) ) {
            $defaults = dt_custom_login_google_defaults();
            if ( $post_vars['google_sso_key'] !== $defaults['google_sso_key'] ) {
                $defaults['google_sso_key'] = $post_vars['google_sso_key'];
                update_option( 'dt_custom_login_google', $defaults, true );
            }
        }

        return $post_vars;
    }

    public function additional_login_buttons( $dt_custom_login ) {
        ?>
        <?php if ( isset( $dt_custom_login['google_sso_key'] ) && ! empty( $dt_custom_login['google_sso_key'] ) ) : ?>
            <div id="my-signin2" style="width: 100%;"></div>
            <script>
                function onSuccess(googleUser) {
                    console.log('Logged in as: ' + googleUser.getBasicProfile().getName());
                }
                function onFailure(error) {
                    console.log(error);
                }
                function renderButton() {
                    gapi.signin2.render('my-signin2', {
                        'scope': 'profile email',
                        'width': 500,
                        'height': 50,
                        'longtitle': true,
                        'theme': 'dark',
                        'onsuccess': onSuccess,
                        'onfailure': onFailure
                    });
                }
            </script>
            <script src="https://apis.google.com/js/platform.js?onload=renderButton" async defer></script>
            <style>
                .abcRioButtonBlue {
                    width: 100% !important;
                }
            </style>
            <br>
        <?php endif; ?>
        <?php
    }

    public function dt_custom_login_head_top() {
        $defaults = dt_custom_login_google_defaults();
        if ( isset( $defaults['google_sso_key'] ) && ! empty( $defaults['google_sso_key'] ) ) {
        ?>
            <meta name="google-signin-client_id" content="20352038920-m4unhfjl5vfrk06clo5l8hudtobb8dq4.apps.googleusercontent.com">
        <?php
        }
    }

    public function _authorize_url( $authorized ){
        if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'location_grid/v1/' ) !== false ) {
            $authorized = true;
        }
        return $authorized;
    }

    public function add_api_routes() {
        $namespace = 'location_grid/v1';
        register_rest_route( $namespace, '/register_via_google', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'register_via_google' ),
                'permission_callback' => '__return_true',
            ),
        ) );
        register_rest_route( $namespace, '/link_account_via_google', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'link_account_via_google' ),
                'permission_callback' => '__return_true',
            ),
        ) );
    }

    /**
     * Handles sign in and registration via Google account
     * @param \WP_REST_Request $request
     *
     * @return bool|\WP_Error
     */
    public function register_via_google( WP_REST_Request $request ) {
        dt_write_log( __METHOD__ );
        $dt_custom_login = dt_custom_login_vars();
        $params = $request->get_json_params();

        // verify token authenticity
        /** @see https://developers.google.com/identity/sign-in/web/backend-auth */

        // Get $id_token via HTTPS POST.
        $google_sso_key =  $dt_custom_login['google_sso_key'];

        $google_token = $params['token'];

        \Firebase\JWT\JWT::$leeway = 300;

        $client = new Google_Client( array( 'client_id' => $google_sso_key ) );  // Specify the CLIENT_ID of the app that accesses the backend
        $payload = $client->verifyIdToken( $google_token );
        if ( $payload ) {
            $google_user_id = $payload['sub'];
            $user_email = $payload['email'];
            $user_nicename = $payload['name'];
            $first_name = $payload['given_name'];
            $last_name = $payload['family_name'];
//            $picture_url = $payload['picture'];

            $random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
            $username = str_replace( ' ', '_', $payload['name'] );

        } else {
            dt_write_log( $payload );
            return new WP_Error( __METHOD__, __( 'Failed Google Verification of User Token', 'location_grid' ) ); // Invalid ID token
        }

        if ( empty( $user_email ) ) {
            return new WP_Error( __METHOD__, __( 'Email is a required permission for login and registration.', 'location_grid' ) );
        }


        $user_id = $this->query_google_email( $user_email );
        // if no google_sso_email found and user with email does not exist
        if ( empty( $user_id ) && ! email_exists( $user_email ) ) {

            // create a user from Google data
            $userdata = array(
                'user_login'      => sanitize_user( $username, false ) . '_'. rand( 100, 999 ),
//                'user_url'        => sanitize_text_field( $picture_url ),
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

//            add_user_meta( $user_id, 'location_grid_language', location_grid_current_language(), true );
//            add_user_meta( $user_id, 'location_grid_phone_number', null, true );
//            add_user_meta( $user_id, 'location_grid_address', null, true );
//            add_user_meta( $user_id, 'location_grid_affiliation_key', null, true );
            add_user_meta( $user_id, 'location_grid_meta', get_location_grid_meta_array(), true );

            add_user_meta( $user_id, 'google_sso_email', $user_email, true );

            add_user_to_blog( get_current_blog_id(), $user_id, 'subscriber' ); // add user to ZumeProject site.
//            add_user_to_blog( '12', $user_id, 'subscriber' ); // add user to Zume Vision

        }
        // if no google_sso_email found but user with email does exist
        else if ( empty( $user_id ) && email_exists( $user_email ) ) {
            $user_id = email_exists( $user_email );
            add_user_meta( $user_id, 'google_sso_email', $user_email, true );

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
        $google_id = get_user_meta( $user_id, 'google_sso_id' );
        if ( empty( $google_id ) ) {
            update_user_meta( $user_id, 'google_sso_id', $google_user_id );
        }

        // store google session token
        update_user_meta( $user_id, 'google_session_token', $google_token );

        // log user in
        $user = get_user_by( 'id', $user_id );
        if ( $user ) {
            wp_set_current_user( $user_id, $user->user_login );
            wp_set_auth_cookie( $user_id );
            do_action( 'wp_login', $user->user_login, $user );
            return true;
        } else {
            return new WP_Error( __METHOD__, 'No user found.' );
        }
    }

    public function link_account_via_google( WP_REST_Request $request ) {
        dt_write_log( __METHOD__ );
        $dt_custom_login = dt_custom_login_vars();
        $params = $request->get_json_params();

        // verify token authenticity
        /** @see https://developers.google.com/identity/sign-in/web/backend-auth */

        // Get $id_token via HTTPS POST.
        $google_sso_key = $dt_custom_login['google_sso_key'];

        $google_token = $params['token'];

        \Firebase\JWT\JWT::$leeway = 300;

        $client = new Google_Client( array( 'client_id' => $google_sso_key ) );  // Specify the CLIENT_ID of the app that accesses the backend
        $payload = $client->verifyIdToken( $google_token );
        if ( $payload ) {
            $google_user_id = $payload['sub'];
            $google_email = $payload['email'];
            $user_nicename = $payload['name'];
            $first_name = $payload['given_name'];
            $last_name = $payload['family_name'];

        } else {
            dt_write_log( $payload );
            return new WP_Error( __METHOD__, 'Failed Google Verification of User Token' ); // Invalid ID token
        }

        $current_user_id = get_current_user_id();
        $current_userdata = get_userdata( $current_user_id );
        if ( empty( $current_user_id ) || empty( $current_userdata ) ) {
            return new WP_Error( __METHOD__, 'No user found.' );
        }

        if ( ! ( $google_email === $current_userdata->user_email ) ) { // if current user email is not the same as the facebook email

            // test if another wp user account is established with the facebook email
            $another_user_id_with_facebook_email = email_exists( $google_email );
            if ( $another_user_id_with_facebook_email ) {

                return new WP_Error( __METHOD__, __( 'Facebook email already linked with another account. Login to this account or use forgot password tool to access account.', 'location_grid' ) );
            }

            // test if another wp user is linked with the facebook email account
            $existing_link = $this->query_google_email( $google_email );
            if ( $existing_link ) {
                return new WP_Error( __METHOD__, __( 'Facebook already linked with another account.', 'location_grid' ) );
            }
        }

        add_user_meta( $current_user_id, 'google_sso_email', $google_email, true );

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
        if ( ! get_user_meta( $current_user_id, 'google_sso_id' ) ) {
            update_user_meta( $current_user_id, 'google_sso_id', $google_user_id );
        }

        // store google session token
        update_user_meta( $current_user_id, 'google_session_token', $google_token );

        return true;
    }

    /**
     * Gets first match for Google email or returns false.
     *
     * @param $email_address
     *
     * @return bool|string
     */
    public function query_google_email( $email_address ) {
        global $wpdb;
        $result = $wpdb->get_var( $wpdb->prepare( "
            SELECT user_id
            FROM $wpdb->usermeta
            WHERE meta_key = 'google_sso_email'
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
DT_Custom_Login_Google::instance();


function dt_custom_login_google_sign_in_button( $type = 'signin' ) {
    ?>
    <div class="button hollow google_elements" id="google_signinButton" style="width:100%;display:none;">
        <span style="float:left;">
            <img src="<?php echo esc_url( get_theme_file_uri( '/assets/images/g-logo.png' ) ) ?>" style="width:20px;" />
        </span>
        <?php esc_attr_e( 'Google', 'dt_custom_login' ) ?>
    </div>
    <div id="google_error"></div>

    <script>
        jQuery('#google_signinButton').click(function() {
            auth2.signIn().then(onSignIn);
        });

        function onSignIn(googleUser) {
            // Useful data for your client-side scripts:
            jQuery('#google_signinButton').attr('style', 'background-color: grey; width:100%;').append(' <img src="<?php echo dt_custom_login_spinner() ?>" width="15px" />');

            let data = {
                "token": googleUser.getAuthResponse().id_token
            };
            jQuery.ajax({
                type: "POST",
                data: JSON.stringify(data),
                contentType: "application/json; charset=utf-8",
                dataType: "json",
                url: '<?php echo esc_url( rest_url( '/dt_custom_login/v1/register_via_google' ) ) ?>',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ) ?>');
                },
            })
                .done(function (data) {
                    console.log(data)
                    window.location = "<?php echo esc_url( dt_custom_login_url('redirect') ) ?>"
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
        }
    </script>
    <?php
}






function dt_custom_login_google_link_account_button() {
    $label = __( 'Link with Google', 'dt_custom_login' );
    ?>
    <div class="button hollow google_elements" id="google_signinButton" style="width:100%; display:none;">
        <span style="float:left;">
            <img src="<?php echo esc_url( get_theme_file_uri( '/assets/images/g-logo.png' ) ) ?>" style="width:20px;" />
        </span>
        <?php esc_attr_e( 'Google', 'dt_custom_login' ) ?>
    </div>
    <div id="google_error"></div>
    <script>
        jQuery('#google_signinButton').click(function() {
            auth2.signIn().then(onSignIn);
        });

        function onSignIn(googleUser) {
            // Useful data for your client-side scripts:
            jQuery('#google_signinButton').attr('style', 'background-color: grey; width:100%;').append(' <img src="<?php echo dt_custom_login_spinner() ?>" width="15px" />');

            let data = {
                "token": googleUser.getAuthResponse().id_token
            };
            jQuery.ajax({
                type: "POST",
                data: JSON.stringify(data),
                contentType: "application/json; charset=utf-8",
                dataType: "json",
                url: '<?php echo esc_url( rest_url( '/dt_custom_login/v1/link_account_via_google' ) ) ?>',
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
                        jQuery('#google_error').html( '<?php esc_html_e( 'Oops. Something went wrong.', 'dt_custom_login' ); ?>' )
                    }
                    console.log("error")
                    console.log(err)
                })
        }
    </script>
    <?php
}

function dt_custom_login_unlink_google_account( $user_id ) {
    if ( isset( $_POST['unlink_google'] ) ) {
        if ( isset( $_POST['user_update_nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['user_update_nonce'] ), 'user_' . $user_id. '_update' ) ) {
            return new WP_Error( 'fail_nonce_verification', 'The form requires a valid nonce, in order to process.' );
        }

        delete_user_meta( $user_id, 'google_sso_email' );
        delete_user_meta( $user_id, 'google_session_token' );
        delete_user_meta( $user_id, 'google_sso_id' );
    }
    return 1;
}
add_action( 'dt_custom_login_update_profile', 'dt_custom_login_unlink_google_account' );



