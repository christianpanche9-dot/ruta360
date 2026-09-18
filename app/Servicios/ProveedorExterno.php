<?php
declare(strict_types=1);

interface ProveedorExterno
{
    public function nombre(): string;

    public function consultar(array $contexto): ResultadoExterno;
}
