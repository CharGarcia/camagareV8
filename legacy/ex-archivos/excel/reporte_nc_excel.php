<?php
	include("../conexiones/conectalogin.php");
	$conexion = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];

	if(isset($_POST['consulta_nc'])){
			$consulta_excel =$_POST['consulta_nc'];	
			$aColumns = array('fecha_nc','nombre', 'en.secuencial_nc', 'en.serie_nc');//Columnas de busqueda
		 $sTable = "cuerpo_nc cn INNER JOIN encabezado_nc en ON en.serie_nc=cn.serie_nc and en.secuencial_nc=cn.secuencial_nc and en.ruc_empresa='".$ruc_empresa."' and cn.ruc_empresa = '".$ruc_empresa."' INNER JOIN clientes cli ON cli.id=en.id_cliente ";
		 $sWhere = " ";
		if ( $consulta_excel != "" )
		{
			$sWhere = "WHERE ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$consulta_excel."%' or ";
			}
			$sWhere = substr_replace( $sWhere, "", -3 );
			$sWhere .= '';
		}
		$sWhere.=" group by en.secuencial_nc";
		//$sWhere.=" order by en.id_encabezado_nc desc";
	$consulta = "SELECT * FROM  $sTable $sWhere";
	$resultado = $conexion->query($consulta);

		if($resultado->num_rows > 0 ){			
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
								 ->setDescription("Reporte de nc")
								 ->setKeywords("reporte de nc")
								 ->setCategory("Reporte excel");

			//para sacar el nombre de la empresa
				$sql_empresa = "SELECT * FROM empresas where ruc= '".$ruc_empresa."'";      
				$resultado_empresa = mysqli_query($conexion,$sql_empresa);
				$empresa_info=mysqli_fetch_array($resultado_empresa);
				$tituloEmpresa= $empresa_info['nombre'];
			$tituloReporte = "Reporte de Notas de Crédito";
			$titulosColumnas = array('Fecha','Cliente','Ruc','Nota Crédito','Factura afectada','Base 0','Base 12','Base no objeto de iva','Base Exento','Descuento','Subtotal','Iva 12','Base ice','Total','Motivo','Aut. SRI');
			
			$objPHPExcel->setActiveSheetIndex(0)
						->mergeCells('A1:P1')
						->mergeCells('A2:P2')
						;
							
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
						->setCellValue('M3',  $titulosColumnas[12])
						->setCellValue('N3',  $titulosColumnas[13])
						->setCellValue('O3',  $titulosColumnas[14])
						->setCellValue('P3',  $titulosColumnas[15])
						;	
			$i = 4;
			//Consultar las tarifas de iva
			while ($fila = $resultado->fetch_array()) {
					$serie=$fila['serie_nc'];
					$secuencial=$fila['secuencial_nc'];
		
						//para sacar el detalle de base cero
						$sql_cero = "SELECT sum(subtotal_nc) as subtotal FROM cuerpo_nc where tarifa_iva = 0 and serie_nc = '".$serie."' and secuencial_nc = '".$secuencial."' and ruc_empresa= '".$ruc_empresa."'";      
						$resultado_subtotales = mysqli_query($conexion,$sql_cero);
						$subtotales=mysqli_fetch_array($resultado_subtotales);
						$base_cero= $subtotales['subtotal'];
						
						//para sacar el detalle de base doce
						$sql_doce = "SELECT sum(subtotal_nc) as subtotal FROM cuerpo_nc where tarifa_iva = 2 and serie_nc = '".$serie."' and secuencial_nc = '".$secuencial."' and ruc_empresa= '".$ruc_empresa."'";      
						$resultado_subtotales = mysqli_query($conexion,$sql_doce);
						$subtotales=mysqli_fetch_array($resultado_subtotales);
						$base_doce= $subtotales['subtotal'];

						//para sacar el detalle de base no obj imp
						$sql_noimp = "SELECT sum(subtotal_nc) as subtotal FROM cuerpo_nc where tarifa_iva = 6 and serie_nc = '".$serie."' and secuencial_nc = '".$secuencial."' and ruc_empresa= '".$ruc_empresa."'";      
						$resultado_subtotales = mysqli_query($conexion,$sql_noimp);
						$subtotales=mysqli_fetch_array($resultado_subtotales);
						$base_noimp= $subtotales['subtotal'];
						//para sacar el detalle de base no obj imp
						$sql_exento = "SELECT sum(subtotal_nc) as subtotal FROM cuerpo_nc where tarifa_iva = 7 and serie_nc = '".$serie."' and secuencial_nc = '".$secuencial."' and ruc_empresa= '".$ruc_empresa."'";      
						$resultado_subtotales = mysqli_query($conexion,$sql_exento);
						$subtotales=mysqli_fetch_array($resultado_subtotales);
						$base_exento= $subtotales['subtotal'];
						
						//para sacar el detalle de descuento
						$sql_descuento = "SELECT sum(descuento) as descuento FROM cuerpo_nc where serie_nc = '".$serie."' and secuencial_nc = '".$secuencial."' and ruc_empresa= '".$ruc_empresa."'";      
						$resultado_subtotales = mysqli_query($conexion,$sql_descuento);
						$subtotales=mysqli_fetch_array($resultado_subtotales);
						$base_descuento = $subtotales['descuento'];
						

			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A'.$i,  date("d/m/Y", strtotime($fila['fecha_nc'])))
			->setCellValue('B'.$i,  strtoupper($fila['nombre']))
			->setCellValue('C'.$i,  "=\"" . $fila['ruc'] . "\"")
			->setCellValue('D'.$i,  $fila['serie_nc'].'-'.$fila['secuencial_nc'])
			->setCellValue('E'.$i,  $fila['factura_modificada'])
			->setCellValue('F'.$i,  $base_cero)
			->setCellValue('G'.$i,  $base_doce)
			->setCellValue('H'.$i,  $base_noimp)
			->setCellValue('I'.$i,  $base_exento)
			->setCellValue('J'.$i,  $base_descuento)
			->setCellValue('K'.$i,  $base_cero+$base_doce+$base_noimp+$base_exento-$base_descuento)
			->setCellValue('L'.$i,  ($base_doce-$fila['descuento'])*0.12)
			->setCellValue('M'.$i,  '0')
			->setCellValue('N'.$i,  $fila['total_nc'])
			->setCellValue('O'.$i,  $fila['motivo'])
			->setCellValue('P'.$i,  "=\"" . $fila['aut_sri'] . "\"")
			;
				$i++;
			}
			$t=$i+1;
			$objPHPExcel->setActiveSheetIndex(0)
						->setCellValue('C'.$t,  'Totales');
								
			for($i = 'A'; $i <= 'P'; $i++){
				$objPHPExcel->setActiveSheetIndex(0)			
					->getColumnDimension($i)->setAutoSize(TRUE);
			}
			
			// Se asigna el nombre a la hoja
			$objPHPExcel->getActiveSheet()->setTitle('Notas de crédito');

			// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
			$objPHPExcel->setActiveSheetIndex(0);
			// Inmovilizar paneles 
			//$objPHPExcel->getActiveSheet(0)->freezePane('A4');
			$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,4);

			// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="Reporte notas de crédito.xlsx"');
			header('Cache-Control: max-age=0');

			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');
			exit;
			
		}else{
			echo('No hay resultados para mostrar');
		}
	}
	
?>