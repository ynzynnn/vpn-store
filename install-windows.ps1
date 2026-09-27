# ==============================================================================
# AUTO INSTALLER & RUNNER UNTUK WINDOWS (LARAGON / PHP)
# ==============================================================================

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "    AUTO INSTALLER WEB PANEL VPN PORT STORE (WINDOWS)     " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

# 1. Deteksi PHP & Composer
$phpPath = "C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64"
$composerPath = "C:\laragon\bin\composer"
$gitPath = "C:\laragon\bin\git\bin"

if (Test-Path $phpPath) {
    $env:Path = "$phpPath;$composerPath;$gitPath;$env:Path"
    Write-Host "[OK] Menggunakan PHP 8.3 & Composer dari Laragon." -ForegroundColor Green
} else {
    Write-Host "[INFO] Memeriksa ketersediaan PHP di sistem..." -ForegroundColor Yellow
}

# 2. File .env
if (-not (Test-Path ".env")) {
    if (Test-Path ".env.example") {
        Copy-Item ".env.example" ".env"
        Write-Host "[OK] File .env berhasil dibuat dari .env.example." -ForegroundColor Green
    }
}

# 3. Database SQLite
if (-not (Test-Path "database\database.sqlite")) {
    New-Item -ItemType File -Path "database\database.sqlite" -Force | Out-Null
    Write-Host "[OK] File database\database.sqlite berhasil dibuat." -ForegroundColor Green
}

# 4. Generate App Key & Migrasi
Write-Host "[...] Menjalankan migrasi database dan seeder data awal..." -ForegroundColor Yellow
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "    INSTALASI SELESAI! MENJALANKAN SERVER LOKAL...        " -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "Akses Web Panel : http://127.0.0.1:8000" -ForegroundColor Cyan
Write-Host "Dashboard       : http://127.0.0.1:8000/dashboard" -ForegroundColor Cyan
Write-Host "Email Admin     : admin@vpnstore.com" -ForegroundColor Cyan
Write-Host "Password Admin  : password123" -ForegroundColor Cyan
Write-Host ""
Write-Host "Tekan CTRL + C untuk menghentikan server." -ForegroundColor Yellow
Write-Host ""

php artisan serve
