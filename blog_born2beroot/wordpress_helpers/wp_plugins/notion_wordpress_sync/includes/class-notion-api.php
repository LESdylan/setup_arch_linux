<?php
/**
 * Notion API Class
 * 
 * Handles all Notion API interactions
 */
class Notion_WP_Sync_API {
    
    private $api_key;
    private $api_version = '2022-06-28';
    private $api_url = 'https://api.notion.com/v1/';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Use constant if defined, otherwise fall back to database option
        if (defined('NOTION_API_KEY')) {
            $this->api_key = NOTION_API_KEY;
            // Log for debugging
            if (WP_DEBUG) {
                error_log('Using NOTION_API_KEY constant from wp-config.php');
            }
        } else {
            $this->api_key = get_option('notion_wp_sync_api_key', '');
            // Log for debugging
            if (WP_DEBUG) {
                error_log('Using API key from database: ' . substr($this->api_key, 0, 5) . '...');
            }
        }
    }
    
    /**
     * Test the API connection
     */
    public function test_connection() {
        // Added debug output for troubleshooting
        if (WP_DEBUG) {
            error_log('Testing Notion API connection with key: ' . substr($this->api_key, 0, 5) . '...');
        }
        
        // Check if API key is empty
        if (empty($this->api_key)) {
            update_option('notion_wp_sync_last_error', 'API key is empty');
            return false;
        }
        
        // Try a simpler endpoint first (users/me)
        $response = $this->request('GET', 'users/me');
        
        if (is_wp_error($response)) {
            // Store the error message for debugging
            $error_message = $response->get_error_message();
            update_option('notion_wp_sync_last_error', $error_message);
            if (WP_DEBUG) {
                error_log('Notion API connection failed: ' . $error_message);
            }
            return false;
        }
        
        if (WP_DEBUG) {
            error_log('Notion API connection successful');
        }
        return true;
    }
    
    /**
     * Get a list of databases
     */
    public function get_databases() {
        if (WP_DEBUG) {
            error_log('Fetching Notion databases');
        }
        
        // First, try the databases endpoint
        $response = $this->request('GET', 'databases');
        
        if (is_wp_error($response) || empty($response['results'])) {
            if (WP_DEBUG) {
                error_log('No databases found via direct database endpoint. Attempting search.');
            }
            
            // If no databases found, try to search for them
            $search_response = $this->request('POST', 'search', [
                'filter' => [
                    'value' => 'database',
                    'property' => 'object'
                ],
                'page_size' => 100
            ]);
            
            if (!is_wp_error($search_response) && !empty($search_response['results'])) {
                if (WP_DEBUG) {
                    error_log('Found ' . count($search_response['results']) . ' databases via search');
                }
                return $search_response['results'];
            }
            
            if (WP_DEBUG) {
                if (is_wp_error($response)) {
                    error_log('Database endpoint error: ' . $response->get_error_message());
                }
                if (is_wp_error($search_response)) {
                    error_log('Search endpoint error: ' . $search_response->get_error_message());
                }
            }
            
            return array();
        }
        
        if (WP_DEBUG) {
            error_log('Found ' . count($response['results']) . ' databases via direct database endpoint');
        }
        
        return $response['results'];
    }
    
    /**
     * Get a specific database
     */
    public function get_database($database_id) {
        $response = $this->request('GET', "databases/{$database_id}");
        
        if (is_wp_error($response)) {
            return null;
        }
        
        return $response;
    }
    
    /**
     * Query a database for pages
     */
    public function query_database($database_id, $filter = array()) {
        $body = array();
        
        if (!empty($filter)) {
            $body['filter'] = $filter;
        }
        
        $response = $this->request('POST', "databases/{$database_id}/query", $body);
        
        if (is_wp_error($response)) {
            return array();
        }
        
        return $response['results'];
    }
    
    /**
     * Get a specific page
     */
    public function get_page($page_id) {
        // Clean the ID for API request
        $page_id = str_replace(['-', ' ', "\n", "\t"], '', $page_id);
        
        if (WP_DEBUG) {
            error_log('NOTION API: Fetching page with ID: ' . $page_id);
        }
        
        $response = $this->request('GET', "pages/{$page_id}");
        
        if (is_wp_error($response)) {
            if (WP_DEBUG) {
                error_log('NOTION API ERROR: ' . $response->get_error_message());
                error_log('NOTION API: Using endpoint: pages/' . $page_id);
            }
            return null;
        }
        
        if (WP_DEBUG) {
            error_log('NOTION API SUCCESS: Retrieved page data');
        }
        
        return $response;
    }
    
    /**
     * Get page content (blocks)
     */
    public function get_page_content($page_id) {
        // Clean the ID for API request
        $page_id = str_replace('-', '', $page_id);
        
        if (WP_DEBUG) {
            error_log('Fetching content for Notion page: ' . $page_id);
        }
        
        $response = $this->request('GET', "blocks/{$page_id}/children?page_size=100");
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $blocks = $response['results'];
        
        // Recursively get nested blocks
        foreach ($blocks as $key => $block) {
            if (isset($block['has_children']) && $block['has_children']) {
                $blocks[$key]['children'] = $this->get_page_content($block['id']);
            }
        }
        
        return $blocks;
    }
    
    /**
     * Make a request to the Notion API
     */
    private function request($method, $endpoint, $body = null) {
        if (empty($this->api_key)) {
            if (WP_DEBUG) {
                error_log('Notion API key is missing');
                error_log('NOTION_API_KEY defined: ' . (defined('NOTION_API_KEY') ? 'Yes' : 'No'));
                error_log('DB API key exists: ' . (get_option('notion_wp_sync_api_key', '') ? 'Yes' : 'No'));
            }
            return new WP_Error('api_key_missing', 'Notion API key is missing');
        }
        
        $url = $this->api_url . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Notion-Version' => $this->api_version,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 60, // Increased timeout
            'sslverify' => true,
        );
        
        if ($body !== null) {
            $args['body'] = json_encode($body);
        }
        
        if (WP_DEBUG) {
            error_log('Making Notion API request to: ' . $url);
            error_log('Request method: ' . $method);
        }
        
        // Attempt with wp_remote_request
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            if (WP_DEBUG) {
                error_log('NOTION API ERROR: Request failed: ' . $error_msg);
            }
            
            // Try an alternative approach with curl if available
            if (function_exists('curl_init') && WP_DEBUG) {
                error_log('NOTION API: Attempting fallback with curl');
                return $this->fallback_curl_request($url, $method, $args, $body);
            }
            
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $body = json_decode($response_body, true);
        
        if (WP_DEBUG) {
            error_log('NOTION API: Response code: ' . $code);
            if ($code >= 400) {
                error_log('NOTION API: Error response body: ' . $response_body);
            }
        }
        
        if ($code < 200 || $code >= 300) {
            $error_message = isset($body['message']) ? $body['message'] : 'Unknown API error (HTTP ' . $code . ')';
            update_option('notion_wp_sync_last_error', $error_message);
            
            if (WP_DEBUG) {
                error_log('NOTION API ERROR: ' . $error_message);
            }
            
            return new WP_Error(
                'api_error',
                $error_message,
                array('status' => $code)
            );
        }
        
        return $body;
    }
    
    /**
     * Fallback method to use curl directly
     */
    private function fallback_curl_request($url, $method, $args, $body = null) {
        $ch = curl_init();
        
        // Set URL
        curl_setopt($ch, CURLOPT_URL, $url);
        
        // Set method
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
        } else if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        
        // Set headers
        $headers = array();
        foreach ($args['headers'] as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Set body if it exists
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        
        // Set other options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        // Execute request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Handle errors
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            
            if (WP_DEBUG) {
                error_log('NOTION API CURL ERROR: ' . $error);
            }
            
            return new WP_Error('api_curl_error', 'CURL Error: ' . $error);
        }
        
        curl_close($ch);
        
        // Process response
        $body = json_decode($response, true);
        
        if ($http_code < 200 || $http_code >= 300) {
            $error_message = isset($body['message']) ? $body['message'] : 'Unknown API error (HTTP ' . $http_code . ')';
            update_option('notion_wp_sync_last_error', $error_message);
            
            if (WP_DEBUG) {
                error_log('NOTION API CURL ERROR: ' . $error_message);
            }
            
            return new WP_Error(
                'api_error',
                $error_message,
                array('status' => $http_code)
            );
        }
        
        return $body;
    }
}
