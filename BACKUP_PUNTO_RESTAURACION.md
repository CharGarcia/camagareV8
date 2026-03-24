# Punto de restauración - 17 Mar 2025

## Estado guardado
- **Menú de módulos** funcionando correctamente debajo del navbar
- **Dropdown de empresas** visible por encima de todo (portal con z-index máximo)
- **Dropdowns de módulos** visibles por encima del menú (portal)
- Submódulos se muestran al pasar el mouse
- Solo un dropdown visible a la vez
- Ajuste de posición cuando el dropdown se sale por la derecha

## Archivos modificados en esta sesión
- `app/views/layouts/main.php` - Portal, orden navbar/menú
- `app/views/partials/menu-modulos.php` - Hover, portal, posicionamiento
- `app/views/partials/navbar.php` - Sin cambios estructurales
- `public/css/app.css` - Portal, z-index, estilos dropdown
- `public/js/app.js` - Dropdown empresas en portal, posicionamiento

## Cómo restaurar
1. **Si usas Git**: `git checkout -- .` o `git reset --hard <commit>`
2. **Si tienes ZIP**: descomprimir `sistema_backup_YYYY-MM-DD_HH-mm.zip` sobre la carpeta
3. **Manual**: restaurar los archivos listados arriba desde tu copia de seguridad

---
*Creado automáticamente como punto de restauración*
