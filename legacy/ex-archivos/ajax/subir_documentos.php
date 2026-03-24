<?php
/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
//para eliminar una carga
if (isset($_POST['id_documento']) ){
	$id_documento=$_POST['id_documento'];
		$sql_doc = "SELECT * FROM documentos_subidos WHERE id_documento=$id_documento;";
		$result = $con->query($sql_doc);
		$datos_documento = mysqli_fetch_array($result);
		$archivo =$datos_documento['archivo'];

	$sql = "DELETE FROM documentos_subidos WHERE id_documento=$id_documento;";
	if($documento_eliminado = mysqli_query($con,$sql)){
		unlink($archivo); //borra un archivo, recibe el path.
		?>
		<div class="alert alert-success alert-dismissible" role="alert">
			  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			  <strong>Aviso!</strong> Archivo eliminado exitosamente.
			</div>
			<?php
		}else {
			?>
			<div class="alert alert-danger alert-dismissible" role="alert">
			  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			  <strong>Error!</strong> Lo siento algo ha salido mal intenta nuevamente.
			</div>
			<?php 
			
		}
	}
//para subir documentos
	if ((isset($_POST['empresa'])) && (isset($_POST['documento'])) && (isset($_FILES['archivo']))){
		if (empty($_POST['empresa'])) {
           $errors[] = "Seleccione una empresa de la cual va a subir información";
		}else if (empty($_POST['documento'])) {
           $errors[] = "Seleccione un tipo de documento para enviar";
		}else if (empty($_FILES['archivo'])) {
            $errors[] = "Seleccione la foto o documento para enviar";
        }else if (!empty($_POST['empresa'])&& !empty($_POST['documento'])&& !empty($_FILES['archivo'])
		){

		session_start();
		$id_usuario = $_SESSION['id_usuario'];
		// escaping, additionally removing everything that could be (html/javascript-) code
		$empresa=mysqli_real_escape_string($con,(strip_tags($_POST["empresa"],ENT_QUOTES)));
		$documento=mysqli_real_escape_string($con,(strip_tags($_POST["documento"],ENT_QUOTES)));
		$detalle=mysqli_real_escape_string($con,(strip_tags($_POST["detalle"],ENT_QUOTES)));
		$fecha_agregado=date("Y-m-d H:i:s");
			
		$b = explode(".",$_FILES['archivo']['name']); //divide la cadena por el punto y lo guarda en un arreglo
		$e = count($b); //calcula el número de elementos del arreglo b
		$ext_file = strtolower($b[$e-1]); //captura la extensión del archivo.
		$archivo = "../documentos_subidos/" . nombre_archivo(20) . ".". strtolower($ext_file); //crea el path de destino del archivo	
		
		$target_dir="../documentos_subidos/";
		$image_name = time()."_".basename($_FILES["archivo"]["name"]);
		$target_file = $target_dir . $image_name;
		$imageFileType = pathinfo(strtolower($target_file),PATHINFO_EXTENSION);
		$imageFileZise= $_FILES['archivo']['size'];
		
		if(($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" && $imageFileType != "pdf" ) and $imageFileZise>0) {
		$errors[]= "Lo sentimos, sólo se permiten archivos JPG, JPEG, PNG, PDF y GIF".mysqli_error($con);
		}else if ($imageFileZise > 10048576) {//1048576 byte=1MB
		$errors[]= "Lo sentimos, pero el archivo es demasiado grande. Selecciona un archivo de menos de 10MB".mysqli_error($con);
		}else if(!move_uploaded_file($_FILES['archivo']['tmp_name'], $archivo)){
		$errors []= "Error al cargar el documento.".$imageFileType.$ext_file.mysqli_error($con);	
		}else{
			$sql="INSERT INTO documentos_subidos VALUES (null,$id_usuario ,$empresa,'$documento','$archivo','$detalle','$fecha_agregado','$fecha_agregado','PENDIENTE')";
			$query_subir = mysqli_query($con,$sql);
			if ($query_subir){
				//$messages[] = "El documento ha sido enviado correctamente.";
				echo "<script>alert('El documento ha sido enviado correctamente.')</script>";
				echo "<script>window.close();</script>";
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
					<strong>Error! </strong> 
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
	}

function nombre_archivo($n){
	$a = array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z","a","b","c","d","e","f","g","h","i","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z","1","2","3","4","5","6","7","8","9","0");
	$name = NULL;
	$e = count($a) - 1; //cuenta el número de elementos del arreglo y le resta 1
	for($i=1;$i<=$n;$i++){
		$m = rand(0,$e); //devuelve un número randómico entre 0 y el número de elementos
		$name .= $a[$m];
	}
	return $name;
}
?>