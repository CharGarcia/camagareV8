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
