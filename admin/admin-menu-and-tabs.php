<?php
if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

/**
 * Class Disciple_Tools_Custom_Login_Menu
 */
class Disciple_Tools_Custom_Login_Menu {

    public $token = 'disciple_tools_custom_login';

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        add_action( "admin_menu", array( $this, "register_menu" ) );
    }

    public function register_menu() {
        add_submenu_page( 'dt_extensions', 'Custom Login', 'Custom Login', 'manage_dt', $this->token, [ $this, 'content' ] );
    }

    /**
     * Menu stub. Replaced when Disciple Tools Theme fully loads.
     */
    public function extensions_menu() {}

    /**
     * Builds page contents
     * @since 0.1
     */
    public function content() {

        if ( !current_user_can( 'manage_dt' ) ) { // manage dt is a permission that is specific to Disciple Tools and allows admins, strategists and dispatchers into the wp-admin
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }

        if ( isset( $_GET["tab"] ) ) {
            $tab = sanitize_key( wp_unslash( $_GET["tab"] ) );
        } else {
            $tab = 'general';
        }

        $link = 'admin.php?page='.$this->token.'&tab=';

        ?>
        <div class="wrap">
            <h2>Custom Login</h2>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_attr( $link ) . 'general' ?>"
                   class="nav-tab <?php echo esc_html( ( $tab == 'general' || !isset( $tab ) ) ? 'nav-tab-active' : '' ); ?>">General</a>
                <a href="<?php echo esc_attr( $link ) . 'help' ?>"
                   class="nav-tab <?php echo esc_html( ( $tab == 'help' ) ? 'nav-tab-active' : '' ); ?>">Help</a>
            </h2>

            <?php
            switch ($tab) {
                case "general":
                    $object = new Disciple_Tools_Custom_Login_Tab_General();
                    $object->content();
                    break;
                case "help":
                    $object = new Disciple_Tools_Custom_Login_Tab_Help();
                    $object->content();
                    break;
                default:
                    break;
            }
            ?>

        </div><!-- End wrap -->

        <?php
    }
}
Disciple_Tools_Custom_Login_Menu::instance();

/**
 * Class Disciple_Tools_Plugin_Starter_Template_Tab_General
 */
class Disciple_Tools_Custom_Login_Tab_General {
    public function content() {
        ?>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder">
                    <div id="post-body-content">
                        <!-- Main Column -->

                        <?php $this->login_configurations() ?>

                        <!-- End Main Column -->
                    </div><!-- end post-body-content -->
                </div><!-- post-body meta box container -->
            </div><!--poststuff end -->
        </div><!-- wrap end -->
        <?php
    }

    public function login_configurations() {
        $dt_custom_login = $this->process_login_configurations();
        ?>
        <!-- Box -->
        <form method="post">
            <?php wp_nonce_field('login'.get_current_user_id(), 'login_nonce' ) ?>
            <table class="widefat striped">
                <thead>
                <tr>
                    <th style="width:50px;"></th>
                    <th>Login Configurations</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td colspan="2">
                        Required fields.
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
                        <strong>Redirect URL</strong> <br>(when someone successfully logs in, where do they get redirected)<br>
                        <strong><?php echo esc_url( site_url('/')) ?></strong><input class="regular-text" name="redirect_url" placeholder="Redirect Page" value="<?php echo $dt_custom_login['redirect_url'] ?>"/> <br>
                    </td>
                </tr>
                <tr>
                    <td style="font-size:1.2em; text-align: center;">
                        <?php
                        if ( empty( $dt_custom_login['google_captcha_client_key'] ) ) {
                            echo '&#10060;';
                        } else {
                            echo '&#9989;';
                        }
                        ?>
                    </td>
                    <td>
                        <strong>Google Captcha Key</strong><br>
                        <input class="regular-text" name="google_captcha_client_key" placeholder="Google Captcha Client Key" value="<?php echo $dt_custom_login['google_captcha_client_key'] ?>"/><br>
                        <input class="regular-text" name="google_captcha_server_secret_key" placeholder="Google Captcha Server Secret Key" value="<?php echo $dt_custom_login['google_captcha_server_secret_key'] ?>"/><br>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        Optional features.
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
                <tr>
                    <td>
                        <button class="button" type="submit">Save</button>
                    </td>
                </tr>
                </tbody>
            </table>
        </form>
        <br>
        <!-- End Box -->
        <?php
    }

    public function process_login_configurations(){
        $dt_custom_login = dt_custom_login_vars();
        // process POST
        if ( isset( $_POST[ 'login_nonce' ] )
            && wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ 'login_nonce' ] ) ), 'login' . get_current_user_id() ) )  {

            foreach( $dt_custom_login as $index => $value ) {
                if ( ! isset( $dt_custom_login[$index] ) ) {
                    $dt_custom_login[$index] = $value;
                }
                if ( isset( $_POST[$index] ) ) {
                    if ( empty( $_POST[$index] ) ) {
                        $dt_custom_login[$index] = '';
                    }
                    else {
                        $dt_custom_login[$index] = trim( sanitize_text_field( wp_unslash( $_POST[$index] ) ) );
                    }
                }
            }

            update_option( 'dt_custom_login', $dt_custom_login, true );
        }

        return $dt_custom_login;

    }

}



/**
 * Class Disciple_Tools_Plugin_Starter_Template_Tab_General
 */
class Disciple_Tools_Custom_Login_Tab_Help {
    public function content() {
        ?>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <!-- Main Column -->

                        <?php $this->captcha() ?>

                        <?php $this->google_sso() ?>

                        <?php $this->facebook_sso() ?>

                        <!-- End Main Column -->
                    </div><!-- end post-body-content -->
                    <div id="postbox-container-1" class="postbox-container">
                        <!-- Right Column -->

                        <?php $this->right_column() ?>

                        <!-- End Right Column -->
                    </div><!-- postbox-container 1 -->
                    <div id="postbox-container-2" class="postbox-container">
                    </div><!-- postbox-container 2 -->
                </div><!-- post-body meta box container -->
            </div><!--poststuff end -->
        </div><!-- wrap end -->
        <?php
    }

    public function captcha() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Captcha Setup</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <a href="https://morweb.org/support-post/set-up-google-recaptcha">https://morweb.org/support-post/set-up-google-recaptcha</a><br>

                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function google_sso() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Google SSO Setup</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    Content
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function facebook_sso() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Facebook SSO Setup</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <p>Reference:<br>
                    <a href="https://developers.facebook.com/docs/facebook-login/web">https://developers.facebook.com/docs/facebook-login/web</a><br></p>

                    <p>Things you'll need:</p>
                    <ul>
                        <li>A Facebook Developer Account</li>
                        <li>A registered Facebook App with Basic Settings configured</li>
                    </ul>

                <p>
                    <a href="https://developers.facebook.com/apps">https://developers.facebook.com/apps</a>
                </p>

                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function right_column() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Information</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    Content
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }


}
