(function () {
    'use strict';

    // ── Estado ──────────────────────────────────────────────────────────────────
    let modalRetV;
    let lineasData   = [];
    let currentSort  = (typeof window.RETV_ordenCol !== 'undefined' && window.RETV_ordenCol) ? window.RETV_ordenCol : 'fecha_emision';
    let currentDir   = (typeof window.RETV_ordenDir !== 'undefined' && window.RETV_ordenDir) ? window.RETV_ordenDir : 'DESC';
    let retvIdActual = 0;
    let retvHasXml   = false;
    let comprobantesAutorizados = [];

    const BASE = window.RETV_rutaBase || (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/modulos/retenciones_ventas';

    // ── Init ─────────────────────────────────────────────────────────────────────

    // Cargar comprobantes autorizados de inmediato
    fetchComprobantesAutorizados();

    document.addEventListener('DOMContentLoaded', () => {
        initModal();

        // Búsqueda predictiva de clientes
        const clienteUrl = BASE + '/getClientesAjax';
        busquedaPredictiva('retv_cliente_search', 'retv_cliente_dropdown', clienteUrl, (item) => {
            document.getElementById('retv_id_cliente').value       = item.id;
            document.getElementById('retv_cliente_search').value   = item.nombre || item.razon_social;
            mostrarInfoCliente(item);
            document.getElementById('retv_cliente_dropdown').classList.add('d-none');
        });

        // (Ya se está cargando arriba)

        // Wrapper para abrir modal de nuevo cliente (requiere clientes_modal.js)
        window.abrirModalCrearCliente = () => {
            if (typeof abrirModalClienteCrear === 'function') {
                abrirModalClienteCrear();
            }
        };

        // Búsqueda en listado
        const buscarEl = document.getElementById('buscarRetV');
        if (buscarEl) {
            buscarEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); RETV_fetchSearch(1); }
            });
        }
    });

    function initModal() {
        const el = document.getElementById('modalRetencionVenta');
        if (el && typeof bootstrap !== 'undefined' && !modalRetV) {
            modalRetV = new bootstrap.Modal(el);
        }
    }

    // ── MÁSCARA COMPARTIDA (000-000-000000000) ───────────────────────────────

    function aplicarMascara(input) {
        let v = input.value.replace(/\D/g, '');
        if (v.length > 15) v = v.slice(0, 15);
        let res = '';
        if (v.length > 0)  res  = v.slice(0, 3);
        if (v.length > 3)  res += '-' + v.slice(3, 6);
        if (v.length > 6)  res += '-' + v.slice(6, 15);
        input.value = res;
    }

    function normalizarMascara(input) {
        const raw = input.value.replace(/\D/g, '');
        if (!raw) return;
        const p1 = raw.slice(0, 3).padStart(3, '0');
        const p2 = raw.slice(3, 6).padStart(3, '0');
        const sec = raw.slice(6, 15);
        const p3  = sec ? sec.padStart(9, '0') : '000000000';
        input.value = `${p1}-${p2}-${p3}`;
    }

    // ── MÁSCARA NÚMERO RETENCIÓN (000-000-000000000) ──────────────────────────

    window.RETV_aplicarMascaraNumero = (input) => {
        aplicarMascara(input);
        actualizarHiddenNumero(input.value);
    };

    window.RETV_normalizarNumero = (input) => {
        normalizarMascara(input);
        actualizarHiddenNumero(input.value);
    };

    window.RETV_aplicarMascaraDocSustento = (input, idx) => {
        aplicarMascara(input);
        if (lineasData[idx] !== undefined) lineasData[idx].cod_doc_sustento = input.value;
    };

    window.RETV_aplicarMascaraDocSustentoManual = (input, idx) => {
        aplicarMascara(input);
        if (lineasData[idx] !== undefined) lineasData[idx].num_doc_sustento = input.value;
    };

    window.RETV_normalizarDocSustentoManual = (input, idx) => {
        normalizarMascara(input);
        if (lineasData[idx] !== undefined) lineasData[idx].num_doc_sustento = input.value;
    };

    function actualizarHiddenNumero(valor) {
        const parts = valor.split('-');
        const estab = document.getElementById('retv_establecimiento');
        const pto   = document.getElementById('retv_punto_emision');
        const sec   = document.getElementById('retv_secuencial');
        if (estab) estab.value = parts[0] || '';
        if (pto)   pto.value   = parts[1] || '';
        if (sec)   sec.value   = parts[2] || '';
    }

    async function fetchComprobantesAutorizados() {
        if (comprobantesAutorizados.length > 0) return;
        try {
            const res = await fetch(`${BASE}/getComprobantesAutorizadosAjax?_=${Date.now()}`);
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            const resp = await res.json();
            if (resp.ok) {
                comprobantesAutorizados = resp.data || [];
                // Refrescar si hay algo visible
                const modalEl = document.getElementById('modalRetencionVenta');
                if (modalEl && modalEl.classList.contains('show') && lineasData.length > 0) {
                    renderLineas();
                }
            } else {
                console.error('Error en respuesta:', resp.mensaje);
            }
        } catch (e) {
            console.error('Error cargando comprobantes autorizados:', e);
        }
    }

    // ── LISTADO ──────────────────────────────────────────────────────────────────

    window.RETV_cambiarPagina = (page) => window.RETV_fetchSearch(page);

    window.RETV_ordenar = (col) => {
        currentDir  = (currentSort === col && currentDir === 'ASC') ? 'DESC' : 'ASC';
        currentSort = col;
        if (typeof window.guardarOrdenacionVista === 'function') {
            window.guardarOrdenacionVista('retenciones_ventas', currentSort, currentDir);
        }
        window.RETV_fetchSearch(1);
    };

    window.RETV_fetchSearch = async (page = 1) => {
        const buscar = (document.getElementById('buscarRetV') || {}).value || '';
        const url = `${BASE}/searchAjax?b=${encodeURIComponent(buscar)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
        try {
            const res  = await fetch(url);
            const data = await res.json();
            if (data.ok) {
                document.getElementById('retv-table-body').innerHTML        = data.rows;
                document.getElementById('retv-pagination').innerHTML        = data.pagination;
                document.getElementById('retv-pagination-info').textContent = data.info;
            }
        } catch (e) {
            console.error('Error buscando retenciones de ventas:', e);
        }
    };

    // ── MODAL — ABRIR ─────────────────────────────────────────────────────────────

    /* ── Pestaña Asiento Contable ─────────────────────────────── */
    function escHtmlRetv(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    async function cargarAsientoContable(id) {
        const tbody   = document.getElementById('retv_asiento_body');
        const tdDebe  = document.getElementById('retv_asiento_total_debe');
        const tdHaber = document.getElementById('retv_asiento_total_haber');
        const aviso   = document.getElementById('retv_asiento_aviso');
        if (!tbody) return;

        const setTot = (d, h) => {
            if (tdDebe)  tdDebe.textContent  = d.toFixed(2);
            if (tdHaber) tdHaber.textContent = h.toFixed(2);
        };

        if (!id) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Guarde la retención para generar el asiento contable.</td></tr>';
            setTot(0, 0);
            if (aviso) aviso.innerHTML = '';
            return;
        }

        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Cargando asiento...</td></tr>';
        try {
            const res  = await fetch(`${BASE}/getAsientoContableAjax?id=${id}`);
            const resp = await res.json();
            const dets = (resp.ok && resp.detalles) ? resp.detalles : [];

            if (!dets.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Sin asiento. Configure las cuentas contables de retenciones en Configuración Contable.</td></tr>';
                setTot(0, 0);
                if (aviso) aviso.innerHTML = '';
                return;
            }

            let totDebe = 0, totHaber = 0;
            tbody.innerHTML = dets.map(d => {
                const debe  = parseFloat(d.debe  || 0);
                const haber = parseFloat(d.haber || 0);
                totDebe += debe; totHaber += haber;
                return `<tr>
                    <td class="ps-3 small"><code class="text-secondary">${escHtmlRetv(d.cuenta_codigo || '')}</code></td>
                    <td class="small">${escHtmlRetv(d.cuenta_nombre || '')}</td>
                    <td class="small text-end">${debe  > 0 ? debe.toFixed(2)  : ''}</td>
                    <td class="small text-end pe-3">${haber > 0 ? haber.toFixed(2) : ''}</td>
                </tr>`;
            }).join('');
            setTot(totDebe, totHaber);

            if (aviso) {
                const descuadre = Math.abs(totDebe - totHaber) > 0.001;
                if (descuadre) {
                    aviso.innerHTML = '<span class="text-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>El asiento está descuadrado. Revise la configuración de cuentas contables de retenciones.</span>';
                } else if (resp.registrado) {
                    const num = resp.numero ? ` N° ${escHtmlRetv(resp.numero)}` : '';
                    aviso.innerHTML = `<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Asiento registrado en contabilidad${num}.</span>`;
                } else {
                    aviso.innerHTML = '<span class="text-warning"><i class="fa-solid fa-circle-info me-1"></i>Asiento sugerido (configure las cuentas para registrarlo).</span>';
                }
            }
        } catch (e) {
            console.error(e);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Error al cargar el asiento contable.</td></tr>';
        }
    }

    window.RETV_abrirModalNuevo = () => {
        initModal();
        retvIdActual = 0;
        retvHasXml   = false;
        lineasData   = [];
        resetForm();
        setTitulo('Nueva Retención Recibida', true);
        toggleBotones(false);
        // Ocultar botón XML al crear nuevo
        const btnXml = document.getElementById('retv-btn-descargar-xml');
        if (btnXml) btnXml.classList.add('d-none');

        const d   = new Date();
        const hoy = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

        const fEmision = document.getElementById('retv_fecha_emision');
        if (fEmision) fEmision.value = hoy;
        window.RETV_actualizarPeriodoFiscal(hoy);

        calcTotales();
        cargarAsientoContable(0);
        modalRetV && modalRetV.show();
        if (typeof window.aplicarFavoritosModal === 'function') window.aplicarFavoritosModal('#modalRetencionVenta');
    };

    window.RETV_abrirModal = async (row) => {
        initModal();
        resetForm();
        const data = JSON.parse(row.dataset.row);
        retvIdActual = data.id;

        try {
            const res  = await fetch(`${BASE}/getByIdAjax?id=${data.id}`);
            const resp = await res.json();
            if (!resp.ok) { mostrarAlerta(resp.mensaje || 'Error al cargar retención', 'danger'); return; }

            const cab    = resp.cabecera;
            const lineas = resp.lineas || [];

            cargarCabecera(cab);
            lineasData = lineas.map(l => ({
                ...l,
                concepto: l.concepto || l.sri_concepto || '',
            }));
            renderLineas();
            calcTotales();

            setTitulo(`Retención ${cab.establecimiento}-${cab.punto_emision}-${cab.secuencial}`, false);
            toggleBotones(true, cab);

            // Mostrar/ocultar botón XML según si el registro tiene XML almacenado
            retvHasXml = !!(cab.detalle_xml && cab.detalle_xml.trim().length > 0);
            const btnXml = document.getElementById('retv-btn-descargar-xml');
            if (btnXml) btnXml.classList.toggle('d-none', !retvHasXml);

            modalRetV && modalRetV.show();
            cargarAsientoContable(retvIdActual);
            if (typeof window.aplicarFavoritosModal === 'function') window.aplicarFavoritosModal('#modalRetencionVenta');
        } catch (e) {
            console.error(e);
            mostrarAlerta('Error al cargar datos de la retención.', 'danger');
        }
    };

    // ── MODAL — DESCARGAR XML ────────────────────────────────────────────────────

    window.RETV_descargarXml = () => {
        if (!retvIdActual || !retvHasXml) {
            mostrarAlerta('Este registro no tiene XML disponible.', 'warning');
            return;
        }
        window.open(`${BASE}/descargarXmlAjax?id=${retvIdActual}`, '_blank');
    };

    // ── MODAL — DESCARGAR PDF ────────────────────────────────────────────────────

    window.RETV_descargarPdf = () => {
        if (!retvIdActual) {
            mostrarAlerta('Guarde la retención antes de generar el PDF.', 'warning');
            return;
        }
        window.open(`${BASE}/exportPdfDoc?id=${retvIdActual}`, '_blank');
    };

    // ── MODAL — GUARDAR ─────────────────────────────────────────────────────────

    window.RETV_guardar = async () => {
        if (lineasData.length === 0) {
            mostrarAlerta('Debe agregar al menos una línea de retención.', 'warning');
            return;
        }

        // Normalizar número antes de guardar
        const numInput = document.getElementById('retv_numero_retencion');
        if (numInput) window.RETV_normalizarNumero(numInput);

        const payload = recopilarFormulario();

        if (!payload.fecha_emision) {
            mostrarAlerta('La fecha de emisión es obligatoria.', 'warning');
            return;
        }
        if (!payload.id_cliente) {
            mostrarAlerta('El cliente es obligatorio.', 'warning');
            return;
        }
        if (!payload.secuencial) {
            mostrarAlerta('El número de retención es obligatorio.', 'warning');
            return;
        }

        const btn = document.getElementById('retv-btn-guardar');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...'; }

        try {
            const res  = await fetch(`${BASE}/guardarAjax`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    'data=' + encodeURIComponent(JSON.stringify(payload)),
            });
            const data = await res.json();
            if (data.ok) {
                mostrarAlerta(data.mensaje, 'success');
                retvIdActual = data.id || retvIdActual;
                if (modalRetV) modalRetV.hide();
                setTimeout(() => RETV_fetchSearch(1), 300);
            } else {
                mostrarAlerta(data.mensaje || 'Error al guardar.', 'danger');
            }
        } catch (e) {
            mostrarAlerta('Error de red al guardar.', 'danger');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Guardar'; }
        }
    };

    // ── MODAL — ELIMINAR ─────────────────────────────────────────────────────────

    window.RETV_eliminar = async () => {
        if (!retvIdActual) return;
        const ok = await confirmar('¿Eliminar esta retención?', 'Esta acción no se puede deshacer.');
        if (!ok) return;

        try {
            const res  = await fetch(`${BASE}/eliminarAjax`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    `id=${retvIdActual}`,
            });
            const data = await res.json();
            if (data.ok) {
                mostrarAlerta(data.mensaje, 'success');
                modalRetV && modalRetV.hide();
                setTimeout(() => RETV_fetchSearch(1), 400);
            } else {
                mostrarAlerta(data.mensaje, 'danger');
            }
        } catch (e) {
            mostrarAlerta('Error al eliminar.', 'danger');
        }
    };

    // ── LÍNEAS DE RETENCIÓN ───────────────────────────────────────────────────────

    window.RETV_agregarLinea = () => {
        lineasData.push({
            cod_doc_sustento:           '01',
            num_doc_sustento:           '',
            fecha_emision_doc_sustento: '',
            codigo_impuesto:            '1',
            codigo_retencion:           '',
            concepto:                   '',
            base_imponible:             '',
            porcentaje_retencion:       '',
            valor_retenido:             0,
        });
        renderLineas();
        const tbody   = document.getElementById('retv_lineas_body');
        const lastRow = tbody && tbody.querySelector('tr:last-child');
        lastRow && lastRow.querySelector('input')?.focus();
    };

    window.RETV_eliminarLinea = (idx) => {
        lineasData.splice(idx, 1);
        renderLineas();
        calcTotales();
    };

    let searchTimer;
    window.RETV_onLineCambio = (idx, campo, valor) => {
        if (!lineasData[idx]) return;
        lineasData[idx][campo] = valor;

        if (campo === 'codigo_retencion') {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => window.RETV_buscarCodigoSri(idx, 'codigo'), 300);
        }
        if (campo === 'concepto') {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => window.RETV_buscarCodigoSri(idx, 'concepto'), 300);
        }

        if (campo === 'base_imponible' || campo === 'porcentaje_retencion') {
            const base = parseFloat(lineasData[idx]['base_imponible']       || 0);
            const pct  = parseFloat(lineasData[idx]['porcentaje_retencion'] || 0);
            lineasData[idx]['valor_retenido'] = Math.round(base * pct / 100 * 100) / 100;
            const tr = document.getElementById('retv_lineas_body').querySelectorAll('tr')[idx];
            if (tr) {
                const valEl = tr.querySelector('.retv-val-retenido');
                if (valEl) valEl.textContent = '$' + lineasData[idx]['valor_retenido'].toFixed(2);
            }
        }

        calcTotales();
    };

    window.RETV_buscarCodigoSri = async (idx, tipoBusqueda = 'codigo') => {
        const q       = tipoBusqueda === 'codigo' ? (lineasData[idx]?.codigo_retencion || '') : (lineasData[idx]?.concepto || '');
        const fEmision = document.getElementById('retv_fecha_emision')?.value || '';
        if (q.length < 1) return;
        const url = `${BASE}/getRetencionesSriAjax?q=${encodeURIComponent(q)}&fecha=${fEmision}`;
        try {
            const res  = await fetch(url);
            const data = await res.json();
            if (data.ok && data.data.length > 0) mostrarDropdownCodigos(idx, data.data, tipoBusqueda);
        } catch (e) { console.error(e); }
    };

    function mostrarDropdownCodigos(idx, items, tipoBusqueda) {
        const tr = document.getElementById('retv_lineas_body').querySelectorAll('tr')[idx];
        if (!tr) return;
        // td:nth-child(3) = Código, td:nth-child(4) = Concepto
        const input = tipoBusqueda === 'codigo'
            ? tr.querySelector('td:nth-child(3) input')
            : tr.querySelector('td:nth-child(4) input');
        if (!input) return;

        let drop = document.querySelector('.retv-cod-dropdown');
        if (drop) drop.remove();

        drop = document.createElement('div');
        drop.className = 'list-group shadow position-fixed retv-cod-dropdown';
        const rect = input.getBoundingClientRect();
        drop.style.cssText = `z-index:10000;width:580px;max-height:250px;overflow-y:auto;top:${rect.bottom}px;left:${rect.left}px;`;
        document.body.appendChild(drop);

        items.forEach(item => {
            const btn     = document.createElement('button');
            btn.type      = 'button';
            btn.className = 'list-group-item list-group-item-action small py-1';
            const isIva   = (item.impuesto_ret == '2');
            const isIsd   = (item.impuesto_ret == '6');
            const labelImp = isIva ? `IVA` : (isIsd ? `ISD` : `RENTA`);
            btn.innerHTML = `<div class="d-flex justify-content-between">
                <span><strong>${item.codigo_ret}</strong> — ${item.concepto_ret}</span>
                <span class="badge bg-light text-dark border ms-2">${labelImp} — ${item.porcentaje_ret}%</span>
            </div>`;
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                lineasData[idx].codigo_retencion    = item.codigo_ret;
                lineasData[idx].concepto            = item.concepto_ret;
                lineasData[idx].porcentaje_retencion = item.porcentaje_ret;
                lineasData[idx].codigo_impuesto     = item.impuesto_ret;

                const base = parseFloat(lineasData[idx].base_imponible || 0);
                const pct  = parseFloat(lineasData[idx].porcentaje_retencion || 0);
                lineasData[idx].valor_retenido = Math.round(base * pct / 100 * 100) / 100;

                renderLineas();
                calcTotales();
                drop.remove();

                setTimeout(() => {
                    const row = document.getElementById('retv_lineas_body').querySelectorAll('tr')[idx];
                    if (row) {
                        const inputBase = row.querySelector('input[oninput*="base_imponible"]');
                        if (inputBase) { inputBase.focus(); inputBase.select(); }
                    }
                }, 100);
            });
            drop.appendChild(btn);
        });

        const closeDrop = (e) => {
            if (!input.contains(e.target) && !drop.contains(e.target)) {
                drop.remove();
                document.removeEventListener('click', closeDrop);
            }
        };
        setTimeout(() => document.addEventListener('click', closeDrop), 10);
    }

    function renderLineas() {
        const tbody = document.getElementById('retv_lineas_body');
        if (!tbody) return;

        if (lineasData.length === 0) {
            tbody.innerHTML = `<tr id="retv_lineas_empty"><td colspan="10" class="text-center py-4 text-muted">
                <i class="fa-regular fa-file-lines d-block mb-1"></i>Agregue al menos una línea de retención.</td></tr>`;
            return;
        }

        const soloLectura = !!window._RETV_soloLectura;
        const ro          = soloLectura ? 'disabled' : '';

        const impLabel = (cod) => {
            const c = String(cod || '').toUpperCase().trim();
            if (c === 'IVA'   || c === '2') return '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 small">IVA</span>';
            if (c === 'ISD'   || c === '6' || c === '3') return '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 small">ISD</span>';
            return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">RENTA</span>';
        };


        tbody.innerHTML = lineasData.map((l, i) => {
            let options = comprobantesAutorizados.map(c => 
                `<option value="${c.codigo_comprobante}" ${l.cod_doc_sustento == c.codigo_comprobante ? 'selected' : ''}>${c.codigo_comprobante} - ${c.comprobante}</option>`
            ).join('');

            if (!options) {
                options = l.cod_doc_sustento 
                    ? `<option value="${l.cod_doc_sustento}" selected>${l.cod_doc_sustento}</option>`
                    : '<option value="">Cargando...</option>';
            }

            return `
            <tr>
                <!-- Tipo Comprobante -->
                <td class="p-0">
                    <select class="form-select form-select-sm border-0 bg-transparent"
                            style="padding:0 4px;height:28px;font-size:0.75rem;"
                            onchange="window.RETV_onLineCambio(${i},'cod_doc_sustento',this.value)" ${ro}>
                        ${options}
                    </select>
                </td>
                <!-- Doc. Sustento -->
                <td class="p-0">
                    <input type="text" class="form-control form-control-sm border-0 bg-transparent font-monospace"
                           style="padding:0 4px;height:28px;font-size:0.78rem;" placeholder="000-000-000000000" maxlength="17"
                           value="${escHtml(l.num_doc_sustento || l.num_comprobante || '')}" ${ro}
                           oninput="window.RETV_aplicarMascaraDocSustentoManual(this,${i})"
                           onblur="window.RETV_normalizarDocSustentoManual(this,${i})">
                </td>
                <!-- Fecha Doc. -->
                <td class="p-0">
                    <input type="date" class="form-control form-control-sm border-0 bg-transparent"
                           style="padding:0 4px;height:28px;font-size:0.78rem;"
                           value="${escHtml(l.fecha_emision_doc_sustento || '')}" ${ro}
                           oninput="window.RETV_onLineCambio(${i},'fecha_emision_doc_sustento',this.value)">
                </td>
                <!-- Código Retención -->
                <td class="p-0" style="position:relative;">
                    <input type="text" class="form-control form-control-sm border-0 bg-transparent"
                           style="padding:0 4px;height:28px;font-size:0.78rem;" placeholder="Cód."
                           value="${escHtml(l.codigo_retencion)}" ${ro}
                           oninput="window.RETV_onLineCambio(${i},'codigo_retencion',this.value)"
                           onfocus="${soloLectura ? '' : `window.RETV_buscarCodigoSri(${i},'codigo')`}">
                </td>
                <!-- Concepto -->
                <td class="p-0" style="position:relative;">
                    <input type="text" class="form-control form-control-sm border-0 bg-transparent"
                           style="padding:0 4px;height:28px;font-size:0.78rem;" placeholder="Concepto..."
                           value="${escHtml(l.concepto || '')}" ${ro}
                           oninput="window.RETV_onLineCambio(${i},'concepto',this.value)"
                           onfocus="${soloLectura ? '' : `window.RETV_buscarCodigoSri(${i},'concepto')`}">
                </td>
                <!-- Impuesto -->
                <td class="p-0 align-middle text-center" style="font-size:0.75rem;">
                    ${impLabel(l.codigo_impuesto)}
                </td>
                <!-- Base Imponible -->
                <td class="p-0">
                    <input type="number" class="form-control form-control-sm border-0 bg-transparent text-end fw-bold"
                           style="padding:0 8px;height:28px;font-size:0.85rem;" placeholder="0.00" step="0.01" min="0"
                           value="${parseFloat(l.base_imponible || 0).toFixed(2)}" ${ro}
                           oninput="window.RETV_onLineCambio(${i},'base_imponible',this.value)"
                           onblur="this.value=parseFloat(this.value||0).toFixed(2)">
                </td>
                <!-- % Retención -->
                <td class="p-0">
                    <input type="number" class="form-control form-control-sm border-0 bg-transparent text-center"
                           style="padding:0 4px;height:28px;font-size:0.78rem;" placeholder="0" step="0.01" min="0" max="100"
                           value="${l.porcentaje_retencion}" ${ro}
                           oninput="window.RETV_onLineCambio(${i},'porcentaje_retencion',this.value)">
                </td>
                <!-- Valor Retenido -->
                <td class="p-0 text-end">
                    <span class="retv-val-retenido small fw-bold text-danger px-2">$${parseFloat(l.valor_retenido || 0).toFixed(2)}</span>
                </td>
                <!-- Eliminar -->
                <td class="p-0 text-center">
                    ${soloLectura ? '' : `<button type="button" class="btn btn-link btn-sm text-danger p-0" onclick="window.RETV_eliminarLinea(${i})" title="Eliminar línea">
                        <i class="fa-solid fa-trash" style="font-size:0.75rem;"></i>
                    </button>`}
                </td>
            </tr>`;
        }).join('');

        const countEl = document.getElementById('retv_count_items');
        if (countEl) countEl.textContent = lineasData.length;
    }

    // ── TOTALES ──────────────────────────────────────────────────────────────────

    function calcTotales() {
        let totalRenta = 0, totalIva = 0, totalIsd = 0;

        lineasData.forEach(l => {
            const valor  = parseFloat(l.valor_retenido || 0);
            const codImp = String(l.codigo_impuesto || '');
            if      (codImp === '1' || codImp === 'RENTA') totalRenta += valor;
            else if (codImp === '2' || codImp === 'IVA')   totalIva   += valor;
            else if (codImp === '6' || codImp === 'ISD')   totalIsd   += valor;
            else                                            totalRenta += valor;
        });

        const totalRet = totalRenta + totalIva + totalIsd;

        const set = (id, v) => {
            const el = document.getElementById(id);
            if (el) el[el.tagName === 'INPUT' ? 'value' : 'textContent'] = typeof v === 'number' ? v.toFixed(2) : v;
        };

        set('retv_lbl_renta',  '$' + totalRenta.toFixed(2));
        set('retv_lbl_iva',    '$' + totalIva.toFixed(2));
        set('retv_lbl_isd',    '$' + totalIsd.toFixed(2));
        set('retv_lbl_total',  '$' + totalRet.toFixed(2));
        set('retv_total_renta', totalRenta.toFixed(2));
        set('retv_total_iva',   totalIva.toFixed(2));
        set('retv_total_isd',   totalIsd.toFixed(2));
    }

    // ── PERÍODO FISCAL ───────────────────────────────────────────────────────────

    window.RETV_actualizarPeriodoFiscal = (fecha) => {
        if (!fecha) return;
        const [y, m] = fecha.split('-');
        const el = document.getElementById('retv_periodo_fiscal');
        if (el) el.value = `${m}/${y}`;
    };

    // ── CARGAR DATOS ─────────────────────────────────────────────────────────────

    function cargarCabecera(cab) {
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };

        set('retv_id',            cab.id);
        set('retv_id_venta',      cab.id_venta || '');
        set('retv_origen',        cab.origen || 'manual');
        set('retv_fecha_emision', cab.fecha_emision || '');
        set('retv_periodo_fiscal', cab.periodo_fiscal || '');

        // Hidden fields
        set('retv_establecimiento', cab.establecimiento || '');
        set('retv_punto_emision',   cab.punto_emision   || '');
        set('retv_secuencial',      String(cab.secuencial || '').padStart(9, '0'));

        // Campo visible número de retención
        const numEl = document.getElementById('retv_numero_retencion');
        if (numEl) {
            numEl.value = (cab.establecimiento || '').padStart(3,'0') + '-' +
                          (cab.punto_emision   || '').padStart(3,'0') + '-' +
                          String(cab.secuencial || '').padStart(9,'0');
        }

        // Cliente
        set('retv_id_cliente', cab.id_cliente || '');
        const clienteEl = document.getElementById('retv_cliente_search');
        if (clienteEl) clienteEl.value = cab.cliente_nombre || '';

        const lblRuc = document.getElementById('retv_lbl_cliente_ruc');
        const lblDir = document.getElementById('retv_lbl_cliente_direccion');
        if (lblRuc) lblRuc.textContent = cab.cliente_identificacion || '—';
        if (lblDir) lblDir.textContent = cab.cliente_direccion || '—';
        const infoCliente = document.getElementById('retv_cliente_info');
        if (infoCliente) infoCliente.classList.remove('d-none');

        // Clave de acceso si es electrónica
        const accessKeyCont = document.getElementById('retv_access_key_container');
        const accessKeyEl   = document.getElementById('retv_access_key');
        if (accessKeyCont && accessKeyEl) {
            if (cab.origen === 'electronico' && cab.clave_acceso) {
                accessKeyEl.textContent = cab.clave_acceso;
                accessKeyCont.classList.remove('d-none');
            } else {
                accessKeyCont.classList.add('d-none');
                accessKeyEl.textContent = '';
            }
        }
    }

    // ── TOGGLES ──────────────────────────────────────────────────────────────────

    function toggleBotones(tieneId, cab = {}) {
        const esElectronica = (cab.origen || '') === 'electronico';
        const btnGuardar    = document.getElementById('retv-btn-guardar');
        const btnEliminar   = document.getElementById('retv-btn-eliminar');
        const btnNuevoCliente = document.querySelector('[onclick="window.abrirModalCrearCliente()"]');

        if (btnGuardar)  btnGuardar.classList.toggle('d-none', esElectronica);
        if (btnEliminar) btnEliminar.classList.toggle('d-none', !tieneId);
        if (btnNuevoCliente) btnNuevoCliente.classList.toggle('d-none', esElectronica);

        // Botón PDF: disponible para cualquier retención ya guardada.
        const btnPdf = document.getElementById('retv-btn-pdf');
        if (btnPdf) btnPdf.classList.toggle('d-none', !tieneId);

        // Campos del encabezado
        const camposHeader = [
            'retv_fecha_emision', 'retv_numero_retencion', 'retv_cliente_search',
        ];
        camposHeader.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = esElectronica;
        });

        // Botón agregar línea
        const btnAgregar = document.querySelector('[onclick="window.RETV_agregarLinea()"]');
        if (btnAgregar) btnAgregar.classList.toggle('d-none', esElectronica);

        // Botones eliminar línea dentro de la tabla (se aplica en renderLineas vía flag global)
        window._RETV_soloLectura = esElectronica;
        renderLineas();
    }

    // ── HELPERS ──────────────────────────────────────────────────────────────────

    function recopilarFormulario() {
        const get = (id) => (document.getElementById(id) || {}).value || '';
        return {
            id:              retvIdActual || '',
            id_venta:        get('retv_id_venta') || null,
            origen:          get('retv_origen') || 'manual',
            fecha_emision:   get('retv_fecha_emision'),
            establecimiento: get('retv_establecimiento'),
            punto_emision:   get('retv_punto_emision'),
            secuencial:      get('retv_secuencial'),
            periodo_fiscal:  get('retv_periodo_fiscal'),
            id_cliente:      get('retv_id_cliente'),
            total_renta:     get('retv_total_renta'),
            total_iva:       get('retv_total_iva'),
            total_isd:       get('retv_total_isd'),
            lineas:          lineasData.map(l => ({
                ...l,
                num_doc_sustento: l.num_doc_sustento || l.num_comprobante || ''
            })),
        };
    }

    function resetForm() {
        window._RETV_soloLectura = false;
        const form = document.getElementById('formRetencionVenta');
        if (form) form.reset();
        document.getElementById('retv_id').value = '';
        retvIdActual = 0;
        lineasData   = [];
        renderLineas();

        // Limpiar número visible
        const numEl = document.getElementById('retv_numero_retencion');
        if (numEl) numEl.value = '';

        const clienteSearch = document.getElementById('retv_cliente_search');
        if (clienteSearch) clienteSearch.value = '';
        const idCliente = document.getElementById('retv_id_cliente');
        if (idCliente) idCliente.value = '';
        const infoCliente = document.getElementById('retv_cliente_info');
        if (infoCliente) infoCliente.classList.add('d-none');

        const accessKeyCont = document.getElementById('retv_access_key_container');
        if (accessKeyCont) accessKeyCont.classList.add('d-none');
        const accessKeyEl = document.getElementById('retv_access_key');
        if (accessKeyEl) accessKeyEl.textContent = '';
    }

    function mostrarInfoCliente(item) {
        const el = document.getElementById('retv_cliente_info');
        if (!el) return;
        const lblRuc = document.getElementById('retv_lbl_cliente_ruc');
        const lblDir = document.getElementById('retv_lbl_cliente_direccion');
        if (lblRuc) lblRuc.textContent = item.identificacion || item.ruc || '—';
        if (lblDir) lblDir.textContent = item.direccion || '—';
        el.classList.remove('d-none');
    }

    function setTitulo(texto) {
        const el = document.getElementById('modalRetvTitulo');
        if (el) el.innerHTML = `<i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>${escHtml(texto)}`;
    }

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function mostrarAlerta(msg, tipo = 'info') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: tipo === 'danger' ? 'error' : tipo === 'warning' ? 'warning' : tipo === 'success' ? 'success' : 'info', text: msg, timer: 3000, showConfirmButton: false, toast: true, position: 'top-end' });
        } else {
            alert(msg);
        }
    }

    async function confirmar(titulo, texto = '') {
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({ title: titulo, text: texto, icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, continuar', cancelButtonText: 'Cancelar' });
            return result.isConfirmed;
        }
        return confirm(titulo + '\n' + texto);
    }

    function busquedaPredictiva(inputId, dropId, url, onSelect) {
        const input = document.getElementById(inputId);
        const drop  = document.getElementById(dropId);
        if (!input || !drop) return;

        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 2) { drop.classList.add('d-none'); drop.innerHTML = ''; return; }
            timer = setTimeout(async () => {
                try {
                    const res   = await fetch(`${url}?q=${encodeURIComponent(q)}`);
                    const data  = await res.json();
                    const items = data.data || data.rows || [];
                    if (!items.length) { drop.classList.add('d-none'); return; }
                    drop.innerHTML = items.map((item) => {
                        const label = item.nombre || item.razon_social || '';
                        const sub   = item.identificacion || item.ruc || '';
                        return `<button type="button" class="list-group-item list-group-item-action py-1 small">
                            <strong>${escHtml(label)}</strong>${sub ? `<br><small class="text-muted">${escHtml(sub)}</small>` : ''}
                        </button>`;
                    }).join('');
                    drop.querySelectorAll('button').forEach((btn, i) => {
                        btn.addEventListener('click', () => onSelect(items[i]));
                    });
                    drop.classList.remove('d-none');
                } catch (e) { console.error(e); }
            }, 300);
        });

        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !drop.contains(e.target)) drop.classList.add('d-none');
        });
    }

})();
