<?php
/**
 * Plugin Name: Notion WP Sync
 * Plugin URI: https://example.com/notion-wp-sync
 * Description: Sync content from Notion to WordPress.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: notion-wp-sync
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('NOTION_WP_SYNC_VERSION', '1.0.0');
define('NOTION_WP_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NOTION_WP_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Error logging function
 */
function notion_wp_sync_log_error($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Notion WP Sync] ' . $message);
    }
    
    // Also store in the database for the admin to see
    $log = get_option('notion_wp_sync_error_log', array());
    $log[] = array(
        'time' => current_time('mysql'),
        'message' => $message
    );
    
    // Keep only the last 100
    if (count($log) > 100) {
        $log = array_slice($log, -100);
    }
    
    update_option('notion_wp_sync_error_log', $log);
}

/**
 * Load required files safely with proper error handling
 */
function notion_wp_sync_load_files() {
    $files = array(
        'includes/class-db.php',
        'includes/class-notion-api.php',
        'includes/class-content-sync.php',
        'includes/class-debug.php',
        'includes/admin-helper.php'
    );
    
    $load_success = true;
    
    // First register an autoloader to help with class dependencies
    spl_autoload_register(function($class_name) {
        // Only handle our plugin's classes
        if (strpos($class_name, 'Notion_WP_Sync_') === 0) {
            $class_file = str_replace('_', '-', strtolower($class_name));
            $class_file = str_replace('notion-wp-sync-', '', $class_file);
            $path = NOTION_WP_SYNC_PLUGIN_DIR . 'includes/class-' . $class_file . '.php';
            
            if (file_exists($path)) {
                require_once $path;
            }
        }
    });
    
    // Now load the core files
    foreach ($files as $file) {
        $path = NOTION_WP_SYNC_PLUGIN_DIR . $file;
        if (file_exists($path)) {
            try {
                require_once $path;
            } catch (Exception $e) {
                notion_wp_sync_log_error("Error loading file $file: " . $e->getMessage());
                $load_success = false;
            }
        } else {
            notion_wp_sync_log_error("Required file not found: $file");
            $load_success = false;
        }
    }
    
    // Create the formatters directory if it doesn't exist
    $formatter_dir = NOTION_WP_SYNC_PLUGIN_DIR . 'includes/formatters';
    if (!is_dir($formatter_dir)) {
        if (wp_mkdir_p($formatter_dir)) {
            notion_wp_sync_log_error("Created formatters directory");
        } else {
            notion_wp_sync_log_error("Failed to create formatters directory");
            $load_success = false;
        }
    }
    
    // Check if the mermaid formatter exists, create it if not
    $formatter_path = $formatter_dir . '/class-mermaid-formatter.php';
    if (!file_exists($formatter_path)) {
        $formatter_content = '<?php
/**
 * Mermaid Diagram Formatter
 */
class Notion_WP_Sync_Mermaid_Formatter {
    public static function format($mermaid_code) {
        $mermaid_code = trim($mermaid_code);
        return \'<div class="mermaid">\' . $mermaid_code . \'</div>\';
    }
}';
        if (file_put_contents($formatter_path, $formatter_content)) {
            notion_wp_sync_log_error("Created mermaid formatter file");
        } else {
            notion_wp_sync_log_error("Failed to create mermaid formatter file");
            $load_success = false;
        }
    }
    
    // Only load troubleshoot.php if it exists
    $troubleshoot_path = NOTION_WP_SYNC_PLUGIN_DIR . 'troubleshoot.php';
    if (file_exists($troubleshoot_path)) {
        try {
            require_once $troubleshoot_path;
        } catch (Exception $e) {
            notion_wp_sync_log_error("Error loading troubleshoot.php: " . $e->getMessage());
        }
    }
    
    return $load_success;
}

// Load core files and handle potential fatal errors
try {
    notion_wp_sync_load_files();
} catch (Exception $e) {
    notion_wp_sync_log_error("Critical error loading plugin files: " . $e->getMessage());
    // Don't let this error break the site
    return;
}

// Activation hook
register_activation_hook(__FILE__, 'notion_wp_sync_activate');

// Deactivation hook
register_deactivation_hook(__FILE__, 'notion_wp_sync_deactivate');

/**
 * Plugin activation
 */
function notion_wp_sync_activate() {
    try {
        // Create tables and default options if the DB class is available
        if (class_exists('Notion_WP_Sync_DB')) {
            $db = new Notion_WP_Sync_DB();
            $db->create_tables();
        } else {
            notion_wp_sync_log_error('Activation error: DB class not found');
        }
        
        // Set default options
        add_option('notion_wp_sync_api_key', '');
        add_option('notion_wp_sync_sync_interval', 'hourly');
        add_option('notion_wp_sync_last_sync', '');
        add_option('notion_wp_sync_error_log', array());
        
        // Add activation notice
        set_transient('notion_wp_sync_activated', true, 60);
    } catch (Exception $e) {
        notion_wp_sync_log_error('Activation error: ' . $e->getMessage());
        // Re-throw to let WordPress know activation failed
        throw $e;
    }
}

