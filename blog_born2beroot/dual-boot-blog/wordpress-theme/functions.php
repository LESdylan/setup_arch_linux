<?php
// This file is used to define theme functions and features for the dual boot blog WordPress theme

// Enqueue styles and scripts
function dual_boot_blog_enqueue_scripts() {
    wp_enqueue_style('main-style', get_stylesheet_uri());
    wp_enqueue_script('main-js', get_template_directory_uri() . '/js/main.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'dual_boot_blog_enqueue_scripts');

// Add theme support for featured images
function dual_boot_blog_setup() {
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
}
add_action('after_setup_theme', 'dual_boot_blog_setup');

// Register custom navigation menus
function dual_boot_blog_menus() {
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'dual_boot_blog'),
        'footer' => __('Footer Menu', 'dual_boot_blog'),
    ));
}
add_action('init', 'dual_boot_blog_menus');

// Register a custom post type for tutorials
function dual_boot_blog_custom_post_type() {
    register_post_type('tutorials', array(
        'labels' => array(
            'name' => __('Tutorials'),
            'singular_name' => __('Tutorial'),
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'thumbnail'),
    ));
}
add_action('init', 'dual_boot_blog_custom_post_type');
?>