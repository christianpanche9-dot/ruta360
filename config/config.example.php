<?php
declare(strict_types=1);

/**
 * Plantilla segura (Manual 6, 6.6). Se versiona y se entrega: no debe
 * contener ningún secreto real. Los marcadores 'CAMBIAR' señalan lo
 * que config/config.local.php tiene que rellenar de verdad para que
 * validar_config.php deje arrancar la aplicación (6.9).
 *
 * Tres grupos, no dos: además de 'app' y 'db' del ejemplo genérico
 * del manual, este proyecto tiene una API interna propia (Manual 4)
 * con su propia URL base y su propio token, que no encajan de forma
 * natural en ninguno de los otros dos grupos.
 */
return [
    'app' => [
        'env' => 'desarrollo',
        'debug' => true,
        'url' => 'http://localhost/ruta360_m6/public',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'ruta360_dev',
        'user' => 'CAMBIAR',
        'password' => 'CAMBIAR',
        'charset' => 'utf8mb4',
    ],
    'api' => [
        'base' => 'http://127.0.0.1/api',
        'token' => 'CAMBIAR',
    ],
];
