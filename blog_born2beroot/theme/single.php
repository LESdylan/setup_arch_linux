<?php get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <?php while (have_posts()): the_post(); ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
                    
                    <div class="entry-meta">
                        <?php
                        // Display post date
                        echo '<span class="posted-on">' . esc_html__('Posted on', 'sublime-tech') . ' ';
                        echo '<time datetime="' . esc_attr(get_the_date('c')) . '">' . esc_html(get_the_date()) . '</time></span>';
                        
                        // Display post author
                        echo '<span class="byline"> ' . esc_html__('by', 'sublime-tech') . ' ';
                        echo '<span class="author vcard">' . esc_html(get_the_author()) . '</span></span>';
                        
                        // Display categories
                        $categories_list = get_the_category_list(', ');
                        if ($categories_list) {
                            echo '<span class="cat-links"> ' . esc_html__('in', 'sublime-tech') . ' ' . $categories_list . '</span>';
                        }
                        ?>
                    </div>
                </header>

                <?php if (has_post_thumbnail()): ?>
                    <div class="post-thumbnail">
                        <?php the_post_thumbnail('large'); ?>
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

            <nav class="navigation post-navigation">
                <h2 class="screen-reader-text"><?php esc_html_e('Post navigation', 'sublime-tech'); ?></h2>
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
