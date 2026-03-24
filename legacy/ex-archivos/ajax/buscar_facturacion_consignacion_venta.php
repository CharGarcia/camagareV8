<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'facturacion_consignacion_venta') {
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$aColumns = array('fecha_consignacion', 'numero_consignacion', 'observaciones', 'numero_consignacion', 'nombre', 'factura_venta'); //Columnas de busqueda
	$sTable = "encabezado_consignacion as enc_con INNER JOIN clientes as cli ON enc_con.id_cli_pro=cli.id";
	$sWhere = "WHERE enc_con.ruc_empresa ='" . $ruc_empresa . " ' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion ='FACTURA' ";

	$text_buscar = explode(' ', $q);
	$like = "";
	for ($i = 0; $i < count($text_buscar); $i++) {
		$like .= "%" . $text_buscar[$i];
	}
	if ($_GET['q'] != "") {
		$sWhere = "WHERE (enc_con.ruc_empresa ='" . $ruc_empresa . " ' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion ='FACTURA' AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND enc_con.ruc_empresa ='" . $ruc_empresa . " ' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion ='FACTURA' OR ";
		}

		$sWhere = substr_replace($sWhere, "AND enc_con.ruc_empresa ='" . $ruc_empresa . " ' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion ='FACTURA' ", -3);
		$sWhere .= ')';
	}
	$sWhere .= " order by $ordenado $por";
	include("../ajax/pagination.php"); //include pagination file
	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../facturacion_consignacion_venta.php';
	//main query to fetch the data
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
?>
		<div class="table-responsive">
			<div class="panel panel-success">
				<table class="table table-hover">
					<tr class="success">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-success" onclick='ordenar("fecha_consignacion");'>Fecha</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-success" onclick='ordenar("operacion");'>Tipo</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-success" onclick='ordenar("id_cli_pro");'>Cliente</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-success" onclick='ordenar("numero_consignacion");'>Número</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-success" onclick='ordenar("factura_venta");'>Factura</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-success" onclick='ordenar("observaciones");'>Observaciones</button></th>
						<th class='text-right'>Opciones</th>
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
						$id_consignacion = $row['id_consignacion'];
						$fecha_consignacion = date('d-m-Y', strtotime($row['fecha_consignacion']));
						$cliente = strtoupper($row['nombre']);
						$numero = $row['numero_consignacion'];
						$observaciones = $row['observaciones'];
						$codigo_unico = $row['codigo_unico'];
						$operacion = $row['operacion'];
						$numero_factura_venta = $row['factura_venta'];
						$factura_venta = $row['serie_sucursal'] . "-" . str_pad($row['factura_venta'], 9, "000000000", STR_PAD_LEFT);

						$sql_factura = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE ruc_empresa= '" . $ruc_empresa . "' and serie_factura='" . $row['serie_sucursal'] . "' and secuencial_factura='" . $row['factura_venta'] . "' ");
						$row_factura = mysqli_fetch_array($sql_factura);
						$id_encabezado_factura = isset($row_factura['id_encabezado_factura']) ? $row_factura['id_encabezado_factura'] : "";
						$estado_factura = isset($row_factura['estado_sri']) ? $row_factura['estado_sri'] : "";
					?>
						<tr>
							<td><?php echo $fecha_consignacion; ?></td>
							<td><?php echo strtoupper($operacion); ?></td>
							<td><?php echo strtoupper($cliente); ?></td>
							<td><?php echo $numero ?></td>
							<td><?php echo $factura_venta ?></td>
							<td class='col-md-3'><?php echo strtoupper($observaciones); ?></td>
							<?php
							if ($numero_factura_venta > 0) {
							?>
								<td class='text-right col-sm-2'>
									<a href="../ajax/imprime_documento.php?id_documento=<?php echo base64_encode($id_encabezado_factura) ?>&tipo_documento=factura&tipo_archivo=pdf" class='btn btn-default btn-xs' title='Mostrar pdf' <?php if ($observaciones == "ANULADA" || $estado_factura == 'PENDIENTE') { ?> disabled <?php } ?>>Pdf</i> </a>
									<a href="#" class='btn btn-info btn-xs' title='Detalle factura' onclick="mostrar_detalle_factura_consignacion('<?php echo $codigo_unico; ?>');" data-toggle="modal" data-target="#detalleConsignacion"><i class="glyphicon glyphicon-list"></i></a>
									<a href="#" class='btn btn-danger btn-xs' title='Eliminar registro' onclick="eliminar_factura_consignacion_venta('<?php echo $codigo_unico; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
								</td>
							<?php
							} else {
							?>
								<td class='text-right'></td>
							<?php

							}
							?>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="7"><span class="pull-right">
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
if ($action == 'eliminar_consignacion_venta') {
	if (!empty($_GET['id_entrada'])) {
		$id_entrada = mysqli_real_escape_string($con, (strip_tags($_GET["id_entrada"], ENT_QUOTES)));
		//buscar ese producto para saber si ya hay salidas y si hay mas salidas que entradas no se puede eliminar		
		$busca_datos_producto = "SELECT * FROM inventarios WHERE ruc_empresa='" . $ruc_empresa . "' and id_inventario='" . $id_entrada . "'";
		$result_datos_producto = $con->query($busca_datos_producto);
		$datos_producto = mysqli_fetch_array($result_datos_producto);
		$codigo_producto = $datos_producto['codigo_producto'];
		$nombre_producto = $datos_producto['nombre_producto'];
		$id_bodega = $datos_producto['id_bodega'];
		$cantidad_entrada = $datos_producto['cantidad_entrada'];
		$tipo_operacion = $datos_producto['operacion'];
		$id_registro_compra = $datos_producto['id_documento_venta'];
		$id_producto = $datos_producto['id_producto'];
		$tipo_registro = $datos_producto['tipo_registro'];
		$codigo_registro = $datos_producto['id_documento_venta'];
		//contar salidas de este producto

		include_once("../clases/saldo_producto_y_conversion.php");
		$saldo_producto_factura = new saldo_producto_y_conversion();
		$saldo_final = $saldo_producto_factura->existencias_productos($id_bodega, $id_producto, $con);


		if ($saldo_final >= $cantidad_entrada) {
			if ($tipo_operacion == 'ENTRADA') {
				$sql_actualiza_saldo_compra = mysqli_query($con, "UPDATE cuerpo_compra SET cantidad_inv=cantidad_inv-'" . $cantidad_entrada . "' WHERE id_cuerpo_compra='" . $id_registro_compra . "'");
			}

			if ($tipo_registro == "T") {
				if ($delete_uno = mysqli_query($con, "DELETE FROM inventarios WHERE id_documento_venta = '" . $codigo_registro . "'")) {
					echo "<script>
			$.notify('Todos los registros relacionados a la transferencia, han sido eliminados.','success');
			setTimeout(function (){location.reload()}, 1000);
			</script>";
				} else {
					$errors[] = "Lo siento algo ha salido mal intenta nuevamente." . mysqli_error($con);
				}
			} else {
				if ($delete_dos = mysqli_query($con, "DELETE FROM inventarios WHERE id_inventario = '" . $id_entrada . "'")) {
					echo "<script>
			$.notify('La entrada ha sido eliminada satisfactoriamente.','success');
			setTimeout(function (){location.reload()}, 1000);
			</script>";
				} else {
					$errors[] = "Lo siento algo ha salido mal intenta nuevamente." . mysqli_error($con);
				}
			}
		} else {
			$errors[] = "No es posible eliminar la entrada, ya que hay más salidas registradas de este producto." . mysqli_error($con);
		}
	} else {
		$errors[] = "Algo ha salido mal intente de nuevo." . mysqli_error($con);
	}
}

if (isset($errors)) {
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
if (isset($messages)) {

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