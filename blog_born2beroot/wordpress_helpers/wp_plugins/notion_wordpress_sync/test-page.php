<?php
/**
 * Test Page for Notion WP Sync
 * 
 * This file allows directly testing a specific Notion page ID.
 */

// Special handling for local network access
$is_localhost = false;
$local_ips = array('127.0.0.1', '::1', '192.168.1.133'); // Local network IPs

if (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], $local_ips)) {
    $is_localhost = true;
}

// Basic security - only allow admin access (more lenient for local network)
if (!isset($_GET['admin_check']) || $_GET['admin_check'] !== 'true') {
    echo "Add ?admin_check=true to the URL to run the test";
    exit;
}

// Try to load WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    echo "WordPress load file not found at expected location.";
    exit;
}

// Ensure user is admin (skip for local network)
if (!$is_localhost && !current_user_can('administrator')) {
    echo "You must be an administrator to use this tool.";
    exit;
}

// Show errors for diagnostic purposes
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Base page ID to test
$test_id = isset($_GET['id']) ? $_GET['id'] : '19a52b5682188049b6c5da694d56c5bf';

// Define plugin constants if needed
if (!defined('NOTION_WP_SYNC_PLUGIN_DIR')) {
    define('NOTION_WP_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('NOTION_WP_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Load required classes
$files = [
    'includes/class-db.php',
    'includes/class-notion-api.php',
    'includes/class-content-sync.php'
];

foreach ($files as $file) {
    $path = NOTION_WP_SYNC_PLUGIN_DIR . $file;
    if (file_exists($path)) {
        require_once $path;
    } else {
        echo "<p>Error: Required file not found: $file</p>";
    }
}

// Optional ID cleaning
function clean_notion_id($id) {
    // Basic cleaning of hyphens and spaces
    $clean_id = preg_replace('/[\-\s]/', '', $id);
    
    // Handle URLs
    if (strpos($clean_id, 'notion.so') !== false) {
        // Extract just the ID from the full URL
        preg_match('/-([a-f0-9]{32})$|([a-f0-9]{32})$/', $clean_id, $matches);
        if (!empty($matches[1])) {
            $clean_id = $matches[1];
        } elseif (!empty($matches[2])) {
            $clean_id = $matches[2];
        }
    }
    
    return $clean_id;
}

// Get cleaned ID
$clean_id = clean_notion_id($test_id);
$current_api_key = get_option('notion_wp_sync_api_key');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Notion Page ID Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; line-height: 1.6; }
        h1, h2 { color: #2c3e50; }
        .section { background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-left: 5px solid #3498db; }
        .error { color: #e74c3c; }
        .success { color: #2ecc71; }
        pre { background: #f8f8f8; padding: 10px; overflow: auto; }
        code { background: #f8f8f8; padding: 2px 5px; }
    </style>
</head>
<body>
    <h1>Notion Page ID Test Tool</h1>
    
    <div class="section">
        <h2>Page ID Information</h2>
        <p><strong>Original ID:</strong> <?php echo htmlspecialchars($test_id); ?></p>
        <p><strong>Cleaned ID:</strong> <?php echo htmlspecialchars($clean_id); ?></p>
    </div>
    
    <div class="section">
        <h2>API Connection Test</h2>
        <?php
        if (empty($current_api_key)) {
            echo "<p class='error'>No API key found in settings! Please go to the Notion WP Sync settings page and enter your API key.</p>";
        } else {
            echo "<p>API key found in settings (starts with: " . substr($current_api_key, 0, 3) . "...)</p>";
            
            // Test API connection
            if (class_exists('Notion_WP_Sync_API')) {
                $api = new Notion_WP_Sync_API();
                $connection = $api->test_connection();
                
                if ($connection) {
                    echo "<p class='success'>✅ API connection successful!</p>";
                } else {
                    echo "<p class='error'>❌ API connection failed. Error: " . get_option('notion_wp_sync_last_error', 'Unknown error') . "</p>";
                }
                
                // Try to fetch the specific page
                $response = $api->get_page($clean_id);
                
                if (is_wp_error($response) || $response === null) {
                    echo "<p class='error'>❌ Failed to fetch page with ID: $clean_id</p>";
                    
                    if (is_wp_error($response)) {
                        echo "<p>Error: " . $response->get_error_message() . "</p>";
                    }
                    
                    echo "<p>Potential reasons:</p>";
                    echo "<ul>";
                    echo "<li>The page doesn't exist</li>";
                    echo "<li>Your integration doesn't have access to the page. Make sure to share the page with your integration!</li>";
                    echo "<li>The API key doesn't have proper permissions</li>";
                    echo "</ul>";
                } else {
                    echo "<p class='success'>✅ Successfully fetched page data!</p>";
                    
                    // Show some basic info
                    echo "<h3>Page Info</h3>";
                    echo "<p><strong>Page ID:</strong> " . $response['id'] . "</p>";
                    echo "<p><strong>Last Edited:</strong> " . $response['last_edited_time'] . "</p>";
                    
                    // Try to get title
                    $title = "Untitled";
                    if (isset($response['properties']['title']) && isset($response['properties']['title']['title'])) {
                        $title_parts = $response['properties']['title']['title'];
                        $title = "";
                        foreach ($title_parts as $part) {
                            $title .= $part['plain_text'];
                        }
                    } elseif (isset($response['properties']['Name']) && isset($response['properties']['Name']['title'])) {
                        $title_parts = $response['properties']['Name']['title'];
                        $title = "";
                        foreach ($title_parts as $part) {
                            $title .= $part['plain_text'];
                        }
                    }
                    
                    echo "<p><strong>Title:</strong> " . htmlspecialchars($title) . "</p>";
                    
                    // Option to sync page right now
                    echo "<h3>Sync This Page</h3>";
                    echo "<form method='post'>";
                    echo "<input type='hidden' name='action' value='sync_page'>";
                    echo "<input type='hidden' name='page_id' value='" . htmlspecialchars($clean_id) . "'>";
                    echo "<p>Post Type: <select name='post_type'>";
                    echo "<option value='post'>Post</option>";
                    echo "<option value='page'>Page</option>";
                    echo "</select></p>";
                    echo "<button type='submit'>Sync This Page Now</button>";
                    echo "</form>";
                }
            } else {
                echo "<p class='error'>❌ Notion_WP_Sync_API class not found!</p>";
            }
        }
        ?>
    </div>
    
    <?php
    // Handle sync request
    if (isset($_POST['action']) && $_POST['action'] === 'sync_page') {
        $page_id = $_POST['page_id'];
        $post_type = $_POST['post_type'];
        
        echo "<div class='section'>";
        echo "<h2>Sync Results</h2>";
        
        if (class_exists('Notion_WP_Sync_Content_Sync')) {
            $sync = new Notion_WP_Sync_Content_Sync();
            $result = $sync->sync_page($page_id, $post_type);
            
            if ($result) {
                echo "<p class='success'>✅ Page synced successfully!</p>";
                
                // Get DB class and find mapping
                if (class_exists('Notion_WP_Sync_DB')) {
                    $db = new Notion_WP_Sync_DB();
                    $mapping = $db->get_mapping_by_notion_id($page_id);
                    
                    if ($mapping) {
                        echo "<p>WordPress Post ID: " . $mapping->wp_post_id . "</p>";
                        echo "<p><a href='" . get_edit_post_link($mapping->wp_post_id) . "' target='_blank'>Edit Post</a> | ";
                        echo "<a href='" . get_permalink($mapping->wp_post_id) . "' target='_blank'>View Post</a></p>";
                    }
                }
            } else {
                echo "<p class='error'>❌ Sync failed!</p>";
                echo "<p>Check the sync logs in the admin panel for more details.</p>";
            }
        } else {
            echo "<p class='error'>❌ Notion_WP_Sync_Content_Sync class not found!</p>";
        }
        
        echo "</div>";
    }
    ?>
    
    <div class="section">
        <h2>Helpful Links</h2>
        <ul>
            <li><a href="<?php echo admin_url('admin.php?page=notion-wp-sync'); ?>">Go to Notion WP Sync Settings</a></li>
            <li><a href="<?php echo admin_url('admin.php?page=notion-wp-sync&tab=mappings'); ?>">Go to Content Mappings</a></li>
            <li><a href="<?php echo admin_url('tools.php?page=notion-wp-sync-debug'); ?>">Go to Notion ID Troubleshooter</a></li>
        </ul>
    </div>
</body>
</html>
