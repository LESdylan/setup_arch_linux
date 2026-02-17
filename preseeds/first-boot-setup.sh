#!/bin/bash
# First-boot setup: Docker + WordPress (requires running systemd + network)
# This script runs once via @reboot crontab, then self-deletes.
exec > /var/log/first-boot.log 2>&1
set -x
export DEBIAN_FRONTEND=noninteractive

echo "=== First-boot setup starting ($(date)) ==="

# Wait for network to be fully up
for i in $(seq 1 30); do
    if ping -c1 -W2 deb.debian.org >/dev/null 2>&1; then
        echo "Network is up after ${i}s"
        break
    fi
    sleep 2
done

### ─── 1. Docker installation (official method) ─────────────────────────────
echo "--- Installing Docker ---"

# Add Docker official GPG key
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc

# Add Docker repo (Debian trixie → use bookworm as fallback if trixie not available)
CODENAME=$(. /etc/os-release && echo "$VERSION_CODENAME")
if [ -z "$CODENAME" ] || [ "$CODENAME" = "trixie" ]; then
    # Docker may not have trixie packages yet — try trixie first, fall back to bookworm
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
https://download.docker.com/linux/debian trixie stable" > /etc/apt/sources.list.d/docker.list
    apt-get update -qq 2>/dev/null
    if ! apt-cache show docker-ce >/dev/null 2>&1; then
        echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
https://download.docker.com/linux/debian bookworm stable" > /etc/apt/sources.list.d/docker.list
        apt-get update -qq
    fi
else
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
https://download.docker.com/linux/debian $CODENAME stable" > /etc/apt/sources.list.d/docker.list
    apt-get update -qq
fi

apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin || true

# Add dlesieur to docker group
usermod -aG docker dlesieur 2>/dev/null || true

# Kill any running VS Code server so it restarts with the docker group loaded.
# Without this, the VS Code server inherits the old group list (no docker GID)
# and every Docker command from the VS Code terminal fails with "permission denied".
# The user's next VS Code reconnect will spawn a fresh server with correct groups.
pkill -u dlesieur -f "vscode-server" 2>/dev/null || true

# Enable and start Docker
systemctl enable docker
systemctl start docker
echo "[OK] Docker installed and running"

### ─── 2. WordPress setup ───────────────────────────────────────────────────
echo "--- Setting up WordPress ---"

# MariaDB setup
systemctl start mariadb
sleep 3
mysql -u root -e "CREATE DATABASE IF NOT EXISTS wordpress;"
mysql -u root -e "CREATE USER IF NOT EXISTS 'wpuser'@'localhost' IDENTIFIED BY 'wppass123';"
mysql -u root -e "GRANT ALL PRIVILEGES ON wordpress.* TO 'wpuser'@'localhost';"
mysql -u root -e "FLUSH PRIVILEGES;"
echo "[OK] MariaDB configured"

# WordPress download
cd /var/www/html
wget -q --timeout=60 --tries=3 https://wordpress.org/latest.tar.gz
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
echo "[OK] WordPress configured"

### ─── 3. UFW — open Docker port ─────────────────────────────────────────────
ufw allow 2375/tcp comment 'Docker' 2>/dev/null || true

### ─── 3b. Ensure NAT keepalive + SSH stability services are running ─────────
# b2b-setup.sh creates these in chroot but systemctl enable may not stick.
# Belt-and-suspenders: re-enable and start them now with real systemd.
systemctl daemon-reload
systemctl enable nat-keepalive 2>/dev/null || true
systemctl start nat-keepalive 2>/dev/null || true
systemctl enable sshd-watchdog 2>/dev/null || true
systemctl start sshd-watchdog 2>/dev/null || true
systemctl enable ssh 2>/dev/null || true
systemctl restart ssh 2>/dev/null || true
# Apply kernel TCP keepalive values (may not have been applied from chroot)
sysctl --system >/dev/null 2>&1 || true
echo "[OK] NAT keepalive + sshd-watchdog + SSH stability ensured"

### ─── 4. Self-destruct ─────────────────────────────────────────────────────
sed -i '/first-boot-setup/d' /etc/crontab
rm -f /root/first-boot-setup.sh
echo "=== First-boot setup complete ($(date)) ==="
