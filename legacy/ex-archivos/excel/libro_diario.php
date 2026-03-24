<?php
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];

    $desde = $_POST['fecha_desde'];
	$hasta = $_POST['fecha_hasta'];
	$consulta   = mysqli_query($con, "SELECT * FROM encabezado_diario WHERE ruc_empresa='".$ruc_empresa."' and 
	DATE_FORMAT(fecha_asiento, '%Y/%m/%d') between '".date("Y/m/d", strtotime($desde))."' and '".date("Y/m/d", strtotime($hasta))."' 
	and (estado ='ok' or estado ='editado')");
		if(mysqli_num_rows($consulta)>0){			
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
								 ->setTitle("Libro diario")
								 ->setSubject("Libro diario")
								 ->setDescription("Libro diario")
								 ->setKeywords("Libro diario")
								 ->setCategory("Libro diario");

			//para sacar el nombre de la empresa
				$sql_empresa = mysqli_query($con, "SELECT * FROM empresas where ruc= '".$ruc_empresa."'");      
				$empresa_info=mysqli_fetch_array($sql_empresa);
				$tituloEmpresa= $empresa_info['nombre'];
			$tituloReporte = "Libro diario desde ". date('d-m-Y', strtotime($desde)) . " hasta ".date('d-m-Y', strtotime($hasta));
			$titulosColumnas = array('Fecha','Asiento','Tipo','Código','Detalle','Debe','Haber');
			
			$objPHPExcel->setActiveSheetIndex(0)
						->mergeCells('A1:G1')
						->mergeCells('A2:G2')
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
						;
						
			$i = 4;
			$suma_debe = 0;
			$suma_haber = 0;
			while ($row = mysqli_fetch_array($consulta)) {
                $fecha_asiento=date('d-m-Y', strtotime($row['fecha_asiento']));
                $numero_asiento=$row['id_diario'];
                $concepto_general=$row['concepto_general'];
                $tipo=$row['tipo'];
                $codigo_unico=$row['codigo_unico'];

                $sql_detalle = mysqli_query($con, "SELECT * FROM detalle_diario_contable as det INNER JOIN plan_cuentas as plan
                ON plan.id_cuenta = det.id_cuenta WHERE det.ruc_empresa='".$ruc_empresa."' and det.codigo_unico ='".$codigo_unico."'");

			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A'.$i,  $fecha_asiento)
			->setCellValue('B'.$i,  "=\"" . $numero_asiento . "\"")
			->setCellValue('C'.$i,   $tipo)
			;
			//$objPHPExcel->getActiveSheet()->getStyle('E'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);
				//$i++;

                foreach ($sql_detalle as $row_detalle){
                    $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValue('D'.$i,  strtoupper($row_detalle['codigo_cuenta']))
                    ->setCellValue('E'.$i,  strtoupper($row_detalle['nombre_cuenta']))
                    ->setCellValue('F'.$i,  $row_detalle['debe'])
                    ->setCellValue('G'.$i,  $row_detalle['haber'])
                    ;
                    $suma_debe += $row_detalle['debe'];
                    $suma_haber += $row_detalle['haber'];
                    $i=$i+1;
                    }
                    $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValue('E'.$i,  "P/R: ".strtoupper($concepto_general))
                    ;
                    $i=$i+1;
			}
            $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValue('E'.$i,  "Sumas")
                    ->setCellValue('F'.$i,  $suma_debe)
                    ->setCellValue('G'.$i,  $suma_haber)
                    ;

            $objPHPExcel->getActiveSheet()->getStyle('F4:G'.$i) ->getNumberFormat() ->setFormatCode('#,##0.00');
			for($i = 'A'; $i <= 'D'; $i++){
				$objPHPExcel->setActiveSheetIndex(0)->getColumnDimension($i)->setAutoSize(TRUE);
			}
			
					
			// Se asigna el nombre a la hoja
			$objPHPExcel->getActiveSheet()->setTitle('LibroDiario');
			

			// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
			$objPHPExcel->setActiveSheetIndex(0);
			$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,4);

			// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="LibroDiario.xlsx"');
			header('Cache-Control: max-age=0');

			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');
			exit;
			
		}else{
			echo('No hay resultados para mostrar');
		}

?>