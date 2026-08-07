<?php
/**
 * Controlador ProvinciaCiudad - Gestión de provincias y ciudades
 * Tablas provincia y ciudad. Relación: ciudad.cod_prov = provincia.codigo
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\Provincia;
use App\models\Ciudad;

class ProvinciaCiudadController extends Controller
{
    private Provincia $modelProvincia;
    private Ciudad $modelCiudad;
    private const BASE_PATH = '/config/provincia-ciudad';

    public function __construct()
    {
        parent::__construct();
        $this->modelProvincia = new Provincia();
        $this->modelCiudad = new Ciudad();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        $ordenColProv = trim($_GET['sort_prov'] ?? 'nombre');
        $ordenDirProv = strtoupper(trim($_GET['dir_prov'] ?? 'asc'));
        $buscarProv = trim($_GET['b_prov'] ?? '');

        $ordenColCiud = trim($_GET['sort_ciud'] ?? 'nombre');
        $ordenDirCiud = strtoupper(trim($_GET['dir_ciud'] ?? 'asc'));
        $buscarCiud = trim($_GET['b_ciud'] ?? '');
        $filtroProv = trim($_GET['f_prov'] ?? '');

        if (!in_array($ordenColProv, Provincia::COLUMNAS_ORDEN, true)) {
            $ordenColProv = 'nombre';
        }
        if ($ordenDirProv !== 'ASC' && $ordenDirProv !== 'DESC') {
            $ordenDirProv = 'ASC';
        }
        if (!in_array($ordenColCiud, Ciudad::COLUMNAS_ORDEN, true)) {
            $ordenColCiud = 'nombre';
        }
        if ($ordenDirCiud !== 'ASC' && $ordenDirCiud !== 'DESC') {
            $ordenDirCiud = 'ASC';
        }

        $rowsProvincias = $this->modelProvincia->getAll($ordenColProv, $ordenDirProv, $buscarProv);
        $rowsCiudades = $this->modelCiudad->getAll($ordenColCiud, $ordenDirCiud, $buscarCiud, $filtroProv !== '' ? $filtroProv : null);
        $provinciasParaSelect = $this->modelProvincia->getTodas();

        $this->viewWithLayout('layouts.main', 'provinciaCiudad.index', [
            'titulo' => 'Provincias y ciudades',
            'rowsProvincias' => $rowsProvincias,
            'rowsCiudades' => $rowsCiudades,
            'rowsProvinciasHtml' => $this->renderFilasProvinciasHtml($rowsProvincias),
            'rowsCiudadesHtml' => $this->renderFilasCiudadesHtml($rowsCiudades),
            'provinciasParaSelect' => $provinciasParaSelect,
            'ordenColProv' => $ordenColProv,
            'ordenDirProv' => $ordenDirProv,
            'buscarProv' => $buscarProv,
            'ordenColCiud' => $ordenColCiud,
            'ordenDirCiud' => $ordenDirCiud,
            'buscarCiud' => $buscarCiud,
            'filtroProv' => $filtroProv,
        ]);
    }

    /**
     * AJAX: listado de provincias (tabla), para búsqueda y ordenamiento en
     * tiempo real sin recargar la página. Mismo patrón que
     * ConfigController::asientosTipoListAjax.
     */
    public function provinciasSearchAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        header('Content-Type: application/json');

        $ordenCol = trim($_GET['sort_prov'] ?? $_POST['sort_prov'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir_prov'] ?? $_POST['dir_prov'] ?? 'ASC'));
        $buscar = trim($_GET['b_prov'] ?? $_POST['b_prov'] ?? '');
        if (!in_array($ordenCol, Provincia::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->modelProvincia->getAll($ordenCol, $ordenDir, $buscar);

        echo json_encode([
            'ok' => true,
            'rows' => $this->renderFilasProvinciasHtml($rows),
        ]);
        exit;
    }

    /**
     * AJAX: listado de ciudades (tabla), para búsqueda, filtro por provincia
     * y ordenamiento en tiempo real sin recargar la página.
     */
    public function ciudadesSearchAjax(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);
        header('Content-Type: application/json');

        $ordenCol = trim($_GET['sort_ciud'] ?? $_POST['sort_ciud'] ?? 'nombre');
        $ordenDir = strtoupper(trim($_GET['dir_ciud'] ?? $_POST['dir_ciud'] ?? 'ASC'));
        $buscar = trim($_GET['b_ciud'] ?? $_POST['b_ciud'] ?? '');
        $filtroProv = trim($_GET['f_prov'] ?? $_POST['f_prov'] ?? '');
        if (!in_array($ordenCol, Ciudad::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre';
        }
        if ($ordenDir !== 'ASC' && $ordenDir !== 'DESC') {
            $ordenDir = 'ASC';
        }

        $rows = $this->modelCiudad->getAll($ordenCol, $ordenDir, $buscar, $filtroProv !== '' ? $filtroProv : null);

        echo json_encode([
            'ok' => true,
            'rows' => $this->renderFilasCiudadesHtml($rows),
        ]);
        exit;
    }

    private function renderFilasProvinciasHtml(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="2" class="text-center py-4 text-muted">No hay provincias registradas.</td></tr>';
        }
        $html = '';
        foreach ($rows as $r) {
            $html .= '<tr class="prov-row" role="button" tabindex="0"'
                . ' data-codigo="' . htmlspecialchars($r['codigo'] ?? '') . '"'
                . ' data-nombre="' . htmlspecialchars($r['nombre'] ?? '') . '">';
            $html .= '<td><code>' . htmlspecialchars($r['codigo'] ?? '') . '</code></td>';
            $html .= '<td>' . htmlspecialchars($r['nombre'] ?? '') . '</td>';
            $html .= '</tr>';
        }
        return $html;
    }

    private function renderFilasCiudadesHtml(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="3" class="text-center py-4 text-muted">No hay ciudades registradas.</td></tr>';
        }
        $html = '';
        foreach ($rows as $r) {
            $html .= '<tr class="ciud-row" role="button" tabindex="0"'
                . ' data-codigo="' . htmlspecialchars($r['codigo'] ?? '') . '"'
                . ' data-nombre="' . htmlspecialchars($r['nombre'] ?? '') . '"'
                . ' data-cod-prov="' . htmlspecialchars($r['cod_prov'] ?? '') . '"'
                . ' data-nombre-provincia="' . htmlspecialchars($r['nombre_provincia'] ?? '') . '">';
            $html .= '<td><code>' . htmlspecialchars($r['codigo'] ?? '') . '</code></td>';
            $html .= '<td>' . htmlspecialchars($r['nombre'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($r['nombre_provincia'] ?? $r['cod_prov'] ?? '') . '</td>';
            $html .= '</tr>';
        }
        return $html;
    }

    public function provinciaStore(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');

        try {
            $this->modelProvincia->crear($codigo, $nombre);
            $_SESSION['provincia_ciudad_msg'] = ['success', 'Provincia creada correctamente.'];
        } catch (\InvalidArgumentException $e) {
            $_SESSION['provincia_ciudad_msg'] = ['danger', $e->getMessage()];
        } catch (\Throwable $e) {
            $_SESSION['provincia_ciudad_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH . $this->tabQuery($_POST['tab'] ?? ''));
    }

    public function provinciaUpdate(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $codigoActual = trim($_POST['codigo_actual'] ?? '');
        $codigoNuevo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');

        if ($codigoActual === '') {
            $_SESSION['provincia_ciudad_msg'] = ['danger', 'Código inválido.'];
            $this->redirect(BASE_URL . self::BASE_PATH . $this->tabQuery($_POST['tab'] ?? ''));
        }

        try {
            if ($this->modelProvincia->actualizar($codigoActual, $codigoNuevo, $nombre)) {
                $_SESSION['provincia_ciudad_msg'] = ['success', 'Provincia actualizada correctamente.'];
            } else {
                $_SESSION['provincia_ciudad_msg'] = ['danger', 'Error al actualizar.'];
            }
        } catch (\InvalidArgumentException $e) {
            $_SESSION['provincia_ciudad_msg'] = ['danger', $e->getMessage()];
        } catch (\Throwable $e) {
            $_SESSION['provincia_ciudad_msg'] = ['danger', 'Error: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH . $this->tabQuery($_POST['tab'] ?? ''));
    }

    public function ciudadStore(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $codProv = trim($_POST['cod_prov'] ?? '');

        try {
            $this->modelCiudad->crear($codigo, $nombre, $codProv);
            $_SESSION['provincia_ciudad_msg'] = ['success', 'Ciudad creada correctamente.'];
        } catch (\InvalidArgumentException $e) {
            $_SESSION['provincia_ciudad_msg'] = ['danger', $e->getMessage()];
        } catch (\Throwable $e) {
            $_SESSION['provincia_ciudad_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH . $this->tabQuery($_POST['tab'] ?? ''));
    }

    public function ciudadUpdate(): void
    {
        $this->requireAuth();
        $this->requireNivel(2);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $codigoActual = trim($_POST['codigo_actual'] ?? '');
        $codProvActual = trim($_POST['cod_prov_actual'] ?? '');
        $codigoNuevo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $codProvNuevo = trim($_POST['cod_prov'] ?? '');

        if ($codigoActual === '' || $codProvActual === '') {
            $_SESSION['provincia_ciudad_msg'] = ['danger', 'Datos inválidos.'];
            $this->redirect(BASE_URL . self::BASE_PATH . $this->tabQuery($_POST['tab'] ?? ''));
        }

        try {
            if ($this->modelCiudad->actualizar($codigoActual, $codProvActual, $codigoNuevo, $nombre, $codProvNuevo)) {
                $_SESSION['provincia_ciudad_msg'] = ['success', 'Ciudad actualizada correctamente.'];
            } else {
                $_SESSION['provincia_ciudad_msg'] = ['danger', 'Error al actualizar.'];
            }
        } catch (\InvalidArgumentException $e) {
            $_SESSION['provincia_ciudad_msg'] = ['danger', $e->getMessage()];
        } catch (\Throwable $e) {
            $_SESSION['provincia_ciudad_msg'] = ['danger', 'Error: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH . $this->tabQuery($_POST['tab'] ?? ''));
    }

    private function tabQuery(string $tab): string
    {
        if ($tab === 'ciudades') {
            return '/ciudades'; // URL limpia: /config/provincia-ciudad/ciudades
        }
        return ''; // provincias es la pestaña por defecto
    }

    private function requireNivel(int $min): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['provincia_ciudad_msg'] = ['danger', 'No tiene permisos.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
