#!/usr/bin/env bash
# ==============================================================================
# AUTO INSTALLER WEB PANEL VPN PORT STORE (UBUNTU / DEBIAN)
# ==============================================================================
# Skrip ini menginstall seluruh komponen yang dibutuhkan:
# - Nginx Web Server
# - PHP 8.3 & PHP-FPM + Ekstensi (curl, mbstring, xml, zip, sqlite3, bcmath, sodium)
# - Composer
# - Setup Project Laravel, Permission, Migrasi Database & Seeder
# - Konfigurasi Virtualhost Nginx & Crontab Scheduler
# ==============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
BOLD='\033[1m'
NC='\033[0m'

clear
echo -e "${BLUE}${BOLD}"
echo "=========================================================="
echo "    AUTO INSTALLER WEB PANEL VPN PORT STORE               "
echo "    Pengelola Port Forwarding IP Public Pterodactyl       "
echo "=========================================================="
echo -e "${NC}"

# 1. Pastikan dijalankan sebagai root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}[ERROR] Script ini harus dijalankan sebagai root. Ketik: sudo -i lalu jalankan kembali.${NC}"
    exit 1
fi

# 2. Input Konfigurasi (Domain / IP & Port)
SERVER_IP=$(curl -s -4 ifconfig.me || curl -s -4 icanhazip.com || hostname -I | awk '{print $1}')

echo -e "${YELLOW}Masukkan Domain atau IP VPS untuk web panel:${NC}"
read -p "Domain / IP [Default: ${SERVER_IP}]: " DOMAIN_INPUT
DOMAIN=${DOMAIN_INPUT:-$SERVER_IP}

read -p "Port Web Panel [Default: 80]: " PORT_INPUT
PORT=${PORT_INPUT:-80}

ENABLE_SSL="n"
SSL_EMAIL=""
if [[ "$DOMAIN" =~ [a-zA-Z] ]]; then
    echo -e "\n${YELLOW}Domain terdeteksi: ${DOMAIN}${NC}"
    read -p "Aktifkan Auto SSL Gratis (HTTPS / Let's Encrypt)? [Y/n]: " SSL_CHOICE
    SSL_CHOICE=${SSL_CHOICE:-Y}
    if [[ "$SSL_CHOICE" =~ ^[Yy]$ ]]; then
        ENABLE_SSL="y"
        read -p "Masukkan Email untuk notifikasi SSL [Default: admin@${DOMAIN}]: " EMAIL_INPUT
        SSL_EMAIL=${EMAIL_INPUT:-admin@${DOMAIN}}
    fi
fi

INSTALL_DIR="/var/www/vpn-store"

echo -e "\n${GREEN}[1/8] Mengupdate repository sistem & dependensi dasar...${NC}"
apt-get update -qq
apt-get install -y -qq curl wget git unzip ufw software-properties-common ca-certificates lsb-release

# 3. Install PHP 8.3 & Ekstensi
echo -e "${GREEN}[2/8] Menginstall Nginx, PHP 8.3 & Ekstensi pendukung...${NC}"
OS_NAME=$(lsb_release -is | tr '[:upper:]' '[:lower:]')

if [ "$OS_NAME" = "ubuntu" ]; then
    add-apt-repository -y ppa:ondrej/php > /dev/null 2>&1 || true
    apt-get update -qq
elif [ "$OS_NAME" = "debian" ]; then
    wget -qO /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
    echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
    apt-get update -qq
fi

apt-get install -y -qq nginx \
    php8.3-cli php8.3-fpm php8.3-common php8.3-mysql php8.3-sqlite3 \
    php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip php8.3-bcmath \
    php8.3-intl

# 4. Install Composer
echo -e "${GREEN}[3/8] Memasang Composer...${NC}"
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer > /dev/null
fi

# 5. Salin / Deploy Source Code ke /var/www/vpn-store
echo -e "${GREEN}[4/8] Menyiapkan source code aplikasi di ${INSTALL_DIR}...${NC}"
export COMPOSER_ALLOW_SUPERUSER=1

CURRENT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ -f "${CURRENT_DIR}/artisan" ] && [ -f "${CURRENT_DIR}/composer.json" ]; then
    echo -e "${BLUE}Menyalin file project dari direktori saat ini...${NC}"
    mkdir -p "${INSTALL_DIR}"
    cp -a "${CURRENT_DIR}/." "${INSTALL_DIR}/"
else
    echo -e "${BLUE}Mengunduh source code dari GitHub (https://github.com/ynzynnn/vpn-store.git)...${NC}"
    TMP_DIR="/tmp/vpn-store-clone-$$"
    rm -rf "${TMP_DIR}"
    git clone --depth 1 https://github.com/ynzynnn/vpn-store.git "${TMP_DIR}"

    if [ ! -f "${TMP_DIR}/composer.json" ]; then
        echo -e "${RED}[ERROR] Gagal mengunduh source code dari GitHub. Periksa koneksi internet VPS Anda.${NC}"
        exit 1
    fi

    mkdir -p "${INSTALL_DIR}"
    cp -a "${TMP_DIR}/." "${INSTALL_DIR}/"
    rm -rf "${TMP_DIR}"
fi

