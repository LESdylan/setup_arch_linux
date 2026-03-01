#!/bin/bash
# Born2beRoot post-installation setup script
# Runs inside in-target (chroot to /target) during d-i late_command
# ─────────────────────────────────────────────────────────────────
# IMPORTANT: Docker can NOT be installed here (no systemd, limited
# network in chroot). Docker is installed by first-boot-setup.sh
# which runs on the first real boot with full network + systemd.
# ─────────────────────────────────────────────────────────────────
set +e # Don't exit on errors — best effort
export DEBIAN_FRONTEND=noninteractive
export DEBCONF_NONINTERACTIVE_SEEN=true
# Added next PATH chroot environment
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

LOG=/var/log/b2b-setup.log
exec > >(tee -a "$LOG") 2>&1

echo "=== Born2beRoot setup starting ($(date)) ==="

### ─── 1. APT sources ────────────────────────────────────────────────────────
cat >/etc/apt/sources.list <<'SRCEOF'
deb http://deb.debian.org/debian trixie main contrib non-free non-free-firmware
deb http://deb.debian.org/debian trixie-updates main contrib non-free non-free-firmware
deb http://security.debian.org/debian-security trixie-security main contrib non-free non-free-firmware
SRCEOF

apt-get clean
apt-get update -qq || true
echo "[OK] APT sources configured"

### ─── 2. Install packages (all available in base repos) ─────────────────────
APT="apt-get install -y -qq -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold"

# Core Born2beRoot mandatory requirements
$APT sudo ufw openssh-server \
	libpam-pwquality apparmor apparmor-utils \
	cron haveged || true
echo "[OK] Core packages"

# Bonus: Web stack (lighttpd + MariaDB + PHP)
$APT lighttpd mariadb-server \
	php-fpm php-mysql php-cgi php-mbstring php-xml php-gd php-curl || true
echo "[OK] Web stack packages"

# Developer essentials (all in base Debian repos)
$APT git git-lfs build-essential gcc g++ make cmake \
	python3 python3-pip python3-venv \
	curl wget net-tools vim nano \
	man-db manpages-dev \
	htop tree tmux screen bash-completion \
	zip unzip tar gzip bzip2 xz-utils \
	ca-certificates gnupg lsb-release apt-transport-https \
	rsync less file patch diffutils \
	dnsutils iputils-ping traceroute \
	lsof strace ltrace \
	jq bc || true
echo "[OK] Developer tools"

### ─── 2b. Third-Party Repos & Quality Tools (DevOps, Linters) ───────────────
# Fix PATH para el entorno chroot
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

echo "Configuring third-party repositories..."

# 1. MongoDB (Usamos [trusted=yes] para bypassear el bloqueo de SHA-1 de Debian Trixie)
echo "deb [trusted=yes] https://repo.mongodb.org/apt/debian bookworm/mongodb-org/7.0 main" >/etc/apt/sources.list.d/mongodb-org-7.0.list

# 2. Kubernetes (Usamos [trusted=yes] para bypassear el bloqueo de firmas v3)
echo "deb [trusted=yes] https://pkgs.k8s.io/core:/stable:/v1.30/deb/ /" >/etc/apt/sources.list.d/kubernetes.list

# Update APT e instalamos Mongo, K8s y PIPX
apt-get update -qq || true
$APT mongodb-org kubectl pipx || true
echo "[OK] MongoDB, Kubectl and Pipx installed"

# Enable MongoDB service (arrancará en el próximo reboot real)
systemctl enable mongod 2>/dev/null || true

# 3. NPM Global Packages
echo "Installing NPM global packages..."
npm install -g eslint prettier snyk || true
echo "[OK] NPM packages installed"

# 4. Python Global Packages via PIPX (El estándar para Debian 12/13)
echo "Installing Python global packages via pipx..."
PIPX_HOME=/opt/pipx PIPX_BIN_DIR=/usr/local/bin pipx install sqlfluff || true
PIPX_HOME=/opt/pipx PIPX_BIN_DIR=/usr/local/bin pipx install ruff || true
PIPX_HOME=/opt/pipx PIPX_BIN_DIR=/usr/local/bin pipx install checkov || true
echo "[OK] Python packages installed"

