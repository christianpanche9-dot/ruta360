<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/conexion.php';
header('Content-Type: application/json; charset=utf-8');

function responderJson(int $estado, array $contenido): never
{
    http_response_code($estado);
    echo json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    responderJson(405, ['ok' => false, 'error' => 'Método no permitido.']);
}

try {
    $consulta = $pdo->query(
        'SELECT id_ciudad, nombre, pais
           FROM ciudades
          WHERE activa = 1
          ORDER BY nombre'
    );
    $filas = $consulta->fetchAll();

    $ciudades = array_map(static fn(array $fila): array => [
        'id_ciudad' => (int) $fila['id_ciudad'],
        'nombre' => $fila['nombre'],
        'pais' => $fila['pais']
    ], $filas);

    responderJson(200, [
        'ok' => true,
        'total' => count($ciudades),
        'datos' => $ciudades
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    responderJson(500, ['ok' => false, 'error' => 'No se ha podido consultar las ciudades.']);
}
