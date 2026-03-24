<?php
include("../conexiones/conectalogin.php");
require("../excel/lib/PHPExcel/PHPExcel/IOFactory.php");
require_once("../helpers/helpers.php");
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$con = conenta_login();

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//boton de cargar archivo 
if ($action == 'cargar_ventas') {
	$nombre_archivo = $_FILES['archivo']['name'];
	$archivo_guardado = $_FILES['archivo']['tmp_name'];

	$directorio = '../docs_temp/'; //Declaramos un  variable con la ruta donde guardaremos los archivos
	$dir = opendir($directorio); //Abrimos el directorio de destino
	$target_path = $directorio . '/ventas.xlsx';

	$imageFileType = pathinfo($nombre_archivo, PATHINFO_EXTENSION);

	if ($imageFileType == "xlsx") {

		if (move_uploaded_file($archivo_guardado, $target_path)) {
			$objPHPExcel = PHPExcel_IOFactory::load('../docs_temp/ventas.xlsx');
			$objPHPExcel->setActiveSheetIndex(0);
			$numRows = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
			//para guardar los clientes

			$guardado = array();
			$mensajes = array();
			for ($p = 2; $p <= $numRows; $p++) {
				$dia = $objPHPExcel->getActiveSheet()->getCell('A' . $p)->getCalculatedValue();
				$mes = $objPHPExcel->getActiveSheet()->getCell('B' . $p)->getCalculatedValue();
				$anio = $objPHPExcel->getActiveSheet()->getCell('C' . $p)->getCalculatedValue();
				$fecha_validar = array('0' => str_pad($dia, 2, "00", STR_PAD_LEFT), "1" => str_pad($mes, 2, "00", STR_PAD_LEFT), "2" => str_pad($anio, 4, "0000", STR_PAD_LEFT));
				$fecha_factura = date('Y-m-d', strtotime(str_pad($anio, 4, "0000", STR_PAD_LEFT) . "-" . str_pad($mes, 2, "00", STR_PAD_LEFT) . "-" . str_pad($dia, 2, "00", STR_PAD_LEFT)));
				$ruc_cedula = $objPHPExcel->getActiveSheet()->getCell('D' . $p)->getCalculatedValue();
				$numero_documento = $objPHPExcel->getActiveSheet()->getCell('F' . $p)->getCalculatedValue();
				$aut_sri = $objPHPExcel->getActiveSheet()->getCell('G' . $p)->getCalculatedValue();
				$doce = $objPHPExcel->getActiveSheet()->getCell('K' . $p)->getCalculatedValue();
				$cero = $objPHPExcel->getActiveSheet()->getCell('L' . $p)->getCalculatedValue();
				$no_objeto = $objPHPExcel->getActiveSheet()->getCell('M' . $p)->getCalculatedValue();
				$propina = $objPHPExcel->getActiveSheet()->getCell('H' . $p)->getCalculatedValue();
				$otros_val = $objPHPExcel->getActiveSheet()->getCell('I' . $p)->getCalculatedValue();
				$total = $objPHPExcel->getActiveSheet()->getCell('J' . $p)->getCalculatedValue();
				$codigo = $objPHPExcel->getActiveSheet()->getCell('N' . $p)->getCalculatedValue();
				//$detalle = $objPHPExcel->getActiveSheet()->getCell('O' . $p)->getCalculatedValue();
				if (
					!empty($dia) &&
					!empty($mes) &&
					!empty($anio) &&
					!empty($ruc_cedula)
					&& !empty($numero_documento)
					&& !empty($aut_sri)
					&& !empty($total)
				) {

					if (validar_fecha($fecha_validar) == false) {
						$mensajes[] = "Fila " . $p . " corregir fecha";
					} else {
						$sql_busca_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE codigo_producto= '" . $codigo . "' and ruc_empresa='" . $ruc_empresa . "' ");
						$row_producto = mysqli_fetch_array($sql_busca_producto);
						$id_producto = $row_producto['id'];
						$tipo_produccion = $row_producto['tipo_produccion'];
						$producto = $row_producto['nombre_producto'];
						$medida = $row_producto['id_unidad_medida'];
						$contar_productos = mysqli_num_rows($sql_busca_producto);
						if ($contar_productos == 0) {
							$mensajes[] = "Fila " . $p . " producto no registrado, debe registrar primero el producto";
						} else {

						$sql_busca_cliente = mysqli_query($con, "SELECT * FROM clientes WHERE ruc= '" . $ruc_cedula . "' and ruc_empresa='" . $ruc_empresa . "' ");
						$row_clientes = mysqli_fetch_array($sql_busca_cliente);
						$id_cliente = $row_clientes['id'];
						$contar_clientes = mysqli_num_rows($sql_busca_cliente);
						if ($contar_clientes == 0) {
							$mensajes[] = "Fila " . $p . " cliente no registrado, debe registrar primero el cliente";
						} else {
						
							if (strlen($numero_documento) != 17) {
								$mensajes[] = "Fila " . $p . " el número de comprobante debe tener el formato 000-000-000000000";
							} else {

								$sql_compra_existente_con_esta_empresa = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_factura='" . substr($numero_documento,0,7) . "' and secuencial_factura='" . intval(substr($numero_documento,8,9)) . "' ");
								$row_count_existente_con_esta_empresa = mysqli_num_rows($sql_compra_existente_con_esta_empresa);

								if ($row_count_existente_con_esta_empresa > 0) {
									$mensajes[] = "Fila " . $p . " Documento " . $numero_documento . " registrado anteriormente.";
								} else {
									$guarda_encabezado_venta =  mysqli_query($con, "INSERT INTO encabezado_factura VALUES (null, 
									'" . $ruc_empresa . "',
									'" . date('Y-m-d H:i:s', strtotime($fecha_factura)) . "', 
									'" . substr($numero_documento,0,7) . "',
									'" . intval(substr($numero_documento,8,9)) . "',
									'" . $id_cliente . "', 
									'cargada desde archivo excel', 
									'',
									'" . date("Y-m-d H:i:s") . "',
									'POR COBRAR',
									'ELECTRÓNICA',
									'AUTORIZADO',
									'" . $total . "',
									'" . $id_usuario . "',
									'2',
									'0',
									'" . $aut_sri . "',
									'ENVIADO',
									'" . $propina . "',
									'" . $otros_val . "')");
									if ($doce > 0) {
										$guarda_detalle_venta = mysqli_query($con, "INSERT INTO cuerpo_factura VALUES (null, 
							'" . $ruc_empresa . "',
							'" . substr($numero_documento,0,7) . "',
							'" . intval(substr($numero_documento,8,9)) . "',
							'" . $id_producto . "',
							'1',
							'" . $doce . "',
							'" . $doce . "',
							'" . $tipo_produccion . "',
							'2',
							'0',
							'0',
							'0',
							'" . $codigo . "',
							'" . $producto . "',
							'" . $medida . "',
							'0','0','0')");
									}

									if ($cero > 0) {
										$guarda_detalle_venta = mysqli_query($con, "INSERT INTO cuerpo_factura VALUES (null, 
							'" . $ruc_empresa . "',
							'" . substr($numero_documento,0,7) . "',
							'" . intval(substr($numero_documento,8,9)) . "',
							'" . $id_producto . "',
							'1',
							'" . $cero . "',
							'" . $cero . "',
							'" . $tipo_produccion . "',
							'0',
							'0',
							'0',
							'0',
							'" . $codigo . "',
							'" . $producto . "',
							'" . $medida . "'
							,'0','0','0')");
									}

									if ($no_objeto > 0) {
										$guarda_detalle_venta = mysqli_query($con, "INSERT INTO cuerpo_factura VALUES (null, 
							'" . $ruc_empresa . "',
							'" . substr($numero_documento,0,7) . "',
							'" . intval(substr($numero_documento,8,9)) . "',
							'" . $id_producto . "',
							'1',
							'" . $no_objeto . "',
							'" . $no_objeto . "',
							'" . $tipo_produccion . "',
							'7',
							'0',
							'0',
							'0',
							'" . $codigo . "',
							'" . $producto . "',
							'" . $medida . "',
							'0','0','0')");
									}

									$guarda_formas_pago_venta = mysqli_query($con, "INSERT INTO formas_pago_ventas VALUES (null,
						  '" . $ruc_empresa . "',
						   '" . substr($numero_documento,0,7) . "',
						   '" . intval(substr($numero_documento,8,9)) . "',
						    '20',
							'" . $total . "')");

									if ($guarda_formas_pago_venta) {
										$guardado[] = 1;
									} else {
										$mensajes[] = " Fila " . $p . " no se guardó.";
									}
									
								}
							}
						}
					}
				}
					
				} else {
					$mensajes[] = "En fila " . $p . " faltan datos sobre el registro.";
				}
				
			}
			if (array_sum($guardado) > 0) {
				unlink($target_path);
				echo "<script>
					$.notify('Las ventas han sido guardadas.','success');
					</script>";
			} else {
				unlink($target_path);
				echo "<script>$.notify('No se guardaron los registros.','error');
				</script>";
			}
		}
	}
	//aqui termina la carga
	if (count($mensajes)>0) {
		echo mensaje_error($mensajes);
		}
}
?>
