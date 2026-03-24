<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'general_mas_consignaciones') {
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$aColumns = array('inv_tmp.codigo_producto', 'inv_tmp.nombre_producto', 'inv_tmp.lote'); //Columnas de busqueda
	$sTable = "existencias_inventario_tmp as inv_tmp INNER JOIN productos_servicios as pro_ser ON inv_tmp.id_producto=pro_ser.id INNER JOIN unidad_medida as uni_med ON inv_tmp.id_medida=uni_med.id_medida INNER JOIN bodega as bod ON inv_tmp.id_bodega=bod.id_bodega";
	$sWhere = "WHERE inv_tmp.ruc_empresa ='" . $ruc_empresa . "' ";
	if ($_GET['q'] != "") {
		$sWhere = "WHERE (inv_tmp.ruc_empresa ='" . $ruc_empresa . "' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND inv_tmp.ruc_empresa ='" . $ruc_empresa . "' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND inv_tmp.ruc_empresa = '" . $ruc_empresa . "' ", -3);
		$sWhere .= ')';
	}
	$sWhere .= " order by $ordenado $por ";

	include("../ajax/pagination.php"); //include pagination file
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
	$reload = 'modulos/existencias_inventario_general.php';
	//main query to fetch the data
	$sql = "SELECT * FROM $sTable $sWhere LIMIT $offset, $per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.codigo_producto");'>Código</button></th>
						<th class='col-xs-4' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.nombre_producto");'>Producto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.nombre_producto");'>Marca</button></th>
						<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.saldo_producto");'>Inventario</button></th>
						<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.saldo_producto");'>Consignación</button></th>
						<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.saldo_producto");'>Total</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.id_medida");'>Medida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.id_bodega");'>Bodega</button></th>
						<th class="text-right" style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.saldo_producto");'>USD</button></th>
					</tr>
					<?php

					$suma_saldo_consignado = array();
					$suma_saldo_inventario = array();
					while ($row = mysqli_fetch_array($query)) {
						$id_producto = $row['id_producto'];
						$id_inventario = $row['id_existencia_tmp'];
						$codigo_producto = strtoupper($row['codigo_producto']);
						$nombre_producto = strtoupper($row['nombre_producto']);
						$cantidad_total = $row['saldo_producto'];
						$id_bodega = $row['id_bodega'];
						$id_medida = $row['id_medida'];
						$precio_producto = $row['precio_producto'];
						$unidad_medida = $row['nombre_medida'];
						$nombre_bodega = $row['nombre_bodega'];
						$suma_saldo_inventario[] = $cantidad_total * $precio_producto;

						//marcas
						$busca_marca = "SELECT * FROM marca_producto as mar_pro, marca as mar WHERE mar_pro.id_marca=mar.id_marca and mar_pro.ruc_empresa = '" . $ruc_empresa . "' and mar_pro.id_producto='" . $id_producto . "'";
						$result_marca = $con->query($busca_marca);
						$row_marca = mysqli_fetch_array($result_marca);
						$nombre_marca = $row_marca['nombre_marca'];

						//desde aqui la consignacion
						$suma_entrada = mysqli_query($con, "SELECT sum(cant_consignacion) as entradas FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con ON det_con.codigo_unico=enc_con.codigo_unico WHERE det_con.id_producto='" . $id_producto . "' and det_con.ruc_empresa='" . $ruc_empresa . "' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='ENTRADA' ");
						$row_entrada = mysqli_fetch_array($suma_entrada);
						$cantidad_entrada = $row_entrada['entradas'];

						$suma_devuelta = mysqli_query($con, "SELECT sum(cant_consignacion) as devueltas FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con ON det_con.codigo_unico=enc_con.codigo_unico WHERE det_con.id_producto='" . $id_producto . "' and det_con.ruc_empresa='" . $ruc_empresa . "' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='DEVOLUCIÓN' ");
						$row_devuelta = mysqli_fetch_array($suma_devuelta);
						$cantidad_devuelta = $row_devuelta['devueltas'];

						$suma_facturada = mysqli_query($con, "SELECT sum(cant_consignacion) as facturada FROM detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con ON det_con.codigo_unico=enc_con.codigo_unico WHERE det_con.id_producto='" . $id_producto . "' and det_con.ruc_empresa='" . $ruc_empresa . "' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='FACTURA' ");
						$row_facturada = mysqli_fetch_array($suma_facturada);
						$cantidad_facturada = $row_facturada['facturada'];

						$saldo_consignado = $cantidad_entrada - $cantidad_devuelta - $cantidad_facturada;
						$suma_saldo_consignado[] = $saldo_consignado * $precio_producto;
						//hasta aqui la consignacion

					?>
						<input type="hidden" value="<?php echo $ruc_empresa; ?>" id="ruc_empresa<?php echo $id_inventario; ?>">
						<input type="hidden" value="<?php echo $id_producto; ?>" id="id_producto_item<?php echo $id_inventario; ?>">
						<input type="hidden" value="<?php echo $id_bodega; ?>" id="id_bodega<?php echo $id_inventario; ?>">
						<tr>
							<td><?php echo strtoupper($codigo_producto); ?></td>
							<td class='col-xs-4'><?php echo strtoupper($nombre_producto); ?></td>
							<td><?php echo strtoupper($nombre_marca); ?></td>
							<td class='text-center'><?php echo number_format($cantidad_total, 4, '.', ''); ?></td>
							<td class='text-center'><?php echo number_format($saldo_consignado, 2, '.', ''); ?></td>
							<td class='text-center'><?php echo number_format($cantidad_total + $saldo_consignado, 2, '.', ''); ?></td>
							<td><?php echo strtoupper($unidad_medida); ?></td>
							<td><?php echo strtoupper($nombre_bodega); ?></td>
							<td class='text-right'>$ <?php echo number_format($precio_producto * ($cantidad_total + $saldo_consignado), 2, '.', ''); ?></td>
						</tr>
					<?php
					}
					//PARA SACAR EL TOTAL de cada pagina
					$total_general = array_sum($suma_saldo_inventario) + array_sum($suma_saldo_consignado);

					?>
					<tr class="info">
						<th colspan="8"><span class="pull-right">
								<?php
								echo "Total USD: ";
								?></span>
						</th>
						<td><span class="pull-right">
								<?php
								echo "$ " . number_format($total_general, 2, '.', '');
								?></span>
						</td>
					</tr>
					<tr>
						<td colspan="9"><span class="pull-right">
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
if ($action == 'general') {
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$aColumns = array('inv_tmp.codigo_producto', 'inv_tmp.nombre_producto'); //Columnas de busqueda
	$sTable = "existencias_inventario_tmp as inv_tmp INNER JOIN productos_servicios as pro_ser ON inv_tmp.id_producto=pro_ser.id INNER JOIN unidad_medida as uni_med ON inv_tmp.id_medida=uni_med.id_medida INNER JOIN bodega as bod ON inv_tmp.id_bodega=bod.id_bodega";
	$sWhere = "WHERE inv_tmp.ruc_empresa ='" . $ruc_empresa . "' ";
	if ($_GET['q'] != "") {
		$sWhere = "WHERE (inv_tmp.ruc_empresa ='" . $ruc_empresa . "' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND inv_tmp.ruc_empresa ='" . $ruc_empresa . "' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND inv_tmp.ruc_empresa = '" . $ruc_empresa . "' ", -3);
		$sWhere .= ')';
	}
	$sWhere .= " order by $ordenado $por ";

	include("../ajax/pagination.php"); //include pagination file
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
	$reload = 'modulos/existencias_inventario_general.php';
	//main query to fetch the data
	$sql = "SELECT * FROM $sTable $sWhere LIMIT $offset, $per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
	?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.codigo_producto");'>Código</button></th>
						<th class='col-xs-4' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.nombre_producto");'>Producto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.nombre_producto");'>Marca</button></th>
						<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.saldo_producto");'>Existencias</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.id_medida");'>Medida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.id_bodega");'>Bodega</button></th>
						<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("inv_tmp.saldo_producto");'>Total</button></th>
						<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Mímimos</button></th>
					</tr>
					<?php


					while ($row = mysqli_fetch_array($query)) {
						$id_producto = $row['id_producto'];
						$id_inventario = $row['id_existencia_tmp'];
						$codigo_producto = strtoupper($row['codigo_producto']);
						$nombre_producto = strtoupper($row['nombre_producto']);
						$cantidad_total = $row['saldo_producto'];
						$id_bodega = $row['id_bodega'];
						$id_medida = $row['id_medida'];
						$precio_total = $cantidad_total * $row['precio_producto'];
						$unidad_medida = $row['nombre_medida'];
						$nombre_bodega = $row['nombre_bodega'];

						//minimos inventarios
						$busca_minimo = "SELECT * FROM minimos_inventarios WHERE ruc_empresa = '" . $ruc_empresa . "' and id_producto='" . $id_producto . "' and id_bodega='" . $id_bodega . "'";
						$result_bodega = $con->query($busca_minimo);
						$row_minimo = mysqli_fetch_array($result_bodega);
						$id_minimo = isset($row_minimo['id_minimo']) ? $row_minimo['id_minimo'] : 0;
						$minimo = isset($row_minimo['valor_minimo']) ? $row_minimo['valor_minimo'] : 0;

						//marcas
						$busca_marca = "SELECT * FROM marca_producto as mar_pro, marca as mar WHERE mar_pro.id_marca=mar.id_marca and mar_pro.ruc_empresa = '" . $ruc_empresa . "' and mar_pro.id_producto='" . $id_producto . "'";
						$result_marca = $con->query($busca_marca);
						$row_marca = mysqli_fetch_array($result_marca);
						$nombre_marca = $row_marca['nombre_marca'];


						//minimos
						if ($minimo == "") {
							$minimo = 1;
						}
						if ($cantidad_total > $minimo) {
							$label_class_minimo = 'label-success';
						}
						if ($cantidad_total < $minimo) {
							$label_class_minimo = 'label-danger';
						}
						if ($cantidad_total == $minimo) {
							$label_class_minimo = 'label-warning';
						}

					?>
						<input type="hidden" value="<?php echo $id_minimo; ?>" id="id_minimo<?php echo $id_inventario; ?>">
						<input type="hidden" value="<?php echo $ruc_empresa; ?>" id="ruc_empresa<?php echo $id_inventario; ?>">
						<input type="hidden" value="<?php echo $id_producto; ?>" id="id_producto_item<?php echo $id_inventario; ?>">
						<input type="hidden" value="<?php echo $id_bodega; ?>" id="id_bodega<?php echo $id_inventario; ?>">
						<input type="hidden" value="<?php echo $minimo; ?>" id="valor_minimo<?php echo $id_inventario; ?>">
						<tr>
							<td><?php echo strtoupper($codigo_producto); ?></td>
							<td class='col-xs-4'><?php echo strtoupper($nombre_producto); ?></td>
							<td><?php echo strtoupper($nombre_marca); ?></td>
							<td class='text-center'><span class="label <?php echo $label_class_minimo; ?>"><?php echo number_format($cantidad_total, 4, '.', ''); ?></span></td>
							<td><?php echo strtoupper($unidad_medida); ?></td>
							<td><?php echo strtoupper($nombre_bodega); ?></td>
							<td class='text-right'>$ <?php echo number_format($precio_total, 2, '.', ''); ?></td>
							<td class='text-center'><a href="#" class='btn btn-info btn-sm' title='Editar mínimos' onclick="obtener_datos('<?php echo $id_inventario; ?>');" data-toggle="modal" data-target="#EditarMinimos"> <?php echo $minimo; ?></a></td>
						</tr>
					<?php
					}
					//PARA SACAR EL TOTAL
					$busca_total = mysqli_query($con, "SELECT sum(exi.saldo_producto*pro.precio_producto) as total_general FROM existencias_inventario_tmp as exi, productos_servicios as pro WHERE exi.ruc_empresa = '" . $ruc_empresa . "' and pro.id = exi.id_producto ");
					$row_total = mysqli_fetch_array($busca_total);
					$total_general = $row_total['total_general'];

					?>
					<tr class="info">
						<th colspan="6"><span class="pull-right">
								<?php
								echo "Total general: ";
								?></span>
						</th>
						<td><span class="pull-right">
								<?php
								echo "$ " . number_format($total_general, 2, '.', '');
								?></span>
						</td>
						<td>
						</td>
					</tr>
					<tr>
						<td colspan="9"><span class="pull-right">
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

