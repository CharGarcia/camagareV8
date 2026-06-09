// Módulo: Reporte de Compras
document.addEventListener('DOMContentLoaded', function () {
    if (typeof aplicarFavoritosModal === 'function') {
        aplicarFavoritosModal();
    }

    // Mes actual por defecto
    const mesEl = document.getElementById('rc-mes');
    if (!mesEl.value || mesEl.value === 'TODOS') {
        mesEl.value = (new Date().getMonth() + 1).toString().padStart(2, '0');
    }

    // ── Buscador predictivo: Proveedor ───────────────────────────────────────
    let debounceTimerProv;
    const searchProv    = document.getElementById('rc-search-proveedor');
    const dropdownProv  = document.getElementById('rc-dropdown-proveedores');
    const chipsProv     = document.getElementById('rc-chips-proveedor');

    searchProv.addEventListener('input', function () {
        clearTimeout(debounceTimerProv);
        const q = this.value.trim();
        if (q.length < 2) { dropdownProv.classList.add('d-none'); return; }

        debounceTimerProv = setTimeout(() => {
            fetch(BASE_URL + '/' + RUTA_MODULO + '/getProveedoresAjax?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    dropdownProv.innerHTML = '';
                    const items = data.data || data.rows || data;
                    if (items && items.length > 0) {
                        items.forEach(item => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-2';
                            btn.style.fontSize = '0.85rem';
                            btn.dataset.id = item.id;
                            const nombre = item.nombre || item.razon_social || '';
                            btn.innerHTML = `<strong>${nombre}</strong><br><small class="text-muted">${item.identificacion || ''}</small>`;
                            btn.addEventListener('click', function () {
                                searchProv.value = '';
                                dropdownProv.classList.add('d-none');
                                if (!chipsProv.querySelector(`input[value="${item.id}"]`)) {
                                    const chip = document.createElement('span');
                                    chip.className = 'badge bg-danger bg-opacity-10 text-danger border border-danger d-flex align-items-center justify-content-between mb-1 text-start';
                                    chip.style.cssText = 'font-size:.75rem;width:100%;white-space:normal;';
                                    chip.innerHTML = `<span class="text-truncate me-2">${nombre}</span>
                                        <input type="hidden" name="id_proveedor[]" value="${item.id}">
                                        <button type="button" class="btn-close btn-close-sm flex-shrink-0" style="font-size:.5rem;"></button>`;
                                    chip.querySelector('button').addEventListener('click', function () {
                                        chip.remove();
                                        window.RC_generarReporte();
                                    });
                                    chipsProv.appendChild(chip);
                                    window.RC_generarReporte();
                                }
                            });
                            dropdownProv.appendChild(btn);
                        });
                        dropdownProv.classList.remove('d-none');
                    } else {
                        dropdownProv.innerHTML = '<div class="list-group-item text-muted small">No se encontraron proveedores</div>';
                        dropdownProv.classList.remove('d-none');
                    }
                })
                .catch(err => console.error(err));
        }, 300);
    });

    // ── Buscador predictivo: Producto ────────────────────────────────────────
    let debounceTimerProd;
    const searchProd    = document.getElementById('rc-search-producto');
    const dropdownProd  = document.getElementById('rc-dropdown-productos');
    const chipsProd     = document.getElementById('rc-chips-producto');

    searchProd.addEventListener('input', function () {
        clearTimeout(debounceTimerProd);
        const q = this.value.trim();
        if (q.length < 2) { dropdownProd.classList.add('d-none'); return; }

        debounceTimerProd = setTimeout(() => {
            fetch(BASE_URL + '/' + RUTA_MODULO + '/getProductosAjax?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    dropdownProd.innerHTML = '';
                    const items = data.data || data.rows || data;
                    if (items && items.length > 0) {
                        items.forEach(item => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-2';
                            btn.style.fontSize = '0.85rem';
                            const nombre = item.nombre || '';
                            btn.innerHTML = `<strong>${nombre}</strong><br><small class="text-muted">Cod: ${item.codigo || ''}</small>`;
                            btn.addEventListener('click', function () {
                                searchProd.value = '';
                                dropdownProd.classList.add('d-none');
                                if (!chipsProd.querySelector(`input[value="${item.id}"]`)) {
                                    const chip = document.createElement('span');
                                    chip.className = 'badge bg-warning bg-opacity-10 text-warning border border-warning d-flex align-items-center justify-content-between mb-1 text-start';
                                    chip.style.cssText = 'font-size:.75rem;width:100%;white-space:normal;';
                                    chip.innerHTML = `<span class="text-truncate me-2">${nombre}</span>
                                        <input type="hidden" name="id_producto[]" value="${item.id}">
                                        <button type="button" class="btn-close btn-close-sm flex-shrink-0" style="font-size:.5rem;"></button>`;
                                    chip.querySelector('button').addEventListener('click', function () {
                                        chip.remove();
                                        window.RC_generarReporte();
                                    });
                                    chipsProd.appendChild(chip);
                                    window.RC_generarReporte();
                                }
                            });
                            dropdownProd.appendChild(btn);
                        });
                        dropdownProd.classList.remove('d-none');
                    } else {
                        dropdownProd.innerHTML = '<div class="list-group-item text-muted small">No se encontraron productos</div>';
                        dropdownProd.classList.remove('d-none');
                    }
                })
                .catch(err => console.error(err));
        }, 300);
    });

    // Cerrar dropdowns al clic fuera
    document.addEventListener('click', function (e) {
        if (!searchProv.contains(e.target) && !dropdownProv.contains(e.target)) dropdownProv.classList.add('d-none');
        if (!searchProd.contains(e.target) && !dropdownProd.contains(e.target)) dropdownProd.classList.add('d-none');
    });

    // Vincular mes/año
    document.getElementById('rc-mes').addEventListener('change',  window.RC_cambiarMesAnio);
    document.getElementById('rc-anio').addEventListener('change', window.RC_cambiarMesAnio);
});

