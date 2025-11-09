<?php
/**
 * Localhost Testing Helper
 * 
 * This file helps with testing when using localhost port forwarding.
 */

// Basic security
if (!isset($_GET['admin_check']) || $_GET['admin_check'] !== 'true') {
    echo "Add ?admin_check=true to the URL to use this helper";
    exit;
}

// Show all errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if we're on localhost
$is_localhost = (isset($_SERVER['REMOTE_ADDR']) && ($_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '::1'));

// Try to load WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
$wordpress_loaded = false;

if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
    $wordpress_loaded = true;
} else {
    echo "<p>WordPress not found at expected path: $wp_load_path</p>";
    // Try alternative paths
    $alt_paths = [
        '../../../wp-load.php',
        dirname(dirname(dirname(__FILE__))) . '/wp-load.php'
    ];
    
    foreach ($alt_paths as $path) {
        echo "<p>Trying alternative path: $path</p>";
        if (file_exists($path)) {
            require_once($path);
            $wordpress_loaded = true;
            echo "<p>WordPress loaded from: $path</p>";
            break;
        }
    }
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Localhost Helper for Notion WP Sync</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; line-height: 1.6; }
        h1, h2 { color: #2c3e50; }
        .card { background: #f8f9fa; border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 20px; }
        .warning { background: #fff8e5; border-left: 4px solid #ffb900; }
        .success { background: #e7f7e3; border-left: 4px solid #46b450; }
        .error { background: #fbeaea; border-left: 4px solid #dc3232; }
        code { background: #f1f1f1; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
        a.button { display: inline-block; background: #0073aa; color: white; padding: 8px 12px; text-decoration: none; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Localhost Helper for Notion WP Sync</h1>
    
    <div class='card " . ($is_localhost ? "success" : "warning") . "'>
        <h2>Environment Check</h2>
        <p><strong>Localhost status:</strong> " . ($is_localhost ? "Running on localhost ✓" : "Not running on localhost ✗") . "</p>
        <p><strong>WordPress:</strong> " . ($wordpress_loaded ? "Loaded successfully ✓" : "Failed to load ✗") . "</p>
        <p><strong>IP Address:</strong> " . $_SERVER['REMOTE_ADDR'] . "</p>
        <p><strong>Current URL:</strong> " . (!empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'Unknown') . "</p>
    </div>
    
    <div class='card'>
        <h2>Direct Access Tools</h2>
        <p>These links provide direct access to diagnostic tools via localhost:</p>
        <ul>
            <li><a href='mermaid-test.php?admin_check=true' class='button'>Mermaid Diagram Tester</a></li>
            <li><a href='network-test.php?admin_check=true' class='button'>Network Test</a></li>
            <li><a href='path-fix.php?admin_check=true' class='button'>Path Fix Tool</a></li>
            <li><a href='test-page.php?admin_check=true' class='button'>Test Page</a></li>
        </ul>
    </div>";

if ($wordpress_loaded) {
    echo "
    <div class='card success'>
        <h2>WordPress Plugin Links</h2>
        <p>These links access the plugin through the WordPress admin:</p>
        <ul>
            <li><a href='" . admin_url('admin.php?page=notion-wp-sync') . "' class='button'>Plugin Settings</a></li>
            <li><a href='" . admin_url('admin.php?page=notion-wp-sync&tab=mappings') . "' class='button'>Content Mappings</a></li>
            <li><a href='" . admin_url('tools.php?page=notion-wp-sync-debug') . "' class='button'>ID Troubleshooter</a></li>
        </ul>
    </div>";
    
    echo "
    <div class='card'>
        <h2>WordPress Environment</h2>
        <p><strong>WordPress Version:</strong> " . get_bloginfo('version') . "</p>
        <p><strong>WordPress URL:</strong> " . get_bloginfo('url') . "</p>
        <p><strong>WordPress Home:</strong> " . get_home_url() . "</p>
        <p><strong>WordPress Admin:</strong> " . admin_url() . "</p>
        <p><strong>Plugin Directory:</strong> " . WP_PLUGIN_DIR . "</p>
        <p><strong>Plugin URL:</strong> " . plugins_url() . "</p>
    </div>";
}

// Check if our Mermaid formatter exists
$formatter_path = dirname(__FILE__) . '/includes/formatters/class-mermaid-formatter.php';
echo "
<div class='card " . (file_exists($formatter_path) ? "success" : "error") . "'>
    <h2>Mermaid Formatter Check</h2>
    <p><strong>Formatter Path:</strong> " . $formatter_path . "</p>
    <p><strong>Status:</strong> " . (file_exists($formatter_path) ? "File exists ✓" : "File missing ✗") . "</p>";

if (!file_exists($formatter_path)) {
    echo "<p>Would you like to create the missing file?</p>
    <form method='post'>
        <input type='hidden' name='action' value='create_formatter'>
        <button type='submit'>Create Mermaid Formatter</button>
    </form>";
}
echo "</div>";

// Handle file creation if requested
if (isset($_POST['action']) && $_POST['action'] === 'create_formatter') {
    $formatter_dir = dirname($formatter_path);
    if (!is_dir($formatter_dir)) {
        mkdir($formatter_dir, 0755, true);
    }
    
    $formatter_content = '<?php
/**
 * Mermaid Diagram Formatter
 * 
 * Properly formats Mermaid diagrams from Notion to WordPress
 */

class Notion_WP_Sync_Mermaid_Formatter {
    
    /**
     * Format a Mermaid diagram
     * 
     * @param string $mermaid_code The raw Mermaid diagram code
     * @return string Formatted Mermaid diagram ready for WordPress
     */
    public static function format($mermaid_code) {
        // Clean up the code
        $mermaid_code = trim($mermaid_code);
        
        // Wrap in proper container with necessary attributes
        $formatted = \'<div class="mermaid">\' . $mermaid_code . \'</div>\';
        
        return $formatted;
    }
}';
    
    if (file_put_contents($formatter_path, $formatter_content)) {
        echo "<div class='card success'>
            <h2>File Created Successfully</h2>
            <p>The mermaid formatter file has been created at: $formatter_path</p>
            <p><a href='mermaid-test.php?admin_check=true' class='button'>Test the Mermaid Formatter</a></p>
        </div>";
    } else {
        echo "<div class='card error'>
            <h2>File Creation Failed</h2>
            <p>Failed to create the file. Please check permissions.</p>
        </div>";
    }
}

echo "</body></html>";
