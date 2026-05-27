(function() {
    'use strict';

    // Priorizar B_URL y R_MODULO inyectados desde PHP
    const B_BASE = (typeof B_URL !== 'undefined') ? B_URL : (window.location.origin + '/sistema/public');
    const API_URL = (typeof R_MODULO !== 'undefined') ? (B_BASE + '/' + R_MODULO) : (B_BASE + '/modulos/liquidacion-compra');

    let detalles = [];
    let pagos = [];
    let infoAdicional = [];
    let liquidacionActual = null;
    let sriDebounceTimer = null;
    const LC_STORAGE_KEY = `lc_borrador_${typeof ID_EMPRESA !== 'undefined' ? ID_EMPRESA : 0}_${typeof ID_USUARIO !== 'undefined' ? ID_USUARIO : 0}`;
    let _egresoDepsCargados = false;
    let _egresoDeps = null;


    const r2 = v => Math.round(v * 100) / 100;
    const DEC_PRECIO = 2; // Default, se podría traer de config si se requiere

    // --- Funciones Globales ---
    window.abrirModalLiquidacion = abrirModalLiquidacionFn;
    window.abrirModalLiquidacionVer = abrirModalLiquidacionVerFn;
    window.LC_fetchSearch = fetchSearchFn;
    window.LC_agregarFila = agregarFilaFn;
    window.LC_removerFila = removerFilaFn;
    window.seleccionarProveedor = seleccionarProveedorFn;
    window.LC_guardar = guardarFn;
    window.LC_syncSecuencial = syncSecuencialFn;
    window.LC_agregarPago = agregarPagoFn;
    window.LC_agregarInfoAdicional = agregarInfoAdicionalFn;
    window.liqCopiarCampoSri = function(inputId) {
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
    window.liqCopiarClaveAcceso = function() { window.liqCopiarCampoSri('liq-sri-clave-acceso'); };

    document.addEventListener('DOMContentLoaded', () => {
        initSortableHeaders();
        initProviderSearch();

        // Escuchar cuando se guarda un proveedor desde el modal compartido
        document.addEventListener('proveedorGuardado', (e) => {
            const res = e.detail;
            if (res.ok && res.data) {
                // Seleccionar automáticamente al nuevo proveedor
                seleccionarProveedorFn({
                    id: res.id,
                    nombre: res.nombre || res.data.razon_social || res.data.nombre,
                    identificacion: res.data.identificacion,
                    email: res.data.email
                });
            }
        });
        
        // Registrar auto-guardado
        lcRegistrarAutoGuardado();

        // Listeners para pestañas de Pagos y Retenciones
        const tabPagos = document.getElementById('tab-liq-pagos-btn');
        if (tabPagos) {
            tabPagos.addEventListener('shown.bs.tab', () => {
                window.LC_cargarPagosTab();
            });
        }
        const tabRet = document.getElementById('tab-liq-retenciones-btn');
        if (tabRet) {
            tabRet.addEventListener('shown.bs.tab', () => {
                window.LC_cargarRetencionesCompra();
            });
        }
    });


    function initSortableHeaders() {
        document.querySelectorAll('.sortable-header').forEach(th => {
            th.addEventListener('click', () => {
                const sort = th.dataset.sort;
                const urlParams = new URLSearchParams(window.location.search);
                const currentSort = urlParams.get('sort') || 'fecha_emision';
                const currentDir = urlParams.get('dir') || 'DESC';
                let dir = (sort === currentSort && currentDir === 'ASC') ? 'DESC' : 'ASC';
                fetchSearchFn(1, sort, dir);
            });
        });
    }

    function fetchSearchFn(page = 1, sort = '', dir = '') {
        const buscarEl = document.getElementById('buscar');
        const buscar = buscarEl ? buscarEl.value : '';
        const url = new URL(API_URL + '/searchAjax', window.location.origin);
        url.searchParams.append('page', page);
        url.searchParams.append('b', buscar);
        if (sort) url.searchParams.append('sort', sort);
        if (dir) url.searchParams.append('dir', dir);

        fetch(url)
            .then(r => r.json())
            .then(res => {
                const tbody = document.getElementById('tbodyLiquidaciones');
                const pInfo = document.getElementById('paginationInfo');
                if (res.ok && tbody) {
                    tbody.innerHTML = res.rows;
                    if (pInfo) pInfo.innerText = res.info;
                    updatePaginationUI(page, res.totalPages);
                }
            })
            .catch(err => console.error("Error LC_fetchSearch:", err));
    }

    function updatePaginationUI(currentPage, totalPages) {
        const container = document.getElementById('paginationContainer');
        if (!container) return;
        container.innerHTML = `
            <button type="button" class="btn btn-outline-secondary" ${currentPage <= 1 ? 'disabled' : ''} onclick="window.LC_fetchSearch(${currentPage - 1})"><i class="bi bi-chevron-left"></i></button>
            <button type="button" class="btn btn-outline-secondary" ${currentPage >= totalPages ? 'disabled' : ''} onclick="window.LC_fetchSearch(${currentPage + 1})"><i class="bi bi-chevron-right"></i></button>
        `;
    }

    // --- Modal Logic ---
    function lcResetearYMostrar() {
        liquidacionActual = null;
        detalles = [];
        const defaultPago = document.getElementById('liq-pago-fav') ? document.getElementById('liq-pago-fav').value : '01';
        pagos = [{ id: Date.now(), id_forma_pago: defaultPago, total: 0 }];
        infoAdicional = [];

        document.getElementById('formLiquidacion').reset();
        document.getElementById('liq-id').value = '';
        document.getElementById('liq-id-proveedor').value = '';
        document.getElementById('search-proveedor').value = '';
        document.getElementById('tituloModalLiq').textContent = 'Nueva Liquidación de Compras y Servicios';
        liqResetSri();

        document.getElementById('tbodyDetalles').innerHTML = '';
        
        // Tab Default
        const tabBtn = document.getElementById('tab-liq-compra-btn');
        if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();

        const pt = document.getElementById('liq-punto');
        if (pt && pt.value) syncSecuencialFn(pt.value);

        agregarFilaFn();
        renderPagos();
        renderInfoAdicional();
        LC_calcTotales();

        if (typeof window.aplicarFavoritosModal === 'function') {
            window.aplicarFavoritosModal('#modalLiquidacion');
        }

        const modalEl = document.getElementById('modalLiquidacion');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function abrirModalLiquidacionFn() {
        const modalEl = document.getElementById('modalLiquidacion');
        
        // Verificar si hay un borrador guardado
        let borrador = null;
        try {
            const raw = localStorage.getItem(LC_STORAGE_KEY);
            if (raw) borrador = JSON.parse(raw);
        } catch (e) {}

        if (borrador && (borrador.id_proveedor || borrador.detalles.length > 0)) {
            const div = document.createElement('div');
            div.id = 'lc-borrador-aviso';
            div.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;';
            div.innerHTML = `
                <div class="bg-white rounded-3 shadow-lg p-4" style="max-width:420px;width:90%;">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-4"></i>
                        <h6 class="fw-bold mb-0">Liquidación sin guardar</h6>
                    </div>
                    <p class="small text-muted mb-4">Hay una liquidación en borrador del proveedor <strong>${borrador.search_proveedor || 'desconocido'}</strong> que no fue guardada. ¿Qué desea hacer?</p>
                    <div class="d-flex gap-2 justify-content-end">
                        <button class="btn btn-sm btn-outline-secondary" id="lc-aviso-nueva">
                            <i class="bi bi-file-earmark-plus me-1"></i> Nueva liquidación
                        </button>
                        <button class="btn btn-sm btn-primary" id="lc-aviso-restaurar">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Cargar borrador
                        </button>
                    </div>
                </div>`;
            document.body.appendChild(div);

            document.getElementById('lc-aviso-restaurar').onclick = () => {
                div.remove();
                lcResetearYMostrar(); // Inicializa pero luego sobreescribimos con el restaurar
                setTimeout(() => lcRestaurar(borrador), 100);
            };

            document.getElementById('lc-aviso-nueva').onclick = () => {
                lcLimpiarBorrador();
                div.remove();
                lcResetearYMostrar();
            };
            return;
        }

        lcResetearYMostrar();
    }



    function abrirModalLiquidacionVerFn(row) {
        const data = JSON.parse(row.dataset.row);
        fetch(`${API_URL}/getLiquidacionAjax?id=${data.id}`)
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    liquidacionActual = res.cabecera;
                    detalles = res.detalles.map(d => ({
                        ...d,
                        id_temp: Date.now() + Math.random(),
                        id_tarifa_iva: d.id_tarifa_iva || 0,
                        adicional: d.info_adicional || '',
                        total: (parseFloat(d.cantidad) * parseFloat(d.precio_unitario)) - parseFloat(d.descuento)
                    }));
                    pagos = res.pagos;
                    infoAdicional = res.info_adicional;

                    poblarFormulario(res.cabecera);
                    liqCargarHistorialSri(res.cabecera.id);
                    renderDetalles();
                    renderPagos();
                    renderInfoAdicional();
                    LC_calcTotales();

                    const seqCompleto = `${res.cabecera.establecimiento || '001'}-${res.cabecera.punto_emision || '001'}-${res.cabecera.secuencial || ''}`;
                    document.getElementById('tituloModalLiq').textContent = 'Liquidación de compras y servicios ' + seqCompleto;

                    window.LC_ID_ACTIVO = res.cabecera.id;

                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalLiquidacion')).show();
                }
            });
    }

    function poblarFormulario(cab) {
        document.getElementById('liq-id').value = cab.id;
        document.getElementById('liq-fecha').value = cab.fecha_emision;
        document.getElementById('liq-punto').value = cab.id_punto_emision;
        document.getElementById('liq-secuencial').value = cab.secuencial;
        document.getElementById('liq-id-proveedor').value = cab.id_proveedor;
        document.getElementById('search-proveedor').value = cab.proveedor_nombre || '';
        document.getElementById('liq-sustento').value = cab.id_sustento_tributario || '';

        // Poblar datos SRI
        liqPoblarSri(cab);
    }

    function liqResetSri() {
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v || ''; };
        set('liq-sri-clave-acceso', '');
        set('liq-sri-ambiente', '');
        set('liq-sri-tipo-emision', '');
        set('liq-sri-numero-autorizacion', '');
        set('liq-sri-fecha-autorizacion', '');
        set('liq-sri-numero-documento', '');
        set('liq-sri-identificacion-proveedor', '');
        set('liq-sri-correo-proveedor', '');
        window.LC_FECHA_EMISION   = null;
        window.LC_PROVEEDOR_RUC   = '';
        const badge = document.getElementById('liq-sri-badge-estado');
        if (badge) { badge.textContent = 'Sin enviar'; badge.className = 'badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2'; }
        const msgs = document.getElementById('liq-sri-mensajes-container');
        if (msgs) msgs.innerHTML = '<p class="text-muted text-center mb-0 py-3 small">Sin respuesta del SRI registrada.</p>';
        const hist = document.getElementById('liq-sri-tbody-historial');
        if (hist) hist.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Sin historial de envíos.</td></tr>';
        const btnAnular = document.getElementById('btnAnularLiq');
        if (btnAnular) btnAnular.classList.add('d-none');
    }

    function liqPoblarSri(cab) {
        liqResetSri();
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v || ''; };
        set('liq-sri-clave-acceso', cab.clave_acceso || '');

        const elAmb     = document.getElementById('liq-sri-ambiente');
        const elEmision = document.getElementById('liq-sri-tipo-emision');
        if (elAmb)     { const esPruebas = String(cab.tipo_ambiente) === '1'; elAmb.value = cab.tipo_ambiente ? (esPruebas ? '1 - PRUEBAS' : '2 - PRODUCCIÓN') : '—'; }
        if (elEmision) { elEmision.value = String(cab.tipo_emision) === '1' ? '1 - NORMAL' : (cab.tipo_emision || '—'); }

        // Número de documento
        const elNroDoc = document.getElementById('liq-sri-numero-documento');
        if (elNroDoc) {
            const est = (cab.establecimiento  || '').toString().padStart(3, '0');
            const pto = (cab.punto_emision    || '').toString().padStart(3, '0');
            const sec = (cab.secuencial       || '').toString().padStart(9, '0');
            elNroDoc.value = `${est}-${pto}-${sec}`;
        }

        // Identificación y correo del proveedor
        set('liq-sri-identificacion-proveedor', cab.proveedor_ruc   || '');
        set('liq-sri-correo-proveedor',         cab.proveedor_email || '');

        // Guardar globales para validaciones de anulación
        window.LC_FECHA_EMISION = (cab.fecha_emision || '').split(' ')[0].split('T')[0] || null;
        window.LC_PROVEEDOR_RUC = (cab.proveedor_ruc || '').trim();

        // Mostrar botón anular si el estado lo permite
        const btnAnular = document.getElementById('btnAnularLiq');
        if (btnAnular) {
            const estado = (cab.estado || '').toLowerCase();
            if (estado !== 'anulado' && estado !== 'borrador') {
                btnAnular.classList.remove('d-none');
            } else {
                btnAnular.classList.add('d-none');
            }
        }

        // Delegar el estado, la fecha formateada y los mensajes a la función unificada
        liqActualizarPestanhaSri({
            estado_sri: cab.estado,
            numero_autorizacion: cab.numero_autorizacion || cab.clave_acceso,
            fecha_autorizacion: cab.fecha_autorizacion,
            mensajes_sri: cab.mensajes_sri
        });
    }

    function ucFirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }

    // --- Details Grid ---
    function agregarFilaFn(item = null) {
        // Validación: No permitir agregar si la última fila no tiene descripción
        if (!item) {
            const lastRow = document.querySelector('#tbodyDetalles .row-detalle:last-child');
            if (lastRow) {
                const lastDesc = lastRow.querySelector('.input-descripcion').value.trim();
                if (!lastDesc) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Atención',
                            text: 'Debe completar la descripción del ítem actual antes de agregar uno nuevo.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                    lastRow.querySelector('.input-descripcion').focus();
                    return;
                }
            }
        }

        const tbody = document.getElementById('tbodyDetalles');
        const tr = document.createElement('tr');
        tr.className = 'row-detalle';
        
        // Determinar valores iniciales
        const id_producto = item ? (item.id_producto || '') : '';
        const codigo = item ? (item.codigo_principal || item.codigo || '') : '';
        const descripcion = item ? (item.descripcion || '') : '';
        const adicional = item ? (item.info_adicional || item.adicional || '') : '';
        const cantidad = item ? (item.cantidad || 1) : 1;
        const p_unitario = item ? parseFloat(item.precio_unitario || 0) : 0;
        const descuento = item ? parseFloat(item.descuento || 0) : 0;
        const id_tarifa = item ? (item.id_tarifa_iva || item.tarifa_iva || 2) : 2; 
        const total = item ? parseFloat(item.total || 0) : 0;

        tr.innerHTML = `
            <td>
                <input type="text" class="input-detalle input-codigo" placeholder="Código" value="${codigo}">
                <input type="hidden" class="input-id-producto" value="${id_producto}">
            </td>
            <td><input type="text" class="input-detalle input-descripcion" placeholder="Descripción" value="${descripcion}"></td>
            <td><input type="text" class="input-detalle input-adicional" placeholder="Info extra..." value="${adicional}"></td>
            <td><input type="number" class="input-detalle text-center input-cantidad" value="${cantidad}" step="any" oninput="window.LC_calcFila(this)"></td>
            <td><input type="number" class="input-detalle text-end input-precio" value="${p_unitario.toFixed(DEC_PRECIO)}" step="any" oninput="window.LC_calcSinImp(this)"></td>
            <td><input type="number" class="input-detalle text-end input-desc" value="${descuento.toFixed(2)}" step="0.01" oninput="window.LC_calcFila(this)"></td>
            <td>
                <select class="form-select form-select-sm border-0 bg-transparent py-0 input-iva" style="font-size:0.8rem" onchange="window.LC_syncPrecioIva(this)">
                    ${(window.TARIFAS_IVA || []).map(t => {
                        const pct = parseFloat(t.porcentaje_iva || t.porcentaje || 0);
                        const label = t.nombre || t.descripcion || t.tarifa || ('IVA ' + pct + '%');
                        return `<option value="${pct}" data-id="${t.id}" ${parseInt(id_tarifa) === parseInt(t.id) ? 'selected' : ''}>${label}</option>`;
                    }).join('')}
                </select>
            </td>
            <td class="text-end fw-bold pt-2 pe-4">$<span class="subtotal-line">${total.toFixed(2)}</span></td>
            <td class="text-center pt-1">
                <button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="window.LC_removerFila(this)" title="Eliminar ítem">
                    <i class="bi bi-trash3 fs-6"></i>
                </button>
            </td>
        `;

        tbody.appendChild(tr);
        
        // Manejo de navegación con Enter
        const inputCant = tr.querySelector('.input-cantidad');
        const inputPrec = tr.querySelector('.input-precio');
        const inputDesc = tr.querySelector('.input-descripcion');
        const inputAdic = tr.querySelector('.input-adicional');
        const inputCod = tr.querySelector('.input-codigo');

        inputDesc.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); inputAdic.focus(); inputAdic.select(); }
        });
        inputAdic.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); inputCant.focus(); inputCant.select(); }
        });
        inputCant.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); inputPrec.focus(); inputPrec.select(); }
        });
        inputPrec.addEventListener('keydown', e => {
            if (e.key === 'Enter') { 
                e.preventDefault(); 
                // Al presionar enter en precio, si es la última fila, agregar una nueva
                const isLast = tr === tbody.lastElementChild;
                if (isLast) agregarFilaFn();
                else {
                    const nextRow = tr.nextElementSibling;
                    if (nextRow) nextRow.querySelector('.input-descripcion').focus();
                }
            }
        });
        
        // inputCod.addEventListener('focus', function() { initProductSearchFn(this, 'codigo'); });
        // inputDesc.addEventListener('focus', function() { initProductSearchFn(this, 'nombre'); });

        actualizarContadorItems();
        if (!item) {
            inputDesc.focus();
            LC_calcTotales();
        }
    }

    function removerFilaFn(btn) {
        btn.closest('tr').remove();
        actualizarContadorItems();
        LC_calcTotales();
    }

    function renderDetalles() {
        const tbody = document.getElementById('tbodyDetalles');
        tbody.innerHTML = '';
        detalles.forEach(d => agregarFilaFn(d));
    }

    function actualizarContadorItems() {
        const count = document.querySelectorAll('#tbodyDetalles .row-detalle').length;
        const el = document.getElementById('liq-count-items');
        if (el) el.textContent = count;
    }

    window.LC_syncPrecioIva = function(el) {
        window.LC_calcFila(el);
    };

    window.LC_calcSinImp = function(el) {
        window.LC_calcFila(el);
    };

    window.LC_calcFila = function(el) {
        const tr = el.closest('tr');
        const cant = parseFloat(tr.querySelector('.input-cantidad').value) || 0;
        const prec = parseFloat(tr.querySelector('.input-precio').value) || 0;
        const desc = parseFloat(tr.querySelector('.input-desc').value) || 0;

        const subtotalBruto = r2(cant * prec);
        const subtotalNeto = r2(subtotalBruto - desc);

        tr.querySelector('.subtotal-line').textContent = subtotalNeto.toFixed(2);
        LC_calcTotales();
    };

    // --- Totals Calculation ---
    function LC_calcTotales() {
        let subtotalGeneralBruto = 0; 
        let descuentoTotal = 0;
        const grupos = {};

        document.querySelectorAll('#tbodyDetalles .row-detalle').forEach(tr => {
            const cant = parseFloat(tr.querySelector('.input-cantidad').value) || 0;
            const prec = parseFloat(tr.querySelector('.input-precio').value) || 0;
            const desc = parseFloat(tr.querySelector('.input-desc').value) || 0;
            const ivaSelect = tr.querySelector('.input-iva');
            const ivaPct = parseFloat(ivaSelect.value) || 0;
            const idTarifa = ivaSelect.options[ivaSelect.selectedIndex].dataset.id;
            const key = ivaPct.toFixed(2);

            const bruto = r2(cant * prec);
            const neto = r2(bruto - desc);

            subtotalGeneralBruto = r2(subtotalGeneralBruto + bruto);
            descuentoTotal = r2(descuentoTotal + desc);

            if (!grupos[key]) {
                const optText = ivaSelect.options[ivaSelect.selectedIndex].text;
                grupos[key] = {
                    pct: ivaPct,
                    base: 0,
                    iva: 0,
                    nombre: optText
                };
            }
            grupos[key].base = r2(grupos[key].base + neto);
            grupos[key].iva = r2(grupos[key].iva + r2(neto * ivaPct / 100));
        });

        const ivaTotal = Object.values(grupos).reduce((acc, g) => r2(acc + g.iva), 0);
        const totalFinal = r2(subtotalGeneralBruto - descuentoTotal + ivaTotal);

        document.getElementById('liq-lbl-subtotal').innerText = subtotalGeneralBruto.toFixed(2);
        document.getElementById('liq-lbl-descuento').innerText = descuentoTotal.toFixed(2);
        document.getElementById('liq-lbl-total').innerText = totalFinal.toFixed(2);

        // Render bases grouping
        const subTotalesDiv = document.getElementById('liq-lbl-subtotales-iva');
        if (subTotalesDiv) {
            subTotalesDiv.innerHTML = '';
            Object.values(grupos).forEach(g => {
                subTotalesDiv.innerHTML += `
                    <div class="d-flex justify-content-between align-items-center mb-1 text-muted">
                        <span class="small">Subtotal ${g.pct}%</span>
                        <span class="fw-bold">${g.base.toFixed(2)}</span>
                    </div>
                `;
            });
        }

        // Render iva grouping
        const ivasDiv = document.getElementById('liq-lbl-ivas-grupo');
        if (ivasDiv) {
            ivasDiv.innerHTML = '';
            Object.values(grupos).forEach(g => {
                if (g.iva > 0) {
                    ivasDiv.innerHTML += `
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small">(+) ${g.nombre}</span>
                            <span class="fw-bold">${g.iva.toFixed(2)}</span>
                        </div>
                    `;
                }
            });
        }

        // Sync first payment if exists
        const inputPago = document.querySelector('#container-pagos input');
        if (inputPago && document.querySelectorAll('#container-pagos .row').length === 1) {
            inputPago.value = totalFinal.toFixed(2);
        }
    }

    // =====================================================================
    // LOCAL STORAGE — Auto-guardado de borrador
    // =====================================================================

    function lcCapturarEstado() {
        const estado = {
            id_proveedor: document.getElementById('liq-id-proveedor')?.value || '',
            search_proveedor: document.getElementById('search-proveedor')?.value || '',
            id_sustento_tributario: document.getElementById('liq-sustento')?.value || '',
            fecha_emision: document.getElementById('liq-fecha')?.value || '',
            id_punto_emision: document.getElementById('liq-punto')?.value || '',
            detalles: [],
            pagos: [],
            info_adicional: []
        };

        // Detalles
        document.querySelectorAll('#tbodyDetalles .row-detalle').forEach(tr => {
            const desc = tr.querySelector('.input-descripcion').value.trim();
            if (!desc) return;

            estado.detalles.push({
                codigo: tr.querySelector('.input-codigo').value,
                id_producto: tr.querySelector('.input-id-producto').value,
                descripcion: desc,
                adicional: tr.querySelector('.input-adicional').value,
                cantidad: tr.querySelector('.input-cantidad').value,
                precio_unitario: tr.querySelector('.input-precio').value,
                descuento: tr.querySelector('.input-desc').value,
                id_tarifa_iva: tr.querySelector('.input-iva').options[tr.querySelector('.input-iva').selectedIndex].dataset.id
            });
        });

        // Pagos
        document.querySelectorAll('#container-pagos .row-pago').forEach(div => {
            estado.pagos.push({
                id_forma_pago: div.querySelector('.input-pago-id').value,
                total: div.querySelector('.input-pago-total').value
            });
        });

        // Info adicional
        document.querySelectorAll('#container-info-adicional .row-info-extra').forEach(tr => {
            const nombre = tr.querySelector('.input-info-nombre').value;
            const valor = tr.querySelector('.input-info-valor').value;
            if (nombre && valor) {
                estado.info_adicional.push({ nombre, valor, fija: tr.dataset.tipo === 'email' });
            }
        });

        return estado;
    }

    function lcAutoGuardar() {
        const idLiq = document.getElementById('liq-id').value;
        if (idLiq) return; // No auto-guardar si estamos editando una existente

        try {
            const estado = lcCapturarEstado();
            if (!estado.id_proveedor && estado.detalles.length === 0) {
                localStorage.removeItem(LC_STORAGE_KEY);
                return;
            }
            localStorage.setItem(LC_STORAGE_KEY, JSON.stringify(estado));
        } catch (e) {}
    }

    function lcLimpiarBorrador() {
        try {
            localStorage.removeItem(LC_STORAGE_KEY);
        } catch (e) {}
    }

    function lcRestaurar(estado) {
        // Resetear y poblar
        document.getElementById('formLiquidacion').reset();
        document.getElementById('liq-id').value = '';
        document.getElementById('liq-id-proveedor').value = estado.id_proveedor || '';
        document.getElementById('search-proveedor').value = estado.search_proveedor || '';
        document.getElementById('liq-sustento').value = estado.id_sustento_tributario || '';
        document.getElementById('liq-fecha').value = estado.fecha_emision || '';
        document.getElementById('liq-punto').value = estado.id_punto_emision || '';

        // Detalles
        const tbody = document.getElementById('tbodyDetalles');
        tbody.innerHTML = '';
        if (estado.detalles && estado.detalles.length > 0) {
            estado.detalles.forEach(d => agregarFilaFn(d));
        } else {
            agregarFilaFn();
        }

        // Pagos
        const containerPagos = document.getElementById('container-pagos');
        containerPagos.innerHTML = '';
        if (estado.pagos && estado.pagos.length > 0) {
            estado.pagos.forEach((p, idx) => {
                agregarPagoFn();
                const rows = containerPagos.querySelectorAll('.row-pago');
                const row = rows[rows.length - 1];
                if (row) {
                    row.querySelector('.input-pago-id').value = p.id_forma_pago;
                    row.querySelector('.input-pago-total').value = p.total;
                }
            });
        } else {
            agregarPagoFn();
        }

        // Info adicional
        const containerInfo = document.getElementById('container-info-adicional');
        containerInfo.innerHTML = '';
        if (estado.info_adicional && estado.info_adicional.length > 0) {
            estado.info_adicional.forEach(ia => {
                agregarInfoAdicionalFn(ia.nombre, ia.valor, ia.fija);
            });
        }

        LC_calcTotales();
    }

    function lcRegistrarAutoGuardado() {
        const form = document.getElementById('formLiquidacion');
        if (!form) return;
        const debouncedGuardar = debounce(lcAutoGuardar, 800);
        form.addEventListener('input', debouncedGuardar);
        form.addEventListener('change', debouncedGuardar);
    }

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }


    // --- Search Logic ---
    function initProviderSearch() {
        const input = document.getElementById('search-proveedor');
        const dropdown = document.getElementById('dropdown-proveedores');
        if (!input || !dropdown) return;

        input.addEventListener('input', () => {
            const q = input.value;
            if (q.length < 2) { dropdown.classList.add('d-none'); return; }

            fetch(`${API_URL}/getProveedoresAjax?q=${q}`)
                .then(r => r.json())
                .then(res => {
                    if (res.ok && res.data.length > 0) {
                        dropdown.innerHTML = res.data.map(p => {
                            const pJson = JSON.stringify(p).replace(/'/g, "&#39;").replace(/"/g, "&quot;");
                            return `
                                <button type="button" class="list-group-item list-group-item-action py-2" onclick='window.seleccionarProveedor(${pJson})'>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold x-small">${p.nombre}</span>
                                        <span class="badge bg-light text-dark border x-small">${p.identificacion}</span>
                                    </div>
                                </button>
                            `;
                        }).join('');
                        dropdown.classList.remove('d-none');
                    } else dropdown.classList.add('d-none');
                });
        });

        document.addEventListener('click', e => {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.add('d-none');
        });
    }

    function seleccionarProveedorFn(p) {
        if (!p) return;
        document.getElementById('liq-id-proveedor').value = p.id;
        document.getElementById('search-proveedor').value = p.nombre;
        document.getElementById('dropdown-proveedores').classList.add('d-none');

        // Automatización del correo
        const container = document.getElementById('container-info-adicional');
        if (container) {
            // Eliminar fila de correo previa si existe
            const prev = container.querySelector('tr[data-tipo="email"]');
            if (prev) prev.remove();

            if (p.email) {
                agregarInfoAdicionalFn('correo del proveedor', p.email, true);
            }
        }

        // Poner el cursor en la descripción de la primera fila
        const firstDesc = document.querySelector('#tbodyDetalles .input-descripcion');
        if (firstDesc) {
            firstDesc.focus();
        }
    }

    function syncSecuencialFn(idPunto) {
        if (!idPunto) return;
        fetch(`${API_URL}/getSecuencialAjax?id_punto_emision=${idPunto}`)
            .then(r => r.json())
            .then(res => {
                if (res.ok) document.getElementById('liq-secuencial').value = res.formateado;
            });
    }

    // --- Auxiliary Renders ---
    function agregarPagoFn() {
        const id = Date.now();
        const defaultPago = document.getElementById('liq-pago-fav') ? document.getElementById('liq-pago-fav').value : '01';
        const container = document.getElementById('container-pagos');
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 align-items-center row-pago';
        div.innerHTML = `
            <div class="col-8">
                <select class="form-select form-select-sm input-pago-id">
                    ${(window.FORMAS_PAGO_SRI || []).map(f => `<option value="${f.codigo}" ${f.codigo == defaultPago ? 'selected' : ''}>${f.nombre}</option>`).join('')}
                </select>
            </div>
            <div class="col-3">
                <input type="number" class="form-control form-control-sm text-end input-pago-total" value="0.00" step="0.01">
            </div>
            <div class="col-1"><i class="bi bi-trash text-danger" role="button" onclick="this.closest('.row-pago').remove()"></i></div>
        `;
        container.appendChild(div);
    }

    function renderPagos() {
        const container = document.getElementById('container-pagos');
        container.innerHTML = '';
        if (pagos.length === 0) {
            agregarPagoFn();
        } else {
            pagos.forEach((p, index) => {
                const isFirst = index === 0;
                const div = document.createElement('div');
                div.className = 'row g-2 mb-2 align-items-center row-pago';
                div.innerHTML = `
                    <div class="col-8">
                        <div class="d-flex align-items-center gap-1">
                            ${isFirst ? (window.STAR_PAGO_HTML || '') : ''}
                            <select class="form-select form-select-sm input-pago-id" ${isFirst ? 'id="liq-pago-fav"' : ''}>
                                ${(window.FORMAS_PAGO_SRI || []).map(f => `<option value="${f.codigo}" ${p.id_forma_pago == f.codigo ? 'selected' : ''}>${f.nombre}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                    <div class="col-3">
                        <input type="number" class="form-control form-control-sm text-end input-pago-total" value="${parseFloat(p.total || 0).toFixed(2)}" step="0.01">
                    </div>
                    <div class="col-1"><i class="bi bi-trash text-danger" role="button" onclick="this.closest('.row-pago').remove()"></i></div>
                `;
                container.appendChild(div);
            });
        }
    }

    function agregarInfoAdicionalFn(nombre = '', valor = '', fija = false) {
        const container = document.getElementById('container-info-adicional');
        const tr = document.createElement('tr');
        tr.className = 'row-info-extra border-bottom';
        if (fija) tr.dataset.tipo = 'email';

        tr.innerHTML = `
            <td class="p-0">
                <input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-nombre" 
                    placeholder="Concepto" value="${nombre}" ${fija ? 'readonly' : ''} style="font-size:0.8rem">
            </td>
            <td class="p-0">
                <input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-valor" 
                    placeholder="Detalle" value="${valor}" style="font-size:0.8rem">
            </td>
            <td class="p-1 text-center">
                ${fija ? 
                    '<i class="bi bi-lock-fill text-muted opacity-50" title="Campo obligatorio"></i>' : 
                    '<i class="bi bi-x-circle-fill text-danger" role="button" onclick="this.closest(\'tr\').remove()"></i>'
                }
            </td>
        `;
        
        if (fija) container.prepend(tr);
        else container.appendChild(tr);
        
        if (!nombre && !fija) tr.querySelector('.input-info-nombre').focus();
    }

    function renderInfoAdicional() {
        const container = document.getElementById('container-info-adicional');
        container.innerHTML = '';
        infoAdicional.forEach(info => {
            const isEmail = info.nombre.toLowerCase().includes('correo');
            agregarInfoAdicionalFn(info.nombre, info.valor, isEmail);
        });
    }

    // --- Save Logic ---
    function guardarFn() {
        const btn = document.getElementById('btnGuardarLiq');
        
        // Recolectar detalles del DOM
        const detallesDom = [];
        let errorDesc = false;
        document.querySelectorAll('#tbodyDetalles .row-detalle').forEach(tr => {
            const ivaSelect = tr.querySelector('.input-iva');
            const desc = tr.querySelector('.input-descripcion').value.trim();
            
            if (!desc) errorDesc = true;

            detallesDom.push({
                id_producto: tr.querySelector('.input-id-producto').value,
                codigo: tr.querySelector('.input-codigo').value,
                descripcion: desc,
                adicional: tr.querySelector('.input-adicional').value,
                cantidad: tr.querySelector('.input-cantidad').value,
                precio_unitario: tr.querySelector('.input-precio').value,
                descuento: tr.querySelector('.input-desc').value,
                id_tarifa_iva: ivaSelect.options[ivaSelect.selectedIndex].dataset.id,
                total: tr.querySelector('.subtotal-line').textContent
            });
        });

        if (errorDesc) {
            if (typeof Swal !== 'undefined') Swal.fire('Atención', 'Todos los ítems deben tener una descripción', 'warning');
            else alert("Todos los ítems deben tener una descripción");
            return;
        }

        // Recolectar pagos
        const pagosDom = [];
        document.querySelectorAll('#container-pagos .row-pago').forEach(div => {
            pagosDom.push({
                id_forma_pago: div.querySelector('.input-pago-id').value,
                total: div.querySelector('.input-pago-total').value
            });
        });

        // Recolectar info adicional
        const infoDom = [];
        document.querySelectorAll('#container-info-adicional .row-info-extra').forEach(div => {
            const nombre = div.querySelector('.input-info-nombre').value;
            const valor = div.querySelector('.input-info-valor').value;
            if (nombre && valor) infoDom.push({ nombre, valor });
        });

        const puntoSelect = document.getElementById('liq-punto');
        const puntoOpt = puntoSelect.options[puntoSelect.selectedIndex];

        const data = {
            id: document.getElementById('liq-id').value,
            id_proveedor: document.getElementById('liq-id-proveedor').value,
            id_sustento_tributario: document.getElementById('liq-sustento').value,
            fecha_emision: document.getElementById('liq-fecha').value,
            id_punto_emision: puntoSelect.value,
            id_establecimiento: puntoOpt.dataset.idEst,
            establecimiento: puntoOpt.dataset.est,
            punto_emision: puntoOpt.dataset.punto,
            secuencial: document.getElementById('liq-secuencial').value,
            total_sin_impuestos: document.getElementById('liq-lbl-subtotal').innerText,
            total_descuento: document.getElementById('liq-lbl-descuento').innerText,
            importe_total: document.getElementById('liq-lbl-total').innerText,
            detalles: detallesDom,
            pagos: pagosDom,
            info_adicional: infoDom
        };

        if (!data.id_proveedor) { 
            if (typeof Swal !== 'undefined') Swal.fire('Atención', 'Debe seleccionar un proveedor', 'warning');
            else alert("Debe seleccionar un proveedor"); 
            return; 
        }
        if (detallesDom.length === 0) { 
            if (typeof Swal !== 'undefined') Swal.fire('Atención', 'Debe agregar al menos un ítem', 'warning');
            else alert("Debe agregar al menos un ítem"); 
            return; 
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

        fetch(`${API_URL}/guardarAjax`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'data=' + encodeURIComponent(JSON.stringify(data))
        })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save me-1"></i> Guardar';
            if (res.ok) {
                lcLimpiarBorrador();
                bootstrap.Modal.getInstance(document.getElementById('modalLiquidacion')).hide();

                fetchSearchFn();
                if (typeof Swal !== 'undefined') Swal.fire('Éxito', res.mensaje, 'success');
            } else {
                if (typeof Swal !== 'undefined') Swal.fire('Error', res.mensaje || "Error al guardar", 'error');
                else alert(res.mensaje || "Error al guardar");
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save me-1"></i> Guardar';
            console.error(err);
        });
    }

    // ── SRI ────────────────────────────────────────────────────────────────

    window.enviarSri = async function() {
        const id = parseInt(document.getElementById('liq-id')?.value || '0');
        if (!id) {
            Swal.fire({ icon: 'warning', title: 'Aviso', text: 'Primero guarda la liquidación antes de enviarla al SRI.' });
            return;
        }

        const confirmar = await Swal.fire({
            icon: 'question',
            title: 'Enviar al SRI',
            html: 'Se firmará la liquidación con el certificado de la empresa y se enviará al SRI para su autorización.<br><small class="text-muted">Este proceso puede tardar unos segundos.</small>',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-cloud-arrow-up me-1"></i> Enviar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
        });
        if (!confirmar.isConfirmed) return;

        Swal.fire({
            title: 'Enviando al SRI...',
            html: '<div class="spinner-border text-primary" role="status"></div><br><small class="text-muted mt-2 d-block">Firmando y enviando comprobante...</small>',
            allowOutsideClick: false,
            showConfirmButton: false,
        });

        try {
            const fd = new FormData();
            fd.append('id', id);
            const resp = await fetch(`${window.B_URL}/${window.R_MODULO}/enviar-sri-ajax`, { method: 'POST', body: fd });
            const json = await resp.json();

            if (json.ok) {
                liqActualizarPestanhaSri({
                    estado_sri:           json.estado,
                    numero_autorizacion:  json.numero_autorizacion || '',
                    fecha_autorizacion:   json.fecha_autorizacion  || '',
                    mensajes_sri:         json.errores?.length ? JSON.stringify(json.errores) : null,
                });
                liqCargarHistorialSri(id);
                Swal.fire({
                    icon: 'success',
                    title: '¡Autorizado!',
                    html: `<p>${json.mensaje}</p><code class="small">${json.numero_autorizacion || ''}</code>`,
                    confirmButtonColor: '#0d6efd',
                });
            } else {
                let errHtml = `<p class="text-danger">${json.mensaje || 'Error al enviar.'}</p>`;
                if (json.errores?.length) {
                    errHtml += '<ul class="text-start small mt-2">';
                    json.errores.forEach(e => {
                        errHtml += `<li><strong>[${e.tipo || 'ERROR'}]</strong> ${e.mensaje || ''}`;
                        if (e.info) errHtml += `<br><small class="text-muted">${e.info}</small>`;
                        errHtml += '</li>';
                    });
                    errHtml += '</ul>';
                }
                if (json.estado) liqActualizarPestanhaSri({ estado_sri: json.estado, mensajes_sri: json.errores?.length ? JSON.stringify(json.errores) : null });
                liqCargarHistorialSri(id);
                Swal.fire({ icon: 'error', title: 'No autorizado', html: errHtml, confirmButtonColor: '#dc3545' });
            }
        } catch (err) {
            liqCargarHistorialSri(id);
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar con el servidor.' });
        }
    };

    function liqActualizarPestanhaSri(data = {}) {
        const badge = document.getElementById('liq-sri-badge-estado');
        if (badge) {
            const estado = (data.estado_sri || data.estado || 'pendiente').toLowerCase();
            const map = {
                'autorizado':       ['bg-success bg-opacity-10 text-success border-success',         'Autorizado'],
                'no_autorizado':    ['bg-danger bg-opacity-10 text-danger border-danger',             'No autorizado'],
                'devuelta':         ['bg-danger bg-opacity-10 text-danger border-danger',             'Devuelta'],
                'en_procesamiento': ['bg-warning bg-opacity-10 text-warning border-warning',          'En procesamiento'],
                'recibida':         ['bg-info bg-opacity-10 text-info border-info',                   'Recibida'],
                'enviando':         ['bg-primary bg-opacity-10 text-primary border-primary',          'Enviando…'],
                'error':            ['bg-danger bg-opacity-10 text-danger border-danger',             'Error'],
                'pendiente':        ['bg-secondary bg-opacity-10 text-secondary border-secondary',    'Sin enviar'],
            };
            const [cls, lbl] = map[estado] ?? map['pendiente'];
            badge.className = `badge ${cls} border border-opacity-25 px-2`;
            badge.textContent = lbl;
        }
        const elAut = document.getElementById('liq-sri-numero-autorizacion');
        if (elAut && data.numero_autorizacion != null) elAut.value = data.numero_autorizacion;

        const elFecha = document.getElementById('liq-sri-fecha-autorizacion');
        if (elFecha && data.fecha_autorizacion) {
            try {
                const d = new Date(data.fecha_autorizacion);
                elFecha.value = isNaN(d) ? data.fecha_autorizacion :
                    d.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            } catch { elFecha.value = data.fecha_autorizacion; }
        }

        const msgBox = document.getElementById('liq-sri-mensajes-container');
        if (msgBox) {
            let mensajes = [];
            if (data.mensajes_sri) { try { mensajes = JSON.parse(data.mensajes_sri); } catch { mensajes = []; } }
            msgBox.innerHTML = mensajes.length
                ? mensajes.map(m => {
                    const tipo = (m.tipo || 'INFO').toUpperCase();
                    const cls2 = tipo === 'ERROR' ? 'text-danger' : tipo === 'ADVERTENCIA' ? 'text-warning' : 'text-info';
                    return `<div class="mb-1 ${cls2}"><strong>[${tipo}]</strong> ${m.mensaje || ''}${m.info ? `<br><small class="text-muted">${m.info}</small>` : ''}</div>`;
                }).join('')
                : '<p class="text-muted text-center mb-0 py-3 small">Sin respuesta del SRI registrada.</p>';
        }
    }

    async function liqCargarHistorialSri(id) {
        const tbody = document.getElementById('liq-sri-tbody-historial');
        if (!tbody || !id) return;
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-2 text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</td></tr>';
        try {
            const resp = await fetch(`${window.B_URL}/${window.R_MODULO}/getHistorialSriAjax?id=${id}&tipo=liquidacion_compra`);
            const json = await resp.json();
            if (!json.ok || !json.data.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted small">Sin historial de envíos.</td></tr>';
                return;
            }
            const accionMap = {
                'enviando':         ['bg-primary', 'bi-cloud-arrow-up',       'Enviando'],
                'recibida':         ['bg-info',    'bi-check-circle',          'Recibida'],
                'devuelta':         ['bg-danger',  'bi-x-circle',              'Devuelta'],
                'autorizado':       ['bg-success', 'bi-patch-check-fill',      'Autorizado'],
                'no_autorizado':    ['bg-danger',  'bi-patch-minus',           'No autorizado'],
                'en_procesamiento': ['bg-warning', 'bi-hourglass-split',       'En proceso'],
                'error':            ['bg-danger',  'bi-exclamation-triangle',  'Error'],
            };
            tbody.innerHTML = json.data.map(row => {
                const [bgCls, icon, lbl] = accionMap[row.accion] ?? ['bg-secondary', 'bi-question', row.accion];
                const esPruebas = row.tipo_ambiente === '1';
                const ambienteLbl = esPruebas
                    ? '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" style="font-size:0.65rem;">PRUEBAS</span>'
                    : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:0.65rem;">PRODUCCIÓN</span>';
                let detalle = row.mensaje || '';
                if (row.detalle_json) {
                    try {
                        const errs = JSON.parse(row.detalle_json);
                        if (Array.isArray(errs) && errs.length) {
                            detalle += '<ul class="mb-0 ps-3 mt-1" style="font-size:0.7rem;">';
                            errs.forEach(e => { detalle += `<li><strong>[${e.tipo||e.id||''}]</strong> ${e.mensaje||''} ${e.info ? '<br><em class="text-muted">'+e.info+'</em>' : ''}</li>`; });
                            detalle += '</ul>';
                        }
                    } catch (e) {}
                }
                if (row.numero_autorizacion && row.accion === 'autorizado') {
                    detalle += `<div class="font-monospace mt-1" style="font-size:0.65rem;word-break:break-all;">${row.numero_autorizacion}</div>`;
                }
                return `<tr>
                    <td class="ps-2 py-1 text-nowrap" style="font-size:0.72rem;">${row.created_at}</td>
                    <td class="py-1">${ambienteLbl}</td>
                    <td class="py-1"><span class="badge ${bgCls} bg-opacity-10 text-${bgCls.replace('bg-','')} border border-${bgCls.replace('bg-','')} border-opacity-25" style="font-size:0.65rem;"><i class="bi ${icon} me-1"></i>${lbl}</span></td>
                    <td class="py-1" style="font-size:0.72rem;">${detalle}</td>
                </tr>`;
            }).join('');
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-2 text-danger small">Error al cargar historial.</td></tr>';
        }
    }

    window.duplicar = () => alert('Funcionalidad de duplicar en desarrollo.');

    // --- Gestión de Pagos (Egresos) ---
    window.LC_cargarPagosTab = async function() {
        const idLiq = document.getElementById('liq-id').value;
        const totalLiq = parseFloat(document.getElementById('liq-lbl-total').textContent) || 0;
        
        const alertaNueva = document.getElementById('pagoAlertaNueva');
        const alertaPagada = document.getElementById('pagoAlertaPagada');
        const cardRegistro = document.getElementById('pagoCardRegistro');
        const tbody = document.getElementById('pagoTbodyHistorial');
        
        if (!alertaNueva || !alertaPagada || !cardRegistro || !tbody) return;

        // Resetear estados visuales
        alertaNueva.classList.remove('d-none');
        alertaPagada.classList.add('d-none');
        cardRegistro.classList.add('d-none');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="spinner-border spinner-border-sm me-2"></i>Cargando historial...</td></tr>';

        // Mostrar total dinámico de la UI
        document.getElementById('pagoTotalCompra').textContent = totalLiq.toFixed(2);

        if (!idLiq) {
            document.getElementById('pagoTotalAbonado').textContent = '0.00';
            document.getElementById('pagoTotalRetencion').textContent = '0.00';
            document.getElementById('pagoSaldoPendiente').textContent = totalLiq.toFixed(2);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Guarda la liquidación para poder registrar pagos internos.</td></tr>';
            return;
        }

        alertaNueva.classList.add('d-none');

        try {
            // 1. Cargar catálogos si no están
            if (!_egresoDepsCargados) {
                const respDeps = await fetch(`${API_URL}/getEgresoDependenciesAjax`);
                const resDeps = await respDeps.json();
                if (resDeps.ok) {
                    _egresoDeps = resDeps.data;
                    _egresoDepsCargados = true;
                    
                    const comboPto = document.getElementById('pagoPuntoEmision');
                    if (comboPto) {
                        comboPto.innerHTML = '<option value="">— Seleccione Punto —</option>' + 
                            (_egresoDeps.puntos || []).map(p => `<option value="${p.id_punto}">${p.estab}-${p.punto}</option>`).join('');
                        if (_egresoDeps.puntos.length > 0) comboPto.selectedIndex = 1;
                    }
                    const comboConc = document.getElementById('pagoConcepto');
                    if (comboConc) {
                        comboConc.innerHTML = '<option value="">— Seleccione Concepto —</option>' + 
                            (_egresoDeps.conceptos || []).map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
                        const cCompra = (_egresoDeps.conceptos || []).find(c => c.comportamiento === 'COMPRA');
                        if (cCompra) comboConc.value = cCompra.id;
                    }
                    const comboFP = document.getElementById('pagoFormaPago');
                    if (comboFP) {
                        comboFP.innerHTML = '<option value="">— Seleccione Forma —</option>' + 
                            (_egresoDeps.formas_pago || []).map(fp => `<option value="${fp.id}">${fp.nombre}</option>`).join('');
                    }
                    const comboBanco = document.getElementById('pagoBancoId');
                    if (comboBanco) {
                        comboBanco.innerHTML = '<option value="">— Opcional —</option>' + 
                            (_egresoDeps.bancos || []).map(b => `<option value="${b.id}">${b.nombre_banco}</option>`).join('');
                    }
                }
            }

            // 2. Cargar datos actualizados (egresos vinculados y retenciones guardadas)
            const resp = await fetch(`${API_URL}/getLiquidacionAjax?id=${idLiq}`);
            const res = await resp.json();
            if (!res.ok) throw new Error(res.mensaje);

            const cab = res.cabecera;
            // Tomamos el total del documento directamente de la UI para reflejar cambios no guardados
            const totalLiq = parseFloat(document.getElementById('liq-lbl-total').textContent) || 0;
            
            let totalAbonado = 0;
            tbody.innerHTML = '';

            if (res.egresos_vinculados && res.egresos_vinculados.length > 0) {
                res.egresos_vinculados.forEach(eg => {
                    const esAnulado = (eg.estado || '').toLowerCase() === 'anulado';
                    const montoVal = parseFloat(eg.monto_pagado || 0);
                    if (!esAnulado) totalAbonado += montoVal;

                    const tr = document.createElement('tr');
                    if (esAnulado) tr.classList.add('table-danger', 'text-decoration-line-through', 'opacity-50');
                    const fEmis = eg.fecha_emision ? eg.fecha_emision.slice(0,10).split('-').reverse().join('/') : '—';
                    
                    tr.innerHTML = `
                        <td class="ps-3">${fEmis}</td>
                        <td><code class="text-secondary fw-bold">${eg.numero_egreso || ''}</code></td>
                        <td>
                            <div class="fw-medium">${eg.concepto_nombre || ''}</div>
                            <small class="text-muted" style="font-size: 0.65rem;">${eg.formas_pago || '—'}</small>
                        </td>
                        <td class="text-end fw-bold pe-3">$ ${montoVal.toFixed(2)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No hay egresos registrados aún.</td></tr>';
            }

            // Retenciones (vienen calculadas del servidor para lo guardado)
            const totalRetenido = parseFloat(res.total_retenido || 0);

            const saldo = Math.max(0, totalLiq - totalAbonado - totalRetenido);
            document.getElementById('pagoTotalCompra').textContent = totalLiq.toFixed(2);
            document.getElementById('pagoTotalRetencion').textContent = totalRetenido.toFixed(2);
            document.getElementById('pagoTotalAbonado').textContent = totalAbonado.toFixed(2);
            document.getElementById('pagoSaldoPendiente').textContent = saldo.toFixed(2);

            if (saldo < 0.01) {
                alertaPagada.classList.remove('d-none');
                cardRegistro.classList.add('d-none');
            } else {
                alertaPagada.classList.add('d-none');
                cardRegistro.classList.remove('d-none');
                document.getElementById('pagoMontoPagar').value = saldo.toFixed(2);
                document.getElementById('pagoObservaciones').value = `Pago de Liquidación #${cab.establecimiento}-${cab.punto_emision}-${cab.secuencial}`;
            }
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger">Error: ${e.message}</td></tr>`;
        }
    };

    window.LC_toggleEgresoBancoForm = function(formaPagoId) {
        const divBanco = document.getElementById('pagoDivDetalleBanco');
        if (!divBanco) return;
        const fp = (_egresoDeps?.formas_pago || []).find(x => x.id == formaPagoId);
        if (fp && fp.tipo === 'BANCO') divBanco.classList.remove('d-none');
        else divBanco.classList.add('d-none');
    };

    window.LC_registrarPagoEgre = async function(e) {
        if (e) e.preventDefault();
        const idLiq = document.getElementById('liq-id').value;
        if (!idLiq) return;

        const btn = document.getElementById('pagoBtnRegistrar');
        const payload = {
            id_compra: parseInt(idLiq),
            monto_pagar: parseFloat(document.getElementById('pagoMontoPagar').value),
            id_punto_emision: document.getElementById('pagoPuntoEmision').value,
            fecha_emision: document.getElementById('pagoFechaEmision').value,
            id_egreso_concepto: document.getElementById('pagoConcepto').value,
            id_forma_pago: document.getElementById('pagoFormaPago').value,
            tipo_operacion_bancaria: document.getElementById('pagoTipoOp').value,
            numero_operacion: document.getElementById('pagoNumOp').value,
            banco_id: document.getElementById('pagoBancoId').value,
            observaciones: document.getElementById('pagoObservaciones').value
        };

        btn.disabled = true;
        try {
            const resp = await fetch(`${API_URL}/registrarEgresoAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await resp.json();
            if (res.ok) {
                Swal.fire('Éxito', res.msg, 'success');
                window.LC_cargarPagosTab();
                fetchSearchFn(1);
            } else throw new Error(res.error);
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
        } finally { btn.disabled = false; }
    };

    // --- Gestión de Retenciones ---
    window.LC_cargarRetencionesCompra = async function() {
        const idLiq = document.getElementById('liq-id').value;
        const tbody = document.getElementById('lc-tbody-retenciones');
        const btn = document.getElementById('btnNuevaRetencionLiq');
        
        if (!tbody || !btn) return;
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><i class="spinner-border spinner-border-sm me-2"></i>Cargando retenciones...</td></tr>';

        if (!idLiq) {
            btn.disabled = true;
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Guarda la liquidación para emitir retenciones.</td></tr>';
            return;
        }

        btn.disabled = false;
        try {
            const resp = await fetch(`${B_BASE}/modulos/retenciones_compras/getPorCompraAjax?id_liquidacion=${idLiq}`);
            const res = await resp.json();
            tbody.innerHTML = '';

            if (res.ok && res.rows && res.rows.length > 0) {
                res.rows.forEach(r => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="ps-3"><code>${r.establecimiento}-${r.punto_emision}-${r.secuencial}</code></td>
                        <td>${r.fecha_emision}</td>
                        <td class="text-end fw-bold">$ ${parseFloat(r.total_retenido).toFixed(2)}</td>
                        <td class="text-center"><span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">${r.estado}</span></td>
                        <td class="text-center"><button class="btn btn-sm btn-link p-0" onclick="window.RET_abrirModalDesdeLista(${r.id})"><i class="bi bi-eye"></i></button></td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No hay retenciones emitidas.</td></tr>';
            }
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">Error: ${e.message}</td></tr>`;
        }
    };

    window.LC_nuevaRetencionDesdeLiq = function() {
        const idLiq = document.getElementById('liq-id').value;
        if (!idLiq) return;
        if (typeof window.RET_nuevaRetencionDesdeLiquidacion === 'function') {
            window.RET_nuevaRetencionDesdeLiquidacion(idLiq);
        } else {
            Swal.fire('Error', 'Módulo de retenciones no cargado.', 'error');
        }
    };

    window.RET_abrirModalDesdeLista = function(id) {
        if (typeof window.RET_abrirModal === 'function') {
            const tr = { dataset: { row: JSON.stringify({ id: id }) } };
            window.RET_abrirModal(tr);
        }
    };

    /**
     * Calcula el último día hábil permitido para anular según las reglas del SRI:
     * Día 7 del mes siguiente a la emisión. Si cae en sábado/domingo, corre al lunes.
     */
    function liqFechaLimiteAnulacion(fechaEmision) {
        const [y, m] = fechaEmision.split('-').map(Number);
        const mesLimite  = m === 12 ? 0 : m;
        const anioLimite = m === 12 ? y + 1 : y;
        let limite = new Date(anioLimite, mesLimite, 7);
        if (limite.getDay() === 6) limite.setDate(9); // sábado → lunes
        if (limite.getDay() === 0) limite.setDate(8); // domingo → lunes
        return limite;
    }

    window.LC_anularLiquidacion = async function() {
        if (!window.LC_ID_ACTIVO) return;

        // ── Regla: Plazo del día 7 del mes siguiente ─────────────────────────
        if (window.LC_FECHA_EMISION) {
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const limiteAnulacion = liqFechaLimiteAnulacion(window.LC_FECHA_EMISION);
            limiteAnulacion.setHours(23, 59, 59, 999);

            if (hoy > limiteAnulacion) {
                const fmtLimite = limiteAnulacion.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' });
                await Swal.fire({
                    icon: 'error',
                    title: 'Fuera de plazo',
                    html: `El plazo para anular esta liquidación venció el <strong>${fmtLimite}</strong>.<br>
                           <small class="text-muted">El SRI permite anular hasta el día 7 del mes siguiente a la emisión<br>
                           (o el siguiente día hábil si cae en fin de semana).</small>`,
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }
        }

        const result = await Swal.fire({
            title: '¿Anular Liquidación?',
            text: 'Esta acción marcará la liquidación como ANULADA. No se puede revertir.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-slash-circle me-2"></i>Sí, anular',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        });

        if (!result.isConfirmed) return;

        const btn = document.getElementById('btnAnularLiq');
        const btnOrigHtml = btn?.innerHTML;
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Anulando...'; }

        try {
            const fd = new FormData();
            fd.append('id', window.LC_ID_ACTIVO);
            const resp = await fetch(`${API_URL}/anularAjax`, { method: 'POST', body: fd });
            const json = await resp.json();

            if (json.ok) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalLiquidacion')).hide();
                Swal.fire({ icon: 'success', title: 'Anulada', text: json.mensaje || 'Liquidación anulada correctamente.', timer: 3000 });
                if (typeof window.LC_fetchSearch === 'function') {
                    window.LC_fetchSearch(window.LC_currentPage || 1);
                } else {
                    location.reload();
                }
            } else {
                Swal.fire({ icon: 'error', title: 'Error al anular', text: json.mensaje || 'No se pudo completar la operación.', confirmButtonColor: '#d33' });
            }
        } catch (err) {
            console.error(err);
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Intente nuevamente.', confirmButtonColor: '#d33' });
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = btnOrigHtml; }
        }
    };

})();



