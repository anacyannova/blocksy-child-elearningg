<?php
if (!defined('ABSPATH')) exit;

/* ─────────────────────────────
   METABOX: LECCIÓN
───────────────────────────── */
add_action('add_meta_boxes', function() {

    add_meta_box(
        'mass_leccion_meta',
        'Configuración de la lección',
        'mass_render_leccion_meta',
        'mass_leccion',
        'normal',
        'high'
    );
});

function mass_render_curso_meta($post) {
    $video = get_post_meta($post->ID, 'mass_video_url', true);
    wp_nonce_field('mass_curso_meta_save', 'mass_curso_nonce');
    ?>
    <table class="form-table">
        <tr>
            <th><label for="mass_video_url">URL del video (YouTube/Vimeo)</label></th>
            <td>
                <input type="url" id="mass_video_url" name="mass_video_url"
                       value="<?php echo esc_attr($video); ?>"
                       style="width:100%" placeholder="https://www.youtube.com/watch?v=...">
                <p class="description">Pega la URL normal de YouTube o Vimeo.</p>
            </td>
        </tr>
    </table>
    <?php
}

function mass_render_leccion_meta($post) {
    $video    = get_post_meta($post->ID, 'mass_video_url', true);
    $curso_id = get_post_meta($post->ID, 'mass_curso_id', true);
    $orden    = get_post_meta($post->ID, 'mass_orden', true);
    $duracion = get_post_meta($post->ID, 'mass_duracion', true);

    wp_nonce_field('mass_leccion_meta_save', 'mass_leccion_nonce');

    $cursos = get_posts(array(
        'post_type'      => 'mass_curso',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));
    ?>
    <table class="form-table">
        <tr>
            <th><label for="mass_curso_id">Curso al que pertenece</label></th>
            <td>
                <select name="mass_curso_id" id="mass_curso_id" style="width:100%">
                    <option value="">— Seleccionar curso —</option>
                    <?php foreach ($cursos as $c): ?>
                    <option value="<?php echo $c->ID; ?>" <?php selected($curso_id, $c->ID); ?>>
                        <?php echo esc_html($c->post_title); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="mass_video_url">URL del video (YouTube/Vimeo)</label></th>
            <td>
                <input type="url" id="mass_video_url" name="mass_video_url"
                       value="<?php echo esc_attr($video); ?>"
                       style="width:100%" placeholder="https://www.youtube.com/watch?v=...">
            </td>
        </tr>
        <tr>
            <th><label for="mass_orden">Orden en el curso</label></th>
            <td>
                <input type="number" id="mass_orden" name="mass_orden"
                       value="<?php echo esc_attr($orden ?: 1); ?>"
                       min="1" style="width:80px">
                <p class="description">Número que define el orden de aparición (1, 2, 3...)</p>
            </td>
        </tr>
        <tr>
            <th><label for="mass_duracion">Duración (minutos)</label></th>
            <td>
                <input type="number" id="mass_duracion" name="mass_duracion"
                       value="<?php echo esc_attr($duracion ?: ''); ?>"
                       min="1" style="width:80px">
                <p class="description">Duración aproximada de la lección en minutos.</p>
            </td>
        </tr>
    </table>
    <?php
}

