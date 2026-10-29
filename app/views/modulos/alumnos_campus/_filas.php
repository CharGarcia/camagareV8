<?php
/**
 * Filas del listado de Campus. Lo incluyen la vista (carga inicial) y
 * AlumnosCampusController::searchAjax(), para que ambas pinten lo mismo.
 *
 * @var array $rows
 */
$urlAlumnos = BASE_URL . '/modulos/alumnos';
?>
<?php if (empty($rows)): ?>
    <tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-geo-alt fs-3 d-block mb-2"></i>No se encontraron campus.</td></tr>
<?php else: ?>
    <?php foreach ($rows as $r): ?>
        <?php
        $activo  = ($r['estado'] ?? 'activo') === 'activo';
        $alumnos = (int) ($r['alumnos'] ?? 0);
        ?>
        <tr class="campus-row" role="button" tabindex="0" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="abrirModalCampusEditar(this)">
            <td class="ps-3 fw-bold" data-col="nombre"><?= htmlspecialchars((string) ($r['nombre'] ?? '')) ?></td>
            <td class="text-muted small text-truncate" style="max-width:350px" data-col="direccion"><?= htmlspecialchars((string) ($r['direccion'] ?? '')) ?></td>
            <td class="text-end" data-col="alumnos">
                <?php if ($alumnos > 0): ?>
                    <a href="<?= $urlAlumnos ?>?b=<?= urlencode('campus:' . (int) $r['id']) ?>" onclick="event.stopPropagation()" title="Ver los alumnos de este campus"><?= $alumnos ?></a>
                <?php else: ?>
                    <span class="text-muted">0</span>
                <?php endif; ?>
            </td>
            <td class="text-center pe-3" data-col="estado">
                <span class="badge bg-<?= $activo ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $activo ? 'success' : 'secondary' ?> border border-<?= $activo ? 'success' : 'secondary' ?> border-opacity-25"><?= $activo ? 'Activo' : 'Inactivo' ?></span>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
