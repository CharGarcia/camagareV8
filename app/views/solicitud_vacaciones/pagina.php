<?php
/**
 * Página pública (sin login) de la solicitud de vacaciones del empleado.
 * Vista standalone: arma su propio <head>. No lleva partials/csrf.php porque el
 * controlador es público y Application lo exime de CSRF.
 *
 * $vista = 'formulario' | 'resultado'
 */
$base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
$e    = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$fmt  = static fn($ymd) => $ymd ? date('d-m-Y', strtotime((string) $ymd)) : '—';
$dias = static function ($v): string {
    $n = round((float) $v, 2);
    return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
};

$sol    = $solicitud ?? [];
$datos  = $datos ?? [];
$info   = $info ?? null;
$saldo  = $info !== null ? (float) ($info['saldo'] ?? 0) : null;
$hoy    = date('Y-m-d');
$minimo = date('Y-m-d', strtotime('-' . \App\Rules\modulos\VacacionSolicitudRules::DIAS_RETROACTIVOS . ' days'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Solicitud de vacaciones</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px 12px; background: #f4f6f9; font-family: Arial, Helvetica, sans-serif; color: #222; }
        .caja { max-width: 620px; margin: 0 auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.08); overflow: hidden; }
        .cab { background: #0d6efd; color: #fff; padding: 18px 22px; }
        .cab h1 { margin: 0; font-size: 19px; }
        .cab p { margin: 4px 0 0; font-size: 13px; opacity: .9; }
        .cuerpo { padding: 22px; }
        .resumen { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
        .dato { flex: 1 1 140px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 10px 12px; }
        .dato span { display: block; font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: .3px; }
        .dato b { font-size: 16px; }
        .saldo b { color: #0d6efd; }
        label { display: block; font-size: 13px; font-weight: bold; color: #444; margin: 0 0 5px; }
        .campo { margin-bottom: 14px; }
        input[type="date"], input[type="number"], input[type="text"], textarea {
            width: 100%; padding: 9px 10px; border: 1px solid #ced4da; border-radius: 6px;
            font-size: 15px; font-family: inherit; background: #fff; color: #222;
        }
        input:focus, textarea:focus { outline: none; border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,.12); }
        .fila { display: flex; gap: 12px; flex-wrap: wrap; }
        .fila > div { flex: 1 1 150px; }
        .ayuda { font-size: 12px; color: #6c757d; margin: 4px 0 0; }
        .aviso { padding: 11px 14px; border-radius: 8px; font-size: 13px; line-height: 1.5; margin-bottom: 16px; }
        .aviso.error { background: #fdecea; border-left: 4px solid #dc3545; color: #842029; }
        .aviso.info { background: #fff8e1; border-left: 4px solid #ffc107; color: #664d03; }
        .aviso.ok { background: #e8f6ed; border-left: 4px solid #198754; color: #0f5132; }
        button { width: 100%; padding: 13px; background: #0d6efd; color: #fff; border: 0; border-radius: 8px;
                 font-size: 16px; font-weight: bold; cursor: pointer; }
        button:hover { background: #0b5ed7; }
        .pie { padding: 14px 22px; background: #f8f9fa; border-top: 1px solid #e9ecef; font-size: 11px; color: #888; }
        .icono { font-size: 42px; line-height: 1; margin-bottom: 10px; }
        .centro { text-align: center; padding: 34px 22px; }
        .centro p { font-size: 15px; line-height: 1.6; margin: 0; }
    </style>
</head>
<body>
<div class="caja">

<?php if (($vista ?? '') === 'formulario'): ?>

    <div class="cab">
        <h1>Solicitud de vacaciones</h1>
        <p><?= $e($sol['empresa_nombre'] ?? '') ?></p>
    </div>

    <div class="cuerpo">
        <?php if (!empty($error)): ?>
            <div class="aviso error"><?= $e($error) ?></div>
        <?php endif; ?>

        <div class="resumen">
            <div class="dato">
                <span>Empleado</span>
                <b style="font-size:14px;"><?= $e($sol['empleado_nombre'] ?? '') ?></b>
            </div>
            <?php if ($info !== null): ?>
                <div class="dato">
                    <span>Antigüedad</span>
                    <b style="font-size:14px;"><?= $e($info['antiguedad'] ?? '—') ?></b>
                </div>
                <div class="dato saldo">
                    <span>Días disponibles</span>
                    <b><?= $dias($saldo) ?></b>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($info !== null && $saldo !== null && $saldo <= 0): ?>
            <div class="aviso info">
                Según nuestros registros no le quedan días de vacaciones disponibles. Puede enviar
                igual su solicitud y Recursos Humanos la revisará.
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $e($base) ?>/solicitud-vacaciones/<?= $e($token ?? '') ?>/enviar" id="frmSol">
            <input type="hidden" name="token" value="<?= $e($token ?? '') ?>">

            <div class="fila">
                <div class="campo">
                    <label for="fecha_desde">Desde *</label>
                    <input type="date" id="fecha_desde" name="fecha_desde" required
                           min="<?= $e($minimo) ?>" value="<?= $e($datos['fecha_desde'] ?? '') ?>">
                </div>
                <div class="campo">
                    <label for="fecha_hasta">Hasta *</label>
                    <input type="date" id="fecha_hasta" name="fecha_hasta" required
                           min="<?= $e($minimo) ?>" value="<?= $e($datos['fecha_hasta'] ?? '') ?>">
                </div>
            </div>

            <div class="campo">
                <label for="dias_solicitados">Días que solicita *</label>
                <input type="number" id="dias_solicitados" name="dias_solicitados" required
                       step="0.5" min="0.5" value="<?= $e($datos['dias_solicitados'] ?? '') ?>">
                <p class="ayuda">Se calcula solo con las fechas. Corríjalo si no toma el rango completo.</p>
            </div>

            <div class="campo">
                <label for="motivo">Motivo (opcional)</label>
                <textarea id="motivo" name="motivo" rows="3" maxlength="500"><?= $e($datos['motivo'] ?? '') ?></textarea>
            </div>

            <div class="campo">
                <label for="contacto">Teléfono de contacto (opcional)</label>
                <input type="text" id="contacto" name="contacto" maxlength="150" value="<?= $e($datos['contacto'] ?? '') ?>">
                <p class="ayuda">Dónde ubicarlo mientras esté de vacaciones.</p>
            </div>

            <button type="submit">Enviar solicitud</button>
        </form>
    </div>

    <div class="pie">Este enlace es personal y sirve una sola vez. No lo comparta.</div>

    <script>
    (function () {
        'use strict';
        var desde = document.getElementById('fecha_desde');
        var hasta = document.getElementById('fecha_hasta');
        var dias  = document.getElementById('dias_solicitados');

        // Los días se sugieren con el rango elegido; el empleado puede corregirlos.
        function calcular() {
            if (!desde.value || !hasta.value) return;
            var d = new Date(desde.value + 'T00:00:00');
            var h = new Date(hasta.value + 'T00:00:00');
            if (isNaN(d) || isNaN(h) || h < d) return;
            var n = Math.round((h - d) / 86400000) + 1;
            dias.value = n;
            dias.max = n;
        }
        desde.addEventListener('change', function () {
            if (hasta.value && hasta.value < desde.value) hasta.value = desde.value;
            hasta.min = desde.value;
            calcular();
        });
        hasta.addEventListener('change', calcular);

        document.getElementById('frmSol').addEventListener('submit', function (ev) {
            if (hasta.value && desde.value && hasta.value < desde.value) {
                ev.preventDefault();
                alert('La fecha hasta no puede ser anterior a la fecha desde.');
                return;
            }
            var b = this.querySelector('button[type="submit"]');
            if (b) { b.disabled = true; b.textContent = 'Enviando...'; }
        });
    })();
    </script>

<?php else: ?>

    <div class="cab">
        <h1>Solicitud de vacaciones</h1>
    </div>
    <div class="centro">
        <div class="icono"><?= ($tipo ?? '') === 'ok' ? '✅' : (($tipo ?? '') === 'error' ? '⚠️' : 'ℹ️') ?></div>
        <p><?= $e($mensaje ?? '') ?></p>
    </div>
    <div class="pie">Puede cerrar esta página.</div>

<?php endif; ?>

</div>
</body>
</html>
