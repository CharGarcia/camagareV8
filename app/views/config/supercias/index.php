<?php
$titulo = "Estructuras SuperCías";
$tipos = ['ESF' => 'Situación Financiera', 'ERI' => 'Resultados Integrales', 'ECP' => 'Cambios en Patrimonio', 'EFE' => 'Flujos de Efectivo'];
$datosGrid = $datosGrid ?? [];
$tabActivo = $tabActivo ?? 'ESF';
?>
<style>
.supercias-row { cursor: pointer; }
.supercias-row:hover { background-color: rgba(0,0,0,.04); }
.supercias-scroll { max-height: calc(100dvh - 360px); overflow-y: auto; }
.supercias-scroll thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; box-shadow: 0 1px 0 #dee2e6; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="bi bi-bank"></i> <?= htmlspecialchars($titulo) ?></h5>
        <p class="text-muted mb-0 small">Gestione la formulación y mapeo de casilleros de los Estados Financieros exigidos por la Superintendencia de Compañías.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="<?= BASE_URL ?>/config" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <button class="btn btn-primary btn-sm" onclick="abrirModalCrear()"><i class="bi bi-plus-lg"></i> Nuevo Casillero</button>
    </div>
</div>

<div class="d-flex align-items-center bg-light px-3 pt-2 rounded-top border border-bottom-0">
    <ul class="nav nav-tabs border-bottom-0 flex-grow-1 tab-pestaña" id="superciasTabs" role="tablist">
        <?php foreach ($tipos as $tipo => $desc): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= ($tabActivo === $tipo) ? 'active' : '' ?>" 
                        id="<?= $tipo ?>-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#tab-<?= $tipo ?>" 
                        type="button" 
                        role="tab" 
                        onclick="cambiarTab('<?= $tipo ?>')">
                    <?= $tipo ?> - <?= $desc ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<div class="tab-content border border-top-0 px-3 py-3 bg-white rounded-bottom" id="superciasTabsContent">
    <?php foreach ($tipos as $tipo => $desc): ?>
        <div class="tab-pane fade <?= ($tabActivo === $tipo) ? 'show active' : '' ?>" id="tab-<?= $tipo ?>" role="tabpanel">
            
            <div class="card shadow-sm border-0 cmg-table-card">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="search-<?= $tipo ?>" class="form-control bg-light border-start-0" placeholder="Buscar casillero..." style="width: 250px;" onkeyup="filtrarTabla('<?= $tipo ?>')">
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive supercias-scroll">
                        <table id="table-<?= $tipo ?>" class="table table-hover align-middle mb-0 w-100">
                            <thead>
                                <tr>
                                    <th class="border-bottom-0" style="width: 10%">CÓDIGO</th>
                                    <?php if ($tipo === 'ECP'): ?>
                                    <th class="border-bottom-0" style="width: 10%">SUB CÓD.</th>
                                    <?php endif; ?>
                                    <th class="border-bottom-0" style="width: 40%">DESCRIPCIÓN</th>
                                    <th class="border-bottom-0" style="width: 40%">FÓRMULA / ORIGEN</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($datosGrid[$tipo] as $row): ?>
                                <tr class="supercias-row" onclick='abrirModalEdicion(<?= json_encode($row) ?>)'>
                                    <td><?= htmlspecialchars($row['codigo']) ?></td>
                                    <?php if ($tipo === 'ECP'): ?>
                                    <td><?= htmlspecialchars($row['subcodigo'] ?? '') ?></td>
                                    <?php endif; ?>
                                    <td class="td-nombre"><?= htmlspecialchars($row['nombre']) ?></td>
                                    <td>
                                        <?php if(empty($row['formula'])): ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border">Origen Contable Mapeado</span>
                                        <?php else: ?>
                                            <span class="font-monospace text-primary bg-primary bg-opacity-10 px-2 py-1 rounded border border-primary border-opacity-25"><?= htmlspecialchars($row['formula']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    <?php endforeach; ?>
</div>

<!-- Modal Casillero (Crear / Editar) -->
<div class="modal fade" id="modalCrearCasillero" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formCrearCasillero" class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title fw-bold" style="font-family: 'Outfit', sans-serif;" id="tituloModalCasillero">Nuevo Casillero</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit_cas_id">
                <div class="row mb-3">
                    <div class="col-4">
                        <label class="form-label fw-bold">Tipo de Estado</label>
                        <select name="tipo" id="select_tipo_casillero" class="form-select" required onchange="toggleSubcodigo(this.value)">
                            <option value="ESF">ESF</option>
                            <option value="ERI">ERI</option>
                            <option value="ECP">ECP</option>
                            <option value="EFE">EFE</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label fw-bold">Código</label>
                        <input type="text" name="codigo" class="form-control" required>
                    </div>
                    <div class="col-4" id="div_subcodigo" style="display: none;">
                        <label class="form-label fw-bold">Subcódigo</label>
                        <input type="text" name="subcodigo" id="input_subcodigo" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Descripción</label>
                    <textarea name="nombre" class="form-control" required rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Ubicar después del código (Opcional)</label>
                    <input type="text" class="form-control" name="codigo_anterior" placeholder="Ej: 10101">
                    <div class="form-text">Si dejas este campo vacío al crear, se agregará al final de la lista. Si lo dejas vacío al editar, mantendrá su posición actual.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Fórmula (Opcional)</label>
                    <textarea name="formula" class="form-control font-monospace" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light border-top-0">
                <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 rounded-pill"><i class="bi bi-save"></i> Guardar Casillero</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSubcodigo(tipo) {
    const div = document.getElementById('div_subcodigo');
    const input = document.getElementById('input_subcodigo');
    if (tipo === 'ECP') {
        div.style.display = 'block';
    } else {
        div.style.display = 'none';
        input.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('formCrearCasillero').addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('edit_cas_id').value;
        const url = BASE_URL + (id ? '/supercias-estructuras/updateAjax' : '/supercias-estructuras/storeAjax');
        submitForm(this, url);
    });
});

