<?php

/* ─────────────────────────────
   1. INCLUDES
───────────────────────────── */
$includes_path = get_stylesheet_directory() . '/includes/';

if (file_exists($includes_path . 'panel-empresa-ajax.php')) {
    require_once $includes_path . 'panel-empresa-ajax.php';
}
if (file_exists($includes_path . 'login-ajax.php')) {
    require_once $includes_path . 'login-ajax.php';
}
if (file_exists($includes_path . 'enqueue.php')) {
    require_once $includes_path . 'enqueue.php';
}
if (file_exists($includes_path . 'cursos-ajax.php')) {
    require_once $includes_path . 'cursos-ajax.php';
}
if (file_exists($includes_path . 'admin-cargos.php')) {
    require_once $includes_path . 'admin-cargos.php';
}
if (file_exists($includes_path . 'metaboxes.php')) {
    require_once $includes_path . 'metaboxes.php';
}

/* ─────────────────────────────
   2. ESTILOS BASE
───────────────────────────── */
function mass_child_base_styles() {
    wp_enqueue_style('blocksy-parent-style', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('blocksy-child-style',  get_stylesheet_uri(), array('blocksy-parent-style'), wp_get_theme()->get('Version'));
    wp_enqueue_style('inter-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap', array(), null);
}
add_action('wp_enqueue_scripts', 'mass_child_base_styles', 20);

/* ─────────────────────────────
   3. OCULTAR ADMIN BAR
───────────────────────────── */
add_filter('show_admin_bar', function($show) {
    return current_user_can('administrator') ? true : false;
});

/* ─────────────────────────────
   4. BLOQUEAR WP-ADMIN
───────────────────────────── */
add_action('admin_init', function() {
    if (wp_doing_ajax()) return;
    if (current_user_can('administrator')) return;

    $roles = (array) wp_get_current_user()->roles;

    if (in_array('subscriber', $roles)) {
        wp_redirect(home_url('/mi-perfil/')); exit;
    }
    if (in_array('gerente_empresa', $roles)) {
        wp_redirect(home_url('/panel-empresa/')); exit;
    }

    wp_redirect(home_url('/')); exit;
});

/* ─────────────────────────────
   5. REDIRECCIÓN FRONTEND
───────────────────────────── */
add_action('template_redirect', function() {
    if (!is_user_logged_in()) return;
    if (is_admin() || wp_doing_ajax()) return;

    $roles = (array) wp_get_current_user()->roles;

    if (in_array('administrator', $roles)) return;

    if (in_array('gerente_empresa', $roles)) {
        if (!is_page('panel-empresa')) {
            wp_redirect(home_url('/panel-empresa/')); exit;
        }
        return;
    }

    if (in_array('subscriber', $roles)) {
        $puede_ver = is_page('mi-perfil')
                  || is_singular('mass_curso')
                  || is_singular('mass_leccion');

        if (!$puede_ver) {
            wp_redirect(home_url('/mi-perfil/')); exit;
        }
    }
});

/* ─────────────────────────────
   6. REGISTRAR ROL
───────────────────────────── */
function mass_registrar_roles() {
    if (!get_role('gerente_empresa')) {
        add_role('gerente_empresa', 'Gerente de Empresa', array('read' => true));
    }
}
add_action('init', 'mass_registrar_roles');

/* ─────────────────────────────
   7. CUSTOM POST TYPES
───────────────────────────── */
function mass_registrar_cpt() {

    register_post_type('mass_curso', array(
        'labels' => array(
            'name'          => 'Cursos',
            'singular_name' => 'Curso',
            'add_new_item'  => 'Añadir curso',
            'edit_item'     => 'Editar curso',
        ),
        'public'       => true,
        'has_archive'  => true,
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-welcome-learn-more',
        'supports'     => array('title', 'editor', 'thumbnail'),
        'rewrite'      => array('slug' => 'cursos'),
    ));

    register_post_type('mass_leccion', array(
        'labels' => array(
            'name'          => 'Lecciones',
            'singular_name' => 'Lección',
            'add_new_item'  => 'Añadir lección',
            'edit_item'     => 'Editar lección',
        ),
        'public'       => true,
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-media-video',
        'supports'     => array('title', 'editor', 'page-attributes'),
        'rewrite'      => array('slug' => 'leccion'),
    ));
}
add_action('init', 'mass_registrar_cpt');

/* ─────────────────────────────
   8. CAMPOS META
───────────────────────────── */
function mass_registrar_meta() {
    register_post_meta('mass_curso', 'mass_video_url', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string',
    ));
    register_post_meta('mass_leccion', 'mass_video_url', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'string',
    ));
    register_post_meta('mass_leccion', 'mass_curso_id', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'integer',
    ));
    register_post_meta('mass_leccion', 'mass_orden', array(
        'show_in_rest' => true,
        'single'       => true,
        'type'         => 'integer',
    ));
}
add_action('init', 'mass_registrar_meta');

/* ─────────────────────────────
   9. CREAR TABLAS
───────────────────────────── */
function mass_crear_tablas() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $sql = "
    CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mass_inscripciones (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     BIGINT UNSIGNED NOT NULL,
        curso_id    BIGINT UNSIGNED NOT NULL,
        fecha       DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_inscripcion (user_id, curso_id)
    ) $charset;

    CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mass_progreso (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     BIGINT UNSIGNED NOT NULL,
        leccion_id  BIGINT UNSIGNED NOT NULL,
        completado  TINYINT(1) DEFAULT 0,
        fecha       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_progreso (user_id, leccion_id)
    ) $charset;

    CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mass_certificados (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     BIGINT UNSIGNED NOT NULL,
        curso_id    BIGINT UNSIGNED NOT NULL,
        fecha       DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_certificado (user_id, curso_id)
    ) $charset;
    ";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
add_action('after_switch_theme', 'mass_crear_tablas');

/* ─────────────────────────────
   10. AUTO-INSCRIBIR DESDE WP ADMIN
───────────────────────────── */

add_action('profile_update', function($user_id) {
    if (!is_admin()) return;
    $cargo = get_field('rol', 'user_' . $user_id);
    if ($cargo) {
        mass_inscribir_por_cargo($user_id, $cargo);
    }
});

add_action('user_register', function($user_id) {
    // Pequeño delay para que SCF guarde primero
    add_action('shutdown', function() use ($user_id) {
        $cargo = get_field('rol', 'user_' . $user_id);
        if ($cargo) {
            mass_inscribir_por_cargo($user_id, $cargo);
        }
    });
});