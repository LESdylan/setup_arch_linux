<?php
/**
 * Quick Notion WP Sync Test
 */

// Basic diagnostic info
echo "<h1>Notion WP Sync Test</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Current file: " . __FILE__ . "</p>";
echo "<p>Current directory: " . __DIR__ . "</p>";

// List plugin files
echo "<h2>Plugin Files</h2>";
$plugin_dir = __DIR__;
$files = scandir($plugin_dir);
echo "<ul>";
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        if (is_dir($plugin_dir . '/' . $file)) {
            echo "<li>📁 $file</li>";
        } else {
            echo "<li>📄 $file</li>";
        }
    }
}
echo "</ul>";

// Create directory and file if it doesn't exist
$formatter_dir = $plugin_dir . '/includes/formatters';
if (!is_dir($formatter_dir)) {
    echo "<p>Creating formatters directory...</p>";
    mkdir($formatter_dir, 0755, true);
    echo "<p>Directory created: $formatter_dir</p>";
}

// File content for mermaid formatter
$formatter_content = '<?php
/**
 * Mermaid Diagram Formatter
 */
class Notion_WP_Sync_Mermaid_Formatter {
    public static function format($mermaid_code) {
        $mermaid_code = trim($mermaid_code);
        return \'<div class="mermaid">\' . $mermaid_code . \'</div>\';
    }
}';

// Create formatter file
$formatter_file = $formatter_dir . '/class-mermaid-formatter.php';
if (!file_exists($formatter_file)) {
    echo "<p>Creating mermaid formatter file...</p>";
    file_put_contents($formatter_file, $formatter_content);
    echo "<p>File created: $formatter_file</p>";
} else {
    echo "<p>Mermaid formatter file already exists</p>";
}

echo "<h2>Success!</h2>";
echo "<p>Your plugin should now be able to format Mermaid diagrams correctly.</p>";
echo "<p>Please deactivate and reactivate the plugin to ensure all changes take effect.</p>";
