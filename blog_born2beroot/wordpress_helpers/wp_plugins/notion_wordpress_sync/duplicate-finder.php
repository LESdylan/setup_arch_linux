<?php
/**
 * Duplicate Plugin Finder
 * Helps identify if there are duplicate copies of the plugin
 */

// Basic security
if (!isset($_GET['admin_check']) || $_GET['admin_check'] !== 'true') {
    echo "Access denied. Add ?admin_check=true to the URL.";
    exit;
}

// Try to load WordPress
$wp_load_path = dirname(dirname(dirname(__FILE__))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    echo "WordPress core files not found. Cannot continue.";
    exit;
}

// Only allow administrators
if (!current_user_can('administrator')) {
    echo "You must be an administrator to use this tool.";
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Notion WP Sync - Duplicate Finder</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; }
        .warning { background: #fff8e5; padding: 15px; border-left: 5px solid #ffb900; margin: 15px 0; }
        .section { background: #f5f5f5; padding: 15px; margin: 15px 0; border-left: 5px solid #0073aa; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Notion WP Sync - Duplicate Finder</h1>
    
    <div class="warning">
        <h2>⚠️ Duplicate Plugin Alert</h2>
        <p>You mentioned seeing two copies of the Notion WP Sync plugin in the WordPress admin. This tool will help identify and fix the issue.</p>
    </div>
    
    <div class="section">
        <h2>Active Plugins</h2>
        <table>
            <tr>
                <th>Plugin</th>
                <th>Path</th>
                <th>Status</th>
            </tr>
            <?php
            $active_plugins = get_option('active_plugins');
            $notion_plugins = array();
            
            foreach ($active_plugins as $plugin) {
                if (strpos($plugin, 'notion') !== false) {
                    $notion_plugins[] = $plugin;
                    $path = WP_PLUGIN_DIR . '/' . $plugin;
                    $status = file_exists($path) ? '✅ File Exists' : '❌ File Missing';
                    echo "<tr>
                        <td>{$plugin}</td>
                        <td>{$path}</td>
                        <td>{$status}</td>
                    </tr>";
                }
            }
            
            if (empty($notion_plugins)) {
                echo "<tr><td colspan='3'>No active Notion plugins found.</td></tr>";
            }
            ?>
        </table>
    </div>
    
    <div class="section">
        <h2>Plugin Directories Search</h2>
        <p>Searching for Notion-related directories in your plugins folder:</p>
        
        <table>
            <tr>
                <th>Directory</th>
                <th>Path</th>
                <th>Status</th>
            </tr>
            <?php
            $plugin_dir = WP_PLUGIN_DIR;
            $dirs = scandir($plugin_dir);
            $notion_dirs = array();
            
            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') continue;
                
                if (strpos($dir, 'notion') !== false) {
                    $notion_dirs[] = $dir;
                    $path = $plugin_dir . '/' . $dir;
                    $is_dir = is_dir($path) ? '✅ Directory' : '❌ Not a directory';
                    echo "<tr>
                        <td>{$dir}</td>
                        <td>{$path}</td>
                        <td>{$is_dir}</td>
                    </tr>";
                }
            }
            
            if (empty($notion_dirs)) {
                echo "<tr><td colspan='3'>No Notion-related directories found.</td></tr>";
            }
            ?>
        </table>
    </div>
    
    <div class="section">
        <h2>Current Plugin Information</h2>
        <p>Information about this specific plugin installation:</p>
        <ul>
            <li>Plugin Directory: <?php echo dirname(__FILE__); ?></li>
            <li>Plugin Folder Name: <?php echo basename(dirname(__FILE__)); ?></li>
            <li>Parent Directory: <?php echo dirname(dirname(__FILE__)); ?></li>
        </ul>
    </div>
    
    <div class="section">
        <h2>How to Fix Duplicate Plugins</h2>
        <p>If you see two copies of the plugin in your WordPress admin, follow these steps:</p>
        <ol>
            <li>Go to the Plugins page in WordPress admin</li>
            <li>Deactivate both copies of Notion WP Sync</li>
            <li>Delete both copies of the plugin from the WordPress admin</li>
            <li>Upload a fresh copy of the plugin</li>
            <li>Activate the plugin</li>
        </ol>
        <p>If you can't delete one of the copies from the WordPress admin:</p>
        <ol>
            <li>Use FTP or your hosting file manager to connect to your site</li>
            <li>Go to the wp-content/plugins directory</li>
            <li>Find and delete any notion-related plugin folders</li>
            <li>Then install a fresh copy of the plugin</li>
        </ol>
    </div>
</body>
</html>
