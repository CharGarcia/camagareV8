(function () {
    'use strict';

    let modalND;
    let formND;
    let motivosBody;
    let pagosBody;

    let listadoTarifasIva = [];
    let listadoFormasPago = [];
    let formasPagoListas = null; // Promise: resuelve cuando listadoFormasPago ya tiene datos.

    document.addEventListener('DOMContentLoaded', () => {
        initModal();
        cargarTarifasIva();
        formasPagoListas = cargarFormasPago();

        if (!window.currentSort) window.currentSort = window.ND_ORDEN_COL || 'fecha_emision';
        if (!window.currentDir)  window.currentDir  = window.ND_ORDEN_DIR || 'DESC';
    });

    // ─── FUNCIONES DE LISTADO (INDEX) ──────────────────────────────────────────

    window.ND_cambiarPaginaAjax = (page) => {
        window.ND_fetchSearch(page);
    };

    window.ND_buscarAjax = (e) => {
        if (e) e.preventDefault();
        window.ND_fetchSearch(1);
    };

    window.ND_ordenar = (col) => {
        const dir = (window.currentSort === col && window.currentDir === 'ASC') ? 'DESC' : 'ASC';
        window.currentSort = col;
        window.currentDir = dir;

        if (typeof window.guardarOrdenacionVista === 'function') {
            window.guardarOrdenacionVista('nota_debito', col, dir);
        }

        window.ND_fetchSearch(1);
    };

    window.ND_fetchSearch = async (page = 1) => {
        const buscar = document.getElementById('buscarND')?.value || '';
        const sort   = window.currentSort || 'fecha_emision';
        const dir    = window.currentDir  || 'DESC';
        const url = `${BASE_URL}/modulos/nota_debito/searchAjax?b=${encodeURIComponent(buscar)}&page=${page}&sort=${encodeURIComponent(sort)}&dir=${encodeURIComponent(dir)}`;

        try {
            const resp = await fetch(url);
            if (!resp.ok) return;
            const data = await resp.json();
            if (!data.ok) return;

            const tbody = document.getElementById('nd-table-body');
            if (tbody) tbody.innerHTML = data.rows ?? '';
            const pg = document.getElementById('nd-pagination');
            if (pg) pg.innerHTML = data.pagination ?? '';
            const pgInfo = document.getElementById('nd-pagination-info');
            if (pgInfo) pgInfo.textContent = data.info ?? '';

            const btnPdf = document.getElementById('btnExportPdf');
            if (btnPdf && data.pdf_url) btnPdf.href = data.pdf_url;
            const btnExcel = document.getElementById('btnExportExcel');
            if (btnExcel && data.excel_url) btnExcel.href = data.excel_url;

            ND_actualizarIconosOrden(sort, dir);
        } catch (e) {
            console.error('Error al buscar ND:', e);
        }
    };

    function ND_actualizarIconosOrden(sort, dir) {
        document.querySelectorAll('th.sortable-header').forEach(th => {
            const icon = th.querySelector('i.bi');
            if (!icon) return;
            const m = (th.getAttribute('onclick') || '').match(/ND_ordenar\('([^']+)'\)/);
            const thCol = m ? m[1] : null;
            if (!thCol) return;
            if (thCol === sort) {
                icon.className = `bi ${dir === 'ASC' ? 'bi-sort-alpha-down' : 'bi-sort-alpha-up'} text-primary ms-1`;
            } else {
                icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
            }
        });
    }

    // ─── FUNCIONES DEL MODAL ──────────────────────────────────────────────────

    function initModal() {
        if (!formND) formND = document.getElementById('formND');
        if (!motivosBody) motivosBody = document.getElementById('nd_motivos_body');
        if (!pagosBody) pagosBody = document.getElementById('nd_pagos_body');

        if (modalND) return true;

        const modalEl = document.getElementById('modalND');
        if (modalEl && typeof bootstrap !== 'undefined') {
            modalND = new bootstrap.Modal(modalEl);
            return true;
        }
        return false;
    }

    window.ND_abrirModalNuevo = () => {
        try {
            if (!initModal()) return;

            const borradorRaw = localStorage.getItem(window.ND_STORAGE_KEY);
            if (borradorRaw) {
                const borrador = JSON.parse(borradorRaw);
                const divAviso = document.createElement('div');
                divAviso.id = 'nd-borrador-aviso';
                divAviso.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;';
                const clienteName = borrador.cliente_nombre || 'desconocido';
                divAviso.innerHTML = `
                    <div class="bg-white rounded-3 shadow-lg p-4" style="max-width:420px;width:90%;">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-4"></i>
                            <h6 class="fw-bold mb-0">Nota de débito sin guardar</h6>
                        </div>
                        <p class="small text-muted mb-4">Hay una nota de débito en borrador del cliente <strong>${clienteName}</strong> que no fue guardada. ¿Qué desea hacer?</p>
                        <div class="d-flex gap-2 justify-content-end">
                            <button class="btn btn-sm btn-outline-secondary" id="nd-aviso-nueva">
                                <i class="bi bi-file-earmark-plus me-1"></i> Nueva nota
                            </button>
                            <button class="btn btn-sm btn-primary" id="nd-aviso-restaurar">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Cargar borrador
                            </button>
                        </div>
                    </div>`;
                document.body.appendChild(divAviso);
                document.getElementById('nd-aviso-restaurar').onclick = () => {
                    divAviso.remove();
                    ND_resetearYMostrar(borrador);
                };
                document.getElementById('nd-aviso-nueva').onclick = () => {
                    window.ND_eliminarRespaldo();
                    divAviso.remove();
                    ND_resetearYMostrar();
                };
                return;
            }

            ND_resetearYMostrar();
        } catch (err) {
            console.error('Error crítico al abrir modal nuevo:', err);
        }
    };

    function ND_resetearYMostrar(borrador = null) {
        try {
            if (formND) formND.reset();
            setEl('nd_id', 'value', '');

            document.getElementById('modalNDTitulo').innerHTML = '<i class="bi bi-file-earmark-plus text-primary me-2"></i>Nueva Nota de Débito';

            if (motivosBody) motivosBody.innerHTML = '';
            if (pagosBody) pagosBody.innerHTML = '';
            ND_agregarMotivo();

            // ND nueva: agregar una fila de pago por defecto (igual que Factura de
            // Venta, que siempre muestra una), ya preseleccionada con la forma de
            // pago del cliente o de la empresa. Se espera a que el catálogo de
            // formas de pago haya cargado para no perder el default por una
            // condición de carrera. Si se va a restaurar un borrador, se omite:
            // ND_restaurarRespaldo() reconstruye sus propias filas de pago.
            if (!borrador) {
                Promise.resolve(formasPagoListas).then(() => {
                    if (!document.getElementById('nd_id').value) window.ND_agregarPago();
                });
            }

            // ND nueva: mostrar la vista previa del RUC Proveedor (se agrega solo
            // a documentos nuevos, ver comentario en el <tbody> del modal).
            document.getElementById('nd-tbody-ruc-proveedor-preview')?.classList.remove('d-none');

            setEl('nd_info_factura_modificada', 'innerHTML', '');
            setEl('nd_lbl_cliente_ruc', 'textContent', '');
            setEl('nd_lbl_cliente_direccion', 'textContent', '');
            setEl('nd_lbl_cliente_correo', 'textContent', '');
            setEl('nd_factura_search', 'value', '');
            setEl('nd_fecha_emision_docs_sustento', 'value', '');
            setEl('nd_id_cliente', 'value', '');
            const infoCli = document.getElementById('nd_info_cliente');
            if (infoCli) infoCli.classList.add('d-none');
            ND_limpiarInfoAdicional();
            ND_setModoLectura(false);
            ND_setFacturaHabilitada(false);

            window.ND_cargarSecuencial();

            setEl('nd-sri-clave-acceso',           'value', '');
            setEl('nd-sri-autorizacion',            'value', '');
            setEl('nd-sri-fecha-autorizacion',      'value', '');
            setEl('nd-sri-numero-documento',        'value', '');
            setEl('nd-sri-identificacion-cliente',  'value', '');
            setEl('nd-sri-correo-cliente',          'value', '');
            window.ND_FECHA_EMISION = null;
            window.ND_CLIENTE_RUC   = '';
            const tbodySri = document.getElementById('nd-sri-tbody-historial');
            if (tbodySri) tbodySri.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Sin historial de envíos.</td></tr>';

            toggleBotonesAccion(false, 'borrador');

            modalND.show();

            document.getElementById('modalND').addEventListener('shown.bs.modal', function onShown() {
                if (borrador) window.ND_restaurarRespaldo(borrador);
                const inpCli = document.getElementById('nd_cliente_search');
                if (inpCli) inpCli.focus();
                this.removeEventListener('shown.bs.modal', onShown);
            });

            window.ND_registrarAutoGuardado();

            if (typeof window.aplicarFavoritosModal === 'function') window.aplicarFavoritosModal('#modalND');

            setTimeout(() => { try { ND_calcTotales(); } catch (e) {} }, 100);
        } catch (e) { console.error(e); }
    }

    window.ND_abrirModalND = async (row) => {
        try {
            if (!initModal()) return;

            const data = JSON.parse(row.dataset.row);
            const est = String(data.establecimiento || '000').padStart(3, '0');
            const pto = String(data.punto_emision || '000').padStart(3, '0');
            const sec = String(data.secuencial || '0').padStart(9, '0');
            const num = `${est}-${pto}-${sec}`;
            const cliente = data.cliente_nombre ? ` - ${data.cliente_nombre}` : '';

            document.getElementById('modalNDTitulo').innerHTML = `<i class="bi bi-file-earmark-plus text-primary me-2"></i>Nota de Débito ${num}${cliente}`;
            setEl('nd_id', 'value', data.id);

            // ND existente: ocultar la vista previa del RUC Proveedor — ese campo
            // solo se guarda en documentos nuevos.
            document.getElementById('nd-tbody-ruc-proveedor-preview')?.classList.add('d-none');

            document.getElementById('nd_fecha_emision').value = (data.fecha_emision || '').split(' ')[0].split('T')[0];
            document.getElementById('nd_id_punto_emision').value = data.id_punto_emision || '';
            document.getElementById('nd_secuencial').value = data.secuencial != null ? String(data.secuencial).padStart(9, '0') : '';
            document.getElementById('nd_id_cliente').value = data.id_cliente || '';
            document.getElementById('nd_cliente_search').value = data.cliente_nombre || '';
            ND_setFacturaHabilitada(true);
            const fechaSustento = (data.fecha_emision_docs_sustento || '').split(' ')[0].split('T')[0];
            document.getElementById('nd_factura_search').value = data.num_doc_modificado || '';
            document.getElementById('nd_fecha_emision_docs_sustento').value = fechaSustento;

            document.getElementById('nd_info_factura_modificada').innerHTML = `
                <div class="d-flex gap-3 flex-wrap">
                    <span><i class="bi bi-file-earmark-text me-1"></i> ${data.num_doc_modificado || '—'}</span>
                    <span><i class="bi bi-calendar me-1"></i> ${fechaSustento || '—'}</span>
                </div>
            `;

            actualizarBadgeEstado(data.estado);
            toggleBotonesAccion(true, data.estado);

            try {
                const resp = await fetch(`${BASE_URL}/modulos/nota_debito/getNdAjax?id=${data.id}`);
                const result = await resp.json();
                if (result.ok) {
                    ND_renderMotivos(result.motivos);
                    ND_renderPagos(result.pagos);
                    ND_seleccionarTarifaDesdeImpuestos(result.impuestos);
                    ND_renderInfoAdicional(result.info_adicional);
                    ND_calcTotales();

                    const cab = result.cabecera || {};
                    const soloDia = (f) => (f || '').split(' ')[0].split('T')[0];

                    if (cab.id_punto_emision != null) document.getElementById('nd_id_punto_emision').value = cab.id_punto_emision;
                    if (cab.secuencial != null)        document.getElementById('nd_secuencial').value = String(cab.secuencial).padStart(9, '0');
                    if (cab.fecha_emision)             document.getElementById('nd_fecha_emision').value = soloDia(cab.fecha_emision);
                    if (cab.id_cliente != null)        document.getElementById('nd_id_cliente').value = cab.id_cliente;
                    if (cab.cliente_nombre != null)    document.getElementById('nd_cliente_search').value = cab.cliente_nombre;
                    if (cab.num_doc_modificado != null) {
                        ND_setFacturaHabilitada(true);
                        document.getElementById('nd_factura_search').value = cab.num_doc_modificado;
                    }
                    if (cab.fecha_emision_docs_sustento) document.getElementById('nd_fecha_emision_docs_sustento').value = soloDia(cab.fecha_emision_docs_sustento);

                    setEl('nd_lbl_cliente_ruc', 'textContent', cab.cliente_ruc || '');
                    setEl('nd_lbl_cliente_direccion', 'textContent', cab.cliente_direccion || '');
                    setEl('nd_lbl_cliente_correo', 'textContent', cab.cliente_email || '');
                    const infoCli = document.getElementById('nd_info_cliente');
                    if (infoCli && cab.id_cliente) infoCli.classList.remove('d-none');

                    if (cab.secuencial != null) {
                        const estN = String(cab.establecimiento || '000').padStart(3, '0');
                        const ptoN = String(cab.punto_emision || '000').padStart(3, '0');
                        const secN = String(cab.secuencial || '0').padStart(9, '0');
                        const cliN = cab.cliente_nombre ? ` - ${cab.cliente_nombre}` : '';
                        document.getElementById('modalNDTitulo').innerHTML = `<i class="bi bi-file-earmark-plus text-primary me-2"></i>Nota de Débito ${estN}-${ptoN}-${secN}${cliN}`;
                    }

                    const estadoEfectivo =
                        (cab.estado === 'autorizado' || cab.estado_sri === 'autorizado') ? 'autorizado' :
                        (cab.estado === 'anulado'    || cab.estado_sri === 'anulado')    ? 'anulado'    :
                        (cab.estado || 'borrador');
                    actualizarBadgeEstado(estadoEfectivo);
                    toggleBotonesAccion(true, estadoEfectivo);

                    const elCorreo = document.getElementById('nd-sri-correo-cliente');
                    if (elCorreo) elCorreo.value = cab.cliente_email || '';
                    const elIdentif = document.getElementById('nd-sri-identificacion-cliente');
                    if (elIdentif && !elIdentif.value) elIdentif.value = cab.cliente_ruc || '';
                    window.ND_CLIENTE_RUC = (cab.cliente_ruc || '').trim();

                    const soloLectura = (data && data._soloLectura === true) || (estadoEfectivo !== 'borrador');
                    ND_setModoLectura(!!soloLectura);
                }
            } catch (e) {
                console.error('Error al cargar datos ND:', e);
            }

            const elClaveAcceso  = document.getElementById('nd-sri-clave-acceso');
            const elAmbiente     = document.getElementById('nd-sri-ambiente');
            const elTipoEmision  = document.getElementById('nd-sri-tipo-emision');
            const elAutorizacion = document.getElementById('nd-sri-autorizacion');
            const elFechaAut     = document.getElementById('nd-sri-fecha-autorizacion');
            const elBadge        = document.getElementById('nd-sri-badge-estado');

            if (elClaveAcceso)  elClaveAcceso.value = data.clave_acceso || '';
            if (elAmbiente) {
                const amb = String(data.tipo_ambiente ?? '1');
                elAmbiente.value = amb === '2' ? '2 - PRODUCCIÓN' : '1 - PRUEBAS';
            }
            if (elTipoEmision) {
                const te = String(data.tipo_emision ?? '1');
                elTipoEmision.value = te === '2' ? '2 - Offline / Indisponibilidad' : '1 - NORMAL';
            }
            if (elAutorizacion) elAutorizacion.value = data.numero_autorizacion || data.clave_acceso || '';
            if (elFechaAut)     elFechaAut.value     = data.fecha_autorizacion || '';

            const elNroDoc = document.getElementById('nd-sri-numero-documento');
            if (elNroDoc) elNroDoc.value = num;
            const elIdentif = document.getElementById('nd-sri-identificacion-cliente');
            if (elIdentif) elIdentif.value = data.cliente_ruc   || '';
            const elCorreo  = document.getElementById('nd-sri-correo-cliente');
            if (elCorreo)   elCorreo.value  = data.cliente_email || '';

            window.ND_FECHA_EMISION  = (data.fecha_emision || '').split(' ')[0].split('T')[0] || null;
            window.ND_CLIENTE_RUC    = (data.cliente_ruc  || '').trim();

            if (elBadge) {
                const estadoMap = {
                    'autorizado':    ['bg-success bg-opacity-10 text-success border-success',       'Autorizado'],
                    'anulado':       ['bg-danger bg-opacity-10 text-danger border-danger',           'Anulado'],
                    'no_autorizado': ['bg-danger bg-opacity-10 text-danger border-danger',           'No autorizado'],
                    'enviando':      ['bg-primary bg-opacity-10 text-primary border-primary',        'Enviando…'],
                    'recibida':      ['bg-info bg-opacity-10 text-info border-info',                 'Recibida'],
                    'error':         ['bg-danger bg-opacity-10 text-danger border-danger',           'Error'],
                };
                const [cls, lbl] = estadoMap[data.estado] ?? ['bg-secondary bg-opacity-10 text-secondary border-secondary', 'Sin enviar'];
                elBadge.className = `badge ${cls} border border-opacity-25 px-2`;
                elBadge.textContent = lbl;
            }

            ndCargarHistorialSri(data.id);
            window.ND_ID_ACTIVO = data.id;

            if (typeof window.aplicarFavoritosModal === 'function') window.aplicarFavoritosModal('#modalND');

            modalND.show();
        } catch (err) {
            console.error('Error al abrir modal edición:', err);
            Swal.fire('Error', 'Ocurrió un error al cargar la nota de débito.', 'error');
        }
    };

    function toggleBotonesAccion(habilitar, estado = 'borrador') {
        const esAutorizado = (estado === 'autorizado');
        const esAnulado = (estado === 'anulado');
        const esBorrador = (estado === 'borrador');

        document.getElementById('nd-btn-sri').disabled = !habilitar || esAutorizado || esAnulado;
        document.getElementById('nd-btn-pdf').disabled = !habilitar;
        document.getElementById('nd-btn-xml').disabled = !habilitar;
        document.getElementById('nd-btn-excel').disabled = !habilitar;
        document.getElementById('nd-btn-correo').disabled = !habilitar || !esAutorizado;
        document.getElementById('btnGuardarND').disabled = esAutorizado || esAnulado;

        const btnEliminar = document.getElementById('btnEliminarND');
        const btnAnular = document.getElementById('btnAnularND');

        if (btnEliminar) btnEliminar.classList.toggle('d-none', !habilitar || !esBorrador);
        if (btnAnular) btnAnular.classList.toggle('d-none', !habilitar || !esAutorizado);
    }

    function ND_setModoLectura(lock) {
        const modal = document.getElementById('modalND');
        if (!modal) return;

        modal.classList.toggle('nd-lectura', !!lock);

        const campos = ['nd_fecha_emision', 'nd_id_punto_emision', 'nd_tarifa_iva',
            'nd_cliente_search', 'nd_factura_search', 'nd_fecha_emision_docs_sustento'];
        campos.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            if (el.tagName === 'SELECT') el.disabled = !!lock;
            else el.readOnly = !!lock;
        });

        modal.querySelectorAll('#nd_motivos_body input, #nd_pagos_body input, #nd_pagos_body select, #nd-tbody-info-adicional input')
            .forEach(el => {
                if (el.tagName === 'SELECT') el.disabled = !!lock;
                else el.readOnly = !!lock;
            });

        const btnGuardar = document.getElementById('btnGuardarND');
        if (btnGuardar) btnGuardar.classList.toggle('d-none', !!lock);
    }

    function setEl(id, prop, val) {
        const el = document.getElementById(id);
        if (el) el[prop] = val;
    }

    function actualizarBadgeEstado(estado) {
        // Reservado para un badge de cabecera si se agrega en el futuro.
    }

    function ND_avisarSecuencialNoConfigurado(tipo) {
        if (typeof Swal === 'undefined') return;
        const html = (tipo === 'serie')
            ? 'No hay una serie / punto de emisión disponible.<br>Configure los puntos de emisión y sus secuenciales en <strong>Empresa → Puntos de emisión</strong> antes de emitir la nota de débito.'
            : 'No están configurados los secuenciales para esta serie (tipo de documento "Nota de débito").<br>Configúrelos en <strong>Empresa → Secuenciales</strong> antes de emitir la nota de débito.';
        Swal.fire({
            icon: 'warning',
            title: 'Secuenciales no configurados',
            html: html,
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#f39c12',
            target: document.getElementById('modalND'),
        });
    }

    window.ND_cargarSecuencial = async () => {
        // En edición de una ND existente no se recalcula ni se valida la serie.
        const idActual = document.getElementById('nd_id').value;
        if (idActual) return;

        const idPt = document.getElementById('nd_id_punto_emision').value;
        const inputSec = document.getElementById('nd_secuencial');

        if (!idPt) {
            window.ND_SECUENCIAL_CONFIGURADO = false;
            if (inputSec) { inputSec.value = ''; inputSec.placeholder = 'Sin serie'; }
            ND_avisarSecuencialNoConfigurado('serie');
            return;
        }

        if (inputSec) inputSec.placeholder = 'Cargando...';
        try {
            const resp = await fetch(`${BASE_URL}/modulos/nota_debito/getSecuencialAjax?id_punto=${idPt}`);
            const data = await resp.json();
            if (data.ok) {
                inputSec.value = data.formateado || String(data.secuencial).padStart(9, '0');
                inputSec.placeholder = '000000001';

                if (data.es_gap) {
                    inputSec.classList.add('border-warning');
                    inputSec.title = data.detalle || 'Número faltante recuperado';
                } else {
                    inputSec.classList.remove('border-warning');
                    inputSec.title = data.detalle || 'Siguiente consecutivo';
                }

                window.ND_SECUENCIAL_CONFIGURADO = (data.configurado !== false);
                if (data.configurado === false) {
                    inputSec.classList.add('border-danger');
                    ND_avisarSecuencialNoConfigurado('secuencial');
                } else {
                    inputSec.classList.remove('border-danger');
                }
            } else {
                inputSec.value = '000000001';
                inputSec.placeholder = '000000001';
                window.ND_SECUENCIAL_CONFIGURADO = false;
                ND_avisarSecuencialNoConfigurado('secuencial');
            }
        } catch (e) {
            if (inputSec) {
                inputSec.value = '000000001';
                inputSec.placeholder = '000000001';
            }
            console.error('Error cargando secuencial ND:', e);
        }
    };

    // ─── AUTOCOMPLETE CLIENTES ──────────────────────────────────────────────

    const searchCliente = document.getElementById('nd_cliente_search');
    const dropdownCliente = document.getElementById('nd_cliente_dropdown');

    if (searchCliente) {
        searchCliente.addEventListener('input', debounce(async () => {
            const term = searchCliente.value.trim();
            if (term.length < 2) {
                dropdownCliente.classList.add('d-none');
                return;
            }

            const resp = await fetch(`${BASE_URL}/modulos/factura_venta/getClientesAjax?q=${encodeURIComponent(term)}`);
            const data = await resp.json();
            if (data.ok && data.data.length > 0) {
                dropdownCliente.innerHTML = data.data.map(c => `
                    <a href="#" class="list-group-item list-group-item-action py-2" onclick="window.ND_seleccionarCliente(${JSON.stringify(c).replace(/"/g, '&quot;')})">
                        <div class="fw-bold small">${c.nombre}</div>
                        <small class="text-muted" style="font-size:0.7rem;">${c.identificacion}</small>
                    </a>
                `).join('');
                dropdownCliente.classList.remove('d-none');
            } else {
                dropdownCliente.classList.add('d-none');
            }
        }, 300));
    }

    function ND_setFacturaHabilitada(habilitar) {
        const inpFactura = document.getElementById('nd_factura_search');
        const inpFecha   = document.getElementById('nd_fecha_emision_docs_sustento');
        if (inpFactura) {
            inpFactura.disabled = !habilitar;
            inpFactura.placeholder = habilitar
                ? 'Buscar factura del cliente o escribir el número...'
                : 'Seleccione un cliente primero...';
        }
        if (inpFecha) inpFecha.disabled = !habilitar;
    }

    window.ND_seleccionarCliente = (c, opciones = {}) => {
        if (c.identificacion === '9999999999999') {
            Swal.fire('Atención', 'No se puede emitir una Nota de Débito a Consumidor Final.', 'warning');
            document.getElementById('nd_cliente_search').value = '';
            document.getElementById('nd_id_cliente').value = '';
            return;
        }
        document.getElementById('nd_id_cliente').value = c.id;
        searchCliente.value = c.nombre;
        if (dropdownCliente) dropdownCliente.classList.add('d-none');

        setEl('nd_lbl_cliente_ruc', 'textContent', c.identificacion || '');
        setEl('nd_lbl_cliente_direccion', 'textContent', c.direccion || '');
        setEl('nd_lbl_cliente_correo', 'textContent', c.email || '');
        const infoCli = document.getElementById('nd_info_cliente');
        if (infoCli) infoCli.classList.remove('d-none');
        window.ND_CLIENTE_RUC = (c.identificacion || '').trim();
        // Forma de pago SRI del cliente (si tiene una configurada): manda sobre el
        // default de empresa al agregar un pago nuevo.
        window.ND_CLIENTE_FORMA_PAGO_SRI = c.id_forma_pago_sri || null;
        // Si ya hay una única fila de pago sin usar (el default agregado al abrir
        // el modal), se re-resuelve con la forma de pago del cliente recién elegido.
        ND_reaplicarFormaPagoDefaultSiVacia();

        ND_actualizarCorreoCliente(c.email || '');
        // El campo de la pestaña SRI (usado como default al "Enviar por correo") solo se
        // llenaba al cargar el documento; si se cambiaba el cliente en la misma edición
        // quedaba con el correo del cliente anterior.
        const elCorreoSri = document.getElementById('nd-sri-correo-cliente');
        if (elCorreoSri) elCorreoSri.value = c.email || '';
        const elIdentifSri = document.getElementById('nd-sri-identificacion-cliente');
        if (elIdentifSri) elIdentifSri.value = c.identificacion || '';
        ND_setFacturaHabilitada(true);

        if (!opciones.conservarDoc) {
            const inpFactura = document.getElementById('nd_factura_search');
            const inpFecha   = document.getElementById('nd_fecha_emision_docs_sustento');
            if (inpFactura) inpFactura.value = '';
            if (inpFecha)   inpFecha.value = '';
            setEl('nd_info_factura_modificada', 'innerHTML', '');
            if (inpFactura) inpFactura.focus();
        }
    };

    // ─── AUTOCOMPLETE FACTURAS ─────────────────────────────────────────────

    const searchFactura = document.getElementById('nd_factura_search');
    const dropdownFactura = document.getElementById('nd_factura_dropdown');

    function ND_fechaSoloDia(f) {
        if (!f) return '';
        return String(f).split(' ')[0].split('T')[0];
    }

    function ND_aplicarMascaraNroDoc(el) {
        if (!el) return;
        el.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 15) v = v.slice(0, 15);
            let res = '';
            if (v.length > 0) res += v.slice(0, 3);
            if (v.length > 3) res += '-' + v.slice(3, 6);
            if (v.length > 6) res += '-' + v.slice(6, 15);
            e.target.value = res;
        });
        el.addEventListener('blur', (e) => {
            const parts = e.target.value.split('-');
            if (parts.length === 1 && parts[0].length > 0) {
                const v = parts[0];
                if (v.length <= 9) e.target.value = `001-001-${v.padStart(9, '0')}`;
            } else if (parts.length === 3) {
                e.target.value = `${parts[0].padStart(3, '0')}-${parts[1].padStart(3, '0')}-${parts[2].padStart(9, '0')}`;
            }
        });
    }

    async function ND_cargarFacturasCliente(term = '') {
        if (searchFactura && (searchFactura.readOnly || searchFactura.disabled)) {
            dropdownFactura.classList.add('d-none');
            return;
        }
        const idCliente = document.getElementById('nd_id_cliente').value;
        if (!idCliente) {
            dropdownFactura.classList.add('d-none');
            return;
        }

        const url = `${BASE_URL}/modulos/nota_debito/buscarFacturasAjax?q=${encodeURIComponent(term)}&id_cliente=${idCliente}`;
        try {
            const resp = await fetch(url);
            const data = await resp.json();
            if (data.ok && data.data.length > 0) {
                dropdownFactura.innerHTML = data.data.map(f => {
                    const json = JSON.stringify(f).replace(/"/g, '&quot;');
                    const esSaldo = f.origen === 'saldo_inicial';
                    const badge = esSaldo
                        ? '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" style="font-size:0.62rem;">SALDO INICIAL</span>'
                        : `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:0.62rem;">${(f.estado || '').toUpperCase()}</span>`;
                    const total = parseFloat(f.importe_total || 0).toFixed(2);
                    return `
                        <a href="#" class="list-group-item list-group-item-action py-2" onclick="window.ND_seleccionarFactura(${json})">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold small">${f.num}</span>
                                ${badge}
                            </div>
                            <small class="text-muted" style="font-size:0.7rem;"><i class="bi bi-calendar me-1"></i>${ND_fechaSoloDia(f.fecha_emision)} &middot; $${total}</small>
                        </a>
                    `;
                }).join('');
                dropdownFactura.classList.remove('d-none');
            } else {
                dropdownFactura.innerHTML = '<div class="list-group-item small text-muted py-2"><i class="bi bi-info-circle me-1"></i>Sin documentos para este cliente. Puede escribir el número manualmente.</div>';
                dropdownFactura.classList.remove('d-none');
            }
        } catch (e) {
            console.error('Error al buscar documentos del cliente:', e);
            dropdownFactura.classList.add('d-none');
        }
    }

    if (searchFactura) {
        ND_aplicarMascaraNroDoc(searchFactura);
        searchFactura.addEventListener('input', debounce(() => {
            ND_cargarFacturasCliente(searchFactura.value.trim());
        }, 300));
        searchFactura.addEventListener('focus', () => {
            ND_cargarFacturasCliente(searchFactura.value.trim());
        });
        document.addEventListener('click', (e) => {
            if (dropdownFactura && !dropdownFactura.contains(e.target) && e.target !== searchFactura) {
                dropdownFactura.classList.add('d-none');
            }
        });
    }

    window.ND_seleccionarFactura = async (f) => {
        if (f.cliente_ruc === '9999999999999') {
            Swal.fire('Atención', 'No se puede emitir una Nota de Débito a un documento de Consumidor Final.', 'warning');
            return;
        }
        const fechaDia = ND_fechaSoloDia(f.fecha_emision);
        searchFactura.value = f.num_doc || f.num || '';
        document.getElementById('nd_fecha_emision_docs_sustento').value = fechaDia;
        dropdownFactura.classList.add('d-none');
        document.getElementById('nd_fecha_emision_docs_sustento').focus();

        const esSaldo = f.origen === 'saldo_inicial';
        const tipoLbl = esSaldo ? 'SALDO INICIAL' : (f.estado || '').toUpperCase();
        document.getElementById('nd_info_factura_modificada').innerHTML = `
            <div class="d-flex gap-3 flex-wrap">
                <span><i class="bi bi-calendar me-1"></i> ${fechaDia}</span>
                <span><i class="bi bi-cash me-1"></i> $${parseFloat(f.importe_total || 0).toFixed(2)}</span>
                <span class="${esSaldo ? 'text-warning' : 'text-primary'} fw-bold">${tipoLbl}</span>
            </div>
        `;
    };

    // ─── MOTIVOS ────────────────────────────────────────────────────────────

    window.ND_agregarMotivo = (razon = '', valor = '') => {
        if (!motivosBody) return;
        const placeholder = motivosBody.querySelector('td[colspan]');
        if (placeholder) motivosBody.innerHTML = '';

        const tr = document.createElement('tr');
        tr.className = 'row-det row-motivo-nd';
        tr.innerHTML = `
            <td class="ps-2"><input type="text" class="input-detalle" name="mot_razon[]" placeholder="Razón de la modificación..." value="${String(razon).replace(/"/g, '&quot;')}"></td>
            <td><input type="number" step="0.01" min="0" class="input-detalle text-end" name="mot_valor[]" placeholder="0.00" value="${valor}" oninput="window.ND_calcTotales()"></td>
            <td class="text-center">
                <button type="button" class="btn btn-link btn-sm p-0 text-danger shadow-none nd-edit-only" onclick="this.closest('tr').remove(); window.ND_calcTotales();">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </td>`;
        motivosBody.appendChild(tr);
        if (!razon) tr.querySelector('input[name="mot_razon[]"]').focus();
    };

    function ND_renderMotivos(motivos) {
        if (!motivosBody) return;
        motivosBody.innerHTML = '';
        (motivos || []).forEach(m => window.ND_agregarMotivo(m.razon || '', m.valor || 0));
        if (!motivos || !motivos.length) window.ND_agregarMotivo();
    }

    // ─── PAGOS (opcional) ───────────────────────────────────────────────────

    function ND_formasPagoOptions(selected = '') {
        if (!listadoFormasPago.length) {
            return `<option value="01" ${selected === '01' ? 'selected' : ''}>Sin utilización del sistema financiero</option>`;
        }
        return listadoFormasPago.map(fp => `<option value="${fp.codigo}" ${fp.codigo === selected ? 'selected' : ''}>${fp.nombre}</option>`).join('');
    }

    // Resuelve la forma de pago SRI por defecto para una fila nueva.
    // Precedencia: forma del CLIENTE → default configurado en EMPRESA.
    // Devuelve el "codigo" SRI (ej. "01"), no el id, porque el <select> de
    // pagos usa codigo como value.
    function ND_resolverFormaPagoDefault() {
        if (!listadoFormasPago.length) return '';

        const idCliente  = window.ND_CLIENTE_FORMA_PAGO_SRI || null;
        const idEmpresa  = (typeof EMPRESA_CONFIG !== 'undefined' && EMPRESA_CONFIG.id_forma_pago_sri_def) ? EMPRESA_CONFIG.id_forma_pago_sri_def : null;
        const idResuelto = idCliente || idEmpresa;
        if (!idResuelto) return '';

        const fp = listadoFormasPago.find(f => String(f.id) === String(idResuelto));
        return fp ? fp.codigo : '';
    }

    // Si hay exactamente una fila de pago y todavía no se le puso un total (es
    // decir, sigue siendo la fila por defecto sin tocar), se le vuelve a aplicar
    // la forma de pago resuelta — útil cuando el cliente se elige DESPUÉS de que
    // la fila por defecto ya se agregó (con solo el default de empresa).
    function ND_reaplicarFormaPagoDefaultSiVacia() {
        if (!pagosBody) return;
        const filas = pagosBody.querySelectorAll('.row-pago-nd');
        if (filas.length !== 1) return;
        const fila = filas[0];
        const total = parseFloat(fila.querySelector('input[name="pago_total[]"]')?.value) || 0;
        if (total > 0) return;
        const sel = fila.querySelector('select[name="pago_forma[]"]');
        const codigo = ND_resolverFormaPagoDefault();
        if (sel && codigo) sel.value = codigo;
    }

    // Filas tipo "row-pago" (divs, no <tr>), mismo diseño que la pestaña
    // "Formas de pago SRI" de Factura de Venta.
    window.ND_agregarPago = (formaPago = '', total = '', plazo = '', unidad = 'dias') => {
        if (!pagosBody) return;
        if (!formaPago) formaPago = ND_resolverFormaPagoDefault();
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center mb-1 row-pago-nd';
        row.innerHTML = `
            <div class="col-5">
                <select class="form-select form-select-sm border-0 bg-light" name="pago_forma[]">${ND_formasPagoOptions(formaPago)}</select>
            </div>
            <div class="col-2">
                <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end border-0 bg-light fw-bold" name="pago_total[]" placeholder="0.00" value="${total}">
            </div>
            <div class="col-2">
                <input type="number" step="1" min="0" class="form-control form-control-sm text-center border-0 bg-light" name="pago_plazo[]" placeholder="Plazo" value="${plazo}">
            </div>
            <div class="col-2">
                <input type="text" class="form-control form-control-sm border-0 bg-light" name="pago_unidad[]" placeholder="Unidad" value="${unidad}">
            </div>
            <div class="col-1 text-center nd-edit-only">
                <button type="button" class="btn btn-link btn-sm p-0 text-danger shadow-none" onclick="this.closest('.row-pago-nd').remove();">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>`;
        pagosBody.appendChild(row);
    };

    function ND_renderPagos(pagos) {
        if (!pagosBody) return;
        pagosBody.innerHTML = '';
        (pagos || []).forEach(p => window.ND_agregarPago(p.forma_pago || '', p.total || 0, p.plazo || 0, p.unidad_tiempo || 'dias'));
    }

    function ND_capturarPagos() {
        const pagos = [];
        document.querySelectorAll('#nd_pagos_body .row-pago-nd').forEach(row => {
            const total = parseFloat(row.querySelector('input[name="pago_total[]"]').value) || 0;
            if (total <= 0) return;
            pagos.push({
                forma_pago: row.querySelector('select[name="pago_forma[]"]').value,
                total: total,
                plazo: parseInt(row.querySelector('input[name="pago_plazo[]"]').value) || 0,
                unidad_tiempo: row.querySelector('input[name="pago_unidad[]"]').value || 'dias',
            });
        });
        return pagos;
    }

    // ─── INFORMACIÓN ADICIONAL ──────────────────────────────────────────────

    const ND_INFO_INPUT_STYLE = 'padding:0 8px;height:30px;font-size:0.8rem;';

    window.ND_agregarInfoAdicional = (concepto = '', detalle = '') => {
        const tbody = document.getElementById('nd-tbody-info-adicional');
        if (!tbody) return;
        const tr = document.createElement('tr');
        tr.className = 'row-info-adicional-nd';
        tr.innerHTML = `
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto-nd" style="${ND_INFO_INPUT_STYLE}" placeholder="Concepto..." value="${(concepto || '').replace(/"/g, '&quot;')}"></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle-nd" style="${ND_INFO_INPUT_STYLE}" placeholder="Detalle..." value="${(detalle || '').replace(/"/g, '&quot;')}"></td>
            <td class="p-0 text-center pe-1">
                <button type="button" class="btn btn-link btn-sm p-0 m-0 text-danger shadow-none nd-edit-only" onclick="this.closest('tr').remove();">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </td>`;
        const filaFija = tbody.querySelector('tr[data-tipo]');
        if (filaFija) tbody.insertBefore(tr, filaFija);
        else tbody.appendChild(tr);
        if (!concepto) tr.querySelector('.input-info-concepto-nd').focus();
    };

    function ND_actualizarCorreoCliente(email) {
        const tbody = document.getElementById('nd-tbody-info-adicional');
        if (!tbody) return;
        let fila = tbody.querySelector('tr[data-tipo="correo-cliente"]');
        if (!email) { if (fila) fila.remove(); return; }

        if (fila) {
            fila.querySelector('.input-info-detalle-nd').value = email;
        } else {
            fila = document.createElement('tr');
            fila.className = 'row-info-adicional-nd';
            fila.dataset.tipo = 'correo-cliente';
            fila.innerHTML = `
                <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto-nd" style="${ND_INFO_INPUT_STYLE}" value="Correo del cliente" readonly></td>
                <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle-nd" style="${ND_INFO_INPUT_STYLE}" value="${(email || '').replace(/"/g, '&quot;')}"></td>
                <td class="p-0 text-center pe-1"><span class="text-muted small" title="Se actualiza al cambiar el cliente"><i class="bi bi-lock-fill"></i></span></td>`;
            tbody.appendChild(fila);
        }
    }

    function ND_capturarInfoAdicional() {
        const items = [];
        document.querySelectorAll('#nd-tbody-info-adicional tr.row-info-adicional-nd').forEach(tr => {
            const nombre = (tr.querySelector('.input-info-concepto-nd')?.value || '').trim();
            const valor  = (tr.querySelector('.input-info-detalle-nd')?.value || '').trim();
            if (nombre && valor) items.push({ nombre, valor });
        });
        return items;
    }

    function ND_limpiarInfoAdicional() {
        const tbody = document.getElementById('nd-tbody-info-adicional');
        if (tbody) tbody.innerHTML = '';
    }

    function ND_renderInfoAdicional(items) {
        ND_limpiarInfoAdicional();
        let correo = '';
        (items || []).forEach(ia => {
            const nombre = ia.nombre ?? '';
            const valor  = ia.valor ?? '';
            const n = nombre.trim().toLowerCase();
            if (n === 'correo del cliente' || n === 'correo') {
                correo = valor;
            } else {
                window.ND_agregarInfoAdicional(nombre, valor);
            }
        });
        if (correo) ND_actualizarCorreoCliente(correo);
    }

    // ─── TARIFAS IVA Y TOTALES ──────────────────────────────────────────────

    async function cargarTarifasIva() {
        try {
            const resp = await fetch(`${BASE_URL}/modulos/nota_debito/getTarifasIvaAjax`);
            const data = await resp.json();
            if (data.ok) {
                listadoTarifasIva = (data.data || []).filter(t => String(t.status) === '1' || t.status === true);
                if (!listadoTarifasIva.length) listadoTarifasIva = data.data || [];
                const sel = document.getElementById('nd_tarifa_iva');
                if (sel) {
                    // Favorito del usuario para esta serie (estrella junto a "Tarifa IVA").
                    // El <select> se puebla recién ahora (AJAX), así que se marca "selected"
                    // directamente en la opción en vez de depender de aplicarFavoritosModal,
                    // que solo funciona sobre opciones ya presentes en el DOM.
                    const estrellaIva = document.querySelector('.btn-favorito[data-target="#nd_tarifa_iva"]');
                    const favIva = (estrellaIva && typeof APP_FAVORITOS !== 'undefined' && APP_FAVORITOS[estrellaIva.dataset.campo] != null)
                        ? String(APP_FAVORITOS[estrellaIva.dataset.campo]) : null;

                    sel.innerHTML = listadoTarifasIva.map(t =>
                        `<option value="${t.id}" data-codigo="${t.codigo}" data-porcentaje="${t.porcentaje_iva}" ${favIva === String(t.id) ? 'selected' : ''}>${t.tarifa}</option>`
                    ).join('');
                    window.ND_calcTotales();
                }
            }
        } catch (e) {
            console.error('Error al cargar tarifas IVA:', e);
        }
    }

    async function cargarFormasPago() {
        try {
            const resp = await fetch(`${BASE_URL}/modulos/nota_debito/getFormasPagoAjax`);
            const data = await resp.json();
            if (data.ok) listadoFormasPago = data.data || [];
        } catch (e) {
            console.error('Error al cargar formas de pago:', e);
        }
    }

    // Selecciona en el <select> de tarifa IVA la que coincida con el primer
    // impuesto guardado de la ND (al reabrir una ND existente).
    function ND_seleccionarTarifaDesdeImpuestos(impuestos) {
        const sel = document.getElementById('nd_tarifa_iva');
        if (!sel || !impuestos || !impuestos.length) return;
        const imp = impuestos[0];
        for (const opt of sel.options) {
            if (opt.dataset.codigo === String(imp.codigo_porcentaje)) {
                sel.value = opt.value;
                return;
            }
        }
    }

    window.ND_calcTotales = () => {
        let subtotal = 0;
        document.querySelectorAll('#nd_motivos_body tr.row-motivo-nd input[name="mot_valor[]"]').forEach(inp => {
            subtotal += parseFloat(inp.value) || 0;
        });

        const sel = document.getElementById('nd_tarifa_iva');
        const opt = sel ? sel.options[sel.selectedIndex] : null;
        const porcIva = opt ? (parseFloat(opt.dataset.porcentaje) || 0) : 0;
        const nombreIva = opt ? opt.text : 'IVA';
        const valorIva = subtotal * (porcIva / 100);
        const total = subtotal + valorIva;

        const decP = window.nd_dec_p || 2;
        setEl('nd_lbl_subtotal', 'textContent', subtotal.toFixed(decP));
        setEl('nd_lbl_iva_nombre', 'textContent', nombreIva);
        setEl('nd_lbl_iva', 'textContent', valorIva.toFixed(decP));
        setEl('nd_lbl_total', 'textContent', total.toFixed(decP));
        setEl('nd_total_sin_impuestos', 'value', subtotal.toFixed(decP));
        setEl('nd_importe_total', 'value', total.toFixed(decP));
    };

    // ─── OPERACIONES CRUD ───────────────────────────────────────────────────

    window.ND_copiarCampoSri = (inputId) => {
        const input = document.getElementById(inputId);
        const val = input ? input.value.trim() : '';
        if (!val) return;
        navigator.clipboard.writeText(val).then(() => {
            const btn = input.nextElementSibling;
            if (btn) {
                const icon = btn.querySelector('i');
                if (icon) { icon.classList.replace('bi-clipboard', 'bi-clipboard-check'); btn.classList.replace('btn-outline-secondary', 'btn-outline-success'); }
                setTimeout(() => {
                    if (icon) { icon.classList.replace('bi-clipboard-check', 'bi-clipboard'); btn.classList.replace('btn-outline-success', 'btn-outline-secondary'); }
                }, 2000);
            }
        }).catch(() => { if (input) { input.select(); document.execCommand('copy'); } });
    };

    function ND_focusYError(el, mensaje) {
        try {
            const tabBtn = document.getElementById('tab-nd-principal-btn');
            if (tabBtn && typeof bootstrap !== 'undefined') bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        } catch (e) {}
        Swal.fire('Falta completar', mensaje, 'warning').then(() => {
            if (el) {
                el.focus();
                if (typeof el.select === 'function') { try { el.select(); } catch (e) {} }
            }
        });
    }

    function ND_validarObligatorios() {
        const idCliente = document.getElementById('nd_id_cliente').value;
        if (!idCliente) { ND_focusYError(document.getElementById('nd_cliente_search'), 'Debe seleccionar el cliente.'); return false; }

        const serie = document.getElementById('nd_id_punto_emision');
        if (!serie || !serie.value) { ND_focusYError(serie, 'Debe seleccionar la serie (punto de emisión).'); return false; }

        const factura = document.getElementById('nd_factura_search');
        if (!factura || !factura.value.trim()) { ND_focusYError(factura, 'Debe indicar la factura o documento a modificar.'); return false; }

        const fechaDoc = document.getElementById('nd_fecha_emision_docs_sustento');
        if (!fechaDoc || !fechaDoc.value) { ND_focusYError(fechaDoc, 'Debe indicar la fecha del documento a modificar.'); return false; }

        const rows = Array.from(motivosBody.querySelectorAll('tr.row-motivo-nd'));
        if (rows.length === 0) { ND_focusYError(null, 'Debe agregar al menos un motivo.'); return false; }
        for (const tr of rows) {
            const razon = tr.querySelector('input[name="mot_razon[]"]');
            const valor = tr.querySelector('input[name="mot_valor[]"]');
            if (!razon.value.trim()) { ND_focusYError(razon, 'La razón del motivo es obligatoria.'); return false; }
            if (!(parseFloat(valor.value) > 0)) { ND_focusYError(valor, 'El valor del motivo debe ser mayor a cero.'); return false; }
        }
        return true;
    }

    function ND_capturarMotivos() {
        const motivos = [];
        motivosBody.querySelectorAll('tr.row-motivo-nd').forEach(tr => {
            motivos.push({
                razon: tr.querySelector('input[name="mot_razon[]"]').value,
                valor: parseFloat(tr.querySelector('input[name="mot_valor[]"]').value) || 0,
            });
        });
        return motivos;
    }

    function ND_capturarImpuestos() {
        const sel = document.getElementById('nd_tarifa_iva');
        const opt = sel ? sel.options[sel.selectedIndex] : null;
        const subtotal = parseFloat(document.getElementById('nd_total_sin_impuestos').value) || 0;
        if (!opt || subtotal <= 0) return [];

        const porcIva = parseFloat(opt.dataset.porcentaje) || 0;
        const valorIva = subtotal * (porcIva / 100);
        return [{
            codigo_impuesto: '2',
            codigo_porcentaje: opt.dataset.codigo,
            tarifa: porcIva,
            base_imponible: subtotal,
            valor: valorIva,
        }];
    }

    window.ND_guardar = async () => {
        // Bloqueo: secuenciales no configurados (solo al CREAR una nueva ND).
        const _esNuevaND = !document.getElementById('nd_id').value;
        if (_esNuevaND && window.ND_SECUENCIAL_CONFIGURADO === false) {
            return Swal.fire({
                icon: 'warning',
                title: 'Secuenciales no configurados',
                html: 'No están configurados los secuenciales para esta serie (tipo de documento "Nota de débito").<br>Configúrelos en <strong>Empresa → Secuenciales</strong> antes de emitir la nota de débito.',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#f39c12',
                target: document.getElementById('modalND'),
            });
        }

        if (!ND_validarObligatorios()) return;

        const payload = {
            id: document.getElementById('nd_id').value,
            id_punto_emision: document.getElementById('nd_id_punto_emision').value,
            secuencial: document.getElementById('nd_secuencial').value,
            fecha_emision: document.getElementById('nd_fecha_emision').value,
            id_cliente: document.getElementById('nd_id_cliente').value,
            num_doc_modificado: document.getElementById('nd_factura_search').value,
            fecha_emision_docs_sustento: document.getElementById('nd_fecha_emision_docs_sustento').value,
            total_sin_impuestos: document.getElementById('nd_total_sin_impuestos').value,
            importe_total: document.getElementById('nd_importe_total').value,
            motivos: ND_capturarMotivos(),
            impuestos: ND_capturarImpuestos(),
            pagos: ND_capturarPagos(),
            info_adicional: ND_capturarInfoAdicional()
        };

        const btn = document.getElementById('btnGuardarND');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

        try {
            const resp = await fetch(`${BASE_URL}/modulos/nota_debito/guardarAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `data=${encodeURIComponent(JSON.stringify(payload))}`
            });
            const data = await resp.json();
            if (data.ok) {
                const idGuardado = parseInt(data.id) || 0;
                window.ND_eliminarRespaldo();

                window.ND_fetchSearch();
                if (idGuardado > 0 && typeof window.ND_abrirModalND === 'function') {
                    window.ND_abrirModalND({ dataset: { row: JSON.stringify({ id: idGuardado, _soloLectura: true }) } });
                }
                if (typeof window.refrescarDesdeModuloHijo === 'function') window.refrescarDesdeModuloHijo();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.mensaje || 'Guardado', showConfirmButton: false, timer: 2500, timerProgressBar: true });
            } else {
                Swal.fire('Error', data.mensaje, 'error');
            }
        } catch (e) {
            console.error('Error al guardar ND:', e);
            window.ND_guardarRespaldo(payload);
            Swal.fire('Error', 'No se pudo guardar la nota de débito. Se ha guardado un borrador local.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
        }
    };

    function ND_capturarEstado() {
        return {
            id_punto_emision: document.getElementById('nd_id_punto_emision').value,
            secuencial: document.getElementById('nd_secuencial').value,
            fecha_emision: document.getElementById('nd_fecha_emision').value,
            id_cliente: document.getElementById('nd_id_cliente').value,
            cliente_nombre: document.getElementById('nd_cliente_search').value,
            cliente_ruc: document.getElementById('nd_lbl_cliente_ruc').textContent,
            cliente_direccion: document.getElementById('nd_lbl_cliente_direccion').textContent,
            cliente_correo: document.getElementById('nd_lbl_cliente_correo').textContent,
            num_doc_modificado: document.getElementById('nd_factura_search').value,
            fecha_emision_docs_sustento: document.getElementById('nd_fecha_emision_docs_sustento').value,
            id_tarifa_iva: document.getElementById('nd_tarifa_iva').value,
            motivos: ND_capturarMotivos(),
            pagos: ND_capturarPagos(),
            info_adicional: ND_capturarInfoAdicional()
        };
    }

    function ND_autoGuardar() {
        const idActual = document.getElementById('nd_id').value;
        if (idActual) return;

        const estado = ND_capturarEstado();
        if (!estado.id_cliente && !estado.motivos.some(m => m.razon)) {
            localStorage.removeItem(window.ND_STORAGE_KEY);
            return;
        }
        localStorage.setItem(window.ND_STORAGE_KEY, JSON.stringify({ data: estado, timestamp: new Date().getTime() }));
    }

    window.ND_registrarAutoGuardado = () => {
        const modal = document.getElementById('modalND');
        if (!modal) return;
        const auto = debounce(ND_autoGuardar, 1000);
        modal.addEventListener('input', () => {
            const idActual = document.getElementById('nd_id').value;
            if (!idActual) auto();
        });
        modal.addEventListener('change', () => {
            const idActual = document.getElementById('nd_id').value;
            if (!idActual) auto();
        });
    };

    window.ND_guardarRespaldo = (data) => {
        localStorage.setItem(window.ND_STORAGE_KEY, JSON.stringify(data));
    };

    window.ND_eliminarRespaldo = () => {
        localStorage.removeItem(window.ND_STORAGE_KEY);
    };

    window.ND_restaurarRespaldo = (data) => {
        if (!data) return;

        document.getElementById('nd_id_punto_emision').value = data.id_punto_emision || '';
        document.getElementById('nd_secuencial').value = data.secuencial || '';
        document.getElementById('nd_fecha_emision').value = data.fecha_emision || '';

        setEl('nd_id_cliente', 'value', data.id_cliente || '');
        setEl('nd_cliente_search', 'value', data.cliente_nombre || '');
        setEl('nd_lbl_cliente_ruc', 'textContent', data.cliente_ruc || '');
        setEl('nd_lbl_cliente_direccion', 'textContent', data.cliente_direccion || '');
        setEl('nd_lbl_cliente_correo', 'textContent', data.cliente_correo || '');
        const infoCli = document.getElementById('nd_info_cliente');
        if (data.id_cliente && infoCli) infoCli.classList.remove('d-none');

        ND_setFacturaHabilitada(!!data.id_cliente);
        setEl('nd_factura_search', 'value', data.num_doc_modificado || '');
        document.getElementById('nd_fecha_emision_docs_sustento').value = (data.fecha_emision_docs_sustento || '').split(' ')[0].split('T')[0];
        if (data.id_tarifa_iva) setEl('nd_tarifa_iva', 'value', data.id_tarifa_iva);

        if (motivosBody) {
            motivosBody.innerHTML = '';
            (data.motivos || []).forEach(m => window.ND_agregarMotivo(m.razon, m.valor));
            if (!data.motivos || !data.motivos.length) window.ND_agregarMotivo();
        }
        if (pagosBody) {
            pagosBody.innerHTML = '';
            (data.pagos || []).forEach(p => window.ND_agregarPago(p.forma_pago, p.total, p.plazo, p.unidad_tiempo));
        }

        ND_renderInfoAdicional(data.info_adicional);

        window.ND_calcTotales();
        if (typeof mostrarToast === 'function') mostrarToast('Borrador de ND restaurado.', 'info');
    };

    function ND_pintarMensajesSri(estado) {
        const badge = document.getElementById('nd-sri-badge-estado');
        if (!badge) return;
        const map = {
            'autorizado':       ['bg-success bg-opacity-10 text-success border-success', 'Autorizado'],
            'no_autorizado':    ['bg-danger bg-opacity-10 text-danger border-danger',   'No autorizado'],
            'devuelta':         ['bg-danger bg-opacity-10 text-danger border-danger',   'Devuelta'],
            'en_procesamiento': ['bg-warning bg-opacity-10 text-warning border-warning','En procesamiento'],
            'recibida':         ['bg-info bg-opacity-10 text-info border-info',         'Recibida'],
            'error':            ['bg-danger bg-opacity-10 text-danger border-danger',   'Error'],
        };
        const [cls, lbl] = map[estado] || ['bg-secondary bg-opacity-10 text-secondary border-secondary', 'Sin enviar'];
        badge.className = `badge ${cls} border border-opacity-25 px-2`;
        badge.textContent = lbl;
    }

    function ND_irPestanaSri() {
        const tabBtn = document.getElementById('tab-nd-sri-btn');
        if (tabBtn && typeof bootstrap !== 'undefined') {
            try { bootstrap.Tab.getOrCreateInstance(tabBtn).show(); } catch (e) {}
        }
    }

    window.ND_enviarSRI = async () => {
        const id = document.getElementById('nd_id').value;
        if (!id) return;

        Swal.fire({
            title: 'Enviar al SRI',
            text: '¿Está seguro de enviar este comprobante al SRI para su autorización?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, enviar',
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Enviando al SRI...',
                    html: '<div class="spinner-border text-primary" role="status"></div><br><small class="text-muted mt-2 d-block">Firmando y enviando comprobante…</small>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                });
                try {
                    const resp = await fetch(`${BASE_URL}/modulos/nota_debito/autorizarSRIAjax`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `id=${id}`
                    });
                    const data = await resp.json();

                    const estadoSri = (data.estado || (data.ok ? 'autorizado' : 'error')).toLowerCase();
                    ND_pintarMensajesSri(estadoSri);
                    ndCargarHistorialSri(id);

                    if (data.ok) {
                        toggleBotonesAccion(true, 'autorizado');
                        ND_setModoLectura(true);
                        window.ND_fetchSearch();
                        Swal.fire('Éxito', 'Comprobante autorizado correctamente.', 'success');
                    } else {
                        ND_irPestanaSri();
                        const esc = (s) => String(s ?? '').replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
                        let html = `<div class="text-start small">${esc(data.mensaje || 'Error desconocido')}</div>`;
                        if (data.errores && data.errores.length > 0) {
                            html += '<ul class="text-start small mt-2 mb-0 ps-3">';
                            data.errores.forEach(e => {
                                if (typeof e === 'string') { html += `<li>${esc(e)}</li>`; return; }
                                const id   = e.id ? `[${esc(e.id)}] ` : '';
                                const mens = esc(e.mensaje || '');
                                const info = e.info ? `<br><em class="text-muted">${esc(e.info)}</em>` : '';
                                html += `<li>${id}${mens}${info}</li>`;
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
            }
        });
    };

    window.ND_exportarPdf = () => {
        const id = document.getElementById('nd_id').value;
        if (id) window.open(`${BASE_URL}/modulos/nota_debito/exportPdfDoc?id=${id}`, '_blank');
    };

    window.ND_exportarXml = () => {
        const id = document.getElementById('nd_id').value;
        if (id) window.location.href = `${BASE_URL}/modulos/nota_debito/exportXmlDoc?id=${id}`;
    };

    window.ND_exportarExcel = () => {
        const id = document.getElementById('nd_id').value;
        if (id) window.open(`${BASE_URL}/modulos/nota_debito/exportExcelDoc?id=${id}`, '_blank');
    };

    window.ND_enviarPorCorreo = async () => {
        const id = document.getElementById('nd_id').value;
        if (!id) return;

        const modalEl = document.getElementById('modalND');
        const correoInput  = document.getElementById('nd-sri-correo-cliente');
        const correoActual = correoInput ? (correoInput.value || '').trim() : '';

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
                if (!value.trim()) return 'Debes ingresar al menos un correo válido!';
            }
        });
        if (!isConfirmed) return;

        Swal.fire({
            title: 'Enviando correo...',
            text: 'Por favor espera',
            allowOutsideClick: false,
            target: modalEl,
            didOpen: () => Swal.showLoading()
        });

        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('correos', correos);

            const resp = await fetch(`${BASE_URL}/modulos/nota_debito/enviarCorreoAjax`, {
                method: 'POST',
                body: fd
            });
            const data = await resp.json();

            if (data.ok) {
                Swal.fire({ icon: 'success', title: '¡Enviado!', text: data.mensaje, timer: 2500, showConfirmButton: false, target: modalEl });
                window.ND_fetchSearch();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje || 'No se pudo enviar el correo.', target: modalEl });
            }
        } catch (e) {
            console.error('Error Correo:', e);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión al enviar el correo.', target: modalEl });
        }
    };

    window.ND_abrirModalClienteCrear = () => {
        if (window.ClienteService && typeof window.ClienteService.abrirModalNuevo === 'function') {
            window.ClienteService.abrirModalNuevo();
        } else {
            Swal.fire('Información', 'El módulo de creación rápida de clientes no está disponible.', 'info');
        }
    };

    window.ND_eliminar = async () => {
        const id = document.getElementById('nd_id').value;
        if (!id) return;

        const result = await Swal.fire({
            title: '¿Eliminar Borrador?',
            text: 'Esta acción eliminará permanentemente la nota de débito.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            confirmButtonColor: '#d33'
        });

        if (result.isConfirmed) {
            try {
                const resp = await fetch(`${BASE_URL}/modulos/nota_debito/eliminarAjax`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}`
                });
                const data = await resp.json();
                if (data.ok) {
                    Swal.fire('Eliminado', data.mensaje, 'success');
                    modalND.hide();
                    window.ND_fetchSearch();
                    if (typeof window.refrescarDesdeModuloHijo === 'function') window.refrescarDesdeModuloHijo();
                } else {
                    Swal.fire('Error', data.mensaje, 'error');
                }
            } catch (e) {
                console.error('Error Eliminar:', e);
            }
        }
    };

    window.ND_anular = async () => {
        const id = document.getElementById('nd_id').value;
        if (!id) return;

        const result = await Swal.fire({
            title: '¿Anular Nota de Débito?',
            text: 'Esta acción anulará el comprobante autorizado. No se puede revertir.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-slash-circle me-2"></i>Sí, anular',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        });

        if (!result.isConfirmed) return;

        const btn = document.getElementById('btnAnularND');
        const btnOrigHtml = btn?.innerHTML;
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Anulando...'; }

        try {
            const resp = await fetch(`${BASE_URL}/modulos/nota_debito/anularAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}`
            });
            const data = await resp.json();
            if (data.ok) {
                modalND.hide();
                Swal.fire({ icon: 'success', title: 'Anulada', text: data.mensaje, timer: 3000 });
                window.ND_fetchSearch();
                if (typeof window.refrescarDesdeModuloHijo === 'function') window.refrescarDesdeModuloHijo();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje, confirmButtonColor: '#d33' });
            }
        } catch (e) {
            console.error('Error Anular:', e);
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'Intente nuevamente.', confirmButtonColor: '#d33' });
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = btnOrigHtml; }
        }
    };

    // ─── HISTORIAL SRI ───────────────────────────────────────────────────────

    async function ndCargarHistorialSri(id) {
        const tbody = document.getElementById('nd-sri-tbody-historial');
        if (!tbody || !id) return;

        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-2 text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</td></tr>';

        try {
            const resp = await fetch(`${BASE_URL}/modulos/nota_debito/getHistorialSriAjax?id=${id}&tipo=nota_debito`);
            const json = await resp.json();

            if (!json.ok || !json.data || !json.data.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted small">Sin historial de envíos.</td></tr>';
                return;
            }

            const accionMap = {
                'enviando':         ['bg-primary',  'bi-cloud-arrow-up',       'Enviando'],
                'recibida':         ['bg-info',     'bi-check-circle',         'Recibida'],
                'devuelta':         ['bg-danger',   'bi-x-circle',             'Devuelta'],
                'autorizado':       ['bg-success',  'bi-patch-check-fill',     'Autorizado'],
                'no_autorizado':    ['bg-danger',   'bi-patch-minus',          'No autorizado'],
                'en_procesamiento': ['bg-warning',  'bi-hourglass-split',      'En proceso'],
                'error':            ['bg-danger',   'bi-exclamation-triangle', 'Error'],
            };

            tbody.innerHTML = json.data.map(row => {
                const [bgCls, icon, lbl] = accionMap[row.accion] ?? ['bg-secondary', 'bi-question', row.accion];
                const esPruebas   = row.tipo_ambiente === '1';
                const ambienteLbl = esPruebas
                    ? '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" style="font-size:0.65rem;">PRUEBAS</span>'
                    : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:0.65rem;">PRODUCCIÓN</span>';

                let detalle = row.mensaje || '';
                if (row.detalle_json) {
                    try {
                        const errs = JSON.parse(row.detalle_json);
                        if (Array.isArray(errs) && errs.length) {
                            detalle += '<ul class="mb-0 ps-3 mt-1" style="font-size:0.7rem;">';
                            errs.forEach(e => {
                                detalle += `<li><strong>[${e.tipo||e.id||''}]</strong> ${e.mensaje||''} ${e.info ? '<br><em class="text-muted">'+e.info+'</em>' : ''}</li>`;
                            });
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
            console.error('Error al cargar historial SRI:', e);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-2 text-danger small">Error al cargar historial.</td></tr>';
        }
    }

    // ─── HELPERS ────────────────────────────────────────────────────────────

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

})();

// ─── Pestaña Asiento Contable (vista previa reutilizable) ───────────────────────
(function () {
    let _ndAsientoTab = null;
    function ndAsientoTab() {
        if (!_ndAsientoTab && typeof window.crearAsientoTab === 'function') {
            _ndAsientoTab = window.crearAsientoTab({
                tbodyId: 'nd-asiento-tbody',
                debeId:  'nd-asiento-debe',
                haberId: 'nd-asiento-haber',
                difId:   'nd-asiento-dif',
                badgeId: 'nd-asiento-badge',
                countId: 'nd-asiento-count',
                statusId: 'nd-asiento-status',
                previewUrl: `${BASE_URL}/modulos/nota_debito/getAsientoSugeridoAjax`,
                cuentasUrl: `${BASE_URL}/modulos/plan-cuentas/searchAjaxCuentas`
            });
            const addBtn = document.getElementById('nd-asiento-add');
            if (addBtn) addBtn.addEventListener('click', () => _ndAsientoTab.agregarLinea());
        }
        return _ndAsientoTab;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const btnTab = document.getElementById('tab-nd-contable-btn');
        if (btnTab) {
            btnTab.addEventListener('shown.bs.tab', function () {
                const tab = ndAsientoTab();
                const idEl = document.getElementById('nd_id');
                if (tab) tab.cargar(idEl ? idEl.value : 0);
            });
        }
    });
})();

// ─── Pestaña Factura relacionada ─────────────────────────────────────────────
(function () {
    async function ndCargarFacturaRelacionada(id) {
        const loading  = document.getElementById('nd-factura-relacionada-loading');
        const contenido = document.getElementById('nd-factura-relacionada-contenido');
        if (!loading || !contenido) return;

        id = parseInt(id) || 0;
        if (!id) {
            loading.innerHTML = '<i class="bi bi-info-circle me-1"></i> Guarda la nota de débito para ver el detalle de la factura relacionada.';
            loading.classList.remove('d-none');
            contenido.classList.add('d-none');
            return;
        }

        loading.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Cargando factura relacionada...';
        loading.classList.remove('d-none');
        contenido.classList.add('d-none');

        try {
            const resp = await fetch(`${BASE_URL}/modulos/nota_debito/getFacturaRelacionadaAjax?id=${id}`);
            const json = await resp.json();

            if (!json.ok) {
                loading.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> ' + (json.mensaje || 'No se pudo cargar la factura relacionada.');
                return;
            }

            const f = json.factura || {};
            const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };

            const numero = `${(f.establecimiento || '').toString().padStart(3, '0')}-${(f.punto_emision || '').toString().padStart(3, '0')}-${(f.secuencial || '').toString().padStart(9, '0')}`;
            setTxt('ndf-numero', numero);
            setTxt('ndf-fecha', f.fecha_emision ? String(f.fecha_emision).split(' ')[0].split('T')[0] : '—');
            setTxt('ndf-cliente', f.cliente_nombre || '—');
            setTxt('ndf-subtotal', parseFloat(f.total_sin_impuestos || 0).toFixed(2));
            setTxt('ndf-total', parseFloat(f.importe_total || 0).toFixed(2));
            setTxt('ndf-cobrado', parseFloat(f.total_cobrado || 0).toFixed(2));
            setTxt('ndf-saldo', parseFloat(json.saldo_pendiente || 0).toFixed(2));

            const estado = (f.estado || '').toLowerCase();
            const estadoClass = (estado === 'autorizado' || estado === 'aprobado') ? 'bg-success' :
                (estado === 'anulado') ? 'bg-danger' : 'bg-secondary';
            const elEstado = document.getElementById('ndf-estado');
            if (elEstado) elEstado.innerHTML = `<span class="badge ${estadoClass} bg-opacity-10 text-${estadoClass.replace('bg-', '')} border border-${estadoClass.replace('bg-', '')} border-opacity-25">${estado.toUpperCase()}</span>`;

            const tbody = document.getElementById('ndf-tbody-detalles');
            if (tbody) {
                const detalles = json.detalles || [];
                if (!detalles.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Sin líneas de detalle.</td></tr>';
                } else {
                    tbody.innerHTML = detalles.map(d => `
                        <tr>
                            <td class="ps-3">${d.descripcion || ''}</td>
                            <td class="text-end">${parseFloat(d.cantidad || 0)}</td>
                            <td class="text-end">${parseFloat(d.precio_unitario || 0).toFixed(2)}</td>
                            <td class="text-end pe-3">${parseFloat(d.precio_total_sin_impuesto || 0).toFixed(2)}</td>
                        </tr>
                    `).join('');
                }
            }

            loading.classList.add('d-none');
            contenido.classList.remove('d-none');
        } catch (e) {
            console.error('Error al cargar factura relacionada:', e);
            loading.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Error al cargar la factura relacionada.';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const btnTab = document.getElementById('tab-nd-factura-btn');
        if (btnTab) {
            btnTab.addEventListener('shown.bs.tab', function () {
                const idEl = document.getElementById('nd_id');
                ndCargarFacturaRelacionada(idEl ? idEl.value : 0);
            });
        }
    });
})();
