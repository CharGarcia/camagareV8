/**
 * Componente reusable: buscador de texto libre + botón "Filtros" que abre un modal
 * con todos los filtros del módulo + chips de los filtros activos junto al input.
 *
 * Sustituye al buscador con dropdown (filtros_busqueda.js). El contrato con el
 * backend es el mismo: el input hidden recibe el string serializado
 * `clave:valor clave:a..b texto libre`, que lee App\Helpers\FiltrosBusqueda::parsear().
 *
 * Uso desde un módulo:
 *
 *   new FiltrosModal({
 *     containerId:   'fmBuscador',       // div vacío donde se renderiza botón + input + chips
 *     hiddenInputId: 'buscar',           // input hidden con el string serializado
 *     placeholder:   'Buscar en todas las columnas...',
 *     titulo:        'Filtros de ingresos',
 *     inputWidth:    320,                // px del input de texto libre
 *     extraId:       'fmExtra',          // opcional: contenedor con botones del módulo (columnas, PDF,
 *                                        // Excel…) que se mueven al final del mismo grupo, pegados
 *     fields: [
 *       // tab:   pestaña del modal (p. ej. 'Ingreso' / 'Detalles'); con una sola no se pintan pestañas
 *       // grupo: encabezado dentro de la pestaña (se pinta cuando cambia)
 *       // col:   ancho Bootstrap dentro del modal (col-md-N); 4 por defecto
 *       { key: 'fecha',   label: 'Fecha',   type: 'date_range',   grupo: 'Documento', col: 6, atajos: true },
 *       { key: 'estado',  label: 'Estado',  type: 'select',       grupo: 'Documento',
 *         options: [{ v: 'registrado', l: 'Registrado' }, ...] },
 *       { key: 'cliente', label: 'Cliente', type: 'text',         grupo: 'Tercero' },
 *       { key: 'monto',   label: 'Monto',   type: 'number_range', grupo: 'Documento', col: 6 },
 *     ],
 *     // Opcional: búsqueda libre DENTRO de los registros (líneas, pagos…) en una pestaña.
 *     // El endpoint recibe ?q= y responde { rows: [ {…} ] }; cada fila trae los datos de la
 *     // línea que coincidió y del registro padre. Clic en la fila → onSelect (p. ej. filtrar
 *     // el listado a ese registro con fm.aplicarFiltro({ key:'numero', value: row.numero })).
 *     busquedaDetalle: {
 *       tab: 'Detalles', url: '/modulos/ingresos/buscarDetallesAjax',
 *       label: 'Buscar dentro de los ingresos', placeholder: 'Nº de factura, referencia, cheque…',
 *       columns: [{ key: 'origen', label: 'Tipo' }, { key: 'monto', label: 'Monto', align: 'end' }, …],
 *       onSelect: (row, fm) => fm.aplicarFiltro({ key: 'numero', value: row.numero_ingreso }),
 *       onOpen:   (row, fm) => { fm.hide(); abrirModalVer(row.id_ingreso); },
 *     },
 *     onApply: () => window.MIMODULO_fetchSearch(1),
 *   }).init();
 */
