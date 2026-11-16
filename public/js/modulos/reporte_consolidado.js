// Reporte Consolidado de Transacciones — carga AJAX, filtros y exportación.
// Los documentos se consultan solo al pulsar «Mostrar» (no al abrir ni al cambiar un filtro).
window.rcon_reporte_generado = false;

// Columnas que tiene hoy la tabla (cambian con «Personalizar»), para el colspan de los avisos.
function RCON_nColumnas() {
    return document.querySelectorAll('#rcon-thead th').length || 1;
}

window.RCON_generarReporte = function () {
    const form     = document.getElementById('form-filtros-consolidado');
    const formData = new FormData(form);
    const params   = new URLSearchParams(formData).toString();

    const tbody = document.getElementById('rcon-tbody');
    tbody.innerHTML = `<tr><td colspan="${RCON_nColumnas()}" class="text-center py-4">
        <div class="spinner-border text-primary" role="status"></div>
        <br><span class="text-muted small mt-2 d-inline-block">Generando reporte...</span>
    </td></tr>`;

    fetch(BASE_URL + '/' + RUTA_MODULO + '/generarAjax', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: params,
    })
    .then(async response => {
        const text = await response.text();
        try { return JSON.parse(text); }
        catch (e) { throw new Error(text.substring(0, 200)); }
    })
    .then(res => {
        if (res.ok) {
            if (res.thead) document.getElementById('rcon-thead').innerHTML = res.thead;
            tbody.innerHTML = res.rows;
            window.rcon_reporte_generado = true;
            if (res.stats) {
                document.getElementById('rcon-stat-documentos').textContent = res.stats.n_documentos ?? 0;
                document.getElementById('rcon-stat-ventas').textContent     = parseFloat(res.stats.total_ventas ?? 0).toFixed(2);
                document.getElementById('rcon-stat-compras').textContent    = parseFloat(res.stats.total_compras ?? 0).toFixed(2);
                document.getElementById('rcon-stat-neto').textContent       = parseFloat(res.stats.neto ?? 0).toFixed(2);
            }
        } else {
            Swal.fire('Error', res.error || 'Ocurrió un error al generar el reporte', 'error');
            tbody.innerHTML = `<tr><td colspan="${RCON_nColumnas()}" class="text-center py-4 text-danger">Error al generar reporte.</td></tr>`;
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error del Servidor', 'Detalle: ' + err.message, 'error');
        tbody.innerHTML = `<tr><td colspan="${RCON_nColumnas()}" class="text-center py-4 text-danger">Error de conexión.</td></tr>`;
    });
};

// Tras guardar la personalización, el reporte ya mostrado se vuelve a consultar con las
// columnas nuevas (si aún no se pulsó «Mostrar», no hay nada que redibujar).
document.addEventListener('reportePersonalizado', function () {
    if (window.rcon_reporte_generado) window.RCON_generarReporte();
});

// ── Selector Año/Mes: recalcula fecha_desde/fecha_hasta (los datos, al pulsar «Mostrar») ──
window.RCON_cambiarMesAnio = function () {
    const anio = document.getElementById('rcon-anio').value;
    const mes  = document.getElementById('rcon-mes').value;

    if (anio === 'TODOS') {
        document.getElementById('rcon-fecha-desde').value = '';
        document.getElementById('rcon-fecha-hasta').value = '';
    } else if (mes === 'TODOS') {
        document.getElementById('rcon-fecha-desde').value = `${anio}-01-01`;
        document.getElementById('rcon-fecha-hasta').value = `${anio}-12-31`;
    } else {
        const ultimoDia = new Date(parseInt(anio), parseInt(mes), 0).getDate();
        document.getElementById('rcon-fecha-desde').value = `${anio}-${mes}-01`;
        document.getElementById('rcon-fecha-hasta').value = `${anio}-${mes}-${String(ultimoDia).padStart(2, '0')}`;
    }
};

// modo: 'DETALLE' (una fila por ítem de cada documento) | 'DOCUMENTO' (una fila por documento).
window.RCON_exportarExcel = function (modo) {
    const params = new URLSearchParams(new FormData(document.getElementById('form-filtros-consolidado')));
    params.set('excel_modo', modo === 'DOCUMENTO' ? 'DOCUMENTO' : 'DETALLE');
    CMG_descargar(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params.toString());
};

window.RCON_exportarPDF = function () {
    const params = new URLSearchParams(new FormData(document.getElementById('form-filtros-consolidado'))).toString();
    CMG_descargar(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params);
};
