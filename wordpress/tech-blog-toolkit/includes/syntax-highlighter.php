<?php
/**
 * Syntax Highlighter — Tech Blog Toolkit
 *
 * Enqueues highlight.js for code blocks in tutorials and posts.
 */

if (!defined('WPINC')) { die; }

add_action('wp_enqueue_scripts', 'tbt_enqueue_highlighter');
function tbt_enqueue_highlighter() {
    // highlight.js from CDN — lightweight syntax highlighting
    wp_enqueue_style(
        'highlightjs-css',
        'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css',
        array(),
        '11.9.0'
    );
    wp_enqueue_script(
        'highlightjs',
        'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js',
        array(),
        '11.9.0',
        true
    );
    wp_add_inline_script('highlightjs', 'hljs.highlightAll();');
}

/**
 * Add [code lang="php"] shortcode for inline code snippets.
 */
add_shortcode('code', 'tbt_code_shortcode');
function tbt_code_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array('lang' => ''), $atts, 'code');
    $lang = esc_attr($atts['lang']);
    $code = esc_html(trim($content));
    return '<pre><code class="language-' . $lang . '">' . $code . '</code></pre>';
}
