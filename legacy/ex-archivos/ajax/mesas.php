<?php
include("../conexiones/conectalogin.php");
include("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

//para guardar y editar
	if($action == 'guardar_mesa'){
	if (isset($_POST['id_mesa']) && $_POST['id_mesa']>0 ) {
			if (empty($_POST['nombre_mesa'])) {
           $errors[] = "Ingrese Nombre que le desea asignar a una mesa";
        } else if (!empty($_POST['nombre_mesa'])){
		$nombre=strClean($_POST["nombre_mesa"]);
		$fecha_agregado=date("Y-m-d H:i:s");

	$busca_mesas = mysqli_query($con, "SELECT * FROM mesas WHERE ruc_empresa = '".$ruc_empresa."' and nombre_mesa = '".$nombre."' ");
	 $count = mysqli_num_rows($busca_mesas);
	 if ($count > 0) {
		 $errors []= "El nombre que desea ingresar ya está registrado.".mysqli_error($con);
	 }else{
		$query_update=mysqli_query($con,  "UPDATE mesas SET nombre_mesa='".$nombre."', fecha_agregado='".$fecha_agregado."', id_usuario ='".$id_usuario."' WHERE id_mesa='".$_POST['id_mesa']."'");
		if ($query_update){
					echo "<script>$.notify('Mesa modificada.','success');
					setTimeout(function (){location.reload()}, 1000);
					</script>";
				} else{
					echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
				}				
			}
	}	
	}else{
	if (empty($_POST['nombre_mesa'])) {
           $errors[] = "Ingrese Nombre que le desea asignar a una mesa";
        } else if (!empty($_POST['nombre_mesa'])){
		/* Connect To Database*/
		
		
		// escaping, additionally removing everything that could be (html/javascript-) code
		$nombre=strClean($_POST["nombre_mesa"]);
		$fecha_agregado=date("Y-m-d H:i:s");

	$busca_mesas = mysqli_query($con, "SELECT * FROM mesas WHERE ruc_empresa = '".$ruc_empresa."' and nombre_mesa = '".$nombre."' ");
	 $count = mysqli_num_rows($busca_mesas);
	 if ($count > 0) {
		 $errors []= "El nombre que desea ingresar ya está registrado.".mysqli_error($con);
	 }else{
			$query_new_insert =mysqli_query($con,"INSERT INTO mesas VALUES (null, '".$ruc_empresa."','".$nombre."','".$fecha_agregado."','".$id_usuario."')");
	
		if ($query_new_insert){
					echo "<script>$.notify('Nueva mesa agregada.','success');
					setTimeout(function (){location.reload()}, 1000);
					</script>";
				} else{
					echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
				}				
			}
	}
}
}
		
			
if($action == 'eliminar_mesa'){
		$id_mesa=intval($_GET['id_mesa']);
	$busca_detalle_mesa = mysqli_query($con, "SELECT * FROM detalle_mesas WHERE id_mesa = '".$id_mesa."' ");
	$contar=mysqli_num_rows($busca_detalle_mesa);
	if ($contar>0){
		echo "<script>$.notify('No es posible eliminar, existen registros realizados con esta mesa.','error');
		setTimeout(function (){location.reload()}, 1000);
		</script>";
	}else{

		//eliminar mesas y posiciones
		$elimina_mesa=mysqli_query($con,"DELETE FROM mesas WHERE id_mesa='".$id_mesa."'");
		$elimina_ubicacion_mesa=mysqli_query($con,"DELETE FROM posiciones_mesas WHERE ruc_empresa='".$ruc_empresa."'");
				
			if ($elimina_mesa && $elimina_ubicacion_mesa){
				echo "<script>$.notify('Mesa iliminada.','success');
						setTimeout(function (){location.reload()}, 1000);
						</script>";
			}else {
				echo "<script>$.notify('No es posible eliminar.','error');
						setTimeout(function (){location.reload()}, 1000);
						</script>";
			}
		
	}
}
	
			
if($action == 'buscar_mesas'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $aColumns = array('nombre_mesa');//Columnas de busqueda
		 $sTable = "mesas";
		 $sWhere = "WHERE ruc_empresa ='".  $ruc_empresa ." '";
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE (ruc_empresa ='".  $ruc_empresa ." ' AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND ruc_empresa = '".  $ruc_empresa ." '", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by id_mesa desc";
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
		$reload = '../mesas.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table">
				<tr  class="info">
				<td>Número</td>
				<td>Nombre</td>
				<td class='text-right'>Opciones</td>
				</tr>
<?php
					$n=1;	
					while($p = mysqli_fetch_assoc($query)){
				?>
						<tr>
								<input type="hidden" value="<?php echo $p['nombre_mesa'];?>" id="nombre_mesa<?php echo $p['id_mesa'];?>">

								<td> <?php echo $n ?> </td>
								<td> <?php echo ($p['nombre_mesa']) ?> </td>
						        <td class='col-md-2 text-right'>
								<a href="#" class='btn btn-info' title='Editar mesa' data-toggle="modal" data-target="#mesas" onclick="obtener_datos('<?php echo $p['id_mesa']; ?>')"><i class="glyphicon glyphicon-edit"></i> </a></span>								
								<a href="#" class='btn btn-default' title='Eliminar mesa' onclick="eliminar_mesa('<?php echo $p['id_mesa']; ?>')"><i class="glyphicon glyphicon-trash"></i> </a></span></td>
								</td>
						</tr>
					<?php
					$n++;
					}
				?>
				<tr>
					<td colspan="3" ><span class="pull-right">
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