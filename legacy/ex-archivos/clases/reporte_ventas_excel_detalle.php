<?php
	include("../conexiones/conectalogin.php");
	$conexion = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];

	if(isset($_POST['consulta_e'])){
			$consulta_excel =$_POST['consulta_e'];	
			$aColumns = array('fecha_factura','nombre', 'secuencial_factura', 'serie_factura','estado_pago');//Columnas de busqueda
		 $sTable = "encabezado_factura ef, clientes c";
		 $sWhere = "WHERE ef.ruc_empresa ='".  $ruc_empresa ." ' and ef.id_cliente = c.id";
		if ( $consulta_excel != "" )
		{
			$sWhere = "WHERE (ef.ruc_empresa ='".  $ruc_empresa ." ' and ef.id_cliente = c.id AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$consulta_excel."%' and ef.ruc_empresa ='".  $ruc_empresa ." ' and ef.id_cliente = c.id or ";
			}
			$sWhere = substr_replace( $sWhere, "AND ef.ruc_empresa = '".  $ruc_empresa ."' and ef.id_cliente = c.id", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by ef.id_encabezado_factura desc";
	
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
								 ->setDescription("Reporte de ventas")
								 ->setKeywords("reporte ventas")
								 ->setCategory("Reporte excel");

			//para sacar el nombre de la empresa
				$sql_empresa = "SELECT * FROM empresas where ruc= '$ruc_empresa'";      
				$resultado_empresa = mysqli_query($conexion,$sql_empresa);
				$empresa_info=mysqli_fetch_array($resultado_empresa);
				$tituloEmpresa= $empresa_info['nombre'];
			$tituloReporte = "Reporte de ventas detallado";
			$titulosColumnas = array('Fecha','Cliente','Ruc','Factura','Cantidad','Código','Detalle','Valor unitario','Descuento','Subtotal','Tipo','Tarifa','IVA','Valor IVA','Total');
			
			$objPHPExcel->setActiveSheetIndex(0)
						->mergeCells('A1:O1')
						->mergeCells('A2:O2')
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
						->setCellValue('O3',  $titulosColumnas[13])
						;
			
			//Se agregan los datos de las facturas
			
					
			//para sacar subtotales de todas las tarifa iva de cada una de las facturas, si aumentara una tarifa no importaria
						
			$i = 4;
		
			while ($fila = $resultado->fetch_array()) {
					$serie=$fila['serie_factura'];
					$secuencial=$fila['secuencial_factura'];
		
		
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A'.$i,  date("d/m/Y", strtotime($fila['fecha_factura'])))
			->setCellValue('B'.$i,  $fila['nombre'])
			->setCellValue('C'.$i,  "=\"" . $fila['ruc'] . "\"")
			->setCellValue('D'.$i,  $fila['serie_factura'].'-'.$fila['secuencial_factura']);
		//para sacar el detalle de la factura
					$sql = "SELECT * FROM cuerpo_factura cf, productos_servicios ps, tipo_produccion tp, tarifa_iva ti where ti.codigo = cf.tarifa_iva and cf.tipo_produccion = tp.codigo and cf.id_producto = ps.id and cf.serie_factura = '$serie' and cf.secuencial_factura = $secuencial and cf.ruc_empresa= '$ruc_empresa' ;";      
					$resultado_detalle = mysqli_query($conexion,$sql);
					$d=$i;
					while ($detalle_factura=mysqli_fetch_array($resultado_detalle)){
					$cantidad= $detalle_factura['cantidad_factura'];
					$producto= $detalle_factura['nombre_producto'];
					$codigo= $detalle_factura['codigo_producto'];
					$valor_unitario= $detalle_factura['valor_unitario_factura'];
					$descuento= $detalle_factura['descuento'];
					$subtotal_factura= $detalle_factura['subtotal_factura']-$descuento;
					$tipo_produccion= $detalle_factura['nombre'];
					$tarifa_iva= $detalle_factura['tarifa'];
					$porcentaje_iva= $detalle_factura['porcentaje_iva']/100;
					$total_factura = $subtotal_factura + ($subtotal_factura * $porcentaje_iva);
					
					$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('E'.$d,  $cantidad)
					->setCellValue('F'.$d,  $codigo)
					->setCellValue('G'.$d,  $producto)
					->setCellValue('H'.$d,  $valor_unitario)
					->setCellValue('I'.$d,  number_format($descuento,2,'.',''))
					->setCellValue('J'.$d,  number_format($subtotal_factura,2,'.',''))
					->setCellValue('K'.$d,  $tipo_produccion)
					->setCellValue('L'.$d,  $tarifa_iva)
					->setCellValue('M'.$d,  $porcentaje_iva)
					->setCellValue('N'.$d,  number_format($subtotal_factura* $porcentaje_iva,2,'.',''))
					->setCellValue('O'.$d,  number_format($total_factura,2,'.',''))	;
					$d=$d+1;
					}
						$i=$d;
			}//fin del while
	
			for($i = 'A'; $i <= 'O'; $i++){
				$objPHPExcel->setActiveSheetIndex(0)			
					->getColumnDimension($i)->setAutoSize(TRUE);
			}
			
			// Se asigna el nombre a la hoja
			$objPHPExcel->getActiveSheet()->setTitle('DetalleVentas');

			// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
			$objPHPExcel->setActiveSheetIndex(0);
			// Inmovilizar paneles 
			//$objPHPExcel->getActiveSheet(0)->freezePane('A4');
			$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,4);

			// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="ReporteDetalleVentas.xlsx"');
			header('Cache-Control: max-age=0');

			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');
			exit;
			
		}else{
			print_r('No hay resultados para mostrar');
		}
	}
	
?>