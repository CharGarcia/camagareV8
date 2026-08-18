/**
 * Módulo de Pedidos - JavaScript Nativo (Vanilla JS)
 */

// ¿La serie seleccionada tiene secuenciales configurados? (se actualiza en syncSerie)
window.PED_SECUENCIAL_CONFIGURADO = true;

function pedAvisarSecuencialNoConfigurado(tipo) {
    if (typeof Swal === 'undefined') return;
    const html = (tipo === 'serie')
        ? 'No hay una serie / punto de emisión disponible.<br>Configure los puntos de emisión y sus secuenciales en <strong>Empresa → Puntos de emisión</strong> antes de crear el pedido.'
        : 'No están configurados los secuenciales para esta serie.<br>Configúrelos en <strong>Empresa → Puntos de emisión</strong> antes de crear el pedido.';
    Swal.fire({
        icon: 'warning',
        title: 'Secuenciales no configurados',
        html: html,
        confirmButtonText: 'Entendido',
        confirmButtonColor: '#f39c12',
        target: document.getElementById('modalPedido'),
    });
}

/** Restringe "Hora Máxima" a horas posteriores a "Hora Inicial" (sin precargar su valor). */
function pedActualizarMinHoraMaxima() {
    const inputHoraIni = document.getElementById('hora_inicial_entrega');
    const inputHoraMax = document.getElementById('hora_maxima_entrega');
    if (!inputHoraIni || !inputHoraMax) return;
    if (inputHoraIni.value) {
        inputHoraMax.min = inputHoraIni.value;
    } else {
        inputHoraMax.removeAttribute('min');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    PED_fetchSearch(window.currentPage);

    const inputHoraIniGlobal = document.getElementById('hora_inicial_entrega');
    if (inputHoraIniGlobal) {
        inputHoraIniGlobal.addEventListener('input', pedActualizarMinHoraMaxima);
    }

    // Buscador principal
    const inputBuscar = document.getElementById('buscar-pedido');
    if (inputBuscar) {
        let timeout;
        inputBuscar.addEventListener('input', (e) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => PED_fetchSearch(1), 300);
        });
    }

    // Sortable Headers
    document.querySelectorAll('.sortable-header').forEach(h => {
        h.addEventListener('click', () => {
            const f = h.dataset.sort;
            if (window.currentSort === f) {
                window.currentDir = (window.currentDir.toLowerCase() === 'asc') ? 'DESC' : 'ASC';
            } else {
                window.currentSort = f;
                window.currentDir = 'ASC';
            }
            if (typeof window.guardarOrdenacionVista === 'function') {
                window.guardarOrdenacionVista('pedidos', window.currentSort, window.currentDir);
            }
            PED_fetchSearch(1);
        });
    });

    // Autocomplete Clientes (Vanilla JS)
    const inputBuscarCliente = document.getElementById('buscar-cliente');
    if (inputBuscarCliente) {
        inputBuscarCliente.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' || e.key === 'Delete') {
                e.preventDefault();
                inputBuscarCliente.value = '';
                document.getElementById('id_cliente').value = '';
            }
        });
    }
    initAutocomplete('buscar-cliente', 'lista-clientes-sugerencias', (item) => {
        document.getElementById('id_cliente').value = item.id;
        document.getElementById('buscar-cliente').value = item.nombre;
    }, `${window.CMG_urlBase}/buscarClientesAjax`);

    // Validaciones de Fecha y Horas de Entrega en Tiempo Real
    const inputFecha = document.getElementById('fecha_entrega');
    const inputHoraIni = document.getElementById('hora_inicial_entrega');
    const inputHoraMax = document.getElementById('hora_maxima_entrega');

    if (inputFecha) inputFecha.addEventListener('change', validarFechasYHoras);
    if (inputHoraIni) inputHoraIni.addEventListener('change', validarFechasYHoras);
    if (inputHoraMax) inputHoraMax.addEventListener('change', validarFechasYHoras);
});

/**
 * Función genérica para autocompletado nativo (Clientes)
 */
function initAutocomplete(inputId, listId, onSelect, url) {
    const input = document.getElementById(inputId);
    if (!input) return;

    let list = document.getElementById(listId);
    if (!list) {
        list = document.createElement('div');
        list.id = listId;
        list.className = 'list-group position-absolute w-100 shadow-sm d-none';
        list.style.zIndex = '1060';
        input.parentNode.style.position = 'relative';
        input.parentNode.appendChild(list);
    }

    let timeout;
    input.addEventListener('input', (e) => {
        clearTimeout(timeout);
        const q = e.target.value.trim();
        if (q.length < 2) {
            list.classList.add('d-none');
            return;
        }

        timeout = setTimeout(async () => {
            try {
                const resp = await fetch(`${url}?term=${encodeURIComponent(q)}`);
                const items = await resp.json();
                
                list.innerHTML = '';
                if (items.length > 0) {
                    items.forEach(item => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action py-2 px-3 small';
                        btn.innerHTML = `<div><strong>${item.identificacion || item.codigo}</strong> - ${item.nombre}</div>`;
                        btn.onclick = () => {
                            onSelect(item);
                            list.classList.add('d-none');
                        };
                        list.appendChild(btn);
                    });
                    list.classList.remove('d-none');
                } else {
                    list.classList.add('d-none');
                }
            } catch (err) {
                console.error('Error en autocomplete:', err);
            }
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !list.contains(e.target)) {
            list.classList.add('d-none');
        }
    });
}

