/**
 * Lógica compartida para el Modal de Proveedores
 * Requiere que BASE_URL esté definido globalmente.
 */

(function (window, document) {
    'use strict';

    const urlBaseProv = BASE_URL + '/modulos/proveedores';
    let modalInstProv = null;
    let catalogosCargadosProv = false;
    let datosCatalogosProv = null;
    let sriDebounceTimer = null;

    // Declarar funciones globales inmediatamente
    window.PROV_abrirModalCrear = async function () { await abrirModalCrearInternal(); };
    window.PROV_abrirModalEditar = async function (data) { await abrirModalEditarInternal(data); };
    window.abrirModalProveedorCrear = window.PROV_abrirModalCrear;
    window.abrirModalProveedorEditar = window.PROV_abrirModalEditar;

    function getModalProv() {
        const el = document.getElementById('modalProveedor');
        if (!el) return null;
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            return bootstrap.Modal.getOrCreateInstance(el);
        }
        return null;
    }

    // --- Helpers para el campo identificación ---
    function getTipoNormalizadoProv() {
        const sel = document.getElementById('prov_tipo_id');
        if (!sel) return '';
        const codigo = (sel.value || '').trim().toUpperCase();
        const textoOpt = (sel.options[sel.selectedIndex]?.text || '').toUpperCase();
        
        if (textoOpt.includes('CEDULA') || textoOpt.includes('CÉDULA') || codigo === '05' || codigo === '5') return 'CEDULA';
        if (textoOpt.includes('RUC') || codigo === '04' || codigo === '4') return 'RUC';
        if (textoOpt.includes('PASAPORTE')) return 'PASAPORTE';
        return codigo;
    }

    function soloNumerosEnInputProv(e) {
        const tipo = getTipoNormalizadoProv();
        if (tipo === 'RUC' || tipo === 'CEDULA') {
            const permitidos = ['Backspace', 'Delete', 'Tab', 'Enter', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];
            if (e.ctrlKey || e.metaKey) return;
            if (!permitidos.includes(e.key) && !/^\d$/.test(e.key)) {
                e.preventDefault();
            }
        }
    }

    function toggleOperacionBancaria(forceId = null) {
        const selFp = document.getElementById('prov_forma_pago');
        const divOp = document.getElementById('prov_div_op_bancaria');
        const selOp = document.getElementById('prov_tipo_operacion_bancaria');
        if (!selFp || !divOp || !selOp) return;

        const val = parseInt(forceId !== null ? forceId : selFp.value, 10) || 0;
        const fp = datosCatalogosProv?.formas_pago?.find(x => parseInt(x.id, 10) === val);

        if (fp && fp.tipo === 'BANCO') {
            divOp.classList.remove('d-none');
        } else {
            divOp.classList.add('d-none');
            selOp.value = '';
        }
    }

    async function cargarCatalogosInitProv() {
        if (catalogosCargadosProv) {
            poblarCatalogosProv();
            return;
        }
        try {
            const resp = await fetch(`${urlBaseProv}/catalogos`);
            const json = await resp.json();
            if (!json.ok) throw new Error(json.error || 'Error al cargar catálogos');

            datosCatalogosProv = json.data;
            catalogosCargadosProv = true;
            poblarCatalogosProv();
        } catch (e) {
            console.error('Error cargando catálogos de proveedores:', e);
        }
    }

    function poblarCatalogosProv() {
        if (!datosCatalogosProv) return;

        const selTipos = document.getElementById('prov_tipo_id');
        if (selTipos) {
            selTipos.innerHTML = ''; // Sin placeholder
            if (datosCatalogosProv.tipos_id) {
                datosCatalogosProv.tipos_id.forEach(item => {
                    // Omitir Consumidor Final en proveedores
                    if (item.nombre.toUpperCase().includes('CONSUMIDOR FINAL')) return;
                    
                    const opt = document.createElement('option');
                    opt.value = item.codigo;
                    opt.textContent = item.nombre;
                    selTipos.appendChild(opt);
                });
            }
        }
        const selEmp = document.getElementById('prov_tipo_empresa');
        if (selEmp) {
            selEmp.innerHTML = '<option value="">-- Ninguno --</option>';
            datosCatalogosProv.tipos_empresa.forEach(x => {
                const opt = document.createElement('option');
                opt.value = x.id;
                opt.textContent = x.nombre;
                selEmp.appendChild(opt);
            });
        }

        const selBanco = document.getElementById('prov_banco');
        if (selBanco) {
            selBanco.innerHTML = '<option value="">-- Ninguno --</option>';
            datosCatalogosProv.bancos.forEach(x => {
                const opt = document.createElement('option');
                opt.value = x.id;
                opt.textContent = x.nombre;
                selBanco.appendChild(opt);
            });
        }

        // Retenciones removidas de poblarCatalogosProv ya que ahora usan búsqueda Ajax

        const selProv = document.getElementById('prov_provincia');
        if (selProv) {
            selProv.innerHTML = '<option value="">-- Seleccione --</option>';
            datosCatalogosProv.provincias.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.codigo;
                opt.textContent = p.nombre;
                selProv.appendChild(opt);
            });
        }

        const selFp = document.getElementById('prov_forma_pago');
        if (selFp && datosCatalogosProv.formas_pago) {
            selFp.innerHTML = '<option value="">— Seleccione forma de pago —</option>';
            datosCatalogosProv.formas_pago.forEach(fp => {
                const opt = document.createElement('option');
                opt.value = fp.id;
                
                let label = fp.nombre;
                if (fp.tipo === 'BANCO' && fp.banco_nombre) {
                    label += ` — ${fp.banco_nombre}`;
                    if (fp.numero_cuenta) {
                        const tCta = fp.tipo_cuenta ? fp.tipo_cuenta.toLowerCase() : 'cta';
                        label += ` (${tCta}: ${fp.numero_cuenta})`;
                    }
                }
                
                opt.textContent = label;
                selFp.appendChild(opt);
            });
        }

        const selSustento = document.getElementById('prov_id_sustento_tributario');
        if (selSustento && datosCatalogosProv.sustento_tributario) {
            selSustento.innerHTML = '<option value="">-- Seleccione Sustento --</option>';
            datosCatalogosProv.sustento_tributario.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.nombre;
                selSustento.appendChild(opt);
            });
        }

        const selConcepto = document.getElementById('prov_id_egreso_concepto');
        if (selConcepto && datosCatalogosProv.conceptos_egreso) {
            selConcepto.innerHTML = '<option value="">-- Seleccione --</option>';
            datosCatalogosProv.conceptos_egreso.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.nombre;
                selConcepto.appendChild(opt);
            });
        }
    }

    window.buscarRetencionSRI = async function (el, tipo) {
        const query = el.value.trim();
        const resultsDiv = document.getElementById(tipo === 'RENTA' ? 'results_retencion_renta' : 'results_retencion_iva');
        const hiddenId = document.getElementById(tipo === 'RENTA' ? 'prov_id_retencion_renta' : 'prov_id_retencion_iva');

        if (query.length < 1) {
            resultsDiv.classList.add('d-none');
            hiddenId.value = '';
            return;
        }

        try {
            console.log('Buscando retenciones:', query, 'tipo:', tipo);
            const resp = await fetch(`${urlBaseProv}/getRetencionesSriAjax?q=${encodeURIComponent(query)}&tipo=${tipo}`);
            const json = await resp.json();

            if (json.ok && json.data.length > 0) {
                resultsDiv.innerHTML = '';
                json.data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'list-group-item list-group-item-action small py-2 cursor-pointer ac-item border-0';
                    div.innerHTML = `<strong>${item.codigo_ret}</strong> - ${item.concepto_ret} <span class="badge bg-primary bg-opacity-10 text-primary float-end">${item.porcentaje_ret}%</span>`;
                    div.onclick = () => seleccionarRetencionSRI(item, tipo);
                    resultsDiv.appendChild(div);
                });
                resultsDiv.classList.remove('d-none');
            } else {
                resultsDiv.innerHTML = '<div class="p-2 small text-muted text-center">Sin resultados</div>';
                resultsDiv.classList.remove('d-none');
            }
        } catch (e) {
            console.error('Error en búsqueda de retenciones:', e);
            resultsDiv.classList.add('d-none');
        }
    };

    function seleccionarRetencionSRI(item, tipo) {
        const inputBusqueda = document.getElementById(tipo === 'RENTA' ? 'busqueda_retencion_renta' : 'busqueda_retencion_iva');
        const hiddenId = document.getElementById(tipo === 'RENTA' ? 'prov_id_retencion_renta' : 'prov_id_retencion_iva');
        const resultsDiv = document.getElementById(tipo === 'RENTA' ? 'results_retencion_renta' : 'results_retencion_iva');

        inputBusqueda.value = `${item.codigo_ret} - ${item.concepto_ret} (${item.porcentaje_ret}%)`;
        hiddenId.value = item.id;
        resultsDiv.classList.add('d-none');
    }



    async function abrirModalCrearInternal() {
        const form = document.getElementById('prov_formProveedor');
        if (!form) return;
        form.reset();
        _provResetMapa();
        const elId = document.getElementById('prov_id');
        if (elId) elId.value = '';
        
        const divOp = document.getElementById('prov_div_op_bancaria');
        if (divOp) divOp.classList.add('d-none');
        const selOp = document.getElementById('prov_tipo_operacion_bancaria');
        if (selOp) selOp.value = '';
        const brenta = document.getElementById('busqueda_retencion_renta');
        if (brenta) brenta.value = '';
        const biva = document.getElementById('busqueda_retencion_iva');
        if (biva) biva.value = '';
        const elTitle = document.getElementById('prov_tituloModal');
        if (elTitle) elTitle.textContent = 'Nuevo Proveedor';

        const btnDlt = document.getElementById('prov_btnEliminar');
        const btnSave = document.getElementById('prov_btnGuardar');
        if (btnDlt) {
            btnDlt.classList.add('d-none');
            btnDlt.disabled = false;
            btnDlt.innerHTML = '<i class="bi bi-trash3 me-1"></i> Eliminar';
        }
        if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
        }

        // Mostrar modal inmediatamente
        const modal = getModalProv();
        if (modal) modal.show();

        if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            const tabBtn = document.getElementById('prov-tab-general-btn');
            if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        }

        if (window.resetearInfoExtraProv) window.resetearInfoExtraProv();
        if (typeof window.limpiarBadgeSri === 'function') window.limpiarBadgeSri();

        // Cargar catálogos
        await cargarCatalogosInitProv();
        
        // Forzar selección de RUC por defecto (después de cargar si es necesario)
        setTimeout(() => {
            const selTipos = document.getElementById('prov_tipo_id');
            if (selTipos) {
                for (let i = 0; i < selTipos.options.length; i++) {
                    if (selTipos.options[i].text.toUpperCase().includes('RUC') || selTipos.options[i].value === '04') {
                        selTipos.selectedIndex = i;
                        break;
                    }
                }
            }
            // Seleccionar sustento 01 por defecto
            const selSustento = document.getElementById('prov_id_sustento_tributario');
            if (selSustento) {
                for (let i = 0; i < selSustento.options.length; i++) {
                    if (selSustento.options[i].textContent.trim().startsWith('01')) {
                        selSustento.selectedIndex = i;
                        break;
                    }
                }
            }
            actualizarRestriccionesIdProv();
        }, 300);
        
        // Si existe la función de favoritos, aplicarla primero
        if (typeof window.aplicarFavoritosModal === 'function') window.aplicarFavoritosModal('#modalProveedor');

        console.log('Abriendo modal crear proveedor');
        if (modal) modal.show();

        setTimeout(() => {
            const e = document.getElementById('prov_identificacion');
            if (e) e.focus();
        }, 150);
    };

    async function abrirModalEditarInternal(rowOrData) {
        console.log('Iniciando abrirModalProveedorEditar');
        const data = (rowOrData instanceof HTMLElement) ? JSON.parse(rowOrData.dataset.row) : rowOrData;
        const form = document.getElementById('prov_formProveedor');
        if (!form) return;
        form.reset();
        _provResetMapa();

        const modal = getModalProv();
        if (modal) modal.show();

        await cargarCatalogosInitProv();

        const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };

        setVal('prov_id', data.id);
        setVal('prov_tipo_id', data.tipo_id_proveedor);
        setVal('prov_identificacion', data.identificacion);
        setVal('prov_razon', data.razon_social);
        setVal('prov_nombre_comercial', data.nombre_comercial);
        setVal('prov_email', data.email);
        setVal('prov_telefono', data.telefono);
        setVal('prov_direccion', data.direccion);
        setVal('prov_tipo_empresa', (data.tipo_empresa || '').toString().trim());
        setVal('prov_provincia', data.provincia);

        if (data.provincia) {
            await window.cargarCiudadesProv(data.provincia);
            setVal('prov_ciudad', data.ciudad);
        } else {
            const selC = document.getElementById('prov_ciudad');
            if (selC) selC.innerHTML = '<option value="">-- Seleccione Provincia --</option>';
        }

        setVal('prov_plazo', data.plazo);
        const esRelacionado = data.relacionado === true || data.relacionado === 'true' || data.relacionado === 1 || data.relacionado === '1' || data.relacionado === 't';
        setVal('prov_relacionado', esRelacionado ? '1' : '0');

        setVal('prov_banco', data.id_banco);
        setVal('prov_tipo_cta', data.tipo_cta);
        setVal('prov_numero_cta', data.numero_cta);



        setVal('prov_id_retencion_renta', data.id_retencion_renta);
        setVal('busqueda_retencion_renta', data.nombre_retencion_renta);
        setVal('prov_id_retencion_iva', data.id_retencion_iva);
        setVal('busqueda_retencion_iva', data.nombre_retencion_iva);
        setVal('prov_id_sustento_tributario', data.id_sustento_tributario);

        setVal('prov_forma_pago', data.id_forma_pago_predeterminada);
        setVal('prov_tipo_operacion_bancaria', data.tipo_operacion_bancaria_predeterminada);
        toggleOperacionBancaria(data.id_forma_pago_predeterminada);
        setVal('prov_monto_maximo', data.monto_maximo_auto_pago);
        setVal('prov_id_egreso_concepto', data.id_egreso_concepto_predeterminado);
        const statusVal = (data.status === false || data.status === 'false' || data.status === 0) ? '0' : '1';
        setVal('prov_status', statusVal);

        // Coordenadas
        if (data.latitud && data.longitud) {
            const lat = parseFloat(data.latitud);
            const lng = parseFloat(data.longitud);
            setVal('prov_latitud',  lat);
            setVal('prov_longitud', lng);
            const setT = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
            setT('prov_geo_lat_txt', lat);
            setT('prov_geo_lng_txt', lng);
            document.getElementById('prov_geo_coords_display')?.classList.remove('d-none');
            document.getElementById('prov_geo_no_coords')?.classList.add('d-none');
            document.getElementById('prov_btnLimpiarCoordenadas')?.classList.remove('d-none');
            if (data.geocodificado_en) {
                const ft = document.getElementById('prov_geo_fecha_txt');
                if (ft) ft.textContent = '(geocodificado: ' + data.geocodificado_en + ')';
            }
            _provPendingCoords = { lat, lng };
        }

        const elTitle = document.getElementById('prov_tituloModal');
        if (elTitle) elTitle.textContent = 'Editar Proveedor';

        if (typeof window.limpiarBadgeSri === 'function') window.limpiarBadgeSri();

        const btnDlt = document.getElementById('prov_btnEliminar');
        const btnSave = document.getElementById('prov_btnGuardar');
        if (btnDlt) {
            btnDlt.classList.remove('d-none');
            btnDlt.disabled = false;
            btnDlt.innerHTML = '<i class="bi bi-trash3 me-1"></i> Eliminar';
        }
        if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            const tabBtn = document.getElementById('prov-tab-general-btn');
            if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        }


        window.fetchInformacionExtraProv(data.id);

        setTimeout(() => {
            actualizarRestriccionesIdProv();
            toggleOperacionBancaria(data.id_forma_pago_predeterminada);
        }, 200);
    };

    window.limpiarBadgeSri = function () {
        const b = document.getElementById('prov_sriBadge');
        const sw = document.getElementById('prov_sriSpinnerWrap');
        if (b) { b.className = 'badge d-none'; b.textContent = ''; }
        if (sw) sw.classList.add('d-none');
    };

    window.mostrarBadgeSri = function (texto, cls) {
        const b = document.getElementById('prov_sriBadge');
        if (b) { b.textContent = texto; b.className = 'badge ' + cls; b.classList.remove('d-none'); }
    };

    function actualizarRestriccionesIdProv() {
        const sel = document.getElementById('prov_tipo_id');
        const inp = document.getElementById('prov_identificacion');
        if (!sel || !inp) return;

        const tipo = getTipoNormalizadoProv();
        window.limpiarBadgeSri();

        if (tipo === 'CEDULA') {
            inp.setAttribute('maxlength', '10');
            inp.setAttribute('inputmode', 'numeric');
            inp.placeholder = '10 dígitos';
        } else if (tipo === 'RUC') {
            inp.setAttribute('maxlength', '13');
            inp.setAttribute('inputmode', 'numeric');
            inp.placeholder = '13 dígitos';
        } else {
            inp.setAttribute('maxlength', '30');
            inp.setAttribute('inputmode', 'text');
            inp.placeholder = 'Identificación';
        }
    }

    window.cargarCiudadesProv = async function (codProvincia, valorSel = null) {
        const selCiudad = document.getElementById('prov_ciudad');
        if (!selCiudad) return;
        selCiudad.innerHTML = '<option value="">-- Cargando --</option>';
        if (!codProvincia) { selCiudad.innerHTML = '<option value="">-- Seleccione Provincia --</option>'; return; }
        try {
            const resp = await fetch(`${urlBaseProv}/ciudades?cod_prov=${encodeURIComponent(codProvincia)}`);
            const data = await resp.json();
            selCiudad.innerHTML = '<option value="">-- Seleccione --</option>';
            if (data.ok) {
                data.data.forEach(x => {
                    const opt = document.createElement('option');
                    opt.value = x.codigo || x.nombre;
                    opt.textContent = x.nombre;
                    selCiudad.appendChild(opt);
                });
                if (valorSel) selCiudad.value = valorSel;
            }
        } catch (e) { selCiudad.innerHTML = '<option value="">-- Error --</option>'; }
    };

    // Inicialización de eventos al cargar el script (si el DOM ya existe)
    function initProvModalEvents() {
        const form = document.getElementById('prov_formProveedor');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btnSave = document.getElementById('prov_btnGuardar');
                const alertEl = document.getElementById('prov_modalAlert');
                const idEl = document.getElementById('prov_id');
                const id = idEl ? idEl.value : '';
                const url = id ? `${urlBaseProv}/update` : `${urlBaseProv}/store`;

                btnSave.disabled = true;
                btnSave.innerHTML = '<span class="spinner-grow spinner-grow-sm me-1"></span>Guardando...';
                if (alertEl) alertEl.classList.add('d-none');

                try {
                    // Habilitar temporalmente prov_tipo_id para que el FormData lo capture si está deshabilitado
                    const selIdInput = document.getElementById('prov_tipo_id');
                    const wasDisabled = selIdInput ? selIdInput.disabled : false;
                    if (selIdInput && wasDisabled) selIdInput.disabled = false;

                    const fd = new FormData(form);

                    if (selIdInput && wasDisabled) selIdInput.disabled = true;

                    const resp = await fetch(url, { method: 'POST', body: fd });
                    const json = await resp.json();

                    if (json.ok) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'success', title: 'Éxito', text: json.msg || 'Guardado correctamente', timer: 1500, showConfirmButton: false });
                        }
                        setTimeout(() => {
                            btnSave.disabled = false;
                            btnSave.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                            getModalProv().hide();
                            
                            if (typeof window.fetchSearch === 'function') {
                                window.fetchSearch(window.currentPage || 1);
                            } else if (typeof window.LC_fetchSearch === 'function') {
                                window.LC_fetchSearch(1);
                            }

                            document.dispatchEvent(new CustomEvent('proveedorGuardado', { 
                                detail: {
                                    ...json,
                                    nombre: json.data ? (json.data.razon_social || json.data.nombre) : ''
                                } 
                            }));
                        }, 800);
                    } else {
                        btnSave.disabled = false;
                        btnSave.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Atención', text: json.msg || json.error || 'Error al guardar' });
                        }
                    }
                } catch (err) {
                    btnSave.disabled = false;
                    btnSave.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo comunicar con el servidor' });
                    }
                }
            });
        }
        
        const inpId = document.getElementById('prov_identificacion');
        const selT = document.getElementById('prov_tipo_id');
        if (inpId && selT) {
            selT.addEventListener('change', () => {
                actualizarRestriccionesIdProv();
                inpId.value = '';
            });

            inpId.addEventListener('keydown', soloNumerosEnInputProv);

            inpId.addEventListener('input', function() {
                const valor = this.value.trim();
                const tipo = getTipoNormalizadoProv();
                window.limpiarBadgeSri();
                clearTimeout(sriDebounceTimer);

                const longEsperada = (tipo === 'RUC') ? 13 : ((tipo === 'CEDULA') ? 10 : 0);

                if (valor.length === longEsperada && longEsperada > 0) {
                    sriDebounceTimer = setTimeout(() => {
                        ejecutarConsultaSriProv(valor);
                    }, 500);
                }
            });
        }
        
        const selP = document.getElementById('prov_provincia');
        if (selP) selP.addEventListener('change', () => window.cargarCiudadesProv(selP.value));

        const selFp = document.getElementById('prov_forma_pago');
        if (selFp) {
            selFp.addEventListener('change', toggleOperacionBancaria);
        }

        // Tab Ubicación: inicializar/actualizar mapa cuando el contenedor ya es visible
        const tabUbicBtn = document.getElementById('prov-tab-ubicacion-btn');
        if (tabUbicBtn) {
            tabUbicBtn.addEventListener('shown.bs.tab', function() {
                if (_provPendingCoords) {
                    const { lat, lng } = _provPendingCoords;
                    _provPendingCoords = null;
                    _provMapInicializarVisible(lat, lng);
                } else if (_provMapa) {
                    setTimeout(() => _provMapa.invalidateSize(), 150);
                } else {
                    _provMapCrearBase();
                }
            });
        }
    }

    async function ejecutarConsultaSriProv(ruc) {
        const sw = document.getElementById('prov_sriSpinnerWrap');
        if (sw) sw.classList.remove('d-none');
        window.mostrarBadgeSri('Consultando...', 'bg-secondary');
        const fd = new FormData(); fd.append('identificacion', ruc);
        try {
            const resp = await fetch(`${urlBaseProv}/consultarSri`, { method: 'POST', body: fd });
            const data = await resp.json();
            if (sw) sw.classList.add('d-none');
            if (data.ok) {
                window.mostrarBadgeSri('✓ SRI', 'bg-success');
                if (data.data) {
                    const d = data.data;
                    document.getElementById('prov_razon').value = d.nombre || '';
                    if (d.nombre_comercial) document.getElementById('prov_nombre_comercial').value = d.nombre_comercial;
                    if (d.direccion) document.getElementById('prov_direccion').value = d.direccion;
                    if (d.cod_prov) {
                        document.getElementById('prov_provincia').value = d.cod_prov;
                        await window.cargarCiudadesProv(d.cod_prov, d.cod_ciudad || '');
                    }
                }
            } else { window.mostrarBadgeSri(data.error || 'No encontrado', 'bg-warning text-dark'); }
        } catch (e) { if (sw) sw.classList.add('d-none'); window.mostrarBadgeSri('Error', 'bg-danger'); }
    }

    window.fetchInformacionExtraProv = async function(id) {
        try {
            const resp = await fetch(`${urlBaseProv}/getDetalleAjax?id=${id}`);
            const json = await resp.json();
            if (json.ok) {
                const d = json.data;
                const elCom = document.getElementById('info_compras');
                if (elCom) elCom.textContent = d.compras_realizadas || '0';
                if (d.stats) {
                    if (document.getElementById('stat_documentos')) document.getElementById('stat_documentos').value = d.stats.documentos_recibidos || '0';
                    if (document.getElementById('stat_total')) document.getElementById('stat_total').value = parseFloat(d.stats.total_compras || 0).toFixed(2);
                    if (document.getElementById('stat_por_pagar')) document.getElementById('stat_por_pagar').value = parseFloat(d.stats.por_pagar || 0).toFixed(2);
                }

                // Inhabilitar identificación si tiene transacciones
                const inUso = (parseInt(d.compras_realizadas, 10) || 0) > 0 || (parseInt(d.stats?.documentos_recibidos, 10) || 0) > 0;
                const selId = document.getElementById('prov_tipo_id');
                const inpId = document.getElementById('prov_identificacion');
                if (selId) {
                    selId.disabled = inUso;
                    selId.title = inUso ? 'No se puede modificar porque tiene compras generadas' : '';
                }
                if (inpId) {
                    inpId.readOnly = inUso;
                    inpId.title = inUso ? 'No se puede modificar porque tiene compras generadas' : '';
                }
            }
        } catch (e) {}
    };

    window.resetearInfoExtraProv = function() {
        const ids = ['info_compras', 'stat_documentos', 'stat_total', 'stat_por_pagar'];
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (el) el[el.tagName === 'INPUT' ? 'value' : 'textContent'] = (id.includes('stat') && id !== 'stat_documentos') ? '0.00' : '0';
        });

        const selId = document.getElementById('prov_tipo_id');
        const inpId = document.getElementById('prov_identificacion');
        if (selId) { selId.disabled = false; selId.title = ''; }
        if (inpId) { inpId.readOnly = false; inpId.title = ''; }
    };
    
    window.eliminarProveedor = async function () {
        const id = document.getElementById('prov_id').value;
        if (!id) return;

        const result = await Swal.fire({
            title: '¿Está seguro?',
            text: "Se realizará una eliminación lógica del proveedor.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });

        if (!result.isConfirmed) return;

        const btnDlt = document.getElementById('prov_btnEliminar');
        const alertEl = document.getElementById('prov_modalAlert');

        btnDlt.disabled = true;
        btnDlt.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Eliminando...';

        try {
            const fd = new FormData();
            fd.append('id_eliminar', id);
            const resp = await fetch(`${urlBaseProv}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();

            if (json.ok) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Eliminado', text: json.msg, timer: 1500, showConfirmButton: false });
                }
                setTimeout(() => {
                    getModalProv().hide();
                    if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
                }, 800);
            } else {
                btnDlt.disabled = false;
                btnDlt.innerHTML = '<i class="bi bi-trash3 me-1"></i> Eliminar';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'No se pudo eliminar' });
                }
            }
        } catch (err) {
            btnDlt.disabled = false;
            btnDlt.innerHTML = '<i class="bi bi-trash3 me-1"></i> Eliminar';
        }
    };

    // ─── Mapa Leaflet (Proveedor) ─────────────────────────────────────────────
    let _provMapa      = null;
    let _provMarcador  = null;
    let _provPendingCoords = null;

    const _PROV_DEFAULT_LAT  = -1.8312;
    const _PROV_DEFAULT_LNG  = -78.1834;
    const _PROV_DEFAULT_ZOOM = 6;

    function _provMapCrearBase() {
        if (_provMapa) return;
        const ph = document.getElementById('prov_mapa_placeholder');
        if (ph) ph.style.display = 'none';

        _provMapa = L.map('prov_mapa_proveedor', { preferCanvas: true })
            .setView([_PROV_DEFAULT_LAT, _PROV_DEFAULT_LNG], _PROV_DEFAULT_ZOOM);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19
        }).addTo(_provMapa);
        setTimeout(() => _provMapa.invalidateSize(), 150);
    }

    function _provMapInicializarVisible(lat, lng) {
        const ph = document.getElementById('prov_mapa_placeholder');
        if (ph) ph.style.display = 'none';

        if (!_provMapa) {
            _provMapa = L.map('prov_mapa_proveedor', { preferCanvas: true }).setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19
            }).addTo(_provMapa);
        } else {
            _provMapa.setView([lat, lng], 15);
        }

        if (_provMarcador) {
            _provMarcador.setLatLng([lat, lng]);
        } else {
            _provMarcador = L.marker([lat, lng], { draggable: true })
                .addTo(_provMapa)
                .bindPopup('Arrastra para ajustar la posición exacta.')
                .openPopup();
            _provMarcador.on('dragend', (e) => {
                const p = e.target.getLatLng();
                _provActualizarCoordenadas(p.lat, p.lng);
            });
        }
        setTimeout(() => _provMapa.invalidateSize(), 150);
        _provActualizarCoordenadas(lat, lng);
    }

    function _provTabUbicacionActiva() {
        const pane = document.getElementById('prov-pane-ubicacion');
        return pane && pane.classList.contains('active') && pane.classList.contains('show');
    }

    function _provProcesarCoordenadas(lat, lng) {
        // Guardar en los inputs ocultos DE INMEDIATO para que FormData los capture
        // aunque el usuario guarde sin abrir la pestaña Ubicación
        const latR = parseFloat(lat.toFixed(8));
        const lngR = parseFloat(lng.toFixed(8));
        const setV = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
        const setT = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        setV('prov_latitud',  latR);
        setV('prov_longitud', lngR);
        setT('prov_geo_lat_txt', latR);
        setT('prov_geo_lng_txt', lngR);
        document.getElementById('prov_geo_coords_display')?.classList.remove('d-none');
        document.getElementById('prov_geo_no_coords')?.classList.add('d-none');
        document.getElementById('prov_btnLimpiarCoordenadas')?.classList.remove('d-none');

        if (_provTabUbicacionActiva()) {
            _provMapInicializarVisible(lat, lng);
        } else {
            _provPendingCoords = { lat, lng };
            _provIrTabUbicacion();
        }
    }

    function _provActualizarCoordenadas(lat, lng) {
        const latR = parseFloat(lat.toFixed(8));
        const lngR = parseFloat(lng.toFixed(8));
        const setV = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
        const setT = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        setV('prov_latitud',  latR);
        setV('prov_longitud', lngR);
        setT('prov_geo_lat_txt', latR);
        setT('prov_geo_lng_txt', lngR);
        document.getElementById('prov_geo_coords_display')?.classList.remove('d-none');
        document.getElementById('prov_geo_no_coords')?.classList.add('d-none');
        document.getElementById('prov_btnLimpiarCoordenadas')?.classList.remove('d-none');
    }

    window.provLimpiarCoordenadas = function() {
        const setV = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
        setV('prov_latitud',  '');
        setV('prov_longitud', '');
        document.getElementById('prov_geo_coords_display')?.classList.add('d-none');
        document.getElementById('prov_geo_no_coords')?.classList.remove('d-none');
        document.getElementById('prov_btnLimpiarCoordenadas')?.classList.add('d-none');
        const ft = document.getElementById('prov_geo_fecha_txt');
        if (ft) ft.textContent = '';
        if (_provMarcador && _provMapa) {
            _provMapa.removeLayer(_provMarcador);
            _provMarcador = null;
        }
        if (_provMapa) {
            _provMapa.setView([_PROV_DEFAULT_LAT, _PROV_DEFAULT_LNG], _PROV_DEFAULT_ZOOM);
        }
    };

    function _provMostrarGeoAlert(msg, tipo) {
        const el = document.getElementById('prov_geo_alert');
        if (!el) return;
        el.textContent = msg;
        el.className = `alert py-1 px-2 small border-0 alert-${tipo}`;
        el.classList.remove('d-none');
        setTimeout(() => el.classList.add('d-none'), 6000);
    }

    function _provIrTabUbicacion() {
        const tabEl = document.getElementById('prov-tab-ubicacion-btn');
        if (tabEl && typeof bootstrap !== 'undefined') {
            (bootstrap.Tab.getInstance(tabEl) || new bootstrap.Tab(tabEl)).show();
        }
    }

    function _provResetMapa() {
        if (_provMapa) {
            _provMapa.remove();
            _provMapa = null;
        }
        _provMarcador     = null;
        _provPendingCoords = null;
        const ph = document.getElementById('prov_mapa_placeholder');
        if (ph) ph.style.display = '';
        // Limpiar inputs y display
        ['prov_latitud', 'prov_longitud'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
        document.getElementById('prov_geo_coords_display')?.classList.add('d-none');
        document.getElementById('prov_geo_no_coords')?.classList.remove('d-none');
        document.getElementById('prov_btnLimpiarCoordenadas')?.classList.add('d-none');
        document.getElementById('prov_geo_alert')?.classList.add('d-none');
        const ft = document.getElementById('prov_geo_fecha_txt');
        if (ft) ft.textContent = '';
    }

    window.provGeocodificarDesdeAPI = async function() {
        const direccion = (document.getElementById('prov_direccion')?.value || '').trim();
        const selProv   = document.getElementById('prov_provincia');
        const selCiud   = document.getElementById('prov_ciudad');
        const provincia = selProv?.options[selProv.selectedIndex]?.text || '';
        const ciudad    = selCiud?.options[selCiud.selectedIndex]?.text || '';

        const partes = [
            direccion,
            ciudad    !== '-- Seleccione --' && ciudad    !== '-- Cargando --' ? ciudad    : '',
            provincia !== '-- Seleccione --'                                   ? provincia : '',
            'Ecuador',
        ].filter(Boolean);

        if (!partes.length) {
            _provMostrarGeoAlert('Complete primero la dirección en la pestaña General.', 'warning');
            _provIrTabUbicacion();
            return;
        }

        const btn = document.getElementById('prov_btnGeocodificar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Buscando...';

        try {
            const fd = new FormData();
            fd.append('direccion', partes.join(', '));
            const resp = await fetch(urlBaseProv + '/geocodificar', { method: 'POST', body: fd });
            const json = await resp.json();

            if (!json.ok) {
                _provMostrarGeoAlert(json.error || 'No se encontraron coordenadas.', 'warning');
                return;
            }

            const msgExito = json.msg
                ? '⚠ ' + json.msg
                : '✓ Ubicación encontrada. Puede arrastrar el marcador para ajustar.';
            _provMostrarGeoAlert(msgExito, json.msg ? 'warning' : 'success');
            _provProcesarCoordenadas(json.data.latitud, json.data.longitud);

        } catch (e) {
            _provMostrarGeoAlert('Error de conexión al geocodificar.', 'danger');
            console.error(e);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i> Obtener desde dirección';
        }
    };

    window.provUsarGps = function() {
        if (!navigator.geolocation) {
            _provMostrarGeoAlert('Su navegador no soporta geolocalización.', 'warning');
            return;
        }
        const btn = document.getElementById('prov_btnUsarGps');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Obteniendo...';

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-crosshair2 me-1"></i> Usar mi ubicación (GPS)';
                _provMostrarGeoAlert('GPS capturado. Cargando mapa...', 'success');
                _provProcesarCoordenadas(pos.coords.latitude, pos.coords.longitude);
            },
            (err) => {
                const msgs = { 1: 'Permiso denegado.', 2: 'Ubicación no disponible.', 3: 'Tiempo agotado.' };
                _provMostrarGeoAlert(msgs[err.code] || 'Error al obtener GPS.', 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-crosshair2 me-1"></i> Usar mi ubicación (GPS)';
            },
            { timeout: 10000, maximumAge: 60000 }
        );
    };

    // Aliases movidos al inicio del script para mayor seguridad

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProvModalEvents);
    } else {
        initProvModalEvents();
    }

})(window, document);
