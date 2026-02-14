#!/bin/bash
# Born2beRoot post-installation setup script
# Runs inside in-target (chroot to /target) during d-i late_command
set +e  # Don't exit on errors — best effort
export DEBIAN_FRONTEND=noninteractive
export DEBCONF_NONINTERACTIVE_SEEN=true

echo "=== Born2beRoot setup starting ==="

### APT sources ###
cat > /etc/apt/sources.list << 'SRCEOF'
deb http://deb.debian.org/debian trixie main contrib non-free non-free-firmware
deb http://deb.debian.org/debian trixie-updates main contrib non-free non-free-firmware
deb http://security.debian.org/debian-security trixie-security main contrib non-free non-free-firmware
SRCEOF

### Install packages ###
apt-get clean
apt-get update -qq

# ── Core Born2beRoot requirements ──
apt-get install -y -qq -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold \
    sudo ufw curl wget net-tools vim openssh-server

# ── Security & monitoring ──
apt-get install -y -qq -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold \
    libpam-pwquality apparmor apparmor-utils cron haveged

# ── Bonus: Web stack (lighttpd + MariaDB + PHP) ──
apt-get install -y -qq -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold \
    lighttpd mariadb-server php-fpm php-mysql php-cgi php-mbstring php-xml php-gd php-curl

# ── Developer essentials ──
apt-get install -y -qq -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold \
    git git-lfs build-essential gcc g++ make cmake \
    python3 python3-pip python3-venv \
    man-db manpages-dev \
    htop tree tmux bash-completion \
    zip unzip tar gzip bzip2 xz-utils \
    ca-certificates gnupg lsb-release \
    rsync less file patch diffutils \
    dnsutils iputils-ping traceroute \
    lsof strace ltrace \
    jq bc

### Hostname — Born2beRoot requires login+42 ###
echo "dlesieur42" > /etc/hostname
sed -i 's/127\.0\.1\.1.*/127.0.1.1\tdlesieur42/' /etc/hosts || \
    echo "127.0.1.1	dlesieur42" >> /etc/hosts

### Groups & user ###
groupadd user42 || true
usermod -aG sudo,user42 dlesieur

### SSH ###
sed -i 's/^#*Port 22/Port 4242/' /etc/ssh/sshd_config
sed -i 's/^#*PermitRootLogin .*/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/^#*PasswordAuthentication .*/PasswordAuthentication yes/' /etc/ssh/sshd_config
systemctl enable ssh

### UFW ###
ufw default deny incoming
ufw default allow outgoing
ufw allow 4242/tcp
ufw allow 80/tcp
ufw allow 443/tcp
echo y | ufw enable

### Sudo config ###
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

### Password policy ###
sed -i 's/^PASS_MAX_DAYS.*/PASS_MAX_DAYS\t30/' /etc/login.defs
sed -i 's/^PASS_MIN_DAYS.*/PASS_MIN_DAYS\t2/' /etc/login.defs
sed -i 's/^PASS_WARN_AGE.*/PASS_WARN_AGE\t7/' /etc/login.defs
sed -i 's/^PASS_MIN_LEN.*/PASS_MIN_LEN\t10/' /etc/login.defs

# pwquality.conf — use sed with fallback append for robustness
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

### Git config — fix large clone timeouts (NAT buffers can cause stalls) ###
git config --system http.postBuffer 524288000
git config --system http.lowSpeedLimit 1000
git config --system http.lowSpeedTime 60
git config --system core.compression 0

### Monitoring script ###
# Already copied to /usr/local/bin/monitoring.sh by late_command (from /cdrom/)
chmod +x /usr/local/bin/monitoring.sh || true

### Crontab ###
echo "*/10 * * * * root /usr/local/bin/monitoring.sh" >> /etc/crontab

### Lighttpd fastcgi ###
lighty-enable-mod fastcgi < /dev/null || true
lighty-enable-mod fastcgi-php < /dev/null || true

### AppArmor — ensure it's running at startup (mandatory) ###
systemctl enable apparmor || true

### Enable services (NO restart — systemd not running in chroot) ###
systemctl enable lighttpd || true
systemctl enable mariadb || true
systemctl enable haveged || true
systemctl enable cron || true
systemctl enable ssh || true
for f in /lib/systemd/system/php*-fpm.service; do
    [ -f "$f" ] && systemctl enable "$(basename "$f")" || true
done

### First-boot WordPress script ###
# Already copied to /root/first-boot-setup.sh by late_command (from /cdrom/)
chmod +x /root/first-boot-setup.sh || true
echo "@reboot root /root/first-boot-setup.sh" >> /etc/crontab

### MOTD banner ###
cat > /etc/motd << 'MOTDEOF'

  ╔═══════════════════════════════════════════════════════╗
  ║            BORN2BEROOT SECURE SYSTEM                  ║
  ╚═══════════════════════════════════════════════════════╝

  SSH Port:   4242      Firewall: Active (UFW)
  Monitoring: Every 10 minutes via wall
  WARNING: All sudo actions are logged.

MOTDEOF

echo "=== Born2beRoot setup complete ==="
