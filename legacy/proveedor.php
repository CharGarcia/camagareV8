<?php
/**
 * Módulo Proveedores - MVC (en desarrollo)
 */
session_start();
if (!isset($_SESSION['ruc_empresa'])) {
    header('Location: /sistema/empresa.php');
    exit;
}
require_once __DIR__ . '/app/bootstrap.php';
include ROOT_PATH . '/head.php';
?>
<title>Proveedores | CaMaGaRe</title>
</head>
<body>
<?php include __DIR__ . '/app/Views/partials/navbar.php'; ?>
<div class="container-fluid">
    <div class="panel panel-info">
        <div class="panel-heading"><h4><i class="glyphicon glyphicon-truck"></i> Proveedores</h4></div>
        <div class="panel-body">
            <p>Módulo en desarrollo.</p>
            <a href="/sistema/empresa.php" class="btn btn-default">Cambiar empresa</a>
        </div>
    </div>
</div>
</body>
</html>
