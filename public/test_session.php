<?php
/**
 * Test sesión - BORRAR después
 */
session_name('CMG_SESSION');
session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();

if (isset($_GET['set'])) {
    $_SESSION['test_val'] = 'OK-' . time();
    session_write_close();
    header('Location: /test_session.php');
    exit;
}

header('Content-Type: text/plain');
echo "Session ID: " . session_id() . "\n";
echo "Test value: " . ($_SESSION['test_val'] ?? 'NO definido') . "\n";
echo "Path writable: " . (is_writable(session_save_path()) ? 'SI' : 'NO') . "\n";
if (!isset($_GET['set'])) {
    echo "\nAgregar ?set=1 para escribir sesión y ver si persiste.";
}
