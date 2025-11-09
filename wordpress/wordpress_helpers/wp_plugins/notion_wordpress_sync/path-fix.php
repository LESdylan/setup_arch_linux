<?php
/**
 * Path Fix Tool for Notion WP Sync
 * 
 * This tool helps diagnose path issues and can fix plugin paths
 */

// Basic check to prevent direct access without authentication
if (!isset($_GET['admin_check']) || $_GET['admin_check'] !== 'true') {
    echo "Add ?admin_check=true to the URL to run this tool.";
    exit;
}

// Output basic header and styles
echo '<!DOCTYPE html>
<html>
<head>
    <title>Notion WP Sync Path Fixer</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; line-height: 1.6; }
        h1, h2 { color: #333; }
        .info { background: #f5f5f5; padding: 15px; margin-bottom: 15px; border-left: 4px solid #0073aa; }
        .warning { background: #fff8e5; padding: 15px; border-left: 4px solid #ffb900; margin: 15px 0; }
        .success { background: #dff0d8; padding: 15px; border-left: 4px solid #46b450; margin: 15px 0; }
        code { background: #f1f1f1; padding: 3px 5px; border-radius: 3px; }
        .button { display: inline-block; background: #0073aa; color: white; padding: 8px 15px; text-decoration: none; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Notion WP Sync - Path Fixer</h1>';

// Try to load WordPress
$wp_load_attempts = array(
    // Try all possible paths to wp-load.php
    dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php',  // Standard path
    dirname(dirname(dirname(__FILE__))) . '/wp-load.php',           // Alternative path
    '../../../wp-load.php'                                          // Relative path
);

$wordpress_loaded = false;
foreach ($wp_load_attempts as $path) {
    echo "<p>Trying to load WordPress from: <code>" . htmlspecialchars($path) . "</code></p>";
    if (file_exists($path)) {
        require_once($path);
        $wordpress_loaded = true;
        echo "<p class='success'>✅ Successfully loaded WordPress from <code>" . htmlspecialchars($path) . "</code></p>";
        break;
    } else {
        echo "<p>❌ File not found at <code>" . htmlspecialchars($path) . "</code></p>";
    }
}

if (!$wordpress_loaded) {
    echo '<div class="warning">
        <h2>WordPress Not Loaded</h2>
        <p>Could not find WordPress installation. Please check your server paths.</p>
        <p>Current file location: <code>' . __FILE__ . '</code></p>
    </div>';
    exit;
}

// Security: Verify user is an admin
if (!current_user_can('administrator')) {
    echo '<div class="warning">
        <h2>Access Denied</h2>
        <p>You must be a WordPress administrator to use this tool.</p>
    </div>';
    exit;
}

// Display server paths
echo '<div class="info">
    <h2>Server Path Information</h2>
    <table style="width:100%; border-collapse: collapse;">
        <tr style="background: #eee;"><th style="text-align:left; padding: 8px;">Path Variable</th><th style="text-align:left; padding: 8px;">Value</th></tr>
        <tr><td style="padding: 8px;"><code>ABSPATH</code></td><td style="padding: 8px;"><code>' . ABSPATH . '</code></td></tr>
        <tr><td style="padding: 8px;"><code>WP_PLUGIN_DIR</code></td><td style="padding: 8px;"><code>' . WP_PLUGIN_DIR . '</code></td></tr>
        <tr><td style="padding: 8px;"><code>WP_CONTENT_DIR</code></td><td style="padding: 8px;"><code>' . WP_CONTENT_DIR . '</code></td></tr>
        <tr><td style="padding: 8px;"><code>plugins_url()</code></td><td style="padding: 8px;"><code>' . plugins_url('', __FILE__) . '</code></td></tr>
        <tr><td style="padding: 8px;"><code>plugin_dir_path()</code></td><td style="padding: 8px;"><code>' . plugin_dir_path(__FILE__) . '</code></td></tr>
        <tr><td style="padding: 8px;"><code>__FILE__</code></td><td style="padding: 8px;"><code>' . __FILE__ . '</code></td></tr>
        <tr><td style="padding: 8px;"><code>dirname(__FILE__)</code></td><td style="padding: 8px;"><code>' . dirname(__FILE__) . '</code></td></tr>
    </table>
</div>';

// Display info about the Notion WP Sync plugin
if (defined('NOTION_WP_SYNC_PLUGIN_DIR') && defined('NOTION_WP_SYNC_PLUGIN_URL')) {
    echo '<div class="info">
        <h2>Notion WP Sync Plugin Information</h2>
        <table style="width:100%; border-collapse: collapse;">
            <tr style="background: #eee;"><th style="text-align:left; padding: 8px;">Constant</th><th style="text-align:left; padding: 8px;">Value</th></tr>
            <tr><td style="padding: 8px;"><code>NOTION_WP_SYNC_PLUGIN_DIR</code></td><td style="padding: 8px;"><code>' . NOTION_WP_SYNC_PLUGIN_DIR . '</code></td></tr>
            <tr><td style="padding: 8px;"><code>NOTION_WP_SYNC_PLUGIN_URL</code></td><td style="padding: 8px;"><code>' . NOTION_WP_SYNC_PLUGIN_URL . '</code></td></tr>
            <tr><td style="padding: 8px;"><code>NOTION_WP_SYNC_VERSION</code></td><td style="padding: 8px;"><code>' . (defined('NOTION_WP_SYNC_VERSION') ? NOTION_WP_SYNC_VERSION : 'Not defined') . '</code></td></tr>
        </table>
    </div>';
}

// Find diagnostic files and test their URLs
$test_files = array('mermaid-test.php', 'network-test.php', 'notion-test.php');
echo '<div class="info">
    <h2>Test Files</h2>
    <p>These are the diagnostic files in your plugin. Click the links to test if they\'re accessible:</p>
    <table style="width:100%; border-collapse: collapse;">
        <tr style="background: #eee;">
            <th style="text-align:left; padding: 8px;">File</th>
            <th style="text-align:left; padding: 8px;">Exists</th>
            <th style="text-align:left; padding: 8px;">Test Link</th>
        </tr>';

foreach ($test_files as $file) {
    $file_path = dirname(__FILE__) . '/' . $file;
    $file_exists = file_exists($file_path);
    $file_url = plugins_url($file, dirname(__FILE__));
    
    echo '<tr>
        <td style="padding: 8px;"><code>' . $file . '</code></td>
        <td style="padding: 8px;">' . ($file_exists ? '✅ Yes' : '❌ No') . '</td>
        <td style="padding: 8px;">
            <a href="' . $file_url . '?admin_check=true" target="_blank" class="button">Test Access</a>
        </td>
    </tr>';
}

echo '</table>
</div>';

// Instructions for fixing paths
echo '<div class="info">
    <h2>How to Fix Path Issues</h2>
    <p>If you\'re experiencing path-related issues with the plugin, try these fixes:</p>
    <ol>
        <li>Make sure your plugin is correctly installed at: <code>' . WP_PLUGIN_DIR . '/notion_wordpress_sync/</code></li>
        <li>Check that all plugin files have the correct permissions (usually 644 for files, 755 for directories)</li>
        <li>If you see paths referencing <code>/home/dlesieur/wp_plugins/notion_wordpress_sync/</code>, these need to be 
            updated to match your server path: <code>' . WP_PLUGIN_DIR . '/notion_wordpress_sync/</code></li>
        <li>For security blocks with direct file access, add the suggested .htaccess rules from the Diagnostic Tools page</li>
    </ol>
</div>';

echo '</body></html>';
