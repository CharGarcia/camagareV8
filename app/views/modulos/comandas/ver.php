<?php
/**
 * Comanda de una mesa — Punto de Venta modo Restaurante. Página STANDALONE
 * (sin layout principal), mismo criterio que caja_sesion/venta.php: selector
 * de productos + carrito, pero el carrito vive en el servidor (comanda_detalle)
 * en vez de memoria del navegador, porque varias rondas se agregan a lo largo
 * del servicio y cualquier dispositivo debe poder verlas.
 *
 * Fase 1: solo agregar/anular ítems y anular la comanda completa. El cobro
 * (genera Factura/Recibo, con posible división de cuenta) llega en una fase
 * posterior.
 *
 * @var string $titulo
 * @var string $rutaModulo
 * @var array  $perm
 * @var array  $comanda
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
        .cm-wrap { display: flex; flex-direction: column; height: 100vh; }
        .cm-header { flex: 0 0 auto; }
        .cm-body { flex: 1 1 auto; min-height: 0; display: flex; }

        .cm-catalogo { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; }
        .cm-search { flex: 0 0 auto; padding: 8px 16px 6px; background: #fff; border-bottom: 1px solid #dee2e6; }
        .cm-grid { flex: 1 1 auto; overflow-y: auto; padding: 14px; display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; align-content: start; }
        .cm-tile { background: #fff; border: 1px solid #dee2e6; border-radius: 10px; padding: 12px 10px; cursor: pointer; text-align: left; }
        .cm-tile:hover { border-color: #0d6efd; }
        .cm-tile .nombre { font-size: .82rem; font-weight: 600; line-height: 1.25; margin-bottom: 6px; min-height: 2.1em; }
        .cm-tile .precio { font-size: .82rem; color: #0d6efd; font-weight: 700; }
        .cm-tile .dest { font-size: .64rem; color: #8a94a6; }
        .cm-empty { color: #8a94a6; }

        .cm-comanda { width: 360px; max-width: 42%; background: #fff; border-left: 1px solid #dee2e6; display: flex; flex-direction: column; }
        .cm-lineas { flex: 1 1 auto; overflow-y: auto; padding: 10px 14px; }
        .cm-linea { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .cm-linea.anulado { opacity: .45; text-decoration: line-through; }
        .cm-linea .desc { flex: 1 1 auto; min-width: 0; }
        .cm-linea .desc .n { font-size: .82rem; font-weight: 600; }
        .cm-linea .desc .p { font-size: .72rem; color: #8a94a6; }
        .cm-linea .desc .estado { font-size: .64rem; margin-top: 2px; }
        .cm-linea .total { font-size: .82rem; font-weight: 600; min-width: 56px; text-align: right; }
        .cm-linea .rm { color: #dc3545; cursor: pointer; }
        .cm-linea .entregar { font-size: .68rem; padding: 2px 8px; }
        .cm-totales { flex: 0 0 auto; padding: 12px 16px; border-top: 1px dashed #dee2e6; }
        .cm-totales .row div { display: flex; justify-content: space-between; padding: 2px 0; font-size: 1.1rem; font-weight: 700; }
        .cm-footer { flex: 0 0 auto; padding: 0 16px 16px; }
    </style>
</head>
<body>
<div class="cm-wrap">
    <div class="cm-header d-flex align-items-center justify-content-between gap-2 px-3 py-2 bg-primary text-white shadow-sm">
        <div class="d-flex align-items-center gap-2">
            <a href="<?= $base ?>/modulos/mesas/tablero" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left"></i></a>
            <div>
                <div class="fw-semibold lh-1">Mesa <?= htmlspecialchars($comanda['mesa_nombre'] ?? '') ?></div>
                <small class="text-white-50"><?= htmlspecialchars($comanda['numero_comanda'] ?? '') ?> &middot; <?= htmlspecialchars($comanda['mesero_nombre'] ?? '') ?></small>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if (!empty($perm['crear']) && ($comanda['estado'] ?? '') === 'abierta'): ?>
                <button type="button" class="btn btn-sm btn-warning" id="cm-btn-enviar-cocina"><i class="bi bi-send me-1"></i>Enviar a cocina/barra</button>
            <?php endif; ?>
            <?php if (!empty($perm['eliminar']) && ($comanda['estado'] ?? '') === 'abierta'): ?>
                <button type="button" class="btn btn-sm btn-outline-light" id="cm-btn-anular"><i class="bi bi-x-circle me-1"></i>Anular comanda</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="cm-body">
        <div class="cm-catalogo">
            <div class="cm-search">
                <input type="text" class="form-control form-control-sm" id="cm-buscar" placeholder="Buscar producto...">
            </div>
            <div class="cm-grid" id="cm-grid"><div class="cm-empty p-3">Escribe para buscar productos.</div></div>
        </div>
        <div class="cm-comanda">
            <div class="cm-lineas" id="cm-lineas"></div>
            <div class="cm-totales">
                <div class="row"><div><span>Total</span><span id="cm-total">$0.00</span></div></div>
            </div>
            <div class="cm-footer">
                <div class="text-muted small text-center">El cobro se habilita en una fase posterior del módulo.</div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    const BASE = "<?= $base ?>";
    const AJAX = BASE + '/modulos/comandas';
    const ID_COMANDA = <?= (int) $comanda['id'] ?>;
    const PUEDE_CREAR = <?= !empty($perm['crear']) ? 'true' : 'false' ?>;
    const $grid = document.getElementById('cm-grid');
    const $buscar = document.getElementById('cm-buscar');
    const $lineas = document.getElementById('cm-lineas');
    const $total = document.getElementById('cm-total');
    let detalles = <?= json_encode($comanda['detalles'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    let buscarTimer = null;

    function money(v) { return '$' + (parseFloat(v || 0)).toFixed(2); }
    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function swalError(html) {
        Swal.fire({ icon: 'error', title: 'Error', html, confirmButtonColor: '#0d6efd', confirmButtonText: 'Aceptar' });
    }
    function swalToast(icon, title) {
        Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2200, timerProgressBar: true });
    }

    const ESTADO_LABEL = {
        pendiente: ['Sin enviar', 'secondary'], enviado: ['En cocina/barra', 'info'],
        preparando: ['Preparando', 'warning'], listo: ['Listo para servir', 'success'],
        entregado: ['Entregado', 'secondary'],
    };

    function renderLineas() {
        const vivas = detalles.filter(d => d.estado_linea !== 'anulado');
        if (!vivas.length && !detalles.length) {
            $lineas.innerHTML = '<div class="cm-empty p-3 text-center">Aún no hay ítems.</div>';
        } else {
            $lineas.innerHTML = detalles.map(d => {
                const [label, color] = ESTADO_LABEL[d.estado_linea] || ['—', 'secondary'];
                return `
                <div class="cm-linea ${d.estado_linea === 'anulado' ? 'anulado' : ''}">
                    <div class="desc">
                        <div class="n">${escapeHtml(d.cantidad)} x ${escapeHtml(d.descripcion)}</div>
                        ${d.observacion_item ? '<div class="p">' + escapeHtml(d.observacion_item) + '</div>' : ''}
                        ${d.estado_linea !== 'anulado' ? '<div class="estado"><span class="badge bg-' + color + '-subtle text-' + color + '-emphasis">' + label + '</span></div>' : ''}
                        ${d.estado_linea === 'listo' && PUEDE_CREAR ? '<button type="button" class="btn btn-sm btn-success entregar mt-1" data-id="' + d.id + '"><i class="bi bi-check2-circle me-1"></i>Entregar</button>' : ''}
                    </div>
                    <div class="total">${money(d.subtotal)}</div>
                    ${d.estado_linea === 'pendiente' && PUEDE_CREAR ? '<span class="rm" data-id="' + d.id + '" title="Quitar"><i class="bi bi-x-lg"></i></span>' : ''}
                </div>`;
            }).join('');
        }
        const total = vivas.reduce((a, d) => a + parseFloat(d.subtotal || 0), 0);
        $total.textContent = money(total);
    }

    async function refrescarComanda() {
        try {
            const r = await fetch(AJAX + '/verAjax'); // sin id: el servidor lee la comanda "actual" de sesión
            const d = await r.json();
            if (d.ok) { detalles = d.data.detalles || []; renderLineas(); }
        } catch (e) { /* silencioso */ }
    }

    $lineas.addEventListener('click', async (ev) => {
        const rm = ev.target.closest('.rm');
        const entregar = ev.target.closest('.entregar');
        if (rm) {
            const fd = new FormData();
            fd.append('id_linea', rm.dataset.id);
            fd.append('id_comanda', ID_COMANDA);
            try {
                const r = await fetch(AJAX + '/anularLineaAjax', { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.ok) { swalError(d.error || 'No se pudo quitar el ítem.'); return; }
                await refrescarComanda();
            } catch (e) { swalError('Error de conexión.'); }
            return;
        }
        if (entregar) {
            const fd = new FormData();
            fd.append('id_linea', entregar.dataset.id);
            entregar.disabled = true;
            try {
                const r = await fetch(AJAX + '/marcarEntregadoAjax', { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.ok) { swalError(d.error || 'No se pudo marcar como entregado.'); entregar.disabled = false; return; }
                await refrescarComanda();
            } catch (e) { swalError('Error de conexión.'); entregar.disabled = false; }
        }
    });

    const $btnEnviarCocina = document.getElementById('cm-btn-enviar-cocina');
    if ($btnEnviarCocina) {
        $btnEnviarCocina.addEventListener('click', async () => {
            const fd = new FormData();
            fd.append('id_comanda', ID_COMANDA);
            $btnEnviarCocina.disabled = true;
            try {
                const r = await fetch(AJAX + '/enviarCocinaAjax', { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.ok) { swalError(d.error || 'No se pudo enviar a cocina/barra.'); return; }
                swalToast('success', d.msg || 'Enviado');
                await refrescarComanda();
            } catch (e) { swalError('Error de conexión.'); }
            finally { $btnEnviarCocina.disabled = false; }
        });
    }

    function renderGrid(rows) {
        if (!rows.length) {
            $grid.innerHTML = '<div class="cm-empty p-3">Sin resultados.</div>';
            return;
        }
        $grid.innerHTML = rows.map(p => `
            <button type="button" class="cm-tile" data-id="${p.id}" data-nombre="${escapeHtml(p.nombre)}" data-precio="${p.precio_base || 0}">
                <div class="nombre">${escapeHtml(p.nombre)}</div>
                <div class="precio">${money(p.precio_base)}</div>
                <div class="dest">${escapeHtml(p.codigo || '')}</div>
            </button>`).join('');
    }

    async function buscarProductos(q) {
        if (!q || q.length < 2) {
            $grid.innerHTML = '<div class="cm-empty p-3">Escribe para buscar productos.</div>';
            return;
        }
        try {
            const r = await fetch(AJAX + '/getProductosAjax?q=' + encodeURIComponent(q));
            const d = await r.json();
            renderGrid(d.ok ? d.data : []);
        } catch (e) { $grid.innerHTML = '<div class="cm-empty p-3">Error al buscar.</div>'; }
    }

    $buscar.addEventListener('input', () => {
        clearTimeout(buscarTimer);
        buscarTimer = setTimeout(() => buscarProductos($buscar.value.trim()), 300);
    });

    $grid.addEventListener('click', async (ev) => {
        const tile = ev.target.closest('.cm-tile');
        if (!tile) return;
        if (!PUEDE_CREAR) { swalError('No tienes permiso para agregar ítems.'); return; }

        const fd = new FormData();
        fd.append('id_comanda', ID_COMANDA);
        fd.append('id_producto', tile.dataset.id);
        fd.append('descripcion', tile.dataset.nombre);
        fd.append('cantidad', '1');
        fd.append('precio_unitario', tile.dataset.precio);
        try {
            const r = await fetch(AJAX + '/agregarLineaAjax', { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo agregar el ítem.'); return; }
            swalToast('success', 'Agregado');
            await refrescarComanda();
        } catch (e) { swalError('Error de conexión.'); }
    });

    const $btnAnular = document.getElementById('cm-btn-anular');
    if ($btnAnular) {
        $btnAnular.addEventListener('click', async () => {
            const { isConfirmed } = await Swal.fire({
                title: '¿Anular esta comanda?',
                text: 'La mesa quedará disponible y se perderán los ítems agregados.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Sí, anular', cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
            });
            if (!isConfirmed) return;
            const fd = new FormData();
            fd.append('id', ID_COMANDA);
            try {
                const r = await fetch(AJAX + '/anularAjax', { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.ok) { swalError(d.error || 'No se pudo anular.'); return; }
                window.location.href = BASE + '/modulos/mesas/tablero';
            } catch (e) { swalError('Error de conexión.'); }
        });
    }

    renderLineas();
    setInterval(refrescarComanda, 8000);
})();
</script>
</body>
</html>
