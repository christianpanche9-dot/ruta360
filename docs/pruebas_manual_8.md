# Pruebas — Manual 8 (Control de versiones con Git)

Matriz de la actividad guiada (8.13-8.18), con evidencia real de
`ruta360_m8`. A diferencia de manuales anteriores, la evidencia aquí
es en su mayoría el propio historial de Git (verificable con `git log`
en cualquier momento), no capturas de peticiones HTTP.

| Caso | Prueba | Resultado esperado | Resultado obtenido |
|---|---|---|---|
| R1 | Rama de función + fusión (8.13-8.14) | La rama se fusiona en `main` sin perder el cambio | Cumplido |
| C1 | Conflicto de fusión real (8.15) | Git marca el conflicto con `<<<<<<<`/`=======`/`>>>>>>>`; se resuelve, se prueba y se confirma | Cumplido |
| V1 | Revertir un commit ya compartido (8.16) | `git revert` deshace el efecto sin reescribir el historial | Cumplido |
| T1 | Etiqueta anotada (8.17) | `git tag -a` crea una etiqueta verificable con `git show` | Cumplido |
| M1 | Repositorio remoto real (8.18) | `git push` sube `main` y las etiquetas a un remoto real | Cumplido (tras resolver 2 incidencias, ver `docs/incidencia_manual_8.md`) |
| P1 | `pathspec`/referencia inexistente (8.21) | Git rechaza sin efectos secundarios | Cumplido |
| P2 | Push rechazado y reconciliación (8.21) | El rechazo ocurre; se resuelve con `pull` + merge, nunca con `--force` | Cumplido |

## R1 — Rama de función y fusión

Rama `feature/aviso-mantenimiento`: añade un aviso operativo opcional,
configurable (`config/config.example.php` → `'aviso' => ''`) y
renderizado solo si tiene contenido (`public/index.php`). Verificado
antes de fusionar con `php -l` (sin errores) y dos pruebas `php -r`
reales en la Terminal del usuario: sin valor no aparece nada; con un
valor, aparece escapado correctamente (`htmlspecialchars`).

```
git switch main
git merge feature/aviso-mantenimiento
```

Resultado: `Fast-forward` (no se creó commit de fusión, porque `main`
no había recibido otros commits desde que se creó la rama — el propio
manual anticipa este caso). `git branch -d
feature/aviso-mantenimiento` confirmó que la rama ya estaba
completamente fusionada (con `-D` habría forzado el borrado aunque no
lo estuviera; no hizo falta).

## C1 — Conflicto de fusión real

Dos ramas creadas desde puntos ya divergentes del historial
(`docs/ajustar-titulo-portada` y `fix/titulo-entorno-pruebas`),
ambas modificando la misma línea `<h1>` de `public/index.php` por
razones distintas y legítimas (marca "Ruta360" vs. aviso de entorno de
pruebas). La primera fusión fue `Fast-forward`; la segunda produjo un
conflicto real:

```
Auto-merging public/index.php
CONFLICT (content): Merge conflict in public/index.php
Automatic merge failed; fix conflicts and then commit the result.
```

Marcadores reales vistos antes de resolver:
```
<<<<<<< HEAD
    <h1>Ruta360 — Consulta el tiempo de un destino</h1>
=======
    <h1>Consulta el tiempo de un destino (entorno de pruebas)</h1>
>>>>>>> fix/titulo-entorno-pruebas
```

Resuelto combinando ambas intenciones (no descartando ninguna, porque
las dos eran válidas y no se contradicen):
`<h1>Ruta360 — Consulta el tiempo de un destino (entorno de
pruebas)</h1>`. Verificado que no quedó ningún marcador (`grep` sin
coincidencias) antes de `git add` + `git commit`, que generó un commit
de fusión real de dos padres (`c56dab1`). Confirmado con `php -l`
real en la Terminal del usuario: sin errores de sintaxis.

`git merge --abort` no fue necesario aquí (el conflicto era sencillo
de resolver), pero queda documentado como la vía de escape si una
fusión conflictiva se quiere cancelar por completo y volver al estado
anterior a intentarla.

