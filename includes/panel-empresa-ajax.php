<?php
if (!defined('ABSPATH')) exit;

/* ─── AGREGAR USUARIO ─── */
add_action('wp_ajax_pe_agregar_usuario', function() {
    check_ajax_referer('pe_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['mensaje' => 'No autorizado.']);

    $nombre   = sanitize_text_field($_POST['nombre']   ?? '');
    $rut      = sanitize_text_field($_POST['rut']      ?? '');
    $email    = sanitize_email($_POST['email']         ?? '');
    $login    = sanitize_user($_POST['login']          ?? '');
    $password = $_POST['password']                     ?? '';
    $cargo    = sanitize_text_field($_POST['cargo']    ?? '');
    $estado   = sanitize_text_field($_POST['estado']   ?? 'activo');

    if (!$nombre || !$email || !$login || !$password)
        wp_send_json_error(['mensaje' => 'Faltan campos obligatorios.']);

    if (email_exists($email))    wp_send_json_error(['mensaje' => 'El correo ya está registrado.']);
    if (username_exists($login)) wp_send_json_error(['mensaje' => 'El usuario ya existe.']);

    $current_user = wp_get_current_user();
    $empresa      = get_field('nombre_empresa', 'user_' . $current_user->ID);
    $sede         = get_field('sede',           'user_' . $current_user->ID);

    $user_id = wp_create_user($login, $password, $email);
    if (is_wp_error($user_id))
        wp_send_json_error(['mensaje' => $user_id->get_error_message()]);

    wp_update_user([
        'ID'           => $user_id,
        'display_name' => $nombre,
        'first_name'   => $nombre,
    ]);

    $user_obj = new WP_User($user_id);
    $user_obj->set_role('subscriber');

    update_field('rut',            $rut,     'user_' . $user_id);
    update_field('rol',            $cargo,   'user_' . $user_id);
    update_field('estado',         $estado,  'user_' . $user_id);
    update_field('nombre_empresa', $empresa, 'user_' . $user_id);
    update_field('sede',           $sede,    'user_' . $user_id);
    update_user_meta($user_id, 'nombre_empresa', $empresa);

    // Inscribir automáticamente según cargo
    mass_inscribir_por_cargo($user_id, $cargo);

    wp_send_json_success(['mensaje' => 'Trabajador agregado correctamente.']);
});

/* ─── MODIFICAR USUARIO ─── */
add_action('wp_ajax_pe_modificar_usuario', function() {
    check_ajax_referer('pe_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['mensaje' => 'No autorizado.']);

    $user_id  = intval($_POST['user_id'] ?? 0);
    $nombre   = sanitize_text_field($_POST['nombre']   ?? '');
    $rut      = sanitize_text_field($_POST['rut']      ?? '');
    $email    = sanitize_email($_POST['email']         ?? '');
    $password = $_POST['password']                     ?? '';
    $cargo_nuevo = sanitize_text_field($_POST['cargo'] ?? '');
    $estado   = sanitize_text_field($_POST['estado']   ?? 'activo');

    if (!$user_id) wp_send_json_error(['mensaje' => 'Usuario no válido.']);

    global $wpdb;

    // cargo anterior
    $cargo_anterior = get_field('rol', 'user_' . $user_id);

    $args = [
        'ID'           => $user_id,
        'display_name' => $nombre,
        'first_name'   => $nombre,
        'user_email'   => $email,
    ];
    if (!empty($password)) $args['user_pass'] = $password;

    $result = wp_update_user($args);
    if (is_wp_error($result))
        wp_send_json_error(['mensaje' => $result->get_error_message()]);

    update_field('rut',    $rut,         'user_' . $user_id);
    update_field('rol',    $cargo_nuevo, 'user_' . $user_id);
    update_field('estado', $estado,      'user_' . $user_id);

    // si cambio el cargo, gestionar cursos
    if ($cargo_anterior && $cargo_anterior !== $cargo_nuevo) {

        $config = get_option('mass_cargos_cursos', array());

        $cursos_anterior = isset($config[$cargo_anterior]) ? array_map('intval', $config[$cargo_anterior]) : [];
        $cursos_nuevo    = isset($config[$cargo_nuevo])    ? array_map('intval', $config[$cargo_nuevo])    : [];

        // desinscribir cursos del cargo anterior que no completo
        foreach ($cursos_anterior as $curso_id) {

            // saltar si el curso tambien pertenece al nuevo cargo
            if (in_array($curso_id, $cursos_nuevo)) continue;

            // verificar si completo todas las lecciones
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
                 WHERE pr.user_id = %d AND pr.completado = 1
                 AND pm.meta_key = 'mass_curso_id'
                 AND pm.meta_value = %d",
                $user_id, $curso_id
            ));

            $curso_completo = ($total > 0 && $completadas >= $total);

            // Si NO está completo, desinscribir y borrar progreso parcial
            if (!$curso_completo) {
                $wpdb->delete(
                    $wpdb->prefix . 'mass_inscripciones',
                    ['user_id' => $user_id, 'curso_id' => $curso_id]
                );
                // Borrar progreso parcial de lecciones de ese curso
                $wpdb->query($wpdb->prepare(
                    "DELETE pr FROM {$wpdb->prefix}mass_progreso pr
                     JOIN {$wpdb->postmeta} pm ON pr.leccion_id = pm.post_id
                     WHERE pr.user_id = %d
                     AND pm.meta_key = 'mass_curso_id'
                     AND pm.meta_value = %d",
                    $user_id, $curso_id
                ));
            }
        }

        // inscribir cursos del nuevo cargo
        mass_inscribir_por_cargo($user_id, $cargo_nuevo);
    }

    wp_send_json_success(['mensaje' => 'Trabajador actualizado correctamente.']);
});

/* ─── ELIMINAR USUARIO ─── */
add_action('wp_ajax_pe_eliminar_usuario', function() {
    check_ajax_referer('pe_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['mensaje' => 'No autorizado.']);

    $user_id = intval($_POST['user_id'] ?? 0);
    if (!$user_id) wp_send_json_error(['mensaje' => 'Usuario no válido.']);

    if (user_can($user_id, 'administrator'))
        wp_send_json_error(['mensaje' => 'No puedes eliminar un administrador.']);

    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($user_id);

    wp_send_json_success(['mensaje' => 'Trabajador eliminado.']);
});