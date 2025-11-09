<?php
/**
 * Plugin Name: Tech Blog Toolkit
 * Plugin URI: https://github.com/LESdylan/tech-blog-toolkit
 * Description: A toolkit for technical blogs with custom post types, code highlighting, and more.
 * Version: 1.0.0
 * Author: LESdylan
 * Author URI: https://example.com
 * License: GPL-2.0+
 * Text Domain: tech-blog-toolkit
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('TBT_VERSION', '1.0.0');
define('TBT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TBT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once TBT_PLUGIN_DIR . 'includes/post-types.php';
require_once TBT_PLUGIN_DIR . 'includes/meta-boxes.php';
require_once TBT_PLUGIN_DIR . 'includes/syntax-highlighter.php';
require_once TBT_PLUGIN_DIR . 'includes/admin-dashboard.php';

// Activation hook
register_activation_hook(__FILE__, 'tbt_activate');
function tbt_activate() {
    // Flush rewrite rules on activation
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'tbt_deactivate');
function tbt_deactivate() {
    // Flush rewrite rules on deactivation
    flush_rewrite_rules();
}

// Add admin menu
add_action('admin_menu', 'tbt_add_admin_menu');
function tbt_add_admin_menu() {
    add_menu_page(
        'Tech Blog Toolkit',
        'Tech Blog',
        'manage_options',
        'tech-blog-toolkit',
        'tbt_admin_page',
        'dashicons-book-alt',
        20
    );
}

// Admin page callback
function tbt_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <div class="welcome-panel">
            <div class="welcome-panel-content">
                <h2>Welcome to Tech Blog Toolkit!</h2>
                <p class="about-description">This plugin enhances your technical blog with specialized features.</p>
                <div class="welcome-panel-column-container">
                    <div class="welcome-panel-column">
                        <h3>Features</h3>
                        <ul>
                            <li>Custom tutorial post type</li>
                            <li>Technical specifications meta boxes</li>
                            <li>Code syntax highlighting</li>
                            <li>Tutorial metrics</li>
                        </ul>
                    </div>
                    <div class="welcome-panel-column">
                        <h3>Getting Started</h3>
                        <p>Go to "Tutorials" in the sidebar to start creating technical content.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
