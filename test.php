<?php
require_once __DIR__ . '/bootstrap.php';
$model = new \App\models\SriDescargaAutoLog();
$stmt = $model->db->query('SELECT detalle_json FROM sri_descarga_auto_log ORDER BY id DESC LIMIT 1');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
file_put_contents('scraper_log.json', $row['detalle_json']);
echo "Done.";
