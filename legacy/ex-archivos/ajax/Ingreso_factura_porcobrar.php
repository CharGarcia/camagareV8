<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$fecha_registro=date("Y-m-d H:i:s");

//PARA BUSCAR LAS FACTURAS
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $aColumns = array('nombre_cliente_factura', 'secuencial_factura', 'serie_factura','estado_factura');//Columnas de busqueda
		 $sTable = "encabezado_factura";
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
		$sWhere.=" order by id_encabezado_factura desc";
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 5; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../facturas.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="table-responsive">
			  <table class="table">
				<tr  class="info">
					<th>Fecha</th>
					<th>Cliente</th>
					<th>Número</th>
					<th>Total</th>
					<th class='text-right'>Agregar</th>
					
				</tr>
				<?php

				while ($row=mysqli_fetch_array($query)){
						$id_encabezado_factura=$row['id_encabezado_factura'];
						$fecha_factura=$row['fecha_factura'];
						$serie_factura=$row['serie_factura'];
						$secuencial_factura=$row['secuencial_factura'];
						$nombre_cliente_factura=$row['nombre_cliente_factura'];
						$total_factura=$row['total_factura'];
					?>
					<input type="hidden" value="<?php echo $id_encabezado_factura;?>" id="id_encabezado_factura<?php echo $id_encabezado_factura;?>">
					<tr>
						<td><?php echo date("d/m/Y", strtotime($fecha_factura)); ?></td>
						<td><?php echo $nombre_cliente_factura; ?></td>
						<td><?php echo $serie_factura; ?>-<?php echo str_pad($secuencial_factura,9,"000000000",STR_PAD_LEFT); ?></td>
						<td class="col-md-2"><div class="pull-right"><input style="text-align:right" type="text" class="form-control" id="total_factura<?php echo $id_encabezado_factura;?>" value="<?php echo $total_factura;?>"></div></td>
						<td class='text-center'><a class='btn btn-info'href="#" onclick="agregar_factura_apagar('<?php echo $id_encabezado_factura ?>')"><i class="glyphicon glyphicon-plus"></i></a></td>
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
			<?php
		}
	}
?>