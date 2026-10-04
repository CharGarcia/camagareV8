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

    <?php // Los back* se suman a los márgenes por defecto de Html2Pdf, como en exportPdf(). ?>
    <page backtop="7mm" backbottom="7mm" backleft="3mm" backright="3mm" footer="page">
        <?php // En tabla de ancho completo: Html2Pdf no centra un <div> sin ancho declarado. ?>
        <table style="width:100%;font-family:Arial,sans-serif;margin-bottom:6px;">
            <tr><td style="width:100%;text-align:center;">
                <span style="font-size:13pt;font-weight:bold;color:#1b2a3a;"><?= $e($nombreEmpresa) ?></span><br>
                <?php if (!empty($empresa['ruc'])): ?><span style="font-size:8pt;">RUC: <?= $e($empresa['ruc']) ?></span><br><?php endif; ?>
                <span style="font-size:10pt;color:#2c4a6b;">Resumen diario de ventas</span><br>
                <span style="font-size:7.5pt;color:#555;">Generado: <?= date('d-m-Y H:i:s') ?></span>
            </td></tr>
        </table>
        <?php if (!empty($filtrosTxt)): ?>
            <table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:7.5pt;margin-bottom:8px;">
                <?php foreach ($filtrosTxt as $lbl => $val): ?>
                    <tr>
                        <td style="width:20%;border:1px solid #c3ccd6;background:#f8f9fa;font-weight:bold;padding:2px 4px;"><?= $e($lbl) ?>:</td>
                        <td style="width:80%;border:1px solid #c3ccd6;padding:2px 4px;"><?= $e($val) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php if (!$bloques): ?>
            <p style="font-family:Arial,sans-serif;text-align:center;">No hay ventas en el período elegido.</p>
        <?php endif; ?>

        <?php foreach ($bloques as $b): ?>
            <?php $esTotal = !empty($b['_es_total']); ?>
            <table style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;margin-top:6px;" nobreak="true">
                <tr>
                    <td colspan="3" style="width:100%;padding:4px 5px;font-size:9.5pt;font-weight:bold;color:#fff;background:<?= $esTotal ? '#146c43' : '#2c4a6b' ?>;">
                        <?= $esTotal ? 'TOTAL DEL PERÍODO · ' : '' ?><?= $e($b['titulo']) ?> — Total neto <?= $e($fmt($b['total_neto'])) ?>
                    </td>
                </tr>
                <tr>
                    <?php foreach ($secciones($b) as [$titulo, $filas]): ?>
                        <td style="width:33%;vertical-align:top;padding:3px 2px 0 2px;">
                            <?= $tablaInline($titulo, $filas, '100%') ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </table>
        <?php endforeach; ?>
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
            <?php foreach ($secciones($b) as [$titulo, $filas]): ?>
                <div style="margin-bottom:6px;"><?= $tablaInline($titulo, $filas, '100%') ?></div>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <p style="color:#888;font-size:12px;margin-top:24px;">
            Se adjunta el PDF. Resumen generado el <?= date('d-m-Y H:i:s') ?>. Reporte interno, sin validez tributaria.
        </p>
    </div>

<?php endif; ?>
