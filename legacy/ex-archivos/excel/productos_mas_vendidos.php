<?php
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];

		$desde = date('Y-m-d H:i:s', strtotime($_POST['desde']));
		 $hasta = date('Y-m-d H:i:s', strtotime($_POST['hasta']));
		 $cantidad = mysqli_real_escape_string($con,(strip_tags($_POST['cantidad'], ENT_QUOTES)));
		 $id_cliente = mysqli_real_escape_string($con,(strip_tags($_POST['id_cliente'], ENT_QUOTES)));
		 $id_producto = mysqli_real_escape_string($con,(strip_tags($_POST['id_producto'], ENT_QUOTES)));

		 if (empty($id_cliente)){
			$condicion_cliente="";
			}else{
			$condicion_cliente=" and encfac.id_cliente=".$id_cliente;	
			}

		if (empty($id_producto)){
			$condicion_producto="";
			}else{
			$condicion_producto=" and cuefac.id_producto=".$id_producto;	
			}

		$consulta = mysqli_query($con, "SELECT med.nombre_medida as nombre_medida, cli.nombre as cliente, cuefac.codigo_producto as codigo_producto, cuefac.nombre_producto as nombre_producto, cuefac.id_medida_salida as medida, sum(cuefac.cantidad_factura) as total_cantidad, sum(cuefac.subtotal_factura) as subtotal FROM cuerpo_factura as cuefac INNER JOIN encabezado_factura as encfac ON encfac.serie_factura = cuefac.serie_factura AND encfac.secuencial_factura = cuefac.secuencial_factura 
		INNER JOIN clientes as cli ON cli.id=encfac.id_cliente LEFT JOIN unidad_medida as med ON med.id_medida=cuefac.id_medida_salida WHERE encfac.ruc_empresa='".$ruc_empresa."' and cuefac.ruc_empresa='".$ruc_empresa."'and encfac.fecha_factura BETWEEN '".$desde."' AND '".$hasta."' $condicion_cliente $condicion_producto group by cuefac.codigo_producto, encfac.id_cliente order by sum(cuefac.cantidad_factura) desc LIMIT 0, $cantidad");

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
								 ->setTitle("mas vendido")
								 ->setSubject("mas vendido")
								 ->setDescription("mas vendido")
								 ->setKeywords("mas vendido")
								 ->setCategory("mas vendido");

			//para sacar el nombre de la empresa
				$sql_empresa = "SELECT * FROM empresas where ruc= '".$ruc_empresa."'";      
				$resultado_empresa = mysqli_query($con,$sql_empresa);
				$empresa_info=mysqli_fetch_array($resultado_empresa);
				$tituloEmpresa= $empresa_info['nombre'];
			$tituloReporte = "Listado de Productos o servicios más vendidos";
			$titulosColumnas = array('Cliente','Código','Producto o servicio','Cantidad','Subtotal','Medida');
			
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

			while ($fila = mysqli_fetch_array($consulta)) {
			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A'.$i,  $fila['cliente'])
			->setCellValue('B'.$i,  $fila['codigo_producto'])
			->setCellValue('C'.$i,  $fila['nombre_producto'])
			->setCellValue('D'.$i,  number_format($fila['total_cantidad'],4,'.',''))
			->setCellValue('E'.$i,  number_format($fila['subtotal'],2,'.',''))
			->setCellValue('F'.$i,  $fila['nombre_medida'])
			;
			$objPHPExcel->getActiveSheet()->getStyle('B'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);
			$objPHPExcel->getActiveSheet()->getStyle('E'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$i++;
			}
			

	//REFERENCIA DEL ARCHIVO DONDE ESTAN LOS FORMATOS C:\xampp\htdocs\sistema\excel\lib\PHPExcel\PHPExcel\Style\NumberFormat.php
				
			for($i = 'A'; $i <= 'F'; $i++){
				$objPHPExcel->setActiveSheetIndex(0)->getColumnDimension($i)->setAutoSize(TRUE);
			}
			
			// Se asigna el nombre a la hoja
			$objPHPExcel->getActiveSheet()->setTitle('mas vendidos');
			
			// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
			$objPHPExcel->setActiveSheetIndex(0);
			$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,4);

			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="MasVendidos.xlsx"');
			header('Cache-Control: max-age=0');

			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');
			exit;
			
		}else{
			echo('No hay resultados para mostrar');
		}

?>