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
