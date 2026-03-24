<?php
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];

	$tipo_reporte=$_POST['tipo_reporte'];
	$id_cliente=$_POST['id_cliente'];
	$id_producto=$_POST['id_producto'];
	$desde=$_POST['fecha_desde'];
	$hasta=$_POST['fecha_hasta'];
	$id_marca=$_POST['id_marca'];

//para proformas
	if($tipo_reporte=='1'){
	if (empty($id_cliente)){
	$condicion_cliente="";
	}else{
	$condicion_cliente=" and enc_pro.id_cliente=".$id_cliente;	
	}

	if (empty($id_producto)){
	$condicion_producto="";
	}else{
	$condicion_producto=" and cue_pro.id_producto=".$id_producto;	
	}

	if (empty($id_marca)){
	$condicion_marca="";
	}else{
	$condicion_marca=" and mar_pro.id_marca=".$id_marca;	
	}			

	$resultado = mysqli_query($con, "SELECT * FROM cuerpo_proforma as cue_pro INNER JOIN encabezado_proforma as enc_pro ON enc_pro.codigo_unico=cue_pro.codigo_unico INNER JOIN clientes as cli ON cli.id=enc_pro.id_cliente LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=cue_pro.id_producto LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=cue_pro.id_producto WHERE enc_pro.ruc_empresa='".$ruc_empresa."' and cue_pro.ruc_empresa='".$ruc_empresa."' and DATE_FORMAT(enc_pro.fecha_proforma, '%Y/%m/%d') between '".date("Y/m/d", strtotime($desde))."' and '".date("Y/m/d", strtotime($hasta))."' $condicion_cliente $condicion_producto $condicion_marca group by cue_pro.codigo_unico");

		if(mysqli_num_rows($resultado) > 0 ){			
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
								 ->setDescription("Reporte de proformas")
								 ->setKeywords("Reporte proformas")
								 ->setCategory("Reporte excel");

			//para sacar el nombre de la empresa
				$sql_empresa = mysqli_query($con,"SELECT * FROM empresas where ruc= '".$ruc_empresa."'");      
				$empresa_info=mysqli_fetch_array($sql_empresa);
				$tituloEmpresa= $empresa_info['nombre_comercial'];
			$tituloReporte = "Reporte de proformas";
			$titulosColumnas = array('Fecha','Cliente','Ruc','Proforma','Base 0','Base 12','Base No iva','Base Exento','Descuento','Iva 12','Total','Usuario');
			
			$objPHPExcel->setActiveSheetIndex(0)
						->mergeCells('A1:L1')
						->mergeCells('A2:L2')
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
						;	
			$i = 4;
			$suma_base_cero=0;
			$suma_base_doce=0;
			$suma_base_noimp=0;
			$suma_base_exento=0;
			$suma_total_proforma=0;
			$suma_base_descuento=0;
			$suma_iva_base_doce=0;
			while ($fila = mysqli_fetch_array($resultado)) {
					$serie=$fila['serie_proforma'];
					$secuencial=$fila['secuencial_proforma'];
					$codigo_unico=$fila['codigo_unico'];
					$id_encabezado_proforma=$fila['id_encabezado_proforma'];
					$id_cliente=$fila['id_cliente'];
		
						//para sacar el detalle de base cero
						$sql_cero = mysqli_query($con, "SELECT sum(subtotal) as subtotal FROM cuerpo_proforma where tarifa_iva = 0 and codigo_unico = '".$codigo_unico."' and ruc_empresa= '".$ruc_empresa."'");      
						$subtotales=mysqli_fetch_array($sql_cero);
						$base_cero= $subtotales['subtotal'];
						$suma_base_cero+= $subtotales['subtotal'];
						
						//para sacar el detalle de base doce
						$sql_doce = mysqli_query($con,"SELECT sum(subtotal) as subtotal FROM cuerpo_proforma where tarifa_iva = 2 and codigo_unico = '".$codigo_unico."' and ruc_empresa= '".$ruc_empresa."'");
						$subtotales=mysqli_fetch_array($sql_doce);
						$base_doce= number_format($subtotales['subtotal'],2,'.','');
						$suma_base_doce+= number_format($subtotales['subtotal'],2,'.','');
						$suma_iva_base_doce+= number_format($subtotales['subtotal']*0.12,2,'.','');
						//para sacar el detalle de base no obj imp
						$sql_noimp = mysqli_query($con, "SELECT sum(subtotal) as subtotal FROM cuerpo_proforma where tarifa_iva = 6 and codigo_unico = '".$codigo_unico."' and ruc_empresa= '".$ruc_empresa."'");
						$subtotales=mysqli_fetch_array($sql_noimp);
						$base_noimp= $subtotales['subtotal'];
						$suma_base_noimp+= $subtotales['subtotal'];
						//para sacar el detalle de base no obj imp
						$sql_exento = mysqli_query($con, "SELECT sum(subtotal) as subtotal FROM cuerpo_proforma where tarifa_iva = 7 and codigo_unico = '".$codigo_unico."' and ruc_empresa= '".$ruc_empresa."'");
						$subtotales=mysqli_fetch_array($sql_exento);
						$base_exento= $subtotales['subtotal'];
						$suma_base_exento+= $subtotales['subtotal'];
						
						//para sacar el detalle de descuento
						$sql_descuento = mysqli_query($con,"SELECT sum(descuento) as descuento FROM cuerpo_proforma where codigo_unico = '".$codigo_unico."' and ruc_empresa= '".$ruc_empresa."'");      
						$subtotales=mysqli_fetch_array($sql_descuento);
						$base_descuento = $subtotales['descuento'];
						$suma_base_descuento+= $subtotales['descuento'];
						
						$suma_total_proforma+=$fila['total_proforma'];
						
						$sql_usuario = mysqli_query($con,"SELECT usu.nombre as nombre_usuario FROM usuarios usu, encabezado_proforma enc where usu.id=enc.id_usuario and enc.id_encabezado_proforma='".$id_encabezado_proforma."'"); 
						$usuario_nombres= mysqli_fetch_array($sql_usuario);
						$nombre_usuario = $usuario_nombres['nombre_usuario'];
						$numero_proforma=str_replace("-","",$fila['serie_proforma']).str_pad($fila['secuencial_proforma'],9,"000000000",STR_PAD_LEFT);

			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A'.$i,  date("d/m/Y", strtotime($fila['fecha_proforma'])))
			->setCellValue('B'.$i,  strtoupper($fila['nombre']))
			->setCellValue('C'.$i,  "=\"" . $fila['ruc'] . "\"")
			->setCellValue('D'.$i,  $fila['serie_proforma'].'-'.$fila['secuencial_proforma'])
			->setCellValue('E'.$i,  number_format($base_cero,2,'.',''))
			->setCellValue('F'.$i,  number_format($base_doce,2,'.',''))
			->setCellValue('G'.$i,  number_format($base_noimp,2,'.',''))
			->setCellValue('H'.$i,  number_format($base_exento,2,'.',''))
			->setCellValue('I'.$i,  number_format($base_descuento,2,'.',''))
			->setCellValue('J'.$i,  number_format($base_doce*0.12,2,'.',''))
			->setCellValue('K'.$i,  number_format($fila['total_proforma'],2,'.',''))
			->setCellValue('L'.$i,  strtoupper($nombre_usuario))
			;
			$objPHPExcel->getActiveSheet()->getStyle('E'.$i.':K'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			$i++;
							
			}
			$t=$i+1;
			$objPHPExcel->setActiveSheetIndex(0)
						->setCellValue('D'.$t,  'Totales')
						->setCellValue('E'.$t,  number_format($suma_base_cero,2,'.',''))
						->setCellValue('F'.$t,  number_format($suma_base_doce,2,'.',''))
						->setCellValue('G'.$t,  number_format($suma_base_noimp,2,'.',''))
						->setCellValue('H'.$t,  number_format($suma_base_exento,2,'.',''))
						->setCellValue('I'.$t,  number_format($suma_base_descuento,2,'.',''))
						->setCellValue('J'.$t,  number_format($suma_iva_base_doce,2,'.',''))
						->setCellValue('K'.$t,  number_format($suma_total_proforma,2,'.',''))
						;
							
			$objPHPExcel->getActiveSheet()->getStyle('E'.$t.':K'.$t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);

							
			for($i = 'A'; $i <= 'L'; $i++){
				$objPHPExcel->setActiveSheetIndex(0)			
					->getColumnDimension($i)->setAutoSize(TRUE);
			}
			
			// Se asigna el nombre a la hoja
			$objPHPExcel->getActiveSheet()->setTitle('Proformas');

			// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
			$objPHPExcel->setActiveSheetIndex(0);
			$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,4);

			// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="Reportedeproformas.xlsx"');
			header('Cache-Control: max-age=0');

			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');
			exit;
			
		}else{
			echo('No hay resultados para mostrar');
		}
	}

	
