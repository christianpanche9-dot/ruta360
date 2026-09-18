<?php
declare(strict_types=1);

/**
 * Nombre de archivo de caché para unas coordenadas concretas.
 * La clave se normaliza para no depender de datos externos al
 * construir la ruta del sistema de archivos.
 */
function rutaCache(float $latitud, float $longitud): string
{
    $clave = number_format($latitud, 2, '.', '') . '_' .
             number_format($longitud, 2, '.', '');
    $clave = str_replace(['-', '.'], ['m', '_'], $clave);

    return __DIR__ . '/../../storage/cache/meteo_' . $clave . '.json';
}

/**
 * Escribe primero un archivo temporal y luego lo renombra, para que
 * ninguna lectura concurrente encuentre un JSON a medio escribir.
 */
function guardarCache(string $archivo, array $datos): void
{
    $json = json_encode(
        ['guardado_en' => time(), 'datos' => $datos],
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );

    $temporal = $archivo . '.tmp';
    if (file_put_contents($temporal, $json, LOCK_EX) === false) {
        throw new RuntimeException('No se ha podido escribir la caché.');
    }
    if (!rename($temporal, $archivo)) {
        throw new RuntimeException('No se ha podido publicar la caché.');
    }
}

/**
 * Lee la caché con fopen() para practicar la liberación explícita:
 * el manejador se cierra en finally incluso si fread() o
 * json_decode() fallan.
 */
function leerCache(string $archivo): ?array
{
    if (!is_file($archivo)) {
        return null;
    }

    $fh = fopen($archivo, 'rb');
    if ($fh === false) {
        return null;
    }

    try {
        $contenido = stream_get_contents($fh);
        if ($contenido === false) {
            return null;
        }
        $cache = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);
        return is_array($cache) ? $cache : null;
    } catch (\JsonException $e) {
        error_log('[cache] JSON no válido: ' . $archivo);
        return null;
    } finally {
        fclose($fh);
    }
}

/**
 * Clasifica la antigüedad de la caché: reciente (<=15 min), antigua
 * (<=6 horas, todavía útil para degradar el servicio) o caducada.
 */
function clasificarCache(?array $cache): string
{
    if ($cache === null || !isset($cache['guardado_en'], $cache['datos'])) {
        return 'ausente';
    }

    $edad = time() - (int) $cache['guardado_en'];
    if ($edad <= 900) {
        return 'reciente';
    }
    if ($edad <= 21600) {
        return 'antigua';
    }
    return 'caducada';
}
