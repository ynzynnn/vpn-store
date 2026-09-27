package main

import (
	"bytes"
	"encoding/json"
	"flag"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/exec"
	"runtime"
	"strconv"
	"strings"
	"time"
)

type HeartbeatPayload struct {
	CPUUsage    float64            `json:"cpu_usage"`
	MemoryUsage float64            `json:"memory_usage"`
	DiskUsage   float64            `json:"disk_usage"`
	ActivePeers int                `json:"active_peers"`
	Traffic     []PeerTrafficDelta `json:"traffic"`
}

type PeerTrafficDelta struct {
	PublicKey string `json:"public_key"`
	BytesRx   int64  `json:"bytes_rx"`
	BytesTx   int64  `json:"bytes_tx"`
}

type HeartbeatResponse struct {
	Status  string    `json:"status"`
	Message string    `json:"message"`
	Sync    SyncState `json:"sync"`
}

type SyncState struct {
	ServerID       uint64          `json:"server_id"`
	VPNSubnet      string          `json:"vpn_subnet"`
	VPNServerIP    string          `json:"vpn_server_ip"`
	WireGuardPort  int             `json:"wireguard_port"`
	PeersToAdd     []PeerItem      `json:"peers_to_add"`
	PeersToRemove  []string        `json:"peers_to_remove"`
	PortsToForward []PortForward   `json:"ports_to_forward"`
	PortsToRemove  []PortForward   `json:"ports_to_remove"`
}

type PeerItem struct {
	PublicKey    string `json:"public_key"`
	PresharedKey string `json:"preshared_key"`
	AllowedIP    string `json:"allowed_ip"`
	Username     string `json:"username"`
}

type PortForward struct {
	PublicPort int    `json:"public_port"`
	TargetIP   string `json:"target_ip"`
	TargetPort int    `json:"target_port"`
	Protocol   string `json:"protocol"` // "both", "tcp", "udp"
}

var lastCounters = make(map[string][2]int64)

func getSystemMetrics() (float64, float64, float64) {
	// Di sistem Linux, metrik diambil dari /proc atau command sederhana
	var cpu, mem, disk float64 = 5.0, 25.0, 15.0

	if runtime.GOOS == "linux" {
		// Dapatkan persentase memory
		outMem, err := exec.Command("sh", "-c", "free | awk '/Mem:/ {printf(\"%.2f\"), $3/$2*100}'").Output()
		if err == nil {
			if v, parseErr := strconv.ParseFloat(strings.TrimSpace(string(outMem)), 64); parseErr == nil {
				mem = v
			}
		}

		// Dapatkan persentase disk /
		outDisk, err := exec.Command("sh", "-c", "df -k / | awk 'NR==2 {print substr($5, 1, length($5)-1)}'").Output()
		if err == nil {
			if v, parseErr := strconv.ParseFloat(strings.TrimSpace(string(outDisk)), 64); parseErr == nil {
				disk = v
			}
		}
	}

	return cpu, mem, disk
}

func readWireguardDeltas() ([]PeerTrafficDelta, int) {
	var deltas []PeerTrafficDelta
	activePeers := 0

	out, err := exec.Command("wg", "show", "wg0", "dump").Output()
	if err != nil {
		return deltas, 0
	}

	lines := strings.Split(strings.TrimSpace(string(out)), "\n")
	if len(lines) <= 1 {
		return deltas, 0
	}

	// Baris pertama adalah konfigurasi interface, baris berikutnya adalah peers
	for _, line := range lines[1:] {
		fields := strings.Split(line, "\t")
		if len(fields) < 8 {
			continue
		}

		pubKey := fields[0]
		rx, _ := strconv.ParseInt(fields[5], 10, 64)
		tx, _ := strconv.ParseInt(fields[6], 10, 64)

		activePeers++

		last, exists := lastCounters[pubKey]
		var deltaRx, deltaTx int64
		if exists {
			if rx >= last[0] {
				deltaRx = rx - last[0]
			}
			if tx >= last[1] {
				deltaTx = tx - last[1]
			}
		}
		lastCounters[pubKey] = [2]int64{rx, tx}

		if deltaRx > 0 || deltaTx > 0 {
			deltas = append(deltas, PeerTrafficDelta{
				PublicKey: pubKey,
				BytesRx:   deltaRx,
				BytesTx:   deltaTx,
			})
		}
	}

	return deltas, activePeers
}

func applySyncState(sync SyncState) {
	// 1. Terapkan Peer WireGuard yang Baru / Aktif
	for _, p := range sync.PeersToAdd {
		args := []string{"set", "wg0", "peer", p.PublicKey, "allowed-ips", p.AllowedIP}
		if p.PresharedKey != "" {
			// Simpan preshared key temporer
			tmpFile := fmt.Sprintf("/tmp/psk_%s.key", p.PublicKey[:8])
			_ = os.WriteFile(tmpFile, []byte(p.PresharedKey), 0600)
			args = append(args, "preshared-key", tmpFile)
		}
		_ = exec.Command("wg", args...).Run()
	}

	// 2. Cabut Peer yang Expired / Suspended
	for _, pubKey := range sync.PeersToRemove {
		_ = exec.Command("wg", "set", "wg0", "peer", pubKey, "remove").Run()
		delete(lastCounters, pubKey)
	}

	// 3. Terapkan Port Forwarding iptables untuk Pterodactyl
	for _, pf := range sync.PortsToForward {
		applyIptablesRule(pf, true)
	}

	// 4. Copot Port Forwarding iptables yang tidak aktif
	for _, pf := range sync.PortsToRemove {
		applyIptablesRule(pf, false)
	}
}

