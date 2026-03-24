<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
include_once("../clases/saldo_producto_y_conversion.php");
include("../ajax/pagination.php");
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$con = conenta_login();

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'limpiar_tabla_tmp') {
	$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and id_usuario ='" . $id_usuario . "'");
}


if ($action == 'mostrar_consulta') {
	$desde = date('Y/m/d', strtotime($_GET['fecha_desde']));
	$hasta = date('Y/m/d', strtotime($_GET['fecha_hasta']));

	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$tipo = mysqli_real_escape_string($con, (strip_tags($_GET['tipo'], ENT_QUOTES)));
	$producto = mysqli_real_escape_string($con, (strip_tags($_GET['producto'], ENT_QUOTES)));
	$nombre_producto = mysqli_real_escape_string($con, (strip_tags($_GET['nombre_producto'], ENT_QUOTES)));
	$marca = mysqli_real_escape_string($con, (strip_tags($_GET['marca'], ENT_QUOTES)));
	$lote = mysqli_real_escape_string($con, (strip_tags($_GET['lote'], ENT_QUOTES)));
	$caducidad = mysqli_real_escape_string($con, (strip_tags($_GET['caducidad'], ENT_QUOTES)));
	$referencia = mysqli_real_escape_string($con, (strip_tags($_GET['referencia'], ENT_QUOTES)));
	$bodega = mysqli_real_escape_string($con, (strip_tags($_GET['bodega'], ENT_QUOTES)));

	switch ($tipo) {
		case "1": //entradas
			$data = entradas($producto, $ordenado, $por, $ruc_empresa, $con, $desde, $hasta, $marca, $lote, $caducidad, $referencia, $bodega);
			entradas_view($data);
			break;
		case "2": //salidas
			$data = salidas($producto, $ordenado, $por, $ruc_empresa, $con, $desde, $hasta, $marca, $lote, $caducidad, $referencia, $bodega);
			salidas_view($data);
			break;
		case "3": //existencia en general
			$data = existencia_general($producto, $ordenado, $por, $ruc_empresa, $con, $hasta, $marca, $bodega, $id_usuario);
			existencia_general_view($data);
			break;
		case "4": //existencia caducidad
			$data = existencia_caducidad($producto, $ruc_empresa, $con, $hasta, $id_usuario, $marca, $caducidad, $bodega);
			existencia_caducidad_view($data);
			break;
		case "5": //existencia lote
			$data = existencia_lote($producto, $ordenado, $por, $ruc_empresa, $con, $hasta, $id_usuario, $marca, $lote, $bodega);
			existencia_lote_view($data);
			break;
		case "6": //existencia + consignacion
			$data = existencia_consignacion($producto, $ordenado, $por, $ruc_empresa, $con, $hasta, $id_usuario, $marca, $bodega);
			existencia_consignacion_view($data);
			break;
		case "7": //kardex
			$data = kardex($nombre_producto, $producto, $ruc_empresa, $con, $desde, $hasta, $marca, $lote, $bodega);
			kardex_view($data);
			break;
		case "8": //existencia + consignacion + lote
			$data = existencia_consignacion_lote($producto, $ordenado, $por, $ruc_empresa, $con, $hasta, $id_usuario, $marca, $lote, $bodega);
			existencia_consignacion_lote_view($data);
			break;
		case "9": //costo venta promedio
			$data = costo_venta_promedio($producto, $ordenado, $por, $ruc_empresa, $con, $desde, $hasta, $id_usuario, $marca, $lote, $caducidad, $bodega);
			costo_venta_promedio_view($data);
			break;
		case "10": //establecer minimos de inventarios
			$data = minimos($producto, $ordenado, $por, $ruc_empresa, $con, $id_usuario, $marca, $lote, $caducidad, $bodega);
			minimos_view($data);
			break;
		case "11": //existencia + consignacion + caducidad
			$data = existencia_consignacion_caducidad($producto, $ordenado, $por, $ruc_empresa, $con, $hasta, $id_usuario, $marca, $caducidad, $bodega);
			existencia_consignacion_caducidad_view($data);
			break;
	}
}


