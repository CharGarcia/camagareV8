<?php
/* Connect To Database*/
include("../clases/contabilizacion.php");
$contabilizacion = new contabilizacion();

include("../conexiones/conectalogin.php");
$con = conenta_login();

/* ====================== HELPERS (compatibles PHP 5.6) ====================== */
function validar_fecha_post($valor)
{
	if (!isset($valor) || $valor === '') return false;
	$ts = @strtotime($valor);
	return $ts !== false;
}
function to_float($s)
{
	$s = str_replace(',', '.', (string)$s);
	$s = trim($s);
	return (float)$s;
}

/* ====================== VALIDACIONES BÁSICAS ====================== */
$errors = array();
$messages = array();

if (empty($_POST['fecha_nc_e'])) {
	$errors[] = "Ingrese fecha para la nota de crédito electrónica.";
} else if (!validar_fecha_post($_POST['fecha_nc_e'])) {
	$errors[] = "Ingrese fecha de nota de crédito correcta.";
} else if (empty($_POST['fecha_factura'])) {
	$errors[] = "Ingrese fecha de emisión de la factura.";
} else if (!validar_fecha_post($_POST['fecha_factura'])) {
	$errors[] = "Ingrese fecha de emisión de la factura correcta.";
} else if (empty($_POST['serie_nc_e'])) {
	$errors[] = "Seleccione serie para la nota de crédito electrónica.";
} else if (empty($_POST['numero_factura'])) {
	$errors[] = "Ingrese factura a modificar por la nota de crédito electrónica.";
} else if (empty($_POST['secuencial_nc_e'])) {
	$errors[] = "Ingrese un número de nota de crédito electrónica.";
} else if (!is_numeric($_POST['secuencial_nc_e'])) {
	$errors[] = "El secuencial de la nota de crédito debe ser numérico.";
} else if (empty($_POST['id_cliente'])) {
	$errors[] = "Ingrese cliente.";
} else if (empty($_POST['motivo'])) {
	$errors[] = "Ingrese el motivo por el cual registra la nota de crédito.";
}

