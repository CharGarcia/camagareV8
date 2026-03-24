<?php
include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];

//para ver el tipo de reporte de declracione de iva segun el select 
if (isset($_POST['declaracion']) && $_POST['declaracion']=="iva"){
		$tipo_periodo=mysqli_real_escape_string($con,(strip_tags($_POST["tipo_periodo"],ENT_QUOTES)));
		
		switch ($tipo_periodo) {
			case "mensual":
			?>
			<option value="0"selected>Seleccione</option>
			<option value="01">Enero</option>
			<option value="02">Febrero</option>
			<option value="03">Marzo</option>
			<option value="04">Abril</option>
			<option value="05">Mayo</option>
			<option value="06">Junio</option>
			<option value="07">Julio</option>
			<option value="08">Agosto</option>
			<option value="09">Septiembre</option>
			<option value="10">Octubre</option>
			<option value="11">Noviembre</option>
			<option value="12">Diciembre</option>
			<?php
				break;
			case "semestral":
			?>
			<option value="0"selected>Seleccione</option>
			<option value="01">Enero-Junio</option>
			<option value="02">Julio-Diciembre</option>
			<?php
			break;
		}
		
}
?>