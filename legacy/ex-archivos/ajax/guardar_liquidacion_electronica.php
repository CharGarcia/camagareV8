<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
include("../validadores/generador_codigo_unico.php");
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
function mb_len($s)
{
	if (function_exists('mb_strlen')) return mb_strlen($s, 'UTF-8');
	return strlen($s);
}
function normalizar_texto($s)
{
	$s = (string)$s;
	// NBSP, tabs y \r -> espacio
	$s = str_replace(array("\xC2\xA0", "\t", "\r"), " ", $s);
	// Colapsar múltiples espacios/saltos
	$s = preg_replace('/\s+/u', ' ', $s);
	return trim($s);
}
function validar_par_adicional(&$concepto, &$detalle, &$errorOut)
{
	$concepto = normalizar_texto($concepto);
	$detalle  = normalizar_texto($detalle);

	if (mb_len($concepto) === 0) {
		$errorOut = "Información adicional: el nombre (concepto) no puede estar vacío.";
		return false;
	}
	if (mb_len($concepto) > 30) {
		$errorOut = "Información adicional: el nombre (\"" . $concepto . "\") excede 30 caracteres.";
		return false;
	}
	if (mb_len($detalle) === 0) {
		$errorOut = "Información adicional: el detalle no puede estar vacío para \"" . $concepto . "\".";
		return false;
	}
	if (mb_len($detalle) > 300) {
		$errorOut = "Información adicional: el detalle para \"" . $concepto . "\" excede 300 caracteres.";
		return false;
	}
	return true;
}

/* ====================== VALIDACIONES BÁSICAS DE POST ====================== */
$errors = array();
$messages = array();

if (empty($_POST['fecha_liquidacion'])) {
	$errors[] = "Ingrese fecha para la liquidación electrónica.";
} else if (!validar_fecha_post($_POST['fecha_liquidacion'])) {
	$errors[] = "Ingrese fecha correcta.";
} else if (empty($_POST['serie_liquidacion'])) {
	$errors[] = "Seleccione serie para la liquidación electrónica.";
} else if (empty($_POST['secuencial_liquidacion'])) {
	$errors[] = "Ingrese un número de liquidación electrónica.";
} else if (!is_numeric($_POST['secuencial_liquidacion'])) {
	$errors[] = "El secuencial de la liquidación debe ser numérico.";
} else if (empty($_POST['id_proveedor_lc'])) {
	$errors[] = "Seleccione un proveedor para la liquidación electrónica.";
} else if (empty($_POST['forma_pago_lc'])) {
	$errors[] = "Seleccione una forma de pago.";
}

