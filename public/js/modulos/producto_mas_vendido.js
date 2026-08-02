// Control de vista para Productos Más Vendidos
document.addEventListener('DOMContentLoaded', function () {
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

let chartInstance = null;
function PMV_dibujarGrafico(rawData) {
    const ctx = document.getElementById('reporteChart').getContext('2d');
    if (chartInstance) chartInstance.destroy();

    const top = rawData.slice(0, 15);
    const labels = top.map(r => r.producto_nombre);
    const cantidades = top.map(r => parseFloat(r.cantidad_vendida));

    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Cantidad Vendida',
                data: cantidades,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } }
        }
    });
}

window.PMV_exportarExcel = function () {
    const form = document.getElementById('form-filtros-reporte');
    const params = new URLSearchParams(new FormData(form)).toString();
    window.open(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params, '_blank');
};

window.PMV_exportarPDF = function () {
    const form = document.getElementById('form-filtros-reporte');
    const params = new URLSearchParams(new FormData(form)).toString();
    window.open(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params, '_blank');
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