/* ─────────────────────────────
   GUARDAR METABOXES
───────────────────────────── */
add_action('save_post_mass_curso', function($post_id) {
    if (!isset($_POST['mass_curso_nonce'])) return;
    if (!wp_verify_nonce($_POST['mass_curso_nonce'], 'mass_curso_meta_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (isset($_POST['mass_video_url'])) {
        update_post_meta($post_id, 'mass_video_url', esc_url_raw($_POST['mass_video_url']));
    }
});

add_action('save_post_mass_leccion', function($post_id) {
    if (!isset($_POST['mass_leccion_nonce'])) return;
    if (!wp_verify_nonce($_POST['mass_leccion_nonce'], 'mass_leccion_meta_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (isset($_POST['mass_video_url'])) {
        update_post_meta($post_id, 'mass_video_url', esc_url_raw($_POST['mass_video_url']));
    }
    if (isset($_POST['mass_curso_id'])) {
        update_post_meta($post_id, 'mass_curso_id', intval($_POST['mass_curso_id']));
    }
    if (isset($_POST['mass_orden'])) {
        update_post_meta($post_id, 'mass_orden', intval($_POST['mass_orden']));
    }
    if (isset($_POST['mass_duracion'])) {
        update_post_meta($post_id, 'mass_duracion', intval($_POST['mass_duracion']));
    }
});

/* ─────────────────────────────
   METABOX: QUIZ DE LA LECCIÓN
───────────────────────────── */
add_action('add_meta_boxes', function() {
    add_meta_box(
        'mass_leccion_quiz',
        'Preguntas del Quiz',
        'mass_render_quiz_meta',
        'mass_leccion',
        'normal',
        'default'
    );
});

function mass_render_quiz_meta($post) {
    $preguntas = get_post_meta($post->ID, 'mass_quiz', true);
    if (!is_array($preguntas)) $preguntas = [];
    wp_nonce_field('mass_quiz_save', 'mass_quiz_nonce');
    ?>
    <div id="mass-quiz-wrap">
        <p style="color:#555;margin-bottom:16px;">
            Agrega las preguntas del quiz. El alumno debe responderlas todas para finalizar la lección.
        </p>

        <div id="mass-preguntas">
        <?php foreach ($preguntas as $i => $p): ?>
        <div class="mass-pregunta-item" style="border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:16px;background:#f9f9f9;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <strong style="color:#00246F;">Pregunta <?php echo $i + 1; ?></strong>
                <button type="button" onclick="massQuitarPregunta(this)" style="background:#ff4444;color:#fff;border:none;border-radius:4px;padding:4px 10px;cursor:pointer;">Eliminar</button>
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block;font-weight:600;margin-bottom:4px;">Enunciado</label>
                <textarea name="mass_quiz[<?php echo $i; ?>][pregunta]" rows="2"
                          style="width:100%;border:1px solid #ddd;border-radius:4px;padding:8px;"
                ><?php echo esc_textarea($p['pregunta'] ?? ''); ?></textarea>
            </div>
            <?php foreach (['a','b','c','d'] as $l): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                <label style="width:20px;font-weight:600;color:#386AF1;"><?php echo strtoupper($l); ?></label>
                <input type="text" name="mass_quiz[<?php echo $i; ?>][opciones][<?php echo $l; ?>]"
                       value="<?php echo esc_attr($p['opciones'][$l] ?? ''); ?>"
                       placeholder="Opción <?php echo strtoupper($l); ?>"
                       style="flex:1;border:1px solid #ddd;border-radius:4px;padding:6px 10px;">
                <label style="display:flex;align-items:center;gap:4px;font-size:13px;white-space:nowrap;">
                    <input type="radio"
                           name="mass_quiz[<?php echo $i; ?>][correcta]"
                           value="<?php echo $l; ?>"
                           <?php checked(($p['correcta'] ?? ''), $l); ?>>
                    Correcta
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        </div>

        <button type="button" onclick="massAgregarPregunta()"
                style="margin-top:8px;padding:8px 20px;background:#386AF1;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;">
            + Agregar pregunta
        </button>
    </div>

    <script>
    var massQIdx = <?php echo count($preguntas); ?>;

    function massAgregarPregunta() {
        var i = massQIdx++;
        var letras = ['a','b','c','d'];
        var html = '<div class="mass-pregunta-item" style="border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:16px;background:#f9f9f9;">';
        html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">';
        html += '<strong style="color:#00246F;">Pregunta nueva</strong>';
        html += '<button type="button" onclick="massQuitarPregunta(this)" style="background:#ff4444;color:#fff;border:none;border-radius:4px;padding:4px 10px;cursor:pointer;">Eliminar</button>';
        html += '</div>';
        html += '<div style="margin-bottom:10px;"><label style="display:block;font-weight:600;margin-bottom:4px;">Enunciado</label>';
        html += '<textarea name="mass_quiz[' + i + '][pregunta]" rows="2" style="width:100%;border:1px solid #ddd;border-radius:4px;padding:8px;"></textarea></div>';
        letras.forEach(function(l) {
            html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">';
            html += '<label style="width:20px;font-weight:600;color:#386AF1;">' + l.toUpperCase() + '</label>';
            html += '<input type="text" name="mass_quiz[' + i + '][opciones][' + l + ']" placeholder="Opción ' + l.toUpperCase() + '" style="flex:1;border:1px solid #ddd;border-radius:4px;padding:6px 10px;">';
            html += '<label style="display:flex;align-items:center;gap:4px;font-size:13px;white-space:nowrap;">';
            html += '<input type="radio" name="mass_quiz[' + i + '][correcta]" value="' + l + '"> Correcta</label>';
            html += '</div>';
        });
        html += '</div>';
        document.getElementById('mass-preguntas').insertAdjacentHTML('beforeend', html);
    }

    function massQuitarPregunta(btn) {
        btn.closest('.mass-pregunta-item').remove();
    }
    </script>
    <?php
}

/* ─── GUARDAR QUIZ ─── */
add_action('save_post_mass_leccion', function($post_id) {
    if (!isset($_POST['mass_quiz_nonce'])) return;
    if (!wp_verify_nonce($_POST['mass_quiz_nonce'], 'mass_quiz_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $quiz = [];
    if (!empty($_POST['mass_quiz']) && is_array($_POST['mass_quiz'])) {
        foreach ($_POST['mass_quiz'] as $p) {
            $pregunta = sanitize_textarea_field($p['pregunta'] ?? '');
            if (!$pregunta) continue;
            $opciones = [];
            foreach (['a','b','c','d'] as $l) {
                $opciones[$l] = sanitize_text_field($p['opciones'][$l] ?? '');
            }
            $quiz[] = [
                'pregunta' => $pregunta,
                'opciones' => $opciones,
                'correcta' => sanitize_text_field($p['correcta'] ?? 'a'),
            ];
        }
    }
    update_post_meta($post_id, 'mass_quiz', $quiz);
}, 20);