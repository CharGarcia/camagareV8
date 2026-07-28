/**
 * Módulo Videollamadas — listado, modal de sala y apertura de la ventana aparte.
 *
 * El token CSRF lo adjunta public/js/csrf.js envolviendo fetch: aquí no hay que
 * hacer nada al respecto.
 */
(function () {
    'use strict';

    const VC_URL    = window.VC_URL_BASE;
    const USUARIOS  = window.VC_USUARIOS || [];
    const MAX_MESH  = window.VC_MAX_MESH || 8;
    const PERM      = window.VC_PERM || {};
    const ID_USUARIO = window.VC_ID_USUARIO || 0;

    let salaActual = null;   // sala cargada en el modal (null = alta nueva)
    let filaParticipante = 0; // contador para ids únicos de las filas

    window.currentSort = window.currentSort || window.VC_ORDEN_COL || 'fecha_inicio';
    window.currentDir  = window.currentDir  || window.VC_ORDEN_DIR || 'DESC';

    function avisar(icono, titulo, texto) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: icono, title: titulo, text: texto, confirmButtonColor: '#0d6efd' });
        } else {
            alert(titulo + (texto ? '\n\n' + texto : ''));
        }
    }

    function avisarOk(texto) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'success', title: texto, timer: 1400, showConfirmButton: false });
        }
    }

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    // ── Listado / búsqueda / paginación ──────────────────────────────────────

    window.VC_buscar = function (p = 1) {
        window.VC_fetchSearch(p);
    };

    window.VC_fetchSearch = async function (p = 1) {
        const b = document.getElementById('txtBuscarVC')?.value || '';
        const tbody = document.getElementById('tbodyVideollamadas');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5"><span class="spinner-border text-primary"></span></td></tr>';
        }
        try {
            const res = await (await fetch(
                `${VC_URL}/searchAjax?b=${encodeURIComponent(b)}&page=${p}&sort=${window.currentSort}&dir=${window.currentDir}`
            )).json();

            if (tbody) tbody.innerHTML = res.rows;
            const pag = document.getElementById('paginationContainer');
            if (pag) pag.innerHTML = res.pagination;
            const info = document.getElementById('paginationInfo');
            if (info) info.innerText = res.info;

            document.querySelectorAll('.sortable-header').forEach(th => {
                const icon = th.querySelector('i');
                if (!icon) return;
                if (th.dataset.col === window.currentSort) {
                    icon.className = (window.currentDir.toLowerCase() === 'asc')
                        ? 'bi bi-sort-alpha-down text-primary ms-1'
                        : 'bi bi-sort-alpha-up text-primary ms-1';
                } else {
                    icon.className = 'bi bi-arrow-down-up small text-muted ms-1';
                }
            });
        } catch (e) {
            console.error(e);
        }
    };

    window.VC_cambiarPaginaAjax = function (p) {
        window.VC_fetchSearch(p);
    };

    window.VC_sort = function (col) {
        if (window.currentSort === col) {
            window.currentDir = (window.currentDir.toUpperCase() === 'ASC') ? 'DESC' : 'ASC';
        } else {
            window.currentSort = col;
            window.currentDir = 'ASC';
        }
        if (navigator.sendBeacon && typeof APP_VISTAS_URL !== 'undefined') {
            const fd = new FormData();
            fd.append('modulo', 'videollamadas');
            fd.append('vistaPayload', JSON.stringify({ __ordenCol__: window.currentSort, __ordenDir__: window.currentDir }));
            // sendBeacon no pasa por el interceptor de csrf.js: el token va a mano.
            fd.append('csrf_token', window.CSRF_TOKEN || '');
            navigator.sendBeacon(APP_VISTAS_URL, fd);
        }
        window.VC_fetchSearch(1);
    };

    document.getElementById('txtBuscarVC')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); window.VC_buscar(1); }
    });

    // ── Modal ────────────────────────────────────────────────────────────────

    function modal() {
        return bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSalaVC'));
    }

    function limpiarFormulario() {
        document.getElementById('vcForm').reset();
        document.getElementById('vc-id').value = '';
        document.getElementById('vc-max').value = 6;
        document.getElementById('vc-max').max = MAX_MESH;
        document.getElementById('vc-duracion').value = 60;
        document.getElementById('vc-sala-espera').checked = true;
        document.getElementById('vcTbodyParticipantes').innerHTML = '';
        filaParticipante = 0;

        // El creador es anfitrión por defecto.
        const selAnfitrion = document.getElementById('vc-anfitrion');
        if (selAnfitrion && ID_USUARIO) selAnfitrion.value = String(ID_USUARIO);

        ['vcBtnEntrar', 'vcBtnCopiar', 'vcBtnInvitar', 'vcBtnFinalizar', 'vcBtnEliminar', 'vcSepAcciones'].forEach(id => {
            document.getElementById(id)?.classList.add('d-none');
        });
        document.getElementById('vcCodigoSala')?.classList.add('d-none');

        document.getElementById('vc-info-codigo').textContent = '—';
        document.getElementById('vc-info-estado').textContent = '—';
        document.getElementById('vc-info-creador').textContent = '—';
        document.getElementById('vc-info-creada').textContent = '—';
        document.getElementById('vc-info-enlace').value = '';

        window.VC_onCambioTipo();
        window.VC_avisoCapacidad();
        actualizarContador();
    }

    function setSoloLectura(bloqueado) {
        const campos = ['vc-titulo', 'vc-tipo', 'vc-fecha-inicio', 'vc-duracion', 'vc-anfitrion',
                        'vc-max', 'vc-descripcion', 'vc-sala-espera', 'vc-invitados', 'vc-grabar'];
        campos.forEach(id => { const el = document.getElementById(id); if (el) el.disabled = bloqueado; });

        document.getElementById('vcBtnGuardar')?.classList.toggle('d-none', bloqueado);
        document.getElementById('vcBtnAgregarInvitado')?.classList.toggle('disabled', bloqueado);
    }

    window.VC_abrirModalNuevo = function () {
        salaActual = null;
        limpiarFormulario();
        setSoloLectura(false);
        document.getElementById('vcModalTitulo').textContent = 'Nueva reunión';
        modal().show();
    };

    window.VC_abrirModalVer = async function (id) {
        limpiarFormulario();
        try {
            const res = await (await fetch(`${VC_URL}/getSalaAjax?id=${id}`)).json();
            if (!res.ok) { avisar('error', 'No se pudo abrir', res.mensaje); return; }

            salaActual = res.data;
            pintarSala(res.data);
            modal().show();
        } catch (e) {
            avisar('error', 'Error de conexión', 'No se pudo cargar la reunión.');
        }
    };

    function pintarSala(s) {
        document.getElementById('vcModalTitulo').textContent = s.titulo;
        document.getElementById('vc-id').value = s.id;
        document.getElementById('vc-titulo').value = s.titulo || '';
        document.getElementById('vc-tipo').value = s.tipo || 'instantanea';
        document.getElementById('vc-descripcion').value = s.descripcion || '';
        document.getElementById('vc-duracion').value = s.duracion_minutos || 0;
        document.getElementById('vc-max').value = s.max_participantes || 6;
        document.getElementById('vc-anfitrion').value = String(s.id_anfitrion || '');
        document.getElementById('vc-sala-espera').checked = !!s.sala_espera;
        document.getElementById('vc-invitados').checked = !!s.permite_invitados;
        document.getElementById('vc-grabar').checked = !!s.grabar;

        if (s.fecha_inicio) {
            // "2026-07-28 15:30:00" → "2026-07-28T15:30" (formato de datetime-local)
            document.getElementById('vc-fecha-inicio').value = String(s.fecha_inicio).replace(' ', 'T').slice(0, 16);
        }

        (s.participantes || []).forEach(p => agregarFilaParticipante(p));

        const enlace = `${VC_URL}/sala`;
        document.getElementById('vc-info-codigo').textContent = s.codigo || '—';
        document.getElementById('vc-info-estado').innerHTML = badgeEstado(s.estado);
        document.getElementById('vc-info-creador').textContent = s.creador_nombre || '—';
        document.getElementById('vc-info-creada').textContent = fmtFecha(s.created_at);
        document.getElementById('vc-info-enlace').value = enlace;

        const codigo = document.getElementById('vcCodigoSala');
        codigo.textContent = s.codigo || '';
        codigo.classList.remove('d-none');

        const finalizada = (s.estado === 'finalizada' || s.estado === 'cancelada');

        document.getElementById('vcBtnCopiar')?.classList.remove('d-none');
        if (!finalizada) {
            document.getElementById('vcBtnEntrar')?.classList.remove('d-none');
            document.getElementById('vcBtnInvitar')?.classList.remove('d-none');
        }
        if (s.estado === 'en_curso' && PERM.actualizar) {
            document.getElementById('vcSepAcciones')?.classList.remove('d-none');
            document.getElementById('vcBtnFinalizar')?.classList.remove('d-none');
        }
        if (PERM.eliminar && s.estado !== 'en_curso') {
            document.getElementById('vcBtnEliminar')?.classList.remove('d-none');
        }

        setSoloLectura(finalizada || !PERM.actualizar);
        window.VC_onCambioTipo();
        window.VC_avisoCapacidad();
        actualizarContador();
    }

    function badgeEstado(estado) {
        const mapa = {
            en_curso:   ['bg-success', 'En curso'],
            finalizada: ['bg-secondary', 'Finalizada'],
            cancelada:  ['bg-danger', 'Cancelada'],
            programada: ['bg-primary', 'Programada'],
        };
        const [cls, txt] = mapa[estado] || mapa.programada;
        return `<span class="badge ${cls} bg-opacity-10 text-${cls.replace('bg-', '')} border border-${cls.replace('bg-', '')} border-opacity-25">${txt}</span>`;
    }

    function fmtFecha(v) {
        if (!v) return '—';
        const d = new Date(String(v).replace(' ', 'T'));
        if (isNaN(d)) return '—';
        const p = (n) => String(n).padStart(2, '0');
        return `${p(d.getDate())}-${p(d.getMonth() + 1)}-${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
    }

    // ── Participantes ────────────────────────────────────────────────────────

    window.VC_agregarParticipante = function (tipo) {
        if (tipo === 'invitado' && !document.getElementById('vc-invitados').checked) {
            avisar('warning', 'Invitados externos desactivados',
                   'Active "Permitir invitados externos" en la pestaña General para agregar personas sin cuenta.');
            return;
        }
        const max = parseInt(document.getElementById('vc-max').value, 10) || 6;
        if (document.querySelectorAll('#vcTbodyParticipantes tr').length >= max) {
            avisar('warning', 'Cupo completo', `La sala está configurada para ${max} participantes.`);
            return;
        }
        agregarFilaParticipante(tipo === 'invitado' ? { nombre_invitado: '' } : { id_usuario: '' });
    };

    function agregarFilaParticipante(p) {
        const i = ++filaParticipante;
        const esInvitado = !p.id_usuario;
        const nombre = p.usuario_nombre || p.nombre_invitado || '';
        const email = p.email || p.usuario_email || '';
        const rol = p.rol || 'participante';

        const celdaIdentidad = esInvitado
            ? `<input type="text" class="form-control form-control-sm vc-p-nombre" placeholder="Nombre del invitado"
                      value="${esc(nombre)}" style="padding:0 4px;height:20px;font-size:0.78rem;">`
            : `<select class="form-select form-select-sm vc-p-usuario" style="padding:0 4px;height:20px;font-size:0.78rem;">
                 <option value="">Seleccione...</option>
                 ${USUARIOS.map(u => `<option value="${u.id}" ${String(u.id) === String(p.id_usuario) ? 'selected' : ''}>${esc(u.nombre)}</option>`).join('')}
               </select>`;

        const tr = document.createElement('tr');
        tr.dataset.fila = i;
        tr.innerHTML = `
            <td class="p-0">${celdaIdentidad}</td>
            <td class="p-0">
                <input type="email" class="form-control form-control-sm vc-p-email" placeholder="correo@ejemplo.com"
                       value="${esc(email)}" style="padding:0 4px;height:20px;font-size:0.78rem;">
            </td>
            <td class="p-0">
                <select class="form-select form-select-sm vc-p-rol" style="padding:0 4px;height:20px;font-size:0.78rem;">
                    <option value="participante" ${rol === 'participante' ? 'selected' : ''}>Participante</option>
                    <option value="moderador" ${rol === 'moderador' ? 'selected' : ''}>Moderador</option>
                    <option value="anfitrion" ${rol === 'anfitrion' ? 'selected' : ''}>Anfitrión</option>
                </select>
            </td>
            <td class="p-0 text-center">
                <button type="button" class="btn btn-link btn-sm text-danger p-0" onclick="VC_quitarParticipante(${i})" title="Quitar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>`;

        document.getElementById('vcTbodyParticipantes').appendChild(tr);
        actualizarContador();
    }

    window.VC_quitarParticipante = function (i) {
        document.querySelector(`#vcTbodyParticipantes tr[data-fila="${i}"]`)?.remove();
        actualizarContador();
    };

    function actualizarContador() {
        const n = document.querySelectorAll('#vcTbodyParticipantes tr').length;
        const max = parseInt(document.getElementById('vc-max')?.value, 10) || 6;
        const el = document.getElementById('vc-contador-participantes');
        if (el) el.textContent = `${n} de ${max} participantes.`;
    }

    function recogerParticipantes() {
        const filas = document.querySelectorAll('#vcTbodyParticipantes tr');
        const out = [];
        filas.forEach(tr => {
            const selUsuario = tr.querySelector('.vc-p-usuario');
            const inpNombre  = tr.querySelector('.vc-p-nombre');
            out.push({
                id_usuario:      selUsuario ? parseInt(selUsuario.value, 10) || 0 : 0,
                nombre_invitado: inpNombre ? inpNombre.value.trim() : '',
                email:           tr.querySelector('.vc-p-email')?.value.trim() || '',
                rol:             tr.querySelector('.vc-p-rol')?.value || 'participante',
            });
        });
        return out;
    }

    // ── Campos dependientes ──────────────────────────────────────────────────

    window.VC_onCambioTipo = function () {
        const tipo = document.getElementById('vc-tipo')?.value;
        document.getElementById('vc-wrap-fecha')?.classList.toggle('d-none', tipo !== 'programada');
    };

    window.VC_avisoCapacidad = function () {
        const max = parseInt(document.getElementById('vc-max')?.value, 10) || 6;
        const aviso = document.getElementById('vc-aviso-capacidad');
        if (!aviso) return;

        if (max > MAX_MESH) {
            aviso.className = 'form-text small text-danger';
            aviso.textContent = `El motor interno admite hasta ${MAX_MESH} participantes.`;
        } else if (max > 6) {
            aviso.className = 'form-text small text-warning';
            aviso.textContent = 'Con más de 6 participantes la calidad se degrada en conexiones lentas.';
        } else {
            aviso.className = 'form-text small text-muted';
            aviso.textContent = `El motor interno admite hasta ${MAX_MESH} participantes.`;
        }
        actualizarContador();
    };

    document.getElementById('vc-grabar')?.addEventListener('change', function () {
        document.getElementById('vc-aviso-grabacion')?.classList.toggle('d-none', !this.checked);
    });

    // ── Acciones ─────────────────────────────────────────────────────────────

    window.VC_guardar = async function () {
        const titulo = document.getElementById('vc-titulo').value.trim();
        if (!titulo) { avisar('warning', 'Falta el título', 'Escriba un título para la reunión.'); return; }

        const fd = new FormData();
        fd.append('id', document.getElementById('vc-id').value || '0');
        fd.append('titulo', titulo);
        fd.append('descripcion', document.getElementById('vc-descripcion').value.trim());
        fd.append('tipo', document.getElementById('vc-tipo').value);
        fd.append('fecha_inicio', document.getElementById('vc-fecha-inicio').value || '');
        fd.append('duracion_minutos', document.getElementById('vc-duracion').value || '0');
        fd.append('id_anfitrion', document.getElementById('vc-anfitrion').value || '0');
        fd.append('max_participantes', document.getElementById('vc-max').value || '6');
        if (document.getElementById('vc-sala-espera').checked) fd.append('sala_espera', '1');
        if (document.getElementById('vc-invitados').checked)   fd.append('permite_invitados', '1');
        if (document.getElementById('vc-grabar').checked)      fd.append('grabar', '1');
        fd.append('participantes', JSON.stringify(recogerParticipantes()));

        const btn = document.getElementById('vcBtnGuardar');
        btn.disabled = true;

        try {
            const res = await (await fetch(`${VC_URL}/guardarAjax`, { method: 'POST', body: fd })).json();
            if (!res.ok) { avisar('error', 'No se pudo guardar', res.mensaje); return; }

            avisarOk(res.mensaje);
            modal().hide();
            window.VC_fetchSearch(window.VC_PAGE || 1);
        } catch (e) {
            avisar('error', 'Error de conexión', 'No se pudo guardar la reunión.');
        } finally {
            btn.disabled = false;
        }
    };

    window.VC_eliminar = async function () {
        const id = document.getElementById('vc-id').value;
        if (!id) return;

        if (typeof Swal !== 'undefined') {
            const r = await Swal.fire({
                icon: 'warning',
                title: '¿Eliminar la reunión?',
                text: 'Se conserva en el historial del sistema, pero deja de estar disponible.',
                showCancelButton: true,
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
            });
            if (!r.isConfirmed) return;
        } else if (!confirm('¿Eliminar la reunión?')) {
            return;
        }

        const fd = new FormData();
        fd.append('id', id);

        try {
            const res = await (await fetch(`${VC_URL}/eliminarAjax`, { method: 'POST', body: fd })).json();
            if (!res.ok) { avisar('error', 'No se pudo eliminar', res.mensaje); return; }
            avisarOk(res.mensaje);
            modal().hide();
            window.VC_fetchSearch(1);
        } catch (e) {
            avisar('error', 'Error de conexión', 'No se pudo eliminar la reunión.');
        }
    };

    /** Marca la sala como en curso y la abre en ventana aparte (como el visor de ayuda). */
    window.VC_entrarSala = async function () {
        const id = document.getElementById('vc-id').value;
        if (!id) return;

        const fd = new FormData();
        fd.append('id', id);

        try {
            const res = await (await fetch(`${VC_URL}/iniciarAjax`, { method: 'POST', body: fd })).json();
            if (!res.ok) { avisar('error', 'No se pudo entrar', res.mensaje); return; }

            window.open(res.url, 'cmgSalaVideollamada', 'width=1280,height=800,menubar=no,toolbar=no');
            modal().hide();
            window.VC_fetchSearch(window.VC_PAGE || 1);
        } catch (e) {
            avisar('error', 'Error de conexión', 'No se pudo abrir la sala.');
        }
    };

    /** Envía la invitación por correo. Cada participante recibe su propio enlace. */
    window.VC_enviarInvitaciones = async function () {
        const id = document.getElementById('vc-id').value;
        if (!id) return;

        if (typeof Swal !== 'undefined') {
            const r = await Swal.fire({
                icon: 'question',
                title: '¿Enviar las invitaciones?',
                text: 'Cada participante con correo recibirá el enlace de la reunión.',
                showCancelButton: true,
                confirmButtonText: 'Enviar',
                cancelButtonText: 'Cancelar',
                target: document.getElementById('modalSalaVC'),
            });
            if (!r.isConfirmed) return;
        }

        const btn = document.getElementById('vcBtnInvitar');
        btn.disabled = true;

        const fd = new FormData();
        fd.append('id', id);

        try {
            const res = await (await fetch(`${VC_URL}/enviarInvitacionesAjax`, { method: 'POST', body: fd })).json();
            if (res.ok) {
                avisarOk(res.mensaje);
            } else {
                avisar('warning', 'Invitaciones', res.mensaje);
            }
        } catch (e) {
            avisar('error', 'Error de conexión', 'No se pudieron enviar las invitaciones.');
        } finally {
            btn.disabled = false;
        }
    };

    window.VC_finalizarSala = async function () {
        const id = document.getElementById('vc-id').value;
        if (!id) return;

        const fd = new FormData();
        fd.append('id', id);

        try {
            const res = await (await fetch(`${VC_URL}/finalizarAjax`, { method: 'POST', body: fd })).json();
            if (!res.ok) { avisar('error', 'No se pudo finalizar', res.mensaje); return; }
            avisarOk(res.mensaje);
            modal().hide();
            window.VC_fetchSearch(window.VC_PAGE || 1);
        } catch (e) {
            avisar('error', 'Error de conexión', 'No se pudo finalizar la reunión.');
        }
    };


    window.VC_copiarEnlace = function () {
        const enlace = document.getElementById('vc-info-enlace')?.value || `${VC_URL}/sala`;
        navigator.clipboard.writeText(enlace)
            .then(() => avisarOk('Enlace copiado'))
            .catch(() => avisar('info', 'Enlace de la reunión', enlace));
    };

})();
