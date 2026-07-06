<?php
/** @var array $perm */
/** @var array $puntos */
?>
<div class="modal fade" id="modalCambio" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="tituloModalCambio">
                    <i class="bi bi-arrow-left-right me-1"></i> Nuevo Cambio de productos
                </h5>
                <span id="cam_estado_badge" class="badge bg-secondary bg-opacity-10 text-secondary ms-2 d-none">Nuevo</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <!-- Barra de acciones superior (PDF / Correo / WhatsApp) -->
                <div class="d-flex gap-1 align-items-center flex-wrap mb-3 pb-2 border-bottom">
                    <button type="button" class="btn btn-outline-danger btn-sm px-2" onclick="camPdf()" title="Exportar PDF"><i class="bi bi-file-earmark-pdf"></i></button>
                    <button type="button" class="btn btn-outline-info btn-sm px-2" onclick="camEmail()" title="Enviar por correo"><i class="bi bi-envelope"></i></button>
                    <button type="button" class="btn btn-outline-success btn-sm px-2" onclick="camWhatsapp()" title="Enviar por WhatsApp"><i class="bi bi-whatsapp"></i></button>
                </div>

                <ul class="nav nav-tabs mb-3" id="tabsCambio" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="cam-tab-general-btn" data-bs-toggle="tab" href="#cam-tab-general" role="tab"><i class="bi bi-info-circle me-1"></i> General</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="cam-tab-asiento-btn" data-bs-toggle="tab" href="#cam-tab-asiento" role="tab"><i class="bi bi-calculator me-1"></i> Asiento contable</a>
                    </li>
                </ul>
                <div class="tab-content" id="tabsCambioContent">
                <div class="tab-pane fade show active" id="cam-tab-general" role="tabpanel">
                <form id="formCambio" autocomplete="off">
                    <input type="hidden" id="cam_id">

                    <!-- Numeración + cliente -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Fecha</label>
                            <input type="date" id="cam_fecha_cambio" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Serie</label>
                            <select id="cam_select_serie" class="form-select form-select-sm" onchange="camSerieChange()">
                                <?php if (empty($puntos)): ?>
                                    <option value="">— Sin secuencial configurado —</option>
                                <?php else: ?>
                                    <?php foreach ($puntos as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>"
                                            data-cod-est="<?= htmlspecialchars($p['cod_establecimiento'] ?? '') ?>"
                                            data-cod-punto="<?= htmlspecialchars($p['codigo_punto'] ?? '') ?>">
                                            <?= htmlspecialchars(($p['cod_establecimiento'] ?? '') . '-' . ($p['codigo_punto'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <input type="hidden" id="cam_serie">
                            <input type="hidden" id="cam_id_punto_emision">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Secuencial</label>
                            <input type="text" id="cam_secuencial" class="form-control form-control-sm bg-light text-center" readonly placeholder="000000000">
                        </div>
                        <div class="col-md-6 position-relative">
                            <label class="form-label small mb-1">Cliente</label>
                            <input type="text" id="cam_cliente_busqueda" class="form-control form-control-sm" placeholder="Buscar cliente por nombre o identificación..." oninput="camBuscarClientes(this.value)" autocomplete="off">
                            <input type="hidden" id="cam_id_cliente">
                            <input type="hidden" id="cam_cliente_email">
                            <div id="cam_clientes_dropdown" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1080; max-height:240px; overflow:auto;"></div>
                        </div>
                    </div>

                    <!-- Motivo, Observaciones y Estado -->
                    <div class="row g-2 mb-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Motivo</label>
                            <input type="text" id="cam_motivo" class="form-control form-control-sm" placeholder="Motivo del cambio (opcional)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Observaciones</label>
                            <input type="text" id="cam_observaciones" class="form-control form-control-sm" placeholder="Observaciones (opcional)">
                        </div>
                        <div class="col-md-2 d-none" id="cam_estado_wrapper">
                            <label class="form-label small mb-1">Estado</label>
                            <select id="cam_estado_selector" class="form-select form-select-sm fw-bold" onchange="camCambiarEstado(this.value)">
                                <option value="Borrador">Borrador</option>
                                <option value="Emitida">Emitida</option>
                                <option value="Anulada">Anulada</option>
                            </select>
                        </div>
                    </div>

                    <!-- ── Productos que devuelve ─────────────────────────────── -->
                    <div class="border rounded-3 p-2 mb-2 bg-light bg-opacity-50">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="mb-0 fw-bold text-success"><i class="bi bi-box-arrow-in-down me-1"></i> Productos que devuelve</h6>
                            <span id="cam_dev_info" class="small text-muted"></span>
                        </div>
                        <div class="position-relative mb-2" id="cam_dev_search_wrap">
                            <input type="text" id="cam_dev_busqueda" class="form-control form-control-sm" placeholder="Buscar producto facturado o cambiado..." oninput="camBuscarLineas(this.value)" autocomplete="off" disabled>
                            <div id="cam_dev_dropdown" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1075; max-height:240px; overflow:auto;"></div>
                        </div>
                        <div class="table-responsive border rounded-3 bg-white" style="max-height:26vh; overflow:auto;">
                            <table class="table table-sm table-hover mb-0 align-middle" id="tablaCamDev">
                                <thead class="table-light">
                                    <tr class="small">
                                        <th>Origen</th>
                                        <th>Producto</th>
                                        <th>Lote / NUP</th>
                                        <th class="text-end">Saldo</th>
                                        <th class="text-end" style="width:110px">Cantidad</th>
                                        <th class="text-end" style="width:90px">Total</th>
                                        <th style="width:34px"></th>
                                    </tr>
                                </thead>
                                <tbody id="cam_dev_body">
                                    <tr><td colspan="7" class="text-center text-muted py-3">Seleccione un cliente y busque el producto a devolver.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ── Productos que entrega a cambio ─────────────────────── -->
                    <div class="border rounded-3 p-2 mb-2 bg-light bg-opacity-50">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-box-arrow-up me-1"></i> Productos que entrega a cambio</h6>
                            <span id="cam_ent_info" class="small text-muted"></span>
                        </div>
                        <div class="position-relative mb-2" id="cam_ent_search_wrap">
                            <input type="text" id="cam_ent_busqueda" class="form-control form-control-sm" placeholder="Buscar producto del catálogo..." oninput="camBuscarProductos(this.value)" autocomplete="off">
                            <div id="cam_ent_dropdown" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1075; max-height:240px; overflow:auto;"></div>
                        </div>
                        <div class="table-responsive border rounded-3 bg-white" style="max-height:26vh; overflow:auto;">
                            <table class="table table-sm table-hover mb-0 align-middle" id="tablaCamEnt">
                                <thead class="table-light">
                                    <tr class="small">
                                        <th>Producto</th>
                                        <th style="width:150px">Bodega</th>
                                        <th class="text-end" style="width:110px">Precio</th>
                                        <th class="text-end" style="width:70px">IVA %</th>
                                        <th class="text-end" style="width:100px">Cantidad</th>
                                        <th class="text-end" style="width:90px">Total</th>
                                        <th style="width:34px"></th>
                                    </tr>
                                </thead>
                                <tbody id="cam_ent_body">
                                    <tr><td colspan="7" class="text-center text-muted py-3">Busque un producto del catálogo para entregar.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Resumen (diferencia informativa) -->
                    <div class="d-flex justify-content-end">
                        <div class="border rounded-3 p-2 bg-white shadow-sm" style="min-width:280px">
                            <div class="d-flex justify-content-between small"><span class="text-muted">Total devuelto:</span><span id="cam_tot_dev" class="fw-semibold">0.00</span></div>
                            <div class="d-flex justify-content-between small"><span class="text-muted">Total entregado:</span><span id="cam_tot_ent" class="fw-semibold">0.00</span></div>
                            <hr class="my-1">
                            <div class="d-flex justify-content-between"><span class="fw-bold">Diferencia:</span><span id="cam_tot_dif" class="fw-bold text-primary">0.00</span></div>
                            <div class="small text-muted text-end" id="cam_dif_hint"></div>
                        </div>
                    </div>
                </form>
                </div><!-- /cam-tab-general -->

                <!-- Pestaña Asiento Contable (a costo) -->
                <div class="tab-pane fade" id="cam-tab-asiento" role="tabpanel">
                    <div class="alert alert-light border small d-flex align-items-center gap-2 mb-2 py-2">
                        <i class="bi bi-info-circle text-primary"></i>
                        <span>Asiento <strong>a costo</strong>: neto entre el reingreso de lo devuelto y la salida de lo entregado (<em>Inventario</em> contra <em>Costo de ventas</em>).</span>
                    </div>
                    <div class="border rounded-3 overflow-hidden bg-white shadow-sm">
                        <div class="table-responsive" style="max-height: 320px;">
                            <table class="table table-sm mb-0 text-nowrap">
                                <thead>
                                    <tr class="table-light border-bottom">
                                        <th class="ps-3 py-2 small fw-bold text-muted" style="width:45%;">Cuenta Contable</th>
                                        <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">Débito / Debe</th>
                                        <th class="py-2 small fw-bold text-muted text-end pe-3" style="width:20%;">Crédito / Haber</th>
                                        <th class="py-2 small fw-bold text-muted" style="width:15%;">Referencia</th>
                                    </tr>
                                </thead>
                                <tbody id="cam-tbody-asiento">
                                    <tr><td colspan="4" class="text-center py-4 text-muted">Guarde el cambio para generar el asiento (se calcula a costo).</td></tr>
                                </tbody>
                                <tfoot class="bg-light fw-bold border-top">
                                    <tr>
                                        <td class="text-end py-2">Totales:</td>
                                        <td class="text-end pe-3 py-2 text-primary" id="cam-asiento-total-debe">0.00</td>
                                        <td class="text-end pe-3 py-2 text-primary" id="cam-asiento-total-haber">0.00</td>
                                        <td class="py-2">
                                            <span id="cam-asiento-badge-cuadre" class="badge bg-secondary bg-opacity-10 text-secondary border px-2">—</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div><!-- /cam-tab-asiento -->
                </div><!-- /tabsCambioContent -->
            </div>

            <div class="modal-footer py-2 d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarCambio" onclick="camEliminar()">
                        <i class="bi bi-trash"></i> Eliminar
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnGuardarCambio" onclick="camGuardar()">
                        <i class="bi bi-save me-1"></i> Guardar cambio
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const RUTA = window.RUTA_MODULO_CAMBIO;
    const DEC_C = (window.EMPRESA_CONFIG && window.EMPRESA_CONFIG.decimales_cantidad) || 2;
    const DEC_P = (window.EMPRESA_CONFIG && window.EMPRESA_CONFIG.decimales_precio) || 2;
    let modal;
    let camClientesTimer = null, camDevTimer = null, camEntTimer = null;
    let camBodegas = [];   // cache de bodegas [{id,nombre}]

    function getModal() {
        if (!modal) modal = new bootstrap.Modal(document.getElementById('modalCambio'));
        return modal;
    }
    function num(v) { const n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function fmt(v, d) { return num(v).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d }); }
    function esc(s) { return String(s == null ? '' : s).replace(/"/g, '&quot;').replace(/</g, '&lt;'); }

    // ─── Reset / modo ─────────────────────────────────────────────────────────
    function resetForm() {
        document.getElementById('formCambio').reset();
        document.getElementById('cam_id').value = '';
        document.getElementById('cam_id_cliente').value = '';
        document.getElementById('cam_cliente_email').value = '';
        document.getElementById('cam_serie').value = '';
        document.getElementById('cam_id_punto_emision').value = '';
        vaciarDev(); vaciarEnt();
        document.getElementById('cam_dev_busqueda').value = '';
        document.getElementById('cam_ent_busqueda').value = '';
        camRecalcular();
    }
    function vaciarDev() {
        document.getElementById('cam_dev_body').innerHTML =
            '<tr class="cam-dev-empty"><td colspan="7" class="text-center text-muted py-3">Seleccione un cliente y busque el producto a devolver.</td></tr>';
        document.getElementById('cam_dev_info').textContent = '';
    }
    function vaciarEnt() {
        document.getElementById('cam_ent_body').innerHTML =
            '<tr class="cam-ent-empty"><td colspan="7" class="text-center text-muted py-3">Busque un producto del catálogo para entregar.</td></tr>';
        document.getElementById('cam_ent_info').textContent = '';
    }

    function setCamposEditables(editable) {
        ['cam_select_serie','cam_fecha_cambio','cam_cliente_busqueda','cam_motivo','cam_observaciones',
         'cam_dev_busqueda','cam_ent_busqueda'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = !editable;
        });
        // La búsqueda de devoluciones requiere además un cliente seleccionado.
        if (editable && !document.getElementById('cam_id_cliente').value) {
            document.getElementById('cam_dev_busqueda').disabled = true;
        }
        document.getElementById('btnGuardarCambio').classList.toggle('d-none', !editable);
    }

    async function cargarBodegas() {
        if (camBodegas.length) return;
        try {
            const res = await fetch(`${RUTA}/getBodegasAjax`);
            const data = await res.json();
            camBodegas = (data.ok && data.data) ? data.data : [];
        } catch (e) { camBodegas = []; }
    }
    function bodegaOptions(sel) {
        return camBodegas.map(b => `<option value="${b.id}" ${String(b.id) === String(sel) ? 'selected' : ''}>${esc(b.nombre)}</option>`).join('');
    }

    window.abrirModalCambioNuevo = async function () {
        resetForm();
        camResetTabs();
        await cargarBodegas();
        setCamposEditables(true);
        document.getElementById('tituloModalCambio').innerHTML = '<i class="bi bi-arrow-left-right me-1"></i> Nuevo Cambio de productos';
        document.getElementById('cam_estado_badge').textContent = 'Nuevo';
        document.getElementById('cam_estado_badge').className = 'badge bg-secondary bg-opacity-10 text-secondary ms-2';
        document.getElementById('btnEliminarCambio').classList.add('d-none');
        document.getElementById('cam_estado_wrapper').classList.add('d-none');
        document.getElementById('cam_fecha_cambio').value = new Date().toISOString().slice(0, 10);
        const selSerie = document.getElementById('cam_select_serie');
        if (selSerie.value) { selSerie.selectedIndex = 0; await camSerieChange(); }
        getModal().show();
    };

    window.abrirModalCambioVer = async function (rowEl) {
        const row = JSON.parse(rowEl.getAttribute('data-row'));
        const puedeActualizar = (<?= (!empty($perm['actualizar']) || !empty($perm['todo'])) ? 'true' : 'false' ?>);
        const editable = (row.estado === 'Borrador') && puedeActualizar;

        resetForm();
        camResetTabs();
        await cargarBodegas();
        setCamposEditables(editable);
        document.getElementById('cam_id').value = row.id;
        document.getElementById('tituloModalCambio').innerHTML = '<i class="bi bi-arrow-left-right me-1"></i> Cambio ' + (row.serie || '') + '-' + (row.secuencial || '');
        camPintarBadge(row.estado);

        const btnG = document.getElementById('btnGuardarCambio');
        btnG.innerHTML = '<i class="bi bi-save me-1"></i> ' + (editable ? 'Actualizar cambio' : 'Guardar cambio');

        const wrap = document.getElementById('cam_estado_wrapper');
        const selEstado = document.getElementById('cam_estado_selector');
        wrap.classList.remove('d-none');
        selEstado.value = row.estado;
        selEstado.dataset.prev = row.estado;
        selEstado.disabled = !puedeActualizar;

        const puedeEliminar = (<?= (!empty($perm['eliminar']) || !empty($perm['todo'])) ? 'true' : 'false' ?>);
        document.getElementById('btnEliminarCambio').classList.toggle('d-none', !puedeEliminar);

        getModal().show();
        await camCargarDetalle(row.id, editable);
    };

    function camPintarBadge(estado) {
        const badge = document.getElementById('cam_estado_badge');
        badge.textContent = estado;
        let cls = 'bg-secondary bg-opacity-10 text-secondary';
        if (estado === 'Emitida') cls = 'bg-success bg-opacity-10 text-success';
        else if (estado === 'Anulada') cls = 'bg-danger bg-opacity-10 text-danger';
        else if (estado === 'Borrador') cls = 'bg-warning bg-opacity-10 text-warning';
        badge.className = 'badge ms-2 ' + cls;
    }

    window.camCambiarEstado = async function (nuevo) {
        const sel = document.getElementById('cam_estado_selector');
        const id  = document.getElementById('cam_id').value;
        const prev = sel.dataset.prev || 'Emitida';
        if (!id || nuevo === prev) return;

        if (nuevo === 'Anulada' || (prev === 'Emitida' && nuevo === 'Borrador')) {
            const c = await Swal.fire({
                title: nuevo === 'Anulada' ? '¿Anular cambio?' : '¿Pasar a Borrador?',
                text: 'Se reversarán los movimientos de inventario de este cambio y se liberará el saldo.',
                icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, continuar', cancelButtonText: 'Cancelar'
            });
            if (!c.isConfirmed) { sel.value = prev; return; }
        }

        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('estado', nuevo);
            const res = await fetch(`${RUTA}/cambiarEstadoAjax`, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudo cambiar el estado.');
            sel.dataset.prev = nuevo;
            camPintarBadge(nuevo);
            if (typeof cargarGrid === 'function') cargarGrid();
            Swal.fire({ icon: 'success', title: 'Estado actualizado', timer: 1200, showConfirmButton: false });
        } catch (e) {
            sel.value = prev;
            Swal.fire('Error', e.message, 'error');
        }
    };

    // ─── Numeración ───────────────────────────────────────────────────────────
    window.camSerieChange = async function () {
        const sel = document.getElementById('cam_select_serie');
        const opt = sel.options[sel.selectedIndex];
        const idPunto = sel.value;
        if (!idPunto || !opt) {
            document.getElementById('cam_serie').value = '';
            document.getElementById('cam_id_punto_emision').value = '';
            document.getElementById('cam_secuencial').value = '';
            return;
        }
        const est = opt.dataset.codEst || '';
        const punto = opt.dataset.codPunto || '';
        document.getElementById('cam_serie').value = est + '-' + punto;
        document.getElementById('cam_id_punto_emision').value = idPunto;
        await camCargarSecuencial(idPunto);
    };
    async function camCargarSecuencial(idPunto) {
        if (!idPunto) return;
        const res = await fetch(`${RUTA}/getSecuencialAjax?id_punto_emision=${idPunto}`);
        const data = await res.json();
        if (!data.ok) {
            document.getElementById('cam_secuencial').value = '';
            Swal.fire('Atención', data.msg || 'No hay secuencial configurado.', 'warning');
            return;
        }
        const sec = data.formateado || String(data.secuencial || '').padStart(9, '0');
        document.getElementById('cam_secuencial').value = sec;
        document.getElementById('cam_secuencial').dataset.sec = data.secuencial || '';
    }

    // ─── Cliente ──────────────────────────────────────────────────────────────
    window.camBuscarClientes = function (q) {
        clearTimeout(camClientesTimer);
        const dd = document.getElementById('cam_clientes_dropdown');
        if (!q || q.length < 2) { dd.classList.add('d-none'); return; }
        camClientesTimer = setTimeout(async () => {
            const res = await fetch(`${RUTA}/buscarClientesAjax?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            dd.innerHTML = '';
            (data.data || []).forEach(c => {
                const a = document.createElement('a');
                a.href = '#'; a.className = 'list-group-item list-group-item-action py-1';
                a.innerHTML = `<span class="small text-dark">${esc(c.nombre)}</span>
                               <span class="small text-muted ms-1">${c.identificacion ? '· ' + esc(c.identificacion) : ''}</span>`;
                a.onclick = (ev) => { ev.preventDefault(); camSeleccionarCliente(c); };
                dd.appendChild(a);
            });
            if (!data.data || !data.data.length) {
                dd.innerHTML = '<span class="list-group-item small text-muted">Sin resultados.</span>';
            }
            dd.classList.remove('d-none');
        }, 300);
    };
    function camSeleccionarCliente(c) {
        document.getElementById('cam_id_cliente').value = c.id;
        document.getElementById('cam_cliente_email').value = c.email || '';
        document.getElementById('cam_cliente_busqueda').value = (c.identificacion || '') + ' — ' + (c.nombre || '');
        document.getElementById('cam_clientes_dropdown').classList.add('d-none');
        // Al cambiar de cliente, se limpian las devoluciones (dependen del cliente).
        vaciarDev();
        document.getElementById('cam_dev_busqueda').disabled = false;
        camRecalcular();
    }

    document.addEventListener('click', (e) => {
        [['cam_cliente_busqueda','cam_clientes_dropdown'],
         ['cam_dev_busqueda','cam_dev_dropdown'],
         ['cam_ent_busqueda','cam_ent_dropdown']].forEach(([inp, dd]) => {
            const el = document.getElementById(dd);
            if (el && !e.target.closest('#' + inp) && !e.target.closest('#' + dd)) el.classList.add('d-none');
        });
    });

    // ─── Devoluciones (buscar líneas de origen y agregar) ─────────────────────
    window.camBuscarLineas = function (q) {
        clearTimeout(camDevTimer);
        const dd = document.getElementById('cam_dev_dropdown');
        const idCliente = document.getElementById('cam_id_cliente').value;
        if (!idCliente) { dd.classList.add('d-none'); return; }
        if (!q || q.length < 2) { dd.classList.add('d-none'); return; }
        camDevTimer = setTimeout(async () => {
            const excl = document.getElementById('cam_id').value || 0;
            const res = await fetch(`${RUTA}/buscarLineasOrigenAjax?id_cliente=${idCliente}&excluir=${excl}&q=${encodeURIComponent(q)}`);
            const data = await res.json();
            dd.innerHTML = '';
            (data.data || []).forEach(l => {
                const ori = (l.origen_tipo === 'CAMBIO' ? 'Cambio' : 'Factura') + ' ' + (l.doc_numero || '');
                const a = document.createElement('a');
                a.href = '#'; a.className = 'list-group-item list-group-item-action py-1';
                a.innerHTML = `<span class="badge ${l.origen_tipo === 'CAMBIO' ? 'bg-info' : 'bg-secondary'} bg-opacity-25 text-dark small me-1">${esc(ori)}</span>
                               <span class="small fw-semibold">${esc(l.producto_codigo ? l.producto_codigo + ' · ' : '')}${esc(l.producto_nombre)}</span>
                               <span class="small text-success ms-1">Saldo: ${fmt(l.saldo_pendiente, DEC_C)}</span>`;
                a.onclick = (ev) => { ev.preventDefault(); camAgregarDevolucion(l); dd.classList.add('d-none'); document.getElementById('cam_dev_busqueda').value=''; };
                dd.appendChild(a);
            });
            if (!data.data || !data.data.length) {
                dd.innerHTML = '<span class="list-group-item small text-muted">Sin líneas con saldo pendiente.</span>';
            }
            dd.classList.remove('d-none');
        }, 300);
    };

    function camAgregarDevolucion(l) {
        const key = l.origen_tipo + '-' + l.id_origen_detalle;
        if (document.querySelector(`#cam_dev_body tr[data-key="${key}"]`)) {
            Swal.fire({ icon: 'info', title: 'Ya agregada', text: 'Esa línea ya está en la lista.', timer: 1200, showConfirmButton: false, target: document.getElementById('modalCambio') });
            return;
        }
        const empty = document.querySelector('#cam_dev_body .cam-dev-empty');
        if (empty) empty.parentElement.removeChild(empty);

        const saldo = num(l.saldo_pendiente);
        const ori = (l.origen_tipo === 'CAMBIO' ? 'Cambio' : 'Factura') + ' ' + (l.doc_numero || '');
        const loteNup = [l.lote, l.nup].filter(Boolean).join(' / ') || '—';
        const tr = document.createElement('tr');
        tr.setAttribute('data-key', key);
        tr.dataset.origenTipo = l.origen_tipo;
        tr.dataset.idOrigenDetalle = l.id_origen_detalle;
        tr.dataset.saldo = saldo;
        tr.dataset.precio = num(l.precio_unitario);
        tr.dataset.porc = num(l.porcentaje_impuesto);
        tr.innerHTML = `
            <td class="small"><span class="badge ${l.origen_tipo === 'CAMBIO' ? 'bg-info' : 'bg-secondary'} bg-opacity-25 text-dark">${esc(ori)}</span></td>
            <td class="small">${esc(l.producto_codigo ? l.producto_codigo + ' · ' : '')}${esc(l.producto_nombre)}</td>
            <td class="small">${esc(loteNup)}</td>
            <td class="text-end small">${fmt(saldo, DEC_C)}</td>
            <td class="p-0"><input type="number" class="form-control form-control-sm text-end cam-dev-cant" min="0" max="${saldo}" step="any" value="${saldo}" oninput="camOnCantDev(this)" style="height:26px;font-size:.8rem;"></td>
            <td class="text-end small cam-dev-total">0.00</td>
            <td class="text-center p-0"><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="camQuitarFila(this,'dev')" title="Quitar"><i class="bi bi-x-lg"></i></button></td>`;
        document.getElementById('cam_dev_body').appendChild(tr);
        camOnCantDev(tr.querySelector('.cam-dev-cant'));
    }

    window.camOnCantDev = function (inp) {
        const tr = inp.closest('tr');
        const saldo = num(tr.dataset.saldo);
        let c = num(inp.value);
        if (c < 0) c = 0;
        if (c > saldo) { c = saldo; inp.value = saldo; }
        const precio = num(tr.dataset.precio), porc = num(tr.dataset.porc);
        const total = c * precio * (1 + porc / 100);
        tr.querySelector('.cam-dev-total').textContent = fmt(total, 2);
        camRecalcular();
    };

    // ─── Entregas (buscar producto de catálogo y agregar) ─────────────────────
    window.camBuscarProductos = function (q) {
        clearTimeout(camEntTimer);
        const dd = document.getElementById('cam_ent_dropdown');
        if (!q || q.length < 2) { dd.classList.add('d-none'); return; }
        camEntTimer = setTimeout(async () => {
            const res = await fetch(`${RUTA}/buscarProductosAjax?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            dd.innerHTML = '';
            (data.data || []).forEach(p => {
                const a = document.createElement('a');
                a.href = '#'; a.className = 'list-group-item list-group-item-action py-1';
                a.innerHTML = `<span class="small fw-semibold">${esc(p.codigo ? p.codigo + ' · ' : '')}${esc(p.nombre)}</span>`;
                a.onclick = (ev) => { ev.preventDefault(); camAgregarEntrega(p); dd.classList.add('d-none'); document.getElementById('cam_ent_busqueda').value=''; };
                dd.appendChild(a);
            });
            if (!data.data || !data.data.length) {
                dd.innerHTML = '<span class="list-group-item small text-muted">Sin resultados.</span>';
            }
            dd.classList.remove('d-none');
        }, 300);
    };

    async function camAgregarEntrega(p) {
        if (document.querySelector(`#cam_ent_body tr[data-prod="${p.id}"]`)) {
            Swal.fire({ icon: 'info', title: 'Ya agregado', text: 'Ese producto ya está en la lista.', timer: 1200, showConfirmButton: false, target: document.getElementById('modalCambio') });
            return;
        }
        const empty = document.querySelector('#cam_ent_body .cam-ent-empty');
        if (empty) empty.parentElement.removeChild(empty);

        // Precios de lista
        let precio = 0;
        try {
            const res = await fetch(`${RUTA}/getPreciosAjax?id_producto=${p.id}`);
            const data = await res.json();
            const precios = (data.ok && data.data) ? data.data : [];
            if (precios.length) precio = num(precios[0].precio);
        } catch (e) {}

        const tr = document.createElement('tr');
        tr.setAttribute('data-prod', p.id);
        tr.innerHTML = `
            <td class="small">${esc(p.codigo ? p.codigo + ' · ' : '')}${esc(p.nombre)}</td>
            <td class="p-0"><select class="form-select form-select-sm cam-ent-bodega" style="height:26px;font-size:.78rem;">${bodegaOptions('')}</select></td>
            <td class="p-0"><input type="number" class="form-control form-control-sm text-end cam-ent-precio" min="0" step="any" value="${precio}" oninput="camOnEnt(this)" style="height:26px;font-size:.8rem;"></td>
            <td class="p-0"><input type="number" class="form-control form-control-sm text-end cam-ent-iva" min="0" step="any" value="0" oninput="camOnEnt(this)" style="height:26px;font-size:.8rem;"></td>
            <td class="p-0"><input type="number" class="form-control form-control-sm text-end cam-ent-cant" min="0" step="any" value="1" oninput="camOnEnt(this)" style="height:26px;font-size:.8rem;"></td>
            <td class="text-end small cam-ent-total">0.00</td>
            <td class="text-center p-0"><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="camQuitarFila(this,'ent')" title="Quitar"><i class="bi bi-x-lg"></i></button></td>`;
        document.getElementById('cam_ent_body').appendChild(tr);
        camOnEnt(tr.querySelector('.cam-ent-cant'));
    }

    window.camOnEnt = function (inp) {
        const tr = inp.closest('tr');
        const precio = num(tr.querySelector('.cam-ent-precio').value);
        const iva = num(tr.querySelector('.cam-ent-iva').value);
        const cant = num(tr.querySelector('.cam-ent-cant').value);
        const total = cant * precio * (1 + iva / 100);
        tr.querySelector('.cam-ent-total').textContent = fmt(total, 2);
        camRecalcular();
    };

    window.camQuitarFila = function (btn, tipo) {
        const tr = btn.closest('tr');
        tr.parentElement.removeChild(tr);
        if (tipo === 'dev' && !document.querySelector('#cam_dev_body tr')) vaciarDev();
        if (tipo === 'ent' && !document.querySelector('#cam_ent_body tr')) vaciarEnt();
        camRecalcular();
    };

    function camRecalcular() {
        // Suma las celdas de total (se mantienen actualizadas en edición y fijas en modo ver).
        const cellNum = (el) => el ? num(String(el.textContent).replace(/,/g, '')) : 0;
        let totDev = 0, totEnt = 0, nDev = 0, nEnt = 0;
        document.querySelectorAll('#cam_dev_body tr[data-key]').forEach(tr => {
            totDev += cellNum(tr.querySelector('.cam-dev-total')); nDev++;
        });
        document.querySelectorAll('#cam_ent_body tr[data-prod]').forEach(tr => {
            totEnt += cellNum(tr.querySelector('.cam-ent-total')); nEnt++;
        });
        document.getElementById('cam_tot_dev').textContent = fmt(totDev, 2);
        document.getElementById('cam_tot_ent').textContent = fmt(totEnt, 2);
        const dif = totEnt - totDev;
        document.getElementById('cam_tot_dif').textContent = fmt(dif, 2);
        const hint = document.getElementById('cam_dif_hint');
        hint.textContent = dif > 0.005 ? 'A favor de la empresa' : (dif < -0.005 ? 'A favor del cliente' : '');
        document.getElementById('cam_dev_info').textContent = nDev ? nDev + ' línea(s)' : '';
        document.getElementById('cam_ent_info').textContent = nEnt ? nEnt + ' línea(s)' : '';
    }

    // ─── Ver / editar detalle existente ───────────────────────────────────────
    async function camCargarDetalle(id, editable) {
        const res = await fetch(`${RUTA}/getDetalleAjax?id=${id}`);
        const data = await res.json();
        if (!data.ok) { Swal.fire('Error', data.error || 'No se pudo cargar.', 'error'); return; }
        const r = data.data;

        document.getElementById('cam_serie').value = r.serie || '';
        const selSerie = document.getElementById('cam_select_serie');
        if (r.id_punto_emision && selSerie.querySelector(`option[value="${r.id_punto_emision}"]`)) selSerie.value = r.id_punto_emision;
        selSerie.disabled = true;
        document.getElementById('cam_id_punto_emision').value = r.id_punto_emision || '';
        document.getElementById('cam_secuencial').value = r.secuencial || '';
        document.getElementById('cam_secuencial').dataset.sec = r.secuencial || '';
        if (r.fecha_cambio) document.getElementById('cam_fecha_cambio').value = String(r.fecha_cambio).slice(0, 10);

        document.getElementById('cam_id_cliente').value = r.id_cliente || '';
        document.getElementById('cam_cliente_email').value = r.cliente_email || '';
        document.getElementById('cam_cliente_busqueda').value = (r.cliente_identificacion || '') + ' — ' + (r.cliente_nombre || '');
        document.getElementById('cam_motivo').value = r.motivo || '';
        document.getElementById('cam_observaciones').value = r.observaciones || '';

        vaciarDev(); vaciarEnt();
        const dets = r.detalles || [];
        const devs = dets.filter(d => d.tipo_linea === 'devolucion');
        const ents = dets.filter(d => d.tipo_linea === 'entrega');

        if (devs.length) {
            document.querySelector('#cam_dev_body .cam-dev-empty')?.remove();
            devs.forEach(d => camPintarDevExistente(d, editable));
        }
        if (ents.length) {
            document.querySelector('#cam_ent_body .cam-ent-empty')?.remove();
            ents.forEach(d => camPintarEntExistente(d, editable));
        }
        if (editable) document.getElementById('cam_dev_busqueda').disabled = false;
        camRecalcular();
    }

    function camPintarDevExistente(d, editable) {
        const ori = (d.origen_tipo === 'CAMBIO' ? 'Cambio' : 'Factura');
        const loteNup = [d.lote, d.nup].filter(Boolean).join(' / ') || '—';
        const saldoRef = num(d.cantidad); // en edición el máximo real se revalida en el server
        const tr = document.createElement('tr');
        tr.setAttribute('data-key', d.origen_tipo + '-' + d.id_origen_detalle);
        tr.dataset.origenTipo = d.origen_tipo;
        tr.dataset.idOrigenDetalle = d.id_origen_detalle;
        tr.dataset.saldo = editable ? 1e12 : saldoRef;
        tr.dataset.precio = num(d.precio_unitario);
        tr.dataset.porc = num(d.porcentaje_impuesto);
        const cantCell = editable
            ? `<input type="number" class="form-control form-control-sm text-end cam-dev-cant" min="0" step="any" value="${num(d.cantidad)}" oninput="camOnCantDev(this)" style="height:26px;font-size:.8rem;">`
            : `<span class="cam-dev-cant-ro">${fmt(d.cantidad, DEC_C)}</span>`;
        tr.innerHTML = `
            <td class="small"><span class="badge ${d.origen_tipo === 'CAMBIO' ? 'bg-info' : 'bg-secondary'} bg-opacity-25 text-dark">${esc(ori)}</span></td>
            <td class="small">${esc(d.producto_codigo ? d.producto_codigo + ' · ' : '')}${esc(d.producto_nombre)}</td>
            <td class="small">${esc(loteNup)}</td>
            <td class="text-end small">${editable ? '—' : fmt(d.cantidad, DEC_C)}</td>
            <td class="p-0 text-end">${cantCell}</td>
            <td class="text-end small cam-dev-total">0.00</td>
            <td class="text-center p-0">${editable ? `<button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="camQuitarFila(this,'dev')"><i class="bi bi-x-lg"></i></button>` : ''}</td>`;
        document.getElementById('cam_dev_body').appendChild(tr);
        if (editable) camOnCantDev(tr.querySelector('.cam-dev-cant'));
        else tr.querySelector('.cam-dev-total').textContent = fmt(num(d.cantidad) * num(d.precio_unitario) * (1 + num(d.porcentaje_impuesto) / 100), 2);
    }

    function camPintarEntExistente(d, editable) {
        const tr = document.createElement('tr');
        tr.setAttribute('data-prod', d.id_producto);
        const total = num(d.cantidad) * num(d.precio_unitario) * (1 + num(d.porcentaje_impuesto) / 100);
        if (editable) {
            tr.innerHTML = `
                <td class="small">${esc(d.producto_codigo ? d.producto_codigo + ' · ' : '')}${esc(d.producto_nombre)}</td>
                <td class="p-0"><select class="form-select form-select-sm cam-ent-bodega" style="height:26px;font-size:.78rem;">${bodegaOptions(d.id_bodega)}</select></td>
                <td class="p-0"><input type="number" class="form-control form-control-sm text-end cam-ent-precio" min="0" step="any" value="${num(d.precio_unitario)}" oninput="camOnEnt(this)" style="height:26px;font-size:.8rem;"></td>
                <td class="p-0"><input type="number" class="form-control form-control-sm text-end cam-ent-iva" min="0" step="any" value="${num(d.porcentaje_impuesto)}" oninput="camOnEnt(this)" style="height:26px;font-size:.8rem;"></td>
                <td class="p-0"><input type="number" class="form-control form-control-sm text-end cam-ent-cant" min="0" step="any" value="${num(d.cantidad)}" oninput="camOnEnt(this)" style="height:26px;font-size:.8rem;"></td>
                <td class="text-end small cam-ent-total">0.00</td>
                <td class="text-center p-0"><button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="camQuitarFila(this,'ent')"><i class="bi bi-x-lg"></i></button></td>`;
            document.getElementById('cam_ent_body').appendChild(tr);
            camOnEnt(tr.querySelector('.cam-ent-cant'));
        } else {
            const bod = (camBodegas.find(b => String(b.id) === String(d.id_bodega)) || {}).nombre || (d.bodega_nombre || '—');
            tr.innerHTML = `
                <td class="small">${esc(d.producto_codigo ? d.producto_codigo + ' · ' : '')}${esc(d.producto_nombre)}</td>
                <td class="small">${esc(bod)}</td>
                <td class="text-end small">${fmt(d.precio_unitario, DEC_P)}</td>
                <td class="text-end small">${fmt(d.porcentaje_impuesto, 2)}</td>
                <td class="text-end small">${fmt(d.cantidad, DEC_C)}</td>
                <td class="text-end small cam-ent-total">${fmt(total, 2)}</td>
                <td></td>`;
            document.getElementById('cam_ent_body').appendChild(tr);
        }
    }

    // ─── Guardar ──────────────────────────────────────────────────────────────
    window.camGuardar = async function () {
        const idCliente = document.getElementById('cam_id_cliente').value;
        if (!idCliente) { Swal.fire('Atención', 'Seleccione un cliente.', 'warning'); return; }
        if (!document.getElementById('cam_secuencial').value) { Swal.fire('Atención', 'Falta el secuencial. Configure el punto de emisión.', 'warning'); return; }

        const devoluciones = [];
        document.querySelectorAll('#cam_dev_body tr[data-key]').forEach(tr => {
            const inp = tr.querySelector('.cam-dev-cant');
            const c = inp ? num(inp.value) : num(tr.querySelector('.cam-dev-cant-ro')?.textContent);
            if (c > 0) devoluciones.push({
                origen_tipo: tr.dataset.origenTipo,
                id_origen_detalle: parseInt(tr.dataset.idOrigenDetalle, 10),
                cantidad: c
            });
        });
        if (!devoluciones.length) { Swal.fire('Atención', 'Agregue al menos un producto a devolver.', 'warning'); return; }

        const entregas = [];
        document.querySelectorAll('#cam_ent_body tr[data-prod]').forEach(tr => {
            const cInp = tr.querySelector('.cam-ent-cant');
            if (!cInp) return; // fila de solo lectura (modo ver)
            const c = num(cInp.value);
            if (c > 0) entregas.push({
                id_producto: parseInt(tr.dataset.prod, 10),
                cantidad: c,
                precio_unitario: num(tr.querySelector('.cam-ent-precio').value),
                porcentaje_impuesto: num(tr.querySelector('.cam-ent-iva').value),
                id_bodega: parseInt(tr.querySelector('.cam-ent-bodega').value || 0, 10)
            });
        });

        const idCambio = document.getElementById('cam_id').value;
        const payload = {
            id: idCambio || null,
            id_cliente: parseInt(idCliente, 10),
            fecha_cambio: document.getElementById('cam_fecha_cambio').value,
            serie: document.getElementById('cam_serie').value,
            secuencial: document.getElementById('cam_secuencial').dataset.sec || '',
            id_punto_emision: document.getElementById('cam_id_punto_emision').value,
            establecimiento: (document.getElementById('cam_serie').value.split('-')[0] || ''),
            punto_emision: (document.getElementById('cam_serie').value.split('-')[1] || ''),
            motivo: document.getElementById('cam_motivo').value,
            observaciones: document.getElementById('cam_observaciones').value,
            devoluciones,
            entregas
        };

        const btn = document.getElementById('btnGuardarCambio');
        const labelOrig = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        try {
            const res = await fetch(`${RUTA}/store`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Error al guardar');
            getModal().hide();
            await Swal.fire('Listo', data.msg, 'success');
            if (typeof cargarGrid === 'function') cargarGrid();
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
        } finally {
            btn.disabled = false; btn.innerHTML = labelOrig;
        }
    };

    // ─── Eliminar ─────────────────────────────────────────────────────────────
    window.camEliminar = async function () {
        const id = document.getElementById('cam_id').value;
        if (!id) return;
        const c = await Swal.fire({
            title: '¿Eliminar cambio?', text: 'Se reversarán los movimientos de inventario de este cambio.',
            icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
        });
        if (!c.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id', id);
            const res = await fetch(`${RUTA}/eliminar`, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Error');
            getModal().hide();
            await Swal.fire('Eliminado', data.msg, 'success');
            if (typeof cargarGrid === 'function') cargarGrid();
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
        }
    };

    // ─── Acciones rápidas ─────────────────────────────────────────────────────
    window.camPdf = function () {
        const id = document.getElementById('cam_id').value;
        if (!id) return Swal.fire('Atención', 'Debe guardar el cambio primero.', 'warning');
        const a = document.createElement('a');
        a.href = `${RUTA}/pdf?id=${id}`; a.download = '';
        document.body.appendChild(a); a.click(); a.remove();
    };
    window.camEmail = async function () {
        const id = document.getElementById('cam_id').value;
        if (!id) return Swal.fire('Atención', 'Debe guardar el cambio primero.', 'warning');
        const { value: correos, isConfirmed } = await Swal.fire({
            title: 'Enviar por correo', input: 'text',
            inputLabel: 'Correo(s) destino, separados por coma.',
            inputValue: document.getElementById('cam_cliente_email').value || '',
            inputPlaceholder: 'cliente@correo.com',
            target: document.getElementById('modalCambio'),
            showCancelButton: true, confirmButtonText: '<i class="bi bi-envelope me-1"></i> Enviar', cancelButtonText: 'Cancelar'
        });
        if (!isConfirmed) return;
        Swal.fire({ title: 'Enviando correo...', allowOutsideClick: false, target: document.getElementById('modalCambio'), didOpen: () => Swal.showLoading() });
        try {
            const fd = new FormData(); fd.append('id', id); fd.append('correos', correos || '');
            const res = await fetch(`${RUTA}/enviarCorreoAjax`, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) Swal.fire('Enviado', data.mensaje || 'Correo enviado correctamente.', 'success');
            else Swal.fire('Error', data.mensaje || 'No se pudo enviar el correo.', 'error');
        } catch (e) {
            Swal.fire('Error', 'No se pudo enviar el correo.', 'error');
        }
    };
    window.camWhatsapp = function () {
        if (!document.getElementById('cam_id').value) return Swal.fire('Atención', 'Debe guardar el cambio primero.', 'warning');
        Swal.fire('Info', 'Enviando por WhatsApp...', 'info');
    };

    // ─── Pestaña Asiento ──────────────────────────────────────────────────────
    function camResetTabs() {
        try { new bootstrap.Tab(document.getElementById('cam-tab-general-btn')).show(); } catch (e) {}
        document.getElementById('cam-tbody-asiento').innerHTML =
            '<tr><td colspan="4" class="text-center py-4 text-muted">Guarde el cambio para generar el asiento (se calcula a costo).</td></tr>';
        document.getElementById('cam-asiento-total-debe').textContent = '0.00';
        document.getElementById('cam-asiento-total-haber').textContent = '0.00';
        const badge = document.getElementById('cam-asiento-badge-cuadre');
        badge.textContent = '—'; badge.className = 'badge bg-secondary bg-opacity-10 text-secondary border px-2';
    }
    async function camCargarAsiento() {
        const id = document.getElementById('cam_id').value;
        const tbody = document.getElementById('cam-tbody-asiento');
        if (!id) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-info-circle me-1"></i> Guarde el cambio para generar el asiento (se calcula a costo).</td></tr>';
            return;
        }
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Cargando asiento contable...</td></tr>';
        try {
            const res = await fetch(`${RUTA}/getAsientoSugeridoAjax?id=${id}`);
            const data = await res.json();
            const dets = (data.ok && data.detalles) ? data.detalles : [];
            if (!dets.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-info-circle me-1"></i> Sin asiento: el neto de costos es cero o el cambio no está Emitido.</td></tr>';
                document.getElementById('cam-asiento-total-debe').textContent = '0.00';
                document.getElementById('cam-asiento-total-haber').textContent = '0.00';
                const badge = document.getElementById('cam-asiento-badge-cuadre');
                badge.textContent = '—'; badge.className = 'badge bg-secondary bg-opacity-10 text-secondary border px-2';
                return;
            }
            let tDebe = 0, tHaber = 0;
            tbody.innerHTML = dets.map(d => {
                tDebe += num(d.debe); tHaber += num(d.haber);
                const cuenta = (d.id_cuenta_contable > 0)
                    ? `${d.cuenta_codigo ? d.cuenta_codigo + ' · ' : ''}${d.cuenta_nombre || ''}`
                    : '<span class="text-danger">— Cuenta sin configurar —</span>';
                return `<tr>
                    <td class="ps-3 small">${cuenta}</td>
                    <td class="text-end pe-3 small">${num(d.debe) ? fmt(d.debe, 2) : ''}</td>
                    <td class="text-end pe-3 small">${num(d.haber) ? fmt(d.haber, 2) : ''}</td>
                    <td class="small text-muted">${esc(d.referencia_detalle || '')}</td>
                </tr>`;
            }).join('');
            document.getElementById('cam-asiento-total-debe').textContent = fmt(tDebe, 2);
            document.getElementById('cam-asiento-total-haber').textContent = fmt(tHaber, 2);
            const cuadrado = Math.abs(tDebe - tHaber) < 0.005;
            const badge = document.getElementById('cam-asiento-badge-cuadre');
            badge.textContent = cuadrado ? 'Cuadrado' : 'Descuadrado';
            badge.className = 'badge px-2 ' + (cuadrado ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25');
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Error al cargar el asiento contable.</td></tr>';
        }
    }
    document.getElementById('cam-tab-asiento-btn').addEventListener('shown.bs.tab', camCargarAsiento);
    window.__camResetTabs = camResetTabs;
})();
</script>
