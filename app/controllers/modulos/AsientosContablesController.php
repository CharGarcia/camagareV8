<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\AsientoContableRepository;
use App\Rules\modulos\AsientoContableRules;
use App\Services\LogSistemaService;
use App\Services\modulos\AsientoContableService;
use App\models\CentroCosto;
use App\models\Proyecto;

class AsientosContablesController extends BaseModuloController
{
    private AsientoContableService $service;
    private const RUTA_MODULO = 'modulos/asientos_contables';

    public function __construct()
    {
        parent::__construct();
        $repository = new AsientoContableRepository();
        $rules = new AsientoContableRules();
        $logService = new LogSistemaService();
        $this->service = new AsientoContableService($repository, $rules, $logService);
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $perm      = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);

        // Sincronizar asientos pendientes automáticamente
        $sincronizador = new \App\Services\modulos\SincronizadorAsientosService();
        $sincronizador->sincronizar($idEmpresa, $idUsuario);
        $warnings = $sincronizador->getWarnings();

        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_asiento');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows   = $result['rows'];
        $total  = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $this->viewWithLayout('layouts.main', 'modulos.asientos_contables.index', [
            'titulo'     => 'Libro Diario / Asientos',
            'perm'       => $perm,
            'warnings'   => $warnings,
            'rutaModulo' => self::RUTA_MODULO,
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
            'perPage'    => $perPage,
            'buscar'     => $buscar,
            'ordenCol'   => $ordenCol,
            'ordenDir'   => $ordenDir,
            'vistaConfig'=> $prefsVista,
            'fullWidth'  => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO);
        $buscar    = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_asiento');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage   = 20;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows   = $result['rows'];
        $total  = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="7" class="text-center py-5 text-muted">No se encontraron asientos.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $statusBadge = '';
                if ($r['estado'] === 'contabilizado') $statusBadge = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Contabilizado</span>';
                elseif ($r['estado'] === 'anulado') $statusBadge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulado</span>';
                else $statusBadge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Borrador</span>';

                echo '<tr class="asiento-row" role="button" onclick="ASIENTO_abrirModal(' . $r['id'] . ')">
                        <td class="ps-3 fw-bold" data-col="numero_comprobante">' . htmlspecialchars($r['numero_comprobante'] ?? '') . '</td>
                        <td data-col="fecha_asiento">' . htmlspecialchars($r['fecha_asiento']) . '</td>
                        <td data-col="tipo_comprobante" class="text-capitalize">' . htmlspecialchars($r['tipo_comprobante']) . '</td>
                        <td data-col="concepto" class="small text-truncate" style="max-width: 250px;">' . htmlspecialchars($r['concepto']) . '</td>
                        <td data-col="modulo_origen" class="text-capitalize small text-muted">' . str_replace('_', ' ', htmlspecialchars($r['modulo_origen'] ?? '')) . '</td>
                        <td data-col="total_debe" class="text-end fw-bold">$' . number_format((float)($r['total_debe'] ?? 0), 2) . '</td>
                        <td class="text-center" data-col="estado">' . $statusBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" ' . $prevDisabled . ' onclick="cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary" ' . $nextDisabled . ' onclick="cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total"
        ]);
        exit;
    }

    public function getDetalleAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        
        $idEmpresa = (int)$_SESSION['id_empresa'];
        
        // Permite buscar por ID directo o por Origen (módulo + id_referencia)
        $id = (int)($_GET['id'] ?? 0);
        $modulo = trim($_GET['modulo'] ?? '');
        $idRef = (int)($_GET['id_ref'] ?? 0);

        if ($modulo !== '' && $idRef > 0) {
            $data = $this->service->getAsientoPorOrigen($modulo, $idRef, $idEmpresa);
        } else {
            $data = $this->service->getDetalleAsiento($id, $idEmpresa);
        }

        if (!$data) {
            echo json_encode(['ok' => false, 'error' => 'No se encontró el asiento.']);
        } else {
            echo json_encode(['ok' => true, 'data' => $data]);
        }
        exit;
    }

    public function getSelectDataAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        
        $idEmpresa = (int)$_SESSION['id_empresa'];
        
        $ccModel = new CentroCosto();
        $centros = $ccModel->getActivosPorEmpresa($idEmpresa);
        
        $pjModel = new Proyecto();
        $proyectos = $pjModel->getActivosPorEmpresa($idEmpresa);
        
        echo json_encode([
            'ok' => true, 
            'data' => [
                'centros_costo' => $centros,
                'proyectos' => $proyectos
            ]
        ]);
        exit;
    }

    public function store(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $data = $this->recogerDatosFormulario();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $id = $this->service->guardarAsiento($data['cabecera'], $data['detalles'], $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Asiento registrado correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function update(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $data = $this->recogerDatosFormulario();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            if (empty($data['cabecera']['id'])) {
                throw new \Exception('ID de asiento inválido.');
            }
            $id = $this->service->guardarAsiento($data['cabecera'], $data['detalles'], $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Asiento actualizado correctamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function anular(): void
    {
        $this->requireActualizar(); // O anular si existe el permiso
        header('Content-Type: application/json');

        $id = (int)($_POST['id'] ?? 0);
        $idEmpresa = (int)$_SESSION['id_empresa'];
        $idUsuario = (int)$_SESSION['id_usuario'];

        try {
            $this->service->anular($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'msg' => 'Asiento anulado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function recogerDatosFormulario(): array
    {
        $cabecera = [
            'id' => (int)($_POST['id'] ?? 0),
            'fecha_asiento' => trim($_POST['fecha_asiento'] ?? ''),
            'tipo_comprobante' => trim($_POST['tipo_comprobante'] ?? 'diario'),
            'numero_comprobante' => trim($_POST['numero_comprobante'] ?? ''),
            'concepto' => trim($_POST['concepto'] ?? ''),
            'estado' => trim($_POST['estado'] ?? 'contabilizado'), // Por default lo guardamos como contabilizado si no envían borrador
            'observaciones' => trim($_POST['observaciones'] ?? ''),
            'modulo_origen' => trim($_POST['modulo_origen'] ?? 'manual'),
            'id_referencia_origen' => !empty($_POST['id_referencia_origen']) ? (int)$_POST['id_referencia_origen'] : null,
        ];

        $detalles = [];
        if (!empty($_POST['detalles_json'])) {
            $det = json_decode($_POST['detalles_json'], true);
            if (is_array($det)) {
                $detalles = $det;
            }
        }

        return [
            'cabecera' => $cabecera,
            'detalles' => $detalles
        ];
    }
}
