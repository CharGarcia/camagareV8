<?php
/**
 * Tirilla del Reporte Restaurante — misma vista que la pantalla, en papel
 * térmico. Se abre en ventana propia y se imprime sola, igual que las tirillas
 * del POS y de las comandas.
 *
 * El Total vendido va SIN impuestos, como en pantalla (sale de las líneas de la
 * comanda), y también el detalle, salvo en la vista por forma de pago. Los
 * bloques de impuestos y de forma de pago salen de los comprobantes y van CON
 * impuestos, como el correo del cierre de caja.
 *
 * @var array  $empresa        Ficha de la empresa (nombre, ruc, direccion, telefono)
 * @var string $titulo         Vista activa ("Ventas por mesa", …)
 * @var array  $resumen        Filtros aplicados, etiqueta => valor
 * @var array  $filas          [['concepto','detalle','total'], …]
 * @var array  $stats          cantidad_comandas, cantidad_documentos, total_vendido
 * @var array  $impuestos      Detalle de impuestos: lineas (etiqueta, valor) —
 *                             subtotales por tarifa, subtotal sin impuestos, IVA,
 *                             servicio— y total con impuestos
 * @var int    $sinComprobante Cobros cuyo comprobante se anuló o eliminó: están
 *                             en el Total vendido pero no en los bloques de abajo
 * @var array  $arqueo         Resumen por forma de pago, como el del correo del
 *                             cierre de caja: formas_pago (filas concepto/detalle/
 *                             total, con impuestos; vacío si la vista activa ya
 *                             es esa), total y propinas (servicio, voluntaria)
 * @var int    $anchoTirilla   58 u 80, lo usa el partial de estilos
 */
$e   = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmt = fn($v) => number_format((float) $v, 2);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Restaurante</title>
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
    <div class="center bold" style="font-size:12px;">REPORTE RESTAURANTE</div>
    <div class="center"><?= $e($titulo) ?></div>
    <div class="center">Emitido: <?= date('d-m-Y H:i') ?></div>

    <?php if (!empty($resumen)): ?>
        <hr class="sep">
        <table class="t-datos"><colgroup><col style="width:38%"><col></colgroup>
            <?php foreach ($resumen as $etiqueta => $valor): ?>
                <tr><td><?= $e($etiqueta) ?>:</td><td><?= $e($valor) ?></td></tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <hr class="sep">
    <table class="t-detalle"><colgroup><col><col class="col-num"></colgroup>
        <tbody>
        <?php if (empty($filas)): ?>
            <tr><td colspan="2" class="center">Sin resultados para estos filtros.</td></tr>
        <?php else: ?>
            <?php foreach ($filas as $i => $f): ?>
                <?php if ($i > 0): ?><tr><td colspan="2"><hr></td></tr><?php endif; ?>
                <tr><td colspan="2"><?= $e($f['concepto']) ?></td></tr>
                <tr>
                    <td class="sub"><?= $e($f['detalle']) ?></td>
                    <td class="num bold">$<?= $fmt($f['total']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <?php // Justo debajo del detalle: es su total, sin impuestos, en todas las
          // vistas menos la de forma de pago, cuyo detalle ya es lo cobrado con
          // impuestos (su total es el Total cobrado de abajo).
          // El &nbsp; hace que en 58 mm parta "TOTAL VENDIDO / (sin imp.)" y no
          // deje el paréntesis cortado por la mitad. ?>
    <hr class="sep">
    <table class="t-totales"><colgroup><col><col class="col-num"></colgroup>
        <tr><td>Comandas</td><td class="num"><?= (int) ($stats['cantidad_comandas'] ?? 0) ?></td></tr>
        <tr><td>Documentos</td><td class="num"><?= (int) ($stats['cantidad_documentos'] ?? 0) ?></td></tr>
        <tr><td>TOTAL VENDIDO (sin&nbsp;imp.)</td><td class="num">$<?= $fmt($stats['total_vendido'] ?? 0) ?></td></tr>
    </table>

    <?php
    // Resumen por forma de pago: el mismo bloque que manda el correo del cierre
    // de caja (forma, cobros, cobrado con impuestos; total; servicio y propina
    // voluntaria), acotado a los filtros del reporte. Sin "contado" ni
    // "diferencia": el reporte no está atado a un turno, así que no hay arqueo
    // contra qué cuadrar.
    $arqueoFormas = $arqueo['formas_pago'] ?? [];
    $propinas     = $arqueo['propinas'] ?? [];
    ?>
    <?php if (!empty($filas)): ?>
        <?php // Del Total vendido al total cobrado: bases por tarifa, IVA y servicio,
              // como el bloque de totales de la factura. La última fila de
              // t-totales sale en negrita: es el total con impuestos. ?>
        <hr class="sep">
        <div class="center bold">DETALLE DE IMPUESTOS</div>
        <table class="t-totales"><colgroup><col><col class="col-num"></colgroup>
            <?php foreach ($impuestos['lineas'] ?? [] as $l): ?>
                <tr><td><?= $e($l['etiqueta']) ?></td><td class="num">$<?= $fmt($l['valor']) ?></td></tr>
            <?php endforeach; ?>
            <tr><td>TOTAL CON IMPUESTOS</td><td class="num">$<?= $fmt($impuestos['total'] ?? 0) ?></td></tr>
        </table>
        <?php if (!empty($sinComprobante)): ?>
            <div style="font-size:10px;">
                <?= (int) $sinComprobante ?> cobro(s) sin comprobante vigente (anulado o
                eliminado): cuentan en el Total vendido, pero no aquí ni en el resumen
                por forma de pago.
            </div>
        <?php endif; ?>

        <hr class="sep">
        <div class="center bold">RESUMEN POR FORMA DE PAGO</div>
        <div class="center" style="font-size:10px;">Lo cobrado, con impuestos</div>
        <?php if (!empty($arqueoFormas)): ?>
            <table class="t-detalle"><colgroup><col><col class="col-num"></colgroup>
                <tbody>
                <?php foreach ($arqueoFormas as $f): ?>
                    <tr><td colspan="2"><?= $e($f['concepto']) ?></td></tr>
                    <tr>
                        <td class="sub"><?= $e($f['detalle']) ?></td>
                        <td class="num"><?= '$' . $fmt($f['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php // Va como t-detalle y no como t-totales: esa clase pone en negrita la
              // ÚLTIMA fila, y aquí la fila fuerte es la primera (el total). ?>
        <table class="t-detalle"><colgroup><col><col class="col-num"></colgroup>
            <?php if (!empty($arqueoFormas)): ?><tr><td colspan="2"><hr></td></tr><?php endif; ?>
            <tr class="bold"><td>Total cobrado</td><td class="num">$<?= $fmt($arqueo['total'] ?? 0) ?></td></tr>
            <tr><td class="sub">Servicio</td><td class="num sub">$<?= $fmt($propinas['servicio'] ?? 0) ?></td></tr>
            <tr><td class="sub">Propina voluntaria</td><td class="num sub">$<?= $fmt($propinas['voluntaria'] ?? 0) ?></td></tr>
        </table>
    <?php endif; ?>

    <hr class="sep">
    <div class="center" style="font-size:10px;">Reporte interno — sin validez tributaria</div>
    <div class="feed"></div>

    <script>
    <?php require MVC_APP . '/views/partials/tirilla_script.php'; ?>
    </script>
</body>
</html>
