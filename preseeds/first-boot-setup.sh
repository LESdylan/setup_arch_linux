#!/bin/bash
# First-boot setup for WordPress (requires running MariaDB + network)
# This script self-deletes after successful execution.
exec > /var/log/first-boot.log 2>&1
set -x

sleep 10

# MariaDB setup
systemctl start mariadb
sleep 3
mysql -u root -e "CREATE DATABASE IF NOT EXISTS wordpress;"
mysql -u root -e "CREATE USER IF NOT EXISTS 'wpuser'@'localhost' IDENTIFIED BY 'wppass123';"
mysql -u root -e "GRANT ALL PRIVILEGES ON wordpress.* TO 'wpuser'@'localhost';"
mysql -u root -e "FLUSH PRIVILEGES;"

# WordPress download
cd /var/www/html
wget -q https://wordpress.org/latest.tar.gz
tar -xzf latest.tar.gz
rm -f latest.tar.gz
chown -R www-data:www-data wordpress

# WordPress config
cat > /var/www/html/wordpress/wp-config.php << 'WPEOF'
<?php
define('DB_NAME', 'wordpress');
define('DB_USER', 'wpuser');
define('DB_PASSWORD', 'wppass123');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
$table_prefix = 'wp_';
define('WP_DEBUG', false);
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
WPEOF

systemctl restart lighttpd

# Self-destruct
rm -f /root/first-boot-setup.sh
sed -i '/first-boot-setup/d' /etc/crontab
echo "=== First-boot WordPress setup complete ==="
