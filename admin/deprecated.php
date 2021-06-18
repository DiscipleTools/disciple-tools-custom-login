<?php
function dt_custom_login_signup_header() {
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
}


// modifies the buttons of the login form.
function dt_custom_login_login_styles() {
    ?>
    <style>
        body.login {
            background: none;
        }
        #wp-submit {
            background: #fefefe;
            border: 0;
            color: #323A68;
            font-size: medium;
            cursor: pointer;
            outline: #323A68 solid 1px;
            padding: 0.85em 1em;
            text-align: center;
            text-decoration: none;
            -webkit-border-radius: 0;
            -moz-border-radius: 0;
            border-radius: 0;
            margin: 2px;
            height: inherit;
            text-shadow: none;
            float:right;
        }
        #wp-submit:hover {
            background: #323A68;
            border: 0;
            color: #fefefe;
            font-size: medium;
            cursor: pointer;
            outline: #323A68 solid 1px;
            padding: 0.85em 1em;
            text-align: center;
            text-decoration: none;
            -webkit-border-radius: 0;
            -moz-border-radius: 0;
            border-radius: 0;
            margin: 2px;
            height:inherit;
            float:right;
        }
        .login h1 a {
            background: url(<?php echo esc_url( get_theme_file_uri( '/assets/images/dt_custom_login-logo-white.png' ) ) ?>) no-repeat top center;
            width: 326px;
            height: 67px;
            text-indent: -9999px;
            overflow: hidden;
            padding-bottom: 15px;
            display: block;
        }
        #nav a {
            background: #fefefe;
            border: 0;
            color: #323A68;
            font-size: medium;
            cursor: pointer;
            outline: #323A68 solid 1px;
            padding: 1em;
            text-align: center;
            text-decoration: none;
            -webkit-border-radius: 0;
            -moz-border-radius: 0;
            border-radius: 0;
            margin: 2px;
        }
        #nav a:hover {
            background: #323A68;
            border: 0;
            color: #fefefe;
            font-size: medium;
            cursor: pointer;
            outline: #323A68 solid 1px;
            padding: 5px;
            text-align: center;
            text-decoration: none;
            -webkit-border-radius: 0;
            -moz-border-radius: 0;
            border-radius: 0;
            margin: 2px;
        }
        @media only screen and (min-width: 640px) {
            #nav a {
                padding: 1em !important;
            }
            #nav a:hover {
                padding: 1em !important;
            }
        }
    </style>
    <?php
}
