<?php
/**
 * Punto de Venta — Diseño A (Grid Retail). Página STANDALONE (sin layout
 * principal). Cobra reutilizando ReciboVentaService a través de
 * PosVentaService::cobrar() — sin SRI/XML, es un recibo interno.
 *
 * @var string $titulo
 * @var string $rutaModulo
 * @var int    $idPuntoEmision
 * @var array  $sesion
 * @var bool   $obligatorioLotes
 * @var bool   $obligatorioCaducidad
 * @var bool   $obligatorioNup
 */
$base = rtrim(BASE_URL ?? '', '/');
$rutaAjax = $base . '/' . $rutaModulo;
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | CaMaGaRe</title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body { height: 100%; }
        body { background: #f4f6f9; overflow: hidden; }
        .pv-wrap { display: flex; flex-direction: column; height: 100vh; }
        .pv-header { flex: 0 0 auto; }
        .pv-body { flex: 1 1 auto; min-height: 0; display: flex; }

        .pv-catalogo { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; }
        .pv-search { flex: 0 0 auto; padding: 12px 16px; background: #fff; border-bottom: 1px solid #dee2e6; }
        .pv-grid { flex: 1 1 auto; overflow-y: auto; padding: 14px; display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; align-content: start; }
        .pv-tile { position: relative; background: #fff; border: 1px solid #dee2e6; border-radius: 10px; padding: 12px 10px; cursor: pointer; text-align: left; transition: border-color .15s; }
        .pv-tile:hover { border-color: #0d6efd; }
        .pv-tile .thumb { width: 100%; height: 56px; object-fit: cover; border-radius: 6px; margin-bottom: 8px; background: #f4f6f9; display: block; }
        .pv-tile .nombre { font-size: .82rem; font-weight: 600; line-height: 1.25; margin-bottom: 6px; min-height: 2.1em; }
        .pv-tile .precio { font-size: .82rem; color: #0d6efd; font-weight: 700; }
        .pv-tile .codigo { font-size: .68rem; color: #8a94a6; }
        .pv-tile .sin-stock {
            position: absolute; top: 6px; right: 6px; width: 22px; height: 22px; border-radius: 50%;
            background: #dc3545; color: #fff; display: flex; align-items: center; justify-content: center; font-size: .7rem;
        }
        .pv-empty { color: #8a94a6; }

        .pv-carrito { width: 340px; max-width: 40%; background: #fff; border-left: 1px solid #dee2e6; display: flex; flex-direction: column; }
        .pv-lineas { flex: 1 1 auto; overflow-y: auto; padding: 10px 14px; }
        .pv-linea { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .pv-linea .desc { flex: 1 1 auto; min-width: 0; }
        .pv-linea .desc .n { font-size: .82rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pv-linea .desc .p { font-size: .72rem; color: #8a94a6; }
        .pv-qty { display: flex; align-items: center; gap: 4px; }
        .pv-qty button { width: 22px; height: 22px; line-height: 1; padding: 0; }
        .pv-qty span { min-width: 20px; text-align: center; font-size: .8rem; }
        .pv-linea .total { font-size: .82rem; font-weight: 600; min-width: 56px; text-align: right; }
        .pv-linea .rm { color: #dc3545; cursor: pointer; }

        .pv-totales { flex: 0 0 auto; padding: 12px 16px; border-top: 1px dashed #dee2e6; font-size: .85rem; }
        .pv-totales .row div { display: flex; justify-content: space-between; padding: 2px 0; }
        .pv-totales .row.total div { font-size: 1.15rem; font-weight: 700; border-top: 1px solid #dee2e6; margin-top: 6px; padding-top: 8px; }

        .pv-pagos { padding: 0 16px 10px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
        .pv-pagos button.active { background: #0d6efd; color: #fff; border-color: #0d6efd; }

        .pv-cobrar { padding: 0 16px 16px; }
    </style>
</head>
<body>
<div class="pv-wrap">
    <div class="pv-header d-flex align-items-center justify-content-between gap-2 px-3 py-2 bg-primary text-white shadow-sm">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-cash-coin fs-5"></i>
            <div>
                <div class="fw-semibold lh-1">Punto de Venta</div>
                <small class="text-white-50">
                    Cajero: <?= htmlspecialchars($sesion['cajero_nombre'] ?? '—') ?> ·
                    Fondo: $<?= number_format((float) $sesion['fondo_inicial'], 2) ?>
                </small>
            </div>
        </div>
        <a href="<?= $rutaAjax ?>" class="btn btn-light btn-sm">
            <i class="bi bi-lock-fill me-1"></i>Cerrar caja
        </a>
    </div>

    <div class="pv-body">
        <div class="pv-catalogo">
            <div class="pv-search">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                    <input type="text" id="pv-buscar" class="form-control" placeholder="Buscar producto o escanear código de barras..." autofocus autocomplete="off">
                </div>
            </div>
            <div class="pv-grid" id="pv-grid">
                <div class="text-center py-4 pv-empty" style="grid-column: 1 / -1;">
                    <span class="spinner-border spinner-border-sm"></span> Cargando productos...
                </div>
            </div>
        </div>

        <div class="pv-carrito">
            <?php if ($obligatorioLotes): ?>
            <div class="small text-muted px-3 pt-2">
                <i class="bi bi-boxes"></i> Esta empresa exige lote en productos inventariados.
            </div>
            <?php endif; ?>
            <div class="pv-lineas" id="pv-lineas">
                <div class="text-center py-4 pv-empty small">El carrito está vacío.<br>Toca un producto para agregarlo.</div>
            </div>
            <div class="pv-totales">
                <div class="row">
                    <div><span>Subtotal</span><span id="pv-subtotal">$0.00</span></div>
                    <div><span>IVA</span><span id="pv-iva">$0.00</span></div>
                </div>
                <div class="row total">
                    <div><span>Total</span><span id="pv-total">$0.00</span></div>
                </div>
            </div>
            <div class="pv-pagos">
                <button type="button" class="btn btn-outline-secondary btn-sm active" data-forma="01">Efectivo</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-forma="19">Tarjeta</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-forma="20">Transf.</button>
            </div>
            <div class="pv-cobrar">
                <button id="pv-btn-cobrar" class="btn btn-success w-100" type="button" disabled>
                    <i class="bi bi-check-circle-fill me-1"></i>Cobrar $0.00
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalLote" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-boxes me-1"></i>Selecciona el lote</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="modalLoteProducto"></p>
                <select id="modalLoteSelect" class="form-select"></select>
                <div id="modalLoteCaducidadWrap" class="d-none">
                    <label class="form-label small fw-semibold text-uppercase text-muted mt-3 mb-1">Fecha de caducidad</label>
                    <input type="date" id="modalLoteCaducidad" class="form-control form-control-sm">
                    <div class="form-text">Este lote no trae caducidad registrada; la empresa la exige.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="modalLoteConfirmar">Agregar al carrito</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNup" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-upc me-1"></i>Número de serie</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="modalNupProducto"></p>
                <input type="text" id="modalNupInput" class="form-control" placeholder="Escanea o escribe el número de serie" autocomplete="off">
                <div class="form-text">Este producto exige un número de serie por unidad — cada unidad queda como una línea propia.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="modalNupConfirmar">Agregar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    const AJAX = "<?= $rutaAjax ?>";
    const BASE = "<?= $base ?>";
    const ID_PUNTO = <?= (int) $idPuntoEmision ?>;
    const OBLIGATORIO_LOTES = <?= $obligatorioLotes ? 'true' : 'false' ?>;
    const OBLIGATORIO_CADUCIDAD = <?= $obligatorioCaducidad ? 'true' : 'false' ?>;
    const OBLIGATORIO_NUP = <?= $obligatorioNup ? 'true' : 'false' ?>;
    const modalLoteEl = document.getElementById('modalLote');
    const modalLote = new bootstrap.Modal(modalLoteEl);
    const modalNupEl = document.getElementById('modalNup');
    const modalNup = new bootstrap.Modal(modalNupEl);
    const $grid = document.getElementById('pv-grid');
    const $buscar = document.getElementById('pv-buscar');
    const $lineas = document.getElementById('pv-lineas');
    const $btnCobrar = document.getElementById('pv-btn-cobrar');
    let cart = [];
    let formaPago = '01';
    let buscarTimer = null;
    let lineSeq = 0;

    function money(v) { return '$' + (parseFloat(v || 0)).toFixed(2); }

    function swalToast(icon, title) {
        Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2800, timerProgressBar: true });
    }
    function swalError(html) {
        Swal.fire({ icon: 'error', title: 'Error', html, confirmButtonColor: '#0d6efd', confirmButtonText: 'Aceptar' });
    }
    function swalWarning(html) {
        Swal.fire({ icon: 'warning', title: 'Atención', html, confirmButtonColor: '#0d6efd', confirmButtonText: 'Aceptar' });
    }

    async function buscarProductos(q) {
        $grid.innerHTML = '<div class="text-center py-4 pv-empty" style="grid-column: 1 / -1;"><span class="spinner-border spinner-border-sm"></span> Buscando...</div>';
        try {
            const res = await fetch(AJAX + '/getProductosAjax?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            renderGrid(json.data || []);
        } catch (e) {
            $grid.innerHTML = '<div class="text-center py-4 text-danger" style="grid-column: 1 / -1;">Error al cargar productos.</div>';
        }
    }

    function renderGrid(rows) {
        if (!rows.length) {
            $grid.innerHTML = '<div class="text-center py-4 pv-empty" style="grid-column: 1 / -1;"><i class="bi bi-box-seam fs-3 d-block mb-2"></i>Sin resultados.</div>';
            return;
        }
        $grid.innerHTML = '';
        rows.forEach(p => {
            const tile = document.createElement('button');
            tile.type = 'button';
            tile.className = 'pv-tile';
            const thumbHtml = p.imagen
                ? '<img class="thumb" src="' + BASE + '/' + escapeHtml(p.imagen) + '" alt="" loading="lazy">'
                : '';
            const sinStock = (p.stock_pos !== undefined && parseFloat(p.stock_pos) <= 0)
                ? '<span class="sin-stock" title="Sin stock"><i class="bi bi-exclamation-triangle-fill"></i></span>'
                : '';
            tile.innerHTML = sinStock + thumbHtml +
                '<div class="nombre">' + escapeHtml(p.nombre || '') + '</div>' +
                '<div class="precio">' + money(p.precio_base) + '</div>' +
                '<div class="codigo">' + escapeHtml(p.codigo || '') + '</div>';
            tile.addEventListener('click', () => addToCart(p));
            $grid.appendChild(tile);
        });
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function esInventariableControlado(p) {
        const inv = p.inventariable === true || p.inventariable === 't' || p.inventariable === 'true' || p.inventariable == 1;
        return inv && p.tipo_produccion !== '02';
    }
    function requiereLote(p) { return OBLIGATORIO_LOTES && esInventariableControlado(p); }
    function requiereNup(p) { return OBLIGATORIO_NUP && esInventariableControlado(p); }

    async function addToCart(p) {
        const idProducto = parseInt(p.id, 10);
        const necesitaNup = requiereNup(p);

        // Con NÚP obligatorio cada unidad es su propia línea (un número de
        // serie por línea) — nunca se fusiona con una existente.
        if (!necesitaNup) {
            const existente = cart.find(l => l.id_producto === idProducto);
            if (existente) {
                existente.cantidad += 1;
                renderCart();
                $buscar.focus();
                return;
            }
        }

        let lote = '', caducidad = '';
        if (requiereLote(p)) {
            const elegido = await seleccionarLote(p);
            if (!elegido) { $buscar.focus(); return; } // cancelado o sin stock
            lote = elegido.lote;
            caducidad = elegido.caducidad;
        }

        let nup = '';
        if (necesitaNup) {
            const val = await capturarNup(p);
            if (val === null) { $buscar.focus(); return; } // cancelado
            nup = val;
        }

        cart.push({
            uid: ++lineSeq,
            id_producto: idProducto,
            descripcion: p.nombre,
            precio_unitario: parseFloat(p.precio_base || 0),
            pct_iva: parseFloat(p.porcentaje_iva_final || 0),
            cantidad: 1,
            lote,
            caducidad,
            nup,
        });
        renderCart();
        $buscar.focus();
    }

    function seleccionarLote(p) {
        return fetch(AJAX + '/getLotesAjax?id_producto=' + p.id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.json())
            .then(json => {
                const lotes = (json.data || []).filter(l => parseFloat(l.stock_lote || 0) > 0);
                if (!lotes.length) {
                    swalWarning('No hay stock con lote disponible para "' + escapeHtml(p.nombre) + '".');
                    return null;
                }
                const faltaCaducidad = OBLIGATORIO_CADUCIDAD && !lotes[0].fecha_caducidad;
                if (lotes.length === 1 && !faltaCaducidad) {
                    const l = lotes[0];
                    return { lote: l.numero_lote === 'sin_lote' ? '' : l.numero_lote, caducidad: l.fecha_caducidad || '' };
                }
                return abrirModalLote(p, lotes);
            })
            .catch(() => {
                swalError('Error de conexión al consultar los lotes de "' + escapeHtml(p.nombre) + '".');
                return null;
            });
    }

    function abrirModalLote(p, lotes) {
        return new Promise(resolve => {
            document.getElementById('modalLoteProducto').textContent = p.nombre;
            const $sel = document.getElementById('modalLoteSelect');
            const $cadWrap = document.getElementById('modalLoteCaducidadWrap');
            const $cad = document.getElementById('modalLoteCaducidad');
            $sel.innerHTML = '';
            lotes.forEach(l => {
                const opt = document.createElement('option');
                opt.value = l.numero_lote;
                const cadTxt = l.fecha_caducidad ? (' · vence ' + l.fecha_caducidad) : '';
                opt.textContent = (l.numero_lote === 'sin_lote' ? 'Sin lote' : l.numero_lote) + ' — stock ' + l.stock_lote + cadTxt;
                $sel.appendChild(opt);
            });

            const sincronizarCaducidad = () => {
                const l = lotes.find(x => x.numero_lote === $sel.value);
                $cad.value = l?.fecha_caducidad || '';
            };
            if (OBLIGATORIO_CADUCIDAD) {
                $cadWrap.classList.remove('d-none');
                sincronizarCaducidad();
            } else {
                $cadWrap.classList.add('d-none');
                $cad.value = '';
            }
            $sel.addEventListener('change', sincronizarCaducidad);

            let resuelto = false;
            const $btnConfirmar = document.getElementById('modalLoteConfirmar');
            const onConfirmar = () => {
                if (OBLIGATORIO_CADUCIDAD && !$cad.value) {
                    $cad.focus();
                    return;
                }
                resuelto = true;
                const val = $sel.value;
                modalLote.hide();
                resolve({ lote: val === 'sin_lote' ? '' : val, caducidad: $cad.value || '' });
            };
            const onHidden = () => {
                modalLoteEl.removeEventListener('hidden.bs.modal', onHidden);
                $btnConfirmar.removeEventListener('click', onConfirmar);
                $sel.removeEventListener('change', sincronizarCaducidad);
                if (!resuelto) resolve(null);
            };

            $btnConfirmar.addEventListener('click', onConfirmar);
            modalLoteEl.addEventListener('hidden.bs.modal', onHidden);
            modalLote.show();
        });
    }

    function capturarNup(p) {
        return new Promise(resolve => {
            document.getElementById('modalNupProducto').textContent = p.nombre;
            const $input = document.getElementById('modalNupInput');
            $input.value = '';

            let resuelto = false;
            const $btnConfirmar = document.getElementById('modalNupConfirmar');
            const confirmar = () => {
                const val = $input.value.trim();
                if (!val) { $input.focus(); return; }
                resuelto = true;
                modalNup.hide();
                resolve(val);
            };
            const onEnter = (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); confirmar(); } };
            const onHidden = () => {
                modalNupEl.removeEventListener('hidden.bs.modal', onHidden);
                $btnConfirmar.removeEventListener('click', confirmar);
                $input.removeEventListener('keydown', onEnter);
                if (!resuelto) resolve(null);
            };

            $btnConfirmar.addEventListener('click', confirmar);
            $input.addEventListener('keydown', onEnter);
            modalNupEl.addEventListener('hidden.bs.modal', onHidden);
            modalNup.show();
            setTimeout(() => $input.focus(), 300);
        });
    }

    function cambiarCantidad(uid, delta) {
        const linea = cart.find(l => l.uid === uid);
        if (!linea) return;
        linea.cantidad += delta;
        if (linea.cantidad <= 0) {
            cart = cart.filter(l => l.uid !== uid);
        }
        renderCart();
    }

    function quitarLinea(uid) {
        cart = cart.filter(l => l.uid !== uid);
        renderCart();
    }

    function renderCart() {
        if (!cart.length) {
            $lineas.innerHTML = '<div class="text-center py-4 pv-empty small">El carrito está vacío.<br>Toca un producto para agregarlo.</div>';
        } else {
            $lineas.innerHTML = '';
            cart.forEach(l => {
                const base = l.precio_unitario * l.cantidad;
                const row = document.createElement('div');
                row.className = 'pv-linea';
                const loteTag = l.lote ? ' <span class="badge bg-secondary bg-opacity-25 text-secondary">Lote ' + escapeHtml(l.lote) + '</span>' : '';
                const nupTag = l.nup ? ' <span class="badge bg-info bg-opacity-25 text-info">S/N ' + escapeHtml(l.nup) + '</span>' : '';
                const qtyHtml = l.nup
                    ? '<span class="small text-muted">1 unidad</span>'
                    : '<div class="pv-qty">' +
                        '<button type="button" class="btn btn-outline-secondary btn-sm" data-act="menos">-</button>' +
                        '<span>' + l.cantidad + '</span>' +
                        '<button type="button" class="btn btn-outline-secondary btn-sm" data-act="mas">+</button>' +
                      '</div>';
                row.innerHTML =
                    '<div class="desc"><div class="n">' + escapeHtml(l.descripcion) + loteTag + nupTag + '</div><div class="p">' + money(l.precio_unitario) + ' c/u</div></div>' +
                    qtyHtml +
                    '<div class="total">' + money(base) + '</div>' +
                    '<i class="bi bi-x-lg rm" data-act="rm"></i>';
                row.querySelector('[data-act="menos"]')?.addEventListener('click', () => cambiarCantidad(l.uid, -1));
                row.querySelector('[data-act="mas"]')?.addEventListener('click', () => cambiarCantidad(l.uid, 1));
                row.querySelector('[data-act="rm"]').addEventListener('click', () => quitarLinea(l.uid));
                $lineas.appendChild(row);
            });
        }

        let subtotal = 0, iva = 0;
        cart.forEach(l => {
            const base = l.precio_unitario * l.cantidad;
            subtotal += base;
            iva += base * l.pct_iva / 100;
        });
        const total = subtotal + iva;
        document.getElementById('pv-subtotal').textContent = money(subtotal);
        document.getElementById('pv-iva').textContent = money(iva);
        document.getElementById('pv-total').textContent = money(total);
        $btnCobrar.textContent = '';
        $btnCobrar.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Cobrar ' + money(total);
        $btnCobrar.disabled = cart.length === 0;
    }

    document.querySelectorAll('.pv-pagos button').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.pv-pagos button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            formaPago = btn.dataset.forma;
        });
    });

    $btnCobrar.addEventListener('click', async () => {
        if (!cart.length) return;
        $btnCobrar.disabled = true;
        $btnCobrar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Procesando...';

        const fd = new FormData();
        fd.append('id_punto_emision', ID_PUNTO);
        fd.append('forma_pago', formaPago);
        fd.append('items', JSON.stringify(cart.map(l => ({
            id_producto: l.id_producto,
            descripcion: l.descripcion,
            cantidad: l.cantidad,
            precio_unitario: l.precio_unitario,
            lote: l.lote || '',
            caducidad: l.caducidad || '',
            nup: l.nup || '',
        }))));

        try {
            const res = await fetch(AJAX + '/cobrarAjax', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (!json.ok) {
                swalError(json.error || 'No se pudo registrar la venta.');
                renderCart();
                return;
            }
            Swal.fire({
                icon: 'success',
                title: 'Venta registrada',
                html: 'Recibo <b>' + escapeHtml(json.data.numero_documento) + '</b> por <b>' + money(json.data.importe_total) + '</b>.' +
                      '<br><br><span class="text-muted small">Ya se descontó el inventario y se generó el asiento contable. Queda guardada como recibo interno — puedes verla, imprimirla o enviarla desde el módulo <b>Recibos de Venta</b>.</span>',
                confirmButtonColor: '#198754',
                confirmButtonText: 'Nueva venta'
            });
            cart = [];
            renderCart();
        } catch (e) {
            swalError('Error de conexión al cobrar.');
            renderCart();
        }
    });

    $buscar.addEventListener('input', () => {
        clearTimeout(buscarTimer);
        buscarTimer = setTimeout(() => buscarProductos($buscar.value.trim()), 350);
    });

    // Los lectores de código de barras "escriben" el código y rematan con Enter.
    $buscar.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter') {
            ev.preventDefault();
            escanearCodigo($buscar.value.trim());
            return;
        }
        // Backspace/Delete limpian el campo de una vez, en vez de borrar
        // carácter por carácter — útil para corregir un escaneo fallido rápido.
        if (ev.key === 'Backspace' || ev.key === 'Delete') {
            ev.preventDefault();
            $buscar.value = '';
            buscarProductos('');
        }
    });

    async function escanearCodigo(valor) {
        if (!valor) return;
        clearTimeout(buscarTimer);

        try {
            const res = await fetch(AJAX + '/getProductosAjax?q=' + encodeURIComponent(valor), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            const rows = json.data || [];

            const valorNorm = valor.toLowerCase();
            let match = rows.find(p =>
                (p.codigo_barras || '').toLowerCase() === valorNorm ||
                (p.codigo || '').toLowerCase() === valorNorm ||
                (p.codigo_auxiliar || '').toLowerCase() === valorNorm
            );
            if (!match && rows.length === 1) {
                match = rows[0];
            }

            if (match) {
                addToCart(match);
                $buscar.value = '';
                renderGrid(rows.filter(p => p.id !== match.id));
            } else if (rows.length > 1) {
                renderGrid(rows);
                swalToast('warning', 'Varios productos coinciden con "' + valor + '" — elige uno de la lista.');
            } else {
                swalToast('warning', 'No se encontró ningún producto con el código "' + valor + '".');
            }
        } catch (e) {
            swalToast('error', 'Error de conexión al buscar el código.');
        } finally {
            $buscar.focus();
        }
    }

    buscarProductos('');
    renderCart();
    $buscar.focus();
})();
</script>
</body>
</html>
