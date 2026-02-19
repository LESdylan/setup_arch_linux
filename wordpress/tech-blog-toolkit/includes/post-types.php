<?php
/**
 * Custom Post Types — Tech Blog Toolkit
 */

if (!defined('WPINC')) { die; }

add_action('init', 'tbt_register_post_types');
function tbt_register_post_types() {
    register_post_type('tutorial', array(
        'labels' => array(
            'name'               => 'Tutorials',
            'singular_name'      => 'Tutorial',
            'add_new'            => 'Add New Tutorial',
            'add_new_item'       => 'Add New Tutorial',
            'edit_item'          => 'Edit Tutorial',
            'new_item'           => 'New Tutorial',
            'view_item'          => 'View Tutorial',
            'search_items'       => 'Search Tutorials',
            'not_found'          => 'No tutorials found',
            'not_found_in_trash' => 'No tutorials found in trash',
            'menu_name'          => 'Tutorials',
        ),
        'public'       => true,
        'has_archive'  => true,
        'rewrite'      => array('slug' => 'tutorials'),
        'supports'     => array('title', 'editor', 'thumbnail', 'excerpt', 'comments'),
        'menu_icon'    => 'dashicons-welcome-learn-more',
        'show_in_rest' => true,
    ));

    register_taxonomy('tutorial_category', 'tutorial', array(
        'labels' => array(
            'name'          => 'Tutorial Categories',
            'singular_name' => 'Tutorial Category',
            'search_items'  => 'Search Categories',
            'add_new_item'  => 'Add New Category',
            'edit_item'     => 'Edit Category',
        ),
        'hierarchical'  => true,
        'show_in_rest'  => true,
        'rewrite'       => array('slug' => 'tutorial-category'),
    ));
}
