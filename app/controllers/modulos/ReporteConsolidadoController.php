<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\ColumnasReporte;
use App\Helpers\ReportePdf;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\ReporteConsolidadoRepository;
use App\Services\modulos\ReportePersonalizacionService;

class ReporteConsolidadoController extends BaseModuloController
{
    /** Única vista del reporte en ColumnasReporte::CATALOGO (botón «Personalizar»). */
    private const VISTA = 'RESUMEN';

    private ReporteConsolidadoRepository $repository;

    protected function getRutaModulo(): string
    {
        return 'modulos/reporte_consolidado';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repository = new ReporteConsolidadoRepository();
    }

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        // Consolidado por establecimientos (mismo selector que Cuentas por Cobrar/Pagar): solo
        // aparece si la empresa activa es la matriz del grupo RUC y el usuario tiene acceso a
        // al menos otro establecimiento (ver EmpresaRepository::getIdsConsolidadoDesdeMatriz).
        $empresaRepo      = new EmpresaRepository();
        $idsConsolidado   = $empresaRepo->getIdsConsolidadoDesdeMatriz($idEmpresa, (int) $_SESSION['id_usuario']);
        $establecimientos = $idsConsolidado ? $empresaRepo->getEtiquetasEstablecimiento($idsConsolidado) : [];

