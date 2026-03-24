<?php $base = BASE_URL; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require MVC_APP . '/views/partials/head.php'; ?>
    <title><?= htmlspecialchars($titulo ?? 'CaMaGaRe') ?> | CaMaGaRe</title>
</head>
<body class="bg-light">
    <div id="cmg-dropdown-portal"></div>
    <header class="cmg-sticky-header">
        <?php require MVC_APP . '/views/partials/navbar.php'; ?>
        <div class="cmg-menu-wrapper">
            <?php require MVC_APP . '/views/partials/menu-modulos.php'; ?>
        </div>
    </header>
    <main class="cmg-main-content">
        <div class="<?= !empty($fullWidth) ? 'container-fluid' : 'container' ?> py-4">
            <?= $contenido ?? '' ?>
        </div>
    </main>
    <?php require MVC_APP . '/views/partials/scripts.php'; ?>
</body>
</html>
