<?php

declare(strict_types=1);

namespace App\Helpers;

use App\repositories\UsuarioPreferenciaRepository;
use App\Services\UsuarioPreferenciaService;

class PreferenciasHelper
{
    private static array $cache = [];

    /**
     * Renderiza el ícono de estrella para configurar favoritos en los formularios.
     */
    public static function renderEstrellaFavorito(string $modulo, string $idSelectUi, string $campoDb): string
    {
        return '<i class="bi bi-star cursor-pointer btn-favorito ms-1 text-muted transition-all" 
                   data-bs-toggle="tooltip" data-bs-placement="top" title="Marcar como favorito"
                   data-modulo="' . htmlspecialchars(str_replace('-', '_', basename($modulo))) . '" 
                   data-campo="' . htmlspecialchars($campoDb) . '" 
                   data-target="#' . htmlspecialchars($idSelectUi) . '"></i>';
    }

    /**
     * Inyecta las variables JS globales para el módulo en curso.
     */
    public static function getJavascriptVariables(string $modulo): string
    {
        $modulo = str_replace('-', '_', basename($modulo));
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);

        if ($idUsuario === 0) {
            $baseUrl = rtrim(BASE_URL ?? '', '/');
            return '<script>const APP_FAVORITOS = {}; const APP_FAVORITOS_URL = "' . $baseUrl . '/Preferencias/guardarAjax";</script>';
        }

        $preferencias = self::fetchPreferenciasCache($modulo, $idUsuario, $idEmpresa);
        $baseUrl = rtrim(BASE_URL ?? '', '/');

