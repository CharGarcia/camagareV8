// Control de vista para Reporte de Cartera
document.addEventListener('DOMContentLoaded', function () {
    RC_setupBuscadorEntidad();
    RC_setupBuscadorDocumento();

    document.getElementById('rc-mes').addEventListener('change', window.RC_cambiarMesAnio);
    document.getElementById('rc-anio').addEventListener('change', window.RC_cambiarMesAnio);

    // Fecha Desde/Hasta arrancan calculadas desde Mes/Año (mes actual por defecto).
    // Sin selección de cliente/proveedor todavía, así que solo pide elegir uno.
    window.RC_cambiarMesAnio();
});

function RC_endpointEntidad() {
    return document.getElementById('rc_tipo').value === 'PROVEEDOR' ? 'getProveedoresAjax' : 'getClientesAjax';
}

// Buscador predictivo con selección múltiple (chips) de clientes/proveedores.
function RC_setupBuscadorEntidad() {
    const input = document.getElementById('rc-search-entidad');
    const dropdown = document.getElementById('rc-dropdown-entidad');
    const chips = document.getElementById('rc-chips-entidad');
    if (!input || !dropdown || !chips) return;

    let debounceTimer;
    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        if (q.length < 2) { dropdown.classList.add('d-none'); return; }

        debounceTimer = setTimeout(() => {
            fetch(BASE_URL + '/' + RUTA_MODULO + '/' + RC_endpointEntidad() + '?q=' + encodeURIComponent(q))
                .then(res => res.json())
                .then(data => {
                    dropdown.innerHTML = '';
                    const items = data.data || [];
                    if (items.length > 0) {
                        items.forEach(item => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-2';
                            btn.style.fontSize = '0.85rem';
                            btn.innerHTML = `<strong>${item.nombre}</strong><br><small class="text-muted">${item.identificacion || ''}</small>`;

                            btn.addEventListener('click', function () {
                                input.value = '';
                                dropdown.classList.add('d-none');

                                if (!chips.querySelector(`input[value="${item.id}"]`)) {
                                    const chip = document.createElement('span');
                                    chip.className = 'badge bg-primary bg-opacity-10 text-primary border border-primary d-flex align-items-center justify-content-between mb-1 text-start';
                                    chip.style.cssText = 'font-size:0.75rem;width:100%;white-space:normal;';
                                    chip.innerHTML = `
                                        <span class="text-truncate me-2">${item.nombre}</span>
                                        <input type="hidden" name="id_entidad[]" value="${item.id}">
                                        <button type="button" class="btn-close btn-close-sm flex-shrink-0" style="font-size:0.5rem;"></button>
                                    `;
                                    chip.querySelector('button').addEventListener('click', function () {
                                        chip.remove();
                                        window.RC_limpiarDocumento(false);
                                        window.RC_generarReporte();
                                    });
                                    chips.appendChild(chip);
                                    window.RC_limpiarDocumento(false);
                                    window.RC_generarReporte();
                                }
                            });

                            dropdown.appendChild(btn);
                        });
                        dropdown.classList.remove('d-none');
                    } else {
                        dropdown.innerHTML = '<div class="list-group-item text-muted small">Sin resultados</div>';
                        dropdown.classList.remove('d-none');
                    }
                })
                .catch(err => console.error(err));
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('d-none');
        }
    });
}

