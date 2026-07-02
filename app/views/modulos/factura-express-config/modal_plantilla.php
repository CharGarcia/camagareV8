<?php
$vistaConfigFexqr = \App\Helpers\PreferenciasHelper::getPreferenciasVista('factura-express-config');
echo \App\Helpers\PreferenciasHelper::renderEstilosPestanasOcultas($vistaConfigFexqr, 'estiloVistaPestanasFexqr');

$urlBase        = rtrim(BASE_URL, '/') . '/modulos/factura-express-config';
$permFexqr      = $perm ?? [];
$establecimientos = $establecimientos ?? [];
$puntosEmision    = $puntosEmision    ?? [];
$formasPago       = $formasPago       ?? [];
?>
<style>
    /* Grilla de ítems con el mismo diseño que la factura de venta (.table-detalle) */
    .modal-fexqr .table-detalle th       { font-size: .7rem !important; padding: 4px 8px !important; text-transform: uppercase; background-color: #f8f9fa; }
    .modal-fexqr .table-detalle td       { padding: 0 !important; vertical-align: middle; }
    .modal-fexqr .input-detalle          { height: 30px !important; font-size: .82rem !important; padding: 2px 8px !important; border: none; background: transparent; width: 100%; }
    .modal-fexqr .input-detalle:focus    { background: #fff; box-shadow: inset 0 0 0 1px #0d6efd; outline: none; }
    .modal-fexqr .row-detalle:hover      { background-color: rgba(13,110,253,.03); }
    .modal-fexqr .remove-row             { color: #dc3545; opacity: 0; transition: opacity .2s; }
    .modal-fexqr .row-detalle:hover .remove-row { opacity: 1; }
    #fexqrDdProd                         { z-index:9999 !important; position:fixed; max-height:220px; overflow-y:auto; min-width:350px; }
</style>

<div class="modal fade modal-fexqr" id="modalFexqr" tabindex="-1" data-bs-backdrop="static" style="z-index:1060">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <form id="formFexqr" novalidate>
                <div class="modal-header bg-light py-3">
                    <h5 class="modal-title fw-bold fs-6">
                        <i class="bi bi-qr-code text-primary me-2"></i>
                        <span id="fexqrModalTitulo">Nueva Plantilla QR</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-0">
                    <input type="hidden" id="fexqr_id" name="id" value="">

                    <!-- Pestañas -->
                    <div class="d-flex align-items-center bg-light px-3 pt-2">
                        <ul class="nav nav-tabs border-bottom-0 flex-grow-1" role="tablist">
                            <li class="nav-item"><a class="nav-link active py-2 small" data-bs-toggle="tab" href="#pane-fexqr-general"><i class="bi bi-sliders me-1"></i>Configuración</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" data-bs-toggle="tab" href="#pane-fexqr-items"><i class="bi bi-list-ul me-1"></i>Productos / Servicios</a></li>
                            <li class="nav-item"><a class="nav-link py-2 small" data-bs-toggle="tab" href="#pane-fexqr-mensajes"><i class="bi bi-chat-text me-1"></i>Mensajes</a></li>
                        </ul>
                        <div class="ms-auto pb-1">
                            <?php
                            $pestanasFexqr = [
                                'pane-fexqr-items'    => 'Productos / Servicios',
                                'pane-fexqr-mensajes' => 'Mensajes',
                            ];
                            echo \App\Helpers\PreferenciasHelper::renderDropdownPestanas($pestanasFexqr, $vistaConfigFexqr, 'factura-express-config');
                            ?>
                        </div>
                    </div>
                    <div class="border-bottom bg-light"></div>

                    <div class="tab-content">

                        <!-- ── Pestaña 1: Configuración ──────────────────── -->
                        <div class="tab-pane fade show active p-3" id="pane-fexqr-general">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold">Nombre de la plantilla *</label>
                                    <input type="text" class="form-control form-control-sm" name="nombre" id="fexqr_nombre"
                                           placeholder="Ej: Consultorio Dr. García" maxlength="150" required>
                                    <small class="text-muted">Este nombre es solo para identificarla internamente.</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Límite solicitudes/hora</label>
                                    <input type="number" class="form-control form-control-sm" name="max_solicitudes_hora"
                                           id="fexqr_max_hora" value="10" min="1" max="200">
                                    <small class="text-muted">Por IP. Anti-spam.</small>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold">Descripción interna</label>
                                    <input type="text" class="form-control form-control-sm" name="descripcion"
                                           id="fexqr_descripcion" maxlength="300" placeholder="Opcional">
                                </div>

                                <!-- Opciones -->
                                <div class="col-12">
                                    <div class="p-3 border rounded-3 bg-light">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="activo" id="fexqrChkActivo" value="1" checked>
                                                    <label class="form-check-label small fw-bold" for="fexqrChkActivo">Plantilla activa</label>
                                                </div>
                                                <small class="text-muted d-block ms-4">Si está inactiva, el QR mostrará un mensaje de no disponible.</small>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="requiere_aprobacion" id="fexqrChkAprobacion" value="1" checked>
                                                    <label class="form-check-label small fw-bold" for="fexqrChkAprobacion">Requiere aprobación manual</label>
                                                </div>
                                                <small class="text-muted d-block ms-4">Si se desactiva, la factura se genera automáticamente.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Serie de facturación -->
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Serie de facturación <span class="text-danger">*</span></label>
                                    <div class="p-2 border rounded-3 bg-white">
                                        <div class="row g-2">
                                            <div class="col-md-5">
                                                <label class="form-label small text-muted mb-1">Establecimiento</label>
                                                <select class="form-select form-select-sm" id="fexqr_id_establecimiento" name="id_establecimiento" required>
                                                    <option value="">- Seleccione -</option>
                                                    <?php foreach ($establecimientos as $est): ?>
                                                        <option value="<?= $est['id'] ?>"
                                                            data-codigo="<?= htmlspecialchars($est['codigo']) ?>">
                                                            <?= htmlspecialchars($est['codigo']) ?> - <?= htmlspecialchars($est['nombre']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label small text-muted mb-1">Punto de emisión</label>
                                                <select class="form-select form-select-sm" id="fexqr_id_punto_emision" name="id_punto_emision" required>
                                                    <option value="">- Seleccione establecimiento -</option>
                                                    <?php foreach ($puntosEmision as $pe): ?>
                                                        <option value="<?= $pe['id'] ?>"
                                                            data-est="<?= $pe['id_establecimiento'] ?>"
                                                            data-codigo="<?= htmlspecialchars($pe['codigo_punto']) ?>">
                                                            <?= htmlspecialchars($pe['codigo_punto']) ?> - <?= htmlspecialchars($pe['nombre']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small text-muted mb-1">Serie</label>
                                                <input type="text" class="form-control form-control-sm bg-light" id="fexqrSeriePreview" readonly placeholder="000-000">
                                            </div>
                                        </div>
                                        <small class="text-muted d-block mt-1"><i class="bi bi-info-circle me-1"></i>Las facturas generadas por este QR usarán esta serie.</small>
                                    </div>
                                </div>

                                <!-- Forma de pago -->
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Forma de pago</label>
                                    <select class="form-select form-select-sm" name="forma_pago" id="fexqr_forma_pago" required>
                                        <?php foreach ($formasPago as $fp): ?>
                                            <option value="<?= htmlspecialchars($fp['codigo']) ?>"
                                                <?= ($fp['codigo'] === '20') ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($fp['codigo']) ?> - <?= htmlspecialchars($fp['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <?php if (empty($formasPago)): ?>
                                            <option value="20">20 - Otros con utilización del sistema financiero</option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <!-- Campos del formulario público -->
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Campos visibles en el formulario público</label>
                                    <div class="p-2 border rounded-3 bg-white d-flex flex-wrap gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="fexqr_campo_nombre" checked disabled>
                                            <label class="form-check-label small" for="fexqr_campo_nombre">Nombre <span class="text-danger">*</span></label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="fexqr_campo_identificacion" checked disabled>
                                            <label class="form-check-label small" for="fexqr_campo_identificacion">Identificación <span class="text-danger">*</span></label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="fexqr_campo_correo" checked disabled>
                                            <label class="form-check-label small" for="fexqr_campo_correo">Correo electrónico <span class="text-danger">*</span></label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="fexqr_campo_telefono">
                                            <label class="form-check-label small" for="fexqr_campo_telefono">Teléfono</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="fexqr_campo_direccion">
                                            <label class="form-check-label small" for="fexqr_campo_direccion">Dirección</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── Pestaña 2: Ítems ──────────────────────────── -->
                        <div class="tab-pane fade p-3" id="pane-fexqr-items">
                            <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                                <div class="table-responsive" style="max-height:350px;">
                                    <table class="table table-sm table-detalle mb-0 text-nowrap">
                                        <thead>
                                            <tr class="table-light border-bottom">
                                                <th class="ps-3 py-2 small fw-bold text-muted" style="width:30%;">Descripción / Servicio</th>
                                                <th class="py-2 small fw-bold text-muted text-end" style="width:11%;">Precio Unit.</th>
                                                <th class="py-2 small fw-bold text-muted text-center" style="width:16%;">IVA</th>
                                                <th class="py-2 small fw-bold text-muted text-center" style="width:10%;">Cant. default</th>
                                                <th class="py-2 small fw-bold text-muted text-center" style="width:12%;">Cant. editable</th>
                                                <th class="py-2 small fw-bold text-muted text-center" style="width:12%;">Preseleccionado</th>
                                                <th style="width:40px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="fexqrTbodyItems">
                                            <tr id="fexqrItemsVacioRow">
                                                <td colspan="7" class="text-center text-muted py-3 small">
                                                    <i class="bi bi-box-seam me-1"></i>Agregue los productos o servicios que aparecerán en el formulario
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Agregar línea + contador -->
                                <div class="p-2 border-top bg-light d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-bold" onclick="fexqrAgregarItemFila({})">
                                        <i class="bi bi-plus-circle me-1"></i> Agregar línea
                                    </button>
                                    <div class="small fw-bold text-muted pe-3">
                                        Items: <span id="fexqrCountItems">0</span>
                                    </div>
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block">
                                <i class="bi bi-info-circle me-1"></i>
                                Los ítems "Preseleccionados" aparecerán marcados por defecto en el formulario público.
                                Los de "Cant. editable" permiten que el cliente cambie la cantidad.
                                El <strong>IVA</strong> lo toma automáticamente del producto/servicio y no es editable (ni aquí ni para el cliente).
                            </small>
                        </div>

                        <!-- ── Pestaña 3: Mensajes ───────────────────────── -->
                        <div class="tab-pane fade p-3" id="pane-fexqr-mensajes">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Mensaje de bienvenida</label>
                                    <textarea class="form-control form-control-sm" name="mensaje_bienvenida" id="fexqr_bienvenida"
                                              rows="3" maxlength="500"
                                              placeholder="Ej: Bienvenido al consultorio. Complete sus datos para solicitar su factura."></textarea>
                                    <small class="text-muted">Aparece en la parte superior del formulario público.</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Mensaje de agradecimiento</label>
                                    <textarea class="form-control form-control-sm" name="mensaje_gracias" id="fexqr_gracias"
                                              rows="3" maxlength="500"
                                              placeholder="Ej: Gracias por su visita. Recibirá su factura en su correo electrónico en breve."></textarea>
                                    <small class="text-muted">Aparece en la pantalla de confirmación tras enviar la solicitud.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light border-top p-2">
                    <div>
                        <?php if ($permFexqr['eliminar'] ?? false): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm px-3 d-none"
                                    id="btnEliminarFexqr" onclick="fexqrEliminar()">
                                <i class="bi bi-trash3 me-1"></i>Eliminar
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fa-solid fa-xmark me-1"></i>Cerrar
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm px-4" id="btnGuardarFexqr">
                            <i class="bi bi-check2-circle me-1"></i>Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function(){
    'use strict';
    const urlBase = '<?= $urlBase ?>';
    let itemIdx   = 0;
    let timerProd;

    // ── Serie: filtrar puntos de emisión por establecimiento ─────────────────
    const selEst  = document.getElementById('fexqr_id_establecimiento');
    const selPe   = document.getElementById('fexqr_id_punto_emision');
    const inpSerie = document.getElementById('fexqrSeriePreview');

    // Guardar todas las opciones de puntos de emisión al inicio
    const todasOpcPe = Array.from(selPe.options).slice(1); // excluye el placeholder

    function filtrarPuntosEmision(idEst) {
        // Remover todas las opciones excepto placeholder
        while (selPe.options.length > 1) selPe.remove(1);
        if (!idEst) { actualizarSerie(); return; }
        todasOpcPe.forEach(opt => {
            if (opt.dataset.est === String(idEst)) {
                selPe.appendChild(opt.cloneNode(true));
            }
        });
        if (selPe.options.length === 2) {
            // Solo hay un punto: seleccionarlo automáticamente
            selPe.selectedIndex = 1;
        }
        actualizarSerie();
    }

    function actualizarSerie() {
        const optEst = selEst.options[selEst.selectedIndex];
        const optPe  = selPe.options[selPe.selectedIndex];
        const codEst = optEst?.dataset?.codigo ?? '';
        const codPe  = optPe?.dataset?.codigo  ?? '';
        inpSerie.value = (codEst && codPe) ? `${codEst}-${codPe}` : '';
    }

    selEst.addEventListener('change', () => filtrarPuntosEmision(selEst.value));
    selPe.addEventListener('change',  actualizarSerie);

    // ── Buscador predictivo de productos (desde el input de descripción) ───────
    // Dropdown global anclado al body (igual que factura de venta)
    let ddProd = document.getElementById('fexqrDdProd');
    if (!ddProd) {
        ddProd = document.createElement('div');
        ddProd.id        = 'fexqrDdProd';
        ddProd.className = 'list-group shadow position-fixed d-none';
        ddProd.style.cssText = 'z-index:9999;min-width:400px;max-height:250px;overflow-y:auto;background:#fff;';
        document.body.appendChild(ddProd);
    }
    let fexqrActiveInput = null; // input de descripción que disparó la búsqueda

    function posicionarDd(input) {
        const rect = input.getBoundingClientRect();
        ddProd.style.top   = (rect.bottom + 2) + 'px';
        ddProd.style.left  = rect.left + 'px';
        ddProd.style.width = Math.max(rect.width, 350) + 'px';
    }

    // Delegación: se busca mientras se escribe en la descripción de cualquier fila
    const tbodyItems = document.getElementById('fexqrTbodyItems');
    tbodyItems.addEventListener('input', (e) => {
        const input = e.target.closest('.fexqr-desc-search');
        if (!input) return;
        fexqrActiveInput = input;
        clearTimeout(timerProd);
        const q = input.value.trim();
        if (q.length < 2) { ddProd.classList.add('d-none'); return; }
        timerProd = setTimeout(() => buscarProductos(q), 300);
    });
    tbodyItems.addEventListener('focusout', (e) => {
        if (e.target.closest('.fexqr-desc-search')) {
            setTimeout(() => ddProd.classList.add('d-none'), 200);
        }
    });

    // Delete / Retroceso en la descripción: si la línea está enlazada a un producto,
    // limpia toda la fila para poder buscar de nuevo (en texto libre se edita normal).
    tbodyItems.addEventListener('keydown', (e) => {
        if (e.key !== 'Backspace' && e.key !== 'Delete') return;
        const input = e.target.closest('.fexqr-desc-search');
        if (!input) return;
        const tr     = input.closest('tr');
        const idProd = tr.querySelector('[name*="id_producto"]');
        if (idProd && idProd.value) {
            e.preventDefault();
            input.value  = '';
            idProd.value = '';
            const precio = tr.querySelector('[name*="precio_unitario"]'); if (precio) precio.value = '0.00';
            const ivaHid = tr.querySelector('[name*="porcentaje_iva"]');  if (ivaHid) ivaHid.value = '0.00';
            const ivaBdg = tr.querySelector('.fexqr-iva-badge');          if (ivaBdg) ivaBdg.textContent = '0%';
            ddProd.classList.add('d-none');
            input.focus();
        }
    });

    async function buscarProductos(q) {
        if (!fexqrActiveInput) return;
        posicionarDd(fexqrActiveInput);
        ddProd.innerHTML = '<div class="list-group-item small text-muted py-1 px-2">Buscando...</div>';
        ddProd.classList.remove('d-none');
        try {
            const r = await fetch(`${urlBase}/buscarProductosAjax?q=${encodeURIComponent(q)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await r.json();
            const lista = d.data ?? [];
            ddProd.innerHTML = '';
            if (!lista.length) {
                ddProd.innerHTML = '<div class="list-group-item small text-muted py-1 px-2">Sin coincidencias en el catálogo</div>';
                return;
            }
            lista.forEach(p => {
                const btn = document.createElement('button');
                btn.type      = 'button';
                btn.className = 'list-group-item list-group-item-action small py-1 border-bottom';
                btn.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center text-start">
                        <div class="pe-3">
                            <div class="fw-bold text-dark">${(p.nombre ?? '').replace(/</g,'&lt;')}</div>
                            <div class="text-muted" style="font-size:.72rem">${p.codigo ?? ''}</div>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10">
                            $${parseFloat(p.precio_unitario ?? 0).toFixed(2)}
                        </span>
                    </div>`;
                btn.onmousedown = (ev) => {
                    ev.preventDefault();
                    fexqrAplicarProductoAFila(fexqrActiveInput, p);
                    ddProd.classList.add('d-none');
                };
                ddProd.appendChild(btn);
            });
        } catch(e) { console.error(e); ddProd.classList.add('d-none'); }
    }

    // Rellena la fila del input activo con los datos del producto elegido
    // Etiqueta de IVA a mostrar: el nombre de la tarifa (ej. "Exento de IVA",
    // "No objeto de impuesto", "15%"); si no hay nombre, cae al porcentaje.
    function fexqrIvaLabel(nombre, pct) {
        return (nombre != null && String(nombre).trim() !== '')
            ? String(nombre)
            : (parseFloat(pct ?? 0).toFixed(0) + '%');
    }
    const fexqrEscHtml = (s) => String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

    window.fexqrAplicarProductoAFila = function(input, p) {
        const tr = input.closest('tr');
        if (!tr) return;
        input.value = p.nombre ?? '';
        const idProd = tr.querySelector('[name*="id_producto"]');   if (idProd) idProd.value = p.id ?? '';
        const precio = tr.querySelector('[name*="precio_unitario"]'); if (precio) precio.value = parseFloat(p.precio_unitario ?? 0).toFixed(2);
        const ivaHid = tr.querySelector('[name*="porcentaje_iva"]');  if (ivaHid) ivaHid.value = parseFloat(p.porcentaje_iva ?? 0).toFixed(2);
        const ivaBdg = tr.querySelector('.fexqr-iva-badge');          if (ivaBdg) ivaBdg.textContent = fexqrIvaLabel(p.nombre_iva, p.porcentaje_iva);
    };

    // ── Contador de ítems ─────────────────────────────────────────────────────
    window.fexqrActualizarContador = function() {
        const n = document.querySelectorAll('#fexqrTbodyItems tr.row-detalle').length;
        const el = document.getElementById('fexqrCountItems');
        if (el) el.textContent = n;
    };

    // ── Agregar fila de ítem ──────────────────────────────────────────────────
    window.fexqrAgregarItemFila = function(item = {}) {
        document.getElementById('fexqrItemsVacioRow')?.remove();
        const idx = itemIdx++;
        const tr  = document.createElement('tr');
        tr.className = 'row-detalle';
        tr.innerHTML = `
            <td class="ps-3">
                <input type="hidden" name="items[${idx}][id_producto]" value="${item.id_producto ?? ''}">
                <input type="text" class="input-detalle fexqr-desc-search" name="items[${idx}][descripcion]"
                       value="${(item.descripcion ?? '').replace(/"/g,'&quot;')}" autocomplete="off"
                       placeholder="Escriba para buscar producto/servicio..." style="min-width:160px;">
            </td>
            <td><input type="number" class="input-detalle text-end" name="items[${idx}][precio_unitario]"
                value="${parseFloat(item.precio_unitario ?? 0).toFixed(2)}" min="0" step="0.01"></td>
            <td class="text-center" style="white-space:normal;">
                <span class="badge bg-light text-dark border fexqr-iva-badge" title="El IVA lo define el producto/servicio y no se puede editar aquí.">${fexqrEscHtml(fexqrIvaLabel(item.nombre_iva, item.porcentaje_iva))}</span>
                <input type="hidden" name="items[${idx}][porcentaje_iva]" value="${parseFloat(item.porcentaje_iva ?? 0).toFixed(2)}">
            </td>
            <td class="text-center"><input type="number" class="input-detalle text-center" name="items[${idx}][cantidad_default]"
                value="${parseFloat(item.cantidad_default ?? 1)}" min="0.001" step="0.001"></td>
            <td class="text-center"><input type="checkbox" class="form-check-input" name="items[${idx}][cantidad_editable]"
                value="1" ${item.cantidad_editable ? 'checked' : ''}></td>
            <td class="text-center"><input type="checkbox" class="form-check-input" name="items[${idx}][seleccionado_default]"
                value="1" ${item.seleccionado_default !== false ? 'checked' : ''}></td>
            <td class="text-center">
                <button type="button" class="btn btn-sm remove-row px-1 py-0" onclick="this.closest('tr').remove(); fexqrActualizarContador();" title="Quitar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>`;
        document.getElementById('fexqrTbodyItems').appendChild(tr);
        fexqrActualizarContador();
    };

    // ── Guardar (crear / actualizar) ──────────────────────────────────────────
    document.getElementById('formFexqr').addEventListener('submit', async function(e) {
        e.preventDefault();
        const id     = document.getElementById('fexqr_id').value;
        const method = id ? 'update' : 'store';
        const btn    = document.getElementById('btnGuardarFexqr');

        // Validaciones obligatorias en frontend
        const idEst = document.getElementById('fexqr_id_establecimiento').value;
        const idPe  = document.getElementById('fexqr_id_punto_emision').value;
        const fp    = document.getElementById('fexqr_forma_pago').value;
        if (!idEst) {
            Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Debe seleccionar un establecimiento.' });
            document.getElementById('fexqr_id_establecimiento').focus();
            return;
        }
        if (!idPe) {
            Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Debe seleccionar un punto de emisión.' });
            document.getElementById('fexqr_id_punto_emision').focus();
            return;
        }
        if (!fp) {
            Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Debe seleccionar una forma de pago.' });
            document.getElementById('fexqr_forma_pago').focus();
            return;
        }

        btn.disabled = true;

        // Recoger ítems
        const items = [];
        document.querySelectorAll('#fexqrTbodyItems tr.row-detalle').forEach(tr => {
            items.push({
                id_producto:          tr.querySelector('[name*="id_producto"]')?.value || null,
                descripcion:          tr.querySelector('[name*="descripcion"]')?.value ?? '',
                precio_unitario:      tr.querySelector('[name*="precio_unitario"]')?.value ?? 0,
                porcentaje_iva:       tr.querySelector('[name*="porcentaje_iva"]')?.value ?? 0,
                cantidad_default:     tr.querySelector('[name*="cantidad_default"]')?.value ?? 1,
                cantidad_editable:    tr.querySelector('[name*="cantidad_editable"]')?.checked ? 1 : 0,
                seleccionado_default: tr.querySelector('[name*="seleccionado_default"]')?.checked ? 1 : 0,
            });
        });

        // Recoger campos_config
        const camposConfig = JSON.stringify({
            nombre:         true,
            identificacion: true,
            correo:         true,
            telefono:       document.getElementById('fexqr_campo_telefono').checked,
            direccion:      document.getElementById('fexqr_campo_direccion').checked,
        });

        const fd = new FormData(this);
        fd.set('items_json',    JSON.stringify(items));
        fd.set('campos_config', camposConfig);
        if (document.getElementById('fexqrChkActivo').checked) fd.set('activo','1'); else fd.delete('activo');
        if (document.getElementById('fexqrChkAprobacion').checked) fd.set('requiere_aprobacion','1'); else fd.delete('requiere_aprobacion');

        try {
            const r = await fetch(`${urlBase}/${method}`, { method:'POST', body:fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await r.json();
            if (d.ok) {
                await Swal.fire({ icon:'success', title:'Guardado', text: d.mensaje, timer:1500, showConfirmButton:false });
                bootstrap.Modal.getInstance(document.getElementById('modalFexqr'))?.hide();
                fexqrBuscar(1);
            } else {
                Swal.fire({ icon:'error', title:'Error', text: d.mensaje });
            }
        } catch(err) {
            Swal.fire({ icon:'error', title:'Error', text:'Error de conexión.' });
        } finally { btn.disabled = false; }
    });

    // ── Eliminar ──────────────────────────────────────────────────────────────
    window.fexqrEliminar = async function() {
        const id = document.getElementById('fexqr_id').value;
        if (!id) return;
        const confirm = await Swal.fire({
            icon: 'warning',
            title: '¿Eliminar plantilla?',
            text: 'Los datos históricos de solicitudes se conservarán.',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        });
        if (!confirm.isConfirmed) return;
        const fd = new FormData(); fd.append('id', id);
        try {
            const r = await fetch(urlBase + '/delete', { method:'POST', body:fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await r.json();
            if (d.ok) {
                await Swal.fire({ icon:'success', title:'Eliminado', text: d.mensaje, timer:1500, showConfirmButton:false });
                bootstrap.Modal.getInstance(document.getElementById('modalFexqr'))?.hide();
                fexqrBuscar(1);
            } else {
                Swal.fire({ icon:'error', title:'Error', text: d.mensaje });
            }
        } catch(e) {
            Swal.fire({ icon:'error', title:'Error', text:'Error de conexión.' });
        }
    };

})();
</script>
