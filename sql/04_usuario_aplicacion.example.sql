-- Manual 7 (7.11-7.12): usuario de aplicación con privilegios
-- limitados. EJEMPLO -- no se ejecuta tal cual: cambiar el usuario y,
-- sobre todo, la contraseña antes de usarlo. Ninguna copia de este
-- archivo con una contraseña real se versiona ni se entrega (mismo
-- criterio que config.local.php, ver .gitignore).
--
-- Por qué un usuario propio y no `root`: la aplicación solo necesita
-- leer y escribir filas (SELECT/INSERT/UPDATE/DELETE) — nunca crear
-- ni borrar tablas, ni gestionar otros usuarios ni otras bases. Si
-- algún día una inyección SQL u otro fallo permitiera ejecutar
-- sentencias arbitrarias, un usuario tan limitado no puede hacer
-- DROP TABLE, GRANT, ni tocar ninguna otra base del servidor.

CREATE USER IF NOT EXISTS 'ruta360_app'@'localhost'
    IDENTIFIED BY 'CAMBIAR_ESTA_CONTRASENA';

GRANT SELECT, INSERT, UPDATE, DELETE
    ON ruta360vs1.*
    TO 'ruta360_app'@'localhost';

FLUSH PRIVILEGES;

-- Verificación (7.12) — debe mostrar únicamente la línea GRANT de
-- arriba, nunca "ALL PRIVILEGES" ni acceso a otra base:
--   SHOW GRANTS FOR 'ruta360_app'@'localhost';
--
-- Prueba de que el límite es real (7.15, Fase C): con este usuario
-- conectado, un DROP TABLE o un CREATE USER debe fallar con
-- "Access denied" — ver docs/pruebas_manual_7.md, caso U1.
