<?php
if (!defined('ABSPATH')) exit;

/* ═══════════════════════════════════════════════════════
   SISTEMA DE CERTIFICADOS — MASS eLearning
   Archivo: includes/certificados.php
   ═══════════════════════════════════════════════════════ */


/* ─────────────────────────────
   1. AGREGAR COLUMNA EN BD
   (código de verificación único)
─────────────────────────────── */
add_action('after_switch_theme', 'mass_actualizar_tabla_certificados');
function mass_actualizar_tabla_certificados() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    // Recrear con columna codigo si no existe
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mass_certificados (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     BIGINT UNSIGNED NOT NULL,
        curso_id    BIGINT UNSIGNED NOT NULL,
        fecha       DATETIME DEFAULT CURRENT_TIMESTAMP,
        codigo      VARCHAR(20) NOT NULL DEFAULT '',
        UNIQUE KEY uq_certificado (user_id, curso_id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Agregar columna codigo si la tabla ya existía sin ella
    $col = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}mass_certificados LIKE 'codigo'");
    if (empty($col)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}mass_certificados ADD COLUMN codigo VARCHAR(20) NOT NULL DEFAULT ''");
    }
}

// También intentar al cargar (para sitios donde el tema ya estaba activo)
add_action('init', function() {
    global $wpdb;
    $col = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}mass_certificados LIKE 'codigo'");
    if (empty($col)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}mass_certificados ADD COLUMN codigo VARCHAR(20) NOT NULL DEFAULT ''");
    }
}, 5);


/* ─────────────────────────────
   2. GENERAR CÓDIGO ÚNICO
─────────────────────────────── */
function mass_generar_codigo_certificado($user_id, $curso_id) {
    // Formato: MASS-XXXX-XXXX (alfanumérico mayúscula)
    $base   = strtoupper(substr(md5($user_id . '-' . $curso_id . '-' . time()), 0, 8));
    $codigo = 'MASS-' . substr($base, 0, 4) . '-' . substr($base, 4, 4);
    return $codigo;
}


/* ─────────────────────────────
   3. REGISTRAR CERTIFICADO
   (llamado desde cursos-ajax.php
   cuando progreso = 100%)
─────────────────────────────── */
function mass_emitir_certificado($user_id, $curso_id) {
    global $wpdb;
    $tabla = $wpdb->prefix . 'mass_certificados';

    // ¿Ya tiene certificado?
    $existe = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $tabla WHERE user_id = %d AND curso_id = %d",
        $user_id, $curso_id
    ));

    if ($existe) return $existe->codigo; // ya emitido, devolver código

    $codigo = mass_generar_codigo_certificado($user_id, $curso_id);

    $wpdb->replace($tabla, [
        'user_id'  => $user_id,
        'curso_id' => $curso_id,
        'fecha'    => current_time('mysql'),
        'codigo'   => $codigo,
    ]);

    return $codigo;
}


/* ─────────────────────────────
   4. REEMPLAZAR wpdb->replace
   en cursos-ajax.php para que
   use nuestra función
─────────────────────────────── */
// Hook que se dispara al completar lección (100%)
add_action('mass_curso_completado', function($user_id, $curso_id) {
    mass_emitir_certificado($user_id, $curso_id);
}, 10, 2);


