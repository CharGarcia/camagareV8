<?php

namespace App\controllers\modulos;

use App\controllers\modulos\BaseModuloController;
use App\services\modulos\EmpresaService;
use App\models\TipoRegimen;
use App\models\TipoEmpresa;
use App\models\Provincia;
use App\models\Ciudad;
use App\models\TarifaIva;
use App\models\FormaPagoSri;

class EmpresaController extends BaseModuloController
{
    private $service;
    private const RUTA_MODULO = 'modulos/empresa';

    public function __construct()
    {
        parent::__construct();
        $this->service = new EmpresaService();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        
        $regimenes = (new TipoRegimen())->getAll();
        $tiposEmpresa = (new TipoEmpresa())->getAll();
        $provincias = (new Provincia())->getAll();
        $ciudades = (new Ciudad())->getAll();
        $tarifasIva = (new TarifaIva())->getAll();
        $formasPagoSri = (new FormaPagoSri())->getAll('nombre', 'ASC');
        
        $data = $this->service->getData($idEmpresa);

        $secRepo = new \App\repositories\SecuencialRepository();
        $tiposSecuencialAgrupados = $secRepo->getTiposDocumentoAgrupados();
        $tiposSecuencialSoportados = $secRepo->getTiposDocumentoSoportados();

        $this->viewWithLayout('layouts.main', 'modulos.empresa.index', [
            'tiposSecuencialAgrupados' => $tiposSecuencialAgrupados,
            'tiposSecuencialSoportados' => $tiposSecuencialSoportados,
            'titulo' => 'Configuración de Empresa',
            'id_empresa' => $idEmpresa,
            'empresa' => $data['empresa'],
            'suscripcion_info' => $data['suscripcion_info'] ?? [],
            'suscripcion_controladora' => $data['suscripcion_controladora'] ?? 0,
            'suscripcion_sin_valores' => $data['suscripcion_sin_valores'] ?? false,
            'correo' => $data['correo'],
            'firmas' => $data['firmas'],
            'puntos' => $data['puntos'],
            'establecimientos' => $data['establecimientos'],
            'tarifasIva' => $tarifasIva,
            'iva_casilleros' => $data['iva_casilleros'],
            'ices' => $data['ices'] ?? [],
            'retenciones_sri_iva' => $data['retenciones_sri_iva'] ?? [],
            'retenciones_casilleros' => $data['retenciones_casilleros'] ?? [],
            'regimenes' => $regimenes,
            'tiposEmpresa' => $tiposEmpresa,
            'provincias' => $provincias,
            'ciudades' => $ciudades,
            'formasPagoSri' => $formasPagoSri,
            'usuarios_empresa' => $data['usuarios_empresa'] ?? [],
            'rutaModulo' => self::RUTA_MODULO,
            'fullWidth' => true
        ]);
    }

    public function save(): void
    {
        ob_start();
        header('Content-Type: application/json');
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $section = $_POST['section'] ?? '';

        if ($idEmpresa <= 0) {
            echo json_encode(['ok' => false, 'error' => 'No hay una empresa seleccionada en la sesión.']);
            exit;
        }

        try {
            switch ($section) {
                case 'general':
                    $this->requireActualizar();
                    $res = $this->service->saveGeneral($idEmpresa, $_POST);
                    echo json_encode(['ok' => $res]);
                    break;
                case 'emisor':
                    $this->requireActualizar();
                    $res = $this->service->saveEmisor($idEmpresa, $_POST);
                    echo json_encode(['ok' => $res]);
                    break;
                case 'correo':
                    $this->requireActualizar();
                    $res = $this->service->saveCorreo($idEmpresa, $_POST);
                    echo json_encode(['ok' => $res]);
                    break;
                case 'firma':
                    $this->requireActualizar();
                    $file = $_FILES['archivo_p12'] ?? null;
                    $pass = $_POST['password_firma'] ?? '';
                    $forzar = !empty($_POST['forzar']);
                    echo json_encode($this->service->uploadFirma($idEmpresa, $file, $pass, $forzar));
                    break;
                case 'punto':
                    $this->requireActualizar();
                    echo json_encode($this->service->savePunto($idEmpresa, $_POST));
                    break;
                case 'establecimiento':
                    $this->requireActualizar();
                    echo json_encode($this->service->saveEstablecimiento($idEmpresa, $_POST, $_FILES));
                    break;
                case 'secuenciales':
                    $this->requireActualizar();
                    $idPunto = (int) ($_POST['id_punto_emision'] ?? 0);
                    $secuenciales = $_POST['secuenciales'] ?? [];
                    $res = $this->service->saveSecuenciales($idPunto, $secuenciales, $idEmpresa);
                    echo json_encode(['ok' => $res]);
                    break;
                case 'secuenciales_iniciales':
                    $this->requireActualizar();
                    $idPunto = (int) ($_POST['id_punto_emision'] ?? 0);
                    if ($idPunto <= 0) {
                        echo json_encode(['ok' => false, 'msg' => 'Punto de emisión no válido.']);
                        break;
                    }
                    $this->service->crearSecuencialesIniciales($idPunto, $idEmpresa);
                    echo json_encode(['ok' => true, 'msg' => 'Secuenciales iniciales creados correctamente.']);
                    break;
                case 'decimales':
                    $this->requireActualizar();
                    $res = $this->service->saveDecimales($idEmpresa, $_POST);
                    echo json_encode(['ok' => $res]);
                    break;
                case 'iva':
                    $this->requireActualizar();
                    $res = $this->service->saveIva($idEmpresa, $_POST);
                    echo json_encode(['ok' => $res]);
                    break;
                case 'facturacion_config':
                    $this->requireActualizar();
                    $res = $this->service->saveFacturacionConfig($idEmpresa, $_POST);
                    echo json_encode(['ok' => $res]);
                    break;
                case 'inventario_config':
                    $this->requireActualizar();
                    $res = $this->service->saveInventarioConfig($idEmpresa, $_POST);
                    echo json_encode(['ok' => $res]);
                    break;
                default:
                    throw new \Exception('Sección no válida');
            }
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }

        $output = ob_get_clean();
        echo $output;
        exit;
    }

