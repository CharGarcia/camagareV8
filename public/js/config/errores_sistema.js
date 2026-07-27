(function () {
    'use strict';

    const API = `${window.BASE_URL || ''}/config/errores-sistema`;

    let currentPage = 1;
    let sortCol = 'created_at';
    let sortDir = 'DESC';
    let timerBusqueda = null;
    let modalDetalle = null;

    function getBuscar() {
        const el = document.getElementById('errInputBuscar');
        return el ? el.value.trim() : '';
    }

    window.ERRSIS_cargarListado = async function (page = 1) {
        currentPage = page;
        const tbody = document.getElementById('tbodyErrores');
        if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

        const params = new URLSearchParams({
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
                const info = document.getElementById('errPaginationInfo');
                if (info) info.textContent = res.info;
                const pag = document.getElementById('errWrapperPagination');
                if (pag) pag.innerHTML = res.pagination;
            } else {
                if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger">${res.error || 'Error al cargar'}</td></tr>`;
            }
        } catch (e) {
            console.error(e);
            if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Error de conexión con el servidor.</td></tr>';
        }
    };

    window.ERRSIS_cambiarPagina = function (page) {
        if (page < 1) return;
        ERRSIS_cargarListado(page);
    };

    window.ERRSIS_verDetalle = async function (id) {
        if (!id) return;
        if (!modalDetalle) {
            const el = document.getElementById('modalErrorDetalle');
            if (el) modalDetalle = new bootstrap.Modal(el);
        }
        const body = document.getElementById('errDetalleBody');
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

    function actualizarIndicadoresOrden() {
        document.querySelectorAll('.err-sort').forEach(function (th) {
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
        const input = document.getElementById('errInputBuscar');
        if (input) {
            input.addEventListener('input', function () {
                clearTimeout(timerBusqueda);
                timerBusqueda = setTimeout(() => ERRSIS_cargarListado(1), 400);
            });
        }

        document.querySelectorAll('.err-sort').forEach(function (th) {
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
                ERRSIS_cargarListado(1);
            });
        });
    });
})();
