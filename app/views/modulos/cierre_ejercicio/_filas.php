<?php
/**
 * Filas del listado de Cierre del Ejercicio. Lo usan la carga inicial (index.php) y la
 * búsqueda AJAX (CierreEjercicioController::searchAjax), para que pinten exactamente igual.
 *
 * @var array $rows Filas ya formateadas por CierreEjercicioController::formatearFila().
 */
$n = static fn($v) => number_format((float) $v, 2, ',', '.');
if (empty($rows)): ?>
    <tr>
        <td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-journal-x fs-3 d-block mb-2"></i>No se encontraron cierres del ejercicio.</td>
    </tr>
<?php else:
    foreach ($rows as $r):
        $vigente = ($r['estado'] ?? '') === 'vigente';
        $cambios = (int) ($r['cambios_posteriores'] ?? 0); ?>
    <tr class="cierre-row" role="button" tabindex="0" data-id="<?= (int) $r['id'] ?>" onclick="CE_detalle(<?= (int) $r['id'] ?>)">
        <td class="ps-3 fw-medium" data-col="anio"><?= (int) $r['anio'] ?></td>
        <td data-col="fecha_cierre"><?= htmlspecialchars($r['fmt_fecha_cierre']) ?></td>
        <td data-col="saldos_desde"><?= htmlspecialchars($r['fmt_saldos_desde']) ?></td>
        <td data-col="asiento_cierre"><code class="text-secondary"><?= htmlspecialchars((string) ($r['numero_cierre'] ?? '—')) ?></code></td>
        <td data-col="asiento_apertura"><code class="text-secondary"><?= htmlspecialchars((string) ($r['numero_apertura'] ?? '—')) ?></code></td>
        <td class="text-end<?= (float) $r['resultado'] < 0 ? ' text-danger' : '' ?>" data-col="resultado"><?= $n($r['resultado']) ?></td>
        <td class="text-end" data-col="activos"><?= $n($r['total_activos']) ?></td>
        <td class="text-end" data-col="patrimonio"><?= $n($r['total_patrimonio']) ?></td>
        <td class="text-center" data-col="estado">
            <?php if ($vigente): ?>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Vigente</span>
                <?php if ($cambios > 0): ?>
                    <i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="<?= $cambios ?> asiento(s) del año cambiaron después del cierre"></i>
                <?php endif; ?>
            <?php else: ?>
                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Revertido</span>
            <?php endif; ?>
        </td>
        <td class="pe-3 text-truncate" style="max-width:220px" data-col="registrado"><?= htmlspecialchars($r['fmt_created_at']) ?> · <?= htmlspecialchars((string) ($r['creado_por_nombre'] ?? '')) ?></td>
    </tr>
<?php endforeach;
endif;
