<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");

$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];
if (isset($_POST['id_producto'])) {
	$id_producto = $_POST['id_producto'];
}
if (isset($_POST['precio_venta'])) {
	$precio_venta = $_POST['precio_venta'];
}
if (isset($_POST['cantidad'])) {
	$cantidad = $_POST['cantidad'];
}
if (isset($_POST['lote_agregar'])) {
	$lote_agregar = $_POST['lote_agregar'];
}
if (isset($_POST['caducidad_agregar'])) {
	$caducidad_agregar = $_POST['caducidad_agregar'];
}
if (isset($_POST['medida_agregar'])) {
	$medida_agregar = $_POST['medida_agregar'];
}
if (isset($_POST['secuencial_factura'])) {
	$secuencial_factura = $_POST['secuencial_factura'];
}
if (isset($_POST['serie_factura_e'])) {
	$serie_factura = $_POST['serie_factura_e'];
}
if (isset($_POST['id_cliente'])) {
	$id_cliente = $_POST['id_cliente'];
}
//if (isset($_POST['bodega_agregar'])){$bodega_agregar=$_POST['bodega_agregar'];}


$busca_bodega = "SELECT * FROM bodega WHERE ruc_empresa = '" . $ruc_empresa . "' ";
$result_bodega = $con->query($busca_bodega);
$row_bodega = mysqli_fetch_array($result_bodega);
//$bodega_agregar=isset($bodega_agregar)?$row_bodega['id_bodega']:$bodega_agregar;
$bodega_agregar = isset($row_bodega['id_bodega']) ? $row_bodega['id_bodega'] : "";


//para saber los decimales que trabaja esta empresa
if (isset($_POST['serie_factura_e'])) {
	$serie_factura = $_POST['serie_factura_e'];
	$busca_info_sucursal = "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' and serie = '" . $serie_factura . "' ";
	$result_info_sucursal = $con->query($busca_info_sucursal);
	$info_sucursal = mysqli_fetch_array($result_info_sucursal);
	$decimal_precio = intval($info_sucursal['decimal_doc']);
	$decimal_cant = intval($info_sucursal['decimal_cant']);
	if ($decimal_cant == 1) {
		$decimal_cant = 0;
	} else {
		$decimal_cant = $decimal_cant;
	}
}



if (!isset($_POST['serie_factura_e']) or empty($_POST['serie_factura_e'])) {
	$decimal_precio = 2;
	$decimal_cant = 0;
}

