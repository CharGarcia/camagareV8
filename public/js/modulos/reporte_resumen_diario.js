/* Resumen Diario: ventas, compras, ingresos y egresos de un día.
   El servidor arma el HTML (ReporteResumenDiarioController::generarAjax); aquí solo se
   piden los datos, se pintan los indicadores y se enlazan PDF/Excel. */
(function () {
    const $ = id => document.getElementById(id);
    const RUTA = window.RRD_RUTA;
    const BASE = window.RRD_BASE;
    const money = n => {
        const v = parseFloat(n) || 0;
        return (v < 0 ? '-$' : '$') + Math.abs(v).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    const fechaTexto = iso => iso.split('-').reverse().join('-');

    let consulta = 0; // descarta respuestas viejas si el usuario cambia de día rápido

    async function mostrar() {
        const fecha = $('rrd-fecha').value;
        const cont = $('rrd-contenido');
        if (!fecha) {
            cont.innerHTML = '<div class="alert alert-warning m-3 mb-0">Elija el día del resumen.</div>';
            return;
        }
        const id = ++consulta;
        cont.innerHTML = '<div class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Armando el resumen…</div>';
        $('rrdBtnPdf').disabled = true;
        $('rrdBtnExcel').disabled = true;

        try {
            const params = new URLSearchParams({ fecha, borradores: $('rrd-borradores').value });
            const res = await fetch(`${BASE}/${RUTA}/generarAjax?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (id !== consulta) return;
            if (!json.ok) {
                cont.innerHTML = `<div class="alert alert-warning m-3 mb-0">${json.mensaje || 'No se pudo generar el resumen.'}</div>`;
                return;
            }
            cont.innerHTML = json.html;
            $('rrd-titulo-dia').textContent = 'Día ' + fechaTexto(fecha);
            $('rrd-kpi-ventas').textContent   = money(json.kpis.ventas_netas);
            $('rrd-kpi-compras').textContent  = money(json.kpis.compras_netas);
            $('rrd-kpi-ingresos').textContent = money(json.kpis.ingresos);
            $('rrd-kpi-egresos').textContent  = money(json.kpis.egresos);
            $('rrd-kpi-neto').textContent     = money(json.kpis.neto_caja);
            $('rrd-kpi-neto').classList.toggle('text-primary', json.kpis.neto_caja >= 0);
            $('rrd-kpi-neto').classList.toggle('text-danger', json.kpis.neto_caja < 0);

            $('rrdBtnPdf').disabled = false;
            $('rrdBtnExcel').disabled = false;
            $('rrdBtnPdf').onclick   = () => CMG_descargar(json.pdf_url);
            $('rrdBtnExcel').onclick = () => CMG_descargar(json.excel_url);
        } catch (e) {
            if (id === consulta) {
                cont.innerHTML = '<div class="alert alert-danger m-3 mb-0">Error de comunicación con el servidor.</div>';
            }
        }
    }

    /** Mueve el día ±1 y vuelve a consultar. */
    function moverDia(delta) {
        const v = $('rrd-fecha').value || window.RRD_HOY;
        const d = new Date(v + 'T12:00:00');
        d.setDate(d.getDate() + delta);
        const p = n => String(n).padStart(2, '0');
        $('rrd-fecha').value = `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
        mostrar();
    }

    $('rrdBtnMostrar').addEventListener('click', mostrar);
    $('rrdBtnAnterior').addEventListener('click', () => moverDia(-1));
    $('rrdBtnSiguiente').addEventListener('click', () => moverDia(1));
    $('rrdBtnLimpiar').addEventListener('click', () => {
        $('rrd-fecha').value = window.RRD_HOY;
        $('rrd-borradores').value = '';
        mostrar();
    });

    mostrar(); // primera carga: hoy
})();
