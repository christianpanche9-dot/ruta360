# Pruebas — Manual 7 (Preparar e importar la base de datos)

Matriz de pruebas pedida en 7.18, con evidencia real de `ruta360_m7`
(vhost `ruta360-m7.local:18081`, base de pruebas `ruta360_test`). Todas
las pruebas se hicieron sobre el proyecto ya en marcha, restaurando la
configuración original después de cada una.

| Caso | Prueba | Resultado esperado | Resultado obtenido | Evidencia |
|---|---|---|---|---|
| I1 | Instalación desde cero | Los 3 SQL reconstruyen el esquema completo, sin errores | Cumplido | ver más abajo |
| D1 | Consultas reales de la aplicación | Datos correctos vía HTTP | Cumplido | ver más abajo |
| C1 | Caracteres acentuados | Se muestran correctamente de principio a fin | Cumplido | ver más abajo |
| U1 | Usuario de aplicación limitado | Operación administrativa denegada; lectura/escritura normal permitida | Cumplido | ver más abajo |
| X1 | Base de datos inexistente | Fallo limpio, sin credenciales, diagnosticable en el log | Cumplido | ver `docs/incidencia_manual_7.md` |
| E1 | ZIP de entrega | Incluye los 4 SQL nuevos y `config.example.php`; excluye lo sensible | Cumplido | ver más abajo |

Además de los seis casos, se verificó por separado la copia de
seguridad y restauración (7.19-7.20, caso "B1") y la migración
reversible del nivel Ampliación (caso "A1") — ver ambos apartados al
final.

## I1 — Instalación desde cero

```bash
mysql -u root -e "DROP DATABASE IF EXISTS ruta360_test;"
sed 's/ruta360vs1/ruta360_test/g' sql/01_base_datos.sql | mysql -u root
mysql -u root ruta360_test < sql/02_estructura.sql
mysql -u root ruta360_test < sql/03_datos_minimos.sql
```

Sin ningún error de importación. Verificado después:

- `SHOW TABLES` → las 5 tablas reales (`ciudades`, `rutas`,
  `puntos_interes`, `usuarios`, `api_tokens`).
- Conteo de filas: `ciudades`=5, `rutas`=1, `puntos_interes`=3,
  `usuarios`=0, `api_tokens`=0 — exactamente el dato mínimo esperado,
  sin nada acumulado de pruebas anteriores.
- `information_schema.TABLES` → las 5 tablas con
  `utf8mb4_unicode_ci`, confirmando la corrección del hallazgo de
  `docs/inventario_base_datos.md` (punto 4: antes, `usuarios` y
  `api_tokens` habían quedado en `utf8mb4_general_ci`).

## D1 — Consultas reales de la aplicación

Con `config.local.php` apuntando a `ruta360_test` y al usuario
`ruta360_app` (sin tocar ningún archivo de código):

- `GET /index.php` → HTTP 200, lista de las 5 ciudades.
- `GET /api/rutas.php` → HTTP 200, JSON con `"total": 1`, datos de
  `ruta360_test` (`"titulo": "Barcelona modernista"`), confirmando
  que la aplicación lee de la base de pruebas y no de `ruta360vs1`.

(Antes de llegar a este resultado, las tres URLs devolvían HTTP 403 —
ver `docs/incidencia_manual_7.md`, incidencia real de permisos tras la
duplicación del proyecto.)

## C1 — Caracteres acentuados

`GET /ver_ruta.php?id_ruta=1` muestra los tres puntos de interés con
sus acentos intactos: `Sagrada Família`, `Casa Milà`, `Casa Batlló`.
Confirma que la cadena completa — tabla `utf8mb4_unicode_ci` → PDO con
`charset=utf8mb4` en el DSN → HTML — conserva los caracteres, no solo
el almacenamiento en la base (que ya se había comprobado por separado
con una consulta SQL directa).

## U1 — Usuario de aplicación con privilegios limitados

Conectado directamente como `ruta360_app` (sin pasar por la
aplicación, para aislar el privilegio del usuario del comportamiento
del código):

| Operación | Resultado |
|---|---|
| `DROP TABLE ciudades;` | `ERROR 1142: DROP command denied to user 'ruta360_app'@'localhost' for table 'ruta360_test'.'ciudades'` |
| `CREATE USER 'otro'@'localhost' ...;` | `ERROR 1227: Access denied; you need (at least one of) the CREATE USER privilege(s)` |
| `SELECT nombre FROM ciudades LIMIT 1;` | `Barcelona` — funciona con normalidad |

