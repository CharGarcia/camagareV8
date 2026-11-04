/**
 * Órdenes de producción — listado, nueva orden / producción rápida y modal de la orden
 * (consumir, cerrar, movimientos, costos, trazabilidad y asiento).
 *
 * Toda acción que escribe usa bandera "en curso" + token de guardado único: un doble clic o
 * un reintento tras "Error de conexión" no descuenta ni produce dos veces (CLAUDE.md §8).
 */
(function () {
    'use strict';

    const URL  = window.POR_URL_BASE;
    const PERM = window.POR_PERM || {};
    const CFG  = window.POR_CONFIG || {};
    const ROLES = { insumo: 'Insumo', principal: 'Principal', coproducto: 'Coproducto', subproducto: 'Subproducto', desperdicio: 'Desperdicio' };
    const TIPOS_REC = { mo: 'Mano de obra', maquina: 'Máquina', cif: 'Costos indirectos' };
    const BASES_REC = { unidad: 'Por unidad producida', lote: 'Fijo por orden', hora: 'Por hora', pct_mp: '% de la materia prima' };

    let enCurso = false;
    let tokenNueva = '';
    let tokenConsumo = '';
    let tokenCierre = '';
    let orden = null;          // orden abierta
    let consumoExtra = [];     // insumos fuera de fórmula agregados en la pestaña Consumir
    let recursos = [];
    let seq = 0;
    let previa = null;         // vista previa de la nueva orden
    window.POR_pagina = 1;

    // ── Utilidades ───────────────────────────────────────────────────────────

    function el(id) { return document.getElementById(id); }
    function esc(t) { return String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
    function num(v) { const n = parseFloat(String(v ?? '').replace(',', '.')); return isNaN(n) ? 0 : n; }
    function cant(v) { return num(v).toLocaleString('es-EC', { maximumFractionDigits: 4 }); }
    function money(v) { return '$' + num(v).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function fecha(v) { if (!v) return ''; const p = String(v).slice(0, 10).split('-'); return p.length === 3 ? `${p[2]}-${p[1]}-${p[0]}` : v; }
    function hoy() { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; }
    function token() { return typeof window.CMG_nuevoTokenGuardado === 'function' ? window.CMG_nuevoTokenGuardado() : (Date.now() + '-' + Math.random().toString(36).slice(2)); }
    function mensajeDe(res) { return res.mensaje || res.error || 'Error desconocido.'; }
    function aviso(icono, titulo, texto) {
        if (typeof Swal === 'undefined') { alert(titulo + '\n' + (texto || '')); return Promise.resolve(); }
        return Swal.fire({ icon: icono, title: titulo, text: texto || '', confirmButtonColor: '#0d6efd' });
    }
    function confirmar(titulo, html, boton, color) {
        if (typeof Swal === 'undefined') return Promise.resolve(confirm(titulo));
        return Swal.fire({ icon: 'question', title: titulo, html: html || '', showCancelButton: true, confirmButtonText: boton || 'Sí',
            cancelButtonText: 'Cancelar', confirmButtonColor: color || '#0d6efd' }).then(r => r.isConfirmed);
    }
    function post(accion, datos) {
        const fd = new FormData();
        Object.entries(datos || {}).forEach(([k, v]) => fd.append(k, v));
        return fetch(`${URL}/${accion}`, { method: 'POST', body: fd }).then(r => r.json());
    }
    function boton(id, texto) {
        const b = el(id);
        b.disabled = true;
        b.dataset.html = b.dataset.html || b.innerHTML;
        b.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> ${texto}`;
        return () => { b.disabled = false; b.innerHTML = b.dataset.html; };
    }
    function badgeRol(rol) {
        const c = { insumo: 'secondary', principal: 'primary', coproducto: 'info', subproducto: 'success', desperdicio: 'dark' }[rol] || 'secondary';
        return `<span class="badge bg-${c} bg-opacity-10 text-${c} border border-${c} border-opacity-25">${ROLES[rol] || rol}</span>`;
    }
    function nombreLinea(l) { return l.id_producto ? `${l.producto_codigo} - ${l.producto_nombre}` : (l.descripcion || '—'); }

    function typeahead(input, url, render, onPick) {
        const dd = document.createElement('div');
        dd.className = 'list-group position-absolute shadow por-dd d-none';
        dd.style.minWidth = '340px';
        input.parentElement.style.position = 'relative';
        input.parentElement.appendChild(dd);
        let timer = null;
        const cerrar = () => { dd.classList.add('d-none'); dd.innerHTML = ''; };
        input.addEventListener('keydown', e => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && input.dataset.elegido === '1') {
                e.preventDefault();
                input.value = '';
                input.dataset.elegido = '';
                cerrar();
                onPick(null);
            }
        });
        input.addEventListener('input', () => {
            if (input.dataset.elegido === '1') { input.dataset.elegido = ''; onPick(null); }
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 2) { cerrar(); return; }
            timer = setTimeout(() => {
                fetch(`${url}?q=${encodeURIComponent(q)}`).then(r => r.json()).then(res => {
                    const data = res.data || [];
                    dd._data = data;
                    dd.innerHTML = data.length
                        ? data.map((x, i) => `<button type="button" class="list-group-item list-group-item-action small py-1" data-i="${i}">${render(x)}</button>`).join('')
                        : '<div class="list-group-item small text-muted">Sin resultados.</div>';
                    dd.classList.remove('d-none');
                });
            }, 250);
        });
        dd.addEventListener('mousedown', e => {
            const b = e.target.closest('[data-i]');
            if (!b) return;
            e.preventDefault();
            const x = dd._data[+b.dataset.i];
            cerrar();
            onPick(x);
        });
        input.addEventListener('blur', () => setTimeout(cerrar, 150));
    }

    // ── Listado ──────────────────────────────────────────────────────────────

    function params() {
        return new URLSearchParams({
            b: el('por-buscar')?.value || '', desde: el('por-desde')?.value || '', hasta: el('por-hasta')?.value || '',
            estado: el('por-estado')?.value || '', tipo: el('por-tipo')?.value || '',
        });
    }

    window.POR_buscar = function (p) {
        if (p < 1 || (p > (window.POR_PAGES || 1) && p !== 1)) return;
        window.POR_pagina = p || 1;
        const q = params();
        q.set('page', String(window.POR_pagina));
        const tbody = el('por-tbody');
        tbody.classList.add('opacity-50');
        fetch(`${URL}/search-ajax?${q}`).then(r => r.json()).then(res => {
            if (!res.ok) return;
            tbody.innerHTML = res.rows;
            el('por-info').textContent = res.info;
            window.POR_PAGES = res.pages;
            const b = el('por-paginacion').querySelectorAll('button');
            b[0].disabled = res.page <= 1;
            b[1].disabled = res.page >= res.pages;
            const r = res.resumen || {};
            el('por-stat-ordenes').textContent = r.ordenes || 0;
            el('por-stat-abiertas').textContent = r.abiertas || 0;
            el('por-stat-cerradas').textContent = r.cerradas || 0;
            el('por-stat-costo').textContent = money(r.costo || 0);
            el('por-stat-merma').textContent = money(r.merma || 0);
            if (res.pdf_url) el('por-pdf').href = res.pdf_url;
            if (res.excel_url) el('por-excel').href = res.excel_url;
            if (sorter) sorter.refreshIcons();
        }).catch(e => console.error('POR_buscar', e)).finally(() => tbody.classList.remove('opacity-50'));
    };

    window.POR_limpiar = function () {
        ['por-buscar', 'por-desde', 'por-hasta', 'por-estado', 'por-tipo'].forEach(id => { if (el(id)) el(id).value = ''; });
        window.POR_buscar(1);
    };

    el('por-buscar')?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); window.POR_buscar(1); } });

    const sorter = window.CMG_initSort ? window.CMG_initSort(window.POR_MODULO, (col, dir, sorts) => {
        window.currentSorts = sorts;
        window.POR_buscar(1);
    }, { sorts: window.currentSorts, multi: true }) : null;

    // ── Nueva orden / producción rápida ──────────────────────────────────────

    let modalNueva = null;
    function getModalNueva() { if (!modalNueva) modalNueva = new bootstrap.Modal(el('modalNuevaOrden')); return modalNueva; }

    if (el('pon-formula-txt')) {
        typeahead(el('pon-formula-txt'), `${URL}/formulas-ajax`,
            f => `<code>${esc(f.codigo)}</code> v${f.version} · ${esc(f.nombre)} <span class="text-muted">→ ${esc(f.producto_nombre)}</span>`,
            f => {
                if (!f) { el('pon-formula').value = ''; previa = null; pintarPrevia(); return; }
                el('pon-formula-txt').value = `${f.codigo} v${f.version} · ${f.nombre}`;
                el('pon-formula-txt').dataset.elegido = '1';
                el('pon-formula').value = f.id;
                el('pon-medida').textContent = f.medida_nombre || 'u';
                if (!num(el('pon-cantidad').value)) el('pon-cantidad').value = num(f.cantidad_base);
                cargarPrevia();
            });
        ['pon-cantidad', 'pon-bodega-insumos'].forEach(id => el(id).addEventListener('change', cargarPrevia));
    }

    window.POR_nueva = function (tipo) {
        if (!PERM.crear) { aviso('warning', 'Sin permiso', 'No tiene permiso para crear órdenes.'); return; }
        const rapida = tipo === 'rapida';
        el('pon-tipo').value = rapida ? 'rapida' : 'orden';
        el('pon-titulo').textContent = rapida ? 'Producción rápida' : 'Nueva orden de producción';
        el('pon-icono').className = rapida ? 'bi bi-lightning-charge text-warning me-2' : 'bi bi-gear-wide-connected text-primary me-2';
        el('pon-ayuda').innerHTML = rapida
            ? '<i class="bi bi-lightning-charge text-warning me-1"></i>En un solo paso: se consumen los insumos de la fórmula y lo producido entra al inventario con su costo real.'
            : '<i class="bi bi-info-circle text-primary me-1"></i>La orden queda planificada. Después registra los consumos (parciales, por peso, con lote) y la cierra con lo que realmente obtuvo.';
        el('pon-btn-guardar').innerHTML = rapida ? '<i class="bi bi-lightning-charge me-1"></i> Producir' : '<i class="bi bi-check-lg me-1"></i> Crear orden';
        el('pon-btn-guardar').dataset.html = el('pon-btn-guardar').innerHTML;
        el('pon-btn-guardar').disabled = false;
        document.querySelectorAll('.pon-col-real').forEach(x => x.classList.toggle('d-none', !rapida));
        ['pon-formula-txt', 'pon-formula', 'pon-cantidad', 'pon-lote', 'pon-caducidad', 'pon-responsable', 'pon-observaciones'].forEach(id => { el(id).value = ''; });
        el('pon-formula-txt').dataset.elegido = '';
        el('pon-merma').value = 0;
        el('pon-fecha').value = hoy();
        el('pon-bodega-insumos').value = CFG.id_bodega_insumos || '';
        el('pon-bodega-destino').value = (CFG.usar_cuarentena && CFG.id_bodega_cuarentena) ? CFG.id_bodega_cuarentena : (CFG.id_bodega_terminado || '');
        el('pon-medida').textContent = 'u';
        previa = null;
        pintarPrevia();
        tokenNueva = token();
        getModalNueva().show();
    };

    function cargarPrevia() {
        const idF = el('pon-formula').value, c = num(el('pon-cantidad').value);
        if (!idF || c <= 0) { previa = null; pintarPrevia(); return; }
        const q = new URLSearchParams({ id_formula: idF, cantidad: String(c), id_bodega_insumos: el('pon-bodega-insumos').value || '0' });
        fetch(`${URL}/vista-previa-ajax?${q}`).then(r => r.json()).then(res => {
            if (!res.ok) { aviso('error', 'Error', mensajeDe(res)); return; }
            previa = res.data;
            if (previa.caducidad_sugerida && !el('pon-caducidad').value) el('pon-caducidad').value = previa.caducidad_sugerida;
            pintarPrevia();
        });
    }

    function pintarPrevia() {
        const rapida = el('pon-tipo').value === 'rapida';
        if (!previa) {
            el('pon-tbody').innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Elija la fórmula y la cantidad.</td></tr>';
            el('pon-faltantes').innerHTML = '';
            return;
        }
        el('pon-tbody').innerHTML = previa.lineas.map(l => {
            const insumo = l.rol === 'insumo';
            const falta = insumo && num(l.falta) > 0;
            return `<tr class="${falta ? 'table-danger' : ''}">
                <td>${badgeRol(l.rol)}</td><td>${esc(nombreLinea(l))}</td><td class="small text-muted">${esc(l.medida_nombre)}</td>
                <td class="text-end">${cant(l.cantidad_teorica)}</td>
                <td class="text-end">${insumo ? cant(l.stock) + (falta ? ` <span class="text-danger small">(faltan ${cant(l.falta)})</span>` : '') : ''}</td>
                <td class="p-0 pon-col-real ${rapida ? '' : 'd-none'}">${!insumo
                    ? `<input type="number" step="any" min="0" class="text-end pon-real" data-orden="${l.orden}" value="${num(l.cantidad_teorica)}">`
                    : ''}</td>
            </tr>`;
        }).join('');
        el('pon-faltantes').innerHTML = previa.faltantes
            ? `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${previa.faltantes} insumo(s) sin stock suficiente en la bodega de insumos.</span>`
            : '<span class="text-success"><i class="bi bi-check2-circle me-1"></i>Hay stock de todos los insumos.</span>';
    }

    window.POR_crear = function () {
        if (enCurso) return;
        const rapida = el('pon-tipo').value === 'rapida';
        if (!el('pon-formula').value) { aviso('warning', 'Falta la fórmula', 'Elija una fórmula vigente.'); return; }
        if (num(el('pon-cantidad').value) <= 0) { aviso('warning', 'Falta la cantidad', 'Indique cuánto va a producir.'); return; }
        if (!el('pon-bodega-insumos').value || !el('pon-bodega-destino').value) { aviso('warning', 'Faltan las bodegas', 'Elija la bodega de insumos y la de destino.'); return; }

        enCurso = true;
        const listo = boton('pon-btn-guardar', rapida ? 'Produciendo…' : 'Creando…');
        const datos = {
            id_formula: el('pon-formula').value, cantidad_planificada: el('pon-cantidad').value, fecha_inicio: el('pon-fecha').value,
            id_bodega_insumos: el('pon-bodega-insumos').value, id_bodega_destino: el('pon-bodega-destino').value,
            lote: el('pon-lote').value, fecha_caducidad: el('pon-caducidad').value, responsable: el('pon-responsable').value,
            observaciones: el('pon-observaciones').value, token_guardado: tokenNueva,
        };
        if (rapida) {
            datos.salidas = JSON.stringify([...document.querySelectorAll('#pon-tbody .pon-real')].map(i => ({ orden: +i.dataset.orden, cantidad: i.value })));
            datos.merma = el('pon-merma').value || '0';
        }
        let creada = false;
        post(rapida ? 'rapida-ajax' : 'crear-ajax', datos)
            .then(res => {
                if (!res.ok) { aviso('error', 'No se registró', mensajeDe(res)); return; }
                creada = true;
                getModalNueva().hide();
                window.POR_buscar(1);
                aviso('success', rapida ? 'Producción registrada' : 'Orden creada', res.mensaje);
                return window.POR_abrir(res.id);
            })
            .catch(e => { console.error(e); aviso('error', 'Error', 'No se recibió respuesta del servidor. Vuelva a pulsar el botón: si ya se había registrado, se abrirá la misma, sin consumir otra vez.'); })
            .finally(() => { enCurso = false; if (!creada) listo(); });
    };

    // ── Modal de la orden ────────────────────────────────────────────────────

    let modalOrden = null;
    function getModalOrden() { if (!modalOrden) modalOrden = new bootstrap.Modal(el('modalOrden')); return modalOrden; }

    if (el('por-extra-txt')) {
        typeahead(el('por-extra-txt'), `${URL}/buscar-insumos-ajax`,
            p => `<code>${esc(p.codigo)}</code> ${esc(p.nombre)} <span class="text-muted">· ${esc(p.medida_nombre || '')}</span>`,
            p => {
                if (!p) return;
                el('por-extra-txt').value = '';
                if (consumoExtra.some(x => x.id_producto === +p.id)) return;
                consumoExtra.push({ uid: ++seq, id_producto: +p.id, producto: `${p.codigo} - ${p.nombre}`, medida: p.medida_nombre || '' });
                pintarConsumir();
            });
    }

    window.POR_abrir = function (id) {
        return fetch(`${URL}/get-orden-ajax?id=${id}`).then(r => r.json()).then(res => {
            if (!res.ok) { aviso('error', 'Error', mensajeDe(res)); return; }
            cargarOrden(res.data);
            getModalOrden().show();
        }).catch(e => { console.error(e); aviso('error', 'Error', 'No se pudo abrir la orden.'); });
    };

    function cargarOrden(o) {
        orden = o;
        consumoExtra = [];
        tokenConsumo = token();
        tokenCierre = token();
        const abierta = o.estado === 'liberada' || o.estado === 'en_proceso';
        el('por-id').value = o.id;
        el('por-titulo').textContent = `${o.numero}${o.tipo === 'rapida' ? ' · Producción rápida' : ''}`;
        el('por-badge').innerHTML = o.badge_estado;

        const dato = (l, v) => `<div class="col-md-3"><span class="text-muted">${l}:</span> <b>${v}</b></div>`;
        el('por-cabecera').innerHTML = [
            dato('Producto', esc(`${o.producto_codigo} - ${o.producto_nombre}`)),
            dato('Fórmula', esc(`${o.formula_codigo || ''} v${o.version_formula}`)),
            dato('Planificado', `${cant(o.cantidad_planificada)} ${esc(o.medida_nombre || '')}`),
            dato('Producido', o.estado === 'cerrada' ? `${cant(o.cantidad_producida)} ${esc(o.medida_nombre || '')}` : '—'),
            dato('Lote', esc(o.lote || '')), dato('Caducidad', fecha(o.fecha_caducidad) || '—'),
            dato('Fecha', fecha(o.fecha_inicio)), dato('Cierre', fecha(o.fecha_fin) || '—'),
            dato('Insumos de', esc(o.bodega_insumos_nombre || '')), dato('Entra a', esc(o.bodega_destino_nombre || '')),
            dato('Responsable', esc(o.responsable || '—')),
        ].join('');

        // Plan
        el('por-tbody-plan').innerHTML = o.lineas.map(l => {
            const insumo = l.rol === 'insumo';
            const real = num(l.cantidad_real), teo = num(l.cantidad_teorica);
            const mostrarReal = insumo || o.estado === 'cerrada';
            const dif = mostrarReal ? real - teo : 0;
            return `<tr>
                <td>${badgeRol(l.rol)}${l.fuera_formula ? ' <span class="small text-muted">fuera de fórmula</span>' : ''}</td>
                <td>${esc(nombreLinea(l))}</td><td class="small text-muted">${esc(l.medida_nombre || '')}</td>
                <td class="text-end">${cant(teo)}</td><td class="text-end">${mostrarReal ? cant(real) : '—'}</td>
                <td class="text-end ${dif > 0 ? 'text-danger' : (dif < 0 ? 'text-success' : '')}">${mostrarReal ? cant(dif) : ''}</td>
                <td class="text-end">${insumo ? cant((o.stocks || {})[l.id_producto] || 0) : ''}</td>
                <td class="text-end">${insumo || o.estado === 'cerrada' ? money(l.costo_real) : ''}</td>
            </tr>`;
        }).join('');

        // Pestañas de acción solo con la orden abierta y permiso de actualizar.
        document.querySelectorAll('#modalOrden .por-solo-abierta').forEach(t => t.classList.toggle('d-none', !(abierta && PERM.actualizar)));
        const primera = document.querySelector('#modalOrden .nav-link[data-bs-target="#pane-por-plan"]');
        if (primera) bootstrap.Tab.getOrCreateInstance(primera).show();

        el('por-fecha-consumo').value = hoy();
        el('por-fecha-cierre').value = hoy();
        el('por-merma').value = 0;
        const pendiente = o.lineas.some(l => l.rol === 'insumo' && num(l.cantidad_teorica) - num(l.cantidad_real) > 0.000001);
        el('por-consumir-pendiente').checked = pendiente && !o.lineas.some(l => l.rol === 'insumo' && num(l.cantidad_real) > 0);
        recursos = (o.recursos || []).map(r => ({ uid: ++seq, tipo: r.tipo, descripcion: r.descripcion || '', base: r.base, cantidad: num(r.cantidad), tarifa: num(r.tarifa) }));
        pintarConsumir();
        pintarCerrar();
        pintarRecursos();
        pintarMovimientos();
        pintarCostos();
        pintarTraza();
        pintarAsientos();

        el('por-btn-anular').classList.toggle('d-none', o.estado === 'anulada' || !PERM.eliminar);
        const sinMovs = !(o.movimientos || []).length;
        el('por-btn-eliminar').classList.toggle('d-none', !PERM.eliminar || !((o.estado === 'liberada' && sinMovs) || o.estado === 'anulada'));
        el('por-btn-asiento').classList.toggle('d-none', !(o.asiento_aviso && PERM.actualizar && o.estado !== 'anulada'));
        el('por-aviso-asiento').classList.toggle('d-none', !o.asiento_aviso);
        el('por-aviso-asiento').innerHTML = o.asiento_aviso ? `<i class="bi bi-exclamation-triangle me-1"></i>Asiento pendiente: ${esc(o.asiento_aviso)}` : '';
        el('por-auditoria').innerHTML = [o.creado_por ? `Creó: ${esc(o.creado_por)}` : '', o.modificado_por ? `Última acción: ${esc(o.modificado_por)}` : ''].filter(Boolean).join(' · ');
    }

    function pintarConsumir() {
        if (!orden) return;
        const filas = orden.lineas.filter(l => l.rol === 'insumo').map(l => ({
            key: 'l' + l.id, id_linea: l.id, id_producto: l.id_producto, nombre: nombreLinea(l), medida: l.medida_nombre || '',
            pendiente: Math.max(0, num(l.cantidad_teorica) - num(l.cantidad_real)), exige: !!l.exige_lote && l.exige_lote !== 'f',
        })).concat(consumoExtra.map(x => ({ key: 'x' + x.uid, id_linea: 0, id_producto: x.id_producto, nombre: x.producto, medida: x.medida, pendiente: 0, extra: x.uid })));

        el('por-tbody-consumir').innerHTML = filas.length ? filas.map(f => `
            <tr data-key="${f.key}" data-linea="${f.id_linea}" data-producto="${f.id_producto}">
                <td>${esc(f.nombre)} <span class="small text-muted">${esc(f.medida)}</span>${f.exige || CFG.exigir_lote_insumos ? ' <i class="bi bi-upc text-primary" title="Exige lote"></i>' : ''}${f.extra ? ' <span class="small text-muted">fuera de fórmula</span>' : ''}</td>
                <td class="text-end">${cant(f.pendiente)}</td>
                <td class="text-center"><input type="checkbox" class="form-check-input m-0 por-peso" style="width:auto;height:auto;"></td>
                <td class="p-0"><input type="number" step="any" min="0" class="text-end por-cant" value="${f.pendiente > 0 ? +f.pendiente.toFixed(6) : ''}"></td>
                <td class="p-0"><input type="number" step="any" min="0" class="text-end por-pi" disabled></td>
                <td class="p-0"><input type="number" step="any" min="0" class="text-end por-pf" disabled></td>
                <td class="p-0"><select class="por-lote"><option value="">Automático (vence primero)</option></select></td>
                <td class="text-center">${f.extra ? `<button type="button" class="btn btn-link btn-sm text-danger p-0" onclick="window.POR_quitarExtra(${f.extra})"><i class="bi bi-x-lg"></i></button>` : ''}</td>
            </tr>`).join('') : '<tr><td colspan="8" class="text-center text-muted py-3">La orden no tiene insumos.</td></tr>';

        el('por-tbody-consumir').querySelectorAll('tr[data-key]').forEach(tr => {
            const peso = tr.querySelector('.por-peso');
            const c = tr.querySelector('.por-cant'), pi = tr.querySelector('.por-pi'), pf = tr.querySelector('.por-pf');
            peso.addEventListener('change', () => {
                c.readOnly = peso.checked; pi.disabled = !peso.checked; pf.disabled = !peso.checked;
                if (!peso.checked) { pi.value = ''; pf.value = ''; }
            });
            const calc = () => { if (peso.checked && pi.value !== '' && pf.value !== '') c.value = +(num(pi.value) - num(pf.value)).toFixed(6); };
            pi.addEventListener('input', calc);
            pf.addEventListener('input', calc);
            const sel = tr.querySelector('.por-lote');
            sel.addEventListener('focus', () => cargarLotes(sel, +tr.dataset.producto), { once: true });
        });
    }

    function cargarLotes(sel, idProducto) {
        fetch(`${URL}/lotes-ajax?id_producto=${idProducto}&id_bodega=${orden.id_bodega_insumos}`).then(r => r.json()).then(res => {
            (res.lotes || []).forEach(l => {
                const o = document.createElement('option');
                o.value = l.numero_lote;
                o.textContent = `${l.numero_lote} · ${cant(l.stock_lote)}${l.fecha_caducidad ? ' · vence ' + fecha(l.fecha_caducidad) : ''}`;
                sel.appendChild(o);
            });
            if (!(res.lotes || []).length) {
                const o = document.createElement('option');
                o.disabled = true;
                o.textContent = 'Sin lotes con stock';
                sel.appendChild(o);
            }
        });
    }

    window.POR_quitarExtra = function (uid) {
        consumoExtra = consumoExtra.filter(x => x.uid !== uid);
        pintarConsumir();
    };

    window.POR_consumir = function () {
        if (enCurso || !orden) return;
        const items = [];
        el('por-tbody-consumir').querySelectorAll('tr[data-key]').forEach(tr => {
            const peso = tr.querySelector('.por-peso').checked;
            const item = { id_linea: +tr.dataset.linea, id_producto: +tr.dataset.producto, numero_lote: tr.querySelector('.por-lote').value };
            if (peso) {
                item.peso_inicial = tr.querySelector('.por-pi').value;
                item.peso_final = tr.querySelector('.por-pf').value;
                if (item.peso_inicial === '' || item.peso_final === '') return;
            } else {
                item.cantidad = tr.querySelector('.por-cant').value;
                if (num(item.cantidad) <= 0) return;
            }
            items.push(item);
        });
        if (!items.length) { aviso('warning', 'Nada que consumir', 'Indique la cantidad (o los pesos) de al menos un insumo.'); return; }

        enCurso = true;
        const listo = boton('por-btn-consumir', 'Registrando…');
        post('consumir-ajax', { id: orden.id, items: JSON.stringify(items), fecha: el('por-fecha-consumo').value, token_guardado: tokenConsumo })
            .then(res => {
                if (!res.ok) { aviso('error', 'No se registró el consumo', mensajeDe(res)); return; }
                aviso('success', 'Consumo registrado', res.mensaje);
                window.POR_buscar(window.POR_pagina);
                // Abrir de nuevo la orden renueva el token: el siguiente consumo es otro documento.
                return window.POR_abrir(orden.id);
            })
            .catch(e => { console.error(e); aviso('error', 'Error', 'No se recibió respuesta del servidor. Vuelva a pulsar: si el consumo ya se había registrado, no se descontará otra vez.'); })
            .finally(() => { enCurso = false; listo(); });
    };

    function pintarCerrar() {
        if (!orden) return;
        const sal = orden.lineas.filter(l => l.rol !== 'insumo');
        el('por-tbody-cerrar').innerHTML = sal.map(l => {
            const conLote = l.rol !== 'desperdicio' && l.id_producto;
            return `<tr data-linea="${l.id}">
                <td>${badgeRol(l.rol)}</td><td>${esc(nombreLinea(l))} <span class="small text-muted">${esc(l.medida_nombre || '')}</span></td>
                <td class="text-end">${cant(l.cantidad_teorica)}</td>
                <td class="p-0"><input type="number" step="any" min="0" class="text-end por-sal-cant" value="${+num(l.cantidad_teorica).toFixed(6)}"></td>
                <td class="p-0">${conLote ? `<input type="text" maxlength="50" class="por-sal-lote" value="${esc(orden.lote || '')}">` : ''}</td>
                <td class="p-0">${conLote ? `<input type="date" class="por-sal-cad" value="${esc((orden.fecha_caducidad || '').slice(0, 10))}">` : ''}</td>
            </tr>`;
        }).join('');
    }

    function pintarRecursos() {
        const opc = (m, s) => Object.entries(m).map(([k, v]) => `<option value="${k}" ${k === s ? 'selected' : ''}>${esc(v)}</option>`).join('');
        el('por-tbody-recursos').innerHTML = recursos.length ? recursos.map(r => `
            <tr>
                <td class="p-0"><select onchange="window.POR_setRec(${r.uid}, 'tipo', this.value)">${opc(TIPOS_REC, r.tipo)}</select></td>
                <td class="p-0"><input type="text" maxlength="150" value="${esc(r.descripcion)}" oninput="window.POR_setRec(${r.uid}, 'descripcion', this.value)"></td>
                <td class="p-0"><select onchange="window.POR_setRec(${r.uid}, 'base', this.value)">${opc(BASES_REC, r.base)}</select></td>
                <td class="p-0"><input type="number" step="any" min="0" class="text-end" value="${r.cantidad}" oninput="window.POR_setRec(${r.uid}, 'cantidad', this.value)"></td>
                <td class="p-0"><input type="number" step="any" min="0" class="text-end" value="${r.tarifa}" oninput="window.POR_setRec(${r.uid}, 'tarifa', this.value)"></td>
                <td class="text-center"><button type="button" class="btn btn-link btn-sm text-danger p-0" onclick="window.POR_quitarRec(${r.uid})"><i class="bi bi-x-lg"></i></button></td>
            </tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted py-2">Sin mano de obra ni costos indirectos.</td></tr>';
    }
    window.POR_setRec = (uid, campo, v) => { const r = recursos.find(x => x.uid === uid); if (r) r[campo] = v; };
    window.POR_quitarRec = uid => { recursos = recursos.filter(x => x.uid !== uid); pintarRecursos(); };
    window.POR_agregarRecurso = () => { recursos.push({ uid: ++seq, tipo: 'mo', descripcion: '', base: 'unidad', cantidad: 1, tarifa: 0 }); pintarRecursos(); };

    window.POR_cerrar = function () {
        if (enCurso || !orden) return;
        const salidas = {};
        el('por-tbody-cerrar').querySelectorAll('tr[data-linea]').forEach(tr => {
            salidas[tr.dataset.linea] = {
                cantidad: tr.querySelector('.por-sal-cant').value,
                numero_lote: tr.querySelector('.por-sal-lote')?.value || '',
                fecha_caducidad: tr.querySelector('.por-sal-cad')?.value || '',
            };
        });
        const principal = orden.lineas.find(l => l.rol === 'principal');
        if (!principal || num(salidas[principal.id]?.cantidad) <= 0) { aviso('warning', 'Falta lo producido', 'Indique cuánto se obtuvo del producto principal.'); return; }

        confirmar('¿Cerrar la orden?', 'Lo producido entra al inventario con su costo real y la orden ya no se podrá modificar (solo anular).', 'Sí, cerrar', '#198754').then(si => {
            if (!si) return;
            enCurso = true;
            const listo = boton('por-btn-cerrar', 'Cerrando…');
            post('cerrar-ajax', {
                id: orden.id, salidas: JSON.stringify(salidas), merma: el('por-merma').value || '0',
                recursos: JSON.stringify(recursos.map(r => ({ tipo: r.tipo, descripcion: r.descripcion, base: r.base, cantidad: r.cantidad, tarifa: r.tarifa }))),
                consumir_pendiente: el('por-consumir-pendiente').checked ? '1' : '0', fecha: el('por-fecha-cierre').value, token_guardado: tokenCierre,
            }).then(res => {
                if (!res.ok) { aviso('error', 'No se cerró', mensajeDe(res)); return; }
                aviso('success', 'Orden cerrada', res.mensaje);
                window.POR_buscar(window.POR_pagina);
                return window.POR_abrir(orden.id);
            }).catch(e => { console.error(e); aviso('error', 'Error', 'No se recibió respuesta del servidor. Vuelva a pulsar: si la orden ya se cerró, no entrará otra vez al inventario.'); })
              .finally(() => { enCurso = false; listo(); });
        });
    };

    function pintarMovimientos() {
        const movs = orden.movimientos || [];
        el('por-tbody-movs').innerHTML = movs.length ? movs.map(m => `
            <tr>
                <td>${fecha(m.fecha)}</td>
                <td>${m.tipo === 'consumo' ? '<span class="text-danger"><i class="bi bi-box-arrow-up me-1"></i>Consumo</span>' : '<span class="text-success"><i class="bi bi-box-arrow-in-down me-1"></i>Entrega</span>'}</td>
                <td>${esc(m.producto_nombre)}</td><td>${esc(m.bodega_nombre || '')}</td><td>${esc(m.numero_lote || 'Sin lote')}</td>
                <td class="text-end">${cant(m.cantidad)}${m.peso_inicial !== null ? `<div class="small text-muted">${cant(m.peso_inicial)} − ${cant(m.peso_final)}</div>` : ''}</td>
                <td class="text-end">${money(m.costo_unitario)}</td><td class="text-end">${money(m.costo_total)}</td><td class="small">${esc(m.usuario_nombre || '')}</td>
            </tr>`).join('') : '<tr><td colspan="9" class="text-center text-muted py-3">Todavía no hay consumos ni entregas.</td></tr>';
    }

    function pintarCostos() {
        const o = orden;
        if (o.estado !== 'cerrada') {
            const mp = o.lineas.filter(l => l.rol === 'insumo').reduce((s, l) => s + num(l.costo_real), 0);
            el('por-costos').innerHTML = `<div class="text-muted">Materiales consumidos hasta ahora: <b>${money(mp)}</b>. El costo final se calcula al cerrar la orden.</div>`;
            return;
        }
        const fila = (l, v, cls) => `<tr class="${cls || ''}"><td>${l}</td><td class="text-end">${v}</td></tr>`;
        const salidas = o.lineas.filter(l => l.rol !== 'insumo').map(l => `<tr><td>${badgeRol(l.rol)} ${esc(nombreLinea(l))}</td>
            <td class="text-end">${cant(l.cantidad_real)}</td><td class="text-end">${money(l.costo_unitario)}</td><td class="text-end">${money(l.costo_real)}</td></tr>`).join('');
        el('por-costos').innerHTML = `
            <div class="row g-3">
                <div class="col-md-5"><table class="table table-sm border mb-0">
                    ${fila('Materiales consumidos', money(o.costo_materiales))}
                    ${fila('Mano de obra aplicada', money(o.costo_mano_obra))}
                    ${fila('Costos indirectos aplicados', money(o.costo_cif))}
                    ${num(o.costo_otros) > 0 ? fila('Otros costos', money(o.costo_otros)) : ''}
                    ${num(o.valor_subproductos) > 0 ? fila('Valor de subproductos (rebaja el costo conjunto)', money(o.valor_subproductos)) : ''}
                    ${num(o.costo_merma) > 0 ? fila(`Merma anormal a gasto (${cant(o.cantidad_merma)})`, '-' + money(o.costo_merma), 'text-danger') : ''}
                    ${fila('<b>Costo que entra al inventario</b>', `<b>${money(o.costo_total)}</b>`, 'table-light')}
                </table>
                <div class="form-text">Reparto entre coproductos: ${esc({ valor_venta: 'valor de venta relativo', unidades: 'unidades físicas', porcentaje: '% fijo' }[o.metodo_reparto] || o.metodo_reparto || '')}.</div></div>
                <div class="col-md-7"><table class="table table-sm border mb-0">
                    <thead class="table-light"><tr><th>Salida</th><th class="text-end">Cantidad</th><th class="text-end">Costo unit.</th><th class="text-end">Costo</th></tr></thead>
                    <tbody>${salidas}</tbody></table></div>
            </div>`;
    }

    function pintarTraza() {
        const t = orden.trazabilidad || [];
        if (!t.length) { el('por-traza').innerHTML = '<div class="text-muted">Sin movimientos todavía.</div>'; return; }
        const tabla = (tipo, titulo) => {
            const filas = t.filter(x => x.tipo === tipo);
            return `<div class="col-md-6"><div class="fw-bold mb-1">${titulo}</div><table class="table table-sm border mb-0">
                <thead class="table-light"><tr><th>Producto</th><th>Lote</th><th>Caducidad</th><th class="text-end">Cantidad</th></tr></thead>
                <tbody>${filas.map(x => `<tr><td>${esc(x.nombre)}</td><td>${esc(x.lote)}</td><td>${fecha(x.caducidad)}</td><td class="text-end">${cant(x.cantidad)}</td></tr>`).join('')
                    || '<tr><td colspan="4" class="text-muted text-center">—</td></tr>'}</tbody></table></div>`;
        };
        el('por-traza').innerHTML = `<div class="row g-3">${tabla('consumo', 'Lotes de insumo usados')}${tabla('entrega', 'Lotes producidos')}</div>`;
    }

    function pintarAsientos() {
        const a = orden.asientos || [];
        if (!a.length) {
            el('por-asientos').innerHTML = `<div class="text-muted">${orden.asiento_aviso ? esc(orden.asiento_aviso) : 'Esta orden no tiene asientos (la contabilización de producción está apagada o todavía no hay importes).'}</div>`;
            return;
        }
        el('por-asientos').innerHTML = a.map(x => `
            <div class="border rounded mb-2">
                <div class="bg-light px-2 py-1 d-flex justify-content-between"><span><b>${esc(x.concepto)}</b></span>
                    <span>${fecha(x.fecha_asiento)} · ${esc(x.estado)}</span></div>
                <table class="table table-sm mb-0"><thead><tr><th>Cuenta</th><th>Detalle</th><th class="text-end">Debe</th><th class="text-end">Haber</th></tr></thead>
                <tbody>${(x.detalles || []).map(d => `<tr><td><code>${esc(d.codigo_cuenta)}</code> ${esc(d.nombre_cuenta)}</td><td>${esc(d.referencia_detalle || '')}</td>
                    <td class="text-end">${num(d.debe) ? money(d.debe) : ''}</td><td class="text-end">${num(d.haber) ? money(d.haber) : ''}</td></tr>`).join('')}</tbody></table>
            </div>`).join('');
    }

    // ── Acciones de la orden ─────────────────────────────────────────────────

    window.POR_pdf = function () {
        if (orden) CMG_pdfDocumento(`${URL}/pdf-documento?id=${orden.id}`);
    };

    function accion(url, titulo, html, botonTxt, color, okTitulo, cerrarModal) {
        if (enCurso || !orden) return;
        confirmar(titulo, html, botonTxt, color).then(si => {
            if (!si) return;
            enCurso = true;
            post(url, { id: orden.id }).then(res => {
                if (!res.ok) { aviso('error', 'No se pudo', mensajeDe(res)); return; }
                aviso('success', okTitulo, res.mensaje);
                window.POR_buscar(window.POR_pagina);
                if (cerrarModal) { getModalOrden().hide(); return; }
                return window.POR_abrir(orden.id);
            }).finally(() => { enCurso = false; });
        });
    }

    window.POR_anular = () => accion('anular-ajax', '¿Anular la orden?',
        'Se revierten sus consumos y entregas de inventario y se anulan sus asientos. Si lo producido ya se vendió, no se podrá anular.',
        'Sí, anular', '#dc3545', 'Orden anulada', false);
    window.POR_eliminar = () => accion('eliminar-ajax', '¿Eliminar la orden?', 'Dejará de aparecer en el listado.', 'Sí, eliminar', '#dc3545', 'Orden eliminada', true);
    window.POR_generarAsiento = () => accion('generar-asiento-ajax', '¿Generar los asientos?', 'Se intenta de nuevo con la configuración actual.', 'Sí, generar', '#0d6efd', 'Asientos', false);
})();
