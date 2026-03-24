<?php
//para guardar un modulo
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';


if ($action == 'eliminar_submodulos'){
	$id_submodulo = $_GET['id_submodulo'];
	$elimina_submodulos = mysqli_query($con, "DELETE FROM submodulos_menu WHERE id_submodulo='".$id_submodulo."'");
	$elimina_modulos_asignados = mysqli_query($con, "DELETE FROM modulos_asignados WHERE id_submodulo='".$id_submodulo."'");
	$messages[] = "Sub Módulo Eliminado.";
}

if($action == 'guardar_modulo'){
		if (empty($_POST['nombre_modulo'])) {
			   $errors[] = "Ingrese Nombre del módulo";
			}else if (empty($_POST['id_icono'])) {
			   $errors[] = "Seleccione un ícono"; 
			} else if (!empty($_POST['nombre_modulo']) && !empty($_POST['id_icono'])){
			/* Connect To Database*/
			// escaping, additionally removing everything that could be (html/javascript-) code
			$nombre_modulo=mysqli_real_escape_string($con,(strip_tags($_POST["nombre_modulo"],ENT_QUOTES)));
			$id_icono=mysqli_real_escape_string($con,(strip_tags($_POST["id_icono"],ENT_QUOTES)));
			
		$sql = "SELECT nombre_modulo FROM modulos_menu where nombre_modulo ='".$nombre_modulo."'";
		$resp = mysqli_query($con,$sql);
		$total_modulos = mysqli_num_rows($resp);

		if ($total_modulos == 0){
			$sql = "INSERT INTO modulos_menu VALUES(NULL,'".$nombre_modulo."','".$id_icono."');";
		if(mysqli_query($con,$sql)){	
			$messages[] = "Módulo guardado satisfactoriamente.";
		}else{
			$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
		}
		}else{
			$errors []= "El módulo que desea agregar ya esta agregado!.".mysqli_error($con);
		}
		} else {
				$errors []= "Error desconocido.";
			}
	
}
if($action == 'editar_modulo'){
		if (empty($_POST['mod_nombre_modulo'])) {
			   $errors[] = "Ingrese Nombre del módulo";
			}else if (empty($_POST['mod_id_modulo'])) {
			   $errors[] = "Seleccione un módulo"; 
			}else if (empty($_POST['mod_id_icono'])) {
			   $errors[] = "Seleccione un ícono"; 
			} else if (!empty($_POST['mod_nombre_modulo']) && !empty($_POST['mod_id_icono']) && !empty($_POST['mod_id_modulo'])){
			/* Connect To Database*/
			// escaping, additionally removing everything that could be (html/javascript-) code
			$nombre_modulo=mysqli_real_escape_string($con,(strip_tags($_POST["mod_nombre_modulo"],ENT_QUOTES)));
			$id_icono=mysqli_real_escape_string($con,(strip_tags($_POST["mod_id_icono"],ENT_QUOTES)));
			$id_modulo=mysqli_real_escape_string($con,(strip_tags($_POST["mod_id_modulo"],ENT_QUOTES)));
			

		$sql="UPDATE modulos_menu SET nombre_modulo='".$nombre_modulo."',id_icono='".$id_icono."' WHERE id_modulo='".$id_modulo."'";
		if(mysqli_query($con,$sql)){	
			$messages[] = "Módulo modificado satisfactoriamente.";
		}else{
			$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
		}
		
		} else {
				$errors []= "Error desconocido.";
			}
	
}
if($action == 'editar_submodulo'){
		if (empty($_POST['mod_id_submodulo'])) {
			   $errors[] = "Seleccione sub módulo";
			}else if (empty($_POST['mod_id_modulo_sub'])) {
			   $errors[] = "Seleccione un módulo al que desea agregar el sub módulo"; 
			}else if (empty($_POST['mod_nombre_submodulo'])) {
			   $errors[] = "Ingrese nombre del submodulo";
			}else if (empty($_POST['mod_ruta'])) {
			   $errors[] = "Ingrese una ruta";
			}else if (empty($_POST['mod_id_icono_sub'])) {
			   $errors[] = "Seleccione un icono";			   
			} else if (!empty($_POST['mod_id_submodulo']) && !empty($_POST['mod_nombre_submodulo']) && !empty($_POST['mod_id_modulo_sub']) && !empty($_POST['mod_ruta']) && !empty($_POST['mod_id_icono_sub'])){
			/* Connect To Database*/
			// escaping, additionally removing everything that could be (html/javascript-) code
			$mod_id_submodulo=mysqli_real_escape_string($con,(strip_tags($_POST["mod_id_submodulo"],ENT_QUOTES)));
			$mod_id_modulo_sub=mysqli_real_escape_string($con,(strip_tags($_POST["mod_id_modulo_sub"],ENT_QUOTES)));
			$mod_nombre_submodulo=mysqli_real_escape_string($con,(strip_tags($_POST["mod_nombre_submodulo"],ENT_QUOTES)));
			$mod_ruta=mysqli_real_escape_string($con,(strip_tags($_POST["mod_ruta"],ENT_QUOTES)));
			$mod_id_icono_sub=mysqli_real_escape_string($con,(strip_tags($_POST["mod_id_icono_sub"],ENT_QUOTES)));
			
		$sql_submodulos="UPDATE submodulos_menu SET nombre_submodulo='".$mod_nombre_submodulo."',id_icono='".$mod_id_icono_sub."',id_modulo='".$mod_id_modulo_sub."' ,ruta='".$mod_ruta."' WHERE id_submodulo='".$mod_id_submodulo."'";
		
		//consultar el id_modulo del item que quiero modificar
		$sql_modulo = "SELECT * FROM submodulos_menu where id_submodulo ='".$mod_id_submodulo."'";
		$resp_submodulos = mysqli_query($con,$sql_modulo);
		$row_modulos = mysqli_fetch_array($resp_submodulos);
		$id_modulo=$row_modulos['id_modulo'];
		
		$sql_modulos_asignados="UPDATE modulos_asignados SET id_modulo='".$mod_id_modulo_sub."' WHERE id_modulo='".$id_modulo."' and id_submodulo='".$mod_id_submodulo."' ";
		if(mysqli_query($con,$sql_submodulos)&& mysqli_query($con,$sql_modulos_asignados)){	
			$messages[] = "SubMódulo modificado satisfactoriamente.";
		}else{
			$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
		}
		
		} else {
				$errors []= "Error desconocido.";
			}
	
}