`SHOW GRANTS FOR 'ruta360_app'@'localhost'` mostró únicamente `GRANT
SELECT, INSERT, UPDATE, DELETE ON ruta360_test.*` — nunca `ALL
PRIVILEGES` ni acceso a otra base.

## X1 — Base de datos inexistente

Ver `docs/incidencia_manual_7.md`, sección 2. Resumen: `db.name` a
`ruta360_inexistente` → HTTP 500, cuerpo genérico sin credenciales,
detalle exacto solo en el log del servidor (`SQLSTATE[HY000] [1044]
Access denied for user 'ruta360_app'@'localhost' to database
'ruta360_inexistente'` — no "Unknown database", por el usuario
limitado con el que se conecta, ver el matiz documentado allí).
Recuperación limpia al restaurar `db.name`, sin reiniciar Apache.

Esta misma prueba descubrió que `config/conexion.php` no fijaba
`http_response_code(500)` en sus fallos — corregido y verificado como
parte de esta prueba (commit `d9b3b3a`).

## E1 — ZIP de entrega

```bash
zip -r ruta360_entrega_7.zip ruta360_m7 \
  -x "ruta360_m7/config/config.local.php" \
  -x "ruta360_m7/.git/*" \
  -x "ruta360_m7/docs/historico/*" \
  -x "ruta360_m7/storage/cache/*.json" \
  -x "*.DS_Store" \
  -x "ruta360_m7/public/_debug_*"
```

Resultado: 86 archivos, 209001 bytes. Verificado con `unzip -l |
grep -c`: 0 coincidencias de `config.local.php`, `.git/`,
`historico/`, `.DS_Store` o `_debug_*`. `sql/` incluye los 4 archivos
nuevos (`01_base_datos.sql`, `02_estructura.sql`,
`03_datos_minimos.sql`, `04_usuario_aplicacion.example.sql`) y
`migraciones/README.md`; los dos archivos antiguos
(`01_estructura.sql`, `02_datos_minimos.sql`) no aparecen, porque ya
no existen en el proyecto (Fase A, commit `e66a9b1`).

## B1 — Copia de seguridad y restauración (7.19-7.20)

```bash
mysqldump -u root ruta360_test > ruta360_test_backup.sql
```

- El volcado tiene 178 líneas, con las 5 sentencias `CREATE TABLE`
  reales y los datos de ejemplo (`Barcelona modernista` aparece una
  vez).
- Restaurado en una base separada y nueva (`ruta360_restaurada`), sin
  tocar `ruta360_test`:
  ```
  mysql -u root -e "CREATE DATABASE ruta360_restaurada CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql -u root ruta360_restaurada < ruta360_test_backup.sql
  ```
- Verificado que la base restaurada tiene los mismos conteos
  (`ciudades`=5, `rutas`=1, `puntos_interes`=3) y el mismo dato
  (`Barcelona modernista`) que el original.
- Base de restauración y volcado eliminados después de verificar — no
  es infraestructura permanente del proyecto, solo la prueba de que
  el mecanismo de copia de seguridad funciona de verdad.

## A1 — Migración reversible (nivel Ampliación)

`sql/migraciones/2026-09-18_01_anadir_creado_en_a_rutas.up.sql` añade
`creado_en` a `rutas` (la única de las tablas principales que no
registraba cuándo se creó cada fila). Probado sobre `ruta360_test`,
que ya tenía datos (no una base recién creada):

1. **Antes**: `SHOW CREATE TABLE rutas` sin `creado_en`.
2. **Aplicar** (`up.sql`): sin errores. La fila ya existente
   (`Barcelona modernista`) quedó con `creado_en = 2026-09-18
   11:27:05` — la fecha del momento de aplicar la migración, por el
   `DEFAULT CURRENT_TIMESTAMP` (no se puede recuperar retroactivamente
   una fecha que nunca se guardó, y el archivo lo advierte).
3. La aplicación real (`GET /api/rutas.php`) siguió funcionando sin
   ningún cambio, con la columna nueva presente.
4. **Revertir** (`down.sql`): sin errores.
5. **Después**: `SHOW CREATE TABLE rutas` vuelve a ser idéntica a la
   de antes de aplicar la migración — sin `creado_en`.
6. La aplicación real siguió funcionando igual tras revertir.

Aplicar y revertir se probaron los dos, no solo uno: una migración
"reversible" que nunca se ha revertido de verdad es una promesa sin
comprobar.
