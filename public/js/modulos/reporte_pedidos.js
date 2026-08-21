// Control de vista para Reporte de Pedidos (mismo patrón que Reporte de Ventas)
document.addEventListener('DOMContentLoaded', function () {
    if (typeof aplicarFavoritosModal === 'function') {
        aplicarFavoritosModal();
    }

    // Buscador predictivo de Clientes (chips, multi-selección)
    let debounceTimerCliente;
    const searchCliente = document.getElementById('rp-search-cliente');
    const dropdownCliente = document.getElementById('rp-dropdown-clientes');
    const chipsCliente = document.getElementById('rp-chips-cliente');

    searchCliente.addEventListener('input', function () {
        clearTimeout(debounceTimerCliente);
        const search = this.value.trim();

        if (search.length < 2) {
            dropdownCliente.classList.add('d-none');
            return;
        }

        debounceTimerCliente = setTimeout(() => {
            fetch(BASE_URL + '/' + RUTA_MODULO + '/getClientesAjax?q=' + encodeURIComponent(search))
                .then(res => res.json())
                .then(data => {
                    dropdownCliente.innerHTML = '';
                    const items = data.data || [];
                    if (items.length > 0) {
                        items.forEach(item => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-2';
                            btn.style.fontSize = '0.85rem';
                            const nombre = item.nombre || item.text;
                            btn.innerHTML = `<strong>${nombre}</strong><br><small class="text-muted">${item.identificacion || ''}</small>`;

                            btn.addEventListener('click', function () {
                                searchCliente.value = '';
                                dropdownCliente.classList.add('d-none');

                                if (!chipsCliente.querySelector(`input[value="${item.id}"]`)) {
                                    const chip = document.createElement('span');
                                    chip.className = 'badge bg-primary bg-opacity-10 text-primary border border-primary d-flex align-items-center justify-content-between mb-1 text-start';
                                    chip.style.fontSize = '0.75rem';
                                    chip.style.width = '100%';
                                    chip.style.whiteSpace = 'normal';
                                    chip.innerHTML = `
                                        <span class="text-truncate me-2">${nombre}</span>
                                        <input type="hidden" name="id_cliente[]" value="${item.id}">
                                        <button type="button" class="btn-close btn-close-sm flex-shrink-0" style="font-size:0.5rem;"></button>
                                    `;
                                    chip.querySelector('button').addEventListener('click', function () {
                                        chip.remove();
                                        window.RP_generarReporte();
                                    });
                                    chipsCliente.appendChild(chip);
                                    window.RP_generarReporte();
                                }
                            });

                            dropdownCliente.appendChild(btn);
                        });
                        dropdownCliente.classList.remove('d-none');
                    } else {
                        dropdownCliente.innerHTML = '<div class="list-group-item text-muted small">No se encontraron clientes</div>';
                        dropdownCliente.classList.remove('d-none');
                    }
                })
                .catch(err => console.error(err));
        }, 300);
    });

    // Buscador predictivo de Producto (nombre o código)
    RP_predictivoTexto('rp-producto-texto', 'rp-dropdown-items', 'buscarItemsAjax', 'Sin ítems que coincidan');

    document.addEventListener('click', function (e) {
        if (!searchCliente.contains(e.target) && !dropdownCliente.contains(e.target)) {
            dropdownCliente.classList.add('d-none');
        }
        const inp = document.getElementById('rp-producto-texto');
        const dd  = document.getElementById('rp-dropdown-items');
        if (inp && dd && !inp.contains(e.target) && !dd.contains(e.target)) dd.classList.add('d-none');
    });

    document.getElementById('rp-mes').addEventListener('change', window.RP_cambiarMesAnio);
    document.getElementById('rp-anio').addEventListener('change', window.RP_cambiarMesAnio);
});

