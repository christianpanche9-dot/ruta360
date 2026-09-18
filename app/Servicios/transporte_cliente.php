<?php
declare(strict_types=1);

require_once __DIR__ . '/ServicioExternoException.php';
require_once __DIR__ . '/meteo_resiliente.php';

/**
 * Construye la URL del servicio de transporte a partir de la
 * configuracion del proveedor y el contexto de origen/destino.
 */
function construirUrlTransporte(array $config, string $origen, string $destino): string
{
    $parametros = http_build_query([
        'origen' => $origen,
        'destino' => $destino
    ]);

    return rtrim((string) $config['url'], '?') . '?' . $parametros;
}

/**
 * Ejecuta una unica peticion al servicio de transporte, con
 * autenticacion Bearer y los timeouts propios del proveedor.
 * Libera siempre el manejador de cURL, incluso si algo falla.
 */
function solicitarTransporteUnaVez(array $config, string $url): array
{
    $ch = null;
    try {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new ServicioExternoException(
                'inicio', 'No se ha podido iniciar cURL.'
            );
        }

        $cabeceras = ['Accept: application/json'];
        if (!empty($config['token'])) {
            $cabeceras[] = 'Authorization: Bearer ' . $config['token'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => (int) ($config['connect_timeout'] ?? 2),
            CURLOPT_TIMEOUT => (int) ($config['timeout'] ?? 4),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $cabeceras
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

        $codigo = (int) ($info['http_code'] ?? 0);
        if ($codigo < 200 || $codigo >= 300) {
            throw new ServicioExternoException(
                'http', 'El proveedor de transporte ha respondido con HTTP ' . $codigo,
                $codigo
            );
        }

        try {
            $datos = json_decode($cuerpo, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ServicioExternoException(
                'json', 'El proveedor de transporte no ha enviado JSON válido.'
            );
        }

        if (!is_array($datos)) {
            throw new ServicioExternoException(
                'contenido', 'Respuesta de transporte con formato inesperado.'
            );
        }

        return $datos;
    } finally {
        $ch = null;
    }
}

/**
 * Igual politica que solicitarConReintento() del Manual 11 (maximo
 * dos intentos, solo si el fallo anterior es transitorio), aplicada
 * al servicio de transporte. Reutilizamos esTransitorio() porque
 * clasifica por categoria/codigo, no por proveedor.
 */
function solicitarJsonTransporte(array $config, string $origen, string $destino): array
{
    $url = construirUrlTransporte($config, $origen, $destino);
    $maxIntentos = 2;

    for ($intento = 1; $intento <= $maxIntentos; $intento++) {
        try {
            return solicitarTransporteUnaVez($config, $url);
        } catch (ServicioExternoException $e) {
            error_log(sprintf(
                '[transporte] categoria=%s http=%d curl=%d mensaje=%s',
                $e->categoria, $e->codigoHttp, $e->codigoCurl, $e->getMessage()
            ));

            if ($intento === $maxIntentos || !esTransitorio($e)) {
                throw $e;
            }
            usleep(250000);
        }
    }

    throw new LogicException('Flujo de reintentos de transporte inesperado.');
}
