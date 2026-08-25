(function () {
    'use strict';

    const state = {
        forma: 0,
        page: 1,
        sort: 'fecha_asiento',
        dir: 'ASC',
        consolidado: false,
    };

    // Contador de peticiones a getSaldosAjax: si el usuario cambia de filtro rápido
    // (p. ej. año completo -> enero) antes de que responda la primera llamada, esa
    // respuesta vieja puede llegar después que la nueva y pisar el valor correcto.
    // Con este contador, solo se aplica la respuesta de la petición más reciente.
    let saldosRequestSeq = 0;
    let searchRequestSeq = 0;

    async function fetchJson(url) {
        const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const contentType = resp.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error(`Respuesta no-JSON (HTTP ${resp.status}) de ${url}`);
        }
        return resp.json();
    }

    function fmtDateInput(v) {
        if (!v) return '';
        return String(v).substring(0, 10);
    }

    function fmtDateDisplay(v) {
        if (!v) return '—';
        const d = new Date(String(v).substring(0, 10) + 'T00:00:00');
        if (isNaN(d.getTime())) return v;
        return d.toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    window.CB_actualizarFechas = function () {
        const anio = document.getElementById('cb-anio').value;
        const mes = parseInt(document.getElementById('cb-mes').value, 10);
        let fInicio, fFin;
        if (!mes) {
            fInicio = `${anio}-01-01`;
            fFin = `${anio}-12-31`;
        } else {
            const mesStr = String(mes).padStart(2, '0');
            fInicio = `${anio}-${mesStr}-01`;
            const ultimoDia = new Date(anio, mes, 0).getDate();
            fFin = `${anio}-${mesStr}-${String(ultimoDia).padStart(2, '0')}`;
        }
        document.getElementById('cb-fecha-inicio').value = fInicio;
        document.getElementById('cb-fecha-fin').value = fFin;
        window.CB_fetchSearch(1);
    };

    // Muestra/oculta el switch "Consolidar por RUC" según si la cuenta elegida tiene
    // establecimientos hermanos con la misma cuenta real (window.CB_GRUPOS_CUENTAS, armado por
    // el servidor en index()). Si la cuenta nueva no tiene grupo, se apaga el switch también.
    function actualizarSwitchConsolidado() {
        const wrap = document.getElementById('cb-consolidado-wrap');
        const chk = document.getElementById('cb-consolidado');
        const tieneGrupo = !!(window.CB_GRUPOS_CUENTAS && window.CB_GRUPOS_CUENTAS[state.forma]);
        wrap.style.display = tieneGrupo ? '' : 'none';
        if (!tieneGrupo) {
            chk.checked = false;
            state.consolidado = false;
        }
    }

    window.CB_toggleConsolidado = function (checked) {
        state.consolidado = !!checked;
        window.CB_fetchSearch(1);
    };

    // Cuenta sin cuenta contable: no hay mayor del cual sacar el detalle, así que el movimiento
    // se arma desde los cobros y pagos hechos con esa cuenta. Se avisa para que quede claro de
    // dónde salen las cifras (empresas que no llevan contabilidad).
    function actualizarAvisoFuente() {
        const aviso = document.getElementById('cb-aviso-fuente');
        if (!aviso) return;
        const sinContabilidad = (window.CB_CUENTAS_SIN_CONTABILIDAD || []).includes(state.forma);
        aviso.innerHTML = sinContabilidad
            ? `<div class="alert alert-info py-2 px-3 mb-0 small d-flex align-items-center gap-2">
                   <i class="bi bi-info-circle"></i>
                   <span>Esta cuenta no tiene cuenta contable asignada: el detalle se arma con los <strong>cobros y pagos</strong> registrados con ella.</span>
               </div>`
            : '';
    }

    window.CB_cambiarCuenta = function (idForma) {
        state.forma = parseInt(idForma, 10) || 0;
        actualizarSwitchConsolidado();
        actualizarAvisoFuente();
        if (!state.forma) {
            document.getElementById('cb-tbody').innerHTML = '<tr><td colspan="13" class="text-center py-5 text-muted"><i class="bi bi-bank fs-3 d-block mb-2"></i>Seleccione una cuenta bancaria.</td></tr>';
            return;
        }
        window.CB_fetchSearch(1);
    };

    function fmtMoney(v) {
        return '$' + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // La Conciliación siempre es del período completo (no del texto de búsqueda de la tabla),
    // por eso solo lleva forma + fechas, sin "b".
    function actualizarUrlsConciliacion() {
        if (!state.forma) return;
        const fechaInicio = document.getElementById('cb-fecha-inicio').value;
        const fechaFin = document.getElementById('cb-fecha-fin').value;
        const params = new URLSearchParams({ forma: state.forma, consolidado: state.consolidado ? 1 : 0, fecha_inicio: fechaInicio, fecha_fin: fechaFin });
        document.getElementById('cb-btn-conciliacion-pdf').href = `${CB_URL_BASE}/exportarConciliacionPdfAjax?${params.toString()}`;
        document.getElementById('cb-btn-conciliacion-excel').href = `${CB_URL_BASE}/exportarConciliacionExcelAjax?${params.toString()}`;
    }

    // ── Conciliación (bloqueo de período) ───────────────────────────────────
    async function actualizarBadgeConciliacion() {
        if (!state.forma) return;
        const fechaInicio = document.getElementById('cb-fecha-inicio').value;
        const fechaFin = document.getElementById('cb-fecha-fin').value;
        const badge = document.getElementById('cb-badge-conciliacion');
        const btnConciliar = document.getElementById('cb-btn-conciliar');
        try {
            const params = new URLSearchParams({ forma: state.forma, consolidado: state.consolidado ? 1 : 0, fecha_inicio: fechaInicio, fecha_fin: fechaFin });
            const json = await fetchJson(`${CB_URL_BASE}/conciliacionActualAjax?${params.toString()}`);
            if (json.ok && json.data) {
                const d = json.data;
                badge.innerHTML = `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-2">
                    <i class="bi bi-lock-fill me-1"></i> Período conciliado (${fmtDateDisplay(d.fecha_inicio)} al ${fmtDateDisplay(d.fecha_fin)}) — saldo final $${Number(d.saldo_final).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}
                </span>`;
                btnConciliar.disabled = true;
                btnConciliar.title = 'El período mostrado ya está conciliado. Reábrelo desde el historial para volver a editar.';
            } else {
                badge.innerHTML = '';
                btnConciliar.disabled = false;
                btnConciliar.title = '';
            }
        } catch (e) {
            console.error(e);
        }
    }

    window.CB_abrirModalConciliar = function () {
        const cuentaTexto = document.getElementById('cb-forma').selectedOptions[0]?.textContent.trim() || '';
        const fechaInicio = document.getElementById('cb-fecha-inicio').value;
        const fechaFin = document.getElementById('cb-fecha-fin').value;
        document.getElementById('cbc-info-cuenta').textContent = cuentaTexto;
        document.getElementById('cbc-info-periodo').textContent = `${fmtDateDisplay(fechaInicio)} al ${fmtDateDisplay(fechaFin)}`;
        document.getElementById('cbc-info-saldo-sistema').textContent = document.getElementById('cb-stat-saldo-final').textContent;
        document.getElementById('cbc-saldo-banco').value = '';
        document.getElementById('cbc-observaciones').value = '';
        new bootstrap.Modal(document.getElementById('modalConciliarCB')).show();
    };

    window.CB_confirmarConciliar = async function () {
        const fechaInicio = document.getElementById('cb-fecha-inicio').value;
        const fechaFin = document.getElementById('cb-fecha-fin').value;
        const saldoBancoStr = document.getElementById('cbc-saldo-banco').value;
        const saldoSistemaStr = document.getElementById('cb-stat-saldo-final').textContent.replace(/[^0-9.-]/g, '');
        const saldoSistema = parseFloat(saldoSistemaStr) || 0;

        if (saldoBancoStr !== '') {
            const saldoBanco = parseFloat(saldoBancoStr);
            if (Math.abs(saldoBanco - saldoSistema) > 0.01) {
                const result = await Swal.fire({
                    icon: 'warning', title: 'El saldo no coincide',
                    html: `Saldo del sistema: <b>$${saldoSistema.toFixed(2)}</b><br>Saldo indicado del banco: <b>$${saldoBanco.toFixed(2)}</b><br><br>¿Deseas conciliar de todas formas?`,
                    showCancelButton: true, confirmButtonText: 'Sí, conciliar igual', cancelButtonText: 'Cancelar',
                });
                if (!result.isConfirmed) return;
            }
        }

        const payload = {
            id_forma_pago: state.forma,
            consolidado: state.consolidado,
            fecha_inicio: fechaInicio,
            fecha_fin: fechaFin,
            saldo_banco: saldoBancoStr,
            observaciones: document.getElementById('cbc-observaciones').value || null,
        };

        try {
            const resp = await fetch(`${CB_URL_BASE}/conciliarPeriodoAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload),
            });
            const json = await resp.json();
            if (!json.ok) {
                Swal.fire({ icon: 'error', title: 'No se pudo conciliar', text: json.error || 'Error desconocido.' });
                return;
            }
            bootstrap.Modal.getInstance(document.getElementById('modalConciliarCB')).hide();
            const mensaje = (json.data && json.data.creadas)
                ? `Se conciliaron ${json.data.creadas} establecimiento(s).`
                : 'Período conciliado';
            Swal.fire({ icon: 'success', title: mensaje, timer: 1800, showConfirmButton: false });
            actualizarBadgeConciliacion();
        } catch (e) {
            console.error(e);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red o servidor.' });
        }
    };

    function renderConciliaciones(rows) {
        const tbody = document.getElementById('cb-tbody-conciliaciones');
        if (!rows || !rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Sin conciliaciones registradas.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const vigente = !r.eliminado;
            const estadoBadge = vigente
                ? (r.desactualizada
                    ? '<span class="badge bg-warning bg-opacity-25 text-warning-emphasis border border-warning">Desactualizada</span>'
                    : '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Vigente</span>')
                : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Reabierta</span>';
            const accion = (vigente && CB_PERM_ELIMINAR)
                ? `<button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" onclick="window.CB_reabrirConciliacion(${r.id})"><i class="bi bi-unlock-fill"></i> Reabrir</button>`
                : '';
            // establecimiento solo viene en la vista consolidada (getConciliacionesGrupo).
            const badgeEst = r.establecimiento
                ? `<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 me-1" title="${r.empresa_nombre || ''}">${r.establecimiento}</span>`
                : '';
            return `<tr>
                <td>${badgeEst}${fmtDateDisplay(r.fecha_inicio)} al ${fmtDateDisplay(r.fecha_fin)}</td>
                <td class="text-end">$${Number(r.saldo_final).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                <td class="text-end">${r.saldo_banco !== null ? '$' + Number(r.saldo_banco).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) : '—'}</td>
                <td>${r.usuario_nombre || ''}</td>
                <td>${estadoBadge}</td>
                <td class="text-center">${accion}</td>
            </tr>`;
        }).join('');
    }

    window.CB_abrirModalHistorialConciliaciones = async function () {
        if (!state.forma) return;
        new bootstrap.Modal(document.getElementById('modalHistorialConciliacionesCB')).show();
        try {
            const json = await fetchJson(`${CB_URL_BASE}/listarConciliacionesAjax?forma=${state.forma}&consolidado=${state.consolidado ? 1 : 0}`);
            renderConciliaciones(json.ok ? json.data : []);
        } catch (e) {
            console.error(e);
        }
    };

    window.CB_reabrirConciliacion = async function (id) {
        const result = await Swal.fire({
            icon: 'warning', title: '¿Reabrir esta conciliación?',
            text: 'El período volverá a permitir reclasificar sus movimientos.',
            showCancelButton: true, confirmButtonText: 'Sí, reabrir', cancelButtonText: 'Cancelar',
        });
        if (!result.isConfirmed) return;

        try {
            const resp = await fetch(`${CB_URL_BASE}/reabrirConciliacionAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ id }),
            });
            const json = await resp.json();
            if (!json.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'No se pudo reabrir.' });
                return;
            }
            window.CB_abrirModalHistorialConciliaciones();
            actualizarBadgeConciliacion();
        } catch (e) {
            console.error(e);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red o servidor.' });
        }
    };

    async function cargarSaldos() {
        if (!state.forma) return;
        const fechaInicio = document.getElementById('cb-fecha-inicio').value;
        const fechaFin = document.getElementById('cb-fecha-fin').value;
        const miSeq = ++saldosRequestSeq;
        try {
            const params = new URLSearchParams({ forma: state.forma, consolidado: state.consolidado ? 1 : 0, fecha_inicio: fechaInicio, fecha_fin: fechaFin });
            const json = await fetchJson(`${CB_URL_BASE}/getSaldosAjax?${params.toString()}`);
            if (miSeq !== saldosRequestSeq) return; // llegó una respuesta más nueva antes: descartar esta
            if (json.ok) {
                document.getElementById('cb-stat-saldo-inicial').textContent = fmtMoney(json.data.saldo_inicial);
                document.getElementById('cb-stat-creditos').textContent = fmtMoney(json.data.creditos);
                document.getElementById('cb-stat-debitos').textContent = fmtMoney(json.data.debitos);
                document.getElementById('cb-stat-saldo-final').textContent = fmtMoney(json.data.saldo_final);
            }
        } catch (e) {
            console.error(e);
        }
    }

    window.CB_fetchSearch = async function (page) {
        state.page = page || 1;
        if (!state.forma) return;

        const buscar = document.getElementById('cb-buscar').value || '';
        const fechaInicio = document.getElementById('cb-fecha-inicio').value;
        const fechaFin = document.getElementById('cb-fecha-fin').value;

        cargarSaldos();
        actualizarUrlsConciliacion();
        actualizarBadgeConciliacion();

        const miSeq = ++searchRequestSeq;
        const tbody = document.getElementById('cb-tbody');
        tbody.innerHTML = '<tr><td colspan="13" class="text-center py-5"><span class="spinner-border spinner-border-sm text-primary"></span></td></tr>';

        const flujo = (document.getElementById('cb-flujo') || {}).value || 'TODOS';
        const tipo = (document.getElementById('cb-tipo') || {}).value || '';
        const cheque = (document.getElementById('cb-cheque') || {}).value || '';

        const params = new URLSearchParams({
            forma: state.forma,
            consolidado: state.consolidado ? 1 : 0,
            page: state.page,
            sort: state.sort,
            dir: state.dir,
            b: buscar,
            fecha_inicio: fechaInicio,
            fecha_fin: fechaFin,
            flujo: flujo,
            tipo: tipo,
            cheque: cheque,
        });

        try {
            const json = await fetchJson(`${CB_URL_BASE}/searchAjax?${params.toString()}`);
            if (miSeq !== searchRequestSeq) return; // llegó una respuesta más nueva antes: descartar esta
            if (!json.ok) {
                tbody.innerHTML = `<tr><td colspan="13" class="text-center py-5 text-danger">${json.error || 'Error al cargar movimientos.'}</td></tr>`;
                return;
            }
            tbody.innerHTML = json.rows;
            document.getElementById('cb-pagination-container').innerHTML = json.pagination;
            document.getElementById('cb-pagination-info').textContent = json.info;
            document.getElementById('cb-btn-pdf').href = json.pdf_url;
            document.getElementById('cb-btn-excel').href = json.excel_url;
        } catch (e) {
            console.error(e);
            if (miSeq === searchRequestSeq) {
                tbody.innerHTML = '<tr><td colspan="13" class="text-center py-5 text-danger">Error de red o servidor.</td></tr>';
            }
        }
    };

    window.CB_cambiarPaginaAjax = function (page) {
        if (page < 1) return;
        window.CB_fetchSearch(page);
    };

    // ── Modal de clasificación ──────────────────────────────────────────────
    window.CB_toggleCampoCheque = function (tipo) {
        const div = document.getElementById('cbm-div-cheque');
        div.classList.toggle('d-none', tipo !== 'CHEQUE');
        div.classList.toggle('d-flex', tipo === 'CHEQUE');
    };

    // Fecha Banco ya registrada del movimiento abierto en el modal (null = no cobrado).
    let chequeFechaBancoActual = null;

    const TIPO_LABELS = {
        DEPOSITO: 'Depósito', CHEQUE: 'Cheque', TRANSFERENCIA: 'Transferencia', DEBITO: 'Débito',
        NOTA_DEBITO: 'Nota Débito', NOTA_CREDITO: 'Nota Crédito', TARJETA: 'Tarjeta',
        PAYPHONE: 'Payphone', OTRO: 'Otro',
    };

    /**
     * Los datos del movimiento (tipo, cheque, observación) pertenecen al ingreso/egreso que lo
     * originó; se corrigen en ese documento, no aquí. Cuando hay documento detrás, se muestran
     * como texto en la tarjeta del encabezado y abajo queda solo la Fecha Banco, que es lo
     * propio de la conciliación. Sin documento (asientos manuales) se editan abajo como siempre,
     * y no se repiten arriba.
     */
    function aplicarSoloLecturaDeDocumento(tieneDocumento, row) {
        document.querySelectorAll('.cbm-editable').forEach(el => {
            el.classList.toggle('d-none', !!tieneDocumento);
        });
        // El bloque del cheque se muestra con d-flex, que en Bootstrap gana sobre d-none:
        // hay que quitárselo al ocultarlo, o quedaría visible pese al d-none.
        if (tieneDocumento) {
            document.getElementById('cbm-div-cheque').classList.remove('d-flex');
        }
        document.querySelectorAll('.cbm-info-doc').forEach(el => {
            el.style.display = tieneDocumento ? '' : 'none';
        });
        const aviso = document.getElementById('cbm-aviso-documento');
        if (aviso) aviso.classList.toggle('d-none', !tieneDocumento);
        if (!tieneDocumento) {
            // El bloque del cheque vuelve a depender del tipo elegido.
            window.CB_toggleCampoCheque(document.getElementById('cbm-tipo').value);
            return;
        }

        const tipo = row.tipo_transaccion || 'OTRO';
        const esCheque = (tipo === 'CHEQUE');
        document.getElementById('cbm-info-tipo').textContent = TIPO_LABELS[tipo] || tipo;
        document.getElementById('cbm-info-numero-cheque').textContent = row.numero_cheque || '—';
        document.getElementById('cbm-info-direccion').textContent = row.cheque_direccion
            ? '(' + row.cheque_direccion.charAt(0) + row.cheque_direccion.slice(1).toLowerCase() + ')'
            : '';
        document.getElementById('cbm-info-fecha-cheque').textContent = fmtDateDisplay(row.fecha_cheque);
        document.getElementById('cbm-info-observacion').textContent = row.observacion || '—';

        // Los datos del cheque solo tienen sentido si el movimiento es un cheque.
        document.getElementById('cbm-info-cheque-wrap').style.display = esCheque ? '' : 'none';
        document.getElementById('cbm-info-fecha-cheque-wrap').style.display = esCheque ? '' : 'none';
        // La observación, solo si hay algo escrito.
        document.getElementById('cbm-info-observacion-wrap').style.display = row.observacion ? '' : 'none';
    }

    function actualizarEstadoCheque(tipo, fechaBancoManual) {
        chequeFechaBancoActual = fechaBancoManual || null;
        const wrap = document.getElementById('cbm-info-estado-wrap');
        const estado = document.getElementById('cbm-info-estado');
        const ayuda = document.getElementById('cbm-ayuda-cobro');
        if (!wrap || !estado || !ayuda) return;

        if (tipo !== 'CHEQUE') {
            wrap.style.display = 'none';
            ayuda.classList.add('d-none');
            return;
        }
        wrap.style.display = '';
        if (fechaBancoManual) {
            estado.innerHTML = `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                <i class="bi bi-check-circle-fill"></i> Cobrado el ${fmtDateDisplay(fechaBancoManual)}</span>`;
            ayuda.classList.add('d-none');
        } else {
            estado.innerHTML = `<span class="badge bg-warning bg-opacity-10 text-warning-emphasis border border-warning border-opacity-25">
                <i class="bi bi-hourglass-split"></i> No cobrado (en circulación)</span>`;
            ayuda.classList.remove('d-none');
        }
    }

    // Al cambiar el tipo en el modal, el bloque de estado/ayuda debe seguir al tipo elegido.
    window.CB_toggleCampoCheque = (function (original) {
        return function (tipo) {
            original(tipo);
            actualizarEstadoCheque(tipo, chequeFechaBancoActual);
        };
    })(window.CB_toggleCampoCheque);

    window.CB_abrirModalClasificacion = function (btn) {
        const tr = btn.closest('tr');
        const row = JSON.parse(tr.dataset.row);

        document.getElementById('cbm-id-asiento-detalle').value = row.id_asiento_detalle || '';
        document.getElementById('cbm-id-asiento').value = row.id_asiento || '';
        // Cuentas sin cuenta contable (empresa que no lleva contabilidad): no hay línea de
        // asiento, la anotación se ancla al cobro/pago de origen.
        document.getElementById('cbm-origen-tipo').value = row.origen_tipo || '';
        document.getElementById('cbm-origen-id').value = row.origen_id || '';
        // row.id_empresa/id_forma_pago solo vienen en la vista consolidada (getMovimientosGrupo);
        // si no vienen, se usa la empresa activa / cuenta seleccionada de siempre.
        document.getElementById('cbm-id-empresa').value = row.id_empresa || 0;
        document.getElementById('cbm-id-forma-pago').value = row.id_forma_pago || state.forma;
        const wrapEst = document.getElementById('cbm-info-establecimiento-wrap');
        if (row.establecimiento) {
            document.getElementById('cbm-info-establecimiento').textContent = row.establecimiento + (row.empresa_nombre ? ' — ' + row.empresa_nombre : '');
            wrapEst.style.display = '';
        } else {
            wrapEst.style.display = 'none';
        }
        document.getElementById('cbm-info-fecha').textContent = fmtDateDisplay(row.fecha_asiento);
        document.getElementById('cbm-info-comprobante').textContent = row.numero_comprobante || 'S/N';
        document.getElementById('cbm-info-glosa').textContent = row.referencia_detalle || row.concepto || '';
        const monto = parseFloat(row.debe) > 0 ? row.debe : row.haber;
        document.getElementById('cbm-info-monto').textContent = '$' + Number(monto || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const tipo = row.tipo_transaccion || 'OTRO';
        document.getElementById('cbm-tipo').value = tipo;
        chequeFechaBancoActual = row.fecha_banco_manual || null;
        window.CB_toggleCampoCheque(tipo);
        // Dirección automática (no editable): ingreso/debe = recibido, egreso/haber = emitido.
        const selDir = document.getElementById('cbm-direccion');
        selDir.value = row.cheque_direccion || (parseFloat(row.debe) > 0 ? 'RECIBIDO' : 'EMITIDO');
        selDir.disabled = true;
        document.getElementById('cbm-numero-cheque').value = row.numero_cheque || '';
        document.getElementById('cbm-fecha-cheque').value = fmtDateInput(row.fecha_cheque);
        // Solo la Fecha Banco REALMENTE registrada (fecha_banco_manual). La columna fecha_banco
        // del listado cae a la fecha del movimiento cuando no se ha conciliado: precargarla aquí
        // dejaba el campo lleno sin que nadie lo hubiera conciliado y, al guardar cualquier otro
        // cambio, marcaba el cheque como cobrado sin querer.
        document.getElementById('cbm-fecha-banco').value = fmtDateInput(row.fecha_banco_manual);
        document.getElementById('cbm-observacion').value = row.observacion || '';

        // Estado de cobro del cheque + cómo marcarlo.
        actualizarEstadoCheque(tipo, row.fecha_banco_manual);

        // Movimiento enlazado a un cobro/pago: sus datos son del documento y van al encabezado.
        // Aquí solo se registra la Fecha Banco (el backend ignora igual cualquier otro cambio).
        const tieneDoc = (row.tiene_documento === true || row.tiene_documento === 't' || row.tiene_documento === '1' || row.tiene_documento === 1);
        aplicarSoloLecturaDeDocumento(tieneDoc, row);

        document.getElementById('cbm-btn-quitar').classList.toggle('d-none', !row.id_clasificacion);

        new bootstrap.Modal(document.getElementById('modalClasificacionCB')).show();
    };

    window.CB_guardarClasificacion = async function () {
        const payload = {
            id_asiento_detalle: parseInt(document.getElementById('cbm-id-asiento-detalle').value, 10) || 0,
            origen_tipo: document.getElementById('cbm-origen-tipo').value || null,
            origen_id: parseInt(document.getElementById('cbm-origen-id').value, 10) || 0,
            id_empresa: parseInt(document.getElementById('cbm-id-empresa').value, 10) || 0,
            id_forma_pago: parseInt(document.getElementById('cbm-id-forma-pago').value, 10) || state.forma,
            tipo_transaccion: document.getElementById('cbm-tipo').value,
            cheque_direccion: document.getElementById('cbm-tipo').value === 'CHEQUE' ? document.getElementById('cbm-direccion').value : null,
            numero_cheque: document.getElementById('cbm-numero-cheque').value || null,
            fecha_cheque: document.getElementById('cbm-fecha-cheque').value || null,
            fecha_banco: document.getElementById('cbm-fecha-banco').value || null,
            observacion: document.getElementById('cbm-observacion').value || null,
        };

        try {
            const resp = await fetch(`${CB_URL_BASE}/guardarClasificacionAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload),
            });
            const json = await resp.json();
            if (!json.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'No se pudo guardar la clasificación.' });
                return;
            }
            bootstrap.Modal.getInstance(document.getElementById('modalClasificacionCB')).hide();
            window.CB_fetchSearch(state.page);
            cargarSaldos();
        } catch (e) {
            console.error(e);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red o servidor.' });
        }
    };

    window.CB_quitarClasificacion = async function () {
        const idAsientoDetalle = parseInt(document.getElementById('cbm-id-asiento-detalle').value, 10) || 0;
        const origenTipo = document.getElementById('cbm-origen-tipo').value || null;
        const origenId = parseInt(document.getElementById('cbm-origen-id').value, 10) || 0;
        const idEmpresaRow = parseInt(document.getElementById('cbm-id-empresa').value, 10) || 0;
        const result = await Swal.fire({
            icon: 'warning', title: '¿Quitar clasificación?',
            text: 'El movimiento volverá a su clasificación automática por defecto.',
            showCancelButton: true, confirmButtonText: 'Sí, quitar', cancelButtonText: 'Cancelar',
        });
        if (!result.isConfirmed) return;

        try {
            const resp = await fetch(`${CB_URL_BASE}/quitarClasificacionAjax`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    id_asiento_detalle: idAsientoDetalle,
                    origen_tipo: origenTipo,
                    origen_id: origenId,
                    id_empresa: idEmpresaRow,
                }),
            });
            const json = await resp.json();
            if (!json.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: json.error || 'No se pudo quitar la clasificación.' });
                return;
            }
            bootstrap.Modal.getInstance(document.getElementById('modalClasificacionCB')).hide();
            window.CB_fetchSearch(state.page);
            cargarSaldos();
        } catch (e) {
            console.error(e);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de red o servidor.' });
        }
    };

    // ── Cheques posfechados ──────────────────────────────────────────────────
    function renderPosfechados(tbodyId, rows, terceroLabel) {
        const tbody = document.getElementById(tbodyId);
        if (!rows || !rows.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">No hay cheques posfechados.</td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const monto = parseFloat(r.debe) > 0 ? r.debe : r.haber;
            return `<tr>
                <td>${fmtDateDisplay(r.fecha_cheque)}</td>
                <td>${r.numero_cheque || ''}</td>
                <td>${r.forma_pago_nombre || ''}</td>
                <td>${r.nombre_entidad || ''}</td>
                <td class="text-end">$${Number(monto || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            </tr>`;
        }).join('');
    }

    window.CB_abrirModalPosfechados = async function () {
        new bootstrap.Modal(document.getElementById('modalPosfechadosCB')).show();
        try {
            const [recibidos, emitidos, emitidosEmp] = await Promise.all([
                fetchJson(`${CB_URL_BASE}/chequesPosfechadosAjax?direccion=RECIBIDO`),
                fetchJson(`${CB_URL_BASE}/chequesPosfechadosAjax?direccion=EMITIDO`),
                fetchJson(`${CB_URL_BASE}/chequesPosfechadosAjax?direccion=EMITIDO_EMPLEADO`),
            ]);
            renderPosfechados('cb-tbody-posf-recibidos', recibidos.ok ? recibidos.data : []);
            renderPosfechados('cb-tbody-posf-emitidos', emitidos.ok ? emitidos.data : []);
            renderPosfechados('cb-tbody-posf-emitidos-emp', emitidosEmp.ok ? emitidosEmp.data : []);
        } catch (e) {
            console.error(e);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        const formaSelect = document.getElementById('cb-forma');
        state.forma = parseInt(formaSelect.value, 10) || 0;
        state.consolidado = !!window.CB_CONSOLIDADO_INICIAL;
        actualizarSwitchConsolidado();
        actualizarAvisoFuente();
        document.getElementById('cb-consolidado').checked = state.consolidado;

        if (window.CMG_initSort) {
            window.CMG_initSort('control_bancario', (col, dir) => {
                state.sort = col;
                state.dir = dir;
                window.CB_fetchSearch(1);
            }, { container: '#cb-tabla', col: state.sort, dir: state.dir });
        }

        if (state.forma) {
            cargarSaldos();
            window.CB_fetchSearch(1);
        }
    });
})();
