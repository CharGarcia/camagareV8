<?php
/**
 * Tirilla del Resumen diario del Reporte de Ventas — mismas secciones y estilos que la
 * tirilla del Reporte Restaurante (reporte_restaurante/tirilla.php): un bloque por día
 * con DOCUMENTOS, DETALLE DE IMPUESTOS y COBRO POR FORMA DE PAGO y, si el período tiene
 * más de un día, el TOTAL DEL PERÍODO al final. Se abre en ventana propia y se imprime
 * sola (partials/tirilla_script.php). Lo arma ReporteVentasResumenDiarioService.
 *
 * @var array  $empresa       Ficha de la empresa (nombre, ruc, logo_ruta)
 * @var array  $resumen       ['dias' => [...bloques], 'total' => ?bloque]
 * @var array  $filtrosTxt    Filtros aplicados, etiqueta => valor
 * @var int    $anchoTirilla  58 u 80, lo usa el partial de estilos
 */
$e   = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmt = fn($v) => ((float) $v < 0 ? '-' : '') . '$' . number_format(abs((float) $v), 2);

$bloques = $resumen['dias'];
if (!empty($resumen['total'])) {
    $bloques[] = $resumen['total'] + ['_es_total' => true];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen diario de ventas</title>
    <?php require MVC_APP . '/views/partials/tirilla_estilos.php'; ?>
</head>
<body>
    <div class="center">
        <?php if (!empty($empresa['logo_ruta'])): ?>
            <img src="<?= $e(BASE_URL . '/' . ltrim($empresa['logo_ruta'], '/')) ?>" style="margin-bottom:4px;">
        <?php endif; ?>
        <h2><?= $e($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? '') ?></h2>
        <?php if (!empty($empresa['ruc'])): ?><h3>RUC: <?= $e($empresa['ruc']) ?></h3><?php endif; ?>
    </div>
    <hr class="sep">
    <div class="center bold" style="font-size:12px;">RESUMEN DIARIO DE VENTAS</div>
    <div class="center">Emitido: <?= date('d-m-Y H:i') ?></div>
    <?php if (!empty($filtrosTxt)): ?>
        <hr class="sep">
        <table class="t-datos"><colgroup><col style="width:38%"><col></colgroup>
            <?php foreach ($filtrosTxt as $etiqueta => $valor): ?>
                <tr><td><?= $e($etiqueta) ?>:</td><td><?= $e($valor) ?></td></tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php if (!$bloques): ?>
        <hr class="sep">
        <div class="center">Sin ventas en el período elegido.</div>
    <?php endif; ?>

    <?php foreach ($bloques as $b): ?>
        <?php $c = $b['cobro']; ?>
        <hr class="sep">
        <div class="center bold" style="font-size:12px;">
            <?= !empty($b['_es_total']) ? 'TOTAL DEL PERÍODO' : 'DÍA ' . $e($b['titulo']) ?>
        </div>
        <?php if (!empty($b['_es_total'])): ?><div class="center"><?= $e($b['titulo']) ?></div><?php endif; ?>

        <hr class="sep">
        <div class="center bold">DOCUMENTOS</div>
        <?php // t-totales pone en negrita la última fila: el total neto. ?>
        <table class="t-totales"><colgroup><col><col class="col-num"></colgroup>
            <?php foreach ($b['documentos'] as $d): ?>
                <tr><td><?= $e($d['etiqueta']) ?> (<?= (int) $d['cantidad'] ?>)</td><td class="num"><?= $fmt($d['total']) ?></td></tr>
            <?php endforeach; ?>
            <?php if ($b['anulados'] > 0): ?>
                <tr><td class="sub" colspan="2">Anulados: <?= (int) $b['anulados'] ?> (no suman)</td></tr>
            <?php endif; ?>
            <tr><td class="sub">Vendido sin&nbsp;imp.</td><td class="num sub"><?= $fmt($b['total_vendido']) ?></td></tr>
            <tr><td>TOTAL NETO</td><td class="num"><?= $fmt($b['total_neto']) ?></td></tr>
        </table>

        <hr class="sep">
        <div class="center bold">DETALLE DE IMPUESTOS</div>
        <table class="t-totales"><colgroup><col><col class="col-num"></colgroup>
            <?php foreach ($b['impuestos']['lineas'] as $l): ?>
                <tr><td><?= $e($l['etiqueta']) ?></td><td class="num"><?= $fmt($l['valor']) ?></td></tr>
            <?php endforeach; ?>
            <tr><td>TOTAL CON IMPUESTOS</td><td class="num"><?= $fmt($b['impuestos']['total']) ?></td></tr>
        </table>

        <hr class="sep">
        <div class="center bold">COBRO POR FORMA DE PAGO</div>
        <div class="center" style="font-size:10px;">Facturas y recibos, con impuestos</div>
        <table class="t-detalle"><colgroup><col><col class="col-num"></colgroup>
            <tbody>
            <?php foreach ($c['formas'] as $f): ?>
                <tr><td colspan="2"><?= $e($f['concepto']) ?></td></tr>
                <tr><td class="sub"><?= $e($f['detalle']) ?></td><td class="num"><?= $fmt($f['total']) ?></td></tr>
            <?php endforeach; ?>
            <?php if ($c['retenido'] != 0): ?>
                <tr><td>Retenciones</td><td class="num"><?= $fmt($c['retenido']) ?></td></tr>
            <?php endif; ?>
            <?php if ($c['nc'] != 0): ?>
                <tr><td>Notas de crédito aplic.</td><td class="num"><?= $fmt($c['nc']) ?></td></tr>
            <?php endif; ?>
            <?php if ($c['pendiente'] != 0): ?>
                <tr><td>Pendiente (crédito)</td><td class="num"><?= $fmt($c['pendiente']) ?></td></tr>
            <?php endif; ?>
            <?php if ($c['excedente'] != 0): ?>
                <tr><td class="sub">Cobrado de más</td><td class="num sub"><?= $fmt($c['excedente']) ?></td></tr>
            <?php endif; ?>
            <tr><td colspan="2"><hr></td></tr>
            <tr class="bold"><td>TOTAL FACT. Y RECIBOS</td><td class="num"><?= $fmt($c['total']) ?></td></tr>
            </tbody>
        </table>
    <?php endforeach; ?>

    <hr class="sep">
    <div class="center" style="font-size:10px;">Reporte interno — sin validez tributaria</div>
    <div class="feed"></div>

    <script>
    <?php require MVC_APP . '/views/partials/tirilla_script.php'; ?>
    </script>
</body>
</html>