async function listarPedidos() {
    await PED_fetchSearch(window.currentPage);
}

window.PED_cambiarPaginaAjax = (n) => PED_fetchSearch(n);
window.PED_fetchSearch = PED_fetchSearch;

async function PED_fetchSearch(page = 1) {
    const tbody = document.getElementById('lista-pedidos');
    const infoPag = document.getElementById('info-paginacion');
    const inputBuscar = document.getElementById('buscar-pedido');
    const term = inputBuscar ? inputBuscar.value.trim() : '';
    
    if (!tbody) return;

    try {
        const uri = `${window.CMG_urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&sort=${window.currentSort}&dir=${window.currentDir}`;
        const resp = await fetch(uri);
        const data = await resp.json();
        
        if (data.ok) {
            window.currentPage = page;
            tbody.innerHTML = data.rows;
            document.getElementById('paginacion-pedidos').innerHTML = data.pagination;
            infoPag.textContent = data.info;
            document.getElementById('btnExportPdf').href = data.pdf_url;
            document.getElementById('btnExportExcel').href = data.excel_url;

            document.querySelectorAll('.sortable-header').forEach(th => {
                const icon = th.querySelector('i');
                const field = th.dataset.sort;
                if (field === window.currentSort) {
                    icon.className = (window.currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
                } else {
                    icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                }
            });
        }
    } catch (err) {
        console.error('Error al listar pedidos:', err);
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-danger">
            <i class="bi bi-exclamation-triangle d-block fs-2 mb-2"></i>
            Error al cargar registros: ${err.message}
        </td></tr>`;
        infoPag.textContent = 'Error de carga';
    }
}

/**
 * Bloqueo de edición (CMG_Bloqueo): evita que un pedido se edite mientras otro
 * usuario ya lo está usando en Consignaciones de Venta (o viceversa).
 */
function PED_bloquearControles(bloquear) {
    const form = document.getElementById('form-pedido-cabecera');
    if (form) {
        form.querySelectorAll('input, select, button').forEach(el => { el.disabled = bloquear; });
    }
    const btnGuardar = document.getElementById('btn-guardar-pedido');
    if (btnGuardar) btnGuardar.classList.toggle('d-none', bloquear);
    const btnEliminar = document.getElementById('btn-eliminar-modal');
    if (btnEliminar && bloquear) btnEliminar.classList.add('d-none');
}

function PED_mostrarAvisoBloqueo(info) {
    const aviso = document.getElementById('aviso-bloqueo-pedido');
    const texto = document.getElementById('aviso-bloqueo-pedido-texto');
    if (!aviso || !texto) return;
    const contexto = info.modulo_contexto ? ` (${info.modulo_contexto})` : '';
    texto.textContent = `Este pedido lo está usando ahora mismo ${info.usuario || 'otro usuario'}${contexto}. Puedes verlo, pero no editarlo hasta que termine.`;
    aviso.classList.remove('d-none');
    PED_bloquearControles(true);
}

function PED_ocultarAvisoBloqueo() {
    const aviso = document.getElementById('aviso-bloqueo-pedido');
    if (aviso) aviso.classList.add('d-none');
    PED_bloquearControles(false);
}

/**
 * Pedido con TODAS sus líneas ya registradas en consignación/factura: no queda
 * nada por editar ni por facturar. Reusa PED_bloquearControles() (deshabilita
 * cabecera, "Agregar línea" y oculta Guardar/Eliminar) y además el botón
 * "Facturar", que vive fuera del <form>.
 */
function bloquearPedidoProcesado(bloquear) {
    const aviso = document.getElementById('aviso-pedido-procesado');
    if (aviso) aviso.classList.toggle('d-none', !bloquear);

    PED_bloquearControles(bloquear);

    const btnFacturar = document.getElementById('btn-facturar-pedido');
    if (btnFacturar) {
        btnFacturar.disabled = bloquear;
        btnFacturar.title = bloquear
            ? 'No hay saldo pendiente: todo el pedido ya está registrado en una consignación o factura.'
            : 'Generar factura de venta desde este pedido';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('modalPedido');
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', () => {
            if (typeof window.CMG_Bloqueo !== 'undefined') window.CMG_Bloqueo.detener();
        });
    }
});

function nuevoPedido() {
    const form = document.getElementById('form-pedido-cabecera');
    if (form) form.reset();

    PED_ocultarAvisoBloqueo();
    bloquearPedidoProcesado(false);

    document.getElementById('pedido_id').value = '';
    document.getElementById('detalle-productos').innerHTML = '';
    document.getElementById('id_cliente').value = '';
    document.getElementById('buscar-cliente').value = '';
    document.getElementById('estado').value = 'Pendiente';
    window._PED_CLIENTE_EMAIL = '';
    
    // Configurar fecha de entrega con la fecha actual por defecto
    document.getElementById('fecha_entrega').value = CMG_fechaLocal();
    const _ahora = new Date();
    document.getElementById('hora_inicial_entrega').value = String(_ahora.getHours()).padStart(2, '0') + ':' + String(_ahora.getMinutes()).padStart(2, '0');
    document.getElementById('hora_maxima_entrega').value = '';
    pedActualizarMinHoraMaxima();
    document.getElementById('id_responsable_entrega').value = '';
    document.getElementById('observaciones').value = '';
    document.getElementById('observaciones_internas').value = '';

    // Activar primera pestaña
    agregarFilaProducto();

    // Configurar serie y secuencial
    const selPuntos = document.getElementById('id_punto_emision');
    if (selPuntos && selPuntos.options.length > 0) {
        selPuntos.disabled = false;
        syncSerie(selPuntos.value);
    } else {
        window.PED_SECUENCIAL_CONFIGURADO = false;
        pedAvisarSecuencialNoConfigurado('serie');
    }

    document.getElementById('titulo-modal').innerHTML = '<i class="bi bi-cart-plus me-2"></i>Nuevo Pedido';
    
    // Ocultar botón eliminar en el modal si es nuevo
    const btnEliminar = document.getElementById('btn-eliminar-modal');
    if (btnEliminar) btnEliminar.classList.add('d-none');

    calcTotales();
    
    const modalEl = document.getElementById('modalPedido');
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    if (typeof window.aplicarFavoritosModal === 'function') {
        window.aplicarFavoritosModal('#modalPedido');
    }
}

/**
 * Agrega una fila de producto al detalle (Simplificada: Código, Descripción, Cantidad)
 */
function agregarFilaProducto(prod = null) {
    const tbody = document.getElementById('detalle-productos');
    if (!tbody) return;

    const consumida = prod ? parseFloat(prod.cantidad_consumida || 0) : 0;
    const registrada = consumida > 0.0001;
    const cantidadOriginal = prod ? parseFloat(prod.cantidad) : 1;
    const badgeHtml = registrada
        ? `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="cursor:pointer;" onclick="verHistorialItem(this)" title="Ya registrado en una consignación o factura. Clic para ver el historial.">
               <i class="bi bi-clock-history me-1"></i>${consumida >= cantidadOriginal ? 'Registrado' : 'Parcial ' + consumida + '/' + cantidadOriginal}
           </span>`
        : '';
    const btnAccionHtml = registrada
        ? `<button type="button" class="btn btn-link btn-sm text-info p-0 shadow-none border-0" onclick="verHistorialItem(this)" title="Ver historial (no se puede eliminar: ya está registrado)">
               <i class="bi bi-clock-history fs-6"></i>
           </button>`
        : `<button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="this.closest('tr').remove(); calcTotales();" title="Eliminar ítem">
               <i class="bi bi-trash3 fs-6"></i>
           </button>`;

    const tr = document.createElement('tr');
    tr.className = 'row-detalle fila-detalle';
    tr.dataset.cantidadConsumida = consumida;

    tr.innerHTML = `
        <td class="text-center align-middle position-relative" style="width: 150px;">
            <input type="text" class="form-control form-control-sm input-detalle input-codigo text-center border-primary border-opacity-25" placeholder="Código..." autocomplete="off" value="${prod ? prod.producto_codigo : ''}" ${registrada ? 'readonly' : ''}>
        </td>
        <td class="align-middle position-relative">
            <input type="text" class="form-control form-control-sm input-detalle input-descripcion fw-bold border-primary border-opacity-25" placeholder="Escribe o busca un producto..." autocomplete="off" value="${prod ? prod.producto_nombre : ''}" ${registrada ? 'readonly' : ''}>
            <input type="hidden" class="input-id-producto" value="${prod ? prod.id_producto : ''}">
            <input type="hidden" class="input-id-detalle" value="${prod && prod.id ? prod.id : ''}">
        </td>
        <td class="align-middle text-center">${badgeHtml}</td>
        <td class="align-middle text-center" style="width: 15%;">
            <input type="number" class="form-control form-control-sm input-detalle text-center input-cantidad" value="${cantidadOriginal}" step="any" min="${consumida}" oninput="calcFila(this)">
        </td>
        <td class="text-center p-0 align-middle" style="width: 40px;">
            ${btnAccionHtml}
        </td>
    `;
    tbody.appendChild(tr);

    if (registrada) {
        // Fila ya registrada en otro documento: no se puede quitar ni cambiar de
        // producto (el servidor lo vuelve a validar igual, esto es solo UX).
        const inCant = tr.querySelector('.input-cantidad');
        inCant.addEventListener('change', () => {
            const val = parseFloat(inCant.value) || 0;
            if (val < consumida) {
                inCant.value = consumida;
                calcFila(inCant);
                Swal.fire({ icon: 'warning', title: 'Atención', text: `No puede bajar de ${consumida}: ya está registrado en una consignación o factura.`, target: document.getElementById('modalPedido') });
            }
        });
        return;
    }

    const inputCod = tr.querySelector('.input-codigo');
    const inputDesc = tr.querySelector('.input-descripcion');
    const dropdownGlobal = document.getElementById('m-dropdown-productos-global');

    if (!prod) {
        setTimeout(() => {
            inputCod.focus();
        }, 50);
    }

    const seleccionarProductoEnFila = (p, row) => {
        row.querySelector('.input-codigo').value = p.codigo;
        row.querySelector('.input-descripcion').value = p.nombre;
        row.querySelector('.input-id-producto').value = p.id;
        row.dataset.idProducto = p.id;

        const inCant = row.querySelector('.input-cantidad');
        inCant.focus();
        inCant.select();
    };

    const buscarProducto = async (q, sourceInput) => {
        q = q.trim();
        if (q.length < 2) {
            dropdownGlobal.classList.add('d-none');
            return;
        }

        const rect = sourceInput.getBoundingClientRect();
        dropdownGlobal.style.top = `${rect.bottom + window.scrollY + 2}px`;
        dropdownGlobal.style.left = `${rect.left + window.scrollX}px`;
        dropdownGlobal.style.width = `${Math.max(rect.width, 350)}px`;
        dropdownGlobal.classList.remove('d-none');
        dropdownGlobal.innerHTML = '<div class="list-group-item small text-muted">Buscando...</div>';

        try {
            const resp = await fetch(`${window.CMG_urlBase}/buscarProductosAjax?term=${encodeURIComponent(q)}`);
            const json = await resp.json();
            dropdownGlobal.innerHTML = '';

            if (json.ok && json.data && json.data.length > 0) {
                json.data.forEach(p => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'list-group-item list-group-item-action small py-1 border-bottom';
                    b.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center text-start">
                            <div class="pe-3">
                                <div class="fw-bold text-dark">${p.nombre}</div>
                                <div class="x-small text-muted">${p.codigo} ${p.codigo_barras ? '| ' + p.codigo_barras : ''}</div>
                            </div>
                        </div>
                    `;
                    b.onmousedown = (evt) => {
                        evt.preventDefault();
                        seleccionarProductoEnFila(p, tr);
                        dropdownGlobal.classList.add('d-none');
                    };
                    dropdownGlobal.appendChild(b);
                });
            } else {
                dropdownGlobal.innerHTML = '<div class="list-group-item small text-muted">Sin coincidencias en el catálogo</div>';
            }
        } catch (err) {
            console.error('Error productos', err);
        }
    };

    const setupAutocompleteEvents = (inputEl) => {
        inputEl.addEventListener('input', debounce((e) => buscarProducto(e.target.value, inputEl), 400));

        inputEl.addEventListener('keydown', (e) => {
            if (e.key === 'Delete' || e.key === 'Backspace') {
                e.preventDefault();
                inputCod.value = '';
                inputDesc.value = '';
                tr.querySelector('.input-id-producto').value = '';
                dropdownGlobal.classList.add('d-none');
            }
            if (e.key === 'Enter') {
                const firstBtn = dropdownGlobal.querySelector('button');
                if (firstBtn && !dropdownGlobal.classList.contains('d-none')) {
                    e.preventDefault();
                    if (firstBtn.onmousedown) firstBtn.onmousedown(new MouseEvent('mousedown'));
                }
            }
        });

        inputEl.addEventListener('blur', () => setTimeout(() => dropdownGlobal.classList.add('d-none'), 200));
    };

    setupAutocompleteEvents(inputCod);
    setupAutocompleteEvents(inputDesc);
}

