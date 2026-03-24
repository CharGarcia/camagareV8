<?php
/* Connect To Database*/
require_once("../conexiones/conectalogin.php");
require_once("../ajax/pagination.php"); //include pagination file
require_once("../helpers/helpers.php"); //include pagination file
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
include_once("../clases/saldo_producto_y_conversion.php");
$saldo_salidas = new saldo_producto_y_conversion();


if ($action == 'nuevo_codigo') {
	$sql = mysqli_query($con, "SELECT codigo_producto FROM productos_servicios WHERE ruc_empresa = '" . $ruc_empresa . "' ORDER BY id DESC LIMIT 1");
	$row_sql = mysqli_fetch_array($sql);

	if ($row_sql) {
		$codigo_producto = trim($row_sql["codigo_producto"]);
		$parteNoNumerica = preg_replace('/[0-9]/', '', $codigo_producto);
		$parte_numerica = filter_var($codigo_producto, FILTER_SANITIZE_NUMBER_INT);
		if (empty($parte_numerica)) {
			$parte_numerica = "0";
		}
		$nuevo_codigo_numerico = $parte_numerica + 1;
		$nuevo_codigo = $parteNoNumerica . str_pad($nuevo_codigo_numerico, strlen($parte_numerica), "0", STR_PAD_LEFT);
		echo $nuevo_codigo;
	} else {
		echo "PRD001";  // 🚀 primer código si la tabla está vacía
	}
}



if ($action == 'porcentajes_iva') {
	$codigo = $_POST['codigo'];
	$sql = mysqli_query($con, "SELECT porcentaje_iva as porcentaje_iva FROM tarifa_iva WHERE codigo ='" . $codigo . "' ");
	$tarifa = mysqli_fetch_array($sql);
	echo number_format($tarifa['porcentaje_iva'], 2, '.', '');
	/* if ($sql) {
		$arrResponse = array('status' => true, 'porcentaje' => $tarifa['porcentaje_iva'], 'tarifa' => $tarifa['tarifa']);
	} else {
		$arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
	}
	echo json_encode($arrResponse);
	die(); */
}

//para eliminar un producto
if ($action == 'eliminar_producto') {
	$id_producto = $_POST['id'];
	//para buscar medida del producto
	$sql_medida_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id = '" . $id_producto . "'");
	$row_medida_producto = mysqli_fetch_array($sql_medida_producto);
	$id_medida_salida = $row_medida_producto['id_unidad_medida'];

	$sql_producto_factura = mysqli_query($con, "SELECT count(*) AS numrows FROM cuerpo_factura WHERE id_producto = '" . $id_producto . "'");
	$row_producto_factura = mysqli_fetch_array($sql_producto_factura);
	$registros_facturas = $row_producto_factura['numrows'];

	$sql_producto_recibo = mysqli_query($con, "SELECT count(*) AS numrows FROM cuerpo_recibo WHERE id_producto = '" . $id_producto . "'");
	$row_producto_recibo = mysqli_fetch_array($sql_producto_recibo);
	$registros_recibos = $row_producto_recibo['numrows'];

	//para buscar saldo del producto
	$sql_saldo_entrada = mysqli_query($con, "SELECT sum(cantidad_entrada) as saldo FROM inventarios WHERE ruc_empresa ='" . $ruc_empresa . "'  and id_producto = '" . $id_producto . "'");
	$row_saldo_entrada = mysqli_fetch_array($sql_saldo_entrada);
	$total_entradas = $row_saldo_entrada['saldo'];

	//total salidas
	$total_cantidad_salidas = array();
	$total_salidas_inventario = mysqli_query($con, "SELECT * FROM inventarios WHERE id_producto = '" . $id_producto . "' and ruc_empresa='" . $ruc_empresa . "' and operacion='SALIDA'");
	while ($row_salidas = mysqli_fetch_array($total_salidas_inventario)) {
		$id_medida_entrada_inv = $row_salidas['id_medida'];
		$cantidad_salida = $row_salidas['cantidad_salida'];
		$total_cantidad_salidas[] = $saldo_salidas->conversion($id_medida_entrada_inv, $id_medida_salida, $id_producto, '0', $cantidad_salida, $con, 'saldo');
	}
	$total_salidas = array_sum($total_cantidad_salidas);
	$saldo_producto = number_format($total_entradas - $total_salidas, 4, '.', '');

	if ($saldo_producto > 0) {
		echo "<script>
					$.notify('Tiene movimientos en el inventario no se puede eliminar, debe dar de baja la existencia.','error');
					</script>";
	} else if ($registros_facturas  > 0) {
		echo "<script>
				$.notify('Tiene movimientos en facturas, no se puede eliminar, modifique su status a pasivo.','error');
				</script>";
	} else if ($registros_recibos > 0) {
		echo "<script>
						$.notify('Tiene movimientos en recibos de venta, no se puede eliminar, modifique su status a pasivo.','error');
						</script>";
	} else {

		$delete_productos = mysqli_query($con, "DELETE FROM productos_servicios WHERE id ='" . $id_producto . "'");
		$delete_marca_productos = mysqli_query($con, "DELETE FROM marca_producto WHERE id_producto ='" . $id_producto . "'");
		$delete_grupo_productos = mysqli_query($con, "DELETE FROM grupo_producto_asignado WHERE id_producto ='" . $id_producto . "'");
		$delete_detalle_por_facturar = mysqli_query($con, "DELETE FROM detalle_por_facturar WHERE id_producto ='" . $id_producto . "'");

		if ($delete_productos) {
			echo "<script>
				$.notify('Producto eliminado.','success');
				setTimeout(function () {location.reload()}, 1000); 
				</script>";
		} else {
			echo "<script>
					$.notify('Intentelo de nuevo, algo ha salido mal','error');
					</script>";
		}
	}
}

