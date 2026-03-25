<?php
/** @var string $titulo */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $totalPages */
/** @var string $buscar */
/** @var int $nivel */
$base = BASE_URL;
$rows = $rows ?? [];
$total = $total ?? 0;
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$perPage = $perPage ?? 20;
$ordenCol = $ordenCol ?? 'nombre';
$ordenDir = $ordenDir ?? 'asc';
$buscar = $buscar ?? '';
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to = $total > 0 ? min($page * $perPage, $total) : 0;
$msg = $_SESSION['empresas_msg'] ?? null;
unset($_SESSION['empresas_msg']);
$urlBaseEmpresas = rtrim($base, '/') . '/config/empresas-sistema';

function thSortEmpresas($urlBase, $col, $label, $ordenCol, $ordenDir, $buscar, $page, $align = '') {
    $dir = ($ordenCol === $col && strtolower($ordenDir) === 'asc') ? 'desc' : 'asc';
    $cls = trim('btn btn-link p-0 text-decoration-none ' . $align);
    $html = '<form method="POST" action="' . htmlspecialchars($urlBase) . '" class="d-inline">';
    $html .= '<input type="hidden" name="sort" value="' . htmlspecialchars($col) . '">';
    $html .= '<input type="hidden" name="dir" value="' . $dir . '">';
    $html .= '<input type="hidden" name="page" value="' . (int)$page . '">';
    $html .= '<input type="hidden" name="b" value="' . htmlspecialchars($buscar) . '">';
    $html .= '<button type="submit" class="' . $cls . '" title="Ordenar por ' . htmlspecialchars($label) . '">' . htmlspecialchars($label) . '</button>';
    $html .= '</form>';
    return $html;
}

