# Incidencia controlada (Manual 5, 5.18)

Ejercicio: romper a propósito una ruta basada en `__DIR__` en un
punto de entrada real, diagnosticarla solo a partir de lo que produce
(sin mirar antes el código), corregirla y verificar. Objetivo del
manual: demostrar que un fallo de este tipo se localiza con método,
no a tanteo.

## 1. Qué se rompió

En `public/rutas.php`, línea 2, se quitó el `../` de la ruta al
punto de arranque común:

```diff
- require_once __DIR__ . '/../config/bootstrap.php';
+ require_once __DIR__ . '/config/bootstrap.php';
```

## 2. Síntoma observado

- **Para un visitante** (`curl http://ruta360-m5.local/rutas.php`):
  página completamente en blanco, código **HTTP 500**. Sin ningún
  detalle del error — `display_errors` está desactivado en el PHP de
  XAMPP, así que un fallo real no revela rutas internas del servidor
  a quien visita la web (correcto para un entorno que se acerca a
  producción; incorrecto sería mostrar aquí la traza completa).

- **En el registro de errores** (`php_error_log`), sí aparece todo lo
  necesario:

  ```
  PHP Warning:  require_once(/Applications/XAMPP/xamppfiles/htdocs/curso_php/ruta360_m5/public/config/bootstrap.php): Failed to open stream: No such file or directory in .../public/rutas.php on line 2
  PHP Fatal error:  Uncaught Error: Failed opening required '/Applications/XAMPP/xamppfiles/htdocs/curso_php/ruta360_m5/public/config/bootstrap.php' ... in .../public/rutas.php:2
  ```

## 3. Diagnóstico (leyendo solo el mensaje anterior)

El error da tres datos suficientes para localizar la causa sin abrir
el editor a ciegas:

1. **Qué archivo falló al abrirse:** `public/config/bootstrap.php`.
2. **Desde dónde se pidió:** `public/rutas.php`, línea 2.
3. **Que esa carpeta no existe:** `config/` nunca ha estado dentro de
   `public/` en este proyecto — vive en la raíz, como hermana de
   `public/` (ver `docs/inventario_antes.md` y
   `docs/correccion_rutas.md`).

Conclusión: `rutas.php` vive en `public/`, así que `__DIR__` en ese
archivo vale `.../ruta360_m5/public`. Al quitar el `../`, la
concatenación con `/config/bootstrap.php` da como resultado
`.../ruta360_m5/public/config/bootstrap.php` — un archivo que nunca
ha existido ahí. El `../` que faltaba es exactamente el que sube de
`public/` a la raíz del proyecto antes de bajar a `config/`.

## 4. Corrección

Se restauró la línea original:

```php
require_once __DIR__ . '/../config/bootstrap.php';
```

## 5. Verificación

| Prueba | Resultado |
|---|---|
| `curl -s -o /dev/null -w "%{http_code}"` a `rutas.php` | 500 (con el error) → 200 (corregido) |
| `curl -s rutas.php \| grep "Se han encontrado"` | vacío (con el error) → "Se han encontrado 7 rutas" (corregido, igual que la línea base de la Fase A) |
| `php_error_log` tras la corrección | Sin nuevas entradas para `rutas.php` |

## Nota aparte, encontrada al reproducir esta incidencia

Durante la comprobación apareció un segundo error real, distinto y
más simple, que conviene documentar porque es fácil confundirlo con
el primero: tras corregir el `require` y volver a enviar el archivo a
través del puente con el Mac, un `curl` devolvió otra vez un error
(esta vez "Permission denied" sobre el propio `rutas.php`, no sobre
`bootstrap.php`) porque el archivo había llegado con permisos
restringidos (600, solo el propietario) y todavía no se le había
aplicado el `chmod 644` de costumbre en este proyecto — no una
incidencia nueva, sino el recordatorio de por qué el `chmod` es un
paso obligatorio después de cada envío de archivo, no opcional.
