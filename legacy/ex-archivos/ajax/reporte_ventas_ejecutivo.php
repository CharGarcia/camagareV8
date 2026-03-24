<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$tipo_reporte = $_POST['tipo_reporte'];
$anio = $_POST['anio'];
$id_marca = $_POST['id_marca'];
$id_producto = $_POST['id_producto'];
$id_cliente = $_POST['id_cliente'];
ini_set('date.timezone', 'America/Guayaquil');

if ($action == 'reporte_ventas_ejecutivo') {

	if (empty($id_producto)) {
		$condicion_producto = "";
	} else {
		$condicion_producto = " and cue_fac.id_producto=" . $id_producto;
	}

	if (empty($id_cliente)) {
		$condicion_cliente = "";
	} else {
		$condicion_cliente = " and enc_fac.id_cliente=" . $id_cliente;
	}

	if (empty($id_marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $id_marca;
	}

	if ($tipo_reporte == '1') {
		$condicion_datos = "sum(cue_fac.cantidad_factura) as cantidad";
		$opcionUno = "Unidades";
		$opcionDos = "Precio promedio";
	} else {
		$condicion_datos = "sum(cue_fac.subtotal_factura-descuento) as cantidad";
		$opcionUno = "Ventas";
		$opcionDos = "Precio promedio";
	}
	//limpiar la tabla

	$resultado_productos = datos_productos($con, $condicion_datos, $ruc_empresa, $anio, $condicion_producto, $condicion_marca, $condicion_cliente);

?>
	<div class="panel panel-info">
		<div class="table-responsive">
			<table class="table table-bordered table-hover">
				<tr class="info">
					<th>Código</th>
					<th>Producto</th>
					<th>Detalle</th>
					<th>Ene</th>
					<th>Feb</th>
					<th>Mar</th>
					<th>Abr</th>
					<th>May</th>
					<th>Jun</th>
					<th>Jul</th>
					<th>Ago</th>
					<th>Sep</th>
					<th>Oct</th>
					<th>Nov</th>
					<th>Dic</th>
					<th>Suma General</th>
					<th>Promedio General</th>
				</tr>
				<?php

				$suma_ene = 0;
				$suma_feb = 0;
				$suma_mar = 0;
				$suma_abr = 0;
				$suma_may = 0;
				$suma_jun = 0;
				$suma_jul = 0;
				$suma_ago = 0;
				$suma_sep = 0;
				$suma_oct = 0;
				$suma_nov = 0;
				$suma_dic = 0;
				$suma_total = 0;
				$suma_general = 0;
				$suma_cantidad_por_precio = 0;

				if ($tipo_reporte == '1') {
					$decimal = 0;
				} else {
					$decimal = 2;
				}

				while ($row = mysqli_fetch_array($resultado_productos)) {
					$codigo = $row['codigo_producto'];
					$producto = $row['nombre_producto'];
					$id_producto = $row['anio'];

					$resultado = resultado_fila($con, $id_producto);

					$row_result = mysqli_fetch_array($resultado);
					$suma_total = $row_result['cantidad_ene'] +
						$row_result['cantidad_feb'] +
						$row_result['cantidad_mar'] +
						$row_result['cantidad_abr'] +
						$row_result['cantidad_may'] +
						$row_result['cantidad_jun'] +
						$row_result['cantidad_jul'] +
						$row_result['cantidad_ago'] +
						$row_result['cantidad_sep'] +
						$row_result['cantidad_oct'] +
						$row_result['cantidad_nov'] +
						$row_result['cantidad_dic'];
					$suma_general += $suma_total;

					$suma_ene += $row_result['cantidad_ene'];
					$suma_feb += $row_result['cantidad_feb'];
					$suma_mar += $row_result['cantidad_mar'];
					$suma_abr += $row_result['cantidad_abr'];
					$suma_may += $row_result['cantidad_may'];
					$suma_jun += $row_result['cantidad_jun'];
					$suma_jul += $row_result['cantidad_jul'];
					$suma_ago += $row_result['cantidad_ago'];
					$suma_sep += $row_result['cantidad_sep'];
					$suma_oct += $row_result['cantidad_oct'];
					$suma_nov += $row_result['cantidad_nov'];
					$suma_dic += $row_result['cantidad_dic'];

					$array_precio_promedio = array(
						$row_result['precio_ene'],
						$row_result['precio_feb'],
						$row_result['precio_mar'],
						$row_result['precio_abr'],
						$row_result['precio_may'],
						$row_result['precio_jun'],
						$row_result['precio_jul'],
						$row_result['precio_ago'],
						$row_result['precio_sep'],
						$row_result['precio_oct'],
						$row_result['precio_nov'],
						$row_result['precio_dic']
					);

					$suma_precios_promedio = array_sum($array_precio_promedio);

					$contador = 0;
					foreach ($array_precio_promedio as $numero) {
						if ($numero > 0) {
							$contador++;
						}
					}
					if ($contador > 0) {
						$precio_promedio_fila = $suma_precios_promedio / $contador;
					} else {
						$precio_promedio_fila = 0;
					}

				?>
					<tr>
						<td rowspan="2" align="left"><?php echo $codigo; ?></td>
						<td rowspan="2" align="left"><?php echo $producto; ?></td>
						<td><?php echo $opcionUno; ?></td>
						<td align="right"><?php echo $row_result['cantidad_ene'] > 0 ? number_format($row_result['cantidad_ene'], $decimal, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['cantidad_feb'] > 0 ? number_format($row_result['cantidad_feb'], $decimal, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['cantidad_mar'] > 0 ? number_format($row_result['cantidad_mar'], $decimal, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['cantidad_abr'] > 0 ? number_format($row_result['cantidad_abr'], $decimal, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['cantidad_may'] > 0 ? number_format($row_result['cantidad_may'], $decimal, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['cantidad_jun'] > 0 ? number_format($row_result['cantidad_jun'], $decimal, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['cantidad_jul'] > 0 ? number_format($row_result['cantidad_jul'], $decimal, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['cantidad_ago'] > 0 ? number_format($row_result['cantidad_ago'], $decimal, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['cantidad_sep'] > 0 ? number_format($row_result['cantidad_sep'], $decimal, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['cantidad_oct'] > 0 ? number_format($row_result['cantidad_oct'], $decimal, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['cantidad_nov'] > 0 ? number_format($row_result['cantidad_nov'], $decimal, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['cantidad_dic'] > 0 ? number_format($row_result['cantidad_dic'], $decimal, '.', '') : ""; ?></td>
						<td align="right"><?php echo number_format($suma_total, $decimal, '.', ''); ?></td>
						<td align="right"></td>
					</tr>
					<tr>
						<td><?php echo $opcionDos; ?></td>
						<td align="right"><?php echo $row_result['precio_ene'] > 0 ? number_format($row_result['precio_ene'], 2, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['precio_feb'] > 0 ? number_format($row_result['precio_feb'], 2, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['precio_mar'] > 0 ? number_format($row_result['precio_mar'], 2, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['precio_abr'] > 0 ? number_format($row_result['precio_abr'], 2, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['precio_may'] > 0 ? number_format($row_result['precio_may'], 2, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['precio_jun'] > 0 ? number_format($row_result['precio_jun'], 2, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['precio_jul'] > 0 ? number_format($row_result['precio_jul'], 2, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['precio_ago'] > 0 ? number_format($row_result['precio_ago'], 2, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['precio_sep'] > 0 ? number_format($row_result['precio_sep'], 2, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['precio_oct'] > 0 ? number_format($row_result['precio_oct'], 2, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['precio_nov'] > 0 ? number_format($row_result['precio_nov'], 2, '.', '') : ""; ?></td>
						<td align="right"><?php echo $row_result['precio_dic'] > 0 ? number_format($row_result['precio_dic'], 2, '.', '') : ""; ?></td>
						<td align="right"></td>
						<td align="right"><?php echo number_format($precio_promedio_fila, 2, '.', ''); ?></td>
					</tr>
				<?php
				}
				?>
				<tr>
					<td colspan="3" align="right">Total <?php echo $opcionUno ?></td>
					<td align="right"><?php echo number_format($suma_ene, $decimal, '.', ''); ?></td>
					<td align="right"><?php echo number_format($suma_feb, $decimal, '.', ''); ?></td>
					<td align="right"><?php echo number_format($suma_mar, $decimal, '.', ''); ?></td>
					<td align="right"><?php echo number_format($suma_abr, $decimal, '.', ''); ?></td>
					<td align="right"><?php echo number_format($suma_may, $decimal, '.', ''); ?></td>
					<td align="right"><?php echo number_format($suma_jun, $decimal, '.', ''); ?></td>
					<td align="right"><?php echo number_format($suma_jul, $decimal, '.', ''); ?></td>
					<td align="right"><?php echo number_format($suma_ago, $decimal, '.', ''); ?></td>
					<td align="right"><?php echo number_format($suma_sep, $decimal, '.', ''); ?></td>
					<td align="right"><?php echo number_format($suma_oct, $decimal, '.', ''); ?></td>
					<td align="right"><?php echo number_format($suma_nov, $decimal, '.', ''); ?></td>
					<td align="right"><?php echo number_format($suma_dic, $decimal, '.', ''); ?></td>
					<td align="right"><?php echo number_format($suma_general, $decimal, '.', ''); ?></td>
					<td></td>
				</tr>

			</table>
		</div>
	</div>

<?php
}

function datos_productos($con, $condicion_datos, $ruc_empresa, $anio, $condicion_producto, $condicion_marca, $condicion_cliente)
{
	$delete_tabla = mysqli_query($con, "DELETE FROM reportes_graficos WHERE ruc_empresa = '" . $ruc_empresa . "'");
	$detalle_ventas = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, '" . $ruc_empresa . "', cue_fac.id_producto, month(enc_fac.fecha_factura) as mes, $condicion_datos, 
	AVG(cue_fac.subtotal_factura-descuento) as promedio
	FROM cuerpo_factura as cue_fac INNER JOIN encabezado_factura as enc_fac ON enc_fac.serie_factura=cue_fac.serie_factura 
	and enc_fac.secuencial_factura=cue_fac.secuencial_factura WHERE cue_fac.ruc_empresa='" . $ruc_empresa . "' 
	and enc_fac.ruc_empresa='" . $ruc_empresa . "' and year(enc_fac.fecha_factura)='" . $anio . "' $condicion_producto $condicion_cliente
	group by cue_fac.id_producto, month(enc_fac.fecha_factura))");

	$delete_tabla = mysqli_query($con, "DELETE FROM reportes_graficos WHERE ruc_empresa = '" . $ruc_empresa . "' and valor_entrada=0 and valor_salida=0");

	$resultado_productos = mysqli_query($con, "SELECT DISTINCT rep.anio, pro_ser.nombre_producto as nombre_producto, 
	pro_ser.codigo_producto as codigo_producto FROM reportes_graficos as rep INNER JOIN 
	productos_servicios as pro_ser ON rep.anio=pro_ser.id LEFT JOIN marca_producto as mar_pro 
	ON mar_pro.id_producto=pro_ser.id WHERE pro_ser.ruc_empresa='" . $ruc_empresa . "' $condicion_marca 
	order by pro_ser.codigo_producto asc");
	return $resultado_productos;
}


function resultado_fila($con, $id_producto)
{
	$resultado = mysqli_query($con, "SELECT 
						SUM(CASE WHEN mes = 1 THEN valor_entrada ELSE 0 END) AS cantidad_ene,
  						SUM(CASE WHEN mes = 1 THEN valor_salida ELSE 0 END) AS precio_ene,
						SUM(CASE WHEN mes = 2 THEN valor_entrada ELSE 0 END) AS cantidad_feb,
  						SUM(CASE WHEN mes = 2 THEN valor_salida ELSE 0 END) AS precio_feb,
						SUM(CASE WHEN mes = 3 THEN valor_entrada ELSE 0 END) AS cantidad_mar,
  						SUM(CASE WHEN mes = 3 THEN valor_salida ELSE 0 END) AS precio_mar,
						SUM(CASE WHEN mes = 4 THEN valor_entrada ELSE 0 END) AS cantidad_abr,
  						SUM(CASE WHEN mes = 4 THEN valor_salida ELSE 0 END) AS precio_abr,
						SUM(CASE WHEN mes = 5 THEN valor_entrada ELSE 0 END) AS cantidad_may,
  						SUM(CASE WHEN mes = 5 THEN valor_salida ELSE 0 END) AS precio_may,
						SUM(CASE WHEN mes = 6 THEN valor_entrada ELSE 0 END) AS cantidad_jun,
  						SUM(CASE WHEN mes = 6 THEN valor_salida ELSE 0 END) AS precio_jun,
						SUM(CASE WHEN mes = 7 THEN valor_entrada ELSE 0 END) AS cantidad_jul,
  						SUM(CASE WHEN mes = 7 THEN valor_salida ELSE 0 END) AS precio_jul,
						SUM(CASE WHEN mes = 8 THEN valor_entrada ELSE 0 END) AS cantidad_ago,
  						SUM(CASE WHEN mes = 8 THEN valor_salida ELSE 0 END) AS precio_ago,
						SUM(CASE WHEN mes = 9 THEN valor_entrada ELSE 0 END) AS cantidad_sep,
  						SUM(CASE WHEN mes = 9 THEN valor_salida ELSE 0 END) AS precio_sep,
						SUM(CASE WHEN mes = 10 THEN valor_entrada ELSE 0 END) AS cantidad_oct,
  						SUM(CASE WHEN mes = 10 THEN valor_salida ELSE 0 END) AS precio_oct,
						SUM(CASE WHEN mes = 11 THEN valor_entrada ELSE 0 END) AS cantidad_nov,
  						SUM(CASE WHEN mes = 11 THEN valor_salida ELSE 0 END) AS precio_nov,
						SUM(CASE WHEN mes = 12 THEN valor_entrada ELSE 0 END) AS cantidad_dic,
  						SUM(CASE WHEN mes = 12 THEN valor_salida ELSE 0 END) AS precio_dic
						FROM reportes_graficos WHERE anio='" . $id_producto . "' GROUP BY anio ");
	return $resultado;
}
?>