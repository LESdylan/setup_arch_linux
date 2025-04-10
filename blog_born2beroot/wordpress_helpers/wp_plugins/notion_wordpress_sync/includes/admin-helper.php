<?php
/**
 * Admin Helper Functions
 * 
 * Provides secure methods to access plugin tools
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate secure direct access URLs for diagnostic tools
 */
class Notion_WP_Sync_Admin_Helper {
    
    /**
     * Generate a secure URL for direct access to diagnostic tools
     * 
     * @param string $tool_file The file name of the tool
     * @param array $extra_params Any additional parameters to add to the URL
     * @return string The secure URL
     */
    public static function get_secure_tool_url($tool_file, $extra_params = array()) {
        // Security: Create a nonce based on the tool name
        $nonce_action = 'notion_wp_' . basename($tool_file, '.php');
        $nonce = wp_create_nonce($nonce_action);
        
        // Build URL using WordPress functions to ensure correct paths
        // This addresses the difference between development and production paths
        $plugin_url = plugins_url('/', dirname(__FILE__));
        $url = $plugin_url . $tool_file;
        
        // Debug the URL construction if needed
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Notion WP Sync: Tool URL constructed: ' . $url);
            error_log('Notion WP Sync: Plugin directory detected as: ' . NOTION_WP_SYNC_PLUGIN_DIR);
        }
        
        // Add default security parameters
        $params = array_merge(array(
            'admin_check' => 'true',
            'security_nonce' => $nonce
        ), $extra_params);
        
        // Add parameters to URL
        $url = add_query_arg($params, $url);
        
