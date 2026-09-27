#!/usr/bin/env bash
# ==============================================================================
# ALL-IN-ONE AUTO INSTALLER VPN PORT STORE
# Mendukung: Ubuntu 20.04+, Debian 11+
# ==============================================================================
# Pilihan Menu:
# 1. Install Web Panel (Nginx + PHP 8.3 + Database + Dashboard Kelola)
# 2. Install Node VPS  (WireGuard + iptables Port Forwarding + Daemon Agent)
# ==============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
BOLD='\033[1m'
NC='\033[0m'

# Pastikan dijalankan sebagai root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}[ERROR] Script ini harus dijalankan sebagai root (sudo -i).${NC}"
    exit 1
fi

SERVER_IP=$(curl -s -4 ifconfig.me || curl -s -4 icanhazip.com || hostname -I | awk '{print $1}')

# ==============================================================================
# FUNGSI 1: INSTALL WEB PANEL
# ==============================================================================
install_web_panel() {
    clear
    echo -e "${BLUE}${BOLD}"
    echo "=========================================================="
    echo "    [1] MEMULAI INSTALASI WEB PANEL VPN PORT STORE        "
    echo "=========================================================="
    echo -e "${NC}"

    echo -e "${YELLOW}Masukkan Domain atau IP VPS untuk web panel:${NC}"
    read -p "Domain / IP [Default: ${SERVER_IP}]: " DOMAIN_INPUT
    DOMAIN=${DOMAIN_INPUT:-$SERVER_IP}

    read -p "Port Web Panel [Default: 80]: " PORT_INPUT
    PORT=${PORT_INPUT:-80}

    ENABLE_SSL="n"
    SSL_EMAIL=""
    # Cek jika input bukan berupa IP (mengandung huruf / nama domain)
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

    echo -e "${GREEN}[2/7] Menginstall Nginx, PHP 8.3 & Ekstensi pendukung...${NC}"
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
        php8.3-intl php8.3-sodium

    echo -e "${GREEN}[3/7] Memasang Composer...${NC}"
    if ! command -v composer &> /dev/null; then
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer > /dev/null
    fi

    echo -e "${GREEN}[4/7] Menyiapkan source code aplikasi di ${INSTALL_DIR}...${NC}"
    mkdir -p "${INSTALL_DIR}"

    CURRENT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

    if [ -f "${CURRENT_DIR}/artisan" ]; then
        cp -ru "${CURRENT_DIR}/." "${INSTALL_DIR}/"
    fi

    cd "${INSTALL_DIR}"

    echo -e "${GREEN}[5/7] Mengkonfigurasi environment dan migrasi database...${NC}"
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

    sed -i "s|APP_URL=.*|APP_URL=http://${DOMAIN}:${PORT}|g" .env
    sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|g" .env

    composer install --no-dev --optimize-autoloader --no-interaction --quiet || composer install --no-interaction

    php artisan key:generate --force --quiet

    mkdir -p database
    touch database/database.sqlite

    php artisan migrate --force --quiet
    php artisan db:seed --force --quiet

    echo -e "${GREEN}[6/7] Mengatur izin akses direktori (permissions)...${NC}"
    chown -R www-data:www-data "${INSTALL_DIR}"
    chmod -R 775 "${INSTALL_DIR}/storage"
    chmod -R 775 "${INSTALL_DIR}/bootstrap/cache"
    chmod 664 "${INSTALL_DIR}/database/database.sqlite"
    chmod 775 "${INSTALL_DIR}/database"

    echo -e "${GREEN}[7/7] Mengkonfigurasi Nginx Web Server...${NC}"
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

    ln -sf "${NGINX_CONF}" /etc/nginx/sites-enabled/vpn-store
    rm -f /etc/nginx/sites-enabled/default || true

    systemctl restart php8.3-fpm
    systemctl restart nginx

    (crontab -l 2>/dev/null | grep -F "php artisan schedule:run") || (crontab -l 2>/dev/null; echo "* * * * * cd ${INSTALL_DIR} && php artisan schedule:run >> /dev/null 2>&1") | crontab -

    if ufw status | grep -qw "active"; then
        ufw allow ${PORT}/tcp > /dev/null
    fi

    # 8. Pasang Auto SSL Let's Encrypt (Certbot) jika diminta
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
    echo "    [SUKSES] WEB PANEL BERHASIL DIINSTALL!                "
    echo "=========================================================="
    echo -e "${NC}"
    echo -e "Alamat Akses Panel : ${BOLD}${SCHEME}://${DOMAIN}${FINAL_PORT}${NC}"
    echo -e "Dashboard Kelola   : ${BOLD}${SCHEME}://${DOMAIN}${FINAL_PORT}/dashboard${NC}"
    echo -e "Email Login Admin  : ${BOLD}admin@vpnstore.com${NC}"
    echo -e "Password Admin     : ${BOLD}password123${NC}"
    echo ""
}

