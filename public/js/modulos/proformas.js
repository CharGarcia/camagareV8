/**
 * Módulo Proformas - JS principal
 * Gestión completa de proformas: crear, editar, aprobar, rechazar, convertir a factura.
 */
'use strict';

const PF = (() => {
    // ─── Estado interno ──────────────────────────────────────────────────────
    let _idActual = 0;
    let _estadoActual = '';
    let _filaCount = 0;
    let _paginaActual = 1;
    let _buscarActual = '';
    let _clienteTimer = null;
    let _productoTimer = null;
    let _filaProductoBuscando = null;

    const URL = PF_CONFIG.urlBase;

    // ─── Inicialización ───────────────────────────────────────────────────────
    function init() {
        const inputBuscar = document.getElementById('pf-buscar');
        if (inputBuscar) {
            inputBuscar.addEventListener('keyup', (e) => {
                clearTimeout(_clienteTimer);
                _clienteTimer = setTimeout(() => {
                    _buscarActual = inputBuscar.value.trim();
                    _paginaActual = 1;
                    _recargarTabla();
                }, 380);
                if (e.key === 'Enter') {
                    clearTimeout(_clienteTimer);
                    _buscarActual = inputBuscar.value.trim();
                    _paginaActual = 1;
                    _recargarTabla();
                }
            });
        }

        // Cambio de establecimiento → cargar puntos
        document.getElementById('pf-establecimiento')?.addEventListener('change', function () {
            _cargarPuntos(this.value);
        });

        // Cambio de punto → obtener secuencial
        document.getElementById('pf-punto')?.addEventListener('change', function () {
            if (this.value && _idActual === 0) {
                _obtenerSecuencial(this.value);
            }
        });

        // Buscador de cliente
        const inputCliente = document.getElementById('pf-cliente-buscar');
        if (inputCliente) {
            inputCliente.addEventListener('input', function () {
                clearTimeout(_clienteTimer);
                _clienteTimer = setTimeout(() => _buscarCliente(this.value), 320);
            });
            inputCliente.addEventListener('blur', () => {
                setTimeout(() => {
                    const dd = document.getElementById('pf-dropdown-clientes');
                    if (dd) dd.classList.add('d-none');
                }, 200);
            });
        }
    }

    // ─── CRUD Modal ───────────────────────────────────────────────────────────
    function nueva() {
        _idActual = 0;
        _estadoActual = 'borrador';
        _filaCount = 0;

        _resetModal();
        document.getElementById('pf-modal-titulo').textContent = 'Nueva Proforma';
        document.getElementById('pf-fecha').value = _hoy();

        _setModoEdicion(true);
        _actualizarBotonesEstado();

        // Auto-obtener secuencial si ya hay punto seleccionado
        const ptoEl = document.getElementById('pf-punto');
        if (ptoEl?.value) _obtenerSecuencial(ptoEl.value);

        new bootstrap.Modal(document.getElementById('modalProforma')).show();
    }

    function verDetalle(id) {
        fetch(`${URL}/getProformaAjax?id=${id}`)
            .then(r => r.json())
            .then(res => {
                if (!res.ok) return _toast(res.mensaje || 'Error al cargar proforma', 'danger');
                const c = res.cabecera;
                _idActual     = c.id;
                _estadoActual = c.estado;
                _filaCount    = 0;

                _resetModal();
                document.getElementById('pf-modal-titulo').textContent = `Proforma ${c.establecimiento}-${c.punto_emision}-${c.secuencial}`;

                // Cabecera
                _setSelect('pf-establecimiento', c.id_establecimiento);
                _cargarPuntos(c.id_establecimiento, c.id_punto_emision);
                document.getElementById('pf-secuencial').value      = c.secuencial;
                document.getElementById('pf-fecha').value           = c.fecha_emision;
                document.getElementById('pf-dias-vigencia').value   = c.dias_vigencia;
                document.getElementById('pf-vendedor').value        = c.id_vendedor ?? '';
                document.getElementById('pf-observaciones').value   = c.observaciones ?? '';
                document.getElementById('pf-id-cliente').value      = c.id_cliente;
                document.getElementById('pf-cliente-buscar').value  = `${c.cliente_nombre} — ${c.cliente_ruc}`;
                document.getElementById('pf-cliente-nombre-sel').textContent = c.cliente_nombre;
                document.getElementById('pf-cliente-ruc-sel').textContent    = c.cliente_ruc;
                document.getElementById('pf-cliente-info').classList.remove('d-none');

                // Detalles
                const tbody = document.getElementById('pf-detalles-tbody');
                tbody.innerHTML = '';
                (res.detalles || []).forEach(d => _agregarFilaConDatos(d));

                // Info adicional
                const adicTbody = document.getElementById('pf-adicional-tbody');
                adicTbody.innerHTML = '';
                (res.info_adicional || []).forEach(a => _agregarFilaAdicionalConDatos(a.nombre, a.valor));

                _calcularTotales();

                // Modo según estado
                const editable = ['borrador'].includes(c.estado);
                _setModoEdicion(editable);
                _actualizarBotonesEstado();
                _setBadgeEstado(c.estado);

                // Botón PDF
                const btnPdf = document.getElementById('btn-pdf-proforma');
                if (btnPdf) btnPdf.classList.remove('d-none');

                // Botón convertir (solo si aprobada o borrador y tiene permiso)
                const btnConv = document.getElementById('btn-convertir-factura');
                if (btnConv) {
                    if (['borrador', 'aprobada'].includes(c.estado) && PF_CONFIG.perm.crear) {
                        btnConv.classList.remove('d-none');
                    } else {
                        btnConv.classList.add('d-none');
                    }
                }

                new bootstrap.Modal(document.getElementById('modalProforma')).show();
            })
            .catch(() => _toast('Error de conexión', 'danger'));
    }

    function guardar() {
        const idCliente = document.getElementById('pf-id-cliente').value;
        if (!idCliente) { _toast('Debe seleccionar un cliente.', 'warning'); return; }

        const idEstab = document.getElementById('pf-establecimiento').value;
        if (!idEstab) { _toast('Debe seleccionar un establecimiento.', 'warning'); return; }

        const idPunto = document.getElementById('pf-punto').value;
        if (!idPunto) { _toast('Debe seleccionar un punto de emisión.', 'warning'); return; }

        const secuencial = document.getElementById('pf-secuencial').value.trim();
        if (!secuencial) { _toast('El secuencial es obligatorio.', 'warning'); return; }

        const detalles = _recolectarDetalles();
        if (detalles.length === 0) { _toast('Debe agregar al menos un ítem.', 'warning'); return; }

        const estEl = document.getElementById('pf-establecimiento');
        const ptoEl = document.getElementById('pf-punto');
        const estCodigo = estEl.selectedOptions[0]?.dataset?.codigo ?? '';
        const ptoCodigo = ptoEl.selectedOptions[0]?.dataset?.codigo ?? '';

        const totales = _calcularTotalesValores();

        const payload = {
            id:                    _idActual || undefined,
            id_establecimiento:    idEstab,
            id_punto_emision:      idPunto,
            establecimiento:       estCodigo,
            punto_emision:         ptoCodigo,
            secuencial:            secuencial.padStart(9, '0'),
            id_cliente:            idCliente,
            id_vendedor:           document.getElementById('pf-vendedor').value || null,
            fecha_emision:         document.getElementById('pf-fecha').value,
            dias_vigencia:         parseInt(document.getElementById('pf-dias-vigencia').value) || 15,
            observaciones:         document.getElementById('pf-observaciones').value.trim() || null,
            estado:                _idActual ? _estadoActual : 'borrador',
            total_sin_impuestos:   totales.subtotal,
            total_descuento:       totales.descuento,
            total_ice:             totales.ice,
            importe_total:         totales.total,
            detalles:              detalles,
            info_adicional:        _recolectarAdicional(),
        };

        const btn = document.getElementById('btn-guardar-proforma');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

        fetch(`${URL}/guardarAjax`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save me-1"></i>Guardar';
            if (!res.ok) return _toast(res.error || 'Error al guardar', 'danger');

            _toast(res.msg || 'Guardado correctamente.', 'success');
            _actualizarFila(res.id, res.rowHtml);
            _idActual = res.id;
            _estadoActual = 'borrador';
            _actualizarBotonesEstado();

            // Recargar para actualizar badge total
            _recargarTabla();
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save me-1"></i>Guardar';
            _toast('Error de conexión', 'danger');
        });
    }

    function cambiarEstado(nuevoEstado) {
        if (!_idActual) return;
        const labels = { aprobada: 'aprobar', rechazada: 'rechazar', anulada: 'anular' };
        const label = labels[nuevoEstado] || nuevoEstado;
        if (!confirm(`¿Desea ${label} esta proforma?`)) return;

        const fd = new FormData();
        fd.append('id', _idActual);
        fd.append('estado', nuevoEstado);

        fetch(`${URL}/cambiarEstadoAjax`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (!res.ok) return _toast(res.error || 'Error', 'danger');
                _estadoActual = nuevoEstado;
                _toast('Estado actualizado.', 'success');
                _setBadgeEstado(nuevoEstado);
                _actualizarBotonesEstado();
                _setModoEdicion(nuevoEstado === 'borrador');
                if (res.rowHtml) _actualizarFila(_idActual, res.rowHtml);
                _recargarTabla();

                // Ocultar botón convertir si ya no es relevante
                const btnConv = document.getElementById('btn-convertir-factura');
                if (btnConv) {
                    if (!['borrador', 'aprobada'].includes(nuevoEstado)) {
                        btnConv.classList.add('d-none');
                    }
                }
            })
            .catch(() => _toast('Error de conexión', 'danger'));
    }

    function eliminar() {
        if (!_idActual) return;
        if (!confirm('¿Desea eliminar esta proforma? Esta acción no se puede deshacer.')) return;

        const fd = new FormData();
        fd.append('id', _idActual);

        fetch(`${URL}/eliminarAjax`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (!res.ok) return _toast(res.error || 'Error al eliminar', 'danger');
                _toast('Proforma eliminada.', 'success');
                bootstrap.Modal.getInstance(document.getElementById('modalProforma'))?.hide();
                _recargarTabla();
            })
            .catch(() => _toast('Error de conexión', 'danger'));
    }

    function exportarPdf() {
        if (!_idActual) return;
        window.open(`${URL}/exportarPdfAjax?id=${_idActual}`, '_blank');
    }

    function convertirAFactura() {
        if (!_idActual) return;
        if (!confirm('¿Desea convertir esta proforma en una factura de venta?\n\nSe abrirá el formulario de ventas con los datos pre-cargados.')) return;

        fetch(`${URL}/convertirAFacturaAjax?id=${_idActual}`)
            .then(r => r.json())
            .then(res => {
                if (!res.ok) return _toast(res.error || 'Error', 'danger');
                // Guardar datos en sessionStorage para pre-llenar el formulario de ventas
                const prefillKey = `ventas_prefill_proforma`;
                sessionStorage.setItem(prefillKey, JSON.stringify({
                    id_proforma:    _idActual,
                    proforma:       res.data.proforma,
                    detalles:       res.data.detalles,
                    info_adicional: res.data.info_adicional,
                }));
                // Abrir ventas en nueva pestaña
                window.open(`${PF_CONFIG.baseUrl}/modulos/factura-venta`, '_blank');
            })
            .catch(() => _toast('Error de conexión', 'danger'));
    }

    // ─── Tabla de detalles ────────────────────────────────────────────────────
    function agregarFila() {
        const filaId = ++_filaCount;
        const tbody  = document.getElementById('pf-detalles-tbody');
        const ivaDefault = PF_CONFIG.tarifasIva.find(t => t.codigo === '4' || t.porcentaje == 15) ??
                           PF_CONFIG.tarifasIva[0] ?? { id: 0, codigo: '4', porcentaje: 15 };

        const tr = document.createElement('tr');
        tr.className = 'row-detalle';
        tr.dataset.fila = filaId;
        tr.innerHTML = _htmlFila(filaId, {}, ivaDefault);
        tbody.appendChild(tr);

        // Focus en la descripción
        const descInput = tr.querySelector(`#pf-desc-${filaId}`);
        if (descInput) {
            descInput.focus();
            // Búsqueda de producto al escribir
            descInput.addEventListener('input', function () {
                clearTimeout(_productoTimer);
                _productoTimer = setTimeout(() => {
                    if (this.value.trim().length >= 2) {
                        _filaProductoBuscando = filaId;
                        _buscarProductoDropdown(this.value.trim(), filaId);
                    } else {
                        _cerrarDropdownProducto(filaId);
                    }
                }, 300);
            });
        }

        _bindEventsFila(tr);
        _calcularTotales();
    }

    function _agregarFilaConDatos(d) {
        const filaId = ++_filaCount;
        const tbody  = document.getElementById('pf-detalles-tbody');
        const iva    = PF_CONFIG.tarifasIva.find(t => parseInt(t.id) === parseInt(d.id_tarifa_iva || 0)) ??
                       PF_CONFIG.tarifasIva.find(t => t.codigo === '4' || t.porcentaje == 15) ??
                       PF_CONFIG.tarifasIva[0] ?? { id: 0, codigo: '4', porcentaje: 15 };

        const tr = document.createElement('tr');
        tr.className = 'row-detalle';
        tr.dataset.fila = filaId;
        tr.dataset.idProducto = d.id_producto ?? '';
        tr.innerHTML = _htmlFila(filaId, d, iva);
        tbody.appendChild(tr);
        _bindEventsFila(tr);
    }

    function _htmlFila(filaId, d, iva) {
        const opcIva = PF_CONFIG.tarifasIva.map(t =>
            `<option value="${t.id}" data-codigo="${t.codigo}" data-pct="${t.porcentaje}" ${t.id == (d.id_tarifa_iva ?? iva.id) ? 'selected' : ''}>${t.porcentaje}%</option>`
        ).join('');

        return `
        <td class="p-0 ps-1 text-muted small align-middle">${filaId}</td>
        <td class="p-0">
            <input type="text" class="input-detalle w-100" id="pf-codigo-${filaId}"
                placeholder="Código" value="${_esc(d.codigo_principal ?? '')}"
                style="padding:0 4px;height:20px;font-size:0.78rem;">
            <input type="hidden" class="pf-id-producto" value="${d.id_producto ?? ''}">
            <input type="hidden" class="pf-id-unidad-medida" value="${d.id_unidad_medida ?? ''}">
        </td>
        <td class="p-0 position-relative">
            <input type="text" class="input-detalle w-100" id="pf-desc-${filaId}"
                placeholder="Descripción del producto o servicio" value="${_esc(d.descripcion ?? '')}"
                style="padding:0 4px;height:20px;font-size:0.78rem;">
            <div class="dropdown-productos d-none" id="pf-dd-prod-${filaId}"></div>
        </td>
        <td class="p-0">
            <input type="number" class="input-detalle w-100 pf-cantidad text-end" id="pf-cant-${filaId}"
                value="${parseFloat(d.cantidad ?? 1).toFixed(2)}" min="0.01" step="0.01"
                style="padding:0 4px;height:20px;font-size:0.78rem;">
        </td>
        <td class="p-0">
            <input type="number" class="input-detalle w-100 pf-precio text-end" id="pf-precio-${filaId}"
                value="${parseFloat(d.precio_unitario ?? 0).toFixed(4)}" min="0" step="0.0001"
                style="padding:0 4px;height:20px;font-size:0.78rem;">
        </td>
        <td class="p-0">
            <input type="number" class="input-detalle w-100 pf-descuento text-end" id="pf-desc-d-${filaId}"
                value="${parseFloat(d.descuento ?? 0).toFixed(2)}" min="0" step="0.01"
                style="padding:0 4px;height:20px;font-size:0.78rem;">
        </td>
        <td class="p-0">
            <select class="input-detalle w-100 pf-iva" id="pf-iva-${filaId}"
                style="padding:0 4px;height:20px;font-size:0.78rem;">${opcIva}</select>
        </td>
        <td class="p-0 text-end pe-1 align-middle fw-semibold small pf-subtotal-fila" id="pf-sub-${filaId}">
            $${parseFloat(d.precio_total_sin_impuesto ?? 0).toFixed(2)}
        </td>
        <td class="p-0 text-center align-middle">
            <button type="button" class="btn btn-link btn-sm p-0 remove-row" onclick="PF.eliminarFila(this)" title="Quitar">
                <i class="bi bi-x-circle text-danger"></i>
            </button>
        </td>`;
    }

    function _bindEventsFila(tr) {
        tr.querySelectorAll('.pf-cantidad, .pf-precio, .pf-descuento, .pf-iva').forEach(el => {
            el.addEventListener('change', _calcularTotales);
            el.addEventListener('input',  _calcularTotales);
        });

        // Búsqueda de producto en campo descripción si es nueva fila
        const filaId = tr.dataset.fila;
        const descEl = tr.querySelector(`#pf-desc-${filaId}`);
        if (descEl) {
            descEl.addEventListener('input', function () {
                clearTimeout(_productoTimer);
                _productoTimer = setTimeout(() => {
                    if (this.value.trim().length >= 2) {
                        _filaProductoBuscando = filaId;
                        _buscarProductoDropdown(this.value.trim(), filaId);
                    } else {
                        _cerrarDropdownProducto(filaId);
                    }
                }, 300);
            });
            descEl.addEventListener('blur', () => {
                setTimeout(() => _cerrarDropdownProducto(filaId), 200);
            });
        }
    }

    function eliminarFila(btn) {
        btn.closest('tr').remove();
        _calcularTotales();
    }

    // ─── Búsqueda de producto ─────────────────────────────────────────────────
    function _buscarProductoDropdown(q, filaId) {
        fetch(`${URL}/getProductosAjax?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(res => {
                const dd = document.getElementById(`pf-dd-prod-${filaId}`);
                if (!dd) return;
                if (!res.ok || !res.data?.length) {
                    dd.classList.add('d-none');
                    return;
                }
                dd.innerHTML = res.data.map(p =>
                    `<div class="dropdown-item-producto" data-id="${p.id}"
                        data-codigo="${_esc(p.codigo ?? '')}"
                        data-nombre="${_esc(p.nombre ?? '')}"
                        data-precio="${parseFloat(p.precio_venta ?? 0).toFixed(4)}"
                        data-iva-id="${p.id_tarifa_iva ?? 0}"
                        data-unidad="${p.id_medida ?? ''}"
                        onmousedown="PF._selProducto(this,'${filaId}')">
                        <div class="fw-semibold">${_esc(p.nombre)}</div>
                        <div class="text-muted small">${_esc(p.codigo ?? '')} — $${parseFloat(p.precio_venta ?? 0).toFixed(2)}</div>
                    </div>`
                ).join('');
                dd.classList.remove('d-none');
            })
            .catch(() => {});
    }

    function _selProducto(el, filaId) {
        const tr  = document.querySelector(`[data-fila="${filaId}"]`);
        if (!tr) return;
        tr.querySelector('.pf-id-producto').value          = el.dataset.id;
        tr.querySelector('.pf-id-unidad-medida').value     = el.dataset.unidad;
        tr.querySelector(`#pf-codigo-${filaId}`).value     = el.dataset.codigo;
        tr.querySelector(`#pf-desc-${filaId}`).value       = el.dataset.nombre;
        tr.querySelector(`#pf-precio-${filaId}`).value     = parseFloat(el.dataset.precio).toFixed(4);

        // Setear IVA si hay dato
        const ivaId = parseInt(el.dataset.ivaId ?? 0);
        const ivaSelect = tr.querySelector(`#pf-iva-${filaId}`);
        if (ivaSelect && ivaId) {
            const opt = Array.from(ivaSelect.options).find(o => parseInt(o.value) === ivaId);
            if (opt) ivaSelect.value = ivaId;
        }

        _cerrarDropdownProducto(filaId);
        _calcularTotales();
    }

    function _cerrarDropdownProducto(filaId) {
        document.getElementById(`pf-dd-prod-${filaId}`)?.classList.add('d-none');
    }

    // ─── Búsqueda de cliente ──────────────────────────────────────────────────
    function _buscarCliente(q) {
        if (q.length < 2) return;
        fetch(`${URL}/getClientesAjax?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(res => {
                const dd = document.getElementById('pf-dropdown-clientes');
                if (!dd) return;
                if (!res.ok || !res.data?.length) { dd.classList.add('d-none'); return; }
                dd.innerHTML = res.data.map(c =>
                    `<div class="dropdown-item-producto" onmousedown="PF._selCliente(${JSON.stringify(c).replace(/"/g,'&quot;')})">
                        <div class="fw-semibold">${_esc(c.nombre)}</div>
                        <div class="text-muted small">${_esc(c.identificacion ?? '')} ${c.email ? '— ' + _esc(c.email) : ''}</div>
                    </div>`
                ).join('');
                dd.classList.remove('d-none');
            })
            .catch(() => {});
    }

    function _selCliente(c) {
        document.getElementById('pf-id-cliente').value              = c.id;
        document.getElementById('pf-cliente-buscar').value          = `${c.nombre} — ${c.identificacion}`;
        document.getElementById('pf-cliente-nombre-sel').textContent = c.nombre;
        document.getElementById('pf-cliente-ruc-sel').textContent    = c.identificacion;
        document.getElementById('pf-cliente-info').classList.remove('d-none');
        document.getElementById('pf-dropdown-clientes').classList.add('d-none');
    }

    // ─── Puntos de emisión ────────────────────────────────────────────────────
    function _cargarPuntos(idEstab, idPuntoSeleccionar) {
        if (!idEstab) return;
        fetch(`${URL}/getPuntosEmisionAjax?id_establecimiento=${idEstab}`)
            .then(r => r.json())
            .then(res => {
                const sel = document.getElementById('pf-punto');
                if (!sel) return;
                sel.innerHTML = '<option value="">— Seleccione —</option>';
                (res.data || []).forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.dataset.codigo = p.codigo ?? '';
                    opt.textContent = `${p.codigo} - ${p.nombre}`;
                    if (idPuntoSeleccionar && p.id == idPuntoSeleccionar) opt.selected = true;
                    sel.appendChild(opt);
                });
                if (sel.value && _idActual === 0) _obtenerSecuencial(sel.value);
            })
            .catch(() => {});
    }

    function _obtenerSecuencial(idPunto) {
        fetch(`${URL}/getSecuencialAjax?id_punto_emision=${idPunto}`)
            .then(r => r.json())
            .then(res => {
                if (res.ok && res.formateado) {
                    document.getElementById('pf-secuencial').value = res.formateado;
                }
            })
            .catch(() => {});
    }

    // ─── Cálculo de totales ───────────────────────────────────────────────────
    function _calcularTotales() {
        const t = _calcularTotalesValores();

        document.getElementById('pf-subtotal0').textContent      = `$${t.subtotal0.toFixed(2)}`;
        document.getElementById('pf-subtotal-iva').textContent   = `$${t.subtotalIva.toFixed(2)}`;
        document.getElementById('pf-total-descuento').textContent = `$${t.descuento.toFixed(2)}`;
        document.getElementById('pf-total-iva').textContent      = `$${t.iva.toFixed(2)}`;
        document.getElementById('pf-importe-total').textContent  = `$${t.total.toFixed(2)}`;

        const rowIce = document.getElementById('pf-row-ice');
        if (t.ice > 0) {
            document.getElementById('pf-total-ice').textContent = `$${t.ice.toFixed(2)}`;
            rowIce.classList.remove('d-none');
        } else {
            rowIce.classList.add('d-none');
        }

        // Actualizar subtotales por fila
        document.querySelectorAll('.row-detalle').forEach(tr => {
            const filaId = tr.dataset.fila;
            const cant  = parseFloat(tr.querySelector(`#pf-cant-${filaId}`)?.value ?? 0) || 0;
            const prec  = parseFloat(tr.querySelector(`#pf-precio-${filaId}`)?.value ?? 0) || 0;
            const desc  = parseFloat(tr.querySelector(`#pf-desc-d-${filaId}`)?.value ?? 0) || 0;
            const sub   = Math.max(0, cant * prec - desc);
            const subEl = document.getElementById(`pf-sub-${filaId}`);
            if (subEl) subEl.textContent = `$${sub.toFixed(2)}`;
        });
    }

    function _calcularTotalesValores() {
        let subtotal  = 0, descuento = 0, subtotal0 = 0, subtotalIva = 0, totalIva = 0, totalIce = 0;

        document.querySelectorAll('.row-detalle').forEach(tr => {
            const filaId = tr.dataset.fila;
            const cant  = parseFloat(tr.querySelector(`#pf-cant-${filaId}`)?.value ?? 0) || 0;
            const prec  = parseFloat(tr.querySelector(`#pf-precio-${filaId}`)?.value ?? 0) || 0;
            const desc  = parseFloat(tr.querySelector(`#pf-desc-d-${filaId}`)?.value ?? 0) || 0;
            const sub   = Math.max(0, cant * prec - desc);

            const ivaSelect = tr.querySelector(`#pf-iva-${filaId}`);
            const ivaPct    = parseFloat(ivaSelect?.selectedOptions[0]?.dataset?.pct ?? 0) || 0;
            const ivaCod    = ivaSelect?.selectedOptions[0]?.dataset?.codigo ?? '0';

            subtotal  += sub;
            descuento += desc;

            if (ivaPct === 0 || ivaCod === '0' || ivaCod === '7') {
                subtotal0 += sub;
            } else {
                subtotalIva += sub;
                totalIva    += sub * (ivaPct / 100);
            }
        });

        const total = subtotal + totalIva + totalIce;
        return { subtotal, descuento, subtotal0, subtotalIva, iva: totalIva, ice: totalIce, total };
    }

    // ─── Info adicional ───────────────────────────────────────────────────────
    function agregarAdicional() {
        _agregarFilaAdicionalConDatos('', '');
    }

    function _agregarFilaAdicionalConDatos(nombre, valor) {
        const tbody = document.getElementById('pf-adicional-tbody');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="p-0"><input type="text" class="input-detalle w-100 pf-adic-nombre" value="${_esc(nombre)}" placeholder="Nombre del campo" style="padding:0 4px;height:20px;font-size:0.78rem;"></td>
            <td class="p-0"><input type="text" class="input-detalle w-100 pf-adic-valor" value="${_esc(valor)}" placeholder="Valor" style="padding:0 4px;height:20px;font-size:0.78rem;"></td>
            <td class="p-0 text-center"><button type="button" class="btn btn-link btn-sm p-0 text-danger" onclick="this.closest('tr').remove()"><i class="bi bi-x-circle"></i></button></td>
        `;
        tbody.appendChild(tr);
    }

    function _recolectarAdicional() {
        const result = [];
        document.querySelectorAll('#pf-adicional-tbody tr').forEach(tr => {
            const nombre = tr.querySelector('.pf-adic-nombre')?.value.trim();
            const valor  = tr.querySelector('.pf-adic-valor')?.value.trim();
            if (nombre && valor) result.push({ nombre, valor });
        });
        return result;
    }

    // ─── Recolecar detalles ───────────────────────────────────────────────────
    function _recolectarDetalles() {
        const result = [];
        document.querySelectorAll('#pf-detalles-tbody .row-detalle').forEach(tr => {
            const filaId   = tr.dataset.fila;
            const cant     = parseFloat(tr.querySelector(`#pf-cant-${filaId}`)?.value ?? 0) || 0;
            const precio   = parseFloat(tr.querySelector(`#pf-precio-${filaId}`)?.value ?? 0) || 0;
            const desc     = parseFloat(tr.querySelector(`#pf-desc-d-${filaId}`)?.value ?? 0) || 0;
            const descr    = tr.querySelector(`#pf-desc-${filaId}`)?.value.trim();
            const codigo   = tr.querySelector(`#pf-codigo-${filaId}`)?.value.trim();
            const idProd   = tr.querySelector('.pf-id-producto')?.value;
            const idUM     = tr.querySelector('.pf-id-unidad-medida')?.value;
            const ivaSelect = tr.querySelector(`#pf-iva-${filaId}`);
            const ivaId    = parseInt(ivaSelect?.value ?? 0) || 0;
            const ivaPct   = parseFloat(ivaSelect?.selectedOptions[0]?.dataset?.pct ?? 0) || 0;
            const ivaCod   = ivaSelect?.selectedOptions[0]?.dataset?.codigo ?? '2';

            if (!descr || cant <= 0) return;

            const subtotalSinImp = Math.max(0, cant * precio - desc);
            const valorIva       = subtotalSinImp * (ivaPct / 100);

            const item = {
                id_producto:              idProd || null,
                id_unidad_medida:         idUM || null,
                codigo_principal:         codigo || descr.substring(0, 25),
                descripcion:              descr,
                cantidad:                 cant,
                precio_unitario:          precio,
                descuento:                desc,
                precio_total_sin_impuesto: subtotalSinImp,
                id_tarifa_iva:            ivaId,
                impuestos: [],
            };

            if (ivaPct > 0) {
                item.impuestos.push({
                    codigo_impuesto:   '2',
                    codigo_porcentaje: ivaCod,
                    tarifa:            ivaPct,
                    base_imponible:    subtotalSinImp,
                    valor:             parseFloat(valorIva.toFixed(2)),
                });
            }

            result.push(item);
        });
        return result;
    }

    // ─── Paginación ───────────────────────────────────────────────────────────
    function cambiarPagina(pagina) {
        _paginaActual = pagina;
        _recargarTabla();
    }

    function _recargarTabla() {
        const params = new URLSearchParams({
            b:    _buscarActual,
            page: _paginaActual,
        });
        fetch(`${URL}/searchAjax?${params}`)
            .then(r => r.json())
            .then(res => {
                if (!res.ok) return;
                const tbody = document.getElementById('pf-tbody');
                if (tbody) tbody.innerHTML = res.rows;
                const pag = document.getElementById('pf-pagination');
                if (pag) pag.innerHTML = res.pagination;
                const info = document.getElementById('pf-info');
                if (info) info.textContent = res.info;
                const badge = document.getElementById('pf-total-badge');
                if (badge) badge.textContent = res.total;
            })
            .catch(() => {});
    }

    // ─── Helpers UI ──────────────────────────────────────────────────────────
    function _resetModal() {
        document.getElementById('pf-detalles-tbody').innerHTML  = '';
        document.getElementById('pf-adicional-tbody').innerHTML = '';
        document.getElementById('pf-id-cliente').value          = '';
        document.getElementById('pf-cliente-buscar').value      = '';
        document.getElementById('pf-cliente-info').classList.add('d-none');
        document.getElementById('pf-secuencial').value          = '';
        document.getElementById('pf-dias-vigencia').value       = '15';
        document.getElementById('pf-vendedor').value            = '';
        document.getElementById('pf-observaciones').value       = '';
        document.getElementById('pf-estado-badge').classList.add('d-none');
        document.getElementById('btn-pdf-proforma')?.classList.add('d-none');
        document.getElementById('btn-convertir-factura')?.classList.add('d-none');
        _calcularTotales();
    }

    function _setModoEdicion(editable) {
        const inputs = document.querySelectorAll('#modalProforma input:not([type=hidden]), #modalProforma select, #modalProforma textarea');
        inputs.forEach(el => { el.disabled = !editable; });
        const btnGuardar = document.getElementById('btn-guardar-proforma');
        const btnAgregar = document.getElementById('btn-agregar-item');
        if (btnGuardar) btnGuardar.classList.toggle('d-none', !editable || (!PF_CONFIG.perm.crear && !PF_CONFIG.perm.actualizar));
        if (btnAgregar) btnAgregar.disabled = !editable;
    }

    function _actualizarBotonesEstado() {
        const es = _estadoActual;
        const esBorrador  = es === 'borrador';
        const esAprobada  = es === 'aprobada';

        _toggleBtn('btn-eliminar-proforma',  _idActual > 0 && PF_CONFIG.perm.eliminar && ['borrador', 'rechazada'].includes(es));
        _toggleBtn('btn-aprobar-proforma',   _idActual > 0 && PF_CONFIG.perm.actualizar && esBorrador);
        _toggleBtn('btn-rechazar-proforma',  _idActual > 0 && PF_CONFIG.perm.actualizar && esAprobada);
        _toggleBtn('btn-anular-proforma',    _idActual > 0 && PF_CONFIG.perm.actualizar && (esBorrador || esAprobada));
        _toggleBtn('btn-guardar-proforma',   (PF_CONFIG.perm.crear && _idActual === 0) || (PF_CONFIG.perm.actualizar && esBorrador));
    }

    function _toggleBtn(id, show) {
        const btn = document.getElementById(id);
        if (btn) btn.classList.toggle('d-none', !show);
    }

    function _setBadgeEstado(estado) {
        const badge = document.getElementById('pf-estado-badge');
        if (!badge) return;
        const clases = {
            borrador:   'bg-secondary bg-opacity-10 text-secondary border-secondary',
            aprobada:   'bg-success bg-opacity-10 text-success border-success',
            rechazada:  'bg-warning bg-opacity-10 text-warning border-warning',
            convertida: 'bg-primary bg-opacity-10 text-primary border-primary',
            anulada:    'bg-danger bg-opacity-10 text-danger border-danger',
        };
        const labels = { borrador: 'Borrador', aprobada: 'Aprobada', rechazada: 'Rechazada', convertida: 'Convertida', anulada: 'Anulada' };
        badge.className = `badge border small ${clases[estado] ?? ''}`;
        badge.textContent = labels[estado] ?? estado;
        badge.classList.remove('d-none');
    }

    function _actualizarFila(id, rowHtml) {
        const tr = document.querySelector(`#pf-tbody tr[data-id="${id}"]`);
        if (tr && rowHtml) {
            const tmp = document.createElement('tbody');
            tmp.innerHTML = rowHtml;
            const newTr = tmp.querySelector('tr');
            if (newTr) tr.replaceWith(newTr);
        } else if (!tr && rowHtml) {
            const tbody = document.getElementById('pf-tbody');
            const vacio = tbody?.querySelector('td[colspan]');
            if (vacio) vacio.closest('tr').remove();
            if (tbody) tbody.insertAdjacentHTML('afterbegin', rowHtml);
        }
    }

    function _setSelect(id, value) {
        const sel = document.getElementById(id);
        if (!sel) return;
        const opt = Array.from(sel.options).find(o => o.value == value);
        if (opt) sel.value = value;
    }

    function _toast(msg, tipo = 'info') {
        const id = 'pf-toast-' + Date.now();
        const color = { success: 'bg-success', danger: 'bg-danger', warning: 'bg-warning text-dark', info: 'bg-info text-dark' }[tipo] ?? 'bg-secondary';
        const html = `<div id="${id}" class="toast align-items-center text-white border-0 ${color}" role="alert">
            <div class="d-flex"><div class="toast-body fw-semibold">${msg}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`;
        let container = document.getElementById('pf-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'pf-toast-container';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }
        container.insertAdjacentHTML('beforeend', html);
        const toastEl = document.getElementById(id);
        const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }

    function _hoy() {
        return new Date().toISOString().split('T')[0];
    }

    function _esc(str) {
        return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ─── Iniciar al cargar ────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', init);

    // ─── API pública ──────────────────────────────────────────────────────────
    return {
        nueva,
        verDetalle,
        guardar,
        eliminar,
        cambiarEstado,
        exportarPdf,
        convertirAFactura,
        agregarFila,
        eliminarFila,
        agregarAdicional,
        cambiarPagina,
        _selCliente,
        _selProducto,
    };
})();
