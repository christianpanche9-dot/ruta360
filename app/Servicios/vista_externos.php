<?php
declare(strict_types=1);

/**
 * La vista no conoce URLs externas, WSDL, claves ni estructuras
 * especificas de un proveedor (Manual 12, REGLA DE DISEÑO en 12.2).
 * Solo sabe interpretar el contrato comun ResultadoExterno y, para
 * cada nombre de proveedor ya normalizado, como mostrar su bloque de
 * datos.
 */
function mostrarDatosExterno(string $proveedor, array $datos): void
{
    switch ($proveedor) {
        case 'meteorologia':
            echo '<p>' . htmlspecialchars((string) $datos['temperatura']) . ' °C</p>';
            break;

        case 'transporte':
            echo '<p>Duración estimada: '
                . htmlspecialchars((string) $datos['duracion_minutos']) . ' min</p>';
            if (!empty($datos['incidencias'])) {
                echo '<ul class="incidencias">';
                foreach ($datos['incidencias'] as $incidencia) {
                    echo '<li>' . htmlspecialchars((string) $incidencia) . '</li>';
                }
                echo '</ul>';
            }
            break;

        case 'distancias':
            echo '<p>Distancia oficial: '
                . htmlspecialchars((string) $datos['distancia_km']) . ' km</p>';
            break;
    }
}

function nombreLegibleProveedor(string $proveedor): string
{
    return match ($proveedor) {
        'meteorologia' => 'Meteorología',
        'transporte' => 'Transporte',
        'distancias' => 'Distancia oficial',
        default => ucfirst($proveedor)
    };
}
