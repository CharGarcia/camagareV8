<?php
/** @var string $titulo */
/** @var array $rowsProvincias */
/** @var array $rowsCiudades */
/** @var array $provinciasParaSelect */
/** @var string $ordenColProv */
/** @var string $ordenDirProv */
/** @var string $buscarProv */
/** @var string $ordenColCiud */
/** @var string $ordenDirCiud */
/** @var string $buscarCiud */
/** @var string $filtroProv */
$base = BASE_URL;
$rowsProvincias = $rowsProvincias ?? [];
$rowsCiudades = $rowsCiudades ?? [];
$provinciasParaSelect = $provinciasParaSelect ?? [];
$ordenColProv = $ordenColProv ?? 'nombre';
$ordenDirProv = $ordenDirProv ?? 'asc';
$buscarProv = $buscarProv ?? '';
$ordenColCiud = $ordenColCiud ?? 'nombre';
$ordenDirCiud = $ordenDirCiud ?? 'asc';
$buscarCiud = $buscarCiud ?? '';
$filtroProv = $filtroProv ?? '';
$tabActivo = trim($_GET['tab'] ?? 'provincias');
if (!in_array($tabActivo, ['provincias', 'ciudades'], true)) {
    $tabActivo = 'provincias';
}
$msg = $_SESSION['provincia_ciudad_msg'] ?? null;
unset($_SESSION['provincia_ciudad_msg']);

$urlBase = rtrim($base, '/') . '/config/provincia-ciudad';
$urlCiudades = $urlBase . '/ciudades';

