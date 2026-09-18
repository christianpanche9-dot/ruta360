<?php
declare(strict_types=1);

require_once __DIR__ . '/ServicioExternoException.php';

/**
 * Construye la URL de la API meteorológica (Open-Meteo) para unas
 * coordenadas concretas. Pedimos únicamente lo que necesitamos:
 * temperatura actual y código de tiempo (weather_code).
 */
function construirUrlMeteo(float $latitud, float $longitud): string
{
    $base = 'https://api.open-meteo.com/v1/forecast';
    $parametros = http_build_query([
        'latitude' => $latitud,
        'longitude' => $longitud,
        'current' => 'temperature_2m,weather_code',
        'timezone' => 'Europe/Madrid'
    ]);

    return $base . '?' . $parametros;
}

/**
 * Ejecuta la petición meteorológica con timeouts de conexión y totales.
 * Libera siempre el manejador de cURL, incluso si algo falla.
 */
function solicitarTiempo(string $url): array
{
    $ch = null;
    try {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new ServicioExternoException(
                'inicio', 'No se ha podido iniciar cURL.'
            );
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);

        $cuerpo = curl_exec($ch);
        $codigoCurl = curl_errno($ch);
        $errorCurl = curl_error($ch);
        $info = curl_getinfo($ch);

        if ($cuerpo === false || $codigoCurl !== 0) {
            throw new ServicioExternoException(
                'transporte', 'Fallo de comunicación: ' . $errorCurl,
                0, $codigoCurl
            );
        }

        return validarRespuestaTiempo($cuerpo, $info);
    } finally {
        $ch = null;
    }
}

/**
 * Comprueba el código HTTP y decodifica el JSON antes de normalizar
 * los datos que Ruta360 necesita realmente.
 */
function validarRespuestaTiempo(string $cuerpo, array $info): array
{
    $codigo = (int) ($info['http_code'] ?? 0);
    if ($codigo < 200 || $codigo >= 300) {
        throw new ServicioExternoException(
            'http', 'El proveedor ha respondido con HTTP ' . $codigo, $codigo
        );
    }

    try {
        $datos = json_decode($cuerpo, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        throw new ServicioExternoException(
            'json', 'El proveedor no ha enviado JSON válido.'
        );
    }

    return normalizarTiempo($datos, $info);
}

/**
 * No devolvemos todo el JSON del proveedor: solo el contrato que
 * Ruta360 necesita, con los tiempos de la petición para depurar.
 */
function normalizarTiempo(array $datos, array $info): array
{
    $actual = $datos['current'] ?? null;
    if (!is_array($actual) ||
        !isset($actual['temperature_2m'], $actual['weather_code'])) {
        throw new ServicioExternoException(
            'contenido', 'Faltan datos meteorológicos obligatorios.'
        );
    }

    return [
        'temperatura' => (float) $actual['temperature_2m'],
        'codigo_tiempo' => (int) $actual['weather_code'],
        'obtenido_en' => date(DATE_ATOM),
        'tiempo_conexion' => (float) ($info['connect_time'] ?? 0),
        'tiempo_total' => (float) ($info['total_time'] ?? 0)
    ];
}

/**
 * Un fallo de transporte por DNS/conexión/timeout, o un HTTP temporal
 * (429/502/503/504), se considera transitorio y puede reintentarse.
 * Los demás (400/401/403/404, JSON inválido, contenido incompleto)
 * no se reintentan automáticamente.
 */
function esTransitorio(ServicioExternoException $e): bool
{
    if ($e->categoria === 'transporte') {
        return in_array($e->codigoCurl, [
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_CONNECT,
            CURLE_OPERATION_TIMEDOUT
        ], true);
    }

    return $e->categoria === 'http' &&
        in_array($e->codigoHttp, [429, 502, 503, 504], true);
}

/**
 * Como máximo dos intentos, con una pausa corta entre ellos, y solo
 * si el fallo anterior es transitorio.
 */
function solicitarConReintento(string $url): array
{
    $maxIntentos = 2;

    for ($intento = 1; $intento <= $maxIntentos; $intento++) {
        try {
            return solicitarTiempo($url);
        } catch (ServicioExternoException $e) {
            error_log(sprintf(
                '[meteo] categoria=%s http=%d curl=%d connect=%.3f total=%.3f mensaje=%s',
                $e->categoria, $e->codigoHttp, $e->codigoCurl,
                0.0, 0.0, $e->getMessage()
            ));

            if ($intento === $maxIntentos || !esTransitorio($e)) {
                throw $e;
            }
            usleep(250000);
        }
    }

    throw new LogicException('Flujo de reintentos inesperado.');
}
