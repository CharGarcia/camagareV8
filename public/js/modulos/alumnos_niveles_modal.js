/**
 * Lógica compartida para el Modal de Niveles/Cursos (Alumnos).
 * Al guardar emite en `document` el evento `nivelGuardado` con
 * { id, nombre, nuevo }: el modal de Alumno lo usa para dejar el nivel recién
 * creado seleccionado (CLAUDE.md §9, "Crear al vuelo"). Si se abrió encima de
 * otro modal y es un alta, se cierra solo; desde su propio listado queda abierto.
 */
(function (window, document) {
    'use strict';

    if (window.__CMG_nivelModal) return;
    window.__CMG_nivelModal = true;

    const urlBase = (typeof BASE_URL !== 'undefined') ? (BASE_URL + '/modulos/alumnos-niveles') : (window.location.origin + '/sistema/public/modulos/alumnos-niveles');
    const modalEl = document.getElementById('modalNivel');
    let modalInst = null;
    let guardando = false;

    // ── Mensajes (SweetAlert) ────────────────────────────────────────────────
    // Con un modal abierto el popup cuelga DENTRO de él (target): colgado de <body>
    // el focus trap de Bootstrap le quita el foco. heightAuto:false porque la página
    // es app-shell (html/body al 100 %).
    function swalTarget() {
        const abiertos = document.querySelectorAll('.modal.show');
        return abiertos.length ? abiertos[abiertos.length - 1] : (document.getElementById('modalNivel') || 'body');
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

    // Encima del modal de Alumno: los modales están forzados a 5060 !important
    // (app.css), así que el submodal y su backdrop se suben con inline important.
    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', () => {
            if (!(typeof window.CMG_modalSobreOtro === 'function' && window.CMG_modalSobreOtro(modalEl))) return;
            modalEl.style.setProperty('z-index', '5080', 'important');
            setTimeout(() => {
                const bds = document.querySelectorAll('.modal-backdrop');
                if (bds.length) bds[bds.length - 1].style.setProperty('z-index', '5075', 'important');
            }, 0);
        });
        modalEl.addEventListener('hidden.bs.modal', () => modalEl.style.removeProperty('z-index'));
    }

    function modoEdicion(id) {
        document.getElementById('nivel_id_modal').value = id;
        document.getElementById('tituloModalNivel').textContent = 'Editar Nivel/Curso';
        document.getElementById('btnEliminarNivelModal')?.classList.remove('d-none');
    }

    window.abrirModalNivelCrear = function () {
        const form = document.getElementById('formNivelModal');
        if (!form) return;
        form.reset();

        document.getElementById('nivel_id_modal').value = '';
        document.getElementById('nivel_orden_modal').value = '0';
        document.getElementById('tituloModalNivel').textContent = 'Nuevo Nivel/Curso';
        document.getElementById('modalAlertNivel')?.classList.add('d-none');
        document.getElementById('btnEliminarNivelModal')?.classList.add('d-none');

        getModal()?.show();
        setTimeout(() => document.getElementById('nivel_nombre_modal')?.focus(), 400);
    };

    window.abrirModalNivelEditar = function (rowOrData) {
        const data = (rowOrData instanceof HTMLElement) ? JSON.parse(rowOrData.dataset.row) : rowOrData;
        const form = document.getElementById('formNivelModal');
        if (!form || !data) return;
        form.reset();

        document.getElementById('nivel_nombre_modal').value = data.nombre || '';
        document.getElementById('nivel_orden_modal').value = data.orden ?? 0;
        document.getElementById('nivel_estado_modal').value = data.estado || 'activo';
        document.getElementById('modalAlertNivel')?.classList.add('d-none');
        modoEdicion(data.id);

        getModal()?.show();
    };

    window.guardarNivelModal = async function () {
        // Bandera antes del primer await: un doble clic no crea dos niveles.
        if (guardando) return;
        const form = document.getElementById('formNivelModal');
        const btn = document.getElementById('btnGuardarNivelModal');
        if (!form || !btn) return;
        guardando = true;

        const id = document.getElementById('nivel_id_modal')?.value;
        const nuevo = !id;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

        try {
            const fd = new FormData(form);
            const resp = await fetch(nuevo ? `${urlBase}/store` : `${urlBase}/update`, { method: 'POST', body: fd });
            const json = await resp.json();

            if (!json.ok) {
                aviso('error', json.error || json.msg || 'No se pudo guardar.');
                return;
            }

            const idGuardado = json.id || id;
            // El id queda puesto antes de reactivar Guardar: el siguiente clic actualiza.
            if (nuevo) modoEdicion(idGuardado);

            const alVuelo = nuevo && typeof window.CMG_modalSobreOtro === 'function' && window.CMG_modalSobreOtro(modalEl);
            document.dispatchEvent(new CustomEvent('nivelGuardado', {
                detail: { id: idGuardado, nombre: json.nombre || fd.get('nombre'), nuevo }
            }));
            if (window.fetchSearchNivel) window.fetchSearchNivel();

            if (alVuelo) {
                getModal()?.hide();
                aviso('success', 'Nivel/curso creado y seleccionado.', { timer: 1400, showConfirmButton: false });
            } else {
                aviso('success', json.msg || 'Guardado correctamente.', { timer: 1200, showConfirmButton: false });
            }
        } catch (e) {
            aviso('error', 'Error de conexión con el servidor.');
        } finally {
            guardando = false;
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
        }
    };

    window.eliminarNivelModal = async function () {
        const id = document.getElementById('nivel_id_modal')?.value;
        if (!id || !(await confirmar('Se eliminará este nivel/curso.', 'Sí, eliminar', true))) return;
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
                aviso('error', json.error || 'No se pudo eliminar.');
            }
        } catch (e) { aviso('error', 'Error de conexión al eliminar.'); } finally { if (btn) btn.disabled = false; }
    };

})(window, document);
