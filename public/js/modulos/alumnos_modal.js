/**
 * Lógica del Modal de Alumno: pestañas, catálogos, filas dinámicas
 * (Matrícula / Horario / Servicios), alta rápida de Campus/Nivel y
 * documentos adjuntos.
 */
(function (window, document) {
    'use strict';

    const urlBase = (typeof BASE_URL !== 'undefined') ? (BASE_URL + '/modulos/alumnos') : (window.location.origin + '/sistema/public/modulos/alumnos');
    const modalEl = document.getElementById('modalAlumno');
    let modalInst = null;
    let catalogosCargados = false;
    let idAlumnoActual = null;

    const datosCatalogos = { tipos_id: [], puntos_emision: [], campus: [], niveles: [], tarifas_iva: [], config: {} };

    // Select de campus/nivel apuntado por el último botón "+" pulsado, para
    // saber a cuál select aplicar la autoselección cuando llega el evento.
    let selCampusObjetivo = null;
    let selNivelObjetivo = null;

    modalEl?.addEventListener('hide.bs.modal', () => {
        document.querySelectorAll('.alu-typeahead-dropdown').forEach(d => d.classList.add('d-none'));
    });


    // ── Mensajes (SweetAlert) ────────────────────────────────────────────────
    // Con un modal abierto el popup cuelga DENTRO de él (target): colgado de <body>
    // el focus trap de Bootstrap le quita el foco. heightAuto:false porque la página
    // es app-shell (html/body al 100 %).
    function swalTarget() {
        const abiertos = document.querySelectorAll('.modal.show');
        return abiertos.length ? abiertos[abiertos.length - 1] : (document.getElementById('modalAlumno') || 'body');
    }
    const TITULOS_SWAL = { success: 'Listo', error: 'Error', warning: 'Atención', info: 'Información' };
    function aviso(icon, texto, opts = {}) {
        if (typeof Swal === 'undefined') { window.alert(texto); return Promise.resolve(); }
        return Swal.fire(Object.assign({
            icon,
            title: TITULOS_SWAL[icon] || '',
            text: texto,
            confirmButtonText: 'Aceptar',
            target: swalTarget(),
            heightAuto: false,
        }, opts));
    }
    async function confirmar(texto, botonSi = 'Sí, continuar', peligro = false) {
        if (typeof Swal === 'undefined') return window.confirm(texto);
        const r = await Swal.fire({
            icon: 'warning',
            title: '¿Está seguro?',
            text: texto,
            showCancelButton: true,
            confirmButtonText: botonSi,
            cancelButtonText: 'Cancelar',
            confirmButtonColor: peligro ? '#dc3545' : undefined,
            reverseButtons: true,
            target: swalTarget(),
            heightAuto: false,
        });
        return r.isConfirmed;
    }

    function getModal() {
        if (!modalInst && typeof bootstrap !== 'undefined' && modalEl) {
            modalInst = new bootstrap.Modal(modalEl);
        }
        return modalInst;
    }

    async function fetchJson(url, opts) {
        const resp = await fetch(url, opts);
        return resp.json();
    }

    // ── Catálogos (tipos de identificación, puntos de emisión, campus, niveles) ──
    async function cargarCatalogos(forzar = false) {
        if (catalogosCargados && !forzar) return;
        try {
            const json = await fetchJson(`${urlBase}/catalogosAjax`);
            if (!json.ok) return;
            datosCatalogos.tipos_id = json.tipos_id || [];
            datosCatalogos.puntos_emision = json.puntos_emision || [];
            datosCatalogos.campus = json.campus || [];
            datosCatalogos.niveles = json.niveles || [];
            datosCatalogos.tarifas_iva = json.tarifas_iva || [];
            // Reglas de facturación del establecimiento (las mismas de la Factura de Venta).
            datosCatalogos.config = json.config_facturacion || {};
            catalogosCargados = true;

            const selTipo = document.getElementById('alu_tipo_id');
            if (selTipo) {
                selTipo.innerHTML = '<option value="">-- Seleccione --</option>' +
                    datosCatalogos.tipos_id.map(t => `<option value="${t.codigo}">${t.nombre}</option>`).join('');
            }
            llenarSelectSeries(document.getElementById('alu_punto_emision'));
            document.querySelectorAll('.sel-campus').forEach(sel => llenarSelectCampus(sel));
            document.querySelectorAll('.sel-nivel').forEach(sel => llenarSelectNivel(sel));
        } catch (e) {}
    }

    /**
     * Series (puntos de emisión) como en la Factura de Venta. Con una sola serie,
     * esa queda elegida y no hay opción vacía; con varias se pide elegir.
     */
    function llenarSelectSeries(select, seleccionado = '') {
        if (!select) return;
        const series = datosCatalogos.puntos_emision;
        const vacia = series.length === 1 ? '' : `<option value="">${series.length ? '-- Seleccione la serie --' : '-- No hay series activas --'}</option>`;
        select.innerHTML = vacia + series.map(p => `<option value="${p.id}" title="${escAttr(p.nombre || '')}">${escAttr(p.serie)}</option>`).join('');
        aplicarSerie(select, seleccionado);
    }

    /** Pone la serie guardada; si no existe (inactiva) o no hay, la única disponible o vacío. */
    function aplicarSerie(select, valor) {
        if (!select) return;
        const v = String(valor || '');
        if (v && Array.from(select.options).some(o => o.value === v)) {
            select.value = v;
        } else {
            select.selectedIndex = 0; // con una sola serie, esa; con varias, «Seleccione»
        }
    }

    function llenarSelectCampus(select, seleccionado = '') {
        select.innerHTML = '<option value="">-- Campus --</option>' +
            datosCatalogos.campus.map(c => `<option value="${c.id}" ${String(c.id) === String(seleccionado) ? 'selected' : ''}>${c.nombre}</option>`).join('');
    }

    function llenarSelectNivel(select, seleccionado = '') {
        select.innerHTML = '<option value="">-- Nivel/Curso --</option>' +
            datosCatalogos.niveles.map(n => `<option value="${n.id}" ${String(n.id) === String(seleccionado) ? 'selected' : ''}>${n.nombre}</option>`).join('');
    }

    window.addEventListener('campusGuardado', (e) => {
        const c = e.detail;
        if (!datosCatalogos.campus.some(x => String(x.id) === String(c.id))) datosCatalogos.campus.push({ id: c.id, nombre: c.nombre });
        // Sin fila de destino (alta desde la barra superior) va al campus de General.
        const objetivoCampus = selCampusObjetivo || document.getElementById('alu_campus_actual');
        document.querySelectorAll('.sel-campus').forEach(sel => {
            const val = (sel === objetivoCampus) ? c.id : sel.value;
            llenarSelectCampus(sel, val);
        });
        selCampusObjetivo = null;
        if (objetivoCampus && objetivoCampus.id === 'alu_campus_actual') asegurarPeriodoVigente();
    });

    window.addEventListener('nivelGuardado', (e) => {
        const n = e.detail;
        if (!datosCatalogos.niveles.some(x => String(x.id) === String(n.id))) datosCatalogos.niveles.push({ id: n.id, nombre: n.nombre });
        const objetivoNivel = selNivelObjetivo || document.getElementById('alu_nivel_actual');
        document.querySelectorAll('.sel-nivel').forEach(sel => {
            const val = (sel === objetivoNivel) ? n.id : sel.value;
            llenarSelectNivel(sel, val);
        });
        selNivelObjetivo = null;
        if (objetivoNivel && objetivoNivel.id === 'alu_nivel_actual') asegurarPeriodoVigente();
    });

    // El modal de Cliente (incluido desde clientes/modal_cliente.php) dispara
    // 'clienteGuardado' en `document` con { ok, data: {id, nombre, identificacion, ...} }.
    // Solo se autoselecciona si el modal de Alumno está abierto, para no
    // interferir si el usuario crea un cliente desde otra pantalla.
    document.addEventListener('clienteGuardado', (e) => {
        if (!modalEl || !modalEl.classList.contains('show')) return;
        const res = e.detail;
        if (!res || !res.ok || !res.data) return;
        const input = document.getElementById('alu_cliente_texto');
        const hidden = document.getElementById('alu_id_cliente');
        if (!input || !hidden) return;
        hidden.value = res.data.id;
        input.value = res.data.identificacion ? `${res.data.nombre} (${res.data.identificacion})` : res.data.nombre;
    });

    // ── Typeahead genérico (mismo patrón que app/views/modulos/mayores/index.php) ──
    // La lista se cuelga del <body> con position: fixed y la posiciona
    // CMG_anclarDropdown (js/components/dropdown_flotante.js, el de Pedidos y
    // Órdenes de Compra): así se dibuja POR ENCIMA del modal, sin quedar recortada
    // por las tablas con scroll ni por el borde del modal.
    function ocultarLista(dropdownEl) {
        dropdownEl.classList.add('d-none');
        dropdownEl.innerHTML = '';
    }

    function setupTypeahead(inputEl, dropdownEl, hiddenEl, fetchFn, renderLabel) {
        let debounceTimer;
        dropdownEl.classList.add('d-none');
        document.body.appendChild(dropdownEl);

        inputEl.addEventListener('keydown', (e) => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && hiddenEl.value !== '') {
                e.preventDefault();
                hiddenEl.value = '';
                inputEl.value = '';
                ocultarLista(dropdownEl);
            }
            if (e.key === 'Escape') ocultarLista(dropdownEl);
        });
        inputEl.addEventListener('input', () => {
            hiddenEl.value = '';
            clearTimeout(debounceTimer);
            const q = inputEl.value.trim();
            if (q.length < 1) { ocultarLista(dropdownEl); return; }
            debounceTimer = setTimeout(async () => {
                let items = [];
                try { items = await fetchFn(q); } catch (e) { return; }
                // El usuario siguió escribiendo o salió del campo mientras llegaba la respuesta.
                if (inputEl.value.trim() !== q || document.activeElement !== inputEl) return;
                if (!items || !items.length) { ocultarLista(dropdownEl); return; }
                dropdownEl.innerHTML = items.map(it => {
                    const label = renderLabel(it);
                    return `<a href="#" class="list-group-item list-group-item-action py-1 px-2 small" data-id="${escAttr(it.id)}" data-label="${escAttr(label)}">${escAttr(label)}</a>`;
                }).join('');
                if (typeof window.CMG_anclarDropdown === 'function') {
                    window.CMG_anclarDropdown(dropdownEl, inputEl, { anchoMinimo: 320, altoMaximo: 260 });
                } else {
                    dropdownEl.classList.remove('d-none');
                }
            }, 300);
        });
        inputEl.addEventListener('blur', () => { setTimeout(() => ocultarLista(dropdownEl), 150); });
        dropdownEl.addEventListener('click', (e) => {
            const a = e.target.closest('a[data-id]');
            if (!a) return;
            e.preventDefault();
            hiddenEl.value = a.dataset.id;
            inputEl.value = a.dataset.label;
            ocultarLista(dropdownEl);
        });
    }

    function initClienteTypeahead() {
        const input = document.getElementById('alu_cliente_texto');
        const dropdown = document.getElementById('alu_cliente_dropdown');
        const hidden = document.getElementById('alu_id_cliente');
        if (!input || input.dataset.typeaheadReady) return;
        input.dataset.typeaheadReady = '1';
        setupTypeahead(input, dropdown, hidden,
            async (q) => {
                const json = await fetchJson(`${urlBase}/getClientesAjax?q=${encodeURIComponent(q)}`);
                return json.ok ? json.data : [];
            },
            (it) => it.identificacion ? `${it.nombre} (${it.identificacion})` : it.nombre
        );
    }

    function initProductoTypeahead(row) {
        const input = row.querySelector('.txt-producto');
        const dropdown = row.querySelector('.producto-dropdown');
        const hidden = row.querySelector('.hid-producto');
        const precioInput = row.querySelector('.txt-precio');
        let ultimosResultados = [];
        setupTypeahead(input, dropdown, hidden,
            async (q) => {
                const json = await fetchJson(`${urlBase}/getProductosAjax?q=${encodeURIComponent(q)}`);
                ultimosResultados = json.ok ? json.data : [];
                // Ingreso libre (regla de la Factura de Venta): se ofrece usar lo escrito
                // como concepto si no coincide exactamente con un producto del catálogo.
                const concepto = q.replace(/\s+/g, ' ').trim();
                if (datosCatalogos.config.facturacion_libre && concepto
                    && !ultimosResultados.some(p => (p.nombre || '').trim().toUpperCase() === concepto.toUpperCase())) {
                    ultimosResultados = ultimosResultados.concat([{ id: '__libre__', nombre: concepto, libre: true }]);
                }
                return ultimosResultados;
            },
            (it) => it.libre ? `➕ Usar «${it.nombre}» como servicio libre` : it.nombre
        );
        // Si se vuelve a escribir, deja de ser el libre elegido (hay que elegir otra vez).
        input.addEventListener('input', () => { delete row.dataset.libre; });
        input.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
        dropdown.addEventListener('click', (e) => {
            const a = e.target.closest('a[data-id]');
            if (!a) return;
            const prod = ultimosResultados.find(p => String(p.id) === a.dataset.id);
            if (!prod) return;
            const selIva = row.querySelector('.sel-iva');
            if (prod.libre) {
                // Concepto libre: se crea como servicio del catálogo al guardar el alumno.
                hidden.value = '';
                input.value = prod.nombre;
                row.dataset.libre = '1';
                row.dataset.precioBase = 0;
                row.dataset.tarifaProducto = '';
                if (selIva) {
                    const def = datosCatalogos.config.id_tarifa_iva_defecto_libre;
                    selIva.value = def ? String(def) : (selIva.options[0]?.value || '');
                }
                if (precioInput) { precioInput.readOnly = false; precioInput.value = ''; precioInput.placeholder = 'Precio'; precioInput.focus(); }
                recalcularFilaServicio(row);
                return;
            }
            delete row.dataset.libre;
            row.dataset.precioBase = prod.precio_base ?? 0;
            row.dataset.tarifaProducto = prod.id_tarifa_iva ?? '';
            if (selIva) selIva.value = prod.id_tarifa_iva != null ? String(prod.id_tarifa_iva) : '';
            // Al elegir (o cambiar) el producto, el precio toma su precio base; el
            // usuario puede editarlo después para este alumno.
            if (precioInput) {
                precioInput.readOnly = datosCatalogos.config.editar_precio_factura === false;
                precioInput.value = Number(prod.precio_base || 0).toFixed(2);
                precioInput.placeholder = `Base: ${Number(prod.precio_base || 0).toFixed(2)}`;
            }
            recalcularFilaServicio(row);
        });
    }

    /** IVA y total de la línea: precio escrito o, si está vacío, el precio base del producto. */
    function recalcularFilaServicio(row) {
        const cant = parseFloat(row.querySelector('.txt-cantidad')?.value) || 0;
        const txtPrecio = row.querySelector('.txt-precio')?.value;
        const precio = txtPrecio !== '' && txtPrecio !== undefined ? (parseFloat(txtPrecio) || 0) : (parseFloat(row.dataset.precioBase) || 0);
        const iva = pctIvaFila(row);
        const base = Math.round(cant * precio * 100) / 100;
        const total = base + Math.round(base * iva) / 100;
        const celTotal = row.querySelector('.cel-total');
        if (celTotal) celTotal.textContent = total.toFixed(2);
        recalcularTotalesServicios();
    }

    /** % de IVA de la fila según su selector de tarifa. */
    function pctIvaFila(tr) {
        const opt = tr.querySelector('.sel-iva')?.selectedOptions?.[0];
        return opt ? (parseFloat(opt.dataset.pct) || 0) : 0;
    }

    function opcionesTarifas(seleccionada) {
        const sel = seleccionada != null ? String(seleccionada) : '';
        return datosCatalogos.tarifas_iva.map(t =>
            `<option value="${t.id}" data-pct="${escAttr(t.porcentaje_iva)}" ${String(t.id) === sel ? 'selected' : ''}>${escAttr(t.tarifa)}</option>`
        ).join('');
    }

    /**
     * Subtotal, subtotales por tarifa de IVA, IVA y total de los servicios ACTIVOS
     * (lo que saldría en la factura), con el mismo formato que la Factura de Venta.
     * IVA línea a línea, como el modo por defecto del generador.
     */
    function recalcularTotalesServicios() {
        const grupos = {};
        let subtotal = 0;
        let ivaTotal = 0;
        document.querySelectorAll('#tablaServicios tbody tr').forEach(tr => {
            if ((!tr.querySelector('.hid-producto')?.value && tr.dataset.libre !== '1') || !tr.querySelector('.chk-activo')?.checked) return;
            const cant = parseFloat(tr.querySelector('.txt-cantidad')?.value) || 0;
            const txtPrecio = tr.querySelector('.txt-precio')?.value;
            const precio = txtPrecio !== '' && txtPrecio !== undefined ? (parseFloat(txtPrecio) || 0) : (parseFloat(tr.dataset.precioBase) || 0);
            const pct = pctIvaFila(tr);
            const base = Math.round(cant * precio * 100) / 100;
            const iva = Math.round(base * pct) / 100;
            const opt = tr.querySelector('.sel-iva')?.selectedOptions?.[0];
            const k = opt ? opt.value : String(pct);
            grupos[k] = grupos[k] || { pct, base: 0, iva: 0, label: opt ? opt.textContent.trim() : `${pct}%` };
            grupos[k].base += base;
            grupos[k].iva += iva;
            subtotal += base;
            ivaTotal += iva;
        });
        const lista = Object.values(grupos).sort((a, b) => b.pct - a.pct);
        const txt = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        txt('aluLblSubtotal', subtotal.toFixed(2));
        txt('aluLblTotal', (Math.round((subtotal + ivaTotal) * 100) / 100).toFixed(2));
        const pctTxt = (n) => (n % 1 === 0 ? n : n.toFixed(2)) + '%';
        const contSub = document.getElementById('aluLblSubtotalesIva');
        if (contSub) {
            contSub.innerHTML = lista.map(g =>
                `<div class="d-flex justify-content-between align-items-center mb-1 text-muted"><span>Subtotal ${escAttr(g.label)}</span><span>${g.base.toFixed(2)}</span></div>`
            ).join('');
        }
        const contIva = document.getElementById('aluLblIvas');
        if (contIva) {
            contIva.innerHTML = lista.filter(g => g.pct > 0 && g.iva > 0).map(g =>
                `<div class="d-flex justify-content-between align-items-center mb-1"><span class="text-muted">(+) IVA ${pctTxt(g.pct)}</span><span>${g.iva.toFixed(2)}</span></div>`
            ).join('');
        }
    }

    // ── Información adicional de la factura (concepto / detalle) ───────────
    const INFO_ADICIONAL_DEFECTO = [{ concepto: 'Alumno', detalle: '{alumno}' }];

    window.aluAgregarFilaInfo = function (data = {}) {
        const tbody = document.querySelector('#tablaInfoAdicional tbody');
        if (!tbody) return;
        const row = document.createElement('tr');
        row.className = 'row-alu';
        row.innerHTML = `
            <td><input type="text" class="form-control form-control-sm input-alu txt-info-concepto" maxlength="300" placeholder="Ej. Alumno" list="aluConceptosInfo" value="${escAttr(data.concepto)}"></td>
            <td><input type="text" class="form-control form-control-sm input-alu txt-info-detalle" maxlength="300" placeholder="Ej. {alumno}" value="${escAttr(data.detalle)}"></td>
            <td class="text-center"><button type="button" class="btn btn-sm p-1 border-0 remove-row" title="Quitar"><i class="bi bi-trash text-danger"></i></button></td>
        `;
        tbody.appendChild(row);
        row.querySelector('.remove-row').addEventListener('click', () => row.remove());
    };

    function cargarInfoAdicional(valor) {
        let filas = valor;
        if (typeof filas === 'string') {
            try { filas = JSON.parse(filas); } catch (e) { filas = null; }
        }
        // NULL (alumno sin configurar) = las filas por defecto; [] = sin filas.
        (Array.isArray(filas) ? filas : INFO_ADICIONAL_DEFECTO).forEach(f => window.aluAgregarFilaInfo(f));
    }

    // ── Filas dinámicas: Matrícula (Períodos) ───────────────────────────────
    window.aluAgregarFilaPeriodo = function (data = {}) {
        const tbody = document.querySelector('#tablaPeriodos tbody');
        if (!tbody) return;
        const row = document.createElement('tr');
        row.className = 'row-alu';
        // Campus y nivel de cada período viajan en data-* de la fila: los de los
        // períodos cerrados se conservan tal cual (historial) y los del vigente se
        // toman de la pestaña General al serializar.
        row.dataset.idCampus = data.id_campus || '';
        row.dataset.idNivel = data.id_nivel || '';
        row.innerHTML = `
            <td><input type="text" class="form-control form-control-sm input-alu txt-anio" placeholder="2025-2026" value="${data.anio_lectivo || ''}"></td>
            <td><input type="date" class="form-control form-control-sm input-alu dt-ingreso" value="${data.fecha_ingreso || ''}"></td>
            <td><input type="date" class="form-control form-control-sm input-alu dt-salida" value="${data.fecha_salida || ''}"></td>
            <td><select class="form-select form-select-sm input-alu sel-motivo">
                    <option value="">--</option>
                    <option value="retiro_voluntario">Retiro voluntario</option>
                    <option value="cambio_institucion">Cambio de institución</option>
                    <option value="no_pago">No pago</option>
                    <option value="graduacion">Graduación</option>
                    <option value="otro">Otro</option>
                </select></td>
            <td><input type="text" class="form-control form-control-sm input-alu txt-obs" value="${data.observacion || ''}"></td>
            <td class="text-center"><button type="button" class="btn btn-sm p-1 border-0 remove-row" title="Quitar"><i class="bi bi-trash text-danger"></i></button></td>
        `;
        tbody.appendChild(row);

        if (data.motivo_salida) row.querySelector('.sel-motivo').value = data.motivo_salida;
        row.querySelector('.remove-row').addEventListener('click', () => row.remove());
    };

    // ── Campus / nivel de la matrícula vigente (pestaña General) ────────────
    function hoyIso() {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    /** Período vigente = con fecha de ingreso y sin fecha de salida (AlumnoRules admite uno solo). */
    function filaPeriodoVigente() {
        return Array.from(document.querySelectorAll('#tablaPeriodos tbody tr'))
            .find(tr => tr.querySelector('.dt-ingreso')?.value && !tr.querySelector('.dt-salida')?.value) || null;
    }

    /**
     * Si en General se elige campus o nivel y el alumno no tiene matrícula
     * vigente, se agrega el período (ingreso = hoy) en la grilla de Matrícula,
     * a la vista del usuario, para que la elección tenga dónde guardarse.
     */
    function asegurarPeriodoVigente() {
        const campus = document.getElementById('alu_campus_actual')?.value || '';
        const nivel = document.getElementById('alu_nivel_actual')?.value || '';
        if ((!campus && !nivel) || filaPeriodoVigente()) return;
        // Reutiliza una fila nueva que el usuario dejó sin fechas.
        const filaVacia = Array.from(document.querySelectorAll('#tablaPeriodos tbody tr'))
            .find(tr => !tr.querySelector('.dt-ingreso')?.value && !tr.querySelector('.dt-salida')?.value);
        if (filaVacia) {
            filaVacia.querySelector('.dt-ingreso').value = hoyIso();
        } else {
            window.aluAgregarFilaPeriodo({ fecha_ingreso: hoyIso() });
        }
    }

    function cargarCampusNivelGeneral() {
        const fila = filaPeriodoVigente();
        const selC = document.getElementById('alu_campus_actual');
        const selN = document.getElementById('alu_nivel_actual');
        if (selC) selC.value = fila ? fila.dataset.idCampus : '';
        if (selN) selN.value = fila ? fila.dataset.idNivel : '';
    }

    ['alu_campus_actual', 'alu_nivel_actual'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', asegurarPeriodoVigente);
    });

    // ── Filas dinámicas: Representantes / autorizados a retirar ─────────────
    // Datos libres (no son Clientes): el que factura va en la pestaña Facturación.
    function escAttr(v) {
        return String(v ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    const RELACIONES = [
        ['padre', 'Padre'], ['madre', 'Madre'], ['tutor', 'Tutor'], ['abuelo', 'Abuelo/a'],
        ['hermano', 'Hermano/a'], ['tio', 'Tío/a'], ['otro', 'Otro'],
    ];

    window.aluAgregarFilaRepresentante = function (data = {}) {
        const tbody = document.querySelector('#tablaRepresentantes tbody');
        if (!tbody) return;
        const row = document.createElement('tr');
        row.className = 'row-alu';
        const retira = data.puede_retirar === true || data.puede_retirar === 't' || data.puede_retirar === 1 || data.puede_retirar === '1';
        row.innerHTML = `
            <td><input type="text" class="form-control form-control-sm input-alu txt-rep-nombres" maxlength="200" placeholder="Nombres y apellidos" value="${escAttr(data.nombres)}"></td>
            <td><input type="text" class="form-control form-control-sm input-alu txt-rep-ident" maxlength="20" value="${escAttr(data.identificacion)}"></td>
            <td><input type="text" class="form-control form-control-sm input-alu txt-rep-tel" maxlength="30" value="${escAttr(data.telefono)}"></td>
            <td><select class="form-select form-select-sm input-alu sel-rep-relacion">
                    <option value="">--</option>
                    ${RELACIONES.map(([v, t]) => `<option value="${v}" ${data.relacion === v ? 'selected' : ''}>${t}</option>`).join('')}
                </select></td>
            <td class="text-center"><input type="checkbox" class="form-check-input chk-rep-retira" ${retira ? 'checked' : ''}></td>
            <td><input type="text" class="form-control form-control-sm input-alu txt-rep-obs" maxlength="200" value="${escAttr(data.observacion)}"></td>
            <td class="text-center"><button type="button" class="btn btn-sm p-1 border-0 remove-row" title="Quitar"><i class="bi bi-trash text-danger"></i></button></td>
        `;
        tbody.appendChild(row);
        row.querySelector('.remove-row').addEventListener('click', () => row.remove());
    };

    // ── Filas dinámicas: Horario ────────────────────────────────────────────
    const DIAS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    window.aluAgregarFilaHorario = function (data = {}) {
        const tbody = document.querySelector('#tablaHorarios tbody');
        if (!tbody) return;
        const row = document.createElement('tr');
        row.className = 'row-alu';
        row.innerHTML = `
            <td><select class="form-select form-select-sm input-alu sel-dia">
                    ${DIAS.slice(1).map((d, i) => `<option value="${i + 1}" ${Number(data.dia_semana) === i + 1 ? 'selected' : ''}>${d}</option>`).join('')}
                </select></td>
            <td><input type="time" class="form-control form-control-sm input-alu tm-inicio" value="${data.hora_inicio ? data.hora_inicio.slice(0, 5) : ''}"></td>
            <td><input type="time" class="form-control form-control-sm input-alu tm-fin" value="${data.hora_fin ? data.hora_fin.slice(0, 5) : ''}"></td>
            <td><select class="form-select form-select-sm input-alu sel-jornada">
                    <option value="">--</option>
                    <option value="matutina" ${data.jornada === 'matutina' ? 'selected' : ''}>Matutina</option>
                    <option value="vespertina" ${data.jornada === 'vespertina' ? 'selected' : ''}>Vespertina</option>
                    <option value="nocturna" ${data.jornada === 'nocturna' ? 'selected' : ''}>Nocturna</option>
                </select></td>
            <td><input type="text" class="form-control form-control-sm input-alu txt-obs" value="${data.observacion || ''}"></td>
            <td class="text-center"><button type="button" class="btn btn-sm p-1 border-0 remove-row" title="Quitar"><i class="bi bi-trash text-danger"></i></button></td>
        `;
        tbody.appendChild(row);
        row.querySelector('.remove-row').addEventListener('click', () => row.remove());
    };

    // ── Filas dinámicas: Servicios y Productos ──────────────────────────────
    window.aluAgregarFilaServicio = function (data = {}) {
        const tbody = document.querySelector('#tablaServicios tbody');
        if (!tbody) return;
        const row = document.createElement('tr');
        row.className = 'row-alu';
        row.dataset.precioBase = data.producto_precio_base ?? 0;
        row.dataset.tarifaProducto = data.producto_id_tarifa_iva ?? '';
        const editarIva = !!datosCatalogos.config.editar_iva_factura;
        const baseTxt = data.producto_precio_base !== undefined && data.producto_precio_base !== null
            ? `Base: ${Number(data.producto_precio_base).toFixed(2)}` : 'Precio base';
        // Línea guardada sin precio propio: se muestra el precio base del producto
        // (queda fijo en la línea al guardar, igual que al elegir un producto nuevo).
        const precioInicial = (data.precio_override !== undefined && data.precio_override !== null && data.precio_override !== '')
            ? data.precio_override
            : (data.id_producto && data.producto_precio_base !== undefined && data.producto_precio_base !== null
                ? Number(data.producto_precio_base).toFixed(2) : '');
        row.innerHTML = `
            <td class="position-relative">
                <input type="text" class="form-control form-control-sm input-alu txt-producto" placeholder="Buscar producto/servicio..." autocomplete="off" value="${escAttr(data.producto_nombre)}">
                <input type="hidden" class="hid-producto" value="${escAttr(data.id_producto)}">
                <div class="list-group shadow alu-typeahead-dropdown producto-dropdown d-none"></div>
            </td>
            <td><input type="text" class="form-control form-control-sm input-alu txt-detalle" maxlength="300" placeholder="Ej. Pensión {MES} {anio}" value="${escAttr(data.detalle)}"></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm input-alu txt-cantidad" value="${escAttr(data.cantidad_default ?? 1)}"></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm input-alu txt-precio" placeholder="${escAttr(baseTxt)}" title="Vacío = precio base del producto" value="${escAttr(precioInicial)}"></td>
            <td><select class="form-select form-select-sm input-alu sel-iva" ${editarIva ? '' : 'disabled title="La configuración de facturación no permite cambiar el IVA"'}>${opcionesTarifas(data.id_tarifa_efectiva ?? data.producto_id_tarifa_iva)}</select></td>
            <td class="text-end cel-total fw-semibold"></td>
            <td class="text-center"><input type="checkbox" class="form-check-input chk-activo" ${(data.activo === undefined || data.activo === true || data.activo === 't') ? 'checked' : ''}></td>
            <td class="text-center"><button type="button" class="btn btn-sm p-1 border-0 remove-row" title="Quitar"><i class="bi bi-trash text-danger"></i></button></td>
        `;
        tbody.appendChild(row);
        // Las listas se mueven al <body> en setupTypeahead: guardar la referencia
        // antes, para poder quitarlas junto con la fila.
        row._listas = [row.querySelector('.producto-dropdown')];
        initProductoTypeahead(row);
        row.querySelectorAll('.txt-cantidad, .txt-precio').forEach(el => el.addEventListener('input', () => recalcularFilaServicio(row)));
        row.querySelector('.sel-iva').addEventListener('change', () => recalcularFilaServicio(row));
        // El precio solo se edita si la configuración de facturación lo permite.
        if (datosCatalogos.config.editar_precio_factura === false) row.querySelector('.txt-precio').readOnly = true;
        row.querySelector('.chk-activo').addEventListener('change', recalcularTotalesServicios);
        recalcularFilaServicio(row);
        row.querySelector('.remove-row').addEventListener('click', () => {
            quitarFilaServicio(row);
            asegurarFilaServicio(); // si era el último, queda una fila vacía lista para escribir
        });
    };

    // ── Serialización de las 3 sub-tablas a los inputs *_json ───────────────
    function serializarTablas() {
        const periodos = [];
        const vigente = filaPeriodoVigente();
        document.querySelectorAll('#tablaPeriodos tbody tr').forEach(tr => {
            const fi = tr.querySelector('.dt-ingreso')?.value;
            if (!fi) return;
            const esVigente = tr === vigente;
            periodos.push({
                id_campus: esVigente ? (document.getElementById('alu_campus_actual')?.value || '') : (tr.dataset.idCampus || ''),
                id_nivel: esVigente ? (document.getElementById('alu_nivel_actual')?.value || '') : (tr.dataset.idNivel || ''),
                anio_lectivo: tr.querySelector('.txt-anio')?.value || '',
                fecha_ingreso: fi,
                fecha_salida: tr.querySelector('.dt-salida')?.value || '',
                motivo_salida: tr.querySelector('.sel-motivo')?.value || '',
                observacion: tr.querySelector('.txt-obs')?.value || '',
            });
        });
        document.getElementById('periodos_json').value = JSON.stringify(periodos);

        const representantes = [];
        document.querySelectorAll('#tablaRepresentantes tbody tr').forEach(tr => {
            const r = {
                nombres: tr.querySelector('.txt-rep-nombres')?.value.trim() || '',
                identificacion: tr.querySelector('.txt-rep-ident')?.value.trim() || '',
                telefono: tr.querySelector('.txt-rep-tel')?.value.trim() || '',
                relacion: tr.querySelector('.sel-rep-relacion')?.value || '',
                puede_retirar: !!tr.querySelector('.chk-rep-retira')?.checked,
                observacion: tr.querySelector('.txt-rep-obs')?.value.trim() || '',
            };
            // Fila totalmente vacía (agregada y no usada): se ignora.
            if (!r.nombres && !r.identificacion && !r.telefono && !r.observacion) return;
            representantes.push(r);
        });
        const hidRep = document.getElementById('representantes_json');
        if (hidRep) hidRep.value = JSON.stringify(representantes);

        const horarios = [];
        document.querySelectorAll('#tablaHorarios tbody tr').forEach(tr => {
            const hi = tr.querySelector('.tm-inicio')?.value;
            const hf = tr.querySelector('.tm-fin')?.value;
            if (!hi || !hf) return;
            horarios.push({
                dia_semana: tr.querySelector('.sel-dia')?.value || '',
                hora_inicio: hi,
                hora_fin: hf,
                jornada: tr.querySelector('.sel-jornada')?.value || '',
                observacion: tr.querySelector('.txt-obs')?.value || '',
            });
        });
        document.getElementById('horarios_json').value = JSON.stringify(horarios);

        const servicios = [];
        document.querySelectorAll('#tablaServicios tbody tr').forEach(tr => {
            const idProd = tr.querySelector('.hid-producto')?.value;
            const esLibre = !idProd && tr.dataset.libre === '1';
            if (!idProd && !esLibre) return;
            const tarifa = tr.querySelector('.sel-iva')?.value || '';
            servicios.push({
                id_producto: idProd || '',
                es_libre: esLibre,
                nombre_libre: esLibre ? tr.querySelector('.txt-producto').value.replace(/\s+/g, ' ').trim() : '',
                // Solo se manda el IVA si difiere del producto (o es un ítem libre).
                id_tarifa_iva: (esLibre || (tarifa && tarifa !== String(tr.dataset.tarifaProducto || ''))) ? tarifa : '',
                detalle: tr.querySelector('.txt-detalle')?.value.trim() || '',
                cantidad_default: tr.querySelector('.txt-cantidad')?.value || 1,
                precio_override: tr.querySelector('.txt-precio')?.value || '',
                activo: tr.querySelector('.chk-activo')?.checked ?? true,
            });
        });
        document.getElementById('servicios_json').value = JSON.stringify(servicios);

        const info = [];
        document.querySelectorAll('#tablaInfoAdicional tbody tr').forEach(tr => {
            const concepto = tr.querySelector('.txt-info-concepto')?.value.trim() || '';
            const detalle = tr.querySelector('.txt-info-detalle')?.value.trim() || '';
            if (concepto || detalle) info.push({ concepto, detalle });
        });
        const hidInfo = document.getElementById('info_adicional_json');
        if (hidInfo) hidInfo.value = JSON.stringify(info);
    }

    /** Sin ítems, deja una fila vacía lista para escribir (las vacías no se guardan). */
    function asegurarFilaServicio() {
        if (!document.querySelector('#tablaServicios tbody tr')) window.aluAgregarFilaServicio();
    }

    function quitarFilaServicio(row) {
        (row._listas || []).forEach(l => l && l.remove());
        row.remove();
        recalcularTotalesServicios();
    }

    function limpiarTablas() {
        document.querySelectorAll('#tablaServicios tbody tr').forEach(quitarFilaServicio);
        ['tablaPeriodos', 'tablaHorarios', 'tablaServicios', 'tablaRepresentantes', 'tablaInfoAdicional'].forEach(id => {
            const tbody = document.querySelector(`#${id} tbody`);
            if (tbody) tbody.innerHTML = '';
        });
    }

    // ── Documentos ───────────────────────────────────────────────────────────
    // La pestaña Documentos está siempre activa (ahí vive la foto, que se puede
    // cargar desde el alta); lo que exige un alumno guardado son los adjuntos,
    // porque se enlazan por id_alumno.
    function setAdjuntosHabilitados(habilitado) {
        ['alu_doc_tipo', 'alu_doc_archivo', 'alu_doc_btn_adjuntar'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = !habilitado;
        });
    }

    function renderDocumentos(documentos) {
        const tbody = document.getElementById('tbodyDocumentosAlumno');
        if (!tbody) return;
        if (!idAlumnoActual) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3 small">Guarde el alumno para adjuntar documentos.</td></tr>';
            return;
        }
        if (!documentos || !documentos.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3 small">Sin documentos adjuntos.</td></tr>';
            return;
        }
        const etiquetas = {
            partida_nacimiento: 'Partida de nacimiento', cedula: 'Cédula/Identificación',
            foto_carnet: 'Foto carnet', certificado_medico: 'Certificado médico',
            contrato: 'Contrato', otro: 'Otro',
        };
        tbody.innerHTML = documentos.map(d => `
            <tr>
                <td>${etiquetas[d.tipo_documento] || d.tipo_documento}</td>
                <td><a href="${window.BASE_URL}/${d.ruta_archivo}" target="_blank">${d.nombre_archivo || 'Ver archivo'}</a></td>
                <td class="small text-muted">${d.fecha_carga || ''}</td>
                <td class="text-center"><button type="button" class="btn btn-sm p-1 border-0" title="Eliminar" onclick="window.aluEliminarDocumento(${d.id})"><i class="bi bi-trash text-danger"></i></button></td>
            </tr>
        `).join('');
    }

    window.aluSubirDocumento = async function () {
        if (!idAlumnoActual) { aviso('warning', 'Guarde el alumno antes de adjuntar documentos.'); return; }
        const fileInput = document.getElementById('alu_doc_archivo');
        const tipo = document.getElementById('alu_doc_tipo').value;
        if (!fileInput.files.length) { aviso('warning', 'Seleccione un archivo.'); return; }

        const fd = new FormData();
        fd.append('id_alumno', idAlumnoActual);
        fd.append('tipo_documento', tipo);
        fd.append('archivo', fileInput.files[0]);

        try {
            const resp = await fetch(`${urlBase}/subirDocumentoAjax`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                renderDocumentos(json.documentos);
                fileInput.value = '';
            } else {
                aviso('error', json.error || 'No se pudo adjuntar el documento.');
            }
        } catch (e) {
            aviso('error', 'Error de conexión al subir el documento.');
        }
    };

    window.aluEliminarDocumento = async function (idDocumento) {
        if (!(await confirmar('Se eliminará este documento adjunto.', 'Sí, eliminar', true))) return;
        const fd = new FormData();
        fd.append('id_documento', idDocumento);
        fd.append('id_alumno', idAlumnoActual);
        try {
            const resp = await fetch(`${urlBase}/eliminarDocumentoAjax`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) renderDocumentos(json.documentos);
            else aviso('error', json.error || 'No se pudo eliminar.');
        } catch (e) { aviso('error', 'Error de conexión al eliminar el documento.'); }
    };

    // ── Foto del alumno ──────────────────────────────────────────────────────
    function initFotoUpload() {
        const input = document.getElementById('alu_foto_input');
        if (!input || input.dataset.ready) return;
        input.dataset.ready = '1';
        input.addEventListener('change', async () => {
            if (!input.files.length) return;
            const fd = new FormData();
            fd.append('foto', input.files[0]);
            try {
                const resp = await fetch(`${urlBase}/uploadFotoAjax`, { method: 'POST', body: fd });
                const json = await resp.json();
                if (json.ok) {
                    document.getElementById('alu_foto_ruta').value = json.path;
                    const preview = document.getElementById('alu_foto_preview');
                    preview.src = `${window.BASE_URL}/${json.path}`;
                    preview.style.display = '';
                } else {
                    aviso('error', json.error || 'No se pudo subir la imagen.');
                }
            } catch (e) {
                aviso('error', 'Error de conexión al subir la imagen.');
            }
        });
    }

    // ── Consulta de cédula (SriIdentificationService, igual que Empleados) ───
    const TIPO_CEDULA = '05'; // AlumnoRules::CEDULA
    let sriDebounceAlu = null;
    let sriUltimaConsultaAlu = '';

    function mostrarBadgeSriAlu(texto, clase) {
        const badge = document.getElementById('aluSriBadge');
        if (!badge) return;
        badge.textContent = texto;
        badge.className = 'badge ' + clase;
    }

    function limpiarBadgeSriAlu() {
        const badge = document.getElementById('aluSriBadge');
        if (badge) badge.className = 'badge d-none';
        document.getElementById('aluSriSpinner')?.classList.add('d-none');
        window.CMG_Identificacion?.pintarAviso(document.getElementById('alu_identificacion'), null);
    }

    /**
     * El registro civil devuelve "APELLIDO1 APELLIDO2 NOMBRE1 NOMBRE2". Las
     * partículas (DE, DEL, LA…) se pegan a la palabra siguiente para no partir
     * apellidos compuestos como "DE LA TORRE". Con 3+ bloques: los dos primeros
     * son apellidos; con 2: uno y uno.
     */
    function separarNombreCompleto(nombre) {
        const particulas = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'SAN', 'SANTA', 'Y', 'VAN', 'VON', 'DA', 'DI'];
        const bloques = [];
        let pendiente = [];
        String(nombre || '').trim().split(/\s+/).filter(Boolean).forEach(pal => {
            pendiente.push(pal);
            if (!particulas.includes(pal.toUpperCase())) {
                bloques.push(pendiente.join(' '));
                pendiente = [];
            }
        });
        if (pendiente.length) bloques.push(pendiente.join(' '));
        if (bloques.length <= 1) return { apellidos: bloques[0] || '', nombres: '' };
        const nAp = bloques.length >= 3 ? 2 : 1;
        return { apellidos: bloques.slice(0, nAp).join(' '), nombres: bloques.slice(nAp).join(' ') };
    }

    // Solo pisa el campo si está vacío o si lo llenó una consulta anterior (el
    // usuario corrigió la cédula): nunca lo que el usuario escribió a mano.
    function rellenarSiLibre(el, valor) {
        if (!el || !valor) return;
        if (el.value.trim() === '' || el.dataset.sri === '1') {
            el.value = valor;
            el.dataset.sri = '1';
        }
    }

    async function consultarSriAlu(cedula) {
        const campo = document.getElementById('alu_identificacion');
        const spinner = document.getElementById('aluSriSpinner');
        sriUltimaConsultaAlu = cedula;
        spinner?.classList.remove('d-none');
        mostrarBadgeSriAlu('Consultando…', 'bg-secondary');
        try {
            const fd = new FormData();
            fd.append('identificacion', cedula);
            const json = await fetchJson(`${urlBase}/consultarSri`, { method: 'POST', body: fd });
            if (sriUltimaConsultaAlu !== cedula) return; // llegó tarde: ya se consulta otra
            spinner?.classList.add('d-none');
            window.CMG_Identificacion?.avisoTrasSri(campo, 'CEDULA', json);

            if (!json.ok || !json.data || !json.data.nombre) {
                mostrarBadgeSriAlu('No encontrado', 'bg-warning text-dark');
                return;
            }
            mostrarBadgeSriAlu('✓ Encontrado', 'bg-success');
            const partes = separarNombreCompleto(json.data.nombre);
            rellenarSiLibre(document.getElementById('alu_apellidos'), partes.apellidos);
            rellenarSiLibre(document.getElementById('alu_nombres'), partes.nombres);
        } catch (e) {
            if (sriUltimaConsultaAlu !== cedula) return;
            spinner?.classList.add('d-none');
            mostrarBadgeSriAlu('Error', 'bg-danger');
        }
    }

    function onIdentificacionAlu() {
        limpiarBadgeSriAlu();
        clearTimeout(sriDebounceAlu);
        sriUltimaConsultaAlu = '';
        const tipo = document.getElementById('alu_tipo_id')?.value || '';
        const campo = document.getElementById('alu_identificacion');
        const valor = (campo?.value || '').replace(/\D/g, '');
        if (tipo !== TIPO_CEDULA || valor.length !== 10) return;
        window.CMG_Identificacion?.pintarAviso(campo, window.CMG_Identificacion.aviso('CEDULA', valor));
        sriDebounceAlu = setTimeout(() => consultarSriAlu(valor), 700);
    }

    document.getElementById('alu_identificacion')?.addEventListener('input', onIdentificacionAlu);
    document.getElementById('alu_tipo_id')?.addEventListener('change', onIdentificacionAlu);
    // Lo que el usuario escribe a mano ya no es "del SRI": una nueva consulta no lo pisa.
    ['alu_nombres', 'alu_apellidos'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', e => { delete e.target.dataset.sri; });
    });

    function reiniciarSriAlu() {
        clearTimeout(sriDebounceAlu);
        sriUltimaConsultaAlu = '';
        limpiarBadgeSriAlu();
        ['alu_nombres', 'alu_apellidos'].forEach(id => {
            const el = document.getElementById(id);
            if (el) delete el.dataset.sri;
        });
    }

    // ── Favoritos de campo (estrellas, public/js/favoritos.js) ───────────────
    // Al editar los valores se asignan por código (sin evento change), así que la
    // estrella no se entera sola: se repinta comparando con el favorito guardado.
    function refrescarEstrellasFavoritos() {
        if (typeof APP_FAVORITOS === 'undefined' || typeof marcarEstrella !== 'function') return;
        document.querySelectorAll('#modalAlumno .btn-favorito').forEach(estrella => {
            const el = document.querySelector(estrella.dataset.target);
            const fav = APP_FAVORITOS[estrella.dataset.campo];
            marcarEstrella(estrella, !!el && fav !== undefined && fav !== '' && String(el.value) === String(fav));
        });
    }

    // ── Facturación desde el alumno ─────────────────────────────────────────
    const CFG_FACT = window.ALU_FACT_CFG || {};
    const ESTADOS_FACT = { borrador: 'Borrador', autorizado: 'Autorizada', anulado: 'Anulada' };
    let modalGfInst = null;
    let gfDatos = null;

    function fmt(n) { return (Number(n) || 0).toFixed(2); }
    function escHtml(v) { return escAttr(v); }

    function mesActual() {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    }

    function gfAlerta(msg, tipo) {
        if (!msg) return;
        aviso(tipo === 'danger' ? 'error' : tipo, msg);
    }

    function gfResumen() {
        const porCliente = {};
        document.querySelectorAll('#aluGfLineas .gf-chk:checked').forEach(chk => {
            const l = gfDatos.lineas.find(x => String(x.id) === chk.value);
            if (!l) return;
            porCliente[l.id_cliente] = porCliente[l.id_cliente] || { nombre: l.cliente, total: 0 };
            porCliente[l.id_cliente].total += l.total;
        });
        const grupos = Object.values(porCliente);
        const el = document.getElementById('aluGfResumen');
        if (el) {
            el.innerHTML = grupos.length
                ? `<strong>Se generarán ${grupos.length} factura(s):</strong> ` + grupos.map(g => `${escHtml(g.nombre)} — ${fmt(g.total)}`).join(' · ')
                : '<span class="text-muted">Marque los servicios a facturar.</span>';
        }
        const btn = document.getElementById('aluGfBtnGenerar');
        if (btn) btn.disabled = grupos.length === 0;
    }

    async function gfCargar() {
        gfAlerta('', '');
        const tbody = document.getElementById('aluGfLineas');
        const avisos = document.getElementById('aluGfAvisos');
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3 small">Cargando…</td></tr>';
        avisos.innerHTML = '';
        const mes = document.getElementById('aluGfMes').value || mesActual();
        try {
            const json = await fetchJson(`${urlBase}/prepararFacturaAjax?id=${idAlumnoActual}&mes=${encodeURIComponent(mes)}`);
            if (!json.ok) {
                tbody.innerHTML = '';
                gfAlerta(json.error || 'No se pudo preparar la factura.', 'danger');
                gfDatos = { lineas: [] };
                gfResumen();
                return;
            }
            gfDatos = json;
            const selSerie = document.getElementById('aluGfSerie');
            if (json.id_punto_emision && !selSerie.dataset.tocado) selSerie.value = String(json.id_punto_emision);

            avisos.innerHTML = (json.avisos || []).map(a =>
                `<div class="alert alert-warning py-1 px-2 small mb-2"><i class="bi bi-exclamation-triangle me-1"></i>${escHtml(a.texto)} Si genera de nuevo, se emitirá otra factura.</div>`
            ).join('');

            if (!json.lineas.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3 small">El alumno no tiene servicios activos guardados (pestaña Facturación).</td></tr>';
            } else {
                tbody.innerHTML = json.lineas.map(l => `
                    <tr>
                        <td class="text-center"><input type="checkbox" class="form-check-input gf-chk" value="${l.id}" ${l.marcada ? 'checked' : ''}></td>
                        <td>${escHtml(l.producto)}${l.aviso ? ` <span class="badge bg-warning text-dark">${escHtml(l.aviso)}</span>` : ''}${l.detalle ? `<div class="text-muted" style="font-size:.72rem;">${escHtml(l.detalle)}</div>` : ''}</td>
                        <td class="text-end">${fmt(l.cantidad)}</td>
                        <td class="text-end">${fmt(l.precio)}</td>
                        <td class="text-end">${fmt(l.total)}</td>
                        <td>${escHtml(l.cliente)}</td>
                    </tr>`).join('');
                tbody.querySelectorAll('.gf-chk').forEach(c => c.addEventListener('change', gfResumen));
            }
            gfResumen();
        } catch (e) {
            tbody.innerHTML = '';
            gfAlerta('Error de conexión al preparar la factura.', 'danger');
        }
    }

    window.aluAbrirGenerarFactura = async function () {
        if (!idAlumnoActual) {
            aviso('warning', 'Guarde el alumno antes de generar facturas.');
            return;
        }
        const el = document.getElementById('modalAluGenerarFactura');
        if (!el || typeof bootstrap === 'undefined') return;
        await cargarCatalogos();
        const selSerie = document.getElementById('aluGfSerie');
        llenarSelectSeries(selSerie);
        delete selSerie.dataset.tocado;
        document.getElementById('aluGfMes').value = mesActual();
        document.getElementById('aluGfTexto').value = '';
        modalGfInst = modalGfInst || new bootstrap.Modal(el);
        modalGfInst.show();
        gfCargar();
    };

    document.getElementById('aluGfMes')?.addEventListener('change', gfCargar);
    document.getElementById('aluGfSerie')?.addEventListener('change', e => { e.target.dataset.tocado = '1'; });
    document.getElementById('aluGfTodas')?.addEventListener('change', e => {
        document.querySelectorAll('#aluGfLineas .gf-chk').forEach(c => { c.checked = e.target.checked; });
        gfResumen();
    });

    window.aluGenerarFactura = async function () {
        const ids = Array.from(document.querySelectorAll('#aluGfLineas .gf-chk:checked')).map(c => Number(c.value));
        const serie = document.getElementById('aluGfSerie').value;
        if (!serie) { gfAlerta('Seleccione la serie de la factura.', 'warning'); return; }
        if (!ids.length) { gfAlerta('Marque al menos un servicio.', 'warning'); return; }
        if (gfDatos && gfDatos.avisos && gfDatos.avisos.length
            && !(await confirmar('Este mes ya tiene facturas generadas para este alumno. Se emitirá otra factura.', 'Sí, generar'))) {
            return;
        }
        const btn = document.getElementById('aluGfBtnGenerar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generando…';
        try {
            const fd = new FormData();
            fd.append('id', idAlumnoActual);
            fd.append('id_punto_emision', serie);
            fd.append('mes', document.getElementById('aluGfMes').value);
            fd.append('texto_item', document.getElementById('aluGfTexto').value.trim());
            fd.append('lineas', JSON.stringify(ids));
            const json = await fetchJson(`${urlBase}/generarFacturaAjax`, { method: 'POST', body: fd });
            if (json.ok) {
                modalGfInst?.hide();
                aviso(json.errores && json.errores.length ? 'warning' : 'success', json.msg, { target: document.getElementById('modalAlumno') });
                cargarFacturasAlumno();
            } else {
                gfAlerta(json.error || 'No se pudo generar la factura.', 'danger');
            }
        } catch (e) {
            gfAlerta('Error de conexión al generar la factura.', 'danger');
        } finally {
            btn.innerHTML = '<i class="bi bi-receipt-cutoff me-1"></i> Generar';
            gfResumen();
        }
    };

    /**
     * Pestaña Transacciones: lo facturado al alumno, un renglón por ítem, con la
     * factura en que salió (número con enlace al PDF, estado y cliente en el title).
     * Las anuladas se muestran tachadas y no suman en el pie.
     */
    async function cargarFacturasAlumno() {
        const tbody = document.getElementById('tbodyFacturasAlumno');
        const tfoot = document.getElementById('tfootFacturasAlumno');
        if (!tbody) return;
        const COLS = 12;
        const mensaje = (txt, cls = 'text-muted') => {
            tbody.innerHTML = `<tr><td colspan="${COLS}" class="text-center ${cls} py-3 small">${escHtml(txt)}</td></tr>`;
            tfoot?.classList.add('d-none');
        };
        if (!idAlumnoActual) { mensaje('Guarde el alumno para ver sus transacciones.'); return; }
        mensaje('Cargando…');
        try {
            const json = await fetchJson(`${urlBase}/facturasAjax?id=${idAlumnoActual}`);
            if (!json.ok) { mensaje(json.error || 'Error', 'text-danger'); return; }
            if (!json.data.length) { mensaje('Aún no se ha facturado nada desde este alumno.'); return; }

            const tot = { subtotal: 0, iva: 0, total: 0, docs: 0 };
            const filas = [];
            json.data.forEach(f => {
                const anulada = f.estado === 'anulado';
                if (!anulada) tot.docs++;
                const numero = CFG_FACT.urlPdfFactura
                    ? `<a href="${escAttr(CFG_FACT.urlPdfFactura)}?id=${f.id_factura}" data-pdf-documento title="PDF de la factura"><i class="bi bi-file-earmark-pdf text-danger me-1"></i>${escHtml(f.numero)}</a>`
                    : escHtml(f.numero);
                const refFactura = `<td>${escHtml(f.periodo)}</td>
                    <td>${escHtml(f.fecha)}</td>
                    <td title="Cliente: ${escAttr(f.cliente)} · Generada el ${escAttr(f.generada)}">${numero}</td>
                    <td>${ESTADOS_FACT[f.estado] || escHtml(f.estado)}</td>`;
                const lineas = f.lineas && f.lineas.length ? f.lineas : [null];
                lineas.forEach(l => {
                    const cls = anulada ? 'text-decoration-line-through text-muted' : '';
                    if (!l) {
                        filas.push(`<tr class="${cls}">${refFactura}<td colspan="8" class="text-muted small">Sin detalle.</td></tr>`);
                        return;
                    }
                    const total = l.subtotal + l.iva;
                    if (!anulada) { tot.subtotal += l.subtotal; tot.iva += l.iva; tot.total += total; }
                    filas.push(`<tr class="${cls}">${refFactura}
                        <td class="text-muted">${escHtml(l.codigo)}</td>
                        <td style="white-space:normal; min-width:200px;">${escHtml(l.descripcion)}${l.detalle ? `<div class="text-muted" style="font-size:.72rem;">${escHtml(l.detalle)}</div>` : ''}</td>
                        <td class="text-end">${fmt(l.cantidad)}</td>
                        <td class="text-end">${fmt(l.precio)}</td>
                        <td class="text-end">${l.descuento ? fmt(l.descuento) : ''}</td>
                        <td class="text-end">${fmt(l.subtotal)}</td>
                        <td class="text-end">${fmt(l.iva)}</td>
                        <td class="text-end fw-semibold">${fmt(total)}</td>
                    </tr>`);
                });
            });
            tbody.innerHTML = filas.join('');
            if (tfoot) {
                tfoot.innerHTML = `<tr class="fw-bold bg-light">
                    <td colspan="9">${tot.docs} factura(s) vigente(s)</td>
                    <td class="text-end">${fmt(tot.subtotal)}</td>
                    <td class="text-end">${fmt(tot.iva)}</td>
                    <td class="text-end">${fmt(tot.total)}</td>
                </tr>`;
                tfoot.classList.remove('d-none');
            }
        } catch (e) {
            mensaje('Error de conexión.', 'text-danger');
        }
    }
    document.getElementById('tab-facturas-btn')?.addEventListener('shown.bs.tab', cargarFacturasAlumno);

    // ── Abrir modal: Crear / Editar ─────────────────────────────────────────
    function irATabGeneral() {
        const btn = document.getElementById('tab-general-btn');
        if (btn && typeof bootstrap !== 'undefined') {
            (bootstrap.Tab.getInstance(btn) || new bootstrap.Tab(btn)).show();
        }
    }

    window.abrirModalAlumnoCrear = async function () {
        await cargarCatalogos();
        const form = document.getElementById('formAlumnoModal');
        if (!form) return;
        form.reset();
        reiniciarSriAlu();
        limpiarTablas();
        idAlumnoActual = null;

        document.getElementById('alu_id').value = '';
        document.getElementById('alu_id_cliente').value = '';
        document.getElementById('alu_cliente_texto').value = '';
        document.getElementById('alu_foto_ruta').value = '';
        const previewCrear = document.getElementById('alu_foto_preview');
        previewCrear.style.display = '';
        previewCrear.src = `${window.BASE_URL}/img/no-image.png`;
        document.getElementById('alu_estado_academico').value = 'activo';

        document.getElementById('tituloModalAlumno').textContent = 'Nuevo Alumno';
        document.getElementById('btnEliminarAlumnoModal').classList.add('d-none');

        setAdjuntosHabilitados(false);
        renderDocumentos([]);
        cargarFacturasAlumno();
        cargarInfoAdicional(null);
        asegurarFilaServicio();
        recalcularTotalesServicios();

        // Después de los valores por defecto, para que el favorito del usuario mande.
        if (typeof window.aplicarFavoritosModal === 'function') window.aplicarFavoritosModal('#modalAlumno');
        refrescarEstrellasFavoritos();

        initClienteTypeahead();
        initFotoUpload();
        irATabGeneral();
        getModal()?.show();
    };

    window.abrirModalAlumnoEditar = async function (rowOrData) {
        await cargarCatalogos();
        let base;
        if (rowOrData instanceof HTMLElement) {
            base = typeof rowOrData.dataset.row === 'string' ? JSON.parse(rowOrData.dataset.row) : rowOrData;
        } else {
            base = rowOrData;
        }
        if (!base || !base.id) return;

        const form = document.getElementById('formAlumnoModal');
        if (!form) return;
        form.reset();
        reiniciarSriAlu();
        limpiarTablas();

        try {
            const json = await fetchJson(`${urlBase}/getDetalleAjax?id=${base.id}`);
            if (!json.ok) { aviso('error', json.error || 'No se pudo cargar el alumno.'); return; }
            const d = json.data;
            idAlumnoActual = d.id;

            document.getElementById('alu_id').value = d.id;
            document.getElementById('alu_nombres').value = d.nombres || '';
            document.getElementById('alu_apellidos').value = d.apellidos || '';
            document.getElementById('alu_tipo_id').value = d.tipo_identificacion || '';
            document.getElementById('alu_identificacion').value = d.numero_identificacion || '';
            document.getElementById('alu_fecha_nacimiento').value = d.fecha_nacimiento || '';
            document.getElementById('alu_sexo').value = d.sexo || '';
            document.getElementById('alu_nacionalidad').value = d.nacionalidad || '';
            document.getElementById('alu_estado_academico').value = d.estado_academico || 'activo';
            document.getElementById('alu_observaciones').value = d.observaciones || '';

            document.getElementById('alu_id_cliente').value = d.id_cliente || '';
            document.getElementById('alu_cliente_texto').value = d.representante_nombre
                ? `${d.representante_nombre}${d.representante_identificacion ? ' (' + d.representante_identificacion + ')' : ''}`
                : '';
            aplicarSerie(document.getElementById('alu_punto_emision'), d.id_punto_emision);

            document.getElementById('alu_tipo_sangre').value = d.tipo_sangre || '';
            document.getElementById('alu_alergias').value = d.alergias_condiciones || '';
            document.getElementById('alu_emerg_nombre').value = d.contacto_emergencia_nombre || '';
            document.getElementById('alu_emerg_telefono').value = d.contacto_emergencia_telefono || '';

            document.getElementById('alu_foto_ruta').value = d.foto_ruta || '';
            const preview = document.getElementById('alu_foto_preview');
            preview.style.display = '';
            preview.src = d.foto_ruta ? `${window.BASE_URL}/${d.foto_ruta}` : `${window.BASE_URL}/img/no-image.png`;

            (d.periodos || []).forEach(p => window.aluAgregarFilaPeriodo(p));
            cargarCampusNivelGeneral();
            (d.horarios || []).forEach(h => window.aluAgregarFilaHorario(h));
            (d.representantes || []).forEach(r => window.aluAgregarFilaRepresentante(r));
            (d.servicios || []).forEach(s => window.aluAgregarFilaServicio(s));
            cargarInfoAdicional(d.info_adicional ?? null);
            asegurarFilaServicio();
            recalcularTotalesServicios();
            renderDocumentos(d.documentos || []);

            document.getElementById('tituloModalAlumno').textContent = 'Editar Alumno';
                document.getElementById('btnEliminarAlumnoModal').classList.remove('d-none');
            setAdjuntosHabilitados(true);
            refrescarEstrellasFavoritos();
            cargarFacturasAlumno();

            initClienteTypeahead();
            initFotoUpload();
            irATabGeneral();
            getModal()?.show();
        } catch (e) {
            aviso('error', 'Error de conexión al cargar el alumno.');
        }
    };

    // ── Guardar / Eliminar ───────────────────────────────────────────────────
    window.guardarAlumnoModal = async function () {
        const form = document.getElementById('formAlumnoModal');
        if (!form) return;
        if (!document.getElementById('alu_nombres').value.trim() || !document.getElementById('alu_apellidos').value.trim()) {
            aviso('warning', 'Nombres y apellidos son obligatorios.');
            irATabGeneral();
            return;
        }
        if (!document.getElementById('alu_id_cliente').value) {
            aviso('warning', 'Debe seleccionar el cliente que factura al alumno (pestaña Facturación).');
            const btn = document.getElementById('tab-facturacion-btn');
            if (btn && typeof bootstrap !== 'undefined') (bootstrap.Tab.getInstance(btn) || new bootstrap.Tab(btn)).show();
            return;
        }

        serializarTablas();

        const id = document.getElementById('alu_id').value;
        const actionUrl = id ? `${urlBase}/update` : `${urlBase}/store`;
        const btn = document.getElementById('btnGuardarAlumnoModal');

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

        try {
            const fd = new FormData(form);
            const resp = await fetch(actionUrl, { method: 'POST', body: fd });
            const json = await resp.json();
            if (!json.ok) {
                aviso('error', json.error || json.msg || 'No se pudo guardar el alumno.');
            } else {
                if (!id && json.id) {
                    idAlumnoActual = json.id;
                    document.getElementById('alu_id').value = json.id;
                    document.getElementById('btnEliminarAlumnoModal').classList.remove('d-none');
                    setAdjuntosHabilitados(true);
                    renderDocumentos([]);
                }
                await aviso('success', json.msg || 'Alumno guardado correctamente.', { timer: 1400, showConfirmButton: false });
                getModal()?.hide();
                if (window.fetchSearchAlumnos) window.fetchSearchAlumnos();
            }
        } catch (e) {
            aviso('error', 'Error de conexión con el servidor.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Guardar';
        }
    };

    window.eliminarAlumnoModal = async function () {
        const id = document.getElementById('alu_id')?.value;
        if (!id || !(await confirmar('Se eliminará este alumno.', 'Sí, eliminar', true))) return;
        const btn = document.getElementById('btnEliminarAlumnoModal');

        if (btn) btn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('id_eliminar', id);
            const resp = await fetch(`${urlBase}/delete`, { method: 'POST', body: fd });
            const json = await resp.json();
            if (json.ok) {
                getModal()?.hide();
                if (window.fetchSearchAlumnos) window.fetchSearchAlumnos();
                aviso('success', 'Alumno eliminado.', { timer: 1400, showConfirmButton: false });
            } else {
                aviso('error', json.error || 'No se pudo eliminar.');
            }
        } catch (e) { aviso('error', 'Error de conexión al eliminar el alumno.'); } finally { if (btn) btn.disabled = false; }
    };


})(window, document);
