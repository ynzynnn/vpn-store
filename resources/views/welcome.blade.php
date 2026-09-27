<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN Store IP Public — Port Forwarding Pterodactyl</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; }
    </style>
</head>
<body class="bg-white text-gray-900 min-h-screen flex flex-col justify-between">

    <!-- Header / Navbar Polos -->
    <header class="border-b border-gray-200 bg-white">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="font-bold text-lg text-gray-900 tracking-tight">
                VPN Port Store
            </a>

            <div class="flex items-center gap-4 text-sm">
                @auth
                    <span class="text-gray-500 text-xs hidden sm:inline">Halo, {{ Auth::user()->name }}</span>
                    <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white rounded text-xs font-medium transition">
                        Buka Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-900 text-xs font-medium">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}" class="px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white rounded text-xs font-medium transition">
                        Daftar Akun
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Main Content Polos -->
    <main class="flex-grow">
        <!-- Hero Section -->
        <section class="max-w-4xl mx-auto px-4 py-16 text-center">
            <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 mb-4">
                Sewa Port IP Public untuk Server Pterodactyl
            </h1>
            <p class="text-base text-gray-600 max-w-2xl mx-auto mb-8 leading-relaxed">
                Hubungkan server Pterodactyl Wings (PC rumahan / VPS NAT) via WireGuard Tunnel dan dapatkan port publik dedicated agar server game Anda bisa diakses dari internet.
            </p>

            <div class="flex justify-center gap-3 mb-12">
                @auth
                    <a href="{{ route('dashboard') }}" class="px-6 py-2.5 bg-gray-900 hover:bg-gray-800 text-white font-medium text-sm rounded transition">
                        Masuk ke Dashboard
                    </a>
                @else
                    <a href="{{ route('register') }}" class="px-6 py-2.5 bg-gray-900 hover:bg-gray-800 text-white font-medium text-sm rounded transition">
                        Mulai Daftar & Sewa
                    </a>
                    <a href="{{ route('login') }}" class="px-6 py-2.5 border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium text-sm rounded transition">
                        Masuk ke Akun
                    </a>
                @endauth
            </div>

            <!-- Ringkasan Angka Polos -->
            <div class="grid grid-cols-3 gap-4 max-w-lg mx-auto border border-gray-200 rounded p-4 text-center bg-gray-50">
                <div>
                    <div class="text-2xl font-bold text-gray-900">{{ $totalServers }}</div>
                    <div class="text-xs text-gray-500">Node VPS</div>
                </div>
                <div class="border-x border-gray-200">
                    <div class="text-2xl font-bold text-gray-900">{{ $totalActivePorts }}</div>
                    <div class="text-xs text-gray-500">Port Tersewa</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900">{{ $totalAccounts }}</div>
                    <div class="text-xs text-gray-500">Akun Tunnel</div>
                </div>
            </div>
        </section>

        <!-- Daftar Node Server Polos -->
        <section class="max-w-4xl mx-auto px-4 py-8 border-t border-gray-200">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Lokasi Node VPS Tersedia</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                @forelse($servers as $server)
                <div class="p-4 border border-gray-200 rounded bg-white">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="font-bold text-sm text-gray-900">{{ $server->name }}</h3>
                            <div class="text-xs text-gray-500">{{ $server->location ?? 'Global' }} ({{ $server->country_code }})</div>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded font-medium {{ $server->status === 'online' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ strtoupper($server->status) }}
                        </span>
                    </div>
                    <div class="text-xs font-mono text-gray-600 space-y-1 pt-2 border-t border-gray-100">
                        <div>IP Publik: <span class="font-bold text-gray-900">{{ $server->ip_address }}</span></div>
                        <div>Range Port: {{ $server->port_range_start }} - {{ $server->port_range_end }}</div>
                        <div>Port Terpakai: {{ $server->allocated_ports_count }} port</div>
                    </div>
                </div>
                @empty
                <div class="col-span-full p-6 text-center text-sm text-gray-500 border border-gray-200 rounded">
                    Belum ada server node yang ditambahkan.
                </div>
                @endforelse
            </div>
        </section>

        <!-- Cara Kerja 3 Langkah Polos -->
        <section class="max-w-4xl mx-auto px-4 py-8 border-t border-gray-200 mb-12">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Cara Menghubungkan ke Pterodactyl</h2>
            <div class="grid sm:grid-cols-3 gap-4 text-xs text-gray-600">
                <div class="p-4 border border-gray-200 rounded bg-gray-50">
                    <div class="font-bold text-gray-900 text-sm mb-1">1. Sewa Port</div>
                    <p>Daftar akun, pilih server node, dan pilih port publik yang diinginkan (contoh: 25565).</p>
                </div>
                <div class="p-4 border border-gray-200 rounded bg-gray-50">
                    <div class="font-bold text-gray-900 text-sm mb-1">2. Pasang WireGuard</div>
                    <p>Download file .conf dari dashboard, pasang di server Wings lokal Anda, dan aktifkan tunnel.</p>
                </div>
                <div class="p-4 border border-gray-200 rounded bg-gray-50">
                    <div class="font-bold text-gray-900 text-sm mb-1">3. Atur Allocation</div>
                    <p>Di panel Pterodactyl, buat Allocation baru menggunakan IP Publik VPS dan port yang Anda sewa.</p>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer Polos -->
    <footer class="border-t border-gray-200 py-6 text-center text-xs text-gray-500">
        <p>&copy; {{ date('Y') }} VPN Port Store. Tampilan sederhana & cepat.</p>
    </footer>

</body>
</html>
