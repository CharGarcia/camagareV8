/**
 * Ficha del producto en cada fila del detalle de un documento (ícono ⓘ).
 *
 * Uso en la vista:
 *   - En la plantilla de la fila, dentro de la celda de la descripción (position-relative):
 *       <span class="span-info-producto d-none" ...><i class="bi bi-info-circle-fill"></i></span>
 *   - Al poner/quitar el producto en la fila:
 *       CMG_pintarInfoProducto(row, { nombre, codigo, codigo_auxiliar, codigo_barras,
 *                                     categoria, marca, unidad, ubicacion, tipo_produccion,
 *                                     precio_base })   // precio_base: texto ya formateado
 *                                                      // con los decimales de la empresa
 *       CMG_pintarInfoProducto(row, null)   // ítem libre o selección borrada
 *
 * La ficha se guarda en la fila (data-info-producto) y UNA sola tarjeta flotante global
 * la muestra: con cientos de filas no se crea un popover de Bootstrap por fila.
 * Hover en escritorio; toque/clic en celular (donde el hover no existe).
 * El saldo se lee al abrir del .span-saldo-info de la fila (bodega y unidad elegidas).
 *
 * Lo usan: Facturas de Venta y Recibos de Venta.
 */
(function () {
    if (window.__CMG_FICHA_PRODUCTO) return;
    window.__CMG_FICHA_PRODUCTO = true;

    window.CMG_pintarInfoProducto = function (row, info) {
        const span = row ? row.querySelector('.span-info-producto') : null;
        if (!span) return;
        if (!info) {
            delete row.dataset.infoProducto;
            span.classList.add('d-none');
            return;
        }
        row.dataset.infoProducto = JSON.stringify(info);
        span.classList.remove('d-none');
    };

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let pop = null;
    let anclaActual = null;
    let fijada = false; // abierta por clic/toque: no se cierra al sacar el mouse

    const obtenerPop = () => {
        if (pop) return pop;
        pop = document.createElement('div');
        pop.className = 'card shadow border-0 d-none';
        // Por encima de los modales (y de los anidados, 5060).
        pop.style.cssText = 'position:fixed; z-index:5100; width:270px; max-width:calc(100vw - 16px); font-size:0.75rem;';
        document.body.appendChild(pop);
        return pop;
    };

    const cerrar = () => {
        if (pop) pop.classList.add('d-none');
        anclaActual = null;
        fijada = false;
    };

    const abrir = (span) => {
        const row = span.closest('tr');
        if (!row || !row.dataset.infoProducto) return;
        let info;
        try { info = JSON.parse(row.dataset.infoProducto); } catch (e) { return; }

        const saldoBox = row.querySelector('.span-saldo-info');
        const saldo = (saldoBox && !saldoBox.classList.contains('d-none'))
            ? (row.querySelector('.lbl-saldo-valor')?.textContent || '') : '';

        const linea = (icono, etiqueta, valor) => (valor === undefined || valor === null || String(valor).trim() === '') ? '' :
            `<div class="d-flex gap-2 py-1 border-bottom border-light">
                <i class="bi ${icono} text-secondary" style="width:14px;"></i>
                <span class="text-muted" style="min-width:78px;">${etiqueta}</span>
                <span class="fw-semibold text-break">${esc(valor)}</span>
            </div>`;

        const tipo = info.tipo_produccion === '02' ? 'Servicio' : 'Bien';
        const p = obtenerPop();
        p.innerHTML = `
            <div class="card-header bg-white py-2 px-3 fw-bold" style="font-size:0.8rem;">
                <i class="bi bi-box-seam text-primary me-1"></i>${esc(info.nombre || 'Producto')}
                <span class="badge bg-secondary bg-opacity-10 text-secondary ms-1">${tipo}</span>
            </div>
            <div class="card-body py-1 px-3">
                ${linea('bi-tags', 'Categoría', info.categoria || 'Sin categoría')}
                ${linea('bi-award', 'Marca', info.marca || 'Sin marca')}
                ${linea('bi-upc', 'Código', info.codigo)}
                ${linea('bi-upc-scan', 'Cód. auxiliar', info.codigo_auxiliar)}
                ${linea('bi-upc-scan', 'Cód. barras', info.codigo_barras)}
                ${linea('bi-rulers', 'Unidad base', info.unidad)}
                ${linea('bi-currency-dollar', 'Precio base', info.precio_base ? '$' + info.precio_base : '')}
                ${linea('bi-geo-alt', 'Ubicación', info.ubicacion)}
                ${linea('bi-boxes', 'Saldo bodega', saldo)}
            </div>`;
        p.classList.remove('d-none');

        // Posición: bajo el ícono, alineada a su derecha, sin salirse de la pantalla.
        const r = span.getBoundingClientRect();
        const w = p.offsetWidth, h = p.offsetHeight;
        const left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8));
        let top = r.bottom + 4;
        if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - h - 4);
        p.style.left = left + 'px';
        p.style.top = top + 'px';
        anclaActual = span;
    };

    document.addEventListener('mouseover', (e) => {
        const span = e.target.closest && e.target.closest('.span-info-producto');
        if (span && !fijada) abrir(span);
    });
    document.addEventListener('mouseout', (e) => {
        if (fijada || !anclaActual) return;
        const span = e.target.closest && e.target.closest('.span-info-producto');
        if (span && !span.contains(e.relatedTarget)) cerrar();
    });
    document.addEventListener('click', (e) => {
        const span = e.target.closest && e.target.closest('.span-info-producto');
        if (span) {
            e.preventDefault();
            e.stopPropagation();
            if (fijada && anclaActual === span) { cerrar(); return; }
            abrir(span);
            fijada = true;
            return;
        }
        if (pop && !pop.contains(e.target)) cerrar();
    }, true);
    // Al desplazar la tabla o la ventana la tarjeta quedaría suelta: se cierra.
    window.addEventListener('scroll', () => { if (anclaActual) cerrar(); }, true);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && anclaActual) cerrar(); });
    document.addEventListener('hidden.bs.modal', cerrar);
})();
