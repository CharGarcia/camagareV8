<?php
/**
 * @deprecated Usar /sistema/public/auth/cambiar-clave (MVC con bcrypt y migración MD5)
 * Redirige al módulo MVC propio.
 */
session_start();
if (isset($_SESSION['id_usuario']) && $_SESSION['id_usuario'] > 0) {
    header('Location: /sistema/public/auth/cambiar-clave');
    exit;
}
header('Location: ../includes/logout.php');
exit;
