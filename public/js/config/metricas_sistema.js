/**
 * Métricas del sistema (/config/metricas-sistema, solo nivel 3).
 * Cada pestaña se pide al abrirla (seccion=usuarios|empresas|…) y se repinta al pulsar Mostrar.
 * El pie de la tarjeta de control usa seccion=resumen.
 */
(function () {
    'use strict';

    const URL_DATOS = (window.BASE_URL || '') + '/config/metricas-sistema?action=datos';
    const cargadas = {};          // seccion -> clave de rango ya pintada
    const charts = {};            // id canvas -> instancia Chart
    let enCurso = {};

    const COLORES = {
        primario: '#0d6efd', exito: '#198754', info: '#0dcaf0', aviso: '#ffc107',
        peligro: '#dc3545', gris: '#6c757d', violeta: '#6f42c1', naranja: '#fd7e14'
    };
    const NIVELES = { 1: 'Usuario', 2: 'Administrador', 3: 'Superadmin' };
    const TIPOS_SRI = {
        factura_venta: 'Factura', nota_credito: 'Nota de crédito', nota_debito: 'Nota de débito',
        retencion_compra: 'Retención', guia_remision: 'Guía de remisión',
        liquidacion_compra: 'Liquidación de compra', factura_reembolso: 'Factura de reembolso'
    };

    // ─── Formato ────────────────────────────────────────────────────────────
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const num = (v, dec = 0) => Number(v || 0).toLocaleString('es-EC', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    const dinero = (v) => num(v, 2);
    function fecha(v, conHora = true) {
        if (!v) return '<span class="text-muted">—</span>';
        const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}):(\d{2}))?/);
        if (!m) return esc(v);
        const f = `${m[3]}-${m[2]}-${m[1]}`;
        return conHora ? `${f} ${m[4] || '00'}:${m[5] || '00'}:${m[6] || '00'}` : f;
    }
    function etiquetaPeriodo(p, porMes) {
        const m = String(p).match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!m) return p;
        return porMes ? `${m[2]}-${m[1]}` : `${m[3]}-${m[2]}`;
    }
    const isoLocal = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

    // ─── Bloques de UI ──────────────────────────────────────────────────────
    function kpi(valor, etiqueta, color = '', sub = '') {
        return `<div class="col-6 col-md-4 col-xl-2"><div class="met-kpi">
            <div class="met-kpi__valor ${color ? 'text-' + color : ''}">${valor}</div>
            <div class="met-kpi__etq">${esc(etiqueta)}</div>
            ${sub ? `<div class="met-kpi__sub">${sub}</div>` : ''}
        </div></div>`;
    }
    function tarjeta(titulo, cuerpo, col = 'col-12', icono = '') {
        return `<div class="${col}"><div class="border rounded-3 h-100">
            <div class="px-3 py-2 border-bottom met-card-titulo">${icono ? `<i class="bi ${icono} me-1"></i>` : ''}${esc(titulo)}</div>
            <div class="p-2">${cuerpo}</div></div></div>`;
    }
    const lienzo = (id) => `<div class="met-chart"><canvas id="${id}"></canvas></div>`;
    function tabla(cols, filas, vacio = 'Sin datos en el rango seleccionado.') {
        const th = cols.map((c) => `<th class="${c.cls || ''}">${esc(c.t)}</th>`).join('');
        const tb = filas.length
            ? filas.map((f) => '<tr>' + cols.map((c) => `<td class="${c.cls || ''}">${c.f(f)}</td>`).join('') + '</tr>').join('')
            : `<tr><td colspan="${cols.length}" class="text-center text-muted py-4">${esc(vacio)}</td></tr>`;
        return `<div class="metricas-scroll"><table class="table table-sm table-hover mb-0 align-middle"><thead><tr>${th}</tr></thead><tbody>${tb}</tbody></table></div>`;
    }
    /** Aviso del registro de actividad (Fase B): desde cuándo hay datos, o que falta el SQL. */
    function avisoActividad(act) {
        if (!act) {
            return `<div class="alert alert-light border small py-2 mb-3"><i class="bi bi-info-circle me-1"></i>
                El registro de actividad por empresa todavía no está activo (falta aplicar <code>2026-11-05_metricas_actividad_usuario.sql</code>).</div>`;
        }
        const desde = act.desde ? fecha(act.desde, false) : 'hoy';
        return `<div class="alert alert-light border small py-2 mb-3"><i class="bi bi-info-circle me-1"></i>
            La actividad por empresa se registra desde el <strong>${desde}</strong>; antes de esa fecha no hay datos.
            No cuenta a los superadministradores. Las horas de uso son estimadas (ventanas de 5 minutos con actividad).</div>`;
    }
    const badge = (txt, color) => `<span class="badge bg-${color} bg-opacity-10 text-${color} border border-${color} border-opacity-25">${esc(txt)}</span>`;
    function navegador(ua) {
        ua = String(ua || '');
        let nav = /Edg\//.test(ua) ? 'Edge' : /OPR\//.test(ua) ? 'Opera' : /Chrome\//.test(ua) ? 'Chrome' : /Firefox\//.test(ua) ? 'Firefox' : /Safari\//.test(ua) ? 'Safari' : (ua ? 'Otro' : '—');
        if (/okhttp|Expo|CFNetwork|Dart/i.test(ua)) nav = 'App';
        const so = /Windows/.test(ua) ? 'Windows' : /Android/.test(ua) ? 'Android' : /iPhone|iPad|iOS/.test(ua) ? 'iOS' : /Mac OS/.test(ua) ? 'macOS' : /Linux/.test(ua) ? 'Linux' : '';
        return esc(so ? `${nav} · ${so}` : nav);
    }

    function grafico(id, config) {
        if (typeof Chart === 'undefined') return;
        const el = document.getElementById(id);
        if (!el) return;
        if (charts[id]) charts[id].destroy();
        config.options = Object.assign({ responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } } }, config.options || {});
        charts[id] = new Chart(el, config);
    }

    // ─── Carga ──────────────────────────────────────────────────────────────
    function rango() {
        return { desde: document.getElementById('met-desde').value, hasta: document.getElementById('met-hasta').value };
    }

    async function pedir(seccion, refrescar) {
        const r = rango();
        const qs = new URLSearchParams({ seccion, desde: r.desde, hasta: r.hasta });
        if (refrescar) qs.set('refrescar', '1');
        const resp = await fetch(URL_DATOS + '&' + qs.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await resp.json().catch(() => ({ ok: false, error: 'Respuesta no válida del servidor.' }));
        if (!json.ok) throw new Error(json.error || 'No se pudieron cargar las métricas.');
        return json;
    }

    async function cargarSeccion(seccion, refrescar = false) {
        const pane = document.getElementById('met-pane-' + seccion);
        if (!pane) return;
        const r = rango();
        const clave = r.desde + '|' + r.hasta;
        if (!refrescar && cargadas[seccion] === clave) return;
        if (enCurso[seccion]) return;
        enCurso[seccion] = true;
        pane.innerHTML = '<div class="text-center text-muted py-5 small"><span class="spinner-border spinner-border-sm me-2"></span>Calculando…</div>';
        try {
            const json = await pedir(seccion, refrescar);
            RENDER[seccion](pane, json.datos);
            cargadas[seccion] = clave;
            document.getElementById('met-generado').textContent = 'Calculado ' + json.generado + (json.cache ? ' (caché)' : '');
        } catch (e) {
            pane.innerHTML = `<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>${esc(e.message)}</div>`;
        } finally {
            enCurso[seccion] = false;
        }
    }

    /** Días del rango elegido (inclusive). */
    function diasRango() {
        const r = rango();
        return Math.round((new Date(r.hasta) - new Date(r.desde)) / 86400000);
    }

    async function cargarResumen(refrescar = false) {
        // Más de un año solo lo admite Tendencias (resumen precalculado): el pie no se calcula.
        if (diasRango() > 366) {
            ['met-st-docs', 'met-st-facturas', 'met-st-ventas', 'met-st-empresas-mov'].forEach((id) => {
                const el = document.getElementById(id); if (el) el.textContent = '—';
            });
            return;
        }
        try {
            const d = (await pedir('resumen', refrescar)).datos;
            const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
            set('met-st-conectados', num(d.conectados));
            set('met-st-usuarios-hoy', num(d.usuarios_hoy));
            set('met-st-usuarios', num(d.usuarios_activos));
            set('met-st-empresas', num(d.empresas_activas));
            set('met-st-empresas-mov', num(d.empresas_con_mov));
            set('met-st-conectadas', d.actividad ? num(d.empresas_ahora) : '—');
            set('met-st-conectadas-hoy', d.actividad ? num(d.empresas_hoy) : '—');
            const w = document.getElementById('met-st-conectadas-wrap');
            if (w) w.title = d.actividad ? 'Empresas con usuarios trabajando en los últimos 15 minutos (sin contar superadmins)' : 'Falta aplicar el SQL de actividad (Fase B)';
            set('met-st-docs', num(d.documentos_sri));
            set('met-st-facturas', num(d.facturas));
            set('met-st-ventas', dinero(d.ventas));
        } catch (e) {
            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', text: e.message });
        }
    }

    function seccionActiva() {
        const b = document.querySelector('#met-tabs .nav-link.active');
        return b ? b.dataset.seccion : 'usuarios';
    }

    // ─── Render por pestaña ─────────────────────────────────────────────────
    const RENDER = {
        usuarios(pane, d) {
            const r = d.resumen || {};
            pane.innerHTML = `
                <div class="row g-2 mb-3">
                    ${kpi(num(r.conectados), 'Conectados ahora', 'success', `${num(r.web)} web · ${num(r.movil)} móvil`)}
                    ${kpi(num(r.activos_hoy), 'Activos hoy', 'primary')}
                    ${kpi(num(r.activos_7d), 'Activos 7 días', '', `${num(r.activos_30d)} en 30 días`)}
                    ${kpi(num(r.activos), 'Usuarios habilitados', '', `${num(r.inactivos)} deshabilitados · ${num(r.total)} total`)}
                    ${kpi(num(r.sin_ingreso_30d), 'Sin ingresar +30 días', 'warning', `${num(r.sin_ingreso_90d)} más de 90 días`)}
                    ${kpi(num(r.nunca), 'Nunca ingresaron', 'danger')}
                </div>
                ${avisoActividad(d.actividad)}
                ${d.actividad ? `<div class="row g-2 mb-3">
                    ${kpi(num(d.actividad.resumen.usuarios_hoy), 'Trabajaron hoy', 'primary', 'en alguna empresa')}
                    ${kpi(num(d.actividad.resumen.usuarios_7d), 'Últimos 7 días')}
                    ${kpi(num(d.actividad.resumen.usuarios_30d), 'Últimos 30 días')}
                    ${kpi(num((d.actividad.serie || []).reduce((a, x) => a + (+x.horas || 0), 0), 1), 'Horas de uso', 'success', 'estimadas en el rango')}
                </div>
                <div class="row g-3 mb-3">${tarjeta('Usuarios y empresas activas', lienzo('met-ch-actividad'), 'col-12', 'bi-activity')}</div>` : ''}
                <div class="row g-3 mb-3">
                    ${tarjeta('Inicios de sesión', lienzo('met-ch-logins'), 'col-lg-8', 'bi-box-arrow-in-right')}
                    ${tarjeta('Por hora del día', lienzo('met-ch-horas'), 'col-lg-4', 'bi-clock')}
                </div>
                <div class="row g-3">
                    ${tarjeta(`Conectados ahora (actividad en los últimos ${d.minutos_conectado} min)`, tabla([
                        { t: 'Usuario', f: (x) => esc(x.nombre) },
                        { t: 'Empresa', cls: 'met-wrap', f: (x) => x.empresa ? esc(x.empresa) : '<span class="text-muted">—</span>' },
                        { t: 'Nivel', f: (x) => esc(NIVELES[x.nivel] || x.nivel) },
                        { t: 'Canal', f: (x) => x.canal === 'movil' ? badge('Móvil', 'info') : badge('Web', 'primary') },
                        { t: 'Navegador', f: (x) => navegador(x.user_agent) },
                        { t: 'IP', f: (x) => esc(x.ip) },
                        { t: 'Inicio de sesión', f: (x) => fecha(x.inicio) },
                        { t: 'Última actividad', f: (x) => fecha(x.ultima_actividad) },
                        { t: 'Empresas', cls: 'text-end', f: (x) => num(x.empresas) }
                    ], d.conectados || [], 'No hay usuarios conectados en este momento.'), 'col-12', 'bi-broadcast')}
                    ${tarjeta('Usuarios habilitados sin ingresar hace más de 30 días', tabla([
                        { t: 'Usuario', f: (x) => esc(x.nombre) },
                        { t: 'Correo', f: (x) => esc(x.mail) },
                        { t: 'Nivel', f: (x) => esc(NIVELES[x.nivel] || x.nivel) },
                        { t: 'Último ingreso', f: (x) => x.ultima ? fecha(x.ultima) : badge('Nunca', 'danger') },
                        { t: 'Empresas', cls: 'text-end', f: (x) => num(x.empresas) }
                    ], d.inactivos || [], 'Todos los usuarios habilitados ingresaron en los últimos 30 días.'), 'col-12', 'bi-person-dash')}
                </div>`;

            const L = d.logins || [];
            grafico('met-ch-logins', {
                type: 'bar',
                data: {
                    labels: L.map((x) => etiquetaPeriodo(x.periodo, d.por_mes)),
                    datasets: [
                        { label: 'Inicios de sesión', data: L.map((x) => +x.logins), backgroundColor: COLORES.primario + 'aa' },
                        { label: 'Usuarios distintos', data: L.map((x) => +x.usuarios), type: 'line', borderColor: COLORES.exito, backgroundColor: COLORES.exito, tension: .3 }
                    ]
                },
                options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
            if (d.actividad) {
                const A = d.actividad.serie || [];
                grafico('met-ch-actividad', {
                    type: 'bar',
                    data: {
                        labels: A.map((x) => etiquetaPeriodo(x.periodo, d.por_mes)),
                        datasets: [
                            { label: 'Usuarios activos', data: A.map((x) => +x.usuarios), backgroundColor: COLORES.primario + 'aa', yAxisID: 'y' },
                            { label: 'Empresas activas', data: A.map((x) => +x.empresas), backgroundColor: COLORES.info + 'aa', yAxisID: 'y' },
                            { label: 'Horas de uso', data: A.map((x) => +x.horas), type: 'line', borderColor: COLORES.exito, backgroundColor: COLORES.exito, tension: .3, yAxisID: 'y2' }
                        ]
                    },
                    options: { scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Cantidad' } },
                        y2: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Horas' } }
                    } }
                });
            }
            const H = Array.from({ length: 24 }, (_, h) => +((d.por_hora || []).find((x) => +x.hora === h)?.logins || 0));
            grafico('met-ch-horas', {
                type: 'bar',
                data: { labels: H.map((_, h) => String(h).padStart(2, '0')), datasets: [{ label: 'Inicios de sesión', data: H, backgroundColor: COLORES.violeta + 'aa' }] },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
        },

        empresas(pane, d) {
            const r = d.resumen || {};
            const nombre = (x) => esc(x.nombre_comercial || x.nombre) + (x.es_demo ? ' ' + badge('Demo', 'secondary') : '');
            const act = d.actividad;
            const ar = act ? act.resumen : {};
            const horas = (v) => v === null || v === undefined ? '<span class="text-muted">—</span>' : num(v, 1);
            pane.innerHTML = `
                <div class="row g-2 mb-3">
                    ${kpi(num(r.activas), 'Empresas activas', 'primary', `${num(r.rucs_activos)} RUC distintos`)}
                    ${act ? kpi(num(ar.empresas_ahora), 'Conectadas ahora', 'success', `${num(ar.empresas_hoy)} hoy · ${num(ar.empresas_7d)} en 7 días`) : ''}
                    ${kpi(num(d.con_movimiento), 'Con documentos', 'success', 'emitidos en el rango')}
                    ${act ? kpi(num(act.con_uso), 'Con usuarios activos', 'info', 'en el rango') : ''}
                    ${kpi(num((d.sin_movimiento || []).length), 'Sin movimiento', 'warning', act ? 'sin documentos ni uso' : 'activas, sin demo')}
                    ${kpi(num(r.en_produccion), 'En producción SRI', 'info')}
                    ${kpi(num(r.vencidas), 'Vigencia vencida', 'danger', `${num(r.por_vencer)} vencen en 30 días`)}
                    ${kpi(num(r.inactivas), 'Inactivas', '', `${num(r.demo)} demo · ${num(r.total)} total`)}
                </div>
                ${avisoActividad(act)}
                ${act ? `<div class="row g-3 mb-3">
                    ${tarjeta(`Conectadas ahora (actividad en los últimos 15 min)`, tabla([
                        { t: 'Empresa', cls: 'met-wrap', f: nombre },
                        { t: 'RUC', f: (x) => esc(x.ruc) },
                        { t: 'Est.', f: (x) => esc(x.establecimiento) },
                        { t: 'Usuarios', cls: 'text-end', f: (x) => num(x.usuarios) + (+x.movil ? ` <span class="text-muted small">(${num(x.movil)} móvil)</span>` : '') },
                        { t: 'Quiénes', cls: 'met-wrap', f: (x) => esc(x.nombres) },
                        { t: 'Última actividad', f: (x) => fecha(x.ultima_actividad) }
                    ], act.conectadas || [], 'No hay empresas con usuarios conectados en este momento.'), 'col-12', 'bi-plug')}
                    ${tarjeta('Empresas con mayor uso (horas estimadas)', tabla([
                        { t: '#', f: (x) => x._i },
                        { t: 'Empresa', cls: 'met-wrap', f: nombre },
                        { t: 'RUC', f: (x) => esc(x.ruc) },
                        { t: 'Usuarios activos', cls: 'text-end', f: (x) => num(x.usuarios_activos) },
                        { t: 'Días con uso', cls: 'text-end', f: (x) => num(x.dias_activos) },
                        { t: 'Horas', cls: 'text-end fw-bold', f: (x) => horas(x.horas) },
                        { t: 'Última actividad', f: (x) => fecha(x.ultima_actividad) }
                    ], (act.uso || []).map((x, i) => Object.assign({}, x, { _i: i + 1 })), 'Sin actividad registrada en el rango.'), 'col-12', 'bi-hourglass-split')}
                </div>` : ''}
                <div class="row g-3 mb-3">
                    ${tarjeta('Empresas con mayor movimiento', lienzo('met-ch-ranking'), 'col-lg-8', 'bi-trophy')}
                    ${tarjeta('Altas por mes (últimos 12 meses)', lienzo('met-ch-altas'), 'col-lg-4', 'bi-plus-circle')}
                </div>
                <div class="row g-3">
                    ${tarjeta('Ranking por documentos emitidos', tabla([
                        { t: '#', f: (x) => x._i },
                        { t: 'Empresa', cls: 'met-wrap', f: nombre },
                        { t: 'RUC', f: (x) => esc(x.ruc) },
                        { t: 'Est.', f: (x) => esc(x.establecimiento) },
                        { t: 'Documentos', cls: 'text-end', f: (x) => num(x.documentos) },
                        { t: 'Facturas', cls: 'text-end', f: (x) => num(x.facturas) },
                        { t: 'Ventas', cls: 'text-end', f: (x) => '$' + dinero(x.ventas) }
                    ].concat(act ? [
                        { t: 'Usuarios activos', cls: 'text-end', f: (x) => x.usuarios_activos === null ? '<span class="text-muted">—</span>' : num(x.usuarios_activos) },
                        { t: 'Horas', cls: 'text-end', f: (x) => horas(x.horas) }
                    ] : []), (d.ranking || []).map((x, i) => Object.assign(x, { _i: i + 1 }))), 'col-12', 'bi-list-ol')}
                    ${tarjeta('Activas sin movimiento en el rango', tabla([
                        { t: 'Empresa', cls: 'met-wrap', f: (x) => esc(x.nombre) },
                        { t: 'RUC', f: (x) => esc(x.ruc) },
                        { t: 'Est.', f: (x) => esc(x.establecimiento) },
                        { t: 'Usuarios', cls: 'text-end', f: (x) => num(x.usuarios) },
                    ].concat(act ? [{ t: 'Último uso', f: (x) => fecha(x.ultimo_uso) }] : []).concat([
                        { t: 'Creada', f: (x) => fecha(x.created_at) },
                        { t: 'Vigente hasta', f: (x) => fecha(x.periodo_vigencia_hasta, false) }
                    ]), d.sin_movimiento || [],'Todas las empresas activas tuvieron movimiento.'), 'col-lg-7', 'bi-moon')}
                    ${tarjeta('Vigencia vencida o por vencer (30 días)', tabla([
                        { t: 'Empresa', cls: 'met-wrap', f: (x) => esc(x.nombre) },
                        { t: 'Vence', f: (x) => fecha(x.periodo_vigencia_hasta, false) },
                        { t: 'Días', cls: 'text-end', f: (x) => +x.dias < 0 ? badge(x.dias, 'danger') : badge(x.dias, 'warning') }
                    ], d.vigencia || [], 'Ninguna empresa vence en los próximos 30 días.'), 'col-lg-5', 'bi-calendar-x')}
                    ${tarjeta('Cupo de usuarios al 80 % o más', tabla([
                        { t: 'Empresa', cls: 'met-wrap', f: (x) => esc(x.nombre) },
                        { t: 'RUC', f: (x) => esc(x.ruc) },
                        { t: 'Usuarios', cls: 'text-end', f: (x) => num(x.usuarios) },
                        { t: 'Máximo', cls: 'text-end', f: (x) => num(x.max_usuarios) },
                        { t: 'Uso', cls: 'text-end', f: (x) => { const p = Math.round(x.usuarios * 100 / x.max_usuarios); return badge(p + ' %', p >= 100 ? 'danger' : 'warning'); } }
                    ], d.cupo || [], 'Ninguna empresa está cerca de su cupo de usuarios.'), 'col-12', 'bi-people')}
                </div>`;

            const top = (d.ranking || []).slice(0, 10);
            grafico('met-ch-ranking', {
                type: 'bar',
                data: {
                    labels: top.map((x) => String(x.nombre_comercial || x.nombre).slice(0, 28)),
                    datasets: [{ label: 'Documentos', data: top.map((x) => +x.documentos), backgroundColor: COLORES.primario + 'aa' }]
                },
                options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
            const A = d.altas || [];
            grafico('met-ch-altas', {
                type: 'bar',
                data: { labels: A.map((x) => etiquetaPeriodo(x.periodo, true)), datasets: [{ label: 'Altas', data: A.map((x) => +x.altas), backgroundColor: COLORES.exito + 'aa' }] },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
        },

        documentos(pane, d) {
            const T = d.tipos || [];
            const sri = T.filter((x) => x.sri);
            const internos = T.filter((x) => !x.sri);
            const totalSri = sri.reduce((a, x) => a + (+x.emitidos || 0), 0);
            const pend = sri.reduce((a, x) => a + (+x.pendientes || 0), 0);
            const fact = T.find((x) => x.clave === 'factura') || {};
            const p = d.pos || {};
            const cols = [
                { t: 'Documento', f: (x) => esc(x.etiqueta) },
                { t: 'Emitidos', cls: 'text-end', f: (x) => num(x.emitidos) },
                { t: 'Anulados', cls: 'text-end', f: (x) => num(x.anulados) },
                { t: 'Sin autorizar', cls: 'text-end', f: (x) => x.sri ? num(x.pendientes) : '<span class="text-muted">—</span>' },
                { t: 'Empresas', cls: 'text-end', f: (x) => num(x.empresas) },
                { t: 'Monto', cls: 'text-end', f: (x) => x.monto === null ? '<span class="text-muted">—</span>' : '$' + dinero(x.monto) }
            ];
            pane.innerHTML = `
                <div class="row g-2 mb-3">
                    ${kpi(num(totalSri), 'Documentos SRI autorizados', 'primary')}
                    ${kpi(num(fact.emitidos), 'Facturas', 'success', '$' + dinero(fact.monto))}
                    ${kpi(num(p.pos), 'Facturas POS', 'info', '$' + dinero(p.monto_pos))}
                    ${kpi(num(p.normal), 'Facturas normales', '', '$' + dinero(p.monto_normal))}
                    ${kpi(num(pend), 'Sin autorizar', pend ? 'warning' : '', 'borradores o pendientes')}
                    ${kpi(num(internos.reduce((a, x) => a + (+x.emitidos || 0), 0)), 'Documentos internos', '', 'compras, recibos, proformas, pedidos')}
                </div>
                <div class="row g-3 mb-3">
                    ${tarjeta('Documentos autorizados en el tiempo', lienzo('met-ch-docs'), 'col-lg-8', 'bi-graph-up')}
                    ${tarjeta('Mezcla de documentos SRI', lienzo('met-ch-mezcla'), 'col-lg-4', 'bi-pie-chart')}
                </div>
                <div class="row g-3">
                    ${tarjeta('Electrónicos (SRI)', tabla(cols, sri), 'col-lg-7', 'bi-cloud-check')}
                    ${tarjeta('Internos', tabla(cols, internos), 'col-lg-5', 'bi-folder2')}
                </div>`;

            const S = d.serie || [];
            const periodos = [...new Set(S.map((x) => x.periodo))].sort();
            const serie = (tipo) => periodos.map((p) => +(S.find((x) => x.tipo === tipo && x.periodo === p)?.n || 0));
            grafico('met-ch-docs', {
                type: 'line',
                data: {
                    labels: periodos.map((x) => etiquetaPeriodo(x, d.por_mes)),
                    datasets: [
                        { label: 'Facturas', data: serie('factura'), borderColor: COLORES.primario, backgroundColor: COLORES.primario, tension: .3 },
                        { label: 'Notas de crédito', data: serie('nota_credito'), borderColor: COLORES.naranja, backgroundColor: COLORES.naranja, tension: .3 },
                        { label: 'Retenciones', data: serie('retencion'), borderColor: COLORES.violeta, backgroundColor: COLORES.violeta, tension: .3 },
                        { label: 'Guías', data: serie('guia'), borderColor: COLORES.gris, backgroundColor: COLORES.gris, tension: .3 }
                    ]
                },
                options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
            const conDatos = sri.filter((x) => +x.emitidos > 0);
            grafico('met-ch-mezcla', {
                type: 'doughnut',
                data: {
                    labels: conDatos.map((x) => x.etiqueta),
                    datasets: [{ data: conDatos.map((x) => +x.emitidos), backgroundColor: [COLORES.primario, COLORES.naranja, COLORES.aviso, COLORES.violeta, COLORES.gris, COLORES.info, COLORES.exito] }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        },

        productos(pane, d) {
            const r = d.resumen || {};
            const cols = (campo) => [
                { t: '#', f: (x) => x._i },
                { t: 'Producto', cls: 'met-wrap', f: (x) => esc(x.producto) },
                { t: 'Empresa', cls: 'met-wrap met-empresas', f: (x) => esc(x.empresas_nombres || '') },
                { t: 'Cantidad', cls: 'text-end' + (campo === 'cantidad' ? ' fw-bold' : ''), f: (x) => num(x.cantidad, 2) },
                { t: 'Monto', cls: 'text-end' + (campo === 'monto' ? ' fw-bold' : ''), f: (x) => '$' + dinero(x.monto) },
                { t: 'Empresas', cls: 'text-end', f: (x) => num(x.empresas) },
                { t: 'Facturas', cls: 'text-end', f: (x) => num(x.facturas) }
            ];
            const idx = (arr) => (arr || []).map((x, i) => Object.assign({}, x, { _i: i + 1 }));
            pane.innerHTML = `
                <div class="row g-2 mb-3">
                    ${kpi(num(r.distintos), 'Productos distintos vendidos', 'primary')}
                    ${kpi(num(r.unidades, 2), 'Unidades vendidas', 'success')}
                    ${kpi('$' + dinero(r.monto), 'Monto sin impuestos')}
                    ${kpi(num(r.lineas), 'Líneas de factura')}
                </div>
                <div class="alert alert-light border small py-2 mb-3">
                    <i class="bi bi-info-circle me-1"></i>Cada empresa tiene su propio catálogo, así que se agrupa por la descripción del producto
                    (sin distinguir mayúsculas ni espacios). Solo facturas autorizadas de producción.
                </div>
                <div class="row g-3 mb-3">
                    ${tarjeta('Top 10 por cantidad', lienzo('met-ch-prod'), 'col-12', 'bi-bar-chart')}
                </div>
                <div class="row g-3">
                    ${tarjeta('Más vendidos por cantidad', tabla(cols('cantidad'), idx(d.por_cantidad)), 'col-xl-6', 'bi-123')}
                    ${tarjeta('Más vendidos por monto', tabla(cols('monto'), idx(d.por_monto)), 'col-xl-6', 'bi-currency-dollar')}
                </div>`;
            const top = (d.por_cantidad || []).slice(0, 10);
            grafico('met-ch-prod', {
                type: 'bar',
                data: { labels: top.map((x) => String(x.producto).slice(0, 30)), datasets: [{ label: 'Cantidad', data: top.map((x) => +x.cantidad), backgroundColor: COLORES.exito + 'aa' }] },
                options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
            });
        },

        sri(pane, d) {
            const t = d.totales || {};
            pane.innerHTML = `
                <div class="row g-2 mb-3">
                    ${kpi(num(t.total), 'Comprobantes enviados', 'primary')}
                    ${kpi(num(t.autorizado), 'Autorizados', 'success')}
                    ${kpi(num(t.rechazado), 'Devueltos / con error', 'danger')}
                    ${kpi(num(t.pendiente), 'En proceso', 'warning', 'recibidos, enviando o en procesamiento')}
                    ${kpi(num(t.tasa_rechazo, 1) + ' %', 'Tasa de rechazo', +t.tasa_rechazo > 5 ? 'danger' : '')}
                </div>
                <div class="alert alert-light border small py-2 mb-3">
                    <i class="bi bi-info-circle me-1"></i>Cuenta el <strong>último estado</strong> de cada comprobante enviado en el rango (un comprobante reenviado y luego autorizado cuenta como autorizado).
                </div>
                <div class="row g-3 mb-3">
                    ${tarjeta('Resultado por tipo de comprobante', lienzo('met-ch-sri'), 'col-lg-7', 'bi-bar-chart-steps')}
                    ${tarjeta('Por tipo', tabla([
                        { t: 'Tipo', f: (x) => esc(TIPOS_SRI[x.tipo] || x.tipo) },
                        { t: 'Autorizados', cls: 'text-end', f: (x) => num(x.autorizado) },
                        { t: 'Rechazados', cls: 'text-end', f: (x) => num(x.rechazado) },
                        { t: 'En proceso', cls: 'text-end', f: (x) => num(x.pendiente) }
                    ], d.por_tipo || []), 'col-lg-5', 'bi-table')}
                </div>
                <div class="row g-3">
                    ${tarjeta('Errores y rechazos más frecuentes', tabla([
                        { t: 'Tipo', f: (x) => esc(TIPOS_SRI[x.tipo_comprobante] || x.tipo_comprobante) },
                        { t: 'Mensaje', cls: 'met-wrap', f: (x) => esc(x.mensaje) },
                        { t: 'Veces', cls: 'text-end', f: (x) => num(x.n) },
                        { t: 'Empresas', cls: 'text-end', f: (x) => num(x.empresas) },
                        { t: 'Último', f: (x) => fecha(x.ultimo) }
                    ], d.frecuentes || [], 'Sin rechazos del SRI en el rango.'), 'col-12', 'bi-exclamation-octagon')}
                </div>`;
            const P = d.por_tipo || [];
            grafico('met-ch-sri', {
                type: 'bar',
                data: {
                    labels: P.map((x) => TIPOS_SRI[x.tipo] || x.tipo),
                    datasets: [
                        { label: 'Autorizados', data: P.map((x) => x.autorizado), backgroundColor: COLORES.exito + 'cc' },
                        { label: 'Rechazados', data: P.map((x) => x.rechazado), backgroundColor: COLORES.peligro + 'cc' },
                        { label: 'En proceso', data: P.map((x) => x.pendiente), backgroundColor: COLORES.aviso + 'cc' }
                    ]
                },
                options: { scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } }
            });
        },

        errores(pane, d) {
            const base = window.BASE_URL || '';
            pane.innerHTML = `
                <div class="row g-2 mb-3">
                    ${kpi(num(d.total), 'Errores registrados', d.total ? 'danger' : '')}
                    ${kpi(num(d.fatales), 'Fatales', d.fatales ? 'danger' : '')}
                    ${kpi(num((d.rutas || []).length), 'Rutas con errores')}
                    <div class="col-6 col-md-4 col-xl-2 d-flex align-items-center">
                        <a href="${base}/config/errores-sistema" class="btn btn-outline-danger btn-sm"><i class="bi bi-bug me-1"></i>Ver el detalle</a>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    ${tarjeta('Errores en el tiempo', lienzo('met-ch-err'), 'col-12', 'bi-graph-up')}
                </div>
                <div class="row g-3">
                    ${tarjeta('Dónde fallan más', tabla([
                        { t: 'Ruta', f: (x) => esc(x.ruta) },
                        { t: 'Errores', cls: 'text-end', f: (x) => num(x.n) },
                        { t: 'Usuarios', cls: 'text-end', f: (x) => num(x.usuarios) },
                        { t: 'Empresas', cls: 'text-end', f: (x) => num(x.empresas) },
                        { t: 'Último', f: (x) => fecha(x.ultimo) },
                        { t: 'Último mensaje', cls: 'met-wrap', f: (x) => esc(x.ultimo_mensaje) }
                    ], d.rutas || [], 'Sin errores en el rango.'), 'col-12', 'bi-signpost-split')}
                </div>`;
            const S = d.serie || [];
            grafico('met-ch-err', {
                type: 'bar',
                data: {
                    labels: S.map((x) => etiquetaPeriodo(x.periodo, d.por_mes)),
                    datasets: [
                        { label: 'Errores', data: S.map((x) => +x.total - +x.fatales), backgroundColor: COLORES.aviso + 'cc' },
                        { label: 'Fatales', data: S.map((x) => +x.fatales), backgroundColor: COLORES.peligro + 'cc' }
                    ]
                },
                options: { scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } }
            });
        }
    };

    /** Variación % con color: verde si sube, rojo si baja (o al revés si `menosEsMejor`). */
    function variacion(pct, menosEsMejor = false) {
        if (pct === null || pct === undefined) return '<span class="text-muted">sin periodo anterior</span>';
        const sube = pct > 0;
        const bueno = menosEsMejor ? !sube : sube;
        const color = pct === 0 ? 'secondary' : (bueno ? 'success' : 'danger');
        return `<span class="text-${color}"><i class="bi bi-arrow-${sube ? 'up' : (pct < 0 ? 'down' : 'right')}-short"></i>${num(Math.abs(pct), 1)} %</span> vs. periodo anterior`;
    }

    RENDER.tendencias = function (pane, d) {
        if (!d.disponible) {
            pane.innerHTML = `<div class="alert alert-light border small mb-0"><i class="bi bi-info-circle me-1"></i>
                El resumen diario todavía no está activo (falta aplicar <code>2026-11-05_metricas_diarias.sql</code>).</div>`;
            return;
        }
        const cob = d.cobertura || {};
        const a = d.actual || {}, v = d.variacion || {}, ant = d.anterior || {};
        const nombre = (x) => esc(x.nombre_comercial || x.nombre);
        const colsVar = [
            { t: 'Empresa', cls: 'met-wrap', f: nombre },
            { t: 'RUC', f: (x) => esc(x.ruc) },
            { t: 'Docs. periodo', cls: 'text-end fw-bold', f: (x) => num(x.actual) },
            { t: 'Docs. anterior', cls: 'text-end', f: (x) => num(x.anterior) },
            { t: 'Variación', cls: 'text-end', f: (x) => (x.diferencia > 0 ? '+' : '') + num(x.diferencia) + (x.pct !== null ? ` <span class="text-muted small">(${num(x.pct, 1)} %)</span>` : '') },
            { t: 'Ventas', cls: 'text-end', f: (x) => '$' + dinero(x.ventas_actual) },
            { t: 'Horas', cls: 'text-end', f: (x) => num(x.horas_actual, 1) }
        ];
        pane.innerHTML = `
            <div class="alert alert-light border small py-2 mb-3"><i class="bi bi-info-circle me-1"></i>
                Datos del <strong>resumen diario</strong> (se calcula cada noche; hoy no se incluye).
                Cubre del <strong>${fecha(cob.desde, false)}</strong> al <strong>${fecha(cob.hasta, false)}</strong>.
                Incluye los documentos migrados. Rango mostrado: ${fecha(d.desde, false)} a ${fecha(d.hasta, false)};
                se compara con el periodo anterior de igual largo (${fecha(ant.desde, false)} a ${fecha(ant.hasta, false)}).
                Admite todo el historial.</div>
            <div class="row g-2 mb-3">
                ${kpi(num(a.documentos), 'Documentos', 'primary', variacion(v.documentos))}
                ${kpi(num(a.facturas), 'Facturas', '', variacion(v.facturas))}
                ${kpi('$' + dinero(a.monto_ventas), 'Ventas facturadas', 'success', variacion(v.monto_ventas))}
                ${kpi(num(a.horas_uso, 1), 'Horas de uso', '', variacion(v.horas_uso))}
                ${kpi(num(a.logins), 'Inicios de sesión', '', variacion(v.logins))}
                ${kpi(num(a.errores), 'Errores', a.errores ? 'danger' : '', variacion(v.errores, true))}
            </div>
            <div class="row g-3 mb-3">
                ${tarjeta('Documentos y ventas', lienzo('met-ch-tend-docs'), 'col-lg-7', 'bi-graph-up')}
                ${tarjeta('Empresas y usuarios', lienzo('met-ch-tend-uso'), 'col-lg-5', 'bi-people')}
            </div>
            <div class="row g-3 mb-3">
                ${tarjeta('SRI y errores', lienzo('met-ch-tend-calidad'), 'col-12', 'bi-shield-check')}
            </div>
            <div class="row g-3 mb-3">
                ${tarjeta('Documentos emitidos por tipo en el rango (incluye migrados)', tabla([
                    { t: 'Documento', f: (x) => esc(x.etq) },
                    { t: 'Emitidos', cls: 'text-end fw-bold', f: (x) => num(x.n) }
                ], [
                    ['facturas', 'Facturas'], ['notas_credito', 'Notas de crédito'], ['notas_debito', 'Notas de débito'],
                    ['retenciones', 'Retenciones'], ['guias', 'Guías de remisión'], ['liquidaciones', 'Liquidaciones de compra'],
                    ['reembolsos', 'Facturas de reembolso'], ['compras', 'Compras registradas'], ['recibos', 'Recibos de venta'],
                    ['proformas', 'Proformas'], ['pedidos', 'Pedidos']
                ].map(([k, etq]) => ({ etq, n: (d.por_tipo || {})[k] || 0 }))), 'col-lg-5', 'bi-files')}
            </div>
            <div class="row g-3">
                ${tarjeta('Empresas que más crecen', tabla(colsVar, d.crecen || [], 'Ninguna empresa creció frente al periodo anterior.'), 'col-xl-6', 'bi-graph-up-arrow')}
                ${tarjeta('Empresas que más caen (riesgo de abandono)', tabla(colsVar, d.caen || [], 'Ninguna empresa cayó frente al periodo anterior.'), 'col-xl-6', 'bi-graph-down-arrow')}
            </div>`;

        const S = d.serie || [];
        const lbl = S.map((x) => d.agrupado === 'year' ? String(x.periodo).slice(0, 4) : etiquetaPeriodo(x.periodo, d.agrupado !== 'day'));
        const docs = (x) => ['facturas', 'notas_credito', 'notas_debito', 'retenciones', 'guias', 'liquidaciones', 'reembolsos', 'compras', 'recibos', 'proformas', 'pedidos']
            .reduce((s, k) => s + (+x[k] || 0), 0);
        grafico('met-ch-tend-docs', {
            type: 'bar',
            data: {
                labels: lbl,
                datasets: [
                    { label: 'Facturas', data: S.map((x) => +x.facturas), backgroundColor: COLORES.primario + 'aa', stack: 'd', yAxisID: 'y' },
                    { label: 'Otros documentos', data: S.map((x) => docs(x) - (+x.facturas || 0)), backgroundColor: COLORES.gris + '88', stack: 'd', yAxisID: 'y' },
                    { label: 'Ventas ($)', data: S.map((x) => +x.monto_ventas), type: 'line', borderColor: COLORES.exito, backgroundColor: COLORES.exito, tension: .3, yAxisID: 'y2' }
                ]
            },
            options: { scales: {
                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
                y2: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
            } }
        });
        grafico('met-ch-tend-uso', {
            type: 'line',
            data: {
                labels: lbl,
                datasets: [
                    { label: 'Empresas con documentos (máx./día)', data: S.map((x) => +x.empresas_con_docs_max || 0), borderColor: COLORES.primario, backgroundColor: COLORES.primario, tension: .3 },
                    { label: 'Empresas con uso (máx./día)', data: S.map((x) => +x.empresas_con_uso_max || 0), borderColor: COLORES.info, backgroundColor: COLORES.info, tension: .3 },
                    { label: 'Usuarios activos (prom./día)', data: S.map((x) => +x.usuarios_activos_prom || 0), borderColor: COLORES.violeta, backgroundColor: COLORES.violeta, tension: .3 }
                ]
            },
            options: { scales: { y: { beginAtZero: true } } }
        });
        grafico('met-ch-tend-calidad', {
            type: 'bar',
            data: {
                labels: lbl,
                datasets: [
                    { label: 'SRI autorizados', data: S.map((x) => +x.sri_autorizados || 0), backgroundColor: COLORES.exito + 'aa' },
                    { label: 'SRI rechazados', data: S.map((x) => +x.sri_rechazados || 0), backgroundColor: COLORES.naranja + 'cc' },
                    { label: 'Errores del sistema', data: S.map((x) => +x.errores || 0), backgroundColor: COLORES.peligro + 'aa' }
                ]
            },
            options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    };

    function bytes(v) {
        v = Number(v || 0);
        const u = ['B', 'KB', 'MB', 'GB', 'TB'];
        let i = 0;
        while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
        return num(v, i ? 1 : 0) + ' ' + u[i];
    }
    const vacio = (txt) => `<span class="text-muted">${esc(txt)}</span>`;

    RENDER.modulos = function (pane, d) {
        const cols = [
            { t: 'Submódulo', f: (x) => esc(x.submodulo) },
            { t: 'Módulo', f: (x) => esc(x.modulo) },
            { t: 'Aperturas', cls: 'text-end fw-bold', f: (x) => num(x.aperturas) },
            { t: 'Usuarios en el rango', cls: 'text-end', f: (x) => num(x.usuarios_rango) },
            { t: 'Empresas en el rango', cls: 'text-end', f: (x) => num(x.empresas_rango) },
            { t: 'Con permiso', cls: 'text-end', f: (x) => num(x.con_permiso) },
            { t: 'Último uso', f: (x) => fecha(x.ultimo_uso) }
        ];
        const escrituras = tarjeta('Lo que más se registra (auditoría)', tabla([
            { t: 'Tabla', f: (x) => `<code>${esc(x.tabla)}</code>` },
            { t: 'Total', cls: 'text-end fw-bold', f: (x) => num(x.total) },
            { t: 'Creaciones', cls: 'text-end', f: (x) => num(x.creaciones) },
            { t: 'Ediciones', cls: 'text-end', f: (x) => num(x.ediciones) },
            { t: 'Eliminaciones', cls: 'text-end', f: (x) => num(x.eliminaciones) },
            { t: 'Empresas', cls: 'text-end', f: (x) => num(x.empresas) },
            { t: 'Usuarios', cls: 'text-end', f: (x) => num(x.usuarios) }
        ], d.escrituras || [], 'Sin acciones auditadas en el rango.'), 'col-12', 'bi-journal-text');

        if (!d.disponible) {
            pane.innerHTML = `<div class="alert alert-light border small py-2 mb-3"><i class="bi bi-info-circle me-1"></i>
                Todavía no se registran las aperturas de submódulos (tabla <code>usuarios_submodulos_uso</code> de Accesos frecuentes).</div>
                <div class="row g-3">${escrituras}</div>`;
            return;
        }
        pane.innerHTML = `
            <div class="row g-2 mb-3">
                ${kpi(num(d.total), 'Submódulos activos', 'primary')}
                ${kpi(num(d.usados), 'Usados en el rango', 'success')}
                ${kpi(num(d.total - d.usados), 'Sin uso en el rango', 'warning')}
            </div>
            <div class="alert alert-light border small py-2 mb-3"><i class="bi bi-info-circle me-1"></i>
                Aperturas registradas desde el <strong>${fecha(d.desde, false)}</strong> (Accesos frecuentes del Inicio). No cuenta a los
                superadministradores. «Con permiso» = usuarios con permiso de ver ese submódulo.</div>
            <div class="row g-3 mb-3">
                ${tarjeta('Aperturas por módulo', lienzo('met-ch-modulos'), 'col-12', 'bi-bar-chart')}
            </div>
            <div class="row g-3">
                ${tarjeta('Submódulos más usados', tabla(cols, (d.top || []).filter((x) => +x.aperturas > 0), 'Ningún submódulo registra aperturas todavía.'), 'col-12', 'bi-star')}
                ${tarjeta('Submódulos que nadie abrió en el rango (los de más permisos primero)', tabla(cols, d.sin_uso || []), 'col-12', 'bi-eye-slash')}
                ${escrituras}
            </div>`;
        const M = (d.por_modulo || []).filter((x) => x.aperturas > 0).slice(0, 15);
        grafico('met-ch-modulos', {
            type: 'bar',
            data: { labels: M.map((x) => x.modulo), datasets: [{ label: 'Aperturas', data: M.map((x) => x.aperturas), backgroundColor: COLORES.primario + 'aa' }] },
            options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    };

    RENDER.comercial = function (pane, d) {
        const s = d.suscripciones || {};
        const e = d.empresas || {};
        const repTotal = (d.reportes || []).reduce((a, x) => a + (+x.n || 0), 0);
        const enRevision = (d.reportes || []).filter((x) => x.estado === 'en_revision').reduce((a, x) => a + (+x.n || 0), 0);
        const P = d.pagos || [];
        const periodos = [...new Set(P.map((x) => x.periodo))].sort();
        const estados = [...new Set(P.map((x) => x.estado))];
        const colEst = { exitoso: COLORES.exito, pagado: COLORES.exito, pendiente: COLORES.aviso, fallido: COLORES.peligro, rechazado: COLORES.peligro };
        pane.innerHTML = `
            ${d.controladoras ? '' : `<div class="alert alert-light border small py-2 mb-3"><i class="bi bi-info-circle me-1"></i>
                Ninguna empresa tiene asignada una empresa que cobre sus suscripciones (<em>Empresas del sistema → Cobro y vigencia</em>), así que no hay suscripciones de la plataforma que mostrar.</div>`}
            <div class="row g-2 mb-3">
                ${kpi(num(s.activas), 'Suscripciones activas', 'primary', `${num(s.otras)} en otro estado`)}
                ${kpi('$' + dinero(s.mrr), 'Ingreso mensual recurrente', 'success', 'sin IVA (MRR)')}
                ${kpi(num(s.atrasadas), 'Cobro atrasado', +s.atrasadas ? 'danger' : '', 'más de 3 días')}
                ${kpi(num(s.cobros_30d), 'Cobros en 30 días', '', '$' + dinero(s.monto_30d) + ' sin IVA')}
                ${kpi(num(enRevision), 'Pagos reportados en revisión', enRevision ? 'warning' : '', `${num(repTotal)} en 90 días`)}
                ${kpi(num(e.regalia), 'Plan sin costo', '', `${num(e.demo)} demo`)}
            </div>
            <div class="row g-3 mb-3">
                ${tarjeta('Cobros de suscripciones por mes', lienzo('met-ch-cobros'), 'col-lg-7', 'bi-calendar-check')}
                ${tarjeta('Pagos reportados (90 días)', tabla([
                    { t: 'Estado', f: (x) => esc(String(x.estado).replace(/_/g, ' ')) },
                    { t: 'Cantidad', cls: 'text-end', f: (x) => num(x.n) },
                    { t: 'Más antiguo', f: (x) => fecha(x.mas_antiguo) }
                ], d.reportes || [], 'Sin pagos reportados.'), 'col-lg-5', 'bi-receipt')}
            </div>
            <div class="row g-3">
                ${tarjeta('Suscripciones con cobro atrasado', tabla([
                    { t: 'Cliente', cls: 'met-wrap', f: (x) => esc(x.cliente) },
                    { t: 'Identificación', f: (x) => esc(x.identificacion) },
                    { t: 'Periodicidad', f: (x) => esc(x.periodicidad) },
                    { t: 'Próximo cobro', f: (x) => fecha(x.proximo_cobro, false) },
                    { t: 'Días', cls: 'text-end', f: (x) => badge(x.dias, +x.dias > 30 ? 'danger' : 'warning') },
                    { t: 'Valor sin IVA', cls: 'text-end', f: (x) => '$' + dinero(x.subtotal) }
                ], d.atrasadas || [], 'Ninguna suscripción tiene el cobro atrasado.'), 'col-12', 'bi-exclamation-circle')}
                ${tarjeta('Cobros en línea de todas las empresas (Payphone / Nuvei)', tabla([
                    { t: 'Pasarela', f: (x) => esc(x.pasarela === 'payphone' ? 'Payphone' : 'Nuvei') },
                    { t: 'Estado', f: (x) => esc(x.estado) },
                    { t: 'Transacciones', cls: 'text-end', f: (x) => num(x.n) },
                    { t: 'Monto', cls: 'text-end', f: (x) => '$' + dinero(x.monto) },
                    { t: 'Empresas', cls: 'text-end', f: (x) => num(x.empresas) },
                    { t: 'Empresa', cls: 'met-wrap met-empresas', f: (x) => esc(x.empresas_nombres || '') }
                ], d.pasarelas || [], 'Sin cobros en línea en el rango.'), 'col-12', 'bi-credit-card')}
            </div>`;
        grafico('met-ch-cobros', {
            type: 'bar',
            data: {
                labels: periodos.map((p) => etiquetaPeriodo(p, true)),
                datasets: estados.map((est) => ({
                    label: est, stack: 'c',
                    data: periodos.map((p) => +(P.find((x) => x.periodo === p && x.estado === est)?.monto || 0)),
                    backgroundColor: (colEst[est] || COLORES.gris) + 'bb'
                }))
            },
            options: { scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } }
        });
    };

    RENDER.salud = function (pane, d) {
        const bd = d.bd || {}, sv = d.servidor || {}, cr = d.cron || {}, er = d.errores || {};
        const usoCon = bd.max_conexiones ? Math.round(bd.conexiones * 100 / bd.max_conexiones) : 0;
        const disco = sv.disco_total ? Math.round((sv.disco_total - sv.disco_libre) * 100 / sv.disco_total) : null;
        const horasCron = cr.ultimo_resumen ? (Date.now() - new Date(String(cr.ultimo_resumen).replace(' ', 'T'))) / 3600000 : null;
        const op = sv.opcache, ap = sv.apcu;
        const fila = (k, v) => `<tr><td class="text-muted">${esc(k)}</td><td class="text-end">${v}</td></tr>`;
        const mini = (filas) => `<div class="metricas-scroll"><table class="table table-sm mb-0"><tbody>${filas}</tbody></table></div>`;
        pane.innerHTML = `
            <div class="row g-2 mb-3">
                ${kpi(bytes(bd.bytes), 'Tamaño de la base', 'primary')}
                ${kpi(`${num(bd.conexiones)} / ${num(bd.max_conexiones)}`, 'Conexiones', usoCon >= 80 ? 'danger' : '', `${num(bd.activas)} activas · ${num(bd.idle_tx)} en transacción sin cerrar`)}
                ${kpi(num(bd.cache_hit, 2) + ' %', 'Aciertos de caché BD', +bd.cache_hit < 95 ? 'warning' : 'success')}
                ${kpi(disco === null ? '—' : disco + ' %', 'Disco usado', disco >= 85 ? 'danger' : '', disco === null ? '' : bytes(sv.disco_libre) + ' libres')}
                ${kpi(num(er.ultimas_24h), 'Errores 24 h', +er.ultimas_24h ? 'danger' : 'success', `${num(er.ultima_hora)} en la última hora · ${num(er.fatales_24h)} fatales`)}
                ${kpi(horasCron === null ? '—' : (horasCron < 26 ? 'Al día' : 'Atrasado'), 'Tarea automática (cron)', horasCron === null ? '' : (horasCron < 26 ? 'success' : 'danger'),
                    cr.ultimo_resumen ? 'resumen diario: ' + fecha(cr.ultimo_resumen) : 'sin resumen diario aún')}
            </div>
            <div class="row g-3 mb-3">
                ${tarjeta('Servidor PHP', mini(
                    fila('PHP', esc(sv.php) + ' · ' + esc(sv.sapi)) + fila('Sistema', esc(sv.so)) +
                    fila('memory_limit', esc(sv.memory_limit)) + fila('max_execution_time', num(sv.max_execution_time) + ' s') +
                    fila('upload_max_filesize', esc(sv.upload_max)) + fila('Hora del servidor', fecha(sv.hora) + ' (' + esc(sv.zona) + ')') +
                    fila('OPcache', op ? (op.activo ? badge('Activo', 'success') + ` ${num(op.hit, 1)} % aciertos · ${num(op.scripts)} scripts · ${bytes(op.memoria_libre)} libres` : badge('Inactivo', 'danger')) : vacio('no disponible')) +
                    fila('OPcache reiniciado', op && op.reinicio ? fecha(op.reinicio) : vacio('—')) +
                    fila('APCu (caché)', ap ? badge('Activo', 'success') + ` ${num(ap.entradas)} entradas · ${bytes(ap.memoria_libre)} libres de ${bytes(ap.memoria_total)}` : badge('No disponible', 'warning'))
                ), 'col-lg-6', 'bi-hdd-network')}
                ${tarjeta('Base de datos', mini(
                    fila('Versión', esc(String(bd.version || '').split(' on ')[0])) + fila('Base', esc(bd.nombre)) +
                    fila('Bloqueos mutuos (deadlocks)', num(bd.deadlocks)) +
                    fila('Archivos temporales (ordenamientos a disco)', bytes(bd.temp_bytes)) +
                    fila('Estadísticas desde', bd.stats_desde ? fecha(bd.stats_desde) : vacio('inicio del servidor'))
                ) + `<div class="px-2 pt-2 met-card-titulo">Tareas automáticas</div>` + mini(
                    (cr.marcas || []).map((m) => fila(m.tarea, m.ultima ? fecha(m.ultima) : vacio('no visible desde la web'))).join('') +
                    fila('Ejecución en curso', cr.en_ejecucion ? 'desde ' + fecha(cr.en_ejecucion) : vacio('no'))
                ), 'col-lg-6', 'bi-database')}
            </div>
            <div class="row g-3">
                ${tarjeta('Comprobantes atascados en el SRI (más de 1 hora sin respuesta, últimos 15 días)', tabla([
                    { t: 'Empresa', cls: 'met-wrap', f: (x) => esc(x.empresa) },
                    { t: 'Tipo', f: (x) => esc(TIPOS_SRI[x.tipo_comprobante] || x.tipo_comprobante) },
                    { t: 'Estado', f: (x) => badge(String(x.accion).replace(/_/g, ' '), 'warning') },
                    { t: 'Desde', f: (x) => fecha(x.created_at) },
                    { t: 'Clave de acceso', f: (x) => `<code class="small">${esc(x.clave_acceso)}</code>` }
                ], d.sri_atascados || [], 'No hay comprobantes atascados.'), 'col-12', 'bi-hourglass-split')}
                ${tarjeta('Consultas en curso de más de 10 segundos', tabla([
                    { t: 'PID', f: (x) => esc(x.pid) },
                    { t: 'Estado', f: (x) => esc(x.state) },
                    { t: 'Segundos', cls: 'text-end fw-bold', f: (x) => num(x.segundos) },
                    { t: 'Espera', f: (x) => esc(x.wait_event_type || '—') },
                    { t: 'Consulta', cls: 'met-wrap', f: (x) => `<code class="small">${esc(x.consulta)}</code>` }
                ], d.en_curso || [], 'Ninguna consulta lleva más de 10 segundos.'), 'col-12', 'bi-stopwatch')}
                ${d.pg_stat_statements ? tarjeta('Consultas que más tiempo consumen (pg_stat_statements)', tabla([
                    { t: 'Llamadas', cls: 'text-end', f: (x) => num(x.llamadas) },
                    { t: 'Total (s)', cls: 'text-end fw-bold', f: (x) => num(x.segundos_total, 1) },
                    { t: 'Promedio (ms)', cls: 'text-end', f: (x) => num(x.ms_promedio, 1) },
                    { t: 'Máximo (ms)', cls: 'text-end', f: (x) => num(x.ms_maximo, 1) },
                    { t: 'Consulta', cls: 'met-wrap', f: (x) => `<code class="small">${esc(x.consulta)}</code>` }
                ], d.pesadas || [], 'Sin datos (la extensión no está cargada).'), 'col-12', 'bi-speedometer') : ''}
                ${tarjeta('Tablas más pesadas', tabla([
                    { t: 'Tabla', f: (x) => `<code>${esc(x.tabla)}</code>` },
                    { t: 'Tamaño', cls: 'text-end fw-bold', f: (x) => bytes(x.bytes) },
                    { t: 'Índices', cls: 'text-end', f: (x) => bytes(x.bytes_indices) },
                    { t: 'Filas (aprox.)', cls: 'text-end', f: (x) => num(x.filas) },
                    { t: 'Filas muertas', cls: 'text-end', f: (x) => +x.muertas > (+x.filas || 1) * 0.2 ? badge(num(x.muertas), 'warning') : num(x.muertas) },
                    { t: 'Último vacuum', f: (x) => fecha(x.ultimo_vacuum) },
                    { t: 'Último analyze', f: (x) => fecha(x.ultimo_analyze) }
                ], d.tablas || []), 'col-12', 'bi-table')}
            </div>`;
    };

    // ─── API pública (llamada desde la vista) ───────────────────────────────
    window.MET_aplicarPeriodo = function (p) {
        const hoy = new Date();
        let d = new Date(hoy), h = new Date(hoy);
        switch (p) {
            case 'hoy': break;
            case '7d': d.setDate(hoy.getDate() - 6); break;
            case '30d': d.setDate(hoy.getDate() - 29); break;
            case 'mes': d = new Date(hoy.getFullYear(), hoy.getMonth(), 1); break;
            case 'mes_ant': d = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1); h = new Date(hoy.getFullYear(), hoy.getMonth(), 0); break;
            case 'anio': d = new Date(hoy.getFullYear(), 0, 1); break;
            case '12m': d = new Date(hoy.getFullYear() - 1, hoy.getMonth(), hoy.getDate() + 1); break;
            case '24m': d = new Date(hoy.getFullYear() - 2, hoy.getMonth(), hoy.getDate() + 1); break;
            case '5a': d = new Date(hoy.getFullYear() - 5, hoy.getMonth(), hoy.getDate() + 1); break;
            case 'todo': d = new Date(2000, 0, 1); break;   // el servidor lo recorta al primer día con datos
            default: return;
        }
        document.getElementById('met-desde').value = isoLocal(d);
        document.getElementById('met-hasta').value = isoLocal(h);
    };

    window.MET_limpiar = function () {
        document.getElementById('met-periodo').value = 'mes';
        window.MET_aplicarPeriodo('mes');
        window.MET_mostrar(false);
    };

    window.MET_mostrar = function (refrescar) {
        const r = rango();
        if (!r.desde || !r.hasta || r.desde > r.hasta) {
            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', text: 'Revise el rango de fechas.' });
            return;
        }
        Object.keys(cargadas).forEach((k) => delete cargadas[k]);
        cargarResumen(refrescar);
        // Rangos de más de un año: solo Tendencias los admite, así que se salta a esa pestaña.
        const btnTend = document.querySelector('#met-tabs [data-seccion="tendencias"]');
        if (diasRango() > 366 && seccionActiva() !== 'tendencias' && btnTend && window.bootstrap) {
            bootstrap.Tab.getOrCreateInstance(btnTend).show();   // su evento shown carga la pestaña
            return;
        }
        cargarSeccion(seccionActiva(), refrescar);
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('#met-tabs [data-bs-toggle="tab"]').forEach((b) => {
            b.addEventListener('shown.bs.tab', () => cargarSeccion(b.dataset.seccion));
        });
        window.MET_mostrar(false);
    });
})();
