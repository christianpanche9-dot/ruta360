# Paquete de entrega (Manual 5, 5.20)

Objetivo del manual: construir el `.zip` que se entregaría de verdad,
mediante una lista explícita de qué se incluye y qué se excluye —
nunca copiando la carpeta del proyecto tal cual — y comprobar que
funciona de forma independiente, fuera del entorno de desarrollo.

## 1. Archivos añadidos antes de empaquetar

Desde la Fase A (`docs/inventario_antes.md`) quedaban pendientes dos
archivos que todo el manual da por hechos en la raíz del proyecto:

- **`README.md`** — qué es el proyecto, su estructura, requisitos,
  puesta en marcha paso a paso (incluida la advertencia de usar
  `config.example.php` como plantilla, nunca entregar
  `config.local.php`), y una limitación conocida documentada con
  honestidad: `sql/02_datos_minimos.sql` no inserta ningún usuario ni
  token, así que la base de datos que resulta de importar solo el SQL
  no permite iniciar sesión ni llamar a la API directamente sin un
  paso manual adicional.
- **`VERSION`** — `1.0.0`, primera versión entregable de la
  reorganización del Manual 5.

## 2. Qué se incluye y qué se excluye, y por qué

| Carpeta/archivo | Incluido | Motivo de exclusión (si aplica) |
|---|---|---|
| `app/` | Sí | — |
| `config/` | Sí, **excepto** `config.local.php` | Contiene credenciales y el token de la API interna; cada entorno crea el suyo a partir de `config.example.php` (así lo indica el propio `README.md`) |
| `docs/` | Sí, **excepto** `docs/historico/` | Son los archivos de ejercicios de manuales anteriores, conservados solo por motivos de aprendizaje; no forman parte del producto que se entrega |
| `public/` | Sí | — |
| `sql/` | Sí | — |
| `storage/` | Sí la carpeta y su `.htaccess`, **no** los `.json` de `storage/cache/` | Son datos generados en tiempo de ejecución (caché de meteorología); regenerarlos es responsabilidad del entorno de destino, no del paquete |
| `tests/` | Sí | Se decide mantenerlo dentro de la entrega porque documenta cómo verificar la configuración (`_test_config.php`) y sirve de doble de prueba del proveedor de transporte |
| `.gitignore` | Sí | — |
| `README.md`, `VERSION` | Sí | — |
| `.DS_Store` (cualquiera) | No | Metadato del sistema de archivos de macOS, sin ningún valor para el proyecto |

Comando usado (dos bloques `zip -r` sobre el mismo archivo, con
exclusiones explícitas mediante `-x`):

```bash
cd .../ruta360_m5

zip -r ../ruta360_entrega_5.zip \
  app/ config/ docs/ public/ sql/ storage/ tests/ \
  .gitignore README.md VERSION \
  -x "config/config.local.php" \
  -x "docs/historico/*" \
  -x "storage/cache/*.json" \
  -x "*.DS_Store"
```

## 3. Verificación del contenido del zip (sin descomprimir)

```
unzip -l ruta360_entrega_5.zip | grep -E "config.local|historico|meteo_.*\.json|DS_Store"
```

Resultado: **sin coincidencias** — ninguno de los cuatro elementos
excluidos quedó dentro del paquete. Total: 72 archivos, 146 683 bytes
sin comprimir.

## 4. Prueba independiente (fuera del entorno de desarrollo)

Descomprimir el zip y comprobar que funciona por sí solo, sin ningún
resto del proyecto original, siguiendo exactamente los pasos que
seguiría quien lo recibe:

1. **Extracción** en una carpeta nueva (`_prueba_entrega/`, hermana de
   `ruta360_m5/`). Se comprobó con `find` que la estructura resultante
   no contiene `config/config.local.php`, ni `docs/historico/`, ni
   ningún `.json` en `storage/cache/` — solo el `.htaccess`.

2. **Configuración**, siguiendo el paso 1 del propio `README.md`:
   se creó `config/config.local.php` a mano con los mismos valores
   reales del entorno de desarrollo (los mismos que en
   `ruta360_m5/config/config.local.php`, incluida la IP `127.0.0.1`
   para las llamadas internas — ver `docs/correccion_rutas.md`, Fase
   D). Se aplicó `chmod 777 storage/cache` (paso 4 del README).

3. **Prueba sin depender de Apache** (paso 5 del README):

   ```
   php tests/_test_config.php
   ```

   Resultado: `Configuración cargada correctamente.` — igual que en
   el proyecto original.

4. **Prueba HTTP completa**, repuntando temporalmente el
   *VirtualHost* `ruta360-m5.local` ya existente hacia la copia
   extraída (sustituyendo `ruta360_m5` por `_prueba_entrega` en su
   `DocumentRoot`, sus tres bloques `<Directory>` y sus dos `Alias`,
   con una copia de seguridad previa de `httpd-vhosts.conf`), en vez
   de crear un *VirtualHost* nuevo — así se reutiliza el
   `ServerAlias 127.0.0.1` ya verificado en la Fase D sin que compita
   con otro vhost por el mismo alias.

   Se repitió la batería de comprobación de la Fase A/D:

   | Prueba | Resultado |
   |---|---|
   | `curl` a `rutas.php` | HTTP 200, "Se han encontrado 7 rutas" (igual que la línea base) |
   | `curl` a `ver_ruta.php?id_ruta=1` | HTTP 200, con las tres secciones de proveedores externos (meteorología, transporte, distancias) mostrando datos reales |
   | `curl` al alias `/_soap/distancias.wsdl` | HTTP 200 |
   | `storage/cache/` tras la visita a `ver_ruta.php` | Apareció un `.json` nuevo, propietario `daemon`, con permisos ya correctos gracias al `chmod 777` previo |
   | `php_error_log` | Sin ninguna entrada nueva |

   Conclusión: el paquete de entrega, una vez descomprimido y
   configurado según su propio `README.md`, funciona exactamente
   igual que el proyecto en desarrollo — sin ningún archivo, ruta ni
   configuración que dependiera silenciosamente de algo fuera del
   zip.

5. **Restauración**: se devolvió `httpd-vhosts.conf` a su versión
   original (desde la copia de seguridad), se reinició Apache, se
   confirmó que el proyecto real seguía respondiendo ("7 rutas") y se
   eliminaron la copia de seguridad y la carpeta `_prueba_entrega/`.

## 5. Resultado final

- `ruta360_entrega_5.zip` — 72 archivos, 146 683 bytes.
- Probado de forma independiente con éxito: misma configuración,
  mismo comportamiento que el proyecto en desarrollo, sin arrastrar
  nada que no debiera entregarse.
