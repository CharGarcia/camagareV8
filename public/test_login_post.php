<?php
/**
 * Test: recibe el POST del login y muestra qué pasaría.
 * BORRAR después: rm public/test_login_post.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Test POST login</h2>";
echo "<p><b>Método:</b> " . ($_SERVER['REQUEST_METHOD'] ?? '?') . "</p>";
echo "<p><b>REQUEST_URI:</b> " . ($_SERVER['REQUEST_URI'] ?? '?') . "</p>";
echo "<p><b>POST cedula:</b> " . ($_POST['cedula'] ?? 'no enviado') . "</p>";
echo "<p><b>POST password:</b> " . (isset($_POST['password']) ? '***' : 'no enviado') . "</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cedula'])) {
    require_once dirname(__DIR__) . '/bootstrap.php';
    require_once MVC_APP . '/models/Usuario.php';
    $model = new \App\models\Usuario();
    $user = $model->validaLogin(trim($_POST['cedula']), trim($_POST['password'] ?? ''));
    echo "<p><b>validaLogin:</b> " . ($user ? "OK (id=" . $user['id'] . ")" : "FALLO - usuario/contraseña incorrectos") . "</p>";
    if ($user) {
        $emp = $model->getEmpresasAsignadasParaLogin((int)$user['id']);
        echo "<p><b>Empresas asignadas:</b> " . ($emp['numrows'] ?? 0) . "</p>";
    }
}

echo "<hr><p>Para probar el login real, el formulario debe enviar a: <code>/auth/login</code></p>";
echo "<p>Verifica en DevTools (F12) → Network: a qué URL se envía el POST.</p>";
echo "<p><a href='/'>Volver al login</a></p>";
