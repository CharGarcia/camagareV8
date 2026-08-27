(function () {
    'use strict';

    let centrosCosto = [];
    let proyectos = [];
    let datosCargados = false;
    let modalInstance = null;
    // Cuadre contra el documento origen del asiento abierto (null = asiento de Diario o de un
    // origen cuyo total no es comparable). Lo arma el backend en getDetalleAjax.
    let cuadreDocumento = null;

    // Constantes de URLs
    const API_ASIENTOS = `${window.BASE_URL || ''}/modulos/asientos_contables`;
    const API_CUENTAS = `${window.BASE_URL || ''}/modulos/plan-cuentas`;

    // modulo_origen con documento mapeado en App\Helpers\DocumentoOrigenAsiento::DOCUMENTOS.
    // Nómina, activos fijos, declaraciones, traspasos y migración no tienen un documento con
    // tercero identificable que mostrar, así que el botón "Ver Documento" ni se ofrece para ellos.
    const MODULOS_CON_DOCUMENTO_ORIGEN = [
        'factura_venta', 'compra', 'ingreso', 'egreso', 'recibo_venta',
        'nota_credito', 'nota_debito', 'retencion_compra', 'retencion_venta',
        'liquidacion_compra', 'importacion', 'consignacion_venta', 'retorno_cv',
        'cambio_producto_cv', 'FACTURACION_CV',
    ];

    function swalError(texto) {
        return Swal.fire({ icon: 'error', title: 'Error', text: texto, confirmButtonText: 'Aceptar' });
    }

    function swalWarning(texto) {
        return Swal.fire({ icon: 'warning', title: 'Atención', text: texto, confirmButtonText: 'Aceptar' });
    }

    function swalExito(texto) {
        return Swal.fire({ icon: 'success', title: 'Éxito', text: texto, timer: 2000, showConfirmButton: false });
    }

    async function cargarDatosAuxiliares() {
        if (datosCargados) return;
        try {
            const resp = await fetch(`${API_ASIENTOS}/getSelectDataAjax`);
            const data = await resp.json();
            if (data.ok) {
                centrosCosto = data.data.centros_costo || [];
                proyectos = data.data.proyectos || [];
                datosCargados = true;
            }
        } catch (e) {
            console.error("Error cargando centros/proyectos:", e);
        }
    }

    function generarSelectOptions(lista, valueKey, textKey, selectedValue) {
        let html = '<option value="">-- Seleccionar --</option>';
        lista.forEach(item => {
            const selected = (item[valueKey] == selectedValue) ? 'selected' : '';
            html += `<option value="${item[valueKey]}" ${selected}>${item[textKey]}</option>`;
        });
        return html;
    }

    function getCurrentLocalDate() {
        const d = new Date();
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    window.ASIENTO_abrirModal = async function (id = 0) {
        if (!modalInstance) {
            const el = document.getElementById('modalAsientoContable');
            if(el) modalInstance = new bootstrap.Modal(el);
        }

        document.getElementById('formAsientoContable').reset();
        document.getElementById('asiento_id').value = id > 0 ? id : '';
        document.getElementById('asiento_modulo_origen').value = 'manual';
        document.getElementById('asiento_id_referencia_origen').value = '';
        document.getElementById('tbodyAsientoDetalles').innerHTML = '';
        document.getElementById('btnAnularAsiento').classList.add('d-none');
        document.getElementById('btnRestablecerAsiento').classList.add('d-none');
        document.getElementById('btnGuardarAsiento').classList.remove('d-none');
        document.getElementById('asientoBarraAcciones').classList.toggle('d-none', !(id > 0));
        document.getElementById('btnVerDocumentoOrigenAsiento').classList.add('d-none');
        cuadreDocumento = null;
        aplicarModoLecturaAsiento(false);
        document.getElementById('asientoModalTitle').textContent = id > 0 ? 'Editar Asiento' : 'Nuevo Asiento';
        document.getElementById('asiento_fecha').value = getCurrentLocalDate();
        document.getElementById('asiento_tipo').value         = 'diario';
        document.getElementById('asiento_tipo_label').value   = 'Diario';
        document.getElementById('asiento_estado').value       = 'contabilizado';
        document.getElementById('asiento_estado_label').value = 'Contabilizado';
        document.getElementById('asiento_numero').value       = '';

        await cargarDatosAuxiliares();

        if (id > 0) {
            try {
                const resp = await fetch(`${API_ASIENTOS}/getDetalleAjax?id=${id}`);
                const res = await resp.json();
                if (res.ok) {
                    cargarDatosAsiento(res.data);
                } else {
                    await swalError(res.error || 'Error al cargar el asiento.');
                    return;
                }
            } catch (e) {
                console.error(e);
                await swalError('Error de conexión al cargar el asiento.');
                return;
            }
        } else {
            // Asiento nuevo: agregar 2 filas por defecto
            window.ASIENTO_agregarFila();
            window.ASIENTO_agregarFila();
        }

        calcularTotales();
        if(modalInstance) modalInstance.show();
    };

    window.ASIENTO_abrirModalDesdeOrigen = async function (modulo, idRef) {
        if (!modalInstance) {
            const el = document.getElementById('modalAsientoContable');
            if(el) modalInstance = new bootstrap.Modal(el);
        }

        document.getElementById('formAsientoContable').reset();
        document.getElementById('asiento_id').value = '';
        document.getElementById('asiento_modulo_origen').value = modulo;
        document.getElementById('asiento_id_referencia_origen').value = idRef;
        document.getElementById('tbodyAsientoDetalles').innerHTML = '';
        document.getElementById('btnAnularAsiento').classList.add('d-none');
        document.getElementById('btnRestablecerAsiento').classList.add('d-none');
        document.getElementById('btnGuardarAsiento').classList.remove('d-none');
        document.getElementById('btnVerDocumentoOrigenAsiento').classList.add('d-none');
        cuadreDocumento = null;
        aplicarModoLecturaAsiento(false);
        document.getElementById('asiento_fecha').value = getCurrentLocalDate();

        await cargarDatosAuxiliares();

        try {
            const resp = await fetch(`${API_ASIENTOS}/getDetalleAjax?modulo=${modulo}&id_ref=${idRef}`);
            const res = await resp.json();
            if (res.ok && res.data) {
                cargarDatosAsiento(res.data);
                document.getElementById('asientoModalTitle').textContent = `Asiento de ${modulo.replace('_', ' ').toUpperCase()}`;
            } else {
                // No existe asiento aún para este origen: formulario en blanco, sin cuentas ni
                // valores sugeridos — el usuario arma las líneas a mano con "Agregar línea".
                document.getElementById('asientoModalTitle').textContent = `Nuevo Asiento - ${modulo.replace('_', ' ').toUpperCase()}`;
                document.getElementById('asiento_tipo').value = modulo;
                document.getElementById('asiento_tipo_label').value = modulo.replace(/_/g, ' ');
                document.getElementById('asiento_estado').value = 'borrador';
                document.getElementById('asiento_estado_label').value = 'Borrador';
                window.ASIENTO_agregarFila();
                window.ASIENTO_agregarFila();
            }
        } catch (e) {
            console.error(e);
        }

        calcularTotales();
        if(modalInstance) modalInstance.show();
    };

    function cargarDatosAsiento(data) {
        const tipoVal   = (data.tipo_comprobante || '').toLowerCase().trim();
        const estadoVal = (data.estado || '').toLowerCase().trim();

        document.getElementById('asiento_id').value            = data.id;
        document.getElementById('asiento_fecha').value         = data.fecha_asiento;
        document.getElementById('asiento_tipo').value          = tipoVal;
        document.getElementById('asiento_tipo_label').value    = tipoVal.replace(/_/g, ' ');
        document.getElementById('asiento_numero').value        = data.numero_comprobante || '';
        document.getElementById('asiento_estado').value        = estadoVal;
        document.getElementById('asiento_estado_label').value  = estadoVal.charAt(0).toUpperCase() + estadoVal.slice(1);
        document.getElementById('asiento_concepto').value      = data.concepto;

        cuadreDocumento = data.cuadre_documento || null;

        if (data.modulo_origen) document.getElementById('asiento_modulo_origen').value = data.modulo_origen;
        if (data.id_referencia_origen) document.getElementById('asiento_id_referencia_origen').value = data.id_referencia_origen;

        const esAnulado = estadoVal === 'anulado';
        const esDiario  = tipoVal === 'diario';
        // Un asiento anulado solo es editable si es de tipo Diario; los demás quedan en solo lectura.
        const soloLectura = esAnulado && !esDiario;

        document.getElementById('btnAnularAsiento').classList.toggle('d-none', esAnulado);
        document.getElementById('btnRestablecerAsiento').classList.toggle('d-none', !(esAnulado && esDiario));
        document.getElementById('btnGuardarAsiento').classList.toggle('d-none', soloLectura);
        document.getElementById('asientoBarraAcciones').classList.toggle('d-none', !(data.id > 0));

        const tieneOrigen = !!(data.id_referencia_origen && MODULOS_CON_DOCUMENTO_ORIGEN.includes(data.modulo_origen));
        document.getElementById('btnVerDocumentoOrigenAsiento').classList.toggle('d-none', !tieneOrigen);

        if (data.detalles && data.detalles.length > 0) {
            data.detalles.forEach(d => window.ASIENTO_agregarFila(d));
        } else {
            window.ASIENTO_agregarFila();
        }

        aplicarModoLecturaAsiento(soloLectura);
    }

    function aplicarModoLecturaAsiento(soloLectura) {
        const fecha = document.getElementById('asiento_fecha');
        const concepto = document.getElementById('asiento_concepto');
        if (fecha) fecha.disabled = soloLectura;
        if (concepto) concepto.disabled = soloLectura;

        document.querySelectorAll('#tbodyAsientoDetalles input, #tbodyAsientoDetalles select')
            .forEach(el => { el.disabled = soloLectura; });
        document.querySelectorAll('#tbodyAsientoDetalles button')
            .forEach(el => { el.style.display = soloLectura ? 'none' : ''; });

        const btnAgregar = document.getElementById('btnAgregarLineaAsiento');
        if (btnAgregar) btnAgregar.style.display = soloLectura ? 'none' : '';

        // El botón Guardar se oculta con d-none en modo lectura, pero además hay que
        // devolverle el estado habilitado: ASIENTO_guardar() lo deshabilita mientras envía
        // y el modal se reutiliza entre aperturas sin recargar la página.
        const btnGuardar = document.getElementById('btnGuardarAsiento');
        if (btnGuardar) btnGuardar.disabled = soloLectura;
    }

    window.ASIENTO_agregarFila = function (datos = null) {
        const tbody = document.getElementById('tbodyAsientoDetalles');
        const tr = document.createElement('tr');
        if (datos && datos.casillero) tr.dataset.casillero = datos.casillero;

        const idCuenta = datos ? datos.id_cuenta_contable : '';
        const codigoNombre = datos && datos.id_cuenta_contable ? `${datos.codigo_cuenta} - ${datos.nombre_cuenta}` : '';
        const idCentro = datos ? datos.id_centro_costo : '';
        const idProyecto = datos ? datos.id_proyecto : '';
        const docRef = datos ? (datos.documento_referencia || '') : '';
        const debe = datos ? parseFloat(datos.debe).toFixed(2) : '0.00';
        const haber = datos ? parseFloat(datos.haber).toFixed(2) : '0.00';

        tr.innerHTML = `
            <td>
                <div class="position-relative">
                    <input type="text" class="form-control form-control-sm cuenta-search" placeholder="Buscar cuenta..." value="${codigoNombre}" autocomplete="off" required>
                    <input type="hidden" class="cuenta-id" value="${idCuenta}">
                    <div class="list-group position-absolute shadow cuenta-results" style="z-index: 1050; max-height: 250px; min-width: 450px; max-width: 600px; width: max-content; overflow-y: auto; display: none;"></div>
                </div>
            </td>
            <td>
                <select class="form-select form-select-sm centro-costo">
                    ${generarSelectOptions(centrosCosto, 'id', 'nombre', idCentro)}
                </select>
            </td>
            <td>
                <select class="form-select form-select-sm proyecto">
                    ${generarSelectOptions(proyectos, 'id', 'nombre', idProyecto)}
                </select>
            </td>
            <td><input type="text" class="form-control form-control-sm doc-ref" value="${docRef}"></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end input-debe" value="${debe}" onfocus="this.select()"></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end input-haber" value="${haber}" onfocus="this.select()"></td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 border-0" onclick="this.closest('tr').remove(); calcularTotales();"><i class="bi bi-x"></i></button>
            </td>
        `;

        tbody.appendChild(tr);

        // Eventos
        const inputDebe = tr.querySelector('.input-debe');
        const inputHaber = tr.querySelector('.input-haber');
        const searchInput = tr.querySelector('.cuenta-search');

        inputDebe.addEventListener('input', function() {
            if (parseFloat(this.value) > 0) inputHaber.value = '0.00';
            calcularTotales();
        });

        inputDebe.addEventListener('blur', function() {
            if(!this.value) this.value = '0.00';
            this.value = parseFloat(this.value).toFixed(2);
        });

        inputHaber.addEventListener('input', function() {
            if (parseFloat(this.value) > 0) inputDebe.value = '0.00';
            calcularTotales();
        });

        inputHaber.addEventListener('blur', function() {
            if(!this.value) this.value = '0.00';
            this.value = parseFloat(this.value).toFixed(2);
        });

        setupAutocomplete(searchInput, tr.querySelector('.cuenta-id'), tr.querySelector('.cuenta-results'));
    };

    function setupAutocomplete(input, hiddenInput, resultsDiv) {
        let timeout = null;

        input.addEventListener('input', function () {
            clearTimeout(timeout);
            const q = this.value.trim();
            if (q.length < 2) {
                resultsDiv.style.display = 'none';
                hiddenInput.value = '';
                return;
            }

            timeout = setTimeout(async () => {
                try {
                    const resp = await fetch(`${API_CUENTAS}/searchAjaxCuentas?q=${encodeURIComponent(q)}&tipo=movimiento`);
                    const res = await resp.json();
                    if (res.ok && res.data.length > 0) {
                        resultsDiv.innerHTML = '';
                        res.data.forEach(item => {
                            const a = document.createElement('a');
                            a.href = '#';
                            a.className = 'list-group-item list-group-item-action py-1 px-2 small';
                            a.innerHTML = `<strong>${item.codigo}</strong> - ${item.nombre}`;
                            a.addEventListener('click', (e) => {
                                e.preventDefault();
                                input.value = `${item.codigo} - ${item.nombre}`;
                                hiddenInput.value = item.id;
                                resultsDiv.style.display = 'none';
                            });
                            resultsDiv.appendChild(a);
                        });
                        resultsDiv.style.display = 'block';
                    } else {
                        resultsDiv.style.display = 'none';
                        hiddenInput.value = '';
                    }
                } catch (e) { }
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if(!input.contains(e.target) && !resultsDiv.contains(e.target)) {
                resultsDiv.style.display = 'none';
            }
        });
    }

    /**
     * Monto del asiento que debe reflejar el importe del documento origen. Si la empresa tiene
     * configurada la cuenta de cartera (por cobrar/por pagar) se mide sobre esas líneas; el
     * Debe total no sirve de referencia en ventas, porque también lleva costo de ventas y
     * descuento. Mismo criterio que AsientoContableRules::evaluarCuadreDocumento() en el
     * servidor, que es quien decide al guardar.
     */
    function montoComparableConDocumento() {
        const cuentas = (cuadreDocumento.cuentas_cartera || []).map(Number);
        const lado = cuadreDocumento.lado === 'haber' ? 'haber' : 'debe';

        let totalDebe = 0;
        let montoCartera = 0;
        let hayLineaCartera = false;

        document.querySelectorAll('#tbodyAsientoDetalles tr').forEach(tr => {
            const idCuenta = parseInt(tr.querySelector('.cuenta-id')?.value || 0, 10);
            totalDebe += parseFloat(tr.querySelector('.input-debe')?.value || 0);

            if (cuentas.length > 0 && cuentas.includes(idCuenta)) {
                hayLineaCartera = true;
                montoCartera += parseFloat(tr.querySelector(lado === 'haber' ? '.input-haber' : '.input-debe')?.value || 0);
            }
        });

        return hayLineaCartera
            ? { monto: montoCartera, base: 'cartera' }
            : { monto: totalDebe, base: cuentas.length > 0 ? 'sin_cartera' : 'total_debe' };
    }

    /** Fila "TOTAL DOCUMENTO" del pie del modal: solo existe en asientos con documento origen. */
    function renderCuadreDocumento() {
        const fila = document.getElementById('asientoFilaCuadreDoc');
        if (!fila) return;

        if (!cuadreDocumento) {
            fila.classList.add('d-none');
            return;
        }

        fila.classList.remove('d-none');

        const total = parseFloat(cuadreDocumento.total_documento || 0);
        const tolerancia = parseFloat(cuadreDocumento.tolerancia || 0.03);
        const doc = cuadreDocumento.etiqueta
                  + (cuadreDocumento.numero_documento ? ' ' + cuadreDocumento.numero_documento : '');

        document.getElementById('asientoCuadreDocEtiqueta').textContent = doc.toUpperCase();
        document.getElementById('asientoCuadreDocTotal').textContent = `$${total.toFixed(2)}`;

        const { monto, base } = montoComparableConDocumento();
        const dif = Math.abs(total - monto);
        const estado = document.getElementById('asientoCuadreDocEstado');

        if (base === 'sin_cartera') {
            estado.textContent = 'Falta la línea de la cuenta por ' + (cuadreDocumento.lado === 'haber' ? 'pagar' : 'cobrar');
            estado.className = 'small fw-bold text-danger';
        } else if (dif <= tolerancia) {
            estado.textContent = base === 'cartera'
                ? 'Coincide con la cartera del asiento'
                : 'Coincide con el total del asiento';
            estado.className = 'small text-success';
        } else {
            estado.textContent = (base === 'cartera' ? 'Cartera del asiento: ' : 'Asiento: ')
                               + monto.toFixed(2) + ' · diferencia: ' + dif.toFixed(2);
            estado.className = 'small fw-bold text-danger';
        }
    }

    function calcularTotales() {
        let totDebe = 0;
        let totHaber = 0;
        let itemCount = 0;

        document.querySelectorAll('.input-debe').forEach(el => {
            totDebe += parseFloat(el.value || 0);
            itemCount++;
        });
        document.querySelectorAll('.input-haber').forEach(el => totHaber += parseFloat(el.value || 0));

        document.getElementById('asientoTotalDebe').textContent = `$${totDebe.toFixed(2)}`;
        document.getElementById('asientoTotalHaber').textContent = `$${totHaber.toFixed(2)}`;

        const countSpan = document.getElementById('m-count-items-asiento');
        if (countSpan) countSpan.textContent = itemCount;

        const dif = Math.abs(totDebe - totHaber).toFixed(2);
        const elDif = document.getElementById('asientoDiferencia');
        const cuadrado = dif === '0.00' && totDebe > 0;

        if (cuadrado) {
            elDif.textContent = 'CUADRADO';
            elDif.className = 'text-center fw-bold fs-6 text-success';
        } else {
            elDif.textContent = `-$${dif}`;
            elDif.className = 'text-center fw-bold fs-6 text-warning';
        }

        // El asiento se puede guardar en cualquier momento, cuadre o no (queda como
        // 'borrador'/temporal hasta que cuadre — ver AsientoContableRules::validarCabecera()).
        // No tocar el estado si el asiento cargado ya está 'anulado': eso se maneja aparte con
        // Anular/Restablecer, no debe cambiar solo porque se edite un monto.
        const estadoInput = document.getElementById('asiento_estado');
        const estadoLabel = document.getElementById('asiento_estado_label');
        if (estadoInput && estadoInput.value !== 'anulado') {
            estadoInput.value = cuadrado ? 'contabilizado' : 'borrador';
            if (estadoLabel) estadoLabel.value = cuadrado ? 'Contabilizado' : 'Borrador (temporal)';
        }

        renderCuadreDocumento();
    }

    window.ASIENTO_guardar = async function () {
        const id = document.getElementById('asiento_id').value;
        const url = id ? `${API_ASIENTOS}/update` : `${API_ASIENTOS}/store`;

        const detalles = [];
        let error = false;

        document.querySelectorAll('#tbodyAsientoDetalles tr').forEach((tr) => {
            const idCuenta = tr.querySelector('.cuenta-id').value;
            if (!idCuenta) {
                error = true;
                tr.querySelector('.cuenta-search').classList.add('is-invalid');
            } else {
                tr.querySelector('.cuenta-search').classList.remove('is-invalid');
            }

            detalles.push({
                id_cuenta_contable: idCuenta,
                id_centro_costo: tr.querySelector('.centro-costo').value,
                id_proyecto: tr.querySelector('.proyecto').value,
                documento_referencia: tr.querySelector('.doc-ref').value,
                debe: tr.querySelector('.input-debe').value,
                haber: tr.querySelector('.input-haber').value
            });
        });

        if (error) {
            await swalWarning('Debe seleccionar una cuenta contable válida en todas las filas.');
            return;
        }

        const btn = document.getElementById('btnGuardarAsiento');
        const textOrig = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        btn.disabled = true;

        const fd = new FormData(document.getElementById('formAsientoContable'));
        fd.append('detalles_json', JSON.stringify(detalles));

        try {
            let resp = await fetch(url, { method: 'POST', body: fd });
            let res = await resp.json();

            // El asiento no refleja el importe del documento origen (factura, compra, …). No se
            // impide guardarlo —puede haber un ajuste legítimo—, pero se avisa y la confirmación
            // queda registrada en la auditoría del sistema.
            if (!res.ok && res.requiere_confirmacion) {
                const confirmar = await Swal.fire({
                    icon: 'warning',
                    title: 'El asiento no cuadra con el documento',
                    text: res.mensaje,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar de todos modos',
                    cancelButtonText: 'Revisar el asiento',
                    confirmButtonColor: '#d33',
                    reverseButtons: true,
                });
                if (!confirmar.isConfirmed) {
                    return;
                }
                fd.append('confirmar_descuadre', '1');
                resp = await fetch(url, { method: 'POST', body: fd });
                res = await resp.json();
            }

            if (res.ok) {
                const estadoGuardado = document.getElementById('asiento_estado').value;
                // Hook genérico para que quien haya abierto el modal (p. ej. la Declaración de
                // IVA/Retenciones) reaccione al guardado sin que este archivo sepa nada de ellos.
                document.dispatchEvent(new CustomEvent('asiento:guardado', {
                    detail: {
                        id: res.id,
                        estado: estadoGuardado,
                        modulo_origen: document.getElementById('asiento_modulo_origen').value,
                        id_referencia_origen: document.getElementById('asiento_id_referencia_origen').value,
                    }
                }));
                if (window.cambiarPaginaAjax) window.cambiarPaginaAjax(window.currentPage || 1);
                if (modalInstance) modalInstance.hide();
                const msg = estadoGuardado === 'borrador'
                    ? 'Guardado como borrador (temporal). Complételo y vuelva a guardar cuando esté cuadrado para registrarlo.'
                    : (res.msg || 'Asiento registrado correctamente.');
                await swalExito(msg);
            } else {
                await swalError(res.error || 'Error al guardar el asiento.');
            }
        } catch (e) {
            console.error(e);
            await swalError('Error de red. Verifique su conexión e intente nuevamente.');
        } finally {
            btn.innerHTML = textOrig;
            btn.disabled = false;
            calcularTotales();
        }
    };

    window.ASIENTO_exportarPdf = function() {
        const id = document.getElementById('asiento_id').value;
        if (!id) return;
        window.open(`${API_ASIENTOS}/exportarPdfAjax?id=${id}`, '_blank');
    };

    window.ASIENTO_exportarExcel = function() {
        const id = document.getElementById('asiento_id').value;
        if (!id) return;
        window.open(`${API_ASIENTOS}/exportarExcelAjax?id=${id}`, '_blank');
    };

    window.ASIENTO_verDocumentoOrigen = function() {
        const modulo = document.getElementById('asiento_modulo_origen').value;
        const idRef = document.getElementById('asiento_id_referencia_origen').value;
        if (!modulo || modulo === 'manual' || !idRef) return;
        if (typeof window.DOCORIGEN_abrirModal === 'function') {
            window.DOCORIGEN_abrirModal(modulo, idRef);
        }
    };

    window.ASIENTO_anular = async function() {
        const id = document.getElementById('asiento_id').value;
        if (!id) return;

        const confirmacion = await Swal.fire({
            title: '¿Anular asiento?',
            text: 'Esta acción no se puede deshacer. El asiento quedará marcado como anulado.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-x-circle me-1"></i> Sí, anular',
            cancelButtonText: 'Cancelar',
        });

        if (!confirmacion.isConfirmed) return;

        const fd = new FormData();
        fd.append('id', id);

        try {
            const resp = await fetch(`${API_ASIENTOS}/anular`, { method: 'POST', body: fd });
            const res = await resp.json();
            if (res.ok) {
                if (window.cambiarPaginaAjax) window.cambiarPaginaAjax(window.currentPage || 1);
                if (modalInstance) modalInstance.hide();
                await swalExito(res.msg || 'Asiento anulado correctamente.');
            } else {
                await swalError(res.error || 'Error al anular el asiento.');
            }
        } catch (e) {
            console.error(e);
            await swalError('Error de red. Verifique su conexión e intente nuevamente.');
        }
    };

    /**
     * Borrado total: en cualquier input editable del modal, una sola pulsación
     * de Retroceso (Backspace) o Delete (Supr) vacía por completo el campo.
     * Listener delegado sobre el modal para cubrir también las filas dinámicas.
     */
    (function initBorradoTotalModal() {
        const modalEl = document.getElementById('modalAsientoContable');
        if (!modalEl) return;

        const TIPOS_EDITABLES = ['text', 'number', 'search', 'date', 'tel'];

        modalEl.addEventListener('keydown', function (e) {
            if (e.key !== 'Backspace' && e.key !== 'Delete') return;

            const el = e.target;
            if (!el || el.tagName !== 'INPUT') return;
            if (el.readOnly || el.disabled) return;

            const tipo = (el.type || 'text').toLowerCase();
            if (!TIPOS_EDITABLES.includes(tipo)) return;
            if (el.value === '') return;

            e.preventDefault();
            el.value = '';
            // Notificar a los handlers existentes (totales, autocomplete, etc.)
            el.dispatchEvent(new Event('input', { bubbles: true }));
        });
    })();

    window.ASIENTO_restablecer = async function() {
        const id = document.getElementById('asiento_id').value;
        if (!id) return;

        const confirmacion = await Swal.fire({
            title: '¿Restablecer asiento?',
            text: 'El asiento volverá al estado Contabilizado.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-arrow-counterclockwise me-1"></i> Sí, restablecer',
            cancelButtonText: 'Cancelar',
        });

        if (!confirmacion.isConfirmed) return;

        const fd = new FormData();
        fd.append('id', id);

        try {
            const resp = await fetch(`${API_ASIENTOS}/restablecer`, { method: 'POST', body: fd });
            const res = await resp.json();
            if (res.ok) {
                if (window.cambiarPaginaAjax) window.cambiarPaginaAjax(window.currentPage || 1);
                if (modalInstance) modalInstance.hide();
                await swalExito(res.msg || 'Asiento restablecido correctamente.');
            } else {
                await swalError(res.error || 'Error al restablecer el asiento.');
            }
        } catch (e) {
            console.error(e);
            await swalError('Error de red. Verifique su conexión e intente nuevamente.');
        }
    };

})();
