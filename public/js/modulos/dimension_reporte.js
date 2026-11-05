/**
 * Pestañas «Documentos» y «Avance / Resumen» del modal de Proyectos y de Centros de costo
 * (HTML: app/views/partials/dimension_reporte_tabs.php).
 *
 *   const dim = CMG_dimReporte({ urlBase, tipo: 'proyecto' | 'centro', modal: elModal });
 *   dim.cargar(id);   // registro guardado: muestra las pestañas (se cargan al abrirlas)
 *   dim.limpiar();    // registro nuevo: las oculta
 *
 * Endpoints (App\Traits\DimensionReporteTrait): documentosAjax, resumenAjax, pdfResumen,
 * excelResumen y documentoOrigenAjax (visor de documento, documento_origen_modal.js).
 */
(function () {
    'use strict';

    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const num = (v) => (parseFloat(v) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = (v) => ((parseFloat(v) || 0) < 0 ? '-$' : '$') + num(Math.abs(parseFloat(v) || 0));

    window.CMG_dimReporte = function (cfg) {
        const modal = cfg.modal;
        const q = (sel) => modal.querySelector(sel);
        const qa = (sel) => modal.querySelectorAll(sel);
        let id = 0;
        let cargado = { documentos: false, resumen: false };

        function periodo() {
            return { desde: q('.dim-desde')?.value || '', hasta: q('.dim-hasta')?.value || '' };
        }
        function qs(extra) {
            const p = periodo();
            return new URLSearchParams(Object.assign({ id }, p.desde ? { desde: p.desde } : {}, p.hasta ? { hasta: p.hasta } : {}, extra || {})).toString();
        }

        // ── Período (Todo / Mes / Trimestre / Semestre / Año / Rango) ─────────
        // Un solo estado para las dos barras (Documentos y Avance/Resumen). Mes, trimestre,
        // semestre y año calculan Desde–Hasta; escribir una fecha a mano pasa a «Rango».
        // Al servidor solo viajan desde/hasta.
        const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const SUBS = {
            mes:       { lbl: 'Mes',       ops: MESES },
            trimestre: { lbl: 'Trimestre', ops: ['1.º (ene–mar)', '2.º (abr–jun)', '3.º (jul–sep)', '4.º (oct–dic)'] },
            semestre:  { lbl: 'Semestre',  ops: ['1.º (ene–jun)', '2.º (jul–dic)'] },
        };
        const MESES_POR = { mes: 1, trimestre: 3, semestre: 6, anio: 12 };
        const hoy = new Date();
        let per = { tipo: 'todo', sub: 1, anio: hoy.getFullYear(), desde: '', hasta: '' };

        const pad = (n) => String(n).padStart(2, '0');
        function subActual(tipo) {
            const m = hoy.getMonth() + 1;
            return tipo === 'mes' ? m : (tipo === 'trimestre' ? Math.ceil(m / 3) : (tipo === 'semestre' ? Math.ceil(m / 6) : 1));
        }
        function calcularFechas() {
            const n = MESES_POR[per.tipo];
            if (!n) { if (per.tipo === 'todo') { per.desde = ''; per.hasta = ''; } return; }
            const mIni = per.tipo === 'anio' ? 1 : (per.sub - 1) * n + 1;
            const mFin = mIni + n - 1;
            per.desde = `${per.anio}-${pad(mIni)}-01`;
            per.hasta = `${per.anio}-${pad(mFin)}-${pad(new Date(per.anio, mFin, 0).getDate())}`;
        }
        function pintarPeriodo() {
            qa('.dim-barra-periodo').forEach(bar => {
                const sub = SUBS[per.tipo];
                bar.querySelector('.dim-per-tipo').value = per.tipo;
                bar.querySelector('.dim-per-sub-wrap').classList.toggle('d-none', !sub);
                bar.querySelector('.dim-per-anio-wrap').classList.toggle('d-none', !MESES_POR[per.tipo]);
                const selSub = bar.querySelector('.dim-per-sub');
                if (sub) {
                    bar.querySelector('.dim-per-sub-lbl').textContent = sub.lbl;
                    if (selSub.dataset.tipo !== per.tipo) {
                        selSub.innerHTML = sub.ops.map((t, i) => `<option value="${i + 1}">${esc(t)}</option>`).join('');
                        selSub.dataset.tipo = per.tipo;
                    }
                    selSub.value = String(per.sub);
                }
                const selAnio = bar.querySelector('.dim-per-anio');
                if (![...selAnio.options].some(o => o.value === String(per.anio))) {
                    selAnio.add(new Option(String(per.anio), String(per.anio)));
                }
                selAnio.value = String(per.anio);
                bar.querySelector('.dim-desde').value = per.desde;
                bar.querySelector('.dim-hasta').value = per.hasta;
            });
        }
        function cambioPeriodo(recargar) {
            cargado = { documentos: false, resumen: false };
            pintarPeriodo();
            if (recargar) recargarVisible();
        }

        qa('.dim-per-tipo').forEach(s => s.addEventListener('change', () => {
            per.tipo = s.value;
            if (SUBS[per.tipo]) per.sub = per.anio === hoy.getFullYear() ? subActual(per.tipo) : 1;
            calcularFechas();
            // Rango: deja las fechas que había para ajustarlas; se consulta con «Mostrar».
            cambioPeriodo(per.tipo !== 'rango');
        }));
        qa('.dim-per-sub').forEach(s => s.addEventListener('change', () => {
            per.sub = parseInt(s.value, 10) || 1;
            calcularFechas();
            cambioPeriodo(true);
        }));
        qa('.dim-per-anio').forEach(s => s.addEventListener('change', () => {
            per.anio = parseInt(s.value, 10) || hoy.getFullYear();
            calcularFechas();
            cambioPeriodo(true);
        }));
        qa('.dim-desde, .dim-hasta').forEach(inp => inp.addEventListener('change', () => {
            per.tipo = 'rango';
            per[inp.classList.contains('dim-desde') ? 'desde' : 'hasta'] = inp.value;
            cambioPeriodo(false);
        }));
        qa('.dim-btn-mostrar').forEach(b => b.addEventListener('click', () => recargarVisible()));
        qa('.dim-btn-todo').forEach(b => b.addEventListener('click', () => {
            per.tipo = 'todo';
            calcularFechas();
            cambioPeriodo(true);
        }));
        q('.dim-btn-pdf')?.addEventListener('click', () => {
            if (!id) return;
            window.CMG_pdfDocumento(`${cfg.urlBase}/pdfResumen?${qs()}`);
        });
        q('.dim-btn-excel')?.addEventListener('click', () => {
            if (!id) return;
            window.CMG_descargar(`${cfg.urlBase}/excelResumen?${qs()}`);
        });

        // Carga perezosa: cada pestaña consulta al abrirse (y otra vez si cambió el período).
        qa('.dim-tab .nav-link').forEach(a => a.addEventListener('shown.bs.tab', () => recargarVisible(true)));

        function recargarVisible(soloSiFalta) {
            if (!id) return;
            if (q('#dim-pane-documentos')?.classList.contains('active') && !(soloSiFalta && cargado.documentos)) cargarDocumentos();
            if (q('#dim-pane-resumen')?.classList.contains('active') && !(soloSiFalta && cargado.resumen)) cargarResumen();
        }

        // ── Documentos ────────────────────────────────────────────────────────
        async function cargarDocumentos() {
            const tbody = q('.dim-docs-tbody');
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-1"></span>Cargando documentos…</td></tr>';
            try {
                const j = await (await fetch(`${cfg.urlBase}/documentosAjax?${qs()}`)).json();
                if (!j.ok) throw new Error(j.error || 'No se pudieron cargar los documentos.');
                cargado.documentos = true;
                let td = 0, th = 0;
                tbody.innerHTML = j.filas.length ? j.filas.map(f => {
                    td += f.debe; th += f.haber;
                    const abr = f.abrible ? ' abrible' : '';
                    return `<tr class="dim-doc-row${abr}" data-modulo="${esc(f.modulo_origen)}" data-ref="${esc(f.id_referencia_origen || '')}" title="${esc(f.cuentas || '')}">
                        <td class="text-nowrap">${esc(f.fecha_txt)}</td>
                        <td class="text-nowrap">${esc(f.numero_comprobante || '#' + f.id_asiento)}</td>
                        <td>${esc(f.origen_nombre)}</td>
                        <td class="text-nowrap">${esc(f.documento || '')}</td>
                        <td>${esc(f.tercero || f.concepto || '')}</td>
                        <td class="text-end">${f.debe ? num(f.debe) : ''}</td>
                        <td class="text-end">${f.haber ? num(f.haber) : ''}</td>
                    </tr>`;
                }).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No hay documentos en el período.</td></tr>';
                const n = j.filas.length;
                q('.dim-docs-count').textContent = `Totales (${n} ${n === 1 ? 'documento' : 'documentos'}${j.truncado ? `; solo los ${j.max} más recientes, acote el período` : ''})`;
                q('.dim-docs-debe').textContent = num(td);
                q('.dim-docs-haber').textContent = num(th);
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${esc(e.message)}</td></tr>`;
            }
        }

        q('.dim-docs-tbody')?.addEventListener('click', (ev) => {
            const tr = ev.target.closest('.dim-doc-row.abrible');
            if (!tr || typeof window.DOCORIGEN_abrirModal !== 'function') return;
            window.DOCORIGEN_abrirModal(tr.dataset.modulo, parseInt(tr.dataset.ref, 10));
        });

        // ── Resumen / avance ──────────────────────────────────────────────────
        function kpi(lbl, val, cls, extra) {
            return `<div class="col-6 col-md-4 col-lg-3"><div class="dim-kpi"><div class="lbl">${esc(lbl)}</div>
                <div class="val ${cls || ''}">${val}</div>${extra || ''}</div></div>`;
        }
        function barra(pct, color) {
            const w = Math.max(0, Math.min(100, pct || 0));
            return `<div class="progress mt-1" style="height:6px;"><div class="progress-bar ${color}" style="width:${w}%"></div></div>`;
        }

        async function cargarResumen() {
            const cuerpo = q('.dim-resumen-cuerpo');
            cuerpo.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-1"></span>Calculando…</div>';
            try {
                const j = await (await fetch(`${cfg.urlBase}/resumenAjax?${qs()}`)).json();
                if (!j.ok) throw new Error(j.error || 'No se pudo calcular el resumen.');
                cargado.resumen = true;
                const r = j.data;
                let html = '<div class="row g-2 mb-3">';
                if (r.tipo === 'proyecto') {
                    const pe = r.ejecucion_pct;
                    const colEj = pe === null ? '' : (pe > 100 ? 'bg-danger' : (pe >= 90 ? 'bg-warning' : 'bg-primary'));
                    html += kpi('Presupuesto', money(r.presupuesto));
                    html += kpi('Ejecutado (costos y gastos)', money(r.costos_gastos));
                    html += kpi('% Ejecución', pe === null ? '<span class="text-muted small">Sin presupuesto</span>' : num(pe) + '%',
                        pe > 100 ? 'text-danger' : '', pe === null ? '' : barra(pe, colEj));
                    html += kpi('Por ejecutar', money(r.por_ejecutar), r.por_ejecutar < 0 ? 'text-danger' : '');
                    html += kpi('Avance en el tiempo', r.tiempo_pct === null ? '<span class="text-muted small">Sin fechas</span>' : Math.round(r.tiempo_pct) + '%',
                        '', r.tiempo_pct === null ? '' : barra(r.tiempo_pct, 'bg-info'));
                    html += kpi('Avance físico', num(r.avance_fisico_pct) + '%', '', barra(r.avance_fisico_pct, 'bg-success'));
                }
                html += kpi('Ingresos', money(r.ingresos), 'text-success');
                html += kpi('Costos y gastos', money(r.costos_gastos), 'text-danger');
                html += kpi('Resultado', money(r.resultado), r.resultado < 0 ? 'text-danger' : 'text-success');
                html += kpi('Documentos', String(r.documentos));
                html += '</div>';

                if (r.tipo === 'proyecto' && r.ejecucion_pct !== null && r.tiempo_pct !== null && r.ejecucion_pct > r.tiempo_pct + 10) {
                    html += `<div class="alert alert-warning py-1 px-2 small mb-2"><i class="bi bi-exclamation-triangle me-1"></i>
                        Se ha ejecutado el ${num(r.ejecucion_pct)}% del presupuesto con el ${Math.round(r.tiempo_pct)}% del plazo transcurrido.</div>`;
                }

                html += '<div class="row g-3"><div class="col-lg-7"><h6 class="small fw-bold text-muted mb-1">Por cuenta contable</h6>'
                    + '<div class="dim-tabla border rounded"><table class="table table-sm mb-0 small"><thead><tr><th>Cuenta</th><th>Grupo</th>'
                    + '<th class="text-end">Debe</th><th class="text-end">Haber</th><th class="text-end">Saldo</th></tr></thead><tbody>'
                    + (r.cuentas.length ? r.cuentas.map(c => `<tr><td>${esc(c.codigo)} - ${esc(c.nombre)}</td><td>${esc(c.grupo)}</td>
                        <td class="text-end">${num(c.debe)}</td><td class="text-end">${num(c.haber)}</td><td class="text-end fw-bold">${num(c.saldo)}</td></tr>`).join('')
                        : '<tr><td colspan="5" class="text-center text-muted py-3">Sin movimientos.</td></tr>')
                    + '</tbody></table></div></div>';
                html += '<div class="col-lg-5"><h6 class="small fw-bold text-muted mb-1">Por mes</h6>'
                    + '<div class="dim-tabla border rounded"><table class="table table-sm mb-0 small"><thead><tr><th>Mes</th>'
                    + '<th class="text-end">Ingresos</th><th class="text-end">Costos y gastos</th><th class="text-end">Resultado</th></tr></thead><tbody>'
                    + (r.meses.length ? r.meses.map(m => `<tr><td>${esc(m.mes_txt)}</td><td class="text-end">${num(m.ingresos)}</td>
                        <td class="text-end">${num(m.costos_gastos)}</td><td class="text-end fw-bold ${m.resultado < 0 ? 'text-danger' : ''}">${num(m.resultado)}</td></tr>`).join('')
                        : '<tr><td colspan="4" class="text-center text-muted py-3">Sin movimientos.</td></tr>')
                    + '</tbody></table></div></div></div>';
                html += '<div class="small text-muted mt-2">Ingresos: cuentas 4 (haber − debe). Costos y gastos: cuentas 5 y 6 (debe − haber). '
                    + 'Solo asientos contabilizados de producción con líneas de este ' + (r.tipo === 'proyecto' ? 'proyecto' : 'centro de costo') + '.</div>';
                cuerpo.innerHTML = html;
            } catch (e) {
                cuerpo.innerHTML = `<div class="text-center text-danger py-4">${esc(e.message)}</div>`;
            }
        }

        function mostrarTabs(si) {
            qa('.dim-tab').forEach(li => li.classList.toggle('d-none', !si));
        }

        return {
            cargar(nuevoId) {
                id = parseInt(nuevoId, 10) || 0;
                cargado = { documentos: false, resumen: false };
                // El visor de documento solo abre documentos de este proyecto / centro.
                window.DOCORIGEN_URL = `${cfg.urlBase}/documentoOrigenAjax?dim=${id}`;
                per = { tipo: 'todo', sub: 1, anio: hoy.getFullYear(), desde: '', hasta: '' };
                pintarPeriodo();
                { const tb = q('.dim-docs-tbody'); if (tb) tb.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Seleccione la pestaña para cargar los documentos.</td></tr>'; }
                { const rc = q('.dim-resumen-cuerpo'); if (rc) rc.innerHTML = '<div class="text-center text-muted py-4">Seleccione la pestaña para calcular el resumen.</div>'; }
                mostrarTabs(id > 0);
            },
            limpiar() { this.cargar(0); },
        };
    };
})();