/** Historial de consignaciones/facturas que registraron una línea del pedido. */
async function verHistorialItem(el) {
    const tr = el.closest('tr');
    if (!tr) return;
    const idDetalle = tr.querySelector('.input-id-detalle')?.value;
    const nombreProducto = tr.querySelector('.input-descripcion')?.value || 'este ítem';
    if (!idDetalle) return;

    Swal.fire({ title: 'Cargando historial...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), target: document.getElementById('modalPedido') });
    try {
        const res = await fetch(`${window.CMG_urlBase}/historialDetalleAjax?id=${idDetalle}`);
        const data = await res.json();

        if (!data.ok) {
            return Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje || 'No se pudo cargar el historial.', target: document.getElementById('modalPedido') });
        }

        const nombreHtml = `<div class="text-start mb-2" style="font-size:12px; font-weight:400;">
                <i class="bi bi-box-seam me-1 text-muted"></i>${nombreProducto}
            </div>`;

        let tablaHtml;
        if (!data.data || data.data.length === 0) {
            tablaHtml = '<p class="text-muted small mb-0 text-start">Sin movimientos registrados para este ítem.</p>';
        } else {
            tablaHtml = '<div style="overflow-x:auto; border:1px solid #dee2e6; border-radius:.375rem;">'
                + '<table class="table table-sm align-middle mb-0 small text-start" style="white-space:nowrap;">'
                + '<thead class="table-light"><tr><th class="ps-2">Tipo</th><th>Número</th><th>Fecha</th><th class="text-end">Cantidad</th><th class="pe-2">Estado</th></tr></thead><tbody>'
                + data.data.map(h => `<tr>
                        <td class="ps-2">${h.tipo}</td>
                        <td>${h.numero || '-'}</td>
                        <td>${h.fecha ? new Date(h.fecha).toLocaleDateString('es-EC') : '-'}</td>
                        <td class="text-end">${parseFloat(h.cantidad).toFixed(2)}</td>
                        <td class="pe-2">${h.estado || '-'}</td>
                    </tr>`).join('')
                + '</tbody></table></div>';
        }

        Swal.fire({
            title: 'Historial del ítem',
            html: nombreHtml + tablaHtml,
            confirmButtonText: 'Cerrar',
            width: 600,
            target: document.getElementById('modalPedido'),
        });
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar el historial.', target: document.getElementById('modalPedido') });
    }
}

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function calcFila(el) {
    calcTotales();
}

