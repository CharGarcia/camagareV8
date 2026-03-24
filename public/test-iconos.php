<?php
/**
 * Diagnóstico: de dónde se toman los iconos del menú
 * Acceder: http://localhost/sistema/public/test-iconos.php
 * ELIMINAR después de diagnosticar.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

$config = require MVC_CONFIG . '/app.php';
if (session_status() === PHP_SESSION_NONE) {
    session_name($config['session']['name'] ?? 'PHPSESSID');
    session_start();
}

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Diagnóstico: Origen de los iconos del menú</h2>";
echo "<pre style='background:#f5f5f5;padding:15px;overflow:auto;font-size:12px'>";

if (!isset($_SESSION['id_usuario'])) {
    echo "Inicia sesión primero en /sistema/public/\n";
    echo "</pre>";
    exit;
}

$db = \App\core\Database::getConnection();
$idUsuario = (int) $_SESSION['id_usuario'];
$idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
$nivel = (int) ($_SESSION['nivel'] ?? 1);

echo "=== 1. ESTRUCTURA DE TABLAS ===\n\n";

$tablas = ['iconos_fontawesome', 'modulos_menu', 'submodulos_menu'];
foreach ($tablas as $t) {
    $r = @$db->query("DESCRIBE $t");
    if ($r) {
        echo "--- $t ---\n";
        while ($row = $r->fetch_assoc()) {
            echo "  " . ($row['Field'] ?? $row['field']) . " | " . ($row['Type'] ?? '') . "\n";
        }
        echo "\n";
    } else {
        echo "--- $t: ERROR o no existe ---\n\n";
    }
}

echo "=== 2. DATOS EN iconos_fontawesome (primeros 10) ===\n\n";
$r = @$db->query("SELECT * FROM iconos_fontawesome LIMIT 10");
if ($r) {
    $cols = [];
    while ($row = $r->fetch_assoc()) {
        if (empty($cols)) $cols = array_keys($row);
        echo implode(' | ', array_map(fn($c) => $row[$c] ?? '', $cols)) . "\n";
    }
    if (empty($cols)) echo "(tabla vacía)\n";
} else {
    echo "Error al consultar\n";
}

echo "\n=== 3. modulos_menu con sus id_icono (primeros 5) ===\n\n";
$r = @$db->query("SELECT id_modulo, nombre_modulo, id_icono FROM modulos_menu LIMIT 5");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        echo "id_modulo={$row['id_modulo']} | {$row['nombre_modulo']} | id_icono={$row['id_icono']}\n";
    }
} else {
    $r = @$db->query("SELECT id, nombre_modulo, id_icono FROM modulos_menu LIMIT 5");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            echo "id={$row['id']} | {$row['nombre_modulo']} | id_icono={$row['id_icono']}\n";
        }
    } else echo "Error\n";
}

echo "\n=== 4. QUERY DE ICONOS (modulos) - probando joins ===\n\n";
$sql1 = "SELECT mm.id_modulo, mm.id_icono, ico.nombre_icono 
    FROM modulos_menu mm 
    LEFT JOIN iconos_fontawesome ico ON ico.id_icono = mm.id_icono 
    LIMIT 5";
echo "SQL (ico.id_icono = mm.id_icono): $sql1\n";
$r = @$db->query($sql1);
if ($r) {
    echo "Resultado: " . $r->num_rows . " filas\n";
    while ($row = $r->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "FALLO: " . $db->error . "\n";
    echo "\nProbando ico.id = mm.id_icono...\n";
    $sql2 = "SELECT mm.id_modulo, mm.id_icono, ico.nombre_icono 
        FROM modulos_menu mm 
        LEFT JOIN iconos_fontawesome ico ON ico.id = mm.id_icono 
        LIMIT 5";
    $r = @$db->query($sql2);
    if ($r) {
        echo "OK con ico.id. Resultado:\n";
        while ($row = $r->fetch_assoc()) print_r($row);
    } else echo "También falla: " . $db->error . "\n";
}

echo "\n=== 5. RESULTADO DE getModulosConSubmodulos (iconos que llegan al menú) ===\n\n";
try {
    $model = new \App\models\ModuloMenu();
    $menu = $model->getModulosConSubmodulos($idUsuario, $idEmpresa, $nivel);
    foreach ($menu as $m) {
        echo "Módulo: " . ($m['nombre_modulo'] ?? '') . " | icono_modulo=" . ($m['icono_modulo'] ?? '(vacío)') . "\n";
        foreach ($m['submodulos'] ?? [] as $s) {
            echo "  - Sub: " . ($s['nombre_submodulo'] ?? '') . " | icono_submodulo=" . ($s['icono_submodulo'] ?? '(vacío)') . "\n";
        }
    }
    if (empty($menu)) echo "(sin módulos para tu usuario/empresa)\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== 6. FontAwesome en la página ===\n";
echo "El layout carga: @fortawesome/fontawesome-free@6.5.1\n";
echo "Clases válidas: fas fa-folder, far fa-folder, fab fa-*, etc.\n";

echo "\n</pre>";
echo "<p><a href='/sistema/public/home/index'>Volver al inicio</a></p>";
