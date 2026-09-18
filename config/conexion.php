<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Antes de este manual, este archivo tenía escritas de forma literal
 * las credenciales de la base de datos y nunca leía
 * config/config.local.php (Manual 6, hallazgo principal, ver
 * docs/inventario_configuracion.md). Ahora usa la configuración ya
 * cargada y validada por config/bootstrap.php.
 */
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    config('db', 'host'),
    config('db', 'port'),
    config('db', 'name'),
    config('db', 'charset')
);

try {
    $pdo = new PDO(
        $dsn,
        config('db', 'user'),
        config('db', 'password'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    // El mensaje al navegador no cambia; el registro sí añade el
    // motivo real (nunca las credenciales, que no aparecen en
    // $e->getMessage() de un fallo de conexión típico, pero por si
    // acaso no se imprime $dsn completo con el usuario/contraseña).
    error_log('conexion: no se ha podido conectar con la base de datos - ' . $e->getMessage());
    http_response_code(500);
    exit('No se ha podido conectar con la base de datos.');
}
