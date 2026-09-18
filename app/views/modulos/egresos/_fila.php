<?php
/**
 * Una fila del listado de Egresos.
 *
 * Lo incluyen index.php (carga inicial) y EgresosController::renderFila() (refresco AJAX
 * de búsqueda / orden / paginación y la fila del egreso recién guardado). Única fuente
 * del HTML de la fila: duplicarlo dejaría uno de los caminos desfasado al tocar una columna.
 * `data-id` es lo que usa la vista para ubicar la fila del egreso guardado.
 *
 * @var array $r Fila de EgresoRepository::getListado() / getFilaListado()
 */
$tipoLabel = \App\Helpers\TipoDocumentoHelper::egresoLabel(
    $r['tipos_detalle'] ?? null,
    $r['tipo_egreso'] ?? null,
    $r['concepto_nombre'] ?? null
);
$estado = $r['estado'] ?? 'registrado';
$estadoClass = match ($estado) {
    'anulado' => 'bg-danger bg-opacity-10 text-danger border-danger',
    default   => 'bg-primary bg-opacity-10 text-primary border-primary',
};
?>
<tr class="egreso-row" role="button" data-id="<?= (int) $r['id'] ?>" onclick="abrirModalEgresoVer(<?= (int) $r['id'] ?>)">
    <td class="ps-3" data-col="numero_egreso"><code class="text-secondary"><?= htmlspecialchars($r['numero_egreso'] ?? '') ?></code></td>
    <td data-col="fecha_emision"><?= !empty($r['fecha_emision']) ? date('d-m-Y', strtotime($r['fecha_emision'])) : '—' ?></td>
    <td data-col="tipo_egreso"><span class="badge bg-light text-dark border"><?= htmlspecialchars($tipoLabel) ?></span></td>
    <td class="fw-medium text-truncate" data-col="sujeto_nombre" style="max-width:200px"><?= htmlspecialchars($r['sujeto_nombre'] ?? '') ?></td>
    <td class="text-truncate text-muted" data-col="observaciones" style="max-width:200px"><?= htmlspecialchars($r['observaciones'] ?? '') ?></td>
    <td class="text-end fw-bold" data-col="monto_total">$<?= number_format((float) ($r['monto_total'] ?? 0), 2) ?></td>
    <td class="text-center pe-3" data-col="estado"><span class="badge <?= $estadoClass ?> border border-opacity-25"><?= ucfirst($estado) ?></span></td>
</tr>
