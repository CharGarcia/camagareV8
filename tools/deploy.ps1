<#
.SYNOPSIS
    Commit + push a origin/main y despliegue en el servidor de produccion.

.DESCRIPTION
    Ejecuta en orden: commit, pull --rebase, revision de lo que trae el cambio,
    push y despliegue por SSH (git pull + reload de Apache).
    Se detiene en cuanto un paso falla e indica si el push llego a hacerse.

    El commit va ANTES del rebase a proposito: git se niega a rebasar con el
    arbol de trabajo sucio.

.PARAMETER Mensaje
    Mensaje del commit. Si se omite, se pide por pantalla.

.PARAMETER SoloDeploy
    Salta commit y push: solo despliega en el servidor lo que ya esta en origin/main.
    Util si el push se hizo pero el deploy fallo.

.EXAMPLE
    .\deploy.ps1 "migra pedidos"
    .\deploy.ps1 -SoloDeploy
#>
param(
    [Parameter(Position = 0)]
    [string]$Mensaje,
    [switch]$SoloDeploy
)

# ---------------------------------------------------------------- configuracion
$SRV         = 'root@24.199.83.113'
$RUTA_REMOTA = '/var/www/sistema'

# El script vive en <repo>/tools/, asi que el repo se deduce solo:
# funciona en cualquier PC sin importar donde este el proyecto.
$REPO = Split-Path -Parent $PSScriptRoot

# ---------------------------------------------------------------- utilidades
function Paso($n, $texto) { Write-Host "`n[$n] $texto" -ForegroundColor Cyan }
function Abortar($texto)  { Write-Host "`nABORTADO: $texto`n" -ForegroundColor Red; exit 1 }
function Aviso($texto)    { Write-Host "  !! $texto" -ForegroundColor Yellow }

# ---------------------------------------------------------------- validaciones
if (-not (Test-Path (Join-Path $REPO '.git'))) {
    Abortar "no encuentro el repositorio en $REPO"
}
Set-Location $REPO
Write-Host "Repositorio: $REPO" -ForegroundColor DarkGray

if (Test-Path (Join-Path $REPO '.git\rebase-merge')) {
    Abortar "hay un rebase a medias. Terminalo con 'git rebase --continue' o cancelalo con 'git rebase --abort'."
}

# ---------------------------------------------------------------- solo deploy
if ($SoloDeploy) {
    Paso '1/1' 'Desplegando en produccion (sin commit ni push)...'
    ssh $SRV "cd $RUTA_REMOTA && git pull origin main && systemctl reload apache2 && echo '--- DEPLOY OK ---'"
    if ($LASTEXITCODE -ne 0) { Abortar 'fallo el deploy en el servidor.' }
    Write-Host "`nLISTO.`n" -ForegroundColor Green
    exit 0
}

# ---------------------------------------------------------------- 1. commit
Paso '1/5' 'Cambios locales:'
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
Paso '2/5' 'Sincronizando con el remoto (pull --rebase)...'
git pull --rebase origin main
if ($LASTEXITCODE -ne 0) {
    Write-Host "`nEl rebase no pudo completarse." -ForegroundColor Red
    Write-Host "Si hay conflictos: resuelvelos, 'git add <archivo>' y 'git rebase --continue'." -ForegroundColor Yellow
    Write-Host "Para deshacer el rebase:  git rebase --abort" -ForegroundColor Yellow
    Write-Host "Tu commit local ya esta hecho; despues relanza el script.`n" -ForegroundColor Yellow
    exit 1
}

# ---------------------------------------------------------------- 3. revision
Paso '3/5' 'Revisando que trae el cambio...'
$arch = git diff --name-only origin/main HEAD
if (-not $arch) { Abortar 'no hay nada nuevo que subir ni desplegar.' }

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
        Abortar 'aplica el SQL en produccion y relanza. El commit local ya esta hecho.'
    }
}
if (-not $sql.Count -and -not $lock.Count -and -not $doc.Count) {
    Write-Host '  Sin SQL, sin composer.lock, sin docs. Todo limpio.' -ForegroundColor DarkGray
}

# ---------------------------------------------------------------- 4. push
Paso '4/5' 'Push a origin/main...'
git push origin main
if ($LASTEXITCODE -ne 0) { Abortar 'fallo el push. Nada se desplego.' }

# ---------------------------------------------------------------- 5. deploy
Paso '5/5' "Desplegando en produccion ($SRV)..."
ssh $SRV "cd $RUTA_REMOTA && git pull origin main && systemctl reload apache2 && echo '--- DEPLOY OK ---'"
if ($LASTEXITCODE -ne 0) {
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
