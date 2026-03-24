<?php
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];

	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'reporte_retenciones_compras'){
		$fecha_desde=mysqli_real_escape_string($con,(strip_tags($_POST["fecha_desde"],ENT_QUOTES)));
		$fecha_hasta=mysqli_real_escape_string($con,(strip_tags($_POST["fecha_hasta"],ENT_QUOTES)));
		
	$consulta="SELECT concat(enc.serie_retencion,'-', lpad(enc.secuencial_retencion,9,'0')) as retencion,
	 enc.numero_comprobante as documento_retenido, enc.fecha_emision as fecha_emision,
	 enc.fecha_documento as fecha_documento, cr.base_imponible as base_imponible, 
	 cr.impuesto as impuesto, cr.codigo_impuesto as codigo_impuesto, 
	 cr.porcentaje_retencion as porcentaje_retencion, round(cr.valor_retenido,2) as valor_retenido, pro.ruc_proveedor as ruc_proveedor,
	 pro.razon_social as proveedor, enc.aut_sri as aut_sri FROM cuerpo_retencion as cr INNER JOIN encabezado_retencion as enc ON enc.serie_retencion=cr.serie_retencion and 
	enc.secuencial_retencion = cr.secuencial_retencion and enc.ruc_empresa=cr.ruc_empresa
	 INNER JOIN proveedores as pro ON pro.id_proveedor=enc.id_proveedor WHERE cr.ruc_empresa = '".$ruc_empresa."' 
	 and enc.ruc_empresa = '".$ruc_empresa."' and enc.fecha_emision between '" . date("Y-m-d", strtotime($fecha_desde)) . "' 
	 and '" . date("Y-m-d", strtotime($fecha_hasta)) . "' order by enc.fecha_emision asc";

	$resultado = $con->query($consulta);	
		
		if($resultado->num_rows>0 ){			
			date_default_timezone_set('America/Guayaquil');
			if (PHP_SAPI == 'cli')
				die('Este archivo solo se puede ver desde un navegador web');

			/** Se agrega la libreria PHPExcel */
			require_once 'lib/PHPExcel/PHPExcel.php';

			// Se crea el objeto PHPExcel
			$objPHPExcel = new PHPExcel();

			// Se asignan las propiedades del libro
			$objPHPExcel->getProperties()->setCreator("CaMaGaRe") //Autor
								 ->setLastModifiedBy("CaMaGaRe") //Ultimo usuario que lo modificó
								 ->setTitle("Reporte Excel")
								 ->setSubject("Reporte Excel")
								 ->setDescription("Reporte retenciones")
								 ->setKeywords("reporte retenciones")
								 ->setCategory("Reporte excel");

			//para sacar el nombre de la empresa
				$sql_empresa = "SELECT * FROM empresas where ruc= '".$ruc_empresa."'";      
				$resultado_empresa = mysqli_query($con,$sql_empresa);
				$empresa_info=mysqli_fetch_array($resultado_empresa);
				$tituloEmpresa= $empresa_info['nombre'];
			$tituloReporte = "Reporte de Retenciones en Compras del: ".$fecha_desde." al ".$fecha_hasta;
			$titulosColumnas = array('N Retención','N Documento','Fecha emisión','Fecha documento','Base imponible','Impuesto','Código','% retención','Valor Retenido', 'Ruc proveedor', 'Proveedor','Aut. SRI');
			
			//$objPHPExcel->setActiveSheetIndex(0)
			//			->mergeCells('A1:A1')
			//			->mergeCells('A2:A2')
			//			;
							
			// Se agregan los titulos del reporte
			$objPHPExcel->setActiveSheetIndex(0)
						->setCellValue('A1',  $tituloEmpresa)
						->setCellValue('A2',  $tituloReporte)
						->setCellValue('A3',  $titulosColumnas[0])
						->setCellValue('B3',  $titulosColumnas[1])
						->setCellValue('C3',  $titulosColumnas[2])
						->setCellValue('D3',  $titulosColumnas[3])
						->setCellValue('E3',  $titulosColumnas[4])
						->setCellValue('F3',  $titulosColumnas[5])
						->setCellValue('G3',  $titulosColumnas[6])
						->setCellValue('H3',  $titulosColumnas[7])
						->setCellValue('I3',  $titulosColumnas[8])
						->setCellValue('J3',  $titulosColumnas[9])
						->setCellValue('K3',  $titulosColumnas[10])
						->setCellValue('L3',  $titulosColumnas[11])
						;
			
			//Se agregan los datos de las retenciones
									
			$i = 4;
		
			while ($fila = $resultado->fetch_array()) {
				$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A'.$i,  $fila['retencion'])
				->setCellValue('B'.$i,  $fila['documento_retenido'])
				->setCellValue('C'.$i,  date("d/m/Y", strtotime($fila['fecha_emision'])))
				->setCellValue('D'.$i,  date("d/m/Y", strtotime($fila['fecha_documento'])))
				->setCellValue('E'.$i,  $fila['base_imponible'])
				->setCellValue('F'.$i,  $fila['impuesto'])
				->setCellValue('G'.$i,  $fila['codigo_impuesto'])
				->setCellValue('H'.$i,  $fila['porcentaje_retencion'])
				->setCellValue('I'.$i,  $fila['valor_retenido'])
				->setCellValue('J'.$i,  "=\"" . $fila['ruc_proveedor'] . "\"")
				->setCellValue('K'.$i,  strtoupper($fila['proveedor']))
				->setCellValue('L'.$i,  "=\"" . $fila['aut_sri']. "\"");
						$i++;
				}
				$t=$i+1;
				$objPHPExcel->getActiveSheet()->getStyle('E4:E'.$t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('I4:I'.$t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);

			
	
			for($i = 'A'; $i <= 'L'; $i++){
				$objPHPExcel->setActiveSheetIndex(0)			
					->getColumnDimension($i)->setAutoSize(TRUE);
			}
			
			// Se asigna el nombre a la hoja
			$objPHPExcel->getActiveSheet()->setTitle('Retenciones');

			// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
			$objPHPExcel->setActiveSheetIndex(0);
			// Inmovilizar paneles 
			//$objPHPExcel->getActiveSheet(0)->freezePane('A4');
			$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,4);

			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="RetencionesCompras.xlsx"');
			header('Cache-Control: max-age=0');

			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');
			exit;
			
		}else{
			print_r('No hay resultados para mostrar');
		}
	}
?>