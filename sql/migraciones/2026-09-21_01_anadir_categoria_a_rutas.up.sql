-- Migración: añadir categoria a rutas
--
-- Aplica sobre un esquema que ya tenga la estructura de
-- sql/02_estructura.sql (con o sin la migración
-- 2026-09-18_01_anadir_creado_en_a_rutas ya aplicada; esta migración
-- no depende de esa columna). No es parte de una instalación desde
-- cero -- para eso ya sirve 02_estructura.sql -- sino un cambio sobre
-- una base que ya existe y que puede tener datos reales.
--
-- Qué añade: una categoría libre para clasificar rutas (ej. "urbana",
-- "naturaleza", "cultural"). Es NULL/opcional a propósito: las filas
-- existentes no tienen valor que poner ahí, y el código de la versión
-- anterior (que no conoce esta columna) debe seguir funcionando sin
-- cambios contra el esquema nuevo -- eso es lo que permite, si hiciera
-- falta, un rollback de código sin tocar la base de datos.

ALTER TABLE rutas
    ADD COLUMN categoria VARCHAR(40) NULL DEFAULT NULL AFTER dificultad;