<?php
declare(strict_types=1);

require_once __DIR__ . '/ProveedorExterno.php';
require_once __DIR__ . '/ResultadoExterno.php';

/**
 * Un doble que sustituye temporalmente a un proveedor real. Implementa
 * el mismo contrato y devuelve un resultado preparado (o lanza una
 * excepcion preparada) para poder probar el planificador sin depender
 * de servicios externos reales (Manual 12, 12.27-12.28).
 */
final class ProveedorSimulado implements ProveedorExterno
{
    public function __construct(
        private string $id,
        private ResultadoExterno|Throwable $respuesta,
        private int $retardoMs = 0
    ) {
    }

    public function nombre(): string
    {
        return $this->id;
    }

    public function consultar(array $contexto): ResultadoExterno
    {
        if ($this->retardoMs > 0) {
            usleep($this->retardoMs * 1000);
        }
        if ($this->respuesta instanceof Throwable) {
            throw $this->respuesta;
        }

        return $this->respuesta;
    }
}