// ── Mes / Año ────────────────────────────────────────────────────────────────
window.RC_cambiarMesAnio = function () {
    const mes  = document.getElementById('rc-mes').value;
    const anio = document.getElementById('rc-anio').value;
    if (!mes || !anio) return;

    if (anio === 'TODOS') {
        document.getElementById('rc-fecha-desde').value = '';
        document.getElementById('rc-fecha-hasta').value = '';
    } else {
        if (mes === 'TODOS') {
            document.getElementById('rc-fecha-desde').value = anio + '-01-01';
            document.getElementById('rc-fecha-hasta').value = anio + '-12-31';
        } else {
            const ultimoDia = new Date(parseInt(anio), parseInt(mes), 0).getDate();
            document.getElementById('rc-fecha-desde').value = `${anio}-${mes}-01`;
            document.getElementById('rc-fecha-hasta').value = `${anio}-${mes}-${String(ultimoDia).padStart(2, '0')}`;
        }
    }
    window.RC_generarReporte();
};

// ── Generar reporte (AJAX) ───────────────────────────────────────────────────
window.RC_generarReporte = function () {
    const form      = document.getElementById('form-filtros-reporte');
    const formData  = new FormData(form);
    const params    = new URLSearchParams(formData).toString();
    const agruparPor = document.getElementById('rc_agrupar_por').value;

    RC_dibujarCabecera(agruparPor);

    const tbody       = document.getElementById('rc_tbody');
    const colSpanAct  = agruparPor === 'NINGUNO' ? 12 : (agruparPor === 'PRODUCTO' ? 7 : 6);
    tbody.innerHTML   = `<tr><td colspan="${colSpanAct}" class="text-center py-4">
        <div class="spinner-border text-danger" role="status"></div>
        <br><span class="text-muted small mt-2 d-inline-block">Generando reporte...</span>
    </td></tr>`;

    fetch(BASE_URL + '/' + RUTA_MODULO + '/generarAjax', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: params,
    })
    .then(async response => {
        const text = await response.text();
        try { return JSON.parse(text); }
        catch (e) { throw new Error(text.substring(0, 200)); }
    })
    .then(res => {
        if (res.ok) {
            tbody.innerHTML = res.rows;

            if (res.stats) {
                document.getElementById('stat-base-0').textContent   = parseFloat(res.stats.total_base_0).toFixed(2);
                document.getElementById('stat-base-iva').textContent = parseFloat(res.stats.total_base_iva).toFixed(2);
                document.getElementById('stat-iva').textContent      = parseFloat(res.stats.total_iva).toFixed(2);
                document.getElementById('stat-total').textContent    = parseFloat(res.stats.gran_total).toFixed(2);
                document.getElementById('stat-documentos').textContent = res.stats.total_documentos;
            }

            const chartContainer = document.getElementById('chart-container');
            if (res.rawData && res.rawData.length > 0) {
                chartContainer.style.display = 'flex';
                RC_dibujarGrafico(res.rawData, res.agrupacion);
            } else {
                chartContainer.style.display = 'none';
            }
        } else {
            Swal.fire('Error', res.error || 'Ocurrió un error al generar el reporte', 'error');
            tbody.innerHTML = `<tr><td colspan="${colSpanAct}" class="text-center py-4 text-danger">Error al generar reporte.</td></tr>`;
            document.getElementById('chart-container').style.display = 'none';
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error del Servidor', 'Detalle: ' + err.message, 'error');
        tbody.innerHTML = `<tr><td colspan="${colSpanAct}" class="text-center py-4 text-danger">Error de conexión.</td></tr>`;
        document.getElementById('chart-container').style.display = 'none';
    });
};