function costo_venta_promedio($producto, $ordenado, $por, $ruc_empresa, $con, $desde, $hasta, $id_usuario, $marca, $lote, $caducidad, $bodega)
{
	$saldo_producto = new saldo_producto_y_conversion();
	if (empty($bodega)) {
		$condicion_bodega = "";
	} else {
		$condicion_bodega = " and id_bodega=" . $bodega;
	}
	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}
	if (empty($producto)) {
		$condicion_producto = "";
	} else {
		$condicion_producto = "and id_producto='" . $producto . "'";
	}

	if (empty($lote)) {
		$condicion_lote = "";
	} else {
		$condicion_lote = "and lote LIKE '%" . $lote . "%' ";
	}

	if (empty($caducidad)) {
		$condicion_caducidad = "";
	} else {
		$condicion_caducidad = "and fecha_vencimiento LIKE '%" . $caducidad . "%' ";
	}

	$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and id_usuario ='" . $id_usuario . "'");
	$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
	SELECT null, id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada), sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida), lote, '" . $id_usuario . "' FROM inventarios WHERE ruc_empresa ='" . $ruc_empresa . "' and DATE_FORMAT(fecha_registro, '%Y/%m/%d') between '" . $desde . "' and '" . $hasta . "' 
	$condicion_producto $condicion_bodega $condicion_lote $condicion_caducidad group by id_producto, id_medida, id_bodega");

	//selet para buscar linea por linea y ver si la medida es igual al producto o sino modificar esa linea
	$resultado = array();
	$sql_filas = mysqli_query($con, "SELECT * FROM existencias_inventario_tmp as exi_tmp 
	LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=exi_tmp.id_producto 
	WHERE exi_tmp.ruc_empresa = '" . $ruc_empresa . "' and exi_tmp.id_usuario = '" . $id_usuario . "' 
	and exi_tmp.cantidad_salida>0 ");
	while ($row_temporales = mysqli_fetch_array($sql_filas)) {
		$id_producto = $row_temporales["id_producto"];
		$codigo_producto = $row_temporales["codigo_producto"];
		$nombre_producto = $row_temporales["nombre_producto"];
		//obtener medida del producto
		$id_medida_salida = $row_temporales['id_unidad_medida'];

		$id_medida_entrada = $row_temporales["id_medida"];
		$cantidad_entrada_tmp = $row_temporales['cantidad_entrada'];
		$id_bodega = $row_temporales['id_bodega'];
		$caducidad = $row_temporales['fecha_caducidad'];
		$lote = $row_temporales['lote'];

		if ($id_medida_entrada != $id_medida_salida) {
			$id_tmp = $row_temporales["id_existencia_tmp"];
			$cantidad_a_transformar = $row_temporales['cantidad_salida'];
			$total_saldo_producto = $saldo_producto->conversion($id_medida_entrada, $id_medida_salida, $id_producto, '0', $cantidad_a_transformar, $con, 'saldo');
			$resultado[] = array('id_tmp' => $id_tmp, 'id_producto' => $id_producto, 'codigo_producto' => $codigo_producto, 'nombre_producto' => $nombre_producto, 'entrada' => $cantidad_entrada_tmp, 'salida' => $cantidad_a_transformar, 'id_bodega' => $id_bodega, 'id_medida' => $id_medida_salida, 'caducidad' => $caducidad, 'saldo_convertido' => $total_saldo_producto, 'lote' => $lote);
		}
	}
	foreach ($resultado as $valor) {
		$delete_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='" . $valor['id_tmp'] . "';");
		$sql_actualizar = mysqli_query($con, "INSERT INTO existencias_inventario_tmp VALUES (null,'" . $valor['id_producto'] . "','" . $valor['codigo_producto'] . "','" . $valor['nombre_producto'] . "','" . $valor['entrada'] . "','" . $valor['saldo_convertido'] . "','" . $valor['id_bodega'] . "','" . $valor['id_medida'] . "','" . $valor['caducidad'] . "', '" . $ruc_empresa . "', '" . $valor['saldo_convertido'] . "','" . $valor['lote'] . "','" . $id_usuario . "' )");
	}
	//todos los id temporales traidos para luego borrarlos
	$ides = array();
	$sql_filas_borrar = mysqli_query($con, "SELECT * FROM existencias_inventario_tmp 
	WHERE ruc_empresa = '" . $ruc_empresa . "' and id_usuario = '" . $id_usuario . "'");
	while ($row_ides_temporales = mysqli_fetch_array($sql_filas_borrar)) {
		$id_temp_iniciales = $row_ides_temporales["id_existencia_tmp"];
		$ides[] = array('id_tmp_iniciales' => $id_temp_iniciales);
	}
	$query_actualiza_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
		SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada), sum(cantidad_salida), id_bodega, id_medida, fecha_caducidad, ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote, '" . $id_usuario . "'  
		FROM existencias_inventario_tmp WHERE ruc_empresa ='" . $ruc_empresa . "' and id_usuario ='" . $id_usuario . "' group by id_bodega, id_producto, id_medida");
	//eliminar los ides tmp iniciales
	foreach ($ides as $id_tm) {
		$delete_ides_tmp_iniciales = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='" . $id_tm['id_tmp_iniciales'] . "';");
	}

	$sTable = "existencias_inventario_tmp as inv INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . $ruc_empresa . "' and inv.id_usuario ='" . $id_usuario . "' $condicion_marca order by $ordenado $por";

	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../reporte_inventarios.php';
	//main query to fetch the data
	$sql = "SELECT inv.id_producto as id_producto, mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, inv.nombre_producto as nombre_producto, inv.cantidad_entrada as cantidad_entrada, inv.cantidad_salida as cantidad_salida, 
	   inv.saldo_producto as existencia, med.nombre_medida as medida, bod.nombre_bodega as bodega FROM $sTable $sWhere LIMIT $offset, $per_page";
	$query = mysqli_query($con, $sql);
	$data = array('desde' => $desde, 'hasta' => $hasta, 'con' => $con, 'query' => $query, 'reload' => $reload, 'page' => $page, 'total_pages' => $total_pages, 'adjacents' => $adjacents, 'numrows' => $numrows);
	return $data;
}
function costo_venta_promedio_view($data)
{
	$query = $data['query'];
	$reload = $data['reload'];
	$page = $data['page'];
	$total_pages = $data['total_pages'];
	$adjacents = $data['adjacents'];
	$numrows = $data['numrows'];
	if ($numrows > 0) {
		//loop through fetched data
?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Producto</button></th>
						<th style="padding: 0px;" class="text-right"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Costo_promedio</button></th>
						<th style="padding: 0px;" class="text-right"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Venta_promedio</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_marca");'>Marca</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Medida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Bodega</button></th>
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
						$id_producto = $row['id_producto'];
						$sql_costo_promedio = mysqli_query($data['con'], "SELECT AVG(costo_unitario) as costo_promedio FROM inventarios WHERE id_producto = '" . $id_producto . "' and DATE_FORMAT(fecha_registro, '%Y/%m/%d') between '" . $data['desde'] . "' and '" . $data['hasta'] . "' and operacion ='ENTRADA' and costo_unitario >0 ");
						$row_costo_promedio = mysqli_fetch_array($sql_costo_promedio);

						$sql_venta_promedio = mysqli_query($data['con'], "SELECT AVG(precio) as precio_promedio FROM inventarios WHERE id_producto = '" . $id_producto . "' and DATE_FORMAT(fecha_registro, '%Y/%m/%d') between '" . $data['desde'] . "' and '" . $data['hasta'] . "' and operacion ='SALIDA' and precio > 0 ");
						$row_venta_promedio = mysqli_fetch_array($sql_venta_promedio);

						if ($row['existencia'] > 0) {
							$existencia = "<span class='label label-success'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						} else {
							$existencia = "<span class='label label-danger'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						}
					?>
						<tr>
							<td><?php echo strtoupper($row['codigo_producto']); ?></td>
							<td class="col-xs-2"><?php echo strtoupper($row['nombre_producto']); ?></td>
							<td class="text-center"><?php echo number_format($row_costo_promedio['costo_promedio'], 4, '.', ''); ?></td>
							<td class="text-center"><?php echo number_format($row_venta_promedio['precio_promedio'], 4, '.', ''); ?></td>
							<td><?php echo strtoupper($row['marca']); ?></td>
							<td><?php echo strtoupper($row['medida']); ?></td>
							<td><?php echo strtoupper($row['bodega']); ?></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="10"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?>
							</span></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	} else {
	?>
		<div class="alert alert-danger" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Mensaje! </strong>
			<?php
			echo "No hay datos para mostrar.";
			?>
		</div>
		<?php
	}
}


function entradas($producto, $ordenado, $por, $ruc_empresa, $con, $desde, $hasta, $marca, $lote, $caducidad, $referencia, $bodega)
{
	if (empty($bodega)) {
		$condicion_bodega = "";
	} else {
		$condicion_bodega = " and inv.id_bodega =" . $bodega;
	}
	if (empty($producto)) {
		$condicion_producto = "";
	} else {
		$condicion_producto = " and inv.id_producto =" . $producto;
	}

	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}

	if (empty($lote)) {
		$condicion_lote = "";
	} else {
		$condicion_lote = " and inv.lote LIKE '%" . $lote . "%' ";
	}

	if (empty($caducidad)) {
		$condicion_caducidad = "";
	} else {
		$condicion_caducidad = " and inv.fecha_vencimiento LIKE '%" . $caducidad . "%' ";
	}

	if (empty($referencia)) {
		$condicion_referencia = "";
	} else {
		$condicion_referencia = " and inv.referencia LIKE '%" . $referencia . "%' ";
	}
	$sWhere = " WHERE inv.ruc_empresa ='" . $ruc_empresa . " ' and inv.operacion='ENTRADA' 
		and DATE_FORMAT(inv.fecha_registro, '%Y/%m/%d') BETWEEN '" . $desde . "' and '" . $hasta . "' 
		and inv.cantidad_entrada > 0 $condicion_producto $condicion_bodega $condicion_marca $condicion_lote $condicion_caducidad $condicion_referencia  
		order by $ordenado $por";

	$sTable = "inventarios as inv INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida 
	INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega 
	INNER JOIN usuarios as usu ON usu.id=inv.id_usuario 
	LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto 
	LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";

	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../reporte_inventarios.php';
	//main query to fetch the data
	$query = mysqli_query($con, "SELECT mar.nombre_marca as marca, round(inv.costo_unitario, 4) as costo_unitario, inv.codigo_producto as codigo_producto, inv.nombre_producto as nombre_producto, round(inv.cantidad_entrada,4) as cantidad_entrada, med.nombre_medida as medida, inv.referencia as referencia, bod.nombre_bodega as bodega,
   inv.fecha_registro as fecha_registro, inv.fecha_vencimiento as fecha_vencimiento, inv.lote as lote, usu.nombre as usuario
   FROM $sTable $sWhere LIMIT $offset, $per_page");
	$data = array('query' => $query, 'reload' => $reload, 'page' => $page, 'total_pages' => $total_pages, 'adjacents' => $adjacents, 'numrows' => $numrows);
	return $data;
}


function kardex($nombre_producto, $producto, $ruc_empresa, $con, $desde, $hasta, $marca, $lote, $bodega)
{
	if (empty($bodega)) {
		$condicion_bodega = "";
	} else {
		$condicion_bodega = " and inv.id_bodega =" . $bodega;
	}
	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}

	if (empty($lote)) {
		$condicion_lote = "";
	} else {
		$condicion_lote = " and inv.lote LIKE '%" . $lote . "%' ";
	}

	$sWhere = " WHERE inv.ruc_empresa ='" . $ruc_empresa . " ' and 
		DATE_FORMAT(inv.fecha_registro, '%Y/%m/%d') <= '" . $hasta . "' 
		and inv.id_producto ='" . $producto . "' and (inv.cantidad_entrada + inv.cantidad_salida) > 0 $condicion_bodega $condicion_marca $condicion_lote    
		 order by inv.fecha_registro desc, inv.cantidad_entrada asc";

	$sTable = "inventarios as inv INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida 
	INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega
	LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto 
	LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";

	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 5; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../reporte_inventarios.php';
	//main query to fetch the data

	$sql = "SELECT inv.fecha_registro as fecha_registro, inv.referencia as referencia, round(inv.cantidad_entrada,4) as entrada,
   round(inv.costo_unitario,4) as costo, round(inv.cantidad_entrada * inv.costo_unitario,2) as total_entrada, 
   round(inv.cantidad_salida,4) as salida, round(inv.precio,4) as precio, round(inv.cantidad_salida * inv.precio,2) as total_salida,
   (SELECT round(SUM(CASE WHEN operacion = 'ENTRADA' THEN cantidad_entrada ELSE -cantidad_salida END),4)
        FROM inventarios AS i2 WHERE i2.fecha_registro <= inv.fecha_registro AND i2.id_producto = inv.id_producto) AS saldo_acumulado,
	(SELECT round(AVG(i3.precio),4)
        FROM inventarios AS i3
        WHERE i3.fecha_registro <= inv.fecha_registro AND i3.id_producto = inv.id_producto and i3.precio>0) AS precio_promedio
    FROM $sTable $sWhere LIMIT $offset, $per_page";
	$query = mysqli_query($con, $sql);
	$data = array('query' => $query, 'reload' => $reload, 'page' => $page, 'total_pages' => $total_pages, 'adjacents' => $adjacents, 'numrows' => $numrows, 'id_producto' => $producto);
	return $data;
}