        $this->viewWithLayout('layouts.main', 'modulos/reporte_consolidado/index', [
            'titulo'     => 'Reporte Consolidado de Transacciones',
            'perm'       => $this->getPermisos(),
            'rutaModulo' => $this->getRutaModulo(),
            'anios'      => $this->repository->getAniosDisponibles($idEmpresa),
            'columnas'   => $this->columnasVista()['pantalla'],
            'puedeConsolidar'  => !empty($idsConsolidado),
            'establecimientos' => $establecimientos,
            'fullWidth'  => true,
            'base'       => BASE_URL,
        ]);
    }

    private function getFiltrosDesdeRequest(): array
    {
        // Sin selector de tipos: el reporte siempre trae TODOS los documentos del período.
        return [
            'fecha_desde'      => $_REQUEST['fecha_desde'] ?? date('Y-m-01'),
            'fecha_hasta'      => $_REQUEST['fecha_hasta'] ?? date('Y-m-d'),
            'incluir_anulados' => !empty($_REQUEST['incluir_anulados']),
            'buscar'           => trim($_REQUEST['buscar'] ?? ''),
            // ESTABLECIMIENTO (solo la empresa activa) | CONSOLIDADO (todo el grupo RUC;
            // solo se honra desde la matriz — ver resolverAlcance()).
            'alcance'          => strtoupper(trim((string) ($_REQUEST['alcance'] ?? ''))),
        ];
    }

    /**
     * Alcance del reporte. Devuelve [idsEmpresa, consolidado]. `alcance=CONSOLIDADO` solo se
     * honra si la empresa activa es la matriz del grupo RUC y hay hermanas accesibles para el
     * usuario; en cualquier otro caso se ignora en silencio y el reporte queda como siempre.
     */
    private function resolverAlcance(int $idEmpresa, array &$filtros): array
    {
        $idsEmpresa  = [$idEmpresa];
        $consolidado = false;
        if (($filtros['alcance'] ?? '') === 'CONSOLIDADO') {
            $grupo = (new EmpresaRepository())->getIdsConsolidadoDesdeMatriz($idEmpresa, (int) $_SESSION['id_usuario']);
            if ($grupo) {
                $idsEmpresa  = $grupo;
                $consolidado = true;
            }
        }
        $filtros['alcance'] = $consolidado ? 'CONSOLIDADO' : 'ESTABLECIMIENTO';
        return [$idsEmpresa, $consolidado];
    }

    /** Texto del alcance para el encabezado del PDF. */
    private function describirAlcance(array $idsEmpresa, bool $consolidado): string
    {
        if (!$consolidado) {
            return '';
        }
        $etq = (new EmpresaRepository())->getEtiquetasEstablecimiento($idsEmpresa);
        return 'Consolidado por RUC (' . count($etq) . ' establecimientos: ' . implode(' · ', $etq) . ')';
    }

    /**
     * Columnas del reporte según la personalización de la empresa activa (botón
     * «Personalizar»): ['pantalla' => …, 'pdf' => …, 'horizontal' => bool]. Ver ColumnasReporte.
     */
    private function columnasVista(): array
    {
        return ReportePersonalizacionService::crear()->resolver((int) $_SESSION['id_empresa'], $this->getRutaModulo(), self::VISTA);
    }

    /** Cabecera de la tabla en pantalla (un <th> por columna visible). */
    public static function theadHtml(array $columnas): string
    {
        $html = '<tr>';
        $i = 0;
        foreach ($columnas as $col) {
            $cls  = trim(ColumnasReporte::claseAlinear($col['alinear']) . ($i++ === 0 ? ' ps-2' : ''));
            $html .= '<th' . ($cls !== '' ? " class=\"{$cls}\"" : '') . '>' . htmlspecialchars($col['titulo']) . '</th>';
        }
        return $html . '</tr>';
    }

    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros   = $this->getFiltrosDesdeRequest();
            [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);

            $rows     = $this->repository->getResumen($idsEmpresa, $filtros);
            $stats    = $this->repository->getEstadisticas($idsEmpresa, $filtros);
            $columnas = $this->columnasVista()['pantalla'];

            ob_start();
            if (empty($rows)) {
                echo '<tr><td colspan="' . count($columnas) . '" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-bar-graph fs-3 d-block mb-2"></i>No se encontraron resultados.</td></tr>';
            } else {
                foreach ($rows as $r) {
                    echo $this->renderFilaHtml($r, $columnas, $consolidado);
                }
            }
            $rowsHtml = ob_get_clean();

            echo json_encode([
                'ok' => true, 'thead' => self::theadHtml($columnas), 'columnas' => count($columnas),
                'rows' => $rowsHtml, 'stats' => $stats, 'consolidado' => $consolidado, 'establecimientos' => count($idsEmpresa),
            ]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            error_log('ReporteConsolidado Exception: ' . $e->getMessage() . ' on line ' . $e->getLine());
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Fila de la pantalla: una celda por columna visible, en el orden de la personalización. */
    private function renderFilaHtml(array $r, array $columnas, bool $consolidado = false): string
    {
        $html = '<tr class="align-middle">';
        $i = 0;
        foreach ($columnas as $clave => $col) {
            $cls  = trim(ColumnasReporte::claseAlinear($col['alinear']) . ($i++ === 0 ? ' ps-2' : ''));
            $html .= '<td' . ($cls !== '' ? " class=\"{$cls}\"" : '') . '>'
                   . $this->celdaPantalla((string) $clave, $r, $columnas, $consolidado) . '</td>';
        }
        return $html . '</tr>';
    }

    /** Contenido HTML (escapado) de una celda de la pantalla. */
    private function celdaPantalla(string $clave, array $r, array $columnas, bool $consolidado): string
    {
        $e   = static fn ($v): string => htmlspecialchars((string) $v);
        $num = static fn (string $k): string => number_format((float) ($r[$k] ?? 0), 2);
        switch ($clave) {
            case 'tipo':
                $tipo   = ReporteConsolidadoRepository::GRUPOS[$r['tipo_documento']] ?? $r['tipo_documento'];
                $origen = (string) ($r['origen'] ?? '');
                return "<span class='badge bg-primary bg-opacity-10 text-primary border border-primary' style='font-size:.7rem;'>" . $e($tipo) . '</span>'
                     . ($origen !== '' ? " <small class='text-muted'>" . $e($origen) . '</small>' : '');
            case 'fecha':
                return !empty($r['fecha']) ? date('d/m/Y', strtotime((string) $r['fecha'])) : '';
            case 'numero':
                // Consolidado por RUC: badge con el establecimiento dueño del documento (mismo
                // estilo que Cuentas por Cobrar/Pagar), con el nombre de la empresa en el tooltip.
                $badge = '';
                if ($consolidado && !empty($r['establecimiento'])) {
                    $badge = "<span class='badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 me-1 fw-normal' style='font-size:.65rem;' title='"
                           . htmlspecialchars((string) ($r['empresa_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') . "'>" . $e($r['establecimiento']) . '</span>';
                }
                return $badge . $e($r['numero'] ?? '');
            case 'tercero':
                // Sin la columna Identificación en pantalla, la identificación va debajo del nombre.
                $ident = isset($columnas['identificacion']) ? '' : "<br><small class='text-muted'>" . $e($r['tercero_ident'] ?? '') . '</small>';
                return "<span class='fw-bold'>" . $e($r['tercero_nombre'] ?? '') . '</span>' . $ident;
            case 'identificacion':
                return $e($r['tercero_ident'] ?? '');
            case 'subtotal':
            case 'iva':
                return $num($clave);
            case 'total':
                return '<span class="fw-bold">' . $num('total') . '</span>';
            case 'estado':
                return '<small>' . $e($r['estado'] ?? '') . '</small>';
            default:
                return '';
        }
    }

    /** Códigos de IVA (codigo_porcentaje) presentes en $rows bajo 'iva_desglose' (JSON), ordenados. */
    private function codigosIvaPresentes(array $rows): array
    {
        $codigos = [];
        foreach ($rows as $r) {
            foreach (json_decode($r['iva_desglose'] ?? '{}', true) ?: [] as $c => $v) {
                $codigos[(string) $c] = true;
            }
        }
        $codigos = array_keys($codigos);
        sort($codigos, SORT_NUMERIC);
        return $codigos;
    }

    /** Encabezados "IVA {etiqueta}" para cada código presente. */
    private function headersIva(array $codigos): array
    {
        $etiquetas = $this->repository->getEtiquetasIva();
        return array_map(fn($c) => 'IVA ' . ($etiquetas[$c] ?? "cód. $c"), $codigos);
    }

    /** Valor de IVA de la fila para cada código, en el mismo orden que headersIva(). */
    private function valoresIva(array $r, array $codigos): array
    {
        $desglose = json_decode($r['iva_desglose'] ?? '{}', true) ?: [];
        return array_map(fn($c) => (float) ($desglose[$c] ?? 0), $codigos);
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltrosDesdeRequest();
        [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);
        $idEmpresa = $idsEmpresa; // alcance del reporte (una empresa o el grupo RUC)

        // Consolidado: cada hoja lleva primero la columna "Estab." con el establecimiento
        // dueño de la línea; sin consolidado, ambas piezas quedan vacías y nada cambia.
        $hEst = $consolidado ? ['Estab.'] : [];
        $est  = fn (array $r): array => $consolidado ? [(string) ($r['establecimiento'] ?? '')] : [];

        try {
            $empresa       = (new \App\models\Empresa())->getPorId((int) $_SESSION['id_empresa']);
            $nombreEmpresa = $empresa['nombre'] ?? '';
            if ($consolidado) {
                $nombreEmpresa .= ' — ' . $this->describirAlcance($idsEmpresa, true);
            }

            $reportService = new \App\Services\ReportService();

            $compras     = $this->repository->getDetalleCompras($idEmpresa, $filtros);
            $codigosIva  = $this->codigosIvaPresentes($compras);
            $spreadsheet = $reportService->construirSpreadsheet(
                array_merge($hEst, ['Fecha', 'Tipo Comprobante', 'N° Documento', 'N° Autorización', 'Proveedor', 'RUC/Cédula', 'Código', 'Descripción', 'Cantidad', 'Precio Unit.', 'Descuento', 'Subtotal'], $this->headersIva($codigosIva), ['Total', 'Estado']),
                array_map(fn($r) => array_merge($est($r), [
                    date('d/m/Y', strtotime($r['fecha'])), $r['tipo_comprobante_nombre'] ?? '', $r['numero_documento'], $r['numero_autorizacion'] ?? '',
                    $r['proveedor_nombre'] ?? '', $r['proveedor_ruc'] ?? '', $r['codigo'] ?? '', $r['descripcion'] ?? '',
                    (float) $r['cantidad'], (float) $r['precio_unitario'], (float) $r['descuento'], (float) $r['subtotal_linea'],
                ], $this->valoresIva($r, $codigosIva), [(float) $r['total_linea'], $r['estado'] ?? '']), $compras),
                'Compras', $nombreEmpresa
            );

            $retCompra = $this->repository->getDetalleRetencionesCompra($idEmpresa, $filtros);
            $reportService->agregarHoja($spreadsheet,
                array_merge($hEst, ['Fecha', 'N° Documento', 'Clave Acceso', 'Proveedor', 'RUC/Cédula', 'Cod. Doc. Sustento', 'N° Doc. Sustento', 'Impuesto', 'Cod. Retención', 'Concepto', 'Base Imponible', '%', 'Valor Retenido', 'Total Comprobante', 'Estado']),
                array_map(fn($r) => array_merge($est($r), [
                    date('d/m/Y', strtotime($r['fecha'])), $r['numero_documento'], $r['clave_acceso'] ?? '',
                    $r['proveedor_nombre'] ?? '', $r['proveedor_ruc'] ?? '', $r['cod_doc_sustento'] ?? '', $r['num_doc_sustento'] ?? '',
                    $r['codigo_impuesto'] ?? '', $r['codigo_retencion'] ?? '', $r['concepto'] ?? '',
                    (float) $r['base_imponible'], (float) $r['porcentaje'], (float) $r['valor_retenido'],
                    (float) $r['total_retenido'], $r['estado'] ?? '',
                ]), $retCompra),
                'Retenciones Compra'
            );

            $facturas   = $this->repository->getDetalleFacturasVenta($idEmpresa, $filtros);
            $codigosIva = $this->codigosIvaPresentes($facturas);
            $reportService->agregarHoja($spreadsheet,
                array_merge($hEst, ['Fecha', 'N° Documento', 'Clave Acceso', 'Cliente', 'Identificación', 'Código', 'Descripción', 'Cantidad', 'Precio Unit.', 'Descuento', 'Subtotal'], $this->headersIva($codigosIva), ['Total', 'Estado']),
                array_map(fn($r) => array_merge($est($r), [
                    date('d/m/Y', strtotime($r['fecha'])), $r['numero_documento'], $r['clave_acceso'] ?? '',
                    $r['cliente_nombre'] ?? '', $r['cliente_ident'] ?? '', $r['codigo'] ?? '', $r['descripcion'] ?? '',
                    (float) $r['cantidad'], (float) $r['precio_unitario'], (float) $r['descuento'], (float) $r['subtotal_linea'],
                ], $this->valoresIva($r, $codigosIva), [(float) $r['total_linea'], $r['estado'] ?? '']), $facturas),
                'Facturas Venta'
            );

            $recibos    = $this->repository->getDetalleRecibosVenta($idEmpresa, $filtros);
            $codigosIva = $this->codigosIvaPresentes($recibos);
            $reportService->agregarHoja($spreadsheet,
                array_merge($hEst, ['Fecha', 'N° Documento', 'Con Impuestos', 'Cliente', 'Identificación', 'Código', 'Descripción', 'Cantidad', 'Precio Unit.', 'Descuento', 'Subtotal'], $this->headersIva($codigosIva), ['Total', 'Estado']),
                array_map(fn($r) => array_merge($est($r), [
                    date('d/m/Y', strtotime($r['fecha'])), $r['numero_documento'], !empty($r['con_impuestos']) ? 'Sí' : 'No',
                    $r['cliente_nombre'] ?? '', $r['cliente_ident'] ?? '', $r['codigo'] ?? '', $r['descripcion'] ?? '',
                    (float) $r['cantidad'], (float) $r['precio_unitario'], (float) $r['descuento'], (float) $r['subtotal_linea'],
                ], $this->valoresIva($r, $codigosIva), [(float) $r['total_linea'], $r['estado'] ?? '']), $recibos),
                'Recibos Venta'
            );

            $retVenta = $this->repository->getDetalleRetencionesVenta($idEmpresa, $filtros);
            $reportService->agregarHoja($spreadsheet,
                array_merge($hEst, ['Fecha', 'N° Documento', 'Clave Acceso', 'Cliente', 'Identificación', 'Cod. Doc. Sustento', 'N° Doc. Sustento', 'Impuesto', 'Cod. Retención', 'Concepto', 'Base Imponible', '%', 'Valor Retenido', 'Total Comprobante', 'Origen']),
                array_map(fn($r) => array_merge($est($r), [
                    date('d/m/Y', strtotime($r['fecha'])), $r['numero_documento'], $r['clave_acceso'] ?? '',
                    $r['cliente_nombre'] ?? '', $r['cliente_ident'] ?? '', $r['cod_doc_sustento'] ?? '', $r['num_doc_sustento'] ?? '',
                    $r['codigo_impuesto'] ?? '', $r['codigo_retencion'] ?? '', $r['concepto'] ?? '',
                    (float) $r['base_imponible'], (float) $r['porcentaje'], (float) $r['valor_retenido'],
                    (float) $r['total_comprobante'], $r['origen'] ?? '',
                ]), $retVenta),
                'Retenciones Venta'
            );

            $notasCredito = $this->repository->getDetalleNotasCredito($idEmpresa, $filtros);
            $codigosIva   = $this->codigosIvaPresentes($notasCredito);
            $reportService->agregarHoja($spreadsheet,
                array_merge($hEst, ['Origen', 'Fecha', 'N° Documento', 'Tercero', 'Identificación', 'Doc. Modificado', 'Motivo', 'Código', 'Descripción', 'Cantidad', 'Precio Unit.', 'Subtotal'], $this->headersIva($codigosIva), ['Total', 'Estado']),
                array_map(fn($r) => array_merge($est($r), [
                    $r['origen'] ?? '', date('d/m/Y', strtotime($r['fecha'])), $r['numero_documento'],
                    $r['tercero_nombre'] ?? '', $r['tercero_ident'] ?? '', $r['doc_modificado'] ?? '', $r['motivo'] ?? '',
                    $r['codigo'] ?? '', $r['descripcion'] ?? '', (float) $r['cantidad'], (float) $r['precio_unitario'], (float) $r['subtotal_linea'],
                ], $this->valoresIva($r, $codigosIva), [(float) $r['total_linea'], $r['estado'] ?? '']), $notasCredito),
                'Notas de Credito'
            );

            $notasDebito = $this->repository->getDetalleNotasDebito($idEmpresa, $filtros);
            $codigosIva  = $this->codigosIvaPresentes($notasDebito);
            $reportService->agregarHoja($spreadsheet,
                array_merge($hEst, ['Origen', 'Fecha', 'N° Documento', 'Tercero', 'Identificación', 'Doc. Modificado', 'Descripción', 'Cantidad', 'Subtotal'], $this->headersIva($codigosIva), ['Total', 'Estado']),
                array_map(fn($r) => array_merge($est($r), [
                    $r['origen'] ?? '', date('d/m/Y', strtotime($r['fecha'])), $r['numero_documento'],
                    $r['tercero_nombre'] ?? '', $r['tercero_ident'] ?? '', $r['doc_modificado'] ?? '',
                    $r['descripcion'] ?? '', $r['cantidad'] !== null ? (float) $r['cantidad'] : '', (float) $r['subtotal_linea'],
                ], $this->valoresIva($r, $codigosIva), [(float) $r['total_linea'], $r['estado'] ?? '']), $notasDebito),
                'Notas de Debito'
            );

            $liquidaciones = $this->repository->getDetalleLiquidaciones($idEmpresa, $filtros);
            $codigosIva    = $this->codigosIvaPresentes($liquidaciones);
            $reportService->agregarHoja($spreadsheet,
                array_merge($hEst, ['Fecha', 'N° Documento', 'Proveedor', 'RUC/Cédula', 'Código', 'Descripción', 'Cantidad', 'Precio Unit.', 'Descuento', 'Subtotal'], $this->headersIva($codigosIva), ['Total', 'Estado']),
                array_map(fn($r) => array_merge($est($r), [
                    date('d/m/Y', strtotime($r['fecha'])), $r['numero_documento'], $r['proveedor_nombre'] ?? '', $r['proveedor_ruc'] ?? '',
                    $r['codigo'] ?? '', $r['descripcion'] ?? '', (float) $r['cantidad'], (float) $r['precio_unitario'], (float) $r['descuento'], (float) $r['subtotal_linea'],
                ], $this->valoresIva($r, $codigosIva), [(float) $r['total_linea'], $r['estado'] ?? '']), $liquidaciones),
                'Liquidaciones'
            );

            $reportService->descargarSpreadsheet($spreadsheet, 'ReporteConsolidado');
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltrosDesdeRequest();
        [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);
        $alcanceTxt = $this->describirAlcance($idsEmpresa, $consolidado);

        try {
            $rows  = $this->repository->getResumen($idsEmpresa, $filtros, 0);
            $stats = $this->repository->getEstadisticas($idsEmpresa, $filtros);

            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = (string) ($empresa['nombre'] ?? 'REPORTE CONSOLIDADO');

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            // Columnas y orientación según la personalización de la empresa (por defecto: horizontal).
            $vista      = $this->columnasVista();
            $horizontal = (bool) $vista['horizontal'];
            $pesos      = $consolidado ? ['__estab' => 6.0] : [];
            foreach ($vista['pdf'] as $clave => $col) {
                $pesos[$clave] = (float) $col['ancho'];
            }
            $w  = ColumnasReporte::anchosPdf($pesos);
            $pt = ColumnasReporte::fuentePdf(count($pesos), $horizontal);

            $cols = [];
            if ($consolidado) {
                $cols[] = ['lbl' => 'Estab.', 'w' => $w['__estab'], 'cls' => 'text-center',
                           'val' => static fn (array $r): string => htmlspecialchars((string) ($r['establecimiento'] ?? ''))];
            }
            foreach ($vista['pdf'] as $clave => $col) {
                $clave = (string) $clave;
                $ancho = ReportePdf::anchoPt($w[$clave], $horizontal);
                $cols[] = ['lbl' => $col['corto'], 'w' => $w[$clave], 'cls' => ColumnasReporte::claseAlinear($col['alinear']),
                           'val' => fn (array $r): string => $this->celdaPdf($clave, $r, $ancho, $pt)];
            }

            // Filtros aplicados e indicadores (cantidad y total por tipo; ventas, compras y neto).
            $desde   = (string) $filtros['fecha_desde'];
            $hasta   = (string) $filtros['fecha_hasta'];
            $periodo = ($desde === '' && $hasta === '')
                ? 'Todos'
                : ($desde !== '' ? date('d-m-Y', strtotime($desde)) : 'inicio') . ' al ' . ($hasta !== '' ? date('d-m-Y', strtotime($hasta)) : 'hoy');
            $filtrosTxt = ['Período' => $periodo, 'Anulados' => $filtros['incluir_anulados'] ? 'Incluidos' : 'Excluidos'];
            if ($filtros['buscar'] !== '') {
                $filtrosTxt['Búsqueda'] = $filtros['buscar'];
            }
            if ($alcanceTxt !== '') {
                $filtrosTxt['Alcance'] = $alcanceTxt;
            }

            $kpisTipo = [];
            foreach (ReporteConsolidadoRepository::GRUPOS as $key => $label) {
                $t = $stats['por_tipo'][$key] ?? [];
                $kpisTipo[] = [$label . ' (' . (int) ($t['n_documentos'] ?? 0) . ')', '$' . number_format((float) ($t['total_general'] ?? 0), 2)];
            }
            $kpisTotales = [
                ['Documentos', number_format((int) $stats['n_documentos'])],
                ['Total Ventas', '$' . number_format((float) $stats['total_ventas'], 2), false, '#146c43'],
                ['Total Compras', '$' . number_format((float) $stats['total_compras'], 2), false, '#b02a37'],
                ['Neto (Ventas - Compras)', '$' . number_format((float) $stats['neto'], 2), true],
            ];

            $cabecera = ReportePdf::encabezado($idEmpresa, $nombreEmpresa, 'Reporte Consolidado de Transacciones')
                      . ReportePdf::filtros($filtrosTxt)
                      . ReportePdf::indicadores($kpisTipo)
                      . ReportePdf::indicadores($kpisTotales);

            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf($horizontal ? 'L' : 'P', 'A4', 'es');
            ReportePdf::escribir($html2pdf, $pt, $cabecera, $cols, $rows, count($rows) . ' documento(s)');
            $html2pdf->output('ReporteConsolidado_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
    }

    /** Contenido HTML (escapado) de una celda del PDF; `$ancho` en puntos para cortar palabras largas. */
    private function celdaPdf(string $clave, array $r, float $ancho, float $pt): string
    {
        $txt = static fn ($v): string => ReportePdf::texto((string) $v, $ancho, $pt);
        $num = static fn (string $k): string => number_format((float) ($r[$k] ?? 0), 2);
        return match ($clave) {
            'tipo'            => $txt((ReporteConsolidadoRepository::GRUPOS[$r['tipo_documento']] ?? $r['tipo_documento'])
                                      . (!empty($r['origen']) ? ' (' . $r['origen'] . ')' : '')),
            'fecha'           => !empty($r['fecha']) ? date('d/m/Y', strtotime((string) $r['fecha'])) : '',
            'numero'          => $txt($r['numero'] ?? ''),
            'tercero'         => $txt($r['tercero_nombre'] ?? ''),
            'identificacion'  => $txt($r['tercero_ident'] ?? ''),
            'subtotal', 'iva' => $num($clave),
            'total'           => '<b>' . $num('total') . '</b>',
            'estado'          => $txt($r['estado'] ?? ''),
            default           => '',
        };
    }
}
