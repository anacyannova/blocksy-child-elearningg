<?php
if (!defined('ABSPATH')) exit;

/* ─────────────────────────────
   REGISTRAR MENÚ EN WP-ADMIN
───────────────────────────── */
add_action('admin_menu', function() {
    add_menu_page(
        'Configuración de Cargos',
        'Cargos y Cursos',
        'manage_options',
        'mass-cargos',
        'mass_render_cargos_page',
        'dashicons-groups',
        30
    );
});

/* ─────────────────────────────
   GUARDAR CONFIGURACIÓN
───────────────────────────── */
add_action('admin_post_mass_guardar_cargos', function() {
    if (!current_user_can('manage_options')) wp_die('No autorizado.');
    check_admin_referer('mass_cargos_nonce');

    $config = array();

    if (isset($_POST['cargo_cursos']) && is_array($_POST['cargo_cursos'])) {
        foreach ($_POST['cargo_cursos'] as $cargo => $cursos) {
            $cargo_limpio   = sanitize_text_field($cargo);
            $cursos_limpios = array_map('intval', $cursos);
            $config[$cargo_limpio] = array_filter($cursos_limpios);
        }
    }

    update_option('mass_cargos_cursos', $config);

    wp_redirect(admin_url('admin.php?page=mass-cargos&guardado=1'));
    exit;
});

/* ─────────────────────────────
   SINCRONIZAR INSCRIPCIONES
───────────────────────────── */
add_action('admin_post_mass_sincronizar_inscripciones', function() {
    if (!current_user_can('manage_options')) wp_die('No autorizado.');
    check_admin_referer('mass_sync_nonce');

    $config = get_option('mass_cargos_cursos', array());
    if (empty($config)) {
        wp_redirect(admin_url('admin.php?page=mass-cargos&sincronizado=0'));
        exit;
    }

    $usuarios = get_users(['role' => 'subscriber', 'number' => -1]);
    $nuevas   = 0;

    foreach ($usuarios as $user) {
        $cargo = get_field('rol', 'user_' . $user->ID);
        if (empty($cargo) || empty($config[$cargo])) continue;

        global $wpdb;
        foreach ($config[$cargo] as $curso_id) {
            $curso_id = intval($curso_id);
            if (!$curso_id) continue;

            $existe = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}mass_inscripciones
                 WHERE user_id = %d AND curso_id = %d",
                $user->ID, $curso_id
            ));

            if (!$existe) {
                $wpdb->insert($wpdb->prefix . 'mass_inscripciones', [
                    'user_id'  => $user->ID,
                    'curso_id' => $curso_id,
                    'fecha'    => current_time('mysql'),
                ]);
                $nuevas++;
            }
        }
    }

    wp_redirect(admin_url('admin.php?page=mass-cargos&sincronizado=' . $nuevas));
    exit;
});

/* ─────────────────────────────
   RENDERIZAR PÁGINA
───────────────────────────── */
function mass_render_cargos_page() {

    $cargos = array(
        'Bombero',
        'Bombero de bencina',
        'Supervisor',
        'Operador',
        'Técnico',
        'Administrativo',
    );

    $cursos = get_posts(array(
        'post_type'      => 'mass_curso',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));

    $config = get_option('mass_cargos_cursos', array());
    ?>
    <div class="wrap">
        <h1>Configuración de Cargos y Cursos</h1>
        <p style="color:#555;margin-bottom:24px;">
            Define qué cursos se asignan automáticamente a cada cargo cuando el gerente crea un trabajador.
        </p>

        <?php if (isset($_GET['guardado'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>Configuración guardada correctamente.</p>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['sincronizado'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>Sincronización completada. <?php echo intval($_GET['sincronizado']); ?> inscripciones nuevas agregadas.</p>
        </div>
        <?php endif; ?>

        <?php if (empty($cursos)): ?>
        <div class="notice notice-warning">
            <p>No hay cursos publicados aún. <a href="<?php echo admin_url('post-new.php?post_type=mass_curso'); ?>">Crear un curso</a></p>
        </div>
        <?php else: ?>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="mass_guardar_cargos">
            <?php wp_nonce_field('mass_cargos_nonce'); ?>

            <table class="widefat fixed striped" style="margin-top:16px;">
                <thead>
                    <tr>
                        <th style="width:220px;">Cargo</th>
                        <th>Cursos asignados automáticamente</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cargos as $cargo):
                    $cursos_del_cargo = isset($config[$cargo]) ? $config[$cargo] : array();
                ?>
                <tr>
                    <td style="font-weight:600;color:#00246F;vertical-align:top;padding-top:16px;">
                        <?php echo esc_html($cargo); ?>
                    </td>
                    <td>
                        <div style="display:flex;flex-wrap:wrap;gap:12px;padding:8px 0;">
                        <?php foreach ($cursos as $curso): ?>
                            <label style="display:flex;align-items:center;gap:6px;font-size:14px;cursor:pointer;">
                                <input type="checkbox"
                                       name="cargo_cursos[<?php echo esc_attr($cargo); ?>][]"
                                       value="<?php echo $curso->ID; ?>"
                                       <?php checked(in_array($curso->ID, $cursos_del_cargo)); ?>>
                                <?php echo esc_html($curso->post_title); ?>
                            </label>
                        <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top:20px;">
                <button type="submit" class="button button-primary button-large">
                    Guardar configuración
                </button>
            </p>
        </form>

        <hr style="margin:40px 0;">

        <h2>Sincronizar inscripciones</h2>
        <p style="color:#555;margin-bottom:16px;">
            Recorre los usuarios activos y asígnales los cursos correspondientes según su cargo actual, sin modificar el estado de los cursos ya completados.
        </p>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="mass_sincronizar_inscripciones">
            <?php wp_nonce_field('mass_sync_nonce'); ?>
            <button type="submit" class="button button-secondary button-large">
                Sincronizar ahora
            </button>
        </form>

        <?php endif; ?>
    </div>
    <?php
}