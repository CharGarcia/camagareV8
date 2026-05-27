/**
 * Modal Firmas Electrónicas
 */
(function (window, document) {
    'use strict';

    const urlBase  = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/modulos/firmas_electronicas';
    const modalEl  = document.getElementById('modalFirma');
    let modalInst  = null;
    let _idFirmaActual  = null;
    let _facturaEstado  = null;
    let _facturaElim    = false;

    function getModal() {
        if (!modalInst && typeof bootstrap !== 'undefined' && modalEl) {
            modalInst = new bootstrap.Modal(modalEl);
        }
        return modalInst;
    }

    // ── Mayúsculas / minúsculas automáticas ───────────────────
    document.getElementById('firma_cod_dactilar')?.addEventListener('input', function () {
        const pos = this.selectionStart;
        this.value = this.value.toUpperCase();
        this.setSelectionRange(pos, pos);
    });
    document.getElementById('firma_correo')?.addEventListener('input', function () {
        const pos = this.selectionStart;
        this.value = this.value.toLowerCase();
        this.setSelectionRange(pos, pos);
    });

    // ── Consulta SRI ───────────────────────────────────────────
    let _sriDebounce = null;

    function mostrarBadgeFirmaSri(msg, cls) {
        const b = document.getElementById('firma_sri_badge');
        if (!b) return;
        b.textContent = msg;
        b.className   = 'badge ' + cls;
        b.classList.remove('d-none');
    }

    function limpiarBadgeFirmaSri() {
        const b  = document.getElementById('firma_sri_badge');
        const sw = document.getElementById('firma_sri_spinner_wrap');
        if (b)  b.className = 'badge d-none';
        if (sw) sw.classList.add('d-none');
    }

    function mostrarSpinnerFirmaSri(visible) {
        const sw = document.getElementById('firma_sri_spinner_wrap');
        if (sw) sw.classList.toggle('d-none', !visible);
    }

    function splitNombreApellido(nombreCompleto) {
        const partes = (nombreCompleto || '').trim().split(/\s+/).filter(Boolean);
        if (partes.length >= 4) return { apellidos: partes.slice(0, 2).join(' '), nombres: partes.slice(2).join(' ') };
        if (partes.length === 3) return { apellidos: partes.slice(0, 2).join(' '), nombres: partes[2] };
        if (partes.length === 2) return { apellidos: partes[0], nombres: partes[1] };
        return { apellidos: partes[0] || '', nombres: '' };
    }

    async function consultarFirmaSri(cedula) {
        mostrarSpinnerFirmaSri(true);
        mostrarBadgeFirmaSri('Consultando…', 'bg-secondary text-white');
        try {
            const fd = new FormData();
            fd.append('identificacion', cedula);
            const resp = await fetch(`${urlBase}/consultarSri`, { method: 'POST', body: fd });
            const json = await resp.json();
            mostrarSpinnerFirmaSri(false);
            if (json.ok && json.data) {
                const { apellidos, nombres } = splitNombreApellido(json.data.nombre || '');
                setVal('firma_apellidos', apellidos);
                setVal('firma_nombres',   nombres);
                mostrarBadgeFirmaSri('✓ Encontrado', 'bg-success bg-opacity-75 text-white');
            } else {
                mostrarBadgeFirmaSri(json.error || 'No encontrado', 'bg-warning text-dark');
            }
        } catch (e) {
            mostrarSpinnerFirmaSri(false);
            mostrarBadgeFirmaSri('Error de consulta', 'bg-danger text-white');
        }
    }

    // ── Tipo de identificación ─────────────────────────────────
    const selectTipoId = document.getElementById('firma_tipo_id');
    const inputNumId   = document.getElementById('firma_num_id');
    const hintNumId    = document.getElementById('firma_num_id_hint');

    function ajustarTipoId(tipo) {
        if (!inputNumId) return;
        if (tipo === 'cedula') {
            inputNumId.pattern     = '\\d{10}';
            inputNumId.maxLength   = 10;
            inputNumId.inputMode   = 'numeric';
            if (hintNumId) hintNumId.textContent = '10 dígitos numéricos';
        } else {
            inputNumId.pattern     = '[a-zA-Z0-9]{1,20}';
            inputNumId.maxLength   = 20;
            inputNumId.inputMode   = 'text';
            if (hintNumId) hintNumId.textContent = 'Letras y números (máx. 20)';
        }
    }

    if (selectTipoId) {
        selectTipoId.addEventListener('change', () => {
            ajustarTipoId(selectTipoId.value);
            limpiarBadgeFirmaSri();
        });
        ajustarTipoId(selectTipoId.value);
    }

    if (inputNumId) {
        inputNumId.addEventListener('input', function () {
            limpiarBadgeFirmaSri();
            clearTimeout(_sriDebounce);
            const tipo  = selectTipoId ? selectTipoId.value : 'cedula';
            const valor = this.value.trim();
            if (tipo === 'cedula' && valor.length === 10) {
                _sriDebounce = setTimeout(() => consultarFirmaSri(valor), 700);
            }
        });
    }

    // ── Tipo de Persona (Natural / Jurídica) ───────────────────
    const selectTipoPersona = document.getElementById('firma_tipo_persona');

    function mostrarCamposJuridica(tipo) {
        const esJuridica    = tipo === 'juridica';
        const bloqueJur     = document.getElementById('bloqueJuridica');
        const bloqueDocsJur = document.getElementById('bloqueDocsJuridica');
        const bloqueConRuc  = document.getElementById('bloqueConRuc');
        if (bloqueJur)     bloqueJur.style.display     = esJuridica ? '' : 'none';
        if (bloqueDocsJur) bloqueDocsJur.style.display = esJuridica ? '' : 'none';
        if (bloqueConRuc)  bloqueConRuc.style.display  = esJuridica ? 'none' : '';
        if (esJuridica) {
            // Al cambiar a Jurídica limpiar y desmarcar Con RUC
            const conRucEl = document.getElementById('firma_con_ruc');
            if (conRucEl) conRucEl.checked = false;
        } else {
            setVal('firma_ruc_empresa',   '');
            setVal('firma_nombre_empresa','');
            setVal('firma_cargo',         '');
        }
    }

    if (selectTipoPersona) {
        selectTipoPersona.addEventListener('change', () => mostrarCamposJuridica(selectTipoPersona.value));
    }

    // ── Validez de Firma (producto) ────────────────────────────
    const selectProducto = document.getElementById('firma_id_producto');
    const pvpBadge       = document.getElementById('firma_pvp_badge');

    function actualizarPvpBadge() {
        if (!selectProducto || !pvpBadge) return;
        const opt    = selectProducto.options[selectProducto.selectedIndex];
        const pvp    = opt ? parseFloat(opt.dataset.pvp || '0') : 0;
        const nombre = opt ? (opt.dataset.nombre || '') : '';
        const hidden = document.getElementById('firma_nombre_producto_modal');
        if (hidden) hidden.value = nombre;
        if (pvp > 0) {
            pvpBadge.textContent = '$' + pvp.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            pvpBadge.classList.remove('d-none');
        } else {
            pvpBadge.classList.add('d-none');
        }
        actualizarFactItem(nombre, pvp);
        actualizarBtnGenerarFactura();
    }

    if (selectProducto) {
        selectProducto.addEventListener('change', actualizarPvpBadge);
    }

    // ── Pestaña Facturación ────────────────────────────────────
    const chkMismosDatos = document.getElementById('fact_mismos_datos');

    const _setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || '—'; };

    function actualizarFactItem(nombre, pvp) {
        const tbody = document.getElementById('factDetalleProductos');
        if (!tbody) return;
        if (!nombre) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-muted fst-italic text-center">— Seleccione una Validez de Firma —</td></tr>';
        } else {
            const pvpFmt = pvp > 0
                ? '$' + parseFloat(pvp).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                : '—';
            tbody.innerHTML = `<tr><td>${nombre}</td><td class="text-end fw-bold">${pvpFmt}</td></tr>`;
        }
    }

    function actualizarResumenFactura() {
        const mismos = chkMismosDatos?.checked ?? true;
        let tipoId = '', numId = '', nombre = '', correo = '', telefono = '', direccion = '';
        if (mismos) {
            const ap  = document.getElementById('firma_apellidos')?.value || '';
            const nom = document.getElementById('firma_nombres')?.value || '';
            nombre    = (ap + ' ' + nom).trim();
            tipoId    = document.getElementById('firma_tipo_id')?.value || '';
            numId     = document.getElementById('firma_num_id')?.value || '';
            correo    = document.getElementById('firma_correo')?.value || '';
            telefono  = document.getElementById('firma_telefono')?.value || '';
            direccion = document.getElementById('firma_direccion')?.value || '';
        } else {
            nombre    = document.getElementById('fact_nombres')?.value || '';
            tipoId    = document.getElementById('fact_tipo_id')?.value || '';
            numId     = document.getElementById('fact_num_id')?.value || '';
            correo    = document.getElementById('fact_correo')?.value || '';
            telefono  = document.getElementById('fact_telefono')?.value || '';
            direccion = document.getElementById('fact_direccion')?.value || '';
        }
        const tipoLabel = { cedula: 'Cédula', ruc: 'RUC', pasaporte: 'Pasaporte' }[tipoId] || tipoId;
        _setTxt('factNombre',    nombre);
        _setTxt('factCedula',    tipoLabel && numId ? `${tipoLabel}: ${numId}` : numId);
        _setTxt('factCorreo',    correo);
        _setTxt('factTelefono',  telefono);
        _setTxt('factDireccion', direccion);
    }

    function actualizarBtnGenerarFactura() {
        const btn = document.getElementById('btnGenerarFactura');
        if (!btn) return;
        const idFirma = document.getElementById('firma_id_modal')?.value;
        const opt     = selectProducto?.options[selectProducto.selectedIndex];
        const tieneProducto = opt && opt.value !== '';
        const puedeFacturar = !_facturaEstado || _facturaElim || _facturaEstado === 'anulada';
        btn.disabled = !(idFirma && tieneProducto && puedeFacturar);
        btn.title = (_facturaEstado && !_facturaElim && _facturaEstado !== 'anulada')
            ? 'Ya existe una factura activa (estado: ' + _facturaEstado + ')'
            : '';
    }

    function ucFirst(str) { return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''; }

    function esFirmaBloqueada() {
        return !_facturaElim && (_facturaEstado === 'borrador' || _facturaEstado === 'autorizada');
    }

    function aplicarBloqueoFirma(bloqueada) {
        // Alerta visible
        const alerta = document.getElementById('alertaBloqueoFirma');
        if (alerta) alerta.classList.toggle('d-none', !bloqueada);

        // Deshabilitar campos de Datos y Facturación
        ['#tab-firma-general', '#tab-firma-pago', '#tab-firma-fact'].forEach(sel => {
            document.querySelector(sel)?.querySelectorAll('input, select, textarea').forEach(el => {
                el.disabled = bloqueada;
            });
        });

        // Botón guardar
        const btnG = document.getElementById('btnGuardarFirmaModal');
        if (btnG) {
            btnG.disabled = bloqueada;
            btnG.title    = bloqueada ? 'Existe una factura activa. Elimínela o anúlela primero.' : '';
        }

        // Botón eliminar
        const btnE = document.getElementById('btnEliminarFirmaModal');
        if (btnE) {
            btnE.disabled = bloqueada;
            btnE.title    = bloqueada ? 'No se puede eliminar: existe una factura activa.' : '';
            btnE.classList.toggle('disabled', bloqueada);
        }
    }

    function mostrarInfoFactura(factData) {
        const headerRow = document.getElementById('factHeaderRow');
        const badge     = document.getElementById('facturaEstadoBadge');
        const numEl     = document.getElementById('factNumero');
        const fechaEl   = document.getElementById('factFecha');
        const linkWrap  = document.getElementById('factLinkWrap');
        const link      = document.getElementById('facturaLink');

        const tieneFactura = !!(factData && factData.factura_estado && factData.factura_eliminada !== true);

        if (headerRow) headerRow.style.display = tieneFactura ? '' : 'none';
        if (linkWrap)  linkWrap.classList.toggle('d-none', !tieneFactura);
        if (!tieneFactura) {
            if (badge)  badge.classList.add('d-none');
            if (numEl)  numEl.textContent  = '';
            if (fechaEl)fechaEl.textContent = '';
            actualizarResumenFactura();
            return;
        }

        // Número de factura
        const nro = [factData.factura_establecimiento, factData.factura_punto_emision, factData.factura_secuencial].filter(Boolean).join('-');
        if (numEl)  numEl.textContent  = nro || '';

        // Fecha
        const fecha = factData.factura_fecha_emision
            ? factData.factura_fecha_emision.toString().slice(0, 10).split('-').reverse().join('-')
            : '';
        if (fechaEl) fechaEl.textContent = fecha;

        // Badge de estado
        const estado = factData.factura_estado || '';
        const estadoMap = {
            'borrador':   ['bg-secondary bg-opacity-10 text-secondary border-secondary', 'Borrador'],
            'autorizada': ['bg-success bg-opacity-10 text-success border-success',        'Autorizada'],
            'anulada':    ['bg-danger bg-opacity-10 text-danger border-danger',           'Anulada'],
        };
        const [cls, lbl] = estadoMap[estado] || ['bg-secondary bg-opacity-10 text-secondary border-secondary', ucFirst(estado)];
        if (badge) {
            badge.className   = `badge ${cls} border border-opacity-25`;
            badge.textContent = lbl;
            badge.classList.remove('d-none');
        }

        // Datos del cliente
        if (factData.facturacion_mismos_datos !== undefined) {
            const factMismos = factData.facturacion_mismos_datos !== false && factData.facturacion_mismos_datos !== 'false';
            const tipoId   = factMismos ? (factData.tipo_identificacion || '') : (factData.facturacion_tipo_id || '');
            const numId    = factMismos ? (factData.numero_identificacion || '') : (factData.facturacion_num_id || '');
            const nombre   = factMismos
                ? ((factData.apellidos || '') + ' ' + (factData.nombres || '')).trim()
                : (factData.facturacion_nombres || '');
            const correo   = factMismos ? (factData.correo || '') : (factData.facturacion_correo || '');
            const telefono = factMismos ? (factData.telefono || '') : (factData.facturacion_telefono || '');
            const direccion= factMismos ? (factData.direccion || '') : (factData.facturacion_direccion || '');
            const tipoLabel= { cedula: 'Cédula', ruc: 'RUC', pasaporte: 'Pasaporte' }[tipoId] || tipoId;
            _setTxt('factNombre',    nombre);
            _setTxt('factCedula',    tipoLabel && numId ? `${tipoLabel}: ${numId}` : numId);
            _setTxt('factCorreo',    correo);
            _setTxt('factTelefono',  telefono);
            _setTxt('factDireccion', direccion);
        } else {
            actualizarResumenFactura();
        }

        // Producto en la tabla (usa el select o nombre_producto del registro)
        const opt    = selectProducto?.options[selectProducto.selectedIndex];
        const nomP   = opt?.dataset.nombre || factData.nombre_producto || '';
        const pvpVal = opt?.dataset.pvp != null && opt.value !== ''
            ? parseFloat(opt.dataset.pvp)
            : (factData.factura_importe_total != null ? parseFloat(factData.factura_importe_total) : 0);
        actualizarFactItem(nomP, pvpVal);

        if (link) link.href = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/modulos/factura-venta';
    }

    function mostrarBloqueFacturacion(mismosDatos) {
        const bloqueCampos = document.getElementById('bloqueFactCampos');
        if (bloqueCampos) bloqueCampos.style.display = mismosDatos ? 'none' : '';
        actualizarResumenFactura();
    }

    if (chkMismosDatos) {
        chkMismosDatos.addEventListener('change', () => mostrarBloqueFacturacion(chkMismosDatos.checked));
    }

    // ── SRI Facturación ────────────────────────────────────────
    let _factSriDebounce = null;
    const selectFactTipoId = document.getElementById('fact_tipo_id');
    const inputFactNumId   = document.getElementById('fact_num_id');

    function mostrarBadgeFactSri(msg, cls) {
        const b = document.getElementById('fact_sri_badge');
        if (!b) return;
        b.textContent = msg;
        b.className   = 'badge ' + cls;
        b.classList.remove('d-none');
    }

    function limpiarBadgeFactSri() {
        const b  = document.getElementById('fact_sri_badge');
        const sw = document.getElementById('fact_sri_spinner_wrap');
        if (b)  b.className = 'badge d-none';
        if (sw) sw.classList.add('d-none');
    }

    function mostrarSpinnerFactSri(visible) {
        const sw = document.getElementById('fact_sri_spinner_wrap');
        if (sw) sw.classList.toggle('d-none', !visible);
    }

    function ajustarFactTipoId(tipo) {
        if (!inputFactNumId) return;
        if (tipo === 'cedula') {
            inputFactNumId.maxLength  = 10;
            inputFactNumId.inputMode  = 'numeric';
        } else if (tipo === 'ruc') {
            inputFactNumId.maxLength  = 13;
            inputFactNumId.inputMode  = 'numeric';
        } else {
            inputFactNumId.maxLength  = 20;
            inputFactNumId.inputMode  = 'text';
        }
    }

    async function consultarFactSri(id) {
        mostrarSpinnerFactSri(true);
        mostrarBadgeFactSri('Consultando…', 'bg-secondary text-white');
        try {
            const fd = new FormData();
            fd.append('identificacion', id);
            const resp = await fetch(`${urlBase}/consultarSri`, { method: 'POST', body: fd });
            const json = await resp.json();
            mostrarSpinnerFactSri(false);
            if (json.ok && json.data) {
                const nombre = json.data.nombre || '';
                const tipo   = selectFactTipoId?.value || 'cedula';
                if (tipo === 'cedula') {
                    // Cédula: nombre viene como "AP1 AP2 NOM1 NOM2" — unir todo
                    const { apellidos, nombres } = splitNombreApellido(nombre);
                    setVal('fact_nombres', (apellidos + ' ' + nombres).trim());
                } else {
                    // RUC: devuelve razón social directamente
                    setVal('fact_nombres', nombre);
                }
                mostrarBadgeFactSri('✓ Encontrado', 'bg-success bg-opacity-75 text-white');
            } else {
                mostrarBadgeFactSri(json.error || 'No encontrado', 'bg-warning text-dark');
            }
        } catch (e) {
            mostrarSpinnerFactSri(false);
            mostrarBadgeFactSri('Error de consulta', 'bg-danger text-white');
        }
    }

    if (selectFactTipoId) {
        selectFactTipoId.addEventListener('change', () => {
            ajustarFactTipoId(selectFactTipoId.value);
            limpiarBadgeFactSri();
        });
        ajustarFactTipoId(selectFactTipoId.value);
    }

    if (inputFactNumId) {
        inputFactNumId.addEventListener('input', function () {
            limpiarBadgeFactSri();
            clearTimeout(_factSriDebounce);
            const tipo  = selectFactTipoId?.value || 'cedula';
            const valor = this.value.trim();
            const longitud = tipo === 'ruc' ? 13 : (tipo === 'cedula' ? 10 : 0);
            if (longitud > 0 && valor.length === longitud) {
                _factSriDebounce = setTimeout(() => consultarFactSri(valor), 700);
            }
        });
    }

    // Actualizar tarjeta cuando cambian datos del titular (mismos datos = true)
    ['firma_tipo_id', 'firma_num_id', 'firma_nombres', 'firma_apellidos', 'firma_correo', 'firma_telefono', 'firma_direccion'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => {
            if (chkMismosDatos?.checked) actualizarResumenFactura();
        });
    });
    ['firma_tipo_id'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => {
            if (chkMismosDatos?.checked) actualizarResumenFactura();
        });
    });

    // Actualizar tarjeta cuando cambian los campos personalizados de facturación
    ['fact_tipo_id', 'fact_num_id', 'fact_nombres', 'fact_correo', 'fact_telefono', 'fact_direccion'].forEach(id => {
        const el = document.getElementById(id);
        el?.addEventListener('input',  () => { if (!chkMismosDatos?.checked) actualizarResumenFactura(); });
        el?.addEventListener('change', () => { if (!chkMismosDatos?.checked) actualizarResumenFactura(); });
    });

    window.generarFacturaDesdeFirma = async function () {
        const idFirma = document.getElementById('firma_id_modal')?.value;
        if (!idFirma) { mostrarAlerta('Guarda la firma antes de generar la factura.', 'warning'); return; }

        const fechaCadVal = document.getElementById('firma_fecha_cad')?.value.trim();
        if (!fechaCadVal || !fechaCadVal.match(/^\d{2}-\d{2}-\d{4}$/)) {
            mostrarAlerta('La fecha de caducidad de la firma es obligatoria antes de generar la factura.', 'warning');
            document.getElementById('firma_fecha_cad')?.focus();
            return;
        }

        const btn     = document.getElementById('btnGenerarFactura');
        const spinner = document.getElementById('factSpinner');
        if (btn)     { btn.disabled = true; }
        if (spinner) spinner.classList.remove('d-none');

        try {
            const fd = new FormData();
            fd.append('id_firma', idFirma);
            const resp = await fetch(`${urlBase}/generarFacturaDesdeFirma`, { method: 'POST', body: fd });
            const json = await resp.json();

            if (json.ok) {
                _facturaEstado = json.factura_estado || 'borrador';
                _facturaElim   = false;
                mostrarInfoFactura(json);
                actualizarBtnGenerarFactura();
                aplicarBloqueoFirma(true);
                mostrarAlerta('Factura generada correctamente. La ficha queda bloqueada hasta que la factura sea eliminada o anulada.', 'success');
                setTimeout(() => { if (window.fetchSearchFirmas) window.fetchSearchFirmas(); }, 1500);
            } else {
                mostrarAlerta(json.error || 'Error al generar la factura.', 'danger');
                if (btn) btn.disabled = false;
            }
        } catch (e) {
            mostrarAlerta('Error de conexión.', 'danger');
            if (btn) btn.disabled = false;
        } finally {
            if (spinner) spinner.classList.add('d-none');
        }
    };

    // ── Provincia → Ciudad ─────────────────────────────────────
    const selectProv   = document.getElementById('firma_cod_prov');
    const selectCiudad = document.getElementById('firma_cod_ciudad');

    async function cargarCiudades(codProv, codCiudadSeleccionada = '') {
        if (!selectCiudad) return;
        selectCiudad.innerHTML = '<option value="">Cargando…</option>';
        if (!codProv) {
            selectCiudad.innerHTML = '<option value="">— Seleccionar ciudad —</option>';
            return;
        }
        try {
            const resp = await fetch(`${urlBase}/ciudadesPorProvincia?cod_prov=${encodeURIComponent(codProv)}`);
            const data = await resp.json();
            selectCiudad.innerHTML = '<option value="">— Seleccionar ciudad —</option>';
            if (data.ok && data.data.length > 0) {
                data.data.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.codigo;
                    opt.textContent = c.nombre;
                    if (c.codigo === codCiudadSeleccionada) opt.selected = true;
                    selectCiudad.appendChild(opt);
                });
            }
        } catch (e) {
            selectCiudad.innerHTML = '<option value="">— Error al cargar —</option>';
        }
    }

    if (selectProv) {
        selectProv.addEventListener('change', () => cargarCiudades(selectProv.value));
    }

    // ── Tipo de pago ───────────────────────────────────────────
    const selectTipoPago      = document.getElementById('firma_tipo_pago');
    const bloqueTransferencia = document.getElementById('bloquePagoTransferencia');
    const bloqueTarjeta       = document.getElementById('bloquePagoTarjeta');

    function mostrarBloquePago(tipo) {
        if (bloqueTransferencia) bloqueTransferencia.style.display = tipo === 'transferencia' ? '' : 'none';
        if (bloqueTarjeta)       bloqueTarjeta.style.display       = tipo === 'tarjeta'       ? '' : 'none';
    }

    if (selectTipoPago) {
        selectTipoPago.addEventListener('change', () => mostrarBloquePago(selectTipoPago.value));
    }

    // ── Fechas dd-mm-yyyy (autoformato) ───────────────────────
    function bindFechaInput(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', function () {
            let v = this.value.replace(/[^\d]/g, '');
            if (v.length >= 3) v = v.slice(0, 2) + '-' + v.slice(2);
            if (v.length >= 6) v = v.slice(0, 5) + '-' + v.slice(5, 9);
            this.value = v;
        });
    }
    bindFechaInput('firma_fecha_nac');
    bindFechaInput('firma_fecha_cad');

    // Badge de estado de caducidad
    const inputFechaCad = document.getElementById('firma_fecha_cad');
    const cadBadge      = document.getElementById('firma_cad_badge');

    function actualizarCadBadge() {
        if (!inputFechaCad || !cadBadge) return;
        const val = inputFechaCad.value.trim();
        if (!val.match(/^\d{2}-\d{2}-\d{4}$/)) { cadBadge.className = 'd-none'; return; }
        const [d, m, y] = val.split('-');
        const ts   = new Date(+y, +m - 1, +d).getTime();
        const hoy  = Date.now();
        const diff = Math.floor((ts - hoy) / 86400000);

        cadBadge.classList.remove('d-none');
        if (diff < 0) {
            cadBadge.className = 'badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25';
            cadBadge.textContent = 'Vencida';
        } else if (diff <= 30) {
            cadBadge.className = 'badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25';
            cadBadge.textContent = 'Vence en ' + diff + ' día' + (diff === 1 ? '' : 's');
        } else {
            cadBadge.className = 'badge bg-success bg-opacity-10 text-success border border-success border-opacity-25';
            cadBadge.textContent = 'Vigente';
        }
    }

    if (inputFechaCad) inputFechaCad.addEventListener('input', actualizarCadBadge);

    // ── Abrir modal crear ──────────────────────────────────────
    window.abrirModalFirmaCrear = function () {
        _idFirmaActual = null;
        const form = document.getElementById('formFirmaModal');
        if (!form) return;
        form.reset();

        document.getElementById('firma_id_modal').value = '';
        document.getElementById('tituloModalFirma').textContent = 'Nueva Firma Electrónica';
        ocultarAlerta();
        document.getElementById('btnEliminarFirmaModal')?.classList.add('d-none');

        const tabInfoBtn = document.getElementById('tab-firma-info-btn');
        if (tabInfoBtn) tabInfoBtn.classList.add('disabled');

        if (pvpBadge) pvpBadge.classList.add('d-none');
        if (cadBadge) cadBadge.className = 'd-none';
        limpiarBadgeFirmaSri();

        const conRucEl = document.getElementById('firma_con_ruc');
        if (conRucEl) conRucEl.checked = false;

        mostrarCamposJuridica('natural');
        desactivarSubidaArchivos();
        mostrarBloquePago('');
        ajustarTipoId('cedula');
        if (selectCiudad) selectCiudad.innerHTML = '<option value="">— Seleccionar ciudad —</option>';
        limpiarPreviews();

        // Facturación: resetear tab
        _facturaEstado = null;
        _facturaElim   = false;
        if (chkMismosDatos) chkMismosDatos.checked = true;
        mostrarBloqueFacturacion(true);
        actualizarFactItem('', 0);
        limpiarBadgeFactSri();
        ajustarFactTipoId('cedula');
        mostrarInfoFactura(null);
        actualizarResumenFactura();
        const btnFact = document.getElementById('btnGenerarFactura');
        if (btnFact) btnFact.disabled = true;

        // Pestaña facturación: deshabilitar hasta guardar
        document.getElementById('tab-firma-fact-btn')?.classList.add('disabled');

        aplicarBloqueoFirma(false);
        activarTab('tab-firma-general-btn');
        getModal()?.show();
        setTimeout(() => document.getElementById('firma_nombres')?.focus(), 500);
    };

    // ── Abrir modal editar ─────────────────────────────────────
    window.abrirModalFirmaEditar = function (rowOrData) {
        let data;
        if (rowOrData instanceof HTMLElement) {
            data = rowOrData.dataset.row ? JSON.parse(rowOrData.dataset.row) : null;
        } else {
            data = rowOrData;
        }
        if (!data) return;
        _idFirmaActual = data.id;

        const form = document.getElementById('formFirmaModal');
        if (!form) return;
        form.reset();
        ocultarAlerta();
        limpiarBadgeFirmaSri();

        document.getElementById('firma_id_modal').value              = data.id;
        document.getElementById('firma_nombre_producto_modal').value = data.nombre_producto || '';
        document.getElementById('tituloModalFirma').textContent       = 'Editar Firma Electrónica';

        setVal('firma_tipo_persona',   data.tipo_persona || 'natural');
        setVal('firma_id_producto',    data.id_producto || '');
        setVal('firma_tipo_id',        data.tipo_identificacion || 'cedula');
        setVal('firma_num_id',         data.numero_identificacion || '');
        setVal('firma_cod_dactilar',   data.codigo_dactilar || '');
        setVal('firma_nombres',        data.nombres || '');
        setVal('firma_apellidos',      data.apellidos || '');
        setVal('firma_sexo',           data.sexo || '');
        setVal('firma_nacionalidad',   data.nacionalidad || '');
        setVal('firma_telefono',       data.telefono || '');
        setVal('firma_correo',         data.correo || '');
        setVal('firma_direccion',      data.direccion || '');
        setVal('firma_estado',         data.estado || 'pendiente');
        setVal('firma_estado_pago',    data.estado_pago || 'pendiente');
        setVal('firma_tipo_pago',      data.tipo_pago || '');
        setVal('firma_observaciones',  data.observaciones || '');

        // Con RUC checkbox
        const conRucEl = document.getElementById('firma_con_ruc');
        if (conRucEl) conRucEl.checked = data.con_ruc === true;

        // Campos Jurídica
        setVal('firma_ruc_empresa',    data.ruc_empresa || '');
        setVal('firma_nombre_empresa', data.nombre_empresa || '');
        setVal('firma_cargo',          data.cargo || '');

        // Mostrar / ocultar bloque Jurídica
        mostrarCamposJuridica(data.tipo_persona || 'natural');

        // Fechas: la BD guarda yyyy-mm-dd, mostrar dd-mm-yyyy
        function bdADisplay(val) {
            if (!val) return '';
            const p = val.split('-');
            return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : val;
        }
        setVal('firma_fecha_nac', bdADisplay(data.fecha_nacimiento));
        setVal('firma_fecha_cad', bdADisplay(data.fecha_caducidad));

        ajustarTipoId(data.tipo_identificacion || 'cedula');
        actualizarCadBadge();
        actualizarPvpBadge();
        mostrarBloquePago(data.tipo_pago || '');

        // Provincia y ciudad
        if (data.cod_prov) {
            setVal('firma_cod_prov', data.cod_prov);
            cargarCiudades(data.cod_prov, data.cod_ciudad || '');
        } else {
            if (selectCiudad) selectCiudad.innerHTML = '<option value="">— Seleccionar ciudad —</option>';
        }

        // Campos facturación
        const factMismos = data.facturacion_mismos_datos !== false && data.facturacion_mismos_datos !== 'false';
        if (chkMismosDatos) chkMismosDatos.checked = factMismos;
        mostrarBloqueFacturacion(factMismos);
        limpiarBadgeFactSri();
        setVal('fact_tipo_id',   data.facturacion_tipo_id   || 'cedula');
        ajustarFactTipoId(data.facturacion_tipo_id || 'cedula');
        setVal('fact_num_id',    data.facturacion_num_id    || '');
        setVal('fact_nombres',   data.facturacion_nombres   || '');
        setVal('fact_telefono',  data.facturacion_telefono  || '');
        setVal('fact_correo',    data.facturacion_correo    || '');
        setVal('fact_direccion', data.facturacion_direccion || '');

        // Estado de factura
        _facturaEstado = data.factura_estado || null;
        _facturaElim   = data.factura_eliminada === true;
        mostrarInfoFactura(data);

        const opt = selectProducto?.options[selectProducto.selectedIndex];
        actualizarFactItem(opt?.dataset.nombre || data.nombre_producto || '', parseFloat(opt?.dataset.pvp || '0'));

        document.getElementById('btnEliminarFirmaModal')?.classList.remove('d-none');

        const tabInfoBtn = document.getElementById('tab-firma-info-btn');
        if (tabInfoBtn) tabInfoBtn.classList.remove('disabled');
        document.getElementById('tab-firma-fact-btn')?.classList.remove('disabled');

        aplicarBloqueoFirma(esFirmaBloqueada());

        activarSubidaArchivos(data.id);
        fetchDetalleFirma(data.id);
        fetchHistorialFirma(data.id);
        actualizarBtnGenerarFactura();
        activarTab('tab-firma-general-btn');
        getModal()?.show();
    };

    // ── Guardar ────────────────────────────────────────────────
    window.guardarFirmaModal = async function () {
        const form  = document.getElementById('formFirmaModal');
        const id    = document.getElementById('firma_id_modal')?.value;

        if (id && esFirmaBloqueada()) {
            mostrarAlerta('No se puede modificar. Primero elimine o anule la factura activa.', 'warning');
            return;
        }

        const fechaCadVal = document.getElementById('firma_fecha_cad')?.value.trim();
        if (!fechaCadVal || !fechaCadVal.match(/^\d{2}-\d{2}-\d{4}$/)) {
            mostrarAlerta('La fecha de caducidad de la firma es obligatoria.', 'warning');
            document.getElementById('firma_fecha_cad')?.focus();
            return;
        }

        const url   = id ? `${urlBase}/update` : `${urlBase}/store`;
        const btn   = document.getElementById('btnGuardarFirmaModal');

        if (!form || !btn) return;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

        try {
            const fd   = new FormData(form);
            const resp = await fetch(url, { method: 'POST', body: fd });
            const json = await resp.json();

            mostrarAlerta(json.msg || json.error, json.ok ? 'success' : 'danger');

            if (json.ok) {
                if (!id && json.id) {
                    _idFirmaActual = json.id;
                    document.getElementById('firma_id_modal').value = json.id;
                    activarSubidaArchivos(json.id);
                    document.getElementById('tab-firma-info-btn')?.classList.remove('disabled');
                    document.getElementById('tab-firma-fact-btn')?.classList.remove('disabled');
                    document.getElementById('btnEliminarFirmaModal')?.classList.remove('d-none');
                    document.getElementById('tituloModalFirma').textContent = 'Editar Firma Electrónica';
                    actualizarBtnGenerarFactura();
                }
                setTimeout(() => {
                    if (window.fetchSearchFirmas) window.fetchSearchFirmas();
                }, 1200);
            }
        } catch (e) {
            mostrarAlerta('Error de conexión.', 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
        }
    };

    // ── Eliminar ───────────────────────────────────────────────
    window.eliminarFirmaModal = async function () {
        const id = document.getElementById('firma_id_modal')?.value;
        if (!id) return;
        if (esFirmaBloqueada()) {
            mostrarAlerta('No se puede eliminar. Primero elimine o anule la factura activa desde Facturas de Venta.', 'warning');
            return;
        }
        if (!confirm('¿Seguro que desea eliminar esta firma electrónica?')) return;
        const btn = document.getElementById('btnEliminarFirmaModal');
        if (btn) btn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id_eliminar', id);
            const resp = await fetch(`${urlBase}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                getModal()?.hide();
                if (window.fetchSearchFirmas) window.fetchSearchFirmas();
            } else {
                mostrarAlerta(json.error || 'Error al eliminar.', 'danger');
            }
        } catch (e) {
            mostrarAlerta('Error de conexión.', 'danger');
        } finally {
            if (btn) btn.disabled = false;
        }
    };

    // ── Detalle e historial ────────────────────────────────────
    async function fetchDetalleFirma(id) {
        try {
            const resp = await fetch(`${urlBase}/getDetalleAjax?id=${id}`);
            const json = await resp.json();
            if (json.ok) {
                renderAdjuntos(json.data.adjuntos || []);
            }
        } catch (e) {}
    }

    async function fetchHistorialFirma(id) {
        const container = document.getElementById('auditoriaTimelineFirma');
        if (!container || !id) return;
        try {
            const resp = await fetch(`${urlBase}/getHistorialAjax?id=${id}&tabla=firmas_electronicas`);
            const json = await resp.json();
            if (json.ok && json.data.length > 0) {
                let html = '<div class="timeline-border position-absolute h-100 border-start border-2 border-primary border-opacity-10" style="left:10px;top:0;"></div>';
                json.data.forEach(log => {
                    const icon = log.accion.includes('Crear') ? 'bi-plus-circle-fill text-success'
                               : log.accion.includes('Actualizar') ? 'bi-pencil-fill text-primary'
                               : log.accion.includes('Eliminar') ? 'bi-trash-fill text-danger'
                               : 'bi-clock-history text-secondary';
                    html += `<div class="timeline-item position-relative mb-3 ps-4">
                        <div class="timeline-icon position-absolute rounded-circle bg-white d-flex align-items-center justify-content-center shadow-sm border" style="left:0;top:0;width:22px;height:22px;z-index:2;">
                            <i class="bi ${icon}" style="font-size:.7rem;"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between align-items-center mb-0">
                                <span class="fw-bold" style="font-size:.75rem;">${log.accion}</span>
                                <span class="text-muted" style="font-size:.65rem;">${log.created_at}</span>
                            </div>
                            <div class="text-muted mb-1" style="font-size:.7rem;"><i class="bi bi-person me-1"></i>${log.usuario_nombre || 'SISTEMA'}</div>
                        </div>
                    </div>`;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="text-center py-4 text-muted small">No hay historial de cambios.</div>';
            }
        } catch (e) {
            container.innerHTML = '<div class="text-center py-3 text-danger small">Error de carga.</div>';
        }
    }

    // ── Adjuntos ───────────────────────────────────────────────
    function renderAdjuntos(adjuntos) {
        const frontal      = adjuntos.filter(a => a.tipo === 'cedula_frontal');
        const posterior    = adjuntos.filter(a => a.tipo === 'cedula_posterior');
        const selfie       = adjuntos.filter(a => a.tipo === 'selfie');
        const comp         = adjuntos.filter(a => a.tipo === 'comprobante_transferencia');
        const rucEmp       = adjuntos.filter(a => a.tipo === 'ruc_empresa');
        const constitucion = adjuntos.filter(a => a.tipo === 'constitucion_compania');
        const nombramiento = adjuntos.filter(a => a.tipo === 'nombramiento');
        const aceptacion   = adjuntos.filter(a => a.tipo === 'aceptacion_nombramiento');

        renderPreviewAdjunto('previewCedulaFrontal',   frontal);
        renderPreviewAdjunto('previewCedulaPosterior', posterior);
        renderPreviewAdjunto('previewSelfie',          selfie);
        renderListaComprobantes(comp);
        renderPreviewAdjunto('previewRucEmpresa',   rucEmp);
        renderPreviewAdjunto('previewConstitucion', constitucion);
        renderPreviewAdjunto('previewNombramiento', nombramiento);
        renderPreviewAdjunto('previewAceptacion',   aceptacion);
    }

    function renderPreviewAdjunto(containerId, adjuntos) {
        const el = document.getElementById(containerId);
        if (!el) return;
        if (adjuntos.length === 0) { el.innerHTML = ''; return; }
        const a = adjuntos[adjuntos.length - 1];
        const esImagen = /\.(jpg|jpeg|png|webp)$/i.test(a.nombre_original || '');
        el.innerHTML = esImagen
            ? `<a href="${a.url_ver}" target="_blank"><img src="${a.url_ver}" class="img-fluid rounded border mb-1" style="max-height:120px;object-fit:cover;" alt="${a.nombre_original}"></a>
               <button type="button" class="btn btn-outline-danger btn-sm d-block" style="font-size:.7rem;padding:1px 6px;" onclick="eliminarAdjunto(${a.id})"><i class="bi bi-trash"></i> Eliminar</button>`
            : `<a href="${a.url_ver}" target="_blank" class="btn btn-outline-secondary btn-sm d-block mb-1"><i class="bi bi-file-earmark-pdf me-1"></i>${a.nombre_original}</a>
               <button type="button" class="btn btn-outline-danger btn-sm d-block" style="font-size:.7rem;padding:1px 6px;" onclick="eliminarAdjunto(${a.id})"><i class="bi bi-trash"></i> Eliminar</button>`;
    }

    function renderListaComprobantes(adjuntos) {
        const el = document.getElementById('listaComprobantes');
        if (!el) return;
        if (adjuntos.length === 0) { el.innerHTML = ''; return; }
        el.innerHTML = adjuntos.map(a =>
            `<div class="d-flex align-items-center gap-2 mb-1">
                <a href="${a.url_ver}" target="_blank" class="btn btn-outline-secondary btn-sm flex-grow-1 text-start" style="font-size:.75rem;"><i class="bi bi-file-earmark me-1"></i>${a.nombre_original}</a>
                <button type="button" class="btn btn-outline-danger btn-sm" style="padding:2px 6px;" onclick="eliminarAdjunto(${a.id})"><i class="bi bi-trash"></i></button>
            </div>`
        ).join('');
    }

    function desactivarSubidaArchivos() {
        const ids = [
            'archivoCedulaFrontal', 'archivoCedulaPosterior', 'archivoSelfie', 'archivoComprobante',
            'archivoRucEmpresa', 'archivoConstitucion', 'archivoNombramiento', 'archivoAceptacion',
        ];
        const btnIds = [
            'btnSubirCedulaFrontal', 'btnSubirCedulaPosterior', 'btnSubirSelfie', 'btnSubirComprobante',
            'btnSubirRucEmpresa', 'btnSubirConstitucion', 'btnSubirNombramiento', 'btnSubirAceptacion',
        ];
        ids.forEach(id => { const el = document.getElementById(id); if (el) el.disabled = true; });
        btnIds.forEach(id => { const el = document.getElementById(id); if (el) el.disabled = true; });
        limpiarPreviews();
    }

    function activarSubidaArchivos(idFirma) {
        const ids = [
            'archivoCedulaFrontal', 'archivoCedulaPosterior', 'archivoSelfie',
            'archivoRucEmpresa', 'archivoConstitucion', 'archivoNombramiento', 'archivoAceptacion',
        ];
        const btnIds = [
            'btnSubirCedulaFrontal', 'btnSubirCedulaPosterior', 'btnSubirSelfie',
            'btnSubirRucEmpresa', 'btnSubirConstitucion', 'btnSubirNombramiento', 'btnSubirAceptacion',
        ];
        ids.forEach(id => { const el = document.getElementById(id); if (el) el.disabled = false; });
        btnIds.forEach(id => { const el = document.getElementById(id); if (el) el.disabled = false; });
        const zonaBtn = document.getElementById('uploadZonaBtnComprobante');
        if (zonaBtn) zonaBtn.classList.remove('d-none');
    }

    function limpiarPreviews() {
        [
            'previewCedulaFrontal', 'previewCedulaPosterior', 'previewSelfie', 'listaComprobantes',
            'previewRucEmpresa', 'previewConstitucion', 'previewNombramiento', 'previewAceptacion',
        ].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = '';
        });
    }

    async function subirAdjunto(inputId, tipo) {
        const input   = document.getElementById(inputId);
        const idFirma = _idFirmaActual || document.getElementById('firma_id_modal')?.value;
        if (!input || !input.files || input.files.length === 0) {
            mostrarAlerta('Selecciona un archivo primero.', 'warning'); return;
        }
        if (!idFirma) {
            mostrarAlerta('Guarda el registro primero antes de adjuntar archivos.', 'warning'); return;
        }
        const fd = new FormData();
        fd.append('archivo', input.files[0]);
        fd.append('id_firma', idFirma);
        fd.append('tipo', tipo);

        const spinner = document.getElementById('spinnerAdjuntos');
        if (spinner) spinner.style.display = '';

        try {
            const resp = await fetch(`${urlBase}/uploadAdjunto`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                mostrarAlerta('Archivo cargado correctamente.', 'success');
                input.value = '';
                fetchDetalleFirma(idFirma);
            } else {
                mostrarAlerta(json.error || 'Error al subir.', 'danger');
            }
        } catch (e) {
            mostrarAlerta('Error de conexión.', 'danger');
        } finally {
            if (spinner) spinner.style.display = 'none';
        }
    }

    window.eliminarAdjunto = async function (id) {
        if (!confirm('¿Eliminar este archivo adjunto?')) return;
        try {
            const fd = new FormData();
            fd.append('id', id);
            const resp = await fetch(`${urlBase}/deleteAdjunto`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                const idFirma = _idFirmaActual || document.getElementById('firma_id_modal')?.value;
                if (idFirma) fetchDetalleFirma(idFirma);
            } else {
                mostrarAlerta(json.error || 'Error al eliminar.', 'danger');
            }
        } catch (e) {}
    };

    // Botones de subida
    document.getElementById('btnSubirCedulaFrontal')?.addEventListener('click',   () => subirAdjunto('archivoCedulaFrontal',   'cedula_frontal'));
    document.getElementById('btnSubirCedulaPosterior')?.addEventListener('click', () => subirAdjunto('archivoCedulaPosterior', 'cedula_posterior'));
    document.getElementById('btnSubirSelfie')?.addEventListener('click',          () => subirAdjunto('archivoSelfie',          'selfie'));
    document.getElementById('btnSubirComprobante')?.addEventListener('click',     () => subirAdjunto('archivoComprobante',     'comprobante_transferencia'));
    document.getElementById('btnSubirRucEmpresa')?.addEventListener('click',      () => subirAdjunto('archivoRucEmpresa',      'ruc_empresa'));
    document.getElementById('btnSubirConstitucion')?.addEventListener('click',    () => subirAdjunto('archivoConstitucion',    'constitucion_compania'));
    document.getElementById('btnSubirNombramiento')?.addEventListener('click',    () => subirAdjunto('archivoNombramiento',    'nombramiento'));
    document.getElementById('btnSubirAceptacion')?.addEventListener('click',      () => subirAdjunto('archivoAceptacion',      'aceptacion_nombramiento'));

    document.getElementById('btnMostrarUploadComprobante')?.addEventListener('click', function () {
        document.getElementById('uploadZonaComprobante')?.classList.remove('d-none');
        this.style.display = 'none';
    });

    // ── Helpers ────────────────────────────────────────────────
    function setVal(id, val) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.tagName === 'SELECT') {
            el.value = val;
            if (el.value !== String(val)) el.value = '';
        } else {
            el.value = val;
        }
    }

    function mostrarAlerta(msg, tipo) {
        const el = document.getElementById('modalAlertFirma');
        if (!el) return;
        el.textContent = msg;
        el.className   = `alert mb-3 py-2 small shadow-sm border-0 alert-${tipo}`;
        el.classList.remove('d-none');
    }

    function ocultarAlerta() {
        const el = document.getElementById('modalAlertFirma');
        if (el) el.classList.add('d-none');
    }

    function activarTab(btnId) {
        const btn = document.getElementById(btnId);
        if (btn && typeof bootstrap !== 'undefined') {
            (bootstrap.Tab.getInstance(btn) || new bootstrap.Tab(btn)).show();
        }
    }

})(window, document);
