<?php

function dt_custom_login_vars() : array {
    $dt_custom_login = get_option('dt_custom_login' );
    if ( empty( $dt_custom_login ) ){
        update_option( 'dt_custom_login', [
            'login_url' => 'login',
            'redirect_url' => 'profile',
            'google_sso_key' => '',
            'google_captcha_client_key' => '',
            'google_captcha_server_secret_key' => '',
            'facebook_public_key' => '',
            'facebook_secret_key' => '',
        ], true );
    }
    return $dt_custom_login;
}
function dt_custom_login_url( string $name ) : string {
    $dt_custom_login = dt_custom_login_vars();

    $login_url = $dt_custom_login['login_url'] ?? '';
    $redirect_url = $dt_custom_login['redirect_url'] ?? '';

    switch( $name ) {
        case 'home':
            return trailingslashit( site_url() );
        case 'login':
            return trailingslashit( site_url() ) . $login_url;
        case 'redirect':
            return trailingslashit( site_url() ) . $redirect_url;
        case 'logout':
            return trailingslashit( site_url() ) . $login_url . '/?action=logout';
        case 'register':
            return trailingslashit( site_url() ) . $login_url . '/?action=register';
        case 'lostpassword':
            return trailingslashit( site_url() ) . $login_url . '/?action=lostpassword';
        case 'resetpass':
            return trailingslashit( site_url() ) . $login_url . '/?action=resetpass';
        case 'expiredkey':
            return trailingslashit( site_url() ) . $login_url . '/?action=lostpassword&error=expiredkey';
        case 'invalidkey':
            return trailingslashit( site_url() ) . $login_url . '/?action=lostpassword&error=invalidkey';
        default:
            return '';
    }
}
