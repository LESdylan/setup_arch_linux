<?php
/**
 * Troubleshooting helper for Notion WP Sync
 * 
 * IMPORTANT: Delete this file after debugging is complete.
 */

// Don't allow direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add troubleshooting information to help debug plugin issues
 */
function notion_wp_sync_add_troubleshooting_info() {
    // Only show for administrators
    if (!current_user_can('administrator')) {
        return;
    }
    
    ?>
    <div class="wrap">
        <h1>Notion WP Sync - Troubleshooting</h1>
        
        <div class="notice notice-info">
            <p><strong>Navigation Help:</strong> The correct URL for this page is 
               <code><?php echo admin_url('tools.php?page=notion-wp-sync-debug'); ?></code>
               or access it from the WordPress admin menu under "Tools" → "Notion WP Sync Debug"
            </p>
            <p>
                <a href="<?php echo admin_url('admin.php?page=notion-wp-sync'); ?>" class="button">Go to Main Plugin Settings</a>
                <a href="<?php echo admin_url('admin.php?page=notion-wp-sync&tab=mappings'); ?>" class="button">Go to Content Mappings</a>
                <a href="<?php echo admin_url('admin.php?page=notion-wp-emergency'); ?>" class="button">Go to Emergency Diagnostics</a>
            </p>
        </div>
        
        <div style="background:#fff; padding:20px; border:1px solid #ccc; margin-top:20px;">
            <h2>Filesystem Permissions</h2>
            <?php
            $plugin_dir = WP_PLUGIN_DIR . '/notion_wordpress_sync';
            $files_to_check = array(
                'notion-wp-sync.php',
                'admin/admin-page.php',
                'includes/class-db.php',
                'includes/class-notion-api.php',
                'includes/class-content-sync.php',
                'includes/class-debug.php',
            );
            
            echo '<ul>';
            foreach ($files_to_check as $file) {
                $full_path = $plugin_dir . '/' . $file;
                if (file_exists($full_path)) {
                    $perms = substr(sprintf('%o', fileperms($full_path)), -4);
                    $readable = is_readable($full_path) ? 'Yes' : 'No';
                    echo "<li>{$file} - Exists, Permissions: {$perms}, Readable: {$readable}</li>";
                } else {
                    echo "<li style='color:red;'>{$file} - Missing</li>";
                }
            }
            echo '</ul>';
            ?>
            
            <h2>WordPress Information</h2>
            <ul>
                <li>WordPress Version: <?php echo get_bloginfo('version'); ?></li>
                <li>PHP Version: <?php echo phpversion(); ?></li>
                <li>Plugin Directory: <?php echo WP_PLUGIN_DIR; ?></li>
                <li>Site URL: <?php echo get_site_url(); ?></li>
                <li>Home URL: <?php echo get_home_url(); ?></li>
            </ul>
            
            <h2>Current User Information</h2>
            <?php 
            $current_user = wp_get_current_user();
            ?>
            <ul>
                <li>User Login: <?php echo $current_user->user_login; ?></li>
                <li>User ID: <?php echo $current_user->ID; ?></li>
                <li>Roles: <?php echo implode(', ', $current_user->roles); ?></li>
                <li>Is Admin: <?php echo current_user_can('administrator') ? 'Yes' : 'No'; ?></li>
                <li>Is Super Admin: <?php echo is_super_admin() ? 'Yes' : 'No'; ?></li>
            </ul>
        </div>
        
        <?php 
        // Only load the ID tester if we can confirm the API class exists
        if (class_exists('Notion_WP_Sync_API')) {
            notion_wp_sync_add_id_tester();
        } else {
            echo '<div style="background:#fff; padding:20px; border:1px solid #ccc; margin-top:20px;">';
            echo '<h2>Notion ID Troubleshooter</h2>';
            echo '<p>The Notion API class is not available. Please make sure all plugin files are properly loaded.</p>';
            echo '</div>';
        }
        ?>
    </div>
    <?php
}

// Add to admin menu
function notion_wp_sync_add_troubleshooting_page() {
    // Add to Tools menu
    add_management_page(
        'Notion WP Sync Troubleshooting',
        'Notion WP Sync Debug',
        'administrator',
        'notion-wp-sync-debug',
        'notion_wp_sync_add_troubleshooting_info'
    );
    
    // Also add to main Notion WP Sync menu for better visibility
    if (function_exists('add_submenu_page')) { // Check if function exists to avoid errors
        add_submenu_page(
            'notion-wp-sync',  // Parent slug
            'Notion ID Troubleshooter',  // Page title
            'ID Troubleshooter',  // Menu title
            'administrator',  // Capability
            'notion-wp-sync-debug',  // Menu slug (same as above)
            'notion_wp_sync_add_troubleshooting_info'  // Function
        );
    }
}
add_action('admin_menu', 'notion_wp_sync_add_troubleshooting_page');

