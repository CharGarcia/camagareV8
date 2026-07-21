<?php
/** @var string $vista  'detalle' | 'resultado' */
$base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
$e = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aceptación de documentos legales</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: #f1f5f9; color: #1e293b; }
        .wrap { max-width: 860px; margin: 30px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.08); overflow: hidden; }
        .card-head { background: #2563eb; color: #fff; padding: 18px 22px; }
        .card-head h1 { font-size: 18px; margin: 0; }
        .card-head p { margin: 4px 0 0; font-size: 13px; opacity: .85; }
        .card-body { padding: 22px; }
        .meta { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px 20px; margin-bottom: 18px; }
        .meta .lbl { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .meta .val { font-size: 15px; font-weight: 600; }
        .doc { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 16px; overflow: hidden; }
        .doc-head { background: #f8fafc; padding: 11px 16px; font-size: 14px; font-weight: 700; border-bottom: 1px solid #e2e8f0; }
        .doc-body { padding: 16px; max-height: 320px; overflow-y: auto; font-size: 13px; line-height: 1.6; }
        .doc-body h3 { font-size: 15px; text-align: center; }
        .doc-body p { text-align: justify; }
        .form-box { margin-top: 20px; padding: 18px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; }
        .form-box label { display: block; font-size: 12px; color: #475569; font-weight: 600; margin-bottom: 4px; }
        .form-box input[type=text] { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 10px; font-size: 14px; font-family: inherit; margin-bottom: 12px; }
        .check { display: flex; gap: 9px; align-items: flex-start; font-size: 13px; margin-bottom: 14px; }
        .btn { border: none; border-radius: 8px; padding: 12px 26px; font-size: 15px; font-weight: 700; cursor: pointer; }
        .btn-ok { background: #16a34a; color: #fff; }
        .btn-ok:disabled { background: #94a3b8; cursor: not-allowed; }
        .result { text-align: center; padding: 10px 0; }
        .result .ico { font-size: 46px; }
        .result h2 { margin: 12px 0 6px; font-size: 20px; }
        .result p { color: #475569; line-height: 1.6; }
        .muted { color: #94a3b8; font-size: 12px; margin-top: 18px; text-align: center; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 14px; }
        @media (max-width: 640px) { .meta, .grid2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">

    <?php if (($vista ?? '') === 'detalle'): ?>
        <div class="card-head">
            <h1>Documentos legales para el uso del sistema</h1>
            <p>Revise los documentos y registre su aceptación</p>
        </div>
        <div class="card-body">

            <div class="meta">
                <div>
                    <div class="lbl">Empresa</div>
                    <div class="val"><?= $e($envio['empresa_nombre'] ?? '') ?></div>
                </div>
                <div>
                    <div class="lbl">RUC</div>
                    <div class="val"><?= $e($envio['empresa_ruc'] ?? '') ?></div>
                </div>
            </div>

            <?php foreach (($documentos ?? []) as $doc): ?>
                <div class="doc">
                    <div class="doc-head">
                        <?= $e($doc['titulo'] ?? '') ?>
                        <span style="font-weight:400;color:#64748b;font-size:12px;">· versión <?= (int) ($doc['version'] ?? 1) ?></span>
                    </div>
                    <div class="doc-body"><?= $doc['contenido_resuelto'] ?? '' ?></div>
                </div>
            <?php endforeach; ?>

            <form method="POST" action="<?= $base ?>/aceptar-documentos/<?= $e($token ?? '') ?>/aceptar" class="form-box" id="form-aceptar">
                <input type="hidden" name="token" value="<?= $e($token ?? '') ?>">

                <div class="grid2">
                    <div>
                        <label for="nombre">Nombre de quien acepta *</label>
                        <input type="text" id="nombre" name="nombre" required maxlength="255" placeholder="Nombre y apellido">
                    </div>
                    <div>
                        <label for="identificacion">Cédula / RUC</label>
                        <input type="text" id="identificacion" name="identificacion" maxlength="30" placeholder="Opcional">
                    </div>
                </div>

                <label class="check">
                    <input type="checkbox" id="chk" required>
                    <span>He leído y <b>acepto</b> el Acuerdo de Tratamiento y Uso de Datos Personales y el Contrato de Prestación de Servicios y Uso del Sistema.</span>
                </label>

                <button type="submit" class="btn btn-ok" id="btn-aceptar" disabled>Aceptar documentos</button>
                <p class="muted" style="text-align:left;margin-top:12px;">
                    Al aceptar se registrará la fecha, hora y su dirección IP como constancia.
                </p>
            </form>

        </div>

        <script>
            (function () {
                var chk = document.getElementById('chk');
                var nom = document.getElementById('nombre');
                var btn = document.getElementById('btn-aceptar');
                function check() { btn.disabled = !(chk.checked && nom.value.trim() !== ''); }
                chk.addEventListener('change', check);
                nom.addEventListener('input', check);
                document.getElementById('form-aceptar').addEventListener('submit', function () {
                    btn.disabled = true; btn.textContent = 'Registrando...';
                });
            })();
        </script>

    <?php else: ?>
        <?php
            $tipo = $tipo ?? 'ok';
            $ico  = $tipo === 'ok' ? '✅' : ($tipo === 'info' ? 'ℹ️' : '⚠️');
            $tit  = $tipo === 'ok' ? 'Listo' : ($tipo === 'info' ? 'Aviso' : 'No se pudo continuar');
            $col  = $tipo === 'ok' ? '#16a34a' : ($tipo === 'info' ? '#2563eb' : '#dc2626');
        ?>
        <div class="card-head" style="background: <?= $col ?>;">
            <h1>Documentos legales</h1>
        </div>
        <div class="card-body">
            <div class="result">
                <div class="ico"><?= $ico ?></div>
                <h2><?= $e($tit) ?></h2>
                <p><?= $mensaje ?? '' ?></p>
            </div>
            <p class="muted">Puede cerrar esta ventana.</p>
        </div>
    <?php endif; ?>

    </div>
</div>
</body>
</html>
