<?php
/**
 * Diagnóstico: de dónde se toman los iconos del menú (PostgreSQL)
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
echo '<h2>Diagnóstico: Origen de los iconos del menú</h2>';
echo "<pre style='background:#f5f5f5;padding:15px;overflow:auto;font-size:12px'>";

if (!isset($_SESSION['id_usuario'])) {
    echo "Inicia sesión primero en /sistema/public/\n";
    echo '</pre>';
    exit;
}

$db = \App\core\Database::getConnection();
$idUsuario = (int) $_SESSION['id_usuario'];
$idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
$nivel = (int) ($_SESSION['nivel'] ?? 1);

echo "=== 1. ESTRUCTURA DE TABLAS (information_schema) ===\n\n";

$tablas = ['iconos_fontawesome', 'modulos_menu', 'submodulos_menu'];
$stCol = $db->prepare('SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? ORDER BY ordinal_position');
foreach ($tablas as $t) {
    $stCol->execute([$t]);
    $cols = $stCol->fetchAll(\PDO::FETCH_ASSOC);
    echo "--- $t ---\n";
    if ($cols) {
        foreach ($cols as $row) {
            echo '  ' . ($row['column_name'] ?? '') . ' | ' . ($row['data_type'] ?? '') . "\n";
        }
    } else {
        echo "  (sin columnas o tabla no existe)\n";
    }
    echo "\n";
}

echo "=== 2. DATOS EN iconos_fontawesome (primeros 10) ===\n\n";
try {
    $r = $db->query('SELECT * FROM iconos_fontawesome LIMIT 10');
    if ($r) {
        $cols = [];
        while ($row = $r->fetch(\PDO::FETCH_ASSOC)) {
            if (empty($cols)) {
                $cols = array_keys($row);
            }
            echo implode(' | ', array_map(fn ($c) => $row[$c] ?? '', $cols)) . "\n";
        }
        if (empty($cols)) {
            echo "(tabla vacía)\n";
        }
    }
} catch (Throwable $e) {
    echo 'Error al consultar: ' . $e->getMessage() . "\n";
}

echo "\n=== 3. modulos_menu con sus id_icono (primeros 5) ===\n\n";
try {
    $r = $db->query('SELECT id_modulo, nombre_modulo, id_icono FROM modulos_menu LIMIT 5');
    if ($r) {
        while ($row = $r->fetch(\PDO::FETCH_ASSOC)) {
            echo "id_modulo={$row['id_modulo']} | {$row['nombre_modulo']} | id_icono={$row['id_icono']}\n";
        }
    }
} catch (Throwable $e) {
    try {
        $r = $db->query('SELECT id, nombre_modulo, id_icono FROM modulos_menu LIMIT 5');
        if ($r) {
            while ($row = $r->fetch(\PDO::FETCH_ASSOC)) {
                echo "id={$row['id']} | {$row['nombre_modulo']} | id_icono={$row['id_icono']}\n";
            }
        }
    } catch (Throwable $e2) {
        echo "Error\n";
    }
}

echo "\n=== 4. QUERY DE ICONOS (modulos) - probando joins ===\n\n";
$sql1 = 'SELECT mm.id_modulo, mm.id_icono, ico.nombre_icono 
    FROM modulos_menu mm 
    LEFT JOIN iconos_fontawesome ico ON ico.id_icono = mm.id_icono 
    LIMIT 5';
echo "SQL (ico.id_icono = mm.id_icono): $sql1\n";
try {
    $r = $db->query($sql1);
    if ($r) {
        echo 'Resultado: ' . $r->rowCount() . " filas (rowCount puede ser -1 en algunos drivers)\n";
        while ($row = $r->fetch(\PDO::FETCH_ASSOC)) {
            print_r($row);
        }
    }
} catch (Throwable $e) {
    echo 'FALLO: ' . $e->getMessage() . "\n";
    echo "\nProbando ico.id = mm.id_icono...\n";
    $sql2 = 'SELECT mm.id_modulo, mm.id_icono, ico.nombre_icono 
        FROM modulos_menu mm 
        LEFT JOIN iconos_fontawesome ico ON ico.id = mm.id_icono 
        LIMIT 5';
    try {
        $r = $db->query($sql2);
        if ($r) {
            echo "OK con ico.id. Resultado:\n";
            while ($row = $r->fetch(\PDO::FETCH_ASSOC)) {
                print_r($row);
            }
        }
    } catch (Throwable $e2) {
        echo 'También falla: ' . $e2->getMessage() . "\n";
    }
}

echo "\n=== 5. RESULTADO DE getModulosConSubmodulos (iconos que llegan al menú) ===\n\n";
try {
    $model = new \App\models\ModuloMenu();
    $menu = $model->getModulosConSubmodulos($idUsuario, $idEmpresa, $nivel);
    foreach ($menu as $m) {
        echo 'Módulo: ' . ($m['nombre_modulo'] ?? '') . ' | icono_modulo=' . ($m['icono_modulo'] ?? '(vacío)') . "\n";
        foreach ($m['submodulos'] ?? [] as $s) {
            echo '  - Sub: ' . ($s['nombre_submodulo'] ?? '') . ' | icono_submodulo=' . ($s['icono_submodulo'] ?? '(vacío)') . "\n";
        }
    }
    if (empty($menu)) {
        echo "(sin módulos para tu usuario/empresa)\n";
    }
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}

echo "\n=== 6. FontAwesome en la página ===\n";
echo "El layout carga: @fortawesome/fontawesome-free@6.5.1\n";
echo "Clases válidas: fas fa-folder, far fa-folder, fab fa-*, etc.\n";

echo "\n</pre>";
echo "<p><a href='/sistema/public/home/index'>Volver al inicio</a></p>";
