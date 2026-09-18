-- Manual 7 (7.6-7.8): estructura completa de Ruta360, sin datos.
-- Ejecutar contra la base ya creada por 01_base_datos.sql:
--   mysql -u root ruta360vs1 < sql/02_estructura.sql
--
-- Las cinco tablas fijan explícitamente ENGINE=InnoDB y
-- CHARSET/COLLATE utf8mb4/utf8mb4_unicode_ci. Antes de este manual,
-- `usuarios` y `api_tokens` fijaban el charset pero no el collation,
-- y por eso habían quedado en utf8mb4_general_ci (el valor por
-- defecto del servidor en el momento de crearlas) en vez de
-- utf8mb4_unicode_ci como las demás — ver
-- docs/inventario_base_datos.md, punto 4. Aquí se corrige: las cinco
-- tablas comparten el mismo collation de forma explícita, para que no
-- dependan de la configuración del servidor donde se importen.

CREATE TABLE ciudades (
    id_ciudad INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    pais VARCHAR(100) NOT NULL,
    latitud DECIMAL(9,6) NOT NULL,
    longitud DECIMAL(9,6) NOT NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_ciudad_pais (nombre, pais)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rutas (
    id_ruta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_ciudad INT UNSIGNED NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    duracion_minutos SMALLINT UNSIGNED NOT NULL,
    distancia_km DECIMAL(5,2) NOT NULL,
    dificultad ENUM('facil','media','alta') NOT NULL DEFAULT 'facil',
    activa TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_rutas_ciudad
        FOREIGN KEY (id_ciudad) REFERENCES ciudades(id_ciudad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE puntos_interes (
    id_punto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_ruta INT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    orden SMALLINT UNSIGNED NOT NULL,
    CONSTRAINT fk_puntos_ruta
        FOREIGN KEY (id_ruta) REFERENCES rutas(id_ruta),
    UNIQUE KEY uq_ruta_orden (id_ruta, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios (
    id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('lector', 'editor', 'admin') NOT NULL DEFAULT 'lector',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_tokens (
    id_token INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expira_en DATETIME NULL,
    revocado_en DATETIME NULL,
    ultimo_uso DATETIME NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_token_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
