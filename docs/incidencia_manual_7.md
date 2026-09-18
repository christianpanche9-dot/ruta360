# Incidencias — Manual 7 (Preparar e importar la base de datos)

Dos incidencias: una real, no planificada, descubierta durante la Fase C
(esta sección); y el ejercicio guiado del manual, 7.17, con una base de
datos inexistente (sección siguiente, se añade tras ejecutarlo).

## 1. Incidencia real: `ruta360_m7` completo con permisos `700`/`600` tras `cp -r`

### Síntoma

Al terminar la Fase B (base de datos reinstalada desde cero, usuario
`ruta360_app` creado y verificado) y levantar el *VirtualHost* de
`ruta360_m7` (puerto 18081) para la Fase C, **las tres pruebas
devolvieron HTTP 403** (`index.php`, `api/rutas.php`,
`ver_ruta.php?id_ruta=1`), con el cuerpo genérico de Apache "Access
forbidden!" — no un error de PHP.

### Diagnóstico

El código de estado (403, no 500) apuntaba a un problema de Apache o
del sistema de archivos, no de la aplicación. El log del propio vhost
(`logs/ruta360-m7-error.log`) lo confirmó de inmediato:

```
[core:crit] (13)Permission denied: /.../ruta360_m7/.htaccess
pcfg_openfile: unable to check htaccess file, ensure it is readable
and that '/.../ruta360_m7/' is executable
```

Comparando permisos reales en el Mac entre `ruta360_m6` (funciona) y
`ruta360_m7` (falla):

```
drwxr-xr-x  ...  ruta360_m6/public/api/     (funciona)
drwx------  ...  ruta360_m7/public/api/     (falla)

-rw-r--r--  ...  ruta360_m6/public/api/rutas.php   (funciona)
-rw-------  ...  ruta360_m7/public/api/rutas.php   (falla)
```

Cada carpeta y archivo de `ruta360_m7`, sin excepción, tenía permisos
`700`/`600` (solo el propietario puede leer/entrar), en vez de los
`755`/`644` habituales del resto del proyecto.

### Causa raíz

`ruta360_m7` se creó con `cp -r ruta360_m6 ruta360_m7`, ejecutado desde
el entorno de automatización a través del punto de montaje de la
carpeta del proyecto, no directamente en una terminal del Mac. Ese
punto de montaje aplica sus propios permisos por defecto a los
archivos que crea — no conserva los permisos de origen como haría un
`cp -r` normal ejecutado en local. El resultado: una copia
bit-a-bit correcta en contenido, pero con permisos que Apache (que
corre como `daemon`, no como el usuario propietario del archivo) no
puede leer.

### Corrección

```bash
chmod -R 755 ruta360_m7
find ruta360_m7 -type f -exec chmod 644 {} \;
chmod 777 ruta360_m7/storage/cache
```

Directorios a `755`, archivos a `644`, y `storage/cache` de vuelta a
`777` (necesita escritura para el usuario de Apache, ver
"Puesta en marcha" del README).

### Verificación

Tras el `chmod`, las tres URLs pasaron a HTTP 200 y devolvieron
contenido real y correcto:

- `index.php` → lista de ciudades completa (Barcelona, Madrid,
  Valencia, París, Roma).
- `api/rutas.php` → JSON con `"total": 1`, datos de
  `ruta360_test` (no de `ruta360vs1`), confirmando que
  `config.local.php` apuntaba a la base correcta.
- `ver_ruta.php?id_ruta=1` → los tres puntos de interés con acentos
  (`Sagrada Família`, `Casa Milà`, `Casa Batlló`) se muestran
  correctamente, de principio a fin de la cadena BD → PDO → PHP →
  HTML.

### Lección

Duplicar un proyecto (`ruta360_m5` → `ruta360_m6` → `ruta360_m7`) no es
solo copiar contenido: **los permisos también son parte del estado que
hay que verificar**, sobre todo cuando la copia pasa por una capa
intermedia (aquí, un punto de montaje) que no garantiza preservarlos.
Un `cp -r` "que no da ningún error" no es lo mismo que un `cp -r` que
produce un resultado funcionalmente idéntico. A partir de aquí, toda
duplicación de proyecto en este curso debe terminar con una
comprobación explícita de permisos (`ls -la` comparando contra el
original), no solo con una comprobación de que los archivos existen.

**Nota sobre herramientas de trabajo:** el `ls -la` hecho desde el
entorno de automatización (no desde la Terminal real del Mac) mostró
permisos incorrectos en varias comprobaciones posteriores de este mismo
manual, incluso en archivos ya corregidos — parece que ese punto de
montaje no refleja fielmente los permisos reales al leerlos, solo al
escribirlos. A partir de aquí, cualquier comprobación de permisos que
importe para un diagnóstico se hace con `ls -la` ejecutado directamente
en la Terminal del Mac, nunca a través del entorno de automatización.

## 2. Ejercicio guiado (7.17): base de datos inexistente

Con `ruta360_m7` ya funcionando correctamente (Fase C superada), se
cambió `db.name` a `ruta360_inexistente` en `config.local.php` —
un nombre que no existe en ningún servidor MySQL de este equipo — y se
repitió la misma petición que antes daba HTTP 200.

### Resultado

- **HTTP 500** (nunca 200 ni 403).
- Cuerpo de la respuesta: únicamente `No se ha podido conectar con la
  base de datos.` — sin host, sin usuario, sin ninguna pista de la
  base de datos real.
- Log del servidor (`php_error_log`), con el detalle exacto:

  ```
  conexion: no se ha podido conectar con la base de datos -
  SQLSTATE[HY000] [1044] Access denied for user
  'ruta360_app'@'localhost' to database 'ruta360_inexistente'
  ```

### Un matiz real frente a la tabla de diagnóstico del manual

El manual asocia "base de datos no existe" con el error *Unknown
database*. Aquí, en cambio, MySQL devolvió **Access denied (1044)**,
no *Unknown database (1049)*. La razón: la conexión se hace con
`ruta360_app`, el usuario de privilegios limitados de la Fase B, que
no tiene ningún permiso concedido sobre `ruta360_inexistente` — y
MySQL, por diseño, no le confirma a un usuario sin acceso si una base
de datos existe o no; siempre responde "acceso denegado", exista o no.
Con `root` (que sí puede ver todas las bases) el mismo error habría
sido *Unknown database*. Ambos son el mismo síntoma de fondo
("`db.name` está mal"), pero el mensaje exacto depende de con qué
usuario se conecta — un matiz que solo se descubre probándolo de
verdad, no leyendo la tabla del manual de memoria.

### Hallazgo adicional descubierto al probar esta incidencia

Antes de corregirlo (ver más abajo), esta misma prueba devolvía
**HTTP 200** con el cuerpo de error — `config/conexion.php` nunca
llamaba a `http_response_code(500)` en su bloque `catch`, a diferencia
de `bootstrap.php`, que sí lo hace para los fallos de validación. Un
fallo real de conexión se reportaba como una respuesta "exitosa" a
nivel de protocolo HTTP, lo que habría engañado a cualquier
monitorización automática que solo mirara el código de estado. Se
corrigió añadiendo `http_response_code(500);` antes del `exit()`, y se
verificó que la misma prueba (`db.name` inexistente) ahora sí devuelve
HTTP 500.

### Recuperación

Se restauró `db.name` a `ruta360_test` y se repitió la petición
original: HTTP 200, lista completa de las 5 ciudades — recuperación
limpia, sin reiniciar Apache (la configuración se valida en cada
petición, igual que en el Manual 6).
