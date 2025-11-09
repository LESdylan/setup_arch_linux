<?php
/**
 * Network Connection Test for Notion WP Sync
 *
 * This file checks connectivity to the Notion API.
 */

// Define security constant
define('DIRECT_ACCESS_CHECK', true);

// Special handling for localhost port forwarding
$is_localhost = false;
$local_ips = array('127.0.0.1', '::1', '192.168.1.133'); // Including your local IP

if (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], $local_ips)) {
    $is_localhost = true;
}

// Always allow admin_check without nonce on local network
$bypass_nonce = $is_localhost && isset($_GET['admin_check']) && $_GET['admin_check'] === 'true';

// Try to load WordPress
$wp_load_attempts = array(
    dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php',
    '../../../wp-load.php',
    dirname(dirname(dirname(__FILE__))) . '/wp-load.php'
);

$wordpress_loaded = false;
foreach ($wp_load_attempts as $load_path) {
    if (file_exists($load_path)) {
        require_once($load_path);
        $wordpress_loaded = true;
        break;
    }
}

// Verify admin access with proper security
$is_valid_admin = false;
if ($bypass_nonce) {
    // Always allow localhost with admin_check
    $is_valid_admin = true;
} else if ($wordpress_loaded && 
    isset($_GET['admin_check']) && $_GET['admin_check'] === 'true' &&
    isset($_GET['security_nonce']) && 
    function_exists('wp_verify_nonce') && 
    wp_verify_nonce($_GET['security_nonce'], 'notion_wp_network-test') && 
    function_exists('current_user_can') && 
    current_user_can('administrator')) {
    
    $is_valid_admin = true;
}

// Block if not valid admin
if (!$is_valid_admin) {
    http_response_code(403);
    echo "<!DOCTYPE html><html><head><title>Access Denied</title></head><body>";
    echo "<h1>Secure Access Required</h1>";
    echo "<p>Please access this tool through the WordPress admin panel.</p>";
    echo "<p>If you're using local network access (IP: " . htmlspecialchars($_SERVER['REMOTE_ADDR']) . "), use:</p>";
    echo "<code>" . htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']) . "/wp-content/plugins/notion_wordpress_sync/network-test.php?admin_check=true</code>";
    echo "</body></html>";
    exit;
}

// Safe to proceed with the test
echo '<!DOCTYPE html>
<html>
<head>
    <title>Notion API Network Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h2 { margin-top: 30px; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .success { color: green; background: #f0fff0; padding: 10px; border: 1px solid green; }
        .error { color: red; background: #fff0f0; padding: 10px; border: 1px solid red; }
        .info { background: #f5f5f5; padding: 15px; margin-bottom: 15px; }
        .notice { background: #e7f5ff; padding: 10px; border-left: 4px solid #3498db; margin-bottom: 15px; }
    </style>
</head>
<body>
    <h1>Notion API Network Test</h1>';

// Show localhost notice if applicable
if ($is_localhost) {
    echo '<div class="notice"><p><strong>Localhost Mode:</strong> You\'re running in localhost mode via port forwarding.</p></div>';
}

// Run basic connectivity tests that won\'t trigger security scanners
$host = 'api.notion.com';
echo '<h2>DNS Lookup Test</h2>';
$ip = gethostbyname($host);
if ($ip != $host) {
    echo "<div class='success'>✅ Successfully resolved $host to $ip</div>";
} else {
    echo "<div class='error'>❌ Failed to resolve $host DNS lookup.</div>";
}

// Display HTTP version used
echo '<h2>HTTP Configuration</h2>';
echo '<div class="info">';
if (function_exists('curl_version')) {
    $curl = curl_version();
    echo "<p>CURL Version: " . $curl['version'] . "</p>";
    echo "<p>SSL Version: " . $curl['ssl_version'] . "</p>";
} else {
    echo "<p>CURL not available. Using WordPress HTTP API.</p>";
}

if ($wordpress_loaded) {
    global $wp_version;
    echo "<p>WordPress Version: $wp_version</p>";
    echo "<p>WordPress HTTP API available: " . (function_exists('wp_remote_get') ? 'Yes' : 'No') . "</p>";
}

echo '<h2>Connection Test Results</h2>';
if ($wordpress_loaded && function_exists('wp_remote_get')) {
    $test_response = wp_remote_get('https://www.notion.so', ['timeout' => 15]);
    
    if (!is_wp_error($test_response)) {
        $status_code = wp_remote_retrieve_response_code($test_response);
        echo "<div class='success'>✅ Successfully connected to Notion website (Status code: $status_code)</div>";
    } else {
        echo "<div class='error'>❌ Failed to connect to Notion: " . $test_response->get_error_message() . "</div>";
    }
} else {
    // Fallback to simple test if WordPress HTTP API isn't available
    $connection = @fsockopen('api.notion.com', 443, $errno, $errstr, 5);
    if ($connection) {
        echo "<div class='success'>✅ Successfully established TCP connection to api.notion.com:443</div>";
        fclose($connection);
    } else {
        echo "<div class='error'>❌ Failed to connect to api.notion.com:443 - Error $errno: $errstr</div>";
    }
}

echo '</div>';
echo '</body></html>';
?>
