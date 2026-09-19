/**
 * Vacaciones registradas de un empleado, dentro de su ficha (pestaña Vacaciones).
 *
 * Lista lo que el empleado ya tiene registrado y abre el mismo formulario del
 * módulo Vacaciones —con el empleado ya fijado— para registrar una nueva, editarla
 * o eliminarla. El marcado sale de app/views/modulos/vacaciones/_vacaciones_empleado.php
 * y los datos, de los endpoints de modulos/vacaciones.
 *
 *   const vacs = new CuadroVacacionesEmpleado(raiz, { onCambio: () => {} });
 *   vacs.cargar(idEmpleado, { nombre, sueldo, saldo });
 *
 * onCambio se llama tras guardar o eliminar, para que la ficha refresque el cuadro
 * de períodos (saldo, días gozados).
 */
(function (window, document) {
    'use strict';

    if (window.CuadroVacacionesEmpleado) return; // la página ya lo cargó

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fecha = (ymd) => ymd ? `${String(ymd).substring(8, 10)}-${String(ymd).substring(5, 7)}-${String(ymd).substring(0, 4)}` : '—';
    const money = (v) => '$' + (Math.round((parseFloat(v) || 0) * 100) / 100).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const dias = (v) => (Math.round((parseFloat(v) || 0) * 100) / 100).toLocaleString('es-EC', { maximumFractionDigits: 2 });

    const ESTADO = {
        registrado: ['info',    'Registrado'],
        pagado:     ['success', 'Pagado'],
        anulado:    ['danger',  'Anulado'],
    };

    const aviso = (icon, title, text) => window.Swal
        ? Swal.fire({ icon, title, text, timer: icon === 'success' ? 1500 : undefined, showConfirmButton: icon !== 'success' })
        : alert(`${title}\n${text || ''}`);

    class CuadroVacacionesEmpleado {
        constructor(raiz, opciones = {}) {
            this.raiz = raiz;
            this.url = raiz.dataset.url;
            this.puedeCrear = raiz.dataset.crear === '1';
            this.puedeActualizar = raiz.dataset.actualizar === '1';
            this.puedeEliminar = raiz.dataset.eliminar === '1';
            this.modalEl = document.getElementById(raiz.dataset.modal);
            this.onCambio = typeof opciones.onCambio === 'function' ? opciones.onCambio : null;

            // Salvaguarda: este modal debe quedar fuera de cualquier otro modal. Dentro
            // de uno hereda su contexto de apilamiento —app.css fuerza
            // .modal { z-index:5060 !important }— y su propio backdrop lo tapa: se ve
            // detrás, atenuado y sin poder cerrarlo. La vista ya lo declara como
            // hermano; esto lo lleva al <body> por si alguien lo incluye anidado.
            if (this.modalEl && this.modalEl.parentElement !== document.body) {
                document.body.appendChild(this.modalEl);
            }
            this.seq = 0;
            this.idEmpleado = null;
            this.empleado = {};
            this.filas = [];
            this.meses = {};
            this.enlazar();
        }

        el(rol) { return this.raiz.querySelector(`[data-rol="${rol}"]`); }
        m(rol)  { return this.modalEl ? this.modalEl.querySelector(`[data-rol="${rol}"]`) : null; }

        /** Carga las vacaciones del empleado. `datos`: nombre, sueldo y saldo (del cuadro de períodos). */
        async cargar(idEmpleado, datos = {}) {
            this.idEmpleado = parseInt(idEmpleado, 10) || null;
            if (datos && Object.keys(datos).length) this.empleado = datos;
            if (!this.idEmpleado) { this.limpiar(); return; }

            const seq = ++this.seq;
            try {
                const resp = await fetch(`${this.url}/vacacionesEmpleadoAjax?id_empleado=${encodeURIComponent(this.idEmpleado)}`);
                const json = await resp.json();
                if (seq !== this.seq) return;
                if (!json.ok) { this.mostrarAviso(json.error || 'No se pudieron cargar las vacaciones.'); return; }
                this.filas = json.data || [];
                this.meses = json.meses || {};
                this.pintar();
                this.mostrarAviso('');
            } catch (e) {
                if (seq === this.seq) this.mostrarAviso('No se pudo conectar con el servidor.');
            }
        }

        recargar() { return this.cargar(this.idEmpleado); }

        limpiar() {
            this.seq++;
            this.idEmpleado = null;
            this.filas = [];
            this.el('tbody').innerHTML = '';
            this.el('badge-total').classList.add('d-none');
            this.mostrarAviso('');
        }

        mostrarAviso(msg) {
            const n = this.el('aviso');
            if (!n) return;
            n.textContent = msg || '';
            n.classList.toggle('d-none', !msg);
        }

        pintar() {
            this.el('tbody').innerHTML = this.filas.length
                ? this.filas.map(v => this.fila(v)).join('')
                : '<tr><td colspan="8" class="text-center text-muted py-3">Este empleado no tiene vacaciones registradas.</td></tr>';

            // Totales sin las anuladas (las mismas que cuentan para el saldo).
            const vivas = this.filas.filter(v => v.estado !== 'anulado');
            const d = vivas.reduce((s, v) => s + (parseFloat(v.dias_gozados) || 0), 0);
            const t = vivas.reduce((s, v) => s + (parseFloat(v.valor) || 0), 0);
            const badge = this.el('badge-total');
            badge.textContent = vivas.length ? `${vivas.length} · ${dias(d)} día(s) · ${money(t)}` : '';
            badge.classList.toggle('d-none', !vivas.length);
        }

        fila(v) {
            const [color, texto] = ESTADO[v.estado] || ['secondary', v.estado];
            const mes = this.meses[v.periodo_mes] ? `${this.meses[v.periodo_mes]} ${v.periodo_anio}` : (v.periodo_anio || '—');
            const obs = v.observacion
                ? `<span class="vac-reg-obs d-inline-block" title="${esc(v.observacion)}">${esc(v.observacion)}</span>`
                : '—';
            const editable = this.puedeActualizar;
            const lapiz = editable
                ? `<button type="button" class="btn btn-outline-primary btn-sm border-0 px-1 py-0 vac-reg-editar" data-id="${v.id}" title="Abrir"><i class="bi bi-pencil"></i></button>`
                : '';
            const rol = v.afecta_rol
                ? ''
                : ' <i class="bi bi-slash-circle text-muted" title="No se incluye en el rol de pagos"></i>';

            return `<tr class="${editable ? 'vac-reg-fila' : ''}" data-id="${v.id}">
                <td>${fecha(v.fecha_desde)}</td>
                <td>${fecha(v.fecha_hasta)}</td>
                <td class="text-center">${dias(v.dias_gozados)}</td>
                <td class="text-end fw-bold">${money(v.valor)}${rol}</td>
                <td>${esc(mes)}</td>
                <td class="text-center"><span class="badge bg-${color} bg-opacity-10 text-${color} border border-${color} border-opacity-25">${esc(texto)}</span></td>
                <td>${obs}</td>
                <td class="text-end text-nowrap">${lapiz}</td>
            </tr>`;
        }

        // ── Modal ─────────────────────────────────────────────────────────────

        abrirNueva() {
            if (!this.idEmpleado) {
                aviso('info', 'Guarde el empleado primero', 'Debe guardar el empleado antes de registrarle vacaciones.');
                return;
            }
            const hoy = new Date().toISOString().substring(0, 10);
            this.m('titulo').textContent = 'Registrar vacación';
            this.m('f-id').value = '';
            this.m('f-desde').value = hoy;
            this.m('f-hasta').value = hoy;
            this.m('f-dias').value = 0;
            this.m('f-mes').value = String(parseInt(hoy.substring(5, 7), 10));
            this.m('f-anio').value = hoy.substring(0, 4);
            this.m('f-afecta').checked = true;
            this.m('f-observacion').value = '';
            // Al crear, el estado siempre es "registrado" (igual que en el módulo).
            const est = this.m('f-estado');
            est.value = 'registrado';
            est.disabled = true;
            this.m('btn-eliminar')?.classList.add('d-none');
            this.pintarResumen();
            this.recalcularValor();
            this.mostrarModal();
        }

        abrirEditar(id) {
            const v = this.filas.find(f => f.id === id);
            if (!v || !this.puedeActualizar) return;
            this.m('titulo').textContent = 'Editar vacación';
            this.m('f-id').value = String(v.id);
            this.m('f-desde').value = v.fecha_desde || '';
            this.m('f-hasta').value = v.fecha_hasta || '';
            this.m('f-dias').value = v.dias_gozados;
            this.m('f-mes').value = String(v.periodo_mes || '');
            this.m('f-anio').value = v.periodo_anio || '';
            this.m('f-afecta').checked = !!v.afecta_rol;
            this.m('f-observacion').value = v.observacion || '';
            const est = this.m('f-estado');
            est.value = v.estado || 'registrado';
            est.disabled = false;
            this.m('btn-eliminar')?.classList.toggle('d-none', !this.puedeEliminar);
            this.pintarResumen();
            this.recalcularValor();
            this.mostrarModal();
        }

        pintarResumen() {
            this.m('res-empleado').textContent = this.empleado.nombre || '—';
            this.m('res-saldo').textContent = this.empleado.saldo != null ? `${dias(this.empleado.saldo)} día(s)` : '—';
            this.m('res-sueldo').textContent = this.empleado.sueldo != null ? money(this.empleado.sueldo) : '—';
        }

        mostrarModal() {
            if (!this.modalEl || !window.bootstrap) return;
            window.bootstrap.Modal.getOrCreateInstance(this.modalEl).show();
        }

        recalcularValor() {
            const d = parseFloat(this.m('f-dias').value) || 0;
            const sueldo = parseFloat(this.empleado.sueldo) || 0;
            this.m('f-valor').value = money((sueldo / 30) * d);
        }

        async guardar() {
            const id = this.m('f-id').value;
            const btn = this.m('btn-guardar');
            const fd = new FormData();
            fd.append('id_empleado', String(this.idEmpleado));
            fd.append('fecha_desde', this.m('f-desde').value);
            fd.append('fecha_hasta', this.m('f-hasta').value);
            fd.append('dias_gozados', this.m('f-dias').value);
            fd.append('periodo_mes', this.m('f-mes').value);
            fd.append('periodo_anio', this.m('f-anio').value);
            fd.append('observacion', this.m('f-observacion').value);
            fd.append('estado', this.m('f-estado').value);
            if (this.m('f-afecta').checked) fd.append('afecta_rol', '1');
            if (id) fd.append('id', id);

            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
            try {
                const resp = await fetch(`${this.url}/${id ? 'update' : 'store'}`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (!json.ok) { aviso('error', 'Atención', json.error || 'No se pudo guardar.'); return; }
                window.bootstrap?.Modal.getInstance(this.modalEl)?.hide();
                aviso('success', id ? 'Vacación actualizada' : 'Vacación registrada', json.msg || '');
                await this.recargar();
                if (this.onCambio) this.onCambio();
            } catch (e) {
                aviso('error', 'Error de red', 'No se pudo conectar con el servidor.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        }

        async eliminar() {
            const id = this.m('f-id').value;
            if (!id) return;
            const r = window.Swal
                ? await Swal.fire({
                    icon: 'warning', title: '¿Eliminar esta vacación?',
                    text: 'Sus días vuelven a contar en el saldo del empleado.',
                    showCancelButton: true, confirmButtonColor: '#d33',
                    confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
                })
                : { isConfirmed: window.confirm('¿Eliminar esta vacación?') };
            if (!r.isConfirmed) return;

            try {
                const fd = new FormData();
                fd.append('id_eliminar', id);
                const resp = await fetch(`${this.url}/delete`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (!json.ok) { aviso('error', 'Atención', json.error || 'No se pudo eliminar.'); return; }
                window.bootstrap?.Modal.getInstance(this.modalEl)?.hide();
                aviso('success', 'Vacación eliminada', json.msg || '');
                await this.recargar();
                if (this.onCambio) this.onCambio();
            } catch (e) {
                aviso('error', 'Error de red', 'No se pudo conectar con el servidor.');
            }
        }

        enlazar() {
            this.el('btn-nueva')?.addEventListener('click', () => this.abrirNueva());
            this.el('btn-recargar')?.addEventListener('click', () => this.recargar());

            this.el('tbody').addEventListener('click', (ev) => {
                const tr = ev.target.closest('tr[data-id]');
                if (!tr) return;
                this.abrirEditar(parseInt(tr.dataset.id, 10));
            });

            if (!this.modalEl) return;

            // Modal sobre modal: app.css fuerza .modal { z-index:5060 !important }, así que
            // este tiene que subir por encima con un inline !important (y su backdrop).
            this.modalEl.addEventListener('show.bs.modal', () => {
                this.modalEl.style.setProperty('z-index', '5080', 'important');
                setTimeout(() => {
                    const backs = document.querySelectorAll('.modal-backdrop');
                    const ultimo = backs[backs.length - 1];
                    if (ultimo) ultimo.style.setProperty('z-index', '5075', 'important');
                }, 0);
            });
            // Al cerrarse, el modal de la ficha sigue abierto: hay que devolverle al body
            // la clase que Bootstrap le quita (si no, la ficha pierde su scroll).
            this.modalEl.addEventListener('hidden.bs.modal', () => {
                if (document.querySelector('.modal.show')) document.body.classList.add('modal-open');
            });

            // Enter no debe disparar el guardado de ningún formulario que lo contenga
            // (el modal se declara dentro del <form> de la ficha, aunque luego se mueva
            // al <body>). El textarea sí conserva sus saltos de línea.
            this.modalEl.addEventListener('keydown', (ev) => {
                if (ev.key === 'Enter' && ev.target.matches('input')) ev.preventDefault();
            });

            this.m('f-dias').addEventListener('input', () => this.recalcularValor());
            ['f-desde', 'f-hasta'].forEach(rol => this.m(rol).addEventListener('change', () => {
                // Días sugeridos por el rango mientras no se hayan fijado a mano.
                const actual = parseFloat(this.m('f-dias').value) || 0;
                const d = this.m('f-desde').value, h = this.m('f-hasta').value;
                if (d && h) {
                    const n = Math.floor((new Date(h) - new Date(d)) / 86400000) + 1;
                    if (n > 0 && actual === 0) { this.m('f-dias').value = n; this.recalcularValor(); }
                }
                // El rol que alimenta sale del mes de la fecha desde.
                if (d) {
                    this.m('f-mes').value = String(parseInt(d.substring(5, 7), 10));
                    this.m('f-anio').value = d.substring(0, 4);
                }
            }));

            this.m('btn-guardar').addEventListener('click', () => this.guardar());
            this.m('btn-eliminar')?.addEventListener('click', () => this.eliminar());
        }
    }

    window.CuadroVacacionesEmpleado = CuadroVacacionesEmpleado;
})(window, document);
