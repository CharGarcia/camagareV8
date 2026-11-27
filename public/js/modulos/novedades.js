/**
 * Lógica del Modal de Novedades (Nómina)
 */
(function (window, document) {
    'use strict';

    const urlModulo = BASE_URL + '/modulos/novedades';
    let modalInst = null;
    const form = document.getElementById('formNovedad');

    function getModal() {
        if (!modalInst && typeof bootstrap !== 'undefined') {
            const el = document.getElementById('modalNovedad');
            if (el) modalInst = new bootstrap.Modal(el);
        }
        return modalInst;
    }

    // ─── Buscador de empleado (autocompletar) ───────────────────────────────
    const empBuscar = document.getElementById('nov_empleado_buscar');
    const empHidden = document.getElementById('nov_id_empleado');
    const empResultados = document.getElementById('nov_empleado_resultados');
    let empTimer = null;
    // ¿El empleado elegido aporta al IESS? Si no, sus ingresos tampoco (como en los rubros fijos).
    let empAportaIess = true;

    // En pgsql el booleano puede llegar como 't'/'f'.
    const esNo = (v) => v === false || v === 'f' || v === 0 || v === '0' || v === 'false';
    const esSi = (v) => v === true || v === 't' || v === 1 || v === '1' || v === 'true';

    function ocultarResultadosEmp() { if (empResultados) empResultados.classList.add('d-none'); }

    function setEmpleado(id, texto, aportaIess = true) {
        if (empHidden) empHidden.value = id || '';
        if (empBuscar) empBuscar.value = texto || '';
        empAportaIess = aportaIess !== false;
        novSincronizarIess();
        ocultarResultadosEmp();
    }

    async function buscarEmpleados(q) {
        try {
            const resp = await fetch(`${urlModulo}/buscarEmpleadosAjax?q=${encodeURIComponent(q)}`);
            const json = await resp.json();
            if (!json.ok || !empResultados) return;
            if (!json.data.length) {
                empResultados.innerHTML = '<div class="list-group-item small text-muted">Sin resultados</div>';
                empResultados.classList.remove('d-none');
                return;
            }
            empResultados.innerHTML = json.data.map(e => {
                const texto = `${e.nombres_apellidos} (${e.identificacion})`.replace(/"/g, '&quot;');
                return `<button type="button" class="list-group-item list-group-item-action py-1 small" data-id="${e.id}" data-texto="${texto}" data-aporta-iess="${e.aporta_iess === false ? '0' : '1'}">
                            <span class="fw-medium">${e.nombres_apellidos}</span> <span class="text-muted">${e.identificacion}</span>
                        </button>`;
            }).join('');
            empResultados.classList.remove('d-none');
        } catch (e) { ocultarResultadosEmp(); }
    }

    if (empBuscar) {
        empBuscar.addEventListener('input', () => {
            if (empHidden) empHidden.value = ''; // obliga a re-seleccionar del listado
            if (!empAportaIess) { empAportaIess = true; novSincronizarIess(); }
            const q = empBuscar.value.trim();
            clearTimeout(empTimer);
            if (q.length < 2) { ocultarResultadosEmp(); return; }
            empTimer = setTimeout(() => buscarEmpleados(q), 300);
        });
        empBuscar.addEventListener('blur', () => setTimeout(ocultarResultadosEmp, 200));
    }
    if (empResultados) {
        // mousedown (antes del blur del input) para poder seleccionar.
        empResultados.addEventListener('mousedown', (ev) => {
            const btn = ev.target.closest('[data-id]');
            if (!btn) return;
            ev.preventDefault();
            setEmpleado(btn.dataset.id, btn.dataset.texto, btn.dataset.aportaIess !== '0');
        });
    }

    // ─── Suprimir/Retroceso limpian todo el input de una vez (reescribir rápido) ─
    function habilitarLimpiezaRapida(el) {
        if (!el) return;
        el.addEventListener('keydown', (e) => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && el.value !== '') {
                e.preventDefault();
                el.value = '';
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }
    document.querySelectorAll('#modalNovedad input[type="text"], #modalNovedad input[type="number"], #modalNovedad textarea')
        .forEach(habilitarLimpiezaRapida);

    // ─── Autocompletar Observación con "Tipo - Mes Año" ──────────────────────
    let obsAuto = '';
    // Modo del modal Nuevo: 'uno' (un empleado), 'todos' (cuadrícula de todos los empleados)
    // o 'prestamo' (Préstamo Empresa con tabla de cuotas).
    let modoActual = 'uno';

    function textoObsAuto() {
        const tipoSel = document.getElementById('nov_tipo_codigo');
        const mesSel = document.getElementById('nov_periodo_mes');
        const anio = (document.getElementById('nov_periodo_anio')?.value || '').trim();
        if (!tipoSel || !tipoSel.value) return '';
        const tipo = tipoSel.options[tipoSel.selectedIndex].text;
        const mes = (mesSel && mesSel.value) ? mesSel.options[mesSel.selectedIndex].text : '';
        return `${tipo} - ${mes} ${anio}`.replace(/\s+/g, ' ').trim();
    }

    // Solo autocompleta si el campo está vacío o si aún contiene el último
    // valor autogenerado (para no pisar un texto que el usuario editó a mano).
    function actualizarObservacion() {
        const obs = document.getElementById('nov_observacion');
        if (!obs) return;
        // En "Todos los empleados" no hay un solo tipo: vacía = "Tipo - Mes Año" de cada novedad.
        // En "Préstamo empresa" la observación es del préstamo (cada cuota lleva "Cuota k/n").
        if (modoActual !== 'uno') return;
        const actual = obs.value.trim();
        if (actual === '' || actual === obsAuto) {
            obsAuto = textoObsAuto();
            obs.value = obsAuto;
        }
    }

    ['nov_tipo_codigo', 'nov_periodo_mes', 'nov_periodo_anio'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', actualizarObservacion);
    });
    document.getElementById('nov_periodo_anio')?.addEventListener('input', actualizarObservacion);

    // Muestra/oculta Valor, Aporta IESS y Motivo según el tipo, y ajusta la etiqueta del valor.
    window.novToggleCampos = function () {
        const cod = document.getElementById('nov_tipo_codigo').value;
        const cat = window.NOVEDAD_CATALOGO || {};
        const esAviso = cod !== '' && cod === cat.cod_aviso_salida;
        // Otros Ingresos y horas: los únicos con la opción de IESS.
        const conIess = cod !== '' && Object.prototype.hasOwnProperty.call(cat.iess_por_defecto || {}, cod);
        // Anticipo, Quincena y Préstamo Empresa: selector Por pagar / Ya pagado.
        const conPago = cod !== '' && (cat.cods_entrega || []).includes(cod);

        const contValor = document.getElementById('nov_container_valor');
        const contIess = document.getElementById('nov_container_iess');
        const contMotivo = document.getElementById('nov_container_motivo');
        const lblValor = document.getElementById('nov_label_valor');
        const inpValor = document.getElementById('nov_valor');
        const selMotivo = document.getElementById('nov_motivo_codigo');

        if (esAviso) {
            contValor.classList.add('d-none');
            if (inpValor) inpValor.value = '0';
            contMotivo.classList.remove('d-none');
        } else {
            contValor.classList.remove('d-none');
            contMotivo.classList.add('d-none');
            if (selMotivo) selMotivo.value = '';
            if (lblValor) lblValor.textContent = (cat.labels && cat.labels[cod]) ? cat.labels[cod] : 'Monto ($)';
        }
        // Con el selector de IESS, el valor comparte la fila con él.
        contIess?.classList.toggle('d-none', !conIess);
        document.getElementById('nov_container_pago')?.classList.toggle('d-none', !conPago);
        contValor.classList.toggle('col-md-6', !conIess && !conPago);
        contValor.classList.toggle('col-md-3', conIess || conPago);
        novSincronizarIess();
        novSincronizarAplica((cat.cods_solo_rol || []).includes(cod));
    };

    // Días no laborados y aviso de salida solo afectan al rol mensual (en la quincena
    // y la semana solo hay ingresos y descuentos): "Afecta a" queda en Rol de Pagos y
    // bloqueado; al cambiar a otro tipo se recupera lo que estaba elegido. El backend
    // aplica la misma regla.
    function novSincronizarAplica(soloRol) {
        const sel = document.getElementById('nov_aplica_en');
        const aviso = document.getElementById('nov_aplica_aviso');
        if (!sel) return;
        if (soloRol) {
            if (!sel.classList.contains('nov-aplica-off')) sel.dataset.antes = sel.value;
            sel.value = 'rol';
            sel.disabled = true;
            sel.classList.add('nov-aplica-off');
            aviso?.classList.remove('d-none');
        } else {
            if (sel.classList.contains('nov-aplica-off')) {
                sel.classList.remove('nov-aplica-off');
                if (!sel.classList.contains('nov-lock-off')) sel.disabled = false;
                if (sel.dataset.antes) sel.value = sel.dataset.antes;
            }
            aviso?.classList.add('d-none');
        }
    }

    function novReiniciarAplica() {
        const sel = document.getElementById('nov_aplica_en');
        if (!sel) return;
        sel.classList.remove('nov-aplica-off');
        sel.disabled = false;
        delete sel.dataset.antes;
        document.getElementById('nov_aplica_aviso')?.classList.add('d-none');
    }

    // Si el empleado no aporta al IESS, el selector queda en "No" y bloqueado (igual
    // que los rubros fijos de su ficha); al volver a un empleado que aporta se
    // recupera lo que estaba elegido. El backend aplica la misma regla.
    function novSincronizarIess() {
        const sel = document.getElementById('nov_aporta_iess');
        const aviso = document.getElementById('nov_iess_aviso');
        if (!sel) return;
        if (!empAportaIess) {
            if (!sel.classList.contains('nov-iess-off')) sel.dataset.antes = sel.value;
            sel.value = 'no';
            sel.disabled = true;
            sel.classList.add('nov-iess-off');
            aviso?.classList.remove('d-none');
        } else {
            if (sel.classList.contains('nov-iess-off')) {
                sel.classList.remove('nov-iess-off');
                if (!sel.classList.contains('nov-lock-off')) sel.disabled = false;
                sel.value = sel.dataset.antes || 'si';
            }
            aviso?.classList.add('d-none');
        }
    }

    function novReiniciarIess(valor) {
        const sel = document.getElementById('nov_aporta_iess');
        if (!sel) return;
        sel.classList.remove('nov-iess-off');
        sel.disabled = false;
        delete sel.dataset.antes;
        sel.value = valor;
        empAportaIess = true;
    }

    // ─── Bloqueo de solo lectura (rol del período ya pagado/contabilizado) ──
    // El backend ya rechaza el guardado (NovedadService::bloquearSiRolPagado); esto
    // evita que el usuario intente editar sin saber que no se va a guardar.
    function novLimpiarBloqueoSoloLectura() {
        const modal = document.getElementById('modalNovedad');
        if (!modal) return;
        modal.querySelectorAll('.nov-lock-off').forEach(el => {
            el.disabled = false;
            el.classList.remove('nov-lock-off');
        });
        document.getElementById('novBloqueoAviso')?.classList.add('d-none');
        document.getElementById('btnGuardarNov')?.classList.remove('d-none');
    }

    function novAplicarSoloLectura(bloqueada) {
        if (!bloqueada) return;
        document.getElementById('novBloqueoAviso')?.classList.remove('d-none');
        const modal = document.getElementById('modalNovedad');
        if (modal) {
            modal.querySelectorAll('.modal-body input, .modal-body select, .modal-body textarea').forEach(el => {
                if (el.disabled) return;
                el.disabled = true;
                el.classList.add('nov-lock-off');
            });
        }
        document.getElementById('btnGuardarNov')?.classList.add('d-none');
        document.getElementById('btnEliminarNov')?.classList.add('d-none');
    }

    // ─── Modo "Todos los empleados": cuadrícula empleado × tipo de novedad ──
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const tabla = document.getElementById('nov_tabla_masivo');
    let gridCargada = false;      // la cuadrícula de esta apertura del modal ya se armó
    let tokenMasivo = '';         // guardado único (CLAUDE.md §8): uno por cada apertura de Nuevo
    let guardandoMasivo = false;

    const cat = () => window.NOVEDAD_CATALOGO || {};
    const esIessTipo = (cod) => Object.prototype.hasOwnProperty.call(cat().iess_por_defecto || {}, cod);
    const esEntregaTipo = (cod) => (cat().cods_entrega || []).includes(cod);
    const esAvisoTipo = (cod) => cod === cat().cod_aviso_salida;
    const unidadCorta = (cod) => ({ horas: 'horas', dias: 'días', monto: '$' }[(cat().unidades || {})[cod]] || '');

    function setModo(modo) {
        modoActual = ['todos', 'prestamo'].includes(modo) ? modo : 'uno';
        const modal = document.getElementById('modalNovedad');
        const dialog = document.getElementById('nov_dialog');
        modal?.classList.toggle('nov-modo-todos', modoActual === 'todos');
        modal?.classList.toggle('nov-modo-prestamo', modoActual === 'prestamo');
        dialog?.classList.toggle('modal-lg', modoActual !== 'todos');
        // "Todos los empleados": pantalla completa, para ver más filas y columnas de la cuadrícula.
        dialog?.classList.toggle('modal-fullscreen', modoActual === 'todos');
        const radio = document.getElementById('nov_modo_' + modoActual);
        if (radio) radio.checked = true;
        const obs = document.getElementById('nov_observacion');
        if (modoActual === 'prestamo') {
            // Las cuotas son Préstamo Empresa: "Afecta a" libre y sin la observación autogenerada del tipo.
            novReiniciarAplica();
            if (obs && obs.value.trim() === obsAuto) { obs.value = ''; }
            obsAuto = '';
            renderCuotasPrestamo();
        } else if (modoActual === 'todos') {
            // Sin un tipo elegido, "Afecta a" queda libre (los que solo van al rol mensual
            // los corrige el servidor) y la observación autogenerada del tipo no aplica.
            novReiniciarAplica();
            if (obs && obs.value.trim() === obsAuto) { obs.value = ''; }
            obsAuto = '';
            if (!gridCargada) cargarGrid();
        } else {
            window.novToggleCampos();
            actualizarObservacion();
        }
    }
    document.getElementById('nov_modo_uno')?.addEventListener('change', () => setModo('uno'));
    document.getElementById('nov_modo_todos')?.addEventListener('change', () => setModo('todos'));
    document.getElementById('nov_modo_prestamo')?.addEventListener('change', () => setModo('prestamo'));

    // ─── Modo "Préstamo empresa": tabla de cuotas ───────────────────────────
    // Mismo cálculo que PrestamoEmpleadoService::calcularCuotas (el servidor recalcula):
    // partes iguales a centavos y la última cuota absorbe la diferencia; un mes por cuota.
    let tokenPrestamo = '';
    let guardandoPrestamo = false;
    const r2 = (v) => (typeof window.CMG_r2 === 'function' ? window.CMG_r2(v) : Number(Number(v).toFixed(2)));
    const fmt2 = (v) => r2(v).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function calcularCuotasPrestamo(total, n, mes, anio) {
        const cuotas = [];
        if (!(n >= 1) || !(total > 0) || !(mes >= 1) || !(anio >= 2000)) return cuotas;
        total = r2(total);
        const base = r2(total / n);
        for (let k = 1; k <= n; k++) {
            const idx = (mes - 1) + (k - 1);
            cuotas.push({
                numero: k,
                mes: (idx % 12) + 1,
                anio: anio + Math.floor(idx / 12),
                valor: k < n ? base : r2(total - base * (n - 1)),
            });
        }
        return cuotas;
    }

    function cuotasMarcadasCobradas() {
        return Array.from(document.querySelectorAll('#nov_tabla_prestamo tbody .nov-cuota-cobrada-chk:checked'))
            .map(c => parseInt(c.dataset.num, 10));
    }

    function renderCuotasPrestamo() {
        const tbody = document.querySelector('#nov_tabla_prestamo tbody');
        if (!tbody) return;
        const monto = parseFloat(String(document.getElementById('nov_pre_monto')?.value || '').replace(',', '.')) || 0;
        const n = parseInt(document.getElementById('nov_pre_cuotas')?.value || '', 10) || 0;
        const mes = parseInt(document.getElementById('nov_periodo_mes')?.value || '', 10) || 0;
        const anio = parseInt(document.getElementById('nov_periodo_anio')?.value || '', 10) || 0;
        const marcadas = new Set(cuotasMarcadasCobradas());
        const meses = (window.NOVEDAD_CATALOGO || {}).meses || {};
        const nombreMes = (m) => meses[m] || document.querySelector(`#nov_periodo_mes option[value="${m}"]`)?.textContent || m;

        const cuotas = n > 120 ? [] : calcularCuotasPrestamo(monto, n, mes, anio);
        if (!cuotas.length) {
            const msg = n > 120 ? 'El máximo es 120 cuotas.' : 'Escriba el monto y el número de cuotas.';
            tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">${msg}</td></tr>`;
        } else {
            tbody.innerHTML = cuotas.map(c => {
                const chk = marcadas.has(c.numero);
                return `<tr class="${chk ? 'nov-cuota-cobrada' : ''}">
                            <td class="text-center">${c.numero}/${n}</td>
                            <td>${esc(nombreMes(c.mes))} ${c.anio}</td>
                            <td class="text-end">${fmt2(c.valor)}</td>
                            <td class="text-center nov-cuota-check"><input type="checkbox" class="form-check-input nov-cuota-cobrada-chk" data-num="${c.numero}" ${chk ? 'checked' : ''} aria-label="Cuota ${c.numero} ya cobrada"></td>
                        </tr>`;
            }).join('');
        }
        actualizarTotalesPrestamo(cuotas);
    }

    // Sin scroll vertical en el modal: la tabla de cuotas toma solo el alto que sobra en la
    // ventana y es ella la que desplaza sus filas (el encabezado y los totales quedan fijos).
    function ajustarAltoPrestamo() {
        if (modoActual !== 'prestamo') return;
        const cont = document.querySelector('#modalNovedad .nov-prestamo-tabla');
        const body = document.querySelector('#formNovedad > .modal-body');
        if (!cont || !body || !body.clientHeight) return; // modal aún oculto: se ajusta al mostrarse
        cont.style.maxHeight = 'none';
        const exceso = body.scrollHeight - body.clientHeight;
        if (exceso > 0) {
            cont.style.maxHeight = Math.max(120, cont.offsetHeight - exceso - 2) + 'px';
        }
    }
    document.getElementById('modalNovedad')?.addEventListener('shown.bs.modal', ajustarAltoPrestamo);
    window.addEventListener('resize', ajustarAltoPrestamo);

    function actualizarTotalesPrestamo(cuotas) {
        const marcadas = new Set(cuotasMarcadasCobradas());
        // Casilla del encabezado: marcada si todas lo están, a medias (indeterminada) si solo algunas.
        const todas = document.getElementById('nov_pre_cobradas_todas');
        if (todas) {
            todas.disabled = !cuotas.length;
            todas.checked = cuotas.length > 0 && marcadas.size === cuotas.length;
            todas.indeterminate = marcadas.size > 0 && marcadas.size < cuotas.length;
        }
        const total = cuotas.reduce((s, c) => s + c.valor, 0);
        const pendientes = cuotas.filter(c => !marcadas.has(c.numero));
        const porCobrar = pendientes.reduce((s, c) => s + c.valor, 0);
        document.getElementById('nov_pre_total').textContent = fmt2(total);
        document.getElementById('nov_pre_por_cobrar').textContent = fmt2(porCobrar);
        document.getElementById('nov_pre_por_cobrar_n').textContent = cuotas.length ? `${pendientes.length} cuota(s)` : '';

        // Si ya se cobraron cuotas, el dinero ya se entregó: el desembolso queda en "Ya fue entregado".
        const sel = document.getElementById('nov_pre_desembolso');
        const ayuda = document.getElementById('nov_pre_desembolso_ayuda');
        if (sel) {
            if (marcadas.size > 0) {
                if (!sel.disabled) sel.dataset.antes = sel.value;
                sel.value = 'entregado';
                sel.disabled = true;
            } else if (sel.disabled) {
                sel.disabled = false;
                sel.value = sel.dataset.antes || 'por_desembolsar';
            }
            if (ayuda) {
                ayuda.textContent = marcadas.size > 0
                    ? 'Hay cuotas ya cobradas: el préstamo ya se entregó.'
                    : (sel.value === 'entregado'
                        ? 'Se entregó fuera del sistema: el rol descuenta las cuotas directo, sin egreso.'
                        : 'Las cuotas se descuentan en el rol cuando el préstamo esté desembolsado por egreso.');
            }
        }
        // El texto de ayuda puede ocupar otra línea: reajustar el alto de la tabla.
        ajustarAltoPrestamo();
    }

    ['nov_pre_monto', 'nov_pre_cuotas', 'nov_periodo_anio'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => { if (modoActual === 'prestamo') renderCuotasPrestamo(); });
    });
    ['nov_periodo_mes', 'nov_periodo_anio'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => { if (modoActual === 'prestamo') renderCuotasPrestamo(); });
    });
    document.getElementById('nov_pre_desembolso')?.addEventListener('change', () => {
        actualizarTotalesPrestamo(calcularCuotasPrestamoActual());
    });
    document.getElementById('nov_tabla_prestamo')?.addEventListener('change', (ev) => {
        // Encabezado: marca o desmarca todas las cuotas de una vez.
        if (ev.target.id === 'nov_pre_cobradas_todas') {
            const marcar = ev.target.checked;
            document.querySelectorAll('#nov_tabla_prestamo tbody .nov-cuota-cobrada-chk').forEach(c => {
                c.checked = marcar;
                c.closest('tr')?.classList.toggle('nov-cuota-cobrada', marcar);
            });
            actualizarTotalesPrestamo(calcularCuotasPrestamoActual());
            return;
        }
        const chk = ev.target.closest('.nov-cuota-cobrada-chk');
        if (!chk) return;
        chk.closest('tr')?.classList.toggle('nov-cuota-cobrada', chk.checked);
        actualizarTotalesPrestamo(calcularCuotasPrestamoActual());
    });

    function calcularCuotasPrestamoActual() {
        const monto = parseFloat(String(document.getElementById('nov_pre_monto')?.value || '').replace(',', '.')) || 0;
        const n = parseInt(document.getElementById('nov_pre_cuotas')?.value || '', 10) || 0;
        if (n > 120) return [];
        return calcularCuotasPrestamo(monto, n,
            parseInt(document.getElementById('nov_periodo_mes')?.value || '', 10) || 0,
            parseInt(document.getElementById('nov_periodo_anio')?.value || '', 10) || 0);
    }

    function reiniciarPrestamo() {
        const m = document.getElementById('nov_pre_monto');
        const n = document.getElementById('nov_pre_cuotas');
        const sel = document.getElementById('nov_pre_desembolso');
        if (m) m.value = '';
        if (n) n.value = '';
        if (sel) { sel.disabled = false; sel.value = 'por_desembolsar'; delete sel.dataset.antes; }
        const tbody = document.querySelector('#nov_tabla_prestamo tbody');
        if (tbody) tbody.innerHTML = '';
        renderCuotasPrestamo();
    }

    async function guardarPrestamo() {
        if (guardandoPrestamo) return;
        guardandoPrestamo = true;
        const btn = document.getElementById('btnGuardarNov');
        let guardado = false;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
        try {
            const cuotas = calcularCuotasPrestamoActual();
            let falta = '';
            if (!document.getElementById('nov_id_empleado').value) falta = 'Seleccione el empleado que recibe el préstamo.';
            else if (!cuotas.length) falta = 'Escriba el monto a prestar y el número de cuotas (de 1 a 120).';
            else if (cuotasMarcadasCobradas().length >= cuotas.length) falta = 'Todas las cuotas están marcadas como ya cobradas: no queda nada por descontar.';
            if (falta) {
                Swal.fire({ icon: 'info', title: 'Falta información', text: falta });
                return;
            }
            const armar = (confirmar) => {
                const fd = new FormData();
                fd.append('id_empleado', document.getElementById('nov_id_empleado').value);
                fd.append('fecha', document.getElementById('nov_fecha').value);
                fd.append('monto', document.getElementById('nov_pre_monto').value);
                fd.append('num_cuotas', document.getElementById('nov_pre_cuotas').value);
                fd.append('periodo_mes', document.getElementById('nov_periodo_mes').value);
                fd.append('periodo_anio', document.getElementById('nov_periodo_anio').value);
                fd.append('aplica_en', document.getElementById('nov_aplica_en').value);
                fd.append('desembolso', document.getElementById('nov_pre_desembolso').value);
                fd.append('observacion', document.getElementById('nov_observacion').value);
                fd.append('cobradas', JSON.stringify(cuotasMarcadasCobradas()));
                fd.append('token_guardado', tokenPrestamo);
                if (confirmar) fd.append('confirmar_avisos', '1');
                return fd;
            };
            let resp = await fetch(`${urlModulo}/storePrestamo`, { method: 'POST', body: armar(false) });
            let json = await resp.json();
            if (!json.ok && json.requiere_confirmacion) {
                const r = await Swal.fire({
                    icon: 'warning',
                    title: 'Revise antes de guardar',
                    html: `<div class="small text-start">${(json.avisos || []).map(a => `<p class="mb-2">${esc(a)}</p>`).join('')}</div>`,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar de todos modos',
                    cancelButtonText: 'Cancelar'
                });
                if (!r.isConfirmed) return;
                resp = await fetch(`${urlModulo}/storePrestamo`, { method: 'POST', body: armar(true) });
                json = await resp.json();
            }
            if (json.ok) {
                guardado = true;
                const porDesembolsar = json.desembolso === 'por_desembolsar';
                await Swal.fire({
                    icon: json.ya_registrado ? 'info' : 'success',
                    title: json.ya_registrado ? 'Ya registrado' : 'Préstamo registrado',
                    html: `<p class="mb-2">${esc(json.msg)}</p>` + (porDesembolsar
                        ? `<p class="small text-muted mb-0">Para entregar el dinero, registre el egreso en <b>Egresos → Nómina</b>: el préstamo aparece como <b>PRÉSTAMO</b> del empleado por $ ${fmt2(json.monto)}. Las cuotas se descuentan en el rol cuando esté desembolsado.</p>`
                        : ''),
                    confirmButtonText: 'Aceptar'
                });
                // El botón queda desactivado hasta cerrar el modal: otro clic no vuelve a crear el préstamo.
                getModal()?.hide();
                window.dispatchEvent(new CustomEvent('novedadGuardada', { detail: json }));
            } else {
                Swal.fire({ icon: 'error', title: 'Atención', text: json.error || 'No se pudo guardar.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor. Vuelva a pulsar Guardar: si ya se había registrado, no se crea otra vez.' });
        } finally {
            guardandoPrestamo = false;
            if (!guardado) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
            }
        }
    }

    async function cargarGrid() {
        if (!tabla) return;
        gridCargada = true;
        tabla.tBodies[0].innerHTML = '<tr><td class="text-center text-muted py-3" colspan="50"><span class="spinner-border spinner-border-sm me-2"></span>Cargando empleados...</td></tr>';
        try {
            const resp = await fetch(`${urlModulo}/empleadosActivosAjax`);
            const json = await resp.json();
            if (!json.ok) throw new Error(json.error || '');
            renderGrid(json.data || []);
        } catch (e) {
            gridCargada = false;
            tabla.tBodies[0].innerHTML = '<tr><td class="text-center text-danger py-3" colspan="50">No se pudo cargar el listado de empleados. Vuelva a elegir «Todos los empleados».</td></tr>';
        }
    }

    function renderGrid(empleados) {
        const tipos = cat().tipos || [];
        const motivos = cat().motivos || [];
        let h = '<tr><th class="nov-col-emp">Empleado</th>';
        tipos.forEach(t => {
            const cod = String(t.codigo);
            let extra = '';
            if (esIessTipo(cod)) {
                extra = `<select class="form-select nov-m-todos mt-1" data-tipo="${esc(cod)}" title="Aplicar a todos los empleados">
                            <option value="">IESS…</option><option value="si">Con IESS</option><option value="no">Sin IESS</option></select>`;
            } else if (esEntregaTipo(cod)) {
                extra = `<select class="form-select nov-m-todos mt-1" data-tipo="${esc(cod)}" title="Aplicar a todos los empleados">
                            <option value="">Pago…</option><option value="por">Por pagar</option><option value="ya">Ya pagado</option></select>`;
            }
            const unidad = esAvisoTipo(cod) ? 'motivo' : unidadCorta(cod);
            h += `<th class="text-center">${esc(t.nombre)}<div class="small text-muted fw-normal">(${esc(unidad)})</div>${extra}</th>`;
        });
        // Observación del empleado: última columna.
        tabla.tHead.innerHTML = h + '<th>Observación<div class="small text-muted fw-normal">(vacía: «Tipo - Mes Año»)</div></th></tr>';

        const optMotivos = '<option value="">—</option>' + motivos.map(m => `<option value="${esc(m.codigo)}">${esc(m.nombre)}</option>`).join('');
        let b = '';
        empleados.forEach(e => {
            const buscar = `${e.nombres_apellidos} ${e.identificacion}`.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
            b += `<tr data-emp="${e.id}" data-buscar="${esc(buscar)}">
                    <td class="nov-col-emp" title="${esc(e.nombres_apellidos)}">${esc(e.nombres_apellidos)}</td>`;
            tipos.forEach(t => {
                const cod = String(t.codigo);
                if (esAvisoTipo(cod)) {
                    b += `<td class="p-0" data-tipo="${esc(cod)}"><select class="form-select nov-m-motivo" aria-label="Motivo de salida">${optMotivos}</select></td>`;
                    return;
                }
                let sel = '';
                if (esIessTipo(cod)) {
                    sel = e.aporta_iess
                        ? '<select class="form-select nov-m-sel" aria-label="Aporta IESS"><option value="si">Con IESS</option><option value="no">Sin IESS</option></select>'
                        : '<select class="form-select nov-m-sel nov-m-fijo" disabled title="El empleado no aporta al IESS"><option value="no">Sin IESS</option></select>';
                } else if (esEntregaTipo(cod)) {
                    sel = '<select class="form-select nov-m-sel" aria-label="Pago"><option value="por">Por pagar</option><option value="ya">Ya pagado</option></select>';
                }
                b += `<td class="p-0" data-tipo="${esc(cod)}"><div class="d-flex"><input type="number" step="0.01" min="0" class="form-control nov-m-valor" aria-label="${esc(t.nombre)}">${sel}</div></td>`;
            });
            b += `<td class="p-0"><input type="text" class="form-control nov-m-obs" maxlength="250" placeholder="Tipo - Mes Año" aria-label="Observación de ${esc(e.nombres_apellidos)}"></td></tr>`;
        });
        tabla.tBodies[0].innerHTML = b || `<tr><td class="text-center text-muted py-3" colspan="${tipos.length + 2}">La empresa no tiene empleados activos.</td></tr>`;

        let f = '<tr><td class="nov-col-emp">Totales</td>';
        tipos.forEach(t => { f += `<td class="text-end pe-2" data-total="${esc(String(t.codigo))}"></td>`; });
        tabla.tFoot.innerHTML = f + '<td></td></tr>';

        document.getElementById('nov_masivo_info').textContent = `${empleados.length} empleado(s) activo(s)`;
        const buscar = document.getElementById('nov_masivo_buscar');
        if (buscar) buscar.value = '';
        actualizarResumen();
    }

    // Valor numérico de una celda ('' o 0 = sin novedad).
    const valorCelda = (inp) => {
        const v = parseFloat(String(inp?.value ?? '').replace(',', '.'));
        return isNaN(v) ? 0 : v;
    };

    function actualizarResumen() {
        if (!tabla) return;
        const totales = {};
        let n = 0;
        tabla.querySelectorAll('tbody td[data-tipo]').forEach(td => {
            const cod = td.dataset.tipo;
            let con = false;
            if (esAvisoTipo(cod)) {
                con = !!td.querySelector('.nov-m-motivo')?.value;
                if (con) totales[cod] = (totales[cod] || 0) + 1;
            } else {
                const v = valorCelda(td.querySelector('.nov-m-valor'));
                con = v !== 0;
                if (con) totales[cod] = (totales[cod] || 0) + v;
            }
            td.classList.toggle('nov-con-valor', con);
            if (con) n++;
        });
        tabla.querySelectorAll('tfoot td[data-total]').forEach(td => {
            const cod = td.dataset.total;
            const t = totales[cod];
            if (!t) { td.textContent = ''; return; }
            td.textContent = esAvisoTipo(cod) ? `${t}` : (unidadCorta(cod) === '$' ? `$ ${t.toFixed(2)}` : `${+t.toFixed(2)}`);
        });
        const cont = document.getElementById('nov_masivo_contador');
        if (cont) cont.textContent = n === 1 ? '1 novedad por registrar' : `${n} novedades por registrar`;
    }

    if (tabla) {
        tabla.addEventListener('input', actualizarResumen);
        tabla.addEventListener('change', (ev) => {
            // Selector del encabezado: aplica la marca (IESS / pago) a todos los empleados.
            const todos = ev.target.closest('.nov-m-todos');
            if (todos) {
                if (todos.value) {
                    tabla.querySelectorAll(`tbody td[data-tipo="${CSS.escape(todos.dataset.tipo)}"] .nov-m-sel:not(.nov-m-fijo)`)
                        .forEach(s => { s.value = todos.value; });
                }
                todos.value = '';
            }
            actualizarResumen();
        });
    }
    document.getElementById('nov_masivo_buscar')?.addEventListener('input', (ev) => {
        const q = ev.target.value.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        const palabras = q.split(/\s+/).filter(Boolean);
        tabla?.querySelectorAll('tbody tr[data-emp]').forEach(tr => {
            const txt = tr.dataset.buscar || '';
            tr.classList.toggle('d-none', !palabras.every(p => txt.includes(p)));
        });
    });

    // Una novedad por cada celda con valor (o con motivo, en el Aviso de salida).
    function recogerItems() {
        const items = [];
        tabla?.querySelectorAll('tbody tr[data-emp]').forEach(tr => {
            const idEmp = parseInt(tr.dataset.emp, 10);
            // Observación del empleado: vale para todas sus novedades (vacía = "Tipo - Mes Año").
            const obs = (tr.querySelector('.nov-m-obs')?.value || '').trim();
            tr.querySelectorAll('td[data-tipo]').forEach(td => {
                const cod = td.dataset.tipo;
                if (esAvisoTipo(cod)) {
                    const motivo = td.querySelector('.nov-m-motivo')?.value || '';
                    if (motivo) items.push({ id_empleado: idEmp, tipo_codigo: cod, valor: 0, motivo_codigo: motivo, observacion: obs });
                    return;
                }
                const v = valorCelda(td.querySelector('.nov-m-valor'));
                if (v === 0) return;
                const sel = td.querySelector('.nov-m-sel');
                const it = { id_empleado: idEmp, tipo_codigo: cod, valor: v, observacion: obs };
                if (esIessTipo(cod)) it.aporta_iess = sel ? sel.value : null;
                if (esEntregaTipo(cod)) it.ya_pagado = !!sel && sel.value === 'ya';
                items.push(it);
            });
        });
        return items;
    }

    function htmlErrores(errores) {
        const filas = errores.map(e => `<tr><td>${esc(e.empleado)}</td><td>${esc(e.novedad || '—')}</td><td>${esc(e.error)}</td></tr>`).join('');
        return `<div class="small text-start" style="max-height:50vh;overflow:auto;">
                    <table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Empleado</th><th>Novedad</th><th>Error</th></tr></thead><tbody>${filas}</tbody></table>
                </div>`;
    }

    async function guardarMasivo() {
        if (guardandoMasivo) return;
        guardandoMasivo = true;
        const btn = document.getElementById('btnGuardarNov');
        let guardado = false;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
        try {
            const items = recogerItems();
            if (!items.length) {
                Swal.fire({ icon: 'info', title: 'Sin novedades', text: 'Escriba el valor en la columna de la novedad que corresponde a cada empleado.' });
                return;
            }
            const armar = (confirmar) => {
                const fd = new FormData();
                fd.append('fecha', document.getElementById('nov_fecha').value);
                fd.append('periodo_mes', document.getElementById('nov_periodo_mes').value);
                fd.append('periodo_anio', document.getElementById('nov_periodo_anio').value);
                fd.append('aplica_en', document.getElementById('nov_aplica_en').value);
                fd.append('items', JSON.stringify(items));
                fd.append('token_guardado', tokenMasivo);
                if (confirmar) fd.append('confirmar_avisos', '1');
                return fd;
            };
            let resp = await fetch(`${urlModulo}/storeMasivo`, { method: 'POST', body: armar(false) });
            let json = await resp.json();
            if (!json.ok && json.requiere_confirmacion) {
                const r = await Swal.fire({
                    icon: 'warning',
                    title: 'Revise antes de guardar',
                    html: `<div class="small text-start" style="max-height:50vh;overflow:auto;">${(json.avisos || []).map(a => `<p class="mb-2">${esc(a)}</p>`).join('')}</div>`,
                    width: 700,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar de todos modos',
                    cancelButtonText: 'Cancelar'
                });
                if (!r.isConfirmed) return;
                resp = await fetch(`${urlModulo}/storeMasivo`, { method: 'POST', body: armar(true) });
                json = await resp.json();
            }
            if (json.ok) {
                guardado = true;
                Swal.fire({ icon: json.ya_registrado ? 'info' : 'success', title: json.ya_registrado ? 'Ya registradas' : 'Guardadas', text: json.msg, timer: 1800, showConfirmButton: false });
                // El botón queda desactivado hasta cerrar el modal: otro clic no vuelve a crear el lote.
                setTimeout(() => {
                    getModal()?.hide();
                    window.dispatchEvent(new CustomEvent('novedadGuardada', { detail: json }));
                }, 1800);
            } else if (json.errores && json.errores.length) {
                Swal.fire({ icon: 'error', title: 'No se registró ninguna novedad', html: htmlErrores(json.errores), width: 900 });
            } else {
                Swal.fire({ icon: 'error', title: 'Atención', text: json.error || 'No se pudo guardar.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor. Vuelva a pulsar Guardar: si ya se habían registrado, no se crean otra vez.' });
        } finally {
            guardandoMasivo = false;
            if (!guardado) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
            }
        }
    }

    // Al cerrar el modal, el botón vuelve a su estado (tras un guardado masivo quedó desactivado).
    document.getElementById('modalNovedad')?.addEventListener('hidden.bs.modal', () => {
        const btn = document.getElementById('btnGuardarNov');
        if (btn && !guardandoMasivo && !guardandoPrestamo) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar'; }
    });

    window.abrirModalCrear = function () {
        if (!form) return;
        form.reset();
        novLimpiarBloqueoSoloLectura();
        // Nueva apertura: cuadrícula y clave de guardado nuevas.
        gridCargada = false;
        if (tabla) { tabla.tHead.innerHTML = ''; tabla.tBodies[0].innerHTML = ''; tabla.tFoot.innerHTML = ''; }
        tokenMasivo = typeof window.CMG_nuevoTokenGuardado === 'function' ? window.CMG_nuevoTokenGuardado() : '';
        tokenPrestamo = typeof window.CMG_nuevoTokenGuardado === 'function' ? window.CMG_nuevoTokenGuardado() : '';
        reiniciarPrestamo();
        document.getElementById('nov_prestamo_info')?.classList.add('d-none');
        document.getElementById('nov_modo_wrap')?.classList.remove('d-none');
        modoActual = 'uno';
        setModo('uno');
        document.getElementById('nov_id').value = '';
        document.getElementById('tituloModalNov').textContent = 'Nueva Novedad';
        document.getElementById('btnEliminarNov')?.classList.add('d-none');
        // Nueva novedad: "Aporta IESS" arranca en Sí (lo habitual en bonos, comisiones y horas).
        novReiniciarIess('si');
        novReiniciarAplica();
        // Anticipo / Quincena / Préstamo Empresa nuevos: por pagar, como siempre.
        const selPagoNuevo = document.getElementById('nov_ya_pagado');
        if (selPagoNuevo) selPagoNuevo.value = 'por';
        setEmpleado('', '');
        obsAuto = '';
        // Toda novedad nace activa: el selector se muestra pero en solo lectura.
        const selEstadoNuevo = document.getElementById('nov_estado');
        selEstadoNuevo.value = 'activo';
        selEstadoNuevo.disabled = true;
        // Precargar valores favoritos (tipo, afecta a) y sincronizar dependientes.
        if (typeof window.aplicarFavoritosModal === 'function') window.aplicarFavoritosModal('#modalNovedad');
        window.novToggleCampos();
        actualizarObservacion();
        getModal()?.show();
    };

    window.abrirModalEditar = async function (tr) {
        const rowData = (tr instanceof HTMLElement) ? JSON.parse(tr.dataset.row) : tr;
        const id = rowData.id;
        if (!form || !id) return;

        form.reset();
        novLimpiarBloqueoSoloLectura();
        document.getElementById('nov_id').value = id;
        document.getElementById('tituloModalNov').textContent = 'Editar Novedad';
        // Editar es siempre una sola novedad: sin el selector de modo.
        document.getElementById('nov_modo_wrap')?.classList.add('d-none');
        document.getElementById('nov_prestamo_info')?.classList.add('d-none');
        setModo('uno');
        document.getElementById('btnEliminarNov')?.classList.remove('d-none');
        // En edición sí se puede cambiar el estado (activar / anular).
        document.getElementById('nov_estado').disabled = false;
        getModal()?.show();

        // Mostrar loader: la carga completa vía AJAX puede tardar y sin esto el
        // usuario ve el modal "vacío" y piensa que la novedad no tiene datos.
        document.getElementById('nov-modal-loader')?.classList.remove('d-none');

        try {
            const resp = await fetch(`${urlModulo}/getDetalleAjax?id=${id}`);
            const res = await resp.json();
            if (!res.ok) return;
            const d = res.data;

            // Marca guardada; sin marca (novedad anterior a la opción), la del tipo:
            // horas sí, Otros Ingresos no — lo mismo que aplica el rol.
            const porDefecto = !!((window.NOVEDAD_CATALOGO || {}).iess_por_defecto || {})[d.tipo_codigo];
            const marca = (d.aporta_iess === null || d.aporta_iess === undefined || d.aporta_iess === '') ? porDefecto : esSi(d.aporta_iess);
            novReiniciarIess(marca ? 'si' : 'no');
            novReiniciarAplica();

            setEmpleado(d.id_empleado || '', d.empleado_nombre ? `${d.empleado_nombre} (${d.empleado_identificacion || ''})` : '', !esNo(d.empleado_aporta_iess));
            document.getElementById('nov_estado').value = d.estado || 'activo';
            document.getElementById('nov_tipo_codigo').value = d.tipo_codigo || '';
            document.getElementById('nov_fecha').value = d.fecha || '';
            document.getElementById('nov_periodo_mes').value = String(parseInt(d.periodo_mes, 10) || '');
            document.getElementById('nov_periodo_anio').value = d.periodo_anio || '';
            document.getElementById('nov_aplica_en').value = d.aplica_en || 'rol';
            document.getElementById('nov_observacion').value = d.observacion || '';
            // Marca de entrega guardada (desembolsado_migrado): ya pagado / por pagar.
            const selPago = document.getElementById('nov_ya_pagado');
            if (selPago) selPago.value = esSi(d.desembolsado_migrado) ? 'ya' : 'por';

            window.novToggleCampos();

            // Valores dependientes del tipo, tras el toggle.
            document.getElementById('nov_valor').value = d.valor != null ? d.valor : '0';
            if (d.motivo_codigo) document.getElementById('nov_motivo_codigo').value = d.motivo_codigo;

            // Sincroniza el "auto" con lo cargado: si la observación coincide con el
            // texto autogenerado, seguirá sincronizándose; si es manual, se respeta.
            obsAuto = textoObsAuto();

            // Cuota de un Préstamo Empresa registrado con tabla de cuotas.
            if (d.prestamo) {
                const p = d.prestamo;
                document.getElementById('nov_prestamo_info_texto').textContent =
                    `Cuota del Préstamo Empresa N° ${p.id}: $ ${fmt2(p.monto)} en ${p.num_cuotas} cuota(s); ${p.cuotas_vigentes} vigente(s) por $ ${fmt2(p.total_vigente)}. `
                    + (p.desembolso === 'entregado' ? 'Ya fue entregado.' : 'Se desembolsa en Egresos → Nómina.');
                document.getElementById('nov_prestamo_info')?.classList.remove('d-none');
            }

            novAplicarSoloLectura(!!d.bloqueada);
        } catch (e) {
        } finally {
            document.getElementById('nov-modal-loader')?.classList.add('d-none');
        }
    };

    if (form) {
        form.addEventListener('submit', async () => {
            const id = document.getElementById('nov_id').value;
            if (!id && modoActual === 'todos') { guardarMasivo(); return; }
            if (!id && modoActual === 'prestamo') { guardarPrestamo(); return; }
            const btn = document.getElementById('btnGuardarNov');
            const url = id ? `${urlModulo}/update` : `${urlModulo}/store`;
            const restaurar = () => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar'; };

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

            try {
                let resp = await fetch(url, { method: 'POST', body: new FormData(form) });
                let json = await resp.json();
                // Avisos que no impiden guardar (quincena con valor en la ficha, concepto repetido
                // en el mes): si el usuario acepta, se reenvía con la confirmación.
                if (!json.ok && json.requiere_confirmacion) {
                    const r = await Swal.fire({
                        icon: 'warning',
                        title: 'Revise antes de guardar',
                        html: `<div class="small text-start">${(json.avisos || []).map(a => `<p class="mb-2">${String(a).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]))}</p>`).join('')}</div>`,
                        showCancelButton: true,
                        confirmButtonText: 'Guardar de todos modos',
                        cancelButtonText: 'Cancelar'
                    });
                    if (!r.isConfirmed) { restaurar(); return; }
                    const fd = new FormData(form);
                    fd.append('confirmar_avisos', '1');
                    resp = await fetch(url, { method: 'POST', body: fd });
                    json = await resp.json();
                }
                if (json.ok) {
                    Swal.fire({ icon: 'success', title: id ? 'Actualizada' : 'Guardada', text: json.msg || 'Novedad guardada.', timer: 1500, showConfirmButton: false });
                    setTimeout(() => {
                        restaurar();
                        getModal()?.hide();
                        window.dispatchEvent(new CustomEvent('novedadGuardada', { detail: json }));
                    }, 1500);
                } else {
                    Swal.fire({ icon: 'error', title: 'Atención', text: json.error || 'No se pudo guardar.' });
                    restaurar();
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
                restaurar();
            }
        });
    }

    // Datos del Préstamo Empresa al que pertenece la novedad (null si no es cuota de uno).
    async function prestamoDeNovedad(id) {
        try {
            const resp = await fetch(`${urlModulo}/getDetalleAjax?id=${encodeURIComponent(id)}`);
            const json = await resp.json();
            return (json.ok && json.data && json.data.prestamo) ? json.data.prestamo : null;
        } catch (e) {
            return null;
        }
    }

    async function eliminarConSwal(id, cerrarModal) {
        if (!id) return;

        // Cuota de un Préstamo Empresa: ofrecer eliminar todas las cuotas del préstamo
        // (p. ej. se registró por error o el empleado ya no lo quiere), si no está pagado.
        const p = await prestamoDeNovedad(id);
        let accion = 'una'; // 'una' (solo esta novedad) o 'prestamo' (todas sus cuotas)
        if (p && p.eliminable && p.cuotas_vigentes > 1) {
            const r = await Swal.fire({
                title: 'Esta cuota es de un préstamo',
                html: `<p class="mb-2">Pertenece al <b>Préstamo Empresa N° ${p.id}</b> de $ ${fmt2(p.monto)}, que tiene <b>${p.cuotas_vigentes} cuotas</b> registradas por $ ${fmt2(p.total_vigente)}.</p>`
                    + '<p class="mb-0">¿Desea eliminar todos los registros de este préstamo? No podrá revertir esta acción.</p>',
                icon: 'warning',
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonColor: '#d33',
                denyButtonColor: '#6c757d',
                confirmButtonText: `Eliminar las ${p.cuotas_vigentes} cuotas`,
                denyButtonText: 'Solo esta cuota',
                cancelButtonText: 'Cancelar'
            });
            if (r.isDismissed) return;
            accion = r.isConfirmed ? 'prestamo' : 'una';
        } else {
            const avisoPrestamo = p && !p.eliminable && p.cuotas_vigentes > 1
                ? `<p class="small text-start mb-2">Pertenece al Préstamo Empresa N° ${p.id}, que no se puede eliminar completo: ${esc(p.motivo || '')}</p>`
                : '';
            const result = await Swal.fire({
                title: '¿Está seguro?',
                html: avisoPrestamo + 'No podrá revertir esta acción.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });
            if (!result.isConfirmed) return;
            // Última cuota vigente del préstamo: se elimina con él (cabecera incluida).
            if (p && p.eliminable && p.cuotas_vigentes === 1) accion = 'prestamo';
        }

        try {
            const fd = new FormData();
            let url = `${urlModulo}/delete`;
            if (accion === 'prestamo') {
                fd.append('id_prestamo', p.id);
                url = `${urlModulo}/eliminarPrestamo`;
            } else {
                fd.append('id_eliminar', id);
            }
            const resp = await fetch(url, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                Swal.fire({ icon: 'success', title: 'Eliminada', text: json.msg || 'Novedad eliminada.', timer: 1500, showConfirmButton: false });
                if (cerrarModal) getModal()?.hide();
                window.dispatchEvent(new CustomEvent('novedadGuardada', { detail: json }));
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'No se pudo eliminar.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
        }
    }

    window.eliminarRegistro = function (id) { eliminarConSwal(id, false); };
    window.eliminarNovedadModal = function () { eliminarConSwal(document.getElementById('nov_id').value, true); };

})(window, document);
