# Inventario y decisiones — Manual 8 (Control de versiones con Git)

Copia de trabajo: `ruta360_m8`, copiada de `ruta360_m7` **sin** su
carpeta `.git` (decisión explícita, ver punto 1).

## 1. Por qué `ruta360_m8` empieza sin historial, a diferencia de todos los manuales anteriores

Desde el Manual 5, cada copia de trabajo (`ruta360_m6`, `ruta360_m7`)
heredó el historial git de la anterior con `cp -r` (incluida su
carpeta `.git`). Este manual asume justo lo contrario: que Ruta360
**nunca ha tenido control de versiones**, y su actividad guiada entera
gira en torno a `git init` sobre una carpeta sin historial (8.5-8.8).

Se decidió, a propósito, simular ese punto de partida: `ruta360_m8` se
copió sin `.git`, para poder ejecutar de verdad `git init`, el primer
`git status` sobre un repositorio recién creado, y el primer commit
-- exactamente como lo describe el manual -- en vez de simularlo sobre
un repositorio que ya tenía 8 commits de manuales anteriores.

**Consecuencia real, no oculta**: el historial de `ruta360_m6` y
`ruta360_m7` (Manuales 5-7) no está dentro de `ruta360_m8`. Sigue
existiendo, intacto, en esas dos carpetas -- no se ha perdido nada --
pero `ruta360_m8` es, deliberadamente, un repositorio nuevo.

## 2. `.gitignore`: reconciliando la plantilla del manual con lo que el proyecto real tiene

La plantilla de 8.6 asume una estructura con `vendor/` (dependencias
de Composer), `/logs/*.log` (una carpeta de logs propia del proyecto)
y `/storage/uploads/*` (subidas de usuarios). Ruta360 no tiene ninguna
de las tres:

| Exclusión de la plantilla | ¿Aplica a Ruta360? | Motivo |
|---|---|---|
| `.env`, `config/local.php`, `config/*.local.php` | Sí, adaptada | Ya excluida desde el Manual 6 como `config/config.local.php` |
| `/vendor/` | No | El proyecto no usa Composer; no hay dependencias instalables |
| `/logs/*.log` | No | Apache registra en los logs propios de XAMPP (fuera del proyecto), no en una carpeta `logs/` del repositorio |
| `/storage/cache/*` | Sí, ya existía | `storage/cache/*.json`, excluida desde el Manual 5 |
| `/storage/sessions/*` | No | La sesión de PHP no escribe en el proyecto (usa el almacén de sesiones por defecto de PHP) |
| `/storage/uploads/*` | No | Ruta360 no acepta subidas de archivos de usuario |
| `.vscode/`, `.idea/`, `Thumbs.db`, `.DS_Store` | Sí, añadida | Genérico de editor/SO; `.DS_Store` ya se excluía, se añaden los demás por si acaso |

`sql/04_usuario_aplicacion.sql` (una copia real del ejemplo, con
contraseña, si alguna vez se crea) sigue excluido desde el Manual 7.

No se copia la plantilla del manual tal cual: se adapta a lo que el
proyecto realmente tiene, siguiendo el mismo criterio de todos los
manuales anteriores ("si algo no existe, no se inventa").

## 3. Repositorio remoto (8.18)

Se configurará un repositorio remoto real (no simulado) en GitHub. El
`git push` necesita las credenciales reales del usuario, que solo
existen en su Terminal real -- nunca se ejecuta ese paso desde este
entorno de automatización, que no tiene ni debe tener acceso a esas
credenciales.
