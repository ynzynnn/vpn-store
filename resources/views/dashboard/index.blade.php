<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — VPN Port Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen" x-data="{ currentTab: 'ports', showNewAccountModal: false, showNewServerModal: false }">

    <!-- Topbar Polos -->
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="{{ route('home') }}" class="font-bold text-base text-gray-900 tracking-tight">
                    VPN Port Store
                </a>
                <nav class="hidden sm:flex items-center gap-4 text-xs font-medium">
                    <button @click="currentTab = 'ports'" :class="currentTab === 'ports' ? 'text-gray-900 border-b-2 border-gray-900 py-4 font-bold' : 'text-gray-500 hover:text-gray-800 py-4'">
                        Port Tersewa ({{ $allocatedPorts->count() }})
                    </button>
                    <button @click="currentTab = 'servers'" :class="currentTab === 'servers' ? 'text-gray-900 border-b-2 border-gray-900 py-4 font-bold' : 'text-gray-500 hover:text-gray-800 py-4'">
                        Node VPS ({{ $servers->count() }})
                    </button>
                    <button @click="currentTab = 'tutorial'" :class="currentTab === 'tutorial' ? 'text-gray-900 border-b-2 border-gray-900 py-4 font-bold' : 'text-gray-500 hover:text-gray-800 py-4'">
                        Panduan Pterodactyl
                    </button>
                </nav>
            </div>

            <div class="flex items-center gap-3 text-xs">
                <div class="text-right hidden sm:block">
                    <div class="font-semibold text-gray-900">{{ Auth::user()->name }}</div>
                    <div class="text-gray-500 text-[11px]">{{ Auth::user()->email }} ({{ strtoupper(Auth::user()->role) }})</div>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 border border-gray-300 hover:bg-gray-100 rounded text-gray-700 font-medium transition">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Sub-Nav Mobile -->
    <div class="sm:hidden bg-white border-b border-gray-200 px-4 flex gap-4 text-xs">
        <button @click="currentTab = 'ports'" :class="currentTab === 'ports' ? 'text-gray-900 border-b-2 border-gray-900 py-2 font-bold' : 'text-gray-500 py-2'">
            Port
        </button>
        <button @click="currentTab = 'servers'" :class="currentTab === 'servers' ? 'text-gray-900 border-b-2 border-gray-900 py-2 font-bold' : 'text-gray-500 py-2'">
            Node
        </button>
        <button @click="currentTab = 'tutorial'" :class="currentTab === 'tutorial' ? 'text-gray-900 border-b-2 border-gray-900 py-2 font-bold' : 'text-gray-500 py-2'">
            Panduan
        </button>
    </div>

    <!-- Main Container Polos -->
    <main class="max-w-6xl mx-auto px-4 py-6">

        <!-- Flash Message Alerts -->
        @if(session('success'))
        <div class="mb-4 p-3 rounded bg-green-50 border border-green-200 text-green-800 text-xs flex justify-between items-center">
            <span>{{ session('success') }}</span>
            <button onclick="this.parentElement.remove()" class="text-green-600 font-bold">&times;</button>
        </div>
        @endif

        @if($errors->any())
        <div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-800 text-xs">
            <span class="font-bold">Terjadi Kesalahan:</span>
            <ul class="list-disc list-inside mt-1">
                @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- TAB 1: PORTS TERSEWA -->
        <div x-show="currentTab === 'ports'" class="space-y-4">
            <div class="flex justify-between items-center bg-white p-4 border border-gray-200 rounded">
                <div>
                    <h2 class="text-sm font-bold text-gray-900">Port Pterodactyl Tersewa</h2>
                    <p class="text-xs text-gray-500">Port publik ini terhubung ke IP WireGuard server lokal Anda.</p>
                </div>
                <button @click="showNewAccountModal = true" class="px-3 py-1.5 bg-gray-900 hover:bg-gray-800 text-white rounded text-xs font-medium transition">
                    + Sewa Port Baru
                </button>
            </div>

            <div class="bg-white border border-gray-200 rounded overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 font-semibold">
                        <tr>
                            <th class="p-3">Node VPS</th>
                            <th class="p-3">Label / Akun</th>
                            <th class="p-3">Port Publik (VPS)</th>
                            <th class="p-3">Tujuan (IP Tunnel)</th>
                            <th class="p-3">Protokol</th>
                            <th class="p-3">Masa Aktif</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-800">
                        @forelse($vpnAccounts as $acc)
                            @foreach($acc->allocatedPorts as $port)
                            <tr class="hover:bg-gray-50">
                                <td class="p-3">
                                    <div class="font-medium text-gray-900">{{ $acc->server->name }}</div>
                                    <div class="text-[11px] font-mono text-gray-500">{{ $acc->server->ip_address }}</div>
                                </td>
                                <td class="p-3">
                                    <div class="font-medium text-gray-900">{{ $port->label ?: 'Game Server' }}</div>
                                    <div class="text-[11px] text-gray-500">User: {{ $acc->username }}</div>
                                </td>
                                <td class="p-3">
                                    <span class="font-mono font-bold text-gray-900 bg-gray-100 px-2 py-0.5 rounded border border-gray-200">
                                        :{{ $port->public_port }}
                                    </span>
                                </td>
                                <td class="p-3 font-mono text-gray-600">
                                    {{ $acc->allocated_ip }}:{{ $port->target_port }}
                                </td>
                                <td class="p-3 uppercase text-[11px] text-gray-600">
                                    {{ $port->protocol }}
                                </td>
                                <td class="p-3 text-[11px] text-gray-600">
                                    {{ $acc->expires_at ? $acc->expires_at->format('d/m/Y') : 'Selamanya' }}
                                </td>
                                <td class="p-3 text-right space-x-1">
                                    <a href="{{ route('accounts.download', $acc->id) }}" class="inline-block px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded font-medium text-[11px] border border-gray-300">
                                        Unduh .conf
                                    </a>
                                    <form action="{{ route('accounts.destroy', $acc->id) }}" method="POST" class="inline" onsubmit="return confirm('Hapus akun dan port ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2 py-1 text-red-600 hover:bg-red-50 rounded text-[11px]">
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        @empty
                        <tr>
                            <td colspan="7" class="p-6 text-center text-gray-500">
                                Belum ada port yang disewa. Klik tombol "+ Sewa Port Baru" di atas.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: SERVERS -->
        <div x-show="currentTab === 'servers'" class="space-y-4">
            <div class="flex justify-between items-center bg-white p-4 border border-gray-200 rounded">
                <div>
                    <h2 class="text-sm font-bold text-gray-900">Daftar Node VPS</h2>
                    <p class="text-xs text-gray-500">Server yang memiliki IP Publik dedicated untuk port forwarding.</p>
                </div>
                @if(Auth::user()->isAdmin())
                <button @click="showNewServerModal = true" class="px-3 py-1.5 bg-gray-900 hover:bg-gray-800 text-white rounded text-xs font-medium transition">
                    + Tambah Node VPS
                </button>
                @endif
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                @forelse($servers as $server)
                <div class="bg-white border border-gray-200 rounded p-4 text-xs space-y-3">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-bold text-sm text-gray-900">{{ $server->name }}</div>
                            <div class="text-gray-500">{{ $server->location ?? 'Global' }} ({{ $server->country_code }})</div>
                        </div>
                        <span class="px-2 py-0.5 rounded font-medium {{ $server->status === 'online' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ strtoupper($server->status) }}
                        </span>
                    </div>

                    <div class="font-mono text-gray-600 bg-gray-50 p-2.5 rounded border border-gray-100 space-y-1">
                        <div>IP Publik: <span class="font-bold text-gray-900">{{ $server->ip_address }}</span></div>
                        <div>Range Port: {{ $server->port_range_start }} - {{ $server->port_range_end }}</div>
                        <div>Port Terpakai: {{ $server->allocated_ports_count }} port</div>
                        <div>Subnet Tunnel: {{ $server->vpn_subnet }}</div>
                    </div>

                    @if(Auth::user()->isAdmin())
                    <div class="space-y-1.5">
                        <label class="block text-gray-700 font-medium text-[11px]">Cara Install di VPS Node ini:</label>
                        <div class="text-[10px] text-gray-500">Opsi 1 (Menu Installer Interaktif - Pilih nomor 2):</div>
                        <input readonly type="text" value="curl -fsSL {{ url('/install.sh') }} | bash" class="w-full bg-gray-50 border border-gray-300 rounded px-2 py-1 font-mono text-[11px] text-gray-800 select-all">
                        <div class="text-[10px] text-gray-500">Opsi 2 (Langsung 1-Klik Otomatis):</div>
                        <input readonly type="text" value="curl -fsSL {{ url('/install-node.sh') }} | bash -s -- --panel={{ url('/') }} --token={{ $server->agent_token }}" class="w-full bg-gray-50 border border-gray-300 rounded px-2 py-1 font-mono text-[11px] text-gray-800 select-all">
                    </div>

                    <div class="pt-2 border-t border-gray-100 flex justify-end">
                        <form action="{{ route('servers.destroy', $server->id) }}" method="POST" onsubmit="return confirm('Hapus node server ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline text-[11px]">
                                Hapus Node
                            </button>
                        </form>
                    </div>
                    @endif
                </div>
                @empty
                <div class="col-span-full p-6 text-center text-sm text-gray-500 bg-white border border-gray-200 rounded">
                    Belum ada node VPS yang terdaftar.
                </div>
                @endforelse
            </div>
        </div>

        <!-- TAB 3: TUTORIAL PTERODACTYL -->
        <div x-show="currentTab === 'tutorial'" class="bg-white border border-gray-200 rounded p-6 max-w-3xl mx-auto space-y-6 text-xs text-gray-700">
            <div>
                <h2 class="text-base font-bold text-gray-900 mb-1">Panduan Singkat Pasang ke Pterodactyl Wings</h2>
                <p class="text-gray-500">Ikuti 3 langkah berikut setelah menyewa port dari panel ini.</p>
            </div>

            <div class="space-y-4">
                <div class="p-4 bg-gray-50 border border-gray-200 rounded">
                    <h3 class="font-bold text-gray-900 text-sm mb-1">Langkah 1: Unduh File Konfigurasi</h3>
                    <p>Klik tombol <strong>"Unduh .conf"</strong> pada baris port yang telah Anda sewa di tab <strong>Port Tersewa</strong>.</p>
                </div>

                <div class="p-4 bg-gray-50 border border-gray-200 rounded space-y-2">
                    <h3 class="font-bold text-gray-900 text-sm mb-1">Langkah 2: Pasang di Server Pterodactyl Wings Anda</h3>
                    <p>Jalankan perintah berikut di terminal server Pterodactyl Wings (Ubuntu / Debian):</p>
                    <div class="bg-gray-900 text-gray-100 p-3 rounded font-mono text-[11px] space-y-1">
                        <div>sudo apt update && sudo apt install wireguard resolvconf -y</div>
                        <div class="text-gray-400"># Masukkan isi file .conf ke /etc/wireguard/wg0.conf</div>
                        <div>sudo nano /etc/wireguard/wg0.conf</div>
                        <div class="text-gray-400"># Aktifkan tunnel WireGuard</div>
                        <div>sudo systemctl enable --now wg-quick@wg0</div>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 border border-gray-200 rounded space-y-2">
                    <h3 class="font-bold text-gray-900 text-sm mb-1">Langkah 3: Tambahkan Allocation di Panel Pterodactyl</h3>
                    <ol class="list-decimal list-inside space-y-1">
                        <li>Buka admin panel Pterodactyl Anda &rarr; <strong>Nodes</strong> &rarr; pilih Node server Anda.</li>
                        <li>Buka tab <strong>Allocation</strong>.</li>
                        <li>Isi <strong>IP Address</strong> dengan <strong>IP Publik VPS Node</strong> yang Anda sewa.</li>
                        <li>Isi <strong>Ports</strong> dengan <strong>Port Publik</strong> yang Anda sewa (misal 25565).</li>
                        <li>Klik <strong>Submit</strong>. Port siap digunakan untuk server game!</li>
                    </ol>
                </div>
            </div>
        </div>

    </main>

    <!-- MODAL SEWA PORT BARU POLOS -->
    <div x-show="showNewAccountModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" style="display: none;">
        <div class="bg-white border border-gray-300 rounded-lg max-w-md w-full p-6 shadow-lg" @click.away="showNewAccountModal = false">
            <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-900">Sewa Port Pterodactyl Baru</h3>
                <button @click="showNewAccountModal = false" class="text-gray-400 hover:text-gray-700 text-lg">&times;</button>
            </div>

            <form action="{{ route('accounts.store') }}" method="POST" class="space-y-3 text-xs">
                @csrf
                <div>
                    <label class="block font-medium text-gray-700 mb-1">Pilih Node VPS</label>
                    <select name="server_id" required class="w-full border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-500">
                        @foreach($servers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->ip_address }} - {{ $s->location }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block font-medium text-gray-700 mb-1">Nama Akun / Server</label>
                    <input type="text" name="username" placeholder="contoh: server-smp-01" required class="w-full border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-500">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Port Publik (VPS)</label>
                        <input type="number" name="public_port" placeholder="cth: 25565" class="w-full border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-500">
                        <span class="text-[10px] text-gray-400">Kosongkan untuk acak</span>
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Target Port (Lokal)</label>
                        <input type="number" name="target_port" placeholder="cth: 25565" class="w-full border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-500">
                        <span class="text-[10px] text-gray-400">Default sama</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Protokol</label>
                        <select name="protocol" class="w-full border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-500">
                            <option value="both">BOTH (TCP & UDP)</option>
                            <option value="tcp">TCP</option>
                            <option value="udp">UDP</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Durasi</label>
                        <select name="days_active" class="w-full border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-500">
                            <option value="30">30 Hari</option>
                            <option value="60">60 Hari</option>
                            <option value="90">90 Hari</option>
                            <option value="365">365 Hari</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-medium text-gray-700 mb-1">Label Catatan (Opsional)</label>
                    <input type="text" name="label" placeholder="Contoh: Server Minecraft Survival" class="w-full border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-500">
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-gray-100">
                    <button type="button" @click="showNewAccountModal = false" class="px-3 py-1.5 border border-gray-300 rounded hover:bg-gray-50 text-gray-700">Batal</button>
                    <button type="submit" class="px-4 py-1.5 bg-gray-900 hover:bg-gray-800 text-white rounded font-medium">Buat & Sewa</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL TAMBAH SERVER NODE POLOS -->
    <div x-show="showNewServerModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" style="display: none;">
        <div class="bg-white border border-gray-300 rounded-lg max-w-md w-full p-6 shadow-lg" @click.away="showNewServerModal = false">
            <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-900">Tambah Node VPS Baru</h3>
                <button @click="showNewServerModal = false" class="text-gray-400 hover:text-gray-700 text-lg">&times;</button>
            </div>

            <form action="{{ route('servers.store') }}" method="POST" class="space-y-3 text-xs">
                @csrf
                <div>
                    <label class="block font-medium text-gray-700 mb-1">Nama Node</label>
                    <input type="text" name="name" placeholder="cth: SG-Gaming-01" required class="w-full border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-500">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">IP Publik VPS</label>
                        <input type="text" name="ip_address" placeholder="103.xxx.xxx.xxx" required class="w-full border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-500">
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Domain (Opsional)</label>
                        <input type="text" name="domain" placeholder="sg1.domain.com" class="w-full border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Lokasi</label>
                        <input type="text" name="location" placeholder="Singapore / Jakarta" class="w-full border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-500">
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Kode Negara</label>
                        <input type="text" name="country_code" placeholder="SG" maxlength="2" required value="SG" class="w-full border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-500 uppercase">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Port WireGuard</label>
                        <input type="number" name="wireguard_port" value="51820" required class="w-full border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-500">
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Port Awal</label>
                        <input type="number" name="port_range_start" value="20000" required class="w-full border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-500">
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Port Akhir</label>
                        <input type="number" name="port_range_end" value="35000" required class="w-full border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none focus:border-gray-500">
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-gray-100">
                    <button type="button" @click="showNewServerModal = false" class="px-3 py-1.5 border border-gray-300 rounded hover:bg-gray-50 text-gray-700">Batal</button>
                    <button type="submit" class="px-4 py-1.5 bg-gray-900 hover:bg-gray-800 text-white rounded font-medium">Simpan Node</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
