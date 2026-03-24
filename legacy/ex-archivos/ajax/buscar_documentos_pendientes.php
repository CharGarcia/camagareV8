<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	
	
//PARA BUSCAR LOS MIVELES O PARALELOS
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'pendientes'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $aColumns = array('emp.nombre_comercial');//Columnas de busqueda
		 $sTable = "empresa_asignada as ea INNER JOIN empresas as emp ON emp.id=ea.id_empresa";
		 $sWhere = "WHERE ea.id_usuario = '" . $id_usuario . "' and emp.estado = '1' ";
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE (ea.id_usuario = '" . $id_usuario . "' and emp.estado = '1' AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND ea.id_usuario = '" . $id_usuario . "' and emp.estado = '1' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by emp.nombre_comercial asc";
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
		$reload = '';
		//main query to fetch the data
		$sql="SELECT emp.nombre_comercial as empresa, emp.ruc as ruc FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th>Empresa</th>
					<th>Facturas</th>
					<th>Retenciones</th>
					<th>Liquidaciones</th>
					<th>NC</th>
					<th>GR</th>				
				</tr>
				<?php
				while ($row_empresas=mysqli_fetch_array($query)){
					$empresa = $row_empresas['empresa'];
					$ruc = $row_empresas['ruc'];
				
				$sql_facturas = mysqli_query($con, "SELECT count(estado_sri) as facturas FROM encabezado_factura WHERE estado_sri='PENDIENTE' and ruc_empresa='".$ruc."'");
				$row_facturas= mysqli_fetch_array($sql_facturas);
				$facturas = $row_facturas['facturas']>0?$row_facturas['facturas']:"";

				$encabezado_nc = mysqli_query($con, "SELECT count(estado_sri) as nc FROM encabezado_nc WHERE estado_sri='PENDIENTE' and ruc_empresa='".$ruc."'");
				$row_encabezado_nc= mysqli_fetch_array($encabezado_nc);
				$nc = $row_encabezado_nc['nc']>0?$row_encabezado_nc['nc']:"";

				$encabezado_gr = mysqli_query($con, "SELECT count(estado_sri) as gr FROM encabezado_gr WHERE estado_sri='PENDIENTE' and ruc_empresa='".$ruc."'");
				$row_encabezado_gr= mysqli_fetch_array($encabezado_gr);
				$gr = $row_encabezado_gr['gr']>0?$row_encabezado_gr['gr']:"";

				$encabezado_liquidacion = mysqli_query($con, "SELECT count(estado_sri) as liq FROM encabezado_liquidacion WHERE estado_sri='PENDIENTE' and ruc_empresa='".$ruc."'");
				$row_encabezado_liquidacion= mysqli_fetch_array($encabezado_liquidacion);
				$liq = $row_encabezado_liquidacion['liq']>0?$row_encabezado_liquidacion['liq']:"";

				$encabezado_retencion = mysqli_query($con, "SELECT count(estado_sri) as ret FROM encabezado_retencion WHERE estado_sri='PENDIENTE' and ruc_empresa='".$ruc."'");
				$row_encabezado_retencion= mysqli_fetch_array($encabezado_retencion);
				$ret = $row_encabezado_retencion['ret']>0?$row_encabezado_retencion['ret']:"";	
					$total=$facturas+$nc+$gr+$liq+$ret;
					//if($total>0){
					?>
					
					<tr>
						<td><?php echo strtoupper($empresa); ?></td>
						<td><span class="badge"><?php echo $facturas; ?></span></td>
						<td><span class="badge"><?php echo $ret; ?></span></td>
						<td><span class="badge"><?php echo $liq; ?></span></td>
						<td><span class="badge"><?php echo $nc; ?></span></td>
						<td><span class="badge"><?php echo $gr; ?></span></td>
					</tr>
				<?php
					//}
				}
				?>
				
				<tr>
					<td colspan="6"><span class="pull-right"><?php echo paginate($reload, $page, $total_pages, $adjacents);?></span></td>
				</tr>
			  </table>
			</div>
			</div>
			<?php
		}
	}
?>