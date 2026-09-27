<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun — VPN Port Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
        <div class="mb-6 text-center">
            <h1 class="text-xl font-bold text-gray-900">Buat Akun Baru</h1>
            <p class="text-xs text-gray-500 mt-1">Daftar untuk mulai menyewa port publik Pterodactyl</p>
        </div>

        @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded">
            {{ $errors->first() }}
        </div>
        @endif

        <form action="{{ route('register') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="name" class="block text-xs font-medium text-gray-700 mb-1">Nama Lengkap</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                    class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:border-gray-500">
            </div>

            <div>
                <label for="email" class="block text-xs font-medium text-gray-700 mb-1">Alamat Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required
                    class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:border-gray-500">
            </div>

            <div>
                <label for="password" class="block text-xs font-medium text-gray-700 mb-1">Kata Sandi</label>
                <input type="password" id="password" name="password" required
                    class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:border-gray-500">
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-medium text-gray-700 mb-1">Ulangi Kata Sandi</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                    class="w-full px-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:border-gray-500">
            </div>

            <button type="submit"
                class="w-full bg-gray-900 hover:bg-gray-800 text-white font-medium py-2 rounded text-sm transition">
                Daftar Akun
            </button>
        </form>

        <div class="mt-6 pt-4 border-t border-gray-100 text-center text-xs text-gray-500 space-y-2">
            <p>Sudah punya akun? <a href="{{ route('login') }}" class="text-blue-600 hover:underline font-medium">Masuk di sini</a></p>
            <p><a href="{{ route('home') }}" class="text-gray-400 hover:text-gray-600">&larr; Kembali ke Beranda</a></p>
        </div>
    </div>

</body>
</html>
