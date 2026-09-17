<?php
/**
 * PDF de una carga de inventario (botón PDF del modal de detalle), con
 * Spipu\Html2Pdf. Lleva las mismas columnas que la tabla del modal: código,
 * producto, bodega, cantidad, costo, OK y motivo.
 *
 * Html2Pdf soporta un subconjunto de CSS: las secciones se maquetan con tablas y
 * anchos en %, sin float ni flex.
 *
 * @var array $carga    Cabecera de la carga (CargaInventarioService::getDetalleCompleto)
 * @var array $cabecera Etiqueta => valor (CargasInventarioController::cabeceraParaExportar)
 * @var array $lineas   Líneas ya preparadas (CargasInventarioController::lineasParaExportar)
 * @var array $empresa
 */
$e = static fn($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

// Valor vacío: guion simple; la raya "—" no existe en la fuente base del PDF y no se ve.
$oGuion = static fn(string $v): string => $v !== '' ? $v : '-';

// Cantidad sin ceros de relleno, como en el modal: 10, 2.5, 0.125.
$cantidad = static fn(float $v): string => rtrim(rtrim(number_format($v, 6, '.', ','), '0'), '.');

// Observación y motivo de rechazo pueden ser largos: van en su propia fila.
$largos = array_flip(['Observación', 'Motivo de rechazo']);
$cortos = array_diff_key($cabecera, $largos);

// Ancho de cada columna (%). Va en el <th> Y en cada <td>: si solo lo lleva el <th>,
// Html2Pdf no parte el texto en varias líneas sino que ensancha la columna hasta que
// quepa en una sola, y la tabla se sale de la hoja.
$anchos = ['codigo' => 14, 'producto' => 27, 'bodega' => 13, 'cantidad' => 8, 'costo' => 8, 'ok' => 4, 'motivo' => 26];
$ancho  = static fn(string $col): string => 'style="width:' . $anchos[$col] . '%"';
?>
<style>
    table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 9pt; }
    h1 { margin: 0; font-size: 14pt; color: #333; }
    h2 { margin: 3px 0 0 0; font-size: 9pt; color: #666; font-weight: normal; }
    .header { text-align: center; margin-bottom: 10px; width: 100%; }
    .datos td { padding: 3px 4px; border: none; font-size: 9pt; }
    .datos .lbl { color: #666; font-size: 7.5pt; width: 90px; }
    .items th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; font-size: 8pt; text-align: left; }
    .items td { border: 1px solid #ccc; padding: 4px; font-size: 8pt; overflow: hidden; word-wrap: break-word; }
    .text-end { text-align: right; }
    .text-center { text-align: center; }
    .error { color: #b02a37; }
    .nota { font-size: 7.5pt; color: #666; margin-top: 12px; }
</style>
<page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">

    <div class="header">
        <h1><?= $e($empresa['nombre'] ?? '') ?></h1>
        <h2>RUC: <?= $e($empresa['ruc'] ?? '') ?> &nbsp;|&nbsp; CARGA DE INVENTARIO #<?= (int) $carga['numero'] ?></h2>
    </div>

    <table class="datos">
        <?php foreach (array_chunk($cortos, 2, true) as $par): ?>
            <tr>
                <?php foreach ($par as $etiqueta => $valor): ?>
                    <td class="lbl"><?= $e($etiqueta) ?></td>
                    <td><?= $e($valor) ?></td>
                <?php endforeach; ?>
                <?php if (count($par) === 1): ?>
                    <td class="lbl"></td>
                    <td></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php foreach (array_intersect_key($cabecera, $largos) as $etiqueta => $valor): ?>
            <tr>
                <td class="lbl"><?= $e($etiqueta) ?></td>
                <td colspan="3"><?= $e($valor) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <br>

    <table class="items">
        <thead>
            <tr>
                <th <?= $ancho('codigo') ?>>Código</th>
                <th <?= $ancho('producto') ?>>Producto</th>
                <th <?= $ancho('bodega') ?>>Bodega</th>
                <th <?= $ancho('cantidad') ?> class="text-end">Cantidad</th>
                <th <?= $ancho('costo') ?> class="text-end">Costo</th>
                <th <?= $ancho('ok') ?> class="text-center">OK</th>
                <th <?= $ancho('motivo') ?>>Motivo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lineas as $l): ?>
                <tr>
                    <td <?= $ancho('codigo') ?>><?= $e($oGuion($l['codigo'])) ?></td>
                    <td <?= $ancho('producto') ?>><?= $e($oGuion($l['producto'])) ?></td>
                    <td <?= $ancho('bodega') ?>><?= $e($oGuion($l['bodega'])) ?></td>
                    <td <?= $ancho('cantidad') ?> class="text-end"><?= $cantidad($l['cantidad']) ?></td>
                    <td <?= $ancho('costo') ?> class="text-end">$ <?= number_format($l['costo'], 2) ?></td>
                    <td <?= $ancho('ok') ?> class="text-center<?= $l['ok'] ? '' : ' error' ?>"><?= $l['ok'] ? 'Sí' : 'No' ?></td>
                    <td <?= $ancho('motivo') ?> class="error"><?= $e($l['motivo']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($lineas)): ?>
                <tr>
                    <td colspan="7" class="text-center">Sin líneas</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="nota">Impreso el <?= date('d-m-Y H:i:s') ?>.</div>
</page>
