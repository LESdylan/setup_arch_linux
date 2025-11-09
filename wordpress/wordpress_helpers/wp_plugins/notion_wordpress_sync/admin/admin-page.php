<?php
/**
 * Admin Page
 * 
 * Creates the admin interface for the plugin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Helper function to clean and format Notion IDs
 */
function notion_wp_sync_clean_id($id) {
    // Basic cleaning
    $clean_id = preg_replace('/[\-\s]/', '', $id);
    
    // Remove 'f' prefix if present
    $clean_id = ltrim($clean_id, 'f');
    
    // Try to extract ID from URL if needed
    if (strpos($clean_id, 'notion.so') !== false || strlen($clean_id) > 32) {
        if (preg_match('/([a-f0-9]{32})(?:\?|$)/', $clean_id, $matches)) {
            $clean_id = $matches[1];
        } elseif (preg_match('/.*([a-f0-9]{8}[a-f0-9]{4}[a-f0-9]{4}[a-f0-9]{4}[a-f0-9]{12}).*/', $clean_id, $matches)) {
            $clean_id = str_replace('-', '', $matches[1]);
        }
    }
    
    return $clean_id;
}

/**
 * Add admin menu with administrator capability
 */
function notion_wp_sync_add_admin_menu() {
    $hook = add_menu_page(
        'Notion WP Sync',         // Page title
        'Notion WP Sync',         // Menu title
        'administrator',          // Keep administrator capability for security
        'notion-wp-sync',         // Menu slug
        'notion_wp_sync_admin_page', // Restore full admin page function
        'dashicons-database-import',  // Icon
        30                         // Position
    );
    
    // Load scripts and styles only on our admin page
    add_action('load-' . $hook, 'notion_wp_sync_load_admin_scripts');
}
add_action('admin_menu', 'notion_wp_sync_add_admin_menu');

/**
 * Load admin scripts and styles
 */
function notion_wp_sync_load_admin_scripts() {
    add_action('admin_enqueue_scripts', 'notion_wp_sync_enqueue_admin_scripts');
}

/**
 * Enqueue admin scripts and styles
 */
function notion_wp_sync_enqueue_admin_scripts() {
    wp_enqueue_style('notion-wp-sync-admin-css', NOTION_WP_SYNC_PLUGIN_URL . 'admin/admin.css', array(), NOTION_WP_SYNC_VERSION);
    wp_enqueue_script('notion-wp-sync-admin-js', NOTION_WP_SYNC_PLUGIN_URL . 'admin/admin.js', array('jquery'), NOTION_WP_SYNC_VERSION, true);
    
    wp_localize_script('notion-wp-sync-admin-js', 'notionWpSync', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('notion_wp_sync_nonce')
    ));
}

/**
 * Register settings
 */
function notion_wp_sync_register_settings() {
    register_setting('notion_wp_sync_group', 'notion_wp_sync_api_key');
    register_setting('notion_wp_sync_group', 'notion_wp_sync_sync_interval');
    register_setting('notion_wp_sync_group', 'notion_wp_sync_databases');
    register_setting('notion_wp_sync_group', 'notion_wp_sync_pages');
}
add_action('admin_init', 'notion_wp_sync_register_settings');

/**
 * Admin page content
 */
