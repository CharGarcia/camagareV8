/**
 * Lógica compartida para el Modal de Campus (Alumnos).
 * Al guardar dispara el evento `campusGuardado` para que cualquier módulo que
 * incluya este sub-modal (p. ej. el modal de Alumno) pueda agregar el nuevo
 * valor a su select y seleccionarlo automáticamente.
 */
(function (window, document) {
    'use strict';

    const urlBase = (typeof BASE_URL !== 'undefined') ? (BASE_URL + '/modulos/alumnos-campus') : (window.location.origin + '/sistema/public/modulos/alumnos-campus');
    const modalEl = document.getElementById('modalCampus');
    let modalInst = null;

    function getModal() {
        if (!modalInst && typeof bootstrap !== 'undefined' && modalEl) {
            modalInst = new bootstrap.Modal(modalEl);
        }
        return modalInst;
    }

    window.abrirModalCampusCrear = function () {
        const form = document.getElementById('formCampusModal');
        if (!form) return;
        form.reset();

        const cid = document.getElementById('campus_id_modal');
        if (cid) cid.value = '';

        const title = document.getElementById('tituloModalCampus');
        if (title) title.textContent = 'Nuevo Campus';

        const alertEl = document.getElementById('modalAlertCampus');
        if (alertEl) alertEl.classList.add('d-none');

        const btnElim = document.getElementById('btnEliminarCampusModal');
        if (btnElim) btnElim.classList.add('d-none');

        getModal()?.show();
        setTimeout(() => {
            const nomInput = document.getElementById('campus_nombre_modal');
            if (nomInput) nomInput.focus();
        }, 400);
    };

    window.abrirModalCampusEditar = function (rowOrData) {
        let data;
        if (rowOrData instanceof HTMLElement) {
            data = typeof rowOrData.dataset.row === 'string' ? JSON.parse(rowOrData.dataset.row) : rowOrData;
        } else {
            data = rowOrData;
        }

        const form = document.getElementById('formCampusModal');
        if (!form || !data) return;
        form.reset();

        document.getElementById('campus_id_modal').value = data.id;
        document.getElementById('campus_nombre_modal').value = data.nombre || '';
        document.getElementById('campus_direccion_modal').value = data.direccion || '';
        document.getElementById('campus_estado_modal').value = data.estado || 'activo';

        document.getElementById('tituloModalCampus').textContent = 'Editar Campus';
        document.getElementById('modalAlertCampus').classList.add('d-none');
        document.getElementById('btnEliminarCampusModal').classList.remove('d-none');

        getModal()?.show();
    };

    window.guardarCampusModal = async function () {
        const form = document.getElementById('formCampusModal');
        const id = document.getElementById('campus_id_modal')?.value;
        const actionUrl = id ? `${urlBase}/update` : `${urlBase}/store`;
        const btn = document.getElementById('btnGuardarCampusModal');
        const alertEl = document.getElementById('modalAlertCampus');

        if (!form || !btn) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

        try {
            const fd = new FormData(form);
            const resp = await fetch(actionUrl, { method: 'POST', body: fd });
            const json = await resp.json();

            if (alertEl) {
                alertEl.textContent = json.msg || json.error;
                alertEl.className = `alert mb-3 py-2 small shadow-sm border-0 ${json.ok ? 'alert-success' : 'alert-danger'}`;
                alertEl.classList.remove('d-none');
            }

            if (json.ok) {
                setTimeout(() => {
                    getModal()?.hide();
                    if (window.fetchSearchCampus) window.fetchSearchCampus();
                    window.dispatchEvent(new CustomEvent('campusGuardado', {
                        detail: { id: json.id || id, nombre: json.nombre || fd.get('nombre') }
                    }));
                }, 600);
            }
        } catch (e) {
            if (alertEl) {
                alertEl.textContent = 'Error de conexión';
                alertEl.className = 'alert alert-danger mb-3 py-2 small shadow-sm border-0';
                alertEl.classList.remove('d-none');
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
        }
    };

    window.eliminarCampusModal = async function () {
        const id = document.getElementById('campus_id_modal')?.value;
        if (!id || !confirm('¿Seguro que desea eliminar este campus?')) return;
        const btn = document.getElementById('btnEliminarCampusModal');

        if (btn) btn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id_eliminar', id);
            const resp = await fetch(`${urlBase}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                getModal()?.hide();
                if (window.fetchSearchCampus) window.fetchSearchCampus();
            } else {
                alert(json.error || 'No se pudo eliminar.');
            }
        } catch (e) {} finally { if (btn) btn.disabled = false; }
    };

})(window, document);
