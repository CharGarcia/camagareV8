<?php
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

//guardar nueva accion del ususario
if($action == 'acciones_menu_empresas'){
	ini_set('date.timezone','America/Guayaquil');
	$origen = mysqli_real_escape_string($con,(strip_tags($_GET['origen'], ENT_QUOTES)));
	$destino = mysqli_real_escape_string($con,(strip_tags($_GET['destino'], ENT_QUOTES)));
	$id_usuario = mysqli_real_escape_string($con,(strip_tags($_GET['id_usuario'], ENT_QUOTES)));
	$ruc_empresa = mysqli_real_escape_string($con,(strip_tags($_GET['ruc_empresa'], ENT_QUOTES)));
	$fecha_registro=date("Y-m-d H:i:s");
	$sql_guardar_accion=mysqli_query($con, "INSERT INTO acciones_usuarios VALUES (null,'".$id_usuario."','".$destino."','".$fecha_registro."','".$ruc_empresa."','".$origen."')");
}

//buscar acciones
if($action == 'buscar_acciones'){
	ini_set('date.timezone','America/Guayaquil');
	 $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['acciones'], ENT_QUOTES)));
		 $aColumns = array('acc.destino','acc.fecha_accion','acc.ruc_empresa','usu.nombre','usu.cedula','emp.nombre','emp.nombre_comercial');//Columnas de busqueda
		 $sTable = "acciones_usuarios as acc INNER JOIN usuarios as usu ON usu.id=acc.id_usuario INNER JOIN empresas as emp ON emp.ruc=acc.ruc_empresa";
		 $sWhere = " ";
		if ( $_GET['acciones'] != "" )
		{
			$sWhere = "WHERE ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' OR ";
			}
			$sWhere = substr_replace( $sWhere, " ", -3 );

		}
		
		$sWhere.=" order by acc.id_accion desc";
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
		$reload = '';
		//main query to fetch the data
		$sql="SELECT usu.nombre as usuario, emp.nombre_comercial as empresa, acc.fecha_accion as fecha_accion, acc.destino as destino, acc.origen as origen  FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th>Usuario</th>
					<th>Empresa</th>
					<th>Origen</th>
					<th>Destino</th>
					<th>Fecha</th>
					
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$usuario=$row['usuario'];
						$empresa=$row['empresa'];
						$origen=$row['origen'];
						$destino=$row['destino'];
						$fecha=$row['fecha_accion'];
					?>
					<tr>
						<td><?php echo strtoupper($usuario); ?></td>
						<td><?php echo strtoupper($empresa); ?></td>
						<td><?php echo $origen; ?></td>
						<td><?php echo $destino; ?></td>
						<td><?php echo $fecha; ?></td>

					</tr>
				<?php
				}
				?>
				<tr>
					<td colspan="5"><span class="pull-right"><?php echo paginate($reload, $page, $total_pages, $adjacents);?></span></td>
				</tr>
			  </table>
			</div>
			</div>
			<?php
		}
}
?>