//para buscar existencias por fecha de caducidad
if ($action == 'fecha_caducidad') {
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$aColumns = array('codigo_producto', 'nombre_producto', 'fecha_caducidad'); //Columnas de busqueda
	$sTable = "existencias_inventario_tmp";
	$sWhere = "WHERE ruc_empresa ='" .  $ruc_empresa . "' and saldo_producto>0 ";
	if ($_GET['q'] != "") {
		$sWhere = "WHERE (ruc_empresa ='" .  $ruc_empresa . " ' and saldo_producto>0 AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND ruc_empresa ='" .  $ruc_empresa . "' and saldo_producto>0 OR ";
		}
		$sWhere = substr_replace($sWhere, "AND ruc_empresa = '" .  $ruc_empresa . "' and saldo_producto>0 ", -3);
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
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = 'modulos/existencias_inventario_caducidad.php';
	//main query to fetch the data
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
	?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
						<th class='col-xs-4' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Producto</button></th>
						<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Existencias</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_medida");'>Medida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_bodega");'>Bodega</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_caducidad");'>Vencimiento</button></th>
						<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Total</button></th>
						<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Mímimos</button></th>
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
						$id_producto = $row['id_producto'];
						$id_inventario = $row['id_existencia_tmp'];
						$codigo_producto = strtoupper($row['codigo_producto']);
						$nombre_producto = strtoupper($row['nombre_producto']);
						$cantidad_total = $row['saldo_producto'];
						$saldo_producto = $row['saldo_producto'];

						$id_bodega = $row['id_bodega'];
						$id_medida = $row['id_medida'];
						$fecha_vencimiento = $row['fecha_caducidad'];

						//buscar nombre de unidades de medida
						$busca_precio = "SELECT * FROM productos_servicios WHERE id = '" . $id_producto . "'";
						$result_precio = $con->query($busca_precio);
						$row_precio = mysqli_fetch_array($result_precio);
						$saldo_producto = $cantidad_total * $row_precio['precio_producto'];

						//buscar nombre de unidades de medida
						$busca_medida = "SELECT * FROM unidad_medida WHERE id_medida = '" . $id_medida . "'";
						$result_medida = $con->query($busca_medida);
						$row_medida = mysqli_fetch_array($result_medida);
						$unidad_medida = $row_medida['nombre_medida'];

						//buscar bodegas
						$busca_bodega = "SELECT * FROM bodega WHERE id_bodega = '" . $id_bodega . "'";
						$result_bodega = $con->query($busca_bodega);
						$row_bodega = mysqli_fetch_array($result_bodega);
						$nombre_bodega = $row_bodega['nombre_bodega'];

						//minimos inventarios
						$busca_minimo = "SELECT * FROM minimos_inventarios WHERE ruc_empresa = '" . $ruc_empresa . "' and id_producto='" . $id_producto . "' and id_bodega='" . $id_bodega . "'";
						$result_bodega = $con->query($busca_minimo);
						$row_minimo = mysqli_fetch_array($result_bodega);
						$id_minimo = $row_minimo['id_minimo'];
						$minimo = $row_minimo['valor_minimo'];

						//minimos
						if ($minimo == "") {
							$minimo = 1;
						}
						if ($cantidad_total > $minimo) {
							$label_class_minimo = 'label-success';
						}
						if ($cantidad_total < $minimo) {
							$label_class_minimo = 'label-danger';
						}
						if ($cantidad_total == $minimo) {
							$label_class_minimo = 'label-warning';
						}
						//if ($saldo_producto>0){	
					?>

						<input type="hidden" value="<?php echo $id_minimo; ?>" id="id_minimo<?php echo $id_inventario; ?>">
						<input type="hidden" value="<?php echo $ruc_empresa; ?>" id="ruc_empresa<?php echo $id_inventario; ?>">
						<input type="hidden" value="<?php echo $id_producto; ?>" id="id_producto_item<?php echo $id_inventario; ?>">
						<input type="hidden" value="<?php echo $id_bodega; ?>" id="id_bodega<?php echo $id_inventario; ?>">
						<input type="hidden" value="<?php echo $minimo; ?>" id="valor_minimo<?php echo $id_inventario; ?>">

						<tr>
							<td><?php echo strtoupper($codigo_producto); ?></td>
							<td class='col-xs-4'><?php echo strtoupper($nombre_producto); ?></td>
							<td class='text-center'><span class="label <?php echo $label_class_minimo; ?>"><?php echo number_format($cantidad_total, 4, '.', ''); ?></span></td>
							<td><?php echo strtoupper($unidad_medida); ?></td>
							<td><?php echo strtoupper($nombre_bodega); ?></td>
							<td><?php echo date("d-m-Y", strtotime($fecha_vencimiento)); ?></td>
							<td class='text-center'><?php echo number_format($saldo_producto, 2, '.', ''); ?></td>
							<td class='text-center'><a href="#" class='btn btn-info btn-sm' title='Editar mínimos' onclick="obtener_datos('<?php echo $id_inventario; ?>');" data-toggle="modal" data-target="#EditarMinimos"> <?php echo $minimo; ?></a></td>
						</tr>
					<?php
						//}
					}
					?>
					<tr>
						<td colspan="9"><span class="pull-right">
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

