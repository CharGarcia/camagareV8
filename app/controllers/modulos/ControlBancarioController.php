<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ControlBancarioRepository;
use App\Rules\modulos\ControlBancarioRules;
use App\Services\modulos\ControlBancarioService;
use App\Services\LogSistemaService;
use App\Services\ReportService;
use App\Helpers\PreferenciasHelper;
use App\models\Empresa;

class ControlBancarioController extends BaseModuloController
{
    private ControlBancarioService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/control-bancario';
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = new ControlBancarioService(
            new ControlBancarioRepository(),
            new ControlBancarioRules(),
            new LogSistemaService(),
            new ReportService()
        );
    }

    private function getFiltrosDesdeRequest(): array
    {
        return [
            'fecha_inicio' => trim($_GET['fecha_inicio'] ?? $_POST['fecha_inicio'] ?? date('Y-01-01')),
            'fecha_fin' => trim($_GET['fecha_fin'] ?? $_POST['fecha_fin'] ?? date('Y-12-31')),
            'buscar' => trim($_GET['b'] ?? $_POST['b'] ?? $_GET['buscar'] ?? $_POST['buscar'] ?? ''),
            'flujo' => strtoupper(trim($_GET['flujo'] ?? $_POST['flujo'] ?? 'TODOS')),
            'tipo' => strtoupper(trim($_GET['tipo'] ?? $_POST['tipo'] ?? '')),
        ];
    }

    /**
     * Resuelve el grupo de cuentas (mismo banco + número de cuenta) del RUC accesible al usuario,
     * cuando se pidió consolidar. Devuelve [] si no se pidió, si la cuenta no tiene grupo, o si el
     * usuario no tiene acceso a ningún establecimiento hermano — en ese caso el llamador debe
     * seguir el camino de una sola cuenta (comportamiento idéntico al de siempre).
     */
    private function resolverPares(int $idEmpresa, int $idFormaPago, int $idUsuario, bool $consolidado): array
    {
        if (!$consolidado || $idFormaPago <= 0) {
            return [];
        }
        $pares = $this->service->resolverGrupoCuenta($idEmpresa, $idFormaPago, $idUsuario);
        return count($pares) > 1 ? $pares : [];
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $formas = $this->service->getFormasBancarias($idEmpresa);
        $idFormaPago = (int) ($_GET['forma'] ?? ($formas[0]['id'] ?? 0));
        $consolidado = !empty($_GET['consolidado']);

        $gruposDeCuentas = $this->service->getGruposDeCuentas($idEmpresa, $idUsuario);

        $aniosDisponibles = $this->service->getAniosDisponibles($idEmpresa);
        if (empty($aniosDisponibles)) {
            $aniosDisponibles = [(int) date('Y')];
        }

        $prefsVista = PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $fechaInicio = date('Y-01-01');
        $fechaFin = date('Y-12-31');

        $resumen = ['saldo_inicial' => 0.0, 'creditos' => 0.0, 'debitos' => 0.0, 'saldo_final' => 0.0];
        if ($idFormaPago > 0) {
            try {
                $pares = $this->resolverPares($idEmpresa, $idFormaPago, $idUsuario, $consolidado);
                $resumen = $pares
                    ? $this->service->getResumenPeriodoGrupo($pares, $fechaInicio, $fechaFin)
                    : $this->service->getResumenPeriodo($idEmpresa, $idFormaPago, $fechaInicio, $fechaFin);
            } catch (\Throwable $e) {
                $idFormaPago = 0;
            }
        }

        $this->viewWithLayout('layouts.main', 'modulos.control_bancario.index', [
            'titulo' => 'Control Bancario',
            'perm' => $this->getPermisos(),
            'rutaModulo' => $this->getRutaModulo(),
            'formas' => $formas,
            'idFormaPago' => $idFormaPago,
            'gruposDeCuentas' => $gruposDeCuentas,
            'consolidado' => $consolidado,
            'aniosDisponibles' => $aniosDisponibles,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'resumen' => $resumen,
            'vistaConfig' => $prefsVista,
            'fullWidth' => true,
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idFormaPago = (int) ($_GET['forma'] ?? $_POST['forma'] ?? 0);
        if ($idFormaPago <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Debe seleccionar una cuenta bancaria.']);
            exit;
        }
        $consolidado = !empty($_GET['consolidado'] ?? $_POST['consolidado'] ?? '');

        $prefsVista = PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $filtros = $this->getFiltrosDesdeRequest();
        $page = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'fecha_asiento');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'ASC'));
        $perPage = 30;

        try {
            $pares = $this->resolverPares($idEmpresa, $idFormaPago, $idUsuario, $consolidado);
            $result = $pares
                ? $this->service->getMovimientosGrupo($pares, $filtros, $page, $perPage, $ordenCol, $ordenDir)
                : $this->service->getMovimientos($idEmpresa, $idFormaPago, $filtros, $page, $perPage, $ordenCol, $ordenDir);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit;
        }

        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to = $total > 0 ? min($page * $perPage, $total) : 0;

        $tipoLabels = [
            'DEPOSITO' => 'Depósito', 'CHEQUE' => 'Cheque', 'TRANSFERENCIA' => 'Transferencia',
            'NOTA_DEBITO' => 'Nota Débito', 'NOTA_CREDITO' => 'Nota Crédito', 'OTRO' => 'Otro',
        ];

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="13" class="text-center py-5 text-muted"><i class="bi bi-bank fs-3 d-block mb-2"></i>No se encontraron movimientos.</td></tr>';
        } else {
            // Fila completa clickeable (mismo patrón que Proveedores): data-row + onclick abre el modal de clasificación.
            foreach ($rows as $r) {
                $rowData = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8');
                $fecha = !empty($r['fecha_asiento']) ? date('d-m-Y', strtotime($r['fecha_asiento'])) : '—';
                $fechaBanco = !empty($r['fecha_banco']) ? date('d-m-Y', strtotime($r['fecha_banco'])) : '—';
                $tipoLabel = $tipoLabels[$r['tipo_transaccion']] ?? $r['tipo_transaccion'];
                $glosa = $r['referencia_detalle'] ?: $r['concepto'] ?: '';
                $esCheque = ($r['tipo_transaccion'] === 'CHEQUE');
                $fechaCheque = ($esCheque && !empty($r['fecha_cheque'])) ? date('d-m-Y', strtotime($r['fecha_cheque'])) : '';
                $beneficiarioCheque = $esCheque ? (string) ($r['beneficiario_cheque'] ?? '') : '';

                $badgeDireccion = '';
                if ($r['tipo_transaccion'] === 'CHEQUE' && !empty($r['cheque_direccion'])) {
                    $cls = $r['cheque_direccion'] === 'EMITIDO' ? 'bg-danger bg-opacity-10 text-danger border-danger' : 'bg-success bg-opacity-10 text-success border-success';
                    $badgeDireccion = ' <span class="badge ' . $cls . ' border border-opacity-25">' . ucfirst(strtolower($r['cheque_direccion'])) . '</span>';
                }
                if (!empty($r['es_posfechado'])) {
                    $badgeDireccion .= ' <span class="badge bg-warning bg-opacity-25 text-warning-emphasis border border-warning border-opacity-50">Posfechado</span>';
                }

                // Solo presente en la vista consolidada (getMovimientosGrupo): de qué establecimiento
                // del grupo RUC viene este movimiento.
                $badgeEst = '';
                if (!empty($r['establecimiento'])) {
                    $tituloEst = htmlspecialchars((string) ($r['empresa_nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $badgeEst = '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 me-1" title="' . $tituloEst . '">' . htmlspecialchars((string) $r['establecimiento']) . '</span>';
                }

                echo '<tr class="cb-row" role="button" tabindex="0" data-row="' . $rowData . '" onclick="CB_abrirModalClasificacion(this)">
                        <td class="ps-3" data-col="fecha_asiento">' . $fecha . '</td>
                        <td data-col="fecha_banco">' . $fechaBanco . '</td>
                        <td data-col="comprobante"><a href="#" onclick="event.stopPropagation(); event.preventDefault(); ASIENTO_abrirModal(' . (int) $r['id_asiento'] . ');" class="text-decoration-none fw-bold" title="Ver asiento contable">' . htmlspecialchars((string) ($r['numero_comprobante'] ?: 'S/N')) . '</a></td>
                        <td data-col="tipo"><span class="badge bg-light text-dark border">' . htmlspecialchars($tipoLabel) . '</span></td>
                        <td data-col="cheque">' . ($esCheque ? htmlspecialchars((string) ($r['numero_cheque'] ?? '')) : '') . $badgeDireccion . '</td>
                        <td data-col="fecha_cheque">' . $fechaCheque . '</td>
                        <td data-col="beneficiario_cheque" class="text-truncate" style="max-width:160px">' . htmlspecialchars($beneficiarioCheque) . '</td>
                        <td data-col="documento" class="text-truncate" style="max-width:140px">' . htmlspecialchars((string) ($r['documento_referencia'] ?? '')) . '</td>
                        <td data-col="tercero" class="text-truncate" style="max-width:160px">' . $badgeEst . htmlspecialchars((string) ($r['nombre_entidad'] ?? '')) . '</td>
                        <td data-col="glosa" class="text-truncate text-muted" style="max-width:220px">' . htmlspecialchars((string) $glosa) . '</td>
                        <td class="text-end" data-col="debe">' . ((float) $r['debe'] > 0 ? number_format((float) $r['debe'], 2) : '') . '</td>
                        <td class="text-end" data-col="haber">' . ((float) $r['haber'] > 0 ? number_format((float) $r['haber'], 2) : '') . '</td>
                        <td class="text-end fw-bold pe-3" data-col="saldo">' . number_format((float) $r['saldo_acumulado'], 2) . '</td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<button type="button" class="btn btn-outline-secondary btn-sm" ' . $prevDisabled . ' onclick="window.CB_cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
              <button type="button" class="btn btn-outline-secondary btn-sm" ' . $nextDisabled . ' onclick="window.CB_cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>';
        $paginationHtml = ob_get_clean();

        $urlBase = BASE_URL . '/' . $this->getRutaModulo();
        echo json_encode([
            'ok' => true,
            'rows' => $rowsHtml,
            'pagination' => $paginationHtml,
            'info' => "$from-$to/$total",
            'total' => $total,
            'pdf_url' => $urlBase . '/exportarPdfAjax?forma=' . $idFormaPago . '&consolidado=' . ($consolidado ? 1 : 0) . '&fecha_inicio=' . urlencode($filtros['fecha_inicio']) . '&fecha_fin=' . urlencode($filtros['fecha_fin']) . '&b=' . urlencode($filtros['buscar']),
            'excel_url' => $urlBase . '/exportarExcelAjax?forma=' . $idFormaPago . '&consolidado=' . ($consolidado ? 1 : 0) . '&fecha_inicio=' . urlencode($filtros['fecha_inicio']) . '&fecha_fin=' . urlencode($filtros['fecha_fin']) . '&b=' . urlencode($filtros['buscar']),
        ]);
        exit;
    }

    public function getSaldosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idFormaPago = (int) ($_GET['forma'] ?? 0);
        $consolidado = !empty($_GET['consolidado'] ?? '');
        $fechaInicio = trim($_GET['fecha_inicio'] ?? date('Y-01-01'));
        $fechaFin = trim($_GET['fecha_fin'] ?? date('Y-12-31'));
        try {
            $pares = $this->resolverPares($idEmpresa, $idFormaPago, $idUsuario, $consolidado);
            $resumen = $pares
                ? $this->service->getResumenPeriodoGrupo($pares, $fechaInicio, $fechaFin)
                : $this->service->getResumenPeriodo($idEmpresa, $idFormaPago, $fechaInicio, $fechaFin);
            echo json_encode(['ok' => true, 'data' => $resumen]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function chequesPosfechadosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idFormaPago = !empty($_GET['forma']) ? (int) $_GET['forma'] : null;
        $consolidado = !empty($_GET['consolidado'] ?? '');
        $direccion = strtoupper(trim($_GET['direccion'] ?? ''));

        $pares = $idFormaPago ? $this->resolverPares($idEmpresa, $idFormaPago, $idUsuario, $consolidado) : [];
        $rows = $pares
            ? $this->service->getChequesPosfechadosGrupo($pares, $direccion)
            : $this->service->getChequesPosfechados($idEmpresa, $idFormaPago, $direccion);
        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }

    /**
     * Resuelve sobre qué empresa opera la clasificación: la del payload (fila de un
     * establecimiento hermano, en vista consolidada) si viene y el usuario tiene acceso a ella
     * dentro del grupo RUC; si no, la empresa activa de sesión (comportamiento de siempre).
     */
    private function resolverEmpresaObjetivo(int $idEmpresaActiva, int $idUsuario, array $data): int
    {
        $idEmpresaObjetivo = !empty($data['id_empresa']) ? (int) $data['id_empresa'] : $idEmpresaActiva;
        if ($idEmpresaObjetivo !== $idEmpresaActiva
            && !$this->service->empresaAccesibleDelGrupo($idEmpresaActiva, $idEmpresaObjetivo, $idUsuario)) {
            throw new \Exception('No tiene acceso a esa empresa del grupo.');
        }
        return $idEmpresaObjetivo;
    }

    public function guardarClasificacionAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresaActiva = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;

        try {
            $idEmpresa = $this->resolverEmpresaObjetivo($idEmpresaActiva, $idUsuario, $data);
            $resultado = $this->service->guardarClasificacion($idEmpresa, $idUsuario, $data);
            echo json_encode(['ok' => true, 'data' => $resultado]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function quitarClasificacionAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresaActiva = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
        $idAsientoDetalle = (int) ($data['id_asiento_detalle'] ?? 0);

        try {
            $idEmpresa = $this->resolverEmpresaObjetivo($idEmpresaActiva, $idUsuario, $data);
            $this->service->quitarClasificacion($idEmpresa, $idUsuario, $idAsientoDetalle);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Devuelve la conciliación vigente que cubre por completo el rango de fechas mostrado (para el badge). */
    public function conciliacionActualAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idFormaPago = (int) ($_GET['forma'] ?? 0);
        $consolidado = !empty($_GET['consolidado'] ?? '');
        $fechaInicio = trim($_GET['fecha_inicio'] ?? '');
        $fechaFin = trim($_GET['fecha_fin'] ?? '');

        $pares = $this->resolverPares($idEmpresa, $idFormaPago, $idUsuario, $consolidado);
        $conciliacion = $pares
            ? $this->service->getConciliacionDelRangoGrupo($pares, $fechaInicio, $fechaFin)
            : $this->service->getConciliacionDelRango($idFormaPago, $fechaInicio, $fechaFin);
        echo json_encode(['ok' => true, 'data' => $conciliacion]);
        exit;
    }

    public function listarConciliacionesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idFormaPago = (int) ($_GET['forma'] ?? 0);
        $consolidado = !empty($_GET['consolidado'] ?? '');

        $pares = $this->resolverPares($idEmpresa, $idFormaPago, $idUsuario, $consolidado);
        $rows = $pares
            ? $this->service->getConciliacionesGrupo($pares)
            : $this->service->getConciliaciones($idEmpresa, $idFormaPago);
        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }

    public function conciliarPeriodoAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
        $consolidado = !empty($data['consolidado'] ?? false);

        try {
            $idFormaPago = (int) ($data['id_forma_pago'] ?? 0);
            $pares = $this->resolverPares($idEmpresa, $idFormaPago, $idUsuario, $consolidado);
            $resultado = $pares
                ? $this->service->conciliarPeriodoGrupo($pares, $idUsuario, $data)
                : $this->service->conciliarPeriodo($idEmpresa, $idUsuario, $data);
            echo json_encode(['ok' => true, 'data' => $resultado]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function reabrirConciliacionAjax(): void
    {
        $this->requireEliminar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $data = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;
        $idConciliacion = (int) ($data['id'] ?? 0);

        try {
            $this->service->reabrirConciliacion($idEmpresa, $idUsuario, $idConciliacion);
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function nombreEmpresaYCuenta(int $idEmpresa, int $idFormaPago): array
    {
        $empresaModel = new Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa);
        $empresaNombre = $empresa['nombre_comercial'] ?: $empresa['nombre'];

        $cuentaNombre = '';
        foreach ($this->service->getFormasBancarias($idEmpresa) as $f) {
            if ((int) $f['id'] === $idFormaPago) {
                $cuentaNombre = $f['nombre'];
                break;
            }
        }

        return [$empresaNombre, $cuentaNombre];
    }

    /** Datos de la empresa con el logo del establecimiento actual (mismo patrón que EgresosController/IngresosController). */
    private function cargarEmpresaParaPdf(int $idEmpresa): array
    {
        $empresaModel = new Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos[0]['logo_ruta'])) {
            $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
        }
        return $empresa;
    }

    public function exportarPdfAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idFormaPago = (int) ($_GET['forma'] ?? 0);
        $consolidado = !empty($_GET['consolidado'] ?? '');
        $filtros = $this->getFiltrosDesdeRequest();

        $pares = $this->resolverPares($idEmpresa, $idFormaPago, $idUsuario, $consolidado);
        if ($pares) {
            $result = $this->service->getMovimientosGrupo($pares, $filtros, 1, 100000, 'fecha_asiento', 'ASC');
            $empresaNombre = 'Consolidado (' . count($pares) . ' establecimientos)';
            $cuentaNombre = $pares[0]['nombre'] ?? '';
        } else {
            $result = $this->service->getMovimientos($idEmpresa, $idFormaPago, $filtros, 1, 100000, 'fecha_asiento', 'ASC');
            [$empresaNombre, $cuentaNombre] = $this->nombreEmpresaYCuenta($idEmpresa, $idFormaPago);
        }
        $this->service->exportarPdf($result['rows'], $empresaNombre, $cuentaNombre);
    }

    public function exportarExcelAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idFormaPago = (int) ($_GET['forma'] ?? 0);
        $consolidado = !empty($_GET['consolidado'] ?? '');
        $filtros = $this->getFiltrosDesdeRequest();

        $pares = $this->resolverPares($idEmpresa, $idFormaPago, $idUsuario, $consolidado);
        if ($pares) {
            $result = $this->service->getMovimientosGrupo($pares, $filtros, 1, 100000, 'fecha_asiento', 'ASC');
            $empresaNombre = 'Consolidado (' . count($pares) . ' establecimientos)';
            $cuentaNombre = $pares[0]['nombre'] ?? '';
        } else {
            $result = $this->service->getMovimientos($idEmpresa, $idFormaPago, $filtros, 1, 100000, 'fecha_asiento', 'ASC');
            [$empresaNombre, $cuentaNombre] = $this->nombreEmpresaYCuenta($idEmpresa, $idFormaPago);
        }
        $this->service->exportarExcel($result['rows'], $empresaNombre, $cuentaNombre);
    }

    public function exportarConciliacionPdfAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idFormaPago = (int) ($_GET['forma'] ?? 0);
        $consolidado = !empty($_GET['consolidado'] ?? '');
        $fechaInicio = trim($_GET['fecha_inicio'] ?? date('Y-01-01'));
        $fechaFin = trim($_GET['fecha_fin'] ?? date('Y-12-31'));

        $pares = $this->resolverPares($idEmpresa, $idFormaPago, $idUsuario, $consolidado);
        $reporte = $pares
            ? $this->service->getReporteConciliacionGrupo($pares, $fechaInicio, $fechaFin)
            : $this->service->getReporteConciliacion($idEmpresa, $idFormaPago, $fechaInicio, $fechaFin);
        $empresa = $this->cargarEmpresaParaPdf($idEmpresa);
        if ($pares) {
            $empresa['nombre_comercial'] = 'Consolidado (' . count($pares) . ' establecimientos)';
        }

        (new \App\Services\modulos\ControlBancarioConciliacionPdfService())->generar($reporte, $empresa);
    }

    public function exportarConciliacionExcelAjax(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];
        $idFormaPago = (int) ($_GET['forma'] ?? 0);
        $consolidado = !empty($_GET['consolidado'] ?? '');
        $fechaInicio = trim($_GET['fecha_inicio'] ?? date('Y-01-01'));
        $fechaFin = trim($_GET['fecha_fin'] ?? date('Y-12-31'));

        $pares = $this->resolverPares($idEmpresa, $idFormaPago, $idUsuario, $consolidado);
        if ($pares) {
            $reporte = $this->service->getReporteConciliacionGrupo($pares, $fechaInicio, $fechaFin);
            $empresaNombre = 'Consolidado (' . count($pares) . ' establecimientos)';
        } else {
            $reporte = $this->service->getReporteConciliacion($idEmpresa, $idFormaPago, $fechaInicio, $fechaFin);
            $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];
            $empresaNombre = $empresa['nombre_comercial'] ?: ($empresa['nombre'] ?? '');
        }

        $this->service->exportarConciliacionExcel($reporte, $empresaNombre);
    }
}