/**
 * Plugin deactivation
 */
function notion_wp_sync_deactivate() {
    wp_clear_scheduled_hook('notion_wp_sync_cron_hook');
}

/**
 * Show activation notice
 */
function notion_wp_sync_activation_notice() {
    if (get_transient('notion_wp_sync_activated')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong>Notion WP Sync</strong> plugin has been activated. 
                <a href="<?php echo admin_url('admin.php?page=notion-wp-sync'); ?>">Configure settings</a> to get started.
            </p>
        </div>
        <?php
        delete_transient('notion_wp_sync_activated');
    }
}
add_action('admin_notices', 'notion_wp_sync_activation_notice');

/**
 * Add action to hook admin menu registration
 */
add_action('admin_menu', 'notion_wp_sync_add_admin_menu');

/**
 * Add emergency diagnostics page
 */
function notion_wp_sync_add_emergency_page() {
    add_menu_page(
        'Notion WP Emergency',
        'Notion Emergency',
        'administrator',
        'notion-wp-emergency',
        'notion_wp_sync_emergency_diagnostics',
        'dashicons-warning',
        99
    );
    
    // Add a direct quick start link
    add_submenu_page(
        'notion-wp-sync',  // Parent slug
        'Quick Start Guide',  // Page title
        '⭐ Quick Start Guide',  // Menu title with star for visibility
        'administrator',  // Capability
        'notion-wp-sync-guide',  // Menu slug
        'notion_wp_sync_show_guide'  // Function
    );
    
    // Add debug submenu directly under the main menu
    add_submenu_page(
        'notion-wp-sync',       // Parent slug - this is important!
        'System Debug',         // Page title
        'System Debug',         // Menu title
        'administrator',        // Capability
        'notion-wp-sync-debug', // Menu slug
        'notion_wp_sync_debug_page' // Function
    );
}
add_action('admin_menu', 'notion_wp_sync_add_emergency_page');

/**
 * Simple debug page function
 */
