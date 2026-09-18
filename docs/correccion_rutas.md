# Fase C — Corrección de rutas (Manual 5, 5.16 pasos 11-14)

Copia de trabajo: `ruta360_m5`. Continúa el inventario de la Fase A
(`docs/inventario_antes.md`) una vez movidos los archivos en la Fase B.

## 1. Punto de arranque común: `config/bootstrap.php`

Antes de escribirlo se revisó qué dependencias comparten realmente las
12 páginas de `public/`, en lugar de copiar el ejemplo del manual
(5.9) sin adaptarlo:

| Dependencia | Páginas que la usan |
|---|---|
| `app/Utilidades/seguridad_web.php` (sesión, roles, CSRF) | rutas, ver_ruta, nueva_ruta, editar_ruta, eliminar_ruta, login, logout (7 de 12) |
| `config/conexion.php` (PDO directo) | index, login, tiempo, y los tres endpoints de `public/api/` |

La mayoría de páginas de `public/` no tocan la base de datos
directamente: acceden a los datos a través de la API interna
(`app/Servicios/cliente_rutas.php`, incidencia 4.14 del Manual 4). Por
eso `config/bootstrap.php` centraliza únicamente `seguridad_web.php`
—la dependencia realmente común— y no `conexion.php`, para no dar a
entender en `index.php`, `login.php` o `tiempo.php` una dependencia de
sesión que no necesitan, ni sumar una dependencia de base de datos a
páginas que nunca la tuvieron (p. ej. `logout.php`).

## 2. Rutas de `require`/`include` corregidas

Todas las rutas relativas basadas en `__DIR__` se recalcularon según
la nueva posición de cada archivo. Resumen por carpeta:

- `public/*.php` → `seguridad_web.php` sustituido por
  `require_once __DIR__.'/../config/bootstrap.php'`; `servicios/*.php`
  pasa a `__DIR__.'/../app/Servicios/*.php'`.
- `public/api/*.php` → `__DIR__.'/../conexion.php'` pasa a
  `__DIR__.'/../../config/conexion.php'` (dos niveles, porque ahora
  cuelga de `public/api/`, no de la raíz).
- `app/Repositorios/RepositorioRuta.php` → `cliente_rutas.php` ahora
  vive en `app/Servicios/`, no en la misma carpeta:
  `__DIR__.'/../Servicios/cliente_rutas.php'`.
- `app/Servicios/PlanificadorRuta.php` → mismo caso a la inversa,
  `RepositorioRuta.php` vive en `app/Repositorios/`:
  `__DIR__.'/../Repositorios/RepositorioRuta.php'`.
- `app/Servicios/cliente_rutas.php` → `config_api.php` ahora vive en
  `config/`: `__DIR__.'/../../config/config_api.php'`.
- `config/config_api.php` → ya vive dentro de `config/`, así que
  `config.local.php` es un archivo hermano, no una subcarpeta:
  `__DIR__.'/config.local.php'` (antes `__DIR__.'/config/config.local.php'`).
- `tests/_test_config.php` → `__DIR__.'/../config/config.local.php'`.

### Bug encontrado durante la revisión (no solo cosmético)

`app/Servicios/cliente_rutas.php` tenía una segunda referencia a
`config.local.php`, dentro de la función `obtenerBaseApiInterna()`,
que **no aparece** en una búsqueda de `require`/`include` porque es
una ruta guardada en una variable (`$rutaConfig`) para un `require`
posterior. Seguía apuntando a `__DIR__.'/../config/config.local.php'`
—correcto solo mientras el archivo vivía en `servicios/` en la raíz—
y con el archivo ya en `app/Servicios/` le faltaba un nivel. Se
corrigió a `__DIR__.'/../../config/config.local.php'`. Sin este
cambio, `rutas.php`, `ver_ruta.php`, `nueva_ruta.php`, `editar_ruta.php`
y `eliminar_ruta.php` habrían fallado en la Fase D con un error opaco,
pese a que `php -l` no lo detecta (es un error de ruta en tiempo de
ejecución, no de sintaxis).

## 3. URLs de recursos estáticos

`href="estilos.css"` en las 9 páginas que lo usaban pasó a
`href="assets/css/estilos.css"`, reflejando el traslado a
`public/assets/css/estilos.css`.

## 4. Lo que se deja para la Fase D (no es un olvido)

Dos valores de configuración siguen apuntando al proyecto antiguo
(`ruta360vs1`):

