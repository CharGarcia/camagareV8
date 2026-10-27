// Control de vista para Mejor Cliente
document.addEventListener('DOMContentLoaded', function () {
    if (typeof aplicarFavoritosModal === 'function') {
        aplicarFavoritosModal();
    }

    const mesEl = document.getElementById('mc-mes');
    if (mesEl && (!mesEl.value || mesEl.value === 'TODOS')) {
        const currentMonth = (new Date().getMonth() + 1).toString().padStart(2, '0');
        mesEl.value = currentMonth;
    }

    document.getElementById('mc-mes').addEventListener('change', window.MC_cambiarMesAnio);
    document.getElementById('mc-anio').addEventListener('change', window.MC_cambiarMesAnio);

    window.MC_generarReporte();
});

window.MC_cambiarMesAnio = function () {
    const mes = document.getElementById('mc-mes').value;
    const anio = document.getElementById('mc-anio').value;

    if (!mes || !anio) return;

    if (anio === 'TODOS') {
        document.getElementById('mc-fecha-desde').value = '';
        document.getElementById('mc-fecha-hasta').value = '';
    } else if (mes === 'TODOS') {
        document.getElementById('mc-fecha-desde').value = anio + '-01-01';
        document.getElementById('mc-fecha-hasta').value = anio + '-12-31';
    } else {
        const fechaHasta = new Date(anio, parseInt(mes), 0);
        document.getElementById('mc-fecha-desde').value = anio + '-' + mes + '-01';
        document.getElementById('mc-fecha-hasta').value = anio + '-' + mes + '-' + fechaHasta.getDate().toString().padStart(2, '0');
    }

    window.MC_generarReporte();
};

window.MC_generarReporte = function () {
    const form = document.getElementById('form-filtros-reporte');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData).toString();
    // El detalle de un cliente usa los filtros con que se generó el ranking que se está viendo.
    MC_paramsRanking = params;

    const tbody = document.getElementById('mc_tbody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><br><span class="text-muted small mt-2 d-inline-block">Generando reporte...</span></td></tr>';

    fetch(BASE_URL + '/' + RUTA_MODULO + '/generarAjax', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: params
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Parse error. Raw response:', text.substring(0, 500));
            throw new Error(text.substring(0, 100));
        }
    })
    .then(res => {
        if (res.ok) {
            tbody.innerHTML = res.rows;

            if (res.stats) {
                document.getElementById('stat-clientes').textContent = res.stats.total_clientes;
                document.getElementById('stat-documentos').textContent = res.stats.total_documentos;
                document.getElementById('stat-monto').textContent = '$' + parseFloat(res.stats.monto_neto_total).toFixed(2);
                document.getElementById('stat-venta-promedio').textContent = '$' + parseFloat(res.stats.venta_promedio).toFixed(2);
            }

            const chartContainer = document.getElementById('chart-container');
            if (res.rawData && res.rawData.length > 0) {
                chartContainer.style.display = 'flex';
                MC_dibujarGrafico(res.rawData, document.getElementById('mc_orden_por').value);
            } else {
                chartContainer.style.display = 'none';
            }
        } else {
            Swal.fire('Error', res.error || 'Ocurrió un error al generar el reporte', 'error');
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Error al generar reporte.</td></tr>';
            document.getElementById('chart-container').style.display = 'none';
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error del Servidor', 'Detalle: ' + err.message, 'error');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Error de conexión.</td></tr>';
        document.getElementById('chart-container').style.display = 'none';
    });
};

let MC_paramsRanking = '';
let MC_detallePeticion = 0;

const MC_esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const MC_dinero = v => (v < 0 ? '-$' : '$') + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const MC_cantidad = v => v.toLocaleString('en-US', { maximumFractionDigits: 4 });

/** Abre el modal con los productos vendidos al cliente de la fila (mismos filtros del ranking). */
window.MC_verDetalle = function (fila) {
    const idCliente = fila.dataset.idCliente;
    const montoFila = parseFloat(fila.dataset.monto) || 0;
    const desde = document.getElementById('mc-fecha-desde').value;
    const hasta = document.getElementById('mc-fecha-hasta').value;
    const fmtFecha = f => f ? f.split('-').reverse().join('-') : '';

    document.getElementById('mc-det-cliente').textContent = fila.dataset.nombre || '';
    document.getElementById('mc-det-subtitulo').textContent = (fila.dataset.ruc || '')
        + (desde || hasta ? ' · Período del ' + fmtFecha(desde) + ' al ' + fmtFecha(hasta) : ' · Todo el período');
    document.getElementById('mc-det-buscar').value = '';
    document.getElementById('mc-det-resumen').textContent = '';
    document.getElementById('mc-det-tfoot').innerHTML = '';
    const tbody = document.getElementById('mc-det-tbody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></td></tr>';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalleCliente')).show();

    // Si se abre otro cliente antes de que responda el anterior, la respuesta vieja se descarta.
    const peticion = ++MC_detallePeticion;
    fetch(BASE_URL + '/' + RUTA_MODULO + '/detalleProductosAjax', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: MC_paramsRanking + '&id_cliente=' + encodeURIComponent(idCliente)
    })
    .then(r => r.json())
    .then(res => {
        if (peticion !== MC_detallePeticion) return;
        if (!res.ok) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">' + MC_esc(res.error || 'No se pudo cargar el detalle.') + '</td></tr>';
            return;
        }
        MC_pintarDetalle(res.rows || [], montoFila);
    })
    .catch(err => {
        if (peticion !== MC_detallePeticion) return;
        console.error(err);
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Error de conexión.</td></tr>';
    });
};

