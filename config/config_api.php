<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Sigue corrigiendo las dos incidencias documentadas desde el Manual 5:
 * - RS-01 (Manual 3): el token de la API interna escrito en texto
 *   plano en este mismo archivo, compartido en el repositorio.
 * - API_BASE_URL fijada de forma literal a la carpeta de desarrollo
 *   (mismo tipo de problema corregido en
 *   app/Servicios/cliente_rutas.php, incidencia 4.14, Manual 4).
 *
 * Antes de este manual, este archivo leía config/config.local.php por
 * su cuenta, con su propia copia del mismo mecanismo de fallo que
 * config/conexion.php y app/Servicios/cliente_rutas.php (Manual 6,
 * hallazgo principal, ver docs/inventario_configuracion.md). Ahora usa
 * directamente la configuración ya cargada y validada por
 * config/bootstrap.php: ninguno de los dos valores está ya escrito en
 * este archivo ni en ningún otro que se comparta en el repositorio.
 */
define('API_TOKEN', config('api', 'token'));
define('API_BASE_URL', rtrim(config('api', 'base'), '/'));
