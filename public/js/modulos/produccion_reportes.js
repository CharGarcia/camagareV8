/**
 * Reportes de producción — producción por producto, consumo de insumos, órdenes cerradas y costo por mes.
 * Solo órdenes cerradas y solo documentos de producción.
 */
(function () {
    'use strict';

    const URL = window.PREP_URL_BASE;
    let pestana = 'producido';

    function el(id) { return document.getElementById(id); }
    function money(v) { return '$' + (parseFloat(v) || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function params() {
        return new URLSearchParams({
            pestana, desde: el('prep-desde').value, hasta: el('prep-hasta').value, producto: el('prep-producto').value,
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

    window.PREP_limpiar = function () {
        el('prep-producto').value = '';
        window.PREP_cargar();
    };

    window.PREP_exportar = function (tipo) {
        CMG_descargar(`${URL}/${tipo === 'pdf' ? 'export-pdf' : 'export-excel'}?${params()}`);
    };

    document.querySelectorAll('#prep-tabs [data-pestana]').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('#prep-tabs .nav-link').forEach(x => x.classList.remove('active'));
        b.classList.add('active');
        pestana = b.dataset.pestana;
        window.PREP_cargar();
    }));

    window.PREP_cargar();
})();
