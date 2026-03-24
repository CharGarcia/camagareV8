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
if ($action == 'cargar_detalle' && isset($ruc_empresa)) {
	$nombre_archivo = $_FILES['archivo']['name'];
	$archivo_guardado = $_FILES['archivo']['tmp_name'];

	$directorio = '../docs_temp/'; //Declaramos un  variable con la ruta donde guardaremos los archivos
	$dir = opendir($directorio); //Abrimos el directorio de destino
	$target_path = $directorio . '/cargar_detalle_ventas.xlsx';
	$imageFileType = pathinfo($nombre_archivo, PATHINFO_EXTENSION);

	if ($imageFileType == "xlsx") {

		if (move_uploaded_file($archivo_guardado, $target_path)) {
			$objPHPExcel = PHPExcel_IOFactory::load('../docs_temp/cargar_detalle_ventas.xlsx');
			$objPHPExcel->setActiveSheetIndex(0);
			$numRows = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
			$serie = $objPHPExcel->getActiveSheet()->getCell('B1')->getCalculatedValue();
			$secuencial = $objPHPExcel->getActiveSheet()->getCell('B2')->getCalculatedValue();
			$total_factura = $objPHPExcel->getActiveSheet()->getCell('B3')->getCalculatedValue();

			$sql_factura_existente = mysqli_query($con, "SELECT count(*) as existe FROM encabezado_factura WHERE serie_factura= '" . $serie . "' and secuencial_factura= '" . $secuencial . "' and ruc_empresa='" . $ruc_empresa . "' and estado_sri='PENDIENTE' ");
			$row_factura_existente = mysqli_fetch_array($sql_factura_existente);
			$factura_existe = $row_factura_existente['existe'];

			$mensajes = array();
			//para guardar los detalles de factura

			if ($factura_existe > 0) {
				$sql_actualiza_encabezado = mysqli_query($con, "UPDATE encabezado_factura SET total_factura ='" . formatMoney($total_factura, 2) . "' 
				WHERE ruc_empresa= '" . $ruc_empresa . "' and serie_factura='" . $serie . "' and secuencial_factura='" . $secuencial . "' ");

				if (!$sql_actualiza_encabezado) {
					$mensajes[] = "No se guardó el total de la factura.";
				}

				$sql_actualiza_pagos = mysqli_query($con, "UPDATE formas_pago_ventas SET valor_pago ='" . formatMoney($total_factura, 2) . "' 
				WHERE ruc_empresa= '" . $ruc_empresa . "' and serie_factura='" . $serie . "' and secuencial_factura='" . $secuencial . "' ");

				if (!$sql_actualiza_pagos) {
					$mensajes[] = "No se guardó el total de pagos de la factura.";
				}

				for ($p = 5; $p <= $numRows; $p++) {
					$id_producto = $objPHPExcel->getActiveSheet()->getCell('A' . $p)->getCalculatedValue();
					$cantidad = $objPHPExcel->getActiveSheet()->getCell('B' . $p)->getCalculatedValue();
					$valor_unitario = $objPHPExcel->getActiveSheet()->getCell('C' . $p)->getCalculatedValue();
					$descuento = $objPHPExcel->getActiveSheet()->getCell('D' . $p)->getCalculatedValue();
					$tarifa_iva = $objPHPExcel->getActiveSheet()->getCell('F' . $p)->getCalculatedValue();
					$detalle_adicional = empty($objPHPExcel->getActiveSheet()->getCell('G' . $p)->getCalculatedValue()) ? "" : $objPHPExcel->getActiveSheet()->getCell('G' . $p)->getCalculatedValue();
					$lote = $objPHPExcel->getActiveSheet()->getCell('H' . $p)->getCalculatedValue();
					$vencimiento = $objPHPExcel->getActiveSheet()->getCell('I' . $p)->getCalculatedValue();
					$id_bodega = $objPHPExcel->getActiveSheet()->getCell('J' . $p)->getCalculatedValue();
					
					if (!empty($id_producto)) {
						$sql_busca_producto = mysqli_query($con, "SELECT id, codigo_producto, nombre_producto, tipo_produccion, id_unidad_medida FROM productos_servicios WHERE id= '" . $id_producto . "' ");
						$row_producto = mysqli_fetch_array($sql_busca_producto);
						$id_producto_encontrado = $row_producto['id'];
						$codigo_producto = $row_producto['codigo_producto'];
						$nombre_producto = $row_producto['nombre_producto'];
						$tipo = $row_producto['tipo_produccion'];
						$id_medida = $row_producto['id_unidad_medida'];
						 
						$sql_busca_iva = mysqli_query($con, "SELECT codigo FROM tarifa_iva WHERE codigo= '" . $tarifa_iva . "' ");
						$row_iva = mysqli_fetch_array($sql_busca_iva);
						$tarifa_iva_encontrada = $row_iva['codigo'];

						$sql_bodega = mysqli_query($con, "SELECT id_bodega FROM bodega WHERE id_bodega= '" . $id_bodega . "' ");
						$row_bodega = mysqli_fetch_array($sql_bodega);
						$id_bodega_encontrada = $row_bodega['id_bodega'];
	
						if ($id_producto != $id_producto_encontrado) {
							$mensajes[] = "Producto o servicio no encontrado en la fila " . $p;
						} else if ($tarifa_iva != $tarifa_iva_encontrada) {
							$mensajes[] = "Tarifa de IVA no encontrada en la fila " . $p;
						} else if ($tipo == "01" && ($id_bodega != $id_bodega_encontrada)) {
							$mensajes[] = "id bodega no encontrada en la fila " . $p;
						} else {
							$guarda_detalle_venta = mysqli_query($con, "INSERT INTO cuerpo_factura VALUES (null, 
							'" . $ruc_empresa . "',
							'" . $serie . "',
							'" . $secuencial . "',
							'" . $id_producto . "',
							'" . formatMoney($cantidad, 2) . "',
							'" . formatMoney($valor_unitario, 4) . "',
							'" . formatMoney($cantidad * $valor_unitario, 2) . "',
							'" . $tipo . "',
							'" . $tarifa_iva . "',
							'0',
							'" . strClean($detalle_adicional) . "',
							'" . formatMoney($descuento, 2) . "',
							'" . $codigo_producto . "',
							'" . $nombre_producto . "',
							'" . $id_medida . "',
							'" . $lote . "',
							'" . date('Y/m/d', strtotime($vencimiento)) . "',
							'" . $id_bodega . "')");
						} //->format('Y-m-d')
					}
				}
			} else {
				$mensajes[] = "No hay una factura registrada con estado pendiente, con este número, para agregar el detalle.";
			}
			if (empty($mensajes)) {
				unlink($target_path);
				echo "<script>
					$.notify('El detalle de ventas ha sido guardado.','success');
					</script>";
			} else {
				unlink($target_path);
				echo "<script>$.notify('Revisar los errores y volver a cargar.','error');
				</script>";
			}
		}
		//aqui termina la carga
		if (count($mensajes) > 0) {
			echo mensaje_error($mensajes);
		}
	} else {
		echo "<script>$.notify('Cargue un archivo excel.','error');
				</script>";
	}
}
