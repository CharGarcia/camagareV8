/**
 * Comprobación con Contabilidad — pantalla común (solo lectura).
 *
 * Compara el saldo que lleva un módulo con el de sus cuentas contables al inicio y al fin de
 * un período, y lista documento por documento lo que cada lado movió (mayor comparado), con
 * el mismo diseño que la comprobación de Control Bancario. El cálculo es del servidor
 * (App\Services\ComprobacionContableService); el modal está en
 * app/views/partials/comprobacion_contable_modal.php.
 *
 * Uso:
 *   CMG_comprobacionContable.abrir({
 *       url: `${BASE_URL}/modulos/cuentas_por_cobrar/comprobacionContableAjax`,
 *       modulo: 'Cuentas por Cobrar',          // nombre del módulo en los textos
 *       etiquetaLibros: 'Según Cartera',        // columna del lado documento
 *       nota: 'Texto que explica qué es el saldo del módulo',
 *       desde: '2026-01-01', hasta: '2026-12-31',
 *       tipos: { factura_venta: 'Factura', … }  // nombre de cada tipo de documento
 *   });
 */
(function () {
    'use strict';

    const TIPOS_BASE = {
        factura_venta: 'Factura', recibo_venta: 'Recibo', nota_credito: 'Nota de crédito',
        nota_debito: 'Nota de débito', retencion_venta: 'Retención', ingreso: 'Ingreso',
        compra: 'Compra', liquidacion_compra: 'Liquidación', retencion_compra: 'Retención',
        egreso: 'Egreso', importacion: 'Importación', saldo_inicial: 'Saldos iniciales', traspaso: 'Traspaso',
        conciliacion_tarjetas: 'Liquidación de tarjetas', activo_fijo: 'Activo fijo', depreciacion: 'Depreciación',
        asiento: 'Asiento',
    };

    let opciones = {};

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function dinero(v) {
        return '$' + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    /** Fecha d-m-Y (regla de fechas del sistema). */
    function fecha(v) {
        if (!v) return '—';
        const p = String(v).substring(0, 10).split('-');
        return p.length === 3 ? `${p[2]}-${p[1]}-${p[0]}` : esc(v);
    }

    function celdaDif(v) {
        const n = Number(v || 0);
        return `<span class="fw-bold ${Math.abs(n) < 0.005 ? 'text-success' : 'text-danger'}">${dinero(n)}</span>`;
    }

    function badge(cls, texto, ayuda) {
        const txt = cls === 'warning' ? 'warning-emphasis' : cls;
        return `<span class="badge bg-${cls} bg-opacity-10 text-${txt} border border-${cls} border-opacity-25" title="${esc(ayuda || '')}">${texto}</span>`;
    }

    function clases() {
        const m = opciones.modulo || 'el módulo';
        return {
            cuadra:            { txt: 'Cuadra', cls: 'success', ayuda: 'El documento y su asiento mueven lo mismo en el período.' },
            solo_documento:    { txt: 'Documento sin asiento', cls: 'warning', ayuda: `El documento suma en ${m}, pero no tiene ningún asiento contabilizado (borrador, pendiente de contabilizar, o un movimiento que no genera asiento, como un ajuste).` },
            sin_cuenta_modulo: { txt: `Asiento sin ${opciones.etiquetaCuenta || 'las cuentas comparadas'}`, cls: 'warning', ayuda: `El documento tiene asiento, pero ninguna de sus líneas afecta ${opciones.etiquetaCuenta ? 'la ' + opciones.etiquetaCuenta : 'las cuentas comparadas'} (p. ej. una factura migrada del sistema anterior, que solo registró la venta sin el costo, o una cuenta mal configurada). El número del asiento lo abre.` },
            solo_contabilidad: { txt: 'Solo en contabilidad', cls: 'info', ayuda: `Asiento sin un documento de ${m} detrás (manual, migrado o de otro módulo).` },
            fuera_modulo:      { txt: 'Documento que no suma aquí', cls: 'danger', ayuda: `El asiento es de un documento que ${m} no cuenta: anulado, eliminado, en un estado que no suma, o que no se aplica a ningún documento del módulo.` },
            monto_distinto:    { txt: 'Monto distinto', cls: 'danger', ayuda: 'El documento y su asiento mueven montos distintos en estas cuentas.' },
            fecha_distinta:    { txt: 'Fecha en otro período', cls: 'secondary', ayuda: 'El documento y su asiento tienen fechas que caen en períodos distintos.' },
            // Propias del cruce de bancos (Control Bancario), visto desde Estados Financieros.
            documento_anulado: { txt: 'Asiento de documento anulado', cls: 'danger', ayuda: 'El ingreso/egreso está anulado o eliminado pero su asiento sigue contabilizado.' },
            otra_cuenta:       { txt: 'Cobrado/pagado con otra cuenta', cls: 'secondary', ayuda: 'El asiento mueve esta cuenta contable, pero el documento se registró con otra forma de pago.' },
        };
    }

    function el(id) { return document.getElementById(id); }

    async function cargar() {
        const desde = el('cc-desde').value;
        const hasta = el('cc-hasta').value;
        const cont = el('cc-contenido');
        if (!desde || !hasta) {
            cont.innerHTML = '<div class="alert alert-warning small mb-0">Indique las fechas Desde y Hasta.</div>';
            return;
        }
        el('cc-loader').classList.remove('d-none');
        try {
            const sep = opciones.url.includes('?') ? '&' : '?';
            const resp = await fetch(`${opciones.url}${sep}${new URLSearchParams({ fecha_inicio: desde, fecha_fin: hasta })}`,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const tipo = resp.headers.get('content-type') || '';
            if (!tipo.includes('application/json')) throw new Error(`HTTP ${resp.status}`);
            const json = await resp.json();
            cont.innerHTML = json.ok
                ? render(json.data)
                : `<div class="alert alert-danger small mb-0">${esc(json.error || 'No se pudo hacer la comprobación.')}</div>`;
        } catch (e) {
            console.error(e);
            cont.innerHTML = '<div class="alert alert-danger small mb-0">Error de red o del servidor.</div>';
        } finally {
            el('cc-loader').classList.add('d-none');
        }
    }

    function render(d) {
        if (d.sin_cuentas) {
            return `<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-1"></i>
                No hay cuentas contables configuradas para <strong>${esc(d.conceptos || opciones.modulo)}</strong>:
                no hay contabilidad contra la cual comparar. Se asignan en <em>Configuración Contable</em>.</div>`;
        }
        const CL = clases();
        const tipos = Object.assign({}, TIPOS_BASE, opciones.tipos || {});
        const libros = esc(opciones.etiquetaLibros || 'Según el módulo');
        const vacio = '<span class="text-muted">—</span>';
        const cuadra = Math.abs(d.fin.diferencia) < 0.005;

        const filasCuentas = (d.cuentas || []).map(c => `<tr>
                <td class="ps-3">${esc(c.codigo)} — ${esc(c.nombre)}</td>
                <td class="text-end text-nowrap">${dinero(c.saldo_ini)}</td>
                <td class="text-end text-nowrap pe-3">${dinero(c.saldo_fin)}</td></tr>`).join('');

        const chips = Object.keys(d.resumen_clases || {}).map(k => {
            const c = CL[k] || { txt: k, cls: 'secondary', ayuda: '' };
            const r = d.resumen_clases[k];
            return `<span class="me-1 mb-1 d-inline-block">${badge(c.cls, `${c.txt}: ${r.cantidad} · ${dinero(r.diferencia)}`, c.ayuda)}</span>`;
        }).join('');

        // Monto de un lado: si su fecha cae fuera del período no suma aquí, y se ve tachado.
        const celdaMonto = (monto, efecto) => {
            if (monto === null || monto === undefined) return vacio;
            const fuera = Math.abs(Number(efecto || 0)) < 0.005 && Math.abs(Number(monto)) >= 0.005;
            return fuera
                ? `<span class="text-muted text-decoration-line-through" title="Su fecha cae fuera del período: no suma aquí">${dinero(monto)}</span>`
                : dinero(monto);
        };

        const filas = (d.partidas || []).map(p => {
            let c = CL[p.clase] || { txt: p.clase, cls: 'secondary', ayuda: '' };
            // Bancos: se nombra la forma con que se registró el documento, que es la causa.
            if (p.clase === 'otra_cuenta' && p.formas_doc) {
                c = { ...c, txt: `${p.tipo === 'ingreso' ? 'Cobrado' : 'Pagado'} con ${esc(p.formas_doc)}` };
            }
            const ok = p.clase === 'cuadra';
            const nombreTipo = tipos[p.tipo] || p.tipo;
            const doc = p.tipo === 'asiento'
                ? esc(p.concepto || 'Asiento')
                : p.tipo === 'saldo_inicial'
                    ? 'Saldos iniciales / apertura'
                    : `${esc(nombreTipo)} ${esc(p.numero || (p.id_doc ? '#' + p.id_doc : ''))}`;
            // Sin línea en estas cuentas pero con asiento del documento en otras: se enlaza ese
            // asiento (atenuado) para ver qué registró.
            const asiento = p.id_asiento
                ? `<a href="#" onclick="event.preventDefault(); ASIENTO_abrirModal(${parseInt(p.id_asiento, 10)});" title="Ver asiento">${esc(p.numero_asiento || 'Asiento')}</a>`
                : (p.id_asiento_doc
                    ? `<a href="#" class="text-muted" onclick="event.preventDefault(); ASIENTO_abrirModal(${parseInt(p.id_asiento_doc, 10)});" title="Asiento del documento: no afecta estas cuentas">${esc(p.numero_asiento_doc || 'Asiento')}</a>`
                    : vacio);
            const dif = Number(p.diferencia || 0);
            const salto = ok ? '' : `<div class="text-danger" style="font-size:.7rem;" title="Lo que esta fila descuadra">${dif > 0 ? '+' : ''}${dinero(dif)}</div>`;
            return `<tr class="${ok ? 'cc-fila-ok' : 'cc-fila-dif'}">
                <td class="ps-3">${badge(c.cls, c.txt, c.ayuda)}</td>
                <td class="fw-medium text-truncate" style="max-width:260px;" title="${doc}">${doc}</td>
                <td class="text-nowrap">${p.fecha_doc ? fecha(p.fecha_doc) : vacio}</td>
                <td class="text-end text-nowrap">${celdaMonto(p.monto_doc, p.efecto_doc)}</td>
                <td class="text-nowrap">${asiento}</td>
                <td class="text-nowrap">${p.fecha_asiento ? fecha(p.fecha_asiento) : vacio}</td>
                <td class="text-end text-nowrap">${celdaMonto(p.monto_asiento, p.efecto_contable)}</td>
                <td class="text-end text-nowrap cc-col-saldo">${dinero(p.saldo_libros)}</td>
                <td class="text-end text-nowrap cc-col-saldo">${dinero(p.saldo_contable)}</td>
                <td class="text-end text-nowrap pe-3">${celdaDif(p.diferencia_acumulada)}${salto}</td>
            </tr>`;
        }).join('');

        const filaSaldo = (txt, l, c, dif) => `<tr class="fw-bold cc-total">
                <td class="ps-3" colspan="7">${txt}</td>
                <td class="text-end text-nowrap cc-col-saldo">${dinero(l)}</td>
                <td class="text-end text-nowrap cc-col-saldo">${dinero(c)}</td>
                <td class="text-end text-nowrap pe-3">${celdaDif(dif)}</td></tr>`;
        const nPartidas = (d.partidas || []).length;
        const nDif = Number(d.partidas_con_diferencia || 0);

        return `
            <div class="p-2 border rounded-3 bg-light mb-2 small d-flex flex-wrap gap-3">
                <div><span class="text-muted">Período:</span> <span class="fw-bold">${fecha(d.fecha_inicio)} al ${fecha(d.fecha_fin)}</span></div>
                <div>${cuadra
                    ? badge('success', '<i class="bi bi-check-circle-fill"></i> Cuadra con la contabilidad')
                    : badge('danger', '<i class="bi bi-exclamation-circle-fill"></i> No cuadra con la contabilidad')}</div>
            </div>
            <div class="card cc-card border-0 shadow-sm rounded-3 mb-2">
                <table class="table table-hover table-sm small mb-0">
                    <thead><tr><th class="ps-3">Concepto</th><th class="text-end">${libros}</th><th class="text-end">Según Contabilidad</th><th class="text-end pe-3">Diferencia</th></tr></thead>
                    <tbody>
                        <tr><td class="ps-3">Saldo al inicio del período</td><td class="text-end text-nowrap">${dinero(d.inicio.libros)}</td><td class="text-end text-nowrap">${dinero(d.inicio.contable)}</td><td class="text-end text-nowrap pe-3">${celdaDif(d.inicio.diferencia)}</td></tr>
                        <tr><td class="ps-3">Movimiento del período</td><td class="text-end text-nowrap">${dinero(d.fin.libros - d.inicio.libros)}</td><td class="text-end text-nowrap">${dinero(d.fin.contable - d.inicio.contable)}</td><td class="text-end text-nowrap pe-3">${celdaDif(d.diferencia_periodo)}</td></tr>
                        <tr class="fw-bold cc-total"><td class="ps-3">Saldo al final del período</td><td class="text-end text-nowrap">${dinero(d.fin.libros)}</td><td class="text-end text-nowrap">${dinero(d.fin.contable)}</td><td class="text-end text-nowrap pe-3">${celdaDif(d.fin.diferencia)}</td></tr>
                    </tbody>
                </table>
            </div>
            ${opciones.nota ? `<div class="form-text mb-2">${opciones.nota}</div>` : ''}
            <div class="card cc-card border-0 shadow-sm rounded-3 mb-2">
                <table class="table table-sm small mb-0">
                    <thead><tr><th class="ps-3">Cuentas contables comparadas</th><th class="text-end">Saldo al inicio</th><th class="text-end pe-3">Saldo al final</th></tr></thead>
                    <tbody>${filasCuentas}</tbody>
                </table>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3 mb-1">
                <h6 class="fw-bold small mb-0">Movimientos del período, saldo por saldo
                    <span class="text-muted fw-normal">(${nPartidas}${nDif ? `, ${nDif} con diferencia` : ''})</span></h6>
                ${nDif && nDif < nPartidas ? `<div class="form-check form-switch small mb-0 ms-auto">
                    <input class="form-check-input" type="checkbox" id="cc-solo-dif"
                           onchange="document.getElementById('cc-mayor').classList.toggle('cc-solo-dif', this.checked)">
                    <label class="form-check-label" for="cc-solo-dif">Ver solo las filas con diferencia</label>
                </div>` : ''}
            </div>
            <div class="mb-2">${chips || '<span class="text-success small"><i class="bi bi-check-circle me-1"></i>No hay partidas con diferencia en el período.</span>'}</div>
            ${Math.abs(d.inicio.diferencia) >= 0.005 ? `<div class="alert alert-warning py-2 px-3 small mb-2"><i class="bi bi-info-circle me-1"></i>
                El período ya <strong>empieza descuadrado</strong> en ${dinero(d.inicio.diferencia)}: esa diferencia viene de antes de la fecha Desde.
                Para encontrar la fila que la causa, ponga como fecha Desde el comienzo de las operaciones y vuelva a comprobar.</div>` : ''}
            <div class="card cc-card cc-partidas border-0 shadow-sm rounded-3" id="cc-mayor">
              <div class="cc-mayor-wrap">
                <table class="table table-hover table-sm small mb-0">
                    <thead><tr><th class="ps-3">Situación</th><th>Documento</th><th>Fecha doc.</th><th class="text-end">Monto doc.</th>
                        <th>Asiento</th><th>Fecha asiento</th><th class="text-end">Monto contable</th>
                        <th class="text-end cc-col-saldo">Saldo ${libros.replace(/^Según\s+/i, '')}</th><th class="text-end cc-col-saldo">Saldo contable</th>
                        <th class="text-end pe-3">Diferencia acum.</th></tr></thead>
                    <tbody>
                        ${filaSaldo('Saldo al inicio del período', d.inicio.libros, d.inicio.contable, d.inicio.diferencia)}
                        ${filas || '<tr><td colspan="10" class="text-center text-muted py-3">No hay movimientos en el período.</td></tr>'}
                        ${d.truncado ? '' : filaSaldo('Saldo al final del período', d.fin.libros, d.fin.contable, d.fin.diferencia)}
                    </tbody>
                </table>
              </div>
            </div>
            ${d.truncado ? `<div class="small text-warning mt-1">Se muestran los primeros ${Number(d.limite || 0).toLocaleString('en-US')} movimientos; acote el período para ver el resto.</div>` : ''}`;
    }

    // El asiento se abre ENCIMA de este modal. app.css fija todos los .modal en 5060
    // con !important: sin subirlo, el asiento podría quedar detrás (ver Proformas).
    // Lo mismo para este modal cuando se abre sobre otro (p. ej. el Cuadre con módulos de
    // Estados Financieros): cada uno queda 20 por encima del modal abierto más alto.
    function elevarSobreAbiertos(modal) {
        const abiertos = [...document.querySelectorAll('.modal.show')].filter(m => m !== modal);
        if (!abiertos.length) return;
        const base = Math.max(...abiertos.map(m => parseInt(getComputedStyle(m).zIndex, 10) || 5060));
        modal.style.setProperty('z-index', String(base + 20), 'important');
        setTimeout(() => {
            const fondos = document.querySelectorAll('.modal-backdrop');
            fondos[fondos.length - 1]?.style.setProperty('z-index', String(base + 15), 'important');
        }, 0);
    }
    document.addEventListener('show.bs.modal', (ev) => {
        if (ev.target.id === 'modalComprobacionContable') {
            elevarSobreAbiertos(ev.target);
        } else if (ev.target.id === 'modalAsientoContable' && el('modalComprobacionContable')?.classList.contains('show')) {
            elevarSobreAbiertos(ev.target);
        }
    });

    window.CMG_comprobacionContable = {
        abrir(opts) {
            opciones = opts || {};
            el('cc-titulo-modulo').textContent = opciones.modulo ? `— ${opciones.modulo}` : '';
            const hoy = (window.CMG_fechaLocal ? window.CMG_fechaLocal(new Date()) : new Date().toISOString().substring(0, 10));
            const hasta = opciones.hasta || hoy;
            el('cc-hasta').value = hasta;
            el('cc-desde').value = opciones.desde || `${hasta.substring(0, 4)}-01-01`;
            el('cc-contenido').innerHTML = '<div style="min-height:160px;"></div>';
            bootstrap.Modal.getOrCreateInstance(el('modalComprobacionContable')).show();
            cargar();
        },
        mostrar: cargar,
    };
})();
