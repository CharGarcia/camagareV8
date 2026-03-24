<?php
require('../validadores/cedula.php');
	if (empty($_POST['nombre'])) {
           $errors[] = "Ingrese nombre";
		} else if (empty($_POST['cedula'])){
			$errors[] = "Ingrese cedula";
		} else if (empty($_POST['mail'])){
			$errors[] = "Ingrese mail";
		} elseif (strlen($_POST['mail']) > 64) {
            $errors[] = "El correo electrónico no puede ser superior a 64 caracteres";
        } elseif (!filter_var($_POST['mail'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Su dirección de correo electrónico no está en un formato de correo electrónico válida";
		} else if (empty($_POST['password'])){
			$errors[] = "Ingrese contraseña";
		} else if (empty($_POST['confirmar_password'])){
			$errors[] = "Confirme contraseña";
		} else if ($_POST['password'] != $_POST['confirmar_password']){
			$errors[] = "La contraseña no coincide";
		} else if (strlen($_POST['password'])<4){
			$errors[] = "Ingrese mínimo 4 caracteres";
		} else if (!empty($_POST['nombre']) 
		&& !empty($_POST['cedula'])
		&& !empty($_POST['mail'])
        && strlen($_POST['mail']) <= 64
        && filter_var($_POST['mail'], FILTER_VALIDATE_EMAIL)
		&& !empty($_POST['password'])){
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		$recaptcha = $_POST["g-recaptcha-response"];
		$url = 'https://www.google.com/recaptcha/api/siteverify';
		$data = array(
			'secret' => '6LdJLE0UAAAAAE2s7Zzf8XhWHYftv6zfPaHo-ZA_',
			'response' => $recaptcha
		);
		$options = array(
			'http' => array (
				'method' => 'POST',
				'content' => http_build_query($data)
			)
		);
		$context  = stream_context_create($options);
		$verify = file_get_contents($url, false, $context);
		$captcha_success = json_decode($verify);
		if ($captcha_success->success) {
		
		// escaping, additionally removing everything that could be (html/javascript-) code
		$nombre=mysqli_real_escape_string($con,(strip_tags($_POST["nombre"],ENT_QUOTES)));
		$cedula=mysqli_real_escape_string($con,(strip_tags($_POST["cedula"],ENT_QUOTES)));
		$tipo_id=mysqli_real_escape_string($con,(strip_tags($_POST["tipo_id"],ENT_QUOTES)));
		$mail=mysqli_real_escape_string($con,(strip_tags($_POST["mail"],ENT_QUOTES)));
		$telefono="0";//mysqli_real_escape_string($con,(strip_tags($_POST["telefono"],ENT_QUOTES)));
		$clave=md5($_POST['password']);
		$buscarUsuario = "SELECT * FROM usuarios WHERE cedula = '".$cedula."' ";
		$result = $con->query($buscarUsuario);
		$count = mysqli_num_rows($result);
		if ($count == 1) {
				$errors []= "El usuario ya está registrado.".mysqli_error($con);
		}else{
			
			$validacedula = validaCedula($cedula);
			if ($tipo_id=='1'){
				if ($validacedula == "cedula correcta"){
                  $messages[] = guarda_usuario($con, $nombre, $cedula, $clave, $mail );
				  echo "<script>
					setTimeout(function () {location.reload()}, 30 * 20);
					</script>";
				}else{
					$errors []= $validacedula . mysqli_error($con);
				}
			}else{
				$messages[] = guarda_usuario($con, $nombre, $cedula, $clave, $mail );
				echo "<script>
					setTimeout(function () {location.reload()}, 30 * 20);
					</script>";
			}
		}
		} else {
			$errors []= "Debe verificarse que no es un robot.";
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
function guarda_usuario($con, $nombre, $cedula, $clave, $mail ){
			$fecha_agregado=date("Y-m-d H:i:s");
			$query_new_insert = mysqli_query($con, "INSERT INTO usuarios VALUES(NULL,'".$nombre."','".$cedula."','".$clave."',1,'".$mail."','Usuario',1,'".$fecha_agregado."','0');");
			//para enviar un correo diciendome que un nuevo usuario se registro		
			$mensaje = "Nuevo usuario registrado en camagare.com: \n " .
			"Nombre: ".strtoupper($nombre)."\n" .
			"Cedula: ".$cedula."\n" .
			"Mail: " . $mail ;
			//desde donde se envia
			$correo = "info@camagare.com";
			//quien recibe el mail
			$mail_uno = "info@camagare.com";
			//Titulo
			$titulo = "Nuevo usuario en CaMaGaRe";
			//cabecera
			$headers = "MIME-Version: 1.0\r\n"; 
			$headers .= "Content-type: text/html; charset=iso-8859-1\r\n"; 
			//dirección del remitente 
			$headers .= "From: CaMaGaRe < $correo >\r\n";
			//Enviamos el mensaje a tu_dirección_email 
			 $envio_registro_mail = mail($mail_uno,$titulo,$mensaje,$headers);
			$envio_registro_mail = mail($mail,$titulo,$mensaje,$headers);			 
					if ($query_new_insert && $envio_registro_mail){		
						return "El usuario ha sido guardado.";
					} else{
						return  "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
					}	
		}
?>