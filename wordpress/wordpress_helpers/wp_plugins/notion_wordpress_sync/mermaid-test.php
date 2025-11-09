<?php
/**
 * Mermaid Diagram Test Tool with Enhanced Security
 * 
 * Tests rendering of Mermaid diagrams from Notion
 */

// Enhanced security checks
define('DIRECT_ACCESS_CHECK', true);

// Special handling for localhost port forwarding
$is_localhost = false;
$local_ips = array('127.0.0.1', '::1', '192.168.1.133'); // Added your local IP

if (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], $local_ips)) {
    $is_localhost = true;
}

// Always allow admin_check without nonce on local network
$bypass_nonce = $is_localhost && isset($_GET['admin_check']) && $_GET['admin_check'] === 'true';

// First, try to properly load WordPress
$wp_load_attempts = array(
    dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php',  // Standard WordPress location
    '../../../wp-load.php',                                         // Relative path attempt
    dirname(dirname(dirname(__FILE__))) . '/wp-load.php'            // Alternative location
);

$wordpress_loaded = false;
foreach ($wp_load_attempts as $load_path) {
    if (file_exists($load_path)) {
        // Found WordPress, load it
        require_once($load_path);
        $wordpress_loaded = true;
        break;
    }
}

// Add security verification
if (!$wordpress_loaded) {
    // Prevent information disclosure
    http_response_code(403);
    die('Access denied. Please log in to WordPress admin and navigate to the Notion WP Sync plugin settings.');
}

// For localhost or local network, always allow access with admin_check
$is_valid_admin = false;
if ($bypass_nonce) {
    $is_valid_admin = true;
} else if (isset($_GET['admin_check']) && $_GET['admin_check'] === 'true') {
    // For remote access, require proper authentication
    if (function_exists('current_user_can') && current_user_can('administrator')) {
        if (isset($_GET['security_nonce']) && wp_verify_nonce($_GET['security_nonce'], 'notion_wp_mermaid_test')) {
            $is_valid_admin = true;
        }
    }
}

