/**
 * Módulo Servicio Externo — modal de orden (cliente, equipo atendido, repuestos/
 * mano de obra) y generación de Factura/Recibo. Clon del JS (antes inline) de
 * Car-Wash, sin lógica de vehículo/novedades y con cliente + equipo obligatorios.
 */
(function () {
    const RUTA = window.RUTA_MODULO_SE;
    const DEC_P = (window.EMPRESA_CONFIG && window.EMPRESA_CONFIG.decimales_precio) || 2;
    let modal, cliTimer = null;
    let SE_CUR = { id: 0, id_documento: 0, tipo_documento: '', estado: '' };

    function getModal() { if (!modal) modal = new bootstrap.Modal(document.getElementById('modalOrdenSE')); return modal; }
    function num(v) { const n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function seFocus(id) { const el = document.getElementById(id); if (el) { try { el.focus(); if (el.select) el.select(); } catch (e) {} } }

    // ─── Reset / apertura ─────────────────────────────────────────────────────
    function resetForm() {
        document.getElementById('formOrdenSE').reset();
        ['se_id','se_id_cliente','se_serie','se_id_punto_emision','se_id_establecimiento','se_numero_orden'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('se_secuencial').value = '';
        document.getElementById('se_secuencial').dataset.sec = '';
        const selSerie = document.getElementById('se_select_serie');
        if (selSerie) { selSerie.selectedIndex = 0; selSerie.disabled = false; }
        document.getElementById('se_tbodyDetalle').innerHTML = '';
        document.getElementById('se_info_body').innerHTML = '';
        document.getElementById('se_info_cliente').innerHTML = '';
        SE_CUR = { id: 0, id_documento: 0, tipo_documento: '', estado: '' };
        window.SE_SECUENCIAL_CONFIGURADO = true;
        seToggleDocBtns(false);
        seRecalcular();
    }

    function setEditable(editable) {
        ['se_fecha_servicio','se_select_serie','se_cliente_busqueda','se_equipo_descripcion','se_equipo_marca',
         'se_equipo_modelo','se_equipo_serie','se_id_bodega','se_direccion_servicio','se_descripcion_trabajo','se_observaciones'].forEach(id => {
            const el = document.getElementById(id); if (el) el.disabled = !editable;
        });
        document.getElementById('se_btn_guardar').classList.toggle('d-none', !editable);
    }

    window.seAbrirNuevo = function () {
        resetForm();
        setEditable(true);
        document.getElementById('seTitulo').innerHTML = '<i class="bi bi-tools me-1 text-info"></i> Nueva orden de Servicio Externo';
        pintarBadge('borrador', 'Borrador');
        document.getElementById('se_btn_eliminar').classList.add('d-none');
        // fecha/hora local
        const d = new Date(); const off = d.getTimezoneOffset();
        document.getElementById('se_fecha_servicio').value = new Date(d.getTime() - off * 60000).toISOString().slice(0, 16);
        // Serie: arranca marcada (primer punto o favorito) y carga el secuencial (como factura).
        if (typeof window.aplicarFavoritosModal === 'function') { try { window.aplicarFavoritosModal('#modalOrdenSE'); } catch (e) {} }
        const selSerie = document.getElementById('se_select_serie');
        if (selSerie.value) {
            seSerieChange();
        } else {
            window.SE_SECUENCIAL_CONFIGURADO = false;
            seAvisarSecuencialNoConfigurado('serie');
        }
        seAgregarLinea();
        seAgregarInfo();   // una línea de info general lista por defecto
        getModal().show();
        setTimeout(() => seFocus('se_cliente_busqueda'), 250);
    };

    window.seAbrirVer = function (rowEl) {
        const row = JSON.parse(rowEl.getAttribute('data-row'));
        seAbrirVerId(row.id, false);
    };

    window.seAbrirVerId = async function (id, irAFacturar) {
        resetForm();
        getModal().show();
        // Mostrar loader: la carga completa vía AJAX puede tardar y sin esto el
        // usuario ve el modal "vacío" y piensa que la orden no tiene datos.
        document.getElementById('se-modal-loader')?.classList.remove('d-none');
        try {
            const res = await fetch(`${RUTA}/getDetalleAjax?id=${id}`);
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudo cargar la orden.');
            const o = data.data;
            const yaFacturado = !!o.id_documento || o.estado === 'facturado' || o.estado === 'anulado';
            const editable = window.SE_PERM.actualizar && !yaFacturado;

            document.getElementById('se_id').value = o.id;
            document.getElementById('se_numero_orden').value = o.numero_orden || '';
            document.getElementById('seTitulo').innerHTML = '<i class="bi bi-tools me-1 text-info"></i> Orden ' + (o.numero_orden || '');
            pintarBadge(o.estado, o.estado);
            SE_CUR = { id: o.id, id_documento: o.id_documento || 0, tipo_documento: o.tipo_documento || '', estado: o.estado || '' };

            // Serie / secuencial (se conserva la numeración de la orden; el selector queda bloqueado).
            const selSerie = document.getElementById('se_select_serie');
            if (o.id_punto_emision && selSerie.querySelector(`option[value="${o.id_punto_emision}"]`)) selSerie.value = o.id_punto_emision;
            selSerie.disabled = true;
            document.getElementById('se_serie').value = (o.establecimiento || '') + '-' + (o.punto_emision || '');
            document.getElementById('se_id_punto_emision').value = o.id_punto_emision || '';
            document.getElementById('se_id_establecimiento').value = o.id_establecimiento || '';
            document.getElementById('se_secuencial').value = o.secuencial || '';
            document.getElementById('se_secuencial').dataset.sec = o.secuencial || '';

            if (o.fecha_servicio) document.getElementById('se_fecha_servicio').value = String(o.fecha_servicio).replace(' ', 'T').slice(0, 16);
            document.getElementById('se_id_cliente').value = o.id_cliente || '';
            document.getElementById('se_cliente_busqueda').value = o.id_cliente ? ((o.cliente_identificacion || '') + ' — ' + (o.cliente_nombre || '')) : '';
            sePintarInfoCliente(o);
            document.getElementById('se_equipo_descripcion').value = o.equipo_descripcion || '';
            document.getElementById('se_equipo_marca').value = o.equipo_marca || '';
            document.getElementById('se_equipo_modelo').value = o.equipo_modelo || '';
            document.getElementById('se_equipo_serie').value = o.equipo_serie || '';
            document.getElementById('se_id_bodega').value = o.id_bodega || '';
            document.getElementById('se_direccion_servicio').value = o.direccion_servicio || '';
            document.getElementById('se_descripcion_trabajo').value = o.descripcion_trabajo || '';
            document.getElementById('se_observaciones').value = o.observaciones || '';

            (o.detalles || []).forEach(d => seCargarLineaGuardada(d));
            (o.info_adicional || []).forEach(ia => seAgregarInfo(ia));
            if (!(o.detalles || []).length) seAgregarLinea();
            seCalcTotales();

            setEditable(editable);
            document.getElementById('se_btn_eliminar').classList.toggle('d-none', !(window.SE_PERM.eliminar && !o.id_documento));

            // Botones de documento: generar si es borrador sin documento; PDF/correo/wa si ya hay documento.
            seToggleDocBtns(true, o);
            if (irAFacturar && !o.id_documento) {
                document.getElementById('se_btn_factura').classList.add('shadow');
            }
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        } finally {
            document.getElementById('se-modal-loader')?.classList.add('d-none');
        }
    };

    function pintarBadge(estado, label) {
        estado = estado || 'borrador';
        const nombres = { borrador: 'Borrador', facturado: 'Facturado', anulado: 'Anulado' };
        const b = document.getElementById('se_estado_badge');
        b.classList.remove('d-none');
        b.textContent = nombres[estado] || label || estado;
        let cls = 'bg-warning bg-opacity-10 text-warning'; // borrador
        if (estado === 'facturado') cls = 'bg-success bg-opacity-10 text-success';
        else if (estado === 'anulado') cls = 'bg-danger bg-opacity-10 text-danger';
        b.className = 'badge ms-2 ' + cls;
    }

    function sePintarInfoCliente(o) {
        const parts = [];
        if (o.cliente_direccion) parts.push('<i class="bi bi-geo-alt"></i> ' + esc(o.cliente_direccion));
        if (o.cliente_email) parts.push('<i class="bi bi-envelope"></i> ' + esc(o.cliente_email));
        if (o.cliente_telefono) parts.push('<i class="bi bi-telephone"></i> ' + esc(o.cliente_telefono));
        document.getElementById('se_info_cliente').innerHTML = parts.length
            ? '<span class="text-muted">' + parts.join(' &nbsp; ') + '</span>' : '';
    }

    // Habilita/deshabilita los botones de documento según el estado de la orden.
    function seToggleDocBtns(mostrar, o) {
        const hayDoc = mostrar && o && !!o.id_documento;
        const puedeFacturar = mostrar && o && !o.id_documento && (o.estado || 'borrador') === 'borrador' && window.SE_PERM.crear;
        const esFactura = hayDoc && (o.tipo_documento === 'FACTURA');
        const ordenGuardada = !!(document.getElementById('se_id').value);
        ['se_btn_factura','se_btn_recibo'].forEach(id => { const b = document.getElementById(id); if (b) b.disabled = !puedeFacturar; });
        const bp = document.getElementById('se_btn_pdf'); if (bp) bp.disabled = !ordenGuardada; // PDF de la orden
        const bc = document.getElementById('se_btn_correo'); if (bc) bc.disabled = !ordenGuardada; // Correo de la orden
        const bw = document.getElementById('se_btn_whatsapp'); if (bw) bw.disabled = !esFactura;
    }

    // ─── Serie / secuencial (mismo control que Factura de Venta: debe estar
    // configurado en Empresa → Secuenciales; si no, se avisa al usuario) ───────
    window.SE_SECUENCIAL_CONFIGURADO = true;

    function seAvisarSecuencialNoConfigurado(tipo) {
        if (typeof Swal === 'undefined') return;
        const html = (tipo === 'serie')
            ? 'No hay una serie / punto de emisión disponible.<br>Configure los puntos de emisión y sus secuenciales en <strong>Empresa → Secuenciales</strong> antes de generar la orden.'
            : 'No están configurados los secuenciales para esta serie.<br>Configúrelos en <strong>Empresa → Secuenciales</strong> antes de generar la orden.';
        Swal.fire({
            icon: 'warning',
            title: 'Secuenciales no configurados',
            html: html,
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#f39c12',
            target: document.getElementById('modalOrdenSE'),
        });
    }

    window.seSerieChange = async function () {
        const sel = document.getElementById('se_select_serie');
        const opt = sel.options[sel.selectedIndex];
        const idPunto = sel.value;
        const inputSec = document.getElementById('se_secuencial');
        if (!idPunto || !opt) {
            document.getElementById('se_serie').value = '';
            document.getElementById('se_id_punto_emision').value = '';
            document.getElementById('se_id_establecimiento').value = '';
            inputSec.value = '';
            window.SE_SECUENCIAL_CONFIGURADO = false;
            seAvisarSecuencialNoConfigurado('serie');
            return;
        }
        const est = opt.dataset.codEst || '';
        const punto = opt.dataset.codPunto || '';
        document.getElementById('se_serie').value = est + '-' + punto;
        document.getElementById('se_id_punto_emision').value = idPunto;
        document.getElementById('se_id_establecimiento').value = opt.dataset.idEst || '';
        await seCargarSecuencial(idPunto);
    };
    async function seCargarSecuencial(idPunto) {
        const inputSec = document.getElementById('se_secuencial');
        try {
            const res = await fetch(`${RUTA}/getSecuencialAjax?id_punto_emision=${idPunto}`);
            const data = await res.json();
            if (!data.ok) {
                inputSec.value = '';
                window.SE_SECUENCIAL_CONFIGURADO = false;
                Swal.fire('Atención', data.msg || 'No hay secuencial disponible.', 'warning');
                return;
            }
            const sec = data.formateado || String(data.secuencial || '').padStart(9, '0');
            inputSec.value = sec;
            inputSec.dataset.sec = data.secuencial || '';

            // Indicador visual si es un gap (número faltante recuperado).
            if (data.es_gap) {
                inputSec.classList.add('border-warning');
                inputSec.title = data.detalle || 'Número faltante recuperado';
            } else {
                inputSec.classList.remove('border-warning');
                inputSec.title = data.detalle || 'Siguiente consecutivo';
            }

            // ¿Está configurado el secuencial para esta serie (Empresa → Secuenciales)?
            window.SE_SECUENCIAL_CONFIGURADO = (data.configurado !== false);
            if (data.configurado === false) {
                inputSec.classList.add('border-danger');
                seAvisarSecuencialNoConfigurado('secuencial');
            } else {
                inputSec.classList.remove('border-danger');
            }
        } catch (e) {
            inputSec.value = '';
            window.SE_SECUENCIAL_CONFIGURADO = false;
        }
    }

    // ─── Cliente ──────────────────────────────────────────────────────────────
    window.seBuscarClientes = function (q) {
        clearTimeout(cliTimer);
        const dd = document.getElementById('se_cli_dropdown');
        if (!q || q.length < 2) { dd.classList.add('d-none'); return; }
        cliTimer = setTimeout(async () => {
            const res = await fetch(`${RUTA}/buscarClientesAjax?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            dd.innerHTML = '';
            (data.data || []).forEach(c => {
                const a = document.createElement('a');
                a.href = '#'; a.className = 'list-group-item list-group-item-action py-1';
                a.innerHTML = `<span class="small fw-semibold">${esc(c.nombre || '')}</span>
                               <span class="small text-muted ms-1">${c.identificacion ? '· ' + esc(c.identificacion) : ''}</span>`;
                a.onclick = (ev) => { ev.preventDefault(); seSeleccionarCliente(c); };
                dd.appendChild(a);
            });
            if (!data.data || !data.data.length) dd.innerHTML = '<span class="list-group-item small text-muted">Sin resultados. Use "Cliente" para crear uno nuevo.</span>';
            dd.classList.remove('d-none');
        }, 300);
    };
    function seSeleccionarCliente(c) {
        document.getElementById('se_id_cliente').value = c.id;
        document.getElementById('se_cliente_busqueda').value = (c.identificacion || '') + ' — ' + (c.nombre || '');
        document.getElementById('se_cli_dropdown').classList.add('d-none');
        sePintarInfoCliente({ cliente_direccion: c.direccion, cliente_email: c.correo, cliente_telefono: c.telefono });
        // Agrega/actualiza el correo del cliente en Info. Adicional (igual que factura).
        seActualizarInfoCorreoCliente(c.correo || '');
        // Si la dirección del servicio está vacía, sugiere la del cliente.
        const dirInp = document.getElementById('se_direccion_servicio');
        if (dirInp && !dirInp.value && c.direccion) dirInp.value = c.direccion;
    }

    // Fila fija con el correo del cliente en Info. Adicional (se actualiza al cambiar de cliente).
    window.seActualizarInfoCorreoCliente = function (email) {
        const tbody = document.getElementById('se_info_body');
        let fila = tbody.querySelector('tr[data-tipo="correo-cliente"]');
        if (!fila) {
            // Reutiliza una línea de correo ya guardada (evita duplicados al reseleccionar cliente).
            fila = Array.from(tbody.querySelectorAll('tr.row-info-adicional')).find(r => (r.querySelector('.input-info-concepto')?.value || '').trim().toLowerCase() === 'correo del cliente');
            if (fila) fila.dataset.tipo = 'correo-cliente';
        }
        email = (email || '').trim();
        if (!email) { if (fila) fila.remove(); return; }
        if (fila) { fila.querySelector('.input-info-detalle').value = email; return; }
        const tr = document.createElement('tr');
        tr.className = 'row-info-adicional';
        tr.dataset.tipo = 'correo-cliente';
        tr.innerHTML = `
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto" style="padding:0 4px;height:20px;font-size:0.78rem;" value="Correo del cliente" readonly></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle" style="padding:0 4px;height:20px;font-size:0.78rem;" value="${esc(email)}"></td>
            <td class="p-0 text-center pe-1"><span class="text-muted small" title="Se actualiza al cambiar el cliente"><i class="bi bi-lock-fill"></i></span></td>`;
        tbody.appendChild(tr);
    };

    // Cerrar dropdown al hacer clic fuera
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#se_cliente_busqueda') && !e.target.closest('#se_cli_dropdown'))
            document.getElementById('se_cli_dropdown')?.classList.add('d-none');
    });

    // ─── Grilla de ítems (portada de Factura de Venta) ────────────────────────
    const EMPRESA_CONFIG = window.EMPRESA_CONFIG || {};
    const TARIFAS_IVA = window.TARIFAS_IVA || [];
    const UNIDADES = window.UNIDADES || [];
    const DEC_PRECIO = EMPRESA_CONFIG.decimales_precio ?? 2;
    const r2 = v => Math.round(v * 100) / 100;
    function seDebounce(fn, wait) { let t; return function (...a) { clearTimeout(t); t = setTimeout(() => fn.apply(this, a), wait); }; }

    // Al cambiar la bodega de la cabecera se recalcula el saldo de todas las líneas.
    window.seBodegaCabeceraChange = function () {
        document.querySelectorAll('#se_tbodyDetalle .row-detalle').forEach(tr => seActualizarSaldoFila(tr));
    };

    // Muestra el saldo del producto de la fila en la bodega de la cabecera.
    async function seActualizarSaldoFila(tr) {
        if (!tr) return;
        const cont = tr.querySelector('.span-saldo-info');
        const lbl  = tr.querySelector('.lbl-saldo-valor');
        if (!cont || !lbl) return;
        const idProd = tr.querySelector('.input-id-producto') ? tr.querySelector('.input-id-producto').value : '';
        const idBod  = document.getElementById('se_id_bodega') ? document.getElementById('se_id_bodega').value : '';
        if (!idProd || !idBod || tr.dataset.controlaStock !== '1') { cont.classList.add('d-none'); return; }
        try {
            const idOrd = document.getElementById('se_id').value || 0;
            const res = await fetch(`${RUTA}/getStockAjax?id_producto=${idProd}&id_bodega=${idBod}&id_orden=${idOrd}`);
            const data = await res.json();
            if (!data.ok) { cont.classList.add('d-none'); return; }
            const st = parseFloat(data.stock || 0);
            lbl.textContent = st.toFixed(2);
            cont.classList.remove('d-none');
            cont.classList.toggle('text-danger', st <= 0);
            cont.classList.toggle('text-muted', st > 0);
        } catch (e) { cont.classList.add('d-none'); }
    }

    // Crea una fila vacía de la grilla y cablea su búsqueda de producto.
    window.seAgregarLinea = function () {
        const tbody = document.getElementById('se_tbodyDetalle');
        const tr = document.createElement('tr');
        tr.className = 'row-detalle';
        tr.innerHTML = `
            <td class="ps-3 position-relative">
                <input type="text" class="form-control form-control-sm input-detalle input-descripcion" placeholder="${EMPRESA_CONFIG.facturacion_libre ? 'Escribe o busca un repuesto/servicio...' : 'Buscar repuesto o servicio...'}">
                <input type="hidden" class="input-id-producto">
                <input type="hidden" class="input-codigo">
                <input type="hidden" class="input-es-libre" value="0">
                <input type="hidden" class="input-ice-pct" value="0">
                <input type="hidden" class="input-ice-val" value="0">
                <input type="hidden" class="input-precio-base-original" value="0">
                <input type="hidden" class="input-factor-original" value="1">
                <div class="mt-1 container-variante d-none">
                    <select class="form-select form-select-sm input-detalle input-variante" style="font-size:0.7rem; height:24px; padding:0 5px;"><option value="">Variantes...</option></select>
                </div>
                <div class="mt-1 small fw-bold text-muted span-saldo-info d-none" style="font-size:0.68rem;">
                    <i class="bi bi-box-seam me-1 text-primary"></i>Saldo: <span class="lbl-saldo-valor">0.00</span>
                </div>
            </td>
            <td><input type="text" class="form-control form-control-sm input-detalle input-adicional text-muted fst-italic" placeholder="Info adicional"></td>
            <td class="${EMPRESA_CONFIG.mostrar_unidad_medida ? '' : 'd-none'}">
                <select class="form-select form-select-sm input-detalle input-medida d-none"><option value="">Medida</option></select>
            </td>
            <td><input type="number" class="form-control form-control-sm input-detalle text-center input-cantidad" value="1" step="any" oninput="seCalcFila(this)"></td>
            <td><select class="form-select form-select-sm input-detalle input-lista-precios"><option value="">P. Base</option></select></td>
            <td><input type="number" class="form-control form-control-sm input-detalle text-end input-precio" value="${(0).toFixed(DEC_PRECIO)}" step="any" oninput="seCalcSinImp(this)" onblur="this.value=parseFloat(this.value||0).toFixed(${DEC_PRECIO})" ${EMPRESA_CONFIG.editar_precio_factura ? '' : 'readonly'}></td>
            <td><input type="number" class="form-control form-control-sm input-detalle text-end input-precio-iva" value="${(0).toFixed(DEC_PRECIO)}" step="any" oninput="seCalcConImp(this)" onblur="this.value=parseFloat(this.value||0).toFixed(${DEC_PRECIO})" ${EMPRESA_CONFIG.editar_precio_factura ? '' : 'readonly'}></td>
            <td><input type="number" class="form-control form-control-sm input-detalle text-end text-danger input-desc" value="0.00" step="any" oninput="seCalcFila(this)" ${EMPRESA_CONFIG.editar_descuento_factura ? '' : 'readonly'}></td>
            <td>
                <select class="form-select form-select-sm input-detalle text-center input-iva" onchange="seSyncPrecioIva(this)" ${EMPRESA_CONFIG.editar_iva_factura ? '' : 'disabled'}>
                    ${TARIFAS_IVA.map(t => `<option value="${t.porcentaje_iva}" data-codigo="${t.codigo}" data-id="${t.id}">${t.tarifa}</option>`).join('')}
                </select>
            </td>
            ${EMPRESA_CONFIG.obligatorio_lotes ? `<td class="align-middle" style="min-width:120px;"><select class="form-select form-select-sm input-detalle input-lote d-none" style="font-size:0.75rem;"><option value="">Seleccionar Lote</option></select></td>` : ''}
            ${EMPRESA_CONFIG.obligatorio_caducidad ? `<td class="align-middle" style="min-width:120px;"><select class="form-select form-select-sm input-detalle input-caducidad d-none" style="font-size:0.75rem;"><option value="">Seleccionar Vencimiento</option></select></td>` : ''}
            ${EMPRESA_CONFIG.obligatorio_nup ? `<td class="align-middle" style="min-width:100px;"><input type="text" class="form-control form-control-sm input-detalle input-nup d-none" placeholder="NUP/Serial" style="font-size:0.75rem;"></td>` : ''}
            <td class="text-end pe-4 align-middle"><span class="subtotal-line">0.00</span></td>
            <td class="text-center p-0 align-middle" style="width:40px;">
                <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="this.closest('tr').remove(); seCalcTotales();" title="Eliminar ítem"><i class="bi bi-trash3 fs-6"></i></button>
            </td>`;
        tbody.appendChild(tr);

        const inputDesc = tr.querySelector('.input-descripcion');
        const dropdownGlobal = document.getElementById('se-dropdown-productos-global');

        const buscarProducto = async (q, sourceInput) => {
            q = (q || '').trim();
            if (q.length < 2) { dropdownGlobal.classList.add('d-none'); return; }
            const rect = sourceInput.getBoundingClientRect();
            dropdownGlobal.style.top = `${rect.bottom + 2}px`;
            dropdownGlobal.style.left = `${rect.left}px`;
            dropdownGlobal.style.width = `${Math.max(rect.width, 350)}px`;
            dropdownGlobal.classList.remove('d-none');
            dropdownGlobal.innerHTML = '<div class="list-group-item small text-muted">Buscando...</div>';
            try {
                // Stock según la bodega de la cabecera (única para toda la orden).
                const idBod = document.getElementById('se_id_bodega').value || 0;
                const idOrd = document.getElementById('se_id').value || 0;
                const resp = await fetch(`${RUTA}/getProductosAjax?q=${encodeURIComponent(q)}&id_bodega=${idBod}&id_orden=${idOrd}`);
                const json = await resp.json();
                dropdownGlobal.innerHTML = '';
                if (json.data && json.data.length > 0) {
                    json.data.forEach(p => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'list-group-item list-group-item-action small py-1 border-bottom';
                        // Stock disponible (solo productos que controlan inventario)
                        let stockBadge = '';
                        if (p.controla_stock) {
                            const st = parseFloat(p.stock_actual || 0);
                            const cls = st > 0 ? 'success' : 'danger';
                            stockBadge = `<span class="badge bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25 me-1">Stock: ${st.toFixed(2)}</span>`;
                        }
                        b.innerHTML = `<div class="d-flex justify-content-between align-items-center text-start">
                                <div class="pe-3"><div class="fw-bold text-dark">${esc(p.nombre)}</div>
                                <div class="x-small text-muted">${esc(p.codigo || '')} ${p.codigo_barras ? '| ' + esc(p.codigo_barras) : ''}</div></div>
                                <div class="text-nowrap">${stockBadge}<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10">$${parseFloat(p.precio_base || 0).toFixed(2)}</span></div></div>`;
                        b.onmousedown = (evt) => {
                            evt.preventDefault();
                            if (p.controla_stock && parseFloat(p.stock_actual || 0) <= 0) {
                                Swal.fire({ icon: 'warning', title: 'Sin stock', text: `"${p.nombre}" no tiene stock disponible en la bodega seleccionada.`, timer: 2600, showConfirmButton: false, target: document.getElementById('modalOrdenSE') });
                            }
                            seSeleccionarProductoEnFila(p, tr);
                            dropdownGlobal.classList.add('d-none');
                        };
                        dropdownGlobal.appendChild(b);
                    });
                    if (EMPRESA_CONFIG.facturacion_libre) seAgregarOpcionServicioLibre(q, tr, dropdownGlobal);
                } else {
                    if (EMPRESA_CONFIG.facturacion_libre) seAgregarOpcionServicioLibre(q, tr, dropdownGlobal);
                    else dropdownGlobal.innerHTML = '<div class="list-group-item small text-muted">Sin coincidencias en el catálogo</div>';
                }
            } catch (err) { console.error('Error productos', err); }
        };

        inputDesc.addEventListener('input', seDebounce((e) => buscarProducto(e.target.value, inputDesc), 400));
        inputDesc.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const firstBtn = dropdownGlobal.querySelector('button');
                if (firstBtn && !dropdownGlobal.classList.contains('d-none')) { e.preventDefault(); firstBtn.onmousedown(new MouseEvent('mousedown')); }
            }
        });
        inputDesc.addEventListener('blur', () => setTimeout(() => dropdownGlobal.classList.add('d-none'), 200));
        inputDesc.addEventListener('blur', () => {
            if (!EMPRESA_CONFIG.facturacion_libre) return;
            const idProd = tr.querySelector('.input-id-producto').value;
            const desc = inputDesc.value.trim();
            if (!idProd && desc.length > 0) seSeleccionarItemLibre(desc, tr);
        });

        seCalcTotales();
        return tr;
    };

    window.seSeleccionarProductoEnFila = function (p, row) {
        row.querySelector('.input-codigo').value = p.codigo || '';
        row.querySelector('.input-descripcion').value = p.nombre || '';
        row.querySelector('.input-precio').value = parseFloat(p.precio_base || 0).toFixed(DEC_PRECIO);
        row.querySelector('.input-id-producto').value = p.id;
        row.dataset.idProducto = p.id;
        row.dataset.tipoProduccion = p.tipo_produccion || '01';
        row.dataset.inventariable = p.inventariable;
        row.dataset.controlaStock = p.controla_stock ? '1' : '0';
        row.querySelector('.input-es-libre').value = '0';
        row.querySelector('.input-precio-base-original').value = p.precio_base || 0;
        row.querySelector('.input-ice-pct').value = p.valor_ice || 0;
        row.querySelector('.input-ice-val').value = 0;
        row.classList.remove('table-warning');

        // Variantes
        const selVar = row.querySelector('.input-variante');
        const contVar = row.querySelector('.container-variante');
        if (p.variantes && p.variantes.length > 0) {
            if (contVar) contVar.classList.remove('d-none');
            selVar.innerHTML = '<option value="">Variantes...</option>';
            p.variantes.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.precio_adicional || 0;
                opt.textContent = `${v.nombre}: ${v.valor} (+${parseFloat(v.precio_adicional || 0).toFixed(2)})`;
                opt.dataset.nombre = v.nombre; opt.dataset.valor = v.valor;
                selVar.appendChild(opt);
            });
            selVar.onchange = () => {
                const base = parseFloat(row.querySelector('.input-precio-base-original').value) || 0;
                const add = parseFloat(selVar.value) || 0;
                row.querySelector('.input-precio').value = (base + add).toFixed(DEC_PRECIO);
                const opt = selVar.options[selVar.selectedIndex];
                row.querySelector('.input-adicional').value = opt.value ? `${opt.dataset.nombre}: ${opt.dataset.valor}` : '';
                seSyncPrecioIva(row.querySelector('.input-precio'));
            };
        } else {
            if (contVar) contVar.classList.add('d-none');
            selVar.innerHTML = '<option value="">Variantes...</option>';
        }

        // IVA: por id de tarifa o por porcentaje
        let pctFinal = null;
        if (p.porcentaje_iva !== undefined && p.porcentaje_iva !== null) pctFinal = parseFloat(p.porcentaje_iva);
        else if (p.tarifa_iva) { const tf = TARIFAS_IVA.find(t => t.id == p.tarifa_iva); if (tf) pctFinal = parseFloat(tf.porcentaje_iva); }
        const selIva = row.querySelector('.input-iva');
        if (selIva) {
            let opt = p.tarifa_iva ? Array.from(selIva.options).find(o => o.dataset.id == p.tarifa_iva) : null;
            if (!opt && pctFinal !== null) opt = Array.from(selIva.options).find(o => Math.abs(parseFloat(o.value) - pctFinal) < 0.001);
            if (opt) selIva.selectedIndex = opt.index;
            else if (pctFinal === 0) selIva.value = '0';
        }

        // Medidas
        const selMedida = row.querySelector('.input-medida');
        if (selMedida) {
            if (p.id_tipo_medida || p.id_medida) {
                selMedida.classList.remove('d-none');
                selMedida.innerHTML = '';
                let compatibles = [];
                if (p.id_tipo_medida) compatibles = UNIDADES.filter(u => u.id_tipo == p.id_tipo_medida);
                if (compatibles.length === 0 && p.id_medida) { const ub = UNIDADES.find(u => u.id == p.id_medida); if (ub) compatibles = [ub]; }
                if (compatibles.length > 0) {
                    compatibles.forEach(u => {
                        const opt = document.createElement('option');
                        opt.value = u.id; opt.textContent = u.nombre; opt.dataset.factor = u.factor_base || 1;
                        if (u.id == p.id_medida) { opt.selected = true; row.querySelector('.input-factor-original').value = u.factor_base || 1; }
                        selMedida.appendChild(opt);
                    });
                } else { selMedida.classList.add('d-none'); }
            } else { selMedida.classList.add('d-none'); selMedida.innerHTML = '<option value="">...</option>'; }
        }

        // Lista de precios
        const selPrecios = row.querySelector('.input-lista-precios');
        selPrecios.innerHTML = '';
        const optBase = document.createElement('option');
        optBase.value = p.precio_base; optBase.textContent = `P. Base ($${parseFloat(p.precio_base || 0).toFixed(DEC_PRECIO)})`;
        selPrecios.appendChild(optBase);
        if (p.precios_lista && p.precios_lista.length > 0) {
            p.precios_lista.forEach(pl => {
                const opt = document.createElement('option');
                opt.value = pl.precio; opt.textContent = `${pl.nombre_precio} ($${parseFloat(pl.precio || 0).toFixed(DEC_PRECIO)})`;
                selPrecios.appendChild(opt);
            });
        }
        selPrecios.onchange = () => { row.querySelector('.input-precio').value = parseFloat(selPrecios.value).toFixed(DEC_PRECIO); seSyncPrecioIva(row.querySelector('.input-precio')); };

        seSyncPrecioIva(row.querySelector('.input-precio'));
        seCalcFila(row.querySelector('.input-cantidad'));
        seActualizarSaldoFila(row);
        const inCant = row.querySelector('.input-cantidad'); inCant.focus(); inCant.select();
    };

    window.seAgregarOpcionServicioLibre = function (texto, tr, dropdown) {
        const sep = document.createElement('div');
        sep.className = 'list-group-item py-1 text-muted x-small border-top bg-light';
        sep.textContent = 'ó facturar como servicio libre:';
        dropdown.appendChild(sep);
        const bLibre = document.createElement('button');
        bLibre.type = 'button';
        bLibre.className = 'list-group-item list-group-item-action py-2 border-0 bg-warning bg-opacity-10';
        bLibre.innerHTML = `<div class="d-flex align-items-center gap-2 text-start"><i class="bi bi-lightning-charge-fill text-warning fs-6"></i>
            <div><div class="fw-bold small text-dark">"${esc(texto)}"</div><div class="x-small text-muted">Registrar como servicio libre (se creará al guardar)</div></div></div>`;
        bLibre.onmousedown = (evt) => { evt.preventDefault(); seSeleccionarItemLibre(texto, tr); dropdown.classList.add('d-none'); };
        dropdown.appendChild(bLibre);
    };

    window.seSeleccionarItemLibre = function (descripcion, row) {
        row.querySelector('.input-descripcion').value = descripcion;
        row.querySelector('.input-id-producto').value = '';
        row.querySelector('.input-codigo').value = '__LIBRE__';
        row.querySelector('.input-es-libre').value = '1';
        row.dataset.tipoProduccion = '02';
        row.dataset.inventariable = 'false';
        const selIva = row.querySelector('.input-iva');
        if (selIva && selIva.options.length > 0) selIva.selectedIndex = 0;
        const selMedida = row.querySelector('.input-medida');
        if (selMedida) selMedida.classList.add('d-none');
        row.classList.add('table-warning');
        row.title = 'Servicio libre - se creará en el catálogo al guardar';
        const inputPrecio = row.querySelector('.input-precio');
        if (inputPrecio) setTimeout(() => { inputPrecio.focus(); inputPrecio.select(); }, 50);
        seCalcFila(row.querySelector('.input-cantidad'));
    };

    window.seSyncPrecioIva = function (el) {
        const tr = el.closest('tr');
        const pSin = parseFloat(tr.querySelector('.input-precio').value) || 0;
        const ivaPct = parseFloat(tr.querySelector('.input-iva').value) || 0;
        tr.querySelector('.input-precio-iva').value = (pSin * (1 + ivaPct / 100)).toFixed(DEC_PRECIO);
        seCalcFila(tr.querySelector('.input-cantidad'));
    };
    window.seCalcSinImp = function (el) {
        const tr = el.closest('tr');
        const pSin = parseFloat(el.value) || 0;
        const ivaPct = parseFloat(tr.querySelector('.input-iva').value) || 0;
        tr.querySelector('.input-precio-iva').value = (pSin * (1 + ivaPct / 100)).toFixed(DEC_PRECIO);
        seCalcFila(el);
    };
    window.seCalcConImp = function (el) {
        const tr = el.closest('tr');
        const pCon = parseFloat(el.value) || 0;
        const ivaPct = parseFloat(tr.querySelector('.input-iva').value) || 0;
        tr.querySelector('.input-precio').value = (pCon / (1 + ivaPct / 100)).toFixed(DEC_PRECIO);
        seCalcFila(el);
    };
    window.seCalcFila = function (el) {
        const tr = el.closest('tr');
        const cant = parseFloat(tr.querySelector('.input-cantidad').value) || 0;
        const prec = parseFloat(tr.querySelector('.input-precio').value) || 0;
        const desc = parseFloat(tr.querySelector('.input-desc').value) || 0;
        const subtotalNeto = r2(r2(cant * prec) - desc);
        tr.querySelector('.subtotal-line').textContent = subtotalNeto.toFixed(2);
        seCalcTotales();
    };
    window.seCalcTotales = function () {
        const modoIva = EMPRESA_CONFIG.calculo_iva || 'linea_linea';
        let subtotalGeneral = 0, descuentoTotal = 0;
        const grupos = {};
        document.querySelectorAll('#se_tbodyDetalle .row-detalle').forEach(tr => {
            const cant = parseFloat(tr.querySelector('.input-cantidad').value) || 0;
            const prec = parseFloat(tr.querySelector('.input-precio').value) || 0;
            const desc = parseFloat(tr.querySelector('.input-desc').value) || 0;
            const selIva = tr.querySelector('.input-iva');
            const optIva = selIva.options[selIva.selectedIndex];
            const ivaPct = parseFloat(optIva ? optIva.value : 0) || 0;
            const key = optIva ? (optIva.dataset.id || ivaPct) : ivaPct;
            const label = optIva ? optIva.text : '0%';
            const bruto = r2(cant * prec);
            const neto = r2(bruto - desc);
            subtotalGeneral = r2(subtotalGeneral + bruto);
            descuentoTotal = r2(descuentoTotal + desc);
            if (!grupos[key]) grupos[key] = { pct: ivaPct, label: label, base: 0, iva: 0 };
            grupos[key].base = r2(grupos[key].base + neto);
            if (modoIva === 'linea_linea') grupos[key].iva = r2(grupos[key].iva + r2(neto * ivaPct / 100));
        });
        if (modoIva === 'subtotal') Object.values(grupos).forEach(g => { g.iva = r2(g.base * g.pct / 100); });
        let ivaTotal = 0; Object.values(grupos).forEach(g => { ivaTotal = r2(ivaTotal + g.iva); });
        const total = r2((subtotalGeneral - descuentoTotal) + ivaTotal);

        const set = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
        set('se-lbl-subtotal', subtotalGeneral.toFixed(2));
        const contSub = document.getElementById('se-lbl-subtotales-iva');
        if (contSub) { contSub.innerHTML = ''; Object.values(grupos).forEach(g => { const d = document.createElement('div'); d.className = 'd-flex justify-content-between align-items-center mb-1 text-muted'; d.innerHTML = `<span>Subtotal ${g.label}</span><span>${g.base.toFixed(2)}</span>`; contSub.appendChild(d); }); }
        set('se-lbl-descuento', descuentoTotal.toFixed(2));
        const contIva = document.getElementById('se-lbl-ivas-grupo');
        if (contIva) { contIva.innerHTML = ''; Object.values(grupos).forEach(g => { if (g.pct > 0 && g.iva > 0) { const d = document.createElement('div'); d.className = 'd-flex justify-content-between align-items-center mb-1'; d.innerHTML = `<span class="text-muted">(+) IVA ${g.pct}%</span><span>${g.iva.toFixed(2)}</span>`; contIva.appendChild(d); } }); }
        set('se-lbl-total', total.toFixed(2));
        const cnt = document.getElementById('se-count-items'); if (cnt) cnt.textContent = document.querySelectorAll('#se_tbodyDetalle .row-detalle').length;
    };
    // Alias para llamadas previas.
    window.seRecalcularGrilla = window.seCalcTotales;

    // Carga un detalle guardado en una fila de la grilla.
    window.seCargarLineaGuardada = function (d) {
        const tr = seAgregarLinea();
        tr.dataset.idProducto = d.id_producto || '';
        tr.dataset.tipoProduccion = (d.tipo_linea === 'servicio') ? '02' : '01';
        tr.dataset.controlaStock = (d.id_producto && d.tipo_linea === 'producto') ? '1' : '0';
        tr.querySelector('.input-descripcion').value = d.descripcion || '';
        tr.querySelector('.input-id-producto').value = d.id_producto || '';
        tr.querySelector('.input-es-libre').value = (d.es_libre === true || d.es_libre === 't' || d.es_libre === 'true' || d.es_libre === 1) ? '1' : '0';
        tr.querySelector('.input-cantidad').value = d.cantidad != null ? parseFloat(d.cantidad) : 1;
        tr.querySelector('.input-precio').value = parseFloat(d.precio_unitario || 0).toFixed(DEC_PRECIO);
        tr.querySelector('.input-desc').value = parseFloat(d.descuento || 0).toFixed(2);
        const selIva = tr.querySelector('.input-iva');
        if (selIva) {
            let opt = d.id_tarifa_iva ? Array.from(selIva.options).find(o => o.dataset.id == d.id_tarifa_iva) : null;
            if (!opt) opt = Array.from(selIva.options).find(o => Math.abs(parseFloat(o.value) - parseFloat(d.porcentaje_iva || 0)) < 0.001);
            if (!opt && d.porcentaje_iva != null && d.porcentaje_iva !== '' && !isNaN(parseFloat(d.porcentaje_iva))) {
                // Tarifa histórica inactiva: se agrega la opción solo para este documento.
                const pctH = parseFloat(d.porcentaje_iva);
                opt = document.createElement('option');
                opt.value = pctH;
                opt.textContent = pctH + '%';
                selIva.appendChild(opt);
            }
            if (opt) selIva.selectedIndex = opt.index;
        }
        seSyncPrecioIva(tr.querySelector('.input-precio'));
        seCalcFila(tr.querySelector('.input-cantidad'));
        seActualizarSaldoFila(tr);
    };


    // ─── Info. Adicional (igual que factura de venta) ─────────────────────────
    window.seAgregarInfo = function (ia) {
        ia = ia || {};
        const tbody = document.getElementById('se_info_body');
        const tr = document.createElement('tr');
        tr.className = 'row-info-adicional';
        tr.innerHTML = `
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto" style="padding:0 4px;height:20px;font-size:0.78rem;" placeholder="Concepto..." value="${esc(ia.nombre || '')}"></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle" style="padding:0 4px;height:20px;font-size:0.78rem;" placeholder="Detalle..." value="${esc(ia.valor || '')}"></td>
            <td class="p-0 text-center pe-1"><button type="button" class="btn btn-link btn-sm p-0 m-0 text-danger shadow-none" onclick="this.closest('tr').remove();"><i class="bi bi-x-circle-fill"></i></button></td>`;
        const primeraFija = tbody.querySelector('tr[data-tipo]');
        if (primeraFija) tbody.insertBefore(tr, primeraFija);
        else tbody.appendChild(tr);
        if (!ia.nombre) tr.querySelector('.input-info-concepto').focus();
    };

    // ─── Totales (usa la grilla portada de factura) ───────────────────────────
    window.seRecalcular = function () { seCalcTotales(); };

    // ─── Guardar ──────────────────────────────────────────────────────────────
    window.seGuardar = async function () {
        const idCli = document.getElementById('se_id_cliente').value;
        // 1º Cliente (obligatorio)
        if (!idCli) { await Swal.fire('Atención', 'Seleccione un cliente.', 'warning'); seFocus('se_cliente_busqueda'); return; }
        // 2º Equipo (obligatorio)
        const equipoDesc = document.getElementById('se_equipo_descripcion').value.trim();
        if (!equipoDesc) { await Swal.fire('Atención', 'Indique el equipo atendido.', 'warning'); seFocus('se_equipo_descripcion'); return; }
        // 3º Serie / secuencial (obligatorio)
        if (!document.getElementById('se_id_punto_emision').value || !document.getElementById('se_secuencial').value) {
            await Swal.fire('Atención', 'Seleccione la serie (punto de emisión).', 'warning'); seFocus('se_select_serie'); return;
        }
        // 4º Bloqueo: secuenciales no configurados (solo al CREAR una orden nueva;
        // una orden ya guardada conserva su numeración aunque luego se desconfigure).
        const esNuevaOrden = (parseInt(document.getElementById('se_id').value, 10) || 0) === 0;
        if (esNuevaOrden && window.SE_SECUENCIAL_CONFIGURADO === false) {
            seAvisarSecuencialNoConfigurado('secuencial');
            return;
        }

        const detalles = [];
        document.querySelectorAll('#se_tbodyDetalle .row-detalle').forEach(tr => {
            const desc = (tr.querySelector('.input-descripcion')?.value || '').trim();
            const cant = num(tr.querySelector('.input-cantidad')?.value);
            if (!desc || cant <= 0) return;
            const selIva = tr.querySelector('.input-iva');
            const optIva = selIva ? selIva.options[selIva.selectedIndex] : null;
            const idProd = tr.querySelector('.input-id-producto')?.value || '';
            const esLibre = (tr.querySelector('.input-es-libre')?.value === '1') || !idProd;
            const tipoProd = tr.dataset.tipoProduccion || '';
            detalles.push({
                id_producto: idProd || null,
                tipo_linea: (tipoProd === '02' || esLibre) ? 'servicio' : (idProd ? 'producto' : 'servicio'),
                es_libre: esLibre,
                descripcion: desc,
                id_bodega: null, // la bodega es única de la cabecera; el backend la aplica a cada línea
                id_tarifa_iva: optIva ? (optIva.dataset.id || null) : null,
                cantidad: cant,
                precio_unitario: num(tr.querySelector('.input-precio')?.value),
                descuento: num(tr.querySelector('.input-desc')?.value),
                porcentaje_iva: optIva ? (parseFloat(optIva.value) || 0) : 0,
            });
        });
        // 4º Al menos un repuesto/servicio
        if (!detalles.length) {
            await Swal.fire('Atención', 'Agregue al menos un repuesto o servicio.', 'warning');
            let fila = document.querySelector('#se_tbodyDetalle .row-detalle .input-descripcion');
            if (!fila) { seAgregarLinea(); fila = document.querySelector('#se_tbodyDetalle .row-detalle .input-descripcion'); }
            if (fila) fila.focus();
            return;
        }

        const info_adicional = [];
        document.querySelectorAll('#se_info_body .row-info-adicional').forEach(row => {
            const nom = (row.querySelector('.input-info-concepto')?.value || '').trim();
            const val = (row.querySelector('.input-info-detalle')?.value || '').trim();
            if (nom && val) info_adicional.push({ nombre: nom, valor: val });
        });

        const serie = document.getElementById('se_serie').value;
        const payload = {
            id: document.getElementById('se_id').value || null,
            id_establecimiento: document.getElementById('se_id_establecimiento').value || null,
            id_punto_emision: document.getElementById('se_id_punto_emision').value || null,
            establecimiento: (serie.split('-')[0] || ''),
            punto_emision: (serie.split('-')[1] || ''),
            secuencial: document.getElementById('se_secuencial').dataset.sec || document.getElementById('se_secuencial').value || '',
            id_cliente: parseInt(idCli, 10),
            equipo_descripcion: equipoDesc,
            equipo_marca: document.getElementById('se_equipo_marca').value.trim(),
            equipo_modelo: document.getElementById('se_equipo_modelo').value.trim(),
            equipo_serie: document.getElementById('se_equipo_serie').value.trim(),
            direccion_servicio: document.getElementById('se_direccion_servicio').value.trim(),
            id_bodega: document.getElementById('se_id_bodega').value || null,
            fecha_servicio: (document.getElementById('se_fecha_servicio').value || '').replace('T', ' '),
            descripcion_trabajo: document.getElementById('se_descripcion_trabajo').value.trim(),
            observaciones: document.getElementById('se_observaciones').value.trim(),
            detalles, info_adicional
        };

        const btn = document.getElementById('se_btn_guardar');
        const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        try {
            const res = await fetch(`${RUTA}/store`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Error al guardar');
            getModal().hide();
            await Swal.fire({ icon: 'success', title: 'Listo', text: data.msg, timer: 1400, showConfirmButton: false });
            if (typeof cargarGrid === 'function') cargarGrid();
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        } finally {
            btn.disabled = false; btn.innerHTML = orig;
        }
    };

    // ─── Eliminar ─────────────────────────────────────────────────────────────
    window.seEliminar = async function () {
        const id = document.getElementById('se_id').value;
        if (!id) return;
        const c = await Swal.fire({ title: '¿Eliminar orden?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' });
        if (!c.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id', id);
            const res = await fetch(`${RUTA}/eliminar`, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'Error');
            getModal().hide();
            await Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1200, showConfirmButton: false });
            if (typeof cargarGrid === 'function') cargarGrid();
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
    };

    // ─── Documento de venta (Factura / Recibo) ────────────────────────────────
    const SE_BASE = RUTA.replace(/\/modulos\/servicio-externo\/?$/, '');

    window.seGenerarDocumento = async function (tipo) {
        const idOrden = document.getElementById('se_id').value;
        if (!idOrden) { Swal.fire('Atención', 'Primero guarde la orden.', 'warning'); return; }
        if (SE_CUR.id_documento) { Swal.fire('Atención', 'Esta orden ya generó un documento.', 'warning'); return; }

        const formas = window.SE_FORMAS_PAGO || [];
        const optForma = formas.map(f => `<option value="${esc(f.codigo)}">${esc(f.nombre)}</option>`).join('') || '<option value="01">Efectivo</option>';
        const etq = tipo === 'FACTURA' ? 'Factura electrónica' : 'Recibo de venta';

        const { value: form } = await Swal.fire({
            title: 'Generar ' + etq,
            target: document.getElementById('modalOrdenSE'),
            html: `<div class="text-start">
                    <label class="form-label small fw-semibold mb-1">Forma de pago</label>
                    <select id="seEmForma" class="form-select form-select-sm">${optForma}</select>
                    <div class="form-text">El inventario se descarga de la bodega seleccionada en la orden.</div>
                   </div>`,
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-receipt me-1"></i> Generar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => ({ forma_pago: document.getElementById('seEmForma').value || '01', id_bodega: 0 })
        });
        if (!form) return;

        Swal.fire({ title: 'Generando ' + etq + '...', allowOutsideClick: false, target: document.getElementById('modalOrdenSE'), didOpen: () => Swal.showLoading() });
        try {
            const fd = new FormData();
            fd.append('id_orden', idOrden);
            fd.append('tipo', tipo);
            fd.append('forma_pago', form.forma_pago);
            fd.append('id_bodega', form.id_bodega);
            const res = await fetch(`${RUTA}/generarDocumentoAjax`, { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudo generar el documento.');

            SE_CUR.id_documento = data.id_documento;
            SE_CUR.tipo_documento = data.tipo_documento;
            SE_CUR.estado = 'facturado';
            pintarBadge('facturado', 'Facturado');
            setEditable(false);
            document.getElementById('se_btn_eliminar').classList.add('d-none');
            seToggleDocBtns(true, { id_documento: data.id_documento, tipo_documento: data.tipo_documento, estado: 'facturado' });
            if (typeof cargarGrid === 'function') cargarGrid();

            const r = await Swal.fire({ icon: 'success', title: '¡Listo!', text: data.msg, showCancelButton: true, confirmButtonText: 'Ver PDF', cancelButtonText: 'Cerrar' });
            if (r.isConfirmed) sePdfDocumento();
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    };

    // PDF de la orden (orden de servicio externo) — descarga directa.
    window.sePdf = function () {
        const id = document.getElementById('se_id').value || SE_CUR.id;
        if (!id) { Swal.fire('Atención', 'Primero guarde la orden.', 'warning'); return; }
        const a = document.createElement('a');
        a.href = `${RUTA}/exportarPdfAjax?id=${id}`;
        document.body.appendChild(a);
        a.click();
        a.remove();
    };
    // PDF del documento generado (factura/recibo).
    window.sePdfDocumento = function () {
        if (!SE_CUR.id_documento) return;
        const ruta = SE_CUR.tipo_documento === 'FACTURA' ? 'factura-venta' : 'recibo-venta';
        window.open(`${SE_BASE}/modulos/${ruta}/exportarPdfAjax?id=${SE_CUR.id_documento}`, '_blank');
    };

    // Enviar el PDF de la orden por correo (mismo patrón que consignaciones).
    window.seCorreo = async function () {
        const id = document.getElementById('se_id').value || SE_CUR.id;
        if (!id) { Swal.fire('Atención', 'Primero guarde la orden.', 'warning'); return; }
        const correoActual = (document.getElementById('se_info_cliente').textContent.match(/[\w.+-]+@[\w-]+\.[\w.-]+/) || [''])[0];
        const { value: correos, isConfirmed } = await Swal.fire({
            title: 'Enviar por correo',
            input: 'text',
            inputLabel: 'Correo(s) destino, separados por coma.',
            inputValue: correoActual,
            inputPlaceholder: 'cliente@correo.com',
            target: document.getElementById('modalOrdenSE'),
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-envelope me-1"></i> Enviar',
            cancelButtonText: 'Cancelar'
        });
        if (!isConfirmed) return;
        Swal.fire({ title: 'Enviando correo...', allowOutsideClick: false, target: document.getElementById('modalOrdenSE'), didOpen: () => Swal.showLoading() });
        try {
            const fd = new FormData(); fd.append('id', id); fd.append('correos', correos || '');
            const res = await fetch(`${RUTA}/enviarCorreoAjax`, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) Swal.fire('Enviado', data.mensaje || 'Correo enviado correctamente.', 'success');
            else Swal.fire('Error', data.mensaje || 'No se pudo enviar el correo.', 'error');
        } catch (e) { Swal.fire('Error', 'No se pudo enviar el correo.', 'error'); }
    };

    window.seWhatsapp = async function () {
        if (!SE_CUR.id_documento || SE_CUR.tipo_documento !== 'FACTURA') return;
        try {
            const res = await fetch(`${SE_BASE}/modulos/factura-venta/getPlantillasWhatsappAjax?id_factura=${SE_CUR.id_documento}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error || 'No se pudieron cargar las plantillas.');
            if (data.configurado === false) { Swal.fire('WhatsApp', 'Aún no tiene configurada la API de WhatsApp. Actívela en su módulo de WhatsApp.', 'info'); return; }
            const opts = (data.plantillas || []).map(p => `<option value="${p.id}">${esc(p.nombre)} (${esc(p.idioma)})</option>`).join('');
            const { value: form, isConfirmed } = await Swal.fire({
                title: 'Enviar por WhatsApp', target: document.getElementById('modalOrdenSE'),
                html: `<div class="text-start">
                        <label class="form-label small fw-semibold mb-1">Plantilla</label>
                        <select id="seWaTpl" class="form-select form-select-sm mb-2">${opts || '<option value="">Sin plantillas</option>'}</select>
                        <label class="form-label small fw-semibold mb-1">Teléfono</label>
                        <input id="seWaTel" class="form-control form-control-sm" value="${esc(data.telefono_cliente || '593')}">
                       </div>`,
                showCancelButton: true, confirmButtonText: 'Enviar',
                preConfirm: () => ({ id_plantilla: document.getElementById('seWaTpl').value, telefono: document.getElementById('seWaTel').value })
            });
            if (!isConfirmed || !form) return;
            Swal.fire({ title: 'Enviando...', allowOutsideClick: false, target: document.getElementById('modalOrdenSE'), didOpen: () => Swal.showLoading() });
            const fd = new FormData();
            fd.append('id_factura', SE_CUR.id_documento);
            fd.append('id_plantilla', form.id_plantilla);
            fd.append('telefono', form.telefono);
            const r2 = await fetch(`${SE_BASE}/modulos/factura-venta/enviarWhatsappAjax`, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d2 = await r2.json();
            if (!d2.ok) throw new Error(d2.error || 'No se pudo enviar.');
            Swal.fire({ icon: 'success', title: '¡Enviado!', text: d2.mensaje || 'Mensaje enviado.', timer: 2200, showConfirmButton: false });
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
    };

    // ─── Crear entidades al vuelo (reutiliza modales existentes) ───────────────
    window.seCrearCliente = function () {
        if (typeof window.abrirModalClienteCrear === 'function') window.abrirModalClienteCrear();
        else Swal.fire('Atención', 'No se pudo abrir el formulario de cliente.', 'warning');
    };
    window.seCrearProducto = function () {
        if (typeof window.abrirModalProductoCrear === 'function') window.abrirModalProductoCrear();
        else Swal.fire('Atención', 'No se pudo abrir el formulario de producto.', 'warning');
    };

    // Autoseleccionar la entidad recién creada (best-effort según el payload del evento).
    document.addEventListener('clienteGuardado', (e) => {
        const j = e.detail || {}; const c = j.data || j;
        if (c && c.id) seSeleccionarCliente({ id: c.id, nombre: c.nombre || j.nombre, identificacion: c.identificacion, direccion: c.direccion, correo: c.correo || c.email, telefono: c.telefono });
    });
    document.addEventListener('productoGuardado', () => {
        // El nuevo producto queda disponible en el buscador de líneas; nada que autoseleccionar aquí.
    });
})();
