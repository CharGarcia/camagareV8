<?php
/**
 * Una fila del listado de Ingresos.
 *
 * Lo incluyen index.php (carga inicial) e IngresosController::renderFila() (refresco AJAX
 * de búsqueda / orden / paginación y la fila del ingreso recién guardado). Única fuente
 * del HTML de la fila: duplicarlo dejaría uno de los caminos desfasado al tocar una columna.
 * `data-id` es lo que usa la vista para ubicar la fila del ingreso guardado.
 *
 * @var array $r Fila de IngresoRepository::getListado() / getFilaListado()
 */
$tipoLabel = \App\Helpers\TipoDocumentoHelper::ingresoLabel(
    $r['tipos_detalle'] ?? null,
    $r['tipo_ingreso'] ?? null,
    $r['concepto_nombre'] ?? null
);
$estado = $r['estado'] ?? 'registrado';
$estadoClass = match ($estado) {
    'anulado'  => 'bg-danger bg-opacity-10 text-danger border-danger',
    'borrador' => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
    default    => 'bg-success bg-opacity-10 text-success border-success',
};
?>
<tr class="ingreso-row" role="button" tabindex="0" data-id="<?= (int) $r['id'] ?>" onclick="abrirModalIngresoVer(<?= (int) $r['id'] ?>)">
    <td class="ps-3" data-col="numero_ingreso"><code class="text-secondary"><?= htmlspecialchars($r['numero_ingreso'] ?? '') ?></code></td>
    <td data-col="fecha_emision"><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—' ?></td>
    <td data-col="tipo_ingreso"><span class="badge bg-light text-dark border"><?= htmlspecialchars($tipoLabel) ?></span></td>
    <td class="fw-medium text-truncate" data-col="recibo_de" style="max-width:200px"><?= htmlspecialchars($r['recibo_de'] ?? $r['cliente_nombre'] ?? $r['concepto_nombre'] ?? '—') ?></td>
    <td data-col="observaciones" class="text-truncate text-muted" style="max-width:200px"><?= htmlspecialchars($r['observaciones'] ?? '') ?></td>
    <td class="text-end fw-bold" data-col="monto_total">$<?= number_format((float) ($r['monto_total'] ?? 0), 2) ?></td>
    <td class="text-center pe-3" data-col="estado"><span class="badge <?= $estadoClass ?> border border-opacity-25"><?= ucfirst($estado) ?></span></td>
</tr>
