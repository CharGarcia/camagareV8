// Módulo: Reporte de Inventarios (Existencias, Movimientos, Valorización, Consignaciones)

// ════════════════════════════════════════════════════════════════════
// HELPERS COMPARTIDOS
// ════════════════════════════════════════════════════════════════════
function RI_paramsFromIds(map) {
    const params = new URLSearchParams();
    Object.keys(map).forEach(key => {
        const el = document.getElementById(map[key]);
        if (!el) return;
        const val = (el.type === 'checkbox') ? (el.checked ? '1' : '') : el.value;
        if (val !== '' && val !== null && val !== undefined) params.set(key, val);
    });
    return params;
}

function RI_setupAutocomplete(searchId, dropdownId, hiddenId, selectedLabelId, ajaxUrl, onSelect) {
    let timer;
    const search   = document.getElementById(searchId);
    const dropdown = document.getElementById(dropdownId);
    if (!search || !dropdown) return;

    search.addEventListener('input', function () {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 2) { dropdown.classList.add('d-none'); return; }

        timer = setTimeout(() => {
            fetch(BASE_URL + '/' + RUTA_MODULO + ajaxUrl + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    dropdown.innerHTML = '';
                    const items = data.data || [];
                    if (items.length > 0) {
                        items.forEach(item => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-2';
                            btn.style.fontSize = '0.85rem';
                            const nombre = item.nombre || '';
                            const sub = item.codigo || item.identificacion || '';
                            btn.innerHTML = `<strong>${nombre}</strong><br><small class="text-muted">${sub}</small>`;
                            btn.addEventListener('click', function () {
                                search.value = nombre;
                                dropdown.classList.add('d-none');
                                document.getElementById(hiddenId).value = item.id;
                                if (selectedLabelId) {
                                    const lbl = document.getElementById(selectedLabelId);
                                    if (lbl) lbl.textContent = nombre;
                                }
                                if (onSelect) onSelect();
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
        if (!search.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.add('d-none');
    });
}

function RI_limpiarBusqueda(searchId, hiddenId, selectedLabelId) {
    document.getElementById(searchId).value = '';
    document.getElementById(hiddenId).value = '';
    if (selectedLabelId) {
        const lbl = document.getElementById(selectedLabelId);
        if (lbl) lbl.textContent = '';
    }
}

const RI_PALETA = [
    'rgba(13,110,253,.7)', 'rgba(220,53,69,.7)', 'rgba(25,135,84,.7)',
    'rgba(255,193,7,.7)', 'rgba(13,202,240,.7)', 'rgba(111,66,193,.7)',
    'rgba(253,126,20,.7)', 'rgba(32,201,151,.7)', 'rgba(214,51,132,.7)',
    'rgba(108,117,125,.7)',
];
function RI_colores(n) {
    return Array.from({ length: n }, (_, i) => RI_PALETA[i % RI_PALETA.length]);
}

function RI_fetchGenerar(tab, params, onOk, onError) {
    params.set('tab', tab);
    fetch(BASE_URL + '/' + RUTA_MODULO + '/generarAjax', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: params.toString(),
    })
    .then(async response => {
        const text = await response.text();
        try { return JSON.parse(text); }
        catch (e) { throw new Error(text.substring(0, 200)); }
    })
    .then(res => {
        if (res.ok) onOk(res);
        else onError(res.error || 'Ocurrió un error al generar el reporte');
    })
    .catch(err => { console.error(err); onError(err.message); });
}

// ════════════════════════════════════════════════════════════════════
// PESTAÑA 1: EXISTENCIAS
// ════════════════════════════════════════════════════════════════════
window.RI_Existencias = {
    chart: null,

    limpiarProducto() {
        RI_limpiarBusqueda('ri-ex-search-producto', 'ri-ex-id-producto', 'ri-ex-producto-seleccionado');
        this.generar();
    },

    dibujarCabecera(modo) {
        let th = '<tr class="text-secondary">';
        if (modo === 'NINGUNO') {
            th += `<th class="ps-3">Producto</th><th>Categoría</th><th>Bodega</th>
                   <th class="text-end">Stock</th><th class="text-end">Mínimo</th><th class="text-end">Máximo</th>
                   <th class="text-end">Costo Unit.</th><th class="text-end">Valor total</th><th class="text-center pe-3">Estado</th>`;
        } else {
            th += `<th class="ps-3">Grupo</th><th class="text-center">Productos</th>
                   <th class="text-end">Stock</th><th class="text-end">Mínimo</th>
                   <th class="text-end">Costo Unit.</th><th class="text-end pe-3">Valor total</th>`;
        }
        th += '</tr>';
        document.getElementById('ri-ex-thead').innerHTML = th;
    },

    generar() {
        const modo = document.getElementById('ri-ex-agrupar').value;
        this.dibujarCabecera(modo);

        const params = RI_paramsFromIds({
            id_bodega: 'ri-ex-bodega', id_categoria: 'ri-ex-categoria', id_marca: 'ri-ex-marca',
            id_producto: 'ri-ex-id-producto', estado_stock: 'ri-ex-estado', agrupar_por: 'ri-ex-agrupar',
        });

        const tbody = document.getElementById('ri-ex-tbody');
        const colSpan = modo === 'NINGUNO' ? 9 : 6;
        tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>`;

        RI_fetchGenerar('existencias', params, (res) => {
            tbody.innerHTML = res.rows;
            if (res.kpis) {
                document.getElementById('ri-ex-kpi-productos').textContent = res.kpis.total_productos;
                document.getElementById('ri-ex-kpi-valor').textContent = parseFloat(res.kpis.valor_total).toFixed(2);
                document.getElementById('ri-ex-kpi-quiebre').textContent = res.kpis.en_quiebre;
                document.getElementById('ri-ex-kpi-alerta').textContent = res.kpis.en_alerta;
            }
            const chartContainer = document.getElementById('ri-ex-chart-container');
            if (res.rawData && res.rawData.length > 0) {
                chartContainer.style.display = 'flex';
                this.dibujarGrafico(res.rawData);
            } else {
                chartContainer.style.display = 'none';
            }
        }, (msg) => {
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4 text-danger">${msg}</td></tr>`;
            document.getElementById('ri-ex-chart-container').style.display = 'none';
        });
    },

    dibujarGrafico(rawData) {
        const ctx = document.getElementById('ri-ex-chart').getContext('2d');
        if (this.chart) this.chart.destroy();

        const top = [...rawData].sort((a, b) => parseFloat(b.valor_total) - parseFloat(a.valor_total)).slice(0, 10);
        const labels = top.map(r => r.nombre_grupo || r.producto_nombre);
        const data = top.map(r => parseFloat(r.valor_total));

        this.chart = new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Valor ($)', data, backgroundColor: RI_colores(labels.length) }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    },

    exportarExcel() {
        const params = RI_paramsFromIds({
            id_bodega: 'ri-ex-bodega', id_categoria: 'ri-ex-categoria', id_marca: 'ri-ex-marca',
            id_producto: 'ri-ex-id-producto', estado_stock: 'ri-ex-estado', agrupar_por: 'ri-ex-agrupar',
        });
        params.set('tab', 'existencias');
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params.toString(), '_blank');
    },
    exportarPDF() {
        const params = RI_paramsFromIds({
            id_bodega: 'ri-ex-bodega', id_categoria: 'ri-ex-categoria', id_marca: 'ri-ex-marca',
            id_producto: 'ri-ex-id-producto', estado_stock: 'ri-ex-estado', agrupar_por: 'ri-ex-agrupar',
        });
        params.set('tab', 'existencias');
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params.toString(), '_blank');
    },
};

// ════════════════════════════════════════════════════════════════════
// PESTAÑA 2: MOVIMIENTOS (KARDEX)
// ════════════════════════════════════════════════════════════════════
window.RI_Movimientos = {
    chart: null,

    limpiarProducto() {
        RI_limpiarBusqueda('ri-mv-search-producto', 'ri-mv-id-producto', 'ri-mv-producto-seleccionado');
        this.generar();
    },

    cambiarMesAnio() {
        const mes = document.getElementById('ri-mv-mes').value;
        const anio = document.getElementById('ri-mv-anio').value;
        if (!mes || !anio) return;

        if (anio === 'TODOS') {
            document.getElementById('ri-mv-fecha-desde').value = '';
            document.getElementById('ri-mv-fecha-hasta').value = '';
        } else if (mes === 'TODOS') {
            document.getElementById('ri-mv-fecha-desde').value = anio + '-01-01';
            document.getElementById('ri-mv-fecha-hasta').value = anio + '-12-31';
        } else {
            const ultimoDia = new Date(parseInt(anio), parseInt(mes), 0).getDate();
            document.getElementById('ri-mv-fecha-desde').value = `${anio}-${mes}-01`;
            document.getElementById('ri-mv-fecha-hasta').value = `${anio}-${mes}-${String(ultimoDia).padStart(2, '0')}`;
        }
        this.generar();
    },

    dibujarCabecera(modo) {
        let th = '<tr class="text-secondary">';
        if (modo === 'NINGUNO') {
            th += `<th class="ps-3">Fecha</th><th>Producto</th><th>Bodega</th><th class="text-center">Tipo</th>
                   <th>Origen</th><th class="text-end">Cantidad</th><th class="text-end">Costo Unit.</th>
                   <th>Lote</th><th>Caducidad</th><th class="pe-3">Observaciones</th>`;
        } else {
            th += `<th class="ps-3">Grupo</th><th class="text-center">Movimientos</th>
                   <th class="text-end">Entradas</th><th class="text-end">Salidas</th>
                   <th class="text-end">Saldo neto</th><th class="text-end pe-3">Costo total</th>`;
        }
        th += '</tr>';
        document.getElementById('ri-mv-thead').innerHTML = th;
    },

    generar() {
        const modo = document.getElementById('ri-mv-agrupar').value;
        this.dibujarCabecera(modo);

        const params = RI_paramsFromIds({
            fecha_desde: 'ri-mv-fecha-desde', fecha_hasta: 'ri-mv-fecha-hasta',
            id_bodega: 'ri-mv-bodega', id_producto: 'ri-mv-id-producto',
            id_categoria: 'ri-mv-categoria', id_marca: 'ri-mv-marca',
            tipo_movimiento: 'ri-mv-tipo', referencia_tipo: 'ri-mv-origen',
            id_usuario: 'ri-mv-usuario', numero_lote: 'ri-mv-lote', nup: 'ri-mv-nup',
            agrupar_por: 'ri-mv-agrupar',
        });

        const tbody = document.getElementById('ri-mv-tbody');
        const colSpan = modo === 'NINGUNO' ? 10 : 6;
        tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>`;

        RI_fetchGenerar('movimientos', params, (res) => {
            tbody.innerHTML = res.rows;
            if (res.kpis) {
                document.getElementById('ri-mv-kpi-total').textContent = res.kpis.total_movimientos;
                document.getElementById('ri-mv-kpi-entradas').textContent = parseFloat(res.kpis.total_entradas).toFixed(2);
                document.getElementById('ri-mv-kpi-salidas').textContent = parseFloat(res.kpis.total_salidas).toFixed(2);
                document.getElementById('ri-mv-kpi-saldo').textContent = parseFloat(res.kpis.saldo_neto).toFixed(2);
            }
            const chartContainer = document.getElementById('ri-mv-chart-container');
            if (modo !== 'NINGUNO' && res.rawData && res.rawData.length > 0) {
                chartContainer.style.display = 'flex';
                this.dibujarGrafico(res.rawData, modo);
            } else {
                chartContainer.style.display = 'none';
            }
        }, (msg) => {
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4 text-danger">${msg}</td></tr>`;
            document.getElementById('ri-mv-chart-container').style.display = 'none';
        });
    },

    dibujarGrafico(rawData, modo) {
        const ctx = document.getElementById('ri-mv-chart').getContext('2d');
        if (this.chart) this.chart.destroy();

        let rows = rawData;
        let type = 'bar';
        if (modo === 'FECHA' || modo === 'MES') {
            rows = [...rawData].sort((a, b) => (a.id_grupo > b.id_grupo ? 1 : -1));
            type = 'line';
        } else {
            rows = [...rawData].sort((a, b) => (parseFloat(b.total_entradas) + parseFloat(b.total_salidas)) - (parseFloat(a.total_entradas) + parseFloat(a.total_salidas))).slice(0, 10);
        }

        const labels = rows.map(r => r.nombre_grupo);
        const entradas = rows.map(r => parseFloat(r.total_entradas));
        const salidas = rows.map(r => parseFloat(r.total_salidas));

        this.chart = new Chart(ctx, {
            type,
            data: {
                labels,
                datasets: [
                    { label: 'Entradas', data: entradas, backgroundColor: 'rgba(25,135,84,.5)', borderColor: 'rgba(25,135,84,1)', tension: .3 },
                    { label: 'Salidas', data: salidas, backgroundColor: 'rgba(220,53,69,.5)', borderColor: 'rgba(220,53,69,1)', tension: .3 },
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    },

    exportarExcel() {
        const params = this._filtros();
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params.toString(), '_blank');
    },
    exportarPDF() {
        const params = this._filtros();
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params.toString(), '_blank');
    },
    _filtros() {
        const params = RI_paramsFromIds({
            fecha_desde: 'ri-mv-fecha-desde', fecha_hasta: 'ri-mv-fecha-hasta',
            id_bodega: 'ri-mv-bodega', id_producto: 'ri-mv-id-producto',
            id_categoria: 'ri-mv-categoria', id_marca: 'ri-mv-marca',
            tipo_movimiento: 'ri-mv-tipo', referencia_tipo: 'ri-mv-origen',
            id_usuario: 'ri-mv-usuario', numero_lote: 'ri-mv-lote', nup: 'ri-mv-nup',
            agrupar_por: 'ri-mv-agrupar',
        });
        params.set('tab', 'movimientos');
        return params;
    },
};

// ════════════════════════════════════════════════════════════════════
// PESTAÑA 3: VALORIZACIÓN
// ════════════════════════════════════════════════════════════════════
window.RI_Valorizacion = {
    chart: null,

    limpiarProducto() {
        RI_limpiarBusqueda('ri-va-search-producto', 'ri-va-id-producto', 'ri-va-producto-seleccionado');
        this.generar();
    },

    generar() {
        const params = RI_paramsFromIds({
            id_bodega: 'ri-va-bodega', id_categoria: 'ri-va-categoria', id_marca: 'ri-va-marca',
            id_producto: 'ri-va-id-producto', agrupar_por: 'ri-va-agrupar',
        });

        const tbody = document.getElementById('ri-va-tbody');
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>`;

        RI_fetchGenerar('valorizacion', params, (res) => {
            tbody.innerHTML = res.rows;
            if (res.kpis) {
                document.getElementById('ri-va-kpi-valor').textContent = parseFloat(res.kpis.valor_total).toFixed(2);
                document.getElementById('ri-va-kpi-productos').textContent = res.kpis.total_productos;
                document.getElementById('ri-va-kpi-top').textContent = res.kpis.producto_top
                    ? `${res.kpis.producto_top} ($${parseFloat(res.kpis.producto_top_valor).toFixed(2)})` : '-';
            }
            const chartContainer = document.getElementById('ri-va-chart-container');
            if (res.rawData && res.rawData.length > 0) {
                chartContainer.style.display = 'flex';
                this.dibujarGrafico(res.rawData);
            } else {
                chartContainer.style.display = 'none';
            }
        }, (msg) => {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">${msg}</td></tr>`;
            document.getElementById('ri-va-chart-container').style.display = 'none';
        });
    },

    dibujarGrafico(rawData) {
        const ctx = document.getElementById('ri-va-chart').getContext('2d');
        if (this.chart) this.chart.destroy();

        const top = [...rawData].sort((a, b) => parseFloat(b.valor_total) - parseFloat(a.valor_total)).slice(0, 10);
        const labels = top.map(r => r.nombre_grupo);
        const data = top.map(r => parseFloat(r.valor_total));
        const bg = RI_colores(labels.length);

        this.chart = new Chart(ctx, {
            type: 'doughnut',
            data: { labels, datasets: [{ data, backgroundColor: bg, borderColor: bg.map(c => c.replace('.7', '1')), borderWidth: 1 }] },
            options: { responsive: true, maintainAspectRatio: false }
        });
    },

    exportarExcel() {
        const params = this._filtros();
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params.toString(), '_blank');
    },
    exportarPDF() {
        const params = this._filtros();
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params.toString(), '_blank');
    },
    _filtros() {
        const params = RI_paramsFromIds({
            id_bodega: 'ri-va-bodega', id_categoria: 'ri-va-categoria', id_marca: 'ri-va-marca',
            id_producto: 'ri-va-id-producto', agrupar_por: 'ri-va-agrupar',
        });
        params.set('tab', 'valorizacion');
        return params;
    },
};

