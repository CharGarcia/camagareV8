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
        // Sin reabrir una sesión ya cerrada (ver getPreferenciasVista).
        if (session_status() === PHP_SESSION_NONE && !isset($_SESSION)) {
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
        // `!isset($_SESSION)`: no reabrir una sesión que el controlador ya cerró a propósito
        // (liberarSesion() en los searchAjax). session_start() volvía a tomar el candado y las
        // demás peticiones del usuario seguían en fila durante la búsqueda. Solo se lee.
        if (session_status() === PHP_SESSION_NONE && !isset($_SESSION)) {
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
     * Anchos (px) que el usuario fijó a las columnas de la tabla de detalle de un
     * documento (Código, Descripción) desde el modal —CMG_detalleColumnas, en
     * public/js/components/detalle_columnas.js—.
     *
     * Viven en la clave propia __detalle_anchos__ de __vista__, no en
     * __columnas_anchos__ (la del listado): guardarVistaAjax reemplaza la clave
     * entera, así que compartirla haría que el listado y el modal se pisaran. Solo
     * se devuelven las claves conocidas con enteros positivos: es un JSON de
     * preferencias y la vista lo imprime dentro de un <script>.
     */
    public static function getAnchosDetalle(array $vistaConfig): array
    {
        $raw = $vistaConfig['__detalle_anchos__'] ?? [];
        $anchos = [];
        if (is_array($raw)) {
            foreach (['codigo', 'descripcion'] as $col) {
                $px = (int) ($raw[$col] ?? 0);
                if ($px > 0) {
                    $anchos[$col] = $px;
                }
            }
        }
        return $anchos;
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
            <ul class="dropdown-menu dropdown-menu-end shadow p-2" style="min-width: 200px; max-height: 350px; overflow-y: auto;" data-modulo="' . htmlspecialchars(str_replace('-', '_', basename($modulo)), ENT_QUOTES) . '">
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
     *
     * Las claves de $pestanas deben ser el id del PANEL (`pane-x`, el `tab-pane`),
     * no el del botón: es lo que espera el CSS de renderEstilosPestanasOcultas(),
     * que oculta `#pane-x` y `.nav-link[data-bs-target="#pane-x"]`. Por eso el
     * nav-link tiene que declarar `data-bs-target`, no solo `href`.
     *
     * $idStyle debe coincidir con el que se le pasó a renderEstilosPestanasOcultas():
     * los modales compartidos (cliente, producto, proveedor…) usan un id propio
     * para no pisar el <style> de la página que los incluye, y el JS necesita
     * saber cuál es para aplicar el cambio en vivo sobre el correcto.
     */
    public static function renderDropdownPestanas(array $pestanas, array $vistaConfig, string $modulo, string $key = '__pestanas_ocultas__', string $idStyle = 'estiloVistaPestanas'): string
    {
        $ocultas = $vistaConfig[$key] ?? [];

        $html = '
        <div class="dropdown d-inline-block dropdown-vista-pestanas ms-auto">
            <button class="btn btn-link btn-sm text-muted p-0 border-0 shadow-none" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Configurar Pestañas">
                <i class="bi bi-gear-fill"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow p-2" style="min-width: 200px; max-height: 350px; overflow-y: auto;" data-modulo="' . htmlspecialchars(str_replace('-', '_', basename($modulo)), ENT_QUOTES) . '" data-style-id="' . htmlspecialchars($idStyle, ENT_QUOTES) . '">
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

    /** Opciones del selector "Filas por página" de los listados. */
    public const POR_PAGINA_OPCIONES = [25, 50, 75, 100];

    /**
     * Último listado que pidió sus filas por página en esta petición (módulo y valor).
     * Lo lee jsPorPagina() desde partials/scripts.php para que favoritos.js inserte el
     * selector junto al paginador con la clave correcta, sin que cada vista haga nada.
     */
    private static ?array $porPaginaUso = null;

    /**
     * Filas por página de un listado a partir de su ruta de módulo ('modulos/clientes',
     * 'tareas_obligaciones'…): lee la preferencia del usuario y deja registrado el módulo
     * para el selector automático. Los controladores de módulo la usan vía
     * BaseModuloController::porPagina(); los globales la llaman directo con su clave.
     */
    public static function porPaginaModulo(string $modulo, int $defecto = 25): int
    {
        $clave = str_replace('-', '_', basename($modulo));
        $n = self::porPagina(self::getPreferenciasVista($modulo), $defecto);
        self::$porPaginaUso = ['modulo' => $clave, 'actual' => $n];
        return $n;
    }

    /**
     * <script> con window.CMG_POR_PAGINA = {modulo, actual, opciones} si algún listado pidió
     * sus filas por página en esta petición; '' en cualquier otra página. Lo imprime
     * partials/scripts.php; favoritos.js (CMG_initPorPagina) pinta el selector.
     */
    public static function jsPorPagina(): string
    {
        if (self::$porPaginaUso === null || empty($_SESSION['id_usuario'])) {
            return '';
        }
        $cfg = self::$porPaginaUso + ['opciones' => self::POR_PAGINA_OPCIONES];
        return '<script>window.CMG_POR_PAGINA = ' . json_encode($cfg) . ';</script>';
    }

    /**
     * Filas por página de un listado: la preferencia del usuario (`__por_pagina__` en
     * `__vista__`) o, si la petición trae `per_page` (el selector recién cambiado, antes de
     * que la preferencia termine de guardarse), ese valor. Solo se aceptan las opciones del
     * selector; cualquier otra cosa cae al valor por defecto.
     *
     * @param array $vista   Lo que devuelve getPreferenciasVista($modulo).
     * @param int   $defecto Valor cuando el usuario aún no eligió (debe estar en las opciones).
     */
    public static function porPagina(array $vista, int $defecto = 25): int
    {
        $pedido = (int) ($_GET['per_page'] ?? $_POST['per_page'] ?? 0);
        if (in_array($pedido, self::POR_PAGINA_OPCIONES, true)) {
            return $pedido;
        }
        $pref = (int) ($vista['__por_pagina__'] ?? 0);
        if (in_array($pref, self::POR_PAGINA_OPCIONES, true)) {
            return $pref;
        }
        return in_array($defecto, self::POR_PAGINA_OPCIONES, true) ? $defecto : self::POR_PAGINA_OPCIONES[0];
    }

    /**
     * Selector compacto "Filas por página" para poner junto al paginador. El `onchange`
     * recibe el valor elegido (p. ej. `window.FV_cambiarPorPagina(this.value)`); el módulo
     * guarda la preferencia con CMG_guardarVista(modulo, {__por_pagina__: n}) y recarga.
     */
    public static function renderSelectorPorPagina(int $actual, string $id, string $onchange): string
    {
        // data-cmg-por-pagina: así favoritos.js no inserta un segundo selector automático.
        $html = '<select id="' . htmlspecialchars($id) . '" data-cmg-por-pagina="1" class="form-select form-select-sm" style="width:auto;" title="Filas por página" aria-label="Filas por página" onchange="' . htmlspecialchars($onchange) . '">';
        foreach (self::POR_PAGINA_OPCIONES as $n) {
            $html .= '<option value="' . $n . '"' . ($n === $actual ? ' selected' : '') . '>' . $n . '</option>';
        }
        return $html . '</select>';
    }
}
