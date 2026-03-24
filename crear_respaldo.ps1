param(
    [string]$Origen = "c:\xampp\htdocs\sistema",
    [string]$Destino = "c:\xampp\htdocs\sistema_backup.zip"
)

# Excluir vendor, node_modules, .git (pesados, se reinstalan con composer/npm)
$temp = Join-Path $env:TEMP "sistema_backup_$(Get-Date -Format 'yyyyMMddHHmmss')"
New-Item -ItemType Directory -Path $temp -Force | Out-Null

robocopy $Origen $temp /E /XD vendor node_modules .git /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null

Compress-Archive -Path "$temp\*" -DestinationPath $Destino -CompressionLevel Fastest -Force
Remove-Item -Path $temp -Recurse -Force -ErrorAction SilentlyContinue
