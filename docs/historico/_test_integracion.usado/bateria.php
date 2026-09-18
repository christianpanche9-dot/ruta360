<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../servicios/RepositorioRuta.php';
require_once __DIR__ . '/../servicios/PlanificadorRuta.php';
require_once __DIR__ . '/../servicios/AdaptadorMeteorologia.php';
require_once __DIR__ . '/../servicios/AdaptadorTransporte.php';
require_once __DIR__ . '/../servicios/AdaptadorDistanciasSoap.php';
require_once __DIR__ . '/../servicios/ProveedorSimulado.php';

$config = require __DIR__ . '/../config/servicios.php';
$repo = new RepositorioRuta();
$idRuta = 1;

function resultado(string $nombre, callable $fn): void
{
    echo "== $nombre ==\n";
    $inicio = microtime(true);
    try {
        $ficha = $fn();
        $ms = (int) round((microtime(true) - $inicio) * 1000);
        $resumen = [];
        foreach ($ficha['externos'] as $nombreProveedor => $r) {
            $resumen[$nombreProveedor] = [
                'disponible' => $r->disponible,
                'origen' => $r->origen,
                'aviso' => $r->aviso,
                'datos' => $r->datos
            ];
        }
        echo "  OK ({$ms} ms): ruta=" . $ficha['ruta']['titulo'] . "\n";
        echo "  " . json_encode($resumen, JSON_UNESCAPED_UNICODE) . "\n\n";
    } catch (Throwable $e) {
        $ms = (int) round((microtime(true) - $inicio) * 1000);
        echo "  EXCEPCION ({$ms} ms): " . get_class($e) . ' - ' . $e->getMessage() . "\n\n";
    }
}

// 1) Todos correctos: la ficha reune las tres integraciones.
resultado('1. Todos correctos (tres proveedores simulados, exito)', function () use ($repo, $idRuta) {
    $proveedores = [
        new ProveedorSimulado('meteorologia', new ResultadoExterno(true, 'meteorologia', ['temperatura' => 21.0, 'codigo_tiempo' => 1])),
        new ProveedorSimulado('transporte', new ResultadoExterno(true, 'transporte', ['duracion_minutos' => 9, 'incidencias' => []])),
        new ProveedorSimulado('distancias', new ResultadoExterno(true, 'distancias', ['distancia_km' => 4.9]))
    ];
    $planificador = new PlanificadorRuta($repo, $proveedores);
    return $planificador->preparar($idRuta);
});

// 2) SOAP lanza SoapFault simulado: solo falta la distancia oficial.
resultado('2. SOAP lanza SoapFault simulado (solo falta distancia oficial)', function () use ($repo, $idRuta, $config) {
    $proveedores = [
        new AdaptadorMeteorologia($config['meteorologia']),
        new AdaptadorTransporte($config['transporte']),
        new AdaptadorDistanciasSoap(
            'http://localhost/curso_php/ruta360vs1/soap/distancias_fallo.wsdl'
        )
    ];
    $planificador = new PlanificadorRuta($repo, $proveedores);
    return $planificador->preparar($idRuta);
});

// 2b) Respuesta SOAP sin DistanceKm: tambien debe faltar solo la distancia.
resultado('2b. SOAP responde sin DistanceKm (solo falta distancia oficial)', function () use ($repo, $idRuta, $config) {
    $proveedores = [
        new AdaptadorMeteorologia($config['meteorologia']),
        new AdaptadorTransporte($config['transporte']),
        new AdaptadorDistanciasSoap(
            'http://localhost/curso_php/ruta360vs1/soap/distancias_sin_distancia.wsdl'
        )
    ];
    $planificador = new PlanificadorRuta($repo, $proveedores);
    return $planificador->preparar($idRuta);
});

// 3) Transporte supera el tiempo: la ficha continua sin transporte.
resultado('3. Transporte supera el tiempo (la ficha continua sin transporte)', function () use ($repo, $idRuta, $config) {
    $configLento = $config['transporte'];
    $configLento['url'] = 'http://localhost/curso_php/ruta360vs1/_test_transporte/ep_transporte.php?lento=6';
    $configLento['timeout'] = 2;
    $configLento['connect_timeout'] = 2;
    $proveedores = [
        new AdaptadorMeteorologia($config['meteorologia']),
        new AdaptadorTransporte($configLento),
        new AdaptadorDistanciasSoap($config['distancias_soap']['wsdl'])
    ];
    $planificador = new PlanificadorRuta($repo, $proveedores);
    return $planificador->preparar($idRuta);
});

// 4) Meteorologia usa cache antigua: dato visible con aviso de antiguedad.
// (La logica de obtenerTiempoResiliente() ya se probo exhaustivamente a
// nivel de funcion en la bateria del Manual 11; aqui comprobamos que el
// planificador y la vista propagan el origen 'cache_antigua' de forma
// generica para cualquier proveedor, tal como exige 12.25.)
resultado('4. Meteorologia con origen cache_antigua (aviso de antiguedad)', function () use ($repo, $idRuta) {
    $proveedores = [
        new ProveedorSimulado('meteorologia', new ResultadoExterno(
            true, 'meteorologia', ['temperatura' => 18.0, 'codigo_tiempo' => 2], 'cache_antigua'
        )),
        new ProveedorSimulado('transporte', new ResultadoExterno(true, 'transporte', ['duracion_minutos' => 10, 'incidencias' => []])),
        new ProveedorSimulado('distancias', new ResultadoExterno(true, 'distancias', ['distancia_km' => 4.8]))
    ];
    $planificador = new PlanificadorRuta($repo, $proveedores);
    return $planificador->preparar($idRuta);
});

// 5) Presupuesto agotado: se omiten proveedores de menor prioridad.
resultado('5. Presupuesto agotado (se omiten proveedores posteriores)', function () use ($repo, $idRuta) {
    $proveedores = [
        new ProveedorSimulado('meteorologia', new ResultadoExterno(true, 'meteorologia', ['temperatura' => 19.0, 'codigo_tiempo' => 1]), 60),
        new ProveedorSimulado('transporte', new ResultadoExterno(true, 'transporte', ['duracion_minutos' => 8, 'incidencias' => []])),
        new ProveedorSimulado('distancias', new ResultadoExterno(true, 'distancias', ['distancia_km' => 4.8]))
    ];
    // Presupuesto de 50 ms: el primer proveedor (60 ms de retardo simulado)
    // agota el presupuesto y los siguientes deben omitirse.
    $planificador = new PlanificadorRuta($repo, $proveedores, 50);
    return $planificador->preparar($idRuta);
});

// 6) Todos los externos fallan: la ruta local continua visible.
resultado('6. Todos los externos fallan (la ruta local sigue visible)', function () use ($repo, $idRuta) {
    $proveedores = [
        new ProveedorSimulado('meteorologia', new ServicioExternoException('transporte', 'Fallo simulado meteorologia')),
        new ProveedorSimulado('transporte', new ServicioExternoException('http', 'Fallo simulado transporte', 503)),
        new ProveedorSimulado('distancias', new ServicioExternoException('soap', 'Fallo simulado distancias'))
    ];
    $planificador = new PlanificadorRuta($repo, $proveedores);
    return $planificador->preparar($idRuta);
});

// 7) Ruta inexistente: error controlado porque falta el dato principal.
resultado('7. Ruta inexistente (error controlado, dato principal ausente)', function () use ($repo) {
    $proveedores = [];
    $planificador = new PlanificadorRuta($repo, $proveedores);
    return $planificador->preparar(999999);
});

echo "Bateria de pruebas Manual 12 completada.\n";
