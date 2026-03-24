<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $c = mysqli_real_escape_string($con,(strip_tags($_REQUEST['c'], ENT_QUOTES)));

		 $aColumns = array('nombre');//Columnas de busqueda
		 $sTable = "clientes";
		$sWhere = "WHERE ruc_empresa ='".  $ruc_empresa ." '";
		if ( $_GET['c'] != "" )
		{
			$sWhere = "WHERE (ruc_empresa ='".  $ruc_empresa ." ' AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$c."%' OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND ruc_empresa = '".  $ruc_empresa ." '", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by nombre asc";
			
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
		$reload = '../modulos/nueva_factura.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){		
			?>
			<div class="table-responsive">
			  <table class="table">
				<tr  class="warning">
					<th>Nombre</th>
					<th>Dirección</th>
					<th>RUC,CI</span></th>
					<th class='text-center' style="width: 36px;">Agregar</th>
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
					$id_cliente_factura=$row['id'];
					$nombre_cliente_factura=$row['nombre'];
					$direccion_cliente_factura=$row['direccion'];
					$ruc_cliente_factura=$row["ruc"];
					?>
					<input type="hidden" value="<?php echo strtoupper($nombre_cliente_factura);?>" id="nombre_cliente_factura<?php echo $id_cliente_factura;?>">
					<input type="hidden" value="<?php echo strtoupper($direccion_cliente_factura);?>" id="direccion_cliente_factura<?php echo $id_cliente_factura;?>">
					<input type="hidden" value="<?php echo $ruc_cliente_factura;?>" id="ruc_cliente_factura<?php echo $id_cliente_factura;?>">
					<tr>
						<td><?php echo $nombre_cliente_factura; ?></td>
						<td><?php echo $direccion_cliente_factura; ?></td>
						<td><?php echo $ruc_cliente_factura; ?></td>
						<td class='text-center' data-dismiss="modal" ><a class='btn btn-info'href="#" onclick="agregar_cliente_factura('<?php echo $id_cliente_factura ?>')"><i class="glyphicon glyphicon-plus"></i></a></td>
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan=4><span class="pull-right">
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