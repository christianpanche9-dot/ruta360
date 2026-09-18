# Plan y registro de pruebas — Manual 5

Casos, resultados y evidencias de todo el proceso de reorganización.
Este archivo es el punto de entrada; cada fase tiene su detalle
completo en el documento que se enlaza, para no duplicar contenido.

## Resumen por fase

| Fase | Qué se probó | Resultado | Detalle |
|---|---|---|---|
| A — Línea de base | 4 casos sobre `ruta360vs1` antes de tocar nada: `rutas.php`, `ver_ruta.php?id_ruta=1`, `api/ciudades.php`, `libros.php?q=quijote` | Los 4 correctos (7 rutas, "Barcelona modernista", HTTP 200, 10 libros) | `docs/inventario_antes.md` |
| B — Reorganización de archivos | Movimiento de cada archivo a su carpeta por responsabilidad, sin tocar código todavía | Estructura `app/config/docs/public/sql/storage/tests` creada; la app queda temporalmente rota (esperado, se corrige en Fase C) | `docs/inventario_antes.md` |
| C — Corrección de rutas | `php -l` en todos los archivos movidos; `php public/api/ciudades.php`; `php tests/_test_config.php`; smoke test de la cadena de requires | Todo correcto tras corregir cada `require`/`include`; se encontró y corrigió un bug real no detectado por el grep de require/include (`cliente_rutas.php`, `obtenerBaseApiInterna()`) | `docs/correccion_rutas.md` |
| D — VirtualHost independiente | Se repiten los 4 casos de la Fase A, ya con `DocumentRoot` en `public/` y sobre `http://ruta360-m5.local` | Los 4 casos, mismo resultado que la línea base; se encontró y corrigió un segundo bug de ruta (`cache_meteo.php`, `rutaCache()`) y un problema de DNS interno (ver detalle) | `docs/correccion_rutas.md`, sección "Fase D" |
| Incidencia controlada (5.18) | Rotura deliberada de un `../` en `public/rutas.php`, diagnóstico solo con el mensaje de error, corrección y verificación | HTTP 500 → 200; "Se han encontrado 7 rutas" recuperado; sin nuevas entradas en el log tras la corrección | `docs/incidencia_5_18.md` |
| Paquete de entrega (5.20) | Extracción del `.zip` en una carpeta limpia, configuración siguiendo el propio README, prueba CLI y prueba HTTP completa repuntando temporalmente el vhost real | Mismo resultado exacto que la Fase D: 200, "7 rutas", los tres proveedores externos funcionando, caché regenerada, log limpio | `docs/paquete_entrega.md` |

## Casos de prueba, con criterio de aceptación

| # | Caso | Comando | Criterio de aceptación |
|---|---|---|---|
| 1 | Listado de rutas | `curl http://ruta360-m5.local/rutas.php` | HTTP 200 y contiene "Se han encontrado N rutas" |
| 2 | Detalle de ruta con proveedores externos | `curl http://ruta360-m5.local/ver_ruta.php?id_ruta=1` | HTTP 200 y aparecen las tres secciones: meteorología, transporte, distancia oficial |
| 3 | Endpoint JSON interno | `curl http://ruta360-m5.local/api/ciudades.php` | HTTP 200 |
| 4 | Búsqueda de libros (API externa real) | `curl "http://ruta360-m5.local/libros.php?q=quijote"` | Contiene "Se han encontrado N libro(s)" |
| 5 | Configuración cargable sin Apache | `php tests/_test_config.php` | "Configuración cargada correctamente." y los 5 valores esperados |
| 6 | Alias de desarrollo accesibles | `curl http://ruta360-m5.local/_soap/distancias.wsdl` | HTTP 200 |
| 7 | Registro de errores limpio | `tail php_error_log` tras cada prueba | Sin entradas nuevas no explicadas |

## Nota sobre el nombre de este archivo

El manual (5.16, paso 20) pide anotar resultado y evidencia en
`docs/pruebas_manual_5.md` desde la propia Fase C, antes incluso de
la Fase D. En la práctica, la evidencia se fue registrando por fase
en archivos con nombre propio (`correccion_rutas.md`,
`incidencia_5_18.md`, `paquete_entrega.md`) a medida que cada fase se
cerraba, y este archivo consolidado se escribió al final, como
índice de todos ellos. El contenido pedido existe y está completo;
lo que se corrige aquí es únicamente la falta del archivo con el
nombre exacto que pide el manual.
