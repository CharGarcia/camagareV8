<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	include("../validadores/periodo_contable.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$fecha_registro=date("Y-m-d H:i:s");

//PARA BUSCAR DETALLES DE LOS INGRESOS
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'detalle'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $deting = mysqli_real_escape_string($con,(strip_tags($_REQUEST['deting'], ENT_QUOTES)));
		 $aColumns = array('beneficiario_cliente','detalle_ing_egr','numero_ing_egr');//Columnas de busqueda
		 $sTable = "detalle_ingresos_egresos";
		 $sWhere = "WHERE ruc_empresa ='".  $ruc_empresa ." ' and tipo_documento='INGRESO' " ;
		if ( $_GET['deting'] != "" )
		{
			$sWhere = "WHERE (ruc_empresa ='".  $ruc_empresa ." ' and tipo_documento='INGRESO' AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$deting."%' and tipo_documento='INGRESO' OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND ruc_empresa = '".  $ruc_empresa ."' and tipo_documento='INGRESO' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by numero_ing_egr desc";
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
		$reload = '../ingresos.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th>Recibo de</th>
					<th>Número</th>
					<th>Valor</th>
					<th>Detalle</th>
				</tr>
				<?php

				while ($row=mysqli_fetch_array($query)){
						$nombre_cliente=$row['beneficiario_cliente'];
						$numero_ingreso=$row['numero_ing_egr'];
						$valor_ing_egr=$row['valor_ing_egr'];
						$detalle_ing_egr=$row['detalle_ing_egr'];

					?>
					<tr>

						<td><?php echo $nombre_cliente; ?></td>
						<td><?php echo $numero_ingreso; ?></td>
						<td><?php echo $valor_ing_egr; ?></td>
						<td><?php echo $detalle_ing_egr; ?></td>
					</tr>
				<?php
				}
				?>
				<tr>
					<td colspan=9 ><span class="pull-right">
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
?>