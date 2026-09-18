<?php
declare(strict_types=1);

require_once __DIR__ . '/meteo_resiliente.php';
require_once __DIR__ . '/cache_meteo.php';

/**
 * Estrategia "cache first": si la caché es reciente evitamos la
 * llamada externa. Si no lo es, intentamos actualizarla; si el
 * servicio falla, todavía podemos usar una copia antigua durante un
 * periodo limitado antes de renunciar a mostrar meteorología.
 */
function obtenerTiempoResiliente(float $latitud, float $longitud): array
{
    $archivo = rutaCache($latitud, $longitud);
    $cache = leerCache($archivo);
    $estado = clasificarCache($cache);

    if ($estado === 'reciente') {
        return [
            'disponible' => true,
            'origen' => 'cache_reciente',
            'datos' => $cache['datos']
        ];
    }

    try {
        $url = construirUrlMeteo($latitud, $longitud);
        $datos = solicitarConReintento($url);
        guardarCache($archivo, $datos);

        return [
            'disponible' => true,
            'origen' => 'servicio',
            'datos' => $datos
        ];
    } catch (Throwable $e) {
        error_log('[meteo] ' . $e->getMessage());

        if ($estado === 'antigua') {
            return [
                'disponible' => true,
                'origen' => 'cache_antigua',
                'datos' => $cache['datos']
            ];
        }

        return [
            'disponible' => false,
            'origen' => 'sin_datos',
            'datos' => null
        ];
    }
}
