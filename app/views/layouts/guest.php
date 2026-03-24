<?php $base = BASE_URL; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require MVC_APP . '/views/partials/head.php'; ?>
    <title><?= htmlspecialchars($titulo ?? 'CaMaGaRe') ?> | CaMaGaRe</title>
</head>
<body class="bg-light">
    <main class="container py-5">
        <?= $contenido ?? '' ?>
    </main>
    <?php require MVC_APP . '/views/partials/scripts.php'; ?>
</body>
</html>
