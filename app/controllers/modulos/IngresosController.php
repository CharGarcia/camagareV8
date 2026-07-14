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

    /** Buscador predictivo de cuentas contables de movimiento para la grilla manual de ingresos. */
    public function searchCuentasAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $q = trim($_GET['q'] ?? '');

        $repoCta = new \App\repositories\modulos\PlanCuentaRepository();
        $cuentas = $repoCta->searchCuentas($idEmpresa, $q, '', 20);
        echo json_encode(['ok' => true, 'data' => $cuentas]);
        exit;
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

        // Saldo actual de cada forma (anticipos se resuelven por cliente vía AJAX)
        $fpRepo = new \App\repositories\modulos\FormaPagoRepository();
        $saldosFormas = (new \App\Services\modulos\FormaPagoService($fpRepo))->getSaldosActuales($idEmpresa);
        foreach ($formasCobro as &$fc) {
            $esAnt = (($fc['tipo'] ?? '') === 'ANTICIPO');
            $fc['es_anticipo'] = $esAnt;
            $fc['saldo']       = $esAnt ? null : (float)($saldosFormas[(int)$fc['id']] ?? 0);
        }
        unset($fc);

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

    /** Saldo de un anticipo de cliente para el cliente seleccionado. */
    public function getSaldoAnticipoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idForma   = (int) ($_GET['id_forma'] ?? 0);
        $idTercero = (int) ($_GET['id_tercero'] ?? 0);

        if ($idForma <= 0 || $idTercero <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'Forma y cliente requeridos.']);
            exit;
        }

        $fpService = new \App\Services\modulos\FormaPagoService(new \App\repositories\modulos\FormaPagoRepository());
        $saldo = $fpService->getSaldoAnticipo($idEmpresa, $idForma, $idTercero);
        echo json_encode(['ok' => true, 'saldo' => $saldo]);
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
        // soloActivos = true: excluir clientes inactivos en la selección.
        $result = $repo->getListado($idEmpresa, $buscar, 1, 15, 'nombre', 'ASC', null, true);

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
        // 'RECIBO' cuando el ingreso es de tipo Recibo de Venta; 'FACTURA' por defecto.
        $tipoDoc   = strtoupper(trim($_GET['tipo'] ?? '')) === 'RECIBO' ? 'RECIBO' : 'FACTURA';

        $result = $this->repository->buscarDocumentosPendientes($idEmpresa, $q, $excluirId, $tipoDoc);
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

            // 4. Calcular saldo anterior = importe_total - cobros previos - retenciones de venta
            //    (mismo criterio que el selector de documentos pendientes).
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

            $stRet = $db->prepare(
                "SELECT COALESCE(SUM(total_renta + total_iva + total_isd), 0)
                 FROM retencion_venta_cabecera
                 WHERE id_venta = ? AND id_empresa = ? AND eliminado = false
                   AND tipo_ambiente = (SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?)"
            );
            $stRet->execute([(int)$data['id_factura'], $idEmpresa, $idEmpresa]);
            $totalRetenido = (float) $stRet->fetchColumn();

            $saldoAnterior = round((float)$factura['importe_total'] - $totalCobrado - $totalRetenido, 2);
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
                'secuencial'          => $secRes['formateado'],
                'numero_ingreso'      => str_pad((string)($punto['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT)
                                         . '-' . str_pad((string)($punto['punto'] ?? '001'), 3, '0', STR_PAD_LEFT)
                                         . '-' . $secRes['formateado'],
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
                        // Fecha en que se podrá cobrar el cheque (control de posfechados)
                        'fecha_cobro'             => !empty($data['fecha_cobro']) ? $data['fecha_cobro'] : null,
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

    /**
     * Genera el PDF (Comprobante de Ingreso). Si la empresa tiene una plantilla
     * activa para 'ingreso' se usa el diseñador; si no, el modelo general.
     */
    public function pdf(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $ingreso = $this->service->getPorId($id, $idEmpresa);
            if (!$ingreso) { http_response_code(404); echo 'Ingreso no encontrado'; exit; }

            $empresa   = $this->cargarEmpresaParaPdf($idEmpresa);
            $detalles  = $ingreso['detalles'] ?? [];
            $pagos     = $ingreso['pagos'] ?? [];
            $asiento   = $this->service->getAsientoContable($id, $idEmpresa);

            // Fase 2 (personalización): renderer si hay plantilla activa 'ingreso'.
            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'ingreso');
            if ($plantilla) {
                $renderer->generar($plantilla, $ingreso, $detalles, $pagos, [], $empresa, 'D');
            } else {
                (new \App\Services\modulos\ComprobanteCajaPdfService())
                    ->generarIngreso($ingreso, $detalles, $pagos, $empresa, 'D', $asiento);
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /** Datos de la empresa (con logo del establecimiento) para el PDF. */
    private function cargarEmpresaParaPdf(int $idEmpresa): array
    {
        $empresaModel = new Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos[0]['logo_ruta'])) {
            $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
        }
        return $empresa;
    }

    /** Envía por correo el PDF del comprobante de ingreso. */
    public function enviarCorreoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            if (!$id) { echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

            $ingreso = $this->service->getPorId($id, $idEmpresa);
            if (!$ingreso) { echo json_encode(['ok' => false, 'mensaje' => 'Ingreso no encontrado.']); exit; }

            // Destino: correo del POST o, si viene vacío, el del cliente.
            $correo = trim($_POST['correo'] ?? '');
            if ($correo === '') {
                $idCli = (int) ($ingreso['id_cliente'] ?? $ingreso['id_recibo_cliente'] ?? 0);
                if ($idCli > 0) {
                    $st = \App\core\Database::getConnection()
                        ->prepare("SELECT email FROM clientes WHERE id = ? AND id_empresa = ?");
                    $st->execute([$idCli, $idEmpresa]);
                    $correo = trim((string) $st->fetchColumn());
                }
            }
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['ok' => false, 'mensaje' => 'Ingrese un correo válido.']);
                exit;
            }

            $empresa    = new Empresa();
            $empresaRow = $empresa->getPorId($idEmpresa) ?? [];
            $num        = (string) ($ingreso['numero_ingreso'] ?? $id);
            $nombreDest = (string) ($ingreso['recibo_de'] ?? $ingreso['cliente_nombre'] ?? 'Cliente');
            $asunto     = 'Comprobante de Ingreso ' . $num;
            $cuerpo     = $this->construirCuerpoCorreoIngreso($ingreso, $empresaRow, $nombreDest);

            // Solo el detalle en el cuerpo (HTML), SIN adjuntar PDF.
            $ok = (new \App\Services\EnvioDocumentosSRIService())->enviarAvisoSimple(
                $idEmpresa, $correo, $nombreDest, $asunto, $cuerpo, (string) ($empresaRow['nombre'] ?? '')
            );

            echo json_encode($ok
                ? ['ok' => true, 'mensaje' => 'Comprobante enviado a ' . $correo]
                : ['ok' => false, 'mensaje' => 'No se pudo enviar. Verifica la configuración de correo de la empresa.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }

    /** Cuerpo HTML del correo con el detalle del ingreso (sin PDF). */
    private function construirCuerpoCorreoIngreso(array $ing, array $empresa, string $nombreDest): string
    {
        $num      = htmlspecialchars((string) ($ing['numero_ingreso'] ?? ''));
        $fecha    = !empty($ing['fecha_emision']) ? date('d/m/Y', strtotime((string) $ing['fecha_emision'])) : '';
        $ident    = htmlspecialchars((string) ($ing['cliente_ruc'] ?? ''));
        $concepto = htmlspecialchars(trim((string) ($ing['observaciones'] ?? ''))) ?: '—';
        $total    = (float) ($ing['monto_total'] ?? 0);
        $letras   = $this->montoEnLetras($total);
        $sujeto   = htmlspecialchars($nombreDest);

        $filasDoc = '';
        foreach (($ing['detalles'] ?? []) as $d) {
            $tipo  = htmlspecialchars((string) ($d['tipo_documento'] ?? ''));
            $ndoc  = htmlspecialchars((string) ($d['numero_documento'] ?? '')) ?: '—';
            $desc  = htmlspecialchars((string) ($d['descripcion'] ?? '')) ?: '—';
            $monto = number_format((float) ($d['monto_cobrado'] ?? 0), 2);
            $filasDoc .= "<tr><td style='border:1px solid #ddd;padding:5px'>$tipo</td><td style='border:1px solid #ddd;padding:5px'>$ndoc</td><td style='border:1px solid #ddd;padding:5px'>$desc</td><td style='border:1px solid #ddd;padding:5px;text-align:right'>\$$monto</td></tr>";
        }

        $filasPago = '';
        foreach (($ing['pagos'] ?? []) as $p) {
            $forma = htmlspecialchars((string) ($p['forma_cobro_nombre'] ?? ''));
            $ref   = trim((string) ($p['referencia'] ?? ''));
            $tipoOp = trim((string) ($p['tipo_operacion_bancaria'] ?? ''));
            if ($tipoOp !== '') { $ref = $ref !== '' ? ($tipoOp . ' — ' . $ref) : $tipoOp; }
            $ref   = htmlspecialchars($ref) ?: '—';
            $monto = number_format((float) ($p['monto'] ?? 0), 2);
            $filasPago .= "<tr><td style='border:1px solid #ddd;padding:5px'>$forma</td><td style='border:1px solid #ddd;padding:5px'>$ref</td><td style='border:1px solid #ddd;padding:5px;text-align:right'>\$$monto</td></tr>";
        }

        $emp = htmlspecialchars((string) ($empresa['nombre'] ?? ''));
        $th  = "style='border:1px solid #ddd;padding:5px;background:#f0f2f5;text-align:left'";

        return "
        <div style='font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333;max-width:640px'>
            <p>Estimado/a <strong>$sujeto</strong>,</p>
            <p>Le compartimos el detalle de su comprobante de ingreso:</p>
            <table style='border-collapse:collapse;margin-bottom:12px'>
                <tr><td style='padding:3px 8px'><strong>Comprobante N.°</strong></td><td style='padding:3px 8px'>$num</td></tr>
                <tr><td style='padding:3px 8px'><strong>Fecha</strong></td><td style='padding:3px 8px'>$fecha</td></tr>
                <tr><td style='padding:3px 8px'><strong>Recibí de</strong></td><td style='padding:3px 8px'>$sujeto</td></tr>
                " . ($ident !== '' ? "<tr><td style='padding:3px 8px'><strong>Identificación</strong></td><td style='padding:3px 8px'>$ident</td></tr>" : '') . "
                <tr><td style='padding:3px 8px'><strong>Concepto</strong></td><td style='padding:3px 8px'>$concepto</td></tr>
            </table>
            <p style='margin:6px 0'><strong>Documentos cobrados</strong></p>
            <table style='border-collapse:collapse;width:100%;margin-bottom:12px'>
                <tr><th $th>Tipo</th><th $th>N.° Documento</th><th $th>Descripción</th><th $th style='text-align:right'>Monto</th></tr>
                $filasDoc
            </table>
            <p style='margin:6px 0'><strong>Formas de cobro</strong></p>
            <table style='border-collapse:collapse;width:100%;margin-bottom:12px'>
                <tr><th $th>Forma</th><th $th>Referencia</th><th $th style='text-align:right'>Valor</th></tr>
                $filasPago
            </table>
            <p style='font-size:16px'><strong>TOTAL: \$" . number_format($total, 2) . "</strong><br>
               <span style='font-size:12px;color:#666'>Son: $letras dólares</span></p>
            <p style='margin-top:14px'>Agradecemos su pago.</p>
            <hr style='border:none;border-top:1px solid #eee'>
            <p style='font-size:12px;color:#888'>$emp<br>Mensaje informativo generado automáticamente.</p>
        </div>";
    }

    /** Monto en letras (reutiliza el validador global num_letras). */
    private function montoEnLetras(float $monto): string
    {
        require_once \MVC_ROOT . '/app/validadores/numero_letras.php';
        if (function_exists('num_letras')) {
            return trim(preg_replace('/\s+/', ' ', (string) num_letras(number_format($monto, 2, '.', ''))));
        }
        return number_format($monto, 2);
    }
}
