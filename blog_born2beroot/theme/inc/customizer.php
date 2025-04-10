<?php
/**
 * Sublime Tech Theme Customizer
 */

/**
 * Add postMessage support for site title and description for the Theme Customizer.
 *
 * @param WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function sublime_tech_customize_register($wp_customize) {
    $wp_customize->get_setting('blogname')->transport         = 'postMessage';
    $wp_customize->get_setting('blogdescription')->transport  = 'postMessage';
    
    // Add section for theme options
    $wp_customize->add_section('sublime_tech_theme_options', array(
        'title'    => __('Theme Options', 'sublime-tech'),
        'priority' => 130,
    ));
    
    // Add code highlighting option
    $wp_customize->add_setting('sublime_tech_code_highlight', array(
        'default'           => true,
        'sanitize_callback' => 'sublime_tech_sanitize_checkbox',
    ));
    
    $wp_customize->add_control('sublime_tech_code_highlight', array(
        'label'       => __('Enable code syntax highlighting', 'sublime-tech'),
        'description' => __('Turn on/off syntax highlighting for code blocks using Prism.js', 'sublime-tech'),
        'section'     => 'sublime_tech_theme_options',
        'type'        => 'checkbox',
    ));
    
    // Add line numbers option
    $wp_customize->add_setting('sublime_tech_line_numbers', array(
        'default'           => true,
        'sanitize_callback' => 'sublime_tech_sanitize_checkbox',
    ));
    
    $wp_customize->add_control('sublime_tech_line_numbers', array(
        'label'       => __('Show line numbers in code blocks', 'sublime-tech'),
        'section'     => 'sublime_tech_theme_options',
        'type'        => 'checkbox',
    ));
    
    // Add code font option
    $wp_customize->add_setting('sublime_tech_code_font', array(
        'default'           => 'Source Code Pro',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    
    $wp_customize->add_control('sublime_tech_code_font', array(
        'label'       => __('Code Font', 'sublime-tech'),
        'description' => __('Font used for code blocks', 'sublime-tech'),
        'section'     => 'sublime_tech_theme_options',
        'type'        => 'select',
        'choices'     => array(
            'Source Code Pro'  => 'Source Code Pro',
            'Consolas'         => 'Consolas',
            'Monaco'           => 'Monaco',
            'Ubuntu Mono'      => 'Ubuntu Mono',
            'Fira Code'        => 'Fira Code',
            'JetBrains Mono'   => 'JetBrains Mono',
        ),
    ));
}
add_action('customize_register', 'sublime_tech_customize_register');

/**
 * Sanitize checkbox inputs
 */
function sublime_tech_sanitize_checkbox($checked) {
    return ((isset($checked) && true == $checked) ? true : false);
}

/**
 * Binds JS handlers to make Theme Customizer preview reload changes asynchronously.
 */
function sublime_tech_customize_preview_js() {
    wp_enqueue_script('sublime-tech-customizer', get_template_directory_uri() . '/assets/js/customizer.js', array('customize-preview'), SUBLIME_TECH_VERSION, true);
}
add_action('customize_preview_init', 'sublime_tech_customize_preview_js');
