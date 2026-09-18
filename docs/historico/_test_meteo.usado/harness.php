<?php
declare(strict_types=1);

require_once __DIR__ . '/../servicios/meteo_resiliente.php';
require_once __DIR__ . '/../servicios/cache_meteo.php';

/**
 * Replica la orquestacion de obtenerTiempoResiliente() pero permite
 * inyectar el archivo de cache y la URL del servicio, para poder
 * probar caidas del proveedor y estados de cache sin modificar el
 * servicio real ni la cache real de produccion.
 */
function obtenerTiempoResilienteArchivo(string $archivo, string $url): array
{
    $cache = leerCache($archivo);
    $estado = clasificarCache($cache);

    if ($estado === 'reciente') {
        return ['disponible' => true, 'origen' => 'cache_reciente', 'datos' => $cache['datos']];
    }

    try {
        $datos = solicitarConReintento($url);
        guardarCache($archivo, $datos);
        return ['disponible' => true, 'origen' => 'servicio', 'datos' => $datos];
    } catch (Throwable $e) {
        error_log('[meteo-test] ' . $e->getMessage());
        if ($estado === 'antigua') {
            return ['disponible' => true, 'origen' => 'cache_antigua', 'datos' => $cache['datos']];
        }
        return ['disponible' => false, 'origen' => 'sin_datos', 'datos' => null];
    }
}

function rutaCachePrueba(string $etiqueta): string
{
    return __DIR__ . '/../storage/cache/meteo_test_' . $etiqueta . '.json';
}

function escribirCachePrueba(string $etiqueta, int $antiguedadSegundos): void
{
    $archivo = rutaCachePrueba($etiqueta);
    $json = json_encode([
        'guardado_en' => time() - $antiguedadSegundos,
        'datos' => [
            'temperatura' => 15.5,
            'codigo_tiempo' => 3,
            'obtenido_en' => date(DATE_ATOM),
            'tiempo_conexion' => 0.05,
            'tiempo_total' => 0.1
        ]
    ], JSON_UNESCAPED_UNICODE);
    file_put_contents($archivo, $json, LOCK_EX);
}