function estadoPagoBadge($estado) {
    $estado = $estado ?? 'pendiente';
    $cls = match ($estado) {
        'pagado' => 'success',
        'vencido' => 'danger',
        default => 'warning',
    };
    $txt = match ($estado) {
        'pagado' => 'Pagado',
        'vencido' => 'Vencido',
        default => 'Pendiente',
    };
    return '<span class="badge bg-' . $cls . '">' . htmlspecialchars($txt) . '</span>';
}
?>
<style>
.empresas-sistema-header { flex-shrink: 0; }
.empresas-sistema-scroll { max-height: calc(100vh - 240px); overflow-y: auto; }
.empresas-sistema-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.empresa-row { cursor: pointer; }
.empresa-row:hover { background-color: rgba(0,0,0,.04); }
.usuarios-empresa-scroll { max-height: 280px; overflow-y: auto; }
.usuarios-empresa-scroll thead th { position: sticky; top: 0; z-index: 1; background: #fff; box-shadow: 0 1px 0 #dee2e6; }
.documentos-empresa-scroll { max-height: 280px; overflow-y: auto; }
.documentos-empresa-scroll thead th { position: sticky; top: 0; z-index: 1; background: #fff; box-shadow: 0 1px 0 #dee2e6; }
</style>
<div class="empresas-sistema-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-building"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">
            <?= $nivel >= 3 ? 'Todas las empresas. Clic en fila para ver ficha.' : 'Empresas que tiene asignadas. Clic en fila para ver ficha.' ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <?php if ($nivel >= 3): ?>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearEmpresa"><i class="bi bi-plus-lg"></i> Crear empresa</button>
        <?php endif; ?>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center gap-2 mb-2 flex-wrap">
    <form method="POST" action="<?= $urlBaseEmpresas ?>" class="d-flex align-items-center gap-2">
        <input type="hidden" name="page" value="1">
        <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
        <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
        <div class="input-group input-group-sm" style="max-width: 320px;">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="b" class="form-control" placeholder="Buscar por razón social, RUC, establecimiento..." value="<?= htmlspecialchars($buscar) ?>">
            <button type="submit" class="btn btn-outline-primary">Buscar</button>
            <?php if ($buscar !== '' || $page > 1): ?>
            <a href="<?= $urlBaseEmpresas ?>" class="btn btn-outline-secondary" title="Volver a página 1 sin filtros">Limpiar</a>
            <?php endif; ?>
        </div>
    </form>
    <?php if ($total > 0): ?>
    <div class="d-flex align-items-center gap-2">
        <span class="text-muted small"><?= $from ?>-<?= $to ?>/<?= $total ?></span>
        <?php if ($page <= 1): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" disabled aria-label="Anterior"><i class="fas fa-angle-left"></i></button>
        <?php else: ?>
        <form method="POST" action="<?= $urlBaseEmpresas ?>" class="d-inline">
            <input type="hidden" name="page" value="<?= $page - 1 ?>">
            <input type="hidden" name="b" value="<?= htmlspecialchars($buscar) ?>">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
            <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Anterior"><i class="fas fa-angle-left"></i></button>
        </form>
        <?php endif; ?>
        <?php if ($page >= $totalPages): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" disabled aria-label="Siguiente"><i class="fas fa-angle-right"></i></button>
        <?php else: ?>
        <form method="POST" action="<?= $urlBaseEmpresas ?>" class="d-inline">
            <input type="hidden" name="page" value="<?= $page + 1 ?>">
            <input type="hidden" name="b" value="<?= htmlspecialchars($buscar) ?>">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
            <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Siguiente"><i class="fas fa-angle-right"></i></button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="empresas-sistema-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'nombre', 'Razón social', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'nombre_comercial', 'Nombre comercial', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'ruc', 'RUC', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'establecimiento', 'Establecimiento', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'direccion', 'Dirección', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'nombre_provincia', 'Provincia', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'nombre_ciudad', 'Ciudad', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th><?= thSortEmpresas($urlBaseEmpresas, 'estado', 'Estado', $ordenCol, $ordenDir, $buscar, $page) ?></th>
                        <th class="text-center">Usuarios</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <?php
                    $usuarios = $r['usuarios'] ?? [];
                    $estado = $r['estado'] ?? '1';
                    ?>
                    <tr class="empresa-row" role="button" tabindex="0"
                        data-id="<?= (int)($r['id'] ?? 0) ?>"
                        data-nombre="<?= htmlspecialchars($r['nombre'] ?? '') ?>"
                        data-nombre-comercial="<?= htmlspecialchars($r['nombre_comercial'] ?? '') ?>"
                        data-ruc="<?= htmlspecialchars($r['ruc'] ?? '') ?>"
                        data-establecimiento="<?= htmlspecialchars($r['establecimiento'] ?? '') ?>"
                        data-direccion="<?= htmlspecialchars($r['direccion'] ?? '') ?>"
                        data-telefono="<?= htmlspecialchars($r['telefono'] ?? '') ?>"
                        data-mail="<?= htmlspecialchars($r['mail'] ?? '') ?>"
                        data-nom-rep-legal="<?= htmlspecialchars($r['nom_rep_legal'] ?? '') ?>"
                        data-ced-rep-legal="<?= htmlspecialchars($r['ced_rep_legal'] ?? '') ?>"
                        data-cod-prov="<?= htmlspecialchars($r['cod_prov'] ?? '') ?>"
                        data-cod-ciudad="<?= htmlspecialchars($r['cod_ciudad'] ?? '') ?>"
                        data-nombre-contador="<?= htmlspecialchars($r['nombre_contador'] ?? '') ?>"
                        data-ruc-contador="<?= htmlspecialchars($r['ruc_contador'] ?? '') ?>"
                        data-estado="<?= htmlspecialchars($estado) ?>"
                        data-valor-cobro="<?= htmlspecialchars($r['valor_cobro'] ?? '') ?>"
                        data-periodo-vigencia-desde="<?= htmlspecialchars($r['periodo_vigencia_desde'] ?? '') ?>"
                        data-periodo-vigencia-hasta="<?= htmlspecialchars($r['periodo_vigencia_hasta'] ?? '') ?>"
                        data-estado-pago="<?= htmlspecialchars($r['estado_pago'] ?? 'pendiente') ?>"
                        data-usuarios="<?= count($usuarios) ?>">
                        <td><?= htmlspecialchars($r['nombre'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($r['nombre_comercial'] ?? '—') ?></td>
                        <td><code><?= htmlspecialchars($r['ruc'] ?? '') ?></code></td>
                        <td><code><?= htmlspecialchars($r['establecimiento'] ?? '—') ?></code></td>
                        <td class="text-truncate" style="max-width: 180px;"><?= htmlspecialchars($r['direccion'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($r['nombre_provincia'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($r['nombre_ciudad'] ?? '—') ?></td>
                        <td>
                            <?php if ($estado === '1'): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><span class="badge bg-light text-dark"><?= count($usuarios) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($rows)): ?>
            <p class="text-muted text-center py-4 mb-0">No hay empresas registradas.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Crear Empresa -->
<?php if ($nivel >= 3): ?>
<div class="modal fade" id="modalCrearEmpresa" tabindex="-1" aria-labelledby="modalCrearEmpresaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= $base ?>/config/empresas-sistema-store" id="form-crear-empresa">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCrearEmpresaLabel"><i class="bi bi-building-add"></i> Crear empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="crear-empresa-msg" class="d-none"></div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="crear-ruc" class="form-label">RUC *</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="crear-ruc" name="ruc" class="form-control form-control-sm solo-numero" required placeholder="1234567890001" maxlength="13" inputmode="numeric" pattern="[0-9]{13}" title="13 dígitos numéricos" autocomplete="off">
                                <button type="button" id="btn-consultar-ruc" class="btn btn-outline-secondary btn-sm" title="Consultar datos del RUC"><i class="bi bi-search"></i></button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="crear-establecimiento" class="form-label">Establecimiento</label>
                            <input type="text" id="crear-establecimiento" name="establecimiento" class="form-control form-control-sm solo-numero" placeholder="000" maxlength="3" pattern="[0-9]{0,3}" inputmode="numeric" title="Solo números 0-9 (opcional)">
                        </div>
                        <div class="col-md-4">
                            <label for="crear-estado" class="form-label">Estado</label>
                            <select id="crear-estado" name="estado" class="form-select form-select-sm">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="crear-nombre" class="form-label">Razón social *</label>
                            <input type="text" id="crear-nombre" name="nombre" class="form-control form-control-sm" required placeholder="Razón social">
                        </div>
                        <div class="col-12">
                            <label for="crear-nombre-comercial" class="form-label">Nombre comercial</label>
                            <input type="text" id="crear-nombre-comercial" name="nombre_comercial" class="form-control form-control-sm" placeholder="Nombre comercial">
                        </div>
                        <div class="col-12">
                            <label for="crear-direccion" class="form-label">Dirección</label>
                            <input type="text" id="crear-direccion" name="direccion" class="form-control form-control-sm" placeholder="Dirección">
                        </div>
                        <div class="col-12">
                            <label for="crear-mail" class="form-label">Correo</label>
                            <input type="email" id="crear-mail" name="mail" class="form-control form-control-sm" placeholder="correo@empresa.com">
                        </div>
                        <div class="col-md-4">
                            <label for="crear-provincia" class="form-label">Provincia</label>
                            <select id="crear-provincia" name="cod_prov" class="form-select form-select-sm">
                                <option value="">Seleccione provincia</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="crear-ciudad" class="form-label">Ciudad</label>
                            <select id="crear-ciudad" name="cod_ciudad" class="form-select form-select-sm">
                                <option value="">Seleccione ciudad</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="crear-telefono" class="form-label">Teléfono</label>
                            <input type="text" id="crear-telefono" name="telefono" class="form-control form-control-sm" placeholder="Teléfono">
                        </div>
                        <input type="hidden" name="tipo" value="01">
                        <input type="hidden" name="nom_rep_legal" value="">
                        <input type="hidden" name="ced_rep_legal" value="">
                        <input type="hidden" name="nombre_contador" value="">
                        <input type="hidden" name="ruc_contador" value="">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-plus-lg"></i> Crear empresa</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Ficha Empresa (con pestañas) -->
<div class="modal fade" id="modalDetalleEmpresa" tabindex="-1" aria-labelledby="modalDetalleEmpresaLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetalleEmpresaLabel"><i class="bi bi-building"></i> <span id="modal-empresa-nombre"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal-empresa-id" value="">
                <ul class="nav nav-tabs mb-3" id="tabsEmpresa" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-empresas-general" data-bs-toggle="tab" data-bs-target="#pane-empresas-general" type="button" role="tab">General</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-empresas-usuarios" data-bs-toggle="tab" data-bs-target="#pane-empresas-usuarios" type="button" role="tab">Usuarios asignados</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-empresas-cobro" data-bs-toggle="tab" data-bs-target="#pane-empresas-cobro" type="button" role="tab">Cobro y vigencia</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-empresas-documentos" data-bs-toggle="tab" data-bs-target="#pane-empresas-documentos" type="button" role="tab">Documentos</button>
                    </li>
                </ul>
                    <div class="tab-content" id="tabsEmpresaContent">
                    <div class="tab-pane fade show active" id="pane-empresas-general" role="tabpanel">
                        <form method="POST" action="<?= $base ?>/config/empresas-sistema-update" id="form-editar-empresa">
                            <div id="editar-empresa-msg" class="d-none mb-3"></div>
                            <input type="hidden" name="id" id="edit-empresa-id" value="">
                            <input type="hidden" name="nom_rep_legal" value="">
                            <input type="hidden" name="ced_rep_legal" value="">
                            <input type="hidden" name="nombre_contador" value="">
                            <input type="hidden" name="ruc_contador" value="">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="edit-ruc" class="form-label">RUC</label>
                                    <input type="text" id="edit-ruc" name="ruc" class="form-control form-control-sm solo-numero" placeholder="RUC" maxlength="13" pattern="[0-9]{13}" inputmode="numeric" title="13 dígitos numéricos" autocomplete="off">
                                </div>
                                <div class="col-md-4">
                                    <label for="edit-establecimiento" class="form-label">Establecimiento</label>
                                    <input type="text" id="edit-establecimiento" name="establecimiento" class="form-control form-control-sm solo-numero" placeholder="000" maxlength="3" pattern="[0-9]{0,3}" inputmode="numeric" title="Solo números 0-9 (3 dígitos)">
                                </div>
                                <div class="col-md-4">
                                    <label for="edit-estado" class="form-label">Estado</label>
                                    <select id="edit-estado" name="estado" class="form-select form-select-sm">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="edit-nombre" class="form-label">Razón social</label>
                                    <input type="text" id="edit-nombre" name="nombre" class="form-control form-control-sm" placeholder="Razón social">
                                </div>
                                <div class="col-12">
                                    <label for="edit-nombre-comercial" class="form-label">Nombre comercial</label>
                                    <input type="text" id="edit-nombre-comercial" name="nombre_comercial" class="form-control form-control-sm" placeholder="Nombre comercial">
                                </div>
                                <div class="col-12">
                                    <label for="edit-direccion" class="form-label">Dirección</label>
                                    <input type="text" id="edit-direccion" name="direccion" class="form-control form-control-sm" placeholder="Dirección">
                                </div>
                                <div class="col-12">
                                    <label for="edit-mail" class="form-label">Correo</label>
                                    <input type="email" id="edit-mail" name="mail" class="form-control form-control-sm" placeholder="correo@empresa.com">
                                </div>
                                <div class="col-md-4">
                                    <label for="edit-provincia" class="form-label">Provincia</label>
                                    <select id="edit-provincia" name="cod_prov" class="form-select form-select-sm">
                                        <option value="">Seleccione provincia</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="edit-ciudad" class="form-label">Ciudad</label>
                                    <select id="edit-ciudad" name="cod_ciudad" class="form-select form-select-sm">
                                        <option value="">Seleccione ciudad</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="edit-telefono" class="form-label">Teléfono</label>
                                    <input type="text" id="edit-telefono" name="telefono" class="form-control form-control-sm" placeholder="Teléfono">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Guardar cambios</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="pane-empresas-usuarios" role="tabpanel">
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small">Agregar usuario</label>
                                <select id="select-usuario-empresa" class="form-select form-select-sm">
                                    <option value="">Cargando...</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-primary" id="btn-agregar-usuario-empresa">
                                    <i class="bi bi-plus"></i> Asignar
                                </button>
                            </div>
                        </div>
                        <div class="usuarios-empresa-scroll">
                            <table class="table table-sm mb-0">
                                <thead class="table-light"><tr><th>Nombre</th><th>Cédula</th><th>Correo</th><th>Nivel</th><th class="text-end">Quitar</th></tr></thead>
                                <tbody id="tbody-usuarios-empresa"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="pane-empresas-cobro" role="tabpanel">
                        <form method="POST" action="<?= $base ?>/config/empresas-sistema-update" id="form-editar-empresa-cobro">
                            <div id="editar-cobro-msg" class="d-none mb-3"></div>
                            <input type="hidden" name="id" id="edit-empresa-id-cobro" value="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="edit-valor-cobro" class="form-label">Valor asignado de cobro</label>
                                    <input type="number" id="edit-valor-cobro" name="valor_cobro" class="form-control form-control-sm" step="0.01" min="0" placeholder="0.00">
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-estado-pago" class="form-label">Estado de pago</label>
                                    <select id="edit-estado-pago" name="estado_pago" class="form-select form-select-sm">
                                        <option value="pendiente">Pendiente</option>
                                        <option value="pagado">Pagado</option>
                                        <option value="vencido">Vencido</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-vigencia-desde" class="form-label">Periodo vigencia desde</label>
                                    <input type="date" id="edit-vigencia-desde" name="periodo_vigencia_desde" class="form-control form-control-sm" placeholder="YYYY-MM-DD">
                                </div>
                                <div class="col-md-6">
                                    <label for="edit-vigencia-hasta" class="form-label">Periodo vigencia hasta</label>
                                    <input type="date" id="edit-vigencia-hasta" name="periodo_vigencia_hasta" class="form-control form-control-sm" placeholder="YYYY-MM-DD">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Guardar cambios</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="pane-empresas-documentos" role="tabpanel">
                        <p class="text-muted small mb-2">Contratos, RUC, licencias y demás documentos legales de la empresa.</p>
                        <form method="POST" action="<?= $base ?>/config/empresas-sistema?action=uploadDocumento" enctype="multipart/form-data" class="mb-3">
                            <input type="hidden" name="action" value="uploadDocumento">
                            <input type="hidden" name="id_empresa" id="upload-id-empresa" value="">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label for="upload-tipo" class="form-label small">Tipo</label>
                                    <select id="upload-tipo" name="tipo_documento" class="form-select form-select-sm">
                                        <option value="contrato">Contrato</option>
                                        <option value="ruc">RUC</option>
                                        <option value="licencia">Licencia</option>
                                        <option value="poder">Poder</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="upload-archivo" class="form-label small">Archivo</label>
                                    <input type="file" id="upload-archivo" name="archivo" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="upload-descripcion" class="form-label small">Descripción (opcional)</label>
                                    <input type="text" id="upload-descripcion" name="descripcion" class="form-control form-control-sm" placeholder="Ej: Contrato 2024">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload"></i> Subir documento</button>
                                </div>
                            </div>
                        </form>
                        <div class="documentos-empresa-scroll">
                            <table class="table table-sm mb-0">
                                <thead class="table-light"><tr><th>Tipo</th><th>Archivo</th><th>Descripción</th><th class="text-end">Acciones</th></tr></thead>
                                <tbody id="tbody-documentos-empresa"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var base = '<?= $base ?>';

    document.querySelectorAll('.solo-numero').forEach(function(inp) {
        inp.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
        inp.addEventListener('paste', function(e) {
            e.preventDefault();
            var text = (e.clipboardData || window.clipboardData).getData('text');
            var nums = text.replace(/\D/g, '');
            var start = this.selectionStart, end = this.selectionEnd;
            this.value = this.value.substring(0, start) + nums + this.value.substring(end);
            this.setSelectionRange(start + nums.length, start + nums.length);
        });
    });

    var modal = document.getElementById('modalDetalleEmpresa');
    var tbody = document.getElementById('tbody-usuarios-empresa');
    var idEmpresa = 0;

    function cargarProvincias(selectId, callback) {
        var sel = document.getElementById(selectId);
        if (!sel) return;
        fetch(base + '/config/empresas-sistema?action=provincias', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var opts = sel.options;
                opts.length = 1;
                (data.provincias || []).forEach(function(p) {
                    var o = document.createElement('option');
                    o.value = p.codigo || '';
                    o.textContent = p.nombre || '';
                    opts.add(o);
                });
                if (typeof callback === 'function') callback();
            })
            .catch(function() { if (typeof callback === 'function') callback(); });
    }

    function cargarCiudades(codProv, selectId, valorSeleccionado) {
        var sel = document.getElementById(selectId);
        if (!sel) return;
        sel.innerHTML = '<option value="">Seleccione ciudad</option>';
        if (!codProv) return;
        fetch(base + '/config/empresas-sistema?action=ciudades&cod_prov=' + encodeURIComponent(codProv), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var opts = sel.options;
                opts.length = 1;
                (data.ciudades || []).forEach(function(c) {
                    var o = document.createElement('option');
                    o.value = c.codigo || '';
                    o.textContent = c.nombre || '';
                    opts.add(o);
                });
                if (valorSeleccionado) sel.value = valorSeleccionado;
            })
            .catch(function() {});
    }

    function abrirModalEmpresa(el) {
        idEmpresa = parseInt(el.dataset.id, 10);
        document.getElementById('modal-empresa-id').value = idEmpresa;
        document.getElementById('edit-empresa-id').value = idEmpresa;
        document.getElementById('edit-empresa-id-cobro').value = idEmpresa;
        document.getElementById('upload-id-empresa').value = idEmpresa;
        document.getElementById('modal-empresa-nombre').textContent = el.dataset.nombre || el.dataset.nombreComercial || el.dataset.establecimiento || '';
        document.getElementById('edit-nombre-comercial').value = el.dataset.nombreComercial || '';
        document.getElementById('edit-ruc').value = el.dataset.ruc || '';
        document.getElementById('edit-nombre').value = el.dataset.nombre || '';
        document.getElementById('edit-establecimiento').value = el.dataset.establecimiento || '';
        document.getElementById('edit-direccion').value = el.dataset.direccion || '';
        document.getElementById('edit-telefono').value = el.dataset.telefono || '';
        document.getElementById('edit-mail').value = el.dataset.mail || '';
        document.getElementById('edit-estado').value = (el.dataset.estado === '1') ? '1' : '0';
        var codProv = el.dataset.codProv || '';
        var codCiudad = el.dataset.codCiudad || '';
        cargarProvincias('edit-provincia', function() {
            document.getElementById('edit-provincia').value = codProv;
            cargarCiudades(codProv, 'edit-ciudad', codCiudad);
        });
        document.getElementById('edit-valor-cobro').value = el.dataset.valorCobro || '';
        document.getElementById('edit-vigencia-desde').value = el.dataset.periodoVigenciaDesde || '';
        document.getElementById('edit-vigencia-hasta').value = el.dataset.periodoVigenciaHasta || '';
        document.getElementById('edit-estado-pago').value = el.dataset.estadoPago || 'pendiente';
        document.getElementById('tab-empresas-general').click();
        cargarUsuarios();
        cargarUsuariosDisponibles();
        cargarDocumentos();
        new bootstrap.Modal(modal).show();
    }

    function cargarUsuariosDisponibles() {
        var select = document.getElementById('select-usuario-empresa');
        select.innerHTML = '<option value="">Cargando...</option>';
        fetch(base + '/config/empresas-sistema?action=usuariosDisponiblesEmpresa&id_empresa=' + idEmpresa)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                select.innerHTML = '<option value="">Seleccione usuario...</option>';
                (data.usuarios || []).forEach(function(u) {
                    var o = document.createElement('option');
                    o.value = u.id_usuario;
                    o.textContent = (u.nombre || '') + ' (' + (u.cedula || '') + ')';
                    select.appendChild(o);
                });
            })
            .catch(function() { select.innerHTML = '<option value="">Error</option>'; });
    }

    function cargarDocumentos() {
        var tbody = document.getElementById('tbody-documentos-empresa');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">Cargando...</td></tr>';
        fetch(base + '/config/empresas-sistema?action=documentosEmpresa&id=' + idEmpresa)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.html) {
                    tbody.innerHTML = data.html;
                    tbody.querySelectorAll('.btn-eliminar-doc').forEach(function(b) {
                        b.addEventListener('click', function() {
                            if (confirm('¿Eliminar este documento?')) {
                                var id = this.dataset.id;
                                var formData = new FormData();
                                formData.append('action', 'deleteDocumento');
                                formData.append('id', id);
                                fetch(base + '/config/empresas-sistema', {
                                    method: 'POST',
                                    body: formData,
                                    credentials: 'same-origin',
                                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                                })
                                .then(function(r) { return r.json(); })
                                .then(function(res) {
                                    if (res.ok) cargarDocumentos();
                                    else alert(res.msg || 'Error');
                                })
                                .catch(function() { alert('Error al eliminar'); });
                            }
                        });
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-muted">Sin documentos</td></tr>';
                }
            })
            .catch(function() { tbody.innerHTML = '<tr><td colspan="4" class="text-danger">Error al cargar</td></tr>'; });
    }

    document.getElementById('tab-empresas-documentos').addEventListener('shown.bs.tab', cargarDocumentos);
    document.getElementById('tab-empresas-usuarios').addEventListener('shown.bs.tab', function() {
        cargarUsuarios();
        cargarUsuariosDisponibles();
    });

    document.querySelectorAll('.empresa-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (!e.target.closest('a')) abrirModalEmpresa(this);
        });
        row.addEventListener('keydown', function(e) {
            if ((e.key === 'Enter' || e.key === ' ') && !e.target.closest('a')) {
                e.preventDefault();
                abrirModalEmpresa(this);
            }
        });
    });

    function cargarUsuarios() {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Cargando...</td></tr>';
        fetch(base + '/config/empresas-sistema?action=usuariosEmpresa&id=' + idEmpresa)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.html) {
                    tbody.innerHTML = data.html;
                    tbody.querySelectorAll('.btn-quitar-usuario-empresa').forEach(function(b) {
                        b.addEventListener('click', function() {
                            if (confirm('¿Quitar este usuario de la empresa?')) {
                                var id = this.dataset.id;
                                var f = document.createElement('form');
                                f.method = 'POST';
                                f.action = base + '/config/asignar-empresas';
                                var i = document.createElement('input');
                                i.type = 'hidden'; i.name = 'action'; i.value = 'quitar';
                                var i2 = document.createElement('input');
                                i2.type = 'hidden'; i2.name = 'id'; i2.value = id;
                                var i3 = document.createElement('input');
                                i3.type = 'hidden'; i3.name = 'redirect'; i3.value = 'empresas-sistema';
                                f.appendChild(i); f.appendChild(i2); f.appendChild(i3);
                                document.body.appendChild(f);
                                f.submit();
                            }
                        });
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Sin usuarios asignados</td></tr>';
                }
                cargarUsuariosDisponibles();
            })
            .catch(function() { tbody.innerHTML = '<tr><td colspan="5" class="text-danger">Error al cargar</td></tr>'; });
    }

    document.getElementById('btn-agregar-usuario-empresa').addEventListener('click', function() {
        var idUsuario = document.getElementById('select-usuario-empresa').value;
        if (!idUsuario) { alert('Seleccione un usuario'); return; }
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = base + '/config/asignar-empresas';
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = 'action'; i.value = 'asignar';
        var i2 = document.createElement('input');
        i2.type = 'hidden'; i2.name = 'id_empresa'; i2.value = idEmpresa;
        var i3 = document.createElement('input');
        i3.type = 'hidden'; i3.name = 'id_usuario'; i3.value = idUsuario;
        var i4 = document.createElement('input');
        i4.type = 'hidden'; i4.name = 'redirect'; i4.value = 'empresas-sistema';
        f.appendChild(i); f.appendChild(i2); f.appendChild(i3); f.appendChild(i4);
        document.body.appendChild(f);
        f.submit();
    });

    document.getElementById('edit-provincia').addEventListener('change', function() {
        cargarCiudades(this.value, 'edit-ciudad', '');
    });

    var modalCrear = document.getElementById('modalCrearEmpresa');
    var crearProvincia = document.getElementById('crear-provincia');
    if (modalCrear) {
        modalCrear.addEventListener('shown.bs.modal', function() {
            cargarProvincias('crear-provincia');
        });
    }
    if (crearProvincia) {
        crearProvincia.addEventListener('change', function() {
            cargarCiudades(this.value, 'crear-ciudad', '');
        });
    }

    function consultarRucSri() {
        var inp = document.getElementById('crear-ruc');
        var btn = document.getElementById('btn-consultar-ruc');
        if (!inp || !btn) return;
        var num = (inp.value || '').replace(/\D/g, '');
        if (num.length !== 13) {
            return;
        }
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
        fetch(base + '/config/empresas-sistema?action=sriIdentificacion&numero=' + encodeURIComponent(num), { credentials: 'same-origin' })
            .then(function(r) {
                var ct = (r.headers.get('content-type') || '');
                if (!ct.includes('application/json')) {
                    return r.text().then(function(t) {
                        throw new Error('El servidor no devolvió JSON (¿sesión expirada o error PHP?). ' + (t ? t.slice(0, 120) : ''));
                    });
                }
                return r.json();
            })
            .then(function(res) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-search"></i>';
                if (res.ok && res.data) {
                    var d = res.data;
                    document.getElementById('crear-nombre').value = d.nombre || '';
                    document.getElementById('crear-nombre-comercial').value = d.nombre_comercial || '';
                    document.getElementById('crear-direccion').value = d.direccion || '';
                    document.getElementById('crear-establecimiento').value = d.establecimiento || '';
                    if (d.tipo && modalCrear) {
                        var tipoInput = modalCrear.querySelector('input[name="tipo"]');
                        if (tipoInput) tipoInput.value = d.tipo;
                    }
                    var codProv = d.cod_prov || '';
                    var codCiud = d.cod_ciudad || '';
                    if (codProv) {
                        document.getElementById('crear-provincia').value = codProv;
                        cargarCiudades(codProv, 'crear-ciudad', codCiud);
                    }
                } else {
                    alert(res.error || 'No se pudo consultar el RUC.');
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-search"></i>';
                var msg = (err && err.message) ? err.message
                    : 'Error de conexión al consultar el RUC. Revise sri_identification_url en config/app.php y que el servidor salga a internet (probar con curl desde el VPS).';
                alert(msg);
            });
    }

    var crearRuc = document.getElementById('crear-ruc');
    var btnConsultar = document.getElementById('btn-consultar-ruc');
    if (crearRuc) {
        crearRuc.addEventListener('blur', function() {
            var n = (this.value || '').replace(/\D/g, '');
            if (n.length === 13) consultarRucSri();
        });
    }
    if (btnConsultar) {
        btnConsultar.addEventListener('click', consultarRucSri);
    }

    function mostrarMsgForm(containerId, tipo, texto) {
        var el = document.getElementById(containerId);
        if (!el) return;
        el.className = 'alert alert-' + (tipo === 'error' ? 'danger' : 'success') + ' alert-dismissible fade show mb-3';
        el.innerHTML = texto + ' <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>';
        el.classList.remove('d-none');
    }
    function ocultarMsgForm(containerId) {
        var el = document.getElementById(containerId);
        if (el) el.classList.add('d-none');
    }

    function enviarFormAjax(form, msgContainerId, url) {
        return fetch(url, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.ok) {
                mostrarMsgForm(msgContainerId, 'success', res.msg || 'Guardado correctamente.');
                setTimeout(function() { window.location.href = base + '/config/empresas-sistema'; }, 1500);
            } else {
                mostrarMsgForm(msgContainerId, 'error', res.error || 'Error desconocido.');
                return Promise.reject();
            }
        })
        .catch(function(err) {
            mostrarMsgForm(msgContainerId, 'error', err.message || 'Error de conexión. Intente de nuevo.');
            return Promise.reject();
        });
    }

    var formCrear = document.getElementById('form-crear-empresa');
    if (formCrear) {
        formCrear.addEventListener('submit', function(e) {
            e.preventDefault();
            ocultarMsgForm('crear-empresa-msg');
            var btn = formCrear.querySelector('button[type="submit"]');
            var txtOrig = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
            enviarFormAjax(formCrear, 'crear-empresa-msg', base + '/config/empresas-sistema-store')
                .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
        });
    }

    var formEditar = document.getElementById('form-editar-empresa');
    if (formEditar) {
        formEditar.addEventListener('submit', function(e) {
            e.preventDefault();
            ocultarMsgForm('editar-empresa-msg');
            var btn = formEditar.querySelector('button[type="submit"]');
            var txtOrig = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
            enviarFormAjax(formEditar, 'editar-empresa-msg', base + '/config/empresas-sistema-update')
                .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
        });
    }

    var formCobro = document.getElementById('form-editar-empresa-cobro');
    if (formCobro) {
        formCobro.addEventListener('submit', function(e) {
            e.preventDefault();
            ocultarMsgForm('editar-cobro-msg');
            var btn = formCobro.querySelector('button[type="submit"]');
            var txtOrig = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }
            enviarFormAjax(formCobro, 'editar-cobro-msg', base + '/config/empresas-sistema-update')
                .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = txtOrig; } });
        });
    }

    var modalCrearForm = document.getElementById('modalCrearEmpresa');
    if (modalCrearForm) {
        modalCrearForm.addEventListener('show.bs.modal', function() { ocultarMsgForm('crear-empresa-msg'); });
    }
    if (modal) {
        modal.addEventListener('show.bs.modal', function() {
            ocultarMsgForm('editar-empresa-msg');
            ocultarMsgForm('editar-cobro-msg');
        });
    }

})();
</script>
