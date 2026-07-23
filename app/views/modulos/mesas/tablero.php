<?php
/**
 * Tablero de mesas — Punto de Venta modo Restaurante. Página STANDALONE (sin
 * layout principal), mismo criterio que caja_sesion/venta.php. Requiere un
 * turno de caja abierto para el punto de emisión (se abre desde Cajas,
 * modulos/caja-pos); este tablero solo LEE esa sesión — no depende de nada
 * más del mostrador.
 *
 * Plano libre: cada mesa se puede arrastrar a cualquier posición (se guarda
 * en % del lienzo, no en píxeles, para que se mantenga proporcional entre
 * dispositivos). Las mesas sin posición asignada caen en una fila inicial
 * predecible hasta que alguien las acomode.
 *
 * @var string $titulo
 * @var string $rutaModulo
 * @var array  $perm
 * @var int    $idPuntoEmision
 * @var array  $sesion
 * @var array  $mesas
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
        .mt-wrap { display: flex; flex-direction: column; height: 100vh; }
        .mt-header { flex: 0 0 auto; padding: 10px 16px 0; }
        .mt-zonas-wrap { flex: 0 0 auto; padding: 10px 16px 0; }
        .mt-zonas { display: flex; gap: 6px; flex-wrap: wrap; background: #fff; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,.06); padding: 10px 12px; }
        .mt-zonas .btn { font-size: .78rem; }
        .mt-canvas-wrap { flex: 1 1 auto; min-height: 0; padding: 10px 16px 16px; }
        .mt-canvas { position: relative; width: 100%; height: 100%; min-height: 480px; background: #fff; border: 1px dashed #dee2e6; border-radius: 12px; overflow: auto; }

        .mt-mesa {
            position: absolute; width: 152px; border-radius: 8px; padding: 14px 12px 14px 14px; cursor: pointer; text-align: left;
            background: #eceef1; border: 1px solid #dadde2; border-left: 4px solid #adb5bd; color: #2b2f36;
            user-select: none; touch-action: none;
            display: flex; flex-direction: column; justify-content: space-between; min-height: 108px;
            box-shadow: 0 1px 3px rgba(16,24,40,.06); transition: box-shadow .12s;
        }
        .mt-mesa.arrastrando { box-shadow: 0 8px 20px rgba(16,24,40,.16); z-index: 50; cursor: grabbing; }
        .mt-mesa.editable { cursor: grab; }
        .mt-mesa.disponible { border-left-color: #3f7d64; }
        .mt-mesa.ocupada { border-left-color: #a5433a; }
        .mt-mesa.por_cobrar { border-left-color: #a8763a; }
        .mt-mesa.mantenimiento { border-left-color: #6c757d; }
        .mt-mesa .nombre { font-size: .96rem; font-weight: 700; color: #1c1f24; }
        .mt-mesa .estado { font-size: .66rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 600; color: #6c7688; display: flex; align-items: center; gap: 5px; }
        .mt-mesa .estado::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
        .mt-mesa.disponible .estado { color: #3f7d64; }
        .mt-mesa.ocupada .estado { color: #a5433a; }
        .mt-mesa.por_cobrar .estado { color: #a8763a; }
        .mt-mesa.mantenimiento .estado { color: #6c757d; }
        .mt-mesa .info { font-size: .74rem; color: #6c7688; margin-top: 6px; }
        .mt-mesa .total { font-size: .95rem; font-weight: 700; margin-top: 2px; color: #1c1f24; }
        .mt-empty { color: #8a94a6; padding: 40px; text-align: center; }
    </style>
</head>
<body>
<div class="mt-wrap">
    <div class="mt-header d-flex align-items-center justify-content-between gap-2 px-3 py-2 bg-primary text-white shadow-sm rounded-3 mx-1">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-grid-3x3-gap-fill fs-5"></i>
            <div>
                <div class="fw-semibold lh-1">Mesas</div>
                <small class="text-white-50">Cajero: <?= htmlspecialchars($sesion['cajero_nombre'] ?? '—') ?></small>
            </div>
        </div>
        <a href="<?= $base ?>/modulos/caja-pos?volver=mesas" class="btn btn-sm btn-outline-light" title="Abrir o cerrar el turno de caja compartido">
            <i class="bi bi-clock-history me-1"></i>Turno de caja
        </a>
    </div>

    <div class="mt-zonas-wrap">
        <div class="mt-zonas" id="mt-zonas"></div>
    </div>

    <div class="mt-canvas-wrap">
        <div class="mt-canvas" id="mt-canvas"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    const BASE = "<?= $base ?>";
    const AJAX = "<?= $rutaAjax ?>";
    const PUEDE_CREAR = <?= !empty($perm['crear']) ? 'true' : 'false' ?>;
    const PUEDE_EDITAR = <?= !empty($perm['actualizar']) ? 'true' : 'false' ?>;
    const $canvas = document.getElementById('mt-canvas');
    const $zonas = document.getElementById('mt-zonas');
    let mesas = <?= json_encode($mesas, JSON_UNESCAPED_UNICODE) ?>;
    let zonaActiva = 'todas';
    let arrastre = null; // {id, el, startX, startY, origLeft, origTop, moved}
    let pausarRefresco = false;

    function swalError(html) {
        Swal.fire({ icon: 'error', title: 'Error', html, confirmButtonColor: '#0d6efd', confirmButtonText: 'Aceptar' });
    }
    function money(v) { return '$' + (parseFloat(v || 0)).toFixed(2); }
    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function renderZonas() {
        const zonas = Array.from(new Set(mesas.map(m => (m.ubicacion || '').trim()).filter(Boolean))).sort();
        const tabs = [['todas', 'Todas']].concat(zonas.map(z => [z, z]));
        if (mesas.some(m => !(m.ubicacion || '').trim())) tabs.push(['__sin_ubicacion__', 'Sin ubicación']);
        $zonas.innerHTML = tabs.map(([val, label]) =>
            `<button type="button" class="btn btn-sm ${val === zonaActiva ? 'btn-primary' : 'btn-outline-secondary'}" data-zona="${escapeHtml(val)}">${escapeHtml(label)}</button>`
        ).join('');
    }

    function mesasVisibles() {
        if (zonaActiva === 'todas') return mesas;
        if (zonaActiva === '__sin_ubicacion__') return mesas.filter(m => !(m.ubicacion || '').trim());
        return mesas.filter(m => (m.ubicacion || '').trim() === zonaActiva);
    }

    /** Posición inicial (fila predecible) para mesas sin pos_x/pos_y todavía. */
    function posicionFallback(index) {
        const porFila = 5;
        const col = index % porFila;
        const fila = Math.floor(index / porFila);
        return { x: 4 + col * 19, y: 4 + fila * 26 };
    }

    function render() {
        const visibles = mesasVisibles();
        if (!visibles.length) {
            $canvas.innerHTML = '<div class="mt-empty"><i class="bi bi-grid-3x3-gap fs-1 d-block mb-2"></i>No hay mesas en esta ubicación.</div>';
            return;
        }
        $canvas.innerHTML = '';
        visibles.forEach((m, idx) => {
            const ocupada = !!m.id_comanda;
            const estado = ocupada ? 'ocupada' : (m.estado || 'disponible');
            const tienePos = m.pos_x !== null && m.pos_x !== undefined && m.pos_y !== null && m.pos_y !== undefined;
            const pos = tienePos ? { x: parseFloat(m.pos_x), y: parseFloat(m.pos_y) } : posicionFallback(idx);

            let info = '';
            if (ocupada) {
                info = (m.numero_comanda || '') + (m.mesero_nombre ? ' &middot; ' + escapeHtml(m.mesero_nombre) : '')
                     + (m.items_comanda ? ' &middot; ' + m.items_comanda + ' ítem(s)' : '');
            } else if (m.capacidad) {
                info = m.capacidad + ' puestos';
            }

            const el = document.createElement('button');
            el.type = 'button';
            el.className = 'mt-mesa ' + estado + (PUEDE_EDITAR ? ' editable' : '');
            el.style.left = pos.x + '%';
            el.style.top = pos.y + '%';
            el.dataset.id = m.id;
            el.dataset.idComanda = m.id_comanda || '';
            el.innerHTML = `
                <div>
                    <div class="nombre">${escapeHtml(m.nombre)}</div>
                    <div class="estado">${ocupada ? 'Ocupada' : (estado === 'disponible' ? 'Disponible' : estado)}</div>
                </div>
                <div>
                    <div class="info">${info}</div>
                    ${ocupada ? '<div class="total">' + money(m.total_comanda) + '</div>' : ''}
                </div>`;
            $canvas.appendChild(el);
            engancharArrastre(el);
        });
    }

    async function abrirOEntrar(el) {
        const idMesa = el.dataset.id;
        const idComanda = el.dataset.idComanda;
        const URL_VER = BASE + '/modulos/comandas/ver'; // URL limpia: el id viaja en sesión, no en la dirección.

        if (idComanda) {
            try {
                const fd = new FormData();
                fd.append('id', idComanda);
                const r = await fetch(BASE + '/modulos/comandas/entrarAjax', { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.ok) { swalError(d.error || 'No se pudo abrir la comanda.'); return; }
                window.location.href = URL_VER;
            } catch (e) { swalError('Error de conexión.'); }
            return;
        }
        if (!PUEDE_CREAR) { swalError('No tienes permiso para abrir comandas.'); return; }

        // Sin preguntar comensales: al abrir la mesa el mesero todavía no sabe
        // cuántos van a sentarse. Se puede registrar después, ya en la comanda.
        try {
            const fd = new FormData();
            fd.append('id_mesa', idMesa);
            const r = await fetch(BASE + '/modulos/comandas/abrirAjax', { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { swalError(d.error || 'No se pudo abrir la comanda.'); return; }
            window.location.href = URL_VER;
        } catch (e) { swalError('Error de conexión al abrir la comanda.'); }
    }

    async function guardarPosicion(id, posX, posY) {
        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('pos_x', posX.toFixed(2));
            fd.append('pos_y', posY.toFixed(2));
            await fetch(AJAX + '/actualizarPosicionAjax', { method: 'POST', body: fd });
        } catch (e) { /* la próxima carga vuelve a intentar desde la última posición conocida */ }
    }

    const UMBRAL_ARRASTRE = 6; // px: menos que esto se trata como un simple clic (abrir mesa)

    function engancharArrastre(el) {
        el.addEventListener('pointerdown', (ev) => {
            if (ev.button !== undefined && ev.button !== 0) return;
            arrastre = {
                id: el.dataset.id, el,
                startX: ev.clientX, startY: ev.clientY,
                origLeft: el.offsetLeft, origTop: el.offsetTop,
                moved: false,
            };
            el.setPointerCapture(ev.pointerId);
            pausarRefresco = true;
        });

        el.addEventListener('pointermove', (ev) => {
            if (!arrastre || arrastre.el !== el || !PUEDE_EDITAR) return;
            const dx = ev.clientX - arrastre.startX;
            const dy = ev.clientY - arrastre.startY;
            if (!arrastre.moved && (Math.abs(dx) > UMBRAL_ARRASTRE || Math.abs(dy) > UMBRAL_ARRASTRE)) {
                arrastre.moved = true;
                el.classList.add('arrastrando');
            }
            if (arrastre.moved) {
                let left = arrastre.origLeft + dx;
                let top = arrastre.origTop + dy;
                left = Math.max(0, Math.min(left, $canvas.clientWidth - el.offsetWidth));
                top = Math.max(0, Math.min(top, $canvas.clientHeight - el.offsetHeight));
                el.style.left = left + 'px';
                el.style.top = top + 'px';
            }
        });

        el.addEventListener('pointerup', async (ev) => {
            if (!arrastre || arrastre.el !== el) return;
            const fueArrastre = arrastre.moved;
            el.classList.remove('arrastrando');
            arrastre = null;
            pausarRefresco = false;

            if (fueArrastre && PUEDE_EDITAR) {
                const posX = (el.offsetLeft / $canvas.clientWidth) * 100;
                const posY = (el.offsetTop / $canvas.clientHeight) * 100;
                el.style.left = posX + '%';
                el.style.top = posY + '%';
                const m = mesas.find(x => String(x.id) === String(el.dataset.id));
                if (m) { m.pos_x = posX; m.pos_y = posY; }
                await guardarPosicion(el.dataset.id, posX, posY);
            } else {
                await abrirOEntrar(el);
            }
        });
    }

    $zonas.addEventListener('click', (ev) => {
        const btn = ev.target.closest('[data-zona]');
        if (!btn) return;
        zonaActiva = btn.dataset.zona;
        renderZonas();
        render();
    });

    async function refrescar() {
        if (pausarRefresco) return;
        try {
            const r = await fetch(AJAX + '/tableroAjax');
            const d = await r.json();
            if (d.ok) { mesas = d.data; renderZonas(); render(); }
        } catch (e) { /* silencioso: reintenta en el próximo ciclo */ }
    }

    renderZonas();
    render();
    setInterval(refrescar, 10000);
})();
</script>
</body>
</html>
