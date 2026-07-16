<?php
/**
 * Vista de Asignación masiva de submódulos (superadmin).
 * Permite asignar UN submódulo a muchos usuarios/empresas de una sola vez.
 */
$base = BASE_URL;
$catalogo = $catalogo ?? [];
$empresas = $empresas ?? [];
$msg = $_SESSION['config_msg'] ?? null;
unset($_SESSION['config_msg']);
?>

<style>
.asigsub-preview-wrap {
    max-height: 360px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: .375rem;
}
.asigsub-preview-wrap thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8f9fa;
    box-shadow: inset 0 -1px 0 #dee2e6;
}
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-people-fill"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Asigne un submódulo nuevo a varios usuarios de una sola vez.</p>
    </div>
    <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($msg[1]) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form id="form-asigsub" onsubmit="return false;">

            <!-- 1. Submódulo -->
            <div class="mb-4">
                <label class="form-label fw-semibold small text-uppercase text-muted">1. Submódulo a asignar</label>
                <select id="asigsub-submodulo" class="form-select">
                    <option value="">Seleccione un submódulo...</option>
                    <?php foreach ($catalogo as $mod): ?>
                        <optgroup label="<?= htmlspecialchars($mod['nombre_modulo']) ?>">
                            <?php foreach ($mod['submodulos'] as $sub): ?>
                                <?php $busqueda = $mod['nombre_modulo'] . ' ' . $sub['nombre_submodulo']; ?>
                                <option value="<?= (int) $sub['id_submodulo'] ?>" data-id-modulo="<?= (int) $mod['id_modulo'] ?>" data-data="<?= htmlspecialchars(json_encode(['search' => $busqueda]), ENT_QUOTES) ?>">
                                    <?= htmlspecialchars($sub['nombre_submodulo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 2. Destinatarios -->
            <div class="mb-4">
                <label class="form-label fw-semibold small text-uppercase text-muted">2. Destinatarios</label>
                <div class="d-flex flex-column gap-2">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="asigsub-modo" id="modo-usuarios" value="usuarios" checked>
                        <label class="form-check-label" for="modo-usuarios">Usuarios específicos</label>
                    </div>
                    <div id="bloque-usuarios" class="ms-4 mb-2" style="max-width:520px">
                        <select id="asigsub-usuarios" multiple placeholder="Buscar usuarios..."></select>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="asigsub-modo" id="modo-admin" value="admin">
                        <label class="form-check-label" for="modo-admin">Todos los administradores (nivel 2)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="asigsub-modo" id="modo-usuario" value="usuario">
                        <label class="form-check-label" for="modo-usuario">Todos los usuarios (nivel 1)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="asigsub-modo" id="modo-todos" value="todos">
                        <label class="form-check-label" for="modo-todos">Todos (administradores + usuarios)</label>
                    </div>

                    <div id="bloque-empresa-filtro" class="ms-4 mb-2" style="max-width:420px">
                        <label class="form-label small text-muted mb-1">Limitar a una empresa (opcional)</label>
                        <select id="asigsub-empresa-filtro" class="form-select form-select-sm">
                            <option value="">Todas las empresas del usuario</option>
                            <?php foreach ($empresas as $e): ?>
                                <option value="<?= (int) $e['id_empresa'] ?>"><?= htmlspecialchars($e['nombre_comercial'] ?? $e['ruc'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="asigsub-modo" id="modo-empresa" value="empresa">
                        <label class="form-check-label" for="modo-empresa">Todos los usuarios de una empresa</label>
                    </div>
                    <div id="bloque-empresa" class="ms-4 mb-2 d-none" style="max-width:420px">
                        <select id="asigsub-empresa" class="form-select form-select-sm">
                            <option value="">Seleccione empresa...</option>
                            <?php foreach ($empresas as $e): ?>
                                <option value="<?= (int) $e['id_empresa'] ?>"><?= htmlspecialchars($e['nombre_comercial'] ?? $e['ruc'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 3. Permisos -->
            <div class="mb-4">
                <label class="form-label fw-semibold small text-uppercase text-muted">3. Permisos a otorgar</label>
                <div class="d-flex flex-wrap gap-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="perm-ver" checked>
                        <label class="form-check-label" for="perm-ver">Ver</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="perm-crear" checked>
                        <label class="form-check-label" for="perm-crear">Crear</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="perm-actualizar" checked>
                        <label class="form-check-label" for="perm-actualizar">Actualizar</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="perm-eliminar" checked>
                        <label class="form-check-label" for="perm-eliminar">Eliminar</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input border-warning" type="checkbox" id="perm-t" checked>
                        <label class="form-check-label" for="perm-t">Acceso total (ver todos los registros)</label>
                    </div>
                </div>
            </div>

            <!-- 4. Opciones -->
            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="asigsub-sobrescribir">
                    <label class="form-check-label" for="asigsub-sobrescribir">
                        Sobrescribir si el usuario ya tiene este submódulo asignado con otros permisos
                    </label>
                </div>
            </div>

            <div id="asigsub-error" class="alert alert-danger d-none small mb-3"></div>

            <div class="d-flex gap-2">
                <button type="button" id="btn-previsualizar" class="btn btn-outline-primary">
                    <i class="bi bi-eye"></i> Previsualizar
                </button>
                <button type="button" id="btn-aplicar" class="btn btn-primary" disabled>
                    <i class="bi bi-check2-circle"></i> Aplicar asignación
                </button>
            </div>
        </form>

        <div id="asigsub-resultado" class="mt-4 d-none">
            <div id="asigsub-totales" class="alert alert-light border small mb-2"></div>
            <div class="asigsub-preview-wrap">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Usuario</th>
                            <th>Empresa</th>
                            <th class="text-center" style="width:140px">Estado</th>
                        </tr>
                    </thead>
                    <tbody id="asigsub-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?= $base ?>/js/config/asignacion_submodulos.js?v=<?= @filemtime(MVC_ROOT . '/public/js/config/asignacion_submodulos.js') ?: time() ?>"></script>
