-- Reversión de 2026-09-21_01_anadir_categoria_a_rutas.up.sql
--
-- Deja la tabla `rutas` sin la columna `categoria`. Como con cualquier
-- reversión de un ADD COLUMN, se pierden los valores que esa columna
-- tuviera guardados en el momento de revertir -- no hay forma de
-- recuperarlos, no es un error de la migración.

ALTER TABLE rutas DROP COLUMN categoria;