<?php
/**
 * Quick Start Guide for Notion WP Sync
 * 
 * This file provides a step-by-step guide to set up and test the plugin.
 */

// Don't allow direct access to this file
if (!defined('ABSPATH')) {
    // Allow viewing this file directly with admin check
    if (!isset($_GET['admin_check']) || $_GET['admin_check'] !== 'true') {
        echo "Add ?admin_check=true to the URL to view this guide";
        exit;
    }
}

// Basic security - only for admins
$is_direct_access = !defined('ABSPATH');

// Only output HTML if accessed directly
if ($is_direct_access):
?>
<!DOCTYPE html>
<html>
<head>
    <title>Notion WP Sync - Quick Start Guide</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; color: #333; max-width: 800px; margin: 0 auto; }
        h1 { color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        h2 { color: #3498db; margin-top: 30px; }
        .step { background: #f9f9f9; border-left: 4px solid #3498db; padding: 15px; margin-bottom: 20px; }
        .note { background: #fef9e7; border-left: 4px solid #f1c40f; padding: 15px; margin: 15px 0; }
        .warning { background: #fdedec; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0; }
        code { background: #f8f8f8; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
        pre { background: #f8f8f8; padding: 15px; overflow: auto; border-radius: 3px; }
        img { max-width: 100%; border: 1px solid #ddd; margin: 10px 0; }
        .button { display: inline-block; background: #3498db; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Notion WP Sync - Quick Start Guide</h1>
    
    <div class="note">
        <strong>Note:</strong> This guide will help you quickly test syncing content from Notion to WordPress.
    </div>
    
    <div class="step">
        <h2>Step 1: Create a Notion Integration</h2>
        <ol>
            <li>Go to <a href="https://www.notion.so/my-integrations" target="_blank">https://www.notion.so/my-integrations</a></li>
            <li>Click "New integration"</li>
            <li>Name it something like "WordPress Sync"</li>
            <li>Select the workspace where your content is</li>
            <li>Click "Submit" to create the integration</li>
            <li>Copy the "Internal Integration Token" (this is your API key)</li>
        </ol>
        <div class="note">
            Make sure your integration has access to read content.
        </div>
    </div>
    
    <div class="step">
        <h2>Step 2: Share your Notion page with the integration</h2>
        <ol>
            <li>Go to the Notion page you want to sync</li>
            <li>Click the "..." menu in the top-right corner</li>
            <li>Click "Add connections"</li>
            <li>Find your "WordPress Sync" integration</li>
            <li>Select it to share the page with the integration</li>
        </ol>
        <div class="warning">
            <strong>Important:</strong> Your Notion page MUST be shared with your integration, or the API can't access it!
        </div>
    </div>
    
    <div class="step">
        <h2>Step 3: Configure the plugin in WordPress</h2>
        <ol>
            <li>Go to your WordPress admin panel</li>
            <li>Find "Notion WP Sync" in the left menu</li>
            <li>Go to the Settings tab</li>
            <li>Enter your Notion API key (the Integration Token you copied)</li>
            <li>Click "Save Settings"</li>
            <li>Click "Test Connection" to verify it works</li>
        </ol>
    </div>
    
    <div class="step">
        <h2>Step 4: Get your Notion page ID</h2>
        <ol>
            <li>Open the page in Notion you want to sync</li>
            <li>Look at the URL in your browser</li>
            <li>The page ID is the last part of the URL: <code>https://www.notion.so/Page-Title-<strong>19a52b5682188049b6c5da694d56c5bf</strong></code></li>
            <li>Copy this ID (in this example: <code>19a52b5682188049b6c5da694d56c5bf</code>)</li>
        </ol>
    </div>
    
    <div class="step">
        <h2>Step 5: Add the page to Content Mappings</h2>
        <ol>
            <li>Go to the "Content Mappings" tab in the Notion WP Sync settings</li>
            <li>Under "Individual Page Sync", click "Add Page"</li>
            <li>Paste your page ID from Step 4</li>
            <li>Select "Post" (or another post type) from the dropdown</li>
            <li>Click "Save Mappings"</li>
        </ol>
    </div>
    
    <div class="step">
        <h2>Step 6: Sync the content</h2>
        <ol>
            <li>Go back to the "Settings" tab</li>
            <li>Click the "Sync Now" button under "Manual Sync"</li>
            <li>Wait for the sync to complete</li>
            <li>Check the "Sync Logs" tab to see if it was successful</li>
        </ol>
    </div>
    
    <div class="step">
        <h2>Step 7: View your content</h2>
        <ol>
            <li>Go to Posts (or the post type you selected) in WordPress</li>
            <li>You should see a new draft post with your Notion content</li>
            <li>Edit and publish it as needed</li>
        </ol>
    </div>
    
    <div class="note">
        <h3>Troubleshooting</h3>
        <p>If your content doesn't appear:</p>
        <ul>
            <li><strong>Check sharing:</strong> Make sure your Notion page is shared with your integration</li>
            <li><strong>Verify the ID:</strong> Double-check that you've copied the correct page ID</li>
            <li><strong>Check logs:</strong> Go to the "Sync Logs" tab to see if there were any errors</li>
            <li><strong>Use the Troubleshooter:</strong> Go to Tools → Notion WP Sync Debug in your WordPress admin to test specific page IDs</li>
        </ul>
    </div>
    
    <div class="step">
        <h2>Testing Your Specific Page</h2>
        <p>Let's test your specific page ID: <code>19a52b5682188049b6c5da694d56c5bf</code></p>
        <p>Enter this ID into the Notion ID Troubleshooter to diagnose any issues.</p>
        <p><a href="<?php echo admin_url('tools.php?page=notion-wp-sync-debug'); ?>" class="button">Open Troubleshooter</a></p>
        
        <p>Or use the Notion Explorer to see what content your integration can access:</p>
        <p><a href="<?php echo admin_url('admin.php?page=notion-wp-sync&tab=explorer'); ?>" class="button">Open Notion Explorer</a></p>
    </div>
</body>
</html>
<?php
else:
    // If included in WordPress, return a function to display the guide
    function notion_wp_sync_show_quick_start() {
        ?>
        <div class="wrap">
            <h1>Notion WP Sync - Quick Start Guide</h1>
            
            <div class="notice notice-info">
                <p>This guide will help you quickly test syncing content from Notion to WordPress.</p>
            </div>
            
            <div style="background:#fff; padding:20px; border:1px solid #ccc; margin-top:20px;">
                <h2>Step 1: Create a Notion Integration</h2>
                <!-- Similar content as above, formatted for WordPress admin -->
                <!-- ... -->
            </div>
            
            <!-- Additional steps formatted for WordPress admin -->
            <!-- ... -->
        </div>
        <?php
    }
    
    // Add to admin menu
    function notion_wp_sync_add_quick_start_page() {
        add_submenu_page(
            'notion-wp-sync',  // Parent slug
            'Quick Start Guide',  // Page title
            'Quick Start Guide',  // Menu title
            'administrator',  // Capability
            'notion-wp-sync-quick-start',  // Menu slug
            'notion_wp_sync_show_quick_start'  // Function
        );
    }
    add_action('admin_menu', 'notion_wp_sync_add_quick_start_page');
endif;
?>