- `config/config.local.php`, clave `api_base`.
- `config/servicios.php`, claves `transporte.url` y
  `distancias_soap.wsdl` (valores de respaldo).

No son rutas de archivo sino URLs HTTP, y su valor correcto depende
del *vhost* que se cree para `ruta360_m5` en la Fase D. Fijar ahora
una URL adivinada sería peor que dejar el mecanismo actual (con
posibilidad de override por variable de entorno, `$_ENV['...']`)
funcionando tal cual. Se actualizarán en cuanto exista la URL real.

## 5. Verificación (por CLI, sin depender todavía de un vhost)

Todas las pruebas se ejecutaron con el PHP de XAMPP
(`/Applications/XAMPP/xamppfiles/bin/php`), no con el PHP del sistema
(Homebrew), porque este último no comparte socket con la base de
datos de XAMPP y da un falso negativo de conexión.

| Prueba | Resultado |
|---|---|
| `php -l` sobre los ~50 archivos `.php` del proyecto | Sin errores de sintaxis en ninguno |
| `php public/api/ciudades.php` | Conecta con la base de datos y llega a la lógica de negocio (usando la nueva ruta a `config/conexion.php`) |
| `php tests/_test_config.php` | Carga `config/config.local.php` desde su nueva ubicación y muestra la configuración del entorno |
| `require` en cadena de `config/bootstrap.php`, `PlanificadorRuta.php`, `AdaptadorLibros.php`, `AdaptadorTransporte.php`, `AdaptadorDistanciasSoap.php`, `AdaptadorMeteorologia.php`, `vista_externos.php` | Todas las rutas resuelven, sin `Failed opening required` |

Estas pruebas comprueban que el código carga y conecta correctamente
desde la nueva estructura, pero no sustituyen a la Fase D: las 4
pruebas de referencia de la Fase A (listado de rutas, ficha de ruta,
`api/ciudades.php` por HTTP, buscador de libros) se repetirán contra
el nuevo *vhost* para confirmar que la web, servida por Apache,
funciona igual que antes de la reorganización.

# Fase D — Nuevo *vhost* y comprobación (Manual 5, 5.16 paso 15 y Fase D)

## 1. *VirtualHost* nuevo

- `ServerName ruta360-m5.local`, `DocumentRoot` apuntando a
  `ruta360_m5/public` (no a la raíz del proyecto): solo lo que debe
  ser público queda expuesto por Apache.
- Dos `Alias` explícitos, fuera del `DocumentRoot`, para dos
  necesidades de desarrollo que **no** son parte de la app pública
  pero deben responder por HTTP: `/_tests` → `tests/` (el simulador
  de transporte) y `/_soap` → `app/Servicios/soap/` (el WSDL de
  distancias). Quedan documentados como excepción, no como precedente
  para exponer más carpetas internas.
- Entrada añadida a `/etc/hosts`: `127.0.0.1 ruta360-m5.local`.

## 2. Bug real: llamadas internas por HTTP y nombres `.local`

