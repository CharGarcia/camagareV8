<?php
session_start();
if($_SESSION['nivel'] >= 1 && isset($_POST['id_usuario']) && isset($_POST['id_empresa'])){
	$id_usuario = $_POST['id_usuario'];
	$id_empresa = $_POST['id_empresa'];

}else{
header('Location: /sistema/index.php');
exit;
}
?>
