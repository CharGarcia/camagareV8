<?php
/** @var string $titulo */
/** @var array $rows */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var string $buscar */
$base = BASE_URL;
$rows = $rows ?? [];
$ordenCol = $ordenCol ?? 'seccion';
$ordenDir = $ordenDir ?? 'asc';
$buscar = $buscar ?? '';
$msg = $_SESSION['sri_etiquetas_msg'] ?? null;
unset($_SESSION['sri_etiquetas_msg']);

function thSort($base, $col, $label, $ordenCol, $ordenDir, $buscar, $align = '') {
    $dir = ($ordenCol === $col && strtolower($ordenDir) === 'asc') ? 'desc' : 'asc';
    $url = rtrim($base, '/') . '/config/sri-casilleros-etiquetas?sort=' . urlencode($col) . '&dir=' . $dir;
    if ($buscar !== '') $url .= '&b=' . urlencode($buscar);
    $cls = trim('text-decoration-none ' . $align);
    return '<a href="' . htmlspecialchars($url) . '" class="' . $cls . '" title="Ordenar por ' . htmlspecialchars($label) . '">' . htmlspecialchars($label) . '</a>';
}
?>
<style>
.sri-row { cursor: pointer; }
.sri-row:hover { background-color: rgba(0,0,0,.04); }
.sri-etiquetas-header { flex-shrink: 0; }
.sri-etiquetas-scroll { max-height: calc(100dvh - 280px); overflow-y: auto; }
.sri-etiquetas-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
</style>
<div class="sri-etiquetas-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-ui-checks-grid"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Configuración de Filas del Formulario 104.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalCrear()"><i class="bi bi-plus-lg"></i> Nueva Fila</button>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="GET" action="<?= rtrim($base, '/') ?>/config/sri-casilleros-etiquetas" class="mb-3">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($ordenCol) ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($ordenDir) ?>">
    <div class="input-group input-group-sm" style="max-width: 380px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="b" class="form-control" placeholder="Buscar en casillero, sección, descripción..." value="<?= htmlspecialchars($buscar) ?>">
        <button type="submit" class="btn btn-outline-primary">Buscar</button>
        <?php if ($buscar !== ''): ?>
        <a href="<?= rtrim($base, '/') ?>/config/sri-casilleros-etiquetas?sort=<?= urlencode($ordenCol) ?>&dir=<?= urlencode($ordenDir) ?>" class="btn btn-outline-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="sri-etiquetas-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3"><?= thSort($base, 'seccion', 'Sección', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th><?= thSort($base, 'descripcion', 'Concepto (Fila)', $ordenCol, $ordenDir, $buscar) ?></th>
                        <th class="text-center">Casillero Bruto</th>
                        <th class="text-center">Casillero Neto</th>
                        <th class="text-center">Casillero Impuesto</th>
                        <th class="text-center"><?= thSort($base, 'orden', 'Orden', $ordenCol, $ordenDir, $buscar, 'text-center d-inline-block') ?></th>
                        <th class="text-center pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <?php
                    $id = (int)$r['id'];
                    $c_seccion = htmlspecialchars($r['seccion'] ?? '');
                    $c_desc = htmlspecialchars($r['descripcion'] ?? '');
                    $c_bruto = htmlspecialchars($r['casillero_bruto'] ?? '');
                    $c_neto = htmlspecialchars($r['casillero_neto'] ?? '');
                    $c_impuesto = htmlspecialchars($r['casillero_impuesto'] ?? '');
                    $c_orden = htmlspecialchars((string)($r['orden'] ?? '0'));
                    $c_indent = htmlspecialchars((string)($r['indent'] ?? '0'));
                    $c_bold = !empty($r['bold']);
                    $c_tipo = htmlspecialchars($r['tipo'] ?? 'valor');
                    
                    $rj = htmlspecialchars(json_encode([
                        'id' => $id,
                        'seccion' => $c_seccion,
                        'descripcion' => $c_desc,
                        'casillero_bruto' => $c_bruto,
                        'casillero_neto' => $c_neto,
                        'casillero_impuesto' => $c_impuesto,
                        'formula_bruto' => $r['formula_bruto'] ?? '',
                        'formula_neto' => $r['formula_neto'] ?? '',
                        'formula_impuesto' => $r['formula_impuesto'] ?? '',
                        'orden' => $c_orden,
                        'indent' => $c_indent,
                        'bold' => $c_bold,
                        'tipo' => $c_tipo
                    ]), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr class="sri-row" role="button" tabindex="0" data-json="<?= $rj ?>" onclick="abrirModalEditar(this)">
                        <td class="ps-3"><?= $c_seccion ?></td>
                        <td>
                            <?php if ($c_bold): ?><strong><?= $c_desc ?></strong><?php else: ?><?= $c_desc ?><?php endif; ?>
                            <?php if ($c_tipo === 'titulo'): ?> <span class="badge bg-info text-dark">TITULO</span> <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($c_bruto): ?><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><?= $c_bruto ?></span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($c_neto): ?><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><?= $c_neto ?></span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($c_impuesto): ?><span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><?= $c_impuesto ?></span><?php endif; ?>
                        </td>
                        <td class="text-center"><?= $c_orden ?></td>
                        <td class="text-center pe-3" onclick="event.stopPropagation()">
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 border-0" onclick="confirmarEliminar(<?= $id ?>, '<?= htmlspecialchars(addslashes($c_desc)) ?>')" title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($rows)): ?>
            <p class="text-muted text-center py-4 mb-0">No hay filas registradas o no coinciden con la búsqueda.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Etiqueta -->
<div class="modal fade" id="modalEtiqueta" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom-0 pb-3">
                <h5 class="modal-title fw-bold" id="modalTitle"><i class="bi bi-plus-circle"></i> Nueva Fila 104</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="formEtiqueta" method="POST" action="<?= BASE_URL ?>/config/sri-casilleros-etiquetas-store">
                <input type="hidden" name="id" id="row_id" value="">
                
                <div class="d-flex align-items-center bg-light px-3 pt-2">
                    <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-medium" data-bs-toggle="tab" data-bs-target="#tab-info" type="button" role="tab">Concepto</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-medium" data-bs-toggle="tab" data-bs-target="#tab-casilleros" type="button" role="tab">Casilleros</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-medium" data-bs-toggle="tab" data-bs-target="#tab-visual" type="button" role="tab">Diseño</button>
                        </li>
                    </ul>
                </div>
                
                <div class="modal-body border-top px-4 py-4">
                    <div class="tab-content">
                        <!-- Info -->
                        <div class="tab-pane fade show active" id="tab-info" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label text-muted small fw-bold mb-1">Sección *</label>
                                    <input type="text" class="form-control" name="seccion" id="seccion" required placeholder="Ej. 400, 500">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label text-muted small fw-bold mb-1">Orden de Dibujado</label>
                                    <input type="number" class="form-control" name="orden" id="orden" value="10">
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-muted small fw-bold mb-1">Descripción del Concepto *</label>
                                    <textarea class="form-control" name="descripcion" id="descripcion" rows="3" required></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Casilleros -->
                        <div class="tab-pane fade" id="tab-casilleros" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label text-primary small fw-bold mb-1">Casillero BRUTO</label>
                                    <input type="text" class="form-control border-primary" name="casillero_bruto" id="casillero_bruto" placeholder="Ej. 401">
                                    <input type="text" class="form-control mt-1 border-primary form-control-sm" name="formula_bruto" id="formula_bruto" placeholder="Fórmula (Opcional)">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-success small fw-bold mb-1">Casillero NETO</label>
                                    <input type="text" class="form-control border-success" name="casillero_neto" id="casillero_neto" placeholder="Ej. 411">
                                    <input type="text" class="form-control mt-1 border-success form-control-sm" name="formula_neto" id="formula_neto" placeholder="Fórmula (Opcional)">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-danger small fw-bold mb-1">Casillero IMPUESTO</label>
                                    <input type="text" class="form-control border-danger" name="casillero_impuesto" id="casillero_impuesto" placeholder="Ej. 421">
                                    <input type="text" class="form-control mt-1 border-danger form-control-sm" name="formula_impuesto" id="formula_impuesto" placeholder="Fórmula (Opcional)">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Visual -->
                        <div class="tab-pane fade" id="tab-visual" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold mb-1">Identación (Nivel)</label>
                                    <input type="number" class="form-control" name="indent" id="indent" value="0" min="0" max="5">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold mb-1">Tipo Fila</label>
                                    <select class="form-select" name="tipo" id="tipo">
                                        <option value="valor">Valores Numéricos</option>
                                        <option value="titulo">Solo Título / Agrupador</option>
                                    </select>
                                </div>
                                <div class="col-12 mt-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="bold" name="bold" value="1">
                                        <label class="form-check-label fw-medium" for="bold">Resaltar en Negrita</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-light border-top-0 py-3 px-4">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-check-lg"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="formDelete" method="POST" action="<?= BASE_URL ?>/config/sri-casilleros-etiquetas-delete" style="display:none;">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function abrirModalCrear() {
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-plus-circle"></i> Nueva Fila 104';
    document.getElementById('formEtiqueta').action = '<?= BASE_URL ?>/config/sri-casilleros-etiquetas-store';
    
    document.getElementById('row_id').value = '';
    document.getElementById('seccion').value = '400';
    document.getElementById('descripcion').value = '';
    document.getElementById('orden').value = '10';
    document.getElementById('casillero_bruto').value = '';
    document.getElementById('casillero_neto').value = '';
    document.getElementById('casillero_impuesto').value = '';
    document.getElementById('formula_bruto').value = '';
    document.getElementById('formula_neto').value = '';
    document.getElementById('formula_impuesto').value = '';
    document.getElementById('indent').value = '0';
    document.getElementById('bold').checked = false;
    document.getElementById('tipo').value = 'valor';
    
    const tabEl = document.querySelector('#modalEtiqueta .nav-tabs button[data-bs-target="#tab-info"]');
    if(tabEl) {
        const tab = new bootstrap.Tab(tabEl);
        tab.show();
    }
    
    new bootstrap.Modal(document.getElementById('modalEtiqueta')).show();
}

function abrirModalEditar(tr) {
    const data = JSON.parse(tr.getAttribute('data-json'));
    
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil"></i> Editar Fila 104';
    document.getElementById('formEtiqueta').action = '<?= BASE_URL ?>/config/sri-casilleros-etiquetas-update';
    
    document.getElementById('row_id').value = data.id;
    document.getElementById('seccion').value = data.seccion;
    document.getElementById('descripcion').value = data.descripcion;
    document.getElementById('orden').value = data.orden;
    document.getElementById('casillero_bruto').value = data.casillero_bruto;
    document.getElementById('casillero_neto').value = data.casillero_neto;
    document.getElementById('casillero_impuesto').value = data.casillero_impuesto;
    document.getElementById('formula_bruto').value = data.formula_bruto;
    document.getElementById('formula_neto').value = data.formula_neto;
    document.getElementById('formula_impuesto').value = data.formula_impuesto;
    document.getElementById('indent').value = data.indent;
    document.getElementById('bold').checked = data.bold;
    document.getElementById('tipo').value = data.tipo;
    
    const tabEl = document.querySelector('#modalEtiqueta .nav-tabs button[data-bs-target="#tab-info"]');
    if(tabEl) {
        const tab = new bootstrap.Tab(tabEl);
        tab.show();
    }
    
    new bootstrap.Modal(document.getElementById('modalEtiqueta')).show();
}

function confirmarEliminar(id, desc) {
    if(confirm('¿Está seguro de eliminar la fila: ' + desc + '?')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('formDelete').submit();
    }
}
</script>
