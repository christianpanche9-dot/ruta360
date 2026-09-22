<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$version = 'desconocida';
$rutaVersion = __DIR__ . '/../VERSION';
if (is_file($rutaVersion)) {
    $version = trim(file_get_contents($rutaVersion));
}

$estado = ['app' => 'ok', 'db' => 'error', 'version' => $version];
$codigo = 503;

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        config('db', 'host'),
        config('db', 'port'),
        config('db', 'name'),
        config('db', 'charset')
    );
    $pdo = new PDO($dsn, config('db', 'user'), config('db', 'password'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    $pdo->query('SELECT 1');
    $estado['db'] = 'ok';
    $codigo = 200;
} catch (Throwable $e) {
    registrar('ERROR', 'Health check: fallo de base de datos', []);
}

http_response_code($codigo);
echo json_encode($estado, JSON_UNESCAPED_UNICODE);
