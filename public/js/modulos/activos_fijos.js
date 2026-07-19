(function () {
    'use strict';

    const AF_URL = window.AF_URL_BASE;
    const CAT_URL = window.AF_CATEGORIAS_URL;
    const CTA_URL = window.AF_CUENTAS_URL;

    const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    window.currentSort = window.currentSort || window.AF_ORDEN_COL || 'fecha_adquisicion';
    window.currentDir  = window.currentDir  || window.AF_ORDEN_DIR || 'DESC';

    async function fetchJson(url, opts) {
        const r = await fetch(url, opts);
        return r.json();
    }

    function fmtMoney(v) {
        return '$' + (parseFloat(v) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ── Typeahead genérico (mismo patrón chip: Backspace/Delete limpia todo) ──

    function setupTypeahead(inputEl, dropdownEl, hiddenEl, fetchFn, renderLabel) {
        let debounceTimer;
        inputEl.addEventListener('keydown', (e) => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && hiddenEl.value !== '') {
                e.preventDefault();
                hiddenEl.value = '';
                inputEl.value = '';
                dropdownEl.style.display = 'none';
                dropdownEl.innerHTML = '';
            }
        });
        inputEl.addEventListener('input', () => {
            hiddenEl.value = '';
            clearTimeout(debounceTimer);
            const q = inputEl.value.trim();
            if (q.length < 1) { dropdownEl.style.display = 'none'; dropdownEl.innerHTML = ''; return; }
            debounceTimer = setTimeout(async () => {
                let items = [];
                try { items = await fetchFn(q); } catch (e) { console.error(e); return; }
                if (!items || !items.length) { dropdownEl.style.display = 'none'; dropdownEl.innerHTML = ''; return; }
                dropdownEl.innerHTML = items.map(it => {
                    const label = renderLabel(it);
                    return `<a href="#" class="list-group-item list-group-item-action py-1 px-2 small" data-id="${it.id}" data-label="${label.replace(/"/g, '&quot;')}">${label}</a>`;
                }).join('');
                dropdownEl.style.display = 'block';
            }, 300);
        });
        dropdownEl.addEventListener('click', (e) => {
            const a = e.target.closest('a[data-id]');
            if (!a) return;
            e.preventDefault();
            hiddenEl.value = a.dataset.id;
            inputEl.value = a.dataset.label;
            dropdownEl.style.display = 'none';
        });
        document.addEventListener('click', (e) => {
            if (e.target !== inputEl && !dropdownEl.contains(e.target)) dropdownEl.style.display = 'none';
        });
    }

    setupTypeahead(
        document.getElementById('af-contrapartida-txt'),
        document.getElementById('af-contrapartida-dropdown'),
        document.getElementById('af-contrapartida-id'),
        async (q) => { const j = await fetchJson(`${CTA_URL}/searchAjaxCuentas?q=${encodeURIComponent(q)}`); return j.ok ? j.data : []; },
        (it) => `${it.codigo} - ${it.nombre}`
    );

    // ── Listado / búsqueda / paginación / orden ────────────────────────────

    window.AF_buscar = function (p = 1) { window.AF_fetchSearch(p); };

    window.AF_fetchSearch = async function (p = 1) {
        const b = document.getElementById('txtBuscarAF')?.value || '';
        const tbody = document.getElementById('tbodyAF');
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5"><span class="spinner-border text-primary"></span></td></tr>';
        try {
            const res = await fetchJson(`${AF_URL}/searchAjax?b=${encodeURIComponent(b)}&page=${p}&sort=${window.currentSort}&dir=${window.currentDir}`);
            if (tbody) tbody.innerHTML = res.rows;
            const info = document.getElementById('paginationInfo');
            if (info) info.innerText = res.info;
            window.AF_PAGE = res.page;

            const btnPdf = document.getElementById('btnExportPdfAF');
            if (btnPdf && res.pdf_url) btnPdf.href = res.pdf_url;
            const btnXlsx = document.getElementById('btnExportExcelAF');
            if (btnXlsx && res.excel_url) btnXlsx.href = res.excel_url;

            const pag = document.getElementById('paginationContainer');
            if (pag) {
                pag.innerHTML = `
                    <button type="button" class="btn btn-outline-secondary" ${res.page <= 1 ? 'disabled' : ''} onclick="window.AF_cambiarPaginaAjax(${res.page - 1})"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-secondary" ${res.page >= res.totalPages ? 'disabled' : ''} onclick="window.AF_cambiarPaginaAjax(${res.page + 1})"><i class="bi bi-chevron-right"></i></button>`;
            }

            document.querySelectorAll('.sortable-header').forEach(th => {
                const icon = th.querySelector('i');
                if (!icon) return;
                if (th.dataset.col === window.currentSort) {
                    icon.className = (window.currentDir.toLowerCase() === 'asc') ? 'bi bi-sort-alpha-down text-primary ms-1' : 'bi bi-sort-alpha-up text-primary ms-1';
                } else {
                    icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                }
            });
        } catch (e) { console.error(e); }
    };

    window.AF_cambiarPaginaAjax = function (p) { window.AF_fetchSearch(p); };

    window.AF_sort = function (col) {
        if (window.currentSort === col) {
            window.currentDir = (window.currentDir.toUpperCase() === 'ASC') ? 'DESC' : 'ASC';
        } else {
            window.currentSort = col;
            window.currentDir = 'ASC';
        }
        if (navigator.sendBeacon && typeof APP_VISTAS_URL !== 'undefined') {
            const fd = new FormData();
            fd.append('modulo', 'modulos/activos-fijos');
            fd.append('vistaPayload', JSON.stringify({ __ordenCol__: window.currentSort, __ordenDir__: window.currentDir }));
            navigator.sendBeacon(APP_VISTAS_URL, fd);
        }
        window.AF_fetchSearch(1);
    };

    document.getElementById('txtBuscarAF')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); window.AF_buscar(1); }
    });

    // ── Categorías (select del modal) ──────────────────────────────────────

    let categoriasCache = null;
    async function cargarCategorias() {
        if (categoriasCache) return categoriasCache;
        const res = await fetchJson(`${CAT_URL}/getActivasAjax`);
        categoriasCache = res.ok ? res.data : [];
        return categoriasCache;
    }

    async function pintarCategorias(selectedId) {
        const cats = await cargarCategorias();
        const sel = document.getElementById('af-categoria');
        sel.innerHTML = '<option value="">Seleccione...</option>' + cats.map(c =>
            `<option value="${c.id}">${c.nombre} (${parseFloat(c.porcentaje_depreciacion_anual).toFixed(2)}%)</option>`
        ).join('');
        if (selectedId) sel.value = selectedId;
    }

    // ── Origen: Manual / Desde factura de compra ───────────────────────────

    window.AF_setOrigen = function (origen) {
        document.getElementById('af-origen').value = origen;
        document.getElementById('af-btn-origen-manual').classList.toggle('active', origen === 'manual');
        document.getElementById('af-btn-origen-compra').classList.toggle('active', origen === 'compra');
        document.getElementById('af-panel-manual').classList.toggle('d-none', origen === 'compra');
        document.getElementById('af-panel-compra').classList.toggle('d-none', origen !== 'compra');
        document.getElementById('af-contrapartida-cont').classList.toggle('d-none', origen === 'compra');

        // En modo compra, nombre/valor/fecha/proveedor se precargan desde la línea elegida.
        document.getElementById('af-nombre').required = (origen === 'manual');
        document.getElementById('af-valor-adquisicion').required = (origen === 'manual');
        document.getElementById('af-fecha-adquisicion').required = (origen === 'manual');
    };

    let comprasSeleccionada = null;

    document.getElementById('af-compra-buscar')?.addEventListener('input', function () {
        clearTimeout(window._afCompraTimer);
        const q = this.value.trim();
        const dd = document.getElementById('af-compra-dropdown');
        if (q.length < 1) { dd.style.display = 'none'; return; }
        window._afCompraTimer = setTimeout(async () => {
            const res = await fetchJson(`${AF_URL}/buscarComprasAjax?b=${encodeURIComponent(q)}`);
            const items = res.ok ? res.data : [];
            if (!items.length) { dd.style.display = 'none'; dd.innerHTML = ''; return; }
            dd.innerHTML = items.map(c =>
                `<a href="#" class="list-group-item list-group-item-action py-1 px-2 small" data-id="${c.id}">
                    <strong>${c.numero}</strong> - ${c.proveedor_nombre} <span class="text-muted">(${c.fecha_emision})</span> - ${fmtMoney(c.importe_total)}
                </a>`
            ).join('');
            dd.style.display = 'block';
        }, 300);
    });

    document.getElementById('af-compra-dropdown')?.addEventListener('click', async (e) => {
        const a = e.target.closest('a[data-id]');
        if (!a) return;
        e.preventDefault();
        document.getElementById('af-compra-dropdown').style.display = 'none';
        document.getElementById('af-compra-buscar').value = a.textContent.trim();

        const res = await fetchJson(`${AF_URL}/getDetalleCompraAjax?id_compra=${a.dataset.id}`);
        if (!res.ok) { Swal.fire('Error', res.mensaje, 'error'); return; }
        comprasSeleccionada = res.data;
        document.getElementById('af-compra-info').innerHTML = `Proveedor: <strong>${res.data.proveedor_nombre}</strong><br>Fecha: ${res.data.fecha_emision}`;

        const body = document.getElementById('af-compra-lineas-body');
        if (!res.data.lineas.length) {
            body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Todas las líneas de esta factura ya están vinculadas a un activo fijo.</td></tr>';
        } else {
            body.innerHTML = res.data.lineas.map(l => `
                <tr role="button" onclick="window.AF_seleccionarLinea(${l.id_compra_detalle})">
                    <td><input type="radio" name="af-linea-radio" value="${l.id_compra_detalle}"></td>
                    <td>${l.descripcion}</td>
                    <td class="text-end">${l.cantidad}</td>
                    <td class="text-end">${fmtMoney(l.precio_total)}</td>
                </tr>`).join('');
        }
        document.getElementById('af-compra-lineas-cont').classList.remove('d-none');
    });

    window.AF_seleccionarLinea = function (idDetalle) {
        const linea = (comprasSeleccionada?.lineas || []).find(l => l.id_compra_detalle === idDetalle);
        if (!linea || !comprasSeleccionada) return;
        document.querySelector(`input[name="af-linea-radio"][value="${idDetalle}"]`).checked = true;

        document.getElementById('af-id-compra-detalle').value = idDetalle;
        document.getElementById('af-id-compra').value = comprasSeleccionada.id_compra;
        document.getElementById('af-nombre').value = linea.descripcion;
        document.getElementById('af-valor-adquisicion').value = linea.precio_total.toFixed(2);
        document.getElementById('af-fecha-adquisicion').value = comprasSeleccionada.fecha_emision;
    };

    // ── Modal: abrir / resetear ─────────────────────────────────────────────

    function resetForm() {
        const f = document.getElementById('formActivoFijo');
        f.reset();
        document.getElementById('af-id').value = '';
        document.getElementById('af-id-compra').value = '';
        document.getElementById('af-id-compra-detalle').value = '';
        document.getElementById('af-id-proveedor').value = '';
        document.getElementById('af-contrapartida-id').value = '';
        document.getElementById('af-contrapartida-txt').value = '';
        document.getElementById('af-compra-buscar').value = '';
        document.getElementById('af-compra-info').innerHTML = '&nbsp;';
        document.getElementById('af-compra-lineas-cont').classList.add('d-none');
        document.getElementById('af-compra-lineas-body').innerHTML = '';
        document.getElementById('af-resumen').classList.add('d-none');
        document.getElementById('af-depreciacion-contenido').innerHTML = '<p class="text-muted small mb-0">Guarde el activo para poder generar su depreciación mensual.</p>';
        document.getElementById('af-btn-eliminar').classList.add('d-none');
        document.getElementById('af-origen-toggle').classList.remove('d-none');
        comprasSeleccionada = null;

        setCamposHabilitados(true);
        window.AF_setOrigen('manual');
    }

    function setCamposHabilitados(habilitado) {
        ['af-nombre', 'af-codigo', 'af-valor-adquisicion', 'af-fecha-adquisicion', 'af-proveedor-texto',
         'af-categoria', 'af-valor-residual', 'af-observaciones', 'af-contrapartida-txt']
            .forEach(id => { const el = document.getElementById(id); if (el) el.disabled = !habilitado; });
    }

    window.AF_abrirModal = async function (id) {
        resetForm();
        await pintarCategorias();

        document.getElementById('afModalTitulo').textContent = id ? 'Editar Activo Fijo' : 'Nuevo Activo Fijo';

        if (id) {
            document.getElementById('af-origen-toggle').classList.add('d-none');
            document.getElementById('af-panel-compra').classList.add('d-none');
            document.getElementById('af-panel-manual').classList.remove('d-none');

            const res = await fetchJson(`${AF_URL}/getActivoAjax?id=${id}`);
            if (!res.ok) { Swal.fire('Error', res.mensaje, 'error'); return; }
            const a = res.data;

            document.getElementById('af-id').value = a.id;
            document.getElementById('af-origen').value = a.origen;
            document.getElementById('af-nombre').value = a.nombre;
            document.getElementById('af-codigo').value = a.codigo || '';
            document.getElementById('af-valor-adquisicion').value = parseFloat(a.valor_adquisicion).toFixed(2);
            document.getElementById('af-fecha-adquisicion').value = a.fecha_adquisicion;
            document.getElementById('af-proveedor-texto').value = a.proveedor_texto || a.proveedor_nombre || '';
            document.getElementById('af-valor-residual').value = parseFloat(a.valor_residual).toFixed(2);
            document.getElementById('af-observaciones').value = a.observaciones || '';
            await pintarCategorias(a.id_categoria);

            document.getElementById('af-resumen').classList.remove('d-none');
            document.getElementById('af-resumen-acumulada').textContent = fmtMoney(a.depreciacion_acumulada);
            document.getElementById('af-resumen-libros').textContent = fmtMoney(a.valor_en_libros);
            document.getElementById('af-resumen-meses').textContent = a.meses_vida_util > 0 ? a.meses_vida_util : 'No depreciable';
            document.getElementById('af-resumen-estado').textContent = a.estado === 'depreciado_total' ? 'Depreciado total' : 'Activo';

            const tieneDeprec = (a.historial || []).length > 0;
            // Valor de adquisición, fecha y categoría se fijan en el alta y nunca se editan
            // (el backend los ignora tras crear el activo). Valor residual solo se puede
            // ajustar mientras el activo no tenga depreciaciones generadas.
            document.getElementById('af-valor-adquisicion').disabled = true;
            document.getElementById('af-fecha-adquisicion').disabled = true;
            document.getElementById('af-categoria').disabled = true;
            document.getElementById('af-valor-residual').disabled = tieneDeprec;

            renderHistorial(a.historial || []);

            if (window.AF_PERM?.eliminar && !tieneDeprec) {
                document.getElementById('af-btn-eliminar').classList.remove('d-none');
                document.getElementById('af-btn-eliminar').dataset.id = a.id;
            }

            new bootstrap.Modal(document.getElementById('modalActivoFijo')).show();
            return;
        }

        document.getElementById('af-fecha-adquisicion').value = new Date().toISOString().slice(0, 10);
        new bootstrap.Modal(document.getElementById('modalActivoFijo')).show();
    };

    function renderHistorial(historial) {
        const cont = document.getElementById('af-depreciacion-contenido');
        if (!historial.length) {
            cont.innerHTML = '<p class="text-muted small mb-0">Este activo aún no tiene depreciación generada.</p>';
            return;
        }
        let html = '<table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Período</th><th class="text-end">Cuota</th><th class="text-end">Acumulada</th><th class="text-end">Valor en Libros</th></tr></thead><tbody>';
        historial.forEach(h => {
            html += `<tr>
                <td>${MESES[h.periodo_mes]} ${h.periodo_anio}</td>
                <td class="text-end">${fmtMoney(h.valor_depreciado)}</td>
                <td class="text-end">${fmtMoney(h.depreciacion_acumulada_after)}</td>
                <td class="text-end">${fmtMoney(h.valor_libros_after)}</td>
            </tr>`;
        });
        html += '</tbody></table>';
        cont.innerHTML = html;
    }

    // ── Guardar / eliminar ───────────────────────────────────────────────────

    window.AF_guardar = function () {
        const form = document.getElementById('formActivoFijo');
        if (!form.reportValidity()) return;

        const origen = document.getElementById('af-origen').value;
        if (origen === 'compra' && !document.getElementById('af-id-compra-detalle').value) {
            Swal.fire('Atención', 'Seleccione la línea de la factura de compra.', 'warning');
            return;
        }
        if (!document.getElementById('af-categoria').value) {
            Swal.fire('Atención', 'Seleccione la categoría del activo.', 'warning');
            return;
        }

        const fd = new FormData(form);
        const btn = document.getElementById('af-btn-guardar');
        btn.disabled = true;
        fetch(`${AF_URL}/guardarAjax`, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            btn.disabled = false;
            if (res.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalActivoFijo'))?.hide();
                window.AF_fetchSearch(window.AF_PAGE || 1);
                Swal.fire('Éxito', res.mensaje, 'success');
            } else {
                Swal.fire('Error al guardar', res.mensaje, 'error');
            }
        }).catch(() => { btn.disabled = false; Swal.fire('Error de Red', 'No se pudo completar la operación.', 'error'); });
    };

    window.AF_eliminar = function () {
        const id = document.getElementById('af-btn-eliminar')?.dataset.id;
        if (!id) return;
        Swal.fire({
            title: '¿Eliminar este activo fijo?',
            text: 'Solo es posible si no tiene depreciaciones generadas.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33'
        }).then(result => {
            if (!result.isConfirmed) return;
            const fd = new FormData();
            fd.append('id', id);
            fetch(`${AF_URL}/eliminarAjax`, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                if (res.ok) {
                    Swal.fire('Eliminado', res.mensaje, 'success').then(() => {
                        bootstrap.Modal.getInstance(document.getElementById('modalActivoFijo'))?.hide();
                        window.AF_fetchSearch(window.AF_PAGE || 1);
                    });
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            });
        });
    };

    // ── Generar depreciación mensual (lote) ──────────────────────────────────

    window.AF_abrirModalDepreciacion = function () {
        const selMes = document.getElementById('dep-mes');
        const selAnio = document.getElementById('dep-anio');
        const hoy = new Date();

        selMes.innerHTML = MESES.slice(1).map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
        selMes.value = String(hoy.getMonth() + 1);

        let anios = '';
        for (let a = hoy.getFullYear(); a >= hoy.getFullYear() - 5; a--) anios += `<option value="${a}">${a}</option>`;
        selAnio.innerHTML = anios;
        selAnio.value = String(hoy.getFullYear());

        document.getElementById('dep-resultado').innerHTML = '';
        document.getElementById('dep-btn-generar').disabled = false;

        new bootstrap.Modal(document.getElementById('modalDepreciacion')).show();
    };

    window.AF_generarDepreciacion = function () {
        const mes = document.getElementById('dep-mes').value;
        const anio = document.getElementById('dep-anio').value;
        const btn = document.getElementById('dep-btn-generar');
        const resultado = document.getElementById('dep-resultado');

        Swal.fire({
            title: `¿Generar depreciación de ${MESES[mes]} ${anio}?`,
            text: 'Se contabilizará en un solo asiento consolidado. Esta operación no se puede repetir para el mismo período.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, generar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;
            btn.disabled = true;
            resultado.innerHTML = '<div class="text-center py-2"><span class="spinner-border spinner-border-sm me-1"></span> Generando...</div>';

            const fd = new FormData();
            fd.append('periodo_anio', anio);
            fd.append('periodo_mes', mes);

            fetch(`${AF_URL}/generarDepreciacionAjax`, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                btn.disabled = false;
                if (res.ok) {
                    resultado.innerHTML = `
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check-circle me-1"></i> ${res.mensaje}<br>
                            <strong>${res.data.cantidad_activos}</strong> activos depreciados por un total de <strong>${fmtMoney(res.data.total_depreciado)}</strong>.
                        </div>`;
                    window.AF_fetchSearch(window.AF_PAGE || 1);
                } else {
                    resultado.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-1"></i> ${res.mensaje}</div>`;
                }
            }).catch(() => {
                btn.disabled = false;
                resultado.innerHTML = '<div class="alert alert-danger mb-0">Error de red al generar la depreciación.</div>';
            });
        });
    };
})();
