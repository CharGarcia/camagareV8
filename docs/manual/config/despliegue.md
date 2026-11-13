---
titulo: Despliegue a producción (deploy.ps1)
resumen: Cómo subir los cambios al servidor con tools/deploy.ps1 desde cualquier PC, incluidos los cambios hechos por Claude en ramas claude/*, y cómo configurar una PC nueva.
categoria: Configuración
tipo: guia
visibilidad: superadmin
etiquetas: deploy, desplegar, subir cambios al servidor, publicar en produccion, actualizar servidor, deploy.ps1, powershell, git pull, git push, rama de claude, claude code, pull request, nada nuevo que subir, abortado, otra computadora, pc nueva, acceso directo en el escritorio, solo deploy
version: 1.0
orden: 90
estado: activo
---

Los cambios del sistema se publican en el servidor de producción con el script
**`tools/deploy.ps1`** (PowerShell), que vive dentro del propio repositorio.
Sirve desde cualquier PC que tenga una copia del proyecto: el script deduce la
carpeta del proyecto a partir de su propia ubicación.

## Qué hace el script, paso a paso

| Paso | Qué hace |
|---|---|
| **1/6 Cambios locales** | Si hay archivos modificados, pide un mensaje y hace el commit (la carpeta `web` se excluye siempre: es otro repositorio). |
| **2/6 Sincronizar** | `git pull --rebase origin main`. |
| **3/6 Ramas de Claude** | Busca en GitHub las ramas `claude/*` con cambios que `main` aún no tiene, muestra sus commits y pregunta si fusionar cada una. Si una choca con `main`, deshace la fusión y se detiene. |
| **4/6 Revisión** | Avisa si el cambio trae SQL (pregunta si ya se aplicó en producción: **el SQL va primero**), `composer.lock` o archivos del manual. |
| **5/6 Push** | `git push origin main`. |
| **6/6 Deploy** | Por SSH: `git pull` en el servidor y recarga de Apache. |

Si no hay nada nuevo que subir pero el servidor **no** tiene la última versión
de `main` (por ejemplo, porque se fusionó un Pull Request en GitHub), ofrece
desplegar igual en lugar de abortar.

## Cómo se ejecuta

Desde la carpeta del proyecto (o con un acceso directo, ver abajo):

```
.\tools\deploy.ps1                         # flujo normal
.\tools\deploy.ps1 "mensaje del commit"    # con mensaje
.\tools\deploy.ps1 -Rama claude/nombre     # fusiona esa rama sin preguntar
.\tools\deploy.ps1 -SinRamas               # no busca ramas de Claude
.\tools\deploy.ps1 -SoloDeploy             # solo despliega lo que ya está en origin/main
```

Al terminar, si el cambio tocó `docs/manual/`, pulsar **Sincronizar** en
`/documentacion/gestion`.

## Cambios hechos con Claude

- **Claude en la nube** (claude.ai/code): los cambios quedan en una rama
  `claude/...`, no en `main`. El paso 3 las muestra: responder **s** para
  fusionarlas y desplegarlas.
- **Claude Code instalado en la PC** trabajando sobre la carpeta del proyecto:
  los cambios quedan como archivos modificados y los toma el paso 1.
- Si un Pull Request ya se fusionó en GitHub, el script lo detecta en el paso 4
  y ofrece desplegarlo.

## Configurar una PC nueva

1. Clonar el repositorio (o usar la copia que ya existe, p. ej. `C:\xampp\htdocs\sistema`).
2. Traer la última versión, para tener el script nuevo:
   `git checkout main` y luego `git pull origin main`.
3. Verificar que la PC entra al servidor por SSH sin pedir contraseña (llave SSH
   autorizada en el servidor); sin eso falla el paso 6.
4. Si PowerShell no deja ejecutar scripts, permitirlo para el usuario:
   `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned`.
5. **Acceso directo en el escritorio**: crearlo apuntando al archivo del
   proyecto, **no** a una copia, para que siempre se use la versión actualizada.
   Destino del acceso directo:
   `powershell.exe -NoExit -ExecutionPolicy Bypass -File "C:\xampp\htdocs\sistema\tools\deploy.ps1"`
   (ajustar la ruta a la carpeta del proyecto en esa PC).

## Cómo saber si se usa la versión nueva del script

Los pasos deben decir **[1/6] … [6/6]** y aparecer **[3/6] Ramas de Claude
pendientes**. Si dice **[1/5] … [5/5]** es una versión vieja o una copia:

- Si es la primera ejecución en esa PC, el propio paso 2 ya trajo el script
  nuevo: la siguiente ejecución usará la versión nueva.
- Si es una **copia** en el escritorio, borrarla y crear un acceso directo al
  archivo de `tools\` (paso 5 de la sección anterior).

## Errores frecuentes

- **«ABORTADO: no hay nada nuevo que subir ni desplegar»**: `main` local, el
  remoto y el servidor ya están al día. Si se esperaba un cambio hecho por
  Claude, revisar que su rama exista en GitHub o que el script sea el nuevo.
- **«estás en la rama …»**: cambiar a `main` con `git checkout main`.
- **«hay un merge a medias» / «hay un rebase a medias»**: terminar o cancelar
  (`git merge --abort` / `git rebase --abort`) y relanzar.
- **Conflicto al fusionar una rama de Claude**: no se fusiona nada de esa rama.
  Pedirle a Claude que la actualice con `main` y relanzar.
- **«EL PUSH SÍ SE HIZO, pero falló el deploy»**: reintentar con
  `.\tools\deploy.ps1 -SoloDeploy`.

## Historial de cambios

- **1.0** — Guía inicial: pasos del script, ramas de Claude, PC nueva y acceso directo.
