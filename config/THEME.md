# Configuración de colores / tema

## Dónde configurar

### Opción 1: Archivo de configuración (actual)
**Archivo:** `config/theme.php`

Edita el array para cambiar colores:

```php
'primary' => [
    'main' => '#6eb5d0',   // Celeste pastel
    'hover' => '#5ca3bd',
    'text' => '#ffffff',
],
'body' => [
    'gradient_start' => '#e8f4f8',
    'gradient_end' => '#f0f7fa',
    'gradient_angle' => '135deg',
],
```

### Opción 2: Presets disponibles
En `config/theme.php` hay presets de celeste. Para usar uno:

```php
'primary' => [
    'main' => $theme['presets']['celeste_claro'],  // #87CEEB
    ...
],
```

| Preset          | Código   | Descripción      |
|-----------------|----------|------------------|
| celeste_suave   | #6eb5d0  | Celeste medio    |
| celeste_claro   | #87CEEB  | Sky blue         |
| celeste_polvo   | #B0E0E6  | Powder blue      |
| celeste_agua    | #7EC8E3  | Agua             |
| azul_cielo      | #89CFF0  | Baby blue        |
| verde_agua      | #7FDBDA  | Turquesa suave   |

### Opción 3: Módulo Configuración (futuro)
Se puede crear en **Configuración > Apariencia** un formulario para:
- Elegir color principal
- Degradado del body
- Guardar en base de datos
- Generar `theme-vars.php` dinámicamente o guardar en `config/theme.php`

---

## Archivos involucrados

| Archivo              | Función                              |
|----------------------|--------------------------------------|
| `config/theme.php`   | Define colores (editar aquí)         |
| `app/views/partials/theme-vars.php` | Inyecta variables CSS en el head |
| `public/css/theme.css` | Aplica variables a body, navbar, botones |
