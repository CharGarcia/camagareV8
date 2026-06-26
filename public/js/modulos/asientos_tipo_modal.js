(function () {
    'use strict';

    let modalListInstance = null;
    let modalFormInstance = null;
    let currentPage = 1;
    let timerBusqueda = null;

    const API_ASIENTOS_TIPO = `${window.BASE_URL || ''}/${window.ASIENTOTIPO_ROUTE || 'modulos/plantillas_contables'}`;

    /**
     * Abre el modal con el listado de Asientos Tipo.
     */
    window.ASIENTOTIPO_abrirModalListado = function () {
        if (!modalListInstance) {
            const el = document.getElementById('modalAsientosTipoList');
            if (el) modalListInstance = new bootstrap.Modal(el);
        }
        
        // Limpiar buscador al abrir
        const inputBuscar = document.getElementById('asientoTipoInputBuscar');
        if (inputBuscar) inputBuscar.value = '';

        ASIENTOTIPO_cargarListado(1);

        if (modalListInstance) modalListInstance.show();
    };

    /**
     * Carga el listado de Asientos Tipo vía AJAX.
     */
    window.ASIENTOTIPO_cargarListado = async function (page = 1) {
        currentPage = page;
        const inputB = document.getElementById('asientoTipoInputBuscar');
        const b = inputB ? inputB.value.trim() : '';

        const tbody = document.getElementById('tbodyAsientosTipo');
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span> Cargando...</td></tr>';

        try {
            const resp = await fetch(`${API_ASIENTOS_TIPO}/asientosTipoListAjax?b=${encodeURIComponent(b)}&page=${page}`);
            const res = await resp.json();
            if (res.ok) {
                if (tbody) tbody.innerHTML = res.rows;
                const info = document.getElementById('asientoTipoPaginationInfo');
                if (info) info.textContent = res.info;
                const pag = document.getElementById('asientoTipoWrapperPagination');
                if (pag) pag.innerHTML = res.pagination;
            } else {
                if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">${res.error || 'Error al cargar listado'}</td></tr>`;
            }
        } catch (e) {
            console.error(e);
            if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Error de conexión con el servidor.</td></tr>';
        }
    };

    /**
     * Cambia de página en el listado.
     */
    window.ASIENTOTIPO_cambiarPagina = function (page) {
        if (page < 1) return;
        ASIENTOTIPO_cargarListado(page);
    };

    /**
     * Detecta el cambio de opción en el selector de tipo de asiento.
     */
    window.ASIENTOTIPO_onSelectChange = function () {
        const select = document.getElementById('asientoTipoSelect');
        const contenedor = document.getElementById('asientoTipoNuevoContenedor');
        const inputNuevo = document.getElementById('asientoTipoNuevo');

        if (select.value === '__nuevo__') {
            contenedor.style.display = 'block';
            inputNuevo.required = true;
            inputNuevo.focus();
        } else {
            contenedor.style.display = 'none';
            inputNuevo.required = false;
            inputNuevo.value = '';
        }
    };

    /**
     * Abre el modal del formulario en modo NUEVO.
     */
    window.ASIENTOTIPO_nuevo = function () {
        if (!modalFormInstance) {
            const el = document.getElementById('modalAsientoTipoForm');
            if (el) modalFormInstance = new bootstrap.Modal(el);
        }

        document.getElementById('formAsientoTipo').reset();
        const rDebe = document.getElementById('eh_debe');
        if (rDebe) rDebe.checked = true;
        document.getElementById('asientoTipoId').value = '0';
        document.getElementById('asientoTipoCodigo').disabled = false;
        document.getElementById('modalAsientoTipoFormLabel').innerHTML = '<i class="bi bi-plus-circle me-2"></i> Registrar Asiento Tipo';

        // Reset checkboxes and hidden field
        document.querySelectorAll('.tc-checkbox').forEach(cb => cb.checked = false);
        const tcHidden = document.getElementById('asientoTipoTipoCuenta');
        if (tcHidden) tcHidden.value = '';

        // Remover opciones personalizadas previas para iniciar limpio
        const select = document.getElementById('asientoTipoSelect');
        const customOpts = select.querySelectorAll('option[data-custom="1"]');
        customOpts.forEach(opt => opt.remove());

        document.getElementById('asientoTipoNuevoContenedor').style.display = 'none';
        document.getElementById('asientoTipoNuevo').required = false;
        document.getElementById('asientoTipoNuevo').value = '';

        if (modalFormInstance) modalFormInstance.show();
    };

    /**
     * Obtiene y abre el formulario en modo EDICIÓN.
     */
    window.ASIENTOTIPO_editar = async function (id) {
        if (!id) return;

        if (!modalFormInstance) {
            const el = document.getElementById('modalAsientoTipoForm');
            if (el) modalFormInstance = new bootstrap.Modal(el);
        }

        try {
            const resp = await fetch(`${API_ASIENTOS_TIPO}/asientosTipoGetDetailAjax?id=${id}`);
            const res = await resp.json();
            if (res.ok) {
                document.getElementById('formAsientoTipo').reset();
                document.getElementById('asientoTipoId').value = res.data.id;
                
                const select = document.getElementById('asientoTipoSelect');
                
                // Remover opciones personalizadas previas para evitar duplicidad
                const customOpts = select.querySelectorAll('option[data-custom="1"]');
                customOpts.forEach(opt => opt.remove());

                // Verificar si el tipo de asiento ya existe en el selector
                let optionExists = false;
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].value === res.data.tipo_asiento) {
                        optionExists = true;
                        break;
                    }
                }

                // Si no existe (es un concepto personalizado previamente creado), lo inyectamos dinámicamente
                if (!optionExists && res.data.tipo_asiento) {
                    const labelText = res.data.tipo_asiento
                        .split('_')
                        .map(w => w.charAt(0).toUpperCase() + w.slice(1))
                        .join(' ');

                    const newOpt = document.createElement('option');
                    newOpt.value = res.data.tipo_asiento;
                    newOpt.textContent = labelText;
                    newOpt.setAttribute('data-custom', '1');
                    select.insertBefore(newOpt, select.querySelector('option[value="__nuevo__"]'));
                }

                select.value = res.data.tipo_asiento;
                
                document.getElementById('asientoTipoNuevoContenedor').style.display = 'none';
                document.getElementById('asientoTipoNuevo').required = false;
                document.getElementById('asientoTipoNuevo').value = '';

                const codInput = document.getElementById('asientoTipoCodigo');
                if (codInput) {
                    codInput.value = res.data.codigo;
                    codInput.disabled = res.data.en_uso === true;
                }
                document.getElementById('asientoTipoReferencia').value = res.data.referencia;
                
                const valDebeHaber = (res.data.debe_haber || 'debe').toLowerCase();
                const rad = document.getElementById(`eh_${valDebeHaber}`);
                if (rad) rad.checked = true;
                
                const checkedParts = (res.data.tipo_cuenta || '').split(',').map(p => p.trim().toLowerCase());
                document.querySelectorAll('.tc-checkbox').forEach(cb => {
                    cb.checked = checkedParts.includes(cb.value);
                });
                const tcHidden = document.getElementById('asientoTipoTipoCuenta');
                if (tcHidden) tcHidden.value = res.data.tipo_cuenta || '';

                document.getElementById('asientoTipoDetalle').value = res.data.detalle || '';
                
                document.getElementById('modalAsientoTipoFormLabel').innerHTML = '<i class="bi bi-pencil-square me-2"></i> Editar Asiento Tipo';

                if (modalFormInstance) modalFormInstance.show();
            } else {
                if (window.Swal) Swal.fire('Error', res.error || 'No se pudo obtener el detalle', 'error');
                else alert(res.error || 'No se pudo obtener el detalle');
            }
        } catch (e) {
            console.error(e);
            if (window.Swal) Swal.fire('Error', 'Error de conexión', 'error');
            else alert('Error de conexión');
        }
    };

    /**
     * Guarda el asiento tipo (Crear o Actualizar).
     */
    window.ASIENTOTIPO_guardar = async function (event) {
        event.preventDefault();

        const form = document.getElementById('formAsientoTipo');
        const id = parseInt(document.getElementById('asientoTipoId').value || '0', 10);
        const url = id > 0 ? `${API_ASIENTOS_TIPO}/asientosTipoUpdate` : `${API_ASIENTOS_TIPO}/asientosTipoStore`;

        const select = document.getElementById('asientoTipoSelect');
        const inputNuevo = document.getElementById('asientoTipoNuevo');

        const fd = new FormData(form);

        // Concatenate selected checkboxes into comma-separated string
        const checkedValues = Array.from(document.querySelectorAll('.tc-checkbox:checked')).map(cb => cb.value);
        const tipoCuentaStr = checkedValues.join(',');
        const tcHidden = document.getElementById('asientoTipoTipoCuenta');
        if (tcHidden) tcHidden.value = tipoCuentaStr;
        fd.set('tipo_cuenta', tipoCuentaStr);

        // Si se seleccionó crear un nuevo concepto, generamos su identificador de forma segura
        if (select.value === '__nuevo__') {
            const nuevoNombreVal = inputNuevo.value.trim();
            if (!nuevoNombreVal) {
                if (window.Swal) Swal.fire('Error', 'El nombre del nuevo concepto es obligatorio.', 'error');
                else alert('El nombre del nuevo concepto es obligatorio.');
                return;
            }

            // Slugificación segura: minúsculas, remover acentos, caracteres permitidos, guiones bajos
            const slug = nuevoNombreVal
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '') // Remover acentos de forma nativa
                .replace(/[^a-z0-9_ ]/g, '')     // Solo permitir caracteres seguros y espacios
                .trim()
                .replace(/\s+/g, '_');           // Reemplazar espacios por guiones bajos

            if (!slug) {
                if (window.Swal) Swal.fire('Error', 'El nombre del concepto contiene caracteres inválidos.', 'error');
                else alert('El nombre del concepto contiene caracteres inválidos.');
                return;
            }

            fd.set('tipo_asiento', slug);
        }

        const btn = document.getElementById('btnAsientoTipoSubmit');
        const origText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';
        btn.disabled = true;

        // Asegurar enviar código en mayúsculas
        const codigoInput = document.getElementById('asientoTipoCodigo');
        if (codigoInput) fd.set('codigo', codigoInput.value.trim().toUpperCase());

        try {
            const resp = await fetch(url, { method: 'POST', body: fd });
            const res = await resp.json();
            if (res.ok) {
                if (window.Swal) Swal.fire('Éxito', res.msg, 'success');
                else alert(res.msg);

                ASIENTOTIPO_cerrarForm();
                ASIENTOTIPO_cargarListado(currentPage);
            } else {
                if (window.Swal) Swal.fire('Error', res.error || 'Error al guardar', 'error');
                else alert(res.error || 'Error al guardar');
            }
        } catch (e) {
            console.error(e);
            if (window.Swal) Swal.fire('Error', 'Error de red al intentar guardar', 'error');
            else alert('Error de red al intentar guardar');
        } finally {
            btn.innerHTML = origText;
            btn.disabled = false;
        }
    };

    /**
     * Elimina un asiento tipo.
     */
    window.ASIENTOTIPO_eliminar = async function (id) {
        if (!id) return;

        let confirmed = false;
        if (window.Swal) {
            const result = await Swal.fire({
                title: '¿Está seguro de eliminar?',
                text: "Esta acción no se puede deshacer y el asiento tipo dejará de estar disponible.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });
            confirmed = result.isConfirmed;
        } else {
            confirmed = confirm('¿Está seguro de eliminar este asiento tipo?');
        }

        if (!confirmed) return;

        const fd = new FormData();
        fd.append('id', id.toString());

        try {
            const resp = await fetch(`${API_ASIENTOS_TIPO}/asientosTipoDelete`, { method: 'POST', body: fd });
            const res = await resp.json();
            if (res.ok) {
                if (window.Swal) Swal.fire('Eliminado', res.msg, 'success');
                else alert(res.msg);

                ASIENTOTIPO_cargarListado(1);
            } else {
                if (window.Swal) Swal.fire('Error', res.error || 'Error al eliminar', 'error');
                else alert(res.error || 'Error al eliminar');
            }
        } catch (e) {
            console.error(e);
            if (window.Swal) Swal.fire('Error', 'Error de conexión', 'error');
            else alert('Error de conexión');
        }
    };

    /**
     * Cierra el formulario de asiento tipo de forma segura.
     */
    window.ASIENTOTIPO_cerrarForm = function () {
        if (modalFormInstance) {
            modalFormInstance.hide();
        }
    };

    // Agregar debounce al buscador del modal de listado
    document.addEventListener('DOMContentLoaded', function () {
        const inputBuscar = document.getElementById('asientoTipoInputBuscar');
        if (inputBuscar) {
            inputBuscar.addEventListener('input', function () {
                clearTimeout(timerBusqueda);
                timerBusqueda = setTimeout(() => {
                    ASIENTOTIPO_cargarListado(1);
                }, 400);
            });
        }
    });

})();
