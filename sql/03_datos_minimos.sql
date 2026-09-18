-- Manual 7 (7.6, 7.9): datos mínimos, suficientes para navegar la
-- aplicación sin depender de nada acumulado durante las pruebas de
-- manuales anteriores. Ejecutar tras 02_estructura.sql:
--   mysql -u root ruta360vs1 < sql/03_datos_minimos.sql
--
-- No inserta usuarios ni tokens: como ya se documentaba en el README
-- del Manual 6 ("Limitación conocida"), un usuario de prueba real
-- necesita una contraseña hasheada con password_hash() y un token con
-- su hash SHA-256, y aquí no se documenta ningún valor concreto para
-- no repetir el problema de seguridad ya corregido en el Manual 3
-- (RS-01). Las tablas `usuarios` y `api_tokens` quedan creadas y
-- vacías, listas para insertar un usuario de prueba a mano.

INSERT INTO ciudades
    (nombre, pais, latitud, longitud)
VALUES
    ('Barcelona', 'España', 41.387400, 2.168600),
    ('Madrid', 'España', 40.416800, -3.703800),
    ('Valencia', 'España', 39.469900, -0.376300),
    ('París', 'Francia', 48.856600, 2.352200),
    ('Roma', 'Italia', 41.902800, 12.496400);

INSERT INTO rutas
    (id_ciudad, titulo, descripcion, duracion_minutos, distancia_km, dificultad)
VALUES
    (1, 'Barcelona modernista',
     'Un recorrido por algunos espacios esenciales del modernismo.',
     180, 4.80, 'media');

INSERT INTO puntos_interes
    (id_ruta, nombre, descripcion, orden)
VALUES
    (1, 'Sagrada Família', 'Inicio del recorrido.', 1),
    (1, 'Casa Milà', 'Arquitectura de Antoni Gaudí.', 2),
    (1, 'Casa Batlló', 'Fachada y formas inspiradas en la naturaleza.', 3);
