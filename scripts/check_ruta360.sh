#!/bin/bash
# Comprobacion periodica de salud (Manual 11, 11.18-11.19).
cd "$(dirname "$0")/.."
URL="http://ruta360-m8.local:18083/health.php"
LOG="storage/logs/monitor.log"
RESPUESTA=$(curl -sS --resolve ruta360-m8.local:18083:127.0.0.1 -w "\n%{http_code}" -m 10 "$URL" 2>&1)
CODIGO=$(echo "$RESPUESTA" | tail -1)
if [ "$CODIGO" = "200" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') OK $CODIGO" >> "$LOG"
    exit 0
else
    echo "$(date '+%Y-%m-%d %H:%M:%S') ERROR $CODIGO" >> "$LOG"
    exit 1
fi