// Buscador predictivo genérico de texto: rellena el input con el valor elegido y regenera.
function RP_predictivoTexto(inputId, dropdownId, endpoint, msgVacio) {
    const input = document.getElementById(inputId);
    const dd    = document.getElementById(dropdownId);
    if (!input || !dd) return;
    let timer;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 2) { dd.classList.add('d-none'); return; }
        timer = setTimeout(() => {
            fetch(BASE_URL + '/' + RUTA_MODULO + '/' + endpoint + '?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    dd.innerHTML = '';
                    const items = data.data || [];
                    if (items.length) {
                        items.forEach(it => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-2 px-3';
                            btn.style.cssText = 'font-size:.82rem;white-space:normal;line-height:1.25;word-break:break-word;';
                            if (it.sub) {
                                const s = document.createElement('small');
                                s.className = 'text-muted text-uppercase d-block';
                                s.style.cssText = 'font-size:.58rem;letter-spacing:.02em;';
                                s.textContent = it.sub;
                                btn.appendChild(s);
                            }
                            const main = document.createElement('span');
                            main.textContent = it.label;
                            btn.appendChild(main);
                            btn.title = it.sub ? (it.sub + ': ' + it.label) : it.label;
                            btn.addEventListener('click', function () {
                                input.value = it.valor;
                                dd.classList.add('d-none');
                                window.RP_generarReporte();
                            });
                            dd.appendChild(btn);
                        });
                    } else {
                        dd.innerHTML = `<div class="list-group-item text-muted small">${msgVacio}</div>`;
                    }
                    dd.classList.remove('d-none');
                })
                .catch(err => console.error(err));
        }, 300);
    });
}

// Maneja el cambio de "Agrupar Por": al elegir "Por Mes" se fuerza el filtro Mes a "Todos".
window.RP_onAgruparChange = function () {
    const agruparPor = document.getElementById('rp_agrupar_por').value;
    const mesEl = document.getElementById('rp-mes');

    if (agruparPor === 'MES') {
        mesEl.disabled = true;
        if (mesEl.value !== 'TODOS') {
            mesEl.value = 'TODOS';
            window.RP_cambiarMesAnio();
            return;
        }
    } else {
        mesEl.disabled = false;
    }
    window.RP_generarReporte();
};

window.RP_cambiarMesAnio = function () {
    const mes = document.getElementById('rp-mes').value;
    const anio = document.getElementById('rp-anio').value;

    if (!mes || !anio) return;

    if (anio === 'TODOS') {
        document.getElementById('rp-fecha-desde').value = '';
        document.getElementById('rp-fecha-hasta').value = '';
    } else if (mes === 'TODOS') {
        document.getElementById('rp-fecha-desde').value = anio + '-01-01';
        document.getElementById('rp-fecha-hasta').value = anio + '-12-31';
    } else {
        const fechaHasta = new Date(anio, parseInt(mes), 0);
        document.getElementById('rp-fecha-desde').value = anio + '-' + mes + '-01';
        document.getElementById('rp-fecha-hasta').value = anio + '-' + mes + '-' + fechaHasta.getDate().toString().padStart(2, '0');
    }

    window.RP_generarReporte();
};

// Columnas por agrupación: [colSpan, [encabezados]]
const RP_COLUMNAS = {
    NINGUNO:     ['Fecha', 'Nro Pedido', 'Cliente', 'Estado', 'Fecha Entrega', 'Resp. Entrega', 'Cant. Pedida'],
    CLIENTE:     ['Cliente', 'Nro Pedidos', 'Cant. Pedida'],
    PRODUCTO:    ['Producto', 'Nro Pedidos', 'Cant. Pedida'],
    ESTADO:      ['Estado', 'Nro Pedidos', 'Cant. Pedida'],
    RESPONSABLE: ['Resp. Entrega', 'Nro Pedidos', 'Cant. Pedida'],
    FECHA:       ['Fecha', 'Nro Pedidos', 'Cant. Pedida'],
    MES:         ['Mes', 'Nro Pedidos', 'Cant. Pedida'],
};

