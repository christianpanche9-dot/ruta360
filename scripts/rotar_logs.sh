#!/bin/bash
# Rotacion de storage/logs/app.log (Manual 11, 11.13).
# Semanal o al superar el limite de tamano; nunca registra secretos
# porque registrar() ya los excluye desde el origen.
ARCHIVO="storage/logs/app.log"
LIMITE="${1:-10485760}"

if [ -f "$ARCHIVO" ] && [ "$(stat -f%z "$ARCHIVO")" -ge "$LIMITE" ]; then
    FECHA=$(date +%Y-%m-%d)
    DESTINO="storage/logs/app-$FECHA.log"
    cp "$ARCHIVO" "$DESTINO"
    : > "$ARCHIVO"
    gzip -f "$DESTINO"
    find storage/logs -name "app-*.log.gz" -mtime +28 -delete
    echo "Rotado: $DESTINO.gz"
else
    echo "Sin rotar: $ARCHIVO no alcanza el limite ($LIMITE bytes)"
fi
