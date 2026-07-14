<?php
/**
 * Correo de recordatorio de tareas vencidas / por vencer.
 * Variables en scope (desde enviar_correo_recordatorio_tareas): $nombre, $tareas.
 * $tareas = [ ['obligacion','cliente_nombre','fecha_tarea','estado'], ... ]
 */
$nombre = $nombre ?? '';
$tareas = $tareas ?? [];
$hoy = new DateTime('today');

/** Devuelve [texto, color] del estado de una tarea. */
$estadoBadge = static function (array $t) use ($hoy): array {
    if (($t['estado'] ?? '') === 'vencida') {
        return ['Vencida', '#dc3545'];
    }
    $fx = DateTime::createFromFormat('Y-m-d', substr((string) ($t['fecha_tarea'] ?? ''), 0, 10));
    if (!$fx) {
        return [ucfirst((string) ($t['estado'] ?? '')), '#6c757d'];
    }
    $dias = (int) $hoy->diff($fx)->format('%r%a');
    if ($dias < 0)  return ['Vencida', '#dc3545'];
    if ($dias === 0) return ['Vence hoy', '#fd7e14'];
    return ['Vence en ' . $dias . ' día' . ($dias === 1 ? '' : 's'), '#fd7e14'];
};

$total = count($tareas);
?>
<div style="font-family: Arial, Helvetica, sans-serif; color:#2d3748; max-width:640px; margin:0 auto;">
    <h2 style="color:#2b6cb0; margin-bottom:4px;">Recordatorio de tareas</h2>
    <p style="margin:0 0 16px;">
        Hola<?= $nombre !== '' ? ' <strong>' . htmlspecialchars($nombre) . '</strong>' : '' ?>,
        tienes <strong><?= $total ?></strong> tarea<?= $total === 1 ? '' : 's' ?>
        vencida<?= $total === 1 ? '' : 's' ?> o por vencer:
    </p>

    <table cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:13px;">
        <thead>
            <tr style="background:#f7f8fc; text-align:left;">
                <th style="padding:8px; border-bottom:2px solid #e2e8f0;">Obligación</th>
                <th style="padding:8px; border-bottom:2px solid #e2e8f0;">Cliente</th>
                <th style="padding:8px; border-bottom:2px solid #e2e8f0; white-space:nowrap;">Fecha</th>
                <th style="padding:8px; border-bottom:2px solid #e2e8f0; white-space:nowrap;">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tareas as $t): ?>
                <?php
                [$badge, $color] = $estadoBadge($t);
                $fecha = !empty($t['fecha_tarea']) ? date('d-m-Y', strtotime((string) $t['fecha_tarea'])) : '-';
                ?>
                <tr>
                    <td style="padding:8px; border-bottom:1px solid #edf2f7;"><?= htmlspecialchars((string) ($t['obligacion'] ?? '')) ?></td>
                    <td style="padding:8px; border-bottom:1px solid #edf2f7;"><?= htmlspecialchars((string) ($t['cliente_nombre'] ?? '')) ?></td>
                    <td style="padding:8px; border-bottom:1px solid #edf2f7; white-space:nowrap;"><?= $fecha ?></td>
                    <td style="padding:8px; border-bottom:1px solid #edf2f7; white-space:nowrap;">
                        <span style="display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:bold; color:#fff; background:<?= $color ?>;"><?= htmlspecialchars($badge) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin:18px 0 0; font-size:12px; color:#718096;">
        Ingresa al sistema para gestionar tus tareas. Este es un aviso automático; por favor no respondas a este correo.
    </p>
</div>
