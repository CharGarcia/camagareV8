<?php
//include("../validadores/generador_codigo_unico.php");
include("../conexiones/conectalogin.php");
include("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//para reiniciar el arreglo cada vez que haya un nuevo cambio de producto
if ($action == 'nuevo_cambio') {
	unset($_SESSION['arrayDetalleProductos']);
}

//para eliminar todo el registro de cambios de productos
if ($action == 'eliminar_registro_total') {
	$codigo_unico = mysqli_real_escape_string($con, (strip_tags($_GET["codigo_unico"], ENT_QUOTES)));

	$delete_inventarios = mysqli_query($con, "DELETE FROM inventarios WHERE id_documento_venta = '" . $codigo_unico . "'");
	$delete_productos_cambiados = mysqli_query($con, "DELETE FROM cambio_productos_facturados WHERE codigo_unico = '" . $codigo_unico . "'");
	$actualiza_encabezado_consignacion = mysqli_query($con, "UPDATE encabezado_consignacion SET factura_venta='',observaciones='REGISTRO ANULADO DESDE CAMBIO DE PRODUCTOS', status='0' WHERE codigo_unico='" . $codigo_unico . "'");
	$delete_detalle_consignacion = mysqli_query($con, "DELETE FROM detalle_consignacion WHERE codigo_unico = '" . $codigo_unico . "'");

	echo "<script>$.notify('Registros eliminados.','success');
	setTimeout(function (){location.reload()}, 1000);
	</script>";
}

//para buscar los cambios de productos
if ($action == 'buscar_cambio_producto') {
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$aColumns = array('cam_pro.factura', 'pro_ser.nombre_producto', 'pro_ser.codigo_producto', 'cam_pro.nuevo_lote', 'cli.nombre', 'cam_pro.observaciones', 'cam_pro.lote_anterior', 'cam_pro.fecha_registro'); //Columnas de busqueda
	$sTable = "cambio_productos_facturados as cam_pro 
		 INNER JOIN productos_servicios as pro_ser ON cam_pro.id_producto_anterior=pro_ser.id 
		 LEFT JOIN clientes as cli ON cli.id=cam_pro.id_cliente INNER JOIN bodega as bod ON bod.id_bodega=cam_pro.id_bodega_anterior";
	$sWhere = "WHERE cam_pro.ruc_empresa ='" . $ruc_empresa . "' ";
	if ($_GET['q'] != "") {
		$sWhere = "WHERE (cam_pro.ruc_empresa ='" . $ruc_empresa . "' AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND cam_pro.ruc_empresa ='" . $ruc_empresa . "' OR ";
		}

		$sWhere = substr_replace($sWhere, "AND cam_pro.ruc_empresa ='" . $ruc_empresa . "' ", -3);
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
	$reload = '../cambio_producto_inventario.php';
	//main query to fetch the data
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
?>
		<div class="table-responsive">
			<div class="panel panel-info">
				<table class="table table-hover">
					<tr>
						<th class="info">Fecha</button></th>
						<th class="danger">Cantidad facturada</button></th>
						<th class="danger">Producto anterior</button></th>
						<th class="danger">Lote anterior</button></th>
						<th class="danger">Bodega anterior</button></th>
						<th class="danger">Factura</button></th>
						<th class="success">Producto nuevo</button></th>
						<th class="success">Cantidad nueva</button></th>
						<th class="success">Nuevo lote</button></th>
						<th class="success">Bodega Nueva</button></th>
						<th class="success">Cliente</button></th>
						<th class="success">Observaciones</button></th>
						<th class='text-right info'>Opciones</th>
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
						$id_cambio = $row['id_cambio'];
						$nombre_producto = "(" . $row['codigo_producto'] . ") " . $row['nombre_producto'];
						$cant_facturada = $row['cant_facturada'];
						$cant_cambiada = $row['cant_cambiada'];
						$lote_anterior = strtoupper($row['lote_anterior']);
						$nuevo_lote = strtoupper($row['nuevo_lote']);
						$id_nuevo_producto = $row['id_nuevo_producto'];
						$codigo_unico = $row['codigo_unico'];
						$fecha_cambio = $row['fecha_cambio'];
						$cliente = $row['nombre'];
						$observaciones = $row['observaciones'];
						$factura = $row['factura'];
						$nombre_bodega_anterior = $row['nombre_bodega'];
						$id_nueva_bodega = $row['id_nueva_bodega'];
						//buscar productos
						$busca_producto_salida = "SELECT * FROM productos_servicios WHERE id = '" . $id_nuevo_producto . "'";
						$result_produtos = $con->query($busca_producto_salida);
						$row_producto_salida = mysqli_fetch_array($result_produtos);
						$nombre_producto_salida = "(" . $row_producto_salida['codigo_producto'] . ") " . $row_producto_salida['nombre_producto'];

						$busca_bodega = mysqli_query($con, "SELECT nombre_bodega as nueva_bodega FROM bodega WHERE id_bodega = '" . $id_nueva_bodega . "'");
						$row_bodega = mysqli_fetch_array($busca_bodega);
					?>
						<td><?php echo date("d/m/Y", strtotime($fecha_cambio)); ?></td>
						<td class='text-right'><?php echo number_format($cant_facturada, 0, '.', ''); ?></td>
						<td class='col-xs-2'><?php echo strtoupper($nombre_producto); ?></td>
						<td><?php echo $lote_anterior; ?></td>
						<td><?php echo strtoupper($nombre_bodega_anterior); ?></td>
						<td class='col-xs-2'><?php echo $factura; ?></td>
						<td class='col-xs-2'><?php echo strtoupper($nombre_producto_salida); ?></td>
						<td class='text-right'><?php echo number_format($cant_cambiada, 0, '.', ''); ?></td>
						<td><?php echo $nuevo_lote; ?></td>
						<td><?php echo strtoupper($row_bodega['nueva_bodega']); ?></td>
						<td class='col-xs-2'><?php echo strtoupper($cliente); ?></td>
						<td class='col-xs-2'><?php echo strtoupper($observaciones); ?></td>

						<td class='text-right'>
							<a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_cambio_producto('<?php echo $codigo_unico; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
						</td>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="14"><span class="pull-right">
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


//para eliminar la linea del cambio de producto
if ($action == 'eliminar_registro_cambio_producto') {
	$intid = $_GET['codigo_unico'];
	$arrData = $_SESSION['arrayDetalleProductos'];
	for ($i = 0; $i < count($arrData); $i++) {
		if ($arrData[$i]['id'] == $intid) {
			unset($arrData[$i]);
			echo "<script>
            $.notify('Eliminado','error');
            </script>";
		}
	}
	sort($arrData); //para reordenar el array
	$_SESSION['arrayDetalleProductos'] = $arrData;
	muestra_detalle_productos();
}



//para guardar el cambio de productos
if ($action == 'guardar_cambio_producto') {
	if (empty($_POST['fecha_cambio_producto'])) {
		echo "<script>$.notify('Ingrese fecha.','error');
					</script>";
	} else if (!date($_POST['fecha_cambio_producto'])) {
		echo "<script>$.notify('Ingrese una fecha correcta.','error');
					</script>";
	} else if (empty($_POST['id_cliente_cambio'])) {
		echo "<script>$.notify('Seleccione un cliente.','error');
					</script>";
	} else if (empty($_POST['registros'])) {
		echo "<script>$.notify('No hay productos agregados para realizar el cambio.','error');
					</script>";
	} else if ((!empty($_POST['fecha_cambio_producto']))) {
		$id_cambio_producto = mysqli_real_escape_string($con, (strip_tags($_POST["id_cambio_producto"], ENT_QUOTES)));
		$fecha_cambio_producto = date('Y-m-d H:i:s', strtotime(mysqli_real_escape_string($con, (strip_tags($_POST["fecha_cambio_producto"], ENT_QUOTES)))));
		$id_cliente = mysqli_real_escape_string($con, (strip_tags($_POST["id_cliente_cambio"], ENT_QUOTES)));
		$serie_sucursal = mysqli_real_escape_string($con, (strip_tags($_POST["serie_factura"], ENT_QUOTES)));
		$observaciones = mysqli_real_escape_string($con, (strip_tags(strClean($_POST["observaciones"]), ENT_QUOTES)));
		ini_set('date.timezone', 'America/Guayaquil');
		$fecha_registro = date("Y-m-d H:i:s");

		//para ver que todos los registros tenga la informacion completa en todos los campos
		foreach ($_POST['registros'] as $valor) {
			if (empty($_POST['numero_consignacion'][$valor])) {
				$contador_consignaciones[] = 1;
				echo "<script>$.notify('Ingrese número de consignación.','error');
				document.getElementById('numero_consignacion' + $valor).focus();</script>";
				exit;
			} else {
				$contador_consignaciones[] = 0;
			}
			if (empty($_POST['id_cv'][$valor])) {
				$contador_productos[] = 1;
				echo "<script>$.notify('Ingrese producto.','error');
				document.getElementById('nombre_producto_cambio' + $valor).focus();
				</script>";
				exit;
			} else {
				$contador_productos[] = 0;
			}
			if (empty($_POST['cant_cambio'][$valor])) {
				$contador_cantidad[] = 1;
				echo "<script>$.notify('Ingrese cantidad.','error');
				document.getElementById('cant_cambio' + $valor).focus();</script>";
				exit;
			} else {
				$contador_cantidad[] = 0;
			}
		}

		if ((array_sum($contador_consignaciones) + array_sum($contador_productos) + array_sum($contador_cantidad)) == 0) {
			foreach ($_POST['registros'] as $valor) {
				$codigo_unico = codigo_aleatorio(19) . "4"; //el 4 es para identificar en las consignaciones que es un cambio de producto

				foreach ($_SESSION['arrayDetalleProductos'] as $producto) {
					if ($producto['id'] == $valor) {
						$serie_factura = substr($producto['factura'], 0, 7);
						$secuencial_factura = substr($producto['factura'], 8, 9);
						$id_producto_anterior = $producto['id_producto'];
						$factura = $serie_factura . "-" . $secuencial_factura;
						$id_bodega_anterior = $producto['id_bodega'];
						$id_medida_anterior = $producto['id_medida'];
						$vencimiento_anterior = date('Y-m-d', strtotime($producto['vencimiento']));
						$lote_anterior = $producto['lote'];
						$nueva_cantidad = $producto['cantidad'];
					}
				}

				$numero_consignacion_afecta = $_POST['numero_consignacion'][$valor];
				$detalle_consignacion = mysqli_query($con, "SELECT * FROM detalle_consignacion WHERE id_det_consignacion = '" . $_POST['id_cv'][$valor] . "' ");
				$row_detalle_cv = mysqli_fetch_array($detalle_consignacion);
				$id_nuevo_producto = $row_detalle_cv['id_producto'];
				$nuevo_lote = $row_detalle_cv['lote'];
				$nueva_bodega = $row_detalle_cv['id_bodega'];
				$codigo_producto = $row_detalle_cv['codigo_producto'];
				$nombre_producto = $row_detalle_cv['nombre_producto'];
				$vencimiento = $row_detalle_cv['vencimiento'];
				$id_bodega = $row_detalle_cv['id_bodega'];
				$id_medida = $row_detalle_cv['id_medida'];
				$nup = $row_detalle_cv['nup'];
				$precio = $row_detalle_cv['precio'];
				$descuento = $row_detalle_cv['descuento'];
				$cantidad_entra =  $_POST['cant_cambio'][$valor];


				$asesor_consignacion_inicial = mysqli_query($con, "SELECT * FROM encabezado_consignacion WHERE numero_consignacion = '" . $numero_consignacion_afecta . "' and ruc_empresa ='" . $ruc_empresa . "' and tipo_consignacion='VENTA' and operacion='ENTRADA' ");
				$row_consignacion_inicial = mysqli_fetch_array($asesor_consignacion_inicial);
				$id_asesor = $row_consignacion_inicial['responsable'];

				$contador = 0;
				if ($nueva_cantidad > 0 && $_POST['id_cv'][$valor] != "") {
					$detalle_cambio_producto = mysqli_query($con, "INSERT INTO cambio_productos_facturados VALUES (null,'" . $valor . "','" . $ruc_empresa . "','" . $id_producto_anterior . "','" . $id_nuevo_producto . "','" . $nuevo_lote . "','" . $nueva_cantidad . "','" . $fecha_registro . "','" . $id_usuario . "','" . $codigo_unico . "','" . $fecha_cambio_producto . "','" . $id_cliente . "','" . $cantidad_entra . "','" . $observaciones . "','" . $factura . "','" . $lote_anterior . "','" . $id_bodega_anterior . "','" . $id_medida_anterior . "','" . $vencimiento_anterior . "', '" . $nueva_bodega . "' )");
					//guardar la salida del inventario como facturacion
					$consulta_ultima_orden = mysqli_query($con, "SELECT max(numero_consignacion) as ultimo FROM encabezado_consignacion WHERE ruc_empresa='" . $ruc_empresa . "' and tipo_consignacion='VENTA' and operacion ='FACTURA'");
					$row_ultimo = mysqli_fetch_array($consulta_ultima_orden);
					$siguiente_orden = $row_ultimo['ultimo'] + 1;
					$observacion_consignacion_venta = "Cambios de productos facturados con anterioridad";

					$encabezado_consignacion = mysqli_query($con, "INSERT INTO encabezado_consignacion VALUES (null,'" . $fecha_cambio_producto . "','" . $ruc_empresa . "','" . $codigo_unico . "','" . $id_cliente . "','VENTA','" . $siguiente_orden . "','" . $observacion_consignacion_venta . "','" . $fecha_registro . "','" . $id_usuario . "','','','" . $id_asesor . "', 'FACTURA','" . $serie_factura . "','" . $secuencial_factura . "','0','0','0','0','1')");
					$detalle_consignacion = mysqli_query($con, "INSERT INTO detalle_consignacion VALUES (null,'" . $id_nuevo_producto . "','" . $codigo_producto . "','" . $nombre_producto . "','" . $nuevo_lote . "','" . $vencimiento . "','" . $id_bodega . "','" . $id_medida . "','" . $ruc_empresa . "','" . $codigo_unico . "','" . $nueva_cantidad . "','" . $numero_consignacion_afecta . "','" . $nup . "','" . $precio . "', '" . $descuento . "')");
					$contador++;
				} else {
					$contador = 0;
				}
			}
		}

		if ($contador > 0) {
			echo "<script>$.notify('Cambios en productos registrado.','success');
				setTimeout(function (){location.reload()}, 1000);
				</script>";
		} else {
			echo "<script>$.notify('Completar información de los nuevos cambios de productos.','error');
				</script>";
		}
	} else {
		echo "<script>$.notify('Intente de nuevo.','error');
				</script>";
	}
}

//para agregar items al cambio de productso
if ($action == 'agregar_detalle_productos') {
	$id_registro = $_GET['id_registro_facturado'];
	$tipo_cambio = $_GET['tipo_cambio'];
	$cantidad = $_GET['cantidad'];
	$arrayDetalleProductos = array();

	if ($tipo_cambio == 'F') {
		$detalle_producto_factura = mysqli_query($con, "SELECT concat(cue.serie_factura,'-',cue.secuencial_factura) as factura, 
		cue.id_cuerpo_factura as id_cuerpo_factura, cue.id_producto as id_producto, cue.id_bodega as id_bodega, 
		cue.id_medida_salida as id_medida_salida, cue.lote as lote, cue.vencimiento as vencimiento, pro.codigo_producto as codigo,
		pro.nombre_producto as producto, bod.nombre_bodega as bodega 
		FROM cuerpo_factura as cue 
		INNER JOIN productos_servicios as pro 
		ON pro.id=cue.id_producto
		INNER JOIN bodega as bod
		ON bod.id_bodega=cue.id_bodega 
		WHERE cue.id_cuerpo_factura='" . $id_registro . "'");
		$row_producto_factura = mysqli_fetch_array($detalle_producto_factura);
		$arrayDatos = array(
			'id' => $row_producto_factura['id_cuerpo_factura'],
			'id_producto' => $row_producto_factura['id_producto'],
			'cantidad' => $cantidad,
			'id_bodega' => $row_producto_factura['id_bodega'],
			'id_medida' => $row_producto_factura['id_medida_salida'],
			'lote' => $row_producto_factura['lote'],
			'vencimiento' => $row_producto_factura['vencimiento'],
			'factura' => $row_producto_factura['factura'],
			'codigo' => $row_producto_factura['codigo'],
			'producto' => $row_producto_factura['producto'],
			'bodega' => $row_producto_factura['bodega'],
			'tipo_cambio' => $tipo_cambio
		);

		if (isset($_SESSION['arrayDetalleProductos'])) {
			$on = true;
			$arrayDetalleProductos = $_SESSION['arrayDetalleProductos'];
			for ($pr = 0; $pr < count($arrayDetalleProductos); $pr++) {
				if ($arrayDetalleProductos[$pr]['id'] == $row_producto_factura['id_cuerpo_factura'] && $arrayDetalleProductos[$pr]['lote'] == $row_producto_factura['lote']) {
					echo "<script>$.notify('Producto ya agregado.','error');</script>";
					$on = false;
				}
			}
			if ($on) {
				array_push($arrayDetalleProductos, $arrayDatos);
			}
			$_SESSION['arrayDetalleProductos'] = $arrayDetalleProductos;
		} else {
			array_push($arrayDetalleProductos, $arrayDatos);
			$_SESSION['arrayDetalleProductos'] = $arrayDetalleProductos;
		}
	} else {
		$detalle_producto_factura = mysqli_query($con, "SELECT can.factura as factura, can.id_cambio as id_cambio,
		can.id_nuevo_producto as id_nuevo_producto, can.id_bodega_anterior as id_bodega_anterior, 
		can.id_medida_anterior as id_medida_anterior, can.nuevo_lote as nuevo_lote, can.vencimiento_anterior as vencimiento_anterior,
		pro.codigo_producto as codigo, pro.nombre_producto as producto, bod.nombre_bodega as bodega
		FROM cambio_productos_facturados as can
		INNER JOIN productos_servicios as pro 
		ON pro.id=can.id_nuevo_producto
		INNER JOIN bodega as bod
		ON bod.id_bodega=can.id_bodega_anterior
		WHERE can.id_cambio='" . $id_registro . "'");
		$row_producto_factura = mysqli_fetch_array($detalle_producto_factura);
		$arrayDatos = array(
			'id' => $row_producto_factura['id_cambio'],
			'id_producto' => $row_producto_factura['id_nuevo_producto'],
			'cantidad' => $cantidad,
			'id_bodega' => $row_producto_factura['id_bodega_anterior'],
			'id_medida' => $row_producto_factura['id_medida_anterior'],
			'lote' => $row_producto_factura['nuevo_lote'],
			'vencimiento' => $row_producto_factura['vencimiento_anterior'],
			'factura' => $row_producto_factura['factura'],
			'codigo' => $row_producto_factura['codigo'],
			'producto' => $row_producto_factura['producto'],
			'bodega' => $row_producto_factura['bodega'],
			'tipo_cambio' => $tipo_cambio
		);
		if (isset($_SESSION['arrayDetalleProductos'])) {
			$on = true;
			$arrayDetalleProductos = $_SESSION['arrayDetalleProductos'];
			for ($pr = 0; $pr < count($arrayDetalleProductos); $pr++) {
				if ($arrayDetalleProductos[$pr]['id'] == $row_producto_factura['id_cambio'] && $arrayDetalleProductos[$pr]['lote'] == $row_producto_factura['nuevo_lote']) {
					echo "<script>$.notify('Producto ya agregado.','error');</script>";
					$on = false;
				}
			}
			if ($on) {
				array_push($arrayDetalleProductos, $arrayDatos);
			}
			$_SESSION['arrayDetalleProductos'] = $arrayDetalleProductos;
		} else {
			array_push($arrayDetalleProductos, $arrayDatos);
			$_SESSION['arrayDetalleProductos'] = $arrayDetalleProductos;
		}
	}
	muestra_detalle_productos();
}

function muestra_detalle_productos()
{
	?>
	<div class="panel panel-info">
		<table class="table table-hover">
			<tr>
				<th class="danger" style="padding: 2px;">Código</th>
				<th class="danger" style="padding: 2px;">Producto Facturado</th>
				<th class="danger" style="padding: 2px;">Factura</th>
				<th class="danger" style="padding: 2px;">Lote</th>
				<th class="danger" style="padding: 2px;">Cantidad</th>
				<th class="danger" style="padding: 2px;">Bodega</th>
				<th class="success text-center" style="padding: 2px;">CV</th>
				<th class="success" style="padding: 2px;">Nuevo producto</th>
				<th class="success" style="padding: 2px;">Cantidad</th>
				<th class="info text-center" style="padding: 2px;">Quitar</th>
			</tr>
			<?php
			foreach ($_SESSION['arrayDetalleProductos'] as $detalle) {
				$id_registro = $detalle['id'];
			?>
				<tr>
					<input type="hidden" name="registros[]" value="<?php echo $id_registro; ?>">
					<input type="hidden" name="id_cv[<?php echo $id_registro; ?>]" id="id_cv<?php echo $id_registro; ?>"></td>
					<input type="hidden" name="cant_producto_cambio[<?php echo $id_registro; ?>]" id="cant_producto_cambio<?php echo $id_registro; ?>"></td>
					<td style="padding: 2px;"><?php echo $detalle['codigo']; ?></td>
					<td style="padding: 2px;"><?php echo strtoupper($detalle['producto']); ?></td>
					<td style="padding: 2px;"><?php echo $detalle['factura']; ?></td>
					<td style="padding: 2px;"><?php echo $detalle['lote']; ?></td>
					<td style="padding: 2px;" class="text-right"><?php echo number_format($detalle['cantidad'], 2, '.', ''); ?></td>
					<td style="padding: 2px;"><?php echo strtoupper($detalle['bodega']); ?></td>

					<td style="padding: 2px;" class='col-xs-1'>
						<input type="number" class="form-control input-sm text-left" name="numero_consignacion[<?php echo $id_registro; ?>]" id="numero_consignacion<?php echo $id_registro; ?>" title="Número de consignación">
					</td>
					<td style="padding: 2px;" class="col-sm-3"><input type="text" title="Nombre del nuevo producto" class="form-control input-sm" name="nombre_producto_cambio[<?php echo $id_registro; ?>]" id="nombre_producto_cambio<?php echo $id_registro; ?>" onkeyup="buscar_producto_cv('<?php echo $id_registro; ?>')"></td>
					<td style="padding: 2px;" class="col-sm-1"><input type="number" title="Cantidad del nuevo producto" class="form-control input-sm text-left" name="cant_cambio[<?php echo $id_registro; ?>]" id="cant_cambio<?php echo $id_registro; ?>" onchange="cantidad_cambio('<?php echo $id_registro; ?>');"></td>
					<td class='text-center' style="padding: 2px;">
						<a href="#" class='btn btn-danger btn-sm' onclick="eliminar_fila('<?php echo $id_registro; ?>')" title="Eliminar item"><i class="glyphicon glyphicon-remove"></i></a>
					</td>
				</tr>
			<?php
			}
			?>
		</table>
	</div>
	<script>
	</script>
<?php
}
?>