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
        limpiarBadgeSriEmp();
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
