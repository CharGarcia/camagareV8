/**
 * Reportes de producción — producción por producto, consumo de insumos, órdenes cerradas y costo por mes.
 * Solo órdenes cerradas y solo documentos de producción.
 *
 * Cada pestaña es una vista del botón «Personalizar» (ColumnasReporte): el servidor pinta la
 * tabla con las columnas que la empresa eligió; al guardar la personalización se vuelve a cargar.
 */
(function () {
    'use strict';

    const URL = window.PREP_URL_BASE;
    let pestana = 'producido';

    function el(id) { return document.getElementById(id); }
    function money(v) { return '$' + (parseFloat(v) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function val(id) { return el(id) ? el(id).value : ''; }

    function params() {
        return new URLSearchParams({
            pestana,
            desde: val('prep-desde'), hasta: val('prep-hasta'),
            id_formula: val('prep-formula'), id_bodega: val('prep-bodega'),
            id_usuario: val('prep-usuario'), responsable: val('prep-responsable'),
            id_producto: val('prep-producto'), id_insumo: val('prep-insumo'),
        });
    }

    window.PREP_cargar = function () {
        const tabla = el('prep-tabla');
        tabla.classList.add('opacity-50');
        fetch(`${URL}/datos-ajax?${params()}`).then(r => r.json()).then(res => {
            if (!res.ok) return;
            tabla.innerHTML = res.html;
            const s = res.resumen || {};
            el('prep-stat-ordenes').textContent = s.ordenes ?? 0;
            el('prep-stat-materiales').textContent = money(s.materiales);
            el('prep-stat-conversion').textContent = money(s.conversion);
            el('prep-stat-costo').textContent = money(s.costo);
            el('prep-stat-merma').textContent = money(s.merma);
        }).catch(e => console.error('PREP', e)).finally(() => tabla.classList.remove('opacity-50'));
    };

    // Limpiar: deja el período y quita los demás filtros.
    window.PREP_limpiar = function () {
        ['prep-formula', 'prep-formula-txt', 'prep-bodega', 'prep-usuario', 'prep-responsable', 'prep-producto', 'prep-producto-txt', 'prep-insumo', 'prep-insumo-txt']
            .forEach(id => { if (el(id)) el(id).value = ''; });
        window.PREP_cargar();
    };

    window.PREP_exportar = function (tipo) {
        CMG_descargar(`${URL}/${tipo === 'pdf' ? 'export-pdf' : 'export-excel'}?${params()}`);
    };

    // ── Buscadores con lista (fórmula, producto e insumo): filtran la lista ya cargada, sin acentos. ──
    function buscadorLista(base, todas) {
        const txt = el(base + '-txt'), hid = el(base), lista = el(base + '-lista');
        if (!txt) return;
        const norm = s => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
        const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        let activo = -1;

        const inicial = todas.find(x => String(x.id) === hid.value);
        if (inicial) txt.value = inicial.texto;

        function cerrar() { lista.classList.add('d-none'); lista.innerHTML = ''; activo = -1; }
        function elegir(x) { hid.value = x.id; txt.value = x.texto; cerrar(); }
        function limpiar() { hid.value = ''; txt.value = ''; cerrar(); }
        function marcar() {
            lista.querySelectorAll('.list-group-item').forEach((b, i) => b.classList.toggle('active', i === activo));
        }
        function pintar() {
            const palabras = norm(txt.value).split(/\s+/).filter(Boolean);
            const res = todas.filter(x => palabras.every(p => norm(x.texto).includes(p))).slice(0, 50);
            lista.innerHTML = res.length
                ? res.map((x, i) => `<button type="button" class="list-group-item list-group-item-action text-truncate" data-i="${i}">${esc(x.texto)}</button>`).join('')
                : '<div class="list-group-item text-muted small">Sin coincidencias</div>';
            lista.classList.remove('d-none');
            activo = -1;
            lista.querySelectorAll('[data-i]').forEach(b => b.addEventListener('mousedown', e => { e.preventDefault(); elegir(res[+b.dataset.i]); }));
            lista._res = res;
        }

        txt.addEventListener('focus', pintar);
        txt.addEventListener('input', () => { hid.value = ''; pintar(); });
        txt.addEventListener('blur', () => setTimeout(() => { if (!hid.value) txt.value = ''; cerrar(); }, 150));
        txt.addEventListener('keydown', e => {
            // Con una fórmula elegida, Backspace/Supr quita toda la selección de una vez (§9 Controles).
            if ((e.key === 'Backspace' || e.key === 'Delete') && hid.value) { e.preventDefault(); limpiar(); pintar(); return; }
            const res = lista._res || [];
            if (e.key === 'ArrowDown' && res.length) { e.preventDefault(); activo = Math.min(activo + 1, res.length - 1); marcar(); }
            else if (e.key === 'ArrowUp' && res.length) { e.preventDefault(); activo = Math.max(activo - 1, 0); marcar(); }
            else if (e.key === 'Enter' && !lista.classList.contains('d-none') && activo >= 0) { e.preventDefault(); elegir(res[activo]); }
            else if (e.key === 'Escape') cerrar();
        });
    }
    buscadorLista('prep-formula', window.PREP_FORMULAS || []);
    buscadorLista('prep-producto', window.PREP_PRODUCTOS || []);
    buscadorLista('prep-insumo', window.PREP_INSUMOS || []);

    // «Personalizar»: la vista abierta es la pestaña; al guardar, se repinta con las nuevas columnas.
    window.CMG_vistaReporteActual = () => pestana;
    document.addEventListener('reportePersonalizado', () => window.PREP_cargar());

    document.querySelectorAll('#prep-tabs [data-pestana]').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('#prep-tabs .nav-link').forEach(x => x.classList.remove('active'));
        b.classList.add('active');
        pestana = b.dataset.pestana;
        window.PREP_cargar();
    }));

    window.PREP_cargar();
})();
