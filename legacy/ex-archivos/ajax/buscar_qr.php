<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
			
	if($action == 'buscar_qr'){
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $aColumns = array('titulo_general');//Columnas de busqueda
		 $sTable = "encabezado_qr";
		 $sWhere = "WHERE id_usuario='".$id_usuario."'";
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE (id_usuario='".$id_usuario."' AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' AND id_usuario='".$id_usuario."' OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND id_usuario='".$id_usuario."' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by id_encabezado_qr asc";
		
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
		$reload = '../codigos_qr.php';
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
					<th>Título general</th>
					<th>Link QR</th>
					<th class='text-right'>Opciones</th>				
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_encabezado_qr=$row['id_encabezado_qr'];
						$codigo_unico=$row['codigo_unico'];
						$titulo_general=ucfirst($row['titulo_general']);
						$link_qr=$row['link_qr'];
					?>
					<tr>						
						<td><?php echo $titulo_general; ?></td>
						<td><a target="_blank" href="miqr.php?codigoqr=<?php echo $codigo_unico ?>"> Mostrar resultado</a></td>
					<td ><span class="pull-right">
					<a target="_blank" href="../qr/genera_qr.php?action=generar_qr&link_qr=<?php echo $link_qr ?>&qr=<?php echo $codigo_unico ?>"  class='btn btn-warning btn-md' title='Generar QR' ><i class="glyphicon glyphicon-qrcode"></i></a> 
					<a href="#" class='btn btn-danger btn-md' title='Eliminar qr' onclick="eliminar_qr('<?php echo $codigo_unico;?>');"><i class="glyphicon glyphicon-trash"></i></a> 	
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan="8"><span class="pull-right">
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