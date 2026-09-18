<?php
declare(strict_types=1);

require_once __DIR__ . '/../Servicios/cliente_rutas.php';

/**
 * Aisla al planificador de como se obtienen los datos internos.
 * Reutiliza la API interna de Ruta360 (api/ruta.php via
 * obtenerRutaApi()) en lugar de duplicar el acceso a MySQL: el
 * planificador solo conoce este contrato (Manual 12, 12.2 y 12.37).
 */
final class RepositorioRuta
{
    public function buscarPorId(int $idRuta): ?array
    {
        $resultado = obtenerRutaApi($idRuta);
        if (!($resultado['ok'] ?? false)) {
            return null;
        }

        return $resultado['datos'];
    }
}
