<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

//para eliminar un iten del ingreso temporal
if (isset($_GET['id'])) {
	$id_tmp = intval($_GET['id']);
	$delete = mysqli_query($con, "DELETE FROM ingresos_egresos_tmp WHERE id_tmp='" . $id_tmp . "'");
}

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//para facturas cobradas
if ($action == 'facturas_cobradas') {
	if (isset($_POST['id'])) {
		$id_factura = $_POST['id'];
	}
	if (isset($_POST['valor_cobrado'])) {
		$valor_cobrado = $_POST['valor_cobrado'];
	}

	if (!empty($id_factura) and !empty($valor_cobrado)) {
		//para buscar datos de la factura a cobrada
		$sql_factura = mysqli_query($con, "SELECT * from encabezado_factura ef, clientes cl WHERE ef.ruc_empresa = '" . $ruc_empresa . "' and id_encabezado_factura = '" . $id_factura . "' and ef.id_cliente= cl.id");
		$row_factura = mysqli_fetch_array($sql_factura);
		$nombre_cliente = $row_factura['nombre'];
		$serie_factura = $row_factura['serie_factura'];
		$secuencial_factura = str_pad($row_factura['secuencial_factura'], 9, "000000000", STR_PAD_LEFT);
		$id_cliente = $row_factura['ruc'];
		$factura = $id_cliente . "-FACTURA-" . $serie_factura . "-" . $secuencial_factura;
		$total_factura = $row_factura['total_factura'];
		//buscar pagos agregados de esta factura en el temporal
		$valor_factura_tmp = 0;
		$sql_tmp = "SELECT * FROM ingresos_egresos_tmp where id_usuario='" . $id_usuario . "' and detalle= '" . $factura . "' and tipo_transaccion='VENTAS' ";
		$respuesta = mysqli_query($con, $sql_tmp);
		while ($valor_ingreso = mysqli_fetch_array($respuesta)) {
			$valor_factura_tmp += $valor_ingreso['valor'];
		}
		$saldo_factura_cobrada = $valor_factura_tmp + $valor_cobrado;

		if ($saldo_factura_cobrada > $total_factura) {
			echo "<script>alert('El valor de la factura es mayor al pendiente de cobro.')</script>";
			echo "<script>window.close();</script>";
		} else {
			//para guardar en el ingreso temporal
			$insert_tmp = mysqli_query($con, "INSERT INTO ingresos_egresos_tmp VALUES (null,'INGRESO','" . $nombre_cliente . "','" . $factura . "','" . $valor_cobrado . "','VENTAS','" . $id_usuario . "')");
		}
	}
}

//para anticipo clientes
if ($action == 'anticipo_clientes') {
	if (isset($_POST['cliente_anticipo'])) {
		$cliente_anticipo = $_POST['cliente_anticipo'];
	}
	if (isset($_POST['valor_anticipo'])) {
		$valor_anticipo = $_POST['valor_anticipo'];
	}
	if (isset($_POST['detalle_anticipo'])) {
		$detalle_anticipo = $_POST['detalle_anticipo'];
	}

	if (!empty($cliente_anticipo) and !empty($valor_anticipo)) {
		//para guardar en el ingreso temporal
		$insert_tmp = mysqli_query($con, "INSERT INTO ingresos_egresos_tmp VALUES (null,'INGRESO','" . $cliente_anticipo . "','" . $detalle_anticipo . "','" . $valor_anticipo . "','ANTICIPO CLIENTES','" . $id_usuario . "')");
	}
}

//para otros ingresos
if ($action == 'otros_ingresos') {
	if (isset($_POST['nombre_otros'])) {
		$nombre_otros = $_POST['nombre_otros'];
	}
	if (isset($_POST['valor_otros'])) {
		$valor_otros = $_POST['valor_otros'];
	}
	if (isset($_POST['detalle_otros'])) {
		$detalle_otros = $_POST['detalle_otros'];
	}

	if (!empty($nombre_otros) and !empty($valor_otros)) {
		//para guardar en el ingreso temporal
		$insert_tmp = mysqli_query($con, "INSERT INTO ingresos_egresos_tmp VALUES (null,'INGRESO','" . $nombre_otros . "','" . $detalle_otros . "','" . $valor_otros . "','OTROS INGRESOS','" . $id_usuario . "')");
	}
}
?>


<div class="panel panel-info">
	<table class="table">
		<tr class="info">
			<th>Recibo de</th>
			<th>Detalle</th>
			<th class='text-center'>Valor</th>
			<th>Tipo ingreso</th>
			<th>Eliminar</th>
		</tr>
		<?php
		// PARA MOSTRAR LOS ITEMS DEL INGRESO
		$total_ingreso = 0;
		$sql = mysqli_query($con, "SELECT * from ingresos_egresos_tmp where id_usuario = '".$id_usuario."' and tipo_documento='INGRESO'");
		while ($row = mysqli_fetch_array($sql)) {
			$id_tmp = $row["id_tmp"];
			$beneficiario_cliente = $row['beneficiario_cliente'];
			$detalle = $row['detalle'];
			$valor = number_format($row['valor'], 2, '.', '');
			$tipo_ingreso = $row['tipo_transaccion'];
			$total_ingreso += $row['valor'];
		?>
			<tr>
				<td><?php echo $beneficiario_cliente; ?></td>
				<td><?php echo $detalle; ?></td>
				<td class='text-center'><?php echo $valor; ?></td>
				<td><?php echo $tipo_ingreso; ?></td>
				<td>
					<a href="#" class='btn btn-danger btn-xs' onclick="eliminar_item_ingreso('<?php echo $id_tmp; ?>')" title="Eliminar item"><i class="glyphicon glyphicon-trash"></i></a>
				</td>
			</tr>

		<?php
		}
		?>

		<tr class="info">
			<td class='text-right' colspan="2">TOTAL INGRESO: </td>
			<td class='text-center'><?php echo number_format($total_ingreso, 2, '.', ''); ?></td>
			<input type="hidden" id="suma_ingreso" value="<?php echo number_format($total_ingreso, 2, '.', ''); ?>">
			<script>
				var total_ingreso = $("#suma_ingreso").val();
				$("#total_ingreso").val(total_ingreso);
			</script>
			<td colspan="2"></td>
		</tr>
	</table>
</div>