//para buscar existencias por lote
if ($action == 'lote') {
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$aColumns = array('codigo_producto', 'nombre_producto', 'lote'); //Columnas de busqueda
	$sTable = "existencias_inventario_tmp";
	$sWhere = "WHERE ruc_empresa ='" .  $ruc_empresa . "'  ";
	if ($_GET['q'] != "") {
		$sWhere = "WHERE (ruc_empresa ='" .  $ruc_empresa . " ' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND ruc_empresa ='" .  $ruc_empresa . "'  OR ";
		}
		$sWhere = substr_replace($sWhere, "AND ruc_empresa = '" .  $ruc_empresa . "' ", -3);
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
	$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
	$row = mysqli_fetch_array($count_query);
	$numrows = $row['numrows'];
	$total_pages = ceil($numrows / $per_page);
	$reload = 'modulos/existencias_inventario_lote.php';
	//main query to fetch the data
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
	?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
						<th class='col-xs-4' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Producto</button></th>
						<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Existencias</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_medida");'>Medida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_bodega");'>Bodega</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("lote");'>Lote</button></th>
						<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("saldo_producto");'>Total</button></th>
						<th class='text-right' style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Mímimos</button></th>
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
						$id_producto = $row['id_producto'];
						$id_inventario = $row['id_existencia_tmp'];
						$codigo_producto = strtoupper($row['codigo_producto']);
						$nombre_producto = strtoupper($row['nombre_producto']);
						$cantidad_total = $row['saldo_producto'];
						$saldo_producto = $row['saldo_producto'];

						$id_bodega = $row['id_bodega'];
						$id_medida = $row['id_medida'];
						$lote = $row['lote'];

						//buscar nombre de unidades de medida
						$busca_precio = "SELECT * FROM productos_servicios WHERE id = '" . $id_producto . "'";
						$result_precio = $con->query($busca_precio);
						$row_precio = mysqli_fetch_array($result_precio);
						$saldo_producto = $cantidad_total * $row_precio['precio_producto'];

						//buscar nombre de unidades de medida
						$busca_medida = "SELECT * FROM unidad_medida WHERE id_medida = '" . $id_medida . "'";
						$result_medida = $con->query($busca_medida);
						$row_medida = mysqli_fetch_array($result_medida);
						$unidad_medida = $row_medida['nombre_medida'];

						//buscar bodegas
						$busca_bodega = "SELECT * FROM bodega WHERE id_bodega = '" . $id_bodega . "'";
						$result_bodega = $con->query($busca_bodega);
						$row_bodega = mysqli_fetch_array($result_bodega);
						$nombre_bodega = $row_bodega['nombre_bodega'];

						//minimos inventarios
						$busca_minimo = "SELECT * FROM minimos_inventarios WHERE ruc_empresa = '" . $ruc_empresa . "' and id_producto='" . $id_producto . "' and id_bodega='" . $id_bodega . "'";
						$result_bodega = $con->query($busca_minimo);
						$row_minimo = mysqli_fetch_array($result_bodega);
						$id_minimo = $row_minimo['id_minimo'];
						$minimo = $row_minimo['valor_minimo'];

						//minimos
						if ($minimo == "") {
							$minimo = 1;
						}
						if ($cantidad_total > $minimo) {
							$label_class_minimo = 'label-success';
						}
						if ($cantidad_total < $minimo) {
							$label_class_minimo = 'label-danger';
						}
						if ($cantidad_total == $minimo) {
							$label_class_minimo = 'label-warning';
						}
						//if ($saldo_producto>0){	
					?>

						<input type="hidden" value="<?php echo $id_minimo; ?>" id="id_minimo<?php echo $id_inventario; ?>">
						<input type="hidden" value="<?php echo $ruc_empresa; ?>" id="ruc_empresa<?php echo $id_inventario; ?>">
						<input type="hidden" value="<?php echo $id_producto; ?>" id="id_producto_item<?php echo $id_inventario; ?>">
						<input type="hidden" value="<?php echo $id_bodega; ?>" id="id_bodega<?php echo $id_inventario; ?>">
						<input type="hidden" value="<?php echo $minimo; ?>" id="valor_minimo<?php echo $id_inventario; ?>">

						<tr>
							<td><?php echo strtoupper($codigo_producto); ?></td>
							<td class='col-xs-4'><?php echo strtoupper($nombre_producto); ?></td>
							<td class='text-center'><span class="label <?php echo $label_class_minimo; ?>"><?php echo number_format($cantidad_total, 4, '.', ''); ?></span></td>
							<td><?php echo strtoupper($unidad_medida); ?></td>
							<td><?php echo strtoupper($nombre_bodega); ?></td>
							<td><?php echo ($lote); ?></td>
							<td class='text-center'><?php echo number_format($saldo_producto, 2, '.', ''); ?></td>
							<td class='text-center'><a href="#" class='btn btn-info btn-sm' title='Editar mínimos' onclick="obtener_datos('<?php echo $id_inventario; ?>');" data-toggle="modal" data-target="#EditarMinimos"> <?php echo $minimo; ?></a></td>
						</tr>
					<?php
						//}
					}
					?>
					<tr>
						<td colspan="9"><span class="pull-right">
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



