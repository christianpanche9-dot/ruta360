<?php
declare(strict_types=1);

require_once __DIR__ . '/ResultadoExterno.php';
require_once __DIR__ . '/ProveedorExterno.php';
require_once __DIR__ . '/../Repositorios/RepositorioRuta.php';

/**
 * Obtiene primero la ruta local. Despues prepara un contexto comun y
 * consulta los adaptadores por orden de prioridad, respetando un
 * presupuesto total de tiempo (Manual 12, 12.19-12.23).
 */
final class PlanificadorRuta
{
    public function __construct(
        private RepositorioRuta $rutas,
        private array $proveedores,
        private int $presupuestoMs = 7000
    ) {
    }

    public function preparar(int $idRuta): array
    {
        $ruta = $this->rutas->buscarPorId($idRuta);
        if ($ruta === null) {
            throw new RuntimeException('Ruta no encontrada.');
        }

        $contexto = $this->crearContexto($ruta);
        $externos = [];
        $inicioGlobal = hrtime(true);

        foreach ($this->proveedores as $proveedor) {
            $transcurrido = (int) ((hrtime(true) - $inicioGlobal) / 1_000_000);
            if ($transcurrido >= $this->presupuestoMs) {
                $externos[$proveedor->nombre()] = new ResultadoExterno(
                    false, $proveedor->nombre(), [], 'omitido',
                    'No se consultó por límite de tiempo'
                );
                continue;
            }
            $externos[$proveedor->nombre()] = $this->consultarSeguro($proveedor, $contexto);
        }

        return ['ruta' => $ruta, 'externos' => $externos];
    }

    /**
     * La excepcion de un proveedor termina en su propia frontera. El
     * bucle continua y conserva los resultados de las demas
     * integraciones (Manual 12, 12.20).
     */
    private function consultarSeguro(ProveedorExterno $proveedor, array $contexto): ResultadoExterno
    {
        try {
            return $proveedor->consultar($contexto);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[integracion] proveedor=%s tipo=%s mensaje=%s',
                $proveedor->nombre(), $e::class, $e->getMessage()
            ));

            return new ResultadoExterno(
                false, $proveedor->nombre(), [], 'sin_datos',
                'Información temporalmente no disponible'
            );
        }
    }

    /**
     * Ruta360 no guarda un punto de destino geografico independiente
     * (sus rutas son recorridos dentro de una misma ciudad). Para que
     * el adaptador SOAP pueda validar una distancia de forma
     * independiente, derivamos un punto sintetico situado a la
     * distancia local ya conocida, a partir del centro de la ciudad.
     */
    private function crearContexto(array $ruta): array
    {
        $lat = (float) $ruta['ciudad']['latitud'];
        $lon = (float) $ruta['ciudad']['longitud'];
        $distanciaLocalKm = (float) ($ruta['distancia_km'] ?? 1.0);

        $desplazamientoGrados = max($distanciaLocalKm, 0.1) / 111.0;
        $latDestino = $lat + $desplazamientoGrados;

        return [
            'latitud' => $lat,
            'longitud' => $lon,
            'origen' => [
                'nombre' => $ruta['ciudad']['nombre'],
                'coordenadas' => $lat . ',' . $lon
            ],
            'destino' => [
                'nombre' => $ruta['ciudad']['nombre'] . ' (fin de ruta)',
                'coordenadas' => $latDestino . ',' . $lon
            ]
        ];
    }
}
