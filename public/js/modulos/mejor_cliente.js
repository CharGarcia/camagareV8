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
    window.open(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params, '_blank');
};

window.MC_exportarPDF = function () {
    const form = document.getElementById('form-filtros-reporte');
    const params = new URLSearchParams(new FormData(form)).toString();
    window.open(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params, '_blank');
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
