<?php
require 'bootstrap.php';
$db = \App\core\Database::getConnection();
$tables = ['categorias', 'bodegas', 'clientes', 'empleados', 'marcas', 'productos', 'proveedores', 'vehiculos', 'vendedores', 'tipo_medida', 'unidades_medida', 'plan_cuentas'];

$schema = [];
foreach ($tables as $t) {
    $q = $db->query("SELECT column_name, data_type, is_nullable, character_maximum_length FROM information_schema.columns WHERE table_schema='public' AND table_name='$t' ORDER BY ordinal_position");
    $cols = [];
    while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
        $cols[] = $r;
    }
    $schema[$t] = $cols;
}
file_put_contents('schema.json', json_encode($schema, JSON_PRETTY_PRINT));
echo "OK";
