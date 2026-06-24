<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\EgresoRepository;
use App\Services\modulos\EgresoService;
use App\Rules\modulos\EgresoRules;
use App\Services\LogSistemaService;
use App\models\Empresa;

class EgresosController extends BaseModuloController
{
    private EgresoService $service;
    private EgresoRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/egresos';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new EgresoRepository();
        $this->service    = new EgresoService(
            $this->repository,
            new EgresoRules(),
            new LogSistemaService()
        );
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $totalPages = (int) ceil($result['total'] / $perPage);

        $empresaModel = new Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa);
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $puntos = [];
        if (!empty($establecimientos)) {
            $puntos = $empresaModel->getPuntosEmision((int) $establecimientos[0]['id']);
        }

        // Usamos repositorio auxiliar para formas de pago si no es un método directo en EgresoRepository
        // O simplemente instanciamos el IngresoRepo que ya tiene el getFormasCobro genérico.
        // Mejor aún, la tabla es universal. Lo extraeré mediante repositorio dedicado de la empresa.
        $fpRepo = new \App\repositories\modulos\FormaPagoRepository(); 
        $formasPago = $fpRepo->getFormasFiltradas($idEmpresa, 'EGRESO');

        $conceptos  = $this->service->getConceptosEgreso($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/egresos/index', [
            'titulo'            => 'Egresos',
            'perm'              => $perm,
            'rows'              => $result['rows'],
            'total'             => $result['total'],
            'page'              => $page,
            'totalPages'        => $totalPages,
            'perPage'           => $perPage,
            'from'              => $result['total'] > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'to'                => $result['total'] > 0 ? min($page * $perPage, $result['total']) : 0,
            'buscar'            => $buscar,
            'ordenCol'          => $ordenCol,
            'ordenDir'          => $ordenDir,
            'vistaConfig'       => $prefsVista,
            'rutaModulo'        => $this->getRutaModulo(),
            'empresa'           => $empresaData,
            'establecimientos'  => $establecimientos,
            'puntos'            => $puntos,
            'formasPago'        => $formasPago,
            'conceptos'         => $conceptos,
            'fullWidth'         => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar     = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage    = 20;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-cash-stack fs-3 d-block mb-2"></i>No se encontraron egresos.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $fecha  = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
                
                $tipoLabels = [
                    'COMPRA' => 'Registro de Compra',
                    'LIQUIDACION' => 'Liquidación',
                    'ROL' => 'Rol de Pago',
                    'QUINCENA' => 'Quincena',
                    'PRESTAMO' => 'Préstamo',
                    'OTRO' => 'Otro Concepto'
                ];
                $tipoLabel = $tipoLabels[$r['tipo_egreso']] ?? $r['tipo_egreso'];
                
                $estado = $r['estado'] ?? 'registrado';
                $estCls = match ($estado) {
                    'anulado' => 'bg-danger bg-opacity-10 text-danger border-danger',
                    default   => 'bg-primary bg-opacity-10 text-primary border-primary',
                };
                $badge = '<span class="badge ' . $estCls . ' border border-opacity-25">' . ucfirst($estado) . '</span>';

                echo '<tr class="egreso-row" role="button" onclick="abrirModalEgresoVer(' . $r['id'] . ')">
                        <td class="ps-3" data-col="numero_egreso"><code>' . htmlspecialchars($r['numero_egreso'] ?? '') . '</code></td>
                        <td data-col="fecha_emision">' . $fecha . '</td>
                        <td data-col="tipo_egreso"><span class="badge bg-light text-dark border">' . htmlspecialchars($tipoLabel) . '</span></td>
                        <td class="fw-medium text-truncate" data-col="sujeto_nombre" style="max-width:200px">' . htmlspecialchars($r['sujeto_nombre'] ?? '') . '</td>
                        <td class="text-truncate text-muted" data-col="observaciones" style="max-width:200px">' . htmlspecialchars($r['observaciones'] ?? '') . '</td>
                        <td class="text-end fw-bold" data-col="monto_total">$' . number_format((float)$r['monto_total'], 2) . '</td>
                        <td class="text-center pe-3" data-col="estado">' . $badge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDis = ($page <= 1) ? 'disabled' : '';
        $nextDis = ($page >= $totalPages) ? 'disabled' : '';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm" ' . $prevDis . ' onclick="window.EGR_cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
              <button type="button" class="btn btn-outline-secondary btn-sm" ' . $nextDis . ' onclick="window.EGR_cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total"
        ]);
        exit;
    }

    public function getEgresoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $egreso = $this->service->getPorId($id, $idEmpresa);
        if (!$egreso) {
            echo json_encode(['ok' => false, 'mensaje' => 'Egreso no encontrado.']);
            exit;
        }

        echo json_encode(['ok' => true, 'data' => $egreso]);
        exit;
    }

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        $tipo    = 'Egresos'; // Map valid definition needed in SecuencialRepository map, assumes same fallback strategy.

        $secService = new \App\Services\SecuencialService();
        $res = $secService->obtenerSiguienteSecuencial($idPunto, $tipo);

        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    public function getProveedoresAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ProveedorRepository();
        $result = $repo->getListado($idEmpresa, $q, 1, 15, 'razon_social', 'ASC');

        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function getEmpleadosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\EmpleadoRepository();
        $result = $repo->getListado($idEmpresa, $q, 1, 15, 'nombre', 'ASC');

        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function getDocumentosPendientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa  = (int) $_SESSION['id_empresa'];
        $sujetoTipo = trim($_GET['tipo_sujeto'] ?? 'PROVEEDOR');
        $sujetoId   = (int) ($_GET['sujeto_id'] ?? 0);

        if ($sujetoId <= 0) {
            echo json_encode(['ok' => true, 'data' => []]);
            exit;
        }

        $docs = [];
        if ($sujetoTipo === 'PROVEEDOR') {
            $docs = $this->service->getDocumentosPendientesProveedor($sujetoId, $idEmpresa);
        } else if ($sujetoTipo === 'EMPLEADO') {
            $docs = $this->service->getDocumentosPendientesEmpleado($sujetoId, $idEmpresa);
        }

        echo json_encode(['ok' => true, 'data' => $docs]);
        exit;
    }

    public function guardarAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        try {
            $data = $_POST;
            if (isset($_POST['data'])) {
                $data = json_decode($_POST['data'], true);
            }

            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['usuario_id'] = (int) $_SESSION['id_usuario'];

            // Componer número Egreso
            $est = str_pad((string)($data['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $pto = str_pad((string)($data['punto_emision'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $sec = str_pad((string)($data['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
            $data['numero_egreso'] = "{$est}-{$pto}-{$sec}";

            $id = $this->service->registrar($data);

            echo json_encode(['ok' => true, 'mensaje' => 'Egreso registrado satisfactoriamente.', 'id' => $id]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getAsientoContableAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $id        = (int) ($_GET['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $asiento   = $this->service->getAsientoContable($id, $idEmpresa);
            echo json_encode(['ok' => true, 'asiento' => $asiento]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function actualizarPagosAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $data = $_POST;
            if (isset($_POST['data'])) {
                $data = json_decode($_POST['data'], true);
            }

            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                throw new \Exception("ID de egreso no válido.");
            }

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $pagos = $data['pagos'] ?? [];
            $fechaEmision = $data['fecha_emision'] ?? null;

            $this->service->actualizarPagos($id, $pagos, $idEmpresa, $idUsuario, $fechaEmision, $data);

            echo json_encode(['ok' => true, 'mensaje' => 'Formas de pago actualizadas con éxito.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function anularAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $this->service->anular($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Egreso anulado con éxito.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function buscarDocumentosPendientesEgresoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $q         = trim($_GET['q'] ?? '');
            $tipo      = strtoupper(trim($_GET['tipo'] ?? 'COMPRA'));
            $excluirId = isset($_GET['excluir_egreso_id']) && $_GET['excluir_egreso_id'] !== ''
                         ? (int) $_GET['excluir_egreso_id'] : null;

            if (!in_array($tipo, ['COMPRA', 'LIQUIDACION'])) {
                $tipo = 'COMPRA';
            }

            $result = $this->repository->buscarDocumentosPendientesEgreso($idEmpresa, $q, $tipo, $excluirId);
            echo json_encode(['ok' => true, 'data' => $result['data'], 'has_more' => $result['has_more']]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function verificarPeriodoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $fecha     = trim($_GET['fecha'] ?? '');
            $idEmpresa = (int) $_SESSION['id_empresa'];
            if (!$fecha) {
                echo json_encode(['ok' => false, 'mensaje' => 'Fecha no proporcionada.']);
                exit;
            }
            $this->service->verificarPeriodo($fecha, $idEmpresa);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getUltimoChequeAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idForma = (int) ($_GET['id_forma_pago'] ?? 0);
        if ($idForma <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'Forma de pago inválida']);
            exit;
        }

        $ultimo = $this->service->getUltimoNumeroCheque($idForma);
        
        // Tentar autoincrementar el número si es numérico
        $siguiente = '';
        if ($ultimo && preg_match('/^(\d+)$/', $ultimo, $matches)) {
            $siguiente = str_pad((string)((int)$matches[1] + 1), strlen($ultimo), '0', STR_PAD_LEFT);
        }

        echo json_encode([
            'ok' => true,
            'ultimo' => $ultimo,
            'siguiente' => $siguiente
        ]);
        exit;
    }
}
