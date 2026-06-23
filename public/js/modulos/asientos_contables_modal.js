(function () {
    'use strict';

    let centrosCosto = [];
    let proyectos = [];
    let datosCargados = false;
    let modalInstance = null;

    // Constantes de URLs
    const API_ASIENTOS = `${window.BASE_URL || ''}/modulos/asientos_contables`;
    const API_CUENTAS = `${window.BASE_URL || ''}/modulos/plan-cuentas`;

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
        document.getElementById('asiento_fecha').value = getCurrentLocalDate();

        await cargarDatosAuxiliares();

        try {
            const resp = await fetch(`${API_ASIENTOS}/getDetalleAjax?modulo=${modulo}&id_ref=${idRef}`);
            const res = await resp.json();
            if (res.ok && res.data) {
                cargarDatosAsiento(res.data);
                document.getElementById('asientoModalTitle').textContent = `Asiento de ${modulo.replace('_', ' ').toUpperCase()}`;
            } else {
                // No existe asiento aún para este origen
                document.getElementById('asientoModalTitle').textContent = `Nuevo Asiento - ${modulo.replace('_', ' ').toUpperCase()}`;
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

        if (data.modulo_origen) document.getElementById('asiento_modulo_origen').value = data.modulo_origen;
        if (data.id_referencia_origen) document.getElementById('asiento_id_referencia_origen').value = data.id_referencia_origen;

        if (data.estado !== 'anulado') {
            document.getElementById('btnAnularAsiento').classList.remove('d-none');
        }

        if (data.detalles && data.detalles.length > 0) {
            data.detalles.forEach(d => window.ASIENTO_agregarFila(d));
        } else {
            window.ASIENTO_agregarFila();
        }
    }

    window.ASIENTO_agregarFila = function (datos = null) {
        const tbody = document.getElementById('tbodyAsientoDetalles');
        const tr = document.createElement('tr');

        const idCuenta = datos ? datos.id_cuenta_contable : '';
        const codigoNombre = datos ? `${datos.codigo_cuenta} - ${datos.nombre_cuenta}` : '';
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

        if (dif === '0.00' && totDebe > 0) {
            elDif.textContent = 'CUADRADO';
            elDif.className = 'text-center fw-bold fs-6 text-success';
            document.getElementById('btnGuardarAsiento').disabled = false;
        } else {
            elDif.textContent = `-$${dif}`;
            elDif.className = 'text-center fw-bold fs-6 text-danger';
            document.getElementById('btnGuardarAsiento').disabled = true;
        }
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
            const resp = await fetch(url, { method: 'POST', body: fd });
            const res = await resp.json();
            if (res.ok) {
                if (window.cambiarPaginaAjax) window.cambiarPaginaAjax(window.currentPage || 1);
                if (modalInstance) modalInstance.hide();
                await swalExito(res.msg || 'Asiento guardado correctamente.');
            } else {
                await swalError(res.error || 'Error al guardar el asiento.');
            }
        } catch (e) {
            console.error(e);
            await swalError('Error de red. Verifique su conexión e intente nuevamente.');
        } finally {
            btn.innerHTML = textOrig;
            calcularTotales();
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

})();
