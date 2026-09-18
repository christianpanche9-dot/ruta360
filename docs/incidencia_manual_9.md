# Incidencias — Manual 9 (Primer despliegue completo)

Tres incidencias reales, no planificadas, surgidas durante el
despliegue guiado (9.7-9.15).

## 1. Colisión de nombre de host con un VirtualHost preexistente

**Síntoma**: el manual especifica literalmente `pruebas.ruta360.local`
como nombre del VirtualHost del entorno de pruebas (9.14). Al revisar
`httpd-vhosts.conf` antes de escribir el nuevo bloque, ese nombre ya
existía — un `VirtualHost *:80` apuntando a
`curso_php/ruta360vs1-pruebas`, una carpeta sin relación con este
despliegue (probablemente un resto de una práctica anterior a la
reorganización por manuales).

**Diagnóstico**: técnicamente no habría sido un conflicto real —
Apache agrupa los *VirtualHost* con nombre por combinación IP:puerto,
así que un segundo `pruebas.ruta360.local` en el puerto 18082 habría
convivido sin error con el existente en el puerto 80. Pero habría
sido confuso de verdad: el mismo nombre de host resolviendo a dos
aplicaciones distintas según el puerto de la URL, rompiendo además la
convención que ya usa este proyecto (`ruta360-m5.local`,
`ruta360-m6.local`, `ruta360-m7.local`: un nombre distinto por
manual).

**Causa raíz**: el nombre de la práctica anterior nunca se limpió, y
el manual asume un entorno limpio que este proyecto, con siete
manuales de historial real, ya no tiene.

**Corrección**: se decidió con el usuario (no en solitario, por ser
una decisión de diseño con consecuencias duraderas) usar
`ruta360-m9-pruebas.local` en su lugar, manteniendo la convención
existente del proyecto en vez del nombre literal del manual.

**Verificación**: `ping ruta360-m9-pruebas.local` resuelve a
127.0.0.1 sin ambigüedad; el `VirtualHost` antiguo en el puerto 80
sigue intacto y sin tocar.

**Lección**: un manual genérico asume un entorno limpio; un proyecto
real acumula historial. Antes de reutilizar un nombre que el propio
manual sugiere, hay que comprobar si ya existe.

## 2. Corrupción real de `httpd-vhosts.conf` al pegar comandos en el editor

**Síntoma**: al pegar en `nano` el bloque `<VirtualHost>` seguido de
los comandos de verificación (`dscacheutil`, `apachectl configtest`,
etc.) en un solo bloque, el editor los interpretó todos como
contenido del archivo. `apachectl configtest` falló con:
```
httpd: Syntax error on line 213 of .../httpd-vhosts.conf:
</VirtualHost>dscacheutil> directive missing closing '>'
```

**Diagnóstico**: `configtest` señaló la línea exacta, y `sed -n
'195,225p'` confirmó que `</VirtualHost>` y `dscacheutil -flushcache`
habían quedado pegados en una sola línea, seguidos de tres líneas más
que eran en realidad comandos de terminal, no configuración de
Apache.

**Causa raíz**: el bloque enviado mezclaba, en un solo mensaje, texto
destinado al editor (el `VirtualHost`) con comandos destinados a la
terminal (después de guardar y salir). Al pegarse todo junto dentro
de `nano`, no hubo forma de que el editor distinguiera dónde terminaba
uno y empezaba el otro.

**Corrección, en dos intentos** (el primero fue incompleto): un primer
intento borró por número de línea (`sed '214,217d'`), pero contó mal
— la línea corrupta real era la 213, no la 214, así que el borrado
por número dejó la línea rota intacta y encima añadió un
`</VirtualHost>` duplicado. La corrección definitiva localizó el
texto exacto y lo reemplazó (`sed 's/<\/VirtualHost>dscacheutil
-flushcache/<\/VirtualHost>/'`), evitando depender de contar líneas
manualmente, y luego eliminó la línea duplicada sobrante.

**Verificación**: `apachectl configtest` pasó a devolver `Syntax OK`
(con dos advertencias preexistentes y sin relación, sobre los
`VirtualHost` de ejemplo de XAMPP); `apachectl restart` completó sin
error.

**Lección**: contar líneas a mano para editar un archivo es frágil y
propenso a errores de conteo (como ocurrió aquí); localizar y
reemplazar el texto exacto es más fiable. Y, de cara a instrucciones
futuras: el contenido para un editor y los comandos de terminal deben
llegar en pasos claramente separados, nunca en el mismo bloque para
pegar de una vez.

## 3. Usuario de MySQL ya existente con una contraseña distinta

**Síntoma**: `ruta360_app` ya existía en MySQL desde el Manual 7. El
comando `CREATE USER IF NOT EXISTS 'ruta360_app'@'localhost'
IDENTIFIED BY 'm9DespliegueTest_2026'` no habría fallado, pero
tampoco habría cambiado la contraseña real de un usuario que ya
existe — `IF NOT EXISTS` omite la creación por completo, contraseña
incluida.

**Diagnóstico**: si `config/config.local.php` se hubiera escrito con
la contraseña nueva asumiendo que `CREATE USER IF NOT EXISTS` la
habría fijado, la aplicación habría fallado al conectar con "Access
denied" — exactamente el síntoma que la propia tabla de diagnóstico
del manual (9.16) anticipa para esta capa.

**Corrección**: se añadió una sentencia `ALTER USER` explícita
después del `CREATE USER IF NOT EXISTS`, forzando la contraseña al
valor real usado en `config.local.php` sin importar si el usuario ya
existía o no.

**Verificación**: `SHOW GRANTS FOR 'ruta360_app'@'localhost'`
confirmó los privilegios correctos (`SELECT, INSERT, UPDATE, DELETE`
únicamente sobre `ruta360_test`); las pruebas de humo que dependen de
la base de datos (`api/ciudades.php`, `api/rutas.php`) confirmaron
que la conexión con la contraseña nueva funcionaba de verdad.

**Lección**: `CREATE USER IF NOT EXISTS` es idempotente para la
*existencia* del usuario, no para su contraseña. En un despliegue que
reutiliza un usuario ya creado por un manual anterior, hay que fijar
la contraseña explícitamente con `ALTER USER`, no asumir que
`CREATE USER` la actualiza.
