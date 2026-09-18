<?php
declare(strict_types=1);

require_once __DIR__ . '/ProveedorExterno.php';
require_once __DIR__ . '/ResultadoExterno.php';
require_once __DIR__ . '/tiempo_resiliente.php';

/**
 * Traduce la meteorologia resiliente del Manual 11 (timeouts,
 * reintentos y cache de archivo) al contrato comun ProveedorExterno.
 * No duplicamos esa logica: solo adaptamos su resultado.
 */
final class AdaptadorMeteorologia implements ProveedorExterno
{
    public function __construct(private array $config)
    {
    }

    public function nombre(): string
    {
        return 'meteorologia';
    }

    public function consultar(array $contexto): ResultadoExterno
    {
        $inicio = hrtime(true);
        $r = obtenerTiempoResiliente(
            (float) $contexto['latitud'],
            (float) $contexto['longitud']
        );

        return new ResultadoExterno(
            $r['disponible'],
            $this->nombre(),
            $r['datos'] ?? [],
            $r['origen'],
            $r['disponible'] ? null : 'Meteorología no disponible',
            (int) ((hrtime(true) - $inicio) / 1_000_000)
        );
    }
}
