<?php
if (!defined('ABSPATH')) exit;

/* ─────────────────────────────
   INSCRIBIR ALUMNO A CURSO
───────────────────────────── */
add_action('wp_ajax_mass_inscribir', function() {
    check_ajax_referer('mass_cursos_nonce', 'nonce');

    $curso_id = intval($_POST['curso_id'] ?? 0);
    $user_id  = intval($_POST['user_id']  ?? 0);

    if (!$curso_id || !$user_id)
        wp_send_json_error(['mensaje' => 'Datos incompletos.']);

    $roles = (array) wp_get_current_user()->roles;
    if (!current_user_can('administrator') && !in_array('gerente_empresa', $roles))
        wp_send_json_error(['mensaje' => 'No autorizado.']);

    global $wpdb;
    $tabla = $wpdb->prefix . 'mass_inscripciones';

    $existe = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $tabla WHERE user_id = %d AND curso_id = %d",
        $user_id, $curso_id
    ));

    if ($existe)
        wp_send_json_error(['mensaje' => 'El alumno ya está inscrito en este curso.']);

    $result = $wpdb->insert($tabla, array(
        'user_id'  => $user_id,
        'curso_id' => $curso_id,
        'fecha'    => current_time('mysql'),
    ));

    if ($result === false)
        wp_send_json_error(['mensaje' => 'Error al inscribir.']);

    wp_send_json_success(['mensaje' => 'Alumno inscrito correctamente.']);
});

/* ─────────────────────────────
   DESASIGNAR ALUMNO DE CURSO
───────────────────────────── */
add_action('wp_ajax_mass_desinscribir', function() {
    check_ajax_referer('mass_cursos_nonce', 'nonce');

    $curso_id = intval($_POST['curso_id'] ?? 0);
    $user_id  = intval($_POST['user_id']  ?? 0);

    if (!$curso_id || !$user_id)
        wp_send_json_error(['mensaje' => 'Datos incompletos.']);

    $roles = (array) wp_get_current_user()->roles;
    if (!current_user_can('administrator') && !in_array('gerente_empresa', $roles))
        wp_send_json_error(['mensaje' => 'No autorizado.']);

    global $wpdb;

    $wpdb->delete($wpdb->prefix . 'mass_inscripciones', array(
        'user_id'  => $user_id,
        'curso_id' => $curso_id,
    ));

    $wpdb->delete($wpdb->prefix . 'mass_progreso', array(
        'user_id' => $user_id,
    ));

    wp_send_json_success(['mensaje' => 'Alumno desasignado correctamente.']);
});

/* ─────────────────────────────
   MARCAR LECCIÓN COMO COMPLETADA
───────────────────────────── */
add_action('wp_ajax_mass_completar_leccion', function() {
    check_ajax_referer('mass_cursos_nonce', 'nonce');

    if (!is_user_logged_in())
        wp_send_json_error(['mensaje' => 'No autorizado.']);

    $leccion_id = intval($_POST['leccion_id'] ?? 0);
    $user_id    = get_current_user_id();

    if (!$leccion_id)
        wp_send_json_error(['mensaje' => 'Lección no válida.']);

    global $wpdb;

    $wpdb->replace($wpdb->prefix . 'mass_progreso', array(
        'user_id'    => $user_id,
        'leccion_id' => $leccion_id,
        'completado' => 1,
        'fecha'      => current_time('mysql'),
    ));

    $curso_id = intval(get_post_meta($leccion_id, 'mass_curso_id', true));
    $progreso = 0;

    if ($curso_id) {
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'mass_leccion'
             AND p.post_status = 'publish'
             AND pm.meta_key = 'mass_curso_id'
             AND pm.meta_value = %d",
            $curso_id
        ));

        $completadas = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mass_progreso pr
             JOIN {$wpdb->postmeta} pm ON pr.leccion_id = pm.post_id
             WHERE pr.user_id = %d
             AND pr.completado = 1
             AND pm.meta_key = 'mass_curso_id'
             AND pm.meta_value = %d",
            $user_id, $curso_id
        ));

        $progreso = $total > 0 ? round(($completadas / $total) * 100) : 0;

        if ($progreso >= 100) {
            $wpdb->replace($wpdb->prefix . 'mass_certificados', array(
                'user_id'  => $user_id,
                'curso_id' => $curso_id,
                'fecha'    => current_time('mysql'),
            ));
        }
    }

    wp_send_json_success(array(
        'mensaje'     => 'Lección completada.',
        'progreso'    => $progreso,
        'certificado' => $progreso >= 100,
    ));
});

/* ─────────────────────────────
   OBTENER CURSOS DISPONIBLES
───────────────────────────── */
add_action('wp_ajax_mass_get_cursos', function() {
    check_ajax_referer('mass_cursos_nonce', 'nonce');

    $user_id = intval($_POST['user_id'] ?? 0);

    $cursos = get_posts(array(
        'post_type'      => 'mass_curso',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));

    global $wpdb;
    $inscritos = array();

    if ($user_id) {
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT curso_id FROM {$wpdb->prefix}mass_inscripciones WHERE user_id = %d",
            $user_id
        ));
        $inscritos = array_map('intval', $rows);
    }

    $data = array();
    foreach ($cursos as $c) {
        $data[] = array(
            'id'       => $c->ID,
            'titulo'   => $c->post_title,
            'inscrito' => in_array($c->ID, $inscritos),
        );
    }

    wp_send_json_success($data);
});

/* ─────────────────────────────
   INSCRIBIR AUTOMÁTICAMENTE
   según cargo del trabajador
───────────────────────────── */
function mass_inscribir_por_cargo($user_id, $cargo) {
    if (empty($cargo)) return;

    $config = get_option('mass_cargos_cursos', array());

    if (empty($config[$cargo])) return;

    global $wpdb;
    $tabla = $wpdb->prefix . 'mass_inscripciones';

    foreach ($config[$cargo] as $curso_id) {
        $curso_id = intval($curso_id);
        if (!$curso_id) continue;

        $existe = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $tabla WHERE user_id = %d AND curso_id = %d",
            $user_id, $curso_id
        ));

        if (!$existe) {
            $wpdb->insert($tabla, array(
                'user_id'  => $user_id,
                'curso_id' => $curso_id,
                'fecha'    => current_time('mysql'),
            ));
        }
    }
}