function notion_wp_sync_debug_page() {
    // Check user is an administrator
    if (!current_user_can('administrator')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    // Include the debug page content from the admin-helper.php if available
    if (class_exists('Notion_WP_Sync_Admin_Helper') && method_exists('Notion_WP_Sync_Admin_Helper', 'render_tools_page')) {
        Notion_WP_Sync_Admin_Helper::render_tools_page();
    } else {
        // Fallback debug content
        echo '<div class="wrap">';
        echo '<h1>Notion WP Sync Debug</h1>';
        echo '<p>The debug tools could not be loaded. Please check your installation.</p>';
        echo '</div>';
    }
}

/**
 * Quick start guide page
 */
function notion_wp_sync_show_guide() {
    // Check if quick-start.php exists and include it
    $quick_start_path = NOTION_WP_SYNC_PLUGIN_DIR . 'quick-start.php';
    if (file_exists($quick_start_path)) {
        include_once $quick_start_path;
        // Call the function if it exists
        if (function_exists('notion_wp_sync_show_quick_start')) {
            notion_wp_sync_show_quick_start();
        }
    } else {
        ?>
        <div class="wrap">
            <h1>Quick Start Guide</h1>
            <div class="notice notice-warning">
                <p>The quick start guide file could not be found. Please contact plugin support.</p>
            </div>
            
            <!-- Basic guidance -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; margin-top: 20px;">
                <h2>Basic Steps to Sync Notion Content</h2>
                <ol>
                    <li>Create a Notion integration at <a href="https://www.notion.so/my-integrations" target="_blank">https://www.notion.so/my-integrations</a></li>
                    <li>Copy your API key from the integration</li>
                    <li>Share your Notion pages with this integration (very important!)</li>
                    <li>Enter your API key in the Settings tab</li>
                    <li>Add your Notion page IDs in the Content Mappings tab</li>
                    <li>Click "Sync Now" in the Settings tab</li>
                </ol>
            </div>
        </div>
        <?php
    }
}

/**
 * Emergency diagnostics content
 */
function notion_wp_sync_emergency_diagnostics() {
    // Check user is an administrator
    if (!current_user_can('administrator')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    // Detect if we're in a local environment
    $is_local = (strpos($_SERVER['SERVER_NAME'], 'localhost') !== false || 
                 in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1', '192.168.1.133')));
    
    ?>
    <div class="wrap">
        <h1>Notion WP Sync Emergency Diagnostics</h1>
        
        <div style="background:#fff; padding:20px; border:1px solid #ccc;">
            <h2>Plugin Files Check</h2>
            <?php
            $plugin_dir = NOTION_WP_SYNC_PLUGIN_DIR;
            $critical_files = array(
                'notion-wp-sync.php',
                'includes/class-db.php',
                'includes/class-notion-api.php',
                'includes/class-content-sync.php',
                'includes/class-debug.php',
                'admin/admin-page.php',
            );
            
            foreach ($critical_files as $file) {
                $path = $plugin_dir . $file;
                if (file_exists($path)) {
                    $size = filesize($path);
                    echo "<p>✅ File exists: $file (Size: $size bytes)</p>";
                } else {
                    echo "<p style='color:red;'>❌ File missing: $file</p>";
                }
            }
            ?>
            
            <h2>PHP Environment</h2>
            <ul>
                <li>PHP Version: <?php echo phpversion(); ?></li>
                <li>Memory Limit: <?php echo ini_get('memory_limit'); ?></li>
                <li>Max Execution Time: <?php echo ini_get('max_execution_time'); ?></li>
            </ul>
            
            <h2>WordPress Information</h2>
            <ul>
                <li>WordPress Version: <?php echo get_bloginfo('version'); ?></li>
                <li>Plugin Directory: <?php echo NOTION_WP_SYNC_PLUGIN_DIR; ?></li>
                <li>Is Debug Mode: <?php echo defined('WP_DEBUG') && WP_DEBUG ? 'Yes' : 'No'; ?></li>
            </ul>
            
            <h2>Fix Critical Error</h2>
            <?php if ($is_local): ?>
                <p>To fix the critical error in your local environment:</p>
                <ol>
                    <li>Use your code editor to open this file: <code><?php echo NOTION_WP_SYNC_PLUGIN_DIR; ?>notion-wp-sync.php</code></li>
                    <li>Copy the code below and replace the entire contents of that file</li>
                    <li>Save the file and reload your WordPress admin</li>
                </ol>
            <?php else: ?>
                <p>To fix the critical error:</p>
                <ol>
                    <li>Access your WordPress files through FTP or your hosting file manager</li>
                    <li>Find and edit this file: <code><?php echo NOTION_WP_SYNC_PLUGIN_DIR; ?>notion-wp-sync.php</code></li>
                    <li>Replace its contents with the code shown below</li>
                </ol>
            <?php endif; ?>
            
            <h2 id="fixed-code">Fixed Plugin Code</h2>
            <textarea style="width:100%; height:300px; font-family:monospace; white-space:pre; overflow:auto;"><?php echo htmlspecialchars(file_get_contents(NOTION_WP_SYNC_PLUGIN_DIR . 'notion-wp-sync.php')); ?></textarea>
            
            <?php if ($is_local): ?>
            <div style="margin-top:20px; padding:15px; background:#f0f8ff; border-left:4px solid #0073aa;">
                <h2>Quick Local Solution</h2>
                <p>Since you're running in a local environment, you can also try:</p>
                <ol>
                    <li>Deactivating and reactivating the plugin from the Plugins page</li>
                    <li>Running <code>notion-test.php</code> directly to create missing files: 
                        <a href="<?php echo plugins_url('notion-test.php?admin_check=true', __FILE__); ?>" class="button">Run Auto-Fix</a>
                    </li>
                    <li>Using the local access page: 
                        <a href="<?php echo plugins_url('local-access.php', __FILE__); ?>" class="button">Local Access Tools</a>
                    </li>
                </ol>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Initialization function to load all plugin components
 */
function notion_wp_sync_init() {
    // Load admin interface
    require_once NOTION_WP_SYNC_PLUGIN_DIR . 'admin/admin-page.php';
    
    // Re-enable other functionality that was disabled for debugging
    notion_wp_sync_setup_schedule();
}
add_action('init', 'notion_wp_sync_init');

/**
 * Set up scheduled sync
 */
function notion_wp_sync_setup_schedule() {
    $interval = get_option('notion_wp_sync_sync_interval', 'hourly');
    
    if (!wp_next_scheduled('notion_wp_sync_cron_hook')) {
        wp_schedule_event(time(), $interval, 'notion_wp_sync_cron_hook');
    }
}

/**
 * Run scheduled sync
 */
function notion_wp_sync_cron_exec() {
    try {
        // Only proceed if the Content_Sync class exists
        if (!class_exists('Notion_WP_Sync_Content_Sync')) {
            notion_wp_sync_log_error('Content_Sync class not found during scheduled sync');
            return;
        }
        $sync = new Notion_WP_Sync_Content_Sync();
        $result = $sync->sync_all();
        
        update_option('notion_wp_sync_last_sync', current_time('mysql'));
        
        if (!$result) {
            notion_wp_sync_log_error('Scheduled sync failed');
        }
    } catch (Exception $e) {
        notion_wp_sync_log_error('Scheduled sync error: ' . $e->getMessage());
    }
}
add_action('notion_wp_sync_cron_hook', 'notion_wp_sync_cron_exec');

/**
 * Ajax handler for manual sync
 */
function notion_wp_sync_manual_sync() {
    check_ajax_referer('notion_wp_sync_nonce', 'nonce');
    
    // Only allow administrators to perform manual sync
    if (!current_user_can('administrator')) {
        wp_send_json_error('Permission denied. Administrator access required.');
    }
    
    try {
        $sync = new Notion_WP_Sync_Content_Sync();
        $result = $sync->sync_all();
        
        update_option('notion_wp_sync_last_sync', current_time('mysql'));
        
        if ($result) {
            wp_send_json_success('Sync completed successfully');
        } else {
            wp_send_json_error('Sync failed');
        }
    } catch (Exception $e) {
        wp_send_json_error('Sync error: ' . $e->getMessage());
    }
}
add_action('wp_ajax_notion_wp_sync_manual_sync', 'notion_wp_sync_manual_sync');

/**
 * Ajax handler for testing API connection
 */
function notion_wp_sync_test_connection() {
    check_ajax_referer('notion_wp_sync_nonce', 'nonce');
    
    // Only allow administrators to test connection
    if (!current_user_can('administrator')) {
        wp_send_json_error('Permission denied. Administrator access required.');
    }
    
    try {
        $api = new Notion_WP_Sync_API();
        $result = $api->test_connection();
        
        if ($result) {
            wp_send_json_success('Connection successful');
        } else {
            wp_send_json_error('Connection failed. Please check your API key and make sure your integration has access to the content.');
        }
    } catch (Exception $e) {
        wp_send_json_error('Connection error: ' . $e->getMessage());
    }
}
add_action('wp_ajax_notion_wp_test_connection', 'notion_wp_sync_test_connection');

/**
 * Ajax handler for running system check
 */
function notion_wp_sync_run_system_check() {
    check_ajax_referer('notion_wp_sync_nonce', 'nonce');
    
    // Only allow administrators to run system check
    if (!current_user_can('administrator')) {
        wp_send_json_error('Permission denied. Administrator access required.');
    }
    
    try {
        if (class_exists('Notion_WP_Sync_Debug')) {
            $results = Notion_WP_Sync_Debug::run_system_check();
            wp_send_json_success($results);
        } else {
            wp_send_json_error('Debug class not found');
        }
    } catch (Exception $e) {
        wp_send_json_error('System check error: ' . $e->getMessage());
    }
}
add_action('wp_ajax_notion_wp_sync_run_system_check', 'notion_wp_sync_run_system_check');

/**
 * Enqueue Mermaid JS and other formatting libraries on the frontend
 */
function notion_wp_sync_enqueue_frontend_scripts() {
    // Only enqueue if the current post might have Notion content
    global $post;
    if (is_singular() && $post) {
        // Check for both possible meta key names
        $notion_id = get_post_meta($post->ID, '_notion_page_id', true);
        if (empty($notion_id)) {
            $notion_id = get_post_meta($post->ID, '_notion_id', true);
        }
        
        if (!empty($notion_id)) {
            // Enqueue Mermaid JS library - Update to the latest version
            wp_enqueue_script(
                'mermaid-js',
                'https://cdn.jsdelivr.net/npm/mermaid@10.9.3/dist/mermaid.min.js', // Update to 10.9.3
                array(),
                '10.9.3', // Update version number
                true
            );
            
            // Initialize Mermaid with improved configuration
            wp_add_inline_script('mermaid-js', '
                document.addEventListener("DOMContentLoaded", function() {
                    if (typeof mermaid !== "undefined") {
                        mermaid.initialize({ 
                            startOnLoad: true,
                            theme: "default",
                            securityLevel: "loose",
                            flowchart: {
                                useMaxWidth: true,
                                htmlLabels: true,
                                curve: "basis"
                            },
                            sequence: {
                                useMaxWidth: true
                            }
                        });
                    }
                });
            ');
            
            // Add improved styling for Mermaid diagrams
            wp_add_inline_style('wp-block-library', '
                /* Mermaid diagrams */
                .mermaid-container {
                    background-color: #f8f9fa;
                    padding: 20px;
                    border-radius: 5px;
                    margin: 20px 0;
                    overflow: auto;
                }
                
                .mermaid {
                    text-align: center;
                    max-width: 100%;
                }
            ');
        }
    }
}
add_action('wp_enqueue_scripts', 'notion_wp_sync_enqueue_frontend_scripts');