/* ─────────────────────────────
   5. SHORTCODE [mass_certificado]
   Para usar en una página WordPress
─────────────────────────────── */
add_shortcode('mass_certificado', 'mass_shortcode_certificado');
function mass_shortcode_certificado($atts) {
    if (!is_user_logged_in()) {
        return '<p>Debes iniciar sesión para ver tu certificado.</p>';
    }

    $curso_id = intval($_GET['curso'] ?? 0);
    if (!$curso_id) {
        return '<p>No se especificó un curso.</p>';
    }

    global $wpdb;
    $user_id = get_current_user_id();

    $cert = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}mass_certificados WHERE user_id = %d AND curso_id = %d",
        $user_id, $curso_id
    ));

    if (!$cert) {
        // Intentar emitir si el curso está realmente completado
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
             AND pm.meta_key = 'mass_curso_id' AND pm.meta_value = %d",
            $user_id, $curso_id
        ));

        if ($total > 0 && $completadas >= $total) {
            $codigo = mass_emitir_certificado($user_id, $curso_id);
            $cert = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}mass_certificados WHERE user_id = %d AND curso_id = %d",
                $user_id, $curso_id
            ));
        } else {
            return '<p style="color:#666;text-align:center;padding:40px;">Aún no has completado este curso.</p>';
        }
    }

    // Asegurar que tiene código (certificados viejos sin código)
    if (empty($cert->codigo)) {
        $codigo = mass_generar_codigo_certificado($cert->user_id, $cert->curso_id);
        $wpdb->update(
            $wpdb->prefix . 'mass_certificados',
            ['codigo' => $codigo],
            ['id' => $cert->id]
        );
        $cert->codigo = $codigo;
    }

    $curso  = get_post($curso_id);
    $user   = get_userdata($user_id);
    $nombre = $user->display_name;
    $fecha  = date_i18n('d \d\e F \d\e Y', strtotime($cert->fecha));
    $titulo = $curso ? $curso->post_title : 'Curso';
    $codigo = $cert->codigo;

    $url_pdf = add_query_arg([
        'mass_cert_pdf' => 1,
        'curso'         => $curso_id,
        'uid'           => $user_id,
        'token'         => mass_token_certificado($user_id, $curso_id),
    ], home_url('/'));

    ob_start();
    ?>
    <div class="mass-cert-wrap" id="massCertWrap">
        <div class="mass-cert-actions">
            <button onclick="window.print()" class="mass-cert-btn mass-cert-btn--print">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir / Guardar PDF
            </button>
            <a href="<?php echo esc_url($url_pdf); ?>" class="mass-cert-btn mass-cert-btn--download" target="_blank">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Descargar PDF
            </a>
        </div>

        <!-- CERTIFICADO VISUAL -->
        <div class="mass-cert" id="massCert">
            <div class="mass-cert__border">

                <div class="mass-cert__logo">
                    <?php
                    $logo = get_option('mass_cert_logo', '');
                    if ($logo): ?>
                        <img src="<?php echo esc_url($logo); ?>" alt="Logo">
                    <?php else:
                        $logo_tema = get_stylesheet_directory_uri() . '/assets/img/mass-logo-1.png';
                    ?>
                        <img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/img/mass-logo-1.png'); ?>" alt="MASS">
                    <?php endif; ?>
                </div>

                <p class="mass-cert__pretitulo">CERTIFICADO DE PARTICIPACIÓN</p>

                <h1 class="mass-cert__presenta">Se certifica que</h1>

                <h2 class="mass-cert__nombre"><?php echo esc_html($nombre); ?></h2>

                <p class="mass-cert__texto">
                    ha completado satisfactoriamente el curso
                </p>

                <h3 class="mass-cert__curso"><?php echo esc_html($titulo); ?></h3>

                <p class="mass-cert__fecha">
                    Emitido el <?php echo esc_html($fecha); ?>
                </p>

                <?php
                $firma_url  = get_option('mass_cert_firma', '');
                $firma_nombre = get_option('mass_cert_firma_nombre', '');
                $firma_cargo  = get_option('mass_cert_firma_cargo', '');
                if ($firma_url || $firma_nombre): ?>
                <div class="mass-cert__firmas">
                    <div class="mass-cert__firma-item">
                        <?php if ($firma_url): ?>
                        <img src="<?php echo esc_url($firma_url); ?>" alt="Firma" class="mass-cert__firma-img">
                        <?php endif; ?>
                        <div class="mass-cert__firma-linea"></div>
                        <?php if ($firma_nombre): ?>
                        <p class="mass-cert__firma-nombre"><?php echo esc_html($firma_nombre); ?></p>
                        <?php endif; ?>
                        <?php if ($firma_cargo): ?>
                        <p class="mass-cert__firma-cargo"><?php echo esc_html($firma_cargo); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mass-cert__footer">
                    <p class="mass-cert__codigo">Código de verificación: <strong><?php echo esc_html($codigo); ?></strong></p>
                    <p class="mass-cert__verify">Verifica este certificado en: <span><?php echo esc_url(home_url('/verificar-certificado/?codigo=' . $codigo)); ?></span></p>
                </div>

            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