# 5. Golangci-lint & Helm
echo "Installing Go linters and Helm..."
curl -sSfL https://raw.githubusercontent.com/golangci/golangci-lint/master/install.sh | sh -s -- -b /usr/local/bin || true
curl -fsSL https://raw.githubusercontent.com/helm/helm/main/scripts/get-helm-3 | bash || true
echo "[OK] Golangci-lint and Helm installed"
### ───────────────────────────────────────────────────────────────────────────

### ─── 3. Hostname — Born2beRoot requires login+42 ───────────────────────────
echo "dlesieur42" >/etc/hostname
hostname dlesieur42 2>/dev/null || true

# Fix /etc/hosts — replace any old hostname or add the correct one
if grep -q "127\.0\.1\.1" /etc/hosts 2>/dev/null; then
	sed -i 's/127\.0\.1\.1.*/127.0.1.1\tdlesieur42/' /etc/hosts
else
	echo "127.0.1.1	dlesieur42" >>/etc/hosts
fi
# Also ensure localhost line exists
grep -q "127\.0\.0\.1.*localhost" /etc/hosts ||
	sed -i '1i 127.0.0.1\tlocalhost' /etc/hosts
echo "[OK] Hostname set to dlesieur42"

### ─── 4. Groups & user ─────────────────────────────────────────────────────
groupadd user42 2>/dev/null || true
# Pre-create docker group NOW so dlesieur has it from the very first login.
# Docker is installed later by first-boot-setup.sh (needs systemd + network),
# but the GROUP must exist before the first SSH/VS Code session or the VS Code
# server process inherits a stale group list without docker → "permission denied"
# on /var/run/docker.sock. Docker's postinst will reuse this group.
groupadd -f docker 2>/dev/null || true
usermod -aG sudo,user42,docker dlesieur
echo "[OK] User dlesieur in groups: sudo, user42, docker"

### ─── 5. SSH — port 4242, no root login ─────────────────────────────────────
sed -i 's/^#*Port .*/Port 4242/' /etc/ssh/sshd_config
sed -i 's/^#*PermitRootLogin .*/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/^#*PasswordAuthentication .*/PasswordAuthentication yes/' /etc/ssh/sshd_config

# ── SSH keepalive + VS Code Remote SSH settings ────────────────────────────
# VirtualBox NAT drops idle TCP mappings after ~5-15 min. We need aggressive
# keepalives on BOTH sides to keep the NAT connection tracking alive.
# Server sends keepalive every 30s, client sends every 15s (in ~/.ssh/config).
#
# VS Code Remote SSH opens MANY parallel connections (SOCKS proxy, exec server,
# tunnels, extension host). MaxStartups must be high or VS Code fails to reconnect.
# MaxStartups 50:30:100 = accept 50 unauthenticated, 30% drop until 100.
for setting in \
	"ClientAliveInterval 30" \
	"ClientAliveCountMax 5" \
	"TCPKeepAlive yes" \
	"MaxStartups 50:30:100" \
	"MaxSessions 20" \
	"LoginGraceTime 300"; do
	key=$(echo "$setting" | awk '{print $1}')
	sed -i "/^#*${key} /d" /etc/ssh/sshd_config
	echo "$setting" >>/etc/ssh/sshd_config
done

# Systemd: ensure sshd restarts automatically on failure + watchdog
mkdir -p /etc/systemd/system/ssh.service.d
cat >/etc/systemd/system/ssh.service.d/override.conf <<'EOF'
[Service]
Restart=always
RestartSec=3
StartLimitIntervalSec=60
StartLimitBurst=10
EOF

# Kernel TCP keepalive — aggressive values to keep VirtualBox NAT alive
# tcp_keepalive_time=60 → first probe after 60s idle (not default 7200!)
# tcp_keepalive_intvl=15 → re-probe every 15s
# tcp_keepalive_probes=5 → 5 failed probes = dead
cat >/etc/sysctl.d/99-ssh-keepalive.conf <<'EOF'
net.ipv4.tcp_keepalive_time=60
net.ipv4.tcp_keepalive_intvl=15
net.ipv4.tcp_keepalive_probes=5
EOF
sysctl --system >/dev/null 2>&1 || true

