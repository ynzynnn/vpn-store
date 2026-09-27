# VPN Store IP Public — Port Forwarding untuk Pterodactyl

Web Panel untuk membuat dan mengelola penyewaan **Port IP Publik** berbasis **WireGuard Tunnel**. Dirancang khusus untuk server game / **Pterodactyl Wings** yang berjalan di PC rumahan atau VPS IP Private (NAT) agar dapat diakses dari internet.

---

## ⚡ Cara Cepat Install (Auto Installer All-in-One)

Di server VPS Linux (Ubuntu 20.04+ / Debian 11+), jalankan perintah:

```bash
bash install.sh
```

Akan muncul menu pilihan:
```text
==========================================================
    AUTO INSTALLER VPN PORT STORE (ALL-IN-ONE)
==========================================================
Silakan pilih komponen yang ingin diinstall di VPS ini:
  [1] Install Web Panel (Nginx + PHP 8.3 + Database + Dashboard)
  [2] Install Node VPS  (WireGuard + iptables Port Forwarding + Daemon Agent)
  [0] Batal / Keluar
```

- **Pilih `1`**: Untuk VPS yang akan dijadikan **Master Web Panel** (otomatis install Nginx, PHP 8.3, Composer, Database SQLite, App Key, Virtualhost, serta opsi **Auto SSL Gratis Let's Encrypt / HTTPS** jika menggunakan domain).
- **Pilih `2`**: Untuk VPS yang akan dijadikan **Node Port Forwarding** (otomatis install WireGuard, aktifkan `net.ipv4.ip_forward`, iptables NAT, dan memasang daemon agent service).

---

### 2. Di Komputer Windows (Lokal / Laragon)
Cukup jalankan file PowerShell script berikut:

```powershell
.\install-windows.ps1
```

Aplikasi otomatis siap dan server lokal langsung aktif di:
- **Web**: `http://127.0.0.1:8000`
- **Dashboard**: `http://127.0.0.1:8000/dashboard`

---

## 🔑 Kredensial Login Default
- **Email**: `admin@vpnstore.com`
- **Password**: `password123`

*(Segera ganti kata sandi atau buat akun baru di halaman registrasi)*

---

## 🌐 Cara Menghubungkan VPS Node (Tempat Port Forwarding)

1. Buka dashboard di tab **Node VPS**.
2. Klik **+ Tambah Node VPS Baru** dan masukkan IP Publik VPS tersebut.
3. Salin perintah instalasi agent yang muncul di kartu node, contoh:
   ```bash
   curl -fsSL http://IP-PANEL/install-node.sh | bash -s -- --panel=http://IP-PANEL --token=TOKEN_NODE
   ```
4. Jalankan perintah tersebut di VPS Node Anda. Node akan otomatis online dan siap meneruskan traffic port publik ke server Pterodactyl pengguna!

---

## 🎮 Cara Menghubungkan ke Pterodactyl Wings

1. Sewa port di menu dashboard (contoh port `25565`).
2. Klik tombol **Unduh .conf** pada baris port yang disewa.
3. Di server Pterodactyl Wings Anda, install WireGuard dan masukkan file konfigurasi ke `/etc/wireguard/wg0.conf`:
   ```bash
   sudo apt update && sudo apt install wireguard resolvconf -y
   sudo nano /etc/wireguard/wg0.conf   # Paste isi .conf di sini
   sudo systemctl enable --now wg-quick@wg0
   ```
4. Di **Admin Panel Pterodactyl** &rarr; **Nodes** &rarr; **Allocation**:
   - Masukkan **IP Publik VPS Node**.
   - Masukkan **Port Publik** yang Anda sewa.
5. Selesai! Server game Anda sekarang bisa dimasuki semua orang dari internet.