//para buscar un producto
if ($action == 'buscar_productos') {
	$condicion_ruc_empresa = compartirClientesProductos($con, $ruc_empresa)['productos'];
	// escaping, additionally removing everything that could be (html/javascript-) code
	$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$aColumns = array('codigo_producto', 'codigo_auxiliar', 'nombre_producto'); //Columnas de busqueda
	$sTable = "productos_servicios as pro_ser LEFT JOIN unidad_medida as uni_med ON uni_med.id_medida=pro_ser.id_unidad_medida ";

	$sWhere = "WHERE $condicion_ruc_empresa ";

	$text_buscar = explode(' ', $q);
	$like = "";
	for ($i = 0; $i < count($text_buscar); $i++) {
		$like .= "%" . $text_buscar[$i];
	}

	if ($_GET['q'] != "") {
		$sWhere = "WHERE ($condicion_ruc_empresa AND ";

		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $like . "%' AND $condicion_ruc_empresa  OR ";
		}

		$sWhere = substr_replace($sWhere, "AND $condicion_ruc_empresa ", -3);
		$sWhere .= ')';
	}

	$sWhere .= " order by $ordenado $por";
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
	$reload = '../productos.php';
	//main query to fetch the data
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_auxiliar");'>Cód_Barras</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_producto");'>Producto</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("tipo_produccion");'>Tipo</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("tarifa_iva");'>IVA</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("precio_producto");'>Pr. Uni.</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("precio_producto");'>P.V.P</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_unidad_medida");'>Medida</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Marca</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Grupo</button></th>
						<th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Status</button></th>
						<th class='text-right'>Opciones</th>
					</tr>
					<?php
					while ($row = mysqli_fetch_array($query)) {
						$id_producto = $row['id'];
						$codigo_producto = $row['codigo_producto'];
						$codigo_auxiliar = $row['codigo_auxiliar'];
						$nombre_producto = $row['nombre_producto'];
						$tipo_produccion = $row['tipo_produccion'];
						$tarifa_iva = $row['tarifa_iva'];
						$tarifa_ice = $row['tarifa_ice'];
						$tarifa_botellas = $row['tarifa_botellas'];
						$precio_producto = $row['precio_producto'];
						$id_unidad_medida = $row['id_unidad_medida'];
						$nombre_unidad_medida = $row['nombre_medida'];
						$id_tipo_medida = $row['id_tipo_medida'];
						$status = $row['status'];

						//para buscar la marca
						$sql_marca = mysqli_query($con, "SELECT mar_pro.id_marca as id_marca, mar.nombre_marca as nombre_marca FROM marca as mar INNER JOIN marca_producto as mar_pro ON mar.id_marca=mar_pro.id_marca WHERE mar_pro.id_producto = '" . $id_producto . "' and mar.ruc_empresa='" . $ruc_empresa . "'");
						$row_marca = mysqli_fetch_array($sql_marca);
						$id_marca = isset($row_marca['id_marca']) ? $row_marca['id_marca'] : "";
						$nombre_marca = isset($row_marca['nombre_marca']) ? $row_marca['nombre_marca'] : "";

						//para buscar el grupo
						$sql_grupo = mysqli_query($con, "SELECT grupo_asi.id_grupo as id_grupo, grupo.nombre_grupo as nombre_grupo FROM grupo_familiar_producto as grupo INNER JOIN grupo_producto_asignado as grupo_asi ON grupo_asi.id_grupo=grupo.id_grupo WHERE grupo_asi.id_producto = '" . $id_producto . "' and grupo.ruc_empresa='" . $ruc_empresa . "'");
						$row_grupo = mysqli_fetch_array($sql_grupo);
						$id_grupo = isset($row_grupo['id_grupo']) ? $row_grupo['id_grupo'] : "";
						$nombre_grupo = isset($row_grupo['nombre_grupo']) ? $row_grupo['nombre_grupo'] : "";

					?>
						<input type="hidden" value="<?php echo $codigo_producto; ?>" id="codigo_producto_mod<?php echo $id_producto; ?>">
						<input type="hidden" value="<?php echo $codigo_auxiliar; ?>" id="codigo_auxiliar_mod<?php echo $id_producto; ?>">
						<input type="hidden" value="<?php echo $nombre_producto; ?>" id="nombre_producto_mod<?php echo $id_producto; ?>">
						<input type="hidden" value="<?php echo $tipo_produccion; ?>" id="tipo_produccion_mod<?php echo $id_producto; ?>">
						<input type="hidden" value="<?php echo $tarifa_iva; ?>" id="tarifa_iva_mod<?php echo $id_producto; ?>">
						<input type="hidden" value="<?php echo $id_unidad_medida; ?>" id="id_unidad_medida_mod<?php echo $id_producto; ?>">
						<input type="hidden" value="<?php echo $id_tipo_medida; ?>" id="tipo_medida_mod<?php echo $id_producto; ?>">
						<input type="hidden" value="<?php echo number_format($precio_producto, 4, '.', ''); ?>" id="precio_producto_mod<?php echo $id_producto; ?>">
						<input type="hidden" value="<?php echo $id_marca; ?>" id="marca_mod<?php echo $id_producto; ?>">
						<input type="hidden" value="<?php echo $id_grupo; ?>" id="grupo_mod<?php echo $id_producto; ?>">
						<input type="hidden" value="<?php echo $status; ?>" id="status_mod<?php echo $id_producto; ?>">
						<tr>

							<td><?php echo $codigo_producto; ?></td>
							<td><?php echo $codigo_auxiliar; ?></td>
							<td class='col-xs-3'><?php echo $nombre_producto; ?></td>

							<?php
							$sql = "SELECT * FROM tipo_produccion where codigo = '" . $tipo_produccion . "' ";
							$queri_tipo = mysqli_query($con, $sql);
							$fila_tipo = mysqli_fetch_array($queri_tipo);
							$tipo_nombre = $fila_tipo['nombre'];
							?>
							<td><?php echo $tipo_nombre; ?></td>
							<?php
							$sql = "SELECT * FROM tarifa_iva where codigo = '" . $tarifa_iva . "' ";
							$queri_iva = mysqli_query($con, $sql);
							$fila_iva = mysqli_fetch_array($queri_iva);
							$iva_nombre = $fila_iva['tarifa'];
							$porcentaje_iva = 1 + ($fila_iva['porcentaje_iva'] / 100);
							?>
							<td><?php echo $iva_nombre; ?></td>
							<td><span class='pull-right'>$ <?php echo number_format($precio_producto, 4, '.', ''); ?></span></td>
							<td><span class='pull-right'>$ <?php echo number_format($precio_producto * $porcentaje_iva, 2); ?></span></td>
							<td><?php echo $nombre_unidad_medida; ?></td>
							<td><?php echo $nombre_marca; ?></td>
							<td><?php echo $nombre_grupo; ?></td>
							<td><?php echo $status == 1 ? "<span class='label label-success'>Activo</span>" : "<span class='label label-danger'>Inactivo</span>"; ?></td>
							<td><span class="pull-right">
									<?php
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'productos')['u'] == 1) {
									?>
										<a href="#" class='btn btn-success btn-xs' title='Agregar varios precios' onclick="detalle_precios('<?php echo $id_producto; ?>');" data-toggle="modal" data-target="#detallePreciosProductos"><i class="glyphicon glyphicon-usd"></i></a>
										<a href="#" class='btn btn-info btn-xs' title='Editar producto o servicio' onclick="editar_producto('<?php echo $id_producto; ?>');" data-toggle="modal" data-target="#productos"><i class="glyphicon glyphicon-edit"></i></a>
									<?php
									}
									if (getPermisos($con, $id_usuario, $ruc_empresa, 'productos')['d'] == 1) {
									?>
										<a href="#" class='btn btn-danger btn-xs' title='Borrar producto o servicio' onclick="eliminar_producto('<?php echo $id_producto; ?>')"><i class="glyphicon glyphicon-trash"></i> </a></span></td>
						<?php
									}
						?>
						</tr>
					<?php
					}
					?>
					<tr>
						<td colspan="12"><span class="pull-right">
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

