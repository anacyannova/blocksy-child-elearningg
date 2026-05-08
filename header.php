<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <header class="site-header">
        <div class="header-inner">
            <div class="brand">
                <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/img/mass-logo-1.png" alt="logo escudo azul de capacitaciones MASS" class="logo">
                <span class="brand-name">capacitaciones MASS</span>
            </div>
            <?php if (is_user_logged_in() && !is_front_page()):
                $user  = wp_get_current_user();
                $roles = (array) $user->roles;
                $es_trabajador = in_array('subscriber', $roles);
                $es_empresa    = in_array('supervisor_empresa', $roles) || in_array('tutor_instructor', $roles) || in_array('administrator', $roles);
            ?>
            <nav class="header-nav">
                <?php if ($es_trabajador): ?>
                    <a href="<?php echo get_permalink(get_page_by_path('mi-perfil')); ?>">Mi perfil</a>
                <?php endif; ?>
                <a href="<?php echo wp_logout_url(home_url()); ?>">Cerrar sesión</a>
            </nav>
        <?php endif; ?>
        </div>
    </header>