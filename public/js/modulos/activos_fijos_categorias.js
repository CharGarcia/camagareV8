(function () {
    'use strict';

    const AFC_URL = window.AFC_URL_BASE;

    async function fetchJson(url) {
        const r = await fetch(url);
        return r.json();
    }

    window.AFC_buscar = function () {
        const b = document.getElementById('txtBuscarAFC')?.value || '';
        const tbody = document.getElementById('tbodyAFC');
        if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5"><span class="spinner-border text-primary"></span></td></tr>';
        fetchJson(`${AFC_URL}/searchAjax?b=${encodeURIComponent(b)}`).then(res => {
            if (tbody) tbody.innerHTML = res.rows;
        });
    };

    document.getElementById('txtBuscarAFC')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); window.AFC_buscar(); }
    });

    function resetForm() {
        const f = document.getElementById('formAFC');
        f.reset();
        document.getElementById('afc-id').value = '';
        document.getElementById('afc-estado').checked = true;
        document.getElementById('afc-btn-eliminar').classList.add('d-none');
    }

    window.AFC_abrirModal = function (id) {
        resetForm();
        document.getElementById('afcModalTitulo').textContent = id ? 'Editar Categoría de Activos Fijos' : 'Nueva Categoría de Activos Fijos';

        if (id) {
            fetchJson(`${AFC_URL}/getCategoriaAjax?id=${id}`).then(res => {
                if (!res.ok) { Swal.fire('Error', res.mensaje, 'error'); return; }
                const c = res.data;
                document.getElementById('afc-id').value = c.id;
                document.getElementById('afc-nombre').value = c.nombre;
                document.getElementById('afc-porcentaje').value = parseFloat(c.porcentaje_depreciacion_anual).toFixed(2);
                document.getElementById('afc-observaciones').value = c.observaciones || '';
                document.getElementById('afc-estado').checked = !!(c.estado === true || c.estado === 't' || c.estado === '1' || c.estado === 1);

                if (window.AFC_PERM?.eliminar) document.getElementById('afc-btn-eliminar').classList.remove('d-none');
                document.getElementById('afc-btn-eliminar').dataset.id = c.id;

                new bootstrap.Modal(document.getElementById('modalAFC')).show();
            });
            return;
        }

        new bootstrap.Modal(document.getElementById('modalAFC')).show();
    };

    window.AFC_guardar = function () {
        const form = document.getElementById('formAFC');
        if (!form.reportValidity()) return;

        const fd = new FormData(form);
        fd.set('estado', document.getElementById('afc-estado').checked ? '1' : '');

        const btn = document.getElementById('afc-btn-guardar');
        btn.disabled = true;
        fetch(`${AFC_URL}/guardarAjax`, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
            btn.disabled = false;
            if (res.ok) {
                bootstrap.Modal.getInstance(document.getElementById('modalAFC'))?.hide();
                window.AFC_buscar();
                Swal.fire('Éxito', res.mensaje, 'success');
            } else {
                Swal.fire('Error al guardar', res.mensaje, 'error');
            }
        }).catch(() => { btn.disabled = false; Swal.fire('Error de Red', 'No se pudo completar la operación.', 'error'); });
    };

    window.AFC_eliminar = function () {
        const id = document.getElementById('afc-btn-eliminar')?.dataset.id;
        if (!id) return;
        Swal.fire({
            title: '¿Eliminar esta categoría?',
            text: 'Solo es posible si no tiene activos fijos registrados.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33'
        }).then(result => {
            if (!result.isConfirmed) return;
            const fd = new FormData();
            fd.append('id', id);
            fetch(`${AFC_URL}/eliminarAjax`, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
                if (res.ok) {
                    Swal.fire('Eliminada', res.mensaje, 'success').then(() => {
                        bootstrap.Modal.getInstance(document.getElementById('modalAFC'))?.hide();
                        window.AFC_buscar();
                    });
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            });
        });
    };
})();
