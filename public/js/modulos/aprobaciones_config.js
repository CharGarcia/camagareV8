/**
 * Módulo Aprobaciones — modal de alta/edición.
 *
 * El listado (buscador, orden, paginación, exportaciones) lo maneja la vista;
 * aquí vive solo el modal: elegir proceso, aprobadores, monto mínimo y estado.
 *
 * Depende de las variables que inyecta la vista: APR_URL, APR_PERM,
 * APR_USUARIOS, APR_DISPONIBLES y APR_CONFIGURADAS.
 */
(function () {
    'use strict';

    let seleccion = new Set();   // aprobadores elegidos en el modal abierto
    let modal = null;

    function escapar(txt) {
        return String(txt ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);
    }

    function nombreUsuario(id) {
        const u = APR_USUARIOS.find(x => x.id === id);
        return u ? u.nombre : ('Usuario #' + id);
    }

    function renderChips() {
        const cont = document.getElementById('apr-chips');
        cont.innerHTML = '';
        seleccion.forEach(uid => {
            const chip = document.createElement('span');
            chip.className = 'badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 fw-medium d-inline-flex align-items-center gap-1';
            chip.innerHTML = escapar(nombreUsuario(uid))
                + ' <i class="bi bi-x-lg" style="cursor:pointer; font-size:.65rem;"></i>';
            chip.querySelector('i').addEventListener('click', () => {
                seleccion.delete(uid);
                renderChips();
            });
            cont.appendChild(chip);
        });
    }

    function mostrarDescripcion() {
        const sel = document.getElementById('apr-proceso');
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('apr-proceso-desc').textContent = opt ? (opt.dataset.desc || '') : '';
    }

    /**
     * Abre el modal. Sin idTipo = alta: el select ofrece solo los procesos que la
     * empresa aún no configuró. Con idTipo = edición: el proceso queda fijo.
     */
    function abrirModal(idTipo) {
        const sel = document.getElementById('apr-proceso');
        const esEdicion = !!idTipo;
        const cfg = esEdicion ? APR_CONFIGURADAS[idTipo] : null;

        if (esEdicion && !cfg) return;

        document.getElementById('apr-modal-titulo').textContent = esEdicion ? 'Editar aprobación' : 'Nueva aprobación';
        document.getElementById('apr-id-tipo').value = esEdicion ? idTipo : '';

        // Al editar el proceso no se cambia: sería otra aprobación distinta.
        if (esEdicion) {
            sel.innerHTML = '<option value="' + cfg.id + '" data-desc="' + escapar(cfg.descripcion) + '">' + escapar(cfg.nombre) + '</option>';
            sel.disabled = true;
        } else {
            sel.disabled = false;
            sel.innerHTML = '<option value="" data-desc="">Selecciona un proceso…</option>'
                + APR_DISPONIBLES.map(t =>
                    '<option value="' + t.id + '" data-desc="' + escapar(t.descripcion) + '">' + escapar(t.nombre) + '</option>'
                ).join('');
        }

        seleccion = new Set(esEdicion ? cfg.aprobadores : []);
        renderChips();
        mostrarDescripcion();

        document.getElementById('apr-umbral').value = (esEdicion && cfg.umbral !== null && cfg.umbral !== '') ? cfg.umbral : '';
        document.getElementById('apr-activa').checked = esEdicion ? cfg.activa : true;
        document.getElementById('apr-buscar').value = '';

        // Eliminar solo aplica a una aprobación ya creada.
        document.getElementById('apr-btn-eliminar').classList.toggle('d-none', !esEdicion || !APR_PERM.eliminar);

        const puedeGuardar = esEdicion ? APR_PERM.actualizar : APR_PERM.crear;
        document.getElementById('apr-btn-guardar').classList.toggle('d-none', !puedeGuardar);
        document.getElementById('apr-buscar').disabled = !puedeGuardar;
        document.getElementById('apr-umbral').disabled = !puedeGuardar;
        document.getElementById('apr-activa').disabled = !puedeGuardar;

        modal.show();
    }

    function setupBuscador() {
        const input = document.getElementById('apr-buscar');
        const dropdown = document.getElementById('apr-dropdown');
        const wrap = document.getElementById('apr-wrap');

        const ocultar = () => { dropdown.classList.add('d-none'); dropdown.innerHTML = ''; };

        function buscar() {
            const q = input.value.trim().toLowerCase();
            if (q === '') { ocultar(); return; }
            const candidatos = APR_USUARIOS
                .filter(u => !seleccion.has(u.id) && u.nombre.toLowerCase().includes(q))
                .slice(0, 8);
            if (candidatos.length === 0) { ocultar(); return; }

            dropdown.innerHTML = candidatos.map(u =>
                '<button type="button" class="list-group-item list-group-item-action small py-1 px-2" data-uid="' + u.id + '">' + escapar(u.nombre) + '</button>'
            ).join('');
            dropdown.classList.remove('d-none');
            dropdown.querySelectorAll('[data-uid]').forEach(btn => {
                btn.addEventListener('click', () => {
                    seleccion.add(parseInt(btn.dataset.uid, 10));
                    renderChips();
                    input.value = '';
                    ocultar();
                    input.focus();
                });
            });
        }

        wrap.addEventListener('click', () => { if (!input.disabled) input.focus(); });
        input.addEventListener('input', buscar);
        input.addEventListener('focus', buscar);
        input.addEventListener('keydown', (e) => {
            // Con el campo vacío, Backspace/Delete quita el último aprobador.
            if ((e.key === 'Backspace' || e.key === 'Delete') && input.value === '' && seleccion.size > 0) {
                e.preventDefault();
                const ultimo = Array.from(seleccion).pop();
                seleccion.delete(ultimo);
                renderChips();
                ocultar();
            }
        });
        document.addEventListener('click', (e) => {
            if (!wrap.contains(e.target) && !dropdown.contains(e.target)) ocultar();
        });
    }

    async function guardar() {
        const idTipo = document.getElementById('apr-proceso').value;
        if (!idTipo) {
            Swal.fire('Falta el proceso', 'Selecciona el proceso que va a requerir aprobación.', 'warning');
            return;
        }
        if (seleccion.size === 0) {
            Swal.fire('Falta un aprobador', 'Agrega al menos un usuario que pueda aprobar.', 'warning');
            return;
        }

        const btn = document.getElementById('apr-btn-guardar');
        btn.disabled = true;

        const fd = new FormData();
        fd.append('id_tipo', idTipo);
        if (document.getElementById('apr-activa').checked) fd.append('requiere_aprobacion', '1');
        const umbral = document.getElementById('apr-umbral').value;
        if (umbral !== '') fd.append('umbral_monto', umbral);
        seleccion.forEach(uid => fd.append('usuarios_aprobadores[]', uid));

        try {
            const res = await fetch(APR_URL + '/guardarAjax', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.ok) {
                Swal.fire('No se pudo guardar', json.mensaje || 'Error desconocido.', 'error');
                btn.disabled = false;
                return;
            }
            modal.hide();
            Swal.fire({ icon: 'success', title: json.mensaje, timer: 1200, showConfirmButton: false })
                .then(() => window.location.reload());
        } catch (err) {
            Swal.fire('Error de conexión', 'No se pudo contactar al servidor.', 'error');
            btn.disabled = false;
        }
    }

    async function eliminar() {
        const idTipo = document.getElementById('apr-id-tipo').value;
        if (!idTipo) return;
        const cfg = APR_CONFIGURADAS[idTipo];

        const conf = await Swal.fire({
            icon: 'warning',
            title: '¿Eliminar la aprobación?',
            html: '<div class="small">Se quitará <strong>' + escapar(cfg ? cfg.nombre : '') + '</strong> del listado.<br>'
                + 'Ese proceso dejará de pedir aprobación y se ejecutará directamente.</div>',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
        });
        if (!conf.isConfirmed) return;

        const fd = new FormData();
        fd.append('id_tipo', idTipo);
        try {
            const res = await fetch(APR_URL + '/eliminarAjax', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.ok) {
                Swal.fire('No se pudo eliminar', json.mensaje || 'Error desconocido.', 'error');
                return;
            }
            modal.hide();
            Swal.fire({ icon: 'success', title: json.mensaje, timer: 1200, showConfirmButton: false })
                .then(() => window.location.reload());
        } catch (err) {
            Swal.fire('Error de conexión', 'No se pudo contactar al servidor.', 'error');
        }
    }

    // Las filas se repintan por AJAX, así que el clic se resuelve por el
    // onclick del HTML que genera el controlador (no por listeners atados a
    // los <tr> de la carga inicial, que desaparecen al buscar o paginar).
    window.APR_abrirModal = abrirModal;

    document.addEventListener('DOMContentLoaded', () => {
        modal = new bootstrap.Modal(document.getElementById('modalAprobacion'));
        setupBuscador();

        document.getElementById('apr-proceso').addEventListener('change', mostrarDescripcion);
        document.getElementById('apr-btn-guardar').addEventListener('click', guardar);
        document.getElementById('apr-btn-eliminar').addEventListener('click', eliminar);

        const btnNueva = document.getElementById('apr-btn-nueva');
        if (btnNueva) {
            btnNueva.addEventListener('click', () => {
                if (APR_DISPONIBLES.length === 0) {
                    Swal.fire(
                        'No hay procesos disponibles',
                        'Ya configuraste todos los procesos aprobables del sistema.',
                        'info'
                    );
                    return;
                }
                abrirModal(null);
            });
        }
    });
})();
