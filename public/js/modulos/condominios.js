/**
 * condominios.js — Pestaña «Condóminos» de Configuración de condominios (modulos/condominios-config).
 *
 * Listado de TODOS los clientes con sus inmuebles (FiltrosModal + orden multi-columna sin recarga +
 * paginación), modal del inmueble (General / Propietarios / Áreas comunes / Expensa) para asignarlo
 * o editarlo, y carga por Excel con vista previa. El resto de la página vive en condominios_config.js.
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

    // ═══════════════════════ LISTADO DE CONDÓMINOS ═══════════════════════
    // Una fila por cliente de la empresa; sus inmuebles van como etiquetas que abren el modal.
    const L = { page: 1, sorts: CFG.sorts || [], fm: null, cargado: false, rows: {}, sel: new Set(), total: 0 };

    /** Condóminos marcados (se conservan al paginar o filtrar) → destinatarios de «Generar cobro». */
    function marcar(id, on) {
        if (on) L.sel.add(+id); else L.sel.delete(+id);
        pintarMarcados();
    }
    function pintarMarcados() {
        const b = $('cond-marcados');
        if (b) { b.textContent = L.sel.size; b.classList.toggle('d-none', !L.sel.size); }
        const chks = document.querySelectorAll('#cond-tbody .cond-chk');
        const todos = $('cond-chk-todos');
        if (todos) todos.checked = chks.length > 0 && Array.from(chks).every(c => c.checked);
    }

    async function cargarListado(page) {
        L.page = page || L.page || 1;
        L.cargado = true;
        const tbody = $('cond-tbody');
        tbody.classList.add('fm-cargando-target');
        const q = new URLSearchParams({ b: $('cond-buscar').value, page: L.page, orden: (window.CMG_ordenParam ? window.CMG_ordenParam(L.sorts) : '') });
        try {
            const res = await pedir(`${CFG.url}/condominosAjax?${q}`);
            if (!res.ok) { tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${esc(res.mensaje)}</td></tr>`; return; }
            pintarListado(res);
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Error de comunicación con el servidor.</td></tr>';
        } finally { tbody.classList.remove('fm-cargando-target'); }
    }

    function etiquetaInmueble(u) {
        const cls = u.estado !== 'activo' ? 'secondary' : (u.rol === 'propietario' ? 'primary' : 'info');
        const tit = `${u.tipo_label} · ${u.rol === 'propietario' ? 'Propietario' : 'Arrendatario'}${u.paga ? ' · paga la expensa' : ''}${u.estado !== 'activo' ? ' · inactivo' : ''}${u.restringida ? ' · áreas comunes restringidas' : ''}`;
        return `<span class="badge cond-inm bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25 me-1 mb-1" title="${esc(tit)}" onclick="event.stopPropagation(); COND.abrir(${u.id})">`
            + `${u.restringida ? '<i class="bi bi-slash-circle text-danger me-1"></i>' : ''}${esc(u.codigo)}${u.nombre && u.nombre !== u.codigo ? ' · ' + esc(u.nombre) : ''}`
            + `${u.rol === 'arrendatario' ? ' <small>(arr.)</small>' : ''}${u.paga ? ' <i class="bi bi-cash-coin ms-1" title="Paga la expensa"></i>' : ''}</span>`;
    }

    function pintarListado(res) {
        const tbody = $('cond-tbody');
        L.rows = {};
        if (!res.rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-people fs-3 d-block mb-2"></i>No se encontraron clientes. Regístrelos en Clientes o cárguelos con el Excel de inmuebles.</td></tr>';
        } else {
            tbody.innerHTML = res.rows.map(r => {
                L.rows[r.id] = r;
                const inm = (r.lista || []);
                return `<tr class="cond-row${r.activo ? '' : ' text-muted'}" data-id="${r.id}" onclick="COND.fila(${r.id})">
                    <td class="ps-3" onclick="event.stopPropagation()"><input type="checkbox" class="form-check-input cond-chk" value="${r.id}" ${L.sel.has(+r.id) ? 'checked' : ''} onchange="COND.marcar(${r.id}, this.checked)"></td>
                    <td class="fw-medium text-truncate" style="max-width:260px" data-col="nombre" title="${esc(r.nombre)}">${esc(r.nombre)}${r.activo ? '' : ' <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">inactivo</span>'}</td>
                    <td data-col="identificacion">${esc(r.identificacion || '—')}</td>
                    <td class="text-truncate small" style="max-width:220px" data-col="contacto" title="${esc([r.email, r.telefono].filter(Boolean).join(' · '))}">${esc([r.email, r.telefono].filter(Boolean).join(' · ') || '—')}</td>
                    <td data-col="inmuebles">${inm.length ? inm.map(etiquetaInmueble).join('') : '<span class="text-muted small">Sin inmueble</span>'}</td>
                    <td class="text-end" data-col="alicuota_pct">${+r.suma_pct ? num(r.suma_pct, 4) : '<span class="text-muted">—</span>'}</td>
                    <td class="text-center" data-col="restringida">${r.restringida ? '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" title="Tiene inmuebles con áreas comunes restringidas"><i class="bi bi-slash-circle"></i></span>' : '<span class="text-muted">—</span>'}</td>
                    <td class="text-end pe-3">${CFG.perm.crear ? `<button type="button" class="btn btn-link btn-sm p-0" title="Asignar otro inmueble a este cliente" onclick="event.stopPropagation(); COND.nueva(${r.id})"><i class="bi bi-plus-lg"></i></button>` : ''}</td>
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
            $('cond-resumen').textContent = `${s.con_inmueble} de ${s.clientes} clientes con inmueble · ${s.activas} inmuebles activos · Σ ${num(s.suma_pct, 4)} % · ${num(s.suma_m2, 2)} m²` + (+s.restringidas > 0 ? ` · ${s.restringidas} restringido(s)` : '');
        }
        L.total = res.total;
        pintarMarcados();
        if (L.sorter) L.sorter.refreshIcons();
    }

    /** Clic en la fila: sin inmueble → asignarle uno; con uno → abrirlo; con varios → se elige la etiqueta. */
    function fila(idCliente) {
        const r = L.rows[idCliente]; if (!r) return;
        const inm = r.lista || [];
        if (!inm.length) { if (CFG.perm.crear) nueva(idCliente); return; }
        if (inm.length === 1) abrir(inm[0].id);
    }

    // ═══════════════════════ MODAL UNIDAD ═══════════════════════
    const M = { id: 0, u: null, personasIni: '', guardando: false };
    const modalU = () => bootstrap.Modal.getOrCreateInstance($('modalUnidad'));

    /** Inmueble nuevo; si viene de la fila de un cliente, ese cliente queda como propietario. */
    function nueva(idCliente) {
        if (!CFG.config) return aviso('info', 'Configure el condominio', 'Antes de asignar inmuebles guarde la pestaña Condominio.');
        M.id = 0; M.u = null; M.personasIni = '';
        $('formUnidad').reset();
        $('uni_id').value = '';
        ['uni_propietario', 'uni_arrendatario'].forEach(p => setChip(p + '_txt', 'uni_id_' + p.replace('uni_', ''), '', ''));
        const cli = idCliente ? L.rows[idCliente] : null;
        if (cli) setChip('uni_propietario_txt', 'uni_id_propietario', cli.id, lblCliente(cli));
        $('uni-titulo').textContent = 'Nuevo inmueble';
        $('uni-badge-estado').classList.add('d-none'); $('uni-badge-restr').classList.add('d-none');
        $('uni-wrap-cambio-personas').classList.remove('d-none');
        $('uni_propietario_desde').value = new Date().toISOString().slice(0, 10);
        $('uni-btn-eliminar')?.classList.add('d-none');
        $('uni-registro').textContent = '';
        $('uni-historial-body').innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Guarde el inmueble para ver el historial.</td></tr>';
        $('uni-restr-body').innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">—</td></tr>';
        $('uni-susc-actual').textContent = 'Guarde el inmueble para ver su expensa.'; $('uni-susc-enlazables').innerHTML = '';
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
            act.innerHTML = '<i class="bi bi-info-circle me-1"></i>Este inmueble aún no tiene suscripción. La cuota se emite desde Suscripciones: al crear la suscripción del pagador elija este inmueble, o enlace aquí una existente.';
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
            const c = await Swal.fire({ icon: 'question', title: `¿Enlazar la suscripción #${idSuscripcion}?`, text: 'Sus recibos o facturas llevarán el detalle del inmueble en la información adicional.', showCancelButton: true, confirmButtonText: 'Enlazar', cancelButtonText: 'Cancelar', target: $('modalUnidad') });
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
        const c = await Swal.fire({ icon: 'question', title: `¿Cargar ${X.filas.length} inmueble(s)?`, text: 'Las existentes se actualizan y los clientes que falten se crean.', showCancelButton: true, confirmButtonText: 'Sí, aplicar', cancelButtonText: 'Cancelar', target: $('modalCondExcel') });
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

    // ═══════════════════════ GENERAR COBRO (emitir en bloque / agregar a suscripciones) ═══════════════════════
    const C = { opciones: null, filas: [], sel: new Set(), previewOk: false, enCurso: false, token: '', idEmision: 0, sondeo: null };
    const modalC = () => bootstrap.Modal.getOrCreateInstance($('modalCondCobro'));
    const modalE = () => bootstrap.Modal.getOrCreateInstance($('modalCondEmisiones'));
    const ACCION_LBL = {
        emitir: ['primary', 'Emitir'], omitir: ['secondary', 'Omitir'], crear: ['success', 'Crear suscripción'],
        agregar: ['info', 'Agregar línea'], actualizar: ['warning', 'Actualizar valor'], sin_cambio: ['secondary', 'Sin cambio'],
    };
    const ESTADO_EMI = { pendiente: ['secondary', 'En cola'], procesando: ['primary', 'Procesando'], completado: ['success', 'Completada'],
        completado_con_errores: ['warning', 'Con errores'], cancelado: ['secondary', 'Cancelada'] };
    const ESTADO_ITEM = { pendiente: ['secondary', 'En cola'], procesando: ['primary', 'Procesando'], generado: ['success', 'Generado'],
        autorizado: ['success', 'Autorizada'], en_procesamiento: ['info', 'En el SRI'], error: ['danger', 'Error'], revisar: ['warning', 'Revisar'] };
    const CORREO_LBL = { no_aplica: ['secondary', '—'], pendiente: ['secondary', 'Pendiente'], enviado: ['success', 'Enviado'], sin_correo: ['warning', 'Sin correo'], error: ['danger', 'Error'] };
    const badge = ([cls, txt]) => `<span class="badge bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25">${esc(txt)}</span>`;

    async function cargarOpcionesCobro() {
        if (C.opciones) return C.opciones;
        const res = await pedir(`${CFG.url}/cobroOpcionesAjax`);
        if (!res.ok) throw new Error(res.mensaje || 'No se pudieron cargar las opciones.');
        C.opciones = res;
        $('cob_id_punto_emision').innerHTML = (res.series || []).map(s => `<option value="${s.id_punto_emision}">${esc(s.establecimiento_codigo)}-${esc(s.punto_emision_codigo)} · ${esc(s.establecimiento_nombre || '')}</option>`).join('')
            || '<option value="">— Sin series activas —</option>';
        $('cob_id_periodicidad').innerHTML = (res.periodicidades || []).map(p => `<option value="${p.id}">${esc(p.nombre)}</option>`).join('');
        $('cob_multa').innerHTML = '<option value="">—</option>' + (res.multas || []).map(m => `<option value="${m.id}">${esc(m.nombre)} (${money(m.valor)})</option>`).join('');
        return res;
    }

    /** Abre la ventana. Cada apertura es un cobro nuevo: token de guardado nuevo (§8 «un guardado = un registro»). */
    async function cobro() {
        try { await cargarOpcionesCobro(); } catch (e) { return aviso('error', 'No se pudo abrir', e.message); }
        C.token = typeof window.CMG_nuevoTokenGuardado === 'function' ? window.CMG_nuevoTokenGuardado() : String(Date.now()) + Math.random();
        C.idEmision = 0; C.enCurso = false; detenerSondeo();
        $('cob_destino').value = L.sel.size ? 'marcados' : 'filtro';
        $('cob_destino').options[0].textContent = `Los marcados en el listado (${L.sel.size})`;
        $('cob_destino').options[1].textContent = `Todos los del filtro actual (${L.total})`;
        if (!$('cob_fecha_inicio').value) { const d = new Date(); d.setMonth(d.getMonth() + 1, 1); $('cob_fecha_inicio').value = d.toISOString().slice(0, 10); }
        $('cob-progreso').classList.add('d-none');
        cobroModo();
        modalC().show();
    }

    function cobroModo() {
        const emitir = $('cob_modo').value === 'emitir';
        document.querySelectorAll('#modalCondCobro .cob-emitir').forEach(el => el.classList.toggle('d-none', !emitir));
        document.querySelectorAll('#modalCondCobro .cob-susc').forEach(el => el.classList.toggle('d-none', emitir));
        if (!emitir) $('cob_agrupar').value = 'inmueble'; // el cobro recurrente va por inmueble (enlace con la suscripción)
        cobroAgrupar();
    }
    function cobroAgrupar() {
        const porInmueble = $('cob_agrupar').value === 'inmueble';
        const opt = $('cob_forma_valor').querySelector('option[value="inmueble"]');
        opt.disabled = !porInmueble;
        if (!porInmueble) $('cob_forma_valor').value = 'fijo';
        $('cob_valor').disabled = $('cob_forma_valor').value === 'inmueble';
        cobroCambio();
    }
    function cobroMulta() {
        const m = (C.opciones?.multas || []).find(x => String(x.id) === $('cob_multa').value);
        if (!m) return;
        setChip('cob_producto_txt', 'cob_id_producto', m.id_producto, m.producto_nombre || m.nombre);
        $('cob_forma_valor').value = 'fijo'; $('cob_valor').value = (+m.valor).toFixed(2); $('cob_valor').disabled = false;
        if (!$('cob_descripcion').value) $('cob_descripcion').value = m.nombre;
        cobroCambio();
    }
    /** Cualquier cambio invalida la vista previa: hay que volver a verla antes de grabar. */
    function cobroCambio() {
        C.previewOk = false;
        $('cob-preview').classList.add('d-none');
        $('cob-btn-aplicar')?.classList.add('d-none');
        $('cob-pie').textContent = '';
    }

    function datosCobro() {
        return {
            modo: $('cob_modo').value, destino: $('cob_destino').value, ids: Array.from(L.sel).join(','), buscar: $('cond-buscar').value,
            agrupar: $('cob_agrupar').value, id_producto: $('cob_id_producto').value, forma_valor: $('cob_forma_valor').value, valor: $('cob_valor').value,
            tipo_comprobante: $('cob_tipo_comprobante').value, id_punto_emision: $('cob_id_punto_emision').value, descripcion: $('cob_descripcion').value,
            texto_item: $('cob_texto_item').value, enviar_correo: $('cob_enviar_correo').checked ? '1' : '0',
            id_periodicidad: $('cob_id_periodicidad').value, fecha_inicio: $('cob_fecha_inicio').value, seleccion: Array.from(C.sel).join(','),
        };
    }
    function errorCobro(mensaje) {
        const [txt, s] = String(mensaje || '').split('|');
        aviso('error', 'Revise', txt, $('modalCondCobro'));
        if (s) sel(document.querySelector(s));
    }

    async function cobroPreview() {
        const res = await post('cobroPreviewAjax', datosCobro());
        if (!res.ok) return errorCobro(res.mensaje);
        C.filas = res.filas || [];
        C.sel = new Set(C.filas.filter(f => f.incluir).map(f => f.clave));
        C.previewOk = true;
        pintarCobro();
    }
    function pintarCobro() {
        const emitir = $('cob_modo').value === 'emitir';
        const aplicables = emitir ? ['emitir'] : ['crear', 'agregar', 'actualizar'];
        const elegidas = C.filas.filter(f => C.sel.has(f.clave) && aplicables.includes(f.accion));
        const total = elegidas.reduce((s, f) => s + (parseFloat(f.valor) || 0), 0);
        const cuenta = a => C.filas.filter(f => f.accion === a).length;
        const kpi = (lbl, val, sub) => `<div class="col-6 col-md-3"><div class="border rounded-3 p-2 bg-light"><div class="text-muted" style="font-size:.7rem">${lbl}</div><div class="fw-bold">${val}</div>${sub ? `<div class="text-muted" style="font-size:.7rem">${sub}</div>` : ''}</div></div>`;
        $('cob-kpis').innerHTML = emitir
            ? kpi('Documentos a emitir', `${elegidas.length} de ${C.filas.length}`, `${cuenta('omitir')} sin valor o sin inmueble`)
              + kpi('Total sin IVA', money(total)) + kpi('Comprobante', $('cob_tipo_comprobante').value === 'factura' ? 'Factura' : 'Recibo de venta')
              + kpi('Correo', $('cob_enviar_correo').checked ? `${elegidas.filter(f => /\S+@\S+\.\S+/.test(f.email)).length} con correo` : 'No se envía')
            : kpi('Suscripciones nuevas', cuenta('crear')) + kpi('Línea agregada', cuenta('agregar'))
              + kpi('Valor actualizado', cuenta('actualizar')) + kpi('Ya estaban (sin cambio)', cuenta('sin_cambio'));
        $('cob-th-valor').textContent = emitir ? 'Valor' : 'Actual → nuevo';
        const aplicable = f => aplicables.includes(f.accion);
        $('cob-body').innerHTML = C.filas.map(f => `<tr class="${aplicable(f) ? (C.sel.has(f.clave) ? '' : 'text-muted') : 'table-light text-muted'}">
            <td class="ps-2">${aplicable(f) ? `<input type="checkbox" class="form-check-input" ${C.sel.has(f.clave) ? 'checked' : ''} onchange="COND.cobroToggle('${f.clave}', this.checked)">` : ''}</td>
            <td>${esc(f.cliente)} <small class="text-muted">${esc(f.identificacion)}</small></td>
            <td class="text-truncate" style="max-width:220px" title="${esc(f.inmueble)}">${esc(f.inmueble || '—')}</td>
            <td class="text-end">${emitir ? (f.valor === null ? '—' : money(f.valor)) : `${f.actual === null ? '' : money(f.actual) + ' → '}${f.valor === null ? '—' : money(f.valor)}`}</td>
            <td>${badge(ACCION_LBL[f.accion] || ['secondary', f.accion])}</td>
            <td class="small">${esc(f.email || '—')}</td>
            <td class="pe-2 small text-muted">${esc(f.nota || '')}</td></tr>`).join('')
            || '<tr><td colspan="7" class="text-center text-muted py-3">No hay condóminos para este cobro.</td></tr>';
        const aplicablesTodas = C.filas.filter(aplicable);
        $('cob-todas').checked = aplicablesTodas.length > 0 && aplicablesTodas.every(f => C.sel.has(f.clave));
        $('cob-preview').classList.remove('d-none');
        const btn = $('cob-btn-aplicar');
        if (btn) {
            btn.querySelector('span').textContent = emitir ? `Emitir ${elegidas.length} documento(s)` : `Aplicar ${elegidas.length} cambio(s)`;
            btn.classList.toggle('d-none', !elegidas.length);
        }
        $('cob-pie').textContent = emitir ? 'Las filas con aviso «ya cobra este concepto» vienen desmarcadas para no cobrar dos veces.' : '';
    }
    function cobroToggle(clave, on) { if (on) C.sel.add(clave); else C.sel.delete(clave); pintarCobro(); }
    function cobroTodas(on) {
        const emitir = $('cob_modo').value === 'emitir';
        const aplicables = emitir ? ['emitir'] : ['crear', 'agregar', 'actualizar'];
        C.sel = new Set(on ? C.filas.filter(f => aplicables.includes(f.accion)).map(f => f.clave) : []);
        pintarCobro();
    }

    async function cobroAplicar() {
        if (C.enCurso) return;
        if (!C.previewOk) return aviso('info', 'Vea la vista previa', 'Revise primero a quiénes se cobra.', $('modalCondCobro'));
        C.enCurso = true; // antes del primer await: un doble clic no crea dos emisiones
        const btn = $('cob-btn-aplicar'); if (btn) btn.disabled = true;
        let liberar = true;
        try {
            const emitir = $('cob_modo').value === 'emitir';
            const n = C.sel.size;
            const c = await Swal.fire({ icon: 'question', target: $('modalCondCobro'), showCancelButton: true, cancelButtonText: 'Cancelar',
                title: emitir ? `¿Emitir ${n} documento(s)?` : `¿Aplicar los cambios a las suscripciones?`,
                text: emitir ? 'Se generan en segundo plano con la serie elegida' + ($('cob_tipo_comprobante').value === 'factura' ? ' y se envían al SRI.' : '.') : 'Se crean, completan o actualizan las suscripciones marcadas.',
                confirmButtonText: emitir ? 'Sí, emitir' : 'Sí, aplicar' });
            if (!c.isConfirmed) return;
            if (!emitir) {
                const res = await post('suscripcionesAplicarAjax', datosCobro());
                if (!res.ok) return errorCobro(res.mensaje);
                await cobroPreview(); // queda a la vista cómo quedó: todo «sin cambio»
                return aviso('success', 'Listo', res.mensaje, $('modalCondCobro'));
            }
            const res = await post('emitirAjax', Object.assign(datosCobro(), { token_guardado: C.token }));
            if (!res.ok) return errorCobro(res.mensaje);
            // Emisión creada: el botón NO se reactiva (otra pulsación crearía otra emisión). Para
            // emitir otra cosa se vuelve a abrir la ventana, con un token nuevo.
            liberar = false;
            btn?.classList.add('d-none');
            C.idEmision = res.id;
            $('cob-preview').classList.add('d-none');
            $('cob-progreso').classList.remove('d-none');
            aviso(res.previo ? 'info' : 'success', res.previo ? 'Ya registrada' : 'Emisión creada', res.mensaje, $('modalCondCobro'));
            iniciarSondeo();
        } catch (e) {
            aviso('error', 'No se pudo', 'Error de comunicación con el servidor. Revise «Emisiones» antes de volver a intentarlo.', $('modalCondCobro'));
        } finally {
            C.enCurso = false;
            if (btn && liberar) btn.disabled = false;
        }
    }

    function detenerSondeo() { if (C.sondeo) { clearInterval(C.sondeo); C.sondeo = null; } }
    function iniciarSondeo() {
        detenerSondeo();
        const paso = async () => {
            try {
                const res = await pedir(`${CFG.url}/emisionEstadoAjax?id=${C.idEmision}`);
                if (!res.ok) return detenerSondeo();
                const e = res.emision, total = +e.total_items || 1, hechos = (+e.generados) + (+e.fallidos);
                $('cob-prog-barra').style.width = Math.round(hechos * 100 / total) + '%';
                $('cob-prog-barra').classList.toggle('bg-warning', +e.fallidos > 0);
                $('cob-prog-texto').textContent = `${e.generados} generado(s) · ${e.fallidos} con error · ${e.correos_enviados} correo(s) · ${e.pendientes} pendiente(s)`;
                $('cob-prog-titulo').textContent = (ESTADO_EMI[e.estado] || ['', e.estado])[1];
                if (!['pendiente', 'procesando'].includes(e.estado)) { detenerSondeo(); cargarListado(); }
            } catch (err) { /* reintenta en el siguiente paso */ }
        };
        paso();
        C.sondeo = setInterval(paso, 3000);
    }

    // ── Historial de emisiones ──
    const E = { lista: {} };
    const fechaHora = iso => { const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/.exec(iso || ""); return m ? `${m[3]}-${m[2]}-${m[1]} ${m[4]}:${m[5]}:${m[6]}` : fecha(iso); };
    async function emisiones() {
        $('emi-detalle').classList.add('d-none'); $('emi-lista').classList.remove('d-none'); $('emi-sub').textContent = '';
        modalE().show();
        const res = await pedir(`${CFG.url}/emisionesAjax`);
        if (!res.ok) { $('emi-body').innerHTML = `<tr><td colspan="11" class="text-center text-danger py-3">${esc(res.mensaje)}</td></tr>`; return; }
        E.lista = {}; (res.emisiones || []).forEach(e => { E.lista[e.id] = e; });
        $('emi-body').innerHTML = (res.emisiones || []).map(e => `<tr role="button" onclick="COND.emisionDetalle(${e.id})">
            <td class="ps-2 text-nowrap">${fechaHora(e.created_at)}</td><td class="fw-medium">${esc(e.descripcion)}</td><td>${esc(e.producto_nombre || '')}</td>
            <td>${e.tipo_comprobante === 'factura' ? 'Factura' : 'Recibo'}</td><td class="text-end">${e.total_items}</td><td class="text-end">${e.generados}</td>
            <td class="text-end ${+e.fallidos ? 'text-danger fw-bold' : ''}">${e.fallidos}</td><td class="text-end">${esTrue(e.enviar_correo) ? e.correos_enviados : '—'}</td>
            <td class="text-end">${money(e.total_valor)}</td><td>${badge(ESTADO_EMI[e.estado] || ['secondary', e.estado])}</td>
            <td class="text-end pe-2">${['pendiente', 'procesando'].includes(e.estado) && CFG.perm.actualizar ? `<button type="button" class="btn btn-link btn-sm p-0 text-danger" title="Cancelar lo que falta" onclick="event.stopPropagation(); COND.emisionCancelar(${e.id})"><i class="bi bi-x-circle"></i></button>` : ''}</td></tr>`).join('')
            || '<tr><td colspan="11" class="text-center text-muted py-3">Aún no hay emisiones en bloque.</td></tr>';
    }
    async function emisionDetalle(id) {
        const desc = (E.lista[id] || {}).descripcion || "";
        const res = await pedir(`${CFG.url}/emisionEstadoAjax?id=${id}&items=1`);
        if (!res.ok) return aviso('error', 'No se pudo', res.mensaje, $('modalCondEmisiones'));
        $('emi-sub').textContent = '· ' + desc;
        $('emi-items').innerHTML = (res.items || []).map(i => `<tr>
            <td class="ps-2">${esc(i.cliente_nombre)} <small class="text-muted">${esc(i.cliente_identificacion || '')}</small></td>
            <td>${esc(i.inmueble_texto || '—')}</td><td class="text-end">${money(i.valor)}</td><td>${esc(i.numero || '—')}</td>
            <td>${badge(ESTADO_ITEM[i.estado] || ['secondary', i.estado])}</td><td>${badge(CORREO_LBL[i.estado_correo] || ['secondary', i.estado_correo])}</td>
            <td class="pe-2 small text-muted">${esc(i.mensaje || '')}</td></tr>`).join('')
            || '<tr><td colspan="7" class="text-center text-muted py-3">Sin documentos.</td></tr>';
        $('emi-lista').classList.add('d-none'); $('emi-detalle').classList.remove('d-none');
    }
    function emisionesVolver() { emisiones(); }
    async function emisionCancelar(id) {
        const c = await Swal.fire({ icon: 'warning', title: '¿Cancelar esta emisión?', text: 'Los documentos ya generados se conservan; los que faltan no se emitirán.', showCancelButton: true, confirmButtonText: 'Sí, cancelar', cancelButtonText: 'No', target: $('modalCondEmisiones') });
        if (!c.isConfirmed) return;
        const res = await post('emisionCancelarAjax', { id });
        if (!res.ok) return aviso('error', 'No se pudo', res.mensaje, $('modalCondEmisiones'));
        emisiones();
    }

    // ═══════════════════════ INICIO ═══════════════════════
    document.addEventListener('DOMContentLoaded', () => {
        if (window.FiltrosModal) {
            L.fm = new FiltrosModal({
                containerId: 'fmBuscadorCOND', hiddenInputId: 'cond-buscar', placeholder: 'Buscar en todas las columnas...', titulo: 'Filtros de condóminos',
                inputWidth: 420, extraId: 'fmExtraCOND', fields: CFG.filtros || [], loadingTarget: '#cond-tbody',
                onApply: () => cargarListado(1),
            });
            L.fm.init();
        }
        $('cond-paginacion').addEventListener('click', e => { const b = e.target.closest('[data-pag]'); if (b && !b.disabled) cargarListado(L.page + parseInt(b.dataset.pag, 10)); });
        if (window.CMG_initSort) {
            L.sorter = window.CMG_initSort(CFG.modulo, (col, dir, sorts) => { L.sorts = sorts; cargarListado(1); }, { sorts: L.sorts, multi: true, container: '.condominos-scroll', reload: false });
        }
        $('formUnidad')?.addEventListener('submit', guardar);
        chip('uni_propietario_txt', 'uni_id_propietario', 'uni_propietario_dd', buscarClientes, lblCliente, onPersonas);
        chip('uni_arrendatario_txt', 'uni_id_arrendatario', 'uni_arrendatario_dd', buscarClientes, lblCliente, onPersonas);
        $('uni_pagador')?.addEventListener('change', onPersonas);
        ['uni_monto_manual', 'uni_area_m2', 'uni_alicuota_pct'].forEach(id => $(id)?.addEventListener('input', cuotaEstimada));

        // La pestaña vive dentro del formulario de configuración: Enter en el buscador no debe
        // enviar ese formulario (el buscador aplica con su propio listener, antes de llegar aquí).
        $('pane-cfg-condominos')?.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.tagName === 'INPUT') e.preventDefault(); });
        // El listado se carga al abrir la pestaña por primera vez; el botón Guardar de la
        // configuración no aplica a esta pestaña (cada inmueble se guarda en su modal).
        const tab = $('cfg-tab-condominos-btn');
        const pie = () => $('cfg-btn-guardar');
        document.querySelectorAll('#formCondConfig a[data-bs-toggle="tab"]').forEach(a => a.addEventListener('shown.bs.tab', () => {
            const enCondominos = a === tab;
            pie()?.classList.toggle('d-none', enCondominos);
            if (enCondominos && !L.cargado) cargarListado(1);
        }));
        if (tab && tab.classList.contains('active')) { pie()?.classList.add('d-none'); cargarListado(1); }
    });

    document.addEventListener('DOMContentLoaded', () => {
        // Marcar / desmarcar todos los condóminos de la página visible.
        $('cond-chk-todos')?.addEventListener('change', e => {
            document.querySelectorAll('#cond-tbody .cond-chk').forEach(c => { c.checked = e.target.checked; marcar(c.value, e.target.checked); });
        });
        chip('cob_producto_txt', 'cob_id_producto', 'cob_producto_dd', buscarServicios, lblServicio, () => cobroCambio());
        ['cob_valor', 'cob_descripcion', 'cob_texto_item'].forEach(id => $(id)?.addEventListener('input', cobroCambio));
        $('modalCondCobro')?.addEventListener('hidden.bs.modal', detenerSondeo);
    });

    return { nueva, abrir, fila, eliminar, restringir, enlazar, onMetodo, abrirExcel, excelLeer, excelAplicar, marcar,
             cobro, cobroModo, cobroAgrupar, cobroMulta, cobroCambio, cobroPreview, cobroToggle, cobroTodas, cobroAplicar,
             emisiones, emisionDetalle, emisionesVolver, emisionCancelar };
})();
