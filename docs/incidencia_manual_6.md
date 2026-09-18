# Incidencia real: colisión de la variable `$config` (Fase B/C, Manual 6)

## Resumen

Durante las pruebas de humo de la Fase B (externalizar la configuración),
`public/rutas.php` y `public/ver_ruta.php` fallaban de forma silenciosa —
sin ningún error PHP, con HTTP 200 y una página bien formada que decía
"La ruta no existe o no está disponible" — mientras que el resto de
páginas que también dependían de la nueva configuración (`index.php`,
`api/rutas.php`, `api/ruta.php`) funcionaban perfectamente. La causa fue
una colisión de nombres entre una variable global que `config/bootstrap.php`
usaba para guardar la configuración validada y una variable con el mismo
nombre que `public/ver_ruta.php` ya usaba, desde antes de este manual,
para otra cosa.

## Síntoma

- `curl http://ruta360-m6.local:18080/ver_ruta.php?id_ruta=2` → HTTP 200,
  HTML válido, pero "No se ha podido cargar la ruta / La ruta no existe o
  no está disponible", pese a que la ruta 2 existe (confirmado llamando a
  `api/ruta.php?id_ruta=2` directamente, que sí la devolvía).
- `public/rutas.php` (el listado) también quedaba con la lista de rutas
  vacía por el mismo motivo, aunque la página cargaba sin error visible.
- El log del vhost (`ruta360-m6-error.log`) no mostraba nada: ni avisos ni
  errores. Esto llevó a un primer diagnóstico erróneo (ver más abajo).

## Diagnóstico

1. Se confirmó que `api/ruta.php?id_ruta=2` (que usa `config/conexion.php`
   directamente, sin pasar por `cliente_rutas.php`) devolvía la ruta
   correctamente — descartando un problema de base de datos.
2. Se escribió un script de diagnóstico temporal
   (`public/_debug_curl_test.php`, borrado al cerrar esta incidencia) que
   reproducía la llamada HTTP interna que hace `obtenerRutaApi()`. La
   primera versión reimplementaba la llamada a mano y funcionaba —lo cual
   resultó ser una pista falsa, porque no probaba la función real.
3. Al revisar el `php_error_log` general (no el del vhost: las llamadas a
   `error_log()` desde PHP van al `error_log` de `php.ini`, no al
   `ErrorLog` de Apache — primera lección de esta incidencia), apareció:
   `cliente_rutas: no se ha podido determinar la API interna (api.base
   vacío o no definido).` — el propio mensaje que añadimos en
   `obtenerBaseApiInterna()` (Fase B) para este caso.
4. Se reescribió el script de diagnóstico para llamar a las funciones
   reales de `cliente_rutas.php` (`obtenerBaseApiInterna()`,
   `obtenerRutaApi()`, `obtenerRutas()`) en vez de reimplementarlas, lo
   que sí reprodujo el fallo real: `config('api','base')` devolvía `null`
   cuando se llamaba desde dentro de esa cadena, aunque devolvía el valor
   correcto cuando se llamaba justo después de incluir `bootstrap.php`
   desde un script nuevo.
5. Revisando `public/ver_ruta.php` línea por línea se encontró la causa:

   ```php
   require_once __DIR__ . '/../config/bootstrap.php';
   // ...
   } else {
       $config = require __DIR__ . '/../config/servicios.php';
       $proveedores = [
           new AdaptadorMeteorologia($config['meteorologia']),
           // ...
   ```

   `ver_ruta.php` (código anterior a este manual, sin tocar) reutiliza el
   nombre `$config` para el array de `config/servicios.php`
   (meteorología/transporte/distancias SOAP), en su propio nivel
   superior. La primera versión de `config/bootstrap.php` guardaba la
   configuración validada así:

   ```php
   $config = require __DIR__ . '/validar_config.php';
   // ...
   function config(string $grupo, string $clave): mixed
   {
       global $config;
       return $config[$grupo][$clave] ?? null;
   }
   ```

   Como `bootstrap.php` se incluye desde el nivel superior de
   `ver_ruta.php`, su `$config` es la misma variable global que
   `ver_ruta.php` reasigna dos líneas más abajo. La segunda asignación
   pisaba en silencio la primera: `config()` seguía funcionando sin
   lanzar ningún error, pero leyendo el array de `servicios.php`
   (`meteorologia`/`transporte`/`distancias_soap`/`libros`) en vez del de
   `app`/`db`/`api`, así que `config('api','base')` devolvía `null` sin
   ningún aviso.

## Causa raíz

Guardar la configuración validada en una variable global genérica
(`$config`) es frágil en un proyecto real: cualquier otro archivo que se
incluya en el mismo ámbito de nivel superior y use ese mismo nombre —algo
razonable, ya que `$config` es un nombre obvio para "la configuración de
esto"— la sobrescribe sin ningún aviso. El ejemplo del manual (6.10) es
correcto para un proyecto mínimo de una sola página, pero no es seguro al
aplicarlo tal cual a un proyecto con varios puntos de entrada y varios
archivos de configuración distintos (`config.local.php` para la app y
`servicios.php` para los proveedores externos), como ya era el caso aquí
desde el Manual 12.

