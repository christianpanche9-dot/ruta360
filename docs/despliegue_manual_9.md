# Ficha de despliegue — Manual 9 (Primer despliegue completo)

Despliegue real de la etiqueta `v0.8.0` (Manual 8) en un entorno de
pruebas separado del de desarrollo, siguiendo la actividad guiada
9.1-9.19. Adaptado de las rutas Windows del manual a macOS/XAMPP real
(`robocopy` → `rsync`, `icacls` → `chmod`, `ipconfig /flushdns` →
`dscacheutil -flushcache`), y sin Composer (este proyecto no tiene
`composer.json`/`composer.lock` — confirmado, ver Manual 8).

| Campo | Contenido |
|---|---|
| Fecha y hora | 2026-09-18, sesión completa de despliegue |
| Entorno | Pruebas local (nunca "producción" — el aula no ofrece ni disponibilidad ni aislamiento suficientes) |
| Versión | Etiqueta `v0.8.0`, commit `bc98280` (verificado con `git describe --tags --exact-match` y `git rev-parse HEAD` tras el clon) |
| Origen | `https://github.com/christianpanche9-dot/ruta360.git` (repositorio real, Manual 8) |
| Destino | `curso_php/ruta360-test/` (`releases/v0.8.0`, `shared/{config,logs,uploads}`, `current`) |
| Base de datos | `ruta360_test` recreada desde cero; usuario `ruta360_app` con `SELECT, INSERT, UPDATE, DELETE` únicamente |
| Configuración | `shared/config/config.local.php`, fuera del repositorio; enlazado a `current/config/config.local.php` mediante symlink, nunca copiado |
| VirtualHost | `ruta360-m9-pruebas.local:18082` (ver incidencia 3 sobre por qué no se usó el nombre literal del manual) |
| Pruebas | 8/8 pruebas de humo superadas (9.15), con evidencia real — ver `docs/pruebas_manual_9.md` |
| Incidencias | 3 incidencias reales durante el despliegue, ninguna bloqueante tras corregirse — ver `docs/incidencia_manual_9.md` |
| Resultado final | **Aceptado** — cumple los 7 criterios de 9.17 |
| Responsable | Christian (alumno), con Claude ejecutando/documentando y el usuario operando su Terminal real para los pasos que requieren privilegios de sistema (`sudo`), MySQL y Apache |

## 9.17 Criterio para aceptar el despliegue — verificación

| Criterio | Cumplido | Evidencia |
|---|---|---|
| `git describe` identifica v0.8.0 en la carpeta release | Sí | `releases/v0.8.0`: `git describe --tags --exact-match` → `v0.8.0`; `git rev-parse HEAD` → `bc982807b06b30e6d7edf105718df792c976d581` |
| Apache sirve `current/public` mediante el dominio de pruebas | Sí | `ServerName ruta360-m9-pruebas.local`, puerto `18082`, `DocumentRoot .../current/public`; `ping` y `curl` confirmados |
| Las credenciales no aparecen en Git ni dentro de `public` | Sí | `shared/config/config.local.php` vive fuera de cualquier `releases/vX` y fuera del repositorio; `current/config/config.local.php` es un symlink, no una copia |
| La aplicación conecta con `ruta360_test` usando `ruta360_app` | Sí | `api/ciudades.php` y `api/rutas.php` devuelven datos reales de `ruta360_test` |
| Las páginas esenciales y la API propia responden correctamente | Sí | Portada, `ver_ruta.php`, `api/ciudades.php`, `api/rutas.php`: todo HTTP 200 con contenido correcto |
| Los logs no contienen errores nuevos que impidan el uso | Sí | `shared/logs/ruta360-m9-error.log`: 0 bytes tras las 8 pruebas; `shared/logs/ruta360-m9-access.log`: 811 bytes (peticiones reales registradas) |
| La ficha de despliegue contiene versión, fecha, pruebas y resultado | Sí | Este documento |
