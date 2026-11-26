# Unified Launcher ChiliVision:
# 1) FastAPI Warm Inference Worker (Port 8001 - opsional, fallback ke CLI jika tidak tersedia)
# 2) Telegram Bot (@chili_vision_bot)
# 3) Laravel Background Scheduler (schedule:work)
# 4) Laravel Web & MySQL API Server (Port 8000)
$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ProjectRoot

# 1. Jalankan FastAPI Warm Inference Worker di Port 8001 jika belum berjalan
$port8001 = Get-NetTCPConnection -LocalPort 8001 -State Listen -ErrorAction SilentlyContinue
if (-not $port8001) {
    $hasUvicorn = python -c "import uvicorn" 2>$null
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Menjalankan FastAPI Warm Inference Worker (Port 8001) ..." -ForegroundColor Cyan
        Start-Process -WindowStyle Hidden -FilePath "python" -ArgumentList "-m", "uvicorn", "src.fastapi_app:app", "--host", "127.0.0.1", "--port", "8001"
    } else {
        Write-Host "[Info] Uvicorn tidak ditemukan; inferensi memakai Python CLI fallback." -ForegroundColor Gray
    }
} else {
    Write-Host "FastAPI Warm Inference Worker (Port 8001) sudah aktif." -ForegroundColor Yellow
}

# 2. Jalankan Telegram Bot (@chili_vision_bot) di background jika belum aktif
$tgProc = Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match "telegram_bot\.py" }
if (-not $tgProc) {
    Write-Host "Menjalankan Telegram Bot (@chili_vision_bot) ..." -ForegroundColor Magenta
    Start-Process -NoNewWindow -FilePath "python" -ArgumentList "src\telegram_bot.py"
} else {
    Write-Host "Telegram Bot (@chili_vision_bot) sudah aktif berjalan." -ForegroundColor Yellow
}

# 3. Jalankan Laravel Background Scheduler (schedule:work) di background jika belum aktif
$schedProc = Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match "artisan\s+schedule:work" }
if (-not $schedProc) {
    Write-Host "Menjalankan Laravel Background Scheduler (schedule:work) ..." -ForegroundColor Cyan
    Start-Process -WindowStyle Hidden -FilePath "php" -ArgumentList "artisan", "schedule:work"
} else {
    Write-Host "Laravel Background Scheduler sudah aktif berjalan." -ForegroundColor Yellow
}

# 4. Jalankan Cloudflare Tunnel ke Port 8000 (Named Tunnel domain tetap dari .env, atau Quick Tunnel fallback)
$cfTunnelToken = ""
$cfPublicUrl = ""
$envFilePath = Join-Path $ProjectRoot ".env"
if (Test-Path $envFilePath) {
    foreach ($line in (Get-Content $envFilePath -ErrorAction SilentlyContinue)) {
        if ($line -match '^\s*CLOUDFLARED_TUNNEL_TOKEN\s*=\s*(.+?)\s*$') {
            $cfTunnelToken = $Matches[1].Trim('"', "'")
        } elseif ($line -match '^\s*CLOUDFLARED_PUBLIC_URL\s*=\s*(.+?)\s*$') {
            $cfPublicUrl = $Matches[1].Trim('"', "'").TrimEnd('/')
        }
    }
}

