<?php
session_start();
include("../validadores/control_usuarios.php");
control_usuario_salida($_SESSION['id_usuario'],'salida');
unset ($_SESSION['username']);
unset ($_SESSION['id_usuario']);
unset ($_SESSION['ruc_empresa']);
session_destroy();
header('Location: /sistema/index.php');
?>
