(function () {
    'use strict';

    let modalFR;
    let modalFRTerceros;
    let formFR;
    let detalleBody;
    let pagosBody;
    let tercerosBody;

    let terceros = []; // estado en memoria de la pestaña "Terceros reembolsados"
    let opcionesPagoHtml = ''; // plantilla de <option> de forma de pago, capturada una sola vez

    document.addEventListener('DOMContentLoaded', () => {
        initModal();
        if (!window.currentSort) window.currentSort = window.FR_ORDEN_COL || 'fecha_emision';
        if (!window.currentDir) window.currentDir = window.FR_ORDEN_DIR || 'DESC';
        initTerceroSearch();
    });

    function r2(v) {
        return Math.round((parseFloat(v) || 0) * 100) / 100;
    }

    function setEl(id, prop, val) {
        const el = document.getElementById(id);
        if (el) el[prop] = val;
    }

    function debounce(fn, wait) {
        let t;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    // ─── LISTADO (INDEX) ────────────────────────────────────────────────────

    window.FR_cambiarPaginaAjax = (page) => window.FR_fetchSearch(page);

    window.FR_ordenar = (col) => {
        const dir = (window.currentSort === col && window.currentDir === 'ASC') ? 'DESC' : 'ASC';
        window.currentSort = col;
        window.currentDir = dir;
        if (typeof window.guardarOrdenacionVista === 'function') {
            window.guardarOrdenacionVista('modulos/factura-reembolso', col, dir);
        }
        window.FR_fetchSearch(1);
    };

    window.FR_fetchSearch = async (page = 1) => {
        const buscar = document.getElementById('buscarFR')?.value || '';
        const sort = window.currentSort || 'fecha_emision';
        const dir = window.currentDir || 'DESC';
        const url = `${BASE_URL}/modulos/factura-reembolso/searchAjax?b=${encodeURIComponent(buscar)}&page=${page}&sort=${encodeURIComponent(sort)}&dir=${encodeURIComponent(dir)}`;

        try {
            const resp = await fetch(url);
            if (!resp.ok) return;
            const data = await resp.json();
            if (!data.ok) return;

            const tbody = document.getElementById('fr-table-body');
            if (tbody) tbody.innerHTML = data.rows ?? '';
            const pg = document.getElementById('fr-pagination');
            if (pg) pg.innerHTML = data.pagination ?? '';
            const pgInfo = document.getElementById('fr-pagination-info');
            if (pgInfo) pgInfo.textContent = data.info ?? '';

            const btnPdf = document.getElementById('btnExportPdf');
            if (btnPdf && data.pdf_url) btnPdf.href = data.pdf_url;
            const btnExcel = document.getElementById('btnExportExcel');
            if (btnExcel && data.excel_url) btnExcel.href = data.excel_url;
        } catch (e) {
            console.error('Error al buscar facturas de reembolso:', e);
        }
    };

    // ─── MODAL ──────────────────────────────────────────────────────────────

    function initModal() {
        if (!formFR) formFR = document.getElementById('formFR');
        if (!detalleBody) detalleBody = document.getElementById('fr_detalle_body');
        if (!pagosBody) pagosBody = document.getElementById('fr_pagos_body');
        if (!tercerosBody) tercerosBody = document.getElementById('fr_terceros_body');

        // Plantilla de opciones de forma de pago: se captura UNA sola vez del select
        // que trae la vista (server-rendered), antes de que FR_renderPagos() pueda
        // borrar esa fila. FR_agregarPago() nunca debe depender de un <select> que ya
        // esté en el DOM, porque FR_renderPagos() borra todas las filas antes de
        // reconstruirlas.
        if (!opcionesPagoHtml) {
            const selPagoBase = document.getElementById('fr-select-pago-sri');
            if (selPagoBase) opcionesPagoHtml = selPagoBase.innerHTML;
        }

        if (!modalFRTerceros) {
            const modalTercerosEl = document.getElementById('modalFRTerceros');
            if (modalTercerosEl && typeof bootstrap !== 'undefined') {
                modalFRTerceros = new bootstrap.Modal(modalTercerosEl);
            }
        }

        if (modalFR) return true;
        const modalEl = document.getElementById('modalFR');
        if (modalEl && typeof bootstrap !== 'undefined') {
            modalFR = new bootstrap.Modal(modalEl);
            return true;
        }
        return false;
    }

    window.FR_abrirModalTerceros = () => {
        if (!modalFRTerceros) initModal();
        modalFRTerceros?.show();
    };

    window.FR_abrirModalNuevo = () => {
        if (!initModal()) return;
        FR_resetearYMostrar();
    };

    function FR_resetearYMostrar() {
        try {
            if (formFR) formFR.reset();
            setEl('fr_id', 'value', '');
            document.getElementById('modalFRTitulo').innerHTML = '<i class="bi bi-arrow-repeat text-primary me-2"></i>Nueva Factura de Reembolso';

            if (detalleBody) detalleBody.innerHTML = '';
            window.FR_agregarDetalle();
            document.querySelectorAll('#fr_pagos_body .row-fr-pago:not(:first-child)').forEach(tr => tr.remove());
            const primerPago = document.querySelector('#fr_pagos_body .row-fr-pago');
            if (primerPago) {
                primerPago.querySelector('select[name="fp_forma_pago[]"]').value = '';
                primerPago.querySelector('input[name="fp_total[]"]').value = '0.00';
            }
            frAplicarFormaPagoPorDefecto(null);
            setEl('fr-input-dias-credito', 'value', '0');
            setEl('fr-select-plazo-credito', 'value', 'dias');

            terceros = [];
            FR_renderTerceros();

            FR_limpiarInfoAdicional();
            setEl('fr_lbl_cliente_ruc', 'textContent', '');
            setEl('fr_lbl_cliente_direccion', 'textContent', '');
            setEl('fr_lbl_cliente_correo', 'textContent', '');
            setEl('fr_id_cliente', 'value', '');
            const infoCli = document.getElementById('fr_info_cliente');
            if (infoCli) infoCli.classList.add('d-none');

            FR_setModoLectura(false);
            window.FR_cargarSecuencial();

            setEl('fr-sri-clave-acceso', 'value', '');
            setEl('fr-sri-autorizacion', 'value', '');
            setEl('fr-sri-fecha-autorizacion', 'value', '');
            setEl('fr-sri-numero-documento', 'value', '');
            const tbodySri = document.getElementById('fr-sri-tbody-historial');
            if (tbodySri) tbodySri.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Sin historial de envíos.</td></tr>';
            const tbodyAsiento = document.getElementById('fr-asiento-tbody');
            if (tbodyAsiento) tbodyAsiento.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">El asiento se genera al enviar la factura al SRI (autorización).</td></tr>';

            FR_toggleBotonesAccion(false, 'borrador');
            window.FR_ID_ACTIVO = 0;

            modalFR.show();
            document.getElementById('modalFR').addEventListener('shown.bs.modal', function onShown() {
                document.getElementById('fr_cliente_search')?.focus();
                this.removeEventListener('shown.bs.modal', onShown);
            });

            setTimeout(() => { try { FR_calcTotales(); } catch (e) {} }, 100);
        } catch (e) { console.error(e); }
    }

    window.FR_abrirModalFR = async (row) => {
        try {
            if (!initModal()) return;

            const data = JSON.parse(row.dataset.row);
            const est = String(data.establecimiento || '000').padStart(3, '0');
            const pto = String(data.punto_emision || '000').padStart(3, '0');
            const sec = String(data.secuencial || '0').padStart(9, '0');
            const num = `${est}-${pto}-${sec}`;
            const cliente = data.cliente_nombre ? ` - ${data.cliente_nombre}` : '';

            document.getElementById('modalFRTitulo').innerHTML = `<i class="bi bi-arrow-repeat text-primary me-2"></i>Factura de Reembolso ${num}${cliente}`;
            setEl('fr_id', 'value', data.id);
            window.FR_ID_ACTIVO = data.id;

            document.getElementById('fr_fecha_emision').value = (data.fecha_emision || '').split(' ')[0].split('T')[0];
            document.getElementById('fr_id_punto_emision').value = data.id_punto_emision || '';
            document.getElementById('fr_secuencial').value = data.secuencial != null ? String(data.secuencial).padStart(9, '0') : '';
            document.getElementById('fr_id_cliente').value = data.id_cliente || '';
            document.getElementById('fr_cliente_search').value = data.cliente_nombre || '';

            const estado = data.estado || 'borrador';
            FR_toggleBotonesAccion(true, estado);
            FR_setModoLectura(estado !== 'borrador');

            try {
                const resp = await fetch(`${BASE_URL}/modulos/factura-reembolso/getFacturaReembolsoAjax?id=${data.id}`);
                const result = await resp.json();
                if (result.ok) {
                    const cab = result.cabecera || result;

                    // Repuebla la cabecera con los datos AUTORITATIVOS del servidor (cab),
                    // no solo el stub recibido en `data` (p. ej. tras guardar solo se pasa
                    // el id: si no se re-aplica aquí, serie/secuencial/cliente quedan vacíos).
                    document.getElementById('fr_fecha_emision').value = (cab.fecha_emision || data.fecha_emision || '').split(' ')[0].split('T')[0];
                    document.getElementById('fr_id_punto_emision').value = cab.id_punto_emision || data.id_punto_emision || '';
                    document.getElementById('fr_secuencial').value = cab.secuencial != null ? String(cab.secuencial).padStart(9, '0') : (data.secuencial != null ? String(data.secuencial).padStart(9, '0') : '');
                    document.getElementById('fr_id_cliente').value = cab.id_cliente || data.id_cliente || '';
                    document.getElementById('fr_cliente_search').value = cab.cliente_nombre || data.cliente_nombre || '';

                    const estFinal = String(cab.establecimiento || data.establecimiento || '000').padStart(3, '0');
                    const ptoFinal = String(cab.punto_emision || data.punto_emision || '000').padStart(3, '0');
                    const secFinal = String(cab.secuencial != null ? cab.secuencial : (data.secuencial || '0')).padStart(9, '0');
                    const numFinal = `${estFinal}-${ptoFinal}-${secFinal}`;
                    const clienteFinal = cab.cliente_nombre ? ` - ${cab.cliente_nombre}` : cliente;
                    document.getElementById('modalFRTitulo').innerHTML = `<i class="bi bi-arrow-repeat text-primary me-2"></i>Factura de Reembolso ${numFinal}${clienteFinal}`;

                    FR_renderDetalles(result.detalles || []);
                    terceros = (result.terceros || []).map(t => ({
                        id_compra: t.id_compra || null,
                        tipo_identificacion_proveedor_reembolso: t.tipo_identificacion_proveedor_reembolso,
                        identificacion_proveedor_reembolso: t.identificacion_proveedor_reembolso,
                        razon_social_proveedor_reembolso: t.razon_social_proveedor_reembolso || '',
                        cod_pais_pago_proveedor_reembolso: t.cod_pais_pago_proveedor_reembolso || '',
                        tipo_proveedor_reembolso: t.tipo_proveedor_reembolso,
                        cod_doc_reembolso: t.cod_doc_reembolso,
                        estab_doc_reembolso: t.estab_doc_reembolso,
                        pto_emi_doc_reembolso: t.pto_emi_doc_reembolso,
                        secuencial_doc_reembolso: t.secuencial_doc_reembolso,
                        fecha_emision_doc_reembolso: (t.fecha_emision_doc_reembolso || '').split(' ')[0].split('T')[0],
                        numero_autorizacion_doc_reemb: t.numero_autorizacion_doc_reemb,
                        impuestos: (t.impuestos || []).map(i => ({
                            codigo_impuesto: i.codigo_impuesto,
                            codigo_porcentaje: i.codigo_porcentaje,
                            tarifa: i.tarifa,
                            base_imponible: i.base_imponible,
                            valor: i.valor,
                        })),
                    }));
                    FR_renderTerceros();

                    FR_renderPagos(result.pagos || []);
                    setEl('fr-input-dias-credito', 'value', (result.pagos && result.pagos[0] && result.pagos[0].plazo) || '0');
                    setEl('fr-select-plazo-credito', 'value', (result.pagos && result.pagos[0] && result.pagos[0].unidad_tiempo) || 'dias');
                    FR_renderInfoAdicional(result.info_adicional || []);
                    FR_calcTotales();

                    setEl('fr_lbl_cliente_ruc', 'textContent', cab.cliente_ruc || '');
                    setEl('fr_lbl_cliente_direccion', 'textContent', cab.cliente_direccion || '');
                    setEl('fr_lbl_cliente_correo', 'textContent', cab.cliente_email || '');
                    const infoCli = document.getElementById('fr_info_cliente');
                    if (infoCli && cab.id_cliente) infoCli.classList.remove('d-none');

                    const elClaveAcceso = document.getElementById('fr-sri-clave-acceso');
                    const elAmbiente = document.getElementById('fr-sri-ambiente');
                    const elAutorizacion = document.getElementById('fr-sri-autorizacion');
                    const elFechaAut = document.getElementById('fr-sri-fecha-autorizacion');
                    const elBadge = document.getElementById('fr-sri-badge-estado');
                    const elNroDoc = document.getElementById('fr-sri-numero-documento');

                    if (elClaveAcceso) elClaveAcceso.value = cab.clave_acceso || '';
                    if (elAmbiente) {
                        const amb = String(cab.tipo_ambiente ?? '1');
                        elAmbiente.value = amb === '2' ? '2 - PRODUCCIÓN' : '1 - PRUEBAS';
                    }
                    if (elAutorizacion) elAutorizacion.value = cab.numero_autorizacion || cab.clave_acceso || '';
                    if (elFechaAut) elFechaAut.value = cab.fecha_autorizacion || '';
                    if (elNroDoc) elNroDoc.value = numFinal;

                    if (elBadge) {
                        const estadoMap = {
                            'autorizado': ['bg-success bg-opacity-10 text-success border-success', 'Autorizado'],
                            'anulado': ['bg-danger bg-opacity-10 text-danger border-danger', 'Anulado'],
                            'no_autorizado': ['bg-danger bg-opacity-10 text-danger border-danger', 'No autorizado'],
                        };
                        const [cls, lbl] = estadoMap[estado] ?? ['bg-secondary bg-opacity-10 text-secondary border-secondary', 'Sin enviar'];
                        elBadge.className = `badge ${cls} border border-opacity-25 px-2`;
                        elBadge.textContent = lbl;
                    }
                }
            } catch (e) {
                console.error('Error al cargar datos de la factura de reembolso:', e);
            }

            FR_cargarHistorialSri(data.id);
            modalFR.show();
        } catch (err) {
            console.error('Error al abrir modal de edición:', err);
            Swal.fire('Error', 'Ocurrió un error al cargar la factura de reembolso.', 'error');
        }
    };

    function FR_toggleBotonesAccion(habilitar, estado = 'borrador') {
        const esAutorizado = estado === 'autorizado';
        const esAnulado = estado === 'anulado';
        const esBorrador = estado === 'borrador';

        document.getElementById('fr-btn-sri').disabled = !habilitar || esAutorizado || esAnulado;
        document.getElementById('fr-btn-pdf').disabled = !habilitar;
        document.getElementById('fr-btn-xml').disabled = !habilitar;
        document.getElementById('fr-btn-correo').disabled = !habilitar || !esAutorizado;
        document.getElementById('btnGuardarFR').disabled = esAutorizado || esAnulado;

        const btnEliminar = document.getElementById('btnEliminarFR');
        const btnAnular = document.getElementById('btnAnularFR');
        if (btnEliminar) btnEliminar.classList.toggle('d-none', !habilitar || !esBorrador);
        if (btnAnular) btnAnular.classList.toggle('d-none', !habilitar || !esAutorizado);
    }

    function FR_setModoLectura(lock) {
        const modal = document.getElementById('modalFR');
        if (!modal) return;
        modal.classList.toggle('fr-lectura', !!lock);
        const campos = ['fr_fecha_emision', 'fr_id_punto_emision', 'fr_cliente_search'];
        campos.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            if (el.tagName === 'SELECT') el.disabled = !!lock;
            else el.readOnly = !!lock;
        });
        const btnGuardar = document.getElementById('btnGuardarFR');
        if (btnGuardar) btnGuardar.classList.toggle('d-none', !!lock);
    }

    // ─── SECUENCIAL ─────────────────────────────────────────────────────────

    window.FR_cargarSecuencial = async () => {
        const idActual = document.getElementById('fr_id').value;
        if (idActual) return;

        const idPt = document.getElementById('fr_id_punto_emision').value;
        const inputSec = document.getElementById('fr_secuencial');
        if (!idPt) {
            window.FR_SECUENCIAL_CONFIGURADO = false;
            if (inputSec) { inputSec.value = ''; inputSec.placeholder = 'Sin serie'; }
            return;
        }

        if (inputSec) inputSec.placeholder = 'Cargando...';
        try {
            const resp = await fetch(`${BASE_URL}/modulos/factura-reembolso/getSecuencialAjax?id_punto=${idPt}`);
            const data = await resp.json();
            if (data.ok) {
                inputSec.value = data.formateado || String(data.secuencial).padStart(9, '0');
                inputSec.placeholder = '000000001';
                window.FR_SECUENCIAL_CONFIGURADO = (data.configurado !== false);
                inputSec.classList.toggle('border-danger', data.configurado === false);
                if (data.configurado === false) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Secuenciales no configurados',
                        html: 'No están configurados los secuenciales para esta serie (tipo de documento "Facturas de reembolso").<br>Configúrelos en <strong>Empresa → Secuenciales</strong>.',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#f39c12',
                        target: document.getElementById('modalFR'),
                    });
                }
            } else {
                inputSec.value = '000000001';
                window.FR_SECUENCIAL_CONFIGURADO = false;
            }
        } catch (e) {
            if (inputSec) inputSec.value = '000000001';
            console.error('Error cargando secuencial de factura de reembolso:', e);
        }
    };

    // ─── CLIENTE (typeahead) ────────────────────────────────────────────────

    const searchCliente = document.getElementById('fr_cliente_search');
    const dropdownCliente = document.getElementById('fr_cliente_dropdown');

    if (searchCliente) {
        searchCliente.addEventListener('input', debounce(async () => {
            const term = searchCliente.value.trim();
            if (term.length < 2) { dropdownCliente.classList.add('d-none'); return; }

            const resp = await fetch(`${BASE_URL}/modulos/factura_venta/getClientesAjax?q=${encodeURIComponent(term)}`);
            const data = await resp.json();
            if (data.ok && data.data.length > 0) {
                dropdownCliente.innerHTML = data.data.map(c => `
                    <a href="#" class="list-group-item list-group-item-action py-2" onclick="window.FR_seleccionarCliente(${JSON.stringify(c).replace(/"/g, '&quot;')})">
                        <div class="fw-bold small">${c.nombre}</div>
                        <small class="text-muted" style="font-size:0.7rem;">${c.identificacion}</small>
                    </a>`).join('');
                dropdownCliente.classList.remove('d-none');
            } else {
                dropdownCliente.classList.add('d-none');
            }
        }, 300));

        document.addEventListener('click', (e) => {
            if (!dropdownCliente.contains(e.target) && e.target !== searchCliente) dropdownCliente.classList.add('d-none');
        });
    }

    window.FR_seleccionarCliente = (c) => {
        document.getElementById('fr_id_cliente').value = c.id;
        searchCliente.value = c.nombre;
        if (dropdownCliente) dropdownCliente.classList.add('d-none');

        setEl('fr_lbl_cliente_ruc', 'textContent', c.identificacion || '');
        setEl('fr_lbl_cliente_direccion', 'textContent', c.direccion || '');
        setEl('fr_lbl_cliente_correo', 'textContent', c.email || '');
        document.getElementById('fr_info_cliente')?.classList.remove('d-none');

        // Insertar o actualizar fila de correo en info adicional
        frActualizarInfoCorreoCliente(c.email || '');

        // Forma de pago por defecto. Precedencia: forma del CLIENTE → FAVORITO del usuario → default de EMPRESA.
        frAplicarFormaPagoPorDefecto(c.id_forma_pago_sri || null);
    };

    /**
     * Selecciona la forma de pago por defecto en la primera fila de pagos.
     * Precedencia: parámetro (forma del cliente) → FAVORITO del usuario → default de EMPRESA.
     * No pisa una selección que el usuario ya haya hecho manualmente.
     */
    function frAplicarFormaPagoPorDefecto(idFpCliente) {
        const favPagoSri = (typeof APP_FAVORITOS !== 'undefined'
            && APP_FAVORITOS['id_forma_pago_sri'] != null
            && APP_FAVORITOS['id_forma_pago_sri'] !== '')
            ? APP_FAVORITOS['id_forma_pago_sri'] : null;
        const idFpSri = idFpCliente || favPagoSri || window.FR_ID_FORMA_PAGO_SRI_DEF;
        if (!idFpSri) return;

        const selectPagoSri = document.querySelector('#fr_pagos_body select[name="fp_forma_pago[]"]');
        if (!selectPagoSri) return;

        const targetId = String(idFpSri);
        for (let i = 0; i < selectPagoSri.options.length; i++) {
            if (selectPagoSri.options[i].getAttribute('data-id') === targetId || selectPagoSri.options[i].value === targetId) {
                selectPagoSri.selectedIndex = i;
                break;
            }
        }
    }

    // ─── DETALLE (líneas libres) ────────────────────────────────────────────

    /** Opciones <option> de tarifa IVA para el select por línea (catálogo global). Selección por CÓDIGO (único), no por %, porque varias tarifas comparten 0% (código 6 "No objeto", 7 "Exento", 0 "0%"). */
    function frTarifaOptionsHtml(codigoSeleccionado) {
        return (window.FR_TARIFAS_IVA || []).map(t =>
            `<option value="${t.porcentaje}" data-codigo="${t.codigo}" ${String(t.codigo) === String(codigoSeleccionado) ? 'selected' : ''}>${t.label}</option>`
        ).join('');
    }

    /** Selecciona en un <select> de tarifa IVA la opción cuyo data-codigo coincide. */
    function frSeleccionarTarifaPorCodigo(selectEl, codigo) {
        if (!selectEl) return;
        for (let i = 0; i < selectEl.options.length; i++) {
            if (selectEl.options[i].dataset.codigo === String(codigo)) {
                selectEl.selectedIndex = i;
                return;
            }
        }
    }

    window.FR_agregarDetalle = (descripcion = '', tipo = 'gasto', cantidad = 1, precio = 0, tarifaCodigo = null) => {
        document.getElementById('fr-tr-detalle-vacio')?.remove();
        const tr = document.createElement('tr');
        tr.className = 'row-fr-detalle';
        const esGasto = tipo !== 'honorario';
        // Gasto siempre código 6 "No objeto de impuesto"; Honorario respeta el código guardado
        // o, si es una línea nueva, el primer código gravado (evita caer en 6/7/0 por defecto).
        const codigoSel = esGasto
            ? '6'
            : (tarifaCodigo != null ? tarifaCodigo : ((window.FR_TARIFAS_IVA || []).find(t => !['6', '7', '0'].includes(String(t.codigo)))?.codigo ?? '0'));
        tr.innerHTML = `
            <td class="ps-3"><input type="text" class="form-control form-control-sm border-0 bg-light" value="${(descripcion || '').replace(/"/g, '&quot;')}" oninput="window.FR_calcTotales()"></td>
            <td class="p-1">
                <select class="form-select form-select-sm fr-detalle-tipo" onchange="window.FR_onTipoChange(this)">
                    <option value="gasto" ${esGasto ? 'selected' : ''}>Gasto</option>
                    <option value="honorario" ${!esGasto ? 'selected' : ''}>Honorario</option>
                </select>
            </td>
            <td class="p-1">
                <select class="form-select form-select-sm fr-detalle-tarifa" ${esGasto ? 'disabled' : ''} onchange="window.FR_calcTotales()">
                    ${frTarifaOptionsHtml(codigoSel)}
                </select>
            </td>
            <td class="p-1"><input type="number" step="0.01" min="0.000001" class="form-control form-control-sm border-0 bg-light text-end" value="${cantidad}" oninput="window.FR_calcTotales()"></td>
            <td class="p-1"><input type="number" step="0.01" min="0" class="form-control form-control-sm border-0 bg-light text-end" value="${parseFloat(precio || 0).toFixed(2)}" oninput="window.FR_calcTotales()"></td>
            <td class="text-end pe-3 fw-bold fr-detalle-subtotal">0.00</td>
            <td class="text-center p-1">
                <button type="button" class="btn btn-link btn-sm p-0 text-primary shadow-none fr-detalle-btn-terceros ${esGasto ? '' : 'd-none'}" onclick="window.FR_abrirModalTerceros()" title="Terceros reembolsados">
                    <i class="bi bi-people-fill"></i>
                </button>
            </td>
            <td class="text-center p-1"><button type="button" class="btn btn-link btn-sm p-0 text-danger shadow-none" onclick="this.closest('tr').remove(); window.FR_calcTotales();"><i class="bi bi-x-circle-fill"></i></button></td>
        `;
        detalleBody.appendChild(tr);
        window.FR_calcTotales();
    };

    /** Al cambiar Gasto ↔ Honorario: refresca la tarifa IVA (Gasto = código 6 fijo), habilita/deshabilita el select y el botón de terceros de esa línea. */
    window.FR_onTipoChange = (sel) => {
        const tr = sel.closest('tr');
        const esGasto = sel.value !== 'honorario';
        const tarifaSel = tr?.querySelector('.fr-detalle-tarifa');
        if (tarifaSel) {
            if (esGasto) frSeleccionarTarifaPorCodigo(tarifaSel, '6');
            tarifaSel.disabled = esGasto;
        }
        tr?.querySelector('.fr-detalle-btn-terceros')?.classList.toggle('d-none', !esGasto);
        window.FR_calcTotales();
    };

    function FR_renderDetalles(detalles) {
        detalleBody.innerHTML = '';
        if (!detalles.length) {
            window.FR_agregarDetalle();
            return;
        }
        detalles.forEach(d => {
            const esGasto = d.es_reembolso !== false && d.es_reembolso !== 'f';
            const primerImpuesto = (d.impuestos || [])[0];
            window.FR_agregarDetalle(d.descripcion, esGasto ? 'gasto' : 'honorario', d.cantidad, d.precio_unitario, primerImpuesto ? primerImpuesto.codigo_porcentaje : null);
        });
    }

    // ─── TERCEROS REEMBOLSADOS (bloque SRI <reembolsos>) ────────────────────

    function FR_renderTerceros() {
        if (!tercerosBody) return;
        tercerosBody.querySelectorAll('tr:not(#fr-tr-terceros-vacio)').forEach(tr => tr.remove());
        const vacio = document.getElementById('fr-tr-terceros-vacio');

        if (terceros.length === 0) {
            vacio?.classList.remove('d-none');
        } else {
            vacio?.classList.add('d-none');
            terceros.forEach((t, idx) => {
                const baseTotal = (t.impuestos || []).reduce((s, i) => s + (parseFloat(i.base_imponible) || 0), 0);
                const impTotal = (t.impuestos || []).reduce((s, i) => s + (parseFloat(i.valor) || 0), 0);
                const doc = `${t.estab_doc_reembolso}-${t.pto_emi_doc_reembolso}-${t.secuencial_doc_reembolso}`;
                const nombre = (t.razon_social_proveedor_reembolso || t.identificacion_proveedor_reembolso || '').toString().replace(/</g, '&lt;');
                const tr = document.createElement('tr');
                tr.className = 'row-fr-tercero';
                tr.innerHTML = `
                    <td class="ps-2 py-1 small">${nombre}<div class="x-small text-muted">${t.identificacion_proveedor_reembolso || ''}</div></td>
                    <td class="py-1 small">${doc}<div class="x-small text-muted">Aut: ...${(t.numero_autorizacion_doc_reemb || '').slice(-10)}</div></td>
                    <td class="py-1 small text-end">${baseTotal.toFixed(2)}</td>
                    <td class="py-1 small text-end">${impTotal.toFixed(2)}</td>
                    <td class="py-1 text-center"><button type="button" class="btn btn-link btn-sm p-0 text-danger shadow-none" onclick="window.FR_quitarTercero(${idx})"><i class="bi bi-x-circle-fill"></i></button></td>
                `;
                tercerosBody.appendChild(tr);
            });
        }

        FR_actualizarBadgesTerceros();
        FR_calcTotales();
    }

    /** Refleja el conteo global de terceros en cada botón "Terceros" del detalle (líneas "Gasto"). */
    function FR_actualizarBadgesTerceros() {
        document.querySelectorAll('.fr-detalle-btn-terceros').forEach(btn => {
            let badge = btn.querySelector('.fr-badge-terceros-count');
            if (terceros.length > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'fr-badge-terceros-count badge bg-primary rounded-pill';
                    badge.style.cssText = 'font-size:0.55rem;position:absolute;transform:translate(2px,-8px);';
                    btn.style.position = 'relative';
                    btn.appendChild(badge);
                }
                badge.textContent = terceros.length;
            } else {
                badge?.remove();
            }
        });
    }

    window.FR_quitarTercero = (idx) => {
        terceros.splice(idx, 1);
        FR_renderTerceros();
    };

    window.FR_toggleTerceroManual = () => {
        document.getElementById('fr_form_tercero_manual')?.classList.toggle('d-none');
    };

    window.FR_calcularIvaTerceroManual = () => {
        const base = parseFloat(document.getElementById('fr_tercero_base').value) || 0;
        const sel = document.getElementById('fr_tercero_tarifa_iva');
        const pct = parseFloat(sel?.value) || 0;
        document.getElementById('fr_tercero_impuesto').value = r2(base * pct / 100).toFixed(2);
    };

    // Máscara para "Estab.-Pto.Emi.-Secuencial" del comprobante del tercero (000-000-000000000).
    const inpNumeroDocTercero = document.getElementById('fr_tercero_numero_doc');
    if (inpNumeroDocTercero) {
        inpNumeroDocTercero.addEventListener('input', function () {
            let val = this.value.replace(/\D/g, '');
            let formatted = '';
            if (val.length > 0) formatted += val.substring(0, 3);
            if (val.length > 3) formatted += '-' + val.substring(3, 6);
            if (val.length > 6) formatted += '-' + val.substring(6, 15);
            this.value = formatted;
        });
        inpNumeroDocTercero.addEventListener('blur', function () {
            const parts = this.value.split('-');
            if (parts.length > 0 && parts[0].length > 0) parts[0] = parts[0].padStart(3, '0');
            if (parts.length > 1 && parts[1].length > 0) parts[1] = parts[1].padStart(3, '0');
            if (parts.length > 2 && parts[2].length > 0) parts[2] = parts[2].padStart(9, '0');
            this.value = parts.join('-');
        });
    }

    // Consulta RUC/Cédula al SRI para autocompletar la razón social del tercero
    // (tipo 04=RUC 13 dígitos, 05=Cédula 10 dígitos; el resto no se consulta).
    let _frTerceroSriTimer;
    const LONGITUD_ID_SRI_TERCERO = { '04': 13, '05': 10 };
    const inpTerceroIdent = document.getElementById('fr_tercero_identificacion');
    if (inpTerceroIdent) {
        inpTerceroIdent.addEventListener('input', function () {
            clearTimeout(_frTerceroSriTimer);
            frLimpiarBadgeSriTercero();
            const valor = this.value.trim();
            const tipo = document.getElementById('fr_tercero_tipo_id')?.value || '';
            const longEsperada = LONGITUD_ID_SRI_TERCERO[tipo] || 0;
            if (longEsperada > 0 && valor.length === longEsperada) {
                _frTerceroSriTimer = setTimeout(() => frConsultarSriTercero(valor), 500);
            }
        });
    }

    function frLimpiarBadgeSriTercero() {
        const b = document.getElementById('fr_tercero_sriBadge');
        const sw = document.getElementById('fr_tercero_sriSpinnerWrap');
        if (b) { b.className = 'badge d-none'; b.textContent = ''; }
        if (sw) sw.style.display = 'none';
    }

    function frMostrarBadgeSriTercero(texto, cls) {
        const b = document.getElementById('fr_tercero_sriBadge');
        if (b) { b.textContent = texto; b.className = 'badge ' + cls; }
    }

    async function frConsultarSriTercero(identificacion) {
        const sw = document.getElementById('fr_tercero_sriSpinnerWrap');
        if (sw) sw.style.display = '';
        frMostrarBadgeSriTercero('Consultando...', 'bg-secondary');
        try {
            const fd = new FormData();
            fd.append('identificacion', identificacion);
            const resp = await fetch(`${BASE_URL}/modulos/factura-reembolso/consultarSriAjax`, { method: 'POST', body: fd });
            const data = await resp.json();
            if (sw) sw.style.display = 'none';
            if (data.ok && data.data) {
                frMostrarBadgeSriTercero('✓ SRI', 'bg-success');
                const razonSocial = document.getElementById('fr_tercero_razon_social');
                if (razonSocial) razonSocial.value = data.data.nombre || razonSocial.value;
            } else {
                frMostrarBadgeSriTercero(data.error || 'No encontrado', 'bg-warning text-dark');
            }
        } catch (e) {
            if (sw) sw.style.display = 'none';
            frMostrarBadgeSriTercero('Error', 'bg-danger');
        }
    }

    window.FR_confirmarTerceroManual = () => {
        const identificacion = document.getElementById('fr_tercero_identificacion').value.trim();
        const numeroDoc = document.getElementById('fr_tercero_numero_doc').value.trim();
        const partesNumDoc = numeroDoc.split('-');
        const estab = (partesNumDoc[0] || '').padStart(3, '0');
        const ptoEmi = (partesNumDoc[1] || '').padStart(3, '0');
        const secuencial = (partesNumDoc[2] || '').padStart(9, '0');
        const fecha = document.getElementById('fr_tercero_fecha').value;
        const autorizacion = document.getElementById('fr_tercero_autorizacion').value.trim();
        const base = r2(parseFloat(document.getElementById('fr_tercero_base').value) || 0);
        const selTarifa = document.getElementById('fr_tercero_tarifa_iva');
        const tarifaPct = parseFloat(selTarifa?.value) || 0;
        const codigoPorcentaje = selTarifa?.selectedOptions[0]?.dataset.codigo || '0';
        const impuesto = r2(base * tarifaPct / 100);

        if (!identificacion || !numeroDoc || !fecha || !autorizacion) {
            return Swal.fire({ icon: 'warning', title: 'Atención', text: 'Complete los datos del comprobante del proveedor (identificación, serie, fecha y autorización).' });
        }
        if (base <= 0) {
            return Swal.fire({ icon: 'warning', title: 'Atención', text: 'Ingrese la base imponible reembolsada.' });
        }

        terceros.push({
            id_compra: null,
            tipo_identificacion_proveedor_reembolso: document.getElementById('fr_tercero_tipo_id').value,
            identificacion_proveedor_reembolso: identificacion,
            razon_social_proveedor_reembolso: document.getElementById('fr_tercero_razon_social').value.trim(),
            cod_pais_pago_proveedor_reembolso: '',
            tipo_proveedor_reembolso: document.getElementById('fr_tercero_tipo_proveedor').value,
            cod_doc_reembolso: document.getElementById('fr_tercero_cod_doc').value,
            estab_doc_reembolso: estab,
            pto_emi_doc_reembolso: ptoEmi,
            secuencial_doc_reembolso: secuencial,
            fecha_emision_doc_reembolso: fecha,
            numero_autorizacion_doc_reemb: autorizacion,
            impuestos: [{ codigo_impuesto: '2', codigo_porcentaje: codigoPorcentaje, tarifa: tarifaPct, base_imponible: base.toFixed(2), valor: impuesto.toFixed(2) }],
        });

        ['fr_tercero_identificacion', 'fr_tercero_razon_social', 'fr_tercero_numero_doc', 'fr_tercero_fecha', 'fr_tercero_autorizacion'].forEach(id => setEl(id, 'value', ''));
        setEl('fr_tercero_base', 'value', '0.00');
        setEl('fr_tercero_impuesto', 'value', '0.00');
        frLimpiarBadgeSriTercero();
        window.FR_toggleTerceroManual();
        FR_renderTerceros();
    };

    function initTerceroSearch() {
        const input = document.getElementById('fr_search_tercero_compra');
        const dropdown = document.getElementById('fr_dropdown_tercero_compras');
        if (!input || !dropdown) return;

        input.addEventListener('input', debounce(async (e) => {
            const q = e.target.value.trim();
            if (q.length < 2) { dropdown.classList.add('d-none'); return; }
            try {
                const resp = await fetch(`${BASE_URL}/modulos/factura-reembolso/buscarComprasAjax?q=${encodeURIComponent(q)}`);
                const json = await resp.json();
                dropdown.innerHTML = '';
                if (json.data && json.data.length > 0) {
                    json.data.forEach(c => {
                        const doc = `${c.establecimiento_prov}-${c.punto_emision_prov}-${c.secuencial_prov}`;
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action py-2 border-start-0 border-end-0';
                        btn.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small text-primary">${c.proveedor_nombre}</span>
                                <span class="badge bg-light text-dark border x-small">${c.proveedor_identificacion}</span>
                            </div>
                            <div class="x-small text-muted">Doc. ${doc} · $${parseFloat(c.importe_total || 0).toFixed(2)} · ${c.fecha_emision}</div>`;
                        btn.onmousedown = (evt) => { evt.preventDefault(); FR_agregarTerceroDesdeCompra(c); };
                        dropdown.appendChild(btn);
                    });
                    dropdown.classList.remove('d-none');
                } else {
                    dropdown.innerHTML = '<div class="list-group-item small text-muted">No se encontraron compras registradas</div>';
                    dropdown.classList.remove('d-none');
                }
            } catch (err) {
                console.error('Error al buscar compras', err);
            }
        }, 300));

        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && e.target !== input) dropdown.classList.add('d-none');
        });
    }

    function FR_agregarTerceroDesdeCompra(c) {
        if (terceros.some(t => t.id_compra && parseInt(t.id_compra) === parseInt(c.id))) {
            Swal.fire({ icon: 'info', title: 'Ya agregado', text: 'Esta compra ya está en la lista de terceros.' });
            return;
        }
        const impuestos = (c.impuestos || []).map(i => ({
            codigo_impuesto: i.codigo_impuesto,
            codigo_porcentaje: i.codigo_porcentaje,
            tarifa: i.tarifa,
            base_imponible: parseFloat(i.base_imponible || 0).toFixed(2),
            valor: parseFloat(i.valor || 0).toFixed(2),
        }));
        terceros.push({
            id_compra: c.id,
            tipo_identificacion_proveedor_reembolso: c.tipo_id_proveedor || '04',
            identificacion_proveedor_reembolso: c.proveedor_identificacion,
            razon_social_proveedor_reembolso: c.proveedor_nombre,
            cod_pais_pago_proveedor_reembolso: '',
            tipo_proveedor_reembolso: '02',
            cod_doc_reembolso: c.tipo_comprobante || '01',
            estab_doc_reembolso: c.establecimiento_prov,
            pto_emi_doc_reembolso: c.punto_emision_prov,
            secuencial_doc_reembolso: c.secuencial_prov,
            fecha_emision_doc_reembolso: (c.fecha_emision || '').split(' ')[0].split('T')[0],
            numero_autorizacion_doc_reemb: c.numero_autorizacion || '',
            impuestos,
        });
        const input = document.getElementById('fr_search_tercero_compra');
        const dropdown = document.getElementById('fr_dropdown_tercero_compras');
        if (input) input.value = '';
        if (dropdown) dropdown.classList.add('d-none');
        FR_renderTerceros();
    }

    // ─── PAGOS ──────────────────────────────────────────────────────────────

    window.FR_agregarPago = (formaPago = '', total = '', plazo = 0, unidad = 'dias') => {
        const tmp = document.createElement('select');
        tmp.innerHTML = opcionesPagoHtml;
        const opciones = Array.from(tmp.options).map(o => `<option value="${o.value}" data-id="${o.getAttribute('data-id') || ''}" ${o.value === formaPago ? 'selected' : ''}>${o.text}</option>`).join('');
        const div = document.createElement('div');
        div.className = 'row g-2 align-items-center mb-1 row-fr-pago';
        div.innerHTML = `
            <div class="col-7">
                <select class="form-select form-select-sm border-0 bg-light" name="fp_forma_pago[]">${opciones}</select>
            </div>
            <div class="col-4">
                <input type="number" class="form-control form-control-sm text-end border-0 bg-light fw-bold" name="fp_total[]" step="0.01" value="${parseFloat(total || 0).toFixed(2)}">
            </div>
            <div class="col-1 text-center">
                <button type="button" class="btn btn-link btn-sm p-0 text-danger shadow-none" onclick="this.closest('.row-fr-pago').remove();" title="Eliminar">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>
            <input type="hidden" name="fp_plazo[]" value="${plazo}">
            <input type="hidden" name="fp_unidad[]" value="${unidad}">
        `;
        pagosBody.appendChild(div);
        div.querySelector('input[name="fp_total[]"]').focus();
    };

    function FR_renderPagos(pagos) {
        document.querySelectorAll('#fr_pagos_body .row-fr-pago').forEach(tr => tr.remove());
        if (!pagos.length) {
            window.FR_agregarPago();
            return;
        }
        pagos.forEach(p => window.FR_agregarPago(p.forma_pago, p.total, p.plazo || 0, p.unidad_tiempo || 'dias'));
    }

    function FR_capturarPagos() {
        // Crédito: días + plazo (unidad) son un solo par para todo el documento (igual
        // que Factura de Venta, pestaña "Crédito"), se aplica a cada forma de pago. En 0
        // el XML omite <plazo>/<unidadTiempo> (son opcionales) — XmlFacturaReembolsoService
        // ya lo maneja así.
        const diasCred = parseInt(document.getElementById('fr-input-dias-credito')?.value) || 0;
        const unidadCred = document.getElementById('fr-select-plazo-credito')?.value || 'dias';
        const pagos = [];
        pagosBody.querySelectorAll('.row-fr-pago').forEach(tr => {
            const forma = tr.querySelector('select[name="fp_forma_pago[]"]').value;
            const total = parseFloat(tr.querySelector('input[name="fp_total[]"]').value) || 0;
            if (forma && total > 0) {
                pagos.push({
                    forma_pago: forma,
                    total: total.toFixed(2),
                    plazo: diasCred,
                    unidad_tiempo: unidadCred,
                });
            }
        });
        return pagos;
    }

    // ─── INFO ADICIONAL ─────────────────────────────────────────────────────

    window.FR_agregarInfoAdicional = (concepto = '', detalle = '') => {
        const tbody = document.getElementById('fr-tbody-info-adicional');
        const tr = document.createElement('tr');
        tr.className = 'row-fr-info-adicional';
        tr.innerHTML = `
            <td class="p-0 align-middle"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-fr-info-concepto" style="padding:1px 4px;height:22px;line-height:1.2;font-size:0.78rem;" placeholder="Concepto..." value="${(concepto || '').replace(/"/g, '&quot;')}"></td>
            <td class="p-0 align-middle"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-fr-info-detalle" style="padding:1px 4px;height:22px;line-height:1.2;font-size:0.78rem;" placeholder="Detalle..." value="${(detalle || '').replace(/"/g, '&quot;')}"></td>
            <td class="p-0 align-middle text-center pe-1"><button type="button" class="btn btn-link btn-sm p-0 m-0 text-danger shadow-none" onclick="this.closest('tr').remove();"><i class="bi bi-x-circle-fill"></i></button></td>
        `;
        tbody.appendChild(tr);
        tr.querySelector('.input-fr-info-concepto').focus();
    };

    function FR_limpiarInfoAdicional() {
        const tbody = document.getElementById('fr-tbody-info-adicional');
        if (tbody) tbody.innerHTML = '';
    }

    /**
     * Inserta o actualiza la fila fija de correo del cliente en info adicional
     * (igual que Factura de Venta). Sin botón de eliminar, se identifica con
     * data-tipo="correo-cliente". Si el cliente no tiene correo, quita la fila.
     */
    function frActualizarInfoCorreoCliente(email) {
        const tbody = document.getElementById('fr-tbody-info-adicional');
        if (!tbody) return;
        let filaCorreo = tbody.querySelector('tr[data-tipo="correo-cliente"]');

        if (!email) {
            if (filaCorreo) filaCorreo.remove();
            return;
        }

        if (filaCorreo) {
            filaCorreo.querySelector('.input-fr-info-detalle').value = email;
        } else {
            const tr = document.createElement('tr');
            tr.className = 'row-fr-info-adicional';
            tr.dataset.tipo = 'correo-cliente';
            tr.innerHTML = `
                <td class="p-0 align-middle"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-fr-info-concepto" style="padding:1px 4px;height:22px;line-height:1.2;font-size:0.78rem;" value="Correo del cliente" readonly></td>
                <td class="p-0 align-middle"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-fr-info-detalle" style="padding:1px 4px;height:22px;line-height:1.2;font-size:0.78rem;" value="${email}"></td>
                <td class="p-0 align-middle text-center pe-1"><span class="text-muted small" title="Se actualiza al cambiar el cliente"><i class="bi bi-lock-fill"></i></span></td>
            `;
            tbody.appendChild(tr);
        }
    }

    function FR_renderInfoAdicional(items) {
        FR_limpiarInfoAdicional();
        // Filas fijas que NO se repintan como línea libre editable:
        // - "Correo del cliente": se reconstruye aparte (ver abajo) para poder
        //   actualizarla/quitarla al cambiar de cliente sin duplicarla.
        // - "RUC Proveedor" (Res. NAC-DGERCGC26-00000027): ya se muestra siempre en la
        //   fila de vista previa fija (#fr-tbody-ruc-proveedor-preview); el Service la
        //   vuelve a insertar como dato real al guardar, así que al recargar el documento
        //   aparecía duplicada (vista previa + línea libre real).
        const NOMBRES_FIJOS = ['correo del cliente', 'ruc proveedor'];
        (items || []).forEach(i => {
            if (NOMBRES_FIJOS.includes((i.nombre || '').toLowerCase().trim())) return;
            window.FR_agregarInfoAdicional(i.nombre || '', i.valor || '');
        });
        const correoGuardado = (items || []).find(i => (i.nombre || '').toLowerCase().trim() === 'correo del cliente');
        if (correoGuardado) frActualizarInfoCorreoCliente(correoGuardado.valor || '');
    }

    function FR_capturarInfoAdicional() {
        const info = [];
        document.querySelectorAll('#fr-tbody-info-adicional tr.row-fr-info-adicional').forEach(tr => {
            const inputs = tr.querySelectorAll('input');
            const nombre = inputs[0]?.value.trim();
            const valor = inputs[1]?.value.trim();
            if (nombre && valor) info.push({ nombre, valor });
        });
        return info;
    }

    // ─── TOTALES ────────────────────────────────────────────────────────────

    window.FR_calcTotales = () => {
        let subtotal = 0;
        let valorIva = 0;
        const nombresIva = new Set();

        detalleBody?.querySelectorAll('tr.row-fr-detalle').forEach(tr => {
            const cant = parseFloat(tr.children[3].querySelector('input').value) || 0;
            const precio = parseFloat(tr.children[4].querySelector('input').value) || 0;
            const sub = r2(cant * precio);
            tr.querySelector('.fr-detalle-subtotal').textContent = sub.toFixed(2);
            subtotal += sub;

            // Solo las líneas "Honorario" llevan IVA normal (tarifa propia de la línea);
            // las "Gasto" van con código 6 "No objeto de impuesto" (tarifa 0).
            const tipoSel = tr.querySelector('.fr-detalle-tipo');
            const esGasto = tipoSel ? tipoSel.value !== 'honorario' : true;
            if (!esGasto) {
                const tarifaSel = tr.querySelector('.fr-detalle-tarifa');
                const opt = tarifaSel ? tarifaSel.options[tarifaSel.selectedIndex] : null;
                const pct = opt ? parseFloat(opt.value) || 0 : 0;
                valorIva += r2(sub * pct / 100);
                if (opt) nombresIva.add(opt.text);
            }
        });
        valorIva = r2(valorIva);
        const total = r2(subtotal + valorIva);
        const nombreIva = nombresIva.size === 1 ? [...nombresIva][0] : 'IVA';

        setEl('fr_lbl_subtotal', 'textContent', subtotal.toFixed(2));
        setEl('fr_lbl_iva_nombre', 'textContent', nombreIva);
        setEl('fr_lbl_iva', 'textContent', valorIva.toFixed(2));
        setEl('fr_lbl_total', 'textContent', total.toFixed(2));
        setEl('fr_total_sin_impuestos', 'value', subtotal.toFixed(2));
        setEl('fr_importe_total', 'value', total.toFixed(2));

        // Auto-completar Formas de Pago si solo hay una fila (igual que Factura de Venta/Compras).
        const pagosTotal = document.querySelectorAll('#fr_pagos_body input[name="fp_total[]"]');
        if (pagosTotal.length === 1) {
            pagosTotal[0].value = total.toFixed(2);
        }

        const totalReembolsado = terceros.reduce((s, t) => s + (t.impuestos || []).reduce((s2, i) => s2 + (parseFloat(i.base_imponible) || 0) + (parseFloat(i.valor) || 0), 0), 0);
        setEl('fr_lbl_total_reembolsado', 'textContent', totalReembolsado.toFixed(2));
        FR_actualizarBadgesTerceros();
    };

    function FR_capturarDetalles() {
        const detalles = [];
        detalleBody?.querySelectorAll('tr.row-fr-detalle').forEach(tr => {
            const descripcion = tr.children[0].querySelector('input').value.trim();
            const tipoSel = tr.querySelector('.fr-detalle-tipo');
            const esGasto = tipoSel ? tipoSel.value !== 'honorario' : true;
            const cantidad = parseFloat(tr.children[3].querySelector('input').value) || 0;
            const precio = parseFloat(tr.children[4].querySelector('input').value) || 0;
            const subtotal = r2(cantidad * precio);
            if (!descripcion || cantidad <= 0) return;

            const impuestos = [];
            if (esGasto) {
                // Código 6: No objeto de impuesto (gasto reembolsado, sin IVA propio).
                impuestos.push({ codigo_impuesto: '2', codigo_porcentaje: '6', tarifa: 0, base_imponible: subtotal.toFixed(2), valor: '0.00' });
            } else {
                const tarifaSel = tr.querySelector('.fr-detalle-tarifa');
                const opt = tarifaSel ? tarifaSel.options[tarifaSel.selectedIndex] : null;
                const pct = opt ? parseFloat(opt.value) || 0 : 0;
                const cod = opt?.dataset.codigo || '0';
                impuestos.push({ codigo_impuesto: '2', codigo_porcentaje: cod, tarifa: pct, base_imponible: subtotal.toFixed(2), valor: r2(subtotal * pct / 100).toFixed(2) });
            }

            detalles.push({
                descripcion,
                cantidad: cantidad.toFixed(6),
                precio_unitario: precio.toFixed(6),
                descuento: '0.00',
                precio_total_sin_impuesto: subtotal.toFixed(2),
                es_reembolso: esGasto,
                impuestos,
            });
        });
        return detalles;
    }

    // ─── GUARDAR / ELIMINAR / ANULAR ────────────────────────────────────────

    function FR_focusYError(el, mensaje, subTabBtnId) {
        try {
            const tabBtn = document.getElementById('tab-fr-principal-btn');
            if (tabBtn && typeof bootstrap !== 'undefined') bootstrap.Tab.getOrCreateInstance(tabBtn).show();
            if (subTabBtnId) {
                const subTabBtn = document.getElementById(subTabBtnId);
                if (subTabBtn && typeof bootstrap !== 'undefined') bootstrap.Tab.getOrCreateInstance(subTabBtn).show();
            }
        } catch (e) {}
        Swal.fire('Falta completar', mensaje, 'warning').then(() => el?.focus());
    }

    function FR_validarObligatorios() {
        const idCliente = document.getElementById('fr_id_cliente').value;
        if (!idCliente) { FR_focusYError(document.getElementById('fr_cliente_search'), 'Debe seleccionar el cliente.'); return false; }

        const serie = document.getElementById('fr_id_punto_emision');
        if (!serie || !serie.value) { FR_focusYError(serie, 'Debe seleccionar la serie (punto de emisión).'); return false; }

        if (!detalleBody || detalleBody.querySelectorAll('tr.row-fr-detalle').length === 0) {
            FR_focusYError(null, 'Debe agregar al menos un ítem en el detalle.');
            return false;
        }

        const totalFacturaVal = parseFloat(document.getElementById('fr_importe_total').value) || 0;
        if (totalFacturaVal <= 0) {
            FR_focusYError(null, 'El total de la factura es $0.00. Revise que las líneas del detalle tengan cantidad y precio unitario.');
            return false;
        }

        // Formas de pago: mismo criterio que el servidor (FacturaReembolsoRules::validarPagos),
        // pero validado aquí primero para dar un error claro antes de intentar guardar.
        let pagoSinForma = null;
        let algunPago = false;
        let sumPagos = 0;
        pagosBody?.querySelectorAll('.row-fr-pago').forEach(tr => {
            const sel = tr.querySelector('select[name="fp_forma_pago[]"]');
            const val = parseFloat(tr.querySelector('input[name="fp_total[]"]').value) || 0;
            if (val <= 0) return;
            if (!sel.value) { pagoSinForma = pagoSinForma || sel; return; }
            algunPago = true;
            sumPagos = r2(sumPagos + val);
        });
        if (pagoSinForma) {
            FR_focusYError(pagoSinForma, 'Seleccione la forma de pago para cada monto ingresado.', 'fr-subtab-pagos-btn');
            return false;
        }
        if (!algunPago) {
            const primeraFilaSelect = document.querySelector('#fr_pagos_body select[name="fp_forma_pago[]"]');
            FR_focusYError(primeraFilaSelect, 'Debe especificar al menos una forma de pago con un monto mayor a cero.', 'fr-subtab-pagos-btn');
            return false;
        }
        if (Math.abs(sumPagos - totalFacturaVal) > 0.001) {
            Swal.fire({
                icon: 'warning', title: 'Descuadre en Pagos',
                text: `La suma de formas de pago ($${sumPagos.toFixed(2)}) no coincide con el total de la factura ($${totalFacturaVal.toFixed(2)}).`,
            }).then(() => {
                try {
                    const tabBtn = document.getElementById('fr-subtab-pagos-btn');
                    if (tabBtn && typeof bootstrap !== 'undefined') bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                } catch (e) {}
            });
            return false;
        }

        if (terceros.length === 0) {
            Swal.fire({
                icon: 'warning', title: 'Faltan terceros reembolsados',
                text: 'Debe agregar al menos un tercero reembolsado (botón "Terceros" en una línea "Gasto" del detalle) — es el sustento de esta factura ante el SRI.',
            }).then(() => window.FR_abrirModalTerceros());
            return false;
        }
        return true;
    }

    window.FR_guardar = async () => {
        const esNueva = !document.getElementById('fr_id').value;
        if (esNueva && window.FR_SECUENCIAL_CONFIGURADO === false) {
            return Swal.fire({
                icon: 'warning',
                title: 'Secuenciales no configurados',
                html: 'No están configurados los secuenciales para esta serie (tipo de documento "Facturas de reembolso").<br>Configúrelos en <strong>Empresa → Secuenciales</strong>.',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#f39c12',
                target: document.getElementById('modalFR'),
            });
        }

        FR_calcTotales();
        if (!FR_validarObligatorios()) return;

        const totalBaseReembolso = terceros.reduce((s, t) => s + (t.impuestos || []).reduce((s2, i) => s2 + (parseFloat(i.base_imponible) || 0), 0), 0);
        const totalImpuestoReembolso = terceros.reduce((s, t) => s + (t.impuestos || []).reduce((s2, i) => s2 + (parseFloat(i.valor) || 0), 0), 0);

        const payload = {
            id: document.getElementById('fr_id').value,
            id_punto_emision: document.getElementById('fr_id_punto_emision').value,
            secuencial: document.getElementById('fr_secuencial').value,
            fecha_emision: document.getElementById('fr_fecha_emision').value,
            id_cliente: document.getElementById('fr_id_cliente').value,
            total_sin_impuestos: document.getElementById('fr_total_sin_impuestos').value,
            total_descuento: '0.00',
            importe_total: document.getElementById('fr_importe_total').value,
            propina: '0.00',
            moneda: 'DOLAR',
            total_base_imponible_reembolso: totalBaseReembolso.toFixed(2),
            total_impuesto_reembolso: totalImpuestoReembolso.toFixed(2),
            total_comprobantes_reembolso: (totalBaseReembolso + totalImpuestoReembolso).toFixed(2),
            detalles: FR_capturarDetalles(),
            terceros: terceros,
            pagos: FR_capturarPagos(),
            info_adicional: FR_capturarInfoAdicional(),
        };

        const btn = document.getElementById('btnGuardarFR');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

        try {
            const resp = await fetch(`${BASE_URL}/modulos/factura-reembolso/guardarAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `data=${encodeURIComponent(JSON.stringify(payload))}`,
            });
            const data = await resp.json();
            if (data.ok) {
                window.FR_fetchSearch();
                const idGuardado = parseInt(data.id) || 0;
                if (idGuardado > 0) {
                    window.FR_abrirModalFR({ dataset: { row: JSON.stringify({ id: idGuardado }) } });
                }
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.mensaje || 'Guardado', showConfirmButton: false, timer: 2500, timerProgressBar: true });
            } else {
                Swal.fire('Error', data.mensaje, 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
        }
    };

    window.FR_eliminar = async () => {
        const id = document.getElementById('fr_id').value;
        if (!id) return;
        const confirm = await Swal.fire({ icon: 'warning', title: '¿Eliminar?', text: 'Esta acción no se puede deshacer.', showCancelButton: true, confirmButtonText: 'Sí, eliminar', confirmButtonColor: '#dc3545' });
        if (!confirm.isConfirmed) return;

        try {
            const resp = await fetch(`${BASE_URL}/modulos/factura-reembolso/eliminarAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${encodeURIComponent(id)}`,
            });
            const data = await resp.json();
            if (data.ok) {
                modalFR.hide();
                window.FR_fetchSearch();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.mensaje, showConfirmButton: false, timer: 2000 });
            } else {
                Swal.fire('Error', data.mensaje, 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
        }
    };

    window.FR_anular = async () => {
        const id = document.getElementById('fr_id').value;
        if (!id) return;
        const confirm = await Swal.fire({ icon: 'warning', title: '¿Anular esta factura de reembolso?', text: 'Se informará la anulación al SRI si corresponde.', showCancelButton: true, confirmButtonText: 'Sí, anular', confirmButtonColor: '#dc3545' });
        if (!confirm.isConfirmed) return;

        try {
            const resp = await fetch(`${BASE_URL}/modulos/factura-reembolso/anularAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${encodeURIComponent(id)}`,
            });
            const data = await resp.json();
            if (data.ok) {
                window.FR_fetchSearch();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.mensaje, showConfirmButton: false, timer: 2000 });
                window.FR_abrirModalFR({ dataset: { row: JSON.stringify({ id: parseInt(id) }) } });
            } else {
                Swal.fire('Error', data.mensaje, 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
        }
    };

    // ─── SRI ─────────────────────────────────────────────────────────────────

    window.FR_enviarSRI = async () => {
        const id = document.getElementById('fr_id').value;
        if (!id) return;

        const confirm = await Swal.fire({
            title: 'Enviar al SRI',
            text: '¿Está seguro de enviar este comprobante al SRI para su autorización?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, enviar',
            cancelButtonText: 'Cancelar',
        });
        if (!confirm.isConfirmed) return;

        Swal.fire({
            title: 'Enviando al SRI...',
            html: '<div class="spinner-border text-primary" role="status"></div><br><small class="text-muted mt-2 d-block">Firmando y enviando comprobante…</small>',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
        });

        try {
            const resp = await fetch(`${BASE_URL}/modulos/factura-reembolso/autorizarSRIAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}`,
            });
            const data = await resp.json();

            FR_cargarHistorialSri(id);

            if (data.ok) {
                FR_toggleBotonesAccion(true, 'autorizado');
                FR_setModoLectura(true);
                setEl('fr-sri-autorizacion', 'value', data.numero_autorizacion || '');
                setEl('fr-sri-fecha-autorizacion', 'value', data.fecha_autorizacion || '');
                const badge = document.getElementById('fr-sri-badge-estado');
                if (badge) {
                    badge.className = 'badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2';
                    badge.textContent = 'Autorizado';
                }
                window.FR_fetchSearch();
                Swal.fire('Éxito', 'Comprobante autorizado correctamente.', 'success');
            } else {
                try {
                    const tabBtn = document.getElementById('tab-fr-sri-btn');
                    if (tabBtn && typeof bootstrap !== 'undefined') bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                } catch (e) {}

                const esc = (s) => String(s ?? '').replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
                let html = `<div class="text-start small">${esc(data.mensaje || 'Error desconocido')}</div>`;
                if (data.errores && data.errores.length > 0) {
                    html += '<ul class="text-start small mt-2 mb-0 ps-3">';
                    data.errores.forEach(e => {
                        if (typeof e === 'string') { html += `<li>${esc(e)}</li>`; return; }
                        const idErr = e.id ? `[${esc(e.id)}] ` : '';
                        const mens = esc(e.mensaje || '');
                        const info = e.info ? `<br><em class="text-muted">${esc(e.info)}</em>` : '';
                        html += `<li>${idErr}${mens}${info}</li>`;
                    });
                    html += '</ul>';
                }
                html += '<div class="text-muted small mt-2">El detalle queda registrado en la pestaña <strong>SRI</strong>.</div>';
                Swal.fire({ icon: 'error', title: 'El SRI rechazó el comprobante', html });
            }
        } catch (e) {
            console.error('Error SRI:', e);
            Swal.fire('Error', 'Error de comunicación con el servidor.', 'error');
        }
    };

    async function FR_cargarHistorialSri(id) {
        const tbody = document.getElementById('fr-sri-tbody-historial');
        if (!tbody || !id) return;

        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-2 text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</td></tr>';

        try {
            const resp = await fetch(`${BASE_URL}/modulos/factura-reembolso/getHistorialSriAjax?id=${id}`);
            const json = await resp.json();

            if (!json.ok || !json.data || !json.data.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted small">Sin historial de envíos.</td></tr>';
                return;
            }

            const accionMap = {
                'enviando': ['bg-primary', 'Enviando'],
                'recibida': ['bg-info', 'Recibida'],
                'devuelta': ['bg-danger', 'Devuelta'],
                'autorizado': ['bg-success', 'Autorizado'],
                'no_autorizado': ['bg-danger', 'No autorizado'],
                'en_procesamiento': ['bg-warning', 'En proceso'],
                'error': ['bg-danger', 'Error'],
            };

            tbody.innerHTML = json.data.map(row => {
                const [bgCls, lbl] = accionMap[row.accion] || ['bg-secondary', row.accion];
                const esPruebas = row.tipo_ambiente === '1';
                const ambienteLbl = esPruebas
                    ? '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" style="font-size:0.65rem;">PRUEBAS</span>'
                    : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:0.65rem;">PRODUCCIÓN</span>';
                let detalle = row.mensaje || '';
                if (row.numero_autorizacion && row.accion === 'autorizado') {
                    detalle += `<div class="font-monospace mt-1" style="font-size:0.65rem;word-break:break-all;">${row.numero_autorizacion}</div>`;
                }
                return `<tr>
                    <td class="ps-2 py-1 text-nowrap" style="font-size:0.72rem;">${row.created_at}</td>
                    <td class="py-1">${ambienteLbl}</td>
                    <td class="py-1"><span class="badge ${bgCls} bg-opacity-10 border border-opacity-25" style="font-size:0.68rem;">${lbl}</span></td>
                    <td class="py-1" style="font-size:0.72rem;">${detalle}</td>
                </tr>`;
            }).join('');
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted small">Error al cargar el historial.</td></tr>';
        }
    }

    // ─── PDF / Correo ────────────────────────────────────────────────────────

    window.FR_exportarPdf = () => {
        const id = document.getElementById('fr_id').value;
        if (id) window.open(`${BASE_URL}/modulos/factura-reembolso/exportPdfDoc?id=${id}`, '_blank');
    };

    window.FR_exportarXml = () => {
        const id = document.getElementById('fr_id').value;
        if (!id) return;
        window.location.href = `${BASE_URL}/modulos/factura-reembolso/exportXmlDoc?id=${id}`;
    };

    window.FR_enviarPorCorreo = async () => {
        const id = document.getElementById('fr_id').value;
        if (!id) return;

        const modalEl = document.getElementById('modalFR');
        const correoActual = (document.getElementById('fr_lbl_cliente_correo')?.textContent || '').trim();

        const { value: correos, isConfirmed } = await Swal.fire({
            title: 'Enviar por correo',
            input: 'text',
            inputLabel: 'Correos electrónicos (separados por coma)',
            inputValue: correoActual,
            target: modalEl,
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-send me-1"></i> Enviar',
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => {
                if (!value.trim()) return 'Debes ingresar al menos un correo válido.';
            },
        });
        if (!isConfirmed) return;

        Swal.fire({ title: 'Enviando correo...', text: 'Por favor espera', allowOutsideClick: false, target: modalEl, didOpen: () => Swal.showLoading() });

        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('correos', correos);

            const resp = await fetch(`${BASE_URL}/modulos/factura-reembolso/enviarCorreoAjax`, { method: 'POST', body: fd });
            const data = await resp.json();

            if (data.ok) {
                Swal.fire({ icon: 'success', title: '¡Enviado!', text: data.mensaje, timer: 2500, showConfirmButton: false, target: modalEl });
                window.FR_fetchSearch();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje || 'No se pudo enviar el correo.', target: modalEl });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión al enviar el correo.', target: modalEl });
        }
    };

    window.FR_copiarCampoSri = (inputId) => {
        const input = document.getElementById(inputId);
        const val = input ? input.value.trim() : '';
        if (!val) return;
        navigator.clipboard.writeText(val).catch(() => { input.select(); document.execCommand('copy'); });
    };
})();

// ─── Pestaña Asiento Contable (vista previa reutilizable) ───────────────────
(function () {
    let _frAsientoTab = null;
    function frAsientoTab() {
        if (!_frAsientoTab && typeof window.crearAsientoTab === 'function') {
            _frAsientoTab = window.crearAsientoTab({
                tbodyId: 'fr-asiento-tbody',
                debeId: 'fr-asiento-debe',
                haberId: 'fr-asiento-haber',
                difId: 'fr-asiento-dif',
                badgeId: 'fr-asiento-badge',
                countId: 'fr-asiento-count',
                statusId: 'fr-asiento-status',
                previewUrl: `${BASE_URL}/modulos/factura-reembolso/getAsientoSugeridoAjax`,
                cuentasUrl: `${BASE_URL}/modulos/plan-cuentas/searchAjaxCuentas`,
            });
            const addBtn = document.getElementById('fr-asiento-add');
            if (addBtn) addBtn.addEventListener('click', () => _frAsientoTab.agregarLinea());
        }
        return _frAsientoTab;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const btnTab = document.getElementById('tab-fr-contable-btn');
        if (btnTab) {
            btnTab.addEventListener('shown.bs.tab', function () {
                const tab = frAsientoTab();
                const idEl = document.getElementById('fr_id');
                if (tab) tab.cargar(idEl ? idEl.value : 0);
            });
        }
    });
})();
