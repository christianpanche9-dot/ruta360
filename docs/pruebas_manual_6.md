# Pruebas — Manual 6 (Configuración sin modificar el código)

Matriz de pruebas pedida en 6.17, con evidencia real de `ruta360_m6`
(vhost `ruta360-m6.local:18080`). Todas las pruebas se hicieron sobre el
proyecto ya en marcha, restaurando la configuración original después de
cada una.

| Caso | Cambio | Resultado esperado | Resultado obtenido | Evidencia |
|---|---|---|---|---|
| D1 | Configuración de desarrollo | Página y consulta correctas | Cumplido | ver más abajo |
| P1 | Base `ruta360_test` | Datos de prueba; no modifica desarrollo | Cumplido | ver más abajo |
| X1 | Falta `config.local.php` | Fallo temprano y mensaje útil | Cumplido | ver más abajo |
| X2 | Contraseña `CAMBIAR` | Validación impide conectar | Cumplido (dos veces) | ver más abajo |
| PR1 | `debug=false` | No aparece traza técnica | Cumplido | ver más abajo |
| E1 | ZIP de entrega | Incluye ejemplo y excluye local | Cumplido | ver más abajo |

## D1 — Configuración de desarrollo

Con la configuración real del equipo (`config.local.php`: `db.name =
ruta360vs1`, `api.base = http://127.0.0.1:18080/api`), tras reiniciar
Apache:

- `index.php` → HTTP 200, lista de ciudades cargada desde la BD vía
  `conexion.php` → `bootstrap.php` → `config()`.
- `api/rutas.php` → HTTP 200, `"total": 7`, datos reales.
- `rutas.php` (público) → HTTP 200, listado con filtros.
- `ver_ruta.php?id_ruta=2` → ficha completa con los tres servicios
  externos (meteorología, transporte, distancia SOAP corregida).

Ningún dato sensible (contraseña, token) aparece en ninguna de las
respuestas HTTP. Log del vhost limpio en todos los casos.

## P1 — Base de pruebas (`ruta360_test`)

`ruta360_test` existe de verdad en el MySQL local, con el mismo esquema
que `ruta360vs1` (`api_tokens`, `ciudades`, `puntos_interes`, `rutas`,
`usuarios`) y datos distintos: 6 ciudades y 2 rutas (frente a las
ciudades/7 rutas de desarrollo).

1. Se cambió solo `db.name` en `config.local.php`, de `ruta360vs1` a
   `ruta360_test` (ningún otro archivo tocado).
2. `api/rutas.php` devolvió `"total": 1` con `"titulo": "Barcelona
   modernista"` — un conjunto de datos claramente distinto al de
   desarrollo. (El total no coincide con el conteo bruto de la tabla,
   2 filas, porque la consulta filtra `activa = 1` en ruta y ciudad; no
   se investigó más a fondo por no ser parte de este manual.)
3. Se restauró `db.name` a `ruta360vs1`.
4. `api/rutas.php` volvió a devolver `"total": 7` — la base de
   desarrollo no se modificó ni se vio afectada por haber apuntado
   temporalmente a la de pruebas.

Cambiar de entorno fue un solo valor en un solo archivo, sin tocar
ninguna lógica.

## X1 — Falta `config.local.php`

1. Se movió `config/config.local.php` fuera del proyecto (`mv` a
   `/tmp`).
2. `index.php` → HTTP 500. Cuerpo de la respuesta: únicamente `No se ha
   podido iniciar la aplicación: revisa la configuración.` (sin detalles).
3. `php_error_log`: `bootstrap: configuración inválida - Falta
   config/config.local.php. Copia config.example.php y complétalo (ver
   README.md).` — mensaje útil, dice exactamente qué falta y qué hacer,
   solo visible en el servidor.
4. Se restauró el archivo. `index.php` volvió a HTTP 200 sin reiniciar
   Apache (la configuración se valida en cada petición).

## X2 — Contraseña `CAMBIAR`

Ver `docs/incidencia_manual_6.md` — se hizo dos veces: una vez como
incidencia real no planificada durante las pruebas (con `db.password`
vacío, que resultó ser un caso aparte, ver más abajo) y otra vez como el
ejercicio guiado exacto del manual (6.16), sustituyendo `db.password` por
`'CAMBIAR'`.

Resultado en ambos casos: HTTP 500, mensaje genérico al navegador (`No se
ha podido iniciar la aplicación: revisa la configuración.`), detalle
específico solo en `php_error_log` (`Falta configuración de base de
datos: db.password.`), recuperación limpia al restaurar el valor
original.

## PR1 — `debug=false`

Con un script temporal que provoca un aviso de PHP a propósito
(`Undefined variable`):

- `app.debug = true`: el navegador recibe el aviso completo de PHP con
  archivo y número de línea.
- `app.debug = false`: el navegador no recibe nada de ese aviso (línea en
  blanco), pero `php_error_log` sigue registrando el mismo aviso las dos
  veces — `error_reporting(E_ALL)` no cambia con `app.debug`, solo
  `display_errors`.

Cambiar `debug` solo afectó lo que se muestra al navegador, tal como pide
6.15.4 ("observa solo el cambio previsto").

## E1 — ZIP de entrega

```
zip -r ruta360_entrega_6.zip ruta360_m6 \
  -x "ruta360_m6/config/config.local.php" \
  -x "ruta360_m6/.git/*" \
  -x "ruta360_m6/docs/historico/*" \
  -x "ruta360_m6/storage/cache/*.json" \
  -x "*.DS_Store" \
  -x "ruta360_m6/public/_debug_*"
```

Resultado: 79 archivos, 180684 bytes. Verificado con `unzip -l | grep`:
ni `config.local.php`, ni `.git/`, ni `docs/historico/`, ni `.DS_Store`,
ni ningún script `_debug_*` aparecen en el paquete. `config/
config.example.php` sí está incluido (72 archivos verificados en el
primer intento tenían el script de diagnóstico de PR1 colado por
accidente — se detectó en la revisión del listado, se borró el archivo
del proyecto y se rehizo el ZIP antes de darlo por bueno).

A diferencia del paquete del Manual 5 (que se construyó antes de que el
proyecto tuviera repositorio git), este ZIP sí necesita excluir `.git/`
explícitamente, porque `ruta360_m6` nació ya con historial heredado.

## Nota sobre incidencias no planificadas

Además de los seis casos de la matriz, las pruebas de esta fase
descubrieron y corrigieron un bug real de la propia Fase B (colisión de
la variable `$config` entre `bootstrap.php` y `public/ver_ruta.php`),
documentado en detalle en `docs/incidencia_manual_6.md`. No estaba en el
plan del manual, pero es la evidencia más honesta de que estas pruebas
sirvieron para algo más que confirmar lo ya esperado.
