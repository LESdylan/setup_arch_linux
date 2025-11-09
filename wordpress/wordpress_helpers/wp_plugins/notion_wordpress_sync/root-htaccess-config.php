<?php
/**
 * This file provides configuration for your WordPress site root .htaccess
 * 
 * It's not used directly, but you should copy the text below to your WordPress root .htaccess file
 */

/**
 * Add the following to your WordPress root .htaccess file:
 
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress

# Allow direct access to Notion WP Sync tools from local network
<Files ~ "^(notion-test|mermaid-test|network-test|localhost-helper|path-fix|test-page)\.php$">
    # Allow local network direct access
    <RequireAny>
        Require ip 127.0.0.1
        Require ip ::1
        Require ip 192.168.1.133
    </RequireAny>
</Files>
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>Root .htaccess Configuration</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; line-height: 1.6; }
        h1, h2 { color: #2c3e50; }
        .code-block { background: #f7f9fb; border: 1px solid #e3e6e8; padding: 15px; overflow: auto; font-family: monospace; }
        .info { background: #e7f5ff; border-left: 4px solid #3498db; padding: 10px 15px; margin: 20px 0; }
        .warning { background: #fff8e5; border-left: 4px solid #f1c40f; padding: 10px 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>WordPress Root .htaccess Configuration</h1>
    
    <div class='info'>
        <p>To fix access issues with Notion WP Sync test tools, you need to add the configuration below to your WordPress 
        root .htaccess file (typically located at <code>/var/www/html/.htaccess</code>).</p>
    </div>
    
    <h2>Configuration to Add</h2>
    
    <pre class='code-block'># BEGIN WordPress
&lt;IfModule mod_rewrite.c&gt;
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
&lt;/IfModule&gt;
# END WordPress

# Allow direct access to Notion WP Sync tools from local network
&lt;Files ~ \"^(notion-test|mermaid-test|network-test|localhost-helper|path-fix|test-page)\.php$\"&gt;
    # Allow local network direct access
    &lt;RequireAny&gt;
        Require ip 127.0.0.1
        Require ip ::1
        Require ip 192.168.1.133
    &lt;/RequireAny&gt;
&lt;/Files&gt;</pre>
    
    <div class='warning'>
        <h3>Important Notes:</h3>
        <ul>
            <li>The above configuration allows direct access to these files from your local network IP (192.168.1.133)</li>
            <li>This is suitable for development but should be removed in production environments</li>
            <li>Make sure to add this configuration to your WordPress root .htaccess file, not just the plugin directory</li>
        </ul>
    </div>
    
    <h2>How to Apply This Configuration</h2>
    <ol>
        <li>Connect to your server via SSH or FTP</li>
        <li>Navigate to the WordPress root directory (<code>/var/www/html/</code>)</li>
        <li>Edit the .htaccess file (or create it if it doesn't exist)</li>
        <li>Add the configuration shown above</li>
        <li>Save the file and test access to the diagnostic tools</li>
    </ol>
</body>
</html>";
