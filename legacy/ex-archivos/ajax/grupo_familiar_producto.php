<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
		session_start();
		$id_usuario = $_SESSION['id_usuario'];
		$ruc_empresa = $_SESSION['ruc_empresa'];
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

	//guardar 
if ($action == 'guardarYeditar_grupo_familiar_producto'){
	
	//guardar
	if (empty($_POST['id_grupo_familiar_producto'])){
	if (empty($_POST['nombre_grupo_familiar_producto'])){
           $errors[] = "Ingrese nombre del grupo familiar";
		}else if (!empty($_POST['nombre_grupo_familiar_producto'])){
		$nombre_grupo_familiar_producto=mysqli_real_escape_string($con,(strip_tags($_POST["nombre_grupo_familiar_producto"],ENT_QUOTES)));
	//para ver si esta repetido
		 $busca_grupos = "SELECT * FROM grupo_familiar_producto WHERE ruc_empresa = '".$ruc_empresa."' and nombre_grupo = '".$nombre_grupo_familiar_producto."'";
		 $result = $con->query($busca_grupos);
		 $count = mysqli_num_rows($result);
		 if ($count == 1){
		$errors []= "El nombre del grupo familiar que intenta guardar ya esta registrado.".mysqli_error($con);
		}else{
		
		$sql="INSERT INTO grupo_familiar_producto VALUES (NULL, '".$ruc_empresa."', '".$nombre_grupo_familiar_producto."')";
		$query_new_insert = mysqli_query($con,$sql);
			if ($query_new_insert){
				$messages[] = "Nuevo grupo familiar registrado.";	
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
if (!empty($_POST['id_grupo_familiar_producto'])){
	if (empty($_POST['nombre_grupo_familiar_producto'])){
           $errors[] = "Ingrese nombre de la marca que sea modificar";
		}else if (!empty($_POST['nombre_grupo_familiar_producto'])){
		$nombre_grupo_familiar_producto=mysqli_real_escape_string($con,(strip_tags($_POST["nombre_grupo_familiar_producto"],ENT_QUOTES)));
		$id_grupo_familiar_producto=mysqli_real_escape_string($con,(strip_tags($_POST["id_grupo_familiar_producto"],ENT_QUOTES)));
	//para ver si esta repetido
		 $busca_grupo = "SELECT * FROM grupo_familiar_producto WHERE ruc_empresa = '".$ruc_empresa."' and nombre_grupo = '".$nombre_grupo_familiar_producto."'";
				 $result = $con->query($busca_grupo);
				 $count = mysqli_num_rows($result);
				 if ($count == 1){
				$errors []= "El nombre del grupo familiar que intenta guardar ya esta registrado.".mysqli_error($con);
				}else{
		
		$sql="UPDATE grupo_familiar_producto SET nombre_grupo='".$nombre_grupo_familiar_producto."' WHERE id_grupo='".$id_grupo_familiar_producto."'";
		$query_new_insert = mysqli_query($con,$sql);
			if ($query_new_insert){
				$messages[] = "El grupo familiar ha sido modificado.";	
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
//fin guardar y editar
		
if ($action == 'eliminar_grupo_familiar_producto'){
	if (!empty($_GET['id_grupo_familiar_producto'])){
		$id_grupo_familiar_producto=mysqli_real_escape_string($con,(strip_tags($_GET["id_grupo_familiar_producto"],ENT_QUOTES)));
		
		$buscar=mysqli_query($con,"SELECT * FROM grupo_producto_asignado WHERE id_grupo = '".$id_grupo_familiar_producto."'");
		$contar=mysqli_num_rows($buscar);
		if ($contar>0){
		$errors []= "No es posible eliminar, actualmente se utiliza en algunos productos.".mysqli_error($con);
		}else{
		if($delete=mysqli_query($con,"DELETE FROM grupo_familiar_producto WHERE id_grupo = '".$id_grupo_familiar_producto."'")){
			$messages[] = "El grupo familiar ha sido eliminado.";	
				echo "<script>setTimeout(function () {location.reload()}, 60 * 20)</script>";	
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
		}
	}
}	



if($action == 'buscar_grupo_familiar_producto'){		
	$q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$aColumns = array('id_grupo', 'nombre_grupo');//Columnas de busqueda
	$sTable = "grupo_familiar_producto";
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
   $sWhere.=" order by nombre_grupo asc ";
   
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
   $reload = '../grupo_familiar_producto.php';
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
			   <th>Id</th>
			   <th>Nombre</th>
			   <th class='text-right'>Opciones</th>
		   </tr>
		   <?php
		   while ($row=mysqli_fetch_array($query)){
				   $id_grupo=$row['id_grupo'];
				   $nombre_grupo=strtoupper($row['nombre_grupo']);					
			   ?>					
			   <input type="hidden" value="<?php echo $id_grupo;?>" id="id_grupo<?php echo $id_grupo;?>">
			   <input type="hidden" value="<?php echo $nombre_grupo;?>" id="nombre_grupo<?php echo $id_grupo;?>">
			   <tr>
				   <td><?php echo $id_grupo; ?></td>
				   <td class='col-xs-4'><?php echo $nombre_grupo; ?></td>

			   <td class='text-right'>
			   <a href="#" class='btn btn-info btn-sm' title='Editar marca' onclick="obtener_datos('<?php echo $id_grupo;?>');" data-toggle="modal" data-target="#grupo_familiar_producto"><i class="glyphicon glyphicon-edit"></i></a> 
			   <a href="#" class='btn btn-danger btn-sm' title='Eliminar marca' onclick="eliminar_grupo_familiar_producto('<?php echo $id_grupo;?>');"><i class="glyphicon glyphicon-trash"></i></a> 
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