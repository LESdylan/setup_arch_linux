<?php get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <header class="page-header">
            <h1 class="page-title"><?php esc_html_e('Linux Tutorials', 'sublime-tech'); ?></h1>
            <div class="archive-description">
                <p><?php esc_html_e('Browse all our Linux installation and configuration tutorials.', 'sublime-tech'); ?></p>
            </div>
        </header>

        <?php if (have_posts()): ?>

            <div class="tutorial-filters">
                <?php
                // Filter by difficulty
                $difficulties = array('Beginner', 'Intermediate', 'Advanced');
                ?>
                <div class="filter-group">
                    <span class="filter-label"><?php esc_html_e('Filter by:', 'sublime-tech'); ?></span>
                    <div class="filter-options">
                        <a href="<?php echo esc_url(get_post_type_archive_link('tutorial')); ?>" class="filter-option <?php echo !isset($_GET['difficulty']) ? 'active' : ''; ?>">
                            <?php esc_html_e('All', 'sublime-tech'); ?>
                        </a>
                        <?php foreach ($difficulties as $difficulty): ?>
                            <a href="<?php echo esc_url(add_query_arg('difficulty', strtolower($difficulty), get_post_type_archive_link('tutorial'))); ?>" class="filter-option <?php echo isset($_GET['difficulty']) && $_GET['difficulty'] === strtolower($difficulty) ? 'active' : ''; ?>">
                                <?php echo esc_html($difficulty); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="tutorial-grid">
                <?php while (have_posts()): the_post(); ?>
                    <div class="tutorial-item">
                        <?php if (has_post_thumbnail()): ?>
                            <div class="tutorial-thumbnail">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail('medium'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="tutorial-content">
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            
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
                                    echo '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>';
                                    echo esc_html($time);
                                    echo '</span>';
                                }
                                ?>
                            </div>
                            
                            <div class="tutorial-excerpt">
                                <?php the_excerpt(); ?>
                            </div>
                            
                            <a href="<?php the_permalink(); ?>" class="tutorial-readmore"><?php esc_html_e('View Tutorial', 'sublime-tech'); ?></a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="pagination">
                <?php the_posts_pagination(); ?>
            </div>

        <?php else: ?>
            
            <p><?php esc_html_e('No tutorials found.', 'sublime-tech'); ?></p>

        <?php endif; ?>

    </main>
</div>

<?php get_sidebar(); ?>
<?php get_footer(); ?>
