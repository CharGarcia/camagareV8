/**
 * Declaración de Impuesto a la Renta — pantalla.
 * Calcula por AJAX con los filtros/ajustes de la tarjeta de control y pinta las pestañas.
 * Los ajustes manuales (anticipos, créditos, gastos no deducibles…) se escriben en la misma
 * tabla de liquidación (inputs amarillos) y viajan en cada recálculo y en las exportaciones.
 * Desde aquí también se clasifican las compras de gasto personal por rubro y se asigna el
 * casillero SRI de cada cuenta del plan (con permiso de modificar en el módulo).
 */
(function () {
    'use strict';

    const CFG = window.RENTA_CFG || {};
    const url = (accion) => `${CFG.base}/${CFG.ruta}/${accion}`;
    const money = (v) => (Number(v) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const $ = (id) => document.getElementById(id);
    // Avisos: el sistema usa SweetAlert2 (window.Toast = mixin de esquina; Swal para errores).
    const aviso = (texto, tipo) => {
        const icono = tipo === 'danger' ? 'error' : 'success';
        if (window.Toast && typeof window.Toast.fire === 'function') { window.Toast.fire({ icon: icono, title: texto }); return; }
        if (window.Swal && typeof window.Swal.fire === 'function') { window.Swal.fire({ icon: icono, text: texto, timer: tipo === 'danger' ? undefined : 1800, showConfirmButton: tipo === 'danger' }); return; }
        if (tipo === 'danger') alert(texto);
    };

    // Ajustes escritos en la tabla (clave → valor), se conservan entre recálculos.
    const ajustes = {};
    const CLAVE_AJUSTE = {
        otros_ingresos: 'otros_ingresos',
        otras_deducciones: 'otras_deducciones',
        gastos_no_deducibles: 'gastos_no_deducibles',
        rentas_exentas: 'rentas_exentas',
        deducciones_adicionales: 'deducciones_adicionales',
        amortizacion_perdidas: 'amortizacion_perdidas',
        anticipo_pagado: 'anticipo_pagado',
        credito_anterior: 'credito_anios_anteriores',
        otros_creditos: 'otros_creditos',
        participacion: 'participacion_trabajadores_pct',
        impuesto_causado: 'tarifa_pct'
    };
    let ultimoCalculo = null;
    let calculando = false;

    function params() {
        const p = new URLSearchParams();
        const form = $('form-filtros-renta');
        new FormData(form).forEach((v, k) => { if (v !== '') p.set(k, v); });
        Object.keys(ajustes).forEach((k) => {
            if (ajustes[k] !== '' && ajustes[k] !== null && ajustes[k] !== undefined) p.set(k, ajustes[k]);
        });
        return p;
    }

    function leerAjustesTabla() {
        document.querySelectorAll('#renta-liquidacion input.renta-edit').forEach((inp) => {
            const k = inp.dataset.ajuste;
            if (!k) return;
            ajustes[k] = inp.value === '' ? '' : inp.value;
            // Participación / tarifa viven también en la tarjeta de control: se sincronizan.
            if (k === 'participacion_trabajadores_pct' && $('renta-participacion')) $('renta-participacion').value = inp.value;
            if (k === 'tarifa_pct' && $('renta-tarifa')) $('renta-tarifa').value = inp.value;
        });
    }

    async function post(accion, datos) {
        const body = new URLSearchParams();
        Object.keys(datos).forEach((k) => body.set(k, datos[k] ?? ''));
        const r = await fetch(url(accion), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'No se pudo guardar.');
        return j;
    }

    window.RENTA_calcular = async function () {
        if (calculando) return;
        calculando = true;
        const btn = $('renta-btn-mostrar');
        btn.disabled = true;
        leerAjustesTabla();
        try {
            const r = await fetch(url('calcularAjax') + '?' + params().toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const j = await r.json();
            if (!j.success) throw new Error(j.message || 'No se pudo calcular la declaración.');
            ultimoCalculo = j.data;
            pintar(j.data);
        } catch (e) {
            $('renta-avisos').innerHTML = `<div class="alert alert-danger py-1 px-2 small mb-0">${esc(e.message)}</div>`;
        } finally {
            calculando = false;
            btn.disabled = false;
        }
    };

    function inputSri(q) {
        if (!CFG.puedeEditar) return q.codigo_sri ? `<span class="badge bg-light text-dark border me-1">${esc(q.codigo_sri)}</span>` : '';
        return `<input type="text" class="form-control form-control-sm renta-sri me-1" maxlength="4" inputmode="numeric" value="${esc(q.codigo_sri || '')}" placeholder="cas." title="Casillero SRI de esta cuenta — Enter para guardar" data-id-cuenta="${q.id_cuenta}" data-id-empresa="${q.id_empresa}" data-original="${esc(q.codigo_sri || '')}">`;
    }

    function pintar(d) {
        // Avisos
        $('renta-avisos').innerHTML = (d.avisos || []).map((a) =>
            `<div class="alert alert-${a.tipo === 'warning' ? 'warning' : 'info'} py-1 px-2 small mb-1"><i class="bi bi-${a.tipo === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-1"></i>${esc(a.texto)}</div>`
        ).join('');

        // KPI
        const r = d.liquidacion.resumen;
        $('kpi-ingresos').textContent = money(r.total_ingresos);
        $('kpi-gastos').textContent = money(r.total_gastos);
        $('kpi-base').textContent = money(r.base_imponible);
        $('kpi-causado').textContent = money(r.impuesto_causado);
        $('kpi-retenciones').textContent = money(r.retenciones);
        if (Number(r.saldo_favor) > 0) {
            $('kpi-pagar').textContent = money(r.saldo_favor);
            $('kpi-pagar-label').textContent = 'Saldo a favor';
        } else {
            $('kpi-pagar').textContent = money(r.impuesto_pagar);
            $('kpi-pagar-label').textContent = 'Impuesto a pagar';
        }

        // Liquidación
        $('renta-liquidacion').innerHTML = d.liquidacion.lineas.map((l) => {
            if (l.seccion) return `<tr class="renta-sec"><td colspan="4">${esc(l.concepto)}</td></tr>`;
            const nota = l.nota ? ` <span class="nota">(${esc(l.nota)})</span>` : '';
            let valor;
            if (l.editable) {
                // Participación y tarifa se editan como porcentaje; el resto como monto.
                const esPct = l.clave === 'participacion' || l.clave === 'impuesto_causado';
                const clave = CLAVE_AJUSTE[l.clave];
                const actual = esPct ? (d.ajustes[clave] ?? '') : (d.ajustes[clave] ?? 0);
                valor = `<span class="me-1">${money(l.valor)}</span>` +
                    `<input type="number" step="0.01" min="0" class="form-control form-control-sm renta-edit bg-warning bg-opacity-25" data-ajuste="${clave}" value="${esc(actual)}" title="${esPct ? 'Porcentaje' : 'Valor'} — presione Mostrar para recalcular" ${esPct ? 'max="100" style="width:80px"' : ''}>` +
                    (esPct ? '<span class="nota ms-1">%</span>' : '');
            } else {
                valor = money(l.valor);
            }
            const cls = l.nivel === 0 ? 'renta-tot' : (l.nivel === 2 ? 'renta-sub' : '');
            return `<tr class="${cls}"><td>${esc(l.concepto)}${nota}</td><td class="cas">${esc(l.casillero)}</td><td class="signo">${esc(l.signo)}</td><td class="val">${valor}</td></tr>`;
        }).join('');
        document.querySelectorAll('#renta-liquidacion input.renta-edit').forEach((inp) => {
            inp.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); RENTA_calcular(); } });
        });

        // Resumen de documentos
        $('renta-resumen').innerHTML = (d.resumen_documentos || []).map((f) =>
            `<tr class="${f.total ? 'renta-tot' : (f.rubro ? 'renta-sub' : '')}"><td>${esc(f.concepto)}</td><td class="text-center">${f.cantidad === null ? '' : f.cantidad}</td><td class="val">${money(f.base)}</td><td class="val">${money(f.monto)}</td></tr>`
        ).join('');

        // Contador de gastos personales sin rubro (pestaña Clasificar)
        const badge = $('renta-badge-sin-rubro');
        if (badge) {
            const sin = (d.documentos && d.documentos.personal_rubros && d.documentos.personal_rubros.sin_rubro) ? Number(d.documentos.personal_rubros.sin_rubro.cantidad) : 0;
            badge.textContent = sin;
            badge.classList.toggle('d-none', sin === 0);
        }

        // Casilleros contables (cada cuenta con su casilla editable de código SRI)
        const tc = $('renta-casilleros');
        if (tc) {
            const c = d.contabilidad || { casilleros: [], sin_casillero: [] };
            const etqEst = (q) => q.establecimiento ? `<span class="badge bg-light text-muted border me-1">${esc(q.establecimiento)}</span>` : '';
            let html = (c.casilleros || []).map((k) =>
                `<tr><td class="cas">${esc(k.casillero)}</td><td>${esc(k.seccion)}</td><td><div class="renta-cuentas">${k.cuentas.map((q) => `<div class="d-flex align-items-center gap-1 py-0">${inputSri(q)}${etqEst(q)}<span>${esc(q.codigo)} ${esc(q.nombre)}</span> <span class="text-dark ms-auto">${money(q.valor)}</span></div>`).join('')}</div></td><td class="val fw-bold">${money(k.valor)}</td></tr>`
            ).join('');
            if ((c.sin_casillero || []).length) {
                html += `<tr class="renta-sec"><td colspan="4">Cuentas con saldo sin casillero SRI (no entran al formulario): escriba el casillero y presione Enter</td></tr>` +
                    c.sin_casillero.map((q) => `<tr><td class="cas text-danger">—</td><td>${esc(q.seccion)}</td><td><div class="d-flex align-items-center gap-1">${inputSri(q)}${etqEst(q)}<span>${esc(q.codigo)} ${esc(q.nombre)}</span></div></td><td class="val">${money(q.valor)}</td></tr>`).join('');
            }
            tc.innerHTML = html || '<tr><td colspan="4" class="text-center text-muted py-3">Sin asientos contabilizados en el ejercicio.</td></tr>';
            tc.querySelectorAll('input.renta-sri').forEach((inp) => {
                inp.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); inp.blur(); } });
                inp.addEventListener('change', () => RENTA_guardarCasillero(inp));
            });
        }

        // Fuentes del detalle
        const sel = $('renta-detalle-fuente');
        if (!sel.options.length) {
            Object.keys(d.fuentes_detalle || CFG.fuentes).forEach((k) => {
                const o = document.createElement('option');
                o.value = k;
                o.textContent = (d.fuentes_detalle || CFG.fuentes)[k];
                sel.appendChild(o);
            });
        }
        $('renta-detalle').innerHTML = '';
        $('renta-detalle-resumen').textContent = '';
        if (document.querySelector('#tab-detalle.active')) RENTA_detalle();
        if ($('renta-clasificar')) {
            $('renta-clasificar').innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">Presione Mostrar para cargar.</td></tr>';
            if (document.querySelector('#tab-clasificar.active')) RENTA_clasificar();
        }
    }

    // ── Casilleros: guardar el código SRI de una cuenta ──
    window.RENTA_guardarCasillero = async function (inp) {
        const nuevo = (inp.value || '').trim();
        if (nuevo === (inp.dataset.original || '')) return;
        if (nuevo !== '' && !/^[0-9]{3,4}$/.test(nuevo)) {
            aviso('El casillero SRI debe tener 3 o 4 dígitos.', 'danger');
            inp.value = inp.dataset.original || '';
            return;
        }
        inp.disabled = true;
        try {
            await post('asignarCodigoSriAjax', { id_cuenta: inp.dataset.idCuenta, id_empresa_cuenta: inp.dataset.idEmpresa, codigo_sri: nuevo });
            inp.dataset.original = nuevo;
            aviso('Casillero guardado. Recalculando…', 'success');
            await RENTA_calcular(); // la cuenta pasa a su casillero
        } catch (e) {
            aviso(e.message, 'danger');
            inp.value = inp.dataset.original || '';
        } finally {
            inp.disabled = false;
        }
    };

    // ── Clasificar gastos personales por rubro ──
    window.RENTA_clasificar = async function () {
        if (!ultimoCalculo || !$('renta-clasificar')) return;
        const filtro = $('renta-clasificar-filtro').value;
        const tb = $('renta-clasificar');
        tb.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">Cargando…</td></tr>';
        try {
            const r = await fetch(`${url('detalleAjax')}?anio=${encodeURIComponent(ultimoCalculo.anio)}&fuente=compras_personal`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const j = await r.json();
            if (!j.success) throw new Error(j.message || 'No se pudo cargar.');
            const filas = j.data.filter((x) => filtro === 'todas' || !x.rubro);
            const opciones = (sel) => '<option value="">Sin rubro</option>' + Object.keys(CFG.rubros || {}).map((k) => `<option value="${k}" ${sel === k ? 'selected' : ''}>${esc(CFG.rubros[k])}</option>`).join('');
            const colEst = CFG.consolidado ? (x) => `<td class="small text-muted">${esc(x.establecimiento)}</td>` : () => '';
            tb.innerHTML = filas.map((x) => {
                const ctrl = CFG.puedeEditar
                    ? `<select class="form-select form-select-sm renta-rubro" data-id="${x.id}" data-id-empresa="${x.id_empresa}" onchange="RENTA_guardarRubro(this, false)">${opciones(x.rubro)}</select>`
                    : esc(x.rubro_nombre);
                const btn = CFG.puedeEditar && x.id_proveedor
                    ? `<button type="button" class="btn btn-outline-primary btn-sm py-0" style="font-size:.7rem;" title="Poner este mismo rubro a todas las compras sin rubro de este proveedor en el ejercicio" onclick="RENTA_guardarRubro(this.closest('tr').querySelector('select.renta-rubro'), true)"><i class="bi bi-people"></i> Aplicar al proveedor</button>`
                    : '';
                return `<tr data-id="${x.id}">${colEst(x)}<td>${esc(x.fecha_emision)}</td><td>${esc(x.tipo_nombre)}</td><td>${esc(x.numero)}</td><td>${esc(x.tercero)}</td><td>${esc(x.identificacion)}</td><td class="val">${money(x.total)}</td><td>${ctrl}</td><td>${btn}</td></tr>`;
            }).join('') || `<tr><td colspan="9" class="text-center text-success py-3"><i class="bi bi-check-circle me-1"></i>${filtro === 'todas' ? 'No hay compras de gasto personal en el ejercicio.' : 'Todas las compras de gasto personal ya tienen rubro.'}</td></tr>`;
            $('renta-clasificar-resumen').textContent = `${filas.length} compra(s)` + (filtro === 'sin_rubro' ? ' sin rubro' : '') + ` de ${j.data.length} de gasto personal`;
        } catch (e) {
            tb.innerHTML = `<tr><td colspan="9" class="text-danger py-3">${esc(e.message)}</td></tr>`;
        }
    };

    window.RENTA_guardarRubro = async function (sel, todasProveedor) {
        if (!sel) return;
        const rubro = sel.value;
        if (todasProveedor && !rubro) { aviso('Elija primero un rubro en esa fila.', 'danger'); return; }
        sel.disabled = true;
        try {
            const j = await post('asignarRubroAjax', {
                id_compra: sel.dataset.id, id_empresa_compra: sel.dataset.idEmpresa, rubro,
                todas_proveedor: todasProveedor ? 1 : 0, anio: ultimoCalculo ? ultimoCalculo.anio : ''
            });
            aviso(j.message, 'success');
            const filtro = $('renta-clasificar-filtro').value;
            if (todasProveedor || (filtro === 'sin_rubro' && rubro)) {
                await RENTA_calcular(); // refresca contador, resumen y la lista
                RENTA_clasificar();
            }
        } catch (e) {
            aviso(e.message, 'danger');
        } finally {
            sel.disabled = false;
        }
    };

    window.RENTA_detalle = async function () {
        if (!ultimoCalculo) return;
        const fuente = $('renta-detalle-fuente').value;
        const tb = $('renta-detalle');
        tb.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">Cargando…</td></tr>';
        try {
            const r = await fetch(`${url('detalleAjax')}?anio=${encodeURIComponent(ultimoCalculo.anio)}&fuente=${encodeURIComponent(fuente)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const j = await r.json();
            if (!j.success) throw new Error(j.message || 'No se pudo cargar el detalle.');
            let base = 0, total = 0;
            const colEst = CFG.consolidado ? (x) => `<td class="small text-muted">${esc(x.establecimiento)}</td>` : () => '';
            tb.innerHTML = j.data.map((x) => {
                base += Number(x.base) || 0; total += Number(x.total) || 0;
                const rubro = x.rubro_nombre ? `<span class="badge ${x.rubro_nombre === 'Sin rubro' ? 'bg-danger bg-opacity-10 text-danger' : 'bg-light text-muted border'}">${esc(x.rubro_nombre)}</span>` : '';
                return `<tr>${colEst(x)}<td>${esc(x.fecha_emision)}</td><td>${esc(x.tipo_nombre)}</td><td>${rubro}</td><td>${esc(x.numero)}</td><td>${esc(x.tercero)}</td><td>${esc(x.identificacion)}</td><td class="val">${money(x.base)}</td><td class="val">${money(x.total)}</td></tr>`;
            }).join('') || '<tr><td colspan="9" class="text-center text-muted py-3">No hay documentos en esta fuente para el ejercicio.</td></tr>';
            $('renta-detalle-resumen').textContent = `${j.data.length} documento(s) · base ${money(base)} · total ${money(total)}`;
        } catch (e) {
            tb.innerHTML = `<tr><td colspan="9" class="text-danger py-3">${esc(e.message)}</td></tr>`;
        }
    };

    window.RENTA_exportar = function (tipo) {
        leerAjustesTabla();
        const u = `${url(tipo)}?${params().toString()}`;
        if (typeof window.CMG_descargar === 'function') {
            window.CMG_descargar(u);
        } else {
            window.location.href = u;
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        const tabDetalle = document.querySelector('[data-bs-target="#tab-detalle"]');
        if (tabDetalle) tabDetalle.addEventListener('shown.bs.tab', () => { if (!$('renta-detalle').innerHTML) RENTA_detalle(); });
        const tabClasificar = document.querySelector('[data-bs-target="#tab-clasificar"]');
        if (tabClasificar) tabClasificar.addEventListener('shown.bs.tab', () => { if (!$('renta-clasificar').querySelector('tr[data-id]')) RENTA_clasificar(); });
        RENTA_calcular();
    });
})();