/* ====================== PROCESO ====================== */
if (empty($errors)) {
	session_start();
	if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['ruc_empresa'])) {
		$errors[] = "Sesión expirada. Ingrese nuevamente.";
	} else {
		$id_usuario  = $_SESSION['id_usuario'];
		$ruc_empresa = $_SESSION['ruc_empresa'];

		$fecha_nc        = date('Y-m-d H:i:s', strtotime($_POST['fecha_nc_e']));
		$fecha_factura   = date('Y-m-d H:i:s', strtotime($_POST['fecha_factura']));
		$serie_nc        = mysqli_real_escape_string($con, strip_tags($_POST["serie_nc_e"], ENT_QUOTES));
		$secuencial_nc   = mysqli_real_escape_string($con, strip_tags($_POST["secuencial_nc_e"], ENT_QUOTES));
		$numero_factura  = mysqli_real_escape_string($con, strip_tags($_POST["numero_factura"], ENT_QUOTES));
		$motivo          = mysqli_real_escape_string($con, strip_tags($_POST["motivo"], ENT_QUOTES));
		$id_cliente      = mysqli_real_escape_string($con, strip_tags($_POST["id_cliente"], ENT_QUOTES));
		$total_nc_raw    = isset($_POST["total_nc_e"]) ? $_POST["total_nc_e"] : "0";
		$total_nc        = mysqli_real_escape_string($con, strip_tags($total_nc_raw, ENT_QUOTES));
		$fecha_registro  = date("Y-m-d H:i:s");

		$serie_factura       = substr($numero_factura, 0, 7);
		$secuencial_factura  = substr($numero_factura, 8, 9);

		$tipo_nc    = "ELECTRÓNICA";
		$estado_sri = "PENDIENTE";

		// Validar formato de factura 001-001-000000001
		if (!empty($numero_factura)) {
			if ((strlen($numero_factura) != 17)
				|| (!is_numeric(substr($numero_factura, 0, 3)))
				|| (!is_numeric(substr($numero_factura, 4, 3)))
				|| (!is_numeric(substr($numero_factura, 8, 9)))
				|| (substr($numero_factura, 3, 1) != "-")
				|| (substr($numero_factura, 7, 1) != "-")
			) {
				$errors[] = 'Ingrese un número de factura correcto. ej: 001-001-000000001 ' . mysqli_error($con);
			}
		}

		// Datos cliente (para info adicional)
		if (empty($errors)) {
			$sql_cliente   = mysqli_query($con, "SELECT email, direccion FROM clientes WHERE id = '" . $id_cliente . "' LIMIT 1");
			$row_cliente   = $sql_cliente ? mysqli_fetch_array($sql_cliente) : null;
			$mail_cliente        = $row_cliente ? $row_cliente['email'] : '';
			$direccion_cliente   = $row_cliente ? $row_cliente['direccion'] : '';
		}

		if (empty($errors)) {
			// Duplicado NC
			$busca_empresa = "SELECT 1 FROM encabezado_nc
                              WHERE ruc_empresa = '" . $ruc_empresa . "'
                                AND serie_nc = '" . $serie_nc . "'
                                AND secuencial_nc ='" . $secuencial_nc . "'
                                AND tipo_nc = 'ELECTRÓNICA' LIMIT 1";
			$result = mysqli_query($con, $busca_empresa);
			$count  = $result ? mysqli_num_rows($result) : 0;

			if ($count > 0) {
				$errors[] = "El número de nota de crédito que intenta guardar ya se encuentra registrado en el sistema." . mysqli_error($con);
			} else {
				// Debe existir detalle temporal
				$sql_nc_temporal = mysqli_query($con, "SELECT * FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' AND ruc_empresa = '" . $ruc_empresa . "'");
				$count_tmp = $sql_nc_temporal ? mysqli_num_rows($sql_nc_temporal) : 0;

				if ($count_tmp == 0) {
					$errors[] = "No hay detalle de productos agregados a la nota de crédito." . mysqli_error($con);
				} else {
					// Vendedor (si existe)
					$id_vendedor = "0";
					$busca_vend = mysqli_query($con, "SELECT ven_ven.id_vendedor as id_vendedor
                        FROM vendedores_ventas ven_ven
                        INNER JOIN encabezado_factura fact ON fact.id_encabezado_factura = ven_ven.id_venta
                        WHERE fact.ruc_empresa = '" . $ruc_empresa . "'
                          AND fact.serie_factura = '" . $serie_factura . "'
                          AND fact.secuencial_factura = '" . $secuencial_factura . "' LIMIT 1");
					if ($busca_vend && mysqli_num_rows($busca_vend) > 0) {
						$row_v = mysqli_fetch_array($busca_vend);
						if (isset($row_v['id_vendedor'])) $id_vendedor = $row_v['id_vendedor'];
					}

					/* ========== TRANSACCIÓN SOLO PARA NC (TODO O NADA) ========== */
					mysqli_autocommit($con, false);
					$todo_ok = true;
					$id_ncv  = null;

					// 1) Encabezado NC
					if ($todo_ok) {
						$sql_enc = "INSERT INTO encabezado_nc VALUES (
                            null,
                            '" . $ruc_empresa . "',
                            '" . $fecha_nc . "',
                            '" . $serie_nc . "',
                            '" . $secuencial_nc . "',
                            '" . $numero_factura . "',
                            '" . $id_cliente . "',
                            '" . $fecha_registro . "',
                            '" . $tipo_nc . "',
                            '" . $estado_sri . "',
                            '" . $total_nc . "',
                            '" . $id_usuario . "',
                            '0','0','',
                            '" . $motivo . "',
                            'PENDIENTE',
                            '" . $fecha_factura . "'
                        )";
						$ok_enc = mysqli_query($con, $sql_enc);
						if (!$ok_enc) {
							$errors[] = "Error guardando encabezado NC: " . mysqli_error($con);
							$todo_ok = false;
						} else {
							$id_ncv = mysqli_insert_id($con);
						}
					}

					// 2) Relación vendedor
					if ($todo_ok) {
						$sql_vend = "INSERT INTO vendedores_ncv VALUES (null, '" . $id_vendedor . "', '" . $id_ncv . "', '" . $fecha_registro . "', '" . $id_usuario . "')";
						$ok_vend = mysqli_query($con, $sql_vend);
						if (!$ok_vend) {
							$errors[] = "Error guardando vendedor de NC: " . mysqli_error($con);
							$todo_ok = false;
						}
					}

					// 3) Detalle adicional (Email, Dirección)
					if ($todo_ok) {
						$ok_ad1 = mysqli_query($con, "INSERT INTO detalle_adicional_nc VALUES (null, '" . $ruc_empresa . "', '" . $serie_nc . "', '" . $secuencial_nc . "', 'Email', '" . mysqli_real_escape_string($con, $mail_cliente) . "')");
						if (!$ok_ad1) {
							$errors[] = "Error guardando información adicional (Email): " . mysqli_error($con);
							$todo_ok = false;
						}
					}
					if ($todo_ok) {
						$ok_ad2 = mysqli_query($con, "INSERT INTO detalle_adicional_nc VALUES (null, '" . $ruc_empresa . "', '" . $serie_nc . "', '" . $secuencial_nc . "', 'Dirección', '" . mysqli_real_escape_string($con, $direccion_cliente) . "')");
						if (!$ok_ad2) {
							$errors[] = "Error guardando información adicional (Dirección): " . mysqli_error($con);
							$todo_ok = false;
						}
					}

					// 4) Cuerpo NC
					if ($todo_ok) {
						while ($row_detalle = mysqli_fetch_array($sql_nc_temporal)) {
							$cantidad_nc   = to_float($row_detalle["cantidad_tmp"]);
							$precio_venta  = to_float($row_detalle['precio_tmp']);
							$subtotal_nc   = number_format(($precio_venta * $cantidad_nc), 2, '.', '');

							$tipo_prod     = $row_detalle['tipo_produccion'];
							$tarifa_iva    = $row_detalle['tarifa_iva'];
							$tarifa_ice    = $row_detalle['tarifa_ice'];
							$tarifa_bp     = $row_detalle['tarifa_botellas'];
							$descuento     = $row_detalle['descuento'];
							$id_producto   = $row_detalle['id_producto'];

							// Producto
							$res_prod = mysqli_query($con, "SELECT codigo_producto, nombre_producto FROM productos_servicios WHERE id = " . $id_producto . " LIMIT 1");
							$datos    = $res_prod ? mysqli_fetch_array($res_prod) : null;
							$codigo_producto = strtoupper(isset($datos['codigo_producto']) ? $datos['codigo_producto'] : '');
							$nombre_producto = strtoupper(isset($datos['nombre_producto']) ? $datos['nombre_producto'] : '');

							$sql_det = "INSERT INTO cuerpo_nc VALUES (
                                null,
                                '" . $ruc_empresa . "',
                                '" . $serie_nc . "',
                                '" . $secuencial_nc . "',
                                '" . $id_producto . "',
                                '" . $cantidad_nc . "',
                                '" . $precio_venta . "',
                                '" . $subtotal_nc . "',
                                '" . $tipo_prod . "',
                                '" . $tarifa_iva . "',
                                '" . $tarifa_ice . "',
                                '" . $tarifa_bp . "',
                                '" . $descuento . "',
                                '" . $codigo_producto . "',
                                '" . $nombre_producto . "'
                            )";
							$ok_det = mysqli_query($con, $sql_det);
							if (!$ok_det) {
								$errors[] = "Error guardando detalle de NC: " . mysqli_error($con);
								$todo_ok = false;
								break;
							}
						}
					}

					// Commit o rollback SOLO de la NC
					$nc_guardada = false;
					if ($todo_ok) {
						mysqli_commit($con);
						$nc_guardada = true;
					} else {
						mysqli_rollback($con);
					}
					mysqli_autocommit($con, true);
					/* ========== FIN TRANSACCIÓN SOLO NC ========== */

					// ==== ASIENTOS CONTABLES POR FUERA (NO AFECTAN LA NC) ====
					if ($nc_guardada) {
						$ok_docs = $contabilizacion->documentosNcVentas($con, $ruc_empresa, $fecha_nc, $fecha_nc);
						$ok_save = false;
						if ($ok_docs !== false) {
							$ok_save = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'nc_ventas');
						}

						// Mensajes al usuario
						if ($ok_docs === false || $ok_save === false) {
							// NC guardada, asientos fallaron → aviso
							echo "<script>
                                    $.notify('Nota de crédito guardada. Los asientos contables no se generaron: revise Contabilización.', 'warn');
                                    setTimeout(function (){location.href ='../modulos/notas_de_credito.php'}, 1200);
                                  </script>";
							exit;
						} else {
							echo "<script>
                                    $.notify('Nota de crédito guardada y asientos contables generados.', 'success');
                                    setTimeout(function (){location.href ='../modulos/notas_de_credito.php'}, 1000);
                                  </script>";
							exit;
						}
					}
					// Si no se guardó la NC, ya tenemos errores acumulados y se mostrarán abajo.
				}
			}
		}
	}
} else {
	$errors[] = "Error desconocido.";
}

/* ====================== MENSAJES ====================== */
if (!empty($errors)) {
?>
	<div class="alert alert-danger" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>Atención! </strong>
		<?php foreach ($errors as $error) {
			echo "<br/>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
		} ?>
	</div>
<?php
}
if (!empty($messages)) {
?>
	<div class="alert alert-success" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>¡Bien hecho! </strong>
		<?php foreach ($messages as $message) {
			echo $message;
		} ?>
	</div>
<?php
}
?>