<?php
/**
 * Mermaid Diagram Formatter
 * 
 * Properly formats Mermaid diagrams from Notion to WordPress
 */

class Notion_WP_Sync_Mermaid_Formatter {
    
    /**
     * Format a Mermaid diagram
     * 
     * @param string $mermaid_code The raw Mermaid diagram code
     * @return string Formatted Mermaid diagram ready for WordPress
     */
    public static function format($mermaid_code) {
        // Clean up the code
        $mermaid_code = self::clean_mermaid_code($mermaid_code);
        
        // Wrap in proper container with necessary attributes
        $formatted = '<div class="mermaid">' . $mermaid_code . '</div>';
        
        return $formatted;
    }
    
    /**
     * Clean up Mermaid code for proper rendering
     * 
     * @param string $code The raw Mermaid code
     * @return string Clean Mermaid code
     */
    private static function clean_mermaid_code($code) {
        // Remove extra whitespace at start and end
        $code = trim($code);
        
        // Fix common syntax issues
        $code = self::fix_syntax_issues($code);
        
        return $code;
    }
    
    /**
     * Fix common syntax issues in Mermaid diagrams
     * 
     * @param string $code The Mermaid code
     * @return string Fixed Mermaid code
     */
    private static function fix_syntax_issues($code) {
        // Fix flowchart syntax (ensure proper spacing after flowchart declaration)
        $code = preg_replace('/^(flowchart\s+[A-Z]+)(\s*)$/m', '$1$2', $code);
        
        // Fix style statements (ensure proper format)
        $code = preg_replace('/style\s+([^\s]+)\s+([^,]+)(?:,)?/m', 'style $1 $2', $code);
        
        // Fix quotes - replace fancy quotes with straight quotes
        $code = str_replace('"', '"', $code);
        $code = str_replace('"', '"', $code);
        $code = str_replace('‘', "'", $code);
        $code = str_replace('’', "'", $code);
        $code = str_replace('\'', "'", $code);
        
        return $code;
    }
}
