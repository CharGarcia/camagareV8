<?php
/**
 * DEBUG: Recibe POST del login, valida, y muestra qué pasa.
 * Form action temporal para diagnosticar.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

$log = [];
$log[] = "1. Método: " . ($_SERVER['REQUEST_METHOD'] ?? '?');
$log[] = "2. POST cedula: " . (isset($_POST['cedula']) ? 'si' : 'no');
$log[] = "3. POST password: " . (isset($_POST['password']) ? 'si' : 'no');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cedula'])) {
    require_once dirname(__DIR__) . '/bootstrap.php';
    require_once MVC_APP . '/models/Usuario.php';
    
    $cedula = trim($_POST['cedula'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    $model = new \App\models\Usuario();
    $user = $model->validaLogin($cedula, $password);
    
    $log[] = "4. validaLogin: " . ($user ? "OK (id={$user['id']})" : "FALLO");
    
    if ($user) {
        $emp = $model->getEmpresasAsignadasParaLogin((int)$user['id']);
        $num = $emp['numrows'] ?? 0;
        $log[] = "5. Empresas: $num";
        
        if ($num > 0) {
            session_name('CMG_SESSION');
            session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            session_start();
            $_SESSION['id_usuario'] = $user['id'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['nivel'] = $user['nivel'];
            $_SESSION['id_empresa'] = $emp['id_empresa'] ?? 0;
            $_SESSION['ruc_empresa'] = $emp['ruc_empresa'] ?? '';
            session_write_close();
            $log[] = "6. Session guardada. Redirigiendo a /home/index";
            header('Location: /home/index');
            exit;
        }
    }
}

echo "<h3>Debug Login</h3><pre>" . implode("\n", $log) . "</pre>";
echo "<p><a href='/'>Volver al login</a> | Para probar: cambia temporalmente el form action a /log_login.php</p>";
