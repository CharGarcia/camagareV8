<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
		session_start();
		$id_usuario = $_SESSION['id_usuario'];
		$ruc_empresa = $_SESSION['ruc_empresa'];
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

	//guardar marcas
if ($action == 'guardarYeditar_marcas'){
	
	//guardar
	if (empty($_POST['id_marca'])){
	if (empty($_POST['nombre_marca'])){
           $errors[] = "Ingrese nombre de la marca";
		}else if (!empty($_POST['nombre_marca'])){
		$nombre_marca=mysqli_real_escape_string($con,(strip_tags($_POST["nombre_marca"],ENT_QUOTES)));
	//para ver si esta repetido
		 $busca_marcas = "SELECT * FROM marca WHERE ruc_empresa = '".$ruc_empresa."' and nombre_marca = '".$nombre_marca."'";
		 $result = $con->query($busca_marcas);
		 $count = mysqli_num_rows($result);
		 if ($count == 1){
		$errors []= "El nombre de la marca que intenta guardar ya esta registrado.".mysqli_error($con);
		}else{
		
		$sql="INSERT INTO marca VALUES (NULL, '".$ruc_empresa."', '".$nombre_marca."')";
		$query_new_insert = mysqli_query($con,$sql);
			if ($query_new_insert){
				$messages[] = "Nueva marca registrada satisfactoriamente.";	
				echo "<script>setTimeout(function () {location.reload()}, 60 * 20)</script>";	
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
		}
		}else {
			$errors []= "Error desconocido.";
		}
	}
//editar
if (!empty($_POST['id_marca'])){
	if (empty($_POST['nombre_marca'])){
           $errors[] = "Ingrese nombre de la marca que sea modificar";
		}else if (!empty($_POST['nombre_marca'])){
		$nombre_marca=mysqli_real_escape_string($con,(strip_tags($_POST["nombre_marca"],ENT_QUOTES)));
		$id_marca=mysqli_real_escape_string($con,(strip_tags($_POST["id_marca"],ENT_QUOTES)));
	//para ver si esta repetido
		 $busca_marcas = "SELECT * FROM marca WHERE ruc_empresa = '".$ruc_empresa."' and nombre_marca = '".$nombre_marca."'";
				 $result = $con->query($busca_marcas);
				 $count = mysqli_num_rows($result);
				 if ($count == 1){
				$errors []= "El nombre de la marca que intenta guardar ya esta registrado.".mysqli_error($con);
				}else{
		
		$sql="UPDATE marca SET nombre_marca='".$nombre_marca."' WHERE id_marca='".$id_marca."'";
		$query_new_insert = mysqli_query($con,$sql);
			if ($query_new_insert){
				$messages[] = "La marca ha sido modificada satisfactoriamente.";	
				echo "<script>setTimeout(function () {location.reload()}, 60 * 20)</script>";	
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
		}
		}else {
			$errors []= "Error desconocido.";
		}
	}

}
//fin guardar y editar categoria
		
if ($action == 'eliminar_marca'){
	if (!empty($_GET['id_marca'])){
		$id_marca=mysqli_real_escape_string($con,(strip_tags($_GET["id_marca"],ENT_QUOTES)));
		
		$buscar=mysqli_query($con,"SELECT * FROM marca_producto WHERE id_marca = '".$id_marca."'");
		$contar=mysqli_num_rows($buscar);
		if ($contar>0){
		$errors []= "No es posible eliminar, actualmente se utiliza en algunos productos.".mysqli_error($con);
		}else{
		if($delete=mysqli_query($con,"DELETE FROM marca WHERE id_marca = '".$id_marca."'")){
			$messages[] = "La marca ha sido eliminada satisfactoriamente.";	
				echo "<script>setTimeout(function () {location.reload()}, 60 * 20)</script>";	
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
		}
	}
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