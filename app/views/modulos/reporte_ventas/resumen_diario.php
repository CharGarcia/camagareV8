<?php
/**
 * Resumen diario del Reporte de Ventas — mismo contenido en tres salidas:
 *  - pantalla: dentro del modal del módulo (Bootstrap, tres columnas por día).
 *  - pdf:      Html2Pdf (sin flex ni float: tablas con el ancho en cada celda).
 *  - correo:   cuerpo del correo (estilos en línea, secciones una debajo de otra
 *              para que se lea en el celular).
 *
 * Cada bloque (un día, y el total del período si hay más de uno) trae las tres
 * secciones de la tirilla del Reporte Restaurante: Documentos, Detalle de impuestos
 * y Cobro por forma de pago. Ver ReporteVentasResumenDiarioService.
 *
 * @var string $modo        pantalla | pdf | correo
 * @var array  $empresa     Ficha de la empresa (nombre, ruc)
 * @var array  $resumen     ['dias' => [...bloques], 'total' => ?bloque]
 * @var array  $filtrosTxt  Filtros aplicados, etiqueta => valor
 * @var array  $pdf         Solo en modo pdf: css, encabezado (con logo) y filtros, del controlador
 * @var string $realizadoPor Usuario que genera el resumen (firma "Realizado por")
 */
$e   = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmt = static fn ($v): string => ((float) $v < 0 ? '-$' : '$') . number_format(abs((float) $v), 2);

/**
 * Filas de cada sección: [etiqueta, valor, estilo] con estilo '' | 'bold' | 'sub'.
 * Se arman una vez y se pintan según el modo.
 */
$secciones = static function (array $b) use ($fmt): array {
    $docs = [];
    foreach ($b['documentos'] as $d) {
        $docs[] = [$d['etiqueta'] . ' (' . $d['cantidad'] . ')', $fmt($d['total']), ''];
    }
    if (!$docs) {
        $docs[] = ['Sin documentos válidos', '', 'sub'];
    }
    if ($b['anulados'] > 0) {
        $docs[] = ['Anulados: ' . $b['anulados'] . ' (no suman)', '', 'sub'];
    }
    $docs[] = ['TOTAL NETO', $fmt($b['total_neto']), 'bold'];
    $docs[] = ['Total vendido sin impuestos', $fmt($b['total_vendido']), 'sub'];

    $imp = [];
    foreach ($b['impuestos']['lineas'] as $l) {
        $imp[] = [$l['etiqueta'], $fmt($l['valor']), ''];
    }
    $imp[] = ['TOTAL CON IMPUESTOS', $fmt($b['impuestos']['total']), 'bold'];

    $c   = $b['cobro'];
    $cob = [];
    foreach ($c['formas'] as $f) {
        $cob[] = [$f['concepto'] . ' (' . $f['detalle'] . ')', $fmt($f['total']), ''];
    }
    if ($c['retenido'] != 0)  { $cob[] = ['Retenciones', $fmt($c['retenido']), '']; }
    if ($c['nc'] != 0)        { $cob[] = ['Notas de crédito aplicadas', $fmt($c['nc']), '']; }
    if ($c['pendiente'] != 0) { $cob[] = ['Pendiente de cobro (crédito)', $fmt($c['pendiente']), '']; }
    if ($c['excedente'] != 0) { $cob[] = ['Cobrado de más', $fmt($c['excedente']), 'sub']; }
    if (!$cob) {
        $cob[] = ['Sin facturas ni recibos', '', 'sub'];
    }
    $cob[] = ['TOTAL FACT. Y RECIBOS', $fmt($c['total']), 'bold'];

    return [
        ['DOCUMENTOS', $docs],
        ['DETALLE DE IMPUESTOS', $imp],
        ['COBRO POR FORMA DE PAGO', $cob],
    ];
};

