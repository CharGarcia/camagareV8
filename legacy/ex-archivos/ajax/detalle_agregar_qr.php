<?PHP
	include("../conexiones/conectalogin.php");
	include("../validadores/generador_codigo_unico.php");
	session_start();
	$con = conenta_login();
	$id_usuario = $_SESSION['id_usuario'];
	

$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

//para agregar nuevo detalle tmp
if($action == 'agregar_detalle'){
	$titulo_pestana = $_POST['titulo_pestana'];
	$detalle = $_POST['detalle'];
	$posicion = $_POST['posicion'];
	$posicion_texto = $_POST['posicion_texto'];
		if (!empty($_FILES["imagen"]["name"])){	
			$b = explode(".",$_FILES['imagen']['name']); //divide la cadena por el punto y lo guarda en un arreglo
			$e = count($b); //calcula el número de elementos del arreglo b
			$ext_file = $b[$e-1]; //captura la extensión del archivo.
			$nombre_imagen = codigo_unico(10).".".$ext_file; //crea el path de destino del archivo
			$imagen_final = "../qr/imagenes/".$nombre_imagen;

			$target_dir="../docs_temp/";
			$archivo_name = time()."_".basename($_FILES["imagen"]["name"]);
			$target_file = $target_dir . $archivo_name;

			$imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);

			if ($imageFileType != "jpeg" && $imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "JPG" && $imageFileType != "PNG") {
					echo "<script>
					$.notify('Sólo se permiten imagenes .jpg o .png','error');
					</script>";
				}else if(!move_uploaded_file($_FILES['imagen']['tmp_name'],$imagen_final)){
					echo "<script>
					$.notify('Error al cargar, revise el tipo de imagen','error');
					</script>";				
				}else{
				$detalle_por_guardar = mysqli_query($con, "INSERT INTO existencias_inventario_tmp VALUES (null, '', '".$titulo_pestana."', '".$nombre_imagen."','','','".$posicion_texto."','".$posicion."','','','','".$detalle."', '".$id_usuario."')");
				muestra_detalle_por_guardar_qr();
				}		
			}else{
			$detalle_por_guardar = mysqli_query($con, "INSERT INTO existencias_inventario_tmp VALUES (null, '', '".$titulo_pestana."', '','','','".$posicion_texto."','','','','','".$detalle."', '".$id_usuario."')");
			muestra_detalle_por_guardar_qr();
			}
}
	
//para eliminar un detalle por guardar
if($action == 'eliminar_detalle'){
	$id_registro = $_GET['id_registro'];
	$elimina_imagen = mysqli_query($con, "SELECT * FROM existencias_inventario_tmp WHERE id_existencia_tmp='".$id_registro."'");
	$row = mysqli_fetch_array($elimina_imagen);
	$imagen =$row['nombre_producto'];
	if (!empty($imagen)){
	unlink("../qr/imagenes/".$row['nombre_producto']);
	}
	$elimina_detalle_qr = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='".$id_registro."'");
	muestra_detalle_por_guardar_qr();	
}

//para eliminar todo el registro e un qr
if($action == 'eliminar_qr'){
	$codigo_unico = $_GET['codigo_unico'];
	$elimina_imagen = mysqli_query($con, "SELECT * FROM detalle_qr WHERE codigo_unico='".$codigo_unico."'");
	while ($row = mysqli_fetch_array($elimina_imagen)){
	unlink("../qr/imagenes/".$row['imagen']);
	}
	$elimina_encabezado_qr = mysqli_query($con, "DELETE FROM encabezado_qr WHERE codigo_unico='".$codigo_unico."'");
	$elimina_detalle_qr = mysqli_query($con, "DELETE FROM detalle_qr WHERE codigo_unico='".$codigo_unico."'");
	}

	
function muestra_detalle_por_guardar_qr(){
	$con = conenta_login();
	$id_usuario = $_SESSION['id_usuario'];
	$busca_detalle_qr = mysqli_query($con, "SELECT * FROM existencias_inventario_tmp WHERE id_usuario = '".$id_usuario."' ");
	?>
			<div class="panel-group" id="accordion">
			
				<?php
				while ($detalles = mysqli_fetch_array($busca_detalle_qr)){
					$id_detalle=$detalles['id_existencia_tmp'];
					$pestana=ucfirst($detalles['codigo_producto']);
					$imagen=$detalles['nombre_producto'];
					$posicion_imagen=$detalles['id_medida'];
					$posicion_texto=$detalles['id_bodega'];
					if ($posicion_texto==1){
						$texto="class='text-left'";
					}
					if ($posicion_texto==2){
						$texto="class='text-center'";
					}
					if ($posicion_texto==3){
						$texto="class='text-right'";
					}
					
					$detalle=ucfirst($detalles['lote']);
					$imagen_mostrar = "../qr/imagenes/".$imagen;
				?>
					<div class="panel panel-info">
					<table>
						<td class='col-md-10'>
							<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion" href="#<?php echo $id_detalle?>" ><span class="caret"></span> <?php echo $pestana ?> </a>
							<div id="<?php echo $id_detalle?>" class="panel-collapse collapse">
								<table class="table">									
								  <?php
								  if ($posicion_imagen==0){
									?>
								  <tr  class="info">
									  <td <?php echo $texto ?>><?php echo $detalle ?></td>
								  </tr>
								  <?php
								  }
								  if ($posicion_imagen==1){
								  ?>
								  <tr  class="info">
									  <td class="text-center"><img width="250" height="150" src="<?php echo $imagen_mostrar ?>"></td>
								  </tr>
								  <tr  class="info">
									  <td <?php echo $texto ?>><?php echo $detalle ?></td>
								  </tr>
								 <?php
								  }
								  if ($posicion_imagen==2){
								  ?>
								   <tr  class="info">
									  <td <?php echo $texto ?>><?php echo $detalle ?></td>
								  </tr>
								 <tr  class="info">
									  <td class="text-center"><img width="250" height="150" src="<?php echo $imagen_mostrar ?>"></td>
								  </tr>
								 
								  <?php
								  }
								  ?>
								  
								</table>
							</div>
						</td>
						<td class='col-md-2'>
							<a class='btn btn-danger btn-sm pull-center' href="#" title='Eliminar' onclick="eliminar_detalle_qr('<?php echo $id_detalle; ?>')"><i class="glyphicon glyphicon-trash"></i></a>
						</td>
					</table>
					</div>
				<?php
				}
				?>
			</div>
			
			
<?php	
}
?>