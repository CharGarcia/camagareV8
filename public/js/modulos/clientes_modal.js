/**
 * Lógica compartida para el Modal de Clientes
 * Requiere que BASE_URL esté definido globalmente.
 */

(function (window, document) {
    'use strict';

    const urlBaseClientes = (typeof BASE_URL !== 'undefined' ? BASE_URL : (typeof B_URL !== 'undefined' ? B_URL : '')) + '/modulos/clientes';
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
                if (wrp) {
                    if (optSel && optSel.dataset.tipo === 'BANCO') {
                        wrp.classList.remove('d-none');
                    } else {
                        wrp.classList.add('d-none');
                        document.getElementById('cliente_tipo_operacion_bancaria').value = 'TRANSFERENCIA';
                    }
                }
                cliToggleCheque();
                cliToggleBotonCobrosPendientes();
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

    // ─── Replicar cliente en otras empresas del usuario ─────────────────────
    let empresasDestinoCache = null;

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    async function cargarEmpresasDestino() {
        if (empresasDestinoCache) return empresasDestinoCache;
        try {
            const resp = await fetch(urlBaseClientes + '/empresasDestinoAjax');
            const json = await resp.json();
            empresasDestinoCache = json.ok ? (json.data || []) : [];
        } catch (e) {
            empresasDestinoCache = [];
        }
        return empresasDestinoCache;
    }

    // TomSelect (ya cargado globalmente vía partials/scripts.php) para poder
    // buscar entre muchas empresas en vez de pintar un checkbox por cada una.
    let tsReplicarEmpresas = null;

    function pintarReplicarLista(empresas) {
        const wrap = document.getElementById('cliente_replicar_wrap');
        const sel = document.getElementById('cliente_replicar_select');
        if (!wrap || !sel) return;

        if (!empresas.length) {
            wrap.classList.add('d-none');
            return;
        }
        wrap.classList.remove('d-none');

        if (!tsReplicarEmpresas && typeof TomSelect !== 'undefined') {
            tsReplicarEmpresas = new TomSelect(sel, {
                plugins: ['remove_button'],
                placeholder: 'Busque y agregue empresas...',
                valueField: 'id_empresa',
                labelField: 'texto',
                searchField: ['texto'],
                options: empresas,
                items: [],
                maxOptions: null,
            });
        } else if (tsReplicarEmpresas) {
            tsReplicarEmpresas.clearOptions();
            tsReplicarEmpresas.addOptions(empresas);
        }
    }

    function resetReplicarUI() {
        const toggle = document.getElementById('cliente_replicar_toggle');
        const listaWrap = document.getElementById('cliente_replicar_lista_wrap');
        if (toggle) toggle.checked = false;
        if (listaWrap) listaWrap.classList.add('d-none');
        if (tsReplicarEmpresas) tsReplicarEmpresas.clear(true);
    }

    /** Prepara la sección "Aplicar también en otras empresas" del modal de ficha. */
    async function inicializarReplicarUI() {
        const empresas = await cargarEmpresasDestino();
        pintarReplicarLista(empresas);
        resetReplicarUI();
        // De paso, muestra/oculta el botón masivo del listado (si existe en esta vista).
        const btnMasivo = document.getElementById('btnCopiarClientesEmpresa');
        if (btnMasivo) btnMasivo.classList.toggle('d-none', empresas.length === 0);
    }

    function nombreEmpresaDestino(id) {
        const emp = (empresasDestinoCache || []).find(e => String(e.id_empresa) === String(id));
        return escapeHtml(emp ? emp.texto : ('Empresa #' + id));
    }

    /** Arma el HTML resumen de la replicación individual (respuesta de store/update). */
    function resumenReplicado(replicado) {
        if (!replicado) return '';
        const grupos = { creado: [], reactivado: [], omitido: [], sin_permiso: [], error: [] };
        Object.entries(replicado).forEach(([id, r]) => {
            const grupo = grupos[r.estado];
            if (grupo) grupo.push(nombreEmpresaDestino(id) + (r.estado === 'error' && r.mensaje ? ` (${escapeHtml(r.mensaje)})` : ''));
        });

        let html = '';
        if (grupos.creado.length) html += `<div class="small text-success mb-1"><i class="bi bi-check-circle me-1"></i>Creado en: ${grupos.creado.join(', ')}</div>`;
        if (grupos.reactivado.length) html += `<div class="small text-primary mb-1"><i class="bi bi-arrow-clockwise me-1"></i>Reactivado en: ${grupos.reactivado.join(', ')}</div>`;
        if (grupos.omitido.length) html += `<div class="small text-muted mb-1"><i class="bi bi-dash-circle me-1"></i>Ya existía (sin cambios) en: ${grupos.omitido.join(', ')}</div>`;
        if (grupos.sin_permiso.length) html += `<div class="small text-warning mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Sin permiso en: ${grupos.sin_permiso.join(', ')}</div>`;
        if (grupos.error.length) html += `<div class="small text-danger mb-1"><i class="bi bi-x-circle me-1"></i>Error en: ${grupos.error.join(', ')}</div>`;
        return html;
    }

    // ─── Modal masivo: copiar TODOS los clientes a otra empresa ─────────────
    let tsCopiarEmpresa = null;

    window.abrirModalCopiarClientesEmpresa = async function () {
        const modalEl = document.getElementById('modalCopiarClientesEmpresa');
        if (!modalEl || typeof bootstrap === 'undefined') return;

        const sel = document.getElementById('copiarClientesEmpresaSelect');
        const empresas = await cargarEmpresasDestino();

        if (!tsCopiarEmpresa && sel && typeof TomSelect !== 'undefined') {
            tsCopiarEmpresa = new TomSelect(sel, {
                placeholder: 'Busque la empresa destino...',
                valueField: 'id_empresa',
                labelField: 'texto',
                searchField: ['texto'],
                maxOptions: null,
            });
        }
        if (tsCopiarEmpresa) {
            tsCopiarEmpresa.clearOptions();
            tsCopiarEmpresa.addOptions(empresas);
            tsCopiarEmpresa.clear(true);
        }

        new bootstrap.Modal(modalEl).show();
    };

    window.confirmarCopiarClientesEmpresa = async function () {
        const idEmpresaDestino = tsCopiarEmpresa
            ? tsCopiarEmpresa.getValue()
            : (document.getElementById('copiarClientesEmpresaSelect')?.value || '');
        if (!idEmpresaDestino) {
            Swal.fire({ icon: 'warning', title: 'Seleccione una empresa destino.' });
            return;
        }
        const empresaTexto = nombreEmpresaDestino(idEmpresaDestino); // ya viene escapado

        const confirmacion = await Swal.fire({
            icon: 'question',
            title: '¿Copiar todos los clientes?',
            html: `Se copiarán los clientes de esta empresa hacia <b>${empresaTexto}</b>.<br>Los que ya existan allí (misma identificación) no se duplican ni se sobrescriben.`,
            showCancelButton: true,
            confirmButtonText: 'Sí, copiar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d'
        });
        if (!confirmacion.isConfirmed) return;

        const btn = document.getElementById('btnConfirmarCopiarClientes');
        const htmlOriginal = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Copiando...';

        try {
            const fd = new FormData();
            fd.append('id_empresa_destino', idEmpresaDestino);
            const resp = await fetch(urlBaseClientes + '/replicarTodosAjax', { method: 'POST', body: fd });
            const json = await resp.json();

            if (!json.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'No se pudo copiar.' });
                return;
            }

            const modalEl = document.getElementById('modalCopiarClientesEmpresa');
            if (modalEl && typeof bootstrap !== 'undefined') {
                (bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl)).hide();
            }

            Swal.fire({
                icon: 'success',
                title: 'Copia completada',
                html: `
                    <div class="small text-start">
                        <div>Total revisados: <b>${json.total}</b></div>
                        <div class="text-success">Creados: <b>${json.creados}</b></div>
                        <div class="text-primary">Reactivados: <b>${json.reactivados}</b></div>
                        <div class="text-muted">Ya existían (sin cambios): <b>${json.omitidos}</b></div>
                        ${json.errores ? `<div class="text-danger">Errores: <b>${json.errores}</b></div>` : ''}
                    </div>
                `
            });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo comunicar con el servidor.' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = htmlOriginal;
        }
    };

    // ─── Pestaña Visitas (ruta del vendedor) ─────────────────────────────────
    // Días en ISO-8601 (1=Lunes .. 7=Domingo), igual que en PHP y en Postgres.
    const DIAS_VISITA_CORTO = { 1: 'Lun', 2: 'Mar', 3: 'Mié', 4: 'Jue', 5: 'Vie', 6: 'Sáb', 7: 'Dom' };
    const FRECUENCIAS_VISITA = { SEMANAL: 'Semanal', QUINCENAL: 'Quincenal', MENSUAL: 'Mensual' };

    /** Números marcados en un grupo de checkboxes de la pestaña Visitas. */
    function cliLeerGrupoVisita(nombre) {
        return Array.from(document.querySelectorAll(`#formCliente input[name="${nombre}[]"]:checked`))
            .map(chk => parseInt(chk.value, 10))
            .filter(n => !isNaN(n))
            .sort((a, b) => a - b);
    }

    /** Marca en el grupo los números recibidos (array, o "{1,3}" por si llega crudo). */
    function cliMarcarGrupoVisita(nombre, valores) {
        let lista = valores;
        if (typeof lista === 'string') {
            lista = lista.replace(/[{}]/g, '').split(',');
        }
        const marcados = Array.isArray(lista)
            ? lista.map(v => parseInt(v, 10)).filter(n => !isNaN(n))
            : [];

        document.querySelectorAll(`#formCliente input[name="${nombre}[]"]`).forEach(chk => {
            chk.checked = marcados.includes(parseInt(chk.value, 10));
        });
    }

    /**
     * Sincroniza la pestaña: muestra/oculta las semanas del mes (solo aplican a
     * quincenal y mensual) y redibuja el resumen de la pauta.
     */
    function cliSincronizarVisitas() {
        const selFrec = document.getElementById('cliente_frecuencia_visita');
        const wrapSemanas = document.getElementById('cliente_wrapper_semanas');
        const frecuencia = (selFrec?.value || '').toUpperCase();
        const dias = cliLeerGrupoVisita('dias_visita');

        // Semanal = todas las semanas, así que la elección de semanas no aplica.
        const mostrarSemanas = dias.length > 0 && frecuencia !== '' && frecuencia !== 'SEMANAL';
        if (wrapSemanas) {
            wrapSemanas.classList.toggle('d-none', !mostrarSemanas);
            if (!mostrarSemanas) cliMarcarGrupoVisita('semanas_visita', []);
        }

        const resumenEl = document.getElementById('cliente_resumen_visita');
        if (!resumenEl) return;

        if (dias.length === 0) {
            resumenEl.textContent = 'Sin ruta de visita definida';
            resumenEl.classList.add('text-muted');
            resumenEl.classList.remove('text-dark');
            return;
        }

        const partes = [];
        if (FRECUENCIAS_VISITA[frecuencia]) partes.push(FRECUENCIAS_VISITA[frecuencia]);
        partes.push(dias.map(d => DIAS_VISITA_CORTO[d]).join(', '));

        if (mostrarSemanas) {
            const semanas = cliLeerGrupoVisita('semanas_visita');
            if (semanas.length) partes.push(semanas.map(s => 'S' + s).join(', '));
        }

        const desde = document.getElementById('cliente_hora_visita_desde')?.value || '';
        const hasta = document.getElementById('cliente_hora_visita_hasta')?.value || '';
        if (desde && hasta) partes.push(`${desde}-${hasta}`);
        else if (desde) partes.push(`desde ${desde}`);
        else if (hasta) partes.push(`hasta ${hasta}`);

        resumenEl.textContent = partes.join(' · ');
        resumenEl.classList.remove('text-muted');
        resumenEl.classList.add('text-dark');
    }

    /** Vuelca la pauta de visita de la ficha en los controles de la pestaña. */
    function cliPoblarVisitas(data) {
        cliMarcarGrupoVisita('dias_visita', data.dias_visita);

        const selFrec = document.getElementById('cliente_frecuencia_visita');
        if (selFrec) selFrec.value = (data.frecuencia_visita || '').toUpperCase();

        // Después de fijar la frecuencia: sincronizar muestra el bloque de
        // semanas, y solo entonces tiene sentido marcarlas.
        cliSincronizarVisitas();
        cliMarcarGrupoVisita('semanas_visita', data.semanas_visita);

        const setV = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };
        setV('cliente_orden_visita', data.orden_visita);
        // Postgres devuelve TIME como "08:00:00" y el input type=time espera "08:00".
        setV('cliente_hora_visita_desde', (data.hora_visita_desde || '').substring(0, 5));
        setV('cliente_hora_visita_hasta', (data.hora_visita_hasta || '').substring(0, 5));
        setV('cliente_observacion_visita', data.observacion_visita);

        cliSincronizarVisitas();
    }

    // ─── Reset formulario ────────────────────────────────────────────────────
    function resetFormulario() {
        const form = document.getElementById('formCliente');
        if (form) form.reset();

        // form.reset() devuelve los checkboxes a su estado del HTML (todos sin
        // marcar), pero no reevalúa la visibilidad del bloque de semanas ni el
        // resumen: hay que forzarlo.
        cliSincronizarVisitas();

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
        await inicializarReplicarUI();
        if (typeof window.aplicarFavoritosModal === 'function') {
            window.aplicarFavoritosModal('#modalCliente');
        }

        // Empieza vacío salvo que exista un favorito para el tipo de identificación
        const selTipos = document.getElementById('cliente_tipo_id');
        const favTipo = (typeof APP_FAVORITOS !== 'undefined') ? APP_FAVORITOS['tipo_id'] : undefined;
        if (selTipos && (favTipo === undefined || favTipo === '')) selTipos.value = "";

        window.aplicarReglasIdentificacion();

        // El favorito de frecuencia pudo dejar el select con valor: reevaluar
        // el bloque de semanas y el resumen de la pauta.
        cliSincronizarVisitas();

        // Cliente nuevo: sin id todavía, así que no aplica el cobro retroactivo
        document.getElementById('cliente_div_cheque')?.classList.add('d-none');
        document.getElementById('cliente_div_cobros_pendientes')?.classList.add('d-none');

        modal.show();
    };

    /**
     * Vuelca los datos de un cliente en los campos del formulario.
     * No toca pestañas, mapa ni botones: sirve tanto al abrir la ficha para
     * editar como para refrescarla en sitio después de guardar.
     */
    async function cliPoblarCampos(data) {
        if (!data) return;
        const setV = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };

        setV('cliente_id', data.id);
        setV('cliente_tipo_id', data.tipo_id);
        window.aplicarReglasIdentificacion();
        setV('cliente_identificacion', data.identificacion);
        setV('cliente_nombre', data.nombre);
        setV('cliente_email', data.email);
        setV('cliente_telefono', data.telefono);
        setV('cliente_direccion', data.direccion);
        setV('cliente_plazo', data.plazo || 0);
        // status es entero: con `data.status || '1'` un cliente inactivo (0) se
        // mostraba como Activo y al guardar se reactivaba solo.
        const statusVal = (data.status === 0 || data.status === '0' || data.status === false || data.status === 'false') ? '0' : '1';
        setV('cliente_status', statusVal);

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
        setV('cliente_monto_minimo_auto_cobro', data.monto_minimo_auto_cobro);
        setV('cliente_monto_maximo_auto_cobro', data.monto_maximo_auto_cobro);

        if (data.id_ingreso_concepto_predeterminado) {
            setV('cliente_id_ingreso_concepto', data.id_ingreso_concepto_predeterminado);
            setV('cliente_id_ingreso_concepto_hidden', data.id_ingreso_concepto_predeterminado);
        }

        cliPoblarVisitas(data);

        const selFc = document.getElementById('cliente_id_forma_cobro_predeterminada');
        if (selFc) {
            // Dispara el mostrar/ocultar de operación bancaria, cheque y botón de cobros
            selFc.dispatchEvent(new Event('change'));
        }
    }

    /**
     * Deja el modal listo para seguir trabajando tras guardar, sin cerrarlo:
     * repuebla los campos con lo que quedó en la base, pasa a modo edición si era
     * un alta y actualiza el resumen comercial.
     */
    async function cliRefrescarModalTrasGuardar(json, eraNuevo) {
        if (json.data) {
            await cliPoblarCampos(json.data);
        }

        // El id manda siempre: sin él el siguiente guardado crearía un duplicado.
        const elId = document.getElementById('cliente_id');
        if (elId && json.id) elId.value = json.id;

        const idCli = elId?.value || '';

        if (eraNuevo) {
            // A partir de aquí el formulario edita la ficha recién creada
            const form = document.getElementById('formCliente');
            if (form) form.action = urlBaseClientes + '/update';
            const t = document.getElementById('tituloModalCliente');
            if (t) t.textContent = 'Ficha de Cliente';
            document.getElementById('btnEliminarCliente')?.classList.remove('d-none');
        }

        if (idCli) fetchEstadisticas(idCli);
        cliToggleBotonCobrosPendientes();
    }

    // ─── Abrir modal Editar ──────────────────────────────────────────────────
    window.abrirModalClienteEditar = async function(rowOrData) {
        const modal = getModalCliente();
        if (!modal) return;
        resetFormulario();
        document.getElementById('formCliente').action = urlBaseClientes + '/update';

        const data = (rowOrData instanceof HTMLElement) ? JSON.parse(rowOrData.dataset.cliente) : rowOrData;
        if (!data) return;

        await cargarCatalogos();
        await inicializarReplicarUI();

        const setT = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || ''; };

        await cliPoblarCampos(data);

        const setV = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };

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


    // ─── Cobro automático: bloques informativos y generación retroactiva ─────

    function cliFmtMoney(n) {
        return (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    /** El bloque de cheque solo aplica si la forma de cobro es de banco y se eligió CHEQUE. */
    function cliToggleCheque() {
        const wrp    = document.getElementById('wrapper_tipo_operacion_bancaria');
        const selOp  = document.getElementById('cliente_tipo_operacion_bancaria');
        const divChq = document.getElementById('cliente_div_cheque');
        if (!wrp || !selOp || !divChq) return;

        const esCheque = !wrp.classList.contains('d-none') && selOp.value === 'CHEQUE';
        divChq.classList.toggle('d-none', !esCheque);
        if (esCheque) cliActualizarReglaFechaCheque();
    }

    /** Refleja los días de crédito vigentes en la nota de la fecha de cobro. */
    function cliActualizarReglaFechaCheque() {
        const el = document.getElementById('cliente_cheque_plazo_txt');
        if (!el) return;
        const plazo = parseInt(document.getElementById('cliente_plazo')?.value, 10) || 0;
        el.textContent = plazo > 0 ? ` (actualmente ${plazo} día(s))` : ' (actualmente sin días de crédito)';
    }

    /** El botón retroactivo requiere cliente guardado y forma de cobro configurada. */
    function cliToggleBotonCobrosPendientes() {
        const div = document.getElementById('cliente_div_cobros_pendientes');
        if (!div) return; // El usuario no tiene permiso para crear ingresos
        const idCli = document.getElementById('cliente_id')?.value || '';
        const idFc  = document.getElementById('cliente_id_forma_cobro_predeterminada')?.value || '';
        div.classList.toggle('d-none', !(idCli && idFc));
    }

    /**
     * Genera los ingresos de las facturas y recibos del cliente emitidos hasta
     * hoy que aún tienen saldo. Previsualiza y pide confirmación primero.
     */
    window.cliGenerarCobrosPendientes = async function () {
        const idCli = document.getElementById('cliente_id')?.value || '';
        if (!idCli) return;

        const btn = document.getElementById('cliente_btnGenerarCobros');
        const htmlOriginal = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Revisando...';

        let prev;
        try {
            const resp = await fetch(`${urlBaseClientes}/previsualizarCobrosPendientesAjax?id=${encodeURIComponent(idCli)}`);
            prev = await resp.json();
        } catch (e) {
            prev = { ok: false, error: 'No se pudo comunicar con el servidor.' };
        } finally {
            btn.disabled = false;
            btn.innerHTML = htmlOriginal;
        }

        if (!prev.ok) {
            Swal.fire({ icon: 'warning', title: 'No se puede generar', text: prev.error || 'Revise la configuración de cobros del cliente.' });
            return;
        }

        if (!prev.cantidad) {
            const extra = prev.cantidad_omitidas
                ? ` Hay ${prev.cantidad_omitidas} documento(s) con saldo que quedan fuera del rango de monto configurado.`
                : '';
            Swal.fire({ icon: 'info', title: 'Nada por generar', text: 'Este cliente no tiene documentos con saldo pendiente hasta hoy.' + extra });
            return;
        }

        const filas = (prev.detalle || []).map(d =>
            `<tr><td class="text-start">${d.tipo_documento === 'RECIBO' ? 'Recibo' : 'Factura'}</td><td class="text-start">${d.numero_documento}</td><td class="text-center">${d.fecha_emision}</td><td class="text-end">$${cliFmtMoney(d.monto)}</td></tr>`
        ).join('');
        const resto = prev.cantidad - (prev.detalle || []).length;

        const html = `
            <p class="mb-2">Se generarán <b>${prev.cantidad}</b> cobro(s) por un total de <b>$${cliFmtMoney(prev.total)}</b>.</p>
            <div style="max-height:220px;overflow:auto;">
              <table class="table table-sm table-bordered mb-1" style="font-size:0.78rem;">
                <thead class="table-light"><tr><th>Tipo</th><th>Documento</th><th>Fecha</th><th class="text-end">Monto</th></tr></thead>
                <tbody>${filas}</tbody>
              </table>
            </div>
            ${resto > 0 ? `<div class="small text-muted">…y ${resto} documento(s) más.</div>` : ''}
            ${prev.cantidad_omitidas ? `<div class="small text-warning mt-2"><i class="bi bi-exclamation-triangle me-1"></i>${prev.cantidad_omitidas} documento(s) quedan fuera del rango de monto.</div>` : ''}
            <div class="small text-muted mt-2">Cada cobro usa la forma de cobro configurada en esta pestaña y cobra el saldo real del documento.</div>
        `;

        const confirmacion = await Swal.fire({
            icon: 'question',
            title: '¿Generar los cobros?',
            html: html,
            width: 640,
            showCancelButton: true,
            confirmButtonText: `Sí, generar ${prev.cantidad}`,
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d'
        });
        if (!confirmacion.isConfirmed) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generando...';

        try {
            const fd = new FormData();
            fd.append('id', idCli);
            const resp = await fetch(`${urlBaseClientes}/generarCobrosPendientesAjax`, { method: 'POST', body: fd });
            const json = await resp.json();

            if (!json.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'No se pudieron generar los cobros.' });
                return;
            }

            const fallidos = json.fallidos || [];
            let detalleFallos = '';
            if (fallidos.length) {
                detalleFallos = '<div class="text-start small mt-2"><b>No se pudieron generar:</b><ul class="mb-0 ps-3">'
                    + fallidos.map(f => `<li>${f.numero_documento}: ${f.error}</li>`).join('')
                    + '</ul></div>';
            }

            Swal.fire({
                icon: fallidos.length ? 'warning' : 'success',
                title: `${json.generados} cobro(s) generado(s)`,
                html: `<p class="mb-0">Total: <b>$${cliFmtMoney(json.total)}</b></p>${detalleFallos}`,
                width: fallidos.length ? 640 : undefined
            });

            fetchEstadisticas(idCli);
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error de Red', text: 'No se pudo comunicar con el servidor.' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = htmlOriginal;
        }
    };

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
        // Botón masivo del listado: solo visible si el usuario tiene otras empresas
        // donde crear clientes. Se evalúa ya al cargar la página (no solo al abrir
        // el modal de ficha), para que no dependa de que el usuario lo abra primero.
        const btnMasivo = document.getElementById('btnCopiarClientesEmpresa');
        if (btnMasivo) {
            cargarEmpresasDestino().then(empresas => {
                btnMasivo.classList.toggle('d-none', empresas.length === 0);
            });
        }

        const form = document.getElementById('formCliente');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!validarIdentificacion() || !validarEmails()) return;
                    const btnSave = document.getElementById('btnGuardarCliente');
                    btnSave.disabled = true; 
                    btnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
                    try {
                        const eraNuevo = !(document.getElementById('cliente_id')?.value);
                        const fd = new FormData(form);
                        const resp = await fetch(form.action, { method: 'POST', body: fd });
                        const json = await resp.json();

                        if (json.ok) {
                        btnSave.disabled = false;
                        btnSave.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';

                        if (typeof window.fetchSearch === 'function') window.fetchSearch(window.currentPage || 1);
                        document.dispatchEvent(new CustomEvent('clienteGuardado', { detail: { ...json, nombre: json.data?.nombre || '' } }));

                        // El modal no se cierra: se refresca en sitio para seguir
                        // completando la ficha. Los módulos que crean el cliente al
                        // vuelo ya lo recibieron por el evento 'clienteGuardado'.
                        await cliRefrescarModalTrasGuardar(json, eraNuevo);

                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: json.msg || 'Guardado correctamente.',
                            timer: 2200,
                            showConfirmButton: false
                        });

                        const htmlReplicado = resumenReplicado(json.replicado);
                        if (htmlReplicado) {
                            setTimeout(() => {
                                Swal.fire({ icon: 'info', title: 'Replicación en otras empresas', html: htmlReplicado, confirmButtonText: 'Entendido' });
                            }, 400);
                        }
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

        const selOpBanco = document.getElementById('cliente_tipo_operacion_bancaria');
        if (selOpBanco) selOpBanco.addEventListener('change', cliToggleCheque);

        const inpPlazo = document.getElementById('cliente_plazo');
        if (inpPlazo) inpPlazo.addEventListener('input', cliActualizarReglaFechaCheque);

        // Pestaña Visitas: cualquier cambio recalcula visibilidad de semanas y resumen
        ['cliente_dias_visita_grupo', 'cliente_semanas_visita_grupo'].forEach(idGrupo => {
            document.getElementById(idGrupo)?.addEventListener('change', cliSincronizarVisitas);
        });
        ['cliente_frecuencia_visita', 'cliente_hora_visita_desde', 'cliente_hora_visita_hasta'].forEach(idCampo => {
            document.getElementById(idCampo)?.addEventListener('change', cliSincronizarVisitas);
        });

        const btnLimpiarDias = document.getElementById('cliente_btn_limpiar_dias');
        if (btnLimpiarDias) {
            btnLimpiarDias.addEventListener('click', () => {
                cliMarcarGrupoVisita('dias_visita', []);
                cliMarcarGrupoVisita('semanas_visita', []);
                const selFrec = document.getElementById('cliente_frecuencia_visita');
                if (selFrec) selFrec.value = '';
                cliSincronizarVisitas();
            });
        }

        const replicarToggle = document.getElementById('cliente_replicar_toggle');
        if (replicarToggle) {
            replicarToggle.addEventListener('change', () => {
                const listaWrap = document.getElementById('cliente_replicar_lista_wrap');
                if (listaWrap) listaWrap.classList.toggle('d-none', !replicarToggle.checked);
                if (!replicarToggle.checked && tsReplicarEmpresas) {
                    tsReplicarEmpresas.clear(true);
                }
            });
        }

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