// Block access if not a valid admin
if (!$is_valid_admin) {
    http_response_code(403);
    // Provide helpful instructions instead of error details
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Access Denied</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; line-height: 1.6; }
            h1 { color: #d63638; }
            .box { background: #f8f9fa; border-left: 4px solid #3582c4; padding: 15px; margin: 20px 0; }
            code { background: #f1f1f1; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
        </style>
    </head>
    <body>
        <h1>Access Denied</h1>
        <p>This tool is only available to authenticated WordPress administrators.</p>
        
        <div class='box'>
            <h2>Proper Access Methods:</h2>
            <ol>
                <li>Log in to your WordPress admin</li>
                <li>Go to <strong>Notion WP Sync → Tools → Mermaid Tester</strong></li>
            </ol>
        </div>
        
        <div class='box'>
            <h2>Local Network Access</h2>
            <p>For local network access (" . htmlspecialchars($_SERVER['REMOTE_ADDR']) . "), use this URL:</p>
            <code>" . htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']) . "/wp-content/plugins/notion_wordpress_sync/mermaid-test.php?admin_check=true</code>
        </div>
        
        <p>If you are the site administrator and need direct access, please:</p>
        <ol>
            <li>Log in to WordPress admin</li>
            <li>Go to <strong>Notion WP Sync → Diagnostic Tools</strong></li>
            <li>Use the secure link provided there for direct access to this tool</li>
        </ol>
    </body>
    </html>";
    exit;
}

// Load our Mermaid formatter
$formatter_path = dirname(__FILE__) . '/includes/formatters/class-mermaid-formatter.php';
if (file_exists($formatter_path)) {
    require_once($formatter_path);
} else {
    echo "<p>Error: Mermaid formatter not found at: $formatter_path</p>";
    // Use a simple implementation if the formatter is missing
    class Notion_WP_Sync_Mermaid_Formatter {
        public static function format($code) {
            return '<div class="mermaid">' . trim($code) . '</div>';
        }
    }
}

// Get sample or submitted diagram
$diagram = isset($_POST['diagram']) ? stripslashes($_POST['diagram']) : 
'flowchart LR
Start[Need to
Encrypt Files?] --> Platform{Which Device?}

Platform -->|Windows| Win{Version Type?}
Platform -->|macOS| Mac["Use Disk Utility:
• Create Encrypted Image
• Choose 128-bit or 256-bit"]
Platform -->|Mobile| Cloud["Use Cloud Storage:
• Proton Drive
• End-to-end encryption"]

Win -->|Pro/Edu/Ent| BuiltIn["Built-in Encryption:
• Right-click → Properties
• Check \'Encrypt contents\'
• Apply settings"]
Win -->|Home| ThirdParty["Third-party Tools:
• AxCrypt
• Other encryption software"]

style Start fill:#f9f,stroke:#333,color:#000
style Platform fill:#bbf,stroke:#333,color:#000
style Win fill:#bbf,stroke:#333,color:#000
style BuiltIn fill:#bfb,stroke:#333,color:#000
style ThirdParty fill:#bfb,stroke:#333,color:#000
style Mac fill:#bfb,stroke:#333,color:#000
style Cloud fill:#bfb,stroke:#333,color:#000';

// Apply formatter
$formatted_diagram = Notion_WP_Sync_Mermaid_Formatter::format($diagram);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Mermaid Diagram Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; line-height: 1.6; }
        h1, h2 { color: #2c3e50; }
        .section { background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-left: 5px solid #3498db; }
        textarea { width: 100%; height: 200px; font-family: monospace; margin-bottom: 15px; }
        .preview { margin-top: 30px; }
        .rendered { background: #f8f8f8; padding: 20px; border-radius: 5px; }
        .code-display { background: #f8f8f8; padding: 15px; overflow: auto; border: 1px solid #ddd; font-family: monospace; }
        .notice { background: #e7f5ff; padding: 10px; border-left: 4px solid #3498db; }
    </style>
    <!-- Latest Mermaid from CDN -->
    <script src="https://cdn.jsdelivr.net/npm/mermaid@10.9.3/dist/mermaid.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            mermaid.initialize({
                startOnLoad: true,
                theme: "default",
                flowchart: {
                    useMaxWidth: true,
                    htmlLabels: true
                }
            });
        });
    </script>
</head>
<body>
    <h1>Notion WP Sync - Mermaid Diagram Tester</h1>
    
    <?php if ($is_localhost): ?>
    <div class="notice">
        <p><strong>Localhost Mode:</strong> You're running in localhost mode via port forwarding.</p>
    </div>
    <?php endif; ?>
    
    <div class="section">
        <h2>Test Your Mermaid Diagram</h2>
        <p>Paste your Notion Mermaid diagram below to see how it renders.</p>
        
        <form method="post">
            <textarea name="diagram"><?php echo htmlspecialchars($diagram); ?></textarea>
            <button type="submit">Process Diagram</button>
        </form>
    </div>
    
    <div class="section">
        <h2>Processed Code</h2>
        <div class="code-display"><?php echo htmlspecialchars($formatted_diagram); ?></div>
    </div>
    
    <div class="section preview">
        <h2>Diagram Preview</h2>
        <div class="rendered">
            <?php echo $formatted_diagram; ?>
        </div>
    </div>
    
    <div class="section">
        <h2>Tips for Mermaid in Notion</h2>
        <ul>
            <li>Start your code block in Notion with <code>```mermaid</code></li>
            <li>Ensure your flowchart direction is specified (LR, TD, etc.)</li>
            <li>Use simple node names (A, B, C or descriptive IDs)</li>
            <li>Avoid special characters in node text</li>
            <li>For multiline text, use <code>\n</code> or line breaks in the node text</li>
        </ul>
    </div>
</body>
</html>
