/**
 * Lógica compartida para el Modal de Empleados
 */

(function (window, document) {
    'use strict';

    const urlModuloEmp = BASE_URL + '/modulos/empleados';
    let modalInstEmp = null;
    const formEmp = document.getElementById('formEmpleado');
    const alertElEmp = document.getElementById('modalAlert');

    // Opciones para la pestaña Horario (turnos + puntos). Se refrescan en cada apertura
    // del modal para reflejar los turnos creados recientemente (no se cachean de forma fija).
    let horarioOpts = { horarios: [], puntos: [] };
    async function cargarOpcionesHorario() {
        try {
            const resp = await fetch(`${urlModuloEmp}/opcionesHorarioAjax?_=${Date.now()}`);
            const j = await resp.json();
            horarioOpts = j.ok ? { horarios: j.horarios || [], puntos: j.puntos || [] } : { horarios: [], puntos: [] };
        } catch (e) {
            horarioOpts = { horarios: [], puntos: [] };
        }
        return horarioOpts;
    }

    function getModalEmp() {
        if (!modalInstEmp && typeof bootstrap !== 'undefined') {
            const el = document.getElementById('modalEmpleado');
            if (el) modalInstEmp = new bootstrap.Modal(el);
        }
        return modalInstEmp;
    }

    window.abrirModalCrear = async function() {
        if (!formEmp) return;
        formEmp.reset();
        limpiarBadgeSriEmp();
        document.getElementById('emp_id').value = '';
        document.getElementById('tituloModal').textContent = 'Nuevo Empleado';
        document.getElementById('btnEliminarModal')?.classList.add('d-none');
        document.querySelector('#tablaPeriodos tbody').innerHTML = '';
        document.querySelector('#tablaRubros tbody').innerHTML = '';
        const tAsg = document.querySelector('#tablaAsignaciones tbody'); if (tAsg) tAsg.innerHTML = '';
        const tGp = document.querySelector('#tablaGastosPersonales tbody'); if (tGp) tGp.innerHTML = '';
        const asgJson = document.getElementById('asignaciones_horario_json'); if (asgJson) asgJson.value = '[]';
        cargarOpcionesHorario(); // refresca turnos/puntos (fire-and-forget; se re-consulta al agregar fila)
        if (alertElEmp) alertElEmp.classList.add('d-none');

        // Cargar defaults IESS
        try {
            const resp = await fetch(`${urlModuloEmp}/getIessDefaults`);
            const res = await resp.json();
            if (res.ok) {
                document.getElementById('emp_aporte_personal').value = res.data.aporte_personal;
                document.getElementById('emp_aporte_patronal').value = res.data.aporte_patronal;
                document.getElementById('emp_sueldo_base').value = res.data.sbu || 0;
            }
        } catch (e) {}

        const tabGenBtn = document.getElementById('tab-general-btn');
        if (tabGenBtn && typeof bootstrap !== 'undefined') {
            (bootstrap.Tab.getInstance(tabGenBtn) || new bootstrap.Tab(tabGenBtn)).show();
        }

        if (typeof window.aplicarFavoritosModal === 'function') {
            window.aplicarFavoritosModal('#modalEmpleado');
            window.toggleIessFields();
        }

        getModalEmp()?.show();
    };

    window.abrirModalEditar = async function(tr) {
        const rowData = (tr instanceof HTMLElement) ? JSON.parse(tr.dataset.row) : tr;
        const id = rowData.id;
        if (!formEmp || !id) return;

        formEmp.reset();
        limpiarBadgeSriEmp();
        document.getElementById('emp_id').value = id;
        document.getElementById('tituloModal').textContent = 'Editar Empleado';
        document.getElementById('btnEliminarModal')?.classList.remove('d-none');
        if (alertElEmp) alertElEmp.classList.add('d-none');

        document.querySelector('#tablaPeriodos tbody').innerHTML = '<tr><td colspan="4" class="text-center py-2 small text-muted">Cargando...</td></tr>';
        document.querySelector('#tablaRubros tbody').innerHTML = '<tr><td colspan="5" class="text-center py-2 small text-muted">Cargando...</td></tr>';

        const tabGenBtn = document.getElementById('tab-general-btn');
        if (tabGenBtn && typeof bootstrap !== 'undefined') {
            (bootstrap.Tab.getInstance(tabGenBtn) || new bootstrap.Tab(tabGenBtn)).show();
        }
        getModalEmp()?.show();

        try {
            const resp = await fetch(`${urlModuloEmp}/getDetalleAjax?id=${id}`);
            const res = await resp.json();
            if (res.ok) {
                const d = res.data;
                // General
                document.getElementById('emp_tipo_id').value = d.tipo_id;
                document.getElementById('emp_identificacion').value = d.identificacion;
                document.getElementById('emp_nombres_apellidos').value = d.nombres_apellidos;
                document.getElementById('emp_email').value = d.email || '';
                document.getElementById('emp_telefono').value = d.telefono || '';
                document.getElementById('emp_direccion').value = d.direccion || '';
                document.getElementById('emp_contacto_emergencia').value = d.contacto_emergencia || '';
                document.getElementById('emp_fecha_nacimiento').value = d.fecha_nacimiento || '';
                document.getElementById('emp_sexo').value = d.sexo || 'M';
                document.getElementById('emp_estado').value = d.estado || 'activo';

                // Laboral
                document.getElementById('emp_fondos_reserva').value = d.fondos_reserva || 'no_se_paga';
                document.getElementById('emp_aporta_iess').value = d.aporta_iess ? 'si' : 'no';
                document.getElementById('emp_decimo_tercero').value = d.decimo_tercero || 'acumula';
                document.getElementById('emp_decimo_cuarto').value = d.decimo_cuarto || 'acumula';
                document.getElementById('emp_aporte_personal').value = d.aporte_personal || 0;
                document.getElementById('emp_aporte_patronal').value = d.aporte_patronal || 0;
                document.getElementById('emp_sueldo_base').value = d.sueldo_base || 0;
                document.getElementById('emp_valor_semanal').value = d.valor_semanal || 0;
                document.getElementById('emp_valor_quincena').value = d.valor_quincena || 0;
                window.toggleIessFields();

                // Imp. Renta
                var _excIr = document.getElementById('emp_excluir_calculo_ir');
                if (_excIr) _excIr.value = d.excluir_calculo_ir ? 'si' : 'no';

                // Puesto
                document.getElementById('emp_region').value = d.region || 'costa';
                document.getElementById('emp_departamento').value = d.departamento || '';
                document.getElementById('emp_cargo').value = d.cargo || '';
                document.getElementById('emp_lugar_trabajo').value = d.lugar_trabajo || '';
                document.getElementById('emp_codigo_sectorial_iess').value = d.codigo_sectorial_iess || '';
                var _atr = document.getElementById('emp_atraso_modo'); if (_atr) _atr.value = d.atraso_modo || 'no_descuenta';

                // Bancarios
                document.getElementById('emp_id_banco_ecuador').value = d.id_banco_ecuador || 0;
                document.getElementById('emp_tipo_cuenta').value = d.tipo_cuenta || '';
                document.getElementById('emp_numero_cuenta').value = d.numero_cuenta || '';

                // Tablas
                const tPeriodos = document.querySelector('#tablaPeriodos tbody');
                tPeriodos.innerHTML = '';
                if (d.periodos?.length) d.periodos.forEach(p => window.agregarFilaPeriodo(p));

                const tRubros = document.querySelector('#tablaRubros tbody');
                tRubros.innerHTML = '';
                if (d.rubros?.length) d.rubros.forEach(r => window.agregarFilaRubro(r));

                // Asignaciones de horario: cargar opciones (turnos/puntos) y renderizar la grilla.
                const tAsg = document.querySelector('#tablaAsignaciones tbody');
                if (tAsg) {
                    tAsg.innerHTML = '';
                    await cargarOpcionesHorario();
                    if (d.asignaciones_horario?.length) d.asignaciones_horario.forEach(a => window.agregarFilaAsignacion(a));
                }

                // Proyección de gastos personales (form. SRI-GP), un registro por año.
                const tGp = document.querySelector('#tablaGastosPersonales tbody');
                if (tGp) {
                    tGp.innerHTML = '';
                    if (d.gastos_personales?.length) d.gastos_personales.forEach(g => window.agregarFilaGasto(g));
                }
            }
        } catch (e) {}
    };

    window.toggleIessFields = async function() {
        const aporta = document.getElementById('emp_aporta_iess')?.value;
        const cols = document.querySelectorAll('.col-aporte-iess');
        const fields = document.querySelectorAll('.iess-field');
        if (!cols.length) return;

        if (aporta === 'no') {
            cols.forEach(c => c.classList.add('d-none'));
            fields.forEach(f => { f.value = '0'; f.readOnly = true; });
        } else {
            cols.forEach(c => c.classList.remove('d-none'));
            fields.forEach(f => { f.readOnly = false; });
            const vPers = parseFloat(document.getElementById('emp_aporte_personal')?.value || '0');
            if (vPers === 0) {
                try {
                    const resp = await fetch(`${urlModuloEmp}/getIessDefaults`);
                    const res = await resp.json();
                    if (res.ok) {
                        document.getElementById('emp_aporte_personal').value = res.data.aporte_personal;
                        document.getElementById('emp_aporte_patronal').value = res.data.aporte_patronal;
                    }
                } catch (e) {}
            }
        }
        document.querySelectorAll('#tablaRubros tbody tr .rb-tipo').forEach(sel => window.toggleRubroIess(sel));
    };

    /**
     * Proyección informativa de retención de Impuesto a la Renta (relación de
     * dependencia) con el sueldo/aporte actuales del formulario.
     *
     * La caja de resultados es markup estático (mismo formato que los totales de
     * la factura de venta): aquí solo se actualizan los valores. Así la pantalla
     * NO se mueve mientras se escriben los gastos personales.
     *
     * Solo lectura: el valor que realmente se retiene lo calcula el rol de pagos.
     */
    let irTimer = null;
    let irPeticion = 0;

    /** Dispara el recálculo con debounce (evita una petición por tecla). */
    window.empIrCargar = function() {
        clearTimeout(irTimer);
        irTimer = setTimeout(empIrCalcular, 300);
    };

    async function empIrCalcular() {
        const lbl = id => document.getElementById(id);
        if (!lbl('ir-lbl-mensual')) return;

        const sueldo = parseFloat(lbl('emp_sueldo_base')?.value || '0');
        const aportePer = parseFloat(lbl('emp_aporte_personal')?.value || '9.45');
        const excluido = lbl('emp_excluir_calculo_ir')?.value === 'si' ? 1 : 0;

        const spinner = lbl('empIrSpinner');
        if (spinner) spinner.classList.remove('d-none');

        const miPeticion = ++irPeticion;

        try {
            const anioActual = new Date().getFullYear();
            const params = new URLSearchParams({ sueldo_base: sueldo, aporte_personal: aportePer, excluir_calculo_ir: excluido });
            params.set('id_empleado', lbl('emp_id')?.value || '0');
            // Proyección tal como está en pantalla (aunque no se haya guardado aún).
            const fila = gpFilaAnio(anioActual);
            if (fila) {
                params.set('gasto_personal_proyectado', String(fila.total));
                params.set('cargas_familiares', String(fila.cargas));
                params.set('caso_especial', fila.especial ? '1' : '0');
            }

            const resp = await fetch(`${urlModuloEmp}/proyeccionIrAjax?${params}`);
            const r = await resp.json();

            // Respuesta obsoleta (llegó después de una petición más nueva): descartar.
            if (miPeticion !== irPeticion) return;
            if (!r.ok) { empIrAviso('danger', 'No se pudo calcular la proyección.'); return; }

            const money = v => Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            lbl('ir-lbl-ingreso').textContent = money(r.ingreso_gravado_anual);
            lbl('ir-lbl-iess').textContent    = money(r.aporte_iess_anual);
            lbl('ir-lbl-base').textContent    = money(r.base_imponible_anual);
            lbl('ir-lbl-causado').textContent = money(r.impuesto_causado);
            lbl('ir-lbl-rebaja').textContent  = money(r.rebaja_gastos_personales);
            lbl('ir-lbl-pct').textContent     = `(${Number(r.porcentaje_rebaja).toFixed(0)}%)`;
            lbl('ir-lbl-anual').textContent   = money(r.retencion_anual);
            lbl('ir-lbl-mensual').textContent = money(r.retencion_mensual);

            // Tope de la fila del año en curso (columna "Tope" de la tabla).
            const filaAnio = gpFilaAnio(r.anio);
            if (filaAnio) {
                const celda = filaAnio.tr.querySelector('.gp-tope');
                if (celda) celda.textContent = r.gasto_personal_tope > 0 ? money(r.gasto_personal_tope) : 'sin tope';
            }

            // Nota bajo la rebaja: de dónde sale el valor.
            const nota = lbl('ir-lbl-gasto-nota');
            if (r.gasto_personal_proyectado === null) {
                nota.textContent = 'sin proyección presentada';
                nota.className = 'text-end text-muted mb-1';
            } else if (r.rebaja_limitada_por_impuesto) {
                nota.textContent = 'la rebaja no puede superar el impuesto causado';
                nota.className = 'text-end text-warning-emphasis mb-1';
            } else if (r.gasto_personal_topado) {
                nota.textContent = `proyectó ${money(r.gasto_personal_proyectado)}, topado a ${money(r.gasto_personal_tope)}`;
                nota.className = 'text-end text-warning-emphasis mb-1';
            } else if (!r.parametros_configurados) {
                nota.textContent = 'sin canasta básica configurada para ' + r.anio;
                nota.className = 'text-end text-warning-emphasis mb-1';
            } else {
                const detalle = r.caso_especial
                    ? 'caso especial'
                    : `${r.cargas_familiares} carga${r.cargas_familiares === 1 ? '' : 's'}`;
                nota.textContent = `${Number(r.porcentaje_rebaja).toFixed(0)}% de ${money(r.gasto_personal_base_rebaja)} · tope ${money(r.gasto_personal_tope)} (${detalle})`;
                nota.className = 'text-end text-muted mb-1';
            }
            nota.style.fontSize = '0.68rem';

            // Avisos: se arman todos juntos para que la altura del bloque cambie una sola vez.
            let avisos = '';
            if (!r.tramos_cargados) {
                avisos += `<div class="alert alert-warning small py-2 mb-2"><i class="bi bi-exclamation-triangle me-1"></i>
                    No hay tabla de tramos de Impuesto a la Renta cargada para ${r.anio}. La retención se muestra en 0.00
                    hasta que se configure en <strong>Config &rarr; Tramos de Impuesto a la Renta</strong>.</div>`;
            }
            if (!r.parametros_configurados) {
                avisos += `<div class="alert alert-warning small py-2 mb-2"><i class="bi bi-exclamation-triangle me-1"></i>
                    No está configurada la <strong>canasta familiar básica</strong> de ${r.anio}, así que la rebaja se calcula
                    sobre la proyección completa sin tope. Cárguela en <strong>Config &rarr; Tramos de Impuesto a la Renta</strong>.</div>`;
            }
            if (excluido) {
                avisos += '<div class="alert alert-secondary small py-2 mb-2"><i class="bi bi-slash-circle me-1"></i>Este empleado está excluido del cálculo automático de IR.</div>';
            }
            if (r.gasto_personal_proyectado === null && !excluido) {
                avisos += `<div class="alert alert-info small py-2 mb-2"><i class="bi bi-info-circle me-1"></i>
                    El empleado no tiene proyección de gastos personales para ${r.anio}, por lo que <strong>no se aplica rebaja</strong>.
                    Regístrela arriba (formulario SRI-GP) para reducir la retención.</div>`;
            }
            document.getElementById('empIrAvisos').innerHTML = avisos;

        } catch (e) {
            if (miPeticion === irPeticion) empIrAviso('danger', 'Error al calcular la proyección.');
        } finally {
            if (miPeticion === irPeticion && spinner) spinner.classList.add('d-none');
        }
    }

    function empIrAviso(tipo, texto) {
        const cont = document.getElementById('empIrAvisos');
        if (cont) cont.innerHTML = `<div class="alert alert-${tipo} small py-2 mb-2">${texto}</div>`;
    }

    window.agregarFilaPeriodo = function(data = {}) {
        const tbody = document.querySelector('#tablaPeriodos tbody');
        if (!tbody) return;
        const row = document.createElement('tr');
        row.className = 'row-emp';
        row.innerHTML = `
            <td class="ps-2"><input type="date" class="form-control form-control-sm input-emp dt-ingreso" value="${data.fecha_ingreso || ''}"></td>
            <td><input type="date" class="form-control form-control-sm input-emp dt-salida" value="${data.fecha_salida || ''}"></td>
            <td><input type="text" class="form-control form-control-sm input-emp dt-motivo" value="${data.motivo_salida || ''}" placeholder="Motivo de salida..."></td>
            <td class="text-center"><button type="button" class="btn btn-sm p-1 border-0 remove-row" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
        `;
        tbody.appendChild(row);
    };

    window.agregarFilaRubro = function(data = {}) {
        const tbody = document.querySelector('#tablaRubros tbody');
        if (!tbody) return;

        // Coordinación con la pestaña Laboral (Aporta al IESS):
        // una fila NUEVA de tipo ingreso hereda el "Aporta IESS" de la empresa;
        // una fila cargada respeta su valor guardado.
        const esNuevo = Object.keys(data).length === 0;
        const masterAporta = document.getElementById('emp_aporta_iess')?.value === 'si';
        const tipoRow = data.tipo || 'ingreso';
        const iessAporta = esNuevo ? (masterAporta && tipoRow === 'ingreso') : !!data.aporta_iess;

        const row = document.createElement('tr');
        row.className = 'row-emp';
        row.innerHTML = `
            <td class="ps-2">
                <select class="form-select form-select-sm input-emp rb-tipo" onchange="window.toggleRubroIess(this)">
                    <option value="ingreso" ${tipoRow === 'ingreso' ? 'selected' : ''}>Ingreso</option>
                    <option value="descuento" ${tipoRow === 'descuento' ? 'selected' : ''}>Descuento</option>
                </select>
            </td>
            <td><input type="text" class="form-control form-control-sm input-emp rb-nombre text-uppercase" value="${data.nombre || ''}" placeholder="Ej: BONO..."></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm input-emp text-end rb-valor" value="${data.valor || '0.00'}"></td>
            <td class="text-center">
                <select class="form-select form-select-sm input-emp text-center rb-iess">
                    <option value="si" ${iessAporta ? 'selected' : ''}>SÍ</option>
                    <option value="no" ${!iessAporta ? 'selected' : ''}>NO</option>
                </select>
            </td>
            <td class="text-center"><button type="button" class="btn btn-sm p-1 border-0 remove-row" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
        `;
        tbody.appendChild(row);
        window.toggleRubroIess(row.querySelector('.rb-tipo'));
    };

    window.agregarFilaAsignacion = async function (data = {}) {
        const tbody = document.querySelector('#tablaAsignaciones tbody');
        if (!tbody) return;
        const nuevoClick = Object.keys(data).length === 0;
        // Al agregar manualmente, refresca los turnos por si se crearon recién.
        if (nuevoClick || !horarioOpts.horarios.length) await cargarOpcionesHorario();
        const opts = horarioOpts || { horarios: [], puntos: [] };
        const esc = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        if (nuevoClick && !opts.horarios.length) {
            if (window.Swal) Swal.fire({ icon: 'info', title: 'No hay turnos', text: 'Primero crea turnos en el módulo «Horarios y turnos».' });
            else alert('Primero crea turnos en el módulo Horarios y turnos.');
            return;
        }
        const optHor = opts.horarios.map(h => `<option value="${h.id}" ${String(data.id_horario) === String(h.id) ? 'selected' : ''}>${esc(h.nombre)}</option>`).join('');
        const optPun = ['<option value="">— Sin punto —</option>'].concat(opts.puntos.map(p => `<option value="${p.id}" ${String(data.id_punto) === String(p.id) ? 'selected' : ''}>${esc(p.nombre)}</option>`)).join('');
        // "Vigente desde" es obligatorio: en filas nuevas se precarga con hoy para no perderlas al guardar.
        const desdeVal = data.vigente_desde ? String(data.vigente_desde).substring(0, 10) : (nuevoClick ? new Date().toISOString().slice(0, 10) : '');
        const row = document.createElement('tr');
        row.className = 'row-emp';
        row.innerHTML = `
            <td class="ps-2"><select class="form-select form-select-sm input-emp asg-horario">${optHor}</select></td>
            <td><select class="form-select form-select-sm input-emp asg-punto">${optPun}</select></td>
            <td><input type="date" class="form-control form-control-sm input-emp asg-desde" value="${desdeVal}"></td>
            <td><input type="date" class="form-control form-control-sm input-emp asg-hasta" value="${(data.vigente_hasta || '').substring(0, 10)}"></td>
            <td class="text-center"><button type="button" class="btn btn-sm p-1 border-0 remove-row" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
        `;
        tbody.appendChild(row);
    };

    window.toggleRubroIess = function(select) {
        const tr = select.closest('tr');
        const iessSel = tr.querySelector('.rb-iess');
        const masterAporta = document.getElementById('emp_aporta_iess')?.value;
        if (!iessSel) return;
        // Un rubro solo puede aportar al IESS si es INGRESO y la empresa aporta.
        if (select.value === 'descuento' || masterAporta === 'no') {
            iessSel.value = 'no';
            iessSel.disabled = true;
        } else {
            // Al pasar de deshabilitado a habilitado (p. ej. cambia a ingreso,
            // o la empresa pasa a aportar), se coordina a "Sí" para evitar
            // discrepancias con la pestaña Laboral.
            if (iessSel.disabled) iessSel.value = 'si';
            iessSel.disabled = false;
        }
    };

    function serializarTablasEmp() {
        const periodos = [];
        document.querySelectorAll('#tablaPeriodos tbody tr').forEach(tr => {
            const inpIngreso = tr.querySelector('.dt-ingreso');
            if (!inpIngreso) return; // ignora filas placeholder (p. ej. "Cargando...")
            const fi = inpIngreso.value;
            if (fi) periodos.push({
                fecha_ingreso: fi,
                fecha_salida: tr.querySelector('.dt-salida')?.value || '',
                motivo_salida: tr.querySelector('.dt-motivo')?.value || ''
            });
        });
        document.getElementById('periodos_json').value = JSON.stringify(periodos);

        const rubros = [];
        document.querySelectorAll('#tablaRubros tbody tr').forEach(tr => {
            const inpNombre = tr.querySelector('.rb-nombre');
            if (!inpNombre) return; // ignora filas placeholder
            const nombre = inpNombre.value;
            if (nombre) rubros.push({
                tipo: tr.querySelector('.rb-tipo')?.value || 'ingreso',
                nombre: nombre,
                valor: tr.querySelector('.rb-valor')?.value || '0',
                aporta_iess: tr.querySelector('.rb-iess')?.value || 'no'
            });
        });
        document.getElementById('rubros_json').value = JSON.stringify(rubros);

        const asignaciones = [];
        document.querySelectorAll('#tablaAsignaciones tbody tr').forEach(tr => {
            const selHor = tr.querySelector('.asg-horario');
            if (!selHor) return;
            const idHor = selHor.value;
            const desde = tr.querySelector('.asg-desde')?.value || '';
            if (idHor && desde) asignaciones.push({
                id_horario: idHor,
                id_punto: tr.querySelector('.asg-punto')?.value || '',
                vigente_desde: desde,
                vigente_hasta: tr.querySelector('.asg-hasta')?.value || ''
            });
        });
        const asgInput = document.getElementById('asignaciones_horario_json');
        if (asgInput) asgInput.value = JSON.stringify(asignaciones);

        const gastos = [];
        document.querySelectorAll('#tablaGastosPersonales tbody tr').forEach(tr => {
            const inpAnio = tr.querySelector('.gp-anio');
            if (!inpAnio) return;
            const anio = parseInt(inpAnio.value || '0', 10);
            if (!anio) return;
            const fila = {
                anio: anio,
                numero_cargas_familiares: tr.querySelector('.gp-cargas')?.value || '0',
                caso_especial: !!tr.querySelector('.gp-especial')?.checked
            };
            GP_RUBROS.forEach(r => { fila[r] = tr.querySelector('.gp-' + r)?.value || '0'; });
            gastos.push(fila);
        });
        const gpInput = document.getElementById('gastos_personales_json');
        if (gpInput) gpInput.value = JSON.stringify(gastos);
    }

    // ------------------------------------------------------------------
    // Proyección de gastos personales (form. SRI-GP), por año.
    // El total de la fila del año en curso es lo que se descuenta (topado al
    // máximo del año) al calcular la retención de Impuesto a la Renta.
    // ------------------------------------------------------------------
    const GP_RUBROS = ['vivienda', 'salud', 'educacion', 'alimentacion', 'vestimenta', 'turismo'];

    function gpTotalFila(tr) {
        let t = 0;
        GP_RUBROS.forEach(r => { t += parseFloat(tr.querySelector('.gp-' + r)?.value || '0') || 0; });
        return t;
    }

    window.gpRecalcular = function(el) {
        const tr = el ? el.closest('tr') : null;
        if (tr) {
            const tot = tr.querySelector('.gp-total');
            if (tot) tot.textContent = gpTotalFila(tr).toFixed(2);
        }
        // El deducible cambió: refresca la proyección de retención.
        if (window.empIrCargar) window.empIrCargar();
    };

    window.agregarFilaGasto = function(data = {}) {
        const tbody = document.querySelector('#tablaGastosPersonales tbody');
        if (!tbody) return;

        const anio = parseInt(data.anio || new Date().getFullYear(), 10);
        // Un solo registro por año (índice único en BD): si ya existe, no duplica.
        const yaExiste = Array.from(tbody.querySelectorAll('.gp-anio'))
            .some(i => parseInt(i.value || '0', 10) === anio);
        if (yaExiste && !data.anio) {
            Swal.fire({ icon: 'info', title: 'Año repetido', text: 'Ya existe una proyección para ' + anio + '. Edítela o cambie el año.' });
            return;
        }

        const inp = (clase, valor, paso) =>
            `<td class="p-0"><input type="number" step="${paso}" min="0" class="form-control form-control-sm border-0 ${clase}"
                style="padding:0 4px;height:20px;font-size:0.78rem;" value="${valor}" oninput="window.gpRecalcular(this)"></td>`;

        const esp = [true, 1, '1', 't', 'true', 'si'].includes(data.caso_especial) ? 'checked' : '';

        const tr = document.createElement('tr');
        tr.innerHTML =
            `<td class="p-0"><input type="number" class="form-control form-control-sm border-0 gp-anio"
                style="padding:0 4px;height:20px;font-size:0.78rem;" value="${anio}" oninput="window.gpRecalcular(this)"></td>` +
            GP_RUBROS.map(r => inp('gp-' + r, Number(data[r] || 0).toFixed(2), '0.01')).join('') +
            inp('gp-cargas', parseInt(data.numero_cargas_familiares || 0, 10), '1') +
            `<td class="text-center p-0"><input type="checkbox" class="form-check-input gp-especial m-0" ${esp}
                title="Discapacidad o enfermedad catastrófica: tope de 100 canastas" onchange="window.gpRecalcular(this)"></td>` +
            `<td class="text-end fw-bold gp-total" style="font-size:0.78rem;">0.00</td>` +
            `<td class="text-end text-muted gp-tope" style="font-size:0.78rem;">–</td>` +
            `<td class="text-center p-0"><button type="button" class="btn btn-sm btn-link text-danger p-0"
                onclick="const t=this.closest('tr'); t.remove(); window.gpRecalcular(null);" title="Quitar"><i class="bi bi-trash"></i></button></td>`;
        tbody.appendChild(tr);

        tr.querySelector('.gp-total').textContent = gpTotalFila(tr).toFixed(2);
    };

    /** Datos de la fila del año indicado tal como están en pantalla. null si no hay fila. */
    function gpFilaAnio(anio) {
        for (const tr of document.querySelectorAll('#tablaGastosPersonales tbody tr')) {
            if (parseInt(tr.querySelector('.gp-anio')?.value || '0', 10) === anio) {
                return {
                    tr: tr,
                    total: gpTotalFila(tr),
                    cargas: parseInt(tr.querySelector('.gp-cargas')?.value || '0', 10) || 0,
                    especial: !!tr.querySelector('.gp-especial')?.checked
                };
            }
        }
        return null;
    }

    /** Total proyectado para un año concreto según lo que hay en pantalla. null si no hay fila. */
    function gpTotalAnio(anio) {
        const filas = document.querySelectorAll('#tablaGastosPersonales tbody tr');
        for (const tr of filas) {
            if (parseInt(tr.querySelector('.gp-anio')?.value || '0', 10) === anio) return gpTotalFila(tr);
        }
        return null;
    }

    // Botón "Imprimir PDF" de la barra superior del modal.
    window.imprimirEmpleadoPdf = function() {
        const id = document.getElementById('emp_id').value;
        if (!id) {
            Swal.fire({ icon: 'info', title: 'Guarde primero', text: 'Debe guardar el empleado antes de imprimir su ficha.' });
            return;
        }
        // Descarga directa (el servidor responde con Content-Disposition: attachment).
        const a = document.createElement('a');
        a.href = `${urlModuloEmp}/imprimirPdf?id=${id}`;
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        a.remove();
    };

    if (formEmp) {
        formEmp.addEventListener('submit', async (e) => {
            e.preventDefault();
            serializarTablasEmp();
            const id = document.getElementById('emp_id').value;
            const btn = document.getElementById('btnGuardar');
            const url = id ? `${urlModuloEmp}/update` : `${urlModuloEmp}/store`;

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

            const restaurarBtn = () => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar'; };

            try {
                const fd = new FormData(formEmp);
                let resp = await fetch(url, { method: 'POST', body: fd });
                let json = await resp.json();

                // El backend pide confirmar porque hay roles abiertos que se regenerarán.
                if (!json.ok && json.requiere_confirmacion) {
                    const conf = await Swal.fire({
                        icon: 'warning',
                        title: 'Roles abiertos',
                        text: json.msg,
                        showCancelButton: true,
                        confirmButtonText: 'Sí, continuar',
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true
                    });
                    if (!conf.isConfirmed) { restaurarBtn(); return; }
                    fd.append('confirmar_roles', '1');
                    resp = await fetch(url, { method: 'POST', body: fd });
                    json = await resp.json();
                }

                if (json.ok) {
                    Swal.fire({
                        icon: 'success',
                        title: id ? 'Actualizado' : 'Guardado',
                        text: json.msg || 'Empleado guardado correctamente.',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    setTimeout(() => {
                        restaurarBtn();
                        getModalEmp()?.hide();
                        if (typeof window.cambiarPaginaAjax === 'function') window.cambiarPaginaAjax(window.currentPage || 1);
                        window.dispatchEvent(new CustomEvent('empleadoGuardado', { detail: json }));
                    }, 1500);
                } else {
                    Swal.fire({ icon: 'error', title: 'Atención', text: json.error || 'No se pudo guardar el empleado.' });
                    restaurarBtn();
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
                restaurarBtn();
            }
        });
    }



    // ─── Consulta SRI por cédula (autocompletar nombre) ──────────────────────
    let sriDebounceEmp = null;
    const campoIdEmp = document.getElementById('emp_identificacion');
    const badgeSriEmp = document.getElementById('empSriBadge');
    const spinnerSriEmp = document.getElementById('empSriSpinner');

    function mostrarBadgeSriEmp(texto, clase) {
        if (!badgeSriEmp) return;
        badgeSriEmp.textContent = texto;
        badgeSriEmp.className = 'badge ' + clase;
    }

    function limpiarBadgeSriEmp() {
        if (badgeSriEmp) badgeSriEmp.className = 'badge d-none';
        if (spinnerSriEmp) spinnerSriEmp.classList.add('d-none');
    }

    async function consultarSriEmp(identificacion) {
        if (spinnerSriEmp) spinnerSriEmp.classList.remove('d-none');
        mostrarBadgeSriEmp('Consultando SRI…', 'bg-secondary');
        try {
            const fd = new FormData();
            fd.append('identificacion', identificacion);
            const resp = await fetch(`${urlModuloEmp}/consultarSri`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (spinnerSriEmp) spinnerSriEmp.classList.add('d-none');

            if (!json.ok || !json.data) {
                mostrarBadgeSriEmp('No encontrado', 'bg-warning text-dark');
                return;
            }

            const d = json.data;
            mostrarBadgeSriEmp('✓ SRI', 'bg-success');

            // El nombre del SRI es la fuente oficial: rellena el campo.
            if (d.nombre) {
                const elNom = document.getElementById('emp_nombres_apellidos');
                if (elNom) elNom.value = d.nombre;
            }
            // La dirección solo se coloca si viene y el campo está vacío.
            if (d.direccion) {
                const elDir = document.getElementById('emp_direccion');
                if (elDir && !elDir.value.trim()) elDir.value = d.direccion;
            }
        } catch (err) {
            if (spinnerSriEmp) spinnerSriEmp.classList.add('d-none');
            mostrarBadgeSriEmp('Error', 'bg-danger');
        }
    }

    function onIdentificacionInputEmp() {
        limpiarBadgeSriEmp();
        clearTimeout(sriDebounceEmp);
        const tipo = (document.getElementById('emp_tipo_id')?.value || '').toLowerCase();
        const valor = (campoIdEmp?.value || '').replace(/\D/g, '');
        if (tipo !== 'cedula') return;           // solo cédulas
        if (valor.length === 10) {
            sriDebounceEmp = setTimeout(() => consultarSriEmp(valor), 700);
        }
    }

    if (campoIdEmp) {
        campoIdEmp.addEventListener('input', onIdentificacionInputEmp);
    }
    document.getElementById('emp_tipo_id')?.addEventListener('change', () => {
        limpiarBadgeSriEmp();
        onIdentificacionInputEmp();
    });

    async function eliminarEmpleadoConSwal(id, cerrarModal) {
        if (!id) return;
        const result = await Swal.fire({
            title: '¿Está seguro?',
            text: 'No podrá revertir esta acción.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });
        if (!result.isConfirmed) return;

        try {
            const fd = new FormData();
            fd.append('id_eliminar', id);
            const resp = await fetch(`${urlModuloEmp}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                Swal.fire({
                    icon: 'success',
                    title: 'Eliminado',
                    text: json.msg || 'Empleado eliminado correctamente.',
                    timer: 1500,
                    showConfirmButton: false
                });
                if (cerrarModal) getModalEmp()?.hide();
                if (typeof window.cambiarPaginaAjax === 'function') window.cambiarPaginaAjax(window.currentPage || 1);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'No se pudo eliminar el empleado.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo conectar con el servidor.' });
        }
    }

    // Botón de eliminar de cada fila del listado.
    window.eliminarRegistro = function(id) {
        eliminarEmpleadoConSwal(id, false);
    };

    // Botón de eliminar dentro del modal.
    window.eliminarRegistroModal = function() {
        const id = document.getElementById('emp_id').value;
        eliminarEmpleadoConSwal(id, true);
    };

})(window, document);