cd "${INSTALL_DIR}"

if [ ! -f "${INSTALL_DIR}/composer.json" ]; then
    echo -e "${RED}[ERROR] File composer.json tidak ditemukan di ${INSTALL_DIR}!${NC}"
    exit 1
fi

# 6. Setup Environment (.env) & Database
echo -e "${GREEN}[5/8] Mengkonfigurasi environment dan migrasi database...${NC}"
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        cat <<EOF > .env
APP_NAME="VPN Port Store"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://${DOMAIN}:${PORT}

APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_FAKER_LOCALE=id_ID

DB_CONNECTION=sqlite
DB_DATABASE=${INSTALL_DIR}/database/database.sqlite

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
EOF
    fi
fi

# Set URL di .env
sed -i "s|APP_URL=.*|APP_URL=http://${DOMAIN}:${PORT}|g" .env
sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|g" .env

# Install dependensi PHP (Production)
echo -e "${BLUE}Menginstall dependensi Composer...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction

mkdir -p database
touch database/database.sqlite

# Generate App Key jika kosong
php artisan key:generate --force

# Migrasi dan Seeder
php artisan migrate --force
php artisan db:seed --force

# Set Permissions
echo -e "${GREEN}[6/8] Mengatur izin akses direktori (permissions)...${NC}"
chown -R www-data:www-data "${INSTALL_DIR}"
chmod -R 775 "${INSTALL_DIR}/storage"
chmod -R 775 "${INSTALL_DIR}/bootstrap/cache"
chmod 664 "${INSTALL_DIR}/database/database.sqlite"
chmod 775 "${INSTALL_DIR}/database"

# 7. Konfigurasi Nginx Virtualhost
echo -e "${GREEN}[7/8] Mengkonfigurasi Nginx Web Server...${NC}"
NGINX_CONF="/etc/nginx/sites-available/vpn-store"

cat <<EOF > "${NGINX_CONF}"
server {
    listen ${PORT};
    listen [::]:${PORT};
    server_name ${DOMAIN};
    root ${INSTALL_DIR}/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Aktifkan konfigurasi Nginx
ln -sf "${NGINX_CONF}" /etc/nginx/sites-enabled/vpn-store
rm -f /etc/nginx/sites-enabled/default || true

nginx -t > /dev/null 2>&1
systemctl restart php8.3-fpm
systemctl restart nginx

# Pasang cron scheduler Laravel
(crontab -l 2>/dev/null | grep -F "php artisan schedule:run") || (crontab -l 2>/dev/null; echo "* * * * * cd ${INSTALL_DIR} && php artisan schedule:run >> /dev/null 2>&1") | crontab -

# Buka firewall jika UFW aktif
if ufw status | grep -qw "active"; then
    ufw allow ${PORT}/tcp > /dev/null
    ufw allow 51820/udp > /dev/null
fi

SCHEME="http"
FINAL_PORT=":${PORT}"
if [ "$PORT" = "80" ]; then
    FINAL_PORT=""
fi

if [ "$ENABLE_SSL" = "y" ]; then
    echo -e "${GREEN}[8/8] Memasang Auto SSL Let's Encrypt (Certbot)...${NC}"
    apt-get install -y -qq certbot python3-certbot-nginx

    if ufw status | grep -qw "active"; then
        ufw allow 443/tcp > /dev/null
    fi

    echo -e "${BLUE}Menghubungi Let's Encrypt untuk menerbitkan sertifikat SSL...${NC}"
    if certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "${SSL_EMAIL}" --redirect; then
        SCHEME="https"
        FINAL_PORT=""
        sed -i "s|APP_URL=.*|APP_URL=https://${DOMAIN}|g" "${INSTALL_DIR}/.env"
        systemctl restart nginx
        echo -e "${GREEN}[OK] Sertifikat SSL berhasil dipasang & redirect HTTPS aktif!${NC}"
    else
        echo -e "${YELLOW}[PERINGATAN] Penerbitan SSL gagal. Pastikan DNS A record domain sudah mengarah ke IP VPS ini.${NC}"
        echo -e "${YELLOW}Panel tetap dapat diakses melalui HTTP.${NC}"
    fi
fi

echo -e "\n${GREEN}${BOLD}"
echo "=========================================================="
echo "    INSTALASI SELESAI & WEB PANEL SIAP DIGUNAKAN!         "
echo "=========================================================="
echo -e "${NC}"
echo -e "Alamat Akses Panel : ${BOLD}${SCHEME}://${DOMAIN}${FINAL_PORT}${NC}"
echo -e "Dashboard Kelola   : ${BOLD}${SCHEME}://${DOMAIN}${FINAL_PORT}/dashboard${NC}"
echo -e "Email Login Admin  : ${BOLD}admin@vpnstore.com${NC}"
echo -e "Password Admin     : ${BOLD}password123${NC}"
echo ""
echo -e "${YELLOW}Catatan:${NC}"
echo "1. Segera ganti kata sandi setelah Anda login."
echo "2. Tambahkan node VPS di tab 'Node VPS' pada dashboard."
echo "3. Pada setiap node VPS, jalankan perintah auto-installer agent yang tersedia di dashboard."
echo ""
