# Pruebas — Manual 10 (Actualización y rollback)

## Migración: prueba de reversibilidad en `ruta360_dev`

| Prueba | Acción | Resultado esperado | Resultado obtenido |
|---|---|---|---|
| Aplicar | `mysql ruta360_dev < ...up.sql` | Columna `categoria` añadida, `VARCHAR(40) NULL`, tras `dificultad` | Cumplido |
| Uso real | Insertar un valor de prueba en `categoria` vía la API | Se guarda y se muestra en `ver_ruta.php` y `rutas.php` | Cumplido — "Categoría: cultural" visible |
| Revertir | `mysql ruta360_dev < ...down.sql` | Columna `categoria` eliminada, esquema idéntico al original | Cumplido |
| Pérdida documentada | Revisar si el valor de prueba sobrevive a la reversión | Se pierde (así lo documenta el propio `.down.sql`) | Cumplido — comportamiento esperado, no un fallo |

## Pruebas de humo tras el despliegue de `v0.9.0` en `ruta360-test`

| Prueba | Acción | Resultado esperado | Resultado obtenido |
|---|---|---|---|
| Resolución | `ping ruta360-m9-pruebas.local` | Resuelve a 127.0.0.1 | Cumplido — 0.0 % de pérdida |
| HTTP | `GET /` | HTTP 200 | Cumplido |
| Recursos | `GET /assets/css/estilos.css` | HTTP 200 | Cumplido |
| Listado con categoría oculta | `GET /rutas.php` | Se muestra "Barcelona modernista" sin línea "Categoría:" (valor NULL) | Cumplido |
| Detalle con categoría oculta | `GET /ver_ruta.php?id_ruta=1` | Igual que el listado | Cumplido |
| API — listado | `GET /api/rutas.php` | JSON incluye `"categoria": null` | Cumplido |
| API — ruta individual | `GET /api/ruta.php?id_ruta=1` | JSON incluye `"categoria": null` | Cumplido |
| Registros | Revisar logs tras las pruebas | Sin errores nuevos atribuibles al despliegue | Cumplido — una línea nueva investigada y descartada como ajena (ver `docs/incidencia_manual_10.md`, incidencia 6) |

## Fuera de alcance en este entorno

El flujo de escritura (crear/editar una ruta con `categoria` desde el
formulario o la API) no se probó contra `ruta360_test`, porque esa
base no tiene usuarios ni un token de API sembrado (igual que en el
Manual 9). Ese flujo sí se probó a fondo, de extremo a extremo, en
`ruta360_dev` antes de desplegar.
