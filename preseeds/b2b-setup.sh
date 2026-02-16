#!/bin/bash
# Born2beRoot post-installation setup script
# Runs inside in-target (chroot to /target) during d-i late_command
# ─────────────────────────────────────────────────────────────────
# IMPORTANT: Docker can NOT be installed here (no systemd, limited
# network in chroot). Docker is installed by first-boot-setup.sh
# which runs on the first real boot with full network + systemd.
# ─────────────────────────────────────────────────────────────────
set +e  # Don't exit on errors — best effort
export DEBIAN_FRONTEND=noninteractive
export DEBCONF_NONINTERACTIVE_SEEN=true

LOG=/var/log/b2b-setup.log
exec > >(tee -a "$LOG") 2>&1

echo "=== Born2beRoot setup starting ($(date)) ==="

### ─── 1. APT sources ────────────────────────────────────────────────────────
cat > /etc/apt/sources.list << 'SRCEOF'
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

### ─── 3. Hostname — Born2beRoot requires login+42 ───────────────────────────
echo "dlesieur42" > /etc/hostname
hostname dlesieur42 2>/dev/null || true

# Fix /etc/hosts — replace any old hostname or add the correct one
if grep -q "127\.0\.1\.1" /etc/hosts 2>/dev/null; then
    sed -i 's/127\.0\.1\.1.*/127.0.1.1\tdlesieur42/' /etc/hosts
else
    echo "127.0.1.1	dlesieur42" >> /etc/hosts
fi
# Also ensure localhost line exists
grep -q "127\.0\.0\.1.*localhost" /etc/hosts || \
    sed -i '1i 127.0.0.1\tlocalhost' /etc/hosts
echo "[OK] Hostname set to dlesieur42"

### ─── 4. Groups & user ─────────────────────────────────────────────────────
groupadd user42 2>/dev/null || true
usermod -aG sudo,user42 dlesieur
echo "[OK] User dlesieur in groups: sudo, user42"

### ─── 5. SSH — port 4242, no root login ─────────────────────────────────────
sed -i 's/^#*Port .*/Port 4242/' /etc/ssh/sshd_config
sed -i 's/^#*PermitRootLogin .*/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/^#*PasswordAuthentication .*/PasswordAuthentication yes/' /etc/ssh/sshd_config
systemctl enable ssh || true
echo "[OK] SSH configured on port 4242"

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

cat > /etc/sudoers.d/sudo_config << 'SUDOEOF'
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
    "enforce_for_root" \
; do
    key=$(echo "$setting" | cut -d= -f1 | xargs)
    if grep -q "^#* *${key}" /etc/security/pwquality.conf 2>/dev/null; then
        sed -i "s/^#* *${key}.*/${setting}/" /etc/security/pwquality.conf
    else
        echo "$setting" >> /etc/security/pwquality.conf
    fi
done
echo "[OK] Password policy set"

### ─── 9. Git config (fix NAT large-clone stalls) ───────────────────────────
git config --system http.postBuffer 524288000
git config --system http.lowSpeedLimit 1000
git config --system http.lowSpeedTime 60
git config --system core.compression 0
echo "[OK] Git configured"

### ─── 10. Monitoring script ─────────────────────────────────────────────────
# Already copied to /usr/local/bin/monitoring.sh by late_command
chmod +x /usr/local/bin/monitoring.sh 2>/dev/null || true

# Crontab: every 10 minutes, broadcast to all terminals
echo "*/10 * * * * root /usr/local/bin/monitoring.sh" >> /etc/crontab
echo "[OK] Monitoring cron set"

### ─── 11. Lighttpd + PHP-FPM ────────────────────────────────────────────────
lighty-enable-mod fastcgi < /dev/null 2>/dev/null || true
lighty-enable-mod fastcgi-php < /dev/null 2>/dev/null || true
echo "[OK] Lighttpd fastcgi modules enabled"

### ─── 12. AppArmor — mandatory at startup ───────────────────────────────────
systemctl enable apparmor || true
echo "[OK] AppArmor enabled"

### ─── 13. Enable all services (NO restart — no systemd in chroot) ──────────
for svc in lighttpd mariadb haveged cron ssh; do
    systemctl enable "$svc" 2>/dev/null || true
done
for f in /lib/systemd/system/php*-fpm.service; do
    [ -f "$f" ] && systemctl enable "$(basename "$f")" 2>/dev/null || true
done
echo "[OK] Services enabled"

### ─── 14. First-boot script (Docker + WordPress) ───────────────────────────
# Already copied to /root/first-boot-setup.sh by late_command
chmod +x /root/first-boot-setup.sh 2>/dev/null || true
echo "@reboot root /bin/bash /root/first-boot-setup.sh" >> /etc/crontab
echo "[OK] First-boot hook registered"

### ─── 15. MOTD ─────────────────────────────────────────────────────────────
cat > /etc/motd << 'MOTDEOF'

  ╔═══════════════════════════════════════════════════════╗
  ║            BORN2BEROOT SECURE SYSTEM                  ║
  ╚═══════════════════════════════════════════════════════╝

  Hostname:   dlesieur42      SSH Port: 4242
  Firewall:   Active (UFW)    AppArmor: Enforced
  Monitoring: Every 10 min    Sudo log: /var/log/sudo/

  WARNING: All actions are logged.

MOTDEOF
echo "[OK] MOTD set"

echo "=== Born2beRoot setup complete ($(date)) ==="
