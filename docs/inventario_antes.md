# Inventario antes de la reorganización — Manual 5

Copia de trabajo: `ruta360_m5` (duplicada de `ruta360vs1`, la única copia funcional, el 17/09/2026).

## Línea de base (Fase A, antes de mover nada)

| Prueba | Resultado |
|---|---|
| `GET /rutas.php` | "Se han encontrado 7 rutas" |
| `GET /ver_ruta.php?id_ruta=1` | "Barcelona modernista" |
| `GET /api/ciudades.php` | HTTP 200 |
| `GET /libros.php?q=quijote` | "Se han encontrado 10 libro(s)" |

Estas cuatro pruebas son las que se repetirán en la Fase D para demostrar que la reorganización no rompió nada.

## Inventario por responsabilidad

| Origen actual | Responsabilidad | Tipo | Destino previsto | Decisión |
|---|---|---|---|---|
| `index.php`, `rutas.php`, `ver_ruta.php`, `nueva_ruta.php`, `editar_ruta.php`, `eliminar_ruta.php`, `login.php`, `logout.php`, `libros.php`, `libro_detalle.php`, `tiempo.php`, `prueba_json.php` | Puntos de entrada web (los solicita el navegador directamente) | Público | `public/*.php` | Mover |
| `api/ciudades.php`, `api/ruta.php`, `api/rutas.php` | Puntos de entrada JSON | Público | `public/api/*.php` | Mover |
| `estilos.css` | Presentación | Público | `public/assets/css/estilos.css` | Mover |
| `conexion.php` | Conexión PDO a MySQL | Configuración | `config/conexion.php` | Mover |
| `config_api.php` | Configuración de la API interna (token, URL base) | Configuración | `config/config_api.php` | Mover |
| `config/config.example.php`, `config/servicios.php` | Plantilla y configuración de servicios externos | Configuración | `config/` (sin cambios) | Mantener |
| `config/config.local.php` | Valores reales por entorno (contraseñas, token) | Configuración con secretos | `config/config.local.php` | Mantener — **nunca se entrega** |
| `seguridad_web.php` | Utilidad de seguridad compartida | Interno | `app/Utilidades/seguridad_web.php` | Mover |
| `servicios/AdaptadorDistanciasSoap.php`, `AdaptadorLibros.php`, `AdaptadorMeteorologia.php`, `AdaptadorTransporte.php`, `cliente_rutas.php`, `meteo_resiliente.php`, `meteorologia.php`, `tiempo_resiliente.php`, `transporte_cliente.php`, `cache_meteo.php`, `vista_externos.php`, `ProveedorExterno.php`, `ProveedorSimulado.php`, `ResultadoExterno.php`, `ServicioExternoException.php`, `PlanificadorRuta.php` | Lógica de negocio y adaptadores externos | Interno | `app/Servicios/` | Mover |
| `servicios/RepositorioRuta.php` | Acceso a datos de rutas (vía API interna) | Interno | `app/Repositorios/RepositorioRuta.php` | Mover |
| `soap/distancias.wsdl`, `distancias_fallo.wsdl`, `distancias_sin_distancia.wsdl`, `distancias_server.php` | Simulación local de servicio SOAP | Interno / soporte | `app/Servicios/soap/` | Mover |
| `_test_transporte/ep_transporte.php` | Simulador activo del endpoint de transporte (lo usa `config/servicios.php`) | Soporte / pruebas | `tests/transporte/ep_transporte.php` | Mover — **actualizar la URL en config/servicios.php (Fase C)** |
| `_test_config.php` | Comprobación de carga de configuración | Soporte / pruebas | `tests/_test_config.php` | Mover |
| `sql/ruta360.sql`, `sql/manual4.sql`, `sql/manual10.sql` | Reconstrucción de la base de datos | Datos | `sql/01_estructura.sql` + `sql/02_datos_minimos.sql` (consolidar y renombrar) | Revisar y renombrar |
| `sql/sql/manual10.sql` | Duplicado accidental (carpeta `sql/` anidada dentro de `sql/`) | Datos | — | Eliminar |
| `storage/cache/*.json` | Caché de meteorología generada en ejecución | Generado | `storage/cache/` (carpeta vacía con `.gitkeep`) | No entregar el contenido |
| `hosts_antes_manual_2b`, `httpd-vhosts_antes_manual_2b.conf` | Copias de seguridad de configuración del sistema (Manual 2B) | Documentación histórica | `docs/historico/` | Mover (no forman parte del código de la app) |
| Todos los `*.usado`, `*.debug.usado`, `*.roto.usado`, `*.b64.usado`, `*.tar.gz*.usado`, y las carpetas `_test_meteo.usado/`, `_test_integracion.usado/` (decenas de archivos) | Historial de ejercicios de manuales anteriores, ya superados | Histórico / no entregable | — | No copiar a la estructura organizada; quedan intactos únicamente en `ruta360vs1`, la copia original |
| `.gitignore` | Exclusiones de control de versiones | Configuración | raíz de `ruta360_m5` | Mantener y actualizar tras mover archivos |
| `README.md`, `VERSION` | Documentación de entrada e identificador de la entrega | Documentación | raíz de `ruta360_m5` | Crear (no existen todavía) |

## Notas

- El repositorio git de `ruta360vs1` (creado en la sesión anterior, commit `4330c58`) no se copió a `ruta360_m5`: esta reorganización se trabajó primero sobre archivos sueltos, dejando pendiente la decisión de si `ruta360_m5` heredaría ese historial o empezaría uno propio.

  **Decisión final (5.20/paquete de entrega):** `ruta360_m5` tiene su
  propio repositorio git, independiente, con un historial nuevo — no
  hereda los commits de `ruta360vs1`. Motivo: `ruta360_m5` no es una
  copia con parches sucesivos como `ruta360vs1` (que arrastra el
  historial completo de los 12 manuales), sino el resultado ya
  terminado de la reorganización; empezar un historial propio, con
  un primer commit que documenta el estado final limpio, refleja
  mejor lo que es este proyecto: la versión que se entregaría de
  verdad. Nótese además que `ruta360_m5` vive dentro de `curso_php`,
  que a su vez ya es un repositorio git (sin commits, con decenas de
  carpetas de ejercicios sin relación) — el repositorio de
  `ruta360_m5` es uno anidado e independiente de ese, exactamente
  como se recomienda para un proyecto que se va a entregar por
  separado.
- La lista de archivos `.usado` es deliberadamente numerosa: son el rastro de ejercicios de manuales anteriores (2 a 12) conservados en `ruta360vs1` por motivos de aprendizaje, pero no pertenecen a una entrega profesional — el propio Manual 5 (5.14) pide excluir "copias antiguas" y archivos que no aportan a la versión actual.
