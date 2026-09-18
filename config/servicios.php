<?php
declare(strict_types=1);

/**
 * Configuracion centralizada de los proveedores externos (Manual 12,
 * 12.17). Las claves y contraseñas no deben quedar escritas en el
 * repositorio, la vista ni los registros de errores: por eso se leen
 * de variables de entorno, con un valor local de demostracion como
 * respaldo para poder ejecutar el proyecto sin depender de Internet.
 */
return [
    'meteorologia' => [
        // Sin parametros propios: el adaptador reutiliza
        // obtenerTiempoResiliente(), que ya trae su configuracion
        // (Open-Meteo) del Manual 11.
    ],
    'transporte' => [
        // El simulador vive en tests/ (fuera de public/, a propósito:
        // no es parte de la app publica) y se sirve mediante un Alias
        // de Apache dedicado a desarrollo/pruebas (ver httpd-vhosts.conf,
        // Manual 5 Fase D), nunca dentro del DocumentRoot público.
        // 127.0.0.1:18080, no ruta360-m6.local: mismo motivo que api_base en
        // config.local.php (el cURL interno de PHP no resuelve nombres
        // .local personalizados de forma fiable en XAMPP para Mac).
        'url' => $_ENV['TRANS_URL']
            ?? 'http://127.0.0.1:18080/_tests/transporte/ep_transporte.php',
        'token' => $_ENV['TRANS_TOKEN'] ?? 'demo-local-token',
        'connect_timeout' => 2,
        'timeout' => 4
    ],
    'distancias_soap' => [
        // Mismo motivo que 'transporte': el WSDL vive en
        // app/Servicios/soap/ (interno) y se sirve por un Alias de
        // desarrollo, no por el DocumentRoot público.
        // Mismo motivo: puerto 18080 en vez del nombre del vhost (ver nota en transporte, arriba).
        'wsdl' => $_ENV['DISTANCIAS_WSDL']
            ?? 'http://127.0.0.1:18080/_soap/distancias.wsdl',
        'connect_timeout' => 2,
        'timeout' => 5
    ],
    // Proyecto final (Manual 4, 4.18-4.19): Open Library, API pública
    // sin clave de acceso. url/sitio_url y los límites se externalizan
    // igual que en transporte/distancias_soap para que cada entorno
    // pueda ajustarlos (por ejemplo, un límite más bajo en pruebas)
    // sin tocar el código.
    'libros' => [
        'url' => $_ENV['LIBROS_URL'] ?? 'https://openlibrary.org/search.json',
        'sitio_url' => $_ENV['LIBROS_SITIO_URL'] ?? 'https://openlibrary.org',
        'connect_timeout' => 3,
        'timeout' => 6,
        'limite_resultados' => (int) ($_ENV['LIBROS_LIMITE'] ?? 10),
        'contacto' => $_ENV['LIBROS_CONTACTO'] ?? 'demo@ruta360.local',
    ]
];
