<?php
/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();

		
	if (empty($_POST['mes_periodo'])) {
           $errors[] = "Seleccione mes";
		}else if (empty($_POST['anio_periodo'])) {
           $errors[] = "Seleccione año";
        }else if (!empty($_POST['mes_periodo'])&& !empty($_POST['anio_periodo'])
		){
					
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
		// escaping, additionally removing everything that could be (html/javascript-) code
		$mes_periodo=mysqli_real_escape_string($con,(strip_tags($_POST["mes_periodo"],ENT_QUOTES)));
		$anio_periodo=mysqli_real_escape_string($con,(strip_tags($_POST["anio_periodo"],ENT_QUOTES)));

		 $busca_periodo = "SELECT * FROM periodo_contable WHERE ruc_empresa = '$ruc_empresa' and mes_periodo = '$mes_periodo' and anio_periodo = '$anio_periodo'";
		 $result = $con->query($busca_periodo);
		 $count = mysqli_num_rows($result);
				 if ($count == 1) {
					 $errors []= "El período seleccionado para registro de transacciones, ya esta registrado.".mysqli_error($con);
				 }else{
						$sql="INSERT INTO periodo_contable (id_periodo, mes_periodo, anio_periodo, ruc_empresa) VALUES (null,'$mes_periodo','$anio_periodo','$ruc_empresa')";
						$query_new_insert = mysqli_query($con,$sql);
						if ($query_new_insert){
							$messages[] = "El período ha sido registrado satisfactoriamente.";
						} else{
							$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
						}
					}
		}else {
			$errors []= "Error desconocido.";
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