    </div><!-- #content -->

    <footer id="colophon" class="site-footer">
        <div class="site-info">
            <div class="footer-copy">
                © <?php echo date('Y'); ?> <?php bloginfo('name'); ?>
                <span class="sep"> | </span>
                <?php
                    /* translators: %s: WordPress. */
                    printf(esc_html__('Powered by %s', 'sublime-tech'), '<a href="https://wordpress.org/">WordPress</a>');
                ?>
                <span class="sep"> | </span>
                <?php esc_html_e('Theme: Sublime Tech', 'sublime-tech'); ?>
            </div>

            <nav class="footer-navigation">
                <?php
                wp_nav_menu(
                    array(
                        'theme_location' => 'footer',
                        'menu_id'        => 'footer-menu',
                        'depth'          => 1,
                        'fallback_cb'    => false,
                    )
                );
                ?>
            </nav>
        </div><!-- .site-info -->
    </footer><!-- #colophon -->
</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
