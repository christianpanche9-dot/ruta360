<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

/**
 * Comprobación rápida de la configuración (Manual 5, 5.9; Manual 6,
 * 6.15 Fase C). Antes de este manual, este script leía
 * config/config.local.php por su cuenta, con las claves planas del
 * formato anterior (config['entorno'], config['app_url']...) — un
 * mecanismo de fallo más, independiente del de config/conexion.php,
 * config/config_api.php y app/Servicios/cliente_rutas.php (ver
 * docs/inventario_configuracion.md, hallazgo principal). Con esas
 * claves ya no existen en el config.local.php nuevo (formato anidado
 * app/db/api), el script daba un falso negativo: decía "no se ha
 * podido cargar la configuración" con una configuración perfectamente
 * válida.
 *
 * Ahora solo usa bootstrap.php: si la configuración es inválida,
 * bootstrap.php ya se encarga de responder con el mensaje genérico y
 * registrar el motivo real (ver config/bootstrap.php), así que este
 * script no necesita su propio try/catch — es la prueba de que ese
 * único punto de carga y validación funciona de verdad.
 */
require __DIR__ . '/../config/bootstrap.php';

echo "Configuración cargada y validada correctamente.\n";
echo "Entorno: " . config('app', 'env') . "\n";
echo "App URL: " . config('app', 'url') . "\n";
echo "DB: " . config('db', 'name') . " (usuario: " . config('db', 'user') . ")\n";
echo "Mostrar errores (debug): " . (config('app', 'debug') ? 'sí' : 'no') . "\n";
echo "API interna: " . config('api', 'base') . "\n";
