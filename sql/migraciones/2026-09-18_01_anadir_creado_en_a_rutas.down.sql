-- Reversión de 2026-09-18_01_anadir_creado_en_a_rutas.up.sql
--
-- Deja la tabla `rutas` exactamente como en sql/02_estructura.sql
-- (versión original): sin columna `creado_en`. Se pierde la fecha de
-- creación registrada desde que se aplicó la migración -- una
-- reversión de un ALTER ADD COLUMN siempre pierde los valores de esa
-- columna, no hay forma de deshacer eso -- pero la estructura vuelve a
-- ser idéntica a la de antes de aplicarla.

ALTER TABLE rutas DROP COLUMN creado_en;
