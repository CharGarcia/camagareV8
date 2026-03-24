<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $p = mysqli_real_escape_string($con,(strip_tags($_REQUEST['p'], ENT_QUOTES)));

		 $aColumns = array('codigo_producto', 'nombre_producto');//Columnas de busqueda
		 $sTable = "productos_servicios";

		 $sWhere = "WHERE ruc_empresa ='".  $ruc_empresa ." '";

		if ( $_GET['p'] != "" )
		{
			$sWhere = "WHERE (ruc_empresa ='".  $ruc_empresa ." ' AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$p."%' OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND ruc_empresa = '".  $ruc_empresa ." ' ", -3 );
			$sWhere .= ')';
		}
			
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 4; //how much records you want to show
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
			<div class="panel panel-info">
			  <table class="table">
				<tr  class="warning">
					<th>Código</th>
					<th>Producto</th>
					<th><span class="pull-right">IVA</span></th>
					<th><span class="pull-right">Cant.</span></th>
					<th><span class="pull-right">Precio</span></th>
					<th class='text-center' style="width: 36px;">Agregar</th>
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
					$id_producto=$row['id'];
					$codigo_producto=$row['codigo_producto'];
					$nombre_producto=$row['nombre_producto'];
					$tarifa_iva=$row['tarifa_iva'];
					$precio_venta=$row["precio_producto"];
					$precio_venta=number_format($precio_venta,4,'.','');
					?>
					<tr>
						<td><?php echo strtoupper($codigo_producto); ?></td>
						<td><?php echo strtoupper($nombre_producto);?></td>
						<td class='col-xs-2'>
						<div class="pull-right">
						
							<?php
							$sql = "SELECT * FROM tarifa_iva where codigo = '$tarifa_iva' ;";
							$res = mysqli_query($con,$sql);
							while($op_iva = mysqli_fetch_assoc($res)){
							?>
							<?php echo $op_iva['tarifa'];?>
							<?php
							}
							?>	
						
											
						</div></td>
						<td class='col-xs-2'>
						<div class="pull-right">
						<input type="text" class="form-control" style="text-align:right" id="cantidad_<?php echo $id_producto; ?>"  value="1" >
						</div></td>
						<td class='col-xs-2'><div class="pull-right">
						<input type="text" class="form-control" style="text-align:right" id="precio_venta_<?php echo $id_producto; ?>"  value="<?php echo $precio_venta;?>" >
						</div></td>
						<td class='text-center'><a class='btn btn-info'href="#" onclick="agregar_item_factura('<?php echo $id_producto ?>')"><i class="glyphicon glyphicon-plus"></i></a></td>
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan=6><span class="pull-right">
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