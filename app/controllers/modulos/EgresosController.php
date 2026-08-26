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
            $secRepo = new \App\repositories\SecuencialRepository();
            foreach ($empresaModel->getPuntosEmision((int) $establecimientos[0]['id']) as $p) {
                $config = $secRepo->getConfigSecuencial((int) $p['id'], 'Egresos');
                if (empty($config['id'])) {
                    continue;
                }
                $puntos[] = $p;
            }
        }
        // Series REALMENTE usadas en egresos guardados, para el filtro "Serie"
        // del buscador — a diferencia de $puntos (solo sirve para elegir la
        // serie de un egreso NUEVO), esto incluye series de cualquier
        // establecimiento y aunque el punto ya no tenga secuencial configurado.
        $seriesFiltro = $this->repository->getSeriesDistintas($idEmpresa);

        // Usamos repositorio auxiliar para formas de pago si no es un método directo en EgresoRepository
        // O simplemente instanciamos el IngresoRepo que ya tiene el getFormasCobro genérico.
        // Mejor aún, la tabla es universal. Lo extraeré mediante repositorio dedicado de la empresa.
        $fpRepo = new \App\repositories\modulos\FormaPagoRepository();
        $formasPago = $fpRepo->getFormasFiltradas($idEmpresa, 'EGRESO');

        // Saldo actual de cada forma (anticipos se resuelven por proveedor vía AJAX)
        $saldosFormas = (new \App\Services\modulos\FormaPagoService($fpRepo))->getSaldosActuales($idEmpresa);
        foreach ($formasPago as &$fp) {
            $esAnt = (($fp['tipo'] ?? '') === 'ANTICIPO');
            $fp['es_anticipo'] = $esAnt;
            $fp['saldo']       = $esAnt ? null : (float)($saldosFormas[(int)$fp['id']] ?? 0);
        }
        unset($fp);

        $conceptos  = $this->service->getConceptosEgreso($idEmpresa);

        // Los botones de concepto ligados a un módulo (Compra/Liquidación/Nómina) solo se
        // muestran si hay algún documento pendiente de ese tipo en la empresa; los conceptos
        // sin módulo (GENERAL) y sin búsqueda de documentos (p. ej. Anticipo Proveedor) no
        // dependen de esto y siempre se muestran.
        $comportamientosConPendientes = [];
        foreach (['COMPRA', 'LIQUIDACION', 'ROL'] as $tipoDoc) {
            $chk = $this->repository->buscarDocumentosPendientesEgreso($idEmpresa, '', $tipoDoc);
            if (!empty($chk['data'])) {
                $comportamientosConPendientes[] = $tipoDoc;
            }
        }

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
            'seriesFiltro'      => $seriesFiltro,
            'formasPago'        => $formasPago,
            'conceptos'         => $conceptos,
            'comportamientosConPendientes' => $comportamientosConPendientes,
            'fullWidth'         => true,
        ]);
    }

    /** Buscador predictivo de cuentas contables de movimiento para la grilla manual de egresos. */
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

    /** Saldo de un anticipo a proveedor para el proveedor seleccionado. */
    public function getSaldoAnticipoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idForma   = (int) ($_GET['id_forma'] ?? 0);
        $idTercero = (int) ($_GET['id_tercero'] ?? 0);

        if ($idForma <= 0 || $idTercero <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'Forma y proveedor requeridos.']);
            exit;
        }

        $fpService = new \App\Services\modulos\FormaPagoService(new \App\repositories\modulos\FormaPagoRepository());
        $saldo = $fpService->getSaldoAnticipo($idEmpresa, $idForma, $idTercero);
        echo json_encode(['ok' => true, 'saldo' => $saldo]);
        exit;
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

                $tipoLabel = \App\Helpers\TipoDocumentoHelper::egresoLabel(
                    $r['tipos_detalle'] ?? null,
                    $r['tipo_egreso'] ?? null,
                    $r['concepto_nombre'] ?? null
                );

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
        $result = $repo->getListado($idEmpresa, $q, 1, 15, 'nombres_apellidos', 'ASC');

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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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

            if (!in_array($tipo, ['COMPRA', 'LIQUIDACION', 'ROL'])) {
                $tipo = 'COMPRA';
            }

            $result = $this->repository->buscarDocumentosPendientesEgreso($idEmpresa, $q, $tipo, $excluirId);
            echo json_encode(['ok' => true, 'data' => $result['data'], 'has_more' => $result['has_more']]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
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

    /**
     * Genera el PDF (Comprobante de Egreso). Si la empresa tiene una plantilla
     * activa para 'egreso' se usa el diseñador; si no, el modelo general.
     */
    public function pdf(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $egreso = $this->service->getPorId($id, $idEmpresa);
            if (!$egreso) { http_response_code(404); echo 'Egreso no encontrado'; exit; }

            $empresa  = $this->cargarEmpresaParaPdf($idEmpresa);
            $detalles = $egreso['detalles'] ?? [];
            $pagos    = $egreso['pagos'] ?? [];
            $asiento  = $this->service->getAsientoContable($id, $idEmpresa);

            $renderer  = new \App\Services\PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'egreso');
            if ($plantilla) {
                $renderer->generar($plantilla, $egreso, $detalles, $pagos, [], $empresa, 'D', $asiento);
            } else {
                (new \App\Services\modulos\ComprobanteCajaPdfService())
                    ->generarEgreso($egreso, $detalles, $pagos, $empresa, 'D', $asiento);
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    // ── Impresión de cheques ────────────────────────────────────────────────────

    /** Estado de impresión de uno o varios pagos-cheque (JSON). */
    public function estadoChequesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $ids = $this->parseIdsCheque($_GET['ids'] ?? ($_GET['id'] ?? ''));
            $estados = (new \App\Services\modulos\ChequeImpresionService())->estado($idEmpresa, $ids);
            echo json_encode(['ok' => true, 'estados' => $estados]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage(), 'estados' => []]);
        }
        exit;
    }

    /** Lista de cheques por imprimir para el modal masivo (JSON). */
    public function chequesPorImprimirAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros = [
                'id_forma_pago'   => (int) ($_GET['id_forma_pago'] ?? 0),
                'desde'           => trim($_GET['desde'] ?? ''),
                'hasta'           => trim($_GET['hasta'] ?? ''),
                'buscar'          => trim($_GET['b'] ?? ''),
                'solo_pendientes' => (($_GET['solo_pendientes'] ?? '1') === '1'),
            ];
            $rows = (new \App\Services\modulos\ChequeImpresionService())->listarPorImprimir($idEmpresa, $filtros);
            echo json_encode(['ok' => true, 'cheques' => $rows]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage(), 'cheques' => []]);
        }
        exit;
    }

    /** Genera y envía el PDF de los cheques indicados; registra la impresión. */
    public function imprimirCheque(): void
    {
        $this->requireLeer();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
            $ids = $this->parseIdsCheque($_GET['ids'] ?? ($_GET['id'] ?? ''));
            if (empty($ids)) { http_response_code(400); echo 'Sin cheques seleccionados.'; exit; }

            $pdf = (new \App\Services\modulos\ChequeImpresionService())
                ->imprimirLote($idEmpresa, $ids, $idUsuario);

            $nombre = (count($ids) === 1) ? ('Cheque_' . $ids[0] . '.pdf') : 'Cheques.pdf';
            // dl=1 → descarga (attachment); por defecto inline para imprimir directo.
            $dispo = (($_GET['dl'] ?? '') === '1') ? 'attachment' : 'inline';
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . $dispo . '; filename="' . $nombre . '"');
            header('Content-Length: ' . strlen($pdf));
            echo $pdf;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Error al imprimir cheque: ' . $e->getMessage();
        }
        exit;
    }

    /**
     * Resuelve (o crea) la plantilla de impresión de cheque del banco de la forma
     * de pago indicada, y devuelve la URL del diseñador visual para configurarla.
     * Afecta a TODOS los cheques de ese banco (no solo al pago actual).
     */
    public function configurarImpresionChequeAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $idEmpresa    = (int) $_SESSION['id_empresa'];
            $idUsuario    = (int) ($_SESSION['id_usuario'] ?? 0);
            $idFormaPago  = (int) ($_GET['id_forma_pago'] ?? 0);
            if ($idFormaPago <= 0) {
                echo json_encode(['ok' => false, 'mensaje' => 'Selecciona una cuenta/banco específico.']);
                exit;
            }

            $forma = (new \App\repositories\modulos\FormaPagoRepository())->getPorId($idFormaPago, $idEmpresa);
            if (!$forma) {
                echo json_encode(['ok' => false, 'mensaje' => 'Forma de pago no encontrada.']);
                exit;
            }
            $idBanco = (int) ($forma['id_banco'] ?? 0);
            if ($idBanco <= 0) {
                echo json_encode(['ok' => false, 'mensaje' => 'Esta forma de pago no tiene un banco asociado.']);
                exit;
            }

            $id  = (new \App\Services\modulos\ChequeImpresionService())
                ->resolverConfiguracionImpresion($idEmpresa, $idBanco, $idUsuario, (string) ($forma['banco_nombre'] ?? ''));
            $url = BASE_URL . '/modulos/plantillas-pdf?action=disenador&id=' . $id;
            echo json_encode(['ok' => true, 'id' => $id, 'url' => $url]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /** Actualiza la fecha de cobro de un cheque (si no está reportado como cobrado). */
    public function actualizarFechaCobroChequeAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
            $idPago    = (int) ($_POST['id_pago'] ?? 0);
            $fecha     = trim($_POST['fecha_cobro'] ?? '');
            if (!$idPago || $fecha === '') {
                echo json_encode(['ok' => false, 'mensaje' => 'Datos incompletos.']); exit;
            }
            $this->service->actualizarFechaCobroCheque($idEmpresa, $idPago, $fecha, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Fecha de cobro actualizada.', 'fecha_cobro' => $fecha]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /** Cambia el nombre a imprimir en el cheque (sin cambiar el beneficiario del egreso). */
    public function actualizarBeneficiarioChequeAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
            $idPago    = (int) ($_POST['id_pago'] ?? 0);
            $nombre    = trim($_POST['nombre'] ?? '');
            if (!$idPago) { echo json_encode(['ok' => false, 'mensaje' => 'Pago no indicado.']); exit; }
            $this->service->actualizarBeneficiarioCheque($idEmpresa, $idPago, $nombre, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Nombre del cheque actualizado.', 'nombre' => $nombre]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Anula un cheque puntual (error de impresión, cheque dañado, etc.) dejando
     * el pago como historial. El egreso sigue vigente; si queda sin cobertura,
     * el usuario agrega otra forma de pago desde el mismo modal.
     */
    public function anularChequeAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
            $idPago    = (int) ($_POST['id_pago'] ?? 0);
            $motivo    = trim($_POST['motivo'] ?? '');
            if (!$idPago) { echo json_encode(['ok' => false, 'mensaje' => 'Pago no indicado.']); exit; }
            $this->service->anularCheque($idEmpresa, $idPago, $motivo, $idUsuario);
            echo json_encode(['ok' => true, 'mensaje' => 'Cheque anulado correctamente.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /** Normaliza el parámetro ids ("1,2,3") a un array de enteros. */
    private function parseIdsCheque($raw): array
    {
        $raw = is_array($raw) ? implode(',', $raw) : (string) $raw;
        $ids = array_filter(array_map('intval', explode(',', $raw)));
        return array_values(array_unique($ids));
    }

    /**
     * Exporta a Excel (XLSX) el Comprobante de Egreso: cabecera, documentos
     * aplicados y formas de pago. Mismos datos que el PDF
     * (ComprobanteCajaPdfService::generarEgreso).
     */
    public function exportarExcelAjax(): void
    {
        $this->requireLeer();

        $id        = (int) ($_GET['id'] ?? 0);
        $idEmpresa = (int) $_SESSION['id_empresa'];
        if (!$id) { http_response_code(400); echo 'ID requerido'; exit; }

        try {
            $egreso = $this->service->getPorId($id, $idEmpresa);
            if (!$egreso || (int) ($egreso['id_empresa'] ?? 0) !== $idEmpresa) {
                http_response_code(404); echo 'Egreso no encontrado'; exit;
            }

            $detalles = $egreso['detalles'] ?? [];
            $pagos    = $egreso['pagos'] ?? [];
            $empresa  = $this->cargarEmpresaParaPdf($idEmpresa);
            $numero   = (string) ($egreso['numero_egreso'] ?? $id);
            $sujeto   = trim((string) ($egreso['sujeto_nombre'] ?? ''));

            require_once MVC_ROOT . '/vendor/autoload.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Egreso');

            $sheet->setCellValue('A1', strtoupper((string) ($empresa['nombre'] ?? '')));
            $sheet->mergeCells('A1:G1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

            $sheet->setCellValue('A2', 'COMPROBANTE DE EGRESO N.° ' . $numero);
            $sheet->mergeCells('A2:G2');
            $sheet->getStyle('A2')->getFont()->setBold(true);

            $fecha = !empty($egreso['fecha_emision']) ? date('d-m-Y', strtotime((string) $egreso['fecha_emision'])) : '';
            $sheet->setCellValue('A3', 'Fecha: ' . $fecha);
            $sheet->setCellValue('D3', 'Pagado a: ' . ($sujeto !== '' ? $sujeto : '—'));
            $sheet->setCellValue('A4', 'Identificación: ' . (string) ($egreso['sujeto_ruc'] ?? ''));
            $sheet->setCellValue('D4', 'Estado: ' . ucfirst((string) ($egreso['estado'] ?? '')));
            $sheet->setCellValue('A5', 'Concepto: ' . (trim((string) ($egreso['observaciones'] ?? '')) ?: '—'));
            $sheet->mergeCells('A5:G5');

            $headerRow = 7;
            $headers = ['Tipo', 'N.° Documento', 'Descripción', 'Monto Doc.', 'Saldo Anterior', 'Pagado', 'Saldo Actual'];
            $col = 'A';
            foreach ($headers as $h) {
                $sheet->setCellValue($col . $headerRow, $h);
                $col++;
            }
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '3C465A']],
            ];
            $sheet->getStyle('A' . $headerRow . ':G' . $headerRow)->applyFromArray($headerStyle);

            $row = $headerRow + 1;
            foreach ($detalles as $d) {
                $sheet->setCellValueExplicit('A' . $row, (string) ($d['tipo_documento'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B' . $row, (string) ($d['numero_documento'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C' . $row, (string) ($d['descripcion'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('D' . $row, (float) ($d['monto_documento'] ?? 0));
                $sheet->setCellValue('E' . $row, (float) ($d['saldo_anterior'] ?? 0));
                $sheet->setCellValue('F' . $row, (float) ($d['monto_pagado'] ?? 0));
                $sheet->setCellValue('G' . $row, (float) ($d['saldo_actual'] ?? 0));
                $row++;
            }
            if (empty($detalles)) {
                $sheet->setCellValue('A' . $row, 'Sin documentos.');
                $sheet->mergeCells('A' . $row . ':G' . $row);
                $row++;
            }

            if ($row > $headerRow + 1) {
                $sheet->getStyle('D' . ($headerRow + 1) . ':G' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
            }

            $row += 1;
            $sheet->setCellValue('F' . $row, 'TOTAL');
            $sheet->getStyle('F' . $row)->getFont()->setBold(true);
            $sheet->setCellValue('G' . $row, (float) ($egreso['monto_total'] ?? 0));
            $sheet->getStyle('G' . $row)->getFont()->setBold(true);
            $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

            if (!empty($pagos)) {
                $row += 2;
                $sheet->setCellValue('A' . $row, 'Formas de Pago');
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;
                $sheet->setCellValue('A' . $row, 'Forma');
                $sheet->setCellValue('B' . $row, 'Referencia');
                $sheet->setCellValue('C' . $row, 'Valor');
                $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
                $row++;
                foreach ($pagos as $p) {
                    $ref    = trim((string) ($p['referencia'] ?? ''));
                    $tipoOp = trim((string) ($p['tipo_operacion_bancaria'] ?? ''));
                    if ($tipoOp !== '') {
                        $extra = (strtoupper($tipoOp) === 'CHEQUE' && !empty($p['numero_cheque'])) ? ('CHEQUE #' . $p['numero_cheque']) : $tipoOp;
                        $ref   = $ref !== '' ? ($extra . ' — ' . $ref) : $extra;
                    }
                    $sheet->setCellValueExplicit('A' . $row, (string) ($p['forma_pago_nombre'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('B' . $row, $ref, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('C' . $row, (float) ($p['monto'] ?? 0));
                    $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                    $row++;
                }
            }

            foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $nombre = 'Egreso_' . $numero . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $nombre . '"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            http_response_code(500); echo 'Error al generar Excel: ' . $e->getMessage();
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

    /** Envía por correo el PDF del comprobante de egreso. */
    public function enviarCorreoAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $id        = (int) ($_POST['id'] ?? 0);
            $idEmpresa = (int) $_SESSION['id_empresa'];
            if (!$id) { echo json_encode(['ok' => false, 'mensaje' => 'ID requerido.']); exit; }

            $egreso = $this->service->getPorId($id, $idEmpresa);
            if (!$egreso) { echo json_encode(['ok' => false, 'mensaje' => 'Egreso no encontrado.']); exit; }

            // Destino: correo del POST o, si viene vacío, el del proveedor/empleado.
            $correo = trim($_POST['correo'] ?? '');
            if ($correo === '') {
                $db = \App\core\Database::getConnection();
                if (!empty($egreso['id_proveedor'])) {
                    $st = $db->prepare("SELECT email FROM proveedores WHERE id = ? AND id_empresa = ?");
                    $st->execute([(int) $egreso['id_proveedor'], $idEmpresa]);
                    $correo = trim((string) $st->fetchColumn());
                } elseif (!empty($egreso['id_empleado'])) {
                    $st = $db->prepare("SELECT email FROM empleados WHERE id = ? AND id_empresa = ?");
                    $st->execute([(int) $egreso['id_empleado'], $idEmpresa]);
                    $correo = trim((string) $st->fetchColumn());
                }
            }
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['ok' => false, 'mensaje' => 'Ingrese un correo válido.']);
                exit;
            }

            $empresa    = new Empresa();
            $empresaRow = $empresa->getPorId($idEmpresa) ?? [];
            $num        = (string) ($egreso['numero_egreso'] ?? $id);
            $nombreDest = (string) ($egreso['sujeto_nombre'] ?? 'Beneficiario');
            $asunto     = 'Comprobante de Egreso ' . $num;
            $cuerpo     = $this->construirCuerpoCorreoEgreso($egreso, $empresaRow, $nombreDest);

            // Solo el detalle en el cuerpo (HTML), SIN adjuntar PDF.
            $ok = (new \App\Services\EnvioDocumentosSRIService())->enviarAvisoSimple(
                $idEmpresa, $correo, $nombreDest, $asunto, $cuerpo, (string) ($empresaRow['nombre'] ?? '')
            );

            echo json_encode($ok
                ? ['ok' => true, 'mensaje' => 'Comprobante enviado a ' . $correo]
                : ['ok' => false, 'mensaje' => 'No se pudo enviar. Verifica la configuración de correo de la empresa.']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => 'Error al enviar correo: ' . $e->getMessage()]);
        }
        exit;
    }

    /** Cuerpo HTML del correo con el detalle del egreso (sin PDF). */
    private function construirCuerpoCorreoEgreso(array $eg, array $empresa, string $nombreDest): string
    {
        $num      = htmlspecialchars((string) ($eg['numero_egreso'] ?? ''));
        $fecha    = !empty($eg['fecha_emision']) ? date('d/m/Y', strtotime((string) $eg['fecha_emision'])) : '';
        $ident    = htmlspecialchars((string) ($eg['sujeto_ruc'] ?? ''));
        $concepto = htmlspecialchars(trim((string) ($eg['observaciones'] ?? ''))) ?: '—';
        $total    = (float) ($eg['monto_total'] ?? 0);
        $letras   = $this->montoEnLetras($total);
        $sujeto   = htmlspecialchars($nombreDest);

        $filasDoc = '';
        foreach (($eg['detalles'] ?? []) as $d) {
            $tipo  = htmlspecialchars((string) ($d['tipo_documento'] ?? ''));
            $ndoc  = htmlspecialchars((string) ($d['numero_documento'] ?? '')) ?: '—';
            $desc  = htmlspecialchars((string) ($d['descripcion'] ?? '')) ?: '—';
            $monto = number_format((float) ($d['monto_pagado'] ?? 0), 2);
            $filasDoc .= "<tr><td style='border:1px solid #ddd;padding:5px'>$tipo</td><td style='border:1px solid #ddd;padding:5px'>$ndoc</td><td style='border:1px solid #ddd;padding:5px'>$desc</td><td style='border:1px solid #ddd;padding:5px;text-align:right'>\$$monto</td></tr>";
        }

        $filasPago = '';
        foreach (($eg['pagos'] ?? []) as $p) {
            $forma = htmlspecialchars((string) ($p['forma_pago_nombre'] ?? ''));
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
            <p>Le compartimos el detalle de su comprobante de egreso:</p>
            <table style='border-collapse:collapse;margin-bottom:12px'>
                <tr><td style='padding:3px 8px'><strong>Comprobante N.°</strong></td><td style='padding:3px 8px'>$num</td></tr>
                <tr><td style='padding:3px 8px'><strong>Fecha</strong></td><td style='padding:3px 8px'>$fecha</td></tr>
                <tr><td style='padding:3px 8px'><strong>Pagado a</strong></td><td style='padding:3px 8px'>$sujeto</td></tr>
                " . ($ident !== '' ? "<tr><td style='padding:3px 8px'><strong>Identificación</strong></td><td style='padding:3px 8px'>$ident</td></tr>" : '') . "
                <tr><td style='padding:3px 8px'><strong>Concepto</strong></td><td style='padding:3px 8px'>$concepto</td></tr>
            </table>
            <p style='margin:6px 0'><strong>Documentos pagados</strong></p>
            <table style='border-collapse:collapse;width:100%;margin-bottom:12px'>
                <tr><th $th>Tipo</th><th $th>N.° Documento</th><th $th>Descripción</th><th $th style='text-align:right'>Monto</th></tr>
                $filasDoc
            </table>
            <p style='margin:6px 0'><strong>Formas de pago</strong></p>
            <table style='border-collapse:collapse;width:100%;margin-bottom:12px'>
                <tr><th $th>Forma</th><th $th>Referencia</th><th $th style='text-align:right'>Valor</th></tr>
                $filasPago
            </table>
            <p style='font-size:16px'><strong>TOTAL: \$" . number_format($total, 2) . "</strong><br>
               <span style='font-size:12px;color:#666'>Son: $letras dólares</span></p>
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
