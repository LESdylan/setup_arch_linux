<?php
/**
 * Meta Boxes — Tech Blog Toolkit
 */

if (!defined('WPINC')) { die; }

add_action('add_meta_boxes', 'tbt_add_meta_boxes');
function tbt_add_meta_boxes() {
    add_meta_box(
        'tbt_tech_specs',
        'Technical Specifications',
        'tbt_tech_specs_callback',
        'tutorial',
        'side',
        'default'
    );
}

function tbt_tech_specs_callback($post) {
    wp_nonce_field('tbt_save_meta', 'tbt_meta_nonce');
    $difficulty = get_post_meta($post->ID, '_tbt_difficulty', true);
    $duration   = get_post_meta($post->ID, '_tbt_duration', true);
    $language   = get_post_meta($post->ID, '_tbt_language', true);
    ?>
    <p>
        <label for="tbt_difficulty"><strong>Difficulty:</strong></label><br>
        <select name="tbt_difficulty" id="tbt_difficulty" style="width:100%">
            <option value="">— Select —</option>
            <option value="beginner"     <?php selected($difficulty, 'beginner'); ?>>Beginner</option>
            <option value="intermediate" <?php selected($difficulty, 'intermediate'); ?>>Intermediate</option>
            <option value="advanced"     <?php selected($difficulty, 'advanced'); ?>>Advanced</option>
        </select>
    </p>
    <p>
        <label for="tbt_duration"><strong>Estimated Duration:</strong></label><br>
        <input type="text" name="tbt_duration" id="tbt_duration" value="<?php echo esc_attr($duration); ?>" placeholder="e.g. 30 min" style="width:100%">
    </p>
    <p>
        <label for="tbt_language"><strong>Programming Language:</strong></label><br>
        <input type="text" name="tbt_language" id="tbt_language" value="<?php echo esc_attr($language); ?>" placeholder="e.g. Python, Bash" style="width:100%">
    </p>
    <?php
}

add_action('save_post_tutorial', 'tbt_save_meta');
function tbt_save_meta($post_id) {
    if (!isset($_POST['tbt_meta_nonce']) || !wp_verify_nonce($_POST['tbt_meta_nonce'], 'tbt_save_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $fields = array('tbt_difficulty', 'tbt_duration', 'tbt_language');
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
        }
    }
}
