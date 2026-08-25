<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\TallerChecklistRepository;
use App\repositories\modulos\TallerDepartamentoRepository;
use App\repositories\modulos\TallerOrdenRepository;
use App\Rules\modulos\TallerOrdenRules;
use App\Services\LogSistemaService;
use App\Services\modulos\TallerChecklistService;
use App\Services\modulos\TallerOrdenService;
use Exception;

/**
 * Taller Mecánico — Órdenes de Trabajo.
 *
 * Pantalla del asesor: recibe el vehículo, arma el presupuesto, registra la
 * aprobación del cliente, mueve la orden entre departamentos y cierra con la
 * entrega y la factura.
 *
 * El tablero del jefe de taller y la pantalla de las tablets son módulos
 * aparte (modulos/taller-tablero y modulos/taller-estacion) para que cada uno
 * tenga sus propios permisos: un operario no necesita —ni debe— poder facturar
 * o eliminar órdenes.
 *
 * El controlador solo recibe, valida lo básico y delega: la lógica vive en
 * TallerOrdenService.
 */
class TallerController extends BaseModuloController
{
    private TallerOrdenService $service;
    private TallerOrdenRepository $repository;
    private TallerChecklistService $checklistService;

    private const RUTA_MODULO    = 'modulos/taller';
    private const TIPO_SECUENCIAL = 'Ordenes de taller';

