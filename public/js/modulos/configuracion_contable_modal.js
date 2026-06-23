(function () {
    'use strict';

    const API_PROG = `${window.BASE_URL || ''}/modulos/configuracion-contable`;
    let activeDropdown = null;
    let debounceTimer = null;

    /**
     * Inicializa o actualiza la visualización de acordeones al presionar "Configurar Asientos".
     */
    window.ASIENTOPROG_configurar = async function () {
        const selector = document.getElementById('tipoAsientoSelector');
        const tipoAsiento = selector.value;

        if (!tipoAsiento) {
            if (window.Swal) Swal.fire('Atención', 'Por favor, elija un tipo de asiento del selector.', 'warning');
            else alert('Por favor, elija un tipo de asiento del selector.');
            return;
        }

        const btn = document.getElementById('btnConfigurarAsientos');
        const origText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Cargando...';
        btn.disabled = true;

        try {
            const resp = await fetch(`${API_PROG}/cargarConfiguracionAjax?tipo_asiento=${tipoAsiento}`);
            const res = await resp.json();

            if (res.ok) {
                window.CONCEPTOS_CONFIGURADOS = res.data || [];
                const tbody = document.getElementById('tbodyConfiguracionGeneral');
                tbody.innerHTML = '';

                // Actualizar dinámicamente la cabecera thead de la tabla general
                const thead = document.getElementById('theadConfiguracionGeneral');
                if (thead) {
                    if (tipoAsiento === 'retenciones_venta') {
                        thead.innerHTML = `
                            <tr>
                                <th class="ps-4 py-2" style="width: 20%">Concepto</th>
                                <th class="py-2" style="width: 20%">Detalle</th>
                                <th class="py-2" style="width: 10%">Tipo Cuenta</th>
                                <th class="py-2" style="width: 20%">Cuenta Contable Debe</th>
                                <th class="py-2" style="width: 20%">Cuenta Contable Haber</th>
                                <th class="text-center py-2" style="width: 10%">Acción</th>
                            </tr>
                        `;
                    } else {
                        thead.innerHTML = `
                            <tr>
                                <th class="ps-4 py-2" style="width: 20%">Concepto</th>
                                <th class="py-2" style="width: 25%">Detalle</th>
                                <th class="py-2" style="width: 15%">Tipo Cuenta</th>
                                <th class="text-center py-2" style="width: 10%">Naturaleza</th>
                                <th class="py-2" style="width: 20%">Cuenta Contable</th>
                                <th class="text-center py-2" style="width: 10%">Acción</th>
                            </tr>
                        `;
                    }
                }

                if (res.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-info-circle me-1"></i> No hay reglas predefinidas para este tipo de asiento.</td></tr>';
                } else {
                    res.data.forEach(item => {
                        const tr = document.createElement('tr');
                        
                        if (tipoAsiento === 'retenciones_venta') {
                            const rvdSuffix = `rvd_${item.id_referencia}`;
                            const inputDebeId = `cuenta_search_${rvdSuffix}`;
                            const hiddenDebeId = `cuenta_hidden_${rvdSuffix}`;
                            const sugDebeId = `sug_${rvdSuffix}`;

                            const rvhSuffix = `rvh_${item.id_referencia}`;
                            const inputHaberId = `cuenta_search_${rvhSuffix}`;
                            const hiddenHaberId = `cuenta_hidden_${rvhSuffix}`;
                            const sugHaberId = `sug_${rvhSuffix}`;
                            
                            const cuentaDebeVal = item.id_cuenta ? `${item.cuenta_codigo} - ${item.cuenta_nombre}` : '';
                            const idCuentaDebeVal = item.id_cuenta || '';

                            const cuentaHaberVal = item.haber_id_cuenta ? `${item.haber_cuenta_codigo} - ${item.haber_cuenta_nombre}` : '';
                            const idCuentaHaberVal = item.haber_id_cuenta || '';

                            const borderClassDebe = idCuentaDebeVal ? '' : 'is-invalid border-danger';
                            const borderClassHaber = idCuentaHaberVal ? '' : 'is-invalid border-danger';

                            tr.innerHTML = `
                                <td class="ps-4 fw-bold text-dark">${item.concepto}</td>
                                <td class="small text-muted">${item.detalle || 'Sin descripción.'}</td>
                                <td><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-1 px-2 m-1 small">Activo</span></td>
                                <td class="autocomplete-celda">
                                    <input type="text" class="form-control form-control-sm ${borderClassDebe}" id="${inputDebeId}" placeholder="Cuenta Debe..." value="${cuentaDebeVal}" autocomplete="off">
                                    <input type="hidden" id="${hiddenDebeId}" value="${idCuentaDebeVal}">
                                    <div class="list-group sugerencias-flotantes" id="${sugDebeId}" style="display: none;"></div>
                                </td>
                                <td class="autocomplete-celda">
                                    <input type="text" class="form-control form-control-sm ${borderClassHaber}" id="${inputHaberId}" placeholder="Cuenta Haber..." value="${cuentaHaberVal}" autocomplete="off">
                                    <input type="hidden" id="${hiddenHaberId}" value="${idCuentaHaberVal}">
                                    <div class="list-group sugerencias-flotantes" id="${sugHaberId}" style="display: none;"></div>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-link text-danger p-0 border-0" onclick="ASIENTOPROG_eliminarAlVuelo(0, '${inputDebeId}', '${hiddenDebeId}', 'retenciones_venta_debe', ${item.id_referencia}); ASIENTOPROG_eliminarAlVuelo(0, '${inputHaberId}', '${hiddenHaberId}', 'retenciones_venta_haber', ${item.id_referencia})" title="Limpiar Cuentas">
                                        <i class="bi bi-trash fs-5"></i>
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(tr);
                            ASIENTOPROG_vincularAutocomplete(0, inputDebeId, hiddenDebeId, sugDebeId, 'activo', 'retenciones_venta_debe', item.id_referencia);
                            ASIENTOPROG_vincularAutocomplete(0, inputHaberId, hiddenHaberId, sugHaberId, 'activo', 'retenciones_venta_haber', item.id_referencia);
                        } else {
                            const safeSuffix = item.tipo_referencia === 'iva_ventas_factura' ? `iva_${item.id_referencia}` : `at_${item.id_asiento_tipo}`;
                            const inputId = `cuenta_search_${safeSuffix}`;
                            const hiddenId = `cuenta_hidden_${safeSuffix}`;
                            const sugId = `sug_${safeSuffix}`;
                            
                            const cuentaVal = item.id_cuenta ? `${item.cuenta_codigo} - ${item.cuenta_nombre}` : '';
                            const idCuentaVal = item.id_cuenta || '';

                            // Mapeo visual premium de badges para el Tipo de Cuenta
                            const colorMap = {
                                activo: 'success',
                                pasivo: 'danger',
                                patrimonio: 'dark',
                                ingreso: 'primary',
                                costo: 'info',
                                gasto: 'warning'
                            };
                            const parts = (item.tipo_cuenta || '').split(',').map(p => p.trim().toLowerCase());
                            let badgeHtml = '';
                            parts.forEach(p => {
                                if (p) {
                                    const label = p.charAt(0).toUpperCase() + p.slice(1);
                                    badgeHtml += `<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 py-1 px-2 m-1 small">${label}</span>`;
                                }
                            });
                            if (!badgeHtml) {
                                badgeHtml = '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 py-1 px-2 m-1 small">Todos</span>';
                            }

                            const debeHaberBadge = (item.debe_haber || 'debe').toLowerCase() === 'debe'
                                ? '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 py-1 px-2 fw-bold small">DEBE</span>'
                                : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 py-1 px-2 fw-bold small">HABER</span>';

                            const borderClass = idCuentaVal ? '' : 'is-invalid border-danger';

                            tr.innerHTML = `
                                <td class="ps-4 fw-bold text-dark">${item.concepto}</td>
                                <td class="small text-muted">${item.detalle || 'Sin descripción detallada.'}</td>
                                <td>${badgeHtml}</td>
                                <td class="text-center">${debeHaberBadge}</td>
                                <td class="autocomplete-celda">
                                    <input type="text" class="form-control form-control-sm ${borderClass}" id="${inputId}" placeholder="Escriba código o nombre..." value="${cuentaVal}" autocomplete="off">
                                    <input type="hidden" id="${hiddenId}" value="${idCuentaVal}">
                                    <div class="list-group sugerencias-flotantes" id="${sugId}" style="display: none;"></div>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-link text-danger p-0 border-0" onclick="ASIENTOPROG_eliminarAlVuelo(item.id_asiento_tipo, '${inputId}', '${hiddenId}', item.tipo_referencia || '', item.id_referencia || 0)" title="Limpiar Cuenta">
                                        <i class="bi bi-trash fs-5"></i>
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(tr);

                            // Configurar autocompletado en caliente para este input filtrando por tipo de cuenta
                            ASIENTOPROG_vincularAutocomplete(item.id_asiento_tipo, inputId, hiddenId, sugId, item.tipo_cuenta || '', item.tipo_referencia || '', item.id_referencia || 0);
                        }
                    });
                }

                // Actualizar método preferido
                const selectorMetodo = document.getElementById('selectorMetodoPreferencia');
                if (selectorMetodo) {
                    selectorMetodo.value = 'general';
                    if (res.metodo !== 'general') {
                        ASIENTOPROG_guardarMetodoPreferencia('general');
                    }
                }

                // Actualizar título y mostrar panel de acordeones
                const selectedText = selector.options[selector.selectedIndex].text;
                document.getElementById('conceptoSeleccionadoTitulo').innerHTML = `<i class="bi bi-gear-fill text-primary me-1"></i> Configuración para: <span class="text-primary fw-bold">${selectedText}</span>`;
                document.getElementById('seccionAcordeones').style.display = 'block';

                // Cerrar acordeones colapsados por defecto (excepto el general)
                ['Clientes', 'Productos', 'Categorias', 'Marcas', 'Ivas'].forEach(dim => {
                    const el = document.getElementById(`collapse${dim}`);
                    if (el && el.classList.contains('show')) {
                        const bsCollapse = bootstrap.Collapse.getInstance(el);
                        if (bsCollapse) bsCollapse.hide();
                    }
                });

                // Scroll suave hacia los acordeones
                document.getElementById('seccionAcordeones').scrollIntoView({ behavior: 'smooth' });

            } else {
                if (window.Swal) Swal.fire('Error', res.error || 'No se pudo cargar la configuración.', 'error');
                else alert(res.error || 'No se pudo cargar la configuración.');
            }
        } catch (e) {
            console.error(e);
            if (window.Swal) Swal.fire('Error', 'Error de red al intentar consultar.', 'error');
        } finally {
            btn.innerHTML = origText;
            btn.disabled = false;
        }
    };

    /**
     * Vincula el comportamiento de autocompletado reactivo en caliente a cada input de la tabla.
     */
    function ASIENTOPROG_vincularAutocomplete(idAsientoTipo, inputId, hiddenId, sugId, tipoCuenta, tipoReferencia = '', idReferencia = 0) {
        const input = document.getElementById(inputId);
        const hidden = document.getElementById(hiddenId);
        const sug = document.getElementById(sugId);

        if (!input) return;

        input.addEventListener('input', function () {
            const q = input.value.trim();
            
            // Si el usuario vacía el campo por completo, limpiamos al vuelo
            if (q === '') {
                hidden.value = '';
                sug.style.display = 'none';
                ASIENTOPROG_eliminarAlVuelo(idAsientoTipo, inputId, hiddenId, tipoReferencia, idReferencia);
                return;
            }

            if (q.length < 2) {
                sug.style.display = 'none';
                return;
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                try {
                    const r = await fetch(`${window.BASE_URL}/modulos/plan-cuentas/searchAjaxCuentas?q=${encodeURIComponent(q)}&tipo=${encodeURIComponent(tipoCuenta)}`);
                    const res = await r.json();

                    if (res.ok && res.data && res.data.length > 0) {
                        sug.innerHTML = '';
                        res.data.forEach(item => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-1 px-2 border-0 small';
                            btn.textContent = `${item.codigo} - ${item.nombre}`;
                            btn.addEventListener('click', () => {
                                input.value = `${item.codigo} - ${item.nombre}`;
                                hidden.value = item.id;
                                sug.style.display = 'none';
                                ASIENTOPROG_guardarAlVuelo(idAsientoTipo, item.id, input, tipoReferencia, idReferencia);
                            });
                            sug.appendChild(btn);
                        });
                        sug.style.display = 'block';
                        activeDropdown = sug;
                    } else {
                        sug.style.display = 'none';
                    }
                } catch (e) {
                    console.error(e);
                }
            }, 300);
        });
    }

    /**
     * Registra o actualiza al vuelo una regla general de cuenta contable.
     */
    window.ASIENTOPROG_guardarAlVuelo = async function (idAsientoTipo, idCuenta, inputElement, tipoReferencia = '', idReferencia = 0) {
        if ((!idAsientoTipo && tipoReferencia !== 'iva_ventas_factura' && tipoReferencia !== 'retenciones_venta' && tipoReferencia !== 'retenciones_venta_debe' && tipoReferencia !== 'retenciones_venta_haber') || !idCuenta) return;

        // Añadir indicador visual de carga corta
        inputElement.classList.add('is-valid');
        const origBg = inputElement.style.backgroundColor;
        inputElement.style.backgroundColor = 'rgba(25, 135, 84, 0.08)';

        const fd = new FormData();
        fd.append('id_asiento_tipo', idAsientoTipo.toString());
        fd.append('id_cuenta', idCuenta.toString());
        if (tipoReferencia) fd.append('tipo_referencia', tipoReferencia);
        if (idReferencia) fd.append('id_referencia', idReferencia.toString());

        try {
            const resp = await fetch(`${API_PROG}/guardarReglaGeneralAjax`, {
                method: 'POST',
                body: fd
            });
            const res = await resp.json();

            if (res.ok) {
                inputElement.classList.remove('is-invalid', 'border-danger');
                // Alerta tipo Toast pequeña o destello visual
                if (window.Swal) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: res.msg
                    });
                }
            } else {
                inputElement.classList.remove('is-valid');
                inputElement.classList.add('is-invalid', 'border-danger');
                if (window.Swal) Swal.fire('Error', res.error || 'Error al guardar', 'error');
            }
        } catch (e) {
            console.error(e);
            inputElement.classList.remove('is-valid');
            inputElement.classList.add('is-invalid', 'border-danger');
        } finally {
            setTimeout(() => {
                inputElement.classList.remove('is-valid');
                // Si ya no es inválido (porque se guardó), no le volvemos a poner el is-invalid
                if (inputElement.value !== '') {
                    inputElement.classList.remove('is-invalid', 'border-danger');
                }
                inputElement.style.backgroundColor = origBg;
            }, 2000);
        }
    };

    /**
     * Elimina al vuelo de forma dinámica una regla general de cuenta.
     */
    window.ASIENTOPROG_eliminarAlVuelo = async function (idAsientoTipo, inputId, hiddenId, tipoReferencia = '', idReferencia = 0) {
        if (!idAsientoTipo && tipoReferencia !== 'iva_ventas_factura' && tipoReferencia !== 'retenciones_venta' && tipoReferencia !== 'retenciones_venta_debe' && tipoReferencia !== 'retenciones_venta_haber') return;

        const input = document.getElementById(inputId);
        const hidden = document.getElementById(hiddenId);

        if (hidden && hidden.value === '') {
            // No hay nada asignado, omitimos
            return;
        }

        const fd = new FormData();
        fd.append('id_asiento_tipo', idAsientoTipo.toString());
        if (tipoReferencia) fd.append('tipo_referencia', tipoReferencia);
        if (idReferencia) fd.append('id_referencia', idReferencia.toString());

        try {
            const resp = await fetch(`${API_PROG}/eliminarReglaGeneralAjax`, {
                method: 'POST',
                body: fd
            });
            const res = await resp.json();

            if (res.ok) {
                if (input) {
                    input.value = '';
                    input.classList.add('is-invalid', 'border-danger');
                }
                if (hidden) hidden.value = '';

                if (window.Swal) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'info',
                        title: 'Cuenta desvinculada correctamente.'
                    });
                }
            } else {
                if (window.Swal) Swal.fire('Error', res.error || 'Error al desvincular', 'error');
            }
        } catch (e) {
            console.error(e);
        }
    };

    /**
     * Guarda la preferencia del método de contabilización seleccionado.
     */
    window.ASIENTOPROG_guardarMetodoPreferencia = async function (metodo) {
        const selector = document.getElementById('tipoAsientoSelector');
        const tipoAsiento = selector.value;
        if (!tipoAsiento) return;

        const fd = new FormData();
        fd.append('tipo_asiento', tipoAsiento);
        fd.append('metodo', metodo);

        try {
            const resp = await fetch(`${API_PROG}/guardarMetodoPreferenciaAjax`, {
                method: 'POST',
                body: fd
            });
            const res = await resp.json();
            if (res.ok) {
                if (window.Swal) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: res.msg
                    });
                }
            } else {
                if (window.Swal) Swal.fire('Error', res.error || 'Error al guardar la preferencia.', 'error');
            }
        } catch (e) {
            console.error(e);
        }
    };

    /**
     * Carga dinámicamente las reglas correspondientes a una dimensión contable.
     */
    window.ASIENTOPROG_cargarDim = async function (tipo) {
        const selector = document.getElementById('tipoAsientoSelector');
        const tipoAsiento = selector.value;
        if (!tipoAsiento) return;

        // Renderizar dinámicamente los inputs de cuenta en base a la Configuración General
        const container = document.getElementById(`inputsDinamicos_${tipo}`);
        if (container) {
            container.innerHTML = '';
            if (window.CONCEPTOS_CONFIGURADOS && window.CONCEPTOS_CONFIGURADOS.length > 0) {
                const totalConceptos = window.CONCEPTOS_CONFIGURADOS.length;
                const colClass = totalConceptos <= 2 ? 'col-md-6 mb-2' : (totalConceptos === 3 ? 'col-md-4 mb-2' : 'col-md-3 mb-2');
                window.CONCEPTOS_CONFIGURADOS.forEach(item => {
                    const col = document.createElement('div');
                    col.className = colClass;
                    col.innerHTML = `
                        <label class="form-label small fw-bold text-dark mb-1">
                            <i class="bi bi-journal-bookmark text-primary me-1"></i> ${item.concepto}
                        </label>
                        <div class="position-relative">
                            <input type="text" class="form-control form-control-sm bg-white text-dark" id="dim_cuenta_search_${tipo}_${item.id_asiento_tipo}" placeholder="Buscar cuenta para ${item.concepto}..." autocomplete="off">
                            <input type="hidden" id="dim_cuenta_id_${tipo}_${item.id_asiento_tipo}">
                            <div class="list-group sugerencias-flotantes" id="dim_cuenta_sug_${tipo}_${item.id_asiento_tipo}" style="display: none;"></div>
                        </div>
                    `;
                    container.appendChild(col);
                });
            } else {
                container.innerHTML = '<div class="col-12 text-center text-muted small py-2"><i class="bi bi-info-circle me-1"></i> Debe configurar y cargar conceptos en la sección General primero.</div>';
            }
        }

        const tbody = document.getElementById(`tbodyDim_${tipo}`);
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Cargando asociaciones...</td></tr>';

        try {
            const resp = await fetch(`${API_PROG}/cargarReglasDimensionAjax?tipo_asiento=${tipoAsiento}&tipo_referencia=${tipo}`);
            const res = await resp.json();

            if (res.ok) {
                tbody.innerHTML = '';
                if (res.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-muted">No se han registrado asociaciones para ${tipo}s.</td></tr>`;
                } else {
                    res.data.forEach(item => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="ps-4 fw-bold text-dark">${item.dimension_nombre}</td>
                            <td class="small text-muted">${item.asiento_tipo_referencia}</td>
                            <td class="fw-medium text-primary">${item.cuenta_codigo} - ${item.cuenta_nombre}</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-link text-danger p-0 border-0" onclick="ASIENTOPROG_eliminarDim(${item.id}, '${tipo}')" title="Eliminar Asociación">
                                    <i class="bi bi-trash fs-5"></i>
                                </button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }

                // Inicializar autocompletados de búsqueda de esa dimensión si no han sido vinculados aún
                ASIENTOPROG_vincularDimAutocomplete(tipo);
            } else {
                tbody.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-danger">Error: ${res.error}</td></tr>`;
            }
        } catch (e) {
            console.error(e);
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-danger">Error de conexión al cargar datos.</td></tr>`;
        }
    };

    /**
     * Vincula el comportamiento autocomplete a los buscadores de la dimensión.
     */
    function ASIENTOPROG_vincularDimAutocomplete(tipo) {
        const searchInput = document.getElementById(`dim_search_${tipo}`);
        const hiddenInput = document.getElementById(`dim_id_${tipo}`);
        const sugDiv = document.getElementById(`dim_sug_${tipo}`);

        if (searchInput && !searchInput.dataset.autocompleteBound) {
            searchInput.dataset.autocompleteBound = "true";

            // Autocomplete de Entidad (Cliente, Producto, etc.)
            searchInput.addEventListener('input', function () {
                const q = searchInput.value.trim();
                if (q === '') {
                    hiddenInput.value = '';
                    sugDiv.style.display = 'none';
                    return;
                }
                if (q.length < 2) {
                    sugDiv.style.display = 'none';
                    return;
                }

                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(async () => {
                    try {
                        const r = await fetch(`${API_PROG}/searchEntidadesAjax?tipo=${tipo}&q=${encodeURIComponent(q)}`);
                        const res = await r.json();

                        sugDiv.innerHTML = '';
                        if (res.length > 0) {
                            res.forEach(item => {
                                const btn = document.createElement('button');
                                btn.type = 'button';
                                btn.className = 'list-group-item list-group-item-action py-1 px-2 border-0 small text-dark bg-white';
                                btn.textContent = item.text + (item.identificacion ? ` (${item.identificacion})` : '');
                                btn.addEventListener('click', () => {
                                    searchInput.value = item.text;
                                    hiddenInput.value = item.id;
                                    sugDiv.style.display = 'none';
                                });
                                sugDiv.appendChild(btn);
                            });
                            sugDiv.style.display = 'block';
                            activeDropdown = sugDiv;
                        } else {
                            sugDiv.style.display = 'none';
                        }
                    } catch (e) {
                        console.error(e);
                    }
                }, 300);
            });
        }

        // Autocomplete de Cuentas Contables dinámicas según conceptos configurados
        if (window.CONCEPTOS_CONFIGURADOS) {
            window.CONCEPTOS_CONFIGURADOS.forEach(item => {
                const inputId = `dim_cuenta_search_${tipo}_${item.id_asiento_tipo}`;
                const hiddenId = `dim_cuenta_id_${tipo}_${item.id_asiento_tipo}`;
                const sugId = `dim_cuenta_sug_${tipo}_${item.id_asiento_tipo}`;
                const tipoCuenta = item.tipo_cuenta || '';

                const cInput = document.getElementById(inputId);
                const cHidden = document.getElementById(hiddenId);
                const cSug = document.getElementById(sugId);

                if (!cInput || cInput.dataset.autocompleteBound) return;
                cInput.dataset.autocompleteBound = "true";

                cInput.addEventListener('input', function () {
                    const q = cInput.value.trim();
                    if (q === '') {
                        cHidden.value = '';
                        cSug.style.display = 'none';
                        return;
                    }
                    if (q.length < 2) {
                        cSug.style.display = 'none';
                        return;
                    }

                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(async () => {
                        try {
                            const r = await fetch(`${window.BASE_URL}/modulos/plan-cuentas/searchAjaxCuentas?q=${encodeURIComponent(q)}&tipo=${encodeURIComponent(tipoCuenta)}`);
                            const res = await r.json();

                            cSug.innerHTML = '';
                            if (res.ok && res.data && res.data.length > 0) {
                                res.data.forEach(c => {
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'list-group-item list-group-item-action py-1 px-2 border-0 small text-dark bg-white';
                                    btn.textContent = `${c.codigo} - ${c.nombre}`;
                                    btn.addEventListener('click', () => {
                                        cInput.value = `${c.codigo} - ${c.nombre}`;
                                        cHidden.value = c.id;
                                        cSug.style.display = 'none';
                                    });
                                    cSug.appendChild(btn);
                                });
                                cSug.style.display = 'block';
                                activeDropdown = cSug;
                            } else {
                                cSug.style.display = 'none';
                            }
                        } catch (e) {
                            console.error(e);
                        }
                    }, 300);
                });
            });
        }
    }

    /**
     * Agrega asíncronamente una nueva regla de dimensión.
     */
    window.ASIENTOPROG_agregarDim = async function (e, tipo) {
        e.preventDefault();

        const selector = document.getElementById('tipoAsientoSelector');
        const tipoAsiento = selector.value;
        if (!tipoAsiento) return;

        const idRef = document.getElementById(`dim_id_${tipo}`).value;
        if (!idRef) {
            if (window.Swal) Swal.fire('Atención', 'Debe seleccionar una entidad de la lista desplegable.', 'warning');
            else alert('Debe seleccionar una entidad de la lista desplegable.');
            return;
        }

        const promesas = [];
        let guardoAlgo = false;

        if (window.CONCEPTOS_CONFIGURADOS) {
            window.CONCEPTOS_CONFIGURADOS.forEach(item => {
                const hiddenInput = document.getElementById(`dim_cuenta_id_${tipo}_${item.id_asiento_tipo}`);
                const idCuenta = hiddenInput ? hiddenInput.value : '';

                if (idCuenta) {
                    guardoAlgo = true;
                    const fd = new FormData();
                    fd.append('id_asiento_tipo', item.id_asiento_tipo.toString());
                    fd.append('id_cuenta', idCuenta);
                    fd.append('id_referencia', idRef);
                    fd.append('tipo_referencia', tipo);

                    promesas.push(
                        fetch(`${API_PROG}/guardarReglaDimensionAjax`, {
                            method: 'POST',
                            body: fd
                        }).then(r => r.json())
                    );
                }
            });
        }

        if (!guardoAlgo) {
            if (window.Swal) Swal.fire('Atención', 'Debe asignar al menos una cuenta contable a los conceptos.', 'warning');
            else alert('Debe asignar al menos una cuenta contable a los conceptos.');
            return;
        }

        try {
            const resultados = await Promise.all(promesas);
            const errores = resultados.filter(r => !r.ok);

            if (errores.length === 0) {
                // Limpiar entidad principal
                document.getElementById(`dim_search_${tipo}`).value = '';
                document.getElementById(`dim_id_${tipo}`).value = '';

                if (window.Swal) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Asociaciones guardadas correctamente.'
                    });
                }

                ASIENTOPROG_cargarDim(tipo);
            } else {
                const msgError = errores.map(e => e.error).filter(Boolean).join(' | ');
                if (window.Swal) Swal.fire('Error', msgError || 'Algunas asociaciones no pudieron guardarse.', 'error');
                else alert(msgError || 'Algunas asociaciones no pudieron guardarse.');
            }
        } catch (err) {
            console.error(err);
        }
    };

    /**
     * Elimina una asociación de dimensión específica.
     */
    window.ASIENTOPROG_eliminarDim = async function (idRule, tipo) {
        if (!idRule) return;

        if (window.Swal) {
            const conf = await Swal.fire({
                title: '¿Está seguro de eliminar esta asociación?',
                text: "Esta regla ya no se aplicará para contabilizar de forma específica.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });
            if (!conf.isConfirmed) return;
        } else {
            if (!confirm('¿Está seguro de eliminar esta asociación?')) return;
        }

        const fd = new FormData();
        fd.append('id', idRule.toString());

        try {
            const resp = await fetch(`${API_PROG}/eliminarReglaDimensionAjax`, {
                method: 'POST',
                body: fd
            });
            const res = await resp.json();

            if (res.ok) {
                if (window.Swal) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: res.msg
                    });
                }
                ASIENTOPROG_cargarDim(tipo);
            } else {
                if (window.Swal) Swal.fire('Error', res.error || 'Error al eliminar.', 'error');
            }
        } catch (e) {
            console.error(e);
        }
    };

    /**
     * Guarda la preferencia del método de contabilización seleccionado para el tipo de asiento.
     */
    window.ASIENTOPROG_guardarMetodoPreferencia = async function (metodo) {
        const selector = document.getElementById('tipoAsientoSelector');
        const tipoAsiento = selector ? selector.value : '';
        if (!tipoAsiento) return;

        const fd = new FormData();
        fd.append('tipo_asiento', tipoAsiento);
        fd.append('metodo', metodo);

        try {
            const resp = await fetch(`${API_PROG}/guardarMetodoPreferenciaAjax`, {
                method: 'POST',
                body: fd
            });
            const res = await resp.json();

            if (res.ok) {
                if (window.Swal) {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: res.msg || 'Preferencia guardada correctamente.'
                    });
                }
            } else {
                if (window.Swal) Swal.fire('Error', res.error || 'No se pudo guardar la preferencia.', 'error');
                else alert(res.error || 'No se pudo guardar la preferencia.');
            }
        } catch (e) {
            console.error(e);
        }
    };

    // Cerrar sugerencias flotantes al hacer clic en otra parte de la pantalla
    document.addEventListener('click', function (e) {
        if (activeDropdown && !activeDropdown.contains(e.target) && !e.target.classList.contains('form-control')) {
            activeDropdown.style.display = 'none';
            activeDropdown = null;
        }
    });

})();