function calcTotales() {
    const rows = document.querySelectorAll('.fila-detalle');
    const countItems = document.getElementById('m-count-items');
    if (countItems) countItems.textContent = rows.length;
}

async function syncSerie(idPunto) {
    if (!idPunto) {
        window.PED_SECUENCIAL_CONFIGURADO = false;
        pedAvisarSecuencialNoConfigurado('serie');
        return;
    }

    const select = document.getElementById('id_punto_emision');
    const option = select.options[select.selectedIndex];

    if (option) {
        document.getElementById('id_establecimiento').value = option.dataset.est || '';
    }

    const inputSec = document.getElementById('secuencial');

    try {
        const res = await fetch(`${window.CMG_urlBase}/getSecuencialAjax?id_punto_emision=${idPunto}`);
        const data = await res.json();

        if (data.status && data.formateado) {
            inputSec.value = data.formateado;

            // ¿Está configurado el secuencial para esta serie? (igual que Factura de Venta)
            window.PED_SECUENCIAL_CONFIGURADO = (data.configurado !== false);
            if (data.configurado === false) {
                inputSec.classList.add('border-danger');
                pedAvisarSecuencialNoConfigurado('secuencial');
            } else {
                inputSec.classList.remove('border-danger');
            }
        } else {
            window.PED_SECUENCIAL_CONFIGURADO = false;
            pedAvisarSecuencialNoConfigurado('secuencial');
        }
    } catch (e) {
        console.error("Error al obtener secuencial", e);
    }
}

