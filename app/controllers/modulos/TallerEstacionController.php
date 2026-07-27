<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\TallerDepartamentoRepository;
use App\repositories\modulos\TallerOrdenRepository;
use App\Rules\modulos\TallerOrdenRules;
use App\Services\LogSistemaService;
use App\Services\modulos\TallerOrdenService;
use Exception;

/**
 * Estación del taller — la tablet que queda fija en cada departamento.
 *
 * Página standalone (sin el menú del sistema) pensada para tocarse con el dedo:
 * fondo oscuro, letra grande y botones amplios. El departamento va en la URL y
 * no en la sesión, igual que el KDS de cocina: así puede haber una tablet en
 * pintura y otra en mecánica al mismo tiempo, cada una en lo suyo, en lugar de
 * que todas cambien juntas al tocar una.
 *
 * Es un módulo aparte de modulos/taller a propósito: expone SOLO lo que un
 * operario necesita —tomar el trabajo, registrar lo que hizo, agregar lo que
 * consumió y pasar el vehículo al siguiente departamento—. Facturar, aprobar
 * presupuestos o eliminar órdenes no está aquí, ni siquiera para quien tenga
 * permisos amplios en esta ruta.
 */
class TallerEstacionController extends BaseModuloController
{
    private TallerOrdenService $service;
    private const RUTA_MODULO = 'modulos/taller-estacion';

    public function __construct()
    {
        parent::__construct();
        $logService = new LogSistemaService();
        $this->service = new TallerOrdenService(
            new TallerOrdenRepository(),
            new TallerDepartamentoRepository(),
            new TallerOrdenRules(),
            $logService
        );
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    // ═══ PANTALLA ════════════════════════════════════════════════════════════

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $departamentos  = $this->service->getDepartamentos($idEmpresa);
        $idDepartamento = (int) ($_GET['id_departamento'] ?? 0);
        if ($idDepartamento <= 0 && !empty($departamentos)) {
            $idDepartamento = (int) $departamentos[0]['id'];
        }

        $this->view('modulos.taller_estacion.index', [
            'titulo'         => 'Estación del taller',
            'rutaModulo'     => self::RUTA_MODULO,
            'perm'           => $this->getPermisos(),
            'departamentos'  => $departamentos,
            'idDepartamento' => $idDepartamento,
            'ordenes'        => $idDepartamento > 0 ? $this->service->getOrdenesPorDepartamento($idEmpresa, $idDepartamento) : [],
            'empleados'      => $this->getEmpleados($idEmpresa),
            'bodegas'        => $this->getBodegas($idEmpresa),
        ]);
    }

    /** Polling de la tablet: las órdenes que hay ahora en el departamento. */
    public function estacionAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idDepartamento = (int) ($_GET['id_departamento'] ?? 0);
        if ($idDepartamento <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Departamento no válido.']);
            exit;
        }

