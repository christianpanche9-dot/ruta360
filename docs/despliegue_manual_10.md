# Ficha de despliegue — Manual 10 (Actualización y rollback)

Actualización real de `ruta360-test` de `v0.8.0` a `v0.9.0` (columna
opcional `categoria` en `rutas`, con migración reversible), con
desarrollo y pruebas completamente aislados en un entorno nuevo
(`ruta360-dev`) antes de tocar el entorno de pruebas compartido, tal
como exige el manual.

| Campo | Contenido |
|---|---|
| Fecha | 2026-09-21 |
| Entorno de desarrollo/pruebas | `ruta360_m8` + base `ruta360_dev` + VirtualHost `ruta360-m8.local:18083`, aislado de `ruta360-test` en todo momento hasta el despliegue |
| Entorno de despliegue | `ruta360-test` (`ruta360-m9-pruebas.local:18082`), el mismo del Manual 9 |
| Versión anterior | `v0.8.0`, conservada en `current-anterior-v0.8.0` |
| Versión nueva | `v0.9.0`, commit `440aa3bff370e30ebaaa12351481b2d2b5df6c72` |
| Migración | `sql/migraciones/2026-09-21_01_anadir_categoria_a_rutas.{up,down}.sql`, probada apply+revert en `ruta360_dev` antes del despliegue |
| Backup pre-migración | `backups/ruta360_test_pre_v0.9.0.sql`, verificado restaurándolo en una base temporal y comparando conteos de filas |
| Modo mantenimiento | Nuevo en este manual: a nivel de Apache (`mod_rewrite` + `ErrorDocument 503`), con bandera en `shared/mantenimiento.flag`, fuera de cualquier release — probado activando y desactivando antes de usarlo de verdad |
| Swap | `current` → `current-anterior-v0.8.0`; `current-next` → `current` (dos `mv`, sin symlink porque `current` ya se manejaba como carpeta real desde el Manual 9) |
| Pruebas | 4/4 en la migración (dev) + 8/8 en el despliegue (test) — ver `docs/pruebas_manual_10.md` |
| Incidencias | 6 incidencias reales, ninguna bloqueante tras corregirse — ver `docs/incidencia_manual_10.md` |
| Resultado final | **Aceptado** — cumple los criterios de abajo, confirmado por el usuario |
| Responsable | Christian (alumno), con Claude ejecutando diagnósticos/documentación y el usuario operando su propia terminal para cada comando real (git, MySQL, Apache) |

## Criterio de aceptación — verificación

| Criterio | Cumplido | Evidencia |
|---|---|---|
| `git describe` identifica v0.9.0 en `releases/v0.9.0` | Sí | `v0.9.0`, commit `440aa3bff370e30ebaaa12351481b2d2b5df6c72` |
| Migración aplicada sin pérdida de datos | Sí | `rutas`: 1 fila antes y después; `categoria = NULL` en la fila existente |
| Backup pre-migración verificado (restaurable de verdad) | Sí | Conteos idénticos entre `ruta360_test` y la restauración de prueba en una base temporal |
| `current` sirve el código nuevo tras el swap | Sí | `grep categoria public/api/rutas.php` → 19 coincidencias en el `current` desplegado |
| Páginas y API responden correctamente con `categoria` | Sí | Listado, detalle y ambos endpoints JSON, sin errores PHP |
| Modo mantenimiento probado y usado durante la ventana de riesgo | Sí | `503` con la bandera activa, `200` sin ella, antes y durante la migración |
| Logs sin errores nuevos atribuibles al despliegue | Sí | Única línea nueva investigada y descartada como ajena (incidencia 6) |
| Ficha de despliegue con versión, fecha, pruebas y resultado | Sí | Este documento |

## Notas honestas y pendientes

- **`current-anterior-v0.8.0`**: se conserva por ahora como red de
  seguridad para un rollback de código inmediato (bastaría con
  intercambiar las carpetas de vuelta). Queda pendiente decidir un
  plazo para retirarlo una vez que `v0.9.0` lleve un tiempo estable en
  `ruta360-test`.
- **Rollback completo (restaurar la base desde el backup)**: no hizo
  falta ejercitarlo en este despliegue porque no surgió ningún
  problema que lo exigiera. El backup verificado (`ruta360_test_pre_v0.9.0.sql`)
  queda disponible por si hiciera falta más adelante.
- **Flujo de escritura de `categoria` en `ruta360_test`**: no probado
  contra ese entorno por falta de usuarios/token sembrados ahí (igual
  que en el Manual 9) — ver `docs/pruebas_manual_10.md`, sección
  "Fuera de alcance".
- Pendiente heredado del Manual 7, todavía sin resolver: reconstruir
  y verificar el ZIP de entrega `ruta360_entrega_7.zip`.
