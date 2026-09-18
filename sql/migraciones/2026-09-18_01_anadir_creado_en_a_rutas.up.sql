-- Migración: añadir creado_en a rutas
--
-- Aplica sobre un esquema ya instalado con sql/02_estructura.sql en su
-- versión original (Manual 7, Fase A). No es parte de una instalación
-- desde cero -- para eso ya sirve 02_estructura.sql tal cual -- sino un
-- cambio sobre una base que ya existe y que puede tener datos reales.
--
-- Qué corrige: `usuarios` y `api_tokens` ya registran cuándo se creó
-- cada fila (`creado_en TIMESTAMP ... DEFAULT CURRENT_TIMESTAMP`), pero
-- `rutas` -- que se crea y se edita con la misma frecuencia, o más -- no
-- lo hacía. Sin esta columna no hay forma de saber cuándo se dio de alta
-- una ruta ni de ordenarlas por antigüedad.
--
-- DEFAULT CURRENT_TIMESTAMP hace que las filas ya existentes queden con
-- la fecha del momento de aplicar la migración (no se puede reconstruir
-- retroactivamente una fecha de creación que nunca se guardó), y que
-- cualquier fila nueva se registre automáticamente a partir de ahora.

ALTER TABLE rutas
    ADD COLUMN creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    AFTER activa;
