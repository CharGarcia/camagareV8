<?php
$theme = getThemeConfig();
$primary = $theme['primary']['main'] ?? '#6eb5d0';
$primaryHover = $theme['primary']['hover'] ?? '#5ca3bd';
$bodyStart = $theme['body']['gradient_start'] ?? '#e8f4f8';
$bodyEnd = $theme['body']['gradient_end'] ?? '#f0f7fa';
$bodyAngle = $theme['body']['gradient_angle'] ?? '135deg';
$linksColor = $theme['links']['color'] ?? '#0d6efd';
$linksHover = $theme['links']['hover'] ?? '#0a58ca';
$fontSizeBase = $theme['typography']['font_size_base'] ?? '0.9375rem';
$fontFamily = $theme['typography']['font_family'] ?? 'system-ui, -apple-system, sans-serif';
$radius = $theme['borders']['radius'] ?? '0.375rem';
$radiusSm = $theme['borders']['radius_sm'] ?? '0.25rem';
$radiusLg = $theme['borders']['radius_lg'] ?? '0.5rem';
?>
<style>
:root {
  --cmg-primary: <?= $primary ?>;
  --cmg-primary-hover: <?= $primaryHover ?>;
  --cmg-primary-text: <?= $theme['primary']['text'] ?? '#ffffff' ?>;
  --cmg-body-gradient-start: <?= $bodyStart ?>;
  --cmg-body-gradient-end: <?= $bodyEnd ?>;
  --cmg-body-gradient-angle: <?= $bodyAngle ?>;
  --cmg-link-color: <?= $linksColor ?>;
  --cmg-link-hover: <?= $linksHover ?>;
  --cmg-font-size-base: <?= $fontSizeBase ?>;
  --cmg-font-family: <?= htmlspecialchars($fontFamily) ?>;
  --cmg-border-radius: <?= $radius ?>;
  --cmg-border-radius-sm: <?= $radiusSm ?>;
  --cmg-border-radius-lg: <?= $radiusLg ?>;
}
</style>
