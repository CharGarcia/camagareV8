/**
 * Lógica compartida para el Modal de Niveles/Cursos (Alumnos).
 * Al guardar dispara el evento `nivelGuardado` para que cualquier módulo que
 * incluya este sub-modal (p. ej. el modal de Alumno) pueda agregar el nuevo
 * valor a su select y seleccionarlo automáticamente.
 */
(function (window, document) {
    'use strict';

    const urlBase = (typeof BASE_URL !== 'undefined') ? (BASE_URL + '/modulos/alumnos-niveles') : (window.location.origin + '/sistema/public/modulos/alumnos-niveles');
    const modalEl = document.getElementById('modalNivel');
    let modalInst = null;

    function getModal() {
        if (!modalInst && typeof bootstrap !== 'undefined' && modalEl) {
            modalInst = new bootstrap.Modal(modalEl);
        }
        return modalInst;
    }

    window.abrirModalNivelCrear = function () {
        const form = document.getElementById('formNivelModal');
        if (!form) return;
        form.reset();

        document.getElementById('nivel_id_modal').value = '';
        document.getElementById('nivel_orden_modal').value = '0';
        document.getElementById('tituloModalNivel').textContent = 'Nuevo Nivel/Curso';
        document.getElementById('modalAlertNivel').classList.add('d-none');
        document.getElementById('btnEliminarNivelModal').classList.add('d-none');

        getModal()?.show();
        setTimeout(() => {
            const nomInput = document.getElementById('nivel_nombre_modal');
            if (nomInput) nomInput.focus();
        }, 400);
    };

    window.abrirModalNivelEditar = function (rowOrData) {
        let data;
        if (rowOrData instanceof HTMLElement) {
            data = typeof rowOrData.dataset.row === 'string' ? JSON.parse(rowOrData.dataset.row) : rowOrData;
        } else {
            data = rowOrData;
        }

        const form = document.getElementById('formNivelModal');
        if (!form || !data) return;
        form.reset();

        document.getElementById('nivel_id_modal').value = data.id;
        document.getElementById('nivel_nombre_modal').value = data.nombre || '';
        document.getElementById('nivel_orden_modal').value = data.orden ?? 0;
        document.getElementById('nivel_estado_modal').value = data.estado || 'activo';

        document.getElementById('tituloModalNivel').textContent = 'Editar Nivel/Curso';
        document.getElementById('modalAlertNivel').classList.add('d-none');
        document.getElementById('btnEliminarNivelModal').classList.remove('d-none');

        getModal()?.show();
    };

    window.guardarNivelModal = async function () {
        const form = document.getElementById('formNivelModal');
        const id = document.getElementById('nivel_id_modal')?.value;
        const actionUrl = id ? `${urlBase}/update` : `${urlBase}/store`;
        const btn = document.getElementById('btnGuardarNivelModal');
        const alertEl = document.getElementById('modalAlertNivel');

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
                    if (window.fetchSearchNivel) window.fetchSearchNivel();
                    window.dispatchEvent(new CustomEvent('nivelGuardado', {
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

    window.eliminarNivelModal = async function () {
        const id = document.getElementById('nivel_id_modal')?.value;
        if (!id || !confirm('¿Seguro que desea eliminar este nivel/curso?')) return;
        const btn = document.getElementById('btnEliminarNivelModal');

        if (btn) btn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id_eliminar', id);
            const resp = await fetch(`${urlBase}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                getModal()?.hide();
                if (window.fetchSearchNivel) window.fetchSearchNivel();
            } else {
                alert(json.error || 'No se pudo eliminar.');
            }
        } catch (e) {} finally { if (btn) btn.disabled = false; }
    };

})(window, document);
