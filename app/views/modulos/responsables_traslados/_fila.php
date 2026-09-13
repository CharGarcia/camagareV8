<?php
/**
 * Una fila del listado de Responsables de Traslado.
 *
 * Lo incluyen index.php (carga inicial) y ResponsablesTrasladoController::renderFila()
 * (refresco AJAX de la búsqueda/paginación). Única fuente del HTML de la fila:
 * si se duplicara, al cambiar una columna el otro camino quedaría desfasado.
 *
 * @var array $r Fila del listado (nombre, identificacion, telefono, email, estado,
 *                created_at, usuarios_vinculados)
 */
$estado      = (string) ($r['estado'] ?? 'activo');
$estadoClass = $estado === 'activo'
    ? 'bg-success bg-opacity-10 text-success border-success'
    : 'bg-secondary bg-opacity-10 text-secondary border-secondary';
$rowData = htmlspecialchars(json_encode($r, JSON_FLAGS), ENT_QUOTES, 'UTF-8');
$fecha   = !empty($r['created_at']) ? date('d-m-Y H:i:s', strtotime((string) $r['created_at'])) : '-';

// Usuarios vinculados. Un responsable ACTIVO sin ningún usuario vinculado es el
// caso que conviene ver de un vistazo: si ese repartidor entra a la app móvil sin
// "acceso total" en Entregas, no le aparece ninguna entrega.
$nUsuarios = (int) ($r['usuarios_vinculados'] ?? 0);
$avisoSinUsuarios = ($nUsuarios === 0 && $estado === 'activo');
?>
<tr class="resp-row" role="button" tabindex="0" data-row='<?= $rowData ?>' onclick="RT_abrirEditar(this)">
    <td class="ps-3 fw-medium text-truncate" style="max-width:280px" data-col="nombre"><?= htmlspecialchars((string) ($r['nombre'] ?? '')) ?></td>
    <td data-col="identificacion"><code class="text-secondary"><?= htmlspecialchars((string) ($r['identificacion'] ?? '-')) ?></code></td>
    <td data-col="telefono"><?= htmlspecialchars((string) ($r['telefono'] ?? '-')) ?></td>
    <td class="text-truncate" style="max-width:220px" data-col="email"><?= htmlspecialchars((string) ($r['email'] ?? '-')) ?></td>
    <td data-col="created_at"><small class="text-muted"><?= $fecha ?></small></td>
    <td class="text-center" data-col="usuarios_vinculados">
        <?php if ($nUsuarios > 0): ?>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"
                  title="<?= $nUsuarios ?> usuario(s) ven las entregas de este responsable en la app móvil"><?= $nUsuarios ?></span>
        <?php elseif ($avisoSinUsuarios): ?>
            <span class="text-warning" title="Ningún usuario vinculado: si este repartidor usa la app móvil sin acceso total, no verá ninguna entrega">
                <i class="bi bi-exclamation-triangle"></i> 0
            </span>
        <?php else: ?>
            <span class="text-muted">0</span>
        <?php endif; ?>
    </td>
    <td class="text-center pe-3" data-col="estado">
        <span class="badge <?= $estadoClass ?> border border-opacity-25"><?= ucfirst($estado) ?></span>
    </td>
</tr>
