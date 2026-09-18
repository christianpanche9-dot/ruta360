# Incidencias — Manual 8 (Control de versiones con Git)

Tres incidencias reales, no planificadas, surgidas durante la actividad
guiada de este manual — documentadas en el mismo formato que los
manuales anteriores (síntoma/diagnóstico/causa raíz/corrección/
verificación/lección).

## 1. `git config` sin efecto antes de `git init`

**Síntoma**: se ejecutaron `git config user.name`, `git config
user.email` y `git config init.defaultBranch main` antes de crear el
repositorio (8.5), esperando que quedaran listos para el primer
`git init`. Tras `git init`, la rama creada fue `master`, no `main`, y
`cat .git/config` no mostraba ninguna sección `[user]`.

**Diagnóstico**: `git config` sin `--global` escribe en
`<repositorio>/.git/config`. Si ese archivo aún no existe (porque el
repositorio no se ha creado todavía), el comando no tiene dónde
escribir la configuración local y no falla de forma visible — no hay
mensaje de error, simplemente no persiste nada.

**Causa raíz**: orden de los pasos. `git config` local necesita que el
repositorio (`.git/`) ya exista.

**Corrección**:
```bash
git init
git branch -m main
git config user.name "Cristhian Panche"
git config user.email "cristhian@example.com"
```

**Verificación**: `cat .git/config` mostró la sección `[user]` con el
nombre y correo correctos; `git branch` mostró únicamente `main`, sin
`master`.

**Lección**: `git config init.defaultBranch` y la identidad local solo
tienen efecto real *después* de que el repositorio exista — antes de
eso, `--global` es la única opción que persiste algo, o hay que
reordenar los pasos.

## 2. URL de remoto mal formada — error 400

**Síntoma**: al ejecutar `git remote add origin
https://github.com/<usuario>/ruta360.git` con el marcador `<usuario>`
copiado literalmente (incluidos los símbolos `<` `>`), `git push`
devolvió `The requested URL returned error: 400` en vez de un error de
autenticación o de repositorio no encontrado.

**Diagnóstico**: los símbolos `<` y `>` no forman parte de ninguna URL
válida de GitHub. El error 400 (petición mal formada) es coherente con
una URL que el servidor ni siquiera puede interpretar como una ruta de
repositorio válida — a diferencia de un 404, que habría indicado una
ruta bien formada pero inexistente.

**Causa raíz**: un marcador de plantilla (`<usuario>`, indicando "sustituir por tu usuario real") se copió sin sustituir el marcador por el valor real.

**Corrección**: `git remote set-url origin
https://github.com/christianpanche9/ruta360.git` (sin corchetes).

**Verificación**: el segundo intento ya no devolvió 400, sino `remote:
Repository not found.` — un error distinto, confirmando que la URL en
sí ya era sintácticamente válida.

**Lección**: un error 400 en una URL apunta casi siempre a un problema
de formato de la propia URL, no de permisos ni de existencia del
recurso — hay que revisar el texto literal de la URL antes de asumir
un problema de credenciales.

## 3. Nombre de usuario real distinto al esperado — "Repository not found"

**Síntoma**: con la URL ya bien formada
(`https://github.com/christianpanche9/ruta360.git`) y el repositorio
ya creado en GitHub, `git push` seguía devolviendo `remote: Repository
not found.`

**Diagnóstico**: al abrir la página del repositorio en el navegador,
la cuenta real mostrada en GitHub era `christianpanche9-dot`, no
`christianpanche9` — GitHub había ajustado el nombre de usuario
respecto al que se asumía.

**Causa raíz**: suposición incorrecta del nombre de cuenta exacto; no
se verificó contra la URL real que GitHub mostraba en la propia
página del repositorio antes de configurar el remoto.

**Corrección**: `git remote set-url origin
https://github.com/christianpanche9-dot/ruta360.git`.

**Verificación**: `git push -u origin main` y `git push origin --tags`
completaron con éxito (`* [new branch] main -> main`, `* [new tag]
v0.8.0 -> v0.8.0`), confirmado además de forma independiente
consultando la página pública del repositorio (8 commits en `main`,
misma estructura de carpetas que el proyecto local).