// ════════════════════════════════════════════════════════════════════
// PESTAÑA 4: CONSIGNACIONES
// ════════════════════════════════════════════════════════════════════
window.RI_Consignaciones = {
    chart: null,

    limpiarCliente() {
        RI_limpiarBusqueda('ri-cv-search-cliente', 'ri-cv-id-cliente', 'ri-cv-cliente-seleccionado');
        this.generar();
    },
    limpiarProducto() {
        RI_limpiarBusqueda('ri-cv-search-producto', 'ri-cv-id-producto', 'ri-cv-producto-seleccionado');
        this.generar();
    },

    dibujarCabecera(modo) {
        let th = '<tr class="text-secondary">';
        if (modo === 'NINGUNO') {
            th += `<th class="ps-3">Fecha</th><th>Cliente</th><th>Producto</th><th>Bodega</th>
                   <th class="text-end">Consignado</th><th class="text-end">Saldo</th>
                   <th class="text-end">Valor a costo</th><th class="text-center pe-3">Estado</th>`;
        } else {
            th += `<th class="ps-3">Grupo</th><th class="text-center">Consignaciones</th>
                   <th class="text-end">Saldo</th><th class="text-end pe-3">Valor a costo</th>`;
        }
        th += '</tr>';
        document.getElementById('ri-cv-thead').innerHTML = th;
    },

    generar() {
        const modo = document.getElementById('ri-cv-agrupar').value;
        this.dibujarCabecera(modo);

        const params = RI_paramsFromIds({
            id_cliente: 'ri-cv-id-cliente', id_producto: 'ri-cv-id-producto',
            id_bodega: 'ri-cv-bodega', id_vendedor: 'ri-cv-vendedor',
            fecha_desde: 'ri-cv-fecha-desde', fecha_hasta: 'ri-cv-fecha-hasta',
            incluir_liquidadas: 'ri-cv-incluir-liquidadas', agrupar_por: 'ri-cv-agrupar',
        });

        const tbody = document.getElementById('ri-cv-tbody');
        const colSpan = modo === 'NINGUNO' ? 8 : 4;
        tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>`;

        RI_fetchGenerar('consignaciones', params, (res) => {
            tbody.innerHTML = res.rows;
            if (res.kpis) {
                document.getElementById('ri-cv-kpi-unidades').textContent = parseFloat(res.kpis.unidades_vigentes).toFixed(2);
                document.getElementById('ri-cv-kpi-valor').textContent = parseFloat(res.kpis.valor_vigente).toFixed(2);
                document.getElementById('ri-cv-kpi-clientes').textContent = res.kpis.clientes_con_saldo;
                document.getElementById('ri-cv-kpi-activas').textContent = res.kpis.consignaciones_activas;
            }
            const chartContainer = document.getElementById('ri-cv-chart-container');
            if (modo !== 'NINGUNO' && res.rawData && res.rawData.length > 0) {
                chartContainer.style.display = 'flex';
                this.dibujarGrafico(res.rawData);
            } else {
                chartContainer.style.display = 'none';
            }
        }, (msg) => {
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4 text-danger">${msg}</td></tr>`;
            document.getElementById('ri-cv-chart-container').style.display = 'none';
        });
    },

    dibujarGrafico(rawData) {
        const ctx = document.getElementById('ri-cv-chart').getContext('2d');
        if (this.chart) this.chart.destroy();

        const top = [...rawData].sort((a, b) => parseFloat(b.valor_saldo) - parseFloat(a.valor_saldo)).slice(0, 10);
        const labels = top.map(r => r.nombre_grupo);
        const data = top.map(r => parseFloat(r.valor_saldo));

        this.chart = new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Valor a costo ($)', data, backgroundColor: RI_colores(labels.length) }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    },

    exportarExcel() {
        const params = this._filtros();
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params.toString(), '_blank');
    },
    exportarPDF() {
        const params = this._filtros();
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params.toString(), '_blank');
    },
    _filtros() {
        const params = RI_paramsFromIds({
            id_cliente: 'ri-cv-id-cliente', id_producto: 'ri-cv-id-producto',
            id_bodega: 'ri-cv-bodega', id_vendedor: 'ri-cv-vendedor',
            fecha_desde: 'ri-cv-fecha-desde', fecha_hasta: 'ri-cv-fecha-hasta',
            incluir_liquidadas: 'ri-cv-incluir-liquidadas', agrupar_por: 'ri-cv-agrupar',
        });
        params.set('tab', 'consignaciones');
        return params;
    },
};

