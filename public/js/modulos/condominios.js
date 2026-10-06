/**
 * condominios.js — Módulo Condominios (fase 1: inmuebles y carga Excel).
 *
 * Listado de inmuebles (FiltrosModal + orden multi-columna sin recarga + paginación), modal de
 * inmueble (General / Propietarios / Áreas comunes / Expensa) y carga por Excel con vista previa.
 * La configuración del condominio y las multas viven en condominios_config.js.
 * Configuración en window.COND_CFG (url, permisos, orden inicial, catálogos, filtros).
 */
window.COND = (function () {
    'use strict';

    const CFG = window.COND_CFG || {};
    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = n => { const v = parseFloat(n) || 0; return (v < 0 ? '-$' : '$') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    const num = (n, d) => (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
    const fecha = iso => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || ''); return m ? `${m[3]}-${m[2]}-${m[1]}` : (iso || ''); };
    const esTrue = v => v === true || v === 't' || v === 'true' || v === 1 || v === '1';
    const sel = el => { if (!el) return; el.focus(); el.classList.add('is-invalid'); setTimeout(() => el.classList.remove('is-invalid'), 2500); };

    async function pedir(url, opciones) {
        const r = await fetch(url, Object.assign({ headers: { 'X-Requested-With': 'XMLHttpRequest' } }, opciones || {}));
        return r.json();
    }
    function post(accion, datos) {
        const fd = datos instanceof FormData ? datos : new FormData();
        if (!(datos instanceof FormData)) Object.entries(datos || {}).forEach(([k, v]) => fd.append(k, v ?? ''));
        return pedir(`${CFG.url}/${accion}`, { method: 'POST', body: fd });
    }
    function aviso(icon, title, text, target) { return Swal.fire({ icon, title, text, target: target || document.body }); }
    /** Mensajes del servidor «texto|#id»: enfoca el control. */
    function errorForm(mensaje, modalEl) {
        const [txt, s] = String(mensaje || '').split('|');
        aviso('error', 'Revise', txt, modalEl);
        if (s) sel(document.querySelector(s));
    }

    // ═══════════════════════ Buscadores tipo chip ═══════════════════════
    /** Input de búsqueda que fija un id oculto; Backspace/Delete limpian toda la selección. */
    function chip(inputId, hiddenId, ddId, fetchFn, label, onPick) {
        const input = $(inputId), hidden = $(hiddenId), dd = $(ddId);
        if (!input || !hidden || !dd) return;
        let t;
        input.addEventListener('keydown', e => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && hidden.value !== '') {
                e.preventDefault(); hidden.value = ''; input.value = ''; dd.style.display = 'none'; dd.innerHTML = '';
                if (onPick) onPick(null);
            }
        });
        input.addEventListener('input', () => {
            hidden.value = ''; clearTimeout(t);
            const q = input.value.trim();
            if (q.length < 1) { dd.style.display = 'none'; dd.innerHTML = ''; return; }
            t = setTimeout(async () => {
                let items = [];
                try { items = await fetchFn(q); } catch (e) { items = []; }
                if (!items.length) { dd.innerHTML = '<span class="list-group-item text-muted small py-1 px-2">Sin resultados</span>'; dd.style.display = 'block'; return; }
                dd.innerHTML = items.map(it => `<a href="#" class="list-group-item list-group-item-action py-1 px-2 small" data-id="${it.id}" data-label="${esc(label(it))}">${esc(label(it))}</a>`).join('');
                dd.style.display = 'block';
                dd._items = items;
            }, 300);
        });
        dd.addEventListener('click', e => {
            const a = e.target.closest('a[data-id]'); if (!a) return;
            e.preventDefault();
            hidden.value = a.dataset.id; input.value = a.dataset.label; dd.style.display = 'none';
            if (onPick) onPick((dd._items || []).find(i => String(i.id) === a.dataset.id) || null);
        });
        document.addEventListener('click', e => { if (e.target !== input && !dd.contains(e.target)) dd.style.display = 'none'; });
    }
    const buscarClientes = async q => (await pedir(`${CFG.url}/buscarClientesAjax?q=${encodeURIComponent(q)}`)).rows || [];
    const buscarServicios = async q => (await pedir(`${CFG.url}/buscarServiciosAjax?q=${encodeURIComponent(q)}`)).rows || [];
    const lblCliente = c => `${c.nombre}${c.identificacion ? ' (' + c.identificacion + ')' : ''}`;
    const lblServicio = p => `${p.codigo ? p.codigo + ' - ' : ''}${p.nombre}`;
    function setChip(inputId, hiddenId, id, label) { $(hiddenId).value = id || ''; $(inputId).value = id ? (label || '') : ''; }

    // ═══════════════════════ LISTADO ═══════════════════════
    const L = { page: CFG.page || 1, sorts: CFG.sorts || [], fm: null };

    async function cargarListado(page) {
        L.page = page || L.page || 1;
        const tbody = $('cond-tbody');
        tbody.classList.add('fm-cargando-target');
        const q = new URLSearchParams({ b: $('cond-buscar').value, page: L.page, orden: (window.CMG_ordenParam ? window.CMG_ordenParam(L.sorts) : '') });
        try {
            const res = await pedir(`${CFG.url}/searchAjax?${q}`);
            if (!res.ok) { tbody.innerHTML = `<tr><td colspan="13" class="text-center text-danger py-4">${esc(res.mensaje)}</td></tr>`; return; }
            pintarListado(res);
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="13" class="text-center text-danger py-4">Error de comunicación con el servidor.</td></tr>';
        } finally { tbody.classList.remove('fm-cargando-target'); }
    }

    function pintarListado(res) {
        const tbody = $('cond-tbody');
        if (!res.rows.length) {
            tbody.innerHTML = `<tr><td colspan="13" class="text-center py-5 text-muted"><i class="bi bi-buildings fs-3 d-block mb-2"></i>${CFG.config ? 'No se encontraron inmuebles. Cree la primera con «Nuevo inmueble» o cárguelas desde Excel.' : 'Configure el condominio para empezar.'}</td></tr>`;
        } else {
            tbody.innerHTML = res.rows.map(r => {
                const cls = r.estado === 'activo' ? 'success' : 'secondary';
                return `<tr class="cond-row" data-id="${r.id}" onclick="COND.abrir(${r.id})">
                    <td class="ps-3" data-col="codigo"><code class="text-secondary">${esc(r.codigo)}</code></td>
                    <td class="fw-medium" data-col="nombre">${esc(r.nombre)}</td>
                    <td data-col="tipo">${esc(r.tipo_label)}</td>
                    <td data-col="torre_bloque">${esc(r.torre_bloque || '—')}</td>
                    <td data-col="piso">${esc(r.piso || '—')}</td>
                    <td class="text-end" data-col="area_m2">${num(r.area_m2, 2)}</td>
                    <td class="text-end" data-col="alicuota_pct">${num(r.alicuota_pct, 4)}</td>
                    <td class="text-truncate" style="max-width:220px" data-col="propietario" title="${esc(r.propietario_nombre)}">${esc(r.propietario_nombre || '—')}</td>
                    <td class="text-truncate" style="max-width:200px" data-col="pagador" title="${esc(r.pagador_nombre)}">${esc(r.pagador_nombre || '—')}${r.pagador === 'arrendatario' ? ' <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">arr.</span>' : ''}</td>
                    <td data-col="metodo">${esc(r.metodo_label)}</td>
                    <td class="text-end" data-col="cuota">${r.cuota_estimada === null || r.cuota_estimada === undefined ? '<span class="text-muted" title="Sin valor vigente">—</span>' : money(r.cuota_estimada)}</td>
                    <td class="text-center" data-col="restringida">${r.restringida ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" title="Áreas comunes restringidas"><i class="bi bi-slash-circle"></i></span>' : '<span class="text-muted">—</span>'}</td>
                    <td class="text-center pe-3" data-col="estado"><span class="badge bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25">${r.estado === 'activo' ? 'Activo' : 'Inactivo'}</span></td>
                </tr>`;
            }).join('');
        }
        const from = res.total ? (res.page - 1) * res.per_page + 1 : 0;
        const to = res.total ? Math.min(res.page * res.per_page, res.total) : 0;
        $('cond-pag-info').textContent = `${from}-${to}/${res.total}`;
        const [prev, next] = $('cond-paginacion').querySelectorAll('[data-pag]');
        prev.disabled = res.page <= 1; next.disabled = res.page >= res.total_pages;
        $('cond-btn-pdf').href = res.pdf_url; $('cond-btn-excel').href = res.excel_url;
        if (res.resumen) {
            const s = res.resumen;
            $('cond-resumen').textContent = `${s.activas} activos · Σ ${num(s.suma_pct, 4)} % · ${num(s.suma_m2, 2)} m²` + (+s.restringidas > 0 ? ` · ${s.restringidas} restringida(s)` : '');
        }
        if (L.sorter) L.sorter.refreshIcons();
    }

    // ═══════════════════════ MODAL UNIDAD ═══════════════════════
    const M = { id: 0, u: null, personasIni: '', guardando: false };
    const modalU = () => bootstrap.Modal.getOrCreateInstance($('modalUnidad'));

    function nueva() {
        if (!CFG.config) return aviso('info', 'Configure el condominio', 'Antes de crear inmuebles guarde la Configuración de condominios (menú Condominios).');
        M.id = 0; M.u = null; M.personasIni = '';
        $('formUnidad').reset();
        $('uni_id').value = '';
        ['uni_propietario', 'uni_arrendatario'].forEach(p => setChip(p + '_txt', 'uni_id_' + p.replace('uni_', ''), '', ''));
        $('uni-titulo').textContent = 'Nuevo inmueble';
        $('uni-badge-estado').classList.add('d-none'); $('uni-badge-restr').classList.add('d-none');
        $('uni-wrap-cambio-personas').classList.remove('d-none');
        $('uni_propietario_desde').value = new Date().toISOString().slice(0, 10);
        $('uni-btn-eliminar')?.classList.add('d-none');
        $('uni-registro').textContent = '';
        $('uni-historial-body').innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Guardel inmueble para ver el historial.</td></tr>';
        $('uni-restr-body').innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">—</td></tr>';
        $('uni-susc-actual').textContent = 'Guardel inmueble para ver su expensa.'; $('uni-susc-enlazables').innerHTML = '';
        $('uni-restr-form')?.classList.add('d-none');
        onMetodo(); cuotaEstimada();
        if (typeof window.aplicarFavoritosModal === 'function') window.aplicarFavoritosModal('#modalUnidad');
        bootstrap.Tab.getOrCreateInstance($('uni-tab-general-btn')).show();
        modalU().show();
        setTimeout(() => $('uni_codigo').focus(), 400);
    }

    async function abrir(id) {
        try {
            const res = await pedir(`${CFG.url}/getAjax?id=${id}`);
            if (!res.ok) return aviso('error', 'No se pudo abrir', res.mensaje);
            cargarUnidad(res);
            if (!$('modalUnidad').classList.contains('show')) {
                bootstrap.Tab.getOrCreateInstance($('uni-tab-general-btn')).show();
                modalU().show();
            }
        } catch (e) { aviso('error', 'No se pudo abrir', 'Error de comunicación con el servidor.'); }
    }

    function cargarUnidad(res) {
        const u = res.unidad; M.id = +u.id; M.u = u;
        $('formUnidad').reset();
        $('uni_id').value = u.id;
        ['codigo', 'nombre', 'tipo', 'torre_bloque', 'piso', 'area_m2', 'alicuota_pct', 'pagador', 'metodo_alicuota', 'monto_manual', 'fondo_reserva_valor_propio', 'estado', 'observaciones']
            .forEach(k => { const el = $('uni_' + k); if (el) el.value = u[k] ?? ''; });
        setChip('uni_propietario_txt', 'uni_id_propietario', u.id_propietario, lblCliente({ nombre: u.propietario_nombre, identificacion: u.propietario_identificacion }));
        setChip('uni_arrendatario_txt', 'uni_id_arrendatario', u.id_arrendatario, u.id_arrendatario ? lblCliente({ nombre: u.arrendatario_nombre, identificacion: u.arrendatario_identificacion }) : '');
        M.personasIni = personasClave();
        $('uni-wrap-cambio-personas').classList.add('d-none');
        $('uni_propietario_desde').value = new Date().toISOString().slice(0, 10);
        $('uni-titulo').textContent = `${u.codigo} · ${u.nombre}`;
        const b = $('uni-badge-estado'), cls = u.estado === 'activo' ? 'success' : 'secondary';
        b.className = `badge ms-2 bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25`; b.textContent = u.estado === 'activo' ? 'Activo' : 'Inactivo'; b.classList.remove('d-none');
        $('uni-badge-restr').classList.toggle('d-none', !u.restringida);
        $('uni-btn-eliminar')?.classList.remove('d-none');
        $('uni-registro').innerHTML = u.created_at ? `<i class="bi bi-clock-history me-1"></i>Registrada el ${fecha(u.created_at)}${u.updated_at ? ' · última modificación ' + fecha(u.updated_at) : ''}` : '';
        onMetodo(); cuotaEstimada();

        $('uni-historial-body').innerHTML = (res.historial || []).length ? res.historial.map(h => `<tr>
            <td class="ps-2">${fecha(h.desde)}</td><td>${h.hasta ? fecha(h.hasta) : '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Vigente</span>'}</td>
            <td>${esc(h.propietario_nombre)}</td><td>${esc(h.arrendatario_nombre || '—')}</td><td>${h.pagador === 'arrendatario' ? 'Arrendatario' : 'Propietario'}</td><td class="pe-2 text-muted">${esc(h.observacion || '')}</td></tr>`).join('')
            : '<tr><td colspan="6" class="text-center text-muted py-3">Sin historial.</td></tr>';

        $('uni-restr-texto').innerHTML = u.restringida ? `<span class="text-danger fw-bold">Restringida</span> desde ${fecha(u.restringida_desde)}.<br>${esc(u.restringida_motivo || '')}` : 'Sin restricción.';
        $('uni-restr-form')?.classList.remove('d-none');
        $('uni-btn-restringir')?.classList.toggle('d-none', !!u.restringida);
        $('uni-btn-levantar')?.classList.toggle('d-none', !u.restringida);
        $('uni-restr-body').innerHTML = (res.restricciones || []).length ? res.restricciones.map(l => `<tr><td class="ps-2">${fecha(l.fecha)}</td>
            <td>${l.accion === 'marcar' ? '<span class="text-danger">Marcar</span>' : '<span class="text-success">Quitar</span>'}${esTrue(l.automatico) ? ' <small class="text-muted">(auto)</small>' : ''}</td>
            <td>${esc(l.motivo || '')}</td><td class="pe-2">${esc(l.usuario_nombre || '')}</td></tr>`).join('')
            : '<tr><td colspan="4" class="text-center text-muted py-3">Sin movimientos.</td></tr>';

        pintarExpensa(res);
    }

    function pintarExpensa(res) {
        const u = res.unidad;
        const act = $('uni-susc-actual');
        if (u.id_suscripcion) {
            act.innerHTML = `<i class="bi bi-arrow-repeat me-1"></i>Expensa mensual: <b>suscripción #${u.id_suscripcion}</b> (${esc(u.suscripcion_estado || '')}${u.suscripcion_proximo_cobro ? ', próximo cobro ' + fecha(u.suscripcion_proximo_cobro) : ''}).
                ${CFG.perm.actualizar ? `<button type="button" class="btn btn-link btn-sm p-0 ms-2" onclick="COND.enlazar(0)">Desenlazar</button>` : ''}`;
        } else {
            act.innerHTML = '<i class="bi bi-info-circle me-1"></i>Este inmueble aún no tiene suscripción: el módulo la creará al emitir la primera expensa.';
        }
        const lista = res.suscripciones_enlazables || [];
        $('uni-susc-enlazables').innerHTML = lista.length && CFG.perm.actualizar ? `<div class="small fw-bold mb-1">Suscripciones del pagador sin inmueble (enlazar como expensa):</div>
            <div class="border rounded-3 bg-white"><table class="table table-sm table-hover mb-0 small"><thead class="table-light"><tr><th class="ps-2">#</th><th>Estado</th><th>Periodicidad</th><th>Comprobante</th><th>Próximo cobro</th><th class="text-end">Monto</th><th class="pe-2"></th></tr></thead><tbody>
            ${lista.map(s => `<tr><td class="ps-2">${s.id}</td><td>${esc(s.estado)}</td><td>${esc(s.periodicidad || '')}</td><td>${esc(s.tipo_comprobante || '')}</td><td>${fecha(s.proximo_cobro)}</td><td class="text-end">${money(s.monto)}</td>
            <td class="text-end pe-2"><button type="button" class="btn btn-outline-primary btn-sm py-0" onclick="COND.enlazar(${s.id})"><i class="bi bi-link-45deg"></i> Enlazar</button></td></tr>`).join('')}</tbody></table></div>` : '';
    }

    function personasClave() { return [$('uni_id_propietario').value, $('uni_id_arrendatario').value, $('uni_pagador').value].join('|'); }
    function onPersonas() { if (M.id) $('uni-wrap-cambio-personas').classList.toggle('d-none', personasClave() === M.personasIni); }

    function onMetodo() {
        const m = $('uni_metodo_alicuota').value || (CFG.config && CFG.config.metodo_alicuota) || 'porcentaje';
        $('uni_monto_manual').disabled = m !== 'manual';
        if (m !== 'manual') $('uni_monto_manual').placeholder = 'Solo si es manual'; else $('uni_monto_manual').placeholder = '0.00';
        cuotaEstimada();
    }
    /** Solo el método manual se puede estimar en pantalla; % y m² dependen del valor vigente (servidor). */
    function cuotaEstimada() {
        const m = $('uni_metodo_alicuota').value || (CFG.config && CFG.config.metodo_alicuota) || 'porcentaje';
        const el = $('uni-cuota-estimada');
        if (m === 'manual') { el.textContent = money($('uni_monto_manual').value) + ' (manual)'; return; }
        if (M.u && M.u.cuota_estimada !== undefined && M.u.cuota_estimada !== null) { el.textContent = money(M.u.cuota_estimada); return; }
        el.textContent = m === 'm2' ? `Tarifa por m² vigente × ${num($('uni_area_m2').value, 2)} m² (se calcula al emitir)` : `Monto a repartir vigente × ${num($('uni_alicuota_pct').value, 4)} % (se calcula al emitir)`;
    }

    async function guardar(e) {
        e?.preventDefault();
        if (M.guardando) return;
        M.guardando = true;
        const btn = $('uni-btn-guardar'); if (btn) btn.disabled = true;
        try {
            const fd = new FormData($('formUnidad'));
            fd.set('monto_manual', $('uni_monto_manual').value); // disabled no viaja
            const res = await post(M.id ? 'updateAjax' : 'storeAjax', fd);
            if (!res.ok) return errorForm(res.mensaje, $('modalUnidad'));
            M.id = res.id || M.id;
            $('uni_id').value = M.id;
            await abrir(M.id);
            cargarListado();
            Swal.fire({ icon: 'success', title: 'Listo', text: res.mensaje, timer: 1600, showConfirmButton: false, target: $('modalUnidad') });
        } catch (err) { aviso('error', 'No se pudo guardar', 'Error de comunicación con el servidor.', $('modalUnidad')); }
        finally { M.guardando = false; if (btn) btn.disabled = false; }
    }

    async function eliminar() {
        const c = await Swal.fire({ icon: 'warning', title: '¿Eliminar este inmueble?', text: 'Solo se eliminan inmuebles sin expensas emitidas. Si ya emitió, márquela como inactiva.', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', target: $('modalUnidad') });
        if (!c.isConfirmed) return;
        const res = await post('eliminarAjax', { id: M.id });
        if (!res.ok) return aviso('error', 'No se pudo', res.mensaje, $('modalUnidad'));
        modalU().hide(); cargarListado();
        aviso('success', 'Listo', res.mensaje);
    }

    async function restringir(marcar) {
        if (!M.id) return;
        const res = await post('restringirAjax', { id: M.id, restringir: marcar ? '1' : '0', fecha: $('restr_fecha').value, motivo: $('restr_motivo').value });
        if (!res.ok) return errorForm(res.mensaje, $('modalUnidad'));
        $('restr_motivo').value = '';
        await abrir(M.id); cargarListado();
        Swal.fire({ icon: 'success', title: 'Listo', text: res.mensaje, timer: 1600, showConfirmButton: false, target: $('modalUnidad') });
    }

    async function enlazar(idSuscripcion) {
        if (!M.id) return;
        if (idSuscripcion) {
            const c = await Swal.fire({ icon: 'question', title: `¿Enlazar la suscripción #${idSuscripcion}?`, text: 'Pasará a ser la expensa mensual de este inmueble y la administrará Condominios.', showCancelButton: true, confirmButtonText: 'Enlazar', cancelButtonText: 'Cancelar', target: $('modalUnidad') });
            if (!c.isConfirmed) return;
        }
        const res = await post('enlazarSuscripcionAjax', { id: M.id, id_suscripcion: idSuscripcion });
        if (!res.ok) return aviso('error', 'No se pudo', res.mensaje, $('modalUnidad'));
        await abrir(M.id);
    }

    // ═══════════════════════ EXCEL ═══════════════════════
    const X = { filas: [] };
    const modalX = () => bootstrap.Modal.getOrCreateInstance($('modalCondExcel'));
    function abrirExcel() {
        X.filas = [];
        $('excel_archivo').value = ''; $('excel-resultado').classList.add('d-none'); $('excel-btn-aplicar').classList.add('d-none'); $('excel-resumen').textContent = '';
        modalX().show();
    }
    async function excelLeer() {
        const f = $('excel_archivo').files[0];
        if (!f) return aviso('info', 'Elija el archivo', 'Seleccione el Excel con los inmuebles.', $('modalCondExcel'));
        const btn = $('excel-btn-leer'); btn.disabled = true;
        try {
            const fd = new FormData(); fd.append('archivo', f);
            const res = await post('importarExcelAjax', fd);
            if (!res.ok) return aviso('error', 'No se pudo leer', res.mensaje, $('modalCondExcel'));
            X.filas = res.filas || [];
            $('excel-avisos').innerHTML = (res.avisos || []).map(a => `<div class="alert alert-warning py-1 px-2 small mb-2"><i class="bi bi-exclamation-triangle me-1"></i>${esc(a)}</div>`).join('');
            $('excel-body').innerHTML = X.filas.length ? X.filas.map(r => `<tr>
                <td class="ps-2 text-muted">${r.fila}</td><td><span class="badge bg-${r.accion === 'crear' ? 'success' : 'primary'} bg-opacity-10 text-${r.accion === 'crear' ? 'success' : 'primary'} border border-opacity-25">${r.accion}</span></td>
                <td><code>${esc(r.datos.codigo)}</code></td><td>${esc(r.datos.nombre || r.datos.codigo)}</td><td>${esc(CFG.tipos[r.datos.tipo] || r.datos.tipo)}</td>
                <td class="text-end">${num(r.datos.area_m2, 2)}</td><td class="text-end">${num(r.datos.alicuota_pct, 4)}</td>
                <td>${esc(r.propietario.nombre)}${r.propietario.crear ? ' <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" title="Se creará como cliente">nuevo</span>' : ''}</td>
                <td>${r.arrendatario ? esc(r.arrendatario.nombre) + (r.arrendatario.crear ? ' <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">nuevo</span>' : '') : '—'}</td>
                <td>${esc(r.datos.pagador)}</td><td>${esc(CFG.metodos[r.datos.metodo_alicuota] || 'Del condominio')}</td><td class="text-end pe-2">${r.datos.monto_manual ? money(r.datos.monto_manual) : ''}</td></tr>`).join('')
                : '<tr><td colspan="12" class="text-center text-muted py-3">Ninguna fila válida.</td></tr>';
            $('excel-errores').innerHTML = (res.errores || []).length ? `<div class="alert alert-danger py-2 px-2 small mb-0"><b>${res.errores.length} fila(s) con error (no se cargarán):</b><ul class="mb-0 ps-3">${res.errores.map(e => `<li>${esc(e)}</li>`).join('')}</ul></div>` : '';
            $('excel-resultado').classList.remove('d-none');
            $('excel-resumen').textContent = `${res.total} fila(s) válidas · ${res.crear} nuevas · ${res.total - res.crear} a actualizar · ${res.clientes_nuevos} cliente(s) nuevo(s)`;
            $('excel-btn-aplicar').classList.toggle('d-none', !X.filas.length);
        } catch (e) { aviso('error', 'No se pudo leer', 'Error de comunicación con el servidor.', $('modalCondExcel')); }
        finally { btn.disabled = false; }
    }
    async function excelAplicar() {
        if (!X.filas.length) return;
        const c = await Swal.fire({ icon: 'question', title: `¿Cargar ${X.filas.length} unidad(es)?`, text: 'Las existentes se actualizan y los clientes que falten se crean.', showCancelButton: true, confirmButtonText: 'Sí, aplicar', cancelButtonText: 'Cancelar', target: $('modalCondExcel') });
        if (!c.isConfirmed) return;
        const btn = $('excel-btn-aplicar'); btn.disabled = true;
        try {
            const res = await post('aplicarExcelAjax', { filas: JSON.stringify(X.filas) });
            if (!res.ok) return aviso('error', 'No se pudo cargar', res.mensaje, $('modalCondExcel'));
            modalX().hide(); cargarListado(1);
            aviso('success', 'Listo', res.mensaje);
        } catch (e) { aviso('error', 'No se pudo cargar', 'Error de comunicación con el servidor.', $('modalCondExcel')); }
        finally { btn.disabled = false; }
    }

    // ═══════════════════════ INICIO ═══════════════════════
    document.addEventListener('DOMContentLoaded', () => {
        if (window.FiltrosModal) {
            L.fm = new FiltrosModal({
                containerId: 'fmBuscadorCOND', hiddenInputId: 'cond-buscar', placeholder: 'Buscar en todas las columnas...', titulo: 'Filtros de inmuebles',
                inputWidth: 420, extraId: 'fmExtraCOND', fields: CFG.filtros || [], loadingTarget: '#cond-tbody',
                onApply: () => cargarListado(1),
            });
            L.fm.init();
        }
        $('cond-paginacion').addEventListener('click', e => { const b = e.target.closest('[data-pag]'); if (b && !b.disabled) cargarListado(L.page + parseInt(b.dataset.pag, 10)); });
        if (window.CMG_initSort) {
            L.sorter = window.CMG_initSort(CFG.modulo, (col, dir, sorts) => { L.sorts = sorts; cargarListado(1); }, { sorts: L.sorts, multi: true, container: '.condominios-scroll', reload: false });
        }
        $('formUnidad')?.addEventListener('submit', guardar);
        chip('uni_propietario_txt', 'uni_id_propietario', 'uni_propietario_dd', buscarClientes, lblCliente, onPersonas);
        chip('uni_arrendatario_txt', 'uni_id_arrendatario', 'uni_arrendatario_dd', buscarClientes, lblCliente, onPersonas);
        $('uni_pagador')?.addEventListener('change', onPersonas);
        ['uni_monto_manual', 'uni_area_m2', 'uni_alicuota_pct'].forEach(id => $(id)?.addEventListener('input', cuotaEstimada));
        cargarListado(L.page);
    });

    return { nueva, abrir, eliminar, restringir, enlazar, onMetodo, abrirExcel, excelLeer, excelAplicar };
})();
