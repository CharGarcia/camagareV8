<?php
/** @var string $titulo */
/** @var array $rows */
/** @var string $ordenCol */
/** @var string $ordenDir */
/** @var string $buscar */
/** @var array $valoresDefecto */
$base = BASE_URL;
$rows = $rows ?? [];
$ordenCol = $ordenCol ?? 'orden';
$ordenDir = $ordenDir ?? 'desc';
$buscar = $buscar ?? '';
$valoresDefecto = $valoresDefecto ?? ['siguiente_orden' => 1, 'ultima_seccion' => '400'];
$msg = $_SESSION['sri_etiquetas_msg'] ?? null;
unset($_SESSION['sri_etiquetas_msg']);
$rowsHtml = $rowsHtml ?? '';
?>
<style>
.sri-row { cursor: pointer; }
.sri-row:hover { background-color: rgba(0,0,0,.04); }
.sri-etiquetas-header { flex-shrink: 0; padding-left: 0.75rem; padding-right: 0.75rem; }
.sri-etiquetas-msg,
.sri-etiquetas-buscador { padding-left: 0.75rem; padding-right: 0.75rem; }
.sri-etiquetas-scroll { max-height: calc(100dvh - 280px); overflow-y: auto; }
.sri-etiquetas-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
.sri-desc-cell { max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
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
<div class="sri-etiquetas-msg alert alert-<?= htmlspecialchars($msg[0]) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg[1]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="sri-etiquetas-buscador mb-3">
    <div class="input-group input-group-sm" style="max-width: 380px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" id="input-buscar-sri" class="form-control" placeholder="Buscar en sección, concepto, casilleros u orden..." value="<?= htmlspecialchars($buscar) ?>" autocomplete="off">
    </div>
</div>

<div class="card cmg-table-card">
    <div class="card-body p-0">
        <div class="sri-etiquetas-scroll">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 sortable-header" data-sort="seccion" role="button">Sección <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="sri-desc-cell sortable-header" data-sort="descripcion" role="button">Concepto (Fila) <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center">Casillero Bruto</th>
                        <th class="text-center">Casillero Neto</th>
                        <th class="text-center">Casillero Impuesto</th>
                        <th class="text-center sortable-header" data-sort="orden" role="button">Orden <i class="bi bi-arrow-down-up small text-muted ms-1"></i></th>
                        <th class="text-center pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodySri"><?= $rowsHtml ?></tbody>
            </table>
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
                                <div class="col-12">
                                    <label class="form-label text-muted small fw-bold mb-1">Fuente del Valor</label>
                                    <select class="form-select" name="fuente_valor" id="fuente_valor">
                                        <option value="documentos">Documentos sincronizados (montos)</option>
                                        <option value="conteo_ventas_emitidas">Conteo: comprobantes de venta emitidos</option>
                                        <option value="conteo_ventas_anuladas">Conteo: comprobantes de venta anulados</option>
                                        <option value="conteo_compras_recibidas">Conteo: comprobantes recibidos (excepto notas de venta)</option>
                                        <option value="conteo_notas_venta_recibidas">Conteo: notas de venta recibidas</option>
                                        <option value="conteo_liquidaciones_emitidas">Conteo: liquidaciones de compra emitidas</option>
                                    </select>
                                    <div class="form-text" style="font-size: 0.7rem;">Los conteos llenan el casillero con la cantidad de documentos del período, no con montos.</div>
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
                                <div class="col-12 mt-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="editable" name="editable" value="1">
                                        <label class="form-check-label fw-medium" for="editable">Editable manualmente en la Declaración de IVA</label>
                                    </div>
                                    <div class="form-text" style="font-size: 0.7rem;">El usuario podrá escribir el valor directamente en el "Resumen 104"; el formulario recalcula solo los casilleros que dependan de este por fórmula.</div>
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
    document.getElementById('seccion').value = '<?= htmlspecialchars($valoresDefecto['ultima_seccion']) ?>';
    document.getElementById('descripcion').value = '';
    document.getElementById('orden').value = '<?= (int)$valoresDefecto['siguiente_orden'] ?>';
    document.getElementById('casillero_bruto').value = '';
    document.getElementById('casillero_neto').value = '';
    document.getElementById('casillero_impuesto').value = '';
    document.getElementById('formula_bruto').value = '';
    document.getElementById('formula_neto').value = '';
    document.getElementById('formula_impuesto').value = '';
    document.getElementById('indent').value = '0';
    document.getElementById('bold').checked = false;
    document.getElementById('editable').checked = false;
    document.getElementById('tipo').value = 'valor';
    document.getElementById('fuente_valor').value = 'documentos';

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
    document.getElementById('editable').checked = data.editable;
    document.getElementById('tipo').value = data.tipo;
    document.getElementById('fuente_valor').value = data.fuente_valor || 'documentos';

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

(function() {
    // Búsqueda y orden en tiempo real: reemplazan solo la tabla vía AJAX, sin
    // recargar la página (el input nunca pierde el foco). Las filas usan
    // onclick inline, así que no hace falta re-vincular eventos tras
    // reemplazar el tbody. Mismo patrón que ASIENTOTIPO_cargarListado
    // (public/js/modulos/asientos_tipo_modal.js).
    var base = '<?= $base ?>';
    var timer = null;
    window.SRI_currentSort = '<?= htmlspecialchars($ordenCol) ?>';
    window.SRI_currentDir = '<?= htmlspecialchars($ordenDir) ?>';

    window.SRI_cargarListado = function() {
        var inputB = document.getElementById('input-buscar-sri');
        var b = inputB ? inputB.value.trim() : '';
        var tbodyEl = document.getElementById('tbodySri');
        if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="7" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

        fetch(base + '/config/sri-casilleros-etiquetas-search?b=' + encodeURIComponent(b) + '&sort=' + window.SRI_currentSort + '&dir=' + window.SRI_currentDir, {
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok && tbodyEl) tbodyEl.innerHTML = data.rows;
            })
            .catch(function() {
                if (tbodyEl) tbodyEl.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Error al cargar.</td></tr>';
            });
    };

    var inputBuscar = document.getElementById('input-buscar-sri');
    if (inputBuscar) {
        inputBuscar.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                SRI_cargarListado();
            }, 400);
        });
    }

    if (window.CMG_initSort) {
        window.CMG_initSort('sri-casilleros-etiquetas', function(col, dir) {
            window.SRI_currentSort = col;
            window.SRI_currentDir = dir;
            SRI_cargarListado();
        }, { col: window.SRI_currentSort, dir: window.SRI_currentDir });
    }
})();
</script>
