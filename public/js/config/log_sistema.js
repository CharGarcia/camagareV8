(function () {
    'use strict';

    const API = `${window.BASE_URL || ''}/config/log-sistema`;

    let currentPage = 1;
    let sortCol = 'created_at';
    let sortDir = 'DESC';
    let timerBusqueda = null;
    let modalDetalle = null;

    function getBuscar() {
        const el = document.getElementById('logInputBuscar');
        return el ? el.value.trim() : '';
    }

    // Lee la barra de filtros y devuelve solo los que tienen valor.
    function getFiltros() {
        const map = { fu: 'fltUsuario', fe: 'fltEmpresa', fa: 'fltAccion', ft: 'fltTabla', fd: 'fltDesde', fh: 'fltHasta' };
        const out = {};
        Object.keys(map).forEach(function (param) {
            const el = document.getElementById(map[param]);
            if (el && el.value.trim() !== '') out[param] = el.value.trim();
        });
        return out;
    }

    function construirParams(extra) {
        const params = new URLSearchParams(extra);
        const f = getFiltros();
        Object.keys(f).forEach(function (k) { params.set(k, f[k]); });
        return params;
    }

    window.LOGSIS_cargarListado = async function (page = 1) {
        currentPage = page;
        const tbody = document.getElementById('tbodyLogSistema');
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

        const params = construirParams({
            action: 'listar',
            b: getBuscar(),
            page: String(page),
            sort: sortCol,
            dir: sortDir,
        });

        try {
            const resp = await fetch(`${API}?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res = await resp.json();
            if (res.ok) {
                if (tbody) tbody.innerHTML = res.rows;
                const info = document.getElementById('logPaginationInfo');
                if (info) info.textContent = res.info;
                const pag = document.getElementById('logWrapperPagination');
                if (pag) pag.innerHTML = res.pagination;
            } else {
                if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">${res.error || 'Error al cargar'}</td></tr>`;
            }
        } catch (e) {
            console.error(e);
            if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Error de conexión con el servidor.</td></tr>';
        }
    };

    window.LOGSIS_cambiarPagina = function (page) {
        if (page < 1) return;
        LOGSIS_cargarListado(page);
    };

    window.LOGSIS_verDetalle = async function (id) {
        if (!id) return;
        if (!modalDetalle) {
            const el = document.getElementById('modalLogDetalle');
            if (el) modalDetalle = new bootstrap.Modal(el);
        }
        const body = document.getElementById('logDetalleBody');
        if (body) body.innerHTML = '<div class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</div>';
        if (modalDetalle) modalDetalle.show();

        try {
            const resp = await fetch(`${API}?action=detalle&id=${encodeURIComponent(id)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res = await resp.json();
            if (res.ok) {
                if (body) body.innerHTML = res.html;
            } else {
                if (body) body.innerHTML = `<div class="alert alert-warning mb-0">${res.error || 'No se pudo cargar el detalle.'}</div>`;
            }
        } catch (e) {
            console.error(e);
            if (body) body.innerHTML = '<div class="alert alert-danger mb-0">Error de conexión.</div>';
        }
    };

    window.LOGSIS_exportar = function (tipo) {
        const action = tipo === 'pdf' ? 'exportarPdf' : 'exportarExcel';
        const params = construirParams({ action: action, b: getBuscar(), sort: sortCol, dir: sortDir });
        window.open(`${API}?${params.toString()}`, '_blank');
    };

    function actualizarIndicadoresOrden() {
        document.querySelectorAll('.log-sort').forEach(function (th) {
            const icon = th.querySelector('i');
            if (icon) icon.remove();
            if (th.getAttribute('data-col') === sortCol) {
                const i = document.createElement('i');
                i.className = 'bi ' + (sortDir === 'ASC' ? 'bi-arrow-up-short' : 'bi-arrow-down-short');
                th.appendChild(i);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('logInputBuscar');
        if (input) {
            input.addEventListener('input', function () {
                clearTimeout(timerBusqueda);
                timerBusqueda = setTimeout(() => LOGSIS_cargarListado(1), 400);
            });
        }

        document.querySelectorAll('.log-sort').forEach(function (th) {
            th.addEventListener('click', function () {
                const col = th.getAttribute('data-col');
                if (!col) return;
                if (sortCol === col) {
                    sortDir = sortDir === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    sortCol = col;
                    sortDir = 'DESC';
                }
                actualizarIndicadoresOrden();
                LOGSIS_cargarListado(1);
            });
        });

        // Filtros: recargar al cambiar cualquiera.
        ['fltUsuario', 'fltEmpresa', 'fltAccion', 'fltTabla', 'fltDesde', 'fltHasta'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', function () { LOGSIS_cargarListado(1); });
        });

        const btnLimpiar = document.getElementById('btnLimpiarFiltros');
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', function () {
                // Selects a "todos"; fechas de vuelta a su valor por defecto (año actual → hoy).
                ['fltUsuario', 'fltEmpresa', 'fltAccion', 'fltTabla'].forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                ['fltDesde', 'fltHasta'].forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el) el.value = el.defaultValue;
                });
                const inputB = document.getElementById('logInputBuscar');
                if (inputB) inputB.value = '';
                LOGSIS_cargarListado(1);
            });
        }
    });
})();
