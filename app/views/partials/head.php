<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" type="image/png" href="<?= rtrim(BASE_URL ?? '', '/') ?>/image/logofinal.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css" rel="stylesheet">
<?php require MVC_APP . '/views/partials/theme-vars.php'; ?>
<script>
    window.BASE_URL = '<?= rtrim(BASE_URL ?? '', '/') ?>';
    const BASE_URL = window.BASE_URL;
</script>
<link href="<?= rtrim(BASE_URL ?? '', '/') ?>/css/app.css?v=<?= time() ?>" rel="stylesheet">
<link href="<?= rtrim(BASE_URL ?? '', '/') ?>/css/theme.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
<style>
    /* Prevent horizontal scrolling ("floating paper" effect on mobile) */
    html, body {
        overflow-x: hidden;
        width: 100%;
        position: relative;
    }
</style>
