<?php
//conexion ftp para servidor toncat
function conecta_ftp(){
$host = "64.225.69.65";
$user = "char";
$pass = "CmGr1980";
	$connection = ftp_connect($host);
	$login = ftp_login($connection, $user, $pass);
	if(!$connection || !$login){
		?>
		<div class="alert alert-danger alert-dismissable">
		<strong>Algo pasa!</strong> Error en la conexión ftp!</div>
		 <?php
		//exit;
	}
return $connection;
}
?>
