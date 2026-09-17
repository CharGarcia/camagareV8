<?php
/**
 * Acta de transferencia de inventario (Spipu\Html2Pdf).
 * Recibe $doc (cabecera + detalles), $empresa y $logoPdf desde el controlador.
 *
 * Html2Pdf soporta un subconjunto de CSS: se maqueta todo con tablas y se evitan
 * float, flex, sombras y border-radius. Regla crítica de esta librería: el ancho
 * de columna debe repetirse en CADA celda (no basta con ponerlo en el <th>), o la
 * tabla se ensancha hasta salirse de la hoja y su contenido deja de verse dentro
 * del área imprimible.
 *
 * @var array  $doc
 * @var array  $empresa
 * @var string $logoPdf  Ruta en disco del logo ('' si la empresa no tiene).
 */
$anulada  = ($doc['estado'] ?? '') === 'anulada';
$entreEst = !empty($doc['entre_establecimientos']) && $doc['entre_establecimientos'] !== 'f';
$detalles = $doc['detalles'] ?? [];
$logoPdf  = $logoPdf ?? '';

$esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** La fuente base del PDF no tiene la raya larga: para los vacíos se usa un guion simple. */
$oVacio = static fn ($v): string => trim((string) $v) !== '' ? (string) $v : '-';

// Columnas de trazabilidad: solo se imprimen si alguna línea las usa, así el
// nombre del producto se queda con el ancho que sobra en las actas sin lotes.
$hayLote = $hayCad = $hayNup = false;
foreach ($detalles as $d) {
    $hayLote = $hayLote || trim((string) ($d['numero_lote'] ?? '')) !== '';
    $hayCad  = $hayCad  || !empty($d['fecha_caducidad']);
    $hayNup  = $hayNup  || trim((string) ($d['nup'] ?? '')) !== '';
}

$conTraza = $hayLote || $hayCad || $hayNup;
$cols     = [
    ['k' => 'n',        't' => '#',        'w' => 5,                  'a' => 'text-center'],
    ['k' => 'codigo',   't' => 'Código',   'w' => 16,                 'a' => ''],
    ['k' => 'producto', 't' => 'Producto', 'w' => $conTraza ? 38 : 65, 'a' => ''],
];
if ($hayLote) { $cols[] = ['k' => 'lote', 't' => 'Lote',        'w' => 13, 'a' => '']; }
if ($hayCad)  { $cols[] = ['k' => 'cad',  't' => 'Caducidad',   'w' => 11, 'a' => 'text-center']; }
if ($hayNup)  { $cols[] = ['k' => 'nup',  't' => 'Serie / NUP', 'w' => 13, 'a' => '']; }
$cols[] = ['k' => 'cant', 't' => 'Cantidad', 'w' => 12, 'a' => 'text-end'];

