<#
.SYNOPSIS
    Crea en el Escritorio un acceso directo a deploy.ps1.

.DESCRIPTION
    Los accesos directos (.lnk) no viajan por Git: hay que ejecutarlo una vez
    en cada PC. Doble clic en el acceso directo -> pide el mensaje del commit
    -> commit + push + deploy, y la ventana queda abierta con el resultado.

.EXAMPLE
    .\tools\crear-acceso-directo.ps1
#>

$deploy    = Join-Path $PSScriptRoot 'deploy.ps1'
$repo      = Split-Path -Parent $PSScriptRoot
$escritorio = [Environment]::GetFolderPath('Desktop')
$destino   = Join-Path $escritorio 'Deploy CaMaGaRe.lnk'

if (-not (Test-Path $deploy)) {
    Write-Host "No encuentro $deploy" -ForegroundColor Red
    exit 1
}

$ws = New-Object -ComObject WScript.Shell
$lnk = $ws.CreateShortcut($destino)
$lnk.TargetPath       = "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe"
$lnk.Arguments        = "-NoExit -ExecutionPolicy Bypass -File `"$deploy`""
$lnk.WorkingDirectory = $repo
$lnk.IconLocation     = "$env:SystemRoot\System32\shell32.dll,147"
$lnk.Description      = 'Commit + push + deploy a produccion (CaMaGaRe)'
$lnk.Save()

Write-Host "Acceso directo creado en:" -ForegroundColor Green
Write-Host "  $destino"
