# Script untuk menghentikan seluruh layanan ChiliVision (Laravel, FastAPI, Scheduler, Telegram Bot)
Write-Host "Menghentikan seluruh layanan ChiliVision..." -ForegroundColor Yellow

# 1. Hentikan Laravel Server / Apache (Port 8000) & PHP Worker Pool (8101-8106)
$port8000 = Get-NetTCPConnection -LocalPort 8000,8101,8102,8103,8104,8105,8106 -State Listen -ErrorAction SilentlyContinue
if ($port8000) {
    $port8000 | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }
    Write-Host "[OK] Server Laravel/Apache & PHP Worker Pool dihentikan." -ForegroundColor Green
} else {
    Write-Host "[Info] Server Laravel/Apache (Port 8000) sedang tidak berjalan." -ForegroundColor Gray
}
$apacheProcs = Get-CimInstance Win32_Process | Where-Object {
    ($_.Name -eq "httpd.exe" -and $_.CommandLine -match "storage\\apache\\httpd\.conf") -or
    ($_.Name -eq "php.exe" -and $_.CommandLine -match "127\.0\.0\.1:810[1-6]")
}
if ($apacheProcs) {
    $apacheProcs | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
}

# 2. Hentikan FastAPI Inference Worker di Port 8001
$port8001 = Get-NetTCPConnection -LocalPort 8001 -State Listen -ErrorAction SilentlyContinue
if ($port8001) {
    $port8001 | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }
    Write-Host "[OK] FastAPI Inference Worker (Port 8001) dihentikan." -ForegroundColor Green
} else {
    Write-Host "[Info] FastAPI Inference Worker sedang tidak berjalan." -ForegroundColor Gray
}

# 3. Hentikan Laravel Background Scheduler (schedule:work)
$schedProc = Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match "artisan\s+schedule:work" }
if ($schedProc) {
    $schedProc | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    Write-Host "[OK] Laravel Background Scheduler dihentikan." -ForegroundColor Green
} else {
    Write-Host "[Info] Laravel Background Scheduler sedang tidak berjalan." -ForegroundColor Gray
}

# 4. Hentikan Telegram Bot (@chili_vision_bot)
$tgProc = Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match "telegram_bot\.py" }
if ($tgProc) {
    $tgProc | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    Write-Host "[OK] Telegram Bot (@chili_vision_bot) dihentikan." -ForegroundColor Green
} else {
    Write-Host "[Info] Telegram Bot sedang tidak berjalan." -ForegroundColor Gray
}

# 5. Hentikan Cloudflare Tunnel
$cfProc = Get-CimInstance Win32_Process | Where-Object {
    $_.Name -eq "cloudflared.exe" -and ($_.CommandLine -match "127\.0\.0\.1:8000" -or $_.CommandLine -match "run --token")
}
if ($cfProc) {
    $cfProc | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    Write-Host "[OK] Cloudflare Tunnel dihentikan." -ForegroundColor Green
} else {
    Write-Host "[Info] Cloudflare Tunnel sedang tidak berjalan." -ForegroundColor Gray
}

Write-Host "Selesai! Semua layanan telah dihentikan." -ForegroundColor Cyan
