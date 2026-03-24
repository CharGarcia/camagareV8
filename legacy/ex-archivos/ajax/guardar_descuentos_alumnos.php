<?php
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();

$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
if($action == 'eliminar_descuento'){
if (isset($_POST['id_alumno'])) {
			$id_producto=mysqli_real_escape_string($con,(strip_tags($_POST["id_producto"],ENT_QUOTES)));
			$mes_descuento=mysqli_real_escape_string($con,(strip_tags($_POST["mes"],ENT_QUOTES)));
			$anio_descuento=mysqli_real_escape_string($con,(strip_tags($_POST["anio"],ENT_QUOTES)));
			$id_alumno=mysqli_real_escape_string($con,(strip_tags($_POST["id_alumno"],ENT_QUOTES)));
	
			if ($delete=mysqli_query($con,"DELETE FROM descuentos_programados WHERE id_referencia = $id_alumno and id_producto =$id_producto and mes_descuento='$mes_descuento' and anio_descuento ='$anio_descuento'")){
			$messages[] = "Descuento eliminado exitosamente.";
			//echo "<script>alert('Descuento eliminado exitosamente.')</script>";
			//echo "<script>window.close();</script>";
		}else {
		$errors []= "No se eliminó el descuento, intente de nuevo.";
		}
}else{
	$errors []= "Seleccione un registro para eliminar el descuento.";
}
}


if($action == 'guardar_descuento'){		
	if (empty($_POST['mes_descuento'])) {
           $errors[] = "Seleccione mes.";
		}else if (empty($_POST['anio_descuento'])) {
           $errors[] = "Seleccione año.";
		}else if (empty($_POST['aplica_descuento'])) {
           $errors[] = "Seleccione a quien desea aplicar el descuento";
		}else if (empty($_POST['valor_descuento'])) {
           $errors[] = "Ingrese valor de descuento.";
        } else if (!empty($_POST['mes_descuento']) && !empty($_POST['anio_descuento'])  && !empty($_POST['aplica_descuento']) 
		&& !empty($_POST['valor_descuento']))
		{
			$id_producto=mysqli_real_escape_string($con,(strip_tags($_POST["id_producto"],ENT_QUOTES)));
			$mes_descuento=mysqli_real_escape_string($con,(strip_tags($_POST["mes_descuento"],ENT_QUOTES)));
			$anio_descuento=mysqli_real_escape_string($con,(strip_tags($_POST["anio_descuento"],ENT_QUOTES)));
			$valor_descuento=mysqli_real_escape_string($con,(strip_tags($_POST["valor_descuento"],ENT_QUOTES)));
			$fecha_agregado=date("Y-m-d H:i:s");
			session_start();
			$id_usuario= $_SESSION['id_usuario'];
			$ruc_empresa = $_SESSION['ruc_empresa'];
								
			foreach ( $_POST['aplica_descuento'] as $id_descontados ){
			$guarda_descuentos_alumnos=mysqli_query($con, "INSERT INTO descuentos_programados VALUES (null, '$ruc_empresa','$mes_descuento','$anio_descuento',$id_descontados,$id_producto,$valor_descuento,$id_usuario, '$fecha_agregado')");
				}	

			if ($guarda_descuentos_alumnos){
			$messages[] = "Descuentos guardados satisfactoriamente.";
			} else
				{
					$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
				}

			} else {
			$errors []= "Error desconocido.";
			}
}
			
		if (isset($errors))
			{
			?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Atención! </strong> 
					<?php
						foreach ($errors as $error) 
						{
							echo $error;
						}
					?>
			</div>
			<?php
			}
			if (isset($messages))
			{
				
			?>
			<div class="alert alert-success" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>¡Bien hecho! </strong>
					<?php
						foreach ($messages as $message) 
						{
							echo $message;
						}
					?>
			</div>
			<?php
			}
?>