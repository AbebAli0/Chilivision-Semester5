@echo off
title Stop ChiliVision Server
set "PATH=C:\Users\62821\.config\herd-lite\bin;E:\xampp\php;E:\xampp\mysql\bin;%PATH%"
cd /d "%~dp0"
powershell -NoExit -ExecutionPolicy Bypass -File "%~dp0stop_server.ps1"
