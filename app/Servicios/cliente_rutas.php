<?php
require_once __DIR__ . '/../../config/config_api.php';

/**
 * Devuelve la URL base de la API interna (config, grupo 'api', clave
 * 'base').
 *
 * Corrige la incidencia documentada en el Manual 4 (guía 4.14): antes
 * cada función de este archivo tenía escrita de forma literal la URL
 * de desarrollo, por lo que el entorno de pruebas (y cualquier otro)
 * mostraba siempre los datos de desarrollo sin avisar.
 *
 * Antes de este manual, esta función leía config/config.local.php por
 * su cuenta, con su propia copia del mismo mecanismo de fallo que
 * config/conexion.php y config/config_api.php (Manual 6, hallazgo
 * principal, ver docs/inventario_configuracion.md). Ahora usa
 * directamente la configuración ya cargada y validada por
 * config/bootstrap.php (disponible aquí porque config_api.php, ya
 * incluido arriba, lo requiere). Si no se puede determinar, la
 * función sigue fallando de forma clara (registra el motivo y lanza
 * una excepción) en vez de usar una URL equivocada en silencio.
 */
function obtenerBaseApiInterna(): string
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    $valor = config('api', 'base');
    if (empty($valor)) {
        error_log('cliente_rutas: no se ha podido determinar la API interna (api.base vacío o no definido).');
        throw new RuntimeException('No se ha podido determinar la URL de la API interna.');
    }

    $base = rtrim($valor, '/');
    return $base;
}

function obtenerRutaApi(int $idRuta): array
{
    try {
        $base = obtenerBaseApiInterna() . '/ruta.php';
    } catch (Throwable $e) {
        return ['ok' => false, 'estado' => 0,
                'error' => 'No se ha podido determinar la API interna.'];
    }
    $url = $base . '?' . http_build_query(['id_ruta' => $idRuta]);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);

    $cuerpo = curl_exec($curl);
    $errorCurl = curl_error($curl);
    $estadoHttp = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($cuerpo === false) {
        error_log($errorCurl);
        return ['ok' => false, 'estado' => 0,
                'error' => 'No se ha podido contactar con el servicio.'];
    }

    $contenido = json_decode($cuerpo, true);
    if (!is_array($contenido)) {
        return ['ok' => false, 'estado' => $estadoHttp,
                'error' => 'El servicio ha devuelto una respuesta no válida.'];
    }

    if ($estadoHttp !== 200 || ($contenido['ok'] ?? false) !== true) {
        return ['ok' => false, 'estado' => $estadoHttp,
                'error' => $contenido['error']
                    ?? 'El servicio no ha podido completar la petición.'];
    }

    if (!isset($contenido['datos']) || !is_array($contenido['datos'])) {
        return ['ok' => false, 'estado' => $estadoHttp,
                'error' => 'La respuesta no contiene los datos esperados.'];
    }

    return ['ok' => true, 'estado' => $estadoHttp,
            'datos' => $contenido['datos']];
}

function obtenerRutas(
    ?int $idCiudad = null,
    ?int $duracionMaxima = null,
    ?string $dificultad = null
): array {
    try {
        $base = obtenerBaseApiInterna() . '/rutas.php';
    } catch (Throwable $e) {
        return ['ok' => false, 'estado' => 0, 'datos' => null,
                'error' => 'No se ha podido determinar la API interna.'];
    }

    $parametros = [];
    if ($idCiudad !== null) {
        $parametros['id_ciudad'] = $idCiudad;
    }
    if ($duracionMaxima !== null) {
        $parametros['duracion_maxima'] = $duracionMaxima;
    }
    if ($dificultad !== null) {
        $parametros['dificultad'] = $dificultad;
    }

    $url = $base . ($parametros !== [] ? '?' . http_build_query($parametros) : '');

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);

    $cuerpo = curl_exec($curl);
    $errorCurl = curl_error($curl);
    $estadoHttp = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($cuerpo === false) {
        error_log($errorCurl);
        return ['ok' => false, 'estado' => 0, 'datos' => null,
                'error' => 'No se ha podido contactar con el servicio de rutas.'];
    }

    $respuesta = json_decode($cuerpo, true);
    if (!is_array($respuesta)) {
        return ['ok' => false, 'estado' => $estadoHttp, 'datos' => null,
                'error' => 'El servicio ha devuelto una respuesta no válida.'];
    }

    if ($estadoHttp < 200 || $estadoHttp >= 300 || ($respuesta['ok'] ?? false) !== true) {
        return ['ok' => false, 'estado' => $estadoHttp, 'datos' => null,
                'error' => $respuesta['error']
                    ?? 'El servicio no ha podido completar la petición.'];
    }

    if (!isset($respuesta['total'], $respuesta['datos'])) {
        return ['ok' => false, 'estado' => $estadoHttp, 'datos' => null,
                'error' => 'La respuesta no contiene los datos esperados.'];
    }

    return ['ok' => true, 'estado' => $estadoHttp,
            'datos' => $respuesta, 'error' => null];
}

