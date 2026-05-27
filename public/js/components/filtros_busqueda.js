/**
 * Componente reusable: Buscador con filtros estilo Odoo.
 *
 * Uso desde un módulo:
 *
 *   const fb = new FiltrosBusqueda({
 *     containerId: 'fbBuscador',          // div vacío donde se renderiza
 *     hiddenInputId: 'buscar',            // input hidden que recibe el string serializado
 *     initialValue: '',                   // valor inicial (texto + tokens)
 *     placeholder: 'Buscar...',
 *     fields: [
 *       { key: 'cliente', label: 'Cliente', icon: 'bi-person', type: 'text' },
 *       { key: 'fecha',   label: 'Fecha',   icon: 'bi-calendar', type: 'date_range' },
 *       { key: 'estado',  label: 'Estado',  icon: 'bi-flag',     type: 'select',
 *         options: [{ v: 'borrador', l: 'Borrador' }, ...] },
 *       { key: 'monto',   label: 'Monto',   icon: 'bi-currency-dollar', type: 'number_range' },
 *     ],
 *     quickFilters: [
 *       { id: 'borrador', label: 'Borrador', mk: () => ({ key:'estado', op:'=', value:'borrador', display:'Borrador' }) },
 *       { id: 'mes', label: 'Este mes',     mk: () => FiltrosBusqueda.helpers.esteMes('fecha') },
 *     ],
 *     onApply: () => window.MIMODULO_fetchSearch(1),
 *   });
 *   fb.init();
 */
