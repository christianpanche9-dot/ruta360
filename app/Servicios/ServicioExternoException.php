<?php
declare(strict_types=1);

final class ServicioExternoException extends RuntimeException
{
    public function __construct(
        public readonly string $categoria,
        string $mensaje,
        public readonly int $codigoHttp = 0,
        public readonly int $codigoCurl = 0
    ) {
        parent::__construct($mensaje);
    }
}