# ── NAT keepalive service ──────────────────────────────────────────────────
# Periodically ping the gateway to keep VirtualBox NAT engine's connection
# tracking table active. This prevents NAT from silently dropping SSH mappings.
cat >/usr/local/bin/nat-keepalive.sh <<'NKEOF'
#!/bin/bash
# Keep VirtualBox NAT alive by pinging gateway every 30 seconds
GW=$(ip route | awk '/default/ {print $3}' | head -1)
[ -z "$GW" ] && GW="10.0.2.2"
while true; do
    ping -c 1 -W 2 "$GW" >/dev/null 2>&1
    sleep 30
done
NKEOF
chmod +x /usr/local/bin/nat-keepalive.sh

cat >/etc/systemd/system/nat-keepalive.service <<'NKSEOF'
[Unit]
Description=Keep VirtualBox NAT connection tracking alive
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
ExecStart=/usr/local/bin/nat-keepalive.sh
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
NKSEOF
systemctl enable nat-keepalive 2>/dev/null || true

# ── SSHD Watchdog — monitors and auto-restarts sshd if it dies ──────────────
# VS Code Remote SSH can cause sshd to become unresponsive. This watchdog
# checks every 15 seconds and auto-restarts if sshd stops listening.
cat >/usr/local/bin/sshd-watchdog.sh <<'WDEOF'
#!/bin/bash
LOG=/var/log/sshd-watchdog.log
echo "$(date): watchdog started (pid=$$)" >> "$LOG"
while true; do
    SSHD_ACTIVE=$(systemctl is-active ssh 2>/dev/null)
    SSHD_COUNT=$(pgrep -c sshd 2>/dev/null || echo 0)
    LISTEN=$(ss -tlnp 2>/dev/null | grep -c 4242)
    ESTAB=$(ss -tnp 2>/dev/null | grep -c 4242)
    MEM_FREE=$(awk '/MemAvailable/{print $2}' /proc/meminfo 2>/dev/null)
    if [ "$SSHD_ACTIVE" != "active" ] || [ "$LISTEN" = "0" ]; then
        echo "$(date): ALERT sshd=$SSHD_ACTIVE procs=$SSHD_COUNT listen=$LISTEN estab=$ESTAB mem_free=${MEM_FREE}kB" >> "$LOG"
        systemctl restart ssh >> "$LOG" 2>&1
        echo "$(date): sshd restart attempted, new_status=$(systemctl is-active ssh)" >> "$LOG"
    fi
    MIN=$(date +%M); SEC=$(date +%S)
    if [ "$((MIN % 5))" = "0" ] && [ "$SEC" -lt "16" ]; then
        echo "$(date): OK sshd=$SSHD_ACTIVE procs=$SSHD_COUNT listen=$LISTEN estab=$ESTAB mem=${MEM_FREE}kB" >> "$LOG"
    fi
    sleep 15
done
WDEOF
chmod +x /usr/local/bin/sshd-watchdog.sh

cat >/etc/systemd/system/sshd-watchdog.service <<'SWEOF'
[Unit]
Description=SSHD health watchdog with auto-restart
After=ssh.service
Requires=ssh.service

[Service]
Type=simple
ExecStart=/usr/local/bin/sshd-watchdog.sh
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SWEOF
systemctl enable sshd-watchdog 2>/dev/null || true

systemctl enable ssh || true
systemctl daemon-reload || true
systemctl restart ssh || true
echo "[OK] SSH configured on port 4242 (keepalives + NAT keepalive + sshd watchdog)"

### ─── 5b. SSH key auth — bake host's public key for passwordless login ──────
# This enables VS Code Remote SSH to reconnect instantly without password prompts.
# The key is from the host machine that runs `make all`.
HOST_PUBKEY_DIR="/home/dlesieur/.ssh"
mkdir -p "$HOST_PUBKEY_DIR"
chmod 700 "$HOST_PUBKEY_DIR"
chown dlesieur:dlesieur "$HOST_PUBKEY_DIR"

# The orchestrator will inject the actual key at ISO creation time.
# late_command copies it from /cdrom/host_ssh_pubkey to /target/tmp/host_ssh_pubkey
# Since b2b-setup.sh runs inside in-target (chroot), the file is at /tmp/
if [ -f /tmp/host_ssh_pubkey ]; then
	cat /tmp/host_ssh_pubkey >>"$HOST_PUBKEY_DIR/authorized_keys"
	chmod 600 "$HOST_PUBKEY_DIR/authorized_keys"
	chown dlesieur:dlesieur "$HOST_PUBKEY_DIR/authorized_keys"
	echo "[OK] Host SSH public key installed for dlesieur"
