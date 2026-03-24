<?php
include("../conexiones/conectalogin.php");
$conexion = conenta_login();
	$sql = "SELECT * FROM impuestos_transaccion ;";
	$res = mysqli_query($conexion,$sql);
	?> <option value="">Seleccione</option> <?php
	while($o = mysqli_fetch_assoc($res)){
?>
		<option value="<?php echo $o['codigo'] ?> " ><?php echo $o['nombre'] ?> </option>
		<?php
	}
?>
