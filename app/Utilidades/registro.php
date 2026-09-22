<?php
declare(strict_types=1);

/**
 * Registro minimo de la aplicacion (Manual 11).
 * Nunca registra contrasenas, tokens, cookies completas ni datos
 * personales innecesarios.
 */
function registrar(string $nivel, string $mensaje, array $contexto = []): void
{
    $linea = sprintf(
        "%s [%s] %s %s\n",
        date('Y-m-d H:i:s'),
        $nivel,
        $mensaje,
        json_encode($contexto, JSON_UNESCAPED_UNICODE)
    );

    $directorio = __DIR__ . '/../../storage/logs';
    if (!is_dir($directorio)) {
        @mkdir($directorio, 0775, true);
    }

    error_log($linea, 3, $directorio . '/app.log');
}