func applyIptablesRule(pf PortForward, isAdd bool) {
	op := "-A"
	if !isAdd {
		op = "-D"
	}

	protocols := []string{"tcp", "udp"}
	if pf.Protocol == "tcp" {
		protocols = []string{"tcp"}
	} else if pf.Protocol == "udp" {
		protocols = []string{"udp"}
	}

	for _, proto := range protocols {
		// PREROUTING DNAT: Forward traffic dari port publik VPS ke IP Tunnel Pterodactyl
		dnatArgs := []string{
			"-t", "nat", op, "PREROUTING",
			"-p", proto, "--dport", strconv.Itoa(pf.PublicPort),
			"-j", "DNAT", "--to-destination", fmt.Sprintf("%s:%d", pf.TargetIP, pf.TargetPort),
		}
		_ = exec.Command("iptables", dnatArgs...).Run()

		// FORWARD: Izinkan traffic menuju IP Tunnel
		fwdArgs := []string{
			op, "FORWARD",
			"-p", proto, "-d", pf.TargetIP, "--dport", strconv.Itoa(pf.TargetPort),
			"-j", "ACCEPT",
		}
		_ = exec.Command("iptables", fwdArgs...).Run()
	}
}

func main() {
	panelURL := flag.String("panel", "", "URL Web Panel Laravel (contoh: https://vpnstore.com)")
	token := flag.String("token", "", "Token Sanctum Agent dari Web Panel")
	interval := flag.Int("interval", 15, "Interval heartbeat dalam detik")
	flag.Parse()

	if *panelURL == "" || *token == "" {
		// Cek dari Environment Variable jika flag tidak ada
		if envPanel := os.Getenv("PANEL_URL"); envPanel != "" {
			*panelURL = envPanel
		}
		if envToken := os.Getenv("AGENT_TOKEN"); envToken != "" {
			*token = envToken
		}
	}

	if *panelURL == "" || *token == "" {
		log.Fatalf("Usage: vpn-agent --panel=https://panel.domain.com --token=YOUR_AGENT_TOKEN")
	}

	*panelURL = strings.TrimRight(*panelURL, "/")
	endpoint := fmt.Sprintf("%s/api/v1/agent/heartbeat", *panelURL)

	log.Printf("[VPN-AGENT] Memulai Daemon VPS Node...")
	log.Printf("[VPN-AGENT] Panel URL: %s", *panelURL)
	log.Printf("[VPN-AGENT] Heartbeat interval: %d detik", *interval)

	client := &http.Client{Timeout: 10 * time.Second}
	ticker := time.NewTicker(time.Duration(*interval) * time.Second)
	defer ticker.Stop()

	// Jalankan sekali saat startup
	doHeartbeat(client, endpoint, *token)

	for range ticker.C {
		doHeartbeat(client, endpoint, *token)
	}
}

func doHeartbeat(client *http.Client, endpoint string, token string) {
	cpu, mem, disk := getSystemMetrics()
	deltas, activePeers := readWireguardDeltas()

	payload := HeartbeatPayload{
		CPUUsage:    cpu,
		MemoryUsage: mem,
		DiskUsage:   disk,
		ActivePeers: activePeers,
		Traffic:     deltas,
	}

	bodyBytes, err := json.Marshal(payload)
	if err != nil {
		log.Printf("[VPN-AGENT] Gagal serialize JSON payload: %v", err)
		return
	}

	req, err := http.NewRequest("POST", endpoint, bytes.NewBuffer(bodyBytes))
	if err != nil {
		log.Printf("[VPN-AGENT] Gagal membuat HTTP request: %v", err)
		return
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Agent-Token", token)
	req.Header.Set("User-Agent", "VPNAgent-Go/1.0")

	resp, err := client.Do(req)
	if err != nil {
		log.Printf("[VPN-AGENT] Gagal menghubungi panel: %v", err)
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		log.Printf("[VPN-AGENT] Panel merespon dengan status code %d", resp.StatusCode)
		return
	}

	var res HeartbeatResponse
	if err := json.NewDecoder(resp.Body).Decode(&res); err != nil {
		log.Printf("[VPN-AGENT] Gagal parse respon JSON: %v", err)
		return
	}

	// Terapkan sinkronisasi WireGuard dan iptables
	applySyncState(res.Sync)
	log.Printf("[VPN-AGENT] Heartbeat sukses. %d peers & %d port forwardings aktif.",
		len(res.Sync.PeersToAdd), len(res.Sync.PortsToForward))
}
