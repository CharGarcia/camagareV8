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
    let previaSeq = 0;         // descarta respuestas de la vista previa que llegan tarde
    let caducidadSugerida = ''; // última caducidad puesta por la fórmula (no por el usuario)
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

    /**
     * Buscador con lista desplegable. La lista se coloca en el contenedor `.position-relative` del campo
     * (no dentro de un `.input-group`, que deformaría sus esquinas). `opts.minChars = 0` muestra la lista
     * apenas se hace clic en el campo, sin escribir nada.
     */
    function typeahead(input, url, render, onPick, opts) {
        const minChars = opts && opts.minChars !== undefined ? opts.minChars : 2;
        const cont = input.closest('.position-relative') || input.parentElement;
        const dd = document.createElement('div');
        dd.className = 'list-group position-absolute shadow por-dd d-none';
        dd.style.minWidth = '340px';
        dd.style.width = '100%';
        cont.style.position = 'relative';
        cont.appendChild(dd);
        let timer = null;
        const cerrar = () => { dd.classList.add('d-none'); dd.innerHTML = ''; };
        const buscar = (q) => {
            fetch(`${url}?q=${encodeURIComponent(q)}`).then(r => r.json()).then(res => {
                if (document.activeElement !== input) return;
                const data = res.data || [];
                dd._data = data;
                dd.innerHTML = data.length
                    ? data.map((x, i) => `<button type="button" class="list-group-item list-group-item-action small py-1" data-i="${i}">${render(x)}</button>`).join('')
                    : '<div class="list-group-item small text-muted">Sin resultados.</div>';
                dd.classList.remove('d-none');
            });
        };
        if (minChars === 0) {
            input.addEventListener('focus', () => { if (input.dataset.elegido !== '1' && !input.value.trim()) buscar(''); });
        }
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
            if (q.length < Math.max(minChars, 1) && !(minChars === 0 && q === '')) { cerrar(); return; }
            timer = setTimeout(() => buscar(q), 250);
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

    // Texto y filtros viajan juntos en «b» como clave:valor: el buscador con filtros (FiltrosModal)
    // escribe ahí estado, tipo, fechas, bodegas, etc.
    function params() {
        return new URLSearchParams({ b: el('por-buscar')?.value || '' });
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
            if (res.pdf_url) el('por-pdf').href = res.pdf_url;
            if (res.excel_url) el('por-excel').href = res.excel_url;
            if (sorter) sorter.refreshIcons();
        }).catch(e => console.error('POR_buscar', e)).finally(() => tbody.classList.remove('opacity-50'));
    };

    // El buscador (FiltrosModal) llama a POR_buscar(1) por su cuenta (onApply) al escribir o filtrar.

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
                if (!f) { el('pon-formula').value = ''; previa = null; previaSeq++; pintarPrevia(); return; }
                el('pon-formula-txt').value = `${f.codigo} v${f.version} · ${f.nombre}`;
                el('pon-formula-txt').dataset.elegido = '1';
                el('pon-formula').value = f.id;
                el('pon-medida').textContent = f.medida_nombre || 'u';
                if (!num(el('pon-cantidad').value)) el('pon-cantidad').value = num(f.cantidad_base);
                cargarPrevia();
            }, { minChars: 0 });
        ['pon-cantidad', 'pon-bodega-insumos'].forEach(id => el(id).addEventListener('change', cargarPrevia));
        el('pon-tbody').addEventListener('input', e => { if (e.target.classList.contains('pon-real')) e.target.dataset.editado = '1'; });
    }

    window.POR_nueva = function (tipo) {
        if (!PERM.crear) { aviso('warning', 'Sin permiso', 'No tiene permiso para crear órdenes.'); return; }
        const rapida = tipo === 'rapida';
        el('pon-tipo').value = rapida ? 'rapida' : 'orden';
        el('pon-titulo').textContent = rapida ? 'Producción rápida' : 'Nueva orden de producción';
        el('pon-icono').className = rapida ? 'bi bi-lightning-charge text-warning me-2' : 'bi bi-gear-wide-connected text-primary me-2';
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
        previaSeq++;
        caducidadSugerida = '';
        pintarPrevia();
        tokenNueva = token();
        getModalNueva().show();
    };

    function cargarPrevia() {
        const idF = el('pon-formula').value, c = num(el('pon-cantidad').value);
        const seq = ++previaSeq;
        if (!idF || c <= 0) { previa = null; pintarPrevia(); return; }
        const q = new URLSearchParams({ id_formula: idF, cantidad: String(c), id_bodega_insumos: el('pon-bodega-insumos').value || '0' });
        fetch(`${URL}/vista-previa-ajax?${q}`).then(r => r.json()).then(res => {
            // Si mientras tanto cambió la fórmula, la cantidad o la bodega, esta respuesta ya no vale.
            if (seq !== previaSeq) return;
            if (!res.ok) { aviso('error', 'Error', mensajeDe(res)); return; }
            previa = res.data;
            previa._clave = `${idF}|${c}`;
            // La caducidad la reemplaza la nueva fórmula solo si estaba vacía o la había puesto la anterior;
            // si el usuario la escribió a mano, se respeta.
            const cad = el('pon-caducidad');
            if (!cad.value || cad.value === caducidadSugerida) {
                cad.value = previa.caducidad_sugerida || '';
                caducidadSugerida = previa.caducidad_sugerida || '';
            }
            pintarPrevia();
        }).catch(e => console.error('vista previa', e));
    }

    function pintarPrevia() {
        const rapida = el('pon-tipo').value === 'rapida';
        // Lo que el usuario escribió en "Producido real" se conserva si la fórmula y la cantidad no cambiaron
        // (p. ej. solo cambió la bodega de insumos); si cambiaron, vuelve a lo teórico y se avisa.
        const editados = {};
        document.querySelectorAll('#pon-tbody .pon-real[data-editado="1"]').forEach(i => { editados[i.dataset.orden] = i.value; });
        const claveAnterior = el('pon-tbody').dataset.clave || '';
        const conservar = previa && previa._clave === claveAnterior;
        el('pon-tbody').dataset.clave = previa ? previa._clave : '';
        if (rapida && previa && !conservar && Object.keys(editados).length) {
            aviso('info', 'Producido real recalculado', 'Cambió la fórmula o la cantidad: lo producido real volvió a la cantidad teórica. Revíselo antes de producir.');
        }
        if (el('pon-count')) el('pon-count').textContent = previa ? previa.lineas.length : 0;
        if (!previa) {
            el('pon-tbody').innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Elija la fórmula y la cantidad.</td></tr>';
            el('pon-faltantes').innerHTML = '';
            return;
        }
        el('pon-tbody').innerHTML = previa.lineas.map(l => {
            const insumo = l.rol === 'insumo';
            const falta = insumo && num(l.falta) > 0;
            // Se muestra en la unidad en que se escribió la fórmula (p. ej. GRAMO). El inventario va en la
            // unidad del producto (p. ej. KILOGRAMO): debajo del teórico se ve la equivalencia.
            const f = num(l.factor_formula) || 1;
            const otraUnidad = Math.abs(f - 1) > 1e-12;
            const enFormula = v => cant(num(v) / f);
            return `<tr class="${falta ? 'table-danger' : ''}">
                <td>${badgeRol(l.rol)}</td><td>${esc(nombreLinea(l))}</td><td class="small text-muted">${esc(l.unidad_formula || l.medida_nombre)}</td>
                <td class="text-end">${cant(l.cantidad_formula ?? l.cantidad_teorica)}${otraUnidad
                    ? `<div class="small text-muted" title="Así se descuenta del inventario">= ${cant(l.cantidad_teorica)} ${esc(l.medida_nombre)}</div>` : ''}</td>
                <td class="text-end"${otraUnidad ? ` title="${cant(l.stock)} ${esc(l.medida_nombre)}"` : ''}>${insumo ? enFormula(l.stock) + (falta ? ` <span class="text-danger small">(faltan ${enFormula(l.falta)})</span>` : '') : ''}</td>
                <td class="p-0 pon-col-real ${rapida ? '' : 'd-none'}">${!insumo
                    ? (conservar && editados[l.orden] !== undefined
                        ? `<input type="number" step="any" min="0" class="text-end pon-real" data-orden="${l.orden}" data-editado="1" value="${esc(editados[l.orden])}">`
                        : `<input type="number" step="any" min="0" class="text-end pon-real" data-orden="${l.orden}" value="${num(l.cantidad_teorica)}">`)
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
        if (!el('pon-fecha').value) { aviso('warning', 'Falta la fecha', 'Indique la fecha de la orden.'); return; }
        if (el('pon-fecha').value > hoy()) { aviso('warning', 'Fecha no válida', 'La fecha de la orden no puede ser futura.'); return; }
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

    // ── Unidad de la fórmula en la orden ─────────────────────────────────────
    // Cada línea trae unidad_formula y factor_formula (1 unidad de la fórmula = factor unidades del
    // producto). Plan, Consumir y Cerrar muestran y reciben las cantidades en la unidad de la fórmula;
    // al enviar se multiplican por el factor: el servidor, el stock y el kardex siguen en la unidad
    // del producto.
    const fac = l => num(l && l.factor_formula) || 1;
    const uni = l => (l && (l.unidad_formula || l.medida_nombre)) || '';
    const otraUnidad = l => Math.abs(fac(l) - 1) > 1e-12;
    const aFormula = (v, l) => num(v) / fac(l);
    const aProducto = (v, l) => +(num(v) * fac(l)).toFixed(6);
    const equivalencia = (v, l) => otraUnidad(l)
        ? `<div class="small text-muted" title="Así se mueve en el inventario">= ${cant(v)} ${esc(l.medida_nombre || '')}</div>` : '';

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
            const stock = (o.stocks || {})[l.id_producto] || 0;
            return `<tr>
                <td>${badgeRol(l.rol)}${l.fuera_formula ? ' <span class="small text-muted">fuera de fórmula</span>' : ''}</td>
                <td>${esc(nombreLinea(l))}</td><td class="small text-muted">${esc(uni(l))}</td>
                <td class="text-end">${cant(aFormula(teo, l))}${equivalencia(teo, l)}</td>
                <td class="text-end">${mostrarReal ? cant(aFormula(real, l)) + equivalencia(real, l) : '—'}</td>
                <td class="text-end ${dif > 0 ? 'text-danger' : (dif < 0 ? 'text-success' : '')}">${mostrarReal ? cant(aFormula(dif, l)) : ''}</td>
                <td class="text-end"${otraUnidad(l) ? ` title="${cant(stock)} ${esc(l.medida_nombre || '')}"` : ''}>${insumo ? cant(aFormula(stock, l)) : ''}</td>
                <td class="text-end">${insumo || o.estado === 'cerrada' ? money(l.costo_real) : ''}</td>
            </tr>`;
        }).join('');

        // Pestañas de acción solo con la orden abierta y permiso de actualizar.
        document.querySelectorAll('#modalOrden .por-solo-abierta').forEach(t => t.classList.toggle('d-none', !(abierta && PERM.actualizar)));
        const primera = document.querySelector('#modalOrden .nav-link[data-bs-target="#pane-por-plan"]');
        if (primera) bootstrap.Tab.getOrCreateInstance(primera).show();

        el('por-fecha-consumo').value = hoy();
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
        // Quién creó/modificó y el historial están en la pestaña «Información» (InformacionRegistro).
    }

    function pintarConsumir() {
        if (!orden) return;
        const filas = orden.lineas.filter(l => l.rol === 'insumo').map(l => ({
            key: 'l' + l.id, id_linea: l.id, id_producto: l.id_producto, nombre: nombreLinea(l), medida: uni(l), factor: fac(l),
            pendiente: Math.max(0, aFormula(num(l.cantidad_teorica) - num(l.cantidad_real), l)), exige: !!l.exige_lote && l.exige_lote !== 'f',
        })).concat(consumoExtra.map(x => ({ key: 'x' + x.uid, id_linea: 0, id_producto: x.id_producto, nombre: x.producto, medida: x.medida, factor: 1, pendiente: 0, extra: x.uid })));

        el('por-tbody-consumir').innerHTML = filas.length ? filas.map(f => `
            <tr data-key="${f.key}" data-linea="${f.id_linea}" data-producto="${f.id_producto}" data-factor="${f.factor}">
                <td>${esc(f.nombre)} <span class="small text-muted">${esc(f.medida)}</span>${f.exige || CFG.exigir_lote_insumos ? ' <i class="bi bi-upc text-primary" title="Exige lote"></i>' : ''}${f.extra ? ' <span class="small text-muted">fuera de fórmula</span>' : ''}</td>
                <td class="text-end">${cant(f.pendiente)}</td>
                <td class="text-center"><input type="checkbox" class="form-check-input m-0 por-peso" style="width:auto;height:auto;"></td>
                <td class="p-0"><input type="number" step="any" min="0" class="text-end por-cant" value="${f.pendiente > 0 ? +f.pendiente.toFixed(6) : ''}"></td>
                <td class="p-0"><input type="number" step="any" min="0" class="text-end por-pi" disabled></td>
                <td class="p-0"><input type="number" step="any" min="0" class="text-end por-pf" disabled></td>
                <td class="p-0"><select class="por-lote"><option value="">Automático (vence primero)</option></select></td>
                <td class="text-center">${f.extra ? `<button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="window.POR_quitarExtra(${f.extra})" title="Eliminar ítem"><i class="bi bi-trash3 fs-6"></i></button>` : ''}</td>
            </tr>`).join('') : '<tr><td colspan="8" class="text-center text-muted py-3">La orden no tiene insumos.</td></tr>';
        if (el('por-count-consumir')) el('por-count-consumir').textContent = filas.length;

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
            sel.addEventListener('focus', () => cargarLotes(sel, +tr.dataset.producto, num(tr.dataset.factor) || 1), { once: true });
        });
    }

    function cargarLotes(sel, idProducto, factor) {
        factor = factor || 1; // el saldo del lote viene en la unidad del producto: se muestra en la de la fórmula
        fetch(`${URL}/lotes-ajax?id_producto=${idProducto}&id_bodega=${orden.id_bodega_insumos}`).then(r => r.json()).then(res => {
            (res.lotes || []).forEach(l => {
                const o = document.createElement('option');
                o.value = l.numero_lote;
                o.textContent = `${l.numero_lote} · ${cant(num(l.stock_lote) / factor)}${l.fecha_caducidad ? ' · vence ' + fecha(l.fecha_caducidad) : ''}`;
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
            const factor = num(tr.dataset.factor) || 1; // lo escrito está en la unidad de la fórmula
            const item = { id_linea: +tr.dataset.linea, id_producto: +tr.dataset.producto, numero_lote: tr.querySelector('.por-lote').value };
            if (peso) {
                const pi = tr.querySelector('.por-pi').value, pf = tr.querySelector('.por-pf').value;
                if (pi === '' || pf === '') return;
                item.peso_inicial = String(+(num(pi) * factor).toFixed(6));
                item.peso_final = String(+(num(pf) * factor).toFixed(6));
            } else {
                const cantidad = tr.querySelector('.por-cant').value;
                if (num(cantidad) <= 0) return;
                item.cantidad = String(+(num(cantidad) * factor).toFixed(6));
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
            return `<tr data-linea="${l.id}" data-factor="${fac(l)}">
                <td>${badgeRol(l.rol)}</td><td>${esc(nombreLinea(l))} <span class="small text-muted">${esc(uni(l))}</span></td>
                <td class="text-end">${cant(aFormula(l.cantidad_teorica, l))}${equivalencia(l.cantidad_teorica, l)}</td>
                <td class="p-0"><input type="number" step="any" min="0" class="text-end por-sal-cant" value="${+aFormula(l.cantidad_teorica, l).toFixed(6)}"></td>
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
                <td class="text-center"><button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="window.POR_quitarRec(${r.uid})" title="Eliminar ítem"><i class="bi bi-trash3 fs-6"></i></button></td>
            </tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted py-3">Sin mano de obra ni costos indirectos.</td></tr>';
        if (el('por-count-recursos')) el('por-count-recursos').textContent = recursos.length;
    }
    window.POR_setRec = (uid, campo, v) => { const r = recursos.find(x => x.uid === uid); if (r) r[campo] = v; };
    window.POR_quitarRec = uid => { recursos = recursos.filter(x => x.uid !== uid); pintarRecursos(); };
    window.POR_agregarRecurso = () => { recursos.push({ uid: ++seq, tipo: 'mo', descripcion: '', base: 'unidad', cantidad: 1, tarifa: 0 }); pintarRecursos(); };

    // ── Cerrar la orden (botón de la barra superior) ─────────────────────────
    // 1) Pide la fecha de cierre. 2) Verifica TODO sin cerrar: lo de la pantalla (cantidad del
    // principal, consumos) y, en el servidor, el cierre completo deshecho al final (estado, fecha,
    // período contable, stock, lotes exigidos, costos). 3) Muestra la lista de comprobación y,
    // si todo está bien, el resumen de lo que entrará al inventario; recién ahí se cierra.
    function datosCierre() {
        const salidas = {};
        el('por-tbody-cerrar').querySelectorAll('tr[data-linea]').forEach(tr => {
            const factor = num(tr.dataset.factor) || 1; // lo escrito está en la unidad de la fórmula
            const v = tr.querySelector('.por-sal-cant').value;
            salidas[tr.dataset.linea] = {
                cantidad: v === '' ? '' : String(+(num(v) * factor).toFixed(6)),
                numero_lote: tr.querySelector('.por-sal-lote')?.value || '',
                fecha_caducidad: tr.querySelector('.por-sal-cad')?.value || '',
            };
        });
        return {
            salidas,
            datos: {
                id: orden.id, salidas: JSON.stringify(salidas), merma: el('por-merma').value || '0',
                recursos: JSON.stringify(recursos.map(r => ({ tipo: r.tipo, descripcion: r.descripcion, base: r.base, cantidad: r.cantidad, tarifa: r.tarifa }))),
                consumir_pendiente: el('por-consumir-pendiente').checked ? '1' : '0',
            },
        };
    }

    function itemCheck(ok, texto) {
        return `<li class="mb-1"><i class="bi ${ok ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'} me-2"></i>${texto}</li>`;
    }

    window.POR_cerrar = async function () {
        if (enCurso || !orden) return;
        if (typeof Swal === 'undefined') { aviso('error', 'No disponible', 'No se pudo abrir la ventana de cierre.'); return; }
        const { salidas, datos } = datosCierre();
        const hoyStr = hoy();
        const desde = String(orden.fecha_inicio || '').slice(0, 10);

        // 1) Fecha de cierre
        const r1 = await Swal.fire({
            icon: 'question',
            title: 'Cerrar la orden',
            html: `<div class="text-start small">
                       <label class="form-label fw-bold mb-1" for="por-swal-fecha">Fecha de cierre</label>
                       <input type="date" id="por-swal-fecha" class="form-control" value="${hoyStr}" max="${hoyStr}" ${desde ? `min="${desde}"` : ''}>
                       <div class="form-text">Es la fecha con que lo producido entra al inventario y del asiento. No puede ser futura ni anterior a la orden (${fecha(desde)}).</div>
                   </div>`,
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-search me-1"></i> Verificar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
            focusConfirm: false,
            preConfirm: () => {
                const v = document.getElementById('por-swal-fecha').value;
                if (!v) { Swal.showValidationMessage('Indique la fecha de cierre.'); return false; }
                if (v > hoyStr) { Swal.showValidationMessage('La fecha de cierre no puede ser futura.'); return false; }
                if (desde && v < desde) { Swal.showValidationMessage(`No puede ser anterior a la fecha de la orden (${fecha(desde)}).`); return false; }
                return v;
            },
        });
        if (!r1.isConfirmed) return;
        const fechaCierre = r1.value;

        // 2) Verificación
        const principal = orden.lineas.find(l => l.rol === 'principal');
        const cantPrincipal = principal ? num(salidas[principal.id]?.cantidad) : 0;
        const hayConsumo = orden.lineas.some(l => l.rol === 'insumo' && num(l.cantidad_real) > 0);
        const pendiente = datos.consumir_pendiente === '1';
        const checks = [
            [true, `Fecha de cierre: <b>${fecha(fechaCierre)}</b>`],
            [cantPrincipal > 0, cantPrincipal > 0
                ? `Producto principal obtenido: <b>${cant(aFormula(cantPrincipal, principal))} ${esc(uni(principal))}</b>`
                : 'Falta indicar cuánto se obtuvo del producto principal (pestaña <b>Cerrar</b>, columna Obtenido).'],
            [hayConsumo || pendiente, hayConsumo
                ? (pendiente ? 'Insumos: hay consumos registrados y se consumirá lo pendiente según la fórmula.' : 'Insumos: se usan solo los consumos ya registrados.')
                : (pendiente ? 'Insumos: se consumirá todo lo de la fórmula (el lote que vence primero).'
                             : 'No hay consumos registrados: registre lo consumido o marque <b>Consumir lo pendiente según la fórmula</b>.')],
        ];

        Swal.fire({ title: 'Verificando…', text: 'Revisando stock, lotes, período contable y costos.', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
        let res;
        try {
            res = await post('verificar-cierre-ajax', { ...datos, fecha: fechaCierre });
        } catch (e) {
            console.error(e);
            aviso('error', 'Error', 'No se pudo verificar: no se recibió respuesta del servidor.');
            return;
        }
        if (!res.ok) { aviso('error', 'No se pudo verificar', mensajeDe(res)); return; }
        checks.push([!!res.puede, res.puede
            ? 'Stock, lotes, período contable y costos: <b>todo en orden</b>.'
            : `Revisión del sistema: ${esc(res.problema || 'no se puede cerrar.')}`]);

        const todoOk = checks.every(c => c[0]);
        const lista = `<ul class="list-unstyled text-start small mb-0">${checks.map(c => itemCheck(c[0], c[1])).join('')}</ul>`;
        if (!todoOk) {
            await Swal.fire({ icon: 'warning', title: 'Todavía no se puede cerrar', html: lista, confirmButtonText: 'Entendido', confirmButtonColor: '#0d6efd' });
            return;
        }

        const s = res.resumen || {};
        const resumen = `<div class="text-start small border rounded p-2 mt-2 bg-light">
                Entrarán <b>${cant(s.cantidad)} ${esc(s.medida || '')}</b> de <b>${esc(s.producto || '')}</b>
                a la bodega <b>${esc(s.bodega || '')}</b>, con un costo de <b>${money(s.costo_total)}</b>.
                ${num(s.costo_merma) > 0 ? `<br>Merma anormal a gasto: <b>${money(s.costo_merma)}</b>.` : ''}
                <div class="text-muted mt-1">Después de cerrar, la orden ya no se puede modificar (solo anular).</div>
            </div>`;
        const r2 = await Swal.fire({
            icon: 'success', title: 'Todo listo para cerrar', html: lista + resumen,
            showCancelButton: true, confirmButtonText: '<i class="bi bi-flag me-1"></i> Sí, cerrar orden', cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
        });
        if (!r2.isConfirmed) return;

        // 3) Cierre real
        enCurso = true;
        const listo = boton('por-btn-cerrar', 'Cerrando…');
        post('cerrar-ajax', { ...datos, fecha: fechaCierre, token_guardado: tokenCierre })
            .then(r => {
                if (!r.ok) { aviso('error', 'No se cerró', mensajeDe(r)); return; }
                aviso('success', 'Orden cerrada', r.mensaje);
                window.POR_buscar(window.POR_pagina);
                return window.POR_abrir(orden.id);
            })
            .catch(e => { console.error(e); aviso('error', 'Error', 'No se recibió respuesta del servidor. Vuelva a pulsar: si la orden ya se cerró, no entrará otra vez al inventario.'); })
            .finally(() => { enCurso = false; listo(); });
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

    // ── Pestaña Asiento: pieza estándar editable (asiento_contable_tab.js) ───
    // Una orden tiene varios asientos (uno por cada consumo y el de cierre). El selector elige cuál
    // se muestra; el componente lo carga desde Asientos Contables, permite corregirlo y guardarlo
    // (quien puede actualizar ese módulo) y «Restaurar automático» lo vuelve a armar con las reglas.
    let asientoTab = null;
    const asientoCfg = {
        prefijo: 'por',
        moduloOrigen: 'produccion_cierre',
        soloRegistrado: true,
        cuentasUrl: `${window.BASE_URL}/modulos/plan-cuentas/searchAjaxCuentas`,
        asientosUrl: `${window.BASE_URL}/modulos/asientos-contables`,
    };
    function getAsientoTab() {
        if (!asientoTab && typeof window.crearAsientoTab === 'function' && el('por-asiento-tbody')) {
            asientoTab = window.crearAsientoTab(asientoCfg);
        }
        return asientoTab;
    }

    function nombreAsiento(x, i, total) {
        const tipo = x.modulo_origen === 'produccion_cierre' ? 'Cierre de la orden' : `Consumo de insumos${total > 1 ? ' ' + (i + 1) : ''}`;
        const numero = x.numero_comprobante || '';
        return `${tipo}${numero ? ' · ' + numero : ''} · ${fecha(x.fecha_asiento)}`;
    }

    function cargarAsientoElegido() {
        const a = orden.asientos || [];
        const x = a[+el('por-asiento-sel').value];
        const tab = getAsientoTab();
        if (!x || !tab) return;
        asientoCfg.moduloOrigen = x.modulo_origen;   // el componente lee la configuración en cada carga
        el('por-asiento-info').innerHTML = (x.editado_manual === true || x.editado_manual === 't')
            ? '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">Editado a mano</span>' : '';
        tab.cargar(+x.id_referencia_origen);
    }

    function pintarAsientos() {
        if (!el('pane-por-asiento')) return; // sin permiso para ver asientos no existe la pestaña
        const a = orden.asientos || [];
        const vacio = el('por-asiento-vacio'), contenido = el('por-asiento-contenido');
        if (!a.length) {
            vacio.innerHTML = orden.asiento_aviso
                ? `<i class="bi bi-exclamation-triangle text-danger me-1"></i>${esc(orden.asiento_aviso)}`
                : 'Esta orden todavía no tiene asientos: se generan al registrar consumos y al cerrarla, si la contabilización de Producción está encendida en Configuración Contable.';
            vacio.classList.remove('d-none');
            contenido.classList.add('d-none');
            return;
        }
        vacio.classList.add('d-none');
        contenido.classList.remove('d-none');
        // Por defecto, el de cierre (si existe); si no, el último consumo.
        const iCierre = a.findIndex(x => x.modulo_origen === 'produccion_cierre');
        let nConsumo = 0;
        const totalConsumos = a.filter(x => x.modulo_origen !== 'produccion_cierre').length;
        el('por-asiento-sel').innerHTML = a.map((x, i) => {
            const idx = x.modulo_origen === 'produccion_cierre' ? 0 : nConsumo++;
            return `<option value="${i}">${esc(nombreAsiento(x, idx, totalConsumos))}</option>`;
        }).join('');
        el('por-asiento-sel').value = String(iCierre >= 0 ? iCierre : a.length - 1);
        cargarAsientoElegido();
    }
    el('por-asiento-sel')?.addEventListener('change', cargarAsientoElegido);

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
