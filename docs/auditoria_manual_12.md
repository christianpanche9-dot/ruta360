# Seguridad, documentación y auditoría final — Manual 12

Auditoría real de `ruta360-test` tras desplegar `v1.0.0` (rama `main` de
`ruta360_m8`, commit `159c2f9`, incorpora el Manual 11 completo).

## Ficha de la instalación auditada

- Aplicación: Ruta360
- Entorno: `ruta360-test` (pruebas), `ruta360-m9-pruebas.local:18082`
- Versión activa: `v1.0.0`
- Estructura: `current` / `releases/{v0.8.0,v0.9.0,v1.0.0}` / `shared` / `backups`
- PHP 8.2.4 · Apache 2.4.56 · MySQL (XAMPP local, macOS)
- Base de datos: `ruta360_test` · usuario de aplicación: `ruta360_app`
- Responsable: Cristhian (desarrollo y pruebas en local)

## Despliegue de v1.0.0

El trabajo del Manual 11 (registro, `health.php`, rotación, automatización)
vivía solo en el repositorio de desarrollo, nunca desplegado. Antes de
auditar se etiquetó `v1.0.0` sobre el commit real y se desplegó siguiendo
el mismo procedimiento del Manual 10: clonar el tag en `releases/`,
construir `current-next` (con `config.local.php` y `storage/logs`
symlinked a `shared/`, siguiendo el mismo patrón), modo mantenimiento
activo durante el swap, y pruebas de humo tras el cambio. `.git` se
eliminó de la copia desplegable antes del swap — nunca debe publicarse.

## Superficie publicada y exposición HTTP (12.4-12.5)

Todos los recursos privados probados por HTTP devuelven `404`:
`config/config.local.php`, `.git/config`, `shared/logs/*`, `backups`.
Solo `public/` es alcanzable, tal como exige el manual.

## Cabeceras, errores y configuración PHP (12.6)

| Revisión | Antes | Después |
|---|---|---|
| `expose_php` | `On` (cabecera `X-Powered-By: PHP/8.2.4` visible) | `Off`, cabecera ausente |
| `display_errors` | `Off` | Sin cambios, correcto |
| Cookie de sesión | Sin `HttpOnly` ni `SameSite` | `HttpOnly; SameSite=Lax` |

`session.cookie_secure` se deja desactivado a propósito: este entorno
es HTTP puro, sin TLS: activarlo rompería las sesiones.

## Secretos (12.7-12.9)

`config.local.php` no está versionado (confirmado con `git ls-files`).
Búsqueda de patrones de contraseñas/tokens en el histórico: sin
coincidencias reales — solo nombres de variables, hashes, plantillas
`CAMBIAR` y un token de prueba (`demo-local-token`) del doble de
transporte. Ningún secreto real ha entrado nunca al repositorio.

## Permisos y privilegio mínimo (12.10-12.12)

| Ruta | Antes | Después |
|---|---|---|
| `shared/uploads` | `777`, grupo `admin` | `775`, grupo `daemon` |
| `backups/` | `755` | `750` |
| `backups/*.sql` | `644` (legible por cualquiera) | `640` |

Privilegios reales del usuario de aplicación (`SHOW GRANTS`):
`ruta360_app` solo tiene `SELECT, INSERT, UPDATE, DELETE` sobre
`ruta360_test` y `ruta360_dev` — sin `GRANT`, `CREATE USER` ni `DROP`.
Cumple el principio de mínimo privilegio exigido en 12.11.

Ruta360 no tiene subida de archivos (`grep` de `$_FILES` en `app/` y
`public/` sin resultados) — 12.12 no aplica.

## Simulacro de recuperación (12.13-12.15)

1. Backup real de `ruta360_test` (`backups/ruta360_test_simulacro_recuperacion.sql`).
2. Restauración en una base aislada (`ruta360_recuperacion_temp`), con
   privilegio concedido a `ruta360_app` solo para la duración del simulacro.
3. Copia de código de `releases/v1.0.0` servida con el servidor embebido
   de PHP (`php -S`), sin tocar Apache ni el entorno real.
4. `health.php` -> `{"app":"ok","db":"ok","version":"1.0.0"}`; portada y
   listado de rutas -> `200`.
5. Recuento exacto (`COUNT(*)`, no la estimación de `information_schema`)
   idéntico entre original y restaurada: `ciudades=5`, `puntos_interes=3`,
   `rutas=1`.
6. Limpieza completa: servidor detenido, base temporal eliminada,
   privilegio revocado, copia de código borrada.

**Hallazgo metodológico**: `information_schema.tables.table_rows` marcó
`rutas: 0` justo tras restaurar, cuando en realidad había 1 fila real
(confirmado por la API y por `COUNT(*)`) — es una estimación de InnoDB,
no un recuento exacto. Para verificar una copia siempre hay que usar
`COUNT(*)` real, nunca esa columna.

RTO observado (manual, sin automatizar): ~14 minutos desde el backup
hasta la verificación funcional completa.

## Registro de hallazgos

| ID | Hallazgo | Prioridad | Estado |
|---|---|---|---|
| H-01 | `VERSION` nunca se actualizaba en cada despliegue (v0.8.0 y v0.9.0 reportaban un valor fijo) | Media | Coincide por primera vez en v1.0.0; el "bump" sigue siendo manual, no automatizado |
| H-02 | `expose_php=On` exponía la versión exacta de PHP | Media | Corregido y verificado |
| H-03 | Cookie de sesión sin `HttpOnly` ni `SameSite` | Alta | Corregido y verificado |
| H-04 | `shared/uploads` en `777` | Alta | Corregido y verificado |
| H-05 | Backups legibles por cualquier usuario del sistema | Media | Corregido y verificado |
| H-06 | `root` de MySQL sin contraseña | Baja | Aceptado (máquina de desarrollo local, sin acceso remoto) |

## Prueba final integrada

Entorno, versión y responsable identificados; `health.php` y pruebas
funcionales verdes contra `v1.0.0` real; recursos privados sin exposición
HTTP; sin secretos en el repositorio; privilegios de base de datos
mínimos confirmados; copia de seguridad restaurada y verificada con
datos reales; modo mantenimiento probado durante el swap (Manual 10);
logs sin errores atribuibles al despliegue.

## Acta de aceptación

| Campo | Contenido |
|---|---|
| Proyecto | Ruta360 |
| Entorno auditado | `ruta360-test` |
| Versión y commit | `v1.0.0` / `159c2f9` |
| Pruebas superadas | Exposición HTTP, secretos, privilegios BD, simulacro de recuperación, prueba final integrada |
| Hallazgos abiertos | H-01 (Media, proceso), H-06 (Baja, aceptado) |
| Riesgos aceptados | `root` de MySQL sin contraseña, en máquina de desarrollo local sin acceso remoto |
| Decisión | **Aceptada** — no quedan hallazgos críticos ni altos abiertos, y la recuperación quedó verificada con evidencia real |

## Notas honestas y pendientes

- El "bump" del archivo `VERSION` en cada release sigue siendo manual;
  sería fácil de olvidar en un despliegue futuro (H-01 queda abierto).
- `ruta360_entrega_7.zip` (Manual 7) sigue sin verificar.
- La fecha de retirada de `current-anterior-v0.8.0` (Manual 10) sigue sin definir;
  ahora además queda `current-anterior-v0.9.0` del despliegue de este manual.
- El simulacro de recuperación se hizo manualmente con el servidor embebido
  de PHP; no se ha probado un procedimiento de recuperación totalmente
  automatizado ni contra una copia de `shared/uploads` (la app no la usa).
