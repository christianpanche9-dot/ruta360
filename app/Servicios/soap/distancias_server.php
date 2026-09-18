<?php
declare(strict_types=1);

/**
 * Servidor SOAP local que sustituye a un proveedor real de distancias
 * oficiales (Manual 12, 12.14). Admite parametros de consulta para
 * simular un SoapFault o una respuesta incompleta, usados por los
 * WSDL de prueba distancias_fallo.wsdl y distancias_sin_distancia.wsdl.
 */

ini_set('soap.wsdl_cache_enabled', '0');

final class DistanciasServicePortType
{
    /**
     * El WSDL usa estilo document/literal "wrapped": el elemento
     * CalcularDistancia agrupa Origen y Destino en un unico objeto de
     * parametros. SoapServer entrega ese objeto como un solo
     * argumento, no como dos argumentos posicionales.
     */
    public function CalcularDistancia($parametros): array
    {
        if (isset($_GET['fallo'])) {
            throw new SoapFault('Server', 'Servicio de distancias no disponible (simulado).');
        }

        if (isset($_GET['sin_distancia'])) {
            return ['CalcularDistanciaResult' => []];
        }

        $Origen = (string) ($parametros->Origen ?? '');
        $Destino = (string) ($parametros->Destino ?? '');

        $origenPartes = array_map('floatval', explode(',', $Origen));
        $destinoPartes = array_map('floatval', explode(',', $Destino));

        if (count($origenPartes) !== 2 || count($destinoPartes) !== 2) {
            throw new SoapFault('Client', 'Origen o destino con formato no válido.');
        }

        [$lat1, $lon1] = $origenPartes;
        [$lat2, $lon2] = $destinoPartes;

        $distancia = $this->haversine($lat1, $lon1, $lat2, $lon2);

        return ['CalcularDistanciaResult' => ['DistanceKm' => round($distancia, 2)]];
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $radioTierraKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $radioTierraKm * $c;
    }
}

$wsdl = __DIR__ . '/distancias.wsdl';
$servidor = new SoapServer($wsdl, ['cache_wsdl' => WSDL_CACHE_NONE]);
$servidor->setClass(DistanciasServicePortType::class);
$servidor->handle();
