<?php

namespace App\Http\Controllers;

use App\Models\AllocatedPort;
use App\Models\Server;
use App\Models\User;
use App\Models\VpnAccount;
use App\Services\PortAllocationService;
use App\Services\WireguardKeyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected WireguardKeyService $keyService,
        protected PortAllocationService $portService
    ) {}

    /**
     * Dashboard Overview
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        // Jika user adalah admin, tampilkan semua akun, jika bukan tampilkan miliknya sendiri
        if ($user && $user->isAdmin()) {
            $vpnAccounts = VpnAccount::with(['server', 'allocatedPorts', 'user'])->latest()->get();
            $allocatedPorts = AllocatedPort::with(['server', 'vpnAccount'])->latest()->get();
        } else {
            $vpnAccounts = VpnAccount::where('user_id', $user?->id)
                ->with(['server', 'allocatedPorts', 'user'])
                ->latest()
                ->get();
            $allocatedPorts = AllocatedPort::whereHas('vpnAccount', function ($q) use ($user) {
                $q->where('user_id', $user?->id);
            })->with(['server', 'vpnAccount'])->latest()->get();
        }

        $servers = Server::with(['vpnAccounts', 'allocatedPorts'])->latest()->get();

        return view('dashboard.index', compact('servers', 'vpnAccounts', 'allocatedPorts', 'user'));
    }

    /**
     * Simpan Server Node Baru (Khusus Admin)
     */
    public function storeServer(Request $request): RedirectResponse
    {
        if (! Auth::user()?->isAdmin()) {
            abort(403, 'Hanya Administrator yang dapat menambahkan server node.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'ip_address' => 'required|ip|unique:servers,ip_address',
            'domain' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:100',
            'country_code' => 'required|string|size:2',
            'port_range_start' => 'required|integer|min:1024|max:65535',
            'port_range_end' => 'required|integer|gte:port_range_start|max:65535',
            'wireguard_port' => 'required|integer|min:1024|max:65535',
        ]);

        // Generate Server WireGuard Keypair
        $keys = $this->keyService->generateKeypair();

        $server = Server::create([
            'name' => $validated['name'],
            'ip_address' => $validated['ip_address'],
            'domain' => $validated['domain'] ?? null,
            'location' => $validated['location'] ?? null,
            'country_code' => strtoupper($validated['country_code']),
            'agent_token' => Str::random(64),
            'port_range_start' => $validated['port_range_start'],
            'port_range_end' => $validated['port_range_end'],
            'wireguard_port' => $validated['wireguard_port'],
            'wireguard_public_key' => $keys['public_key'],
            'wireguard_private_key' => $keys['private_key'],
            'vpn_subnet' => '10.8.0.0/24',
            'vpn_server_ip' => '10.8.0.1',
            'status' => 'offline',
        ]);

        return redirect()->route('dashboard')->with('success', "Server {$server->name} berhasil ditambahkan!");
    }

    /**
     * Buat Akun Tunnel & Alokasi Port Baru untuk Pterodactyl
     */
    public function storeAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'server_id' => 'required|exists:servers,id',
            'username' => 'required|string|max:64',
            'public_port' => 'nullable|integer|min:1024|max:65535',
            'target_port' => 'nullable|integer|min:1024|max:65535',
            'protocol' => 'required|in:both,tcp,udp',
            'label' => 'nullable|string|max:100',
            'days_active' => 'required|integer|min:1|max:365',
        ]);

        $server = Server::findOrFail($validated['server_id']);

        // Pastikan username unik di server ini
        if ($server->vpnAccounts()->where('username', $validated['username'])->exists()) {
            return back()->withErrors(['username' => 'Username ini sudah digunakan di server ini.'])->withInput();
        }

        // Ambil IP internal berikutnya
        $allocatedIp = $server->getNextAvailableVpnIp();
        if (! $allocatedIp) {
            return back()->withErrors(['server_id' => 'Kapasitas IP VPN di server ini sudah penuh.'])->withInput();
        }

        $user = Auth::user() ?? User::first();

        // Generate Client Keypair WireGuard
        $keys = $this->keyService->generateKeypair();

        $vpnAccount = VpnAccount::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'protocol' => 'wireguard',
            'username' => $validated['username'],
            'public_key' => $keys['public_key'],
            'private_key' => $keys['private_key'],
            'preshared_key' => $keys['preshared_key'],
            'allocated_ip' => $allocatedIp,
            'status' => 'active',
            'expires_at' => now()->addDays((int) $validated['days_active']),
        ]);

        // Alokasi Port Publik
        try {
            $allocatedPort = $this->portService->allocatePort(
                $vpnAccount,
                $validated['public_port'] ?? null,
                $validated['target_port'] ?? null,
                $validated['protocol'],
                $validated['label'] ?: 'Pterodactyl Game Server'
            );
        } catch (\Exception $e) {
            $vpnAccount->delete();

            return back()->withErrors(['public_port' => $e->getMessage()])->withInput();
        }

        return redirect()->route('dashboard')->with('success', "Akun VPN {$vpnAccount->username} & Port {$allocatedPort->public_port} berhasil dibuat untuk Pterodactyl!");
    }

    /**
     * Download WireGuard Config (.conf)
     */
    public function downloadConfig(VpnAccount $vpnAccount): StreamedResponse
    {
        // Proteksi kepemilikan
        if (Auth::check() && ! Auth::user()->isAdmin() && $vpnAccount->user_id !== Auth::id()) {
            abort(403, 'Anda tidak memiliki hak akses untuk mendownload konfigurasi ini.');
        }

        $configContent = $vpnAccount->generateWireguardConfig();
        $filename = "pterodactyl-{$vpnAccount->username}.conf";

        return response()->streamDownload(function () use ($configContent) {
            echo $configContent;
        }, $filename, [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * Hapus Akun VPN & Port yang terkait
     */
    public function destroyAccount(VpnAccount $vpnAccount): RedirectResponse
    {
        // Proteksi kepemilikan
        if (Auth::check() && ! Auth::user()->isAdmin() && $vpnAccount->user_id !== Auth::id()) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus akun ini.');
        }

        $name = $vpnAccount->username;
        $vpnAccount->delete();

        return redirect()->route('dashboard')->with('success', "Akun VPN {$name} dan port terkait berhasil dihapus.");
    }

    /**
     * Hapus Server Node (Khusus Admin)
     */
    public function destroyServer(Server $server): RedirectResponse
    {
        if (! Auth::user()?->isAdmin()) {
            abort(403, 'Hanya Administrator yang dapat menghapus server node.');
        }

        $name = $server->name;
        $server->delete();

        return redirect()->route('dashboard')->with('success', "Server {$name} berhasil dihapus.");
    }
}