## Corrección

Se cambió `config/bootstrap.php` para que la configuración validada se
guarde en una variable `static` local a la propia función `config()`, en
vez de en una variable global:

```php
function config(string $grupo, string $clave): mixed
{
    static $configuracion = null;

    if ($configuracion === null) {
        try {
            $configuracion = require __DIR__ . '/validar_config.php';
        } catch (Throwable $e) {
            error_log('bootstrap: configuración inválida - ' . $e->getMessage());
            http_response_code(500);
            exit('No se ha podido iniciar la aplicación: revisa la configuración.');
        }
    }

    return $configuracion[$grupo][$clave] ?? null;
}

config('app', 'env'); // fuerza la carga inmediata, no perezosa
```

Una variable `static` dentro de una función solo es visible y modificable
desde dentro de esa misma función: ningún otro archivo, sea cual sea el
nombre que use para sus propias variables, puede leerla ni sobrescribirla.
Esto elimina la clase completa de colisión, no solo el caso concreto de
`ver_ruta.php`.

## Verificación

Tras el cambio, con el mismo script de diagnóstico llamando a las
funciones reales:

```
--- obtenerBaseApiInterna() ---
base = http://127.0.0.1:18080/api

--- obtenerRutaApi(2) ---
array (
  'ok' => true,
  'estado' => 200,
  'datos' => array ( 'id_ruta' => 2, 'titulo' => 'Barcelona junto al mar', ... ),
)

--- obtenerRutas() (la que usa rutas.php) ---
ok = true
estado = 200
error = NULL
total = 7
```

Y `ver_ruta.php?id_ruta=2` mostró la ficha completa, incluidos los tres
servicios externos (meteorología, transporte y la distancia SOAP
corregida en el hallazgo secundario de
`docs/inventario_configuracion.md`), con el log del vhost y el
`php_error_log` limpios tras la petición.

También se reprodujo la colisión de forma aislada, fuera del proyecto,
para confirmar que la causa identificada era exactamente esa y que la
corrección la resuelve en general (no solo para `ver_ruta.php`):

```php
require "bootstrap.php";
config('api', 'base'); // valor correcto

// Simula lo que hacía ver_ruta.php: otro script reasigna $config.
$config = ['meteorologia' => [], 'transporte' => ['url' => 'x']];

config('api', 'base'); // con el bootstrap.php corregido, sigue devolviendo el valor correcto
config('db', 'host');  // igual
```

## Lección para el resto del proyecto

- `error_log()` sin argumentos adicionales escribe en el `error_log` de
  `php.ini` (aquí, `php_error_log`), no en el `ErrorLog` del vhost de
  Apache. Diagnosticar solo con el log del vhost puede hacer parecer que
  "no hay ningún error" cuando sí lo hay.
- Guardar estado compartido en una variable global con un nombre genérico
  (`$config`, `$datos`, `$resultado`...) es una fuente de bugs silenciosos
  en cualquier proyecto con varios puntos de entrada que comparten el
  mismo ámbito de nivel superior. Una variable `static` dentro de la
  función que la necesita es más segura por defecto.

---

## Segundo caso: ejercicio guiado (Manual 6, 6.16)

Además de la incidencia real de arriba, se hizo también el ejercicio
guiado que pide el manual: romper `db.password` a propósito y comprobar
que el fallo es limpio.

### Procedimiento

1. Se hizo una copia de seguridad de `config/config.local.php`.
2. Se cambió `'password' => ''` por `'password' => 'CAMBIAR'` (el
   marcador de plantilla que `validar_config.php` rechaza explícitamente).
3. Se pidió `index.php`.

### Resultado

- HTTP 500.
- Cuerpo de la respuesta al navegador: únicamente
  `No se ha podido iniciar la aplicación: revisa la configuración.` — sin
  ningún dato de configuración, ni el nombre de la clave que falla, ni
  rastro de la excepción.
- `php_error_log`: `bootstrap: configuración inválida - Falta
  configuración de base de datos: db.password.` — el detalle completo
  (qué falta) queda solo en el servidor, nunca en la respuesta HTTP.
- Se restauró `config.local.php` a su valor original
  (`'password' => ''`, válido en este proyecto: MySQL local sin
  contraseña) y `index.php` volvió a responder HTTP 200 sin reiniciar
  Apache ni hacer nada más: `validar_config.php` se reevalúa en cada
  petición porque no hay ningún caché de configuración entre peticiones.

### Conclusión

El mecanismo de fallo (mensaje genérico al navegador, detalle solo en el
log del servidor) funciona como se diseñó en `validar_config.php` y
`bootstrap.php` (6.9-6.10), tanto para esta incidencia guiada como para la
incidencia real documentada arriba.
