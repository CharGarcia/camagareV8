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


    // ── Mensajes (SweetAlert) ────────────────────────────────────────────────
    // Con un modal abierto el popup cuelga DENTRO de él (target): colgado de <body>
    // el focus trap de Bootstrap le quita el foco. heightAuto:false porque la página
    // es app-shell (html/body al 100 %).
    function swalTarget() {
        const abiertos = document.querySelectorAll('.modal.show');
        return abiertos.length ? abiertos[abiertos.length - 1] : (document.getElementById('modalCampus') || 'body');
    }
    const TITULOS_SWAL = { success: 'Listo', error: 'Error', warning: 'Atención', info: 'Información' };
    function aviso(icon, texto, opts = {}) {
        if (typeof Swal === 'undefined') { window.alert(texto); return Promise.resolve(); }
        return Swal.fire(Object.assign({
            icon,
            title: TITULOS_SWAL[icon] || '',
            text: texto,
            confirmButtonText: 'Aceptar',
            target: swalTarget(),
            heightAuto: false,
        }, opts));
    }
    async function confirmar(texto, botonSi = 'Sí, continuar', peligro = false) {
        if (typeof Swal === 'undefined') return window.confirm(texto);
        const r = await Swal.fire({
            icon: 'warning',
            title: '¿Está seguro?',
            text: texto,
            showCancelButton: true,
            confirmButtonText: botonSi,
            cancelButtonText: 'Cancelar',
            confirmButtonColor: peligro ? '#dc3545' : undefined,
            reverseButtons: true,
            target: swalTarget(),
            heightAuto: false,
        });
        return r.isConfirmed;
    }

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

            if (!json.ok) {
                aviso('error', json.error || json.msg || 'No se pudo guardar.');
            }

            if (json.ok) {
                await aviso('success', json.msg || 'Guardado correctamente.', { timer: 1200, showConfirmButton: false });
                setTimeout(() => {
                    getModal()?.hide();
                    if (window.fetchSearchCampus) window.fetchSearchCampus();
                    window.dispatchEvent(new CustomEvent('campusGuardado', {
                        detail: { id: json.id || id, nombre: json.nombre || fd.get('nombre') }
                    }));
                }, 600);
            }
        } catch (e) {
            aviso('error', 'Error de conexión con el servidor.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
        }
    };

    window.eliminarCampusModal = async function () {
        const id = document.getElementById('campus_id_modal')?.value;
        if (!id || !(await confirmar('Se eliminará este campus.', 'Sí, eliminar', true))) return;
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
                aviso('error', json.error || 'No se pudo eliminar.');
            }
        } catch (e) { aviso('error', 'Error de conexión al eliminar.'); } finally { if (btn) btn.disabled = false; }
    };

})(window, document);