/* ─────────────────────────────
   6. TOKEN SEGURO PARA PDF
─────────────────────────────── */
function mass_token_certificado($user_id, $curso_id) {
    return hash_hmac('sha256', $user_id . '-' . $curso_id, wp_salt('auth'));
}

function mass_verificar_token_certificado($user_id, $curso_id, $token) {
    return hash_equals(mass_token_certificado($user_id, $curso_id), $token);
}


/* ─────────────────────────────
   7. ENDPOINT PDF (sin librería)
   Genera HTML imprimible
─────────────────────────────── */
add_action('template_redirect', 'mass_servir_certificado_pdf');
function mass_servir_certificado_pdf() {
    if (empty($_GET['mass_cert_pdf'])) return;

    $curso_id = intval($_GET['curso'] ?? 0);
    $user_id  = intval($_GET['uid']   ?? 0);
    $token    = sanitize_text_field($_GET['token'] ?? '');

    if (!$curso_id || !$user_id || !mass_verificar_token_certificado($user_id, $curso_id, $token)) {
        wp_die('Certificado no válido.', 403);
    }

    global $wpdb;
    $cert = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}mass_certificados WHERE user_id = %d AND curso_id = %d",
        $user_id, $curso_id
    ));

    if (!$cert) wp_die('Certificado no encontrado.', 404);

    $curso  = get_post($curso_id);
    $user   = get_userdata($user_id);
    $nombre = $user->display_name;
    $fecha  = date_i18n('d \d\e F \d\e Y', strtotime($cert->fecha));
    $titulo = $curso ? $curso->post_title : 'Curso';
    $codigo = $cert->codigo ?: 'SIN-CÓDIGO';

    $firma_url    = get_option('mass_cert_firma', '');
    $firma_nombre = get_option('mass_cert_firma_nombre', '');

    // RUT del alumno desde ACF
    $rut = function_exists('get_field') ? get_field('rut', 'user_' . $user_id) : '';

    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
    <meta charset="UTF-8">
    <title>Certificado — <?php echo esc_html($nombre); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            background: #e8e8e8;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 30px 20px;
            font-family: 'Montserrat', sans-serif;
            min-height: 100vh;
        }

        .pdf-actions {
            margin-bottom: 20px;
        }

        .pdf-btn {
            padding: 10px 28px;
            background: #00246F;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
        }

        /* ── CERTIFICADO ── */
        .cert {
            width: 960px;
            background: #f4f5f8;
            position: relative;
            box-shadow: 0 10px 50px rgba(0,0,0,0.18);
        }

        /* Borde exterior azul marino */
        .cert__outer {
            border: 6px solid #1a3466;
            margin: 0;
            padding: 0;
            position: relative;
        }

        /* Borde interior */
        .cert__inner {
            border: 2px solid #1a3466;
            margin: 8px;
            padding: 52px 70px 44px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            min-height: 580px;
            position: relative;
        }

        /* Cinta / marcador arriba a la izquierda */
        .cert__ribbon {
            position: absolute;
            top: -6px;
            left: 40px;
            width: 52px;
            height: 80px;
            background: #1a3466;
            clip-path: polygon(0 0, 100% 0, 100% 85%, 50% 100%, 0 85%);
            z-index: 10;
        }

        /* Escudo marca de agua */
        .cert__watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 300px;
            height: 300px;
            opacity: 0.055;
            pointer-events: none;
        }

        /* ── TEXTOS ── */
        .cert__titulo {
            font-size: 52px;
            font-weight: 800;
            color: #1a3466;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 4px;
            position: relative;
            z-index: 1;
        }

        .cert__subtitulo {
            font-size: 15px;
            font-weight: 400;
            color: #1a3466;
            letter-spacing: 1px;
            margin-bottom: 32px;
            position: relative;
            z-index: 1;
        }

        .cert__presenta {
            font-size: 13px;
            color: #888;
            font-weight: 400;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
        }

        .cert__nombre {
            font-family: 'Dancing Script', cursive;
            font-size: 54px;
            color: #1a2233;
            line-height: 1.1;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .cert__rut {
            font-size: 14px;
            font-weight: 700;
            color: #1a3466;
            letter-spacing: 1px;
            margin-bottom: 28px;
            position: relative;
            z-index: 1;
        }

        .cert__completado {
            font-size: 13px;
            color: #888;
            font-weight: 400;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .cert__curso {
            font-size: 22px;
            font-weight: 800;
            color: #1a3466;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
        }

        .cert__curso-linea {
            width: 60px;
            height: 3px;
            background: #1a3466;
            margin: 0 auto 24px;
            position: relative;
            z-index: 1;
        }

        .cert__descripcion {
            font-size: 13px;
            color: #555;
            font-weight: 400;
            line-height: 1.7;
            max-width: 540px;
            margin-bottom: 40px;
            position: relative;
            z-index: 1;
        }

        /* ── FIRMAS ── */
        .cert__firmas {
            display: flex;
            justify-content: space-between;
            width: 100%;
            margin-top: auto;
            position: relative;
            z-index: 1;
        }

        .cert__firma-item {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            min-width: 180px;
        }

        .cert__firma-img {
            height: 45px;
            object-fit: contain;
            margin-bottom: 4px;
        }

        .cert__firma-linea {
            width: 180px;
            height: 1px;
            background: #333;
            margin-bottom: 5px;
        }

        .cert__firma-label {
            font-size: 12px;
            color: #333;
            font-weight: 400;
        }

        .cert__firma-valor {
            font-size: 12px;
            color: #555;
        }

        /* ── PRINT ── */
        @media print {
            body { background: #fff; padding: 0; }
            .pdf-actions { display: none !important; }
            .cert { box-shadow: none; width: 100%; }
            @page { size: A4 landscape; margin: 0; }
        }
    </style>
    </head>
    <body>

    <div class="pdf-actions">
        <button class="pdf-btn" onclick="window.print()">🖨 Imprimir / Guardar como PDF</button>
    </div>

    <div class="cert">
        <div class="cert__outer">

            <!-- Cinta decorativa arriba izquierda -->
            <div class="cert__ribbon"></div>

            <div class="cert__inner">

                <!-- Escudo marca de agua -->
                <svg class="cert__watermark" viewBox="0 0 100 115" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M50 2L95 20V55C95 80 75 102 50 113C25 102 5 80 5 55V20L50 2Z" fill="#1a3466"/>
                    <path d="M50 15L85 30V55C85 75 68 94 50 103C32 94 15 75 15 55V30L50 15Z" fill="none" stroke="#1a3466" stroke-width="2"/>
                </svg>

                <h1 class="cert__titulo">Certificado</h1>
                <p class="cert__subtitulo">de participación</p>

                <p class="cert__presenta">Este documento certifica que</p>

                <div class="cert__nombre"><?php echo esc_html($nombre); ?></div>

                <?php if ($rut): ?>
                <p class="cert__rut"><?php echo esc_html($rut); ?></p>
                <?php endif; ?>

                <p class="cert__completado">Ha completado con éxito el curso de:</p>

                <div class="cert__curso"><?php echo esc_html($titulo); ?></div>
                <div class="cert__curso-linea"></div>

                <p class="cert__descripcion">
                    adquiriendo los conocimientos y habilidades correspondientes, y<br>
                    cumpliendo con los contenidos establecidos. Se deja constancia de<br>
                    su participación y aprobación de la capacitación.
                </p>

                <!-- Firmas -->
                <div class="cert__firmas">

                    <div class="cert__firma-item">
                        <div class="cert__firma-linea"></div>
                        <span class="cert__firma-label">Fecha:</span>
                        <span class="cert__firma-valor"><?php echo esc_html($fecha); ?></span>
                    </div>

                    <div class="cert__firma-item" style="align-items:flex-end;">
                        <?php if ($firma_url): ?>
                        <img src="<?php echo esc_url($firma_url); ?>" alt="Firma" class="cert__firma-img">
                        <?php endif; ?>
                        <div class="cert__firma-linea"></div>
                        <span class="cert__firma-label">Firma de Centro de capacitación</span>
                        <?php if ($firma_nombre): ?>
                        <span class="cert__firma-valor"><?php echo esc_html($firma_nombre); ?></span>
                        <?php endif; ?>
                    </div>

                </div>

            </div>
        </div>
    </div>

    </body>
    </html>
    <?php
    exit;
}


/* ─────────────────────────────
   8. SHORTCODE [mass_verificar]
   Página pública de verificación
─────────────────────────────── */
add_shortcode('mass_verificar_certificado', 'mass_shortcode_verificar');
function mass_shortcode_verificar($atts) {
    $codigo = sanitize_text_field($_GET['codigo'] ?? '');
    $resultado = '';

    if ($codigo) {
        global $wpdb;
        $cert = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mass_certificados WHERE codigo = %s",
            $codigo
        ));

        if ($cert) {
            $user  = get_userdata($cert->user_id);
            $curso = get_post($cert->curso_id);
            $fecha = date_i18n('d \d\e F \d\e Y', strtotime($cert->fecha));
            $resultado = '
            <div class="mass-verify mass-verify--ok">
                <div class="mass-verify__icon">✅</div>
                <h3>Certificado válido</h3>
                <p><strong>Alumno:</strong> ' . esc_html($user->display_name) . '</p>
                <p><strong>Curso:</strong> ' . esc_html($curso->post_title) . '</p>
                <p><strong>Fecha:</strong> ' . esc_html($fecha) . '</p>
                <p><strong>Código:</strong> ' . esc_html($codigo) . '</p>
            </div>';
        } else {
            $resultado = '
            <div class="mass-verify mass-verify--error">
                <div class="mass-verify__icon">❌</div>
                <h3>Certificado no encontrado</h3>
                <p>El código <strong>' . esc_html($codigo) . '</strong> no corresponde a ningún certificado emitido.</p>
            </div>';
        }
    }

    ob_start();
    ?>
    <div class="mass-verify-wrap">
        <?php if (!$codigo): ?>
        <form method="get" class="mass-verify-form">
            <label for="mass_codigo">Ingresa el código del certificado:</label>
            <div class="mass-verify-input-group">
                <input type="text" id="mass_codigo" name="codigo"
                       placeholder="Ej: MASS-AB12-CD34"
                       value="<?php echo esc_attr($codigo); ?>">
                <button type="submit">Verificar</button>
            </div>
        </form>
        <?php endif; ?>

        <?php echo $resultado; ?>

        <?php if ($codigo): ?>
        <a href="<?php echo esc_url(remove_query_arg('codigo')); ?>" class="mass-verify-back">← Verificar otro</a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}


