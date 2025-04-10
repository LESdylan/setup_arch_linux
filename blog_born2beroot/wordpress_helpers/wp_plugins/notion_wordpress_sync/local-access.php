<?php
/**
 * Local Network Access Helper
 *
 * Provides direct access to diagnostic tools from your local network
 */

// Detect if we're on local network
$is_localhost = false;
$local_ips = array('127.0.0.1', '::1', '192.168.1.133');

if (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], $local_ips)) {
    $is_localhost = true;
}

if (!$is_localhost) {
    http_response_code(403);
    echo "This tool is only accessible from local network IP addresses.";
    exit;
}

// Basic information about the server
$request_uri = $_SERVER['REQUEST_URI'] ?? 'Unknown';
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown';
$host = $_SERVER['HTTP_HOST'] ?? 'Unknown';
$remote_addr = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// Construct base URLs - use the current host instead of hardcoding
$plugin_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $host . '/wp-content/plugins/notion_wordpress_sync/';
$wp_admin_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $host . '/wp-admin/';

// Header with basic styling
echo '<!DOCTYPE html>
<html>
<head>
    <title>Local Network Access - Notion WP Sync</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; line-height: 1.6; }
        h1, h2 { color: #2c3e50; }
        .section { background: #f8f9fa; border-left: 4px solid #3498db; padding: 15px; margin-bottom: 15px; }
        .tool-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin: 20px 0; }
        .tool-link { display: block; padding: 10px; background: #f1f9ff; border: 1px solid #bae1ff; text-align: center; text-decoration: none; color: #0073aa; border-radius: 4px; }
        .tool-link:hover { background: #e1f3ff; }
        .info { background: #e7f5ff; border-left: 4px solid #3498db; padding: 10px 15px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        table th { background-color: #f9f9f9; }
    </style>
</head>
<body>
    <h1>Local Network Access - Notion WP Sync</h1>
    
    <div class="info">
        <p>This page provides direct access to Notion WP Sync diagnostic tools from your local network.</p>
        <p><strong>Your IP:</strong> ' . $remote_addr . '</p>
    </div>
    
    <div class="section">
        <h2>Direct Access Tools</h2>
        <div class="tool-grid">
            <a href="' . $plugin_url . 'mermaid-test.php?admin_check=true" class="tool-link">Mermaid Tester</a>
            <a href="' . $plugin_url . 'network-test.php?admin_check=true" class="tool-link">Network Test</a>
            <a href="' . $plugin_url . 'test-page.php?admin_check=true" class="tool-link">Test Page</a>
            <a href="' . $plugin_url . 'path-fix.php?admin_check=true" class="tool-link">Path Fixer</a>
            <a href="' . $plugin_url . 'localhost-helper.php?admin_check=true" class="tool-link">Localhost Helper</a>
            <a href="' . $plugin_url . 'notion-wp-sync-debug.php?admin_check=true" class="tool-link">Debug Helper</a>
            <a href="' . $plugin_url . 'notion-test.php?admin_check=true" class="tool-link">Plugin Test</a>
        </div>
    </div>
    
    <div class="section">
        <h2>WordPress Admin</h2>
        <div class="tool-grid">
            <a href="' . $wp_admin_url . 'admin.php?page=notion-wp-sync" class="tool-link">Plugin Settings</a>
            <a href="' . $wp_admin_url . 'admin.php?page=notion-wp-sync&tab=mappings" class="tool-link">Content Mappings</a>
            <a href="' . $wp_admin_url . 'admin.php?page=notion-wp-sync&tab=logs" class="tool-link">Sync Logs</a>
            <a href="' . $wp_admin_url . 'admin.php?page=notion-wp-sync&tab=debug" class="tool-link">System Check</a>
        </div>
    </div>
    
    <div class="section">
        <h2>Fix Access Issues</h2>
        <p>If you\'re having trouble accessing the tools directly:</p>
        <ol>
            <li>Make sure your <code>.htaccess</code> file allows access to the diagnostic tools.</li>
            <li>Try adding this to your site root <code>.htaccess</code> file:</li>
        </ol>
        <pre style="background:#f5f5f5; padding:10px; overflow:auto;">
# Allow access to notion-wordpress-sync test files
&lt;Files ~ "^(notion-test|mermaid-test|network-test|localhost-helper|path-fix|test-page)\.php$"&gt;
    &lt;RequireAny&gt;
        Require ip 127.0.0.1
        Require ip ::1
        Require ip 192.168.1.133
    &lt;/RequireAny&gt;
&lt;/Files&gt;
</pre>
    </div>
    
    <div class="section">
        <h2>Server Environment</h2>
        <table>
            <tr>
                <th>Server Software</th>
                <td>' . htmlspecialchars($server_software) . '</td>
            </tr>
            <tr>
                <th>Document Root</th>
                <td>' . htmlspecialchars($document_root) . '</td>
            </tr>
            <tr>
                <th>Host</th>
                <td>' . htmlspecialchars($host) . '</td>
            </tr>
            <tr>
                <th>Remote Address</th>
                <td>' . htmlspecialchars($remote_addr) . '</td>
            </tr>
            <tr>
                <th>Request URI</th>
                <td>' . htmlspecialchars($request_uri) . '</td>
            </tr>
            <tr>
                <th>PHP Version</th>
                <td>' . phpversion() . '</td>
            </tr>
        </table>
    </div>
</body>
</html>';