function pdfPedido() {
    const id = document.getElementById('pedido_id').value;
    if (!id) return Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe guardar el pedido primero.' });
    window.open(`${window.CMG_urlBase}/pdf?id=${id}`, '_blank');
}

function excelPedido() {
    const id = document.getElementById('pedido_id').value;
    if (!id) return Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe guardar el pedido primero.' });
    window.open(`${window.CMG_urlBase}/excel?id=${id}`, '_blank');
}

async function emailPedido() {
    const id = document.getElementById('pedido_id').value;
    if (!id) return Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe guardar el pedido primero.' });

    const { value: correos, isConfirmed } = await Swal.fire({
        title: 'Enviar por correo',
        input: 'text',
        inputLabel: 'Correo(s) destino, separados por coma.',
        inputValue: window._PED_CLIENTE_EMAIL || '',
        inputPlaceholder: 'cliente@correo.com',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-envelope me-1"></i> Enviar',
        cancelButtonText: 'Cancelar',
        target: document.getElementById('modalPedido'),
    });
    if (!isConfirmed) return;

    Swal.fire({ title: 'Enviando correo...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), target: document.getElementById('modalPedido') });
    try {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('correos', correos || '');
        const res = await fetch(`${window.CMG_urlBase}/enviarCorreoAjax`, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            Swal.fire({ icon: 'success', title: 'Enviado', text: data.mensaje || 'Correo enviado correctamente.', target: document.getElementById('modalPedido') });
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.mensaje || 'No se pudo enviar el correo.', target: document.getElementById('modalPedido') });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo enviar el correo.', target: document.getElementById('modalPedido') });
    }
}

/**
 * Genera una Factura de Venta a partir de este pedido: pide al servidor el cliente y
 * los productos ya resueltos (mismo shape que usa Factura de Venta), los deja listos
 * en sessionStorage y redirige al módulo de Facturas, que los toma al cargar y abre
 * el modal "Nueva Factura" pre-llenado. No crea la factura por sí sola: el usuario
 * revisa precios/IVA y guarda desde el formulario normal de Factura de Venta.
 */
async function facturarPedido() {
    const id = document.getElementById('pedido_id').value;
    if (!id) return Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe guardar el pedido primero.' });

    const estadoActual = document.getElementById('estado').value;
    if (estadoActual === 'Procesado' || estadoActual === 'Anulado') {
        return Swal.fire({ icon: 'warning', title: 'No se puede facturar', text: `Este pedido está ${estadoActual}; no se puede generar una factura desde él.`, target: document.getElementById('modalPedido') });
    }

    const confirmacion = await Swal.fire({
        icon: 'question',
        title: 'Generar factura',
        text: '¿Generar una factura de venta con los datos de este pedido? Podrás revisar precios e IVA antes de guardarla.',
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar',
        target: document.getElementById('modalPedido'),
    });
    if (!confirmacion.isConfirmed) return;

    Swal.fire({ title: 'Preparando factura...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), target: document.getElementById('modalPedido') });
    try {
        const res = await fetch(`${window.CMG_urlBase}/datosParaFacturarAjax?id=${id}`);
        const data = await res.json();
        Swal.close();

        if (!data.ok) {
            return Swal.fire({ icon: 'error', title: 'No se pudo preparar la factura', text: data.mensaje || 'Error desconocido.', target: document.getElementById('modalPedido') });
        }

        sessionStorage.setItem('cmg_facturar_desde_pedido', JSON.stringify(data));
        window.location.href = `${window.BASE_URL}/modulos/factura-venta`;
    } catch (e) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo preparar los datos para facturar.', target: document.getElementById('modalPedido') });
    }
}

function validarFechasYHoras() {
    const inputFecha = document.getElementById('fecha_entrega');
    const inputHoraIni = document.getElementById('hora_inicial_entrega');
    const inputHoraMax = document.getElementById('hora_maxima_entrega');

    if (!inputFecha) return true;

    let isOk = true;

    // Helper para eliminar clase inválida y mensaje previo
    const clearError = (el) => {
        el.classList.remove('is-invalid');
        const feedback = el.parentNode.querySelector('.invalid-feedback');
        if (feedback) feedback.remove();
    };

    // Helper para mostrar error
    const showError = (el, msg) => {
        clearError(el);
        el.classList.add('is-invalid');
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = msg;
        el.parentNode.appendChild(feedback);
    };

    clearError(inputFecha);
    clearError(inputHoraIni);
    clearError(inputHoraMax);

    // 1. Fecha de entrega no puede ser menor a la fecha actual (hoy local)
    if (inputFecha.value) {
        const localDate = new Date();
        const year = localDate.getFullYear();
        const month = String(localDate.getMonth() + 1).padStart(2, '0');
        const day = String(localDate.getDate()).padStart(2, '0');
        const todayStr = `${year}-${month}-${day}`;

        if (inputFecha.value < todayStr) {
            showError(inputFecha, 'La fecha de entrega no puede ser menor a la fecha actual.');
            isOk = false;
        }
    }

    // 2. Horas inicial y máxima
    const hIni = inputHoraIni.value;
    const hMax = inputHoraMax.value;

    if (hIni && hMax) {
        if (hIni > hMax) {
            showError(inputHoraIni, 'La hora inicial no puede ser mayor a la hora máxima.');
            showError(inputHoraMax, 'La hora máxima debe ser mayor a la hora inicial.');
            isOk = false;
        } else if (hIni === hMax) {
            showError(inputHoraIni, 'La hora inicial no puede ser igual a la hora máxima.');
            showError(inputHoraMax, 'La hora máxima no puede ser igual a la hora inicial.');
            isOk = false;
        }
    }

    return isOk;
}

async function guardarPedido() {
    const selectPuntos = document.getElementById('id_punto_emision');
    const optionPunto = selectPuntos.options[selectPuntos.selectedIndex];

    const cabecera = {
        id: document.getElementById('pedido_id').value,
        id_cliente: document.getElementById('id_cliente').value,
        fecha_pedido: document.getElementById('fecha_pedido').value,
        id_establecimiento: document.getElementById('id_establecimiento').value,
        id_punto_emision: selectPuntos.value,
        establecimiento: optionPunto ? optionPunto.dataset.codEst : '',
        punto_emision: optionPunto ? optionPunto.dataset.codPunto : '',
        secuencial: document.getElementById('secuencial').value,
        estado: document.getElementById('estado').value,
        fecha_entrega: document.getElementById('fecha_entrega').value,
        hora_inicial_entrega: document.getElementById('hora_inicial_entrega').value,
        hora_maxima_entrega: document.getElementById('hora_maxima_entrega').value,
        id_responsable_entrega: document.getElementById('id_responsable_entrega').value,
        subtotal: 0,
        iva: 0,
        total: 0,
        observaciones: document.getElementById('observaciones').value,
        observaciones_internas: document.getElementById('observaciones_internas').value
    };

    // Bloqueo: secuenciales no configurados (solo al CREAR un pedido nuevo)
    if (!cabecera.id && window.PED_SECUENCIAL_CONFIGURADO === false) {
        pedAvisarSecuencialNoConfigurado('secuencial');
        return;
    }

    if (!cabecera.id_cliente || !cabecera.fecha_pedido || !cabecera.secuencial) {
        Swal.fire({ icon: 'warning', title: 'Faltan datos', text: 'El Cliente, Fecha de pedido y Secuencial son obligatorios.' });
        return;
    }

    if (!cabecera.fecha_entrega || !cabecera.hora_inicial_entrega || !cabecera.hora_maxima_entrega || !cabecera.id_responsable_entrega) {
        Swal.fire({ icon: 'warning', title: 'Faltan datos de Entrega', text: 'Debe llenar todos los campos en la fila de Entrega.' });
        return;
    }

    if (!validarFechasYHoras()) {
        Swal.fire({ icon: 'warning', title: 'Validación de Entrega', text: 'Por favor, corrija los errores en la fecha u horas de entrega antes de continuar.' });
        return;
    }

    const detalles = [];
    document.querySelectorAll('.fila-detalle').forEach(tr => {
        const idProd = tr.querySelector('.input-id-producto').value;
        const cant = parseFloat(tr.querySelector('.input-cantidad').value) || 0;
        const descrip = tr.querySelector('.input-descripcion').value.trim();

        if (idProd && cant > 0 && descrip !== '') {
            detalles.push({
                id: tr.querySelector('.input-id-detalle')?.value || '',
                id_producto: idProd,
                cantidad: cant,
                precio_unitario: 0,
                subtotal: 0,
                iva: 0,
                descuento: 0,
                total: 0
            });
        }
    });

    if (detalles.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe agregar al menos un producto válido al pedido.' });
        return;
    }

    try {
        const formData = new FormData();
        formData.append('cabecera[id]', cabecera.id);
        formData.append('cabecera[id_cliente]', cabecera.id_cliente);
        formData.append('cabecera[fecha_pedido]', cabecera.fecha_pedido);
        formData.append('cabecera[id_establecimiento]', cabecera.id_establecimiento);
        formData.append('cabecera[id_punto_emision]', cabecera.id_punto_emision);
        formData.append('cabecera[establecimiento]', cabecera.establecimiento);
        formData.append('cabecera[punto_emision]', cabecera.punto_emision);
        formData.append('cabecera[secuencial]', cabecera.secuencial);
        formData.append('cabecera[estado]', cabecera.estado);
        formData.append('cabecera[fecha_entrega]', cabecera.fecha_entrega);
        formData.append('cabecera[hora_inicial_entrega]', cabecera.hora_inicial_entrega);
        formData.append('cabecera[hora_maxima_entrega]', cabecera.hora_maxima_entrega);
        formData.append('cabecera[id_responsable_entrega]', cabecera.id_responsable_entrega);
        formData.append('cabecera[observaciones]', cabecera.observaciones);
        formData.append('cabecera[observaciones_internas]', cabecera.observaciones_internas);
        
        detalles.forEach((d, i) => {
            formData.append(`detalles[${i}][id]`, d.id);
            formData.append(`detalles[${i}][id_producto]`, d.id_producto);
            formData.append(`detalles[${i}][cantidad]`, d.cantidad);
            formData.append(`detalles[${i}][precio_unitario]`, d.precio_unitario);
            formData.append(`detalles[${i}][subtotal]`, d.subtotal);
            formData.append(`detalles[${i}][iva]`, d.iva);
            formData.append(`detalles[${i}][descuento]`, d.descuento);
            formData.append(`detalles[${i}][total]`, d.total);
        });

        const resp = await fetch(`${window.CMG_urlBase}/guardarAjax`, {
            method: 'POST',
            body: formData
        });
        const res = await resp.json();

        if (res.status) {
            Swal.fire({ icon: 'success', title: 'Éxito', text: res.message });
            bootstrap.Modal.getInstance(document.getElementById('modalPedido')).hide();
            listarPedidos();
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message });
        }
    } catch (err) {
        console.error('Error al guardar:', err);
        Swal.fire({ icon: 'error', title: 'Error', text: 'Ocurrió un error inesperado al guardar el pedido.' });
    }
}

