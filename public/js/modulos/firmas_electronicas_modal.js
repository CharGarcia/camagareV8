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
    let _firmaEstado    = null;   // estado del trámite tal como está guardado

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
                const partesSri = (apellidos || '').trim().split(/\s+/);
                setVal('firma_apellido_paterno', partesSri.shift() || '');
                setVal('firma_apellido_materno', partesSri.join(' '));
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

    function ajustarTipoId(tipo) {
        if (!inputNumId) return;
        if (tipo === 'cedula') {
            inputNumId.pattern     = '\\d{10}';
            inputNumId.maxLength   = 10;
            inputNumId.inputMode   = 'numeric';
        } else {
            inputNumId.pattern     = '[a-zA-Z0-9]{1,20}';
            inputNumId.maxLength   = 20;
            inputNumId.inputMode   = 'text';
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

    // «Miembro de empresa» ya no se ofrece: solo se muestra en una firma que ya lo tiene,
    // para que al guardarla no cambie sola a Persona Natural.
    function mostrarOpcionMiembro(mostrar) {
        const opt = selectTipoPersona?.querySelector('option[value="miembro"]');
        if (opt) { opt.hidden = !mostrar; opt.disabled = !mostrar; }
    }

    function mostrarCamposJuridica(tipo) {
        // Representante legal y miembro de empresa comparten los datos de la empresa.
        const esJuridica    = tipo === 'juridica' || tipo === 'miembro';
        document.querySelectorAll('#modalFirma .solo-miembro').forEach(el => el.classList.toggle('d-none', tipo !== 'miembro'));
        document.querySelectorAll('#modalFirma .solo-juridica').forEach(el => el.classList.toggle('d-none', tipo !== 'juridica'));
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
            ['firma_departamento', 'firma_motivo', 'firma_rep_identificacion', 'firma_rep_nombres', 'firma_rep_apellidos'].forEach(id => setVal(id, ''));
        }
    }

    // ── Validez de Firma (producto) ────────────────────────────
    const selectProducto = document.getElementById('firma_id_producto');

    /**
     * Solo deja elegir las firmas del tipo de persona elegido (configuración → pestaña
     * Tipo de persona; data-para vacío = sirve para ambos). Si la firma elegida no
     * corresponde, se quita la selección; al abrir una ficha se conserva la que ya tenía
     * (el servidor avisa al guardar si no cuadra).
     */
    function filtrarFirmasPorTipoPersona(tipo, conservarSeleccion = false) {
        if (!selectProducto) return;
        const esEmpresa = tipo === 'juridica' || tipo === 'miembro';
        let quitarSeleccion = false;
        selectProducto.querySelectorAll('option[data-para]').forEach(opt => {
            const para = opt.dataset.para || '';
            const sirve = para === '' || (para === 'juridica') === esEmpresa;
            const visible = sirve || (conservarSeleccion && opt.selected);
            opt.hidden = !visible;
            opt.disabled = !visible;
            if (!sirve && opt.selected && !conservarSeleccion) quitarSeleccion = true;
        });
        if (quitarSeleccion) {
            selectProducto.value = '';
            selectProducto.dispatchEvent(new Event('change'));
        }
    }

    if (selectTipoPersona) {
        selectTipoPersona.addEventListener('change', () => {
            mostrarCamposJuridica(selectTipoPersona.value);
            filtrarFirmasPorTipoPersona(selectTipoPersona.value);
        });
    }
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
        // Con factura activa, la pestaña Facturación sigue mostrando lo facturado.
        if (!esFirmaBloqueada()) actualizarFactItem(nombre, pvp);
        actualizarBtnGenerarFactura();
    }

    if (selectProducto) {
        selectProducto.addEventListener('change', actualizarPvpBadge);
        selectProducto.addEventListener('change', autocompletarCaducidad);
        selectProducto.addEventListener('change', () => {
            if (esFirmaBloqueada()) {
                mostrarAlerta('La factura activa se mantiene con el tipo de firma y el valor anteriores; solo cambia la ficha.', 'warning');
            }
        });
    }

    // ── Caducidad = hoy + vigencia del tipo de firma ───────────
    // Se autocompleta al elegir la firma mientras el usuario no la haya escrito a mano
    // (o la firma ya traiga una). Al emitirla, el servidor la vuelve a contar desde ese día.
    let _cadManual = false;

    function sumarVigencia(cant, unidad) {
        // Igual que FirmaElectronicaRules::sumarVigencia(): meses/años sin desbordar el mes.
        let d = new Date();
        if (unidad === 'dias') {
            d.setDate(d.getDate() + cant);
        } else {
            const total = d.getFullYear() * 12 + d.getMonth() + (unidad === 'meses' ? cant : cant * 12);
            const anio = Math.floor(total / 12), mes = total % 12;
            const ultimo = new Date(anio, mes + 1, 0).getDate();
            d = new Date(anio, mes, Math.min(d.getDate(), ultimo));
        }
        return [String(d.getDate()).padStart(2, '0'), String(d.getMonth() + 1).padStart(2, '0'), d.getFullYear()].join('-');
    }

    function autocompletarCaducidad() {
        const opt  = selectProducto?.options[selectProducto.selectedIndex];
        const cant = parseInt(opt?.dataset.vigCant || '0', 10);
        const unidad = opt?.dataset.vigUnidad || '';
        if (_cadManual || !cant || !unidad) return;
        setVal('firma_fecha_cad', sumarVigencia(cant, unidad));
        actualizarCadBadge();
    }

    document.getElementById('firma_fecha_cad')?.addEventListener('input', () => { _cadManual = true; });

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
            const ap  = [document.getElementById('firma_apellido_paterno')?.value, document.getElementById('firma_apellido_materno')?.value].filter(Boolean).join(' ');
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
        const puedeFacturar = !_facturaEstado || _facturaElim || _facturaEstado === 'anulado';
        btn.disabled = !(idFirma && tieneProducto && puedeFacturar);
        btn.title = (_facturaEstado && !_facturaElim && _facturaEstado !== 'anulado')
            ? 'Ya existe una factura activa (estado: ' + _facturaEstado + ')'
            : '';
    }

    function ucFirst(str) { return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''; }

    /** Tiene factura activa (borrador o autorizada): no se elimina. */
    function esFirmaBloqueada() {
        return !_facturaElim && (_facturaEstado === 'borrador' || _facturaEstado === 'autorizado');
    }

    /** Aprobada por Uanataca o emitida: la solicitud ya no se edita (el servidor también lo impide). */
    const ESTADOS_CERRADOS = ['aprobada', 'emitida'];
    function esFirmaSoloLectura() {
        return ESTADOS_CERRADOS.includes(_firmaEstado);
    }

    // El pago ya facturado no cambia mientras la factura siga activa (el servidor lo conserva igual). El tipo de firma sí, hasta que se emita.
    const CAMPOS_FACTURADOS = ['#firma_estado_pago'];

    function aplicarBloqueoFirma() {
        const conFactura = esFirmaBloqueada();
        const soloLectura = esFirmaSoloLectura();

        // Alerta visible: el texto cambia según lo que queda bloqueado
        const alerta = document.getElementById('alertaBloqueoFirma');
        if (alerta) {
            // Con la factura ya autorizada no se muestra el aviso de "puede corregir"
            // (solo el de solo lectura, si además ya está emitida).
            alerta.classList.toggle('d-none', !soloLectura && !(conFactura && _facturaEstado === 'borrador'));
            const txt = alerta.querySelector('.js-texto-bloqueo');
            if (txt) txt.innerHTML = soloLectura
                ? `Esta solicitud ya está <strong>${_firmaEstado === 'aprobada' ? 'aprobada por Uanataca' : 'emitida'}</strong> y no se puede editar. El avance del trámite se lleva desde la pestaña <strong>Trámite</strong>.`
                : 'Esta firma tiene una <strong>factura activa</strong>: hasta que se emita puede corregir los datos del titular, el tipo de firma y el trámite (la factura queda con el tipo y el valor con que se hizo). El pago y los datos de facturación quedan como se facturaron.';
        }

        // Datos del titular: se bloquean al quedar aprobada o emitida
        document.querySelector('#tab-firma-general')?.querySelectorAll('input, select, textarea').forEach(el => {
            el.disabled = soloLectura || (conFactura && CAMPOS_FACTURADOS.some(s => el.matches(s)));
        });
        // Pago y Facturación: fijos mientras exista la factura activa o la solicitud esté cerrada
        ['#tab-firma-pago', '#tab-firma-fact'].forEach(sel => {
            document.querySelector(sel)?.querySelectorAll('input, select, textarea').forEach(el => {
                el.disabled = conFactura || soloLectura;
            });
        });

        // Botón guardar
        const btnG = document.getElementById('btnGuardarFirmaModal');
        if (btnG) {
            btnG.disabled = soloLectura;
            btnG.title    = soloLectura ? 'La solicitud ya está aprobada o emitida: no se puede editar.' : '';
        }

        // Botón eliminar: no con factura activa ni con la solicitud aprobada/emitida
        const bloqueada = conFactura || soloLectura;
        const btnE = document.getElementById('btnEliminarFirmaModal');
        if (btnE) {
            btnE.disabled = bloqueada;
            btnE.title    = soloLectura ? 'No se puede eliminar: la solicitud ya está aprobada o emitida.'
                          : (conFactura ? 'No se puede eliminar: existe una factura activa.' : '');
            btnE.classList.toggle('disabled', bloqueada);
        }
    }

    // PDF de la factura de la firma: Ver / Imprimir / Descargar con el diálogo estándar (CLAUDE.md §9).
    document.getElementById('btnPdfFacturaFirma')?.addEventListener('click', () => {
        const id = _idFirmaActual || document.getElementById('firma_id_modal')?.value;
        if (!id) return;
        CMG_pdfDocumento(`${urlBase}/pdfFacturaAjax?id=${encodeURIComponent(id)}`);
    });

    function mostrarInfoFactura(factData) {
        const headerRow = document.getElementById('factHeaderRow');
        const badge     = document.getElementById('facturaEstadoBadge');
        const numEl     = document.getElementById('factNumero');
        const fechaEl   = document.getElementById('factFecha');
        const linkWrap  = document.getElementById('factLinkWrap');

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
            'autorizado': ['bg-success bg-opacity-10 text-success border-success',        'Autorizada'],
            'anulado':    ['bg-danger bg-opacity-10 text-danger border-danger',           'Anulada'],
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
    ['firma_tipo_id', 'firma_num_id', 'firma_nombres', 'firma_apellido_paterno', 'firma_apellido_materno', 'firma_correo', 'firma_telefono', 'firma_direccion'].forEach(id => {
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
                aplicarBloqueoFirma();
                mostrarAlerta('Factura generada correctamente. El pago y los datos de facturación quedan fijos; los datos del titular y el tipo de firma se pueden corregir hasta que se emita.', 'success');
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
        if (bloqueTarjeta)       bloqueTarjeta.style.display       = (tipo === 'tarjeta' || tipo === 'payphone') ? '' : 'none';
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
        _cadManual = false;
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
        filtrarFirmasPorTipoPersona('natural');
        desactivarSubidaArchivos();
        mostrarBloquePago('');
        ajustarTipoId('cedula');
        if (selectCiudad) selectCiudad.innerHTML = '<option value="">— Seleccionar ciudad —</option>';
        limpiarPreviews();

        // Facturación: resetear tab
        _facturaEstado = null;
        _firmaEstado   = null;
        mostrarOpcionMiembro(false);
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

        // Pestañas facturación y trámite: deshabilitar hasta guardar
        document.getElementById('tab-firma-fact-btn')?.classList.add('disabled');
        document.getElementById('tab-firma-tramite-btn')?.classList.add('disabled');
        document.getElementById('tab-firma-uanataca-btn')?.classList.add('disabled');
        setVal('firma_estado', 'pendiente');

        aplicarBloqueoFirma();
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

        mostrarOpcionMiembro(data.tipo_persona === 'miembro');
        setVal('firma_tipo_persona',   data.tipo_persona || 'natural');
        setVal('firma_id_producto',    data.id_producto || '');
        setVal('firma_tipo_id',        data.tipo_identificacion || 'cedula');
        setVal('firma_num_id',         data.numero_identificacion || '');
        setVal('firma_cod_dactilar',   data.codigo_dactilar || '');
        setVal('firma_nombres',        data.nombres || '');
        // Registros anteriores solo tienen "apellidos": el primero va a paterno y el resto a materno.
        const partesApe = (data.apellidos || '').trim().split(/\s+/);
        setVal('firma_apellido_paterno', data.apellido_paterno || partesApe.shift() || '');
        setVal('firma_apellido_materno', data.apellido_paterno ? (data.apellido_materno || '') : partesApe.join(' '));
        setVal('firma_departamento',   data.departamento || '');
        setVal('firma_motivo',         data.motivo || '');
        setVal('firma_rep_tipo_id',    data.rep_tipo_identificacion || 'cedula');
        setVal('firma_rep_identificacion', data.rep_identificacion || '');
        setVal('firma_rep_nombres',    data.rep_nombres || '');
        setVal('firma_rep_apellidos',  data.rep_apellidos || '');
        setVal('firma_sexo',           data.sexo || '');
        setVal('firma_nacionalidad',   data.nacionalidad || '');
        setVal('firma_telefono',       data.telefono || '');
        setVal('firma_correo',         data.correo || '');
        setVal('firma_direccion',      data.direccion || '');
        setVal('firma_estado',         data.estado || 'pendiente');
        _firmaEstado = data.estado || 'pendiente';
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
        filtrarFirmasPorTipoPersona(data.tipo_persona || 'natural', true);

        // Fechas: la BD guarda yyyy-mm-dd, mostrar dd-mm-yyyy
        function bdADisplay(val) {
            if (!val) return '';
            const p = val.split('-');
            return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : val;
        }
        setVal('firma_fecha_nac', bdADisplay(data.fecha_nacimiento));
        setVal('firma_fecha_cad', bdADisplay(data.fecha_caducidad));
        _cadManual = !!data.fecha_caducidad;

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
        document.getElementById('tab-firma-tramite-btn')?.classList.remove('disabled');
        document.getElementById('tab-firma-uanataca-btn')?.classList.remove('disabled');

        aplicarBloqueoFirma();
        cargarTramite(data.id);

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

        if (id && esFirmaSoloLectura()) {
            mostrarAlerta('Esta solicitud ya está aprobada o emitida y no se puede editar. El avance del trámite se lleva desde la pestaña Trámite.', 'warning');
            return;
        }

        const inpDireccion = document.getElementById('firma_direccion');
        if (inpDireccion && inpDireccion.value.trim().length < 10) {
            mostrarAlerta('La dirección debe tener al menos 10 caracteres (calle, número, sector).', 'warning');
            activarTab('tab-firma-general-btn');
            inpDireccion.focus();
            return;
        }

        const fechaCadVal = document.getElementById('firma_fecha_cad')?.value.trim();
        if (document.getElementById('firma_estado')?.value === 'emitida' && !/^\d{2}-\d{2}-\d{4}$/.test(fechaCadVal || '')) {
            mostrarAlerta('Para marcar la firma como emitida indique su fecha de vencimiento.', 'warning');
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
                    document.getElementById('tab-firma-tramite-btn')?.classList.remove('disabled');
                    document.getElementById('tab-firma-uanataca-btn')?.classList.remove('disabled');
                    document.getElementById('btnEliminarFirmaModal')?.classList.remove('d-none');
                    cargarTramite(json.id);
                    document.getElementById('tituloModalFirma').textContent = 'Editar Firma Electrónica';
                    actualizarBtnGenerarFactura();
                }
                setTimeout(() => {
                    if (window.fetchSearchFirmas) window.fetchSearchFirmas();
                }, 1200);
                _firmaEstado = document.getElementById('firma_estado')?.value || _firmaEstado;
            }
        } catch (e) {
            mostrarAlerta('Error de conexión.', 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Guardar';
            // Si se acaba de marcar como emitida con factura activa, la ficha pasa a solo lectura.
            aplicarBloqueoFirma();
        }
    };

    // ── Eliminar ───────────────────────────────────────────────
    window.eliminarFirmaModal = async function () {
        const id = document.getElementById('firma_id_modal')?.value;
        if (!id) return;
        if (esFirmaSoloLectura()) {
            mostrarAlerta('No se puede eliminar: la solicitud ya está aprobada o emitida.', 'warning');
            return;
        }
        if (esFirmaBloqueada()) {
            mostrarAlerta('No se puede eliminar. Primero elimine o anule la factura activa desde Facturas de Venta.', 'warning');
            return;
        }
        if (!(await confirmar('Se eliminará esta firma electrónica.', 'Sí, eliminar'))) return;
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
        renderPreviewAdjunto('previewCedulaRepresentante', adjuntos.filter(a => a.tipo === 'cedula_representante'));
        renderPreviewAdjunto('previewAutorizacion',        adjuntos.filter(a => a.tipo === 'autorizacion'));

        renderPreviewAdjunto('previewCedulaFrontal',   frontal);
        renderPreviewAdjunto('previewCedulaPosterior', posterior);
        renderPreviewAdjunto('previewSelfie',          selfie);
        renderListaComprobantes(comp);
        renderPreviewAdjunto('previewRucEmpresa',   rucEmp);
        renderPreviewAdjunto('previewConstitucion', constitucion);
        renderPreviewAdjunto('previewNombramiento', nombramiento);
        renderPreviewAdjunto('previewAceptacion',   aceptacion);
    }

    function escNombre(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    }

    function renderPreviewAdjunto(containerId, adjuntos) {
        const el = document.getElementById(containerId);
        if (!el) return;
        if (adjuntos.length === 0) { el.innerHTML = ''; return; }
        const a = adjuntos[adjuntos.length - 1];
        const esImagen = /\.(jpg|jpeg|png|webp)$/i.test(a.nombre_original || '');
        const nombre = escNombre(a.nombre_original);
        el.innerHTML = esImagen
            ? `<a href="${a.url_ver}" target="_blank"><img src="${a.url_ver}" class="img-fluid rounded border mb-1" style="max-height:120px;object-fit:cover;" alt="${nombre}"></a>
               <button type="button" class="btn btn-outline-danger btn-sm d-block" style="font-size:.7rem;padding:1px 6px;" onclick="eliminarAdjunto(${a.id})"><i class="bi bi-trash"></i> Eliminar</button>`
            : `<a href="${a.url_ver}" target="_blank" class="btn btn-outline-secondary btn-sm d-block mb-1"><i class="bi bi-file-earmark-pdf me-1"></i>${nombre}</a>
               <button type="button" class="btn btn-outline-danger btn-sm d-block" style="font-size:.7rem;padding:1px 6px;" onclick="eliminarAdjunto(${a.id})"><i class="bi bi-trash"></i> Eliminar</button>`;
    }

    function renderListaComprobantes(adjuntos) {
        const el = document.getElementById('listaComprobantes');
        if (!el) return;
        if (adjuntos.length === 0) { el.innerHTML = ''; return; }
        el.innerHTML = adjuntos.map(a =>
            `<div class="d-flex align-items-center gap-2 mb-1">
                <a href="${a.url_ver}" target="_blank" class="btn btn-outline-secondary btn-sm flex-grow-1 text-start" style="font-size:.75rem;"><i class="bi bi-file-earmark me-1"></i>${escNombre(a.nombre_original)}</a>
                <button type="button" class="btn btn-outline-danger btn-sm" style="padding:2px 6px;" onclick="eliminarAdjunto(${a.id})"><i class="bi bi-trash"></i></button>
            </div>`
        ).join('');
    }

    function desactivarSubidaArchivos() {
        const ids = [
            'archivoCedulaFrontal', 'archivoCedulaPosterior', 'archivoSelfie', 'archivoComprobante',
            'archivoRucEmpresa', 'archivoConstitucion', 'archivoNombramiento', 'archivoAceptacion', 'archivoCedulaRepresentante', 'archivoAutorizacion',
        ];
        const btnIds = [
            'btnSubirCedulaFrontal', 'btnSubirCedulaPosterior', 'btnSubirSelfie', 'btnSubirComprobante',
            'btnSubirRucEmpresa', 'btnSubirConstitucion', 'btnSubirNombramiento', 'btnSubirAceptacion', 'btnSubirCedulaRepresentante', 'btnSubirAutorizacion',
        ];
        ids.forEach(id => { const el = document.getElementById(id); if (el) el.disabled = true; });
        btnIds.forEach(id => { const el = document.getElementById(id); if (el) el.disabled = true; });
        limpiarPreviews();
    }

    function activarSubidaArchivos(idFirma) {
        const ids = [
            'archivoCedulaFrontal', 'archivoCedulaPosterior', 'archivoSelfie',
            'archivoRucEmpresa', 'archivoConstitucion', 'archivoNombramiento', 'archivoAceptacion', 'archivoCedulaRepresentante', 'archivoAutorizacion',
        ];
        const btnIds = [
            'btnSubirCedulaFrontal', 'btnSubirCedulaPosterior', 'btnSubirSelfie',
            'btnSubirRucEmpresa', 'btnSubirConstitucion', 'btnSubirNombramiento', 'btnSubirAceptacion', 'btnSubirCedulaRepresentante', 'btnSubirAutorizacion',
        ];
        ids.forEach(id => { const el = document.getElementById(id); if (el) el.disabled = false; });
        btnIds.forEach(id => { const el = document.getElementById(id); if (el) el.disabled = false; });
        const zonaBtn = document.getElementById('uploadZonaBtnComprobante');
        if (zonaBtn) zonaBtn.classList.remove('d-none');
    }

    function limpiarPreviews() {
        [
            'previewCedulaFrontal', 'previewCedulaPosterior', 'previewSelfie', 'listaComprobantes',
            'previewRucEmpresa', 'previewConstitucion', 'previewNombramiento', 'previewAceptacion', 'previewCedulaRepresentante', 'previewAutorizacion',
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
        if (!(await confirmar('Se eliminará este archivo adjunto.', 'Sí, eliminar'))) return;
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
    document.getElementById('btnSubirCedulaRepresentante')?.addEventListener('click', () => subirAdjunto('archivoCedulaRepresentante', 'cedula_representante'));
    document.getElementById('btnSubirAutorizacion')?.addEventListener('click',    () => subirAdjunto('archivoAutorizacion',    'autorizacion'));

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

    // Mensajes con SweetAlert (como en Facturas de Venta). Se dibujan dentro del modal
    // mientras está abierto, para que el foco del modal de Bootstrap no los bloquee.
    function destinoSwal() {
        return modalEl?.classList.contains('show') ? modalEl : 'body';
    }

    const SWAL_TIPOS = {
        success: ['success', '¡Listo!'],
        danger:  ['error',   'Error'],
        warning: ['warning', 'Atención'],
        info:    ['info',    'Información'],
    };

    function mostrarAlerta(msg, tipo) {
        if (!msg) return;
        const [icon, title] = SWAL_TIPOS[tipo] || SWAL_TIPOS.info;
        if (!window.Swal) { alert(msg); return; }
        Swal.fire({ icon, title, text: msg, target: destinoSwal(), confirmButtonText: 'Aceptar' });
    }

    /** Confirmación con SweetAlert; devuelve true si el usuario acepta. */
    async function confirmar(msg, textoBoton = 'Sí, continuar') {
        if (!window.Swal) return confirm(msg);
        const r = await Swal.fire({
            icon: 'question', title: '¿Continuar?', text: msg, target: destinoSwal(),
            showCancelButton: true, confirmButtonText: textoBoton, cancelButtonText: 'Cancelar', reverseButtons: true,
        });
        return r.isConfirmed;
    }

    function ocultarAlerta() {
        const el = document.getElementById('modalAlertFirma');
        if (el) el.classList.add('d-none');
    }

    // ── Trámite: pago, correo, Uanataca e historial ───────────
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    let _tramite = null;

    async function cargarTramite(id) {
        const cont = document.getElementById('tramiteContenido');
        const uana = document.getElementById('uanatacaContenido');
        if (!cont || !id) return;
        cont.innerHTML = uana.innerHTML = '<div class="py-3 text-muted">Cargando…</div>';
        try {
            const json = await (await fetch(`${urlBase}/getTramiteAjax?id=${id}`)).json();
            if (!json.ok) throw new Error(json.error);
            _tramite = json;
            renderTramite(json);
            renderUanataca(json);
            // Si el trámite cambió de estado (consulta a Uanataca, marcar emitida…), la ficha
            // lo refleja y, al quedar aprobada o emitida, pasa a solo lectura.
            if (json.estado && json.estado !== _firmaEstado) {
                _firmaEstado = json.estado;
                setVal('firma_estado', json.estado);
                aplicarBloqueoFirma();
            }
        } catch (e) {
            cont.innerHTML = uana.innerHTML = `<div class="text-danger py-3">${esc(e.message || 'No se pudo cargar el trámite.')}</div>`;
        }
    }
    window.FIRMA_recargarTramite = () => { if (_idFirmaActual) cargarTramite(_idFirmaActual); };

    function paso(ok, actual, titulo, detalle) {
        const icon = ok ? 'bi-check-circle-fill text-success' : (actual ? 'bi-arrow-right-circle-fill text-primary' : 'bi-circle text-secondary');
        return `<div class="d-flex gap-2 mb-2"><i class="bi ${icon} fs-6"></i><div><div class="fw-semibold text-dark">${titulo}</div>${detalle ? `<div class="text-muted">${detalle}</div>` : ''}</div></div>`;
    }

    function renderTramite(t) {
        const puede = !!(window.FIRMAS_PERM && window.FIRMAS_PERM.actualizar);
        const pagoOk = t.estado_pago === 'confirmado';
        const correoOk = !!t.correo_confirmado_at;
        const registrada = ['en_proceso', 'aprobada', 'emitida'].includes(t.estado);
        const formas = (window.FIRMAS_FORMAS_COBRO || []).map(f => `<option value="${f.id}">${esc(f.nombre)}${f.tipo ? ' (' + esc(f.tipo) + ')' : ''}</option>`).join('');
        const comprobante = (t.adjuntos || []).filter(a => a.tipo === 'comprobante_transferencia').pop();
        let acciones = '';

        if (puede && ['pendiente', 'en_revision', 'rechazado'].includes(t.estado_pago) && t.estado !== 'cancelada') {
            acciones += `
            <div class="border rounded-3 p-2 mb-2 ${t.estado_pago === 'en_revision' ? 'border-info bg-info bg-opacity-10' : ''}">
                <div class="fw-semibold mb-1"><i class="bi bi-cash-coin me-1"></i>${t.estado_pago === 'en_revision' ? 'Revisar comprobante de pago' : 'Registrar pago recibido'}</div>
                ${comprobante ? `<div class="mb-1"><a href="${comprobante.url}" target="_blank"><i class="bi bi-receipt me-1"></i>Ver comprobante: ${esc(comprobante.nombre)}</a></div>` : ''}
                <div class="d-flex flex-wrap gap-2 align-items-end">
                    <div><label class="form-label small mb-0">Cuenta / forma de cobro</label>
                        <select class="form-select form-select-sm" id="trFormaCobro" style="width:220px;"><option value="">— Elegir —</option>${formas}</select></div>
                    <div><label class="form-label small mb-0">Referencia</label>
                        <input type="text" class="form-control form-control-sm" id="trReferencia" style="width:160px;" placeholder="N.º de transferencia"></div>
                    <button type="button" class="btn btn-success btn-sm" data-tr="aprobar"><i class="bi bi-check2-circle me-1"></i>Aprobar y facturar</button>
                    ${t.estado_pago === 'en_revision' ? '<button type="button" class="btn btn-outline-danger btn-sm" data-tr="rechazar"><i class="bi bi-x-circle me-1"></i>Rechazar</button>' : ''}
                </div>
                <div class="form-text small">Genera la factura (serie configurada), la envía al SRI y registra el cobro.</div>
            </div>`;
        }
        if (puede && pagoOk && !correoOk) {
            acciones += `<div class="d-flex flex-wrap gap-2 mb-2">
                <button type="button" class="btn btn-outline-primary btn-sm" data-tr="reenviar"><i class="bi bi-envelope-arrow-up me-1"></i>${t.correo_enviado_at ? 'Reenviar' : 'Enviar'} factura y confirmación</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-tr="confirmar_manual"><i class="bi bi-envelope-check me-1"></i>Marcar correo confirmado</button>
            </div>`;
        }
        // API de Uanataca: envío y estado de la solicitud.
        if (t.uanataca_uuid) {
            acciones += `<div class="border rounded-3 p-2 mb-2 border-primary bg-primary bg-opacity-10">
                <div class="fw-semibold mb-1"><i class="bi bi-cloud-check me-1"></i>Solicitud en Uanataca: ${esc(t.uanataca_estado_label || t.uanataca_estado || '')}</div>
                <div class="text-muted">${esc(t.uanataca_uuid)}${t.uanataca_estado_at ? ' · consultado ' + esc(t.uanataca_estado_at) : ''}</div>
                ${t.uanataca_comentarios ? `<details class="mt-1"><summary class="text-muted">Comentarios de Uanataca</summary><div class="small" style="white-space:pre-wrap;">${esc(t.uanataca_comentarios)}</div></details>` : ''}
                ${puede ? '<button type="button" class="btn btn-outline-primary btn-sm mt-2" data-tr="sincronizar_uanataca"><i class="bi bi-arrow-repeat me-1"></i>Consultar estado ahora</button>' : ''}
                ${t.estado === 'por_registrar' ? '<div class="small text-warning-emphasis mt-1">El cliente corrigió su solicitud: actualice los documentos en el portal de distribuidores de Uanataca.</div>' : ''}
            </div>`;
        } else if (puede && t.uanataca_api && t.estado === 'por_registrar') {
            const faltan = t.uanataca_faltantes || [];
            acciones += `<div class="border rounded-3 p-2 mb-2">
                <div class="fw-semibold mb-1"><i class="bi bi-cloud-upload me-1"></i>Enviar a Uanataca por API</div>
                ${faltan.length ? `<div class="alert alert-warning py-1 px-2 mb-2">Falta: ${faltan.map(esc).join(', ')}.</div>` : ''}
                ${!t.correo_confirmado_at ? '<div class="small text-muted mb-1">El cliente aún no confirma su correo.</div>' : ''}
                ${t.uanataca_error ? `<div class="small text-danger mb-1">Último intento: ${esc(t.uanataca_error)}</div>` : ''}
                <button type="button" class="btn btn-primary btn-sm" data-tr="enviar_uanataca" ${faltan.length ? 'disabled' : ''}><i class="bi bi-send me-1"></i>Enviar a Uanataca</button>
                <span class="small text-muted ms-1">Consume Uanacréditos.</span>
            </div>`;
        }
        if (puede && ['por_registrar', 'en_proceso', 'aprobada'].includes(t.estado)) {
            acciones += `<div class="border rounded-3 p-2 mb-2">
                <div class="d-flex flex-wrap gap-2 align-items-end">
                    ${t.estado === 'por_registrar' ? `<div><label class="form-label small mb-0">N.º de solicitud en Uanataca</label>
                        <input type="text" class="form-control form-control-sm" id="trRefUanataca" style="width:180px;"></div>
                        <button type="button" class="btn ${t.uanataca_api ? 'btn-outline-secondary' : 'btn-primary'} btn-sm" data-tr="registrar_uanataca"><i class="bi bi-box-arrow-up-right me-1"></i>Ya la registré en Uanataca${t.uanataca_api ? ' (manual)' : ''}</button>` : ''}
                    <div><label class="form-label small mb-0">Vencimiento de la firma</label>
                        <input type="date" class="form-control form-control-sm" id="trCaducidad" style="width:150px;" value="${esc(t.caducidad_sugerida || '')}"></div>
                    <button type="button" class="btn btn-success btn-sm" data-tr="emitida"><i class="bi bi-patch-check me-1"></i>Marcar emitida</button>
                </div>
            </div>`;
        }
        if (t.estado === 'emitida') {
            const avisos = (t.avisos || []).map(a =>
                `<div class="${a.enviado ? '' : 'text-danger'}"><i class="bi ${a.canal === 'correo' ? 'bi-envelope' : 'bi-whatsapp'} me-1"></i>${esc(a.fecha)} · ${a.manual ? 'manual' : (a.dias === 0 ? 'el día del vencimiento' : a.dias + ' días antes')} · ${esc(a.destino || '')}${a.enviado ? '' : ' · no enviado' + (a.error ? ': ' + esc(a.error) : '')}</div>`).join('');
            acciones += `<div class="border rounded-3 p-2 mb-2">
                <div class="fw-semibold mb-1"><i class="bi bi-bell me-1"></i>Vencimiento: ${esc(t.fecha_caducidad || 'sin fecha')}</div>
                ${avisos ? `<div class="small text-muted mb-2">${avisos}</div>` : '<div class="small text-muted mb-2">Aún no se han enviado avisos de vencimiento.</div>'}
                ${puede && t.fecha_caducidad ? '<button type="button" class="btn btn-outline-primary btn-sm" data-tr="aviso_vencimiento"><i class="bi bi-send me-1"></i>Enviar aviso ahora</button>' : ''}
            </div>`;
        }
        if (puede && !['emitida', 'cancelada'].includes(t.estado)) {
            acciones += `<div class="d-flex flex-wrap gap-2 mb-2">
                ${!['borrador', 'aprobada'].includes(t.estado) ? '<button type="button" class="btn btn-outline-warning btn-sm" data-tr="observar"><i class="bi bi-exclamation-triangle me-1"></i>Pedir corrección al cliente</button>' : ''}
                <button type="button" class="btn btn-outline-secondary btn-sm" data-tr="nota"><i class="bi bi-chat-left-text me-1"></i>Agregar nota</button>
                <button type="button" class="btn btn-outline-danger btn-sm" data-tr="cancelar"><i class="bi bi-slash-circle me-1"></i>Cancelar solicitud</button>
            </div>`;
        }

        const enlace = t.url_portal
            ? `<div class="input-group input-group-sm"><input type="text" class="form-control" readonly value="${esc(t.url_portal)}">
                 <button type="button" class="btn btn-outline-secondary" title="Copiar" onclick="copiarTextoFirma(this.dataset.v)" data-v="${esc(t.url_portal)}"><i class="bi bi-clipboard"></i></button>
                 ${puede ? '<button type="button" class="btn btn-outline-success" data-tr="enviar_enlace" title="Enviar por correo / WhatsApp"><i class="bi bi-send"></i></button>' : ''}</div>`
            : (puede ? '<button type="button" class="btn btn-outline-success btn-sm" data-tr="enviar_enlace"><i class="bi bi-send me-1"></i>Enviar enlace al cliente</button>' : '<span class="text-muted">Sin enlace</span>');

        const eventos = (t.eventos || []).map(ev => `
            <div class="border-start border-2 ps-2 mb-2 ${ev.visible_cliente ? 'border-primary' : 'border-secondary'}">
                <div class="fw-semibold text-dark">${esc(ev.titulo)} ${ev.visible_cliente ? '' : '<span class="badge bg-light text-secondary border ms-1">interno</span>'}</div>
                ${ev.detalle ? `<div class="text-muted">${esc(ev.detalle)}</div>` : ''}
                <div class="text-muted" style="font-size:.7rem;">${esc(ev.fecha)}${ev.usuario ? ' · ' + esc(ev.usuario) : ''}</div>
            </div>`).join('') || '<div class="text-muted">Sin movimientos todavía.</div>';

        document.getElementById('tramiteContenido').innerHTML = `
        <div class="row g-3 text-dark">
            <div class="col-lg-5">
                <div class="mb-2"><span class="text-muted">Estado:</span> <b>${esc(t.estado_label)}</b>${t.total !== null ? ` · <span class="text-muted">Total:</span> <b>$${Number(t.total).toFixed(2)}</b>` : ''}</div>
                ${paso(t.estado !== 'borrador', t.estado === 'borrador', 'Datos y documentos del cliente', t.estado === 'borrador' ? 'El cliente aún no envía sus documentos.' : '')}
                ${paso(pagoOk, !pagoOk && t.estado === 'pendiente', 'Pago', pagoOk ? `${esc(t.tipo_pago || '')}${t.factura_numero ? ' · Factura ' + esc(t.factura_numero) + ' (' + esc(t.factura_estado || '') + ')' : ''}` : (t.estado_pago === 'en_revision' ? 'Comprobante en revisión' : (t.estado_pago === 'rechazado' ? 'Rechazado: ' + esc(t.pago_motivo_rechazo || '') : 'Pendiente')))}
                ${paso(correoOk, pagoOk && !correoOk, 'Correo confirmado', correoOk ? `${esc(t.correo)} · ${esc(t.correo_confirmado_at)}` : (t.correo_enviado_at ? `Factura enviada a ${esc(t.correo)} el ${esc(t.correo_enviado_at)}; esperando confirmación.` : esc(t.correo || '')))}
                ${paso(registrada, t.estado === 'por_registrar', 'Registrada en Uanataca', registrada ? `${t.uanataca_referencia ? 'Ref. ' + esc(t.uanataca_referencia) + ' · ' : ''}${esc(t.uanataca_registrada_at || '')}` : 'Use la pestaña Uanataca para copiar los datos.')}
                ${paso(['aprobada', 'emitida'].includes(t.estado), t.estado === 'en_proceso', 'Aprobada por Uanataca', '')}
                ${paso(t.estado === 'emitida', t.estado === 'aprobada', 'Firma emitida', '')}
                ${t.estado === 'observada' ? `<div class="alert alert-warning py-1 px-2 mb-2">Corrección pedida: ${esc(t.observacion_cliente || '')}</div>` : ''}
                <div class="mt-3"><div class="text-muted mb-1">Enlace del cliente</div>${enlace}</div>
            </div>
            <div class="col-lg-7">
                ${acciones}
                <div class="text-muted fw-semibold mt-2 mb-1"><i class="bi bi-clock-history me-1"></i>Historial</div>
                <div style="max-height:260px;overflow-y:auto;">${eventos}</div>
            </div>
        </div>`;
    }

    function renderUanataca(t) {
        const filas = (t.uanataca || []).map(c => `
            <tr><td class="text-muted" style="width:200px;">${esc(c.campo)}</td>
                <td class="fw-semibold text-dark">${esc(c.valor) || '<span class="text-muted fw-normal">—</span>'}</td>
                <td class="text-end" style="width:40px;">${c.valor ? `<button type="button" class="btn btn-sm btn-link p-0" title="Copiar" onclick="copiarTextoFirma(this.dataset.v)" data-v="${esc(c.valor)}"><i class="bi bi-clipboard"></i></button>` : ''}</td></tr>`).join('');
        const docs = (t.adjuntos || []).map(a => `<a class="btn btn-sm btn-outline-secondary me-1 mb-1" href="${a.url}" target="_blank"><i class="bi bi-file-earmark me-1"></i>${esc(a.etiqueta)}</a>`).join('') || '<span class="text-muted">Sin documentos.</span>';
        const aviso = !t.correo_confirmado_at && t.estado_pago === 'confirmado'
            ? '<div class="alert alert-warning py-1 px-2 mb-2"><i class="bi bi-envelope-exclamation me-1"></i>El cliente aún no confirma su correo: Uanataca le enviará ahí la activación.</div>' : '';
        document.getElementById('uanatacaContenido').innerHTML = `
            ${aviso}
            <div class="mb-2">
                <span class="text-muted">Copie cada dato en el portal de Uanataca:</span>
            </div>
            <table class="table table-sm table-hover align-middle mb-3" style="font-size:.82rem;"><tbody>${filas}</tbody></table>
            <div class="text-muted fw-semibold mb-1">Documentos</div>
            <div>${docs}</div>`;
    }

    async function postTramite(endpoint, datos) {
        const fd = new FormData();
        fd.append('id', _idFirmaActual);
        Object.entries(datos || {}).forEach(([k, v]) => fd.append(k, v));
        const json = await (await fetch(`${urlBase}/${endpoint}`, { method: 'POST', body: fd })).json();
        mostrarAlerta(json.ok ? json.msg : json.error, json.ok ? 'success' : 'danger');
        if (json.ok) {
            cargarTramite(_idFirmaActual);
            if (window.fetchSearchFirmas) window.fetchSearchFirmas();
        }
        return json.ok;
    }

    async function pedirTexto(titulo, label) {
        if (window.Swal) {
            const r = await Swal.fire({ title: titulo, input: 'textarea', inputLabel: label, showCancelButton: true,
                confirmButtonText: 'Aceptar', cancelButtonText: 'Cancelar', target: modalEl,
                inputValidator: v => (!v || !v.trim()) ? 'Este dato es obligatorio.' : undefined });
            return r.isConfirmed ? r.value.trim() : null;
        }
        const v = prompt(`${titulo}\n${label}`);
        return v && v.trim() ? v.trim() : null;
    }

    let _tramiteEnCurso = false;
    document.getElementById('tramiteContenido')?.addEventListener('click', async (ev) => {
        const btn = ev.target.closest('[data-tr]');
        if (!btn || _tramiteEnCurso) return;
        const accion = btn.dataset.tr;
        // Texto original: si la acción falla, el botón vuelve a como estaba (si sale bien,
        // cargarTramite() repinta la pestaña y lo reemplaza).
        const htmlOriginal = btn.innerHTML;
        _tramiteEnCurso = true;
        btn.disabled = true;
        try {
            if (accion === 'aprobar') {
                const forma = document.getElementById('trFormaCobro')?.value;
                if (!forma) { mostrarAlerta('Elija la cuenta o forma de cobro donde llegó el pago.', 'warning'); return; }
                if (!(await confirmar('Se generará la factura, se enviará al SRI y se registrará el cobro.'))) return;
                await postTramite('aprobarPagoAjax', { id_forma_cobro: forma, referencia: document.getElementById('trReferencia')?.value || '' });
            } else if (accion === 'rechazar') {
                const motivo = await pedirTexto('Rechazar comprobante', 'Motivo (lo verá el cliente)');
                if (motivo) await postTramite('rechazarPagoAjax', { motivo });
            } else if (accion === 'reenviar') {
                await postTramite('reenviarConfirmacionAjax');
            } else if (accion === 'confirmar_manual') {
                if (await confirmar('¿El cliente confirmó por otro medio que el correo ' + (_tramite?.correo || '') + ' es suyo?', 'Sí, confirmado')) await postTramite('marcarCorreoConfirmadoAjax');
            } else if (accion === 'enviar_uanataca') {
                if (await confirmar('Se creará la solicitud en Uanataca con los datos y documentos del cliente (consume Uanacréditos).', 'Sí, enviar')) {
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando…';
                    await postTramite('enviarUanatacaAjax');
                }
            } else if (accion === 'aviso_vencimiento') {
                if (await confirmar('Se enviará al cliente el aviso de vencimiento con el enlace para renovar (correo y, si hay plantilla aprobada, WhatsApp).', 'Sí, enviar')) {
                    await postTramite('avisoVencimientoAjax');
                }
            } else if (accion === 'sincronizar_uanataca') {
                await postTramite('sincronizarUanatacaAjax');
            } else if (accion === 'registrar_uanataca') {
                await postTramite('accionTramiteAjax', { accion: 'registrar_uanataca', referencia: document.getElementById('trRefUanataca')?.value || '' });
            } else if (accion === 'emitida') {
                const fecha = document.getElementById('trCaducidad')?.value;
                if (!fecha) { mostrarAlerta('Indique la fecha de vencimiento de la firma emitida.', 'warning'); return; }
                await postTramite('accionTramiteAjax', { accion: 'emitida', fecha_caducidad: fecha });
            } else if (accion === 'observar') {
                const nota = await pedirTexto('Pedir corrección', '¿Qué debe corregir el cliente? (se le envía por correo)');
                if (nota) await postTramite('accionTramiteAjax', { accion: 'observar', nota });
            } else if (accion === 'nota') {
                const nota = await pedirTexto('Agregar nota', 'Nota interna (no la ve el cliente)');
                if (nota) await postTramite('accionTramiteAjax', { accion: 'nota', nota });
            } else if (accion === 'cancelar') {
                const nota = await pedirTexto('Cancelar solicitud', 'Motivo de la cancelación');
                if (nota) await postTramite('accionTramiteAjax', { accion: 'cancelar', nota });
            } else if (accion === 'enviar_enlace') {
                const d = {
                    id: _idFirmaActual,
                    nombres: document.getElementById('firma_nombres')?.value || '',
                    apellidos: [document.getElementById('firma_apellido_paterno')?.value, document.getElementById('firma_apellido_materno')?.value].filter(Boolean).join(' '),
                    correo: document.getElementById('firma_correo')?.value || '',
                    telefono: document.getElementById('firma_telefono')?.value || '',
                };
                if (window.abrirModalEnviarFormulario) window.abrirModalEnviarFormulario(d);
            }
        } catch (e) {
            mostrarAlerta('Error de conexión o respuesta inválida del servidor. Intente de nuevo.', 'danger');
        } finally {
            _tramiteEnCurso = false;
            btn.disabled = false;
            btn.innerHTML = htmlOriginal;
        }
    });

    function activarTab(btnId) {
        const btn = document.getElementById(btnId);
        if (btn && typeof bootstrap !== 'undefined') {
            (bootstrap.Tab.getInstance(btn) || new bootstrap.Tab(btn)).show();
        }
    }

})(window, document);
