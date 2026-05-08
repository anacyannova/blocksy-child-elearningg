<?php get_header(); ?>

<?php while (have_posts()) : the_post();
    global $wpdb;

    $leccion_id  = get_the_ID();
    $video_url   = get_post_meta($leccion_id, 'mass_video_url', true);
    $curso_id    = intval(get_post_meta($leccion_id, 'mass_curso_id', true));
    $duracion    = get_post_meta($leccion_id, 'mass_duracion', true);
    $user_id     = get_current_user_id();
    $preguntas   = get_post_meta($leccion_id, 'mass_quiz', true);
    if (!is_array($preguntas)) $preguntas = [];

    $embed_url = '';
    if ($video_url) {
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $video_url, $m))
            $embed_url = 'https://www.youtube.com/embed/' . $m[1];
        elseif (preg_match('/vimeo\.com\/(\d+)/', $video_url, $m))
            $embed_url = 'https://player.vimeo.com/video/' . $m[1];
    }

    // Progreso del curso
    $progreso_pct = 0;
    $curso_titulo = '';
    if ($curso_id) {
        $curso_titulo = get_the_title($curso_id);
        $total_lecs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'mass_leccion' AND p.post_status = 'publish'
             AND pm.meta_key = 'mass_curso_id' AND pm.meta_value = %d",
            $curso_id
        ));
        $completadas_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}mass_progreso pr
             JOIN {$wpdb->postmeta} pm ON pr.leccion_id = pm.post_id
             WHERE pr.user_id = %d AND pr.completado = 1
             AND pm.meta_key = 'mass_curso_id' AND pm.meta_value = %d",
            $user_id, $curso_id
        ));
        $progreso_pct = $total_lecs > 0 ? round(($completadas_count / $total_lecs) * 100) : 0;
    }

    // ¿Ya completó esta lección?
    $ya_completa = (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT completado FROM {$wpdb->prefix}mass_progreso
         WHERE user_id = %d AND leccion_id = %d AND completado = 1",
        $user_id, $leccion_id
    ));

    $total_preguntas   = count($preguntas);
    $offset_svg        = $progreso_pct > 0 ? 94.25 - (94.25 * $progreso_pct / 100) : 94.25;
    $color_progreso    = $progreso_pct >= 100 ? '#00D118' : '#386AF1';
?>

