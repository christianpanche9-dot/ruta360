-- Manual 7 (7.6-7.7): creación de la base de datos, en su propio
-- archivo — antes todo esto vivía junto con la primera tabla en
-- sql/01_estructura.sql. Ejecutar sin seleccionar ninguna base
-- primero (`mysql -u root < sql/01_base_datos.sql`).
--
-- Si se necesita un nombre distinto (por ejemplo, para tener una
-- copia de pruebas en paralelo), cambiar el nombre en las dos líneas
-- de abajo. No hay ningún otro sitio en estos SQL donde el nombre
-- esté escrito de nuevo.

CREATE DATABASE IF NOT EXISTS ruta360vs1
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE ruta360vs1;