//transferir entre bodegas
if ($action == 'transferir') {
	ini_set('date.timezone', 'America/Guayaquil');
	include("../validadores/generador_codigo_unico.php");

	if (empty($_GET['id'])) {
		echo "<script>$.notify('Seleccione un producto.','error')</script>";
	} else if (empty($_GET['cantidad'])) {
		echo "<script>$.notify('Ingrese cantidad a transferir.','error')</script>";
	} else if ($_GET['existencia'] <= 0) {
		echo "<script>$.notify('No hay existencia disponible.','error')</script>";
	} else if (!is_numeric($_GET['cantidad'])) {
		echo "<script>$.notify('Ingrese cantidad en números.','error')</script>";
	} else if (empty($_GET['id_medida_transferir'])) {
		echo "<script>$.notify('Seleccione medida.','error')</script>";
	} else if (empty($_GET['id_bodega_transferir'])) {
		echo "<script>$.notify('Seleccione bodega.','error')</script>";
	} elseif ($_GET['id_bodega_transferir'] == $_GET['id_bodega_existente']) {
		echo "<script>$.notify('No es posible transferir a la misma bodega.','error')</script>";
	} else if (!empty($_GET['id']) && !empty($_GET['cantidad']) && !empty($_GET['id_medida_transferir']) && !empty($_GET['id_bodega_transferir'])) {
		$id_bodega_transferir = mysqli_real_escape_string($con, (strip_tags($_GET['id_bodega_transferir'], ENT_QUOTES)));
		$id_bodega_existente = mysqli_real_escape_string($con, (strip_tags($_GET['id_bodega_existente'], ENT_QUOTES)));
		$cantidad = mysqli_real_escape_string($con, (strip_tags($_GET['cantidad'], ENT_QUOTES)));
		$existencia = mysqli_real_escape_string($con, (strip_tags($_GET['existencia'], ENT_QUOTES)));
		$id_medida_salida = mysqli_real_escape_string($con, (strip_tags($_GET['id_medida_producto'], ENT_QUOTES)));
		$id_medida_entrada = mysqli_real_escape_string($con, (strip_tags($_GET['id_medida_transferir'], ENT_QUOTES)));
		$id_producto = mysqli_real_escape_string($con, (strip_tags($_GET['id_producto'], ENT_QUOTES)));
		$lote = mysqli_real_escape_string($con, (strip_tags($_GET['lote'], ENT_QUOTES)));
		$fecha_registro = date("Y-m-d H:i:s");
		$tipo_salida = "T";
		$codigo_unico = codigo_unico(20);

		$sql_costo = mysqli_query($con, "SELECT costo_unitario as costo, fecha_vencimiento as vencimiento FROM inventarios WHERE id_producto= '" . $id_producto . "' and lote ='" . $lote . "' and ruc_empresa='" . $ruc_empresa . "' and operacion='ENTRADA' ");
		$row_costo = mysqli_fetch_assoc($sql_costo);
		$costo = $row_costo['costo'];
		$fecha_caducidad = $row_costo['vencimiento'];

		//para ver nombre de bodega de donde sale
		$sql_bodega_sale = mysqli_query($con, "SELECT * FROM bodega WHERE id_bodega= '" . $id_bodega_existente . "' ");
		$row_bodega_sale = mysqli_fetch_assoc($sql_bodega_sale);
		$nombre_bodega_sale = $row_bodega_sale['nombre_bodega'];
		$ruc_bodega_sale = $row_bodega_sale['ruc_empresa'];

		//para ver nombre de bodega de donde entra
		$sql_bodega_entra = mysqli_query($con, "SELECT * FROM bodega WHERE id_bodega= '" . $id_bodega_transferir . "' ");
		$row_bodega_entra = mysqli_fetch_assoc($sql_bodega_entra);
		$nombre_bodega_entra = $row_bodega_entra['nombre_bodega'];
		$ruc_bodega_entra = $row_bodega_entra['ruc_empresa'];

		$referencia_entrada = "Transferencia interna recibida desde bodega " . $nombre_bodega_sale . " a bodega " . $nombre_bodega_entra;
		$referencia_salida = "Transferencia interna enviada a bodega " . $nombre_bodega_entra . " desde bodega " . $nombre_bodega_sale;

		$sql_datos_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id= '" . $id_producto . "' ");
		$row_datos_producto = mysqli_fetch_array($sql_datos_producto);
		$codigo_producto_transferir = $row_datos_producto['codigo_producto'];
		$nombre_producto_transferir = $row_datos_producto['nombre_producto'];

		$sql_salida = mysqli_query($con, "INSERT INTO inventarios VALUES (NULL, '" . $ruc_bodega_sale . "', '" . $id_producto . "','" . $costo . "','0','" . $cantidad . "','" . $fecha_registro . "','" . $fecha_caducidad . "','" . $referencia_salida . "', '" . $id_usuario . "', '" . $id_medida_salida . "','" . $fecha_registro . "','" . $tipo_salida . "', '" . $id_bodega_existente . "','SALIDA','" . $codigo_producto_transferir . "','" . $nombre_producto_transferir . "','0','OK','" . $lote . "','" . $codigo_unico . "')");
		$sql_entrada = mysqli_query($con, "INSERT INTO inventarios VALUES (NULL, '" . $ruc_bodega_entra . "', '" . $id_producto . "','0', '" . $cantidad . "',0,'" . $fecha_registro . "','" . $fecha_caducidad . "','" . $referencia_entrada . "', '" . $id_usuario . "', '" . $id_medida_entrada . "','" . $fecha_registro . "','" . $tipo_salida . "', '" . $id_bodega_transferir . "','ENTRADA','" . $codigo_producto_transferir . "','" . $nombre_producto_transferir . "','" . $costo . "','OK','" . $lote . "','" . $codigo_unico . "')");
		if ($sql_entrada && $sql_salida) {
			echo "<script>$.notify('Transferido con éxito.','success')</script>";
		} else {
			echo "<script>$.notify('No se realizó la entrada de inventario, intente otra vez.','error')</script>";
		}
	} else {
		echo "<script>$.notify('Error desconocido.','error')</script>";
	}
}

