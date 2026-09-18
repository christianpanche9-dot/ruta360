# Inventario de configuración — Manual 6 (Fase A)

Copia de trabajo: `ruta360_m6` (duplicada de `ruta360_m5`, con su
historial git heredado, el 17/09/2026).

## 1. Infraestructura de partida

`ruta360_m6` tiene su propio *VirtualHost*, independiente del de
`ruta360_m5`, para poder probarlo en paralelo durante todo el manual:

| | ruta360_m5 | ruta360_m6 |
|---|---|---|
| Host | ruta360-m5.local | ruta360-m6.local |
| Puerto | 80 | **18080** |
| Autorreferencia interna | http://127.0.0.1/... | http://127.0.0.1:18080/... |

**Por qué un puerto y no una segunda IP de loopback.** El primer
intento fue replicar el truco del Manual 5 (una IP de loopback propia,
`127.0.0.2`, con su `ServerAlias`) para que las llamadas internas de
`ruta360_m6` no compitieran con las de `ruta360_m5` por la misma
`127.0.0.1`. No funcionó: a diferencia de Linux, **macOS no activa
automáticamente todo el bloque `127.0.0.0/8` en la interfaz de
loopback** — `ifconfig lo0` solo muestra `127.0.0.1`. Añadir la IP a
mano con `ifconfig lo0 alias` habría funcionado, pero no sobrevive a
un reinicio del equipo, así que no es una base reproducible para un
proyecto que se entrega.

La alternativa fue darle a `ruta360_m6` su propio puerto (`Listen`
adicional en Apache). El primer puerto elegido, **8081, ya estaba
ocupado** por otra herramienta de desarrollo en este Mac (el bundler
de Expo/React Native, que usa ese puerto por defecto) — y el intento
de `Listen 8081` no solo falló, sino que **tumbó Apache por completo**
(`no listening sockets available, shutting down`), dejando caído
también `ruta360_m5` hasta el siguiente restart. Lección: un `Listen`
que no puede abrir su puerto no falla solo para ese vhost, sino que
puede parar todo el servidor. Se cambió a **18080**, comprobado libre
primero con `lsof`, y sin relación con ningún otro proyecto de este
equipo.

## 2. Línea de base (antes de tocar configuración)

| Prueba | ruta360_m6 (puerto 18080) |
|---|---|
| `GET /rutas.php` | HTTP 200, "Se han encontrado 7 rutas" |
| `GET /ver_ruta.php?id_ruta=1` | HTTP 200, con meteorología, transporte y distancia oficial |
| `php tests/_test_config.php` | Pendiente de repetir tras los cambios de la Fase B |

Estos resultados son el objetivo a mantener durante todo el manual
(Fase C).

## 3. Hallazgo principal: config/conexion.php nunca leyó config.local.php

El Manual 5 externalizó `db_host`, `db_name`, `db_user` y `db_pass` a
`config/config.local.php` — pero **`config/conexion.php` nunca los
usó**. El archivo que de verdad abre la conexión PDO tiene sus
propios valores, escritos directamente:

```php
$host = 'localhost';
$baseDatos = 'ruta360vs1';
$usuario = 'root';
$contrasena = '';
```

Coincidían por casualidad con los de `config.local.php` (por eso
nunca dio un error visible), pero son dos fuentes de verdad
independientes: cambiar `config.local.php` no habría cambiado nada
en la conexión real. Este es el ejemplo exacto que motiva la
"Regla clave" del manual (6.2): *si para instalar Ruta360 en otro
equipo hay que editar archivos de lógica, la configuración no está
bien separada.*

## 4. Hallazgo secundario: direcciones SOAP desactualizadas (confirmado experimentalmente)

Los tres `.wsdl` de `app/Servicios/soap/` declaran su propia
dirección de servicio (`<soap:address location="...">`) apuntando
todavía a la ruta **anterior a la reorganización del Manual 5**:

```
http://localhost/curso_php/ruta360vs1/soap/distancias_server.php
```

`AdaptadorDistanciasSoap.php` crea el `SoapClient` sin una opción
`location` que la sobrescriba, así que usa literalmente la dirección
del WSDL — no la ruta reorganizada (`_soap/` vía Alias). Comprobado
moviendo temporalmente `ruta360vs1/soap/` fuera de sitio: la sección
"Distancia oficial" pasó a "Información temporalmente no disponible"
(degradación limpia, sin error, gracias a la resiliencia ya
existente) y se recuperó al restaurar la carpeta. Conclusión: **tanto
`ruta360_m5` como `ruta360_m6` dependen en secreto de que la carpeta
original `ruta360vs1` siga existiendo** — un acoplamiento oculto que
ni el `grep` de rutas del Manual 5 podía detectar, porque no es una
ruta `require`/`include` sino un valor declarativo dentro de un XML.

## 5. Otros valores encontrados

| Buscar | Encontrado en | Valor actual | Tipo | Destino |
|---|---|---|---|---|
| `localhost` | config/conexion.php:2 | `'localhost'` | Configuración | `config('db','host')` |
| `ruta360vs1` | config/conexion.php:3 | `'ruta360vs1'` | Configuración | `config('db','name')` |
| `root` | config/conexion.php:4 | `'root'` | Secreto | `config('db','user')` |
| (vacío) | config/conexion.php:5 | `$contrasena = ''` | Secreto | `config('db','password')` |
| `ruta360-m5.local` | config/config.local.php:app_url | Valor heredado de la copia, no actualizado | Configuración | Corregir a `http://ruta360-m6.local:18080` |
| dirección SOAP | app/Servicios/soap/*.wsdl | `http://localhost/curso_php/ruta360vs1/...` | Configuración (acoplada al entorno) | Override explícito de `location` en `crearClienteSoap()`, usando una URL de `config()` |
| `127.0.0.1:18080` | config/servicios.php (transporte, distancias_soap) | Ya usa `$_ENV` con este valor como respaldo | Configuración, ya semi-externalizada | Revisar si el respaldo debería derivarse de `config()` en vez de estar escrito aparte |
| `mostrar_errores` / debug | config/config.local.php | `true` (ya existente) | Configuración | `config('app','debug')`, con comportamiento por entorno (6.13) |
| `api_token` | config/config.local.php | ya presente, nunca en el código | Secreto | `config('api','token')` (grupo propio, ver más abajo) |

## 6. Grupos de configuración planeados (Fase B)

Por decisión explícita para este proyecto: tres grupos, no dos como
el ejemplo mínimo del manual, porque `api_base`/`api_token` (cliente
de la API interna, Manual 4) no encajan de forma natural ni en `app`
ni en `db`.

```
config('app', 'env' | 'debug' | 'url')
config('db',  'host' | 'port' | 'name' | 'user' | 'password' | 'charset')
config('api', 'base' | 'token')
```

## 7. Qué NO se toca todavía

Siguiendo el método del manual (6.4: "no modificaremos todavía la
base"), este documento es solo el inventario. Los cambios de código
(plantilla, archivo local, cargador, validador, reescritura de
`conexion.php` y de `crearClienteSoap()`) son la Fase B.
