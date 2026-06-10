<?php
// Script para probar las consultas del módulo de IVA y ver los errores reales en pantalla
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../bootstrap.php';
session_start();

$idEmpresa = $_SESSION['id_empresa'] ?? 1;

try {
    $db = \App\core\Database::getConnection();
    echo "Conexion DB OK.<br>";

    // Prueba 1: sri_casilleros_etiquetas
    echo "Probando sri_casilleros_etiquetas... ";
    $sql1 = "SELECT * FROM sri_casilleros_etiquetas WHERE eliminado = false ORDER BY seccion ASC, orden ASC";
    $st1 = $db->query($sql1);
    echo "OK. Registros: " . $st1->rowCount() . "<br>";

    // Prueba 2: ventas_cabecera
    echo "Probando ventas_cabecera... ";
    $sql2 = "SELECT DISTINCT EXTRACT(YEAR FROM fecha_emision) as anio FROM ventas_cabecera WHERE id_empresa = ? AND eliminado = false ORDER BY anio DESC";
    $st2 = $db->prepare($sql2);
    $st2->execute([$idEmpresa]);
    echo "OK. Años encontrados: " . count($st2->fetchAll()) . "<br>";

    // Prueba 3: casilleros_declaracion_sri
    echo "Probando casilleros_declaracion_sri... ";
    $st3 = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'casilleros_declaracion_sri' AND column_name = 'id'");
    echo "OK.<br>";

    // Prueba 4: Cargar Controller (esto validará namespace y requires)
    echo "Probando cargar DeclaracionIvaController... ";
    $controller = new \App\controllers\modulos\DeclaracionIvaController();
    echo "OK.<br>";

    echo "<b>Todo parece estar bien en la base de datos y la carga de clases.</b>";

} catch (\Throwable $e) {
    echo "<b>ERROR DETECTADO:</b> " . $e->getMessage() . "<br>En archivo: " . $e->getFile() . " linea " . $e->getLine();
}