$pesoTotal = array_sum(array_column($cols, 'w'));
foreach ($cols as $i => $c) {
    $cols[$i]['pct'] = round($c['w'] / $pesoTotal * 100, 2) . '%';
}
$nCols = count($cols);
?>
<style>
    table { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 9pt; table-layout: fixed; }
    td, th { vertical-align: top; }
    .text-end    { text-align: right; }
    .text-center { text-align: center; }

    /* Encabezado: logo | datos de la empresa | recuadro del documento */
    .cab td { border: none; padding: 0; vertical-align: middle; }
    .cab .emp-nombre { font-size: 13pt; font-weight: bold; color: #1f2937; }
    .cab .emp-dato   { font-size: 8.5pt; color: #4b5563; }
    .doc-box { border: 1px solid #1f2937; padding: 4px 6px; }
    .doc-box .doc-tipo { font-size: 8pt;  font-weight: bold; color: #1f2937; text-align: center; }
    .doc-box .doc-num  { font-size: 13pt; font-weight: bold; color: #1f2937; text-align: center; }
    .doc-box .doc-fec  { font-size: 7.5pt; color: #4b5563; text-align: center; }
    .regla { border-top: 2px solid #1f2937; margin: 6px 0 8px 0; }

    .anulada { color: #b91c1c; border: 1px solid #b91c1c; padding: 4px; font-weight: bold;
               text-align: center; margin-bottom: 6px; font-size: 10pt; }

    /* Origen / destino / responsables */
    .box td { border: 1px solid #d1d5db; padding: 3px 5px; font-size: 9pt; }
    .box .sec { background: #1f2937; color: #ffffff; font-size: 8pt; font-weight: bold;
                text-align: center; padding: 3px; }
    .box .lbl { background: #f3f4f6; color: #4b5563; font-size: 7.5pt; }
    .box .val { font-size: 9pt; }

    /* Detalle */
    .items th { background: #1f2937; color: #ffffff; border: 1px solid #1f2937;
                padding: 4px; font-size: 8pt; text-align: left; }
    .items td { border: 1px solid #d1d5db; padding: 3px 4px; font-size: 8pt; }
    .items .tot td { background: #f3f4f6; font-weight: bold; font-size: 8.5pt; }
    .items .vacio { text-align: center; color: #6b7280; padding: 8px; }

    .obs { margin-top: 8px; }
    .obs td  { border: 1px solid #d1d5db; padding: 4px 6px; font-size: 8.5pt; }
    .obs .lbl { color: #4b5563; font-size: 7.5pt; }

    .firmas td { border: none; font-size: 8.5pt; text-align: center; padding: 0 4px; }
    .firmas .linea { border-top: 1px solid #1f2937; padding-top: 3px; }
    .firmas .rol   { font-weight: bold; font-size: 8pt; }
    .firmas .quien { font-size: 8pt; color: #374151; }

    .nota { font-size: 7pt; color: #6b7280; margin-top: 10px; }
</style>
<page backtop="10mm" backbottom="12mm" backleft="10mm" backright="10mm" footer="page">

    <?php if ($anulada): ?>
        <div class="anulada">TRANSFERENCIA ANULADA</div>
    <?php endif; ?>

    <table class="cab">
        <tr>
            <?php if ($logoPdf !== ''): ?>
                <td style="width:24%">
                    <img src="<?= $esc($logoPdf) ?>" style="max-width:42mm;max-height:20mm;">
                </td>
                <td style="width:44%">
            <?php else: ?>
                <td style="width:68%">
            <?php endif; ?>
                <div class="emp-nombre"><?= $esc($empresa['nombre'] ?? '') ?></div>
                <?php if (!empty($empresa['nombre_comercial']) && $empresa['nombre_comercial'] !== ($empresa['nombre'] ?? '')): ?>
                    <div class="emp-dato"><?= $esc($empresa['nombre_comercial']) ?></div>
                <?php endif; ?>
                <div class="emp-dato">RUC: <?= $esc($empresa['ruc'] ?? '') ?></div>
            </td>
            <td style="width:32%">
                <div class="doc-box">
                    <div class="doc-tipo">TRANSFERENCIA DE INVENTARIO</div>
                    <div class="doc-num"><?= $esc($doc['numero']) ?></div>
                    <div class="doc-fec"><?= date('d-m-Y H:i:s', strtotime((string) $doc['fecha_transferencia'])) ?></div>
                </div>
            </td>
        </tr>
    </table>

    <div class="regla"></div>

    <table class="box">
        <tr>
            <td class="sec" colspan="2" style="width:50%">ORIGEN</td>
            <td class="sec" colspan="2" style="width:50%">DESTINO</td>
        </tr>
        <tr>
            <td class="lbl" style="width:13%">Bodega</td>
            <td class="val" style="width:37%"><b><?= $esc($doc['origen_nombre']) ?></b></td>
            <td class="lbl" style="width:13%">Bodega</td>
            <td class="val" style="width:37%"><b><?= $esc($doc['destino_nombre']) ?></b></td>
        </tr>
        <tr>
            <td class="lbl" style="width:13%">Entrega</td>
            <td class="val" style="width:37%"><?= $esc($oVacio($doc['responsable_envia'] ?? '')) ?></td>
            <td class="lbl" style="width:13%">Recibe</td>
            <td class="val" style="width:37%"><?= $esc($oVacio($doc['responsable_recibe'] ?? '')) ?></td>
        </tr>
        <tr>
            <td class="lbl" style="width:13%">Registró</td>
            <td class="val" style="width:37%"><?= $esc($oVacio($doc['usuario_nombre'] ?? '')) ?></td>
            <td class="lbl" style="width:13%">Tipo</td>
            <td class="val" style="width:37%"><?= $entreEst ? 'Entre establecimientos' : 'Entre bodegas del mismo establecimiento' ?></td>
        </tr>
    </table>

    <br>

    <table class="items">
        <thead>
            <tr>
                <?php foreach ($cols as $c): ?>
                    <th style="width:<?= $c['pct'] ?>" class="<?= $c['a'] ?>"><?= $esc($c['t']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (!$detalles): ?>
                <tr><td colspan="<?= $nCols ?>" class="vacio">La transferencia no tiene líneas registradas.</td></tr>
            <?php else: ?>
                <?php foreach ($detalles as $i => $d): ?>
                    <?php
                    $valores = [
                        'n'        => (string) ($i + 1),
                        'codigo'   => (string) ($d['producto_codigo'] ?? ''),
                        'producto' => (string) ($d['producto_nombre'] ?? ''),
                        'lote'     => $oVacio($d['numero_lote'] ?? ''),
                        'cad'      => !empty($d['fecha_caducidad']) ? date('d-m-Y', strtotime((string) $d['fecha_caducidad'])) : '-',
                        'nup'      => $oVacio($d['nup'] ?? ''),
                        'cant'     => number_format((float) $d['cantidad'], 2),
                    ];
                    ?>
                    <tr>
                        <?php foreach ($cols as $c): ?>
                            <td style="width:<?= $c['pct'] ?>" class="<?= $c['a'] ?>"><?= $esc($valores[$c['k']]) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr class="tot">
                <td class="text-end" colspan="<?= $nCols - 1 ?>">TOTAL (<?= count($detalles) ?> <?= count($detalles) === 1 ? 'línea' : 'líneas' ?>)</td>
                <td style="width:<?= $cols[$nCols - 1]['pct'] ?>" class="text-end"><?= number_format((float) $doc['total_items'], 2) ?></td>
            </tr>
        </tbody>
    </table>

    <?php if (!empty($doc['observaciones'])): ?>
        <?php // En tabla y no en un <div> con borde: al saltar de página, Html2Pdf
              // dibuja el recuadro de la tabla y el del div lo pierde. ?>
        <table class="obs">
            <tr><td style="width:100%"><span class="lbl">Observaciones:</span> <?= $esc($doc['observaciones']) ?></td></tr>
        </table>
    <?php endif; ?>

    <br><br><br>

    <?php // Las firmas no se parten entre páginas: si no caben, pasan enteras a la siguiente. ?>
    <nobreak>
        <table class="firmas">
            <tr>
                <td style="width:42%">&nbsp;</td>
                <td style="width:16%">&nbsp;</td>
                <td style="width:42%">&nbsp;</td>
            </tr>
            <tr>
                <?php // Contenido inline (no <div>): un bloque dentro de un <td> se centra
                      // respecto al ancho de la página, no al de la celda, y el rótulo se sale. ?>
                <td style="width:42%" class="linea">
                    <span class="rol">ENTREGUÉ CONFORME</span><br>
                    <span class="quien"><?= $esc($oVacio($doc['responsable_envia'] ?? '')) ?></span>
                </td>
                <td style="width:16%">&nbsp;</td>
                <td style="width:42%" class="linea">
                    <span class="rol">RECIBÍ CONFORME</span><br>
                    <span class="quien"><?= $esc($oVacio($doc['responsable_recibe'] ?? '')) ?></span>
                </td>
            </tr>
        </table>
    </nobreak>

    <div class="nota">
        Documento interno de control de inventario. No sustituye a la guía de remisión exigida para el traslado
        de mercadería entre establecimientos. Impreso el <?= date('d-m-Y H:i:s') ?>.
    </div>
</page>
