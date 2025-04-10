<?php
/**
 * Database Class
 * 
 * Handles database operations for the plugin
 */
class Notion_WP_Sync_DB {
    
    private $mappings_table;
    private $sync_log_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->mappings_table = $wpdb->prefix . 'notion_wp_sync_mappings';
        $this->sync_log_table = $wpdb->prefix . 'notion_wp_sync_log';
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $mappings_sql = "CREATE TABLE $this->mappings_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            notion_id varchar(36) NOT NULL,
            notion_type varchar(20) NOT NULL,
            wp_post_id bigint(20) NOT NULL,
            wp_post_type varchar(20) NOT NULL,
            last_synced datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY notion_id (notion_id)
        ) $charset_collate;";
        
        $log_sql = "CREATE TABLE $this->sync_log_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            sync_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            notion_id varchar(36) NOT NULL,
            wp_post_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL,
            message text,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($mappings_sql);
        dbDelta($log_sql);
    }
    
    /**
     * Get mapping by Notion ID
     */
    public function get_mapping_by_notion_id($notion_id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM $this->mappings_table WHERE notion_id = %s",
            $notion_id
        );
        
        return $wpdb->get_row($query);
    }
    
    /**
     * Get mapping by WordPress post ID
     */
    public function get_mapping_by_post_id($post_id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM $this->mappings_table WHERE wp_post_id = %d",
            $post_id
        );
        
        return $wpdb->get_row($query);
    }
    
    /**
     * Save or update mapping
     */
    public function save_mapping($notion_id, $notion_type, $post_id, $post_type) {
        global $wpdb;
        
        $mapping = $this->get_mapping_by_notion_id($notion_id);
        
        if ($mapping) {
            // Update existing mapping
            $wpdb->update(
                $this->mappings_table,
                array(
                    'wp_post_id' => $post_id,
                    'wp_post_type' => $post_type,
                    'last_synced' => current_time('mysql')
                ),
                array('notion_id' => $notion_id)
            );
        } else {
            // Insert new mapping
            $wpdb->insert(
                $this->mappings_table,
                array(
                    'notion_id' => $notion_id,
                    'notion_type' => $notion_type,
                    'wp_post_id' => $post_id,
                    'wp_post_type' => $post_type,
                    'last_synced' => current_time('mysql')
                )
            );
        }
    }
    
    /**
     * Get all mappings
     */
    public function get_all_mappings() {
        global $wpdb;
        
        $query = "SELECT * FROM $this->mappings_table ORDER BY last_synced DESC";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Log sync event
     */
    public function log_sync($notion_id, $post_id, $status, $message) {
        global $wpdb;
        
        $wpdb->insert(
            $this->sync_log_table,
            array(
                'sync_time' => current_time('mysql'),
                'notion_id' => $notion_id,
                'wp_post_id' => $post_id,
                'status' => $status,
                'message' => $message
            )
        );
    }
    
    /**
     * Get sync logs
     */
    public function get_sync_logs($limit = 50) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM $this->sync_log_table ORDER BY sync_time DESC LIMIT %d",
            $limit
        );
        
        return $wpdb->get_results($query);
    }
}
