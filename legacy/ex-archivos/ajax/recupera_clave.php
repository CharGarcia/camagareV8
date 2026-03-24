<?php
	if (empty($_POST['cedula'])) {
           $errors[] = "Ingrese cedula";
		} else if (!empty($_POST['cedula'])){
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		// escaping, additionally removing everything that could be (html/javascript-) code
		$cedula=mysqli_real_escape_string($con,(strip_tags($_POST["cedula"],ENT_QUOTES)));
		
		$buscarUsuario = "SELECT * FROM usuarios WHERE cedula = '$cedula' ";
		$result = $con->query($buscarUsuario);
		$count = mysqli_num_rows($result);
		$mail_clave = mysqli_fetch_array($result);
		$mail = $mail_clave['mail'];
		if ($count == 0) {
				$errors []= "La cedula ingresada no existe.".mysqli_error($con);
		}else{
			$nueva_clave = name_file(4);
		    $mensaje = "Su nueva contraseña para ingresar al sistema camagare es: ".$nueva_clave;
		
			$sql="UPDATE usuarios SET password='".md5($nueva_clave)."' where cedula= '".$cedula."'";
		    $query_update = mysqli_query($con,$sql);
			if ($query_update){
		//envia por mail la nueva clave
		$correo = "info@camagare.com";
		//Titulo
		$titulo = "Recuperar contraseña de camarage";
		//cabecera
		$headers = "MIME-Version: 1.0\r\n"; 
		$headers .= "Content-type: text/html; charset=iso-8859-1\r\n"; 
		//dirección del remitente 
		$headers .= "From: camagare < $correo >\r\n";
		//Enviamos el mensaje a tu_dirección_email 
	     $bool = mail($mail,$titulo,$mensaje,$headers);
		if($bool){
			$messages[] = "Su contraseña ha sido enviada a: " . $mail ;
		}else{
			$errors []= "Mensaje no enviado.".mysqli_error($con);
			exit;
		}
				
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
		
			}
			} else {
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
function name_file($n){
	$a = array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","P","Q","R","S","T","U","V","W","X","Y","Z","1","2","3","4","5","6","7","8","9");
	$name = NULL;
	$e = count($a) - 1; //cuenta el número de elementos del arreglo y le resta 1
	for($i=1;$i<=$n;$i++){
		$m = rand(0,$e); //devuelve un número randómico entre 0 y el número de elementos
		$name .= $a[$m];
	}
	return $name;
}
?>