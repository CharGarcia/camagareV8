/**
 * Cierre del Ejercicio (modulos/cierre_ejercicio): listado, vista previa, generación y reversión.
 */
(function () {
    'use strict';

    const cfg = window.CE_CONFIG || {};
    const $ = (id) => document.getElementById(id);

    let page = 1;
    let totalPages = 1;
    let ordenCol = cfg.ordenCol || 'anio';
    let ordenDir = (cfg.ordenDir || 'DESC').toUpperCase();
    let buscarTimer = null;

    let tokenGuardado = '';
    let guardando = false;
    let previa = null;        // último cálculo válido
    let calcSeq = 0;          // descarta respuestas viejas si el usuario cambia rápido
    let detalleActual = null;

    // ── Utilidades ─────────────────────────────────────────────────────────
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const num = (v) => Number(v || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fecha = (iso) => iso ? String(iso).substring(0, 10).split('-').reverse().join('-') : '';

    function aviso(msg, tipo) {
        if (typeof window.showToast === 'function') { window.showToast(msg, tipo || 'info'); return; }
        alert(msg);
    }

    async function confirmar(titulo, html, textoBoton) {
        if (window.Swal) {
            const r = await Swal.fire({ title: titulo, html, icon: 'warning', showCancelButton: true,
                confirmButtonText: textoBoton, cancelButtonText: 'Cancelar', focusCancel: true });
            return r.isConfirmed;
        }
        return confirm(titulo);
    }

    async function getJson(url, opts) {
        const resp = await fetch(url, opts);
        return resp.json();
    }

    function modal(id) {
        return bootstrap.Modal.getOrCreateInstance($(id));
    }

    // ── Listado ────────────────────────────────────────────────────────────
    async function cargar() {
        if (!cfg.tabla) {
            $('tbodyCierres').innerHTML = '<tr><td colspan="10" class="text-center py-5 text-muted">Módulo sin tabla en la base de datos.</td></tr>';
            return;
        }
        const b = ($('buscarCierre')?.value || '').trim();
        const qs = `page=${page}&b=${encodeURIComponent(b)}&orden=${encodeURIComponent(ordenCol + ':' + ordenDir)}`;
        try {
            const json = await getJson(`${cfg.urlBase}/searchAjax?${qs}`);
            if (!json.ok) { aviso(json.error || 'No se pudo cargar el listado', 'error'); return; }
            pintarFilas(json.rows || []);
            totalPages = json.total_pages || 1;
            const per = json.per_page || 25;
            const desde = json.total > 0 ? (page - 1) * per + 1 : 0;
            const hasta = json.total > 0 ? Math.min(page * per, json.total) : 0;
            $('paginationInfo').textContent = `${desde}-${hasta}/${json.total}`;
            $('cePrev').disabled = page <= 1;
            $('ceNext').disabled = page >= totalPages;
            if (json.pdf_url) $('btnExportPdf').href = json.pdf_url;
            if (json.excel_url) $('btnExportExcel').href = json.excel_url;
        } catch (e) {
            console.error('Cierre del ejercicio: listado', e);
        }
    }

    function badgeEstado(r) {
        if (r.estado !== 'vigente') {
            return '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Revertido</span>';
        }
        const revisar = Number(r.cambios_posteriores || 0) > 0
            ? ` <i class="bi bi-exclamation-triangle-fill text-warning" title="${r.cambios_posteriores} asiento(s) del año cambiaron después del cierre"></i>` : '';
        return '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Vigente</span>' + revisar;
    }

    function pintarFilas(rows) {
        const tb = $('tbodyCierres');
        if (!rows.length) {
            tb.innerHTML = '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-journal-x fs-3 d-block mb-2"></i>Todavía no se ha cerrado ningún ejercicio.</td></tr>';
            return;
        }
        tb.innerHTML = rows.map(r => `
            <tr class="ce-row" data-id="${r.id}">
                <td class="ps-3 fw-semibold" data-col="anio">${esc(r.anio)}</td>
                <td data-col="fecha_cierre">${esc(r.fmt_fecha_cierre)}</td>
                <td data-col="saldos_desde">${esc(r.fmt_saldos_desde)}</td>
                <td data-col="asiento_cierre">${esc(r.numero_cierre || '—')}</td>
                <td data-col="asiento_apertura">${esc(r.numero_apertura || '—')}</td>
                <td class="text-end ${Number(r.resultado) < 0 ? 'text-danger' : ''}" data-col="resultado">${num(r.resultado)}</td>
                <td class="text-end" data-col="activos">${num(r.total_activos)}</td>
                <td class="text-end" data-col="patrimonio">${num(r.total_patrimonio)}</td>
                <td class="text-center" data-col="estado">${badgeEstado(r)}</td>
                <td class="pe-3 small text-muted" data-col="registrado">${esc(r.fmt_created_at)}<br>${esc(r.creado_por_nombre || '')}</td>
            </tr>`).join('');
    }

    $('tbodyCierres')?.addEventListener('click', (ev) => {
        const tr = ev.target.closest('tr.ce-row');
        if (tr) CE_detalle(Number(tr.dataset.id));
    });
    $('cePrev')?.addEventListener('click', () => { if (page > 1) { page--; cargar(); } });
    $('ceNext')?.addEventListener('click', () => { if (page < totalPages) { page++; cargar(); } });
    $('buscarCierre')?.addEventListener('input', () => {
        clearTimeout(buscarTimer);
        buscarTimer = setTimeout(() => { page = 1; cargar(); }, 400);
    });

    if (typeof window.CMG_initSort === 'function') {
        window.CMG_initSort(cfg.modulo, (col, dir) => {
            ordenCol = col; ordenDir = String(dir || 'ASC').toUpperCase();
            page = 1; cargar();
        }, { col: ordenCol, dir: ordenDir, reload: false });
    }

    // ── Tablas de líneas y KPIs ────────────────────────────────────────────
    function tablaLineas(lineas, vacio) {
        if (!lineas || !lineas.length) {
            return `<div class="text-muted small p-3">${esc(vacio)}</div>`;
        }
        let d = 0, h = 0;
        const filas = lineas.map(l => {
            d += Number(l.debe || 0); h += Number(l.haber || 0);
            return `<tr><td>${esc(l.codigo)}</td><td>${esc(l.nombre)}</td>
                    <td class="text-end">${Number(l.debe) ? num(l.debe) : ''}</td>
                    <td class="text-end">${Number(l.haber) ? num(l.haber) : ''}</td></tr>`;
        }).join('');
        return `<table class="table table-sm table-bordered mb-0 ce-lineas">
                    <thead><tr><th style="width:130px">Código</th><th>Cuenta</th><th class="text-end" style="width:130px">Debe</th><th class="text-end" style="width:130px">Haber</th></tr></thead>
                    <tbody>${filas}</tbody>
                    <tfoot><tr class="fw-semibold"><td colspan="2" class="text-end">Totales (${lineas.length} líneas)</td>
                        <td class="text-end">${num(d)}</td><td class="text-end">${num(h)}</td></tr></tfoot>
                </table>`;
    }

    function kpi(icono, color, valor, etiqueta) {
        return `<div class="ce-kpi d-flex align-items-center gap-2 border rounded-3 px-2 py-1 bg-white">
                    <span class="rounded-2 bg-${color} bg-opacity-10 text-${color} px-2 py-1"><i class="bi ${icono}"></i></span>
                    <div><div class="v">${valor}</div><div class="l">${esc(etiqueta)}</div></div>
                </div>`;
    }

    function kpis(d) {
        const r = Number(d.resultado || 0);
        let html = kpi(r >= 0 ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow', r >= 0 ? 'success' : 'danger', num(r), r >= 0 ? 'Utilidad del año' : 'Pérdida del año');
        if (Math.abs(Number(d.resultado_anterior || 0)) >= 0.01) {
            html += kpi('bi-clock-history', 'warning', num(d.resultado_anterior), 'Resultados anteriores');
        }
        html += kpi('bi-bank', 'primary', num(d.total_activos), 'Activos al 31-12');
        html += kpi('bi-credit-card', 'secondary', num(d.total_pasivos), 'Pasivos al 31-12');
        html += kpi('bi-building', 'info', num(d.total_patrimonio), 'Patrimonio al 31-12');
        return html;
    }

    // ── Nuevo cierre ───────────────────────────────────────────────────────
    window.CE_nuevo = async function () {
        tokenGuardado = typeof window.CMG_nuevoTokenGuardado === 'function' ? window.CMG_nuevoTokenGuardado() : '';
        previa = null;
        $('ceBtnGenerar').disabled = true;
        $('ceObs').value = '';
        ['ceErrores', 'ceAvisos', 'cePrevia'].forEach(id => $(id).classList.add('d-none'));
        try {
            const json = await getJson(`${cfg.urlBase}/contextoAjax`);
            if (!json.ok) { aviso(json.error, 'error'); return; }
            const ctx = json.data;
            if (ctx.ambiente !== '2') {
                aviso('La empresa está en ambiente de pruebas: el cierre se hace sobre la contabilidad de producción.', 'warning');
            }
            const sel = $('ceAnio');
            const anios = ctx.anios.length ? ctx.anios : [ctx.sugerido];
            sel.innerHTML = anios.map(a => `<option value="${a}" ${a === ctx.sugerido ? 'selected' : ''}>${a}</option>`).join('');
            $('ceDesde').value = '';
            $('ceDesdeTodo').checked = false;
            modal('modalCierreNuevo').show();
            calcular(true);
        } catch (e) {
            aviso('Error de conexión', 'error');
        }
    };

    $('ceAnio')?.addEventListener('change', () => calcular(true));
    $('ceDesde')?.addEventListener('change', () => { $('ceDesdeTodo').checked = false; calcular(false); });
    $('ceDesdeTodo')?.addEventListener('change', () => calcular(false));

    /** @param {boolean} sugerido true = que el servidor elija "saldos desde". */
    async function calcular(sugerido) {
        const seq = ++calcSeq;
        previa = null;
        $('ceBtnGenerar').disabled = true;
        $('ceCargando').classList.remove('d-none');
        ['ceErrores', 'ceAvisos', 'cePrevia'].forEach(id => $(id).classList.add('d-none'));

        const anio = $('ceAnio').value;
        let qs = `anio=${encodeURIComponent(anio)}`;
        if (!sugerido) {
            qs += '&saldos_desde=' + ($('ceDesdeTodo').checked ? '' : encodeURIComponent($('ceDesde').value));
        }
        try {
            const json = await getJson(`${cfg.urlBase}/calcularAjax?${qs}`);
            if (seq !== calcSeq) return;
            $('ceCargando').classList.add('d-none');
            if (!json.ok) {
                $('ceErrores').textContent = json.error || 'No se pudo calcular el cierre.';
                $('ceErrores').classList.remove('d-none');
                return;
            }
            pintarPrevia(json.data);
        } catch (e) {
            if (seq !== calcSeq) return;
            $('ceCargando').classList.add('d-none');
            $('ceErrores').textContent = 'Error de conexión al calcular el cierre.';
            $('ceErrores').classList.remove('d-none');
        }
    }

    function pintarPrevia(d) {
        // "Saldos desde": lo que eligió el servidor (o el usuario), bloqueado si es obligatorio.
        $('ceDesde').value = d.saldos_desde || '';
        $('ceDesdeTodo').checked = !d.saldos_desde;
        $('ceDesde').disabled = !!d.saldos_desde_fijo;
        $('ceDesdeTodo').disabled = !!d.saldos_desde_fijo || !!d.saldos_desde_min;
        if (d.saldos_desde_min) $('ceDesde').min = d.saldos_desde_min; else $('ceDesde').removeAttribute('min');

        if (d.errores && d.errores.length) {
            $('ceErrores').innerHTML = d.errores.map(esc).join('<br>');
            $('ceErrores').classList.remove('d-none');
        }
        if (d.avisos && d.avisos.length) {
            $('ceAvisos').innerHTML = d.avisos.map(esc).join('<br>');
            $('ceAvisos').classList.remove('d-none');
        }
        $('ceKpis').innerHTML = kpis(d);
        $('ceExplica').innerHTML = `Se registrará el <b>asiento de cierre al ${fecha(d.fecha_cierre)}</b>, que salda las cuentas de resultados del año, `
            + `y el <b>asiento de apertura al ${fecha(d.fecha_apertura)}</b>, con los saldos de balance `
            + (d.saldos_desde ? `acumulados desde el ${fecha(d.saldos_desde)}` : 'de todo el histórico')
            + `. Luego se bloquea el año ${esc(d.anio)} en Períodos Contables. Los reportes del año ${esc(d.anio)} se seguirán viendo igual.`;
        $('ceCntCierre').textContent = (d.cierre.lineas || []).length;
        $('ceCntApertura').textContent = (d.apertura.lineas || []).length;
        $('ceTablaCierre').innerHTML = tablaLineas(d.cierre.lineas, 'Las cuentas de resultados del año ya están en cero: no hace falta asiento de cierre.');
        $('ceTablaApertura').innerHTML = tablaLineas(d.apertura.lineas, 'No hay saldos de balance que arrastrar.');
        $('cePrevia').classList.remove('d-none');

        previa = d;
        $('ceBtnGenerar').disabled = !!(d.errores && d.errores.length);
    }

    window.CE_generar = async function () {
        if (guardando || !previa) return;
        guardando = true;
        const btn = $('ceBtnGenerar');
        btn.disabled = true;
        let cerrado = false;
        try {
            const ok = await confirmar(`¿Cerrar el ejercicio ${previa.anio}?`,
                `Se registrarán los asientos de cierre y apertura y se <b>bloqueará el año ${esc(previa.anio)}</b>: `
                + 'nadie podrá registrar ni modificar documentos con fecha de ese año hasta que se revierta el cierre.',
                'Sí, cerrar el ejercicio');
            if (!ok) return;

            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generando…';
            const fd = new FormData();
            fd.append('anio', previa.anio);
            fd.append('saldos_desde', previa.saldos_desde || '');
            fd.append('observaciones', $('ceObs').value);
            fd.append('token_guardado', tokenGuardado);
            const json = await getJson(`${cfg.urlBase}/generar`, { method: 'POST', body: fd });
            if (!json.ok) {
                aviso(json.error || 'No se pudo generar el cierre.', 'error');
                return;
            }
            cerrado = true;
            modal('modalCierreNuevo').hide();
            aviso(json.msg, 'success');
            page = 1;
            await cargar();
            CE_detalle(json.id);
        } catch (e) {
            aviso('Error de conexión. Revise el listado antes de reintentar: el cierre pudo haberse registrado.', 'error');
        } finally {
            guardando = false;
            btn.innerHTML = '<i class="bi bi-lock"></i> Generar cierre';
            // Tras crear el cierre el botón no se reactiva: el modal ya se cerró.
            btn.disabled = cerrado || !previa || !!(previa.errores && previa.errores.length);
        }
    };

    // ── Detalle y reversión ────────────────────────────────────────────────
    window.CE_detalle = async function (id) {
        try {
            const json = await getJson(`${cfg.urlBase}/detalleAjax?id=${encodeURIComponent(id)}`);
            if (!json.ok) { aviso(json.error, 'error'); return; }
            const d = json.data;
            detalleActual = d;
            $('ceDetTitulo').textContent = `Cierre del ejercicio ${d.anio}`;
            $('ceDetKpis').innerHTML = kpis(d);
            $('ceDetNumCierre').textContent = d.numero_cierre ? `(${d.numero_cierre})` : '';
            $('ceDetNumApertura').textContent = d.numero_apertura ? `(${d.numero_apertura})` : '';
            $('ceDetTablaCierre').innerHTML = tablaLineas(d.lineas_cierre, 'Sin asiento de cierre: las cuentas de resultados ya estaban en cero.');
            $('ceDetTablaApertura').innerHTML = tablaLineas(d.lineas_apertura, 'Sin asiento de apertura.');

            const avisoEl = $('ceDetAviso');
            if (d.estado === 'vigente' && Number(d.cambios_posteriores) > 0) {
                avisoEl.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>${d.cambios_posteriores} asiento(s) con fecha hasta el ${esc(d.fmt_fecha_cierre)} `
                    + 'se crearon o modificaron después de este cierre (se reabrió un período). La apertura ya no refleja esos saldos: revierta el cierre y vuelva a generarlo.';
                avisoEl.classList.remove('d-none');
            } else {
                avisoEl.classList.add('d-none');
            }
            const rev = $('ceDetRevertido');
            if (d.estado !== 'vigente') {
                rev.innerHTML = `<i class="bi bi-arrow-counterclockwise me-1"></i>Revertido el ${esc(d.fmt_revertido_at)} por ${esc(d.revertido_por_nombre || '')}. Motivo: ${esc(d.motivo_reversion || '')}`;
                rev.classList.remove('d-none');
            } else {
                rev.classList.add('d-none');
            }
            $('ceDetPie').textContent = `Saldos desde: ${d.fmt_saldos_desde}. Registrado el ${d.fmt_created_at} por ${d.creado_por_nombre || '—'}.`
                + (d.observaciones ? ` Observaciones: ${d.observaciones}` : '');

            const btnRev = $('ceBtnRevertir');
            if (btnRev) btnRev.classList.toggle('d-none', !(d.estado === 'vigente' && d.es_ultimo));
            modal('modalCierreDetalle').show();
        } catch (e) {
            aviso('Error de conexión', 'error');
        }
    };

    window.CE_revertir = async function () {
        const d = detalleActual;
        if (!d) return;
        let motivo = '';
        if (window.Swal) {
            const r = await Swal.fire({
                title: `¿Revertir el cierre de ${d.anio}?`,
                html: 'Se <b>anularán</b> los asientos de cierre y apertura y se reabrirá el año en Períodos Contables. El registro queda en el historial.',
                icon: 'warning', input: 'text', inputPlaceholder: 'Motivo de la reversión',
                showCancelButton: true, confirmButtonText: 'Revertir', cancelButtonText: 'Cancelar', focusCancel: true,
                inputValidator: (v) => (!v || v.trim().length < 5) ? 'Indique el motivo (al menos 5 caracteres)' : undefined,
            });
            if (!r.isConfirmed) return;
            motivo = r.value;
        } else {
            motivo = prompt('Motivo de la reversión') || '';
            if (!motivo) return;
        }
        const fd = new FormData();
        fd.append('id', d.id);
        fd.append('motivo', motivo);
        try {
            const json = await getJson(`${cfg.urlBase}/revertir`, { method: 'POST', body: fd });
            if (!json.ok) { aviso(json.error, 'error'); return; }
            modal('modalCierreDetalle').hide();
            aviso(json.msg, 'success');
            cargar();
        } catch (e) {
            aviso('Error de conexión', 'error');
        }
    };

    cargar();
})();
