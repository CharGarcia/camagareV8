<?php					
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$id_usuario = $_SESSION['id_usuario'];
		ini_set('date.timezone','America/Guayaquil');
		$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

	if($action == 'detalle_efectivo'){
		$fecha_caja=mysqli_real_escape_string($con,(strip_tags($_POST["fecha_caja"],ENT_QUOTES)));
		//eliminar registros 
		$query_elimina_registros = mysqli_query($con, "DELETE FROM detalle_efectivo WHERE ruc_empresa='".$ruc_empresa."' and fecha_detalle='".date('Y-m-d',strtotime($fecha_caja))."'"); 

		$billeteCien=mysqli_real_escape_string($con,(strip_tags($_POST["billeteCien"],ENT_QUOTES)));
		$billeteCincincuenta=mysqli_real_escape_string($con,(strip_tags($_POST["billeteCincuenta"],ENT_QUOTES)));
		$billeteVeinte=mysqli_real_escape_string($con,(strip_tags($_POST["billeteVeinte"],ENT_QUOTES)));
		$billeteDiez=mysqli_real_escape_string($con,(strip_tags($_POST["billeteDiez"],ENT_QUOTES)));
		$billeteCinco=mysqli_real_escape_string($con,(strip_tags($_POST["billeteCinco"],ENT_QUOTES)));
		$billeteDos=mysqli_real_escape_string($con,(strip_tags($_POST["billeteDos"],ENT_QUOTES)));
		$billeteUno=mysqli_real_escape_string($con,(strip_tags($_POST["billeteUno"],ENT_QUOTES)));
		
		$monedaCien=mysqli_real_escape_string($con,(strip_tags($_POST["monedaCien"],ENT_QUOTES)));
		$monedaCincuenta=mysqli_real_escape_string($con,(strip_tags($_POST["monedaCincuenta"],ENT_QUOTES)));
		$monedaVeinticinco=mysqli_real_escape_string($con,(strip_tags($_POST["monedaVeinticinco"],ENT_QUOTES)));
		$monedaDiez=mysqli_real_escape_string($con,(strip_tags($_POST["monedaDiez"],ENT_QUOTES)));
		$monedaCinco=mysqli_real_escape_string($con,(strip_tags($_POST["monedaCinco"],ENT_QUOTES)));
		$monedaUno=mysqli_real_escape_string($con,(strip_tags($_POST["monedaUno"],ENT_QUOTES)));

		$insert_billete_cien=mysqli_query($con, "INSERT INTO detalle_efectivo VALUES (null,'".$ruc_empresa."','billete','100','".$billeteCien."','".date('Y-m-d H:i:s', strtotime($fecha_caja))."','".$id_usuario."','".date('Y-m-d')."')");
		$insert_billete_cicuenta=mysqli_query($con, "INSERT INTO detalle_efectivo VALUES (null,'".$ruc_empresa."','billete','50','".$billeteCincincuenta."','".date('Y-m-d H:i:s', strtotime($fecha_caja))."','".$id_usuario."','".date('Y-m-d')."')");
		$insert_billete_veinte=mysqli_query($con, "INSERT INTO detalle_efectivo VALUES (null,'".$ruc_empresa."','billete','20','".$billeteVeinte."','".date('Y-m-d H:i:s', strtotime($fecha_caja))."','".$id_usuario."','".date('Y-m-d')."')");
		$insert_billete_diez=mysqli_query($con, "INSERT INTO detalle_efectivo VALUES (null,'".$ruc_empresa."','billete','10','".$billeteDiez."','".date('Y-m-d H:i:s', strtotime($fecha_caja))."','".$id_usuario."','".date('Y-m-d')."')");
		$insert_billete_cinco=mysqli_query($con, "INSERT INTO detalle_efectivo VALUES (null,'".$ruc_empresa."','billete','5','".$billeteCinco."','".date('Y-m-d H:i:s', strtotime($fecha_caja))."','".$id_usuario."','".date('Y-m-d')."')");
		$insert_billete_dos=mysqli_query($con, "INSERT INTO detalle_efectivo VALUES (null,'".$ruc_empresa."','billete','2','".$billeteDos."','".date('Y-m-d H:i:s', strtotime($fecha_caja))."','".$id_usuario."','".date('Y-m-d')."')");
		$insert_billete_uno=mysqli_query($con, "INSERT INTO detalle_efectivo VALUES (null,'".$ruc_empresa."','billete','1','".$billeteUno."','".date('Y-m-d H:i:s', strtotime($fecha_caja))."','".$id_usuario."','".date('Y-m-d')."')");
	
		$insert_moneda_cien=mysqli_query($con, "INSERT INTO detalle_efectivo VALUES (null,'".$ruc_empresa."','moneda','100','".$monedaCien."','".date('Y-m-d H:i:s', strtotime($fecha_caja))."','".$id_usuario."','".date('Y-m-d')."')");
		$insert_moneda_cicuenta=mysqli_query($con, "INSERT INTO detalle_efectivo VALUES (null,'".$ruc_empresa."','moneda','50','".$monedaCincuenta."','".date('Y-m-d H:i:s', strtotime($fecha_caja))."','".$id_usuario."','".date('Y-m-d')."')");
		$insert_moneda_veinticinco=mysqli_query($con, "INSERT INTO detalle_efectivo VALUES (null,'".$ruc_empresa."','moneda','25','".$monedaVeinticinco."','".date('Y-m-d H:i:s', strtotime($fecha_caja))."','".$id_usuario."','".date('Y-m-d')."')");
		$insert_moneda_diez=mysqli_query($con, "INSERT INTO detalle_efectivo VALUES (null,'".$ruc_empresa."','moneda','10','".$monedaDiez."','".date('Y-m-d H:i:s', strtotime($fecha_caja))."','".$id_usuario."','".date('Y-m-d')."')");
		$insert_moneda_cinco=mysqli_query($con, "INSERT INTO detalle_efectivo VALUES (null,'".$ruc_empresa."','moneda','5','".$monedaCinco."','".date('Y-m-d H:i:s', strtotime($fecha_caja))."','".$id_usuario."','".date('Y-m-d')."')");
		$insert_moneda_uno=mysqli_query($con, "INSERT INTO detalle_efectivo VALUES (null,'".$ruc_empresa."','moneda','1','".$monedaUno."','".date('Y-m-d H:i:s', strtotime($fecha_caja))."','".$id_usuario."','".date('Y-m-d')."')");
	
	
		if ($insert_billete_cien){
			echo "<script>
			$.notify('Detalle actualizado.','success');
			</script>";	
		} else{
			echo "<script>
			$.notify('Lo siento algo ha salido mal intenta nuevamente.','error');
			</script>";
		}
	}
	
	if($action == 'entradas_salidas_caja'){

	if (empty($_POST['detalle_entrada_salida'])) {
           $errors[] = "Ingrese un detalle de entrada o salida.";
		}else if (!date($_POST['forma_pago'])) {
           $errors[] = "Seleccione forma de pago";
		}else if (empty($_POST['valor_entrada_salida'])) {
           $errors[] = "Ingrese un valor.";
		}else if (!is_numeric($_POST['valor_entrada_salida'])) {
           $errors[] = "Ingrese valores en el campo valor.";
		}else if (is_numeric($_POST['valor_entrada_salida'])<=0) {
           $errors[] = "Ingrese valores mayor a cero en el campo valor.";
        } else if (!empty($_POST['detalle_entrada_salida']) && !empty($_POST['forma_pago']) && !empty($_POST['valor_entrada_salida']))
		{

			$fecha_caja=mysqli_real_escape_string($con,(strip_tags($_POST["fecha_caja"],ENT_QUOTES)));
			$detalle=mysqli_real_escape_string($con,(strip_tags($_POST["detalle_entrada_salida"],ENT_QUOTES)));
			$forma_pago=mysqli_real_escape_string($con,(strip_tags($_POST["forma_pago"],ENT_QUOTES)));
			$valor=mysqli_real_escape_string($con,(strip_tags($_POST["valor_entrada_salida"],ENT_QUOTES)));
			$tipo_registro=mysqli_real_escape_string($con,(strip_tags($_POST["tipo_registro"],ENT_QUOTES)));

		if ($tipo_registro=='ENTRADA'){
			$entrada=$valor;
		}else{
			$entrada=0;
		}
		
		if ($tipo_registro=='SALIDA'){
			$salida=$valor;
		}else{
			$salida=0;
		}
			
	$insert_detalle_diario=mysqli_query($con, "INSERT INTO detalle_diario_caja VALUES (null,'".$ruc_empresa."','".date('Y-m-d H:i:s', strtotime($fecha_caja))."','".date('Y-m-d H:i:s')."','".$entrada."','".$salida."','".$id_usuario."','MANUAL', '".$detalle."', '".$forma_pago."')");
			
		if ($insert_detalle_diario){
				echo "<script>
				$.notify('Registro realizado.','success');
				</script>";	
			} else{
				echo "<script>
				$.notify('Lo siento algo ha salido mal intenta nuevamente.','error');
				</script>";
			}
		
		}
	}	

if($action == 'eliminar_registro'){
	$id_registro=mysqli_real_escape_string($con,(strip_tags($_GET["id_registro"],ENT_QUOTES)));
	$query_elimina_registros = mysqli_query($con, "DELETE FROM detalle_diario_caja WHERE id_diario_caja='".$id_registro."'"); 
		echo "<script>
		$.notify('Registro eliminado.','error');
		</script>";
	}
	
		if (isset($errors)){
			
			?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Error!</strong> 
					<?php
						foreach ($errors as $error) {
								echo $error;
							}
						?>
			</div>
			<?php
			}
			if (isset($messages)){
				
				?>
				<div class="alert alert-success" role="alert">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						<strong>¡Bien hecho!</strong>
						<?php
							foreach ($messages as $message) {
									echo $message;
								}
							?>
				</div>
				<?php
			}
?>