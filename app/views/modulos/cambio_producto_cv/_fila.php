<?php
/**
 * Una fila del listado de Cambios de productos: a la izquierda el producto que ENTRA
 * (devolución) y a la derecha el que SALE (entrega), emparejados por orden dentro del cambio
 * (ver CambioProductoCvRepository::getListado). Si un lado tiene más líneas que el otro, las
 * celdas del lado corto quedan vacías; Cliente y Observaciones son del cambio y van siempre.
 *
 * Lo incluyen index.php (carga inicial) y CambioProductoCvController::renderFila() (refresco
 * AJAX). Única fuente del HTML de la fila: duplicarlo dejaría uno de los dos caminos desfasado.
 *
 * @var array $r       Fila de CambioProductoCvRepository::getListado()
 * @var int   $decCant Decimales de cantidad de la empresa
 */
$h = static fn($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

$cantidad = static fn($v): string => ($v === null || $v === '') ? '' : number_format((float) $v, (int) ($decCant ?? 2));

// Producto con su código debajo; vacío si ese lado no tiene línea en esta fila.
$producto = static function ($nombre, $codigo) use ($h): string {
    if (($nombre ?? '') === '' && ($codigo ?? '') === '') {
        return '';
    }
    return '<div class="text-truncate cam-producto" title="' . $h($nombre) . '">' . $h($nombre) . '</div>'
         . (($codigo ?? '') !== '' ? '<div class="small text-muted">' . $h($codigo) . '</div>' : '');
};

// Documento de origen de lo que entra: la factura de venta o, si viene de un cambio anterior, ese cambio.
$factura = '';
if (($r['dev_origen_numero'] ?? '') !== '') {
    $factura = ($r['dev_origen_tipo'] ?? '') === 'CAMBIO'
        ? '<span class="small text-muted">Cambio</span> ' . $h($r['dev_origen_numero'])
        : $h($r['dev_origen_numero']);
}

// El listado ya no tiene columna Estado: un cambio anulado o en borrador se distingue en la fila.
$estado      = (string) ($r['estado'] ?? '');
$claseEstado = ['Anulada' => ' cam-fila-anulada', 'Borrador' => ' cam-fila-borrador'][$estado] ?? '';
$tituloFila  = ['Anulada' => 'Cambio anulado', 'Borrador' => 'Cambio en borrador'][$estado] ?? '';

// abrirModalCambioVer() solo necesita estos datos del cambio.
$dataRow = $h(json_encode([
    'id'         => (int) ($r['id'] ?? 0),
    'estado'     => $estado,
    'serie'      => (string) ($r['serie'] ?? ''),
    'secuencial' => (string) ($r['secuencial'] ?? ''),
]));
?>
<tr class="cambio-row<?= $claseEstado ?>" role="button" tabindex="0" data-row="<?= $dataRow ?>" onclick="abrirModalCambioVer(this)"<?= $tituloFila !== '' ? ' title="' . $tituloFila . '"' : '' ?>>
    <td class="ps-3 text-end" data-col="dev_cantidad"><?= $cantidad($r['dev_cantidad'] ?? null) ?></td>
    <td data-col="dev_producto"><?= $producto($r['dev_producto_nombre'] ?? null, $r['dev_producto_codigo'] ?? null) ?></td>
    <td data-col="dev_lote"><?= $h($r['dev_lote'] ?? '') ?></td>
    <td data-col="dev_bodega"><?= $h($r['dev_bodega'] ?? '') ?></td>
    <td class="text-nowrap" data-col="dev_factura"><?= $factura ?></td>
    <td class="text-end cam-lado-sale" data-col="ent_cantidad"><?= $cantidad($r['ent_cantidad'] ?? null) ?></td>
    <td data-col="ent_producto"><?= $producto($r['ent_producto_nombre'] ?? null, $r['ent_producto_codigo'] ?? null) ?></td>
    <td data-col="ent_lote"><?= $h($r['ent_lote'] ?? '') ?></td>
    <td data-col="ent_bodega"><?= $h($r['ent_bodega'] ?? '') ?></td>
    <td class="text-truncate" style="max-width:230px" data-col="cliente" title="<?= $h($r['cliente_nombre'] ?? '') ?>"><?= $h($r['cliente_nombre'] ?? '') ?></td>
    <td class="text-truncate pe-3" style="max-width:260px" data-col="observaciones" title="<?= $h($r['observaciones'] ?? '') ?>"><?= $h($r['observaciones'] ?? '') ?></td>
</tr>
