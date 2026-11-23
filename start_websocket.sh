#!/usr/bin/env bash
echo "============================================================"
echo "Starting HealthWing VetSystem WebSocket Server on Port 8080"
echo "============================================================"
php "$(dirname "$0")/ws_server.php" 8080
