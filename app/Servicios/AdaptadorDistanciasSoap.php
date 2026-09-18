<?php
declare(strict_types=1);

require_once __DIR__ . '/ProveedorExterno.php';
require_once __DIR__ . '/ResultadoExterno.php';
require_once __DIR__ . '/ServicioExternoException.php';

/**
 * connection_timeout limita el establecimiento de la conexion, pero
 * no una respuesta lenta; por eso ajustamos tambien
 * default_socket_timeout y lo restauramos al terminar (Manual 12,
 * 12.11 y 12.15).
 *
 * 'location' (Manual 6, hallazgo secundario, ver
 * docs/inventario_configuracion.md): sin esta opción, SoapClient usa
 * el <soap:address location="..."> que trae escrito el propio WSDL
 * como destino real de la llamada, en vez de la URL con la que se
 * cargó ese WSDL. En este proyecto ese valor había quedado obsoleto
 * (apuntaba a ruta360vs1/soap/distancias_server.php, una carpeta que
 * ya no existe tras la reorganización del Manual 5) y el fallo pasaba
 * desapercibido: el adaptador degradaba en silencio en vez de avisar
 * de una URL mal configurada. Fijar 'location' junto al propio WSDL
 * (mismo directorio, servido por el mismo Alias) hace que la llamada
 * real dependa de dónde vive el servicio en cada entorno, no de un
 * valor grabado dentro del WSDL.
 */
function crearClienteSoap(string $wsdl): SoapClient
{
    return new SoapClient($wsdl, [
        'exceptions' => true,
        'connection_timeout' => 2,
        'cache_wsdl' => WSDL_CACHE_DISK,
        'trace' => false,
        'keep_alive' => false,
        'location' => dirname($wsdl) . '/distancias_server.php'
    ]);
}

final class AdaptadorDistanciasSoap implements ProveedorExterno
{
    public function __construct(private string $wsdl)
    {
    }

    public function nombre(): string
    {
        return 'distancias';
    }

    public function consultar(array $contexto): ResultadoExterno
    {
        $cliente = null;
        $anterior = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', '5');
        $inicio = hrtime(true);

        try {
            $cliente = crearClienteSoap($this->wsdl);
            $r = $cliente->__soapCall('CalcularDistancia', [[
                'Origen' => $contexto['origen']['coordenadas'],
                'Destino' => $contexto['destino']['coordenadas']
            ]]);

            $km = $r->CalcularDistanciaResult->DistanceKm ?? null;
            if (!is_numeric($km) || (float) $km < 0) {
                throw new ServicioExternoException(
                    'contenido', 'Distancia SOAP no válida.'
                );
            }

            return new ResultadoExterno(true, $this->nombre(), [
                'distancia_km' => (float) $km
            ], 'servicio', null, (int) ((hrtime(true) - $inicio) / 1_000_000));
        } catch (SoapFault $e) {
            throw new ServicioExternoException(
                'soap', 'Fallo del servicio SOAP.'
            );
        } finally {
            if ($anterior !== false) {
                ini_set('default_socket_timeout', (string) $anterior);
            }
            $cliente = null;
        }
    }
}
