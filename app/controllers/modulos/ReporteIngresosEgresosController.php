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

    private const OPERACIONES = ['TRANSFERENCIA' => 'Transferencia', 'DEPOSITO' => 'Depósito', 'DEBITO' => 'Débito', 'CHEQUE' => 'Cheque'];

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
            'ver_por'           => strtoupper(trim($_REQUEST['ver_por'] ?? 'DETALLE')), // DETALLE | TERCERO
            'fecha_desde'       => trim($_REQUEST['fecha_desde'] ?? ''),
            'fecha_hasta'       => trim($_REQUEST['fecha_hasta'] ?? ''),
            'tercero_tipo'      => strtoupper(trim($_REQUEST['tercero_tipo'] ?? '')),
            'tercero_id'        => (int) ($_REQUEST['tercero_id'] ?? 0),
            'id_vendedor'       => (int) ($_REQUEST['id_vendedor'] ?? 0),
            'id_forma'          => (int) ($_REQUEST['id_forma'] ?? 0),
            'operacion_bancaria'=> strtoupper(trim($_REQUEST['operacion_bancaria'] ?? '')),
            'id_concepto'       => (int) ($_REQUEST['id_concepto'] ?? 0),
            'estado'            => strtoupper(trim($_REQUEST['estado'] ?? 'TODOS')),
            'tipo_documento'    => strtoupper(trim($_REQUEST['tipo_documento'] ?? '')),
            'monto_min'         => trim($_REQUEST['monto_min'] ?? ''),
            'monto_max'         => trim($_REQUEST['monto_max'] ?? ''),
            'buscar'            => trim($_REQUEST['buscar'] ?? ''),
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
                'stats'     => [
                    'total_ingresos' => (float)($stats['total_ingresos'] ?? 0),
                    'total_egresos'  => (float)($stats['total_egresos'] ?? 0),
                    'neto'           => (float)($stats['neto'] ?? 0),
                    'n_ingresos'     => (int)($stats['n_ingresos'] ?? 0),
                    'n_egresos'      => (int)($stats['n_egresos'] ?? 0),
                    'n_documentos'   => (int)($stats['n_documentos'] ?? 0),
                ],
                'excel_url' => $urlBase . '/exportExcel?' . $qs,
                'pdf_url'   => $urlBase . '/exportPdf?' . $qs,
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
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
            return '<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-search fs-3 d-block mb-2"></i>Sin resultados para los filtros seleccionados.</td></tr>';
        }
        $h = '';
        foreach ($rows as $r) {
            $fecha = !empty($r['fecha']) ? date('d-m-Y', strtotime($r['fecha'])) : '—';
            $signo = $r['tipo_flujo'] === 'INGRESO' ? 'text-success' : 'text-danger';
            $estado = strtolower($r['estado'] ?? '');
            $estBadge = $estado === 'anulado'
                ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">Anulado</span>'
                : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">' . htmlspecialchars(ucfirst($estado ?: 'registrado')) . '</span>';
            $h .= '<tr>'
                . '<td>' . $this->badgeFlujo($r['tipo_flujo']) . '</td>'
                . '<td><code class="text-secondary">' . htmlspecialchars($r['numero'] ?? '') . '</code></td>'
                . '<td>' . $fecha . '</td>'
                . '<td class="text-truncate" style="max-width:200px" title="' . htmlspecialchars($r['tercero_nombre'] ?? '') . '">' . htmlspecialchars($r['tercero_nombre'] ?? '—') . '</td>'
                . '<td><span class="badge bg-light text-dark border">' . htmlspecialchars($r['tipo_documento'] ?? '') . '</span> ' . htmlspecialchars($r['numero_documento'] ?? '') . '</td>'
                . '<td class="text-truncate text-muted" style="max-width:220px" title="' . htmlspecialchars($r['descripcion'] ?? '') . '">' . htmlspecialchars($r['descripcion'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars($r['concepto'] ?? '') . '</td>'
                . '<td class="text-center">' . $estBadge . '</td>'
                . '<td class="text-end fw-bold ' . $signo . '">$' . number_format((float)($r['monto'] ?? 0), 2) . '</td>'
                . '</tr>';
        }
        return $h;
    }

    private function renderAgrupado(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-people fs-3 d-block mb-2"></i>Sin resultados para los filtros seleccionados.</td></tr>';
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
                . '<td class="text-end fw-bold ' . $signo . '">$' . number_format((float)($r['total'] ?? 0), 2) . '</td>'
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
            $h .= '<tr>'
                . '<td>' . $this->badgeFlujo($r['tipo_flujo']) . '</td>'
                . '<td class="fw-medium">' . htmlspecialchars($r['forma_nombre'] ?? '—') . '</td>'
                . '<td><span class="badge bg-light text-dark border">' . htmlspecialchars($r['forma_tipo'] ?? '') . '</span></td>'
                . '<td class="text-center">' . (int)($r['comprobantes'] ?? 0) . '</td>'
                . '<td class="text-center">' . (int)($r['pagos_n'] ?? 0) . '</td>'
                . '<td class="text-end fw-bold ' . $signo . '">$' . number_format((float)($r['total'] ?? 0), 2) . '</td>'
                . '</tr>';
        }
        return $h;
    }

    private function renderPeriodo(array $rows, string $modo): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-calendar3 fs-3 d-block mb-2"></i>Sin resultados para los filtros seleccionados.</td></tr>';
        }
        $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
        $h = '';
        foreach ($rows as $r) {
            $ing = (float)($r['ingresos'] ?? 0); $egr = (float)($r['egresos'] ?? 0); $neto = $ing - $egr;
            if ($modo === 'MES') {
                [$a, $m] = array_pad(explode('-', (string)($r['periodo'] ?? '')), 2, '');
                $etq = ($meses[$m] ?? $m) . ' ' . $a;
            } else {
                $etq = !empty($r['periodo']) ? date('d-m-Y', strtotime((string)$r['periodo'])) : '—';
            }
            $h .= '<tr>'
                . '<td class="fw-medium">' . htmlspecialchars($etq) . '</td>'
                . '<td class="text-end text-success">$' . number_format($ing, 2) . '</td>'
                . '<td class="text-center text-muted">' . (int)($r['n_ing'] ?? 0) . '</td>'
                . '<td class="text-end text-danger">$' . number_format($egr, 2) . '</td>'
                . '<td class="text-center text-muted">' . (int)($r['n_egr'] ?? 0) . '</td>'
                . '<td class="text-end fw-bold ' . ($neto >= 0 ? 'text-primary' : 'text-danger') . '">$' . number_format($neto, 2) . '</td>'
                . '</tr>';
        }
        return $h;
    }

    // ── Exportaciones ─────────────────────────────────────────────────────────

    /**
     * Excel detallado. La primera hoja es lo que se ve en pantalla —en la vista
     * Documento, el detalle completo; en las de resumen, el resumen, seguido del
     * detalle completo en la hoja "Detalle"— con los filtros y totales arriba. La
     * última, "Cobros y pagos", tiene una fila por cada forma de cobro/pago.
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
                'Totales'  => sprintf('Ingresos %s (%d)  |  Egresos %s (%d)  |  Neto %s',
                    $dinero($stats['total_ingresos'] ?? 0), (int)($stats['n_ingresos'] ?? 0),
                    $dinero($stats['total_egresos'] ?? 0), (int)($stats['n_egresos'] ?? 0),
                    $dinero($stats['neto'] ?? 0)),
                'Generado' => date('d-m-Y H:i:s'),
            ];
            $detalle = $this->hojaDetalle($idEmpresa, $f);

            $modo = in_array($f['ver_por'], ['TERCERO', 'FORMA', 'FECHA', 'MES'], true) ? $f['ver_por'] : 'DETALLE';
            if ($modo === 'DETALLE') {
                $libro = $svc->construirSpreadsheet($detalle['headers'], $detalle['data'], 'Detalle', $nombreEmpresa, $info, $detalle['formatos']);
            } else {
                $exp = $this->datosExport($idEmpresa, $f);
                $formatos = [];
                foreach ($exp['money'] as $i) { $formatos[$i + 1] = self::XL_DINERO; }
                $hoja = match ($modo) { 'TERCERO' => 'Por tercero', 'FORMA' => 'Por forma de pago', 'FECHA' => 'Por día', default => 'Por mes' };
                $libro = $svc->construirSpreadsheet($exp['headers'], $exp['data'], $hoja, $nombreEmpresa, $info, $formatos);
                $svc->agregarHoja($libro, $detalle['headers'], $detalle['data'], 'Detalle', $detalle['formatos']);
            }
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
     * Hoja "Detalle": una fila por línea de cada comprobante (las mismas que la vista
     * Documento, sin tope) con todo lo que se sabe de ella. Ingreso y egreso van en
     * columnas separadas para poder sumarlos directamente.
     */
    private function hojaDetalle(int $idEmpresa, array $f): array
    {
        $headers = ['Flujo', 'N° Comprobante', 'Fecha', 'Estado', 'Concepto', 'Tipo tercero', 'Tercero', 'Identificación',
                    'Vendedor', 'Tipo doc.', 'N° Documento', 'Fecha doc.', 'Descripción', 'Valor doc.', 'Saldo anterior',
                    'Ingreso', 'Egreso', 'Saldo actual', 'Total comprobante', 'Formas de cobro/pago', 'Cuenta contable',
                    'Observaciones', 'Registrado por', 'Fecha registro'];
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
                           24 => self::XL_FECHA_HORA],
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
                    'Fecha cobro/pago', 'Beneficiario cheque', 'Ingreso', 'Egreso', 'Documentos', 'Observaciones',
                    'Registrado por'];
        $data = [];
        foreach ($this->repository->getPagosExport($idEmpresa, $f) as $r) {
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
                $esIng ? (float) $r['monto'] : null,
                $esIng ? null : (float) $r['monto'],
                $r['documentos'],
                $r['observaciones'],
                $r['registrado_por'],
            ];
        }
        return [
            'headers'  => $headers,
            'data'     => $data,
            'formatos' => [3 => self::XL_FECHA, 15 => self::XL_FECHA, 17 => self::XL_DINERO, 18 => self::XL_DINERO],
        ];
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
        if ($f['estado'] !== '' && $f['estado'] !== 'TODOS') {
            $info['Estado'] = ucfirst(strtolower($f['estado']));
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
                $headers = ['Flujo', 'Tipo', 'Tercero', 'Identificación', 'Comprobantes', 'Documentos', 'Total'];
                $data = array_map(fn($r) => [$fl($r['tipo_flujo']), ucfirst(strtolower($r['tercero_tipo'] ?? '')),
                    $r['tercero_nombre'], $r['tercero_ident'], (int)$r['comprobantes'], (int)$r['documentos'], (float)$r['total']], $rows);
                return ['headers' => $headers, 'data' => $data, 'right' => [4,5,6], 'money' => [6]];
            case 'FORMA':
                $rows = $this->repository->getReporteAgrupadoForma($idEmpresa, $f);
                $headers = ['Flujo', 'Forma', 'Tipo', 'Comprobantes', 'Pagos', 'Total'];
                $data = array_map(fn($r) => [$fl($r['tipo_flujo']), $r['forma_nombre'], $r['forma_tipo'],
                    (int)$r['comprobantes'], (int)$r['pagos_n'], (float)$r['total']], $rows);
                return ['headers' => $headers, 'data' => $data, 'right' => [3,4,5], 'money' => [5]];
            case 'FECHA':
            case 'MES':
                $esMes = $f['ver_por'] === 'MES';
                $rows = $esMes ? $this->repository->getReporteAgrupadoMes($idEmpresa, $f)
                               : $this->repository->getReporteAgrupadoFecha($idEmpresa, $f);
                $headers = [$esMes ? 'Mes' : 'Fecha', 'Ingresos', 'N° Ing.', 'Egresos', 'N° Egr.', 'Neto'];
                $data = array_map(function ($r) use ($esMes) {
                    $ing = (float)$r['ingresos']; $egr = (float)$r['egresos'];
                    $etq = $esMes ? (string)$r['periodo'] : (!empty($r['periodo']) ? date('d-m-Y', strtotime((string)$r['periodo'])) : '');
                    return [$etq, $ing, (int)$r['n_ing'], $egr, (int)$r['n_egr'], $ing - $egr];
                }, $rows);
                return ['headers' => $headers, 'data' => $data, 'right' => [1,2,3,4,5], 'money' => [1,3,5]];
            default:
                $rows = $this->repository->getReporteDetallado($idEmpresa, $f, 0);
                $headers = ['Flujo', 'Número', 'Fecha', 'Tercero', 'Tipo Doc.', 'N° Documento', 'Descripción', 'Concepto', 'Estado', 'Monto'];
                $data = array_map(fn($r) => [$fl($r['tipo_flujo']), $r['numero'],
                    !empty($r['fecha']) ? date('d-m-Y', strtotime($r['fecha'])) : '',
                    $r['tercero_nombre'], $r['tipo_documento'], $r['numero_documento'],
                    $r['descripcion'], $r['concepto'], ucfirst(strtolower($r['estado'] ?? '')), (float)$r['monto']], $rows);
                return ['headers' => $headers, 'data' => $data, 'right' => [9], 'money' => [9]];
        }
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $f = $this->getFiltros();
        $exp     = $this->datosExport($idEmpresa, $f);
        $stats   = $this->repository->getEstadisticas($idEmpresa, $f);
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $filtros = $this->describirFiltros($idEmpresa, $f);

        $autoload = \MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) require_once $autoload;

        $money  = fn($v) => number_format((float)$v, 2);
        $right  = array_flip($exp['right']);
        $money2 = array_flip($exp['money'] ?? []);

        ob_start(); ?>
        <style>
            table { width:100%; border-collapse:collapse; font-family:Arial,sans-serif; font-size:8pt; }
            th { background:#f2f2f2; border:1px solid #ccc; padding:4px; }
            td { border:1px solid #ccc; padding:4px; }
            .r { text-align:right; } .c { text-align:center; }
            .head { text-align:center; margin-bottom:10px; }
            .kpi td { border:1px solid #ccc; padding:6px; font-size:9pt; }
        </style>
        <?php $subtitulos = ['TERCERO' => ' — por tercero', 'FORMA' => ' — por forma de cobro/pago', 'FECHA' => ' — total por día', 'MES' => ' — por mes']; ?>
        <div class="head">
            <h3><?= htmlspecialchars($empresa['nombre'] ?? '') ?></h3>
            <h4>Reporte de Ingresos y Egresos<?= $subtitulos[$f['ver_por']] ?? '' ?></h4>
            <p style="font-size:8pt"><?= htmlspecialchars(implode('  |  ', array_map(fn($k, $v) => "$k: $v", array_keys($filtros), $filtros))) ?></p>
            <p style="font-size:8pt">Generado: <?= date('d-m-Y H:i:s') ?></p>
        </div>
        <table class="kpi" style="margin-bottom:10px">
            <tr>
                <td class="c"><strong>Ingresos</strong><br>$<?= $money($stats['total_ingresos'] ?? 0) ?> (<?= (int)($stats['n_ingresos'] ?? 0) ?>)</td>
                <td class="c"><strong>Egresos</strong><br>$<?= $money($stats['total_egresos'] ?? 0) ?> (<?= (int)($stats['n_egresos'] ?? 0) ?>)</td>
                <td class="c"><strong>Neto</strong><br>$<?= $money($stats['neto'] ?? 0) ?></td>
            </tr>
        </table>
        <table>
            <thead>
                <tr><?php foreach ($exp['headers'] as $i => $h): ?><th class="<?= isset($right[$i]) ? 'r' : '' ?>"><?= htmlspecialchars($h) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
                <?php foreach ($exp['data'] as $fila): ?>
                    <tr>
                        <?php foreach ($fila as $i => $val): ?>
                            <td class="<?= isset($right[$i]) ? 'r' : '' ?>"><?= isset($money2[$i]) ? '$' . $money($val) : htmlspecialchars((string)$val) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $html = ob_get_clean();
        try {
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output('ReporteIngresosEgresos_' . date('Ymd_His') . '.pdf', 'D');
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
        exit;
    }
}
