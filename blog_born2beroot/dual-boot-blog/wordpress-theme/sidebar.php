<div class="sidebar">
    <h2>Blog Categories</h2>
    <ul>
        <li><a href="/tutorials/dual-boot-setup.html">Dual Boot Setup</a></li>
        <li><a href="/tutorials/personalization.html">Personalization</a></li>
        <li><a href="/tutorials/security.html">Security</a></li>
    </ul>
    
    <h2>Recent Posts</h2>
    <ul>
        <?php
        // Fetch recent posts from the database
        $recent_posts = wp_get_recent_posts(array(
            'numberposts' => 5, // Change this number to display more or fewer posts
            'post_status' => 'publish'
        ));
        foreach($recent_posts as $post) : ?>
            <li><a href="<?php echo get_permalink($post['ID']); ?>"><?php echo $post['post_title']; ?></a></li>
        <?php endforeach; ?>
    </ul>
    
    <h2>Archives</h2>
    <ul>
        <?php
        // Display monthly archives
        wp_get_archives(array(
            'type' => 'monthly',
            'limit' => 12
        ));
        ?>
    </ul>
</div>