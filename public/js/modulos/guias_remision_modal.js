/**
 * Lógica compartida para el Modal de Guías de Remisión
 */
(function (window, document) {
    'use strict';

    // Obtener la URL base de forma segura. Se usa B_URL de factura_venta, BASE_URL global u obtencion del DOM
    function getBaseUrlGR() {
        if (typeof B_URL !== 'undefined' && B_URL) return B_URL;
        if (typeof BASE_URL !== 'undefined' && BASE_URL) return BASE_URL;
        if (typeof window.B_URL !== 'undefined' && window.B_URL) return window.B_URL;
        if (typeof window.BASE_URL !== 'undefined' && window.BASE_URL) return window.BASE_URL;
        
        // Fallback dinámico: extraer de la etiqueta script actual
        const scripts = document.getElementsByTagName('script');
        for (let i = 0; i < scripts.length; i++) {
            const src = scripts[i].src;
            if (src && src.includes('/js/modulos/guias_remision_modal.js')) {
                return src.split('/js/modulos/guias_remision_modal.js')[0];
            }
        }
        return '';
    }
    const urlBaseGR = getBaseUrlGR() + '/modulos/guias_remision';
    let idActual = null;
    let estadoActual = 'borrador';
    let bloquearSecuencial = false;
    let timerFactura = null;
    let timerTransp = null;
    let timerCliente = null;
    let timerProdInline = null;

    // --- Funciones Globales ---

    window.GR_abrirCrear = function () {
        window.GR_resetModal();
        idActual = null;
        estadoActual = 'borrador';
        document.getElementById('gr-modal-titulo').textContent = 'Nueva Guía de Remisión';
        document.getElementById('gr-numero-badge').style.display = 'none';
        
        const modalEl = document.getElementById('modalGuiaRemision');
        if (modalEl) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            window.GR_actualizarSecuencial();
            window.GR_registrarAutoGuardado();
            window.GR_verificarRespaldo();
        }
    };

    window.GR_abrirEditar = function (data) {
        const r = (data instanceof HTMLElement) ? JSON.parse(data.dataset.row) : data;
        window.GR_resetModal();
        
        fetch(urlBaseGR + '/get-guia-ajax?id=' + r.id)
            .then(r => r.json())
            .then(json => {
                if (json.ok) {
                    window.GR_cargarDatosModal(json.cabecera, json.detalles, json.info_adicional);
                    const modalEl = document.getElementById('modalGuiaRemision');
                    if (modalEl) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }
                } else {
                    Swal.fire('Error', json.mensaje || 'No se pudo cargar la guía.', 'error');
                }
            });
    };

    window.GR_resetModal = function () {
        idActual = null;
        estadoActual = 'borrador';
        bloquearSecuencial = false;

        document.getElementById('gr-id').value = '';
        document.getElementById('gr-modal-titulo').textContent = 'Nueva Guía de Remisión';
        document.getElementById('gr-numero-badge').style.display = 'none';

        const acBar = document.getElementById('gr-acciones-existente');
        if (acBar) { acBar.classList.add('d-none'); acBar.classList.remove('d-flex'); }

        const btnEl = document.getElementById('btn-gr-eliminar');
        if (btnEl) btnEl.classList.add('d-none');

        ['gr-motivo','gr-partida','gr-destino','gr-ruta',
         'gr-num-doc-sustento','gr-doc-aduanero','gr-cod-est-destino'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = '';
        });
        document.getElementById('gr-secuencial').value = '';
        document.getElementById('gr-cod-doc-sustento').value = '01';

        const hoy = new Date().toISOString().split('T')[0];
        document.getElementById('gr-fecha-emision').value = hoy;
        document.getElementById('gr-fecha-inicio').value  = hoy;
        document.getElementById('gr-fecha-fin').value     = hoy;
        
        document.getElementById('gr-id-transportista').value   = '';
        document.getElementById('gr-search-transportista').value = '';
        document.getElementById('gr-id-cliente').value   = '';
        document.getElementById('gr-search-cliente').value = '';
        document.getElementById('gr-placa').value = '';

        const infoTransp = document.getElementById('gr-info-transportista');
        if (infoTransp) infoTransp.classList.add('d-none');
        
        const lblTranspId = document.getElementById('gr-lbl-transp-id');
        if (lblTranspId) lblTranspId.textContent = '';
        
        const lblTranspPlaca = document.getElementById('gr-lbl-transp-placa');
        if (lblTranspPlaca) lblTranspPlaca.textContent = '';

        const infoCli = document.getElementById('gr-info-cliente');
        if (infoCli) infoCli.classList.add('d-none');
        
        const lblCliRuc = document.getElementById('gr-lbl-cliente-ruc');
        if (lblCliRuc) lblCliRuc.textContent = '';
        
        const lblCliDir = document.getElementById('gr-lbl-cliente-direccion');
        if (lblCliDir) lblCliDir.textContent = '';

        // SRI tab
        document.getElementById('gr-sri-badge-estado').textContent = 'Sin enviar';
        document.getElementById('gr-sri-badge-estado').className   = 'badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2';
        document.getElementById('gr-sri-clave-acceso').value            = '';
        document.getElementById('gr-sri-ambiente').value                = '';
        document.getElementById('gr-sri-tipo-emision').value            = '';
        document.getElementById('gr-sri-num-autorizacion').value        = '';
        document.getElementById('gr-sri-fecha-autorizacion').value      = '';
        document.getElementById('gr-sri-numero-documento').value        = '';
        document.getElementById('gr-sri-identificacion-cliente').value  = '';
        document.getElementById('gr-sri-correo-cliente').value          = '';
        window.GR_FECHA_EMISION = null;
        window.GR_CLIENTE_RUC   = '';
        document.getElementById('gr-sri-mensajes').innerHTML       = '<p class="text-muted text-center mb-0 py-3 small">Sin respuesta del SRI registrada.</p>';
        document.getElementById('gr-sri-tbody-historial').innerHTML= '<tr><td colspan="4" class="text-center py-3 text-muted">Sin historial de envíos.</td></tr>';

        document.getElementById('gr-tbody-detalle').innerHTML = '';
        document.getElementById('gr-tbody-adicional').innerHTML = '';
        const cntEl = document.getElementById('gr-count-items');
        if (cntEl) cntEl.textContent = '0';

        const tabBtn = document.getElementById('tab-gr-guia-btn');
        if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
    };

    window.GR_cargarDatosModal = function (cab, detalles, adicional) {
        bloquearSecuencial = true;
        idActual     = cab.id;
        estadoActual = cab.estado || 'borrador';

        document.getElementById('gr-id').value = cab.id;
        const numero = (cab.establecimiento||'') + '-' + (cab.punto_emision||'') + '-' + (cab.secuencial||'');
        const cliente = cab.cliente_nombre || '—';
        const transportista = cab.transportista_nombre || '—';
        document.getElementById('gr-modal-titulo').textContent = `Guía de remisión #${numero} - ${cliente} / ${transportista}`;

        const acBar = document.getElementById('gr-acciones-existente');
        if (acBar) { acBar.classList.remove('d-none'); acBar.classList.add('d-flex'); }

        const btnEl = document.getElementById('btn-gr-eliminar');
        if (btnEl) {
            if (['borrador','anulado'].includes(estadoActual)) btnEl.classList.remove('d-none');
            else btnEl.classList.add('d-none');
        }

        document.getElementById('gr-secuencial').value       = cab.secuencial || '';
        document.getElementById('gr-fecha-emision').value    = cab.fecha_emision || '';
        document.getElementById('gr-fecha-inicio').value     = cab.fecha_inicio_transporte || '';
        document.getElementById('gr-fecha-fin').value        = cab.fecha_fin_transporte || '';
        document.getElementById('gr-motivo').value           = cab.motivo_traslado || '';
        document.getElementById('gr-partida').value          = cab.direccion_partida || '';
        document.getElementById('gr-destino').value          = cab.direccion_destino || '';
        document.getElementById('gr-ruta').value             = cab.ruta || '';
        document.getElementById('gr-placa').value            = cab.placa || '';
        document.getElementById('gr-cod-doc-sustento').value = cab.cod_doc_sustento || '';
        document.getElementById('gr-num-doc-sustento').value  = cab.num_doc_sustento || '';
        document.getElementById('gr-doc-aduanero').value      = cab.doc_aduanero_unico || '';
        document.getElementById('gr-cod-est-destino').value   = cab.cod_establecimiento_destino || '';

        // Transportista
        document.getElementById('gr-id-transportista').value    = cab.id_transportista || '';
        document.getElementById('gr-search-transportista').value = cab.transportista_nombre || '';
        if (cab.transportista_nombre) {
            document.getElementById('gr-lbl-transp-id').textContent    = cab.transportista_identificacion || '';
            document.getElementById('gr-lbl-transp-placa').textContent = cab.placa || '';
            document.getElementById('gr-info-transportista').classList.remove('d-none');
        }

        // Cliente
        document.getElementById('gr-id-cliente').value     = cab.id_cliente || '';
        document.getElementById('gr-search-cliente').value  = cab.cliente_nombre || '';
        if (cab.cliente_nombre) {
            document.getElementById('gr-lbl-cliente-ruc').textContent = cab.cliente_ruc || '';
            document.getElementById('gr-lbl-cliente-direccion').textContent = cab.cliente_direccion || '';
            document.getElementById('gr-info-cliente').classList.remove('d-none');
        }

        // SRI tab
        const sriMap = {
            'autorizado':    'bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2',
            'anulado':       'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2',
            'no_autorizado': 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2',
            'borrador':      'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2',
        };
        const sriB = document.getElementById('gr-sri-badge-estado');
        sriB.textContent = (estadoActual || 'borrador').charAt(0).toUpperCase() + (estadoActual || 'borrador').slice(1).replace('_',' ');
        sriB.className   = 'badge ' + (sriMap[estadoActual] || sriMap['borrador']);

        document.getElementById('gr-sri-clave-acceso').value       = cab.clave_acceso || '';
        document.getElementById('gr-sri-num-autorizacion').value   = cab.numero_autorizacion || cab.clave_acceso || '';
        document.getElementById('gr-sri-fecha-autorizacion').value = cab.fecha_autorizacion
            ? new Date(cab.fecha_autorizacion.replace(' ', 'T')).toLocaleString('es-EC') : '';

        const amb = String(cab.tipo_ambiente || '');
        document.getElementById('gr-sri-ambiente').value     = amb === '2' ? '2 - PRODUCCIÓN' : (amb === '1' ? '1 - PRUEBAS' : amb);
        const emi = String(cab.tipo_emision || '');
        document.getElementById('gr-sri-tipo-emision').value = emi === '1' ? '1 - NORMAL' : emi;

        // Número de documento
        const elNroDoc = document.getElementById('gr-sri-numero-documento');
        if (elNroDoc) {
            const est = String(cab.establecimiento || '').padStart(3, '0');
            const pto = String(cab.punto_emision   || '').padStart(3, '0');
            const sec = String(cab.secuencial      || '').padStart(9, '0');
            elNroDoc.value = `${est}-${pto}-${sec}`;
        }
        const elIdentif = document.getElementById('gr-sri-identificacion-cliente');
        if (elIdentif) elIdentif.value = cab.cliente_ruc   || '';
        const elCorreo  = document.getElementById('gr-sri-correo-cliente');
        if (elCorreo)   elCorreo.value  = cab.cliente_email || '';

        // Guardar globales para validación de anulación
        window.GR_FECHA_EMISION = (cab.fecha_emision || '').split(' ')[0].split('T')[0] || null;
        window.GR_CLIENTE_RUC   = (cab.cliente_ruc   || '').trim();

        // Mensajes SRI
        const msgEl = document.getElementById('gr-sri-mensajes');
        if (cab.mensajes_sri) {
            try {
                const msgs = JSON.parse(cab.mensajes_sri);
                if (Array.isArray(msgs) && msgs.length > 0) {
                    msgEl.innerHTML = msgs.map(m => `
                        <div class="mb-2 pb-2 border-bottom">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-10 small">${m.identificador || 'ERROR'}</span>
                                <small class="text-muted" style="font-size:0.7rem">${m.tipo || ''}</small>
                            </div>
                            <div class="mt-1 fw-medium text-wrap">${m.mensaje || ''}</div>
                            ${m.informacionAdicional ? `<div class="small text-muted mt-1">${m.informacionAdicional}</div>` : ''}
                        </div>
                    `).join('');
                } else {
                    msgEl.innerHTML = `<div class="p-2 small text-dark">${cab.mensajes_sri}</div>`;
                }
            } catch(e) {
                msgEl.innerHTML = `<div class="p-2 small text-dark">${cab.mensajes_sri}</div>`;
            }
        } else {
            msgEl.innerHTML = '<p class="text-muted text-center mb-0 py-3 small">Sin respuesta del SRI registrada.</p>';
        }

        // Bloquear si no es borrador
        const esBorrador = estadoActual === 'borrador';
        ['gr-fecha-emision','gr-fecha-inicio','gr-fecha-fin','gr-motivo',
         'gr-partida','gr-destino','gr-ruta','gr-placa',
         'gr-num-doc-sustento','gr-doc-aduanero','gr-cod-est-destino',
         'gr-search-transportista','gr-search-cliente'
        ].forEach(id => { const el = document.getElementById(id); if (el) el.readOnly = !esBorrador; });
        document.getElementById('gr-cod-doc-sustento').disabled = !esBorrador;

        const grSerie = document.getElementById('gr-serie');
        if (grSerie) {
            grSerie.disabled = !esBorrador;
            if (cab.id_punto_emision) grSerie.value = cab.id_punto_emision;
        }
        bloquearSecuencial = false;

        const btnGuardar = document.getElementById('btn-gr-guardar');
        if (btnGuardar) btnGuardar.style.display = esBorrador ? 'inline-block' : 'none';

        const btnEnviar = document.getElementById('btn-gr-enviar-sri');
        if (btnEnviar) btnEnviar.style.display = ['borrador','no_autorizado','devuelta'].includes(estadoActual) ? 'inline-block' : 'none';

        const btnAnular = document.getElementById('btn-gr-anular');
        if (btnAnular) btnAnular.style.display = ['borrador','autorizado'].includes(estadoActual) ? 'inline-block' : 'none';

        // Ocultar botones de agregar cuando no es borrador
        const btnAgrLin = document.getElementById('btn-gr-agregar-linea');
        if (btnAgrLin) btnAgrLin.style.display = esBorrador ? '' : 'none';
        const btnAgrAdi = document.getElementById('btn-gr-agregar-adicional');
        if (btnAgrAdi) btnAgrAdi.style.display = esBorrador ? '' : 'none';

        // Cargar detalles existentes como filas DOM
        document.getElementById('gr-tbody-detalle').innerHTML = '';
        (detalles || []).forEach(d => window.GR_agregarLinea(d));
        window.GR_actualizarNumeracion();

        // Cargar adicionales existentes como filas DOM
        document.getElementById('gr-tbody-adicional').innerHTML = '';
        (adicional || []).forEach(a => window.GR_agregarAdicionalLinea(a));
        window.GR_cargarHistorialSri(cab.id);

        window.GR_ID_ACTIVO = cab.id;
    };

    window.GR_actualizarSecuencial = function () {
        if (bloquearSecuencial) return;
        const sel = document.getElementById('gr-serie');
        const idPunto = sel?.value;
        if (!idPunto) return;
        const inputSec = document.getElementById('gr-secuencial');
        if (inputSec) inputSec.placeholder = 'Cargando...';
        fetch(urlBaseGR + '/get-secuencial-ajax?id_punto_emision=' + idPunto)
            .then(r => r.json())
            .then(d => {
                if (d.ok && inputSec) {
                    inputSec.value = d.formateado;
                    inputSec.placeholder = '000000001';
                }
            });
    };

    window.GR_formatearNumDoc = function (input) {
        let v = input.value.replace(/\D/g, '').slice(0, 15);
        if (v.length > 6) v = v.slice(0,3) + '-' + v.slice(3,6) + '-' + v.slice(6);
        else if (v.length > 3) v = v.slice(0,3) + '-' + v.slice(3);
        input.value = v;
    };

    window.GR_buscarFacturaSustento = function (q) {
        const tipo = document.getElementById('gr-cod-doc-sustento').value;
        if (tipo !== '01') { window.GR_formatearNumDoc(document.getElementById('gr-num-doc-sustento')); return; }
        
        clearTimeout(timerFactura);
        const div = document.getElementById('gr-dropdown-factura');
        if (q.length < 3) { div.style.display = 'none'; return; }

        timerFactura = setTimeout(() => {
            fetch(urlBaseGR + '/get-facturas-ajax?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(d => {
                    if (!d.ok || d.data.length === 0) { div.style.display = 'none'; return; }
                    let h = '';
                    d.data.forEach(f => {
                        const num = `${f.establecimiento}-${f.punto_emision}-${f.secuencial}`;
                        h += `<div class="p-2 border-bottom hover-bg-light cursor-pointer" onclick="GR_seleccionarFacturaSustento(${f.id}, '${num}')">
                                <div class="fw-bold small">${num}</div>
                                <div class="text-muted" style="font-size:0.7rem">${f.cliente_nombre}</div>
                              </div>`;
                    });
                    div.innerHTML = h;
                    div.style.display = 'block';
                });
        }, 300);
    };

    window.GR_seleccionarFacturaSustento = function (id, num) {
        const inputNum = document.getElementById('gr-num-doc-sustento');
        if (inputNum) inputNum.value = num;
        const dd = document.getElementById('gr-dropdown-factura');
        if (dd) dd.style.display = 'none';

        fetch(urlBaseGR + '/get-detalle-factura-ajax?id=' + id)
            .then(r => r.json())
            .then(d => {
                if (!d.ok) return;
                const cab = d.cabecera;
                
                // 1. Autocompletar Cliente
                window.GR_seleccionarCliente(cab.id_cliente, cab.cliente_nombre, cab.cliente_ruc, cab.cliente_direccion || '');

                // 2. Origen (Dirección del establecimiento actual)
                const selSerie = document.getElementById('gr-serie');
                if (selSerie) {
                    const idEst = selSerie.selectedOptions[0]?.dataset.idEst;
                    // Si GR_establecimientos no está definido, se puede omitir o buscar vía AJAX
                    if (window.GR_establecimientos) {
                        const est = window.GR_establecimientos.find(e => parseInt(e.id) === parseInt(idEst));
                        if (est) document.getElementById('gr-partida').value = est.direccion || '';
                    }
                }

                // 3. Destino (Dirección del cliente)
                document.getElementById('gr-destino').value = cab.cliente_direccion || '';

                // 4. Motivo: Venta
                document.getElementById('gr-motivo').value = 'Venta';

                // 5. Cód. est. destino: 001
                document.getElementById('gr-cod-est-destino').value = '001';

                // 6. Cargar Productos
                document.getElementById('gr-tbody-detalle').innerHTML = '';
                (d.detalles || []).forEach(det => {
                    window.GR_agregarLinea({
                        id_producto: det.id_producto,
                        codigo_principal: det.producto_codigo,
                        descripcion: det.producto_nombre,
                        cantidad: det.cantidad
                    });
                });
                window.GR_actualizarNumeracion();
            });
    };

    window.GR_buscarTransportista = function (q) {
        clearTimeout(timerTransp);
        const dd = document.getElementById('gr-dropdown-transportista');
        if (q.length < 2) { dd.style.display = 'none'; return; }
        timerTransp = setTimeout(() => {
            fetch(urlBaseGR + '/get-transportistas-ajax?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(d => {
                    if (!d.ok || !d.data.length) { dd.style.display = 'none'; return; }
                    dd.innerHTML = d.data.map(t =>
                        `<div class="p-2 border-bottom" style="cursor:pointer"
                              onmousedown="GR_seleccionarTransportista(${t.id},'${GR_esc(t.nombre)}','${GR_esc(t.identificacion||'')}','${GR_esc(t.placa||'')}')">
                            <strong>${t.nombre}</strong> <small class="text-muted ms-2">${t.identificacion||''}</small>
                            ${t.placa ? `<span class="badge bg-primary bg-opacity-10 text-primary ms-2">${t.placa}</span>` : ''}
                        </div>`
                    ).join('');
                    dd.style.display = 'block';
                });
        }, 250);
    };

    window.GR_seleccionarTransportista = function (id, nombre, identificacion, placa) {
        document.getElementById('gr-id-transportista').value    = id;
        document.getElementById('gr-search-transportista').value = nombre;
        if (placa && !document.getElementById('gr-placa').value) document.getElementById('gr-placa').value = placa;
        document.getElementById('gr-lbl-transp-id').textContent    = identificacion;
        document.getElementById('gr-lbl-transp-placa').textContent = placa;
        document.getElementById('gr-info-transportista').classList.remove('d-none');
        document.getElementById('gr-dropdown-transportista').style.display = 'none';
    };

    window.GR_buscarCliente = function (q) {
        clearTimeout(timerCliente);
        const dd = document.getElementById('gr-dropdown-cliente');
        if (q.length < 2) { dd.style.display = 'none'; return; }
        timerCliente = setTimeout(() => {
            fetch(urlBaseGR + '/get-clientes-ajax?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(d => {
                    if (!d.ok || !d.data.length) { dd.style.display = 'none'; return; }
                    dd.innerHTML = d.data.map(c =>
                        `<div class="p-2 border-bottom" style="cursor:pointer"
                              onmousedown="GR_seleccionarCliente(${c.id},'${GR_esc(c.nombre)}','${GR_esc(c.identificacion||'')}','${GR_esc(c.direccion||'')}')">
                            <strong>${c.nombre}</strong> <small class="text-muted ms-2">${c.identificacion||''}</small>
                        </div>`
                    ).join('');
                    dd.style.display = 'block';
                });
        }, 250);
    };

    window.GR_seleccionarCliente = function (id, nombre, identificacion, direccion) {
        document.getElementById('gr-id-cliente').value    = id;
        document.getElementById('gr-search-cliente').value = nombre;
        document.getElementById('gr-lbl-cliente-ruc').textContent = identificacion;
        document.getElementById('gr-lbl-cliente-direccion').textContent = direccion;
        document.getElementById('gr-info-cliente').classList.remove('d-none');
        if (!document.getElementById('gr-destino').value) document.getElementById('gr-destino').value = direccion || '';
        document.getElementById('gr-dropdown-cliente').style.display = 'none';
    };

    window.GR_agregarLinea = function (data) {
        const esBorrador = estadoActual === 'borrador';
        const tbody = document.getElementById('gr-tbody-detalle');
        if (!tbody) return;
        const tr = document.createElement('tr');
        tr.className = 'row-detalle';
        const ro = esBorrador ? '' : 'readonly';
        tr.innerHTML = `
            <td class="p-0 text-center align-middle" style="width:36px;font-size:.78rem;color:#6c757d"></td>
            <td class="p-0" style="width:110px">
                <input type="hidden" class="input-id-producto" value="${data?.id_producto || ''}">
                <input type="text" class="input-detalle input-codigo" placeholder="Código" value="${GR_esc(data?.codigo_principal||'')}" ${ro}>
            </td>
            <td class="p-0 position-relative">
                <input type="text" class="input-detalle input-descripcion fw-medium" placeholder="Buscar o escribir descripción..." style="text-transform:uppercase" value="${GR_esc(data?.descripcion||'')}" ${ro}>
                <div class="dropdown-gr-inline position-absolute bg-white border rounded shadow-sm" style="display:none;max-height:200px;overflow-y:auto;top:100%;left:0;width:100%;z-index:2100"></div>
            </td>
            <td class="p-0" style="width:110px">
                <input type="number" class="input-detalle text-end input-cantidad" step="any" min="0.000001" value="${data?.cantidad || 1}" ${ro}>
            </td>
            <td class="p-0 text-center align-middle" style="width:36px">
                ${esBorrador ? `<button type="button" class="btn btn-link p-0 text-danger shadow-none border-0 remove-row-gr"
                    onclick="this.closest('tr').remove(); GR_actualizarNumeracion();" title="Quitar">
                    <i class="bi bi-trash3" style="font-size:.8rem"></i></button>` : ''}
            </td>`;
        tbody.appendChild(tr);

        if (esBorrador) {
            const inputDesc = tr.querySelector('.input-descripcion');
            const dropdown  = tr.querySelector('.dropdown-gr-inline');

            inputDesc.addEventListener('input', function() {
                const q = this.value.trim();
                clearTimeout(timerProdInline);
                if (q.length < 2) { dropdown.style.display = 'none'; return; }
                timerProdInline = setTimeout(() => {
                    fetch(urlBaseGR + '/get-productos-ajax?q=' + encodeURIComponent(q))
                        .then(r => r.json())
                        .then(res => {
                            if (!res.ok || !res.data.length) { dropdown.style.display = 'none'; return; }
                            dropdown.innerHTML = res.data.map((p, idx) =>
                                `<div class="p-2 border-bottom" style="cursor:pointer;font-size:.82rem" data-idx="${idx}">
                                    <strong>${p.nombre}</strong>${p.codigo ? `<small class="text-muted ms-2">[${p.codigo}]</small>` : ''}
                                 </div>`
                            ).join('');
                            dropdown.style.display = 'block';
                            dropdown.querySelectorAll('div[data-idx]').forEach(div => {
                                div.addEventListener('mousedown', e => {
                                    e.preventDefault();
                                    const p = res.data[parseInt(div.dataset.idx)];
                                    tr.querySelector('.input-id-producto').value = p.id;
                                    tr.querySelector('.input-codigo').value = p.codigo || '';
                                    inputDesc.value = p.nombre.toUpperCase();
                                    dropdown.style.display = 'none';
                                    tr.querySelector('.input-cantidad').focus();
                                });
                            });
                        });
                }, 250);
            });

            inputDesc.addEventListener('blur', () => {
                setTimeout(() => { dropdown.style.display = 'none'; }, 200);
            });

            if (!data) setTimeout(() => inputDesc.focus(), 30);
        }

        window.GR_actualizarNumeracion();
    };

    window.GR_actualizarNumeracion = function () {
        document.querySelectorAll('#gr-tbody-detalle tr.row-detalle').forEach((tr, i) => {
            const c = tr.querySelector('td:first-child');
            if (c) c.textContent = i + 1;
        });
        const cntEl = document.getElementById('gr-count-items');
        if (cntEl) cntEl.textContent = document.querySelectorAll('#gr-tbody-detalle tr.row-detalle').length;
    };

    window.GR_agregarAdicionalLinea = function (data) {
        const esBorrador = estadoActual === 'borrador';
        const tbody = document.getElementById('gr-tbody-adicional');
        if (!tbody) return;
        const tr = document.createElement('tr');
        tr.className = 'row-adicional';
        const ro = esBorrador ? '' : 'readonly';
        
        // Manejar tanto objeto {nombre, valor} como pasar nombre y valor como argumentos (legacy)
        let n = '', v = '';
        if (typeof data === 'object') { n = data.nombre || ''; v = data.valor || ''; }
        else if (arguments.length >= 1) { n = arguments[0] || ''; v = arguments[1] || ''; }

        tr.innerHTML = `
            <td class="p-0">
                <input type="text" class="form-control form-control-sm border-0 bg-transparent input-adic-nombre"
                    style="padding:0 4px;height:20px;font-size:0.78rem;" placeholder="Nombre..." maxlength="30"
                    value="${GR_esc(n)}" ${ro}>
            </td>
            <td class="p-0">
                <input type="text" class="form-control form-control-sm border-0 bg-transparent input-adic-valor"
                    style="padding:0 4px;height:20px;font-size:0.78rem;" placeholder="Valor..." maxlength="300"
                    value="${GR_esc(v)}" ${ro}>
            </td>
            <td class="p-0 text-center pe-1">
                ${esBorrador ? `<button type="button" class="btn btn-link btn-sm p-0 m-0 text-danger shadow-none"
                    onclick="this.closest('tr').remove();" title="Quitar">
                    <i class="bi bi-x-circle-fill"></i></button>` : ''}
            </td>`;
        tbody.appendChild(tr);
        if (!n && esBorrador) tr.querySelector('.input-adic-nombre').focus();
    };

    window.GR_copiarCampoSri = function (inputId) {
        const el = document.getElementById(inputId);
        const val = el ? el.value.trim() : '';
        if (!val) return;
        navigator.clipboard.writeText(val).then(() => {
            const btn = el.nextElementSibling;
            if (btn) {
                const icon = btn.querySelector('i');
                if (icon) { icon.classList.replace('bi-clipboard', 'bi-clipboard-check'); btn.classList.replace('btn-outline-secondary', 'btn-outline-success'); }
                setTimeout(() => {
                    if (icon) { icon.classList.replace('bi-clipboard-check', 'bi-clipboard'); btn.classList.replace('btn-outline-success', 'btn-outline-secondary'); }
                }, 2000);
            }
        }).catch(() => { if (el) { el.select(); document.execCommand('copy'); } });
    };
    window.GR_copiarClaveAcceso = function () { window.GR_copiarCampoSri('gr-sri-clave-acceso'); };

    window.GR_recolectarDetalles = function () {
        const detalles = [];
        document.querySelectorAll('#gr-tbody-detalle tr.row-detalle').forEach(tr => {
            const desc = tr.querySelector('.input-descripcion')?.value.trim();
            if (!desc) return;
            detalles.push({
                id_producto:      tr.querySelector('.input-id-producto')?.value || null,
                codigo_principal: tr.querySelector('.input-codigo')?.value.trim() || '',
                codigo_auxiliar:  '',
                descripcion:      desc.toUpperCase(),
                cantidad:         parseFloat(tr.querySelector('.input-cantidad')?.value) || 1,
            });
        });
        return detalles;
    };

    window.GR_recolectarAdicionales = function () {
        const adicionales = [];
        document.querySelectorAll('#gr-tbody-adicional tr.row-adicional').forEach(tr => {
            const nombre = tr.querySelector('.input-adic-nombre')?.value.trim();
            const valor  = tr.querySelector('.input-adic-valor')?.value.trim();
            if (nombre && valor) adicionales.push({ nombre, valor });
        });
        return adicionales;
    };

    window.GR_guardar = function () {
        const serieEl  = document.getElementById('gr-serie');
        const serieOpt = serieEl?.options[serieEl?.selectedIndex];

        const payload = {
            id:                              document.getElementById('gr-id').value || null,
            id_establecimiento:              serieOpt?.dataset?.idEst   || '',
            id_punto_emision:                serieEl?.value             || '',
            establecimiento:                 serieOpt?.dataset?.codEst  || '001',
            punto_emision:                   serieOpt?.dataset?.codPunto|| '001',
            secuencial:                      document.getElementById('gr-secuencial').value,
            fecha_emision:                   document.getElementById('gr-fecha-emision').value,
            id_cliente:                      document.getElementById('gr-id-cliente').value,
            id_transportista:                document.getElementById('gr-id-transportista').value,
            placa:                           document.getElementById('gr-placa').value,
            fecha_inicio_transporte:         document.getElementById('gr-fecha-inicio').value,
            fecha_fin_transporte:            document.getElementById('gr-fecha-fin').value,
            motivo_traslado:                 document.getElementById('gr-motivo').value,
            direccion_partida:               document.getElementById('gr-partida').value,
            direccion_destino:               document.getElementById('gr-destino').value,
            ruta:                            document.getElementById('gr-ruta').value,
            cod_doc_sustento:                document.getElementById('gr-cod-doc-sustento').value,
            num_doc_sustento:                document.getElementById('gr-num-doc-sustento').value,
            num_autorizacion_doc_sustento:   '',
            fecha_emision_doc_sustento:      '',
            doc_aduanero_unico:              document.getElementById('gr-doc-aduanero').value,
            cod_establecimiento_destino:     document.getElementById('gr-cod-est-destino').value,
            tipo_ambiente:                   '1',
            tipo_emision:                    '1',
            estado:                          'borrador',
            detalles:                        window.GR_recolectarDetalles(),
            adicionales:                     window.GR_recolectarAdicionales(),
        };

        const btn = document.getElementById('btn-gr-guardar');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...'; }

        fetch(urlBaseGR + '/guardar-ajax', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(d => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar'; }
            if (d.ok) {
                window.GR_eliminarRespaldo();
                const modalEl = document.getElementById('modalGuiaRemision');
                if (modalEl) bootstrap.Modal.getInstance(modalEl).hide();
                
                if (typeof window.GR_cargar === 'function') window.GR_cargar(window.GR_page || 1);
                Swal.fire({ icon: 'success', title: '¡Guardado!', text: d.mensaje, timer: 2000, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Error al guardar', text: d.mensaje || 'Error al guardar.' });
            }
        })
        .catch(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar'; }
            Swal.fire({ icon: 'error', title: 'Error de Conexión', text: 'No se pudo conectar con el servidor. Intente nuevamente.' });
        });
    };

    window.GR_eliminar = async function () {
        if (!idActual) return;
        const conf = await Swal.fire({
            icon: 'warning',
            title: '¿Eliminar guía?',
            text: 'Esta acción no se puede deshacer.',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        });
        if (!conf.isConfirmed) return;
        fetch(urlBaseGR + '/eliminar-ajax', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + idActual,
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                const modalEl = document.getElementById('modalGuiaRemision');
                if (modalEl) bootstrap.Modal.getInstance(modalEl).hide();
                if (typeof window.GR_cargar === 'function') window.GR_cargar(1);
                Swal.fire({ icon: 'success', title: 'Eliminado', text: d.mensaje, timer: 2000, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.mensaje });
            }
        });
    };

    function grFechaLimiteAnulacion(fechaEmision) {
        const [y, m] = fechaEmision.split('-').map(Number);
        const mesLimite  = m === 12 ? 0 : m;
        const anioLimite = m === 12 ? y + 1 : y;
        let limite = new Date(anioLimite, mesLimite, 7);
        if (limite.getDay() === 6) limite.setDate(9); // sábado → lunes
        if (limite.getDay() === 0) limite.setDate(8); // domingo → lunes
        return limite;
    }

    window.GR_anular = async function () {
        if (!idActual) return;

        // ── Regla: Plazo del día 7 del mes siguiente ─────────────────────────
        if (window.GR_FECHA_EMISION) {
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const limiteAnulacion = grFechaLimiteAnulacion(window.GR_FECHA_EMISION);
            limiteAnulacion.setHours(23, 59, 59, 999);

            if (hoy > limiteAnulacion) {
                const fmtLimite = limiteAnulacion.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' });
                await Swal.fire({
                    icon: 'error',
                    title: 'Fuera de plazo',
                    html: `El plazo para anular esta guía de remisión venció el <strong>${fmtLimite}</strong>.<br>
                           <small class="text-muted">El SRI permite anular hasta el día 7 del mes siguiente a la emisión<br>
                           (o el siguiente día hábil si cae en fin de semana).</small>`,
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }
        }

        const conf = await Swal.fire({
            icon: 'warning',
            title: '¿Anular guía de remisión?',
            text: 'La guía quedará marcada como anulada y no podrá editarse. No se puede revertir.',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-slash-circle me-2"></i>Sí, anular',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        });
        if (!conf.isConfirmed) return;

        const btn = document.getElementById('btn-gr-anular');
        const btnOrigHtml = btn?.innerHTML;
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Anulando...'; }

        try {
            const r = await fetch(urlBaseGR + '/anular-ajax', {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + idActual,
            });
            const d = await r.json();
            if (d.ok) {
                const modalEl = document.getElementById('modalGuiaRemision');
                if (modalEl) bootstrap.Modal.getInstance(modalEl).hide();
                if (typeof window.GR_cargar === 'function') window.GR_cargar(window.GR_page || 1);
                Swal.fire({ icon: 'success', title: 'Anulada', text: d.mensaje, timer: 2000, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: d.mensaje });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Intente nuevamente.', confirmButtonColor: '#d33' });
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = btnOrigHtml; }
        }
    };

    window.GR_enviarSri = async function () {
        if (!idActual) return;
        const conf = await Swal.fire({
            icon: 'question',
            title: 'Enviar al SRI',
            text: '¿Desea enviar esta guía al SRI para autorización electrónica?',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, enviar',
            cancelButtonText: 'Cancelar',
        });
        if (!conf.isConfirmed) return;
        const btn = document.getElementById('btn-gr-enviar-sri');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...'; }
        fetch(urlBaseGR + '/enviar-sri-ajax', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + idActual,
        })
        .then(r => r.json())
        .then(d => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i>Enviar al SRI'; }
            if (d.ok) {
                if (typeof window.GR_cargar === 'function') window.GR_cargar(window.GR_page || 1);
                const modalEl = document.getElementById('modalGuiaRemision');
                if (modalEl) bootstrap.Modal.getInstance(modalEl).hide();
                Swal.fire({ icon: 'success', title: '¡Autorizado!', text: d.mensaje, timer: 2500, showConfirmButton: false });
            } else {
                const errores = d.errores && d.errores.length
                    ? '<ul class="text-start mt-2 mb-0 ps-3">' + d.errores.map(e => `<li>${e.mensaje || JSON.stringify(e)}</li>`).join('') + '</ul>'
                    : '';
                Swal.fire({
                    icon: 'error',
                    title: 'No autorizado',
                    html: `<div>${d.mensaje || 'Error al enviar al SRI.'}</div>${errores}`,
                    confirmButtonColor: '#d33',
                });
            }
        })
        .catch(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i>Enviar al SRI'; }
            Swal.fire({ icon: 'error', title: 'Error de Conexión', text: 'No se pudo conectar con el servidor.' });
        });
    };

    window.GR_exportarPdf = function () { if (idActual) window.open(urlBaseGR + '/exportar-pdf-ajax?id=' + idActual, '_blank'); };
    window.GR_exportarXml = function () { if (idActual) window.open(urlBaseGR + '/exportar-xml-ajax?id=' + idActual, '_blank'); };

    window.GR_cargarHistorialSri = function (id) {
        fetch(urlBaseGR + '/get-historial-sri-ajax?id=' + id)
            .then(r => r.json())
            .then(d => {
                const tbody = document.getElementById('gr-sri-tbody-historial');
                if (!tbody) return;
                if (!d.ok || !d.data.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Sin historial de envíos.</td></tr>';
                    return;
                }
                tbody.innerHTML = d.data.map(l => `<tr>
                    <td class="ps-2">${l.created_at ? new Date(l.created_at).toLocaleString('es-EC') : ''}</td>
                    <td>${l.ambiente||''}</td>
                    <td>${l.accion||''} / ${l.estado_sri||''}</td>
                    <td>${l.mensaje||''}</td>
                </tr>`).join('');
            });
    };

    window.GR_cargarDetallesDesdeFactura = function (idFactura) {
        if (!idFactura) return;
        fetch(urlBaseGR + '/get-detalle-factura-ajax?id=' + idFactura)
            .then(r => r.json())
            .then(d => {
                if (!d.ok) return;
                document.getElementById('gr-tbody-detalle').innerHTML = '';
                (d.detalles || []).forEach(det => {
                    window.GR_agregarLinea({
                        id_producto: det.id_producto,
                        codigo_principal: det.producto_codigo,
                        descripcion: det.producto_nombre,
                        cantidad: det.cantidad
                    });
                });
                window.GR_actualizarNumeracion();
            });
    };

    // --- Respaldo Local ---
    const GR_STORAGE_KEY = 'gr_borrador';

    window.GR_autoGuardar = function () {
        if (estadoActual !== 'borrador') return;
        const serieEl = document.getElementById('gr-serie');
        const serieOpt = serieEl?.options[serieEl?.selectedIndex];
        const estado = {
            id:                            document.getElementById('gr-id').value || null,
            id_establecimiento:            serieOpt?.dataset?.idEst || '',
            id_punto_emision:              serieEl?.value || '',
            establecimiento:               serieOpt?.dataset?.codEst || '001',
            punto_emision:                 serieOpt?.dataset?.codPunto || '001',
            secuencial:                    document.getElementById('gr-secuencial').value,
            fecha_emision:                 document.getElementById('gr-fecha-emision').value,
            id_cliente:                    document.getElementById('gr-id-cliente').value,
            cliente_nombre:                document.getElementById('gr-search-cliente').value,
            cliente_identificacion:        document.getElementById('gr-lbl-cliente-ruc')?.textContent || '',
            cliente_direccion:             document.getElementById('gr-lbl-cliente-direccion')?.textContent || '',
            id_transportista:              document.getElementById('gr-id-transportista').value,
            transportista_nombre:          document.getElementById('gr-search-transportista').value,
            transportista_identificacion:  document.getElementById('gr-lbl-transp-id')?.textContent || '',
            placa:                         document.getElementById('gr-placa').value,
            fecha_inicio_transporte:       document.getElementById('gr-fecha-inicio').value,
            fecha_fin_transporte:          document.getElementById('gr-fecha-fin').value,
            motivo_traslado:               document.getElementById('gr-motivo').value,
            direccion_partida:             document.getElementById('gr-partida').value,
            direccion_destino:             document.getElementById('gr-destino').value,
            ruta:                          document.getElementById('gr-ruta').value,
            cod_doc_sustento:              document.getElementById('gr-cod-doc-sustento').value,
            num_doc_sustento:              document.getElementById('gr-num-doc-sustento').value,
            doc_aduanero_unico:            document.getElementById('gr-doc-aduanero').value,
            cod_establecimiento_destino:   document.getElementById('gr-cod-est-destino').value,
            detalles:                      window.GR_recolectarDetalles(),
            adicionales:                   window.GR_recolectarAdicionales(),
        };
        // Solo guardar si hay algo significativo
        if (!estado.id_cliente && !estado.detalles.length && !estado.id_transportista) {
            localStorage.removeItem(GR_STORAGE_KEY);
            return;
        }
        localStorage.setItem(GR_STORAGE_KEY, JSON.stringify(estado));
    };

    window.GR_eliminarRespaldo = function () {
        localStorage.removeItem(GR_STORAGE_KEY);
    };

    window.GR_verificarRespaldo = function () {
        const respaldoRaw = localStorage.getItem(GR_STORAGE_KEY);
        if (respaldoRaw && estadoActual === 'borrador' && !document.getElementById('gr-id').value) {
            const data = JSON.parse(respaldoRaw);
            const clienteName = data.cliente_nombre || 'desconocido';
            
            Swal.fire({
                title: 'Guía sin guardar',
                html: `Hay una guía de remisión en borrador del cliente <strong>${clienteName}</strong>. ¿Desea restaurarla?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, restaurar',
                cancelButtonText: 'Nueva guía'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.GR_restaurarRespaldo(data);
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    window.GR_eliminarRespaldo();
                }
            });
        }
    };

    window.GR_restaurarRespaldo = function (data) {
        if (!data) return;
        if (data.id_punto_emision) document.getElementById('gr-serie').value = data.id_punto_emision;
        document.getElementById('gr-secuencial').value = data.secuencial || '';
        document.getElementById('gr-fecha-emision').value = data.fecha_emision || '';
        window.GR_seleccionarCliente(data.id_cliente, data.cliente_nombre, data.cliente_identificacion, data.cliente_direccion);
        window.GR_seleccionarTransportista(data.id_transportista, data.transportista_nombre, data.transportista_identificacion, data.placa);
        document.getElementById('gr-placa').value = data.placa || '';
        document.getElementById('gr-fecha-inicio').value = data.fecha_inicio_transporte || '';
        document.getElementById('gr-fecha-fin').value = data.fecha_fin_transporte || '';
        document.getElementById('gr-motivo').value = data.motivo_traslado || '';
        document.getElementById('gr-partida').value = data.direccion_partida || '';
        document.getElementById('gr-destino').value = data.direccion_destino || '';
        document.getElementById('gr-ruta').value = data.ruta || '';
        document.getElementById('gr-cod-doc-sustento').value = data.cod_doc_sustento || '01';
        document.getElementById('gr-num-doc-sustento').value = data.num_doc_sustento || '';
        document.getElementById('gr-doc-aduanero').value = data.doc_aduanero_unico || '';
        document.getElementById('gr-cod-est-destino').value = data.cod_establecimiento_destino || '';
        document.getElementById('gr-tbody-detalle').innerHTML = '';
        (data.detalles || []).forEach(det => window.GR_agregarLinea(det));
        document.getElementById('gr-tbody-adicional').innerHTML = '';
        (data.adicionales || []).forEach(ad => window.GR_agregarAdicionalLinea(ad));
        window.GR_actualizarNumeracion();
    };

    window.GR_registrarAutoGuardado = function () {
        const modal = document.getElementById('modalGuiaRemision');
        if (!modal || modal.dataset.autoGuardadoRegistrado) return;
        modal.dataset.autoGuardadoRegistrado = '1';
        const debounced = (() => {
            let t;
            return () => { clearTimeout(t); t = setTimeout(window.GR_autoGuardar, 800); };
        })();
        modal.addEventListener('input', debounced);
        modal.addEventListener('change', debounced);
    };

    // --- Auxiliares ---
    function GR_esc(s) {
        if (!s) return '';
        return s.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function initGrEvents() {
        ['gr-motivo', 'gr-partida', 'gr-destino', 'gr-ruta', 'gr-placa'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', function () { this.value = this.value.toUpperCase(); });
        });
        const numDoc = document.getElementById('gr-num-doc-sustento');
        if (numDoc) numDoc.addEventListener('input', function() { window.GR_formatearNumDoc(this); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGrEvents);
    } else {
        initGrEvents();
    }

})(window, document);