function notion_wp_sync_admin_page() {
    // Check user is an administrator
    if (!current_user_can('administrator')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    // Get current tab
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
    
    // Get API key
    $api_key = get_option('notion_wp_sync_api_key', '');
    $has_api_key = !empty($api_key) || defined('NOTION_API_KEY');
    
    // Test connection if API key exists
    $connection_status = false;
    if ($has_api_key && class_exists('Notion_WP_Sync_API')) {
        $api = new Notion_WP_Sync_API();
        $connection_status = $api->test_connection();
    }
    
    ?>
    <div class="wrap notion-wp-sync-admin">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <h2 class="nav-tab-wrapper">
            <a href="?page=notion-wp-sync&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
            <a href="?page=notion-wp-sync&tab=mappings" class="nav-tab <?php echo $active_tab == 'mappings' ? 'nav-tab-active' : ''; ?>">Content Mappings</a>
            <a href="?page=notion-wp-sync&tab=explorer" class="nav-tab <?php echo $active_tab == 'explorer' ? 'nav-tab-active' : ''; ?>">Notion Explorer</a>
            <a href="?page=notion-wp-sync&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">Sync Logs</a>
            <a href="?page=notion-wp-sync&tab=debug" class="nav-tab <?php echo $active_tab == 'debug' ? 'nav-tab-active' : ''; ?>">System Check</a>
        </h2>
        
        <div class="tab-content">
            <?php
            if ($active_tab == 'settings') {
                notion_wp_sync_settings_tab($has_api_key, $connection_status);
            } elseif ($active_tab == 'mappings') {
                notion_wp_sync_mappings_tab($has_api_key, $connection_status);
            } elseif ($active_tab == 'explorer') {
                notion_wp_sync_explorer_tab($has_api_key, $connection_status);
            } elseif ($active_tab == 'logs') {
                notion_wp_sync_logs_tab();
            } elseif ($active_tab == 'debug') {
                notion_wp_sync_debug_tab();
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Settings tab content
 */
function notion_wp_sync_settings_tab($has_api_key, $connection_status) {
    $sync_interval = get_option('notion_wp_sync_sync_interval', 'hourly');
    $last_sync = get_option('notion_wp_sync_last_sync', '');
    $api_key = get_option('notion_wp_sync_api_key', '');
    
    // Connection status message
    $status_class = $connection_status ? 'success' : 'error';
    $status_message = $connection_status ? 'Successfully connected to Notion API' : 'Failed to connect to Notion API';
    $last_error = get_option('notion_wp_sync_last_error', '');
    if (!$connection_status && !empty($last_error)) {
        $status_message .= ': ' . $last_error;
    }
    
    ?>
    <div class="notion-wp-sync-settings">
        <form method="post" action="options.php">
            <?php settings_fields('notion_wp_sync_group'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="notion_wp_sync_api_key">Notion API Key</label>
                    </th>
                    <td>
                        <?php if (defined('NOTION_API_KEY')): ?>
                            <p><strong>API key is defined in wp-config.php</strong></p>
                            <p class="description">
                                For increased security, your API key is stored in your wp-config.php file.
                                <br>Current key value: <code><?php echo esc_html(substr(NOTION_API_KEY, 0, 5) . '...' . substr(NOTION_API_KEY, -4)); ?></code>
                                <br>To modify it, edit your wp-config.php file.
                            </p>
                        <?php else: ?>
                            <input type="password" 
                                   id="notion_wp_sync_api_key" 
                                   name="notion_wp_sync_api_key" 
                                   value="<?php echo esc_attr($api_key); ?>" 
                                   class="regular-text">
                            <p class="description">
                                Get your API key from the <a href="https://www.notion.so/my-integrations" target="_blank">Notion Integrations page</a>.
                                For increased security, consider defining NOTION_API_KEY in your wp-config.php file.
                            </p>
                        <?php endif; ?>
                        
                        <div id="notion-test-connection-container" style="margin-top: 10px;">
                            <button type="button" id="notion-wp-test-connection" class="button button-secondary">
                                Test Connection
                            </button>
                            <span id="notion-connection-result" style="margin-left: 10px; display: inline-block; padding: 5px;">
                                <?php if ($has_api_key): ?>
                                    <span class="connection-status <?php echo $status_class; ?>">
                                        <span class="dashicons dashicons-<?php echo $connection_status ? 'yes' : 'no'; ?>"></span> 
                                        <?php echo $status_message; ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="notion_wp_sync_sync_interval">Sync Interval</label>
                    </th>
                    <td>
                        <select id="notion_wp_sync_sync_interval" name="notion_wp_sync_sync_interval">
                            <option value="hourly" <?php selected($sync_interval, 'hourly'); ?>>Hourly</option>
                            <option value="twicedaily" <?php selected($sync_interval, 'twicedaily'); ?>>Twice Daily</option>
                            <option value="daily" <?php selected($sync_interval, 'daily'); ?>>Daily</option>
                        </select>
                        
                        <?php if (!empty($last_sync)): ?>
                            <p class="description">Last sync: <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync)); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Save Settings'); ?>
        </form>
        
        <?php if ($has_api_key && $connection_status): ?>
            <div class="notion-wp-sync-manual-sync">
                <h2>Manual Sync</h2>
                <p>Click the button below to manually sync content from Notion to WordPress.</p>
                <button id="notion-wp-sync-manual-sync-button" class="button button-primary">Sync Now</button>
                <div id="notion-wp-sync-sync-status"></div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Mappings tab content
 */
function notion_wp_sync_mappings_tab($has_api_key, $connection_status) {
    if (!$has_api_key || !$connection_status) {
        echo '<div class="notice notice-error"><p>Please configure your Notion API key in the Settings tab first.</p></div>';
        return;
    }
    
    $api = new Notion_WP_Sync_API();
    $db = new Notion_WP_Sync_DB();
    
    // Get databases
    $databases = $api->get_databases();
    $current_databases = get_option('notion_wp_sync_databases', array());
    $current_pages = get_option('notion_wp_sync_pages', array());
    
    // Get post types
    $post_types = get_post_types(array('public' => true), 'objects');
    
    ?>
    <div class="notion-wp-sync-mappings">
        <form method="post" action="options.php">
            <?php settings_fields('notion_wp_sync_group'); ?>
            
            <h2>Database Mappings</h2>
            <p>Select Notion databases to sync and map them to WordPress post types.</p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Sync</th>
                        <th>Database Name</th>
                        <th>WordPress Post Type</th>
                    </tr>
                </thead>
                <tbody id="notion-databases">
                    <?php foreach ($databases as $database): ?>
                        <?php 
                        $db_id = $database['id'];
                        $db_title = isset($database['title'][0]['plain_text']) ? $database['title'][0]['plain_text'] : 'Untitled Database';
                        $is_selected = false;
                        $selected_post_type = 'post';
                        
                        // Check if this database is already mapped
                        foreach ($current_databases as $current_db) {
                            if ($current_db['id'] === $db_id) {
                                $is_selected = true;
                                $selected_post_type = $current_db['post_type'];
                                break;
                            }
                        }
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="database_selected[]" value="<?php echo $db_id; ?>" <?php checked($is_selected); ?>>
                            </td>
                            <td><?php echo esc_html($db_title); ?></td>
                            <td>
                                <select name="database_post_type[<?php echo $db_id; ?>]">
                                    <?php foreach ($post_types as $type => $object): ?>
                                        <option value="<?php echo $type; ?>" <?php selected($selected_post_type, $type); ?>>
                                            <?php echo esc_html($object->labels->singular_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($databases)): ?>
                        <tr>
                            <td colspan="3">No databases found in your Notion workspace.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <h2>Individual Page Sync</h2>
            <p>Add individual Notion pages to sync by entering their page IDs.</p>
            
            <table class="wp-list-table widefat fixed striped" id="notion-pages-table">
                <thead>
                    <tr>
                        <th>Notion Page ID</th>
                        <th>WordPress Post Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="notion-pages">
                    <?php foreach ($current_pages as $index => $page): ?>
                        <tr>
                            <td>
                                <input type="text" name="notion_wp_sync_pages[<?php echo $index; ?>][id]" value="<?php echo esc_attr($page['id']); ?>" class="regular-text">
                            </td>
                            <td>
                                <select name="notion_wp_sync_pages[<?php echo $index; ?>][post_type]">
                                    <?php foreach ($post_types as $type => $object): ?>
                                        <option value="<?php echo $type; ?>" <?php selected($page['post_type'], $type); ?>>
                                            <?php echo esc_html($object->labels->singular_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button type="button" class="button remove-page">Remove</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <tr class="page-template" style="display: none;">
                        <td>
                            <input type="text" name="notion_wp_sync_pages[__INDEX__][id]" value="" class="regular-text">
                        </td>
                        <td>
                            <select name="notion_wp_sync_pages[__INDEX__][post_type]">
                                <?php foreach ($post_types as $type => $object): ?>
                                    <option value="<?php echo $type; ?>">
                                        <?php echo esc_html($object->labels->singular_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <button type="button" class="button remove-page">Remove</button>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">
                            <button type="button" class="button" id="add-page">Add Page</button>
                        </td>
                    </tr>
                </tfoot>
            </table>
            
            <input type="hidden" id="notion_wp_sync_databases" name="notion_wp_sync_databases" value="">
            
            <?php submit_button('Save Mappings'); ?>
        </form>
        
        <h2>Existing Mappings</h2>
        <p>These are the current content mappings between Notion and WordPress.</p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Notion ID</th>
                    <th>Notion Type</th>
                    <th>WordPress Post ID</th>
                    <th>WordPress Post Type</th>
                    <th>Last Synced</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $mappings = $db->get_all_mappings();
                
                if (is_array($mappings) || is_object($mappings)):
                    foreach ($mappings as $mapping): 
                        $post = get_post($mapping->wp_post_id);
                        $post_title = $post ? $post->post_title : 'Post not found';
                    ?>
                        <tr>
                            <td><?php echo esc_html($mapping->notion_id); ?></td>
                            <td><?php echo esc_html($mapping->notion_type); ?></td>
                            <td>
                                <a href="<?php echo get_edit_post_link($mapping->wp_post_id); ?>" target="_blank">
                                    <?php echo esc_html($mapping->wp_post_id); ?> (<?php echo esc_html($post_title); ?>)
                                </a>
                            </td>
                            <td><?php echo esc_html($mapping->wp_post_type); ?></td>
                            <td><?php echo esc_html($mapping->last_synced); ?></td>
                        </tr>
                    <?php endforeach; 
                endif; ?>
                
                <?php if (empty($mappings)): ?>
                    <tr>
                        <td colspan="5">No mappings found. Sync some content first.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Process the mappings form submission
 */
function notion_wp_sync_process_mappings_form() {
    if (!isset($_POST['option_page']) || $_POST['option_page'] !== 'notion_wp_sync_group') {
        return;
    }
    
    // Process databases
    if (isset($_POST['notion_wp_sync_databases'])) {
        $databases_json = sanitize_text_field($_POST['notion_wp_sync_databases']);
        if (!empty($databases_json)) {
            $databases = json_decode(stripslashes($databases_json), true);
            if (is_array($databases)) {
                update_option('notion_wp_sync_databases', $databases);
                if (WP_DEBUG) {
                    error_log('NOTION WP: Saved ' . count($databases) . ' database mappings');
                }
            }
        }
    }
    
    // Process individual pages - IMPROVED VERSION
    if (isset($_POST['notion_wp_sync_pages']) && is_array($_POST['notion_wp_sync_pages'])) {
        $pages = array();
        $index = 0;
        
        foreach ($_POST['notion_wp_sync_pages'] as $page) {
            if (!isset($page['id']) || empty($page['id'])) {
                continue;  // Skip empty entries
            }
            
            // Extra detailed logging for debugging
            $raw_id = $page['id'];
            error_log('Processing page ID: ' . $raw_id);
            
            // Handle different ID formats
            $clean_id = notion_wp_sync_clean_id($raw_id);
            
            // Additional cleaning for URLs
            if (strpos($clean_id, 'notion.so') !== false) {
                // Extract just the ID from the full URL
                preg_match('/-([a-f0-9]{32})$|([a-f0-9]{32})$/', $clean_id, $matches);
                if (!empty($matches[1])) {
                    $clean_id = $matches[1];
                } elseif (!empty($matches[2])) {
                    $clean_id = $matches[2];
                }
            }
            
            // More logging for debugging
            error_log('Raw ID: ' . $raw_id . ', Cleaned ID: ' . $clean_id);
            
            $pages[$index] = array(
                'id' => $clean_id,
                'post_type' => sanitize_text_field($page['post_type'])
            );
            
            $index++;
        }
        
        // Save the cleaned page IDs
        update_option('notion_wp_sync_pages', $pages);
        error_log('NOTION WP: Saved ' . count($pages) . ' individual page mappings');
        
        // Special handling for problematic page
        $specific_page = '19a52b5682188049b6c5da694d56c5bf';
        error_log('Checking if specific page ID is in saved pages: ' . $specific_page);
        $found = false;
        foreach ($pages as $page) {
            error_log('Comparing with: ' . $page['id']);
            if ($page['id'] === $specific_page) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            error_log('SPECIFIC PAGE NOT FOUND IN SAVED PAGES - ADDING MANUALLY');
            $pages[] = [
                'id' => $specific_page,
                'post_type' => 'post'
            ];
            update_option('notion_wp_sync_pages', $pages);
        }
    }
}
add_action('admin_init', 'notion_wp_sync_process_mappings_form');

/**
 * Logs tab content
 */
function notion_wp_sync_logs_tab() {
    $db = new Notion_WP_Sync_DB();
    $logs = method_exists($db, 'get_sync_logs') ? $db->get_sync_logs() : array();
    
    ?>
    <div class="notion-wp-sync-logs">
        <h2>Sync Logs</h2>
        <p>Recent sync activity between Notion and WordPress.</p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Notion ID</th>
                    <th>WordPress Post</th>
                    <th>Status</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (is_array($logs) || is_object($logs)):
                    foreach ($logs as $log): 
                        $status_class = isset($log->status) && $log->status === 'success' ? 'success' : 'error';
                        $post = isset($log->wp_post_id) ? get_post($log->wp_post_id) : null;
                        $post_title = $post ? $post->post_title : 'N/A';
                    ?>
                        <tr>
                            <td><?php echo esc_html($log->sync_time); ?></td>
                            <td><?php echo esc_html($log->notion_id); ?></td>
                            <td>
                                <?php if ($post): ?>
                                    <a href="<?php echo get_edit_post_link($log->wp_post_id); ?>" target="_blank">
                                        <?php echo esc_html($post_title); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo esc_html($post_title); ?>
                                <?php endif; ?>
                            </td>
                            <td class="status-<?php echo $status_class; ?>"><?php echo esc_html($log->status); ?></td>
                            <td><?php echo esc_html($log->message); ?></td>
                        </tr>
                    <?php endforeach;
                endif; ?>
                
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5">No sync logs found. Try syncing content first.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Debug tab content
 */
function notion_wp_sync_debug_tab() {
    ?>
    <div class="notion-wp-sync-debug">
        <h2>System Check</h2>
        <p>This tool checks your system configuration and helps identify any issues with the plugin.</p>
        
        <button id="run-system-check" class="button button-primary">Run System Check</button>
        
        <div id="system-check-results" style="margin-top: 20px; display: none;">
            <h3>System Check Results</h3>
            
            <div class="system-check-section" id="wordpress-check">
                <h4>WordPress</h4>
                <div class="results-content"></div>
            </div>
            
            <div class="system-check-section" id="plugin-check">
                <h4>Plugin Files</h4>
                <div class="results-content"></div>
            </div>
            
            <div class="system-check-section" id="database-check">
                <h4>Database</h4>
                <div class="results-content"></div>
            </div>
            
            <div class="system-check-section" id="api-check">
                <h4>Notion API Connection</h4>
                <div class="results-content"></div>
            </div>
            
            <div class="system-check-section" id="php-check">
                <h4>PHP Requirements</h4>
                <div class="results-content"></div>
            </div>
        </div>
        
        <h2 style="margin-top: 30px;">Error Log</h2>
        <p>Recent errors recorded by the plugin:</p>
        
        <?php
        $error_log = function_exists('Notion_WP_Sync_Debug::get_error_log') ? Notion_WP_Sync_Debug::get_error_log() : get_option('notion_wp_sync_error_log', array());
        
        if (empty($error_log)) {
            echo '<p>No errors have been logged.</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Time</th><th>Error Message</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($error_log as $error) {
                echo '<tr>';
                echo '<td>' . esc_html($error['time']) . '</td>';
                echo '<td>' . esc_html($error['message']) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        ?>
        
        <h2 style="margin-top: 30px;">Plugin Information</h2>
        <table class="wp-list-table widefat fixed" style="max-width: 500px;">
            <tbody>
                <tr>
                    <th>Plugin Version</th>
                    <td><?php echo NOTION_WP_SYNC_VERSION; ?></td>
                </tr>
                <tr>
                    <th>Plugin Directory</th>
                    <td><?php echo esc_html(NOTION_WP_SYNC_PLUGIN_DIR); ?></td>
                </tr>
                <tr>
                    <th>Plugin URL</th>
                    <td><?php echo esc_html(NOTION_WP_SYNC_PLUGIN_URL); ?></td>
                </tr>
                <tr>
                    <th>PHP Version</th>
                    <td><?php echo phpversion(); ?></td>
                </tr>
                <tr>
                    <th>WordPress Version</th>
                    <td><?php echo get_bloginfo('version'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Notion Explorer tab content
 */
function notion_wp_sync_explorer_tab($has_api_key, $connection_status) {
    if (!$has_api_key || !$connection_status) {
        echo '<div class="notice notice-error"><p>Please configure your Notion API key in the Settings tab first.</p></div>';
        return;
    }
    
    // Get all Notion resources
    $resources = Notion_WP_Sync_Debug::get_all_notion_resources();
    ?>
    <div class="notion-wp-sync-explorer">
        <h2>Notion Content Explorer</h2>
        <p>This shows all Notion content your integration can access. If no databases appear below, you need to:</p>
        
        <div class="notice notice-info">
            <h3>How to Share Databases with Your Integration</h3>
            <ol>
                <li>Open your Notion database/page in a browser</li>
                <li>Click the "..." menu in the top-right corner</li>
                <li>Select "Add connections"</li>
                <li>Find your integration "wp-sync" and select it</li>
                <li>Return to this page and refresh</li>
            </ol>
            <p><img src="<?php echo NOTION_WP_SYNC_PLUGIN_URL; ?>admin/images/notion-share-guide.jpg" alt="How to share with integration" style="max-width:600px; border:1px solid #ccc;"></p>
        </div>
        
        <button id="refresh-notion-resources" class="button button-primary">Refresh Notion Resources</button>
        
        <?php if ($resources['status'] === 'error'): ?>
            <div class="notice notice-error">
                <p><?php echo esc_html($resources['message']); ?></p>
            </div>
        <?php else: ?>
            <h3>Databases (<?php echo count($resources['resources']['databases']); ?>)</h3>
            <?php if (empty($resources['resources']['databases'])): ?>
                <div class="notice notice-warning inline">
                    <p>No databases found! Make sure you've shared your Notion databases with your integration.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>ID</th>
                            <th>Last Edited</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resources['resources']['databases'] as $db): ?>
                            <tr>
                                <td><?php echo esc_html($db['title']); ?></td>
                                <td><code><?php echo esc_html($db['id']); ?></code></td>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($db['last_edited_time']))); ?></td>
                                <td>
                                    <a href="?page=notion-wp-sync&tab=mappings" class="button button-small">Map to WordPress</a>
                                    <?php if (!empty($db['url'])): ?>
                                        <a href="<?php echo esc_url($db['url']); ?>" target="_blank" class="button button-small">View in Notion</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <h3>Pages (<?php echo count($resources['resources']['pages']); ?>)</h3>
            <?php if (empty($resources['resources']['pages'])): ?>
                <div class="notice notice-warning inline">
                    <p>No pages found! Make sure you've shared your Notion pages with your integration.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>ID</th>
                            <th>Last Edited</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resources['resources']['pages'] as $page): ?>
                            <tr>
                                <td><?php echo esc_html($page['title']); ?></td>
                                <td><code><?php echo esc_html($page['id']); ?></code></td>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($page['last_edited_time']))); ?></td>
                                <td>
                                    <a href="?page=notion-wp-sync&tab=mappings" class="button button-small">Map to WordPress</a>
                                    <?php if (!empty($page['url'])): ?>
                                        <a href="<?php echo esc_url($page['url']); ?>" target="_blank" class="button button-small">View in Notion</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
?>
