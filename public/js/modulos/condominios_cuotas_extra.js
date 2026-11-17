/**
 * condominios_cuotas_extra.js — Configuración de condominios → pestaña «Cuotas extraordinarias».
 *
 * Cuotas extraordinarias a plazos (pintura, ascensor…): listado, ventana con datos generales,
 * plan por inmueble (reparto, anticipo, plazo, pagador), cronograma de cuotas y emisión aparte.
 * La lógica vive en el servidor (CondominioCuotaExtraService); aquí solo se pinta y se envía.
 * Configuración en window.COND_CFG (url, perm). Diseño: docs/diseno/condominios-cuotas-extraordinarias.md.
 */
window.CEX = (function () {
    'use strict';

    const CFG = window.COND_CFG || {};
    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = n => { const v = parseFloat(n) || 0; return (v < 0 ? '-$' : '$') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    const mes = p => p ? `${String(p).slice(4, 6)}/${String(p).slice(0, 4)}` : '';
    const fechaD = f => f ? String(f).slice(0, 10).split('-').reverse().join('-') : '';

    const ESTADO_PLAN = { pendiente: ['secondary', 'Pendiente'], en_curso: ['primary', 'En curso'], completo: ['success', 'Completo'], liquidado: ['info', 'Liquidado'], pagado: ['success', 'Pagado'], cancelado: ['secondary', 'Cancelado'] };
    const ESTADO_CARGO = { pendiente: ['warning', 'Pendiente'], emitido: ['success', 'Emitido'], anulado: ['secondary', 'Anulado'] };
    const SUBTIPO = { cuota: '', abono: 'Abono inicial', liquidacion: 'Liquidación' };
    const badge = ([cls, lbl]) => `<span class="badge bg-${cls} bg-opacity-10 text-${cls} border border-${cls} border-opacity-25">${esc(lbl)}</span>`;

    const S = { opciones: null, actual: null, prev: null, aparte: null, orden: 'creado:DESC', ocupado: false, tokenPlanes: null, tokenEmision: null, cargado: false };

    async function pedir(url, opciones) {
        const r = await fetch(url, Object.assign({ headers: { 'X-Requested-With': 'XMLHttpRequest' } }, opciones || {}));
        return r.json();
    }
    function post(accion, datos) {
        const fd = new FormData();
        Object.entries(datos || {}).forEach(([k, v]) => fd.append(k, v ?? ''));
        return pedir(`${CFG.url}/${accion}`, { method: 'POST', body: fd });
    }
    function aviso(icon, title, text) { return Swal.fire({ icon, title, text }); }
    function errorForm(mensaje) {
        const [txt, s] = String(mensaje || 'Error').split('|');
        aviso('error', 'Revise', txt);
        if (!s) return;
        const el = document.querySelector(s); if (!el) return;
        const pane = el.closest('.tab-pane');
        if (pane) { const btn = document.querySelector(`#modalCuotaExtra a[href="#${pane.id}"]`); if (btn) bootstrap.Tab.getOrCreateInstance(btn).show(); }
        el.focus(); el.classList.add('is-invalid'); setTimeout(() => el.classList.remove('is-invalid'), 2500);
    }
    /** Bandera «en curso» + botón deshabilitado ANTES del primer await (CLAUDE.md §8). */
    async function unaVez(btnId, fn) {
        if (S.ocupado) return;
        S.ocupado = true; const b = $(btnId); if (b) b.disabled = true;
        try { await fn(); } catch (e) { aviso('error', 'Error de conexión', 'No se pudo comunicar con el servidor. Revise antes de reintentar.'); }
        finally { S.ocupado = false; if (b) b.disabled = false; }
    }

    // ── Listado ──
    let tBuscar;
    async function cargar(conOpciones) {
        const res = await pedir(`${CFG.url}/cuotasExtraAjax?b=${encodeURIComponent($('cex-buscar')?.value || '')}&orden=${encodeURIComponent(S.orden)}${conOpciones ? '&opciones=1' : ''}`);
        if (!res.ok) { $('cex-body').innerHTML = `<tr><td colspan="9" class="text-center text-danger py-3">${esc(res.mensaje)}</td></tr>`; return; }
        if (res.opciones) S.opciones = res.opciones;
        if (S.opciones && !S.opciones.instalado) {
            $('cex-body').innerHTML = '<tr><td colspan="9" class="text-center text-warning py-3">Falta aplicar database/migrations/20261117_condominios_cuotas_extraordinarias.sql.</td></tr>';
            return;
        }
        const rows = res.rows || [];
        $('cex-body').innerHTML = rows.length ? rows.map(r => `<tr style="cursor:pointer" onclick="CEX.abrir(${r.id})">
            <td class="ps-2 fw-medium">${esc(r.nombre)}</td>
            <td>${esc(r.acta || '')}${r.fecha_acta ? ` <small class="text-muted">${fechaD(r.fecha_acta)}</small>` : ''}</td>
            <td>${esc(r.producto_nombre || '')}</td>
            <td class="text-end">${money(r.meta_total)}</td>
            <td class="text-end">${r.inmuebles}</td>
            <td class="text-end">${money(r.emitido)}</td>
            <td class="text-end">${money(r.por_emitir)}</td>
            <td>${r.donde_cobro === 'aparte' ? 'Aparte' : 'Con la alícuota'}</td>
            <td class="pe-2">${badge(r.estado === 'activo' ? ['primary', 'Activa'] : ['secondary', 'Cerrada'])}</td></tr>`).join('')
            : '<tr><td colspan="9" class="text-center text-muted py-3">Aún no hay cuotas extraordinarias.</td></tr>';
        document.querySelectorAll('#cex-tabla th[data-sort]').forEach(th => {
            const [c, d] = S.orden.split(':');
            th.querySelector('.cex-dir')?.remove();
            if (th.dataset.sort === c) th.insertAdjacentHTML('beforeend', ` <i class="cex-dir bi bi-caret-${d === 'DESC' ? 'down' : 'up'}-fill"></i>`);
        });
    }
    function ordenar(col) {
        const [c, d] = S.orden.split(':');
        S.orden = `${col}:${c === col && d === 'ASC' ? 'DESC' : 'ASC'}`;
        cargar(false);
    }

    // ── Ventana: opciones ──
    function llenarSelects() {
        const o = S.opciones || {};
        $('cex_id_punto_emision').innerHTML = (o.series || []).map(s => `<option value="${s.id_punto_emision}">${esc(s.establecimiento_codigo)}-${esc(s.punto_emision_codigo)} · ${esc(s.establecimiento_nombre || '')}</option>`).join('');
        $('cex_id_forma_anticipo').innerHTML = '<option value="">— La primera de tipo Anticipo —</option>'
            + (o.formas_anticipo || []).map(f => `<option value="${f.id}">${esc(f.nombre)}</option>`).join('');
    }
    function plazosTexto() { return ($('cex_plazos').value || '').split(/[\s,;]+/).map(n => parseInt(n, 10)).filter(n => n >= 1 && n <= 120).sort((a, b) => a - b).filter((n, i, a) => a.indexOf(n) === i); }
    function llenarPlazoDefecto(sel) {
        const ps = plazosTexto(); const v = sel ?? parseInt($('cex_plazo_defecto').value, 10);
        $('cex_plazo_defecto').innerHTML = ps.map(p => `<option value="${p}" ${p === v ? 'selected' : ''}>${p} cuota${p > 1 ? 's' : ''}</option>`).join('');
        if (!ps.includes(v) && ps.length) $('cex_plazo_defecto').value = String(ps[ps.length - 1]);
    }

    // Buscador tipo chip del concepto (Backspace/Delete limpian toda la selección).
    function chipProducto() {
        const input = $('cex_producto_txt'), hidden = $('cex_id_producto'), dd = $('cex_producto_dd');
        if (!input || input.dataset.listo) return; input.dataset.listo = '1';
        let t;
        input.addEventListener('keydown', e => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && hidden.value !== '') { e.preventDefault(); hidden.value = ''; input.value = ''; dd.style.display = 'none'; }
        });
        input.addEventListener('input', () => {
            hidden.value = ''; clearTimeout(t);
            const q = input.value.trim();
            if (!q) { dd.style.display = 'none'; return; }
            t = setTimeout(async () => {
                const rows = (await pedir(`${CFG.url}/buscarServiciosAjax?q=${encodeURIComponent(q)}`)).rows || [];
                dd.innerHTML = rows.length ? rows.map(p => { const l = `${p.codigo ? p.codigo + ' - ' : ''}${p.nombre}`; return `<a href="#" class="list-group-item list-group-item-action py-1 px-2 small" data-id="${p.id}" data-label="${esc(l)}">${esc(l)}</a>`; }).join('')
                    : '<span class="list-group-item text-muted small py-1 px-2">Sin resultados. Cree el servicio en Productos.</span>';
                dd.style.display = 'block';
            }, 300);
        });
        dd.addEventListener('click', e => { const a = e.target.closest('a[data-id]'); if (!a) return; e.preventDefault(); hidden.value = a.dataset.id; input.value = a.dataset.label; dd.style.display = 'none'; });
        document.addEventListener('click', e => { if (e.target !== input && !dd.contains(e.target)) dd.style.display = 'none'; });
    }

    async function asegurarOpciones() {
        if (!S.opciones) { const r = await pedir(`${CFG.url}/cuotasExtraAjax?opciones=1`); S.opciones = r.opciones || {}; }
        llenarSelects();
    }

    function nuevo() {
        unaVezAbrir(async () => {
            await asegurarOpciones();
            S.actual = null; S.prev = null; S.aparte = null;
            $('cex_id').value = '';
            ['cex_nombre', 'cex_acta', 'cex_fecha_acta', 'cex_meta_total', 'cex_descripcion', 'cex_producto_txt', 'cex_id_producto'].forEach(id => { $(id).value = ''; });
            $('cex_calculo').value = 'porcentaje'; $('cex_plazos').value = '1, 6, 12, 24'; llenarPlazoDefecto(24);
            const sig = new Date(); sig.setDate(1); sig.setMonth(sig.getMonth() + 1); // por defecto, el mes siguiente
            $('cex_primer_periodo').value = `${sig.getFullYear()}-${String(sig.getMonth() + 1).padStart(2, '0')}`;
            $('cex_donde_cobro').value = 'junto'; $('cex_pagador_defecto').value = 'propietario'; $('cex_tipo_comprobante').value = 'recibo';
            $('cex_id_forma_anticipo').value = '';
            pintarDetalle(null);
        });
    }
    async function unaVezAbrir(fn) {
        await fn();
        bootstrap.Tab.getOrCreateInstance(document.querySelector('#modalCuotaExtra a[href="#cex-pane-general"]')).show();
        bootstrap.Modal.getOrCreateInstance($('modalCuotaExtra')).show();
    }
    async function abrir(id) {
        await asegurarOpciones();
        const res = await pedir(`${CFG.url}/cuotaExtraGetAjax?id=${id}`);
        if (!res.ok) return aviso('error', 'No se pudo abrir', res.mensaje);
        S.prev = null; S.aparte = null;
        cargarFormulario(res.aporte);
        pintarDetalle(res);
        await unaVezAbrir(async () => {});
    }
    function cargarFormulario(a) {
        $('cex_id').value = a.id;
        $('cex_nombre').value = a.nombre || ''; $('cex_acta').value = a.acta || ''; $('cex_fecha_acta').value = (a.fecha_acta || '').slice(0, 10);
        $('cex_meta_total').value = a.meta_total; $('cex_descripcion').value = a.descripcion || '';
        $('cex_id_producto').value = a.id_producto; $('cex_producto_txt').value = `${a.producto_codigo ? a.producto_codigo + ' - ' : ''}${a.producto_nombre || ''}`;
        $('cex_calculo').value = a.calculo === 'porcentaje' ? 'porcentaje' : 'iguales';
        $('cex_plazos').value = (a.plazos_permitidos || []).join(', '); llenarPlazoDefecto(parseInt(a.plazo_defecto, 10));
        $('cex_primer_periodo').value = a.primer_periodo ? `${a.primer_periodo.slice(0, 4)}-${a.primer_periodo.slice(4, 6)}` : '';
        $('cex_donde_cobro').value = a.donde_cobro; $('cex_pagador_defecto').value = a.pagador_defecto;
        $('cex_tipo_comprobante').value = a.tipo_comprobante || 'recibo'; $('cex_id_punto_emision').value = a.id_punto_emision || '';
        $('cex_id_forma_anticipo').value = a.id_forma_anticipo || '';
    }

    /** Pinta KPIs, planes y cuotas; habilita o bloquea controles según el estado. */
    function pintarDetalle(d) {
        S.actual = d;
        const a = d?.aporte, planes = d?.planes || [], cerrada = a && a.estado !== 'activo';
        $('cex-titulo').textContent = a ? a.nombre : 'Nueva cuota extraordinaria';
        $('cex-estado-badge').classList.toggle('d-none', !a);
        if (a) $('cex-estado-badge').textContent = cerrada ? 'Cerrada' : 'Activa';
        const conPlanes = planes.length > 0;
        ['cex_meta_total', 'cex_calculo', 'cex_primer_periodo', 'cex_producto_txt'].forEach(id => { $(id).disabled = conPlanes || cerrada; });
        ['cex_nombre', 'cex_acta', 'cex_fecha_acta', 'cex_plazos', 'cex_plazo_defecto', 'cex_donde_cobro', 'cex_pagador_defecto', 'cex_tipo_comprobante', 'cex_id_punto_emision', 'cex_id_forma_anticipo', 'cex_descripcion']
            .forEach(id => { $(id).disabled = !!cerrada; });
        $('cex-bloqueo-aviso').classList.toggle('d-none', !conPlanes);
        const puedeGuardar = a ? CFG.perm.actualizar : CFG.perm.crear;
        if ($('cex-btn-guardar')) $('cex-btn-guardar').classList.toggle('d-none', !puedeGuardar || !!cerrada);
        if ($('cex-btn-eliminar')) $('cex-btn-eliminar').classList.toggle('d-none', !a || conPlanes);
        if ($('cex-btn-cerrar')) $('cex-btn-cerrar').classList.toggle('d-none', !a || !!cerrada);
        $('cex-acciones').classList.toggle('d-none', !a);
        $('cex-sin-guardar').classList.toggle('d-none', !!a);
        $('cex-planes-wrap').classList.toggle('d-none', !a);
        $('cex-prev').classList.add('d-none'); $('cex-aparte').classList.add('d-none');

        const t = d?.totales || {};
        const kpi = (lbl, v, cls) => `<span><span class="text-muted">${lbl}</span> <b class="${cls || ''}">${money(v)}</b></span>`;
        $('cex-kpis').innerHTML = a ? kpi('Aprobado', a.meta_total) + kpi('Asignado', t.asignado) + kpi('Anticipos', t.anticipos) + kpi('Emitido', t.emitido)
            + kpi('Cobrado', t.cobrado, 'text-success') + kpi('Saldo', t.saldo, 'text-danger') : '';

        $('cex-planes-body').innerHTML = planes.length ? planes.map(p => {
            const acc = [];
            if (!cerrada && ['pendiente', 'en_curso'].includes(p.estado)) {
                if (CFG.perm.crear && CFG.perm.actualizar && +p.por_emitir > 0) acc.push(`<li><a class="dropdown-item small" href="#" onclick="CEX.plan('liquidar', ${p.id});return false"><i class="bi bi-cash-coin me-1"></i>Liquidar el saldo</a></li>`);
                if (CFG.perm.actualizar && +p.cuotas_pendientes > 0) acc.push(`<li><a class="dropdown-item small" href="#" onclick="CEX.plan('plazo', ${p.id});return false"><i class="bi bi-calendar-range me-1"></i>Cambiar número de cuotas</a></li>`);
                if (CFG.perm.actualizar) acc.push(`<li><a class="dropdown-item small" href="#" onclick="CEX.plan('pagador', ${p.id});return false"><i class="bi bi-person-gear me-1"></i>Cambiar quién paga</a></li>`);
            }
            if (CFG.perm.crear && +p.anticipo_aplicado > 0 && !p.id_ingreso_abono) acc.push(`<li><a class="dropdown-item small text-warning" href="#" onclick="CEX.plan('abono', ${p.id});return false"><i class="bi bi-arrow-clockwise me-1"></i>Aplicar el anticipo (reintentar)</a></li>`);
            return `<tr>
                <td class="ps-2 fw-medium">${esc(p.unidad_codigo)} <small class="text-muted">${esc(p.unidad_nombre && p.unidad_nombre !== p.unidad_codigo ? p.unidad_nombre : '')}</small></td>
                <td>${esc(p.cliente_nombre || '')} <small class="text-muted">${p.pagador === 'arrendatario' ? '(arr.)' : ''}</small></td>
                <td class="text-end">${money(p.monto_total)}</td>
                <td class="text-end">${+p.anticipo_aplicado ? money(p.anticipo_aplicado) + (p.id_ingreso_abono ? '' : ' <i class="bi bi-exclamation-triangle text-warning" title="Anticipo aún no aplicado"></i>') : '—'}</td>
                <td class="text-end">${+p.cuota_valor ? money(p.cuota_valor) : '—'}</td>
                <td class="text-center">${p.cuotas_emitidas}/${p.plazo_elegido}</td>
                <td class="text-end">${money(p.emitido)}</td>
                <td class="text-end text-success">${money(p.cobrado)}</td>
                <td class="text-end fw-medium">${money(p.saldo)}</td>
                <td>${badge(ESTADO_PLAN[p.estado] || ['secondary', p.estado])}</td>
                <td class="pe-2 text-end">${acc.length ? `<div class="dropdown"><button class="btn btn-link btn-sm p-0" data-bs-toggle="dropdown" type="button"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end">${acc.join('')}</ul></div>` : ''}</td></tr>`;
        }).join('') : '<tr><td colspan="11" class="text-center text-muted py-3">Aún no hay planes. Pulse «Calcular reparto».</td></tr>';

        const cargos = d?.cargos || [];
        $('cex-cuotas-body').innerHTML = cargos.length ? cargos.map(c => {
            const acc = [];
            if (c.estado === 'pendiente' && c.subtipo !== 'cuota' && CFG.perm.crear) acc.push(`<button type="button" class="btn btn-link btn-sm p-0" title="Emitir su documento" onclick="CEX.cargo('emitir_cargo', ${c.id})"><i class="bi bi-send"></i></button>`);
            if (c.estado === 'emitido' && (c.documento_anulado === true || c.documento_anulado === 't') && CFG.perm.actualizar) acc.push(`<button type="button" class="btn btn-link btn-sm p-0 text-warning" title="Su documento está anulado: volver a pendiente" onclick="CEX.cargo('pendiente', ${c.id})"><i class="bi bi-arrow-counterclockwise"></i></button>`);
            const anulado = c.documento_anulado === true || c.documento_anulado === 't';
            return `<tr class="${anulado ? 'text-decoration-line-through text-muted' : ''}">
                <td class="ps-2">${mes(c.periodo)}</td><td>${esc(c.unidad_codigo)}</td>
                <td>${c.subtipo === 'cuota' ? `${c.numero_cuota}/${c.total_cuotas}` : `<i>${SUBTIPO[c.subtipo] || c.subtipo}</i>`}</td>
                <td class="text-end">${money(c.monto)}</td><td>${esc(c.cliente_nombre || '')}</td>
                <td>${c.donde_cobro === 'aparte' ? 'Aparte' : 'Con la alícuota'}</td>
                <td>${badge(ESTADO_CARGO[c.estado] || ['secondary', c.estado])}</td>
                <td>${c.documento_numero ? `${c.tipo_documento === 'factura' ? 'Fact.' : 'Rec.'} ${esc(c.documento_numero)}${anulado ? ' (anulado)' : ''}` : ''}</td>
                <td class="text-end">${c.estado === 'emitido' ? money(c.cobrado) : ''}</td>
                <td class="pe-2 text-end">${acc.join(' ')}</td></tr>`;
        }).join('') : '<tr><td colspan="10" class="text-center text-muted py-3">Sin cuotas.</td></tr>';
    }

    // ── Guardar / cerrar / eliminar ──
    function datos() {
        return {
            id: $('cex_id').value, nombre: $('cex_nombre').value, id_producto: $('cex_id_producto').value, acta: $('cex_acta').value,
            fecha_acta: $('cex_fecha_acta').value, meta_total: $('cex_meta_total').value, calculo: $('cex_calculo').value,
            plazos_permitidos: $('cex_plazos').value, plazo_defecto: $('cex_plazo_defecto').value, primer_periodo: $('cex_primer_periodo').value,
            donde_cobro: $('cex_donde_cobro').value, pagador_defecto: $('cex_pagador_defecto').value, tipo_comprobante: $('cex_tipo_comprobante').value,
            id_punto_emision: $('cex_id_punto_emision').value, id_forma_anticipo: $('cex_id_forma_anticipo').value, descripcion: $('cex_descripcion').value,
        };
    }
    function guardar() {
        unaVez('cex-btn-guardar', async () => {
            const res = await post('cuotaExtraGuardarAjax', datos());
            if (!res.ok) return errorForm(res.mensaje);
            // El id queda puesto antes de reactivar el botón: el siguiente clic edita, no crea otra.
            cargarFormulario(res.aporte); pintarDetalle(res);
            cargar(false);
            Swal.fire({ icon: 'success', title: 'Listo', text: res.mensaje, timer: 1800, showConfirmButton: false });
        });
    }
    async function cerrar() {
        const c = await Swal.fire({ icon: 'warning', title: '¿Cerrar esta cuota extraordinaria?', text: 'Las cuotas que faltan emitir se anulan. Lo emitido y lo cobrado se conservan. No se puede reabrir.', showCancelButton: true, confirmButtonText: 'Sí, cerrar', cancelButtonText: 'Cancelar' });
        if (!c.isConfirmed) return;
        unaVez('cex-btn-cerrar', async () => {
            const res = await post('cuotaExtraCerrarAjax', { id: $('cex_id').value });
            if (!res.ok) return aviso('error', 'No se pudo', res.mensaje);
            await abrir($('cex_id').value); cargar(false); aviso('success', 'Listo', res.mensaje);
        });
    }
    async function eliminar() {
        const c = await Swal.fire({ icon: 'warning', title: '¿Eliminar esta cuota extraordinaria?', text: 'Aún no tiene planes por inmueble.', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' });
        if (!c.isConfirmed) return;
        unaVez('cex-btn-eliminar', async () => {
            const res = await post('cuotaExtraEliminarAjax', { id: $('cex_id').value });
            if (!res.ok) return aviso('error', 'No se pudo', res.mensaje);
            bootstrap.Modal.getInstance($('modalCuotaExtra'))?.hide(); cargar(false); aviso('success', 'Listo', res.mensaje);
        });
    }

    // ── Plan por inmueble: vista previa y activación ──
    function filasPrev() {
        return (S.prev?.filas || []).map(f => {
            const tr = document.querySelector(`#cex-prev-body tr[data-u="${f.id}"]`);
            if (!tr) return { id_unidad: f.id };
            const monto = tr.querySelector('.cex-monto').value.trim();
            return { id_unidad: f.id, incluir: tr.querySelector('.cex-inc').checked ? '1' : '0', monto, manual: monto !== '' ? '1' : '0',
                     anticipo: tr.querySelector('.cex-ant').value || '0', plazo: tr.querySelector('.cex-plazo').value, pagador: tr.querySelector('.cex-pag').value };
        });
    }
    async function calcular() {
        const id = $('cex_id').value; if (!id) return;
        const res = await post('cuotaExtraPlanesPreviewAjax', { id, filas: JSON.stringify(S.prev ? filasPrev() : []) });
        if (!res.ok) return errorForm(res.mensaje);
        S.prev = res; S.tokenPlanes = S.tokenPlanes || (window.CMG_nuevoTokenGuardado ? CMG_nuevoTokenGuardado() : '');
        pintarPrev();
    }
    function pintarPrev() {
        const r = S.prev, t = r.totales, plazos = r.aporte.plazos_permitidos || [];
        $('cex-prev-tot').innerHTML = `<span>Aprobado <b>${money(t.meta)}</b></span>${t.ya_asignado ? `<span>Ya en planes <b>${money(t.ya_asignado)}</b></span>` : ''}
            <span>Inmuebles <b>${t.incluidos}</b></span><span>Asignado <b>${money(t.asignado)}</b></span><span>Anticipos <b>${money(t.anticipos)}</b></span>
            <span>A plazos <b>${money(t.saldo_plazos)}</b></span><span class="${Math.abs(t.diferencia) >= 0.01 ? 'text-danger fw-bold' : 'text-success'}">Diferencia ${money(t.diferencia)}</span>`;
        $('cex-prev-body').innerHTML = r.filas.length ? r.filas.map(f => `<tr data-u="${f.id}" class="${f.incluir ? '' : 'text-muted'} ${f.bloquea ? 'table-warning' : ''}">
            <td class="ps-2"><input type="checkbox" class="form-check-input cex-inc" ${f.incluir ? 'checked' : ''} onchange="CEX.recalcular()"></td>
            <td class="fw-medium">${esc(f.codigo)} <small class="text-muted">${esc(f.cliente_nombre || '')}</small></td>
            <td class="text-end">${f.alicuota_pct !== null && f.alicuota_pct !== undefined ? (+f.alicuota_pct).toFixed(4) : ''}</td>
            <td class="p-0"><select class="form-select cex-pag" onchange="CEX.recalcular()"><option value="propietario" ${f.pagador === 'propietario' ? 'selected' : ''}>Propietario</option>${f.id_arrendatario ? `<option value="arrendatario" ${f.pagador === 'arrendatario' ? 'selected' : ''}>Arrendatario</option>` : ''}</select></td>
            <td class="p-0"><input type="number" step="0.01" class="form-control text-end cex-monto" placeholder="${(+f.monto).toFixed(2)}" value="${f.manual ? (+f.monto).toFixed(2) : ''}" onchange="CEX.recalcular()"></td>
            <td class="p-0"><input type="number" step="0.01" min="0" class="form-control text-end cex-ant" value="${+f.anticipo ? (+f.anticipo).toFixed(2) : ''}" placeholder="0.00" onchange="CEX.recalcular()"></td>
            <td class="text-end">${+f.anticipo_disponible ? `<a href="#" title="Usar todo" onclick="CEX.usarAnticipo(${f.id}, ${f.anticipo_disponible});return false">${money(f.anticipo_disponible)}</a>` : '<span class="text-muted">—</span>'}</td>
            <td class="p-0"><select class="form-select cex-plazo" onchange="CEX.recalcular()">${plazos.map(p => `<option value="${p}" ${+p === +f.plazo ? 'selected' : ''}>${p}</option>`).join('')}</select></td>
            <td class="text-end">${f.incluir && +f.saldo_plazos ? `${money(f.cuota)}${f.cuota_ultima !== f.cuota ? ` <small class="text-muted" title="Última cuota">/ ${money(f.cuota_ultima)}</small>` : ''}` : '—'}</td>
            <td class="pe-2 small ${f.bloquea ? 'text-danger' : 'text-muted'}">${esc(f.nota || '')}</td></tr>`).join('')
            : '<tr><td colspan="10" class="text-center text-muted py-3">Todos los inmuebles activos ya tienen plan.</td></tr>';
        $('cex-prev-todos').checked = r.filas.every(f => f.incluir);
        $('cex-prev').classList.remove('d-none');
    }
    let tRecalc;
    function recalcular() { clearTimeout(tRecalc); tRecalc = setTimeout(calcular, 250); }
    function prevTodos(on) { document.querySelectorAll('#cex-prev-body .cex-inc').forEach(c => { c.checked = on; }); recalcular(); }
    function usarAnticipo(idU, disp) { const tr = document.querySelector(`#cex-prev-body tr[data-u="${idU}"]`); if (tr) { tr.querySelector('.cex-ant').value = (+disp).toFixed(2); recalcular(); } }
    async function activar() {
        if (!S.prev) return;
        const t = S.prev.totales;
        if (Math.abs(t.diferencia) >= 0.01) return aviso('warning', 'No cuadra', `Lo asignado no iguala el monto aprobado (diferencia ${money(t.diferencia)}).`);
        const c = await Swal.fire({ icon: 'question', title: `¿Activar ${t.incluidos} plan(es)?`, html: `Se crean todas sus cuotas.${t.anticipos > 0 ? `<br>Se aplican <b>${money(t.anticipos)}</b> de anticipos con un documento de abono inicial por inmueble.` : ''}`, showCancelButton: true, confirmButtonText: 'Sí, activar', cancelButtonText: 'Cancelar' });
        if (!c.isConfirmed) return;
        unaVez('cex-btn-activar', async () => {
            const res = await post('cuotaExtraActivarAjax', { id: $('cex_id').value, filas: JSON.stringify(filasPrev()), token_guardado: S.tokenPlanes || '' });
            if (!res.ok) return errorForm(res.mensaje);
            S.prev = null; S.tokenPlanes = null;
            pintarDetalle(res); cargar(false);
            if ((res.avisos || []).length) return Swal.fire({ icon: 'warning', title: res.mensaje, html: res.avisos.map(esc).join('<br>') });
            aviso('success', 'Listo', res.mensaje);
        });
    }

    // ── Acciones sobre un plan / una cuota ──
    async function plan(accion, idPlan) {
        const p = (S.actual?.planes || []).find(x => +x.id === +idPlan); if (!p) return;
        const datos = { id: $('cex_id').value, id_plan: idPlan, accion };
        if (accion === 'liquidar') {
            const c = await Swal.fire({ icon: 'question', title: `¿Liquidar el saldo de ${p.unidad_codigo}?`, html: `Se emite un documento por <b>${money(p.por_emitir)}</b> (las ${p.cuotas_pendientes} cuota(s) que faltan) a ${esc(p.cliente_nombre)} y el plan se cierra.`, showCancelButton: true, confirmButtonText: 'Sí, liquidar', cancelButtonText: 'Cancelar' });
            if (!c.isConfirmed) return;
        } else if (accion === 'plazo') {
            const r = await Swal.fire({ title: `Cuotas que faltan de ${p.unidad_codigo}`, text: `Hoy faltan ${p.cuotas_pendientes} cuota(s) por ${money(p.por_emitir)}. ¿En cuántas cuotas se cobra lo que falta?`, input: 'number', inputValue: p.cuotas_pendientes, inputAttributes: { min: 1, max: 120 }, showCancelButton: true, confirmButtonText: 'Cambiar', cancelButtonText: 'Cancelar' });
            if (!r.isConfirmed) return; datos.cuotas = r.value;
        } else if (accion === 'pagador') {
            const r = await Swal.fire({ title: `¿Quién paga ${p.unidad_codigo}?`, text: 'Aplica a las cuotas que faltan emitir.', input: 'select', inputOptions: { propietario: 'El propietario', arrendatario: 'El arrendatario' }, inputValue: p.pagador, showCancelButton: true, confirmButtonText: 'Cambiar', cancelButtonText: 'Cancelar' });
            if (!r.isConfirmed) return; datos.pagador = r.value;
        }
        if (S.ocupado) return; S.ocupado = true;
        try {
            const res = await post('cuotaExtraPlanAccionAjax', datos);
            if (!res.ok) return aviso('error', 'No se pudo', res.mensaje);
            pintarDetalle(res); cargar(false); aviso('success', 'Listo', res.mensaje);
        } finally { S.ocupado = false; }
    }
    async function cargo(accion, idCargo) {
        if (accion === 'pendiente') {
            const c = await Swal.fire({ icon: 'question', title: '¿Volver a pendiente esta cuota?', text: 'Su documento está anulado: la cuota saldrá en la próxima emisión.', showCancelButton: true, confirmButtonText: 'Sí', cancelButtonText: 'No' });
            if (!c.isConfirmed) return;
        }
        if (S.ocupado) return; S.ocupado = true;
        try {
            const res = await post('cuotaExtraPlanAccionAjax', { id: $('cex_id').value, accion, id_cargo: idCargo });
            if (!res.ok) return aviso('error', 'No se pudo', res.mensaje);
            pintarDetalle(res); cargar(false); aviso('success', 'Listo', res.mensaje);
        } finally { S.ocupado = false; }
    }

    // ── Emisión aparte ──
    async function aparteVista() {
        const res = await post('cuotaExtraApartePreviewAjax', { id: $('cex_id').value, periodo: $('cex_emi_periodo').value });
        if (!res.ok) return errorForm(res.mensaje);
        S.aparte = res; S.tokenEmision = window.CMG_nuevoTokenGuardado ? CMG_nuevoTokenGuardado() : '';
        $('cex-aparte-body').innerHTML = res.filas.length ? res.filas.map(f => `<tr>
            <td class="ps-2"><input type="checkbox" class="form-check-input cex-ap" value="${esc(f.clave)}" checked></td>
            <td>${esc(f.cliente)} <small class="text-muted">${esc(f.identificacion)}</small></td><td>${esc(f.inmueble)}</td>
            <td>${esc(f.cuotas)}</td><td class="text-end">${money(f.valor)}</td><td class="small">${esc(f.email || '—')}</td>
            <td class="pe-2 small text-muted">${esc(f.nota || '')}</td></tr>`).join('')
            : '<tr><td colspan="7" class="text-center text-muted py-3">No hay cuotas por emitir aparte hasta ese mes.</td></tr>';
        $('cex-aparte').classList.remove('d-none');
        if ($('cex-btn-emitir')) $('cex-btn-emitir').disabled = !res.filas.length;
    }
    function aparteTodos(on) { document.querySelectorAll('#cex-aparte-body .cex-ap').forEach(c => { c.checked = on; }); }
    async function aparteEmitir() {
        const sel = Array.from(document.querySelectorAll('#cex-aparte-body .cex-ap:checked')).map(c => c.value);
        if (!sel.length) return aviso('info', 'Nada marcado', 'Marque al menos un documento.');
        const c = await Swal.fire({ icon: 'question', title: `¿Emitir ${sel.length} documento(s)?`, text: 'Se generan en segundo plano; puede seguir trabajando.', showCancelButton: true, confirmButtonText: 'Sí, emitir', cancelButtonText: 'Cancelar' });
        if (!c.isConfirmed) return;
        unaVez('cex-btn-emitir', async () => {
            const res = await post('cuotaExtraEmitirAparteAjax', { id: $('cex_id').value, periodo: $('cex_emi_periodo').value, seleccion: sel.join(','),
                enviar_correo: $('cex_emi_correo').checked ? '1' : '0', token_guardado: S.tokenEmision || '' });
            if (!res.ok) return errorForm(res.mensaje);
            $('cex-aparte').classList.add('d-none'); S.aparte = null;
            aviso('success', 'Emisión creada', res.mensaje + ' Los documentos se ven en Recibos o Facturas de Venta; el avance, en el historial de Emisiones.');
            setTimeout(async () => { const d = await pedir(`${CFG.url}/cuotaExtraGetAjax?id=${$('cex_id').value}`); if (d.ok) pintarDetalle(d); }, 4000);
        });
    }

    function exportar(formato) {
        const id = $('cex_id').value; if (!id) return;
        const url = `${CFG.url}/cuotaExtraExport?id=${id}&formato=${formato}`;
        if (window.CMG_descargar) CMG_descargar(url); else window.location.href = url;
    }

    // ── Venta del inmueble / cambio de arrendatario (lo llama la ficha del inmueble al guardar) ──
    async function revisarCambioPersona(idUnidad, desde) {
        let res;
        try { res = await pedir(`${CFG.url}/cuotaExtraCambioPersonaAjax?id_unidad=${idUnidad}`); } catch (e) { return; }
        if (!res.ok || !(res.planes || []).length) return;
        const decisiones = [];
        for (const p of res.planes) {
            const r = await Swal.fire({
                icon: 'question', title: `Cuota extraordinaria «${p.aporte_nombre}»`,
                html: `Cambió quién paga este inmueble. Faltan <b>${p.cuotas_pendientes}</b> cuota(s) por <b>${money(p.por_emitir)}</b> que hoy se cobran a <b>${esc(p.cliente_nombre)}</b>.<br><br>¿Qué hacemos con esa deuda?`,
                showDenyButton: true, showCancelButton: true, confirmButtonText: 'Sigue con el inmueble (nuevo pagador)', denyButtonText: `Liquidarla a ${esc(p.cliente_nombre)}`, cancelButtonText: 'Decidir después',
            });
            if (r.isConfirmed) decisiones.push({ id_plan: p.id, accion: 'seguir' });
            else if (r.isDenied) decisiones.push({ id_plan: p.id, accion: 'liquidar' });
        }
        if (!decisiones.length) return;
        const fd = { id_unidad: idUnidad, desde: desde || '', decisiones: JSON.stringify(decisiones) };
        const r = await post('cuotaExtraCambioPersonaAjax', fd);
        aviso(r.ok ? 'success' : 'error', r.ok ? 'Cuotas extraordinarias' : 'No se pudo', r.mensaje);
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!$('cex-body')) return;
        chipProducto();
        $('cex_plazos')?.addEventListener('change', () => llenarPlazoDefecto());
        $('cex-buscar')?.addEventListener('input', () => { clearTimeout(tBuscar); tBuscar = setTimeout(() => cargar(false), 300); });
        document.querySelectorAll('#cex-tabla th[data-sort]').forEach(th => th.addEventListener('click', () => ordenar(th.dataset.sort)));
        // Carga perezosa: al abrir la pestaña por primera vez.
        $('cfg-tab-cuotas-extra-btn')?.addEventListener('shown.bs.tab', () => { if (!S.cargado) { S.cargado = true; cargar(true); } });
        if (document.querySelector('#pane-cfg-cuotas-extra.active')) { S.cargado = true; cargar(true); }
    });

    return { nuevo, abrir, guardar, cerrar, eliminar, calcular, recalcular, prevTodos, usarAnticipo, activar, plan, cargo,
             aparteVista, aparteTodos, aparteEmitir, exportar, revisarCambioPersona };
})();
