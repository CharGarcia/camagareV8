// Control de vista para Reporte de Ventas por Vendedor
document.addEventListener('DOMContentLoaded', function () {
    // Buscador predictivo de Producto
    let debounceTimerProducto;
    const searchProducto = document.getElementById('rvv-search-producto');
    const dropdownProducto = document.getElementById('rvv-dropdown-productos');
    const idProducto = document.getElementById('rvv-id-producto');

    searchProducto.addEventListener('input', function () {
        clearTimeout(debounceTimerProducto);
        const search = this.value.trim();

        if (idProducto.value) { idProducto.value = ''; }

        if (search.length < 2) {
            dropdownProducto.classList.add('d-none');
            return;
        }

        debounceTimerProducto = setTimeout(() => {
            fetch(BASE_URL + '/' + RUTA_MODULO + '/buscarProductosAjax?q=' + encodeURIComponent(search))
                .then(res => res.json())
                .then(data => {
                    dropdownProducto.innerHTML = '';
                    const items = data.data || [];
                    if (items.length > 0) {
                        items.forEach(item => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-2';
                            btn.style.fontSize = '0.85rem';
                            btn.innerHTML = `<strong>${item.nombre}</strong><br><small class="text-muted">${item.codigo || ''}</small>`;
                            btn.addEventListener('click', function () {
                                searchProducto.value = item.nombre;
                                idProducto.value = item.id;
                                dropdownProducto.classList.add('d-none');
                                window.RVV_generarReporte();
                            });
                            dropdownProducto.appendChild(btn);
                        });
                        dropdownProducto.classList.remove('d-none');
                    } else {
                        dropdownProducto.innerHTML = '<div class="list-group-item text-muted small">No se encontraron productos</div>';
                        dropdownProducto.classList.remove('d-none');
                    }
                })
                .catch(err => console.error(err));
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (!searchProducto.contains(e.target) && !dropdownProducto.contains(e.target)) {
            dropdownProducto.classList.add('d-none');
        }
    });

    document.getElementById('rvv-mes').addEventListener('change', window.RVV_cambiarMesAnio);
    document.getElementById('rvv-anio').addEventListener('change', window.RVV_cambiarMesAnio);

    window.RVV_generarReporte();
});

window.RVV_cambiarMesAnio = function () {
    const mes = document.getElementById('rvv-mes').value;
    const anio = document.getElementById('rvv-anio').value;

    if (!mes || !anio) return;

    if (anio === 'TODOS') {
        document.getElementById('rvv-fecha-desde').value = '';
        document.getElementById('rvv-fecha-hasta').value = '';
    } else if (mes === 'TODOS') {
        document.getElementById('rvv-fecha-desde').value = anio + '-01-01';
        document.getElementById('rvv-fecha-hasta').value = anio + '-12-31';
    } else {
        const fechaHasta = new Date(anio, parseInt(mes), 0);
        document.getElementById('rvv-fecha-desde').value = anio + '-' + mes + '-01';
        document.getElementById('rvv-fecha-hasta').value = anio + '-' + mes + '-' + fechaHasta.getDate().toString().padStart(2, '0');
    }

    window.RVV_generarReporte();
};

window.RVV_generarReporte = function () {
    const form = document.getElementById('form-filtros-rvv');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData).toString();

    const agruparPor = document.getElementById('rvv_agrupar_por').value;
    RVV_dibujarCabecera(agruparPor);

    const tbody = document.getElementById('rvv_tbody');
    const colSpanActual = RVV_colSpanPara(agruparPor);
    tbody.innerHTML = `<tr><td colspan="${colSpanActual}" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><br><span class="text-muted small mt-2 d-inline-block">Generando reporte...</span></td></tr>`;

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
            console.error("Parse error. Raw response:", text.substring(0, 500));
            throw new Error(text.substring(0, 100));
        }
    })
    .then(res => {
        if (res.ok) {
            tbody.innerHTML = res.rows;

            if (res.stats) {
                document.getElementById('rvv-stat-base-0').textContent = parseFloat(res.stats.total_base_0).toFixed(2);
                document.getElementById('rvv-stat-base-iva').textContent = parseFloat(res.stats.total_base_iva).toFixed(2);
                document.getElementById('rvv-stat-iva').textContent = parseFloat(res.stats.total_iva).toFixed(2);
                document.getElementById('rvv-stat-total').textContent = parseFloat(res.stats.gran_total).toFixed(2);
                document.getElementById('rvv-stat-saldo').textContent = parseFloat(res.stats.total_saldo || 0).toFixed(2);
            }
            if (res.estados) {
                document.getElementById('rvv-stat-documentos').textContent = res.estados.autorizados;
                document.getElementById('rvv-stat-borradores').textContent = res.estados.borradores;
                document.getElementById('rvv-stat-anulados').textContent = res.estados.anulados;
            }
        } else {
            Swal.fire('Error', res.error || 'Ocurrió un error al generar el reporte', 'error');
            tbody.innerHTML = `<tr><td colspan="${colSpanActual}" class="text-center py-4 text-danger">Error al generar reporte.</td></tr>`;
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error del Servidor', 'Detalle: ' + err.message, 'error');
        tbody.innerHTML = `<tr><td colspan="${colSpanActual}" class="text-center py-4 text-danger">Error de conexión.</td></tr>`;
    });
};

