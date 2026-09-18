<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/harness.php';

$base = 'http://localhost/curso_php/ruta360vs1/_test_meteo';

function resultado(string $nombre, callable $fn): void
{
    echo "== $nombre ==\n";
    $inicio = microtime(true);
    try {
        $r = $fn();
        $ms = (int) round((microtime(true) - $inicio) * 1000);
        echo "  OK ({$ms} ms): " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n\n";
    } catch (Throwable $e) {
        $ms = (int) round((microtime(true) - $inicio) * 1000);
        $extra = '';
        if ($e instanceof ServicioExternoException) {
            $t = esTransitorio($e) ? 'si' : 'no';
            $extra = " categoria={$e->categoria} http={$e->codigoHttp} curl={$e->codigoCurl} transitorio={$t}";
        }
        echo "  EXCEPCION ({$ms} ms): " . get_class($e) . ' - ' . $e->getMessage() . $extra . "\n\n";
    }
}

resultado('2. Dominio inexistente (transporte, transitorio, con reintento)', function () {
    return solicitarConReintento('http://ruta360-dominio-que-no-existe-xyz123.invalid/x');
});

resultado('3. Timeout total > 5s (transporte, transitorio, con reintento)', function () use ($base) {
    return solicitarConReintento($base . '/ep_timeout.php');
});

resultado('4. HTTP 503 persistente (un reintento y despues fallo)', function () use ($base) {
    return solicitarConReintento($base . '/ep_503.php');
});

escribirCachePrueba('503fallback', 3600);
resultado('4b. HTTP 503 persistente con cache antigua disponible (fallback completo)', function () use ($base) {
    return obtenerTiempoResilienteArchivo(rutaCachePrueba('503fallback'), $base . '/ep_503.php');
});

resultado('5. HTTP 401 (sin reintento automatico)', function () use ($base) {
    return solicitarConReintento($base . '/ep_401.php');
});

resultado('6. JSON invalido (sin reintento, categoria json)', function () use ($base) {
    return solicitarConReintento($base . '/ep_json_invalido.php');
});

escribirCachePrueba('reciente', 120);
resultado('7. Cache reciente <=15min (no debe llamar al proveedor)', function () {
    return obtenerTiempoResilienteArchivo(
        rutaCachePrueba('reciente'),
        'http://ruta360-dominio-que-no-existe-xyz123.invalid/no-deberia-llamarse'
    );
});

escribirCachePrueba('antigua', 7200);
resultado('8. Cache antigua 2h + proveedor caido (dato antiguo con aviso)', function () use ($base) {
    return obtenerTiempoResilienteArchivo(rutaCachePrueba('antigua'), 'http://ruta360-dominio-que-no-existe-xyz123.invalid/x');
});

escribirCachePrueba('caducada', 28800);
resultado('9. Cache caducada 8h + proveedor caido (ruta sin meteorologia)', function () use ($base) {
    return obtenerTiempoResilienteArchivo(rutaCachePrueba('caducada'), 'http://ruta360-dominio-que-no-existe-xyz123.invalid/x');
});

$archivoCorrupto = rutaCachePrueba('corrupta');
file_put_contents($archivoCorrupto, '{esto no es json valido');
resultado('10. Lectura de cache corrupta (fclose en finally, sin fuga de recursos)', function () use ($archivoCorrupto) {
    $r1 = leerCache($archivoCorrupto);
    $r2 = leerCache($archivoCorrupto);
    return ['primera_lectura' => $r1, 'segunda_lectura' => $r2, 'nota' => 'null esperado en ambas'];
});

echo "Bateria de pruebas Manual 11 completada.\n";