function MC_pintarDetalle(rows, montoFila) {
    const tbody = document.getElementById('mc-det-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Los documentos de este cliente no tienen detalle de productos.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(r => {
        const monto = parseFloat(r.monto_neto) || 0;
        const buscar = (r.producto_codigo + ' ' + r.producto_nombre).toLowerCase();
        return '<tr data-buscar="' + MC_esc(buscar) + '">'
            + '<td class="ps-3 text-nowrap">' + MC_esc(r.producto_codigo) + '</td>'
            + '<td>' + MC_esc(r.producto_nombre) + '</td>'
            + '<td class="text-nowrap">' + MC_esc(r.unidad_medida) + '</td>'
            + '<td class="text-end">' + MC_cantidad(parseFloat(r.cantidad) || 0) + '</td>'
            + '<td class="text-center">' + (parseInt(r.cantidad_documentos) || 0) + '</td>'
            + '<td class="text-end fw-bold ' + (monto < 0 ? 'text-danger' : 'text-success') + '">' + MC_dinero(monto) + '</td>'
            + '<td class="text-end pe-3">' + (parseFloat(r.pct) || 0).toFixed(2) + '%</td>'
            + '</tr>';
    }).join('');

    const total = CMG_r2(rows.reduce((s, r) => s + (parseFloat(r.monto_neto) || 0), 0));
    let pie = '<tr><td colspan="5" class="ps-3">TOTAL (' + rows.length + ' ' + (rows.length === 1 ? 'producto' : 'productos') + ')</td>'
        + '<td class="text-end">' + MC_dinero(total) + '</td><td class="pe-3"></td></tr>';
    // El monto del ranking sale de las cabeceras; si las líneas no suman lo mismo (documentos
    // migrados sin detalle, descuentos de cabecera) se muestra la diferencia en lugar de esconderla.
    const diferencia = CMG_r2(montoFila - total);
    if (Math.abs(diferencia) >= 0.01) {
        pie += '<tr class="fw-normal text-muted"><td colspan="5" class="ps-3 small">Diferencia con el monto neto del ranking (' + MC_dinero(montoFila) + '): documentos sin detalle de productos</td>'
            + '<td class="text-end">' + MC_dinero(diferencia) + '</td><td class="pe-3"></td></tr>';
    }
    document.getElementById('mc-det-tfoot').innerHTML = pie;
    MC_filtrarDetalle();
}

window.MC_filtrarDetalle = function () {
    const q = document.getElementById('mc-det-buscar').value.trim().toLowerCase();
    const filas = document.querySelectorAll('#mc-det-tbody tr[data-buscar]');
    let visibles = 0;
    filas.forEach(tr => {
        const ok = q === '' || q.split(/\s+/).every(p => tr.dataset.buscar.includes(p));
        tr.style.display = ok ? '' : 'none';
        if (ok) visibles++;
    });
    document.getElementById('mc-det-resumen').textContent = filas.length
        ? (q === '' ? filas.length + (filas.length === 1 ? ' producto' : ' productos') : visibles + ' de ' + filas.length + ' productos')
        : '';
};

let chartInstance = null;
function MC_dibujarGrafico(rawData, ordenPor) {
    const ctx = document.getElementById('reporteChart').getContext('2d');
    if (chartInstance) chartInstance.destroy();

    const top = rawData.slice(0, 15);
    const labels = top.map(r => r.cliente_nombre);
    const esCantidad = ordenPor === 'cantidad';
    const data = top.map(r => parseFloat(esCantidad ? r.cantidad_documentos : r.monto_neto));

    document.getElementById('chart-titulo').textContent = esCantidad
        ? 'Top Clientes por Cantidad de Documentos'
        : 'Top Clientes por Monto Neto';

    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: esCantidad ? 'Cantidad de Documentos' : 'Monto Neto ($)',
                data: data,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } }
        }
    });
}

window.MC_exportarExcel = function () {
    const form = document.getElementById('form-filtros-reporte');
    const params = new URLSearchParams(new FormData(form)).toString();
    CMG_descargar(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params);
};

window.MC_exportarPDF = function () {
    const form = document.getElementById('form-filtros-reporte');
    const params = new URLSearchParams(new FormData(form)).toString();
    CMG_descargar(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params);
};

window.MC_enviarCorreo = function () {
    const email = document.getElementById('mc-email-destino').value.trim();
    if (!email) {
        Swal.fire('Falta el correo', 'Indica al menos un destinatario.', 'warning');
        return;
    }

    const form = document.getElementById('form-filtros-reporte');
    const formData = new FormData(form);
    formData.append('email', email);
    formData.append('asunto', document.getElementById('mc-email-asunto').value.trim());
    formData.append('mensaje', document.getElementById('mc-email-mensaje').value.trim());
    const params = new URLSearchParams(formData).toString();

    const btn = document.getElementById('btn-enviar-correo-reporte');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...';

    fetch(BASE_URL + '/' + RUTA_MODULO + '/enviarEmailAjax', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: params
    })
    .then(res => res.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar';
        if (res.ok) {
            const modalEl = document.getElementById('modalEnviarCorreo');
            bootstrap.Modal.getInstance(modalEl)?.hide();
            Swal.fire('Enviado', res.mensaje || 'Correo enviado correctamente.', 'success');
        } else {
            Swal.fire('Error', res.error || 'No se pudo enviar el correo.', 'error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar';
        console.error(err);
        Swal.fire('Error del Servidor', 'Detalle: ' + err.message, 'error');
    });
};