else
	echo "[WARN] No host SSH public key found at /tmp/host_ssh_pubkey — password auth only"
fi

# Ensure PubkeyAuthentication is enabled in sshd_config
sed -i 's/^#*PubkeyAuthentication .*/PubkeyAuthentication yes/' /etc/ssh/sshd_config
grep -q '^PubkeyAuthentication' /etc/ssh/sshd_config || echo 'PubkeyAuthentication yes' >>/etc/ssh/sshd_config

### ─── 6. UFW — only port 4242 + web ports + dev ports ───────────────────────
ufw default deny incoming
ufw default allow outgoing
ufw allow 4242/tcp comment 'SSH'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
ufw allow 5173/tcp comment 'Vite Frontend'
ufw allow 3000/tcp comment 'Backend API'
echo y | ufw enable
echo "[OK] UFW firewall active"

### ─── 7. Sudo — strict rules per subject ───────────────────────────────────
mkdir -p /var/log/sudo
chmod 700 /var/log/sudo

cat >/etc/sudoers.d/sudo_config <<'SUDOEOF'
Defaults	passwd_tries=3
Defaults	badpass_message="Wrong password. Access denied!"
Defaults	logfile="/var/log/sudo/sudo.log"
Defaults	log_input,log_output
Defaults	iolog_dir="/var/log/sudo"
Defaults	requiretty
Defaults	secure_path="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/snap/bin"
SUDOEOF
chmod 440 /etc/sudoers.d/sudo_config
echo "[OK] Sudo configured"

### ─── 8. Password policy ───────────────────────────────────────────────────
# login.defs — password aging
sed -i 's/^PASS_MAX_DAYS.*/PASS_MAX_DAYS\t30/' /etc/login.defs
sed -i 's/^PASS_MIN_DAYS.*/PASS_MIN_DAYS\t2/' /etc/login.defs
sed -i 's/^PASS_WARN_AGE.*/PASS_WARN_AGE\t7/' /etc/login.defs

# pwquality.conf — complexity (use robust sed + fallback append)
for setting in \
	"minlen = 10" \
	"dcredit = -1" \
	"ucredit = -1" \
	"lcredit = -1" \
	"maxrepeat = 3" \
	"usercheck = 1" \
	"difok = 7" \
	"enforce_for_root"; do
	key=$(echo "$setting" | cut -d= -f1 | xargs)
	if grep -q "^#* *${key}" /etc/security/pwquality.conf 2>/dev/null; then
		sed -i "s/^#* *${key}.*/${setting}/" /etc/security/pwquality.conf
	else
		echo "$setting" >>/etc/security/pwquality.conf
	fi
done
echo "[OK] Password policy set"

### ─── 9. tmux — persistent sessions (survive SSH drops) ────────────────────
# Install tmux (already in package list above, but ensure it's there)
$APT tmux || true

# tmux config for user dlesieur — sane defaults for dev work
TMUX_CONF="/home/dlesieur/.tmux.conf"
cat >"$TMUX_CONF" <<'TMUXEOF'
# ── Born2beRoot tmux config ──────────────────────────────────
# Reload: tmux source ~/.tmux.conf

# Use C-a as prefix (like screen), keep C-b as well
set -g prefix2 C-a
bind C-a send-prefix -2

# 256-color + true-color support
set -g default-terminal "tmux-256color"
set -ga terminal-overrides ",xterm-256color:Tc"

# Mouse support (scrolling, clicking panes, resizing)
set -g mouse on

# Start windows/panes at 1 (not 0)
set -g base-index 1
setw -g pane-base-index 1

# Renumber windows when one is closed
set -g renumber-windows on

# Longer scrollback buffer (50k lines)
set -g history-limit 50000

# Faster key repetition
set -sg escape-time 0

# Activity monitoring
setw -g monitor-activity on
set -g visual-activity off

# Status bar
set -g status-style "bg=#1a1b26,fg=#a9b1d6"
set -g status-left "#[bold,fg=#7aa2f7] #S "
set -g status-right "#[fg=#565f89] %H:%M │ #h "
set -g status-left-length 30
set -g status-right-length 40

# Pane borders
set -g pane-border-style "fg=#3b4261"
set -g pane-active-border-style "fg=#7aa2f7"