<div class="sl-wrap">

    <!-- TARJETA SUPERIOR -->
    <div class="sl-card-top">

        <div class="sl-card-header">
            <h2 class="sl-curso-titulo"><?php echo esc_html($curso_titulo); ?></h2>
            <span class="sl-progress-circle">
                <svg viewBox="0 0 36 36" width="48" height="48">
                    <circle class="sl-track" cx="18" cy="18" r="15"/>
                    <circle class="sl-fill" cx="18" cy="18" r="15"
                            stroke="<?php echo $color_progreso; ?>"
                            stroke-dasharray="94.25"
                            stroke-dashoffset="<?php echo $offset_svg; ?>"/>
                </svg>
                <span class="sl-pct-label" style="color:<?php echo $color_progreso; ?>">
                    <?php echo $progreso_pct; ?>%
                </span>
            </span>
        </div>

        <div class="sl-card-row">
            <span class="sl-leccion-nombre"><?php the_title(); ?></span>
        </div>

        <?php if ($duracion): ?>
        <div class="sl-card-row">
            <span class="sl-duracion">Duración: <?php echo esc_html($duracion); ?> minutos</span>
        </div>
        <?php endif; ?>

    </div>

    <!-- CUERPO: sidebar + contenido -->
    <div class="sl-body">

        <!-- SIDEBAR IZQUIERDO -->
        <div class="sl-sidebar">
            <div class="sl-sidebar-card">
                <span class="sl-sidebar-label">Respuestas<br>completadas</span>
                <span class="sl-sidebar-count" id="slRespCount">0/<?php echo $total_preguntas; ?></span>
            </div>
        </div>

        <!-- CONTENIDO PRINCIPAL -->
        <div class="sl-main">

            <h3 class="sl-atencion-titulo">Presta atención</h3>
            <p class="sl-atencion-desc">Escucha y observa el vídeo antes de responder las respuestas.</p>

            <?php if ($embed_url): ?>
            <div class="sl-video">
                <iframe src="<?php echo esc_url($embed_url); ?>"
                        frameborder="0" allowfullscreen
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                </iframe>
            </div>
            <?php endif; ?>

            <!-- QUIZ -->
            <?php if (!empty($preguntas)): ?>
            <div class="sl-quiz" id="slQuiz">
                <?php foreach ($preguntas as $i => $p): ?>
                <div class="sl-pregunta" data-index="<?php echo $i; ?>">
                    <p class="sl-pregunta-titulo">
                        <?php echo ($i + 1) . '. ' . esc_html($p['pregunta']); ?>
                    </p>
                    <p class="sl-seleccione">Seleccione una respuesta.</p>
                    <div class="sl-opciones">
                        <?php foreach ($p['opciones'] as $letra => $texto): ?>
                        <?php if (!$texto) continue; ?>
                        <label class="sl-opcion">
                            <input type="radio"
                                   name="quiz_<?php echo $i; ?>"
                                   value="<?php echo esc_attr($letra); ?>"
                                   data-correcta="<?php echo esc_attr($p['correcta']); ?>"
                                   onchange="slRespuesta(this, <?php echo $i; ?>)">
                            <?php echo esc_html($letra) . ') ' . esc_html($texto); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ACCIONES -->
            <div class="sl-acciones">
                <?php if (!$ya_completa): ?>
                <button class="sl-btn-finalizar"
                    id="slBtnFinalizar"
                    data-leccion="<?php echo $leccion_id; ?>"
                    data-total="<?php echo $total_preguntas; ?>"
                    onclick="slFinalizar(this)"
                    <?php echo $total_preguntas > 0 ? 'disabled' : ''; ?>>
                    Finalizar lección
                </button>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- MODAL ERROR QUIZ -->
    <div id="slModalError" style="display:none;" class="sl-modal-overlay">
        <div class="sl-modal-box">
            <p class="sl-modal-texto" id="slModalTexto"></p>
            <button class="sl-modal-btn" onclick="slCerrarModal()">Comenzar de nuevo</button>
        </div>
    </div>
</div>

<script>
var slRespondidas = {};
var slTotal = <?php echo $total_preguntas; ?>;

function slRespuesta(input, idx) {
    slRespondidas[idx] = input.value;
    var count = Object.keys(slRespondidas).length;
    document.getElementById('slRespCount').textContent = count + '/' + slTotal;

    // Habilitar botón solo si respondió todas
    if (count >= slTotal) {
        var btn = document.getElementById('slBtnFinalizar');
        if (btn) btn.disabled = false;
    }
}

function slFinalizar(btn) {
    var correctas = 0;
    var total = 0;
    var hayError = false;

    document.querySelectorAll('#slQuiz .sl-pregunta').forEach(function(preg) {
        total++;
        var idx = preg.dataset.index;
        var seleccionada = document.querySelector('input[name="quiz_' + idx + '"]:checked');
        if (!seleccionada || seleccionada.value !== seleccionada.dataset.correcta) {
            hayError = true;
        } else {
            correctas++;
        }
    });

    if (hayError) {
        document.getElementById('slModalTexto').textContent =
            'Tuviste ' + correctas + ' de ' + total + ' respuestas correctas. Necesitas el 100% de respuestas correctas para pasar.';
        document.getElementById('slModalError').style.display = 'flex';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Guardando...';

    fetch(MASS.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mass_completar_leccion'
            + '&leccion_id=' + btn.dataset.leccion
            + '&nonce=' + MASS.nonce
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            window.location.href = '<?php echo $curso_id ? esc_js(get_permalink($curso_id)) : esc_js(home_url("/mi-perfil/")); ?>';
        } else {
            btn.disabled = false;
            btn.textContent = 'Finalizar lección';
            alert(res.data.mensaje || 'Error al guardar.');
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = 'Finalizar lección';
    });
}

function slCerrarModal() {
    document.getElementById('slModalError').style.display = 'none';
}

</script>

<?php endwhile; ?>
<?php get_footer(); ?>