<?php
/**
 * Constancia al representante tras guardar en el portal.
 * Variables: $data = [nombre, empresa_nombre, actualizados, creados, alumnos[]].
 */
$nombre  = htmlspecialchars((string) ($data['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
$empresa = htmlspecialchars((string) ($data['empresa_nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
$act     = (int) ($data['actualizados'] ?? 0);
$cre     = (int) ($data['creados'] ?? 0);
$lista   = array_map(fn($n) => htmlspecialchars((string) $n, ENT_QUOTES, 'UTF-8'), (array) ($data['alumnos'] ?? []));
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#333;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:24px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.08);">
        <tr><td style="background:#198754;padding:20px 28px;color:#ffffff;">
          <h2 style="margin:0;font-size:19px;">Datos actualizados</h2>
        </td></tr>
        <tr><td style="padding:26px 28px;">
          <p style="margin:0 0 14px;font-size:14px;">Hola <b><?= $nombre ?></b>,</p>
          <p style="margin:0 0 14px;font-size:14px;line-height:1.6;">
            Recibimos sus datos<?= $empresa !== '' ? ' en <b>' . $empresa . '</b>' : '' ?>: sus datos de facturación quedaron actualizados<?php
            if ($act > 0) { echo ', ' . $act . ' alumno(s) actualizado(s)'; }
            if ($cre > 0) { echo ', ' . $cre . ' alumno(s) registrado(s)'; } ?>.
          </p>
          <?php if ($lista): ?>
          <ul style="margin:0 0 14px;padding-left:20px;font-size:14px;line-height:1.6;">
            <?php foreach ($lista as $n): ?><li><?= $n ?></li><?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <p style="margin:0;font-size:12px;line-height:1.6;color:#888;">
            Si usted no hizo este cambio, comuníquese de inmediato con la institución.
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