// ── Filtro "Documento": typeahead sobre los documentos (facturas, recibos,
// compras, saldos iniciales...) de los clientes/proveedores seleccionados.
// Al elegir uno queda fijado (input visible = etiqueta, hidden = número);
// Backspace/Delete con una selección fija limpia todo el filtro de una vez.
function RC_setupBuscadorDocumento() {
    const input = document.getElementById('rc-search-documento');
    const hidden = document.getElementById('rc-documento');
    const dropdown = document.getElementById('rc-dropdown-documento');
    if (!input || !hidden || !dropdown) return;

    let debounceTimer;

    const buscar = function (q) {
        const form = document.getElementById('form-filtros-reporte');
        const params = new URLSearchParams(new FormData(form));
        params.delete('documento');
        params.set('q', q);

        fetch(BASE_URL + '/' + RUTA_MODULO + '/getDocumentosAjax?' + params.toString())
            .then(res => res.json())
            .then(data => {
                dropdown.innerHTML = '';
                const items = data.data || [];
                if (items.length === 0) {
                    dropdown.innerHTML = '<div class="list-group-item text-muted small">' + (data.mensaje || 'Sin documentos') + '</div>';
                    dropdown.classList.remove('d-none');
                    return;
                }
                items.forEach(item => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action py-1';
                    btn.style.fontSize = '0.8rem';
                    btn.innerHTML = `<strong>${item.numero}</strong> <span class="text-muted">· ${item.origen} · ${item.fecha} · $${item.total}</span>`
                        + (item.entidad ? `<br><small class="text-muted">${item.entidad}</small>` : '');
                    btn.addEventListener('click', function () {
                        hidden.value = item.numero;
                        input.value = item.numero + ' · ' + item.origen;
                        input.classList.add('bg-light', 'fw-bold');
                        dropdown.classList.add('d-none');
                        window.RC_generarReporte();
                    });
                    dropdown.appendChild(btn);
                });
                dropdown.classList.remove('d-none');
            })
            .catch(err => console.error(err));
    };

    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        if (hidden.value !== '') return; // con selección fija no se escribe: se limpia con Backspace/Delete
        const q = this.value.trim();
        debounceTimer = setTimeout(() => buscar(q), 300);
    });

    // Al enfocar sin selección, mostrar los últimos documentos de la entidad
    input.addEventListener('focus', function () {
        if (hidden.value === '') buscar(this.value.trim());
    });

    input.addEventListener('keydown', function (e) {
        if ((e.key === 'Backspace' || e.key === 'Delete') && hidden.value !== '') {
            e.preventDefault();
            window.RC_limpiarDocumento(true);
        }
    });

    document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('d-none');
        }
    });
}

// Limpia el filtro de documento (input visible + hidden + dropdown).
// $regenerar: volver a generar el reporte tras limpiar.
window.RC_limpiarDocumento = function (regenerar) {
    const input = document.getElementById('rc-search-documento');
    const hidden = document.getElementById('rc-documento');
    const dropdown = document.getElementById('rc-dropdown-documento');
    if (!input || !hidden) return;
    const habia = hidden.value !== '' || input.value !== '';
    hidden.value = '';
    input.value = '';
    input.classList.remove('bg-light', 'fw-bold');
    if (dropdown) dropdown.classList.add('d-none');
    if (regenerar && habia) window.RC_generarReporte();
};

// Parámetros del formulario; con idEntidad, limita a ESA entidad (acciones
// del encabezado de cada estado de cuenta) ignorando "Todos".
function RC_paramsExport(idEntidad) {
    const form = document.getElementById('form-filtros-reporte');
    const params = new URLSearchParams(new FormData(form));
    if (idEntidad) {
        params.delete('id_entidad[]');
        params.delete('todos');
        params.append('id_entidad[]', idEntidad);
    }
    return params;
}

window.RC_cambiarTipo = function () {
    const esProveedor = document.getElementById('rc_tipo').value === 'PROVEEDOR';
    document.getElementById('rc-label-entidad').textContent = esProveedor ? 'Proveedor(es)' : 'Cliente(s)';
    document.getElementById('rc-search-entidad').placeholder = esProveedor ? 'Buscar proveedores...' : 'Buscar clientes...';
    document.getElementById('rc-label-stat-entidades').textContent = esProveedor ? 'Proveedores' : 'Clientes';
    document.getElementById('rc-label-todos').textContent = esProveedor
        ? 'Todos los proveedores con saldo pendiente'
        : 'Todos los clientes con saldo pendiente';

    // Al cambiar de tipo, la selección previa (clientes u proveedores) ya no aplica
    document.getElementById('rc-chips-entidad').innerHTML = '';
    window.RC_limpiarDocumento(false);
    window.RC_generarReporte();
};

// "Todos" reemplaza a la selección manual: bloquea el buscador de entidades
// mientras esté activo (el servidor resuelve la lista con saldo pendiente).
window.RC_toggleTodos = function () {
    const activo = document.getElementById('rc-todos').checked;
    document.getElementById('rc-search-entidad').disabled = activo;
    document.getElementById('rc-chips-entidad').classList.toggle('d-none', activo);
    window.RC_limpiarDocumento(false);
    window.RC_generarReporte();
};

