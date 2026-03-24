<?php
	include("../conexiones/conectalogin.php");
	$conexion = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];

	$consulta = "SELECT pro.codigo_producto as codigo_producto,
    pro.nombre_producto as nombre_producto, round(co.costo,4) as costo, tar.tarifa as tarifa,
    uni_med.nombre_medida as medida, round(pro.precio_producto,4) as precio, round((pro.precio_producto-co.costo),4) as utilidad,
    round((pro.precio_producto * (1+tar.porcentaje_iva/100)),4) as precio_venta
      FROM productos_servicios as pro LEFT JOIN costos_productos as co ON co.id_producto=pro.id
      INNER JOIN tarifa_iva as tar ON tar.codigo=pro.tarifa_iva
      LEFT JOIN unidad_medida as uni_med ON uni_med.id_medida=pro.id_unidad_medida 
      WHERE pro.ruc_empresa='".$ruc_empresa."' ORDER BY pro.nombre_producto asc";
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
								 ->setTitle("CostosProductos")
								 ->setSubject("CostosProductos")
								 ->setDescription("CostosProductos")
								 ->setKeywords("CostosProductos")
								 ->setCategory("CostosProductos");

			//para sacar el nombre de la empresa
				$sql_empresa = "SELECT * FROM empresas where ruc= '".$ruc_empresa."'";      
				$resultado_empresa = mysqli_query($conexion,$sql_empresa);
				$empresa_info=mysqli_fetch_array($resultado_empresa);
				$tituloEmpresa= $empresa_info['nombre'];
			$tituloReporte = "Costos de productos y servicios";
			$titulosColumnas = array('Código','Producto/servicio','Tarifa IVA','Medida','Costo Promedio','Precio Unitario','Utilidad','Margen','Precio de venta');
			
			$objPHPExcel->setActiveSheetIndex(0)
						->mergeCells('A1:C1')
						->mergeCells('A2:C2')
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
						;
			
			//para sacar subtotales de todas las tarifa iva de cada una de las facturas, si aumentara una tarifa no importaria
						
			$i = 4;
			
			while ($fila = $resultado->fetch_array()) {
			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A'.$i,  "=\"" . $fila['codigo_producto'] . "\"")
			->setCellValue('B'.$i,  strtoupper($fila['nombre_producto']))
			->setCellValue('C'.$i,  strtoupper($fila['tarifa']))
			->setCellValue('D'.$i,  strtoupper($fila['medida']))
			->setCellValue('E'.$i,  number_format($fila['costo'],4,'.',''))
			->setCellValue('F'.$i,  number_format($fila['precio'],4,'.',''))
			->setCellValue('G'.$i,  number_format($fila['utilidad'],4,'.',''))
			->setCellValue('H'.$i,  number_format((($fila['precio']-$fila['costo'])/$fila['precio']),4,'.',''))
			->setCellValue('I'.$i,  number_format($fila['precio_venta'],4,'.',''))
			;
			$i++;			
			}
            
            $objPHPExcel->getActiveSheet()->getStyle('E4:I'.$i)->getNumberFormat()->setFormatCode('#,##0.0000');
            $objPHPExcel->getActiveSheet()->getStyle('H4:H'.$i)->getNumberFormat()->setFormatCode('0.00%');
								
			for($i = 'A'; $i <= 'I'; $i++){
				$objPHPExcel->setActiveSheetIndex(0)			
					->getColumnDimension($i)->setAutoSize(TRUE);
			}
			
			// Se asigna el nombre a la hoja
			$objPHPExcel->getActiveSheet()->setTitle('Detalle');

			// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
			$objPHPExcel->setActiveSheetIndex(0);
			$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,4);

			// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="CostosProductos.xlsx"');
			header('Cache-Control: max-age=0');

			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');
			exit;
			
		}else{
			echo('No hay resultados para mostrar');
		}

?>