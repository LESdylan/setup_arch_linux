CREATE DATABASE IF NOT EXISTS `wp-database`;

-- Drop user if exists to avoid conflicts
DROP USER IF EXISTS 'dlesieur42'@'localhost';

-- Create user with password from wp-config.php
CREATE USER 'dlesieur42'@'localhost' IDENTIFIED BY 'temp_wp123';

-- Grant all privileges to the WordPress database
GRANT ALL PRIVILEGES ON `wp-database`.* TO 'dlesieur42'@'localhost';

-- Make sure changes take effect
FLUSH PRIVILEGES;