async function editarPedido(id) {
    try {
        const formData = new FormData();
        formData.append('id', id);

        const resp = await fetch(`${window.CMG_urlBase}/obtenerPedidoAjax`, {
            method: 'POST',
            body: formData
        });
        const res = await resp.json();

        if (res.status && res.data) {
            const p = res.data.cabecera;
            if (!p) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se encontraron los datos del pedido.'
                });
                return;
            }

            const setVal = (idEl, val) => {
                const el = document.getElementById(idEl);
                if (el) el.value = val !== null && val !== undefined ? val : '';
            };

            setVal('pedido_id', p.id);
            setVal('id_cliente', p.id_cliente);
            setVal('buscar-cliente', p.cliente_nombre);
            window._PED_CLIENTE_EMAIL = p.cliente_email || '';
            
            let dFecha = p.fecha_pedido ? p.fecha_pedido.substring(0, 10) : '';
            setVal('fecha_pedido', dFecha);
            
            const selPuntos = document.getElementById('id_punto_emision');
            if (selPuntos) {
                selPuntos.disabled = false; // Asegurar que inicie habilitado por si acaso
                if (p.id_punto_emision) {
                    selPuntos.value = p.id_punto_emision;
                    const elEst = document.getElementById('id_establecimiento');
                    if (elEst) elEst.value = p.id_establecimiento || '';
                    selPuntos.disabled = true;
                }
            }
            setVal('secuencial', p.secuencial);

            setVal('estado', p.estado || 'Pendiente');
            setVal('fecha_entrega', p.fecha_entrega);
            setVal('hora_inicial_entrega', p.hora_inicial_entrega);
            setVal('hora_maxima_entrega', p.hora_maxima_entrega);
            pedActualizarMinHoraMaxima();
            setVal('id_responsable_entrega', p.id_responsable_entrega);
            setVal('observaciones', p.observaciones);
            setVal('observaciones_internas', p.observaciones_internas);

            const tbody = document.getElementById('detalle-productos');
            let todoRegistrado = false;
            if (tbody) {
                tbody.innerHTML = '';
                if (res.data.detalles && res.data.detalles.length > 0) {
                    res.data.detalles.forEach(d => {
                        agregarFilaProducto(d);
                    });
                    todoRegistrado = res.data.detalles.every(d => parseFloat(d.cantidad_consumida || 0) >= parseFloat(d.cantidad) - 0.0001);
                } else {
                    agregarFilaProducto();
                }
            }

            const elTitulo = document.getElementById('titulo-modal');
            if (elTitulo) {
                const nroPedido = (p.establecimiento && p.punto_emision && p.secuencial)
                    ? `${p.establecimiento}-${p.punto_emision}-${p.secuencial}`
                    : (p.numero_pedido || '');
                elTitulo.innerHTML = `<i class="bi bi-pencil-square me-2"></i>Editar Pedido #${nroPedido}`;
            }

            const btnEliminar = document.getElementById('btn-eliminar-modal');
            if (btnEliminar) btnEliminar.classList.remove('d-none');

            calcTotales();
            PED_ocultarAvisoBloqueo();
            bloquearPedidoProcesado(todoRegistrado);

            const modalEl = document.getElementById('modalPedido');
            if (modalEl) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
                if (typeof window.aplicarFavoritosModal === 'function') {
                    window.aplicarFavoritosModal('#modalPedido');
                }
            } else {
                console.error("No se encontró el elemento modal 'modalPedido' en el DOM.");
            }

            if (typeof window.CMG_Bloqueo !== 'undefined') {
                window.CMG_Bloqueo.iniciar({
                    urlBase: window.CMG_urlBase,
                    idRegistro: p.id,
                    moduloContexto: 'Pedidos',
                    onBloqueado: PED_mostrarAvisoBloqueo,
                    onPerdido: () => {
                        Swal.fire({ icon: 'warning', title: 'Perdiste el control de este pedido', text: 'Otro usuario lo tomó. Guarda tus cambios pendientes con cuidado o recarga.' });
                        PED_bloquearControles(true);
                    }
                });
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: res.message || 'No se pudieron recuperar los datos del pedido.'
            });
        }
    } catch (err) {
        console.error('Error al editar:', err);
        Swal.fire({
            icon: 'error',
            title: 'Error de Red / JS',
            text: 'Ocurrió un error al procesar la solicitud: ' + err.message
        });
    }
}

