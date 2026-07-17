<?php
/**
 * Partial compartido: panel lateral (offcanvas) con el detalle de un documento.
 *
 * Uso desde cualquier módulo:
 *     <?php require MVC_APP . '/views/partials/offcanvas_doc_preview.php'; ?>
 *     ...
 *     CMG_abrirPreviewDoc(id, tipo, extra)
 *
 *   tipo:  FACTURA | FACTURA_VENTA | VENTA | RECIBO | RECIBO_VENTA
 *          | COMPRA | LIQUIDACION | IMPORTACION | SALDO_INICIAL
 *
 *   extra: (opcional) datos que el llamador ya tiene en la fila. Se usan como
 *          respaldo mientras carga y si el documento no se puede consultar.
 *          Para SALDO_INICIAL es obligatorio (no hay documento que consultar):
 *          { numero, fecha, sujetoLabel, sujeto, total }
 *
 * Nota: cada endpoint de detalle valida el permiso de LECTURA de SU módulo
 * (compras, factura-venta, etc.). Si el usuario no lo tiene, el panel muestra
 * un aviso en lugar de romper la pantalla.
 *
 * Requiere Bootstrap 5 (Offcanvas) y la constante BASE_URL.
 */
?>
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasDocPreview"
     aria-labelledby="offcanvasDocPreviewLabel" style="width: 420px;">
    <div class="offcanvas-header bg-light border-bottom py-2 px-3">
        <h6 class="offcanvas-title fw-bold text-primary mb-0 d-flex align-items-center" id="offcanvasDocPreviewLabel">
            <i class="bi bi-file-earmark-text me-2 fs-5"></i> Detalle del Documento
        </h6>
        <button type="button" class="btn-close btn-close-sm text-reset" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column bg-light bg-opacity-25" style="overflow:hidden;">

        <!-- Cargando -->
        <div id="preview-doc-loading" class="text-center py-5 d-none flex-grow-1 d-flex flex-column justify-content-center">
            <div class="spinner-border spinner-border-sm text-primary mx-auto mb-2" role="status"></div>
            <div class="small text-muted">Cargando desglose...</div>
        </div>

        <!-- Error / sin permiso -->
        <div id="preview-doc-error" class="text-center py-5 d-none flex-grow-1 d-flex flex-column justify-content-center px-4">
            <i class="bi bi-exclamation-triangle text-warning fs-3 mb-2"></i>
            <div class="small text-muted" id="preview-doc-error-txt">No se pudo cargar el detalle.</div>
        </div>

        <!-- Contenido -->
        <div id="preview-doc-content" class="d-flex flex-column h-100 w-100 d-none">
            <!-- Encabezado -->
            <div class="bg-white p-3 border-bottom shadow-sm">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <div>
                        <span id="p-badge-tipo" class="badge bg-primary bg-opacity-10 text-primary border mb-1" style="font-size:0.65rem;">FACTURA</span>
                        <h6 id="p-txt-numero" class="fw-bold mb-0 text-dark" style="font-family:monospace; font-size: 0.95rem;">000-000-000000000</h6>
                    </div>
                    <div class="text-end">
                        <span class="small text-muted d-block" style="font-size:0.7rem;">Fecha</span>
                        <strong id="p-txt-fecha" class="small text-dark">--/--/----</strong>
                    </div>
                </div>
                <div class="mt-2 pt-2 border-top">
                    <span class="small text-muted d-block mb-0" id="p-lbl-sujeto" style="font-size:0.7rem;">Cliente / Proveedor</span>
                    <span id="p-txt-sujeto" class="small fw-medium text-dark">—</span>
                </div>
            </div>

            <!-- Ítems -->
            <div class="flex-grow-1 overflow-auto p-3">
                <h6 class="small fw-bold text-muted mb-2" style="font-size:0.75rem; letter-spacing: 0.5px;">ÍTEMS DETALLADOS</h6>
                <div id="p-container-items" class="d-flex flex-column gap-2"></div>
            </div>

            <!-- Totales -->
            <div class="bg-white p-3 border-top shadow-sm mt-auto">
                <div class="d-flex justify-content-between mb-1" id="p-row-subtotal">
                    <span class="small text-muted" style="font-size:0.75rem;">Subtotal</span>
                    <span id="p-txt-subtotal" class="small fw-medium text-dark">$0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-1" id="p-row-iva">
                    <span class="small text-muted" style="font-size:0.75rem;">IVA</span>
                    <span id="p-txt-iva" class="small fw-medium text-dark">$0.00</span>
                </div>
                <div class="d-flex justify-content-between pt-2 border-top border-2">
                    <span class="fw-bold text-dark" style="font-size:0.85rem;" id="p-lbl-total">Total Documento</span>
                    <span id="p-txt-total" class="fw-bold text-primary fs-6">$0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Por encima de modales apilados (el panel puede abrirse desde un modal) */
    #offcanvasDocPreview { z-index: 6000 !important; }
    .offcanvas-backdrop  { z-index: 5990 !important; }
