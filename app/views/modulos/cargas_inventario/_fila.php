<?php
/**
 * Una fila del listado de Cargas de Inventario.
 *
 * Lo incluyen index.php (carga inicial) y CargasInventarioController::renderFila()
 * (refresco AJAX de búsqueda / orden / paginación). Única fuente del HTML de la
 * fila: duplicarlo dejaría uno de los dos caminos desfasado al tocar una columna.
 *
 * @var array $r Fila del listado (numero, fecha, tipo_movimiento, total_lineas,
 *               estado, validada, observacion, creado_por_nombre, aprobado_por_nombre,
 *               motivo_anulacion)
 */
$estado = (string) ($r['estado'] ?? 'pendiente');
$estadoClass = match ($estado) {
    'aprobada'  => 'bg-success bg-opacity-10 text-success border-success',
    'rechazada' => 'bg-danger bg-opacity-10 text-danger border-danger',
    'anulada'   => 'bg-secondary bg-opacity-10 text-secondary border-secondary',
    default     => 'bg-warning bg-opacity-10 text-warning border-warning',
};
$estadoTexto = ['pendiente' => 'Pendiente', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada', 'anulada' => 'Anulada'][$estado] ?? ucfirst($estado);
// El motivo de la anulación se lee al pasar el cursor sobre el estado.
$estadoTitulo = $estado === 'anulada' ? trim((string) ($r['motivo_anulacion'] ?? '')) : '';

$tipo = (string) ($r['tipo_movimiento'] ?? '');
$tipoColor = match ($tipo) { 'entrada' => 'success', 'salida' => 'danger', default => 'secondary' };

// Una carga pendiente con líneas en error no se puede aprobar: conviene verlo
// en el listado sin abrir el detalle.
$conError = ($estado === 'pendiente') && (empty($r['validada']) || $r['validada'] === 'f');

// En las cargas migradas la observación es una línea técnica larguísima de la que
// solo sirve la referencia; el texto completo queda en el tooltip de la celda.
$observacionCompleta = trim((string) ($r['observacion'] ?? ''));
$observacion = \App\Helpers\ObservacionCargaInventario::paraMostrar($observacionCompleta);
?>
<tr class="carga-row" role="button" tabindex="0" onclick="CI_verDetalle(<?= (int) $r['id'] ?>)">
    <td class="ps-3 fw-bold" data-col="numero">#<?= (int) $r['numero'] ?></td>
    <td data-col="fecha"><?= !empty($r['fecha']) ? date('d-m-Y', strtotime((string) $r['fecha'])) : '-' ?></td>
    <td data-col="tipo">
        <span class="badge bg-<?= $tipoColor ?> bg-opacity-10 text-<?= $tipoColor ?> border border-<?= $tipoColor ?> border-opacity-25"><?= htmlspecialchars(ucfirst($tipo)) ?></span>
    </td>
    <td class="text-center" data-col="lineas"><?= (int) ($r['total_lineas'] ?? 0) ?></td>
    <td class="text-center" data-col="estado">
        <span class="badge <?= $estadoClass ?> border border-opacity-25"<?= $estadoTitulo !== '' ? ' title="' . htmlspecialchars('Motivo: ' . $estadoTitulo) . '"' : '' ?>><?= $estadoTexto ?></span>
        <?php if ($conError): ?>
            <i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="Tiene líneas con error; no se puede aprobar"></i>
        <?php endif; ?>
    </td>
    <td class="small text-muted" data-col="creado"><?= htmlspecialchars((string) ($r['creado_por_nombre'] ?? '-')) ?></td>
    <td class="small text-muted" data-col="aprobado"><?= htmlspecialchars((string) ($r['aprobado_por_nombre'] ?? '-')) ?></td>
    <td class="text-truncate pe-3" style="max-width:320px" data-col="observacion"
        title="<?= htmlspecialchars($observacionCompleta) ?>"><?= $observacion !== '' ? htmlspecialchars($observacion) : '-' ?></td>
</tr>
