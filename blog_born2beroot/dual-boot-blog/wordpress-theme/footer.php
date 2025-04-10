<?php
// This file contains the footer section of the WordPress theme.

?>

<footer>
    <div class="footer-content">
        <p>&copy; <?php echo date("Y"); ?> Dual Boot Blog. All rights reserved.</p>
        <nav>
            <ul>
                <li><a href="<?php echo home_url(); ?>">Home</a></li>
                <li><a href="<?php echo site_url('/about'); ?>">About</a></li>
                <li><a href="<?php echo site_url('/tutorials'); ?>">Tutorials</a></li>
            </ul>
        </nav>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>