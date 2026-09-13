/**
 * Módulo Responsables de Traslado — listado (búsqueda, orden, paginación) y modal.
 * El CSRF lo adjunta public/js/csrf.js automáticamente (layout estándar).
 */
(function (window, document) {
    'use strict';

    const urlBase = window.RT_URL_BASE || '';
    let timerBuscar = null;

    // ── Listado ─────────────────────────────────────────────────────────────

    window.RT_fetchSearch = async function (page) {
        page = page || 1;
        const input = document.getElementById('buscarResponsable');
        const term  = input ? input.value.trim() : '';
        const uri = urlBase + '/search-ajax'
            + '?b=' + encodeURIComponent(term)
            + '&page=' + page
            + '&sort=' + encodeURIComponent(window.RT_currentSort || 'nombre')
            + '&dir=' + encodeURIComponent(window.RT_currentDir || 'ASC');

        try {
            const resp = await fetch(uri);
            const data = await resp.json();
            if (!data.ok) return;

            window.RT_currentPage = page;
            document.getElementById('tbodyResponsables').innerHTML   = data.rows;
            document.getElementById('rtPaginationContainer').innerHTML = data.pagination;
            document.getElementById('rtPaginationInfo').textContent   = data.info;

            const btnPdf   = document.getElementById('btnExportPdf');
            const btnExcel = document.getElementById('btnExportExcel');
            if (btnPdf && data.pdf_url)     btnPdf.href   = data.pdf_url;
            if (btnExcel && data.excel_url) btnExcel.href = data.excel_url;

            RT_pintarIconosOrden();
        } catch (e) {
            console.error('Responsables de traslado: fallo al refrescar el listado', e);
        }
    };

    function RT_pintarIconosOrden() {
        document.querySelectorAll('.sortable-header').forEach(function (th) {
            const icon = th.querySelector('i');
            if (!icon) return;
            if (th.dataset.sort === window.RT_currentSort) {
                icon.className = (String(window.RT_currentDir).toUpperCase() === 'ASC')
                    ? 'bi bi-sort-alpha-down text-primary ms-1'
                    : 'bi bi-sort-alpha-up text-primary ms-1';
            } else {
                icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
            }
        });
    }

    // ── Modal ───────────────────────────────────────────────────────────────

    function setVal(id, valor) {
        const el = document.getElementById(id);
        if (el) el.value = valor == null ? '' : valor;
    }

    function RT_resetModal() {
        ['rt-id', 'rt-nombre', 'rt-identificacion', 'rt-telefono', 'rt-email'].forEach(function (id) {
            setVal(id, '');
        });
        setVal('rt-estado', 'activo');

        const err = document.getElementById('rt-email-error');
        if (err) { err.textContent = ''; err.classList.add('d-none'); }
        const campoEmail = document.getElementById('rt-email');
        if (campoEmail) campoEmail.classList.remove('is-invalid');

        const aud = document.getElementById('rt-auditoria');
        if (aud) { aud.textContent = ''; aud.classList.add('d-none'); }

        const btn = document.getElementById('btn-rt-guardar');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar'; }

        RT_usuariosPendiente();
    }

    function RT_mostrarModal() {
        const el = document.getElementById('modalResponsable');
        if (el) bootstrap.Modal.getOrCreateInstance(el).show();
    }

    window.RT_abrirCrear = function () {
        RT_resetModal();
        document.getElementById('rt-modal-titulo').textContent = 'Nuevo Responsable de Traslado';
        const btnDel = document.getElementById('btn-rt-eliminar');
        if (btnDel) btnDel.classList.add('d-none');
        RT_mostrarTabGeneral();
        RT_mostrarModal();
    };

    window.RT_abrirEditar = function (origen) {
        const r = (origen instanceof HTMLElement) ? JSON.parse(origen.dataset.row) : origen;
        RT_resetModal();

        document.getElementById('rt-modal-titulo').textContent = 'Editar Responsable de Traslado';
        setVal('rt-id', r.id);
        setVal('rt-nombre', r.nombre);
        setVal('rt-identificacion', r.identificacion);
        setVal('rt-telefono', r.telefono);
        setVal('rt-email', r.email);
        setVal('rt-estado', r.estado || 'activo');

        RT_pintarAuditoria(r);

        const btnDel = document.getElementById('btn-rt-eliminar');
        if (btnDel) btnDel.classList.remove('d-none');
        RT_mostrarTabGeneral();
        RT_cargarUsuarios(r.id);
        RT_mostrarModal();
    };

    /** Quién y cuándo, como línea discreta al pie (no como pestaña). */
    function RT_pintarAuditoria(r) {
        const aud = document.getElementById('rt-auditoria');
        if (!aud) return;
        const partes = [];
        if (r.created_at) partes.push('Creado: ' + RT_fecha(r.created_at));
        if (r.updated_at) partes.push('Última modificación: ' + RT_fecha(r.updated_at));
        if (!partes.length) { aud.classList.add('d-none'); return; }
        aud.textContent = partes.join(' · ');
        aud.classList.remove('d-none');
    }

    /** El servidor puede mandar la fecha ya formateada (d-m-Y H:i:s) o cruda. */
    function RT_fecha(valor) {
        if (/^\d{2}-\d{2}-\d{4}/.test(valor)) return valor;
        const d = new Date(String(valor).replace(' ', 'T'));
        if (isNaN(d.getTime())) return valor;
        const p = function (n) { return String(n).padStart(2, '0'); };
        return p(d.getDate()) + '-' + p(d.getMonth() + 1) + '-' + d.getFullYear()
            + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
    }

    // ── Pestaña "Usuarios vinculados" ───────────────────────────────────────
    //
    // Vista inversa del vínculo que se administra en Configuración → Usuarios del
    // sistema. Decide qué entregas ve cada repartidor en la app móvil.

    /** Al abrir el modal siempre se empieza por General, no por donde se quedó. */
    function RT_mostrarTabGeneral() {
        const btn = document.getElementById('rt-tab-general-btn');
        if (btn && typeof bootstrap !== 'undefined') bootstrap.Tab.getOrCreateInstance(btn).show();
    }

    function RT_pintarContadorUsuarios(n) {
        const badge = document.getElementById('rt-usuarios-contador');
        if (!badge) return;
        badge.textContent = n;
        badge.classList.toggle('d-none', !n);
    }

    /** Responsable aún sin guardar: no hay a qué vincular. */
    function RT_usuariosPendiente() {
        const tbody = document.getElementById('rt-tbody-usuarios');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-muted small">Guarde el responsable para poder vincular usuarios.</td></tr>';
        }
        document.getElementById('rt-usuarios-editor')?.classList.add('d-none');
        document.getElementById('rt-usuarios-solo-lectura')?.classList.add('d-none');
        RT_pintarContadorUsuarios(0);
    }

    async function RT_cargarUsuarios(idResponsable) {
        const tbody = document.getElementById('rt-tbody-usuarios');
        if (!tbody || !idResponsable) return;

        tbody.innerHTML = '<tr><td colspan="4" class="text-muted small">Cargando…</td></tr>';
        const editor      = document.getElementById('rt-usuarios-editor');
        const soloLectura = document.getElementById('rt-usuarios-solo-lectura');

        try {
            const resp = await fetch(urlBase + '/usuarios-ajax?id=' + encodeURIComponent(idResponsable));
            const d    = await resp.json();
            if (!d.ok) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-danger small">' + (d.mensaje || 'No se pudo cargar.') + '</td></tr>';
                return;
            }

            const puedeEditar = !!d.puede_editar;
            editor?.classList.toggle('d-none', !puedeEditar);
            soloLectura?.classList.toggle('d-none', puedeEditar);

            RT_pintarVinculados(d.vinculados || [], idResponsable, puedeEditar);
            if (puedeEditar) RT_pintarDisponibles(d.disponibles || []);
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-danger small">Error de conexión.</td></tr>';
        }
    }

    function RT_pintarVinculados(filas, idResponsable, puedeEditar) {
        const tbody = document.getElementById('rt-tbody-usuarios');
        RT_pintarContadorUsuarios(filas.length);

        if (!filas.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-muted small">'
                + 'Ningún usuario vinculado. Sin vínculo y sin "acceso total", ese usuario no verá entregas de este responsable.'
                + '</td></tr>';
            return;
        }

        tbody.innerHTML = '';
        filas.forEach(function (u) {
            const tr = document.createElement('tr');

            const tdNombre = document.createElement('td');
            tdNombre.className = 'fw-medium';
            tdNombre.textContent = u.nombre || '';
            tr.appendChild(tdNombre);

            const tdMail = document.createElement('td');
            tdMail.className = 'text-truncate';
            tdMail.style.maxWidth = '220px';
            tdMail.textContent = u.mail || '—';
            tr.appendChild(tdMail);

            // Pista útil: el vínculo solo sirve de verdad si el usuario entra por la app.
            const tdApp = document.createElement('td');
            tdApp.className = 'text-center';
            tdApp.innerHTML = (u.puede_app_movil === true || u.puede_app_movil === 't' || u.puede_app_movil === 1)
                ? '<i class="bi bi-check-circle text-success" title="Tiene habilitada la app móvil"></i>'
                : '<i class="bi bi-dash-circle text-muted" title="Sin acceso a la app móvil: el vínculo solo aplicará en la web"></i>';
            tr.appendChild(tdApp);

            const tdAcc = document.createElement('td');
            tdAcc.className = 'text-end';
            if (puedeEditar) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-outline-danger py-0 px-2';
                btn.innerHTML = '<i class="bi bi-trash3"></i>';
                btn.title = 'Quitar vínculo';
                btn.addEventListener('click', function () { RT_desvincularUsuario(u.id, u.nombre, idResponsable); });
                tdAcc.appendChild(btn);
            } else {
                tdAcc.innerHTML = '<span class="text-muted small">—</span>';
            }
            tr.appendChild(tdAcc);

            tbody.appendChild(tr);
        });
    }

    function RT_pintarDisponibles(filas) {
        const select = document.getElementById('rt-select-usuario');
        if (!select) return;

        select.innerHTML = '';
        if (!filas.length) {
            select.innerHTML = '<option value="">No hay usuarios disponibles en esta empresa</option>';
            select.disabled = true;
            document.getElementById('rt-btn-vincular').disabled = true;
            return;
        }
        select.disabled = false;
        document.getElementById('rt-btn-vincular').disabled = false;

        const vacia = document.createElement('option');
        vacia.value = '';
        vacia.textContent = 'Seleccione un usuario…';
        select.appendChild(vacia);

        filas.forEach(function (u) {
            const op = document.createElement('option');
            op.value = u.id_usuario;
            op.textContent = u.nombre + (u.mail ? ' — ' + u.mail : '');
            select.appendChild(op);
        });
    }

    async function RT_vincularUsuario() {
        const idResponsable = document.getElementById('rt-id').value;
        const select        = document.getElementById('rt-select-usuario');
        const idUsuario     = select ? select.value : '';
        if (!idResponsable || !idUsuario) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Seleccione un usuario.' });
            return;
        }

        const btn = document.getElementById('rt-btn-vincular');
        if (btn) btn.disabled = true;

        const fd = new FormData();
        fd.append('id_responsable', idResponsable);
        fd.append('id_usuario', idUsuario);

        try {
            const resp = await fetch(urlBase + '/vincular-usuario-ajax', { method: 'POST', body: fd });
            const d    = await resp.json();
            if (!d.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: d.mensaje || 'No se pudo vincular.' });
                return;
            }
            await RT_cargarUsuarios(idResponsable);
            window.RT_fetchSearch(window.RT_currentPage);   // refresca la columna "Usuarios"
            Swal.fire({
                toast: true, position: 'top-end', icon: 'success',
                title: d.mensaje, timer: 2500, showConfirmButton: false, timerProgressBar: true
            });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor.' });
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    async function RT_desvincularUsuario(idVinculo, nombre, idResponsable) {
        const conf = await Swal.fire({
            icon: 'warning',
            title: '¿Quitar el vínculo?',
            html: '<strong>' + nombre + '</strong> dejará de ver las entregas de este responsable.',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, quitar',
            cancelButtonText: 'Cancelar'
        });
        if (!conf.isConfirmed) return;

        const fd = new FormData();
        fd.append('id', idVinculo);

        try {
            const resp = await fetch(urlBase + '/desvincular-usuario-ajax', { method: 'POST', body: fd });
            const d    = await resp.json();
            if (!d.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: d.mensaje || 'No se pudo quitar.' });
                return;
            }
            await RT_cargarUsuarios(idResponsable);
            window.RT_fetchSearch(window.RT_currentPage);
            Swal.fire({
                toast: true, position: 'top-end', icon: 'success',
                title: d.mensaje, timer: 2500, showConfirmButton: false, timerProgressBar: true
            });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor.' });
        }
    }

    function RT_validarEmail() {
        const campo = document.getElementById('rt-email');
        const errEl = document.getElementById('rt-email-error');
        if (!campo || !errEl) return true;

        const valor = campo.value.trim();
        if (valor === '') {                       // opcional: vacío es válido
            errEl.classList.add('d-none');
            campo.classList.remove('is-invalid');
            return true;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) {
            errEl.textContent = 'El formato del correo electrónico no es válido.';
            errEl.classList.remove('d-none');
            campo.classList.add('is-invalid');
            return false;
        }
        errEl.classList.add('d-none');
        campo.classList.remove('is-invalid');
        return true;
    }

    function RT_cerrarModal(despues) {
        const modalEl = document.getElementById('modalResponsable');
        const inst    = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
        const limpiar = function () {
            if (!document.querySelector('.modal.show')) {
                document.querySelectorAll('.modal-backdrop').forEach(function (b) { b.remove(); });
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('padding-right');
            }
            if (typeof despues === 'function') despues();
        };
        if (!inst || !modalEl.classList.contains('show')) { limpiar(); return; }
        modalEl.addEventListener('hidden.bs.modal', limpiar, { once: true });
        inst.hide();
    }

    window.RT_guardar = function () {
        const nombre = (document.getElementById('rt-nombre').value || '').trim();
        if (!nombre) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'El nombre del responsable es obligatorio.' });
            return;
        }
        if (!RT_validarEmail()) return;

        const id  = document.getElementById('rt-id').value;
        const btn = document.getElementById('btn-rt-guardar');
        if (btn) {
            btn.disabled  = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando…';
        }

        const fd = new FormData();
        if (id) fd.append('id', id);
        fd.append('nombre', nombre);
        fd.append('identificacion', document.getElementById('rt-identificacion').value.trim());
        fd.append('telefono', document.getElementById('rt-telefono').value.trim());
        fd.append('email', document.getElementById('rt-email').value.trim());
        fd.append('estado', document.getElementById('rt-estado').value);

        fetch(urlBase + '/guardar-ajax', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (btn) {
                    btn.disabled  = false;
                    btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
                }
                if (!d.ok) {
                    Swal.fire({ icon: 'error', title: 'Error', text: d.mensaje || 'No se pudo guardar.' });
                    return;
                }
                // El modal no se cierra al guardar (igual que Clientes y Transportistas):
                // si era nuevo, queda en modo edición con el id recién creado.
                if (!id && d.id) {
                    setVal('rt-id', d.id);
                    document.getElementById('rt-modal-titulo').textContent = 'Editar Responsable de Traslado';
                    const btnDel = document.getElementById('btn-rt-eliminar');
                    if (btnDel) btnDel.classList.remove('d-none');
                    // Ya existe: la pestaña de usuarios deja de estar en espera.
                    RT_cargarUsuarios(d.id);
                }
                if (d.data) {
                    // Valores ya normalizados por el servidor (nombre en mayúsculas,
                    // correo en minúsculas, opcionales vacíos como NULL).
                    setVal('rt-nombre', d.data.nombre);
                    setVal('rt-identificacion', d.data.identificacion);
                    setVal('rt-telefono', d.data.telefono);
                    setVal('rt-email', d.data.email);
                    RT_pintarAuditoria(d.data);
                }

                window.RT_fetchSearch(id ? window.RT_currentPage : 1);
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: d.mensaje || 'Guardado', timer: 2500,
                    showConfirmButton: false, timerProgressBar: true
                });
                document.dispatchEvent(new CustomEvent('responsableTrasladoGuardado', { detail: d }));
            })
            .catch(function () {
                if (btn) {
                    btn.disabled  = false;
                    btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
                }
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor.' });
            });
    };

    window.RT_eliminar = async function () {
        const id     = document.getElementById('rt-id').value;
        const nombre = document.getElementById('rt-nombre').value;
        if (!id) return;

        const conf = await Swal.fire({
            icon: 'warning',
            title: '¿Eliminar responsable?',
            html: '<strong>' + nombre + '</strong> dejará de estar disponible.',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });
        if (!conf.isConfirmed) return;

        const fd = new FormData();
        fd.append('id', id);

        fetch(urlBase + '/eliminar-ajax', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) {
                    // El caso habitual es "está en uso en N pedidos": mensaje largo,
                    // mejor en un diálogo normal que en un toast.
                    Swal.fire({ icon: 'error', title: 'No se puede eliminar', text: d.mensaje });
                    return;
                }
                RT_cerrarModal(function () {
                    window.RT_fetchSearch(1);
                    Swal.fire({
                        toast: true, position: 'top-end', icon: 'success',
                        title: d.mensaje || 'Eliminado', timer: 2500,
                        showConfirmButton: false, timerProgressBar: true
                    });
                });
            })
            .catch(function () {
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor.' });
            });
    };

    // ── Arranque ────────────────────────────────────────────────────────────

    function init() {
        const input = document.getElementById('buscarResponsable');
        if (input) {
            input.addEventListener('input', function () {
                clearTimeout(timerBuscar);
                timerBuscar = setTimeout(function () { window.RT_fetchSearch(1); }, 400);
            });
        }

        const campoNombre = document.getElementById('rt-nombre');
        if (campoNombre) {
            campoNombre.addEventListener('input', function () { this.value = this.value.toUpperCase(); });
        }
        const campoEmail = document.getElementById('rt-email');
        if (campoEmail) campoEmail.addEventListener('blur', RT_validarEmail);

        const btnVincular = document.getElementById('rt-btn-vincular');
        if (btnVincular) btnVincular.addEventListener('click', RT_vincularUsuario);

        // Motor de ordenamiento global: persiste __ordenCol__/__ordenDir__ por usuario.
        if (typeof window.CMG_initSort === 'function') {
            window.CMG_initSort('responsables-traslados', function (col, dir) {
                window.RT_currentSort = col;
                window.RT_currentDir  = dir;
                window.RT_fetchSearch(1);
            }, { col: window.RT_currentSort, dir: window.RT_currentDir });
        }

        RT_pintarIconosOrden();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(window, document);