// ════════════════════════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function () {
    if (typeof aplicarFavoritosModal === 'function') {
        aplicarFavoritosModal();
    }

    const mesEl = document.getElementById('ri-mv-mes');
    if (mesEl && (!mesEl.value || mesEl.value === 'TODOS')) {
        mesEl.value = (new Date().getMonth() + 1).toString().padStart(2, '0');
    }

    RI_setupAutocomplete('ri-ex-search-producto', 'ri-ex-dropdown-producto', 'ri-ex-id-producto', 'ri-ex-producto-seleccionado', '/getProductosAjax?q=', () => window.RI_Existencias.generar());
    RI_setupAutocomplete('ri-mv-search-producto', 'ri-mv-dropdown-producto', 'ri-mv-id-producto', 'ri-mv-producto-seleccionado', '/getProductosAjax?q=', () => window.RI_Movimientos.generar());
    RI_setupAutocomplete('ri-va-search-producto', 'ri-va-dropdown-producto', 'ri-va-id-producto', 'ri-va-producto-seleccionado', '/getProductosAjax?q=', () => window.RI_Valorizacion.generar());
    RI_setupAutocomplete('ri-cv-search-producto', 'ri-cv-dropdown-producto', 'ri-cv-id-producto', 'ri-cv-producto-seleccionado', '/getProductosAjax?q=', () => window.RI_Consignaciones.generar());
    RI_setupAutocomplete('ri-cv-search-cliente', 'ri-cv-dropdown-cliente', 'ri-cv-id-cliente', 'ri-cv-cliente-seleccionado', '/getClientesAjax?q=', () => window.RI_Consignaciones.generar());
});
