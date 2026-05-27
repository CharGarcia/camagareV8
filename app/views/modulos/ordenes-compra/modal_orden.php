<?php /** @var array $perm */ ?>
<style>
    .oc-detalle-wrap { max-height: 350px; overflow: visible; }
    .oc-detalle-wrap thead th { position: sticky; top: 0; z-index: 1; background: #f8f9fa; }
</style>

<div class="modal fade" id="modalOrdenCompra" tabindex="-1" data-bs-backdrop="static" aria-labelledby="ocModalLabel">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0 cmg-favoritos-card" data-modulo="ordenes-compra">

            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold" id="ocModalLabel">
                    <i class="bi bi-cart3 text-primary me-2"></i>
                    <span id="oc_titulo_modal">Nueva Orden de Compra</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-3">
                <!-- Acciones Rápidas Superior -->
                <div class="d-flex justify-content-start gap-1 mb-3">
                    <button type="button" class="btn btn-outline-success btn-sm px-2 py-1"
                            onclick="window.PROV_abrirModalCrear && window.PROV_abrirModalCrear()"
                            title="Nuevo proveedor">
                        <i class="bi bi-person-plus"></i>
                    </button>
                </div>
                <hr class="text-muted my-0 mb-3 opacity-25">

                <form id="formOrdenCompra" autocomplete="off">
                    <input type="hidden" id="oc_id" name="id">

                    <!-- Fila 1: Serie | Secuencial | Estado | Proveedor -->
                    <div class="row g-3 mb-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Serie <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="oc_id_punto_emision" name="id_punto_emision"
                                    onchange="ocSyncSerie(this.value)">
                                <!-- poblado vía JS -->
                            </select>
                            <input type="hidden" name="id_establecimiento" id="oc_id_establecimiento">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Secuencial <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm bg-light text-center"
                                   id="oc_secuencial" name="secuencial" readonly placeholder="000000001" maxlength="9">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Estado</label>
                            <select class="form-select form-select-sm" id="oc_estado" name="estado">
                                <option value="borrador">Borrador</option>
                                <option value="aprobado">Aprobado</option>
                                <option value="recibido">Recibido</option>
                                <option value="anulado">Anulado</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold mb-1">Proveedor <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm position-relative">
                                <span class="input-group-text bg-white"><i class="bi bi-shop"></i></span>
                                <input type="text" class="form-control form-control-sm" id="oc_proveedor_texto"
                                       placeholder="Buscar por nombre o RUC..." autocomplete="off">
                                <input type="hidden" id="oc_proveedor_id" name="id_proveedor">
                                <div id="oc_lista_proveedores"
                                     class="list-group shadow position-absolute d-none"
                                     style="z-index:1060;width:100%;max-height:220px;overflow-y:auto;top:100%;left:0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fila 2: Fechas + Observaciones -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Fecha Orden <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="oc_fecha_orden" name="fecha_orden" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold mb-1">Fecha Recepción</label>
                            <input type="date" class="form-control form-control-sm" id="oc_fecha_recepcion" name="fecha_recepcion">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold mb-1">Observaciones</label>
                            <input type="text" class="form-control form-control-sm" id="oc_observaciones" name="observaciones"
                                   placeholder="Notas adicionales sobre la orden..." maxlength="500">
                        </div>
                    </div>

                    <hr class="text-muted opacity-25">

                    <!-- Detalle -->
                    <div class="border rounded-3 overflow-hidden bg-white shadow-sm mb-3">
                        <div class="table-responsive oc-detalle-wrap">
                            <table class="table table-sm table-detalle mb-0 text-nowrap align-middle">
                                <thead>
                                    <tr class="table-light border-bottom">
                                        <th class="ps-3 py-2 small fw-bold text-muted text-center" style="width:130px">Código</th>
                                        <th class="py-2 small fw-bold text-muted">Descripción <span class="text-danger">*</span></th>
                                        <th class="py-2 small fw-bold text-muted text-center" style="width:90px">Cantidad <span class="text-danger">*</span></th>
                                        <th class="py-2 small fw-bold text-muted text-center" style="width:110px">P. Unitario</th>
                                        <th class="py-2 small fw-bold text-muted" style="width:22%">Notas</th>
                                        <th style="width:40px"></th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyOcDetalle">
                                    <!-- filas dinámicas -->
                                </tbody>
                            </table>
                        </div>

                        <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold"
                                    onclick="ocAgregarFilaDetalle()">
                                <i class="bi bi-plus-circle me-1"></i> Agregar línea
                            </button>
                            <div class="small fw-bold text-muted pe-2">
                                Ítems: <span id="oc_count_items">0</span>
                            </div>
                        </div>
                    </div>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer justify-content-between bg-light border-top p-2">
                <div>
                    <?php if (!empty($perm['eliminar'])): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none"
                                id="oc_btn_eliminar" onclick="ocEliminar()">
                            <i class="bi bi-trash3 me-1"></i> Eliminar
                        </button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Cerrar
                    </button>
                    <button type="submit" form="formOrdenCompra" class="btn btn-primary btn-sm px-4 shadow-sm" id="oc_btn_guardar">
                        <i class="bi bi-check2-circle me-1"></i> Guardar
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Dropdown flotante global para búsqueda de productos -->
<div id="oc-dropdown-productos-global"
     class="list-group shadow position-fixed d-none"
     style="z-index:9999;min-width:380px;max-height:260px;overflow-y:auto;background:#fff">
</div>

<script>
function ocEscHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function ocDebounce(fn, ms) {
    let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
}

// ── Detalle helpers ───────────────────────────────────────────────────────────
window.ocLimpiarDetalle = function() {
    document.getElementById('tbodyOcDetalle').innerHTML = '';
    document.getElementById('oc_count_items').textContent = '0';
};

window.ocActualizarContador = function() {
    document.getElementById('oc_count_items').textContent =
        document.getElementById('tbodyOcDetalle').querySelectorAll('tr').length;
};

window.ocEliminarFila = function(btn) {
    btn.closest('tr').remove();
    ocActualizarContador();
};

window.ocObtenerItems = function() {
    const items = [];
    document.getElementById('tbodyOcDetalle').querySelectorAll('tr').forEach(tr => {
        const desc = tr.querySelector('.oc-item-descripcion')?.value?.trim() ?? '';
        if (!desc) return;
        items.push({
            id_producto:     tr.querySelector('.oc-item-id-producto')?.value  ?? null,
            descripcion:     desc,
            cantidad:        parseFloat(tr.querySelector('.oc-item-cantidad')?.value)  || 1,
            precio_unitario: parseFloat(tr.querySelector('.oc-item-precio')?.value)    || 0,
            notas:           tr.querySelector('.oc-item-notas')?.value?.trim()         ?? '',
        });
    });
    return items;
};

// ── Agregar fila con autocomplete (mismo patrón que pedidos) ──────────────────
window.ocAgregarFilaDetalle = function(item = {}) {
    const tbody  = document.getElementById('tbodyOcDetalle');
    const tr     = document.createElement('tr');
    tr.className = 'oc-fila-detalle';

    const codigo   = item.codigo         ?? '';
    const desc     = item.descripcion    ?? '';
    const cant     = item.cantidad       ?? '1';
    const precio   = item.precio_unitario ?? '0.00';
    const notas    = item.notas          ?? '';
    const idProd   = item.id_producto    ?? '';

    tr.innerHTML = `
        <td class="text-center align-middle position-relative" style="width:130px">
            <input type="text" class="form-control form-control-sm input-detalle oc-item-codigo text-center border-primary border-opacity-25"
                   placeholder="Código..." autocomplete="off" value="${ocEscHtml(codigo)}">
        </td>
        <td class="align-middle position-relative">
            <input type="text" class="form-control form-control-sm input-detalle oc-item-descripcion fw-bold border-primary border-opacity-25"
                   placeholder="Escribe o busca un producto..." autocomplete="off" value="${ocEscHtml(desc)}">
            <input type="hidden" class="oc-item-id-producto" value="${ocEscHtml(idProd)}">
        </td>
        <td class="align-middle text-center" style="width:90px">
            <input type="number" class="form-control form-control-sm input-detalle oc-item-cantidad text-center"
                   value="${ocEscHtml(cant)}" min="0.000001" step="any">
        </td>
        <td class="align-middle text-center" style="width:110px">
            <input type="number" class="form-control form-control-sm input-detalle oc-item-precio text-end"
                   value="${ocEscHtml(precio)}" min="0" step="any">
        </td>
        <td class="align-middle" style="width:22%">
            <input type="text" class="form-control form-control-sm input-detalle oc-item-notas"
                   placeholder="Notas..." maxlength="200" value="${ocEscHtml(notas)}">
        </td>
        <td class="text-center p-0 align-middle" style="width:40px">
            <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0"
                    onclick="ocEliminarFila(this)" title="Eliminar ítem">
                <i class="bi bi-trash3 fs-6"></i>
            </button>
        </td>`;

    tbody.appendChild(tr);
    ocActualizarContador();

    const inputCod  = tr.querySelector('.oc-item-codigo');
    const inputDesc = tr.querySelector('.oc-item-descripcion');
    const dropdown  = document.getElementById('oc-dropdown-productos-global');

    if (!item.descripcion) setTimeout(() => inputCod.focus(), 50);

    const seleccionarProducto = (p) => {
        inputCod.value  = p.codigo       ?? '';
        inputDesc.value = p.descripcion  ?? '';
        tr.querySelector('.oc-item-id-producto').value = p.id ?? '';
        const precio_field = tr.querySelector('.oc-item-precio');
        if (precio_field && p.precio_unitario != null) precio_field.value = parseFloat(p.precio_unitario).toFixed(2);
        dropdown.classList.add('d-none');
        const cant = tr.querySelector('.oc-item-cantidad');
        cant.focus(); cant.select();
    };

    const buscarProducto = async (q, srcInput) => {
        q = q.trim();
        if (q.length < 2) { dropdown.classList.add('d-none'); return; }
        const rect = srcInput.getBoundingClientRect();
        dropdown.style.top   = `${rect.bottom + window.scrollY + 2}px`;
        dropdown.style.left  = `${rect.left   + window.scrollX}px`;
        dropdown.style.width = `${Math.max(rect.width, 380)}px`;
        dropdown.classList.remove('d-none');
        dropdown.innerHTML = '<div class="list-group-item small text-muted">Buscando...</div>';
        try {
            const resp = await fetch(`${OC_URL_BASE}/getProductosAjax?q=${encodeURIComponent(q)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const json = await resp.json();
            dropdown.innerHTML = '';
            if (json.ok && json.data?.length > 0) {
                json.data.forEach(p => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'list-group-item list-group-item-action small py-1 border-bottom';
                    b.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center text-start">
                            <div>
                                <div class="fw-bold text-dark">${ocEscHtml(p.descripcion)}</div>
                                <div class="text-muted" style="font-size:0.75rem">${ocEscHtml(p.codigo ?? '')}</div>
                            </div>
                            <small class="text-muted ms-2">${parseFloat(p.precio_unitario ?? 0).toFixed(2)}</small>
                        </div>`;
                    b.onmousedown = (e) => { e.preventDefault(); seleccionarProducto(p); };
                    dropdown.appendChild(b);
                });
            } else {
                dropdown.innerHTML = '<div class="list-group-item small text-muted">Sin coincidencias en el catálogo</div>';
            }
        } catch(e) {}
    };

    const setupAutocomplete = (inputEl) => {
        inputEl.addEventListener('input', ocDebounce((e) => buscarProducto(e.target.value, inputEl), 380));
        inputEl.addEventListener('keydown', (e) => {
            if (e.key === 'Delete' || e.key === 'Backspace') {
                e.preventDefault();
                inputCod.value = ''; inputDesc.value = '';
                tr.querySelector('.oc-item-id-producto').value = '';
                dropdown.classList.add('d-none');
            }
            if (e.key === 'Enter') {
                const first = dropdown.querySelector('button');
                if (first && !dropdown.classList.contains('d-none')) {
                    e.preventDefault();
                    first.onmousedown(new MouseEvent('mousedown'));
                }
            }
        });
        inputEl.addEventListener('blur', () => setTimeout(() => dropdown.classList.add('d-none'), 200));
    };

    setupAutocomplete(inputCod);
    setupAutocomplete(inputDesc);
};
</script>
