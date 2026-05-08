<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── registrar AJAX ── */
add_action( 'wp_ajax_nopriv_lm_do_login', 'lm_do_login' );
add_action( 'wp_ajax_lm_do_login', 'lm_do_login' );

/* ── función principal ── */
function lm_do_login() {

    // si ya está logueado → redirigir
    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();

        wp_send_json_success([
            'redirect' => mass_redirect_por_rol( $user )
        ]);
    }

    // seguridad nonce
    if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], 'lm_login_nonce' ) ) {
        wp_send_json_error([
            'message' => 'Error de seguridad (nonce inválido).'
        ]);
    }

    // sanitizar
    $log = sanitize_text_field( $_POST['log'] ?? '' );
    $pwd = $_POST['pwd'] ?? '';

    // validar
    if ( empty( $log ) || empty( $pwd ) ) {
        wp_send_json_error([
            'message' => 'Completa todos los campos.'
        ]);
    }

    // intento de login
    $user = wp_signon([
        'user_login'    => $log,
        'user_password' => $pwd,
        'remember'      => false,
    ], false);

    // error login
    if ( is_wp_error( $user ) ) {
        wp_send_json_error([
            'message' => 'Usuario o contraseña incorrectos.'
        ]);
    }

    // login correcto
    wp_send_json_success([
        'redirect' => mass_redirect_por_rol( $user )
    ]);

    wp_die();
}

/* ── redirección por rol ── */
function mass_redirect_por_rol( $user ): string {

    $roles = (array) $user->roles;

    // Supervisor / Instructor / Admin → panel empresa
    if (
        in_array('administrator', $roles) ||
        in_array('supervisor-empresa', $roles) ||
        in_array('tutor_instructor', $roles)
    ) {
        return home_url('/panel-empresa/');
    }

    // Alumno → perfil
    if ( in_array('subscriber', $roles) ) {
        return home_url('/mi-perfil/');
    }

    // fallback
    return home_url('/');
}