//reporte detalle de proformas
if($tipo_reporte=='2'){
if (empty($id_cliente)){
$condicion_cliente="";
}else{
$condicion_cliente=" and enc_pro.id_cliente=".$id_cliente;	
}

if (empty($id_producto)){
$condicion_producto="";
}else{
$condicion_producto=" and cue_pro.id_producto=".$id_producto;	
}

if (empty($id_marca)){
$condicion_marca="";
}else{
$condicion_marca=" and mar_pro.id_marca=".$id_marca;	
}

	$resultado = mysqli_query($con, "SELECT enc_pro.fecha_proforma as fecha_proforma, 
				enc_pro.serie_proforma as serie_proforma, cue_pro.secuencial_proforma as secuencial_proforma,
				cli.nombre as nombre_cliente, enc_pro.total_proforma as total_proforma, 
				cli.ruc as ruc, cue_pro.cantidad as cantidad_proforma,
				cue_pro.nombre_producto as nombre_producto, cue_pro.codigo_producto as codigo_producto,
				cue_pro.valor_unitario as valor_unitario_proforma,cue_pro.descuento as descuento, 
				cue_pro.subtotal as subtotal_proforma, cue_pro.lote as lote, 
				tip_pro.nombre as nombre_produccion, tar_iva.tarifa as tarifa, tar_iva.porcentaje_iva as porcentaje_iva, cue_pro.vencimiento as vencimiento, med.nombre_medida as medida, bod.nombre_bodega as bodega 
				FROM cuerpo_proforma as cue_pro INNER JOIN encabezado_proforma as enc_pro ON enc_pro.codigo_unico=cue_pro.codigo_unico 
				INNER JOIN clientes as cli ON cli.id=enc_pro.id_cliente 
				LEFT JOIN productos_servicios as pro_ser ON pro_ser.id=cue_pro.id_producto 
				LEFT JOIN marca_producto as mar_pro ON mar_pro.id_producto=cue_pro.id_producto 
				INNER JOIN tipo_produccion as tip_pro ON tip_pro.codigo=cue_pro.tipo_produccion 
				INNER JOIN tarifa_iva as tar_iva ON tar_iva.codigo=cue_pro.tarifa_iva LEFT JOIN unidad_medida as med ON med.id_medida = cue_pro.id_medida_salida LEFT JOIN bodega as bod ON bod.id_bodega=cue_pro.id_bodega WHERE enc_pro.ruc_empresa='".$ruc_empresa."' and cue_pro.ruc_empresa='".$ruc_empresa."' and DATE_FORMAT(enc_pro.fecha_proforma, '%Y/%m/%d') between '".date("Y/m/d", strtotime($desde))."' and '".date("Y/m/d", strtotime($hasta))."' $condicion_cliente $condicion_producto $condicion_marca ");
				$suma_total_proforma=0;
				$suma_cantidad =0;
				$suma_valor_unitario =0;
				$suma_subtotal_proforma =0;
				$suma_iva=0;

		if(mysqli_num_rows($resultado) > 0 ){		
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
								 ->setDescription("Reporte de proformas")
								 ->setKeywords("reporte proformas")
								 ->setCategory("Reporte excel");

			//para sacar el nombre de la empresa
				$sql_empresa = "SELECT * FROM empresas where ruc= '".$ruc_empresa."'";      
				$resultado_empresa = mysqli_query($con,$sql_empresa);
				$empresa_info=mysqli_fetch_array($resultado_empresa);
				$tituloEmpresa= $empresa_info['nombre_comercial'];
			$tituloReporte = "Reporte de proformas detallado";
			$titulosColumnas = array('Fecha','Cliente','Ruc','Proforma','Código','Detalle','Tipo','Tarifa','Cantidad','Valor unitario','Subtotal','IVA','Total','Lote','Medida','vencimiento','Bodega');
			
			$objPHPExcel->setActiveSheetIndex(0)
						->mergeCells('A1:Q1')
						->mergeCells('A2:Q2')
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
						->setCellValue('Q3',  $titulosColumnas[16])
						;
			$i = 4;
		
			while ($fila = mysqli_fetch_array($resultado)) {
					$fecha_proforma=$fila['fecha_proforma'];
					$serie_proforma=$fila['serie_proforma'];
					$secuencial_proforma=$fila['secuencial_proforma'];
					$nombre_cliente_proforma=$fila['nombre_cliente'];
					$ruc_cliente=$fila['ruc'];
					$cantidad= $fila['cantidad_proforma'];
					$suma_cantidad += $fila['cantidad_proforma'];
					$producto= preg_replace('/"/', "", $fila['nombre_producto']);
					$codigo= $fila['codigo_producto'];
					$valor_unitario= $fila['valor_unitario_proforma'];
					$suma_valor_unitario += $fila['valor_unitario_proforma'];
					$descuento= $fila['descuento'];
					$subtotal_proforma= $fila['subtotal_proforma'];
					$suma_subtotal_proforma += $fila['subtotal_proforma'];
					$tipo_produccion= $fila['nombre_produccion'];
					$tarifa_iva= $fila['tarifa'];
					$porcentaje_iva= $fila['porcentaje_iva']/100;
					$suma_iva +=number_format(($subtotal_proforma * $porcentaje_iva),2,'.','');
					$lote= $fila['lote'];
					$medida= $fila['medida'];
					$vencimiento= $fila['vencimiento'];
					$bodega= $fila['bodega'];
					$total_proforma= ($fila['subtotal_proforma'] + ($subtotal_proforma * $porcentaje_iva));
					$suma_total_proforma +=$total_proforma;
		
		
		$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A'.$i,  date("d/m/Y", strtotime($fila['fecha_proforma'])))
			->setCellValue('B'.$i,  $nombre_cliente_proforma)
			->setCellValue('C'.$i,  "=\"" . $fila['ruc'] . "\"")
			->setCellValue('D'.$i,  $fila['serie_proforma'].'-'.str_pad($fila['secuencial_proforma'],9,"000000000",STR_PAD_LEFT))
			->setCellValue('E'.$i,  "=\"" .$codigo. "\"")
			->setCellValue('F'.$i,  "=\"" .$producto. "\"")
			->setCellValue('G'.$i,  $tipo_produccion)
			->setCellValue('H'.$i,  $tarifa_iva)
			->setCellValue('I'.$i,  number_format($cantidad,4,'.',''))
			->setCellValue('J'.$i,  number_format($valor_unitario,4,'.',''))
			->setCellValue('K'.$i,  number_format($subtotal_proforma,2,'.',''))
			->setCellValue('L'.$i,  number_format(($subtotal_proforma * $porcentaje_iva),2,'.',''))
			->setCellValue('M'.$i,  number_format($total_proforma,2,'.',''))
			->setCellValue('N'.$i,  $lote)
			->setCellValue('O'.$i,  $medida)
			->setCellValue('P'.$i,  date("d-m-Y", strtotime($vencimiento)))
			->setCellValue('Q'.$i,  $bodega)
			;
			$objPHPExcel->getActiveSheet()->getStyle('I'.$i.':M'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			$i=$i+1;
			}//fin del while

			$t=$i+1;
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('H'.$t,  'Totales')
				->setCellValue('I'.$t,  number_format($suma_cantidad,4,'.',''))
				->setCellValue('J'.$t,  number_format($suma_valor_unitario,4,'.',''))
				->setCellValue('K'.$t,  number_format($suma_subtotal_proforma,2,'.',''))
				->setCellValue('L'.$t,  number_format($suma_iva,2,'.',''))
				->setCellValue('M'.$t,  number_format($suma_total_proforma,2,'.',''))
				;
			$objPHPExcel->getActiveSheet()->getStyle('I'.$t.':M'.$t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			
			for($i = 'A'; $i <= 'Q'; $i++){
				$objPHPExcel->setActiveSheetIndex(0)			
					->getColumnDimension($i)->setAutoSize(TRUE);
			}
			
			// Se asigna el nombre a la hoja
			$objPHPExcel->getActiveSheet()->setTitle('DetalleProformas');

			// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
			$objPHPExcel->setActiveSheetIndex(0);
			// Inmovilizar paneles 
			//$objPHPExcel->getActiveSheet(0)->freezePane('A4');
			$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,4);

			// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="ReporteDetalleProformas.xlsx"');
			header('Cache-Control: max-age=0');

			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');
			exit;
			
		}else{
			print_r('No hay resultados para mostrar');
		}
	}
?>