function thSort($urlBase, $tab, $col, $label, $ordenCol, $ordenDir, $buscar, $filtro = '') {
    $dir = ($ordenCol === $col && strtolower($ordenDir) === 'asc') ? 'desc' : 'asc';
    $params = [];
    if ($col !== 'nombre') $params['sort_' . $tab] = $col;
    if ($dir !== 'asc') $params['dir_' . $tab] = $dir;
    if ($tab === 'prov' && $buscar !== '') $params['b_prov'] = $buscar;
    if ($tab === 'ciud') {
        if ($buscar !== '') $params['b_ciud'] = $buscar;
        if ($filtro !== '') $params['f_prov'] = $filtro;
    }
    $base = ($tab === 'ciud') ? rtrim($urlBase, '/') . '/ciudades' : $urlBase;
    $url = empty($params) ? $base : $base . '?' . http_build_query($params);
    return '<a href="' . htmlspecialchars($url) . '" class="text-decoration-none" title="Ordenar por ' . htmlspecialchars($label) . '">' . htmlspecialchars($label) . '</a>';
}
?>
<style>
.provincia-ciudad-scroll { max-height: calc(100vh - 320px); overflow-y: auto; }
.provincia-ciudad-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.prov-row, .ciud-row { cursor: pointer; }
.prov-row:hover, .ciud-row:hover { background-color: rgba(0,0,0,.04); }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Gestión de provincias y ciudades. Clic en fila para editar.</p>
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
                <ul class="nav nav-tabs mb-3" id="tabsProvinciaCiudad" role="tablist">
            <li class="nav-item" role="presentation">
                <a href="<?= htmlspecialchars($urlBase) ?>" class="nav-link <?= $tabActivo === 'provincias' ? 'active' : '' ?>">Provincias</a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="<?= htmlspecialchars($urlCiudades) ?>" class="nav-link <?= $tabActivo === 'ciudades' ? 'active' : '' ?>">Ciudades</a>
            </li>
        </ul>

        <div class="tab-content" id="tabsProvinciaCiudadContent">
                <div class="tab-pane fade <?= $tabActivo === 'provincias' ? 'show active' : '' ?>" id="pane-provincias" role="tabpanel">
                <form method="GET" action="<?= $urlBase ?>" class="mb-3 form-provincia-ciudad">
            <?php if ($ordenColProv !== 'nombre' || $ordenDirProv !== 'asc'): ?>
            <input type="hidden" name="sort_prov" value="<?= htmlspecialchars($ordenColProv) ?>">
            <input type="hidden" name="dir_prov" value="<?= htmlspecialchars($ordenDirProv) ?>">
            <?php endif; ?>
            <?php if ($buscarCiud !== ''): ?><input type="hidden" name="b_ciud" value="<?= htmlspecialchars($buscarCiud) ?>"><?php endif; ?>
            <?php if ($filtroProv !== ''): ?><input type="hidden" name="f_prov" value="<?= htmlspecialchars($filtroProv) ?>"><?php endif; ?>
            <?php if ($ordenColCiud !== 'nombre' || $ordenDirCiud !== 'asc'): ?>
            <input type="hidden" name="sort_ciud" value="<?= htmlspecialchars($ordenColCiud) ?>">
            <input type="hidden" name="dir_ciud" value="<?= htmlspecialchars($ordenDirCiud) ?>">
            <?php endif; ?>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <div class="input-group input-group-sm" style="max-width: 280px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="b_prov" class="form-control" placeholder="Buscar provincia..." value="<?= htmlspecialchars($buscarProv) ?>">
                    <button type="submit" class="btn btn-outline-primary">Buscar</button>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaProvincia"><i class="bi bi-plus-lg"></i> Nueva provincia</button>
                </div>
                </form>
                <div class="provincia-ciudad-scroll border rounded mt-2">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= thSort($urlBase, 'prov', 'codigo', 'Código', $ordenColProv, $ordenDirProv, $buscarProv) ?></th>
                                <th><?= thSort($urlBase, 'prov', 'nombre', 'Nombre', $ordenColProv, $ordenDirProv, $buscarProv) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rowsProvincias as $r): ?>
                            <tr class="prov-row" role="button" tabindex="0"
                                data-codigo="<?= htmlspecialchars($r['codigo'] ?? '') ?>"
                                data-nombre="<?= htmlspecialchars($r['nombre'] ?? '') ?>">
                                <td><code><?= htmlspecialchars($r['codigo'] ?? '') ?></code></td>
                                <td><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($rowsProvincias)): ?>
                    <p class="text-muted text-center py-4 mb-0">No hay provincias registradas.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-pane fade <?= $tabActivo === 'ciudades' ? 'show active' : '' ?>" id="pane-ciudades" role="tabpanel">
        <form method="GET" action="<?= $urlCiudades ?>" class="mb-3 form-provincia-ciudad">
            <?php if ($ordenColCiud !== 'nombre' || $ordenDirCiud !== 'asc'): ?>
            <input type="hidden" name="sort_ciud" value="<?= htmlspecialchars($ordenColCiud) ?>">
            <input type="hidden" name="dir_ciud" value="<?= htmlspecialchars($ordenDirCiud) ?>">
            <?php endif; ?>
            <?php if ($buscarProv !== ''): ?><input type="hidden" name="b_prov" value="<?= htmlspecialchars($buscarProv) ?>"><?php endif; ?>
            <?php if ($ordenColProv !== 'nombre' || $ordenDirProv !== 'asc'): ?>
            <input type="hidden" name="sort_prov" value="<?= htmlspecialchars($ordenColProv) ?>">
            <input type="hidden" name="dir_prov" value="<?= htmlspecialchars($ordenDirProv) ?>">
            <?php endif; ?>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <select name="f_prov" class="form-select form-select-sm" style="max-width: 200px;">
                    <option value="">Todas las provincias</option>
                    <?php foreach ($provinciasParaSelect as $p): ?>
                    <option value="<?= htmlspecialchars($p['codigo'] ?? '') ?>" <?= ($filtroProv === ($p['codigo'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="input-group input-group-sm" style="max-width: 280px;">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="b_ciud" class="form-control" placeholder="Buscar ciudad..." value="<?= htmlspecialchars($buscarCiud) ?>">
                    <button type="submit" class="btn btn-outline-primary">Buscar</button>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaCiudad"><i class="bi bi-plus-lg"></i> Nueva ciudad</button>
                </div>
                </form>
                <div class="provincia-ciudad-scroll border rounded mt-2">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><?= thSort($urlBase, 'ciud', 'codigo', 'Código', $ordenColCiud, $ordenDirCiud, $buscarCiud, $filtroProv) ?></th>
                                <th><?= thSort($urlBase, 'ciud', 'nombre', 'Nombre', $ordenColCiud, $ordenDirCiud, $buscarCiud, $filtroProv) ?></th>
                                <th><?= thSort($urlBase, 'ciud', 'cod_prov', 'Provincia', $ordenColCiud, $ordenDirCiud, $buscarCiud, $filtroProv) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rowsCiudades as $r): ?>
                            <tr class="ciud-row" role="button" tabindex="0"
                                data-codigo="<?= htmlspecialchars($r['codigo'] ?? '') ?>"
                                data-nombre="<?= htmlspecialchars($r['nombre'] ?? '') ?>"
                                data-cod-prov="<?= htmlspecialchars($r['cod_prov'] ?? '') ?>"
                                data-nombre-provincia="<?= htmlspecialchars($r['nombre_provincia'] ?? '') ?>">
                                <td><code><?= htmlspecialchars($r['codigo'] ?? '') ?></code></td>
                                <td><?= htmlspecialchars($r['nombre'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['nombre_provincia'] ?? $r['cod_prov'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($rowsCiudades)): ?>
                    <p class="text-muted text-center py-4 mb-0">No hay ciudades registradas.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva provincia -->
<div class="modal fade" id="modalNuevaProvincia" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/provincia-ciudad-provincia-store">
                <input type="hidden" name="tab" value="provincias">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nueva provincia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new-prov-codigo" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" id="new-prov-codigo" name="codigo" class="form-control" required placeholder="Ej: 17" maxlength="10">
                    </div>
                    <div class="mb-3">
                        <label for="new-prov-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="new-prov-nombre" name="nombre" class="form-control" required placeholder="Nombre de la provincia">
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

<!-- Modal Editar provincia -->
<div class="modal fade" id="modalEditarProvincia" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/provincia-ciudad-provincia-update">
                <input type="hidden" name="tab" value="provincias">
                <input type="hidden" name="codigo_actual" id="edit-prov-codigo-actual" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar provincia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-prov-codigo" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" id="edit-prov-codigo" name="codigo" class="form-control" required placeholder="Ej: 17" maxlength="10">
                    </div>
                    <div class="mb-3">
                        <label for="edit-prov-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="edit-prov-nombre" name="nombre" class="form-control" required placeholder="Nombre de la provincia">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nueva ciudad -->
<div class="modal fade" id="modalNuevaCiudad" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/provincia-ciudad-ciudad-store">
                <input type="hidden" name="tab" value="ciudades">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nueva ciudad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new-ciud-cod-prov" class="form-label">Provincia <span class="text-danger">*</span></label>
                        <select id="new-ciud-cod-prov" name="cod_prov" class="form-select" required>
                            <option value="">Seleccione provincia...</option>
                            <?php foreach ($provinciasParaSelect as $p): ?>
                            <option value="<?= htmlspecialchars($p['codigo'] ?? '') ?>"><?= htmlspecialchars($p['nombre'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="new-ciud-codigo" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" id="new-ciud-codigo" name="codigo" class="form-control" required placeholder="Ej: 001" maxlength="20">
                    </div>
                    <div class="mb-3">
                        <label for="new-ciud-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="new-ciud-nombre" name="nombre" class="form-control" required placeholder="Nombre de la ciudad">
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

<!-- Modal Editar ciudad -->
<div class="modal fade" id="modalEditarCiudad" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/provincia-ciudad-ciudad-update">
                <input type="hidden" name="tab" value="ciudades">
                <input type="hidden" name="codigo_actual" id="edit-ciud-codigo-actual" value="">
                <input type="hidden" name="cod_prov_actual" id="edit-ciud-cod-prov-actual" value="">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Editar ciudad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-ciud-cod-prov" class="form-label">Provincia <span class="text-danger">*</span></label>
                        <select id="edit-ciud-cod-prov" name="cod_prov" class="form-select" required>
                            <option value="">Seleccione provincia...</option>
                            <?php foreach ($provinciasParaSelect as $p): ?>
                            <option value="<?= htmlspecialchars($p['codigo'] ?? '') ?>"><?= htmlspecialchars($p['nombre'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-ciud-codigo" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" id="edit-ciud-codigo" name="codigo" class="form-control" required placeholder="Ej: 001" maxlength="20">
                    </div>
                    <div class="mb-3">
                        <label for="edit-ciud-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="edit-ciud-nombre" name="nombre" class="form-control" required placeholder="Nombre de la ciudad">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    // Quitar parámetros vacíos de la URL al enviar formularios
    document.querySelectorAll('.form-provincia-ciudad').forEach(function(form) {
        form.addEventListener('submit', function() {
            form.querySelectorAll('input, select').forEach(function(el) {
                if (el.value === '' && el.name) el.removeAttribute('name');
            });
        });
    });

    var modalProv = document.getElementById('modalEditarProvincia');
    var modalCiud = document.getElementById('modalEditarCiudad');
    if (!modalProv || !modalCiud) return;

    document.querySelectorAll('.prov-row').forEach(function(row) {
        row.addEventListener('click', function() {
            document.getElementById('edit-prov-codigo-actual').value = this.dataset.codigo || '';
            document.getElementById('edit-prov-codigo').value = this.dataset.codigo || '';
            document.getElementById('edit-prov-nombre').value = this.dataset.nombre || '';
            new bootstrap.Modal(modalProv).show();
        });
        row.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
        });
    });

    document.querySelectorAll('.ciud-row').forEach(function(row) {
        row.addEventListener('click', function() {
            document.getElementById('edit-ciud-codigo-actual').value = this.dataset.codigo || '';
            document.getElementById('edit-ciud-cod-prov-actual').value = this.dataset.codProv || '';
            document.getElementById('edit-ciud-codigo').value = this.dataset.codigo || '';
            document.getElementById('edit-ciud-nombre').value = this.dataset.nombre || '';
            document.getElementById('edit-ciud-cod-prov').value = this.dataset.codProv || '';
            new bootstrap.Modal(modalCiud).show();
        });
        row.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
        });
    });
})();
</script>
