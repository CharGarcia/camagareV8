@echo off
chcp 65001 >nul
set FECHA=%date:~-4,4%-%date:~-10,2%-%date:~-7,2%_%time:~0,2%-%time:~3,2%-%time:~6,2%
set FECHA=%FECHA: =0%
set DESTINO=c:\xampp\htdocs\sistema_backup_%FECHA%.zip
set ORIGEN=c:\xampp\htdocs\sistema

echo Creando respaldo...
echo Excluyendo: vendor, node_modules, .git
echo Destino: %DESTINO%
echo.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0crear_respaldo.ps1" -Origen "%ORIGEN%" -Destino "%DESTINO%"

if exist "%DESTINO%" (
    echo.
    echo Respaldo creado correctamente: %DESTINO%
    for %%A in ("%DESTINO%") do echo Tamaño: %%~zA bytes
) else (
    echo Error al crear el respaldo. Ejecutando backup completo...
    powershell -NoProfile -Command "Compress-Archive -Path '%ORIGEN%\*' -DestinationPath '%DESTINO%' -CompressionLevel Fastest -Force"
)

echo.
pause