        return '<script>
            const APP_FAVORITOS = ' . json_encode($preferencias, JSON_UNESCAPED_UNICODE) . ';
            const APP_FAVORITOS_URL = "' . $baseUrl . '/Preferencias/guardarAjax";
            const APP_VISTAS_URL = "' . $baseUrl . '/Preferencias/guardarVistaAjax";
        </script>';
    }

    private static function fetchPreferenciasCache(string $modulo, int $idUsuario, int $idEmpresa): array
    {
        if (!isset(self::$cache[$modulo])) {
            $service = new UsuarioPreferenciaService(new UsuarioPreferenciaRepository());
            self::$cache[$modulo] = $service->obtenerPreferencias($idUsuario, $idEmpresa, $modulo);
        }
        return self::$cache[$modulo];
    }

    /**
     * Devuelve el objeto de la vista (columnas y ordenamiento) para un módulo.
     */
    public static function getPreferenciasVista(string $modulo): array
    {
        $modulo = str_replace('-', '_', basename($modulo));
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);

        if ($idUsuario === 0) {
            return [];
        }

        $preferencias = self::fetchPreferenciasCache($modulo, $idUsuario, $idEmpresa);
        return $preferencias['__vista__'] ?? [];
    }

    /**
     * Renderiza el bloque CSS que personaliza la vista (columnas ocultas y anchos).
     */
    public static function renderEstilosColumnasOcultas(array $vistaConfig, string $idStyle = 'estiloVistaColumnas'): string
    {
        $css = '';
        
        // 1. Columnas Ocultas
        if (!empty($vistaConfig['__columnas_ocultas__']) && is_array($vistaConfig['__columnas_ocultas__'])) {
            foreach ($vistaConfig['__columnas_ocultas__'] as $colVal) {
                $colVal = htmlspecialchars($colVal, ENT_QUOTES, 'UTF-8');
                $css .= "th[data-col=\"$colVal\"], td[data-col=\"$colVal\"] { display: none !important; }\n";
            }
        }

        // 2. Anchos de Columnas
        if (!empty($vistaConfig['__columnas_anchos__']) && is_array($vistaConfig['__columnas_anchos__'])) {
            foreach ($vistaConfig['__columnas_anchos__'] as $colKey => $width) {
                $colKey = htmlspecialchars((string)$colKey, ENT_QUOTES, 'UTF-8');
                $width = (int)$width;
                if ($width > 0) {
                    $css .= "th[data-col=\"$colKey\"], td[data-col=\"$colKey\"] { width: {$width}px !important; min-width: {$width}px !important; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }\n";
                }
            }
        }

        return "<style id=\"" . htmlspecialchars($idStyle) . "\">\n{$css}</style>";
    }

    /**
     * Renderiza un dropdown de Bootstrap con checkboxes para mostrar/ocultar columnas.
     * @param array $columnas Array asociativo donde Key = "data-col" y Value = "Etiqueta visible"
     * @param array $vistaConfig Objeto devuelto por getPreferenciasVista
     */
    public static function renderDropdownColumnas(array $columnas, array $vistaConfig, string $modulo): string
    {
        $ocultas = $vistaConfig['__columnas_ocultas__'] ?? [];

        $html = '
        <div class="dropdown d-inline-block dropdown-vista-columnas">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Personalizar Columnas">
                <i class="bi bi-layout-three-columns"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow p-2" style="min-width: 200px;" data-modulo="' . htmlspecialchars(str_replace('-', '_', basename($modulo)), ENT_QUOTES) . '">
                <li><h6 class="dropdown-header px-1"><i class="bi bi-eye me-1"></i> Columnas Visibles</h6></li>';

        foreach ($columnas as $colKey => $colLabel) {
            $checked = !in_array($colKey, $ocultas, true) ? 'checked' : '';
            $html .= '
                <li>
                    <label class="dropdown-item d-flex align-items-center cursor-pointer gap-2 py-1" for="col_' . $colKey . '">
                        <div class="form-check form-switch m-0 p-0">
                            <input class="form-check-input cursor-pointer toggle-columna-vista ms-0" type="checkbox" role="switch" id="col_' . $colKey . '" value="' . $colKey . '" ' . $checked . '>
                        </div>
                        <span class="fw-medium" style="font-size: 0.85rem;">' . htmlspecialchars($colLabel) . '</span>
                    </label>
                </li>';
        }

        $html .= '
            </ul>
        </div>';

        return $html;
    }
    /**
     * Renderiza el bloque CSS que oculta las pestañas desmarcadas por el usuario en los modales.
     */
    public static function renderEstilosPestanasOcultas(array $vistaConfig, string $idStyle = 'estiloVistaPestanas'): string
    {
        if (empty($vistaConfig['__pestanas_ocultas__']) || !is_array($vistaConfig['__pestanas_ocultas__'])) {
            return '<style id="' . htmlspecialchars($idStyle) . '"></style>';
        }

        $css = '';
        foreach ($vistaConfig['__pestanas_ocultas__'] as $tabVal) {
            $tabVal = htmlspecialchars($tabVal, ENT_QUOTES, 'UTF-8');
            // Ocultamos tanto el botón (nav-link) como el contenido (tab-pane)
            $css .= ".nav-link[data-bs-target=\"#$tabVal\"], #$tabVal { display: none !important; }\n";
        }

        return "<style id=\"" . htmlspecialchars($idStyle) . "\">\n{$css}</style>";
    }

    /**
     * Renderiza un dropdown para configurar la visibilidad de las pestañas del modal.
     */
    public static function renderDropdownPestanas(array $pestanas, array $vistaConfig, string $modulo, string $key = '__pestanas_ocultas__'): string
    {
        $ocultas = $vistaConfig[$key] ?? [];

        $html = '
        <div class="dropdown d-inline-block dropdown-vista-pestanas ms-auto">
            <button class="btn btn-link btn-sm text-muted p-0 border-0 shadow-none" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Configurar Pestañas">
                <i class="bi bi-gear-fill"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow p-2" style="min-width: 200px;" data-modulo="' . htmlspecialchars(str_replace('-', '_', basename($modulo)), ENT_QUOTES) . '">
                <li><h6 class="dropdown-header px-1"><i class="bi bi-layers me-1"></i> Pestañas Visibles</h6></li>';

        foreach ($pestanas as $tabId => $tabLabel) {
            $checked = !in_array($tabId, $ocultas, true) ? 'checked' : '';
            $html .= '
                <li>
                    <label class="dropdown-item d-flex align-items-center cursor-pointer gap-2 py-1" for="tab_cfg_' . $tabId . '">
                        <div class="form-check form-switch m-0 p-0">
                            <input class="form-check-input cursor-pointer toggle-pestana-vista ms-0" type="checkbox" role="switch" id="tab_cfg_' . $tabId . '" value="' . $tabId . '" ' . $checked . '>
                        </div>
                        <span class="fw-medium" style="font-size: 0.85rem;">' . htmlspecialchars($tabLabel) . '</span>
                    </label>
                </li>';
        }

        $html .= '
            </ul>
        </div>';

        return $html;
    }
}
