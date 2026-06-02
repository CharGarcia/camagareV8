<?php
require_once __DIR__ . '/../config/database.php';
$db = new PDO('pgsql:host=localhost;dbname=camagarev8;user=postgres;password=postgres');
$q = $db->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'suscripcion_periodicidades'");
print_r($q->fetchAll(PDO::FETCH_ASSOC));