$cfExe = (Get-Command cloudflared -ErrorAction SilentlyContinue).Source
if (-not $cfExe) {
    $cfExe = @("C:\Program Files (x86)\cloudflared\cloudflared.exe", "C:\Program Files\cloudflared\cloudflared.exe") | Where-Object { Test-Path $_ } | Select-Object -First 1
}
$cfLog = Join-Path $ProjectRoot "storage\logs\cloudflared.log"
$cfUrlFile = Join-Path $ProjectRoot "storage\logs\cloudflare_url.txt"
if ($cfExe) {
    $cfProc = Get-CimInstance Win32_Process | Where-Object {
        $_.Name -eq "cloudflared.exe" -and ($_.CommandLine -match "127\.0\.0\.1:8000" -or $_.CommandLine -match "run --token")
    }
    if (-not $cfProc) {
        if (Test-Path $cfLog) { Remove-Item -Force $cfLog -ErrorAction SilentlyContinue }
        if (Test-Path $cfUrlFile) { Remove-Item -Force $cfUrlFile -ErrorAction SilentlyContinue }

        if ($cfTunnelToken) {
            $displayUrl = if ($cfPublicUrl) { $cfPublicUrl } else { "https://chilivision.me" }
            Write-Host "Menjalankan Cloudflare Named Tunnel ($displayUrl) ..." -ForegroundColor Cyan
            Start-Process -WindowStyle Hidden -FilePath $cfExe -ArgumentList "tunnel", "--no-autoupdate", "run", "--token", $cfTunnelToken -RedirectStandardError $cfLog
            $registered = $false
            for ($i = 0; $i -lt 20; $i++) {
                Start-Sleep -Milliseconds 500
                if (Test-Path $cfLog) {
                    if (Select-String -Path $cfLog -Pattern "Registered tunnel connection" -Quiet -ErrorAction SilentlyContinue) {
                        $registered = $true
                        break
                    }
                }
            }
            Set-Content -Path $cfUrlFile -Value $displayUrl -Encoding UTF8
            Write-Host "============================================================" -ForegroundColor Green
            Write-Host " Domain Tetap (Buka di HP/Browser): $displayUrl" -ForegroundColor Green
            Write-Host " URL API ESP32 (secrets.h):         $displayUrl/api" -ForegroundColor Green
            if (-not $registered) {
                Write-Host " [Info] Tunnel sedang menyambung; cek storage\logs\cloudflared.log bila belum online." -ForegroundColor Yellow
            }
            Write-Host "============================================================" -ForegroundColor Green
        } else {
            Write-Host "Menjalankan Cloudflare Quick Tunnel (HTTPS publik ke port 8000) ..." -ForegroundColor Cyan
            Start-Process -WindowStyle Hidden -FilePath $cfExe -ArgumentList "tunnel", "--url", "http://127.0.0.1:8000", "--no-autoupdate" -RedirectStandardError $cfLog
            $cfUrl = $null
            for ($i = 0; $i -lt 30; $i++) {
                Start-Sleep -Milliseconds 500
                if (Test-Path $cfLog) {
                    $match = Select-String -Path $cfLog -Pattern "https://[a-z0-9-]+\.trycloudflare\.com" -AllMatches -ErrorAction SilentlyContinue | Select-Object -First 1
                    if ($match) {
                        $cfUrl = $match.Matches[0].Value
                        break
                    }
                }
            }
            if ($cfUrl) {
                Set-Content -Path $cfUrlFile -Value $cfUrl -Encoding UTF8
                Write-Host "============================================================" -ForegroundColor Green
                Write-Host " URL Online (Buka di HP/Browser): $cfUrl" -ForegroundColor Green
                Write-Host " URL API ESP32 (secrets.h):       $cfUrl/api" -ForegroundColor Green
                Write-Host "============================================================" -ForegroundColor Green
            } else {
                Write-Host "[Info] URL Cloudflare belum muncul di log; cek storage\logs\cloudflared.log beberapa detik lagi." -ForegroundColor Yellow
            }
        }
    } else {
        Write-Host "Cloudflare Tunnel sudah aktif berjalan." -ForegroundColor Yellow
        if (Test-Path $cfUrlFile) {
            $savedUrl = (Get-Content $cfUrlFile -Raw).Trim()
            if ($savedUrl) {
                Write-Host " URL Online Aktif: $savedUrl (API ESP32: $savedUrl/api)" -ForegroundColor Green
            }
        }
    }
} else {
    Write-Host "[Info] cloudflared tidak ditemukan; server hanya berjalan secara lokal." -ForegroundColor Gray
}

# 5. Restart proses lama di port 8000 / worker 8101-8106 lalu jalankan Apache Multi-Worker + PHP Pool
$port8000 = Get-NetTCPConnection -LocalPort 8000,8101,8102,8103,8104,8105,8106 -State Listen -ErrorAction SilentlyContinue
if ($port8000) {
    Write-Host "Menghentikan proses lama di port 8000 / PHP worker agar dapat dijalankan bersih..." -ForegroundColor Yellow
    $port8000 | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }
    Start-Sleep -Seconds 1
}
Get-CimInstance Win32_Process | Where-Object {
    ($_.Name -eq "php.exe" -and ($_.CommandLine -match "artisan serve" -or $_.CommandLine -match "127\.0\.0\.1:810[1-6]")) -or
    ($_.Name -eq "httpd.exe" -and $_.CommandLine -match [regex]::Escape("storage\apache\httpd.conf"))
} | ForEach-Object {
    Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
}