if($action == 'guardar_submodulo'){
		if (empty($_POST['id_modulo'])) {
			   $errors[] = "Seleccione módulo donde se va a agregar el submodulo";
			}else if (empty($_POST['nombre_submodulo'])) {
			   $errors[] = "Ingrese nombre del submodulo";
			}else if (empty($_POST['ruta'])) {
			   $errors[] = "Ingrese la ruta";
			}else if (empty($_POST['id_icono'])) {
			   $errors[] = "Seleccione un icono";			   
			} else if (!empty($_POST['id_modulo']) && !empty($_POST['nombre_submodulo']) && !empty($_POST['ruta']) && !empty($_POST['id_icono'])){
			/* Connect To Database*/
			// escaping, additionally removing everything that could be (html/javascript-) code
			$id_modulo=mysqli_real_escape_string($con,(strip_tags($_POST["id_modulo"],ENT_QUOTES)));
			$nombre_submodulo=mysqli_real_escape_string($con,(strip_tags($_POST["nombre_submodulo"],ENT_QUOTES)));
			$ruta=mysqli_real_escape_string($con,(strip_tags($_POST["ruta"],ENT_QUOTES)));
			$id_icono=mysqli_real_escape_string($con,(strip_tags($_POST["id_icono"],ENT_QUOTES)));
			
		$sql = "SELECT nombre_submodulo FROM submodulos_menu where nombre_submodulo ='".$nombre_submodulo."'";
		$resp = mysqli_query($con,$sql);
		$total_submodulos = mysqli_num_rows($resp);

		if ($total_submodulos == 0){
			$sql = "INSERT INTO submodulos_menu VALUES(NULL,'".$nombre_submodulo."','".$ruta."','".$id_modulo."','".$id_icono."');";
		if(mysqli_query($con,$sql)){	
			$messages[] = "Sub Módulo guardado satisfactoriamente.";
		}else{
			$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
		}
		}else{
			$errors []= "El Sub módulo que desea agregar ya esta agregado!.".mysqli_error($con);
		}
		} else {
				$errors []= "Error desconocido.";
			}
	
}