# Easy split bindings (use current path)
bind | split-window -h -c "#{pane_current_path}"
bind - split-window -v -c "#{pane_current_path}"

# Easy pane navigation (vim-like)
bind h select-pane -L
bind j select-pane -D
bind k select-pane -U
bind l select-pane -R

# Easy window navigation
bind -n M-Left  previous-window
bind -n M-Right next-window

# Reload config
bind r source-file ~/.tmux.conf \; display "Config reloaded!"
TMUXEOF
chown dlesieur:dlesieur "$TMUX_CONF"

# Auto-attach to tmux on interactive SSH login (for user dlesieur)
# This goes in .bashrc — only activates on interactive login, NOT in scripts
BASHRC="/home/dlesieur/.bashrc"
if ! grep -q 'TMUX_AUTO_ATTACH' "$BASHRC" 2>/dev/null; then
	cat >>"$BASHRC" <<'BASHEOF'

# ── tmux auto-attach (SSH sessions survive disconnects) ──────
# Only in interactive SSH sessions, not in scripts or VS Code integrated terminal
TMUX_AUTO_ATTACH=1
if [ -n "$SSH_CONNECTION" ] && [ -z "$TMUX" ] && [ -z "$VSCODE_INJECTION" ] && [ -t 0 ]; then
    # Try to attach to existing 'dev' session, or create one
    tmux has-session -t dev 2>/dev/null && exec tmux attach -t dev || exec tmux new -s dev
fi
BASHEOF
fi
chown dlesieur:dlesieur "$BASHRC"

echo "[OK] tmux configured with auto-attach for dlesieur"

### ─── 10. Git config (fix NAT large-clone stalls) ──────────────────────────
git config --system http.postBuffer 524288000
git config --system http.lowSpeedLimit 1000
git config --system http.lowSpeedTime 60
git config --system core.compression 0
echo "[OK] Git configured"

### ─── 11. Monitoring script ────────────────────────────────────────────────
# Already copied to /usr/local/bin/monitoring.sh by late_command
chmod +x /usr/local/bin/monitoring.sh 2>/dev/null || true

# Crontab: every 10 minutes, broadcast to all terminals
echo "*/10 * * * * root /usr/local/bin/monitoring.sh" >>/etc/crontab
echo "[OK] Monitoring cron set"

### ─── 12. Lighttpd + PHP-FPM + WordPress routing ────────────────────────────
# Detect installed PHP-FPM version and socket path
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "8.2")
PHP_SOCK="/run/php/php${PHP_VER}-fpm.sock"

# Enable mod_fastcgi (base module)
lighty-enable-mod fastcgi </dev/null 2>/dev/null || true

# DISABLE the stock fastcgi-php config — it uses php-cgi, NOT php-fpm,
# and creates a conflicting fastcgi.server entry for ".php".
lighty-disable-mod fastcgi-php </dev/null 2>/dev/null || true
rm -f /etc/lighttpd/conf-enabled/15-fastcgi-php.conf

# Also remove the default "unconfigured" placeholder page
rm -f /etc/lighttpd/conf-enabled/99-unconfigured.conf

# Create a single, clean WordPress + PHP-FPM config
# This is the ONLY file that defines how PHP is served.
cat >/etc/lighttpd/conf-available/99-wordpress.conf <<WPLIGHT
# ── WordPress + PHP-FPM on Lighttpd ──────────────────────────
# Load required modules (mod_fastcgi is loaded by 10-fastcgi.conf)
server.modules += ( "mod_rewrite" )

# PHP-FPM via Unix socket (the only PHP handler — no php-cgi)
fastcgi.server += ( ".php" =>
    (( "socket" => "${PHP_SOCK}",
       "broken-scriptfilename" => "enable"
    ))
)

# Root redirect: / → /wordpress/
url.redirect = ( "^/$" => "/wordpress/" )

# WordPress pretty permalinks — protect wp-admin/wp-content/wp-includes
# directories from being rewritten (they are directories, not files, so
# url.rewrite-if-not-file would catch them and break wp-admin).
url.rewrite-if-not-file = (
    "^/wordpress/wp-admin(.*)"    => "/wordpress/wp-admin\$1",
    "^/wordpress/wp-includes(.*)" => "/wordpress/wp-includes\$1",
    "^/wordpress/wp-content(.*)"  => "/wordpress/wp-content\$1",
    "^/wordpress/(.*\\.php.*)"    => "/wordpress/\$1",
    "^/wordpress/(.*)"            => "/wordpress/index.php/\$1"
)

