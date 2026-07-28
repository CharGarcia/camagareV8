<?php

/**
 * Cuerpo del correo de invitación (y de recordatorio) a una videollamada.
 * Lo incluye enviar_correo_invitacion_videollamada().
 *
 * @var array $data titulo, descripcion, fecha_texto, anfitrion, codigo, enlace,
 *                  nombre_destinatario, empresa, es_invitado, es_recordatorio
 */

$h = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

$esRecordatorio = !empty($data['es_recordatorio']);
$acento = $esRecordatorio ? '#f0ad4e' : '#0d6efd';
?>
<div style="font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; background:#f4f6f9; padding:24px;">
  <div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.06);">

    <div style="background:<?= $acento ?>; color:#ffffff; padding:18px 24px;">
      <div style="font-size:13px; opacity:.85; text-transform:uppercase; letter-spacing:.5px;">
        <?= $esRecordatorio ? 'Su reunión está por comenzar' : 'Invitación a una videollamada' ?>
      </div>
      <div style="font-size:20px; font-weight:bold; margin-top:4px;"><?= $h($data['titulo']) ?></div>
    </div>

    <div style="padding:24px;">
      <p style="margin:0 0 16px; color:#333; font-size:15px;">
        Hola <?= $h($data['nombre_destinatario'] ?: 'a todos') ?>,
      </p>

      <p style="margin:0 0 20px; color:#555; font-size:14px; line-height:1.5;">
        <?php if ($esRecordatorio): ?>
          Le recordamos que la reunión a la que fue invitado está por comenzar.
        <?php else: ?>
          <?= $h($data['anfitrion']) ?> le invita a una videollamada<?= !empty($data['empresa']) ? ' de ' . $h($data['empresa']) : '' ?>.
        <?php endif; ?>
      </p>

      <?php if (!empty($data['descripcion'])): ?>
        <p style="margin:0 0 20px; color:#555; font-size:14px; line-height:1.5; padding:12px;
                  background:#f8f9fa; border-left:3px solid <?= $acento ?>; border-radius:4px;">
          <?= nl2br($h($data['descripcion'])) ?>
        </p>
      <?php endif; ?>

      <table style="width:100%; font-size:14px; color:#333; border-collapse:collapse; margin-bottom:22px;">
        <?php if (!empty($data['fecha_texto'])): ?>
        <tr>
          <td style="padding:6px 0; color:#888; width:110px;">Cuándo</td>
          <td style="padding:6px 0; font-weight:bold;"><?= $h($data['fecha_texto']) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
          <td style="padding:6px 0; color:#888;">Organiza</td>
          <td style="padding:6px 0;"><?= $h($data['anfitrion']) ?></td>
        </tr>
        <tr>
          <td style="padding:6px 0; color:#888;">Código</td>
          <td style="padding:6px 0; font-family:monospace;"><?= $h($data['codigo']) ?></td>
        </tr>
      </table>

      <div style="text-align:center; margin-bottom:20px;">
        <a href="<?= $h($data['enlace']) ?>"
           style="display:inline-block; background:<?= $acento ?>; color:#ffffff; text-decoration:none;
                  padding:12px 30px; border-radius:6px; font-weight:bold; font-size:15px;">
          Entrar a la reunión
        </a>
      </div>

      <p style="margin:0 0 8px; color:#888; font-size:12px; text-align:center;">
        Si el botón no funciona, copie esta dirección en su navegador:
      </p>
      <p style="margin:0 0 20px; color:#0d6efd; font-size:12px; text-align:center; word-break:break-all;">
        <?= $h($data['enlace']) ?>
      </p>

      <?php if (!empty($data['es_invitado'])): ?>
        <div style="background:#fff8e6; border:1px solid #ffe1a8; border-radius:6px; padding:12px; font-size:12px; color:#7a5b18;">
          <strong>Este enlace es personal.</strong> No lo comparta: da acceso directo a la reunión.
          No necesita crear ninguna cuenta, solo permitir el uso de su cámara y micrófono cuando el
          navegador se lo pida.
        </div>
      <?php endif; ?>
    </div>

    <div style="background:#f8f9fa; padding:14px 24px; font-size:11px; color:#999; text-align:center;">
      Este mensaje se generó automáticamente. No responda a este correo.
    </div>
  </div>
</div>
