<?php
/** @var string $vista  'detalle' | 'resultado' */
$base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
$e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aprobación de compra</title>
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
        .total { font-size: 22px; color: #0f172a; }
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
        <?php if ($vista === 'detalle'): $c = $compra;
            $numero = ($c['establecimiento_prov'] ?? '') . '-' . ($c['punto_emision_prov'] ?? '') . '-' . ($c['secuencial_prov'] ?? '');
        ?>
            <div class="card-head">
                <h1>Aprobación de compra</h1>
                <p><?= $e($c['empresa_nombre']) ?></p>
            </div>
            <div class="card-body">
                <div class="meta">
                    <div><div class="lbl">Documento</div><div class="val"><?= $e($numero) ?></div></div>
                    <div><div class="lbl">Fecha de emisión</div><div class="val"><?= !empty($c['fecha_emision']) ? $e(date('d-m-Y', strtotime($c['fecha_emision']))) : '-' ?></div></div>
                    <div><div class="lbl">Proveedor</div><div class="val"><?= $e($c['proveedor_nombre']) ?></div></div>
                    <div><div class="lbl">RUC</div><div class="val"><?= $e($c['proveedor_ruc']) ?></div></div>
                    <div><div class="lbl">Registrada por</div><div class="val"><?= $e($c['creado_por_nombre']) ?></div></div>
                    <div><div class="lbl">Total</div><div class="val total">$ <?= number_format((float) ($c['importe_total'] ?? 0), 2) ?></div></div>
                </div>

                <div class="actions">
                    <form method="POST" action="<?= $base ?>/aprobar-compra/<?= $e($token) ?>/aprobar" onsubmit="return confirm('¿Aprobar esta compra?');">
                        <input type="hidden" name="token" value="<?= $e($token) ?>">
                        <button type="submit" class="btn btn-ok">✓ Aprobar</button>
                    </form>
                    <button type="button" class="btn btn-no" onclick="document.getElementById('rbox').style.display='block';this.style.display='none';">✕ Rechazar</button>
                </div>

                <form method="POST" action="<?= $base ?>/aprobar-compra/<?= $e($token) ?>/rechazar" class="rechazo-box" id="rbox">
                    <input type="hidden" name="token" value="<?= $e($token) ?>">
                    <label style="font-size:13px;font-weight:600;color:#b91c1c;">Motivo del rechazo</label>
                    <textarea name="motivo" rows="3" required placeholder="Explique por qué rechaza esta compra…"></textarea>
                    <div style="margin-top:10px;"><button type="submit" class="btn btn-no">Confirmar rechazo</button></div>
                </form>

                <p class="muted">Mientras la compra esté pendiente no se puede pagar, ni procesar su inventario, ni se genera su asiento contable.</p>
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
