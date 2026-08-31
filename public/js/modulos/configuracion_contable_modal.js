(function () {
    'use strict';

    const API_PROG = `${window.BASE_URL || ''}/modulos/configuracion-contable`;
    let activeDropdown = null;
    let debounceTimer = null;

    // Acordeones de dimensión visibles por tipo de asiento (se muestran todos los aplicables;
    // la resolución de cuentas elige el más específico configurado, cayendo a General).
    const ACORDEONES_DIM = {
        ventas_factura:        ['accItemCliente', 'accItemProducto', 'accItemCategoria', 'accItemMarca', 'accItemTipoProduccion'],
        recibos_venta:         ['accItemCliente', 'accItemProducto', 'accItemCategoria', 'accItemMarca', 'accItemTipoProduccion'],
        adquisiciones_compras: ['accItemProveedor', 'accItemProducto', 'accItemCategoria', 'accItemMarca'],
        nomina:                ['accItemEmpleado']
    };
    const ACORDEONES_DIM_TODOS = ['accItemCliente', 'accItemProveedor', 'accItemProducto', 'accItemCategoria', 'accItemMarca', 'accItemTipoProduccion', 'accItemEmpleado'];

    // ¿La dimensión actual es la regla por NOMBRE del ítem de compra? (producto + adquisiciones_compras).
    // En ese caso la regla se guarda por texto (tipo_referencia='item_compra', clave = descripción del ítem).
    function ASIENTOPROG_esItemCompra(tipo) {
        const ta = (document.getElementById('tipoAsientoSelector') || {}).value || '';
        return tipo === 'producto' && ta === 'adquisiciones_compras';
    }

    // Módulo del que salen los movimientos de una dimensión: cliente siempre de ventas, proveedor
    // siempre de compras, y las dimensiones de línea (producto/categoría/marca) según el tipo de
    // asiento. Determina de qué documentos se sacan los años y contra qué se filtra el listado.
    function ASIENTOPROG_moduloDeDimension(tipo, tipoAsiento) {
        if (tipo === 'cliente') return 'ventas';
        if (tipo === 'proveedor') return 'compras';
        const ta = tipoAsiento || (document.getElementById('tipoAsientoSelector') || {}).value || '';
        return (ta === 'ventas_factura' || ta === 'recibos_venta') ? 'ventas' : 'compras';
    }

    // Tipo de Producción (Bien/Servicio) es un selector fijo de 2 valores, no un buscador con
    // autocompletado como el resto de dimensiones — por eso no pasa por
    // ASIENTOPROG_vincularDimAutocomplete(); este handler solo replica lo que allí hace el click
    // de una sugerencia: fijar el hidden id_referencia y disparar el indicador de faltantes.
    window.ASIENTOPROG_seleccionarTipoProduccion = function (select) {
        const hidden = document.getElementById('dim_id_tipo_produccion');
        const label = document.getElementById('dim_search_tipo_produccion');
        if (hidden) hidden.value = select.value;
        if (label) label.value = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : '';
        if (select.value) ASIENTOPROG_mostrarFaltantesEntidad('tipo_produccion');
    };

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
                            const safeSuffix = ASIENTOPROG_esConceptoIva(item) ? `iva_${item.id_referencia}` : `at_${item.id_asiento_tipo}`;
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
            'iva_ventas_factura', 'iva_compras_factura', 'iva_recibos_venta',
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
            'iva_ventas_factura', 'iva_compras_factura', 'iva_recibos_venta',
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

    // Mapa de valor de checkbox -> [label, color] para armar badges a partir de un CSV guardado
    const ASIENTOPROG_TIPO_MAP = {
        activo: ['Activo', 'success'], pasivo: ['Pasivo', 'danger'], patrimonio: ['Patrimonio', 'dark'],
        ingreso: ['Ingresos', 'primary'], costo: ['Costos', 'info'], gasto: ['Gastos', 'warning']
    };

    /**
     * Badges de tipo de cuenta a partir del CSV configurado en la opción/forma (item.tipo_cuenta_contable).
     * Vacío o sin coincidencias = sin restricción, se muestran los 6 tipos como referencia (igual que antes).
     */
    function ASIENTOPROG_badgesPorTipoCuenta(csv) {
        const partes = (csv || '').split(',').map(p => p.trim().toLowerCase()).filter(Boolean);
        if (!partes.length) return ASIENTOPROG_badgesTipoCuenta(ASIENTOPROG_TIPOS_TODOS);
        const tipos = partes.map(p => ASIENTOPROG_TIPO_MAP[p]).filter(Boolean);
        return ASIENTOPROG_badgesTipoCuenta(tipos.length ? tipos : ASIENTOPROG_TIPOS_TODOS);
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

        // El tipo de cuenta esperado (badges + filtro del buscador) se calcula por cada opción
        // individual dentro de ASIENTOPROG_renderReferencias, a partir de item.tipo_cuenta_contable
        // (configurado en el modal de Opciones de Ingresos y Egresos) — no hay un tipo fijo por sección.
        const base = {
            idKey: 'id_opcion', refParam: 'id_opcion', selectorParam: 'naturaleza',
            detalle: 'Configurado en Opciones de Ingresos y Egresos',
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

        // Igual que en Ingresos/Egresos: el tipo esperado se calcula por cada forma individual
        // en ASIENTOPROG_renderReferencias, a partir de item.tipo_cuenta_contable.
        const base = {
            idKey: 'id_forma', refParam: 'id_forma', selectorParam: 'flujo',
            detalle: 'Configurado en Formas de Cobros y Pagos',
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

            // Tipo esperado por ESTA opción/forma específica (configurado en su propio modal admin).
            // Vacío = sin restricción, igual comportamiento que antes.
            const cfgItem = Object.assign({}, cfg, {
                tipoCuentaFiltro: item.tipo_cuenta_contable || '',
                badgesHtml: ASIENTOPROG_badgesPorTipoCuenta(item.tipo_cuenta_contable)
            });

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="ps-4 fw-bold text-dark">${ASIENTOPROG_esc(item.concepto)}</td>
                <td class="small text-muted">${cfg.detalle}</td>
                <td>${cfgItem.badgesHtml}</td>
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
            if (btnDel) btnDel.addEventListener('click', () => ASIENTOPROG_eliminarRefAlVuelo(idRef, inputId, hiddenId, cfgItem));
            tbody.appendChild(tr);

            ASIENTOPROG_vincularAutoRef(idRef, inputId, hiddenId, sugId, cfgItem);
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
                // El backend devuelve las demás filas del mismo dinero (el mismo concepto en el
                // bloque contrario, y las formas hermanas de la misma cuenta bancaria) que hoy no
                // tienen esta cuenta.
                if (res.sugerencia && res.sugerencia.length) {
                    ASIENTOPROG_sugerirReplica(res.sugerencia, idCuenta, inputElement.value, cfg);
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
                if (inputElement.value !== '') {
                    inputElement.classList.remove('is-invalid', 'border-danger');
                }
                inputElement.style.backgroundColor = origBg;
            }, 2000);
        }
    }

    /**
     * Texto del aviso: una frase cuando hay un solo destino, y una lista cuando son varios.
     */
    function ASIENTOPROG_htmlReplica(destinos, cuentaTexto) {
        const cuenta = ASIENTOPROG_esc(cuentaTexto);

        if (destinos.length === 1) {
            const d        = destinos[0];
            const concepto = ASIENTOPROG_esc(d.concepto);
            const bloque   = ASIENTOPROG_esc(d.bloque);
            const tambien  = d.es_misma_forma
                ? `<b>${concepto}</b> también se usa en <b>${bloque}</b>`
                : `<b>${concepto}</b> (<b>${bloque}</b>) es la misma cuenta bancaria`;
            return d.cuenta_actual
                ? `${tambien} y tiene asignada <b>${ASIENTOPROG_esc(d.cuenta_actual)}</b>.<br>¿Reemplazarla por <b>${cuenta}</b>?`
                : `${tambien} y aún no tiene cuenta contable.<br>¿Aplicar ahí también <b>${cuenta}</b>?`;
        }

        const items = destinos.map(d => {
            const actual = d.cuenta_actual
                ? ASIENTOPROG_esc(d.cuenta_actual)
                : '<i class="text-muted">sin cuenta</i>';
            return `<li><b>${ASIENTOPROG_esc(d.bloque)}</b> · ${ASIENTOPROG_esc(d.concepto)} — ${actual}</li>`;
        }).join('');

        return `Este mismo dinero se registra en otras filas que hoy no tienen <b>${cuenta}</b>:
                <ul class="text-start small mt-2 mb-2">${items}</ul>
                ¿Aplicarla en todas?`;
    }

    /**
     * Propone replicar la cuenta recién asignada en las demás filas que representan el mismo
     * dinero. El backend manda la lista: el mismo concepto en el bloque contrario (forma con
     * aplica_en = AMBAS, u opción marcada en Ingresos y Egresos) y las formas hermanas que son la
     * misma cuenta bancaria — mismo banco y mismo número — en los dos bloques. Nada se toca sin
     * que el usuario acepte; cada guardado lleva sin_sugerencia para no volver a sugerir en cadena.
     */
    async function ASIENTOPROG_sugerirReplica(destinos, idCuenta, cuentaTexto, cfg) {
        if (!window.Swal || !Array.isArray(destinos) || destinos.length === 0) return;

        const varios = destinos.length > 1;
        const conf = await Swal.fire({
            title: 'Aplicar la misma cuenta',
            html: ASIENTOPROG_htmlReplica(destinos, cuentaTexto),
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: varios ? `Sí, aplicar en las ${destinos.length}` : 'Sí, aplicar',
            cancelButtonText: 'No, dejar como está'
        });
        if (!conf.isConfirmed) return;

        let aplicados = 0;
        const fallos = [];

        for (const d of destinos) {
            const fd = new FormData();
            fd.append(cfg.refParam, String(d.id_referencia));
            fd.append('id_cuenta', String(idCuenta));
            fd.append(cfg.selectorParam, d.selector_valor);
            fd.append('sin_sugerencia', '1');

            try {
                const resp = await fetch(`${API_PROG}/${cfg.endpointGuardar}`, { method: 'POST', body: fd });
                const res = await resp.json();

                if (!res.ok) {
                    fallos.push(`${d.bloque} · ${d.concepto}: ${res.error || 'error al guardar'}`);
                    continue;
                }

                // Reflejar el cambio en esa fila, sin recargar todo el modal.
                const suffix = `${d.prefijo}_${d.id_referencia}`;
                const inputDestino  = document.getElementById(`cuenta_search_${suffix}`);
                const hiddenDestino = document.getElementById(`cuenta_hidden_${suffix}`);
                if (inputDestino) {
                    inputDestino.value = cuentaTexto;
                    inputDestino.classList.remove('is-invalid', 'border-danger');
                }
                if (hiddenDestino) hiddenDestino.value = String(idCuenta);
                aplicados++;
            } catch (e) {
                console.error(e);
                fallos.push(`${d.bloque} · ${d.concepto}: no se pudo contactar al servidor`);
            }
        }

        if (fallos.length) {
            Swal.fire({
                title: aplicados ? 'Aplicada solo en parte' : 'No se pudo aplicar',
                html: `${aplicados} de ${destinos.length} filas quedaron con la cuenta.
                       <ul class="text-start small mt-2 mb-0">${fallos.map(f => `<li>${ASIENTOPROG_esc(f)}</li>`).join('')}</ul>`,
                icon: aplicados ? 'warning' : 'error'
            });
            return;
        }

        ASIENTOPROG_toast('success', varios
            ? `Cuenta aplicada en ${aplicados} filas más.`
            : `Cuenta aplicada también en ${destinos[0].bloque}.`);
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

    /** ¿Este concepto (de CONCEPTOS_CONFIGURADOS) es un override de IVA por tarifa? */
    function ASIENTOPROG_esConceptoIva(item) {
        return !!item && (
            item.tipo_referencia === 'iva_ventas_factura' ||
            item.tipo_referencia === 'iva_compras_factura' ||
            item.tipo_referencia === 'iva_recibos_venta'
        );
    }

    // Clave única por concepto para IDs de elementos e input hidden: los conceptos normales usan su
    // id_asiento_tipo (siempre > 0); las tarifas de IVA comparten id_asiento_tipo=0 entre todas ellas,
    // así que se distinguen por su tarifa (id_referencia) en su lugar.
    function ASIENTOPROG_dimKey(item) {
        return ASIENTOPROG_esConceptoIva(item) ? `iva_${item.id_referencia}` : item.id_asiento_tipo;
    }

    /**
     * Indicador proactivo: al elegir una entidad/ítem en una regla específica, consulta qué
     * cuentas quedarían SIN asignar (ni en esta regla ni en la General) si esa entidad usa esta
     * configuración — para avisarle al usuario ANTES de que falle un asiento real (Opción 2:
     * "la entidad manda", ver AsientoBuilderService). Incluye el IVA por tarifa, que tiene su
     * propia cascada independiente de los demás conceptos.
     */
    window.ASIENTOPROG_mostrarFaltantesEntidad = async function (tipo) {
        const cont = document.getElementById(`dim_faltantes_${tipo}`);
        if (!cont) return;

        const tipoAsiento = (document.getElementById('tipoAsientoSelector') || {}).value || '';
        const refType = ASIENTOPROG_esItemCompra(tipo) ? 'item_compra' : tipo;
        const valor = (document.getElementById(`dim_id_${tipo}`) || {}).value || '';
        if (!tipoAsiento || !valor) { cont.innerHTML = ''; return; }

        cont.innerHTML = '<div class="small text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Verificando cuentas...</div>';

        try {
            let url = `${API_PROG}/getFaltantesDimensionAjax?tipo_asiento=${encodeURIComponent(tipoAsiento)}&tipo_referencia=${encodeURIComponent(refType)}`;
            url += (refType === 'item_compra')
                ? `&referencia_texto=${encodeURIComponent(valor)}`
                : `&id_referencia=${encodeURIComponent(valor)}`;

            const r = await fetch(url);
            const res = await r.json();
            if (!res.ok || !res.data) { cont.innerHTML = ''; return; }

            const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const faltan = [...(res.data.conceptos || []), ...(res.data.iva || [])];

            // Alcance real del motor (2026-08-01): en Ventas con Factura, Recibos de Venta y
            // Compras, Producto/Categoría/Marca (Ítem, en Compras) ya arman el asiento COMPLETO por
            // línea (Cuenta por Cobrar/Por Pagar, Subtotal/Gasto, Costo, Inventario e IVA) — Propina
            // y Descuento siempre quedan en Cliente/Proveedor/General. En Compras, ICE TAMPOCO se
            // reparte (el subtotal ya viene neto por línea) — a diferencia de Ventas/Recibos, donde
            // ICE sí tiene su propio reparto.
            const esDimensionPorLinea = ['producto', 'categoria', 'marca', 'tipo_produccion'].includes(tipo);
            const repartoCompletoImplementado = ['ventas_factura', 'recibos_venta', 'adquisiciones_compras'].includes(tipoAsiento);
            const esCompras = (tipoAsiento === 'adquisiciones_compras');
            let notaAlcance = '';
            if (esDimensionPorLinea && repartoCompletoImplementado) {
                notaAlcance = '<div class="small text-muted mt-1"><i class="bi bi-info-circle me-1"></i>'
                    + 'Esta regla arma el asiento completo de las líneas de esa dimensión ('
                    + (esCompras ? 'Por Pagar, Subtotal/Gasto e Inventario' : 'Cuenta por Cobrar, Subtotal, ICE, Costo e Inventario')
                    + `). Propina${esCompras ? ', Descuento e ICE' : ' y Descuento'} siempre se toman del Proveedor/Cliente `
                    + 'o de la Configuración General.</div>';
            } else if (esDimensionPorLinea) {
                notaAlcance = '<div class="small text-muted mt-1"><i class="bi bi-info-circle me-1"></i>'
                    + 'Por ahora, esta regla solo se usa para el Subtotal/Gasto de cada línea y el IVA por tarifa. '
                    + 'Los demás conceptos siempre se toman del Cliente/Proveedor o de la Configuración General, '
                    + 'aunque se asignen aquí (próximamente se ampliará, igual que ya funciona en Ventas con Factura).</div>';
            }

            if (faltan.length === 0) {
                cont.innerHTML = '<div class="alert alert-success py-2 px-3 small mb-0"><i class="bi bi-check-circle me-1"></i> '
                    + 'Con esta regla, todos los conceptos quedan cubiertos (por esta regla o por la Configuración General).'
                    + notaAlcance + '</div>';
            } else {
                cont.innerHTML = '<div class="alert alert-warning py-2 px-3 small mb-0"><i class="bi bi-exclamation-triangle me-1"></i> '
                    + 'Si esta entidad usa esta regla, el asiento quedaría INCOMPLETO — falta cuenta (ni aquí ni en la General) para: '
                    + `<strong>${faltan.map(esc).join(', ')}</strong>. `
                    + 'Puede asignarlas en la Configuración General para que apliquen a todos.'
                    + notaAlcance + '</div>';
            }
        } catch (e) {
            console.error(e);
            cont.innerHTML = '';
        }
    };

    /**
     * Carga dinámicamente las reglas correspondientes a una dimensión contable.
     */
    // abrirIdx: índice de la tarjeta que debe quedar desplegada tras recargar (al guardar una
    // cuenta se vuelve a pintar la lista y, sin esto, la ficha en edición se cerraría sola).
    window.ASIENTOPROG_cargarDim = async function (tipo, abrirIdx = null) {
        const selector = document.getElementById('tipoAsientoSelector');
        const tipoAsiento = selector.value;
        if (!tipoAsiento) return;

        const contFaltantes = document.getElementById(`dim_faltantes_${tipo}`);
        if (contFaltantes) contFaltantes.innerHTML = '';

        // Filtro de años (movimientos de la empresa) para las dimensiones con filtro de año.
        if (tipo === 'proveedor' || tipo === 'cliente' || tipo === 'producto' || tipo === 'categoria' || tipo === 'marca') {
            const modulo = ASIENTOPROG_moduloDeDimension(tipo, tipoAsiento);
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

        // Los campos de cuenta ya no viven en el formulario de alta: se editan dentro de la
        // tarjeta de cada entidad (ver ASIENTOPROG_tarjetasDim), que es donde también se consultan.

        const cards = document.getElementById(`dimCards_${tipo}`);
        if (!cards) return;

        cards.innerHTML = '<div class="col-12 text-center py-3 text-muted small"><span class="spinner-border spinner-border-sm me-1"></span> Cargando asociaciones...</div>';

        try {
            const refType = ASIENTOPROG_esItemCompra(tipo) ? 'item_compra' : tipo;
            const resp = await fetch(`${API_PROG}/cargarReglasDimensionAjax?tipo_asiento=${tipoAsiento}&tipo_referencia=${refType}`);
            const res = await resp.json();

            if (res.ok) {
                const html = ASIENTOPROG_tarjetasDim(tipo, res.data);
                cards.innerHTML = html || `<div class="col-12 text-center py-3 text-muted small">No se han registrado asociaciones para ${tipo}s.</div>`;

                // Inicializar autocompletados: el del buscador de entidad y los de las cuentas de
                // cada tarjeta (que además guardan al vuelo).
                ASIENTOPROG_vincularDimAutocomplete(tipo);
                ASIENTOPROG_vincularCuentasTarjetas(tipo);

                // Al recargar tras guardar una cuenta, dejar abierta la ficha que se estaba editando.
                if (abrirIdx !== null && abrirIdx !== undefined && window.bootstrap) {
                    const panel = document.getElementById(`dimAcc_${tipo}_${abrirIdx}`);
                    if (panel) {
                        bootstrap.Collapse.getOrCreateInstance(panel).show();
                        const btn = panel.closest('.card')?.querySelector('.btn[data-bs-toggle="collapse"]');
                        if (btn) btn.classList.remove('collapsed');
                    }
                }
            } else {
                cards.innerHTML = `<div class="col-12 text-center py-3 text-danger small">Error: ${ASIENTOPROG_esc(res.error)}</div>`;
            }
        } catch (e) {
            console.error(e);
            cards.innerHTML = '<div class="col-12 text-center py-3 text-danger small">Error de conexión al cargar datos.</div>';
        }
    };

    /**
     * Una TARJETA por entidad (producto, cliente, categoría…) con sus conceptos repartidos en
     * Debe y Haber. Sustituye a la tabla plana, donde las reglas de una misma entidad se leían
     * como filas sueltas y no se veía qué le faltaba.
     *
     * Cada lado muestra:
     *   - los conceptos con cuenta PROPIA de esa entidad (con su cuenta y el botón de quitar);
     *   - los que no tienen cuenta ni aquí ni en la configuración General → en rojo, porque esos
     *     sí dejan el asiento incompleto.
     * Los que la entidad no configura pero la General sí resuelve no se listan uno a uno (no son
     * un problema: la cascada los cubre); se resumen en una línea al pie de la tarjeta.
     */
    /**
     * Entidad que el usuario acaba de agregar con el buscador de cada dimensión, por tipo:
     * { producto: {id, nombre}, cliente: {...} }. Vive solo en la pantalla — una ficha sin
     * ninguna cuenta asignada no se guarda en la base (no habría nada que guardar), así que se
     * recuerda aquí para poder mostrar su tarjeta vacía y que el usuario la llene.
     */
    const ASIENTOPROG_dimNueva = {};

    function ASIENTOPROG_tarjetasDim(tipo, filas) {
        const conceptos = (window.CONCEPTOS_CONFIGURADOS || []);
        // Clave de agrupación: el id de la entidad (o su nombre en los ítems de compra, que no
        // tienen id). Nunca el nombre a secas: dos entidades distintas pueden llamarse igual.
        const claveEnt = (f) => `${f.id_referencia != null ? f.id_referencia : ''}|${f.id_referencia == null ? (f.dimension_nombre || '') : ''}`;
        const esIvaFila = (f) => parseInt(f.id_asiento_tipo) === 0 && f.codigo_tarifa_iva != null;

        const grupos = new Map();

        // Entidad recién agregada con el buscador: todavía no tiene ninguna cuenta, así que el
        // backend no la devuelve. Se antepone aquí para que su ficha exista y se pueda llenar.
        const nueva = ASIENTOPROG_dimNueva[tipo];
        if (nueva) {
            grupos.set(`nueva:${nueva.id}`, { nombre: nueva.nombre, filas: [], refId: nueva.id, esNueva: true });
        }

        filas.forEach(f => {
            const k = claveEnt(f);
            // Si la entidad recién agregada ya tenía reglas, es la misma ficha: no duplicarla.
            const kNueva = nueva ? `nueva:${nueva.id}` : null;
            const mismaQueNueva = nueva && String(nueva.id) === String(ASIENTOPROG_esItemCompra(tipo) ? f.dimension_nombre : f.id_referencia);
            const clave = mismaQueNueva ? kNueva : k;
            if (!grupos.has(clave)) grupos.set(clave, { nombre: f.dimension_nombre || '(sin nombre)', filas: [] });
            grupos.get(clave).filas.push(f);
            if (mismaQueNueva) grupos.get(clave).nombre = f.dimension_nombre || nueva.nombre;
        });

        // Fila EDITABLE de un concepto dentro de la tarjeta: el input trae la cuenta propia si la
        // tiene; si no, el marcador de posición dice qué pasa hoy con ese concepto (lo cubre la
        // General, o no lo cubre nadie). Se guarda al vuelo al elegir cuenta y se quita al vaciarlo.
        const lineaConcepto = (c, propia, idx) => {
            const key       = ASIENTOPROG_dimKey(c);
            const inputId   = `dimc_${tipo}_${idx}_${key}`;
            const valor     = propia ? `${propia.cuenta_codigo} - ${propia.cuenta_nombre}` : '';
            const general   = c.cuenta_codigo ? `${c.cuenta_codigo}` : '';
            const marcador  = general ? `General: ${general}` : 'sin cuenta';
            const claseSin  = (!propia && !c.id_cuenta) ? ' border-danger' : '';
            return `
            <div class="d-flex align-items-center gap-1 border-bottom py-1">
                <span class="small text-truncate" style="flex:0 0 42%;" title="${ASIENTOPROG_esc(c.concepto)}">${ASIENTOPROG_esc(c.concepto)}</span>
                <div class="position-relative flex-grow-1">
                    <input type="text" class="form-control form-control-sm py-0 bg-white text-dark${claseSin}" style="height:26px; font-size:.78rem;"
                           id="${inputId}" value="${ASIENTOPROG_esc(valor)}" placeholder="${ASIENTOPROG_esc(marcador)}" autocomplete="off"
                           data-tipo="${tipo}" data-idx="${idx}"
                           data-asiento-tipo="${c.id_asiento_tipo}"
                           data-tarifa-iva="${ASIENTOPROG_esConceptoIva(c) ? c.id_referencia : ''}"
                           data-tipo-cuenta="${ASIENTOPROG_esc(c.tipo_cuenta || '')}"
                           data-regla="${propia ? propia.id : ''}">
                    <div class="list-group sugerencias-flotantes" id="${inputId}_sug" style="display:none;"></div>
                </div>
                <button type="button" class="btn btn-link text-danger p-0 border-0 lh-1${propia ? '' : ' invisible'}"
                        onclick="ASIENTOPROG_eliminarDim(${propia ? propia.id : 0}, '${tipo}')" title="Quitar esta cuenta">
                    <i class="bi bi-trash small"></i>
                </button>
            </div>`;
        };

        const columna = (titulo, fondo, color, icono, filasHtml) => `
            <div class="col-6">
                <div class="fw-bold small mb-1 px-2 py-1 rounded" style="background:${fondo}; color:${color};">
                    <i class="bi ${icono} me-1"></i>${titulo}
                </div>
                ${filasHtml || '<div class="small text-muted py-1">—</div>'}
            </div>`;

        let html = '';
        let indice = 0;   // sufijo de los ids de los paneles plegables (únicos por dimensión)
        grupos.forEach(g => {
            const idx = indice;
            // Regla propia de la entidad para un concepto (el IVA se cruza por tarifa, no por id)
            const propiaDe = (c) => parseInt(c.id_asiento_tipo) > 0
                ? g.filas.find(f => parseInt(f.id_asiento_tipo) === parseInt(c.id_asiento_tipo))
                : g.filas.find(f => esIvaFila(f) && String(f.codigo_tarifa_iva) === String(c.id_referencia));

            const faltantes = conceptos.filter(c => !c.id_cuenta && !propiaDe(c));   // ni aquí ni en General
            const heredados = conceptos.filter(c => c.id_cuenta && !propiaDe(c)).length;

            const esDebe = (x) => ((x.debe_haber || 'debe') + '').toLowerCase() === 'debe';
            const filasDe = (lado) => conceptos
                .filter(c => (lado === 'debe') === esDebe(c))
                .map(c => lineaConcepto(c, propiaDe(c), idx))
                .join('');

            // Sin la configuración General cargada no hay con qué comparar: se omite el estado en
            // vez de afirmar "completa" sin saberlo.
            const estado = !conceptos.length
                ? ''
                : (faltantes.length
                    ? `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 small" title="Conceptos sin cuenta ni en esta ficha ni en la configuración General">faltan ${faltantes.length}</span>`
                    : '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small" title="Ningún concepto queda sin cuenta: los que no están aquí los cubre la configuración General"><i class="bi bi-check2 me-1"></i>completa</span>');

            // Una entidad por fila, plegable. TODAS arrancan encogidas: el badge de estado de la
            // cabecera ya dice si a esa entidad le falta alguna cuenta, así que no hace falta
            // abrirlas para detectarlo.
            const idPanel = `dimAcc_${tipo}_${idx}`;

            // Clave de la entidad para guardar/eliminar: id numérico, salvo en los ítems de compra,
            // cuya regla se identifica por el NOMBRE (referencia_texto).
            const refId    = g.filas.length ? (g.filas[0].id_referencia ?? '') : (g.refId ?? '');
            const refTexto = ASIENTOPROG_esItemCompra(tipo) ? g.nombre : '';

            html += `
            <div class="col-12" data-dim-card="${idx}" data-ref-id="${ASIENTOPROG_esc(String(refId ?? ''))}" data-ref-texto="${ASIENTOPROG_esc(refTexto)}">
                <div class="card border">
                    <div class="card-header bg-white p-0 d-flex align-items-center">
                        <button class="btn btn-link flex-grow-1 d-flex justify-content-between align-items-center gap-2 py-2 px-3 text-decoration-none shadow-none collapsed"
                                type="button" data-bs-toggle="collapse" data-bs-target="#${idPanel}"
                                aria-expanded="false" aria-controls="${idPanel}">
                            <span class="fw-bold text-dark text-truncate" title="${ASIENTOPROG_esc(g.nombre)}">${ASIENTOPROG_esc(g.nombre)}</span>
                            <span class="d-flex align-items-center gap-1 text-nowrap">
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 small">${g.filas.length} cuenta(s)</span>
                                ${estado}
                                <i class="bi bi-chevron-down text-muted small ms-1"></i>
                            </span>
                        </button>
                        <button type="button" class="btn btn-link text-danger px-3 border-0 shadow-none${g.filas.length ? '' : ' invisible'}"
                                onclick="ASIENTOPROG_eliminarConfiguracionEntidad('${tipo}', ${idx})"
                                title="Eliminar toda la configuración de esta ficha">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <div id="${idPanel}" class="collapse">
                        <div class="card-body p-2 border-top">
                            <div class="row g-2">
                                ${columna('Debe',  '#E6F1FB', '#0C447C', 'bi-arrow-down-right', filasDe('debe'))}
                                ${columna('Haber', '#FAEEDA', '#633806', 'bi-arrow-up-right',   filasDe('haber'))}
                            </div>
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
                                <span class="small text-muted">
                                    ${heredados ? `<i class="bi bi-info-circle me-1"></i>Otros ${heredados} concepto(s) usan la cuenta de la configuración General.` : ''}
                                </span>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0" style="font-size:.75rem;"
                                        onclick="ASIENTOPROG_copiarDeGeneral('${tipo}', ${idx})">
                                    <i class="bi bi-clipboard-check me-1"></i>Copiar cuentas de General
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
            indice++;
        });

        return html;
    }

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
                    const cont = document.getElementById(`dim_faltantes_${tipo}`);
                    if (cont) cont.innerHTML = '';
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
                            // El autocompletado respeta el mismo año que el listado, para no ofrecer
                            // ítems fuera del período que el usuario está revisando.
                            const selAnioItem = document.getElementById('dim_anio_producto');
                            const anioItem = selAnioItem ? (selAnioItem.value || '') : '';
                            const ri = await fetch(`${API_PROG}/getItemsComprasAjax?q=${encodeURIComponent(q)}`
                                + (anioItem ? `&anio=${encodeURIComponent(anioItem)}` : ''));
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
                                        ASIENTOPROG_mostrarFaltantesEntidad(tipo);
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
                                    ASIENTOPROG_mostrarFaltantesEntidad(tipo);
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

        // Las cuentas ya no se escriben en este formulario (solo elige la entidad): cada concepto
        // se edita dentro de su tarjeta y se guarda al vuelo — ver ASIENTOPROG_vincularCuentasTarjetas.
    }

    /**
     * Agrega asíncronamente una nueva regla de dimensión.
     */
    /**
     * Copia a UNA ficha las cuentas que la configuración General ya tiene resueltas, guardándolas
     * al vuelo como reglas propias de esa entidad. Sirve para partir de la base y ajustar solo lo
     * que cambie. Los conceptos que ya tienen cuenta propia en la ficha no se tocan.
     */
    window.ASIENTOPROG_copiarDeGeneral = async function (tipo, idx) {
        const selTipo = document.getElementById('tipoAsientoSelector');
        const tipoAsiento = selTipo ? selTipo.value : '';
        const ref = ASIENTOPROG_refDeTarjeta(tipo, idx);
        if (!tipoAsiento || !ref) return;

        // Releer la configuración General MÁS RECIENTE (puede haber cambiado en esta sesión sin
        // recargar la página). Así se copian también las cuentas recién asignadas en General.
        let conceptos = window.CONCEPTOS_CONFIGURADOS || [];
        try {
            const res = await (await fetch(`${API_PROG}/cargarConfiguracionAjax?tipo_asiento=${tipoAsiento}`)).json();
            if (res.ok && Array.isArray(res.data)) {
                conceptos = res.data;
                window.CONCEPTOS_CONFIGURADOS = res.data;
            }
        } catch (e) { /* fallback: usar lo que ya está en memoria */ }

        const card = document.querySelector(`#dimCards_${tipo} [data-dim-card="${idx}"]`);
        if (!card) return;

        const esConceptoValido = (c) => parseInt(c.id_asiento_tipo) > 0 || ASIENTOPROG_esConceptoIva(c);
        const aplicables = conceptos.filter(c => esConceptoValido(c) && c.id_cuenta);
        const sinCuenta  = conceptos.filter(c => esConceptoValido(c) && !c.id_cuenta).length;

        let copiadas = 0;
        for (const item of aplicables) {
            const input = document.getElementById(`dimc_${tipo}_${idx}_${ASIENTOPROG_dimKey(item)}`);
            if (!input || input.dataset.regla) continue;   // ya tiene cuenta propia: no se pisa

            const fd = new FormData();
            fd.append('id_asiento_tipo', item.id_asiento_tipo.toString());
            fd.append('id_cuenta', item.id_cuenta);
            fd.append('tipo_asiento', tipoAsiento);
            if (ASIENTOPROG_esConceptoIva(item)) fd.append('codigo_tarifa_iva', item.id_referencia.toString());
            if (ASIENTOPROG_esItemCompra(tipo)) {
                fd.append('tipo_referencia', 'item_compra');
                fd.append('referencia_texto', ref.texto || ref.id);
            } else {
                fd.append('tipo_referencia', tipo);
                fd.append('id_referencia', ref.id);
            }
            try {
                const res = await (await fetch(`${API_PROG}/guardarReglaDimensionAjax`, { method: 'POST', body: fd })).json();
                if (res.ok) copiadas++;
            } catch (e) { console.error(e); }
        }

        let msg;
        if (copiadas === 0) {
            msg = 'No había cuentas de General que copiar (o esta ficha ya las tiene todas).';
        } else {
            msg = `Se copiaron ${copiadas} cuenta(s) de General.`;
            if (sinCuenta > 0) msg += ` (${sinCuenta} concepto(s) siguen sin cuenta en General: configúrelos allí.)`;
        }
        if (window.Swal) {
            Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2600, timerProgressBar: true })
                .fire({ icon: copiadas > 0 ? 'success' : 'info', title: msg });
        }

        if (copiadas > 0) {
            delete ASIENTOPROG_dimNueva[tipo];
            ASIENTOPROG_cargarDim(tipo, idx);
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
        const modulo = ASIENTOPROG_moduloDeDimension(tipo, tipoAsiento);
        let etiqueta = ETIQUETA_ENTIDAD[tipo] || 'Entidades';
        if (tipo === 'producto') etiqueta = (modulo === 'compras') ? 'Productos homologados (compras)' : 'Productos vendidos';

        // El listado respeta el año elegido en el selector de la dimensión (si lo hay y no es "Todos").
        const selAnio = document.getElementById(`dim_anio_${tipo}`);
        const anio = selAnio ? (selAnio.value || '') : '';

        if (titulo) {
            titulo.innerHTML = `<i class="bi bi-card-list me-1 text-primary"></i> ${etiqueta}`
                + (anio ? ` <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-1">${anio}</span>` : '');
        }
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        lista.innerHTML = '<div class="text-muted small py-3 text-center"><span class="spinner-border spinner-border-sm me-1"></span> Cargando...</div>';

        // En compras, la regla por producto se basa en los ÍTEMS de las compras (texto libre), no en
        // el catálogo. El modal los lista con una columna que indica si están homologados (informativa).
        if (tipo === 'producto' && modulo === 'compras') {
            if (titulo) {
                titulo.innerHTML = '<i class="bi bi-card-list me-1 text-primary"></i> Ítems de compras'
                    + (anio ? ` <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-1">${anio}</span>` : '');
            }
            const esc = (s) => (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            let items = [];
            const pintarItems = (arr) => {
                lista.innerHTML = '';
                if (!arr.length) {
                    lista.innerHTML = `<div class="text-muted small py-3 text-center">Sin ítems${anio ? ` en ${anio}` : ''}.</div>`;
                    return;
                }
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
                        ASIENTOPROG_mostrarFaltantesEntidad('producto');
                    });
                    lista.appendChild(row);
                });
            };
            try {
                const r = await fetch(`${API_PROG}/getItemsComprasAjax${anio ? `?anio=${encodeURIComponent(anio)}` : ''}`);
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
            if (!arr.length) {
                lista.innerHTML = `<div class="text-muted small py-3 text-center">Sin resultados${anio ? ` en ${anio}` : ''}.</div>`;
                return;
            }
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
                    ASIENTOPROG_mostrarFaltantesEntidad(tipo);
                });
                lista.appendChild(a);
            });
        };

        try {
            let url = `${API_PROG}/getEntidadesDimensionAjax?tipo=${encodeURIComponent(tipo)}`;
            if (tipo === 'producto' || tipo === 'categoria' || tipo === 'marca') url += `&modulo=${encodeURIComponent(modulo)}`;
            if (anio) url += `&anio=${encodeURIComponent(anio)}`;
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

    /**
     * Paso 1 del alta: solo REGISTRA la entidad elegida y abre su ficha. Las cuentas se asignan
     * después, concepto por concepto, dentro de la propia tarjeta (se guardan al vuelo).
     *
     * Antes este mismo formulario pedía la entidad y las cuentas de todos los conceptos a la vez,
     * y no dejaba guardar si no se llenaba al menos una: para revisar o completar una ficha había
     * que rellenarla entera de nuevo.
     */
    window.ASIENTOPROG_agregarDim = async function (e, tipo) {
        e.preventDefault();

        const selector = document.getElementById('tipoAsientoSelector');
        const tipoAsiento = selector ? selector.value : '';
        if (!tipoAsiento) return;

        const esItem = ASIENTOPROG_esItemCompra(tipo);
        const idRef = document.getElementById(`dim_id_${tipo}`).value;
        const nombre = document.getElementById(`dim_search_${tipo}`).value.trim();
        if (!idRef) {
            const msg = esItem ? 'Debe seleccionar un ítem de compra de la lista.' : 'Debe seleccionar una entidad de la lista desplegable.';
            if (window.Swal) Swal.fire('Atención', msg, 'warning');
            else alert(msg);
            return;
        }

        ASIENTOPROG_dimNueva[tipo] = { id: idRef, nombre: nombre || idRef };

        document.getElementById(`dim_search_${tipo}`).value = '';
        document.getElementById(`dim_id_${tipo}`).value = '';

        await ASIENTOPROG_cargarDim(tipo);

        // Abrir la ficha recién agregada (siempre es la primera) para poder llenarla enseguida.
        const cards = document.getElementById(`dimCards_${tipo}`);
        const primera = cards ? cards.querySelector('[data-dim-card] .collapse') : null;
        if (primera && window.bootstrap) {
            bootstrap.Collapse.getOrCreateInstance(primera).show();
            const btn = cards.querySelector('[data-dim-card] .card-header .btn[data-bs-toggle="collapse"]');
            if (btn) btn.classList.remove('collapsed');
        }
    };

    /**
     * Autocompletado + guardado AL VUELO de las cuentas dentro de las tarjetas de dimensión.
     * Al elegir una cuenta se guarda esa regla sola; al vaciar el campo se elimina. Mismo
     * comportamiento que la configuración General, para no tener dos formas de guardar.
     */
    function ASIENTOPROG_vincularCuentasTarjetas(tipo) {
        const cont = document.getElementById(`dimCards_${tipo}`);
        if (!cont) return;

        cont.querySelectorAll('input[data-asiento-tipo]').forEach(input => {
            if (input.dataset.bound) return;
            input.dataset.bound = 'true';
            const sug = document.getElementById(`${input.id}_sug`);

            input.addEventListener('input', function () {
                const q = input.value.trim();
                if (q === '') {
                    // Campo vaciado: si esa regla existía, se quita (sin preguntar: es un solo concepto).
                    const idRegla = input.dataset.regla;
                    if (idRegla) ASIENTOPROG_quitarCuentaTarjeta(tipo, idRegla);
                    if (sug) sug.style.display = 'none';
                    return;
                }
                if (q.length < 2) { if (sug) sug.style.display = 'none'; return; }

                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(async () => {
                    try {
                        const r = await fetch(`${window.BASE_URL}/modulos/plan-cuentas/searchAjaxCuentas?q=${encodeURIComponent(q)}&tipo=${encodeURIComponent(input.dataset.tipoCuenta || '')}`);
                        const res = await r.json();
                        sug.innerHTML = '';
                        if (res.ok && res.data && res.data.length > 0) {
                            res.data.forEach(c => {
                                const btn = document.createElement('button');
                                btn.type = 'button';
                                btn.className = 'list-group-item list-group-item-action py-1 px-2 border-0 small text-dark bg-white';
                                btn.textContent = `${c.codigo} - ${c.nombre}`;
                                btn.addEventListener('click', () => {
                                    input.value = `${c.codigo} - ${c.nombre}`;
                                    sug.style.display = 'none';
                                    ASIENTOPROG_guardarCuentaTarjeta(tipo, input, c.id);
                                });
                                sug.appendChild(btn);
                            });
                            sug.style.display = 'block';
                            activeDropdown = sug;
                        } else {
                            sug.style.display = 'none';
                        }
                    } catch (err) { console.error(err); }
                }, 300);
            });
        });
    }

    /** Datos de la entidad (id o nombre) a la que pertenece una tarjeta. */
    function ASIENTOPROG_refDeTarjeta(tipo, idx) {
        const card = document.querySelector(`#dimCards_${tipo} [data-dim-card="${idx}"]`);
        if (!card) return null;
        return { id: card.dataset.refId || '', texto: card.dataset.refTexto || '' };
    }

    /** Guarda (o actualiza) la cuenta de UN concepto de una ficha. */
    async function ASIENTOPROG_guardarCuentaTarjeta(tipo, input, idCuenta) {
        const selector = document.getElementById('tipoAsientoSelector');
        const tipoAsiento = selector ? selector.value : '';
        const ref = ASIENTOPROG_refDeTarjeta(tipo, input.dataset.idx);
        if (!tipoAsiento || !ref) return;

        const fd = new FormData();
        fd.append('id_asiento_tipo', input.dataset.asientoTipo || '0');
        fd.append('id_cuenta', idCuenta);
        fd.append('tipo_asiento', tipoAsiento);
        if (input.dataset.tarifaIva) fd.append('codigo_tarifa_iva', input.dataset.tarifaIva);
        if (ASIENTOPROG_esItemCompra(tipo)) {
            fd.append('tipo_referencia', 'item_compra');
            fd.append('referencia_texto', ref.texto || ref.id);
        } else {
            fd.append('tipo_referencia', tipo);
            fd.append('id_referencia', ref.id);
        }

        try {
            const res = await (await fetch(`${API_PROG}/guardarReglaDimensionAjax`, { method: 'POST', body: fd })).json();
            if (!res.ok) {
                if (window.Swal) Swal.fire('No se pudo guardar', res.error || 'Error al guardar la cuenta.', 'error');
                else alert(res.error || 'Error al guardar la cuenta.');
                return;
            }
            ASIENTOPROG_toastOk('Cuenta guardada.');
            delete ASIENTOPROG_dimNueva[tipo];          // ya tiene reglas: sale de la lista del backend
            ASIENTOPROG_cargarDim(tipo, input.dataset.idx);
        } catch (err) { console.error(err); }
    }

    /** Quita la cuenta de UN concepto (al vaciar su campo). */
    async function ASIENTOPROG_quitarCuentaTarjeta(tipo, idRegla) {
        const fd = new FormData();
        fd.append('id', idRegla);
        try {
            const res = await (await fetch(`${API_PROG}/eliminarReglaDimensionAjax`, { method: 'POST', body: fd })).json();
            if (res.ok) {
                ASIENTOPROG_toastOk('Cuenta quitada.');
                ASIENTOPROG_cargarDim(tipo);
            }
        } catch (err) { console.error(err); }
    }

    /** Elimina TODAS las cuentas configuradas para una ficha (botón de la cabecera del acordeón). */
    window.ASIENTOPROG_eliminarConfiguracionEntidad = async function (tipo, idx) {
        const selector = document.getElementById('tipoAsientoSelector');
        const tipoAsiento = selector ? selector.value : '';
        const ref = ASIENTOPROG_refDeTarjeta(tipo, idx);
        if (!tipoAsiento || !ref) return;

        const card = document.querySelector(`#dimCards_${tipo} [data-dim-card="${idx}"]`);
        const nombre = card ? (card.querySelector('.card-header .fw-bold')?.textContent || '') : '';

        if (window.Swal) {
            const conf = await Swal.fire({
                title: '¿Eliminar toda la configuración?',
                html: `Se quitarán <b>todas</b> las cuentas configuradas para <b>${ASIENTOPROG_esc(nombre)}</b>.<br>` +
                      'Esa entidad pasará a contabilizarse con la configuración General.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar todo',
                cancelButtonText: 'Cancelar'
            });
            if (!conf.isConfirmed) return;
        } else if (!confirm('¿Eliminar toda la configuración de esta ficha?')) {
            return;
        }

        const fd = new FormData();
        fd.append('tipo_asiento', tipoAsiento);
        fd.append('tipo_referencia', ASIENTOPROG_esItemCompra(tipo) ? 'item_compra' : tipo);
        if (ASIENTOPROG_esItemCompra(tipo)) fd.append('referencia_texto', ref.texto || ref.id);
        else fd.append('id_referencia', ref.id);

        try {
            const res = await (await fetch(`${API_PROG}/eliminarReglasEntidadAjax`, { method: 'POST', body: fd })).json();
            if (res.ok) {
                ASIENTOPROG_toastOk(res.msg || 'Configuración eliminada.');
                delete ASIENTOPROG_dimNueva[tipo];
                ASIENTOPROG_cargarDim(tipo);
            } else {
                if (window.Swal) Swal.fire('Error', res.error || 'No se pudo eliminar.', 'error');
                else alert(res.error || 'No se pudo eliminar.');
            }
        } catch (err) { console.error(err); }
    };

    /** Aviso breve, no bloqueante (mismo estilo que el resto del módulo). */
    function ASIENTOPROG_toastOk(titulo) {
        if (!window.Swal) return;
        Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1600, timerProgressBar: true })
            .fire({ icon: 'success', title: titulo });
    }

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

    // ── Configurar cuentas sugeridas por el plan modelo (solo los conceptos sin cuenta) ──
    const btnSugeridas = document.getElementById('btnConfigurarSugeridas');
    if (btnSugeridas) {
        btnSugeridas.addEventListener('click', async function () {
            const confirmar = window.Swal
                ? (await Swal.fire({
                    title: 'Configurar cuentas sugeridas',
                    html: 'Se asignarán las cuentas del <b>plan de cuentas modelo</b> a los conceptos que estén <b>sin cuenta</b>: tipos de asiento de ventas, recibos, compras y nómina, IVA por tarifa, cierre del ejercicio, formas de cobro/pago y opciones de ingreso/egreso.'
                        + '<br><br>Las cuentas ya configuradas <b>no se modifican</b>, y el plan de cuentas no se toca.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, configurar',
                    cancelButtonText: 'Cancelar'
                })).isConfirmed
                : confirm('¿Asignar las cuentas del plan modelo a los conceptos sin cuenta?');

            if (!confirmar) return;

            const textoOriginal = btnSugeridas.innerHTML;
            btnSugeridas.disabled = true;
            btnSugeridas.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Configurando...';

            try {
                const resp = await fetch(`${API_PROG}/configurarSugeridasAjax`, { method: 'POST' });
                const res = await resp.json();

                if (res.ok) {
                    let html = res.msg;
                    if (res.omitidas && res.omitidas.length) {
                        html += '<br><br><small class="text-muted">Sin asignar (esa cuenta no existe en el plan de la empresa):<br>'
                            + res.omitidas.map(o => '• ' + o).join('<br>') + '</small>';
                    }
                    if (window.Swal) {
                        await Swal.fire({
                            title: res.configuradas > 0 ? '¡Listo!' : 'Sin cambios',
                            html: html,
                            icon: res.configuradas > 0 ? 'success' : 'info'
                        });
                    }
                    if (res.configuradas > 0) location.reload();
                } else {
                    if (window.Swal) Swal.fire('Error', res.error || 'No se pudo configurar.', 'error');
                }
            } catch (e) {
                console.error(e);
                if (window.Swal) Swal.fire('Error', 'Error de conexión.', 'error');
            } finally {
                btnSugeridas.disabled = false;
                btnSugeridas.innerHTML = textoOriginal;
            }
        });
    }

})();
