(function () {
    'use strict';

    const API_PROG = `${window.BASE_URL || ''}/modulos/configuracion-contable`;
    let activeDropdown = null;
    let debounceTimer = null;

    // Acordeones de dimensión visibles por tipo de asiento (se muestran todos los aplicables;
    // la resolución de cuentas elige el más específico configurado, cayendo a General).
    const ACORDEONES_DIM = {
        ventas_factura:        ['accItemCliente', 'accItemProducto', 'accItemCategoria', 'accItemMarca'],
        adquisiciones_compras: ['accItemProveedor', 'accItemProducto', 'accItemCategoria', 'accItemMarca'],
        nomina:                ['accItemEmpleado']
    };
    const ACORDEONES_DIM_TODOS = ['accItemCliente', 'accItemProveedor', 'accItemProducto', 'accItemCategoria', 'accItemMarca', 'accItemEmpleado'];

    // ¿La dimensión actual es la regla por NOMBRE del ítem de compra? (producto + adquisiciones_compras).
    // En ese caso la regla se guarda por texto (tipo_referencia='item_compra', clave = descripción del ítem).
    function ASIENTOPROG_esItemCompra(tipo) {
        const ta = (document.getElementById('tipoAsientoSelector') || {}).value || '';
        return tipo === 'producto' && ta === 'adquisiciones_compras';
    }

    function ASIENTOPROG_mostrarAcordeonesDim(tipoAsiento) {
        const visibles = ACORDEONES_DIM[tipoAsiento] || [];
        ACORDEONES_DIM_TODOS.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = visibles.includes(id) ? '' : 'none';
        });
    }

    // Retroceso/Suprimir limpia de golpe cualquier buscador del módulo cuando ya hay un valor
    // seleccionado (input + su hidden + su dropdown de sugerencias). Mientras se escribe una
    // búsqueda (sin selección) el borrado funciona carácter a carácter como siempre.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Backspace' && e.key !== 'Delete') return;
        const el = e.target;
        if (!el || el.tagName !== 'INPUT' || el.type === 'hidden') return;
        const parent = el.parentElement;
        if (!parent) return;
        const hidden = parent.querySelector('input[type="hidden"]');
        const sug = parent.querySelector('.sugerencias-flotantes');
        if (!hidden || !sug) return; // no es un buscador de este módulo
        if (hidden.value) {
            e.preventDefault();
            el.value = '';
            hidden.value = '';
            sug.style.display = 'none';
            el.classList.remove('is-valid', 'is-invalid', 'border-danger');
        }
    });

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
                // Las retenciones (venta y compra) conservan la tabla clásica (doble cuenta por fila).
                const esRetenciones = (tipoAsiento === 'retenciones_venta' || tipoAsiento === 'retenciones_compra');
                const usaDosColumnas = !esRetenciones;
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
                    if (esRetenciones) {
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
                        
                        if (esRetenciones) {
                            // Compra: retención = pasivo (Debe: Cuentas por Pagar · Haber: Retención por pagar).
                            // Venta: retención = activo (Debe: Retención · Haber: Cuentas por Cobrar).
                            const esCompraRet = (tipoAsiento === 'retenciones_compra');
                            const retPrefix = esCompraRet ? 'retenciones_compra' : 'retenciones_venta';
                            const retTipoCuenta = esCompraRet ? 'pasivo' : 'activo';
                            const retBadge = esCompraRet
                                ? '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 py-1 px-2 m-1 small">Pasivo</span>'
                                : '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-1 px-2 m-1 small">Activo</span>';

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
                                <td>${retBadge}</td>
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
                                    <button type="button" class="btn btn-link text-danger p-0 border-0" onclick="ASIENTOPROG_eliminarAlVuelo(0, '${inputDebeId}', '${hiddenDebeId}', '${retPrefix}_debe', ${item.id_referencia}); ASIENTOPROG_eliminarAlVuelo(0, '${inputHaberId}', '${hiddenHaberId}', '${retPrefix}_haber', ${item.id_referencia})" title="Limpiar Cuentas">
                                        <i class="bi bi-trash fs-5"></i>
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(tr);
                            ASIENTOPROG_vincularAutocomplete(0, inputDebeId, hiddenDebeId, sugDebeId, retTipoCuenta, `${retPrefix}_debe`, item.id_referencia);
                            ASIENTOPROG_vincularAutocomplete(0, inputHaberId, hiddenHaberId, sugHaberId, retTipoCuenta, `${retPrefix}_haber`, item.id_referencia);
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

                // Acordeones de dimensión visibles según el tipo de asiento
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
        // Tipos que se guardan sin id_asiento_tipo base (IVA por tarifa y retenciones venta/compra).
        const sinAsientoBase = [
            'iva_ventas_factura', 'iva_compras_factura',
            'retenciones_venta', 'retenciones_venta_debe', 'retenciones_venta_haber',
            'retenciones_compra', 'retenciones_compra_debe', 'retenciones_compra_haber'
        ];
        if ((!idAsientoTipo && !sinAsientoBase.includes(tipoReferencia)) || !idCuenta) return;

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
        const sinAsientoBase = [
            'iva_ventas_factura', 'iva_compras_factura',
            'retenciones_venta', 'retenciones_venta_debe', 'retenciones_venta_haber',
            'retenciones_compra', 'retenciones_compra_debe', 'retenciones_compra_haber'
        ];
        if (!idAsientoTipo && !sinAsientoBase.includes(tipoReferencia)) return;

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
            // Todos los tipos de cuenta (activo, pasivo, patrimonio, ingreso, costo, gasto),
            // igual que Ingresos/Egresos: el selector no filtra por tipo.
            badgesHtml: ASIENTOPROG_badgesTipoCuenta(ASIENTOPROG_TIPOS_TODOS),
            tipoCuentaFiltro: '',
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
     * Carga dinámicamente las reglas correspondientes a una dimensión contable.
     */
    window.ASIENTOPROG_cargarDim = async function (tipo) {
        const selector = document.getElementById('tipoAsientoSelector');
        const tipoAsiento = selector.value;
        if (!tipoAsiento) return;

        // Filtro de años (movimientos de la empresa) para las dimensiones con filtro de año.
        if (tipo === 'proveedor' || tipo === 'cliente' || tipo === 'producto') {
            const modulo = (tipo === 'cliente') ? 'ventas'
                         : (tipo === 'proveedor') ? 'compras'
                         : (tipoAsiento === 'ventas_factura' ? 'ventas' : 'compras');
            try {
                const ra = await fetch(`${API_PROG}/getAniosMovimientosAjax?modulo=${modulo}`);
                const ja = await ra.json();
                const selAnio = document.getElementById(`dim_anio_${tipo}`);
                if (selAnio && ja.ok && Array.isArray(ja.anios)) {
                    const prev = selAnio.value;
                    selAnio.innerHTML = '<option value="">Todos los años</option>' + ja.anios.map(a => `<option value="${a}">${a}</option>`).join('');
                    selAnio.value = ja.anios.map(String).includes(prev) ? prev : '';
                }
            } catch (e) { /* noop */ }
        }

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
            const refType = ASIENTOPROG_esItemCompra(tipo) ? 'item_compra' : tipo;
            const resp = await fetch(`${API_PROG}/cargarReglasDimensionAjax?tipo_asiento=${tipoAsiento}&tipo_referencia=${refType}`);
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

            // Autocomplete de Entidad (Cliente, Proveedor, Producto, Categoría, Marca) por texto.
            // La lista completa con ✓ de configurados se ofrece aparte, en el modal de cada dimensión.
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
                        // Compras + producto → buscar ÍTEMS de compra; la clave de la regla es el nombre (texto).
                        if (ASIENTOPROG_esItemCompra(tipo)) {
                            const ri = await fetch(`${API_PROG}/getItemsComprasAjax?q=${encodeURIComponent(q)}`);
                            const resi = await ri.json();
                            const items = (resi.ok && resi.data) ? resi.data : [];
                            sugDiv.innerHTML = '';
                            if (items.length > 0) {
                                items.slice(0, 60).forEach(it => {
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'list-group-item list-group-item-action py-1 px-2 border-0 small text-dark bg-white d-flex justify-content-between align-items-center';
                                    const badge = (it.configurado == 1)
                                        ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 ms-2"><i class="bi bi-check-circle-fill"></i></span>'
                                        : (it.homologado == 1 ? '<span class="badge bg-secondary bg-opacity-10 text-secondary ms-2">homol.</span>' : '');
                                    const safe = (it.descripcion || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                    btn.innerHTML = `<span>${safe}</span>${badge}`;
                                    btn.addEventListener('click', () => {
                                        searchInput.value = it.descripcion;
                                        hiddenInput.value = it.descripcion; // clave = nombre del ítem
                                        sugDiv.style.display = 'none';
                                    });
                                    sugDiv.appendChild(btn);
                                });
                                sugDiv.style.display = 'block';
                                activeDropdown = sugDiv;
                            } else {
                                sugDiv.style.display = 'none';
                            }
                            return;
                        }

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
    window.ASIENTOPROG_copiarDeGeneral = async function (tipo) {
        const selTipo = document.getElementById('tipoAsientoSelector');
        const tipoAsiento = selTipo ? selTipo.value : '';

        // Releer la configuración General MÁS RECIENTE (puede haber cambiado en esta sesión sin
        // recargar la página). Así se copian también las cuentas recién asignadas en General.
        let conceptos = window.CONCEPTOS_CONFIGURADOS || [];
        if (tipoAsiento) {
            try {
                const resp = await fetch(`${API_PROG}/cargarConfiguracionAjax?tipo_asiento=${tipoAsiento}`);
                const res = await resp.json();
                if (res.ok && Array.isArray(res.data)) {
                    conceptos = res.data;
                    window.CONCEPTOS_CONFIGURADOS = res.data;
                }
            } catch (e) { /* fallback: usar lo que ya está en memoria */ }
        }

        const aplicables = conceptos.filter(c => parseInt(c.id_asiento_tipo) > 0 && c.id_cuenta);
        const sinCuenta  = conceptos.filter(c => parseInt(c.id_asiento_tipo) > 0 && !c.id_cuenta).length;
        let copiadas = 0;
        aplicables.forEach(item => {
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
            const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2600, timerProgressBar: true });
            let msg;
            if (copiadas === 0) {
                msg = 'No hay cuentas configuradas en General para copiar.';
            } else {
                msg = `Se copiaron ${copiadas} cuenta(s) de General.`;
                if (sinCuenta > 0) msg += ` (${sinCuenta} concepto(s) siguen sin cuenta en General: configúrelos allí para poder copiarlos.)`;
            }
            Toast.fire({ icon: copiadas > 0 ? 'success' : 'info', title: msg });
        }
    };

    // Etiquetas para el título del modal de lista, según la dimensión.
    const ETIQUETA_ENTIDAD = {
        cliente:   'Clientes con ventas',
        proveedor: 'Proveedores con compras',
        producto:  'Productos con movimientos',
        categoria: 'Categorías',
        marca:     'Marcas',
        empleado:  'Empleados activos'
    };

    /**
     * Abre un modal con la lista de entidades de la dimensión (cliente/proveedor/producto/categoría/
     * marca), marcando con ✓ las que ya tienen cuentas. Al hacer clic, la selecciona y cierra el modal.
     */
    window.ASIENTOPROG_abrirModalEntidades = async function (tipo) {
        const modalEl  = document.getElementById('modalProveedoresCompras');
        const lista    = document.getElementById('modalProvLista');
        const buscador = document.getElementById('modalProvSearch');
        const titulo   = document.getElementById('modalEntidadesTitulo');
        if (!modalEl || !lista) return;

        // Para producto, el listado depende del módulo: en compras son los productos HOMOLOGADOS
        // (los ítems de compra son texto libre y entran al catálogo vía homologación); en ventas, los vendidos.
        const tipoAsiento = (document.getElementById('tipoAsientoSelector') || {}).value || '';
        const modulo = (tipoAsiento === 'ventas_factura') ? 'ventas' : 'compras';
        let etiqueta = ETIQUETA_ENTIDAD[tipo] || 'Entidades';
        if (tipo === 'producto') etiqueta = (modulo === 'compras') ? 'Productos homologados (compras)' : 'Productos vendidos';

        if (titulo) titulo.innerHTML = `<i class="bi bi-card-list me-1 text-primary"></i> ${etiqueta}`;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        lista.innerHTML = '<div class="text-muted small py-3 text-center"><span class="spinner-border spinner-border-sm me-1"></span> Cargando...</div>';

        // En compras, la regla por producto se basa en los ÍTEMS de las compras (texto libre), no en
        // el catálogo. El modal los lista con una columna que indica si están homologados (informativa).
        if (tipo === 'producto' && modulo === 'compras') {
            if (titulo) titulo.innerHTML = '<i class="bi bi-card-list me-1 text-primary"></i> Ítems de compras';
            const esc = (s) => (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            let items = [];
            const pintarItems = (arr) => {
                lista.innerHTML = '';
                if (!arr.length) { lista.innerHTML = '<div class="text-muted small py-3 text-center">Sin ítems.</div>'; return; }
                arr.forEach(it => {
                    const row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 gap-2';
                    const cfg = (it.configurado == 1)
                        ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-check-circle-fill me-1"></i>con cuentas</span>'
                        : '';
                    const homol = (it.homologado == 1)
                        ? '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">Homologado</span>'
                        : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Sin homologar</span>';
                    row.innerHTML = `<span class="small text-truncate" title="${esc(it.descripcion)}">${esc(it.descripcion)}</span><span class="text-nowrap d-flex gap-1">${cfg}${homol}</span>`;
                    row.addEventListener('click', () => {
                        const s = document.getElementById('dim_search_producto');
                        const h = document.getElementById('dim_id_producto');
                        if (s) { s.value = it.descripcion; s.classList.remove('is-invalid', 'border-danger'); }
                        if (h) h.value = it.descripcion; // clave de la regla = nombre del ítem
                        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    });
                    lista.appendChild(row);
                });
            };
            try {
                const r = await fetch(`${API_PROG}/getItemsComprasAjax`);
                const res = await r.json();
                items = (res.ok && res.data) ? res.data : [];
                pintarItems(items);
            } catch (e) {
                lista.innerHTML = '<div class="text-danger small py-3 text-center">Error al cargar ítems.</div>';
            }
            if (buscador) {
                buscador.value = '';
                buscador.oninput = () => {
                    const q = buscador.value.trim().toLowerCase();
                    pintarItems(items.filter(it => (it.descripcion || '').toLowerCase().includes(q)));
                };
            }
            return;
        }

        let datos = [];
        const pintar = (arr) => {
            lista.innerHTML = '';
            if (!arr.length) { lista.innerHTML = '<div class="text-muted small py-3 text-center">Sin resultados.</div>'; return; }
            arr.forEach(p => {
                const a = document.createElement('button');
                a.type = 'button';
                a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2';
                const cfg = (p.configurado == 1) ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-check-circle-fill me-1"></i>con cuentas</span>' : '';
                a.innerHTML = `<span>${(p.nombre || '')}${p.identificacion ? ` <span class="text-muted">(${p.identificacion})</span>` : ''}</span>${cfg}`;
                a.addEventListener('click', () => {
                    const s = document.getElementById(`dim_search_${tipo}`);
                    const h = document.getElementById(`dim_id_${tipo}`);
                    if (s) { s.value = p.nombre; s.classList.remove('is-invalid', 'border-danger'); }
                    if (h) h.value = p.id;
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                });
                lista.appendChild(a);
            });
        };

        try {
            let url = `${API_PROG}/getEntidadesDimensionAjax?tipo=${encodeURIComponent(tipo)}`;
            if (tipo === 'producto') url += `&modulo=${encodeURIComponent(modulo)}`;
            const r = await fetch(url);
            const res = await r.json();
            datos = (res.ok && res.data) ? res.data : [];
            pintar(datos);
        } catch (e) {
            lista.innerHTML = '<div class="text-danger small py-3 text-center">Error al cargar.</div>';
        }

        if (buscador) {
            buscador.value = '';
            buscador.oninput = () => {
                const q = buscador.value.trim().toLowerCase();
                pintar(datos.filter(p => (p.nombre || '').toLowerCase().includes(q) || (p.identificacion || '').toLowerCase().includes(q)));
            };
        }
    };

    /**
     * Abre un modal con las descripciones ÚNICAS de los ítems transados con la entidad seleccionada
     * (proveedor → compras; cliente → ventas), filtradas por el año elegido. Solo para cliente/proveedor.
     */
    window.ASIENTOPROG_abrirModalItems = async function (tipo) {
        const idEl     = document.getElementById(`dim_id_${tipo}`);
        const nombreEl = document.getElementById(`dim_search_${tipo}`);
        const anioSel  = document.getElementById(`dim_anio_${tipo}`);
        const modalEl  = document.getElementById('modalItemsProveedor');
        const body     = document.getElementById('modalItemsBody');
        const titProv  = document.getElementById('modalItemsProvNombre');
        if (!modalEl || !body) return;

        const idEnt = idEl ? idEl.value : '';
        if (!idEnt) {
            if (window.Swal) Swal.fire('Atención', 'Seleccione primero una entidad.', 'warning');
            else alert('Seleccione primero una entidad.');
            return;
        }
        const anio = anioSel ? anioSel.value : '';
        if (titProv) titProv.textContent = (nombreEl && nombreEl.value) ? `· ${nombreEl.value}` : '';
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        body.innerHTML = '<div class="text-muted small py-3 text-center"><span class="spinner-border spinner-border-sm me-1"></span> Cargando ítems...</div>';

        try {
            const r = await fetch(`${API_PROG}/getItemsEntidadAjax?tipo=${encodeURIComponent(tipo)}&id=${encodeURIComponent(idEnt)}&anio=${encodeURIComponent(anio)}`);
            const res = await r.json();
            if (!res.ok) { body.innerHTML = `<div class="text-danger small">${res.error || 'Error al cargar ítems.'}</div>`; return; }
            const items = res.data || [];
            if (items.length === 0) {
                body.innerHTML = `<div class="text-muted small py-3 text-center">Sin ítems${anio ? ` en ${anio}` : ''}.</div>`;
                return;
            }
            const esc = (s) => (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            body.innerHTML = `<div class="small fw-bold text-muted mb-2">${items.length} ítem(s)${anio ? ` en ${anio}` : ' (todos los años)'}:</div>`
                + '<div class="d-flex flex-column gap-1">' + items.map(d => `<div class="border rounded px-2 py-1 small">${esc(d)}</div>`).join('') + '</div>';
        } catch (e) {
            body.innerHTML = '<div class="text-danger small">Error al cargar ítems.</div>';
        }
    };

    window.ASIENTOPROG_agregarDim = async function (e, tipo) {
        e.preventDefault();

        const selector = document.getElementById('tipoAsientoSelector');
        const tipoAsiento = selector.value;
        if (!tipoAsiento) return;

        const esItem = ASIENTOPROG_esItemCompra(tipo);
        const idRef = document.getElementById(`dim_id_${tipo}`).value;
        if (!idRef) {
            const msg = esItem ? 'Debe seleccionar un ítem de compra de la lista.' : 'Debe seleccionar una entidad de la lista desplegable.';
            if (window.Swal) Swal.fire('Atención', msg, 'warning');
            else alert(msg);
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
                    if (esItem) {
                        // La clave de la regla es el NOMBRE del ítem (texto), no un id.
                        fd.append('tipo_referencia', 'item_compra');
                        fd.append('referencia_texto', idRef);
                    } else {
                        fd.append('id_referencia', idRef);
                        fd.append('tipo_referencia', tipo);
                    }

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


    // Cerrar sugerencias flotantes al hacer clic en otra parte de la pantalla
    document.addEventListener('click', function (e) {
        if (activeDropdown && !activeDropdown.contains(e.target) && !e.target.classList.contains('form-control')) {
            activeDropdown.style.display = 'none';
            activeDropdown = null;
        }
    });

})();
