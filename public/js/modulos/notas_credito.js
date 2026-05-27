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
        
        // Inicializar ordenamiento desde preferencias si existen
        const table = document.querySelector('.nc-scroll table');
        if (table) {
            window.currentSort = 'fecha_emision'; // Default
            window.currentDir = 'DESC';
        }
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
        
        // Guardar preferencia de ordenamiento
        if (window.FavoritosService) {
            window.FavoritosService.guardarPreferencia('modulos/notas_credito', '__ordenCol__', col);
            window.FavoritosService.guardarPreferencia('modulos/notas_credito', '__ordenDir__', dir);
        }
        
        window.NC_fetchSearch(1);
    };

    window.NC_fetchSearch = async (page = 1) => {
        const buscarEl = document.getElementById('buscarNC');
        if (!buscarEl) return;
        const buscar = buscarEl.value;
        const url = `${BASE_URL}/modulos/notas_credito/searchAjax?b=${encodeURIComponent(buscar)}&page=${page}&sort=${window.currentSort || 'fecha_emision'}&dir=${window.currentDir || 'DESC'}`;

        try {
            const resp = await fetch(url);
            const data = await resp.json();
            if (data.ok) {
                document.getElementById('nc-table-body').innerHTML = data.rows;
                document.getElementById('nc-pagination').innerHTML = data.pagination;
                document.getElementById('nc-pagination-info').textContent = data.info;
            }
        } catch (e) {
            console.error('Error al buscar NC:', e);
        }
    };

    // ─── FUNCIONES DEL MODAL ──────────────────────────────────────────────────

    function initModal() {
        if (!formNC) formNC = document.getElementById('formNC');
        if (!tableBody) tableBody = document.getElementById('nc_detalles_body');

        if (modalNC) return true;
        
        const modalEl = document.getElementById('modalNC');
        if (modalEl && typeof bootstrap !== 'undefined') {
            modalNC = new bootstrap.Modal(modalEl);
            console.log('Modal NC inicializado con éxito');
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
            const infoCli = document.getElementById('nc_info_cliente');
            if (infoCli) infoCli.classList.add('d-none');
            
            window.NC_cargarSecuencial();
            
            // Resetear sección SRI
            setEl('nc-sri-clave-acceso',           'value', '');
            setEl('nc-sri-autorizacion',            'value', '');
            setEl('nc-sri-fecha-autorizacion',      'value', '');
            setEl('nc-sri-numero-documento',        'value', '');
            setEl('nc-sri-identificacion-cliente',  'value', '');
            setEl('nc-sri-correo-cliente',          'value', '');
            setEl('nc-sri-mensajes', 'innerHTML', '<p class="text-muted text-center mb-0 py-3 small">Sin respuesta del SRI registrada.</p>');
            window.NC_FECHA_EMISION = null;
            window.NC_CLIENTE_RUC   = '';
            const tbodySri = document.getElementById('nc-sri-tbody-historial');
            if (tbodySri) tbodySri.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">Sin historial de envíos.</td></tr>';

            modalNC.show();

            if (borrador) {
                document.getElementById('modalNC').addEventListener('shown.bs.modal', function onShown() {
                    window.NC_restaurarRespaldo(borrador);
                    this.removeEventListener('shown.bs.modal', onShown);
                });
            }

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
            document.getElementById('nc_num_doc_modificado').value = data.num_doc_modificado;
            document.getElementById('nc_fecha_emision_docs_sustento').value = data.fecha_emision_docs_sustento;
            document.getElementById('nc_motivo').value = data.motivo;
            
            document.getElementById('nc_info_factura_modificada').innerHTML = `
                <div class="d-flex gap-3">
                    <span><i class="bi bi-file-earmark-text me-1"></i> ${data.num_doc_modificado}</span>
                    <span><i class="bi bi-calendar me-1"></i> ${data.fecha_emision_docs_sustento}</span>
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
            const elMensajes     = document.getElementById('nc-sri-mensajes');

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

            if (elMensajes) {
                elMensajes.innerHTML = '<p class="text-muted text-center mb-0 py-3 small">Sin respuesta del SRI registrada.</p>';
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

    window.NC_seleccionarCliente = (c) => {
        if (c.identificacion === '9999999999999') {
            Swal.fire('Atención', 'No se puede emitir una Nota de Crédito a Consumidor Final.', 'warning');
            document.getElementById('nc_cliente_search').value = '';
            document.getElementById('nc_id_cliente').value = '';
            return;
        }
        document.getElementById('nc_id_cliente').value = c.id;
        searchCliente.value = c.nombre;
        dropdownCliente.classList.add('d-none');
    };

    // ─── AUTOCOMPLETE FACTURAS ─────────────────────────────────────────────

    const searchFactura = document.getElementById('nc_factura_search');
    const dropdownFactura = document.getElementById('nc_factura_dropdown');

    if (searchFactura) {
        searchFactura.addEventListener('input', debounce(async () => {
            const term = searchFactura.value.trim();
            const idCliente = document.getElementById('nc_id_cliente').value;
            
            const url = `${BASE_URL}/modulos/notas_credito/buscarFacturasAjax?q=${encodeURIComponent(term)}&id_cliente=${idCliente}`;
            
            const resp = await fetch(url);
            const data = await resp.json();
            if (data.ok && data.data.length > 0) {
                dropdownFactura.innerHTML = data.data.map(f => {
                    const pEst = (f.establecimiento || '').toString().padStart(3, '0');
                    const pPunto = (f.punto_emision || '').toString().padStart(3, '0');
                    const pSec = (f.secuencial || '').toString().padStart(9, '0');
                    const pNum = `${pEst}-${pPunto}-${pSec}`;
                    return `
                        <a href="#" class="list-group-item list-group-item-action py-2" onclick="window.NC_seleccionarFactura(${JSON.stringify(f).replace(/"/g, '&quot;')})">
                            <div class="fw-bold small">${pNum}</div>
                            <small class="text-muted" style="font-size:0.7rem;">${f.cliente_nombre} - ${f.fecha_emision}</small>
                        </a>
                    `;
                }).join('');
                dropdownFactura.classList.remove('d-none');
            } else {
                dropdownFactura.classList.add('d-none');
            }
        }, 300));
    }

    window.NC_seleccionarFactura = async (f) => {
        if (f.cliente_ruc === '9999999999999') {
            Swal.fire('Atención', 'No se puede emitir una Nota de Crédito a una factura de Consumidor Final.', 'warning');
            return;
        }
        const pEst = (f.establecimiento || '').toString().padStart(3, '0');
        const pPunto = (f.punto_emision || '').toString().padStart(3, '0');
        const pSec = (f.secuencial || '').toString().padStart(9, '0');
        const numComp = `${pEst}-${pPunto}-${pSec}`;
        document.getElementById('nc_num_doc_modificado').value = numComp;
        document.getElementById('nc_fecha_emision_docs_sustento').value = f.fecha_emision;
        searchFactura.value = numComp;
        dropdownFactura.classList.add('d-none');
        
        document.getElementById('nc_info_factura_modificada').innerHTML = `
            <div class="d-flex gap-3">
                <span><i class="bi bi-calendar me-1"></i> ${f.fecha_emision}</span>
                <span><i class="bi bi-cash me-1"></i> $${f.importe_total}</span>
                <span class="text-primary fw-bold">${f.estado.toUpperCase()}</span>
            </div>
        `;

        if (!document.getElementById('nc_id_cliente').value) {
            window.NC_seleccionarCliente({ id: f.id_cliente, nombre: f.cliente_nombre, identificacion: f.cliente_ruc });
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
            <td class="ps-3 py-1">
                <input type="hidden" name="det_id_producto[]" value="${idProducto}">
                <input type="text" name="det_descripcion[]" class="input-detalle" value="${descripcion}" placeholder="Descripción...">
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
        window.NC_calcFila(tr.querySelector('input[name="det_cantidad[]"]'));
    }

    window.NC_removerFila = (btn) => {
        btn.closest('tr').remove();
        if (tableBody.children.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No hay items.</td></tr>';
        }
        calcTotales();
    };

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

    window.NC_guardar = async () => {
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
            num_doc_modificado: document.getElementById('nc_num_doc_modificado').value,
            fecha_emision_docs_sustento: document.getElementById('nc_fecha_emision_docs_sustento').value,
            motivo: document.getElementById('nc_motivo').value,
            id_bodega: document.getElementById('nc_id_bodega').value,
            total_sin_impuestos: document.getElementById('nc_total_sin_impuestos').value,
            total_descuento: document.getElementById('nc_total_descuento').value,
            importe_total: document.getElementById('nc_importe_total').value,
            detalles: detalles
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
            num_doc_modificado: document.getElementById('nc_num_doc_modificado').value,
            fecha_emision_docs_sustento: document.getElementById('nc_fecha_emision_docs_sustento').value,
            motivo: document.getElementById('nc_motivo').value,
            id_bodega: document.getElementById('nc_id_bodega').value,
            detalles: detalles
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

        document.getElementById('nc_num_doc_modificado').value = data.num_doc_modificado || '';
        document.getElementById('nc_fecha_emision_docs_sustento').value = data.fecha_emision_docs_sustento || '';
        document.getElementById('nc_motivo').value = data.motivo || '';
        document.getElementById('nc_id_bodega').value = data.id_bodega || '';

        if (tableBody) {
            tableBody.innerHTML = '';
            (data.detalles || []).forEach(det => {
                window.NC_agregarFila(det);
            });
        }

        calcTotales();
        if (typeof mostrarToast === 'function') mostrarToast('Borrador de NC restaurado.', 'info');
    };

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
                Swal.showLoading();
                try {
                    const resp = await fetch(`${BASE_URL}/modulos/notas_credito/autorizarSRIAjax`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `id=${id}`
                    });
                    const data = await resp.json();
                    if (data.ok) {
                        Swal.fire('Éxito', 'Comprobante autorizado correctamente.', 'success');
                        modalNC.hide();
                        window.NC_fetchSearch();
                    } else {
                        let msg = data.mensaje || 'Error desconocido';
                        if (data.errores && data.errores.length > 0) {
                            msg += ':\n' + data.errores.map(e => `- ${e.mensaje || e}`).join('\n');
                        }
                        Swal.fire('Error', msg, 'error');
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

        Swal.fire({
            title: 'Enviar por Correo',
            text: '¿Desea enviar esta nota de crédito al correo del cliente?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, enviar',
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.showLoading();
                try {
                    const resp = await fetch(`${BASE_URL}/modulos/notas_credito/enviarCorreoAjax?id=${id}`);
                    const data = await resp.json();
                    if (data.ok) {
                        Swal.fire('Éxito', 'Correo enviado correctamente.', 'success');
                    } else {
                        Swal.fire('Error', data.mensaje || 'Error al enviar correo', 'error');
                    }
                } catch (e) {
                    console.error('Error Correo:', e);
                    Swal.fire('Error', 'Error de comunicación con el servidor.', 'error');
                }
            }
        });
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


