<?php
require_once 'C:/xampp/htdocs/sistema/bootstrap.php';
require_once 'C:/xampp/htdocs/sistema/app/core/Database.php';

$pdo = App\core\Database::getConnection();

// Check if any CONTABILIZADO
$sql = "SELECT COUNT(*) as c FROM asientos_contables_cabecera WHERE estado = 'CONTABILIZADO'";
$stmt = $pdo->query($sql);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

// Check any asientes for empresa 1 or 0 or whatever.
$sql = "SELECT id_empresa, COUNT(*) FROM asientos_contables_cabecera GROUP BY id_empresa";
$stmt = $pdo->query($sql);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

// Check any asientes details
$sql = "SELECT COUNT(*) as c FROM asientos_contables_detalle";
$stmt = $pdo->query($sql);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

// Check the fields of asientos_contables_cabecera
$sql = "SELECT * FROM asientos_contables_cabecera LIMIT 1";
$stmt = $pdo->query($sql);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