async function eliminarPedidoConf(id) {
    const result = await Swal.fire({
        icon: 'warning',
        title: '¿Eliminar pedido?',
        text: 'Esta acción no se puede deshacer.',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });
    if (!result.isConfirmed) return;

    try {
        const formData = new FormData();
        formData.append('id', id);

        const resp = await fetch(`${window.CMG_urlBase}/eliminarAjax`, {
            method: 'POST',
            body: formData
        });
        const res = await resp.json();

        if (res.status) {
            Swal.fire({ icon: 'success', title: 'Eliminado', text: res.message, timer: 1500, showConfirmButton: false });
            listarPedidos();
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message });
        }
    } catch (err) {
        console.error('Error al eliminar:', err);
        Swal.fire({ icon: 'error', title: 'Error', text: 'Ocurrió un error inesperado.' });
    }
}

function eliminarPedidoActual() {
    const id = document.getElementById('pedido_id').value;
    if (id) eliminarPedidoConf(id);
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const d = String(date.getDate()).padStart(2, '0');
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const y = date.getFullYear();
    const h = String(date.getHours()).padStart(2, '0');
    const min = String(date.getMinutes()).padStart(2, '0');
    const s = String(date.getSeconds()).padStart(2, '0');
    return `${d}-${m}-${y} ${h}:${min}:${s}`;
}

