<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\core\Controller;
use App\repositories\modulos\IngresoRepository;
use App\Services\modulos\IngresoService;
use App\Rules\modulos\IngresoRules;
use App\Services\LogSistemaService;
use App\models\Empresa;

class IngresosController extends BaseModuloController
{
    private IngresoService $service;
    private IngresoRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/ingresos';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new IngresoRepository();
        $this->service    = new IngresoService(
            $this->repository,
            new IngresoRules(),
            new LogSistemaService()
        );
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? $_GET['buscar'] ?? $_POST['buscar'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage  = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $totalPages = (int) ceil($result['total'] / $perPage);

        $empresaModel = new Empresa();
        $empresaData  = $empresaModel->getPorId($idEmpresa);
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $puntos = [];
        if (!empty($establecimientos)) {
            $puntos = $empresaModel->getPuntosEmision((int) $establecimientos[0]['id']);
        }

        $formasCobro = $this->service->getFormasCobro($idEmpresa);
        $conceptos   = $this->service->getConceptosIngreso($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/ingresos/index', [
            'titulo'            => 'Ingresos',
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
            'base'              => BASE_URL,
            'rutaModulo'        => $this->getRutaModulo(),
            'empresa'           => $empresaData,
            'establecimientos'  => $establecimientos,
            'puntos'            => $puntos,
            'formasCobro'       => $formasCobro,
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
        $page       = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol   = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_emision');
        $ordenDir   = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'DESC'));
        $perPage    = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result     = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows       = $result['rows'];
        $total      = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from       = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to         = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-wallet2 fs-3 d-block mb-2"></i>No se encontraron ingresos.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $rowData = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $fecha   = !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—';
                
                $tipoLabels = [
                    'FACTURA_VENTA' => 'Facturas de Venta',
                    'RECIBO_VENTA'  => 'Recibo de Venta',
                    'OTRO'          => 'Otro Ingreso'
                ];
                $tipoLabel = $tipoLabels[$r['tipo_ingreso']] ?? $r['tipo_ingreso'];
                
                $estado  = $r['estado'] ?? 'registrado';
                $estadoClass = match ($estado) {
                    'anulado'    => 'bg-danger bg-opacity-10 text-danger border-danger',
                    'borrador'   => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
                    default      => 'bg-success bg-opacity-10 text-success border-success',
                };
                $estadoBadge = '<span class="badge ' . $estadoClass . ' border border-opacity-25">' . ucfirst($estado) . '</span>';

                echo '<tr class="ingreso-row" role="button" tabindex="0" data-id="' . $r['id'] . '" onclick="abrirModalIngresoVer(' . $r['id'] . ')">
                        <td class="ps-3" data-col="numero_ingreso"><code class="text-secondary">' . htmlspecialchars($r['numero_ingreso'] ?? '') . '</code></td>
                        <td data-col="fecha_emision">' . $fecha . '</td>
                        <td data-col="tipo_ingreso"><span class="badge bg-light text-dark border">' . htmlspecialchars($tipoLabel) . '</span></td>
                        <td class="fw-medium text-truncate" data-col="recibo_de" style="max-width:200px">' . htmlspecialchars($r['recibo_de'] ?? $r['cliente_nombre'] ?? $r['concepto_nombre'] ?? '—') . '</td>
                        <td data-col="observaciones" class="text-truncate text-muted" style="max-width:200px">' . htmlspecialchars($r['observaciones'] ?? '') . '</td>
                        <td class="text-end fw-bold" data-col="monto_total">$' . number_format((float)($r['monto_total'] ?? 0), 2) . '</td>
                        <td class="text-center pe-3" data-col="estado">' . $estadoBadge . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm" ' . $prevDisabled . ' onclick="window.ING_cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
              <button type="button" class="btn btn-outline-secondary btn-sm" ' . $nextDisabled . ' onclick="window.ING_cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';
        $paginationHtml = ob_get_clean();

