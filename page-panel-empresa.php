<?php
/* Template Name: Panel Empresa */

get_header();

if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}

global $wpdb;

$current_user = wp_get_current_user();
$nombre       = $current_user->display_name;
$rol_display  = in_array('administrator', $current_user->roles) ? 'Administrador' : 'Gerente de Empresa';

$partes    = explode(' ', trim($nombre));
$iniciales = '';
if (isset($partes[0])) $iniciales .= strtoupper(substr($partes[0], 0, 1));
if (isset($partes[1])) $iniciales .= strtoupper(substr($partes[1], 0, 1));

$empresa = get_field('nombre_empresa', 'user_' . $current_user->ID);
$sede    = get_field('sede', 'user_' . $current_user->ID);

$usuarios = get_users([
    'role'       => 'subscriber',
    'meta_key'   => 'nombre_empresa',
    'meta_value' => $empresa,
    'number'     => -1,
]);

$total = $activos = $certificados = 0;

foreach ($usuarios as $t) {
    $total++;
    $est = strtolower(trim(get_field('estado', 'user_' . $t->ID)));
    if ($est === 'activo') $activos++;
    if (!empty(get_user_meta($t->ID, 'certificado_aprobado', true))) $certificados++;
}
?>

<main class="container">

<div class="pe-wrap">

    <!-- HEADER -->
    <div class="pe-header">

        <div class="pe-perfil">
            <div class="pe-avatar"><?php echo esc_html($iniciales); ?></div>
            <div class="pe-perfil-info">
                <span class="pe-rol"><?php echo esc_html($rol_display); ?></span>
                <p class="pe-nombre"><?php echo esc_html($nombre); ?></p>
                <?php if ($empresa): ?>
                <p class="pe-empresa"><?php echo esc_html($empresa); ?></p>
                <?php endif; ?>
                <?php if ($sede): ?>
                <p class="pe-sede"><?php echo esc_html($sede); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="pe-metricas">
            <div class="pe-metrica-card">
                <span class="pe-metrica-num"><?php echo $total; ?></span>
                <span class="pe-metrica-label">Total</span>
            </div>
            <div class="pe-metrica-card">
                <span class="pe-metrica-num"><?php echo $activos; ?></span>
                <span class="pe-metrica-label">Activos</span>
            </div>
            <div class="pe-metrica-card">
                <span class="pe-metrica-num"><?php echo $certificados; ?></span>
                <span class="pe-metrica-label">Certificados</span>
            </div>
        </div>

    </div>

    <!-- LISTADO -->
    <div class="pe-lista">

        <h2 class="pe-titulo">Listado de trabajadores</h2>

        <div class="pe-top">
            <div class="pe-filtros">
                <button class="pe-filtro-btn activo" onclick="peF('todos',this)">Todos</button>
                <button class="pe-filtro-btn" onclick="peF('activo',this)">Activos</button>
                <button class="pe-filtro-btn" onclick="peF('inactivo',this)">Fuera de servicio</button>
                <button class="pe-filtro-btn" onclick="peAZ()">A - Z</button>
            </div>
            <button class="pe-btn-agregar" onclick="peAgregar()">Registro de colaborador</button>
        </div>

        <?php if (empty($usuarios)): ?>
        <p style="color:#00246F;">No hay trabajadores registrados.</p>
        <?php else: ?>

        <table class="pe-table" id="peTabla">
            <thead>
                <tr>
                    <th>Trabajador</th>
                    <th>Cargo</th>
                    <th>Estado</th>
                    <th>Progreso</th>
                    <th>Certificación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $user):

                $estado = strtolower(trim(get_field('estado', 'user_' . $user->ID)));
                if (empty($estado)) $estado = 'activo';

                $cargo = get_field('rol', 'user_' . $user->ID);
                $rut   = get_field('rut', 'user_' . $user->ID);

                // Progreso desde tablas propias
                $progress  = 0;
                $cert_html = '<span class="pe-sin-cert">Sin certificado</span>';

                $cursos_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT curso_id FROM {$wpdb->prefix}mass_inscripciones WHERE user_id = %d",
                    $user->ID
                ));

                if (!empty($cursos_ids)) {
                    $course_id = intval($cursos_ids[0]);

                    $total_lecciones = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->posts} p
                         JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                         WHERE p.post_type = 'mass_leccion'
                         AND p.post_status = 'publish'
                         AND pm.meta_key = 'mass_curso_id'
                         AND pm.meta_value = %d",
                        $course_id
                    ));

                    $completadas = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}mass_progreso pr
                         JOIN {$wpdb->postmeta} pm ON pr.leccion_id = pm.post_id
                         WHERE pr.user_id = %d
                         AND pr.completado = 1
                         AND pm.meta_key = 'mass_curso_id'
                         AND pm.meta_value = %d",
                        $user->ID, $course_id
                    ));

                    $progress = $total_lecciones > 0 ? round(($completadas / $total_lecciones) * 100) : 0;

                    $cert_url = get_user_meta($user->ID, 'certificado_url', true);
                    if ($progress >= 100 && !empty($cert_url)) {
                        $cert_html = '<a class="pe-cert-link" href="' . esc_url($cert_url) . '" target="_blank">Descargar</a>';
                    }
                }

                if ($progress >= 70)     $bar_color = '#00D118';
                elseif ($progress >= 30) $bar_color = '#FFA500';
                else                     $bar_color = '#FF3B3B';

                $badge_class = ($estado === 'activo') ? 'pe-badge-activo' : 'pe-badge-inactivo';
                $badge_label = ($estado === 'activo') ? 'Activo' : 'Fuera de servicio';
            ?>
            <tr data-estado="<?php echo esc_attr($estado); ?>"
                data-nombre="<?php echo esc_attr($user->display_name); ?>"
                data-id="<?php echo $user->ID; ?>"
                data-email="<?php echo esc_attr($user->user_email); ?>"
                data-login="<?php echo esc_attr($user->user_login); ?>"
                data-rut="<?php echo esc_attr($rut); ?>"
                data-cargo="<?php echo esc_attr($cargo); ?>"
                data-estado-val="<?php echo esc_attr($estado); ?>">

                <td>
                    <span class="pe-nombre-trabajador"><?php echo esc_html($user->display_name); ?></span>
                    <?php if ($rut): ?>
                    <span class="pe-rut"><?php echo esc_html($rut); ?></span>
                    <?php endif; ?>
                </td>

                <td><span class="pe-cargo"><?php echo esc_html($cargo); ?></span></td>

                <td>
                    <span class="pe-badge <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span>
                </td>

                <td>
                    <div class="pe-progress-wrap">
                        <div class="pe-progress-bar">
                            <div class="pe-progress-fill"
                                 style="width:<?php echo $progress; ?>%;background:<?php echo $bar_color; ?>;"></div>
                        </div>
                        <span class="pe-progress-pct"><?php echo $progress; ?>%</span>
                    </div>
                </td>

                <td><?php echo $cert_html; ?></td>

                <td class="pe-acciones">
                    <a href="#" class="pe-modificar"
                       onclick="peMod(<?php echo $user->ID; ?>);return false;">Modificar</a>
                    <a href="#" class="pe-eliminar"
                       onclick="peDel(<?php echo $user->ID; ?>,'<?php echo esc_js($user->display_name); ?>');return false;">Eliminar</a>
                </td>

            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>

    </div>

