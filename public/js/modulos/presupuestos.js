/**
 * presupuestos.js — Módulo Presupuestos.
 *
 * Listado (orden multi-columna sin recarga, buscador, paginación) y modal con cuatro pestañas:
 * Datos, Presupuesto (grilla cuentas × meses editable en la versión en borrador), Ejecución
 * (presupuesto vs real con semáforo; clic en una cifra muestra los asientos) y Versiones.
 * Configuración en window.PRE_CFG (url, permisos, orden inicial, catálogos).
 */
window.PRE = (function () {
    'use strict';

    const CFG = window.PRE_CFG || {};
    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = n => { const v = parseFloat(n) || 0; return (v < 0 ? '-$' : '$') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    const num2 = n => (Math.round((parseFloat(n) || 0) * 100) / 100).toFixed(2);
    const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const mesCorto = ym => { const m = /^(\d{4})-(\d{2})/.exec(ym || ''); return m ? `${MESES[+m[2] - 1]}-${m[1]}` : (ym || ''); };
    const fecha = iso => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || ''); return m ? `${m[3]}-${m[2]}-${m[1]}` : (iso || ''); };
    const ESTADO_CLASE = { borrador: 'secondary', aprobado: 'success', cerrado: 'dark', aprobada: 'success', reemplazada: 'secondary' };

    async function pedir(url, opciones) {
        const r = await fetch(url, Object.assign({ headers: { 'X-Requested-With': 'XMLHttpRequest' } }, opciones || {}));
        return r.json();
    }
    function post(accion, datos) {
        const fd = new FormData();
        Object.entries(datos || {}).forEach(([k, v]) => fd.append(k, v ?? ''));
        return pedir(`${CFG.url}/${accion}`, { method: 'POST', body: fd });
    }
    function aviso(icon, title, text, target) {
        return Swal.fire({ icon, title, text, target: target || document.body });
    }
    /** Mensajes del servidor «texto|#id»: enfoca el control. */
    function errorForm(mensaje) {
        const [txt, sel] = String(mensaje || '').split('|');
        aviso('error', 'Revise', txt, $('modalPre'));
        if (sel) { const el = document.querySelector(sel); el?.focus(); el?.classList.add('is-invalid'); setTimeout(() => el?.classList.remove('is-invalid'), 2500); }
    }

    // ═══════════════════════ LISTADO ═══════════════════════
    const L = { page: CFG.page || 1, sorts: CFG.sorts || [], timer: null };

    async function cargarListado(page) {
        L.page = page || L.page || 1;
        const tbody = $('pre-tbody');
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Cargando…</td></tr>';
        const q = new URLSearchParams({ b: $('pre-buscar').value, page: L.page, orden: (window.CMG_ordenParam ? window.CMG_ordenParam(L.sorts) : '') });
        try {
            const res = await pedir(`${CFG.url}/searchAjax?${q}`);
            if (!res.ok) { tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${esc(res.mensaje)}</td></tr>`; return; }
            pintarListado(res);
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Error de comunicación con el servidor.</td></tr>';
        }
    }

    function pintarListado(res) {
        const tbody = $('pre-tbody');
        if (!res.rows.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted">No se encontraron presupuestos.</td></tr>';
        } else {
            tbody.innerHTML = res.rows.map(r => {
                const cls = ESTADO_CLASE[r.estado] || 'secondary';
                const sem = r.pct_ejecucion === null ? '' : `<span class="pre-sem pre-sem-${semaforoPct(r.pct_ejecucion, r.umbral_amarillo, r.umbral_rojo)}"></span>`;
                return `<tr class="pre-row" data-id="${r.id}" onclick="PRE.abrir(${r.id})">
                    <td class="ps-3 fw-medium" data-col="nombre">${esc(r.nombre)}</td>
                    <td data-col="periodo">${mesCorto(r.periodo_desde)} a ${mesCorto(r.periodo_hasta)}</td>
                    <td data-col="alcance">${esc(alcanceTexto(r))}</td>
                    <td class="text-center" data-col="version"><small>${esc(r.version_nombre || '—')}${r.version_estado ? ` <span class="text-muted">(${esc(r.version_estado)})</span>` : ''}</small></td>
                    <td class="text-end" data-col="ingresos">${money(r.ingresos)}</td>
                    <td class="text-end" data-col="gastos">${money(r.gastos)}</td>
                    <td class="text-end" data-col="ejecutado">${r.ejecutado_gastos === null ? '<span class="text-muted">—</span>' : money(r.ejecutado_gastos)}</td>
                    <td class="text-center" data-col="pct">${r.pct_ejecucion === null ? '<span class="text-muted">—</span>' : sem + r.pct_ejecucion + ' %'}</td>
                    <td class="text-center pe-3" data-col="estado"><span class="badge bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25">${esc(r.estado)}</span></td>
                </tr>`;
            }).join('');
        }
        const from = res.total ? (res.page - 1) * res.per_page + 1 : 0;
        const to = res.total ? Math.min(res.page * res.per_page, res.total) : 0;
        $('pre-pag-info').textContent = `${from}-${to}/${res.total}`;
        const [prev, next] = $('pre-paginacion').querySelectorAll('[data-pag]');
        prev.disabled = res.page <= 1;
        next.disabled = res.page >= res.total_pages;
        $('pre-btn-pdf').href = res.pdf_url;
        $('pre-btn-excel').href = res.excel_url;
    }

    function semaforoPct(pct, am, ro) {
        pct = parseFloat(pct); am = parseFloat(am) || 90; ro = parseFloat(ro) || 100;
        return pct >= ro ? 'rojo' : (pct >= am ? 'amarillo' : 'verde');
    }
    function alcanceTexto(r) {
        if (r.alcance === 'centro_costo') return 'C. costo: ' + (r.centro_costo_nombre || '');
        if (r.alcance === 'proyecto') return 'Proyecto: ' + (r.proyecto_nombre || '');
        return 'Toda la empresa';
    }

    // ═══════════════════════ MODAL: estado ═══════════════════════
    const M = { id: 0, cab: null, version: null, versiones: [], meses: [], lineas: [], rubros: [], editable: false, ejecVista: 'cuentas', ejec: null, dirty: false };

    function modal() { return bootstrap.Modal.getOrCreateInstance($('modalPre')); }

    function nuevo() {
        M.id = 0; M.cab = null; M.version = null; M.versiones = []; M.meses = []; M.lineas = []; M.editable = false; M.ejec = null; M.dirty = false;
        $('formPre').reset();
        $('pre_id').value = '';
        $('pre_anio').value = new Date().getFullYear() + 1;
        $('pre-titulo').textContent = 'Nuevo presupuesto';
        $('pre-badge-estado').classList.add('d-none');
        $('pre-version-label').textContent = '';
        onTipoPeriodo(); onAlcance();
        ['pre-btn-aprobar', 'pre-btn-reforma', 'pre-btn-cerrar', 'pre-btn-reabrir', 'pre-btn-descartar-reforma', 'pre-btn-eliminar', 'pre-btn-guardar-grilla'].forEach(id => $(id)?.classList.add('d-none'));
        $('pre-aviso-aprobado').style.display = 'none';
        habilitarDatos(true);
        $('pre-grilla-vacio').classList.remove('d-none'); $('pre-grilla-wrap').classList.add('d-none');
        $('pre-ejec-vacio').classList.remove('d-none'); $('pre-ejec-wrap').classList.add('d-none');
        $('pre-versiones-body').innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">—</td></tr>';
        bootstrap.Tab.getOrCreateInstance($('pre-tab-datos-btn')).show();
        modal().show();
    }

    async function abrir(id, idVersion) {
        try {
            const q = new URLSearchParams({ id, id_version: idVersion || '' });
            const res = await pedir(`${CFG.url}/getAjax?${q}`);
            if (!res.ok) return aviso('error', 'No se pudo abrir', res.mensaje);
            cargarDetalle(res);
            if (!$('modalPre').classList.contains('show')) {
                bootstrap.Tab.getOrCreateInstance($('pre-tab-datos-btn')).show();
                modal().show();
            }
        } catch (e) { aviso('error', 'No se pudo abrir', 'Error de comunicación con el servidor.'); }
    }

    function cargarDetalle(res) {
        const c = res.cabecera;
        M.id = +c.id; M.cab = c; M.version = res.version; M.versiones = res.versiones; M.meses = c.meses; M.rubros = res.rubros; M.ejec = null; M.dirty = false;
        M.lineas = (res.lineas || []).map(l => ({ id: +l.id, id_cuenta: +l.id_cuenta, cuenta_codigo: l.cuenta_codigo, cuenta_nombre: l.cuenta_nombre, es_ingreso: !!l.es_ingreso,
            id_rubro: l.id_rubro ? +l.id_rubro : null, valores: Object.assign({}, l.valores || {}) }));
        M.editable = !!(M.version && M.version.estado === 'borrador' && c.estado !== 'cerrado' && (CFG.perm.actualizar || CFG.perm.crear));

        $('pre_id').value = c.id;
        $('pre_nombre').value = c.nombre || '';
        $('pre_tipo_periodo').value = c.tipo_periodo || 'anual';
        const d = String(c.periodo_desde).slice(0, 7), h = String(c.periodo_hasta).slice(0, 7);
        $('pre_anio').value = d.slice(0, 4); $('pre_mes').value = d; $('pre_desde').value = d; $('pre_hasta').value = h;
        $('pre_alcance').value = c.alcance || 'empresa';
        $('pre_id_centro_costo').value = c.id_centro_costo || '';
        $('pre_id_proyecto').value = c.id_proyecto || '';
        $('pre_umbral_amarillo').value = parseFloat(c.umbral_amarillo) || 90;
        $('pre_umbral_rojo').value = parseFloat(c.umbral_rojo) || 100;
        $('pre_observaciones').value = c.observaciones || '';
        onTipoPeriodo(); onAlcance();

        $('pre-titulo').textContent = c.nombre;
        const b = $('pre-badge-estado'); const cls = ESTADO_CLASE[c.estado] || 'secondary';
        b.className = `badge ms-2 bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25`; b.textContent = c.estado;
        $('pre-version-label').textContent = M.version ? `Mostrando: ${M.version.nombre} (${M.version.estado})` : 'Sin versiones';

        const tieneAprobada = !!c.tiene_aprobada;
        habilitarDatos(!tieneAprobada && c.estado !== 'cerrado');
        $('pre-aviso-aprobado').style.display = tieneAprobada ? '' : 'none';
        const hayBorrador = !!c.id_version_borrador;
        toggle('pre-btn-aprobar', hayBorrador && c.estado !== 'cerrado' && M.version && M.version.estado === 'borrador');
        toggle('pre-btn-reforma', tieneAprobada && !hayBorrador && c.estado !== 'cerrado');
        toggle('pre-btn-cerrar', c.estado === 'aprobado');
        toggle('pre-btn-reabrir', c.estado === 'cerrado');
        toggle('pre-btn-descartar-reforma', hayBorrador && M.version && M.version.estado === 'borrador' && +M.version.numero > 1);
        toggle('pre-btn-eliminar', !tieneAprobada);
        toggle('pre-btn-guardar-grilla', M.editable);
        const btnG = $('pre-btn-guardar'); if (btnG) btnG.disabled = c.estado === 'cerrado';

        pintarGrilla();
        pintarVersiones();
        $('pre-ejec-vacio').classList.remove('d-none'); $('pre-ejec-wrap').classList.add('d-none');
    }

    function toggle(id, si) { $(id)?.classList.toggle('d-none', !si); }
    function habilitarDatos(si) {
        ['pre_tipo_periodo', 'pre_anio', 'pre_mes', 'pre_desde', 'pre_hasta', 'pre_alcance', 'pre_id_centro_costo', 'pre_id_proyecto'].forEach(id => { $(id).disabled = !si; });
    }
    function onTipoPeriodo() {
        const t = $('pre_tipo_periodo').value;
        $('pre-wrap-anio').classList.toggle('d-none', t !== 'anual');
        $('pre-wrap-mes').classList.toggle('d-none', t !== 'mensual');
        $('pre-wrap-desde').classList.toggle('d-none', t !== 'personalizado');
        $('pre-wrap-hasta').classList.toggle('d-none', t !== 'personalizado');
    }
    function onAlcance() {
        const a = $('pre_alcance').value;
        $('pre-wrap-cc').classList.toggle('d-none', a !== 'centro_costo');
        $('pre-wrap-pr').classList.toggle('d-none', a !== 'proyecto');
    }

    // ── Guardar datos ──
    async function guardarDatos(e) {
        e?.preventDefault();
        const datos = Object.fromEntries(new FormData($('formPre')).entries());
        // Los disabled no viajan en FormData: se completan desde los controles.
        ['tipo_periodo', 'anio', 'mes', 'desde', 'hasta', 'alcance', 'id_centro_costo', 'id_proyecto'].forEach(k => { datos[k] = $('pre_' + k).value; });
        const btn = $('pre-btn-guardar'); btn.disabled = true;
        try {
            const res = await post(M.id ? 'updateAjax' : 'storeAjax', datos);
            if (!res.ok) return errorForm(res.mensaje);
            await abrir(res.id || M.id);
            cargarListado();
            if (!M.id || res.id) bootstrap.Tab.getOrCreateInstance($('pre-tab-grilla-btn')).show();
            Swal.fire({ icon: 'success', title: 'Listo', text: res.mensaje, timer: 1800, showConfirmButton: false, target: $('modalPre') });
        } catch (err) { aviso('error', 'No se pudo guardar', 'Error de comunicación con el servidor.', $('modalPre')); }
        finally { btn.disabled = false; }
    }

    async function eliminar() {
        const c = await Swal.fire({ icon: 'warning', title: '¿Eliminar este presupuesto?', text: 'Solo se eliminan presupuestos que nunca se aprobaron.', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', target: $('modalPre') });
        if (!c.isConfirmed) return;
        const res = await post('eliminarAjax', { id: M.id });
        if (!res.ok) return aviso('error', 'No se pudo', res.mensaje, $('modalPre'));
        modal().hide(); cargarListado();
        aviso('success', 'Listo', res.mensaje);
    }

    async function cambiarEstado(estado) {
        const res = await post('cambiarEstadoAjax', { id: M.id, estado });
        if (!res.ok) return aviso('error', 'No se pudo', res.mensaje, $('modalPre'));
        await abrir(M.id); cargarListado();
    }

    // ═══════════════════════ GRILLA ═══════════════════════
    function pintarGrilla() {
        if (!M.id || !M.version) { $('pre-grilla-vacio').classList.remove('d-none'); $('pre-grilla-wrap').classList.add('d-none'); return; }
        $('pre-grilla-vacio').classList.add('d-none'); $('pre-grilla-wrap').classList.remove('d-none');
        $('pre-grilla-herramientas').style.display = M.editable ? '' : 'none';
        $('pre-grilla-nota').textContent = M.editable ? '' : 'Versión aprobada: solo lectura. Para cambiar montos cree una reforma.';
        $('pre-grilla-aviso').classList.add('d-none');

        $('pre-grilla-head').innerHTML = `<th class="pre-cuenta ps-2">Cuenta</th><th>Rubro</th>`
            + M.meses.map(ym => `<th class="text-end">${mesCorto(ym)}</th>`).join('')
            + `<th class="text-end">Total</th><th style="width:28px"></th>`;

        const rubroSel = (l) => M.editable
            ? `<select class="pre-rubro form-select" onchange="PRE.setRubro(${l.id_cuenta}, this.value)"><option value="">—</option>${M.rubros.map(r => `<option value="${r.id}" ${l.id_rubro === +r.id ? 'selected' : ''}>${esc(r.nombre)}</option>`).join('')}</select>`
            : `<span class="small text-muted">${esc((M.rubros.find(r => +r.id === l.id_rubro) || {}).nombre || '—')}</span>`;

        $('pre-grilla-body').innerHTML = M.lineas.length ? M.lineas.map(l => {
            const total = M.meses.reduce((s, ym) => s + (parseFloat(l.valores[ym]) || 0), 0);
            return `<tr class="${l.es_ingreso ? 'pre-ingreso' : 'pre-gasto'}" data-cuenta="${l.id_cuenta}">
                <td class="pre-fija pre-cuenta" title="${esc(l.cuenta_codigo + ' ' + l.cuenta_nombre)}"><b>${esc(l.cuenta_codigo)}</b> <span class="text-muted">${esc(l.cuenta_nombre)}</span></td>
                <td class="pre-fija">${rubroSel(l)}</td>
                ${M.meses.map(ym => `<td class="text-end"><input type="text" inputmode="decimal" class="pre-monto" value="${num2(l.valores[ym])}" data-ym="${ym}" ${M.editable ? '' : 'readonly'}
                    onfocus="this.select()" onchange="PRE.setMonto(${l.id_cuenta}, '${ym}', this.value)"></td>`).join('')}
                <td class="text-end pre-total-fila"><input type="text" inputmode="decimal" class="pre-monto fw-bold" value="${num2(total)}" title="Escriba el total y se reparte en partes iguales" ${M.editable ? '' : 'readonly'}
                    onfocus="this.select()" onchange="PRE.repartirTotal(${l.id_cuenta}, this.value)"></td>
                <td class="text-center">${M.editable ? `<button type="button" class="btn btn-link btn-sm p-0 btn-quitar" onclick="PRE.quitar(${l.id_cuenta})" title="Quitar"><i class="bi bi-x-lg"></i></button>` : ''}</td>
            </tr>`;
        }).join('') : `<tr><td colspan="${M.meses.length + 4}" class="text-center text-muted py-4">Sin cuentas. ${M.editable ? 'Agregue cuentas con el buscador, copie de otro presupuesto o cargue la plantilla.' : ''}</td></tr>`;
        pintarPie();
    }

    function pintarPie() {
        const sum = (ing, ym) => M.lineas.filter(l => l.es_ingreso === ing).reduce((s, l) => s + (ym ? (parseFloat(l.valores[ym]) || 0) : M.meses.reduce((t, m) => t + (parseFloat(l.valores[m]) || 0), 0)), 0);
        const fila = (et, ing) => `<tr><td class="pre-cuenta" colspan="2">${et}</td>${M.meses.map(ym => `<td class="text-end">${money(sum(ing, ym))}</td>`).join('')}<td class="text-end">${money(sum(ing, null))}</td><td></td></tr>`;
        $('pre-grilla-foot').innerHTML = fila('Total ingresos', true) + fila('Total costos y gastos', false);
    }

    function linea(idCuenta) { return M.lineas.find(l => l.id_cuenta === +idCuenta); }
    function setMonto(idCuenta, ym, v) {
        const l = linea(idCuenta); if (!l) return;
        l.valores[ym] = Math.max(0, parseFloat(String(v).replace(/,/g, '')) || 0);
        M.dirty = true; refrescarFila(l);
    }
    function repartirTotal(idCuenta, v) {
        const l = linea(idCuenta); if (!l) return;
        const total = Math.max(0, parseFloat(String(v).replace(/,/g, '')) || 0);
        const n = M.meses.length;
        const parte = Math.floor(total / n * 100) / 100;
        M.meses.forEach((ym, i) => { l.valores[ym] = i === n - 1 ? Math.round((total - parte * (n - 1)) * 100) / 100 : parte; });
        M.dirty = true; refrescarFila(l);
    }
    function refrescarFila(l) {
        const tr = $('pre-grilla-body').querySelector(`tr[data-cuenta="${l.id_cuenta}"]`); if (!tr) return;
        tr.querySelectorAll('input.pre-monto[data-ym]').forEach(inp => { inp.value = num2(l.valores[inp.dataset.ym]); });
        tr.querySelector('.pre-total-fila input').value = num2(M.meses.reduce((s, ym) => s + (parseFloat(l.valores[ym]) || 0), 0));
        pintarPie();
    }
    function setRubro(idCuenta, v) { const l = linea(idCuenta); if (l) { l.id_rubro = v ? +v : null; M.dirty = true; } }
    function quitar(idCuenta) { M.lineas = M.lineas.filter(l => l.id_cuenta !== +idCuenta); M.dirty = true; pintarGrilla(); }

    function agregarCuenta(c) {
        if (linea(c.id)) return aviso('info', 'Ya está', `La cuenta ${c.codigo} ya está en el presupuesto.`, $('modalPre'));
        const valores = {}; M.meses.forEach(ym => { valores[ym] = 0; });
        M.lineas.push({ id: 0, id_cuenta: +c.id, cuenta_codigo: c.codigo, cuenta_nombre: c.nombre, es_ingreso: String(c.codigo).startsWith('4'), id_rubro: null, valores });
        M.dirty = true; pintarGrilla();
        const inp = $('pre-grilla-body').querySelector(`tr[data-cuenta="${c.id}"] input.pre-monto`); inp?.focus();
    }

    function initBuscadorCuenta() {
        const input = $('pre-buscar-cuenta'), drop = $('pre-dropdown-cuenta'); let timer = null;
        input.addEventListener('input', () => {
            clearTimeout(timer); const q = input.value.trim();
            if (q.length < 1) { drop.classList.add('d-none'); return; }
            timer = setTimeout(async () => {
                const res = await pedir(`${CFG.url}/buscarCuentasAjax?q=${encodeURIComponent(q)}`);
                drop.innerHTML = (res.data || []).length ? res.data.map(c => `<button type="button" class="list-group-item list-group-item-action small py-1" data-c='${esc(JSON.stringify(c))}'>
                    <b>${esc(c.codigo)}</b> ${esc(c.nombre)} ${c.es_grupo && c.es_grupo !== 'f' ? '<span class="badge bg-info bg-opacity-10 text-info ms-1">grupo</span>' : ''}</button>`).join('')
                    : '<div class="list-group-item small text-muted">Sin resultados (solo cuentas 4, 5 y 6).</div>';
                drop.classList.remove('d-none');
            }, 250);
        });
        drop.addEventListener('click', e => {
            const b = e.target.closest('[data-c]'); if (!b) return;
            agregarCuenta(JSON.parse(b.dataset.c)); input.value = ''; drop.classList.add('d-none');
        });
        document.addEventListener('click', e => { if (!e.target.closest('#pre-buscar-cuenta') && !e.target.closest('#pre-dropdown-cuenta')) drop.classList.add('d-none'); });
    }

    async function guardarGrilla() {
        if (!M.version || !M.editable) return;
        const btn = $('pre-btn-guardar-grilla'); btn.disabled = true;
        try {
            const lineas = M.lineas.map(l => ({ id: l.id, id_cuenta: l.id_cuenta, id_rubro: l.id_rubro, valores: l.valores }));
            const res = await post('guardarLineasAjax', { id_version: M.version.id, lineas: JSON.stringify(lineas) });
            if (!res.ok) return errorForm(res.mensaje);
            M.dirty = false;
            await abrir(M.id, M.version.id); cargarListado();
            Swal.fire({ icon: 'success', title: 'Listo', text: res.mensaje, timer: 1800, showConfirmButton: false, target: $('modalPre') });
        } catch (e) { aviso('error', 'No se pudo guardar', 'Error de comunicación con el servidor.', $('modalPre')); }
        finally { btn.disabled = false; }
    }

    // ── Herramientas ──
    let origenModo = 'copiar';
    async function abrirCopiar() {
        origenModo = 'copiar';
        $('pre-origen-titulo').textContent = 'Copiar de otro presupuesto';
        $('pre-origen-copiar').classList.remove('d-none'); $('pre-origen-ejecutado').classList.add('d-none');
        const sel = $('pre_origen_version'); sel.innerHTML = '<option>Cargando…</option>';
        const res = await pedir(`${CFG.url}/listaParaCopiarAjax`);
        const opts = (res.data || []).filter(o => +o.id_version !== +(M.version?.id || 0));
        sel.innerHTML = opts.length ? opts.map(o => `<option value="${o.id_version}">${esc(o.label)}</option>`).join('') : '<option value="">No hay otros presupuestos</option>';
        bootstrap.Modal.getOrCreateInstance($('modalPreOrigen')).show();
    }
    function abrirDesdeEjecutado() {
        origenModo = 'ejecutado';
        $('pre-origen-titulo').textContent = 'Partir de lo ejecutado';
        $('pre-origen-copiar').classList.add('d-none'); $('pre-origen-ejecutado').classList.remove('d-none');
        const d = M.meses[0], h = M.meses[M.meses.length - 1];
        const resta = ym => { const [y, m] = ym.split('-').map(Number); return `${y - 1}-${String(m).padStart(2, '0')}`; };
        $('pre_ref_desde').value = resta(d); $('pre_ref_hasta').value = resta(h);
        bootstrap.Modal.getOrCreateInstance($('modalPreOrigen')).show();
    }
    async function aplicarOrigen() {
        const pct = $('pre_origen_pct').value || 0;
        const url = origenModo === 'copiar'
            ? `${CFG.url}/copiarDeAjax?${new URLSearchParams({ id: M.id, id_version_origen: $('pre_origen_version').value, pct })}`
            : `${CFG.url}/desdeEjecutadoAjax?${new URLSearchParams({ id: M.id, desde: $('pre_ref_desde').value, hasta: $('pre_ref_hasta').value, pct })}`;
        const res = await pedir(url);
        if (!res.ok) return aviso('error', 'No se pudo', res.mensaje, $('modalPreOrigen'));
        if (!res.lineas.length) return aviso('info', 'Sin datos', 'No se encontraron cuentas con valores para traer.', $('modalPreOrigen'));
        M.lineas = res.lineas.map(l => ({ id: 0, id_cuenta: +l.id_cuenta, cuenta_codigo: l.cuenta_codigo, cuenta_nombre: l.cuenta_nombre, es_ingreso: !!l.es_ingreso, id_rubro: l.id_rubro ? +l.id_rubro : null, valores: l.valores }));
        M.dirty = true; pintarGrilla();
        bootstrap.Modal.getInstance($('modalPreOrigen'))?.hide();
        $('pre-grilla-aviso').innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>Se trajeron ${res.lineas.length} cuenta(s). Revise y pulse <b>Guardar presupuesto</b> para grabar.`;
        $('pre-grilla-aviso').classList.remove('d-none');
    }
    function plantilla() { if (M.id) CMG_descargar(`${CFG.url}/plantillaExcel?id=${M.id}`); }
    async function importar(input) {
        const f = input.files[0]; input.value = ''; if (!f) return;
        const fd = new FormData(); fd.append('archivo', f); fd.append('id', M.id);
        const res = await pedir(`${CFG.url}/importarExcelAjax`, { method: 'POST', body: fd });
        if (!res.ok) return aviso('error', 'No se pudo cargar', res.mensaje, $('modalPre'));
        // Rubros nuevos del archivo: se crean al vuelo.
        for (const l of res.lineas) {
            if (l.rubro_nuevo && l.rubro_nombre) {
                const r = await post('rubrosAjax', { accion: 'crear', nombre: l.rubro_nombre });
                if (r.ok) { M.rubros = r.rubros; l.id_rubro = r.id; }
            }
        }
        M.lineas = res.lineas.map(l => ({ id: 0, id_cuenta: +l.id_cuenta, cuenta_codigo: l.cuenta_codigo, cuenta_nombre: l.cuenta_nombre, es_ingreso: !!l.es_ingreso, id_rubro: l.id_rubro ? +l.id_rubro : null, valores: l.valores }));
        M.dirty = true; pintarGrilla();
        const av = $('pre-grilla-aviso');
        av.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>Se leyeron ${res.lineas.length} cuenta(s). Revise y pulse <b>Guardar presupuesto</b>.`
            + (res.errores.length ? `<br><b>Filas omitidas:</b><ul class="mb-0">${res.errores.map(e => `<li>${esc(e)}</li>`).join('')}</ul>` : '');
        av.classList.remove('d-none');
    }

    async function gestionarRubros() {
        const lista = M.rubros.map(r => `<div class="d-flex align-items-center gap-2 mb-1"><input class="form-control form-control-sm" value="${esc(r.nombre)}" data-rid="${r.id}">
            <button type="button" class="btn btn-outline-danger btn-sm" data-del="${r.id}" title="Eliminar"><i class="bi bi-trash"></i></button></div>`).join('');
        const { value, isConfirmed } = await Swal.fire({
            title: 'Rubros', target: $('modalPre'),
            html: `<div class="text-start small">${lista || '<p class="text-muted">Aún no hay rubros.</p>'}<hr class="my-2"><input class="form-control form-control-sm" id="pre-rubro-nuevo" placeholder="Nuevo rubro (Enter o Guardar)"></div>`,
            showCancelButton: true, confirmButtonText: 'Guardar', cancelButtonText: 'Cerrar',
            didOpen: (el) => {
                el.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', async () => {
                    const r = await post('rubrosAjax', { accion: 'eliminar', id: b.dataset.del });
                    if (r.ok) { M.rubros = r.rubros; Swal.close(); gestionarRubros(); }
                }));
            },
            preConfirm: () => ({
                renombrar: [...Swal.getHtmlContainer().querySelectorAll('[data-rid]')].map(i => ({ id: i.dataset.rid, nombre: i.value })),
                nuevo: Swal.getHtmlContainer().querySelector('#pre-rubro-nuevo').value.trim(),
            }),
        });
        if (!isConfirmed || !value) return;
        for (const r of value.renombrar) {
            const actual = M.rubros.find(x => +x.id === +r.id);
            if (actual && actual.nombre !== r.nombre && r.nombre.trim()) await post('rubrosAjax', { accion: 'renombrar', id: r.id, nombre: r.nombre });
        }
        if (value.nuevo) await post('rubrosAjax', { accion: 'crear', nombre: value.nuevo });
        const res = await post('rubrosAjax', { accion: 'listar' });
        if (res.ok) { M.rubros = res.rubros; pintarGrilla(); }
    }

    // ═══════════════════════ VERSIONES ═══════════════════════
    function pintarVersiones() {
        const tb = $('pre-versiones-body');
        tb.innerHTML = M.versiones.length ? M.versiones.map(v => {
            const cls = ESTADO_CLASE[v.estado] || 'secondary';
            const actual = M.version && +M.version.id === +v.id;
            return `<tr class="${actual ? 'table-active' : ''}">
                <td class="ps-2 fw-medium">${esc(v.nombre)}</td>
                <td><span class="badge bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25">${esc(v.estado)}</span></td>
                <td class="text-truncate" style="max-width:220px" title="${esc(v.motivo || '')}">${esc(v.motivo || '')}</td>
                <td>${v.aprobado_at ? fecha(v.aprobado_at) + (v.aprobado_por_nombre ? ' · ' + esc(v.aprobado_por_nombre) : '') : '—'}</td>
                <td>${esc(v.acta || '—')}</td>
                <td class="text-end">${money(v.ingresos)}</td><td class="text-end">${money(v.gastos)}</td>
                <td class="pe-2 text-end">${actual ? '<span class="small text-muted">mostrando</span>' : `<button type="button" class="btn btn-outline-primary btn-sm py-0" onclick="PRE.abrir(${M.id}, ${v.id})">Ver</button>`}</td>
            </tr>`;
        }).join('') : '<tr><td colspan="8" class="text-center text-muted py-3">—</td></tr>';
    }
    function abrirAprobar() { $('pre_acta').value = ''; $('pre_obs_aprobacion').value = ''; bootstrap.Modal.getOrCreateInstance($('modalPreAprobar')).show(); }
    async function aprobar() {
        if (M.dirty) return aviso('warning', 'Guarde primero', 'Hay cambios en la grilla sin guardar.', $('modalPreAprobar'));
        const res = await post('aprobarAjax', { id_version: M.version.id, acta: $('pre_acta').value, observacion: $('pre_obs_aprobacion').value });
        if (!res.ok) return aviso('error', 'No se pudo aprobar', res.mensaje.split('|')[0], $('modalPreAprobar'));
        bootstrap.Modal.getInstance($('modalPreAprobar'))?.hide();
        await abrir(M.id); cargarListado();
        aviso('success', 'Aprobada', res.mensaje, $('modalPre'));
    }
    function abrirReforma() { $('pre_motivo').value = ''; bootstrap.Modal.getOrCreateInstance($('modalPreReforma')).show(); }
    async function crearReforma() {
        const res = await post('reformaAjax', { id: M.id, motivo: $('pre_motivo').value });
        if (!res.ok) return aviso('error', 'No se pudo', res.mensaje.split('|')[0], $('modalPreReforma'));
        bootstrap.Modal.getInstance($('modalPreReforma'))?.hide();
        await abrir(M.id, res.id_version); bootstrap.Tab.getOrCreateInstance($('pre-tab-grilla-btn')).show();
    }
    async function descartarReforma() {
        const c = await Swal.fire({ icon: 'warning', title: '¿Descartar la reforma?', text: 'Se pierden los cambios de esta versión en borrador; la vigente no se toca.', showCancelButton: true, confirmButtonText: 'Sí, descartar', cancelButtonText: 'Cancelar', target: $('modalPre') });
        if (!c.isConfirmed) return;
        const res = await post('eliminarReformaAjax', { id_version: M.version.id });
        if (!res.ok) return aviso('error', 'No se pudo', res.mensaje, $('modalPre'));
        await abrir(M.id); cargarListado();
    }

    // ═══════════════════════ EJECUCIÓN ═══════════════════════
    async function cargarEjecucion() {
        if (!M.id) return;
        const idVer = M.version && M.version.estado !== 'borrador' ? M.version.id : '';
        const res = await pedir(`${CFG.url}/ejecucionAjax?${new URLSearchParams({ id: M.id, id_version: idVer })}`);
        if (!res.ok) {
            $('pre-ejec-vacio').innerHTML = `<i class="bi bi-speedometer2 fs-3 d-block mb-2"></i>${esc(res.mensaje)}`;
            $('pre-ejec-vacio').classList.remove('d-none'); $('pre-ejec-wrap').classList.add('d-none'); return;
        }
        M.ejec = res; $('pre-ejec-vacio').classList.add('d-none'); $('pre-ejec-wrap').classList.remove('d-none');
        const k = (et, t, color) => `<div class="col-6 col-md-3"><div class="card bg-light border-0 text-center p-2"><span class="small text-muted d-block" style="font-size:.7rem">${et}</span>
            <h6 class="mb-0 fw-bold text-${color}">${money(t.ejecutado)} <span class="text-muted fw-normal small">/ ${money(t.presupuesto)}</span></h6>
            <span class="pre-pct ${t.semaforo}">${t.pct === null ? '—' : t.pct + ' %'}</span></div></div>`;
        $('pre-ejec-kpis').innerHTML = k('INGRESOS: ejecutado / presupuesto', res.totales.ingresos, 'success') + k('COSTOS Y GASTOS: ejecutado / presupuesto', res.totales.gastos, 'danger')
            + `<div class="col-12 col-md-6"><div class="card bg-light border-0 p-2 small"><b>${esc(res.version.nombre)}</b> (${esc(res.version.estado)}) · ${esc(alcanceTexto(res.cabecera))}<br>
               Semáforo gastos: <span class="pre-pct verde">&lt; ${res.cabecera.umbral_amarillo} %</span> <span class="pre-pct amarillo">≥ ${res.cabecera.umbral_amarillo} %</span> <span class="pre-pct rojo">≥ ${res.cabecera.umbral_rojo} %</span>. En ingresos se invierte: rojo si no se llega.</div></div>`;
        pintarEjecucion();
    }
    function ejecVista(v) {
        M.ejecVista = v;
        $('pre-ejec-btn-cuentas').className = 'btn ' + (v === 'cuentas' ? 'btn-primary' : 'btn-outline-primary');
        $('pre-ejec-btn-rubros').className = 'btn ' + (v === 'rubros' ? 'btn-primary' : 'btn-outline-primary');
        pintarEjecucion();
    }
    function pintarEjecucion() {
        const e = M.ejec; if (!e) return;
        const t = $('pre-ejec-tabla');
        const pct = (x) => `<span class="pre-pct ${x.semaforo}">${x.pct === null ? '—' : x.pct + ' %'}</span>`;
        if (M.ejecVista === 'rubros') {
            let html = `<thead class="table-light"><tr><th class="ps-2">Rubro</th><th class="text-end">Presupuesto</th><th class="text-end">Ejecutado</th><th class="text-end">Diferencia</th><th class="text-end pe-2">% Ejec.</th></tr></thead><tbody>`;
            for (const [clase, et] of [['ingresos', 'Ingresos'], ['gastos', 'Costos y gastos']]) {
                const rs = e.rubros[clase] || {};
                html += `<tr class="pre-rubro-fila"><td class="ps-2" colspan="5">${et}</td></tr>`;
                html += Object.entries(rs).map(([n, r]) => `<tr><td class="ps-3">${esc(n)}</td><td class="text-end">${money(r.presupuesto)}</td><td class="text-end">${money(r.ejecutado)}</td><td class="text-end">${money(r.diferencia)}</td><td class="text-end pe-2">${pct(r)}</td></tr>`).join('');
                const tt = e.totales[clase];
                html += `<tr class="fw-bold"><td class="ps-2">Total ${et.toLowerCase()}</td><td class="text-end">${money(tt.presupuesto)}</td><td class="text-end">${money(tt.ejecutado)}</td><td class="text-end">${money(tt.diferencia)}</td><td class="text-end pe-2">${pct(tt)}</td></tr>`;
            }
            t.innerHTML = html + '</tbody>';
            return;
        }
        let html = `<thead class="table-light"><tr><th class="ps-2">Cuenta</th>${e.meses.map(ym => `<th class="text-end" colspan="2">${mesCorto(ym)}${ym === e.mes_actual ? ' ●' : ''}</th>`).join('')}<th class="text-end">Presup.</th><th class="text-end">Ejec.</th><th class="text-end pe-2">%</th></tr>
            <tr class="small text-muted"><th></th>${e.meses.map(() => '<th class="text-end">Pres.</th><th class="text-end">Ejec.</th>').join('')}<th></th><th></th><th></th></tr></thead><tbody>`;
        let rubroAct = null;
        for (const f of e.filas) {
            if (f.rubro !== rubroAct) { rubroAct = f.rubro; html += `<tr class="pre-rubro-fila"><td class="ps-2" colspan="${e.meses.length * 2 + 4}">${esc(f.rubro)}</td></tr>`; }
            html += `<tr><td class="ps-3" title="${esc(f.cuenta_codigo + ' ' + f.cuenta_nombre)}"><b>${esc(f.cuenta_codigo)}</b> <span class="text-muted">${esc(f.cuenta_nombre)}</span></td>`
                + e.meses.map(ym => { const m = f.meses[ym]; return `<td class="text-end text-muted">${m.presupuesto ? money(m.presupuesto) : ''}</td>
                    <td class="text-end ${m.ejecutado ? 'pre-click' : ''} pre-pct ${m.semaforo}" ${m.ejecutado ? `onclick="PRE.verAsientos(${f.id_cuenta}, '${ym}', '${esc(f.cuenta_codigo)}')"` : ''}>${m.ejecutado ? money(m.ejecutado) : ''}</td>`; }).join('')
                + `<td class="text-end">${money(f.presupuesto)}</td><td class="text-end">${money(f.ejecutado)}</td><td class="text-end pe-2">${pct(f)}</td></tr>`;
        }
        for (const [clase, et] of [['ingresos', 'Total ingresos'], ['gastos', 'Total costos y gastos']]) {
            const tt = e.totales[clase], pm = e.por_mes[clase] || {};
            html += `<tr class="fw-bold"><td class="ps-2">${et}</td>${e.meses.map(ym => `<td class="text-end">${money(pm[ym]?.presupuesto || 0)}</td><td class="text-end">${money(pm[ym]?.ejecutado || 0)}</td>`).join('')}
                <td class="text-end">${money(tt.presupuesto)}</td><td class="text-end">${money(tt.ejecutado)}</td><td class="text-end pe-2">${pct(tt)}</td></tr>`;
        }
        t.innerHTML = html + '</tbody>';
    }
    async function verAsientos(idCuenta, ym, codigo) {
        $('pre-asientos-titulo').textContent = `Asientos · ${codigo} · ${mesCorto(ym)}`;
        $('pre-asientos-body').innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Cargando…</td></tr>';
        bootstrap.Modal.getOrCreateInstance($('modalPreAsientos')).show();
        const res = await pedir(`${CFG.url}/asientosEjecucionAjax?${new URLSearchParams({ id: M.id, id_cuenta: idCuenta, periodo: ym })}`);
        $('pre-asientos-body').innerHTML = res.ok && res.data.length ? res.data.map(a => `<tr><td class="ps-2">${fecha(a.fecha_asiento)}</td><td>${esc(a.numero_comprobante)}</td>
            <td class="text-truncate" style="max-width:260px" title="${esc(a.concepto)}">${esc(a.concepto)}</td><td><small>${esc(a.modulo_origen)}</small></td>
            <td><small>${esc(a.cuenta_codigo)}</small></td><td class="text-end pe-2">${money(a.monto)}</td></tr>`).join('')
            : `<tr><td colspan="6" class="text-center text-muted py-3">${esc(res.mensaje || 'Sin asientos.')}</td></tr>`;
    }

    // ═══════════════════════ PDF / Excel del presupuesto ═══════════════════════
    function pdf() { if (M.id) CMG_pdfDocumento(`${CFG.url}/pdfPresupuesto?id=${M.id}&id_version=${M.version?.id || ''}`); }
    function excel() { if (M.id) CMG_descargar(`${CFG.url}/excelPresupuesto?id=${M.id}&id_version=${M.version?.id || ''}`); }

    // ═══════════════════════ INICIO ═══════════════════════
    document.addEventListener('DOMContentLoaded', () => {
        $('formPre').addEventListener('submit', guardarDatos);
        $('pre-buscar').addEventListener('input', () => { clearTimeout(L.timer); L.timer = setTimeout(() => cargarListado(1), 400); });
        $('pre-paginacion').addEventListener('click', e => { const b = e.target.closest('[data-pag]'); if (b && !b.disabled) cargarListado(L.page + parseInt(b.dataset.pag, 10)); });
        if (window.CMG_initSort) {
            window.CMG_initSort(CFG.modulo, (col, dir, sorts) => { L.sorts = sorts; cargarListado(1); }, { sorts: L.sorts, multi: true });
        }
        initBuscadorCuenta();
        $('pre-tab-ejecucion-btn').addEventListener('shown.bs.tab', cargarEjecucion);
        $('modalPre').addEventListener('hide.bs.modal', e => {
            if (M.dirty && !confirm('Hay cambios en la grilla sin guardar. ¿Cerrar de todos modos?')) e.preventDefault();
        });
        cargarListado(L.page);
    });

    return { nuevo, abrir, eliminar, cambiarEstado, onTipoPeriodo, onAlcance, guardarGrilla, setMonto, repartirTotal, setRubro, quitar,
             abrirCopiar, abrirDesdeEjecutado, aplicarOrigen, plantilla, importar, gestionarRubros,
             abrirAprobar, aprobar, abrirReforma, crearReforma, descartarReforma, ejecVista, verAsientos, pdf, excel };
})();