        echo json_encode([
            'ok'   => true,
            'data' => $this->service->getOrdenesPorDepartamento((int) $_SESSION['id_empresa'], $idDepartamento),
        ]);
        exit;
    }

    /** Detalle de una orden acotado al departamento (modal de la tablet). */
    public function estacionOrdenAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id  = (int) ($_GET['id'] ?? 0);
            $dep = (int) ($_GET['id_departamento'] ?? 0);
            if ($id <= 0 || $dep <= 0) throw new Exception('Parámetros incompletos.');

            $data = $this->service->getDetalleDepartamento($id, (int) $_SESSION['id_empresa'], $dep);
            if (!$data) throw new Exception('Orden no encontrada.');

            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ═══ TRABAJO DEL DEPARTAMENTO ════════════════════════════════════════════

    public function iniciarEtapaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $idEtapa = (int) ($_POST['id_etapa'] ?? 0);
            if ($idEtapa <= 0) throw new Exception('Etapa no válida.');

            $this->service->iniciarEtapa(
                $idEtapa,
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                (int) ($_POST['id_empleado'] ?? 0) ?: null
            );
            echo json_encode(['ok' => true, 'msg' => 'Trabajo iniciado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function avanceEtapaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $idEtapa = (int) ($_POST['id_etapa'] ?? 0);
            if ($idEtapa <= 0) throw new Exception('Etapa no válida.');

            $this->service->guardarAvanceEtapa($idEtapa, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], [
                'trabajo_realizado'       => trim($_POST['trabajo_realizado'] ?? ''),
                'observaciones'           => trim($_POST['observaciones'] ?? '') ?: null,
                'id_empleado_responsable' => (int) ($_POST['id_empleado_responsable'] ?? 0) ?: null,
            ]);
            echo json_encode(['ok' => true, 'msg' => 'Avance guardado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function terminarEtapaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $idEtapa = (int) ($_POST['id_etapa'] ?? 0);
            if ($idEtapa <= 0) throw new Exception('Etapa no válida.');

            $this->service->terminarEtapa($idEtapa, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], [
                'trabajo_realizado'         => trim($_POST['trabajo_realizado'] ?? ''),
                'observaciones'             => trim($_POST['observaciones'] ?? '') ?: null,
                'id_empleado_responsable'   => (int) ($_POST['id_empleado_responsable'] ?? 0) ?: null,
                'id_departamento_siguiente' => (int) ($_POST['id_departamento_siguiente'] ?? 0),
            ]);
            echo json_encode(['ok' => true, 'msg' => 'Trabajo cerrado.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ═══ CONSUMOS Y EVIDENCIA ════════════════════════════════════════════════

    public function agregarLineaAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $input   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $idOrden = (int) ($input['id_orden'] ?? 0);
            if ($idOrden <= 0) throw new Exception('Orden no válida.');

            $id = $this->service->agregarLinea($idOrden, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], $input);
            echo json_encode(['ok' => true, 'msg' => 'Agregado a la orden.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarLineaAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $idLinea = (int) ($_POST['id'] ?? 0);
            if ($idLinea <= 0) throw new Exception('Línea no válida.');

            $this->service->eliminarLinea($idLinea, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Línea eliminada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function agregarNotaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id_orden'] ?? 0);
            if ($id <= 0) throw new Exception('Orden no válida.');

            $this->service->agregarNota(
                $id,
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                trim($_POST['concepto'] ?? ''),
                trim($_POST['detalle'] ?? '') ?: null,
                (int) ($_POST['id_departamento'] ?? 0) ?: null
            );
            echo json_encode(['ok' => true, 'msg' => 'Nota agregada.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Foto del trabajo, normalmente tomada con la cámara de la tablet. */
    public function subirFotoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $idOrden = (int) ($_POST['id_orden'] ?? 0);
            if ($idOrden <= 0) throw new Exception('Orden no válida.');
            if (empty($_FILES['foto'])) throw new Exception('No se recibió ninguna imagen.');

            $res = $this->service->guardarFotoSubida(
                $_FILES['foto'],
                $idOrden,
                (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'],
                [
                    'id_departamento' => (int) ($_POST['id_departamento'] ?? 0) ?: null,
                    'momento'         => trim($_POST['momento'] ?? 'proceso'),
                    'descripcion'     => trim($_POST['descripcion'] ?? '') ?: null,
                ]
            );

            echo json_encode(['ok' => true, 'msg' => 'Foto agregada.', 'id' => $res['id'], 'url' => $res['url']]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Catálogo de repuestos y servicios, con saldo en la bodega elegida. */
    public function getProductosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $buscar    = trim($_GET['q'] ?? $_GET['term'] ?? '');
            $idBodega  = (int) ($_GET['id_bodega'] ?? 0);
            $idOrden   = (int) ($_GET['id_orden'] ?? 0);

            $repo    = new \App\repositories\modulos\ProductoRepository();
            $repoInv = new \App\repositories\modulos\InventarioRepository();
            $result  = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, 'venta', true);

            $rows = array_map(function ($p) use ($repo, $repoInv, $idEmpresa, $idBodega, $idOrden) {
                $p['precios_lista'] = $repo->getPrecios((int) $p['id'], $idEmpresa);

                $esInv = ($p['inventariable'] == true || $p['inventariable'] === 'true' || $p['inventariable'] == 1)
                         && (($p['tipo_produccion'] ?? '01') !== '02');
                $stock = 0.0;
                if ($idBodega > 0 && $esInv) {
                    $stock = $repoInv->getStockActual(
                        (int) $p['id'], $idBodega, $idEmpresa,
                        $idOrden > 0 ? $idOrden : null,
                        $idOrden > 0 ? 'taller_orden' : null
                    );
                }
                $p['stock_actual']   = $stock;
                $p['controla_stock'] = $esInv;
                return $p;
            }, $result['rows']);

            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ═══ HELPERS ═════════════════════════════════════════════════════════════

    private function getBodegas(int $idEmpresa): array
    {
        return (new \App\repositories\modulos\BodegaRepository())->getBodegasPermitidas(
            (int) $_SESSION['id_usuario'],
            $idEmpresa,
            (int) ($_SESSION['nivel'] ?? 1)
        );
    }

    private function getEmpleados(int $idEmpresa): array
    {
        try {
            return (new \App\repositories\modulos\EmpleadoRepository())->getActivosParaSelect($idEmpresa);
        } catch (\Throwable $e) {
            // El taller puede operar sin el módulo de empleados cargado.
            return [];
        }
    }
}
