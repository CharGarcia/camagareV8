<?php
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
	if (empty($_POST['titulo_general'])) {
           $errors[] = "Ingrese título general.";
        } else if (!empty($_POST['titulo_general'])){
			//ini_set('date.timezone','America/Guayaquil');
			session_start();
			$id_usuario = $_SESSION['id_usuario'];
			$titulo_general=mysqli_real_escape_string($con,(strip_tags($_POST["titulo_general"],ENT_QUOTES)));
			$codigo_unico = codigo_unico(10);
			$link_qr="www.camagare.com/sistema/qr/miqr.php?codigoqr=" . $codigo_unico;

			$sql_detalle_temporal=mysqli_query($con,"select * from existencias_inventario_tmp WHERE id_usuario = '".$id_usuario."'");
			$count=mysqli_num_rows($sql_detalle_temporal);
		if ($count==0){
		$errors []= " No hay detalle agregado.".mysqli_error($con);
		}else{		

			$guarda_encabezado_qr=mysqli_query($con, "INSERT INTO encabezado_qr VALUES (null,'".$titulo_general."','".$link_qr."','".$codigo_unico."','".$id_usuario."')");

			while ($row_detalle = mysqli_fetch_array($sql_detalle_temporal)){
								$pestana=str_replace(" ", "_", $row_detalle['codigo_producto']);
								$imagen=$row_detalle['nombre_producto'];
								$detalle=$row_detalle['lote'];
								$posicion_texto=$row_detalle['id_bodega'];
								$posicion_imagen=$row_detalle['id_medida'];
				$guarda_detalle_qr = mysqli_query($con, "INSERT INTO detalle_qr VALUES (null,'".$codigo_unico."','".$pestana."','".$detalle."','".$imagen."','".$posicion_texto."','".$posicion_imagen."')");
			}
			
				if ($guarda_encabezado_qr && $guarda_detalle_qr){
				$messages []= "Registro guardado con éxito.";				
				} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.";
				}
			
			}	
		}else{
			$errors []= "Error desconocido.";
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
			
function codigo_unico($n){
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
			
			
			
			