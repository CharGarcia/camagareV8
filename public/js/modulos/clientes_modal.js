/**
 * Lógica compartida para el Modal de Clientes
 * Requiere que BASE_URL esté definido globalmente.
 */

(function (window, document) {
    'use strict';

    const urlBaseClientes = (window.BASE_URL || '') + '/modulos/clientes';
    let modalClienteInst = null;
    let datosCatalogos = null;
    let catalogosCargados = false;
    let sriDebounceTimer = null;

    function getModalCliente() {
        if (!modalClienteInst && typeof bootstrap !== 'undefined') {
            const el = document.getElementById('modalCliente');
            if (el) modalClienteInst = new bootstrap.Modal(el);
        }
        return modalClienteInst;
    }

    // ─── Helpers para el campo identificación ───────────────────────────────

    function getTipoNormalizado() {
        const sel = document.getElementById('cliente_tipo_id');
        if (!sel) return '';
        const codigo = (sel.value || '').trim().toUpperCase();
        const textoOpt = (sel.options[sel.selectedIndex]?.text || '').toUpperCase();
        
        if (textoOpt.includes('CONSUMIDOR') || codigo.includes('CONSUMIDOR')) return 'CONSUMIDOR_FINAL';
        if (textoOpt.includes('PASAPORTE') || codigo.includes('PAS')) return 'PASAPORTE';
        if (textoOpt.includes('CEDULA') || textoOpt.includes('CÉDULA') || codigo.includes('CED')) return 'CEDULA';
        if (textoOpt.includes('RUC')) return 'RUC';
        return codigo;
    }

    window.aplicarReglasIdentificacion = function () {
        const tipo = getTipoNormalizado();
        const campo = document.getElementById('cliente_identificacion');
        const nombre = document.getElementById('cliente_nombre');
        if (!campo) return;

        campo.readOnly = false;
        campo.classList.remove('field-sri-locked');
        campo.setAttribute('inputmode', 'numeric');
        campo.setAttribute('pattern', '');
        limpiarBadgeSri();

        if (nombre) {
            nombre.readOnly = false;
            nombre.classList.remove('field-sri-locked');
        }

        switch (tipo) {
            case 'RUC':
                campo.maxLength = 13;
                campo.setAttribute('inputmode', 'numeric');
                break;
            case 'CEDULA':
                campo.maxLength = 10;
                campo.setAttribute('inputmode', 'numeric');
                break;
            case 'PASAPORTE':
                campo.maxLength = 20;
                campo.setAttribute('inputmode', 'text');
                break;
            case 'CONSUMIDOR_FINAL':
                campo.maxLength = 13;
                campo.value = '9999999999999';
                campo.readOnly = true;
                campo.classList.add('field-sri-locked');
                if (nombre) {
                    nombre.value = 'CONSUMIDOR FINAL';
                    nombre.readOnly = true;
                    nombre.classList.add('field-sri-locked');
                }
                limpiarBadgeSri();
                break;
        }
    };

    function soloNumerosEnInput(e) {
        const tipo = getTipoNormalizado();
        if (tipo === 'RUC' || tipo === 'CEDULA') {
            const permitidos = ['Backspace', 'Delete', 'Tab', 'Enter', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];
            if (e.ctrlKey || e.metaKey) return;
            if (!permitidos.includes(e.key) && !/^\d$/.test(e.key)) {
                e.preventDefault();
            }
        }
    }

    // ─── Validación de identificación ────────────────────────────────────────
    function validarIdentificacion() {
        const tipo = getTipoNormalizado();
        const valor = (document.getElementById('cliente_identificacion')?.value || '').trim();
        const errEl = document.getElementById('identificacionError');

        const mostrarError = (msg) => {
            if (errEl) {
                errEl.textContent = msg;
                errEl.style.setProperty('display', 'block', 'important');
            }
        };
        const limpiarError = () => {
            if (errEl) {
                errEl.textContent = '';
                errEl.style.setProperty('display', 'none', 'important');
            }
        };

        switch (tipo) {
            case 'RUC':
                if (!/^\d{13}$/.test(valor)) {
                    mostrarError('El RUC debe tener exactamente 13 dígitos numéricos.');
                    return false;
                }
                const ultTres = valor.slice(-3);
                if (ultTres !== '001' && ultTres !== '002') {
                    mostrarError('Los últimos 3 dígitos del RUC deben ser 001 o 002.');
                    return false;
                }
                limpiarError();
                return true;
            case 'CEDULA':
                if (!/^\d{10}$/.test(valor)) {
                    mostrarError('La cédula debe tener exactamente 10 dígitos numéricos.');
                    return false;
                }
                limpiarError();
                return true;
            case 'PASAPORTE':
                if (valor.length === 0 || valor.length > 20) {
                    mostrarError('El pasaporte puede tener hasta 20 caracteres alfanuméricos.');
                    return false;
                }
                limpiarError();
                return true;
            case 'CONSUMIDOR_FINAL':
                limpiarError();
                return true;
            default:
                if (valor.length === 0) {
                    mostrarError('Ingrese la identificación.');
                    return false;
                }
                limpiarError();
                return true;
        }
    }

    // ─── Consulta SRI ────────────────────────────────────────────────────────
    function mostrarBadgeSri(texto, cls) {
        const b = document.getElementById('sriBadge');
        if (!b) return;
        b.textContent = texto;
        b.className = 'badge ' + cls;
        b.classList.remove('d-none');
    }

    function limpiarBadgeSri() {
        const b = document.getElementById('sriBadge');
        const sw = document.getElementById('sriSpinnerWrap');
        if (b) { b.className = 'badge d-none'; b.textContent = ''; }
        if (sw) sw.classList.add('d-none');
    }

    async function consultarSri(identificacion) {
        const sw = document.getElementById('sriSpinnerWrap');
        if (sw) sw.classList.remove('d-none');
        mostrarBadgeSri('Consultando SRI…', 'bg-secondary');
        try {
            const fd = new FormData();
            fd.append('identificacion', identificacion);
            const resp = await fetch(urlBaseClientes + '/consultarSri', { method: 'POST', body: fd });
            const json = await resp.json();
            if (sw) sw.classList.add('d-none');

            if (!json.ok) {
                mostrarBadgeSri('No encontrado', 'bg-warning text-dark');
                return;
            }

            const d = json.data;
            mostrarBadgeSri('✓ SRI', 'bg-success');

            if (d.nombre) {
                const el = document.getElementById('cliente_nombre');
                if (el && !el.readOnly) el.value = d.nombre;
            }
            if (d.direccion) {
                const el = document.getElementById('cliente_direccion');
                if (el) el.value = d.direccion;
            }
            if (d.cod_prov && d.cod_prov !== '') {
                const selProv = document.getElementById('cliente_provincia');
                if (selProv) {
                    selProv.value = d.cod_prov;
                    await cargarCiudades(d.cod_prov, d.cod_ciudad || '');
                }
            }
        } catch (err) {
            if (sw) sw.classList.add('d-none');
            mostrarBadgeSri('Error', 'bg-danger');
        }
    }

    function onIdentificacionInput() {
        const tipo = getTipoNormalizado();
        const valor = (document.getElementById('cliente_identificacion')?.value || '').trim();
        limpiarBadgeSri();
        clearTimeout(sriDebounceTimer);

        const longitudesValidas = { RUC: 13, CEDULA: 10 };
        const longEsperada = longitudesValidas[tipo];

        if (!longEsperada) return;

        if (valor.length === longEsperada) {
            sriDebounceTimer = setTimeout(() => {
                if (validarIdentificacion()) consultarSri(valor);
            }, 700);
        }
    }

    function validarEmails() {
        const campo = document.getElementById('cliente_email');
        const errEl = document.getElementById('emailError');
        if (!campo || !errEl) return true;

        const raw = campo.value.trim();
        if (raw === '') {
            errEl.textContent = 'El correo electrónico es obligatorio.';
            errEl.style.display = 'block';
            campo.classList.add('is-invalid');
            return false;
        }

        const correos = raw.split(',').map(s => s.trim()).filter(s => s !== '');
        const reEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const invalidos = correos.filter(c => !reEmail.test(c));

        if (invalidos.length > 0) {
            errEl.textContent = 'Correos inválidos: ' + invalidos.join(', ');
            errEl.style.display = 'block';
            campo.classList.add('is-invalid');
            return false;
        }
        errEl.style.display = 'none';
        campo.classList.remove('is-invalid');
        return true;
    }

    // ─── Catálogos ──────────────────────────────────────
    async function cargarCatalogos() {
        if (catalogosCargados) return;
        try {
            const resp = await fetch(`${urlBaseClientes}/catalogos`);
            const json = await resp.json();
            if (!json.ok) throw new Error(json.error || 'Error al cargar catálogos');
            datosCatalogos = json.data;
            catalogosCargados = true;
            poblarCatalogos();
        } catch (e) {
            console.error('Error cargando catálogos:', e);
        }
    }

    function poblarCatalogos() {
        if (!datosCatalogos) return;

        const selTipos = document.getElementById('cliente_tipo_id');
        if (selTipos) {
            selTipos.innerHTML = '<option value="">-- Seleccione --</option>';
            datosCatalogos.tipos_id.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.codigo;
                opt.textContent = item.nombre;
                selTipos.appendChild(opt);
            });
            window.aplicarReglasIdentificacion();
        }

        const selProv = document.getElementById('cliente_provincia');
        if (selProv) {
            selProv.innerHTML = '<option value="">— Seleccione provincia —</option>';
            datosCatalogos.provincias.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.codigo;
                opt.textContent = p.nombre;
                selProv.appendChild(opt);
            });
        }

        const selVend = document.getElementById('cliente_vendedor');
        if (selVend) {
            selVend.innerHTML = '<option value="">— Sin vendedor asignado —</option>';
            datosCatalogos.vendedores.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v.id;
                opt.textContent = v.nombre + (v.identificacion ? ' (' + v.identificacion + ')' : '');
                selVend.appendChild(opt);
            });
        }

        const selSri = document.getElementById('cliente_id_forma_pago_sri');
        if (selSri && datosCatalogos.formas_pago_sri) {
            selSri.innerHTML = '<option value="">— Seleccione forma de pago SRI —</option>';
            datosCatalogos.formas_pago_sri.forEach(f => {
                const opt = document.createElement('option');
                opt.value = f.id;
                opt.textContent = f.codigo + ' - ' + f.nombre;
                selSri.appendChild(opt);
            });
        }

        const selFc = document.getElementById('cliente_id_forma_cobro_predeterminada');
        if (selFc && datosCatalogos.formas_cobros_pagos) {
            selFc.innerHTML = '<option value="">— Seleccione forma de cobro —</option>';
            datosCatalogos.formas_cobros_pagos.forEach(f => {
                const opt = document.createElement('option');
                opt.value = f.id;
                opt.dataset.tipo = f.tipo || '';
                let texto = f.nombre;
                if (f.tipo !== 'EFECTIVO' && f.banco_nombre) {
                    texto += ' (' + f.banco_nombre + ')';
                }
                opt.textContent = texto;
                selFc.appendChild(opt);
            });
            
            // Lógica para mostrar operación bancaria
            selFc.addEventListener('change', function() {
                const optSel = this.options[this.selectedIndex];
                const wrp = document.getElementById('wrapper_tipo_operacion_bancaria');
                if (!wrp) return;
                if (optSel && optSel.dataset.tipo === 'BANCO') {
                    wrp.classList.remove('d-none');
                } else {
                    wrp.classList.add('d-none');
                    document.getElementById('cliente_tipo_operacion_bancaria').value = 'TRANSFERENCIA';
                }
            });
        }

        const selConcepto = document.getElementById('cliente_id_ingreso_concepto');
        if (selConcepto && datosCatalogos.conceptos_ingreso) {
            selConcepto.innerHTML = '<option value="">-- Seleccione --</option>';
            let idVentaDefault = null;
            let fallbackDefault = null;
            
            datosCatalogos.conceptos_ingreso.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.nombre;
                
                if (c.comportamiento === 'FACTURA_VENTA' || c.comportamiento === 'COBRO_FACTURA') {
                    idVentaDefault = c.id;
                } else {
                    const n = (c.nombre || '').toLowerCase();
                    if (n.includes('venta') || n.includes('factura')) {
                        fallbackDefault = c.id;
                    }
                }
                selConcepto.appendChild(opt);
            });
            
            if (!idVentaDefault && fallbackDefault) {
                idVentaDefault = fallbackDefault;
            }
            
            if (idVentaDefault) {
                selConcepto.value = idVentaDefault;
            }
            
            const hdConcepto = document.getElementById('cliente_id_ingreso_concepto_hidden');
            if (hdConcepto) hdConcepto.value = selConcepto.value;
        }
    }



    async function cargarCiudades(codProv, valorCiudad) {
        const sel = document.getElementById('cliente_ciudad');
        if (!sel) return;
        if (!codProv) { sel.innerHTML = '<option value="">— Seleccione ciudad —</option>'; return; }
        sel.innerHTML = '<option value="">Cargando...</option>'; sel.disabled = true;
        try {
            const resp = await fetch(urlBaseClientes + '/ciudades?cod_prov=' + encodeURIComponent(codProv));
            const json = await resp.json();
            sel.innerHTML = '<option value="">— Seleccione ciudad —</option>';
            if (json.ok) {
                json.data.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.codigo; opt.textContent = c.nombre; sel.appendChild(opt);
                });
                if (valorCiudad) sel.value = valorCiudad;
            }
        } catch (e) { sel.innerHTML = '<option value="">Error</option>'; } finally { sel.disabled = false; }
    }

    // ─── Mapa Leaflet ────────────────────────────────────────────────────────
    let _mapaCliente     = null;
    let _marcadorCliente = null;
    let _pendingMapCoords = null;

    // Centro por defecto (Ecuador)
    const _DEFAULT_LAT = -1.8312;
    const _DEFAULT_LNG = -78.1834;
    const _DEFAULT_ZOOM = 6;

    function _mapCrearBase() {
        if (_mapaCliente) return; // ya inicializado
        const placeholder = document.getElementById('mapa_placeholder');
        if (placeholder) placeholder.style.display = 'none';

        _mapaCliente = L.map('mapa_cliente', { preferCanvas: true }).setView([_DEFAULT_LAT, _DEFAULT_LNG], _DEFAULT_ZOOM);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19
        }).addTo(_mapaCliente);
        setTimeout(() => _mapaCliente.invalidateSize(), 150);
    }

    function _mapInicializarVisible(lat, lng) {
        const placeholder = document.getElementById('mapa_placeholder');
        if (placeholder) placeholder.style.display = 'none';

        if (!_mapaCliente) {
            _mapaCliente = L.map('mapa_cliente', { preferCanvas: true }).setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19
            }).addTo(_mapaCliente);
        } else {
            _mapaCliente.setView([lat, lng], 15);
        }

        if (_marcadorCliente) {
            _marcadorCliente.setLatLng([lat, lng]);
        } else {
            _marcadorCliente = L.marker([lat, lng], { draggable: true })
                .addTo(_mapaCliente)
                .bindPopup('Arrastra para ajustar la posición exacta.')
                .openPopup();
            _marcadorCliente.on('dragend', (e) => {
                const p = e.target.getLatLng();
                _actualizarCoordenadas(p.lat, p.lng);
            });
        }

        setTimeout(() => _mapaCliente.invalidateSize(), 150);
        _actualizarCoordenadas(lat, lng);
    }

    /** Verifica si la pestaña Ubicación ya está activa y visible */
    function _tabUbicacionActiva() {
        const pane = document.getElementById('pane-ubicacion');
        return pane && pane.classList.contains('active') && pane.classList.contains('show');
    }

    /** Procesa coordenadas: guarda en inputs ocultos DE INMEDIATO y luego actualiza el mapa */
    function _procesarCoordenadas(lat, lng) {
        // Guardar en los inputs ocultos de inmediato para que FormData los capture
        // aunque el usuario guarde sin abrir la pestaña Ubicación
        const latR = parseFloat(lat.toFixed(8));
        const lngR = parseFloat(lng.toFixed(8));
        const setV = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
        const setT = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        setV('cliente_latitud',  latR);
        setV('cliente_longitud', lngR);
        setT('geo_lat_txt', latR);
        setT('geo_lng_txt', lngR);
        document.getElementById('geo_coords_display')?.classList.remove('d-none');
        document.getElementById('geo_no_coords')?.classList.add('d-none');
        document.getElementById('btnLimpiarCoordenadas')?.classList.remove('d-none');

        if (_tabUbicacionActiva()) {
            _mapInicializarVisible(lat, lng);
        } else {
            _pendingMapCoords = { lat, lng };
            _irTabUbicacion();
        }
    }

    function _actualizarCoordenadas(lat, lng) {
        const latR = parseFloat(lat.toFixed(8));
        const lngR = parseFloat(lng.toFixed(8));
        const setV = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
        const setT = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        setV('cliente_latitud',  latR);
        setV('cliente_longitud', lngR);
        setT('geo_lat_txt', latR);
        setT('geo_lng_txt', lngR);
        document.getElementById('geo_coords_display')?.classList.remove('d-none');
        document.getElementById('geo_no_coords')?.classList.add('d-none');
        document.getElementById('btnLimpiarCoordenadas')?.classList.remove('d-none');
    }

    window.limpiarCoordenadas = function() {
        const setV = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
        setV('cliente_latitud', '');
        setV('cliente_longitud', '');
        document.getElementById('geo_coords_display')?.classList.add('d-none');
        document.getElementById('geo_no_coords')?.classList.remove('d-none');
        document.getElementById('btnLimpiarCoordenadas')?.classList.add('d-none');
        const ft = document.getElementById('geo_fecha_txt');
        if (ft) ft.textContent = '';
        if (_marcadorCliente && _mapaCliente) {
            _mapaCliente.removeLayer(_marcadorCliente);
            _marcadorCliente = null;
        }
        // Volver a la vista general si el mapa está activo
        if (_mapaCliente) {
            _mapaCliente.setView([_DEFAULT_LAT, _DEFAULT_LNG], _DEFAULT_ZOOM);
        }
    };

    function _mostrarGeoAlert(msg, tipo) {
        const el = document.getElementById('geo_alert');
        if (!el) return;
        el.textContent = msg;
        el.className = `alert py-1 px-2 small border-0 alert-${tipo}`;
        el.classList.remove('d-none');
        setTimeout(() => el.classList.add('d-none'), 6000);
    }

    function _irTabUbicacion() {
        const tabEl = document.getElementById('tab-ubicacion-btn');
        if (tabEl && typeof bootstrap !== 'undefined') {
            (bootstrap.Tab.getInstance(tabEl) || new bootstrap.Tab(tabEl)).show();
        }
    }

    window.geocodificarDesdeAPI = async function() {
        const direccion = (document.getElementById('cliente_direccion')?.value || '').trim();
        const selProv   = document.getElementById('cliente_provincia');
        const selCiud   = document.getElementById('cliente_ciudad');
        const provincia = selProv?.options[selProv.selectedIndex]?.text || '';
        const ciudad    = selCiud?.options[selCiud.selectedIndex]?.text || '';

        const partes = [
            direccion,
            ciudad    !== '— Seleccione ciudad —'    && ciudad    !== '- Seleccione ciudad -'    ? ciudad    : '',
            provincia !== '— Seleccione provincia —' && provincia !== '- Seleccione provincia -' ? provincia : '',
            'Ecuador',
        ].filter(Boolean);

        if (!partes.length) {
            _mostrarGeoAlert('Complete primero la dirección en la pestaña General.', 'warning');
            _irTabUbicacion();
            return;
        }

        const btn = document.getElementById('btnGeocodificar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Buscando...';

        try {
            const fd = new FormData();
            fd.append('direccion', partes.join(', '));
            const resp = await fetch(urlBaseClientes + '/geocodificar', { method: 'POST', body: fd });
            const json = await resp.json();

            if (!json.ok) {
                _mostrarGeoAlert(json.error || 'No se encontraron coordenadas.', 'warning');
                return;
            }

            const msgExito = json.msg
                ? '⚠ ' + json.msg
                : '✓ Ubicación encontrada. Puede arrastrar el marcador para ajustar.';
            _mostrarGeoAlert(msgExito, json.msg ? 'warning' : 'success');
            _procesarCoordenadas(json.data.latitud, json.data.longitud);

        } catch (e) {
            _mostrarGeoAlert('Error de conexión al geocodificar.', 'danger');
            console.error(e);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i> Obtener desde dirección';
        }
    };

    window.usarGps = function() {
        if (!navigator.geolocation) {
            _mostrarGeoAlert('Su navegador no soporta geolocalización.', 'warning');
            return;
        }
        const btn = document.getElementById('btnUsarGps');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Obteniendo...';

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-crosshair2 me-1"></i> Usar mi ubicación (GPS)';
                _mostrarGeoAlert('GPS capturado. Cargando mapa...', 'success');
                _procesarCoordenadas(pos.coords.latitude, pos.coords.longitude);
            },
            (err) => {
                const msgs = { 1: 'Permiso denegado.', 2: 'Ubicación no disponible.', 3: 'Tiempo agotado.' };
                _mostrarGeoAlert(msgs[err.code] || 'Error al obtener GPS.', 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-crosshair2 me-1"></i> Usar mi ubicación (GPS)';
            },
            { timeout: 10000, maximumAge: 60000 }
        );
    };

    // ─── Reset formulario ────────────────────────────────────────────────────
    function resetFormulario() {
        const form = document.getElementById('formCliente');
        if (form) form.reset();

        // Reset mapa
        const inputLat = document.getElementById('cliente_latitud');
        const inputLng = document.getElementById('cliente_longitud');
        if (inputLat) inputLat.value = '';
        if (inputLng) inputLng.value = '';
        document.getElementById('geo_coords_display')?.classList.add('d-none');
        document.getElementById('geo_no_coords')?.classList.remove('d-none');
        document.getElementById('btnLimpiarCoordenadas')?.classList.add('d-none');
        document.getElementById('geo_alert')?.classList.add('d-none');
        const ft = document.getElementById('geo_fecha_txt');
        if (ft) ft.textContent = '';
        // Destruir el mapa para que pueda reinicializarse la próxima vez
        if (_mapaCliente) {
            _mapaCliente.remove();
            _mapaCliente = null;
        }
        _marcadorCliente = null;
        _pendingMapCoords = null;
        // Restaurar el placeholder del mapa
        const ph = document.getElementById('mapa_placeholder');
        if (ph) ph.style.display = '';

        const inputId = document.getElementById('cliente_id');
        if (inputId) inputId.value = '';

        const alertEl = document.getElementById('modalAlert');
        if (alertEl) {
            alertEl.classList.add('d-none');
            alertEl.textContent = '';
        }

        // Reset tabs
        const firstTab = document.getElementById('tab-general-btn');
        if (firstTab && typeof bootstrap !== 'undefined') {
            try {
                const tabInst = bootstrap.Tab.getInstance(firstTab) || new bootstrap.Tab(firstTab);
                tabInst.show();
            } catch (err) {
                console.warn('No se pudo resetear la pestaña:', err);
            }
        }


        // IMPORTANTE: Resetear botones SIEMPRE
        const btnSave = document.getElementById('btnGuardarCliente');
        if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
        }

        const btnDlt = document.getElementById('btnEliminarCliente');
        if (btnDlt) {
            btnDlt.classList.add('d-none');
            btnDlt.disabled = false;
            btnDlt.innerHTML = '<i class="bi bi-trash3 me-1"></i> Eliminar';
        }

        const titleEl = document.getElementById('tituloModalCliente');
        if (titleEl) titleEl.textContent = 'Nuevo Cliente';
        
        limpiarBadgeSri();
    }

    // ─── Abrir modal Crear ───────────────────────────────────────────────────
    window.abrirModalClienteCrear = async function() {
        const modal = getModalCliente();
        if (!modal) return;
        resetFormulario();
        document.getElementById('formCliente').action = urlBaseClientes + '/store';

        await cargarCatalogos();
        if (typeof window.aplicarFavoritosModal === 'function') {
            window.aplicarFavoritosModal('#modalCliente');
        }

        // El usuario prefiere que empiece vacío
        const selTipos = document.getElementById('cliente_tipo_id');
        if (selTipos) selTipos.value = "";

        window.aplicarReglasIdentificacion();
        modal.show();
    };

    // ─── Abrir modal Editar ──────────────────────────────────────────────────
    window.abrirModalClienteEditar = async function(rowOrData) {
        const modal = getModalCliente();
        if (!modal) return;
        resetFormulario();
        document.getElementById('formCliente').action = urlBaseClientes + '/update';

        const data = (rowOrData instanceof HTMLElement) ? JSON.parse(rowOrData.dataset.cliente) : rowOrData;
        if (!data) return;

        await cargarCatalogos();

        const setV = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };
        const setT = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || ''; };

        setV('cliente_id', data.id);
        setV('cliente_tipo_id', data.tipo_id);
        window.aplicarReglasIdentificacion();
        setV('cliente_identificacion', data.identificacion);
        setV('cliente_nombre', data.nombre);
        setV('cliente_email', data.email);
        setV('cliente_telefono', data.telefono);
        setV('cliente_direccion', data.direccion);
        setV('cliente_plazo', data.plazo || 0);
        setV('cliente_status', data.status || '1');

        if (data.provincia) {
            const selProv = document.getElementById('cliente_provincia');
            if (selProv) {
                selProv.value = data.provincia;
                await cargarCiudades(data.provincia, data.ciudad || '');
            }
        }

        if (data.id_vendedor) setV('cliente_vendedor', data.id_vendedor);



        setV('cliente_id_forma_pago_sri', data.id_forma_pago_sri);
        setV('cliente_id_forma_cobro_predeterminada', data.id_forma_cobro_predeterminada);
        setV('cliente_tipo_operacion_bancaria', data.tipo_operacion_bancaria_predeterminada || 'TRANSFERENCIA');
        setV('cliente_monto_maximo_auto_cobro', data.monto_maximo_auto_cobro);

        if (data.id_ingreso_concepto_predeterminado) {
            setV('cliente_id_ingreso_concepto', data.id_ingreso_concepto_predeterminado);
            setV('cliente_id_ingreso_concepto_hidden', data.id_ingreso_concepto_predeterminado);
        }

        const selFc = document.getElementById('cliente_id_forma_cobro_predeterminada');
        if (selFc) {
            // trigger change to show/hide the wrapper
            selFc.dispatchEvent(new Event('change'));
        }

        // Coordenadas
        if (data.latitud && data.longitud) {
            const lat = parseFloat(data.latitud);
            const lng = parseFloat(data.longitud);
            setV('cliente_latitud', lat);
            setV('cliente_longitud', lng);
            setT('geo_lat_txt', lat);
            setT('geo_lng_txt', lng);
            document.getElementById('geo_coords_display')?.classList.remove('d-none');
            document.getElementById('geo_no_coords')?.classList.add('d-none');
            document.getElementById('btnLimpiarCoordenadas')?.classList.remove('d-none');
            if (data.geocodificado_en) {
                const ft = document.getElementById('geo_fecha_txt');
                if (ft) ft.textContent = '(geocodificado: ' + data.geocodificado_en + ')';
            }
            // Guardar para cuando el usuario abra la pestaña Ubicación
            _pendingMapCoords = { lat, lng };
        }

        setT('tituloModalCliente', 'Ficha de Cliente');
        const btnDlt = document.getElementById('btnEliminarCliente');
        if (btnDlt) btnDlt.classList.remove('d-none');

        fetchEstadisticas(data.id);
        modal.show();
    };

    async function fetchEstadisticas(id) {
        if (!id) return;
        try {
            const resp = await fetch(`${urlBaseClientes}/estadisticas?id=${id}`);
            const json = await resp.json();
            if (json.ok) {
                const d = json.data;
                const fmt = (v) => '$' + parseFloat(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
                
                document.getElementById('stat_facturas').textContent = d.facturas_emitidas || 0;
                document.getElementById('stat_ventas').textContent = fmt(d.total_ventas);
                document.getElementById('stat_subtotal').textContent = fmt(d.total_subtotal);
                document.getElementById('stat_nc').textContent = fmt(d.total_nc);
                document.getElementById('stat_anuladas').textContent = d.facturas_anuladas || 0;
            }
        } catch (e) {}
    }


    window.eliminarCliente = async function() {
        const id = document.getElementById('cliente_id').value;
        if (!id) return;

        const result = await Swal.fire({
            title: '¿Está seguro?',
            text: "No podrá revertir esta acción.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });

        if (!result.isConfirmed) return;

        const btnDlt = document.getElementById('btnEliminarCliente');
        btnDlt.disabled = true; 
        btnDlt.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Eliminando...';
        
        try {
            const fd = new FormData(); 
            fd.append('id_eliminar', id);
            const resp = await fetch(`${urlBaseClientes}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            
            if (json.ok) {
                Swal.fire({
                    icon: 'success',
                    title: 'Eliminado',
                    text: json.msg || 'Cliente eliminado correctamente.',
                    timer: 1500,
                    showConfirmButton: false
                });
                setTimeout(() => { 
                    getModalCliente().hide(); 
                    if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1); 
                }, 1500);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: json.error || 'No se pudo eliminar el cliente.'
                });
                btnDlt.disabled = false; 
                btnDlt.innerHTML = '<i class="bi bi-trash3 me-1"></i> Eliminar'; 
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Error de Red',
                text: 'No se pudo conectar con el servidor.'
            });
            btnDlt.disabled = false; 
            btnDlt.innerHTML = '<i class="bi bi-trash3 me-1"></i> Eliminar'; 
        }
    };

    function initEvents() {
        const form = document.getElementById('formCliente');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!validarIdentificacion() || !validarEmails()) return;
                    const btnSave = document.getElementById('btnGuardarCliente');
                    btnSave.disabled = true; 
                    btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
                    try {
                        const fd = new FormData(form);
                        const resp = await fetch(form.action, { method: 'POST', body: fd });
                        const json = await resp.json();

                        if (json.ok) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: json.msg || 'Guardado correctamente.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        setTimeout(() => { 
                            btnSave.disabled = false;
                            btnSave.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
                            getModalCliente().hide(); 
                            if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1); 
                            document.dispatchEvent(new CustomEvent('clienteGuardado', { detail: { ...json, nombre: json.data?.nombre || '' } }));
                        }, 1500);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Atención',
                            text: json.error || 'No se pudo guardar la información.'
                        });
                        btnSave.disabled = false; 
                        btnSave.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar'; 
                    }
                } catch (err) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Red',
                        text: 'No se pudo conectar con el servidor.'
                    });
                    btnSave.disabled = false; 
                    btnSave.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar'; 
                }
            });
        }

        const selTipo = document.getElementById('cliente_tipo_id');
        if (selTipo) {
            selTipo.addEventListener('change', () => { 
                window.aplicarReglasIdentificacion(); 
                const tipo = getTipoNormalizado();
                const campo = document.getElementById('cliente_identificacion');
                if (tipo !== 'CONSUMIDOR_FINAL' && campo && !campo.readOnly) {
                    campo.value = '';
                }
            });
        }

        const campoId = document.getElementById('cliente_identificacion');
        if (campoId) {
            campoId.addEventListener('keydown', soloNumerosEnInput);
            campoId.addEventListener('input', onIdentificacionInput);
        }

        const selProv = document.getElementById('cliente_provincia');
        if (selProv) selProv.addEventListener('change', () => cargarCiudades(selProv.value, ''));

        const campoEmail = document.getElementById('cliente_email');
        if (campoEmail) campoEmail.addEventListener('blur', validarEmails);

        // Tab Ubicación: inicializar/actualizar mapa cuando el contenedor ya es visible
        const tabUbicBtn = document.getElementById('tab-ubicacion-btn');
        if (tabUbicBtn) {
            tabUbicBtn.addEventListener('shown.bs.tab', function() {
                if (_pendingMapCoords) {
                    // Hay coordenadas pendientes → mostrar con marcador
                    const { lat, lng } = _pendingMapCoords;
                    _pendingMapCoords = null;
                    _mapInicializarVisible(lat, lng);
                } else if (_mapaCliente) {
                    // Mapa ya existe → solo corregir tamaño
                    setTimeout(() => _mapaCliente.invalidateSize(), 150);
                } else {
                    // Sin coordenadas → mostrar mapa base (vista general del país)
                    _mapCrearBase();
                }
            });
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initEvents);
    else initEvents();

})(window, document);
