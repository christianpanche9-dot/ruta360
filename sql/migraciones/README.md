# Migraciones

Cada cambio de esquema posterior a la primera entrega se documenta
aquí como un par de archivos, nunca uno solo: uno para aplicar el
cambio (`.up.sql`) y otro para revertirlo (`.down.sql`), con la misma
fecha y el mismo nombre descriptivo:

```
migraciones/2026-09-18_01_anadir_creado_en_a_rutas.up.sql
migraciones/2026-09-18_01_anadir_creado_en_a_rutas.down.sql
```

`sql/02_estructura.sql` sigue siendo la fuente de verdad para una
instalación *desde cero* (siempre incluye ya todos los cambios
aplicados hasta la fecha) — las migraciones son solo para una base que
ya existía **antes** del cambio y que no se puede simplemente volver a
crear desde cero sin perder sus datos.

Cada `.up.sql` debe poder ejecutarse solo, sobre una base que ya tenga
el esquema anterior, y explicar en un comentario qué corrige o qué
añade y por qué. Cada `.down.sql` debe dejar la estructura exactamente
como estaba antes de aplicar el `.up.sql` correspondiente — aunque no
siempre pueda recuperar los datos que ese cambio hubiera guardado
mientras estuvo aplicado (por ejemplo, revertir un `ADD COLUMN`
siempre pierde los valores de esa columna; eso es inevitable, no un
error de la migración).

## Migraciones aplicadas

| Fecha | Archivo | Qué cambia | Probado (aplicar + revertir) |
|---|---|---|---|
| 2026-09-18 | `2026-09-18_01_anadir_creado_en_a_rutas` | Añade `creado_en` a `rutas` (ya lo tenían `usuarios` y `api_tokens`, `rutas` no) | Sí — ver `docs/pruebas_manual_7.md`, caso A1 |

| 2026-09-21 | `2026-09-21_01_anadir_categoria_a_rutas` | Añade `categoria` (opcional) a `rutas`, para el Manual 10 | Sí — aplicar+revertir probado en `ruta360_dev`, ver docs/pruebas_manual_10.md |