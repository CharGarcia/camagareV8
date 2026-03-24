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
if ($action == 'cargar_compras') {
	$nombre_archivo = $_FILES['archivo']['name'];
	$archivo_guardado = $_FILES['archivo']['tmp_name'];

	$directorio = '../docs_temp/'; //Declaramos un  variable con la ruta donde guardaremos los archivos
	$dir = opendir($directorio); //Abrimos el directorio de destino
	$target_path = $directorio . '/compras.xlsx';

	$imageFileType = pathinfo($nombre_archivo, PATHINFO_EXTENSION);

	if ($imageFileType == "xlsx") {

		if (move_uploaded_file($archivo_guardado, $target_path)) {
			$objPHPExcel = PHPExcel_IOFactory::load('../docs_temp/compras.xlsx');
			$objPHPExcel->setActiveSheetIndex(0);
			$numRows = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
			//para guardar los proveedores

			$guardado = array();
			$mensajes = array();
			for ($p = 2; $p <= $numRows; $p++) {
				$dia = $objPHPExcel->getActiveSheet()->getCell('A' . $p)->getCalculatedValue();
				$mes = $objPHPExcel->getActiveSheet()->getCell('B' . $p)->getCalculatedValue();
				$anio = $objPHPExcel->getActiveSheet()->getCell('C' . $p)->getCalculatedValue();
				$fecha_validar = array('0' => str_pad($dia, 2, "00", STR_PAD_LEFT), "1" => str_pad($mes, 2, "00", STR_PAD_LEFT), "2" => str_pad($anio, 4, "0000", STR_PAD_LEFT));
				$fecha_factura = date('Y-m-d', strtotime(str_pad($anio, 4, "0000", STR_PAD_LEFT) . "-" . str_pad($mes, 2, "00", STR_PAD_LEFT) . "-" . str_pad($dia, 2, "00", STR_PAD_LEFT)));
				$ruc_cedula = $objPHPExcel->getActiveSheet()->getCell('D' . $p)->getCalculatedValue();
				$id_comprobante = $objPHPExcel->getActiveSheet()->getCell('E' . $p)->getCalculatedValue();
				$numero_documento = $objPHPExcel->getActiveSheet()->getCell('F' . $p)->getCalculatedValue();
				$codigo_sustento = $objPHPExcel->getActiveSheet()->getCell('G' . $p)->getCalculatedValue();
				$aut_sri = $objPHPExcel->getActiveSheet()->getCell('H' . $p)->getCalculatedValue();
				$doce = $objPHPExcel->getActiveSheet()->getCell('I' . $p)->getCalculatedValue();
				$cero = $objPHPExcel->getActiveSheet()->getCell('J' . $p)->getCalculatedValue();
				$no_objeto = $objPHPExcel->getActiveSheet()->getCell('K' . $p)->getCalculatedValue();
				$ice = $objPHPExcel->getActiveSheet()->getCell('L' . $p)->getCalculatedValue();
				$otros_val = $objPHPExcel->getActiveSheet()->getCell('M' . $p)->getCalculatedValue();
				$total = $objPHPExcel->getActiveSheet()->getCell('N' . $p)->getCalculatedValue();
				$detalle = $objPHPExcel->getActiveSheet()->getCell('O' . $p)->getCalculatedValue();
				$codigo_unico = codigo_aleatorio(20);
				$tipo_documento = "04"; // strlen($ruc_cedula) == 13 ? "04" : "05";
				if (
					!empty($dia) &&
					!empty($mes) &&
					!empty($anio) &&
					!empty($ruc_cedula) &&
					!empty($id_comprobante)
					&& !empty($numero_documento)
					&& !empty($codigo_sustento)
					&& !empty($aut_sri)
					&& !empty($total)
					&& !empty($detalle)
				) {

					if (validar_fecha($fecha_validar) == false) {
						$mensajes[] = "Fila " . $p . " corregir fecha";
					} else {
						$sql_busca_proveedor = mysqli_query($con, "SELECT * FROM proveedores WHERE ruc_proveedor= '" . $ruc_cedula . "' and ruc_empresa='" . $ruc_empresa . "' ");
						$row_proveedores = mysqli_fetch_array($sql_busca_proveedor);
						$id_proveedor = $row_proveedores['id_proveedor'];
						$contar_proveedores = mysqli_num_rows($sql_busca_proveedor);
						if ($contar_proveedores == 0) {
							$mensajes[] = "Fila " . $p . " proveedor no registrado, debe resgitrar primero el proveedor";
						} else {
						
							if (strlen($numero_documento) != 17) {
								$mensajes[] = "Fila " . $p . " el número de comprobante debe tener el formato 000-000-000000000";
							} else {

								$sql_compra_existente_con_esta_empresa = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE ruc_empresa = '" . $ruc_empresa . "' and id_proveedor='" . $id_proveedor . "' and numero_documento='" . $numero_documento . "' and id_comprobante='" . $id_comprobante . "' ");
								$row_count_existente_con_esta_empresa = mysqli_num_rows($sql_compra_existente_con_esta_empresa);

								if ($row_count_existente_con_esta_empresa > 0) {
									$mensajes[] = "Fila " . $p . " Documento " . $numero_documento . " registrado anteriormente.";
								} else {
									$guarda_encabezado_compra =  mysqli_query($con, "INSERT INTO encabezado_compra VALUES (null, 
						'" . date('Y-m-d H:i:s', strtotime($fecha_factura)) . "', 
						'" . $ruc_empresa . "', 
						'" . $numero_documento . "', 
						'" . $codigo_unico . "', 
						'" . $id_proveedor . "', 
						'" . $id_comprobante . "', 
						'" . $codigo_sustento . "', 
						'" . $aut_sri . "',
						'" . date('Y-m-d H:i:s', strtotime($fecha_factura)) . "', 
						'" . substr($numero_documento, 9, 9) . "',
						'" . substr($numero_documento, 9, 9) . "',
						 '" . date("Y-m-d H:i:s") . "',
						  '" . $id_usuario . "', 
						  '" . $total . "', 
						  '','0','FÍSICA','" . $tipo_documento . "','" . $ice . "','" . $otros_val . "','0')");

									if ($doce > 0) {
										$guarda_detalle_compra = mysqli_query($con, "INSERT INTO cuerpo_compra VALUES (null, 
							'" . $ruc_empresa . "',
							'" . $codigo_unico . "',
							'001',
							'" . strClean($detalle) . "',
							'1',
							'" . $doce . "',
							'0',
							'2',
							'2',
							'" . ($doce) . "',
							0)");
									}

									if ($cero > 0) {
										$guarda_detalle_compra = mysqli_query($con, "INSERT INTO cuerpo_compra VALUES (null, 
							'" . $ruc_empresa . "',
							'" . $codigo_unico . "',
							'001',
							'" . strClean($detalle) . "',
							'1',
							'" . $cero . "',
							'0',
							'2',
							'0',
							'" . ($cero) . "',
							0)");
									}

									if ($no_objeto > 0) {
										$guarda_detalle_compra = mysqli_query($con, "INSERT INTO cuerpo_compra VALUES (null, 
							'" . $ruc_empresa . "',
							'" . $codigo_unico . "',
							'001',
							'" . strClean($detalle) . "',
							'1',
							'" . $no_objeto . "',
							'0',
							'2',
							'6',
							'" . ($no_objeto) . "',
							0)");
									}

									$guarda_formas_pago_compra = mysqli_query($con, "INSERT INTO formas_pago_compras VALUES (null,
						  '" . $ruc_empresa . "',
						   '" . $codigo_unico . "',
						    '20',
							'" . $total . "',
							 '30',
							 'Días' )");

									if ($guarda_encabezado_compra) {
										$guardado[] = 1;
									} else {
										$mensajes[] = " Fila " . $p . " no se guardó.";
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
					$.notify('Las compras/servicios han sido guardados.','success');
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
