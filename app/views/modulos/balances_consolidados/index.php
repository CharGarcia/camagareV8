<?php
/** @var array $perm @var string $rutaModulo @var bool $hayGrupo */
$base = BASE_URL;
$rutaUrl = $base . '/' . $rutaModulo;
?>
<script>document.body.classList.add('cmg-no-app-shell');</script>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-diagram-3 text-primary me-2"></i>Balances Consolidados</h4>
        <p class="text-muted mb-0 small">Define qué cuenta de cada establecimiento del mismo RUC representa el mismo concepto contable, para que Estados Financieros y Balance de Comprobación puedan mostrarlos sumados. El mapeo es manual — el sistema nunca adivina equivalencias por código de cuenta.</p>
    </div>
    <?php if (!empty($perm['crear'])): ?>
    <button type="button" class="btn btn-primary btn-sm ms-auto" id="bc-btn-nuevo" <?= $hayGrupo ? '' : 'disabled' ?>><i class="bi bi-plus-lg me-1"></i>Nuevo</button>
    <?php endif; ?>
</div>

<?php if (!$hayGrupo): ?>
    <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>Esta empresa es la única del RUC a la que tienes acceso, así que no hay con qué consolidar. Este módulo aplica cuando el RUC tiene más de un establecimiento.</div>
<?php else: ?>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Concepto consolidado</th>
                        <th>Tipo</th>
                        <th>Cuentas mapeadas</th>
                        <th class="text-center" style="width:110px">Acciones</th>
                    </tr>
                </thead>
                <tbody id="bc-tbody">
                    <tr><td colspan="4" class="text-center text-muted py-4">Cargando…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════ MODAL: Crear/Editar grupo ═══════════════════ -->
<div class="modal fade" id="modalGrupoBC" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-diagram-3 me-2"></i><span id="bc-modal-titulo">Nuevo grupo consolidado</span></h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="bc-id-grupo">
                <div class="row g-2 mb-3">
                    <div class="col-8">
                        <label class="form-label small fw-bold mb-1">Nombre del concepto <span class="text-danger">*</span></label>
                        <input type="text" id="bc-nombre" class="form-control form-control-sm shadow-none" placeholder="Ej. Caja General" maxlength="150">
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-bold mb-1">Tipo <span class="text-danger">*</span></label>
                        <select id="bc-tipo" class="form-select form-select-sm shadow-none">
                            <option value="ACTIVO">Activo</option>
                            <option value="PASIVO">Pasivo</option>
                            <option value="PATRIMONIO">Patrimonio</option>
                            <option value="INGRESO">Ingreso</option>
                            <option value="COSTO">Costo</option>
                            <option value="GASTO">Gasto</option>
                        </select>
                    </div>
                </div>
                <div class="alert alert-warning small py-2 mb-3" id="bc-aviso-patrimonio" style="display:none;">
                    <i class="bi bi-exclamation-triangle me-1"></i> Las cuentas de patrimonio (capital, resultados) no siempre deben sumarse entre establecimientos — verifícalo con tu contador antes de mapear.
                </div>
                <label class="form-label small fw-bold mb-1">Cuenta equivalente por establecimiento (deja en blanco el que no aplique)</label>
                <div class="table-responsive" style="max-height:340px;overflow-y:auto;">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th style="width:35%">Establecimiento</th><th>Cuenta</th></tr></thead>
                        <tbody id="bc-tbody-establecimientos"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm px-4" onclick="window.BC_guardar()"><i class="bi bi-check-lg me-1"></i>Guardar</button>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
    const BC_URL_BASE = "<?= $rutaUrl ?>";
    const BC_PERM_ACTUALIZAR = <?= !empty($perm['actualizar']) ? 'true' : 'false' ?>;
    const BC_PERM_ELIMINAR = <?= !empty($perm['eliminar']) ? 'true' : 'false' ?>;
    window.BASE_URL = '<?= $base ?>';
</script>
<script src="<?= $base ?>/js/modulos/balances_consolidados.js?v=<?= time() ?>"></script>
