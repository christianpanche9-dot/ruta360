# Pruebas — Manual 9 (Primer despliegue completo)

Pruebas de humo (9.15) ejecutadas contra el despliegue real en
`ruta360-m9-pruebas.local:18082`, con evidencia real de cada una.

| Prueba | Acción | Resultado esperado | Resultado obtenido |
|---|---|---|---|
| Resolución | `ping ruta360-m9-pruebas.local` | Resuelve a 127.0.0.1 | Cumplido — 0.0 % de pérdida |
| HTTP | `GET /` | HTTP 200 | Cumplido |
| Recursos | `GET /assets/css/estilos.css` | HTTP 200 | Cumplido |
| PHP | `GET /ver_ruta.php?id_ruta=1` | Sin errores mostrados | Cumplido — "Barcelona modernista", acentos intactos (España) |
| Base de datos | `GET /api/ciudades.php`, `GET /api/rutas.php` | Datos mínimos visibles | Cumplido — 5 ciudades, 1 ruta ("Barcelona modernista"), igual que Manual 7 |
| API propia | `GET /api/rutas.php` | JSON válido y estado correcto | Cumplido — `"ok": true`, estructura completa con ciudad anidada |
| API externa | `GET /tiempo.php?id_ciudad=1` | Respuesta o error controlado | Cumplido — API real de meteorología (Open-Meteo) respondió con datos reales: 23.5 °C, 14.2 km/h |
| Registros | Revisar logs tras las pruebas | Sin errores nuevos graves | Cumplido — `ruta360-m9-error.log`: 0 bytes; `ruta360-m9-access.log`: 811 bytes (peticiones registradas) |

## Nota sobre la prueba de la API externa

El primer intento real falló con "La ciudad seleccionada no es
válida." — no por un fallo del despliegue, sino porque la prueba se
lanzó con el parámetro equivocado (`id_ruta` en vez de `id_ciudad`,
el que realmente espera `tiempo.php`). Se leyó el código real del
archivo desplegado para confirmar el parámetro correcto antes de
repetir la prueba, en vez de asumir cuál sería. Repetida con
`id_ciudad=1`, la prueba fue satisfactoria.

## Verificación de versión exacta

```
cd releases/v0.8.0
git describe --tags --exact-match   → v0.8.0
git rev-parse HEAD                   → bc982807b06b30e6d7edf105718df792c976d581
git status                           → nothing to commit, working tree clean
```

Confirma que lo desplegado es exactamente el commit etiquetado como
`v0.8.0` en el Manual 8 — ni un commit antes ni uno después.