Al repetir las 4 pruebas de la Fase A contra el nuevo *vhost*,
`rutas.php` y `ver_ruta.php` fallaban ("No se ha podido contactar con
el servicio de rutas/de libros") mientras `api/ciudades.php` y
`libros.php` funcionaban. La diferencia: los dos que fallaban hacen
una petición HTTP interna hacia sí mismos (`cliente_rutas.php` llama
a `public/api/*.php` del mismo servidor).

Diagnóstico, paso a paso:

1. El log (`php_error_log`) mostraba `name lookup timed out` — un
   fallo de resolución de nombre, no de conexión.
2. Vaciar la caché de DNS del sistema (`dscacheutil -flushcache`) no
   lo arregló.
3. Un `curl` normal desde terminal a `ruta360-m5.local` funcionaba
   perfectamente — la entrada en `/etc/hosts` era correcta.
4. Conclusión: el cURL del PHP de XAMPP para Mac no resuelve de forma
   fiable nombres `.local` personalizados **desde dentro del propio
   proceso de Apache**, aunque cualquier proceso externo (`curl`,
   `php` por CLI) sí lo haga. El proyecto original ya evitaba este
   problema usando `http://localhost/...` para estas llamadas
   internas en vez del nombre del *vhost* — no repliqué esa precaución
   al construir la URL nueva.

Solución adoptada: usar la IP literal `127.0.0.1` (no necesita
resolución) en las tres URLs internas (`api_base` en
`config.local.php`; `transporte.url` y `distancias_soap.wsdl` en
`servicios.php`), y añadir `ServerAlias 127.0.0.1` al *vhost* para que
Apache la enrute igual que el nombre.

## 3. Bug real: segundo error, mismo síntoma, causa distinta

Con `127.0.0.1` puesto, `rutas.php`/`ver_ruta.php` seguían fallando
con el mismo "name lookup timed out" — algo imposible para una IP
literal. Al comprobar el archivo en el propio Mac (`cat
config/config.local.php`), seguía teniendo el valor antiguo
(`ruta360-m5.local`): el envío del archivo corregido había fallado en
silencio (coincidió con una caída momentánea de la conexión con el
Mac). El propio "problema de red" que se investigó durante varios
pasos era, en realidad, que el cambio nunca había llegado al disco.
Lección: cuando una herramienta de envío informa éxito pero el
comportamiento no cambia, comprobar el contenido real en destino
antes de seguir depurando el síntoma.

Un efecto colateral de este mismo incidente: el bloque `<VirtualHost>`
de `ruta360-m5.local` quedó duplicado en `httpd-vhosts.conf` (el
comando que lo añadía se ejecutó dos veces). Se detectó contando
aperturas/cierres de `<VirtualHost>` y se eliminó el bloque repetido
antes de continuar.

## 4. Bug real encontrado en la verificación final: `cache_meteo.php`

Al probar `ver_ruta.php` (que sí carga con éxito), el log mostraba un
aviso no fatal: `file_put_contents(.../app/Servicios/../storage/cache/...)
Failed to open stream`. `cache_meteo.php` construye la ruta de caché
con `__DIR__.'/../storage/cache/...'` — una cadena de texto, no un
`require`, así que no apareció en la revisión por `require`/`include`
de la sección 2. Igual que con `cliente_rutas.php` (sección 2), la
ruta era correcta solo mientras el archivo vivía en `servicios/` en
la raíz; con el archivo en `app/Servicios/` le faltaba un nivel.
Corregido a `__DIR__.'/../../storage/cache/...'`.

Tras corregirlo apareció un segundo problema, ya de permisos: Apache
en XAMPP para Mac corre como el usuario `daemon` (comprobado con
`ps aux | grep httpd`), que no pertenece al grupo del propietario del
proyecto (`admin`). `storage/cache/` estaba en `755` (solo el
propietario puede escribir). Se cambió a `777` — aceptable aquí porque
solo contiene una caché de datos meteorológicos no sensibles,
regenerable en cualquier momento.

**Moraleja para el resto del proyecto:** cualquier búsqueda de rutas
rotas tras un traslado de archivos debe incluir no solo `require` e
`include`, sino cualquier cadena que combine `__DIR__` con `..` para
construir una ruta de sistema de archivos (cachés, registros,
exportaciones). Antes de dar la Fase C/D por cerradas se hizo un
barrido final con `grep -rn "__DIR__"` excluyendo `require`/`include`
en `app/`, `public/`, `tests/` y `config/`, y no aparecieron más
casos.

## 5. Verificación final — Fase A repetida contra `ruta360-m5.local`

| Prueba (línea base Fase A) | Resultado en `ruta360-m5.local` |
|---|---|
| `GET /rutas.php` → "Se han encontrado 7 rutas" | ✅ Idéntico |
| `GET /ver_ruta.php?id_ruta=1` → "Barcelona modernista" | ✅ Idéntico |
| `GET /api/ciudades.php` → HTTP 200 | ✅ Idéntico |
| `GET /libros.php?q=quijote` → "Se han encontrado 10 libro(s)" | ✅ Idéntico (una repetición intermedia falló por una intermitencia puntual de Open Library / la red, no reproducible) |

Comprobaciones adicionales, no parte de la línea base original pero
relevantes para esta fase:

- `GET /_tests/transporte/ep_transporte.php?ignorar_auth=1` → JSON
  válido del simulador (confirma el `Alias` de desarrollo).
- `GET /_soap/distancias.wsdl` → HTTP 200 (confirma el segundo
  `Alias`).
- `GET /tiempo.php?id_ciudad=1` → datos de tiempo correctos.
- Tras una petición a `ver_ruta.php`, `storage/cache/` recibe un
  archivo nuevo escrito por el usuario `daemon` (confirma el bug de
  ruta y el de permisos, ambos resueltos).

Con esto, `ruta360_m5` funciona de forma completamente autónoma bajo
su propio *vhost*, replicando el comportamiento del proyecto original
sin depender de él para nada — ni rutas de archivo, ni URLs, ni
simuladores de prueba.