function RP_dibujarCabecera(agruparPor) {
    const cols = RP_COLUMNAS[agruparPor] || RP_COLUMNAS.NINGUNO;
    let theadHtml = '<tr class="text-secondary" style="font-family: \'Outfit\', sans-serif;">';
    cols.forEach((c, i) => {
        const clase = (i === 0) ? 'ps-4' : (i === cols.length - 1 ? 'text-end pe-4' : (c.indexOf('Nro') === 0 ? 'text-center' : ''));
        theadHtml += `<th class="${clase}">${c}</th>`;
    });
    theadHtml += '</tr>';
    document.getElementById('rp_thead').innerHTML = theadHtml;
}

// Función principal para pedir los datos via AJAX
window.RP_generarReporte = function () {
    const form = document.getElementById('form-filtros-reporte');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData).toString();

    const agruparPor = document.getElementById('rp_agrupar_por').value;
    RP_dibujarCabecera(agruparPor);

    const tbody = document.getElementById('rp_tbody');
    const colSpanActual = (RP_COLUMNAS[agruparPor] || RP_COLUMNAS.NINGUNO).length;
    tbody.innerHTML = `<tr><td colspan="${colSpanActual}" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><br><span class="text-muted small mt-2 d-inline-block">Generando reporte...</span></td></tr>`;

    fetch(BASE_URL + '/' + RUTA_MODULO + '/generarAjax', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: params
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error("Parse error. Raw response:", text.substring(0, 500));
            throw new Error(text.substring(0, 100));
        }
    })
    .then(res => {
        if (res.ok) {
            tbody.innerHTML = res.rows;

            if (res.stats) {
                document.getElementById('stat-pedidos').textContent = res.stats.total_pedidos;
                document.getElementById('stat-cantidad').textContent = parseFloat(res.stats.total_cantidad).toFixed(2);
                document.getElementById('stat-clientes').textContent = res.stats.total_clientes;
            }
            if (res.estados) {
                document.getElementById('stat-pendientes').textContent = res.estados.pendientes;
                document.getElementById('stat-procesados').textContent = res.estados.procesados;
                document.getElementById('stat-anulados').textContent   = res.estados.anulados;
            }

            const chartContainer = document.getElementById('chart-container');
            if (res.rawData && res.rawData.length > 0) {
                chartContainer.style.display = 'flex';
                RP_dibujarGrafico(res.rawData, res.agrupacion);
            } else {
                chartContainer.style.display = 'none';
            }
        } else {
            Swal.fire('Error', res.error || 'Ocurrió un error al generar el reporte', 'error');
            tbody.innerHTML = `<tr><td colspan="${colSpanActual}" class="text-center py-4 text-danger">Error al generar reporte.</td></tr>`;
            document.getElementById('chart-container').style.display = 'none';
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error del Servidor', 'Detalle: ' + err.message, 'error');
        tbody.innerHTML = `<tr><td colspan="${colSpanActual}" class="text-center py-4 text-danger">Error de conexión.</td></tr>`;
        document.getElementById('chart-container').style.display = 'none';
    });
};

window.rp_last_raw_data = null;
window.rp_last_agrupacion = null;
let rpChartInstance = null;

window.RP_cambiarTipoGrafico = function () {
    if (window.rp_last_raw_data) {
        RP_dibujarGrafico(window.rp_last_raw_data, window.rp_last_agrupacion);
    }
};

function RP_formatearMes(mes) {
    if (!mes || mes.indexOf('-') === -1) return mes || '';
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const [anio, num] = mes.split('-');
    return (meses[parseInt(num, 10) - 1] || num) + ' ' + anio;
}