//PARA BUSCAR modulos
if($action == 'mostrar_modulos'){
         $b = mysqli_real_escape_string($con,(strip_tags($_REQUEST['b'], ENT_QUOTES)));
		 $aColumns = array('nombre_modulo');//Columnas de busqueda
		 $sTable = "modulos_menu as mom,  iconos_fontawesome as ibo";
		 $sWhere = "WHERE ibo.id_icono=mom.id_icono ";
		if ( $_GET['b'] != "" )
		{
			$sWhere = "WHERE (ibo.id_icono=mom.id_icono AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$b."%' AND ibo.id_icono=mom.id_icono OR ";
			}
			
			$sWhere = substr_replace( $sWhere, "AND ibo.id_icono=mom.id_icono ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by mom.nombre_modulo asc";
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 10; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../paginas/modulos_submodulos.php';
		//main query to fetch the data
		$sql="SELECT * FROM $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th>Nombre</th>
					<th>icono</th>
					<th>Nombre icono</th>
					<th>Opciones</th>					
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_modulo=$row['id_modulo'];
						$nombre_modulo=$row['nombre_modulo'];
						$nombre_icono=$row['nombre_icono'];
						$id_icono=$row['id_icono'];
						
					?>
					<input type="hidden" value="<?php echo $id_modulo;?>" id="id_modulo<?php echo $id_modulo;?>">
					<input type="hidden" value="<?php echo $nombre_modulo;?>" id="nombre_modulo<?php echo $id_modulo;?>">
					<input type="hidden" value="<?php echo $id_icono;?>" id="id_icono<?php echo $id_modulo;?>">
					<input type="hidden" value="<?php echo $nombre_icono;?>" id="nombre_icono<?php echo $id_modulo;?>">
					<tr>
						<td><?php echo strtoupper ($nombre_modulo); ?></td>
						<td><i class="<?php echo strtoupper ($nombre_icono); ?>"></i></td>
						<td><?php echo strtoupper ($nombre_icono); ?></td>
	
					<td><a href="#"  title='Editar modulo' onclick="obtener_datos_modulo('<?php echo $id_modulo; ?>')" data-toggle="modal" data-target="#editarModulo" class='btn btn-info btn-md' ><i class="glyphicon glyphicon-edit"></i></a></td>
					<?php
				}
				?>
				<tr>
					<td colspan="5" ><span class="pull-right">
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

//PARA BUSCAR modulos
if($action == 'mostrar_submodulos'){
         $b = mysqli_real_escape_string($con,(strip_tags($_REQUEST['b'], ENT_QUOTES)));
		 $aColumns = array('nombre_submodulo');//Columnas de busqueda
		 $sTable = "submodulos_menu as mom,  iconos_fontawesome as ibo";
		 $sWhere = "WHERE ibo.id_icono=mom.id_icono ";
		if ( $_GET['b'] != "" )
		{
			$sWhere = "WHERE (ibo.id_icono=mom.id_icono AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$b."%' AND ibo.id_icono=mom.id_icono OR ";
			}
			
			$sWhere = substr_replace( $sWhere, "AND ibo.id_icono=mom.id_icono ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by mom.nombre_submodulo asc";
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 10; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../paginas/modulos_submodulos.php';
		//main query to fetch the data
		$sql="SELECT * FROM $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th>Módulo</th>
					<th>Submódulo</th>
					<th>icono</th>
					<th>Nombre icono</th>
					<th>Ruta</th>
					<th>Opciones</th>					
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_submodulo=$row['id_submodulo'];
						$id_modulo=$row['id_modulo'];
						$nombre_submodulo=$row['nombre_submodulo'];
						$nombre_icono=$row['nombre_icono'];
						$id_icono=$row['id_icono'];
						$ruta=$row['ruta'];
					//nombre del modulo
					$sql_modulo = "SELECT * FROM modulos_menu where id_modulo='".$id_modulo."'";
					$respuesta_modulo = mysqli_query($con,$sql_modulo);
					$datos_modulos = mysqli_fetch_assoc($respuesta_modulo);
					$nombre_modulo=$datos_modulos['nombre_modulo'];
						
					?>
					<input type="hidden" value="<?php echo $id_submodulo;?>" id="id_submodulo<?php echo $id_submodulo;?>">
					<input type="hidden" value="<?php echo $nombre_submodulo;?>" id="nombre_submodulo<?php echo $id_submodulo;?>">
					<input type="hidden" value="<?php echo $id_icono;?>" id="id_icono_sub<?php echo $id_submodulo;?>">
					<input type="hidden" value="<?php echo $ruta;?>" id="ruta<?php echo $id_submodulo;?>">
					<input type="hidden" value="<?php echo $id_modulo;?>" id="id_modulo_sub<?php echo $id_submodulo;?>">
					<tr>
						<td><?php echo strtoupper ($nombre_modulo); ?></td>
						<td><?php echo strtoupper ($nombre_submodulo); ?></td>
						<td><i class="<?php echo strtoupper ($nombre_icono); ?>"></i></td>
						<td><?php echo strtoupper ($nombre_icono); ?></td>
						<td><?php echo strtoupper ($ruta); ?></td>
	
					<td>
					<a href="#"  title='Editar submodulo' onclick="obtener_datos_submodulo('<?php echo $id_submodulo; ?>')" data-toggle="modal" data-target="#editarSubModulo" class='btn btn-info btn-sm' ><i class="glyphicon glyphicon-edit"></i></a>
					<a href="#" class='btn btn-danger btn-sm' title='Eliminar submodulo' onclick="eliminar_submodulo('<?php echo $id_submodulo;?>');"><i class="glyphicon glyphicon-trash"></i></a>
					</td>
					<?php
				}
				?>
				<tr>
					<td colspan="7" ><span class="pull-right">
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