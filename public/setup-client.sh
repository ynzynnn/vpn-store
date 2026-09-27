#!/usr/bin/env bash
# ==============================================================================
# VPN PORT STORE — AUTO SETUP CLIENT WIREGUARD UNTUK PTERODACTYL WINGS
# ==============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}"
echo "=========================================================="
echo "    PTERODACTYL WINGS — WIREGUARD TUNNEL SETUP            "
echo "=========================================================="
echo -e "${NC}"

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}[ERROR] Jalankan script ini sebagai root (sudo -i).${NC}"
    exit 1
fi

echo -e "${GREEN}[1/3] Menginstall WireGuard dan resolvconf...${NC}"
apt-get update -qq
apt-get install -y -qq wireguard resolvconf curl

echo -e "${GREEN}[2/3] Mengkonfigurasi tunnel WireGuard...${NC}"
mkdir -p /etc/wireguard

if [ -f /etc/wireguard/wg0.conf ]; then
    echo -e "${BLUE}File /etc/wireguard/wg0.conf sudah ada. Mengaktifkan kembali...${NC}"
else
    echo -e "${BLUE}Silakan tempel (paste) isi file .conf WireGuard Anda dari dashboard:${NC}"
    read -p "Tekan ENTER untuk membuka nano editor..."
    nano /etc/wireguard/wg0.conf
fi

chmod 600 /etc/wireguard/wg0.conf

echo -e "${GREEN}[3/3] Mengaktifkan Tunnel WireGuard...${NC}"
systemctl enable --now wg-quick@wg0 || systemctl restart wg-quick@wg0

echo -e "${GREEN}"
echo "=========================================================="
echo " [SUKSES] WireGuard Tunnel Berhasil Diaktifkan!           "
echo " Mengetes Ping ke VPS Node (10.8.0.1)...                  "
echo "=========================================================="
echo -e "${NC}"

ping -c 3 10.8.0.1 || true

echo -e "${BLUE}Langkah Selanjutnya:${NC}"
echo "1. Buka Pterodactyl Admin Panel -> Nodes -> Allocation."
echo "2. Masukkan IP Publik VPS dan Port yang Anda sewa."
echo "3. Selamat bermain!"
