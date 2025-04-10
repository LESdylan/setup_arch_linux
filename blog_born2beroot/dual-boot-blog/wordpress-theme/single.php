<?php get_header(); ?>

<div class="single-post">
    <?php
    if (have_posts()) :
        while (have_posts()) : the_post(); ?>
            <h1><?php the_title(); ?></h1>
            <div class="post-meta">
                <span>Published on: <?php the_date(); ?></span>
                <span>By: <?php the_author(); ?></span>
            </div>
            <div class="post-content">
                <?php the_content(); ?>
            </div>
            <div class="post-tags">
                <?php the_tags('<span>Tags: ', ', ', '</span>'); ?>
            </div>
        <?php endwhile;
    else :
        echo '<p>No content found</p>';
    endif;
    ?>
</div>

<?php get_sidebar(); ?>
<?php get_footer(); ?>