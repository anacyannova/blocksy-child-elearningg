<?php
if (!defined('ABSPATH')) exit;

function capacitaciones_assets() {

    $css = get_stylesheet_directory_uri() . '/assets/css/';
    $js  = get_stylesheet_directory_uri() . '/assets/js/';
    $ver = wp_get_theme()->get('Version');

    /* ── Siempre: base + layout ── */
    wp_enqueue_style('mass-base',   $css . 'base.css',   ['blocksy-child-style'], $ver);
    wp_enqueue_style('mass-layout', $css . 'layout.css', ['mass-base'],           $ver);

    /* ── Panel empresa ── */
    if (is_page('panel-empresa')) {
        wp_enqueue_style('mass-panel', $css . 'panel-empresa.css', ['mass-layout'], $ver);

        wp_enqueue_script('panel-empresa-js', $js . 'panel-empresa.js', [], $ver, true);
        wp_localize_script('panel-empresa-js', 'pe_ajax', [
            'url'   => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pe_nonce'),
        ]);
    }

    /* ── Login ── */
    if (is_page_template('page-login.php') || is_page('login')) {
        wp_enqueue_style('mass-login', $css . 'login.css', ['mass-layout'], $ver);

        wp_enqueue_script('mass-login-js', $js . 'login.js', [], $ver, true);
        wp_localize_script('mass-login-js', 'LM', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('lm_login_nonce'),
        ]);
    }

    /* ── Mi perfil ── */
    $template_actual = get_page_template_slug();
    if (is_page('mi-perfil') || $template_actual === 'page-miperfil.php') {
        wp_enqueue_style('mass-perfil', $css . 'mi-perfil.css', ['mass-layout'], $ver);
    }

    /* ── Cursos (single) ── */
    if (is_singular('mass_curso') || is_singular('mass_leccion')) {
        wp_enqueue_style('mass-curso', $css . 'curso.css', ['mass-layout'], $ver);
        wp_enqueue_script('curso-js',  $js  . 'curso.js', [], $ver, true);
        wp_localize_script('curso-js', 'MASS', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('mass_cursos_nonce'),
        ]);
    }

}
add_action('wp_enqueue_scripts', 'capacitaciones_assets', 25);