<?php
/**
 * Admin Dashboard Widget — Tech Blog Toolkit
 */

if (!defined('WPINC')) { die; }

add_action('wp_dashboard_setup', 'tbt_add_dashboard_widget');
function tbt_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'tbt_dashboard_widget',
        'Tech Blog Toolkit — Overview',
        'tbt_dashboard_widget_callback'
    );
}

function tbt_dashboard_widget_callback() {
    $tutorial_count = wp_count_posts('tutorial');
    $published = isset($tutorial_count->publish) ? $tutorial_count->publish : 0;
    $drafts    = isset($tutorial_count->draft)   ? $tutorial_count->draft   : 0;
    $post_count = wp_count_posts('post');
    ?>
    <div style="display:flex;gap:20px;flex-wrap:wrap;">
        <div style="flex:1;min-width:120px;background:#f0f6fc;padding:15px;border-radius:8px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#2271b1;"><?php echo (int)$published; ?></div>
            <div style="color:#50575e;">Tutorials Published</div>
        </div>
        <div style="flex:1;min-width:120px;background:#fef8ee;padding:15px;border-radius:8px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#dba617;"><?php echo (int)$drafts; ?></div>
            <div style="color:#50575e;">Tutorial Drafts</div>
        </div>
        <div style="flex:1;min-width:120px;background:#edf8f1;padding:15px;border-radius:8px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#00a32a;"><?php echo (int)$post_count->publish; ?></div>
            <div style="color:#50575e;">Blog Posts</div>
        </div>
    </div>
    <p style="margin-top:15px;">
        <a href="<?php echo admin_url('edit.php?post_type=tutorial'); ?>" class="button button-primary">Manage Tutorials</a>
        <a href="<?php echo admin_url('admin.php?page=tech-blog-toolkit'); ?>" class="button">Plugin Settings</a>
    </p>
    <?php
}