// ── Cabecera dinámica ────────────────────────────────────────────────────────
function RC_dibujarCabecera(agruparPor) {
    let th = '<tr class="text-secondary" style="font-family:\'Outfit\',sans-serif;">';

    if (agruparPor === 'PROVEEDOR') {
        th += `
            <th class="ps-4">Proveedor</th>
            <th class="text-center">Nro Comp.</th>
            <th class="text-end">Base 0% / Exento</th>
            <th class="text-end">Base IVA</th>
            <th class="text-end">Total IVA</th>
            <th class="text-end pe-4">Gran Total</th>
        `;
    } else if (agruparPor === 'PRODUCTO') {
        th += `
            <th class="ps-4">Producto</th>
            <th class="text-center">Cantidad Comprada</th>
            <th class="text-center">Tipo IVA</th>
            <th class="text-end">Base 0% / Exento</th>
            <th class="text-end">Base IVA</th>
            <th class="text-end">Total IVA</th>
            <th class="text-end pe-4">Gran Total</th>
        `;
    } else if (agruparPor === 'FECHA') {
        th += `
            <th class="ps-4">Fecha</th>
            <th class="text-center">Nro Comp.</th>
            <th class="text-end">Base 0% / Exento</th>
            <th class="text-end">Base IVA</th>
            <th class="text-end">Total IVA</th>
            <th class="text-end pe-4">Gran Total</th>
        `;
    } else {
        // NINGUNO / DETALLADO — 12 columnas
        th += `
            <th class="ps-3">F. Emisión</th>
            <th>F. Registro</th>
            <th>Nro Documento</th>
            <th>Proveedor</th>
            <th class="text-center">Tipo</th>
            <th>Usuario</th>
            <th>Nro Autorización</th>
            <th class="text-end">Base 0% / Exento</th>
            <th class="text-end">Base IVA</th>
            <th class="text-end">Total IVA</th>
            <th class="text-end pe-3">Gran Total</th>
            <th class="text-end pe-3">Retenciones</th>
        `;
    }

    th += '</tr>';
    document.getElementById('rc_thead').innerHTML = th;
}

