<?php
declare(strict_types=1);

require_once __DIR__ . '/ProveedorExterno.php';
require_once __DIR__ . '/ResultadoExterno.php';
require_once __DIR__ . '/ServicioExternoException.php';
require_once __DIR__ . '/transporte_cliente.php';

/**
 * El proveedor de transporte devuelve estimated_minutes y alerts.
 * Ruta360 quiere duracion_minutos e incidencias: el adaptador
 * traduce el contrato externo al interno (Manual 12, 12.6-12.7).
 */
final class AdaptadorTransporte implements ProveedorExterno
{
    public function __construct(private array $config)
    {
    }

    public function nombre(): string
    {
        return 'transporte';
    }

    public function consultar(array $contexto): ResultadoExterno
    {
        $inicio = hrtime(true);
        $json = solicitarJsonTransporte(
            $this->config,
            (string) $contexto['origen']['nombre'],
            (string) $contexto['destino']['nombre']
        );

        if (!isset($json['estimated_minutes'])) {
            throw new ServicioExternoException(
                'contenido', 'Falta estimated_minutes.'
            );
        }

        return new ResultadoExterno(true, $this->nombre(), [
            'duracion_minutos' => (int) $json['estimated_minutes'],
            'incidencias' => array_values($json['alerts'] ?? [])
        ], 'servicio', null, (int) ((hrtime(true) - $inicio) / 1_000_000));
    }
}
