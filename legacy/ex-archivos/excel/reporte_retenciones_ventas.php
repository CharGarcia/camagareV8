<?php
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];

	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'reporte_retenciones_ventas'){
		$fecha_desde=mysqli_real_escape_string($con,(strip_tags($_POST["fecha_desde"],ENT_QUOTES)));
		$fecha_hasta=mysqli_real_escape_string($con,(strip_tags($_POST["fecha_hasta"],ENT_QUOTES)));
		
	$consulta="SELECT concat(enc.serie_retencion,'-', lpad(enc.secuencial_retencion,9,'0')) as retencion,
	enc.numero_documento as documento_retenido, enc.fecha_emision as fecha_emision, cr.base_imponible as base_imponible,
	cr.impuesto as impuesto, cr.codigo_impuesto as codigo_impuesto, cr.porcentaje_retencion as porcentaje_retencion,
	 cr.valor_retenido as valor_retenido, cli.ruc as ruc_cliente, cli.nombre as cliente, enc.aut_sri as aut_sri 
	 FROM cuerpo_retencion_venta as cr INNER JOIN encabezado_retencion_venta as enc ON enc.codigo_unico=cr.codigo_unico INNER JOIN clientes as cli ON cli.id=enc.id_cliente
	 WHERE enc.ruc_empresa = '".$ruc_empresa."' and cr.ruc_empresa = '".$ruc_empresa."' and enc.fecha_emision between '" . date("Y-m-d", strtotime($fecha_desde)) . "' and '" . date("Y-m-d", strtotime($fecha_hasta)) . "' order by enc.fecha_emision asc";

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
				$sql_empresa = mysqli_query($con,"SELECT * FROM empresas where ruc= '".$ruc_empresa."'");      
				$empresa_info=mysqli_fetch_array($sql_empresa);
				$tituloEmpresa= $empresa_info['nombre'];
			$tituloReporte = "Reporte de Retenciones en Ventas del: ".$fecha_desde." al ".$fecha_hasta;
			$titulosColumnas = array('N Retención','Doc Retenido','Fecha emisión','Base imponible','Impuesto','Código','% retención','Valor Retenido', 'Ruc cliente', 'Cliente','Aut SRI');
			
			/*
			$objPHPExcel->setActiveSheetIndex(0)
						->mergeCells('A1:A1')
						->mergeCells('A2:A2')
						;
						*/
							
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
						->setCellValue('k3',  $titulosColumnas[10])
						;
			
			//Se agregan los datos de las retenciones
									
			$i = 4;
		
			while ($fila = $resultado->fetch_array()) {
				switch ($fila['impuesto']) {
					case "1":
						$tipo_impuesto='RENTA';
						break;
					case "2":
						$tipo_impuesto='IVA';
						break;
					case "6":
						$tipo_impuesto='ISD';
						break;
						}
				
				$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A'.$i,  $fila['retencion'])
				->setCellValue('B'.$i,  "=\"" . $fila['documento_retenido'] . "\"")
				->setCellValue('C'.$i,  date("d/m/Y", strtotime($fila['fecha_emision'])))
				->setCellValue('D'.$i,  $fila['base_imponible'])
				->setCellValue('E'.$i,  $tipo_impuesto)
				->setCellValue('F'.$i,  $fila['codigo_impuesto'])
				->setCellValue('G'.$i,  $fila['porcentaje_retencion'])
				->setCellValue('H'.$i,  $fila['valor_retenido'])
				->setCellValue('I'.$i,  "=\"" . $fila['ruc_cliente'] . "\"")
				->setCellValue('J'.$i,  strtoupper($fila['cliente']))
				->setCellValue('K'.$i,  "=\"" . $fila['aut_sri'] . "\"");
					$i++;
				}
				$t=$i+1;
				$objPHPExcel->getActiveSheet()->getStyle('D4:D'.$t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('H4:H'.$t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			//fin del while
	
			for($i = 'A'; $i <= 'K'; $i++){
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
			header('Content-Disposition: attachment;filename="RetencionesVentas.xlsx"');
			header('Cache-Control: max-age=0');

			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');
			exit;
			
		}else{
			print_r('No hay resultados para mostrar');
		}
	}
?>