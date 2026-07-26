<?php
/**
 * Manual completo en una sola página — pensado para imprimir o guardar como PDF
 * desde el navegador (Ctrl+P → "Guardar como PDF"). Página STANDALONE.
 *
 * Se usa el diálogo de impresión del navegador en lugar de generar el PDF en el
 * servidor: el manual crece sin límite y renderizarlo con TCPDF costaría memoria
 * y tiempo en un servidor pequeño, mientras que el navegador ya sabe paginar
 * este HTML perfectamente.
 *
 * El contenido llega ya filtrado por visibilidad desde el servidor: cada usuario
 * imprime únicamente los artículos que puede leer.
 *
 * @var string $titulo
 * @var array  $categorias  [['categoria' => string, 'articulos' => [...]], ...]
 * @var string $empresa
 */
$base = rtrim(BASE_URL ?? '', '/');
$categorias = $categorias ?? [];
$empresa = trim((string) ($empresa ?? ''));

$totalArticulos = 0;
foreach ($categorias as $c) {
    $totalArticulos += count($c['articulos']);
}

/** Ancla única del artículo dentro de esta página. */
$anclaArticulo = static function (string $slug): string {
    return 'art-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($slug));
};
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <?php require MVC_APP . "/views/partials/csrf.php"; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> — completo | CaMaGaRe</title>
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/image/logofinal.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #fff; color: #212529; }
        .mc-wrap { max-width: 900px; margin: 0 auto; padding: 1.5rem 1.25rem 4rem; }
        .mc-barra { position: sticky; top: 0; z-index: 10; background: #fff; border-bottom: 1px solid #dee2e6; }

        .mc-portada { text-align: center; padding: 2rem 0 1.5rem; border-bottom: 2px solid #0d6efd; margin-bottom: 1.5rem; }
        .mc-portada h1 { font-weight: 600; }

        .mc-indice a { text-decoration: none; color: #212529; }
        .mc-indice a:hover { text-decoration: underline; }
        .mc-indice .mc-cat { font-weight: 600; margin-top: .75rem; }

        .mc-categoria { font-size: 1.4rem; font-weight: 600; color: #0d6efd; border-bottom: 1px solid #dee2e6; padding-bottom: .35rem; margin: 2rem 0 1rem; }
        .mc-articulo { margin-bottom: 2.2rem; }
        .mc-articulo > h3 { font-size: 1.2rem; font-weight: 600; margin-bottom: .2rem; }
        .mc-resumen { color: #6c757d; font-size: .92rem; margin-bottom: .8rem; }

        /* Misma tipografía del visor para que lo impreso se lea igual */
        .mc-cuerpo h2 { font-size: 1.08rem; font-weight: 600; margin: 1.3rem 0 .5rem; padding-bottom: .25rem; border-bottom: 1px solid #e9ecef; }
        .mc-cuerpo h3 { font-size: .98rem; font-weight: 600; margin: 1rem 0 .4rem; }
        .mc-cuerpo p, .mc-cuerpo li { line-height: 1.65; }
        .mc-cuerpo table { width: 100%; border-collapse: collapse; margin: .7rem 0; font-size: .87rem; }
        .mc-cuerpo th, .mc-cuerpo td { border: 1px solid #dee2e6; padding: .35rem .5rem; vertical-align: top; }
        .mc-cuerpo th { background: #f8f9fa; text-align: left; }
        .mc-cuerpo code { background: #f1f3f5; padding: .1rem .3rem; border-radius: 4px; font-size: .87em; }
        .mc-cuerpo pre { background: #f8f9fa; border: 1px solid #e9ecef; padding: .6rem; border-radius: 6px; overflow-x: auto; }
        .mc-cuerpo blockquote { border-left: 3px solid #0d6efd; background: #f8f9fa; padding: .4rem .7rem; margin: .7rem 0; }

        @media print {
            .mc-no-print { display: none !important; }
            .mc-wrap { max-width: none; padding: 0; }
            .mc-categoria { page-break-before: always; }
            .mc-categoria:first-of-type { page-break-before: avoid; }
            .mc-articulo { page-break-inside: auto; }
            .mc-articulo > h3, .mc-cuerpo h2, .mc-cuerpo h3 { page-break-after: avoid; }
            .mc-cuerpo table, .mc-cuerpo pre { page-break-inside: avoid; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body>

<div class="mc-barra mc-no-print">
    <div class="mc-wrap d-flex align-items-center justify-content-between gap-2 py-2" style="padding-bottom:.5rem!important;">
        <a href="<?= $base ?>/documentacion" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver al manual
        </a>
        <div class="small text-muted">
            <?= (int) $totalArticulos ?> artículo<?= $totalArticulos === 1 ? '' : 's' ?>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print();">
            <i class="bi bi-printer me-1"></i>Imprimir o guardar en PDF
        </button>
    </div>
</div>

<div class="mc-wrap">
    <!-- Portada -->
    <div class="mc-portada">
        <h1 class="mb-1"><?= htmlspecialchars($titulo) ?></h1>
        <?php if ($empresa !== ''): ?>
        <div class="fs-5 text-muted"><?= htmlspecialchars($empresa) ?></div>
        <?php endif; ?>
        <div class="small text-muted mt-2">Generado el <?= date('d-m-Y H:i:s') ?></div>
    </div>

    <?php if (empty($categorias)): ?>
        <div class="alert alert-secondary">
            No hay artículos disponibles para su usuario.
        </div>
    <?php else: ?>

    <!-- Índice -->
    <div class="mc-indice mb-4">
        <h2 class="h5 fw-semibold">Contenido</h2>
        <?php foreach ($categorias as $cat): ?>
            <div class="mc-cat"><?= htmlspecialchars($cat['categoria']) ?></div>
            <ul class="mb-0">
                <?php foreach ($cat['articulos'] as $art): ?>
                <li><a href="#<?= $anclaArticulo($art['slug']) ?>"><?= htmlspecialchars($art['titulo']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </div>

    <!-- Artículos -->
    <?php foreach ($categorias as $cat): ?>
        <h2 class="mc-categoria"><?= htmlspecialchars($cat['categoria']) ?></h2>

        <?php foreach ($cat['articulos'] as $art): ?>
        <article class="mc-articulo" id="<?= $anclaArticulo($art['slug']) ?>">
            <h3><?= htmlspecialchars($art['titulo']) ?></h3>
            <?php if ($art['resumen'] !== ''): ?>
            <div class="mc-resumen"><?= htmlspecialchars($art['resumen']) ?></div>
            <?php endif; ?>
            <?php // Contenido saneado con HTMLPurifier antes de guardarse (ver DocumentacionService). ?>
            <div class="mc-cuerpo"><?= $art['contenido'] ?></div>
            <?php if ($art['version'] !== ''): ?>
            <div class="small text-muted mt-2">Versión <?= htmlspecialchars($art['version']) ?></div>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <?php endif; ?>
</div>

</body>
</html>
