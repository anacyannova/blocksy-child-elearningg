<?php
/**
 * Template Name: Login
 */

if ( is_user_logged_in() ) {
    $user  = wp_get_current_user();
    $roles = $user->roles;

    if ( in_array( 'administrator', $roles ) ) {
        // puede quedarse
    } elseif ( in_array( 'gerente_empresa', $roles ) ) {
        wp_redirect( home_url( '/panel-empresa/' ) );
        exit;
    } elseif ( in_array( 'subscriber', $roles ) ) {
        wp_redirect( home_url( '/mi-perfil/' ) );
        exit;
    }
}

$img_url = '';

$attachments = get_posts([
    'post_type'      => 'attachment',
    'posts_per_page' => 1,
    'meta_query'     => [
        [
            'key'     => '_wp_attached_file',
            'value'   => 'inicio.jpg',
            'compare' => 'LIKE'
        ]
    ]
]);

if ( ! empty($attachments) ) {
    $img_url = wp_get_attachment_url($attachments[0]->ID);
}

if ( empty($img_url) ) {
    $img_url = get_stylesheet_directory_uri() . '/assets/img/inicio.jpg';
}

get_header();
?>

<div class="lm-wrap">

    <div class="lm-hero" style="background-image: url('<?php echo esc_url($img_url); ?>');"></div>

    <div class="lm-card-wrap">
        <div class="lm-card">

            <h2 class="lm-titulo">Bienvenido a capacitaciones MASS</h2>

            <div class="lm-field">
                <label class="lm-label" for="lm_usuario">Ingresa tu nombre de usuario</label>
                <input type="text" id="lm_usuario" class="lm-input">
            </div>

            <div class="lm-field">
                <label class="lm-label" for="lm_pass">Contraseña</label>
                <div class="lm-input-wrap">
                    <input type="password" id="lm_pass" class="lm-input">
                    <button type="button" class="lm-eye" onclick="lmTogglePass()" tabindex="-1">
                        <svg id="lm-eye-icon" style="pointer-events:none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div id="lm-error" class="lm-error" style="display:none;"></div>

            <div class="lm-btn-wrap">
                <button class="lm-btn" id="lm-btn" onclick="lmLogin()">
                    <span id="lm-btn-text">Ingresar</span>
                    <span id="lm-btn-loader" style="display:none;">Cargando...</span>
                </button>
            </div>

        </div>
    </div>

</div>

<?php get_footer(); ?>