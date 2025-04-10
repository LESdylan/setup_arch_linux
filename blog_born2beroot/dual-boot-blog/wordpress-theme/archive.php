<?php
get_header(); ?>

<div class="archive-header">
    <h1><?php the_archive_title(); ?></h1>
    <p><?php the_archive_description(); ?></p>
</div>

<div class="archive-posts">
    <?php if (have_posts()) : 
        while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <h2 class="post-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                <div class="post-meta">
                    <span class="post-date"><?php echo get_the_date(); ?></span>
                    <span class="post-author"><?php the_author(); ?></span>
                </div>
                <div class="post-excerpt">
                    <?php the_excerpt(); ?>
                </div>
            </article>
        <?php endwhile; 
    else : ?>
        <p><?php esc_html_e('No posts found.', 'your-text-domain'); ?></p>
    <?php endif; ?>
</div>

<div class="pagination">
    <?php
    the_posts_pagination(array(
        'mid_size' => 2,
        'prev_text' => __('&laquo; Previous', 'your-text-domain'),
        'next_text' => __('Next &raquo;', 'your-text-domain'),
    ));
    ?>
</div>

<?php
get_sidebar();
get_footer(); ?>