# Inventario de base de datos — Manual 7 (Fase A)

Copia de trabajo: `ruta360_m7` (duplicada de `ruta360_m6`, con su
historial git heredado, el 18/09/2026).

Este inventario se basa en el esquema **real** de `ruta360vs1`
(consultado con `SHOW TABLES` / `SHOW CREATE TABLE` sobre el MySQL
local, no en el ejemplo genérico del manual, que usa nombres de
columna distintos — `id`/`ciudad_id` — a los del proyecto real
(`id_ciudad`/`id_ruta`). El manual advierte explícitamente: *"si una
tabla no existe, no se inventa"* — este inventario respeta esa regla
en ambos sentidos: ni se añaden tablas del ejemplo que no existen
(`favoritos`), ni se cambian nombres de columna para parecerse al
ejemplo.

## 1. Tablas reales (confirmadas con `SHOW CREATE TABLE`)

| Tabla | Clave primaria | Claves foráneas | Filas actuales (`ruta360vs1`) |
|---|---|---|---|
| `ciudades` | `id_ciudad` | — | 5 |
| `rutas` | `id_ruta` | `id_ciudad` → `ciudades.id_ciudad` | 12 |
| `puntos_interes` | `id_punto` | `id_ruta` → `rutas.id_ruta` | 3 |
| `usuarios` | `id_usuario` | — | 2 |
| `api_tokens` | `id_token` | `id_usuario` → `usuarios.id_usuario` | 2 |

Relación completa: una ciudad tiene varias rutas; una ruta pertenece a
una ciudad y tiene varios puntos de interés, en un orden (`orden`,
único por ruta); un usuario puede tener varios tokens de API.

Claves únicas adicionales, además de las primarias:

- `ciudades.uq_ciudad_pais` — `(nombre, pais)`, evita duplicar la
  misma ciudad.
- `puntos_interes.uq_ruta_orden` — `(id_ruta, orden)`, evita dos
  puntos con el mismo orden en la misma ruta.
- `usuarios.email` — único.
- `api_tokens.token_hash` — único (nunca se guarda el token en claro,
  solo su hash SHA-256, ver Manual 3).

## 2. Hallazgo: la tabla `favoritos` del ejemplo del manual no existe

El manual usa `favoritos` (relación `usuarios`↔`rutas`) como ejemplo
de tabla de unión en 7.4. Se comprobó con `SHOW TABLES` en las tres
bases reales del equipo (`ruta360vs1`, `ruta360_test` y `ruta360`):
**ninguna tiene `favoritos`**. No es una funcionalidad de este
proyecto (Ruta360 no tiene "marcar como favorita" en ningún sitio del
código, verificado por separado con `grep -ri favorito app/ public/`
→ sin resultados). No se inventa ni se añade: el inventario de este
manual documenta lo que existe, no lo que el ejemplo sugiere.

## 3. Hallazgo aparte: la base `ruta360` (no usada) tiene una tabla extra

Al comprobar las tres bases visibles en phpMyAdmin se confirmó que
`ruta360` (distinta de `ruta360vs1` y `ruta360_test`, y que este
proyecto no usa ni configura en ningún `config.local.php`) tiene una
tabla `ruta_punto` que no existe en las otras dos. Es, con toda
probabilidad, un resto de una versión anterior o de otro ejercicio del
curso — fuera del alcance de Ruta360 y de este manual. Se documenta
aquí solo para que quede constancia de que se investigó y se descartó
conscientemente, no por descuido.

## 4. Hallazgo: `collation` inconsistente entre tablas

Las tres bases (`ruta360vs1`, `ruta360_test`, `ruta360`) tienen
`utf8mb4` / `utf8mb4_unicode_ci` como charset/collation **por
defecto**. Pero al mirar tabla por tabla (`SHOW CREATE TABLE`), dos de
las cinco no heredaron ese valor por defecto:

| Tabla | Collation real |
|---|---|
| `ciudades` | `utf8mb4_unicode_ci` |
| `rutas` | `utf8mb4_unicode_ci` |
| `puntos_interes` | `utf8mb4_unicode_ci` |
| `usuarios` | **`utf8mb4_general_ci`** |
| `api_tokens` | **`utf8mb4_general_ci`** |

Causa probable: `usuarios` y `api_tokens` se crearon en un `CREATE
TABLE` con `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4` explícito (ver
`sql/02_datos_minimos.sql` actual) pero sin fijar `COLLATE`, así que
tomaron el collation por defecto del *servidor* MySQL en el momento de
crearlas, no el de la base de datos. No es un fallo visible hoy
(`email` y `token_hash` no llevan tildes ni comparaciones que dependan
de reglas de acentos/mayúsculas), pero es una inconsistencia real que
conviene corregir en los SQL nuevos, para que las cinco tablas
compartan un único collation explícito y no dependan de la
configuración por defecto del servidor donde se importen — justo el
tipo de cosa "frágil" que este manual pide evitar (7.2).

**Decisión para los SQL nuevos (Fase A):** las cinco tablas se crean
con `utf8mb4_unicode_ci` explícito en cada `CREATE TABLE`, no solo a
nivel de base de datos.

## 5. Reconciliación de la estructura de archivos SQL

El proyecto real tiene hoy solo dos archivos, que mezclan varias
responsabilidades:

| Archivo actual | Contiene |
|---|---|
| `sql/01_estructura.sql` | `CREATE DATABASE` + tabla `ciudades` + sus datos |
| `sql/02_datos_minimos.sql` | Tablas `rutas` y `puntos_interes` (con datos) + tablas `usuarios` y `api_tokens` (sin datos) |

El manual pide separar por responsabilidad, en cuatro archivos
numerados más una carpeta de migraciones futuras:

| Archivo nuevo | Contenido |
|---|---|
| `sql/01_base_datos.sql` | Solo `CREATE DATABASE` (nombre parametrizable, ver comentario en el propio archivo) |
| `sql/02_estructura.sql` | Las cinco `CREATE TABLE` reales, sin datos, con `utf8mb4_unicode_ci` explícito en todas |
| `sql/03_datos_minimos.sql` | Datos mínimos para poder navegar la aplicación: las 5 ciudades y 1 ruta de ejemplo con sus puntos de interés (igual que hoy). **No inserta usuarios ni tokens** — se mantiene la misma limitación ya documentada en el README (Manual 6): un usuario de prueba requiere un hash generado con `password_hash()` y no se documenta ningún valor concreto, para no repetir el problema de seguridad ya corregido en el Manual 3 (RS-01) |
| `sql/04_usuario_aplicacion.example.sql` | Ejemplo (no real) de `CREATE USER` + `GRANT` para el usuario de aplicación de privilegios limitados (7.11) |
| `sql/migraciones/` | Vacía por ahora, con un `README.md` explicando su propósito — reservada para cambios futuros al esquema, uno por archivo, según pide el manual |

Los datos reales de `rutas`/`puntos_interes` que hoy tiene
`ruta360vs1` (12 rutas, no 3) no se copian tal cual al SQL de entrega:
igual que en el Manual 6, el objetivo es un conjunto de datos mínimo y
reproducible, no un volcado de todo lo acumulado durante las pruebas
de manuales anteriores.

## 6. Qué no se toca todavía

Siguiendo el método del manual, esto es solo el inventario (Fase A).
La reescritura real de los archivos `sql/` es el siguiente paso
(sigue en este mismo commit/sesión), y la instalación desde cero en
una base limpia es la Fase B.
