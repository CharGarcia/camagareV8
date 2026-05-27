/**
 * Lógica compartida para el Modal de Empleados
 */

(function (window, document) {
    'use strict';

    const urlModuloEmp = BASE_URL + '/modulos/empleados';
    let modalInstEmp = null;
    const formEmp = document.getElementById('formEmpleado');
    const alertElEmp = document.getElementById('modalAlert');

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
        document.getElementById('emp_id').value = '';
        document.getElementById('tituloModal').textContent = 'Nuevo Empleado';
        document.getElementById('btnEliminarModal')?.classList.add('d-none');
        document.querySelector('#tablaPeriodos tbody').innerHTML = '';
        document.querySelector('#tablaRubros tbody').innerHTML = '';
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

                // Puesto
                document.getElementById('emp_region').value = d.region || 'costa';
                document.getElementById('emp_departamento').value = d.departamento || '';
                document.getElementById('emp_cargo').value = d.cargo || '';
                document.getElementById('emp_lugar_trabajo').value = d.lugar_trabajo || '';
                document.getElementById('emp_horario_trabajo').value = d.horario_trabajo || '';
                document.getElementById('emp_codigo_sectorial_iess').value = d.codigo_sectorial_iess || '';

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


            }
        } catch (e) {}
    };

    window.toggleIessFields = async function() {
        const aporta = document.getElementById('emp_aporta_iess')?.value;
        const container = document.getElementById('container-aportes-iess');
        const fields = document.querySelectorAll('.iess-field');
        if (!container) return;

        if (aporta === 'no') {
            container.classList.add('d-none');
            fields.forEach(f => { f.value = '0'; f.readOnly = true; });
        } else {
            container.classList.remove('d-none');
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

    window.agregarFilaPeriodo = function(data = {}) {
        const tbody = document.querySelector('#tablaPeriodos tbody');
        if (!tbody) return;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="date" class="form-control form-control-sm dt-ingreso shadow-none" value="${data.fecha_ingreso || ''}"></td>
            <td><input type="date" class="form-control form-control-sm dt-salida shadow-none" value="${data.fecha_salida || ''}"></td>
            <td><input type="text" class="form-control form-control-sm dt-motivo shadow-none" value="${data.motivo_salida || ''}" placeholder="..."></td>
            <td class="text-center"><button type="button" class="btn btn-outline-danger btn-xs border-0" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
        `;
        tbody.appendChild(row);
    };

    window.agregarFilaRubro = function(data = {}) {
        const tbody = document.querySelector('#tablaRubros tbody');
        if (!tbody) return;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <select class="form-select form-select-sm rb-tipo shadow-none" onchange="window.toggleRubroIess(this)">
                    <option value="ingreso" ${data.tipo === 'ingreso' ? 'selected' : ''}>Ingreso</option>
                    <option value="descuento" ${data.tipo === 'descuento' ? 'selected' : ''}>Descuento</option>
                </select>
            </td>
            <td><input type="text" class="form-control form-control-sm rb-nombre text-uppercase shadow-none" value="${data.nombre || ''}" placeholder="Ej: BONO... "></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm rb-valor shadow-none" value="${data.valor || '0.00'}"></td>
            <td class="text-center">
                <select class="form-select form-select-sm rb-iess shadow-none">
                    <option value="si" ${data.aporta_iess ? 'selected' : ''}>SÍ</option>
                    <option value="no" ${!data.aporta_iess ? 'selected' : ''}>NO</option>
                </select>
            </td>
            <td class="text-center"><button type="button" class="btn btn-outline-danger btn-xs border-0" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
        `;
        tbody.appendChild(row);
        window.toggleRubroIess(row.querySelector('.rb-tipo'));
    };

    window.toggleRubroIess = function(select) {
        const tr = select.closest('tr');
        const iessSel = tr.querySelector('.rb-iess');
        const masterAporta = document.getElementById('emp_aporta_iess')?.value;
        if (!iessSel) return;
        if (select.value === 'descuento' || masterAporta === 'no') {
            iessSel.value = 'no';
            iessSel.disabled = true;
        } else {
            iessSel.disabled = false;
        }
    };

    function serializarTablasEmp() {
        const periodos = [];
        document.querySelectorAll('#tablaPeriodos tbody tr').forEach(tr => {
            const fi = tr.querySelector('.dt-ingreso').value;
            if (fi) periodos.push({
                fecha_ingreso: fi,
                fecha_salida: tr.querySelector('.dt-salida').value,
                motivo_salida: tr.querySelector('.dt-motivo').value
            });
        });
        document.getElementById('periodos_json').value = JSON.stringify(periodos);

        const rubros = [];
        document.querySelectorAll('#tablaRubros tbody tr').forEach(tr => {
            const nombre = tr.querySelector('.rb-nombre').value;
            if (nombre) rubros.push({
                tipo: tr.querySelector('.rb-tipo').value,
                nombre: nombre,
                valor: tr.querySelector('.rb-valor').value,
                aporta_iess: tr.querySelector('.rb-iess').value
            });
        });
        document.getElementById('rubros_json').value = JSON.stringify(rubros);
    }

    if (formEmp) {
        formEmp.addEventListener('submit', async (e) => {
            e.preventDefault();
            serializarTablasEmp();
            const id = document.getElementById('emp_id').value;
            const btn = document.getElementById('btnGuardar');
            const url = id ? `${urlModuloEmp}/update` : `${urlModuloEmp}/store`;

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
            if (alertElEmp) alertElEmp.classList.add('d-none');

            try {
                const fd = new FormData(formEmp);
                const resp = await fetch(url, { method: 'POST', body: fd });
                const json = await resp.json();

                if (alertElEmp) {
                    alertElEmp.textContent = json.msg || json.error;
                    alertElEmp.className = 'alert mb-3 py-2 small shadow-sm border-0 ' + (json.ok ? 'alert-success' : 'alert-danger');
                    alertElEmp.classList.remove('d-none');
                }

                if (json.ok) {
                    setTimeout(() => { 
                        getModalEmp()?.hide(); 
                        if (typeof window.cambiarPaginaAjax === 'function') window.cambiarPaginaAjax(window.currentPage || 1);
                        window.dispatchEvent(new CustomEvent('empleadoGuardado', { detail: json }));
                    }, 800);
                } else { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar'; }
            } catch (err) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar'; }
        });
    }



    window.eliminarRegistroModal = async function() {
        const id = document.getElementById('emp_id').value;
        if (!id || !confirm('¿Seguro que desea eliminar este empleado?')) return;
        try {
            const fd = new FormData(); fd.append('id_eliminar', id);
            const resp = await fetch(`${urlModuloEmp}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) { getModalEmp()?.hide(); if (typeof window.cambiarPaginaAjax === 'function') window.cambiarPaginaAjax(window.currentPage || 1); }
        } catch (e) {}
    };

})(window, document);
