<?php
declare(strict_types=1);

/**
 * Cargador (Manual 6, 6.8). Combina la plantilla con el archivo
 * local: la plantilla aporta la estructura y los valores por
 * defecto que no son sensibles; el archivo local sobrescribe solo lo
 * que declara. array_replace_recursive conserva las claves de
 * config.example.php que config.local.php no menciona.
 */
$example = require __DIR__ . '/config.example.php';
$localFile = __DIR__ . '/config.local.php';

if (!is_file($localFile)) {
    throw new RuntimeException(
        'Falta config/config.local.php. Copia config.example.php y complétalo (ver README.md).'
    );
}

$local = require $localFile;
$config = array_replace_recursive($example, $local);

/**
 * Ampliación (Manual 6, 6.14 y 6.20). Si están definidas, las
 * variables de entorno tienen la última palabra sobre db.user y
 * db.password — por encima incluso de config.local.php. Útil en
 * despliegues donde no conviene mantener un config.local.php físico
 * (contenedores, integración continua, un PaaS que ya da variables de
 * entorno propias): permite fijar las credenciales sin escribir ningún
 * archivo en disco. Si la variable no está definida, o está vacía, no
 * se toca el valor que ya viniera de config.local.php.
 */
$usuarioEntorno = getenv('RUTA360_DB_USER');
if ($usuarioEntorno !== false && $usuarioEntorno !== '') {
    $config['db']['user'] = $usuarioEntorno;
}

$contrasenaEntorno = getenv('RUTA360_DB_PASSWORD');
if ($contrasenaEntorno !== false && $contrasenaEntorno !== '') {
    $config['db']['password'] = $contrasenaEntorno;
}

return $config;
