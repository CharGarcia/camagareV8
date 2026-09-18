/**
 * Modal de Vacaciones (Nómina): empleado con buscador, saldo, valor y el cuadro de
 * períodos de servicio (CuadroPeriodosVacaciones, en vacaciones_periodos.js), donde se
 * marcan los ya tomados o pagados antes del sistema.
 *
 * Dos modos sobre el mismo modal:
 *   - 'vacacion': registrar/editar una vacación (el cuadro de períodos se puede plegar).
 *   - 'periodos': solo el empleado y su cuadro (botón "Períodos" del listado).
 */
(function (window, document) {
    'use strict';

    const urlModulo = BASE_URL + '/modulos/vacaciones';
    let modalInst = null;
    const form = document.getElementById('formVacacion');
    let sueldoEmp = 0;
    let modo = 'vacacion';
    let periodosAbiertos = leerPreferencia();

    const $ = (id) => document.getElementById(id);
    const money = (v) => '$' + (parseFloat(v) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const dias = (v) => {
        const t = (Math.round((parseFloat(v) || 0) * 100) / 100).toLocaleString('es-EC', { maximumFractionDigits: 2 });
        return t + (t === '1' ? ' día' : ' días');
    };
    const fmtFecha = (ymd) => ymd ? `${ymd.substring(8, 10)}-${ymd.substring(5, 7)}-${ymd.substring(0, 4)}` : '—';
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const empBuscar = $('vac_empleado_buscar');
    const empHidden = $('vac_id_empleado');
    const empResultados = $('vac_empleado_resultados');

    function getModal() {
        if (!modalInst && typeof bootstrap !== 'undefined') modalInst = new bootstrap.Modal($('modalVacacion'));
        return modalInst;
    }

    // Preferencia del usuario: cuadro de períodos abierto o plegado al registrar vacaciones.
    function leerPreferencia() {
        try { return localStorage.getItem('cmg_vac_periodos') !== '0'; } catch (e) { return true; }
    }
    function guardarPreferencia(v) {
        try { localStorage.setItem('cmg_vac_periodos', v ? '1' : '0'); } catch (e) { }
    }

    function aplicarModo(nuevo, titulo) {
        modo = nuevo;
        const soloPeriodos = nuevo === 'periodos';
        $('tituloModalVac').textContent = titulo;
        $('iconoModalVac').className = `bi ${soloPeriodos ? 'bi-calendar-check' : 'bi-umbrella'} me-2 text-primary`;
        $('vac_campos').classList.toggle('d-none', soloPeriodos);
        $('vac_container_estado').classList.toggle('d-none', soloPeriodos);
        $('vac_container_empleado').classList.toggle('col-md-8', !soloPeriodos);
        $('vac_container_empleado').classList.toggle('col-12', soloPeriodos);
        $('btnGuardarVac').classList.toggle('d-none', soloPeriodos);
        $('btnCancelarVacTxt').textContent = soloPeriodos ? 'Cerrar' : 'Cancelar';
        // En modo períodos el cuadro siempre está abierto: el botón de plegar sobra.
        $('vac_btn_periodos').classList.toggle('d-none', soloPeriodos);
    }

    // ─── Cuadro de períodos + panel de saldo ─────────────────────────────────
    // Tras marcar o quitar una marca, el cuadro se recarga solo; aquí se repinta el panel.
    const cuadro = new window.CuadroPeriodosVacaciones($('vac_cuadro_periodos'), { onCambio: pintarPanel });

    function pintarPanel(d) {
        const saldo = parseFloat(d.saldo) || 0;
        sueldoEmp = parseFloat(d.sueldo_base) || 0;
        $('vac_info_antig').textContent = d.antiguedad || '—';
        $('vac_info_derecho').textContent = d.fecha_ingreso ? `Derecho del año: ${dias(d.derecho_anio_actual)}` : '';
        $('vac_info_acumulado').textContent = dias(d.total_derecho);
        $('vac_info_marcados').textContent = dias(d.dias_marcados);
        $('vac_info_gozados').textContent = dias(d.dias_gozados_total);
        $('vac_info_saldo').textContent = dias(saldo);
        $('vac_info_saldo').classList.toggle('text-danger', saldo < 0);
        $('vac_info_saldo').classList.toggle('text-primary', saldo >= 0);
        $('vac_info_sueldo').textContent = money(d.sueldo_base);
        $('vac_info_ingreso').textContent = d.fecha_ingreso ? fmtFecha(d.fecha_ingreso) : 'sin registrar';
        $('vac_info').classList.remove('d-none');
        actualizarSeccionPeriodos();
        cuadro.scrollAlPrimerPendiente();
        recalcularValor();
    }

    async function cargarInfoEmpleado(idEmpleado) {
        // Al editar, la vacación abierta no cuenta en el saldo (se está reemplazando).
        const d = await cuadro.cargar(idEmpleado, { exclude: $('vac_id').value || 0 });
        if (d) pintarPanel(d);
    }

    function actualizarSeccionPeriodos() {
        const hayEmpleado = !!(empHidden && empHidden.value) && !$('vac_info').classList.contains('d-none');
        const abierto = modo === 'periodos' || periodosAbiertos;
        $('vac_periodos').classList.toggle('d-none', !(hayEmpleado && abierto));
        const txt = abierto ? 'Ocultar períodos' : 'Ver períodos';
        const n = cuadro.pendientes;
        $('vac_btn_periodos_txt').textContent = n > 0 ? `${txt} (${n} pendiente${n === 1 ? '' : 's'})` : txt;
        $('vac_btn_periodos_chev').className = `bi ${abierto ? 'bi-chevron-up' : 'bi-chevron-down'} ms-1`;
    }

    $('vac_btn_periodos').addEventListener('click', () => {
        periodosAbiertos = !periodosAbiertos;
        guardarPreferencia(periodosAbiertos);
        actualizarSeccionPeriodos();
        cuadro.scrollAlPrimerPendiente();
    });

    // ─── Buscador de empleado ────────────────────────────────────────────────
    let empTimer = null;

    const ocultarResultados = () => empResultados && empResultados.classList.add('d-none');

    function setEmpleado(id, texto) {
        if (empHidden) empHidden.value = id || '';
        if (empBuscar) empBuscar.value = texto || '';
        ocultarResultados();
        if (id) {
            cargarInfoEmpleado(id);
        } else {
            cuadro.limpiar();
            $('vac_info').classList.add('d-none');
            $('vac_periodos').classList.add('d-none');
            sueldoEmp = 0;
            recalcularValor();
        }
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
                const texto = esc(`${e.nombres_apellidos} (${e.identificacion})`);
                return `<button type="button" class="list-group-item list-group-item-action py-1 small" data-id="${esc(e.id)}" data-texto="${texto}">
                            <span class="fw-medium">${esc(e.nombres_apellidos)}</span> <span class="text-muted">${esc(e.identificacion)}</span>
                        </button>`;
            }).join('');
            empResultados.classList.remove('d-none');
        } catch (e) { ocultarResultados(); }
    }

    if (empBuscar) {
        empBuscar.addEventListener('input', () => {
            if (empHidden && empHidden.value) setEmpleado('', empBuscar.value);
            const q = empBuscar.value.trim();
            clearTimeout(empTimer);
            if (q.length < 2) { ocultarResultados(); return; }
            empTimer = setTimeout(() => buscarEmpleados(q), 300);
        });
        // Con un empleado ya elegido, Retroceso/Suprimir limpian la selección entera.
        empBuscar.addEventListener('keydown', (ev) => {
            if ((ev.key === 'Backspace' || ev.key === 'Delete') && empHidden && empHidden.value) {
                ev.preventDefault();
                setEmpleado('', '');
            }
        });
        empBuscar.addEventListener('blur', () => setTimeout(ocultarResultados, 200));
    }
    if (empResultados) {
        empResultados.addEventListener('mousedown', (ev) => {
            const btn = ev.target.closest('[data-id]');
            if (!btn) return;
            ev.preventDefault();
            setEmpleado(btn.dataset.id, btn.dataset.texto);
        });
    }

    // ─── Valor y días ────────────────────────────────────────────────────────
    function recalcularValor() {
        const d = parseFloat($('vac_dias').value) || 0;
        $('vac_valor_preview').value = money((sueldoEmp / 30) * d);
    }

    function diasEntreFechas() {
        const d = $('vac_fecha_desde').value, h = $('vac_fecha_hasta').value;
        if (!d || !h) return 0;
        const ms = new Date(h) - new Date(d);
        return ms >= 0 ? Math.floor(ms / 86400000) + 1 : 0;
    }

    $('vac_dias').addEventListener('input', recalcularValor);
    ['vac_fecha_desde', 'vac_fecha_hasta'].forEach(id => $(id).addEventListener('change', () => {
        // Sugerir días por rango si aún no se han fijado.
        const actual = parseFloat($('vac_dias').value) || 0;
        const sug = diasEntreFechas();
        if (sug > 0 && actual === 0) { $('vac_dias').value = sug; recalcularValor(); }
        // Período del rol desde la fecha desde.
        const fd = $('vac_fecha_desde').value;
        if (fd) {
            $('vac_periodo_mes').value = String(parseInt(fd.substring(5, 7), 10));
            $('vac_periodo_anio').value = fd.substring(0, 4);
        }
    }));

    // ─── Abrir / guardar ─────────────────────────────────────────────────────
    window.abrirModalCrear = function () {
        if (!form) return;
        form.reset();
        $('vac_id').value = '';
        aplicarModo('vacacion', 'Nueva Vacación');
        $('btnEliminarVac')?.classList.add('d-none');
        setEmpleado('', '');
        $('vac_afecta_rol').checked = true;
        // Nueva: estado registrado en solo lectura.
        const est = $('vac_estado'); est.value = 'registrado'; est.disabled = true;
        recalcularValor();
        getModal()?.show();
    };

    // Solo los períodos del empleado (botón "Períodos" del listado).
    window.abrirModalPeriodos = function () {
        if (!form) return;
        form.reset();
        $('vac_id').value = '';
        aplicarModo('periodos', 'Períodos de vacaciones');
        $('btnEliminarVac')?.classList.add('d-none');
        setEmpleado('', '');
        getModal()?.show();
    };

    $('modalVacacion').addEventListener('shown.bs.modal', () => {
        // Al crear o abrir períodos, directo al buscador. Al editar no: con el empleado
        // ya elegido, un Retroceso ahí limpiaría la selección.
        if (!$('vac_id').value && !empHidden.value) empBuscar?.focus();
        cuadro.scrollAlPrimerPendiente();
    });

    window.abrirModalEditar = async function (tr) {
        const rowData = (tr instanceof HTMLElement) ? JSON.parse(tr.dataset.row) : tr;
        const id = rowData.id;
        if (!form || !id) return;
        form.reset();
        $('vac_id').value = id;
        aplicarModo('vacacion', 'Editar Vacación');
        $('btnEliminarVac')?.classList.remove('d-none');
        $('vac_estado').disabled = false;
        getModal()?.show();
        document.getElementById('vac-modal-loader')?.classList.remove('d-none');

        try {
            const resp = await fetch(`${urlModulo}/getDetalleAjax?id=${id}`);
            const res = await resp.json();
            if (!res.ok) return;
            const d = res.data;
            setEmpleado(d.id_empleado || '', d.empleado_nombre ? `${d.empleado_nombre} (${d.empleado_identificacion || ''})` : '');
            $('vac_estado').value = d.estado || 'registrado';
            $('vac_fecha_desde').value = d.fecha_desde || '';
            $('vac_fecha_hasta').value = d.fecha_hasta || '';
            $('vac_dias').value = d.dias_gozados != null ? parseFloat(d.dias_gozados) : 0;
            $('vac_periodo_mes').value = String(parseInt(d.periodo_mes, 10) || '');
            $('vac_periodo_anio').value = d.periodo_anio || '';
            $('vac_afecta_rol').checked = (d.afecta_rol === true || d.afecta_rol === 't' || d.afecta_rol === '1' || d.afecta_rol === 1);
            $('vac_observacion').value = d.observacion || '';
            recalcularValor();
        } catch (e) {
        } finally {
            document.getElementById('vac-modal-loader')?.classList.add('d-none');
        }
    };

    if (form) {
        form.addEventListener('submit', async () => {
            if (modo === 'periodos') return; // en ese modo no hay vacación que guardar
            if (!empHidden.value) { Swal.fire({ icon: 'info', title: 'Seleccione un empleado' }); return; }
            const id = $('vac_id').value;
            const btn = $('btnGuardarVac');
            const url = id ? `${urlModulo}/update` : `${urlModulo}/store`;
            const restaurar = () => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar'; };
            btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
            try {
                const resp = await fetch(url, { method: 'POST', body: new FormData(form) });
                const json = await resp.json();
                if (json.ok) {
                    Swal.fire({ icon: 'success', title: id ? 'Actualizada' : 'Guardada', text: json.msg, timer: 1400, showConfirmButton: false });
                    setTimeout(() => { restaurar(); getModal()?.hide(); window.dispatchEvent(new CustomEvent('vacacionGuardada')); }, 1400);
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

    async function eliminarConSwal(id, cerrar) {
        if (!id) return;
        const r = await Swal.fire({ title: '¿Está seguro?', text: 'No podrá revertir esta acción.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' });
        if (!r.isConfirmed) return;
        try {
            const fd = new FormData(); fd.append('id_eliminar', id);
            const resp = await fetch(`${urlModulo}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1300, showConfirmButton: false });
                if (cerrar) getModal()?.hide();
                window.dispatchEvent(new CustomEvent('vacacionGuardada'));
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error });
            }
        } catch (e) { }
    }

    window.eliminarRegistro = (id) => eliminarConSwal(id, false);
    window.eliminarVacacionModal = () => eliminarConSwal($('vac_id').value, true);

})(window, document);