// ── Gráfico ──────────────────────────────────────────────────────────────────
window.rc_last_raw_data   = null;
window.rc_last_agrupacion = null;
let rcChartInstance = null;

window.RC_cambiarTipoGrafico = function () {
    if (window.rc_last_raw_data) RC_dibujarGrafico(window.rc_last_raw_data, window.rc_last_agrupacion);
};

function RC_generarColores(n) {
    const paleta = [
        'rgba(220,53,69,.7)', 'rgba(255,193,7,.7)', 'rgba(13,110,253,.7)',
        'rgba(25,135,84,.7)', 'rgba(13,202,240,.7)', 'rgba(108,117,125,.7)',
        'rgba(111,66,193,.7)', 'rgba(253,126,20,.7)', 'rgba(32,201,151,.7)',
        'rgba(214,51,132,.7)', 'rgba(255,99,132,.7)', 'rgba(54,162,235,.7)',
    ];
    return Array.from({ length: n }, (_, i) => paleta[i % paleta.length]);
}

function RC_dibujarGrafico(rawData, agrupacion) {
    window.rc_last_raw_data   = rawData;
    window.rc_last_agrupacion = agrupacion;

    const ctx = document.getElementById('reporteChart').getContext('2d');
    if (rcChartInstance) rcChartInstance.destroy();

    let labels = [], dataTotales = [], defaultType = 'bar';

    if (agrupacion === 'PROVEEDOR') {
        labels     = rawData.map(r => r.proveedor_nombre);
        dataTotales = rawData.map(r => parseFloat(r.total));
    } else if (agrupacion === 'PRODUCTO') {
        labels     = rawData.map(r => r.producto_nombre);
        dataTotales = rawData.map(r => parseFloat(r.total));
    } else if (agrupacion === 'FECHA') {
        const sorted = [...rawData].sort((a, b) => new Date(a.fecha) - new Date(b.fecha));
        labels      = sorted.map(r => r.fecha);
        dataTotales  = sorted.map(r => parseFloat(r.total));
        defaultType  = 'line';
    } else {
        const lim  = rawData.slice(0, 30).reverse();
        labels     = lim.map(r => r.numero_documento);
        dataTotales = lim.map(r => parseFloat(r.total));
    }

    const selectEl = document.getElementById('rc-tipo-grafico');
    let type = selectEl ? selectEl.value : 'auto';
    if (type === 'auto') type = defaultType;

    let bg = 'rgba(220,53,69,.5)', border = 'rgba(220,53,69,1)';
    if (type === 'pie' || type === 'doughnut') {
        bg     = RC_generarColores(labels.length);
        border = bg.map(c => c.replace('.7', '1'));
    }

    rcChartInstance = new Chart(ctx, {
        type: type,
        data: {
            labels,
            datasets: [{
                label: 'Gran Total ($)',
                data: dataTotales,
                backgroundColor: bg,
                borderColor: border,
                borderWidth: (type === 'pie' || type === 'doughnut') ? 1 : 2,
                tension: 0.3,
                fill: type === 'line',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: (type === 'pie' || type === 'doughnut' || type === 'line') } },
            scales: (type === 'pie' || type === 'doughnut') ? {} : {
                y: { beginAtZero: true, ticks: { callback: v => '$' + v } }
            }
        }
    });
}

// ── Exportar ─────────────────────────────────────────────────────────────────
window.RC_exportarExcel = function () {
    const params = new URLSearchParams(new FormData(document.getElementById('form-filtros-reporte'))).toString();
    window.open(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params, '_blank');
};

window.RC_exportarPDF = function () {
    const params = new URLSearchParams(new FormData(document.getElementById('form-filtros-reporte'))).toString();
    window.open(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params, '_blank');
};
