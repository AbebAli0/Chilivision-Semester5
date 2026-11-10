@echo off
title Stop ChiliVision Telegram Bot
set "PATH=C:\Users\62821\.config\herd-lite\bin;E:\xampp\php;E:\xampp\mysql\bin;%PATH%"
cd /d "%~dp0"
powershell -NoExit -ExecutionPolicy Bypass -Command "$procs = Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match 'telegram_bot\.py' }; if ($procs) { $procs | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }; Write-Host '[OK] Telegram Bot (@chili_vision_bot) berhasil dihentikan.' -ForegroundColor Green } else { Write-Host '[Info] Telegram Bot sedang tidak berjalan.' -ForegroundColor Yellow }"
