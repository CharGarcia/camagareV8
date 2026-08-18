<?php
/**
 * Acta de transferencia de inventario (Spipu\Html2Pdf).
 * Recibe $doc (cabecera + detalles) y $empresa desde el controlador.
 *
 * Html2Pdf soporta un subconjunto de CSS: se maquetan las secciones con tablas
 * y se evitan float, flex y sombras.
 *
 * @var array $doc
 * @var array $empresa
 */
$anulada  = ($doc['estado'] ?? '') === 'anulada';
$entreEst = !empty($doc['entre_establecimientos']) && $doc['entre_establecimientos'] !== 'f';

$estOrigen  = trim(($doc['establecimiento_origen_codigo'] ?? '') . ' ' . ($doc['establecimiento_origen_nombre'] ?? ''));
$estDestino = trim(($doc['establecimiento_destino_codigo'] ?? '') . ' ' . ($doc['establecimiento_destino_nombre'] ?? ''));
?>
<style>
    table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 9pt; }
    h1 { margin: 0; font-size: 14pt; color: #333; }
    h2 { margin: 3px 0 0 0; font-size: 9pt; color: #666; font-weight: normal; }
    .header { text-align: center; margin-bottom: 10px; width: 100%; }
    .anulada { color: #c00; border: 1px solid #c00; padding: 3px; font-weight: bold; text-align: center; margin-bottom: 6px; }
    .datos td { padding: 3px 4px; border: none; font-size: 9pt; }
    .datos .lbl { color: #666; font-size: 7.5pt; width: 90px; }
    .items th { background: #f2f2f2; border: 1px solid #ccc; padding: 4px; font-size: 8pt; text-align: left; }
    .items td { border: 1px solid #ccc; padding: 4px; font-size: 8pt; overflow: hidden; word-wrap: break-word; }
    .text-end { text-align: right; }
    .firmas td { border: none; border-top: 1px solid #333; text-align: center; font-size: 8.5pt; padding-top: 4px; }
    .nota { font-size: 7.5pt; color: #666; margin-top: 12px; }
</style>
<page backtop="10mm" backbottom="10mm" backleft="10mm" backright="10mm">

    <?php if ($anulada): ?>
        <div class="anulada">TRANSFERENCIA ANULADA</div>
    <?php endif; ?>

    <div class="header">
        <h1><?= htmlspecialchars((string) ($empresa['nombre'] ?? '')) ?></h1>
        <h2>RUC: <?= htmlspecialchars((string) ($empresa['ruc'] ?? '')) ?> &nbsp;|&nbsp; ACTA DE TRANSFERENCIA DE INVENTARIO</h2>
    </div>

    <table class="datos">
        <tr>
            <td class="lbl">Número</td>
            <td><b><?= htmlspecialchars((string) $doc['numero']) ?></b></td>
            <td class="lbl">Fecha</td>
            <td><?= date('d-m-Y H:i:s', strtotime((string) $doc['fecha_transferencia'])) ?></td>
        </tr>
        <tr>
            <td class="lbl">Bodega origen</td>
            <td><?= htmlspecialchars((string) $doc['origen_nombre']) ?><?= $estOrigen !== '' ? ' (Est. ' . htmlspecialchars($estOrigen) . ')' : '' ?></td>
            <td class="lbl">Bodega destino</td>
            <td><?= htmlspecialchars((string) $doc['destino_nombre']) ?><?= $estDestino !== '' ? ' (Est. ' . htmlspecialchars($estDestino) . ')' : '' ?></td>
        </tr>
        <tr>
            <td class="lbl">Entrega</td>
            <td><?= htmlspecialchars((string) ($doc['responsable_envia'] ?: '—')) ?></td>
            <td class="lbl">Recibe</td>
            <td><?= htmlspecialchars((string) ($doc['responsable_recibe'] ?: '—')) ?></td>
        </tr>
        <tr>
            <td class="lbl">Registró</td>
            <td><?= htmlspecialchars((string) ($doc['usuario_nombre'] ?? '')) ?></td>
            <td class="lbl">Tipo</td>
            <td><?= $entreEst ? 'Entre establecimientos' : 'Entre bodegas del mismo establecimiento' ?></td>
        </tr>
        <?php if (!empty($doc['observaciones'])): ?>
        <tr>
            <td class="lbl">Observaciones</td>
            <td colspan="3"><?= htmlspecialchars((string) $doc['observaciones']) ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <br>

    <table class="items">
        <thead>
            <tr>
                <th style="width:11%">Código</th>
                <th style="width:31%">Producto</th>
                <th style="width:13%">Lote</th>
                <th style="width:11%">Caducidad</th>
                <th style="width:13%">Serie / NUP</th>
                <th style="width:7%" class="text-end">Cant.</th>
                <th style="width:7%" class="text-end">C.unit</th>
                <th style="width:7%" class="text-end">C.total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($doc['detalles'] ?? []) as $d): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($d['producto_codigo'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($d['producto_nombre'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($d['numero_lote'] ?? '')) ?></td>
                    <td><?= !empty($d['fecha_caducidad']) ? date('d-m-Y', strtotime((string) $d['fecha_caducidad'])) : '' ?></td>
                    <td><?= htmlspecialchars((string) ($d['nup'] ?? '')) ?></td>
                    <td class="text-end"><?= number_format((float) $d['cantidad'], 2) ?></td>
                    <td class="text-end"><?= number_format((float) $d['costo_unitario'], 4) ?></td>
                    <td class="text-end"><?= number_format((float) $d['costo_total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th colspan="5" class="text-end">TOTALES</th>
                <th class="text-end"><?= number_format((float) $doc['total_items'], 2) ?></th>
                <th></th>
                <th class="text-end"><?= number_format((float) $doc['total_costo'], 2) ?></th>
            </tr>
        </tbody>
    </table>

    <br><br><br><br>

    <table class="firmas">
        <tr>
            <td style="width:45%">Entregué conforme<br><?= htmlspecialchars((string) ($doc['responsable_envia'] ?? '')) ?></td>
            <td style="width:10%; border-top:none;"></td>
            <td style="width:45%">Recibí conforme<br><?= htmlspecialchars((string) ($doc['responsable_recibe'] ?? '')) ?></td>
        </tr>
    </table>

    <div class="nota">
        Documento interno de control de inventario. No sustituye a la guía de remisión exigida para el traslado
        de mercadería entre establecimientos. Impreso el <?= date('d-m-Y H:i:s') ?>.
    </div>
</page>