/* ─────────────────────────────
   9. BOTÓN EN MI PERFIL
   Helper para mostrar botón
   de descarga en page-miperfil
─────────────────────────────── */
function mass_boton_certificado($user_id, $curso_id) {
    global $wpdb;
    $cert = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}mass_certificados WHERE user_id = %d AND curso_id = %d",
        $user_id, $curso_id
    ));

    if (!$cert) return '';

    $url = add_query_arg([
        'mass_cert_pdf' => 1,
        'curso'         => $curso_id,
        'uid'           => $user_id,
        'token'         => mass_token_certificado($user_id, $curso_id),
    ], home_url('/'));

    return '<a href="' . esc_url($url) . '" target="_blank" class="mass-cert-badge">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        Ver Certificado
    </a>';
}


/* ─────────────────────────────
   10. PÁGINA DE AJUSTES ADMIN
─────────────────────────────── */
add_action('admin_menu', 'mass_menu_certificados');
function mass_menu_certificados() {
    add_submenu_page(
        'edit.php?post_type=mass_curso',
        'Configuración de Certificados',
        '🎓 Certificados',
        'manage_options',
        'mass-certificados',
        'mass_pagina_certificados'
    );
}

function mass_pagina_certificados() {
    if (isset($_POST['mass_cert_save']) && check_admin_referer('mass_cert_settings')) {
        update_option('mass_cert_logo',          esc_url_raw($_POST['mass_cert_logo'] ?? ''));
        update_option('mass_cert_firma',         esc_url_raw($_POST['mass_cert_firma'] ?? ''));
        update_option('mass_cert_firma_nombre',  sanitize_text_field($_POST['mass_cert_firma_nombre'] ?? ''));
        update_option('mass_cert_firma_cargo',   sanitize_text_field($_POST['mass_cert_firma_cargo'] ?? ''));
        update_option('mass_cert_color_primario',   sanitize_hex_color($_POST['mass_cert_color_primario'] ?? '#00246F'));
        update_option('mass_cert_color_secundario', sanitize_hex_color($_POST['mass_cert_color_secundario'] ?? '#386AF1'));
        update_option('mass_cert_color_acento',     sanitize_hex_color($_POST['mass_cert_color_acento'] ?? '#D4AF37'));
        echo '<div class="notice notice-success"><p>✅ Configuración guardada.</p></div>';
    }

    $logo          = get_option('mass_cert_logo', '');
    $firma         = get_option('mass_cert_firma', '');
    $firma_nombre  = get_option('mass_cert_firma_nombre', '');
    $firma_cargo   = get_option('mass_cert_firma_cargo', '');
    $col_pri       = get_option('mass_cert_color_primario', '#00246F');
    $col_sec       = get_option('mass_cert_color_secundario', '#386AF1');
    $col_ace       = get_option('mass_cert_color_acento', '#D4AF37');

    // Certificados emitidos
    global $wpdb;
    $certificados = $wpdb->get_results(
        "SELECT c.*, u.display_name, p.post_title
         FROM {$wpdb->prefix}mass_certificados c
         JOIN {$wpdb->users} u ON c.user_id = u.ID
         JOIN {$wpdb->posts} p ON c.curso_id = p.ID
         ORDER BY c.fecha DESC
         LIMIT 50"
    );
    ?>
    <div class="wrap">
        <h1>🎓 Configuración de Certificados</h1>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-top:20px;">

            <!-- FORMULARIO -->
            <div>
                <form method="post">
                    <?php wp_nonce_field('mass_cert_settings'); ?>

                    <table class="form-table">
                        <tr>
                            <th>Logo del certificado</th>
                            <td>
                                <input type="url" name="mass_cert_logo" value="<?php echo esc_attr($logo); ?>"
                                       style="width:100%" placeholder="URL de la imagen del logo">
                                <p class="description">Pega la URL del logo (Media > Copiar URL). Dejar vacío para usar el logo del tema.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Imagen de firma</th>
                            <td>
                                <input type="url" name="mass_cert_firma" value="<?php echo esc_attr($firma); ?>"
                                       style="width:100%" placeholder="URL de la firma (imagen PNG transparente)">
                            </td>
                        </tr>
                        <tr>
                            <th>Nombre del firmante</th>
                            <td>
                                <input type="text" name="mass_cert_firma_nombre" value="<?php echo esc_attr($firma_nombre); ?>"
                                       style="width:100%" placeholder="Ej: Juan Pérez">
                            </td>
                        </tr>
                        <tr>
                            <th>Cargo del firmante</th>
                            <td>
                                <input type="text" name="mass_cert_firma_cargo" value="<?php echo esc_attr($firma_cargo); ?>"
                                       style="width:100%" placeholder="Ej: Director Académico">
                            </td>
                        </tr>
                        <tr>
                            <th>Color primario</th>
                            <td>
                                <input type="color" name="mass_cert_color_primario" value="<?php echo esc_attr($col_pri); ?>">
                                <span style="color:#666;font-size:12px;margin-left:8px;">Borde y nombre del alumno</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Color secundario</th>
                            <td>
                                <input type="color" name="mass_cert_color_secundario" value="<?php echo esc_attr($col_sec); ?>">
                                <span style="color:#666;font-size:12px;margin-left:8px;">Nombre del curso</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Color acento (dorado)</th>
                            <td>
                                <input type="color" name="mass_cert_color_acento" value="<?php echo esc_attr($col_ace); ?>">
                                <span style="color:#666;font-size:12px;margin-left:8px;">Líneas decorativas y pretítulo</span>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="mass_cert_save" class="button button-primary">
                            Guardar configuración
                        </button>
                    </p>
                </form>
            </div>

            <!-- INSTRUCCIONES -->
            <div style="background:#f8f9ff;border:1px solid #d0d7ff;border-radius:8px;padding:24px;">
                <h3 style="margin-top:0;color:#00246F;">📋 Instrucciones de instalación</h3>

                <p><strong>1. Crear página del certificado</strong><br>
                Ve a Páginas > Añadir nueva. Slug sugerido: <code>certificado</code><br>
                Agrega el shortcode: <code>[mass_certificado]</code></p>

                <p><strong>2. Crear página de verificación</strong><br>
                Crea una página con slug <code>verificar-certificado</code><br>
                Agrega: <code>[mass_verificar_certificado]</code></p>

                <p><strong>3. Botón en Mi Perfil</strong><br>
                En <code>page-miperfil.php</code>, dentro del loop de cursos,<br>
                agrega donde quieras mostrar el botón:</p>
                <code style="display:block;background:#fff;padding:8px;border-radius:4px;font-size:12px;margin-top:4px;">
                    &lt;?php echo mass_boton_certificado($user_id, $course_id); ?&gt;
                </code>

                <p style="margin-top:16px;"><strong>4. Verificar BD</strong><br>
                Si los certificados antiguos no tienen código, visita esta URL una vez:<br>
                <code style="font-size:11px;"><?php echo esc_url(add_query_arg('mass_fix_codigos', '1', home_url('/'))); ?></code>
                </p>
            </div>
        </div>

        <!-- TABLA DE CERTIFICADOS EMITIDOS -->
        <h2 style="margin-top:40px;">Certificados emitidos</h2>
        <?php if (empty($certificados)): ?>
        <p>Aún no se han emitido certificados.</p>
        <?php else: ?>
        <table class="widefat striped" style="margin-top:10px;">
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Curso</th>
                    <th>Fecha</th>
                    <th>Código</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($certificados as $c):
                $url = add_query_arg([
                    'mass_cert_pdf' => 1,
                    'curso'         => $c->curso_id,
                    'uid'           => $c->user_id,
                    'token'         => mass_token_certificado($c->user_id, $c->curso_id),
                ], home_url('/'));
            ?>
            <tr>
                <td><?php echo esc_html($c->display_name); ?></td>
                <td><?php echo esc_html($c->post_title); ?></td>
                <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($c->fecha))); ?></td>
                <td><code><?php echo esc_html($c->codigo ?: '—'); ?></code></td>
                <td>
                    <a href="<?php echo esc_url($url); ?>" target="_blank" class="button button-small">
                        Ver certificado
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}


/* ─────────────────────────────
   11. FIX: generar códigos
   para certificados existentes
─────────────────────────────── */
add_action('init', function() {
    if (empty($_GET['mass_fix_codigos']) || !current_user_can('administrator')) return;

    global $wpdb;
    $sin_codigo = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}mass_certificados WHERE codigo = '' OR codigo IS NULL"
    );

    foreach ($sin_codigo as $c) {
        $codigo = mass_generar_codigo_certificado($c->user_id, $c->curso_id);
        $wpdb->update(
            $wpdb->prefix . 'mass_certificados',
            ['codigo' => $codigo],
            ['id'     => $c->id]
        );
    }

    wp_die('✅ Códigos generados para ' . count($sin_codigo) . ' certificados. <a href="' . admin_url('edit.php?post_type=mass_curso&page=mass-certificados') . '">Volver</a>');
});
