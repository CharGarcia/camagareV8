<?php
/**
 * Página pública de recepción de una transferencia de inventario.
 * Llega por el enlace del correo, sin login: es standalone (arma su propio
 * <head>) y no carga el layout del sistema. No necesita el token CSRF porque el
 * controlador está en $publicControllers (exento de esa validación).
 *
 * @var string $vista  'detalle' | 'resultado'
 */
$base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
$e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$num = static fn($v): string => rtrim(rtrim(number_format((float) $v, 6, '.', ''), '0'), '.');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recepción de transferencia</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: #f1f5f9; color: #1e293b; }
        .wrap { max-width: 760px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.08); overflow: hidden; }
        .card-head { background: #2563eb; color: #fff; padding: 18px 22px; }
        .card-head h1 { font-size: 18px; margin: 0; }
        .card-head p { margin: 4px 0 0; font-size: 13px; opacity: .85; }
        .card-body { padding: 22px; }
        .ruta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-size: 15px; font-weight: 600; margin-bottom: 16px; }
        .ruta .bodega { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 10px; }
        .ruta .flecha { color: #64748b; }
        .meta { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px 20px; margin-bottom: 18px; }
        .meta .lbl { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .meta .val { font-size: 15px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 7px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; color: #475569; font-weight: 600; }
        td.num, th.num { text-align: right; }
        .traza { color: #64748b; font-size: 12px; }
        .campo { margin-top: 18px; }
        .campo label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; }
        input[type=text], textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px; font-family: inherit; font-size: 14px; }
        .actions { margin-top: 22px; display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { border: none; border-radius: 8px; padding: 11px 22px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-ok { background: #16a34a; color: #fff; }
        .btn-no { background: #fff; color: #dc2626; border: 1px solid #dc2626; }
        .btn-pdf { background: #fff; color: #b91c1c; border: 1px solid #b91c1c; }
        .rechazo-box { display: none; margin-top: 14px; padding: 14px; background: #fef2f2; border-radius: 8px; }
        .aviso { background: #f8fafc; border-left: 3px solid #2563eb; padding: 10px 14px; font-size: 13px; color: #475569; margin-bottom: 18px; }
        .resuelta { padding: 14px; border-radius: 8px; font-size: 14px; margin-bottom: 18px; }
        .resuelta.ok { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .resuelta.no { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .result { text-align: center; padding: 10px 0; }
        .result .ico { font-size: 46px; }
        .result h2 { margin: 12px 0 6px; font-size: 20px; }
        .result p { color: #475569; }
        .muted { color: #94a3b8; font-size: 12px; margin-top: 18px; text-align: center; }
        @media (max-width: 520px) { .meta { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <?php if ($vista === 'detalle'):
            $d          = $doc;
            $detalles   = $d['detalles'] ?? [];
            $recepcion  = (string) ($d['recepcion_estado'] ?? 'pendiente');
            $hayTraza   = false;
            foreach ($detalles as $l) {
                $hayTraza = $hayTraza || trim((string) ($l['numero_lote'] ?? '')) !== '' || trim((string) ($l['nup'] ?? '')) !== '';
            }
        ?>
            <div class="card-head">
                <h1>Recepción de transferencia <?= $e($d['numero']) ?></h1>
                <p><?= $e($d['empresa_nombre']) ?></p>
            </div>
            <div class="card-body">

                <?php if (!empty($yaResuelta)): ?>
                    <div class="resuelta <?= $recepcion === 'recibida' ? 'ok' : 'no' ?>">
                        <strong><?= $recepcion === 'recibida' ? 'Recepción ya confirmada' : 'Recepción ya rechazada' ?></strong><br>
                        <?= $e($d['recepcion_nombre'] ?? '') ?>
                        <?php if (!empty($d['recepcion_fecha'])): ?>
                            · <?= $e(date('d-m-Y H:i:s', strtotime((string) $d['recepcion_fecha']))) ?>
                        <?php endif; ?>
                        <?php if (!empty($d['recepcion_comentario'])): ?>
                            <br><em><?= $e($d['recepcion_comentario']) ?></em>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="aviso">
                        Revise el detalle y confirme que recibió la mercadería tal como consta aquí.
                        Su confirmación queda registrada en el sistema como constancia de la entrega.
                    </div>
                <?php endif; ?>

                <div class="ruta">
                    <span class="bodega"><?= $e($d['origen_nombre']) ?></span>
                    <span class="flecha">&rarr;</span>
                    <span class="bodega"><?= $e($d['destino_nombre']) ?></span>
                    <a class="btn btn-pdf" style="padding:6px 14px;font-size:13px;margin-left:auto;"
                       href="<?= $base ?>/recepcion-transferencia/<?= $e($token) ?>/pdf" target="_blank" rel="noopener">
                        Ver el acta en PDF
                    </a>
                </div>

                <div class="meta">
                    <div><div class="lbl">Fecha</div><div class="val"><?= $d['fecha_transferencia'] ? $e(date('d-m-Y H:i:s', strtotime((string) $d['fecha_transferencia']))) : '-' ?></div></div>
                    <div><div class="lbl">Unidades</div><div class="val"><?= $e(number_format((float) $d['total_items'], 2)) ?></div></div>
                    <div><div class="lbl">Entrega</div><div class="val"><?= $e(($d['responsable_envia'] ?? '') !== '' ? $d['responsable_envia'] : '-') ?></div></div>
                    <div><div class="lbl">Recibe</div><div class="val"><?= $e(($d['responsable_recibe'] ?? '') !== '' ? $d['responsable_recibe'] : '-') ?></div></div>
                </div>

                <table>
                    <thead><tr>
                        <th>Producto</th>
                        <?php if ($hayTraza): ?><th>Lote / Serie</th><?php endif; ?>
                        <th class="num">Cantidad</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($detalles as $l): ?>
                            <tr>
                                <td>
                                    <?= $e($l['producto_nombre'] ?? '') ?>
                                    <?php if (!empty($l['producto_codigo'])): ?>
                                        <span class="traza">· <?= $e($l['producto_codigo']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($hayTraza): ?>
                                    <td class="traza">
                                        <?= $e(trim(($l['numero_lote'] ?? '') . ' ' . ($l['nup'] ?? ''))) ?: '-' ?>
                                        <?php if (!empty($l['fecha_caducidad'])): ?>
                                            <br>vence <?= $e(date('d-m-Y', strtotime((string) $l['fecha_caducidad']))) ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td class="num"><?= $e($num($l['cantidad'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (!empty($d['observaciones'])): ?>
                    <p class="traza" style="margin-top:14px;"><strong>Observaciones:</strong> <?= $e($d['observaciones']) ?></p>
                <?php endif; ?>

                <?php if (empty($yaResuelta)): ?>
                    <form method="POST" action="<?= $base ?>/recepcion-transferencia/<?= $e($token) ?>/confirmar"
                          onsubmit="return confirm('¿Confirmar que recibió esta transferencia conforme?');">
                        <input type="hidden" name="token" value="<?= $e($token) ?>">
                        <div class="campo">
                            <label for="nombre">Nombre de quien recibe *</label>
                            <input type="text" id="nombre" name="nombre" maxlength="150" required
                                   value="<?= $e($d['responsable_recibe'] ?? '') ?>" placeholder="Nombre y apellido">
                        </div>
                        <div class="campo">
                            <label for="comentario">Observaciones (opcional)</label>
                            <textarea id="comentario" name="comentario" rows="2" placeholder="Cualquier detalle sobre la entrega…"></textarea>
                        </div>
                        <div class="actions">
                            <button type="submit" class="btn btn-ok">✓ Confirmar recepción</button>
                            <button type="button" class="btn btn-no"
                                    onclick="document.getElementById('rbox').style.display='block';this.style.display='none';">✕ Rechazar</button>
                        </div>
                    </form>

                    <form method="POST" action="<?= $base ?>/recepcion-transferencia/<?= $e($token) ?>/rechazar" class="rechazo-box" id="rbox">
                        <input type="hidden" name="token" value="<?= $e($token) ?>">
                        <div class="campo" style="margin-top:0;">
                            <label for="nombre-rechazo" style="color:#b91c1c;">Su nombre *</label>
                            <input type="text" id="nombre-rechazo" name="nombre" maxlength="150" required placeholder="Nombre y apellido">
                        </div>
                        <div class="campo">
                            <label for="motivo" style="color:#b91c1c;">Motivo del rechazo *</label>
                            <textarea id="motivo" name="motivo" rows="3" required placeholder="Qué faltó o qué llegó distinto de lo que dice el acta…"></textarea>
                        </div>
                        <div style="margin-top:10px;"><button type="submit" class="btn btn-no">Confirmar rechazo</button></div>
                    </form>
                <?php endif; ?>

                <p class="muted">
                    El inventario ya fue trasladado en el sistema al registrar la transferencia:
                    esta página solo deja constancia de la conformidad de quien recibe.
                </p>
            </div>
        <?php else: /* resultado */
            $ico = ['ok' => ['✓', '#16a34a'], 'error' => ['✕', '#dc2626'], 'info' => ['ℹ', '#2563eb']][$tipo] ?? ['ℹ', '#2563eb'];
        ?>
            <div class="card-body">
                <div class="result">
                    <div class="ico" style="color:<?= $ico[1] ?>;"><?= $ico[0] ?></div>
                    <h2><?= $tipo === 'ok' ? 'Listo' : ($tipo === 'error' ? 'No se pudo procesar' : 'Información') ?></h2>
                    <p><?= $e($mensaje) ?></p>
                </div>
                <p class="muted">Puede cerrar esta ventana.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
