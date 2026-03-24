<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

	if($action == 'opcion_consignacion_ventas'){
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));	 
		 $ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
		 $por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
		 $aColumns = array('fecha_consignacion', 'numero_consignacion', 'observaciones','numero_consignacion', 'nombre');//Columnas de busqueda
		 $sTable = "encabezado_consignacion as enc_con INNER JOIN clientes as cli ON enc_con.id_cli_pro=cli.id";
		$sWhere = "WHERE enc_con.ruc_empresa ='". $ruc_empresa ." ' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion !='ENTRADA' " ;
			if ( $_GET['q'] != "" )
			{
				$sWhere = "WHERE (enc_con.ruc_empresa ='". $ruc_empresa ." ' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion !='ENTRADA' AND ";
				
				for ( $i=0 ; $i<count($aColumns) ; $i++ )
				{
					$sWhere .= $aColumns[$i]." LIKE '%".$q."%' AND enc_con.ruc_empresa ='". $ruc_empresa ." ' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion !='ENTRADA' OR ";
				}
				
				$sWhere = substr_replace( $sWhere, "AND enc_con.ruc_empresa ='". $ruc_empresa ." ' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion !='ENTRADA' ", -3 );
				$sWhere .= ')';
			}
		$sWhere.=" order by $ordenado $por";
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
		$reload = '../consignaciones_ventas.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
		<div class="table-responsive">
			<div class="panel panel-warning">
			  <table class="table table-hover">
				<tr  class="warning">
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-warning" onclick='ordenar("fecha_consignacion");'>Fecha</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-warning" onclick='ordenar("operacion");'>Tipo</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-warning" onclick='ordenar("id_cli_pro");'>Cliente</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-warning" onclick='ordenar("numero_consignacion");'>Número</button></th>
					<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-warning" onclick='ordenar("observaciones");'>Observaciones</button></th>
					<th class='text-right'>Opciones</th>
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_consignacion=$row['id_consignacion'];
						$fecha_consignacion=date('d-m-Y', strtotime($row['fecha_consignacion']));
						$cliente=strtoupper ($row['nombre']);
						$numero=$row['numero_consignacion'];
						$observaciones=$row['observaciones'];
						$codigo_unico=$row['codigo_unico'];
						$operacion=$row['operacion'];
					?>					
					<tr>
						<td><?php echo $fecha_consignacion; ?></td>
						<td><?php echo strtoupper ($operacion); ?></td>
						<td><?php echo strtoupper ($cliente); ?></td>
						<td><?php echo $numero ?></td>
						<td><?php echo strtoupper ($observaciones); ?></td>
					<td class='text-right'>
					<a title='Imprimir pdf' href="../pdf/pdf_opcion_consignacion_ventas.php?action=consignacion_ventas&codigo_unico=<?php echo $codigo_unico ?>" class='btn btn-default btn-xs' title='Pdf' target="_blank">Pdf</a>
					<a href="#" class='btn btn-info btn-xs' title='Detalle consignación' onclick="mostrar_detalle_opcion_consignacion('<?php echo $codigo_unico;?>');" data-toggle="modal" data-target="#detalleConsignacion"><i class="glyphicon glyphicon-list"></i></a>
					<a href="#" class='btn btn-danger btn-xs' title='Eliminar consignación' onclick="eliminar_opcion_consignacion_ventas('<?php echo $codigo_unico;?>');"><i class="glyphicon glyphicon-trash"></i></a>
					</td>
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan="6"><span class="pull-right">
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
	
	//para eliminar una consignacion en venta
	if ($action == 'eliminar_consignacion_venta'){
	if (!empty($_GET['id_entrada'])){
	$id_entrada=mysqli_real_escape_string($con,(strip_tags($_GET["id_entrada"],ENT_QUOTES)));
//buscar ese producto para saber si ya hay salidas y si hay mas salidas que entradas no se puede eliminar		
	$busca_datos_producto = "SELECT * FROM inventarios WHERE ruc_empresa='".$ruc_empresa."' and id_inventario='".$id_entrada."'";
	$result_datos_producto = $con->query($busca_datos_producto);
	$datos_producto = mysqli_fetch_array($result_datos_producto);
	$codigo_producto= $datos_producto['codigo_producto'];
	$nombre_producto= $datos_producto['nombre_producto'];
	$id_bodega= $datos_producto['id_bodega'];
	$cantidad_entrada= $datos_producto['cantidad_entrada'];
	$tipo_operacion= $datos_producto['operacion'];
	$id_registro_compra= $datos_producto['id_documento_venta'];
	$id_producto= $datos_producto['id_producto'];
	$tipo_registro= $datos_producto['tipo_registro'];
	$codigo_registro= $datos_producto['id_documento_venta'];
//contar salidas de este producto

	include_once("../clases/saldo_producto_y_conversion.php");
	$saldo_producto_factura = new saldo_producto_y_conversion();
	$saldo_final= $saldo_producto_factura->existencias_productos($id_bodega, $id_producto, $con);

	
	if($saldo_final >= $cantidad_entrada){
		if ($tipo_operacion=='ENTRADA'){
		$sql_actualiza_saldo_compra=mysqli_query($con,"UPDATE cuerpo_compra SET cantidad_inv=cantidad_inv-'".$cantidad_entrada."' WHERE id_cuerpo_compra='".$id_registro_compra."'");
		}
		
		if ($tipo_registro=="T"){
			if($delete_uno=mysqli_query($con,"DELETE FROM inventarios WHERE id_documento_venta = '".$codigo_registro."'")){
			echo "<script>
			$.notify('Todos los registros relacionados a la transferencia, han sido eliminados.','success');
			setTimeout(function (){location.reload()}, 1000);
			</script>";
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
		}else{
			if($delete_dos=mysqli_query($con,"DELETE FROM inventarios WHERE id_inventario = '".$id_entrada."'")){
			echo "<script>
			$.notify('La entrada ha sido eliminada satisfactoriamente.','success');
			setTimeout(function (){location.reload()}, 1000);
			</script>";
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
		}	
				
	}else{
		$errors []= "No es posible eliminar la entrada, ya que hay más salidas registradas de este producto.".mysqli_error($con);
	}
	}else{
		$errors []= "Algo ha salido mal intente de nuevo.".mysqli_error($con);
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