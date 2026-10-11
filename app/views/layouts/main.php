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
