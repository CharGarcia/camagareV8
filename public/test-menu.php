<?php
/**
 * Script de prueba para diagnosticar el menú de módulos
 * Acceder: http://localhost/sistema/public/test-menu.php
 * ELIMINAR después de las pruebas.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

$config = require MVC_CONFIG . '/app.php';
if (session_status() === PHP_SESSION_NONE) {
    session_name($config['session']['name'] ?? 'PHPSESSID');
    session_start();
}

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Prueba del menú de módulos</h2>";
echo "<pre style='background:#f5f5f5;padding:15px;overflow:auto;'>";

// 1. Sesión
echo "=== 1. SESIÓN ===\n";
if (!isset($_SESSION['id_usuario'])) {
    echo "NO hay sesión. Inicia sesión primero en /sistema/public/\n";
    echo "</pre>";
    exit;
}
echo "id_usuario: " . ($_SESSION['id_usuario'] ?? 'null') . "\n";
echo "id_empresa: " . ($_SESSION['id_empresa'] ?? 'null') . "\n";
echo "nivel: " . ($_SESSION['nivel'] ?? 'null') . "\n";
echo "nombre: " . ($_SESSION['nombre'] ?? 'null') . "\n\n";

$idUsuario = (int) $_SESSION['id_usuario'];
$idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
$nivel = (int) ($_SESSION['nivel'] ?? 1);

if ($idEmpresa <= 0) {
    echo "id_empresa es 0. Selecciona una empresa primero.\n";
    echo "</pre>";
    exit;
}

// 2. Estructura de tablas
echo "=== 2. ESTRUCTURA DE TABLAS ===\n";
try {
    $db = \App\core\Database::getConnection();
    
    $r = $db->query("DESCRIBE modulos_menu");
    echo "modulos_menu columnas: ";
    if ($r) {
        $cols = [];
        while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
        echo implode(', ', $cols) . "\n";
    } else echo "error\n";
    
    $r = $db->query("DESCRIBE submodulos_menu");
    echo "submodulos_menu columnas: ";
    if ($r) {
        $cols = [];
        while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
        echo implode(', ', $cols) . "\n\n";
    } else echo "error\n\n";

// 3. Conteo en tablas
echo "=== 3. REGISTROS EN TABLAS ===\n";
    
    $r = $db->query("SELECT COUNT(*) as n FROM modulos_menu");
    $n = $r ? $r->fetch_assoc()['n'] : 0;
    echo "modulos_menu: $n registros\n";
    
    $r = $db->query("SELECT COUNT(*) as n FROM submodulos_menu");
    $n = $r ? $r->fetch_assoc()['n'] : 0;
    echo "submodulos_menu: $n registros\n";
    
    $r = $db->query("SELECT COUNT(*) as n FROM modulos_asignados WHERE id_usuario=$idUsuario AND id_empresa=$idEmpresa");
    $n = $r ? $r->fetch_assoc()['n'] : 0;
    echo "modulos_asignados (tu usuario+empresa): $n registros\n\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// 4. Query según nivel
echo "=== 4. QUERY EJECUTADA ===\n";
if ($nivel >= 3) {
    $sql = "SELECT mm.id_modulo, mm.nombre_modulo, sm.id_submodulo, sm.nombre_submodulo, sm.ruta
        FROM modulos_menu mm
        LEFT JOIN submodulos_menu sm ON sm.id_modulo = mm.id_modulo
        ORDER BY mm.nombre_modulo, sm.nombre_submodulo";
    echo "Nivel 3: usa modulos_menu + submodulos_menu (todos)\n";
} else {
    $sql = "SELECT mm.id_modulo, mm.nombre_modulo, sm.id_submodulo, sm.nombre_submodulo, sm.ruta
        FROM modulos_asignados ma
        INNER JOIN submodulos_menu sm ON sm.id_submodulo = ma.id_submodulo
        INNER JOIN modulos_menu mm ON mm.id_modulo = ma.id_modulo
        WHERE ma.id_usuario = $idUsuario AND ma.id_empresa = $idEmpresa
        ORDER BY mm.nombre_modulo, sm.nombre_submodulo";
    echo "Nivel 1-2: usa modulos_asignados (solo asignados)\n";
}
echo "SQL: $sql\n\n";

// 5. Resultado raw
echo "=== 5. FILAS DEVUELTAS POR LA QUERY ===\n";
try {
    $result = $db->query($sql);
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    echo "Total filas: " . count($rows) . "\n";
    if (!empty($rows)) {
        print_r(array_slice($rows, 0, 5));
        if (count($rows) > 5) echo "... (mostrando 5 de " . count($rows) . ")\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// 6. Resultado del modelo
echo "\n=== 6. RESULTADO DE getModulosConSubmodulos() ===\n";
try {
    $model = new \App\models\ModuloMenu();
    $menuModulos = $model->getModulosConSubmodulos($idUsuario, $idEmpresa, $nivel);
    echo "Total módulos: " . count($menuModulos) . "\n";
    if (!empty($menuModulos)) {
        foreach ($menuModulos as $m) {
            echo "  - " . ($m['nombre_modulo'] ?? '') . " (" . count($m['submodulos'] ?? []) . " submódulos)\n";
        }
        echo "\nEstructura completa:\n";
        print_r($menuModulos);
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// 7. Después del filtro (solo módulos con submódulos)
echo "\n=== 7. DESPUÉS DEL FILTRO (solo módulos con submódulos) ===\n";
$filtrados = array_values(array_filter($menuModulos ?? [], fn($m) => !empty($m['submodulos'] ?? [])));
echo "Módulos que se mostrarían: " . count($filtrados) . "\n";

echo "\n</pre>";
echo "<p><a href='/sistema/public/home/index'>Volver al inicio</a></p>";
