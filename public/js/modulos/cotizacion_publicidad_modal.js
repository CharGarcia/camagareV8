/**
 * cotizacion_publicidad_modal.js — Lógica del modal de Cotización de Publicidad (window.CP)
 */
(function () {
    'use strict';

    const CFG      = () => window.CP_CONFIG || {};
    const urlBase  = () => CFG().urlBase || '';
    const tivas    = () => CFG().tarifasIva || [];
    const categorias = () => CFG().categorias || [];
    const perm     = () => CFG().perm || {};

    const $id  = id => document.getElementById(id);
    const fmt2 = v => parseFloat(v || 0).toFixed(2);

    function toast(msg, type = 'success') {
        if (typeof window.mostrarToast === 'function') { window.mostrarToast(msg, type); return; }
        if (typeof window.Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', icon: type === 'success' ? 'success' : 'error', title: msg, showConfirmButton: false, timer: 3000 });
            return;
        }
        alert(msg);
    }

    async function confirm2(msg) {
        if (typeof window.Swal !== 'undefined') {
            const r = await Swal.fire({ title: '¿Confirmar?', text: msg, icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, continuar', cancelButtonText: 'Cancelar' });
            return r.isConfirmed;
        }
        return window.confirm(msg);
    }

    function getModal() {
        let m = bootstrap.Modal.getInstance($id('modalCotizacionPublicidad'));
        if (!m) m = new bootstrap.Modal($id('modalCotizacionPublicidad'));
        return m;
    }

    const BADGE_CLASSES = {
        borrador:   'badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25',
        aprobada:   'badge bg-success bg-opacity-10 text-success border border-success border-opacity-25',
        rechazada:  'badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25',
        convertida: 'badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25',
        anulada:    'badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
    };
    const BADGE_LABELS = { borrador:'Borrador', aprobada:'Aprobada', rechazada:'Rechazada', convertida:'Facturada', anulada:'Anulada' };

    let _estadoActual = 'borrador';

    function _aplicarEstado(estado) {
        _estadoActual = estado;
        const badge = $id('cp_estadoBadge');
        badge.className = BADGE_CLASSES[estado] || 'badge bg-secondary';
        badge.textContent = BADGE_LABELS[estado] || estado;
        badge.classList.remove('d-none');

        const show = (id, cond) => { const el = $id(id); if (el) el.classList.toggle('d-none', !cond); };
        const guardada   = !!$id('cp_id').value;
        const facturable = perm().crear && ['aprobada', 'convertida'].includes(estado);

        show('cp-btn-factura', facturable);
        show('cp-btn-generar-factura-tab', facturable);
        show('cp-btn-version', perm().crear && guardada);
        show('cp-vr1',         perm().crear && guardada);
        show('cp-btn-pdf',     guardada);
        show('cp-vr2',         perm().actualizar && guardada);
        show('cp-btn-aprobar',  perm().actualizar && estado === 'borrador');
        show('cp-btn-rechazar', perm().actualizar && estado === 'aprobada');
        show('cp-btn-anular',   perm().actualizar && ['borrador', 'aprobada'].includes(estado));

        show('cp_btnEliminar', perm().eliminar   && estado !== 'convertida');
        show('cp_btnGuardar',  perm().actualizar && estado === 'borrador');
    }

    function _reset() {
        $id('cp_id').value = '';
        $id('cp_tituloModal').textContent = 'Nueva Cotización';
        $id('cp_fecha').value = new Date().toISOString().slice(0, 10);
        $id('cp_numero').value = 'Se genera al guardar';
        $id('cp_proyecto').value = '';
        $id('cp_presupuesto').value = '0.00';
        $id('cp_vendedor').value = '';
        $id('cp_contacto').value = '';
        $id('cp_comision').value = '17';
        $id('cp_observaciones').value = '';
        if ($id('cp_tarifaIva').options.length) $id('cp_tarifaIva').selectedIndex = 0;

        _limpiarCliente();

        const tbody = $id('cp_tbodyDetalle');
        tbody.innerHTML = '';
        tbody.appendChild(_crearFila());
        _calcularTotales();

        const tbodyCostos = $id('cp_tbodyCostos');
        if (tbodyCostos) tbodyCostos.innerHTML = '<tr><td colspan="5" class="text-center text-muted small py-3">Guarde la cotización para gestionar costos</td></tr>';

        const resumenCont = $id('cp_resumenContenido');
        if (resumenCont) resumenCont.innerHTML = '<div class="text-center text-muted small py-5">Guarde la cotización para ver el resumen.</div>';

        const tbodyFac = $id('cp_tbodyFacturas');
        if (tbodyFac) tbodyFac.innerHTML = '<tr><td colspan="4" class="text-center text-muted small py-3">Sin facturas asociadas</td></tr>';
        $id('cp_facturasMensaje')?.classList.add('d-none');
        $id('cp-btn-generar-factura-tab')?.classList.add('d-none');

        const badge = $id('cp_estadoBadge');
        badge.className = 'badge d-none';
        badge.textContent = '';

        ['cp-btn-factura', 'cp-btn-version', 'cp-vr1', 'cp-btn-pdf', 'cp-vr2',
         'cp-btn-aprobar', 'cp-btn-rechazar', 'cp-btn-anular',
         'cp_btnEliminar', 'cp_btnGuardar'].forEach(id => {
            const el = $id(id); if (el) el.classList.add('d-none');
        });

        if (perm().crear) {
            const btn = $id('cp_btnGuardar');
            if (btn) btn.classList.remove('d-none');
        }

        const tabPrincipal = document.getElementById('cp-tab-cotizacion-btn');
        if (tabPrincipal) bootstrap.Tab.getOrCreateInstance(tabPrincipal).show();
    }

    /* ── Cliente ─────────────────────────────────────────────── */
    function _limpiarCliente() {
        $id('cp_idCliente').value     = '';
        $id('cp_clienteBuscar').value = '';
        const infoDiv = $id('cp_infoCliente');
        if (infoDiv) infoDiv.style.display = 'none';
        const rucEl = $id('cp_clienteRuc');    if (rucEl) rucEl.textContent = '';
        const nomEl = $id('cp_clienteNombre'); if (nomEl) nomEl.textContent = '';
    }

    function _seleccionarCliente(c) {
        $id('cp_idCliente').value     = c.id;
        $id('cp_clienteBuscar').value = c.nombre || c.razon_social || '';
        const infoDiv = $id('cp_infoCliente');
        if (infoDiv) infoDiv.style.display = '';
        const rucEl = $id('cp_clienteRuc');    if (rucEl) rucEl.textContent = c.identificacion || c.ruc || '';
        const nomEl = $id('cp_clienteNombre'); if (nomEl) nomEl.textContent = c.nombre || c.razon_social || '';
        const dd = $id('cp_ddClientes');
        if (dd) dd.classList.add('d-none');
    }

    function _initClienteBuscar() {
        const inp = $id('cp_clienteBuscar');
        const dd  = $id('cp_ddClientes');
        if (!inp || !dd) return;

        inp.addEventListener('keydown', e => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && $id('cp_idCliente').value) {
                e.preventDefault();
                _limpiarCliente();
                dd.classList.add('d-none');
            }
        });

        let timer;
        inp.addEventListener('input', () => {
            clearTimeout(timer);
            const q = inp.value.trim();
            if (!q) {
                dd.classList.add('d-none');
                $id('cp_idCliente').value = '';
                const infoDiv = $id('cp_infoCliente');
                if (infoDiv) infoDiv.style.display = 'none';
                return;
            }
            timer = setTimeout(async () => {
                try {
                    const data = await (await fetch(`${urlBase()}/getClientesAjax?q=${encodeURIComponent(q)}`)).json();
                    if (!data.ok || !data.data.length) {
                        dd.innerHTML = '<div class="list-group-item text-muted small">Sin resultados</div>';
                        dd.classList.remove('d-none');
                        return;
                    }
                    dd.innerHTML = data.data.map(c =>
                        `<div class="list-group-item list-group-item-action py-2" onclick='window._cpSelCliente(${JSON.stringify(c)})'>
                            <div class="fw-semibold small">${_esc(c.nombre || c.razon_social || '')}</div>
                            <div class="text-muted" style="font-size:0.75rem;">${_esc(c.identificacion || c.ruc || '')}</div>
                        </div>`
                    ).join('');
                    dd.classList.remove('d-none');
                } catch (e) { console.error(e); }
            }, 300);
        });

        document.addEventListener('click', e => {
            if (!inp.contains(e.target) && !dd.contains(e.target)) dd.classList.add('d-none');
        });
    }
    window._cpSelCliente = c => _seleccionarCliente(c);

    /* ── Filas de detalle ────────────────────────────────────── */
    function _categoriaOpts(idSeleccionada) {
        const opts = categorias().map(c => {
            const sel = parseInt(c.id) === parseInt(idSeleccionada || 0) ? 'selected' : '';
            return `<option value="${c.id}" ${sel}>${_esc(c.nombre)}</option>`;
        }).join('');
        return `<option value="">Sin categoría</option>${opts}`;
    }

    function _crearFila(data = {}) {
        const precio = parseFloat(data.precio_unitario || 0);
        const cant   = parseFloat(data.cantidad || 1);
        const subtotal = precio * cant;

        const tr = document.createElement('tr');
        tr.className  = 'row-detalle';
        tr.dataset.id = data.id || 0;

        tr.innerHTML = `
        <td class="ps-3">
            <select class="form-select form-select-sm input-detalle input-categoria">
                ${_categoriaOpts(data.id_categoria)}
            </select>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm input-detalle input-descripcion"
                value="${_esc(data.descripcion || '')}" placeholder="Descripción del servicio">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm input-detalle text-end input-precio"
                value="${precio.toFixed(4)}" step="any" min="0">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm input-detalle text-center input-ciudades"
                value="${parseInt(data.ciudades || 1)}" step="1" min="0">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm input-detalle text-center input-dias"
                value="${parseInt(data.dias || 1)}" step="1" min="0">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm input-detalle text-center input-cantidad"
                value="${cant.toFixed(2)}" step="any" min="0">
        </td>
        <td class="text-end pe-4 align-middle">
            <span class="subtotal-line">${subtotal.toFixed(2)}</span>
        </td>
        <td class="text-center p-0 align-middle" style="width:40px;">
            <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0"
                onclick="CP.eliminarFila(this)" title="Eliminar ítem">
                <i class="bi bi-trash3 fs-6"></i>
            </button>
        </td>`;

        tr.querySelector('.input-precio').addEventListener('input', () => _recalcFila(tr));
        tr.querySelector('.input-cantidad').addEventListener('input', () => _recalcFila(tr));

        return tr;
    }

    function _recalcFila(tr) {
        const cant   = parseFloat(tr.querySelector('.input-cantidad').value || 0);
        const precio = parseFloat(tr.querySelector('.input-precio').value || 0);
        tr.querySelector('.subtotal-line').textContent = (cant * precio).toFixed(2);
        _calcularTotales();
    }

    function _calcularTotales() {
        let subtotal = 0;
        document.querySelectorAll('#cp_tbodyDetalle .row-detalle').forEach(tr => {
            const cant   = parseFloat(tr.querySelector('.input-cantidad').value || 0);
            const precio = parseFloat(tr.querySelector('.input-precio').value || 0);
            subtotal += cant * precio;
        });

        const comisionPct = parseFloat($id('cp_comision').value || 0);
        const comision = subtotal * (comisionPct / 100);

        const selIva = $id('cp_tarifaIva');
        const pct = parseFloat(selIva?.selectedOptions[0]?.dataset.pct || 0);
        const iva = (subtotal + comision) * (pct / 100);

        const total = subtotal + comision + iva;

        $id('cp_subtotal').textContent      = fmt2(subtotal);
        $id('cp_totalComision').textContent = fmt2(comision);
        $id('cp_totalIva').textContent      = fmt2(iva);
        $id('cp_importeTotal').textContent  = fmt2(total);
        $id('cp_countItems').textContent    = document.querySelectorAll('#cp_tbodyDetalle .row-detalle').length;
    }

    /* ── Payload ─────────────────────────────────────────────── */
    function _buildPayload() {
        const detalles = [];
        document.querySelectorAll('#cp_tbodyDetalle .row-detalle').forEach(tr => {
            const descripcion = tr.querySelector('.input-descripcion').value.trim();
            if (!descripcion) return;
            detalles.push({
                id:              parseInt(tr.dataset.id || 0),
                id_categoria:    tr.querySelector('.input-categoria').value || null,
                descripcion:     descripcion,
                precio_unitario: parseFloat(tr.querySelector('.input-precio').value || 0),
                ciudades:        parseInt(tr.querySelector('.input-ciudades').value || 1),
                dias:            parseInt(tr.querySelector('.input-dias').value || 1),
                cantidad:        parseFloat(tr.querySelector('.input-cantidad').value || 0),
            });
        });

        return {
            id:             $id('cp_id').value,
            fecha_emision:  $id('cp_fecha').value,
            proyecto:       $id('cp_proyecto').value.trim(),
            presupuesto:    parseFloat($id('cp_presupuesto').value || 0),
            id_vendedor:    $id('cp_vendedor').value,
            id_cliente:     $id('cp_idCliente').value,
            contacto:       $id('cp_contacto').value.trim(),
            id_tarifa_iva:  $id('cp_tarifaIva').value,
            comision:       parseFloat($id('cp_comision').value || 0),
            observaciones:  $id('cp_observaciones').value.trim(),
            detalles,
        };
    }

    /* ── Cargar cotización existente ─────────────────────────── */
    function _badgeEstadoFactura(estado) {
        const e = String(estado || '').toLowerCase();
        const map = {
            borrador:   'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25',
            autorizado: 'bg-success bg-opacity-10 text-success border border-success border-opacity-25',
            enviado:    'bg-success bg-opacity-10 text-success border border-success border-opacity-25',
            anulado:    'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
        };
        const cls = map[e] || 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25';
        const label = e ? e.charAt(0).toUpperCase() + e.slice(1) : '—';
        return `<span class="badge ${cls}">${_esc(label)}</span>`;
    }

    async function _cargarFacturas(id) {
        const tbody = $id('cp_tbodyFacturas');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted small py-3">Cargando...</td></tr>';
        try {
            const data = await (await fetch(`${urlBase()}/getFacturasAjax?id=${id}`)).json();
            const lista = (data.ok && data.facturas) ? data.facturas : [];

            // Solo se puede generar otra factura si NO hay ninguna activa (no anulada).
            const hayActiva = lista.some(f => f.estado !== 'anulada');
            const msgEl = $id('cp_facturasMensaje');
            if (hayActiva) {
                $id('cp-btn-generar-factura-tab')?.classList.add('d-none');
                $id('cp-btn-factura')?.classList.add('d-none');
                if (msgEl) { msgEl.textContent = 'Ya existe una factura activa para esta cotización. Anúlela para poder generar otra.'; msgEl.classList.remove('d-none'); }
            } else if (msgEl) {
                msgEl.classList.add('d-none');
            }

            if (!lista.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted small py-3">Sin facturas asociadas</td></tr>';
                return;
            }
            tbody.innerHTML = lista.map(f => {
                const num   = `${f.establecimiento || ''}-${f.punto_emision || ''}-${String(f.secuencial || '').padStart(9, '0')}`;
                const fecha = (f.fecha_emision || '').slice(0, 10).split('-').reverse().join('-');
                const total = parseFloat(f.importe_total || 0).toFixed(2);
                return `<tr>
                    <td class="ps-3 small">${_esc(fecha)}</td>
                    <td class="small"><code class="text-secondary">${_esc(num)}</code></td>
                    <td class="small text-end">${total}</td>
                    <td class="small text-center">${_badgeEstadoFactura(f.estado)}</td>
                </tr>`;
            }).join('');
        } catch (e) {
            console.error(e);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger small py-3">Error al cargar facturas</td></tr>';
        }
    }

    // Dropdown flotante genérico anclado a <body> (escapa el overflow:auto de
    // la tarjeta de ítems, que si no recorta el listado).
    function _crearDropdownFlotante() {
        const dd = document.createElement('div');
        dd.className = 'list-group shadow d-none';
        dd.style.cssText = 'position:fixed;z-index:9999;min-width:260px;max-height:220px;overflow-y:auto;background:#fff;border:1px solid #dee2e6;border-radius:0 0 6px 6px;box-shadow:0 4px 16px rgba(0,0,0,.12);';
        document.body.appendChild(dd);
        return dd;
    }
    function _posDropdown(dd, anclaEl, minWidth = 260) {
        const r = anclaEl.getBoundingClientRect();
        dd.style.top   = r.bottom + 'px';
        dd.style.left  = r.left   + 'px';
        dd.style.width = Math.max(r.width, minWidth) + 'px';
    }

    /**
     * Fila de encabezado de grupo (una por línea cotizada): descripción + cotizado
     * + botón para agregar otra fila de costo (otro proveedor) bajo esa misma línea.
     */
    function _crearFilaGrupoCosto(det) {
        const cotizado = parseFloat(det.precio_total_sin_impuesto || 0);
        const tr = document.createElement('tr');
        tr.className = 'costo-header table-light';
        tr.dataset.idDetalle = det.id;
        tr.dataset.cotizado = cotizado;
        tr.innerHTML = `
        <td colspan="4" class="ps-3 py-1 small">
            <strong>${_esc(det.descripcion || '')}</strong>
            <span class="text-muted">${det.categoria_nombre ? '(' + _esc(det.categoria_nombre) + ')' : ''}</span>
            <span class="text-muted"> · Cotizado: $${cotizado.toFixed(2)}</span>
        </td>
        <td class="text-center p-0">
            <button type="button" class="btn btn-link btn-sm p-0 text-primary shadow-none border-0"
                onclick="CP.agregarLineaCosto(${det.id})" title="Agregar proveedor para esta línea">
                <i class="bi bi-plus-circle"></i>
            </button>
        </td>`;
        return tr;
    }

    function _crearFilaCosto(d, idDetalle) {
        d = d || {};
        const tr = document.createElement('tr');
        tr.className = 'row-costo';
        tr.dataset.idDetalle = idDetalle;
        tr.innerHTML = `
        <td class="ps-3">
            <input type="text" class="form-control form-control-sm input-detalle input-proveedor-buscar"
                value="${_esc(d.proveedor_nombre || '')}" placeholder="Buscar proveedor...">
            <input type="hidden" class="input-id-proveedor" value="${d.id_proveedor || ''}">
        </td>
        <td>
            <div class="d-flex align-items-center gap-1">
                <input type="text" class="form-control form-control-sm input-detalle input-factura-proveedor"
                    value="${_esc(d.factura_proveedor || '')}" placeholder="N° factura (libre)">
                <button type="button" class="btn btn-link btn-sm p-0 text-primary shadow-none border-0 input-btn-buscar-factura"
                    title="Buscar facturas de este proveedor" ${d.id_proveedor ? '' : 'disabled'}>
                    <i class="bi bi-search"></i>
                </button>
                <input type="hidden" class="input-id-compra" value="${d.id_compra || ''}">
            </div>
        </td>
        <td><input type="number" class="form-control form-control-sm input-detalle text-end input-valor-costo" value="${parseFloat(d.valor_costo || 0).toFixed(2)}" step="any" min="0" oninput="CP._recalcularUtilidad()"></td>
        <td><input type="text" class="form-control form-control-sm input-detalle input-observacion-costo" value="${_esc(d.observacion_costo || '')}"></td>
        <td class="text-center p-0 align-middle" style="width:40px;">
            <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="CP.eliminarFilaCosto(this)" title="Eliminar línea">
                <i class="bi bi-trash3 fs-6"></i>
            </button>
        </td>`;

        const inp        = tr.querySelector('.input-proveedor-buscar');
        const idInp       = tr.querySelector('.input-id-proveedor');
        const btnFactura  = tr.querySelector('.input-btn-buscar-factura');
        const inpFactura  = tr.querySelector('.input-factura-proveedor');
        const idCompraInp = tr.querySelector('.input-id-compra');

        const dd = _crearDropdownFlotante();
        tr._cpDdProv = dd;
        dd._cpTr = tr;

        const _limpiarFacturaVinculada = () => {
            idCompraInp.value = '';
        };

        inp.addEventListener('keydown', e => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && idInp.value) {
                e.preventDefault();
                inp.value = ''; idInp.value = '';
                btnFactura.disabled = true;
                _limpiarFacturaVinculada();
                dd.classList.add('d-none');
            }
        });

        let timer;
        inp.addEventListener('input', () => {
            clearTimeout(timer);
            const q = inp.value.trim();
            if (!q) { dd.classList.add('d-none'); idInp.value = ''; btnFactura.disabled = true; _limpiarFacturaVinculada(); return; }
            timer = setTimeout(async () => {
                try {
                    const res = await (await fetch(`${urlBase()}/getProveedoresAjax?q=${encodeURIComponent(q)}`)).json();
                    if (!res.ok || !res.data.length) {
                        dd.innerHTML = '<div class="list-group-item text-muted small py-2">Sin resultados</div>';
                    } else {
                        dd.innerHTML = res.data.map(p =>
                            `<div class="list-group-item list-group-item-action py-1" style="font-size:0.8rem;cursor:pointer;"
                                 onclick='window._cpSelProveedor(this, ${JSON.stringify(p)})'>
                                <span class="fw-semibold">${_esc(p.nombre)}</span>
                                <span class="text-muted ms-2 small">${_esc(p.identificacion || '')}</span>
                            </div>`
                        ).join('');
                    }
                    _posDropdown(dd, inp);
                    dd.classList.remove('d-none');
                } catch (e) { console.error(e); }
            }, 280);
        });
        // Reposicionar si la tabla de costos hace scroll.
        document.querySelector('#cp-tab-costos .table-responsive')?.addEventListener('scroll', () => {
            if (!dd.classList.contains('d-none')) _posDropdown(dd, inp);
        });
        document.addEventListener('click', e => {
            if (!inp.contains(e.target) && !dd.contains(e.target)) dd.classList.add('d-none');
        });

        // Al tipear manualmente el N° de factura, ya no queda vinculada a la
        // compra elegida del listado (puede haber sido editada a mano).
        inpFactura.addEventListener('input', _limpiarFacturaVinculada);

        // ── Buscar facturas de compra del proveedor ya seleccionado ──
        const ddFact = _crearDropdownFlotante();
        ddFact.style.minWidth = '340px';
        tr._cpDdFact = ddFact;

        async function _buscarFacturas() {
            const idProveedor = idInp.value;
            if (!idProveedor) { toast('Seleccione primero un proveedor', 'error'); return; }
            const idCotizacion = $id('cp_id').value;
            ddFact.innerHTML = '<div class="list-group-item text-muted small py-2">Buscando...</div>';
            _posDropdown(ddFact, btnFactura, 340);
            ddFact.classList.remove('d-none');
            try {
                const resp = await fetch(`${urlBase()}/getFacturasProveedorAjax?id_proveedor=${idProveedor}&id_cotizacion=${idCotizacion}`);
                const res  = await resp.json();
                if (!res.ok) {
                    ddFact.innerHTML = `<div class="list-group-item text-danger small py-2">${_esc(res.error || 'No se pudo buscar facturas.')}</div>`;
                    return;
                }
                if (!res.data.length) {
                    ddFact.innerHTML = '<div class="list-group-item text-muted small py-2">Este proveedor no tiene facturas de compra registradas (o ya fueron usadas). Puede ingresar el N° y el costo manualmente.</div>';
                    return;
                }
                ddFact.innerHTML = res.data.map(f => {
                    const fecha = (f.fecha_emision || '').slice(0, 10).split('-').reverse().join('-');
                    return `<div class="list-group-item list-group-item-action py-1" style="font-size:0.8rem;cursor:pointer;"
                             onclick='window._cpSelFactura(this, ${JSON.stringify(f)})'>
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold">${_esc(f.numero)}</span>
                                <span class="text-muted">${_esc(fecha)}</span>
                            </div>
                            <div class="text-muted">Subtotal: $${parseFloat(f.total_sin_impuestos || 0).toFixed(2)}</div>
                        </div>`;
                }).join('');
            } catch (e) {
                console.error('Error al buscar facturas del proveedor:', e);
                ddFact.innerHTML = '<div class="list-group-item text-danger small py-2">Error de conexión al buscar facturas.</div>';
            }
        }
        btnFactura.addEventListener('click', _buscarFacturas);
        document.querySelector('#cp-tab-costos .table-responsive')?.addEventListener('scroll', () => {
            if (!ddFact.classList.contains('d-none')) _posDropdown(ddFact, btnFactura, 340);
        });
        document.addEventListener('click', e => {
            if (!btnFactura.contains(e.target) && !ddFact.contains(e.target)) ddFact.classList.add('d-none');
        });
        ddFact._cpTr = tr;

        return tr;
    }
    window._cpSelProveedor = (el, p) => {
        const dd = el.closest('.list-group');
        const tr = dd?._cpTr;
        if (!tr) return;
        tr.querySelector('.input-proveedor-buscar').value = p.nombre || '';
        tr.querySelector('.input-id-proveedor').value = p.id || '';
        tr.querySelector('.input-id-compra').value = '';
        tr.querySelector('.input-btn-buscar-factura').disabled = false;
        dd.classList.add('d-none');
    };
    window._cpSelFactura = (el, f) => {
        const dd = el.closest('.list-group');
        const tr = dd?._cpTr;
        if (!tr) return;
        tr.querySelector('.input-factura-proveedor').value = f.numero || '';
        tr.querySelector('.input-id-compra').value = f.id || '';
        tr.querySelector('.input-valor-costo').value = parseFloat(f.total_sin_impuestos || 0).toFixed(2);
        dd.classList.add('d-none');
        _recalcularUtilidad();
    };

    function _recalcularUtilidad() {
        let cotizado = 0, costo = 0;
        // Cotizado: una vez por cada línea (grupo), no por cada fila de costo.
        document.querySelectorAll('#cp_tbodyCostos .costo-header').forEach(tr => {
            cotizado += parseFloat(tr.dataset.cotizado || 0);
        });
        document.querySelectorAll('#cp_tbodyCostos .row-costo').forEach(tr => {
            costo += parseFloat(tr.querySelector('.input-valor-costo').value || 0);
        });
        const comisionPct = parseFloat($id('cp_comision').value || 0);
        const base = cotizado * (1 + comisionPct / 100);
        const utilidad = base - costo;
        const pct = base > 0 ? (utilidad / base * 100) : 0;
        const el = $id('cp_resumenUtilidad');
        if (!el) return;
        if (utilidad < 0) {
            el.innerHTML = `<span class="text-danger">Pérdida: $${Math.abs(utilidad).toFixed(2)} (${pct.toFixed(1)}%)</span>`;
        } else {
            el.innerHTML = `<span class="text-success">Utilidad: $${utilidad.toFixed(2)} (${pct.toFixed(1)}%)</span>`;
        }
    }

    /**
     * Pinta la pestaña Costos agrupada por línea cotizada: un encabezado por
     * línea (descripción + cotizado) seguido de sus filas de costo (una por
     * proveedor). Si una línea no tiene costos aún, se le agrega una fila vacía.
     */
    function _renderCostosTab(detalles, costos) {
        const tbody = $id('cp_tbodyCostos');
        if (!tbody) return;
        tbody.innerHTML = '';

        const porDetalle = {};
        (costos || []).forEach(c => {
            const k = c.id_detalle;
            if (!porDetalle[k]) porDetalle[k] = [];
            porDetalle[k].push(c);
        });

        detalles.forEach(det => {
            tbody.appendChild(_crearFilaGrupoCosto(det));
            const filas = porDetalle[det.id] || [];
            if (!filas.length) {
                tbody.appendChild(_crearFilaCosto({}, det.id));
            } else {
                filas.forEach(c => tbody.appendChild(_crearFilaCosto(c, det.id)));
            }
        });

        _recalcularUtilidad();
    }

    function _grupoUltimaFilaCosto(idDetalle) {
        const filas = document.querySelectorAll(`#cp_tbodyCostos tr[data-id-detalle="${idDetalle}"]`);
        return filas.length ? filas[filas.length - 1] : null;
    }

    /* ════════════════════════════════════════════════════════════
     * PESTAÑA RESUMEN — análisis financiero de la cotización
     * ════════════════════════════════════════════════════════════ */

    function _kpiCard(label, valor, extra = '', claseValor = 'text-dark') {
        return `<div class="bg-white border rounded p-2 shadow-sm text-center" style="min-width:150px;flex:1;">
            <div class="text-muted" style="font-size:0.68rem;text-transform:uppercase;letter-spacing:.03em;">${_esc(label)}</div>
            <div class="fw-bold ${claseValor}" style="font-size:1.05rem;">${valor}</div>
            ${extra ? `<div class="text-muted" style="font-size:0.7rem;">${extra}</div>` : ''}
        </div>`;
    }

    function _renderResumenTab(c, detalles, costos) {
        const cont = $id('cp_resumenContenido');
        if (!cont || !c) return;

        const subtotal   = parseFloat(c.total_sin_impuestos || 0);
        const comisionPct = parseFloat(c.comision || 0);
        const comision   = parseFloat(c.total_comision || 0);
        const iva        = parseFloat(c.total_iva || 0);
        const total      = parseFloat(c.importe_total || 0);
        const presupuesto = parseFloat(c.presupuesto || 0);

        // Costos agrupados por línea
        const costosPorDetalle = {};
        (costos || []).forEach(co => {
            const k = co.id_detalle;
            if (!costosPorDetalle[k]) costosPorDetalle[k] = [];
            costosPorDetalle[k].push(co);
        });

        let costoTotal = 0;
        let lineasSinCosto = 0;
        const filasLinea = [];
        const porCategoria = {};
        const porProveedor = {};

        (detalles || []).forEach(det => {
            const cotizadoLinea = parseFloat(det.precio_total_sin_impuesto || 0);
            const costosLinea = costosPorDetalle[det.id] || [];
            const costoLinea = costosLinea.reduce((s, co) => s + parseFloat(co.valor_costo || 0), 0);
            costoTotal += costoLinea;
            if (!costosLinea.length || costoLinea <= 0) lineasSinCosto++;

            const utilidadLinea = cotizadoLinea - costoLinea;
            const margenLinea = cotizadoLinea > 0 ? (utilidadLinea / cotizadoLinea * 100) : 0;

            filasLinea.push({ det, cotizadoLinea, costoLinea, utilidadLinea, margenLinea, tieneCosto: costosLinea.length > 0 });

            const catKey = det.categoria_nombre || 'Sin categoría';
            if (!porCategoria[catKey]) porCategoria[catKey] = { cotizado: 0, costo: 0 };
            porCategoria[catKey].cotizado += cotizadoLinea;
            porCategoria[catKey].costo    += costoLinea;

            costosLinea.forEach(co => {
                const provKey = co.proveedor_nombre || 'Sin proveedor';
                if (!porProveedor[provKey]) porProveedor[provKey] = { costo: 0, facturas: 0 };
                porProveedor[provKey].costo += parseFloat(co.valor_costo || 0);
                porProveedor[provKey].facturas += 1;
            });
        });

        // Utilidad de agencia: lo que se cobra (subtotal + comisión, antes de IVA) menos el costo real.
        const baseFacturable = subtotal + comision;
        const utilidad = baseFacturable - costoTotal;
        const margenPct = baseFacturable > 0 ? (utilidad / baseFacturable * 100) : 0;
        const claseUtilidad = utilidad < 0 ? 'text-danger' : 'text-success';

        let html = '';

        // ── KPIs ──
        html += `<div class="d-flex flex-wrap gap-2 mb-3">
            ${_kpiCard('Subtotal cotizado', '$' + subtotal.toFixed(2))}
            ${_kpiCard('Comisión agencia', '$' + comision.toFixed(2), comisionPct.toFixed(2) + '%')}
            ${_kpiCard('IVA', '$' + iva.toFixed(2))}
            ${_kpiCard('Total cotizado', '$' + total.toFixed(2), '', 'text-primary')}
            ${_kpiCard('Costo real', '$' + costoTotal.toFixed(2))}
            ${_kpiCard(utilidad < 0 ? 'Pérdida' : 'Utilidad', '$' + Math.abs(utilidad).toFixed(2), 'Margen: ' + margenPct.toFixed(1) + '%', claseUtilidad)}
        </div>`;

        if (lineasSinCosto > 0) {
            html += `<div class="alert alert-warning py-2 px-3 small mb-3">
                <i class="bi bi-exclamation-triangle me-1"></i>
                ${lineasSinCosto} de ${detalles.length} línea(s) todavía no tienen costo registrado — la utilidad mostrada puede no ser definitiva.
            </div>`;
        }

        if (presupuesto > 0) {
            const diff = total - presupuesto;
            const dentro = diff <= 0;
            html += `<div class="alert ${dentro ? 'alert-success' : 'alert-danger'} py-2 px-3 small mb-3 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-wallet2 me-1"></i>Presupuesto del cliente: $${presupuesto.toFixed(2)} — Total cotizado: $${total.toFixed(2)}</span>
                <strong>${dentro ? 'Dentro de presupuesto' : 'Excede presupuesto'} ($${Math.abs(diff).toFixed(2)})</strong>
            </div>`;
        }

        // ── Tabla por línea ──
        html += `<h6 class="small fw-bold text-secondary mb-2"><i class="bi bi-list-ul me-1"></i>Detalle por línea</h6>
        <div class="border rounded-3 overflow-hidden bg-white shadow-sm mb-3">
            <div class="table-responsive" style="max-height:220px;">
                <table class="table table-sm mb-0 text-nowrap">
                    <thead class="table-light"><tr>
                        <th class="ps-3 small">Descripción</th>
                        <th class="small">Categoría</th>
                        <th class="small text-end">Cotizado</th>
                        <th class="small text-end">Costo</th>
                        <th class="small text-end">Utilidad</th>
                        <th class="small text-end pe-3">Margen</th>
                    </tr></thead>
                    <tbody>`;
        if (!filasLinea.length) {
            html += `<tr><td colspan="6" class="text-center text-muted small py-3">Sin líneas</td></tr>`;
        } else {
            filasLinea.forEach(f => {
                const cls = f.utilidadLinea < 0 ? 'text-danger' : 'text-success';
                html += `<tr>
                    <td class="ps-3 small text-truncate" style="max-width:220px;">${_esc(f.det.descripcion || '')}</td>
                    <td class="small">${_esc(f.det.categoria_nombre || '-')}</td>
                    <td class="small text-end">$${f.cotizadoLinea.toFixed(2)}</td>
                    <td class="small text-end">${f.tieneCosto ? '$' + f.costoLinea.toFixed(2) : '<span class="text-muted">Sin costo</span>'}</td>
                    <td class="small text-end ${cls}">$${f.utilidadLinea.toFixed(2)}</td>
                    <td class="small text-end pe-3 ${cls}">${f.margenLinea.toFixed(1)}%</td>
                </tr>`;
            });
        }
        html += `</tbody></table></div></div>`;

        // ── Tabla por categoría ──
        const catKeys = Object.keys(porCategoria);
        if (catKeys.length) {
            html += `<h6 class="small fw-bold text-secondary mb-2"><i class="bi bi-tags me-1"></i>Por categoría</h6>
            <div class="border rounded-3 overflow-hidden bg-white shadow-sm mb-3">
                <div class="table-responsive" style="max-height:180px;">
                    <table class="table table-sm mb-0 text-nowrap">
                        <thead class="table-light"><tr>
                            <th class="ps-3 small">Categoría</th>
                            <th class="small text-end">Cotizado</th>
                            <th class="small text-end">Costo</th>
                            <th class="small text-end">Utilidad</th>
                            <th class="small text-end pe-3">Margen</th>
                        </tr></thead>
                        <tbody>`;
            catKeys.sort((a, b) => porCategoria[b].cotizado - porCategoria[a].cotizado).forEach(k => {
                const g = porCategoria[k];
                const u = g.cotizado - g.costo;
                const m = g.cotizado > 0 ? (u / g.cotizado * 100) : 0;
                const cls = u < 0 ? 'text-danger' : 'text-success';
                html += `<tr>
                    <td class="ps-3 small">${_esc(k)}</td>
                    <td class="small text-end">$${g.cotizado.toFixed(2)}</td>
                    <td class="small text-end">$${g.costo.toFixed(2)}</td>
                    <td class="small text-end ${cls}">$${u.toFixed(2)}</td>
                    <td class="small text-end pe-3 ${cls}">${m.toFixed(1)}%</td>
                </tr>`;
            });
            html += `</tbody></table></div></div>`;
        }

        // ── Tabla por proveedor ──
        const provKeys = Object.keys(porProveedor);
        if (provKeys.length) {
            html += `<h6 class="small fw-bold text-secondary mb-2"><i class="bi bi-truck me-1"></i>Por proveedor</h6>
            <div class="border rounded-3 overflow-hidden bg-white shadow-sm mb-3">
                <div class="table-responsive" style="max-height:180px;">
                    <table class="table table-sm mb-0 text-nowrap">
                        <thead class="table-light"><tr>
                            <th class="ps-3 small">Proveedor</th>
                            <th class="small text-center">Facturas usadas</th>
                            <th class="small text-end pe-3">Costo total</th>
                        </tr></thead>
                        <tbody>`;
            provKeys.sort((a, b) => porProveedor[b].costo - porProveedor[a].costo).forEach(k => {
                const g = porProveedor[k];
                html += `<tr>
                    <td class="ps-3 small">${_esc(k)}</td>
                    <td class="small text-center">${g.facturas}</td>
                    <td class="small text-end pe-3">$${g.costo.toFixed(2)}</td>
                </tr>`;
            });
            html += `</tbody></table></div></div>`;
        }

        // ── Estado de facturación ──
        const estadoLabel = BADGE_LABELS[c.estado] || c.estado;
        const estadoCls = BADGE_CLASSES[c.estado] || 'badge bg-secondary';
        html += `<div class="d-flex align-items-center gap-2 small text-muted">
            <span class="${estadoCls}">${_esc(estadoLabel)}</span>
            ${c.estado === 'convertida' && c.fecha_convertida ? 'Facturada el ' + _esc((c.fecha_convertida || '').slice(0, 10).split('-').reverse().join('-')) : 'Aún no se ha generado una factura para esta cotización.'}
        </div>`;

        cont.innerHTML = html;
    }

    async function _cargarCotizacion(id) {
        try {
            const data = await (await fetch(`${urlBase()}/getCotizacionAjax?id=${id}`)).json();
            if (!data.ok) { toast(data.mensaje || 'Error al cargar', 'error'); return; }

            const c = data.cabecera;
            $id('cp_id').value = c.id;
            const anio = new Date(c.fecha_emision).getFullYear();
            const numero = `${String(c.numero).padStart(3, '0')}-${anio} V${c.version}`;
            $id('cp_tituloModal').textContent = `Cotización ${numero}`;
            $id('cp_numero').value = numero;

            $id('cp_fecha').value        = (c.fecha_emision || '').slice(0, 10);
            $id('cp_proyecto').value     = c.proyecto || '';
            $id('cp_presupuesto').value  = parseFloat(c.presupuesto || 0).toFixed(2);
            $id('cp_vendedor').value     = c.id_vendedor || '';
            $id('cp_contacto').value     = c.contacto || '';
            $id('cp_comision').value     = c.comision || 0;
            $id('cp_observaciones').value = c.observaciones || '';

            const selIva = $id('cp_tarifaIva');
            if (selIva && c.id_tarifa_iva) {
                Array.from(selIva.options).forEach(o => { o.selected = parseInt(o.value) === parseInt(c.id_tarifa_iva); });
            }

            _limpiarCliente();
            if (c.id_cliente) {
                $id('cp_idCliente').value     = c.id_cliente;
                $id('cp_clienteBuscar').value = c.cliente_nombre || '';
                const infoDiv = $id('cp_infoCliente');
                if (infoDiv) infoDiv.style.display = '';
                const rucEl = $id('cp_clienteRuc');    if (rucEl) rucEl.textContent = c.cliente_ruc || '';
                const nomEl = $id('cp_clienteNombre'); if (nomEl) nomEl.textContent = c.cliente_nombre || '';
            }

            $id('cp_tbodyDetalle').innerHTML = '';
            (data.detalles || []).forEach(d => $id('cp_tbodyDetalle').appendChild(_crearFila(d)));
            _calcularTotales();

            _renderCostosTab(data.detalles || [], data.costos || []);
            _renderResumenTab(c, data.detalles || [], data.costos || []);

            _aplicarEstado(c.estado);
            _cargarFacturas(id);
        } catch (e) {
            console.error(e);
            toast('Error de conexión', 'error');
        }
    }

    function _esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    /* ════════════════════════════════════════════════════════════
     * MODAL "GENERAR FACTURA" — facturación manual con productos reales
     * ════════════════════════════════════════════════════════════ */

    let _cpfBloquearSecuencial = false;

    function _syncEstabFactura(selPunto) {
        const opt = selPunto.selectedOptions[0];
        const hiddenEstab = $id('cpf_establecimiento');
        if (hiddenEstab && opt) hiddenEstab.value = opt.dataset.est || '';
    }

    async function _cargarSecuencialFactura(idPunto) {
        if (!idPunto || _cpfBloquearSecuencial) return;
        const inputSec = $id('cpf_secuencial');
        if (inputSec) inputSec.placeholder = 'Cargando...';
        try {
            const resp = await fetch(`${urlBase()}/getSecuencialFacturaAjax?id_punto_emision=${idPunto}`);
            const json = await resp.json();
            if (json.ok) {
                inputSec.value = json.formateado || String(json.secuencial).padStart(9, '0');
            } else {
                inputSec.value = '000000001';
            }
            inputSec.placeholder = '000000001';
        } catch (e) {
            if (inputSec) { inputSec.value = '000000001'; inputSec.placeholder = '000000001'; }
            console.error('Error cargando secuencial de factura', e);
        }
    }

    function _limpiarClienteFactura() {
        $id('cpf_idCliente').value = '';
        $id('cpf_clienteBuscar').value = '';
        const dd = $id('cpf_ddClientes');
        if (dd) dd.classList.add('d-none');
    }

    function _seleccionarClienteFactura(c) {
        $id('cpf_idCliente').value = c.id;
        $id('cpf_clienteBuscar').value = c.nombre || c.razon_social || '';
        const dd = $id('cpf_ddClientes');
        if (dd) dd.classList.add('d-none');
    }

    let _cpfClienteBuscarInit = false;
    function _initClienteBuscarFactura() {
        if (_cpfClienteBuscarInit) return;
        const inp = $id('cpf_clienteBuscar');
        const dd  = $id('cpf_ddClientes');
        if (!inp || !dd) return;
        _cpfClienteBuscarInit = true;

        inp.addEventListener('keydown', e => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && $id('cpf_idCliente').value) {
                e.preventDefault();
                _limpiarClienteFactura();
            }
        });

        let timer;
        inp.addEventListener('input', () => {
            clearTimeout(timer);
            const q = inp.value.trim();
            if (!q) { dd.classList.add('d-none'); $id('cpf_idCliente').value = ''; return; }
            timer = setTimeout(async () => {
                try {
                    const data = await (await fetch(`${urlBase()}/getClientesAjax?q=${encodeURIComponent(q)}`)).json();
                    if (!data.ok || !data.data.length) {
                        dd.innerHTML = '<div class="list-group-item text-muted small">Sin resultados</div>';
                        dd.classList.remove('d-none');
                        return;
                    }
                    dd.innerHTML = data.data.map(c =>
                        `<div class="list-group-item list-group-item-action py-2" onclick='window._cpfSelCliente(${JSON.stringify(c)})'>
                            <div class="fw-semibold small">${_esc(c.nombre || c.razon_social || '')}</div>
                            <div class="text-muted" style="font-size:0.75rem;">${_esc(c.identificacion || c.ruc || '')}</div>
                        </div>`
                    ).join('');
                    dd.classList.remove('d-none');
                } catch (e) { console.error(e); }
            }, 300);
        });

        document.addEventListener('click', e => {
            if (!inp.contains(e.target) && !dd.contains(e.target)) dd.classList.add('d-none');
        });
    }
    window._cpfSelCliente = c => _seleccionarClienteFactura(c);

    function _crearFilaFactura(data = {}) {
        const precio = parseFloat(data.precio_unitario || 0);
        const cant   = parseFloat(data.cantidad || 1);

        const tr = document.createElement('tr');
        tr.className = 'row-detalle-factura';
        tr.innerHTML = `
        <td class="ps-3 position-relative">
            <input type="text" class="form-control form-control-sm input-detalle input-descripcion"
                value="${_esc(data.descripcion || '')}" placeholder="Buscar producto...">
            <input type="hidden" class="input-id-producto" value="${data.id_producto || ''}">
            <input type="hidden" class="input-codigo" value="${_esc(data.codigo_principal || '')}">
            <input type="hidden" class="input-id-tarifa" value="${data.id_tarifa_iva || ''}">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm input-detalle text-center input-cantidad"
                value="${cant.toFixed(2)}" step="any" min="0">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm input-detalle text-end input-precio"
                value="${precio.toFixed(2)}" step="any" min="0">
        </td>
        <td class="text-end pe-4 align-middle">
            <span class="subtotal-line">${(precio * cant).toFixed(2)}</span>
        </td>
        <td class="text-center p-0 align-middle" style="width:40px;">
            <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0"
                onclick="CP.eliminarFilaFactura(this)" title="Eliminar línea">
                <i class="bi bi-trash3 fs-6"></i>
            </button>
        </td>`;

        const inpDesc = tr.querySelector('.input-descripcion');
        const ddProd  = document.createElement('div');
        ddProd.className = 'list-group shadow d-none';
        ddProd.style.cssText = 'position:fixed;z-index:9999;min-width:320px;max-height:220px;overflow-y:auto;background:#fff;border:1px solid #dee2e6;border-radius:0 0 6px 6px;box-shadow:0 4px 16px rgba(0,0,0,.12);';
        document.body.appendChild(ddProd);
        tr._cpfDdProd = ddProd;
        ddProd._cpfTr = tr;

        function _posDdProd() {
            const r = inpDesc.getBoundingClientRect();
            ddProd.style.top   = r.bottom + 'px';
            ddProd.style.left  = r.left   + 'px';
            ddProd.style.width = Math.max(r.width, 320) + 'px';
        }

        inpDesc.addEventListener('keydown', e => {
            const idProd = tr.querySelector('.input-id-producto');
            if ((e.key === 'Backspace' || e.key === 'Delete') && idProd && idProd.value) {
                e.preventDefault();
                inpDesc.value = '';
                idProd.value = '';
                tr.querySelector('.input-codigo').value = '';
                tr.querySelector('.input-id-tarifa').value = '';
                ddProd.classList.add('d-none');
            }
        });

        let timer;
        inpDesc.addEventListener('input', () => {
            clearTimeout(timer);
            const q = inpDesc.value.trim();
            if (!q) { ddProd.classList.add('d-none'); return; }
            timer = setTimeout(async () => {
                try {
                    const res = await (await fetch(`${urlBase()}/getProductosAjax?q=${encodeURIComponent(q)}`)).json();
                    if (!res.ok || !res.data?.length) {
                        ddProd.innerHTML = '<div class="list-group-item text-muted small py-2 px-3">Sin resultados</div>';
                    } else {
                        ddProd.innerHTML = res.data.map(p =>
                            `<div class="list-group-item list-group-item-action py-1 px-3" style="font-size:0.8rem;cursor:pointer;"
                                 onclick='window._cpfSelProd(this, ${JSON.stringify(p)})'>
                                <span class="fw-semibold">${_esc(p.codigo || '')} — ${_esc(p.nombre || '')}</span>
                                <span class="text-muted ms-2 float-end">$${parseFloat(p.precio_base || 0).toFixed(2)}</span>
                            </div>`
                        ).join('');
                    }
                    _posDdProd();
                    ddProd.classList.remove('d-none');
                } catch (e) { console.error(e); }
            }, 280);
        });
        document.addEventListener('click', e => {
            if (!inpDesc.contains(e.target) && !ddProd.contains(e.target)) ddProd.classList.add('d-none');
        });

        tr.querySelector('.input-cantidad').addEventListener('input', () => _recalcFilaFactura(tr));
        tr.querySelector('.input-precio').addEventListener('input', () => _recalcFilaFactura(tr));

        return tr;
    }

    window._cpfSelProd = (el, p) => {
        const dd = el.closest('.list-group');
        const tr = dd?._cpfTr;
        if (!tr) return;

        tr.querySelector('.input-id-producto').value = p.id || '';
        tr.querySelector('.input-codigo').value      = p.codigo || '';
        tr.querySelector('.input-descripcion').value = p.nombre || '';
        tr.querySelector('.input-id-tarifa').value   = p.tarifa_iva || '';
        // Solo prefilla el precio si la línea aún no tiene uno (se conserva el valor
        // tomado de la cotización o el que el usuario ya haya editado).
        const inpPrecio = tr.querySelector('.input-precio');
        if (!parseFloat(inpPrecio.value || 0)) {
            inpPrecio.value = parseFloat(p.precio_base || 0).toFixed(2);
        }

        dd?.classList.add('d-none');
        _recalcFilaFactura(tr);
        tr.querySelector('.input-cantidad')?.focus();
    };

    function _recalcFilaFactura(tr) {
        const cant   = parseFloat(tr.querySelector('.input-cantidad').value || 0);
        const precio = parseFloat(tr.querySelector('.input-precio').value || 0);
        tr.querySelector('.subtotal-line').textContent = (cant * precio).toFixed(2);
        _calcularTotalesFactura();
    }

    function _calcularTotalesFactura() {
        let subtotal = 0, iva = 0;
        document.querySelectorAll('#cpf_tbodyDetalle .row-detalle-factura').forEach(tr => {
            const cant   = parseFloat(tr.querySelector('.input-cantidad').value || 0);
            const precio = parseFloat(tr.querySelector('.input-precio').value || 0);
            const idTar  = parseInt(tr.querySelector('.input-id-tarifa').value || 0);
            const linea  = cant * precio;
            subtotal += linea;
            const t = tivas().find(x => parseInt(x.id) === idTar);
            if (t) iva += linea * (parseFloat(t.porcentaje_iva || 0) / 100);
        });
        $id('cpf_subtotal').textContent = fmt2(subtotal);
        $id('cpf_iva').textContent      = fmt2(iva);
        $id('cpf_total').textContent    = fmt2(subtotal + iva);
    }

    /* ── API pública ─────────────────────────────────────────── */
    const CP = {
        recalcularTotales() { _calcularTotales(); },

        nueva() {
            _reset();
            _initClienteBuscar();
            getModal().show();
            if (typeof window.aplicarFavoritosModal === 'function') {
                window.aplicarFavoritosModal('#modalCotizacionPublicidad');
            }
        },

        async verDetalle(id) {
            _reset();
            _initClienteBuscar();
            await _cargarCotizacion(id);
            getModal().show();
        },

        async guardar() {
            const payload = _buildPayload();

            if (!payload.fecha_emision) { toast('Ingrese la fecha de emisión', 'error'); return; }
            if (!payload.id_cliente)    { toast('Seleccione un cliente', 'error'); return; }
            if (!payload.id_tarifa_iva) { toast('Seleccione la tarifa de IVA', 'error'); return; }
            if (!payload.detalles.length) { toast('Agregue al menos un ítem', 'error'); return; }

            const btn = $id('cp_btnGuardar');
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...'; }

            try {
                const data = await (await fetch(`${urlBase()}/guardarAjax`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                })).json();

                if (!data.ok) { toast(data.error || 'Error al guardar', 'error'); return; }

                toast(data.msg || 'Guardado correctamente');
                getModal().hide();
                if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
            } catch (e) {
                console.error(e);
                toast('Error de conexión', 'error');
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar'; }
            }
        },

        async nuevaVersion() {
            const id = $id('cp_id').value;
            if (!id) return;
            if (!(await confirm2('¿Crear una nueva versión de esta cotización?'))) return;
            try {
                const fd = new FormData();
                fd.append('id', id);
                const data = await (await fetch(`${urlBase()}/nuevaVersionAjax`, { method: 'POST', body: fd })).json();
                if (!data.ok) { toast(data.error || 'Error', 'error'); return; }
                toast('Nueva versión creada');
                getModal().hide();
                if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
            } catch (e) { console.error(e); toast('Error de conexión', 'error'); }
        },

        async cambiarEstado(estado) {
            const id = $id('cp_id').value;
            if (!id) return;
            const msgs = {
                aprobada:  '¿Aprobar esta cotización?',
                rechazada: '¿Rechazar esta cotización?',
                anulada:   '¿Anular esta cotización? Esta acción no se puede deshacer.',
            };
            if (!(await confirm2(msgs[estado] || `¿Cambiar estado a "${estado}"?`))) return;

            try {
                const fd = new FormData();
                fd.append('id', id); fd.append('estado', estado);
                const data = await (await fetch(`${urlBase()}/cambiarEstadoAjax`, { method: 'POST', body: fd })).json();
                if (!data.ok) { toast(data.error || 'Error', 'error'); return; }
                toast('Estado actualizado');
                getModal().hide();
                if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
            } catch (e) { console.error(e); toast('Error de conexión', 'error'); }
        },

        async eliminar() {
            const id = $id('cp_id').value;
            if (!id) return;
            if (!(await confirm2('¿Eliminar esta cotización?'))) return;
            try {
                const fd = new FormData();
                fd.append('id', id);
                const data = await (await fetch(`${urlBase()}/eliminarAjax`, { method: 'POST', body: fd })).json();
                if (!data.ok) { toast(data.error || 'Error al eliminar', 'error'); return; }
                toast('Cotización eliminada');
                getModal().hide();
                if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
            } catch (e) { console.error(e); toast('Error de conexión', 'error'); }
        },

        exportarPdf() {
            const id = $id('cp_id').value;
            if (!id) return;
            window.open(`${urlBase()}/exportarPdfAjax?id=${id}`, '_blank');
        },

        /* ── Generar Factura (modal exclusivo) ────────────────── */
        async abrirGenerarFactura() {
            const id = $id('cp_id').value;
            if (!id) return;
            try {
                const data = await (await fetch(`${urlBase()}/getDatosFacturaAjax?id=${id}`)).json();
                if (!data.ok) { toast(data.error || 'Error al cargar datos', 'error'); return; }
                if (!data.puede_generar) {
                    Swal.fire({ icon: 'warning', title: 'No se puede generar', text: data.mensaje || 'No se puede generar la factura.' });
                    return;
                }

                $id('cpf_idCotizacion').value = id;
                $id('cpf_fecha').value = new Date().toISOString().slice(0, 10);

                _cpfBloquearSecuencial = false;
                const selPunto = $id('cpf_punto');
                if (selPunto) {
                    selPunto.selectedIndex = 0;
                    _syncEstabFactura(selPunto);
                    if (selPunto.value) _cargarSecuencialFactura(selPunto.value);
                }

                _limpiarClienteFactura();
                if (data.cotizacion?.id_cliente) {
                    _seleccionarClienteFactura({
                        id: data.cotizacion.id_cliente,
                        nombre: data.cotizacion.cliente_nombre,
                        identificacion: data.cotizacion.cliente_ruc,
                    });
                }

                const tbody = $id('cpf_tbodyDetalle');
                tbody.innerHTML = '';
                tbody.appendChild(_crearFilaFactura({ precio_unitario: data.cotizacion?.total_sin_impuestos || 0 }));
                _calcularTotalesFactura();

                let m = bootstrap.Modal.getInstance($id('cp_modalGenerarFactura'));
                if (!m) m = new bootstrap.Modal($id('cp_modalGenerarFactura'));
                m.show();
            } catch (e) { console.error(e); toast('Error de conexión', 'error'); }
        },

        agregarFilaFactura() {
            const tbody = $id('cpf_tbodyDetalle');
            if (!tbody) return;
            const tr = _crearFilaFactura();
            tbody.appendChild(tr);
            _calcularTotalesFactura();
        },

        eliminarFilaFactura(btn) {
            const tr = btn.closest('tr');
            if (tr?._cpfDdProd) tr._cpfDdProd.remove();
            tr?.remove();
            _calcularTotalesFactura();
        },

        async generarFactura() {
            const idCotizacion = $id('cpf_idCotizacion').value;
            const selPunto = $id('cpf_punto');
            const optPunto = selPunto?.selectedOptions[0];

            const detalles = [];
            document.querySelectorAll('#cpf_tbodyDetalle .row-detalle-factura').forEach(tr => {
                const descripcion = tr.querySelector('.input-descripcion').value.trim();
                if (!descripcion) return;
                detalles.push({
                    id_producto:      tr.querySelector('.input-id-producto').value || null,
                    codigo_principal: tr.querySelector('.input-codigo').value || '',
                    descripcion,
                    cantidad:         parseFloat(tr.querySelector('.input-cantidad').value || 0),
                    precio_unitario:  parseFloat(tr.querySelector('.input-precio').value || 0),
                    id_tarifa_iva:    tr.querySelector('.input-id-tarifa').value || 0,
                });
            });

            const payload = {
                id:                  idCotizacion,
                fecha_emision:       $id('cpf_fecha').value,
                id_establecimiento:  optPunto?.dataset.est || '',
                id_punto_emision:    selPunto?.value || '',
                establecimiento:     optPunto?.dataset.codEst   || '',
                punto_emision:       optPunto?.dataset.codPunto || '',
                secuencial:          $id('cpf_secuencial').value.trim(),
                id_cliente:          $id('cpf_idCliente').value,
                detalles,
            };

            if (!payload.id_cliente)   { toast('Seleccione un cliente', 'error'); return; }
            if (!payload.secuencial)   { toast('No se pudo cargar el secuencial', 'error'); return; }
            if (!detalles.length)      { toast('Agregue al menos un producto', 'error'); return; }
            if (detalles.some(d => !d.id_producto)) { toast('Seleccione un producto del catálogo en cada línea', 'error'); return; }

            const btn = $id('cpf_btnGenerar');
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generando...'; }

            try {
                const data = await (await fetch(`${urlBase()}/convertirAFacturaAjax`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                })).json();

                if (!data.ok) { toast(data.error || 'No se pudo generar la factura', 'error'); return; }

                const badge = $id('cp_estadoBadge');
                if (badge) {
                    badge.className = BADGE_CLASSES.convertida;
                    badge.textContent = BADGE_LABELS.convertida;
                    badge.classList.remove('d-none');
                }
                bootstrap.Modal.getInstance($id('cp_modalGenerarFactura'))?.hide();
                _cargarFacturas(idCotizacion);
                if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);

                Swal.fire({
                    icon: 'success',
                    title: 'Factura generada',
                    text: 'Se ha generado una factura de venta para que sea revisada y autorizada.',
                    confirmButtonText: 'Aceptar'
                });
            } catch (e) {
                console.error(e);
                toast('Error de conexión', 'error');
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Generar Factura'; }
            }
        },

        async guardarCostos() {
            const id = $id('cp_id').value;
            if (!id) return;
            const costos = [];
            document.querySelectorAll('#cp_tbodyCostos .row-costo').forEach(tr => {
                costos.push({
                    id_detalle: parseInt(tr.dataset.idDetalle || 0),
                    id_proveedor: tr.querySelector('.input-id-proveedor').value || null,
                    id_compra: tr.querySelector('.input-id-compra').value || null,
                    factura_proveedor: tr.querySelector('.input-factura-proveedor').value.trim(),
                    valor_costo: parseFloat(tr.querySelector('.input-valor-costo').value || 0),
                    observacion_costo: tr.querySelector('.input-observacion-costo').value.trim(),
                });
            });

            const btn = $id('cp_btnGuardarCostos');
            if (btn) { btn.disabled = true; }
            try {
                const data = await (await fetch(`${urlBase()}/guardarCostosAjax`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, costos }),
                })).json();
                if (!data.ok) { toast(data.error || 'Error al guardar costos', 'error'); return; }
                toast('Costos guardados correctamente');

                // Recargar cabecera+detalles+costos para reflejar en Resumen lo
                // realmente persistido (filas vacías no se guardan en el servidor).
                try {
                    const fresh = await (await fetch(`${urlBase()}/getCotizacionAjax?id=${id}`)).json();
                    if (fresh.ok) {
                        _renderCostosTab(fresh.detalles || [], fresh.costos || []);
                        _renderResumenTab(fresh.cabecera, fresh.detalles || [], fresh.costos || []);
                    } else {
                        _recalcularUtilidad();
                    }
                } catch (e) { _recalcularUtilidad(); }
            } catch (e) {
                console.error(e);
                toast('Error de conexión', 'error');
            } finally {
                if (btn) btn.disabled = false;
            }
        },

        agregarLineaCosto(idDetalle) {
            const ancla = _grupoUltimaFilaCosto(idDetalle);
            if (!ancla) return;
            const tr = _crearFilaCosto({}, idDetalle);
            ancla.insertAdjacentElement('afterend', tr);
        },

        eliminarFilaCosto(btn) {
            const tr = btn.closest('tr');
            if (tr?._cpDdProv) tr._cpDdProv.remove();
            if (tr?._cpDdFact) tr._cpDdFact.remove();
            tr?.remove();
            _recalcularUtilidad();
        },

        _recalcularUtilidad() { _recalcularUtilidad(); },

        agregarFila() {
            const tbody = $id('cp_tbodyDetalle');
            if (!tbody) return;
            const tr = _crearFila();
            tbody.appendChild(tr);
            _calcularTotales();
            tr.querySelector('.input-descripcion').focus();
        },

        eliminarFila(btn) {
            btn.closest('tr')?.remove();
            _calcularTotales();
        },

        /* ── Gestión de categorías ────────────────────────────── */
        abrirCategorias() {
            let m = bootstrap.Modal.getInstance($id('cp_modalCategorias'));
            if (!m) m = new bootstrap.Modal($id('cp_modalCategorias'));
            _renderCategorias();
            m.show();
        },

        async agregarCategoria() {
            const inp = $id('cp_nuevaCategoria');
            const nombre = (inp?.value || '').trim();
            if (!nombre) { toast('Ingrese el nombre de la categoría', 'error'); return; }
            try {
                const fd = new FormData();
                fd.append('nombre', nombre);
                const data = await (await fetch(`${urlBase()}/guardarCategoriaAjax`, { method: 'POST', body: fd })).json();
                if (!data.ok) { toast(data.error || 'Error al guardar', 'error'); return; }
                CFG().categorias = CFG().categorias || [];
                CFG().categorias.push({ id: data.id, nombre: data.nombre });
                CFG().categorias.sort((a, b) => a.nombre.localeCompare(b.nombre));
                inp.value = '';
                _renderCategorias();
                _refrescarSelectsCategoria();
                toast('Categoría agregada');
            } catch (e) { console.error(e); toast('Error de conexión', 'error'); }
        },

        async eliminarCategoria(id) {
            if (!(await confirm2('¿Eliminar esta categoría?'))) return;
            try {
                const fd = new FormData();
                fd.append('id', id);
                const data = await (await fetch(`${urlBase()}/eliminarCategoriaAjax`, { method: 'POST', body: fd })).json();
                if (!data.ok) { toast(data.error || 'No se pudo eliminar', 'error'); return; }
                CFG().categorias = (CFG().categorias || []).filter(c => parseInt(c.id) !== parseInt(id));
                _renderCategorias();
                _refrescarSelectsCategoria();
                toast('Categoría eliminada');
            } catch (e) { console.error(e); toast('Error de conexión', 'error'); }
        },
    };

    function _renderCategorias() {
        const cont = $id('cp_listaCategorias');
        if (!cont) return;
        const lista = categorias();
        if (!lista.length) {
            cont.innerHTML = '<div class="text-center text-muted small py-3">Sin categorías registradas</div>';
            return;
        }
        cont.innerHTML = lista.map(c => `
            <div class="list-group-item d-flex justify-content-between align-items-center py-1 px-2">
                <span class="small">${_esc(c.nombre)}</span>
                ${perm().eliminar ? `<button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="CP.eliminarCategoria(${c.id})" title="Eliminar">
                    <i class="bi bi-trash3"></i>
                </button>` : ''}
            </div>`).join('');
    }

    function _refrescarSelectsCategoria() {
        document.querySelectorAll('#cp_tbodyDetalle .input-categoria').forEach(sel => {
            const actual = sel.value;
            sel.innerHTML = _categoriaOpts(actual);
        });
    }

    document.addEventListener('shown.bs.modal', e => {
        if (e.target.id !== 'modalCotizacionPublicidad') return;
        if (!$id('cp_id')?.value) {
            $id('cp_clienteBuscar')?.focus();
        } else {
            document.querySelector('#cp_tbodyDetalle .input-descripcion')?.focus();
        }
    });

    // Limpiar dropdowns anclados a <body> (proveedores/productos) al cerrar los modales.
    document.addEventListener('hidden.bs.modal', e => {
        if (e.target.id === 'modalCotizacionPublicidad') {
            document.querySelectorAll('#cp_tbodyCostos .row-costo').forEach(tr => {
                if (tr._cpDdProv) { tr._cpDdProv.remove(); tr._cpDdProv = null; }
                if (tr._cpDdFact) { tr._cpDdFact.remove(); tr._cpDdFact = null; }
            });
        }
        if (e.target.id === 'cp_modalGenerarFactura') {
            document.querySelectorAll('#cpf_tbodyDetalle .row-detalle-factura').forEach(tr => {
                if (tr._cpfDdProd) { tr._cpfDdProd.remove(); tr._cpfDdProd = null; }
            });
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        _initClienteBuscarFactura();
        const selPunto = $id('cpf_punto');
        if (selPunto) {
            selPunto.addEventListener('change', () => {
                _syncEstabFactura(selPunto);
                _cargarSecuencialFactura(selPunto.value);
            });
        }
    });

    window.CP = CP;
})();
