(function () {
    'use strict';

    // ── Estado ──────────────────────────────────────────────────────────────────
    let modalRet;
    let lineasData = [];   // [{codigo_impuesto, codigo_retencion, concepto, base_imponible, porcentaje_retener, valor_retenido}]
    let currentSort = (typeof window.RET_ordenCol !== 'undefined' && window.RET_ordenCol) ? window.RET_ordenCol : 'fecha_emision';
    let currentDir  = (typeof window.RET_ordenDir !== 'undefined' && window.RET_ordenDir) ? window.RET_ordenDir : 'DESC';
    let retIdActual = 0;
    // Bloquea la carga del secuencial SOLO mientras se abre una retención existente,
    // para no sobrescribir el número guardado. Se libera al terminar la carga, de modo
    // que un cambio manual de serie sí recargue el siguiente consecutivo (igual que factura).
    let retBloquearSecuencial = false;

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
            // Pasar el foco al número de documento retenido
            const numDoc = document.getElementById('ret_num_doc_sustento');
            if (numDoc) numDoc.focus();
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

        // Recargar el asiento contable al abrir su pestaña
        const tabAsientoBtn = document.getElementById('tab-ret-asiento-btn');
        if (tabAsientoBtn) {
            tabAsientoBtn.addEventListener('shown.bs.tab', () => cargarAsientoContable(retIdActual));
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
        if (typeof window.guardarOrdenacionVista === 'function') {
            window.guardarOrdenacionVista('retenciones_compras', currentSort, currentDir);
        }
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
        retBloquearSecuencial = false; // nueva retención: permitir carga del consecutivo
        lineasData  = [];
        resetForm();
        setTitulo('Nueva Retención en Compras', true);
        toggleBotones(false);
        RET_cargarSecuencial();

        // Cargar el catálogo de sustento filtrado por el tipo de documento por defecto
        const tipoDefault = (document.getElementById('ret_tipo_doc_sustento') || {}).value || '01';
        window.RET_filtrarSustentos(tipoDefault);

        // Iniciar con una línea lista para capturar la retención
        if (typeof window.RET_agregarLinea === 'function') window.RET_agregarLinea();
        
        // Fecha actual por defecto (Local)
        const d = new Date();
        const hoy = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        
        const fEmision = document.getElementById('ret_fecha_emision');
        const fDoc = document.getElementById('ret_fecha_emision_doc_sustento');
        if (fEmision) fEmision.value = hoy;
        if (fDoc) fDoc.value = hoy;

        window.RET_actualizarPeriodoFiscal(hoy);
        limpiarSriTab();
        cargarAsientoContable(0);

        calcTotales();
        modalRet && modalRet.show();
        if (typeof window.aplicarFavoritosModal === 'function') {
            window.aplicarFavoritosModal('#modalRetencion');
            // Recargar el consecutivo según la serie favorita aplicada
            RET_cargarSecuencial();
        }

        // Foco inicial en el buscador de proveedor
        setTimeout(() => {
            const p = document.getElementById('ret_proveedor_search');
            if (p) p.focus();
        }, 400);
    };

    window.RET_abrirModal = async (row) => {
        initModal();
        resetForm();
        const data = JSON.parse(row.dataset.row);
        retIdActual = data.id;
        // Bloquear la carga del secuencial mientras se cargan los datos guardados.
        retBloquearSecuencial = true;

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
            cargarAsientoContable(retIdActual);

            window.RET_ID_ACTIVO = retIdActual;

            modalRet && modalRet.show();
            if (typeof window.aplicarFavoritosModal === 'function') window.aplicarFavoritosModal('#modalRetencion');
        } catch (e) {
            console.error(e);
            mostrarAlerta('Error al cargar datos de la retención.', 'danger');
        } finally {
            // Liberar el bloqueo: a partir de aquí, cambiar la serie recarga el
            // siguiente consecutivo con normalidad (igual que factura de venta).
            retBloquearSecuencial = false;
        }
    };

    // ── MODAL — GUARDAR ─────────────────────────────────────────────────────────

    window.RET_guardar = async () => {
        if (lineasData.length === 0) {
            mostrarAlerta('Debe agregar al menos una línea de retención.', 'warning');
            return;
        }
        const payload = recopilarFormulario();

        if (!payload.id_proveedor) {
            mostrarAlerta('Debe seleccionar el proveedor (sujeto retenido).', 'warning');
            document.getElementById('ret_proveedor_search')?.focus();
            return;
        }

        if (!payload.num_doc_sustento) {
            mostrarAlerta('Ingrese el número del documento retenido.', 'warning');
            document.getElementById('ret_num_doc_sustento')?.focus();
            return;
        }

        if (!payload.fecha_emision) {
            mostrarAlerta('La fecha de emisión es obligatoria.', 'warning');
            document.getElementById('ret_fecha_emision')?.focus();
            return;
        }

        // Validar diferencia de fechas (máximo 5 días y no anterior)
        if (payload.fecha_emision && payload.fecha_emision_doc_sustento) {
            const fRet = new Date(payload.fecha_emision + 'T00:00:00');
            const fDoc = new Date(payload.fecha_emision_doc_sustento + 'T00:00:00');
            
            if (fRet < fDoc) {
                mostrarAlerta('La fecha de la retención no puede ser anterior a la del documento retenido.', 'warning');
                document.getElementById('ret_fecha_emision_doc_sustento')?.focus();
                return;
            }

            const diffTime = fRet - fDoc;
            const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
            if (diffDays > 5) {
                mostrarAlerta('La retención debe emitirse máximo 5 días después de la fecha de emisión de la compra.', 'warning');
                document.getElementById('ret_fecha_emision_doc_sustento')?.focus();
                return;
            }
        }

        // Documento sustento no registrado (captura manual): exigir sus totales,
        // necesarios para el XML 2.0.0 (totalSinImpuestos / importeTotal).
        const vinculado = payload.id_compra || payload.id_liquidacion;
        if (!vinculado) {
            const subSustento   = parseFloat(payload.doc_sustento_subtotal || 0) || 0;
            const totalSustento  = parseFloat(payload.doc_sustento_total || 0) || 0;
            if (subSustento <= 0 && totalSustento <= 0) {
                mostrarAlerta('El documento sustento no está registrado: ingrese sus totales (Subtotal e IVA) en la tarjeta "Documento sustento".', 'warning');
                document.getElementById('ret_doc_subtotal')?.focus();
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

        // Progreso mientras se verifica el estado en el SRI y se anula (igual que factura)
        Swal.fire({
            title: 'Procesando anulación...',
            html: 'Estamos <strong>verificando el estado en el SRI</strong> y anulando el comprobante.<br>Esto puede tardar unos segundos, por favor espere.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const res = await fetch(`${BASE}/anularAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${retIdActual}`,
            });
            const data = await res.json();
            if (data.ok) {
                await Swal.fire({ icon: 'success', title: 'Retención anulada', text: data.mensaje || 'La retención fue anulada.', timer: 2200, showConfirmButton: false });
                RET_fetchSearch(1);
                const row = { dataset: { row: JSON.stringify({ id: retIdActual }) } };
                window.RET_abrirModal(row);
            } else {
                await Swal.fire({ icon: 'error', title: 'No se pudo anular', text: data.mensaje || 'No se pudo anular la retención.', confirmButtonColor: '#dc3545' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error al anular la retención.' });
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = btnOrigHtml; }
        }
    };

    // ── ENVIAR AL SRI ────────────────────────────────────────────────────────────

    window.RET_enviarSRI = async () => {
        if (!retIdActual) return;

        // Confirmación (mismo mensaje que factura de venta)
        const confirmar = await Swal.fire({
            icon: 'question',
            title: 'Enviar al SRI',
            html: 'Se firmará el comprobante con el certificado de la empresa y se enviará al SRI para su autorización.<br><small class="text-muted">Este proceso puede tardar unos segundos.</small>',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-cloud-arrow-up me-1"></i> Enviar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
        });
        if (!confirmar.isConfirmed) return;

        // Progreso (bloqueante) — "Enviando al SRI..."
        Swal.fire({
            title: 'Enviando al SRI...',
            html: '<div class="spinner-border text-primary" role="status"></div><br><small class="text-muted mt-2 d-block">Firmando y enviando comprobante...</small>',
            allowOutsideClick: false,
            showConfirmButton: false,
        });

        const btn = document.getElementById('ret-btn-sri');
        if (btn) btn.disabled = true;

        try {
            const res  = await fetch(`${BASE}/autorizarSRIAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${retIdActual}`,
            });
            const data = await res.json();

            if (data.ok) {
                await Swal.fire({
                    icon: 'success',
                    title: '¡Autorizada!',
                    html: `<p>${data.mensaje || 'Retención autorizada por el SRI.'}</p><code class="small">${data.numero_autorizacion || ''}</code>`,
                    confirmButtonColor: '#0d6efd',
                });
            } else {
                // Mostrar el detalle real devuelto por el SRI (tipo / mensaje / info)
                let errHtml = `<p class="text-danger">${data.mensaje || 'Error al enviar.'}</p>`;
                if (data.errores?.length) {
                    errHtml += '<ul class="text-start small mt-2">';
                    data.errores.forEach(e => {
                        errHtml += `<li><strong>[${e.tipo || 'ERROR'}]</strong> ${e.mensaje || ''}`;
                        if (e.info) errHtml += `<br><small class="text-muted">${e.info}</small>`;
                        errHtml += '</li>';
                    });
                    errHtml += '</ul>';
                }
                await Swal.fire({
                    icon: 'error',
                    title: 'No autorizada',
                    html: errHtml,
                    confirmButtonColor: '#dc3545',
                });
            }

            // Recargar datos del modal y del listado
            const row = { dataset: { row: JSON.stringify({ id: retIdActual }) } };
            window.RET_abrirModal(row);
            RET_fetchSearch(1);
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar con el servidor.' });
        } finally {
            if (btn) btn.disabled = false;
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

    window.RET_enviarPorCorreo = async () => {
        const id = parseInt(retIdActual) || 0;
        if (!id) return;

        // Correo actual del proveedor mostrado en la interfaz
        const mailLbl = document.getElementById('ret_lbl_proveedor_email');
        const correoActual = (mailLbl && mailLbl.textContent && mailLbl.textContent !== '—') ? mailLbl.textContent.trim() : '';

        const { value: correos, isConfirmed } = await Swal.fire({
            title: 'Enviar por correo',
            input: 'text',
            inputLabel: 'Correos electrónicos (separados por coma o espacio)',
            inputValue: correoActual,
            target: document.getElementById('modalRetencion'),
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-send me-1"></i> Enviar',
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => {
                if (!value.trim()) return 'Debes ingresar al menos un correo válido!';
            }
        });
        if (!isConfirmed) return;

        Swal.fire({
            title: 'Enviando correo...',
            text: 'Por favor espera',
            allowOutsideClick: false,
            target: document.getElementById('modalRetencion'),
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('correos', correos);

            const resp = await fetch(`${BASE}/reenviarCorreoAjax`, { method: 'POST', body: fd });
            const textResponse = await resp.text();

            let json;
            try {
                json = JSON.parse(textResponse);
            } catch (err) {
                console.error('RAW RESPONSE:', textResponse);
                Swal.fire({
                    icon: 'error', title: 'Respuesta inválida',
                    html: '<pre style="text-align:left; max-height:200px; overflow:auto;">' + textResponse.substring(0, 500) + '</pre>',
                    target: document.getElementById('modalRetencion')
                });
                return;
            }

            if (json.ok) {
                Swal.fire({ icon: 'success', title: '¡Enviado!', text: json.mensaje, timer: 2500, showConfirmButton: false });
                if (typeof RET_fetchSearch === 'function') RET_fetchSearch(1);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: json.mensaje || 'No se pudo enviar el correo.' });
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión al enviar el correo.' });
        }
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
        autollenarDocSustento();
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
        if (campo === 'base_imponible' || campo === 'codigo_impuesto') autollenarDocSustento();
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

    // Nombre del impuesto a partir del código SRI (1=Renta, 2=IVA, 6=ISD)
    function nombreImpuesto(cod) {
        const c = String(cod || '').toUpperCase();
        if (c === '1' || c === 'RENTA') return 'Renta';
        if (c === '2' || c === 'IVA')   return 'IVA';
        if (c === '6' || c === 'ISD')   return 'ISD';
        return c || '—';
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
                    ${nombreImpuesto(l.codigo_impuesto)}
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

    // Autollena la tarjeta "Documento sustento" desde las líneas cuando el documento
    // NO está vinculado a una compra/liquidación (captura manual): el Subtotal se toma
    // de la base de las líneas de Renta y el IVA de la base de las líneas de IVA.
    function autollenarDocSustento() {
        const idCompra = (document.getElementById('ret_id_compra') || {}).value || '';
        const idLiq    = (document.getElementById('ret_id_liquidacion') || {}).value || '';
        if (idCompra || idLiq) return; // vinculado → los valores vienen del documento

        let subtotal = 0, iva = 0;
        lineasData.forEach(l => {
            const base = parseFloat(l.base_imponible || 0) || 0;
            const cod  = String(l.codigo_impuesto || '').toUpperCase();
            if (cod === '1' || cod === 'RENTA')    subtotal += base;
            else if (cod === '2' || cod === 'IVA') iva += base;
        });

        const elSub = document.getElementById('ret_doc_subtotal');
        const elIva = document.getElementById('ret_doc_iva');
        if (elSub) elSub.value = subtotal > 0 ? subtotal.toFixed(2) : '';
        if (elIva) elIva.value = iva > 0 ? iva.toFixed(2) : '';
        if (typeof window.RET_calcTotalSustento === 'function') window.RET_calcTotalSustento();
    }

    // Filtra el catálogo de sustento tributario según el Tipo de Documento
    // (columna tipo_comprobante, igual que en compras).
    window.RET_filtrarSustentos = (tipo, selectedId = null) => {
        const el = document.getElementById('ret_id_sustento_tributario');
        if (!el || !Array.isArray(window.RET_SUSTENTOS)) return;
        tipo = String(tipo || '').padStart(2, '0');
        if (selectedId == null) selectedId = el.value || null; // conservar selección actual

        const opts = window.RET_SUSTENTOS.filter(s => {
            const tipos = String(s.tipo_comprobante || '').split(',').map(t => t.trim());
            return !tipo || tipos.includes(tipo);
        });

        el.innerHTML = '<option value="">-- Seleccione --</option>' + opts.map(s =>
            `<option value="${s.id}" ${String(selectedId) === String(s.id) ? 'selected' : ''}>${escHtml(s.codigo)} - ${escHtml(s.nombre)}</option>`
        ).join('');

        // Si la selección previa ya no aplica, preseleccionar 01 (Crédito Tributario IVA) si existe
        if (!el.value) {
            const cred = opts.find(s => s.codigo === '01');
            if (cred) el.value = cred.id;
        }
    };

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
                if (!retBloquearSecuencial && inputSec) {
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
        const lblEmail = document.getElementById('ret_lbl_proveedor_email');

        if (lblRuc) lblRuc.textContent = item.identificacion || item.ruc || '—';
        if (lblDir) lblDir.textContent = item.direccion || '—';
        if (lblEmail) lblEmail.textContent = item.email || item.correo || '—';

        el.classList.remove('d-none');
    }

    window.RET_actualizarPeriodoFiscal = (fecha) => {
        if (!fecha) return;
        const [y, m] = fecha.split('-');
        const el = document.getElementById('ret_periodo_fiscal');
        if (el) el.value = `${m}/${y}`;
    };

    // Solo definir un fallback si el modal compartido de proveedores no cargó su
    // propia función (proveedores_modal.js define window.PROV_abrirModalCrear /
    // window.abrirModalProveedorCrear). No sobrescribir esa función correcta.
    if (typeof window.abrirModalProveedorCrear !== 'function') {
        window.abrirModalProveedorCrear = () => {
            if (typeof window.PROV_abrirModalCrear === 'function') {
                window.PROV_abrirModalCrear();
            } else {
                console.error('No se encontró la función para crear proveedor.');
            }
        };
    }



    // ── CARGAR DATOS ─────────────────────────────────────────────────────────────

    function cargarCabecera(cab) {
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };

        set('ret_id', cab.id);
        set('ret_fecha_emision', cab.fecha_emision || '');
        set('ret_periodo_fiscal', cab.periodo_fiscal || '');
        set('ret_num_doc_sustento', cab.num_doc_sustento || '');
        set('ret_fecha_emision_doc_sustento', cab.fecha_emision_doc_sustento || '');
        set('ret_num_aut_sustento', cab.numero_autorizacion_sustento || '');
        set('ret_doc_subtotal', cab.doc_sustento_subtotal != null ? cab.doc_sustento_subtotal : '');
        set('ret_doc_iva',      cab.doc_sustento_iva      != null ? cab.doc_sustento_iva      : '');
        set('ret_doc_total',    cab.doc_sustento_total    != null ? cab.doc_sustento_total    : '');
        // Filtrar sustento según el tipo de documento y seleccionar el guardado
        window.RET_filtrarSustentos(cab.tipo_doc_sustento || '01', cab.id_sustento_tributario || null);
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
        const lblEmail = document.getElementById('ret_lbl_proveedor_email');
        if (lblRuc) lblRuc.textContent = cab.proveedor_identificacion || '—';
        if (lblDir) lblDir.textContent = cab.proveedor_direccion || '—';
        if (lblEmail) lblEmail.textContent = cab.proveedor_email || '—';
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

        actualizarBadgeSriEstado(cab.estado || 'borrador');
        cargarHistorialSri(cab.id);
    }

    async function cargarHistorialSri(id) {
        const tbody = document.getElementById('ret-sri-tbody-historial');
        if (!tbody || !id) return;

        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-2 text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</td></tr>';

        try {
            const res  = await fetch(`${BASE}/getHistorialSriAjax?id=${id}`);
            const json = await res.json();

            if (!json.ok || !json.data || !json.data.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted small">Sin historial de envíos.</td></tr>';
                return;
            }

            const accionMap = {
                'enviando':         ['bg-primary', 'bi-cloud-arrow-up',      'Enviando'],
                'recibida':         ['bg-info',    'bi-check-circle',        'Recibida'],
                'devuelta':         ['bg-danger',  'bi-x-circle',            'Devuelta'],
                'autorizada':       ['bg-success', 'bi-patch-check-fill',    'Autorizada'],
                'autorizado':       ['bg-success', 'bi-patch-check-fill',    'Autorizado'],
                'no_autorizada':    ['bg-danger',  'bi-patch-minus',         'No autorizada'],
                'no_autorizado':    ['bg-danger',  'bi-patch-minus',         'No autorizado'],
                'en_procesamiento': ['bg-warning', 'bi-hourglass-split',     'En proceso'],
                'anulada':          ['bg-danger',  'bi-slash-circle',        'Anulada'],
                'error':            ['bg-danger',  'bi-exclamation-triangle','Error'],
            };

            tbody.innerHTML = json.data.map(row => {
                const [bgCls, icon, lbl] = accionMap[row.accion] ?? ['bg-secondary', 'bi-question', row.accion || ''];
                const cls = bgCls.replace('bg-', '');
                const esPruebas = row.tipo_ambiente === '1';
                const ambienteLbl = esPruebas
                    ? '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" style="font-size:0.65rem;">PRUEBAS</span>'
                    : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:0.65rem;">PRODUCCIÓN</span>';

                // Detalle: mensaje + errores del json (incluye informacionAdicional)
                let detalle = escHtml(row.mensaje || '');
                if (row.detalle_json) {
                    try {
                        const errs = JSON.parse(row.detalle_json);
                        if (Array.isArray(errs) && errs.length) {
                            detalle += '<ul class="mb-0 ps-3 mt-1" style="font-size:0.7rem;">';
                            errs.forEach(e => {
                                detalle += `<li><strong>[${escHtml(e.tipo || e.id || '')}]</strong> ${escHtml(e.mensaje || '')}${e.info ? '<br><em class="text-muted">' + escHtml(e.info) + '</em>' : ''}</li>`;
                            });
                            detalle += '</ul>';
                        }
                    } catch (e) {}
                }
                if (row.numero_autorizacion && (row.accion === 'autorizada' || row.accion === 'autorizado')) {
                    detalle += `<div class="font-monospace mt-1" style="font-size:0.65rem;word-break:break-all;">${escHtml(row.numero_autorizacion)}</div>`;
                }

                return `<tr>
                    <td class="ps-2 py-1 text-nowrap" style="font-size:0.72rem;">${escHtml(row.created_at || '')}</td>
                    <td class="py-1">${ambienteLbl}</td>
                    <td class="py-1"><span class="badge ${bgCls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25" style="font-size:0.65rem;"><i class="bi ${icon} me-1"></i>${lbl}</span></td>
                    <td class="py-1" style="font-size:0.72rem;">${detalle}</td>
                </tr>`;
            }).join('');
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-2 text-danger small">Error al cargar historial.</td></tr>';
        }
    }

    // ── Pestaña Asiento Contable (mismo modelo que retención de venta) ──────────
    async function cargarAsientoContable(id) {
        const tbody   = document.getElementById('ret_asiento_body');
        const tdDebe  = document.getElementById('ret_asiento_total_debe');
        const tdHaber = document.getElementById('ret_asiento_total_haber');
        const aviso   = document.getElementById('ret_asiento_aviso');
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
                    <td class="ps-3 small"><code class="text-secondary">${escHtml(d.cuenta_codigo || '')}</code></td>
                    <td class="small">${escHtml(d.cuenta_nombre || '')}</td>
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
                    const num = resp.numero ? ` N° ${escHtml(resp.numero)}` : '';
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

    function limpiarSriTab() {
        ['ret-sri-clave-acceso','ret-sri-autorizacion','ret-sri-fecha-autorizacion',
         'ret-sri-ambiente','ret-sri-tipo-emision','ret-sri-numero-documento',
         'ret-sri-identificacion-proveedor','ret-sri-correo-proveedor'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = '';
        });
        window.RET_FECHA_EMISION = null;
        window.RET_PROVEEDOR_RUC = '';
        const h = document.getElementById('ret-sri-tbody-historial');
        if (h) h.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted small">Sin historial de envíos.</td></tr>';
        actualizarBadgeSriEstado('borrador');
    }

    // ── TOGGLES DE BOTONES ────────────────────────────────────────────────────────

    function toggleBotones(tieneId, esEditable = false, cab = {}) {
        const btnGuardar  = document.getElementById('ret-btn-guardar');
        const btnSri      = document.getElementById('ret-btn-sri');
        const btnPdf      = document.getElementById('ret-btn-pdf');
        const btnXml      = document.getElementById('ret-btn-xml');
        const btnCorreo   = document.getElementById('ret-btn-correo');
        const btnEliminar = document.getElementById('ret-btn-eliminar');
        const btnAnular   = document.getElementById('ret-btn-anular');

        if (btnGuardar)  btnGuardar.disabled  = !esEditable && tieneId;
        if (btnSri) {
            const puedeEnviar = tieneId && cab.estado === 'borrador';
            btnSri.disabled = !puedeEnviar;
        }
        if (btnPdf) btnPdf.disabled = !tieneId;
        if (btnXml) btnXml.disabled = !tieneId;
        if (btnCorreo) btnCorreo.disabled = !(tieneId && cab.estado === 'autorizada');

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
            id_sustento_tributario:         get('ret_id_sustento_tributario') || null,
            num_doc_sustento:               get('ret_num_doc_sustento'),
            fecha_emision_doc_sustento:     get('ret_fecha_emision_doc_sustento'),
            numero_autorizacion_sustento:   get('ret_num_aut_sustento'),
            doc_sustento_subtotal:          get('ret_doc_subtotal') || 0,
            doc_sustento_iva:               get('ret_doc_iva') || 0,
            doc_sustento_total:             get('ret_doc_total') || 0,
            observaciones:                  '', // Eliminado del modal según solicitud previa
            total_retenido_renta:           get('ret_total_renta'),
            total_retenido_iva:             get('ret_total_iva'),
            total_retenido_isd:             get('ret_total_isd'),
            total_retenido:                 get('ret_total_retenido'),
            lineas: lineasData,
        };
    }

    // Total del documento sustento = subtotal + IVA
    window.RET_calcTotalSustento = () => {
        const get = (id) => parseFloat((document.getElementById(id) || {}).value) || 0;
        const el = document.getElementById('ret_doc_total');
        if (el) el.value = (get('ret_doc_subtotal') + get('ret_doc_iva')).toFixed(2);
    };

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
        const lblEmail = document.getElementById('ret_lbl_proveedor_email');
        if (lblRuc) lblRuc.textContent = '';
        if (lblDir) lblDir.textContent = '';
        if (lblEmail) lblEmail.textContent = '';



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

                // Totales del documento sustento (desde la liquidación)
                const elSub = document.getElementById('ret_doc_subtotal');
                const elIva = document.getElementById('ret_doc_iva');
                if (elSub) elSub.value = subtotal;
                if (elIva) elIva.value = totalIva;
                if (typeof window.RET_calcTotalSustento === 'function') window.RET_calcTotalSustento();
            }, 300);
        }
    };
})();


