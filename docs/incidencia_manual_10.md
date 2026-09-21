# Incidencias — Manual 10 (Actualización y rollback)

Seis incidencias reales, no planificadas, surgidas durante el
desarrollo aislado en `ruta360-dev` y el despliegue en `ruta360-test`.

## 1. VirtualHost obsoleto apuntando a la carpeta de un manual anterior

**Síntoma**: al levantar el entorno de desarrollo aislado, el navegador
mostró `DNS_PROBE_FINISHED_NXDOMAIN` para `ruta360-m7.local:18081`.
Tras añadir la entrada que faltaba en `/etc/hosts`, el error cambió a
"El servicio ha devuelto una respuesta no válida."

**Diagnóstico**: `sed -n` sobre `httpd-vhosts.conf` mostró que el
`VirtualHost` de `ruta360-m7.local` tenía su `DocumentRoot` apuntando
todavía a `curso_php/ruta360_m7/public` — la carpeta del Manual 7, no
la actual (`ruta360_m8`). Ese `VirtualHost` nunca se actualizó cuando
el curso avanzó de manual.

**Causa raíz**: infraestructura que no se mantiene sincronizada con el
avance del proyecto; cada manual creó su propia carpeta de código,
pero el `VirtualHost` del primero se quedó fijo.

**Corrección**: en vez de reescribir el `VirtualHost` viejo (que podría
tener otras dependencias sin revisar), se creó uno nuevo y correcto
(`ruta360-m8.local:18083`) apuntando a `ruta360_m8/public`, y se
añadió su entrada correspondiente en `/etc/hosts`.

**Verificación**: `apachectl configtest` → `Syntax OK`; `apachectl
restart` sin error; `curl` contra el nuevo VirtualHost devolvió JSON
real de la API.

**Lección**: un `DocumentRoot` que apunta a la carpeta equivocada no
siempre da un error obvio — aquí dio un error genérico de "respuesta
no válida" porque Apache sí respondía, solo que servía código de otro
manual. Hay que verificar el `DocumentRoot` real, no asumir que el
nombre del host coincide con la carpeta correcta.

## 2. Contraseña de MySQL desactualizada tras un cambio de otro manual

**Síntoma**: con el VirtualHost ya corregido, la aplicación seguía sin
conectar a la base de datos.

**Diagnóstico**: se probaron directamente ambas contraseñas conocidas
de `ruta360_app`@`localhost` contra MySQL. La contraseña del Manual 7
(`m7TestLocal_2026`) dio "Access denied"; la del Manual 9
(`m9DespliegueTest_2026`) funcionó. El `ALTER USER` del Manual 9 había
cambiado la contraseña de la cuenta compartida `ruta360_app` para
*todos* los entornos que la usan, no solo para `ruta360_test` — en
MySQL, un usuario (`usuario`@`host`) es una única cuenta, no una por
base de datos.

**Causa raíz**: `config/config.local.php` de este entorno nuevo todavía
tenía la contraseña heredada del Manual 7, sin saber que el Manual 9
la había sobrescrito a nivel de cuenta.

**Corrección**: se actualizó `config.local.php` con la contraseña
real vigente.

**Verificación**: tras el cambio, las páginas que dependen de la base
de datos respondieron con datos reales en vez de errores 500.

**Lección**: `ALTER USER` cambia una cuenta completa, no una relación
usuario-base de datos. Si varios entornos comparten el mismo usuario
de MySQL, cambiar su contraseña en un entorno rompe silenciosamente
a los demás hasta que se actualiza su configuración también.

## 3. Base de datos nueva sin usuarios ni token de API

**Síntoma**: con la conexión ya funcionando, no había forma de iniciar
sesión (la app no tiene registro propio) ni de guardar cambios vía la
API interna ("El token no es válido o ha caducado.").

**Diagnóstico**: `sql/03_datos_minimos.sql` nunca siembra la tabla
`usuarios` ni `api_tokens` — son datos sensibles que, correctamente,
no viven en un script versionado. Una base de datos nueva desde cero
(`ruta360_dev`) hereda esa ausencia.

**Corrección**: se generó un hash `bcrypt` real con el `password_hash()`
de PHP para crear un usuario editor de pruebas, y se calculó el
SHA-256 real del token configurado en `config.local.php` para
insertarlo en `api_tokens`, replicando cómo la app valida ambos
mecanismos por separado (sesión de login vs. token Bearer interno).