/* ====================== PROCESO PRINCIPAL ====================== */
if (empty($errors)) {
	ini_set('date.timezone', 'America/Guayaquil');

	session_start();
	if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['ruc_empresa'])) {
		$errors[] = "Sesión expirada. Ingrese nuevamente.";
	} else {
		$id_usuario  = $_SESSION['id_usuario'];
		$ruc_empresa = $_SESSION['ruc_empresa'];

		$fecha_liquidacion     = date('Y-m-d H:i:s', strtotime($_POST['fecha_liquidacion']));
		$serie_liquidacion     = mysqli_real_escape_string($con, strip_tags($_POST["serie_liquidacion"], ENT_QUOTES));
		$secuencial_liquidacion = mysqli_real_escape_string($con, strip_tags($_POST["secuencial_liquidacion"], ENT_QUOTES));
		$id_proveedor_lc       = mysqli_real_escape_string($con, strip_tags($_POST["id_proveedor_lc"], ENT_QUOTES));
		$forma_pago_lc         = mysqli_real_escape_string($con, strip_tags($_POST["forma_pago_lc"], ENT_QUOTES));
		$total_lc_raw          = isset($_POST["total_lc"]) ? $_POST["total_lc"] : "0";
		$total_lc              = to_float($total_lc_raw); // para comparar numéricamente
		$total_lc_sql          = mysqli_real_escape_string($con, strip_tags($_POST["total_lc"], ENT_QUOTES));

		$fecha_registro = date('Y-m-d H:i:s');
		$hoy_yyyy_mm_dd = date('Y-m-d');

		// ¿Liquidación duplicada?
		$busca_liq = mysqli_query($con, "SELECT 1 FROM encabezado_liquidacion 
            WHERE ruc_empresa = '" . $ruc_empresa . "' 
              AND serie_liquidacion = '" . $serie_liquidacion . "' 
              AND secuencial_liquidacion = '" . $secuencial_liquidacion . "' 
            LIMIT 1");
		$existe = $busca_liq ? mysqli_num_rows($busca_liq) : 0;

		if ($existe > 0) {
			$errors[] = "El número de liquidación que intenta guardar ya se encuentra registrado en el sistema." . mysqli_error($con);
		} else {
			// Debe existir detalle temporal
			$sql_lc_temporal = mysqli_query($con, "SELECT * FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' AND ruc_empresa = '" . $ruc_empresa . "'");
			$count_tmp = $sql_lc_temporal ? mysqli_num_rows($sql_lc_temporal) : 0;

			if ($count_tmp == 0) {
				$errors[] = "No hay detalle de productos o servicios agregados a la liquidación." . mysqli_error($con);
			} else {
				// Recalcular total (subtotal - descuento) + IVA
				$sql_subtotal = mysqli_query($con, "SELECT ROUND(SUM(subtotal - descuento), 2) AS subtotal 
                    FROM factura_tmp 
                    WHERE id_usuario = '" . $id_usuario . "' AND ruc_empresa = '" . $ruc_empresa . "' 
                    GROUP BY id_usuario");
				$row_subtotal = $sql_subtotal ? mysqli_fetch_array($sql_subtotal) : null;
				$subtotal_calc = $row_subtotal ? to_float($row_subtotal['subtotal']) : 0.0;

				$sql_iva = mysqli_query($con, "SELECT ROUND(SUM(ft.subtotal - ft.descuento), 2) AS suma_tarifa_iva, 
                            (ti.porcentaje_iva/100) AS porcentaje 
                        FROM factura_tmp ft 
                        INNER JOIN tarifa_iva ti ON ti.codigo = ft.tarifa_iva 
                        WHERE ft.id_usuario = '" . $id_usuario . "' 
                          AND ft.ruc_empresa = '" . $ruc_empresa . "' 
                          AND ti.porcentaje_iva > 0 
                        GROUP BY ft.tarifa_iva");
				$total_iva_calc = 0.0;
				if ($sql_iva) {
					while ($row_iva = mysqli_fetch_array($sql_iva)) {
						$sum_base = to_float($row_iva['suma_tarifa_iva']);
						$porc     = to_float($row_iva['porcentaje']);
						$total_iva_calc += $sum_base * $porc;
					}
				}
				$total_liq_calc = round($subtotal_calc + $total_iva_calc, 2);

				// Comparación con tolerancia (0.01)
				if (abs($total_liq_calc - $total_lc) > 0.01) {
					$errors[] = "El total de la liquidación ($total_lc) no coincide con el calculado ($total_liq_calc). Agregue o elimine un ítem para recalcular.";
				} else {
					/* ===================== VALIDACIÓN PREVIA infoAdicional ===================== */
					$campos_adicionales = array();
					$errores_validacion = array();

					$busca_adicional_tmp = mysqli_query($con, "SELECT concepto, detalle FROM adicional_tmp 
                        WHERE id_usuario = '" . $id_usuario . "' 
                          AND serie_factura = '" . $serie_liquidacion . "' 
                          AND secuencial_factura = '" . $secuencial_liquidacion . "'");
					$hay_adicionales = ($busca_adicional_tmp && mysqli_num_rows($busca_adicional_tmp) > 0);

					if ($hay_adicionales) {
						while ($row_ad = mysqli_fetch_array($busca_adicional_tmp)) {
							$campos_adicionales[] = array(
								'concepto' => $row_ad['concepto'],
								'detalle'  => $row_ad['detalle']
							);
						}
					}
					// Validar/normalizar y evitar duplicados (case-insensitive)
					$vistos = array();
					for ($i = 0; $i < count($campos_adicionales); $i++) {
						$concepto = $campos_adicionales[$i]['concepto'];
						$detalle  = $campos_adicionales[$i]['detalle'];

						$msgErr = '';
						if (!validar_par_adicional($concepto, $detalle, $msgErr)) {
							$errores_validacion[] = $msgErr;
							continue;
						}
						$key = function_exists('mb_strtolower') ? mb_strtolower($concepto, 'UTF-8') : strtolower($concepto);
						if (isset($vistos[$key])) {
							$errores_validacion[] = "Información adicional: el nombre \"" . $concepto . "\" está duplicado.";
							continue;
						}
						$vistos[$key] = true;

						$campos_adicionales[$i]['concepto'] = $concepto;
						$campos_adicionales[$i]['detalle']  = $detalle;
					}
					if (count($campos_adicionales) > 15) {
						$errores_validacion[] = "Información adicional: máximo permitido 15 campos. Actualmente: " . count($campos_adicionales) . ".";
					}

					if (!empty($errores_validacion)) {
						foreach ($errores_validacion as $e) {
							$errors[] = $e;
						}
					} else {
						/* ===================== TRANSACCIÓN: TODO O NADA ===================== */
						$codigo_unico = codigo_unico(20);
						mysqli_autocommit($con, false);
						$todo_ok = true;

						// 1) Encabezado
						if ($todo_ok) {
							$sql_enc = "INSERT INTO encabezado_liquidacion VALUES (
                                null,
                                '" . $ruc_empresa . "',
                                '" . $fecha_liquidacion . "',
                                '" . $serie_liquidacion . "',
                                '" . $secuencial_liquidacion . "',
                                '" . $id_proveedor_lc . "',
                                '" . $hoy_yyyy_mm_dd . "',
                                'PENDIENTE',
                                '" . $total_lc_sql . "',
                                '" . $_SESSION['id_usuario'] . "',
                                '0','0','0',
                                'PENDIENTE',
                                '" . $codigo_unico . "'
                            )";
							$ok_enc = mysqli_query($con, $sql_enc);
							if (!$ok_enc) {
								$errors[] = "Error guardando encabezado de la liquidación: " . mysqli_error($con);
								$todo_ok = false;
							}
						}

						// 2) Forma de pago
						if ($todo_ok) {
							$sql_fp = "INSERT INTO formas_pago_liquidacion VALUES (
                                null,
                                '" . $ruc_empresa . "',
                                '" . $serie_liquidacion . "',
                                '" . $secuencial_liquidacion . "',
                                '" . $forma_pago_lc . "',
                                '" . $total_lc_sql . "',
                                '" . $codigo_unico . "'
                            )";
							$ok_fp = mysqli_query($con, $sql_fp);
							if (!$ok_fp) {
								$errors[] = "Error guardando forma de pago: " . mysqli_error($con);
								$todo_ok = false;
							}
						}

						// 3) Detalle adicional (si hubo)
						if ($todo_ok && count($campos_adicionales) > 0) {
							for ($i = 0; $i < count($campos_adicionales); $i++) {
								$concepto_ins = mysqli_real_escape_string($con, $campos_adicionales[$i]['concepto']);
								$detalle_ins  = mysqli_real_escape_string($con, $campos_adicionales[$i]['detalle']);
								$sql_ad = "INSERT INTO detalle_adicional_liquidacion VALUES (
                                    null,
                                    '" . $ruc_empresa . "',
                                    '" . $serie_liquidacion . "',
                                    '" . $secuencial_liquidacion . "',
                                    '" . $concepto_ins . "',
                                    '" . $detalle_ins . "',
                                    '" . $codigo_unico . "'
                                )";
								$ok_ad = mysqli_query($con, $sql_ad);
								if (!$ok_ad) {
									$errors[] = "Error guardando información adicional (" . $concepto_ins . "): " . mysqli_error($con);
									$todo_ok = false;
									break;
								}
							}
						}

						// 4) Cuerpo de la liquidación
						if ($todo_ok) {
							while ($row_det = mysqli_fetch_array($sql_lc_temporal)) {
								$cantidad_lc  = to_float($row_det["cantidad_tmp"]);
								$precio_lc    = to_float($row_det['precio_tmp']);
								$subtotal_lc  = number_format(($precio_lc * $cantidad_lc), 2, '.', '');
								$tarifa_iva   = $row_det['tarifa_iva'];
								$descuento    = $row_det['descuento'];
								$codigo       = $row_det['id_producto'];
								$detalle_txt  = $row_det['lote'];

								$sql_det = "INSERT INTO cuerpo_liquidacion VALUES (
                                    null,
                                    '" . $ruc_empresa . "',
                                    '" . $serie_liquidacion . "',
                                    '" . $secuencial_liquidacion . "',
                                    '" . $cantidad_lc . "',
                                    '" . $precio_lc . "',
                                    '" . $subtotal_lc . "',
                                    '" . $tarifa_iva . "',
                                    '" . $descuento . "',
                                    '" . $codigo . "',
                                    '" . mysqli_real_escape_string($con, $detalle_txt) . "',
                                    '" . $codigo_unico . "'
                                )";
								$ok_det = mysqli_query($con, $sql_det);
								if (!$ok_det) {
									$errors[] = "Error guardando detalle de la liquidación: " . mysqli_error($con);
									$todo_ok = false;
									break;
								}
							}
						}

						// Commit o rollback
						if ($todo_ok) {
							mysqli_commit($con);
							mysqli_autocommit($con, true);
							echo "<script>
                                    $.notify('Liquidación guardada con éxito','success');
                                    setTimeout(function (){location.href ='../modulos/liquidacion_compra_servicio.php'}, 1000);
                                  </script>";
							exit;
						} else {
							mysqli_rollback($con);
							mysqli_autocommit($con, true);
						}
						/* ===================== FIN TRANSACCIÓN ===================== */
					}
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