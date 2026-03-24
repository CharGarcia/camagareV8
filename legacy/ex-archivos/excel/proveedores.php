<?php
	include("../conexiones/conectalogin.php");
	$conexion = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];

	
	$consulta = "SELECT * FROM proveedores WHERE ruc_empresa='".$ruc_empresa."' order by razon_social asc";
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
								 ->setTitle("Proveedores")
								 ->setSubject("Proveedores")
								 ->setDescription("Proveedores")
								 ->setKeywords("Proveedores")
								 ->setCategory("Proveedores");

			//para sacar el nombre de la empresa
				$sql_empresa = "SELECT * FROM empresas where ruc= '".$ruc_empresa."'";      
				$resultado_empresa = mysqli_query($conexion,$sql_empresa);
				$empresa_info=mysqli_fetch_array($resultado_empresa);
				$tituloEmpresa= $empresa_info['nombre'];
			$tituloReporte = "Listado de Proveedores";
			$titulosColumnas = array('Ruc','Nombre','Nombre comercial','Dirección','Teléfono','Mail');
			
			$objPHPExcel->setActiveSheetIndex(0)
						->mergeCells('A1:F1')
						->mergeCells('A2:F2')
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
						;
								
			$i = 4;
			
			while ($fila = $resultado->fetch_array()) {
			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A'.$i,  "=\"" . $fila['ruc_proveedor'] . "\"")
			->setCellValue('B'.$i,  strtoupper($fila['razon_social']))
			->setCellValue('C'.$i,  strtoupper($fila['nombre_comercial']))
			->setCellValue('D'.$i,  strtoupper($fila['dir_proveedor']))
			->setCellValue('E'.$i,  $fila['telf_proveedor'])
			->setCellValue('F'.$i,  $fila['mail_proveedor'])
			;
						$i++;
			}
								
			for($i = 'A'; $i <= 'F'; $i++){
				$objPHPExcel->setActiveSheetIndex(0)			
					->getColumnDimension($i)->setAutoSize(TRUE);
			}
			
			// Se asigna el nombre a la hoja
			$objPHPExcel->getActiveSheet()->setTitle('Proveedores');

			// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
			$objPHPExcel->setActiveSheetIndex(0);
			$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,4);

			// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="Proveedores.xlsx"');
			header('Cache-Control: max-age=0');

			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');
			exit;
			
		}else{
			echo('No hay resultados para mostrar');
		}

?>