(function () {
    'use strict';

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    // Helpers de fechas exportados para que los módulos los usen en quickFilters
    const helpers = {
        ymd: d => d.toISOString().slice(0, 10),
        hoy: () => new Date(),
        primerDiaMes: d => new Date(d.getFullYear(), d.getMonth(), 1),
        ultimoDiaMes: d => new Date(d.getFullYear(), d.getMonth() + 1, 0),
        esteMes: (campoFecha = 'fecha', display = 'Este mes') => {
            const d = new Date();
            const ini = new Date(d.getFullYear(), d.getMonth(), 1);
            const fin = new Date(d.getFullYear(), d.getMonth() + 1, 0);
            return { key: campoFecha, op: 'BETWEEN',
                value: [helpers.ymd(ini), helpers.ymd(fin)], display };
        },
        mesPasado: (campoFecha = 'fecha', display = 'Mes pasado') => {
            const d = new Date(); d.setMonth(d.getMonth() - 1);
            const ini = new Date(d.getFullYear(), d.getMonth(), 1);
            const fin = new Date(d.getFullYear(), d.getMonth() + 1, 0);
            return { key: campoFecha, op: 'BETWEEN',
                value: [helpers.ymd(ini), helpers.ymd(fin)], display };
        },
        esteAnio: (campoFecha = 'fecha') => {
            const y = new Date().getFullYear();
            return { key: campoFecha, op: 'BETWEEN',
                value: [`${y}-01-01`, `${y}-12-31`], display: 'Año ' + y };
        },
        hoyMismo: (campoFecha = 'fecha', display = 'Hoy') => {
            const d = helpers.ymd(new Date());
            return { key: campoFecha, op: 'BETWEEN', value: [d, d], display };
        },
    };

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
            const f = parseValor(key, valor, neg);
            if (f) filters.push(f);
            texto = texto.replace(m[0], '');
        }
        return { filters, texto: texto.trim().replace(/\s+/g, ' ') };
    }

    function sameFilter(a, b) {
        return a && b && a.key === b.key && a.op === b.op
            && JSON.stringify(a.value) === JSON.stringify(b.value)
            && !!a.neg === !!b.neg;
    }

    class FiltrosBusqueda {
        constructor(opts) {
            this.opts = Object.assign({
                containerId: null,
                hiddenInputId: null,
                initialValue: '',
                placeholder: 'Buscar...',
                fields: [],
                quickFilters: [],
                onApply: () => {},
                debounceMs: 400,
            }, opts || {});
            this.state = {
                filters: [],
                activeQuick: new Set(),
                inputText: '',
                submenuOpen: null,
            };
        }

        init() {
            const container = document.getElementById(this.opts.containerId);
            if (!container) return;

            // HTML base
            container.classList.add('fb-search');
            container.innerHTML = `
                <div class="fb-search-box form-control form-control-sm d-flex flex-wrap align-items-center gap-1" tabindex="0">
                    <i class="bi bi-search text-muted small me-1"></i>
                    <div class="fb-chips d-flex flex-wrap gap-1"></div>
                    <input type="text" class="border-0 flex-grow-1 p-0 fb-search-typer" placeholder="${escapeHtml(this.opts.placeholder)}" autocomplete="off">
                </div>
                <div class="fb-search-dropdown shadow-lg border rounded" style="display:none;"></div>
            `;

            this.elBox      = container.querySelector('.fb-search-box');
            this.elChips    = container.querySelector('.fb-chips');
            this.elInput    = container.querySelector('.fb-search-typer');
            this.elDropdown = container.querySelector('.fb-search-dropdown');
            this.elHidden   = document.getElementById(this.opts.hiddenInputId);

            // Cargar estado inicial desde el valor del input hidden (o initialValue)
            const base = (this.elHidden ? this.elHidden.value : '') || this.opts.initialValue || '';
            const ini = parseInitial(base);
            ini.filters.forEach(f => f.display = this.formatDisplay(f));
            this.state.filters   = ini.filters;
            this.state.inputText = ini.texto;
            this.elInput.value   = ini.texto;
            this.renderChips();

            // Detectar quickFilters ya activos
            this.opts.quickFilters.forEach(q => {
                if (this.state.filters.find(f => sameFilter(f, q.mk()))) {
                    this.state.activeQuick.add(q.id);
                }
            });

            this.bindEvents();
        }

        bindEvents() {
            this.elBox.addEventListener('click', e => {
                if (e.target === this.elBox || e.target === this.elChips || e.target.tagName === 'I') {
                    this.elInput.focus();
                }
                this.openDropdown();
            });
            this.elInput.addEventListener('focus', () => this.openDropdown());

            let debounce;
            this.elInput.addEventListener('input', () => {
                this.state.inputText = this.elInput.value;
                this.renderDropdown();
                clearTimeout(debounce);
                debounce = setTimeout(() => this.apply(), this.opts.debounceMs);
            });

            this.elInput.addEventListener('keydown', e => {
                if (e.key === 'Backspace' && this.elInput.value === '' && this.state.filters.length > 0) {
                    this.state.filters.pop();
                    this.recomputeActiveQuick();
                    this.renderChips();
                    this.apply();
                    this.renderDropdown();
                } else if (e.key === 'Escape') {
                    this.closeDropdown();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    this.apply();
                }
            });

            document.addEventListener('click', e => {
                if (!this.elBox.contains(e.target) && !this.elDropdown.contains(e.target)) {
                    this.closeDropdown();
                }
            });
        }

        formatDisplay(f) {
            const fld = this.opts.fields.find(x => x.key === f.key);
            if (f.op === 'BETWEEN' && Array.isArray(f.value)) return `${f.value[0]} → ${f.value[1]}`;
            if (f.op === 'IN' && Array.isArray(f.value)) return f.value.join(', ');
            if (f.op !== 'ILIKE' && f.op !== '=') return `${f.op} ${f.value}`;
            if (fld && fld.type === 'select') {
                const opt = (fld.options || []).find(o => o.v === f.value);
                return opt ? opt.l : f.value;
            }
            return f.value;
        }

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
            if (this.state.inputText) parts.push(this.state.inputText);
            return parts.join(' ');
        }

        apply() {
            if (this.elHidden) this.elHidden.value = this.serialize();
            try { this.opts.onApply(); } catch (e) { console.error('onApply error:', e); }
        }

        recomputeActiveQuick() {
            this.state.activeQuick = new Set();
            this.opts.quickFilters.forEach(q => {
                if (this.state.filters.find(f => sameFilter(f, q.mk()))) {
                    this.state.activeQuick.add(q.id);
                }
            });
        }

        renderChips() {
            this.elChips.innerHTML = '';
            this.state.filters.forEach((f, idx) => {
                const fld = this.opts.fields.find(x => x.key === f.key);
                const label = fld ? fld.label : f.key;
                const chip = document.createElement('span');
                chip.className = 'fb-chip' + (f.neg ? ' fb-chip-neg' : '');
                chip.innerHTML = `<span class="fb-chip-key">${f.neg ? '≠ ' : ''}${escapeHtml(label)}:</span> <span>${escapeHtml(f.display || f.value)}</span> <span class="fb-chip-x" title="Quitar">×</span>`;
                chip.querySelector('.fb-chip-x').addEventListener('click', e => {
                    e.stopPropagation();
                    this.state.filters.splice(idx, 1);
                    this.recomputeActiveQuick();
                    this.renderChips();
                    this.apply();
                });
                this.elChips.appendChild(chip);
            });
        }

        openDropdown() {
            this.elDropdown.style.display = 'block';
            this.renderDropdown();
        }
        closeDropdown() {
            this.elDropdown.style.display = 'none';
            this.state.submenuOpen = null;
        }

        renderDropdown() {
            const text = this.state.inputText.trim();
            const textFields = this.opts.fields.filter(f => f.type === 'text');
            let html = '';

            if (text && textFields.length > 0) {
                html += '<div class="fb-dd-section">';
                html += `<div class="fb-dd-title">Buscar "${escapeHtml(text)}" en</div>`;
                textFields.forEach(f => {
                    html += `<div class="fb-dd-item" data-action="add-text" data-key="${f.key}">
                        <i class="bi ${f.icon || 'bi-funnel'}"></i> ${escapeHtml(f.label)}: <span class="fb-match">${escapeHtml(text)}</span>
                    </div>`;
                });
                html += `<div class="fb-dd-item" data-action="add-libre">
                    <i class="bi bi-search"></i> Buscar texto libre: <span class="fb-match">${escapeHtml(text)}</span>
                </div>`;
                html += '</div>';
            }

            if (this.opts.quickFilters.length > 0) {
                html += '<div class="fb-dd-section">';
                html += '<div class="fb-dd-title">⚡ Filtros rápidos</div>';
                this.opts.quickFilters.forEach(q => {
                    const activo = this.state.activeQuick.has(q.id);
                    html += `<div class="fb-dd-item ${activo ? 'active' : ''}" data-action="toggle-quick" data-id="${q.id}">
                        <i class="bi ${activo ? 'bi-check-square-fill text-primary' : 'bi-square'}"></i> ${escapeHtml(q.label)}
                    </div>`;
                });
                html += '</div>';
            }

            html += '<div class="fb-dd-section">';
            html += '<div class="fb-dd-title">+ Agregar filtro</div>';
            this.opts.fields.forEach(f => {
                const isOpen = this.state.submenuOpen === f.key;
                html += `<div class="fb-dd-item" data-action="open-submenu" data-key="${f.key}">
                    <i class="bi ${f.icon || 'bi-funnel'}"></i> ${escapeHtml(f.label)}
                    <i class="bi ${isOpen ? 'bi-chevron-up' : 'bi-chevron-right'} fb-hint"></i>
                </div>`;
                if (isOpen) html += this.renderSubmenu(f);
            });
            html += '</div>';

            this.elDropdown.innerHTML = html;
            this.wireDropdown();
        }

        renderSubmenu(f) {
            let html = '<div class="fb-dd-submenu">';
            if (f.type === 'text') {
                html += `<label>Contiene</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" data-sub-key="${f.key}" data-sub-type="text" placeholder="${escapeHtml(f.placeholder || '')}">
                        <button class="btn btn-primary" data-action="submit-sub" data-key="${f.key}">Aplicar</button>
                    </div>`;
            } else if (f.type === 'select') {
                html += `<label>${escapeHtml(f.label)}</label>
                    <div class="input-group input-group-sm">
                        <select class="form-select" data-sub-key="${f.key}" data-sub-type="select">
                            ${(f.options || []).map(o => `<option value="${escapeHtml(o.v)}">${escapeHtml(o.l)}</option>`).join('')}
                        </select>
                        <button class="btn btn-primary" data-action="submit-sub" data-key="${f.key}">Aplicar</button>
                    </div>`;
            } else if (f.type === 'date_range') {
                html += `<label>Desde</label>
                    <input type="date" class="form-control form-control-sm mb-2" data-sub-key="${f.key}" data-sub-type="date_from">
                    <label>Hasta</label>
                    <div class="input-group input-group-sm">
                        <input type="date" class="form-control" data-sub-key="${f.key}" data-sub-type="date_to">
                        <button class="btn btn-primary" data-action="submit-sub" data-key="${f.key}">Aplicar</button>
                    </div>`;
            } else if (f.type === 'number_range') {
                html += `<label>Mínimo</label>
                    <input type="number" step="0.01" class="form-control form-control-sm mb-2" data-sub-key="${f.key}" data-sub-type="num_from" placeholder="0">
                    <label>Máximo</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.01" class="form-control" data-sub-key="${f.key}" data-sub-type="num_to" placeholder="∞">
                        <button class="btn btn-primary" data-action="submit-sub" data-key="${f.key}">Aplicar</button>
                    </div>`;
            }
            html += '</div>';
            return html;
        }

        wireDropdown() {
            this.elDropdown.querySelectorAll('[data-action]').forEach(el => {
                el.addEventListener('click', e => {
                    e.stopPropagation();
                    const a = el.dataset.action;
                    if (a === 'add-text') {
                        this.addFilter({ key: el.dataset.key, op: 'ILIKE', value: this.state.inputText });
                        this.clearInput();
                    } else if (a === 'add-libre') {
                        // El texto libre ya está en inputText; solo aplicar.
                        this.apply();
                    } else if (a === 'toggle-quick') {
                        this.toggleQuick(el.dataset.id);
                    } else if (a === 'open-submenu') {
                        this.state.submenuOpen = this.state.submenuOpen === el.dataset.key ? null : el.dataset.key;
                        this.renderDropdown();
                    } else if (a === 'submit-sub') {
                        this.submitSubmenu(el.dataset.key);
                    }
                });
            });
            this.elDropdown.querySelectorAll('[data-sub-key]').forEach(inp => {
                inp.addEventListener('keydown', e => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.submitSubmenu(inp.dataset.subKey);
                    }
                });
            });
        }

        submitSubmenu(key) {
            const fld = this.opts.fields.find(f => f.key === key);
            if (!fld) return;
            const inputs = this.elDropdown.querySelectorAll(`[data-sub-key="${key}"]`);
            let f = null;
            if (fld.type === 'text') {
                const v = inputs[0].value.trim();
                if (v) f = { key, op: 'ILIKE', value: v };
            } else if (fld.type === 'select') {
                const v = inputs[0].value;
                const opt = (fld.options || []).find(o => o.v === v);
                if (v) f = { key, op: '=', value: v, display: opt ? opt.l : v };
            } else if (fld.type === 'date_range') {
                const desde = inputs[0].value, hasta = inputs[1].value;
                if (desde && hasta) f = { key, op: 'BETWEEN', value: [desde, hasta] };
                else if (desde)     f = { key, op: '>=', value: desde };
                else if (hasta)     f = { key, op: '<=', value: hasta };
            } else if (fld.type === 'number_range') {
                const min = inputs[0].value, max = inputs[1].value;
                if (min && max) f = { key, op: 'BETWEEN', value: [min, max] };
                else if (min)   f = { key, op: '>=', value: min };
                else if (max)   f = { key, op: '<=', value: max };
            }
            if (f) {
                this.addFilter(f);
                this.state.submenuOpen = null;
                this.renderDropdown();
            }
        }

        addFilter(f) {
            f.display = f.display || this.formatDisplay(f);
            const idx = this.state.filters.findIndex(x => x.key === f.key && !x.neg);
            if (idx >= 0) this.state.filters[idx] = f;
            else this.state.filters.push(f);
            this.recomputeActiveQuick();
            this.renderChips();
            this.apply();
        }

        toggleQuick(qid) {
            const q = this.opts.quickFilters.find(qq => qq.id === qid);
            if (!q) return;
            if (this.state.activeQuick.has(qid)) {
                this.state.activeQuick.delete(qid);
                const ref = q.mk();
                const idx = this.state.filters.findIndex(f => sameFilter(f, ref));
                if (idx >= 0) this.state.filters.splice(idx, 1);
                this.renderChips();
                this.apply();
                this.renderDropdown();
            } else {
                this.state.activeQuick.add(qid);
                this.addFilter(q.mk());
                this.renderDropdown();
            }
        }

        clearInput() {
            this.elInput.value = '';
            this.state.inputText = '';
        }
    }

    FiltrosBusqueda.helpers = helpers;
    window.FiltrosBusqueda = FiltrosBusqueda;
})();