(function () {
    'use strict';

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function ymd(d) {
        if (typeof window.CMG_fechaLocal === 'function') return window.CMG_fechaLocal(d);
        const p = n => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
    }

    // yyyy-mm-dd → dd-mm-yyyy (formato estándar de fechas del sistema)
    function fechaBonita(v) {
        const m = String(v || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        return m ? `${m[3]}-${m[2]}-${m[1]}` : v;
    }

    const atajosFecha = [
        { id: 'hoy',        label: 'Hoy',        rango: () => { const d = new Date(); return [ymd(d), ymd(d)]; } },
        { id: 'semana',     label: 'Esta semana', rango: () => {
            const d = new Date(); const dia = (d.getDay() + 6) % 7; // lunes = 0
            const ini = new Date(d); ini.setDate(d.getDate() - dia);
            const fin = new Date(ini); fin.setDate(ini.getDate() + 6);
            return [ymd(ini), ymd(fin)];
        } },
        { id: 'mes',        label: 'Este mes',   rango: () => { const d = new Date(); return [ymd(new Date(d.getFullYear(), d.getMonth(), 1)), ymd(new Date(d.getFullYear(), d.getMonth() + 1, 0))]; } },
        { id: 'mes_pasado', label: 'Mes pasado', rango: () => { const d = new Date(); d.setMonth(d.getMonth() - 1); return [ymd(new Date(d.getFullYear(), d.getMonth(), 1)), ymd(new Date(d.getFullYear(), d.getMonth() + 1, 0))]; } },
        { id: 'anio',       label: 'Este año',   rango: () => { const y = new Date().getFullYear(); return [`${y}-01-01`, `${y}-12-31`]; } },
    ];

    function parseValor(key, valor, neg) {
        let m = valor.match(/^(.+?)\.\.(.+)$/);
        if (m) return { key, op: 'BETWEEN', value: [m[1].trim(), m[2].trim()], neg };
        m = valor.match(/^(>=|<=|>|<|=)(.+)$/);
        if (m) return { key, op: m[1], value: m[2].trim(), neg };
        if (valor.indexOf(',') !== -1) {
            return { key, op: 'IN', value: valor.split(',').map(v => v.trim()).filter(Boolean), neg };
        }
        return { key, op: 'ILIKE', value: valor, neg };
    }

    function parseInitial(buscar) {
        const filters = [];
        let texto = buscar || '';
        const regex = /(-?)([a-záéíóúñ_]+):("([^"]*)"|([^\s"]+))/giu;
        let m;
        while ((m = regex.exec(buscar || '')) !== null) {
            const neg = m[1] === '-';
            const key = m[2].toLowerCase();
            const valor = (m[4] !== undefined && m[4] !== '') ? m[4] : m[5];
            filters.push(parseValor(key, valor, neg));
            texto = texto.replace(m[0], '');
        }
        return { filters, texto: texto.trim().replace(/\s+/g, ' ') };
    }

    let seq = 0;

    class FiltrosModal {
        constructor(opts) {
            this.opts = Object.assign({
                containerId: null,
                hiddenInputId: null,
                initialValue: '',
                placeholder: 'Buscar...',
                titulo: 'Filtros',
                inputWidth: 320,
                extraId: null,      // id de un contenedor con botones del módulo que se pegan al final del grupo
                tabDefault: 'General', // pestaña de los campos sin `tab`
                // Búsqueda libre dentro de los registros, en una pestaña (ver doc arriba).
                busquedaDetalle: null, // { tab, url, label, placeholder, hint, minChars, max, columns:[{key,label,align}], onSelect(row, fm), onOpen(row, fm) }
                fields: [],
                // Debe devolver la promesa de la búsqueda (p. ej. una función async):
                // el indicador de carga se apaga cuando esa promesa termina.
                onApply: () => {},
                debounceMs: 400,
                // Opcional: selector CSS de lo que se atenúa mientras se busca (p. ej. el
                // tbody del listado). Sin él, solo se muestra el spinner de la caja.
                loadingTarget: null,
            }, opts || {});
            this.state = { filters: [], inputText: '' };
            this.applySeq = 0;
            this.uid = 'fm' + (++seq);
        }

        init() {
            const container = document.getElementById(this.opts.containerId);
            if (!container) return;
            this.container = container;

            // Un solo input-group pegado: [Filtros] [🔍 texto] [×] [+ botones del módulo].
            // El ancho fijo lo lleva el input (no el grupo) para que los botones que se
            // anexan no lo achiquen.
            container.classList.add('fm-search');
            container.innerHTML = `
                <div class="input-group input-group-sm fm-input-group">
                    <button type="button" class="btn btn-outline-secondary fm-btn" title="Filtros">
                        <i class="bi bi-funnel"></i><span class="badge rounded-pill bg-primary fm-badge d-none">0</span>
                    </button>
                    <div class="form-control fm-box" style="--fm-w:${parseInt(this.opts.inputWidth, 10) || 320}px" tabindex="-1">
                        <div class="fm-chips"></div>
                        <input type="text" class="fm-typer" placeholder="${escapeHtml(this.opts.placeholder)}" autocomplete="off">
                        <span class="fm-spin d-none" role="status" title="Buscando..."><span class="spinner-border text-primary"></span><span class="visually-hidden">Buscando...</span></span>
                    </div>
                </div>
            `;
            this.elGroup  = container.querySelector('.fm-input-group');
            this.elBtn    = container.querySelector('.fm-btn');
            this.elBadge  = container.querySelector('.fm-badge');
            this.elBox    = container.querySelector('.fm-box');
            this.elInput  = container.querySelector('.fm-typer');
            this.elSpin   = container.querySelector('.fm-spin');
            this.elChips  = container.querySelector('.fm-chips');
            this.elHidden = document.getElementById(this.opts.hiddenInputId);

            // Botones propios del módulo (columnas, PDF, Excel…): se mueven dentro del
            // mismo input-group, pegados al final. `extraId` es el contenedor que los
            // trae; si el JS no corre, quedan donde estaban (fallback visible).
            // Van dentro de un <span class="fm-extra"> para que en móvil pasen a su
            // propia línea (el CSS del componente los pega o los separa según el ancho).
            if (this.opts.extraId) {
                const extra = document.getElementById(this.opts.extraId);
                if (extra) {
                    const wrap = document.createElement('span');
                    wrap.className = 'fm-extra';
                    while (extra.firstChild) wrap.appendChild(extra.firstChild);
                    extra.remove();
                    this.elGroup.appendChild(wrap);
                }
            }

            this.buildModal();

            const base = (this.elHidden ? this.elHidden.value : '') || this.opts.initialValue || '';
            const ini = parseInitial(base);
            this.state.filters   = ini.filters;
            this.state.inputText = ini.texto;
            this.elInput.value   = ini.texto;
            this.renderChips();

            this.bindEvents();
        }

        // ── Modal ────────────────────────────────────────────────────────────────
        buildModal() {
            const id = `${this.uid}-modal`;
            const wrap = document.createElement('div');
            wrap.innerHTML = `
                <div class="modal fade fm-modal" id="${id}" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                      <div class="modal-header py-2">
                        <h6 class="modal-title fw-bold"><i class="bi bi-funnel me-2 text-primary"></i>${escapeHtml(this.opts.titulo)}</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                      </div>
                      <div class="modal-body">
                        <form class="fm-form" onsubmit="return false;">${this.renderFields()}</form>
                      </div>
                      <div class="modal-footer py-2">
                        <button type="button" class="btn btn-sm btn-outline-danger me-auto fm-limpiar"><i class="bi bi-eraser me-1"></i>Limpiar filtros</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-sm btn-primary fm-aplicar"><i class="bi bi-check-lg me-1"></i>Aplicar</button>
                      </div>
                    </div>
                  </div>
                </div>`;
            this.elModal = wrap.firstElementChild;
            document.body.appendChild(this.elModal);
            this.elForm = this.elModal.querySelector('.fm-form');
            // Solo se cierra con la X, Cancelar, Aplicar o Limpiar: ni clic fuera ni Escape,
            // para no perder lo que se está llenando por un clic accidental.
            this.modal  = (window.bootstrap && bootstrap.Modal)
                ? new bootstrap.Modal(this.elModal, { backdrop: 'static', keyboard: false })
                : null;

            this.bindBusquedaDetalle();
            this.elModal.querySelector('.fm-aplicar').addEventListener('click', () => this.applyFromModal());
            this.elModal.querySelector('.fm-limpiar').addEventListener('click', () => {
                this.state.filters = this.state.filters.filter(f => f.neg || !this.field(f.key));
                this.fillModal();
                this.renderChips();
                this.apply();
                this.hide();
            });
            this.elModal.addEventListener('shown.bs.modal', () => {
                const first = this.elForm.querySelector('input, select');
                if (first) first.focus();
            });
            this.elForm.addEventListener('keydown', e => {
                if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    this.applyFromModal();
                }
            });
            // Atajos de fecha: llenan los dos inputs del rango
            this.elForm.querySelectorAll('[data-fm-atajo]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const key = btn.dataset.fmKey;
                    const atajo = atajosFecha.find(a => a.id === btn.dataset.fmAtajo);
                    if (!atajo) return;
                    const [ini, fin] = atajo.rango();
                    this.ctrl(key, 'from').value = ini;
                    this.ctrl(key, 'to').value   = fin;
                });
            });
        }

        /** Pestañas del modal en orden de aparición (campo `tab`; sin él, todos van a una sola). */
        tabs() {
            const out = [];
            this.opts.fields.forEach(f => {
                const t = f.tab || this.opts.tabDefault;
                if (!out.includes(t)) out.push(t);
            });
            // Una pestaña puede tener solo la búsqueda libre (sin campos).
            const bd = this.opts.busquedaDetalle;
            if (bd && bd.url) {
                const t = bd.tab || this.opts.tabDefault;
                if (!out.includes(t)) out.push(t);
            }
            return out;
        }

        renderFields() {
            const tabs = this.tabs();
            if (tabs.length <= 1) {
                return `<div class="row g-2">${this.renderTabFields(this.opts.fields)}</div>`;
            }
            let nav = '<ul class="nav nav-tabs fm-tabs mb-2" role="tablist">';
            let panes = '<div class="tab-content">';
            tabs.forEach((t, idx) => {
                const id = `${this.uid}-tab-${idx}`;
                nav += `<li class="nav-item" role="presentation">
                    <button class="nav-link ${idx === 0 ? 'active' : ''}" type="button" data-bs-toggle="tab" data-bs-target="#${id}" role="tab">
                        ${escapeHtml(t)} <span class="badge rounded-pill bg-primary fm-tab-count d-none" data-tab="${escapeHtml(t)}">0</span>
                    </button></li>`;
                const campos = this.opts.fields.filter(f => (f.tab || this.opts.tabDefault) === t);
                const bd = this.opts.busquedaDetalle;
                const buscador = (bd && bd.url && (bd.tab || this.opts.tabDefault) === t) ? this.renderBusquedaDetalle(bd, campos.length > 0) : '';
                panes += `<div class="tab-pane fade ${idx === 0 ? 'show active' : ''}" id="${id}" role="tabpanel">
                    ${buscador}
                    ${campos.length ? `<div class="row g-2">${this.renderTabFields(campos)}</div>` : ''}</div>`;
            });
            return nav + '</ul>' + panes + '</div>';
        }

        // ── Búsqueda libre dentro de los registros (pestaña de detalles) ─────────
        renderBusquedaDetalle(bd, conCampos = false) {
            const cols = bd.columns || [];
            return `<div class="fm-det">
                <label class="form-label fm-label d-block"><i class="bi bi-search me-1 text-muted"></i>${escapeHtml(bd.label || 'Buscar dentro de los registros')}</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control fm-det-input" placeholder="${escapeHtml(bd.placeholder || 'Escriba para buscar...')}" autocomplete="off">
                    <button type="button" class="btn btn-outline-secondary fm-det-clear" title="Limpiar"><i class="bi bi-x-lg"></i></button>
                </div>
                ${bd.hint ? `<div class="fm-det-hint text-muted small mt-1">${escapeHtml(bd.hint)}</div>` : ''}
                <div class="fm-det-results mt-2 d-none">
                    <div class="fm-det-count text-muted small mb-1"></div>
                    <div class="fm-det-scroll border rounded">
                        <table class="table table-sm table-hover mb-0 fm-det-table">
                            <thead class="table-light"><tr>
                                ${cols.map(c => `<th class="${c.align === 'end' ? 'text-end' : ''}">${escapeHtml(c.label)}</th>`).join('')}
                                ${bd.onOpen ? '<th class="text-center" style="width:36px"></th>' : ''}
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            ${conCampos ? '<div class="fm-det-sep"><span>Filtros por campo</span></div>' : ''}`;
        }

        bindBusquedaDetalle() {
            const bd = this.opts.busquedaDetalle;
            const box = this.elForm.querySelector('.fm-det');
            if (!bd || !box) return;
            this.elDetInput   = box.querySelector('.fm-det-input');
            this.elDetResults = box.querySelector('.fm-det-results');
            this.elDetCount   = box.querySelector('.fm-det-count');
            this.elDetBody    = box.querySelector('.fm-det-table tbody');

            let timer = null, reqSeq = 0;
            const buscar = async () => {
                const q = this.elDetInput.value.trim();
                const min = bd.minChars || 2;
                if (q.length < min) { this.elDetResults.classList.add('d-none'); return; }
                const mySeq = ++reqSeq;
                const cols = bd.columns || [];
                this.elDetResults.classList.remove('d-none');
                this.elDetCount.textContent = 'Buscando...';
                this.elDetBody.innerHTML = `<tr><td colspan="${cols.length + 1}" class="text-center py-3"><span class="spinner-border spinner-border-sm text-primary"></span></td></tr>`;
                try {
                    const sep = bd.url.indexOf('?') === -1 ? '?' : '&';
                    const resp = await fetch(`${bd.url}${sep}q=${encodeURIComponent(q)}`);
                    const data = await resp.json();
                    if (mySeq !== reqSeq) return; // llegó una respuesta vieja
                    this.renderDetRows(data.rows || [], bd);
                } catch (e) {
                    console.error('busquedaDetalle:', e);
                    if (mySeq !== reqSeq) return;
                    this.elDetCount.textContent = '';
                    this.elDetBody.innerHTML = `<tr><td colspan="${cols.length + 1}" class="text-danger text-center py-3">Error al buscar.</td></tr>`;
                }
            };
            this.elDetInput.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(buscar, bd.debounceMs || 350); });
            this.elDetInput.addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); clearTimeout(timer); buscar(); }
            });
            box.querySelector('.fm-det-clear').addEventListener('click', () => {
                this.elDetInput.value = '';
                this.elDetResults.classList.add('d-none');
                this.elDetInput.focus();
            });
        }

        renderDetRows(rows, bd) {
            const cols = bd.columns || [];
            const n = rows.length;
            const max = bd.max || 50;
            this.elDetCount.textContent = n === 0 ? 'Sin coincidencias.'
                : (n >= max ? `Se muestran las primeras ${max} coincidencias; afine la búsqueda.` : `${n} coincidencia${n === 1 ? '' : 's'}.`);
            this.elDetBody.innerHTML = '';
            rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.setAttribute('role', 'button');
                tr.innerHTML = cols.map(c => {
                    const v = row[c.key] ?? '';
                    const cls = [c.align === 'end' ? 'text-end' : '', c.class || ''].join(' ').trim();
                    return `<td class="${cls}" title="${escapeHtml(v)}">${escapeHtml(v)}</td>`;
                }).join('') + (bd.onOpen ? `<td class="text-center"><button type="button" class="btn btn-link btn-sm p-0 fm-det-open" title="Abrir"><i class="bi bi-box-arrow-up-right"></i></button></td>` : '');
                tr.addEventListener('click', () => { if (bd.onSelect) bd.onSelect(row, this); });
                const open = tr.querySelector('.fm-det-open');
                if (open) open.addEventListener('click', e => { e.stopPropagation(); bd.onOpen(row, this); });
                this.elDetBody.appendChild(tr);
            });
        }

        /** Fija un filtro (reemplaza el de la misma clave), repinta chips y aplica. */
        /**
         * Fija un filtro (reemplaza el de la misma clave) y repinta los chips.
         * @param {boolean} cerrar  cierra el modal después (por defecto sí)
         * @param {boolean} aplicar lanza la búsqueda (por defecto sí); con false se pueden
         *                          encadenar varios cambios y aplicar solo el último
         */
        aplicarFiltro(f, cerrar = true, aplicar = true) {
            if (!f || !f.key) return;
            f.op = f.op || (Array.isArray(f.value) ? 'BETWEEN' : 'ILIKE');
            const idx = this.state.filters.findIndex(x => x.key === f.key && !x.neg);
            if (idx >= 0) this.state.filters[idx] = f; else this.state.filters.push(f);
            this.renderChips();
            if (aplicar) this.apply();
            if (cerrar) this.hide();
        }

        /** ¿Hay un filtro activo (no negado) con esta clave y, si se indica, este valor? */
        tieneFiltro(key, value) {
            return this.state.filters.some(x => x.key === key && !x.neg
                && (value === undefined || JSON.stringify(x.value) === JSON.stringify(value)));
        }

        /** Copia del filtro activo (no negado) con esta clave, o null. Solo lectura. */
        getFiltro(key) {
            const f = this.state.filters.find(x => x.key === key && !x.neg);
            return f ? JSON.parse(JSON.stringify(f)) : null;
        }

        /**
         * Quita los filtros (no negados) con esta clave y, si se indica, este valor;
         * repinta chips y (por defecto) aplica. Pensado para accesos directos de la vista
         * que encienden/apagan un filtro sin tocar el estado interno del componente.
         * No hace nada si no había ese filtro.
         */
        quitarFiltro(key, value, aplicar = true) {
            const antes = this.state.filters.length;
            this.state.filters = this.state.filters.filter(x => !(x.key === key && !x.neg
                && (value === undefined || JSON.stringify(x.value) === JSON.stringify(value))));
            if (this.state.filters.length === antes) return;
            this.renderChips();
            if (aplicar) this.apply();
        }

        /** Vacía texto libre y filtros (incluidos los negados). Con aplicar=false solo repinta. */
        limpiar(aplicar = true) {
            this.state.filters = [];
            this.state.inputText = '';
            if (this.elInput) this.elInput.value = '';
            if (this.elHidden) this.elHidden.value = '';
            this.renderChips();
            if (aplicar) this.apply();
        }

        /** Cuántos filtros activos hay en cada pestaña (badge del nav). */
        updateTabCounts() {
            if (!this.elForm) return;
            this.elForm.querySelectorAll('.fm-tab-count').forEach(b => {
                const n = this.state.filters.filter(f => {
                    const fld = this.field(f.key);
                    return fld && (fld.tab || this.opts.tabDefault) === b.dataset.tab;
                }).length;
                b.textContent = n;
                b.classList.toggle('d-none', n === 0);
            });
        }

        renderTabFields(fields) {
            let html = '';
            let grupoActual = null;
            fields.forEach(f => {
                if (f.grupo && f.grupo !== grupoActual) {
                    grupoActual = f.grupo;
                    html += `<div class="col-12 fm-grupo">${escapeHtml(f.grupo)}</div>`;
                }
                const col = parseInt(f.col, 10) || 4;
                const icon = f.icon ? `<i class="bi ${escapeHtml(f.icon)} me-1 text-muted"></i>` : '';
                html += `<div class="col-md-${col} col-12"><label class="form-label fm-label d-block">${icon}${escapeHtml(f.label)}</label>`;
                if (f.type === 'text') {
                    html += `<input type="text" class="form-control form-control-sm" data-fm-key="${escapeHtml(f.key)}" data-fm-part="v" placeholder="${escapeHtml(f.placeholder || '')}">`;
                } else if (f.type === 'select') {
                    html += `<select class="form-select form-select-sm" data-fm-key="${escapeHtml(f.key)}" data-fm-part="v">
                        <option value="">${escapeHtml(f.placeholderOption || 'Todos')}</option>
                        ${(f.options || []).map(o => `<option value="${escapeHtml(o.v)}">${escapeHtml(o.l)}</option>`).join('')}
                    </select>`;
                } else if (f.type === 'date_range') {
                    html += `<div class="input-group input-group-sm">
                        <input type="date" class="form-control" data-fm-key="${escapeHtml(f.key)}" data-fm-part="from" title="Desde">
                        <span class="input-group-text">a</span>
                        <input type="date" class="form-control" data-fm-key="${escapeHtml(f.key)}" data-fm-part="to" title="Hasta">
                    </div>`;
                    if (f.atajos) {
                        html += `<div class="fm-atajos">${atajosFecha.map(a =>
                            `<button type="button" class="btn btn-link btn-sm p-0" data-fm-atajo="${a.id}" data-fm-key="${escapeHtml(f.key)}">${a.label}</button>`
                        ).join('<span class="text-muted mx-1">·</span>')}</div>`;
                    }
                } else if (f.type === 'number_range') {
                    html += `<div class="input-group input-group-sm">
                        <input type="number" step="0.01" class="form-control" data-fm-key="${escapeHtml(f.key)}" data-fm-part="from" placeholder="Mín.">
                        <span class="input-group-text">a</span>
                        <input type="number" step="0.01" class="form-control" data-fm-key="${escapeHtml(f.key)}" data-fm-part="to" placeholder="Máx.">
                    </div>`;
                }
                html += '</div>';
            });
            return html;
        }

        field(key) { return this.opts.fields.find(f => f.key === key); }
        ctrl(key, part) { return this.elForm.querySelector(`[data-fm-key="${key}"][data-fm-part="${part}"]`); }

        /** Carga el estado actual en los controles del modal. */
        fillModal() {
            this.opts.fields.forEach(f => {
                const cur = this.state.filters.find(x => x.key === f.key && !x.neg);
                if (f.type === 'text' || f.type === 'select') {
                    const el = this.ctrl(f.key, 'v');
                    if (!el) return;
                    let v = '';
                    if (cur) v = Array.isArray(cur.value) ? cur.value.join(',') : cur.value;
                    el.value = v;
                } else {
                    const from = this.ctrl(f.key, 'from'), to = this.ctrl(f.key, 'to');
                    if (!from || !to) return;
                    from.value = ''; to.value = '';
                    if (!cur) return;
                    if (cur.op === 'BETWEEN' && Array.isArray(cur.value)) { from.value = cur.value[0]; to.value = cur.value[1]; }
                    else if (cur.op === '>=' || cur.op === '>') from.value = cur.value;
                    else if (cur.op === '<=' || cur.op === '<') to.value = cur.value;
                    else if (cur.op === '=' || cur.op === 'ILIKE') { from.value = cur.value; to.value = cur.value; }
                }
            });
        }

        /** Lee los controles del modal y reconstruye los filtros. */
        applyFromModal() {
            // Se conservan los filtros que el modal no puede representar (negados o de
            // claves sin control), por ejemplo los que llegan escritos en la URL.
            const conservar = this.state.filters.filter(f => f.neg || !this.field(f.key));
            const nuevos = [];
            this.opts.fields.forEach(f => {
                let flt = null;
                if (f.type === 'text') {
                    const v = (this.ctrl(f.key, 'v')?.value || '').trim();
                    if (v) flt = { key: f.key, op: 'ILIKE', value: v };
                } else if (f.type === 'select') {
                    const v = this.ctrl(f.key, 'v')?.value || '';
                    if (v) flt = { key: f.key, op: '=', value: v };
                } else if (f.type === 'date_range' || f.type === 'number_range') {
                    const a = (this.ctrl(f.key, 'from')?.value || '').trim();
                    const b = (this.ctrl(f.key, 'to')?.value || '').trim();
                    if (a && b) flt = { key: f.key, op: 'BETWEEN', value: [a, b] };
                    else if (a) flt = { key: f.key, op: '>=', value: a };
                    else if (b) flt = { key: f.key, op: '<=', value: b };
                }
                if (flt) nuevos.push(flt);
            });
            this.state.filters = conservar.concat(nuevos);
            this.renderChips();
            this.apply();
            this.hide();
        }

        show() { this.fillModal(); this.updateTabCounts(); if (this.modal) this.modal.show(); }
        hide() { if (this.modal) this.modal.hide(); }

        // ── Input de texto libre y chips ─────────────────────────────────────────
        bindEvents() {
            this.elBtn.addEventListener('click', () => this.show());
            // Los chips viven dentro de la caja: clic en cualquier hueco enfoca el input.
            this.elBox.addEventListener('click', e => {
                if (e.target === this.elBox || e.target === this.elChips) this.elInput.focus();
            });

            let debounce;
            this.elInput.addEventListener('input', () => {
                this.state.inputText = this.elInput.value;
                clearTimeout(debounce);
                // El spinner se enciende al teclear (búsqueda en espera), no recién
                // cuando sale la petición: así no hay un hueco en que parezca que no
                // pasa nada.
                this.setLoading(true);
                debounce = setTimeout(() => this.apply(), this.opts.debounceMs);
            });
            this.elInput.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(debounce);
                    this.state.inputText = this.elInput.value;
                    this.apply();
                } else if (e.key === 'Escape') {
                    clearTimeout(debounce);
                    this.elInput.value = '';
                    this.state.inputText = '';
                    this.apply();
                } else if (e.key === 'Backspace' && this.elInput.value === '' && this.state.filters.length > 0) {
                    // Con el input vacío, Backspace quita el último chip.
                    e.preventDefault();
                    this.state.filters.pop();
                    this.renderChips();
                    this.apply();
                }
            });
        }

        formatDisplay(f) {
            const fld = this.field(f.key);
            const esFecha = fld && fld.type === 'date_range';
            const fmt = v => esFecha ? fechaBonita(v) : v;
            if (f.op === 'BETWEEN' && Array.isArray(f.value)) return `${fmt(f.value[0])} → ${fmt(f.value[1])}`;
            if (f.op === 'IN' && Array.isArray(f.value)) {
                if (fld && fld.type === 'select') {
                    return f.value.map(v => ((fld.options || []).find(o => o.v === v) || { l: v }).l).join(', ');
                }
                return f.value.join(', ');
            }
            if (f.op === '>=') return `desde ${fmt(f.value)}`;
            if (f.op === '<=') return `hasta ${fmt(f.value)}`;
            if (f.op === '>' || f.op === '<') return `${f.op} ${fmt(f.value)}`;
            if (fld && fld.type === 'select') {
                const opt = (fld.options || []).find(o => o.v === f.value);
                return opt ? opt.l : f.value;
            }
            return fmt(f.value);
        }

        renderChips() {
            this.elChips.innerHTML = '';
            const n = this.state.filters.length;
            this.elBadge.textContent = n;
            this.elBadge.classList.toggle('d-none', n === 0);
            this.elBtn.classList.toggle('btn-outline-primary', n > 0);
            this.elBtn.classList.toggle('btn-outline-secondary', n === 0);
            this.updateTabCounts();

            this.state.filters.forEach((f, idx) => {
                const fld = this.field(f.key);
                const label = fld ? fld.label : f.key;
                const chip = document.createElement('span');
                chip.className = 'fm-chip' + (f.neg ? ' fm-chip-neg' : '');
                chip.title = 'Clic para editar en el modal';
                chip.innerHTML = `<span class="fm-chip-key">${f.neg ? '≠ ' : ''}${escapeHtml(label)}:</span> <span>${escapeHtml(this.formatDisplay(f))}</span> <span class="fm-chip-x" title="Quitar">×</span>`;
                chip.addEventListener('click', () => this.show());
                chip.querySelector('.fm-chip-x').addEventListener('click', e => {
                    e.stopPropagation();
                    this.state.filters.splice(idx, 1);
                    this.renderChips();
                    this.apply();
                });
                this.elChips.appendChild(chip);
            });
            this.elChips.classList.toggle('d-none', n === 0);
        }

        // ── Serialización (mismo formato que FiltrosBusqueda::parsear en PHP) ────
        serialize() {
            const parts = [];
            for (const f of this.state.filters) {
                const neg = f.neg ? '-' : '';
                let v = '';
                if (f.op === 'BETWEEN' && Array.isArray(f.value)) v = `${f.value[0]}..${f.value[1]}`;
                else if (f.op === 'IN' && Array.isArray(f.value))  v = f.value.join(',');
                else if (f.op === 'ILIKE' || f.op === '=')          v = f.value;
                else                                                 v = f.op + f.value;
                if (typeof v === 'string' && v.indexOf(' ') !== -1) v = `"${v}"`;
                parts.push(`${neg}${f.key}:${v}`);
            }
            if (this.state.inputText.trim()) parts.push(this.state.inputText.trim());
            return parts.join(' ');
        }

        apply() {
            if (this.elHidden) this.elHidden.value = this.serialize();

            // Cada búsqueda lleva un número: si el usuario lanza otra antes de que
            // termine la anterior, solo la ÚLTIMA apaga el indicador (una respuesta
            // vieja que llega tarde no lo apaga mientras la nueva sigue en curso).
            const mySeq  = ++this.applySeq;
            const inicio = Date.now();
            this.setLoading(true);

            let resultado;
            try { resultado = this.opts.onApply(); } catch (e) { console.error('onApply error:', e); }

            Promise.resolve(resultado)
                .catch(e => console.error('onApply error:', e))
                .finally(() => {
                    if (mySeq !== this.applySeq) return;
                    // Mínimo visible de 250 ms: una respuesta instantánea no debe
                    // parpadear, pero sí dejar claro que la búsqueda se hizo.
                    const resto = Math.max(0, 250 - (Date.now() - inicio));
                    setTimeout(() => { if (mySeq === this.applySeq) this.setLoading(false); }, resto);
                });
        }

        /** Enciende/apaga el spinner de la caja y la atenuación de `loadingTarget`. */
        setLoading(on) {
            if (this.elSpin) this.elSpin.classList.toggle('d-none', !on);
            if (this.elBox) {
                this.elBox.classList.toggle('fm-cargando', !!on);
                this.elBox.setAttribute('aria-busy', on ? 'true' : 'false');
            }
            if (this.opts.loadingTarget) {
                document.querySelectorAll(this.opts.loadingTarget)
                    .forEach(el => el.classList.toggle('fm-cargando-target', !!on));
            }
        }

        /** Valor serializado actual (por si un módulo lo necesita sin leer el hidden). */
        getValue() { return this.serialize(); }
    }

    FiltrosModal.atajosFecha = atajosFecha;
    window.FiltrosModal = FiltrosModal;
})();
