# Ruta360

Aplicación web de planificación de rutas turísticas, desarrollada como
proyecto guía a lo largo del itinerario formativo MF0493_3
(Implantación de aplicaciones web en entornos Internet, intranet y
extranet). Permite consultar rutas por ciudad, ver el detalle de una
ruta con datos de proveedores externos (meteorología, transporte,
distancias), gestionar rutas con roles de usuario, y buscar libros a
través de la API pública de Open Library.

Esta versión añade una base de datos reproducible (Manual 7): el
esquema completo y unos datos mínimos se reconstruyen desde cero con
los archivos de `sql/`, sin depender de ningún volcado manual ni de lo
acumulado durante el desarrollo, y la aplicación se conecta con un
usuario de MySQL de privilegios limitados, no con `root` (ver
`docs/inventario_base_datos.md`). La versión anterior (Manual 6:
configuración externalizada) ya hacía que el código no cambiara según
el equipo o el entorno donde se instale, leyendo de un archivo local
todos los valores que sí cambian (base de datos, URLs, credenciales).
La versión anterior a esa (Manual 5: estructura profesional) tenía ya
la organización de archivos actual, pero algunos de esos valores
seguían escritos de forma literal en el código (ver
`docs/inventario_configuracion.md`).

## Estructura

```
app/            Lógica de negocio (nunca se sirve directamente por HTTP)
  Servicios/    Adaptadores a proveedores externos, cliente de la API interna
  Repositorios/ Acceso a los datos de rutas
  Utilidades/   Sesión, roles y CSRF
config/         Configuración (config.local.php NUNCA se entrega ni se versiona)
docs/           Documentación del proyecto y del proceso de reorganización
public/         Único directorio servido por Apache (DocumentRoot)
  api/          Endpoints JSON de la API interna
  assets/       CSS, JavaScript e imágenes
sql/            Base de datos, estructura, datos mínimos y usuario de aplicación (ver docs/inventario_base_datos.md)
storage/        Datos generados en ejecución (caché); no se entrega su contenido
tests/          Doble de prueba del proveedor de transporte y comprobación de config
```

## Requisitos

- Apache 2.4 con PHP 8.2+ y las extensiones `pdo_mysql`, `curl` y
  `soap` (XAMPP los incluye).
- MySQL/MariaDB.

## Configuración

Ningún valor de conexión ni ninguna credencial está escrito en el
código: todo se lee de `config/config.local.php`, a través de un único
punto de carga y validación (`config/bootstrap.php`, función `config()`)
que usa el resto de la aplicación. Ver `docs/inventario_configuracion.md`
(qué valores había y de dónde salían) y `docs/incidencia_manual_6.md`
(qué falla, y cómo, si la configuración es inválida).

1. Copiar `config/config.example.php` a `config/config.local.php`.
2. Completar los valores reales de este equipo (base de datos, URL del
   proyecto, token de la API interna).
3. No compartir `config/config.local.php`: no se versiona (ver
   `.gitignore`) ni se entrega (ver "Paquete de entrega" más abajo).
4. Abrir la URL inicial del proyecto: si carga la lista de ciudades, la
   conexión a la base de datos ya está probada. También se puede
   comprobar por línea de comandos con `php tests/_test_config.php`
   (paso 5 de "Puesta en marcha").

### Variables

Tres grupos — el ejemplo genérico trae `app` y `db`; este proyecto añade
`api` porque tiene su propia API interna (Manual 4):

- `app.env`, `app.debug`, `app.url`
- `db.host`, `db.port`, `db.name`, `db.user`, `db.password`, `db.charset`
- `api.base`, `api.token`

Si falta `config.local.php`, si le falta alguna clave obligatoria, o si
deja algún valor en `'CAMBIAR'` (el marcador de la plantilla), la
aplicación se detiene al arrancar con un mensaje genérico — nunca revela
el valor ni la clave concreta al navegador — y registra el motivo exacto
solo en el log del servidor (`config/validar_config.php`,
`config/bootstrap.php`).

## Puesta en marcha

1. Configurar el proyecto: ver la sección "Configuración" de arriba.

2. Importar la base de datos, en este orden (ver
   `docs/inventario_base_datos.md` y `docs/pruebas_manual_7.md` para el
   detalle de cada archivo y su verificación):

   ```
   mysql -u root < sql/01_base_datos.sql
   mysql -u root ruta360vs1 < sql/02_estructura.sql
   mysql -u root ruta360vs1 < sql/03_datos_minimos.sql
   ```

   (Sustituir `ruta360vs1` por el nombre que se haya puesto en
   `db.name` dentro de `config.local.php`, si es distinto.)

   Opcional pero recomendado (Manual 7): crear un usuario de MySQL
   propio para la aplicación, con permisos limitados a
   `SELECT`/`INSERT`/`UPDATE`/`DELETE` (nunca `root` en `db.user`).
   Ver `sql/04_usuario_aplicacion.example.sql` — copiarlo, cambiar la
   contraseña de ejemplo y ejecutarlo antes de completar
   `config.local.php` con ese usuario.

3. Configurar un *VirtualHost* de Apache con `DocumentRoot` apuntando
   a la carpeta `public/` de este proyecto (nunca a la raíz). Ver
   `docs/correccion_rutas.md`, sección "Fase D", para un ejemplo
   completo, incluidos los dos `Alias` de desarrollo necesarios para
   el simulador de transporte y el WSDL de distancias.

