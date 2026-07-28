<?php
/**
 * Correo de aviso: conversaciones de soporte sin atender.
 *
 * Variables disponibles (las inyecta enviar_correo_soporte_pendiente):
 *   $pendientes  array de conversaciones con empresa, usuario, asunto y espera
 *   $urlBandeja  enlace directo a la bandeja
 */
$pendientes = $pendientes ?? [];
$urlBandeja = $urlBandeja ?? '';
?>
<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#212529;max-width:640px;">

    <h2 style="margin:0 0 4px;font-size:18px;color:#0d6efd;">Consultas de soporte sin atender</h2>
    <p style="margin:0 0 16px;color:#6c757d;font-size:13px;">
        Hay <?= count($pendientes) ?> conversaci<?= count($pendientes) === 1 ? 'ón' : 'ones' ?>
        esperando respuesta.
    </p>

    <table cellpadding="8" cellspacing="0" border="0" width="100%"
           style="border-collapse:collapse;border:1px solid #dee2e6;">
        <thead>
            <tr style="background:#f8f9fa;">
                <th align="left" style="border-bottom:1px solid #dee2e6;font-size:12px;">Empresa</th>
                <th align="left" style="border-bottom:1px solid #dee2e6;font-size:12px;">Usuario</th>
                <th align="left" style="border-bottom:1px solid #dee2e6;font-size:12px;">Consulta</th>
                <th align="left" style="border-bottom:1px solid #dee2e6;font-size:12px;">Situación</th>
                <th align="right" style="border-bottom:1px solid #dee2e6;font-size:12px;">Espera</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pendientes as $c): ?>
            <?php $min = (int) round((float) ($c['minutos_espera'] ?? 0)); ?>
            <tr>
                <td style="border-bottom:1px solid #f1f3f5;font-size:13px;">
                    <?= htmlspecialchars((string) ($c['empresa_nombre'] ?? '—')) ?>
                </td>
                <td style="border-bottom:1px solid #f1f3f5;font-size:13px;">
                    <?= htmlspecialchars((string) ($c['usuario_nombre'] ?? '—')) ?>
                </td>
                <td style="border-bottom:1px solid #f1f3f5;font-size:13px;">
                    <?= htmlspecialchars((string) ($c['asunto'] ?? 'Consulta')) ?>
                    <?php if (!empty($c['origen_modulo'])): ?>
                        <br><span style="color:#6c757d;font-size:11px;">
                            <?= htmlspecialchars((string) $c['origen_modulo']) ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td style="border-bottom:1px solid #f1f3f5;font-size:12px;">
                    <?php if (($c['estado'] ?? '') === 'espera'): ?>
                        <span style="color:#b02a37;">Nadie la ha tomado</span>
                    <?php else: ?>
                        <span style="color:#6c757d;">
                            Asignada a <?= htmlspecialchars((string) ($c['agente_nombre'] ?? 'alguien del equipo')) ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td align="right" style="border-bottom:1px solid #f1f3f5;font-size:13px;color:#dc3545;white-space:nowrap;">
                    <?= $min >= 60 ? intdiv($min, 60) . ' h ' . ($min % 60) . ' min' : $min . ' min' ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($urlBandeja !== ''): ?>
    <p style="margin:20px 0 0;">
        <a href="<?= htmlspecialchars($urlBandeja) ?>"
           style="background:#0d6efd;color:#fff;padding:10px 18px;border-radius:6px;
                  text-decoration:none;font-size:14px;display:inline-block;">
            Abrir la bandeja de soporte
        </a>
    </p>
    <?php endif; ?>

    <p style="margin:24px 0 0;color:#adb5bd;font-size:11px;">
        Aviso automático del chat de soporte. Se envía cuando una consulta supera el
        tiempo de espera configurado, y no se repite mientras no haya novedades.
    </p>
</div>