    public function __construct()
    {
        parent::__construct();
        $this->repository = new TallerOrdenRepository();
        $departamentoRepo = new TallerDepartamentoRepository();
        $logService = new LogSistemaService();

        $this->service = new TallerOrdenService(
            $this->repository,
            $departamentoRepo,
            new TallerOrdenRules(),
            $logService
        );
        $this->checklistService = new TallerChecklistService(new TallerChecklistRepository(), $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    // ═══ VISTA PRINCIPAL (listado) ═══════════════════════════════════════════

    public function index(): void
    {
        $this->requireLeer();
        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        $buscar   = trim($_GET['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_ingreso');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        // Orden que llega desde el tablero: el id viaja en sesión para que la
        // URL quede limpia. Es de un solo uso, así al recargar no se reabre.
        $abrirOrden = (int) ($_SESSION['taller_abrir_orden'] ?? 0);
        unset($_SESSION['taller_abrir_orden']);

        $this->viewWithLayout('layouts.main', 'modulos.taller.index', array_merge(
            $this->datosComunes($idEmpresa),
            [
                'titulo'      => 'Taller Mecánico',
                'perm'        => $perm,
                'rutaModulo'  => self::RUTA_MODULO,
                'rows'        => $rows,
                'total'       => $total,
                'page'        => $page,
                'totalPages'  => $totalPages,
                'perPage'     => $perPage,
                'buscar'      => $buscar,
                'ordenCol'    => $ordenCol,
                'ordenDir'    => $ordenDir,
                'vistaConfig' => $prefsVista,
                'abrirOrden'  => $abrirOrden,
            ]
        ));
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar     = trim($_GET['b'] ?? $_GET['q'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_ingreso');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'desc'));
        $perPage    = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="9" class="text-center py-5 text-muted">'
               . '<i class="bi bi-wrench-adjustable-circle fs-3 d-block mb-2"></i>No se encontraron órdenes de trabajo.</td></tr>';
        } else {
            foreach ($rows as $r) {
                echo self::filaListado($r);
            }
        }
        $rowsHtml = ob_get_clean();

        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        $paginationHtml = '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'pdf_url'    => BASE_URL . '/' . self::RUTA_MODULO . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url'  => BASE_URL . '/' . self::RUTA_MODULO . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
        ]);
        exit;
    }

    // ═══ RECEPCIÓN ═══════════════════════════════════════════════════════════

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) throw new Exception("Datos no recibidos.");

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $input['id_empresa']     = $idEmpresa;
            $input['id_usuario']     = (int) $_SESSION['id_usuario'];
            $input['empresa_config'] = $this->getEmpresaConfig($idEmpresa);

            if (!empty($input['id'])) {
                $this->requireActualizar();
                $this->service->actualizarRecepcion((int) $input['id'], $idEmpresa, $input);
                echo json_encode(['ok' => true, 'msg' => 'Orden actualizada correctamente.', 'id' => (int) $input['id']]);
            } else {
                $id = $this->service->crearRecepcion($input);
                echo json_encode(['ok' => true, 'msg' => 'Orden de trabajo registrada.', 'id' => $id]);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id   = (int) ($_GET['id'] ?? 0);
            $data = $this->service->getDetalleCompleto($id, (int) $_SESSION['id_empresa']);
            if (!$data) throw new Exception("Orden no encontrada.");

            echo json_encode(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function guardarDiagnosticoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id   = (int) ($_POST['id'] ?? 0);
            $dep  = (int) ($_POST['id_departamento'] ?? 0);
            $texto = trim($_POST['diagnostico'] ?? '');
            if ($id <= 0) throw new Exception('Orden no válida.');

            $this->service->guardarDiagnostico($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], $texto, $dep ?: null);
            echo json_encode(['ok' => true, 'msg' => 'Diagnóstico guardado.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ═══ LÍNEAS (repuestos y mano de obra) ═══════════════════════════════════

    public function agregarLineaAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $idOrden = (int) ($input['id_orden'] ?? 0);
            if ($idOrden <= 0) throw new Exception('Orden no válida.');

            $id = $this->service->agregarLinea($idOrden, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], $input);
            echo json_encode(['ok' => true, 'msg' => 'Agregado a la orden.', 'id' => $id]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function actualizarLineaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $input   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $idLinea = (int) ($input['id'] ?? 0);
            if ($idLinea <= 0) throw new Exception('Línea no válida.');

            $this->service->actualizarLinea($idLinea, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], $input);
            echo json_encode(['ok' => true, 'msg' => 'Línea actualizada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function estadoLineaAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $idLinea = (int) ($_POST['id'] ?? 0);
            $estado  = trim($_POST['estado'] ?? '');
            $motivo  = trim($_POST['motivo'] ?? '');
            if ($idLinea <= 0) throw new Exception('Línea no válida.');

            $this->service->cambiarEstadoLinea($idLinea, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], $estado, $motivo ?: null);
            echo json_encode(['ok' => true, 'msg' => 'Línea actualizada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ═══ APROBACIÓN DEL PRESUPUESTO ══════════════════════════════════════════

    public function aprobarAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Orden no válida.');

            $this->service->aprobarPresupuesto($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], [
                'aprobado_por'         => trim($_POST['aprobado_por'] ?? ''),
                'aprobado_medio'       => trim($_POST['aprobado_medio'] ?? ''),
                'aprobado_observacion' => trim($_POST['aprobado_observacion'] ?? '') ?: null,
            ]);
            echo json_encode(['ok' => true, 'msg' => 'Presupuesto aprobado. Los departamentos ya pueden trabajar.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ═══ FLUJO POR DEPARTAMENTOS ═════════════════════════════════════════════

    public function enviarDepartamentoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id  = (int) ($_POST['id'] ?? 0);
            $dep = (int) ($_POST['id_departamento'] ?? 0);
            if ($id <= 0) throw new Exception('Orden no válida.');

            $this->service->enviarADepartamento($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], $dep, [
                'trabajo_realizado'       => trim($_POST['trabajo_realizado'] ?? '') ?: null,
                'observaciones'           => trim($_POST['observaciones'] ?? '') ?: null,
                'id_empleado_responsable' => (int) ($_POST['id_empleado_responsable'] ?? 0) ?: null,
            ]);
            echo json_encode(['ok' => true, 'msg' => 'Vehículo enviado al departamento.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ═══ BITÁCORA Y FOTOS ════════════════════════════════════════════════════

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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Sube una foto de evidencia (recepción, proceso o entrega). */
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
                    'momento'         => trim($_POST['momento'] ?? 'ingreso'),
                    'descripcion'     => trim($_POST['descripcion'] ?? '') ?: null,
                ]
            );

            echo json_encode(['ok' => true, 'msg' => 'Foto agregada.', 'id' => $res['id'], 'url' => $res['url']]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarFotoAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Foto no válida.');

            $this->service->eliminarFoto($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Foto eliminada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ═══ CIERRE ══════════════════════════════════════════════════════════════

    public function entregarAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Orden no válida.');

            $this->service->entregar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], [
                'entregado_a'              => trim($_POST['entregado_a'] ?? ''),
                'kilometraje_salida'       => $_POST['kilometraje_salida'] ?? '',
                'recomendaciones'          => trim($_POST['recomendaciones'] ?? '') ?: null,
                'proximo_mantenimiento_km' => $_POST['proximo_mantenimiento_km'] ?? '',
                'proxima_cita'             => $_POST['proxima_cita'] ?? '',
                'garantia_dias'            => $_POST['garantia_dias'] ?? 0,
                'garantia_km'              => $_POST['garantia_km'] ?? 0,
            ]);
            echo json_encode(['ok' => true, 'msg' => 'Vehículo entregado.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function cambiarEstadoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id     = (int) ($_POST['id'] ?? 0);
            $estado = trim($_POST['estado'] ?? '');
            if ($id <= 0) throw new Exception("ID no válido.");

            $this->service->cambiarEstado($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], $estado);
            echo json_encode(['ok' => true, 'msg' => 'Estado actualizado.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminar(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception("ID no válido.");

            $this->service->eliminar($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            echo json_encode(['ok' => true, 'msg' => 'Orden eliminada.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ═══ FACTURACIÓN ═════════════════════════════════════════════════════════

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        if ($idPunto <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Punto de emisión no válido.']);
            exit;
        }
        $res = (new \App\Services\SecuencialService())->obtenerSiguienteSecuencial($idPunto, self::TIPO_SECUENCIAL);
        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    public function generarDocumentoAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $idOrden = (int) ($_POST['id_orden'] ?? 0);
            $tipo    = strtoupper(trim($_POST['tipo'] ?? ''));
            if ($idOrden <= 0) throw new Exception('Orden no válida.');
            if (!in_array($tipo, ['FACTURA', 'RECIBO'], true)) throw new Exception('Tipo de documento no válido.');

            // Emitir el documento es una operación del módulo de ventas, no del
            // taller: se exige el permiso de creación de ese módulo. El botón ya
            // se oculta en pantalla, pero la puerta se cierra también aquí.
            $this->requirePermisoDocumento($tipo);

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $res = $this->service->generarDocumento(
                $idOrden,
                $idEmpresa,
                (int) $_SESSION['id_usuario'],
                $tipo,
                [
                    'forma_pago' => trim($_POST['forma_pago'] ?? '01'),
                    'id_bodega'  => (int) ($_POST['id_bodega'] ?? 0),
                ],
                $this->getEmpresaConfig($idEmpresa)
            );

            echo json_encode([
                'ok'               => true,
                'msg'              => ($tipo === 'FACTURA' ? 'Factura ' : 'Recibo ') . $res['numero_documento'] . ' generado correctamente.',
                'tipo_documento'   => $res['tipo'],
                'id_documento'     => $res['id_documento'],
                'numero_documento' => $res['numero_documento'],
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ═══ DOCUMENTOS PDF ══════════════════════════════════════════════════════

    /** PDF de la orden de trabajo (el que firma el cliente al dejar el auto). */
    public function exportarPdfAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $orden = $this->service->getDetalleCompleto($id, $idEmpresa, false);
            if (!$orden) { http_response_code(404); echo 'Orden no encontrada'; exit; }

            (new \App\Services\modulos\TallerOrdenPdfService())->generar($orden, $this->cargarEmpresaPdf($idEmpresa), 'D');
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /** Informe técnico: todo lo que se le hizo al vehículo. */
    public function informeTecnicoAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $orden = $this->service->getDetalleCompleto($id, $idEmpresa, false);
            if (!$orden) { http_response_code(404); echo 'Orden no encontrada'; exit; }

            (new \App\Services\modulos\TallerInformeTecnicoPdfService())->generar($orden, $this->cargarEmpresaPdf($idEmpresa), 'D');
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar el informe: ' . $e->getMessage();
        }
        exit;
    }

    /** Precuenta: los valores a pagar, para que el cliente los revise. */
    public function precuentaAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $orden = $this->service->getDetalleCompleto($id, $idEmpresa, false);
            if (!$orden) { http_response_code(404); echo 'Orden no encontrada'; exit; }

            (new \App\Services\modulos\TallerPrecuentaPdfService())->generar($orden, $this->cargarEmpresaPdf($idEmpresa), 'D');
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar la precuenta: ' . $e->getMessage();
        }
        exit;
    }

    // ═══ ENVÍO POR WHATSAPP ══════════════════════════════════════════════════

    /**
     * Plantillas aprobadas por Meta que sirven para el taller, más el teléfono
     * del contacto ya normalizado a formato internacional.
     *
     * Se filtran las plantillas "rápidas" de otros módulos (facturas, roles,
     * retenciones…) para no ofrecer aquí algo que no corresponde; las plantillas
     * libres que cree la empresa sí aparecen todas.
     */
    public function getPlantillasWhatsappAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idOrden   = (int) ($_GET['id'] ?? 0);

        try {
            $config = (new \App\models\WhatsappConfig())->obtenerConfiguracion($idEmpresa);
            if (!$config || empty($config['access_token']) || empty($config['phone_number_id'])) {
                echo json_encode(['ok' => true, 'configurado' => false]);
                exit;
            }

            $todas = (new \App\models\WhatsappPlantilla())->getPlantillasAprobadas($idEmpresa);

            // Plantillas reservadas a otros módulos: no se ofrecen desde el taller.
            $deOtrosModulos = [
                'aviso_mensajes_pendientes', 'factura_por_cobrar', 'factura_venta',
                'proforma', 'cuenta_por_cobrar', 'renovacion_suscripcion', 'renovacion_firma_electronica',
                'retencion_compra', 'nota_credito', 'nota_debito', 'guia_remision',
                'rol_pagos', 'descuento_empleado', 'link_pago_payphone', 'link_pago_nuvei',
            ];
            $propias = ['orden_taller', 'informe_tecnico_taller', 'precuenta_taller'];

            $plantillas = [];
            foreach ($todas as $p) {
                $nombre = (string) ($p['nombre'] ?? '');
                if (in_array($nombre, $propias, true) || !in_array($nombre, $deOtrosModulos, true)) {
                    $plantillas[] = $p;
                }
            }

            // Teléfono del contacto de la orden, en formato internacional.
            $telefono = '593';
            if ($idOrden > 0) {
                $orden = $this->service->getDetalleCompleto($idOrden, $idEmpresa, false);
                if ($orden) {
                    $telefono = self::normalizarTelefono(
                        (string) ($orden['telefono_contacto'] ?? $orden['cliente_telefono'] ?? '')
                    );
                }
            }

            // Sugerencia: la plantilla propia del taller, si la empresa la creó.
            $idDefault = 0;
            foreach ($plantillas as $p) {
                if (in_array((string) $p['nombre'], $propias, true) || stripos((string) $p['nombre'], 'taller') !== false) {
                    $idDefault = (int) $p['id'];
                    break;
                }
            }

            echo json_encode([
                'ok'                   => true,
                'configurado'          => true,
                'plantillas'           => $plantillas,
                'telefono_cliente'     => $telefono,
                'id_plantilla_default' => $idDefault,
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Envía por WhatsApp la orden de trabajo, el informe técnico o la precuenta.
     *
     * El PDF se sube a Meta y viaja como documento en la cabecera de la
     * plantilla; las variables del cuerpo se rellenan con los datos del
     * vehículo. Mismo flujo que usa Factura de Venta.
     */
    public function enviarWhatsappAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa   = (int) $_SESSION['id_empresa'];
        $idOrden     = (int) ($_POST['id'] ?? 0);
        $idPlantilla = (int) ($_POST['id_plantilla'] ?? 0);
        $tipo        = trim($_POST['tipo'] ?? 'orden'); // orden | informe | precuenta
        $telefono    = preg_replace('/[^0-9]/', '', trim($_POST['telefono'] ?? ''));

        if ($idOrden <= 0 || $idPlantilla <= 0 || $telefono === '') {
            echo json_encode(['ok' => false, 'error' => 'Faltan datos para enviar el mensaje.']);
            exit;
        }
        if (str_starts_with($telefono, '593') && strlen($telefono) !== 12) {
            echo json_encode(['ok' => false, 'error' => 'El número para Ecuador (593) debe tener 12 dígitos.']);
            exit;
        }

        $tmpPdf = null;
        try {
            $orden = $this->service->getDetalleCompleto($idOrden, $idEmpresa, false);
            if (!$orden) throw new Exception('Orden no encontrada.');

            // Se busca dentro de las aprobadas: así la validación de estado va
            // implícita y no hace falta SQL suelto en el controlador.
            $plantilla = null;
            foreach ((new \App\models\WhatsappPlantilla())->getPlantillasAprobadas($idEmpresa) as $p) {
                if ((int) $p['id'] === $idPlantilla) { $plantilla = $p; break; }
            }
            if (!$plantilla) {
                throw new Exception('La plantilla no existe o no está aprobada por Meta.');
            }

            [$pdfString, $etiqueta, $archivo] = $this->generarPdfDocumento($tipo, $orden, $idEmpresa);
            if ($pdfString === '') throw new Exception('No se pudo generar el PDF.');

            $whatsapp = new \App\Services\WhatsappService();

            // Meta recibe el archivo por su cuenta: se sube y se borra enseguida.
            $tmpPdf = sys_get_temp_dir() . '/taller_' . $idOrden . '_' . uniqid() . '.pdf';
            file_put_contents($tmpPdf, $pdfString);
            $upload = $whatsapp->uploadMessageMedia($idEmpresa, $tmpPdf, 'application/pdf');
            @unlink($tmpPdf);
            $tmpPdf = null;

            if (empty($upload['success'])) {
                throw new Exception('No se pudo subir el PDF a WhatsApp: ' . ($upload['message'] ?? ''));
            }

            $componentes = $this->armarComponentesWhatsapp($plantilla, $orden, $etiqueta, $upload['media_id'], $archivo);

            $res = $whatsapp->sendTemplateMessage(
                $idEmpresa, $telefono, (string) $plantilla['nombre'], (string) $plantilla['idioma'], $componentes
            );
            if (empty($res['success'])) {
                throw new Exception('No se pudo enviar el mensaje: ' . ($res['message'] ?? ''));
            }

            $this->service->agregarNota(
                $idOrden, $idEmpresa, (int) $_SESSION['id_usuario'],
                $etiqueta . ' enviado por WhatsApp', 'Al ' . $telefono, null
            );

            echo json_encode(['ok' => true, 'mensaje' => $etiqueta . ' enviado por WhatsApp.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            if ($tmpPdf !== null && is_file($tmpPdf)) @unlink($tmpPdf);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Genera el PDF pedido.
     *
     * @return array{0:string,1:string,2:string} [contenido, etiqueta, nombre de archivo]
     */
    private function generarPdfDocumento(string $tipo, array $orden, int $idEmpresa): array
    {
        $empresa = $this->cargarEmpresaPdf($idEmpresa);
        $numero  = trim((string) ($orden['numero_orden'] ?? 'OT'));

        switch ($tipo) {
            case 'informe':
                return [
                    (string) (new \App\Services\modulos\TallerInformeTecnicoPdfService())->generar($orden, $empresa, 'S'),
                    'Informe técnico',
                    'Informe_Tecnico_' . $numero . '.pdf',
                ];
            case 'precuenta':
                return [
                    (string) (new \App\Services\modulos\TallerPrecuentaPdfService())->generar($orden, $empresa, 'S'),
                    'Precuenta',
                    'Precuenta_' . $numero . '.pdf',
                ];
            default:
                return [
                    (string) (new \App\Services\modulos\TallerOrdenPdfService())->generar($orden, $empresa, 'S'),
                    'Orden de trabajo',
                    'Orden_Taller_' . $numero . '.pdf',
                ];
        }
    }

    /**
     * Traduce la plantilla de Meta a los componentes de la API.
     *
     * Las variables del cuerpo se rellenan por posición con lo que el cliente
     * necesita saber: 1) su nombre, 2) la placa, 3) el número de orden y
     * 4) el total. Si la plantilla usa menos variables, se toman las primeras.
     */
    private function armarComponentesWhatsapp(array $plantilla, array $orden, string $etiqueta, string $mediaId, string $archivo): array
    {
        $componentes = json_decode((string) ($plantilla['componentes'] ?? '[]'), true) ?: [];
        $salida = [];

        $valores = [
            1 => (string) ($orden['cliente_nombre'] ?? $orden['nombre_usuario'] ?? 'Cliente'),
            2 => (string) ($orden['placa'] ?? ''),
            3 => (string) ($orden['numero_orden'] ?? ''),
            4 => '$' . number_format((float) ($orden['total'] ?? 0), 2),
        ];

        foreach ($componentes as $comp) {
            $tipoComp = strtoupper((string) ($comp['type'] ?? ''));

            if ($tipoComp === 'HEADER' && strtoupper((string) ($comp['format'] ?? '')) === 'DOCUMENT') {
                $salida[] = [
                    'type'       => 'header',
                    'parameters' => [[
                        'type'     => 'document',
                        'document' => ['id' => $mediaId, 'filename' => $archivo],
                    ]],
                ];
                continue;
            }

            if ($tipoComp === 'BODY') {
                $texto = (string) ($comp['text'] ?? '');
                if (preg_match_all('/{{(\d+)}}/', $texto, $m)) {
                    $parametros = [];
                    for ($i = 1; $i <= (int) max($m[1]); $i++) {
                        $parametros[] = ['type' => 'text', 'text' => $valores[$i] ?? ' '];
                    }
                    $salida[] = ['type' => 'body', 'parameters' => $parametros];
                }
            }
        }

        return $salida;
    }

    /** Teléfono en formato internacional para WhatsApp (Ecuador por defecto). */
    private static function normalizarTelefono(string $telefono): string
    {
        $tel = preg_replace('/\D/', '', $telefono);
        if ($tel === '') return '593';
        if (str_starts_with($tel, '593')) return $tel;
        if (str_starts_with($tel, '0'))   return '593' . substr($tel, 1);
        return '593' . $tel;
    }

    /** Envía por correo la orden de trabajo, el informe técnico o la precuenta. */
    public function enviarCorreoAjax(): void
    {
        ob_start();
        $this->requireLeer();
        header('Content-Type: application/json');

        $id        = (int) ($_POST['id'] ?? 0);
        $tipo      = trim($_POST['tipo'] ?? 'orden'); // orden | informe | precuenta
        $idEmpresa = (int) $_SESSION['id_empresa'];

        if (!$id) {
            if (ob_get_level() > 0) ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']);
            exit;
        }

        try {
            $orden = $this->service->getDetalleCompleto($id, $idEmpresa, false);
            if (!$orden) {
                if (ob_get_level() > 0) ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'Orden no encontrada.']);
                exit;
            }

            $empresa = $this->cargarEmpresaPdf($idEmpresa);
            [$pdfString, $etiqueta, $archivo] = $this->generarPdfDocumento($tipo, $orden, $idEmpresa);

            $numero = trim((string) ($orden['numero_orden'] ?? ''));

            // Destinatarios: los del formulario, o el correo del contacto/cliente.
            $correosDestino = trim($_POST['correos'] ?? '');
            if ($correosDestino === '') {
                $correosDestino = trim((string) ($orden['correo_contacto'] ?? '')) ?: (string) ($orden['cliente_email'] ?? '');
            }
            if ($correosDestino === '') {
                if (ob_get_level() > 0) ob_end_clean();
                echo json_encode(['ok' => false, 'mensaje' => 'No hay un correo registrado. Ingrese uno para enviar.']);
                exit;
            }

            $clienteNombre = (string) ($orden['cliente_nombre'] ?? $orden['nombre_usuario'] ?? 'Cliente');
            $empresaNombre = (string) ($empresa['nombre'] ?? '');

            $asunto = $etiqueta . ' ' . $numero . ($empresaNombre !== '' ? ' — ' . $empresaNombre : '');
            $cuerpo = "<div style='font-family:Arial,sans-serif;line-height:1.5;'>"
                . "<p>Estimad@ " . htmlspecialchars($clienteNombre) . ",</p>"
                . "<p>Adjunto encontrará " . ($tipo === 'precuenta' ? 'el detalle de los valores a pagar' : 'el ' . strtolower($etiqueta))
                . " de la orden <strong>" . htmlspecialchars($numero) . "</strong>"
                . ", correspondiente al vehículo de placa <strong>" . htmlspecialchars((string) ($orden['placa'] ?? '')) . "</strong>.</p>"
                . "<p>Saludos cordiales,<br>" . htmlspecialchars($empresaNombre) . "</p>"
                . "</div>";

            $emailSvc = new \App\Services\EnvioDocumentosSRIService();
            $enviado  = $emailSvc->enviarPdfSimple(
                $idEmpresa, $correosDestino, $clienteNombre, $asunto, $cuerpo, $pdfString,
                pathinfo($archivo, PATHINFO_FILENAME),
                $empresaNombre
            );

            if (ob_get_level() > 0) ob_end_clean();
            echo json_encode($enviado
                ? ['ok' => true, 'mensaje' => 'Correo enviado correctamente.']
                : ['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifique la configuración de correo o el destinatario.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            if (ob_get_level() > 0) ob_end_clean();
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }

    // ═══ EXPORTACIÓN DEL LISTADO ═════════════════════════════════════════════

    public function exportPdf(): void
    {
        $this->requireLeer();
        $rows = $this->filasParaExportar();

        try {
            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? 'TALLER MECÁNICO';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            ob_start();
?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8pt; table-layout: fixed; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: left; }
                td { border: 1px solid #ccc; padding: 4px; overflow: hidden; word-wrap: break-word; }
                .header { text-align: center; margin-bottom: 15px; width: 100%; }
                h1 { margin: 0; font-size: 14pt; color: #333; }
                h2 { margin: 3px 0 0 0; color: #666; font-size: 10pt; text-transform: uppercase; }
                .num { text-align: right; }
            </style>
            <page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">
                <div class="header">
                    <h1><?= htmlspecialchars($nombreEmpresa) ?></h1>
                    <h2>Órdenes de trabajo del taller</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 12%">Fecha</th>
                            <th style="width: 12%">N.° Orden</th>
                            <th style="width: 10%">Placa</th>
                            <th style="width: 18%">Vehículo</th>
                            <th style="width: 18%">Cliente</th>
                            <th style="width: 12%">Departamento</th>
                            <th style="width: 10%">Estado</th>
                            <th style="width: 8%">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['fecha_ingreso'] ? date('d-m-Y H:i', strtotime((string) $r['fecha_ingreso'])) : '') ?></td>
                                <td><?= htmlspecialchars((string) ($r['numero_orden'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($r['placa'] ?? '')) ?></td>
                                <td><?= htmlspecialchars(trim((string) ($r['marca'] ?? '') . ' ' . (string) ($r['modelo'] ?? ''))) ?></td>
                                <td><?= htmlspecialchars((string) ($r['cliente_nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($r['departamento_nombre'] ?? '')) ?></td>
                                <td><?= htmlspecialchars(\App\Services\modulos\TallerPdfHelper::etiquetaEstado((string) ($r['estado'] ?? ''))) ?></td>
                                <td class="num"><?= number_format((float) ($r['total'] ?? 0), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </page>
<?php
            $content = ob_get_clean();

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($content);
            $html2pdf->output('Ordenes_Taller_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar PDF: " . $e->getMessage();
            exit;
        }
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $rows = $this->filasParaExportar();

        try {
            $empresaModel  = new \App\models\Empresa();
            $empresa       = $empresaModel->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? '';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $headers = ['Fecha ingreso', 'N.° Orden', 'Placa', 'Vehículo', 'Cliente', 'Departamento',
                        'Estado', 'Aprobada', 'Repuestos', 'Mano de obra', 'IVA', 'Total', 'Documento'];
            $exportData = [];
            foreach ($rows as $r) {
                $exportData[] = [
                    $r['fecha_ingreso'] ? date('d-m-Y H:i:s', strtotime((string) $r['fecha_ingreso'])) : '',
                    (string) ($r['numero_orden'] ?? ''),
                    (string) ($r['placa'] ?? ''),
                    trim((string) ($r['marca'] ?? '') . ' ' . (string) ($r['modelo'] ?? '') . ' ' . (string) ($r['anio'] ?? '')),
                    (string) ($r['cliente_nombre'] ?? ''),
                    (string) ($r['departamento_nombre'] ?? ''),
                    \App\Services\modulos\TallerPdfHelper::etiquetaEstado((string) ($r['estado'] ?? '')),
                    \App\Helpers\Booleano::es($r['aprobado'] ?? false) ? 'Sí' : 'No',
                    number_format((float) ($r['subtotal_repuestos'] ?? 0), 2, '.', ''),
                    number_format((float) ($r['subtotal_mano_obra'] ?? 0), 2, '.', ''),
                    number_format((float) ($r['iva'] ?? 0), 2, '.', ''),
                    number_format((float) ($r['total'] ?? 0), 2, '.', ''),
                    (string) ($r['numero_documento'] ?? ''),
                ];
            }

            (new \App\Services\ReportService())->exportToExcel(
                'Órdenes de taller', $headers, $exportData, 'Órdenes de trabajo', $nombreEmpresa
            );
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/html');
            echo "Error al generar Excel: " . $e->getMessage();
            exit;
        }
    }

    // ═══ AUTOCOMPLETES Y AUXILIARES ══════════════════════════════════════════

    public function buscarVehiculosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $q = trim($_GET['q'] ?? $_GET['term'] ?? '');
        if (mb_strlen($q) < 2) {
            echo json_encode(['ok' => true, 'data' => []]);
            exit;
        }
        echo json_encode(['ok' => true, 'data' => $this->service->buscarVehiculos((int) $_SESSION['id_empresa'], $q)]);
        exit;
    }

    public function buscarClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $q  = trim($_GET['q'] ?? $_GET['term'] ?? '');
            $db = \App\core\Database::getConnection();
            $st = $db->prepare(
                "SELECT id, identificacion, nombre, direccion, email AS correo, telefono
                 FROM clientes
                 WHERE (nombre ILIKE :q OR identificacion ILIKE :q)
                   AND id_empresa = :e AND status = '1' AND eliminado = false
                 ORDER BY nombre ASC
                 LIMIT 10"
            );
            $st->execute([':q' => "%$q%", ':e' => (int) $_SESSION['id_empresa']]);
            echo json_encode(['ok' => true, 'data' => $st->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Repuestos y servicios del catálogo, con precio, IVA y saldo en bodega. */
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

            // Precios y variantes EN LOTE: dos consultas para los 15 resultados en
            // vez de dos por producto. El buscador dispara con cada tecla, así que
            // era donde más se acumulaban los viajes a la base.
            $idsProd    = array_column($result['rows'], 'id');
            $preciosMap = $repo->getPreciosPorProductos($idsProd, $idEmpresa);
            $variantMap = $repo->getVariantesPorProductos($idsProd, $idEmpresa);
            $rows = array_map(function ($p) use ($repo, $repoInv, $idEmpresa, $idBodega, $idOrden, $preciosMap, $variantMap) {
                $p['precios_lista'] = $preciosMap[(int) $p['id']] ?? [];
                $p['variantes']     = $variantMap[(int) $p['id']] ?? [];

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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function getStockAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idProducto = (int) ($_GET['id_producto'] ?? 0);
            $idBodega   = (int) ($_GET['id_bodega'] ?? 0);
            $idOrden    = (int) ($_GET['id_orden'] ?? 0);
            if (!$idProducto || !$idBodega) {
                echo json_encode(['ok' => false, 'stock' => 0]);
                exit;
            }
            $stock = (new \App\repositories\modulos\InventarioRepository())->getStockActual(
                $idProducto, $idBodega, (int) $_SESSION['id_empresa'],
                $idOrden > 0 ? $idOrden : null,
                $idOrden > 0 ? 'taller_orden' : null
            );
            echo json_encode(['ok' => true, 'stock' => $stock]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'stock' => 0, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Historial de servicios del vehículo: lo primero que pregunta el asesor. */
    public function historialVehiculoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idVehiculo = (int) ($_GET['id_vehiculo'] ?? 0);
        $excluir    = (int) ($_GET['excluir'] ?? 0);
        if ($idVehiculo <= 0) {
            echo json_encode(['ok' => true, 'data' => []]);
            exit;
        }
        echo json_encode([
            'ok'   => true,
            'data' => $this->repository->getHistorialVehiculo($idVehiculo, (int) $_SESSION['id_empresa'], $excluir),
        ]);
        exit;
    }

    /**
     * Departamentos activos. Se lee con el permiso del propio taller (no con el
     * del catálogo) porque el asesor los necesita para operar la orden, aunque
     * no pueda administrarlos. Se usa para refrescar los selectores del modal
     * después de crear un departamento al vuelo.
     */
    public function departamentosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        echo json_encode(['ok' => true, 'data' => $this->service->getDepartamentos((int) $_SESSION['id_empresa'])]);
        exit;
    }

    /** Plantilla del checklist de recepción configurada por la empresa. */
    public function plantillaChecklistAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        echo json_encode(['ok' => true, 'data' => $this->checklistService->getPlantilla((int) $_SESSION['id_empresa'])]);
        exit;
    }

    /** Indicadores: tiempo por departamento y productividad por técnico. */
    public function indicadoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $desde = trim($_GET['desde'] ?? date('Y-m-01'));
        $hasta = trim($_GET['hasta'] ?? date('Y-m-d'));

        echo json_encode(['ok' => true, 'data' => $this->service->getIndicadores((int) $_SESSION['id_empresa'], $desde, $hasta)]);
        exit;
    }

    // ═══ HELPERS ═════════════════════════════════════════════════════════════

    /** Rutas MVC de los documentos que la orden puede generar. */
    public const RUTA_FACTURA = 'modulos/factura-venta';
    public const RUTA_RECIBO  = 'modulos/recibo-venta';

    /**
     * Exige permiso de creación sobre el módulo del documento que se va a
     * emitir. Tener acceso al taller no habilita a facturar.
     */
    private function requirePermisoDocumento(string $tipo): void
    {
        $ruta = ($tipo === 'FACTURA') ? self::RUTA_FACTURA : self::RUTA_RECIBO;
        $perm = $this->permisosModuloPorRuta($ruta);

        if (empty($perm['crear']) && empty($perm['todo'])) {
            throw new Exception(
                'No tiene permiso para emitir '
                . ($tipo === 'FACTURA' ? 'facturas de venta' : 'recibos de venta') . '.'
            );
        }
    }

    /** Datos que necesitan tanto la vista principal como sus modales. */
    private function datosComunes(int $idEmpresa): array
    {
        $empresaRepo = new \App\repositories\modulos\EmpresaRepository();
        $secRepo = new \App\repositories\SecuencialRepository();
        $puntos = [];
        foreach ($empresaRepo->getPuntosEmision($idEmpresa) as $p) {
            $config = $secRepo->getConfigSecuencial((int) $p['id'], 'Ordenes de taller');
            if (empty($config['id'])) {
                continue;
            }
            $puntos[] = $p;
        }

        return [
            'empresa'       => $this->getEmpresaConfig($idEmpresa),
            'puntos'        => $puntos,
            // Series usadas realmente en órdenes existentes (para el filtro del listado,
            // distinto de $puntos que es "en qué serie se puede emitir un documento nuevo").
            'seriesFiltro'  => $this->repository->getSeriesDistintas($idEmpresa),
            'formasPago'    => $this->repository->getFormasPago(),
            'bodegas'       => $this->getBodegas($idEmpresa),
            'tarifasIva'    => $this->repository->getTarifasIva(),
            'unidades'      => $this->repository->getUnidadesMedida($idEmpresa),
            'departamentos' => $this->service->getDepartamentos($idEmpresa),
            'empleados'     => $this->getEmpleados($idEmpresa),
            'checklistBase' => $this->checklistService->getPlantilla($idEmpresa),
        ];
    }

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

    /** Filas del listado con los filtros vigentes, sin paginar (exportaciones). */
    private function filasParaExportar(): array
    {
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? 'fecha_ingreso');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? 'desc'));

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $data = $this->service->getListado($idEmpresa, $buscar, 1, 0, $ordenCol, $ordenDir, $idUsuarioFiltro);
        return $data['rows'];
    }

    private function cargarEmpresaPdf(int $idEmpresa): array
    {
        $empresaModel = new \App\models\Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos[0]['logo_ruta'])) {
            $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
        }
        try {
            $estConfig = (new \App\repositories\modulos\EmpresaRepository())
                ->getEstablecimientoConfig((int) ($establecimientos[0]['id'] ?? 0));
            if ($estConfig) { $empresa = array_merge($empresa, $estConfig); }
        } catch (\Throwable $e) {}
        return $empresa;
    }

    private function getEmpresaConfig(int $idEmpresa): array
    {
        $empresaModel = new \App\models\Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos)) {
            try {
                $estConfig = (new \App\repositories\modulos\EmpresaRepository())
                    ->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $empresaData = array_merge($empresaData, $estConfig);
                }
            } catch (\Throwable $e) {}
        }
        return $empresaData;
    }

    // ─── Render de la fila del listado (compartido con searchAjax) ────────────

    public static function badgeEstado(string $estado): string
    {
        $mapa = [
            'recepcion'       => ['secondary', 'Recepción'],
            'diagnostico'     => ['info',      'Diagnóstico'],
            'presupuesto'     => ['warning',   'Presupuesto'],
            'aprobada'        => ['primary',   'Aprobada'],
            'en_proceso'      => ['primary',   'En proceso'],
            'control_calidad' => ['info',      'Control calidad'],
            'terminada'       => ['success',   'Terminada'],
            'entregada'       => ['success',   'Entregada'],
            'facturada'       => ['success',   'Facturada'],
            'anulada'         => ['danger',    'Anulada'],
        ];
        [$color, $texto] = $mapa[$estado] ?? ['secondary', ucfirst($estado ?: 'Recepción')];

        return '<span class="badge bg-' . $color . ' bg-opacity-10 text-' . $color
             . ' border border-' . $color . ' border-opacity-25">' . htmlspecialchars($texto) . '</span>';
    }

    private static function filaListado(array $r): string
    {
        $fecha    = !empty($r['fecha_ingreso']) ? date('d-m-Y H:i', strtotime((string) $r['fecha_ingreso'])) : '';
        $dataJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
        $vehiculo = trim((string) ($r['marca'] ?? '') . ' ' . (string) ($r['modelo'] ?? ''));

        $depNombre = (string) ($r['departamento_nombre'] ?? '');
        $depColor  = (string) ($r['departamento_color'] ?? '#6c757d');
        $depHtml   = $depNombre !== ''
            ? '<span class="badge rounded-pill" style="background:' . htmlspecialchars($depColor) . '1a;color:'
              . htmlspecialchars($depColor) . ';border:1px solid ' . htmlspecialchars($depColor) . '40;">'
              . htmlspecialchars($depNombre) . '</span>'
            : '<span class="text-muted small">—</span>';

        $aprob = \App\Helpers\Booleano::es($r['aprobado'] ?? false)
            ? '<i class="bi bi-check-circle-fill text-success" title="Presupuesto aprobado"></i>'
            : '<i class="bi bi-clock-history text-warning" title="Sin aprobación del cliente"></i>';

        return '<tr class="tll-row" role="button" tabindex="0" data-row=\'' . $dataJson . '\' onclick="tllAbrirVer(this)">
                    <td class="ps-3" data-col="fecha_ingreso">' . htmlspecialchars($fecha) . '</td>
                    <td data-col="numero_orden" class="fw-bold text-primary">' . htmlspecialchars((string) ($r['numero_orden'] ?? '')) . '</td>
                    <td data-col="placa" class="fw-semibold">' . htmlspecialchars((string) ($r['placa'] ?? '')) . '</td>
                    <td data-col="vehiculo" class="text-truncate" style="max-width:180px">' . htmlspecialchars($vehiculo) . '</td>
                    <td data-col="cliente" class="text-truncate" style="max-width:200px">' . htmlspecialchars((string) ($r['cliente_nombre'] ?? '')) . '</td>
                    <td data-col="departamento">' . $depHtml . '</td>
                    <td class="text-center" data-col="aprobado">' . $aprob . '</td>
                    <td data-col="total" class="text-end">' . number_format((float) ($r['total'] ?? 0), 2) . '</td>
                    <td class="text-center pe-3" data-col="estado">' . self::badgeEstado((string) ($r['estado'] ?? '')) . '</td>
                  </tr>';
    }
}