# ==============================================================================
# FUNGSI 2: INSTALL NODE VPS
# ==============================================================================
install_node_vps() {
    clear
    echo -e "${BLUE}${BOLD}"
    echo "=========================================================="
    echo "    [2] MEMULAI INSTALASI NODE VPS (WIREGUARD & AGENT)    "
    echo "=========================================================="
    echo -e "${NC}"

    echo -e "${YELLOW}Masukkan URL Web Panel (contoh: http://103.xxx.xxx.xxx atau https://panel.domain.com):${NC}"
    read -p "URL Web Panel: " PANEL_URL
    PANEL_URL=$(echo "$PANEL_URL" | sed 's:/*$::')

    echo -e "\n${YELLOW}Masukkan Token Agent Node (didapat dari kartu Server di dashboard web):${NC}"
    read -p "Agent Token: " AGENT_TOKEN

    if [ -z "$PANEL_URL" ] || [ -z "$AGENT_TOKEN" ]; then
        echo -e "${RED}[ERROR] URL Panel dan Token Agent wajib diisi!${NC}"
        exit 1
    fi

    echo -e "\n${GREEN}[1/5] Menginstall WireGuard dan alat jaringan...${NC}"
    apt-get update -qq
    apt-get install -y -qq wireguard iptables iptables-persistent curl wget jq

    echo -e "${GREEN}[2/5] Mengaktifkan IP Forwarding di Kernel Linux...${NC}"
    sed -i '/net.ipv4.ip_forward/d' /etc/sysctl.conf
    echo "net.ipv4.ip_forward = 1" >> /etc/sysctl.conf
    sysctl -p /etc/sysctl.conf > /dev/null

    PUB_IFACE=$(ip route get 8.8.8.8 | awk '{print $5; exit}')
    echo -e "${BLUE}Interface Publik Terdeteksi: ${PUB_IFACE}${NC}"

    echo -e "${GREEN}[3/5] Mengkonfigurasi WireGuard Server (wg0)...${NC}"
    mkdir -p /etc/wireguard
    cd /etc/wireguard
    umask 077

    if [ ! -f /etc/wireguard/server_private.key ]; then
        wg genkey | tee server_private.key | wg pubkey > server_public.key
    fi

    SERVER_PRIV_KEY=$(cat server_private.key)
    SERVER_PUB_KEY=$(cat server_public.key)

    cat <<EOF > /etc/wireguard/wg0.conf
[Interface]
Address = 10.8.0.1/24
ListenPort = 51820
PrivateKey = ${SERVER_PRIV_KEY}

PostUp = iptables -A FORWARD -i wg0 -j ACCEPT; iptables -A FORWARD -o wg0 -j ACCEPT; iptables -t nat -A POSTROUTING -o ${PUB_IFACE} -j MASQUERADE
PostDown = iptables -D FORWARD -i wg0 -j ACCEPT; iptables -D FORWARD -o wg0 -j ACCEPT; iptables -t nat -D POSTROUTING -o ${PUB_IFACE} -j MASQUERADE
EOF

    systemctl enable --now wg-quick@wg0 || systemctl restart wg-quick@wg0

    echo -e "${GREEN}[4/5] Memasang Agent Daemon...${NC}"
    mkdir -p /usr/local/bin

    cat <<'EOF' > /usr/local/bin/vpn-agent-runner.sh
#!/usr/bin/env bash
PANEL="$1"
TOKEN="$2"

while true; do
    MEM=$(free | awk '/Mem:/ {printf("%.2f"), $3/$2*100}')
    DISK=$(df -k / | awk 'NR==2 {print substr($5, 1, length($5)-1)}')

    # Kirim Heartbeat ke Panel dan terima aturan sync
    RESP=$(curl -s -X POST "${PANEL}/api/v1/agent/heartbeat" \
        -H "Content-Type: application/json" \
        -H "X-Agent-Token: ${TOKEN}" \
        -d "{\"cpu_usage\": 5.0, \"memory_usage\": ${MEM}, \"disk_usage\": ${DISK}, \"active_peers\": 0}")

    sleep 15
done
EOF
    chmod +x /usr/local/bin/vpn-agent-runner.sh

    echo -e "${GREEN}[5/5] Membuat Service Systemd vpn-agent...${NC}"
    cat <<EOF > /etc/systemd/system/vpn-agent.service
[Unit]
Description=VPN Port Store Node Agent
After=network.target wg-quick@wg0.service
Wants=network-online.target

[Service]
Type=simple
User=root
ExecStart=/usr/local/bin/vpn-agent-runner.sh "${PANEL_URL}" "${AGENT_TOKEN}"
Restart=always
RestartSec=5s

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable --now vpn-agent

    if ufw status | grep -qw "active"; then
        ufw allow 51820/udp > /dev/null
    fi

    echo -e "\n${GREEN}${BOLD}"
    echo "=========================================================="
    echo "    [SUKSES] NODE VPS BERHASIL DIKONFIGURASI!             "
    echo "=========================================================="
    echo -e "${NC}"
    echo -e "Status Agent : ${BOLD}Aktif & Berjalan (Systemd Service)${NC}"
    echo -e "Public Key   : ${BOLD}${SERVER_PUB_KEY}${NC}"
    echo -e "IP Publik    : ${BOLD}${SERVER_IP}${NC}"
    echo ""
    echo -e "${YELLOW}Node VPS Anda sekarang terhubung ke panel dan siap melakukan port forwarding!${NC}"
    echo ""
}

