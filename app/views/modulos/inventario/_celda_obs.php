<?php
/**
 * Celda «Obs.» del listado de Movimientos de Inventario (la usan index.php y searchAjax).
 *
 * Primer renglón: la observación del movimiento (p. ej. "Salida por Factura # 001-101-…").
 * Segundo renglón, en gris con ícono: la observación general de la carga de inventario,
 * si el movimiento viene de una. Cada renglón muestra hasta 2 líneas; el texto completo
 * queda en el `title` (al pasar el cursor).
 *
 * @var array $row Fila de InventarioRepository::getKardex() (obs_movimiento, obs_carga, observaciones).
 */
$obsMov   = trim((string) ($row['obs_movimiento'] ?? $row['observaciones'] ?? ''));
$obsCarga = trim((string) ($row['obs_carga'] ?? ''));
$obsTitle = trim((string) ($row['observaciones'] ?? ''));
?>
<td class="small inv-obs" data-col="observaciones" title="<?= htmlspecialchars($obsTitle) ?>">
    <?php if ($obsMov === '' && $obsCarga === ''): ?>
        <span class="text-muted">-</span>
    <?php else: ?>
        <?php if ($obsMov !== ''): ?>
            <div class="inv-obs-texto"><?= htmlspecialchars($obsMov) ?></div>
        <?php endif; ?>
        <?php if ($obsCarga !== ''): ?>
            <div class="inv-obs-texto text-muted"><i class="bi bi-box-seam me-1"></i><?= htmlspecialchars($obsCarga) ?></div>
        <?php endif; ?>
    <?php endif; ?>
</td>