function obtenerCiudades(): array
{
    try {
        $url = obtenerBaseApiInterna() . '/ciudades.php';
    } catch (Throwable $e) {
        return ['ok' => false, 'estado' => 0, 'datos' => null,
                'error' => 'No se ha podido determinar la API interna.'];
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);

    $cuerpo = curl_exec($curl);
    $errorCurl = curl_error($curl);
    $estadoHttp = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($cuerpo === false) {
        error_log($errorCurl);
        return ['ok' => false, 'estado' => 0, 'datos' => null,
                'error' => 'No se ha podido contactar con el servicio de ciudades.'];
    }

    $respuesta = json_decode($cuerpo, true);
    if (!is_array($respuesta)) {
        return ['ok' => false, 'estado' => $estadoHttp, 'datos' => null,
                'error' => 'El servicio de ciudades ha devuelto una respuesta no válida.'];
    }

    if ($estadoHttp < 200 || $estadoHttp >= 300 || ($respuesta['ok'] ?? false) !== true) {
        return ['ok' => false, 'estado' => $estadoHttp, 'datos' => null,
                'error' => $respuesta['error'] ?? 'El servicio de ciudades ha devuelto un error.'];
    }

    if (!isset($respuesta['datos']) || !is_array($respuesta['datos'])) {
        return ['ok' => false, 'estado' => $estadoHttp, 'datos' => null,
                'error' => 'La respuesta no contiene los datos esperados.'];
    }

    return ['ok' => true, 'estado' => $estadoHttp,
            'datos' => $respuesta['datos'], 'error' => null];
}

function crearRuta(array $datos): array
{
    try {
        $url = obtenerBaseApiInterna() . '/rutas.php';
    } catch (Throwable $e) {
        return ['ok' => false, 'estado' => 0,
                'error' => 'No se ha podido determinar la API interna.'];
    }
    $json = json_encode($datos, JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return ['ok' => false, 'estado' => 0,
                'error' => 'No se han podido preparar los datos.'];
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . API_TOKEN
        ],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8
    ]);

    $respuestaCruda = curl_exec($curl);
    $estadoHttp = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($curl);
    curl_close($curl);

    if ($respuestaCruda === false || $errorCurl !== '') {
        return ['ok' => false, 'estado' => 0,
                'error' => 'No se ha podido conectar con la API.'];
    }

    $contenido = json_decode($respuestaCruda, true);
    if (!is_array($contenido)) {
        return ['ok' => false, 'estado' => $estadoHttp,
                'error' => 'La API ha enviado una respuesta no válida.'];
    }

    return [
        'ok' => $estadoHttp === 201 && ($contenido['ok'] ?? false),
        'estado' => $estadoHttp,
        'error' => $contenido['error'] ?? ($estadoHttp === 201 ? null : 'Respuesta sin mensaje.'),
        'errores' => $contenido['errores'] ?? [],
        'datos' => $contenido['datos'] ?? []
    ];
}

function enviarJson(string $metodo, string $url, ?array $datos = null): array
{
    $curl = curl_init($url);
    $opciones = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . API_TOKEN
        ],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8
    ];

    if ($datos !== null) {
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return ['ok' => false, 'estado' => 0,
                    'error' => 'No se han podido preparar los datos.'];
        }
        $opciones[CURLOPT_POSTFIELDS] = $json;
    }

    curl_setopt_array($curl, $opciones);
    $respuesta = curl_exec($curl);
    $estado = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $errorCurl = curl_error($curl);
    curl_close($curl);

    if ($respuesta === false || $errorCurl !== '') {
        return ['ok' => false, 'estado' => 0,
                'error' => 'No se ha podido conectar con la API.'];
    }

    $contenido = json_decode($respuesta, true);
    if (!is_array($contenido)) {
        return ['ok' => false, 'estado' => $estado,
                'error' => 'La API ha enviado una respuesta no válida.'];
    }

    return $contenido + ['estado' => $estado];
}

function actualizarRuta(int $idRuta, array $datos): array
{
    try {
        $url = obtenerBaseApiInterna() . '/rutas.php?id_ruta=' . $idRuta;
    } catch (Throwable $e) {
        return ['ok' => false, 'estado' => 0,
                'error' => 'No se ha podido determinar la API interna.'];
    }
    return enviarJson('PUT', $url, $datos);
}

function modificarRuta(int $idRuta, array $cambios): array
{
    try {
        $url = obtenerBaseApiInterna() . '/rutas.php?id_ruta=' . $idRuta;
    } catch (Throwable $e) {
        return ['ok' => false, 'estado' => 0,
                'error' => 'No se ha podido determinar la API interna.'];
    }
    return enviarJson('PATCH', $url, $cambios);
}

function eliminarRutaCliente(int $idRuta): array
{
    try {
        $url = obtenerBaseApiInterna() . '/rutas.php?id_ruta=' . $idRuta;
    } catch (Throwable $e) {
        return ['ok' => false, 'estado' => 0,
                'error' => 'No se ha podido determinar la API interna.'];
    }
    return enviarJson('DELETE', $url);
}
