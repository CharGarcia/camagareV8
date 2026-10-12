<?php $base = BASE_URL; ?>
<?php $menuVertical = ($tipoMenu ?? 'horizontal') === 'vertical'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require MVC_APP . '/views/partials/head.php'; ?>
    <title><?= htmlspecialchars($titulo ?? 'CaMaGaRe') ?> | CaMaGaRe</title>
</head>
<body class="bg-light<?= $menuVertical ? ' cmg-menu-vertical' : '' ?><?= $menuVertical && !empty($sidebarColapsado) ? ' cmg-sidebar-colapsado' : '' ?>">
    <div id="cmg-dropdown-portal"></div>
    <header class="cmg-sticky-header">
        <?php require MVC_APP . '/views/partials/navbar.php'; ?>
        <?php if (!$menuVertical): ?>
        <div class="cmg-menu-wrapper">
            <?php require MVC_APP . '/views/partials/menu-modulos.php'; ?>
        </div>
        <?php endif; ?>
    </header>
    <script>
        // Alto real del header, medido apenas se dibuja y ANTES de pintar el resto: así el
        // sidebar y el contenido aparecen desde el inicio en su lugar, sin el salto de
        // esperar al final de la carga (el 80px de app.css es solo el respaldo). Se vuelve
        // a medir si el header cambia de alto (letra web que termina de cargar, navbar en
        // dos filas, etc.); scripts.php sigue midiendo también al cargar y al redimensionar.
        (function () {
            var h = document.querySelector('.cmg-sticky-header');
            if (!h) return;
            var medir = function () {
                document.documentElement.style.setProperty('--cmg-sticky-h', h.offsetHeight + 'px');
            };
            medir();
            if (window.ResizeObserver) new ResizeObserver(medir).observe(h);
        })();
    </script>
    <?php if ($menuVertical): ?>
        <?php require MVC_APP . '/views/partials/menu-sidebar.php'; ?>
    <?php endif; ?>
    <main class="cmg-main-content">
        <div class="<?= !empty($fullWidth) ? 'container-fluid' : 'container' ?> py-3 py-md-4 px-2 px-md-3">
            <?= $contenido ?? '' ?>
        </div>
    </main>
    <?php require MVC_APP . '/views/partials/scripts.php'; ?>
    <?php require MVC_APP . '/views/partials/contabilidad_auto.php'; ?>
    <?php require MVC_APP . '/views/partials/soporte_widget.php'; ?>
    <?php require MVC_APP . '/views/partials/novedades_modal.php'; ?>
</body>
</html>
