<?php
/** @var array $responsables */
/** @var array $perm */
/** @var array $puntos */
?>
<div class="modal fade" id="modalRetorno" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="tituloModalRetorno">
                    <i class="bi bi-arrow-return-left me-1"></i> Nuevo Retorno de Consignación
                </h5>
                <span id="ret_estado_badge" class="badge bg-secondary bg-opacity-10 text-secondary ms-2 d-none">Nuevo</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <!-- Barra de acciones superior (estándar del sistema: PDF / Correo / WhatsApp) -->
                <div class="d-flex gap-1 align-items-center flex-wrap mb-3 pb-2 border-bottom">
                    <button type="button" class="btn btn-outline-danger btn-sm px-2" onclick="retPdf()" title="Exportar PDF"><i class="bi bi-file-earmark-pdf"></i></button>
                    <button type="button" class="btn btn-outline-info btn-sm px-2" onclick="retEmail()" title="Enviar por correo"><i class="bi bi-envelope"></i></button>
                    <button type="button" class="btn btn-outline-success btn-sm px-2" onclick="retWhatsapp()" title="Enviar por WhatsApp"><i class="bi bi-whatsapp"></i></button>
                </div>

                <ul class="nav nav-tabs mb-3" id="tabsRetorno" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="ret-tab-general-btn" data-bs-toggle="tab" href="#ret-tab-general" role="tab"><i class="bi bi-info-circle me-1"></i> General</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="ret-tab-asiento-btn" data-bs-toggle="tab" href="#ret-tab-asiento" role="tab"><i class="bi bi-calculator me-1"></i> Asiento contable</a>
                    </li>
                </ul>
                <div class="tab-content" id="tabsRetornoContent">
                <div class="tab-pane fade show active" id="ret-tab-general" role="tabpanel">
                <form id="formRetorno" autocomplete="off">
                    <input type="hidden" id="ret_id">

                    <!-- Numeración -->
                    <div class="row g-2 mb-2">
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Fecha retorno</label>
                            <input type="date" id="ret_fecha_retorno" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Serie</label>
                            <select id="ret_select_serie" class="form-select form-select-sm" onchange="retSerieChange()">
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
                            <input type="hidden" id="ret_serie">
                            <input type="hidden" id="ret_id_punto_emision">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Secuencial</label>
                            <input type="text" id="ret_secuencial" class="form-control form-control-sm bg-light text-center" readonly placeholder="000000000">
                        </div>
                        <div class="col-md-6 position-relative">
                            <label class="form-label small mb-1">Cliente</label>
                            <input type="text" id="ret_cliente_busqueda" class="form-control form-control-sm" placeholder="Buscar por cliente o N° de consignación..." oninput="retBuscarConsignaciones(this.value)" autocomplete="off">
                            <input type="hidden" id="ret_id_cliente">
                            <input type="hidden" id="ret_cliente_email">
                            <div id="ret_clientes_dropdown" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:1080; max-height:240px; overflow:auto;"></div>
                        </div>
                    </div>

                    <!-- Motivo, Observaciones y Estado -->
                    <div class="row g-2 mb-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Motivo</label>
                            <input type="text" id="ret_motivo" class="form-control form-control-sm" placeholder="Motivo del retorno (opcional)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Observaciones</label>
                            <input type="text" id="ret_observaciones" class="form-control form-control-sm" placeholder="Observaciones (opcional)">
                        </div>
                        <div class="col-md-2 d-none" id="ret_estado_wrapper">
                            <label class="form-label small mb-1">Estado</label>
                            <select id="ret_estado_selector" class="form-select form-select-sm fw-bold" onchange="retCambiarEstado(this.value)">
                                <option value="Borrador">Borrador</option>
                                <option value="Emitida">Emitida</option>
                                <option value="Anulada">Anulada</option>
                            </select>
                        </div>
                    </div>

                    <!-- Grilla de líneas pendientes de retornar -->
                    <div class="d-flex justify-content-between align-items-center mt-2 mb-1">
                        <h6 class="mb-0 fw-bold text-secondary"><i class="bi bi-box-seam me-1"></i> Productos a retornar</h6>
                        <span id="ret_lineas_info" class="small text-muted"></span>
                    </div>
                    <div class="table-responsive border rounded-3" style="max-height:40vh; overflow:auto;">
                        <table class="table table-sm table-hover mb-0 align-middle" id="tablaRetLineas">
                            <thead class="table-light">
                                <tr class="small">
                                    <th style="width:34px" class="text-center">
                                        <input type="checkbox" id="ret_check_all" onchange="retToggleTodos(this.checked)" title="Seleccionar todo">
                                    </th>
                                    <th>Consignación</th>
                                    <th>Producto</th>
                                    <th>Lote / NUP</th>
                                    <th>Bodega</th>
                                    <th class="text-end">Saldo</th>
                                    <th class="text-end" style="width:120px">Cant. a retornar</th>
                                </tr>
                            </thead>
                            <tbody id="ret_lineas_body">
                                <tr><td colspan="7" class="text-center text-muted py-4">Seleccione un cliente para ver sus consignaciones pendientes.</td></tr>
                            </tbody>
                        </table>
                    </div>

                </form>
                </div><!-- /ret-tab-general -->

                <!-- Pestaña Asiento Contable (inverso a la consignación) -->
                <div class="tab-pane fade" id="ret-tab-asiento" role="tabpanel">
                    <div class="alert alert-light border small d-flex align-items-center gap-2 mb-2 py-2">
                        <i class="bi bi-info-circle text-primary"></i>
                        <span>Reclasificación <strong>a costo</strong>, inversa a la consignación: <em>Inventario</em> contra <em>Mercadería en consignación</em> (la mercadería vuelve del poder de terceros).</span>
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
                                <tbody id="ret-tbody-asiento">
                                    <tr><td colspan="4" class="text-center py-4 text-muted">Guarde el retorno para generar el asiento (se calcula a costo).</td></tr>
                                </tbody>
                                <tfoot class="bg-light fw-bold border-top">
                                    <tr>
                                        <td class="text-end py-2">Totales:</td>
                                        <td class="text-end pe-3 py-2 text-primary" id="ret-asiento-total-debe">0.00</td>
                                        <td class="text-end pe-3 py-2 text-primary" id="ret-asiento-total-haber">0.00</td>
                                        <td class="py-2">
                                            <span id="ret-asiento-badge-cuadre" class="badge bg-secondary bg-opacity-10 text-secondary border px-2">—</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div><!-- /ret-tab-asiento -->
                </div><!-- /tabsRetornoContent -->
            </div>

            <div class="modal-footer py-2 d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btnEliminarRetorno" onclick="retEliminar()">
                        <i class="bi bi-trash"></i> Eliminar
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnGuardarRetorno" onclick="retGuardar()">
                        <i class="bi bi-save me-1"></i> Guardar retorno
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const RUTA = window.RUTA_MODULO_RETORNO;
    const DEC_C = (window.EMPRESA_CONFIG && window.EMPRESA_CONFIG.decimales_cantidad) || 2;
    const DEC_P = (window.EMPRESA_CONFIG && window.EMPRESA_CONFIG.decimales_precio) || 2;
    let modal;
    let retClientesTimer = null;

    function getModal() {
        if (!modal) modal = new bootstrap.Modal(document.getElementById('modalRetorno'));
        return modal;
    }

    function num(v) { const n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function fmt(v, d) { return num(v).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d }); }

    // ─── Modo edición / vista ────────────────────────────────────────────────
    function resetForm() {
        document.getElementById('formRetorno').reset();
        document.getElementById('ret_id').value = '';
        document.getElementById('ret_id_cliente').value = '';
        document.getElementById('ret_cliente_email').value = '';
        document.getElementById('ret_serie').value = '';
        document.getElementById('ret_id_punto_emision').value = '';
        document.getElementById('ret_lineas_body').innerHTML =
            '<tr><td colspan="7" class="text-center text-muted py-4">Seleccione un cliente para ver sus consignaciones pendientes.</td></tr>';
        document.getElementById('ret_lineas_info').textContent = '';
        document.getElementById('ret_check_all').checked = false;
        retRecalcular();
    }

    function setCamposEditables(editable) {
        ['ret_select_serie','ret_fecha_retorno',
         'ret_cliente_busqueda','ret_motivo','ret_observaciones','ret_check_all'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = !editable;
        });
        document.getElementById('btnGuardarRetorno').classList.toggle('d-none', !editable);
    }

    window.abrirModalRetornoNuevo = async function () {
        resetForm();
        retResetTabs();
        setCamposEditables(true);
        document.getElementById('tituloModalRetorno').innerHTML = '<i class="bi bi-arrow-return-left me-1"></i> Nuevo Retorno de Consignación';
        document.getElementById('ret_estado_badge').textContent = 'Nuevo';
        document.getElementById('ret_estado_badge').className = 'badge bg-secondary bg-opacity-10 text-secondary ms-2';
        document.getElementById('btnEliminarRetorno').classList.add('d-none');
        document.getElementById('ret_estado_wrapper').classList.add('d-none'); // sin estado hasta crear
        document.getElementById('ret_fecha_retorno').value = new Date().toISOString().slice(0, 10);
        const selSerie = document.getElementById('ret_select_serie');
        if (selSerie.value) { selSerie.selectedIndex = 0; await retSerieChange(); }
        getModal().show();
    };

    window.abrirModalRetornoVer = async function (rowEl) {
        const row = JSON.parse(rowEl.getAttribute('data-row'));
        const puedeActualizar = (<?= (!empty($perm['actualizar']) || !empty($perm['todo'])) ? 'true' : 'false' ?>);
        const editable = (row.estado === 'Borrador') && puedeActualizar; // solo Borrador es editable

        resetForm();
        retResetTabs();
        setCamposEditables(editable);
        document.getElementById('ret_id').value = row.id;
        document.getElementById('tituloModalRetorno').innerHTML = '<i class="bi bi-arrow-return-left me-1"></i> Retorno ' + (row.serie || '') + '-' + (row.secuencial || '');
        retPintarBadge(row.estado);

        // Botón guardar: en Borrador editable dice "Actualizar".
        const btnG = document.getElementById('btnGuardarRetorno');
        btnG.innerHTML = '<i class="bi bi-save me-1"></i> ' + (editable ? 'Actualizar retorno' : 'Guardar retorno');

        // Selector de estado (Borrador | Emitida | Anulada), habilitado si tiene permiso de actualizar.
        const wrap = document.getElementById('ret_estado_wrapper');
        const selEstado = document.getElementById('ret_estado_selector');
        wrap.classList.remove('d-none');
        selEstado.value = row.estado;
        selEstado.dataset.prev = row.estado;
        selEstado.disabled = !puedeActualizar;

        const puedeEliminar = (<?= (!empty($perm['eliminar']) || !empty($perm['todo'])) ? 'true' : 'false' ?>);
        document.getElementById('btnEliminarRetorno').classList.toggle('d-none', !puedeEliminar);

        getModal().show();
        if (editable) {
            await retCargarParaEditar(row);
        } else {
            await retCargarDetalle(row.id);
        }
    };

    // Carga un retorno Borrador en modo edición (grilla editable con cantidades precargadas).
    async function retCargarParaEditar(row) {
        const res = await fetch(`${RUTA}/getDetalleAjax?id=${row.id}`);
        const data = await res.json();
        if (!data.ok) { Swal.fire('Error', data.error || 'No se pudo cargar el retorno.', 'error'); return; }
        const r = data.data;

        // Numeración: se conserva la del retorno (no se regenera). El selector de serie queda bloqueado.
        document.getElementById('ret_serie').value = r.serie || '';
        const selSerie = document.getElementById('ret_select_serie');
        if (r.id_punto_emision && selSerie.querySelector(`option[value="${r.id_punto_emision}"]`)) {
            selSerie.value = r.id_punto_emision;
        }
        selSerie.disabled = true;
        document.getElementById('ret_id_punto_emision').value = r.id_punto_emision || '';
        document.getElementById('ret_secuencial').value = r.secuencial || '';
        document.getElementById('ret_secuencial').dataset.sec = r.secuencial || '';
        if (r.fecha_retorno) document.getElementById('ret_fecha_retorno').value = String(r.fecha_retorno).slice(0, 10);

        // Cliente
        document.getElementById('ret_id_cliente').value = r.id_cliente || '';
        document.getElementById('ret_cliente_email').value = r.cliente_email || '';
        document.getElementById('ret_cliente_busqueda').value = (r.cliente_identificacion || '') + ' — ' + (r.cliente_nombre || '');
        document.getElementById('ret_motivo').value = r.motivo || '';
        document.getElementById('ret_observaciones').value = r.observaciones || '';

        // Grilla editable con las líneas pendientes del cliente y las cantidades del retorno precargadas.
        await retCargarLineasCliente(r.id_cliente);
        (r.detalles || []).forEach(d => {
            const tr = document.querySelector(`#ret_lineas_body tr[data-idcd="${d.id_consignacion_detalle}"]`);
            if (tr) {
                const inp = tr.querySelector('.ret-cant');
                if (inp) { inp.value = d.cantidad; retOnCant(inp); }
            }
        });
    }

    function retPintarBadge(estado) {
        const badge = document.getElementById('ret_estado_badge');
        badge.textContent = estado;
        let cls = 'bg-secondary bg-opacity-10 text-secondary';
        if (estado === 'Emitida') cls = 'bg-success bg-opacity-10 text-success';
        else if (estado === 'Anulada') cls = 'bg-danger bg-opacity-10 text-danger';
        else if (estado === 'Borrador') cls = 'bg-warning bg-opacity-10 text-warning';
        badge.className = 'badge ms-2 ' + cls;
    }

    // Cambia el estado del retorno (Borrador | Emitida | Anulada) vía endpoint dedicado.
    window.retCambiarEstado = async function (nuevo) {
        const sel = document.getElementById('ret_estado_selector');
        const id  = document.getElementById('ret_id').value;
        const prev = sel.dataset.prev || 'Emitida';
        if (!id || nuevo === prev) return;

        if (nuevo === 'Anulada' || (prev === 'Emitida' && nuevo === 'Borrador')) {
            const c = await Swal.fire({
                title: nuevo === 'Anulada' ? '¿Anular retorno?' : '¿Pasar a Borrador?',
                text: 'Se reversará la entrada de inventario de este retorno y se liberará el saldo.',
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
            retPintarBadge(nuevo);
            if (typeof cargarGrid === 'function') cargarGrid();
            Swal.fire({ icon: 'success', title: 'Estado actualizado', timer: 1200, showConfirmButton: false });
        } catch (e) {
            sel.value = prev;
            Swal.fire('Error', e.message, 'error');
        }
    };

    // ─── Numeración (Serie = establecimiento-punto en un solo selector) ───────
    window.retSerieChange = async function () {
        const sel = document.getElementById('ret_select_serie');
        const opt = sel.options[sel.selectedIndex];
        const idPunto = sel.value;
        if (!idPunto || !opt) {
            document.getElementById('ret_serie').value = '';
            document.getElementById('ret_id_punto_emision').value = '';
            document.getElementById('ret_secuencial').value = '';
            return;
        }
        const est = opt.dataset.codEst || '';
        const punto = opt.dataset.codPunto || '';
        document.getElementById('ret_serie').value = est + '-' + punto;
        document.getElementById('ret_id_punto_emision').value = idPunto;
        await retCargarSecuencial(idPunto);
    };

    async function retCargarSecuencial(idPunto) {
        if (!idPunto) return;
        const res = await fetch(`${RUTA}/getSecuencialAjax?id_punto_emision=${idPunto}`);
        const data = await res.json();
        if (!data.ok) {
            document.getElementById('ret_secuencial').value = '';
            Swal.fire('Atención', data.msg || 'No hay secuencial configurado.', 'warning');
            return;
        }
        const sec = data.formateado || String(data.secuencial || '').padStart(9, '0');
        document.getElementById('ret_secuencial').value = sec;
        document.getElementById('ret_secuencial').dataset.sec = data.secuencial || '';
    }

    // ─── Cliente ─────────────────────────────────────────────────────────────
    window.retBuscarConsignaciones = function (q) {
        clearTimeout(retClientesTimer);
        const dd = document.getElementById('ret_clientes_dropdown');
        if (!q || q.length < 2) { dd.classList.add('d-none'); return; }
        retClientesTimer = setTimeout(async () => {
            const res = await fetch(`${RUTA}/buscarConsignacionesAjax?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            dd.innerHTML = '';
            (data.data || []).forEach(c => {
                const numero = (c.serie || '') + '-' + (c.secuencial || '');
                const a = document.createElement('a');
                a.href = '#'; a.className = 'list-group-item list-group-item-action py-1';
                a.innerHTML = `<span class="fw-bold text-primary small">${numero}</span>
                               <span class="small text-dark ms-2">${(c.cliente_nombre || '')}</span>
                               <span class="small text-muted ms-1">${c.cliente_identificacion ? '· ' + c.cliente_identificacion : ''}</span>`;
                a.onclick = (ev) => { ev.preventDefault(); retSeleccionarConsignacion(c); };
                dd.appendChild(a);
            });
            if (!data.data || !data.data.length) {
                dd.innerHTML = '<span class="list-group-item small text-muted">Sin resultados con saldo pendiente.</span>';
            }
            dd.classList.remove('d-none');
        }, 300);
    };

    function retSeleccionarConsignacion(c) {
        document.getElementById('ret_id_cliente').value = c.id_cliente;
        document.getElementById('ret_cliente_busqueda').value = (c.cliente_identificacion || '') + ' — ' + (c.cliente_nombre || '');
        document.getElementById('ret_clientes_dropdown').classList.add('d-none');
        retCargarLineasCliente(c.id_cliente);
    }

    document.addEventListener('click', (e) => {
        const dd = document.getElementById('ret_clientes_dropdown');
        if (dd && !e.target.closest('#ret_cliente_busqueda') && !e.target.closest('#ret_clientes_dropdown')) {
            dd.classList.add('d-none');
        }
    });

    // ─── Líneas pendientes ───────────────────────────────────────────────────
    async function retCargarLineasCliente(idCliente) {
        const body = document.getElementById('ret_lineas_body');
        body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
        try {
            const res = await fetch(`${RUTA}/getLineasClienteAjax?id_cliente=${idCliente}`);
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Error');
            const lineas = data.data || [];
            if (!lineas.length) {
                body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Este cliente no tiene consignaciones pendientes de retornar.</td></tr>';
                document.getElementById('ret_lineas_info').textContent = '';
                retRecalcular();
                return;
            }
            body.innerHTML = lineas.map(retRenderLinea).join('');
            document.getElementById('ret_lineas_info').textContent = lineas.length + ' línea(s) pendiente(s)';
            retRecalcular();
        } catch (err) {
            body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">No se pudieron cargar las consignaciones.</td></tr>';
        }
    }

    function retRenderLinea(l, i) {
        const saldo = num(l.saldo_pendiente);
        const consig = (l.serie || '') + '-' + (l.secuencial || '');
        const loteNup = [l.lote, l.nup].filter(Boolean).join(' / ') || '—';
        return `<tr data-idcd="${l.id_consignacion_detalle}" data-saldo="${saldo}" data-precio="${num(l.precio_unitario)}" data-porc="${num(l.porcentaje_impuesto)}" style="cursor:pointer" onclick="retFilaClick(event, this)">
            <td class="text-center"><input type="checkbox" class="ret-chk" onchange="retChkLinea(this)"></td>
            <td class="small">${consig}</td>
            <td class="small">${(l.producto_codigo ? l.producto_codigo + ' · ' : '')}${l.producto_nombre || ''}</td>
            <td class="small">${loteNup}</td>
            <td class="small">${l.bodega_nombre || '—'}</td>
            <td class="text-end small">${fmt(saldo, DEC_C)}</td>
            <td class="p-0"><input type="number" class="form-control form-control-sm text-end ret-cant" min="0" max="${saldo}" step="any" value="0" oninput="retOnCant(this)" onclick="event.stopPropagation()" style="height:26px;font-size:.8rem;"></td>
        </tr>`;
    }

    // Marca/desmarca la fila completa al hacer clic (excepto sobre el input de cantidad o el check).
    window.retFilaClick = function (ev, tr) {
        if (ev.target.closest('.ret-cant') || ev.target.closest('.ret-chk')) return;
        const chk = tr.querySelector('.ret-chk');
        if (!chk) return;
        chk.checked = !chk.checked;
        retChkLinea(chk);
    };

    window.retChkLinea = function (chk) {
        const tr = chk.closest('tr');
        const inp = tr.querySelector('.ret-cant');
        const saldo = num(tr.dataset.saldo);
        inp.value = chk.checked ? saldo : 0;
        retOnCant(inp, true);
    };

    window.retToggleTodos = function (checked) {
        document.querySelectorAll('#ret_lineas_body tr').forEach(tr => {
            const chk = tr.querySelector('.ret-chk');
            if (chk) { chk.checked = checked; retChkLinea(chk); }
        });
    };

    window.retOnCant = function (inp) {
        const tr = inp.closest('tr');
        const saldo = num(tr.dataset.saldo);
        let c = num(inp.value);
        if (c < 0) c = 0;
        // No se puede retornar más de lo pendiente (saldo).
        if (c > saldo) { c = saldo; inp.value = (inp.value === '' ? '' : saldo); }
        const chk = tr.querySelector('.ret-chk');
        if (chk) chk.checked = c > 0;
    };

    function retRecalcular() { /* Los totales monetarios no se muestran en retornos. */ }

    // ─── Ver detalle existente (solo lectura) ────────────────────────────────
    async function retCargarDetalle(id) {
        const body = document.getElementById('ret_lineas_body');
        body.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
        const res = await fetch(`${RUTA}/getDetalleAjax?id=${id}`);
        const data = await res.json();
        if (!data.ok) { body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">No se pudo cargar.</td></tr>'; return; }
        const r = data.data;
        document.getElementById('ret_serie').value = r.serie || '';
        const selSerie = document.getElementById('ret_select_serie');
        if (r.id_punto_emision && selSerie.querySelector(`option[value="${r.id_punto_emision}"]`)) {
            selSerie.value = r.id_punto_emision;
        }
        document.getElementById('ret_secuencial').value = r.secuencial || '';
        document.getElementById('ret_cliente_email').value = r.cliente_email || '';
        document.getElementById('ret_cliente_busqueda').value = (r.cliente_identificacion || '') + ' — ' + (r.cliente_nombre || '');
        document.getElementById('ret_motivo').value = r.motivo || '';
        document.getElementById('ret_observaciones').value = r.observaciones || '';
        if (r.fecha_retorno) document.getElementById('ret_fecha_retorno').value = String(r.fecha_retorno).slice(0, 10);

        const dets = r.detalles || [];
        body.innerHTML = dets.map(d => {
            const consig = (d.consignacion_serie || '') + '-' + (d.consignacion_secuencial || '');
            const loteNup = [d.lote, d.nup].filter(Boolean).join(' / ') || '—';
            return `<tr>
                <td class="text-center"><i class="bi bi-check2 text-success"></i></td>
                <td class="small">${consig}</td>
                <td class="small">${(d.producto_codigo ? d.producto_codigo + ' · ' : '')}${d.producto_nombre || ''}</td>
                <td class="small">${loteNup}</td>
                <td class="small">${d.bodega_nombre || '—'}</td>
                <td class="text-end small">—</td>
                <td class="text-end small fw-bold">${fmt(d.cantidad, DEC_C)}</td>
            </tr>`;
        }).join('');
        document.getElementById('ret_lineas_info').textContent = dets.length + ' línea(s)';
    }

    // ─── Guardar ─────────────────────────────────────────────────────────────
    window.retGuardar = async function () {
        const idCliente = document.getElementById('ret_id_cliente').value;
        if (!idCliente) { Swal.fire('Atención', 'Seleccione un cliente.', 'warning'); return; }
        if (!document.getElementById('ret_secuencial').value) { Swal.fire('Atención', 'Falta el secuencial. Configure el punto de emisión.', 'warning'); return; }

        const detalles = [];
        document.querySelectorAll('#ret_lineas_body tr').forEach(tr => {
            const inp = tr.querySelector('.ret-cant');
            if (!inp) return;
            const c = num(inp.value);
            if (c > 0) detalles.push({ id_consignacion_detalle: parseInt(tr.dataset.idcd, 10), cantidad: c });
        });
        if (!detalles.length) { Swal.fire('Atención', 'Indique la cantidad a retornar en al menos un producto.', 'warning'); return; }

        const idRetorno = document.getElementById('ret_id').value;
        const esEdicion = !!idRetorno;

        const payload = {
            id: idRetorno || null,
            id_cliente: parseInt(idCliente, 10),
            fecha_retorno: document.getElementById('ret_fecha_retorno').value,
            serie: document.getElementById('ret_serie').value,
            secuencial: document.getElementById('ret_secuencial').dataset.sec || '',
            id_punto_emision: document.getElementById('ret_id_punto_emision').value,
            establecimiento: (document.getElementById('ret_serie').value.split('-')[0] || ''),
            punto_emision: (document.getElementById('ret_serie').value.split('-')[1] || ''),
            motivo: document.getElementById('ret_motivo').value,
            observaciones: document.getElementById('ret_observaciones').value,
            detalles
        };

        const btn = document.getElementById('btnGuardarRetorno');
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

    // ─── Eliminar ────────────────────────────────────────────────────────────
    window.retEliminar = async function () {
        const id = document.getElementById('ret_id').value;
        if (!id) return;
        const c = await Swal.fire({
            title: '¿Eliminar retorno?', text: 'Se reversará la entrada de inventario de todos los productos.',
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

    // ─── Acciones rápidas (PDF / Correo / WhatsApp) ───────────────────────────
    window.retPdf = function () {
        const id = document.getElementById('ret_id').value;
        if (!id) return Swal.fire('Atención', 'Debe guardar el retorno primero.', 'warning');
        const a = document.createElement('a');
        a.href = `${RUTA}/pdf?id=${id}`;
        a.download = '';
        document.body.appendChild(a);
        a.click();
        a.remove();
    };
    window.retEmail = async function () {
        const id = document.getElementById('ret_id').value;
        if (!id) return Swal.fire('Atención', 'Debe guardar el retorno primero.', 'warning');

        const { value: correos, isConfirmed } = await Swal.fire({
            title: 'Enviar por correo',
            input: 'text',
            inputLabel: 'Correo(s) destino, separados por coma.',
            inputValue: document.getElementById('ret_cliente_email').value || '',
            inputPlaceholder: 'cliente@correo.com',
            target: document.getElementById('modalRetorno'),
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-envelope me-1"></i> Enviar',
            cancelButtonText: 'Cancelar'
        });
        if (!isConfirmed) return;

        Swal.fire({ title: 'Enviando correo...', allowOutsideClick: false, target: document.getElementById('modalRetorno'), didOpen: () => Swal.showLoading() });
        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('correos', correos || '');
            const res = await fetch(`${RUTA}/enviarCorreoAjax`, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                Swal.fire('Enviado', data.mensaje || 'Correo enviado correctamente.', 'success');
            } else {
                Swal.fire('Error', data.mensaje || 'No se pudo enviar el correo.', 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo enviar el correo.', 'error');
        }
    };
    window.retWhatsapp = function () {
        if (!document.getElementById('ret_id').value) return Swal.fire('Atención', 'Debe guardar el retorno primero.', 'warning');
        Swal.fire('Info', 'Enviando por WhatsApp...', 'info');
    };

    // ─── Pestaña Asiento Contable ─────────────────────────────────────────────
    function retResetTabs() {
        try { new bootstrap.Tab(document.getElementById('ret-tab-general-btn')).show(); } catch (e) {}
        document.getElementById('ret-tbody-asiento').innerHTML =
            '<tr><td colspan="4" class="text-center py-4 text-muted">Guarde el retorno para generar el asiento (se calcula a costo).</td></tr>';
        document.getElementById('ret-asiento-total-debe').textContent = '0.00';
        document.getElementById('ret-asiento-total-haber').textContent = '0.00';
        const badge = document.getElementById('ret-asiento-badge-cuadre');
        badge.textContent = '—'; badge.className = 'badge bg-secondary bg-opacity-10 text-secondary border px-2';
    }

    async function retCargarAsiento() {
        const id = document.getElementById('ret_id').value;
        const tbody = document.getElementById('ret-tbody-asiento');
        if (!id) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-info-circle me-1"></i> Guarde el retorno para generar el asiento (se calcula a costo).</td></tr>';
            return;
        }
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Cargando asiento contable...</td></tr>';
        try {
            const res = await fetch(`${RUTA}/getAsientoSugeridoAjax?id=${id}`);
            const data = await res.json();
            const dets = (data.ok && data.detalles) ? data.detalles : [];
            if (!dets.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-info-circle me-1"></i> Sin asiento: el retorno no tiene costo registrado (kardex de la consignación a 0) o no está Emitido.</td></tr>';
                document.getElementById('ret-asiento-total-debe').textContent = '0.00';
                document.getElementById('ret-asiento-total-haber').textContent = '0.00';
                const badge = document.getElementById('ret-asiento-badge-cuadre');
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
                    <td class="small text-muted">${d.referencia_detalle || ''}</td>
                </tr>`;
            }).join('');
            document.getElementById('ret-asiento-total-debe').textContent = fmt(tDebe, 2);
            document.getElementById('ret-asiento-total-haber').textContent = fmt(tHaber, 2);
            const cuadrado = Math.abs(tDebe - tHaber) < 0.005;
            const badge = document.getElementById('ret-asiento-badge-cuadre');
            badge.textContent = cuadrado ? 'Cuadrado' : 'Descuadrado';
            badge.className = 'badge px-2 ' + (cuadrado ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' : 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25');
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Error al cargar el asiento contable.</td></tr>';
        }
    }

    // Cargar el asiento al mostrar la pestaña.
    document.getElementById('ret-tab-asiento-btn').addEventListener('shown.bs.tab', retCargarAsiento);

    // Exponer para reset al abrir el modal.
    window.__retResetTabs = retResetTabs;
})();
</script>
