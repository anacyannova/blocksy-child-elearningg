<?php get_header(); ?>

<?php while (have_posts()) : the_post();
    global $wpdb;

    $course_id = get_the_ID();
    $thumb     = get_the_post_thumbnail_url($course_id, 'large');
    $user_id   = get_current_user_id();

    // Lecciones ordenadas
    $lecciones = get_posts(array(
        'post_type'      => 'mass_leccion',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_key'       => 'mass_orden',
        'orderby'        => 'meta_value_num',
        'order'          => 'ASC',
        'meta_query'     => array(array(
            'key'   => 'mass_curso_id',
            'value' => $course_id,
        )),
    ));

    // Lecciones completadas por el usuario
    $completadas = array();
    if ($user_id) {
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT leccion_id FROM {$wpdb->prefix}mass_progreso
             WHERE user_id = %d AND completado = 1",
            $user_id
        ));
        $completadas = array_map('intval', $rows);
    }

    $total    = count($lecciones);
    $hechas   = count(array_intersect(array_column($lecciones, 'ID'), $completadas));
    $progreso = $total > 0 ? round(($hechas / $total) * 100) : 0;
?>

<div class="cs-wrap">

    <div class="cs-grid">

        <!-- COLUMNA IZQUIERDA: imagen + título -->
        <div class="cs-left">
            <?php if ($thumb): ?>
            <div class="cs-thumb">
                <img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>">
                <h2 class="cs-titulo"><?php the_title(); ?></h2>
            </div>
            <?php endif; ?>
        </div>

        <!-- COLUMNA DERECHA: descripción + lecciones -->
        <div class="cs-right">

            <div class="cs-descripcion">
                <h4>Descripción</h4>
                <?php the_content(); ?>
            </div>

            <div class="cs-contenido">
                <h4>Contenido del curso</h4>

                <?php if (empty($lecciones)): ?>
                <p class="cs-sin-lecciones">Este curso aún no tiene lecciones.</p>
                <?php else: ?>

                <div class="cs-accordion" id="csAccordion">
                    <?php foreach ($lecciones as $i => $lec):
                        $lec_id      = $lec->ID;
                        $completada  = in_array($lec_id, $completadas);
                        $orden       = intval(get_post_meta($lec_id, 'mass_orden', true)) ?: ($i + 1);
                        $descripcion = get_the_excerpt($lec_id);

                        $bloqueada = false;
                        if ($i > 0) {
                            $anterior_id = $lecciones[$i - 1]->ID;
                            if (!in_array($anterior_id, $completadas)) {
                                $bloqueada = true;
                            }
                        }

                        if ($completada)    { $offset = 0;     $color_class = 'fill-green'; $label_color = '#00D118'; }
                        elseif ($bloqueada) { $offset = 94.25; $color_class = 'fill-gray';  $label_color = '#ADB5BD'; }
                        else                { $offset = 94.25; $color_class = 'fill-blue';  $label_color = '#386AF1'; }

                        $acc_class = 'cs-acc-item';
                        if ($bloqueada) $acc_class .= ' bloqueada';
                        if (!$bloqueada && !$completada) $acc_class .= ' activa';
                    ?>
                    <div class="<?php echo $acc_class; ?>" data-index="<?php echo $i; ?>">

                        <button class="cs-acc-header <?php echo $bloqueada ? 'disabled' : ''; ?>"
                                <?php echo $bloqueada ? 'disabled' : 'onclick="csToggle(this)"'; ?>>

                            <span class="cs-acc-arrow">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                    <path d="M3 5l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>

                            <span class="cs-acc-titulo">
                                Lección <?php echo $orden; ?>: <?php echo esc_html($lec->post_title); ?>
                            </span>

                            <span class="cs-progress-circle">
                                <svg viewBox="0 0 36 36" width="38" height="38">
                                    <circle class="cs-track" cx="18" cy="18" r="15"/>
                                    <circle class="cs-fill <?php echo $color_class; ?>" cx="18" cy="18" r="15"
                                            stroke-dasharray="94.25"
                                            stroke-dashoffset="<?php echo $offset; ?>"/>
                                </svg>
                                <span class="cs-pct-label" style="color:<?php echo $label_color; ?>">
                                    <?php echo $completada ? '100%' : '0%'; ?>
                                </span>
                            </span>

                        </button>

                        <div class="cs-acc-body <?php echo (!$bloqueada && !$completada) ? 'open' : ''; ?>">
                            <?php if ($descripcion): ?>
                            <p class="cs-acc-desc"><?php echo esc_html($descripcion); ?></p>
                            <?php endif; ?>

                            <?php if (!$bloqueada): ?>
                            <div class="cs-acc-btn-wrap">
                                <a href="<?php echo get_permalink($lec_id); ?>" class="cs-btn-ingresar">
                                    Ingresar al curso
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>

                <?php endif; ?>
            </div>

        </div>
    </div>

</div>

<?php endwhile; ?>
<?php get_footer(); ?>