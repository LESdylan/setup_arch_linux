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
apt-get install -y -qq -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold \
    sudo ufw curl wget net-tools vim
apt-get install -y -qq -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold \
    libpam-pwquality apparmor apparmor-utils cron haveged
apt-get install -y -qq -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold \
    lighttpd mariadb-server php-fpm php-mysql php-cgi php-mbstring php-xml php-gd php-curl

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
sed -i 's/^# minlen = .*/minlen = 10/' /etc/security/pwquality.conf
sed -i 's/^# dcredit = .*/dcredit = -1/' /etc/security/pwquality.conf
sed -i 's/^# ucredit = .*/ucredit = -1/' /etc/security/pwquality.conf
sed -i 's/^# lcredit = .*/lcredit = -1/' /etc/security/pwquality.conf
sed -i 's/^# maxrepeat = .*/maxrepeat = 3/' /etc/security/pwquality.conf
sed -i 's/^# usercheck = .*/usercheck = 1/' /etc/security/pwquality.conf
sed -i 's/^# difok = .*/difok = 7/' /etc/security/pwquality.conf
sed -i 's/^# enforce_for_root.*/enforce_for_root/' /etc/security/pwquality.conf

### Monitoring script ###
# Already copied to /usr/local/bin/monitoring.sh by late_command (from /cdrom/)
chmod +x /usr/local/bin/monitoring.sh || true

### Crontab ###
echo "*/10 * * * * root /usr/local/bin/monitoring.sh" >> /etc/crontab

### Lighttpd fastcgi ###
lighty-enable-mod fastcgi < /dev/null || true
lighty-enable-mod fastcgi-php < /dev/null || true

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

echo "=== Born2beRoot setup complete ==="
