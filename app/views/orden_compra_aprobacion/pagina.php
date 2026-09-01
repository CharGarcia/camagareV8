<?php
/** @var string $vista  'detalle' | 'resultado' */
$base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
$e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$fmtNum = static fn($v) => number_format((float) $v, 2, '.', ',');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aprobación de Orden de Compra</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: #eef2f7; color: #1e293b; }
        .wrap { max-width: 640px; margin: 28px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 14px; box-shadow: 0 6px 22px rgba(15,40,80,.10); overflow: hidden; }
        .card-head { background: #1f4e79; color: #fff; padding: 20px 24px; }
        .card-head h1 { font-size: 19px; margin: 0; font-weight: 700; }
        .card-head p { margin: 4px 0 0; font-size: 13px; opacity: .85; }
        .card-body { padding: 22px 24px; }
        .num-badge { display:inline-block; background:#eaf1f8; color:#1f4e79; font-weight:700; border-radius:8px; padding:6px 12px; font-size:14px; margin-bottom:14px; }
        .meta { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px 20px; margin-bottom: 6px; }
        .meta .lbl { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .meta .val { font-size: 15px; font-weight: 600; margin-top: 2px; }
        table.items { width:100%; border-collapse:collapse; margin:18px 0 6px; font-size:13px; }
        table.items th { text-align:left; background:#f1f5f9; color:#475569; font-size:11px; text-transform:uppercase; letter-spacing:.03em; padding:8px 10px; }
        table.items td { padding:8px 10px; border-top:1px solid #e2e8f0; }
        table.items td.num { text-align:right; }
        table.items .item-nota { color:#64748b; font-size:11px; margin-top:3px; }
        table.items tfoot td { color:#475569; }
        table.items tfoot tr:first-child td { border-top:2px solid #cbd5e1; font-weight:600; color:#1e293b; }
        .total-box { margin: 18px 0 6px; background:#1f4e79; color:#fff; border-radius:10px; padding:14px 18px; display:flex; justify-content:space-between; align-items:center; }
        .total-box .t-lbl { font-size:13px; opacity:.9; }
        .total-box .t-val { font-size:22px; font-weight:800; }
        .actions { margin-top: 20px; }
        .btn { border: none; border-radius: 9px; padding: 13px 22px; font-size: 15px; font-weight: 700; cursor: pointer; width:100%; }
        .btn-ok { background: #16a34a; color: #fff; }
        .btn-ok:hover { background:#15803d; }
        .note { color:#64748b; font-size:12px; margin-top:14px; text-align:center; line-height:1.5; }
        .result { text-align: center; padding: 16px 0; }
        .result .ico { font-size: 54px; line-height:1; }
        .result h2 { margin: 14px 0 6px; font-size: 21px; }
        .result p { color: #475569; font-size:15px; }
        .muted { color: #94a3b8; font-size: 12px; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <?php if (($vista ?? '') === 'detalle'):
            $o = $orden;
            // Mismos totales (y mismo redondeo) que el PDF que recibió el proveedor.
            $tot   = \App\Services\modulos\OrdenCompraService::calcularTotales($detalle ?? []);
            $total = $tot['total'];
            $pctTxt = static fn(float $p): string => rtrim(rtrim(number_format($p, 2, '.', ''), '0'), '.') ?: '0';
        ?>
            <div class="card-head">
                <h1>Aprobación de Orden de Compra</h1>
                <p>Revise el detalle y confirme su aprobación.</p>
            </div>
            <div class="card-body">
                <span class="num-badge">N.º <?= $e($o['numero_orden'] ?? '') ?></span>
                <div class="meta">
                    <div><div class="lbl">Proveedor</div><div class="val"><?= $e($o['proveedor_nombre'] ?? '') ?></div></div>
                    <div><div class="lbl">Fecha</div><div class="val"><?= $e(date('d-m-Y', strtotime((string)($o['fecha_orden'] ?? 'now')))) ?></div></div>
                    <div><div class="lbl">Identificación</div><div class="val"><?= $e($o['proveedor_identificacion'] ?? '—') ?></div></div>
                    <div><div class="lbl">Estado</div><div class="val">Enviada, pendiente de aprobación</div></div>
                </div>

                <table class="items">
                    <thead><tr><th>Descripción</th><th class="num">Cant.</th><th class="num">P. Unit.</th><th class="num">IVA</th><th class="num">Subtotal</th></tr></thead>
                    <tbody>
                        <?php foreach (($detalle ?? []) as $d):
                            $cant = (float) ($d['cantidad'] ?? 0);
                            $pu   = (float) ($d['precio_unitario'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <?= $e($d['descripcion'] ?? '') ?>
                                <?php $nota = trim((string) ($d['notas'] ?? '')); if ($nota !== ''): ?>
                                    <div class="item-nota">Nota: <?= $e($nota) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= $e($fmtNum($cant)) ?></td>
                            <td class="num"><?= $e($fmtNum($pu)) ?></td>
                            <td class="num"><?= $e($pctTxt((float) ($d['porcentaje_iva'] ?? 0))) ?>%</td>
                            <td class="num"><?= $e($fmtNum(round($cant * $pu, 2))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="num">Subtotal</td>
                            <td class="num"><?= $e($fmtNum($tot['subtotal'])) ?></td>
                        </tr>
                        <?php foreach ($tot['grupos'] as $g): ?>
                        <tr>
                            <td colspan="4" class="num">Subtotal <?= $e($g['label']) ?></td>
                            <td class="num"><?= $e($fmtNum($g['base'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php foreach ($tot['grupos'] as $g): if ($g['porcentaje'] <= 0) continue; ?>
                        <tr>
                            <td colspan="4" class="num">IVA <?= $e($pctTxt((float) $g['porcentaje'])) ?>%</td>
                            <td class="num"><?= $e($fmtNum($g['iva'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($tot['total_iva'] <= 0): ?>
                        <tr>
                            <td colspan="4" class="num">IVA</td>
                            <td class="num"><?= $e($fmtNum(0)) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tfoot>
                </table>

                <div class="total-box">
                    <span class="t-lbl">TOTAL</span>
                    <span class="t-val">$<?= $e($fmtNum($total)) ?></span>
                </div>

                <form method="POST" action="<?= $e($base) ?>/aprobar-orden-compra/<?= $e($token) ?>/aprobar">
                    <div class="actions">
                        <button type="submit" class="btn btn-ok">✓ Aprobar esta orden de compra</button>
                    </div>
                </form>

                <p class="note">Al aprobar, confirma que está de acuerdo con los productos, cantidades y precios de
                esta orden. Si tiene alguna consulta, puede responder directamente a este correo.</p>
            </div>
        <?php else:
            $tipo = $tipo ?? 'info';
            $ico  = $tipo === 'ok' ? '✅' : ($tipo === 'error' ? '⚠️' : 'ℹ️');
            $titulo = $tipo === 'ok' ? '¡Listo!' : ($tipo === 'error' ? 'No se pudo procesar' : 'Información');
        ?>
            <div class="card-head">
                <h1>Orden de Compra</h1>
            </div>
            <div class="card-body">
                <div class="result">
                    <div class="ico"><?= $ico ?></div>
                    <h2><?= $e($titulo) ?></h2>
                    <p><?= $e($mensaje ?? '') ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="muted">Este enlace es personal. No lo comparta.</div>
</div>
</body>
</html>
