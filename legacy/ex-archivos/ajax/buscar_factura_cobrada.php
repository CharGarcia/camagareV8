<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$fecha_registro=date("Y-m-d H:i:s");

//PARA BUSCAR LAS FACTURAS
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $aColumns = array('cl.nombre', 'ef.secuencial_factura', 'ef.serie_factura');//Columnas de busqueda
		 $sTable = "encabezado_factura ef, clientes cl";
		 $sWhere = "WHERE ef.ruc_empresa ='".  $ruc_empresa ."' and ef.id_cliente = cl.id";
		if ( $_GET['q'] != "" ){			
			$sWhere = "WHERE (ef.ruc_empresa ='".  $ruc_empresa ." ' and ef.id_cliente = cl.id AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' and ef.ruc_empresa ='".  $ruc_empresa ." ' and ef.id_cliente = cl.id OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND ef.ruc_empresa = '".  $ruc_empresa ." ' and ef.id_cliente = cl.id ", -3 );
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
		$reload = '../ingresos.php';
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
						$secuencial_factura=str_pad($row['secuencial_factura'],9,"000000000",STR_PAD_LEFT);
						$factura_modificada = $serie_factura."-".$secuencial_factura;
						$nombre_cliente_factura=$row['nombre'];
						$id_cliente = $row['ruc'];
						$factura=$id_cliente."-FACTURA-".$serie_factura."-".$secuencial_factura;

							//buscar pagos agregados de esta factura en el temporal
							$valor_factura_tmp=0;
							$sql_tmp = "SELECT * FROM ingresos_egresos_tmp where id_usuario='$id_usuario' and detalle= '$factura' and tipo_transaccion='VENTAS' ";
							$respuesta = mysqli_query($con,$sql_tmp);
							while ($valor_ingreso = mysqli_fetch_assoc($respuesta)){
							$valor_factura_tmp+=$valor_ingreso['valor'];				
							}
							
						//buscar pagos agregados de esta factura en los detalles de los ingresos
							$valor_factura_ingreso=0;
							$sql_tmp_ingreso = "SELECT * FROM detalle_ingresos_egresos where ruc_empresa='$ruc_empresa' and detalle_ing_egr= '$factura' and tipo_ing_egr='VENTAS' and tipo_documento='INGRESO' ";
							$respuesta_ingreso = mysqli_query($con,$sql_tmp_ingreso);
							while ($valor_detalle_ingreso = mysqli_fetch_assoc($respuesta_ingreso)){
							$valor_factura_ingreso+=$valor_detalle_ingreso['valor_ing_egr'];				
							}
							
						//buscar en notas de credito el valor para descontar
							$valor_nc=0;
							$sql_nc = "SELECT * FROM encabezado_nc where ruc_empresa='$ruc_empresa' and factura_modificada= '$factura_modificada' ";
							$respuesta_nc = mysqli_query($con,$sql_nc);
							while ($valor_detalle_nc = mysqli_fetch_assoc($respuesta_nc)){
							$valor_nc+=$valor_detalle_nc['total_nc'];				
							}
						
						$total_factura=$row['total_factura'] - $valor_factura_tmp - $valor_factura_ingreso - $valor_nc ;
						if ($total_factura>0){
					?>
					<input type="hidden" value="<?php echo $total_factura;?>" id="total_factura_por_cobrar<?php echo $id_encabezado_factura;?>">
					<tr>
						<td><?php echo date("d/m/Y", strtotime($fecha_factura)); ?></td>
						<td><?php echo $nombre_cliente_factura; ?></td>
						<td><?php echo $serie_factura; ?>-<?php echo str_pad($secuencial_factura,9,"000000000",STR_PAD_LEFT); ?></td>
						<td class="col-md-2"><div class="pull-right"><input style="text-align:right" type="text" class="form-control" id="valor_cobrado<?php echo $id_encabezado_factura;?>" value="<?php echo $total_factura;?>"></div></td>
						<?php
						if ($total_factura <= 0){
						?>
						<td class='text-center'><a class='btn btn-danger'href="#" title="No se puede agregar, no hay valores pendientes" ><i class="glyphicon glyphicon-ban-circle"></i></a></td>
						<?php
						}else{
						?>
						<td class='text-center'><a class='btn btn-info'href="#" title="Agregar pago" onclick="agregar_factura_cobrada('<?php echo $id_encabezado_factura ?>')"><i class="glyphicon glyphicon-plus"></i></a></td>
						<?php
						}
						?>
					</tr>
										<?php
										}
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