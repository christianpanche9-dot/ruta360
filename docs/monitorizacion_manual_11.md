# Monitorización, logs y mantenimiento — Manual 11

Trabajo real realizado en `ruta360_m8` / `ruta360_dev` (rama
`feature/monitorizacion`), sin tocar `ruta360-test`.

## Registro de la aplicación

`app/Utilidades/registro.php` añade `registrar(nivel, mensaje, contexto)`,
cargada desde `bootstrap.php` para toda la app. Escribe en
`storage/logs/app.log` con fecha, nivel, mensaje y contexto en JSON.
Nunca registra contraseñas, tokens ni datos personales. Conectada a un
caso real: `crearRuta()` en `public/api/rutas.php` registra `INFO` al
crear una ruta y `ERROR` (con contexto seguro, no la excepción cruda)
si falla.

## Localización real de logs (adaptado de las rutas de Windows del manual)

| Componente | Ubicación real | Nota |
|---|---|---|
| Apache (por vhost) | `curso_php/ruta360-test/shared/logs/ruta360-m9-*.log` | Específico de cada VirtualHost |
| PHP (global) | `/Applications/XAMPP/xamppfiles/logs/php_error_log` | **Hallazgo real**: es un único archivo para todo el XAMPP, no por proyecto — nunca lo habíamos revisado en los Manuales 9-10 |
| MySQL | `/Applications/XAMPP/xamppfiles/var/mysql/*.err` | 177 MB de avisos repetidos de `mysql_upgrade` (Manual 10), inofensivos pero candidatos a rotación |
| Ruta360 (propio) | `storage/logs/app.log` | Nuevo en este manual, vía `registrar()` |

## Endpoint de salud

`public/health.php`: abre su propia conexión a BD (no reutiliza
`config/conexion.php`, que falla duro con `exit()`), responde JSON con
`app`, `db`, `version` y código `200`/`503`. Probado en fallo real
(ver incidencia controlada) y en éxito.

## Hallazgo: permisos de escritura para el usuario real de Apache (`daemon`)

Apache/PHP corren como el usuario `daemon` (no `cristhianpanche`, no
`_www`). Cualquier carpeta que la app necesite escribir (`storage/cache`,
`storage/logs`) debe tener permiso de grupo para `daemon`, no solo para
el usuario que la creó a mano. Corregido con `chgrp daemon` + `chmod 775`
en ambas carpetas. `storage/cache` resultó ser código huérfano
(`cache_meteo.php`/`tiempo_resiliente.php`, no conectado a ningún
endpoint real — probablemente de la serie UF1846); `storage/logs` sí es
real y activo.

## Hallazgo: resolución de nombres `.local` en macOS

Los VirtualHost `.local` (`ruta360-m8.local`, `ruta360-m9-pruebas.local`)
añaden 2-5 segundos de latencia por la resolución mDNS/Bonjour de
macOS, aunque exista una entrada fija en `/etc/hosts`. El tiempo real
de la aplicación (confirmado con `curl --resolve`) es de milisegundos
(6-45 ms). Probablemente ha inflado el tiempo de todas las pruebas de
humo desde el Manual 9 sin que lo notáramos. Para medir tiempo real de
ahora en adelante: `curl --resolve <host>:<puerto>:127.0.0.1 ...`.

## Recursos (11.11-11.12)

Disco al 96 % (10 GB libres de 228 GB) — no urgente, del sistema
completo, no solo de este proyecto. 10 procesos Apache/MySQL activos.
`Threads_connected=1`, `Max_used_connections=2`, `max_connections=151`
— sin riesgo de saturación.

## Rotación de logs (11.13)

`scripts/rotar_logs.sh`, adaptado a bash (el manual trae PowerShell).
Probado de verdad: con el límite real (10 MB) no rotó; forzando un
límite artificial de 50 bytes, sí rotó, produciendo
`app-2026-09-22.log.gz` y vaciando `app.log`.

## Modo mantenimiento (11.14-11.15)

Ya construido y probado en el Manual 10, a nivel de Apache
(`mod_rewrite` + `ErrorDocument 503`, bandera en `shared/mantenimiento.flag`).
No se duplicó aquí.

## Incidencia controlada (11.16-11.17)

Se cambió `db.name` a un valor inexistente en `config/config.local.php`
(con copia de seguridad previa), en `ruta360_dev`, entorno aislado.

| Dato | Evidencia real |
|---|---|
| Síntoma | `health.php` → `503`, `{"db":"error"}`; `index.php` → `500` (falla duro, sin JSON, por diseño del Manual 6) |
| Causa | `db.name` apuntando a una base inexistente |
| Registro | `app.log`: `[ERROR] Health check: fallo de base de datos` |
| Resolución | Restaurar `config.local.php` desde la copia |
| Verificación | `health.php` y `index.php` vuelven a `200` |

Confirma que `health.php` degrada con elegancia y que una página normal
no lo hace — una diferencia real de comportamiento, no solo teórica.

## Automatización (11.18-11.19)

`scripts/check_ruta360.sh`, programado con `cron` (Mac no tiene
Programador de tareas de Windows) cada 15 minutos, probado dado de alta
y retirado tras la demostración, tal como pide el propio manual al
cerrar la práctica de aula. Registra `OK`/`ERROR` con código HTTP en
`storage/logs/monitor.log`.
