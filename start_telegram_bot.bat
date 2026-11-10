@echo off
title ChiliVision Telegram Bot (@chili_vision_bot)
set "PATH=C:\Users\62821\.config\herd-lite\bin;E:\xampp\php;E:\xampp\mysql\bin;%PATH%"
cd /d "%~dp0"
powershell -NoExit -ExecutionPolicy Bypass -Command "Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match 'telegram_bot\.py' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }; Write-Host 'Menjalankan Telegram Bot (@chili_vision_bot) di Terminal...' -ForegroundColor Cyan; python src\telegram_bot.py"