function kardex_view($data)
{
	$query = $data['query'];
	$reload = $data['reload'];
	$page = $data['page'];
	$total_pages = $data['total_pages'];
	$adjacents = $data['adjacents'];
	$numrows = $data['numrows'];
	$id_producto = $data['id_producto'];
	//loop through fetched data
	if (!empty($id_producto)) {
		if ($numrows > 0) {
		?>
			<div class="table-responsive">
				<div class="panel panel-info">
					<table class="table table-hover">
						<thead>
							<tr class="info">
								<th style="padding: 0px;" rowspan="2">Fecha</th>
								<th style="padding: 0px;" rowspan="2">Detalle</th>
								<th class="text-center" style="padding: 0px;" colspan="3">Entradas</th>
								<th class="text-center" style="padding: 0px;" colspan="3">Salidas</th>
								<th class="text-center" style="padding: 0px;" colspan="3">Existencias</th>
							</tr>
							<tr class="info">
								<th class="text-center">Cantidad</th>
								<th class="text-center">Precio</th>
								<th class="text-center">Total</th>
								<th class="text-center">Cantidad</th>
								<th class="text-center">Precio</th>
								<th class="text-center">Total</th>
								<th class="text-center">Cantidad</th>
								<th class="text-center">Precio</th>
								<th class="text-center">Total</th>
							</tr>
						</thead>

						<?php
						while ($row = mysqli_fetch_array($query)) {
						?>
							<tr>
								<td><?php echo date('d-m-Y', strtotime($row['fecha_registro'])); ?></td>
								<td class="col-xs-2"><?php echo strtoupper($row['referencia']); ?></td>
								<td bgcolor="#90EE90" class="text-right"><?php echo $row['entrada'] == 0 ? "" : $row['entrada']; ?></td>
								<td bgcolor="#90EE90" class="text-right"><?php echo $row['entrada'] != 0 ? $row['costo'] : ""; ?></td>
								<td bgcolor="#90EE90" class="text-right"><?php echo $row['total_entrada'] == 0 ? "" : $row['total_entrada']; ?></td>
								<td bgcolor="#F0E68C" class="text-right"><?php echo $row['salida'] == 0 ? "" : $row['salida']; ?></td>
								<td bgcolor="#F0E68C" class="text-right"><?php echo $row['salida'] != 0 ? $row['precio'] : ""; ?></td>
								<td bgcolor="#F0E68C" class="text-right"><?php echo $row['total_salida'] == 0 ? "" : $row['total_salida']; ?></td>
								<td bgcolor="#87CEFA" class="text-right"><?php echo $row['saldo_acumulado']; ?></td>
								<td bgcolor="#87CEFA" class="text-right"><?php echo $row['precio_promedio']; ?></td>
								<td bgcolor="#87CEFA" class="text-right"><?php echo number_format($row['saldo_acumulado'] * $row['precio_promedio'], 2, '.', ''); ?></td>
							</tr>
						<?php
						}
						?>
						<tr>
							<td colspan="12"><span class="pull-right">
									<?php
									echo paginate($reload, $page, $total_pages, $adjacents);
									?>
								</span></td>
						</tr>
					</table>
				</div>
			</div>
		<?php
		} else {
		?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong>Mensaje! </strong>
				<?php
				echo "No hay datos para mostrar.";
				?>
			</div>
		<?php

		}
	} else {
		?>
		<div class="alert alert-info" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Mensaje! </strong>
			<?php
			echo "Seleccione un producto.";
			?>
		</div>
	<?php

	}
}

function entradas_view($data)
{
	$query = $data['query'];
	$reload = $data['reload'];
	$page = $data['page'];
	$total_pages = $data['total_pages'];
	$adjacents = $data['adjacents'];
	$numrows = $data['numrows'];
	//loop through fetched data
	if ($numrows > 0) {
	?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Producto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cantidad_entrada");'>Cantidad</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Medida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_marca");'>Marca</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("costo_unitario");'>Costo</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("lote");'>Lote</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("referencia");'>Referencia</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Bodega</button></th>
						<th class="col-xs-1" style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_registro");'>Fecha_registro</button></th>
						<th class="col-xs-1" style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_vencimiento");'>Caducidad</button></th>
						<th class="col-xs-1" style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Usuario</button></th>
						<input type="hidden" value="<?php echo $page; ?>" id="pagina">
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
					?>
						<tr>
							<td><?php echo strtoupper($row['codigo_producto']); ?></td>
							<td class="col-xs-2"><?php echo strtoupper($row['nombre_producto']); ?></td>
							<td><?php echo number_format($row['cantidad_entrada'], 4, '.', ''); ?></td>
							<td><?php echo strtoupper($row['medida']); ?></td>
							<td><?php echo strtoupper($row['marca']); ?></td>
							<td><?php echo number_format($row['costo_unitario'], 4, '.', ''); ?></td>
							<td><?php echo $row['lote']; ?></td>
							<td class="col-xs-2"><?php echo strtoupper($row['referencia']); ?></td>
							<td><?php echo strtoupper($row['bodega']); ?></td>
							<td><?php echo date("d-m-Y", strtotime($row['fecha_registro'])); ?></td>
							<td><?php echo date("d-m-Y", strtotime($row['fecha_vencimiento'])); ?></td>
							<td class="col-xs-1"><?php echo strtoupper($row['usuario']); ?></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="12"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?>
							</span></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	} else {
	?>
		<div class="alert alert-danger" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Mensaje! </strong>
			<?php
			echo "No hay datos para mostrar.";
			?>
		</div>
	<?php

	}
}

function salidas($producto, $ordenado, $por, $ruc_empresa, $con, $desde, $hasta, $marca, $lote, $caducidad, $referencia, $bodega)
{
	if (empty($bodega)) {
		$condicion_bodega = "";
	} else {
		$condicion_bodega = " and inv.id_bodega =" . $bodega;
	}

	if (empty($producto)) {
		$condicion_producto = "";
	} else {
		$condicion_producto = " and inv.id_producto =" . $producto;
	}

	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}

	if (empty($lote)) {
		$condicion_lote = "";
	} else {
		$condicion_lote = " and inv.lote LIKE '%" . $lote . "%' ";
	}

	if (empty($caducidad)) {
		$condicion_caducidad = "";
	} else {
		$condicion_caducidad = " and inv.fecha_vencimiento LIKE '%" . $caducidad . "%' ";
	}

	if (empty($referencia)) {
		$condicion_referencia = "";
	} else {
		$condicion_referencia = " and inv.referencia LIKE '%" . $referencia . "%' ";
	}


	$sWhere = " WHERE inv.ruc_empresa ='" . $ruc_empresa . " ' and inv.operacion='SALIDA' 
	and DATE_FORMAT(inv.fecha_registro, '%Y/%m/%d') BETWEEN '" . $desde . "' and '" . $hasta . "' 
	and inv.cantidad_salida > 0 $condicion_producto $condicion_bodega $condicion_marca $condicion_lote $condicion_caducidad $condicion_referencia 
	order by $ordenado $por";

	$sTable = "inventarios as inv INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega INNER JOIN usuarios as usu ON usu.id=inv.id_usuario LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";

	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../reporte_inventarios.php';
	//main query to fetch the data
	$sql = "SELECT mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, inv.nombre_producto as nombre_producto, round(inv.cantidad_salida, 4) as cantidad_salida, med.nombre_medida as medida, round(inv.precio,4) as precio, inv.referencia as referencia, bod.nombre_bodega as bodega,
   inv.fecha_registro as fecha_registro, inv.fecha_vencimiento as fecha_vencimiento, inv.lote as lote, usu.nombre as usuario
   FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	$data = array('query' => $query, 'reload' => $reload, 'page' => $page, 'total_pages' => $total_pages, 'adjacents' => $adjacents, 'numrows' => $numrows);
	return $data;
}
//loop through fetched data
function salidas_view($data)
{
	$query = $data['query'];
	$reload = $data['reload'];
	$page = $data['page'];
	$total_pages = $data['total_pages'];
	$adjacents = $data['adjacents'];
	$numrows = $data['numrows'];
	if ($numrows > 0) {
	?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Producto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cantidad_salida");'>Cantidad</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Medida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_marca");'>Marca</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("precio");'>Precio_venta</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("lote");'>Lote</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("referencia");'>Referencia</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Bodega</button></th>
						<th class="col-xs-1" style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_registro");'>Fecha_registro</button></th>
						<th class="col-xs-1" style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_vencimiento");'>Caducidad</button></th>
						<th class="col-xs-1" style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Usuario</button></th>
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
					?>
						<tr>
							<td><?php echo strtoupper($row['codigo_producto']); ?></td>
							<td class="col-xs-2"><?php echo strtoupper($row['nombre_producto']); ?></td>
							<td><?php echo number_format($row['cantidad_salida'], 4, '.', ''); ?></td>
							<td><?php echo strtoupper($row['medida']); ?></td>
							<td><?php echo strtoupper($row['marca']); ?></td>
							<td><?php echo number_format($row['precio'], 4, '.', ''); ?></td>
							<td><?php echo $row['lote']; ?></td>
							<td class="col-xs-2"><?php echo strtoupper($row['referencia']); ?></td>
							<td><?php echo strtoupper($row['bodega']); ?></td>
							<td><?php echo date("d-m-Y", strtotime($row['fecha_registro'])); ?></td>
							<td><?php echo date("d-m-Y", strtotime($row['fecha_vencimiento'])); ?></td>
							<td class="col-xs-1"><?php echo strtoupper($row['usuario']); ?></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="12"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?>
							</span></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	} else {
	?>
		<div class="alert alert-danger" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Mensaje! </strong>
			<?php
			echo "No hay datos para mostrar.";
			?>
		</div>
	<?php
	}
}

