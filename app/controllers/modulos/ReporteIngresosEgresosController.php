<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\ReporteIngresosEgresosRepository;
use App\repositories\modulos\VendedorRepository;

class ReporteIngresosEgresosController extends BaseModuloController
{
    /** Formatos de celda de las hojas del Excel. */
    private const XL_DINERO     = '#,##0.00';
    private const XL_FECHA      = 'dd-mm-yyyy';
    private const XL_FECHA_HORA = 'dd-mm-yyyy hh:mm:ss';

    /** Formas de ver distintas de Documento (detalle). */
    private const VISTAS_RESUMEN = ['TERCERO', 'FORMA', 'FECHA', 'MES', 'ANTICIPOS'];

    private const OPERACIONES =['TRANSFERENCIA' => 'Transferencia', 'DEPOSITO' => 'Depósito', 'DEBITO' => 'Débito', 'CHEQUE' => 'Cheque'];

    private ReporteIngresosEgresosRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/reporte_ingresos_egresos';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReporteIngresosEgresosRepository();
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $this->viewWithLayout('layouts.main', 'modulos/reporte_ingresos_egresos/index', [
            'titulo'      => 'Reporte de Ingresos y Egresos',
            'perm'        => $this->getPermisos(),
            'rutaModulo'  => $this->getRutaModulo(),
            'formas'      => $this->repository->getFormasPago($idEmpresa),
            'conceptos'   => $this->repository->getConceptos($idEmpresa),
            'proyectos'   => $this->repository->getProyectos($idEmpresa),
            'vendedores'  => (new VendedorRepository())->getVendedoresActivos($idEmpresa),
            'anios'       => $this->repository->getAnios($idEmpresa),
            'fullWidth'   => true,
            'base'        => BASE_URL,
        ]);
    }

    private function getFiltros(): array
    {
        return [
            'tipo_flujo'        => strtoupper(trim($_REQUEST['tipo_flujo'] ?? 'AMBOS')),
            'ver_por'           => strtoupper(trim($_REQUEST['ver_por'] ?? 'COMPROBANTE')), // DETALLE | COMPROBANTE | TERCERO | FORMA | FECHA | MES | ANTICIPOS
            'fecha_desde'       => trim($_REQUEST['fecha_desde'] ?? ''),
            'fecha_hasta'       => trim($_REQUEST['fecha_hasta'] ?? ''),
            'tercero_tipo'      => strtoupper(trim($_REQUEST['tercero_tipo'] ?? '')),
            'tercero_id'        => (int) ($_REQUEST['tercero_id'] ?? 0),
            'id_vendedor'       => (int) ($_REQUEST['id_vendedor'] ?? 0),
            'id_forma'          => (int) ($_REQUEST['id_forma'] ?? 0),
            'operacion_bancaria'=> strtoupper(trim($_REQUEST['operacion_bancaria'] ?? '')),
            'id_concepto'       => (int) ($_REQUEST['id_concepto'] ?? 0),
            'id_proyecto'       => (int) ($_REQUEST['id_proyecto'] ?? 0),
            'estado'            => strtoupper(trim($_REQUEST['estado'] ?? '')) === 'ANULADO' ? 'ANULADO' : 'VIGENTES',
            'tipo_documento'    => strtoupper(trim($_REQUEST['tipo_documento'] ?? '')),
            'monto_min'         => trim($_REQUEST['monto_min'] ?? ''),
            'monto_max'         => trim($_REQUEST['monto_max'] ?? ''),
            'buscar'            => trim($_REQUEST['buscar'] ?? ''),
            // TODOS | MONETARIOS (sin lo cobrado/pagado con formas que no mueven dinero:
            // Anticipo, Otros) | NO_MONETARIOS (solo eso)
            'movimientos'       => in_array($mov = strtoupper(trim($_REQUEST['movimientos'] ?? '')), ['MONETARIOS', 'NO_MONETARIOS'], true) ? $mov : 'TODOS',
        ];
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $f = $this->getFiltros();

            $stats = $this->repository->getEstadisticas($idEmpresa, $f);

            switch ($f['ver_por']) {
                case 'COMPROBANTE':
                    $rows = $this->repository->getReporteAgrupadoComprobante($idEmpresa, $f);
                    $rowsHtml = $this->renderComprobante($rows);
                    break;
                case 'TERCERO':
                    $rows = $this->repository->getReporteAgrupadoTercero($idEmpresa, $f);
                    $rowsHtml = $this->renderAgrupado($rows);
                    break;
                case 'FORMA':
                    $rows = $this->repository->getReporteAgrupadoForma($idEmpresa, $f);
                    $rowsHtml = $this->renderForma($rows);
                    break;
                case 'FECHA':
                    $rows = $this->repository->getReporteAgrupadoFecha($idEmpresa, $f);
                    $rowsHtml = $this->renderPeriodo($rows, 'FECHA');
                    break;
                case 'MES':
                    $rows = $this->repository->getReporteAgrupadoMes($idEmpresa, $f);
                    $rowsHtml = $this->renderPeriodo($rows, 'MES');
                    break;
                case 'ANTICIPOS':
                    if ($this->anticiposDeUnTercero($f)) {
                        $mov  = $this->repository->getAnticiposMovimientos($idEmpresa, $f);
                        $rows = $mov['movimientos'];
                        $rowsHtml = $this->renderAnticiposMovimientos($mov);
                    } else {
                        $rows = $this->repository->getAnticiposResumen($idEmpresa, $f);
                        $rowsHtml = $this->renderAnticipos($rows);
                    }
                    break;
                default:
                    $rows = $this->repository->getReporteDetallado($idEmpresa, $f);
                    $rowsHtml = $this->renderDetalle($rows);
            }

            $urlBase = BASE_URL . '/' . $this->getRutaModulo();
            $qs      = http_build_query($f);
            echo json_encode([
                'ok'        => true,
                'rows'      => $rowsHtml,
                'total'     => count($rows),
                'ver_por'   => $f['ver_por'],
                'movimientos_tercero' => $f['ver_por'] === 'ANTICIPOS' && $this->anticiposDeUnTercero($f),
                'stats'     => $stats,
                'excel_url' => $urlBase . '/exportExcel?' . $qs,
                'pdf_url'   => $urlBase . '/exportPdf?' . $qs,
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Detalle de un ingreso o egreso para el panel lateral (clic en una fila): cabecera,
     * líneas y formas de cobro/pago. Lo valida el permiso de este reporte, no el de los
     * módulos de Ingresos/Egresos (quien ve el reporte puede no tener acceso a ellos).
     */
    public function getComprobanteAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $flujo = strtoupper(trim($_GET['flujo'] ?? '')) === 'EGRESO' ? 'EGRESO' : 'INGRESO';
        $doc = $this->repository->getComprobante((int) $_SESSION['id_empresa'], $flujo, (int) ($_GET['id'] ?? 0));
        if ($doc === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'mensaje' => 'No se encontró el ' . ($flujo === 'INGRESO' ? 'ingreso' : 'egreso') . '.']);
            exit;
        }
        echo json_encode(['ok' => true] + $doc);
        exit;
    }

    public function buscarTercerosAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $tipo = strtoupper(trim($_GET['tipo'] ?? 'CLIENTE'));
        $q    = trim($_GET['q'] ?? '');
        echo json_encode(['ok' => true, 'data' => $this->repository->buscarTerceros($idEmpresa, $tipo, $q)]);
        exit;
    }

    /**
     * <tr> de una fila que abre, al hacer clic, el detalle de su ingreso o egreso en el
     * panel lateral (partials/offcanvas_doc_preview.php, modo comprobante).
     */
    private static function trComprobante(string $flujo, int $id, string $numero, string $tercero, string $cls = ''): string
    {
        if ($id <= 0) {
            return '<tr class="' . $cls . '">';
        }
        return '<tr class="rie-fila-doc ' . $cls . '" style="cursor:pointer;" title="Ver el detalle del ' . ($flujo === 'INGRESO' ? 'ingreso' : 'egreso') . '"'
             . ' data-flujo="' . ($flujo === 'INGRESO' ? 'INGRESO' : 'EGRESO') . '" data-id="' . $id . '"'
             . ' data-numero="' . htmlspecialchars($numero) . '" data-tercero="' . htmlspecialchars($tercero) . '">';
    }

    /** Vista Anticipos con un cliente o proveedor elegido: se listan sus movimientos. */
    private function anticiposDeUnTercero(array $f): bool
    {
        return $f['tercero_id'] > 0 && in_array($f['tercero_tipo'], ['CLIENTE', 'PROVEEDOR'], true);
    }

    // ── Render de filas ───────────────────────────────────────────────────────

    private function badgeFlujo(string $flujo): string
    {
        return $flujo === 'INGRESO'
            ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Ingreso</span>'
            : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Egreso</span>';
    }

    private function renderDetalle(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-search fs-3 d-block mb-2"></i>Sin resultados para los filtros seleccionados.</td></tr>';
        }
        $h = '';
        foreach ($rows as $r) {
            $fecha = !empty($r['fecha']) ? date('d-m-Y', strtotime($r['fecha'])) : '—';
            $signo = $r['tipo_flujo'] === 'INGRESO' ? 'text-success' : 'text-danger';
            $estado = strtolower($r['estado'] ?? '');
            $estBadge = $estado === 'anulado'
                ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulado</span>'
                : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">' . htmlspecialchars(ucfirst($estado ?: 'registrado')) . '</span>';
            $h .= self::trComprobante($r['tipo_flujo'], (int) $r['id_comprobante'], (string) ($r['numero'] ?? ''), (string) ($r['tercero_nombre'] ?? ''))
                . '<td>' . $this->badgeFlujo($r['tipo_flujo']) . '</td>'
                . '<td><code class="text-secondary">' . htmlspecialchars($r['numero'] ?? '') . '</code></td>'
                . '<td>' . $fecha . '</td>'
                . '<td class="text-truncate" style="max-width:200px" title="' . htmlspecialchars($r['tercero_nombre'] ?? '') . '">' . htmlspecialchars($r['tercero_nombre'] ?? '—') . '</td>'
                . '<td class="text-truncate" style="max-width:140px" title="' . htmlspecialchars($r['vendedor'] ?? '') . '">' . htmlspecialchars($r['vendedor'] ?? '') . '</td>'
                . '<td><span class="badge bg-light text-dark border">' . htmlspecialchars($r['tipo_documento'] ?? '') . '</span> ' . htmlspecialchars($r['numero_documento'] ?? '') . '</td>'
                . '<td class="text-truncate text-muted" style="max-width:220px" title="' . htmlspecialchars($r['descripcion'] ?? '') . '">' . htmlspecialchars($r['descripcion'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars($r['concepto'] ?? '') . '</td>'
                . '<td class="text-center">' . $estBadge . '</td>'
                . '<td class="text-end fw-bold ' . $signo . '">$' . number_format((float)($r['monto'] ?? 0), 2)
                . self::notaNoMonetario((float)($r['monto_anticipo'] ?? 0), (float)($r['monto_otros'] ?? 0)) . '</td>'
                . '</tr>';
        }
        return $h;
    }

    /**
     * Vista Documento (agrupado): una fila por ingreso/egreso, como se registró; los
     * documentos que cancela van juntos en una sola celda.
     */
    private function renderComprobante(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-search fs-3 d-block mb-2"></i>Sin resultados para los filtros seleccionados.</td></tr>';
        }
        $h = '';
        foreach ($rows as $r) {
            $fecha = !empty($r['fecha']) ? date('d-m-Y', strtotime($r['fecha'])) : '—';
            $signo = $r['tipo_flujo'] === 'INGRESO' ? 'text-success' : 'text-danger';
            $estado = strtolower($r['estado'] ?? '');
            $estBadge = $estado === 'anulado'
                ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulado</span>'
                : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">' . htmlspecialchars(ucfirst($estado ?: 'registrado')) . '</span>';
            $lineas = (int) ($r['lineas'] ?? 0);
            $h .= self::trComprobante($r['tipo_flujo'], (int) $r['id_comprobante'], (string) ($r['numero'] ?? ''), (string) ($r['tercero_nombre'] ?? ''))
                . '<td>' . $this->badgeFlujo($r['tipo_flujo']) . '</td>'
                . '<td><code class="text-secondary">' . htmlspecialchars($r['numero'] ?? '') . '</code></td>'
                . '<td>' . $fecha . '</td>'
                . '<td class="text-truncate" style="max-width:220px" title="' . htmlspecialchars($r['tercero_nombre'] ?? '') . '">' . htmlspecialchars($r['tercero_nombre'] ?? '—') . '</td>'
                . '<td class="text-truncate" style="max-width:140px" title="' . htmlspecialchars($r['asesor'] ?? '') . '">' . htmlspecialchars($r['asesor'] ?? '') . '</td>'
                . '<td class="text-truncate" style="max-width:320px" title="' . htmlspecialchars($r['detalle'] ?? '') . '">'
                . ($lineas > 1 ? '<span class="badge bg-light text-dark border me-1">' . $lineas . ' doc.</span>' : '')
                . htmlspecialchars($r['documentos'] ?? '') . '</td>'
                . '<td class="text-truncate text-muted" style="max-width:200px" title="' . htmlspecialchars($r['observaciones'] ?? '') . '">' . htmlspecialchars($r['observaciones'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars($r['concepto'] ?? '') . '</td>'
                . '<td class="text-center">' . $estBadge . '</td>'
                . '<td class="text-end fw-bold ' . $signo . '">$' . number_format((float)($r['valor'] ?? 0), 2)
                . self::notaNoMonetario((float)($r['monto_anticipo'] ?? 0), (float)($r['monto_otros'] ?? 0)) . '</td>'
                . '</tr>';
        }
        return $h;
    }

    /**
     * Marcas de la parte pagada con formas no monetarias: el monto se muestra completo,
     * pero esa parte no es entrada/salida de dinero.
     */
    private static function notaNoMonetario(float $anticipo, float $otros): string
    {
        $badge = static fn (string $texto, string $title): string =>
            '<div><span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-25 fw-normal"'
            . ' title="' . $title . '">' . $texto . '</span></div>';
        return ($anticipo > 0.004 ? $badge('Anticipo aplicado $' . number_format($anticipo, 2),
                    'Pagado con un anticipo: el dinero entró (o salió) cuando se registró el anticipo; esta parte no suma en Entrada/Salida de dinero ni en el Neto.') : '')
             . ($otros > 0.004 ? $badge('Otros (no monetario) $' . number_format($otros, 2),
                    'Pagado con una forma de tipo Otros (cruce, compensación…): no es entrada/salida de dinero.') : '');
    }

    /** Celda de "no monetario": guion cuando no hubo. */
    private static function celdaNoMonetario(float $valor): string
    {
        return '<td class="text-end">' . ($valor > 0.004
            ? '<span class="text-warning-emphasis">$' . number_format($valor, 2) . '</span>'
            : '<span class="text-muted">—</span>') . '</td>';
    }

    private function renderAgrupado(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-people fs-3 d-block mb-2"></i>Sin resultados para los filtros seleccionados.</td></tr>';
        }
        $h = '';
        foreach ($rows as $r) {
            $signo = $r['tipo_flujo'] === 'INGRESO' ? 'text-success' : 'text-danger';
            $tterc = match ($r['tercero_tipo']) { 'PROVEEDOR' => 'Proveedor', 'EMPLEADO' => 'Empleado', default => 'Cliente' };
            $h .= '<tr>'
                . '<td>' . $this->badgeFlujo($r['tipo_flujo']) . '</td>'
                . '<td><span class="badge bg-light text-dark border">' . $tterc . '</span></td>'
                . '<td class="fw-medium">' . htmlspecialchars($r['tercero_nombre'] ?? '—') . '<div class="small text-muted">' . htmlspecialchars($r['tercero_ident'] ?? '') . '</div></td>'
                . '<td class="text-center">' . (int)($r['comprobantes'] ?? 0) . '</td>'
                . '<td class="text-center">' . (int)($r['documentos'] ?? 0) . '</td>'
                . '<td class="text-end">$' . number_format((float)($r['total'] ?? 0), 2) . '</td>'
                . self::celdaNoMonetario((float)($r['no_monetario'] ?? 0))
                . '<td class="text-end fw-bold ' . $signo . '">$' . number_format((float)($r['caja'] ?? 0), 2) . '</td>'
                . '</tr>';
        }
        return $h;
    }

    private function renderForma(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-credit-card fs-3 d-block mb-2"></i>Sin resultados para los filtros seleccionados.</td></tr>';
        }
        $h = '';
        foreach ($rows as $r) {
            $signo = $r['tipo_flujo'] === 'INGRESO' ? 'text-success' : 'text-danger';
            // Forma no monetaria (anticipo, otros): su total real, marcado como no entrada/salida de dinero.
            $esAnt = !empty($r['es_no_monetario']);
            $total = '$' . number_format((float)($r['total'] ?? 0), 2);
            $celdaTotal = $esAnt
                ? '<td class="text-end fw-bold text-warning-emphasis" title="Forma no monetaria (anticipo u otros): no entra ni sale dinero de caja o bancos.">'
                  . $total . '<div class="small fw-normal text-muted">no es entrada/salida de dinero</div></td>'
                : '<td class="text-end fw-bold ' . $signo . '">' . $total . '</td>';
            $h .= '<tr>'
                . '<td>' . $this->badgeFlujo($r['tipo_flujo']) . '</td>'
                . '<td class="fw-medium">' . htmlspecialchars($r['forma_nombre'] ?? '—') . '</td>'
                . '<td><span class="badge bg-light text-dark border">' . htmlspecialchars($r['forma_tipo'] ?? '') . '</span></td>'
                . '<td class="text-center">' . (int)($r['comprobantes'] ?? 0) . '</td>'
                . '<td class="text-center">' . (int)($r['pagos_n'] ?? 0) . '</td>'
                . $celdaTotal
                . '</tr>';
        }
        return $h;
    }

    private function renderPeriodo(array $rows, string $modo): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-calendar3 fs-3 d-block mb-2"></i>Sin resultados para los filtros seleccionados.</td></tr>';
        }
        $h = '';
        foreach ($rows as $r) {
            $neto = (float)($r['neto'] ?? 0);
            $h .= '<tr>'
                . '<td class="fw-medium">' . htmlspecialchars(self::etiquetaPeriodo((string)($r['periodo'] ?? ''), $modo)) . '</td>'
                . '<td class="text-end text-success">$' . number_format((float)($r['ingresos'] ?? 0), 2) . '</td>'
                . self::celdaNoMonetario((float)($r['ingresos_no_monetario'] ?? 0))
                . '<td class="text-center text-muted">' . (int)($r['n_ing'] ?? 0) . '</td>'
                . '<td class="text-end text-danger">$' . number_format((float)($r['egresos'] ?? 0), 2) . '</td>'
                . self::celdaNoMonetario((float)($r['egresos_no_monetario'] ?? 0))
                . '<td class="text-center text-muted">' . (int)($r['n_egr'] ?? 0) . '</td>'
                . '<td class="text-end fw-bold ' . ($neto >= 0 ? 'text-primary' : 'text-danger') . '">$' . number_format($neto, 2) . '</td>'
                . '</tr>';
        }
        return $h;
    }

    private static function etiquetaPeriodo(string $periodo, string $modo): string
    {
        if ($modo === 'MES') {
            $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
            [$a, $m] = array_pad(explode('-', $periodo), 2, '');
            return ($meses[$m] ?? $m) . ' ' . $a;
        }
        return $periodo !== '' ? date('d-m-Y', strtotime($periodo)) : '—';
    }

    /** Vista Anticipos: saldo por cliente/proveedor. */
    private function renderAnticipos(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-piggy-bank fs-3 d-block mb-2"></i>Sin anticipos para los filtros seleccionados.</td></tr>';
        }
        $h = '';
        foreach ($rows as $r) {
            $fin = (float)($r['saldo_final'] ?? 0);
            $h .= '<tr>'
                . '<td>' . $this->badgeFlujo($r['tipo_flujo']) . '</td>'
                . '<td class="fw-medium">' . htmlspecialchars($r['tercero_nombre'] ?? '—') . '<div class="small text-muted">' . htmlspecialchars($r['tercero_ident'] ?? '') . '</div></td>'
                . '<td class="text-end">$' . number_format((float)($r['saldo_anterior'] ?? 0), 2) . '</td>'
                . '<td class="text-end text-success">$' . number_format((float)($r['recibido'] ?? 0), 2) . '</td>'
                . '<td class="text-end text-warning-emphasis">$' . number_format((float)($r['aplicado'] ?? 0), 2) . '</td>'
                . '<td class="text-end fw-bold ' . ($fin < 0 ? 'text-danger' : 'text-primary') . '">$' . number_format($fin, 2) . '</td>'
                . '</tr>';
        }
        return $h;
    }

    /** Vista Anticipos con un tercero elegido: saldo anterior y cada movimiento con su saldo. */
    private function renderAnticiposMovimientos(array $mov): string
    {
        // Las filas de un ingreso/egreso (recibido, aplicado) abren su detalle al hacer clic.
        $fila = static fn (string $fecha, string $movimiento, string $num, string $forma, string $detalle, string $rec, string $apl, float $saldo, string $cls = '', array $m = []): string =>
            self::trComprobante((string) ($m['tipo_flujo'] ?? ''), (int) ($m['id_doc'] ?? 0), $num, '', $cls)
            . '<td>' . $fecha . '</td><td>' . $movimiento . '</td>'
            . '<td><code class="text-secondary">' . htmlspecialchars($num) . '</code></td>'
            . '<td>' . htmlspecialchars($forma) . '</td>'
            . '<td class="text-truncate text-muted" style="max-width:260px" title="' . htmlspecialchars($detalle) . '">' . htmlspecialchars($detalle) . '</td>'
            . '<td class="text-end text-success">' . $rec . '</td>'
            . '<td class="text-end text-warning-emphasis">' . $apl . '</td>'
            . '<td class="text-end fw-bold ' . ($saldo < 0 ? 'text-danger' : 'text-primary') . '">$' . number_format($saldo, 2) . '</td>'
            . '</tr>';
        $h = $fila('', '<span class="fw-medium">Saldo anterior</span>', '', '', 'Saldos iniciales y movimientos antes del periodo', '', '', (float) $mov['saldo_anterior'], 'table-light');
        foreach ($mov['movimientos'] as $m) {
            $esApl = $m['origen'] === 'APLICADO';
            $etq = $esApl
                ? '<span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-25">Aplicado</span>'
                : '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">' . ($m['tipo_flujo'] === 'INGRESO' ? 'Recibido' : 'Entregado') . '</span>';
            $h .= $fila(
                !empty($m['fecha']) ? date('d-m-Y', strtotime((string) $m['fecha'])) : '—',
                $etq, (string) $m['numero'], (string) $m['forma'], (string) $m['detalle'],
                $esApl ? '' : '$' . number_format((float) $m['recibido'], 2),
                $esApl ? '$' . number_format((float) $m['aplicado'], 2) : '',
                (float) $m['saldo'], '', $m
            );
        }
        if (empty($mov['movimientos'])) {
            $h .= '<tr><td colspan="8" class="text-center py-3 text-muted">Sin movimientos de anticipo en el periodo.</td></tr>';
        }
        return $h;
    }

    // ── Exportaciones ─────────────────────────────────────────────────────────

    /**
     * Excel. La primera hoja, "Ingresos y Egresos", es el reporte tal como lo pide
     * el usuario: una fila por comprobante con tipo, número, fecha, tercero, valor,
     * detalle (el cuerpo del comprobante), observaciones y asesor, con los filtros y
     * totales arriba. Si en pantalla hay una vista de resumen (por tercero, forma,
     * día o mes), esa va en la segunda hoja. Luego "Detalle completo" (una fila por
     * línea con todas las columnas) y "Cobros y pagos" (una fila por forma de
     * cobro/pago).
     */
    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $f = $this->getFiltros();
        $nombreEmpresa = (new \App\models\Empresa())->getPorId($idEmpresa)['nombre'] ?? '';

        try {
            $svc   = new \App\Services\ReportService();
            $stats  = $this->repository->getEstadisticas($idEmpresa, $f);
            $dinero = fn($v) => ((float) $v < 0 ? '-$' : '$') . number_format(abs((float) $v), 2);
            $info   = $this->describirFiltros($idEmpresa, $f) + [
                'Totales'  => sprintf('Ingresos %s (%d)  |  Egresos %s (%d)',
                    $dinero($stats['total_ingresos']), $stats['n_ingresos'],
                    $dinero($stats['total_egresos']), $stats['n_egresos']),
                'No monetario' => sprintf('Ingresos %s (anticipo %s, otros %s)  |  Egresos %s (anticipo %s, otros %s)',
                    $dinero($stats['no_monetario_ingresos']), $dinero($stats['anticipo_ingresos']), $dinero($stats['otros_ingresos']),
                    $dinero($stats['no_monetario_egresos']), $dinero($stats['anticipo_egresos']), $dinero($stats['otros_egresos'])),
                'Dinero' => sprintf('Entrada %s  |  Salida %s  |  Neto %s',
                    $dinero($stats['caja_ingresos']), $dinero($stats['caja_egresos']), $dinero($stats['neto'])),
                'Generado' => date('d-m-Y H:i:s'),
            ];

            $resumen = $this->hojaResumen($idEmpresa, $f);
            $libro   = $svc->construirSpreadsheet($resumen['headers'], $resumen['data'], 'Ingresos y Egresos', $nombreEmpresa, $info, $resumen['formatos']);
            // Detalle y Observaciones son textos largos (el detalle trae una línea por
            // renglón): ancho fijo con ajuste de texto en lugar del ancho automático,
            // solo sobre las filas de datos (las últimas count(data) filas de la hoja).
            $hoja   = $libro->getActiveSheet();
            $ultima = $hoja->getHighestRow();
            $primera = $ultima - count($resumen['data']) + 1;
            foreach ($resumen['ajustar'] as $col => $ancho) {
                $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $hoja->getColumnDimension($letra)->setAutoSize(false)->setWidth($ancho);
                if ($resumen['data']) {
                    $hoja->getStyle("$letra$primera:$letra$ultima")->getAlignment()
                         ->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
                }
            }

            $modo = in_array($f['ver_por'], self::VISTAS_RESUMEN, true) ? $f['ver_por'] : 'DETALLE';
            if ($modo !== 'DETALLE') {
                $exp = $this->datosExport($idEmpresa, $f);
                $formatos = [];
                foreach ($exp['money'] as $i) { $formatos[$i + 1] = self::XL_DINERO; }
                $hojaModo = match ($modo) {
                    'TERCERO' => 'Por tercero', 'FORMA' => 'Por forma de pago', 'FECHA' => 'Por día',
                    'ANTICIPOS' => 'Anticipos', default => 'Por mes',
                };
                $svc->agregarHoja($libro, $exp['headers'], $exp['data'], $hojaModo, $formatos);
            }
            $detalle = $this->hojaDetalle($idEmpresa, $f);
            $svc->agregarHoja($libro, $detalle['headers'], $detalle['data'], 'Detalle completo', $detalle['formatos']);
            $pagos = $this->hojaPagos($idEmpresa, $f);
            $svc->agregarHoja($libro, $pagos['headers'], $pagos['data'], 'Cobros y pagos', $pagos['formatos']);
            $svc->descargarSpreadsheet($libro, 'Ingresos y Egresos');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
        exit;
    }

    /**
     * Hoja "Ingresos y Egresos": una fila por comprobante (los mismos de la vista
     * Documento, sin tope). Tipo, número, fecha, cliente/proveedor/empleado, valor,
     * detalle (las líneas del cuerpo del comprobante, una por renglón), observaciones
     * y asesor. El asesor es el vendedor de los documentos cobrados o, en las líneas
     * de otros conceptos, el vendedor asignado al cliente.
     */
    private function hojaResumen(int $idEmpresa, array $f): array
    {
        // "Valor" es el comprobante completo; aparte, cuánto se pagó con formas no monetarias
        // (anticipo, otros) y cuánto fue entrada/salida de dinero.
        $headers = ['Tipo', 'N° Ingreso/Egreso', 'Fecha', 'Cliente / Proveedor / Empleado', 'Valor', 'No monetario',
                    'Entrada/salida de dinero', 'Detalle', 'Observaciones', 'Asesor'];
        $data = [];
        foreach ($this->repository->getResumenExport($idEmpresa, $f) as $r) {
            $data[] = [
                $r['tipo_flujo'] === 'INGRESO' ? 'Ingreso' : 'Egreso',
                $r['numero'],
                self::xlFecha($r['fecha']),
                $r['tercero_nombre'],
                (float) $r['valor'],
                self::noMonetarioXl($r['no_monetario']),
                (float) $r['caja'],
                $r['detalle'],
                $r['observaciones'],
                $r['asesor'],
            ];
        }
        return [
            'headers'  => $headers,
            'data'     => $data,
            'formatos' => [3 => self::XL_FECHA, 5 => self::XL_DINERO, 6 => self::XL_DINERO, 7 => self::XL_DINERO],
            'ajustar'  => [8 => 60, 9 => 40], // columna (1-based) => ancho
        ];
    }

    /**
     * Hoja "Detalle": una fila por línea de cada comprobante (las mismas que la vista
     * Documento, sin tope) con todo lo que se sabe de ella. Ingreso y egreso van en
     * columnas separadas para poder sumarlos directamente.
     */
    private function hojaDetalle(int $idEmpresa, array $f): array
    {
        $headers = ['Flujo', 'N° Comprobante', 'Fecha', 'Estado', 'Concepto', 'Tipo tercero', 'Tercero', 'Identificación',
                    'Vendedor', 'Tipo doc.', 'N° Documento', 'Fecha doc.', 'Descripción', 'Valor doc.', 'Saldo anterior',
                    'Ingreso', 'Egreso', 'No monetario', 'Saldo actual', 'Total comprobante', 'Formas de cobro/pago',
                    'Cuenta contable', 'Observaciones', 'Registrado por', 'Fecha registro'];
        $data = [];
        foreach ($this->repository->getDetalleExport($idEmpresa, $f) as $r) {
            $esIng = $r['tipo_flujo'] === 'INGRESO';
            $data[] = [
                $esIng ? 'Ingreso' : 'Egreso',
                $r['numero'],
                self::xlFecha($r['fecha']),
                ucfirst(strtolower((string) $r['estado'])),
                $r['concepto'],
                ucfirst(strtolower((string) $r['tercero_tipo'])),
                $r['tercero_nombre'],
                $r['tercero_ident'],
                $r['vendedor'],
                $r['tipo_documento'],
                $r['numero_documento'],
                self::xlFecha($r['fecha_documento']),
                $r['descripcion'],
                self::num($r['monto_documento']),
                self::num($r['saldo_anterior']),
                $esIng ? (float) $r['monto'] : null,
                $esIng ? null : (float) $r['monto'],
                self::noMonetarioXl((float) $r['monto_anticipo'] + (float) $r['monto_otros']),
                self::num($r['saldo_actual']),
                (float) $r['monto_comprobante'],
                $r['formas_pago'],
                trim(($r['cuenta_codigo'] ?? '') . ' ' . ($r['cuenta_nombre'] ?? '')),
                $r['observaciones'],
                $r['registrado_por'],
                self::xlFecha($r['registrado_el']),
            ];
        }
        return [
            'headers'  => $headers,
            'data'     => $data,
            'formatos' => [3 => self::XL_FECHA, 12 => self::XL_FECHA, 14 => self::XL_DINERO, 15 => self::XL_DINERO,
                           16 => self::XL_DINERO, 17 => self::XL_DINERO, 18 => self::XL_DINERO, 19 => self::XL_DINERO,
                           20 => self::XL_DINERO, 25 => self::XL_FECHA_HORA],
        ];
    }

    /**
     * Hoja "Cobros y pagos": una fila por cada forma de cobro/pago de los comprobantes
     * (los mismos que la vista Por forma de cobro/pago), con la operación bancaria, el
     * cheque y los documentos que cancela.
     */
    private function hojaPagos(int $idEmpresa, array $f): array
    {
        $headers = ['Flujo', 'N° Comprobante', 'Fecha', 'Estado', 'Concepto', 'Tipo tercero', 'Tercero', 'Identificación',
                    'Vendedor', 'Forma de cobro/pago', 'Tipo de forma', 'Operación bancaria', 'N° Cheque', 'Referencia',
                    'Fecha cobro/pago', 'Beneficiario cheque', 'Ingreso', 'Egreso', 'No monetario', 'Documentos',
                    'Observaciones', 'Registrado por'];
        $data = [];
        foreach ($this->repository->getPagosExport($idEmpresa, $f) as $r) {
            // El cobro/pago con una forma no monetaria (anticipo, otros) va en su propia columna.
            $esAnt = !\App\Helpers\TiposFormaPago::esMonetario((string) ($r['forma_tipo'] ?? ''));
            $esIng = $r['tipo_flujo'] === 'INGRESO';
            $data[] = [
                $esIng ? 'Ingreso' : 'Egreso',
                $r['numero'],
                self::xlFecha($r['fecha']),
                ucfirst(strtolower((string) $r['estado'])),
                $r['concepto'],
                ucfirst(strtolower((string) $r['tercero_tipo'])),
                $r['tercero_nombre'],
                $r['tercero_ident'],
                $r['vendedor'],
                $r['forma_nombre'],
                $r['forma_tipo'],
                $r['operacion_bancaria'],
                $r['numero_cheque'],
                $r['referencia'],
                self::xlFecha($r['fecha_cobro']),
                $r['beneficiario_cheque'],
                $esIng && !$esAnt ? (float) $r['monto'] : null,
                !$esIng && !$esAnt ? (float) $r['monto'] : null,
                $esAnt ? (float) $r['monto'] : null,
                $r['documentos'],
                $r['observaciones'],
                $r['registrado_por'],
            ];
        }
        return [
            'headers'  => $headers,
            'data'     => $data,
            'formatos' => [3 => self::XL_FECHA, 15 => self::XL_FECHA, 17 => self::XL_DINERO, 18 => self::XL_DINERO, 19 => self::XL_DINERO],
        ];
    }

    /** Monto no monetario para el Excel: vacío cuando no hubo (en vez de 0,00 en cada fila). */
    private static function noMonetarioXl(mixed $valor): ?float
    {
        return (float) $valor > 0.004 ? round((float) $valor, 2) : null;
    }

    /**
     * Filtros aplicados en texto (etiqueta => valor), para el encabezado del Excel y
     * del PDF: un reporte descargado o impreso tiene que decir de qué periodo,
     * vendedor, tercero, etc. es.
     */
    private function describirFiltros(int $idEmpresa, array $f): array
    {
        $fecha = fn(string $d) => $d !== '' ? date('d-m-Y', strtotime($d)) : '';
        [$desde, $hasta] = [$fecha($f['fecha_desde']), $fecha($f['fecha_hasta'])];
        $info = [
            'Periodo' => match (true) {
                $desde !== '' && $hasta !== '' => "Del $desde al $hasta",
                $desde !== ''                  => "Desde el $desde",
                $hasta !== ''                  => "Hasta el $hasta",
                default                        => 'Todas las fechas',
            },
            'Mostrar' => match ($f['tipo_flujo']) {
                'INGRESO' => 'Solo ingresos', 'EGRESO' => 'Solo egresos', default => 'Ingresos y egresos',
            },
        ];
        if ($f['id_vendedor'] > 0) {
            $vendedor = (new VendedorRepository())->getDetalleCompleto($f['id_vendedor'], $idEmpresa);
            $info['Vendedor'] = ($vendedor['nombre'] ?? '#' . $f['id_vendedor']) . ' (solo cobros de sus facturas y recibos)';
        }
        if ($f['tercero_id'] > 0 && $f['tercero_tipo'] !== '') {
            $info[ucfirst(strtolower($f['tercero_tipo']))] = $this->repository->getNombreTercero($idEmpresa, $f['tercero_tipo'], $f['tercero_id'])
                ?? '#' . $f['tercero_id'];
        }
        if ($f['id_forma'] > 0) {
            $info['Forma de cobro/pago'] = self::nombreEnCatalogo($this->repository->getFormasPago($idEmpresa), $f['id_forma']);
        }
        if ($f['operacion_bancaria'] !== '') {
            $info['Operación bancaria'] = self::OPERACIONES[$f['operacion_bancaria']] ?? $f['operacion_bancaria'];
        }
        if ($f['id_concepto'] > 0) {
            $info['Concepto'] = self::nombreEnCatalogo($this->repository->getConceptos($idEmpresa), $f['id_concepto']);
        }
        if ($f['id_proyecto'] > 0) {
            $info['Proyecto'] = self::nombreEnCatalogo($this->repository->getProyectos($idEmpresa), $f['id_proyecto']);
        }
        $info['Estado'] = $f['estado'] === 'ANULADO' ? 'Anulados' : 'Vigentes';
        if ($f['movimientos'] !== 'TODOS') {
            $info['Movimientos'] = $f['movimientos'] === 'MONETARIOS'
                ? 'Solo monetarios (sin anticipos ni formas tipo Otros)'
                : 'Solo no monetarios (anticipos y formas tipo Otros)';
        }
        if ($f['tipo_documento'] !== '') {
            $info['Tipo de documento'] = $f['tipo_documento'];
        }
        if ($f['monto_min'] !== '' || $f['monto_max'] !== '') {
            $info['Monto del comprobante'] = trim(
                ($f['monto_min'] !== '' ? 'desde $' . number_format((float) $f['monto_min'], 2) : '')
                . ($f['monto_max'] !== '' ? ' hasta $' . number_format((float) $f['monto_max'], 2) : '')
            );
        }
        if ($f['buscar'] !== '') {
            $info['Búsqueda'] = $f['buscar'];
        }
        return $info;
    }

    private static function nombreEnCatalogo(array $catalogo, int $id): string
    {
        foreach ($catalogo as $item) {
            if ((int) $item['id'] === $id) {
                return (string) $item['nombre'];
            }
        }
        return '#' . $id;
    }

    /** Fecha u hora de la BD como fecha de Excel, para poder ordenar y filtrar por ella. */
    private static function xlFecha(?string $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $excel = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime($valor));
        return $excel === false ? null : (float) $excel;
    }

    private static function num(mixed $valor): ?float
    {
        return ($valor === null || $valor === '') ? null : (float) $valor;
    }

    /** Construye [headers, data, right] según el modo (ver_por), reutilizado por Excel y PDF. */
    private function datosExport(int $idEmpresa, array $f): array
    {
        $fl = fn($t) => $t === 'INGRESO' ? 'Ingreso' : 'Egreso';
        switch ($f['ver_por']) {
            case 'TERCERO':
                $rows = $this->repository->getReporteAgrupadoTercero($idEmpresa, $f);
                $headers = ['Flujo', 'Tipo', 'Tercero', 'Identificación', 'Comprobantes', 'Documentos', 'Total', 'No monetario', 'Entrada/salida de dinero'];
                $data = array_map(fn($r) => [$fl($r['tipo_flujo']), ucfirst(strtolower($r['tercero_tipo'] ?? '')),
                    $r['tercero_nombre'], $r['tercero_ident'], (int)$r['comprobantes'], (int)$r['documentos'], (float)$r['total'],
                    (float)$r['no_monetario'], (float)$r['caja']], $rows);
                return ['headers' => $headers, 'data' => $data, 'right' => [4,5,6,7,8], 'money' => [6,7,8]];
            case 'FORMA':
                $rows = $this->repository->getReporteAgrupadoForma($idEmpresa, $f);
                $headers = ['Flujo', 'Forma', 'Tipo', 'Comprobantes', 'Pagos', 'Total', 'Entrada/salida de dinero'];
                $data = array_map(fn($r) => [$fl($r['tipo_flujo']), $r['forma_nombre'], $r['forma_tipo'],
                    (int)$r['comprobantes'], (int)$r['pagos_n'], (float)$r['total'],
                    empty($r['es_no_monetario']) ? 'Sí' : 'No (anticipo u otros)'], $rows);
                return ['headers' => $headers, 'data' => $data, 'right' => [3,4,5], 'money' => [5]];
            case 'FECHA':
            case 'MES':
                $esMes = $f['ver_por'] === 'MES';
                $rows = $esMes ? $this->repository->getReporteAgrupadoMes($idEmpresa, $f)
                               : $this->repository->getReporteAgrupadoFecha($idEmpresa, $f);
                $headers = [$esMes ? 'Mes' : 'Fecha', 'Ingresos', 'Ing. no monetario', 'N° Ing.', 'Egresos', 'Egr. no monetario', 'N° Egr.', 'Neto (dinero)'];
                $data = array_map(fn($r) => [self::etiquetaPeriodo((string)$r['periodo'], $esMes ? 'MES' : 'FECHA'),
                    (float)$r['ingresos'], (float)$r['ingresos_no_monetario'], (int)$r['n_ing'],
                    (float)$r['egresos'], (float)$r['egresos_no_monetario'], (int)$r['n_egr'], (float)$r['neto']], $rows);
                return ['headers' => $headers, 'data' => $data, 'right' => [1,2,3,4,5,6,7], 'money' => [1,2,4,5,7]];
            case 'ANTICIPOS':
                if ($this->anticiposDeUnTercero($f)) {
                    $mov = $this->repository->getAnticiposMovimientos($idEmpresa, $f);
                    $headers = ['Fecha', 'Movimiento', 'N° Comprobante', 'Forma / concepto', 'Detalle', 'Recibido/entregado', 'Aplicado', 'Saldo'];
                    $data = [['', 'Saldo anterior', '', '', 'Saldos iniciales y movimientos antes del periodo', null, null, (float)$mov['saldo_anterior']]];
                    foreach ($mov['movimientos'] as $m) {
                        $data[] = [!empty($m['fecha']) ? date('d-m-Y', strtotime((string)$m['fecha'])) : '',
                            self::nombreMovAnticipo($m), $m['numero'], $m['forma'], $m['detalle'],
                            $m['origen'] === 'APLICADO' ? null : (float)$m['recibido'],
                            $m['origen'] === 'APLICADO' ? (float)$m['aplicado'] : null, (float)$m['saldo']];
                    }
                    return ['headers' => $headers, 'data' => $data, 'right' => [5,6,7], 'money' => [5,6,7]];
                }
                $rows = $this->repository->getAnticiposResumen($idEmpresa, $f);
                $headers = ['Flujo', 'Tercero', 'Identificación', 'Saldo anterior', 'Recibido/entregado', 'Aplicado', 'Saldo final'];
                $data = array_map(fn($r) => [$fl($r['tipo_flujo']), $r['tercero_nombre'], $r['tercero_ident'],
                    (float)$r['saldo_anterior'], (float)$r['recibido'], (float)$r['aplicado'], (float)$r['saldo_final']], $rows);
                return ['headers' => $headers, 'data' => $data, 'right' => [3,4,5,6], 'money' => [3,4,5,6]];
            default:
                $rows = $this->repository->getReporteDetallado($idEmpresa, $f, 0);
                $headers = ['Flujo', 'Número', 'Fecha', 'Tercero', 'Asesor', 'Tipo Doc.', 'N° Documento', 'Descripción', 'Concepto', 'Estado', 'Monto', 'No monetario'];
                $data = array_map(fn($r) => [$fl($r['tipo_flujo']), $r['numero'],
                    !empty($r['fecha']) ? date('d-m-Y', strtotime($r['fecha'])) : '',
                    $r['tercero_nombre'], $r['vendedor'], $r['tipo_documento'], $r['numero_documento'],
                    $r['descripcion'], $r['concepto'], ucfirst(strtolower($r['estado'] ?? '')), (float)$r['monto'],
                    self::noMonetarioXl((float)$r['monto_anticipo'] + (float)$r['monto_otros'])], $rows);
                return ['headers' => $headers, 'data' => $data, 'right' => [10,11], 'money' => [10,11]];
        }
    }

    /** "Recibido" / "Entregado" / "Aplicado" de un movimiento de anticipo. */
    private static function nombreMovAnticipo(array $m): string
    {
        return $m['origen'] === 'APLICADO' ? 'Aplicado' : ($m['tipo_flujo'] === 'INGRESO' ? 'Recibido' : 'Entregado');
    }

    /**
     * PDF con el formato de los demás reportes (App\Helpers\ReportePdf): encabezado con el
     * logo, caja de filtros, indicadores, listado con anchos por columna y fila de totales.
     * Las columnas cuyo valor queda fijado por un filtro no se repiten en cada fila (el
     * asesor al filtrar por vendedor, el tercero al elegir uno, etc.): ese dato ya va en el
     * encabezado y en la caja de filtros. Con ingresos y egresos juntos, los montos van en
     * dos columnas (Ingreso / Egreso) para que el total de cada una se pueda sumar.
     */
    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $f = $this->getFiltros();

        try {
            $modo = in_array($f['ver_por'], [...self::VISTAS_RESUMEN, 'COMPROBANTE'], true) ? $f['ver_por'] : 'DETALLE';
            if ($modo === 'ANTICIPOS' && $this->anticiposDeUnTercero($f)) {
                // Movimientos de un tercero: primero la fila del saldo anterior.
                $modo  = 'ANTICIPOS_MOV';
                $mov   = $this->repository->getAnticiposMovimientos($idEmpresa, $f);
                $filas = array_merge([['fecha' => null, 'origen' => 'SALDO_ANTERIOR', 'tipo_flujo' => '', 'numero' => '', 'forma' => '',
                    'detalle' => 'Saldos iniciales y movimientos antes del periodo', 'recibido' => 0, 'aplicado' => 0,
                    'saldo' => $mov['saldo_anterior']]], $mov['movimientos']);
            } else {
                $filas = match ($modo) {
                    'TERCERO'   => $this->repository->getReporteAgrupadoTercero($idEmpresa, $f),
                    'FORMA'     => $this->repository->getReporteAgrupadoForma($idEmpresa, $f),
                    'FECHA'     => $this->repository->getReporteAgrupadoFecha($idEmpresa, $f),
                    'MES'       => $this->repository->getReporteAgrupadoMes($idEmpresa, $f),
                    'ANTICIPOS' => $this->repository->getAnticiposResumen($idEmpresa, $f),
                    'COMPROBANTE' => $this->repository->getReporteAgrupadoComprobante($idEmpresa, $f),
                    default     => $this->repository->getReporteDetallado($idEmpresa, $f, 0),
                };
            }
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $indicadores = str_starts_with($modo, 'ANTICIPOS')
                ? $this->indicadoresAnticiposPdf($modo, $filas, $mov ?? null)
                : $this->indicadoresPdf($this->repository->getEstadisticas($idEmpresa, $f), $f['tipo_flujo']);

            $horizontal = in_array($modo, ['DETALLE', 'COMPROBANTE', 'ANTICIPOS_MOV'], true);
            $pt         = $horizontal ? 7.0 : 8.0;
            $cols       = \App\Helpers\ReportePdf::completarAnchos($this->columnasPdf($modo, $f, $horizontal, $pt));

            [$titulo, $subtitulo] = $this->tituloPdf($idEmpresa, $modo, $f);
            $sustantivo = match ($modo) {
                'TERCERO' => ['tercero', 'terceros'], 'FORMA' => ['forma de cobro/pago', 'formas de cobro/pago'],
                'FECHA'   => ['día', 'días'], 'MES' => ['mes', 'meses'],
                'ANTICIPOS' => ['cliente/proveedor', 'clientes/proveedores'], 'ANTICIPOS_MOV' => ['fila', 'filas'],
                'COMPROBANTE' => ['comprobante', 'comprobantes'],
                default => ['línea', 'líneas'],
            };
            $n = count($filas);
            $etiquetaTotal = "TOTALES ({$n} " . ($n === 1 ? $sustantivo[0] : $sustantivo[1]) . ')';

            $cabecera = \App\Helpers\ReportePdf::encabezado($idEmpresa, (string) ($empresa['nombre'] ?? ''), $titulo, $subtitulo)
                . \App\Helpers\ReportePdf::filtros($this->describirFiltros($idEmpresa, $f))
                . \App\Helpers\ReportePdf::indicadores($indicadores);

            $autoload = \MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;
            $pdf = new \Spipu\Html2Pdf\Html2Pdf($horizontal ? 'L' : 'P', 'A4', 'es');
            // Por bloques: con un solo writeHTML el tiempo crece al cuadrado de las filas.
            \App\Helpers\ReportePdf::escribir($pdf, $pt, $cabecera, $cols, $filas, $etiquetaTotal);
            $pdf->output('ReporteIngresosEgresos_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }

    /**
     * Título del PDF según lo que se muestra ("Reporte de Ingresos", "… de Egresos" o
     * "… de Ingresos y Egresos") y la vista; el subtítulo nombra al vendedor o al tercero
     * cuando el reporte es de uno solo.
     */
    private function tituloPdf(int $idEmpresa, string $modo, array $f): array
    {
        $titulo = match ($f['tipo_flujo']) {
            'INGRESO' => 'Reporte de Ingresos', 'EGRESO' => 'Reporte de Egresos', default => 'Reporte de Ingresos y Egresos',
        };
        $titulo .= match ($modo) {
            'COMPROBANTE' => ' · Documentos agrupados',
            'TERCERO' => ' · Por tercero', 'FORMA' => ' · Por forma de cobro/pago',
            'FECHA'   => ' · Por día', 'MES' => ' · Por mes',
            'ANTICIPOS', 'ANTICIPOS_MOV' => ' · Anticipos', default => '',
        };
        $sub = [];
        if ($f['id_vendedor'] > 0) {
            $v = (new VendedorRepository())->getDetalleCompleto($f['id_vendedor'], $idEmpresa);
            $sub[] = 'Vendedor: ' . ($v['nombre'] ?? '#' . $f['id_vendedor']);
        }
        if ($f['tercero_id'] > 0 && $f['tercero_tipo'] !== '') {
            $sub[] = ucfirst(strtolower($f['tercero_tipo'])) . ': '
                   . ($this->repository->getNombreTercero($idEmpresa, $f['tercero_tipo'], $f['tercero_id']) ?? '#' . $f['tercero_id']);
        }
        return [$titulo, implode(' · ', $sub)];
    }

    /**
     * Banda de indicadores: solo los del flujo que se muestra. Ingresos/egresos son lo
     * cobrado/pagado completo; aparte, lo no monetario (anticipo y otros) y la
     * entrada/salida de dinero, que es con lo que se calcula el neto.
     */
    private function indicadoresPdf(array $stats, string $flujo): array
    {
        $d    = static fn ($v): string => ((float) $v < 0 ? '-$' : '$') . number_format(abs((float) $v), 2);
        $nIng = $stats['n_ingresos'];
        $nEgr = $stats['n_egresos'];
        $ing  = ['INGRESOS (' . $nIng . ' comprob.)', $d($stats['total_ingresos']), false, '#146c43'];
        $egr  = ['EGRESOS (' . $nEgr . ' comprob.)',  $d($stats['total_egresos']),  false, '#b02a37'];
        return match ($flujo) {
            'INGRESO' => [['COMPROBANTES', (string) $nIng], $ing, ['NO MONETARIO', $d($stats['no_monetario_ingresos'])],
                          ['ENTRADA DE DINERO', $d($stats['caja_ingresos']), true]],
            'EGRESO'  => [['COMPROBANTES', (string) $nEgr], $egr, ['NO MONETARIO', $d($stats['no_monetario_egresos'])],
                          ['SALIDA DE DINERO', $d($stats['caja_egresos']), true]],
            default   => [$ing, $egr, ['ENTRADA DE DINERO', $d($stats['caja_ingresos'])], ['SALIDA DE DINERO', $d($stats['caja_egresos'])],
                          ['NETO (DINERO)', $d($stats['neto']), true]],
        };
    }

    /** Indicadores de la vista Anticipos: saldo anterior, recibido, aplicado y saldo final. */
    private function indicadoresAnticiposPdf(string $modo, array $filas, ?array $mov): array
    {
        $d = static fn ($v): string => ((float) $v < 0 ? '-$' : '$') . number_format(abs((float) $v), 2);
        $suma = static fn (string $campo): float => array_sum(array_map(static fn ($r) => (float) ($r[$campo] ?? 0), $filas));
        $anterior = $modo === 'ANTICIPOS_MOV' ? (float) $mov['saldo_anterior'] : $suma('saldo_anterior');
        $final    = $modo === 'ANTICIPOS_MOV' ? (float) $mov['saldo_final']    : $suma('saldo_final');
        return [['SALDO ANTERIOR', $d($anterior)], ['RECIBIDO / ENTREGADO', $d($suma('recibido')), false, '#146c43'],
                ['APLICADO', $d($suma('aplicado')), false, '#997404'], ['SALDO FINAL', $d($final), true]];
    }

    /**
     * Columnas del PDF por vista (ver App\Helpers\ReportePdf). `w` es el ancho base en %;
     * las columnas `flex` (las descriptivas) se quedan con lo que liberan las que se ocultan.
     */
    private function columnasPdf(string $modo, array $f, bool $horizontal, float $pt): array
    {
        $txt   = static fn (string $campo, float $w) => static fn (array $r): string
            => \App\Helpers\ReportePdf::texto((string) ($r[$campo] ?? ''), \App\Helpers\ReportePdf::anchoPt($w, $horizontal), $pt);
        $num   = static fn ($v): string => number_format((float) $v, 2);
        $ent   = static fn ($v): string => number_format((float) $v, 0);
        $sumar = static fn (callable $valor): callable => static fn (array $rows): float => array_sum(array_map($valor, $rows));
        $ambos = !in_array($f['tipo_flujo'], ['INGRESO', 'EGRESO'], true);

        // Monto de la fila en la columna de su flujo (con ambos flujos) o en una sola columna.
        // El monto va completo; lo pagado con formas no monetarias se anota debajo (detalle)
        // o se marca en la forma, y va aparte en las columnas "no monet." y "dinero".
        $celda = static function (array $r, string $campo, bool $negrita) use ($num): string {
            $v    = $num($r[$campo] ?? 0);
            $ant  = (float) ($r['monto_anticipo'] ?? 0);
            $otro = (float) ($r['monto_otros'] ?? 0);
            return ($negrita ? "<b>$v</b>" : $v)
                . (!empty($r['es_no_monetario']) ? '<br><i>no monetario</i>' : '')
                . ($ant > 0.004 ? '<br><i>ant. ' . $num($ant) . '</i>' : '')
                . ($otro > 0.004 ? '<br><i>otros ' . $num($otro) . '</i>' : '');
        };
        $montos = static function (string $campo, float $w, string $sufijo = '', bool $negrita = true) use ($ambos, $f, $sumar, $celda): array {
            if (!$ambos) {
                return [['lbl' => ($f['tipo_flujo'] === 'INGRESO' ? 'Ingreso' : 'Egreso') . $sufijo, 'w' => $w * 2, 'cls' => 'text-end',
                         'tot' => $sumar(static fn ($r) => (float) ($r[$campo] ?? 0)),
                         'val' => static fn (array $r): string => $celda($r, $campo, $negrita)]];
            }
            $col = static fn (string $flujo, string $lbl) => ['lbl' => $lbl . $sufijo, 'w' => $w, 'cls' => 'text-end',
                'tot' => $sumar(static fn ($r) => ($r['tipo_flujo'] ?? '') === $flujo ? (float) ($r[$campo] ?? 0) : 0.0),
                'val' => static fn (array $r): string => ($r['tipo_flujo'] ?? '') === $flujo ? $celda($r, $campo, $negrita) : ''];
            return [$col('INGRESO', 'Ingreso'), $col('EGRESO', 'Egreso')];
        };
        $dinero = static fn (string $campo, string $lbl, float $w, bool $neg = false): array => ['lbl' => $lbl, 'w' => $w, 'cls' => 'text-end',
            'tot' => $sumar(static fn ($r) => (float) ($r[$campo] ?? 0)),
            'val' => static fn (array $r): string => $neg ? '<b>' . $num($r[$campo] ?? 0) . '</b>' : $num($r[$campo] ?? 0)];

        if ($modo === 'ANTICIPOS') {
            return [
                ['lbl' => 'Flujo', 'w' => 9, 'cls' => '',
                 'val' => static fn (array $r): string => ($r['tipo_flujo'] ?? '') === 'INGRESO' ? 'Cliente' : 'Proveedor'],
                ['lbl' => 'Cliente / Proveedor', 'w' => 31, 'cls' => '', 'flex' => true, 'val' => $txt('tercero_nombre', 31)],
                ['lbl' => 'Identificación', 'w' => 12, 'cls' => '', 'val' => $txt('tercero_ident', 12)],
                $dinero('saldo_anterior', 'Saldo anterior', 12),
                $dinero('recibido', 'Recibido / entregado', 12),
                $dinero('aplicado', 'Aplicado', 12),
                $dinero('saldo_final', 'Saldo final', 12, true),
            ];
        }

        if ($modo === 'ANTICIPOS_MOV') {
            $saldo = $dinero('saldo', 'Saldo', 9, true);
            unset($saldo['tot']); // el saldo es corrido, no se suma
            return [
                ['lbl' => 'Fecha', 'w' => 7, 'cls' => 'text-center',
                 'val' => static fn (array $r): string => !empty($r['fecha']) ? date('d-m-Y', strtotime((string) $r['fecha'])) : ''],
                ['lbl' => 'Movimiento', 'w' => 9, 'cls' => '',
                 'val' => static fn (array $r): string => $r['origen'] === 'SALDO_ANTERIOR' ? '<b>Saldo anterior</b>' : self::nombreMovAnticipo($r)],
                ['lbl' => 'N° Comprobante', 'w' => 11, 'cls' => '', 'val' => $txt('numero', 11)],
                ['lbl' => 'Forma / concepto', 'w' => 14, 'cls' => '', 'val' => $txt('forma', 14)],
                ['lbl' => 'Detalle', 'w' => 28, 'cls' => '', 'flex' => true, 'val' => $txt('detalle', 28)],
                $dinero('recibido', 'Recibido / entregado', 11),
                $dinero('aplicado', 'Aplicado', 11),
                $saldo,
            ];
        }

        if ($modo === 'TERCERO') {
            return array_merge([
                ['lbl' => 'Tipo', 'w' => 8, 'cls' => '',
                 'val' => static fn (array $r): string => htmlspecialchars(ucfirst(strtolower((string) ($r['tercero_tipo'] ?? ''))))],
                ['lbl' => 'Tercero', 'w' => 24, 'cls' => '', 'flex' => true, 'val' => $txt('tercero_nombre', 24)],
                ['lbl' => 'Identificación', 'w' => 11, 'cls' => '', 'val' => $txt('tercero_ident', 11)],
                ['lbl' => 'Comprob.', 'w' => 7, 'cls' => 'text-center', 'fmt' => $ent,
                 'tot' => $sumar(static fn ($r) => (float) ($r['comprobantes'] ?? 0)),
                 'val' => static fn (array $r): string => (string) (int) ($r['comprobantes'] ?? 0)],
                ['lbl' => 'Docs.', 'w' => 6, 'cls' => 'text-center', 'fmt' => $ent,
                 'tot' => $sumar(static fn ($r) => (float) ($r['documentos'] ?? 0)),
                 'val' => static fn (array $r): string => (string) (int) ($r['documentos'] ?? 0)],
            ], $montos('total', 7, '', false), $montos('no_monetario', 7, ' no monet.', false), $montos('caja', 7, ' dinero'));
        }

        if ($modo === 'FORMA') {
            // Un comprobante puede cobrarse con varias formas: la suma de "Comprobantes" lo
            // contaría varias veces, así que esa columna no totaliza (los pagos sí).
            return array_merge([
                ['lbl' => 'Forma de cobro/pago', 'w' => 38, 'cls' => '', 'flex' => true, 'val' => $txt('forma_nombre', 38)],
                ['lbl' => 'Tipo', 'w' => 16, 'cls' => '', 'val' => $txt('forma_tipo', 16)],
                ['lbl' => 'Comprobantes', 'w' => 12, 'cls' => 'text-center',
                 'val' => static fn (array $r): string => (string) (int) ($r['comprobantes'] ?? 0)],
                ['lbl' => 'Pagos', 'w' => 10, 'cls' => 'text-center', 'fmt' => $ent,
                 'tot' => $sumar(static fn ($r) => (float) ($r['pagos_n'] ?? 0)),
                 'val' => static fn (array $r): string => (string) (int) ($r['pagos_n'] ?? 0)],
            ], $montos('total', 12));
        }

        if ($modo === 'FECHA' || $modo === 'MES') {
            $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
            $cols = [['lbl' => $modo === 'MES' ? 'Mes' : 'Fecha', 'w' => 22, 'cls' => $modo === 'MES' ? '' : 'text-center', 'flex' => true,
                'val' => static function (array $r) use ($modo, $meses): string {
                    $p = (string) ($r['periodo'] ?? '');
                    if ($modo === 'MES') {
                        [$a, $m] = array_pad(explode('-', $p), 2, '');
                        return '<b>' . htmlspecialchars(($meses[$m] ?? $m) . ' ' . $a) . '</b>';
                    }
                    return '<b>' . ($p !== '' ? date('d-m-Y', strtotime($p)) : '—') . '</b>';
                }]];
            $flujo = static fn (string $campo, string $campoN, string $lbl, string $lblN) => [
                ['lbl' => $lbl, 'w' => 14, 'cls' => 'text-end', 'tot' => $sumar(static fn ($r) => (float) ($r[$campo] ?? 0)),
                 'val' => static fn (array $r): string => $num($r[$campo] ?? 0)],
                ['lbl' => 'No monet.', 'w' => 11, 'cls' => 'text-end', 'tot' => $sumar(static fn ($r) => (float) ($r[$campo . '_no_monetario'] ?? 0)),
                 'val' => static fn (array $r): string => $num($r[$campo . '_no_monetario'] ?? 0)],
                ['lbl' => $lblN, 'w' => 8, 'cls' => 'text-center', 'fmt' => $ent, 'tot' => $sumar(static fn ($r) => (float) ($r[$campoN] ?? 0)),
                 'val' => static fn (array $r): string => (string) (int) ($r[$campoN] ?? 0)],
            ];
            if ($f['tipo_flujo'] !== 'EGRESO') {
                $cols = array_merge($cols, $flujo('ingresos', 'n_ing', 'Ingresos', 'N° Ing.'));
            }
            if ($f['tipo_flujo'] !== 'INGRESO') {
                $cols = array_merge($cols, $flujo('egresos', 'n_egr', 'Egresos', 'N° Egr.'));
            }
            // Columna de dinero: el neto con ambos flujos; la entrada o salida con uno solo.
            $cols[] = match ($f['tipo_flujo']) {
                'INGRESO' => $dinero('ingresos_caja', 'Entrada de dinero', 14, true),
                'EGRESO'  => $dinero('egresos_caja', 'Salida de dinero', 14, true),
                default   => $dinero('neto', 'Neto (dinero)', 14, true),
            };
            return $cols;
        }

        if ($modo === 'COMPROBANTE') {
            // A4 horizontal: una fila por ingreso/egreso; sus documentos juntos en una celda.
            $cols = [
                ['lbl' => 'Número', 'w' => 10, 'cls' => '', 'val' => $txt('numero', 10)],
                ['lbl' => 'Fecha', 'w' => 6, 'cls' => 'text-center',
                 'val' => static fn (array $r): string => !empty($r['fecha']) ? date('d-m-Y', strtotime((string) $r['fecha'])) : '—'],
            ];
            if ($f['tercero_id'] <= 0) {
                $cols[] = ['lbl' => 'Cliente / Proveedor / Empleado', 'w' => 18, 'cls' => '', 'flex' => true, 'val' => $txt('tercero_nombre', 18)];
            }
            if ($f['id_vendedor'] <= 0) {
                $cols[] = ['lbl' => 'Asesor', 'w' => 10, 'cls' => '', 'val' => $txt('asesor', 10)];
            }
            $cols[] = ['lbl' => 'Documentos', 'w' => 24, 'cls' => '', 'flex' => true,
                       'val' => static fn (array $r): string => htmlspecialchars((string) ($r['documentos'] ?? ''))];
            if ($f['id_concepto'] <= 0) {
                $cols[] = ['lbl' => 'Concepto', 'w' => 8, 'cls' => '', 'val' => $txt('concepto', 8)];
            }
            return array_merge($cols, $montos('valor', 6));
        }

        // DETALLE (A4 horizontal): una fila por línea del comprobante. Se omiten las
        // columnas que un filtro deja con un único valor.
        $cols = [
            ['lbl' => 'Número', 'w' => 9, 'cls' => '', 'val' => $txt('numero', 9)],
            ['lbl' => 'Fecha', 'w' => 6, 'cls' => 'text-center',
             'val' => static fn (array $r): string => !empty($r['fecha']) ? date('d-m-Y', strtotime((string) $r['fecha'])) : '—'],
        ];
        if ($f['tercero_id'] <= 0) {
            $cols[] = ['lbl' => 'Cliente / Proveedor / Empleado', 'w' => 17, 'cls' => '', 'flex' => true, 'val' => $txt('tercero_nombre', 17)];
        }
        if ($f['id_vendedor'] <= 0) {
            $cols[] = ['lbl' => 'Asesor', 'w' => 10, 'cls' => '', 'val' => $txt('vendedor', 10)];
        }
        if ($f['tipo_documento'] === '') {
            $cols[] = ['lbl' => 'Tipo doc.', 'w' => 6, 'cls' => 'text-center', 'val' => $txt('tipo_documento', 6)];
        }
        $cols[] = ['lbl' => 'N° Documento', 'w' => 10, 'cls' => '', 'val' => $txt('numero_documento', 10)];
        $cols[] = ['lbl' => 'Descripción', 'w' => 16, 'cls' => '', 'flex' => true, 'val' => $txt('descripcion', 16)];
        if ($f['id_concepto'] <= 0) {
            $cols[] = ['lbl' => 'Concepto', 'w' => 8, 'cls' => '', 'val' => $txt('concepto', 8)];
        }
        return array_merge($cols, $montos('monto', 6));
    }
}
