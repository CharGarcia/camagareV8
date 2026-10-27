// Control de vista para Productos Más Vendidos
document.addEventListener('DOMContentLoaded', function () {
    // Favoritos (tipo de gráfico) antes del primer reporte, para que el gráfico ya salga con el elegido.
    if (typeof aplicarFavoritosModal === 'function') {
        aplicarFavoritosModal();
    }

    // Setear mes actual por defecto
    if (!document.getElementById('pmv-mes').value || document.getElementById('pmv-mes').value === 'TODOS') {
        const currentMonth = (new Date().getMonth() + 1).toString().padStart(2, '0');
        document.getElementById('pmv-mes').value = currentMonth;
    }

    // Buscador predictivo de Clientes (multi-selección con chips)
    PMV_setupBuscadorChips({
        inputId: 'pmv-search-cliente',
        dropdownId: 'pmv-dropdown-clientes',
        chipsId: 'pmv-chips-cliente',
        campoOculto: 'id_cliente[]',
        endpoint: 'getClientesAjax',
        labelFn: item => item.nombre || item.text,
        subLabelFn: item => item.identificacion || item.ruc || ''
    });

    // Buscador predictivo de Productos (multi-selección con chips)
    PMV_setupBuscadorChips({
        inputId: 'pmv-search-producto',
        dropdownId: 'pmv-dropdown-productos',
        chipsId: 'pmv-chips-producto',
        campoOculto: 'id_producto[]',
        endpoint: 'buscarProductosAjax',
        labelFn: item => item.nombre || item.text,
        subLabelFn: item => item.codigo || ''
    });

    document.getElementById('pmv-mes').addEventListener('change', window.PMV_cambiarMesAnio);
    document.getElementById('pmv-anio').addEventListener('change', window.PMV_cambiarMesAnio);

    // Generar el reporte inicial con los filtros por defecto
    window.PMV_generarReporte();
});