if ($action == 'saldos_para_transferencias') {
	$id_producto = mysqli_real_escape_string($con, (strip_tags($_REQUEST['id_producto'], ENT_QUOTES)));
	$lote = mysqli_real_escape_string($con, (strip_tags($_REQUEST['lote'], ENT_QUOTES)));
	$tipo = mysqli_real_escape_string($con, (strip_tags($_REQUEST['tipo'], ENT_QUOTES)));
	$bodega = mysqli_real_escape_string($con, (strip_tags($_REQUEST['bodega'], ENT_QUOTES)));

	if ($tipo == "1") {

		if (empty($lote)) {
			$condicion_lote = "";
		} else {
			$condicion_lote = " and inv.lote LIKE '%" . $lote . "%' ";
		}

		$query_inventario = mysqli_query($con, "SELECT inv.id_inventario as id,
		sum(inv.cantidad_entrada - inv.cantidad_salida) as existencia, inv.codigo_producto as codigo_producto, inv.nombre_producto as nombre_producto,
		bod.nombre_bodega as bodega_actual, med.nombre_medida as medida, inv.id_medida as id_medida, inv.lote as lote, 
		inv.id_bodega as id_bodega
		 FROM inventarios as inv 
		 INNER JOIN bodega as bod ON bod.id_bodega=inv.id_bodega 
		 INNER JOIN unidad_medida as med ON med.id_medida=inv.id_medida
		  WHERE inv.id_producto='" . $id_producto . "' and inv.ruc_empresa ='" . $ruc_empresa . "' $condicion_lote 
		  and inv.id_bodega ='" . $bodega . "' group by inv.id_bodega, inv.id_producto, inv.id_medida, inv.lote HAVING existencia > 0");
	?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Bodega_Inicial</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Código</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Producto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Medida</button></th>
						<th class="text-right" style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Saldo</button></th>
						<th class="text-right" style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">lote</button></th>
						<th class="text-right" style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-success">Cantidad_transferir</button></th>
						<th class="text-right" style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-success">Medida</button></th>
						<th class="text-right" style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-success">Bodega_Final</button></th>
						<th class="text-right" style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-success">Enviar</button></th>
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query_inventario)) {
					?>
						<input type="hidden" id="existencia<?php echo $row['id']; ?>" value="<?php echo $row['existencia']; ?>">
						<input type="hidden" id="id_medida_entrada<?php echo $row['id']; ?>" value="<?php echo $row['id_medida']; ?>">
						<input type="hidden" id="lote<?php echo $row['id']; ?>" value="<?php echo $row['lote']; ?>">
						<tr>
							<td><?php echo strtoupper($row['bodega_actual']); ?></td>
							<td><?php echo $row['codigo_producto']; ?></td>
							<td class="col-xs-3"><?php echo strtoupper($row['nombre_producto']); ?></td>
							<td><?php echo strtoupper($row['medida']); ?></td>
							<td class="text-right"><?php echo $row['existencia']; ?></td>
							<td><?php echo strtoupper($row['lote']); ?></td>
							<td><input type="text" class="form-control text-right col-xs-2" id="cantidad_transferir<?php echo $row['id']; ?>"></td>
							<td>
								<select class="form-control" id="medida_transferir<?php echo $row['id']; ?>">
									<?php
									//para saber el tipo de unidad de medida
									$busca_tipo_medida = "SELECT * FROM unidad_medida WHERE id_medida='" . $row['id_medida'] . "' ";
									$resultado_tipo_medida = $con->query($busca_tipo_medida);
									$row_tipo_medida = mysqli_fetch_array($resultado_tipo_medida);
									$id_tipo_medida = $row_tipo_medida['id_tipo_medida'];

									$res_med = mysqli_query($con, "SELECT * FROM unidad_medida WHERE id_tipo_medida= '" . $id_tipo_medida . "'");
									while ($med = mysqli_fetch_assoc($res_med)) {
										if ($row['id_medida'] == $med['id_medida']) {
									?>
											<option value="<?php echo $row['id_medida']; ?>" selected><?php echo strtoupper($row['medida']) ?> </option>
										<?php
										} else {
										?>
											<option value="<?php echo $med['id_medida'] ?>"><?php echo strtoupper($med['nombre_medida']) ?> </option>
									<?php
										}
									}
									?>
								</select>
							</td>
							<td>
								<select class="form-control" id="id_bodega_transferir<?php echo $row['id']; ?>">
									<?php
									$sql_bod_final = mysqli_query($con, "SELECT * FROM bodega WHERE ruc_empresa= '" . $ruc_empresa . "' order by nombre_bodega asc");
									while ($row_bod_final = mysqli_fetch_assoc($sql_bod_final)) {
									?>
										<option value="<?php echo $row_bod_final['id_bodega'] ?>" selected><?php echo strtoupper($row_bod_final['nombre_bodega']) ?> </option>
									<?php
									}
									?>
								</select>
							</td>
							<td class='text-center'><a href="#" class='btn btn-info btn-sm' title='Transferir' onclick="transferir('<?php echo $row['id']; ?>');"><i class="glyphicon glyphicon-share-alt"></i></a></td>
						</tr>
					<?php
					}
					?>
				</table>
			</div>
		</div>
<?php
	}
}

?>