function RVV_colSpanPara(agruparPor) {
    if (agruparPor === 'PRODUCTO') return 10;
    if (agruparPor === 'MARCA' || agruparPor === 'CATEGORIA') return 6;
    if (agruparPor === 'NINGUNO') return 10;
    return 7; // VENDEDOR, MES
}

function RVV_dibujarCabecera(agruparPor) {
    let theadHtml = '<tr class="text-secondary" style="font-family: \'Outfit\', sans-serif;">';

    if (agruparPor === 'PRODUCTO') {
        theadHtml += `
            <th class="ps-4">Código</th>
            <th>Producto</th>
            <th>Marca</th>
            <th>Categoría</th>
            <th class="text-center">Cant. Vendida</th>
            <th class="text-center">Tipo IVA</th>
            <th class="text-end">Base 0% / Exento</th>
            <th class="text-end">Base IVA</th>
            <th class="text-end">Total IVA</th>
            <th class="text-end pe-4">Gran Total</th>
        `;
    } else if (agruparPor === 'MARCA') {
        theadHtml += `
            <th class="ps-4">Marca</th>
            <th class="text-center">Cant. Vendida</th>
            <th class="text-end">Base 0% / Exento</th>
            <th class="text-end">Base IVA</th>
            <th class="text-end">Total IVA</th>
            <th class="text-end pe-4">Gran Total</th>
        `;
    } else if (agruparPor === 'CATEGORIA') {
        theadHtml += `
            <th class="ps-4">Categoría</th>
            <th class="text-center">Cant. Vendida</th>
            <th class="text-end">Base 0% / Exento</th>
            <th class="text-end">Base IVA</th>
            <th class="text-end">Total IVA</th>
            <th class="text-end pe-4">Gran Total</th>
        `;
    } else if (agruparPor === 'MES') {
        theadHtml += `
            <th class="ps-4">Mes</th>
            <th class="text-center">Nro Documentos</th>
            <th class="text-end">Base 0% / Exento</th>
            <th class="text-end">Base IVA</th>
            <th class="text-end">Total IVA</th>
            <th class="text-end">Gran Total</th>
            <th class="text-end pe-4">Saldo</th>
        `;
    } else if (agruparPor === 'NINGUNO') {
        theadHtml += `
            <th class="ps-4">Fecha</th>
            <th>Nro Documento</th>
            <th>Cliente</th>
            <th class="text-center">Estado</th>
            <th>Vendedor</th>
            <th class="text-end">Base 0% / Exento</th>
            <th class="text-end">Base IVA</th>
            <th class="text-end">Total IVA</th>
            <th class="text-end">Gran Total</th>
            <th class="text-end pe-4">Saldo</th>
        `;
    } else {
        // VENDEDOR
        theadHtml += `
            <th class="ps-4">Vendedor</th>
            <th class="text-center">Nro Documentos</th>
            <th class="text-end">Base 0% / Exento</th>
            <th class="text-end">Base IVA</th>
            <th class="text-end">Total IVA</th>
            <th class="text-end">Gran Total</th>
            <th class="text-end pe-4">Saldo</th>
        `;
    }

    theadHtml += '</tr>';
    document.getElementById('rvv_thead').innerHTML = theadHtml;
}

window.RVV_exportarExcel = function () {
    const form = document.getElementById('form-filtros-rvv');
    const params = new URLSearchParams(new FormData(form)).toString();
    window.open(BASE_URL + '/' + RUTA_MODULO + '/exportExcel?' + params, '_blank');
};

window.RVV_exportarPDF = function () {
    const form = document.getElementById('form-filtros-rvv');
    const params = new URLSearchParams(new FormData(form)).toString();
    window.open(BASE_URL + '/' + RUTA_MODULO + '/exportPdf?' + params, '_blank');
};

/* ════════════════════════════════════════════════════
   MODAL: Enviar reporte por correo
════════════════════════════════════════════════════ */
window.RVV_abrirModalCorreo = function () {
    document.getElementById('rvv-email-destino').value = '';
    document.getElementById('rvv-email-asunto').value = '';
    document.getElementById('rvv-email-mensaje').value = '';
    new bootstrap.Modal(document.getElementById('modalCorreoRVV')).show();
};

