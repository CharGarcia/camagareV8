(function () {
    'use strict';

    const API_PROG = `${window.BASE_URL || ''}/modulos/configuracion-contable`;
    let activeDropdown = null;
    let debounceTimer = null;

    // Métodos de "Contabilizar Por" disponibles por tipo de asiento (Fase A).
    const METODOS_DIM = {
        ventas_factura:        [['general', 'General (Por Defecto)'], ['cliente', 'Por Cliente'], ['producto', 'Por Producto/Servicio'], ['categoria', 'Por Categoría'], ['marca', 'Por Marca']],
        adquisiciones_compras: [['general', 'General (Por Defecto)'], ['proveedor', 'Por Proveedor'], ['producto', 'Por Producto/Servicio'], ['categoria', 'Por Categoría'], ['marca', 'Por Marca']]
    };
    // Acordeones de dimensión visibles por tipo de asiento.
    const ACORDEONES_DIM = {
        ventas_factura:        ['accItemCliente', 'accItemProducto', 'accItemCategoria', 'accItemMarca'],
        adquisiciones_compras: ['accItemProveedor', 'accItemProducto', 'accItemCategoria', 'accItemMarca']
    };
    const ACORDEONES_DIM_TODOS = ['accItemCliente', 'accItemProveedor', 'accItemProducto', 'accItemCategoria', 'accItemMarca'];

    function ASIENTOPROG_poblarSelectorMetodo(tipoAsiento, metodoActual) {
        const sel = document.getElementById('selectorMetodoPreferencia');
        if (!sel) return;
        const opciones = METODOS_DIM[tipoAsiento] || [['general', 'General (Por Defecto)']];
        sel.innerHTML = opciones.map(([v, t]) => `<option value="${v}">${t}</option>`).join('');
        const valido = opciones.some(([v]) => v === metodoActual);
        sel.value = valido ? metodoActual : 'general';
    }

    function ASIENTOPROG_mostrarAcordeonesDim(tipoAsiento) {
        const visibles = ACORDEONES_DIM[tipoAsiento] || [];
        ACORDEONES_DIM_TODOS.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = visibles.includes(id) ? '' : 'none';
        });
    }

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
                // Modos especiales con dos acordeones (referencias de otros módulos)
                if (res.modo === 'ingresos_egresos') {
                    ASIENTOPROG_renderModoIngresoEgreso(res, selector);
                    return;
                }
                if (res.modo === 'cobros_pagos') {
                    ASIENTOPROG_renderModoCobroPago(res, selector);
                    return;
                }

                // Resto de tipos: acordeón general estándar (asegurar visibilidad)
                const accGeneral = document.getElementById('acordeonConfiguracion');
                if (accGeneral) accGeneral.style.display = '';
                ['acordeonIngresoEgreso', 'acordeonCobroPago'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.style.display = 'none';
                });

                window.CONCEPTOS_CONFIGURADOS = res.data || [];
                const tbody = document.getElementById('tbodyConfiguracionGeneral');
                tbody.innerHTML = '';

                // Layout: ventas/compras y demás conceptos de naturaleza fija → dos columnas (Debe | Haber).
                // retenciones_venta conserva la tabla clásica (doble cuenta por fila).
                const usaDosColumnas = (tipoAsiento !== 'retenciones_venta');
                const dosColCont = document.getElementById('dosColumnasGeneral');
                const tablaWrap  = document.getElementById('tablaGeneralWrap');
                const colDebe    = document.getElementById('colDebeGeneral');
                const colHaber   = document.getElementById('colHaberGeneral');
                if (dosColCont) dosColCont.style.display = usaDosColumnas ? '' : 'none';
                if (tablaWrap)  tablaWrap.style.display  = usaDosColumnas ? 'none' : '';
                if (usaDosColumnas) {
                    if (colDebe)  colDebe.innerHTML  = '';
                    if (colHaber) colHaber.innerHTML = '';
                }

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
                    if (usaDosColumnas) {
                        const vacio = '<div class="text-center py-4 text-muted small"><i class="bi bi-info-circle me-1"></i> Sin conceptos.</div>';
                        if (colDebe)  colDebe.innerHTML  = vacio;
                        if (colHaber) colHaber.innerHTML = '';
                    } else {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-info-circle me-1"></i> No hay reglas predefinidas para este tipo de asiento.</td></tr>';
                    }
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
                            const safeSuffix = (item.tipo_referencia === 'iva_ventas_factura' || item.tipo_referencia === 'iva_compras_factura') ? `iva_${item.id_referencia}` : `at_${item.id_asiento_tipo}`;
                            const inputId = `cuenta_search_${safeSuffix}`;
                            const hiddenId = `cuenta_hidden_${safeSuffix}`;
                            const sugId = `sug_${safeSuffix}`;

                            const cuentaVal = item.id_cuenta ? `${item.cuenta_codigo} - ${item.cuenta_nombre}` : '';
                            const idCuentaVal = item.id_cuenta || '';
                            const borderClass = idCuentaVal ? '' : 'is-invalid border-danger';

                            // Cada concepto se ubica en su columna natural (Debe o Haber).
                            const esDebe = (item.debe_haber || 'debe').toLowerCase() === 'debe';
                            const cont = esDebe ? colDebe : colHaber;
                            if (!cont) return;

                            const card = document.createElement('div');
                            card.className = 'px-3 py-2 border-top';
                            card.innerHTML = `
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div class="pe-2">
                                        <div class="fw-bold text-dark small">${item.concepto}</div>
                                        ${item.detalle ? `<div class="text-muted" style="font-size:0.72rem;">${item.detalle}</div>` : ''}
                                    </div>
                                    <button type="button" class="btn btn-link text-danger p-0 border-0" onclick="ASIENTOPROG_eliminarAlVuelo(${item.id_asiento_tipo || 0}, '${inputId}', '${hiddenId}', '${item.tipo_referencia || ''}', ${item.id_referencia || 0})" title="Limpiar cuenta">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <div class="autocomplete-celda position-relative">
                                    <input type="text" class="form-control form-control-sm ${borderClass}" id="${inputId}" placeholder="Buscar cuenta..." value="${cuentaVal}" autocomplete="off">
                                    <input type="hidden" id="${hiddenId}" value="${idCuentaVal}">
                                    <div class="list-group sugerencias-flotantes" id="${sugId}" style="display: none;"></div>
                                </div>
                            `;
                            cont.appendChild(card);

                            // Configurar autocompletado en caliente para este input filtrando por tipo de cuenta
                            ASIENTOPROG_vincularAutocomplete(item.id_asiento_tipo, inputId, hiddenId, sugId, item.tipo_cuenta || '', item.tipo_referencia || '', item.id_referencia || 0);
                        }
                    });
                }

                // Selector "Contabilizar Por" + acordeones de dimensión según el tipo de asiento
                ASIENTOPROG_poblarSelectorMetodo(tipoAsiento, res.metodo || 'general');
                ASIENTOPROG_mostrarAcordeonesDim(tipoAsiento);

                // Actualizar título y mostrar panel de acordeones
                const selectedText = selector.options[selector.selectedIndex].text;
                document.getElementById('conceptoSeleccionadoTitulo').innerHTML = `<i class="bi bi-gear-fill text-primary me-1"></i> Configuración para: <span class="text-primary fw-bold">${selectedText}</span>`;
                document.getElementById('seccionAcordeones').style.display = 'block';

                // Cerrar acordeones colapsados por defecto (excepto el general)
                ['Clientes', 'Proveedores', 'Productos', 'Categorias', 'Marcas', 'Ivas'].forEach(dim => {
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
        if ((!idAsientoTipo && tipoReferencia !== 'iva_ventas_factura' && tipoReferencia !== 'iva_compras_factura' && tipoReferencia !== 'retenciones_venta' && tipoReferencia !== 'retenciones_venta_debe' && tipoReferencia !== 'retenciones_venta_haber') || !idCuenta) return;

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
        if (!idAsientoTipo && tipoReferencia !== 'iva_ventas_factura' && tipoReferencia !== 'iva_compras_factura' && tipoReferencia !== 'retenciones_venta' && tipoReferencia !== 'retenciones_venta_debe' && tipoReferencia !== 'retenciones_venta_haber') return;

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

    /* ============================================================
       MODOS ESPECIALES (referencias de otros módulos)
       Render genérico de dos acordeones con asignación de cuenta al vuelo.
       Usado por: Ingresos y Egresos (Opciones) y Cobros y Pagos (Formas).
       ============================================================ */

    /**
     * Escapa texto para insertarlo de forma segura en HTML.
     */
    function ASIENTOPROG_esc(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, s => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[s]));
    }

    /**
     * Toast breve de SweetAlert (si está disponible).
     */
    function ASIENTOPROG_toast(icon, title) {
        if (!window.Swal) return;
        Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, timerProgressBar: true })
            .fire({ icon, title });
    }

    // Tipos de cuenta para los badges informativos (quemados)
    const ASIENTOPROG_TIPOS_TODOS = [
        ['Activo', 'success'], ['Pasivo', 'danger'], ['Patrimonio', 'dark'],
        ['Ingresos', 'primary'], ['Costos', 'info'], ['Gastos', 'warning']
    ];
    const ASIENTOPROG_TIPOS_ACTIVO = [['Activo', 'success']];

    /**
     * Construye los badges informativos de tipo de cuenta.
     */
    function ASIENTOPROG_badgesTipoCuenta(tipos) {
        return tipos.map(([label, color]) =>
            `<span class="badge bg-${color} bg-opacity-10 text-${color} border border-${color} border-opacity-25 py-1 px-1 me-1 mb-1 small">${label}</span>`
        ).join('');
    }

    /**
     * Badge de naturaleza contable ('debe' | 'haber').
     */
    function ASIENTOPROG_naturalezaBadge(naturaleza) {
        return naturaleza === 'haber'
            ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-1 px-2 fw-bold small">HABER</span>'
            : '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-1 px-2 fw-bold small">DEBE</span>';
    }

    /**
     * Prepara la vista para un modo especial: oculta el acordeón general y los demás
     * contenedores especiales, muestra el solicitado, fija título y despliega el panel.
     */
    function ASIENTOPROG_prepararModoEspecial(idContenedor, selector) {
        window.CONCEPTOS_CONFIGURADOS = [];

        const accGeneral = document.getElementById('acordeonConfiguracion');
        if (accGeneral) accGeneral.style.display = 'none';

        ['acordeonIngresoEgreso', 'acordeonCobroPago'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = (id === idContenedor) ? 'block' : 'none';
        });

        const selectorMetodo = document.getElementById('selectorMetodoPreferencia');
        if (selectorMetodo) selectorMetodo.value = 'general';

        const selectedText = selector.options[selector.selectedIndex].text;
        document.getElementById('conceptoSeleccionadoTitulo').innerHTML =
            `<i class="bi bi-gear-fill text-primary me-1"></i> Configuración para: <span class="text-primary fw-bold">${selectedText}</span>`;

        const seccion = document.getElementById('seccionAcordeones');
        seccion.style.display = 'block';
        seccion.scrollIntoView({ behavior: 'smooth' });
    }

    /**
     * Modo Ingresos y Egresos (desde Opciones de Ingreso/Egreso).
     */
    function ASIENTOPROG_renderModoIngresoEgreso(res, selector) {
        ASIENTOPROG_prepararModoEspecial('acordeonIngresoEgreso', selector);

        const base = {
            idKey: 'id_opcion', refParam: 'id_opcion', selectorParam: 'naturaleza',
            detalle: 'Configurado en Opciones de Ingresos y Egresos',
            badgesHtml: ASIENTOPROG_badgesTipoCuenta(ASIENTOPROG_TIPOS_TODOS),
            tipoCuentaFiltro: '',
            endpointGuardar: 'guardarReglaOpcionAjax', endpointEliminar: 'eliminarReglaOpcionAjax'
        };

        ASIENTOPROG_renderReferencias(res.ingresos || [], 'tbodyOpcIngresos', Object.assign({}, base, {
            prefijo: 'opc_ingreso', selectorValor: 'ingreso',
            naturalezaBadge: ASIENTOPROG_naturalezaBadge('haber'),
            vacioMsg: 'No hay opciones de ingreso activas. Créelas en el módulo "Opciones de Ingresos y Egresos".'
        }));
        ASIENTOPROG_renderReferencias(res.egresos || [], 'tbodyOpcEgresos', Object.assign({}, base, {
            prefijo: 'opc_egreso', selectorValor: 'egreso',
            naturalezaBadge: ASIENTOPROG_naturalezaBadge('debe'),
            vacioMsg: 'No hay opciones de egreso activas. Créelas en el módulo "Opciones de Ingresos y Egresos".'
        }));
    }

    /**
     * Modo Cobros y Pagos (desde Formas de Cobros/Pagos).
     */
    function ASIENTOPROG_renderModoCobroPago(res, selector) {
        ASIENTOPROG_prepararModoEspecial('acordeonCobroPago', selector);

        const base = {
            idKey: 'id_forma', refParam: 'id_forma', selectorParam: 'flujo',
            detalle: 'Configurado en Formas de Cobros y Pagos',
            badgesHtml: ASIENTOPROG_badgesTipoCuenta(ASIENTOPROG_TIPOS_ACTIVO),
            tipoCuentaFiltro: 'activo',
            endpointGuardar: 'guardarReglaFormaAjax', endpointEliminar: 'eliminarReglaFormaAjax'
        };

        ASIENTOPROG_renderReferencias(res.cobros || [], 'tbodyFormaCobros', Object.assign({}, base, {
            prefijo: 'forma_cobro', selectorValor: 'cobro',
            naturalezaBadge: ASIENTOPROG_naturalezaBadge('debe'),
            vacioMsg: 'No hay formas de cobro activas. Créelas en el módulo "Formas de Cobros y Pagos".'
        }));
        ASIENTOPROG_renderReferencias(res.pagos || [], 'tbodyFormaPagos', Object.assign({}, base, {
            prefijo: 'forma_pago', selectorValor: 'pago',
            naturalezaBadge: ASIENTOPROG_naturalezaBadge('haber'),
            vacioMsg: 'No hay formas de pago activas. Créelas en el módulo "Formas de Cobros y Pagos".'
        }));
    }

    /**
     * Render genérico de filas de referencias (opciones o formas) en una tabla.
     * Cada fila permite asignar una cuenta contable con autocompletado al vuelo.
     */
    function ASIENTOPROG_renderReferencias(lista, tbodyId, cfg) {
        const tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!lista || lista.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-info-circle me-1"></i> ${cfg.vacioMsg}</td></tr>`;
            return;
        }

        lista.forEach(item => {
            const idRef = item[cfg.idKey];
            const suffix = `${cfg.prefijo}_${idRef}`;
            const inputId = `cuenta_search_${suffix}`;
            const hiddenId = `cuenta_hidden_${suffix}`;
            const sugId = `sug_${suffix}`;

            const idCuentaVal = item.id_cuenta || '';
            const cuentaVal = item.id_cuenta ? `${item.cuenta_codigo} - ${item.cuenta_nombre}` : '';
            const borderClass = idCuentaVal ? '' : 'is-invalid border-danger';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="ps-4 fw-bold text-dark">${ASIENTOPROG_esc(item.concepto)}</td>
                <td class="small text-muted">${cfg.detalle}</td>
                <td>${cfg.badgesHtml}</td>
                <td class="text-center">${cfg.naturalezaBadge}</td>
                <td class="autocomplete-celda">
                    <input type="text" class="form-control form-control-sm ${borderClass}" id="${inputId}" placeholder="Escriba código o nombre..." value="${ASIENTOPROG_esc(cuentaVal)}" autocomplete="off">
                    <input type="hidden" id="${hiddenId}" value="${idCuentaVal}">
                    <div class="list-group sugerencias-flotantes" id="${sugId}" style="display: none;"></div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-link text-danger p-0 border-0 btn-eliminar-ref" title="Quitar cuenta">
                        <i class="bi bi-trash fs-5"></i>
                    </button>
                </td>
            `;
            const btnDel = tr.querySelector('.btn-eliminar-ref');
            if (btnDel) btnDel.addEventListener('click', () => ASIENTOPROG_eliminarRefAlVuelo(idRef, inputId, hiddenId, cfg));
            tbody.appendChild(tr);

            ASIENTOPROG_vincularAutoRef(idRef, inputId, hiddenId, sugId, cfg);
        });
    }

    /**
     * Autocompletado de cuenta contable para una referencia, filtrado por tipo según cfg.
     */
    function ASIENTOPROG_vincularAutoRef(idRef, inputId, hiddenId, sugId, cfg) {
        const input = document.getElementById(inputId);
        const hidden = document.getElementById(hiddenId);
        const sug = document.getElementById(sugId);
        if (!input) return;

        input.addEventListener('input', function () {
            const q = input.value.trim();

            if (q === '') {
                hidden.value = '';
                sug.style.display = 'none';
                ASIENTOPROG_eliminarRefAlVuelo(idRef, inputId, hiddenId, cfg);
                return;
            }
            if (q.length < 2) {
                sug.style.display = 'none';
                return;
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                try {
                    const r = await fetch(`${window.BASE_URL}/modulos/plan-cuentas/searchAjaxCuentas?q=${encodeURIComponent(q)}&tipo=${encodeURIComponent(cfg.tipoCuentaFiltro)}`);
                    const res = await r.json();

                    if (res.ok && res.data && res.data.length > 0) {
                        sug.innerHTML = '';
                        res.data.forEach(c => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-1 px-2 border-0 small';
                            btn.textContent = `${c.codigo} - ${c.nombre}`;
                            btn.addEventListener('click', () => {
                                input.value = `${c.codigo} - ${c.nombre}`;
                                hidden.value = c.id;
                                sug.style.display = 'none';
                                ASIENTOPROG_guardarRefAlVuelo(idRef, c.id, input, cfg);
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
     * Guarda al vuelo la cuenta contable asignada a una referencia.
     */
    async function ASIENTOPROG_guardarRefAlVuelo(idRef, idCuenta, inputElement, cfg) {
        if (!idRef || !idCuenta) return;

        inputElement.classList.add('is-valid');
        const origBg = inputElement.style.backgroundColor;
        inputElement.style.backgroundColor = 'rgba(25, 135, 84, 0.08)';

        const fd = new FormData();
        fd.append(cfg.refParam, idRef.toString());
        fd.append('id_cuenta', idCuenta.toString());
        fd.append(cfg.selectorParam, cfg.selectorValor);

        try {
            const resp = await fetch(`${API_PROG}/${cfg.endpointGuardar}`, { method: 'POST', body: fd });
            const res = await resp.json();

            if (res.ok) {
                inputElement.classList.remove('is-invalid', 'border-danger');
                ASIENTOPROG_toast('success', res.msg);
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
                if (inputElement.value !== '') {
                    inputElement.classList.remove('is-invalid', 'border-danger');
                }
                inputElement.style.backgroundColor = origBg;
            }, 2000);
        }
    }

    /**
     * Quita al vuelo la cuenta contable de una referencia.
     */
    async function ASIENTOPROG_eliminarRefAlVuelo(idRef, inputId, hiddenId, cfg) {
        const input = document.getElementById(inputId);
        const hidden = document.getElementById(hiddenId);

        if (hidden && hidden.value === '') {
            return;
        }

        const fd = new FormData();
        fd.append(cfg.refParam, idRef.toString());
        fd.append(cfg.selectorParam, cfg.selectorValor);

        try {
            const resp = await fetch(`${API_PROG}/${cfg.endpointEliminar}`, { method: 'POST', body: fd });
            const res = await resp.json();

            if (res.ok) {
                if (input) {
                    input.value = '';
                    input.classList.add('is-invalid', 'border-danger');
                }
                if (hidden) hidden.value = '';
                ASIENTOPROG_toast('info', res.msg || 'Cuenta desvinculada correctamente.');
            } else {
                if (window.Swal) Swal.fire('Error', res.error || 'Error al desvincular', 'error');
            }
        } catch (e) {
            console.error(e);
        }
    }

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

        // Renderizar los inputs de cuenta en dos columnas (Debe | Haber), igual que la sección General.
        // Se excluye el IVA por tarifa (id_asiento_tipo = 0): ese se configura en General, no por dimensión.
        const container = document.getElementById(`inputsDinamicos_${tipo}`);
        if (container) {
            const conceptos = (window.CONCEPTOS_CONFIGURADOS || []).filter(c => parseInt(c.id_asiento_tipo) > 0);
            if (conceptos.length > 0) {
                const tarjeta = (item) => `
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-dark mb-1"><i class="bi bi-journal-bookmark text-primary me-1"></i> ${item.concepto}</label>
                        <div class="position-relative">
                            <input type="text" class="form-control form-control-sm bg-white text-dark" id="dim_cuenta_search_${tipo}_${item.id_asiento_tipo}" placeholder="Buscar cuenta..." autocomplete="off">
                            <input type="hidden" id="dim_cuenta_id_${tipo}_${item.id_asiento_tipo}">
                            <div class="list-group sugerencias-flotantes" id="dim_cuenta_sug_${tipo}_${item.id_asiento_tipo}" style="display: none;"></div>
                        </div>
                    </div>`;
                const esDebe = (c) => (c.debe_haber || 'debe').toLowerCase() === 'debe';
                const debeHtml  = conceptos.filter(esDebe).map(tarjeta).join('')          || '<div class="text-muted small py-1">Sin conceptos.</div>';
                const haberHtml = conceptos.filter(c => !esDebe(c)).map(tarjeta).join('')  || '<div class="text-muted small py-1">Sin conceptos.</div>';
                container.innerHTML = `
                    <div class="col-12 mb-1 d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-secondary btn-sm fw-bold" onclick="ASIENTOPROG_copiarDeGeneral('${tipo}')">
                            <i class="bi bi-clipboard-check me-1"></i> Copiar cuentas de General
                        </button>
                        <span class="small text-muted">Precarga las cuentas de la configuración General; luego ajuste solo las que necesite.</span>
                    </div>
                    <div class="col-md-6">
                        <div class="fw-bold small mb-2 px-2 py-1 rounded" style="background:#E6F1FB; color:#0C447C;"><i class="bi bi-arrow-down-right me-1"></i> Debe</div>
                        ${debeHtml}
                    </div>
                    <div class="col-md-6">
                        <div class="fw-bold small mb-2 px-2 py-1 rounded" style="background:#FAEEDA; color:#633806;"><i class="bi bi-arrow-up-right me-1"></i> Haber</div>
                        ${haberHtml}
                    </div>`;
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
    /**
     * Copia las cuentas configuradas en la sección General a los inputs de la dimensión activa
     * (Cliente/Proveedor/Producto/Categoría/Marca), para que el usuario solo ajuste lo que quiera.
     * Excluye el IVA por tarifa (id_asiento_tipo = 0), que se configura en General.
     */
    window.ASIENTOPROG_copiarDeGeneral = function (tipo) {
        const conceptos = (window.CONCEPTOS_CONFIGURADOS || []).filter(c => parseInt(c.id_asiento_tipo) > 0 && c.id_cuenta);
        let copiadas = 0;
        conceptos.forEach(item => {
            const search = document.getElementById(`dim_cuenta_search_${tipo}_${item.id_asiento_tipo}`);
            const hidden = document.getElementById(`dim_cuenta_id_${tipo}_${item.id_asiento_tipo}`);
            if (search && hidden) {
                search.value = `${item.cuenta_codigo} - ${item.cuenta_nombre}`;
                hidden.value = item.id_cuenta;
                search.classList.remove('is-invalid', 'border-danger');
                copiadas++;
            }
        });
        if (window.Swal) {
            const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2200, timerProgressBar: true });
            Toast.fire({
                icon: copiadas > 0 ? 'success' : 'info',
                title: copiadas > 0
                    ? `Se copiaron ${copiadas} cuenta(s) de General. Ajuste las que necesite y guarde.`
                    : 'No hay cuentas configuradas en General para copiar.'
            });
        }
    };

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