4. Dar permiso de escritura a `storage/cache/` para el usuario con el
   que corre Apache (en XAMPP para Mac, `daemon`):

   ```
   chmod 777 storage/cache
   ```

5. Comprobar que todo carga bien sin depender de Apache:

   ```
   php tests/_test_config.php
   ```

## Limitación conocida

`sql/02_estructura.sql` crea las tablas `usuarios` y `api_tokens`
pero `sql/03_datos_minimos.sql` no inserta ningún registro en ellas:
reproducir solo el SQL da una base de datos funcional para consultar
rutas, pero sin ningún usuario con el que iniciar sesión ni ningún
token con el que llamar a la API interna directamente. Para tener
un usuario de prueba hay que insertar uno manualmente (con una contraseña hasheada con
`password_hash()`) y registrar un token en `api_tokens` con su hash
SHA-256, como se hizo durante el desarrollo (ver `docs/` de los
manuales anteriores). No se documenta aquí una contraseña o token
concretos para no repetir el mismo problema de seguridad ya corregido
en el Manual 3 (RS-01).

## Documentación del proceso

- `docs/inventario_antes.md` — inventario de archivos antes de la
  reorganización (Fase A, Manual 5).
- `docs/correccion_rutas.md` — qué se movió, qué rutas se corrigieron
  y cómo se verificó (Fases C y D, Manual 5), incluida la configuración
  del *VirtualHost*.
- `docs/incidencia_5_18.md` — incidencia controlada del Manual 5: un
  fallo provocado a propósito, diagnosticado y corregido con evidencias.
- `docs/pruebas_manual_5.md` — índice de pruebas del Manual 5.
- `docs/inventario_configuracion.md` — inventario de valores de
  configuración antes de externalizarlos (Fase A, Manual 6), incluidos
  dos hallazgos reales: credenciales de base de datos nunca leídas de
  la configuración, y una URL de WSDL SOAP obsoleta.
- `docs/incidencia_manual_6.md` — dos incidencias: una real, no
  planificada (colisión de la variable `$config`, detectada durante las
  pruebas de la Fase B) y el ejercicio guiado del manual (6.16).
- `docs/pruebas_manual_6.md` — matriz de pruebas del Manual 6
  (D1/P1/X1/X2/PR1/E1), con evidencia de cada caso.
- `docs/inventario_base_datos.md` — esquema real de la base de datos
  (Fase A, Manual 7), incluidos dos hallazgos reales: una tabla del
  ejemplo del manual que no existe en el proyecto (`favoritos`) y una
  inconsistencia de `collation` entre tablas, corregida en los SQL
  nuevos.
- `docs/incidencia_manual_7.md` — dos incidencias: una real, no
  planificada (permisos incorrectos tras duplicar el proyecto) y el
  ejercicio guiado del manual (7.17, base de datos inexistente),
  incluido un fallo real corregido en `config/conexion.php`.
- `docs/pruebas_manual_7.md` — matriz de pruebas del Manual 7
  (I1/D1/C1/U1/X1/E1/B1/A1), con evidencia de cada caso, incluida la
  copia de seguridad y restauración real y la migración reversible
  del nivel Ampliación.
- `docs/inventario_git.md` — decisiones del Manual 8: por qué
  `ruta360_m8` empieza sin historial heredado (a diferencia de todos
  los manuales anteriores) y cómo se adaptó `.gitignore` a lo que el
  proyecto realmente tiene.
- `docs/incidencia_manual_8.md` — cinco incidencias reales: `git
  config` sin efecto por ejecutarse antes de `git init`, una URL de
  remoto mal formada, un nombre de usuario de GitHub real distinto al
  asumido, un pathspec/referencia inexistente y un push rechazado con
  reconciliación real (8.21, ejercicio opcional).
- `docs/pruebas_manual_8.md` — matriz de la actividad guiada del
  Manual 8 (R1/C1/V1/T1/M1/P1/P2): rama y fusión, conflicto de fusión
  real resuelto, `git revert` sobre un commit ya compartido, etiqueta
  anotada, repositorio remoto real, y las incidencias opcionales de
  8.21.
- `docs/historico/` — archivos de ejercicios de manuales anteriores,
  conservados por motivos de aprendizaje. **No forma parte de la
  entrega** (ver el paquete de entrega, que los excluye).

## Control de versiones

Este proyecto usa Git desde el Manual 8. Flujo de trabajo:

1. `main` representa el estado estable e integrable.
2. Cada tarea (una mejora, una corrección, un cambio de documentación)
   se desarrolla en su propia rama: `feature/nombre`, `fix/nombre` o
   `docs/nombre`.
3. Antes de confirmar, revisar `git diff --staged` — nunca confirmar
   sin haber leído qué se va a registrar.
4. Al terminar una tarea, fusionar la rama en `main`, comprobar que el
   proyecto sigue funcionando, y borrar la rama ya fusionada.
5. Una versión lista para desplegar se marca con una etiqueta anotada
   (`git tag -a vX.Y.Z`), siempre sobre `main` limpio.

Ver `docs/inventario_git.md` para las decisiones específicas de este
proyecto (por qué el historial empieza en este manual, y qué excluye
`.gitignore` y por qué).

Repositorio remoto real (Manual 8, 8.18):
<https://github.com/christianpanche9-dot/ruta360>. Etiqueta de
cierre de este manual: `v0.8.0`.