window.RC_cambiarMesAnio = function () {
    const mes = document.getElementById('rc-mes').value;
    const anio = document.getElementById('rc-anio').value;
    if (!mes || !anio) return;

    if (anio === 'TODOS') {
        document.getElementById('rc-fecha-desde').value = '';
        document.getElementById('rc-fecha-hasta').value = '';
    } else if (mes === 'TODOS') {
        document.getElementById('rc-fecha-desde').value = anio + '-01-01';
        document.getElementById('rc-fecha-hasta').value = anio + '-12-31';
    } else {
        const fechaHasta = new Date(anio, parseInt(mes), 0);
        document.getElementById('rc-fecha-desde').value = anio + '-' + mes + '-01';
        document.getElementById('rc-fecha-hasta').value = anio + '-' + mes + '-' + fechaHasta.getDate().toString().padStart(2, '0');
    }

    window.RC_generarReporte();
};

window.RC_generarReporte = function () {
    const form = document.getElementById('form-filtros-reporte');
    const params = new URLSearchParams(new FormData(form)).toString();

    const cont = document.getElementById('rc-resultados');
    cont.innerHTML = `<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><br><span class="text-muted small mt-2 d-inline-block">Generando estado de cuenta...</span></div>`;

    fetch(BASE_URL + '/' + RUTA_MODULO + '/generarAjax', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: params
    })
    .then(async response => {
        const text = await response.text();
        try { return JSON.parse(text); }
        catch (e) {
            console.error("Parse error. Raw response:", text.substring(0, 500));
            throw new Error(text.substring(0, 100));
        }
    })
    .then(res => {
        if (res.ok) {
            cont.innerHTML = res.html;
            if (res.stats) {
                document.getElementById('stat-entidades').textContent = res.stats.entidades;
                document.getElementById('stat-cargos').textContent = parseFloat(res.stats.total_cargos).toFixed(2);
                document.getElementById('stat-abonos').textContent = parseFloat(res.stats.total_abonos).toFixed(2);
                document.getElementById('stat-saldo').textContent = parseFloat(res.stats.saldo_total).toFixed(2);
            }
        } else {
            Swal.fire('Error', res.error || 'Ocurrió un error al generar el reporte', 'error');
            cont.innerHTML = `<div class="text-center py-4 text-danger">Error al generar reporte.</div>`;
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error del Servidor', 'Detalle: ' + err.message, 'error');
        cont.innerHTML = `<div class="text-center py-4 text-danger">Error de conexión.</div>`;
    });
};

window.RC_exportarExcel = function (idEntidad) {
    window.open(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + RC_paramsExport(idEntidad).toString(), '_blank');
};

window.RC_exportarPDF = function (idEntidad) {
    window.open(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + RC_paramsExport(idEntidad).toString(), '_blank');
};

// Abre el modal de correo para la entidad del encabezado; precarga su email.
window.RC_abrirModalCorreo = function (idEntidad, email) {
    document.getElementById('rc-correo-id-entidad').value = idEntidad || '';
    const dest = document.getElementById('rc-correo-destinatarios');
    if (email && !dest.value.trim()) dest.value = email;
    const modal = new bootstrap.Modal(document.getElementById('modalCorreoReporte'));
    modal.show();
};

window.RC_enviarCorreo = function () {
    const correos = document.getElementById('rc-correo-destinatarios').value.trim();
    if (!correos) {
        Swal.fire('Atención', 'Ingrese al menos un correo destinatario.', 'warning');
        return;
    }

    const adjuntar = document.getElementById('rc-correo-adjuntar').value;
    const idEntidad = parseInt(document.getElementById('rc-correo-id-entidad').value, 10) || 0;
    const params = RC_paramsExport(idEntidad);
    params.set('correos', correos);
    params.set('adjuntar', adjuntar);

    const btn = document.getElementById('btn-enviar-correo-reporte');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...';

    fetch(BASE_URL + '/' + RUTA_MODULO + '/enviarCorreoAjax', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: params.toString()
    })
    .then(res => res.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar';
        if (res.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalCorreoReporte')).hide();
            Swal.fire('Enviado', res.mensaje, 'success');
        } else {
            Swal.fire('Error', res.mensaje || 'No se pudo enviar el correo.', 'error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar';
        Swal.fire('Error del Servidor', 'Detalle: ' + err.message, 'error');
    });
};
