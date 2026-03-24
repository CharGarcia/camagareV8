<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
		session_start();
		$id_usuario = $_SESSION['id_usuario'];
		$ruc_empresa = $_SESSION['ruc_empresa'];
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

	if($action == 'buscar_medida'){		
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $aColumns = array('id_medida', 'nombre_medida');//Columnas de busqueda
		 $sTable = "unidad_medida";
		$sWhere = "WHERE ruc_empresa ='".  $ruc_empresa ." '  " ;
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE (ruc_empresa ='".  $ruc_empresa ." ' AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' AND ruc_empresa = '".  $ruc_empresa ."' OR ";
			}
			
			$sWhere = substr_replace( $sWhere, "AND ruc_empresa = '".  $ruc_empresa ."' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by nombre_medida asc ";
		
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 20; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../unidad_medida.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
		<div class="table-responsive">
			<div class="panel panel-info">
			  <table class="table table-hover">
				<tr  class="info">
					<th>id</th>
					<th>Nombre</th>
					<th class='text-right'>Opciones</th>
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_medida=$row['id_medida'];
						$nombre_medida=strtoupper ($row['nombre_medida']);					
					?>					
					<input type="hidden" value="<?php echo $id_medida;?>" id="id_medida<?php echo $id_medida;?>">
					<input type="hidden" value="<?php echo $nombre_medida;?>" id="nombre_medida<?php echo $id_medida;?>">
					<tr>
						<td><?php echo $id_medida; ?></td>
						<td class='col-xs-4'><?php echo $nombre_medida; ?></td>

					<td class='text-right'>
					<a href="#" class='btn btn-info btn-md' title='Editar medida' onclick="obtener_datos('<?php echo $id_medida;?>');" data-toggle="modal" data-target="#UnidadMedida"><i class="glyphicon glyphicon-edit"></i></a> 
					<a href="#" class='btn btn-danger btn-md' title='Eliminar medida' onclick="eliminar_medida('<?php echo $id_medida;?>');"><i class="glyphicon glyphicon-trash"></i></a> 
					</td>
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan=3 ><span class="pull-right">
					<?php
					 echo paginate($reload, $page, $total_pages, $adjacents);
					?></span></td>
				</tr>
			  </table>
			</div>
			</div>
			<?php
		}
	}

	//inicio guardar y editar una unidad de medida
if ($action == 'guardarYeditar_medida'){
	
	//guardar
	if (empty($_POST['id_medida'])){
	if (empty($_POST['nombre_medida'])){
           $errors[] = "Ingrese nombre de la unidad de medida";
		}else if (!empty($_POST['nombre_medida'])){
		$nombre_medida=mysqli_real_escape_string($con,(strip_tags($_POST["nombre_medida"],ENT_QUOTES)));
	//para ver si esta repetido
		 $busca_medida = "SELECT * FROM unidad_medida WHERE ruc_empresa = '$ruc_empresa' and nombre_medida = '$nombre_medida'";
		 $result = $con->query($busca_medida);
		 $count = mysqli_num_rows($result);
		 if ($count == 1){
		$errors []= "El nombre de la unidad de medida que intenta guardar ya esta registrado.".mysqli_error($con);
		}else{
		
		$sql="INSERT INTO unidad_medida VALUES (NULL, '$ruc_empresa', '$nombre_medida')";
		$query_new_insert = mysqli_query($con,$sql);
			if ($query_new_insert){
				$messages[] = "Nueva unidad de medida registrada satisfactoriamente.";	
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
if (!empty($_POST['id_medida'])){
	if (empty($_POST['nombre_medida'])){
           $errors[] = "Ingrese nombre de la unidad de medida que sea modificar";
		}else if (!empty($_POST['nombre_medida'])){
		$nombre_medida=mysqli_real_escape_string($con,(strip_tags($_POST["nombre_medida"],ENT_QUOTES)));
		$id_medida=mysqli_real_escape_string($con,(strip_tags($_POST["id_medida"],ENT_QUOTES)));
	//para ver si esta repetido
		 $busca_medida = "SELECT * FROM unidad_medida WHERE ruc_empresa = '$ruc_empresa' and nombre_medida = '$nombre_medida'";
				 $result = $con->query($busca_medida);
				 $count = mysqli_num_rows($result);
				 if ($count == 1){
				$errors []= "El nombre de la unidad de medida que intenta guardar ya esta registrado.".mysqli_error($con);
				}else{
		
		$sql="UPDATE unidad_medida SET nombre_medida='".$nombre_medida."' WHERE id_medida='".$id_medida."'";
		$query_new_insert = mysqli_query($con,$sql);
			if ($query_new_insert){
				$messages[] = "La unidad de medida ha sido modificada satisfactoriamente.";	
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
//fin guardar y editar unidad de medida

//para eliminar una unidad de medida
if ($action == 'eliminar_medida'){
	if (!empty($_GET['id_medida'])){
	$id_medida=mysqli_real_escape_string($con,(strip_tags($_GET["id_medida"],ENT_QUOTES)));		
	//para ver si esta registrada y no eliminar
		 $busca_medida = "SELECT * FROM inventarios WHERE ruc_empresa = '$ruc_empresa' and id_medida = $id_medida";
				 $result = $con->query($busca_medida);
				 $count = mysqli_num_rows($result);
				 if ($count == 1){
				$errors []= "La unidad de medida que intenta eliminar, esta utilizada en el inventario y no se puede borrar.".mysqli_error($con);
				}else{	
		
		if($delete=mysqli_query($con,"DELETE FROM unidad_medida WHERE id_medida = $id_medida")){
			$messages[] = "La unidad de medida ha sido eliminada satisfactoriamente.";	
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