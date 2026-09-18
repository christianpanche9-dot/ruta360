<?php
declare(strict_types=1);

/**
 * Validador (Manual 6, 6.9). Una configuración incompleta debe fallar
 * aquí, con un mensaje que dice QUÉ falta sin revelar ningún valor:
 * ni el de config.local.php ni la excepción completa. Las cinco
 * comprobaciones son las que pide la tabla de 6.9 del manual.
 */
$config = require __DIR__ . '/cargar_config.php';

$entornosPermitidos = ['desarrollo', 'pruebas', 'produccion'];
if (!in_array($config['app']['env'] ?? null, $entornosPermitidos, true)) {
    throw new RuntimeException('app.env no válido.');
}

foreach (['host', 'name', 'user'] as $clave) {
    $valor = $config['db'][$clave] ?? '';
    if ($valor === '' || $valor === 'CAMBIAR') {
        throw new RuntimeException("Falta configuración de base de datos: db.$clave.");
    }
}

// db.password se valida aparte: en este proyecto una contraseña vacía
// es una configuración real y válida (MySQL local de XAMPP, usuario
// root sin contraseña, ver config.local.php), así que una cadena
// vacía no puede tratarse como "sin configurar". Lo único que debe
// rechazarse es dejar el marcador de plantilla sin rellenar.
if (($config['db']['password'] ?? null) === 'CAMBIAR') {
    throw new RuntimeException('Falta configuración de base de datos: db.password.');
}

if (!is_int($config['db']['port'] ?? null) || $config['db']['port'] < 1) {
    throw new RuntimeException('db.port debe ser un número de puerto válido.');
}

if (!is_bool($config['app']['debug'] ?? null)) {
    throw new RuntimeException('app.debug debe ser true o false.');
}

if (!preg_match('#^https?://#', (string) ($config['app']['url'] ?? ''))) {
    throw new RuntimeException('app.url debe empezar por http:// o https://.');
}

if (empty($config['api']['base'])
    || empty($config['api']['token'])
    || $config['api']['token'] === 'CAMBIAR'
) {
    throw new RuntimeException('Falta configuración de la API interna: api.base o api.token.');
}

return $config;
