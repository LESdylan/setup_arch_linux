<?php
/**
 * Content Sync Class
 * 
 * Handles the actual content synchronization
 */
class Notion_WP_Sync_Content_Sync {
    
    private $api;
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new Notion_WP_Sync_API();
        $this->db = new Notion_WP_Sync_DB();
    }
    
    /**
     * Sync all configured content
     */
    public function sync_all() {
        $databases_to_sync = get_option('notion_wp_sync_databases', array());
        $pages_to_sync = get_option('notion_wp_sync_pages', array());
        
        $success = true;
        
        // Sync databases
        foreach ($databases_to_sync as $database) {
            $result = $this->sync_database($database['id'], $database['post_type']);
            if (!$result) {
                $success = false;
            }
        }
        
        // Sync individual pages
        foreach ($pages_to_sync as $page) {
            $result = $this->sync_page($page['id'], $page['post_type']);
            if (!$result) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Sync a database
     */
    public function sync_database($database_id, $post_type) {
        // Get database pages
        $pages = $this->api->query_database($database_id);
        
        if (empty($pages)) {
            $this->db->log_sync($database_id, 0, 'error', 'Failed to fetch database pages');
            return false;
        }
        
        $success = true;
        
        foreach ($pages as $page) {
            $result = $this->sync_single_page($page['id'], $post_type);
            if (!$result) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Sync a page
     */
    public function sync_page($page_id, $post_type) {
        // Format page ID correctly (with or without hyphens)
        $original_id = $page_id;
        $page_id = $this->format_notion_id($page_id);
        
        // Add detailed debug logging
        if (WP_DEBUG) {
            error_log('NOTION SYNC: Starting sync for individual page');
            error_log('NOTION SYNC: Original ID: ' . $original_id);
            error_log('NOTION SYNC: Formatted ID: ' . $page_id);
        }
        
        $result = $this->sync_single_page($page_id, $post_type);
        
        // Log the result
        if (WP_DEBUG) {
            error_log('NOTION SYNC: Sync result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        }
        
        return $result;
    }
    
    /**
     * Format Notion ID to ensure it's in the correct format
     */
    private function format_notion_id($id) {
        // Remove any hyphens or spaces
        $clean_id = preg_replace('/[\-\s]/', '', $id);
        
        // Sometimes IDs have an 'f' prefix in the URL but not in the API
        $clean_id = ltrim($clean_id, 'f');
        
        // Check length
        if (strlen($clean_id) !== 32) {
            // Try other common Notion ID formats
            
            // Format: Full URL with page title (extract the UUID part)
            if (preg_match('/([a-f0-9]{32})(?:\?|$)/', $clean_id, $matches)) {
                $clean_id = $matches[1];
            } 
            // Format: Extract the ID from a URL like structure
            elseif (preg_match('/.*([a-f0-9]{8}[a-f0-9]{4}[a-f0-9]{4}[a-f0-9]{4}[a-f0-9]{12}).*/', $clean_id, $matches)) {
                $clean_id = str_replace('-', '', $matches[1]);
            }
            
            if (WP_DEBUG) {
                error_log('NOTION SYNC: ID after additional processing: ' . $clean_id . ' (Length: ' . strlen($clean_id) . ')');
            }
        }
        
        return $clean_id;
    }
    
    /**
     * Sync a single page
     */
    private function sync_single_page($page_id, $post_type) {
        if (WP_DEBUG) {
            error_log('NOTION SYNC: Requesting page with ID: ' . $page_id);
        }
        
        // Get page data
        $page = $this->api->get_page($page_id);
        
        if (!$page) {
            $error_msg = 'Failed to fetch page from Notion API';
            if (WP_DEBUG) {
                error_log('NOTION SYNC ERROR: ' . $error_msg . ' - ID: ' . $page_id);
                error_log('NOTION SYNC: Last error: ' . get_option('notion_wp_sync_last_error', 'None'));
            }
            $this->db->log_sync($page_id, 0, 'error', $error_msg);
            return false;
        }
        
        // Get page content
        $blocks = $this->api->get_page_content($page_id);
        
        // Extract page properties
        $title = $this->extract_title($page);
        $content = $this->convert_blocks_to_html($blocks);
        
        // Check if we already have a mapping
        $mapping = $this->db->get_mapping_by_notion_id($page_id);
        
        if ($mapping) {
            // Update existing post
            $post_id = $this->update_post($mapping->wp_post_id, $title, $content, $page);
        } else {
            // Create new post
            $post_id = $this->create_post($title, $content, $post_type, $page);
        }
        
        if (!$post_id) {
            $this->db->log_sync($page_id, 0, 'error', 'Failed to create/update post');
            return false;
        }
        
        // Save mapping
        $this->db->save_mapping($page_id, 'page', $post_id, $post_type);
        $this->db->log_sync($page_id, $post_id, 'success', 'Synced successfully');
        
        return true;
    }
    
    /**
     * Extract title from page
     */
    private function extract_title($page) {
        // First try to get a title property
        if (isset($page['properties']['title']) && !empty($page['properties']['title']['title'])) {
            return $this->extract_rich_text($page['properties']['title']['title']);
        }
        
        // Try to get a Name property
        if (isset($page['properties']['Name']) && !empty($page['properties']['Name']['title'])) {
            return $this->extract_rich_text($page['properties']['Name']['title']);
        }
        
        // Fallback to page ID
        return 'Notion Page ' . substr($page['id'], 0, 8);
    }
    
    /**
     * Extract text from rich text array
     */
    private function extract_rich_text($rich_text) {
        $text = '';
        
        foreach ($rich_text as $text_item) {
            $text .= $text_item['plain_text'];
        }
        
        return $text;
    }
    
    /**
     * Convert Notion blocks to HTML
     */
    private function convert_blocks_to_html($blocks) {
        $html = '';
        
        foreach ($blocks as $block) {
            $html .= $this->convert_block_to_html($block);
        }
        
        return $html;
    }
    
    /**
     * Convert a single block to HTML
     */
    private function convert_block_to_html($block) {
        $type = $block['type'];
        $html = '';
        
        switch ($type) {
            case 'paragraph':
                $text = $this->extract_rich_text($block['paragraph']['rich_text']);
                $html = '<p>' . $this->format_rich_text($block['paragraph']['rich_text']) . '</p>';
                break;
                
            case 'heading_1':
                $html = '<h1>' . $this->format_rich_text($block['heading_1']['rich_text']) . '</h1>';
                break;
                
            case 'heading_2':
                $html = '<h2>' . $this->format_rich_text($block['heading_2']['rich_text']) . '</h2>';
                break;
                
            case 'heading_3':
                $html = '<h3>' . $this->format_rich_text($block['heading_3']['rich_text']) . '</h3>';
                break;
                
            case 'bulleted_list_item':
                $html = '<ul><li>' . $this->format_rich_text($block['bulleted_list_item']['rich_text']) . '</li></ul>';
                break;
                
            case 'numbered_list_item':
                $html = '<ol><li>' . $this->format_rich_text($block['numbered_list_item']['rich_text']) . '</li></ol>';
                break;
                
            case 'to_do':
                $checked = $block['to_do']['checked'] ? ' checked' : '';
                $html = '<div class="notion-todo"><input type="checkbox"' . $checked . ' disabled> ' . 
                        $this->format_rich_text($block['to_do']['rich_text']) . '</div>';
                break;
                
            case 'toggle':
                $html = '<details><summary>' . $this->format_rich_text($block['toggle']['rich_text']) . '</summary>';
                if (!empty($block['children'])) {
                    $html .= $this->convert_blocks_to_html($block['children']);
                }
                $html .= '</details>';
                break;
                
            case 'code':
                $language = isset($block['code']['language']) ? $block['code']['language'] : '';
                
                // Special handling for mermaid diagrams
                if ($language === 'mermaid') {
                    $diagram_code = $this->extract_rich_text($block['code']['rich_text']);
                    
                    // Use the specialized Mermaid formatter if available
                    if (class_exists('Notion_WP_Sync_Mermaid_Formatter')) {
                        $html = Notion_WP_Sync_Mermaid_Formatter::format($diagram_code);
                    } else {
                        // Fallback to basic formatting
                        $html = '<div class="mermaid">' . $diagram_code . '</div>';
                    }
                } else {
                    $html = '<pre><code class="language-' . $language . '">' . 
                            htmlspecialchars($this->extract_rich_text($block['code']['rich_text'])) . 
                            '</code></pre>';
                }
                break;
                
            case 'image':
                $caption = '';
                if (!empty($block['image']['caption'])) {
                    $caption = $this->extract_rich_text($block['image']['caption']);
                }
                
                if ($block['image']['type'] === 'external') {
                    $url = $block['image']['external']['url'];
                    $html = '<figure><img src="' . esc_url($url) . '" alt="' . esc_attr($caption) . '">';
                    if ($caption) {
                        $html .= '<figcaption>' . $caption . '</figcaption>';
                    }
                    $html .= '</figure>';
                } else if ($block['image']['type'] === 'file') {
                    // Handle file upload later
                    $url = $block['image']['file']['url'];
                    $html = '<figure><img src="' . esc_url($url) . '" alt="' . esc_attr($caption) . '">';
                    if ($caption) {
                        $html .= '<figcaption>' . $caption . '</figcaption>';
                    }
                    $html .= '</figure>';
                }
                break;
                
            case 'divider':
                $html = '<hr>';
                break;
                
            case 'quote':
                $html = '<blockquote>' . $this->format_rich_text($block['quote']['rich_text']) . '</blockquote>';
                break;
                
            case 'equation':
                // Add support for LaTeX equations (requires KaTeX or MathJax)
                $equation = isset($block['equation']['expression']) ? $block['equation']['expression'] : '';
                $html = '<div class="notion-equation">$$' . $equation . '$$</div>';
                break;
                
            case 'table':
                // Support for tables
                $html = '<div class="notion-table-container"><table class="notion-table">';
                
                if (!empty($block['table']['children'])) {
                    foreach ($block['table']['children'] as $row_index => $row) {
                        $html .= '<tr>';
                        foreach ($row['cells'] as $cell_index => $cell) {
                            $tag = ($row_index === 0 && $block['table']['has_column_header']) ? 'th' : 'td';
                            $html .= '<' . $tag . '>' . $this->format_rich_text($cell) . '</' . $tag . '>';
                        }
                        $html .= '</tr>';
                    }
                }
                
                $html .= '</table></div>';
                break;
                
            case 'callout':
                // Support for callouts/notes
                $icon = '';
                if (isset($block['callout']['icon'])) {
                    if ($block['callout']['icon']['type'] === 'emoji') {
                        $icon = '<span class="notion-callout-icon">' . $block['callout']['icon']['emoji'] . '</span>';
                    }
                }
                
                $html = '<div class="notion-callout">' . 
                        $icon . 
                        '<div class="notion-callout-content">' . 
                        $this->format_rich_text($block['callout']['rich_text']) . 
                        '</div></div>';
                break;
        }
        
        // Handle children
        if (isset($block['has_children']) && $block['has_children'] && isset($block['children'])) {
            if ($type !== 'toggle') { // Toggle already handles its children
                $html .= '<div class="notion-children">';
                $html .= $this->convert_blocks_to_html($block['children']);
                $html .= '</div>';
            }
        }
        
        return $html;
    }
    
    /**
     * Format rich text with HTML formatting
     */
    private function format_rich_text($rich_text) {
        $html = '';
        
        foreach ($rich_text as $text) {
            $content = htmlspecialchars($text['plain_text']);
            $annotations = $text['annotations'];
            
            if ($annotations['bold']) {
                $content = '<strong>' . $content . '</strong>';
            }
            
            if ($annotations['italic']) {
                $content = '<em>' . $content . '</em>';
            }
            
            if ($annotations['strikethrough']) {
                $content = '<del>' . $content . '</del>';
            }
            
            if ($annotations['underline']) {
                $content = '<u>' . $content . '</u>';
            }
            
            if ($annotations['code']) {
                $content = '<code>' . $content . '</code>';
            }
            
            if (isset($text['href']) && !empty($text['href'])) {
                $content = '<a href="' . esc_url($text['href']) . '">' . $content . '</a>';
            }
            
            $html .= $content;
        }
        
        return $html;
    }
    
    /**
     * Create a new WordPress post
     */
    private function create_post($title, $content, $post_type, $page) {
        $post_data = array(
            'post_title'    => $title,
            'post_content'  => $content,
            'post_status'   => 'draft',
            'post_type'     => $post_type,
        );
        
        // Add excerpt if available
        if (isset($page['properties']['excerpt']) && !empty($page['properties']['excerpt']['rich_text'])) {
            $post_data['post_excerpt'] = $this->extract_rich_text($page['properties']['excerpt']['rich_text']);
        }
        
        // Insert the post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return false;
        }
        
        // Set featured image if available
        if (isset($page['properties']['featured_image']) && !empty($page['properties']['featured_image']['files'])) {
            $this->set_featured_image($post_id, $page['properties']['featured_image']['files'][0]);
        }
        
        // Store Notion metadata
        update_post_meta($post_id, '_notion_page_id', $page['id']);
        update_post_meta($post_id, '_notion_last_edited', $page['last_edited_time']);
        
        return $post_id;
    }
    
    /**
     * Update an existing WordPress post
     */
    private function update_post($post_id, $title, $content, $page) {
        $post_data = array(
            'ID'            => $post_id,
            'post_title'    => $title,
            'post_content'  => $content,
        );
        
        // Add excerpt if available
        if (isset($page['properties']['excerpt']) && !empty($page['properties']['excerpt']['rich_text'])) {
            $post_data['post_excerpt'] = $this->extract_rich_text($page['properties']['excerpt']['rich_text']);
        }
        
        // Update the post
        $post_id = wp_update_post($post_data);
        
        if (is_wp_error($post_id)) {
            return false;
        }
        
        // Set featured image if available
        if (isset($page['properties']['featured_image']) && !empty($page['properties']['featured_image']['files'])) {
            $this->set_featured_image($post_id, $page['properties']['featured_image']['files'][0]);
        }
        
        // Update Notion metadata
        update_post_meta($post_id, '_notion_last_edited', $page['last_edited_time']);
        
        return $post_id;
    }
    
    /**
     * Set featured image for a post
     */
    private function set_featured_image($post_id, $image) {
        // For external images, download them first
        $image_url = '';
        
        if ($image['type'] === 'external') {
            $image_url = $image['external']['url'];
        } else if ($image['type'] === 'file') {
            $image_url = $image['file']['url'];
        }
        
        if (empty($image_url)) {
            return;
        }
        
        // Download image
        $upload = $this->download_image($image_url);
        
        if (is_wp_error($upload)) {
            return;
        }
        
        // Set as featured image
        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name(basename($upload['file'])),
            'post_content'   => '',
            'post_status'    => 'inherit'
        ), $upload['file'], $post_id);
        
        if (is_wp_error($attachment_id)) {
            return;
        }
        
        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        // Set as featured image
        set_post_thumbnail($post_id, $attachment_id);
    }
    
    /**
     * Download an image from URL
     */
    private function download_image($url) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        $temp_file = download_url($url);
        
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }
        
        $file_array = array(
            'name'     => basename($url),
            'tmp_name' => $temp_file
        );
        
        // Move the temporary file to the uploads directory
        $upload = wp_handle_sideload(
            $file_array,
            array('test_form' => false)
        );
        
        return $upload;
    }
}
