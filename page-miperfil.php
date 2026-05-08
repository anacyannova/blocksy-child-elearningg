<?php
/* Template Name: Mi perfil */

get_header();

if (!is_user_logged_in()) {
    wp_redirect(home_url('/login'));
    exit;
}

global $wpdb;

$user     = wp_get_current_user();
$user_id  = $user->ID;
$nombre   = $user->display_name;

$partes    = explode(' ', trim($nombre));
$iniciales = '';
if (isset($partes[0])) $iniciales .= strtoupper(substr($partes[0], 0, 1));
if (isset($partes[1])) $iniciales .= strtoupper(substr($partes[1], 0, 1));

$empresa = get_field('nombre_empresa', 'user_' . $user_id) ?: '';
$sede    = get_field('sede',           'user_' . $user_id) ?: '';
$estado  = get_field('estado',         'user_' . $user_id) ?: 'activo';

// Cursos inscritos desde nuestra propia tabla
$cursos_ids = $wpdb->get_col($wpdb->prepare(
    "SELECT curso_id FROM {$wpdb->prefix}mass_inscripciones WHERE user_id = %d",
    $user_id
));

$cursos = array();
foreach ($cursos_ids as $cid) {
    $post = get_post(intval($cid));
    if ($post && $post->post_status === 'publish') {
        $cursos[] = $post;
    }
}
?>

<div class="mp-wrap">

    <div class="mp-header">
        <div class="mp-perfil">
            <div class="mp-avatar"><?php echo esc_html($iniciales); ?></div>
            <div class="mp-info">
                <span class="mp-rol">Trabajador</span>
                <h1 class="mp-nombre"><?php echo esc_html($nombre); ?></h1>
                <div class="mp-empresa-sede">
                    <?php if ($empresa): ?>
                        <span class="mp-empresa"><?php echo esc_html($empresa); ?></span>
                    <?php endif; ?>
                    <?php if ($sede): ?>
                        <span class="mp-sep">—</span>
                        <span class="mp-sede"><?php echo esc_html($sede); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="mp-estado-badge mp-estado-<?php echo esc_attr($estado); ?>">
            <span class="mp-estado-dot"></span>
            <?php echo $estado === 'activo' ? 'Activo' : 'Inactivo'; ?>
        </div>
    </div>

    <div class="mp-cursos">
        <h2 class="mp-titulo-seccion">Cursos activos</h2>

        <?php if (!empty($cursos)) : ?>
        <div class="mp-grid">
            <?php foreach ($cursos as $post) :
                $course_id = $post->ID;
                $thumb     = get_the_post_thumbnail_url($course_id, 'medium');
                $excerpt   = get_the_excerpt($course_id);
                $link      = get_permalink($course_id);

                // Progreso desde nuestra tabla
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
                    $user_id, $course_id
                ));

                $progress = $total_lecciones > 0 ? round(($completadas / $total_lecciones) * 100) : 0;

                if ($progress >= 70)     $color = '#00D118';
                elseif ($progress >= 30) $color = '#FFA500';
                else                     $color = '#386AF1';
            ?>
            <div class="mp-card">
                <?php if ($thumb): ?>
                <div class="mp-card-img" style="background-image:url('<?php echo esc_url($thumb); ?>')"></div>
                <?php endif; ?>
                <div class="mp-card-body">
                    <div class="mp-card-top">
                        <h3 class="mp-card-titulo"><?php echo esc_html(get_the_title($course_id)); ?></h3>
                        <div class="mp-progress-circle" style="--p:<?php echo $progress; ?>;--c:<?php echo $color; ?>">
                            <span><?php echo $progress; ?>%</span>
                        </div>
                    </div>
                    <?php if ($excerpt): ?>
                    <p class="mp-card-desc"><?php echo esc_html($excerpt); ?></p>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($link); ?>" class="mp-btn-curso">Ingresar al curso</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <p class="mp-sin-cursos">No tienes cursos inscritos.</p>
        <?php endif; ?>
    </div>

</div>

<?php get_footer(); ?>