/** Tabla de una sección con estilos en línea (sirve para PDF y correo). */
$tablaInline = static function (string $titulo, array $filas, string $ancho) use ($e): string {
    $html = "<table style='width:{$ancho};border-collapse:collapse;font-family:Arial,sans-serif;font-size:8.5pt;'>"
          . "<tr><td colspan='2' style='width:100%;background:#e3e9f0;border:1px solid #9aa7b4;padding:3px 5px;font-weight:bold;color:#1b2a3a;'>"
          . $e($titulo) . '</td></tr>';
    foreach ($filas as [$lbl, $val, $estilo]) {
        $st = 'border-bottom:1px solid #e3e7ec;padding:2px 5px;';
        $st .= $estilo === 'bold' ? 'font-weight:bold;background:#f4f7fa;' : '';
        $st .= $estilo === 'sub' ? 'color:#6a747e;font-size:7.5pt;' : '';
        $html .= "<tr><td style='width:68%;{$st}'>" . $e($lbl) . '</td>'
               . "<td style='width:32%;text-align:right;{$st}'>" . $e($val) . '</td></tr>';
    }
    return $html . '</table>';
};

$bloques = $resumen['dias'];
if (!empty($resumen['total'])) {
    $bloques[] = $resumen['total'] + ['_es_total' => true];
}
$nombreEmpresa = (string) ($empresa['nombre_comercial'] ?? '') !== '' ? $empresa['nombre_comercial'] : ($empresa['nombre'] ?? '');
?>
<?php if ($modo === 'pantalla'): ?>

    <?php if (!empty($filtrosTxt)): ?>
        <div class="d-flex flex-wrap gap-1 mb-3 small">
            <?php foreach ($filtrosTxt as $lbl => $val): ?>
                <span class="badge bg-light text-dark border fw-normal"><span class="text-muted"><?= $e($lbl) ?>:</span> <?= $e($val) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$bloques): ?>
        <div class="text-center text-muted py-5"><i class="bi bi-calendar-x fs-3 d-block mb-2"></i>No hay ventas en el período elegido.</div>
    <?php endif; ?>

    <?php foreach ($bloques as $b): ?>
        <?php $esTotal = !empty($b['_es_total']); ?>
        <div class="border rounded-3 mb-3 <?= $esTotal ? 'border-primary' : '' ?>">
            <div class="px-3 py-2 border-bottom fw-bold <?= $esTotal ? 'bg-primary bg-opacity-10 text-primary' : 'bg-light' ?>">
                <i class="bi <?= $esTotal ? 'bi-sigma' : 'bi-calendar-day' ?> me-1"></i>
                <?= $esTotal ? 'TOTAL DEL PERÍODO · ' : '' ?><?= $e($b['titulo']) ?>
                <span class="float-end"><?= $e($fmt($b['total_neto'])) ?></span>
            </div>
            <?php // Detalle documento por documento (como el PDF): facturas, recibos y, si entran,
                  // NC, con su saldo pendiente hoy (en rojo si el cliente aún debe). ?>
            <?php foreach (($b['detalle'] ?? []) as $g): ?>
                <div class="px-2 pt-2">
                    <div class="small fw-bold text-uppercase text-muted mb-1" style="font-size:.68rem;">
                        <?= $e($g['etiqueta']) ?> (<?= count($g['filas']) ?>)
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" style="font-size:.78rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-1">Número</th>
                                    <th class="py-1">Cliente</th>
                                    <th class="py-1 text-end">Total</th>
                                    <th class="py-1 text-end">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($g['filas'] as $d): ?>
                                    <tr>
                                        <td class="py-1 text-nowrap"><?= $e($d['numero']) ?></td>
                                        <td class="py-1"><?= $e($d['cliente']) ?></td>
                                        <td class="py-1 text-end text-nowrap"><?= $e($fmt($d['total'])) ?></td>
                                        <td class="py-1 text-end text-nowrap <?= $d['saldo'] > 0.004 ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $e($fmt($d['saldo'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold table-light">
                                    <td class="py-1 text-end" colspan="2">Total <?= $e(mb_strtolower($g['etiqueta'])) ?></td>
                                    <td class="py-1 text-end text-nowrap"><?= $e($fmt($g['total'])) ?></td>
                                    <td class="py-1 text-end text-nowrap"><?= $e($fmt($g['saldo'])) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!empty($b['detalle'])): ?>
                <div class="px-2 pt-3 small fw-bold text-uppercase text-primary" style="font-size:.7rem;">Resumen del día</div>
            <?php endif; ?>
            <div class="row g-0">
                <?php foreach ($secciones($b) as [$titulo, $filas]): ?>
                    <div class="col-md-4 p-2">
                        <div class="small fw-bold text-uppercase text-muted mb-1" style="font-size:.68rem;"><?= $e($titulo) ?></div>
                        <table class="table table-sm mb-0" style="font-size:.78rem;">
                            <?php foreach ($filas as [$lbl, $val, $estilo]): ?>
                                <tr class="<?= $estilo === 'bold' ? 'fw-bold table-light' : ($estilo === 'sub' ? 'text-muted small' : '') ?>">
                                    <td class="py-1"><?= $e($lbl) ?></td>
                                    <td class="py-1 text-end text-nowrap"><?= $e($val) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