function existencia_general($producto, $ordenado, $por, $ruc_empresa, $con, $hasta, $marca, $bodega, $id_usuario)
{
	$condicion_bodega = '';
	$condicion_marca = '';
	$condicion_producto = '';

	// Verifica y construye las condiciones basadas en los parámetros
	if (!empty($bodega)) {
		$condicion_bodega = " AND exi.id_bodega = '" . mysqli_real_escape_string($con, $bodega) . "'";
	}

	if (!empty($marca)) {
		$condicion_marca = " AND mar_pro.id_marca = '" . mysqli_real_escape_string($con, $marca) . "'";
	}

	if (!empty($producto)) {
		$condicion_producto = " AND exi.id_producto = '" . mysqli_real_escape_string($con, $producto) . "'";
	}

	$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "' and id_usuario ='" . $id_usuario . "'");
	$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp 
	(id_existencia_tmp, id_producto, codigo_producto, nombre_producto, cantidad_entrada, cantidad_salida, id_bodega, id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
	SELECT null, exi.id_producto, exi.codigo_producto, pro.nombre_producto, sum(exi.cantidad_entrada), sum(exi.cantidad_salida), exi.id_bodega, exi.id_medida, exi.fecha_vencimiento, exi.ruc_empresa, sum(exi.cantidad_entrada)-sum(exi.cantidad_salida), exi.lote, '" . $id_usuario . "' 
	FROM inventarios as exi 
	INNER JOIN productos_servicios as pro 
	ON pro.id=exi.id_producto 
	WHERE exi.ruc_empresa ='" . mysqli_real_escape_string($con, $ruc_empresa) . "' and DATE_FORMAT(exi.fecha_registro, '%Y/%m/%d') <= '" . mysqli_real_escape_string($con, $hasta) . "' 
	$condicion_producto $condicion_bodega GROUP by exi.id_producto, exi.id_medida, exi.id_bodega");


	$sTable = "existencias_inventario_tmp as inv 
	INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida 
	INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega 
	LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto 
	LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . $ruc_empresa . "' and inv.id_usuario ='" . $id_usuario . "' $condicion_marca order by $ordenado $por";


	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../sistema/reporte_inventarios.php';
	//main query to fetch the data
	$sql = "SELECT inv.id_producto as id_producto, inv.codigo_producto as codigo_producto, 
	inv.nombre_producto as nombre_producto, inv.saldo_producto as existencia, 
    med.nombre_medida as medida, bod.nombre_bodega as bodega, COALESCE(mar.nombre_marca, '---') AS marca 
	FROM $sTable $sWhere LIMIT $offset, $per_page";
	$query = mysqli_query($con, $sql);
	$data = array('hasta' => $hasta, 'con' => $con, 'query' => $query, 'reload' => $reload, 'page' => $page, 'total_pages' => $total_pages, 'adjacents' => $adjacents, 'numrows' => $numrows);
	return $data;
}

