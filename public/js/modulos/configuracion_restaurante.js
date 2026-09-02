/**
 * Configuración Restaurante — estaciones de preparación y su impresora.
 *
 * Sigue el patrón de los demás listados (Proveedores, Categorías): las filas
 * las arma el servidor, y aquí solo se refresca el tbody por AJAX al buscar,
 * ordenar o paginar. La vista inyecta CR_URL, CR_PERM y CR_ORDEN.
 */
(function () {
    'use strict';

    let modalInst = null;

    function getModal() {
        if (!modalInst && typeof bootstrap !== 'undefined') {
            modalInst = new bootstrap.Modal(document.getElementById('modalEstacion'));
        }
        return modalInst;
    }

    function swalError(html) {
        Swal.fire({ icon: 'error', title: 'Error', html, confirmButtonColor: '#dc3545' });
    }
    function swalToast(icon, title) {
        Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2500, timerProgressBar: true });
    }

    // PDO devuelve los booleanos de Postgres como 't'/'f', no como true/false.
    function esVerdadero(v) {
        return v === true || v === 't' || v === 'true' || v === 1 || v === '1';
    }

    // ─── Listado: búsqueda, orden y paginación ───────────────────────────────

    async function fetchSearch(page = 1) {
        const buscar = document.getElementById('buscarEstacion')?.value || '';
        const params = new URLSearchParams({ b: buscar, page, sort: CR_ORDEN.col, dir: CR_ORDEN.dir });

        try {
            const r = await fetch(`${CR_URL}/searchAjax?${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await r.json();
            if (!d.ok) return;

            document.getElementById('tbodyEstaciones').innerHTML = d.rows;
            document.getElementById('paginationContainer').innerHTML = d.pagination;
            document.getElementById('paginationInfo').textContent = d.info;

            // Los enlaces de exportación siguen al filtro que se esté viendo.
            const pdf = document.getElementById('btnExportPdf');
            const xls = document.getElementById('btnExportExcel');
            if (pdf) pdf.href = d.pdf_url;
            if (xls) xls.href = d.excel_url;
        } catch (e) { /* se mantiene lo último bueno en pantalla */ }
    }

    window.fetchSearch = fetchSearch;
    window.cambiarPaginaAjax = (p) => fetchSearch(p);

    document.querySelectorAll('.sortable-header').forEach(th => {
        th.addEventListener('click', () => {
            const col = th.dataset.sort;
            CR_ORDEN.dir = (CR_ORDEN.col === col && CR_ORDEN.dir.toUpperCase() === 'ASC') ? 'desc' : 'asc';
            CR_ORDEN.col = col;
            fetchSearch(1);
        });
    });

    // ─── Estación predeterminada (estrella de cada fila) ─────────────────────

    /**
     * Marca la estación de esta fila como predeterminada, o la desmarca si ya
     * lo era. Es la que recoge los ítems que no tienen estación propia (los que
     * se agregan a la comanda desde el stock general).
     */
    window.CR_togglePredeterminada = async function (btn) {
        if (!CR_PERM.actualizar) return;

        const yaEra = btn.dataset.pred === '1';
        const fd = new FormData();
        fd.append('id_estacion', yaEra ? '0' : btn.dataset.id);

        btn.disabled = true;
        try {
            const r = await fetch(`${CR_URL}/fijarPredeterminadaAjax`, { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo guardar.'); return; }
            swalToast('success', d.msg || 'Configuración guardada');
            // Se recarga el listado entero: al marcar una, la anterior deja de serlo.
            await fetchSearch(1);
        } catch (e) {
            swalError('Error de conexión.');
        } finally {
            btn.disabled = false;
        }
    };

    // ─── Ancho del papel de la tirilla ───────────────────────────────────────
    // Ajuste del salón entero, no de una estación: es el papel por el que salen
    // la cuenta y la factura desde el POS y la comanda.

    document.getElementById('cr-ancho-tirilla')?.addEventListener('change', async (ev) => {
        const fd = new FormData();
        fd.append('ancho_papel_tirilla', ev.target.value);
        ev.target.disabled = true;
        try {
            const r = await fetch(`${CR_URL}/guardarAnchoTirillaAjax`, { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo guardar.'); return; }
            swalToast('success', d.msg || 'Guardado');
        } catch (e) {
            swalError('Error de conexión.');
        } finally {
            ev.target.disabled = false;
        }
    });

    // ─── Modal ───────────────────────────────────────────────────────────────

    function toggleBloqueImpresion() {
        const chk = document.getElementById('est_imprime');
        const bloque = document.getElementById('est_bloque_impresion');
        if (!chk || !bloque) return;
        bloque.classList.toggle('d-none', !chk.checked);
    }
    document.getElementById('est_imprime')?.addEventListener('change', toggleBloqueImpresion);

    window.CR_abrirModalCrear = function () {
        document.getElementById('tituloModalEstacion').textContent = 'Nueva estación';
        document.getElementById('est_id').value = '';
        document.getElementById('est_nombre').value = '';
        document.getElementById('est_tipo').value = 'cocina';
        document.getElementById('est_activo').checked = true;
        document.getElementById('est_imprime').checked = false;
        document.getElementById('est_auto').checked = true;
        document.getElementById('est_ancho').value = '80';
        document.getElementById('est_copias').value = 1;
        toggleBloqueImpresion();

        document.getElementById('btnEliminarEstacion')?.classList.add('d-none');
        document.getElementById('btnGuardarEstacion').disabled = !CR_PERM.crear;

        getModal()?.show();
        setTimeout(() => document.getElementById('est_nombre')?.focus(), 400);
    };

    window.CR_abrirModalEditar = function (fila) {
        const d = JSON.parse(fila.dataset.row);
        document.getElementById('tituloModalEstacion').textContent = 'Editar estación';
        document.getElementById('est_id').value = d.id;
        document.getElementById('est_nombre').value = d.nombre || '';
        document.getElementById('est_tipo').value = d.tipo || 'cocina';
        document.getElementById('est_activo').checked = esVerdadero(d.activo);
        document.getElementById('est_imprime').checked = esVerdadero(d.imprime_ordenes);
        document.getElementById('est_auto').checked = esVerdadero(d.imprimir_auto);
        document.getElementById('est_ancho').value = String(d.ancho_papel || 80);
        document.getElementById('est_copias').value = d.copias || 1;
        toggleBloqueImpresion();

        // Una estación en uso no se puede borrar: se avisa antes de intentarlo.
        const btnEliminar = document.getElementById('btnEliminarEstacion');
        if (btnEliminar) {
            const usos = parseInt(d.usos, 10) || 0;
            btnEliminar.classList.remove('d-none');
            btnEliminar.disabled = usos > 0;
            btnEliminar.title = usos > 0 ? `${usos} ítem(s) preparan aquí; no se puede eliminar.` : '';
        }
        document.getElementById('btnGuardarEstacion').disabled = !CR_PERM.actualizar;

        getModal()?.show();
    };

    window.CR_guardar = async function () {
        const nombre = document.getElementById('est_nombre').value.trim();
        if (!nombre) { swalError('Ingrese un nombre para la estación.'); return; }

        const fd = new FormData();
        const id = document.getElementById('est_id').value;
        if (id) fd.append('id', id);
        fd.append('nombre', nombre);
        fd.append('tipo', document.getElementById('est_tipo').value);
        fd.append('ancho_papel', document.getElementById('est_ancho').value);
        fd.append('copias', document.getElementById('est_copias').value);
        // Los checkbox solo se envían marcados: el backend lee la ausencia como false.
        if (document.getElementById('est_activo').checked) fd.append('activo', '1');
        if (document.getElementById('est_imprime').checked) fd.append('imprime_ordenes', '1');
        if (document.getElementById('est_auto').checked) fd.append('imprimir_auto', '1');

        const btn = document.getElementById('btnGuardarEstacion');
        btn.disabled = true;
        try {
            const r = await fetch(`${CR_URL}/guardarEstacionAjax`, { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo guardar la estación.'); return; }
            getModal()?.hide();
            swalToast('success', d.msg || 'Guardado');
            await fetchSearch(1);
        } catch (e) {
            swalError('Error de conexión.');
        } finally {
            btn.disabled = false;
        }
    };

    window.CR_eliminar = async function () {
        const id = document.getElementById('est_id').value;
        if (!id) return;

        const { isConfirmed } = await Swal.fire({
            title: '¿Eliminar esta estación?',
            text: 'Los pedidos ya enviados a preparación no se ven afectados.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        });
        if (!isConfirmed) return;

        const fd = new FormData();
        fd.append('id', id);
        try {
            const r = await fetch(`${CR_URL}/eliminarEstacionAjax`, { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo eliminar.'); return; }
            getModal()?.hide();
            swalToast('success', d.msg || 'Estación eliminada');
            await fetchSearch(1);
        } catch (e) { swalError('Error de conexión.'); }
    };
})();
