/**
 * Fórmulas de producción — listado + modal (entradas, salidas, MO/CIF, costo estimado).
 *
 * La salida principal es el producto de la cabecera; las demás salidas (coproductos,
 * subproductos, desperdicio) y los insumos van en sus pestañas. Una fórmula usada en
 * órdenes se abre en solo lectura: para cambiarla se crea una nueva versión.
 */
(function () {
    'use strict';

    const URL   = window.PFO_URL_BASE;
    const PERM  = window.PFO_PERM || {};
    const MODOS = window.PFO_MODOS || {};
    const TIPOS_REC = window.PFO_TIPOS_RECURSO || {};
    const BASES_REC = window.PFO_BASES_RECURSO || {};
    const ROLES_SALIDA = { coproducto: 'Coproducto', subproducto: 'Subproducto', desperdicio: 'Desperdicio' };

    let modalRef   = null;
    let lineas     = [];   // insumos + otras salidas (sin la principal)
    let recursos   = [];
    let seq        = 0;
    let soloLectura = false;
    let guardando  = false;
    let actual     = null; // fórmula abierta (respuesta del servidor)
    window.PFO_pagina = 1;

    // ── Borrador local de una fórmula NUEVA (localStorage) ───────────────────
    // Mientras se llena una fórmula que aún no se guardó, lo escrito se copia en el navegador
    // (por empresa y usuario). Si se cierra el modal, se recarga la página o se corta la luz, al
    // pulsar «Nueva» se ofrece recuperarlo. Se borra al guardar la fórmula o al «Empezar de cero».
    // Solo es una comodidad de este navegador: nunca llega al servidor ni a otros usuarios.
    const BORRADOR_KEY = window.PFO_BORRADOR_KEY || 'cmg_pfo_borrador';
    let borradorActivo = false;   // true solo mientras se edita una fórmula nueva
    let borradorTimer = null;

    function leerBorrador() {
        try { const t = localStorage.getItem(BORRADOR_KEY); return t ? JSON.parse(t) : null; } catch (e) { return null; }
    }
    function borrarBorrador() {
        clearTimeout(borradorTimer);
        try { localStorage.removeItem(BORRADOR_KEY); } catch (e) { /* almacenamiento no disponible */ }
    }
    function valor(id) { const x = el(id); return x ? x.value : ''; }
    function hayContenido() {
        return !!(valor('pfo-producto') || valor('pfo-nombre').trim() || valor('pfo-instrucciones').trim() || valor('pfo-observaciones').trim()
            || recursos.length
            || lineas.some(l => l.id_producto || String(l.cantidad ?? '').trim() !== '' || String(l.descripcion || '').trim() !== ''));
    }
    function guardarBorrador() {
        if (!borradorActivo || valor('pfo-id')) return;
        clearTimeout(borradorTimer);
        borradorTimer = setTimeout(() => {
            if (!borradorActivo || valor('pfo-id')) return;
            if (!hayContenido()) { borrarBorrador(); return; }
            const sinUid = x => { const { uid, ...resto } = x; return resto; };
            const snap = {
                v: 1, ts: Date.now(),
                cab: {
                    producto: valor('pfo-producto'), producto_txt: valor('pfo-producto-txt'), medida: el('pfo-medida')?.textContent || 'u',
                    nombre: valor('pfo-nombre'), cantidad_base: valor('pfo-cantidad-base'), vida_util: valor('pfo-vida-util'),
                    metodo: valor('pfo-metodo'), reparto_principal: valor('pfo-reparto-principal'),
                    instrucciones: valor('pfo-instrucciones'), observaciones: valor('pfo-observaciones'),
                },
                lineas: lineas.map(sinUid),
                recursos: recursos.map(sinUid),
            };
            try { localStorage.setItem(BORRADOR_KEY, JSON.stringify(snap)); } catch (e) { /* lleno o bloqueado: se sigue sin borrador */ }
        }, 500);
    }
    function aplicarBorrador(b) {
        const c = b.cab || {};
        const poner = (id, v) => { if (el(id)) el(id).value = v ?? ''; };
        poner('pfo-producto', c.producto);
        poner('pfo-producto-txt', c.producto_txt);
        el('pfo-producto-txt').dataset.elegido = c.producto ? '1' : '';
        el('pfo-medida').textContent = c.medida || 'u';
        poner('pfo-nombre', c.nombre);
        poner('pfo-cantidad-base', c.cantidad_base || 1);
        poner('pfo-vida-util', c.vida_util);
        poner('pfo-metodo', c.metodo);
        poner('pfo-reparto-principal', c.reparto_principal);
        poner('pfo-instrucciones', c.instrucciones);
        poner('pfo-observaciones', c.observaciones);
        lineas = (b.lineas || []).map(l => ({ ...nuevaLinea(l.rol || 'insumo'), ...l, uid: ++seq }));
        recursos = (b.recursos || []).map(r => ({ ...r, uid: ++seq }));
        if (!lineas.some(l => l.rol === 'insumo')) lineas.push(nuevaLinea('insumo'));
    }
    function fechaBorrador(ts) {
        const d = new Date(ts), p = n => String(n).padStart(2, '0');
        return `${p(d.getDate())}-${p(d.getMonth() + 1)}-${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
    }
    // Escribir en cualquier campo del modal guarda el borrador (las tablas además lo hacen al repintarse).
    ['input', 'change'].forEach(ev => document.getElementById('modalFormula')?.addEventListener(ev, guardarBorrador));

    // ── Utilidades ───────────────────────────────────────────────────────────

    function el(id) { return document.getElementById(id); }
    function esc(t) {
        return String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function num(v) { const n = parseFloat(String(v ?? '').replace(',', '.')); return isNaN(n) ? 0 : n; }
    function fmt(v, d) { return num(v).toLocaleString('es-EC', { minimumFractionDigits: d ?? 2, maximumFractionDigits: d ?? 2 }); }
    function fmtCant(v) { return num(v).toLocaleString('es-EC', { maximumFractionDigits: 6 }); }
    function money(v) { return '$' + fmt(v, 2); }
    function aviso(icono, titulo, texto) {
        if (typeof Swal === 'undefined') { alert(titulo + '\n' + (texto || '')); return Promise.resolve(); }
        return Swal.fire({ icon: icono, title: titulo, text: texto || '', confirmButtonColor: '#0d6efd' });
    }
    function confirmar(titulo, html, boton, color) {
        if (typeof Swal === 'undefined') return Promise.resolve(confirm(titulo));
        return Swal.fire({ icon: 'question', title: titulo, html: html || '', showCancelButton: true,
            confirmButtonText: boton || 'Sí', cancelButtonText: 'Cancelar', confirmButtonColor: color || '#0d6efd' })
            .then(r => r.isConfirmed);
    }
    function mensajeDe(res) { return res.mensaje || res.error || 'Error desconocido.'; }
    function getModal() {
        if (!modalRef) modalRef = new bootstrap.Modal(el('modalFormula'));
        return modalRef;
    }
    function post(accion, datos) {
        const fd = datos instanceof FormData ? datos : new FormData();
        if (!(datos instanceof FormData)) Object.entries(datos || {}).forEach(([k, v]) => fd.append(k, v));
        return fetch(`${URL}/${accion}`, { method: 'POST', body: fd }).then(r => r.json());
    }

    // ── Listado ──────────────────────────────────────────────────────────────

    window.PFO_buscar = function (p) {
        if (p < 1 || p > (window.PFO_PAGES || 1) && p !== 1) return;
        window.PFO_pagina = p || 1;
        // El estado y demás filtros viajan dentro de «b» como clave:valor (FiltrosModal los escribe ahí).
        const params = new URLSearchParams({ b: el('pfo-buscar')?.value || '', page: String(window.PFO_pagina) });
        const tbody = el('pfo-tbody');
        tbody.classList.add('opacity-50');
        fetch(`${URL}/search-ajax?${params}`)
            .then(r => r.json())
            .then(res => {
                if (!res.ok) return;
                tbody.innerHTML = res.rows;
                el('pfo-info').textContent = res.info;
                window.PFO_PAGES = res.pages;
                const btns = el('pfo-paginacion').querySelectorAll('button');
                btns[0].disabled = res.page <= 1;
                btns[1].disabled = res.page >= res.pages;
                if (res.pdf_url) el('pfo-pdf').href = res.pdf_url;
                if (res.excel_url) el('pfo-excel').href = res.excel_url;
                if (sorter) sorter.refreshIcons();
            })
            .catch(e => console.error('PFO_buscar', e))
            .finally(() => tbody.classList.remove('opacity-50'));
    };

    // El buscador (FiltrosModal) llama a PFO_buscar(1) por su cuenta (onApply) al escribir o filtrar.

    const sorter = window.CMG_initSort ? window.CMG_initSort(window.PFO_MODULO, (col, dir, sorts) => {
        window.currentSorts = sorts;
        window.PFO_buscar(1);
    }, { sorts: window.currentSorts, multi: true }) : null;

    // ── Buscador de productos (selección tipo chip) ──────────────────────────

    // Una sola lista flotante para todos los buscadores (cabecera y filas): va en <body> con
    // position:fixed bajo el campo activo, así no la recorta el scroll de las tablas de ítems.
    const dd = document.createElement('div');
    dd.className = 'list-group position-fixed shadow pfo-dd d-none';
    document.body.appendChild(dd);
    let ddDueno = null;      // input que abrió la lista
    let ddAlElegir = null;   // qué hacer al elegir
    function cerrarDd() { dd.classList.add('d-none'); dd.innerHTML = ''; ddDueno = null; ddAlElegir = null; }
    function ubicarDd() {
        if (!ddDueno) return;
        const r = ddDueno.getBoundingClientRect();
        dd.style.left = `${r.left}px`;
        dd.style.top = `${r.bottom + 2}px`;
        dd.style.width = `${Math.max(r.width, 340)}px`;
    }
    window.addEventListener('resize', ubicarDd);
    // Si se desplaza la página o el modal, la lista se cierra (no queda flotando fuera de lugar); su propio scroll no cuenta.
    document.addEventListener('scroll', e => { if (ddDueno && e.target !== dd) cerrarDd(); }, true);
    dd.addEventListener('mousedown', e => {
        const b = e.target.closest('[data-i]');
        if (!b || !ddDueno) return;
        e.preventDefault();
        const p = dd._data[+b.dataset.i];
        const input = ddDueno, alElegir = ddAlElegir;
        input.value = `${p.codigo} - ${p.nombre}`;
        input.dataset.elegido = '1';
        cerrarDd();
        alElegir && alElegir(p);
    });
    el('modalFormula')?.addEventListener('hide.bs.modal', cerrarDd);

    function typeahead(input, onPick, onClear) {
        let timer = null;
        const cerrar = () => { if (ddDueno === input) cerrarDd(); };

        input.addEventListener('keydown', e => {
            if ((e.key === 'Backspace' || e.key === 'Delete') && input.dataset.elegido === '1') {
                e.preventDefault();
                input.value = '';
                input.dataset.elegido = '';
                cerrar();
                onClear && onClear();
            }
        });
        input.addEventListener('input', () => {
            if (input.dataset.elegido === '1') { input.dataset.elegido = ''; onClear && onClear(); }
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 2) { cerrar(); return; }
            timer = setTimeout(() => {
                fetch(`${URL}/buscar-productos-ajax?q=${encodeURIComponent(q)}`)
                    .then(r => r.json())
                    .then(res => {
                        if (document.activeElement !== input) return; // el usuario ya salió del campo
                        const data = res.data || [];
                        dd.innerHTML = data.length
                            ? data.map((p, i) => `<button type="button" class="list-group-item list-group-item-action small py-1" data-i="${i}">
                                    <code>${esc(p.codigo)}</code> ${esc(p.nombre)} <span class="text-muted">· ${esc(p.medida_nombre || '')}</span></button>`).join('')
                            : '<div class="list-group-item small text-muted">Sin productos inventariables con ese texto.</div>';
                        dd._data = data;
                        ddDueno = input;
                        ddAlElegir = onPick;
                        ubicarDd();
                        dd.classList.remove('d-none');
                    });
            }, 250);
        });
        input.addEventListener('blur', () => setTimeout(cerrar, 150));
    }

    /** Fija el producto que se fabrica (lo usan el buscador y el «crear producto al vuelo»). */
    function elegirProductoPrincipal(p) {
        el('pfo-producto-txt').value = `${p.codigo} - ${p.nombre}`;
        el('pfo-producto-txt').dataset.elegido = '1';
        el('pfo-producto').value = p.id;
        el('pfo-medida').textContent = p.medida_nombre || 'u';
        guardarBorrador();
    }

    /** Pone un producto en una línea de insumo o salida (mismo llenado que el buscador de la fila). */
    function elegirProductoEnLinea(l, p) {
        l.id_producto = p.id; l.producto = `${p.codigo} - ${p.nombre}`; l.medida = p.medida_nombre || '';
        // Unidad: vuelve a la del producto; el selector ofrece las de su mismo tipo.
        l.producto_id_medida = p.id_medida ? +p.id_medida : null;
        l.id_tipo_medida = p.id_tipo_medida ? +p.id_tipo_medida : null;
        l.id_unidad_medida = null;
    }

    typeahead(el('pfo-producto-txt'), elegirProductoPrincipal,
        () => { el('pfo-producto').value = ''; el('pfo-medida').textContent = 'u'; });

    // ── Líneas ───────────────────────────────────────────────────────────────

    function nuevaLinea(rol) {
        return { uid: ++seq, rol, id_producto: null, producto: '', medida: '', descripcion: '', modo_cantidad: 'proporcional',
                 cantidad: '', merma_pct: 0, exige_lote: false, reparto_pct: '', valor_neto_realizable: '',
                 id_unidad_medida: null, producto_id_medida: null, id_tipo_medida: null };
    }

    window.PFO_agregarLinea = function (rol) {
        lineas.push(nuevaLinea(rol));
        render();
    };
    window.PFO_agregarRecurso = function () {
        recursos.push({ uid: ++seq, tipo: 'mo', descripcion: '', base: 'unidad', cantidad: 1, tarifa: '' });
        renderRecursos();
    };
    window.PFO_quitar = function (uid) {
        lineas = lineas.filter(l => l.uid !== uid);
        recursos = recursos.filter(r => r.uid !== uid);
        render();
        renderRecursos();
    };
    window.PFO_set = function (uid, campo, valor) {
        const l = lineas.find(x => x.uid === uid) || recursos.find(x => x.uid === uid);
        if (!l) return;
        l[campo] = valor;
        if (campo === 'rol' || campo === 'modo_cantidad' || campo === 'tipo') { render(); renderRecursos(); }
        if (campo === 'cantidad' || campo === 'modo_cantidad') pintarSumaPct();
    };

    function opciones(mapa, sel) {
        return Object.entries(mapa).map(([k, v]) => `<option value="${k}" ${k === sel ? 'selected' : ''}>${esc(v)}</option>`).join('');
    }

    // ── Tablas de ítems: mismo diseño que el detalle de Facturas de Venta ─────
    // Celdas sin relleno, controles «input-detalle» sin borde (se marcan en azul al enfocar),
    // papelera para quitar y contador de ítems en el pie de cada tabla.
    const IN  = 'form-control form-control-sm input-detalle';
    const SEL = 'form-select form-select-sm input-detalle';

    function celdaProducto(l, primera) {
        return `<td class="${primera ? 'ps-3' : ''}"><input type="text" class="${IN} pfo-prod-txt" data-uid="${l.uid}" value="${esc(l.producto)}"
                    ${l.producto ? 'data-elegido="1"' : ''} placeholder="Buscar producto…" autocomplete="off" ${soloLectura ? 'disabled' : ''}></td>`;
    }
    function celdaTexto(html, clase) {
        return `<td class="pfo-celda-texto small text-muted ${clase || ''}">${html}</td>`;
    }
    function celdaQuitar(uid) {
        return `<td class="text-center" style="width:40px;">${soloLectura ? '' : `<button type="button" class="btn btn-link btn-sm text-danger p-0 shadow-none border-0" onclick="window.PFO_quitar(${uid})" title="Eliminar ítem"><i class="bi bi-trash3 fs-6"></i></button>`}</td>`;
    }
    // Unidad de la línea: «% del lote» no lleva unidad; si el producto tiene otras unidades de su
    // mismo tipo (kilo ↔ gramo ↔ quintal), se elige en un selector y el servidor convierte a la unidad
    // del producto al producir. Sin el SQL de la columna (PFO_UNIDAD_LINEA = false) queda como texto.
    const UNIDADES = window.PFO_UNIDADES || [];
    function celdaUnidad(l) {
        if (l.modo_cantidad === 'porcentaje') return celdaTexto('%');
        const comp = (window.PFO_UNIDAD_LINEA && l.id_tipo_medida && l.producto_id_medida)
            ? UNIDADES.filter(u => +u.id_tipo === +l.id_tipo_medida) : [];
        if (comp.length < 2) return celdaTexto(esc(l.medida));
        const elegida = l.id_unidad_medida || l.producto_id_medida;
        if (!comp.some(u => +u.id === +l.producto_id_medida)) comp.unshift({ id: l.producto_id_medida, nombre: l.medida });
        const opts = comp.map(u => `<option value="${u.id}" ${+u.id === +elegida ? 'selected' : ''}>${esc(u.nombre)}${+u.id === +l.producto_id_medida ? ' ★' : ''}</option>`).join('');
        return `<td><select class="${SEL}" ${soloLectura ? 'disabled' : ''} title="★ = unidad del producto (la del inventario)"
                    onchange="window.PFO_set(${l.uid}, 'id_unidad_medida', +this.value === ${+l.producto_id_medida} ? null : +this.value)">${opts}</select></td>`;
    }

    function filaVacia(colspan, texto) {
        return `<tr><td colspan="${colspan}" class="text-center text-muted py-3 small">${texto}</td></tr>`;
    }

    function render() {
        const dis = soloLectura ? 'disabled' : '';

        const ins = lineas.filter(l => l.rol === 'insumo');
        el('pfo-tbody-insumos').innerHTML = ins.length ? ins.map(l => `
            <tr>
                ${celdaProducto(l, true)}
                <td><select class="${SEL}" ${dis} onchange="window.PFO_set(${l.uid}, 'modo_cantidad', this.value)">${opciones(MODOS, l.modo_cantidad)}</select></td>
                <td><input type="number" step="any" min="0" class="${IN} text-end" value="${esc(l.cantidad)}" ${dis} oninput="window.PFO_set(${l.uid}, 'cantidad', this.value)"
                     placeholder="${l.modo_cantidad === 'porcentaje' ? '%' : ''}"></td>
                ${celdaUnidad(l)}
                <td><input type="number" step="any" min="0" max="99.99" class="${IN} text-end" value="${esc(l.merma_pct)}" ${dis} oninput="window.PFO_set(${l.uid}, 'merma_pct', this.value)"></td>
                <td class="text-center"><input type="checkbox" class="form-check-input m-0" ${l.exige_lote ? 'checked' : ''} ${dis} onchange="window.PFO_set(${l.uid}, 'exige_lote', this.checked)"></td>
                ${celdaQuitar(l.uid)}
            </tr>`).join('') : filaVacia(7, 'Sin insumos. Agregue lo que se consume para fabricar.');
        el('pfo-count-insumos').textContent = ins.length;

        const metodo = el('pfo-metodo').value || window.PFO_METODO_CFG;
        const sal = lineas.filter(l => l.rol !== 'insumo');
        el('pfo-tbody-salidas').innerHTML = sal.length ? sal.map(l => `
            <tr>
                <td class="ps-3"><select class="${SEL}" ${dis} onchange="window.PFO_set(${l.uid}, 'rol', this.value)">${opciones(ROLES_SALIDA, l.rol)}</select></td>
                ${l.rol === 'desperdicio' && !l.id_producto
                    ? `<td><input type="text" class="${IN}" value="${esc(l.descripcion)}" ${dis} placeholder="Descripción (p. ej. recortes sin valor)" oninput="window.PFO_set(${l.uid}, 'descripcion', this.value)"></td>`
                    : celdaProducto(l, false)}
                <td><select class="${SEL}" ${dis} onchange="window.PFO_set(${l.uid}, 'modo_cantidad', this.value)">${opciones(MODOS, l.modo_cantidad)}</select></td>
                <td><input type="number" step="any" min="0" class="${IN} text-end" value="${esc(l.cantidad)}" ${dis} oninput="window.PFO_set(${l.uid}, 'cantidad', this.value)"></td>
                ${celdaUnidad(l)}
                ${l.rol === 'coproducto'
                    ? `<td><input type="number" step="any" min="0" max="100" class="${IN} text-end" value="${esc(l.reparto_pct)}" ${dis || (metodo !== 'porcentaje' ? 'disabled' : '')} placeholder="${metodo === 'porcentaje' ? '% costo' : '—'}" oninput="window.PFO_set(${l.uid}, 'reparto_pct', this.value)"></td>`
                    : l.rol === 'subproducto'
                        ? `<td><input type="number" step="any" min="0" class="${IN} text-end" value="${esc(l.valor_neto_realizable)}" ${dis} placeholder="$ por unidad" oninput="window.PFO_set(${l.uid}, 'valor_neto_realizable', this.value)"></td>`
                        : celdaTexto('Sin valor', 'text-end')}
                ${celdaQuitar(l.uid)}
            </tr>`).join('') : filaVacia(7, 'Sin otras salidas: solo se obtiene el producto principal.');
        el('pfo-count-salidas').textContent = sal.length;

        // Buscadores de producto por fila.
        document.querySelectorAll('.pfo-prod-txt').forEach(inp => {
            if (inp._ta) return;
            inp._ta = true;
            const l = lineas.find(x => x.uid === +inp.dataset.uid);
            typeahead(inp, p => {
                elegirProductoEnLinea(l, p);
                render();
            }, () => { l.id_producto = null; l.producto = ''; l.medida = ''; });
        });

        guardarBorrador();
        const hayCopro = lineas.some(l => l.rol === 'coproducto');
        el('pfo-col-reparto-principal').classList.toggle('d-none', !(hayCopro && metodo === 'porcentaje'));
        pintarSumaPct();
    }

    function pintarSumaPct() {
        const pct = lineas.filter(l => l.rol === 'insumo' && l.modo_cantidad === 'porcentaje');
        el('pfo-suma-pct').textContent = pct.length
            ? `Insumos en % del lote: ${fmt(pct.reduce((s, l) => s + num(l.cantidad), 0), 2)} %`
            : '';
    }

    function renderRecursos() {
        const dis = soloLectura ? 'disabled' : '';
        el('pfo-tbody-recursos').innerHTML = recursos.length ? recursos.map(r => `
            <tr>
                <td class="ps-3"><select class="${SEL}" ${dis} onchange="window.PFO_set(${r.uid}, 'tipo', this.value)">${opciones(TIPOS_REC, r.tipo)}</select></td>
                <td><input type="text" class="${IN}" value="${esc(r.descripcion)}" ${dis} maxlength="150" placeholder="Ej.: Operario de troqueladora" oninput="window.PFO_set(${r.uid}, 'descripcion', this.value)"></td>
                <td><select class="${SEL}" ${dis} onchange="window.PFO_set(${r.uid}, 'base', this.value)">${opciones(BASES_REC, r.base)}</select></td>
                <td><input type="number" step="any" min="0" class="${IN} text-end" value="${esc(r.cantidad)}" ${dis} oninput="window.PFO_set(${r.uid}, 'cantidad', this.value)"></td>
                <td><input type="number" step="any" min="0" class="${IN} text-end" value="${esc(r.tarifa)}" ${dis} oninput="window.PFO_set(${r.uid}, 'tarifa', this.value)"></td>
                ${celdaQuitar(r.uid)}
            </tr>`).join('') : filaVacia(6, 'Sin costos de mano de obra ni indirectos.');
        el('pfo-count-recursos').textContent = recursos.length;
        guardarBorrador();
    }

    el('pfo-metodo')?.addEventListener('change', render);

    // ── Modal ────────────────────────────────────────────────────────────────

    function setSoloLectura(v) {
        soloLectura = v;
        ['pfo-nombre', 'pfo-producto-txt', 'pfo-cantidad-base', 'pfo-vida-util', 'pfo-metodo',
         'pfo-reparto-principal', 'pfo-instrucciones', 'pfo-observaciones'].forEach(id => { if (el(id)) el(id).disabled = v; });
        document.querySelectorAll('#modalFormula .pfo-editable').forEach(b => b.classList.toggle('d-none', v));
        actualizarBarra();
    }

    /**
     * Barra superior: las acciones de la fórmula solo con una fórmula guardada; el botón de nuevo
     * producto solo si la fórmula se puede editar. Si no queda nada visible, la barra se oculta.
     */
    function actualizarBarra() {
        const conFormula = !!actual;
        const btnProd = el('pfo-btn-nuevo-producto');
        const conProducto = !!btnProd && !soloLectura;
        el('pfo-acciones-formula').classList.toggle('d-none', !conFormula);
        if (btnProd) btnProd.classList.toggle('d-none', !conProducto);
        el('pfo-sep-catalogos')?.classList.toggle('d-none', !(conFormula && conProducto));
        el('pfo-acciones').classList.toggle('d-none', !(conFormula || conProducto));
    }

    function badge(estado) {
        const m = { vigente: ['success', 'Vigente'], inactiva: ['secondary', 'Inactiva'], borrador: ['warning', 'Borrador'] }[estado] || ['warning', 'Borrador'];
        return `<span class="badge bg-${m[0]} bg-opacity-10 text-${m[0]} border border-${m[0]} border-opacity-25">${m[1]}</span>`;
    }

    function limpiar() {
        borradorActivo = false;
        actual = null;
        lineas = [];
        recursos = [];
        el('pfo-id').value = '';
        ['pfo-codigo', 'pfo-nombre', 'pfo-producto-txt', 'pfo-producto', 'pfo-vida-util', 'pfo-metodo',
         'pfo-reparto-principal', 'pfo-instrucciones', 'pfo-observaciones', 'pfo-cantidad-estimar'].forEach(id => { if (el(id)) el(id).value = ''; });
        el('pfo-producto-txt').dataset.elegido = '';
        el('pfo-cantidad-base').value = 1;
        el('pfo-medida').textContent = 'u';
        el('pfo-titulo').textContent = 'Nueva fórmula';
        el('pfo-badge').innerHTML = '';
        el('pfo-acciones').classList.add('d-none');
        el('pfo-btn-eliminar').classList.add('d-none');
        el('pfo-aviso-componentes').classList.add('d-none');
        el('pfo-estimado').innerHTML = 'Pulse Calcular para ver el costo con los costos actuales de los insumos.';
        el('pfo-btn-guardar').classList.remove('d-none');
        el('pfo-btn-guardar').innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar';
        const primera = document.querySelector('#modalFormula .nav-link[data-bs-target="#pane-pfo-insumos"]');
        if (primera) bootstrap.Tab.getOrCreateInstance(primera).show();
        setSoloLectura(false);
    }

    window.PFO_nueva = async function () {
        if (!PERM.crear) { aviso('warning', 'Sin permiso', 'No tiene permiso para crear fórmulas.'); return; }
        limpiar();
        lineas.push(nuevaLinea('insumo'));

        const b = leerBorrador();
        if (b && b.cab) {
            const prod = b.cab.producto_txt ? `<b>${esc(b.cab.producto_txt)}</b>` : 'sin producto elegido';
            const html = `Tiene una fórmula nueva que no se guardó (${prod}), del <b>${fechaBorrador(b.ts)}</b>.<br>¿Quiere seguir con ella?`;
            let opcion = 'recuperar';
            if (typeof Swal !== 'undefined') {
                const r = await Swal.fire({
                    icon: 'question', title: 'Fórmula sin guardar', html,
                    showDenyButton: true, showCancelButton: true,
                    confirmButtonText: 'Recuperarla', denyButtonText: 'Empezar de cero', cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#0d6efd', denyButtonColor: '#6c757d',
                });
                opcion = r.isConfirmed ? 'recuperar' : (r.isDenied ? 'cero' : 'cancelar');
            } else {
                opcion = confirm('Tiene una fórmula nueva sin guardar. ¿Recuperarla? (Cancelar = empezar de cero)') ? 'recuperar' : 'cero';
            }
            if (opcion === 'cancelar') return;
            if (opcion === 'recuperar') aplicarBorrador(b);
            else borrarBorrador();
        }

        borradorActivo = true;
        render();
        renderRecursos();
        getModal().show();
    };

    // ── Ayudas «?» del modal ────────────────────────────────────────────────
    // Igual que en Configuración de Producción: se abren al pasar el mouse o tocar el ícono.
    // Este archivo carga ANTES que bootstrap.bundle (va al final del layout): se crean en DOMContentLoaded.
    function initAyudas() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Popover) return;
        document.querySelectorAll('.pfo-ayuda[data-bs-toggle="popover"]').forEach(a => {
            bootstrap.Popover.getOrCreateInstance(a, { trigger: 'hover focus', customClass: 'pfo-popover', container: 'body' });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAyudas);
    else initAyudas();
    // Al cerrar el modal no debe quedar una ventanita abierta.
    el('modalFormula')?.addEventListener('hide.bs.modal', () => {
        document.querySelectorAll('.pfo-ayuda').forEach(a => bootstrap.Popover.getInstance(a)?.hide());
    });

    // ── Crear producto al vuelo (CLAUDE.md §9) ─────────────────────────────
    // El botón de la barra abre el modal de Productos encima de la fórmula. Al guardar un producto
    // NUEVO, productos_modal.js se cierra solo y emite `productoGuardado`; aquí se vuelve a pedir
    // por el buscador de la fórmula (solo productos inventariables) y se coloca:
    //   1) como producto que se fabrica, si aún no hay uno;
    //   2) si no, en la pestaña abierta: «Otras salidas» → primera salida vacía o una nueva
    //      (coproducto); cualquier otra → primer insumo vacío o uno nuevo.

    window.PFO_nuevoProducto = function () {
        if (soloLectura) return;
        if (typeof window.abrirModalProductoCrear !== 'function') { aviso('warning', 'No disponible', 'No tiene permiso para crear productos.'); return; }
        window.abrirModalProductoCrear();
    };

    document.addEventListener('productoGuardado', async (e) => {
        const res = e.detail || {};
        if (!res.ok || !res.id || res.nuevo !== true) return;
        if (!el('modalFormula')?.classList.contains('show') || soloLectura) return;

        const termino = res.codigo || res.nombre || '';
        if (!termino) return;
        try {
            const json = await fetch(`${URL}/buscar-productos-ajax?q=${encodeURIComponent(termino)}`).then(r => r.json());
            const p = (json.data || []).find(x => String(x.id) === String(res.id));
            if (!p) {
                aviso('info', 'Producto creado', 'El producto se creó, pero no aparece en la fórmula: aquí solo se usan productos que llevan inventario (no servicios).');
                return;
            }

            if (!el('pfo-producto').value) {
                elegirProductoPrincipal(p);
                return;
            }

            const enSalidas = !!document.querySelector('#modalFormula .nav-link.active[data-bs-target="#pane-pfo-salidas"]');
            let l = lineas.find(x => (enSalidas ? x.rol !== 'insumo' : x.rol === 'insumo') && !x.id_producto && !(x.descripcion || '').trim());
            if (!l) {
                l = nuevaLinea(enSalidas ? 'coproducto' : 'insumo');
                lineas.push(l);
            }
            elegirProductoEnLinea(l, p);
            render();
        } catch (err) {
            console.error('Error al recuperar el producto recién creado:', err);
        }
    });

    window.PFO_abrir = function (id) {
        return fetch(`${URL}/get-formula-ajax?id=${id}`)
            .then(r => r.json())
            .then(res => {
                if (!res.ok) { aviso('error', 'Error', mensajeDe(res)); return; }
                cargar(res.data);
                getModal().show();
            })
            .catch(e => { console.error(e); aviso('error', 'Error', 'No se pudo abrir la fórmula.'); });
    };

    function cargar(f) {
        limpiar();
        actual = f;
        el('pfo-id').value = f.id;
        el('pfo-titulo').textContent = `${f.codigo} · v${f.version}`;
        el('pfo-badge').innerHTML = badge(f.estado);
        el('pfo-codigo').value = f.codigo;
        el('pfo-nombre').value = f.nombre;
        el('pfo-producto').value = f.id_producto;
        el('pfo-producto-txt').value = `${f.producto_codigo} - ${f.producto_nombre}`;
        el('pfo-producto-txt').dataset.elegido = '1';
        el('pfo-medida').textContent = f.medida_nombre || 'u';
        el('pfo-cantidad-base').value = num(f.cantidad_base);
        el('pfo-vida-util').value = f.vida_util_dias ?? '';
        el('pfo-metodo').value = f.metodo_reparto || '';
        el('pfo-instrucciones').value = f.instrucciones || '';
        el('pfo-observaciones').value = f.observaciones || '';
        el('pfo-cantidad-estimar').value = num(f.cantidad_base);

        (f.lineas || []).forEach(l => {
            if (l.rol === 'principal') {
                el('pfo-reparto-principal').value = l.reparto_pct ?? '';
                return;
            }
            lineas.push({
                uid: ++seq, rol: l.rol, id_producto: l.id_producto ? +l.id_producto : null,
                producto: l.id_producto ? `${l.producto_codigo} - ${l.producto_nombre}` : '', medida: l.medida_nombre || '',
                descripcion: l.descripcion || '', modo_cantidad: l.modo_cantidad, cantidad: num(l.cantidad),
                merma_pct: num(l.merma_pct), exige_lote: !!l.exige_lote && l.exige_lote !== 'f',
                reparto_pct: l.reparto_pct ?? '', valor_neto_realizable: l.valor_neto_realizable ?? '',
                id_unidad_medida: l.id_unidad_medida ? +l.id_unidad_medida : null,
                producto_id_medida: l.producto_id_medida ? +l.producto_id_medida : null,
                id_tipo_medida: l.id_tipo_medida ? +l.id_tipo_medida : null,
            });
        });
        (f.recursos || []).forEach(r => recursos.push({ uid: ++seq, tipo: r.tipo, descripcion: r.descripcion || '',
            base: r.base, cantidad: num(r.cantidad), tarifa: num(r.tarifa) }));

        const usada = !!f.usada_en_ordenes;
        setSoloLectura(usada || !PERM.actualizar);
        el('pfo-acciones').classList.remove('d-none');
        el('pfo-btn-activar').classList.toggle('d-none', f.estado === 'vigente' || !PERM.actualizar);
        el('pfo-btn-inactivar').classList.toggle('d-none', f.estado !== 'vigente' || !PERM.actualizar);
        el('pfo-btn-version').classList.toggle('d-none', !PERM.crear);
        el('pfo-aviso-usada').classList.toggle('d-none', !usada);
        el('pfo-btn-guardar').classList.toggle('d-none', usada || !PERM.actualizar);
        el('pfo-btn-guardar').innerHTML = '<i class="bi bi-check-lg me-1"></i> Actualizar';
        el('pfo-btn-eliminar').classList.toggle('d-none', usada || !PERM.eliminar);

        if (f.tiene_componentes_venta) {
            el('pfo-aviso-componentes').innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Este producto tiene <b>componentes de venta</b> en su ficha '
                + '(se descuentan al venderlo). Mientras los tenga, la fórmula no se puede activar: sus insumos se descontarían dos veces.';
            el('pfo-aviso-componentes').classList.remove('d-none');
        }

        // Quién creó/modificó y el historial están en la pestaña «Información» (InformacionRegistro).

        render();
        renderRecursos();
        if (f.estimado) pintarEstimado(f.estimado);
    }

    function fechaHora(v) {
        const d = new Date(String(v).replace(' ', 'T'));
        if (isNaN(d)) return v;
        const p = n => String(n).padStart(2, '0');
        return `${p(d.getDate())}-${p(d.getMonth() + 1)}-${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
    }

    function datosFormulario() {
        const fd = new FormData();
        fd.append('id', el('pfo-id').value || '0');
        fd.append('nombre', el('pfo-nombre').value);
        fd.append('id_producto', el('pfo-producto').value || '0');
        fd.append('cantidad_base', el('pfo-cantidad-base').value);
        fd.append('vida_util_dias', el('pfo-vida-util').value);
        fd.append('metodo_reparto', el('pfo-metodo').value);
        fd.append('reparto_pct_principal', el('pfo-reparto-principal').value);
        fd.append('instrucciones', el('pfo-instrucciones').value);
        fd.append('observaciones', el('pfo-observaciones').value);
        fd.append('lineas', JSON.stringify(lineas.map(l => ({
            rol: l.rol, id_producto: l.id_producto, descripcion: l.descripcion, modo_cantidad: l.modo_cantidad,
            cantidad: l.cantidad, merma_pct: l.merma_pct, exige_lote: l.exige_lote,
            reparto_pct: l.reparto_pct, valor_neto_realizable: l.valor_neto_realizable,
            id_unidad_medida: l.modo_cantidad === 'porcentaje' ? null : (l.id_unidad_medida || null),
        }))));
        fd.append('recursos', JSON.stringify(recursos.map(r => ({
            tipo: r.tipo, descripcion: r.descripcion, base: r.base, cantidad: r.cantidad, tarifa: r.tarifa,
        }))));
        return fd;
    }

    window.PFO_guardar = function () {
        if (guardando) return;
        const nuevo = !el('pfo-id').value;
        if (nuevo ? !PERM.crear : !PERM.actualizar) { aviso('warning', 'Sin permiso', 'No tiene permiso para guardar fórmulas.'); return; }
        if (!el('pfo-producto').value) { aviso('warning', 'Falta el producto', 'Elija el producto que se fabrica.'); return; }
        const sinProd = lineas.find(l => l.rol !== 'desperdicio' && !l.id_producto);
        if (sinProd) { aviso('warning', 'Falta un producto', 'Hay una línea sin producto elegido: búsquelo y selecciónelo de la lista, o quite la línea.'); return; }

        guardando = true;
        const btn = el('pfo-btn-guardar');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando…';
        let creada = false;

        post('guardar-ajax', datosFormulario())
            .then(res => {
                if (!res.ok) { aviso('error', 'No se guardó', mensajeDe(res)); return; }
                creada = true;
                if (nuevo) { borradorActivo = false; borrarBorrador(); }
                aviso('success', 'Fórmula guardada', res.mensaje);
                window.PFO_buscar(window.PFO_pagina);
                // El botón vuelve a habilitarse solo cuando el modal ya tiene el id guardado.
                return window.PFO_abrir(res.id);
            })
            .catch(e => { console.error(e); aviso('error', 'Error', 'No se recibió respuesta del servidor.'); })
            .finally(() => {
                guardando = false;
                btn.disabled = false;
                if (!creada) btn.innerHTML = el('pfo-id').value ? '<i class="bi bi-check-lg me-1"></i> Actualizar' : '<i class="bi bi-check-lg me-1"></i> Guardar';
            });
    };

    function accion(accionUrl, titulo, html, boton, color, okTitulo) {
        const id = el('pfo-id').value;
        if (!id || guardando) return;
        confirmar(titulo, html, boton, color).then(si => {
            if (!si) return;
            guardando = true;
            post(accionUrl, { id })
                .then(res => {
                    if (!res.ok) { aviso('error', 'No se pudo', mensajeDe(res)); return; }
                    aviso('success', okTitulo, res.mensaje);
                    window.PFO_buscar(window.PFO_pagina);
                    if (accionUrl === 'eliminar-ajax') { getModal().hide(); return; }
                    return window.PFO_abrir(res.id || id);
                })
                .finally(() => { guardando = false; });
        });
    }

    window.PFO_activar = () => accion('activar-ajax', '¿Activar la fórmula?',
        'Las órdenes nuevas de este producto la usarán. Si había otra versión vigente, quedará inactiva.', 'Sí, activar', '#198754', 'Fórmula vigente');
    window.PFO_inactivar = () => accion('inactivar-ajax', '¿Inactivar la fórmula?',
        'No se podrán crear órdenes nuevas con ella. Las órdenes existentes no cambian.', 'Sí, inactivar', '#6c757d', 'Fórmula inactiva');
    window.PFO_nuevaVersion = () => accion('nueva-version-ajax', '¿Crear una nueva versión?',
        'Se copia esta fórmula como nueva versión en borrador. Podrá editarla y activarla.', 'Sí, crear', '#0d6efd', 'Nueva versión creada');
    window.PFO_eliminar = () => accion('eliminar-ajax', '¿Eliminar la fórmula?',
        'Dejará de aparecer en el listado.', 'Sí, eliminar', '#dc3545', 'Fórmula eliminada');

    // ── Costo estimado ───────────────────────────────────────────────────────

    window.PFO_estimar = function () {
        if (!el('pfo-producto').value) return;
        const fd = datosFormulario();
        fd.append('cantidad_estimar', el('pfo-cantidad-estimar').value || el('pfo-cantidad-base').value);
        el('pfo-estimado').innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span>';
        post('estimar-ajax', fd).then(res => {
            if (!res.ok) { el('pfo-estimado').innerHTML = `<span class="text-danger">${esc(mensajeDe(res))}</span>`; return; }
            pintarEstimado(res.data);
        });
    };

    function pintarEstimado(e) {
        if (!el('pfo-cantidad-estimar').value) el('pfo-cantidad-estimar').value = num(e.cantidad);
        const nombres = {};
        lineas.filter(l => l.rol === 'insumo').forEach((l, i) => { nombres[i] = l.producto || '—'; });
        const ins = (e.lineas || []).filter(l => l.rol === 'insumo');
        const filas = ins.map((l, i) => `<tr><td>${esc(nombres[i] || '')}</td><td class="text-end">${fmtCant(l.cantidad)} <span class="text-muted">${esc(l.medida || '')}</span></td>
            <td class="text-end">${money(l.costo_unitario)}</td><td class="text-end">${money(l.costo_total)}</td></tr>`).join('');
        el('pfo-estimado').innerHTML = `
            <div class="table-responsive border rounded mb-2"><table class="table table-sm mb-0" style="font-size:.78rem;">
                <thead class="table-light"><tr><th>Insumo</th><th class="text-end">Cantidad (con merma)</th><th class="text-end">Costo unit.</th><th class="text-end">Costo</th></tr></thead>
                <tbody>${filas || '<tr><td colspan="4" class="text-muted text-center">Sin insumos.</td></tr>'}</tbody>
            </table></div>
            <div class="d-flex flex-wrap gap-4 text-dark">
                <div>Materiales: <b>${money(e.materiales)}</b></div>
                <div>Mano de obra: <b>${money(e.mano_obra)}</b></div>
                <div>Costos indirectos: <b>${money(e.cif)}</b></div>
                <div>Total: <b>${money(e.total)}</b></div>
                <div>Por unidad: <b class="text-primary">${money(e.costo_unitario)}</b></div>
            </div>
            <div class="form-text">Con el costo promedio actual de cada insumo en la bodega de insumos (o el de su ficha). El costo real sale de los consumos al cerrar la orden.</div>`;
    }
})();
