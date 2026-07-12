// Control de vista para Reporte de Ventas
document.addEventListener('DOMContentLoaded', function () {
    // Aplicar favoritos si están disponibles
    if (typeof aplicarFavoritosModal === 'function') {
        aplicarFavoritosModal();
    }

    // Setear mes actual por defecto (si no fue sobreescrito por favoritos)
    if (!document.getElementById('rv-mes').value || document.getElementById('rv-mes').value === 'TODOS') {
        const currentMonth = (new Date().getMonth() + 1).toString().padStart(2, '0');
        document.getElementById('rv-mes').value = currentMonth;
    }

    // Buscador predictivo de Clientes
    let debounceTimerCliente;
    const searchCliente = document.getElementById('rv-search-cliente');
    const dropdownCliente = document.getElementById('rv-dropdown-clientes');
    const chipsCliente = document.getElementById('rv-chips-cliente');

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
                    const items = data.items || data.data || data.rows || data;
                    if (items && items.length > 0) {
                        items.forEach(item => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-2';
                            btn.style.fontSize = '0.85rem';
                            btn.dataset.id = item.id;
                            const nombre = item.nombre || item.text;
                            btn.dataset.nombre = nombre;
                            btn.innerHTML = `<strong>${nombre}</strong><br><small class="text-muted">${item.identificacion || item.ruc || ''}</small>`;
                            
                            btn.addEventListener('click', function() {
                                searchCliente.value = '';
                                dropdownCliente.classList.add('d-none');
                                
                                // Evitar duplicados
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
                                    chip.querySelector('button').addEventListener('click', function() {
                                        chip.remove();
                                        window.RV_generarReporte();
                                    });
                                    chipsCliente.appendChild(chip);
                                    window.RV_generarReporte();
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

    // ── Buscadores predictivos de texto: Producto (ítems de venta) e Info adicional ──
    RV_predictivoTexto('rv-producto-texto', 'rv-dropdown-items', 'buscarItemsAjax', 'Sin ítems que coincidan');
    RV_predictivoTexto('rv-buscar-info',    'rv-dropdown-info',  'buscarInfoAdicionalAjax', 'Sin coincidencias');

    // Cerrar dropdowns al hacer clic fuera
    document.addEventListener('click', function (e) {
        if (!searchCliente.contains(e.target) && !dropdownCliente.contains(e.target)) {
            dropdownCliente.classList.add('d-none');
        }
        ['rv-producto-texto|rv-dropdown-items', 'rv-buscar-info|rv-dropdown-info'].forEach(par => {
            const [inpId, ddId] = par.split('|');
            const inp = document.getElementById(inpId), dd = document.getElementById(ddId);
            if (inp && dd && !inp.contains(e.target) && !dd.contains(e.target)) dd.classList.add('d-none');
        });
    });

    // Vincular selectores de Mes y Año
    document.getElementById('rv-mes').addEventListener('change', window.RV_cambiarMesAnio);
    document.getElementById('rv-anio').addEventListener('change', window.RV_cambiarMesAnio);
});

// Buscador predictivo genérico de texto: rellena el input con el valor elegido y regenera.
function RV_predictivoTexto(inputId, dropdownId, endpoint, msgVacio) {
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
                                window.RV_generarReporte();
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
window.RV_onAgruparChange = function() {
    const agruparPor = document.getElementById('rv_agrupar_por').value;
    const mesEl = document.getElementById('rv-mes');

    if (agruparPor === 'MES') {
        mesEl.disabled = true;
        if (mesEl.value !== 'TODOS') {
            mesEl.value = 'TODOS';
            window.RV_cambiarMesAnio(); // recalcula el rango de fechas y genera el reporte
            return;
        }
    } else {
        mesEl.disabled = false;
    }
    window.RV_generarReporte();
};

window.RV_cambiarMesAnio = function() {
    const mes = document.getElementById('rv-mes').value;
    const anio = document.getElementById('rv-anio').value;
    
    if (!mes || !anio) return;

    if (anio === 'TODOS') {
        document.getElementById('rv-fecha-desde').value = '';
        document.getElementById('rv-fecha-hasta').value = '';
    } else {
        if (mes === 'TODOS') {
            document.getElementById('rv-fecha-desde').value = anio + '-01-01';
            document.getElementById('rv-fecha-hasta').value = anio + '-12-31';
        } else {
            const fechaHasta = new Date(anio, parseInt(mes), 0);
            const strDesde = anio + '-' + mes + '-01';
            const strHasta = anio + '-' + mes + '-' + fechaHasta.getDate().toString().padStart(2, '0');
            
            document.getElementById('rv-fecha-desde').value = strDesde;
            document.getElementById('rv-fecha-hasta').value = strHasta;
        }
    }
    
    window.RV_generarReporte();
};

// Función principal para pedir los datos via AJAX
window.RV_generarReporte = function () {
    const form = document.getElementById('form-filtros-reporte');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData).toString();
    
    const agruparPor = document.getElementById('rv_agrupar_por').value;

    // Actualizar cabeceras de la tabla según la agrupación elegida
    RV_dibujarCabecera(agruparPor);

    const tbody = document.getElementById('rv_tbody');
    const colSpanActual = agruparPor === 'NINGUNO' ? 12 : (agruparPor === 'PRODUCTO' ? 7 : 6);
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

            // Actualizar tarjetas estadísticas
            if (res.stats) {
                document.getElementById('stat-base-0').textContent = parseFloat(res.stats.total_base_0).toFixed(2);
                document.getElementById('stat-base-iva').textContent = parseFloat(res.stats.total_base_iva).toFixed(2);
                document.getElementById('stat-iva').textContent = parseFloat(res.stats.total_iva).toFixed(2);
                document.getElementById('stat-total').textContent = parseFloat(res.stats.gran_total).toFixed(2);
            }
            if (res.estados) {
                document.getElementById('stat-documentos').textContent = res.estados.autorizados;
                document.getElementById('stat-borradores').textContent = res.estados.borradores;
                document.getElementById('stat-anulados').textContent   = res.estados.anulados;
            }

            // Dibujar gráfico
            const chartContainer = document.getElementById('chart-container');
            if (res.rawData && res.rawData.length > 0) {
                chartContainer.style.display = 'flex';
                RV_dibujarGrafico(res.rawData, res.agrupacion);
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

window.rv_last_raw_data = null;
window.rv_last_agrupacion = null;
let chartInstance = null;

window.RV_cambiarTipoGrafico = function() {
    if (window.rv_last_raw_data) {
        RV_dibujarGrafico(window.rv_last_raw_data, window.rv_last_agrupacion);
    }
};

function RV_formatearMes(mes) {
    if (!mes || mes.indexOf('-') === -1) return mes || '';
    const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const [anio, num] = mes.split('-');
    return (meses[parseInt(num, 10) - 1] || num) + ' ' + anio;
}

function RV_generarColores(cantidad) {
    const paleta = [
        'rgba(54, 162, 235, 0.7)', 'rgba(255, 99, 132, 0.7)', 'rgba(75, 192, 192, 0.7)',
        'rgba(255, 206, 86, 0.7)', 'rgba(153, 102, 255, 0.7)', 'rgba(255, 159, 64, 0.7)',
        'rgba(201, 203, 207, 0.7)', 'rgba(100, 200, 100, 0.7)', 'rgba(200, 100, 200, 0.7)',
        'rgba(100, 100, 200, 0.7)', 'rgba(200, 200, 100, 0.7)', 'rgba(100, 200, 200, 0.7)'
    ];
    let colores = [];
    for(let i=0; i<cantidad; i++) {
        colores.push(paleta[i % paleta.length]);
    }
    return colores;
}

function RV_dibujarGrafico(rawData, agrupacion) {
    window.rv_last_raw_data = rawData;
    window.rv_last_agrupacion = agrupacion;
    
    const ctx = document.getElementById('reporteChart').getContext('2d');
    
    if (chartInstance) {
        chartInstance.destroy();
    }

    let labels = [];
    let dataTotales = [];
    let defaultType = 'bar'; 

    if (agrupacion === 'CLIENTE') {
        labels = rawData.map(r => r.cliente_nombre);
        dataTotales = rawData.map(r => parseFloat(r.total));
    } else if (agrupacion === 'PRODUCTO') {
        labels = rawData.map(r => r.producto_nombre);
        dataTotales = rawData.map(r => parseFloat(r.total));
        defaultType = 'bar'; 
    } else if (agrupacion === 'FECHA') {
        let sortedData = [...rawData].sort((a, b) => new Date(a.fecha) - new Date(b.fecha));
        labels = sortedData.map(r => r.fecha);
        dataTotales = sortedData.map(r => parseFloat(r.total));
        defaultType = 'line';
    } else if (agrupacion === 'MES') {
        let sortedData = [...rawData].sort((a, b) => (a.mes > b.mes ? 1 : -1));
        labels = sortedData.map(r => RV_formatearMes(r.mes));
        dataTotales = sortedData.map(r => parseFloat(r.total));
        defaultType = 'line';
    } else {
        let limitData = rawData.slice(0, 30).reverse();
        labels = limitData.map(r => r.numero_factura);
        dataTotales = limitData.map(r => parseFloat(r.total));
        defaultType = 'bar';
    }

    const selectEl = document.getElementById('rv-tipo-grafico');
    let type = selectEl ? selectEl.value : 'auto';
    if (type === 'auto') {
        type = defaultType;
    }

    let backgroundColor = 'rgba(54, 162, 235, 0.5)';
    let borderColor = 'rgba(54, 162, 235, 1)';
    
    if (type === 'pie' || type === 'doughnut') {
        backgroundColor = RV_generarColores(labels.length);
        borderColor = backgroundColor.map(c => c.replace('0.7', '1'));
    }

    chartInstance = new Chart(ctx, {
        type: type,
        data: {
            labels: labels,
            datasets: [{
                label: 'Gran Total ($)',
                data: dataTotales,
                backgroundColor: backgroundColor,
                borderColor: borderColor,
                borderWidth: (type === 'pie' || type === 'doughnut') ? 1 : 2,
                tension: 0.3,
                fill: type === 'line' ? true : false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: (type === 'pie' || type === 'doughnut' || type === 'line') }
            },
            scales: (type === 'pie' || type === 'doughnut') ? {} : {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return '$' + value; }
                    }
                }
            }
        }
    });
}

function RV_dibujarCabecera(agruparPor) {
    let theadHtml = '<tr class="text-secondary" style="font-family: \'Outfit\', sans-serif;">';
    
    if (agruparPor === 'CLIENTE') {
        theadHtml += `
            <th class="ps-4">Cliente</th>
            <th class="text-center">Nro Facturas</th>
            <th class="text-end">Base 0% / Exento</th>
            <th class="text-end">Base IVA</th>
            <th class="text-end">Total IVA</th>
            <th class="text-end pe-4">Gran Total</th>
        `;
    } else if (agruparPor === 'PRODUCTO') {
        theadHtml += `
            <th class="ps-4">Producto</th>
            <th class="text-center">Cantidad Vendida</th>
            <th class="text-center">Tipo IVA</th>
            <th class="text-end">Base 0% / Exento</th>
            <th class="text-end">Base IVA</th>
            <th class="text-end">Total IVA</th>
            <th class="text-end pe-4">Gran Total</th>
        `;
    } else if (agruparPor === 'FECHA') {
        theadHtml += `
            <th class="ps-4">Fecha</th>
            <th class="text-center">Nro Facturas</th>
            <th class="text-end">Base 0% / Exento</th>
            <th class="text-end">Base IVA</th>
            <th class="text-end">Total IVA</th>
            <th class="text-end pe-4">Gran Total</th>
        `;
    } else if (agruparPor === 'MES') {
        theadHtml += `
            <th class="ps-4">Mes</th>
            <th class="text-center">Nro Facturas</th>
            <th class="text-end">Base 0% / Exento</th>
            <th class="text-end">Base IVA</th>
            <th class="text-end">Total IVA</th>
            <th class="text-end pe-4">Gran Total</th>
        `;
    } else {
        // NINGUNO / DETALLADO
        theadHtml += `
            <th class="ps-4">Fecha</th>
            <th>Nro Documento</th>
            <th>Cliente</th>
            <th class="text-center">Estado</th>
            <th>Vendedor</th>
            <th>Cajero</th>
            <th>Usuario</th>
            <th class="text-end">Base 0% / Exento</th>
            <th class="text-end">Base IVA</th>
            <th class="text-end">Total IVA</th>
            <th class="text-end pe-4">Gran Total</th>
            <th class="text-end pe-4">Retenciones</th>
        `;
    }
    
    theadHtml += '</tr>';
    document.getElementById('rv_thead').innerHTML = theadHtml;
}

window.RV_exportarExcel = function() {
    const form = document.getElementById('form-filtros-reporte');
    const params = new URLSearchParams(new FormData(form)).toString();
    window.open(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params, '_blank');
};

window.RV_exportarPDF = function() {
    const form = document.getElementById('form-filtros-reporte');
    const params = new URLSearchParams(new FormData(form)).toString();
    window.open(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params, '_blank');
};
