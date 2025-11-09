<?php
/**
 * Debug Class
 * 
 * Handles debugging and system checks for the plugin
 */
class Notion_WP_Sync_Debug {
    
    /**
     * Run a complete system check
     */
    public static function run_system_check() {
        $results = array(
            'wordpress' => self::check_wordpress(),
            'plugin' => self::check_plugin_files(),
            'database' => self::check_database(),
            'api' => self::check_api_connection(),
            'php' => self::check_php_requirements(),
            'notion_api_key' => self::check_notion_api_key(),
        );
        
        return $results;
    }
    
    /**
     * Check Notion API key
     */
    private static function check_notion_api_key() {
        // Check if the API key is defined as a constant
        $defined_in_config = defined('NOTION_API_KEY');
        
        // Check if the API key is stored in the database
        $stored_in_db = !empty(get_option('notion_wp_sync_api_key', ''));
        
        // Get the actual API key being used
        $api = new Notion_WP_Sync_API();
        $test_result = $api->test_connection();
        
        $result = array(
            'status' => ($defined_in_config || $stored_in_db) ? 'success' : 'error',
            'message' => ($defined_in_config || $stored_in_db) ? 
                        'Notion API key found' : 
                        'Notion API key not found',
            'details' => array(
                'defined_in_wp_config' => $defined_in_config ? 'Yes' : 'No',
                'stored_in_database' => $stored_in_db ? 'Yes' : 'No',
                'connection_test_result' => $test_result ? 'Successful' : 'Failed',
                'last_error' => get_option('notion_wp_sync_last_error', 'None'),
            )
        );
        
        return $result;
    }
    
    /**
     * Check WordPress environment
     */
    private static function check_wordpress() {
        global $wp_version;
        
        $meets_requirements = version_compare($wp_version, '5.0', '>=');
        
        return array(
            'status' => $meets_requirements ? 'success' : 'error',
            'message' => $meets_requirements ? 
                       'WordPress version is compatible' : 
                       'WordPress version is not compatible',
            'details' => array(
                'current_version' => $wp_version,
                'required_version' => '5.0',
                'is_multisite' => is_multisite() ? 'Yes' : 'No',
                'is_debug_mode' => defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled',
            )
        );
    }
    
    /**
     * Check PHP requirements
     */
    private static function check_php_requirements() {
        $required_version = '7.0';
        $current_version = phpversion();
        $meets_requirements = version_compare($current_version, $required_version, '>=');
        
        $result = array(
            'status' => $meets_requirements ? 'success' : 'error',
            'message' => $meets_requirements ? 
                       'PHP version is compatible' : 
                       'PHP version is not compatible',
            'details' => array(
                'current_version' => $current_version,
                'required_version' => $required_version,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'curl_enabled' => function_exists('curl_version') ? 'Yes' : 'No',
                'json_enabled' => function_exists('json_decode') ? 'Yes' : 'No',
            )
        );
        
        return $result;
    }
    
    /**
     * Check plugin files exist
     */
    private static function check_plugin_files() {
        $required_files = array(
            'includes/class-db.php',
            'includes/class-notion-api.php',
            'includes/class-content-sync.php',
            'includes/class-debug.php',
            'admin/admin-page.php',
            'admin/admin.js',
            'admin/admin.css',
        );
        
        $missing_files = array();
        $unreadable_files = array();
        
        foreach ($required_files as $file) {
            $full_path = NOTION_WP_SYNC_PLUGIN_DIR . $file;
            
            if (!file_exists($full_path)) {
                $missing_files[] = $file;
            } else if (!is_readable($full_path)) {
                $unreadable_files[] = $file;
            }
        }
        
        if (!empty($missing_files) || !empty($unreadable_files)) {
            $status = 'error';
            $message = 'Some plugin files are missing or unreadable';
        } else {
            $status = 'success';
            $message = 'All plugin files are present and readable';
        }
        
        return array(
            'status' => $status,
            'message' => $message,
            'details' => array(
                'missing_files' => $missing_files,
                'unreadable_files' => $unreadable_files,
                'plugin_dir' => NOTION_WP_SYNC_PLUGIN_DIR,
            )
        );
    }
    