        return $url;
    }
    
    /**
     * Get list of available diagnostic tools
     */
    public static function get_available_tools() {
        return array(
            'mermaid-test.php' => 'Mermaid Diagram Tester',
            'network-test.php' => 'Network Connectivity Test',
            'notion-test.php' => 'Notion API Tester',
            'localhost-helper.php' => 'Localhost Port Forwarding Helper'
        );
    }
    
    /**
     * Get server path info for troubleshooting
     */
    public static function get_path_info() {
        $paths = array(
            'NOTION_WP_SYNC_PLUGIN_DIR' => defined('NOTION_WP_SYNC_PLUGIN_DIR') ? NOTION_WP_SYNC_PLUGIN_DIR : 'Not defined',
            'NOTION_WP_SYNC_PLUGIN_URL' => defined('NOTION_WP_SYNC_PLUGIN_URL') ? NOTION_WP_SYNC_PLUGIN_URL : 'Not defined',
            'WP_PLUGIN_DIR' => defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : 'Not defined',
            'ABSPATH' => defined('ABSPATH') ? ABSPATH : 'Not defined',
            'plugins_url' => plugins_url('', dirname(__FILE__)),
            'plugin_dir_path' => plugin_dir_path(dirname(__FILE__)),
            'plugin_basename' => plugin_basename(dirname(dirname(__FILE__))),
            '__FILE__' => __FILE__,
            'script path' => $_SERVER['SCRIPT_FILENAME'] ?? 'Not available'
        );
        
        return $paths;
    }
    
    /**
     * Add a diagnostic tools page to WordPress admin
     */
    public static function add_diagnostic_tools_page() {
        // IMPORTANT: Register the page as a submenu of notion-wp-sync
        add_submenu_page(
            'notion-wp-sync',          // Parent slug - must match main menu
            'Diagnostic Tools',         // Page title
            'Diagnostic Tools',         // Menu title
            'administrator',            // Capability
            'notion-wp-sync-tools',     // Menu slug
            array('Notion_WP_Sync_Admin_Helper', 'render_tools_page')
        );
        
        // Direct access to debug page (for compatibility)
        if (!function_exists('notion_wp_sync_debug_page')) {
            add_submenu_page(
                'tools.php',             // Put it under Tools as a fallback
                'Notion WP Sync Debug',  // Page title
                'Notion ID Troubleshooter', // Menu title
                'administrator',         // Capability
                'notion-wp-sync-debug',  // Menu slug
                array('Notion_WP_Sync_Admin_Helper', 'render_tools_page') // Use the same rendering function
            );
        }
    }
    
    /**
     * Render the diagnostic tools page
     */
    public static function render_tools_page() {
        // Security check
        if (!current_user_can('administrator')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        
        $tools = self::get_available_tools();
        $path_info = self::get_path_info();
        
        // Check if this might be a localhost port forwarding situation
        $is_localhost_possible = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
                                strpos($_SERVER['SERVER_NAME'] ?? '', 'localhost') !== false || 
                                in_array($_SERVER['REMOTE_ADDR'] ?? '', array('127.0.0.1', '::1'));
        ?>
        <div class="wrap">
            <h1>Notion WP Sync - Diagnostic Tools</h1>
            
            <?php if ($is_localhost_possible): ?>
            <div class="notice notice-info">
                <p>
                    <strong>Port Forwarding Detected:</strong> If you're using port forwarding on localhost, you can use
                    the direct links below, or try our <a href="<?php echo esc_url(plugins_url('localhost-helper.php?admin_check=true', dirname(__FILE__))); ?>" 
                       target="_blank">Localhost Helper</a> for simplified access.
                </p>
            </div>
            <?php endif; ?>
            
            <div class="notice notice-warning">
                <p>
                    <strong>Note:</strong> These tools are for administrators only. 
                    The provided links include security tokens that will expire after 24 hours.
                </p>
            </div>
            
            <div class="card" style="max-width:100%; margin-bottom:20px; padding:15px;">
                <h2>Path Information</h2>
                <p>This information can help troubleshoot path-related issues:</p>
                <table class="widefat striped" style="max-width:800px;">
                    <thead>
                        <tr>
                            <th>Path Variable</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($path_info as $name => $value): ?>
                        <tr>
                            <td><code><?php echo esc_html($name); ?></code></td>
                            <td><code><?php echo esc_html($value); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Tool</th>
                        <th>Description</th>
                        <th>Access Options</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tools as $file => $name): ?>
                        <tr>
                            <td><?php echo esc_html($name); ?></td>
                            <td>
                                <?php 
                                switch ($file) {
                                    case 'mermaid-test.php':
                                        echo 'Test and debug Mermaid diagram rendering from Notion';
                                        break;
                                    case 'network-test.php':
                                        echo 'Test network connectivity to Notion API';
                                        break;
                                    case 'notion-test.php':
                                        echo 'Test Notion API authentication and content access';
                                        break;
                                    case 'localhost-helper.php':
                                        echo 'Special helper for localhost port forwarding situations';
                                        break;
                                    default:
                                        echo 'Diagnostic tool';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(self::get_secure_tool_url($file)); ?>" 
                                   class="button" 
                                   target="_blank">
                                    Secure Access
                                </a>
                                
                                <?php if ($is_localhost_possible): ?>
                                <a href="<?php echo esc_url(plugins_url($file . '?admin_check=true', dirname(__FILE__))); ?>" 
                                   class="button" 
                                   target="_blank">
                                    Localhost Direct
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="card" style="max-width:100%; margin-top:20px; padding:15px;">
                <h2>Direct Access</h2>
                <p>If you're experiencing security blocks when accessing the test files directly, try adding the following to your .htaccess file:</p>
                <pre style="background:#f5f5f5; padding:10px; overflow:auto;">
# Allow access to notion-wordpress-sync test files
&lt;Files ~ "^(notion-test|mermaid-test|network-test)\.php$"&gt;
    &lt;IfModule mod_authz_core.c&gt;
        Require all granted
    &lt;/IfModule&gt;
    &lt;IfModule !mod_authz_core.c&gt;
        Order allow,deny
        Allow from all
    &lt;/IfModule&gt;
&lt;/Files&gt;
</pre>
            </div>
        </div>
        <?php
    }
}

// Hook to register the tools page
add_action('admin_menu', array('Notion_WP_Sync_Admin_Helper', 'add_diagnostic_tools_page'), 20);