function abrirModalEdicion(data) {
    document.getElementById('formCrearCasillero').reset();
    document.getElementById('tituloModalCasillero').innerText = 'Editar Casillero';
    
    document.getElementById('edit_cas_id').value = data.id;
    document.querySelector('#formCrearCasillero select[name="tipo"]').value = data.tipo;
    document.querySelector('#formCrearCasillero input[name="codigo"]').value = data.codigo;
    document.querySelector('#formCrearCasillero input[name="subcodigo"]').value = data.subcodigo || '';
    document.querySelector('#formCrearCasillero textarea[name="nombre"]').value = data.nombre;
    document.querySelector('#formCrearCasillero textarea[name="formula"]').value = data.formula || '';
    document.querySelector('#formCrearCasillero input[name="codigo_anterior"]').value = '';
    
    toggleSubcodigo(data.tipo);
    new bootstrap.Modal(document.getElementById('modalCrearCasillero')).show();
}

function abrirModalCrear() {
    document.getElementById('formCrearCasillero').reset();
    document.getElementById('tituloModalCasillero').innerText = 'Nuevo Casillero';
    document.getElementById('edit_cas_id').value = '';
    
    const activeTab = document.querySelector('.nav-tabs .nav-link.active').id.split('-')[0];
    document.querySelector('#formCrearCasillero select[name="tipo"]').value = activeTab;
    
    toggleSubcodigo(activeTab);
    new bootstrap.Modal(document.getElementById('modalCrearCasillero')).show();
}

function submitForm(formElement, url) {
    const btn = formElement.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    btn.disabled = true;

    fetch(url, {
        method: 'POST',
        body: new FormData(formElement)
    })
    .then(r => r.json())
    .then(res => {
        if(res.ok) {
            Swal.fire({icon: 'success', title: 'Éxito', text: res.mensaje, timer: 1500, showConfirmButton: false});
            setTimeout(() => location.reload(), 1500);
        } else {
            Swal.fire('Error', res.error || 'Error', 'error');
        }
    })
    .catch(() => Swal.fire('Error', 'Error de red', 'error'))
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function cambiarTab(tipo) {
    fetch(BASE_URL + '/preferencias/guardarAjax', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ modulo: 'supercias_estructuras', preferencias: JSON.stringify({tabActivo: tipo}) })
    });
}

function filtrarTabla(tipo) {
    const term = document.getElementById('search-' + tipo).value.toLowerCase();
    const rows = document.querySelectorAll('#table-' + tipo + ' tbody tr');
    rows.forEach(r => {
        const text = r.innerText.toLowerCase();
        r.style.display = text.includes(term) ? '' : 'none';
    });
}
</script>
