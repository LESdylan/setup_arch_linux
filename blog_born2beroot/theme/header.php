<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">
    <a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e('Skip to content', 'sublime-tech'); ?></a>

    <header id="masthead" class="site-header">
        <div class="site-branding">
            <?php if (has_custom_logo()): ?>
                <div class="site-logo"><?php the_custom_logo(); ?></div>
            <?php endif; ?>
            
            <div class="site-title-container">
                <?php if (is_front_page() && is_home()): ?>
                    <h1 class="site-title"><a href="<?php echo esc_url(home_url('/')); ?>" rel="home"><?php bloginfo('name'); ?></a></h1>
                <?php else: ?>
                    <p class="site-title"><a href="<?php echo esc_url(home_url('/')); ?>" rel="home"><?php bloginfo('name'); ?></a></p>
                <?php endif; ?>
                
                <?php $description = get_bloginfo('description', 'display'); ?>
                <?php if ($description || is_customize_preview()): ?>
                    <p class="site-description"><?php echo $description; ?></p>
                <?php endif; ?>
            </div>
        </div>

        <nav id="site-navigation" class="main-navigation">
            <?php
            wp_nav_menu(
                array(
                    'theme_location' => 'primary',
                    'menu_id'        => 'primary-menu',
                    'fallback_cb'    => false,
                )
            );
            ?>
        </nav>
    </header>

    <div id="content" class="site-content">
