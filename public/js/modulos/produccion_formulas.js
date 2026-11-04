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
        const params = new URLSearchParams({
            b: el('pfo-buscar').value || '', estado: el('pfo-estado').value || '', page: String(window.PFO_pagina),
        });
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

    let timerBuscar = null;
    el('pfo-buscar')?.addEventListener('input', () => {
        clearTimeout(timerBuscar);
        timerBuscar = setTimeout(() => window.PFO_buscar(1), 400);
    });

    const sorter = window.CMG_initSort ? window.CMG_initSort(window.PFO_MODULO, (col, dir, sorts) => {
        window.currentSorts = sorts;
        window.PFO_buscar(1);
    }, { sorts: window.currentSorts, multi: true }) : null;

    // ── Buscador de productos (selección tipo chip) ──────────────────────────

    function typeahead(input, onPick, onClear) {
        const dd = document.createElement('div');
        dd.className = 'list-group position-absolute shadow pfo-dd d-none';
        dd.style.minWidth = '320px';
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
                        const data = res.data || [];
                        dd.innerHTML = data.length
                            ? data.map((p, i) => `<button type="button" class="list-group-item list-group-item-action small py-1" data-i="${i}">
                                    <code>${esc(p.codigo)}</code> ${esc(p.nombre)} <span class="text-muted">· ${esc(p.medida_nombre || '')}</span></button>`).join('')
                            : '<div class="list-group-item small text-muted">Sin productos inventariables con ese texto.</div>';
                        dd._data = data;
                        dd.classList.remove('d-none');
                    });
            }, 250);
        });
        dd.addEventListener('mousedown', e => {
            const b = e.target.closest('[data-i]');
            if (!b) return;
            e.preventDefault();
            const p = dd._data[+b.dataset.i];
            input.value = `${p.codigo} - ${p.nombre}`;
            input.dataset.elegido = '1';
            cerrar();
            onPick(p);
        });
        input.addEventListener('blur', () => setTimeout(cerrar, 150));
    }

    typeahead(el('pfo-producto-txt'), p => {
        el('pfo-producto').value = p.id;
        el('pfo-medida').textContent = p.medida_nombre || 'u';
        if (!el('pfo-nombre').value) el('pfo-nombre').value = p.nombre;
        if (!el('pfo-codigo').value) el('pfo-codigo').value = ('F-' + p.codigo).toUpperCase().slice(0, 30);
    }, () => { el('pfo-producto').value = ''; el('pfo-medida').textContent = 'u'; });

    // ── Líneas ───────────────────────────────────────────────────────────────

    function nuevaLinea(rol) {
        return { uid: ++seq, rol, id_producto: null, producto: '', medida: '', descripcion: '', modo_cantidad: 'proporcional',
                 cantidad: '', merma_pct: 0, exige_lote: false, reparto_pct: '', valor_neto_realizable: '' };
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

    function celdaProducto(l) {
        return `<td class="p-0"><input type="text" class="pfo-prod-txt" data-uid="${l.uid}" value="${esc(l.producto)}"
                    ${l.producto ? 'data-elegido="1"' : ''} placeholder="Buscar producto…" autocomplete="off" ${soloLectura ? 'disabled' : ''}></td>`;
    }

    function render() {
        const dis = soloLectura ? 'disabled' : '';
        const quitar = uid => soloLectura ? '' : `<button type="button" class="btn btn-link btn-sm text-danger p-0" onclick="window.PFO_quitar(${uid})" title="Quitar"><i class="bi bi-x-lg"></i></button>`;

        const ins = lineas.filter(l => l.rol === 'insumo');
        el('pfo-tbody-insumos').innerHTML = ins.length ? ins.map(l => `
            <tr>
                ${celdaProducto(l)}
                <td class="p-0"><select ${dis} onchange="window.PFO_set(${l.uid}, 'modo_cantidad', this.value)">${opciones(MODOS, l.modo_cantidad)}</select></td>
                <td class="p-0"><input type="number" step="any" min="0" class="text-end" value="${esc(l.cantidad)}" ${dis} oninput="window.PFO_set(${l.uid}, 'cantidad', this.value)"
                     placeholder="${l.modo_cantidad === 'porcentaje' ? '%' : ''}"></td>
                <td class="small text-muted px-1">${l.modo_cantidad === 'porcentaje' ? '%' : esc(l.medida)}</td>
                <td class="p-0"><input type="number" step="any" min="0" max="99.99" class="text-end" value="${esc(l.merma_pct)}" ${dis} oninput="window.PFO_set(${l.uid}, 'merma_pct', this.value)"></td>
                <td class="text-center"><input type="checkbox" class="form-check-input m-0" style="width:auto;height:auto;" ${l.exige_lote ? 'checked' : ''} ${dis} onchange="window.PFO_set(${l.uid}, 'exige_lote', this.checked)"></td>
                <td class="text-center">${quitar(l.uid)}</td>
            </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted py-3">Sin insumos. Agregue lo que se consume para fabricar.</td></tr>';

        const metodo = el('pfo-metodo').value || window.PFO_METODO_CFG;
        const sal = lineas.filter(l => l.rol !== 'insumo');
        el('pfo-tbody-salidas').innerHTML = sal.length ? sal.map(l => `
            <tr>
                <td class="p-0"><select ${dis} onchange="window.PFO_set(${l.uid}, 'rol', this.value)">${opciones(ROLES_SALIDA, l.rol)}</select></td>
                ${l.rol === 'desperdicio' && !l.id_producto
                    ? `<td class="p-0"><input type="text" value="${esc(l.descripcion)}" ${dis} placeholder="Descripción (p. ej. recortes sin valor)" oninput="window.PFO_set(${l.uid}, 'descripcion', this.value)"></td>`
                    : celdaProducto(l)}
                <td class="p-0"><select ${dis} onchange="window.PFO_set(${l.uid}, 'modo_cantidad', this.value)">${opciones(MODOS, l.modo_cantidad)}</select></td>
                <td class="p-0"><input type="number" step="any" min="0" class="text-end" value="${esc(l.cantidad)}" ${dis} oninput="window.PFO_set(${l.uid}, 'cantidad', this.value)"></td>
                <td class="small text-muted px-1">${l.modo_cantidad === 'porcentaje' ? '%' : esc(l.medida)}</td>
                <td class="p-0">${l.rol === 'coproducto'
                    ? `<input type="number" step="any" min="0" max="100" class="text-end" value="${esc(l.reparto_pct)}" ${dis || (metodo !== 'porcentaje' ? 'disabled' : '')} placeholder="${metodo === 'porcentaje' ? '% costo' : '—'}" oninput="window.PFO_set(${l.uid}, 'reparto_pct', this.value)">`
                    : l.rol === 'subproducto'
                        ? `<input type="number" step="any" min="0" class="text-end" value="${esc(l.valor_neto_realizable)}" ${dis} placeholder="$ por unidad" oninput="window.PFO_set(${l.uid}, 'valor_neto_realizable', this.value)">`
                        : '<span class="small text-muted px-1">Sin valor</span>'}</td>
                <td class="text-center">${quitar(l.uid)}</td>
            </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted py-3">Sin otras salidas: solo se obtiene el producto principal.</td></tr>';

        // Buscadores de producto por fila.
        document.querySelectorAll('.pfo-prod-txt').forEach(inp => {
            if (inp._ta) return;
            inp._ta = true;
            const l = lineas.find(x => x.uid === +inp.dataset.uid);
            typeahead(inp, p => {
                l.id_producto = p.id; l.producto = `${p.codigo} - ${p.nombre}`; l.medida = p.medida_nombre || '';
                render();
            }, () => { l.id_producto = null; l.producto = ''; l.medida = ''; });
        });

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
                <td class="p-0"><select ${dis} onchange="window.PFO_set(${r.uid}, 'tipo', this.value)">${opciones(TIPOS_REC, r.tipo)}</select></td>
                <td class="p-0"><input type="text" value="${esc(r.descripcion)}" ${dis} maxlength="150" placeholder="Ej.: Operario de troqueladora" oninput="window.PFO_set(${r.uid}, 'descripcion', this.value)"></td>
                <td class="p-0"><select ${dis} onchange="window.PFO_set(${r.uid}, 'base', this.value)">${opciones(BASES_REC, r.base)}</select></td>
                <td class="p-0"><input type="number" step="any" min="0" class="text-end" value="${esc(r.cantidad)}" ${dis} oninput="window.PFO_set(${r.uid}, 'cantidad', this.value)"></td>
                <td class="p-0"><input type="number" step="any" min="0" class="text-end" value="${esc(r.tarifa)}" ${dis} oninput="window.PFO_set(${r.uid}, 'tarifa', this.value)"></td>
                <td class="text-center">${soloLectura ? '' : `<button type="button" class="btn btn-link btn-sm text-danger p-0" onclick="window.PFO_quitar(${r.uid})"><i class="bi bi-x-lg"></i></button>`}</td>
            </tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted py-3">Sin costos de mano de obra ni indirectos.</td></tr>';
    }

    el('pfo-metodo')?.addEventListener('change', render);

    // ── Modal ────────────────────────────────────────────────────────────────

    function setSoloLectura(v) {
        soloLectura = v;
        ['pfo-codigo', 'pfo-nombre', 'pfo-producto-txt', 'pfo-cantidad-base', 'pfo-vida-util', 'pfo-metodo',
         'pfo-reparto-principal', 'pfo-instrucciones', 'pfo-observaciones'].forEach(id => { if (el(id)) el(id).disabled = v; });
        document.querySelectorAll('#modalFormula .pfo-editable').forEach(b => b.classList.toggle('d-none', v));
    }

    function badge(estado) {
        const m = { vigente: ['success', 'Vigente'], inactiva: ['secondary', 'Inactiva'], borrador: ['warning', 'Borrador'] }[estado] || ['warning', 'Borrador'];
        return `<span class="badge bg-${m[0]} bg-opacity-10 text-${m[0]} border border-${m[0]} border-opacity-25">${m[1]}</span>`;
    }

    function limpiar() {
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
        el('pfo-auditoria').classList.add('d-none');
        el('pfo-estimado').innerHTML = 'Pulse Calcular para ver el costo con los costos actuales de los insumos.';
        el('pfo-btn-guardar').classList.remove('d-none');
        el('pfo-btn-guardar').innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar';
        const primera = document.querySelector('#modalFormula .nav-link[data-bs-target="#pane-pfo-insumos"]');
        if (primera) bootstrap.Tab.getOrCreateInstance(primera).show();
        setSoloLectura(false);
    }

    window.PFO_nueva = function () {
        if (!PERM.crear) { aviso('warning', 'Sin permiso', 'No tiene permiso para crear fórmulas.'); return; }
        limpiar();
        lineas.push(nuevaLinea('insumo'));
        render();
        renderRecursos();
        getModal().show();
    };

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

        const quien = [f.creado_por ? `Creó: ${esc(f.creado_por)}` : '', f.updated_at ? `Modificado: ${esc(fechaHora(f.updated_at))}${f.modificado_por ? ' por ' + esc(f.modificado_por) : ''}` : '']
            .filter(Boolean).join(' · ');
        if (quien) { el('pfo-auditoria').innerHTML = quien; el('pfo-auditoria').classList.remove('d-none'); }

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
        fd.append('codigo', el('pfo-codigo').value);
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
        const filas = ins.map((l, i) => `<tr><td>${esc(nombres[i] || '')}</td><td class="text-end">${fmtCant(l.cantidad)}</td>
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
