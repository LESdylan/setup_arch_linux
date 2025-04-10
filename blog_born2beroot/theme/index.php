<?php get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <?php if (have_posts()) : ?>

            <?php while (have_posts()) : the_post(); ?>
                
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <header class="entry-header">
                        <h2 class="entry-title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h2>
                        
                        <div class="entry-meta">
                            <?php
                            echo '<span class="posted-on">' . esc_html__('Posted on', 'sublime-tech') . ' ';
                            echo '<time datetime="' . esc_attr(get_the_date('c')) . '">' . esc_html(get_the_date()) . '</time></span>';
                            echo '<span class="byline"> ' . esc_html__('by', 'sublime-tech') . ' ';
                            echo '<span class="author vcard">' . esc_html(get_the_author()) . '</span></span>';
                            ?>
                        </div>
                    </header>

                    <div class="entry-content">
                        <?php the_excerpt(); ?>
                        <a href="<?php the_permalink(); ?>" class="read-more"><?php esc_html_e('Read More', 'sublime-tech'); ?></a>
                    </div>
                </article>

            <?php endwhile; ?>

            <div class="pagination">
                <?php the_posts_pagination(); ?>
            </div>

        <?php else : ?>
            
            <p><?php esc_html_e('No posts found.', 'sublime-tech'); ?></p>

        <?php endif; ?>

    </main>
</div>

<?php get_sidebar(); ?>
<?php get_footer(); ?>