/**
 * Add a debug form for testing specific page IDs
 */
function notion_wp_sync_add_id_tester() {
    if (!current_user_can('administrator')) {
        return;
    }
    
    $test_id = isset($_POST['test_notion_id']) ? sanitize_text_field($_POST['test_notion_id']) : '';
    $test_results = '';
    
    if (!empty($test_id) && isset($_POST['test_notion_action']) && $_POST['test_notion_action'] === 'debug_id') {
        try {
            // Process the test ID
            $raw_id = $test_id;
            $clean_id = preg_replace('/[\-\s]/', '', $test_id);
            $clean_id = ltrim($clean_id, 'f');
            
            if (strpos($clean_id, 'notion.so') !== false || strlen($clean_id) > 32) {
                if (preg_match('/([a-f0-9]{32})(?:\?|$)/', $clean_id, $matches)) {
                    $clean_id = $matches[1];
                } elseif (preg_match('/.*([a-f0-9]{8}[a-f0-9]{4}[a-f0-9]{4}[a-f0-9]{4}[a-f0-9]{12}).*/', $clean_id, $matches)) {
                    $clean_id = str_replace('-', '', $matches[1]);
                }
            }
            
            // Make sure API is instantiated safely
            if (class_exists('Notion_WP_Sync_API')) {
                $api = new Notion_WP_Sync_API();
                
                $test_results .= "<h3>ID Analysis</h3>";
                $test_results .= "<ul>";
                $test_results .= "<li><strong>Raw ID:</strong> {$raw_id}</li>";
                $test_results .= "<li><strong>Processed ID:</strong> {$clean_id}</li>";
                $test_results .= "<li><strong>ID Length:</strong> " . strlen($clean_id) . "</li>";
                $test_results .= "</ul>";
                
                $test_results .= "<h3>API Test Result</h3>";
                
                // Only make the API request if we have a valid-looking ID
                if (strlen($clean_id) == 32 && preg_match('/^[a-f0-9]+$/', $clean_id)) {
                    $response = $api->request('GET', "pages/{$clean_id}");
                    
                    if (is_wp_error($response)) {
                        $test_results .= "<p style='color:red;'>Error: " . $response->get_error_message() . "</p>";
                    } else {
                        $test_results .= "<p style='color:green;'>Success! The API can access this page.</p>";
                        $page_title = "Unknown";
                        
                        // Try to extract the title
                        if (isset($response['properties']['title']['title'])) {
                            $title_parts = $response['properties']['title']['title'];
                            $page_title = "";
                            foreach ($title_parts as $part) {
                                $page_title .= $part['plain_text'];
                            }
                        } elseif (isset($response['properties']['Name']['title'])) {
                            $title_parts = $response['properties']['Name']['title'];
                            $page_title = "";
                            foreach ($title_parts as $part) {
                                $page_title .= $part['plain_text'];
                            }
                        }
                        
                        $test_results .= "<p><strong>Page Title:</strong> " . esc_html($page_title) . "</p>";
                    }
                } else {
                    $test_results .= "<p style='color:orange;'>Warning: The ID doesn't appear to be a valid Notion ID format.</p>";
                }
            } else {
                $test_results .= "<p style='color:red;'>Error: Notion API class not available.</p>";
            }
        } catch (Exception $e) {
            $test_results .= "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
        }
    }
    
    ?>
    <div style="background:#fff; padding:20px; border:1px solid #ccc; margin-top:20px;">
        <h2>Notion ID Troubleshooter</h2>
        <p>Use this tool to test and analyze Notion IDs that aren't working properly.</p>
        
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="test_notion_id">Notion ID or URL</label></th>
                    <td>
                        <input type="text" name="test_notion_id" id="test_notion_id" value="<?php echo esc_attr($test_id); ?>" class="regular-text">
                        <p class="description">Enter a Notion page ID, URL, or any format that isn't working correctly.</p>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="test_notion_action" value="debug_id">
            <?php submit_button('Test ID', 'primary', 'submit', false); ?>
        </form>
        
        <?php if (!empty($test_results)): ?>
            <div style="margin-top:20px; padding:15px; background:#f9f9f9; border:1px solid #ddd;">
                <h2>Test Results</h2>
                <?php echo $test_results; ?>
                
                <h3>Manual Fix Instructions</h3>
                <p>To fix this page:</p>
                <ol>
                    <li>Go to the "Content Mappings" tab</li>
                    <li>Under "Individual Page Sync", click "Add Page"</li>
                    <li>Enter exactly this ID: <code><?php echo esc_html($clean_id); ?></code></li>
                    <li>Select the appropriate post type</li>
                    <li>Click "Save Mappings"</li>
                    <li>Go to the "Settings" tab and click "Sync Now"</li>
                </ol>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// Don't add the action hook directly, we're calling the function manually
// with safeguards in place
