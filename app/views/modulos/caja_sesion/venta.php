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
        .pv-tile { background: #fff; border: 1px solid #dee2e6; border-radius: 10px; padding: 12px 10px; cursor: pointer; text-align: left; transition: border-color .15s; }
        .pv-tile:hover { border-color: #0d6efd; }
        .pv-tile .nombre { font-size: .82rem; font-weight: 600; line-height: 1.25; margin-bottom: 6px; min-height: 2.1em; }
        .pv-tile .precio { font-size: .82rem; color: #0d6efd; font-weight: 700; }
        .pv-tile .codigo { font-size: .68rem; color: #8a94a6; }
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
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="pv-buscar" class="form-control" placeholder="Buscar producto por nombre o código...">
                </div>
            </div>
            <div class="pv-grid" id="pv-grid">
                <div class="text-center py-4 pv-empty" style="grid-column: 1 / -1;">
                    <span class="spinner-border spinner-border-sm"></span> Cargando productos...
                </div>
            </div>
        </div>

        <div class="pv-carrito">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const AJAX = "<?= $rutaAjax ?>";
    const ID_PUNTO = <?= (int) $idPuntoEmision ?>;
    const $grid = document.getElementById('pv-grid');
    const $buscar = document.getElementById('pv-buscar');
    const $lineas = document.getElementById('pv-lineas');
    const $btnCobrar = document.getElementById('pv-btn-cobrar');
    let cart = [];
    let formaPago = '01';
    let buscarTimer = null;

    function money(v) { return '$' + (parseFloat(v || 0)).toFixed(2); }

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
            tile.innerHTML = '<div class="nombre">' + escapeHtml(p.nombre || '') + '</div>' +
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

    function addToCart(p) {
        const idProducto = parseInt(p.id, 10);
        const existente = cart.find(l => l.id_producto === idProducto);
        if (existente) {
            existente.cantidad += 1;
        } else {
            cart.push({
                id_producto: idProducto,
                descripcion: p.nombre,
                precio_unitario: parseFloat(p.precio_base || 0),
                pct_iva: parseFloat(p.porcentaje_iva_final || 0),
                cantidad: 1,
            });
        }
        renderCart();
    }

    function cambiarCantidad(idProducto, delta) {
        const linea = cart.find(l => l.id_producto === idProducto);
        if (!linea) return;
        linea.cantidad += delta;
        if (linea.cantidad <= 0) {
            cart = cart.filter(l => l.id_producto !== idProducto);
        }
        renderCart();
    }

    function quitarLinea(idProducto) {
        cart = cart.filter(l => l.id_producto !== idProducto);
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
                row.innerHTML =
                    '<div class="desc"><div class="n">' + escapeHtml(l.descripcion) + '</div><div class="p">' + money(l.precio_unitario) + ' c/u</div></div>' +
                    '<div class="pv-qty">' +
                        '<button type="button" class="btn btn-outline-secondary btn-sm" data-act="menos">-</button>' +
                        '<span>' + l.cantidad + '</span>' +
                        '<button type="button" class="btn btn-outline-secondary btn-sm" data-act="mas">+</button>' +
                    '</div>' +
                    '<div class="total">' + money(base) + '</div>' +
                    '<i class="bi bi-x-lg rm" data-act="rm"></i>';
                row.querySelector('[data-act="menos"]').addEventListener('click', () => cambiarCantidad(l.id_producto, -1));
                row.querySelector('[data-act="mas"]').addEventListener('click', () => cambiarCantidad(l.id_producto, 1));
                row.querySelector('[data-act="rm"]').addEventListener('click', () => quitarLinea(l.id_producto));
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
        }))));

        try {
            const res = await fetch(AJAX + '/cobrarAjax', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (!json.ok) {
                alert(json.error || 'No se pudo registrar la venta.');
                renderCart();
                return;
            }
            alert('Venta registrada: ' + json.data.numero_documento + ' — ' + money(json.data.importe_total));
            cart = [];
            renderCart();
        } catch (e) {
            alert('Error de conexión al cobrar.');
            renderCart();
        }
    });

    $buscar.addEventListener('input', () => {
        clearTimeout(buscarTimer);
        buscarTimer = setTimeout(() => buscarProductos($buscar.value.trim()), 350);
    });

    buscarProductos('');
    renderCart();
})();
</script>
</body>
</html>