//para buscar datos del producto o servicio 
if (!empty($id_producto) && !empty($cantidad) && !empty($precio_venta)) {
	$subtotal = number_format($cantidad * $precio_venta, 2, '.', '');
	$insert_tmp = mysqli_query($con, "INSERT INTO factura_tmp (id, id_producto, cantidad_tmp, precio_tmp, descuento, tipo_produccion, tarifa_iva, tarifa_ice , tarifa_botellas, id_usuario ,id_bodega,id_medida, lote,vencimiento, subtotal, ruc_empresa)
SELECT null, '" . $id_producto . "', '" . number_format($cantidad, $decimal_cant, '.', '') . "','" . number_format($precio_venta, $decimal_precio, '.', '') . "','0', 
tipo_produccion, tarifa_iva , tarifa_ice, tarifa_botellas,'" . $id_usuario . "', if(tipo_produccion='02', 0,'" . $bodega_agregar . "'), if(tipo_produccion='02', 0,'" . $medida_agregar . "'), if(tipo_produccion='02', 0,'" . $lote_agregar . "'), if(tipo_produccion='02', 0,'" . $caducidad_agregar . "'), '" . $subtotal . "', ruc_empresa FROM productos_servicios WHERE id='" . $id_producto . "'");
}

//para agregar un producto a la lista de la factura cuando lee el codigo de barras
if (isset($_POST['bar_code']) && isset($_POST['producto_agregar'])) {
	$insert_tmp_barcode = mysqli_query($con, "INSERT INTO factura_tmp (id, id_producto, cantidad_tmp, precio_tmp, descuento, tipo_produccion, tarifa_iva, tarifa_ice , tarifa_botellas, id_usuario ,id_bodega,id_medida, lote,vencimiento, subtotal, ruc_empresa)
	SELECT null, id, 1, precio_producto,'0', tipo_produccion, tarifa_iva , tarifa_ice, tarifa_botellas,'" . $id_usuario . "', if(tipo_produccion='02', 0,'" . $bodega_agregar . "'), if(tipo_produccion='02', 0, id_unidad_medida), if(tipo_produccion='02', 0,0), if(tipo_produccion='02', 0,0), round(precio_producto,2), ruc_empresa FROM productos_servicios WHERE codigo_producto='" . $_POST['producto_agregar'] . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' ");

	if ($con->insert_id == 0) {
		echo "<script>$.notify('Producto no encontrado.','error')</script>";
	}
}

//para agregar un adicional al temporal de facturas adicional
if (isset($_POST['agregar_adicional'])) {
	$concepto = $_POST['adicional_concepto'];
	$detalle = $_POST['adicional_descripcion'];
	$detalle_adicional_tmp = mysqli_query($con, "INSERT INTO adicional_tmp VALUES (null, '" . $id_usuario . "', '" . $serie_factura . "', '" . $secuencial_factura . "', '" . $concepto . "','" . $detalle . "')");
}

//para cuando se cambia de cliente
if (isset($_POST['cambia_cliente'])) {
	//no se hace nada solo actualiza los datos del cliente
}

//para eliminar una fila de info adicional de la factura
if (isset($_POST['id_adicional'])) {
	$id_info_adicional = intval($_POST['id_adicional']);
	$elimina_detalle_por_facturarse = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_ad_tmp='" . $id_info_adicional . "'");
}

//para actualizar un descuento en la factura actual
if (isset($_POST['id_tmp_descuento'])) {
	$id_descuento = intval($_POST["id_tmp_descuento"]);
	$valor_descuento = mysqli_real_escape_string($con, (strip_tags($_POST["valor_descuento"], ENT_QUOTES)));
	$precio = $_POST["subtotal_sin_impuesto"];
	$actualiza_descuento = mysqli_query($con, "UPDATE factura_tmp SET precio_tmp='" . $precio . "' / cantidad_tmp, descuento='" . $valor_descuento . "', subtotal= round(precio_tmp * cantidad_tmp,2) WHERE id='" . $id_descuento . "'");
}

//para actualizar un descuento en la factura actual
if (isset($_POST['aplicar_descuento_todos'])) {
	$porcentaje_descuento = $_POST["porcentaje_descuento"];
	$actualiza_descuento = mysqli_query($con, "UPDATE factura_tmp SET descuento= subtotal * '" . $porcentaje_descuento . "' /100, subtotal= round(precio_tmp * cantidad_tmp,2) WHERE id_usuario='" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "'");
}

//para eliminar un iten de la factura tmp
if (isset($_POST['id'])) {
	$id_tmp = intval($_POST['id']);
	$delete = mysqli_query($con, "DELETE FROM factura_tmp WHERE id='" . $id_tmp . "'");
}

//para agregar propina y tasa turistica
if (isset($_POST['agregar_propina_tasa'])) {
	$propina = $_POST['propina'];
	$tasa = $_POST['tasa'];
	$delete_propina_tasa_tmp = mysqli_query($con, "DELETE FROM propina_tasa_tmp WHERE id_usuario = '" . $id_usuario . "'");
	$detalle_propina_tasa_tmp = mysqli_query($con, "INSERT INTO propina_tasa_tmp VALUES (null, '" . $id_usuario . "', '" . $propina . "', '" . $tasa . "')");
}
?>
<div class="panel panel-info">
	<div class="table-responsive">
		<table class="table table-bordered">
			<tr class="info">
				<th class='text-center' style="padding: 2px;">Código</th>
				<th class='text-center' style="padding: 2px;">Cant.</th>
				<th style="padding: 2px;">Descripción</th>
				<th class='text-right' style="padding: 2px;">Precio Uni.</th>
				<th class='text-center' style="padding: 2px;">Descuento</th>
				<th class='text-right' style="padding: 2px;">IVA</th>
				<th class='text-right' style="padding: 2px;">Subtotal</th>
				<th class='text-right' style="padding: 2px;">Eliminar</th>
			</tr>
			<?php

			// PARA MOSTRAR LOS ITEMS DE LA FACTURA
			$subtotal_general = 0;
			$total_descuento = 0;
			$sql = mysqli_query($con, "SELECT ps.tarifa_iva as tarifa, ft.id as id_tmp, 
			ps.codigo_producto as codigo_producto, ft.cantidad_tmp as cantidad_tmp, ps.nombre_producto as nombre_producto, 
			ft.precio_tmp as precio_tmp, ft.descuento as descuento, uni_med.abre_medida as abre_medida, 
			bod.nombre_bodega as nombre_bodega, ft.vencimiento as vencimiento, ft.lote as lote, 
			ps.tipo_produccion as tipo_produccion, ft.subtotal as subtotal FROM factura_tmp as ft 
			INNER JOIN productos_servicios as ps ON ps.id=ft.id_producto 
			LEFT JOIN unidad_medida as uni_med ON uni_med.id_medida=ft.id_medida 
			LEFT JOIN bodega as bod ON bod.id_bodega=ft.id_bodega 
			WHERE ft.id_usuario = '" . $id_usuario . "' and ft.ruc_empresa = '" . $ruc_empresa . "' ");
			while ($row = mysqli_fetch_array($sql)) {
				$id_tmp = $row["id_tmp"];
				$codigo_producto = $row['codigo_producto'];
				$nombre_producto = $row['nombre_producto'];
				$nombre_lote = $row['lote'];
				$nombre_vencimiento = $row['vencimiento'];
				$vencimiento = $row['vencimiento'];
				$tipo_produccion = $row['tipo_produccion'];
				$medida = $row['abre_medida'];
				$bodega = $row['nombre_bodega'];
				//para saber si quiere que se imprima lote, bodega, vencimiento, 
				$sql_impresion = mysqli_query($con, "SELECT * FROM configuracion_facturacion where ruc_empresa ='" . $ruc_empresa . "' and serie_sucursal ='" . $serie_factura . "'");
				$row_impresion = mysqli_fetch_array($sql_impresion);
				$resultado_lote = isset($row_impresion['lote_impreso']);
				$resultado_medida = isset($row_impresion['medida_impreso']);
				$resultado_bodega = isset($row_impresion['bodega_impreso']);
				$resultado_vencimiento = isset($row_impresion['vencimiento_impreso']);

				if ($tipo_produccion == "01") {
					if ($resultado_lote == "SI") {
						$nombre_producto = $nombre_producto . " Lt " . $nombre_lote;
					}
					if ($resultado_medida == "SI") {
						$nombre_producto = $nombre_producto . " Md " . $medida;
					}

					if ($resultado_bodega == "SI") {
						$nombre_producto = $nombre_producto . " Bg " . $bodega;
					}

					if ($resultado_vencimiento == "SI") {
						$nombre_producto = $nombre_producto . " Vto " . date('d-m-Y', strtotime($vencimiento));;
					}
				}

				$cantidad = number_format($row['cantidad_tmp'], $decimal_cant, '.', '');
				$precio_venta = number_format($row['precio_tmp'], $decimal_precio, '.', '');
				$descuento = number_format($row['descuento'], 2, '.', '');
				$subtotal = number_format($row['subtotal'] - $descuento, 2, '.', '');
				$subtotal_general += number_format($subtotal, 2, '.', ''); //Sumador subtotal general
				$total_descuento += number_format($descuento, 2, '.', ''); //Sumador total descuento
				$codigo_tarifa = $row['tarifa'];
				//PARA MOStrar el nombre de la tarifa de iva
				$nombre_tarifa_iva = mysqli_query($con, "select * from tarifa_iva where codigo = '" . $codigo_tarifa . "'");
				$row_tarifa = mysqli_fetch_array($nombre_tarifa_iva);
				$nombre_tarifa = $row_tarifa['tarifa'];
				$tarifa = number_format($row_tarifa['porcentaje_iva'] + 100, 2, '.', '');
				$porcentaje_tarifa = number_format($row_tarifa['porcentaje_iva'] / 100, 2, '.', '');
				$subtotal_inicial = number_format($row['subtotal'], 2, '.', '');
				$total_sin_impuesto = number_format($subtotal_inicial, 2, '.', '');

				$total_con_impuesto = number_format($total_sin_impuesto + ($total_sin_impuesto * $porcentaje_tarifa), 2, '.', '');

			?>
				<input type="hidden" id="subtotal_item<?php echo $id_tmp; ?>" value="<?php echo number_format($subtotal + $descuento, 2, '.', ''); ?>">
				<input type="hidden" id="descuento_item<?php echo $id_tmp; ?>" value="<?php echo $descuento; ?>">
				<tr>
					<td class='text-center' style="padding: 2px;"><?php echo strtoupper($codigo_producto); ?></td>
					<td class='text-center' style="padding: 2px;"><?php echo $cantidad; ?></td>
					<td><?php echo $nombre_producto; ?></td>
					<td class='text-right' style="padding: 2px;"><?php echo $precio_venta; ?></td>
					<td class="col-sm-2" style="padding: 2px;">
						<div class="input-group">
							<input type="text" style="text-align:right;" class="form-control input-sm" title="Descuento" id="descuento<?php echo $id_tmp; ?>" onchange="aplicar_descuento_directo('<?php echo $id_tmp; ?>');" value="<?php echo $descuento; ?>">
							<span class="input-group-btn">
								<button class='btn btn-info' type="button" onclick="pasa_descuento('<?php echo $id_tmp; ?>', '<?php echo $subtotal_inicial; ?>', '<?php echo $serie_factura; ?>', '<?php echo $secuencial_factura; ?>', '<?php echo $id_cliente; ?>', '<?php echo $descuento; ?>', '<?php echo $total_con_impuesto; ?>', '<?php echo $total_sin_impuesto; ?>', '<?php echo $tarifa; ?>')" title='Opciones de descuento' data-toggle="modal" data-target="#aplicarDescuento"><span class="glyphicon glyphicon-flash"></span></button>
							</span>
						</div>
					</td>
					<td class='text-right' style="padding: 2px;"><?php echo $nombre_tarifa; ?></td>
					<td class='text-right' style="padding: 2px;"><?php echo $subtotal; ?></td>
					<td class='text-right' style="padding: 2px;">
						<a href="#" class='btn btn-danger btn-sm' onclick="eliminar_fila('<?php echo $id_tmp; ?>')" title="Eliminar item"><i class="glyphicon glyphicon-remove"></i></a>
					</td>
				</tr>
			<?php
			}
			?>
		</table>
	</div>
</div>

<!-- para mostrar los adicionales de la factura -->
<div class="row">
	<div class="col-md-6">
		<div class="panel panel-info">
			<div class="panel-heading">Información adicional</div>
			<td><?php
				include("../ajax/muestra_adicional_factura_tmp.php");
				$muestra_adicionales_factura = muestra_adicionales_factura($serie_factura, $secuencial_factura, $id_usuario, $con, $id_cliente);
				echo $muestra_adicionales_factura; ?>
			</td>
		</div>
	</div>
	<!-- para mostrar los subtotales -->
	<div class="col-md-6">
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table">
					<tr class="info">
						<td class='text-right'>SUBTOTAL GENERAL: </td>
						<td class='text-center'><?php echo number_format($subtotal_general, 2, '.', ''); ?></td>
						<!-- <td>
							<p style="display:none;">Base + impuesto</p>
						</td> -->
						<!-- <td class='text-right'><a class='btn btn-info btn-xs' title="Subtotal + impuesto"><span class="glyphicon glyphicon-option-horizontal"></span></a></td> -->
					</tr>
					<?php
					//PARA MOSTRAR LOS NOMBRES DE CADA TARIFA DE IVA Y LOS VALORES DE CADA SUBTOTAL
					$sql_tarifas_iva = mysqli_query($con, "SELECT sum(round((round(ft.cantidad_tmp,'" . $decimal_cant . "') * round(ft.precio_tmp, '" . $decimal_precio . "'))- ft.descuento,2)) as suma_tarifa_iva, 
					ti.porcentaje_iva as porcentaje_iva, ti.tarifa as tarifa, ft.cantidad_tmp as cantidad_tmp, 
					ft.precio_tmp as precio_tmp, ft.descuento as descuento_tmp 
					FROM factura_tmp as ft, tarifa_iva as ti 
					WHERE ti.codigo = ft.tarifa_iva and ft.id_usuario = '" . $id_usuario . "' and ft.ruc_empresa = '" . $ruc_empresa . "' 
					group by ft.tarifa_iva ");
					while ($row = mysqli_fetch_array($sql_tarifas_iva)) {
						$nombre_tarifa_iva = strtoupper($row["tarifa"]);
						$porcentaje_iva = number_format(1 + ($row["porcentaje_iva"] / 100), 2, '.', '');
						$subtotal_tarifa_iva = number_format($row['suma_tarifa_iva'], 2, '.', '');
					?>
						<tr class="info">
							<td class='text-right'>SUBTOTAL <?php echo ($nombre_tarifa_iva); ?>:</td>
							<td class='text-center'><?php echo number_format($subtotal_tarifa_iva, 2, '.', ''); ?></td>
							<!-- <td>
								<p style="display:none;"><?php echo number_format($subtotal_tarifa_iva * $porcentaje_iva, 2, '.', ''); ?></p>
							</td> -->
							<!-- <td></td> -->
							<input type="hidden" id="propina_calculada" value="<?php echo number_format($subtotal_general * 0.10, 2, '.', ''); ?>">
							<!-- AQUI VA 0.10 PORQUE ES EL VALOR DE SERVICIO-->
						</tr>

					<?php
					}
					?>
					<tr class="info">
						<td class='text-right'>TOTAL DESCUENTO: </td>
						<td class='text-center'><?php echo number_format($total_descuento, 2, '.', ''); ?></td>
					</tr>
					<?php
					//PARA MOSTRAR LOS IVAS
					$total_iva = 0;
					$subtotal_porcentaje_iva = 0;
					$sql_iva = mysqli_query($con, "SELECT ti.tarifa as tarifa, (sum(round(ft.cantidad_tmp,'" . $decimal_cant . "') * round(ft.precio_tmp,'" . $decimal_precio . "') - descuento) * ti.tarifa /100)  as porcentaje 
					FROM factura_tmp ft, tarifa_iva ti 
					where ti.codigo = ft.tarifa_iva and ft.id_usuario = '" . $id_usuario . "' and ft.ruc_empresa = '" . $ruc_empresa . "' and ti.tarifa > 0 group by ft.tarifa_iva ");
					while ($row = mysqli_fetch_array($sql_iva)) {
						$nombre_porcentaje_iva = strtoupper($row["tarifa"]);
						$porcentaje_iva = $row['porcentaje'];
						$subtotal_porcentaje_iva = $porcentaje_iva;
						$total_iva += number_format($subtotal_porcentaje_iva, 2, '.', '');
					?>
						<tr class="info">
							<td class='text-right'>IVA <?php echo ($nombre_porcentaje_iva); ?>:</td>
							<td class='text-center'><?php echo number_format($subtotal_porcentaje_iva, 2, '.', ''); ?></td>
						</tr>
					<?php
					}
					//para mostrar la casilla de propina dependiendo si esta asignada o no
					$propina = mysqli_query($con, "SELECT * FROM configuracion_facturacion where ruc_empresa ='" . $ruc_empresa . "' and serie_sucursal ='" . $serie_factura . "'");
					$row_propina = mysqli_fetch_array($propina);
					$resultado_propina = isset($row_propina['propina']) ? $row_propina['propina'] : 0;
					$resultado_tasa = isset($row_propina['tasa_turistica']) ? $row_propina['tasa_turistica'] : 0;

					$total_propina = 0; //empty($row_propina_tasa['propina'])?0:$row_propina_tasa['propina'];
					$total_tasa = 0; //empty($row_propina_tasa['tasa'])?0:$row_propina_tasa['tasa'];


					if ($resultado_propina == "SI") {
					?>
						<tr class="info">
							<td class='text-right'>SERVICIO: </td>
							<td class="col-sm-3">
								<div class="input-group">
									<input class="form-control text-center input-sm" type="text" id="propina" value="<?php echo number_format($total_propina, 2, '.', ''); ?>" onchange="aplica_propina_tasa();" title="Ingrese el valor del servicio o propina y presione enter">
									<span class="input-group-btn btn-md"><button onclick="calcular_propina()" class="btn btn-info btn-md" type="button" title="calcular 10%"><span class="glyphicon glyphicon-option-vertical"></span></button></span>
								</div>
							</td>
						</tr>
					<?php
					}
					if ($resultado_tasa == "SI") {
					?>
						<tr class="info">
							<td class='text-right'>TASA TURISTICA: </td>
							<td class='col-sm-1'>
								<input class="form-control text-center input-sm" type="text" id="tasa_turistica" value="<?php echo number_format($total_tasa, 2, '.', ''); ?>" onchange="aplica_propina_tasa();" title="Ingrese el valor de la tasa y presione enter">
							</td>
						</tr>
					<?php
					}
					?>

					<tr class="info">
						<td class='text-right'>TOTAL: </td>
						<td class='text-center'><?php echo number_format($subtotal_general + $total_iva + $total_propina + $total_tasa, 2, '.', ''); ?></td>
					</tr>
					<input type="hidden" id="suma_factura" value="<?php echo number_format($subtotal_general + $total_iva + $total_propina + $total_tasa, 2, '.', ''); ?>">
				</table>
			</div>
		</div>
	</div>
</div>

<script>
	//para aparecer y desaparecer
	/* $(document).ready(function() {

		$("a").click(function() {
			$("p").toggle();
		});

	}); */
</script>