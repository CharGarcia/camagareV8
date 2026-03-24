<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
include("../clases/saldo_producto_y_conversion.php");
include("../clases/control_salidas_inventario.php");
include("../validadores/generador_codigo_unico.php");
$codigo_unico = codigo_unico(20);
$con = conenta_login();
if (empty($_POST['fecha_proforma'])) {
	$errors[] = "Ingrese fecha para la proforma.";
} else if (!date($_POST['fecha_proforma'])) {
	$errors[] = "Ingrese fecha correcta";
} else if (empty($_POST['secuencial_proforma'])) {
	$errors[] = "Ingrese un número de proforma.";
} else if (empty($_POST['serie_proforma'])) {
	$errors[] = "Seleccione serie.";
} else if (!is_numeric($_POST['secuencial_proforma'])) {
	$errors[] = "Ingrese un número de proforma.";
} else if (empty($_POST['id_cliente_proforma'])) {
	$errors[] = "Seleccione un cliente para proforma.";
} else if (
	!empty($_POST['fecha_proforma']) && !empty($_POST['serie_proforma']) && !empty($_POST['secuencial_proforma'])
	&& !empty($_POST['id_cliente_proforma'])
) {
	ini_set('date.timezone', 'America/Guayaquil');
	$guarda_salida_inventario = new control_salida_inventario();
	$saldo_producto = new saldo_producto_y_conversion();
	$fecha_proforma = date('Y-m-d H:i:s', strtotime($_POST['fecha_proforma']));
	$secuencial_proforma = mysqli_real_escape_string($con, (strip_tags($_POST["secuencial_proforma"], ENT_QUOTES)));
	$serie_proforma = mysqli_real_escape_string($con, (strip_tags($_POST["serie_proforma"], ENT_QUOTES)));
	$id_cliente = mysqli_real_escape_string($con, (strip_tags($_POST["id_cliente_proforma"], ENT_QUOTES)));
	$total_proforma = mysqli_real_escape_string($con, (strip_tags($_POST["total_proforma"], ENT_QUOTES)));
	$referencia_salida_inventario = "Proforma: " . str_pad($secuencial_proforma, 9, "000000000", STR_PAD_LEFT);
	$action = mysqli_real_escape_string($con, (strip_tags($_POST["action"], ENT_QUOTES)));
	$codigo_unico_editar = mysqli_real_escape_string($con, (strip_tags($_POST["codigo_unico"], ENT_QUOTES)));
	$fecha_registro = date("Y-m-d H:i:s");
	//session_start();
	$id_usuario = $_SESSION['id_usuario'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	//traer correo de esta empresa
	$tipo_salida = "P";

	if ($action == 'editar') {
		$delete_encabezado_proforma = mysqli_query($con, "DELETE FROM encabezado_proforma WHERE ruc_empresa= '" . $ruc_empresa . "' and codigo_unico='" . $codigo_unico_editar . "' ");
		$delete_cuerpo_proforma = mysqli_query($con, "DELETE FROM cuerpo_proforma WHERE ruc_empresa= '" . $ruc_empresa . "' and codigo_unico='" . $codigo_unico_editar . "'");
		$delete_adicionales_proforma = mysqli_query($con, "DELETE FROM detalle_adicional_proforma WHERE ruc_empresa= '" . $ruc_empresa . "' and codigo_unico='" . $codigo_unico_editar . "'");
		$delete_registro_inventario = mysqli_query($con, "DELETE FROM inventarios WHERE ruc_empresa = '" . $ruc_empresa . "' and id_documento_venta='" . $codigo_unico_editar . "'");
	}

	/* //para saber si esta serie trabaja con inventario
	$sql_inventario = mysqli_query($con, "SELECT * FROM configuracion_proformas where ruc_empresa ='" . $ruc_empresa . "' and serie_sucursal ='" . $serie_proforma . "'");
	$row_invetario = mysqli_fetch_array($sql_inventario);
	$inventario = isset($row_invetario['inventario']);
 */
	//para ver si la proforma que queremos registrar ya esta registrada	
	$busca_empresa = mysqli_query($con, "SELECT * FROM encabezado_proforma WHERE ruc_empresa = '" . $ruc_empresa . "' and secuencial_proforma ='" . $secuencial_proforma . "'");
	$count = mysqli_num_rows($busca_empresa);
	if ($count > 0) {
		$errors[] = "El número de proforma que intenta guardar ya se encuentra registrado en el sistema." . mysqli_error($con);
	} else {
		$sql_proforma_temporal = mysqli_query($con, "SELECT * FROM factura_tmp as fac_tmp 
		INNER JOIN productos_servicios as pro_ser ON fac_tmp.id_producto=pro_ser.id 
		WHERE fac_tmp.id_usuario = '" . $id_usuario . "' and fac_tmp.ruc_empresa = '" . $ruc_empresa . "'");
		$count = mysqli_num_rows($sql_proforma_temporal);
		if ($count == 0) {
			$errors[] = "No hay detalle de productos o servicios agregados a la proforma." . mysqli_error($con);
		} else {
			//para guardar el encabezado de la proforma
			$query_encabezado_proforma = mysqli_query($con, "INSERT INTO encabezado_proforma VALUES (null, '" . $ruc_empresa . "','" . $fecha_proforma . "','" . $serie_proforma . "','" . $secuencial_proforma . "','" . $id_cliente . "', '" . $fecha_registro . "', '" . $total_proforma . "', '" . $id_usuario . "','PENDIENTE','PENDIENTE','" . $codigo_unico . "','')");

			//para guardar detalle adicional de la proforma
			$busca_adicional_tmp = mysqli_query($con, "SELECT * FROM adicional_tmp WHERE id_usuario = '" . $id_usuario . "' and serie_factura = '" . $serie_proforma . "' and secuencial_factura = '" . $secuencial_proforma . "'");
			while ($row_detalle_adicional = mysqli_fetch_array($busca_adicional_tmp)) {
				$concepto = $row_detalle_adicional['concepto'];
				$detalle = $row_detalle_adicional['detalle'];
				$query_guarda_detalle_adicional_proforma = mysqli_query($con, "INSERT INTO detalle_adicional_proforma VALUES (null, '" . $ruc_empresa . "','" . $codigo_unico . "','" . $secuencial_proforma . "','" . $concepto . "','" . $detalle . "')");
			}

			//para guardar el detalle de la proforma
			while ($row_detalle = mysqli_fetch_array($sql_proforma_temporal)) {
				$cantidad_proforma = str_replace(",", ".", $row_detalle["cantidad_tmp"]);
				$precio_venta = str_replace(",", ".", $row_detalle['precio_tmp']);
				$subtotal_proforma = number_format(str_replace(",", ".", $precio_venta * $cantidad_proforma), 2, '.', ''); //Precio total formateado
				$tipo_produccion = $row_detalle['tipo_produccion'];
				$tarifa_iva = $row_detalle['tarifa_iva'];
				$tarifa_ice = $row_detalle['tarifa_ice'];
				$tarifa_bp = $row_detalle['tarifa_botellas'];
				$descuento = $row_detalle['descuento'];
				$id_producto = $row_detalle['id_producto'];
				$id_bodega = $row_detalle['id_bodega'];
				$id_medida_salida = $row_detalle['id_medida'];
				$codigo_producto = $row_detalle['codigo_producto'];
				$nombre_producto = $row_detalle['nombre_producto'];
				$lote = $row_detalle['lote'];
				$vencimiento = date('Y/m/d H:i:s', strtotime($row_detalle['vencimiento']));
				$guarda_detalle_proforma = mysqli_query($con, "INSERT INTO cuerpo_proforma VALUES (null, '" . $ruc_empresa . "','" . $codigo_unico . "','" . $secuencial_proforma . "','" . $id_producto . "','" . $cantidad_proforma . "','" . $precio_venta . "','" . $subtotal_proforma . "','" . $tipo_produccion . "','" . $tarifa_iva . "','" . $tarifa_ice . "','" . $tarifa_bp . "','" . $descuento . "','" . $codigo_producto . "','" . $nombre_producto . "','" . $id_medida_salida . "','" . $lote . "','" . $vencimiento . "','" . $id_bodega . "')");
			}
			if ($query_encabezado_proforma && $guarda_detalle_proforma && $query_guarda_detalle_adicional_proforma) {
				echo "<script>
					$.notify('proforma guardada con éxito','success');
					setTimeout(function (){location.href ='../modulos/proformas.php'}, 1000);
					</script>";
			} else {
				echo "<script>
					$.notify('Lo siento algo ha salido mal intenta nuevamente','error');
					</script>";
			}
		}
	}
} else {
	$errors[] = "Error desconocido.";
}

if (isset($errors)) {
?>
	<div class="alert alert-danger" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>Atención! </strong>
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
		<strong>¡Bien hecho! </strong>
		<?php
		foreach ($messages as $message) {
			echo $message;
		}
		?>
	</div>
<?php
}
?>