//guardar o editar productos o servicios
if ($action == 'guardar_producto') {
	$id_producto = intval($_POST['id_producto']);
	$codigo = strClean($_POST['codigo_producto']);
	$auxiliar = strClean($_POST['codigo_auxiliar']);
	$nombre = strClean($_POST['nombre_producto']);
	$iva = $_POST['iva_producto'];
	$precio = $_POST['precio_producto'];
	$tipo = $_POST['tipo_producto'];
	$marca = $_POST['marca_producto'];
	$grupo = $_POST['grupo_producto'];
	$tipo_medida = $_POST['tipo_medida_producto'];
	$unidad_medida = $_POST['unidad_medida_producto'];
	$status = $_POST['status_producto'];

	if (empty($codigo)) {
		echo "<script>
		$.notify('Ingrese código','error');
		</script>";
	} else if (empty($nombre)) {
		echo "<script>
		$.notify('Ingrese nombre del producto o servicio','error');
		</script>";
	} else if ($iva == "") {
		echo "<script>
		$.notify('Seleccione una tarifa de IVA','error');
		</script>";
	} else if (empty($precio)) {
		echo "<script>
		$.notify('Ingrese precio','error');
		</script>";
	} else if (!is_numeric($precio)) {
		echo "<script>
		$.notify('Ingrese precio correcto','error');
		</script>";
	} else if ($tipo == "") {
		echo "<script>
		$.notify('Seleccione tipo, producto o servicio','error');
		</script>";
	} else if ($tipo == "01" && $tipo_medida == "") {
		echo "<script>
		$.notify('Seleccione un tipo de medida','error');
		</script>";
	} else if ($tipo == "01" && $tipo_medida != "" && $unidad_medida == "") {
		echo "<script>
		$.notify('Seleccione una unidad de medida','error');
		</script>";
	} else if (empty($ruc_empresa)) {
		echo "<script>
		$.notify('La sesión ha expirado, reingrese al sistema.','error');
		</script>";
	} else if (empty($id_usuario)) {
		echo "<script>
		$.notify('La sesión ha expirado, reingrese al sistema.','error');
		</script>";
	} else {

		if ($tipo == "01") {
			$nombre_tipo = "Producto";
		} else {
			$nombre_tipo = "Servicio";
		}

		if (empty($id_producto)) {
			$busca_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE ruc_empresa = '" . $ruc_empresa . "' and codigo_producto='" . $codigo . "' ");
			$count = mysqli_num_rows($busca_producto);

			if (!empty($auxiliar)) {
				$busca_auxiliar = mysqli_query($con, "SELECT * FROM productos_servicios WHERE ruc_empresa = '" . $ruc_empresa . "' and codigo_auxiliar='" . $auxiliar . "' ");
				$count_auxiliar = mysqli_num_rows($busca_auxiliar);
			} else {
				$count_auxiliar = 0;
			}

			if ($count + $count_auxiliar > 0) {
				echo "<script>
                $.notify('El '+'" . $nombre_tipo . "'+' con ese código principal o código auxiliar ya esta registrado','error');
                </script>";
			} else {
				$guarda_producto = mysqli_query($con, "INSERT INTO productos_servicios (ruc_empresa,
                                                                        codigo_producto,
                                                                        nombre_producto,
																		codigo_auxiliar,
                                                                        precio_producto,
                                                                        tipo_produccion,
                                                                        tarifa_iva,
                                                                        fecha_agregado,
                                                                        id_unidad_medida ,
                                                                        id_usuario)
                                                                            VALUES ('" . $ruc_empresa . "',
                                                                                    '" . $codigo . "',
                                                                                    '" . $nombre . "',
																					'" . $auxiliar . "',
                                                                                    '" . $precio . "',
                                                                                    '" . $tipo . "',
                                                                                    '" . $iva . "',
                                                                                    '" . date("Y-m-d H:i:s") . "',
                                                                                    '" . $unidad_medida . "',
                                                                                    '" . $id_usuario . "')");
				if (!empty($marca)) {
					$lastid = mysqli_insert_id($con);
					$guarda_marca = mysqli_query($con, "INSERT INTO marca_producto (id_producto,
																			id_marca,
																			ruc_empresa,
																			fecha_agregado,
																			id_usuario)
																			VALUES ('" . $lastid . "',
																					'" . $marca . "',
																					'" . $ruc_empresa . "',
																					'" . date("Y-m-d H:i:s") . "',
																					'" . $id_usuario . "')");
				}

				if (!empty($grupo)) {
					$guarda_grupo = mysqli_query($con, "INSERT INTO grupo_producto_asignado (id_producto,
																			id_grupo,
																			ruc_empresa,
																			fecha_agregado,
																			id_usuario)
																			VALUES ('" . $lastid . "',
																					'" . $grupo . "',
																					'" . $ruc_empresa . "',
																					'" . date("Y-m-d H:i:s") . "',
																					'" . $id_usuario . "')");
				}

				if ($guarda_producto) {
					echo "<script>
                $.notify('" . $nombre_tipo . "'+' registrado','success');
				document.querySelector('#guardar_producto').reset();
				load(1);
                </script>";
				} else {
					echo "<script>
                $.notify('Revisar que el código o el nombre no contengan caracteres especiales','error');
                </script>";
				}
			}
		} else {
			//modificar el producto or (id != '".$id_producto."' and ruc_empresa = '".$ruc_empresa."' and nombre_producto='".$nombre."' )
			$busca_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id != '" . $id_producto . "' and codigo_producto='" . $codigo . "' and ruc_empresa = '" . $ruc_empresa . "' ");
			$count = mysqli_num_rows($busca_producto);

			if (!empty($auxiliar)) {
				$busca_auxiliar = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id != '" . $id_producto . "' and codigo_auxiliar='" . $auxiliar . "' and ruc_empresa = '" . $ruc_empresa . "' ");
				$count_auxiliar = mysqli_num_rows($busca_auxiliar);
			} else {
				$count_auxiliar = 0;
			}

			if ($count + $count_auxiliar > 0) {
				echo "<script>
                $.notify('El '+'" . $nombre_tipo . "'+' con ese código principal o código auxiliar ya esta registrado','error');
                </script>";
			} else {
				$update_producto = mysqli_query($con, "UPDATE productos_servicios SET nombre_producto='" . $nombre . "',
											codigo_auxiliar='" . $auxiliar . "',
											precio_producto='" . $precio . "',
											tipo_produccion='" . $tipo . "',
											tarifa_iva='" . $iva . "',
											fecha_agregado='" . date("Y-m-d H:i:s") . "',
											id_unidad_medida ='" . $unidad_medida . "',
											status='" . $status . "',
											id_usuario='" . $id_usuario . "' WHERE id='" . $id_producto . "' ");

				//para actualizar marca
				if (!empty($marca)) {
					$delete_registro = mysqli_query($con, "DELETE FROM marca_producto WHERE id_producto = '" . $id_producto . "' ");
					//if(mysqli_num_rows($busca_registro)>0){
					//$actualiza_marca = mysqli_query($con, "UPDATE marca_producto SET id_marca ='" . $marca . "', fecha_agregado='".date("Y-m-d H:i:s")."', id_usuario='" . $id_usuario . "' WHERE id_producto ='".$id_producto."' ");
					$guarda_marca = mysqli_query($con, "INSERT INTO marca_producto (id_producto,
						id_marca,
						ruc_empresa,
						fecha_agregado,
						id_usuario)
						VALUES ('" . $id_producto . "',
								'" . $marca . "',
								'" . $ruc_empresa . "',
								'" . date("Y-m-d H:i:s") . "',
								'" . $id_usuario . "')");
				}

				//para actualizar grupo
				if (!empty($grupo)) {
					$delete_registro = mysqli_query($con, "DELETE FROM grupo_producto_asignado WHERE id_producto = '" . $id_producto . "' ");
					$guarda_grupo = mysqli_query($con, "INSERT INTO grupo_producto_asignado (id_producto,
						id_grupo,
						ruc_empresa,
						fecha_agregado,
						id_usuario)
						VALUES ('" . $id_producto . "',
								'" . $grupo . "',
								'" . $ruc_empresa . "',
								'" . date("Y-m-d H:i:s") . "',
								'" . $id_usuario . "')");
				}
				//para nombre en inventarios
				if ($tipo == "01") {
					$actualiza_inventario = mysqli_query($con, "UPDATE inventarios SET nombre_producto='" . $nombre . "' WHERE id_producto ='" . $id_producto . "'");
				}

				if ($update_producto) {
					echo "<script>
                    $.notify('" . $nombre_tipo . "'+' actualizado','success');
					setTimeout(function () {location.reload()}, 1000);
                        </script>";
				} else {
					echo "<script>
						$.notify('Revisar que el código o el nombre no contengan caracteres especiales','error');
                        </script>";
				}
			}
		}
	}
}

if ($action == 'tipo_medida') {
	//para buscar medida y cargar combos de nuevo producto

	$tipo_medida = mysqli_real_escape_string($con, (strip_tags($_POST["tipo_medida"], ENT_QUOTES)));
	$id_unidad_medida = mysqli_real_escape_string($con, (strip_tags($_POST["id_unidad_medida"], ENT_QUOTES)));

	$busca_unidad_medida = "SELECT * FROM unidad_medida where id_tipo_medida='" . $tipo_medida . "' ";
	$resultado_unidad_medida = $con->query($busca_unidad_medida);

	?>
	<option value="">Seleccione</option>
	<?php
	while ($row_unidad = mysqli_fetch_array($resultado_unidad_medida)) {
		if ($row_unidad['id_medida'] == $id_unidad_medida) {
	?>
			<option value="<?php echo $id_unidad_medida; ?>" selected><?php echo $row_unidad['nombre_medida']; ?></option>
		<?php
		} else {
		?>
			<option value="<?php echo $row_unidad['id_medida']; ?>"><?php echo $row_unidad['nombre_medida']; ?></option>
<?php
		}
	}
}


if ($action == 'verificar_producto_existente') {
	$codigo = strClean($_POST['codigo_producto']);
	$auxiliar = strClean($_POST['codigo_auxiliar']);


	if (!empty($codigo)) {
		$busca_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE ruc_empresa = '" . $ruc_empresa . "' and codigo_producto='" . $codigo . "' ");
		$count = mysqli_num_rows($busca_producto);
		if ($count > 0) {
			echo "<script>
				$.notify('Código ya registrado','error');
				</script>";
		}
	}
	if (!empty($auxiliar)) {
		$busca_auxiliar = mysqli_query($con, "SELECT * FROM productos_servicios WHERE ruc_empresa = '" . $ruc_empresa . "' and codigo_auxiliar='" . $auxiliar . "' ");
		$count_auxiliar = mysqli_num_rows($busca_auxiliar);
		if ($count_auxiliar > 0) {
			echo "<script>
				$.notify('Código auxiliar ya registrado','error');
				</script>";
		}
	}
}
?>