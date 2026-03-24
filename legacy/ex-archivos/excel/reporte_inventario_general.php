<?php
	include("../conexiones/conectalogin.php");
	$conexion = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	
	$consulta = "SELECT  exi.lote as lote, exi.fecha_caducidad as caducidad, pro.precio_producto as precio_producto, exi.id_producto as id_prod, exi.codigo_producto as codigo_prod, exi.nombre_producto as nombre_prod, exi.saldo_producto as saldo_producto, med.nombre_medida as nombre_medida, bod.nombre_bodega as nombre_bodega FROM existencias_inventario_tmp as exi INNER JOIN productos_servicios as pro ON exi.id_producto=pro.id INNER JOIN unidad_medida as med ON exi.id_medida=med.id_medida INNER JOIN bodega as bod ON exi.id_bodega=bod.id_bodega WHERE exi.ruc_empresa = '".$ruc_empresa."' ";
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
								 ->setTitle("Existencia")
								 ->setSubject("Existencia")
								 ->setDescription("Existencia")
								 ->setKeywords("Existencia")
								 ->setCategory("Reporte Existencias");

			//para sacar el nombre de la empresa
				$sql_empresa = "SELECT * FROM empresas where ruc= '".$ruc_empresa."'";      
				$resultado_empresa = mysqli_query($conexion,$sql_empresa);
				$empresa_info=mysqli_fetch_array($resultado_empresa);
				$tituloEmpresa= $empresa_info['nombre_comercial'];
			$tituloReporte = "Reporte de existencias";
			$titulosColumnas = array('Id producto','Código','Producto','Existencia','Medida','Bodega','Marca','Total');
			
			$objPHPExcel->setActiveSheetIndex(0)
						->mergeCells('A1:H1')
						->mergeCells('A2:H2')
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
						;
			$i = 4;
			
			while ($fila = $resultado->fetch_array()) {
					$id_producto=$fila['id_prod'];
					$codigo_producto=$fila['codigo_prod'];
					$nombre_producto=$fila['nombre_prod'];
					$saldo_producto=$fila['saldo_producto'];
					$nombre_medida=$fila['nombre_medida'];
					$nombre_bodega=$fila['nombre_bodega'];
					$caducidad=$fila['caducidad'];
					$lote=$fila['lote'];
					$precio_final=$fila['precio_producto']*$saldo_producto;
					 $busca_marca = "SELECT * FROM marca_producto as mar_pro INNER JOIN marca as mar ON mar_pro.id_marca=mar.id_marca WHERE mar_pro.id_producto = '".$id_producto."' ";
					 $result_marca = $conexion->query($busca_marca);
					 $row_marca = mysqli_fetch_array($result_marca);
					 $nombre_marca=$row_marca['nombre_marca'];
					 


			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A'.$i,  $id_producto)
			->setCellValue('B'.$i,  $codigo_producto)
			->setCellValue('C'.$i,  strtoupper($nombre_producto))
			->setCellValue('D'.$i,  $saldo_producto)
			->setCellValue('E'.$i,  $nombre_medida)
			->setCellValue('F'.$i,  $nombre_bodega)
			->setCellValue('G'.$i,  $nombre_marca)
			->setCellValue('H'.$i,  $precio_final)
			;
						$i++;
			}
			$t=$i+1;
								
			for($i = 'A'; $i <= 'H'; $i++){
				$objPHPExcel->setActiveSheetIndex(0)			
					->getColumnDimension($i)->setAutoSize(TRUE);
			}
			
			// Se asigna el nombre a la hoja
			$objPHPExcel->getActiveSheet()->setTitle('Existencias');

			// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
			$objPHPExcel->setActiveSheetIndex(0);
			$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,4);

			// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="Existencias.xlsx"');
			header('Cache-Control: max-age=0');

			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');
			exit;
			
		}else{
			echo('No hay resultados para mostrar');
		}
	
?>