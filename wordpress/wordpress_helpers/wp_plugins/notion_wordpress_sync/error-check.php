<?php
/*
 * Simple Error Diagnostic for Notion WP Sync
 * 
 * Place this file in your plugin directory and access it directly
 * via browser to diagnose issues.
 */

// Basic security - only allow admin access
if (!isset($_GET['admin_check']) || $_GET['admin_check'] !== 'true') {
    echo "Add ?admin_check=true to the URL to run the diagnostic";
    exit;
}

// Show errors for diagnostic purposes
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Notion WP Sync Error Diagnostic</h1>";

// Check PHP Version
echo "<h2>PHP Environment</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Define constants if not already defined
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(dirname(__FILE__)))) . '/');
}

// Check plugin files
echo "<h2>Plugin Files Check</h2>";
$plugin_dir = __DIR__;
$critical_files = [
    'notion-wp-sync.php',
    'includes/class-db.php',
    'includes/class-notion-api.php',
    'includes/class-content-sync.php',
    'includes/class-debug.php',
    'admin/admin-page.php',
    'troubleshoot.php'
];

foreach ($critical_files as $file) {
    $path = $plugin_dir . '/' . $file;
    if (file_exists($path)) {
        echo "<p>✅ $file exists</p>";
        // Check if file has content
        $size = filesize($path);
        echo "<p>&nbsp;&nbsp;&nbsp;Size: $size bytes</p>";
    } else {
        echo "<p>❌ $file missing</p>";
    }
}

// Instructions for fixing the issue
echo "<h2>How to Fix Critical Error</h2>";
echo "<p>Follow these steps:</p>";
echo "<ol>";
echo "<li>Go to your WordPress admin area</li>";
echo "<li>Navigate to Plugins</li>";
echo "<li>Deactivate the Notion WP Sync plugin</li>";
echo "<li>Access your plugin files through WordPress admin:</li>";
echo "<li>Go to Plugins > Plugin Editor</li>";
echo "<li>Select 'Notion WP Sync' from the dropdown</li>";
echo "<li>Edit the main plugin file (notion-wp-sync.php) to include this code:</li>";
echo "</ol>";

echo "<pre style='background:#f5f5f5;padding:15px;overflow:auto;max-height:300px;'>";
echo htmlspecialchars('<?php
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
if (!defined(\'WPINC\')) {
    die;
}

define(\'NOTION_WP_SYNC_VERSION\', \'1.0.0\');
define(\'NOTION_WP_SYNC_PLUGIN_DIR\', plugin_dir_path(__FILE__));
define(\'NOTION_WP_SYNC_PLUGIN_URL\', plugin_dir_url(__FILE__));

/**
 * Error logging function
 */
function notion_wp_sync_log_error($message) {
    if (defined(\'WP_DEBUG\') && WP_DEBUG) {
        error_log(\'[Notion WP Sync] \' . $message);
    }
    
    // Also store in the database for the admin to see
    $log = get_option(\'notion_wp_sync_error_log\', array());
    $log[] = array(
        \'time\' => current_time(\'mysql\'),
        \'message\' => $message
    );
    
    // Keep only the last 100
    if (count($log) > 100) {
        $log = array_slice($log, -100);
    }
    
    update_option(\'notion_wp_sync_error_log\', $log);
}

/**
 * Load files safely - Updated version with better error handling
 */
function notion_wp_sync_load_files() {
    // List core files to load
    $files = array(
        \'includes/class-db.php\',
        \'includes/class-notion-api.php\',
        \'includes/class-content-sync.php\',
        \'includes/class-debug.php\'
    );
    
    $load_success = true;
    
    // Register class autoloader
    spl_autoload_register(function($class_name) {
        if (strpos($class_name, \'Notion_WP_Sync_\') === 0) {
            $class_file = str_replace(\'_\', \'-\', strtolower($class_name));
            $class_file = str_replace(\'notion-wp-sync-\', \'\', $class_file);
            $path = NOTION_WP_SYNC_PLUGIN_DIR . \'includes/class-\' . $class_file . \'.php\';
            
            if (file_exists($path)) {
                require_once $path;
            }
        }
    });
    
    // Load core files one by one
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
    
    // Load troubleshooting file last
    $troubleshoot_path = NOTION_WP_SYNC_PLUGIN_DIR . \'troubleshoot.php\';
    if (file_exists($troubleshoot_path)) {
        try {
            require_once $troubleshoot_path;
        } catch (Exception $e) {
            notion_wp_sync_log_error("Error loading troubleshoot.php: " . $e->getMessage());
        }
    }
    
    return $load_success;
}

// Load core files with error handling
try {
    notion_wp_sync_load_files();
} catch (Exception $e) {
    notion_wp_sync_log_error("Critical error loading plugin files: " . $e->getMessage());
    return; // Prevent further execution on critical error
}

// Remaining plugin code...
// Include all your other hooks and functions below
');
echo "</pre>";

echo "<p>After making these changes, reactivate the plugin.</p>";
?>
