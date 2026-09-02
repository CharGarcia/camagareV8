<?php
/**
 * Pantalla de cocina/barra (KDS) — POS Restaurantes, Fase 2. Página STANDALONE
 * pensada para quedar fija en una tablet/monitor de la cocina o la barra.
 * Se refresca sola por polling (sin WebSockets, por restricción de infra —
 * ver memoria del proyecto); las líneas 'listo' desaparecen de aquí (pasan a
 * ser responsabilidad del mesero en modulos/comandas/ver).
 *
 * Además, esta pantalla es la que IMPRIME las órdenes de su estación: el
 * servidor está fuera de la red del restaurante y no alcanza a la impresora,
 * así que encola (comandas_impresiones) y este navegador saca el papel por la
 * impresora conectada a ESTE equipo. Para que salga sin diálogo, Chrome debe
 * arrancarse con --kiosk-printing.
 *
 * @var string $titulo
 * @var string $rutaModulo
 * @var array  $perm
 * @var array  $estaciones  [{id, nombre, tipo, orden, activo}] — catálogo configurable, no un enum fijo
 * @var int    $idEstacion
 * @var array  $comandas
 * @var ?array $estacionImpresora  Config de impresora de esta estación, o null si es solo pantalla
 * @var string $empresaNombre
 */
