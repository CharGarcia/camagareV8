<#
.SYNOPSIS
    Commit + push a origin/main y despliegue en el servidor de produccion.

.DESCRIPTION
    Ejecuta en orden: commit, pull --rebase, fusion de las ramas de Claude
    pendientes, revision de lo que trae el cambio, push y despliegue por SSH
    (git pull + reload de Apache).
    Se detiene en cuanto un paso falla e indica si el push llego a hacerse.

    El commit va ANTES del rebase a proposito: git se niega a rebasar con el
    arbol de trabajo sucio.

    Ramas de Claude (claude/*): los cambios hechos por Claude Code en la nube
    quedan en una rama propia, no en main. El paso 3 las busca en el remoto y,
    por cada una que tenga commits que main aun no tiene, muestra esos commits
    y pregunta si fusionarla. La fusion se hace DESPUES del rebase para no
    aplanar el merge.

    Si no hay nada nuevo que subir pero el servidor esta atrasado respecto a
    origin/main (p. ej. un Pull Request se fusiono en GitHub), ofrece
    desplegar igual en lugar de abortar.

.PARAMETER Mensaje
    Mensaje del commit. Si se omite, se pide por pantalla.

.PARAMETER Rama
    Rama del remoto a fusionar sin preguntar (p. ej. claude/confident-darwin-vveq0a).
    Si se omite, se revisan las ramas claude/* pendientes y se pregunta por cada una.

.PARAMETER SinRamas
    No busca ni fusiona ramas de Claude.

.PARAMETER SoloDeploy
    Salta commit y push: solo despliega en el servidor lo que ya esta en origin/main.
    Util si el push se hizo pero el deploy fallo.

.EXAMPLE
    .\deploy.ps1 "migra pedidos"
    .\deploy.ps1 -Rama claude/confident-darwin-vveq0a
    .\deploy.ps1 -SinRamas
    .\deploy.ps1 -SoloDeploy
#>
param(
    [Parameter(Position = 0)]
    [string]$Mensaje,
    [string]$Rama,
    [switch]$SinRamas,
    [switch]$SoloDeploy
)

# ---------------------------------------------------------------- configuracion
$SRV         = 'root@24.199.83.113'
$RUTA_REMOTA = '/var/www/sistema'
$PREFIJO_RAMAS_CLAUDE = 'claude/'

# El script vive en <repo>/tools/, asi que el repo se deduce solo:
# funciona en cualquier PC sin importar donde este el proyecto.
$REPO = Split-Path -Parent $PSScriptRoot

# ---------------------------------------------------------------- utilidades
function Paso($n, $texto) { Write-Host "`n[$n] $texto" -ForegroundColor Cyan }
function Abortar($texto)  { Write-Host "`nABORTADO: $texto`n" -ForegroundColor Red; exit 1 }
function Aviso($texto)    { Write-Host "  !! $texto" -ForegroundColor Yellow }

function Desplegar {
    # Out-Host: la salida de ssh se muestra pero no se mezcla con el valor que devuelve la funcion.
    ssh $SRV "cd $RUTA_REMOTA && git pull origin main && systemctl reload apache2 && echo '--- DEPLOY OK ---'" | Out-Host
    return ($LASTEXITCODE -eq 0)
}

# Fusiona origin/<rama> en main. Si hay conflictos, deshace la fusion y aborta.
function FusionarRama($nombre) {
    Write-Host "  Fusionando origin/$nombre ..." -ForegroundColor DarkGray
    git merge --no-edit "origin/$nombre" | Out-Host
    if ($LASTEXITCODE -ne 0) {
        git merge --abort 2>$null
        Write-Host "`nLa rama $nombre tiene conflictos con main: no se fusiono nada de ella." -ForegroundColor Red
        Write-Host "Resuelvela a mano (git merge origin/$nombre) o pidele a Claude que la actualice con main." -ForegroundColor Yellow
        Abortar 'conflicto al fusionar la rama. Lo que ya estaba commiteado sigue intacto.'
    }
}

# ---------------------------------------------------------------- validaciones
if (-not (Test-Path (Join-Path $REPO '.git'))) {
    Abortar "no encuentro el repositorio en $REPO"
}
Set-Location $REPO
Write-Host "Repositorio: $REPO" -ForegroundColor DarkGray

if (Test-Path (Join-Path $REPO '.git\rebase-merge')) {
    Abortar "hay un rebase a medias. Terminalo con 'git rebase --continue' o cancelalo con 'git rebase --abort'."
}
if (Test-Path (Join-Path $REPO '.git\MERGE_HEAD')) {
    Abortar "hay un merge a medias. Terminalo con 'git commit' o cancelalo con 'git merge --abort'."
}

# ---------------------------------------------------------------- solo deploy
if ($SoloDeploy) {
    Paso '1/1' 'Desplegando en produccion (sin commit ni push)...'
    if (-not (Desplegar)) { Abortar 'fallo el deploy en el servidor.' }
    Write-Host "`nLISTO.`n" -ForegroundColor Green
    exit 0
}

# El script hace push de main: hay que estar parado en main.
$ramaActual = (git rev-parse --abbrev-ref HEAD).Trim()
if ($ramaActual -ne 'main') {
    Abortar "estas en la rama '$ramaActual'. Cambiate a main con 'git checkout main' y relanza."
}

# ---------------------------------------------------------------- 1. commit
Paso '1/6' 'Cambios locales:'
# 'web' se excluye siempre: es otro repo (sitio legacy) y commitearlo lo destruiria.
$pendientes = git status --porcelain -- . ':(exclude)web'

if ($pendientes) {
    $pendientes | ForEach-Object { Write-Host "  $_" }

    if ([string]::IsNullOrWhiteSpace($Mensaje)) {
        $Mensaje = Read-Host "`nMensaje del commit"
        if ([string]::IsNullOrWhiteSpace($Mensaje)) { Abortar 'hace falta un mensaje de commit.' }
    } else {
        $r = Read-Host "`nCommitear estos archivos como '$Mensaje'? (S/n)"
        if ($r -and $r -ne 's' -and $r -ne 'S') { Abortar 'cancelado por el usuario.' }
    }

    git add -A -- . ':(exclude)web'
    git commit -m $Mensaje
    if ($LASTEXITCODE -ne 0) { Abortar 'fallo el commit.' }
} else {
    Write-Host '  (sin cambios sin commitear)' -ForegroundColor DarkGray
}

# ---------------------------------------------------------------- 2. sincronizar
Paso '2/6' 'Sincronizando con el remoto (pull --rebase)...'
git pull --rebase origin main
if ($LASTEXITCODE -ne 0) {
    Write-Host "`nEl rebase no pudo completarse." -ForegroundColor Red
    Write-Host "Si hay conflictos: resuelvelos, 'git add <archivo>' y 'git rebase --continue'." -ForegroundColor Yellow
    Write-Host "Para deshacer el rebase:  git rebase --abort" -ForegroundColor Yellow
    Write-Host "Tu commit local ya esta hecho; despues relanza el script.`n" -ForegroundColor Yellow
    exit 1
}

# ---------------------------------------------------------------- 3. ramas de Claude
Paso '3/6' 'Ramas de Claude pendientes de fusionar...'
if ($SinRamas -and -not $Rama) {
    Write-Host '  (omitido: -SinRamas)' -ForegroundColor DarkGray
} else {
    # --prune: olvida las ramas que ya se borraron en GitHub (p. ej. tras fusionar un PR).
    git fetch origin --prune --quiet
    if ($LASTEXITCODE -ne 0) { Abortar 'no se pudo consultar el remoto (git fetch).' }

    if ($Rama) {
        $Rama = $Rama.Trim() -replace '^origin/', ''
        git rev-parse --verify --quiet "origin/$Rama" > $null
        if ($LASTEXITCODE -ne 0) { Abortar "no existe la rama origin/$Rama en el remoto." }
        $candidatas = @($Rama)
        $preguntar  = $false
    } else {
        # Solo las ramas claude/* que tienen commits que main aun no tiene.
        $candidatas = @(git branch -r --no-merged HEAD --list "origin/$PREFIJO_RAMAS_CLAUDE*" |
            ForEach-Object { ($_.Trim()) -replace '^origin/', '' } |
            Where-Object { $_ -and $_ -notmatch '->' })
        $preguntar  = $true
    }

    if (-not $candidatas.Count) {
        Write-Host '  (ninguna rama de Claude pendiente)' -ForegroundColor DarkGray
    }
    foreach ($nombre in $candidatas) {
        $commits = @(git log --oneline "HEAD..origin/$nombre")
        if (-not $commits.Count) {
            Write-Host "  origin/$nombre ya esta incluida en main." -ForegroundColor DarkGray
            continue
        }
        Write-Host "`n  Rama origin/$nombre trae $($commits.Count) commit(s):" -ForegroundColor White
        $commits | ForEach-Object { Write-Host "    $_" }
        if ($preguntar) {
            $r = Read-Host "  Fusionar esta rama en main y desplegarla? (s/N)"
            if ($r -ne 's' -and $r -ne 'S') {
                Write-Host '  (no se fusiona)' -ForegroundColor DarkGray
                continue
            }
        }
        FusionarRama $nombre
    }
}

# ---------------------------------------------------------------- 4. revision
Paso '4/6' 'Revisando que trae el cambio...'
$arch = git diff --name-only origin/main HEAD
if (-not $arch) {
    # Nada nuevo en main local, pero el servidor puede estar atrasado
    # (p. ej. un Pull Request que se fusiono directamente en GitHub).
    $enRemoto   = (git rev-parse origin/main).Trim()
    $enServidor = (ssh $SRV "cd $RUTA_REMOTA && git rev-parse HEAD" 2>$null)
    $sshOk      = ($LASTEXITCODE -eq 0)
    if (-not $sshOk) { Aviso 'no se pudo consultar la version del servidor por SSH.' }
    if ($sshOk -and $enServidor -and "$enServidor".Trim() -ne $enRemoto) {
        Aviso 'No hay nada nuevo que subir, pero el servidor NO tiene la ultima version de origin/main.'
        $pendSrv = @(git diff --name-only "$enServidor".Trim() $enRemoto 2>$null)
        if ($pendSrv | Where-Object { $_ -match '\.sql$' }) {
            Write-Host '  !! Lo pendiente en el servidor trae SQL: aplicalo en PRODUCCION antes de desplegar.' -ForegroundColor Red
        }
        if ($pendSrv | Where-Object { $_ -match '^docs/manual/' }) {
            Aviso 'cambio docs/manual -> pulsar Sincronizar en /documentacion/gestion'
        }
        $r = Read-Host "`nDesplegar en el servidor lo que ya esta en origin/main? (s/N)"
        if ($r -ne 's' -and $r -ne 'S') { Abortar 'cancelado por el usuario.' }
        Paso '6/6' "Desplegando en produccion ($SRV)..."
        if (-not (Desplegar)) { Abortar 'fallo el deploy en el servidor.' }
        Write-Host "`nLISTO.`n" -ForegroundColor Green
        exit 0
    }
    Abortar 'no hay nada nuevo que subir ni desplegar (el servidor ya tiene la ultima version).'
}

$sql  = @($arch | Where-Object { $_ -match '\.sql$' })
$lock = @($arch | Where-Object { $_ -match 'composer\.lock$' })
$doc  = @($arch | Where-Object { $_ -match '^docs/manual/' })

if ($lock.Count) { Aviso 'cambio composer.lock -> hay que correr composer install en el servidor' }
if ($doc.Count)  { Aviso 'cambio docs/manual -> pulsar Sincronizar en /documentacion/gestion' }
if ($sql.Count) {
    Write-Host '  !! Este cambio trae SQL:' -ForegroundColor Red
    $sql | ForEach-Object { Write-Host "     $_" -ForegroundColor Red }
    Write-Host '     Regla: el SQL va PRIMERO, el codigo despues.' -ForegroundColor Red
    $r = Read-Host "`nYa aplicaste el SQL en la base de PRODUCCION? (s/n)"
    if ($r -ne 's' -and $r -ne 'S') {
        Abortar 'aplica el SQL en produccion y relanza. El commit local (y la fusion de ramas) ya esta hecho.'
    }
}
if (-not $sql.Count -and -not $lock.Count -and -not $doc.Count) {
    Write-Host '  Sin SQL, sin composer.lock, sin docs. Todo limpio.' -ForegroundColor DarkGray
}

# ---------------------------------------------------------------- 5. push
Paso '5/6' 'Push a origin/main...'
git push origin main
if ($LASTEXITCODE -ne 0) { Abortar 'fallo el push. Nada se desplego.' }

# ---------------------------------------------------------------- 6. deploy
Paso '6/6' "Desplegando en produccion ($SRV)..."
if (-not (Desplegar)) {
    Write-Host "`nEL PUSH SI SE HIZO, pero fallo el deploy en el servidor." -ForegroundColor Red
    Write-Host "Reintenta solo el deploy con:  .\tools\deploy.ps1 -SoloDeploy`n" -ForegroundColor Yellow
    exit 1
}

# ---------------------------------------------------------------- resumen
if ($lock.Count -or $doc.Count) {
    Write-Host "`nPENDIENTES MANUALES:" -ForegroundColor Yellow
    if ($lock.Count) { Write-Host '  - composer install en el servidor' -ForegroundColor Yellow }
    if ($doc.Count)  { Write-Host '  - Sincronizar en /documentacion/gestion' -ForegroundColor Yellow }
}
Write-Host "`nLISTO.`n" -ForegroundColor Green