$apacheExe = "E:\xampp\apache\bin\httpd.exe"
$phpCmd = (Get-Command php -ErrorAction SilentlyContinue).Source
$laravelRouter = Join-Path $ProjectRoot "vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php"
if ((Test-Path $apacheExe) -and $phpCmd -and (Test-Path $laravelRouter)) {
    $apacheDir = Join-Path $ProjectRoot "storage\apache"
    New-Item -ItemType Directory -Force -Path $apacheDir | Out-Null
    $publicPath = (Join-Path $ProjectRoot "public") -replace '\\', '/'
    $apacheConfPath = Join-Path $apacheDir "httpd.conf"
    $apacheErrorLog = (Join-Path $ProjectRoot "storage\logs\apache-error.log") -replace '\\', '/'
    $apachePid = (Join-Path $apacheDir "httpd.pid") -replace '\\', '/'

    $workerPorts = 8101..8106
    $workerProcs = foreach ($wPort in $workerPorts) {
        Start-Process -WindowStyle Hidden -FilePath $phpCmd -WorkingDirectory (Join-Path $ProjectRoot "public") -ArgumentList "-S", "127.0.0.1:$wPort", "..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php" -PassThru
    }

    $balancerMembers = ($workerPorts | ForEach-Object { "    BalancerMember http://127.0.0.1:$_ disablereuse=On retry=0 timeout=60" }) -join "`r`n"

    $httpdConf = @"
ServerRoot "E:/xampp/apache"
Listen 0.0.0.0:8000
ServerName 127.0.0.1:8000
PidFile "$apachePid"
ErrorLog "$apacheErrorLog"
LogLevel warn

LoadModule access_compat_module modules/mod_access_compat.so
LoadModule alias_module modules/mod_alias.so
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule deflate_module modules/mod_deflate.so
LoadModule env_module modules/mod_env.so
LoadModule filter_module modules/mod_filter.so
LoadModule headers_module modules/mod_headers.so
LoadModule lbmethod_byrequests_module modules/mod_lbmethod_byrequests.so
LoadModule mime_module modules/mod_mime.so
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_balancer_module modules/mod_proxy_balancer.so
LoadModule proxy_http_module modules/mod_proxy_http.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule setenvif_module modules/mod_setenvif.so
LoadModule slotmem_shm_module modules/mod_slotmem_shm.so

<IfModule mpm_winnt_module>
    ThreadsPerChild 64
    MaxConnectionsPerChild 0
</IfModule>

<IfModule mime_module>
    TypesConfig conf/mime.types
</IfModule>

DocumentRoot "$publicPath"
<Directory "$publicPath">
    Options FollowSymLinks
    AllowOverride None
    Require all granted
</Directory>

ProxyPreserveHost On
<Proxy "balancer://phpcluster">
$balancerMembers
    ProxySet lbmethod=byrequests
</Proxy>

ProxyPass /storage !
ProxyPass /images !
ProxyPass /models/ !
ProxyPass /build !
ProxyPass /favicon.ico !
ProxyPass /robots.txt !
ProxyPass /models balancer://phpcluster/api/models
ProxyPass / balancer://phpcluster/
ProxyPassReverse / balancer://phpcluster/

<IfModule deflate_module>
    AddOutputFilterByType DEFLATE text/html text/plain text/css text/javascript application/javascript application/json image/svg+xml
</IfModule>

KeepAlive On
MaxKeepAliveRequests 200
KeepAliveTimeout 5
"@
    Set-Content -Path $apacheConfPath -Value $httpdConf -Encoding ASCII
    Write-Host "Menjalankan Apache Load Balancer (6 Worker PHP 8.4 + Gzip) + TiDB Cloud di http://127.0.0.1:8000 ..." -ForegroundColor Green
    try {
        & $apacheExe -f $apacheConfPath
    } finally {
        $workerProcs | ForEach-Object { Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue }
    }
} else {
    Write-Host "Menjalankan Laravel Web, IoT, Gemini AI & TiDB Cloud API Server di http://127.0.0.1:8000 ..." -ForegroundColor Green
    php artisan serve --host=0.0.0.0 --port=8000
}
