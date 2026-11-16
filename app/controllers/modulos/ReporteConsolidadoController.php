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

    /**
     * Excel del reporte. `excel_modo=DOCUMENTO` → una fila por documento en cada hoja; si no,
     * una fila por ítem (detallado). Solo se crean las hojas que tienen documentos y al final
     * va siempre la hoja «Resumen» (cantidad, bases e IVA por tarifa y totales por tipo).
     */
    public function exportExcel(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        $filtros   = $this->getFiltrosDesdeRequest();
        [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);
        $porDocumento = strtoupper((string) ($_REQUEST['excel_modo'] ?? '')) === 'DOCUMENTO';

        // Consolidado: cada hoja lleva primero la columna "Estab." con el establecimiento
        // dueño de la línea; sin consolidado, ambas piezas quedan vacías y nada cambia.
        $hEst = $consolidado ? ['Estab.'] : [];
        $est  = fn (array $r): array => $consolidado ? [(string) ($r['establecimiento'] ?? '')] : [];

        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa);
            $nombreEmpresa = $empresa['nombre'] ?? '';
            if ($consolidado) {
                $nombreEmpresa .= ' — ' . $this->describirAlcance($idsEmpresa, true);
            }

            $datos = $this->datosExcel($idsEmpresa, $filtros);
            $hojas = $porDocumento ? $this->hojasPorDocumento($datos, $hEst, $est) : $this->hojasDetalle($datos, $hEst, $est);
            $hojas = array_values(array_filter($hojas, static fn (array $h): bool => !empty($h['filas'])));
            $hojas[] = $this->hojaResumen($datos);

            $reportService = new \App\Services\ReportService();
            $spreadsheet   = null;
            foreach ($hojas as $h) {
                if ($spreadsheet === null) {
                    $spreadsheet = $reportService->construirSpreadsheet($h['cab'], $h['filas'], $h['titulo'], $nombreEmpresa);
                } else {
                    $reportService->agregarHoja($spreadsheet, $h['cab'], $h['filas'], $h['titulo']);
                }
            }
            $this->resaltarResumen($spreadsheet);

            $reportService->descargarSpreadsheet($spreadsheet, $porDocumento ? 'ReporteConsolidado_PorDocumento' : 'ReporteConsolidado');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo 'Error al generar Excel: ' . $e->getMessage();
        }
    }

    /** Detalle línea por línea de las 8 fuentes (las retenciones ya separadas por impuesto). */
    private function datosExcel(array $idsEmpresa, array $filtros): array
    {
        $r = $this->repository;
        return [
            'compras'       => $r->getDetalleCompras($idsEmpresa, $filtros),
            'ret_compra'    => $this->separarRetencion($r->getDetalleRetencionesCompra($idsEmpresa, $filtros)),
            'facturas'      => $r->getDetalleFacturasVenta($idsEmpresa, $filtros),
            'recibos'       => $r->getDetalleRecibosVenta($idsEmpresa, $filtros),
            'ret_venta'     => $this->separarRetencion($r->getDetalleRetencionesVenta($idsEmpresa, $filtros)),
            'nc'            => $r->getDetalleNotasCredito($idsEmpresa, $filtros),
            'nd'            => $r->getDetalleNotasDebito($idsEmpresa, $filtros),
            'liquidaciones' => $r->getDetalleLiquidaciones($idsEmpresa, $filtros),
        ];
    }

    /** Hojas del Excel detallado: una fila por ítem (o por impuesto, en las retenciones). */
    private function hojasDetalle(array $d, array $hEst, callable $est): array
    {
        $fecha = static fn (array $r): string => date('d/m/Y', strtotime((string) $r['fecha']));
        $hojas = [];

        $iva = $this->codigosIvaPresentes($d['compras']);
        $hojas[] = ['titulo' => 'Compras',
            'cab' => array_merge($hEst, ['Fecha', 'Tipo Comprobante', 'N° Documento', 'N° Autorización', 'Proveedor', 'RUC/Cédula', 'Código', 'Descripción', 'Cantidad', 'Precio Unit.', 'Descuento', 'Subtotal'], $this->headersIva($iva), ['Total', 'Estado']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $fecha($r), $r['tipo_comprobante_nombre'] ?? '', $r['numero_documento'], $r['numero_autorizacion'] ?? '',
                $r['proveedor_nombre'] ?? '', $r['proveedor_ruc'] ?? '', $r['codigo'] ?? '', $r['descripcion'] ?? '',
                (float) $r['cantidad'], (float) $r['precio_unitario'], (float) $r['descuento'], (float) $r['subtotal_linea'],
            ], $this->valoresIva($r, $iva), [(float) $r['total_linea'], $r['estado'] ?? '']), $d['compras'])];

        $hojas[] = ['titulo' => 'Retenciones Compra',
            'cab' => array_merge($hEst, ['Fecha', 'N° Documento', 'Clave Acceso', 'Proveedor', 'RUC/Cédula', 'Cod. Doc. Sustento', 'N° Doc. Sustento', 'Impuesto', 'Cod. Retención', 'Concepto', 'Base Imponible', '%', 'Valor Retenido', 'Total Comprobante', 'Estado']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $fecha($r), $r['numero_documento'], $r['clave_acceso'] ?? '',
                $r['proveedor_nombre'] ?? '', $r['proveedor_ruc'] ?? '', $r['cod_doc_sustento'] ?? '', $r['num_doc_sustento'] ?? '',
                $r['codigo_impuesto'] ?? '', $r['codigo_retencion'] ?? '', $r['concepto'] ?? '',
                (float) $r['base_imponible'], (float) $r['porcentaje'], (float) $r['valor_retenido'],
                (float) $r['total_retenido'], $r['estado'] ?? '',
            ]), $d['ret_compra'])];

        $iva = $this->codigosIvaPresentes($d['facturas']);
        $hojas[] = ['titulo' => 'Facturas Venta',
            'cab' => array_merge($hEst, ['Fecha', 'N° Documento', 'Clave Acceso', 'Cliente', 'Identificación', 'Código', 'Descripción', 'Cantidad', 'Precio Unit.', 'Descuento', 'Subtotal'], $this->headersIva($iva), ['Total', 'Estado']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $fecha($r), $r['numero_documento'], $r['clave_acceso'] ?? '',
                $r['cliente_nombre'] ?? '', $r['cliente_ident'] ?? '', $r['codigo'] ?? '', $r['descripcion'] ?? '',
                (float) $r['cantidad'], (float) $r['precio_unitario'], (float) $r['descuento'], (float) $r['subtotal_linea'],
            ], $this->valoresIva($r, $iva), [(float) $r['total_linea'], $r['estado'] ?? '']), $d['facturas'])];

        $iva = $this->codigosIvaPresentes($d['recibos']);
        $hojas[] = ['titulo' => 'Recibos Venta',
            'cab' => array_merge($hEst, ['Fecha', 'N° Documento', 'Con Impuestos', 'Cliente', 'Identificación', 'Código', 'Descripción', 'Cantidad', 'Precio Unit.', 'Descuento', 'Subtotal'], $this->headersIva($iva), ['Total', 'Estado']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $fecha($r), $r['numero_documento'], !empty($r['con_impuestos']) ? 'Sí' : 'No',
                $r['cliente_nombre'] ?? '', $r['cliente_ident'] ?? '', $r['codigo'] ?? '', $r['descripcion'] ?? '',
                (float) $r['cantidad'], (float) $r['precio_unitario'], (float) $r['descuento'], (float) $r['subtotal_linea'],
            ], $this->valoresIva($r, $iva), [(float) $r['total_linea'], $r['estado'] ?? '']), $d['recibos'])];

        $hojas[] = ['titulo' => 'Retenciones Venta',
            'cab' => array_merge($hEst, ['Fecha', 'N° Documento', 'Clave Acceso', 'Cliente', 'Identificación', 'Cod. Doc. Sustento', 'N° Doc. Sustento', 'Impuesto', 'Cod. Retención', 'Concepto', 'Base Imponible', '%', 'Valor Retenido', 'Total Comprobante', 'Origen']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $fecha($r), $r['numero_documento'], $r['clave_acceso'] ?? '',
                $r['cliente_nombre'] ?? '', $r['cliente_ident'] ?? '', $r['cod_doc_sustento'] ?? '', $r['num_doc_sustento'] ?? '',
                $r['codigo_impuesto'] ?? '', $r['codigo_retencion'] ?? '', $r['concepto'] ?? '',
                (float) $r['base_imponible'], (float) $r['porcentaje'], (float) $r['valor_retenido'],
                (float) $r['total_comprobante'], $r['origen'] ?? '',
            ]), $d['ret_venta'])];

        $iva = $this->codigosIvaPresentes($d['nc']);
        $hojas[] = ['titulo' => 'Notas de Credito',
            'cab' => array_merge($hEst, ['Origen', 'Fecha', 'N° Documento', 'Tercero', 'Identificación', 'Doc. Modificado', 'Motivo', 'Código', 'Descripción', 'Cantidad', 'Precio Unit.', 'Subtotal'], $this->headersIva($iva), ['Total', 'Estado']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $r['origen'] ?? '', $fecha($r), $r['numero_documento'],
                $r['tercero_nombre'] ?? '', $r['tercero_ident'] ?? '', $r['doc_modificado'] ?? '', $r['motivo'] ?? '',
                $r['codigo'] ?? '', $r['descripcion'] ?? '', (float) $r['cantidad'], (float) $r['precio_unitario'], (float) $r['subtotal_linea'],
            ], $this->valoresIva($r, $iva), [(float) $r['total_linea'], $r['estado'] ?? '']), $d['nc'])];

        $iva = $this->codigosIvaPresentes($d['nd']);
        $hojas[] = ['titulo' => 'Notas de Debito',
            'cab' => array_merge($hEst, ['Origen', 'Fecha', 'N° Documento', 'Tercero', 'Identificación', 'Doc. Modificado', 'Descripción', 'Cantidad', 'Subtotal'], $this->headersIva($iva), ['Total', 'Estado']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $r['origen'] ?? '', $fecha($r), $r['numero_documento'],
                $r['tercero_nombre'] ?? '', $r['tercero_ident'] ?? '', $r['doc_modificado'] ?? '',
                $r['descripcion'] ?? '', $r['cantidad'] !== null ? (float) $r['cantidad'] : '', (float) $r['subtotal_linea'],
            ], $this->valoresIva($r, $iva), [(float) $r['total_linea'], $r['estado'] ?? '']), $d['nd'])];

        $iva = $this->codigosIvaPresentes($d['liquidaciones']);
        $hojas[] = ['titulo' => 'Liquidaciones',
            'cab' => array_merge($hEst, ['Fecha', 'N° Documento', 'Proveedor', 'RUC/Cédula', 'Código', 'Descripción', 'Cantidad', 'Precio Unit.', 'Descuento', 'Subtotal'], $this->headersIva($iva), ['Total', 'Estado']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $fecha($r), $r['numero_documento'], $r['proveedor_nombre'] ?? '', $r['proveedor_ruc'] ?? '',
                $r['codigo'] ?? '', $r['descripcion'] ?? '', (float) $r['cantidad'], (float) $r['precio_unitario'], (float) $r['descuento'], (float) $r['subtotal_linea'],
            ], $this->valoresIva($r, $iva), [(float) $r['total_linea'], $r['estado'] ?? '']), $d['liquidaciones'])];

        return $hojas;
    }

    /** Campos que se suman al agrupar las líneas de cada fuente en documentos. */
    private const SUMAR = [
        'compras'       => ['subtotal_linea', 'total_linea', 'descuento'],
        'facturas'      => ['subtotal_linea', 'total_linea', 'descuento'],
        'recibos'       => ['subtotal_linea', 'total_linea', 'descuento'],
        'liquidaciones' => ['subtotal_linea', 'total_linea', 'descuento'],
        'nc'            => ['subtotal_linea', 'total_linea'],
        'nd'            => ['subtotal_linea', 'total_linea'],
        'ret_compra'    => ['base_renta', 'ret_renta', 'base_iva', 'ret_iva', 'ret_otros'],
        'ret_venta'     => ['base_renta', 'ret_renta', 'base_iva', 'ret_iva', 'ret_otros'],
    ];

    /** Las 8 fuentes agrupadas por documento (una fila por documento). */
    private function agruparTodo(array $d): array
    {
        $out = [];
        foreach (self::SUMAR as $clave => $sumar) {
            $out[$clave] = $this->agruparPorDocumento($d[$clave], $sumar);
        }
        return $out;
    }

    /** Hojas del Excel «por documento»: una fila por documento, sin el detalle por ítem. */
    private function hojasPorDocumento(array $d, array $hEst, callable $est): array
    {
        $g     = $this->agruparTodo($d);
        $fecha = static fn (array $r): string => date('d/m/Y', strtotime((string) $r['fecha']));
        $retHead = ['Base Renta', 'Ret. Renta', 'Base IVA', 'Ret. IVA', 'Otras Ret. (ISD)'];
        $retVals = static fn (array $r): array => [$r['base_renta'], $r['ret_renta'], $r['base_iva'], $r['ret_iva'], $r['ret_otros']];
        $hojas   = [];

        $iva = $this->codigosIvaPresentes($g['compras']);
        $hojas[] = ['titulo' => 'Compras',
            'cab' => array_merge($hEst, ['Fecha', 'Tipo Comprobante', 'N° Documento', 'N° Autorización', 'Proveedor', 'RUC/Cédula', 'Ítems', 'Descuento', 'Subtotal'], $this->headersIva($iva), ['Total', 'Estado']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $fecha($r), $r['tipo_comprobante_nombre'] ?? '', $r['numero_documento'], $r['numero_autorizacion'] ?? '',
                $r['proveedor_nombre'] ?? '', $r['proveedor_ruc'] ?? '', (int) $r['n_items'], $r['descuento'], $r['subtotal_linea'],
            ], $this->valoresIva($r, $iva), [$r['total_linea'], $r['estado'] ?? '']), $g['compras'])];

        $hojas[] = ['titulo' => 'Retenciones Compra',
            'cab' => array_merge($hEst, ['Fecha', 'N° Documento', 'Clave Acceso', 'Proveedor', 'RUC/Cédula', 'Cod. Doc. Sustento', 'N° Doc. Sustento', 'Líneas'], $retHead, ['Total Retenido', 'Estado']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $fecha($r), $r['numero_documento'], $r['clave_acceso'] ?? '', $r['proveedor_nombre'] ?? '', $r['proveedor_ruc'] ?? '',
                $r['cod_doc_sustento'] ?? '', $r['num_doc_sustento'] ?? '', (int) $r['n_items'],
            ], $retVals($r), [(float) $r['total_retenido'], $r['estado'] ?? '']), $g['ret_compra'])];

        $iva = $this->codigosIvaPresentes($g['facturas']);
        $hojas[] = ['titulo' => 'Facturas Venta',
            'cab' => array_merge($hEst, ['Fecha', 'N° Documento', 'Clave Acceso', 'Cliente', 'Identificación', 'Ítems', 'Descuento', 'Subtotal'], $this->headersIva($iva), ['Total', 'Estado']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $fecha($r), $r['numero_documento'], $r['clave_acceso'] ?? '', $r['cliente_nombre'] ?? '', $r['cliente_ident'] ?? '',
                (int) $r['n_items'], $r['descuento'], $r['subtotal_linea'],
            ], $this->valoresIva($r, $iva), [$r['total_linea'], $r['estado'] ?? '']), $g['facturas'])];

        $iva = $this->codigosIvaPresentes($g['recibos']);
        $hojas[] = ['titulo' => 'Recibos Venta',
            'cab' => array_merge($hEst, ['Fecha', 'N° Documento', 'Con Impuestos', 'Cliente', 'Identificación', 'Ítems', 'Descuento', 'Subtotal'], $this->headersIva($iva), ['Total', 'Estado']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $fecha($r), $r['numero_documento'], !empty($r['con_impuestos']) ? 'Sí' : 'No', $r['cliente_nombre'] ?? '', $r['cliente_ident'] ?? '',
                (int) $r['n_items'], $r['descuento'], $r['subtotal_linea'],
            ], $this->valoresIva($r, $iva), [$r['total_linea'], $r['estado'] ?? '']), $g['recibos'])];

        $hojas[] = ['titulo' => 'Retenciones Venta',
            'cab' => array_merge($hEst, ['Fecha', 'N° Documento', 'Clave Acceso', 'Cliente', 'Identificación', 'Cod. Doc. Sustento', 'N° Doc. Sustento', 'Líneas'], $retHead, ['Total Comprobante', 'Origen']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $fecha($r), $r['numero_documento'], $r['clave_acceso'] ?? '', $r['cliente_nombre'] ?? '', $r['cliente_ident'] ?? '',
                $r['cod_doc_sustento'] ?? '', $r['num_doc_sustento'] ?? '', (int) $r['n_items'],
            ], $retVals($r), [(float) $r['total_comprobante'], $r['origen'] ?? '']), $g['ret_venta'])];

        $iva = $this->codigosIvaPresentes($g['nc']);
        $hojas[] = ['titulo' => 'Notas de Credito',
            'cab' => array_merge($hEst, ['Origen', 'Fecha', 'N° Documento', 'Tercero', 'Identificación', 'Doc. Modificado', 'Motivo', 'Ítems', 'Subtotal'], $this->headersIva($iva), ['Total', 'Estado']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $r['origen'] ?? '', $fecha($r), $r['numero_documento'], $r['tercero_nombre'] ?? '', $r['tercero_ident'] ?? '',
                $r['doc_modificado'] ?? '', $r['motivo'] ?? '', (int) $r['n_items'], $r['subtotal_linea'],
            ], $this->valoresIva($r, $iva), [$r['total_linea'], $r['estado'] ?? '']), $g['nc'])];

        $iva = $this->codigosIvaPresentes($g['nd']);
        $hojas[] = ['titulo' => 'Notas de Debito',
            'cab' => array_merge($hEst, ['Origen', 'Fecha', 'N° Documento', 'Tercero', 'Identificación', 'Doc. Modificado', 'Ítems', 'Subtotal'], $this->headersIva($iva), ['Total', 'Estado']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $r['origen'] ?? '', $fecha($r), $r['numero_documento'], $r['tercero_nombre'] ?? '', $r['tercero_ident'] ?? '',
                $r['doc_modificado'] ?? '', (int) $r['n_items'], $r['subtotal_linea'],
            ], $this->valoresIva($r, $iva), [$r['total_linea'], $r['estado'] ?? '']), $g['nd'])];

        $iva = $this->codigosIvaPresentes($g['liquidaciones']);
        $hojas[] = ['titulo' => 'Liquidaciones',
            'cab' => array_merge($hEst, ['Fecha', 'N° Documento', 'Proveedor', 'RUC/Cédula', 'Ítems', 'Descuento', 'Subtotal'], $this->headersIva($iva), ['Total', 'Estado']),
            'filas' => array_map(fn ($r) => array_merge($est($r), [
                $fecha($r), $r['numero_documento'], $r['proveedor_nombre'] ?? '', $r['proveedor_ruc'] ?? '',
                (int) $r['n_items'], $r['descuento'], $r['subtotal_linea'],
            ], $this->valoresIva($r, $iva), [$r['total_linea'], $r['estado'] ?? '']), $g['liquidaciones'])];

        return $hojas;
    }

    /** Rótulos de la hoja Resumen que van resaltados (totales y cabecera de retenciones). */
    private const RESUMEN_TOTAL_PREFIJO = 'TOTAL ';
    private const RESUMEN_CAB_RETENCIONES = 'Retenciones';

    /**
     * Hoja «Resumen»: por cada tipo de documento (y subtipo: compras por tipo de comprobante,
     * NC/ND emitidas y recibidas) cuántos documentos e ítems hay, descuento, subtotal, base e
     * IVA de cada tarifa, total IVA y total; luego los totales de ventas y compras, y debajo
     * las retenciones (renta, IVA y otras) de compra y de venta.
     */
    private function hojaResumen(array $d): array
    {
        $g = $this->agruparTodo($d);

        // Conjuntos de documentos, en el orden en que se muestran.
        $conjuntos = [];
        $porTipo   = [];
        foreach ($g['compras'] as $r) {
            $porTipo[(string) ($r['tipo_comprobante_nombre'] ?? 'Compra')][] = $r;
        }
        foreach ($porTipo as $tipo => $docs) {
            $conjuntos['Compras: ' . $tipo] = ['docs' => $docs, 'grupo' => 'compras'];
        }
        $conjuntos['Liquidaciones de Compra']        = ['docs' => $g['liquidaciones'], 'grupo' => 'compras'];
        $conjuntos['Facturas de Venta']              = ['docs' => $g['facturas'], 'grupo' => 'ventas'];
        $conjuntos['Recibos de Venta']               = ['docs' => $g['recibos'], 'grupo' => 'ventas'];
        $origen = static fn (array $docs, string $o): array => array_values(array_filter($docs, static fn ($r) => ($r['origen'] ?? '') === $o));
        $conjuntos['Notas de Crédito emitidas (venta)']  = ['docs' => $origen($g['nc'], 'VENTA'), 'grupo' => null];
        $conjuntos['Notas de Crédito recibidas (compra)'] = ['docs' => $origen($g['nc'], 'COMPRA'), 'grupo' => null];
        $conjuntos['Notas de Débito emitidas (venta)']    = ['docs' => $origen($g['nd'], 'VENTA'), 'grupo' => null];
        $conjuntos['Notas de Débito recibidas (compra)']  = ['docs' => $origen($g['nd'], 'COMPRA'), 'grupo' => null];
        $conjuntos = array_filter($conjuntos, static fn ($c) => !empty($c['docs']));

        // Totales de cada conjunto.
        $filas = [];
        foreach ($conjuntos as $nombre => $c) {
            $t = ['nombre' => $nombre, 'grupo' => $c['grupo'], 'docs' => count($c['docs']), 'items' => 0,
                  'descuento' => 0.0, 'subtotal' => 0.0, 'total' => 0.0, 'base' => [], 'iva' => []];
            foreach ($c['docs'] as $r) {
                $t['items']     += (int) $r['n_items'];
                $t['descuento'] += (float) ($r['descuento'] ?? 0);
                $t['subtotal']  += (float) $r['subtotal_linea'];
                $t['total']     += (float) $r['total_linea'];
                foreach (['base' => 'base_desglose', 'iva' => 'iva_desglose'] as $k => $campo) {
                    foreach (json_decode($r[$campo] ?? '{}', true) ?: [] as $cod => $v) {
                        $t[$k][(string) $cod] = ($t[$k][(string) $cod] ?? 0) + (float) $v;
                    }
                }
            }
            $filas[] = $t;
        }

        // Tarifas presentes y columnas: Base de cada tarifa y, si alguna tiene IVA, su IVA al lado.
        $codigos = [];
        $ivaCod  = [];
        foreach ($filas as $t) {
            foreach (array_keys($t['base'] + $t['iva']) as $cod) {
                $codigos[$cod] = true;
                $ivaCod[$cod]  = ($ivaCod[$cod] ?? 0) + abs($t['iva'][$cod] ?? 0);
            }
        }
        $codigos = array_keys($codigos);
        sort($codigos, SORT_NUMERIC);
        $etq    = $this->repository->getEtiquetasIva();
        $sinTarifa = static fn (array $t): float => round($t['subtotal'] - array_sum($t['base']), 2);
        $conSinTarifa = (bool) array_filter($filas, static fn ($t) => abs($sinTarifa($t)) >= 0.01);

        $cab = ['Tipo de documento', 'Documentos', 'Ítems', 'Descuento', 'Subtotal'];
        foreach ($codigos as $cod) {
            $nom   = $etq[$cod] ?? "cód. $cod";
            $cab[] = 'Base ' . $nom;
            if ($ivaCod[$cod] >= 0.005) {
                $cab[] = 'IVA ' . $nom;
            }
        }
        if ($conSinTarifa) {
            $cab[] = 'Subtotal sin tarifa de IVA';
        }
        $cab[] = 'Total IVA';
        $cab[] = 'Total';

        $fila = function (array $t) use ($codigos, $ivaCod, $conSinTarifa, $sinTarifa): array {
            $f = [$t['nombre'], $t['docs'], $t['items'], round($t['descuento'], 2), round($t['subtotal'], 2)];
            foreach ($codigos as $cod) {
                $f[] = round($t['base'][$cod] ?? 0, 2);
                if ($ivaCod[$cod] >= 0.005) {
                    $f[] = round($t['iva'][$cod] ?? 0, 2);
                }
            }
            if ($conSinTarifa) {
                $f[] = $sinTarifa($t);
            }
            $f[] = round(array_sum($t['iva']), 2);
            $f[] = round($t['total'], 2);
            return $f;
        };

        $datos = array_map($fila, $filas);
        if (!$datos) {
            $datos[] = ['Sin documentos en el período'];
        }

        // Totales de ventas (facturas + recibos) y de compras (compras + liquidaciones).
        foreach (['ventas' => 'TOTAL VENTAS (Facturas + Recibos)', 'compras' => 'TOTAL COMPRAS (Compras + Liquidaciones)'] as $grupo => $rotulo) {
            $del = array_filter($filas, static fn ($t) => $t['grupo'] === $grupo);
            if (!$del) {
                continue;
            }
            $suma = ['nombre' => $rotulo, 'docs' => 0, 'items' => 0, 'descuento' => 0.0, 'subtotal' => 0.0, 'total' => 0.0, 'base' => [], 'iva' => []];
            foreach ($del as $t) {
                foreach (['docs', 'items', 'descuento', 'subtotal', 'total'] as $k) {
                    $suma[$k] += $t[$k];
                }
                foreach (['base', 'iva'] as $k) {
                    foreach ($t[$k] as $cod => $v) {
                        $suma[$k][$cod] = ($suma[$k][$cod] ?? 0) + $v;
                    }
                }
            }
            $datos[] = $fila($suma);
        }

        // Retenciones de compra y de venta.
        $ret = [];
        foreach (['ret_compra' => 'Retenciones de Compra (practicadas a proveedores)', 'ret_venta' => 'Retenciones de Venta (recibidas de clientes)'] as $clave => $rotulo) {
            if (!$g[$clave]) {
                continue;
            }
            $s = ['base_renta' => 0.0, 'ret_renta' => 0.0, 'base_iva' => 0.0, 'ret_iva' => 0.0, 'ret_otros' => 0.0, 'lineas' => 0];
            foreach ($g[$clave] as $r) {
                $s['lineas'] += (int) $r['n_items'];
                foreach (['base_renta', 'ret_renta', 'base_iva', 'ret_iva', 'ret_otros'] as $k) {
                    $s[$k] += (float) $r[$k];
                }
            }
            $ret[] = [$rotulo, count($g[$clave]), $s['lineas'], round($s['base_renta'], 2), round($s['ret_renta'], 2),
                      round($s['base_iva'], 2), round($s['ret_iva'], 2), round($s['ret_otros'], 2),
                      round($s['ret_renta'] + $s['ret_iva'] + $s['ret_otros'], 2)];
        }
        if ($ret) {
            $datos[] = [];
            $datos[] = [self::RESUMEN_CAB_RETENCIONES, 'Comprobantes', 'Líneas', 'Base Renta', 'Ret. Renta', 'Base IVA', 'Ret. IVA', 'Otras Ret. (ISD)', 'Total Retenido'];
            array_push($datos, ...$ret);
        }

        return ['titulo' => 'Resumen', 'cab' => $cab, 'filas' => $datos];
    }

    /** Negrita en las filas de totales y en la cabecera de retenciones de la hoja Resumen. */
    private function resaltarResumen(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->getSheetByName('Resumen');
        if ($sheet === null) {
            return;
        }
        $ultimaCol = $sheet->getHighestColumn();
        foreach ($sheet->getRowIterator() as $row) {
            $n   = $row->getRowIndex();
            $val = (string) $sheet->getCell('A' . $n)->getValue();
            if (str_starts_with($val, self::RESUMEN_TOTAL_PREFIJO)) {
                $sheet->getStyle("A{$n}:{$ultimaCol}{$n}")->getFont()->setBold(true);
                $sheet->getStyle("A{$n}:{$ultimaCol}{$n}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E9ECEF');
            } elseif ($val === self::RESUMEN_CAB_RETENCIONES) {
                $sheet->getStyle("A{$n}:I{$n}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A{$n}:I{$n}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
            }
        }
        $sheet->getColumnDimension('A')->setAutoSize(true);
    }

    /**
     * Junta las líneas de detalle de cada documento en una sola fila: los campos de texto se
     * toman de la primera línea, los de `$sumar` se suman, los desgloses de IVA y de base se
     * suman por tarifa y `n_items` cuenta las líneas. La clave es origen + id de la cabecera
     * (las hojas de NC/ND juntan venta y compra, cuyos ids pueden repetirse). Conserva el orden.
     */
    private function agruparPorDocumento(array $rows, array $sumar): array
    {
        $json = ['iva_desglose', 'base_desglose'];
        $out  = [];
        foreach ($rows as $r) {
            $k = ($r['origen'] ?? '') . '|' . ($r['id_documento'] ?? '');
            if (!isset($out[$k])) {
                $base = $r;
                $base['n_items'] = 0;
                foreach ($sumar as $c) {
                    $base[$c] = 0.0;
                }
                foreach ($json as $c) {
                    $base['__' . $c] = [];
                }
                $out[$k] = $base;
            }
            $out[$k]['n_items']++;
            foreach ($sumar as $c) {
                $out[$k][$c] += (float) ($r[$c] ?? 0);
            }
            foreach ($json as $c) {
                foreach (json_decode($r[$c] ?? '{}', true) ?: [] as $cod => $v) {
                    $out[$k]['__' . $c][(string) $cod] = ($out[$k]['__' . $c][(string) $cod] ?? 0) + (float) $v;
                }
            }
        }
        foreach ($out as &$doc) {
            foreach ($json as $c) {
                $doc[$c] = json_encode($doc['__' . $c], JSON_FORCE_OBJECT);
                unset($doc['__' . $c]);
            }
            foreach ($sumar as $c) {
                $doc[$c] = round($doc[$c], 2);
            }
        }
        unset($doc);
        return array_values($out);
    }

    /**
     * Reparte el valor de cada línea de retención por impuesto (renta / IVA / otras, p. ej.
     * ISD) para sumarlas por comprobante. El código viene como '1'/'2' o 'RENTA'/'IVA'.
     */
    private function separarRetencion(array $rows): array
    {
        foreach ($rows as &$r) {
            $imp  = strtoupper(trim((string) ($r['codigo_impuesto'] ?? '')));
            $tipo = in_array($imp, ['1', 'RENTA'], true) ? 'renta' : (in_array($imp, ['2', 'IVA'], true) ? 'iva' : 'otros');
            foreach (['renta', 'iva', 'otros'] as $t) {
                $r["base_{$t}"] = $t === $tipo ? (float) $r['base_imponible'] : 0.0;
                $r["ret_{$t}"]  = $t === $tipo ? (float) $r['valor_retenido'] : 0.0;
            }
        }
        unset($r);
        return $rows;
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
