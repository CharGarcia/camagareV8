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
        cargarLineaContableConciliar(fechaInicio, fechaFin);
    };

    // ── Comprobación con Contabilidad (solo lectura) ─────────────────────────
    const CLASES_COMPROBACION = {
        cuadra:            { txt: 'Cuadra',                          cls: 'success',   ayuda: 'El documento y su asiento mueven lo mismo en el período.' },
        solo_documento:   { txt: 'Documento sin asiento',           cls: 'warning',   ayuda: 'El ingreso/egreso/traspaso no tiene ningún asiento contabilizado (pendiente de contabilizar o migrado sin asiento).' },
        sin_cuenta_modulo: { txt: 'Asiento sin cuenta del banco',   cls: 'warning',   ayuda: 'El documento tiene asiento, pero ninguna de sus líneas afecta la cuenta contable del banco (su forma de pago apunta a otra cuenta en el asiento). El número del asiento lo abre.' },
        solo_contabilidad: { txt: 'Solo en contabilidad',            cls: 'info',      ayuda: 'Asiento sin ingreso/egreso detrás (manual, migrado, etc.).' },
        documento_anulado: { txt: 'Asiento de documento anulado',    cls: 'danger',    ayuda: 'El ingreso/egreso está anulado o eliminado pero su asiento sigue contabilizado.' },
        otra_cuenta:       { txt: 'Cobrado/pagado con otra cuenta',  cls: 'secondary', ayuda: 'El asiento mueve esta cuenta contable, pero el documento se registró con otra forma de pago. Revise la forma de pago del documento o la cuenta de su asiento.' },
        monto_distinto:    { txt: 'Monto distinto',                  cls: 'danger',    ayuda: 'El documento y su asiento mueven montos distintos en el banco.' },
        fecha_distinta:    { txt: 'Fecha en otro período',           cls: 'secondary', ayuda: 'El documento y su asiento tienen fechas que caen en períodos distintos.' },
    };

    function paramsComprobacion() {
        return new URLSearchParams({
            forma: state.forma,
            fecha_inicio: document.getElementById('cb-fecha-inicio').value,
            fecha_fin: document.getElementById('cb-fecha-fin').value,
        });
    }

    function celdaDif(v) {
        const n = Number(v || 0);
        const cls = Math.abs(n) < 0.005 ? 'text-success' : 'text-danger';
        return `<span class="fw-bold ${cls}">${fmtMoney(n)}</span>`;
    }

    // Línea resumida dentro del modal "Marcar Período como Conciliado".
    async function cargarLineaContableConciliar() {
        const cont = document.getElementById('cbc-info-contable');
        if (!cont) return;
        cont.innerHTML = '';
        if (!state.forma || state.consolidado) return;
        cont.innerHTML = '<span class="text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Comparando con la contabilidad…</span>';
        try {
            const json = await fetchJson(`${CB_URL_BASE}/comprobacionContableAjax?${paramsComprobacion().toString()}`);
            if (!json.ok || !json.data || json.data.sin_cuenta_contable) { cont.innerHTML = ''; return; }
            const f = json.data.fin;
            const cuadra = Math.abs(f.diferencia) < 0.005;
            cont.innerHTML = `<span class="text-muted">Saldo según contabilidad:</span> <span class="fw-bold">${fmtMoney(f.contable)}</span>
                <span class="badge ms-1 ${cuadra ? 'bg-success' : 'bg-danger'} bg-opacity-10 ${cuadra ? 'text-success border-success' : 'text-danger border-danger'} border border-opacity-25">
                    ${cuadra ? '<i class="bi bi-check-circle-fill"></i> Cuadra' : 'Diferencia ' + fmtMoney(f.diferencia)}</span>
                <a href="#" class="ms-1 small" onclick="event.preventDefault(); CB_abrirComprobacionContable();">Ver detalle</a>`;
        } catch (e) {
            console.error(e);
            cont.innerHTML = '';
        }
    }

    window.CB_abrirComprobacionContable = async function () {
        if (!state.forma) {
            Swal.fire({ icon: 'info', title: 'Seleccione una cuenta', text: 'Elija la cuenta bancaria a comprobar.' });
            return;
        }
        if (state.consolidado) {
            Swal.fire({ icon: 'info', title: 'Vista consolidada', text: 'La comprobación con contabilidad se hace por cuenta: desactive "Consolidar por RUC".' });
            return;
        }
        const cont = document.getElementById('cb-comp-contenido');
        const loader = document.getElementById('cb-comp-loader');
        cont.innerHTML = '<div style="min-height:160px;"></div>';
        loader.classList.remove('d-none');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalComprobacionContableCB')).show();
        try {
            const json = await fetchJson(`${CB_URL_BASE}/comprobacionContableAjax?${paramsComprobacion().toString()}`);
            if (!json.ok) {
                cont.innerHTML = `<div class="alert alert-danger small mb-0">${escHtml(json.error || 'No se pudo hacer la comprobación.')}</div>`;
                return;
            }
            cont.innerHTML = renderComprobacion(json.data);
        } catch (e) {
            console.error(e);
            cont.innerHTML = '<div class="alert alert-danger small mb-0">Error de red o servidor.</div>';
        } finally {
            loader.classList.add('d-none');
        }
    };

    function renderComprobacion(d) {
        if (d.sin_cuenta_contable) {
            return `<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-1"></i>
                La cuenta <strong>${escHtml(d.forma)}</strong> no tiene cuenta contable asignada: no hay contabilidad contra la cual comparar.
                Se asigna en <em>Formas de cobro y pago</em>.</div>`;
        }
        // Cuenta(s) que de verdad mueven los asientos (regla de Configuración Contable o cuenta
        // base de la forma). Si cobros y pagos van a cuentas distintas, se dice cuál es cuál.
        const nombreCuenta = c => `${escHtml(c.codigo)} — ${escHtml(c.nombre)}`;
        const cuentasPorId = Object.fromEntries((d.cuentas || []).map(c => [String(c.id), c]));
        const cCobro = cuentasPorId[String(d.cuenta_cobro)];
        const cPago = cuentasPorId[String(d.cuenta_pago)];
        const cuenta = (cCobro && cPago && cCobro.id !== cPago.id)
            ? `Cobros: ${nombreCuenta(cCobro)} · Pagos: ${nombreCuenta(cPago)}`
            : ((d.cuentas || []).map(nombreCuenta).join(', ') || '—');
        const formas = (d.formas || []).map(f => escHtml(f.nombre)).join(', ');
        const avisoCompartida = (d.formas || []).length > 1
            ? `<div class="alert alert-warning py-2 px-3 small mb-2"><i class="bi bi-exclamation-triangle me-1"></i>
                   Esta cuenta contable la usan <strong>${d.formas.length} cuentas bancarias</strong> (${formas}). La contabilidad no las distingue,
                   así que se comparan <strong>todas juntas</strong>.</div>`
            : '';
        const movLibros = d.fin.libros - d.inicio.libros;
        const movCont = d.fin.contable - d.inicio.contable;

        const chips = Object.keys(d.resumen_clases || {}).map(k => {
            const c = CLASES_COMPROBACION[k] || { txt: k, cls: 'secondary', ayuda: '' };
            const r = d.resumen_clases[k];
            return `<span class="badge bg-${c.cls} bg-opacity-10 text-${c.cls === 'warning' ? 'warning-emphasis' : c.cls} border border-${c.cls} border-opacity-25 me-1 mb-1" title="${escHtml(c.ayuda)}">
                        ${c.txt}: ${r.cantidad} · ${fmtMoney(r.diferencia)}</span>`;
        }).join('');

        const vacio = '<span class="text-muted">—</span>';
        // Monto de un lado: si su fecha cae fuera del período no suma aquí, y se ve tachado.
        const celdaMonto = (monto, efecto) => {
            if (monto === null || monto === undefined) return vacio;
            const fuera = Math.abs(Number(efecto || 0)) < 0.005 && Math.abs(Number(monto)) >= 0.005;
            return fuera
                ? `<span class="text-muted text-decoration-line-through" title="Su fecha cae fuera del período: no suma aquí">${fmtMoney(monto)}</span>`
                : fmtMoney(monto);
        };

        // Mayor comparado: saldo al inicio, cada movimiento con el saldo acumulado de cada lado,
        // y saldo al final. La fila donde cambia la diferencia acumulada es la que descuadra.
        const filas = (d.partidas || []).map(p => {
            let c = CLASES_COMPROBACION[p.clase] || { txt: p.clase, cls: 'secondary', ayuda: '' };
            // "Otra cuenta": se nombra la forma con que se registró el documento (p. ej. Efectivo),
            // que es la causa: el asiento va al banco y el documento dice otra cosa.
            if (p.clase === 'otra_cuenta' && p.formas_doc) {
                c = {
                    ...c,
                    txt: `${p.tipo === 'ingreso' ? 'Cobrado' : 'Pagado'} con ${escHtml(p.formas_doc)}`,
                    ayuda: `El documento se registró con "${p.formas_doc}", pero su asiento mueve esta cuenta contable. `
                         + 'Corrija la forma de pago del documento (si el dinero pasó por el banco) o la cuenta de su asiento.',
                };
            }
            const ok = p.clase === 'cuadra';
            const tipoDoc = ({ ingreso: 'Ingreso', egreso: 'Egreso', traspaso: 'Traspaso', conciliacion_tarjetas: 'Liquidación de tarjetas' })[p.tipo] || 'Asiento';
            // Sin línea en la cuenta del banco pero con asiento del documento en otras cuentas: se
            // enlaza ese asiento (atenuado) para ver a qué cuenta fue.
            const asiento = p.id_asiento
                ? `<a href="#" onclick="event.preventDefault(); ASIENTO_abrirModal(${parseInt(p.id_asiento, 10)});" title="Ver asiento">${escHtml(p.numero_asiento || 'Asiento')}</a>`
                : (p.id_asiento_doc
                    ? `<a href="#" class="text-muted" onclick="event.preventDefault(); ASIENTO_abrirModal(${parseInt(p.id_asiento_doc, 10)});" title="Asiento del documento: no afecta la cuenta del banco">${escHtml(p.numero_asiento_doc || 'Asiento')}</a>`
                    : vacio);
            const doc = p.tipo === 'asiento' ? escHtml(p.concepto || '') : `${tipoDoc} ${escHtml(p.numero || '')}`;
            const dif = Number(p.diferencia || 0);
            const salto = ok ? '' : `<div class="text-danger" style="font-size:.7rem;" title="Lo que esta fila descuadra">${dif > 0 ? '+' : ''}${fmtMoney(dif)}</div>`;
            return `<tr class="${ok ? 'cb-fila-ok' : 'cb-fila-dif'}">
                <td class="ps-3"><span class="badge bg-${c.cls} bg-opacity-10 text-${c.cls === 'warning' ? 'warning-emphasis' : c.cls} border border-${c.cls} border-opacity-25" title="${escHtml(c.ayuda)}">${c.txt}</span></td>
                <td class="fw-medium text-truncate" style="max-width:240px;" title="${doc}">${doc}</td>
                <td class="text-nowrap">${p.fecha_doc ? fmtDateDisplay(p.fecha_doc) : vacio}</td>
                <td class="text-end text-nowrap">${celdaMonto(p.monto_doc, p.efecto_doc)}</td>
                <td class="text-nowrap">${asiento}</td>
                <td class="text-nowrap">${p.fecha_asiento ? fmtDateDisplay(p.fecha_asiento) : vacio}</td>
                <td class="text-end text-nowrap">${celdaMonto(p.monto_asiento, p.efecto_contable)}</td>
                <td class="text-end text-nowrap cb-col-saldo">${fmtMoney(p.saldo_libros)}</td>
                <td class="text-end text-nowrap cb-col-saldo">${fmtMoney(p.saldo_contable)}</td>
                <td class="text-end text-nowrap pe-3">${celdaDif(p.diferencia_acumulada)}${salto}</td>
            </tr>`;
        }).join('');
        const filaSaldo = (txt, libros, contable, dif) => `<tr class="fw-bold cb-comp-total">
                <td class="ps-3" colspan="7">${txt}</td>
                <td class="text-end text-nowrap cb-col-saldo">${fmtMoney(libros)}</td>
                <td class="text-end text-nowrap cb-col-saldo">${fmtMoney(contable)}</td>
                <td class="text-end text-nowrap pe-3">${celdaDif(dif)}</td>
            </tr>`;
        const nPartidas = (d.partidas || []).length;
        const nDif = Number(d.partidas_con_diferencia || 0);

        const cuadra = Math.abs(d.fin.diferencia) < 0.005;
        return `
            <div class="p-2 border rounded-3 bg-light mb-2 small d-flex flex-wrap gap-3">
                <div><span class="text-muted">Cuenta contable:</span> <span class="fw-bold">${cuenta}</span></div>
                <div><span class="text-muted">Período:</span> <span class="fw-bold">${fmtDateDisplay(d.fecha_inicio)} al ${fmtDateDisplay(d.fecha_fin)}</span></div>
                <div>${cuadra
                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25"><i class="bi bi-check-circle-fill"></i> Cuadra con la contabilidad</span>'
                    : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><i class="bi bi-exclamation-circle-fill"></i> No cuadra con la contabilidad</span>'}</div>
            </div>
            ${avisoCompartida}
            <div class="card cb-comp-card border-0 shadow-sm rounded-3 mb-2">
                <table class="table table-hover table-sm small mb-0">
                    <thead>
                        <tr><th class="ps-3">Concepto</th><th class="text-end">Según Ingresos/Egresos</th><th class="text-end">Según Contabilidad</th><th class="text-end pe-3">Diferencia</th></tr>
                    </thead>
                    <tbody>
                        <tr><td class="ps-3">Saldo al inicio del período</td><td class="text-end text-nowrap">${fmtMoney(d.inicio.libros)}</td><td class="text-end text-nowrap">${fmtMoney(d.inicio.contable)}</td><td class="text-end text-nowrap pe-3">${celdaDif(d.inicio.diferencia)}</td></tr>
                        <tr><td class="ps-3">Movimiento del período</td><td class="text-end text-nowrap">${fmtMoney(movLibros)}</td><td class="text-end text-nowrap">${fmtMoney(movCont)}</td><td class="text-end text-nowrap pe-3">${celdaDif(d.diferencia_periodo)}</td></tr>
                        <tr class="fw-bold cb-comp-total"><td class="ps-3">Saldo al final del período</td><td class="text-end text-nowrap">${fmtMoney(d.fin.libros)}</td><td class="text-end text-nowrap">${fmtMoney(d.fin.contable)}</td><td class="text-end text-nowrap pe-3">${celdaDif(d.fin.diferencia)}</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="form-text mb-2">
                "Según Ingresos/Egresos" es el saldo en libros: saldo inicial de Saldos Iniciales más todos los cobros, pagos y traspasos,
                con los cheques desde que se emiten (igual que la contabilidad). Por eso puede diferir del saldo de esta pantalla,
                que solo cuenta un cheque cuando tiene Fecha Banco.
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3 mb-1">
                <h6 class="fw-bold small mb-0">Movimientos del período, saldo por saldo
                    <span class="text-muted fw-normal">(${nPartidas}${nDif ? `, ${nDif} con diferencia` : ''})</span></h6>
                ${nDif && nDif < nPartidas ? `<div class="form-check form-switch small mb-0 ms-auto">
                    <input class="form-check-input" type="checkbox" id="cb-comp-solo-dif"
                           onchange="document.getElementById('cb-comp-mayor').classList.toggle('cb-solo-dif', this.checked)">
                    <label class="form-check-label" for="cb-comp-solo-dif">Ver solo las filas con diferencia</label>
                </div>` : ''}
            </div>
            <div class="mb-2">${chips || '<span class="text-success small"><i class="bi bi-check-circle me-1"></i>No hay partidas con diferencia en el período.</span>'}</div>
            ${Math.abs(d.inicio.diferencia) >= 0.005 ? `<div class="alert alert-warning py-2 px-3 small mb-2"><i class="bi bi-info-circle me-1"></i>
                El período ya <strong>empieza descuadrado</strong> en ${fmtMoney(d.inicio.diferencia)}: esa diferencia viene de antes de la fecha de inicio
                (por ejemplo, la apertura migrada o un saldo inicial distinto en Saldos Iniciales). Para encontrar la fila que la causa,
                ponga como fecha de inicio el comienzo de las operaciones y vuelva a comprobar.</div>` : ''}
            <div class="card cb-comp-card cb-comp-partidas border-0 shadow-sm rounded-3" id="cb-comp-mayor">
              <div class="cb-comp-mayor-wrap">
                <table class="table table-hover table-sm small mb-0">
                    <thead>
                        <tr><th class="ps-3">Situación</th><th>Documento</th><th>Fecha doc.</th><th class="text-end">Monto doc.</th>
                            <th>Asiento</th><th>Fecha asiento</th><th class="text-end">Monto contable</th>
                            <th class="text-end cb-col-saldo">Saldo Ing./Egr.</th><th class="text-end cb-col-saldo">Saldo contable</th>
                            <th class="text-end pe-3">Diferencia acum.</th></tr>
                    </thead>
                    <tbody>
                        ${filaSaldo('Saldo al inicio del período', d.inicio.libros, d.inicio.contable, d.inicio.diferencia)}
                        ${filas || `<tr><td colspan="10" class="text-center text-muted py-3">No hay movimientos en el período.</td></tr>`}
                        ${d.truncado ? '' : filaSaldo('Saldo al final del período', d.fin.libros, d.fin.contable, d.fin.diferencia)}
                    </tbody>
                </table>
              </div>
            </div>
            ${d.truncado ? `<div class="small text-warning mt-1">Se muestran los primeros ${Number(d.limite || 0).toLocaleString('en-US')} movimientos; acote el período para ver el resto.</div>` : ''}`;
    }

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
        document.getElementById('cb-hist-modal-loader')?.classList.remove('d-none');
        try {
            const json = await fetchJson(`${CB_URL_BASE}/listarConciliacionesAjax?forma=${state.forma}&consolidado=${state.consolidado ? 1 : 0}`);
            renderConciliaciones(json.ok ? json.data : []);
        } catch (e) {
            console.error(e);
        } finally {
            document.getElementById('cb-hist-modal-loader')?.classList.add('d-none');
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
        // Mismo indicador que el buscador (FiltrosModal): la tabla se atenúa mientras carga
        // (también al paginar, ordenar o cambiar los filtros de la tarjeta), en lugar de
        // reemplazarse por una fila con spinner.
        tbody.classList.add('fm-cargando-target');

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
            // Sin paginación: el listado trae todo el período; aquí solo va el total.
            document.getElementById('cb-pagination-info').textContent = json.info;
            document.getElementById('cb-btn-pdf').href = json.pdf_url;
            document.getElementById('cb-btn-excel').href = json.excel_url;
        } catch (e) {
            console.error(e);
            if (miSeq === searchRequestSeq) {
                tbody.innerHTML = '<tr><td colspan="13" class="text-center py-5 text-danger">Error de red o servidor.</td></tr>';
            }
        } finally {
            // Solo la última búsqueda apaga el indicador (una respuesta vieja no lo quita
            // mientras la nueva sigue en curso).
            if (miSeq === searchRequestSeq) tbody.classList.remove('fm-cargando-target');
        }
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
        // Quién está detrás del movimiento: la etiqueta sigue al tipo de entidad de la línea
        // (Cliente / Proveedor / Empleado); si no viene, la genérica. El nombre sale del
        // beneficiario del cheque o, si no hay, del tercero del asiento/documento.
        const ETIQUETA_ENTIDAD = { cliente: 'Cliente', proveedor: 'Proveedor', empleado: 'Empleado' };
        document.getElementById('cbm-info-beneficiario-label').textContent =
            (ETIQUETA_ENTIDAD[row.tipo_entidad] || 'Beneficiario / Cliente') + ':';
        document.getElementById('cbm-info-beneficiario').textContent = row.beneficiario_cheque || row.nombre_entidad || '—';
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
        // Cheque: solo la Fecha Banco REALMENTE registrada (fecha_banco_manual). La columna
        // fecha_banco del listado cae a la fecha del movimiento cuando no se ha conciliado:
        // precargarla en un cheque dejaba el campo lleno sin que nadie lo hubiera conciliado y,
        // al guardar cualquier otro cambio, lo marcaba como cobrado sin querer.
        // Transferencia, depósito o débito: se hacen efectivos el día del documento, así que su
        // Fecha Banco es esa (o la que se haya registrado a mano) y no queda pendiente.
        document.getElementById('cbm-fecha-banco').value = fmtDateInput(
            tipo === 'CHEQUE' ? row.fecha_banco_manual : (row.fecha_banco_manual || row.fecha_banco));
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
    function escHtml(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    /** Días entre hoy y la fecha del cheque (negativo = ya pasó). */
    function diasHastaFecha(v) {
        if (!v) return null;
        const f = new Date(String(v).substring(0, 10) + 'T00:00:00');
        if (isNaN(f.getTime())) return null;
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        return Math.round((f - hoy) / 86400000);
    }

    /**
     * Estado del cheque en el modal: con la fecha cumplida (y sin Fecha Banco, que es lo
     * único que devuelve el servidor en ese caso) → "Por cobrar"; dentro de la ventana
     * de aviso → "Vence en N días". Mismos criterios que el aviso del navbar.
     */
    function badgePosfechado(fecha, diasPorVencer) {
        const d = diasHastaFecha(fecha);
        if (d === null) return '';
        if (d <= 0) return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-1">Por cobrar</span>';
        if (d <= diasPorVencer) {
            const txt = d === 1 ? 'Vence mañana' : `Vence en ${d} días`;
            return `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 ms-1">${txt}</span>`;
        }
        return '';
    }

    /**
     * Cheque de un ingreso/egreso migrado del sistema anterior: se lista si su fecha es
     * futura, pero sin etiqueta, porque el aviso del navbar no los cuenta.
     */
    function esMigrado(r) {
        return r.es_migrado === true || r.es_migrado === 't' || r.es_migrado === 1;
    }

    function renderPosfechados(tbodyId, rows, diasPorVencer) {
        const tbody = document.getElementById(tbodyId);
        if (!rows || !rows.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">No hay cheques posfechados.</td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const monto = parseFloat(r.debe) > 0 ? r.debe : r.haber;
            return `<tr>
                <td class="text-nowrap">${fmtDateDisplay(r.fecha_cheque)}${esMigrado(r) ? '' : badgePosfechado(r.fecha_cheque, diasPorVencer)}</td>
                <td>${escHtml(r.numero_cheque)}</td>
                <td>${escHtml(r.forma_pago_nombre)}</td>
                <td>${escHtml(r.nombre_entidad)}</td>
                <td class="text-end">$${Number(monto || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
            </tr>`;
        }).join('');
    }

    /**
     * @param {string} [pestana] 'recibidos' | 'emitidos': pestaña a mostrar al abrir (la usa
     *        el aviso del navbar, vía CB_ABRIR_POSFECHADOS). En 'emitidos', si no hay cheques a
     *        proveedores pero sí a empleados, se abre la de empleados.
     */
    window.CB_abrirModalPosfechados = async function (pestana) {
        new bootstrap.Modal(document.getElementById('modalPosfechadosCB')).show();
        document.getElementById('cb-posf-modal-loader')?.classList.remove('d-none');
        try {
            const [recibidos, emitidos, emitidosEmp] = await Promise.all([
                fetchJson(`${CB_URL_BASE}/chequesPosfechadosAjax?direccion=RECIBIDO`),
                fetchJson(`${CB_URL_BASE}/chequesPosfechadosAjax?direccion=EMITIDO`),
                fetchJson(`${CB_URL_BASE}/chequesPosfechadosAjax?direccion=EMITIDO_EMPLEADO`),
            ]);
            const diasPorVencer = parseInt(recibidos.dias_por_vencer, 10) || 5;
            const rowsRec = recibidos.ok ? recibidos.data : [];
            const rowsEmi = emitidos.ok ? emitidos.data : [];
            const rowsEmp = emitidosEmp.ok ? emitidosEmp.data : [];
            renderPosfechados('cb-tbody-posf-recibidos', rowsRec, diasPorVencer);
            renderPosfechados('cb-tbody-posf-emitidos', rowsEmi, diasPorVencer);
            renderPosfechados('cb-tbody-posf-emitidos-emp', rowsEmp, diasPorVencer);

            let destino = null;
            if (pestana === 'recibidos') destino = '#cb-tab-recibidos';
            else if (pestana === 'emitidos') destino = (!rowsEmi.length && rowsEmp.length) ? '#cb-tab-emitidos-emp' : '#cb-tab-emitidos';
            if (destino) {
                const btn = document.querySelector(`#cb-tabs-posfechados [data-bs-target="${destino}"]`);
                if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
            }
        } catch (e) {
            console.error(e);
        } finally {
            document.getElementById('cb-posf-modal-loader')?.classList.add('d-none');
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

        // Llegada desde el aviso de cheques posfechados del navbar: abrir el modal en su
        // pestaña. La pestaña viene de sesión (CB_ABRIR_POSFECHADOS), no de la URL.
        const posf = window.CB_ABRIR_POSFECHADOS || '';
        if (posf === 'recibidos' || posf === 'emitidos') {
            window.CB_abrirModalPosfechados(posf);
        }
    });
})();
