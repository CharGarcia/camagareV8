<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\ReporteConsolidadoRepository;

class ReporteConsolidadoController extends BaseModuloController
{
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
            'grupos'     => ReporteConsolidadoRepository::GRUPOS,
            'anios'      => $this->repository->getAniosDisponibles($idEmpresa),
            'puedeConsolidar'  => !empty($idsConsolidado),
            'establecimientos' => $establecimientos,
            'fullWidth'  => true,
            'base'       => BASE_URL,
        ]);
    }

    private function getFiltrosDesdeRequest(): array
    {
        $incluir = $_REQUEST['incluir'] ?? array_keys(ReporteConsolidadoRepository::GRUPOS);
        if (!is_array($incluir)) $incluir = [$incluir];

        return [
            'fecha_desde'      => $_REQUEST['fecha_desde'] ?? date('Y-m-01'),
            'fecha_hasta'      => $_REQUEST['fecha_hasta'] ?? date('Y-m-d'),
            'incluir'          => array_values(array_map('strval', $incluir)),
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

    public function generarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $filtros   = $this->getFiltrosDesdeRequest();
            [$idsEmpresa, $consolidado] = $this->resolverAlcance($idEmpresa, $filtros);

            $rows  = $this->repository->getResumen($idsEmpresa, $filtros);
            $stats = $this->repository->getEstadisticas($idsEmpresa, $filtros);

            ob_start();
            if (empty($rows)) {
                echo '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-file-earmark-bar-graph fs-3 d-block mb-2"></i>No se encontraron resultados.</td></tr>';
            } else {
                foreach ($rows as $r) {
                    echo $this->renderFilaHtml($r, $consolidado);
                }
            }
            $rowsHtml = ob_get_clean();

            echo json_encode(['ok' => true, 'rows' => $rowsHtml, 'stats' => $stats, 'consolidado' => $consolidado, 'establecimientos' => count($idsEmpresa)]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            error_log('ReporteConsolidado Exception: ' . $e->getMessage() . ' on line ' . $e->getLine());
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function renderFilaHtml(array $r, bool $consolidado = false): string
    {
        $etiquetas = ReporteConsolidadoRepository::GRUPOS;
        $tipo   = htmlspecialchars($etiquetas[$r['tipo_documento']] ?? $r['tipo_documento']);
        $origen = htmlspecialchars($r['origen'] ?? '');
        $subtotal = number_format((float) ($r['subtotal'] ?? 0), 2);
        $iva      = number_format((float) ($r['iva'] ?? 0), 2);
        $total    = number_format((float) ($r['total'] ?? 0), 2);
        $estado   = htmlspecialchars((string) ($r['estado'] ?? ''));

        // Consolidado por RUC: badge con el establecimiento dueño del documento (mismo estilo
        // que Cuentas por Cobrar/Pagar), con el nombre de la empresa en el tooltip.
        $badgeEst = '';
        if ($consolidado && !empty($r['establecimiento'])) {
            $tituloEst = htmlspecialchars((string) ($r['empresa_nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
            $badgeEst  = "<span class='badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 me-1 fw-normal' style='font-size:.65rem;' title='{$tituloEst}'>"
                       . htmlspecialchars((string) $r['establecimiento']) . "</span>";
        }

        $html  = '<tr class="align-middle">';
        $html .= "<td><span class='badge bg-primary bg-opacity-10 text-primary border border-primary' style='font-size:.7rem;'>$tipo</span>" . ($origen ? " <small class='text-muted'>$origen</small>" : '') . "</td>";
        $html .= "<td class='text-center'>" . date('d/m/Y', strtotime($r['fecha'] ?? '')) . "</td>";
        $html .= "<td>{$badgeEst}" . htmlspecialchars($r['numero'] ?? '') . "</td>";
        $html .= "<td><span class='fw-bold'>" . htmlspecialchars($r['tercero_nombre'] ?? '') . "</span><br><small class='text-muted'>" . htmlspecialchars($r['tercero_ident'] ?? '') . "</small></td>";
        $html .= "<td class='text-end'>$subtotal</td>";
        $html .= "<td class='text-end'>$iva</td>";
        $html .= "<td class='text-end fw-bold'>$total</td>";
        $html .= "<td class='text-center small'>$estado</td>";
        $html .= '</tr>';
        return $html;
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
                array_merge($hEst, ['Fecha', 'N° Documento', 'N° Autorización', 'Proveedor', 'RUC/Cédula', 'Código', 'Descripción', 'Cantidad', 'Precio Unit.', 'Descuento', 'Subtotal'], $this->headersIva($codigosIva), ['Total', 'Estado']),
                array_map(fn($r) => array_merge($est($r), [
                    date('d/m/Y', strtotime($r['fecha'])), $r['numero_documento'], $r['numero_autorizacion'] ?? '',
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

        $rows  = $this->repository->getResumen($idsEmpresa, $filtros);
        $stats = $this->repository->getEstadisticas($idsEmpresa, $filtros);

        try {
            $empresa       = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nombreEmpresa = $empresa['nombre'] ?? 'REPORTE CONSOLIDADO';

            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;

            $etiquetas = ReporteConsolidadoRepository::GRUPOS;

            ob_start();
            ?>
            <style>
                table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8pt; margin: 0 auto 15px auto; }
                th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; text-align: center; }
                td { border: 1px solid #ccc; padding: 4px; }
                .text-end { text-align: right; }
                .text-center { text-align: center; }
                .header { text-align: center; margin-bottom: 15px; }
                table.kpi td { border: 1px solid #ccc; padding: 5px; text-align: center; font-size: 8pt; }
                table.kpi td.label { background: #f2f2f2; font-weight: bold; }
            </style>
            <div class="header">
                <h2><?= htmlspecialchars($nombreEmpresa) ?></h2>
                <h3>Reporte Consolidado de Transacciones</h3>
                <p>Del <?= date('d-m-Y', strtotime($filtros['fecha_desde'])) ?> al <?= date('d-m-Y', strtotime($filtros['fecha_hasta'])) ?> — Generado: <?= date('d-m-Y H:i:s') ?></p>
                <?php if ($alcanceTxt !== ''): ?><p><strong>Alcance:</strong> <?= htmlspecialchars($alcanceTxt) ?></p><?php endif; ?>
            </div>
            <table class="kpi">
                <tr>
                    <?php foreach ($etiquetas as $key => $label): $t = $stats['por_tipo'][$key] ?? null; ?>
                        <td class="label"><?= htmlspecialchars($label) ?></td>
                    <?php endforeach; ?>
                    <td class="label">Total Ventas</td>
                    <td class="label">Total Compras</td>
                    <td class="label">Neto</td>
                </tr>
                <tr>
                    <?php foreach ($etiquetas as $key => $label): $t = $stats['por_tipo'][$key] ?? null; ?>
                        <td>$<?= number_format((float) ($t['total_general'] ?? 0), 2) ?><br><small><?= (int) ($t['n_documentos'] ?? 0) ?> doc.</small></td>
                    <?php endforeach; ?>
                    <td>$<?= number_format((float) $stats['total_ventas'], 2) ?></td>
                    <td>$<?= number_format((float) $stats['total_compras'], 2) ?></td>
                    <td>$<?= number_format((float) $stats['neto'], 2) ?></td>
                </tr>
            </table>
            <table>
                <thead>
                    <tr><?php if ($consolidado): ?><th>Estab.</th><?php endif; ?><th>Tipo</th><th>Fecha</th><th>Número</th><th>Tercero</th><th>Identificación</th><th>Subtotal</th><th>IVA</th><th>Total</th><th>Estado</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <?php if ($consolidado): ?><td class="text-center"><?= htmlspecialchars((string) ($r['establecimiento'] ?? '')) ?></td><?php endif; ?>
                            <td><?= htmlspecialchars($etiquetas[$r['tipo_documento']] ?? $r['tipo_documento']) ?><?= !empty($r['origen']) ? ' (' . htmlspecialchars($r['origen']) . ')' : '' ?></td>
                            <td class="text-center"><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
                            <td><?= htmlspecialchars($r['numero'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['tercero_nombre'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['tercero_ident'] ?? '') ?></td>
                            <td class="text-end"><?= number_format((float) ($r['subtotal'] ?? 0), 2) ?></td>
                            <td class="text-end"><?= number_format((float) ($r['iva'] ?? 0), 2) ?></td>
                            <td class="text-end"><strong><?= number_format((float) ($r['total'] ?? 0), 2) ?></strong></td>
                            <td class="text-center"><?= htmlspecialchars((string) ($r['estado'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background-color: #e9ecef;">
                        <th colspan="<?= $consolidado ? 8 : 7 ?>" class="text-center" style="font-size: 9pt;">TOTAL GENERAL:</th>
                        <th class="text-end" style="color:#dc3545;font-weight:bold;">$<?= number_format((float) $stats['total_general'], 2) ?></th>
                        <th>-</th>
                    </tr>
                </tfoot>
            </table>
            <?php
            $html = ob_get_clean();
            $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $html2pdf->writeHTML($html);
            $html2pdf->output('ReporteConsolidado_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            echo 'Error al generar PDF: ' . $e->getMessage();
        }
    }
}
