<?php
require 'c:/xampp/htdocs/sistema/bootstrap.php';
$pdo = \App\core\Database::getConnection();
$stmt = $pdo->query("SELECT * FROM sri_casilleros_etiquetas ORDER BY seccion, orden");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