# Default index file
index-file.names += ( "index.php" )

# Allow uploads up to 32 MB
server.max-request-size = 32768
WPLIGHT

# Enable the config (create symlink)
ln -sf /etc/lighttpd/conf-available/99-wordpress.conf \
	/etc/lighttpd/conf-enabled/99-wordpress.conf 2>/dev/null || true

# Ensure lighttpd listens on port 80
if ! grep -q 'server.port' /etc/lighttpd/lighttpd.conf 2>/dev/null; then
	echo 'server.port = 80' >>/etc/lighttpd/lighttpd.conf
fi
echo "[OK] Lighttpd + PHP-FPM + WordPress routing configured"

### ─── 13. AppArmor — mandatory at startup + enforce profiles ────────────────
systemctl enable apparmor || true

# Create enforcement-ready AppArmor profile for PHP-FPM
mkdir -p /etc/apparmor.d
cat >/etc/apparmor.d/local/usr.sbin.php-fpm 2>/dev/null <<'AAEOF' || true
# Allow PHP-FPM to serve WordPress
/var/www/html/wordpress/** r,
/var/www/html/wordpress/wp-content/uploads/** rw,
/var/www/html/wordpress/wp-content/cache/** rw,
/tmp/** rw,
/run/php/** rw,
AAEOF

# Enforce AppArmor profiles if they exist
for profile in usr.sbin.php-fpm${PHP_VER} usr.sbin.lighttpd; do
	if [ -f "/etc/apparmor.d/${profile}" ]; then
		aa-enforce "/etc/apparmor.d/${profile}" 2>/dev/null || true
	fi
done
echo "[OK] AppArmor enabled + WordPress profiles configured"

### ─── 14. Enable all services (NO restart — no systemd in chroot) ──────────
for svc in lighttpd mariadb haveged cron ssh nat-keepalive sshd-watchdog; do
	systemctl enable "$svc" 2>/dev/null || true
done
for f in /lib/systemd/system/php*-fpm.service; do
	[ -f "$f" ] && systemctl enable "$(basename "$f")" 2>/dev/null || true
done
# Disable conflict docker services
systemctl disable postgresql 2>/dev/null || true
systemctl disable redis-server 2>/dev/null || true
echo "[OK] Services enabled"

### ─── 15. First-boot script (Docker + WordPress) ───────────────────────────
# Already copied to /root/first-boot-setup.sh by late_command
chmod +x /root/first-boot-setup.sh 2>/dev/null || true
echo "@reboot root /bin/bash /root/first-boot-setup.sh" >>/etc/crontab
echo "[OK] First-boot hook registered"

### ─── 16. MOTD ─────────────────────────────────────────────────────────────
cat >/etc/motd <<'MOTDEOF'

  ╔═══════════════════════════════════════════════════════╗
  ║            BORN2BEROOT SECURE SYSTEM                  ║
  ╠═══════════════════════════════════════════════════════╣
  ║  Hostname:   dlesieur42      SSH Port: 4242           ║
  ║  Firewall:   Active (UFW)    AppArmor: Enforced       ║
  ║  Monitoring: Every 10 min    Sudo log: /var/log/sudo/ ║
  ╠═══════════════════════════════════════════════════════╣
  ║  🔒 tmux auto-attach is ON                            ║
  ║                                                       ║
  ║  Your session runs inside tmux and survives SSH drops.║
  ║  If disconnected, just reconnect — you'll be right    ║
  ║  back where you left off.                             ║
  ║                                                       ║
  ║  Quick reference:                                     ║
  ║    Ctrl+B d    → detach (leave session running)       ║
  ║    Ctrl+B |    → split pane horizontally              ║
  ║    Ctrl+B -    → split pane vertically                ║
  ║    Ctrl+B c    → new window                           ║
  ║    Ctrl+B n/p  → next/previous window                 ║
  ║    tmux ls     → list sessions                        ║
  ╠═══════════════════════════════════════════════════════╣
  ║  WARNING: All actions are logged.                     ║
  ╚═══════════════════════════════════════════════════════╝

MOTDEOF
echo "[OK] MOTD set"

echo "=== Born2beRoot setup complete ($(date)) ==="
