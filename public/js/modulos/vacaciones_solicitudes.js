/**
 * Cuadro de solicitudes de vacaciones (reutilizable).
 *
 * El empleado recibe por correo un enlace, llena el formulario público y su
 * solicitud queda pendiente; desde aquí se aprueba —lo que registra la vacación—
 * o se rechaza. Lo usan la pestaña Vacaciones del modal de Empleados (modo
 * 'empleado') y la bandeja del módulo Vacaciones (modo 'bandeja'); el marcado
 * sale de app/views/modulos/vacaciones/_solicitudes.php y los datos, de los
 * endpoints de modulos/vacaciones.
 *
 *   const cuadro = new CuadroSolicitudesVacaciones(raiz, { onCambio: () => {} });
 *   cuadro.cargarEmpleado(idEmpleado, { correo, nombre });   // modo 'empleado'
 *   cuadro.cargarBandeja();                                  // modo 'bandeja'
 *
 * onCambio se llama cada vez que algo cambia (se envió un enlace, se aprobó o se
 * rechazó una solicitud), para que la página refresque lo suyo: el saldo del
 * empleado, el listado de vacaciones, el contador de pendientes…
 */
(function (window, document) {
    'use strict';

    if (window.CuadroSolicitudesVacaciones) return; // la página ya lo cargó

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fecha = (ymd) => ymd ? `${String(ymd).substring(8, 10)}-${String(ymd).substring(5, 7)}-${String(ymd).substring(0, 4)}` : '—';
    const fechaHora = (v) => {
        if (!v) return '—';
        const d = new Date(String(v).replace(' ', 'T'));
        if (isNaN(d)) return fecha(v);
        const p = (n) => String(n).padStart(2, '0');
        return `${p(d.getDate())}-${p(d.getMonth() + 1)}-${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`;
    };
    const dias = (v) => {
        const n = Math.round((parseFloat(v) || 0) * 100) / 100;
        return n.toLocaleString('es-EC', { maximumFractionDigits: 2 });
    };

    const ESTADO = {
        enviada:   ['secondary', 'Enviada al empleado'],
        pendiente: ['warning',   'Esperando aprobación'],
        aprobada:  ['success',   'Aprobada'],
        rechazada: ['danger',    'Rechazada'],
        cancelada: ['secondary', 'Anulada'],
    };

    // El cuadro se usa en dos sitios: la página de Vacaciones (sin modal) y DENTRO del modal
    // de Empleados. Con un modal abierto, su focus trap (Bootstrap) le quita el foco a todo
    // lo que cuelga de <body> —donde va el popup—, y los campos de estos diálogos (correo,
    // comentario, motivo) no dejan escribir: por eso el popup se ancla al modal cuando lo hay.
    const swal = (opts) => {
        if (!window.Swal) return Promise.resolve({ isConfirmed: window.confirm(opts.title || '') });
        // El último abierto: si hay modales anidados, el de encima es el que atrapa el foco.
        const abiertos = document.querySelectorAll('.modal.show');
        const modalAbierto = abiertos[abiertos.length - 1] || null;
        return Swal.fire(modalAbierto ? Object.assign({ target: modalAbierto }, opts) : opts);
    };
    const aviso = (icon, title, text) => window.Swal
        ? Swal.fire({ icon, title, text, timer: icon === 'success' ? 1600 : undefined, showConfirmButton: icon !== 'success' })
        : alert(`${title}\n${text || ''}`);

    class CuadroSolicitudesVacaciones {
        constructor(raiz, opciones = {}) {
            this.raiz = raiz;
            this.url = raiz.dataset.url;
            this.modo = raiz.dataset.modo || 'empleado';
            this.puedeCrear = raiz.dataset.crear === '1';
            this.puedeActualizar = raiz.dataset.actualizar === '1';
            this.onCambio = typeof opciones.onCambio === 'function' ? opciones.onCambio : null;
            this.seq = 0;              // descarta respuestas que llegan tarde
            this.idEmpleado = null;
            this.empleado = {};
            this.filas = [];
            this.enlazar();
        }

        el(rol) {
            return this.raiz.querySelector(`[data-rol="${rol}"]`);
        }

        /** Modo 'empleado': solicitudes del empleado abierto en la ficha. */
        async cargarEmpleado(idEmpleado, datos = {}) {
            this.idEmpleado = parseInt(idEmpleado, 10) || null;
            this.empleado = datos || {};
            if (!this.idEmpleado) { this.limpiar(); return; }
            await this.pedir(`${this.url}/solicitudesEmpleadoAjax?id_empleado=${encodeURIComponent(this.idEmpleado)}`);
        }

        /** Modo 'bandeja': solicitudes de toda la empresa. */
        async cargarBandeja() {
            const estado = this.el('filtro-estado') ? this.el('filtro-estado').value : '';
            await this.pedir(`${this.url}/solicitudesBandejaAjax?estado=${encodeURIComponent(estado)}`);
        }

        recargar() {
            return this.modo === 'bandeja' ? this.cargarBandeja() : this.cargarEmpleado(this.idEmpleado, this.empleado);
        }

        async pedir(url) {
            const seq = ++this.seq;
            try {
                const resp = await fetch(url);
                const json = await resp.json();
                if (seq !== this.seq) return;
                if (!json.ok) { this.mostrarAviso(json.error || 'No se pudieron cargar las solicitudes.'); return; }
                if (json.disponible === false) {
                    this.filas = [];
                    this.pintar();
                    this.mostrarAviso('Las solicitudes de vacaciones todavía no están habilitadas en la base de datos. Avise al administrador del sistema.');
                    this.habilitarAcciones(false);
                    return;
                }
                this.habilitarAcciones(true);
                this.filas = json.data || [];
                this.pendientes = json.pendientes;
                this.pintar();
                this.mostrarAviso('');
            } catch (e) {
                if (seq === this.seq) this.mostrarAviso('No se pudo conectar con el servidor.');
            }
        }

        habilitarAcciones(on) {
            ['btn-enviar', 'btn-pdf-detalle'].forEach(rol => {
                const b = this.el(rol);
                if (b) b.disabled = !on;
            });
        }

        limpiar() {
            this.seq++;
            this.idEmpleado = null;
            this.filas = [];
            this.el('tbody').innerHTML = '';
            this.mostrarAviso('');
            const badge = this.el('badge-pendientes');
            if (badge) badge.classList.add('d-none');
        }

        mostrarAviso(msg) {
            const n = this.el('aviso');
            if (!n) return;
            n.textContent = msg || '';
            n.classList.toggle('d-none', !msg);
        }

        pintar() {
            const cols = this.modo === 'bandeja' ? 8 : 7;
            this.el('tbody').innerHTML = this.filas.length
                ? this.filas.map(s => this.fila(s)).join('')
                : `<tr><td colspan="${cols}" class="text-center text-muted py-3">`
                  + (this.modo === 'bandeja' ? 'No hay solicitudes.' : 'Este empleado no tiene solicitudes. Use «Enviar solicitud» para mandarle el enlace.')
                  + '</td></tr>';

            const badge = this.el('badge-pendientes');
            if (badge) {
                const n = this.pendientes;
                badge.textContent = n ? `${n} esperando aprobación` : '';
                badge.classList.toggle('d-none', !n);
            }
        }

        fila(s) {
            const [color, texto] = ESTADO[s.estado] || ['secondary', s.estado];
            let detalle = '';
            if (s.estado === 'enviada') {
                detalle = `Enviada a ${s.correo_destino || '—'}${s.expira_at ? `. El enlace caduca el ${fechaHora(s.expira_at)}` : ''}.`;
            } else if (s.resuelto_at) {
                detalle = `${texto} por ${s.resuelto_nombre || '—'} el ${fechaHora(s.resuelto_at)}.`
                    + (s.comentario ? ` ${s.comentario}` : '');
            }
            const badge = `<span class="badge bg-${color} bg-opacity-10 text-${color} border border-${color} border-opacity-25"`
                + (detalle ? ` title="${esc(detalle)}"` : '') + `>${esc(texto)}</span>`;

            const motivo = s.motivo ? `<span class="vac-sol-motivo d-inline-block" title="${esc(s.motivo)}">${esc(s.motivo)}</span>` : '—';
            const cuando = s.solicitado_at ? fechaHora(s.solicitado_at) : fechaHora(s.enviado_at);
            const empleado = this.modo === 'bandeja'
                ? `<td><span class="fw-medium">${esc(s.empleado_nombre)}</span><br><code class="text-secondary" style="font-size:.7rem;">${esc(s.empleado_identificacion)}</code></td>`
                : '';

            return `<tr data-id="${s.id}">
                ${empleado}
                <td>${badge}</td>
                <td>${fecha(s.fecha_desde)}</td>
                <td>${fecha(s.fecha_hasta)}</td>
                <td class="text-center">${s.dias_solicitados > 0 ? dias(s.dias_solicitados) : '—'}</td>
                <td>${motivo}</td>
                <td>${cuando}</td>
                <td class="text-end text-nowrap">${this.acciones(s)}</td>
            </tr>`;
        }

        acciones(s) {
            const b = (rol, icono, titulo, clase) =>
                `<button type="button" class="btn btn-outline-${clase} btn-sm border-0 px-1 py-0 vac-sol-accion" data-accion="${rol}" data-id="${s.id}" title="${titulo}"><i class="bi ${icono}"></i></button>`;

            if (s.estado === 'enviada') {
                return (s.url ? b('copiar', 'bi-clipboard', 'Copiar el enlace para enviárselo al empleado', 'secondary') : '')
                    + (this.puedeActualizar ? b('anular', 'bi-x-circle', 'Anular el enlace', 'danger') : '');
            }
            if (s.estado === 'pendiente') {
                return (this.puedeCrear ? b('aprobar', 'bi-check2-circle', 'Aprobar y registrar la vacación', 'success') : '')
                    + (this.puedeActualizar ? b('rechazar', 'bi-x-circle', 'Rechazar', 'danger') : '')
                    + b('pdf', 'bi-file-earmark-pdf', 'Imprimir la solicitud (PDF)', 'danger');
            }
            return b('pdf', 'bi-file-earmark-pdf', 'Imprimir la solicitud (PDF)', 'danger');
        }

        // ── Acciones ──────────────────────────────────────────────────────────

        async enviarInvitacion() {
            if (!this.idEmpleado) {
                aviso('info', 'Guarde el empleado primero', 'Debe guardar el empleado antes de enviarle la solicitud.');
                return;
            }
            const r = await swal({
                icon: 'question',
                title: 'Enviar solicitud de vacaciones',
                html: 'Se le enviará al empleado un enlace personal para que llene su solicitud.'
                    + '<br><small class="text-muted">El enlace sirve una sola vez y caduca a los 15 días.</small>',
                input: 'email',
                inputLabel: 'Correo del empleado',
                inputValue: this.empleado.correo || '',
                inputPlaceholder: 'correo@ejemplo.com',
                showCancelButton: true,
                confirmButtonText: 'Enviar',
                cancelButtonText: 'Cancelar',
                preConfirm: (v) => {
                    if (!v || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v.trim())) {
                        Swal.showValidationMessage('Escriba un correo válido.');
                        return false;
                    }
                    return v.trim();
                },
            });
            if (!r.isConfirmed) return;

            const fd = new FormData();
            fd.append('id_empleado', String(this.idEmpleado));
            fd.append('correo', r.value);
            const json = await this.postear('enviarSolicitudAjax', fd);
            if (!json) return;

            if (json.enviado) {
                aviso('success', 'Solicitud enviada', json.msg);
            } else {
                await swal({
                    icon: 'warning',
                    title: 'No se pudo enviar el correo',
                    html: `${esc(json.msg)}<br><br><small class="text-break">${esc(json.url || '')}</small>`,
                    confirmButtonText: 'Copiar enlace',
                }).then(() => this.copiarTexto(json.url));
            }
            await this.recargar();
            if (this.onCambio) this.onCambio();
        }

        async aprobar(id) {
            const r = await swal({
                icon: 'question',
                title: '¿Aprobar la solicitud?',
                html: 'Se registrará la vacación del empleado con esas fechas y sus días, y el valor se calculará con su sueldo.',
                input: 'text',
                inputLabel: 'Comentario (opcional)',
                inputAttributes: { maxlength: 500 },
                showCancelButton: true,
                confirmButtonText: 'Sí, aprobar',
                cancelButtonText: 'Cancelar',
                footer: '<label class="small"><input type="checkbox" id="vacSolNotificar" checked> Avisar al empleado por correo</label>',
            });
            if (!r.isConfirmed) return;

            const fd = new FormData();
            fd.append('id', String(id));
            fd.append('comentario', r.value || '');
            if (this.notificarMarcado()) fd.append('notificar', '1');
            const json = await this.postear('aprobarSolicitudAjax', fd);
            if (!json) return;
            aviso('success', 'Solicitud aprobada', json.msg);
            await this.recargar();
            if (this.onCambio) this.onCambio();
        }

        async rechazar(id) {
            const r = await swal({
                icon: 'warning',
                title: '¿Rechazar la solicitud?',
                input: 'text',
                inputLabel: 'Motivo del rechazo',
                inputAttributes: { maxlength: 500 },
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, rechazar',
                cancelButtonText: 'Cancelar',
                footer: '<label class="small"><input type="checkbox" id="vacSolNotificar" checked> Avisar al empleado por correo</label>',
                preConfirm: (v) => {
                    if (!v || !v.trim()) {
                        Swal.showValidationMessage('Escriba el motivo: el empleado debe saber por qué.');
                        return false;
                    }
                    return v.trim();
                },
            });
            if (!r.isConfirmed) return;

            const fd = new FormData();
            fd.append('id', String(id));
            fd.append('motivo', r.value || '');
            if (this.notificarMarcado()) fd.append('notificar', '1');
            const json = await this.postear('rechazarSolicitudAjax', fd);
            if (!json) return;
            aviso('success', 'Solicitud rechazada', json.msg);
            await this.recargar();
            if (this.onCambio) this.onCambio();
        }

        async anular(id) {
            const r = await swal({
                icon: 'warning',
                title: '¿Anular el enlace?',
                text: 'El empleado ya no podrá usarlo. Puede enviarle uno nuevo cuando quiera.',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, anular',
                cancelButtonText: 'Cancelar',
            });
            if (!r.isConfirmed) return;

            const fd = new FormData();
            fd.append('id', String(id));
            const json = await this.postear('cancelarSolicitudAjax', fd);
            if (!json) return;
            aviso('success', 'Enlace anulado', json.msg);
            await this.recargar();
        }

        notificarMarcado() {
            const chk = document.getElementById('vacSolNotificar');
            return !chk || chk.checked;
        }

        async postear(accion, fd) {
            try {
                const resp = await fetch(`${this.url}/${accion}`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (!json.ok) { aviso('error', 'Atención', json.error || 'No se pudo completar la acción.'); return null; }
                return json;
            } catch (e) {
                aviso('error', 'Error de red', 'No se pudo conectar con el servidor.');
                return null;
            }
        }

        copiarTexto(texto) {
            if (!texto) return;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(texto).then(() => aviso('success', 'Enlace copiado', ''));
            } else {
                window.prompt('Copie el enlace:', texto);
            }
        }

        // Solo se usa para PDFs (solicitud y detalle): pregunta Imprimir / Descargar / Ver.
        descargar(url) {
            CMG_pdfDocumento(url);
        }

        enlazar() {
            this.el('tbody').addEventListener('click', (ev) => {
                const btn = ev.target.closest('.vac-sol-accion');
                if (!btn) return;
                const id = parseInt(btn.dataset.id, 10);
                const s = this.filas.find(f => f.id === id);
                switch (btn.dataset.accion) {
                    case 'aprobar':  this.aprobar(id); break;
                    case 'rechazar': this.rechazar(id); break;
                    case 'anular':   this.anular(id); break;
                    case 'copiar':   this.copiarTexto(s && s.url); break;
                    case 'pdf':      this.descargar(`${this.url}/solicitudPdf?id=${id}&_=${Date.now()}`); break;
                }
            });

            this.el('btn-recargar')?.addEventListener('click', () => this.recargar());
            this.el('filtro-estado')?.addEventListener('change', () => this.cargarBandeja());
            this.el('btn-enviar')?.addEventListener('click', () => this.enviarInvitacion());
            this.el('btn-pdf-detalle')?.addEventListener('click', () => {
                if (!this.idEmpleado) {
                    aviso('info', 'Guarde el empleado primero', 'Debe guardar el empleado antes de imprimir su detalle de vacaciones.');
                    return;
                }
                this.descargar(`${this.url}/detalleEmpleadoPdf?id_empleado=${this.idEmpleado}&_=${Date.now()}`);
            });
        }
    }

    window.CuadroSolicitudesVacaciones = CuadroSolicitudesVacaciones;
})(window, document);