    /**
     * Check database tables
     */
    private static function check_database() {
        global $wpdb;
        
        $prefix = $wpdb->prefix;
        $mappings_table = $prefix . 'notion_wp_sync_mappings';
        $sync_log_table = $prefix . 'notion_wp_sync_log';
        
        $mappings_exists = $wpdb->get_var("SHOW TABLES LIKE '$mappings_table'") === $mappings_table;
        $sync_log_exists = $wpdb->get_var("SHOW TABLES LIKE '$sync_log_table'") === $sync_log_table;
        
        if (!$mappings_exists || !$sync_log_exists) {
            $status = 'error';
            $message = 'Some database tables are missing';
        } else {
            $status = 'success';
            $message = 'All database tables exist';
        }
        
        return array(
            'status' => $status,
            'message' => $message,
            'details' => array(
                'mappings_table_exists' => $mappings_exists ? 'Yes' : 'No',
                'sync_log_table_exists' => $sync_log_exists ? 'Yes' : 'No',
                'wordpress_prefix' => $prefix,
            )
        );
    }
    
    /**
     * Check API connection
     */
    private static function check_api_connection() {
        $api_key = '';
        if (defined('NOTION_API_KEY')) {
            $api_key = NOTION_API_KEY;
        } else {
            $api_key = get_option('notion_wp_sync_api_key', '');
        }
        
        if (empty($api_key)) {
            return array(
                'status' => 'warning',
                'message' => 'Notion API key is not configured.',
                'details' => array(
                    'api_key_set' => false,
                    'connection_tested' => false,
                )
            );
        }
        
        $api = new Notion_WP_Sync_API();
        $connection = $api->test_connection();
        $last_error = get_option('notion_wp_sync_last_error', '');
        
        $result = array(
            'status' => $connection ? 'success' : 'error',
            'message' => $connection ? 
                'Successfully connected to Notion API.' : 
                'Failed to connect to Notion API. ' . $last_error,
            'details' => array(
                'api_key_set' => true,
                'api_key_format' => 'Key exists (starts with ' . substr($api_key, 0, 3) . '...)',
                'connection_tested' => true,
                'connection_successful' => $connection,
                'last_error' => $last_error,
            )
        );
        
        return $result;
    }
    
    /**
     * Get all accessible Notion resources (for debugging)
     */
    public static function get_all_notion_resources() {
        if (!class_exists('Notion_WP_Sync_API')) {
            return [
                'status' => 'error',
                'message' => 'Notion API class not found',
                'resources' => []
            ];
        }
        
        $api = new Notion_WP_Sync_API();
        
        // Try a direct search with no filters to get everything accessible
        $search_response = $api->request('POST', 'search', [
            'page_size' => 100
        ]);
        
        if (is_wp_error($search_response)) {
            return [
                'status' => 'error',
                'message' => 'Error fetching Notion resources: ' . $search_response->get_error_message(),
                'resources' => []
            ];
        }
        
        // Group results by type
        $resources = [
            'databases' => [],
            'pages' => [],
            'other' => []
        ];
        
        foreach ($search_response['results'] as $result) {
            $object_type = $result['object'] ?? 'unknown';
            
            // Get a title for the object
            $title = 'Untitled';
            if ($object_type === 'database' && !empty($result['title'])) {
                $title = '';
                foreach ($result['title'] as $text) {
                    $title .= $text['plain_text'] ?? '';
                }
            } elseif ($object_type === 'page' && !empty($result['properties']['title'])) {
                $title_prop = $result['properties']['title'];
                if (!empty($title_prop['title'])) {
                    $title = '';
                    foreach ($title_prop['title'] as $text) {
                        $title .= $text['plain_text'] ?? '';
                    }
                }
            }
            
            $item = [
                'id' => $result['id'],
                'title' => $title ?: 'Untitled',
                'created_time' => $result['created_time'] ?? '',
                'last_edited_time' => $result['last_edited_time'] ?? '',
                'url' => $result['url'] ?? '',
            ];
            
            if ($object_type === 'database') {
                $resources['databases'][] = $item;
            } elseif ($object_type === 'page') {
                $resources['pages'][] = $item;
            } else {
                $resources['other'][] = $item;
            }
        }
        
        return [
            'status' => 'success',
            'message' => 'Found ' . count($search_response['results']) . ' Notion resources',
            'resources' => $resources
        ];
    }
    
    /**
     * Get a log of the last 50 errors
     */
    public static function get_error_log() {
        $log = get_option('notion_wp_sync_error_log', array());
        return array_slice($log, -50); // Return last 50 entries
    }
    
    /**
     * Log an error
     */
    public static function log_error($message, $context = array()) {
        $log = get_option('notion_wp_sync_error_log', array());
        
        $log[] = array(
            'time' => current_time('mysql'),
            'message' => $message,
            'context' => $context
        );
        
        // Keep only the last 100 entries
        if (count($log) > 100) {
            $log = array_slice($log, -100);
        }
        
        update_option('notion_wp_sync_error_log', $log);
    }
}