<?php elseif ($modo === 'pdf'): ?>

    <?php // Mismos estilos que el PDF del reporte (htmlPdf): encabezado con logo y caja
          // "Filtros aplicados" (CSS_FILTROS_PDF). Los arma el controlador. ?>
    <style>
        body { font-family: Arial, sans-serif; color: #000; }
        table { border-collapse: collapse; }
        .header { text-align: center; }
        .header h2 { margin: 0 0 1px 0; font-size: 13pt; color: #1b2a3a; }
        .header h3 { margin: 0 0 1px 0; font-size: 10pt; color: #2c4a6b; }
        .header p  { margin: 0; font-size: 7.5pt; color: #555; }
        table.fil-tit, table.filtros { width: 100%; table-layout: fixed; }
        <?= $pdf['css'] ?? '' ?>
    </style>
    <?php // Los back* se suman a los márgenes por defecto de Html2Pdf, como en exportPdf(). ?>
    <page backtop="7mm" backbottom="7mm" backleft="3mm" backright="3mm" footer="page">
        <?= $pdf['encabezado'] ?? '' ?>
        <?= $pdf['filtros'] ?? '' ?>

        <?php if (!$bloques): ?>
            <p style="font-family:Arial,sans-serif;text-align:center;">No hay ventas en el período elegido.</p>
        <?php endif; ?>

        <?php foreach ($bloques as $b): ?>
            <?php $esTotal = !empty($b['_es_total']); ?>
            <?php // Barra del día (o del total del período). ?>
            <table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;margin-top:8px;">
                <tr>
                    <td style="width:100%;padding:4px 5px;font-size:9.5pt;font-weight:bold;color:#fff;background:<?= $esTotal ? '#146c43' : '#2c4a6b' ?>;">
                        <?= $esTotal ? 'TOTAL DEL PERÍODO · ' : '' ?><?= $e($b['titulo']) ?> — Total neto <?= $e($fmt($b['total_neto'])) ?>
                    </td>
                </tr>
            </table>

            <?php // 1) Detalle: un listado por tipo (facturas, recibos y, si entran, NC) con número,
                  //    cliente, total y saldo pendiente hoy. Puede ocupar varias páginas: el <thead>
                  //    se repite y el total va en tabla aparte (un <tfoot> se repetiría en cada hoja). ?>
            <?php foreach (($b['detalle'] ?? []) as $g): ?>
                <table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:7.5pt;margin-top:4px;table-layout:fixed;">
                    <thead>
                        <tr>
                            <th colspan="4" style="width:100%;text-align:left;background:#e3e9f0;border:1px solid #9aa7b4;padding:3px 5px;font-size:8.5pt;color:#1b2a3a;">
                                <?= $e(mb_strtoupper($g['etiqueta'])) ?> (<?= count($g['filas']) ?>)
                            </th>
                        </tr>
                        <tr>
                            <th style="width:18%;background:#f4f7fa;border:1px solid #c3ccd6;padding:2px 4px;">Número</th>
                            <th style="width:54%;background:#f4f7fa;border:1px solid #c3ccd6;padding:2px 4px;">Cliente</th>
                            <th style="width:14%;background:#f4f7fa;border:1px solid #c3ccd6;padding:2px 4px;text-align:right;">Total</th>
                            <th style="width:14%;background:#f4f7fa;border:1px solid #c3ccd6;padding:2px 4px;text-align:right;">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($g['filas'] as $i => $d): ?>
                            <?php $z = $i % 2 ? 'background:#f6f8fa;' : ''; ?>
                            <tr>
                                <td style="width:18%;border:1px solid #c3ccd6;padding:2px 4px;<?= $z ?>"><?= $e($d['numero']) ?></td>
                                <td style="width:54%;border:1px solid #c3ccd6;padding:2px 4px;<?= $z ?>"><?= $e($d['cliente']) ?></td>
                                <td style="width:14%;border:1px solid #c3ccd6;padding:2px 4px;text-align:right;<?= $z ?>"><?= $e($fmt($d['total'])) ?></td>
                                <td style="width:14%;border:1px solid #c3ccd6;padding:2px 4px;text-align:right;<?= $z ?><?= $d['saldo'] > 0.004 ? 'color:#b02a37;font-weight:bold;' : '' ?>"><?= $e($fmt($d['saldo'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:7.5pt;table-layout:fixed;">
                    <tr>
                        <td style="width:72%;border:1px solid #9aa7b4;background:#e3e9f0;padding:2px 4px;text-align:right;font-weight:bold;">TOTAL <?= $e(mb_strtoupper($g['etiqueta'])) ?>:</td>
                        <td style="width:14%;border:1px solid #9aa7b4;background:#e3e9f0;padding:2px 4px;text-align:right;font-weight:bold;"><?= $e($fmt($g['total'])) ?></td>
                        <td style="width:14%;border:1px solid #9aa7b4;background:#e3e9f0;padding:2px 4px;text-align:right;font-weight:bold;"><?= $e($fmt($g['saldo'])) ?></td>
                    </tr>
                </table>
            <?php endforeach; ?>

            <?php // 2) Resumen del día (o del período): Documentos, Detalle de impuestos y Cobro.
                  //    nobreak: las tres columnas nunca quedan partidas entre dos páginas. ?>
            <table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;margin-top:5px;" nobreak="true">
                <?php if (!empty($b['detalle'])): ?>
                    <tr>
                        <td colspan="3" style="width:100%;padding:2px 2px 0 2px;font-size:8pt;font-weight:bold;color:#2c4a6b;">
                            RESUMEN DEL DÍA <?= $e($b['titulo']) ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <?php foreach ($secciones($b) as [$titulo, $filas]): ?>
                        <td style="width:33%;vertical-align:top;padding:3px 2px 0 2px;">
                            <?= $tablaInline($titulo, $filas, '100%') ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </table>
        <?php endforeach; ?>

        <?php // Firmas: "Realizado por" con el nombre de quien genera el resumen y "Aprobado por"
              // en blanco. nobreak: las dos firmas nunca quedan separadas de página. ?>
        <table style="width:100%;margin-top:15mm;font-family:Arial,sans-serif;font-size:8.5pt;" nobreak="true">
            <tr>
                <td style="width:18%;"></td>
                <td style="width:28%;border-top:1px solid #000;text-align:center;padding-top:3px;">
                    <b>Realizado por</b><br><?= $e($realizadoPor ?? '') ?>
                </td>
                <td style="width:8%;"></td>
                <td style="width:28%;border-top:1px solid #000;text-align:center;padding-top:3px;">
                    <b>Aprobado por</b><br>&nbsp;
                </td>
                <td style="width:18%;"></td>
            </tr>
        </table>
    </page>

<?php else: /* correo */ ?>

    <div style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:auto;">
        <h2 style="color:#2563eb;margin-bottom:2px;">Resumen diario de ventas</h2>
        <p style="margin-top:0;">
            <strong><?= $e($nombreEmpresa) ?></strong><?= !empty($empresa['ruc']) ? ' · RUC ' . $e($empresa['ruc']) : '' ?>
        </p>
        <?php if (!empty($filtrosTxt)): ?>
            <table style="border-collapse:collapse;font-size:13px;margin-bottom:12px;">
                <?php foreach ($filtrosTxt as $lbl => $val): ?>
                    <tr><td style="padding:2px 10px 2px 0;color:#666;"><?= $e($lbl) ?></td><td style="padding:2px 0;"><?= $e($val) ?></td></tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php foreach ($bloques as $b): ?>
            <?php $esTotal = !empty($b['_es_total']); ?>
            <div style="margin:16px 0 4px 0;padding:6px 8px;font-weight:bold;color:#fff;background:<?= $esTotal ? '#146c43' : '#2c4a6b' ?>;">
                <?= $esTotal ? 'TOTAL DEL PERÍODO · ' : '' ?><?= $e($b['titulo']) ?> — Total neto <?= $e($fmt($b['total_neto'])) ?>
            </div>
            <?php // Detalle documento por documento (como la pantalla y el PDF). Estilos en línea:
                  // los clientes de correo ignoran <style> y clases. ?>
            <?php foreach (($b['detalle'] ?? []) as $g): ?>
                <table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:12px;margin-bottom:6px;">
                    <tr>
                        <td colspan="4" style="background:#e3e9f0;border:1px solid #9aa7b4;padding:4px 6px;font-weight:bold;color:#1b2a3a;">
                            <?= $e(mb_strtoupper($g['etiqueta'])) ?> (<?= count($g['filas']) ?>)
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f4f7fa;border:1px solid #c3ccd6;padding:3px 6px;font-weight:bold;">Número</td>
                        <td style="background:#f4f7fa;border:1px solid #c3ccd6;padding:3px 6px;font-weight:bold;">Cliente</td>
                        <td style="background:#f4f7fa;border:1px solid #c3ccd6;padding:3px 6px;font-weight:bold;text-align:right;">Total</td>
                        <td style="background:#f4f7fa;border:1px solid #c3ccd6;padding:3px 6px;font-weight:bold;text-align:right;">Saldo</td>
                    </tr>
                    <?php foreach ($g['filas'] as $d): ?>
                        <tr>
                            <td style="border:1px solid #c3ccd6;padding:3px 6px;white-space:nowrap;"><?= $e($d['numero']) ?></td>
                            <td style="border:1px solid #c3ccd6;padding:3px 6px;"><?= $e($d['cliente']) ?></td>
                            <td style="border:1px solid #c3ccd6;padding:3px 6px;text-align:right;white-space:nowrap;"><?= $e($fmt($d['total'])) ?></td>
                            <td style="border:1px solid #c3ccd6;padding:3px 6px;text-align:right;white-space:nowrap;<?= $d['saldo'] > 0.004 ? 'color:#b02a37;font-weight:bold;' : '' ?>"><?= $e($fmt($d['saldo'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="2" style="background:#e3e9f0;border:1px solid #9aa7b4;padding:3px 6px;text-align:right;font-weight:bold;">Total <?= $e(mb_strtolower($g['etiqueta'])) ?></td>
                        <td style="background:#e3e9f0;border:1px solid #9aa7b4;padding:3px 6px;text-align:right;font-weight:bold;white-space:nowrap;"><?= $e($fmt($g['total'])) ?></td>
                        <td style="background:#e3e9f0;border:1px solid #9aa7b4;padding:3px 6px;text-align:right;font-weight:bold;white-space:nowrap;"><?= $e($fmt($g['saldo'])) ?></td>
                    </tr>
                </table>
            <?php endforeach; ?>
            <?php if (!empty($b['detalle'])): ?>
                <div style="margin:10px 0 4px 0;font-size:12px;font-weight:bold;color:#2c4a6b;">RESUMEN DEL DÍA <?= $e($b['titulo']) ?></div>
            <?php endif; ?>
            <?php foreach ($secciones($b) as [$titulo, $filas]): ?>
                <div style="margin-bottom:6px;"><?= $tablaInline($titulo, $filas, '100%') ?></div>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <p style="color:#888;font-size:12px;margin-top:24px;">
            Se adjunta el PDF. Resumen generado el <?= date('d-m-Y H:i:s') ?>. Reporte interno, sin validez tributaria.
        </p>
    </div>

<?php endif; ?>
