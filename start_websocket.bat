@echo off
title HealthWing VetSystem WebSocket Hub (Port 8080)
echo ============================================================
echo Starting HealthWing VetSystem WebSocket Server on Port 8080
echo Keep this window open for instant WebSocket Video/Voice/Chat
echo ============================================================
php "%~dp0ws_server.php" 8080
pause