</div>

<!-- MODAL -->
<div id="peModal" style="display:none;" class="pe-modal-overlay">
    <div class="pe-modal-box" onclick="event.stopPropagation()">

        <div class="pe-modal-header">
            <h3 id="peModalTitulo" class="pe-modal-titulo"></h3>
            <button class="pe-modal-cerrar" onclick="peModalClose()">&#x2715;</button>
        </div>

        <div class="pe-modal-body">
            <div class="pe-form-grid">

                <div class="pe-form-group">
                    <label class="pe-label">Nombre completo</label>
                    <input type="text" id="pe_nombre" class="pe-input" placeholder="Ej: Juan Pérez">
                </div>

                <div class="pe-form-group">
                    <label class="pe-label">RUT</label>
                    <input type="text" id="pe_rut" class="pe-input" placeholder="Ej: 12.345.678-9">
                </div>

                <div class="pe-form-group">
                    <label class="pe-label">Correo electrónico</label>
                    <input type="email" id="pe_email" class="pe-input" placeholder="correo@ejemplo.com">
                </div>

                <div class="pe-form-group">
                    <label class="pe-label">Nombre de usuario</label>
                    <input type="text" id="pe_login" class="pe-input" placeholder="usuario123">
                </div>

                <div class="pe-form-group">
                    <label class="pe-label">Contraseña <span id="pe_pass_hint" style="font-size:12px;color:#7A8EAE;">(dejar vacío para no cambiar)</span></label>
                    <div class="pe-input-wrap">
                        <input type="password" id="pe_password" class="pe-input" placeholder="••••••••">
                        <button type="button" class="pe-eye" onclick="peTogglePass()" tabindex="-1">
                            <svg id="pe-eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="pe-form-group">
                    <label class="pe-label">Cargo</label>
                    <select id="pe_cargo" class="pe-select">
                        <option value="">— Seleccionar —</option>
                        <option value="Bombero">Bombero</option>
                        <option value="Bombero de bencina">Bombero de bencina</option>
                        <option value="Supervisor">Supervisor</option>
                        <option value="Operador">Operador</option>
                        <option value="Técnico">Técnico</option>
                        <option value="Administrativo">Administrativo</option>
                    </select>
                </div>

                <div class="pe-form-group">
                    <label class="pe-label">Estado</label>
                    <select id="pe_estado" class="pe-select">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Fuera de servicio</option>
                    </select>
                </div>

            </div>

            <div id="pe_msg" class="pe-msg" style="display:none;"></div>
        </div>

        <div class="pe-modal-footer">
            <button class="pe-btn-cancelar" onclick="peModalClose()">Cancelar</button>
            <button class="pe-btn-guardar" id="peBtnGuardar" onclick="peGuardar()">Guardar</button>
        </div>

    </div>
</div>

</main>

<?php get_footer(); ?>