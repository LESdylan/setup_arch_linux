<?php
/**
 * Direct access helper - redirects to the proper WordPress admin page
 */

// Basic security
if (!isset($_GET['admin_check']) || $_GET['admin_check'] !== 'true') {
    // Try to load WordPress
    $wp_load_path = dirname(dirname(dirname(__FILE__))) . '/wp-load.php';
    
    if (file_exists($wp_load_path)) {
        require_once($wp_load_path);
        
        // If WordPress loaded successfully, redirect to the proper admin page
        // FIXED: Use admin.php?page= format instead of direct URL
        wp_redirect(admin_url('admin.php?page=notion-wp-sync-debug'));
        exit;
    }
    
    // If WordPress couldn't be loaded, show a helpful message
    echo "<h1>Notion WP Sync - Debug Helper</h1>";
    echo "<p>This file helps you access the debug page. The correct way to access it is:</p>";
    echo "<ol>";
    echo "<li>Log in to your WordPress admin</li>";
    echo "<li>Go to Notion WP Sync → System Debug in the admin menu</li>";
    echo "</ol>";
    echo "<p>Or add <code>?admin_check=true</code> to the URL to see direct access information.</p>";
    exit;
}

// Direct access mode with admin_check=true
echo "<!DOCTYPE html>
<html>
<head>
    <title>Notion WP Sync - Debug Helper</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; line-height: 1.6; }
        h1, h2 { color: #333; }
        .info { background: #f5f5f5; padding: 15px; margin-bottom: 15px; border-left: 4px solid #0073aa; }
        .warning { background: #fff8e5; padding: 15px; border-left: 4px solid #ffb900; margin: 15px 0; }
        code { background: #f1f1f1; padding: 3px 5px; border-radius: 3px; }
        .button { display: inline-block; background: #0073aa; color: white; padding: 8px 15px; text-decoration: none; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Notion WP Sync - Debug Helper</h1>
    
    <div class='warning'>
        <h2>Correct URL Information</h2>
        <p>You're trying to access the debug page directly, which isn't the standard WordPress way.</p>
        <p>The correct URL format in WordPress admin is:</p>
        <ul>
            <li><code>http://yourdomain.com/wp-admin/admin.php?page=notion-wp-sync-debug</code></li>
            <li>Or navigate to: WordPress Admin → Notion WP Sync → System Debug</li>
        </ul>
    </div>
    
    <div class='info'>
        <h2>Alternative Direct Access URLs</h2>
        <p>You can also access other diagnostic tools directly:</p>
        <ul>
            <li>Network Test: <code>http://yourdomain.com/wp-content/plugins/notion_wordpress_sync/network-test.php?admin_check=true</code></li>
            <li>Mermaid Test: <code>http://yourdomain.com/wp-content/plugins/notion_wordpress_sync/mermaid-test.php?admin_check=true</code></li>
        </ul>
    </div>
    
    <p>If you can't access the WordPress admin, try:</p>
    <ol>
        <li>Check that you're logged in as an administrator</li>
        <li>Make sure the plugin is activated in WordPress</li>
        <li>Check your server error logs for PHP errors</li>
    </ol>
</body>
</html>";