$base = rtrim(BASE_URL ?? '', '/');
$rutaAjax = $base . '/' . $rutaModulo;
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <?php require MVC_APP . "/views/partials/csrf.php"; ?>
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
        .kd-print-badge { font-size: .72rem; color: #6c757d; display: flex; align-items: center; gap: 5px; }
        .kd-print-badge.activa { color: #59d18a; }
        .kd-card-header .btn-reimprimir { --bs-btn-padding-y: .05rem; --bs-btn-padding-x: .35rem; font-size: .7rem; }
    </style>
</head>
<body>
<div class="kd-header d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-3">
        <i class="bi bi-egg-fried fs-4"></i>
        <div class="fw-semibold">Pantalla de preparación</div>
        <div class="kd-tabs d-flex gap-1">
            <?php if (empty($estaciones)): ?>
                <span class="text-muted small">Sin estaciones configuradas — créalas en Configuración Restaurante.</span>
            <?php endif; ?>
            <?php foreach ($estaciones as $e): ?>
                <a href="<?= $rutaAjax ?>?id_estacion=<?= (int) $e['id'] ?>" class="<?= (int) $e['id'] === $idEstacion ? 'active' : '' ?>"><?= htmlspecialchars($e['nombre']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="d-flex align-items-center gap-3">
        <?php if (!empty($estacionImpresora)): ?>
            <?php // Estado visible: en cocina nadie abre la consola para saber si la impresión quedó activa. ?>
            <div class="kd-print-badge activa" id="kd-print-estado" title="Esta pantalla imprime las órdenes de la estación en la impresora de este equipo">
                <i class="bi bi-printer-fill"></i>
                <span>Impresión activa · <?= (int) $estacionImpresora['ancho_papel'] ?> mm<?= (int) $estacionImpresora['copias'] > 1 ? ' · ' . (int) $estacionImpresora['copias'] . ' copias' : '' ?></span>
            </div>
        <?php elseif ($idEstacion): ?>
            <div class="kd-print-badge" title="Esta estación no tiene impresora. Actívala en Configuración Restaurante.">
                <i class="bi bi-printer"></i><span>Solo pantalla</span>
            </div>
        <?php endif; ?>
        <a href="<?= $base ?>/modulos/mesas/tablero" class="btn btn-sm btn-outline-light"><i class="bi bi-grid-3x3-gap-fill me-1"></i>Mesas</a>
    </div>
</div>

<div id="kd-grid" class="kd-grid"></div>

<script>
(function () {
    const AJAX = "<?= $rutaAjax ?>";
    const ID_ESTACION = <?= (int) $idEstacion ?>;
    const PUEDE_ACTUALIZAR = <?= !empty($perm['actualizar']) ? 'true' : 'false' ?>;
    const IMPRESORA = <?= json_encode($estacionImpresora ?: null, JSON_UNESCAPED_UNICODE) ?>;
    const EMPRESA_NOMBRE = <?= json_encode($empresaNombre ?? '', JSON_UNESCAPED_UNICODE) ?>;
    const $grid = document.getElementById('kd-grid');
    let comandas = <?= json_encode($comandas, JSON_UNESCAPED_UNICODE) ?>;

    // Las cantidades vienen de Postgres como numeric ("1.000000"): se muestran
    // con los decimales configurados en Empresa → Facturación.
    const DEC_CANT = <?= (int) ($decimalesCantidad ?? 2) ?>;
    function cantidad(v) { return (parseFloat(v) || 0).toFixed(DEC_CANT); }

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

    // ─── Impresión de órdenes ────────────────────────────────────────────────
    // El servidor no ve la impresora del restaurante: encola en
    // comandas_impresiones y ESTE navegador saca el papel. El ciclo es
    // imprimir → marcar impreso; sin el marcado el ticket volvería en cada
    // poll, así que una pantalla sin permiso de actualizar no imprime (el
    // servidor tampoco le manda la cola).

    // Ids ya impresos por ESTA pantalla. Si el marcado en el servidor falla
    // (red intermitente), evita que el mismo papel salga otra vez mientras se
    // reintenta solo el marcado.
    const yaImpresos = new Set();
    let imprimiendo = false;

    function fechaHora(iso) {
        if (!iso) return '';
        const d = new Date(String(iso).replace(' ', 'T'));
        return isNaN(d) ? String(iso) : d.toLocaleString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    /**
     * Ticket de cocina: sin precios ni datos fiscales — es una instrucción de
     * preparación. Letra grande a propósito: se lee de pie, a un metro y con
     * las manos ocupadas.
     */
    function armarTicketCocina(t) {
        const lineas = (t.lineas || []).map(l => `
            <tr><td class="cant">${cantidad(l.cantidad)}</td>
                <td class="desc">${escapeHtml(l.descripcion)}
                    ${l.observacion_item ? `<div class="obs">▸ ${escapeHtml(l.observacion_item)}</div>` : ''}
                </td></tr>`).join('');

        // 58 mm tiene menos ancho útil: la misma letra no entra.
        const esAngosto = parseInt(t.ancho_papel, 10) === 58;

        return `<!DOCTYPE html><html lang="es"><head>
            <meta charset="UTF-8">
            <title>Orden ${escapeHtml(t.numero_comanda)}</title>
            <?php require MVC_APP . "/views/partials/tirilla_estilos.php"; ?>
            <style>
                /* El ticket de cocina pisa el tamaño de la tirilla de cuenta:
                   aquí no compite espacio con precios ni impuestos, y lo que
                   importa es que se lea rápido. */
                body { font-size: ${esAngosto ? 13 : 15}px; line-height: 1.25; }
                .est { font-size: ${esAngosto ? 17 : 20}px; font-weight: bold; text-align: center; }
                .mesa { font-size: ${esAngosto ? 20 : 24}px; font-weight: bold; text-align: center; margin: 2px 0; }
                .meta { font-size: ${esAngosto ? 11 : 12}px; text-align: center; }
                .copia { text-align: center; font-weight: bold; font-size: ${esAngosto ? 15 : 17}px; border: 2px solid #000; padding: 2px; margin: 3px 0; }
                table.items { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 4px; }
                table.items td { padding: 4px 0; vertical-align: top; border-bottom: 1px dashed #000; }
                td.cant { width: 20%; font-size: ${esAngosto ? 18 : 21}px; font-weight: bold; }
                td.desc { font-size: ${esAngosto ? 15 : 17}px; font-weight: bold; }
                .obs { font-size: ${esAngosto ? 13 : 14}px; font-weight: normal; margin-top: 2px; }
            </style>
        </head><body>
            <div class="est">${escapeHtml(t.estacion_nombre)}</div>
            ${EMPRESA_NOMBRE ? `<div class="meta">${escapeHtml(EMPRESA_NOMBRE)}</div>` : ''}
            <hr class="sep">
            <div class="mesa">${escapeHtml(t.mesa_nombre)}</div>
            <div class="meta">${escapeHtml(t.numero_comanda)} &middot; ${escapeHtml(fechaHora(t.created_at))}</div>
            ${t.mesero_nombre ? `<div class="meta">Mesero: ${escapeHtml(t.mesero_nombre)}</div>` : ''}
            ${t.es_reimpresion ? '<div class="copia">— COPIA —</div>' : ''}
            <table class="items"><colgroup><col style="width:20%"><col></colgroup><tbody>${lineas}</tbody></table>
            ${t.comanda_observaciones ? `<hr class="sep"><div>${escapeHtml(t.comanda_observaciones)}</div>` : ''}
            <br><br>
        </body></html>`;
    }

    /**
     * Imprime en un iframe oculto, no en una ventana nueva: el bloqueador de
     * emergentes mataría un window.open sin clic del usuario, y esta pantalla
     * imprime sola. Con Chrome en --kiosk-printing no aparece diálogo.
     */
    function imprimirHtml(html) {
        return new Promise(resolve => {
            const iframe = document.createElement('iframe');
            iframe.setAttribute('aria-hidden', 'true');
            iframe.style.cssText = 'position:fixed;left:-9999px;top:0;width:1px;height:1px;border:0;';
            document.body.appendChild(iframe);

            const doc = iframe.contentWindow.document;
            doc.open();
            doc.write(html);
            doc.close();

            // Un respiro para que el iframe termine de maquetar antes de print():
            // sin él, el navegador puede mandar la página a medio armar.
            setTimeout(() => {
                try {
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                } catch (e) { /* la impresora resolverá; no se puede hacer más desde aquí */ }
                setTimeout(() => { iframe.remove(); resolve(); }, 1200);
            }, 300);
        });
    }

    async function marcarImpreso(id) {
        const fd = new FormData();
        fd.append('id', id);
        try {
            const r = await fetch(AJAX + '/marcarImpresoAjax', { method: 'POST', body: fd });
            const d = await r.json();
            return !!(d && d.ok);
        } catch (e) {
            return false; // se reintenta en el próximo poll
        }
    }

    async function procesarImpresiones(tickets) {
        if (!IMPRESORA || imprimiendo || !Array.isArray(tickets) || !tickets.length) return;
        imprimiendo = true;
        try {
            for (const t of tickets) {
                // Ya salió en esta pantalla y solo faltó cerrar el ciclo: se
                // reintenta el marcado, nunca el papel.
                if (yaImpresos.has(t.id)) {
                    await marcarImpreso(t.id);
                    continue;
                }
                yaImpresos.add(t.id);

                const html = armarTicketCocina(t);
                const copias = Math.min(5, Math.max(1, parseInt(t.copias, 10) || 1));
                for (let i = 0; i < copias; i++) {
                    await imprimirHtml(html);
                }
                await marcarImpreso(t.id);
            }
        } finally {
            imprimiendo = false;
        }
    }

    async function reimprimir(idComanda) {
        const fd = new FormData();
        fd.append('id_comanda', idComanda);
        fd.append('id_estacion', ID_ESTACION);
        try {
            const r = await fetch(AJAX + '/reimprimirAjax', { method: 'POST', body: fd });
            const d = await r.json();
            if (!d.ok) { alert(d.error || 'No se pudo reimprimir.'); return; }
            await refrescar(); // el ticket nuevo llega en la cola de este mismo poll
        } catch (e) { /* silencioso: se puede volver a pulsar */ }
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
                            <span class="d-flex align-items-center gap-2">
                                ${(IMPRESORA && PUEDE_ACTUALIZAR) ? `<button type="button" class="btn btn-outline-light btn-reimprimir js-reimprimir" data-comanda="${c.id_comanda}" title="Volver a imprimir esta orden"><i class="bi bi-printer"></i></button>` : ''}
                                <span class="tiempo">${min} min</span>
                            </span>
                        </div>
                        ${c.lineas.map(item => `
                            <div class="kd-item">
                                <div class="desc">${cantidad(item.cantidad)} x ${escapeHtml(item.descripcion)}</div>
                                ${item.observacion_item ? '<div class="obs"><i class="bi bi-chat-left-text me-1"></i>' + escapeHtml(item.observacion_item) + '</div>' : ''}
                                ${badgeAccion(item)}
                            </div>`).join('')}
                    </div>`;
        }).join('');
    }

    $grid.addEventListener('click', async (ev) => {
        const btnPrint = ev.target.closest('.js-reimprimir');
        if (btnPrint) {
            btnPrint.disabled = true;
            await reimprimir(btnPrint.dataset.comanda);
            return;
        }

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
            if (d.ok) {
                comandas = d.data;
                render();
                // Después de pintar: imprimir bloquea el hilo mientras dura el
                // diálogo (cuando lo hay) y la cocina no debe quedarse sin ver
                // las tarjetas por eso. No se espera — el próximo poll no debe
                // depender de que la impresora termine.
                procesarImpresiones(d.impresiones);
            }
        } catch (e) { /* silencioso: reintenta en el próximo ciclo */ }
    }

    render();
    if (ID_ESTACION) {
        // Un poll inmediato además del intervalo: al abrir la pantalla puede
        // haber órdenes esperando (la tablet estuvo apagada), y no tiene sentido
        // que la cocina espere 5 s por ellas.
        refrescar();
        setInterval(refrescar, 5000);
    }
})();
</script>
</body>
</html>
