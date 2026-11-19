/**
 * Modal de Bodega (crear / editar) — app/views/modulos/bodegas/modal.php.
 *
 * Pieza reutilizable: la usa el listado de Bodegas y las pantallas que crean una bodega al vuelo
 * (Configuración de Producción). Emite:
 *   - `bodegaGuardado`  detail: { id, nombre, nuevo, id_establecimiento, status }
 *   - `bodegaEliminada` detail: { id }
 * Una bodega NUEVA cierra el modal sola si se abrió encima de otro modal o con
 * abrirModalBodegaCrear({ cerrarAlCrear: true }); desde su propio listado se queda abierta en
 * modo edición (CLAUDE.md §9, "Crear al vuelo").
 */
(function () {
    'use strict';
    if (window.__CMG_bodegaModal) return;
    window.__CMG_bodegaModal = true;

    const modalEl = document.getElementById('modalBodega');
    const form = document.getElementById('formBodega');
    if (!modalEl || !form) return;

    const urlBase = modalEl.dataset.urlBase;
    let modalInst = null;
    let cerrarAlCrear = false;

    function getModal() {
        if (!modalInst) modalInst = new bootstrap.Modal(modalEl);
        return modalInst;
    }

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fmt2 = (v) => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // ─── Mensajes (SweetAlert) ───────────────────────────────────────────────
    function avisoError(texto, titulo = 'No se pudo completar') {
        if (typeof Swal === 'undefined') { alert(texto); return; }
        Swal.fire({ icon: 'error', title: titulo, text: texto, confirmButtonText: 'Aceptar' });
    }
    function avisoOk(texto) {
        if (typeof Swal === 'undefined') return;
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', showConfirmButton: false, timer: 2200, title: texto });
    }
    function emitir(nombre, detail) {
        document.dispatchEvent(new CustomEvent(nombre, { detail }));
    }

    // ─── Interruptor de Estado ───────────────────────────────────────────────
    // El valor real va en el oculto #bodega_status (1/0), que es el que leen la
    // estrella de favorito (favoritos.js trabaja con .value) y el guardado. El
    // interruptor #bodega_status_switch solo lo muestra y lo cambia.
    function sincronizarStatusSwitchBodega() {
        const oculto = document.getElementById('bodega_status');
        const sw     = document.getElementById('bodega_status_switch');
        if (oculto && sw) sw.checked = String(oculto.value) !== '0';
    }
    (function initStatusSwitchBodega() {
        const oculto = document.getElementById('bodega_status');
        const sw     = document.getElementById('bodega_status_switch');
        if (!oculto || !sw) return;
        sw.addEventListener('change', () => {
            oculto.value = sw.checked ? '1' : '0';
            oculto.dispatchEvent(new Event('change')); // actualiza la estrella de favorito
        });
        // aplicarFavoritosModal() fija el oculto y dispara 'change'.
        oculto.addEventListener('change', sincronizarStatusSwitchBodega);
    })();
    function fijarStatusBodega(activo) {
        const oculto = document.getElementById('bodega_status');
        if (oculto) oculto.value = activo ? '1' : '0';
        sincronizarStatusSwitchBodega();
    }

    function mostrarPestanaGeneral() {
        const btn = document.getElementById('tab-general-btn');
        if (btn && typeof bootstrap !== 'undefined') bootstrap.Tab.getOrCreateInstance(btn).show();
    }

    /** @param {{cerrarAlCrear?: boolean}} [opts] */
    window.abrirModalBodegaCrear = function (opts) {
        cerrarAlCrear = !!(opts && opts.cerrarAlCrear);
        form.reset();
        // form.reset() no toca los campos ocultos: el estado vuelve a Activo a mano.
        fijarStatusBodega(true);
        document.getElementById('bodega_id').value = '';
        document.getElementById('tituloModal').textContent = 'Nueva Bodega';
        document.getElementById('btnEliminar')?.classList.add('d-none');
        document.getElementById('bodegaSaldoBox').classList.add('d-none');
        mostrarPestanaGeneral();

        // Solo el listado de Bodegas pinta las estrellas de favorito dentro del modal.
        if (typeof aplicarFavoritosModal === 'function' && modalEl.querySelector('.btn-favorito')) {
            aplicarFavoritosModal('#modalBodega');
        }
        // Bodega nueva: todos los usuarios con acceso (modelo "lista de denegados").
        fetchAccesosUsuarios(0);
        getModal().show();
    };

    window.abrirModalBodegaEditar = function (row) {
        cerrarAlCrear = false;
        const data = JSON.parse(row.dataset.row);
        form.reset();
        document.getElementById('bodega_id').value = data.id;
        document.getElementById('bodega_nombre').value = data.nombre || '';
        const isInactive = data.status === false || data.status === 'false' || data.status === 0 || data.status === '0' || data.status === 'f';
        fijarStatusBodega(!isInactive);
        const selEst = document.getElementById('bodega_id_establecimiento');
        if (selEst) selEst.value = data.id_establecimiento ? String(data.id_establecimiento) : '';

        document.getElementById('tituloModal').textContent = 'Editar Bodega';
        document.getElementById('btnEliminar')?.classList.remove('d-none');
        mostrarPestanaGeneral();

        pintarSaldo({
            productos: data.productos,
            unidades: data.unidades,
            valor_costo: data.valor_costo,
            valor_venta: data.valor_venta
        });
        fetchAccesosUsuarios(data.id);
        getModal().show();
    };

    function pintarSaldo(s) {
        document.getElementById('saldo_productos').textContent = Number(s.productos || 0).toLocaleString('en-US');
        document.getElementById('saldo_unidades').textContent = fmt2(s.unidades);
        document.getElementById('saldo_costo').textContent = fmt2(s.valor_costo);
        document.getElementById('saldo_venta').textContent = fmt2(s.valor_venta);
        document.getElementById('bodegaSaldoBox').classList.remove('d-none');
    }

    async function refrescarSaldo(id) {
        try {
            const resp = await fetch(`${urlBase}/getDetalleAjax?id=${id}`);
            const json = await resp.json();
            if (json.ok && json.data && json.data.saldo) pintarSaldo(json.data.saldo);
        } catch (e) {
            console.error('Error cargando saldo de la bodega:', e);
        }
    }

    async function fetchAccesosUsuarios(id) {
        const container = document.getElementById('tbodyAccesosUsuarios');
        if (!container) return;

        if (!id) {
            container.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted small">Guarde la bodega para configurar los accesos. Por defecto, todos los usuarios tienen acceso.</td></tr>';
            return;
        }

        container.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm mb-2"></div><div class="small">Cargando usuarios...</div></td></tr>';

        try {
            const resp = await fetch(`${urlBase}/getUsuariosAccesoAjax?id=${id}`);
            const json = await resp.json();

            if (json.ok) {
                let html = '';
                if (json.data.length === 0) {
                    html = '<tr><td colspan="3" class="text-center py-4">No hay usuarios disponibles.</td></tr>';
                } else {
                    json.data.forEach(u => {
                        const uid = parseInt(u.id, 10);
                        html += `
                            <tr class="align-middle">
                                <td class="text-center">
                                    <div class="form-check d-inline-block">
                                        <input class="form-check-input check-acceso" type="checkbox" value="${uid}" ${u.tiene_acceso ? 'checked' : ''} onchange="validarDefaultInPermiso(this)">
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="form-check d-inline-block">
                                        <input class="form-check-input radio-default" type="radio" name="user_default_${uid}" data-user-id="${uid}" ${u.es_default ? 'checked' : ''} ${u.tiene_acceso ? '' : 'disabled'}>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold small">${esc(u.nombre)}</div>
                                    <div class="text-muted" style="font-size: 0.65rem;">${esc(u.mail)}</div>
                                </td>
                            </tr>`;
                    });
                }
                container.innerHTML = html;
            } else {
                container.innerHTML = `<tr><td colspan="3" class="text-center py-4 text-danger small">${esc(json.error || 'Error')}</td></tr>`;
            }
        } catch (e) {
            container.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-danger small">Error de conexión</td></tr>';
        }
    }

    window.validarDefaultInPermiso = function (chk) {
        const row = chk.closest('tr');
        if (!row) return;
        const radio = row.querySelector('.radio-default');
        if (!radio) return;
        if (!chk.checked) {
            radio.checked = false;
            radio.disabled = true;
        } else {
            radio.disabled = false;
        }
    };

    // Bandera "en curso" (§8): un Enter repetido o un doble clic no envían otro guardado.
    let guardandoBodega = false;
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (guardandoBodega) return;
        guardandoBodega = true;
        const id = document.getElementById('bodega_id').value;
        const action = id ? '/update' : '/store';
        const fd = new FormData(form);
        const btn = document.getElementById('btnGuardar');
        const restaurarBtn = () => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
        };

        if (!String(fd.get('nombre') || '').trim()) {
            guardandoBodega = false;
            avisoError('Escriba el nombre de la bodega.', 'Falta un dato');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        // Aviso mientras el servidor guarda (no se puede cerrar hasta que responda).
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: id ? 'Actualizando bodega...' : 'Guardando bodega...',
                text: 'Por favor espere, no cierre esta ventana.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });
        }

        try {
            const resp = await fetch(urlBase + action, { method: 'POST', body: fd });
            const json = await resp.json();

            if (json.ok) {
                const bodegaId = id || json.id;
                const nuevo = !id;
                const detalle = {
                    id: bodegaId ? parseInt(bodegaId, 10) : null,
                    nombre: String(fd.get('nombre') || '').trim(),
                    nuevo,
                    id_establecimiento: fd.get('id_establecimiento') || '',
                    status: String(fd.get('status')) !== '0',
                };

                // Alta encima de otro modal o pedida desde otra pantalla: se cierra sola y la
                // pantalla de origen la deja seleccionada al recibir `bodegaGuardado`.
                if (nuevo && json.id && (cerrarAlCrear || (window.CMG_modalSobreOtro && window.CMG_modalSobreOtro(modalEl)))) {
                    cerrarAlCrear = false;
                    getModal().hide();
                    emitir('bodegaGuardado', detalle);
                    avisoOk('Bodega creada y seleccionada.');
                    return; // el botón se restaura en finally; el formulario se limpia al volver a abrir
                }

                // El modal se queda abierto. Si era nueva, pasa a modo edición con su id:
                // así el siguiente Guardar actualiza esta bodega y no crea otra.
                if (nuevo && json.id) {
                    document.getElementById('bodega_id').value = json.id;
                    document.getElementById('tituloModal').textContent = 'Editar Bodega';
                    document.getElementById('btnEliminar')?.classList.remove('d-none');
                }
                let avisoAccesos = '';
                if (bodegaId) {
                    // Bodega recién creada: la tabla de accesos aún no estaba cargada,
                    // no hay nada que guardar (todos con acceso por defecto).
                    if (id) {
                        const rAcc = await guardarAccesosUsuarios(bodegaId);
                        if (rAcc && rAcc.ok === false) avisoAccesos = rAcc.error || 'No se pudieron guardar los accesos de usuarios.';
                    }
                    refrescarSaldo(bodegaId);
                    fetchAccesosUsuarios(bodegaId);
                }
                emitir('bodegaGuardado', detalle);
                if (avisoAccesos) {
                    avisoError(avisoAccesos, 'Bodega guardada, accesos sin guardar');
                } else {
                    avisoOk(json.msg || 'Guardado correctamente.');
                }
            } else {
                avisoError(json.msg || json.error || 'Error desconocido');
            }
        } catch (err) {
            avisoError('Error de red al conectar con el servidor.');
        } finally {
            restaurarBtn();
            guardandoBodega = false;
        }
    });

    async function guardarAccesosUsuarios(idBodega) {
        const container = document.getElementById('tbodyAccesosUsuarios');
        if (!container) return null;

        const filas = container.querySelectorAll('tr.align-middle');
        if (filas.length === 0) return null; // la tabla no llegó a cargarse: no tocar accesos

        const accesos = [];
        filas.forEach(row => {
            const chk = row.querySelector('.check-acceso');
            const rad = row.querySelector('.radio-default');
            if (chk && chk.checked) {
                accesos.push({ id_usuario: chk.value, es_default: rad ? rad.checked : false });
            }
        });

        const fd = new FormData();
        fd.append('id_bodega', idBodega);
        fd.append('accesos', JSON.stringify(accesos));

        try {
            const resp = await fetch(`${urlBase}/guardarAccesosAjax`, { method: 'POST', body: fd });
            return await resp.json();
        } catch (e) {
            console.error('Error guardando accesos:', e);
            return { ok: false, error: 'Error de conexión al guardar los accesos.' };
        }
    }

    window.eliminarBodega = async function () {
        const id = document.getElementById('bodega_id').value;
        if (!id) return;
        const nombre = document.getElementById('bodega_nombre').value || 'esta bodega';

        if (typeof Swal !== 'undefined') {
            const r = await Swal.fire({
                icon: 'warning',
                title: '¿Eliminar la bodega?',
                html: `Se eliminará <b>${esc(nombre)}</b>. Solo se puede eliminar una bodega sin movimientos ni documentos asociados.`,
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                focusCancel: true
            });
            if (!r.isConfirmed) return;
            Swal.fire({ title: 'Eliminando bodega...', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
        } else if (!confirm('¿Está seguro de eliminar esta bodega?')) {
            return;
        }

        const fd = new FormData();
        fd.append('id_eliminar', id);

        try {
            const resp = await fetch(urlBase + '/delete', { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                getModal().hide();
                emitir('bodegaEliminada', { id: parseInt(id, 10) });
                avisoOk(json.msg || 'Bodega eliminada correctamente.');
            } else {
                avisoError(json.error || 'No se pudo eliminar la bodega.', 'No se puede eliminar');
            }
        } catch (e) {
            avisoError('Error de conexión al eliminar la bodega.');
        }
    };
})();