function RP_generarColores(cantidad) {
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

function RP_dibujarGrafico(rawData, agrupacion) {
    window.rp_last_raw_data = rawData;
    window.rp_last_agrupacion = agrupacion;

    const ctx = document.getElementById('reporteChart').getContext('2d');
    if (rpChartInstance) {
        rpChartInstance.destroy();
    }

    let labels = [];
    let dataTotales = [];
    let defaultType = 'bar';

    if (agrupacion === 'CLIENTE') {
        labels = rawData.map(r => r.cliente_nombre);
        dataTotales = rawData.map(r => parseFloat(r.cantidad_total));
    } else if (agrupacion === 'PRODUCTO') {
        labels = rawData.map(r => r.producto_nombre);
        dataTotales = rawData.map(r => parseFloat(r.cantidad_total));
    } else if (agrupacion === 'ESTADO') {
        labels = rawData.map(r => r.estado);
        dataTotales = rawData.map(r => parseFloat(r.cantidad_pedidos));
        defaultType = 'doughnut';
    } else if (agrupacion === 'RESPONSABLE') {
        labels = rawData.map(r => r.responsable_entrega);
        dataTotales = rawData.map(r => parseFloat(r.cantidad_total));
    } else if (agrupacion === 'FECHA') {
        let sortedData = [...rawData].sort((a, b) => new Date(a.fecha) - new Date(b.fecha));
        labels = sortedData.map(r => r.fecha);
        dataTotales = sortedData.map(r => parseFloat(r.cantidad_total));
        defaultType = 'line';
    } else if (agrupacion === 'MES') {
        let sortedData = [...rawData].sort((a, b) => (a.mes > b.mes ? 1 : -1));
        labels = sortedData.map(r => RP_formatearMes(r.mes));
        dataTotales = sortedData.map(r => parseFloat(r.cantidad_total));
        defaultType = 'line';
    } else {
        let limitData = rawData.slice(0, 30).reverse();
        labels = limitData.map(r => r.numero_pedido);
        dataTotales = limitData.map(r => parseFloat(r.cantidad_total));
    }

    const selectEl = document.getElementById('rp-tipo-grafico');
    let type = selectEl ? selectEl.value : 'auto';
    if (type === 'auto') {
        type = defaultType;
    }

    let backgroundColor = 'rgba(54, 162, 235, 0.5)';
    let borderColor = 'rgba(54, 162, 235, 1)';
    if (type === 'pie' || type === 'doughnut') {
        backgroundColor = RP_generarColores(labels.length);
        borderColor = backgroundColor.map(c => c.replace('0.7', '1'));
    }

    rpChartInstance = new Chart(ctx, {
        type: type,
        data: {
            labels: labels,
            datasets: [{
                label: 'Cantidad Pedida',
                data: dataTotales,
                backgroundColor: backgroundColor,
                borderColor: borderColor,
                borderWidth: (type === 'pie' || type === 'doughnut') ? 1 : 2,
                tension: 0.3,
                fill: type === 'line'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: (type === 'pie' || type === 'doughnut' || type === 'line') }
            },
            scales: (type === 'pie' || type === 'doughnut') ? {} : {
                y: { beginAtZero: true }
            }
        }
    });
}

window.RP_exportarExcel = function () {
    const form = document.getElementById('form-filtros-reporte');
    const params = new URLSearchParams(new FormData(form)).toString();
    window.open(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params, '_blank');
};

window.RP_exportarPDF = function () {
    const form = document.getElementById('form-filtros-reporte');
    const params = new URLSearchParams(new FormData(form)).toString();
    window.open(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params, '_blank');
};

/* ════════════════════════════════════════════════════
   PANEL LATERAL: detalle del pedido al hacer clic en una
   fila (solo vista Detallado; las filas agrupadas no
   representan un pedido individual).
════════════════════════════════════════════════════ */
document.addEventListener('click', function (e) {
    const tr = e.target.closest('#rp_tbody tr[data-tipo]');
    if (!tr) return;
    if (e.target.closest('button, a, input, select, label')) return;
    if (typeof window.CMG_abrirPreviewDoc !== 'function') return;

    window.CMG_abrirPreviewDoc(tr.dataset.id, tr.dataset.tipo);
});