// ==========================================
// NUEVO: Funciones para creación de Clientes y Responsables de Traslado desde el Modal de Pedidos
// ==========================================

let modalRespInst = null;

function abrirModalResponsableCrear() {
    document.getElementById('resp_nombre').value = '';
    document.getElementById('resp_identificacion').value = '';
    document.getElementById('resp_telefono').value = '';
    document.getElementById('resp_email').value = '';
    
    if (!modalRespInst) {
        modalRespInst = new bootstrap.Modal(document.getElementById('modalResponsableTraslado'));
    }
    modalRespInst.show();
}

function cerrarModalResponsable() {
    if (modalRespInst) {
        modalRespInst.hide();
    }
}

async function guardarResponsableTraslado() {
    const nombre = document.getElementById('resp_nombre').value.trim();
    const identificacion = document.getElementById('resp_identificacion').value.trim();
    const telefono = document.getElementById('resp_telefono').value.trim();
    const email = document.getElementById('resp_email').value.trim();

    if (!nombre) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'El nombre completo es obligatorio.'
        });
        return;
    }

    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        Swal.fire({
            icon: 'warning',
            title: 'Atención',
            text: 'El formato del correo electrónico no es válido.'
        });
        return;
    }

    try {
        const formData = new FormData();
        formData.append('nombre', nombre);
        formData.append('identificacion', identificacion);
        formData.append('telefono', telefono);
        formData.append('email', email);

        const resp = await fetch(`${window.CMG_urlBase}/guardarResponsableAjax`, {
            method: 'POST',
            body: formData
        });
        const res = await resp.json();

        if (res.status && res.data) {
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: res.message || 'Responsable creado con éxito.',
                timer: 1500,
                showConfirmButton: false
            });

            const selectResp = document.getElementById('id_responsable_entrega');
            if (selectResp) {
                const option = document.createElement('option');
                option.value = res.data.id;
                option.textContent = res.data.nombre;
                selectResp.appendChild(option);
                selectResp.value = res.data.id;
            }

            cerrarModalResponsable();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: res.message || 'No se pudo guardar la información.'
            });
        }
    } catch (err) {
        console.error('Error al guardar responsable:', err);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo conectar con el servidor.'
        });
    }
}

document.addEventListener('clienteGuardado', function(e) {
    const res = e.detail;
    if (res && res.ok && res.data) {
        const cliente = res.data;
        const inputBuscar = document.getElementById('buscar-cliente');
        const inputId = document.getElementById('id_cliente');
        if (inputBuscar && inputId) {
            inputBuscar.value = cliente.nombre;
            inputId.value = cliente.id;
            document.getElementById('fecha_pedido').focus();
        }
    }
});

document.addEventListener('show.bs.modal', function (event) {
    if (event.target.id === 'modalResponsableTraslado') {
        setTimeout(() => {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            if (backdrops.length > 1) {
                backdrops[backdrops.length - 1].style.zIndex = '1055';
            }
        }, 0);
    }
});
