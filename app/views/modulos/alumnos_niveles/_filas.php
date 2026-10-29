<?php
/**
 * Filas del listado de Niveles / Cursos. Lo incluyen la vista (carga inicial) y
 * AlumnosNivelesController::searchAjax(), para que ambas pinten lo mismo.
 *
 * @var array $rows
 */
$urlAlumnos = BASE_URL . '/modulos/alumnos';
?>
<?php if (empty($rows)): ?>
    <tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-mortarboard fs-3 d-block mb-2"></i>No se encontraron niveles/cursos.</td></tr>
<?php else: ?>
    <?php foreach ($rows as $r): ?>
        <?php
        $activo  = ($r['estado'] ?? 'activo') === 'activo';
        $alumnos = (int) ($r['alumnos'] ?? 0);
        ?>
        <tr class="nivel-row" role="button" tabindex="0" data-row='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>' onclick="abrirModalNivelEditar(this)">
            <td class="ps-3 text-end text-muted" data-col="orden"><?= (int) ($r['orden'] ?? 0) ?></td>
            <td class="fw-bold" data-col="nombre"><?= htmlspecialchars((string) ($r['nombre'] ?? '')) ?></td>
            <td class="text-end" data-col="alumnos">
                <?php if ($alumnos > 0): ?>
                    <a href="<?= $urlAlumnos ?>?b=<?= urlencode('nivel:' . (int) $r['id']) ?>" onclick="event.stopPropagation()" title="Ver los alumnos de este nivel/curso"><?= $alumnos ?></a>
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