**Lección**: al configurar un remoto, copiar la URL exacta que
GitHub muestra en su propia interfaz (botón "Copy" de la URL HTTPS)
es más fiable que reconstruirla de memoria a partir del nombre de
usuario asumido.

## 4. `pathspec ... did not match` (8.21, ejercicio opcional)

**Síntoma**: al ejecutar a propósito `git add
config/archivo_que_no_existe.php` y `git switch rama_que_no_existe`
sobre `ruta360_m8`.

**Salida real**:
```
fatal: pathspec 'config/archivo_que_no_existe.php' did not match any files
fatal: invalid reference: rama_que_no_existe
```

**Diagnóstico**: en ambos casos Git rechaza la operación antes de
tocar nada, porque ni el archivo ni la rama existen. `git status`
inmediatamente después confirmó un árbol de trabajo limpio — ningún
efecto secundario.

**Causa raíz**: provocada a propósito (nombre de archivo/rama
inventado), para observar el mensaje real. Nótese que la versión de
Git usada aquí dice *"did not match any files"*, no la redacción más
antigua *"did not match any file(s) known to git"* que aparece en
algunas versiones — el mensaje exacto varía según la versión de Git.

**Corrección**: no aplica (nada que corregir; son las operaciones
originales las que no debían ejecutarse con esos nombres).

**Lección**: Git falla de forma segura ante un pathspec o una
referencia inexistente — no crea nada a medias.

## 5. Push rechazado (`non-fast-forward`) y conflicto durante la reconciliación (8.21, ejercicio opcional)

**Síntoma**: se simuló un escenario real de dos copias divergentes
del mismo repositorio: `ruta360_m8` (este proyecto) avanzó `main` con
un commit y lo subió; un segundo clon independiente,
`ruta360_colega` (clonado antes de ese commit, representando a un
colega que no había actualizado su copia), intentó subir su propio
commit sin haber integrado antes los cambios remotos.

**Salida real** (primer intento, `git push` directo):
```
 ! [rejected]        main -> main (fetch first)
error: failed to push some refs to '...'
hint: Updates were rejected because the remote contains work that you do not
hint: have locally. ...
```

**Diagnóstico**: el rechazo es el comportamiento correcto y
esperado — Git nunca sobrescribe silenciosamente el trabajo de otra
persona en el remoto.

**Complicación adicional real, no anticipada**: `git pull` sin más
falló con `fatal: Need to specify how to reconcile divergent
branches` porque esta instalación de Git no tenía configurada una
estrategia por defecto (`pull.rebase`). Se resolvió con `git config
pull.rebase false` (local a `ruta360_colega`, no global, siguiendo el
mismo criterio usado con la identidad del Manual 8).

Tras eso, `git pull origin main` sí produjo el conflicto esperado —
pero en un archivo distinto al de la simulación original
(`README.md`, porque tanto la nota "de colega" como la nota de
demostración se habían añadido en la misma zona del archivo).
Resuelto conservando ambas notas, sin descartar ninguna, igual que
en el conflicto de 8.15. `git commit --no-edit` completó el merge
(commit `dbf861d`, con dos padres).

**Verificación**: `git push origin main` desde `ruta360_colega`
completó con éxito (`4ecc27e..dbf861d main -> main`). `ruta360_m8` se
sincronizó después con `git fetch` + `git merge --ff-only
origin/main`, confirmando que ambas copias locales y el remoto
quedaron con el mismo historial.

**Limpieza**: las notas de esta demostración se retiraron de
`README.md` en un commit posterior, y el clon `ruta360_colega` (solo
una herramienta de demostración, no parte del proyecto) se eliminó.

**Lección**: un push rechazado nunca se debe forzar
(`git push --force`) para "que funcione" — eso descartaría el
trabajo remoto sin avisar a nadie. La secuencia correcta es
`git pull` (integrar), resolver cualquier conflicto que surja, y
recién entonces volver a intentar el `push`.
