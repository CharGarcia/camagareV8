/**
 * Cierre del Ejercicio (modulos/cierre_ejercicio): listado, vista previa, generación y reversión.
 * Un solo modal (#modalCierre) en dos modos: 'nuevo' (vista previa editable) y 'detalle'.
 */
(function () {
    'use strict';

    const cfg = window.CE_CONFIG || {};
    const $ = (id) => document.getElementById(id);

    window.currentSorts = cfg.sorts || [];
    window.currentPage = cfg.page || 1;
    let sorter = null;

    let modo = 'nuevo';
    let tokenGuardado = '';
    let guardando = false;
    let previa = null;        // último cálculo válido (modo nuevo)
    let calcSeq = 0;          // descarta respuestas viejas si el usuario cambia rápido
    let detalleActual = null; // cierre abierto (modo detalle)

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

    const modal = () => bootstrap.Modal.getOrCreateInstance($('modalCierre'));

    function mostrarPestana(id) {
        const btn = $(id);
        if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
    }

    // ── Listado ────────────────────────────────────────────────────────────
    window.cambiarPaginaAjax = (n) => window.fetchSearch(n);

    window.fetchSearch = async (page = 1) => {
        const term = ($('buscarCierre')?.value || '').trim();
        const orden = typeof window.CMG_ordenParam === 'function' ? window.CMG_ordenParam(window.currentSorts || []) : '';
        const tbody = $('tbodyCierres');
        if (tbody) tbody.classList.add('fm-cargando-target');
        try {
            const data = await getJson(`${cfg.urlBase}/searchAjax?b=${encodeURIComponent(term)}&page=${page}&orden=${encodeURIComponent(orden)}`);
            if (!data.ok) { aviso(data.error || 'No se pudo cargar el listado', 'error'); return; }
            window.currentPage = page;
            tbody.innerHTML = data.rows;
            $('paginationContainer').innerHTML = data.pagination;
            $('paginationInfo').textContent = data.info;
            $('btnExportPdf').href = data.pdf_url;
            $('btnExportExcel').href = data.excel_url;
            if (sorter) sorter.refreshIcons();
        } catch (e) {
            console.error('Cierre del ejercicio: listado', e);
        } finally {
            if (tbody) tbody.classList.remove('fm-cargando-target');
        }
    };

    if (typeof window.CMG_initSort === 'function') {
        sorter = window.CMG_initSort(cfg.modulo, (col, dir, sorts) => {
            window.currentSorts = sorts;
            window.fetchSearch(1);
        }, { sorts: window.currentSorts, multi: true, container: '.cierre-ejercicio-scroll', reload: false });
    }

    // ── Piezas del modal ───────────────────────────────────────────────────
    function tablaLineas(lineas, vacio) {
        if (!lineas || !lineas.length) {
            return `<div class="text-muted small p-3 text-center"><i class="bi bi-inbox d-block fs-4 mb-1"></i>${esc(vacio)}</div>`;
        }
        let d = 0, h = 0;
        const filas = lineas.map(l => {
            d += Number(l.debe || 0); h += Number(l.haber || 0);
            return `<tr><td><code class="text-secondary">${esc(l.codigo)}</code></td><td>${esc(l.nombre)}</td>
                    <td class="text-end">${Number(l.debe) ? num(l.debe) : ''}</td>
                    <td class="text-end">${Number(l.haber) ? num(l.haber) : ''}</td></tr>`;
        }).join('');
        return `<table class="table table-sm table-bordered table-hover mb-0 ce-lineas">
                    <thead class="table-light"><tr><th style="width:140px">Código</th><th>Cuenta</th><th class="text-end" style="width:140px">Debe</th><th class="text-end" style="width:140px">Haber</th></tr></thead>
                    <tbody>${filas}</tbody>
                    <tfoot class="table-light"><tr class="fw-semibold"><td colspan="2" class="text-end">Totales (${lineas.length} líneas)</td>
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

    function setAlerta(id, html) {
        const el = $(id);
        if (html) { el.innerHTML = html; el.classList.remove('d-none'); } else { el.classList.add('d-none'); el.innerHTML = ''; }
    }

    function pintarLineas(cierre, apertura, vacioCierre) {
        $('ceCntCierre').textContent = (cierre || []).length || '';
        $('ceCntApertura').textContent = (apertura || []).length || '';
        $('ceTablaCierre').innerHTML = tablaLineas(cierre, vacioCierre);
        $('ceTablaApertura').innerHTML = tablaLineas(apertura, 'No hay saldos de balance que arrastrar.');
    }

    function limpiarModal() {
        ['ceErrores', 'ceAvisos', 'ceRevertido'].forEach(id => setAlerta(id, ''));
        $('ceResumen').classList.add('d-none');
        $('cePie').classList.add('d-none');
        $('ceEstadoBadge').innerHTML = '';
        pintarLineas([], [], '');
        mostrarPestana('ce-tab-general-btn');
    }

    function setEditable(editable) {
        ['ceAnio', 'ceDesde', 'ceDesdeTodo', 'ceObs'].forEach(id => { $(id).disabled = !editable; });
        $('ceBtnGenerar')?.classList.toggle('d-none', !editable);
    }

    // ── Nuevo cierre ───────────────────────────────────────────────────────
    window.CE_nuevo = async function () {
        modo = 'nuevo';
        tokenGuardado = typeof window.CMG_nuevoTokenGuardado === 'function' ? window.CMG_nuevoTokenGuardado() : '';
        previa = null;
        detalleActual = null;
        limpiarModal();
        $('ceTitulo').textContent = 'Nuevo cierre del ejercicio';
        $('ceBtnRevertir')?.classList.add('d-none');
        setEditable(true);
        $('ceBtnGenerar').disabled = true;
        $('ceObs').value = '';
        $('ceDesde').value = '';
        $('ceDesdeTodo').checked = false;
        try {
            const json = await getJson(`${cfg.urlBase}/contextoAjax`);
            if (!json.ok) { aviso(json.error, 'error'); return; }
            const ctx = json.data;
            const anios = ctx.anios.length ? ctx.anios : [ctx.sugerido];
            $('ceAnio').innerHTML = anios.map(a => `<option value="${a}" ${a === ctx.sugerido ? 'selected' : ''}>${a}</option>`).join('');
            modal().show();
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
        if (modo !== 'nuevo') return;
        const seq = ++calcSeq;
        previa = null;
        $('ceBtnGenerar').disabled = true;
        $('ceCargando').classList.remove('d-none');
        ['ceErrores', 'ceAvisos'].forEach(id => setAlerta(id, ''));
        $('ceResumen').classList.add('d-none');

        let qs = `anio=${encodeURIComponent($('ceAnio').value)}`;
        if (!sugerido) {
            qs += '&saldos_desde=' + ($('ceDesdeTodo').checked ? '' : encodeURIComponent($('ceDesde').value));
        }
        try {
            const json = await getJson(`${cfg.urlBase}/calcularAjax?${qs}`);
            if (seq !== calcSeq) return;
            $('ceCargando').classList.add('d-none');
            if (!json.ok) {
                setAlerta('ceErrores', esc(json.error || 'No se pudo calcular el cierre.'));
                pintarLineas([], [], '');
                return;
            }
            pintarPrevia(json.data);
        } catch (e) {
            if (seq !== calcSeq) return;
            $('ceCargando').classList.add('d-none');
            setAlerta('ceErrores', 'Error de conexión al calcular el cierre.');
        }
    }

    function pintarPrevia(d) {
        // "Saldos desde": lo que eligió el servidor (o el usuario), bloqueado si es obligatorio.
        $('ceDesde').value = d.saldos_desde || '';
        $('ceDesdeTodo').checked = !d.saldos_desde;
        $('ceDesde').disabled = !!d.saldos_desde_fijo;
        $('ceDesdeTodo').disabled = !!d.saldos_desde_fijo || !!d.saldos_desde_min;
        if (d.saldos_desde_min) $('ceDesde').min = d.saldos_desde_min; else $('ceDesde').removeAttribute('min');

        setAlerta('ceErrores', (d.errores || []).map(esc).join('<br>'));
        setAlerta('ceAvisos', (d.avisos || []).map(esc).join('<br>'));
        $('ceKpis').innerHTML = kpis(d);
        $('ceExplica').innerHTML = `Se registrará el <b>asiento de cierre al ${fecha(d.fecha_cierre)}</b>, que salda las cuentas de resultados del año, `
            + `y el <b>asiento de apertura al ${fecha(d.fecha_apertura)}</b>, con los saldos de balance `
            + (d.saldos_desde ? `acumulados desde el ${fecha(d.saldos_desde)}` : 'de todo el histórico')
            + `. Luego se bloquea el año ${esc(d.anio)} en Períodos Contables. Los reportes del año ${esc(d.anio)} se seguirán viendo igual.`;
        $('ceResumen').classList.remove('d-none');
        pintarLineas(d.cierre.lineas, d.apertura.lineas, 'Las cuentas de resultados del año ya están en cero: no hace falta asiento de cierre.');

        previa = d;
        $('ceBtnGenerar').disabled = !!(d.errores && d.errores.length);
    }

    window.CE_generar = async function () {
        if (guardando || !previa) return;
        guardando = true;
        const btn = $('ceBtnGenerar');
        btn.disabled = true;
        let creado = false;
        try {
            const ok = await confirmar(`¿Cerrar el ejercicio ${previa.anio}?`,
                `Se registrarán los asientos de cierre y apertura y se <b>bloqueará el año ${esc(previa.anio)}</b>: `
                + 'nadie podrá registrar ni modificar documentos con fecha de ese año hasta que se revierta el cierre.',
                'Sí, cerrar el ejercicio');
            if (!ok) return;

            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generando…';
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
            creado = true;
            aviso(json.msg, 'success');
            window.fetchSearch(1);
            // El modal pasa a mostrar el cierre recién creado: el botón Generar ya no vuelve.
            await CE_detalle(json.id);
        } catch (e) {
            aviso('Error de conexión. Revise el listado antes de reintentar: el cierre pudo haberse registrado.', 'error');
        } finally {
            guardando = false;
            btn.innerHTML = '<i class="bi bi-lock me-1"></i> Generar cierre';
            btn.disabled = creado || !previa || !!(previa.errores && previa.errores.length);
        }
    };

    // ── Detalle y reversión ────────────────────────────────────────────────
    window.CE_detalle = async function (id) {
        try {
            const json = await getJson(`${cfg.urlBase}/detalleAjax?id=${encodeURIComponent(id)}`);
            if (!json.ok) { aviso(json.error, 'error'); return; }
            const d = json.data;
            modo = 'detalle';
            calcSeq++;
            detalleActual = d;
            limpiarModal();
            setEditable(false);

            $('ceTitulo').textContent = `Cierre del ejercicio ${d.anio}`;
            $('ceEstadoBadge').innerHTML = d.estado === 'vigente'
                ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fs-6 fw-normal">Vigente</span>'
                : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 fs-6 fw-normal">Revertido</span>';
            $('ceAnio').innerHTML = `<option>${esc(d.anio)}</option>`;
            $('ceDesde').value = d.saldos_desde ? String(d.saldos_desde).substring(0, 10) : '';
            $('ceDesdeTodo').checked = !d.saldos_desde;
            $('ceObs').value = d.observaciones || '';

            if (d.estado === 'vigente' && Number(d.cambios_posteriores) > 0) {
                setAlerta('ceAvisos', `<i class="bi bi-exclamation-triangle me-1"></i>${d.cambios_posteriores} asiento(s) con fecha hasta el ${esc(d.fmt_fecha_cierre)} `
                    + 'se crearon o modificaron después de este cierre (se reabrió un período). La apertura ya no refleja esos saldos: revierta el cierre y vuelva a generarlo.');
            }
            if (d.estado !== 'vigente') {
                setAlerta('ceRevertido', `<i class="bi bi-arrow-counterclockwise me-1"></i>Revertido el ${esc(d.fmt_revertido_at)} por ${esc(d.revertido_por_nombre || '')}. Motivo: ${esc(d.motivo_reversion || '')}`);
            }
            $('ceKpis').innerHTML = kpis(d);
            $('ceExplica').innerHTML = `Asiento de cierre <b>${esc(d.numero_cierre || '—')}</b> al ${esc(d.fmt_fecha_cierre)} `
                + `y asiento de apertura <b>${esc(d.numero_apertura || '—')}</b> al ${esc(d.fmt_fecha_apertura)}.`;
            $('ceResumen').classList.remove('d-none');
            $('cePie').textContent = `Registrado el ${d.fmt_created_at} por ${d.creado_por_nombre || '—'}.`;
            $('cePie').classList.remove('d-none');
            pintarLineas(d.lineas_cierre, d.lineas_apertura, 'Sin asiento de cierre: las cuentas de resultados ya estaban en cero.');

            $('ceBtnRevertir')?.classList.toggle('d-none', !(d.estado === 'vigente' && d.es_ultimo));
            modal().show();
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
            aviso(json.msg, 'success');
            window.fetchSearch(window.currentPage || 1);
            await CE_detalle(d.id);
        } catch (e) {
            aviso('Error de conexión', 'error');
        }
    };
})();