// Buscador predictivo genérico con selección múltiple (chips), reutilizado para Cliente y Producto.
function PMV_setupBuscadorChips({ inputId, dropdownId, chipsId, campoOculto, endpoint, labelFn, subLabelFn }) {
    const input = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    const chips = document.getElementById(chipsId);
    if (!input || !dropdown || !chips) return;

    let debounceTimer;
    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        if (q.length < 2) { dropdown.classList.add('d-none'); return; }

        debounceTimer = setTimeout(() => {
            fetch(BASE_URL + '/' + RUTA_MODULO + '/' + endpoint + '?q=' + encodeURIComponent(q))
                .then(res => res.json())
                .then(data => {
                    dropdown.innerHTML = '';
                    const items = data.data || [];
                    if (items.length > 0) {
                        items.forEach(item => {
                            const label = labelFn(item);
                            const sub = subLabelFn(item);
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-2';
                            btn.style.fontSize = '0.85rem';
                            btn.innerHTML = `<strong>${label}</strong><br><small class="text-muted">${sub}</small>`;

                            btn.addEventListener('click', function () {
                                input.value = '';
                                dropdown.classList.add('d-none');

                                if (!chips.querySelector(`input[value="${item.id}"]`)) {
                                    const chip = document.createElement('span');
                                    chip.className = 'badge bg-primary bg-opacity-10 text-primary border border-primary d-flex align-items-center justify-content-between mb-1 text-start';
                                    chip.style.cssText = 'font-size:0.75rem;width:100%;white-space:normal;';
                                    chip.innerHTML = `
                                        <span class="text-truncate me-2">${label}</span>
                                        <input type="hidden" name="${campoOculto}" value="${item.id}">
                                        <button type="button" class="btn-close btn-close-sm flex-shrink-0" style="font-size:0.5rem;"></button>
                                    `;
                                    chip.querySelector('button').addEventListener('click', function () {
                                        chip.remove();
                                        window.PMV_generarReporte();
                                    });
                                    chips.appendChild(chip);
                                    window.PMV_generarReporte();
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

// Sincroniza el input libre de "Top" (o el checkbox "Mostrar todos") con el
// campo oculto top_n que realmente viaja en el formulario.
window.PMV_syncTopN = function () {
    const num = document.getElementById('pmv_top_n');
    const hidden = document.getElementById('pmv_top_n_hidden');
    const todos = document.getElementById('pmv_top_todos').checked;

    if (todos) {
        hidden.value = 'TODOS';
        return;
    }

    let v = parseInt(num.value, 10);
    if (!v || v < 1) v = 20;
    num.value = v;
    hidden.value = String(v);
};

window.PMV_toggleTopTodos = function () {
    const todos = document.getElementById('pmv_top_todos').checked;
    document.getElementById('pmv_top_n').disabled = todos;
    window.PMV_syncTopN();
    window.PMV_generarReporte();
};

window.PMV_cambiarMesAnio = function () {
    const mes = document.getElementById('pmv-mes').value;
    const anio = document.getElementById('pmv-anio').value;
    if (!mes || !anio) return;

    if (anio === 'TODOS') {
        document.getElementById('pmv-fecha-desde').value = '';
        document.getElementById('pmv-fecha-hasta').value = '';
    } else if (mes === 'TODOS') {
        document.getElementById('pmv-fecha-desde').value = anio + '-01-01';
        document.getElementById('pmv-fecha-hasta').value = anio + '-12-31';
    } else {
        const fechaHasta = new Date(anio, parseInt(mes), 0);
        document.getElementById('pmv-fecha-desde').value = anio + '-' + mes + '-01';
        document.getElementById('pmv-fecha-hasta').value = anio + '-' + mes + '-' + fechaHasta.getDate().toString().padStart(2, '0');
    }

    window.PMV_generarReporte();
};

window.PMV_generarReporte = function () {
    const form = document.getElementById('form-filtros-reporte');
    const params = new URLSearchParams(new FormData(form)).toString();
    // El detalle de un producto usa los filtros con que se generó el ranking que se está viendo.
    PMV_paramsRanking = params;

    const tbody = document.getElementById('pmv_tbody');
    tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><br><span class="text-muted small mt-2 d-inline-block">Generando reporte...</span></td></tr>`;

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
            tbody.innerHTML = res.rows;

            if (res.stats) {
                document.getElementById('stat-productos').textContent = res.stats.productos_distintos;
                document.getElementById('stat-unidades').textContent = parseFloat(res.stats.total_unidades).toLocaleString('es-EC', { maximumFractionDigits: 2 });
                document.getElementById('stat-total').textContent = parseFloat(res.stats.total_vendido).toFixed(2);
                document.getElementById('stat-top1-nombre').textContent = res.stats.top1_nombre || '—';
                document.getElementById('stat-top1-cantidad').textContent = res.stats.top1_nombre
                    ? (parseFloat(res.stats.top1_cantidad).toLocaleString('es-EC', { maximumFractionDigits: 2 }) + ' unidades')
                    : '';
            }

            const chartContainer = document.getElementById('chart-container');
            if (res.rawData && res.rawData.length > 0) {
                chartContainer.style.display = 'flex';
                PMV_dibujarGrafico(res.rawData);
            } else {
                chartContainer.style.display = 'none';
            }
        } else {
            Swal.fire('Error', res.error || 'Ocurrió un error al generar el reporte', 'error');
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">Error al generar reporte.</td></tr>`;
            document.getElementById('chart-container').style.display = 'none';
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error del Servidor', 'Detalle: ' + err.message, 'error');
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">Error de conexión.</td></tr>`;
        document.getElementById('chart-container').style.display = 'none';
    });
};

let PMV_paramsRanking = '';
let PMV_detallePeticion = 0;
let PMV_detalleProducto = null;

const PMV_esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const PMV_dinero = v => (v < 0 ? '-$' : '$') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const PMV_cantidad = v => v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** Abre el modal con los clientes que compraron el producto de la fila (mismos filtros del ranking). */
window.PMV_verDetalle = function (fila) {
    PMV_detalleProducto = {
        id: fila.dataset.idProducto || '',
        nombre: fila.dataset.nombre || '',
        codigo: fila.dataset.codigo || ''
    };
    const desde = document.getElementById('pmv-fecha-desde').value;
    const hasta = document.getElementById('pmv-fecha-hasta').value;
    const fmtFecha = f => f ? f.split('-').reverse().join('-') : '';

    document.getElementById('pmv-det-producto').textContent = PMV_detalleProducto.nombre;
    document.getElementById('pmv-det-subtitulo').textContent = (PMV_detalleProducto.codigo ? PMV_detalleProducto.codigo + ' · ' : '')
        + (desde || hasta ? 'Período del ' + fmtFecha(desde) + ' al ' + fmtFecha(hasta) : 'Todo el período');
    document.getElementById('pmv-det-buscar').value = '';
    document.getElementById('pmv-det-resumen').textContent = '';
    document.getElementById('pmv-det-tfoot').innerHTML = '';
    const tbody = document.getElementById('pmv-det-tbody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalleProducto')).show();

    // Si se abre otro producto antes de que responda el anterior, la respuesta vieja se descarta.
    const peticion = ++PMV_detallePeticion;
    fetch(BASE_URL + '/' + RUTA_MODULO + '/detalleClientesAjax', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: PMV_paramsDetalle().toString()
    })
    .then(r => r.json())
    .then(res => {
        if (peticion !== PMV_detallePeticion) return;
        if (!res.ok) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">' + PMV_esc(res.error || 'No se pudo cargar el detalle.') + '</td></tr>';
            return;
        }
        PMV_pintarDetalle(res.rows || []);
    })
    .catch(err => {
        if (peticion !== PMV_detallePeticion) return;
        console.error(err);
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Error de conexión.</td></tr>';
    });
};

function PMV_paramsDetalle() {
    const params = new URLSearchParams(PMV_paramsRanking);
    params.set('det_id_producto', PMV_detalleProducto.id);
    params.set('det_nombre', PMV_detalleProducto.nombre);
    params.set('det_codigo', PMV_detalleProducto.codigo);
    return params;
}

function PMV_pintarDetalle(rows) {
    const tbody = document.getElementById('pmv-det-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No se encontraron clientes para este producto.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(r => {
        const buscar = (r.cliente_ruc + ' ' + r.cliente_nombre).toLowerCase();
        return '<tr data-buscar="' + PMV_esc(buscar) + '">'
            + '<td class="ps-3 text-nowrap">' + PMV_esc(r.cliente_ruc) + '</td>'
            + '<td>' + PMV_esc(r.cliente_nombre) + '</td>'
            + '<td class="text-end fw-bold">' + PMV_cantidad(parseFloat(r.cantidad_vendida) || 0) + '</td>'
            + '<td class="text-center">' + (parseInt(r.num_documentos) || 0) + '</td>'
            + '<td class="text-end fw-bold text-success">' + PMV_dinero(parseFloat(r.total_vendido) || 0) + '</td>'
            + '<td class="text-end pe-3">' + (parseFloat(r.pct) || 0).toFixed(2) + '%</td>'
            + '</tr>';
    }).join('');

    const totCant = rows.reduce((s, r) => s + (parseFloat(r.cantidad_vendida) || 0), 0);
    const totVenta = CMG_r2(rows.reduce((s, r) => s + (parseFloat(r.total_vendido) || 0), 0));
    document.getElementById('pmv-det-tfoot').innerHTML =
        '<tr><td colspan="2" class="ps-3">TOTAL (' + rows.length + ' ' + (rows.length === 1 ? 'cliente' : 'clientes') + ')</td>'
        + '<td class="text-end">' + PMV_cantidad(totCant) + '</td><td></td>'
        + '<td class="text-end">' + PMV_dinero(totVenta) + '</td><td class="pe-3"></td></tr>';
    PMV_filtrarDetalle();
}

/** PDF/Excel del detalle abierto: mismos filtros del ranking y, si hay texto en el buscador, solo lo filtrado. */
window.PMV_exportarDetalle = function (accion) {
    if (!PMV_detalleProducto) return;
    const params = PMV_paramsDetalle();
    params.set('buscar', document.getElementById('pmv-det-buscar').value.trim());
    CMG_descargar(BASE_URL + '/' + RUTA_MODULO + '/' + accion + '?' + params.toString());
};

window.PMV_filtrarDetalle = function () {
    const q = document.getElementById('pmv-det-buscar').value.trim().toLowerCase();
    const filas = document.querySelectorAll('#pmv-det-tbody tr[data-buscar]');
    let visibles = 0;
    filas.forEach(tr => {
        const ok = q === '' || q.split(/\s+/).every(p => tr.dataset.buscar.includes(p));
        tr.style.display = ok ? '' : 'none';
        if (ok) visibles++;
    });
    document.getElementById('pmv-det-resumen').textContent = filas.length
        ? (q === '' ? filas.length + (filas.length === 1 ? ' cliente' : ' clientes') : visibles + ' de ' + filas.length + ' clientes')
        : '';
};

window.pmv_last_raw_data = null;
let chartInstance = null;

// Acordeón del gráfico (cerrado por defecto). Chart.js no puede medir un canvas oculto:
// el gráfico se dibuja igual al generar el reporte y se reajusta al ancho real al abrirse.
document.addEventListener('DOMContentLoaded', function () {
    const cuerpo  = document.getElementById('pmv-grafico-body');
    const chevron = document.getElementById('pmv-grafico-chevron');
    if (!cuerpo) return;
    cuerpo.addEventListener('shown.bs.collapse', function () {
        if (chevron) chevron.className = 'bi bi-chevron-up';
        if (chartInstance) chartInstance.resize();
    });
    cuerpo.addEventListener('hidden.bs.collapse', function () {
        if (chevron) chevron.className = 'bi bi-chevron-down';
    });
});

window.PMV_cambiarTipoGrafico = function () {
    if (window.pmv_last_raw_data) {
        PMV_dibujarGrafico(window.pmv_last_raw_data);
    }
};

function PMV_generarColores(cantidad) {
    const paleta = [
        'rgba(54, 162, 235, 0.7)', 'rgba(255, 99, 132, 0.7)', 'rgba(75, 192, 192, 0.7)',
        'rgba(255, 206, 86, 0.7)', 'rgba(153, 102, 255, 0.7)', 'rgba(255, 159, 64, 0.7)',
        'rgba(201, 203, 207, 0.7)', 'rgba(100, 200, 100, 0.7)', 'rgba(200, 100, 200, 0.7)',
        'rgba(100, 100, 200, 0.7)', 'rgba(200, 200, 100, 0.7)', 'rgba(100, 200, 200, 0.7)'
    ];
    let colores = [];
    for (let i = 0; i < cantidad; i++) {
        colores.push(paleta[i % paleta.length]);
    }
    return colores;
}

function PMV_dibujarGrafico(rawData) {
    window.pmv_last_raw_data = rawData;

    const ctx = document.getElementById('reporteChart').getContext('2d');
    if (chartInstance) chartInstance.destroy();

    const top = rawData.slice(0, 15);
    const labels = top.map(r => r.producto_nombre);
    const cantidades = top.map(r => parseFloat(r.cantidad_vendida));

    // Automático = barras horizontales: los nombres de productos son largos y se leen mejor en el eje vertical.
    const selectEl = document.getElementById('pmv-tipo-grafico');
    const elegido = selectEl ? selectEl.value : 'auto';
    const horizontal = elegido === 'auto';
    const type = horizontal ? 'bar' : elegido;
    const circular = type === 'pie' || type === 'doughnut';

    let backgroundColor = 'rgba(54, 162, 235, 0.5)';
    let borderColor = 'rgba(54, 162, 235, 1)';
    if (circular) {
        backgroundColor = PMV_generarColores(labels.length);
        borderColor = backgroundColor.map(c => c.replace('0.7', '1'));
    }

    const ejeValor = { beginAtZero: true };

    chartInstance = new Chart(ctx, {
        type: type,
        data: {
            labels: labels,
            datasets: [{
                label: 'Cantidad Vendida',
                data: cantidades,
                backgroundColor: backgroundColor,
                borderColor: borderColor,
                borderWidth: circular ? 1 : 2,
                tension: 0.3,
                fill: type === 'line'
            }]
        },
        options: {
            indexAxis: horizontal ? 'y' : 'x',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: circular || type === 'line' }
            },
            scales: circular ? {} : (horizontal ? { x: ejeValor } : { y: ejeValor })
        }
    });
}

window.PMV_exportarExcel = function () {
    const form = document.getElementById('form-filtros-reporte');
    const params = new URLSearchParams(new FormData(form)).toString();
    CMG_descargar(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params);
};

window.PMV_exportarPDF = function () {
    const form = document.getElementById('form-filtros-reporte');
    const params = new URLSearchParams(new FormData(form)).toString();
    CMG_descargar(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params);
};

window.PMV_abrirModalCorreo = function () {
    const modal = new bootstrap.Modal(document.getElementById('modalCorreoReporte'));
    modal.show();
};

window.PMV_enviarCorreo = function () {
    const correos = document.getElementById('pmv-correo-destinatarios').value.trim();
    if (!correos) {
        Swal.fire('Atención', 'Ingrese al menos un correo destinatario.', 'warning');
        return;
    }

    const adjuntar = document.getElementById('pmv-correo-adjuntar').value;
    const form = document.getElementById('form-filtros-reporte');
    const params = new URLSearchParams(new FormData(form));
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