</style>

<script>
(function () {
    'use strict';

    const BASE = '<?= BASE_URL ?>';

    // tipo -> endpoint de detalle + etiquetas
    const TIPOS = {
        FACTURA:       { url: BASE + '/modulos/factura-venta/getFacturaAjax',        badge: 'FACTURA DE VENTA', sujeto: 'Cliente',   forma: 'cabecera' },
        FACTURA_VENTA: { url: BASE + '/modulos/factura-venta/getFacturaAjax',        badge: 'FACTURA DE VENTA', sujeto: 'Cliente',   forma: 'cabecera' },
        VENTA:         { url: BASE + '/modulos/factura-venta/getFacturaAjax',        badge: 'FACTURA DE VENTA', sujeto: 'Cliente',   forma: 'cabecera' },
        RECIBO:        { url: BASE + '/modulos/recibo-venta/getFacturaAjax',         badge: 'RECIBO DE VENTA',  sujeto: 'Cliente',   forma: 'cabecera' },
        RECIBO_VENTA:  { url: BASE + '/modulos/recibo-venta/getFacturaAjax',         badge: 'RECIBO DE VENTA',  sujeto: 'Cliente',   forma: 'cabecera' },
        COMPRA:        { url: BASE + '/modulos/compras/getCompraAjax',               badge: 'FACTURA DE COMPRA', sujeto: 'Proveedor', forma: 'data' },
        LIQUIDACION:   { url: BASE + '/modulos/liquidacion-compra/getLiquidacionAjax', badge: 'LIQUIDACIÓN',    sujeto: 'Proveedor', forma: 'cabecera' },
        IMPORTACION:   { url: BASE + '/modulos/importaciones/getImportacionAjax',     badge: 'IMPORTACIÓN',     sujeto: 'Proveedor exterior', forma: 'data' }
    };

    const $ = (id) => document.getElementById(id);

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function money(v) {
        const n = parseFloat(v);
        return '$' + (isNaN(n) ? 0 : n).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    // 'YYYY-MM-DD ...' -> 'DD-MM-YYYY'
    function fecha(f) {
        if (!f) return '';
        const d = String(f).substring(0, 10).split('-');
        return d.length === 3 ? `${d[2]}-${d[1]}-${d[0]}` : String(f);
    }

    function mostrar(cual) {
        ['preview-doc-loading', 'preview-doc-error', 'preview-doc-content']
            .forEach(id => $(id).classList.toggle('d-none', id !== cual));
    }

    function error(msg) {
        $('preview-doc-error-txt').textContent = msg || 'No se pudo cargar el detalle.';
        mostrar('preview-doc-error');
    }

    function pintarCabecera({ badge, numero, fechaTxt, sujetoLabel, sujeto }) {
        $('p-badge-tipo').textContent = badge || '';
        $('p-txt-numero').textContent = numero || '';
        $('p-txt-fecha').textContent  = fechaTxt || '';
        $('p-lbl-sujeto').textContent = sujetoLabel || '';
        $('p-txt-sujeto').textContent = sujeto || '—';
    }

    function pintarTotales({ subtotal, iva, total, soloTotal, labelTotal }) {
        $('p-row-subtotal').classList.toggle('d-none', !!soloTotal);
        $('p-row-iva').classList.toggle('d-none', !!soloTotal);
        $('p-lbl-total').textContent = labelTotal || 'Total Documento';
        if (!soloTotal) {
            $('p-txt-subtotal').textContent = money(subtotal);
            $('p-txt-iva').textContent      = money(iva);
        }
        $('p-txt-total').textContent = money(total);
    }

    function pintarItems(dets, aviso) {
        const cont = $('p-container-items');
        if (aviso) {
            cont.innerHTML = `<div class="text-center py-3 text-muted small">${esc(aviso)}</div>`;
            return;
        }
        if (!dets || dets.length === 0) {
            cont.innerHTML = '<div class="text-center py-3 text-muted small">Sin ítems registrados.</div>';
            return;
        }
        cont.innerHTML = dets.map(d => {
            const cant   = parseFloat(d.cantidad || 0);
            const pUni   = parseFloat(d.precio_unitario || d.costo_unitario || 0);
            const dTotal = parseFloat(d.precio_total_sin_impuesto || d.subtotal || (cant * pUni));
            const desc   = d.descripcion || d.producto_nombre || 'Ítem';
            const cod    = d.codigo_principal || d.codigo || '';
            return `
                <div class="bg-white border rounded-3 p-2 shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div style="max-width:70%">
                            ${cod ? `<small class="d-block text-muted" style="font-size:0.7rem;">Cod: ${esc(cod)}</small>` : ''}
                            <strong class="d-block text-dark small text-truncate" title="${esc(desc)}">${esc(desc)}</strong>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold text-secondary small">${money(dTotal)}</span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1 pt-1 border-top border-light" style="font-size:0.75rem;">
                        <span class="text-muted">Cant: <strong>${cant}</strong></span>
                        <span class="text-muted">P.U: <strong>${money(pUni)}</strong></span>
                    </div>
                </div>`;
        }).join('');
    }

    /**
     * Abre el panel lateral con el detalle del documento.
     * @param {number|string} id    id del documento
     * @param {string} tipo         ver TIPOS + 'SALDO_INICIAL'
     * @param {object} [extra]      datos de respaldo de la fila
     */
    window.CMG_abrirPreviewDoc = function (id, tipo, extra) {
        const el = $('offcanvasDocPreview');
        if (!el) return;

        // Sacarlo del contenedor actual para que no lo tape el apilamiento de modales
        if (el.parentNode !== document.body) document.body.appendChild(el);

        const oc = bootstrap.Offcanvas.getOrCreateInstance(el);
        oc.show();

        extra = extra || {};

        // Saldo inicial: no es un documento electrónico, no hay nada que consultar
        if (tipo === 'SALDO_INICIAL') {
            pintarCabecera({
                badge:       'SALDO INICIAL',
                numero:      extra.numero || '',
                fechaTxt:    fecha(extra.fecha),
                sujetoLabel: extra.sujetoLabel || '',
                sujeto:      extra.sujeto || ''
            });
            pintarItems(null, 'Saldo inicial de apertura: no tiene ítems de documento.');
            pintarTotales({ total: extra.total, soloTotal: true, labelTotal: 'Saldo inicial' });
            mostrar('preview-doc-content');
            return;
        }

        const cfg = TIPOS[tipo];
        if (!cfg) { error('Tipo de documento no soportado: ' + tipo); return; }

        mostrar('preview-doc-loading');

        fetch(`${cfg.url}?id=${encodeURIComponent(id)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
            .then(res => {
                if (!res || !res.ok) throw new Error((res && res.mensaje) || 'Documento no disponible');

                const cab  = (cfg.forma === 'data') ? res.data : res.cabecera;
                if (!cab) throw new Error('El documento no devolvió datos');
                let dets = (cfg.forma === 'data') ? (cab.detalles || []) : (res.detalles || []);

                const numero = [
                    cab.establecimiento || cab.establecimiento_prov || '',
                    cab.punto_emision   || cab.punto_emision_prov   || '',
                    cab.secuencial      || cab.secuencial_prov      || ''
                ].join('-');

                pintarCabecera({
                    badge:       cfg.badge,
                    numero:      numero !== '--' ? numero : (extra.numero || ''),
                    fechaTxt:    fecha(cab.fecha_emision || cab.fecha_nacionalizacion || cab.created_at),
                    sujetoLabel: cfg.sujeto,
                    sujeto:      cab.cliente_nombre || cab.proveedor_nombre || extra.sujeto || ''
                });

                let subtotal, total, iva;
                if (tipo === 'IMPORTACION') {
                    // Importaciones: FOB + gastos capitalizables = costo nacionalizado; el IVA es crédito tributario aparte.
                    subtotal = parseFloat(cab.subtotal_fob || 0);
                    iva      = parseFloat(cab.total_iva || 0);
                    total    = parseFloat(cab.costo_total_nacionalizado || 0) || (subtotal + parseFloat(cab.total_gastos_capitalizables || 0));
                    dets = dets.map(d => ({
                        ...d,
                        costo_unitario: d.costo_unitario_nacionalizado || d.precio_unitario_fob,
                        subtotal:       d.costo_total_nacionalizado    || d.precio_total_fob,
                        codigo:         d.codigo_producto_raw
                    }));
                } else {
                    subtotal = parseFloat(cab.total_sin_impuestos || 0);
                    total    = parseFloat(cab.importe_total || 0);
                    iva      = parseFloat(cab.monto_iva || 0) || (total - subtotal);
                }

                pintarItems(dets);
                pintarTotales({ subtotal, iva, total });
                mostrar('preview-doc-content');
            })
            .catch(e => {
                console.error('[preview doc]', e);
                error((e && e.message) || 'No se pudo cargar el detalle. Verifica que tengas permiso de lectura sobre el módulo del documento.');
            });
    };
})();
</script>
