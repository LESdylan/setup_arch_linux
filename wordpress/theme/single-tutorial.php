<?php get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <?php while (have_posts()): the_post(); ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class('tutorial-single'); ?>>
                <header class="entry-header">
                    <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
                    
                    <div class="tutorial-meta">
                        <?php
                        // Display difficulty level if it exists
                        $difficulty = get_post_meta(get_the_ID(), 'tutorial_difficulty', true);
                        if ($difficulty) {
                            echo '<span class="tutorial-difficulty ' . esc_attr(strtolower($difficulty)) . '">';
                            echo esc_html($difficulty);
                            echo '</span>';
                        }
                        
                        // Display estimated time if it exists
                        $time = get_post_meta(get_the_ID(), 'tutorial_time', true);
                        if ($time) {
                            echo '<span class="tutorial-time">';
                            echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>';
                            echo esc_html($time);
                            echo '</span>';
                        }
                        
                        // Display post date
                        echo '<span class="posted-on">';
                        echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>';
                        echo '<time datetime="' . esc_attr(get_the_date('c')) . '">' . esc_html(get_the_date()) . '</time>';
                        echo '</span>';
                        ?>
                    </div>
                </header>

                <?php if (has_post_thumbnail()): ?>
                    <div class="tutorial-thumbnail">
                        <?php the_post_thumbnail('large'); ?>
                    </div>
                <?php endif; ?>

                <?php 
                // Display prerequisites if they exist
                $prerequisites = get_post_meta(get_the_ID(), 'tutorial_prerequisites', true);
                if ($prerequisites): ?>
                    <div class="tutorial-prerequisites">
                        <h3><?php esc_html_e('Prerequisites', 'sublime-tech'); ?></h3>
                        <?php echo wp_kses_post($prerequisites); ?>
                    </div>
                <?php endif; ?>

                <div class="entry-content">
                    <?php 
                    the_content();
                    
                    wp_link_pages(
                        array(
                            'before' => '<div class="page-links">' . esc_html__('Pages:', 'sublime-tech'),
                            'after'  => '</div>',
                        )
                    );
                    ?>
                </div>

                <?php 
                // Display OS compatibility if it exists
                $os_compatibility = get_post_meta(get_the_ID(), 'tutorial_os_compatibility', true);
                if ($os_compatibility): ?>
                    <div class="tutorial-os-compatibility">
                        <h3><?php esc_html_e('OS Compatibility', 'sublime-tech'); ?></h3>
                        <ul class="os-list">
                            <?php 
                            $os_array = explode(',', $os_compatibility);
                            foreach ($os_array as $os) {
                                echo '<li>' . esc_html(trim($os)) . '</li>';
                            }
                            ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <footer class="entry-footer">
                    <?php
                    // Display tags
                    $tags_list = get_the_tag_list('', ', ');
                    if ($tags_list) {
                        echo '<div class="tags-links">' . esc_html__('Tags:', 'sublime-tech') . ' ' . $tags_list . '</div>';
                    }
                    ?>
                </footer>
            </article>

            <nav class="navigation tutorial-navigation">
                <h2 class="screen-reader-text"><?php esc_html_e('Tutorial navigation', 'sublime-tech'); ?></h2>
                <div class="nav-links">
                    <?php
                    previous_post_link('<div class="nav-previous">%link</div>', '%title');
                    next_post_link('<div class="nav-next">%link</div>', '%title');
                    ?>
                </div>
            </nav>

            <?php
            // If comments are open or we have at least one comment, load up the comment template.
            if (comments_open() || get_comments_number()) {
                comments_template();
            }
            ?>

        <?php endwhile; ?>

    </main>
</div>

<?php get_sidebar(); ?>
<?php get_footer(); ?>