## V1 — Revertir un commit ya compartido

Se creó deliberadamente un commit con un error real de tipo "olvidé
quitar el código de depuración" (`var_dump($ciudades); exit;` en
`public/index.php` — sintácticamente válido pero que rompe la página
al detener la ejecución antes de generar el HTML), simulando que ya
estaba en `main` (equivalente a ya compartido/desplegado).

En vez de `git reset --hard` (que el manual señala explícitamente
como una práctica a evitar, porque reescribe el historial y borra la
evidencia del error), se usó:
```
git revert --no-edit 167d1b4
```

Resultado: nuevo commit `bc98280` que deshace el cambio. Verificado
que `public/index.php` volvió exactamente al contenido anterior (una
sola línea eliminada, sin ningún otro cambio) y que el commit erróneo
sigue visible en `git log` — a diferencia de `reset --hard`, aquí no
se pierde el rastro de que el error existió y cómo se corrigió.

## T1 — Etiqueta anotada

```
git tag -a v0.8.0 -m "Ruta360 - cierre Manual 8 ..."
git show v0.8.0 --stat
```

Verificado: `git tag` lista `v0.8.0`; `git show v0.8.0 --stat` muestra
el mensaje de la etiqueta, el "tagger" correcto y el commit exacto al
que apunta (`bc98280`, el revert — el último commit de la actividad
guiada antes de etiquetar).

## M1 — Repositorio remoto real

Repositorio creado en GitHub (`https://github.com/christianpanche9-dot/ruta360`,
público, vacío al crearlo). Dos incidencias reales resueltas antes de
lograr el push (ver `docs/incidencia_manual_8.md`, secciones 2 y 3:
una URL mal formada con marcadores de plantilla sin sustituir, y un
nombre de usuario real distinto al asumido).

```
git push -u origin main
git push origin --tags
```

Resultado real:
```
 * [new branch]      main -> main
 * [new tag]         v0.8.0 -> v0.8.0
```

Verificado de forma independiente (sin usar las credenciales del
usuario) consultando la página pública del repositorio: rama por
defecto `main`, 8 commits, misma estructura de carpetas
(`app`, `config`, `docs`, `public`, `sql`, `storage`, `tests`) que el
proyecto local.

## P1 — `pathspec`/referencia inexistente

```
git add config/archivo_que_no_existe.php
git switch rama_que_no_existe
```

Resultado real: `fatal: pathspec '...' did not match any files` y
`fatal: invalid reference: rama_que_no_existe`. `git status`
inmediatamente después confirmó que no hubo ningún efecto — árbol de
trabajo limpio. Ver `docs/incidencia_manual_8.md`, sección 4.

## P2 — Push rechazado y reconciliación

Simulado con un segundo clon (`ruta360_colega`) desincronizado
respecto a `ruta360_m8`. Secuencia real completa:

1. `ruta360_m8` avanza `main` y hace `push` (éxito).
2. `ruta360_colega`, todavía en el commit anterior, hace su propio
   commit local y prueba `push` → **rechazado**:
   `! [rejected] main -> main (fetch first)`.
3. `git pull` sin configurar estrategia → falla:
   `fatal: Need to specify how to reconcile divergent branches`.
4. `git config pull.rebase false` (local) + `git pull origin main` →
   produce un conflicto real en `README.md` (ambas copias habían
   anotado la misma zona del archivo).
5. Conflicto resuelto conservando ambas anotaciones → `git commit
   --no-edit` → commit de fusión real (`dbf861d`).
6. `git push origin main` desde `ruta360_colega` → **éxito**
   (`4ecc27e..dbf861d`).
7. `ruta360_m8` sincronizado con `git fetch` + `git merge --ff-only
   origin/main`.

Ver `docs/incidencia_manual_8.md`, sección 5, para la narrativa
completa con las salidas reales de cada paso. Las notas de prueba se
retiraron de `README.md` y `ruta360_colega` se eliminó al terminar,
por ser solo una herramienta de demostración.
