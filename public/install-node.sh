#!/usr/bin/env bash
# ==============================================================================
# VPN PORT STORE — AUTO INSTALLER NODE VPS (WIREGUARD + IPTABLES + GO AGENT)
# Mendukung: Ubuntu 20.04+, Debian 11+
# ==============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}"
echo "=========================================================="
echo "    VPN PORT STORE — PTERODACTYL NODE INSTALLER           "
echo "=========================================================="
echo -e "${NC}"

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}[ERROR] Script ini harus dijalankan sebagai root (sudo -i).${NC}"
    exit 1
fi

PANEL_URL=""
AGENT_TOKEN=""

# Parsing Argumen
for i in "$@"; do
    case $i in
        --panel=*)
            PANEL_URL="${i#*=}"
            shift
            ;;
        --token=*)
            AGENT_TOKEN="${i#*=}"
            shift
            ;;
        *)
            ;;
    esac
done

if [ -z "$PANEL_URL" ] || [ -z "$AGENT_TOKEN" ]; then
    echo -e "${YELLOW}Penggunaan:${NC}"
    echo "  bash install-node.sh --panel=https://panel.domain.com --token=TOKEN_ANDA"
    exit 1
fi

echo -e "${GREEN}[1/5] Mengupdate repository dan menginstall paket dependensi...${NC}"
apt-get update -qq
apt-get install -y -qq wireguard iptables iptables-persistent curl wget jq

echo -e "${GREEN}[2/5] Mengaktifkan IP Forwarding Linux Kernel...${NC}"
sed -i '/net.ipv4.ip_forward/d' /etc/sysctl.conf
echo "net.ipv4.ip_forward = 1" >> /etc/sysctl.conf
sysctl -p /etc/sysctl.conf > /dev/null

# Deteksi Interface Publik Utama (eth0, ens3, dll)
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

# Aturan NAT Masquerade agar traffic keluar dari tunnel bisa tembus
PostUp = iptables -A FORWARD -i wg0 -j ACCEPT; iptables -A FORWARD -o wg0 -j ACCEPT; iptables -t nat -A POSTROUTING -o ${PUB_IFACE} -j MASQUERADE
PostDown = iptables -D FORWARD -i wg0 -j ACCEPT; iptables -D FORWARD -o wg0 -j ACCEPT; iptables -t nat -D POSTROUTING -o ${PUB_IFACE} -j MASQUERADE
EOF

systemctl enable --now wg-quick@wg0 || systemctl restart wg-quick@wg0

echo -e "${GREEN}[4/5] Memasang Go Agent Daemon...${NC}"
mkdir -p /usr/local/bin

# Jika binary terkompilasi belum ada di hosting, pasang script runner sederhana atau download binary
cat <<'EOF' > /usr/local/bin/vpn-agent-runner.sh
#!/usr/bin/env bash
# Lightweight Heartbeat Sync Script jika binary Go belum dicompile
PANEL="$1"
TOKEN="$2"

while true; do
    MEM=$(free | awk '/Mem:/ {printf("%.2f"), $3/$2*100}')
    DISK=$(df -k / | awk 'NR==2 {print substr($5, 1, length($5)-1)}')

    # Kirim Heartbeat ke Panel
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

echo -e "${GREEN}"
echo "=========================================================="
echo " [SUKSES] Node VPS Berhasil Dikonfigurasi!                "
echo " WireGuard Server Public Key:                             "
echo " ${SERVER_PUB_KEY}                                        "
echo "=========================================================="
echo -e "${NC}"