function existencia_general_view($data)
{
	$query = $data['query'];
	$reload = $data['reload'];
	$page = $data['page'];
	$total_pages = $data['total_pages'];
	$adjacents = $data['adjacents'];
	$numrows = $data['numrows'];
	if ($numrows > 0) {
		//loop through fetched data
	?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Código</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Producto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Existencia</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Costo</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Venta</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Marca</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Medida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Bodega</button></th>
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
						$id_producto = $row['id_producto'];
						$sql_costo_promedio = mysqli_query($data['con'], "SELECT AVG(costo_unitario) as costo_promedio FROM inventarios WHERE id_producto = '" . $id_producto . "' and DATE_FORMAT(fecha_registro, '%Y/%m/%d') <= '" . $data['hasta'] . "' and operacion ='ENTRADA' and costo_unitario >0 ");
						$row_costo_promedio = mysqli_fetch_array($sql_costo_promedio);

						$sql_venta_promedio = mysqli_query($data['con'], "SELECT AVG(precio) as precio_promedio FROM inventarios WHERE id_producto = '" . $id_producto . "' and DATE_FORMAT(fecha_registro, '%Y/%m/%d') <= '" . $data['hasta'] . "' and operacion ='SALIDA' and precio > 0 ");
						$row_venta_promedio = mysqli_fetch_array($sql_venta_promedio);

						if ($row['existencia'] > 0) {
							$existencia = "<span class='label label-success'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						} else {
							$existencia = "<span class='label label-danger'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						}
					?>
						<tr>
							<td><?php echo strtoupper($row['codigo_producto']); ?></td>
							<td class="col-xs-2"><?php echo strtoupper($row['nombre_producto']); ?></td>
							<td><?php echo $existencia; ?></td>
							<td><?php echo number_format($row_costo_promedio['costo_promedio'], 4, '.', ''); ?></td>
							<td><?php echo number_format($row_venta_promedio['precio_promedio'], 4, '.', ''); ?></td>
							<td><?php echo strtoupper($row['marca']);
								?></td>
							<td><?php echo strtoupper($row['medida']); ?></td>
							<td><?php echo strtoupper($row['bodega']); ?></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="8"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?>
							</span></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	} else {
	?>
		<div class="alert alert-danger" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Mensaje! </strong>
			<?php
			echo "No hay datos para mostrar.";
			?>
		</div>
	<?php
	}
}

function existencia_consignacion_lote($producto, $ordenado, $por, $ruc_empresa, $con, $hasta, $id_usuario, $marca, $lote, $bodega)
{
	$condicion_bodega = '';
	$condicion_marca = '';
	$condicion_producto = '';
	$condicion_lote = '';

	// Verifica y construye las condiciones basadas en los parámetros
	if (!empty($bodega)) {
		$condicion_bodega = " AND exi.id_bodega = '" . mysqli_real_escape_string($con, $bodega) . "'";
	}

	if (!empty($marca)) {
		$condicion_marca = " AND mar_pro.id_marca = '" . mysqli_real_escape_string($con, $marca) . "'";
	}

	if (!empty($producto)) {
		$condicion_producto = " AND exi.id_producto = '" . mysqli_real_escape_string($con, $producto) . "'";
	}

	if (!empty($lote)) {
		$condicion_lote = " AND exi.lote = '" . mysqli_real_escape_string($con, $lote) . "'";
	}

	$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "' and id_usuario ='" . $id_usuario . "'");
	$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp 
	(id_existencia_tmp, id_producto, codigo_producto, nombre_producto, cantidad_entrada,cantidad_salida, id_bodega, id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
	SELECT null, exi.id_producto, exi.codigo_producto, pro.nombre_producto, sum(exi.cantidad_entrada), sum(exi.cantidad_salida), exi.id_bodega, exi.id_medida, exi.fecha_vencimiento, exi.ruc_empresa, sum(exi.cantidad_entrada)-sum(exi.cantidad_salida), exi.lote, '" . $id_usuario . "' 
	FROM inventarios as exi 
	INNER JOIN productos_servicios as pro 
	ON pro.id=exi.id_producto 
	WHERE exi.ruc_empresa ='" . mysqli_real_escape_string($con, $ruc_empresa) . "' and DATE_FORMAT(exi.fecha_registro, '%Y/%m/%d') <= '" . mysqli_real_escape_string($con, $hasta) . "' 
	$condicion_producto $condicion_bodega $condicion_lote GROUP by exi.id_producto, exi.id_medida, exi.id_bodega, exi.lote");

	$sTable = "existencias_inventario_tmp as inv 
	INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida 
	INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega 
	LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto 
	LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . mysqli_real_escape_string($con, $ruc_empresa) . "' and inv.id_usuario ='" . $id_usuario . "' $condicion_marca order by $ordenado $por";

	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../reporte_inventarios.php';
	//main query to fetch the data
	$sql = "SELECT inv.id_producto as id_producto, mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, 
	inv.nombre_producto as nombre_producto, inv.saldo_producto as existencia, med.nombre_medida as medida, 
	bod.nombre_bodega as bodega, inv.lote as lote, inv.id_bodega as id_bodega FROM $sTable $sWhere LIMIT $offset, $per_page";
	$query = mysqli_query($con, $sql);
	$data = array('ruc_empresa' => $ruc_empresa, 'hasta' => $hasta, 'con' => $con, 'query' => $query, 'reload' => $reload, 'page' => $page, 'total_pages' => $total_pages, 'adjacents' => $adjacents, 'numrows' => $numrows);
	return $data;
}


function existencia_consignacion_lote_view($data)
{
	$query = $data['query'];
	$reload = $data['reload'];
	$page = $data['page'];
	$total_pages = $data['total_pages'];
	$adjacents = $data['adjacents'];
	$numrows = $data['numrows'];
	if ($numrows > 0) {
		//loop through fetched data
	?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Producto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Existencia</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Consignación</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Saldo</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("lote");'>Lote</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_marca");'>Marca</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Medida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Bodega</button></th>
					</tr>
					<?php
					$saldo_consignado = 0;
					while ($row = mysqli_fetch_array($query)) {
						$id_producto = $row['id_producto'];
						$id_bodega = $row['id_bodega'];
						$lote = $row['lote'];
						//desde aqui la consignacion
						$suma_entrada = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as entradas 
				FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con ON det_con.codigo_unico=enc_con.codigo_unico 
				WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' and det_con.ruc_empresa='" . $data['ruc_empresa'] . "' and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' and det_con.id_producto='" . $id_producto . "' 
				and det_con.id_bodega='" . $id_bodega . "' and det_con.lote = '" . $lote . "' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='ENTRADA' ");
						$row_entrada = mysqli_fetch_array($suma_entrada);
						$cantidad_entrada = $row_entrada['entradas'];

						$suma_devuelta = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as devueltas 
				FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con ON 
				det_con.codigo_unico=enc_con.codigo_unico WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' 
				and det_con.ruc_empresa='" . $data['ruc_empresa'] . "' 
				and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' and det_con.lote = '" . $lote . "' and det_con.id_producto='" . $id_producto . "' and det_con.id_bodega='" . $id_bodega . "' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='DEVOLUCIÓN' ");
						$row_devuelta = mysqli_fetch_array($suma_devuelta);
						$cantidad_devuelta = $row_devuelta['devueltas'];

						$suma_facturada = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as facturada 
				FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con ON det_con.codigo_unico=enc_con.codigo_unico 
				WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' and det_con.ruc_empresa='" . $data['ruc_empresa'] . "' 
				and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' 
				and det_con.lote = '" . $lote . "' and det_con.id_producto='" . $id_producto . "' and det_con.id_bodega='" . $id_bodega . "' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='FACTURA'");
						$row_facturada = mysqli_fetch_array($suma_facturada);
						$cantidad_facturada = $row_facturada['facturada'];

						$saldo_consignado = $cantidad_entrada - $cantidad_devuelta - $cantidad_facturada;
						$saldo_final = $row['existencia'] + $saldo_consignado;

						if ($row['existencia'] > 0) {
							$existencia = "<span class='label label-success'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						} else {
							$existencia = "<span class='label label-danger'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						}

						if ($saldo_final > 0) {
							$saldo_final = "<span class='label label-success'>" . number_format($saldo_final, 4, '.', '') . "</span>";
						} else {
							$saldo_final = "<span class='label label-danger'>" . number_format($saldo_final, 4, '.', '') . "</span>";
						}

						$detalle_consignaciones = 'Entradas: ' . $cantidad_entrada . ' - Facturadas: ' . $cantidad_facturada . ' - Devueltas: ' . $cantidad_devuelta;

					?>
						<tr>
							<td><?php echo strtoupper($row['codigo_producto']); ?></td>
							<td class="col-xs-2"><?php echo strtoupper($row['nombre_producto']); ?></td>
							<td><?php echo $existencia; ?></td>
							<td><?php echo number_format($saldo_consignado, 4, '.', '');
								if ($saldo_consignado != 0) {
								?>
									<a href="#" data-toggle="tooltip" data-placement="top" title="<?php echo $detalle_consignaciones; ?>"><span class="glyphicon glyphicon-tag"></span></a>
								<?php
								}
								?>
							</td>
							<td><?php echo $saldo_final; ?></td>
							<td><?php echo $row['lote']; ?></td>
							<td><?php echo strtoupper($row['marca']); ?></td>
							<td><?php echo strtoupper($row['medida']); ?></td>
							<td><?php echo strtoupper($row['bodega']); ?></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="9"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?>
							</span></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	} else {
	?>
		<div class="alert alert-danger" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Mensaje! </strong>
			<?php
			echo "No hay datos para mostrar.";
			?>
		</div>
	<?php
	}
}

function existencia_consignacion_caducidad($producto, $ordenado, $por, $ruc_empresa, $con, $hasta, $id_usuario, $marca, $caducidad, $bodega)
{
	$condicion_bodega = '';
	$condicion_marca = '';
	$condicion_producto = '';

	// Verifica y construye las condiciones basadas en los parámetros
	if (!empty($bodega)) {
		$condicion_bodega = " AND exi.id_bodega = '" . mysqli_real_escape_string($con, $bodega) . "'";
	}

	if (!empty($marca)) {
		$condicion_marca = " AND mar_pro.id_marca = '" . mysqli_real_escape_string($con, $marca) . "'";
	}

	if (!empty($producto)) {
		$condicion_producto = " AND exi.id_producto = '" . mysqli_real_escape_string($con, $producto) . "'";
	}

	if (!empty($caducidad)) {
		$condicion_caducidad = " AND DATE_FORMAT(exi.fecha_vencimiento, '%Y/%m/%d') = '" . mysqli_real_escape_string($con, date('Y/m/d', strtotime($caducidad))) . "'";
		$caducidad_hasta = "";
	} else {
		$condicion_caducidad = '';
		$caducidad_hasta = " AND DATE_FORMAT(exi.fecha_vencimiento, '%Y/%m/%d') <= '" . mysqli_real_escape_string($con, $hasta) . "'";
	}

	$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "' and id_usuario ='" . $id_usuario . "'");
	$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp 
	(id_existencia_tmp, id_producto, codigo_producto, nombre_producto, cantidad_entrada,cantidad_salida, id_bodega, id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
	SELECT null, exi.id_producto, exi.codigo_producto, pro.nombre_producto, sum(exi.cantidad_entrada), sum(exi.cantidad_salida), exi.id_bodega, exi.id_medida, exi.fecha_vencimiento, exi.ruc_empresa, sum(exi.cantidad_entrada)-sum(exi.cantidad_salida), exi.lote, '" . $id_usuario . "' 
	FROM inventarios as exi 
	INNER JOIN productos_servicios as pro 
	ON pro.id=exi.id_producto 
	WHERE exi.ruc_empresa ='" . mysqli_real_escape_string($con, $ruc_empresa) . "' 
	$caducidad_hasta $condicion_producto $condicion_bodega $condicion_caducidad 
	GROUP by exi.id_producto, exi.id_medida, exi.id_bodega, exi.fecha_vencimiento");

	$sTable = "existencias_inventario_tmp as inv 
	INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida 
	INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega 
	LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto 
	LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . mysqli_real_escape_string($con, $ruc_empresa) . "' and inv.id_usuario ='" . $id_usuario . "' $condicion_marca 
	order by inv.fecha_caducidad desc";
	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../reporte_inventarios.php';
	//main query to fetch the data
	$sql = "SELECT inv.id_producto as id_producto, mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, 
	inv.nombre_producto as nombre_producto, inv.saldo_producto as existencia, med.nombre_medida as medida, 
	bod.nombre_bodega as bodega, inv.fecha_caducidad as caducidad, inv.id_bodega as id_bodega FROM $sTable $sWhere LIMIT $offset, $per_page";
	$query = mysqli_query($con, $sql);
	$data = array('ruc_empresa' => $ruc_empresa, 'hasta' => $hasta, 'con' => $con, 'query' => $query, 'reload' => $reload, 'page' => $page, 'total_pages' => $total_pages, 'adjacents' => $adjacents, 'numrows' => $numrows);
	return $data;
}

function existencia_consignacion_caducidad_view($data)
{
	$query = $data['query'];
	$reload = $data['reload'];
	$page = $data['page'];
	$total_pages = $data['total_pages'];
	$adjacents = $data['adjacents'];
	$numrows = $data['numrows'];
	if ($numrows > 0) {
		//loop through fetched data
	?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Producto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Existencia</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Consignación</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Saldo</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("caducidad");'>Caducidad</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_marca");'>Marca</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Medida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Bodega</button></th>
					</tr>
					<?php
					$saldo_consignado = 0;
					while ($row = mysqli_fetch_array($query)) {
						$id_producto = $row['id_producto'];
						$id_bodega = $row['id_bodega'];
						$caducidad = $row['caducidad'];
						//desde aqui la consignacion
						$suma_entrada = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as entradas 
				FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con ON det_con.codigo_unico=enc_con.codigo_unico 
				WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' and det_con.ruc_empresa='" . $data['ruc_empresa'] . "' and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' and det_con.id_producto='" . $id_producto . "' 
				and det_con.id_bodega='" . $id_bodega . "' and det_con.vencimiento = '" . $caducidad . "' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='ENTRADA' ");
						$row_entrada = mysqli_fetch_array($suma_entrada);
						$cantidad_entrada = $row_entrada['entradas'];

						$suma_devuelta = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as devueltas 
				FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con ON 
				det_con.codigo_unico=enc_con.codigo_unico WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' 
				and det_con.ruc_empresa='" . $data['ruc_empresa'] . "' 
				and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' and det_con.vencimiento = '" . $caducidad . "' and det_con.id_producto='" . $id_producto . "' and det_con.id_bodega='" . $id_bodega . "' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='DEVOLUCIÓN' ");
						$row_devuelta = mysqli_fetch_array($suma_devuelta);
						$cantidad_devuelta = $row_devuelta['devueltas'];

						$suma_facturada = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as facturada 
				FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con ON det_con.codigo_unico=enc_con.codigo_unico 
				WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' and det_con.ruc_empresa='" . $data['ruc_empresa'] . "' 
				and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' 
				and det_con.vencimiento = '" . $caducidad . "' and det_con.id_producto='" . $id_producto . "' and det_con.id_bodega='" . $id_bodega . "' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='FACTURA'");
						$row_facturada = mysqli_fetch_array($suma_facturada);
						$cantidad_facturada = $row_facturada['facturada'];

						$saldo_consignado = $cantidad_entrada - $cantidad_devuelta - $cantidad_facturada;
						$saldo_final = $row['existencia'] + $saldo_consignado;

						if ($row['existencia'] > 0) {
							$existencia = "<span class='label label-success'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						} else {
							$existencia = "<span class='label label-danger'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						}

						if ($saldo_final > 0) {
							$saldo_final = "<span class='label label-success'>" . number_format($saldo_final, 4, '.', '') . "</span>";
						} else {
							$saldo_final = "<span class='label label-danger'>" . number_format($saldo_final, 4, '.', '') . "</span>";
						}

						$detalle_consignaciones = 'Entradas: ' . $cantidad_entrada . ' - Facturadas: ' . $cantidad_facturada . ' - Devueltas: ' . $cantidad_devuelta;

					?>
						<tr>
							<td><?php echo strtoupper($row['codigo_producto']); ?></td>
							<td class="col-xs-2"><?php echo strtoupper($row['nombre_producto']); ?></td>
							<td><?php echo $existencia; ?></td>
							<td><?php echo number_format($saldo_consignado, 4, '.', '');
								if ($saldo_consignado != 0) {
								?>
									<a href="#" data-toggle="tooltip" data-placement="top" title="<?php echo $detalle_consignaciones; ?>"><span class="glyphicon glyphicon-tag"></span></a>
								<?php
								}
								?>
							</td>
							<td><?php echo $saldo_final; ?></td>
							<td><?php echo date("d-m-Y", strtotime($row['caducidad'])); ?></td>
							<td><?php echo strtoupper($row['marca']); ?></td>
							<td><?php echo strtoupper($row['medida']); ?></td>
							<td><?php echo strtoupper($row['bodega']); ?></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="9"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?>
							</span></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	} else {
	?>
		<div class="alert alert-danger" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Mensaje! </strong>
			<?php
			echo "No hay datos para mostrar.";
			?>
		</div>
	<?php
	}
}

function existencia_consignacion($producto, $ordenado, $por, $ruc_empresa, $con, $hasta, $id_usuario, $marca, $bodega)
{

	$condicion_bodega = '';
	$condicion_marca = '';
	$condicion_producto = '';

	// Verifica y construye las condiciones basadas en los parámetros
	if (!empty($bodega)) {
		$condicion_bodega = " AND exi.id_bodega = '" . mysqli_real_escape_string($con, $bodega) . "'";
	}

	if (!empty($marca)) {
		$condicion_marca = " AND mar_pro.id_marca = '" . mysqli_real_escape_string($con, $marca) . "'";
	}

	if (!empty($producto)) {
		$condicion_producto = " AND exi.id_producto = '" . mysqli_real_escape_string($con, $producto) . "'";
	}

	$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "' and id_usuario ='" . $id_usuario . "'");
	$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp 
	(id_existencia_tmp, id_producto, codigo_producto, nombre_producto, cantidad_entrada,cantidad_salida, id_bodega, id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
	SELECT null, exi.id_producto, exi.codigo_producto, pro.nombre_producto, sum(exi.cantidad_entrada), sum(exi.cantidad_salida), exi.id_bodega, exi.id_medida, exi.fecha_vencimiento, exi.ruc_empresa, sum(exi.cantidad_entrada)-sum(exi.cantidad_salida), exi.lote, '" . $id_usuario . "' 
	FROM inventarios as exi 
	INNER JOIN productos_servicios as pro 
	ON pro.id=exi.id_producto 
	WHERE exi.ruc_empresa ='" . mysqli_real_escape_string($con, $ruc_empresa) . "' AND DATE_FORMAT(exi.fecha_registro, '%Y/%m/%d') <= '" . mysqli_real_escape_string($con, $hasta) . "'
	$condicion_producto $condicion_bodega  
	GROUP by exi.id_producto, exi.id_medida, exi.id_bodega");

	$sTable = "existencias_inventario_tmp as inv 
	INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida 
	INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega 
	LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto 
	LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . mysqli_real_escape_string($con, $ruc_empresa) . "' and inv.id_usuario ='" . $id_usuario . "' $condicion_marca 
	order by inv.lote desc";

	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../reporte_inventarios.php';
	//main query to fetch the data
	$sql = "SELECT inv.id_producto as id_producto, mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, inv.nombre_producto as nombre_producto, inv.saldo_producto as existencia, med.nombre_medida as medida, bod.nombre_bodega as bodega, inv.id_bodega as id_bodega
	 FROM $sTable $sWhere LIMIT $offset, $per_page";
	$query = mysqli_query($con, $sql);
	$data = array('ruc_empresa' => $ruc_empresa, 'hasta' => $hasta, 'con' => $con, 'query' => $query, 'reload' => $reload, 'page' => $page, 'total_pages' => $total_pages, 'adjacents' => $adjacents, 'numrows' => $numrows);
	return $data;
}

function existencia_consignacion_view($data)
{
	$query = $data['query'];
	$reload = $data['reload'];
	$page = $data['page'];
	$total_pages = $data['total_pages'];
	$adjacents = $data['adjacents'];
	$numrows = $data['numrows'];
	if ($numrows > 0) {
	?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Producto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Existencia</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Consignación</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Saldo</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_marca");'>Marca</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Medida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Bodega</button></th>
					</tr>
					<?php
					$saldo_consignado = 0;
					while ($row = mysqli_fetch_array($query)) {
						$id_producto = $row['id_producto'];
						$id_bodega = $row['id_bodega'];
						//$lote = $row['lote'];

						//desde aqui la consignacion
						//and det_con.lote='" . $lote . "'
						$suma_entrada = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as entradas 
				FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con ON det_con.codigo_unico=enc_con.codigo_unico 
				WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' and det_con.ruc_empresa='" . $data['ruc_empresa'] . "' and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "'
				and det_con.id_producto='" . $id_producto . "' and det_con.id_bodega='" . $id_bodega . "' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='ENTRADA' ");
						$row_entrada = mysqli_fetch_array($suma_entrada);
						$cantidad_entrada = $row_entrada['entradas'];

						$suma_devuelta = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as devueltas 
				FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON 
				det_con.codigo_unico=enc_con.codigo_unico WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' and det_con.ruc_empresa='" . $data['ruc_empresa'] . "' and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' and 
				det_con.id_producto='" . $id_producto . "' and det_con.id_bodega='" . $id_bodega . "' and det_con.numero_orden_entrada > 0  and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='DEVOLUCIÓN' ");
						$row_devuelta = mysqli_fetch_array($suma_devuelta);
						$cantidad_devuelta = $row_devuelta['devueltas'];

						$suma_facturada = mysqli_query($data['con'], "SELECT sum(det_con.cant_consignacion) as facturada 
				FROM encabezado_consignacion as enc_con INNER JOIN detalle_consignacion as det_con ON det_con.codigo_unico=enc_con.codigo_unico 
				WHERE enc_con.ruc_empresa='" . $data['ruc_empresa'] . "' and det_con.ruc_empresa='" . $data['ruc_empresa'] . "' and DATE_FORMAT(enc_con.fecha_consignacion, '%Y/%m/%d') <= '" . $data['hasta'] . "' 
				and det_con.id_producto='" . $id_producto . "' and det_con.id_bodega='" . $id_bodega . "' and det_con.numero_orden_entrada > 0 and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='FACTURA' ");
						$row_facturada = mysqli_fetch_array($suma_facturada);
						$cantidad_facturada = $row_facturada['facturada'];

						$saldo_consignado = $cantidad_entrada - $cantidad_devuelta - $cantidad_facturada;
						$saldo_final = $row['existencia'] + $saldo_consignado;

						if ($row['existencia'] > 0) {
							$existencia = "<span class='label label-success'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						} else {
							$existencia = "<span class='label label-danger'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						}

						if ($saldo_final > 0) {
							$saldo_final = "<span class='label label-success'>" . number_format($saldo_final, 4, '.', '') . "</span>";
						} else {
							$saldo_final = "<span class='label label-danger'>" . number_format($saldo_final, 4, '.', '') . "</span>";
						}
					?>
						<tr>
							<td><?php echo strtoupper($row['codigo_producto']); ?></td>
							<td class="col-xs-2"><?php echo strtoupper($row['nombre_producto']); ?></td>
							<td><?php echo $existencia; ?></td>
							<td><?php echo number_format($saldo_consignado, 4, '.', ''); ?></td>
							<td><?php echo $saldo_final; ?></td>
							<td><?php echo strtoupper($row['marca']); ?></td>
							<td><?php echo strtoupper($row['medida']); ?></td>
							<td><?php echo strtoupper($row['bodega']); ?></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="8"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?>
							</span></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	} else {
	?>
		<div class="alert alert-danger" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Mensaje! </strong>
			<?php
			echo "No hay datos para mostrar.";
			?>
		</div>
	<?php
	}
}


function existencia_caducidad($producto, $ruc_empresa, $con, $hasta, $id_usuario, $marca, $caducidad, $bodega)
{

	$condicion_bodega = '';
	$condicion_marca = '';
	$condicion_producto = '';

	// Verifica y construye las condiciones basadas en los parámetros
	if (!empty($bodega)) {
		$condicion_bodega = " AND exi.id_bodega = '" . mysqli_real_escape_string($con, $bodega) . "'";
	}

	if (!empty($marca)) {
		$condicion_marca = " AND mar_pro.id_marca = '" . mysqli_real_escape_string($con, $marca) . "'";
	}

	if (!empty($producto)) {
		$condicion_producto = " AND exi.id_producto = '" . mysqli_real_escape_string($con, $producto) . "'";
	}

	if (!empty($caducidad)) {
		$condicion_caducidad = " AND DATE_FORMAT(exi.fecha_vencimiento, '%Y/%m/%d') = '" . mysqli_real_escape_string($con, date('Y/m/d', strtotime($caducidad))) . "'";
		$caducidad_hasta = "";
	} else {
		$condicion_caducidad = '';
		$caducidad_hasta = " AND DATE_FORMAT(exi.fecha_vencimiento, '%Y/%m/%d') <= '" . mysqli_real_escape_string($con, $hasta) . "'";
	}

	$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "' and id_usuario ='" . $id_usuario . "'");
	$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp 
	(id_existencia_tmp, id_producto, codigo_producto, nombre_producto, cantidad_entrada,cantidad_salida, id_bodega, id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
	SELECT null, exi.id_producto, exi.codigo_producto, pro.nombre_producto, sum(exi.cantidad_entrada), sum(exi.cantidad_salida), exi.id_bodega, exi.id_medida, exi.fecha_vencimiento, exi.ruc_empresa, sum(exi.cantidad_entrada)-sum(exi.cantidad_salida), exi.lote, '" . $id_usuario . "' 
	FROM inventarios as exi 
	INNER JOIN productos_servicios as pro 
	ON pro.id=exi.id_producto 
	WHERE exi.ruc_empresa ='" . mysqli_real_escape_string($con, $ruc_empresa) . "' 
	$caducidad_hasta $condicion_producto $condicion_bodega $condicion_caducidad 
	GROUP by exi.id_producto, exi.id_medida, exi.id_bodega, exi.fecha_vencimiento");

	$sTable = "existencias_inventario_tmp as inv 
	INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida 
	INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega 
	LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto 
	LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . mysqli_real_escape_string($con, $ruc_empresa) . "' and inv.id_usuario ='" . $id_usuario . "' $condicion_marca 
	order by inv.fecha_caducidad desc";

	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../reporte_inventarios.php';
	//main query to fetch the data
	$sql = "SELECT inv.id_producto as id_producto, inv.codigo_producto as codigo_producto, 
	inv.nombre_producto as nombre_producto, inv.saldo_producto as existencia, 
    med.nombre_medida as medida, bod.nombre_bodega as bodega, COALESCE(mar.nombre_marca, '---') AS marca, 
	inv.fecha_caducidad as vencimiento 
	FROM $sTable $sWhere LIMIT $offset, $per_page";
	$query = mysqli_query($con, $sql);
	$data = array('query' => $query, 'reload' => $reload, 'page' => $page, 'total_pages' => $total_pages, 'adjacents' => $adjacents, 'numrows' => $numrows);
	return $data;
}

function existencia_caducidad_view($data)
{
	$query = $data['query'];
	$reload = $data['reload'];
	$page = $data['page'];
	$total_pages = $data['total_pages'];
	$adjacents = $data['adjacents'];
	$numrows = $data['numrows'];
	if ($numrows > 0) {
		//loop through fetched data
	?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Producto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Existencia</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_marca");'>Marca</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Medida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Bodega</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_caducidad");'>Vencimiento</button></th>
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
						if ($row['existencia'] > 0) {
							$existencia = "<span class='label label-success'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						} else {
							$existencia = "<span class='label label-danger'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						}
					?>
						<tr>
							<td><?php echo strtoupper($row['codigo_producto']); ?></td>
							<td class="col-xs-2"><?php echo strtoupper($row['nombre_producto']); ?></td>
							<td><?php echo $existencia; ?></td>
							<td><?php echo strtoupper($row['marca']); ?></td>
							<td><?php echo strtoupper($row['medida']); ?></td>
							<td><?php echo strtoupper($row['bodega']); ?></td>
							<td><?php echo date('d-m-Y', strtotime($row['vencimiento'])); ?></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="7"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?>
							</span></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	} else {
	?>
		<div class="alert alert-danger" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Mensaje! </strong>
			<?php
			echo "No hay datos para mostrar.";
			?>
		</div>
	<?php
	}
}


function existencia_lote($producto, $ordenado, $por, $ruc_empresa, $con, $hasta, $id_usuario, $marca, $lote, $bodega)
{

	$condicion_bodega = '';
	$condicion_marca = '';
	$condicion_producto = '';
	$condicion_lote = '';

	// Verifica y construye las condiciones basadas en los parámetros
	if (!empty($bodega)) {
		$condicion_bodega = " AND exi.id_bodega = '" . mysqli_real_escape_string($con, $bodega) . "'";
	}

	if (!empty($marca)) {
		$condicion_marca = " AND mar_pro.id_marca = '" . mysqli_real_escape_string($con, $marca) . "'";
	}

	if (!empty($producto)) {
		$condicion_producto = " AND exi.id_producto = '" . mysqli_real_escape_string($con, $producto) . "'";
	}

	if (!empty($lote)) {
		$condicion_lote = " AND exi.lote = '" . mysqli_real_escape_string($con, $lote) . "'";
	}

	$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "' and id_usuario ='" . $id_usuario . "'");
	$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp 
	(id_existencia_tmp, id_producto, codigo_producto, nombre_producto, cantidad_entrada,cantidad_salida, id_bodega, id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
	SELECT null, exi.id_producto, exi.codigo_producto, pro.nombre_producto, sum(exi.cantidad_entrada), sum(exi.cantidad_salida), exi.id_bodega, exi.id_medida, exi.fecha_vencimiento, exi.ruc_empresa, sum(exi.cantidad_entrada)-sum(exi.cantidad_salida), exi.lote, '" . $id_usuario . "' 
	FROM inventarios as exi 
	INNER JOIN productos_servicios as pro 
	ON pro.id=exi.id_producto 
	WHERE exi.ruc_empresa ='" . mysqli_real_escape_string($con, $ruc_empresa) . "' AND DATE_FORMAT(exi.fecha_registro, '%Y/%m/%d') <= '" . mysqli_real_escape_string($con, $hasta) . "'
	$condicion_producto $condicion_bodega $condicion_lote 
	GROUP by exi.id_producto, exi.id_medida, exi.id_bodega, exi.lote");

	$sTable = "existencias_inventario_tmp as inv 
	INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida 
	INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega 
	LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto 
	LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . mysqli_real_escape_string($con, $ruc_empresa) . "' and inv.id_usuario ='" . $id_usuario . "' $condicion_marca 
	order by inv.lote desc";

	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../reporte_inventarios.php';
	//main query to fetch the data
	$sql = "SELECT mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, inv.nombre_producto as nombre_producto, inv.saldo_producto as existencia, med.nombre_medida as medida, bod.nombre_bodega as bodega, inv.lote as lote, inv.fecha_caducidad as caducidad FROM $sTable $sWhere LIMIT $offset, $per_page";
	$query = mysqli_query($con, $sql);
	$data = array('query' => $query, 'reload' => $reload, 'page' => $page, 'total_pages' => $total_pages, 'adjacents' => $adjacents, 'numrows' => $numrows);
	return $data;
}

function existencia_lote_view($data)
{
	$query = $data['query'];
	$reload = $data['reload'];
	$page = $data['page'];
	$total_pages = $data['total_pages'];
	$adjacents = $data['adjacents'];
	$numrows = $data['numrows'];
	if ($numrows > 0) {
		//loop through fetched data
	?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Producto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Existencia</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_marca");'>Marca</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Medida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Bodega</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("lote");'>Lote</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("caducidad");'>Caducidad</button></th>
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
						if ($row['existencia'] > 0) {
							$existencia = "<span class='label label-success'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						} else {
							$existencia = "<span class='label label-danger'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						}
					?>
						<tr>
							<td><?php echo strtoupper($row['codigo_producto']); ?></td>
							<td class="col-xs-2"><?php echo strtoupper($row['nombre_producto']); ?></td>
							<td><?php echo $existencia; ?></td>
							<td><?php echo strtoupper($row['marca']); ?></td>
							<td><?php echo strtoupper($row['medida']); ?></td>
							<td><?php echo strtoupper($row['bodega']); ?></td>
							<td><?php echo strtoupper($row['lote']); ?></td>
							<td><?php echo date("d/m/Y", strtotime($row['caducidad'])); ?></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="8"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?>
							</span></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	} else {
	?>
		<div class="alert alert-danger" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Mensaje! </strong>
			<?php
			echo "No hay datos para mostrar.";
			?>
		</div>
	<?php
	}
}


function minimos($producto, $ordenado, $por, $ruc_empresa, $con, $id_usuario, $marca, $lote, $caducidad, $bodega)
{
	$saldo_producto = new saldo_producto_y_conversion();
	if (empty($bodega)) {
		$condicion_bodega = "";
	} else {
		$condicion_bodega = " and id_bodega =" . $bodega;
	}

	if (empty($marca)) {
		$condicion_marca = "";
	} else {
		$condicion_marca = " and mar_pro.id_marca=" . $marca;
	}
	if (empty($producto)) {
		$condicion_producto = "";
	} else {
		$condicion_producto = "and id_producto='" . $producto . "'";
	}

	if (empty($lote)) {
		$condicion_lote = "";
	} else {
		$condicion_lote = "and lote LIKE '%" . $lote . "%' ";
	}

	if (empty($caducidad)) {
		$condicion_caducidad = "";
	} else {
		$condicion_caducidad = "and fecha_vencimiento LIKE '%" . $caducidad . "%' ";
	}

	$delete_inventario_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and id_usuario ='" . $id_usuario . "'");
	$query_guarda_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
SELECT null, id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada), sum(cantidad_salida), id_bodega, id_medida, fecha_vencimiento,ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida), lote, '" . $id_usuario . "' FROM inventarios WHERE ruc_empresa ='" . $ruc_empresa . "' $condicion_producto $condicion_bodega $condicion_lote $condicion_caducidad group by id_producto, id_bodega ");

	//selet para buscar linea por linea y ver si la medida es igual al producto o sino modificar esa linea
	$resultado = array();
	$sql_filas = mysqli_query($con, "SELECT * FROM existencias_inventario_tmp as exi_tmp 
	LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=exi_tmp.id_producto 
	WHERE exi_tmp.ruc_empresa = '" . $ruc_empresa . "' and exi_tmp.id_usuario = '" . $id_usuario . "' and exi_tmp.cantidad_salida>0 ");
	while ($row_temporales = mysqli_fetch_array($sql_filas)) {
		$id_producto = $row_temporales["id_producto"];
		$codigo_producto = $row_temporales["codigo_producto"];
		$nombre_producto = $row_temporales["nombre_producto"];
		//obtener medida del producto
		$id_medida_salida = $row_temporales['id_unidad_medida'];

		$id_medida_entrada = $row_temporales["id_medida"];
		$cantidad_entrada_tmp = $row_temporales['cantidad_entrada'];
		$id_bodega = $row_temporales['id_bodega'];
		$caducidad = $row_temporales['fecha_caducidad'];
		$lote = $row_temporales['lote'];

		if ($id_medida_entrada != $id_medida_salida) {
			$id_tmp = $row_temporales["id_existencia_tmp"];
			$cantidad_a_transformar = $row_temporales['cantidad_salida'];
			$total_saldo_producto = $saldo_producto->conversion($id_medida_entrada, $id_medida_salida, $id_producto, '0', $cantidad_a_transformar, $con, 'saldo');
			$resultado[] = array('id_tmp' => $id_tmp, 'id_producto' => $id_producto, 'codigo_producto' => $codigo_producto, 'nombre_producto' => $nombre_producto, 'entrada' => $cantidad_entrada_tmp, 'salida' => $cantidad_a_transformar, 'id_bodega' => $id_bodega, 'id_medida' => $id_medida_salida, 'caducidad' => $caducidad, 'saldo_convertido' => $total_saldo_producto, 'lote' => $lote);
		}
	}
	foreach ($resultado as $valor) {
		$delete_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='" . $valor['id_tmp'] . "';");
		$sql_actualizar = mysqli_query($con, "INSERT INTO existencias_inventario_tmp VALUES (null,'" . $valor['id_producto'] . "','" . $valor['codigo_producto'] . "','" . $valor['nombre_producto'] . "','" . $valor['entrada'] . "','" . $valor['saldo_convertido'] . "','" . $valor['id_bodega'] . "','" . $valor['id_medida'] . "','" . $valor['caducidad'] . "', '" . $ruc_empresa . "', '" . $valor['saldo_convertido'] . "','" . $valor['lote'] . "','" . $id_usuario . "' )");
	}
	//todos los id temporales traidos para luego borrarlos
	$ides = array();
	$sql_filas_borrar = mysqli_query($con, "SELECT * FROM existencias_inventario_tmp 
	WHERE ruc_empresa = '" . $ruc_empresa . "' and id_usuario = '" . $id_usuario . "'");
	while ($row_ides_temporales = mysqli_fetch_array($sql_filas_borrar)) {
		$id_temp_iniciales = $row_ides_temporales["id_existencia_tmp"];
		$ides[] = array('id_tmp_iniciales' => $id_temp_iniciales);
	}
	$query_actualiza_inventario_tmp = mysqli_query($con, "INSERT INTO existencias_inventario_tmp (id_existencia_tmp, id_producto, codigo_producto,nombre_producto,cantidad_entrada,cantidad_salida,id_bodega,id_medida,fecha_caducidad, ruc_empresa, saldo_producto,lote,id_usuario) 
	SELECT null,id_producto, codigo_producto, nombre_producto, sum(cantidad_entrada), sum(cantidad_salida), id_bodega, id_medida, fecha_caducidad, ruc_empresa, sum(cantidad_entrada)-sum(cantidad_salida),lote, '" . $id_usuario . "'  FROM existencias_inventario_tmp WHERE ruc_empresa ='" . $ruc_empresa . "' group by id_bodega, id_producto");
	//eliminar los ides tmp iniciales
	foreach ($ides as $id_tm) {
		$delete_ides_tmp_iniciales = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_existencia_tmp='" . $id_tm['id_tmp_iniciales'] . "';");
	}

	$sTable = "existencias_inventario_tmp as inv 
	INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida 
	INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega 
	LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=inv.id_producto 
	LEFT JOIN marca as mar ON mar.id_marca=mar_pro.id_marca";
	$sWhere = "WHERE inv.ruc_empresa ='" . $ruc_empresa . "' and inv.id_usuario ='" . $id_usuario . "' $condicion_marca order by $ordenado $por"; //and inv.saldo_producto > 0

	//pagination variables
	$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$per_page = 20; //how much records you want to show
	$adjacents  = 4; //gap between pages after number of adjacents
	$offset = ($page - 1) * $per_page;
	//Count the total number of row in your table*/
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = '../reporte_inventarios.php';
	//main query to fetch the data
	$sql = "SELECT inv.id_producto as id_producto, mar.nombre_marca as marca, inv.codigo_producto as codigo_producto, inv.nombre_producto as nombre_producto, inv.cantidad_entrada as cantidad_entrada, inv.cantidad_salida as cantidad_salida, 
   inv.saldo_producto as existencia, med.nombre_medida as medida, bod.nombre_bodega as bodega, bod.id_bodega as id_bodega FROM $sTable $sWhere LIMIT $offset, $per_page ";
	$query = mysqli_query($con, $sql);
	$data = array('con' => $con, 'query' => $query, 'reload' => $reload, 'page' => $page, 'total_pages' => $total_pages, 'adjacents' => $adjacents, 'numrows' => $numrows);
	return $data;
}


function minimos_view($data)
{
	$query = $data['query'];
	$reload = $data['reload'];
	$page = $data['page'];
	$total_pages = $data['total_pages'];
	$adjacents = $data['adjacents'];
	$numrows = $data['numrows'];
	if ($numrows > 0) {
		//loop through fetched data
	?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Producto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cantidad_entrada");'>Entrada</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cantidad_salida");'>Salida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Existencia</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_marca");'>Marca</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Medida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Bodega</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Mínimo</button></th>
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
						$id_producto = $row['id_producto'];
						$id_bodega = $row['id_bodega'];

						//minimos inventarios
						$busca_minimo = mysqli_query($data['con'], "SELECT * FROM minimos_inventarios WHERE id_producto='" . $id_producto . "' and id_bodega='" . $id_bodega . "'");
						$row_minimo = mysqli_fetch_array($busca_minimo);
						$id_minimo = isset($row_minimo['id_minimo']) ? $row_minimo['id_minimo'] : 0;
						$valor_minimo = isset($row_minimo['valor_minimo']) ? $row_minimo['valor_minimo'] : 0;

						//minimos
						if ($valor_minimo == "") {
							$minimo = 1;
						}
						if ($row['existencia'] > $valor_minimo) {
							$label_class_minimo = 'btn btn-success btn-sm';
						}
						if ($row['existencia'] < $valor_minimo) {
							$label_class_minimo = 'btn btn-danger btn-sm';
						}
						if ($row['existencia'] == $valor_minimo) {
							$label_class_minimo = 'btn btn-warning btn-sm';
						}

						if ($row['existencia'] > 0) {
							$existencia = "<span class='label label-success'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						} else {
							$existencia = "<span class='label label-danger'>" . number_format($row['existencia'], 4, '.', '') . "</span>";
						}
					?>
						<input type="hidden" value="<?php echo $page; ?>" id="pagina">
						<tr>
							<td><?php echo strtoupper($row['codigo_producto']); ?></td>
							<td class="col-xs-2"><?php echo strtoupper($row['nombre_producto']); ?></td>
							<td><?php echo number_format($row['cantidad_entrada'], 4, '.', ''); ?></td>
							<td><?php echo number_format($row['cantidad_salida'], 4, '.', ''); ?></td>
							<td><?php echo $existencia; ?></td>
							<td><?php echo strtoupper($row['marca']); ?></td>
							<td><?php echo strtoupper($row['medida']); ?></td>
							<td><?php echo strtoupper($row['bodega']); ?></td>
							<td class='text-center'><a href="#" class='<?php echo $label_class_minimo; ?>' title='Editar mínimos' onclick="actualizar_minimo('<?php echo $id_minimo; ?>', '<?php echo $id_producto; ?>',  '<?php echo $id_bodega; ?>', '<?php echo number_format($valor_minimo, 0, '.', ''); ?>');" data-toggle="modal" data-target="#EditarMinimos"> <?php echo $valor_minimo; ?></a></td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="10"><span class="pull-right">
								<?php
								echo paginate($reload, $page, $total_pages, $adjacents);
								?>
							</span></td>
					</tr>
				</table>
			</div>
		</div>
	<?php
	} else {
	?>
		<div class="alert alert-danger" role="alert">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong>Mensaje! </strong>
			<?php
			echo "No hay datos para mostrar.";
			?>
		</div>
<?php
	}
}

?>