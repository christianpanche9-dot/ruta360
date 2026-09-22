<?php
declare(strict_types=1);

/**
 * Punto de arranque común (Manual 5, 5.9 y 5.16; Manual 6, 6.10).
 *
 * Único lugar que carga, combina y valida la configuración
 * (config/validar_config.php) y expone la función config() para
 * leerla. El resto de la aplicación deja de incluir
 * config/config.local.php por su cuenta: hasta este manual,
 * config/conexion.php, config/config_api.php y
 * app/Servicios/cliente_rutas.php lo hacían cada uno por separado,
 * con su propia copia del mismo mecanismo de fallo (ver
 * docs/inventario_configuracion.md, hallazgo principal).
 *
 * También centraliza la única dependencia que comparten casi todos
 * los puntos de entrada de public/: la sesión y las funciones de
 * seguridad web (Manual 5, 5.9).
 */

/**
 * Acceso de solo lectura a la configuración ya validada.
 *
 * La configuración se guarda en una variable static LOCAL a esta
 * función, nunca en una variable global $config. La primera versión
 * de este archivo sí usaba `global $config`, y eso provocó una
 * incidencia real durante las pruebas de la Fase C: public/ver_ruta.php
 * (código anterior a este manual, sin tocar) hace
 * `$config = require __DIR__.'/../config/servicios.php';` en su propio
 * nivel superior. Como bootstrap.php se incluye también desde ese
 * mismo nivel superior, esa asignación pisaba en silencio la
 * configuración validada de bootstrap.php con el array de
 * config/servicios.php (que no tiene claves 'app'/'db'/'api'):
 * config() seguía funcionando sin lanzar ningún error, pero leyendo el
 * array equivocado, así que config('api','base') devolvía null sin
 * ningún aviso (ver docs/incidencia_manual_6.md). Una variable static
 * dentro de la función es inmune a esto: ningún archivo externo puede
 * verla ni sobrescribirla, sea cual sea el nombre que use para sus
 * propias variables.
 *
 * config() nunca lanza ni imprime nada: si la clave no existe, o el
 * grupo no existe, devuelve null, porque a este punto ya sabemos que
 * la configuración es válida y un null aquí es un error de
 * programación (una clave mal escrita), no un problema de
 * configuración.
 */
function config(string $grupo, string $clave): mixed
{
    static $configuracion = null;

    if ($configuracion === null) {
        try {
            $configuracion = require __DIR__ . '/validar_config.php';
        } catch (Throwable $e) {
            // El mensaje dice QUÉ falta (ver validar_config.php); nunca
            // el valor, ni la excepción completa, que podría contener
            // datos de conexión en su traza.
            error_log('bootstrap: configuración inválida - ' . $e->getMessage());
            http_response_code(500);
            exit('No se ha podido iniciar la aplicación: revisa la configuración.');
        }
    }

    return $configuracion[$grupo][$clave] ?? null;
}

// Fuerza la carga y validación de la configuración en cuanto se
// incluye bootstrap.php (falla rápido si es inválida), en vez de
// esperar a la primera llamada real a config() desde el resto de la
// aplicación.
config('app', 'env');

/**
 * Modo diagnóstico por entorno (Manual 6, 6.13). En producción se
 * siguen registrando los errores (error_reporting no cambia), pero
 * no se muestran al navegador: app.debug no autoriza a imprimir la
 * configuración, el DSN completo ni una excepción con credenciales.
 */
if (config('app', 'debug') === true) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}
error_reporting(E_ALL);

require_once __DIR__ . '/../app/Utilidades/registro.php';
require_once __DIR__ . '/../app/Utilidades/seguridad_web.php';
