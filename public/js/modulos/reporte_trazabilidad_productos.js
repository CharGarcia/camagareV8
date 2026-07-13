(function () {
    'use strict';

    const searchProducto = document.getElementById('tzp-search-producto');
    const dropdownProducto = document.getElementById('tzp-dropdown-producto');
    const seleccionadoInfo = document.getElementById('tzp-producto-seleccionado');
    const fechaDesde = document.getElementById('tzp-fecha-desde');
    const fechaHasta = document.getElementById('tzp-fecha-hasta');
    const btnBuscar = document.getElementById('tzp-btn-buscar');
    const btnPdf = document.getElementById('tzp-btn-pdf');
    const btnExcel = document.getElementById('tzp-btn-excel');
    const tbody = document.getElementById('tzp-tbody');
    const kpis = document.getElementById('tzp-kpis');
    const avisoTruncado = document.getElementById('tzp-aviso-truncado');

    let idProductoActual = null;
    let debounceTimer = null;

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    // ── Autocompletado de producto ─────────────────────────────────────────
    searchProducto.addEventListener('input', function () {
        const q = searchProducto.value.trim();
        clearTimeout(debounceTimer);
        if (q.length < 2) {
            dropdownProducto.classList.add('d-none');
            return;
        }
        debounceTimer = setTimeout(function () {
            fetch(BASE_URL + '/' + RUTA_MODULO + '/buscarProductosAjax?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(res => {
                    if (!res.ok || !res.data.length) {
                        dropdownProducto.innerHTML = '<div class="list-group-item text-muted small">Sin resultados</div>';
                        dropdownProducto.classList.remove('d-none');
                        return;
                    }
                    dropdownProducto.innerHTML = res.data.map(p =>
                        `<button type="button" class="list-group-item list-group-item-action small tzp-item-producto"
                                 data-id="${p.id}" data-nombre="${escapeHtml(p.codigo + ' - ' + p.nombre)}">
                            <strong>${escapeHtml(p.codigo)}</strong> — ${escapeHtml(p.nombre)}
                         </button>`
                    ).join('');
                    dropdownProducto.classList.remove('d-none');
                })
                .catch(() => { dropdownProducto.classList.add('d-none'); });
        }, 300);
    });

    dropdownProducto.addEventListener('click', function (ev) {
        const item = ev.target.closest('.tzp-item-producto');
        if (!item) return;
        idProductoActual = parseInt(item.dataset.id, 10);
        seleccionadoInfo.textContent = item.dataset.nombre;
        seleccionadoInfo.classList.remove('fst-italic', 'text-muted');
        searchProducto.value = '';
        dropdownProducto.classList.add('d-none');
        btnBuscar.disabled = false;
        btnPdf.disabled = false;
        btnExcel.disabled = false;
        cargarTimeline();
    });

    document.addEventListener('click', function (ev) {
        if (!dropdownProducto.contains(ev.target) && ev.target !== searchProducto) {
            dropdownProducto.classList.add('d-none');
        }
    });

    btnBuscar.addEventListener('click', cargarTimeline);

    // ── Exportaciones ────────────────────────────────────────────────────
    function paramsExport() {
        const p = new URLSearchParams({ id_producto: idProductoActual || '' });
        if (fechaDesde.value) p.set('desde', fechaDesde.value);
        if (fechaHasta.value) p.set('hasta', fechaHasta.value);
        return p.toString();
    }
    btnPdf.addEventListener('click', function () {
        if (!idProductoActual) return;
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportarPdf?' + paramsExport(), '_blank');
    });
    btnExcel.addEventListener('click', function () {
        if (!idProductoActual) return;
        window.open(BASE_URL + '/' + RUTA_MODULO + '/exportarExcel?' + paramsExport(), '_blank');
    });

    // ── Carga de la línea de tiempo ─────────────────────────────────────────
    function cargarTimeline() {
        if (!idProductoActual) return;

        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Cargando...</td></tr>';

        const params = new URLSearchParams({ id_producto: idProductoActual });
        if (fechaDesde.value) params.set('desde', fechaDesde.value);
        if (fechaHasta.value) params.set('hasta', fechaHasta.value);

        fetch(BASE_URL + '/' + RUTA_MODULO + '/timelineAjax?' + params.toString())
            .then(r => r.json())
            .then(res => {
                if (!res.ok) {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">' + escapeHtml(res.mensaje || 'Error al cargar.') + '</td></tr>';
                    kpis.style.display = 'none';
                    return;
                }
                renderKpis(res.data.resumen);
                renderEventos(res.data.eventos);
                avisoTruncado.style.display = res.data.truncado ? '' : 'none';
                document.getElementById('tzp-info-total').textContent = res.data.eventos.length + ' evento' + (res.data.eventos.length === 1 ? '' : 's');
            })
            .catch(() => {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Error de conexión.</td></tr>';
            });
    }

    function renderKpis(r) {
        document.getElementById('tzp-kpi-stock').textContent = Number(r.stock_actual).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('tzp-kpi-entradas').textContent = Number(r.total_entradas).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('tzp-kpi-salidas').textContent = Number(r.total_salidas).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('tzp-kpi-costo').textContent = Number(r.costo_promedio).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
        document.getElementById('tzp-kpi-total').textContent = r.total_movimientos;
        document.getElementById('tzp-kpi-ultimo').textContent = r.ultimo_movimiento || '-';
        kpis.style.display = '';
    }

    function claseLinea(ev) {
        if (ev.tipo === 'catalogo') return 'catalogo';
        if (ev.tipo === 'documento') return 'documento';
        if (ev.tipo_movimiento === 'entrada') return 'entrada';
        if (ev.tipo_movimiento === 'salida') return 'salida';
        return 'ajuste';
    }

    function renderEventos(eventos) {
        if (!eventos.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted">No se encontraron eventos para este producto en el rango seleccionado.</td></tr>';
            return;
        }

        tbody.innerHTML = eventos.map(ev => {
            if (ev.tipo === 'catalogo') {
                const cambios = (ev.cambios || []).map(c => `${escapeHtml(c.campo)}: ${escapeHtml(c.antes ?? '-')} → ${escapeHtml(c.despues ?? '-')}`).join('; ');
                return `<tr class="tzp-linea ${claseLinea(ev)}">
                    <td class="ps-3 text-nowrap small">${escapeHtml(ev.fecha)}</td>
                    <td><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">${escapeHtml(ev.titulo)}</span></td>
                    <td colspan="4" class="small text-muted">${cambios || 'Sin detalle de cambios.'}</td>
                    <td colspan="2"></td>
                    <td class="small">${escapeHtml(ev.usuario || '-')}</td>
                </tr>`;
            }

            if (ev.tipo === 'documento') {
                const doc = ev.doc_numero
                    ? (ev.doc_ruta
                        ? `<a href="${BASE_URL}/${ev.doc_ruta}" target="_blank">${escapeHtml(ev.doc_numero)}</a>`
                        : escapeHtml(ev.doc_numero))
                    : '-';
                return `<tr class="tzp-linea ${claseLinea(ev)}">
                    <td class="ps-3 text-nowrap small">${escapeHtml(ev.fecha)}</td>
                    <td class="small">
                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">${escapeHtml(ev.titulo)}</span>
                        <span class="text-muted" style="font-size:.65rem;" title="No afecta el inventario">sin stock</span>
                    </td>
                    <td class="small">${doc}</td>
                    <td class="small">${escapeHtml(ev.doc_contraparte || '-')}</td>
                    <td class="text-center small">${Number(ev.cantidad).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    <td class="text-center small text-muted">—</td>
                    <td class="small text-muted">—</td>
                    <td class="small text-muted">—</td>
                    <td class="small">${escapeHtml(ev.usuario || '-')}</td>
                </tr>`;
            }

            const lote = [ev.numero_lote, ev.nup, ev.fecha_caducidad].filter(Boolean).join(' / ') || '-';
            const doc = ev.doc_numero
                ? (ev.doc_ruta
                    ? `<a href="${BASE_URL}/${ev.doc_ruta}" target="_blank">${escapeHtml(ev.doc_numero)}</a>`
                    : escapeHtml(ev.doc_numero))
                : '-';
            const cantidadClass = ev.cantidad >= 0 ? 'text-success' : 'text-danger';

            return `<tr class="tzp-linea ${claseLinea(ev)}">
                <td class="ps-3 text-nowrap small">${escapeHtml(ev.fecha)}</td>
                <td class="small">${escapeHtml(ev.titulo)}</td>
                <td class="small">${doc}</td>
                <td class="small">${escapeHtml(ev.doc_contraparte || '-')}</td>
                <td class="text-center small ${cantidadClass}">${Number(ev.cantidad).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                <td class="text-center small">${Number(ev.stock_posterior).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                <td class="small">${escapeHtml(lote)}</td>
                <td class="small">${escapeHtml(ev.bodega || '-')}</td>
                <td class="small">${escapeHtml(ev.usuario || '-')}</td>
            </tr>`;
        }).join('');
    }
})();
