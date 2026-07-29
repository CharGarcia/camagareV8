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
    <title>Aprobación de Proforma</title>
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
        .total-box { margin: 18px 0 6px; background:#1f4e79; color:#fff; border-radius:10px; padding:14px 18px; display:flex; justify-content:space-between; align-items:center; }
        .total-box .t-lbl { font-size:13px; opacity:.9; }
        .total-box .t-val { font-size:22px; font-weight:800; }
        label { font-size:13px; font-weight:600; color:#334155; display:block; margin:18px 0 6px; }
        textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 9px; padding: 10px; font-family: inherit; font-size: 14px; min-height: 74px; resize: vertical; }
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
            $p = $prof;
            $numero = ($p['establecimiento'] ?? '') . '-' . ($p['punto_emision'] ?? '') . '-'
                    . str_pad((string) ($p['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
            $dias   = (int) ($p['dias_vigencia'] ?? 15);
            $vence  = date('d-m-Y', strtotime((string) ($p['fecha_emision'] ?? 'now') . " +{$dias} days"));
        ?>
            <div class="card-head">
                <h1>Aprobación de Proforma</h1>
                <p>Revise el detalle y confirme su aprobación.</p>
            </div>
            <div class="card-body">
                <span class="num-badge">N.º <?= $e($numero) ?></span>
                <div class="meta">
                    <div><div class="lbl">Cliente</div><div class="val"><?= $e($p['cliente_nombre'] ?? '') ?></div></div>
                    <div><div class="lbl">Fecha</div><div class="val"><?= $e(date('d-m-Y', strtotime((string)($p['fecha_emision'] ?? 'now')))) ?></div></div>
                    <div><div class="lbl">Válida hasta</div><div class="val"><?= $e($vence) ?></div></div>
                    <div><div class="lbl">Identificación</div><div class="val"><?= $e($p['cliente_ruc'] ?? '—') ?></div></div>
                </div>

                <div class="total-box">
                    <span class="t-lbl">TOTAL</span>
                    <span class="t-val">$<?= $e($fmtNum($p['importe_total'] ?? 0)) ?></span>
                </div>

                <form method="POST" action="<?= $e($base) ?>/aprobar-proforma/<?= $e($token) ?>/aprobar">
                    <label for="comentario">Comentario (opcional)</label>
                    <textarea id="comentario" name="comentario" maxlength="1000" placeholder="Si desea, agregue una nota para la empresa…"></textarea>
                    <div class="actions">
                        <button type="submit" class="btn btn-ok">✓ Aprobar esta proforma</button>
                    </div>
                </form>

                <p class="note">Al aprobar, confirma que está de acuerdo con el detalle y los valores de esta proforma.
                También puede responder directamente al correo si tiene alguna consulta.</p>
            </div>
        <?php else:
            $tipo = $tipo ?? 'info';
            $ico  = $tipo === 'ok' ? '✅' : ($tipo === 'error' ? '⚠️' : 'ℹ️');
            $titulo = $tipo === 'ok' ? '¡Listo!' : ($tipo === 'error' ? 'No se pudo procesar' : 'Información');
        ?>
            <div class="card-head">
                <h1>Proforma</h1>
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
