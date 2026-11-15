---
titulo: Despliegue a producción (deploy.ps1)
resumen: Cómo subir los cambios al servidor con tools/deploy.ps1 desde cualquier PC, cómo desplegar lo que Claude subió a main y cómo configurar una PC nueva.
categoria: Configuración
tipo: guia
visibilidad: superadmin
etiquetas: deploy, desplegar, subir cambios al servidor, publicar en produccion, actualizar servidor, deploy.ps1, powershell, git pull, git push, claude code, cambios de claude, nada nuevo que subir, abortado, solo deploy, otra computadora, pc nueva, acceso directo en el escritorio, deploy camagare
version: 1.2
orden: 90
estado: activo
---

Los cambios del sistema se publican en el servidor de producción con el script
**`tools/deploy.ps1`** (PowerShell), que vive dentro del propio repositorio.
Sirve desde cualquier PC que tenga una copia del proyecto: el script deduce la
carpeta del proyecto a partir de su propia ubicación. Normalmente se lanza con
el acceso directo **«Deploy CaMaGaRe»** del escritorio.

## Qué hace el script, paso a paso

| Paso | Qué hace |
|---|---|
| **1/5 Cambios locales** | Si hay archivos modificados, los muestra, pide un mensaje y hace el commit (la carpeta `web` se excluye siempre: es otro repositorio). |
| **2/5 Sincronizar** | `git pull --rebase origin main`: trae lo que esté en GitHub (incluido lo que subió Claude) y pone encima tu commit. |
| **3/5 Revisión** | Si no tienes cambios propios que subir, consulta por SSH la versión del servidor: si producción ya está al día con `main`, termina (*«Nada que hacer»*); si está atrasada (por ejemplo, Claude subió cambios), sigue con lo que llegó de `main` y, en el paso 4, pregunta si desplegarlo. Si el cambio trae SQL, pregunta si ya se aplicó en producción (**el SQL va primero**); avisa también si cambió `composer.lock` o el manual. |
| **4/5 Push** | `git push origin main` (se salta si no hay cambios tuyos; antes pregunta *«¿Desplegar en producción lo que llegó de main?»*). |
| **5/5 Deploy** | Por SSH: `git pull` en el servidor y recarga de Apache. |

## Cómo se ejecuta

Desde la carpeta del proyecto (o con el acceso directo, ver abajo):

```
.\tools\deploy.ps1                         # flujo normal (pide el mensaje del commit)
.\tools\deploy.ps1 "mensaje del commit"    # con mensaje
.\tools\deploy.ps1 -SoloDeploy             # solo despliega lo que ya está en origin/main
```

Al terminar, si el cambio tocó `docs/manual/`, pulsar **Sincronizar** en
`/documentacion/gestion`.

## Cambios hechos con Claude

- **Claude en la nube** (claude.ai/code): sube los cambios **directo a `main`**
  en GitHub. Para tenerlos basta con el **acceso directo** (flujo normal): el
  paso 2 los trae a la PC y el script los despliega en producción, tengas o no
  cambios propios. Si traen SQL, lo pide primero.
- **Claude Code instalado en la PC** trabajando sobre la carpeta del proyecto:
  los cambios quedan como archivos modificados y los toma el paso 1.
- Si Claude entrega un **SQL**, se ejecuta primero en la base de producción
  (pgAdmin) y después se despliega el código.

## Configurar una PC nueva

1. Clonar el repositorio (o usar la copia que ya existe, p. ej. `C:\xampp\htdocs\sistema`).
2. Traer la última versión: `git checkout main` y luego `git pull origin main`.
3. Verificar que la PC entra al servidor por SSH sin pedir contraseña (llave SSH
   autorizada en el servidor); sin eso falla el paso 5.
4. Si PowerShell no deja ejecutar scripts, permitirlo para el usuario:
   `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned`.
5. **Acceso directo en el escritorio**: se crea con `tools\crear-acceso-directo.ps1`,
   o a mano apuntando al archivo del proyecto (**no** a una copia), para que
   siempre se use la versión actualizada:
   `powershell.exe -NoExit -ExecutionPolicy Bypass -File "C:\xampp\htdocs\sistema\tools\deploy.ps1"`
   (ajustar la ruta a la carpeta del proyecto en esa PC).
6. Opcional: para que `git pull` no abra el editor Vim al juntar cambios,
   `git config --global core.editor notepad`.

## Errores frecuentes

- **«Nada que hacer»**: no hay cambios tuyos y producción ya tiene lo último
  de `main`. No es un error.
- **«no pude consultar la version del servidor por SSH»**: revisar la conexión
  o la llave SSH; mientras tanto, `.\tools\deploy.ps1 -SoloDeploy`.
- **«hay un rebase a medias»**: terminarlo (`git rebase --continue`) o
  cancelarlo (`git rebase --abort`) y relanzar.
- **«You have not concluded your merge (MERGE_HEAD exists)»** al hacer
  `git pull`: hay una fusión a medias. Revisar con `git status`; si no hay
  trabajo propio que conservar, `git merge --abort` y repetir el `git pull`.
- **Se abre Vim con «Merge branch 'main' …»**: es el mensaje del merge. Salir
  con `Esc`, escribir `:wq` y `Enter`.
- **El rebase del paso 2 tiene conflictos**: resolverlos, `git add <archivo>` y
  `git rebase --continue`; el commit local ya está hecho, relanzar el script.
- **«EL PUSH SÍ SE HIZO, pero falló el deploy»**: reintentar con
  `.\tools\deploy.ps1 -SoloDeploy`.

## Historial de cambios

- **1.2** — Sin cambios propios, el flujo normal ya no se detiene: si
  producción está atrasada respecto a `main` (cambios de Claude en la nube),
  revisa SQL y manual y ofrece desplegar sin push. Ya no hace falta
  `-SoloDeploy` para los cambios de Claude.
- **1.1** — El script vuelve a la versión de 5 pasos, sin fusionar ramas
  `claude/*`. Claude en la nube sube directo a `main`; se despliega con
  `-SoloDeploy`. Nuevos errores frecuentes: merge a medias y Vim.
- **1.0** — Guía inicial: pasos del script, ramas de Claude, PC nueva y acceso directo.