    public function deletePunto(): void
    {
        header('Content-Type: application/json');
        $this->requireEliminar();
        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);

        try {
            $res = $this->service->deletePunto($id, $idEmpresa);
            echo json_encode(['ok' => $res]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function deleteEstablecimiento(): void
    {
        header('Content-Type: application/json');
        $this->requireEliminar();
        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);

        try {
            $res = $this->service->deleteEstablecimiento($id, $idEmpresa);
            echo json_encode(['ok' => $res]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function saveIce(): void
    {
        header('Content-Type: application/json');
        try {
            $this->requireActualizar();
            $idEmpresa = (int)($_SESSION['id_empresa'] ?? 0);
            $res = $this->service->saveIce($idEmpresa, $_POST);
            echo json_encode(['ok' => $res]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function deleteIce(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');
        
        $id = (int)($_POST['id'] ?? 0);
        $idEmpresa = (int)($_SESSION['id_empresa'] ?? 0);
        $res = $this->service->deleteIce($id, $idEmpresa);
        echo json_encode(['ok' => $res]);
        exit;
    }

    public function getSecuenciales(): void
    {
        header('Content-Type: application/json');
        try {
            $this->requireLeer();
            $idPunto = (int)($_GET['id_punto'] ?? 0);
            $idEmpresa = (int)($_SESSION['id_empresa'] ?? 0);
            $data = $this->service->getSecuencialesByPunto($idPunto, $idEmpresa);
            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function descargarFirma(): void
    {
        $this->requireLeer();
        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo 'ID de firma inválido.';
            exit;
        }

        $repo  = new \App\repositories\modulos\EmpresaRepository();
        $firma = $repo->getFirmaById($id);

        if (!$firma || (int)$firma['id_empresa'] !== $idEmpresa) {
            http_response_code(404);
            echo 'Firma no encontrada o sin acceso.';
            exit;
        }

        $ruta = $firma['archivo_ruta'] ?? '';
        if (!$ruta || !file_exists($ruta)) {
            http_response_code(404);
            echo 'Archivo de firma no encontrado en el servidor.';
            exit;
        }

        $nombre = $firma['archivo_nombre'] ?? 'firma.p12';
        header('Content-Type: application/x-pkcs12');
        header('Content-Disposition: attachment; filename="' . basename($nombre) . '"');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: no-cache, must-revalidate');
        readfile($ruta);
        exit;
    }

    public function cargarPredefinidos104(): void
    {
        header('Content-Type: application/json');
        try {
            $this->requireActualizar();
            $idEmpresa = (int)($_SESSION['id_empresa'] ?? 0);
            if ($idEmpresa <= 0) {
                echo json_encode(['ok' => false, 'error' => 'No hay empresa seleccionada.']);
                exit;
            }
            $res = $this->service->cargarCasilleros104Default($idEmpresa);
            echo json_encode(['ok' => $res]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
