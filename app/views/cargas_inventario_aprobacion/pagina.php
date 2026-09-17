<?php
/** @var string $vista  'detalle' | 'resultado' */
$base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
$e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aprobación de carga de inventario</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: #f1f5f9; color: #1e293b; }
        .wrap { max-width: 720px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.08); overflow: hidden; }
        .card-head { background: #2563eb; color: #fff; padding: 18px 22px; }
        .card-head h1 { font-size: 18px; margin: 0; }
        .card-head p { margin: 4px 0 0; font-size: 13px; opacity: .85; }
        .card-body { padding: 22px; }
        .meta { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px 20px; margin-bottom: 18px; }
        .meta .lbl { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .meta .val { font-size: 15px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 7px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; color: #475569; font-weight: 600; }
        td.num, th.num { text-align: right; }
        .actions { margin-top: 22px; display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { border: none; border-radius: 8px; padding: 11px 22px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-ok { background: #16a34a; color: #fff; }
        .btn-no { background: #fff; color: #dc2626; border: 1px solid #dc2626; }
        textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px; font-family: inherit; font-size: 13px; margin-top: 8px; }
        .rechazo-box { display: none; margin-top: 14px; padding: 14px; background: #fef2f2; border-radius: 8px; }
        .result { text-align: center; padding: 10px 0; }
        .result .ico { font-size: 46px; }
        .result h2 { margin: 12px 0 6px; font-size: 20px; }
        .result p { color: #475569; }
        .muted { color: #94a3b8; font-size: 12px; margin-top: 18px; text-align: center; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <?php if ($vista === 'detalle'):
            $c = $carga;
            // Ajuste por conteo físico: la cantidad es lo contado; se muestra la diferencia que
            // se registraría con el saldo de hoy (al aprobar se recalcula).
            $esAjuste = ($c['tipo_movimiento'] ?? '') === 'ajuste';
            $num = static fn($v): string => rtrim(rtrim(number_format((float) $v, 6, '.', ''), '0'), '.');
        ?>
            <div class="card-head">
                <h1><i></i>Aprobación de carga de inventario</h1>
                <p><?= $e($c['empresa_nombre']) ?></p>
            </div>
            <div class="card-body">
                <div class="meta">
                    <div><div class="lbl">N°</div><div class="val">#<?= $e($c['numero']) ?></div></div>
                    <div><div class="lbl">Tipo</div><div class="val" style="text-transform:capitalize;"><?= $e($c['tipo_movimiento']) ?></div></div>
                    <div><div class="lbl">Fecha</div><div class="val"><?= $c['fecha'] ? $e(date('d-m-Y', strtotime($c['fecha']))) : '-' ?></div></div>
                    <div><div class="lbl">Líneas</div><div class="val"><?= (int) $c['total_lineas'] ?></div></div>
                    <div><div class="lbl">Registrada por</div><div class="val"><?= $e($c['creado_por_nombre']) ?></div></div>
                </div>

                <?php if ($esAjuste): ?>
                    <p style="font-size:13px;color:#475569;margin:0 0 12px;">
                        Ajuste por conteo físico: cada producto quedará con la cantidad contada y solo se
                        registrará la diferencia. El saldo y la diferencia son los de hoy; al aprobar se
                        recalculan con el saldo de ese momento.
                    </p>
                <?php endif; ?>
                <table>
                    <thead><tr>
                        <th>Producto</th><th>Bodega</th>
                        <?php if ($esAjuste): ?>
                            <th class="num">Contado</th><th class="num">Saldo actual</th><th class="num">Diferencia</th>
                        <?php else: ?>
                            <th class="num">Cantidad</th>
                        <?php endif; ?>
                        <th class="num">Costo</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach (($c['detalle'] ?? []) as $d): ?>
                            <tr>
                                <td><?= $e($d['producto_nombre'] ?: $d['cod_producto_raw']) ?><?= !empty($d['numero_lote']) ? ' <span style="color:#64748b;">· lote ' . $e($d['numero_lote']) . '</span>' : '' ?></td>
                                <td><?= $e($d['bodega_nombre'] ?: $d['cod_bodega_raw']) ?></td>
                                <td class="num"><?= $e($num($d['cantidad'])) ?></td>
                                <?php if ($esAjuste): ?>
                                    <td class="num"><?= array_key_exists('saldo_actual', $d) ? $e($num($d['saldo_actual'])) : '-' ?></td>
                                    <td class="num"><?= array_key_exists('diferencia_estimada', $d) ? $e(($d['diferencia_estimada'] > 0 ? '+' : '') . $num($d['diferencia_estimada'])) : '-' ?></td>
                                <?php endif; ?>
                                <td class="num">$ <?= number_format((float) $d['costo_unitario'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="actions">
                    <form method="POST" action="<?= $base ?>/aprobar-carga-inventario/<?= $e($token) ?>/aprobar" onsubmit="return confirm('<?= $esAjuste ? '¿Aprobar este ajuste? Se registrará la diferencia de cada producto con su saldo actual.' : '¿Aprobar esta carga? Se aplicará al inventario.' ?>');">
                        <input type="hidden" name="token" value="<?= $e($token) ?>">
                        <button type="submit" class="btn btn-ok">✓ Aprobar</button>
                    </form>
                    <button type="button" class="btn btn-no" onclick="document.getElementById('rbox').style.display='block';this.style.display='none';">✕ Rechazar</button>
                </div>

                <form method="POST" action="<?= $base ?>/aprobar-carga-inventario/<?= $e($token) ?>/rechazar" class="rechazo-box" id="rbox">
                    <input type="hidden" name="token" value="<?= $e($token) ?>">
                    <label style="font-size:13px;font-weight:600;color:#b91c1c;">Motivo del rechazo</label>
                    <textarea name="motivo" rows="3" required placeholder="Explique por qué rechaza esta carga…"></textarea>
                    <div style="margin-top:10px;"><button type="submit" class="btn btn-no">Confirmar rechazo</button></div>
                </form>

                <p class="muted">El inventario se afecta únicamente cuando la carga es aprobada.</p>
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
