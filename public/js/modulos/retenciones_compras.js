(function () {
    'use strict';

    // ── Estado ──────────────────────────────────────────────────────────────────
    let modalRet;
    let lineasData = [];   // [{codigo_impuesto, codigo_retencion, concepto, base_imponible, porcentaje_retener, valor_retenido}]
    let currentSort = 'fecha_emision';
    let currentDir  = 'DESC';
    let retIdActual = 0;

    const BASE = window.RET_rutaBase || (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/modulos/retenciones_compras';

    // ── Init ─────────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', () => {
        initModal();

        // Buscar proveedor
        const provUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/modulos/proveedores/getProveedoresAjax';
        busquedaPredictiva('ret_proveedor_search', 'ret_proveedor_dropdown', provUrl, (item) => {
            document.getElementById('ret_id_proveedor').value = item.id;
            document.getElementById('ret_proveedor_search').value = item.razon_social || item.nombre;
            mostrarInfoProveedor(item);
            document.getElementById('ret_proveedor_dropdown').classList.add('d-none');
        });

        // Aplicar máscara al número de documento
        const numDocInput = document.getElementById('ret_num_doc_sustento');
        if (numDocInput) {
            numDocInput.addEventListener('input', (e) => {
                let v = e.target.value.replace(/\D/g, '');
                if (v.length > 15) v = v.slice(0, 15);
                let res = '';
                if (v.length > 0) res += v.slice(0, 3);
                if (v.length > 3) res += '-' + v.slice(3, 6);
                if (v.length > 6) res += '-' + v.slice(6, 15);
                e.target.value = res;
            });

            numDocInput.addEventListener('blur', (e) => {
                let parts = e.target.value.split('-');
                if (parts.length === 1 && parts[0].length > 0) {
                    // Si solo escribió números sin guiones
                    let v = parts[0];
                    if (v.length <= 9) {
                        e.target.value = `001-001-${v.padStart(9, '0')}`;
                    }
                } else if (parts.length === 3) {
                    let p1 = parts[0].padStart(3, '0');
                    let p2 = parts[1].padStart(3, '0');
                    let p3 = parts[2].padStart(9, '0');
                    e.target.value = `${p1}-${p2}-${p3}`;
                }
            });
        }



        // Buscar input
        const buscarEl = document.getElementById('buscarRet');
        if (buscarEl) {
            buscarEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); RET_fetchSearch(1); }
            });
        }
    });

    function initModal() {
        const el = document.getElementById('modalRetencion');
        if (el && typeof bootstrap !== 'undefined' && !modalRet) {
            modalRet = new bootstrap.Modal(el);
        }
    }

    // ── LISTADO ──────────────────────────────────────────────────────────────────

    window.RET_cambiarPagina = (page) => window.RET_fetchSearch(page);

    window.RET_ordenar = (col) => {
        currentDir  = (currentSort === col && currentDir === 'ASC') ? 'DESC' : 'ASC';
        currentSort = col;
        window.RET_fetchSearch(1);
    };

    window.RET_fetchSearch = async (page = 1) => {
        const buscar = (document.getElementById('buscarRet') || {}).value || '';
        const url = `${BASE}/searchAjax?b=${encodeURIComponent(buscar)}&page=${page}&sort=${currentSort}&dir=${currentDir}`;
        try {
            const res  = await fetch(url);
            const data = await res.json();
            if (data.ok) {
                document.getElementById('ret-table-body').innerHTML    = data.rows;
                document.getElementById('ret-pagination').innerHTML    = data.pagination;
                document.getElementById('ret-pagination-info').textContent = data.info;
            }
        } catch (e) {
            console.error('Error buscando retenciones:', e);
        }
    };

    // ── MODAL — ABRIR ─────────────────────────────────────────────────────────────

    window.RET_abrirModalNuevo = () => {
        initModal();
        retIdActual = 0;
        lineasData  = [];
        resetForm();
        setTitulo('Nueva Retención en Compras', true);
        toggleBotones(false);
        RET_cargarSecuencial();
        
        // Fecha actual por defecto (Local)
        const d = new Date();
        const hoy = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        
        const fEmision = document.getElementById('ret_fecha_emision');
        const fDoc = document.getElementById('ret_fecha_emision_doc_sustento');
        if (fEmision) fEmision.value = hoy;
        if (fDoc) fDoc.value = hoy;

        window.RET_actualizarPeriodoFiscal(hoy);
        limpiarSriTab();

        calcTotales();
        modalRet && modalRet.show();
        if (typeof window.aplicarFavoritosModal === 'function') window.aplicarFavoritosModal('#modalRetencion');
    };

    window.RET_abrirModal = async (row) => {
        initModal();
        resetForm();
        const data = JSON.parse(row.dataset.row);
        retIdActual = data.id;

        try {
            const res  = await fetch(`${BASE}/getByIdAjax?id=${data.id}`);
            const resp = await res.json();
            if (!resp.ok) { mostrarAlerta(resp.mensaje || 'Error al cargar retención', 'danger'); return; }

            const cab    = resp.cabecera;
            const lineas = resp.lineas || [];

            cargarCabecera(cab);
            lineasData = lineas.map(l => ({ ...l }));
            renderLineas();
            calcTotales();

            setTitulo(`Retención ${cab.establecimiento}-${cab.punto_emision}-${cab.secuencial}`, false);

            const esEditable = cab.estado === 'borrador';
            toggleBotones(true, esEditable, cab);
            cargarSriTab(cab);

            window.RET_ID_ACTIVO = retIdActual;

            modalRet && modalRet.show();
            if (typeof window.aplicarFavoritosModal === 'function') window.aplicarFavoritosModal('#modalRetencion');
        } catch (e) {
            console.error(e);
            mostrarAlerta('Error al cargar datos de la retención.', 'danger');
        }
    };

    // ── MODAL — GUARDAR ─────────────────────────────────────────────────────────

    window.RET_guardar = async () => {
        if (lineasData.length === 0) {
            mostrarAlerta('Debe agregar al menos una línea de retención.', 'warning');
            return;
        }
        const payload = recopilarFormulario();
        
        if (!payload.fecha_emision) {
            mostrarAlerta('La fecha de emisión es obligatoria.', 'warning');
            return;
        }

        // Validar diferencia de fechas (máximo 5 días y no anterior)
        if (payload.fecha_emision && payload.fecha_emision_doc_sustento) {
            const fRet = new Date(payload.fecha_emision + 'T00:00:00');
            const fDoc = new Date(payload.fecha_emision_doc_sustento + 'T00:00:00');
            
            if (fRet < fDoc) {
                mostrarAlerta('La fecha de la retención no puede ser anterior a la del documento retenido.', 'warning');
                return;
            }
            
            const diffTime = fRet - fDoc;
            const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
            if (diffDays > 5) {
                mostrarAlerta('La retención debe emitirse máximo 5 días después de la fecha de emisión de la compra.', 'warning');
                return;
            }
        }
        const url  = `${BASE}/guardarAjax`;

        const btn = document.getElementById('ret-btn-guardar');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...'; }

        try {
            const res  = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'data=' + encodeURIComponent(JSON.stringify(payload)),
            });
            const data = await res.json();
            if (data.ok) {
                mostrarAlerta(data.mensaje, 'success');
                retIdActual = data.id || retIdActual;
                
                // Cerrar modal
                if (modalRet) modalRet.hide();
                
                // Refrescar listado principal si existe
                if (typeof RET_fetchSearch === 'function') setTimeout(() => RET_fetchSearch(1), 300);
                
                // Refrescar listado en el modal de compras si existe la función global
                if (typeof window.CMG_cargarRetencionesCompra === 'function') {
                    setTimeout(() => window.CMG_cargarRetencionesCompra(), 400);
                }
            } else {
                mostrarAlerta(data.mensaje || 'Error al guardar.', 'danger');
            }
        } catch (e) {
            mostrarAlerta('Error de red al guardar.', 'danger');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-1"></i>Guardar'; }
        }
    };

    // ── MODAL — ELIMINAR / ANULAR ────────────────────────────────────────────────

    window.RET_eliminar = async () => {
        if (!retIdActual) return;
        const ok = await confirmar('¿Eliminar esta retención?', 'Esta acción no se puede deshacer.');
        if (!ok) return;

        try {
            const res  = await fetch(`${BASE}/eliminarAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${retIdActual}`,
            });
            const data = await res.json();
            if (data.ok) {
                mostrarAlerta(data.mensaje, 'success');
                modalRet && modalRet.hide();
                setTimeout(() => RET_fetchSearch(1), 400);
            } else {
                mostrarAlerta(data.mensaje, 'danger');
            }
        } catch (e) {
            mostrarAlerta('Error al eliminar.', 'danger');
        }
    };

    function retFechaLimiteAnulacion(fechaEmision) {
        const [y, m] = fechaEmision.split('-').map(Number);
        const mesLimite  = m === 12 ? 0 : m;
        const anioLimite = m === 12 ? y + 1 : y;
        let limite = new Date(anioLimite, mesLimite, 7);
        if (limite.getDay() === 6) limite.setDate(9); // sábado → lunes
        if (limite.getDay() === 0) limite.setDate(8); // domingo → lunes
        return limite;
    }

    window.RET_anular = async () => {
        if (!retIdActual) return;

        // ── Regla: Plazo del día 7 del mes siguiente ─────────────────────────
        if (window.RET_FECHA_EMISION) {
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const limiteAnulacion = retFechaLimiteAnulacion(window.RET_FECHA_EMISION);
            limiteAnulacion.setHours(23, 59, 59, 999);

            if (hoy > limiteAnulacion) {
                const fmtLimite = limiteAnulacion.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' });
                await Swal.fire({
                    icon: 'error',
                    title: 'Fuera de plazo',
                    html: `El plazo para anular esta retención venció el <strong>${fmtLimite}</strong>.<br>
                           <small class="text-muted">El SRI permite anular hasta el día 7 del mes siguiente a la emisión<br>
                           (o el siguiente día hábil si cae en fin de semana).</small>`,
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }
        }

        const result = await Swal.fire({
            title: '¿Anular esta retención?',
            text: 'Esta acción anulará el comprobante. No se podrá reactivar.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-slash-circle me-2"></i>Sí, anular',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        });

        if (!result.isConfirmed) return;

        const btn = document.getElementById('ret-btn-anular');
        const btnOrigHtml = btn?.innerHTML;
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Anulando...'; }

        try {
            const res = await fetch(`${BASE}/anularAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${retIdActual}`,
            });
            const data = await res.json();
            if (data.ok) {
                mostrarAlerta(data.mensaje, 'success');
                setTimeout(() => RET_fetchSearch(1), 400);
                const row = { dataset: { row: JSON.stringify({ id: retIdActual }) } };
                setTimeout(() => window.RET_abrirModal(row), 600);
            } else {
                mostrarAlerta(data.mensaje, 'danger');
            }
        } catch (e) {
            mostrarAlerta('Error al anular.', 'danger');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = btnOrigHtml; }
        }
    };

    // ── ENVIAR AL SRI ────────────────────────────────────────────────────────────

    window.RET_enviarSRI = async () => {
        if (!retIdActual) return;
        const ok = await confirmar('¿Enviar al SRI?', 'Se firmará y enviará la retención al SRI Ecuador.');
        if (!ok) return;

        const btn = document.getElementById('ret-btn-sri');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando...'; }

        try {
            const res  = await fetch(`${BASE}/autorizarSRIAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${retIdActual}`,
            });
            const data = await res.json();

            if (data.ok) {
                mostrarAlerta('✅ ' + data.mensaje, 'success');
            } else {
                mostrarAlerta('⚠️ ' + (data.mensaje || 'Error al enviar al SRI.'), 'warning');
            }

            // Recargar datos del modal
            setTimeout(() => {
                const row = { dataset: { row: JSON.stringify({ id: retIdActual }) } };
                window.RET_abrirModal(row);
                RET_fetchSearch(1);
            }, 800);
        } catch (e) {
            mostrarAlerta('Error de red al enviar al SRI.', 'danger');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up me-1"></i>Enviar al SRI'; }
        }
    };

    // ── EXPORTAR ─────────────────────────────────────────────────────────────────

    window.RET_exportarPdf = () => {
        if (!retIdActual) return;
        window.open(`${BASE}/exportPdfDoc?id=${retIdActual}`, '_blank');
    };

    window.RET_exportarXml = () => {
        if (!retIdActual) return;
        window.open(`${BASE}/exportXmlDoc?id=${retIdActual}`, '_blank');
    };

    // ── LÍNEAS DE RETENCIÓN ───────────────────────────────────────────────────────

    window.RET_agregarLinea = () => {
        let baseSugerida = '';
        const formEl = document.getElementById('formRetencion');
        if (formEl && formEl.dataset.compraSubtotal) {
            baseSugerida = formEl.dataset.compraSubtotal;
        }

        lineasData.push({
            codigo_impuesto:    '1',
            id_retencion_sri:   '',
            codigo_retencion:   '',
            concepto:           '',
            base_imponible:     baseSugerida,
            porcentaje_retener: '',
            valor_retenido:     0,
        });
        renderLineas();
        const tbody = document.getElementById('ret_lineas_body');
        const lastRow = tbody && tbody.querySelector('tr:last-child');
        lastRow && lastRow.querySelector('select')?.focus();
    };

    window.RET_eliminarLinea = (idx) => {
        lineasData.splice(idx, 1);
        renderLineas();
        calcTotales();
    };

    let searchTimer;
    window.RET_onLineCambio = (idx, campo, valor) => {
        if (!lineasData[idx]) return;
        lineasData[idx][campo] = valor;

        if (campo === 'codigo_retencion' || campo === 'concepto') {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                window.RET_buscarCodigoSri(idx, campo === 'codigo_retencion' ? 'codigo' : 'concepto');
            }, 300);
        }

        if (campo === 'codigo_impuesto') {
            const formEl = document.getElementById('formRetencion');
            if (formEl) {
                const subtotalNeto = parseFloat(formEl.dataset.compraSubtotal || 0);
                const totalIva     = parseFloat(formEl.dataset.compraIva || 0);
                if (valor == '1' && subtotalNeto > 0) lineasData[idx].base_imponible = subtotalNeto;
                else if (valor == '2' && totalIva > 0) lineasData[idx].base_imponible = totalIva;
            }
            renderLineas();
        }

        if (campo === 'base_imponible' || campo === 'porcentaje_retener') {
            const base = parseFloat(lineasData[idx]['base_imponible'] || 0);
            const pct  = parseFloat(lineasData[idx]['porcentaje_retener'] || 0);
            lineasData[idx]['valor_retenido'] = Math.round(base * pct / 100 * 100) / 100;
            const tr = document.getElementById('ret_lineas_body').querySelectorAll('tr')[idx];
            if (tr) {
                const valEl = tr.querySelector('.ret-val-retenido');
                if (valEl) valEl.textContent = '$' + lineasData[idx]['valor_retenido'].toFixed(2);
            }
        }

        calcTotales();
    };

    window.RET_buscarCodigoSri = async (idx, tipoBusqueda) => {
        const q = tipoBusqueda === 'codigo' ? (lineasData[idx]?.codigo_retencion || '') : (lineasData[idx]?.concepto || '');
        const fEmision = document.getElementById('ret_fecha_emision').value || '';
        const url = `${BASE}/getRetencionesSriAjax?q=${encodeURIComponent(q)}&fecha=${fEmision}`;
        try {
            const res  = await fetch(url);
            const data = await res.json();
            if (data.ok && data.data.length > 0) {
                mostrarDropdownCodigos(idx, data.data, tipoBusqueda);
            }
        } catch (e) {
            console.error(e);
        }
    };

    function mostrarDropdownCodigos(idx, items, tipoBusqueda) {
        const tr = document.getElementById('ret_lineas_body').querySelectorAll('tr')[idx];
        if (!tr) return;
        
        // Determinar el input objetivo
        const input = tipoBusqueda === 'codigo' 
            ? tr.querySelector('td:nth-child(1) input') 
            : tr.querySelector('td:nth-child(2) input');
        if (!input) return;

        // Eliminar dropdown previo si existe
        let drop = document.querySelector('.ret-cod-dropdown');
        if (drop) drop.remove();

        drop = document.createElement('div');
        drop.className = 'list-group shadow position-fixed ret-cod-dropdown';
        
        // Calcular posición
        const rect = input.getBoundingClientRect();
        drop.style.cssText = `
            z-index: 10000;
            width: 550px;
            max-height: 250px;
            overflow-y: auto;
            top: ${rect.bottom}px;
            left: ${rect.left}px;
        `;
        document.body.appendChild(drop);

        drop.innerHTML = '';
        items.forEach(item => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action small py-1';
            const isIva   = (item.impuesto_ret == '2' || String(item.impuesto_ret).toLowerCase().includes('iva'));
            const isIsd   = (item.impuesto_ret == '6' || String(item.impuesto_ret).toLowerCase().includes('isd'));
            const labelImp = isIva ? `IVA (${item.impuesto_ret})` : (isIsd ? `ISD (${item.impuesto_ret})` : `RENTA (${item.impuesto_ret})`);
            btn.innerHTML = `<div class="d-flex justify-content-between">
                <span><strong>${item.codigo_ret}</strong> — ${item.concepto_ret}</span>
                <span class="badge bg-light text-dark border ms-2">${labelImp} — ${item.porcentaje_ret}%</span>
            </div>`;
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                lineasData[idx].codigo_retencion   = item.codigo_ret;
                lineasData[idx].concepto           = item.concepto_ret;
                lineasData[idx].porcentaje_retener = item.porcentaje_ret;
                lineasData[idx].codigo_impuesto    = item.impuesto_ret;
                lineasData[idx].id_retencion_sri   = item.id;
                
                // Sugerir base imponible si existe contexto de compra
                const formEl = document.getElementById('formRetencion');
                if (formEl) {
                    const subtotalNeto = parseFloat(formEl.dataset.compraSubtotal || 0);
                    const totalIva     = parseFloat(formEl.dataset.compraIva || 0);
                    
                    const isRenta = (item.impuesto_ret == '1' || String(item.impuesto_ret).toLowerCase().includes('renta'));
                    const isIva   = (item.impuesto_ret == '2' || String(item.impuesto_ret).toLowerCase().includes('iva'));

                    if (isRenta && subtotalNeto > 0) {
                        lineasData[idx].base_imponible = subtotalNeto;
                    } else if (isIva && totalIva > 0) {
                        lineasData[idx].base_imponible = totalIva;
                    }
                }
                
                const base = parseFloat(lineasData[idx].base_imponible || 0);
                const pct  = parseFloat(lineasData[idx].porcentaje_retener || 0);
                lineasData[idx].valor_retenido = Math.round(base * pct / 100 * 100) / 100;

                renderLineas();
                calcTotales();
                drop.remove();

                // Mover foco a base imponible
                setTimeout(() => {
                    const row = document.getElementById('ret_lineas_body').querySelectorAll('tr')[idx];
                    if (row) {
                        const inputBase = row.querySelector('input[oninput*="base_imponible"]');
                        if (inputBase) {
                            inputBase.focus();
                            inputBase.select();
                        }
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
        const tbody = document.getElementById('ret_lineas_body');
        if (!tbody) return;

        if (lineasData.length === 0) {
            tbody.innerHTML = `<tr id="ret_lineas_empty"><td colspan="7" class="text-center py-4 text-muted">
                <i class="fa-regular fa-file-lines d-block mb-1"></i>Agregue al menos una línea de retención.</td></tr>`;
            return;
        }

        tbody.innerHTML = lineasData.map((l, i) => `
        <tr>
            <!-- Código -->
            <td class="p-0" style="position:relative;">
                <input type="text" class="form-control form-control-sm border-0 bg-transparent"
                       style="padding:0 4px;height:28px;font-size:0.78rem;" placeholder="Código"
                       value="${escHtml(l.codigo_retencion)}"
                       oninput="window.RET_onLineCambio(${i},'codigo_retencion',this.value)"
                       onfocus="window.RET_buscarCodigoSri(${i},'codigo')">
            </td>
            <!-- Concepto -->
            <td class="p-0" style="position:relative;">
                <input type="text" class="form-control form-control-sm border-0 bg-transparent"
                       style="padding:0 4px;height:28px;font-size:0.78rem;" placeholder="Concepto de retención..."
                       value="${escHtml(l.concepto)}"
                       oninput="window.RET_onLineCambio(${i},'concepto',this.value)"
                       onfocus="window.RET_buscarCodigoSri(${i},'concepto')">
            </td>
            <!-- Impuesto -->
            <td class="p-0 align-middle text-center">
                <span class="small fw-bold ${(l.codigo_impuesto == '2' || l.codigo_impuesto == 'IVA') ? 'text-primary' : 'text-success'}">
                    ${l.codigo_impuesto}
                </span>
            </td>
            <!-- Base Imponible -->
            <td class="p-0">
                <input type="number" class="form-control form-control-sm border-0 bg-transparent text-end fw-bold"
                       style="padding:0 8px;height:28px;font-size:0.85rem;" placeholder="0.00" step="0.01" min="0"
                       value="${parseFloat(l.base_imponible || 0).toFixed(2)}"
                       oninput="window.RET_onLineCambio(${i},'base_imponible',this.value)"
                       onblur="this.value = parseFloat(this.value || 0).toFixed(2)">
            </td>
            <!-- % Retención -->
            <td class="p-0">
                <input type="number" class="form-control form-control-sm border-0 bg-transparent text-center"
                       style="padding:0 4px;height:28px;font-size:0.78rem;" placeholder="0" step="0.01" min="0" max="100"
                       value="${l.porcentaje_retener}"
                       oninput="window.RET_onLineCambio(${i},'porcentaje_retener',this.value)">
            </td>
            <!-- Valor Retenido -->
            <td class="p-0 text-end">
                <span class="ret-val-retenido small fw-bold text-danger px-2">$${parseFloat(l.valor_retenido || 0).toFixed(2)}</span>
            </td>
            <!-- Acciones -->
            <td class="p-0 text-center">
                <button type="button" class="btn btn-link btn-sm text-danger p-0" onclick="window.RET_eliminarLinea(${i})" title="Eliminar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </td>
        </tr>`).join('');

        const countEl = document.getElementById('ret_count_items');
        if (countEl) countEl.textContent = lineasData.length;
    }

    // ── TOTALES ──────────────────────────────────────────────────────────────────

    function calcTotales() {
        let totalRenta = 0;
        let totalIva   = 0;
        let totalIsd   = 0;

        lineasData.forEach(l => {
            const valor = parseFloat(l.valor_retenido || 0);
            const codImp = String(l.codigo_impuesto || '');

            if (codImp === '1' || codImp === 'RENTA')           totalRenta += valor;
            else if (codImp === '2' || codImp === 'IVA')        totalIva   += valor;
            else if (codImp === '6' || codImp === 'ISD')        totalIsd   += valor;
            else                                                totalRenta += valor;
        });

        const totalRet = totalRenta + totalIva + totalIsd;

        const set = (id, v) => { 
            const el = document.getElementById(id); 
            if (el) el[el.tagName === 'INPUT' ? 'value' : 'textContent'] = typeof v === 'number' ? v.toFixed(2) : v; 
        };

        set('ret_lbl_renta',   '$' + totalRenta.toFixed(2));
        set('ret_lbl_iva',     '$' + totalIva.toFixed(2));
        set('ret_lbl_isd',     '$' + totalIsd.toFixed(2));
        set('ret_lbl_total',   '$' + totalRet.toFixed(2));

        set('ret_total_renta',    totalRenta.toFixed(2));
        set('ret_total_iva',      totalIva.toFixed(2));
        set('ret_total_isd',      totalIsd.toFixed(2));
        set('ret_total_retenido', totalRet.toFixed(2));
    }

    // ── SECUENCIAL ───────────────────────────────────────────────────────────────

    window.RET_cargarSecuencial = async () => {
        const sel = document.getElementById('ret_id_punto_emision');
        if (!sel || !sel.value) return;

        const inputSec = document.getElementById('ret_secuencial');
        if (inputSec) inputSec.placeholder = 'Cargando...';

        try {
            const res  = await fetch(`${BASE}/getSecuencialAjax?id_punto_emision=${sel.value}`);
            const data = await res.json();
            if (data.ok) {
                if (!retIdActual && inputSec) {
                    inputSec.value = data.formateado || String(data.secuencial).padStart(9, '0');
                    
                    // Indicador visual de gap (como en facturación)
                    if (data.es_gap) {
                        inputSec.classList.add('border-warning');
                        inputSec.title = data.detalle || 'Número faltante recuperado';
                    } else {
                        inputSec.classList.remove('border-warning');
                        inputSec.title = data.detalle || 'Siguiente consecutivo';
                    }
                }
            }
        } catch (e) {
            console.warn('No se pudo cargar el secuencial:', e);
        }
    };

    // ── PROVEEDOR INFO ────────────────────────────────────────────────────────────

    function mostrarInfoProveedor(item) {
        const el = document.getElementById('ret_proveedor_info');
        if (!el) return;
        
        const lblRuc = document.getElementById('ret_lbl_proveedor_ruc');
        const lblDir = document.getElementById('ret_lbl_proveedor_direccion');
        
        if (lblRuc) lblRuc.textContent = item.identificacion || item.ruc || '—';
        if (lblDir) lblDir.textContent = item.direccion || '—';
        
        el.classList.remove('d-none');
    }

    window.RET_actualizarPeriodoFiscal = (fecha) => {
        if (!fecha) return;
        const [y, m] = fecha.split('-');
        const el = document.getElementById('ret_periodo_fiscal');
        if (el) el.value = `${m}/${y}`;
    };

    window.abrirModalProveedorCrear = () => {
        if (typeof window.abrirModalCrearProveedor === 'function') {
            window.abrirModalCrearProveedor();
        } else if (typeof window.getModal === 'function') {
            window.getModal();
        } else {
            console.error('No se encontró la función para crear proveedor.');
        }
    };



    // ── CARGAR DATOS ─────────────────────────────────────────────────────────────

    function cargarCabecera(cab) {
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };

        set('ret_id', cab.id);
        set('ret_fecha_emision', cab.fecha_emision || '');
        set('ret_periodo_fiscal', cab.periodo_fiscal || '');
        set('ret_num_doc_sustento', cab.num_doc_sustento || '');
        set('ret_fecha_emision_doc_sustento', cab.fecha_emision_doc_sustento || '');
        set('ret_num_aut_sustento', cab.numero_autorizacion_sustento || '');
        set('ret_observaciones', cab.observaciones || '');
        set('ret_id_proveedor', cab.id_proveedor || '');
        set('ret_id_compra', cab.id_compra || '');
        set('ret_id_liquidacion', cab.id_liquidacion || '');

        // Proveedor search display
        const provEl = document.getElementById('ret_proveedor_search');
        if (provEl) provEl.value = cab.proveedor_razon_social || cab.proveedor_nombre || '';

        // Tipo doc sustento
        const selectTipo = document.getElementById('ret_tipo_doc_sustento');
        if (selectTipo) selectTipo.value = cab.tipo_doc_sustento || '01';

        // Punto de emisión
        const selectPunto = document.getElementById('ret_id_punto_emision');
        if (selectPunto && cab.id_punto_emision) selectPunto.value = cab.id_punto_emision;

        set('ret_secuencial', String(cab.secuencial || '').padStart(9, '0'));

        // Proveedor labels
        const lblRuc = document.getElementById('ret_lbl_proveedor_ruc');
        const lblDir = document.getElementById('ret_lbl_proveedor_direccion');
        if (lblRuc) lblRuc.textContent = cab.proveedor_identificacion || '—';
        if (lblDir) lblDir.textContent = cab.proveedor_direccion || '—';
        const infoProv = document.getElementById('ret_proveedor_info');
        if (infoProv) infoProv.classList.remove('d-none');

        // Badge de estado
        actualizarBadgeEstado(cab.estado || 'borrador');
    }

    window.RET_copiarCampoSri = function(inputId) {
        const el = document.getElementById(inputId);
        const val = el ? el.value.trim() : '';
        if (!val) return;
        navigator.clipboard.writeText(val).then(() => {
            const btn = el.nextElementSibling;
            if (btn) {
                const icon = btn.querySelector('i');
                if (icon) { icon.classList.replace('bi-clipboard', 'bi-clipboard-check'); btn.classList.replace('btn-outline-secondary', 'btn-outline-success'); }
                setTimeout(() => {
                    if (icon) { icon.classList.replace('bi-clipboard-check', 'bi-clipboard'); btn.classList.replace('btn-outline-success', 'btn-outline-secondary'); }
                }, 2000);
            }
        }).catch(() => { if (el) { el.select(); document.execCommand('copy'); } });
    };

    function cargarSriTab(cab) {
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v || ''; };
        set('ret-sri-clave-acceso', cab.clave_acceso || '');
        set('ret-sri-autorizacion', cab.numero_autorizacion || '');
        set('ret-sri-fecha-autorizacion', cab.fecha_autorizacion ? formatFecha(cab.fecha_autorizacion) : '');
        set('ret-sri-ambiente',     cab.tipo_ambiente == '2' ? '2 - PRODUCCIÓN' : '1 - PRUEBAS');
        set('ret-sri-tipo-emision', cab.tipo_emision  == '1' ? '1 - NORMAL'     : (cab.tipo_emision || ''));

        // Número de documento
        const elNroDoc = document.getElementById('ret-sri-numero-documento');
        if (elNroDoc) {
            const est = String(cab.establecimiento || '').padStart(3, '0');
            const pto = String(cab.punto_emision   || '').padStart(3, '0');
            const sec = String(cab.secuencial      || '').padStart(9, '0');
            elNroDoc.value = `${est}-${pto}-${sec}`;
        }
        set('ret-sri-identificacion-proveedor', cab.proveedor_identificacion || '');
        set('ret-sri-correo-proveedor',         cab.proveedor_email          || '');

        // Guardar globales para validaciones de anulación
        window.RET_FECHA_EMISION   = (cab.fecha_emision || '').split(' ')[0].split('T')[0] || null;
        window.RET_PROVEEDOR_RUC   = (cab.proveedor_identificacion || '').trim();

        // Mensajes
        const msgEl = document.getElementById('ret-sri-mensajes');
        if (msgEl) {
            let mensajes = [];
            if (cab.mensajes_sri) { try { mensajes = JSON.parse(cab.mensajes_sri); } catch { mensajes = []; } }
            msgEl.innerHTML = mensajes.length
                ? mensajes.map(m => {
                    const tipo = (m.tipo || 'INFO').toUpperCase();
                    const cls  = tipo === 'ERROR' ? 'text-danger' : tipo === 'ADVERTENCIA' ? 'text-warning' : 'text-info';
                    return `<div class="mb-1 ${cls}"><strong>[${tipo}]</strong> ${m.mensaje || ''}${m.info ? `<br><small class="text-muted">${m.info}</small>` : ''}</div>`;
                }).join('')
                : '<p class="text-muted text-center mb-0 py-3 small">Sin respuesta del SRI registrada.</p>';
        }

        actualizarBadgeSriEstado(cab.estado || 'borrador');
        cargarHistorialSri(cab.id);
    }

    async function cargarHistorialSri(id) {
        if (!id) return;
        const el = document.getElementById('ret-sri-historial');
        if (!el) return;
        try {
            const res  = await fetch(`${BASE}/getHistorialSriAjax?id=${id}`);
            const data = await res.json();
            if (data.ok && data.data && data.data.length > 0) {
                el.innerHTML = `<table class="table table-sm mb-0 small">
                    <thead><tr><th>Fecha</th><th>Acción</th><th>Estado SRI</th><th>Mensaje</th></tr></thead>
                    <tbody>${data.data.map(r => `
                        <tr>
                            <td class="text-nowrap">${escHtml(r.created_at || '')}</td>
                            <td><span class="badge bg-secondary bg-opacity-10 text-secondary border">${escHtml(r.accion || '')}</span></td>
                            <td>${escHtml(r.estado_sri || '')}</td>
                            <td class="text-truncate" style="max-width:200px;">${escHtml(r.mensaje || '')}</td>
                        </tr>`).join('')}
                    </tbody></table>`;
            } else {
                el.innerHTML = '<span class="text-muted">Sin historial de envíos.</span>';
            }
        } catch (e) {
            el.innerHTML = '<span class="text-muted">Error al cargar historial.</span>';
        }
    }

    function limpiarSriTab() {
        ['ret-sri-clave-acceso','ret-sri-autorizacion','ret-sri-fecha-autorizacion',
         'ret-sri-ambiente','ret-sri-tipo-emision','ret-sri-numero-documento',
         'ret-sri-identificacion-proveedor','ret-sri-correo-proveedor'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = '';
        });
        window.RET_FECHA_EMISION = null;
        window.RET_PROVEEDOR_RUC = '';
        const el = document.getElementById('ret-sri-mensajes');
        if (el) el.innerHTML = '<p class="text-muted text-center mb-0 py-3 small">Sin respuesta del SRI registrada.</p>';
        const h = document.getElementById('ret-sri-historial');
        if (h) h.innerHTML = '<p class="text-muted text-center mb-0 py-3 small">Sin historial.</p>';
        actualizarBadgeSriEstado('borrador');
    }

    // ── TOGGLES DE BOTONES ────────────────────────────────────────────────────────

    function toggleBotones(tieneId, esEditable = false, cab = {}) {
        const btnGuardar  = document.getElementById('ret-btn-guardar');
        const btnSri      = document.getElementById('ret-btn-sri');
        const btnPdf      = document.getElementById('ret-btn-pdf');
        const btnXml      = document.getElementById('ret-btn-xml');
        const btnEliminar = document.getElementById('ret-btn-eliminar');
        const btnAnular   = document.getElementById('ret-btn-anular');

        if (btnGuardar)  btnGuardar.disabled  = !esEditable && tieneId;
        if (btnSri) {
            const puedeEnviar = tieneId && cab.estado === 'borrador';
            btnSri.disabled = !puedeEnviar;
        }
        if (btnPdf) btnPdf.disabled = !tieneId;
        if (btnXml) btnXml.disabled = !tieneId;

        if (btnEliminar) {
            const puedeEliminar = tieneId && ['borrador', 'no_autorizada'].includes(cab.estado);
            btnEliminar.classList.toggle('d-none', !puedeEliminar);
        }
        if (btnAnular) {
            const puedeAnular = tieneId && cab.estado === 'autorizada';
            btnAnular.classList.toggle('d-none', !puedeAnular);
        }
    }

    function actualizarBadgeEstado(estado) {
        const el = document.getElementById('ret_estado_badge');
        if (!el) return;
        if (!estado || estado === 'borrador') { el.innerHTML = ''; return; }
        const clases = {
            autorizada:     'bg-success bg-opacity-10 text-success border-success',
            no_autorizada:  'bg-warning bg-opacity-10 text-warning border-warning',
            anulada:        'bg-danger bg-opacity-10 text-danger border-danger',
        };
        const cls = clases[estado] || 'bg-primary bg-opacity-10 text-primary border-primary';
        el.innerHTML = `<span class="badge border ${cls} px-2">${ucfirst(estado.replace('_', ' '))}</span>`;
    }

    function actualizarBadgeSriEstado(estado) {
        const el = document.getElementById('ret-sri-badge-estado');
        if (!el) return;
        const cfg = {
            borrador:       ['bg-secondary bg-opacity-10 text-secondary border-secondary', 'Borrador'],
            autorizada:     ['bg-success bg-opacity-10 text-success border-success', 'Autorizada'],
            no_autorizada:  ['bg-warning bg-opacity-10 text-warning border-warning', 'No autorizada'],
            anulada:        ['bg-danger bg-opacity-10 text-danger border-danger', 'Anulada'],
        };
        const [cls, label] = cfg[estado] || ['bg-secondary bg-opacity-10 text-secondary border-secondary', 'Sin enviar'];
        el.className = `badge ${cls} border border-opacity-25 px-2`;
        el.textContent = label;
    }

    // ── HELPERS ──────────────────────────────────────────────────────────────────

    function recopilarFormulario() {
        const get = (id) => (document.getElementById(id) || {}).value || '';
        return {
            id:                             retIdActual || '',
            id_punto_emision:               get('ret_id_punto_emision'),
            id_establecimiento:             '',
            establecimiento:                '',
            punto_emision:                  '',
            secuencial:                     get('ret_secuencial'),
            fecha_emision:                  get('ret_fecha_emision'),
            periodo_fiscal:                 get('ret_periodo_fiscal'),
            id_proveedor:                   get('ret_id_proveedor'),
            id_compra:                      get('ret_id_compra') || null,
            id_liquidacion:                 get('ret_id_liquidacion') || null,
            tipo_doc_sustento:              get('ret_tipo_doc_sustento'),
            num_doc_sustento:               get('ret_num_doc_sustento'),
            fecha_emision_doc_sustento:     get('ret_fecha_emision_doc_sustento'),
            numero_autorizacion_sustento:   get('ret_num_aut_sustento'),
            observaciones:                  '', // Eliminado del modal según solicitud previa
            total_retenido_renta:           get('ret_total_renta'),
            total_retenido_iva:             get('ret_total_iva'),
            total_retenido_isd:             get('ret_total_isd'),
            total_retenido:                 get('ret_total_retenido'),
            lineas: lineasData,
        };
    }

    function resetForm() {
        const form = document.getElementById('formRetencion');
        if (form) form.reset();
        document.getElementById('ret_id').value = '';
        retIdActual = 0;
        lineasData  = [];
        const elIdComp = document.getElementById('ret_id_compra');
        if (elIdComp) elIdComp.value = '';
        const elIdLiq = document.getElementById('ret_id_liquidacion');
        if (elIdLiq) elIdLiq.value = '';
        renderLineas();

        // Limpiar búsquedas
        const provSearch = document.getElementById('ret_proveedor_search');
        if (provSearch) provSearch.value = '';
        document.getElementById('ret_id_proveedor').value = '';
        const provInfo = document.getElementById('ret_proveedor_info');
        if (provInfo) provInfo.classList.add('d-none');
        
        const lblRuc = document.getElementById('ret_lbl_proveedor_ruc');
        const lblDir = document.getElementById('ret_lbl_proveedor_direccion');
        if (lblRuc) lblRuc.textContent = '';
        if (lblDir) lblDir.textContent = '';



        // Resetear estado badge
        actualizarBadgeEstado('borrador');
    }

    function setTitulo(texto, esNuevo) {
        const el = document.getElementById('modalRetTitulo');
        if (el) el.innerHTML = `<i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>${escHtml(texto)}`;
    }

    function formatFecha(str) {
        if (!str) return '';
        try {
            return new Date(str).toLocaleString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        } catch (e) { return str; }
    }

    function escHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function ucfirst(str) {
        return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
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
                    const res  = await fetch(`${url}?q=${encodeURIComponent(q)}`);
                    const data = await res.json();
                    const items = data.data || data.rows || [];
                    if (!items.length) { drop.classList.add('d-none'); return; }
                    drop.innerHTML = items.map((item, i) => {
                        const label = item.razon_social || item.nombre || item.numero || item.num_doc || JSON.stringify(item);
                        const sub   = item.identificacion || item.ruc || item.proveedor_nombre || '';
                        return `<button type="button" class="list-group-item list-group-item-action py-1 small" data-idx="${i}">
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

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    window.RET_nuevaRetencionDesdeLiquidacion = function(idLiq) {
        if (!idLiq) return;

        const idProv = document.getElementById('liq-proveedor-id').value;
        const nombreProv = document.getElementById('liq-proveedor-search').value;
        const estab = document.getElementById('liq-establecimiento').value;
        const pto = document.getElementById('liq-punto').value;
        const sec = document.getElementById('liq-secuencial').value;
        const numDoc = `${estab}-${pto}-${sec}`;
        const fechaDoc = document.getElementById('liq-fecha-emision').value;
        
        const subtotal = document.getElementById('liq-subtotal-neto').value || '0.00';
        const totalIva = document.getElementById('liq-total-iva').value || '0.00';

        if (typeof window.RET_abrirModalNuevo === 'function') {
            window.RET_abrirModalNuevo();
            
            setTimeout(() => {
                const form = document.getElementById('formRetencion');
                if (form) {
                    form.dataset.compraSubtotal = subtotal;
                    form.dataset.compraIva      = totalIva;
                }

                const elIdLiq = document.getElementById('ret_id_liquidacion');
                if (elIdLiq) elIdLiq.value = idLiq;
                
                const elIdProv = document.getElementById('ret_id_proveedor');
                if (elIdProv) elIdProv.value = idProv;
                
                const elSearchProv = document.getElementById('ret_proveedor_search');
                if (elSearchProv) elSearchProv.value = nombreProv;
                
                const elNumDoc = document.getElementById('ret_num_doc_sustento');
                if (elNumDoc) elNumDoc.value = numDoc;
                
                const elFechaDoc = document.getElementById('ret_fecha_emision_doc_sustento');
                if (elFechaDoc) elFechaDoc.value = fechaDoc;

                const selectTipo = document.getElementById('ret_tipo_doc_sustento');
                if (selectTipo) selectTipo.value = '03'; // Liquidación de compra
            }, 300);
        }
    };
})();


