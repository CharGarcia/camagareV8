<?php
/** @var string $titulo */
/** @var array $rows */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var string $buscar */
$base = BASE_URL;
$rows = $rows ?? [];
$ordenCol = $ordenCol ?? 'orden';
$ordenDir = $ordenDir ?? 'asc';
$buscar = $buscar ?? '';
$msg = $_SESSION['ia_agentes_msg'] ?? null;
unset($_SESSION['ia_agentes_msg']);
?>
<style>
.ia-agente-row { cursor: pointer; }
.ia-agente-row:hover { background-color: rgba(0,0,0,.04); }
.ia-agentes-scroll { max-height: calc(100dvh - 280px); overflow-y: auto; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-robot"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Plantillas de prompts que usan las empresas en el módulo IA Soporte. Haga clic en una fila para editar.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoAgente"><i class="bi bi-plus-lg"></i> Crear nuevo</button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="ia-agentes-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px;"></th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th class="text-center">Orden</th>
                        <th class="text-center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr class="ia-agente-row" role="button" tabindex="0"
                        data-id="<?= (int) $r['id'] ?>"
                        data-nombre="<?= htmlspecialchars($r['nombre'] ?? '') ?>"
                        data-descripcion="<?= htmlspecialchars($r['descripcion'] ?? '') ?>"
                        data-icono="<?= htmlspecialchars($r['icono'] ?? 'bi-robot') ?>"
                        data-prompt="<?= htmlspecialchars($r['prompt_sistema'] ?? '') ?>"
                        data-orden="<?= (int) ($r['orden'] ?? 0) ?>"
                        data-activo="<?= !empty($r['activo']) ? 1 : 0 ?>">
                        <td class="text-center"><i class="bi <?= htmlspecialchars($r['icono'] ?? 'bi-robot') ?>"></i></td>
                        <td class="fw-medium"><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                        <td class="text-truncate" style="max-width:360px;"><?= htmlspecialchars($r['descripcion'] ?? '') ?></td>
                        <td class="text-center"><?= (int) ($r['orden'] ?? 0) ?></td>
                        <td class="text-center">
                            <?php if (!empty($r['activo'])): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($rows)): ?>
            <p class="text-muted text-center py-4 mb-0">No hay agentes registrados.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Nuevo agente -->
<div class="modal fade" id="modalNuevoAgente" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/iaAgentesStore">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nuevo agente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control" required placeholder="Ej: Agente Tributario">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ícono (Bootstrap Icons)</label>
                            <input type="text" name="icono" class="form-control" placeholder="bi-calculator" value="bi-robot">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Orden</label>
                            <input type="number" name="orden" class="form-control" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <input type="text" name="descripcion" class="form-control" placeholder="Resumen corto de la especialidad">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Prompt del sistema <span class="text-danger">*</span></label>
                            <textarea name="prompt_sistema" class="form-control" rows="8" required placeholder="Instrucciones que definen el rol y comportamiento del agente..."></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="activo" value="1" id="new-activo" checked>
                                <label class="form-check-label" for="new-activo">Activo (visible para las empresas)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar agente -->
<div class="modal fade" id="modalEditarAgente" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/iaAgentesUpdate" id="formEditarAgente">
                <input type="hidden" name="id" id="edit-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar agente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="edit-nombre" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ícono (Bootstrap Icons)</label>
                            <input type="text" name="icono" id="edit-icono" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Orden</label>
                            <input type="number" name="orden" id="edit-orden" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <input type="text" name="descripcion" id="edit-descripcion" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Prompt del sistema <span class="text-danger">*</span></label>
                            <textarea name="prompt_sistema" id="edit-prompt" class="form-control" rows="10" required></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="activo" value="1" id="edit-activo">
                                <label class="form-check-label" for="edit-activo">Activo (visible para las empresas)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="submit" form="formEliminarAgente" class="btn btn-outline-danger"><i class="bi bi-trash"></i> Eliminar</button>
                    <div>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<form method="POST" action="<?= $base ?>/config/iaAgentesDelete" id="formEliminarAgente" onsubmit="return confirm('¿Eliminar este agente? Las conversaciones ya creadas con él no se verán afectadas, pero dejará de estar disponible para nuevas conversaciones.');">
    <input type="hidden" name="id" id="delete-id" value="">
</form>

<script>
(function() {
    var modal = document.getElementById('modalEditarAgente');
    var form = modal ? modal.querySelector('form') : null;
    if (!modal || !form) return;

    document.querySelectorAll('.ia-agente-row').forEach(function(row) {
        row.addEventListener('click', function() {
            form.querySelector('#edit-id').value = this.dataset.id || '';
            form.querySelector('#edit-nombre').value = this.dataset.nombre || '';
            form.querySelector('#edit-descripcion').value = this.dataset.descripcion || '';
            form.querySelector('#edit-icono').value = this.dataset.icono || 'bi-robot';
            form.querySelector('#edit-prompt').value = this.dataset.prompt || '';
            form.querySelector('#edit-orden').value = this.dataset.orden || '0';
            form.querySelector('#edit-activo').checked = this.dataset.activo === '1';
            document.getElementById('delete-id').value = this.dataset.id || '';
            new bootstrap.Modal(modal).show();
        });
        row.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
})();
</script>
