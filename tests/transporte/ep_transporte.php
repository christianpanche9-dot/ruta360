<?php
declare(strict_types=1);

/**
 * Doble local del servicio de transporte externo, usado como valor
 * por defecto en config/servicios.php para poder probar Ruta360 sin
 * depender de un proveedor real (Manual 12, 12.27-12.28). Admite
 * parametros de consulta para simular distintos fallos.
 */

/**
 * Apache no siempre traslada la cabecera Authorization a
 * $_SERVER['HTTP_AUTHORIZATION']; getallheaders() sí la conserva.
 */
function obtenerCabeceraAutorizacion(): string
{
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $nombre => $valor) {
            if (strcasecmp($nombre, 'Authorization') === 0) {
                return $valor;
            }
        }
    }
    return '';
}

$tokenEsperado = 'demo-local-token';
$cabecera = obtenerCabeceraAutorizacion();
if ($cabecera !== 'Bearer ' . $tokenEsperado && !isset($_GET['ignorar_auth'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Token no válido']);
    exit;
}

if (isset($_GET['lento'])) {
    sleep((int) $_GET['lento']);
}

if (isset($_GET['http'])) {
    http_response_code((int) $_GET['http']);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Fallo simulado']);
    exit;
}

if (isset($_GET['json_invalido'])) {
    http_response_code(200);
    header('Content-Type: text/plain');
    echo 'esto no es json valido {{{';
    exit;
}

if (isset($_GET['sin_campo'])) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['alerts' => []]);
    exit;
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'estimated_minutes' => 12,
    'alerts' => ['Retraso leve en la línea 3']
]);