**Verificación**: login exitoso con las credenciales creadas; guardado
de una ruta nueva sin el error de token.

**Lección**: un entorno de desarrollo aislado desde cero no hereda
datos operativos (usuarios, tokens) que sí existen en entornos más
antiguos porque se crearon a mano en algún momento, nunca por script.

## 4. `mysql.proc` desactualizado tras una migración de versión de MariaDB

**Síntoma**: al hacer el backup de `ruta360_test` con `mysqldump
--routines --triggers`, el comando avisó: `Column count of mysql.proc
is wrong. Expected 21, found 20... Please use mysql_upgrade`.

**Diagnóstico**: el binario de MariaDB de XAMPP se actualizó en algún
momento (de 10.1.08 a 10.4.28) pero nunca se ejecutó `mysql_upgrade`
para poner al día las tablas internas del sistema.

**Corrección**: se ejecutó `mysql_upgrade -u root` (con `sudo`, porque
el archivo de control `mysql_upgrade_info` requiere permisos de
escritura en la carpeta de datos de XAMPP, que no pertenece al usuario
normal de macOS).

**Verificación**: `mysql_upgrade` recorrió todas las bases del
servidor con resultado `OK`; el re-dump posterior de `ruta360_test` no
mostró ningún warning.

**Lección**: un warning de `mysqldump` puede señalar una inconsistencia
real de infraestructura (una actualización a medias), no solo un
problema del comando. Vale la pena arreglar la causa (`mysql_upgrade`)
en vez de simplemente quitar la opción que la revela.

## 5. Casi pérdida de trabajo durante la fusión, por archivos de bloqueo huérfanos

**Síntoma**: al fusionar `feature/categoria-ruta` a `main`, `git
checkout main` falló (`Unable to create '.git/HEAD.lock': File
exists`, `fatal: bad object refs/heads/feature/categoria-ruta.lock.stale`)
pero alcanzó a reescribir parcialmente el árbol de trabajo. Los dos
archivos de la migración desaparecieron del disco.

**Diagnóstico**: quedaban archivos de bloqueo huérfanos en `.git/`
de maniobras anteriores con la herramienta de automatización, que no
pudo limpiarlos por una restricción de borrado de su entorno. Antes de
actuar, se verificó que el commit real seguía intacto: `.git/HEAD`
apuntaba a la rama correcta, y `.git/refs/heads/feature/categoria-ruta`
seguía apuntando al hash de commit correcto — es decir, no se había
perdido ningún commit, solo el árbol de trabajo estaba temporalmente
desincronizado.

**Corrección**: se eliminaron a mano (con `rm -f`, sin restricción en
la terminal real del usuario) los archivos de bloqueo concretos, y se
ejecutó `git reset --hard HEAD` para reconstruir el árbol de trabajo
desde el commit intacto.

**Verificación**: `ls -la sql/migraciones/` mostró los archivos de
vuelta con el tamaño exacto original; `git status` limpio; la fusión
se repitió y esta vez se completó sin error.

**Lección**: en Git, un commit ya hecho es prácticamente inmune a la
pérdida (es un objeto inmutable direccionado por contenido); lo que
se rompió aquí fue solo la sincronización del árbol de trabajo, y
verificar eso primero (en vez de entrar en pánico) es lo que permitió
una recuperación segura con un comando bien entendido en vez de uno
improvisado.

## 6. Petición externa investigada durante las pruebas de humo

**Síntoma**: tras el despliegue, el log de errores mostró una línea
nueva: `script '.../current/public/ruta.php' not found or unable to
stat`.

**Diagnóstico**: el log de accesos mostró que la petición vino de un
navegador Chrome real (`Mozilla/5.0 ... Chrome/153.0.0.0`), no de
ninguno de los comandos de prueba (que usan `curl` o no llevan
user-agent, por ser llamadas internas de PHP). Se revisaron las dos
únicas referencias a `ruta.php` en el código (`cliente_rutas.php` y
`api/rutas.php`) y ambas construyen la URL correcta con el prefijo
`/api/`.

**Corrección**: ninguna — no era un defecto del despliegue.

**Verificación**: ningún otro request repitió el mismo patrón; las
pruebas de humo propias no generaron ninguna línea nueva en el log de
errores.

**Lección**: un error nuevo en el log no debe descartarse sin más,
pero tampoco asumirse como culpa del propio despliegue — el
user-agent y el código fuente dieron evidencia suficiente para
descartarlo como ajeno.
