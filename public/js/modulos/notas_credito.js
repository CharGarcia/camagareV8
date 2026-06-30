(function () {
    'use strict';

    // Variables de estado
    let modalNC;
    let formNC;
    let tableBody;
    
    let NC_idActual = null;
    let NC_estadoActual = 'borrador';
    let listadoTarifasIva = [];

    // Inicialización al cargar el script
    document.addEventListener('DOMContentLoaded', () => {
        console.log('Notas de Crédito: DOM listo, inicializando...');
        initModal();
        cargarTarifasIva();
        
        // El ordenamiento inicial proviene de la preferencia del usuario que el
        // controlador ya aplicó (window.NC_ORDEN_COL / NC_ORDEN_DIR inyectados en
        // la vista). Solo se usa el default si la vista no los definió.
        if (!window.currentSort) window.currentSort = window.NC_ORDEN_COL || 'fecha_emision';
        if (!window.currentDir)  window.currentDir  = window.NC_ORDEN_DIR || 'DESC';
    });

    // ─── FUNCIONES DE LISTADO (INDEX) ──────────────────────────────────────────

    window.NC_cambiarPaginaAjax = (page) => {
        window.NC_fetchSearch(page);
    };

    window.NC_buscarAjax = (e) => {
        if (e) e.preventDefault();
        window.NC_fetchSearch(1);
    };

    window.NC_ordenar = (col) => {
        const dir = (window.currentSort === col && window.currentDir === 'ASC') ? 'DESC' : 'ASC';
        window.currentSort = col;
        window.currentDir = dir;

        // Guardar preferencia de ordenamiento en la vista (__vista__), que es lo
        // que lee el controlador (getPreferenciasVista → __ordenCol__/__ordenDir__).
        if (typeof window.guardarOrdenacionVista === 'function') {
            window.guardarOrdenacionVista('notas_credito', col, dir);
        }

        window.NC_fetchSearch(1);
    };

    window.NC_fetchSearch = async (page = 1) => {
        const buscar = document.getElementById('buscarNC')?.value || '';
        const sort   = window.currentSort || 'fecha_emision';
        const dir    = window.currentDir  || 'DESC';
        const url = `${BASE_URL}/modulos/notas_credito/searchAjax?b=${encodeURIComponent(buscar)}&page=${page}&sort=${encodeURIComponent(sort)}&dir=${encodeURIComponent(dir)}`;

        try {
            const resp = await fetch(url);
            if (!resp.ok) return;
            const data = await resp.json();
            if (!data.ok) return;

            const tbody = document.getElementById('nc-table-body');
            if (tbody) tbody.innerHTML = data.rows ?? '';
            const pg = document.getElementById('nc-pagination');
            if (pg) pg.innerHTML = data.pagination ?? '';
            const pgInfo = document.getElementById('nc-pagination-info');
            if (pgInfo) pgInfo.textContent = data.info ?? '';

            NC_actualizarIconosOrden(sort, dir);
        } catch (e) {
            console.error('Error al buscar NC:', e);
        }
    };

    // Refleja la columna/dirección activa en los íconos del encabezado (en vivo).
    function NC_actualizarIconosOrden(sort, dir) {
        document.querySelectorAll('th.sortable-header').forEach(th => {
            const icon = th.querySelector('i.bi');
            if (!icon) return;
            const m = (th.getAttribute('onclick') || '').match(/NC_ordenar\('([^']+)'\)/);
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
        if (!formNC) formNC = document.getElementById('formNC');
        if (!tableBody) tableBody = document.getElementById('nc_detalles_body');

        if (modalNC) return true;
        
        const modalEl = document.getElementById('modalNC');
        if (modalEl && typeof bootstrap !== 'undefined') {
            modalNC = new bootstrap.Modal(modalEl);
            console.log('Modal NC inicializado con éxito');

            // Enter en Motivo → pasar el cursor a Cliente.
            const inpMotivo = document.getElementById('nc_motivo');
            if (inpMotivo) {
                inpMotivo.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const inpCli = document.getElementById('nc_cliente_search');
                        if (inpCli) inpCli.focus();
                    }
                });
            }
            return true;
        }
        
        if (!modalEl) console.error('Elemento #modalNC no encontrado en el DOM');
        if (typeof bootstrap === 'undefined') console.error('Bootstrap no está definido');
        
        return false;
    }

    window.NC_abrirModalNuevo = () => {
        try {
            if (!initModal()) return;
            NC_idActual = null;
            
            // Verificar borrador antes de abrir
            const borradorRaw = localStorage.getItem(window.NC_STORAGE_KEY);
            if (borradorRaw) {
                const borrador = JSON.parse(borradorRaw);
                const divAviso = document.createElement('div');
                divAviso.id = 'nc-borrador-aviso';
                divAviso.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;';
                const clienteName = borrador.cliente_nombre || 'desconocido';
                divAviso.innerHTML = `
                    <div class="bg-white rounded-3 shadow-lg p-4" style="max-width:420px;width:90%;">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-4"></i>
                            <h6 class="fw-bold mb-0">Nota de crédito sin guardar</h6>
                        </div>
                        <p class="small text-muted mb-4">Hay una nota de crédito en borrador del cliente <strong>${clienteName}</strong> que no fue guardada. ¿Qué desea hacer?</p>
                        <div class="d-flex gap-2 justify-content-end">
                            <button class="btn btn-sm btn-outline-secondary" id="nc-aviso-nueva">
                                <i class="bi bi-file-earmark-plus me-1"></i> Nueva nota
                            </button>
                            <button class="btn btn-sm btn-primary" id="nc-aviso-restaurar">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Cargar borrador
                            </button>
                        </div>
                    </div>`;
                document.body.appendChild(divAviso);
                document.getElementById('nc-aviso-restaurar').onclick = () => {
                    divAviso.remove();
                    NC_resetearYMostrar(borrador);
                };
                document.getElementById('nc-aviso-nueva').onclick = () => {
                    window.NC_eliminarRespaldo();
                    divAviso.remove();
                    NC_resetearYMostrar();
                };
                return;
            }

            NC_resetearYMostrar();

        } catch (err) {
            console.error('Error crítico al abrir modal nuevo:', err);
        }
    };

    function NC_resetearYMostrar(borrador = null) {
        try {
            if (formNC) formNC.reset();
            const idInput = document.getElementById('nc_id');
            if (idInput) idInput.value = '';
            
            document.getElementById('modalNCTitulo').innerHTML = '<i class="bi bi-file-earmark-minus text-primary me-2"></i>Nueva Nota de Crédito';
            
            // Limpiar tabla
            if (tableBody) tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Seleccione una factura para cargar los detalles.</td></tr>';
            
            // Limpiar datos de cliente/factura
            setEl('nc_info_factura_modificada', 'innerHTML', '');
            setEl('nc_lbl_cliente_ruc', 'textContent', '');
            setEl('nc_lbl_cliente_direccion', 'textContent', '');
            setEl('nc_lbl_cliente_correo', 'textContent', '');
            setEl('nc_factura_search', 'value', '');
            setEl('nc_fecha_emision_docs_sustento', 'value', '');
            setEl('nc_id_cliente', 'value', '');
            const infoCli = document.getElementById('nc_info_cliente');
            if (infoCli) infoCli.classList.add('d-none');
            NC_limpiarInfoAdicional();
            // En una NC nueva, el documento a modificar arranca deshabilitado
            // hasta que se seleccione un cliente.
            NC_setFacturaHabilitada(false);
            
            window.NC_cargarSecuencial();
            
            // Resetear sección SRI
            setEl('nc-sri-clave-acceso',           'value', '');
            setEl('nc-sri-autorizacion',            'value', '');
            setEl('nc-sri-fecha-autorizacion',      'value', '');
            setEl('nc-sri-numero-documento',        'value', '');
            setEl('nc-sri-identificacion-cliente',  'value', '');
            setEl('nc-sri-correo-cliente',          'value', '');
            window.NC_FECHA_EMISION = null;
            window.NC_CLIENTE_RUC   = '';
            const tbodySri = document.getElementById('nc-sri-tbody-historial');
            if (tbodySri) tbodySri.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Sin historial de envíos.</td></tr>';

            modalNC.show();

            document.getElementById('modalNC').addEventListener('shown.bs.modal', function onShown() {
                if (borrador) window.NC_restaurarRespaldo(borrador);
                // NC nueva: el cursor inicia en Motivo (luego pasa a Cliente con Enter).
                const inpMotivo = document.getElementById('nc_motivo');
                if (inpMotivo) inpMotivo.focus();
                this.removeEventListener('shown.bs.modal', onShown);
            });

            window.NC_registrarAutoGuardado();

            if (typeof window.aplicarFavoritosModal === 'function') window.aplicarFavoritosModal('#modalNC');

            setTimeout(() => {
                try { calcTotales(); } catch (e) { }
            }, 100);
        } catch(e) { console.error(e); }
    }

    window.NC_abrirModalNC = async (row) => {
        try {
            console.log('Abriendo modal edición...');
            if (!initModal()) return;
            
            const data = JSON.parse(row.dataset.row);
            NC_idActual = data.id;
            NC_estadoActual = 'edicion';
            document.getElementById('nc_id').value = data.id;
            const est = String(data.establecimiento || '000').padStart(3, '0');
            const pto = String(data.punto_emision || '000').padStart(3, '0');
            const sec = String(data.secuencial || '0').padStart(9, '0');
            const num = `${est}-${pto}-${sec}`;
            const cliente = data.cliente_nombre ? ` - ${data.cliente_nombre}` : '';

            document.getElementById('modalNCTitulo').innerHTML = `<i class="bi bi-file-earmark-minus text-primary me-2"></i>Nota de Crédito ${num}${cliente}`;
            
            // Cargar datos cabecera
            document.getElementById('nc_fecha_emision').value = data.fecha_emision;
            document.getElementById('nc_id_punto_emision').value = data.id_punto_emision;
            document.getElementById('nc_secuencial').value = String(data.secuencial).padStart(9, '0');
            document.getElementById('nc_id_cliente').value = data.id_cliente;
            document.getElementById('nc_cliente_search').value = data.cliente_nombre;
            // En edición el documento a modificar siempre está habilitado.
            NC_setFacturaHabilitada(true);
            const fechaSustento = (data.fecha_emision_docs_sustento || '').split(' ')[0].split('T')[0];
            document.getElementById('nc_factura_search').value = data.num_doc_modificado || '';
            document.getElementById('nc_fecha_emision_docs_sustento').value = fechaSustento;
            document.getElementById('nc_motivo').value = data.motivo;

            document.getElementById('nc_info_factura_modificada').innerHTML = `
                <div class="d-flex gap-3 flex-wrap">
                    <span><i class="bi bi-file-earmark-text me-1"></i> ${data.num_doc_modificado || '—'}</span>
                    <span><i class="bi bi-calendar me-1"></i> ${fechaSustento || '—'}</span>
                </div>
            `;

            // Info Auditoría (Solo si existen los elementos)
            setEl('nc_info_created_by', 'textContent', data.usuario_nombre || '—');
            setEl('nc_info_created_at', 'textContent', data.created_at || '—');
            setEl('nc_info_updated_by', 'textContent', data.updated_by_nombre || '—');
            setEl('nc_info_updated_at', 'textContent', data.updated_at || '—');
            
            actualizarBadgeEstado(data.estado);
            toggleBotonesAccion(true, data.estado);

            // Cargar detalles y datos completos del cliente (incluye cliente_email)
            try {
                const resp = await fetch(`${BASE_URL}/modulos/notas_credito/getNcAjax?id=${data.id}`);
                const result = await resp.json();
                if (result.ok) {
                    renderDetalles(result.detalles);
                    NC_renderInfoAdicional(result.info_adicional);
                    calcTotales();
                    // cliente_email solo viene en getPorId (no en el listado), lo tomamos aquí
                    const cab = result.cabecera;
                    const elCorreo = document.getElementById('nc-sri-correo-cliente');
                    if (elCorreo) elCorreo.value = cab.cliente_email || '';
                    // Actualizar RUC también por si acaso
                    const elIdentif = document.getElementById('nc-sri-identificacion-cliente');
                    if (elIdentif && !elIdentif.value) elIdentif.value = cab.cliente_ruc || '';
                    window.NC_CLIENTE_RUC = (cab.cliente_ruc || '').trim();
                }
            } catch (e) {
                console.error('Error al cargar datos NC:', e);
            }

            // Llenar campos SRI
            const elClaveAcceso  = document.getElementById('nc-sri-clave-acceso');
            const elAmbiente     = document.getElementById('nc-sri-ambiente');
            const elTipoEmision  = document.getElementById('nc-sri-tipo-emision');
            const elAutorizacion = document.getElementById('nc-sri-autorizacion');
            const elFechaAut     = document.getElementById('nc-sri-fecha-autorizacion');
            const elBadge        = document.getElementById('nc-sri-badge-estado');

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

            // Número de documento
            const elNroDoc = document.getElementById('nc-sri-numero-documento');
            if (elNroDoc) {
                const est = String(data.establecimiento  || '').padStart(3, '0');
                const pto = String(data.punto_emision    || '').padStart(3, '0');
                const sec = String(data.secuencial       || '').padStart(9, '0');
                elNroDoc.value = `${est}-${pto}-${sec}`;
            }
            const elIdentif = document.getElementById('nc-sri-identificacion-cliente');
            if (elIdentif) elIdentif.value = data.cliente_ruc   || '';
            const elCorreo  = document.getElementById('nc-sri-correo-cliente');
            if (elCorreo)   elCorreo.value  = data.cliente_email || '';

            // Guardar globales para validaciones de anulación
            window.NC_FECHA_EMISION  = (data.fecha_emision || '').split(' ')[0].split('T')[0] || null;
            window.NC_CLIENTE_RUC    = (data.cliente_ruc  || '').trim();

            // Badge de estado SRI
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

            // Cargar historial SRI
            ncCargarHistorialSri(data.id);

            window.NC_ID_ACTIVO = data.id;

            // Aplicar favoritos (estrellas)
            if (typeof window.aplicarFavoritosModal === 'function') window.aplicarFavoritosModal('#modalNC');

            modalNC.show();
        } catch (err) {
            console.error('Error al abrir modal edición:', err);
            Swal.fire('Error', 'Ocurrió un error al cargar la nota de crédito.', 'error');
        }
    };

    function toggleBotonesAccion(habilitar, estado = 'borrador') {
        const esAutorizado = (estado === 'autorizado');
        const esAnulado = (estado === 'anulado');
        const esBorrador = (estado === 'borrador');

        document.getElementById('nc-btn-sri').disabled = !habilitar || esAutorizado || esAnulado;
        document.getElementById('nc-btn-pdf').disabled = !habilitar;
        document.getElementById('nc-btn-xml').disabled = !habilitar;
        document.getElementById('nc-btn-correo').disabled = !habilitar || !esAutorizado;
        document.getElementById('btnGuardarNC').disabled = esAutorizado || esAnulado;

        // Botones footer (Eliminar/Anular)
        const btnEliminar = document.getElementById('btnEliminarNC');
        const btnAnular = document.getElementById('btnAnularNC');
        
        if (btnEliminar) btnEliminar.classList.toggle('d-none', !habilitar || !esBorrador);
        if (btnAnular) btnAnular.classList.toggle('d-none', !habilitar || !esAutorizado);
    }

    function setEl(id, prop, val) {
        const el = document.getElementById(id);
        if (el) el[prop] = val;
    }

    function resetInfoAuditoria() {
        setEl('nc_info_created_by', 'textContent', '—');
        setEl('nc_info_created_at', 'textContent', '—');
        setEl('nc_info_updated_by', 'textContent', '—');
        setEl('nc_info_updated_at', 'textContent', '—');
        setEl('nc_info_factura_modificada', 'innerHTML', '');
        setEl('nc_estado_badge', 'innerHTML', '');
    }

    function actualizarBadgeEstado(estado) {
        const badge = document.getElementById('nc_estado_badge');
        if (!badge) return;
        const badgeClass = matchEstadoClass(estado);
        badge.innerHTML = `<span class="badge ${badgeClass} bg-opacity-10 border border-opacity-25">${estado.toUpperCase()}</span>`;
    }

    function matchEstadoClass(estado) {
        return {
            'autorizado': 'bg-success text-success border-success',
            'anulado': 'bg-danger text-danger border-danger',
            'borrador': 'bg-secondary text-secondary border-secondary',
        }[estado] || 'bg-primary text-primary border-primary';
    }

    window.NC_cargarSecuencial = () => {
        const idPt = document.getElementById('nc_id_punto_emision').value;
        if (!idPt) return;

        const url = `${BASE_URL}/modulos/notas_credito/getSecuencialAjax?id_punto=${idPt}`;
        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    const padSec = String(data.secuencial).padStart(9, '0');
                    document.getElementById('nc_secuencial').value = padSec;
                }
            });
    };

    // ─── AUTOCOMPLETE CLIENTES ──────────────────────────────────────────────

    const searchCliente = document.getElementById('nc_cliente_search');
    const dropdownCliente = document.getElementById('nc_cliente_dropdown');

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
                    <a href="#" class="list-group-item list-group-item-action py-2" onclick="window.NC_seleccionarCliente(${JSON.stringify(c).replace(/"/g, '&quot;')})">
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

    // Enter en la fecha del documento → saltar a la descripción del primer detalle.
    const inpFechaDoc = document.getElementById('nc_fecha_emision_docs_sustento');
    if (inpFechaDoc) {
        inpFechaDoc.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const body = document.getElementById('nc_detalles_body');
            const primeraDesc = body && body.querySelector('input[name="det_descripcion[]"]');
            if (primeraDesc) primeraDesc.focus();
        });
    }

    // Habilita/inhabilita la sección de "documento a modificar" según haya cliente.
    function NC_setFacturaHabilitada(habilitar) {
        const inpFactura = document.getElementById('nc_factura_search');
        const inpFecha   = document.getElementById('nc_fecha_emision_docs_sustento');
        if (inpFactura) {
            inpFactura.disabled = !habilitar;
            inpFactura.placeholder = habilitar
                ? 'Buscar factura del cliente o escribir el número...'
                : 'Seleccione un cliente primero...';
        }
        if (inpFecha) inpFecha.disabled = !habilitar;
    }

    window.NC_seleccionarCliente = (c, opciones = {}) => {
        if (c.identificacion === '9999999999999') {
            Swal.fire('Atención', 'No se puede emitir una Nota de Crédito a Consumidor Final.', 'warning');
            document.getElementById('nc_cliente_search').value = '';
            document.getElementById('nc_id_cliente').value = '';
            return;
        }
        document.getElementById('nc_id_cliente').value = c.id;
        searchCliente.value = c.nombre;
        if (dropdownCliente) dropdownCliente.classList.add('d-none');

        // Info detallada del cliente
        setEl('nc_lbl_cliente_ruc', 'textContent', c.identificacion || '');
        setEl('nc_lbl_cliente_direccion', 'textContent', c.direccion || '');
        setEl('nc_lbl_cliente_correo', 'textContent', c.email || '');
        const infoCli = document.getElementById('nc_info_cliente');
        if (infoCli) infoCli.classList.remove('d-none');
        window.NC_CLIENTE_RUC = (c.identificacion || '').trim();

        // Correo del cliente como información adicional (fila fija)
        NC_actualizarCorreoCliente(c.email || '');

        // Habilitar el documento a modificar
        NC_setFacturaHabilitada(true);

        // Si el cambio viene de elegir un cliente (no al cargar una factura),
        // limpiar el documento previo para evitar inconsistencias.
        if (!opciones.conservarDoc) {
            const inpFactura = document.getElementById('nc_factura_search');
            const inpFecha   = document.getElementById('nc_fecha_emision_docs_sustento');
            if (inpFactura) inpFactura.value = '';
            if (inpFecha)   inpFecha.value = '';
            setEl('nc_info_factura_modificada', 'innerHTML', '');
            // Aún no hay factura del sistema cargada: dejar la grilla con una
            // línea vacía lista para ingresar manualmente.
            NC_grillaListaParaIngresar();
            // Tras elegir el cliente, llevar el cursor a la factura.
            if (inpFactura) inpFactura.focus();
        }
    };

    // Deja la grilla de detalles con una sola línea vacía lista para capturar.
    function NC_grillaListaParaIngresar() {
        if (!tableBody) return;
        NC_limpiarDropdownsProducto();
        tableBody.innerHTML = '';
        agregarFila();
        calcTotales();
    }

    // ─── AUTOCOMPLETE FACTURAS ─────────────────────────────────────────────

    const searchFactura = document.getElementById('nc_factura_search');
    const dropdownFactura = document.getElementById('nc_factura_dropdown');

    // Normaliza una fecha (puede venir como 'YYYY-MM-DD HH:MM:SS' o ISO) a 'YYYY-MM-DD'.
    function NC_fechaSoloDia(f) {
        if (!f) return '';
        return String(f).split(' ')[0].split('T')[0];
    }

    // Máscara de comprobante 000-000-000000000 (3-3-9) para entrada manual.
    function NC_aplicarMascaraNroDoc(el) {
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

    // Carga el listado de documentos (facturas + saldos iniciales) del cliente seleccionado.
    async function NC_cargarFacturasCliente(term = '') {
        const idCliente = document.getElementById('nc_id_cliente').value;
        if (!idCliente) {
            dropdownFactura.classList.add('d-none');
            return;
        }

        const url = `${BASE_URL}/modulos/notas_credito/buscarFacturasAjax?q=${encodeURIComponent(term)}&id_cliente=${idCliente}`;
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
                        <a href="#" class="list-group-item list-group-item-action py-2" onclick="window.NC_seleccionarFactura(${json})">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold small">${f.num}</span>
                                ${badge}
                            </div>
                            <small class="text-muted" style="font-size:0.7rem;"><i class="bi bi-calendar me-1"></i>${NC_fechaSoloDia(f.fecha_emision)} &middot; $${total}</small>
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
        // Máscara 000-000-000000000 en la entrada manual del número de documento.
        NC_aplicarMascaraNroDoc(searchFactura);
        // Al escribir: el valor tecleado ES el num_doc_modificado (entrada manual)
        // y a la vez se filtra el listado de documentos del cliente.
        searchFactura.addEventListener('input', debounce(() => {
            NC_cargarFacturasCliente(searchFactura.value.trim());
        }, 300));
        // Al enfocar: mostrar todos los documentos del cliente.
        searchFactura.addEventListener('focus', () => {
            NC_cargarFacturasCliente(searchFactura.value.trim());
        });
        // Al completar manualmente el documento (commit con 'change'): limpiar la
        // grilla y dejar una línea lista para capturar. La selección desde el
        // dropdown fija el valor por código (no dispara 'change'), por lo que no
        // afecta a los detalles cargados de una factura del sistema.
        searchFactura.addEventListener('change', () => {
            NC_grillaListaParaIngresar();
        });
        // Cerrar el dropdown al hacer clic fuera.
        document.addEventListener('click', (e) => {
            if (dropdownFactura && !dropdownFactura.contains(e.target) && e.target !== searchFactura) {
                dropdownFactura.classList.add('d-none');
            }
        });
    }

    window.NC_seleccionarFactura = async (f) => {
        if (f.cliente_ruc === '9999999999999') {
            Swal.fire('Atención', 'No se puede emitir una Nota de Crédito a un documento de Consumidor Final.', 'warning');
            return;
        }
        const fechaDia = NC_fechaSoloDia(f.fecha_emision);
        searchFactura.value = f.num_doc || f.num || '';
        document.getElementById('nc_fecha_emision_docs_sustento').value = fechaDia;
        dropdownFactura.classList.add('d-none');
        // Tras elegir la factura, llevar el cursor a la fecha del documento.
        document.getElementById('nc_fecha_emision_docs_sustento').focus();

        const esSaldo = f.origen === 'saldo_inicial';
        const tipoLbl = esSaldo ? 'SALDO INICIAL' : (f.estado || '').toUpperCase();
        document.getElementById('nc_info_factura_modificada').innerHTML = `
            <div class="d-flex gap-3 flex-wrap">
                <span><i class="bi bi-calendar me-1"></i> ${fechaDia}</span>
                <span><i class="bi bi-cash me-1"></i> $${parseFloat(f.importe_total || 0).toFixed(2)}</span>
                <span class="${esSaldo ? 'text-warning' : 'text-primary'} fw-bold">${tipoLbl}</span>
            </div>
        `;

        if (esSaldo) {
            // Los saldos iniciales no tienen detalle de productos: dejar una línea
            // vacía lista para que el usuario capture los ítems manualmente.
            NC_grillaListaParaIngresar();
            return;
        }

        try {
            const resp = await fetch(`${BASE_URL}/modulos/notas_credito/getFacturaDetallesAjax?id_factura=${f.id}`);
            const result = await resp.json();
            if (result.ok) {
                renderDetallesFromFactura(result.detalles);
                calcTotales();
            }
        } catch (e) {
            console.error('Error al cargar detalles de factura:', e);
        }
    };

    // ─── MANEJO DE DETALLES ────────────────────────────────────────────────

    /**
     * Resuelve el id de la tarifa IVA a partir de los impuestos del detalle.
     * Busca primero por id_tarifa_iva, luego por porcentaje (campo tarifa), luego por codigo_porcentaje.
     * Si no encuentra nada, retorna el id de la primera tarifa activa.
     */
    function resolverIdTarifa(impuestos, id_tarifa_iva_directo) {
        // 1. Si viene directo el id
        if (id_tarifa_iva_directo && id_tarifa_iva_directo > 0) return id_tarifa_iva_directo;
        
        if (impuestos && impuestos.length > 0) {
            const imp = impuestos[0];
            // 2. Si el impuesto ya trae id_tarifa_iva
            if (imp.id_tarifa_iva) return imp.id_tarifa_iva;
            // 3. Buscar por porcentaje numérico (campo 'tarifa' en ventas_detalle_impuestos)
            const pct = parseFloat(imp.tarifa);
            if (!isNaN(pct)) {
                const found = listadoTarifasIva.find(t => parseFloat(t.porcentaje_iva) === pct);
                if (found) return found.id;
            }
            // 4. Buscar por codigo_porcentaje
            if (imp.codigo_porcentaje) {
                const found = listadoTarifasIva.find(t => String(t.codigo) === String(imp.codigo_porcentaje));
                if (found) return found.id;
            }
        }
        
        // 5. Fallback: primera tarifa disponible
        return listadoTarifasIva.length > 0 ? listadoTarifasIva[0].id : 0;
    }

    function renderDetallesFromFactura(detalles) {
        NC_limpiarDropdownsProducto();
        tableBody.innerHTML = '';
        detalles.forEach(d => {
            const idTarifa = resolverIdTarifa(d.impuestos, d.id_tarifa_iva);
            agregarFila({
                id_producto: d.id_producto,
                codigo_principal: d.codigo_principal,
                descripcion: d.descripcion,
                cantidad: d.cantidad,
                precio_unitario: d.precio_unitario,
                descuento: d.descuento,
                id_tarifa_iva: idTarifa
            });
        });
    }

    function renderDetalles(detalles) {
        NC_limpiarDropdownsProducto();
        tableBody.innerHTML = '';
        detalles.forEach(d => {
            const idTarifa = resolverIdTarifa(d.impuestos, d.id_tarifa_iva);
            agregarFila({ ...d, id_tarifa_iva: idTarifa });
        });
    }

    window.NC_agregarFila = () => {
        agregarFila();
    };

    function agregarFila(data = {}) {
        const tr = document.createElement('tr');
        tr.className = 'row-det';
        
        const idProducto = data.id_producto || '';
        const descripcion = data.descripcion || '';
        const cantidad = data.cantidad || 0;
        const precioUnitario = data.precio_unitario || 0;
        const descuento = data.descuento || 0;
        const idTarifaIva = data.id_tarifa_iva || 1;

        tr.innerHTML = `
            <td class="ps-3 py-1 position-relative">
                <input type="hidden" name="det_id_producto[]" value="${idProducto}">
                <input type="text" name="det_descripcion[]" class="input-detalle" value="${descripcion}" placeholder="Buscar producto/servicio o escribir..." autocomplete="off">
            </td>
            <td class="py-1">
                <input type="number" name="det_cantidad[]" class="input-detalle text-center" value="${cantidad}" step="0.000001" oninput="window.NC_calcFila(this)">
            </td>
            <td class="py-1">
                <input type="number" name="det_precio_unitario[]" class="input-detalle text-end" value="${precioUnitario}" step="0.000001" oninput="window.NC_calcFila(this)">
            </td>
            <td class="py-1">
                <input type="number" name="det_descuento[]" class="input-detalle text-end" value="${descuento}" step="0.01" oninput="window.NC_calcFila(this)">
            </td>
            <td class="py-1">
                <select name="det_id_tarifa_iva[]" class="input-detalle text-center" onchange="window.NC_calcFila(this)">
                    ${listadoTarifasIva.map(t => `<option value="${t.id}" data-porcentaje="${t.porcentaje_iva}" data-codigo="${t.codigo}" ${t.id == idTarifaIva ? 'selected' : ''}>${t.tarifa}</option>`).join('')}
                </select>
            </td>
            <td class="text-end pe-4 fw-bold nc-fila-total py-1">$0.00</td>
            <td class="py-1">
                <button type="button" class="btn btn-link btn-sm text-danger p-0" onclick="window.NC_removerFila(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        
        tableBody.appendChild(tr);
        NC_attachProductoAutocomplete(tr);
        window.NC_calcFila(tr.querySelector('input[name="det_cantidad[]"]'));
    }

    // Quita los dropdowns de productos colgados del <body> antes de reconstruir
    // la grilla, para no dejar elementos huérfanos.
    function NC_limpiarDropdownsProducto() {
        if (!tableBody) return;
        tableBody.querySelectorAll('tr').forEach(tr => { if (tr._ncDdProd) tr._ncDdProd.remove(); });
    }

    // Autocomplete de productos/servicios sobre el campo descripción de la fila.
    function NC_attachProductoAutocomplete(tr) {
        const inpDesc = tr.querySelector('input[name="det_descripcion[]"]');
        const inpId   = tr.querySelector('input[name="det_id_producto[]"]');
        if (!inpDesc) return;

        // El dropdown se cuelga del <body> con position:fixed para escapar el
        // overflow de la tabla/modal.
        const dd = document.createElement('div');
        dd.className = 'list-group shadow d-none';
        dd.style.cssText = 'position:fixed;z-index:20000;min-width:320px;max-height:240px;overflow-y:auto;background:#fff;border:1px solid #dee2e6;border-radius:0 0 6px 6px;box-shadow:0 4px 16px rgba(0,0,0,.12);';
        document.body.appendChild(dd);
        tr._ncDdProd = dd;   // referencia para limpieza al eliminar la fila
        dd._ncTr = tr;       // referencia inversa para la selección

        function posicionar() {
            const r = inpDesc.getBoundingClientRect();
            dd.style.top   = r.bottom + 'px';
            dd.style.left  = r.left + 'px';
            dd.style.width = Math.max(r.width, 320) + 'px';
        }

        let timer;
        inpDesc.addEventListener('input', () => {
            clearTimeout(timer);
            // Al editar manualmente la descripción se rompe el vínculo con el producto.
            if (inpId) inpId.value = '';
            const q = inpDesc.value.trim();
            if (q.length < 2) { dd.classList.add('d-none'); return; }
            timer = setTimeout(async () => {
                try {
                    const res = await (await fetch(`${BASE_URL}/modulos/factura_venta/getProductosAjax?q=${encodeURIComponent(q)}`)).json();
                    if (!res.ok || !res.data || !res.data.length) {
                        dd.innerHTML = '<div class="list-group-item text-muted small py-2 px-3">Sin resultados — puede dejar la descripción manual.</div>';
                    } else {
                        dd.innerHTML = res.data.map(p =>
                            `<div class="list-group-item list-group-item-action py-1 px-3" style="font-size:0.8rem;cursor:pointer;"
                                 onclick='window.NC_seleccionarProducto(this, ${JSON.stringify(p).replace(/'/g, "&#39;")})'>
                                <span class="fw-semibold">${(p.codigo || '')} — ${(p.nombre || '')}</span>
                                <span class="text-muted ms-2 float-end">$${parseFloat(p.precio_base || 0).toFixed(2)}</span>
                            </div>`
                        ).join('');
                    }
                    posicionar();
                    dd.classList.remove('d-none');
                } catch (e) { console.error('Error al buscar productos NC:', e); }
            }, 280);
        });

        const tabla = tr.closest('.table-responsive');
        if (tabla) tabla.addEventListener('scroll', () => { if (!dd.classList.contains('d-none')) posicionar(); });
        document.addEventListener('click', (e) => {
            if (!inpDesc.contains(e.target) && !dd.contains(e.target)) dd.classList.add('d-none');
        });
    }

    window.NC_seleccionarProducto = (el, p) => {
        const dd = el.closest('.list-group');
        const tr = dd && dd._ncTr;
        if (!tr) return;

        const inpId   = tr.querySelector('input[name="det_id_producto[]"]');
        const inpDesc = tr.querySelector('input[name="det_descripcion[]"]');
        const inpCant = tr.querySelector('input[name="det_cantidad[]"]');
        const inpPrec = tr.querySelector('input[name="det_precio_unitario[]"]');
        const selIva  = tr.querySelector('select[name="det_id_tarifa_iva[]"]');

        if (inpId)   inpId.value = p.id || '';
        if (inpDesc) inpDesc.value = p.nombre || '';
        if (inpCant && (!inpCant.value || parseFloat(inpCant.value) <= 0)) inpCant.value = 1;
        if (inpPrec) inpPrec.value = parseFloat(p.precio_base || 0).toFixed(2);

        // Asignar tarifa IVA: por id directo y, como respaldo, por porcentaje.
        if (selIva) {
            let opt = p.tarifa_iva ? Array.from(selIva.options).find(o => o.value == p.tarifa_iva) : null;
            if (!opt && p.porcentaje_iva_final != null) {
                const pct = parseFloat(p.porcentaje_iva_final);
                opt = Array.from(selIva.options).find(o => Math.abs(parseFloat(o.dataset.porcentaje || 0) - pct) < 0.001);
            }
            if (opt) selIva.value = opt.value;
        }

        dd.classList.add('d-none');
        window.NC_calcFila(inpCant || inpPrec);
        if (inpCant) inpCant.focus();
    };

    window.NC_removerFila = (btn) => {
        const tr = btn.closest('tr');
        if (tr && tr._ncDdProd) tr._ncDdProd.remove();   // limpiar dropdown colgado del body
        tr.remove();
        if (tableBody.children.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No hay items.</td></tr>';
        }
        calcTotales();
    };

    // ─── INFORMACIÓN ADICIONAL ─────────────────────────────────────────────────

    const NC_INFO_INPUT_STYLE = 'padding:0 4px;height:20px;font-size:0.78rem;';

    window.NC_agregarInfoAdicional = (concepto = '', detalle = '') => {
        const tbody = document.getElementById('nc-tbody-info-adicional');
        if (!tbody) return;
        const tr = document.createElement('tr');
        tr.className = 'row-info-adicional-nc';
        tr.innerHTML = `
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto-nc" style="${NC_INFO_INPUT_STYLE}" placeholder="Concepto..." value="${(concepto || '').replace(/"/g, '&quot;')}"></td>
            <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle-nc" style="${NC_INFO_INPUT_STYLE}" placeholder="Detalle..." value="${(detalle || '').replace(/"/g, '&quot;')}"></td>
            <td class="p-0 text-center pe-1">
                <button type="button" class="btn btn-link btn-sm p-0 m-0 text-danger shadow-none" onclick="this.closest('tr').remove();">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </td>`;
        // Insertar antes de la fila fija (correo del cliente) si existe.
        const filaFija = tbody.querySelector('tr[data-tipo]');
        if (filaFija) tbody.insertBefore(tr, filaFija);
        else tbody.appendChild(tr);
        if (!concepto) tr.querySelector('.input-info-concepto-nc').focus();
    };

    // Fila fija con el correo del cliente; se actualiza al cambiar de cliente.
    function NC_actualizarCorreoCliente(email) {
        const tbody = document.getElementById('nc-tbody-info-adicional');
        if (!tbody) return;
        let fila = tbody.querySelector('tr[data-tipo="correo-cliente"]');
        if (!email) { if (fila) fila.remove(); return; }

        if (fila) {
            fila.querySelector('.input-info-detalle-nc').value = email;
        } else {
            fila = document.createElement('tr');
            fila.className = 'row-info-adicional-nc';
            fila.dataset.tipo = 'correo-cliente';
            fila.innerHTML = `
                <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-concepto-nc" style="${NC_INFO_INPUT_STYLE}" value="Correo del cliente" readonly></td>
                <td class="p-0"><input type="text" class="form-control form-control-sm border-0 bg-transparent input-info-detalle-nc" style="${NC_INFO_INPUT_STYLE}" value="${(email || '').replace(/"/g, '&quot;')}"></td>
                <td class="p-0 text-center pe-1"><span class="text-muted small" title="Se actualiza al cambiar el cliente"><i class="bi bi-lock-fill"></i></span></td>`;
            tbody.appendChild(fila);
        }
    }

    function NC_capturarInfoAdicional() {
        const items = [];
        document.querySelectorAll('#nc-tbody-info-adicional tr.row-info-adicional-nc').forEach(tr => {
            const nombre = (tr.querySelector('.input-info-concepto-nc')?.value || '').trim();
            const valor  = (tr.querySelector('.input-info-detalle-nc')?.value || '').trim();
            if (nombre && valor) items.push({ nombre, valor });
        });
        return items;
    }

    function NC_limpiarInfoAdicional() {
        const tbody = document.getElementById('nc-tbody-info-adicional');
        if (tbody) tbody.innerHTML = '';
    }

    // Renderiza la info adicional guardada; el correo del cliente va a la fila fija.
    function NC_renderInfoAdicional(items) {
        NC_limpiarInfoAdicional();
        let correo = '';
        (items || []).forEach(ia => {
            const nombre = ia.nombre ?? '';
            const valor  = ia.valor ?? '';
            const n = nombre.trim().toLowerCase();
            if (n === 'correo del cliente' || n === 'correo') {
                correo = valor;
            } else {
                window.NC_agregarInfoAdicional(nombre, valor);
            }
        });
        if (correo) NC_actualizarCorreoCliente(correo);
    }

    window.NC_calcFila = (el) => {
        const tr = el.closest('tr');
        const cant = parseFloat(tr.querySelector('input[name="det_cantidad[]"]').value) || 0;
        const prec = parseFloat(tr.querySelector('input[name="det_precio_unitario[]"]').value) || 0;
        const desc = parseFloat(tr.querySelector('input[name="det_descuento[]"]').value) || 0;
        
        const subtotal = (cant * prec) - desc;
        
        const decP = window.nc_dec_p || 2;
        tr.querySelector('.nc-fila-total').textContent = `$${subtotal.toLocaleString('en-US', {minimumFractionDigits: decP, maximumFractionDigits: decP})}`;
        
        calcTotales();
    };

    // ─── CÁLCULOS GENERALES ─────────────────────────────────────────────────

    async function cargarTarifasIva() {
        try {
            const resp = await fetch(`${BASE_URL}/modulos/notas_credito/getTarifasIvaAjax`);
            const data = await resp.json();
            if (data.ok) {
                listadoTarifasIva = data.data;
                console.log('Tarifas IVA cargadas:', listadoTarifasIva.length);
            }
        } catch (e) {
            console.error('Error al cargar tarifas IVA:', e);
        }
    }

    function calcTotales() {
        if (!tableBody) return;
        
        let subtotalSinImp = 0;
        let totalDescuento = 0;
        
        // Agrupadores por tarifa
        const subtotalesPorTarifa = {};
        const ivasPorTarifa = {};

        const rows = tableBody.querySelectorAll('tr.row-det');
        rows.forEach(tr => {
            const cantInput = tr.querySelector('input[name="det_cantidad[]"]');
            const precInput = tr.querySelector('input[name="det_precio_unitario[]"]');
            const descInput = tr.querySelector('input[name="det_descuento[]"]');
            const selIva = tr.querySelector('select[name="det_id_tarifa_iva[]"]');
            
            if (!cantInput || !precInput || !descInput || !selIva) return;

            const cant = parseFloat(cantInput.value) || 0;
            const prec = parseFloat(precInput.value) || 0;
            const desc = parseFloat(descInput.value) || 0;
            const optIva = selIva.options[selIva.selectedIndex];
            if (!optIva) return;
            
            const porcIva = parseFloat(optIva.dataset.porcentaje) || 0;
            const nombreIva = optIva.text;

            const baseFila = cant * prec;
            const baseConDesc = baseFila - desc;
            const valorIva = baseConDesc * (porcIva / 100);

            subtotalSinImp += baseFila;
            totalDescuento += desc;

            if (!subtotalesPorTarifa[nombreIva]) subtotalesPorTarifa[nombreIva] = 0;
            subtotalesPorTarifa[nombreIva] += baseFila;

            if (porcIva > 0) {
                if (!ivasPorTarifa[nombreIva]) ivasPorTarifa[nombreIva] = 0;
                ivasPorTarifa[nombreIva] += valorIva;
            }

            const decP = window.nc_dec_p || 2;
            const totalFilaEl = tr.querySelector('.nc-fila-total');
            if (totalFilaEl) totalFilaEl.textContent = (baseConDesc + valorIva).toFixed(decP);
        });

        const decP = window.nc_dec_p || 2;

        const updateLabel = (id, val, isHtml = false) => {
            const el = document.getElementById(id);
            if (el) {
                if (isHtml) el.innerHTML = val;
                else el.textContent = val;
            }
        };

        const updateValue = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val;
        };

        // Renderizar subtotales por tarifa
        let htmlSubtotales = '';
        for (const [nombre, valor] of Object.entries(subtotalesPorTarifa)) {
            htmlSubtotales += `
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small">Subtotal ${nombre}</span>
                    <span class="fw-medium">${valor.toFixed(decP)}</span>
                </div>`;
        }
        updateLabel('nc_lbl_subtotales_iva', htmlSubtotales, true);

        // Renderizar IVAs por tarifa
        let htmlIvas = '';
        let totalIva = 0;
        for (const [nombre, valor] of Object.entries(ivasPorTarifa)) {
            totalIva += valor;
            htmlIvas += `
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small">IVA ${nombre}</span>
                    <span class="fw-bold">${valor.toFixed(decP)}</span>
                </div>`;
        }
        updateLabel('nc_lbl_ivas_grupo', htmlIvas, true);

        const totalNC = (subtotalSinImp - totalDescuento) + totalIva;

        updateLabel('nc_lbl_subtotal', subtotalSinImp.toFixed(decP));
        updateLabel('nc_lbl_descuento', totalDescuento.toFixed(decP));
        updateLabel('nc_lbl_total', totalNC.toFixed(decP));

        updateValue('nc_total_sin_impuestos', (subtotalSinImp - totalDescuento).toFixed(decP));
        updateValue('nc_total_descuento', totalDescuento.toFixed(decP));
        updateValue('nc_importe_total', totalNC.toFixed(decP));
    }

    // ─── OPERACIONES CRUD ───────────────────────────────────────────────────

    window.NC_copiarCampoSri = (inputId) => {
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
    window.NC_copiarClaveAcceso = () => window.NC_copiarCampoSri('nc-sri-clave-acceso');

    // Muestra el aviso y, al cerrarlo, deja el cursor en el campo que falta completar.
    function NC_focusYError(el, mensaje) {
        // Asegurar que la pestaña principal esté visible antes de enfocar.
        try {
            const tabBtn = document.getElementById('tab-nc-principal-btn');
            if (tabBtn && typeof bootstrap !== 'undefined') bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        } catch (e) {}
        Swal.fire('Falta completar', mensaje, 'warning').then(() => {
            if (el) {
                el.focus();
                if (typeof el.select === 'function') { try { el.select(); } catch (e) {} }
            }
        });
    }

    // Valida los campos obligatorios en orden y enfoca el primero que falte.
    function NC_validarObligatorios() {
        const motivo = document.getElementById('nc_motivo');
        if (!motivo || !motivo.value.trim()) { NC_focusYError(motivo, 'El motivo de la nota de crédito es obligatorio.'); return false; }

        const idCliente = document.getElementById('nc_id_cliente').value;
        if (!idCliente) { NC_focusYError(document.getElementById('nc_cliente_search'), 'Debe seleccionar el cliente.'); return false; }

        const serie = document.getElementById('nc_id_punto_emision');
        if (!serie || !serie.value) { NC_focusYError(serie, 'Debe seleccionar la serie (punto de emisión).'); return false; }

        const factura = document.getElementById('nc_factura_search');
        if (!factura || !factura.value.trim()) { NC_focusYError(factura, 'Debe indicar la factura o documento a modificar.'); return false; }

        const fechaDoc = document.getElementById('nc_fecha_emision_docs_sustento');
        if (!fechaDoc || !fechaDoc.value) { NC_focusYError(fechaDoc, 'Debe indicar la fecha del documento a modificar.'); return false; }

        const rows = Array.from(tableBody.querySelectorAll('tr.row-det'));
        if (rows.length === 0) { NC_focusYError(null, 'Debe agregar al menos un ítem a la nota de crédito.'); return false; }
        for (const tr of rows) {
            const desc = tr.querySelector('input[name="det_descripcion[]"]');
            const cant = tr.querySelector('input[name="det_cantidad[]"]');
            if (!desc.value.trim()) { NC_focusYError(desc, 'La descripción del ítem es obligatoria.'); return false; }
            if (!(parseFloat(cant.value) > 0)) { NC_focusYError(cant, 'La cantidad del ítem debe ser mayor a cero.'); return false; }
        }
        return true;
    }

    window.NC_guardar = async () => {
        if (!NC_validarObligatorios()) return;

        const formData = new FormData(formNC);
        const detalles = [];
        const rows = tableBody.querySelectorAll('tr.row-det');
        
        rows.forEach(tr => {
            const selIva = tr.querySelector('select[name="det_id_tarifa_iva[]"]');
            const optIva = selIva.options[selIva.selectedIndex];
            
            const cant = parseFloat(tr.querySelector('input[name="det_cantidad[]"]').value);
            const prec = parseFloat(tr.querySelector('input[name="det_precio_unitario[]"]').value);
            const desc = parseFloat(tr.querySelector('input[name="det_descuento[]"]').value) || 0;
            const base = (cant * prec) - desc;
            const porcIva = parseFloat(optIva.dataset.porcentaje) || 0;
            const valorIva = base * (porcIva / 100);

            detalles.push({
                id_producto: tr.querySelector('input[name="det_id_producto[]"]').value,
                descripcion: tr.querySelector('input[name="det_descripcion[]"]').value,
                cantidad: cant,
                precio_unitario: prec,
                descuento: desc,
                precio_total_sin_impuesto: base,
                impuestos: [{
                    codigo_impuesto: '2',
                    codigo_porcentaje: optIva.dataset.codigo,
                    tarifa: porcIva,
                    base_imponible: base,
                    valor: valorIva
                }]
            });
        });

        const payload = {
            id: document.getElementById('nc_id').value,
            id_punto_emision: document.getElementById('nc_id_punto_emision').value,
            secuencial: document.getElementById('nc_secuencial').value,
            fecha_emision: document.getElementById('nc_fecha_emision').value,
            id_cliente: document.getElementById('nc_id_cliente').value,
            num_doc_modificado: document.getElementById('nc_factura_search').value,
            fecha_emision_docs_sustento: document.getElementById('nc_fecha_emision_docs_sustento').value,
            motivo: document.getElementById('nc_motivo').value,
            id_bodega: document.getElementById('nc_id_bodega').value,
            total_sin_impuestos: document.getElementById('nc_total_sin_impuestos').value,
            total_descuento: document.getElementById('nc_total_descuento').value,
            importe_total: document.getElementById('nc_importe_total').value,
            detalles: detalles,
            info_adicional: NC_capturarInfoAdicional()
        };

        const btn = document.getElementById('btnGuardarNC');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

        try {
            const resp = await fetch(`${BASE_URL}/modulos/notas_credito/guardarAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `data=${encodeURIComponent(JSON.stringify(payload))}`
            });
            const data = await resp.json();
            if (data.ok) {
                window.NC_eliminarRespaldo();
                Swal.fire('Éxito', data.mensaje, 'success');
                modalNC.hide();
                window.NC_fetchSearch();
                // Notificar al módulo padre si existe una función de refresco
                if (typeof window.fvRefrescarDatosModal === 'function') {
                    window.fvRefrescarDatosModal();
                } else if (typeof window.refrescarDesdeModuloHijo === 'function') {
                    window.refrescarDesdeModuloHijo();
                }
            } else {
                Swal.fire('Error', data.mensaje, 'error');
            }
        } catch (e) {
            console.error('Error al guardar NC:', e);
            window.NC_guardarRespaldo(payload);
            Swal.fire('Error', 'No se pudo guardar la nota de crédito. Se ha guardado un borrador local.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
        }
    };

    function NC_capturarEstado() {
        const detalles = [];
        const rows = tableBody.querySelectorAll('tr.row-det');
        rows.forEach(tr => {
            detalles.push({
                id_producto: tr.querySelector('input[name="det_id_producto[]"]').value,
                descripcion: tr.querySelector('input[name="det_descripcion[]"]').value,
                cantidad: tr.querySelector('input[name="det_cantidad[]"]').value,
                precio_unitario: tr.querySelector('input[name="det_precio_unitario[]"]').value,
                descuento: tr.querySelector('input[name="det_descuento[]"]').value,
                id_tarifa_iva: tr.querySelector('select[name="det_id_tarifa_iva[]"]').value
            });
        });

        return {
            id_punto_emision: document.getElementById('nc_id_punto_emision').value,
            secuencial: document.getElementById('nc_secuencial').value,
            fecha_emision: document.getElementById('nc_fecha_emision').value,
            id_cliente: document.getElementById('nc_id_cliente').value,
            cliente_nombre: document.getElementById('nc_cliente_search').value,
            cliente_ruc: document.getElementById('nc_lbl_cliente_ruc').textContent,
            cliente_direccion: document.getElementById('nc_lbl_cliente_direccion').textContent,
            cliente_correo: document.getElementById('nc_lbl_cliente_correo').textContent,
            num_doc_modificado: document.getElementById('nc_factura_search').value,
            fecha_emision_docs_sustento: document.getElementById('nc_fecha_emision_docs_sustento').value,
            motivo: document.getElementById('nc_motivo').value,
            id_bodega: document.getElementById('nc_id_bodega').value,
            detalles: detalles,
            info_adicional: NC_capturarInfoAdicional()
        };
    }

    function NC_autoGuardar() {
        const idActual = document.getElementById('nc_id').value;
        if (idActual) return; // Solo auto-guardar para nuevas NC

        const estado = NC_capturarEstado();
        if (!estado.id_cliente && !estado.detalles.length && !estado.motivo) {
            localStorage.removeItem(window.NC_STORAGE_KEY);
            return;
        }
        localStorage.setItem(window.NC_STORAGE_KEY, JSON.stringify({ data: estado, timestamp: new Date().getTime() }));
    }

    window.NC_registrarAutoGuardado = () => {
        const modal = document.getElementById('modalNC');
        if (!modal) return;
        const auto = debounce(NC_autoGuardar, 1000);
        modal.addEventListener('input', (e) => {
            const idActual = document.getElementById('nc_id').value;
            if (!idActual) auto();
        });
        modal.addEventListener('change', (e) => {
            const idActual = document.getElementById('nc_id').value;
            if (!idActual) auto();
        });
    };

    window.NC_guardarRespaldo = (data) => {
        localStorage.setItem(window.NC_STORAGE_KEY, JSON.stringify(data));
    };

    window.NC_eliminarRespaldo = () => {
        localStorage.removeItem(window.NC_STORAGE_KEY);
        const alertRespaldo = document.getElementById('nc-alert-respaldo');
        if (alertRespaldo) alertRespaldo.classList.add('d-none');
    };

    window.NC_verificarRespaldo = () => {
        // Obsoleto, la lógica se movió a NC_abrirModalNuevo
    };

    window.NC_restaurarRespaldo = (data) => {
        if (!data) return;
        
        document.getElementById('nc_id_punto_emision').value = data.id_punto_emision || '';
        document.getElementById('nc_secuencial').value = data.secuencial || '';
        document.getElementById('nc_fecha_emision').value = data.fecha_emision || '';
        
        // Cliente
        setEl('nc_id_cliente', 'value', data.id_cliente || '');
        setEl('nc_cliente_search', 'value', data.cliente_nombre || '');
        setEl('nc_lbl_cliente_ruc', 'textContent', data.cliente_ruc || '');
        setEl('nc_lbl_cliente_direccion', 'textContent', data.cliente_direccion || '');
        setEl('nc_lbl_cliente_correo', 'textContent', data.cliente_correo || '');
        const infoCli = document.getElementById('nc_info_cliente');
        if (data.id_cliente && infoCli) infoCli.classList.remove('d-none');

        // Habilitar el documento a modificar si hay cliente en el borrador.
        NC_setFacturaHabilitada(!!data.id_cliente);
        setEl('nc_factura_search', 'value', data.num_doc_modificado || '');
        document.getElementById('nc_fecha_emision_docs_sustento').value = (data.fecha_emision_docs_sustento || '').split(' ')[0].split('T')[0];
        document.getElementById('nc_motivo').value = data.motivo || '';
        document.getElementById('nc_id_bodega').value = data.id_bodega || '';

        if (tableBody) {
            NC_limpiarDropdownsProducto();
            tableBody.innerHTML = '';
            (data.detalles || []).forEach(det => {
                window.NC_agregarFila(det);
            });
        }

        NC_renderInfoAdicional(data.info_adicional);

        calcTotales();
        if (typeof mostrarToast === 'function') mostrarToast('Borrador de NC restaurado.', 'info');
    };

    // Pinta el resultado del SRI en la pestaña SRI (badge + panel de mensajes),
    // El detalle de los mensajes del SRI queda registrado en el Historial de Envíos;
    // aquí solo se refleja el estado en el badge de la pestaña SRI.
    function NC_pintarMensajesSri(estado) {
        const badge = document.getElementById('nc-sri-badge-estado');
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

    function NC_irPestanaSri() {
        const tabBtn = document.getElementById('tab-nc-sri-btn');
        if (tabBtn && typeof bootstrap !== 'undefined') {
            try { bootstrap.Tab.getOrCreateInstance(tabBtn).show(); } catch (e) {}
        }
    }

    window.NC_enviarSRI = async () => {
        const id = document.getElementById('nc_id').value;
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
                // Progreso persistente mientras se firma/envía (igual que factura):
                // no se cierra al hacer clic afuera y no tiene botón de confirmar.
                Swal.fire({
                    title: 'Enviando al SRI...',
                    html: '<div class="spinner-border text-primary" role="status"></div><br><small class="text-muted mt-2 d-block">Firmando y enviando comprobante…</small>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                });
                try {
                    const resp = await fetch(`${BASE_URL}/modulos/notas_credito/autorizarSRIAjax`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `id=${id}`
                    });
                    const data = await resp.json();

                    // El mensaje del SRI queda fijo en la pestaña SRI (badge + panel)
                    // y en el historial, para que permanezca a la vista del usuario.
                    const estadoSri = (data.estado || (data.ok ? 'autorizado' : 'error')).toLowerCase();
                    NC_pintarMensajesSri(estadoSri);
                    ncCargarHistorialSri(id);

                    if (data.ok) {
                        toggleBotonesAccion(true, 'autorizado');
                        window.NC_fetchSearch();
                        Swal.fire('Éxito', 'Comprobante autorizado correctamente.', 'success');
                    } else {
                        NC_irPestanaSri(); // dejar el mensaje a la vista
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

    window.NC_exportarPdf = () => {
        const id = document.getElementById('nc_id').value;
        if (id) window.open(`${BASE_URL}/modulos/notas_credito/exportPdfDoc?id=${id}`, '_blank');
    };

    window.NC_exportarXml = () => {
        const id = document.getElementById('nc_id').value;
        if (id) window.location.href = `${BASE_URL}/modulos/notas_credito/exportXmlDoc?id=${id}`;
    };

    window.NC_enviarPorCorreo = async () => {
        const id = document.getElementById('nc_id').value;
        if (!id) return;

        const modalEl = document.getElementById('modalNC');

        // Correo actual del cliente (se puede ver y editar antes de enviar).
        const correoInput  = document.getElementById('nc-sri-correo-cliente');
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

            const resp = await fetch(`${BASE_URL}/modulos/notas_credito/enviarCorreoAjax`, {
                method: 'POST',
                body: fd
            });
            const data = await resp.json();

            if (data.ok) {
                Swal.fire({ icon: 'success', title: '¡Enviado!', text: data.mensaje, timer: 2500, showConfirmButton: false, target: modalEl });
                window.NC_fetchSearch();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje || 'No se pudo enviar el correo.', target: modalEl });
            }
        } catch (e) {
            console.error('Error Correo:', e);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión al enviar el correo.', target: modalEl });
        }
    };

    window.NC_abrirModalClienteCrear = () => {
        if (window.ClienteService && typeof window.ClienteService.abrirModalNuevo === 'function') {
            window.ClienteService.abrirModalNuevo();
        } else {
            Swal.fire('Información', 'El módulo de creación rápida de clientes no está disponible.', 'info');
        }
    };

    window.NC_eliminar = async () => {
        const id = document.getElementById('nc_id').value;
        if (!id) return;

        const result = await Swal.fire({
            title: '¿Eliminar Borrador?',
            text: 'Esta acción eliminará permanentemente la nota de crédito.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            confirmButtonColor: '#d33'
        });

        if (result.isConfirmed) {
            try {
                const resp = await fetch(`${BASE_URL}/modulos/notas_credito/eliminarAjax`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}`
                });
                const data = await resp.json();
                if (data.ok) {
                    Swal.fire('Eliminado', data.mensaje, 'success');
                    modalNC.hide();
                    window.NC_fetchSearch();
                    // Notificar al módulo padre si existe una función de refresco
                    if (typeof window.fvRefrescarDatosModal === 'function') {
                        window.fvRefrescarDatosModal();
                    } else if (typeof window.refrescarDesdeModuloHijo === 'function') {
                        window.refrescarDesdeModuloHijo();
                    }
                } else {
                    Swal.fire('Error', data.mensaje, 'error');
                }
            } catch (e) {
                console.error('Error Eliminar:', e);
            }
        }
    };

    function ncFechaLimiteAnulacion(fechaEmision) {
        const [y, m] = fechaEmision.split('-').map(Number);
        const mesLimite  = m === 12 ? 0 : m;
        const anioLimite = m === 12 ? y + 1 : y;
        let limite = new Date(anioLimite, mesLimite, 7);
        if (limite.getDay() === 6) limite.setDate(9); // sábado → lunes
        if (limite.getDay() === 0) limite.setDate(8); // domingo → lunes
        return limite;
    }

    window.NC_anular = async () => {
        const id = document.getElementById('nc_id').value;
        if (!id) return;

        // ── Regla: Plazo del día 7 del mes siguiente ─────────────────────────
        if (window.NC_FECHA_EMISION) {
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const limiteAnulacion = ncFechaLimiteAnulacion(window.NC_FECHA_EMISION);
            limiteAnulacion.setHours(23, 59, 59, 999);

            if (hoy > limiteAnulacion) {
                const fmtLimite = limiteAnulacion.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' });
                await Swal.fire({
                    icon: 'error',
                    title: 'Fuera de plazo',
                    html: `El plazo para anular esta nota de crédito venció el <strong>${fmtLimite}</strong>.<br>
                           <small class="text-muted">El SRI permite anular hasta el día 7 del mes siguiente a la emisión<br>
                           (o el siguiente día hábil si cae en fin de semana).</small>`,
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }
        }

        const result = await Swal.fire({
            title: '¿Anular Nota de Crédito?',
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

        const btn = document.getElementById('btnAnularNC');
        const btnOrigHtml = btn?.innerHTML;
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Anulando...'; }

        try {
            const resp = await fetch(`${BASE_URL}/modulos/notas_credito/anularAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}`
            });
            const data = await resp.json();
            if (data.ok) {
                modalNC.hide();
                Swal.fire({ icon: 'success', title: 'Anulada', text: data.mensaje, timer: 3000 });
                window.NC_fetchSearch();
                if (typeof window.fvRefrescarDatosModal === 'function') window.fvRefrescarDatosModal();
                else if (typeof window.refrescarDesdeModuloHijo === 'function') window.refrescarDesdeModuloHijo();
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

    async function ncCargarHistorialSri(id) {
        const tbody = document.getElementById('nc-sri-tbody-historial');
        if (!tbody || !id) return;

        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-2 text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</td></tr>';

        try {
            const resp = await fetch(`${BASE_URL}/modulos/notas_credito/getHistorialSriAjax?id=${id}&tipo=nota_credito`);
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
                    } catch(e) {}
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

        } catch(e) {
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
    let _ncAsientoTab = null;
    function ncAsientoTab() {
        if (!_ncAsientoTab && typeof window.crearAsientoTab === 'function') {
            _ncAsientoTab = window.crearAsientoTab({
                tbodyId: 'nc-asiento-tbody',
                debeId:  'nc-asiento-debe',
                haberId: 'nc-asiento-haber',
                difId:   'nc-asiento-dif',
                badgeId: 'nc-asiento-badge',
                countId: 'nc-asiento-count',
                statusId: 'nc-asiento-status',
                previewUrl: `${BASE_URL}/modulos/notas_credito/getAsientoSugeridoAjax`,
                cuentasUrl: `${BASE_URL}/modulos/plan-cuentas/searchAjaxCuentas`
            });
            const addBtn = document.getElementById('nc-asiento-add');
            if (addBtn) addBtn.addEventListener('click', () => _ncAsientoTab.agregarLinea());
        }
        return _ncAsientoTab;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const btnTab = document.getElementById('tab-nc-contable-btn');
        if (btnTab) {
            btnTab.addEventListener('shown.bs.tab', function () {
                const tab = ncAsientoTab();
                const idEl = document.getElementById('nc_id');
                if (tab) tab.cargar(idEl ? idEl.value : 0);
            });
        }
    });
})();