        $urlBase = BASE_URL . '/' . $this->getRutaModulo();
        echo json_encode([
            'ok'         => true,
            'rows'       => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'       => "$from-$to/$total",
            'total'      => $total,
            'pdf_url'    => $urlBase . '/export-pdf?b='    . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url'  => $urlBase . '/export-excel?b='  . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
        ]);
        exit;
    }

    public function getIngresoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $ingreso = $this->service->getPorId($id, $idEmpresa);
        if (!$ingreso) {
            echo json_encode(['ok' => false, 'mensaje' => 'Ingreso no encontrado.']);
            exit;
        }

        echo json_encode(['ok' => true, 'data' => $ingreso]);
        exit;
    }

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        $tipo    = 'Ingresos'; // Según DOCUMENT_MAP en SecuencialRepository

        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, $tipo);

        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    public function getClientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $buscar = trim($_GET['q'] ?? '');

        $repo = new \App\repositories\modulos\ClienteRepository();
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC');

        echo json_encode(['ok' => true, 'data' => $result['rows']]);
        exit;
    }

    public function buscarDocumentosPendientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q         = trim($_GET['q'] ?? '');
        $excluirId = isset($_GET['excluir_ingreso_id']) && $_GET['excluir_ingreso_id'] !== ''
                     ? (int) $_GET['excluir_ingreso_id'] : null;

        $result = $this->repository->buscarDocumentosPendientes($idEmpresa, $q, $excluirId);
        echo json_encode(['ok' => true, 'data' => $result['data'], 'has_more' => $result['has_more']]);
        exit;
    }

    public function getDocumentosPendientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idCliente = (int) ($_GET['id_cliente'] ?? 0);
        $excluirId = isset($_GET['excluir_ingreso_id']) && $_GET['excluir_ingreso_id'] !== '' ? (int)$_GET['excluir_ingreso_id'] : null;

        if (!$idCliente) {
            echo json_encode(['ok' => false, 'data' => [], 'mensaje' => 'Cliente requerido.']);
            exit;
        }

        $docs = $this->service->getFacturasPendientes($idCliente, $idEmpresa, $excluirId);
        echo json_encode(['ok' => true, 'data' => $docs]);
        exit;
    }

    public function guardarAjax(): void
    {
        header('Content-Type: application/json');
        
        try {
            $data = $_POST;
            if (isset($_POST['data'])) {
                $data = json_decode($_POST['data'], true);
            }

            // Verificar permisos dinámicamente
            $id = !empty($data['id']) ? (int) $data['id'] : 0;
            if ($id > 0) {
                $this->requireActualizar();
            } else {
                $this->requireCrear();
            }

            $data['id_empresa'] = (int) $_SESSION['id_empresa'];
            $data['id_usuario'] = (int) $_SESSION['id_usuario'];
            
            $est = str_pad((string)($data['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $punto = str_pad((string)($data['punto_emision'] ?? '001'), 3, '0', STR_PAD_LEFT);
            $sec = str_pad((string)($data['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
            $data['numero_ingreso'] = "{$est}-{$punto}-{$sec}";

            if ($id > 0) {
                $this->service->actualizar($id, $data);
                echo json_encode(['ok' => true, 'mensaje' => 'Ingreso actualizado correctamente.', 'id' => $id]);
            } else {
                $newId = $this->service->crear($data);
                echo json_encode(['ok' => true, 'mensaje' => 'Ingreso registrado correctamente.', 'id' => $newId]);
            }
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

    public function anularAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $this->service->anular($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Ingreso anulado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $this->service->eliminar($id, $idEmpresa, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Ingreso eliminado correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function getIngresoDependenciesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $db = \App\core\Database::getConnection();

        // 1. Puntos de Emisión
        $sqlP = "SELECT pe.id AS id_punto, pe.establecimiento AS estab, pe.punto,
                        pe.id_establecimiento AS id_establecimiento
                 FROM empresa_puntos_emision pe
                 WHERE pe.id_empresa = ? AND pe.eliminado = false AND pe.estado = true
                 ORDER BY pe.establecimiento, pe.punto";
        $stP = $db->prepare($sqlP);
        $stP->execute([$idEmpresa]);
        $puntos = $stP->fetchAll(\PDO::FETCH_ASSOC);

        // 2. Conceptos de Ingreso
        $sqlC = "SELECT id, nombre, comportamiento
                 FROM empresa_opciones_ingreso_egreso
                 WHERE id_empresa = ? AND aplica_ingresos = TRUE AND UPPER(estado) = 'ACTIVO' AND eliminado = FALSE
                 ORDER BY nombre ASC";
        $stC = $db->prepare($sqlC);
        $stC->execute([$idEmpresa]);
        $conceptos = $stC->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Formas de Cobro
        $sqlF = "SELECT id, nombre, tipo
                 FROM empresa_formas_pago
                 WHERE id_empresa = ? AND eliminado = false AND activo = true
                   AND (aplica_en = 'AMBAS' OR aplica_en = 'INGRESO')
                 ORDER BY nombre ASC";
        $stF = $db->prepare($sqlF);
        $stF->execute([$idEmpresa]);
        $formas = $stF->fetchAll(\PDO::FETCH_ASSOC);

        // 4. Bancos
        $sqlB = "SELECT id, nombre_banco
                 FROM empresa_bancos
                 WHERE id_empresa = ? AND eliminado = false AND estado = true
                 ORDER BY nombre_banco ASC";
        $stB = $db->prepare($sqlB);
        $stB->execute([$idEmpresa]);
        $bancos = $stB->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'ok'   => true,
            'data' => [
                'puntos'      => $puntos,
                'conceptos'   => $conceptos,
                'formas_cobro'=> $formas,
                'bancos'      => $bancos,
            ]
        ]);
        exit;
    }

    public function registrarCobroRapidoAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!$data || empty($data['id_factura'])) {
            echo json_encode(['ok' => false, 'mensaje' => 'Datos inválidos o ID de factura faltante.']);
            exit;
        }

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        try {
            $db = \App\core\Database::getConnection();

            // 1. Obtener factura con cliente
            $stFact = $db->prepare(
                "SELECT v.*, c.nombre AS cliente_nombre
                 FROM ventas_cabecera v
                 INNER JOIN clientes c ON c.id = v.id_cliente
                 WHERE v.id = ? AND v.id_empresa = ? AND v.eliminado = false"
            );
            $stFact->execute([(int)$data['id_factura'], $idEmpresa]);
            $factura = $stFact->fetch(\PDO::FETCH_ASSOC);

            if (!$factura) {
                throw new \Exception('Factura no encontrada.');
            }

            // 2. Obtener datos del punto de emisión
            $stPunto = $db->prepare(
                "SELECT id, establecimiento, punto, id_establecimiento
                 FROM empresa_puntos_emision
                 WHERE id = ? AND id_empresa = ? AND eliminado = false"
            );
            $stPunto->execute([(int)$data['id_punto_emision'], $idEmpresa]);
            $punto = $stPunto->fetch(\PDO::FETCH_ASSOC);

            if (!$punto) {
                throw new \Exception('Punto de emisión no válido.');
            }

            // 3. Obtener siguiente secuencial
            $secuencialService = new \App\Services\SecuencialService();
            $secRes = $secuencialService->obtenerSiguienteSecuencial((int)$data['id_punto_emision'], 'Ingresos');

            // 4. Calcular saldo anterior (total cobrado hasta ahora para esta factura)
            $stSaldo = $db->prepare(
                "SELECT COALESCE(SUM(id2.monto_cobrado), 0)
                 FROM ingresos_detalle id2
                 INNER JOIN ingresos_cabecera ic2 ON id2.id_ingreso = ic2.id
                 WHERE id2.tipo_documento = 'FACTURA'
                   AND id2.id_referencia_documento = ?
                   AND ic2.estado != 'anulado'
                   AND ic2.eliminado = false"
            );
            $stSaldo->execute([(int)$data['id_factura']]);
            $totalCobrado  = (float) $stSaldo->fetchColumn();
            $saldoAnterior = round((float)$factura['importe_total'] - $totalCobrado, 2);
            $montoCobrar   = round((float)$data['monto_cobrar'], 2);

            $numDoc = $factura['establecimiento'] . '-' . $factura['punto_emision'] . '-' . $factura['secuencial'];

            $payload = [
                'id_empresa'          => $idEmpresa,
                'id_establecimiento'  => (int)($punto['id_establecimiento'] ?? 0),
                'id_punto_emision'    => (int)$punto['id'],
                'id_cliente'          => (int)$factura['id_cliente'],
                'id_usuario'          => $idUsuario,
                'fecha_emision'       => $data['fecha_emision'],
                'establecimiento'     => $punto['establecimiento'],
                'punto_emision'       => $punto['punto'],
                'secuencial'          => $secRes['secuencial'],
                'numero_ingreso'      => $punto['establecimiento'] . '-' . $punto['punto'] . '-' . $secRes['secuencial'],
                'tipo_ingreso'        => 'FACTURA_VENTA',
                'id_ingreso_concepto' => !empty($data['id_ingreso_concepto']) ? (int)$data['id_ingreso_concepto'] : null,
                'monto_total'         => $montoCobrar,
                'observaciones'       => !empty($data['observaciones']) ? $data['observaciones'] : ('Cobro de factura ' . $numDoc),
                'recibo_de'           => $factura['cliente_nombre'],
                'id_recibo_cliente'   => (int)$factura['id_cliente'],
                'detalles'            => [
                    [
                        'tipo_documento'          => 'FACTURA',
                        'id_referencia_documento' => (int)$data['id_factura'],
                        'numero_documento'        => $numDoc,
                        'descripcion'             => 'Cobro de factura ' . $numDoc,
                        'monto_documento'         => (float)$factura['importe_total'],
                        'saldo_anterior'          => $saldoAnterior,
                        'monto_cobrado'           => $montoCobrar,
                        'saldo_actual'            => max(0, $saldoAnterior - $montoCobrar),
                    ]
                ],
                'pagos' => [
                    [
                        'id_forma_cobro'          => (int)$data['id_forma_cobro'],
                        'monto'                   => $montoCobrar,
                        'referencia'              => $data['referencia'] ?? null,
                        'tipo_operacion_bancaria' => $data['tipo_operacion_bancaria'] ?? null,
                        'numero_cheque'           => $data['numero_operacion'] ?? null,
                    ]
                ],
            ];

            $idIngreso = $this->service->crear($payload);
            echo json_encode(['ok' => true, 'msg' => 'Cobro registrado con éxito.', 'id_ingreso' => $idIngreso]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