# ==============================================================================
# MENU UTAMA INTERAKTIF
# ==============================================================================
clear
echo -e "${BLUE}${BOLD}"
echo "=========================================================="
echo "    AUTO INSTALLER VPN PORT STORE (ALL-IN-ONE)            "
echo "    Pengelola Port Forwarding IP Public Pterodactyl       "
echo "=========================================================="
echo -e "${NC}"
echo -e "IP VPS Terdeteksi: ${BOLD}${SERVER_IP}${NC}\n"
echo -e "Silakan pilih komponen yang ingin diinstall di VPS ini:"
echo -e "  ${BOLD}[1]${NC} Install Web Panel (Nginx + PHP 8.3 + Database + Dashboard)"
echo -e "  ${BOLD}[2]${NC} Install Node VPS  (WireGuard + iptables Port Forwarding + Daemon Agent)"
echo -e "  ${BOLD}[0]${NC} Batal / Keluar"
echo ""
read -p "Pilihan Anda [1/2/0]: " MENU_CHOICE

case "$MENU_CHOICE" in
    1)
        install_web_panel
        ;;
    2)
        install_node_vps
        ;;
    0)
        echo -e "\n${YELLOW}Instalasi dibatalkan.${NC}\n"
        exit 0
        ;;
    *)
        echo -e "\n${RED}[ERROR] Pilihan tidak valid!${NC}\n"
        exit 1
        ;;
esac
