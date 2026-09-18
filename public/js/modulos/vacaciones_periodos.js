/**
 * Cuadro de períodos de vacaciones de un empleado (reutilizable).
 *
 * Muestra sus años de trabajo —derecho, lo tomado o pagado antes del sistema, lo gozado
 * en el sistema y lo pendiente— y permite marcar los períodos ya tomados o pagados. Lo
 * usan el modal de Vacaciones y la pestaña Vacaciones del modal de Empleados; el marcado
 * sale de app/views/modulos/vacaciones/_cuadro_periodos.php y los datos, de los
 * endpoints de modulos/vacaciones.
 *
 *   const cuadro = new CuadroPeriodosVacaciones(elementoRaiz, { onCambio: (data) => {} });
 *   const data = await cuadro.cargar(idEmpleado, { exclude: idVacacionEnEdicion });
 *
 * cargar() devuelve lo mismo que getInfoEmpleadoAjax (saldo, antigüedad, períodos…) para
 * que la página pinte su propio panel, o null si otra carga la reemplazó. onCambio se
 * llama con los datos nuevos cada vez que se marca o se quita la marca de un período.
 */
(function (window, document) {
    'use strict';

    if (window.CuadroPeriodosVacaciones) return; // la página ya lo cargó

    const num = (v) => {
        const n = Math.round((parseFloat(v) || 0) * 100) / 100;
        return n.toLocaleString('es-EC', { maximumFractionDigits: 2 });
    };
    const dias = (v) => { const t = num(v); return t + (t === '1' ? ' día' : ' días'); };
    const fmtFecha = (ymd) => ymd ? `${ymd.substring(8, 10)}-${ymd.substring(5, 7)}-${ymd.substring(0, 4)}` : '—';
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const SITUACION = {
        pendiente:      ['warning',   'Pendiente'],
        parcial:        ['warning',   'Parcial'],
        tomado:         ['success',   'Vacaciones tomadas'],
        pagado:         ['primary',   'Pagado antes del sistema'],
        gozado:         ['info',      'Gozado en el sistema'],
        en_curso:       ['secondary', 'En curso'],
        fuera_de_rango: ['danger',    'Ya no calza con la antigüedad'],
    };

    function filaPeriodo(p, puedeMarcar) {
        const [color, texto] = SITUACION[p.situacion] || ['secondary', p.situacion];
        const m = p.marca;
        const marcable = puedeMarcar && p.marcable;

        let detalle = '';
        if (m) {
            detalle = `${m.estado === 'pagado' ? 'Pagado' : 'Tomado'} antes del sistema: ${dias(m.dias)}.`
                + ` Marcado por ${m.usuario || '—'} el ${m.fecha_registro || '—'}.`
                + (m.observacion ? ` ${m.observacion}` : '')
                + (m.fechas_marcadas ? ` Se marcó cuando el período iba del ${m.fechas_marcadas}.` : '');
        } else if (p.situacion === 'en_curso') {
            detalle = `Año en curso: lleva ${num(p.dias_acumulados)} de ${num(p.dias_derecho)} días. Se podrá marcar cuando se complete.`;
        } else if (p.situacion === 'gozado' || p.situacion === 'parcial') {
            detalle = `Cubierto con vacaciones registradas en el sistema: ${dias(p.dias_sistema)}.`;
        }
        const alerta = m && (m.fechas_marcadas || p.situacion === 'fuera_de_rango')
            ? ' <i class="bi bi-exclamation-triangle-fill text-warning"></i>' : '';
        const badge = `<span class="badge bg-${color} bg-opacity-10 text-${color} border border-${color} border-opacity-25"`
            + (detalle ? ` title="${esc(detalle)}"` : '') + `>${texto}</span>${alerta}`;

        const derecho = (p.completo || p.situacion === 'fuera_de_rango')
            ? num(p.dias_derecho)
            : `${num(p.dias_acumulados)}<span class="text-muted"> de ${num(p.dias_derecho)}</span>`;

        let antes = '—';
        if (m) {
            const icono = m.estado === 'pagado' ? 'bi-cash-coin text-primary' : 'bi-check2-square text-success';
            antes = `${num(m.dias)} <i class="bi ${icono}"></i>`;
        } else if (marcable) {
            antes = `<input type="number" class="form-control form-control-sm shadow-none text-center mx-auto vac-per-dias" data-numero="${p.numero}"`
                + ` value="${p.dias_derecho}" min="0.5" max="${p.dias_derecho}" step="0.5" disabled title="Días que tomó o cobró de este período"`
                + ' style="padding:0 4px;height:20px;font-size:0.78rem;width:60px;">';
        }

        const accion = m && m.puede_desmarcar
            ? `<button type="button" class="btn btn-link btn-sm p-0 text-danger vac-per-desmarcar" data-id="${m.id}" data-numero="${p.numero}" title="Quitar la marca"><i class="bi bi-arrow-counterclockwise"></i></button>`
            : '';

        const clases = [];
        if (['tomado', 'pagado', 'gozado'].includes(p.situacion)) clases.push('vac-per-cerrado');
        if (p.completo && (p.situacion === 'pendiente' || p.situacion === 'parcial')) clases.push('vac-per-pendiente');

        return `<tr${clases.length ? ` class="${clases.join(' ')}"` : ''}>
            <td class="text-center">${marcable ? `<input type="checkbox" class="form-check-input m-0 vac-per-chk" data-numero="${p.numero}">` : ''}</td>
            <td class="text-center fw-medium">${p.numero}</td>
            <td>${fmtFecha(p.fecha_inicio)}</td>
            <td>${fmtFecha(p.fecha_fin)}</td>
            <td class="text-center">${derecho}</td>
            <td class="text-center">${antes}</td>
            <td class="text-center">${p.dias_sistema > 0 ? num(p.dias_sistema) : '—'}</td>
            <td class="text-center fw-bold">${p.pendiente > 0 ? num(p.pendiente) : '—'}</td>
            <td>${badge}</td>
            <td class="text-center">${accion}</td>
        </tr>`;
    }

    class CuadroPeriodosVacaciones {
        constructor(raiz, opciones = {}) {
            this.raiz = raiz;
            this.url = raiz.dataset.url;
            this.puedeMarcar = raiz.dataset.puedeMarcar === '1';
            this.onCambio = typeof opciones.onCambio === 'function' ? opciones.onCambio : null;
            this.seq = 0;          // descarta respuestas que llegan tarde
            this.idEmpleado = null;
            this.exclude = 0;
            this.data = null;
            this.pendientes = 0;   // períodos completos con días pendientes
            this.enlazar();
        }

        el(rol) {
            return this.raiz.querySelector(`[data-rol="${rol}"]`);
        }

        /** Trae y pinta el cuadro del empleado. `exclude`: vacación en edición (no cuenta en el saldo). */
        async cargar(idEmpleado, { exclude = 0 } = {}) {
            const seq = ++this.seq;
            this.idEmpleado = idEmpleado;
            this.exclude = exclude || 0;
            try {
                const resp = await fetch(`${this.url}/getInfoEmpleadoAjax?id_empleado=${encodeURIComponent(idEmpleado)}&exclude=${encodeURIComponent(this.exclude)}`);
                const json = await resp.json();
                if (seq !== this.seq) return null;
                if (!json.ok) {
                    this.mostrarError(json.error || 'No se pudo cargar la información de vacaciones del empleado.');
                    return null;
                }
                this.render(json.data);
                return json.data;
            } catch (e) {
                if (seq === this.seq) this.mostrarError('No se pudo conectar con el servidor.');
                return null;
            }
        }

        /** Vacía el cuadro y descarta cualquier carga en curso. */
        limpiar() {
            this.seq++;
            this.idEmpleado = null;
            this.data = null;
            this.pendientes = 0;
            this.el('tbody').innerHTML = '';
            this.el('aviso').classList.add('d-none');
            this.el('adelantados').classList.add('d-none');
            this.el('acciones')?.classList.add('d-none');
            this.raiz.querySelectorAll('[data-rol^="res-"]').forEach(n => { n.textContent = n.dataset.rol === 'res-derecho-anio' ? '' : '—'; });
        }

        mostrarError(msg) {
            this.el('tbody').innerHTML = '';
            this.el('acciones')?.classList.add('d-none');
            this.el('aviso').textContent = msg;
            this.el('aviso').classList.remove('d-none');
        }

        render(d) {
            this.data = d;
            const periodos = d.periodos || [];
            const puedeMarcar = this.puedeMarcar && !!d.periodos_disponible;
            const hayMarcables = puedeMarcar && periodos.some(p => p.marcable);

            // Resumen (solo si el marcado lo trae: pestaña Vacaciones del empleado).
            const res = (rol, texto) => { const n = this.el(rol); if (n) n.textContent = texto; };
            const saldo = parseFloat(d.saldo) || 0;
            res('res-ingreso', d.fecha_ingreso ? fmtFecha(d.fecha_ingreso) : 'sin registrar');
            res('res-antig', d.antiguedad || '—');
            res('res-derecho-anio', d.fecha_ingreso ? `Derecho del año: ${dias(d.derecho_anio_actual)}` : '');
            res('res-acumulado', dias(d.total_derecho));
            res('res-marcados', dias(d.dias_marcados));
            res('res-gozados', dias(d.dias_gozados_total));
            res('res-saldo', dias(saldo));
            const nSaldo = this.el('res-saldo');
            if (nSaldo) {
                nSaldo.classList.toggle('text-danger', saldo < 0);
                nSaldo.classList.toggle('text-primary', saldo >= 0);
            }

            let aviso = '';
            if (!d.fecha_ingreso) {
                aviso = 'El empleado no tiene fecha de ingreso: regístrela en su ficha (pestaña Periodos del módulo Empleados) para calcular sus períodos.';
            } else if (!d.periodos_disponible) {
                aviso = 'Todavía no se pueden marcar períodos: falta habilitar esta función en la base de datos. Avise al administrador del sistema.';
            } else if (periodos.some(p => p.situacion === 'fuera_de_rango' || (p.marca && p.marca.fechas_marcadas))) {
                aviso = 'Hay períodos marcados con otras fechas: cambió la fecha de ingreso del empleado. Revíselos y quite la marca de los que ya no correspondan.';
            }
            this.el('aviso').textContent = aviso;
            this.el('aviso').classList.toggle('d-none', !aviso);

            this.el('tbody').innerHTML = periodos.length
                ? periodos.map(p => filaPeriodo(p, puedeMarcar)).join('')
                : '<tr><td colspan="10" class="text-center text-muted py-3">Sin períodos para mostrar.</td></tr>';

            const adelantados = parseFloat(d.dias_adelantados) || 0;
            this.el('adelantados').textContent = adelantados > 0
                ? `Gozó ${dias(adelantados)} por adelantado: más de lo que ha acumulado hasta hoy.` : '';
            this.el('adelantados').classList.toggle('d-none', adelantados <= 0);

            this.el('acciones')?.classList.toggle('d-none', !hayMarcables);
            const todos = this.el('chk-todos');
            todos.checked = false;
            todos.indeterminate = false;
            todos.disabled = !hayMarcables;

            this.pendientes = periodos.filter(p => p.completo && (p.situacion === 'pendiente' || p.situacion === 'parcial')).length;
            this.actualizarSeleccion();
            this.scrollAlPrimerPendiente();
        }

        /** Empleados con muchos años: lleva a la vista el primer período pendiente (si el cuadro se ve). */
        scrollAlPrimerPendiente() {
            const caja = this.el('caja');
            const fila = caja.querySelector('tr.vac-per-pendiente');
            if (!fila || caja.offsetParent === null) return;
            const thead = caja.querySelector('thead');
            caja.scrollTop = Math.max(0, fila.offsetTop - (thead ? thead.offsetHeight : 0));
        }

        actualizarSeleccion() {
            const chks = [...this.raiz.querySelectorAll('.vac-per-chk')];
            let n = 0, total = 0;
            chks.forEach(chk => {
                const inp = this.raiz.querySelector(`.vac-per-dias[data-numero="${chk.dataset.numero}"]`);
                if (inp) inp.disabled = !chk.checked;
                chk.closest('tr')?.classList.toggle('vac-per-sel', chk.checked);
                if (chk.checked) { n++; total += parseFloat(inp?.value) || 0; }
            });
            const sel = this.el('sel');
            if (sel) sel.textContent = n ? `${n} período${n === 1 ? '' : 's'} seleccionado${n === 1 ? '' : 's'} · ${dias(total)}` : 'Ningún período seleccionado';
            ['btn-tomado', 'btn-pagado'].forEach(rol => { const b = this.el(rol); if (b) b.disabled = n === 0; });
            const todos = this.el('chk-todos');
            todos.checked = n > 0 && n === chks.length;
            todos.indeterminate = n > 0 && n < chks.length;
        }

        async recargarTrasCambio() {
            const d = await this.cargar(this.idEmpleado, { exclude: this.exclude });
            if (d && this.onCambio) this.onCambio(d);
        }

        async marcar(estado) {
            const idEmp = this.idEmpleado;
            const chks = [...this.raiz.querySelectorAll('.vac-per-chk:checked')];
            if (!idEmp || !chks.length) return;

            const fd = new FormData();
            fd.append('id_empleado', idEmp);
            fd.append('estado', estado);
            fd.append('observacion', (this.el('obs')?.value || '').trim());
            let total = 0;
            for (const chk of chks) {
                const n = chk.dataset.numero;
                const inp = this.raiz.querySelector(`.vac-per-dias[data-numero="${n}"]`);
                const valor = parseFloat(inp?.value) || 0;
                const max = parseFloat(inp?.max) || 0;
                if (valor <= 0 || valor > max) {
                    Swal.fire({ icon: 'warning', title: 'Revise los días', text: `Los días del período ${n} deben ser mayores a cero y hasta ${num(max)}.` });
                    inp?.focus();
                    return;
                }
                total += valor;
                fd.append(`periodos[${n}]`, String(valor));
            }

            const r = await Swal.fire({
                icon: 'question',
                title: chks.length === 1 ? `¿Marcar el período como ${estado}?` : `¿Marcar ${chks.length} períodos como ${estado}s?`,
                text: `Se descontarán ${dias(total)} del saldo del empleado. No se genera ningún valor ni se toca el rol de pagos.`,
                showCancelButton: true,
                confirmButtonText: 'Sí, marcar',
                cancelButtonText: 'Cancelar',
            });
            if (!r.isConfirmed) return;

            ['btn-tomado', 'btn-pagado'].forEach(rol => { const b = this.el(rol); if (b) b.disabled = true; });
            try {
                const resp = await fetch(`${this.url}/marcarPeriodosAjax`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) {
                    Swal.fire({ icon: 'success', title: json.msg, timer: 1300, showConfirmButton: false });
                    if (this.el('obs')) this.el('obs').value = '';
                    await this.recargarTrasCambio();
                } else {
                    Swal.fire({ icon: 'error', title: 'Atención', text: json.error || 'No se pudieron marcar los períodos.' });
                    this.actualizarSeleccion();
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
                this.actualizarSeleccion();
            }
        }

        async desmarcar(id, numero) {
            const r = await Swal.fire({
                icon: 'warning',
                title: `¿Quitar la marca del período ${numero}?`,
                text: 'Sus días vuelven a contar en el saldo del empleado.',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, quitar',
                cancelButtonText: 'Cancelar',
            });
            if (!r.isConfirmed) return;
            try {
                const fd = new FormData();
                fd.append('id_periodo', id);
                const resp = await fetch(`${this.url}/desmarcarPeriodoAjax`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) {
                    Swal.fire({ icon: 'success', title: json.msg, timer: 1300, showConfirmButton: false });
                    await this.recargarTrasCambio();
                } else {
                    Swal.fire({ icon: 'error', title: 'Atención', text: json.error || 'No se pudo quitar la marca.' });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
            }
        }

        enlazar() {
            const tbody = this.el('tbody');
            tbody.addEventListener('change', (ev) => {
                if (ev.target.classList.contains('vac-per-chk')) this.actualizarSeleccion();
            });
            tbody.addEventListener('input', (ev) => {
                if (ev.target.classList.contains('vac-per-dias')) this.actualizarSeleccion();
            });
            tbody.addEventListener('click', (ev) => {
                const btn = ev.target.closest('.vac-per-desmarcar');
                if (btn) { this.desmarcar(btn.dataset.id, btn.dataset.numero); return; }
                // Clic en cualquier parte de la fila marca/desmarca su casilla.
                if (ev.target.closest('input, button, a')) return;
                const chk = ev.target.closest('tr')?.querySelector('.vac-per-chk');
                if (chk) { chk.checked = !chk.checked; this.actualizarSeleccion(); }
            });
            // Enter en los días u observación no debe enviar el formulario que contiene al cuadro.
            this.raiz.addEventListener('keydown', (ev) => {
                if (ev.key === 'Enter' && ev.target.matches('input')) ev.preventDefault();
            });
            this.el('chk-todos').addEventListener('change', (ev) => {
                this.raiz.querySelectorAll('.vac-per-chk').forEach(chk => { chk.checked = ev.target.checked; });
                this.actualizarSeleccion();
            });
            this.el('btn-tomado')?.addEventListener('click', () => this.marcar('tomado'));
            this.el('btn-pagado')?.addEventListener('click', () => this.marcar('pagado'));
        }
    }

    window.CuadroPeriodosVacaciones = CuadroPeriodosVacaciones;
})(window, document);
