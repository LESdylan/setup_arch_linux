<?php
/**
 * Sublime Tech Theme functions and definitions
 */

if (!defined('SUBLIME_TECH_VERSION')) {
    define('SUBLIME_TECH_VERSION', '1.0.0');
}

/**
 * Set up theme defaults and register support for various WordPress features
 */
function sublime_tech_setup() {
    // Add default posts and comments RSS feed links to head
    add_theme_support('automatic-feed-links');

    // Let WordPress manage the document title
    add_theme_support('title-tag');

    // Enable support for Post Thumbnails on posts and pages
    add_theme_support('post-thumbnails');

    // Register navigation menus
    register_nav_menus(
        array(
            'primary' => esc_html__('Primary Menu', 'sublime-tech'),
            'footer' => esc_html__('Footer Menu', 'sublime-tech'),
        )
    );

    // Add theme support for selective refresh for widgets
    add_theme_support('customize-selective-refresh-widgets');

    // Add support for editor styles
    add_theme_support('editor-styles');
    
    // Add support for responsive embeds
    add_theme_support('responsive-embeds');
    
    // Add support for full and wide align images
    add_theme_support('align-wide');

    // Add support for the block editor
    add_theme_support('wp-block-styles');
}
add_action('after_setup_theme', 'sublime_tech_setup');

/**
 * Enqueue scripts and styles
 */
function sublime_tech_scripts() {
    // Theme stylesheet
    wp_enqueue_style('sublime-tech-style', get_stylesheet_uri(), array(), SUBLIME_TECH_VERSION);
    
    // Sublime dark theme style
    wp_enqueue_style('sublime-dark', get_template_directory_uri() . '/assets/css/sublime-dark.css', array(), SUBLIME_TECH_VERSION);
    
    // Custom Prism.js theme that matches Sublime
    wp_enqueue_style('prism-sublime', get_template_directory_uri() . '/assets/css/prism-sublime.css', array(), SUBLIME_TECH_VERSION);
    
    // Theme JavaScript
    wp_enqueue_script('sublime-tech-script', get_template_directory_uri() . '/assets/js/theme.js', array('jquery'), SUBLIME_TECH_VERSION, true);

    // Comment reply script
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'sublime_tech_scripts');

/**
 * Register widget area
 */
function sublime_tech_widgets_init() {
    register_sidebar(
        array(
            'name'          => esc_html__('Sidebar', 'sublime-tech'),
            'id'            => 'sidebar-1',
            'description'   => esc_html__('Add widgets here to appear in your sidebar.', 'sublime-tech'),
            'before_widget' => '<section id="%1$s" class="widget %2$s">',
            'after_widget'  => '</section>',
            'before_title'  => '<h2 class="widget-title">',
            'after_title'   => '</h2>',
        )
    );
}
add_action('widgets_init', 'sublime_tech_widgets_init');

/**
 * Include additional functions
 */
require get_template_directory() . '/inc/template-tags.php';
require get_template_directory() . '/inc/customizer.php';