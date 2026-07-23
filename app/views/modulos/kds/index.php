<?php
/**
 * Pantalla de cocina/barra (KDS) — POS Restaurantes, Fase 2. Página STANDALONE
 * pensada para quedar fija en una tablet/monitor de la cocina o la barra.
 * Se refresca sola por polling (sin WebSockets, por restricción de infra —
 * ver memoria del proyecto); las líneas 'listo' desaparecen de aquí (pasan a
 * ser responsabilidad del mesero en modulos/comandas/ver).
 *
 * @var string $titulo
 * @var string $rutaModulo
 * @var array  $perm
 * @var array  $estaciones  [{id, nombre, tipo, orden, activo}] — catálogo configurable, no un enum fijo
 * @var int    $idEstacion
 * @var array  $comandas
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
        body { background: #14171c; color: #fff; }
        .kd-header { padding: 10px 18px; background: #1e222a; border-bottom: 1px solid #2c313a; }
        .kd-tabs a { color: #8a94a6; text-decoration: none; padding: 6px 14px; border-radius: 999px; font-size: .85rem; font-weight: 600; }
        .kd-tabs a.active { background: #0d6efd; color: #fff; }
        .kd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; padding: 16px; }
        .kd-card { background: #1e222a; border: 1px solid #2c313a; border-radius: 12px; overflow: hidden; }
        .kd-card.urgente { border-color: #dc3545; }
        .kd-card-header { padding: 10px 14px; background: #262b34; display: flex; justify-content: space-between; align-items: center; }
        .kd-card-header .num { font-weight: 700; }
        .kd-card-header .tiempo { font-size: .78rem; color: #adb5bd; }
        .kd-item { padding: 10px 14px; border-bottom: 1px solid #262b34; }
        .kd-item:last-child { border-bottom: none; }
        .kd-item .desc { font-size: .92rem; font-weight: 600; }
        .kd-item .obs { font-size: .78rem; color: #f0ad4e; margin-top: 2px; }
        .kd-item .badge-estado { font-size: .68rem; }
        .kd-item .acciones { margin-top: 6px; }
        .kd-empty { color: #6c757d; text-align: center; padding: 60px 20px; grid-column: 1 / -1; }
    </style>
</head>
<body>
<div class="kd-header d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-3">
        <i class="bi bi-egg-fried fs-4"></i>
        <div class="fw-semibold">Cocina / Barra</div>
        <div class="kd-tabs d-flex gap-1">
            <?php if (empty($estaciones)): ?>
                <span class="text-muted small">Sin estaciones configuradas — créalas en Menú → pestaña Estaciones.</span>
            <?php endif; ?>
            <?php foreach ($estaciones as $e): ?>
                <a href="<?= $rutaAjax ?>?id_estacion=<?= (int) $e['id'] ?>" class="<?= (int) $e['id'] === $idEstacion ? 'active' : '' ?>"><?= htmlspecialchars($e['nombre']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <a href="<?= $base ?>/modulos/mesas/tablero" class="btn btn-sm btn-outline-light"><i class="bi bi-grid-3x3-gap-fill me-1"></i>Mesas</a>
</div>

<div id="kd-grid" class="kd-grid"></div>

<script>
(function () {
    const AJAX = "<?= $rutaAjax ?>";
    const ID_ESTACION = <?= (int) $idEstacion ?>;
    const PUEDE_ACTUALIZAR = <?= !empty($perm['actualizar']) ? 'true' : 'false' ?>;
    const $grid = document.getElementById('kd-grid');
    let comandas = <?= json_encode($comandas, JSON_UNESCAPED_UNICODE) ?>;

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function minutosDesde(fecha) {
        if (!fecha) return 0;
        const ms = Date.now() - new Date(fecha.replace(' ', 'T')).getTime();
        return Math.max(0, Math.floor(ms / 60000));
    }

    function badgeAccion(item) {
        if (!PUEDE_ACTUALIZAR) return '';
        if (item.estado_linea === 'enviado') {
            return `<button type="button" class="btn btn-sm btn-outline-warning acciones" data-id="${item.id}" data-estado="preparando">
                        <i class="bi bi-fire me-1"></i>Preparando</button>`;
        }
        if (item.estado_linea === 'preparando') {
            return `<button type="button" class="btn btn-sm btn-outline-success acciones" data-id="${item.id}" data-estado="listo">
                        <i class="bi bi-check2 me-1"></i>Listo</button>`;
        }
        return '';
    }

    function render() {
        if (!comandas.length) {
            $grid.innerHTML = '<div class="kd-empty"><i class="bi bi-cup-hot fs-1 d-block mb-2"></i>No hay pedidos pendientes.</div>';
            return;
        }
        $grid.innerHTML = comandas.map(c => {
            const min = minutosDesde(c.enviado_at);
            const urgente = min >= 15 ? 'urgente' : '';
            return `<div class="kd-card ${urgente}">
                        <div class="kd-card-header">
                            <span class="num">${escapeHtml(c.numero_comanda)} &middot; Mesa ${escapeHtml(c.mesa_nombre)}</span>
                            <span class="tiempo">${min} min</span>
                        </div>
                        ${c.lineas.map(item => `
                            <div class="kd-item">
                                <div class="desc">${escapeHtml(item.cantidad)} x ${escapeHtml(item.descripcion)}</div>
                                ${item.observacion_item ? '<div class="obs"><i class="bi bi-chat-left-text me-1"></i>' + escapeHtml(item.observacion_item) + '</div>' : ''}
                                ${badgeAccion(item)}
                            </div>`).join('')}
                    </div>`;
        }).join('');
    }

    $grid.addEventListener('click', async (ev) => {
        const btn = ev.target.closest('.acciones');
        if (!btn) return;
        const fd = new FormData();
        fd.append('id_linea', btn.dataset.id);
        fd.append('estado', btn.dataset.estado);
        btn.disabled = true;
        try {
            const r = await fetch(AJAX + '/marcarEstadoAjax', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) { await refrescar(); } else { btn.disabled = false; alert(d.error || 'No se pudo actualizar.'); }
        } catch (e) { btn.disabled = false; }
    });

    async function refrescar() {
        if (!ID_ESTACION) return;
        try {
            const r = await fetch(AJAX + '/pollAjax?id_estacion=' + ID_ESTACION);
            const d = await r.json();
            if (d.ok) { comandas = d.data; render(); }
        } catch (e) { /* silencioso: reintenta en el próximo ciclo */ }
    }

    render();
    if (ID_ESTACION) setInterval(refrescar, 5000);
})();
</script>
</body>
</html>