window.RVV_enviarCorreo = function () {
    const email = document.getElementById('rvv-email-destino').value.trim();
    const asunto = document.getElementById('rvv-email-asunto').value.trim();
    const mensaje = document.getElementById('rvv-email-mensaje').value.trim();

    if (!email) {
        Swal.fire('Atención', 'Ingrese el correo destinatario.', 'warning');
        return;
    }

    const form = document.getElementById('form-filtros-rvv');
    const formData = new FormData(form);
    formData.append('email', email);
    formData.append('asunto', asunto);
    formData.append('mensaje', mensaje);

    const btn = document.getElementById('rvv-btn-enviar-correo');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando...';

    fetch(BASE_URL + '/' + RUTA_MODULO + '/enviarCorreoAjax', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i>Enviar Correo';
        if (data.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalCorreoRVV')).hide();
            Swal.fire('Enviado', data.mensaje || 'Correo enviado correctamente.', 'success');
        } else {
            Swal.fire('Error', data.error || 'No se pudo enviar el correo.', 'error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i>Enviar Correo';
        Swal.fire('Error de conexión', err.message, 'error');
    });
};

/* ════════════════════════════════════════════════════
   PANEL LATERAL: detalle del documento (modo Detallado)
════════════════════════════════════════════════════ */
document.addEventListener('click', function (e) {
    const tr = e.target.closest('tr[data-doc-id]');
    if (!tr) return;
    if (e.target.closest('button, a, input, select, label')) return;
    if (typeof window.CMG_abrirPreviewDoc !== 'function') return;

    window.CMG_abrirPreviewDoc(tr.dataset.docId, tr.dataset.docTipo, {
        numero: tr.dataset.docNumero || '',
        sujetoLabel: 'Cliente',
        sujeto: tr.dataset.docSujeto || ''
    });
});

/* ════════════════════════════════════════════════════
   OFFCANVAS: detalle de documentos por vendedor (drill-down
   desde una fila de la agrupación "Por Vendedor").
════════════════════════════════════════════════════ */
document.addEventListener('click', function (e) {
    const tr = e.target.closest('tr[data-vendedor-id]');
    if (!tr) return;
    if (e.target.closest('button, a, input, select, label')) return;

    window.RVV_abrirDetalleVendedor(tr.dataset.vendedorId, tr.dataset.vendedorNombre || '');
});

window.RVV_abrirDetalleVendedor = function (idVendedor, nombreVendedor) {
    const offcanvasEl = document.getElementById('offcanvasDetalleVendedor');
    const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
    const loading = document.getElementById('rvv-dv-loading');
    const content = document.getElementById('rvv-dv-content');
    const tbody = document.getElementById('rvv-dv-tbody');

    document.getElementById('rvv-dv-subtitulo').textContent = nombreVendedor;
    loading.classList.remove('d-none');
    content.classList.add('d-none');
    tbody.innerHTML = '';

    bsOffcanvas.show();

    const form = document.getElementById('form-filtros-rvv');
    const formData = new FormData(form);
    formData.set('id_vendedor', idVendedor);

    fetch(BASE_URL + '/' + RUTA_MODULO + '/detalleVendedorAjax', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        loading.classList.add('d-none');
        content.classList.remove('d-none');

        if (!data.ok) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${data.error || 'Error al cargar el detalle.'}</td></tr>`;
            document.getElementById('rvv-dv-total').textContent = '0.00';
            document.getElementById('rvv-dv-saldo').textContent = '0.00';
            return;
        }

        const docs = data.documentos || [];
        if (docs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Sin documentos en el período filtrado.</td></tr>';
            document.getElementById('rvv-dv-total').textContent = '0.00';
            document.getElementById('rvv-dv-saldo').textContent = '0.00';
            return;
        }

        let totalNeto = 0;
        let totalSaldo = 0;
        let html = '';
        docs.forEach(d => {
            const subtotal = parseFloat(d.subtotal || 0);
            const nc = parseFloat(d.nc || 0);
            const total = parseFloat(d.total || 0);
            const saldo = parseFloat(d.saldo || 0);
            totalNeto += total;
            totalSaldo += saldo;

            html += `<tr>
                <td class="ps-3">${d.fecha_emision ? d.fecha_emision.split('-').reverse().join('/') : ''}</td>
                <td class="fw-bold">${d.numero_factura || ''}</td>
                <td>${d.cliente_nombre || ''}</td>
                <td class="text-end">$${subtotal.toFixed(2)}</td>
                <td class="text-end ${nc > 0 ? 'text-danger' : 'text-muted'}">${nc > 0 ? '-$' + nc.toFixed(2) : '—'}</td>
                <td class="text-end fw-bold text-success">$${total.toFixed(2)}</td>
                <td class="text-end pe-3 fw-bold ${saldo > 0.01 ? 'text-danger' : 'text-success'}">$${saldo.toFixed(2)}</td>
            </tr>`;
        });

        tbody.innerHTML = html;
        document.getElementById('rvv-dv-total').textContent = totalNeto.toFixed(2);
        document.getElementById('rvv-dv-saldo').textContent = totalSaldo.toFixed(2);
    })
    .catch(err => {
        loading.classList.add('d-none');
        content.classList.remove('d-none');
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">Error de conexión: ${err.message}</td></tr>`;
    });
};
