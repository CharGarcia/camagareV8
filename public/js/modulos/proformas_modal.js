/**
 * proformas_modal.js — Lógica del modal de Proformas (window.PF)
 */
(function () {
    'use strict';

    const CFG      = () => window.PF_CONFIG || {};
    const urlBase  = () => CFG().urlBase || '';
    const tivas    = () => CFG().tarifasIva || [];
    const perm     = () => CFG().perm || {};

    /**
     * Decimales y modo de cálculo del IVA configurados por la empresa/establecimiento
     * (los mismos que usa Facturas de Venta). La pantalla, lo que se guarda y el PDF
     * tienen que mostrar exactamente las mismas cifras, así que todo el módulo formatea
     * y redondea con estos valores en vez de constantes fijas.
     */
    const decPrecio = () => {
        const n = parseInt(CFG().decimales_precio, 10);
        return isNaN(n) ? 2 : Math.max(0, Math.min(6, n));
    };
    const decCant = () => {
        const n = parseInt(CFG().decimales_cantidad, 10);
        return isNaN(n) ? 2 : Math.max(0, Math.min(6, n));
    };
    const modoIva = () => CFG().calculo_iva || 'linea_linea';

    /** Redondea a 2 decimales evitando errores de punto flotante (igual que en Facturas). */
    const r2 = v => Math.round(((parseFloat(v) || 0) + Number.EPSILON) * 100) / 100;

    /* ── Shortcuts DOM ───────────────────────────────────────── */
    const $id = id => document.getElementById(id);
    const fmt2 = v => parseFloat(v || 0).toFixed(2);

    /* ── Editor de condiciones (Quill) ───────────────────────── */
    // El contenido NO va en la proforma: se guarda como HTML y se imprime en un PDF anexo.
    // Sin imágenes a propósito (se incrustarían en base64 dentro de la columna de texto).
    const QUILL_TOOLBAR = [
        [{ header: [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ color: [] }],
        [{ align: [] }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        [{ indent: '-1' }, { indent: '+1' }],
        ['link', 'clean'],
    ];
    let _quillPf = null, _quillPlt = null;
    function _crearQuill(selector, placeholder) {
        if (!window.Quill || !document.querySelector(selector)) return null;
        return new Quill(selector, { theme: 'snow', placeholder, modules: { toolbar: QUILL_TOOLBAR } });
    }
    // Se crean bajo demanda (la primera vez que se usan), ya con el DOM del modal presente.
    function _quillProforma() {
        if (!_quillPf) _quillPf = _crearQuill('#pf_condicionesEditor', 'Escriba aquí las condiciones adicionales de la cotización…');
        return _quillPf;
    }
    function _quillPlantilla() {
        if (!_quillPlt) _quillPlt = _crearQuill('#plt_condicionesEditor', 'Condiciones que se precargarán al usar la plantilla…');
        return _quillPlt;
    }
    /** HTML del editor, o '' si no hay texto real (solo párrafos vacíos). */
    function _condicionesHtml(q) {
        if (!q || !q.getText().trim()) return '';
        return q.root.innerHTML;
    }
    function _setCondicionesHtml(q, html) {
        if (!q) return;
        q.setContents(html ? q.clipboard.convert(html) : [], 'silent');
    }

    /* ── Notificaciones ──────────────────────────────────────── */
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

    /* ── Instancia modal Bootstrap ───────────────────────────── */
    function getModal() {
        let m = bootstrap.Modal.getInstance($id('modalProforma'));
        if (!m) m = new bootstrap.Modal($id('modalProforma'));
        return m;
    }

    /* ── Badge de estado ─────────────────────────────────────── */
    const BADGE_CLASSES = {
        borrador:   'badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25',
        aprobada:   'badge bg-success bg-opacity-10 text-success border border-success border-opacity-25',
        rechazada:  'badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25',
        convertida: 'badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25',
        anulada:    'badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
    };
    const BADGE_LABELS = { borrador:'Borrador', aprobada:'Aprobada', rechazada:'Rechazada', convertida:'Facturada', anulada:'Anulada' };

    function _aplicarEstado(estado) {
        const badge = $id('pf_estadoBadge');
        badge.className = BADGE_CLASSES[estado] || 'badge bg-secondary';
        badge.textContent = BADGE_LABELS[estado] || estado;

        const show = (id, cond) => { const el = $id(id); if (el) el.classList.toggle('d-none', !cond); };

        // Barra de acciones
        const guardada      = !!$id('pf_id').value;
        const convertible   = perm().crear && ['borrador','aprobada','convertida'].includes(estado);
        // La factura solo se genera desde una proforma aprobada (o ya facturada, para reconvertir).
        const facturable    = perm().crear && ['aprobada','convertida'].includes(estado);
        show('pf-btn-factura',  facturable);
        show('pf-btn-pedido',   convertible);
        // El recibo aplica las mismas reglas de facturación → misma condición que la factura.
        show('pf-btn-recibo',   facturable);
        show('pf-btn-duplicar', perm().crear && guardada);
        // Solo se puede editar en borrador: fuera de ahí se bloquea todo el detalle
        // (cliente, ítems, info adicional, vigencia) y no tiene sentido ofrecer
        // aplicar una plantilla que de todos modos no se podría guardar.
        const editable = estado === 'borrador';
        const fieldset = $id('pf_fieldsetEditable');
        if (fieldset) fieldset.disabled = !editable;
        // El fieldset no bloquea un contenteditable: el editor se desactiva aparte.
        const qCond = _quillProforma();
        if (qCond) qCond.enable(editable);
        show('pf-btn-plantillas', editable);
        show('pf-vr1',          perm().crear && guardada);
        show('pf-btn-pdf',       guardada);
        show('pf-btn-excel',     guardada);
        show('pf-btn-correo',    guardada);
        show('pf-btn-whatsapp',  guardada);
        show('pf-vr2',          perm().actualizar && guardada);
        show('pf-btn-aprobar',   perm().actualizar && estado === 'borrador');
        show('pf-btn-rechazar',  perm().actualizar && estado === 'aprobada');
        show('pf-btn-anular',    perm().actualizar && ['borrador','aprobada'].includes(estado));

        // Footer
        show('pf_btnEliminar', perm().eliminar  && estado !== 'convertida');
        show('pf_btnGuardar',  perm().actualizar && estado === 'borrador');
    }

    /* ── Reset modal ─────────────────────────────────────────── */
    function _reset() {
        $id('pf_id').value = '';
        $id('pf_tituloModal').textContent = 'Nueva Proforma';

        // Cabecera
        $id('pf_fecha').value        = CMG_fechaLocal();
        $id('pf_secuencial').value   = '';
        $id('pf_diasVigencia').value = '15';
        $id('pf_vendedor').value     = '';
        $id('pf_observaciones').value = '';
        _setCondicionesHtml(_quillProforma(), '');

        // Punto de emisión — seleccionar el primero si hay uno solo
        const selPunto = $id('pf_punto');
        // La serie solo se bloquea al editar (ver PF.verDetalle); aquí se libera
        // porque _reset() se usa para "nueva proforma" o para reiniciar antes de
        // cargar otra. El flag en sí lo maneja quien llama a _reset(), no esta función.
        if (selPunto) {
            selPunto.disabled = false;
            selPunto.selectedIndex = 0;   // primer punto (no hay opción vacía)
            _syncEstab(selPunto);
            // Se llama SIEMPRE (igual que cargarSecuencial() en factura de venta),
            // incluso con el select vacío: si la empresa no tiene ninguna serie con el
            // secuencial de "Proformas" configurado, el valor es '' y _cargarSecuencial
            // avisa y deja _secuencialConfigurado en false, que es lo que después
            // bloquea el guardado. Con el antiguo `if (selPunto.value)` ese caso pasaba
            // en silencio y el flag se quedaba en true (se guardaba sin numeración).
            _secuencialConfigurado = true;   // se recalcula en _cargarSecuencial
            _cargarSecuencial(selPunto.value);
        }

        // Cliente
        _limpiarCliente();

        // Detalles: una fila vacía lista para ingresar
        const tbody = $id('pf_tbodyDetalle');
        tbody.innerHTML = '';
        tbody.appendChild(_crearFila());
        _calcularTotales();

        // Info adicional: una fila vacía lista
        const tbodyAd = $id('pf_tbodyAdicional');
        tbodyAd.innerHTML = '';
        tbodyAd.appendChild(_crearFilaAdicional());

        // Facturas asociadas (se cargan al abrir una proforma existente)
        const tbodyFac = $id('pf_tbodyFacturas');
        if (tbodyFac) tbodyFac.innerHTML = '<tr><td colspan="4" class="text-center text-muted small py-3">Sin facturas asociadas</td></tr>';

        // Info Productos: se reconstruye vacía (una fila sin descripción aún)
        _renderInfoProductos();

        // Badge estado
        const badge = $id('pf_estadoBadge');
        badge.className = 'badge d-none';
        badge.textContent = '';

        // Aviso de aprobación del cliente (oculto en proforma nueva)
        $id('pf_aprobacionCliente')?.classList.add('d-none');

        // Una proforma nueva siempre es editable (por si el modal quedó bloqueado
        // por una proforma aprobada/anulada que se estaba viendo antes).
        const fieldsetReset = $id('pf_fieldsetEditable');
        if (fieldsetReset) fieldsetReset.disabled = false;
        $id('pf-btn-plantillas')?.classList.remove('d-none');

        // Ocultar todos los botones de acción
        ['pf-btn-factura','pf-btn-pedido','pf-btn-recibo','pf-btn-duplicar','pf-vr1',
         'pf-btn-pdf','pf-btn-excel','pf-btn-whatsapp','pf-btn-correo','pf-vr2',
         'pf-btn-aprobar','pf-btn-rechazar','pf-btn-anular',
         'pf_btnEliminar','pf_btnGuardar'].forEach(id => {
            const el = $id(id); if (el) el.classList.add('d-none');
        });

        // Para nueva proforma mostrar Guardar si tiene permiso
        if (perm().crear) {
            const btn = $id('pf_btnGuardar');
            if (btn) btn.classList.remove('d-none');
        }

        // Volver a pestaña principal
        const tabPrincipal = document.getElementById('pf-tab-proforma-btn');
        if (tabPrincipal) bootstrap.Tab.getOrCreateInstance(tabPrincipal).show();

    }

    /* ── Flag que bloquea sobreescribir el secuencial (modo edición) ── */
    let _bloquearSecuencial = false;

    /* ── ¿La serie activa tiene secuencial configurado? (se actualiza en _cargarSecuencial) ── */
    let _secuencialConfigurado = true;

    /* ── Aviso de secuencial no configurado (igual que factura de venta) ── */
    function _avisarSecuencialNoConfigurado(motivo) {
        const txt = (motivo === 'serie')
            ? 'No hay una serie / punto de emisión disponible.<br>Configure los puntos de emisión y sus secuenciales en <strong>Empresa → Puntos de emisión</strong> antes de emitir la proforma.'
            : 'No está configurado el secuencial para esta serie.<br>Configúrelo en <strong>Empresa → Puntos de emisión</strong> antes de emitir la proforma.';
        Swal.fire({
            icon: 'warning',
            title: 'Secuencial no configurado',
            html: txt,
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#f39c12',
            target: document.getElementById('modalProforma') || undefined,
        });
    }

    /* ── Sync establecimiento: igual que syncSerie() en factura de venta ── */
    function _syncEstab(selPunto) {
        const opt = selPunto.selectedOptions[0];
        const hiddenEstab = $id('pf_establecimiento');
        if (hiddenEstab && opt) hiddenEstab.value = opt.dataset.est || '';
    }

    /* ── Cargar secuencial: réplica exacta de cargarSecuencial() de factura de venta ── */
    async function _cargarSecuencial(idPunto) {
        if (_bloquearSecuencial) return;
        const inputSec = $id('pf_secuencial');

        // Sin serie / punto de emisión: no hay secuencial configurable.
        if (!idPunto) {
            _secuencialConfigurado = false;
            if (inputSec) { inputSec.value = ''; inputSec.placeholder = 'Sin serie'; }
            _avisarSecuencialNoConfigurado('serie');
            return;
        }

        if (inputSec) inputSec.placeholder = 'Cargando...';
        try {
            const resp = await fetch(`${urlBase()}/getSecuencialAjax?id_punto_emision=${idPunto}`);
            const json = await resp.json();
            // Re-chequear tras el await: si mientras esta respuesta viajaba se empezó
            // a cargar una proforma existente (que bloquea y fija su propio secuencial),
            // esta respuesta ya está obsoleta y no debe pisarlo.
            if (_bloquearSecuencial) return;
            if (json.ok) {
                inputSec.value       = json.formateado || String(json.secuencial).padStart(9, '0');
                inputSec.placeholder = '000000001';
                if (json.es_gap) {
                    inputSec.classList.add('border-warning');
                    inputSec.title = json.detalle || 'Número faltante recuperado';
                } else {
                    inputSec.classList.remove('border-warning');
                    inputSec.title = json.detalle || 'Siguiente consecutivo';
                }

                // ¿Está configurado el secuencial para esta serie?
                _secuencialConfigurado = (json.configurado !== false);
                if (json.configurado === false) {
                    inputSec.classList.add('border-danger');
                    _avisarSecuencialNoConfigurado('secuencial');
                } else {
                    inputSec.classList.remove('border-danger');
                }
            } else {
                inputSec.value       = '000000001';
                inputSec.placeholder = '000000001';
                _secuencialConfigurado = false;
                _avisarSecuencialNoConfigurado('secuencial');
            }
        } catch(e) {
            if (inputSec) { inputSec.value = '000000001'; inputSec.placeholder = '000000001'; }
            console.error('Error cargando secuencial proforma', e);
        }
    }

    /* ── Cliente ─────────────────────────────────────────────── */
    function _limpiarCliente() {
        $id('pf_idCliente').value     = '';
        $id('pf_clienteBuscar').value = '';
        const infoDiv = $id('pf_infoCliente');
        if (infoDiv) infoDiv.classList.add('d-none');
        const rucEl = $id('pf_clienteRuc');    if (rucEl) rucEl.textContent = '';
        const nomEl = $id('pf_clienteNombre'); if (nomEl) nomEl.textContent = '';
        const emlEl = $id('pf_clienteEmail');  if (emlEl) emlEl.textContent = '';
        // Quitar fila de correo del cliente en info adicional
        _actualizarInfoCorreoCliente('');
    }

    function _seleccionarCliente(c) {
        $id('pf_idCliente').value     = c.id;
        $id('pf_clienteBuscar').value = c.nombre || c.razon_social || '';
        const infoDiv = $id('pf_infoCliente');
        if (infoDiv) infoDiv.classList.remove('d-none');
        const rucEl = $id('pf_clienteRuc');    if (rucEl) rucEl.textContent = c.identificacion || c.ruc || '';
        const nomEl = $id('pf_clienteNombre'); if (nomEl) nomEl.textContent = c.nombre || c.razon_social || '';
        const emlEl = $id('pf_clienteEmail');  if (emlEl) emlEl.textContent = c.email || '';
        const dd = $id('pf_ddClientes');
        if (dd) dd.classList.add('d-none');

        // Mover foco al primer ítem de descripción
        setTimeout(() => {
            const primerDesc = document.querySelector('#pf_tbodyDetalle .input-descripcion');
            primerDesc?.focus();
        }, 50);

        // Insertar correo del cliente en info adicional
        _actualizarInfoCorreoCliente(c.email || '');
    }

    function _actualizarInfoCorreoCliente(email) {
        const tbody = $id('pf_tbodyAdicional');
        if (!tbody) return;

        let filaCorreo = tbody.querySelector('tr[data-tipo="correo-cliente"]');

        if (!email) {
            if (filaCorreo) filaCorreo.remove();
            return;
        }

        if (filaCorreo) {
            // Actualizar email si ya existe
            filaCorreo.querySelector('.inp-ad-valor').value = email;
        } else {
            // Crear fila fija de correo (al inicio de la tabla)
            const tr = document.createElement('tr');
            tr.dataset.tipo = 'correo-cliente';
            tr.innerHTML = `
            <td class="p-0 ps-2">
                <input type="text" class="input-detalle inp-ad-nombre" value="Correo del cliente" readonly style="background-color:#f8f9fa;">
            </td>
            <td class="p-0">
                <input type="text" class="input-detalle inp-ad-valor" value="${_esc(email)}" readonly style="background-color:#f8f9fa;">
            </td>
            <td class="p-0 text-center pe-1">
                <span class="text-muted small" title="Se actualiza al cambiar el cliente"><i class="bi bi-lock-fill"></i></span>
            </td>`;
            tbody.appendChild(tr);
        }
    }

    function _initClienteBuscar() {
        const inp = $id('pf_clienteBuscar');
        const dd  = $id('pf_ddClientes');
        if (!inp || !dd) return;

        // Backspace / Delete con cliente seleccionado → limpiar todo
        inp.addEventListener('keydown', e => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && $id('pf_idCliente').value) {
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
                // Limpiar cliente al vaciar el campo
                $id('pf_idCliente').value = '';
                const infoDiv = $id('pf_infoCliente');
                if (infoDiv) infoDiv.classList.add('d-none');
                return;
            }
            timer = setTimeout(async () => {
                try {
                    const data = await (await fetch(`${urlBase()}/getClientesAjax?q=${encodeURIComponent(q)}`)).json();
                    if (!data.ok || !data.data.length) {
                        dd.innerHTML = '<div class="pf-dd-item text-muted small">Sin resultados</div>';
                        dd.classList.remove('d-none');
                        return;
                    }
                    dd.innerHTML = data.data.map(c =>
                        `<div class="list-group-item list-group-item-action py-2 pf-dd-item" onclick='window._pfSelCliente(${JSON.stringify(c)})'>
                            <div class="fw-semibold small">${_esc(c.nombre || c.razon_social || '')}</div>
                            <div class="text-muted" style="font-size:0.75rem;">${_esc(c.identificacion || c.ruc || '')}</div>
                        </div>`
                    ).join('');
                    dd.classList.remove('d-none');
                } catch(e) { console.error(e); }
            }, 300);
        });

        document.addEventListener('click', e => {
            if (!inp.contains(e.target) && !dd.contains(e.target)) dd.classList.add('d-none');
        });
    }

    window._pfSelCliente = c => _seleccionarCliente(c);

    /* ── Filas de detalle (misma estructura que factura de venta) ── */

    // Genera opciones IVA (value = porcentaje, data-id = id, data-codigo = codigo)
    function _tivasOpts(idTarIvaSeleccionado) {
        return tivas().map(t => {
            const sel = parseInt(t.id) === parseInt(idTarIvaSeleccionado || 0) ? 'selected' : '';
            return `<option value="${t.porcentaje_iva ?? 0}" data-id="${t.id}" data-codigo="${t.codigo || ''}" ${sel}>${t.tarifa || (t.porcentaje_iva ?? 0) + '%'}</option>`;
        }).join('');
    }

    // Genera opciones de lista de precios del producto.
    // Si el producto tiene precios cargados, los lista; si no, una opción con el precio actual.
    function _listaPreciosOpts(precios, precioActual) {
        const lista = Array.isArray(precios) ? precios : [];
        if (lista.length === 0) {
            return `<option value="1" data-precio="${(+precioActual || 0)}" selected>Precio 1</option>`;
        }
        const actual = +(+precioActual || 0).toFixed(4);
        return lista.map((pr, i) => {
            const val = +(parseFloat(pr.precio || 0)).toFixed(4);
            const sel = Math.abs(val - actual) < 0.0001 ? 'selected' : '';
            return `<option value="${i + 1}" data-precio="${parseFloat(pr.precio || 0)}" ${sel}>${_esc(pr.nombre_precio || ('Precio ' + (i + 1)))}</option>`;
        }).join('');
    }

    function _crearFila(data = {}) {
        const idTarIva     = parseInt(data.id_tarifa_iva || 0);
        const tiva         = tivas().find(t => parseInt(t.id) === idTarIva);
        const tasa         = parseFloat(tiva?.porcentaje || 0);
        const precioSinImp = parseFloat(data.precio_unitario || 0);
        const cant         = parseFloat(data.cantidad || 1);
        const desc         = parseFloat(data.descuento || 0);
        const base         = Math.max(0, r2(r2(cant * precioSinImp) - desc));
        const precioConImp = precioSinImp * (1 + tasa / 100);

        const tr = document.createElement('tr');
        tr.className  = 'row-detalle';
        tr.dataset.id = data.id || 0;

        tr.innerHTML = `
        <td class="ps-3">
            <input type="text" class="form-control form-control-sm input-detalle input-descripcion"
                value="${_esc(data.descripcion || '')}" placeholder="Buscar producto o escribe descripción...">
            <input type="hidden" class="input-id-producto" value="${data.id_producto || ''}">
            <input type="hidden" class="input-codigo" value="${_esc(data.codigo_principal || '')}">
            <input type="hidden" class="input-imagen" value="${_esc(data.producto_imagen || data.imagen || '')}">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm input-detalle input-adicional text-muted fst-italic"
                value="${_esc(data.adicional || '')}" placeholder="Info adicional">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm input-detalle text-center input-cantidad"
                value="${parseFloat(data.cantidad || 1).toFixed(decCant())}" step="any" min="0">
        </td>
        <td>
            <select class="form-select form-select-sm input-detalle input-lista-precios">
                ${_listaPreciosOpts(data.precios_lista, precioSinImp)}
            </select>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm input-detalle text-end input-precio"
                value="${precioSinImp.toFixed(decPrecio())}" step="any" min="0">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm input-detalle text-end input-precio-iva"
                value="${precioConImp.toFixed(decPrecio())}" step="any" min="0">
        </td>
        <td>
            <div class="d-flex align-items-center">
                <input type="number" class="form-control form-control-sm input-detalle text-end text-danger input-desc"
                    value="${desc.toFixed(2)}" step="any" min="0">
                <button type="button" class="btn btn-link btn-sm p-1 text-primary shadow-none border-0"
                    onclick="PF._abrirDescuento(this)" title="Aplicar descuento">
                    <i class="bi bi-plus-circle"></i>
                </button>
            </div>
        </td>
        <td>
            <select class="form-select form-select-sm input-detalle text-center input-iva">
                ${_tivasOpts(idTarIva)}
            </select>
        </td>
        <td class="text-end pe-4 align-middle">
            <span class="subtotal-line">${base.toFixed(2)}</span>
        </td>
        <td class="text-center p-0 align-middle" style="width:40px;">
            <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0"
                onclick="PF.eliminarFila(this)" title="Eliminar ítem">
                <i class="bi bi-trash3 fs-6"></i>
            </button>
        </td>`;

        // Autocomplete descripción → productos
        // El dropdown se añade al <body> con position:fixed para escapar el overflow:hidden de la tabla
        const inpDesc = tr.querySelector('.input-descripcion');
        const ddProd  = document.createElement('div');
        ddProd.className = 'list-group shadow d-none';
        ddProd.style.cssText = [
            'position:fixed',
            'z-index:9999',
            'min-width:340px',
            'max-height:220px',
            'overflow-y:auto',
            'background:#fff',
            'border:1px solid #dee2e6',
            'border-radius:0 0 6px 6px',
            'box-shadow:0 4px 16px rgba(0,0,0,.12)',
        ].join(';');
        document.body.appendChild(ddProd);
        tr._pfDdProd  = ddProd;  // referencia para limpieza al eliminar la fila
        ddProd._pfTr  = tr;      // referencia inversa para _pfSelProd

        function _posDdProd() {
            const r = inpDesc.getBoundingClientRect();
            ddProd.style.top   = r.bottom + 'px';
            ddProd.style.left  = r.left   + 'px';
            ddProd.style.width = Math.max(r.width, 340) + 'px';
        }

        // Backspace / Delete con producto seleccionado → limpiar la fila
        inpDesc.addEventListener('keydown', e => {
            const idProd = tr.querySelector('.input-id-producto');
            if ((e.key === 'Backspace' || e.key === 'Delete') && idProd && idProd.value) {
                e.preventDefault();
                inpDesc.value = '';
                idProd.value = '';
                tr.querySelector('.input-codigo').value = '';
                ddProd.classList.add('d-none');
            }
        });

        let timer;
        inpDesc.addEventListener('input', () => {
            clearTimeout(timer);
            const q = inpDesc.value.trim();
            if (!q) {
                ddProd.classList.add('d-none');
                // Limpiar datos del producto al vaciar descripción
                tr.querySelector('.input-id-producto').value = '';
                tr.querySelector('.input-codigo').value = '';
                return;
            }
            timer = setTimeout(async () => {
                try {
                    const res = await (await fetch(`${urlBase()}/getProductosAjax?q=${encodeURIComponent(q)}`)).json();
                    if (!res.ok || !res.data?.length) {
                        ddProd.innerHTML = '<div class="list-group-item text-muted small py-2 px-3">Sin resultados</div>';
                    } else {
                        ddProd.innerHTML = res.data.map(p =>
                            `<div class="list-group-item list-group-item-action py-1 px-3" style="font-size:0.8rem;cursor:pointer;"
                                 onclick='window._pfSelProd(this, ${JSON.stringify(p)})'>
                                <span class="fw-semibold">${_esc(p.codigo || '')} — ${_esc(p.nombre || '')}</span>
                                <span class="text-muted ms-2 float-end">$${parseFloat(p.precio_base || 0).toFixed(2)}</span>
                            </div>`
                        ).join('');
                    }
                    _posDdProd();
                    ddProd.classList.remove('d-none');
                } catch(e) { console.error(e); }
            }, 280);
        });
        // Reposicionar si la tabla hace scroll
        document.querySelector('.table-responsive')?.addEventListener('scroll', () => {
            if (!ddProd.classList.contains('d-none')) _posDdProd();
        });
        document.addEventListener('click', e => {
            if (!inpDesc.contains(e.target) && !ddProd.contains(e.target)) ddProd.classList.add('d-none');
        });

        // Eventos de cálculo (igual que calcSinImp / calcConImp / syncPrecioIva en FV)
        const inpPrecio  = tr.querySelector('.input-precio');
        const inpPrecIva = tr.querySelector('.input-precio-iva');
        const selIva     = tr.querySelector('.input-iva');

        inpPrecio.addEventListener('input', () => {
            const t = parseFloat(selIva.value || 0);
            inpPrecIva.value = (parseFloat(inpPrecio.value || 0) * (1 + t / 100)).toFixed(decPrecio());
            _recalcFila(tr);
        });
        inpPrecIva.addEventListener('input', () => {
            const t = parseFloat(selIva.value || 0);
            const pCon = parseFloat(inpPrecIva.value || 0);
            inpPrecio.value = t > 0 ? (pCon / (1 + t / 100)).toFixed(decPrecio()) : pCon.toFixed(decPrecio());
            _recalcFila(tr);
        });
        selIva.addEventListener('change', () => {
            const t = parseFloat(selIva.value || 0);
            inpPrecIva.value = (parseFloat(inpPrecio.value || 0) * (1 + t / 100)).toFixed(decPrecio());
            _recalcFila(tr);
        });
        tr.querySelector('.input-cantidad').addEventListener('input', () => _recalcFila(tr));
        tr.querySelector('.input-desc').addEventListener('input', () => _recalcFila(tr));

        // Cambiar lista de precios → actualiza precio sin/con IVA
        const selLista = tr.querySelector('.input-lista-precios');
        selLista.addEventListener('change', () => {
            const precioSel = parseFloat(selLista.selectedOptions[0]?.dataset.precio || 0);
            const t = parseFloat(selIva.value || 0);
            inpPrecio.value  = precioSel.toFixed(decPrecio());
            inpPrecIva.value = (precioSel * (1 + t / 100)).toFixed(decPrecio());
            _recalcFila(tr);
        });

        _recalcFila(tr);
        return tr;
    }

    // Seleccionar producto desde autocomplete (replica el patrón de factura de venta)
    window._pfSelProd = (el, p) => {
        const dd = el.closest('.list-group');
        const tr = dd?._pfTr;
        if (!tr) return;
        dd.classList.add('d-none');
        _pfLlenarFilaProducto(tr, p);
    };

    // Rellena una fila del detalle con los datos de un producto. Reutilizable:
    // desde el autocomplete y al crear un producto nuevo desde la proforma.
    function _pfLlenarFilaProducto(tr, p) {
        // Obtener precio sin IVA
        const pSin = parseFloat(p.precio_base || 0);
        const pCon = parseFloat(p.pvp || 0);

        tr.querySelector('.input-id-producto').value = p.id    || '';
        tr.querySelector('.input-codigo').value      = p.codigo || '';
        tr.querySelector('.input-descripcion').value = p.nombre || '';
        const inpImg = tr.querySelector('.input-imagen');
        if (inpImg) inpImg.value = p.imagen || '';
        tr.querySelector('.input-cantidad').value    = (1).toFixed(decCant());
        tr.querySelector('.input-precio').value      = pSin.toFixed(decPrecio());
        tr.querySelector('.input-precio-iva').value  = pCon.toFixed(decPrecio());
        tr.querySelector('.input-desc').value        = '0.00';

        // Asignar IVA (priorizar porcentaje_iva_final como en FV)
        let pctFinal = null;
        if (p.porcentaje_iva_final !== undefined && p.porcentaje_iva_final !== null) {
            pctFinal = parseFloat(p.porcentaje_iva_final);
        } else if (p.porcentaje_iva !== undefined && p.porcentaje_iva !== null) {
            pctFinal = parseFloat(p.porcentaje_iva);
        } else if (p.porcentaje !== undefined && p.porcentaje !== null) {
            pctFinal = parseFloat(p.porcentaje);
        } else if (p.tarifa_iva) {
            const tFound = tivas().find(t => t.id == p.tarifa_iva);
            if (tFound) pctFinal = parseFloat(tFound.porcentaje_iva || tFound.porcentaje);
        }

        if (pctFinal !== null || p.tarifa_iva) {
            const selIva = tr.querySelector('.input-iva');
            if (selIva) {
                // Prioridad 1: buscar por ID directo
                let opt = p.tarifa_iva ? Array.from(selIva.options).find(o => o.dataset.id == p.tarifa_iva) : null;
                // Prioridad 2: buscar por porcentaje (fallback)
                if (!opt && pctFinal !== null) {
                    opt = Array.from(selIva.options).find(o => Math.abs(parseFloat(o.value) - pctFinal) < 0.001);
                }
                if (opt) {
                    selIva.selectedIndex = opt.index;
                } else if (pctFinal === 0) {
                    selIva.value = '0';
                }
            }
        }

        // Lista de precios reales del producto (el listener change ya está en _crearFila)
        const selLista = tr.querySelector('.input-lista-precios');
        selLista.innerHTML = _listaPreciosOpts(p.precios_lista, pSin);
        selLista.value = '1';

        _recalcFila(tr);

        // Foco en cantidad
        tr.querySelector('.input-cantidad')?.focus();
    }

    function _recalcFila(tr) {
        const cant = parseFloat(tr.querySelector('.input-cantidad').value || 0);
        const pSin = parseFloat(tr.querySelector('.input-precio').value || 0);
        const desc = parseFloat(tr.querySelector('.input-desc').value || 0);
        // Igual que Facturas de Venta: se redondea el bruto y luego el neto, no al final.
        const base = Math.max(0, r2(r2(cant * pSin) - desc));

        tr.querySelector('.subtotal-line').textContent = base.toFixed(2);

        _calcularTotales();
    }

    function _numerarFilas() {
        let i = 1;
        document.querySelectorAll('#pf_tbodyDetalle .row-detalle').forEach(() => i++);
        $id('pf_countItems').textContent = i - 1;
    }

    /**
     * Totales del pie, con el MISMO algoritmo que Facturas de Venta (calcTotales):
     *  - se redondea a 2 decimales en cada paso (bruto, neto y acumulados);
     *  - "Subtotal" es el bruto (antes de descuento), coherente con la línea "(-) Descuento";
     *  - el IVA respeta la configuración de la empresa: 'linea_linea' acumula el IVA
     *    redondeado de cada renglón, 'subtotal' lo calcula sobre la base de cada tarifa.
     */
    function _calcularTotales() {
        const modo   = modoIva();
        const grupos = {};  // { idTarifa: { tasa, base, iva } }
        let subtotalBrutoTotal = 0;
        let totalDesc = 0;

        document.querySelectorAll('#pf_tbodyDetalle .row-detalle').forEach(tr => {
            const cant  = parseFloat(tr.querySelector('.input-cantidad').value || 0);
            const pSin  = parseFloat(tr.querySelector('.input-precio').value   || 0);
            const desc  = parseFloat(tr.querySelector('.input-desc').value     || 0);
            const sel   = tr.querySelector('.input-iva');
            const tasa  = parseFloat(sel.value || 0);                  // value = porcentaje
            const idTar = sel.selectedOptions[0]?.dataset.id || '0';   // data-id = id tarifa
            const bruto = r2(cant * pSin);
            const base  = Math.max(0, r2(bruto - desc));

            subtotalBrutoTotal = r2(subtotalBrutoTotal + bruto);
            totalDesc          = r2(totalDesc + desc);

            if (!grupos[idTar]) grupos[idTar] = { tasa, base: 0, iva: 0 };
            grupos[idTar].base = r2(grupos[idTar].base + base);
            if (modo === 'linea_linea') {
                grupos[idTar].iva = r2(grupos[idTar].iva + r2(base * tasa / 100));
            }
        });

        if (modo === 'subtotal') {
            Object.values(grupos).forEach(g => { g.iva = r2(g.base * g.tasa / 100); });
        }

        let ivaTotal = 0;
        Object.values(grupos).forEach(g => { ivaTotal = r2(ivaTotal + g.iva); });
        const total = r2((subtotalBrutoTotal - totalDesc) + ivaTotal);

        // Se listan por tarifa ascendente (el PDF hace lo mismo, así ambos coinciden
        // también en el orden de las líneas, no sólo en los importes).
        const ordenados = Object.values(grupos).sort((a, b) => a.tasa - b.tasa);

        // Subtotal general (bruto, antes de descuento)
        const elSub = $id('pf_subtotalGeneral');
        if (elSub) elSub.textContent = fmt2(subtotalBrutoTotal);

        // Descuento
        const elDesc = $id('pf_totalDescuento');
        if (elDesc) elDesc.textContent = fmt2(totalDesc);

        // Subtotales por tarifa
        const elSubIvas = $id('pf_subtotalesIva');
        if (elSubIvas) {
            elSubIvas.innerHTML = ordenados.map(g =>
                `<div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Subtotal ${g.tasa}%</span>
                    <span>${fmt2(g.base)}</span>
                </div>`
            ).join('');
        }

        // IVA por tarifa
        const elIvas = $id('pf_ivasGrupo');
        if (elIvas) {
            elIvas.innerHTML = ordenados.filter(g => g.tasa > 0).map(g =>
                `<div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">(+) IVA ${g.tasa}%</span>
                    <span class="fw-bold">${fmt2(g.iva)}</span>
                </div>`
            ).join('');
        }

        // Total
        const elTotal = $id('pf_importeTotal');
        if (elTotal) elTotal.textContent = fmt2(total);

        // Contador ítems
        const count = document.querySelectorAll('#pf_tbodyDetalle .row-detalle').length;
        $id('pf_countItems').textContent = count;
    }

    /* ── Info adicional ──────────────────────────────────────── */
    function _crearFilaAdicional(nombre = '', valor = '') {
        const tr = document.createElement('tr');
        tr.innerHTML = `
        <td class="p-0 ps-2"><input class="input-detalle inp-ad-nombre" type="text" value="${_esc(nombre)}" placeholder="Concepto"></td>
        <td class="p-0"><input class="input-detalle inp-ad-valor" type="text" value="${_esc(valor)}" placeholder="Detalle"></td>
        <td class="p-0 text-center" style="width:40px;">
            <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="this.closest('tr').remove()">
                <i class="bi bi-trash3"></i>
            </button>
        </td>`;
        return tr;
    }

    /* ── Payload ─────────────────────────────────────────────── */
    function _buildPayload() {
        const selPunto = $id('pf_punto');
        const optPunto = selPunto?.selectedOptions[0];

        // Mismos redondeos y mismo modo de cálculo del IVA que Facturas de Venta, para que
        // lo guardado coincida exactamente con lo que muestra la pantalla (y luego el PDF).
        const modo     = modoIva();
        const detalles = [];
        const grupos   = {};   // idTarifa => { tasa, base, iva }
        let totalSinImpuestos = 0;
        let totalDescuento    = 0;
        document.querySelectorAll('#pf_tbodyDetalle .row-detalle').forEach(tr => {
            const sel      = tr.querySelector('.input-iva');
            const tasa     = parseFloat(sel?.value || 0);                          // value = porcentaje
            const codPct   = sel?.selectedOptions[0]?.dataset.codigo || '0';
            const idTarIva = parseInt(sel?.selectedOptions[0]?.dataset.id || 0);   // data-id = id tarifa
            const cant     = parseFloat(tr.querySelector('.input-cantidad').value || 0);
            const pSin     = parseFloat(tr.querySelector('.input-precio').value   || 0);
            const desc     = r2(tr.querySelector('.input-desc').value             || 0);
            const base     = Math.max(0, r2(r2(cant * pSin) - desc));
            const valIva   = r2(base * (tasa / 100));

            // Saltar filas vacías (sin descripción)
            const descripcion = tr.querySelector('.input-descripcion').value.trim();
            if (!descripcion) return;

            totalSinImpuestos = r2(totalSinImpuestos + base);
            totalDescuento    = r2(totalDescuento + desc);

            const kTar = String(idTarIva || tasa);
            if (!grupos[kTar]) grupos[kTar] = { tasa, base: 0, iva: 0 };
            grupos[kTar].base = r2(grupos[kTar].base + base);
            grupos[kTar].iva  = r2(grupos[kTar].iva + valIva);

            // El impuesto se guarda SIEMPRE (también con tarifa 0), igual que en Facturas:
            // así el PDF puede desglosar "Subtotal 0%" sin tener que deducirlo.
            const imp = [{
                codigo_impuesto: '2', codigo_porcentaje: codPct, tarifa: tasa,
                base_imponible: base, valor: valIva,
            }];

            detalles.push({
                id:                        parseInt(tr.dataset.id || 0),
                id_producto:               tr.querySelector('.input-id-producto')?.value || null,
                codigo_principal:          tr.querySelector('.input-codigo')?.value.trim() || '',
                descripcion:               descripcion,
                adicional:                 tr.querySelector('.input-adicional')?.value.trim() || '',
                cantidad:                  cant,
                precio_unitario:           pSin,
                descuento:                 desc,
                precio_total_sin_impuesto: base,
                id_tarifa_iva:             idTarIva,
                impuestos:                 imp,
            });
        });

        // En modo 'subtotal' el IVA se recalcula sobre la base acumulada de cada tarifa.
        if (modo === 'subtotal') {
            Object.values(grupos).forEach(g => { g.iva = r2(g.base * g.tasa / 100); });
        }
        let totalIva = 0;
        Object.values(grupos).forEach(g => { totalIva = r2(totalIva + g.iva); });

        const importeTotal = r2(totalSinImpuestos + totalIva);

        const adicional = [];
        document.querySelectorAll('#pf_tbodyAdicional tr').forEach(tr => {
            const n = tr.querySelector('.inp-ad-nombre')?.value.trim() || '';
            const v = tr.querySelector('.inp-ad-valor')?.value.trim()  || '';
            if (n && v) adicional.push({ nombre: n, valor: v });
        });

        return {
            id:                 $id('pf_id').value,
            id_establecimiento: optPunto?.dataset.est || '',
            id_punto_emision:   selPunto?.value || '',
            establecimiento:    optPunto?.dataset.codEst   || '',
            punto_emision:      optPunto?.dataset.codPunto || '',
            secuencial:         $id('pf_secuencial').value.trim(),
            fecha_emision:      $id('pf_fecha').value,
            id_cliente:         $id('pf_idCliente').value,
            id_vendedor:        $id('pf_vendedor').value,
            dias_vigencia:      parseInt($id('pf_diasVigencia').value || 15),
            vigencia_unidad:    $id('pf_vigenciaUnidad').value || 'dias',
            observaciones:      $id('pf_observaciones').value.trim(),
            condiciones_html:   _condicionesHtml(_quillProforma()),
            moneda:             'DOLAR',
            total_sin_impuestos: +totalSinImpuestos.toFixed(2),
            total_descuento:     +totalDescuento.toFixed(2),
            total_ice:           0,
            importe_total:       +importeTotal.toFixed(2),
            detalles,
            info_adicional:     adicional,
        };
    }

    /* ── Cargar proforma existente ───────────────────────────── */
    /* ── Pestaña Facturas ────────────────────────────────────── */
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

    // Diálogo reutilizable de stock insuficiente (factura y recibo).
    function _mostrarStockInsuficiente(faltantes) {
        const filas = (faltantes || []).map(f => `
            <tr>
                <td class="text-start">${_esc(f.producto)}</td>
                <td class="text-end">${parseFloat(f.disponible || 0).toFixed(2)}</td>
                <td class="text-end">${parseFloat(f.requerido || 0).toFixed(2)}</td>
            </tr>`).join('');
        Swal.fire({
            icon: 'error',
            title: 'Stock insuficiente',
            html: `
                <p class="mb-2 small">La configuración de la empresa exige stock disponible para facturar.
                Los siguientes productos no tienen saldo suficiente:</p>
                <table class="table table-sm table-bordered small mb-0">
                    <thead class="table-light">
                        <tr><th class="text-start">Producto</th><th class="text-end">Disponible</th><th class="text-end">Requerido</th></tr>
                    </thead>
                    <tbody>${filas}</tbody>
                </table>`,
            confirmButtonText: 'Entendido'
        });
    }

    async function _cargarFacturas(id) {
        const tbody = $id('pf_tbodyFacturas');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted small py-3">Cargando...</td></tr>';
        try {
            const data = await (await fetch(`${urlBase()}/getFacturasAjax?id=${id}`)).json();
            const lista = (data.ok && data.facturas) ? data.facturas : [];
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

    /* ── Pestaña "Info Productos" ───────────────────────────────
     * Se reconstruye a partir del DOM de la pestaña "Proforma" (misma fuente de
     * verdad), así que siempre refleja lo último aunque el usuario haya editado
     * la lista de detalle sin guardar todavía. El textarea de información
     * adicional escribe en vivo sobre el input .input-adicional de su propia fila. */
    function _renderInfoProductos() {
        const cont = $id('pf_gridInfoProductos');
        if (!cont) return;

        const filas = Array.from(document.querySelectorAll('#pf_tbodyDetalle .row-detalle'))
            .filter(tr => tr.querySelector('.input-descripcion')?.value.trim());

        if (!filas.length) {
            cont.innerHTML = '<div class="text-center text-muted small py-4">Agregue productos en la pestaña "Proforma" para verlos aquí.</div>';
            return;
        }

        const base = (typeof BASE_URL !== 'undefined' ? BASE_URL : (typeof B_URL !== 'undefined' ? B_URL : ''));
        cont.innerHTML = '';
        filas.forEach(tr => {
            const codigo   = tr.querySelector('.input-codigo')?.value.trim() || '';
            const nombre   = tr.querySelector('.input-descripcion')?.value   || '';
            const cantidad = tr.querySelector('.input-cantidad')?.value      || '';
            const imagen   = tr.querySelector('.input-imagen')?.value.trim() || '';
            const inpAdic  = tr.querySelector('.input-adicional');

            const card = document.createElement('div');
            card.className = 'pf-card-producto';
            card.innerHTML = `
                ${imagen
                    ? `<img class="pf-card-producto-img" src="${_esc(base + '/' + imagen)}" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'pf-card-producto-sinimg',innerHTML:'<i class=\\'bi bi-image\\' style=\\'font-size:2rem;opacity:.4\\'></i>'}))">`
                    : `<div class="pf-card-producto-sinimg"><i class="bi bi-image" style="font-size:1.1rem;opacity:.4"></i></div>`}
                <div class="pf-card-producto-body">
                    <div class="fw-bold mb-1">${codigo ? _esc(codigo) + ' — ' : ''}${_esc(nombre)}</div>
                    <div class="text-muted mb-1" style="font-size:0.62rem;">Cant.: ${_esc(cantidad)}</div>
                    <textarea class="form-control form-control-sm pf-card-producto-adicional" rows="1"
                        placeholder="Info. adicional...">${_esc(inpAdic?.value || '')}</textarea>
                </div>`;

            const ta = card.querySelector('.pf-card-producto-adicional');
            ta.addEventListener('input', () => {
                if (inpAdic) inpAdic.value = ta.value;
            });

            cont.appendChild(card);
        });
    }

    /* ── Pestaña "Plantillas" ────────────────────────────────────
     * Lista global de la empresa (no depende de la proforma actual). Guardar
     * toma una foto del detalle/adicional/vigencia visibles en ese momento;
     * usar reemplaza esos mismos datos en el formulario. */
    function _badgeVigenciaPlantilla(p) {
        const unidad = { dias: 'días', meses: 'meses', anios: 'años' }[p.vigencia_unidad] || p.vigencia_unidad;
        return `${_esc(p.dias_vigencia)} ${_esc(unidad)}`;
    }

    async function _cargarPlantillas() {
        const tbody = $id('pf_tbodyPlantillas');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted small py-3">Cargando...</td></tr>';
        try {
            const data = await (await fetch(`${urlBase()}/listarPlantillasAjax`)).json();
            const lista = (data.ok && data.plantillas) ? data.plantillas : [];
            if (!lista.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted small py-3">Aún no hay plantillas guardadas</td></tr>';
                return;
            }
            const puedeActualizar = !!perm().actualizar;
            const puedeEliminar   = !!perm().eliminar;
            tbody.innerHTML = lista.map(p => `
                <tr>
                    <td class="ps-3 small">${_esc(p.nombre)}</td>
                    <td class="small text-center">${_esc(p.total_items)}</td>
                    <td class="small">${_badgeVigenciaPlantilla(p)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" onclick="PF.usarPlantilla(${p.id})" title="Usar esta plantilla">
                            <i class="bi bi-box-arrow-in-down"></i> Usar
                        </button>
                        ${puedeActualizar ? `
                        <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 ms-1" onclick="PF.editarPlantilla(${p.id})" title="Editar plantilla">
                            <i class="bi bi-pencil"></i>
                        </button>` : ''}
                        ${puedeEliminar ? `
                        <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 ms-1" onclick="PF.eliminarPlantilla(${p.id})" title="Eliminar plantilla">
                            <i class="bi bi-trash3"></i>
                        </button>` : ''}
                    </td>
                </tr>`).join('');
        } catch (e) {
            console.error(e);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger small py-3">Error al cargar plantillas</td></tr>';
        }
    }

    /* ── Modal "Nueva plantilla" ─────────────────────────────────
     * Tabla de ítems independiente de la que usa la proforma en edición (clases
     * propias plt-*), con su propio autocomplete de producto pero sin recálculo
     * de subtotales/totales — una plantilla no muestra un total, solo guarda los
     * datos base para reusarlos. */
    function _crearFilaPlantilla(data = {}) {
        const idTarIva = parseInt(data.id_tarifa_iva || 0);
        const tr = document.createElement('tr');
        tr.className = 'row-plt-detalle';

        tr.innerHTML = `
        <td class="ps-3">
            <input type="text" class="form-control form-control-sm input-detalle plt-input-descripcion"
                value="${_esc(data.descripcion || '')}" placeholder="Buscar producto o escribe descripción...">
            <input type="hidden" class="plt-input-id-producto" value="${data.id_producto || ''}">
            <input type="hidden" class="plt-input-codigo" value="${_esc(data.codigo_principal || '')}">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm input-detalle plt-input-adicional text-muted fst-italic"
                value="${_esc(data.adicional || '')}" placeholder="Info adicional">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm input-detalle text-center plt-input-cantidad"
                value="${parseFloat(data.cantidad || 1).toFixed(decCant())}" step="any" min="0">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm input-detalle text-end plt-input-precio"
                value="${parseFloat(data.precio_unitario || 0).toFixed(decPrecio())}" step="any" min="0">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm input-detalle text-end plt-input-desc"
                value="${parseFloat(data.descuento || 0).toFixed(2)}" step="any" min="0">
        </td>
        <td>
            <select class="form-select form-select-sm input-detalle text-center plt-input-iva">
                ${_tivasOpts(idTarIva)}
            </select>
        </td>
        <td class="text-center" style="width:40px;">
            <button type="button" class="btn btn-link btn-sm p-0 text-danger shadow-none border-0" onclick="PF.eliminarFilaPlantilla(this)">
                <i class="bi bi-trash3 fs-6"></i>
            </button>
        </td>`;

        // Autocomplete descripción → productos (mismo patrón que la tabla de detalle
        // de la proforma, dropdown anclado al body para escapar el overflow de la tabla)
        const inpDesc = tr.querySelector('.plt-input-descripcion');
        const ddProd  = document.createElement('div');
        ddProd.className = 'list-group shadow d-none';
        ddProd.style.cssText = [
            'position:fixed', 'z-index:9999', 'min-width:340px', 'max-height:220px',
            'overflow-y:auto', 'background:#fff', 'border:1px solid #dee2e6',
            'border-radius:0 0 6px 6px', 'box-shadow:0 4px 16px rgba(0,0,0,.12)',
        ].join(';');
        document.body.appendChild(ddProd);
        tr._pltDdProd = ddProd;
        ddProd._pltTr = tr;

        function _posDdProd() {
            const r = inpDesc.getBoundingClientRect();
            ddProd.style.top   = r.bottom + 'px';
            ddProd.style.left  = r.left   + 'px';
            ddProd.style.width = Math.max(r.width, 340) + 'px';
        }

        inpDesc.addEventListener('keydown', e => {
            const idProd = tr.querySelector('.plt-input-id-producto');
            if ((e.key === 'Backspace' || e.key === 'Delete') && idProd && idProd.value) {
                e.preventDefault();
                inpDesc.value = '';
                idProd.value = '';
                tr.querySelector('.plt-input-codigo').value = '';
                ddProd.classList.add('d-none');
            }
        });

        let timer;
        inpDesc.addEventListener('input', () => {
            clearTimeout(timer);
            const q = inpDesc.value.trim();
            if (!q) {
                ddProd.classList.add('d-none');
                tr.querySelector('.plt-input-id-producto').value = '';
                tr.querySelector('.plt-input-codigo').value = '';
                return;
            }
            timer = setTimeout(async () => {
                try {
                    const res = await (await fetch(`${urlBase()}/getProductosAjax?q=${encodeURIComponent(q)}`)).json();
                    if (!res.ok || !res.data?.length) {
                        ddProd.innerHTML = '<div class="list-group-item text-muted small py-2 px-3">Sin resultados</div>';
                    } else {
                        ddProd.innerHTML = res.data.map(p =>
                            `<div class="list-group-item list-group-item-action py-1 px-3" style="font-size:0.8rem;cursor:pointer;"
                                 onclick='window._pltSelProd(this, ${JSON.stringify(p)})'>
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

        return tr;
    }

    window._pltSelProd = (el, p) => {
        const dd = el.closest('.list-group');
        const tr = dd?._pltTr;
        if (!tr) return;
        dd.classList.add('d-none');

        tr.querySelector('.plt-input-id-producto').value = p.id     || '';
        tr.querySelector('.plt-input-codigo').value      = p.codigo || '';
        tr.querySelector('.plt-input-descripcion').value = p.nombre || '';
        tr.querySelector('.plt-input-cantidad').value    = (1).toFixed(decCant());
        tr.querySelector('.plt-input-precio').value      = parseFloat(p.precio_base || 0).toFixed(decPrecio());
        tr.querySelector('.plt-input-desc').value        = '0.00';

        let pctFinal = null;
        if (p.porcentaje_iva_final !== undefined && p.porcentaje_iva_final !== null) {
            pctFinal = parseFloat(p.porcentaje_iva_final);
        } else if (p.porcentaje_iva !== undefined && p.porcentaje_iva !== null) {
            pctFinal = parseFloat(p.porcentaje_iva);
        } else if (p.porcentaje !== undefined && p.porcentaje !== null) {
            pctFinal = parseFloat(p.porcentaje);
        } else if (p.tarifa_iva) {
            const tFound = tivas().find(t => t.id == p.tarifa_iva);
            if (tFound) pctFinal = parseFloat(tFound.porcentaje_iva || tFound.porcentaje);
        }

        const selIva = tr.querySelector('.plt-input-iva');
        if (selIva && (pctFinal !== null || p.tarifa_iva)) {
            let opt = p.tarifa_iva ? Array.from(selIva.options).find(o => o.dataset.id == p.tarifa_iva) : null;
            if (!opt && pctFinal !== null) {
                opt = Array.from(selIva.options).find(o => Math.abs(parseFloat(o.value) - pctFinal) < 0.001);
            }
            if (opt) selIva.selectedIndex = opt.index;
            else if (pctFinal === 0) selIva.value = '0';
        }
    };

    function _recolectarPlantillaModal() {
        const detalles = [];
        document.querySelectorAll('#plt_tbodyDetalle .row-plt-detalle').forEach(tr => {
            const descripcion = tr.querySelector('.plt-input-descripcion')?.value.trim() || '';
            if (!descripcion) return;
            const sel = tr.querySelector('.plt-input-iva');
            detalles.push({
                id_producto:               tr.querySelector('.plt-input-id-producto')?.value || null,
                codigo_principal:          tr.querySelector('.plt-input-codigo')?.value.trim() || '',
                descripcion:               descripcion,
                adicional:                 tr.querySelector('.plt-input-adicional')?.value.trim() || '',
                cantidad:                  parseFloat(tr.querySelector('.plt-input-cantidad').value || 0),
                precio_unitario:           parseFloat(tr.querySelector('.plt-input-precio').value || 0),
                descuento:                 parseFloat(tr.querySelector('.plt-input-desc').value || 0),
                precio_total_sin_impuesto: 0,
                id_tarifa_iva:             parseInt(sel?.selectedOptions[0]?.dataset.id || 0),
            });
        });
        return detalles;
    }

    async function _cargarProforma(id) {
        try {
            const data = await (await fetch(`${urlBase()}/getProformaAjax?id=${id}`)).json();
            if (!data.ok) { toast(data.mensaje || 'Error al cargar', 'error'); return; }

            const c = data.cabecera;
            $id('pf_id').value = c.id;

            const estab = c.establecimiento || '';
            const punto = c.punto_emision   || '';
            const secue = String(c.secuencial || '').padStart(9, '0');
            $id('pf_tituloModal').textContent = `Proforma ${estab}-${punto}-${secue}`;

            // Bloquear cargarSecuencial ANTES de tocar el select
            // (igual que FV_BLOQUEAR_SECUENCIAL en factura de venta)
            _bloquearSecuencial = true;

            // La serie NO se puede cambiar al editar (el secuencial ya quedó fijado
            // al crear y no se recalcula para otra serie — ver ProformaService::actualizar).
            // Se deshabilita el select para que coincida con ese comportamiento; antes
            // se podía cambiar la serie sin que el secuencial se actualizara, dejando
            // la proforma con una serie nueva pero el número de la anterior.
            const selPunto = $id('pf_punto');
            if (selPunto) {
                let opt = c.id_punto_emision
                    ? Array.from(selPunto.options).find(o => parseInt(o.value) === parseInt(c.id_punto_emision))
                    : null;
                if (!opt && c.id_punto_emision) {
                    // El punto de emisión original ya no está activo (o fue eliminado de
                    // la lista): se agrega una opción con los datos guardados para no
                    // mostrar en silencio una serie distinta a la real.
                    opt = document.createElement('option');
                    opt.value = c.id_punto_emision;
                    opt.dataset.est      = c.id_establecimiento || '';
                    opt.dataset.codEst   = estab;
                    opt.dataset.codPunto = punto;
                    opt.textContent      = `${estab}-${punto} (inactivo)`;
                    selPunto.appendChild(opt);
                }
                Array.from(selPunto.options).forEach(o => { o.selected = (o === opt); });
                _syncEstab(selPunto);
                selPunto.disabled = true;
            }

            // Restaurar el secuencial guardado (formateado a 9 dígitos)
            const inputSec = $id('pf_secuencial');
            inputSec.value       = secue;
            inputSec.placeholder = '000000001';
            inputSec.classList.remove('border-warning');
            inputSec.title = '';

            $id('pf_fecha').value         = (c.fecha_emision || '').slice(0, 10);
            $id('pf_diasVigencia').value = c.dias_vigencia || 15;
            $id('pf_diasVigencia').value  = c.dias_vigencia || 15;
            $id('pf_vigenciaUnidad').value = c.vigencia_unidad || 'dias';
            $id('pf_vendedor').value      = c.id_vendedor   || '';
            $id('pf_observaciones').value = c.observaciones || '';
            _setCondicionesHtml(_quillProforma(), c.condiciones_html || '');

            // Cliente (sin insertar correo aún, se hará al cargar info_adicional)
            _limpiarCliente();
            if (c.id_cliente) {
                $id('pf_idCliente').value     = c.id_cliente;
                $id('pf_clienteBuscar').value = c.cliente_nombre || '';
                const infoDiv = $id('pf_infoCliente');
                if (infoDiv) infoDiv.classList.remove('d-none');
                const rucEl = $id('pf_clienteRuc');    if (rucEl) rucEl.textContent = c.cliente_ruc || '';
                const nomEl = $id('pf_clienteNombre'); if (nomEl) nomEl.textContent = c.cliente_nombre || '';
                const emlEl = $id('pf_clienteEmail');  if (emlEl) emlEl.textContent = c.cliente_email || '';
            }

            // Detalles
            $id('pf_tbodyDetalle').innerHTML = '';
            (data.detalles || []).forEach(d => {
                $id('pf_tbodyDetalle').appendChild(_crearFila(d));
            });
            _calcularTotales();

            // Info adicional (cargar guardadas + una vacía si no hay)
            const tbodyAd = $id('pf_tbodyAdicional');
            tbodyAd.innerHTML = '';
            (data.info_adicional || []).forEach(a => {
                tbodyAd.appendChild(_crearFilaAdicional(a.nombre, a.valor));
            });
            if (!tbodyAd.children.length) tbodyAd.appendChild(_crearFilaAdicional());

            _aplicarEstado(c.estado);

            // Aviso si el cliente aprobó desde el correo (fecha + comentario)
            const banner = $id('pf_aprobacionCliente');
            const bTexto = $id('pf_aprobacionClienteTexto');
            if (banner && bTexto) {
                if (c.aprobacion_cliente_fecha) {
                    const f = _fmtFechaHora(c.aprobacion_cliente_fecha);
                    const com = (c.aprobacion_cliente_comentario || '').trim();
                    bTexto.innerHTML = `Aprobada por el cliente el <strong>${_esc(f)}</strong>`
                        + (com ? ` — "<em>${_esc(com)}</em>"` : '');
                    banner.classList.remove('d-none');
                } else {
                    banner.classList.add('d-none');
                }
            }

            _cargarFacturas(id);
            _renderInfoProductos();
        } catch(e) {
            console.error(e);
            toast('Error de conexión', 'error');
        }
    }

    /* ── Helper escape HTML ──────────────────────────────────── */
    function _esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    /* ── Fecha 'YYYY-MM-DD HH:MM:SS' → 'd-m-Y H:i' ──────────────── */
    function _fmtFechaHora(v) {
        const s = String(v || '');
        const m = s.match(/(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        return m ? `${m[3]}-${m[2]}-${m[1]} ${m[4]}:${m[5]}` : s;
    }

    /* ════════════════════════════════════════════════════════════
     * BORRADOR EN LOCALSTORAGE — auto-guardado de proforma sin guardar
     * Réplica del mecanismo de factura de venta. Solo aplica a
     * proformas NUEVAS (pf_id vacío); editar una existente no toca el
     * borrador. Clave por empresa+usuario (PF_CONFIG.storageKey).
     * ════════════════════════════════════════════════════════════ */
    const _storageKey = () => CFG().storageKey || 'pf_borrador_0_0';

    /** Serializa el estado actual del modal (sin secuencial ni id). */
    function _capturarBorrador() {
        const estado = {};

        // Cliente
        estado.id_cliente      = $id('pf_idCliente')?.value     || '';
        estado.cliente_buscar  = $id('pf_clienteBuscar')?.value  || '';
        estado.cliente_ruc     = $id('pf_clienteRuc')?.textContent    || '';
        estado.cliente_nombre  = $id('pf_clienteNombre')?.textContent || '';
        estado.cliente_email   = $id('pf_clienteEmail')?.textContent  || '';

        // Cabecera
        estado.id_punto_emision = $id('pf_punto')?.value          || '';
        estado.fecha            = $id('pf_fecha')?.value          || '';
        estado.dias_vigencia    = $id('pf_diasVigencia')?.value   || '';
        estado.vigencia_unidad  = $id('pf_vigenciaUnidad')?.value || '';
        estado.id_vendedor      = $id('pf_vendedor')?.value       || '';
        estado.observaciones    = $id('pf_observaciones')?.value  || '';
        estado.condiciones_html = _condicionesHtml(_quillProforma());

        // Detalles
        estado.detalles = [];
        document.querySelectorAll('#pf_tbodyDetalle .row-detalle').forEach(tr => {
            const fila = {
                id_producto: tr.querySelector('.input-id-producto')?.value || '',
                codigo:      tr.querySelector('.input-codigo')?.value      || '',
                descripcion: tr.querySelector('.input-descripcion')?.value || '',
                adicional:   tr.querySelector('.input-adicional')?.value   || '',
                cantidad:    tr.querySelector('.input-cantidad')?.value    || '',
                precio:      tr.querySelector('.input-precio')?.value      || '',
                precio_iva:  tr.querySelector('.input-precio-iva')?.value  || '',
                descuento:   tr.querySelector('.input-desc')?.value        || '',
                iva_id:      tr.querySelector('.input-iva')?.selectedOptions[0]?.dataset.id || '',
            };
            if (fila.id_producto || fila.descripcion.trim()) estado.detalles.push(fila);
        });

        // Info adicional (solo filas libres, no la fija de correo del cliente)
        estado.info_adicional = [];
        document.querySelectorAll('#pf_tbodyAdicional tr:not([data-tipo])').forEach(tr => {
            const n = tr.querySelector('.inp-ad-nombre')?.value || '';
            const v = tr.querySelector('.inp-ad-valor')?.value  || '';
            if (n || v) estado.info_adicional.push({ nombre: n, valor: v });
        });

        return estado;
    }

    /** Guarda el estado actual en localStorage (solo si hay algo relevante). */
    function _autoGuardarBorrador() {
        // Solo proformas nuevas: si ya tiene id, no tocar el borrador.
        if ($id('pf_id')?.value) return;
        try {
            const estado = _capturarBorrador();
            if (!estado.id_cliente && !estado.detalles.length) {
                localStorage.removeItem(_storageKey());
                return;
            }
            localStorage.setItem(_storageKey(), JSON.stringify(estado));
        } catch (e) { /* localStorage lleno o deshabilitado */ }
    }

    /** Elimina el borrador guardado. */
    function _limpiarBorrador() {
        try { localStorage.removeItem(_storageKey()); } catch (e) {}
    }

    /** Lee el borrador guardado (o null). */
    function _leerBorrador() {
        try {
            const raw = localStorage.getItem(_storageKey());
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }

    /** Restaura un borrador en el modal (ya reseteado). */
    function _restaurarBorrador(estado) {
        // Cliente
        if (estado.id_cliente) {
            $id('pf_idCliente').value     = estado.id_cliente;
            $id('pf_clienteBuscar').value = estado.cliente_buscar || '';
            const infoDiv = $id('pf_infoCliente');
            if (infoDiv) infoDiv.classList.remove('d-none');
            const rucEl = $id('pf_clienteRuc');    if (rucEl) rucEl.textContent = estado.cliente_ruc    || '';
            const nomEl = $id('pf_clienteNombre'); if (nomEl) nomEl.textContent = estado.cliente_nombre || '';
            const emlEl = $id('pf_clienteEmail');  if (emlEl) emlEl.textContent = estado.cliente_email  || '';
        }

        // Cabecera
        if (estado.fecha)          $id('pf_fecha').value         = estado.fecha;
        if (estado.dias_vigencia)  $id('pf_diasVigencia').value  = estado.dias_vigencia;
        if (estado.vigencia_unidad && $id('pf_vigenciaUnidad')) $id('pf_vigenciaUnidad').value = estado.vigencia_unidad;
        if (estado.id_vendedor)    $id('pf_vendedor').value      = estado.id_vendedor;
        if (estado.observaciones)  $id('pf_observaciones').value = estado.observaciones;
        if (estado.condiciones_html) _setCondicionesHtml(_quillProforma(), estado.condiciones_html);

        // Detalles
        const tbody = $id('pf_tbodyDetalle');
        tbody.innerHTML = '';
        if (estado.detalles && estado.detalles.length) {
            estado.detalles.forEach(fila => {
                const tr = _crearFila();
                tbody.appendChild(tr);
                const set = (sel, val) => { const el = tr.querySelector(sel); if (el && val !== undefined) el.value = val; };
                set('.input-id-producto', fila.id_producto);
                set('.input-codigo',      fila.codigo);
                set('.input-descripcion', fila.descripcion);
                set('.input-adicional',   fila.adicional);
                set('.input-cantidad',    fila.cantidad);
                set('.input-precio',      fila.precio);
                set('.input-precio-iva',  fila.precio_iva);
                set('.input-desc',        fila.descuento);
                // IVA por id de tarifa
                if (fila.iva_id) {
                    const selIva = tr.querySelector('.input-iva');
                    const opt = selIva && Array.from(selIva.options).find(o => o.dataset.id == fila.iva_id);
                    if (opt) selIva.selectedIndex = opt.index;
                }
                _recalcFila(tr);
            });
        } else {
            tbody.appendChild(_crearFila());
        }

        // Info adicional libre (manteniendo la fila fija de correo si la hay)
        if (estado.info_adicional && estado.info_adicional.length) {
            const tbodyAd = $id('pf_tbodyAdicional');
            estado.info_adicional.forEach(item => {
                tbodyAd.appendChild(_crearFilaAdicional(item.nombre, item.valor));
            });
        }

        _calcularTotales();
    }

    /** Engancha listeners de auto-guardado sobre el modal (una sola vez). */
    let _borradorListenersOk = false;
    function _registrarAutoGuardado() {
        if (_borradorListenersOk) return;
        const modal = $id('modalProforma');
        if (!modal) return;
        let timer;
        const debounced = () => { clearTimeout(timer); timer = setTimeout(_autoGuardarBorrador, 800); };
        modal.addEventListener('input', debounced);
        modal.addEventListener('change', debounced);
        _borradorListenersOk = true;
    }

    /** Muestra el overlay "Proforma sin guardar" con opciones nueva/restaurar. */
    function _avisarBorrador(borrador) {
        const aviso = document.createElement('div');
        aviso.id = 'pf-borrador-aviso';
        aviso.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;';
        const clienteName = _esc(borrador.cliente_buscar || borrador.cliente_nombre || 'desconocido');
        aviso.innerHTML = `
            <div class="bg-white rounded-3 shadow-lg p-4" style="max-width:420px;width:90%;">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-4"></i>
                    <h6 class="fw-bold mb-0">Proforma sin guardar</h6>
                </div>
                <p class="small text-muted mb-4">Hay una proforma en borrador del cliente <strong>${clienteName}</strong> que no fue guardada. ¿Qué desea hacer?</p>
                <div class="d-flex gap-2 justify-content-end">
                    <button class="btn btn-sm btn-outline-secondary" id="pf-aviso-nueva">
                        <i class="bi bi-file-earmark-plus me-1"></i> Nueva proforma
                    </button>
                    <button class="btn btn-sm btn-primary" id="pf-aviso-restaurar">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Cargar borrador
                    </button>
                </div>
            </div>`;
        document.body.appendChild(aviso);

        $id('pf-aviso-restaurar').onclick = () => {
            aviso.remove();
            _bloquearSecuencial = false;
            _reset();
            _initClienteBuscar();
            const modalEl = $id('modalProforma');
            modalEl.addEventListener('shown.bs.modal', function onShown() {
                _restaurarBorrador(borrador);
                this.removeEventListener('shown.bs.modal', onShown);
            });
            getModal().show();
        };
        $id('pf-aviso-nueva').onclick = () => {
            _limpiarBorrador();
            aviso.remove();
            _bloquearSecuencial = false;
            _reset();
            _initClienteBuscar();
            getModal().show();
            if (typeof window.aplicarFavoritosModal === 'function') {
                window.aplicarFavoritosModal('#modalProforma');
            }
        };
    }

    /* ── API pública ─────────────────────────────────────────── */
    const PF = {

        nueva() {
            _registrarAutoGuardado();

            // ¿Hay una proforma en borrador sin guardar?
            const borrador = _leerBorrador();
            if (borrador && (borrador.id_cliente || (borrador.detalles && borrador.detalles.length))) {
                _avisarBorrador(borrador);
                return;
            }

            _bloquearSecuencial = false;
            _reset();
            _initClienteBuscar();
            getModal().show();
            if (typeof window.aplicarFavoritosModal === 'function') {
                window.aplicarFavoritosModal('#modalProforma');
            }
        },

        /** Limpia el borrador guardado (uso externo/manual si hiciera falta). */
        limpiarBorrador() { _limpiarBorrador(); },

        async verDetalle(id) {
            // Bloquear ANTES de _reset() (igual que factura de venta) para que
            // ninguna llamada asíncrona de _reset() pise el secuencial real que
            // está por cargar _cargarProforma().
            _bloquearSecuencial = true;
            _reset();
            _initClienteBuscar();
            await _cargarProforma(id);
            getModal().show();
        },

        async guardar() {
            const payload = _buildPayload();

            // Bloqueo: secuencial no configurado para la serie (solo al CREAR una nueva).
            // Va ANTES del chequeo de id_punto_emision: si la empresa no tiene ninguna
            // serie con secuencial de "Proformas", el select está vacío y el aviso útil
            // es este ("configure los puntos de emisión"), no "seleccione el punto".
            const _esNuevaProforma = !$id('pf_id')?.value;
            if (_esNuevaProforma && _secuencialConfigurado === false) {
                _avisarSecuencialNoConfigurado(payload.id_punto_emision ? 'secuencial' : 'serie');
                return;
            }

            if (!payload.id_punto_emision)   { toast('Seleccione el punto de emisión', 'error'); return; }

            if (!payload.secuencial)         { toast('Ingrese el secuencial', 'error'); return; }
            if (!payload.fecha_emision)      { toast('Ingrese la fecha de emisión', 'error'); return; }
            if (!payload.id_cliente)         { toast('Seleccione un cliente', 'error'); return; }
            if (!payload.detalles.length)    { toast('Agregue al menos un ítem', 'error'); return; }

            const btn = $id('pf_btnGuardar');
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...'; }

            try {
                const data = await (await fetch(`${urlBase()}/guardarAjax`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                })).json();

                if (!data.ok) { toast(data.error || 'Error al guardar', 'error'); return; }

                _limpiarBorrador();
                toast(data.msg || 'Guardado correctamente');

                // No cerrar el modal: recargar la proforma guardada (igual que factura de
                // venta). Así queda con su número/estado, secuencial bloqueado y la barra
                // de acciones (Factura, Recibo, PDF, etc.) habilitada para seguir operando.
                if (data.id) await _cargarProforma(data.id);

                if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
            } catch(e) {
                console.error(e);
                toast('Error de conexión', 'error');
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar'; }
            }
        },

        async cambiarEstado(estado) {
            const id = $id('pf_id').value;
            if (!id) return;

            const msgs = {
                aprobada:  '¿Aprobar esta proforma?',
                rechazada: '¿Rechazar esta proforma?',
                anulada:   '¿Anular esta proforma? Esta acción no se puede deshacer.',
            };
            if (!(await confirm2(msgs[estado] || `¿Cambiar estado a "${estado}"?`))) return;

            try {
                const fd = new FormData();
                fd.append('id', id); fd.append('estado', estado);
                const data = await (await fetch(`${urlBase()}/cambiarEstadoAjax`, { method:'POST', body:fd })).json();
                if (!data.ok) { toast(data.error || 'Error', 'error'); return; }
                toast('Estado actualizado');
                getModal().hide();
                if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
            } catch(e) { console.error(e); toast('Error de conexión', 'error'); }
        },

        async eliminar() {
            const id = $id('pf_id').value;
            if (!id) return;
            if (!(await confirm2('¿Eliminar esta proforma?'))) return;

            try {
                const fd = new FormData();
                fd.append('id', id);
                const data = await (await fetch(`${urlBase()}/eliminarAjax`, { method:'POST', body:fd })).json();
                if (!data.ok) { toast(data.error || 'Error al eliminar', 'error'); return; }
                toast('Proforma eliminada');
                getModal().hide();
                if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
            } catch(e) { console.error(e); toast('Error de conexión', 'error'); }
        },

        exportarPdf() {
            const id = $id('pf_id').value;
            if (!id) { toast('Guarde la proforma antes de exportar el PDF', 'error'); return; }
            // El endpoint responde con Content-Disposition: attachment → descarga directa
            // (sin abrir una pestaña en blanco).
            const a = document.createElement('a');
            a.href = `${urlBase()}/exportarPdfAjax?id=${id}`;
            a.rel = 'noopener';
            document.body.appendChild(a);
            a.click();
            a.remove();
        },

        exportarExcel() {
            const id = $id('pf_id').value;
            if (!id) { toast('Guarde la proforma antes de exportar el Excel', 'error'); return; }
            const a = document.createElement('a');
            a.href = `${urlBase()}/exportarExcelAjax?id=${id}`;
            a.rel = 'noopener';
            document.body.appendChild(a);
            a.click();
            a.remove();
        },

        imprimirFichaProductos() {
            const id = $id('pf_id').value;
            if (!id) { toast('Guarde la proforma antes de generar la ficha', 'error'); return; }
            const a = document.createElement('a');
            a.href = `${urlBase()}/exportarFichaProductosAjax?id=${id}`;
            a.rel = 'noopener';
            document.body.appendChild(a);
            a.click();
            a.remove();
        },

        /** PDF anexo con las condiciones (se genera desde lo GUARDADO en la proforma). */
        imprimirCondiciones() {
            const id = $id('pf_id').value;
            if (!id) { toast('Guarde la proforma antes de generar el PDF de condiciones', 'error'); return; }
            if (!_condicionesHtml(_quillProforma())) {
                toast('La proforma no tiene condiciones. Escríbalas y guarde antes de descargar el PDF', 'error');
                return;
            }
            const a = document.createElement('a');
            a.href = `${urlBase()}/exportarCondicionesAjax?id=${id}`;
            a.rel = 'noopener';
            document.body.appendChild(a);
            a.click();
            a.remove();
        },

        /* ── Plantillas ──────────────────────────────────────── */
        abrirPlantillas() {
            _cargarPlantillas();
            bootstrap.Modal.getOrCreateInstance($id('modalListaPlantillas')).show();
        },

        nuevaPlantilla() {
            $id('plt_id').value = '';
            $id('plt_nombre').value = '';
            $id('plt_diasVigencia').value = 15;
            $id('plt_vigenciaUnidad').value = 'dias';
            _setCondicionesHtml(_quillPlantilla(), '');
            if ($id('plt_modalTitulo')) $id('plt_modalTitulo').textContent = 'Nueva plantilla';

            const tbody = $id('plt_tbodyDetalle');
            tbody.innerHTML = '';
            tbody.appendChild(_crearFilaPlantilla());

            const tbodyAd = $id('plt_tbodyAdicional');
            tbodyAd.innerHTML = '';
            tbodyAd.appendChild(_crearFilaAdicional());

            bootstrap.Modal.getOrCreateInstance($id('modalPlantilla')).show();
        },

        async editarPlantilla(idPlantilla) {
            try {
                const data = await (await fetch(`${urlBase()}/getPlantillaAjax?id=${idPlantilla}`)).json();
                if (!data.ok) { toast(data.mensaje || 'Error al cargar la plantilla', 'error'); return; }
                const p = data.plantilla;

                $id('plt_id').value = p.id;
                $id('plt_nombre').value = p.nombre || '';
                $id('plt_diasVigencia').value = p.dias_vigencia || 15;
                $id('plt_vigenciaUnidad').value = p.vigencia_unidad || 'dias';
                _setCondicionesHtml(_quillPlantilla(), p.condiciones_html || '');
                if ($id('plt_modalTitulo')) $id('plt_modalTitulo').textContent = 'Editar plantilla';

                const tbody = $id('plt_tbodyDetalle');
                tbody.innerHTML = '';
                (p.detalles || []).forEach(d => {
                    tbody.appendChild(_crearFilaPlantilla({ ...d, adicional: d.info_adicional }));
                });
                if (!tbody.children.length) tbody.appendChild(_crearFilaPlantilla());

                const tbodyAd = $id('plt_tbodyAdicional');
                tbodyAd.innerHTML = '';
                (p.info_adicional || []).forEach(a => tbodyAd.appendChild(_crearFilaAdicional(a.nombre, a.valor)));
                if (!tbodyAd.children.length) tbodyAd.appendChild(_crearFilaAdicional());

                bootstrap.Modal.getOrCreateInstance($id('modalPlantilla')).show();
            } catch (e) {
                console.error(e);
                toast('Error de conexión', 'error');
            }
        },

        agregarFilaPlantilla() {
            $id('plt_tbodyDetalle')?.appendChild(_crearFilaPlantilla());
        },

        agregarAdicionalPlantilla() {
            $id('plt_tbodyAdicional')?.appendChild(_crearFilaAdicional());
        },

        eliminarFilaPlantilla(btn) {
            const tr = btn.closest('tr');
            if (tr?._pltDdProd) tr._pltDdProd.remove();
            tr?.remove();
        },

        async guardarPlantillaModal() {
            const nombre = $id('plt_nombre').value.trim();
            if (!nombre) { toast('Ingrese un nombre para la plantilla', 'error'); return; }

            const detalles = _recolectarPlantillaModal();
            if (!detalles.length) { toast('Agregue al menos un ítem con descripción', 'error'); return; }

            const info_adicional = [];
            document.querySelectorAll('#plt_tbodyAdicional tr').forEach(tr => {
                const n = tr.querySelector('.inp-ad-nombre')?.value.trim() || '';
                const v = tr.querySelector('.inp-ad-valor')?.value.trim()  || '';
                if (n && v) info_adicional.push({ nombre: n, valor: v });
            });

            const payload = {
                id:              parseInt($id('plt_id').value || 0),
                nombre,
                dias_vigencia:   parseInt($id('plt_diasVigencia').value || 15),
                vigencia_unidad: $id('plt_vigenciaUnidad').value || 'dias',
                condiciones_html: _condicionesHtml(_quillPlantilla()),
                detalles,
                info_adicional,
            };

            const btn = $id('plt_btnGuardar');
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...'; }
            try {
                const resp = await fetch(`${urlBase()}/guardarPlantillaAjax`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await resp.json();
                if (!data.ok) { toast(data.mensaje || 'Error al guardar la plantilla', 'error'); return; }
                toast('Plantilla guardada correctamente');
                bootstrap.Modal.getInstance($id('modalPlantilla'))?.hide();
                _cargarPlantillas();
            } catch (e) {
                console.error(e);
                toast('Error de conexión', 'error');
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Guardar'; }
            }
        },

        async usarPlantilla(idPlantilla) {
            const tieneDatos = Array.from(document.querySelectorAll('#pf_tbodyDetalle .row-detalle'))
                .some(tr => tr.querySelector('.input-descripcion')?.value.trim());
            if (tieneDatos) {
                const ok = await confirm2('Esto reemplazará el detalle, la información adicional, la vigencia y las condiciones actuales por los de la plantilla.');
                if (!ok) return;
            }

            try {
                const data = await (await fetch(`${urlBase()}/getPlantillaAjax?id=${idPlantilla}`)).json();
                if (!data.ok) { toast(data.mensaje || 'Error al cargar la plantilla', 'error'); return; }
                const p = data.plantilla;

                const tbody = $id('pf_tbodyDetalle');
                tbody.innerHTML = '';
                (p.detalles || []).forEach(d => {
                    tbody.appendChild(_crearFila({ ...d, adicional: d.info_adicional }));
                });
                if (!tbody.children.length) tbody.appendChild(_crearFila());
                _calcularTotales();

                const tbodyAd = $id('pf_tbodyAdicional');
                tbodyAd.innerHTML = '';
                (p.info_adicional || []).forEach(a => tbodyAd.appendChild(_crearFilaAdicional(a.nombre, a.valor)));
                if (!tbodyAd.children.length) tbodyAd.appendChild(_crearFilaAdicional());

                if ($id('pf_diasVigencia'))   $id('pf_diasVigencia').value   = p.dias_vigencia   || 15;
                if ($id('pf_vigenciaUnidad')) $id('pf_vigenciaUnidad').value = p.vigencia_unidad || 'dias';
                _setCondicionesHtml(_quillProforma(), p.condiciones_html || '');

                _renderInfoProductos();

                // Cerrar el modal de plantillas y mostrar la pestaña Proforma para
                // que se vea el detalle recién aplicado.
                bootstrap.Modal.getInstance($id('modalListaPlantillas'))?.hide();
                const tabProforma = $id('pf-tab-proforma-btn');
                if (tabProforma) {
                    (bootstrap.Tab.getInstance(tabProforma) || new bootstrap.Tab(tabProforma)).show();
                }

                toast('Plantilla aplicada');
            } catch (e) {
                console.error(e);
                toast('Error de conexión', 'error');
            }
        },

        async eliminarPlantilla(idPlantilla) {
            const ok = await confirm2('¿Eliminar esta plantilla? Esta acción no se puede deshacer.');
            if (!ok) return;
            try {
                const fd = new FormData();
                fd.append('id', idPlantilla);
                const data = await (await fetch(`${urlBase()}/eliminarPlantillaAjax`, { method: 'POST', body: fd })).json();
                if (!data.ok) { toast(data.mensaje || 'Error al eliminar', 'error'); return; }
                toast('Plantilla eliminada');
                _cargarPlantillas();
            } catch (e) {
                console.error(e);
                toast('Error de conexión', 'error');
            }
        },

        async enviarWhatsapp() {
            const id = $id('pf_id').value;
            if (!id) { toast('Guarde la proforma antes de enviarla', 'error'); return; }

            const modalProf = document.getElementById('modalProforma');
            const select    = $id('pf_waSelectPlantilla');
            select.innerHTML = '<option value="">Cargando plantillas...</option>';
            $id('pf_waId').value = id;

            try {
                const data = await (await fetch(`${urlBase()}/getPlantillasWhatsappAjax?id=${id}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })).json();

                if (!data.ok) { toast(data.error || 'Error al cargar plantillas', 'error'); return; }

                if (data.configurado === false) {
                    Swal.fire({
                        icon: 'info',
                        title: 'WhatsApp no configurado',
                        html: 'Aún no tienes configurado el servicio oficial de <b>WhatsApp Business API</b>. Contacta a soporte para activarlo.',
                        confirmButtonText: 'Entendido',
                        target: modalProf || undefined,
                    });
                    return;
                }
                if (!data.plantillas || !data.plantillas.length) {
                    Swal.fire({
                        icon: 'info', title: 'Sin plantillas',
                        text: 'No hay plantillas de WhatsApp aprobadas por Meta para enviar la proforma.',
                        target: modalProf || undefined,
                    });
                    return;
                }

                select.innerHTML = '<option value="">— Seleccione una plantilla —</option>';
                data.plantillas.forEach(p => {
                    select.innerHTML += `<option value="${p.id}" data-nombre="${_esc(p.nombre)}">${_esc(p.nombre)} (${_esc(p.idioma)})</option>`;
                });
                if (data.id_plantilla_default) select.value = data.id_plantilla_default;

                $id('pf_waTelefono').value = data.telefono_cliente || '593';

                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEnviarWhatsappProforma')).show();
            } catch (e) {
                console.error(e);
                toast('Error de conexión', 'error');
            }
        },

        async enviarCorreo() {
            const id = $id('pf_id').value;
            if (!id) { toast('Guarde la proforma antes de enviarla', 'error'); return; }

            const correoActual = ($id('pf_clienteEmail')?.textContent || '').trim();
            const modalEl = document.getElementById('modalProforma');
            // El anexo de condiciones va siempre con la proforma (se genera desde lo guardado);
            // aquí solo se avisa. El editor refleja lo que se cargó de la proforma.
            const tieneCond = !!_condicionesHtml(_quillProforma());

            const { value: formValues, isConfirmed } = await Swal.fire({
                title: 'Enviar proforma por correo',
                html: `
                    <label class="form-label small fw-bold text-start d-block mb-1">Correos electrónicos (separados por coma o espacio)</label>
                    <input id="pf-swal-correos" class="swal2-input m-0" style="width:100%;" value="${_esc(correoActual)}" placeholder="cliente@correo.com">
                    <div class="form-check text-start mt-3">
                        <input class="form-check-input" type="checkbox" id="pf-swal-ficha">
                        <label class="form-check-label small" for="pf-swal-ficha">
                            Adjuntar ficha de productos con imágenes
                        </label>
                    </div>
                    ${tieneCond ? `<div class="text-start small text-muted mt-2">
                        <i class="bi bi-paperclip me-1"></i>Se adjuntará también el PDF de condiciones de la proforma.
                    </div>` : ''}`,
                target: modalEl || undefined,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-send me-1"></i> Enviar',
                cancelButtonText: 'Cancelar',
                focusConfirm: false,
                preConfirm: () => {
                    const correos = document.getElementById('pf-swal-correos')?.value.trim() || '';
                    if (!correos) { Swal.showValidationMessage('Ingrese al menos un correo válido.'); return false; }
                    return { correos, adjuntarFicha: !!document.getElementById('pf-swal-ficha')?.checked };
                }
            });
            if (!isConfirmed) return;
            const { correos, adjuntarFicha } = formValues;

            Swal.fire({
                title: 'Enviando correo...',
                text: 'Por favor espera',
                allowOutsideClick: false,
                target: modalEl || undefined,
                didOpen: () => Swal.showLoading(),
            });

            try {
                const fd = new FormData();
                fd.append('id', id);
                fd.append('correos', correos);
                fd.append('adjuntarFicha', adjuntarFicha ? '1' : '0');

                const resp = await fetch(`${urlBase()}/enviarCorreoAjax`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });
                const data = await resp.json();

                if (data.ok) {
                    // Refrescar el listado para que la columna "Correo" muestre "Enviado".
                    if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
                    Swal.fire({
                        icon: 'success',
                        title: '¡Enviado!',
                        text: data.mensaje || 'Correo enviado correctamente.',
                        target: modalEl || undefined,
                        timer: 2500,
                        showConfirmButton: false,
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'No se pudo enviar',
                        text: data.mensaje || 'Verifique la configuración de correo.',
                        target: modalEl || undefined,
                    });
                }
            } catch (e) {
                console.error(e);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión al enviar el correo.', target: modalEl || undefined });
            }
        },

        async convertirAFactura() {
            const id = $id('pf_id').value;
            if (!id) return;
            await this._convertirEnFactura(id, false);
        },

        async _convertirEnFactura(id, forzar) {
            try {
                const body = new URLSearchParams();
                body.append('id', id);
                if (forzar) body.append('forzar', '1');

                const resp = await fetch(`${urlBase()}/convertirAFacturaAjax`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body
                });
                const data = await resp.json();

                // La proforma ya tiene una factura asociada: confirmar antes de crear otra
                if (data.requiere_confirmacion) {
                    const r = await Swal.fire({
                        icon: 'warning',
                        title: 'Ya existe una factura',
                        text: data.mensaje || 'Esta proforma ya tiene una factura asociada. ¿Desea continuar y crear otra?',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, crear otra',
                        cancelButtonText: 'Cancelar'
                    });
                    if (r.isConfirmed) await this._convertirEnFactura(id, true);
                    return;
                }

                // La configuración de la empresa exige stock y no hay saldo suficiente.
                if (data.stock_insuficiente) { _mostrarStockInsuficiente(data.faltantes); return; }

                if (!data.ok) { toast(data.error || 'No se pudo crear la factura', 'error'); return; }

                // Nos quedamos en el modal de proforma: la proforma queda marcada como
                // facturada y NO se ocultan los botones (solo se actualiza el badge).
                const badge = $id('pf_estadoBadge');
                if (badge) {
                    badge.className = BADGE_CLASSES.convertida;
                    badge.textContent = BADGE_LABELS.convertida;
                    badge.classList.remove('d-none');
                }

                // Refrescar la pestaña "Detalle de facturas" para que aparezca la recién creada.
                _cargarFacturas(id);

                Swal.fire({
                    icon: 'success',
                    title: 'Factura generada',
                    text: 'Se ha generado una factura de venta para que sea revisada y autorizada.',
                    confirmButtonText: 'Aceptar'
                });
            } catch (e) {
                console.error(e);
                toast('Error de conexión', 'error');
            }
        },

        crearPedido() {
            toast('Crear pedido próximamente', 'info');
        },

        async crearReciboVenta() {
            const id = $id('pf_id').value;
            if (!id) return;

            const r = await Swal.fire({
                icon: 'question',
                title: 'Generar recibo de venta',
                text: 'Se generará un recibo de venta (borrador) a partir de esta proforma. ¿Desea continuar?',
                showCancelButton: true,
                confirmButtonText: 'Sí, generar',
                cancelButtonText: 'Cancelar'
            });
            if (!r.isConfirmed) return;

            try {
                const body = new URLSearchParams();
                body.append('id', id);

                const resp = await fetch(`${urlBase()}/convertirAReciboAjax`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body
                });
                const data = await resp.json();

                // La configuración de la empresa exige stock y no hay saldo suficiente.
                if (data.stock_insuficiente) { _mostrarStockInsuficiente(data.faltantes); return; }

                if (!data.ok) { toast(data.error || 'No se pudo generar el recibo', 'error'); return; }

                Swal.fire({
                    icon: 'success',
                    title: 'Recibo generado',
                    text: 'Se ha generado un recibo de venta (borrador) para que sea revisado y emitido.',
                    confirmButtonText: 'Aceptar'
                });
            } catch (e) {
                console.error(e);
                toast('Error de conexión', 'error');
            }
        },

        duplicar() {
            const id = $id('pf_id').value;
            if (!id) return;
            toast('Duplicar proforma próximamente', 'info');
        },

        agregarFila() {
            const tbody = $id('pf_tbodyDetalle');
            if (!tbody) return;
            const tr = _crearFila();
            tbody.appendChild(tr);
            _calcularTotales();
            tr.querySelector('.input-descripcion').focus();
        },

        // Abrir el modal de creación de cliente (mismo modal que Facturas de Venta).
        // Al guardar, el evento 'clienteGuardado' lo selecciona en la proforma.
        nuevoCliente() {
            if (typeof window.abrirModalClienteCrear === 'function') {
                window.abrirModalClienteCrear();
            } else {
                toast('No se pudo abrir el formulario de cliente.', 'error');
            }
        },

        // Abrir el modal de creación de producto (mismo modal que Facturas de Venta).
        // Al guardar, el evento 'productoGuardado' agrega una línea con ese producto.
        nuevoProducto() {
            if (typeof window.abrirModalProductoCrear === 'function') {
                window.abrirModalProductoCrear();
            } else {
                toast('No se pudo abrir el formulario de producto.', 'error');
            }
        },

        eliminarFila(btn) {
            const tr = btn.closest('tr');
            if (tr?._pfDdProd) tr._pfDdProd.remove();  // limpiar dropdown del body
            tr?.remove();
            _calcularTotales();
        },

        agregarAdicional() {
            $id('pf_tbodyAdicional')?.appendChild(_crearFilaAdicional());
        },

        /* ── Descuento rápido ────────────────────────────────── */
        _trDescTarget: null,
        _modalDescInst: null,

        _abrirDescuento(btn) {
            const tr = btn.closest('tr');
            if (!tr) return;
            PF._trDescTarget = tr;

            if (!PF._modalDescInst) {
                PF._modalDescInst = new bootstrap.Modal($id('pf_modalDescuento'));
            }

            const valActual = parseFloat(tr.querySelector('.input-desc').value) || 0;
            $id('pf_inputDescVal').value  = valActual;
            $id('pf_inputDescCalc').value = valActual.toFixed(2);

            // Si hay valor previo → modo valor; si no → porcentaje
            if (valActual > 0) {
                document.getElementById('pfDescVal').checked  = true;
                document.getElementById('pfDescPorc').checked = false;
            } else {
                document.getElementById('pfDescPorc').checked = true;
                document.getElementById('pfDescVal').checked  = false;
            }
            $id('pf_checkDescTodo').checked = false;
            PF._validarCalcDesc();
            PF._modalDescInst.show();
            setTimeout(() => { $id('pf_inputDescVal')?.focus(); $id('pf_inputDescVal')?.select(); }, 400);
        },

        _validarCalcDesc() {
            const tr = PF._trDescTarget;
            if (!tr) return;
            const tipo   = document.querySelector('input[name="pfTipoDesc"]:checked')?.value || 'P';
            const val    = parseFloat($id('pf_inputDescVal').value) || 0;
            const cant   = parseFloat(tr.querySelector('.input-cantidad').value) || 1;
            const precio = parseFloat(tr.querySelector('.input-precio').value) || 0;
            const sub    = precio * cant;
            $id('pf_inputDescCalc').value = r2(tipo === 'P' ? sub * (val / 100) : val).toFixed(2);
        },

        _confirmarDescuento() {
            const tipo       = document.querySelector('input[name="pfTipoDesc"]:checked')?.value || 'P';
            const val        = parseFloat($id('pf_inputDescVal').value) || 0;
            const aplicarTodo = $id('pf_checkDescTodo').checked;

            if (val < 0) {
                toast('El descuento no puede ser negativo', 'warning');
                return;
            }

            const filas = aplicarTodo
                ? document.querySelectorAll('#pf_tbodyDetalle .row-detalle')
                : [PF._trDescTarget];

            // Validar que el descuento no exceda el subtotal
            let todasValidas = true;
            filas.forEach(tr => {
                if (!tr) return;
                const p = parseFloat(tr.querySelector('.input-precio').value) || 0;
                const c = parseFloat(tr.querySelector('.input-cantidad').value) || 1;
                const subtotal = p * c;
                const finalDesc = tipo === 'P' ? subtotal * (val / 100) : val;

                if (finalDesc > subtotal) {
                    todasValidas = false;
                }
            });

            if (!todasValidas) {
                toast('El descuento no puede ser mayor al subtotal de la línea', 'warning');
                return;
            }

            // Aplicar descuento si pasa validaciones
            filas.forEach(tr => {
                if (!tr) return;
                const p = parseFloat(tr.querySelector('.input-precio').value) || 0;
                const c = parseFloat(tr.querySelector('.input-cantidad').value) || 1;
                const finalDesc = tipo === 'P' ? (p * c) * (val / 100) : val;
                const inp = tr.querySelector('.input-desc');
                inp.value = r2(finalDesc).toFixed(2);
                inp.dispatchEvent(new Event('input', { bubbles: true }));
            });

            PF._modalDescInst?.hide();
        },
    };

    /* ── Foco al terminar la animación de apertura ──────────── */
    document.addEventListener('shown.bs.modal', e => {
        if (e.target.id !== 'modalProforma') return;
        // Si es nueva proforma (sin id), foco en cliente; si es edición, foco en descripción
        if (!$id('pf_id')?.value) {
            $id('pf_clienteBuscar')?.focus();
        } else {
            document.querySelector('#pf_tbodyDetalle .input-descripcion')?.focus();
        }
    });

    /* ── Refrescar "Info Productos" al abrir esa pestaña ────── */
    document.addEventListener('shown.bs.tab', e => {
        if (e.target?.id === 'pf-tab-productos-btn') _renderInfoProductos();
    });

    /* ── Limpiar dropdowns del body al cerrar el modal ──────── */
    document.addEventListener('hidden.bs.modal', e => {
        if (e.target.id === 'modalProforma') {
            document.querySelectorAll('#pf_tbodyDetalle .row-detalle').forEach(tr => {
                if (tr._pfDdProd) { tr._pfDdProd.remove(); tr._pfDdProd = null; }
            });
        }
        if (e.target.id === 'modalPlantilla') {
            document.querySelectorAll('#plt_tbodyDetalle .row-plt-detalle').forEach(tr => {
                if (tr._pltDdProd) { tr._pltDdProd.remove(); tr._pltDdProd = null; }
            });
        }
    });

    /* ── Inicializar eventos ─────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', () => {
        const selPunto = $id('pf_punto');
        if (selPunto) {
            // Igual que syncSerie() en factura de venta:
            // siempre sincroniza el establecimiento y recarga el secuencial
            // (el flag _bloquearSecuencial lo protege en modo edición)
            selPunto.addEventListener('change', () => {
                _syncEstab(selPunto);
                _cargarSecuencial(selPunto.value);
            });
        }

        // Envío por WhatsApp (formulario del modal)
        const formWa = document.getElementById('pf_formWhatsapp');
        if (formWa) {
            formWa.addEventListener('submit', async (e) => {
                e.preventDefault();
                const idProf = $id('pf_waId').value;
                const idPlan = $id('pf_waSelectPlantilla').value;
                const tel    = $id('pf_waTelefono').value.trim();
                const waEl   = document.getElementById('modalEnviarWhatsappProforma');

                if (!idPlan) { toast('Seleccione una plantilla', 'error'); return; }
                if (!tel)    { toast('Ingrese el teléfono del cliente', 'error'); return; }

                const btn  = $id('pf_btnEnviarWhatsapp');
                const orig = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando...';

                try {
                    const fd = new FormData();
                    fd.append('id', idProf);
                    fd.append('id_plantilla', idPlan);
                    fd.append('telefono', tel);

                    const data = await (await fetch(`${urlBase()}/enviarWhatsappAjax`, {
                        method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })).json();

                    if (data.ok) {
                        bootstrap.Modal.getInstance(waEl)?.hide();
                        Swal.fire({
                            icon: 'success', title: '¡Enviado!',
                            text: data.mensaje || 'Proforma enviada por WhatsApp.',
                            target: document.getElementById('modalProforma') || undefined,
                            timer: 2500, showConfirmButton: false,
                        });
                    } else {
                        Swal.fire({
                            icon: 'error', title: 'Error',
                            text: data.error || 'No se pudo enviar por WhatsApp.',
                            target: waEl || undefined,
                        });
                    }
                } catch (err) {
                    console.error(err);
                    toast('Error de conexión', 'error');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                }
            });
        }
    });

    // ─── Crear cliente/producto al vuelo desde la proforma ─────────────────────
    // Los modales de Clientes y Productos emiten estos eventos al guardar. Solo se
    // actúa si el modal de proforma está abierto (evita interferir con otros módulos).
    const _pfModalAbierto = () =>
        document.getElementById('modalProforma')?.classList.contains('show');

    document.addEventListener('clienteGuardado', (e) => {
        if (!_pfModalAbierto()) return;
        const res = e.detail;
        if (res && res.ok && res.data) {
            _seleccionarCliente(res.data);
        }
    });

    document.addEventListener('productoGuardado', async (e) => {
        if (!_pfModalAbierto()) return;
        const res = e.detail;
        if (!res || !res.ok || !res.id) return;

        // El /store de productos solo devuelve el id; recuperamos el producto
        // completo buscándolo por su código (el form aún conserva el valor).
        const codigo  = document.getElementById('prod_codigo')?.value?.trim() || '';
        const nombre  = document.getElementById('prod_nombre')?.value?.trim() || '';
        const termino = codigo || nombre;
        if (!termino) return;

        try {
            const resp = await fetch(`${urlBase()}/getProductosAjax?q=${encodeURIComponent(termino)}`);
            const data = await resp.json();
            if (data.ok && Array.isArray(data.data)) {
                const prod = data.data.find(p => String(p.id) === String(res.id)) || data.data[0];
                if (prod) {
                    const tbody = $id('pf_tbodyDetalle');
                    const tr    = _crearFila();
                    tbody.appendChild(tr);
                    _pfLlenarFilaProducto(tr, prod);
                    _calcularTotales();
                    return;
                }
            }
        } catch (err) {
            console.error('Error al recuperar el producto recién creado:', err);
        }
        toast('Producto creado. Búscalo en el detalle para agregarlo.', 'info');
    });

    window.PF = PF;
})();
