<?php
	include("../ajax/informes_contables_consolidados.php");
	$con = conenta_login();
//	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	
		$desde = $_POST['fecha_desde'];
		$hasta = $_POST['fecha_hasta'];
		
		$informe = $_POST['nombre_informe'];
		$nivel = $_POST['nivel'];
		if ($nivel=='0'){
		$nivel_cuenta = "";
		}else{
		$nivel_cuenta = " and nivel_cuenta = ".$nivel;	
		}

		if ($informe=='sri'){
			generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '1', '6');
			$sql_delete=mysqli_query($con,"DELETE FROM balances_tmp WHERE mid(ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and id_usuario='".$id_usuario."' and nivel_cuenta !='5'");  
			$sql_update_pasivo=mysqli_query($con,"UPDATE balances_tmp SET valor=valor*-1 WHERE mid(ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and mid(codigo_cuenta,1,1)='2' ");  
			$sql_update_patrimonio=mysqli_query($con,"UPDATE balances_tmp SET valor=valor*-1 WHERE mid(ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and mid(codigo_cuenta,1,1)='3' ");  
			$sql_update_ingresos=mysqli_query($con,"UPDATE balances_tmp SET valor=valor*-1 WHERE mid(ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and mid(codigo_cuenta,1,1)='4' "); 
			$sql_update=mysqli_query($con,"UPDATE balances_tmp as bal_tmp INNER JOIN plan_cuentas as plan ON bal_tmp.codigo_cuenta=plan.codigo_cuenta SET bal_tmp.codigo_cuenta = plan.codigo_sri WHERE mid(plan.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and mid(bal_tmp.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' ");  
			$consulta=mysqli_query($con,"SELECT codigo_cuenta as codigo_cuenta, sum(valor) as valor FROM balances_tmp WHERE mid(ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and id_usuario='".$id_usuario."' group by codigo_cuenta");  			
			$errores = control_errores($con, $ruc_empresa, $desde, $hasta, 'excel');
		}
		
		
		if ($informe=='1'){
		generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '1', '3');
		$consulta=mysqli_query($con,"SELECT nivel_cuenta as nivel, codigo_cuenta as codigo_cuenta, nombre_cuenta as nombre_cuenta, sum(valor) as valor  FROM balances_tmp WHERE mid(ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' $nivel_cuenta group by codigo_cuenta, nivel_cuenta");  

		//para sacar la utilidad
			$resultado_utilidad= utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta);
			$resultado_ejercicio=$resultado_utilidad['resultado'];
			$utilidad_ejercicio=$resultado_utilidad['valor'];
				
			//para sacar los totales de activo pasivo y patrimonio
			generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '1', '3');
			$sql_totales=mysqli_query($con,"SELECT nombre_cuenta as nombre_cuenta, sum(valor) as valor, codigo_cuenta as codigo_cuenta FROM balances_tmp WHERE mid(ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and nivel_cuenta='1' group by codigo_cuenta");  
			$errores = control_errores($con, $ruc_empresa, $desde, $hasta, 'excel');
		}
		
		if ($informe=='2'){
			generar_balance($con, $ruc_empresa, $id_usuario, $desde, $hasta, '4', '6');
			$consulta=mysqli_query($con,"SELECT nivel_cuenta as nivel, codigo_cuenta as codigo_cuenta, nombre_cuenta as nombre_cuenta, sum(valor) as valor  FROM balances_tmp WHERE mid(ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' $nivel_cuenta group by codigo_cuenta");  
			$resultado_utilidad= utilidad_perdida($con, $ruc_empresa, $id_usuario, $desde, $hasta);
			$resultado_ejercicio=$resultado_utilidad['resultado'];
			$utilidad_ejercicio=$resultado_utilidad['valor'];
			$errores = control_errores($con, $ruc_empresa, $desde, $hasta, 'excel');
		}
		
		if ($informe=='3'){
			$consulta = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, sum(det_dia.debe) as debe, sum(det_dia.haber) as haber FROM detalle_diario_contable as det_dia INNER JOIN encabezado_diario as enc_dia ON enc_dia.codigo_unico=det_dia.codigo_unico INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det_dia.id_cuenta WHERE mid(plan.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and mid(enc_dia.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and mid(det_dia.ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and DATE_FORMAT(enc_dia.fecha_asiento, '%Y/%m/%d') between '".date("Y/m/d", strtotime($desde))."' and '".date("Y/m/d", strtotime($hasta))."' and mid(plan.codigo_cuenta,1,1) >= '1' and mid(plan.codigo_cuenta,1,1) <= '6' and enc_dia.estado !='ANULADO' and plan.nivel_cuenta='5' group by plan.id_cuenta order by plan.codigo_cuenta asc");
			$errores = control_errores($con, $ruc_empresa, $desde, $hasta, 'excel');
		}
			
			
			
		if(mysqli_num_fields($consulta) > 0 ){			
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
								 ->setTitle("Estados Financieros")
								 ->setSubject("Estados Financieros")
								 ->setDescription("Estados Financieros")
								 ->setKeywords("Estados Financieros")
								 ->setCategory("Estados Financieros");

			//para sacar el nombre de la empresa
				$sql_empresa = mysqli_query($con,"SELECT * FROM empresas where ruc= '".$ruc_empresa."'");      
				$empresa_info=mysqli_fetch_array($sql_empresa);
				$tituloEmpresa= $empresa_info['nombre'];
				$rep_legal= $empresa_info['nom_rep_legal'];
				$nombre_contador= $empresa_info['nombre_contador'];
				$cedula_rep_legal= $empresa_info['ced_rep_legal'];
				$ruc_contador= $empresa_info['ruc_contador'];
				
			if ($informe=='sri'){
			$tituloReporte = "BALANCES SRI";
			$fechaReporte = "DEL ".date("d-m-Y", strtotime($desde))." AL ".date("d-m-Y", strtotime($hasta));
			$tituloHoja = "Balances_sri";
			$titulolibro = "Balances_sri.xlsx";
			$titulosColumnas = array('','Código SRI','Valor','','','','','');
			}
			if ($informe=='1'){
			$tituloReporte = "ESTADO DE SITUACIÓN FINANCIERA";
			$fechaReporte = "AL ".date("d-m-Y", strtotime($hasta));
			$tituloHoja = "Estado_situacion_financiera";
			$titulolibro="Estado_situacion_financiera.xlsx";
			$titulosColumnas = array('Código','Código SRI','Cuenta','Nivel 5','Nivel 4','Nivel 3','Nivel 2','Nivel 1');
			}
			if ($informe=='2'){
			$tituloReporte = "ESTADO DE RESULTADOS";
			$fechaReporte = "DEL ".date("d-m-Y", strtotime($desde))." AL ".date("d-m-Y", strtotime($hasta));
			$tituloHoja = "Estado_resultados";
			$titulolibro="Estado_resultados.xlsx";
			$titulosColumnas = array('Código','Código SRI','Cuenta','Nivel 5','Nivel 4','Nivel 3','Nivel 2','Nivel 1');
			}
			if ($informe=='3'){
			$tituloReporte = "BALANCE DE COMPROBACIÓN";
			$fechaReporte = "DEL ".date("d-m-Y", strtotime($desde))." AL ".date("d-m-Y", strtotime($hasta));
			$tituloHoja = "Balance_comprobacion";
			$titulolibro="Balance_comprobacion.xlsx";
			$titulosColumnas = array('Código','Cuenta','Debe','Haber','Saldo deudor','Saldo acreedor');
			}
			$titulosColumnas = $titulosColumnas;
			
			$objPHPExcel->setActiveSheetIndex(0)
						->mergeCells('A1:H1')
						->mergeCells('A2:H2')
						->mergeCells('A3:H3')
						;
							
			// Se agregan los titulos del reporte
			$objPHPExcel->setActiveSheetIndex(0)
						->setCellValue('A1',  $tituloEmpresa)
						->setCellValue('A2',  $tituloReporte)
						->setCellValue('A3',  $fechaReporte)
						->setCellValue('A4',  $titulosColumnas[0])
						->setCellValue('B4',  $titulosColumnas[1])
						->setCellValue('C4',  $titulosColumnas[2])
						->setCellValue('D4',  $titulosColumnas[3])
						->setCellValue('E4',  $titulosColumnas[4])
						->setCellValue('F4',  $titulosColumnas[5])
						->setCellValue('G4',  $titulosColumnas[6])
						->setCellValue('H4',  $titulosColumnas[7])
						;			
			$i = 5;
			
			if ($informe=='1'){
				while ($row_detalle_balance=mysqli_fetch_array($consulta)){
						$codigo_cuenta =$row_detalle_balance['codigo_cuenta'];
						$codigos_sri=mysqli_query($con,"SELECT * FROM plan_cuentas WHERE mid(ruc_empresa,1,10) = '".substr($ruc_empresa,0,10)."' and codigo_cuenta='".$codigo_cuenta."'");  
						$row_codigos_sri=mysqli_fetch_array($codigos_sri);
						$codigo_sri=$row_codigos_sri['codigo_sri'];
						$valor =$row_detalle_balance['valor'];
					if ($valor!=0){	
						$nivel=$row_detalle_balance['nivel'];
						if (substr($codigo_cuenta,0,1)==1){
						$valor=$valor;
						}else{
						$valor=$valor*-1;	
						}
						
						if ($nivel==5){
						$nivel_cinco=$valor;	
						}else{
						$nivel_cinco="";	
						}
						
						if ($nivel==4){
						$nivel_cuatro=$valor;	
						}else{
						$nivel_cuatro="";	
						}
						
						if ($nivel==3){
						$nivel_tres=$valor;	
						}else{
						$nivel_tres="";	
						}
						
						if ($nivel==2){
						$nivel_dos=$valor;	
						}else{
						$nivel_dos="";	
						}
						
						if ($nivel==1){
						$nivel_uno=$valor;	
						}else{
						$nivel_uno="";	
						}
						
						
				$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A'.$i,  $row_detalle_balance['codigo_cuenta'])
				->setCellValue('B'.$i,  $codigo_sri)
				->setCellValue('C'.$i,  strtoupper($row_detalle_balance['nombre_cuenta']))
				->setCellValue('D'.$i,  $nivel_cinco)
				->setCellValue('E'.$i,  $nivel_cuatro)
				->setCellValue('F'.$i,  $nivel_tres)
				->setCellValue('G'.$i,  $nivel_dos)
				->setCellValue('H'.$i,  $nivel_uno)
				;
				$objPHPExcel->getActiveSheet()->getStyle('D'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('E'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('F'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('G'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('H'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('A'.$i)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
				$objPHPExcel->getActiveSheet()->getStyle('B'.$i)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
				$i++;
					
				}
				
				}

				$t=$i+1;
				
				while ($row_totales=mysqli_fetch_array($sql_totales)){
					$codigo_cuenta=$row_totales['codigo_cuenta'];
					$nombre_cuenta_pasivo_patrimonio=$row_totales['nombre_cuenta'];
					if (substr($codigo_cuenta,0,1)==1){
					$valor_pasivo_patrimonio =number_format($row_totales['valor'],2,'.','');
					}else{
					$valor_pasivo_patrimonio =number_format($row_totales['valor']*-1,2,'.','');
					}
				
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('C'.$t,  strtoupper($nombre_cuenta_pasivo_patrimonio))
					->setCellValue('D'.$t,  number_format($valor_pasivo_patrimonio,2,'.',''))
					;
					$t=$t+1;
				}
				
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('C'.$t,  $resultado_ejercicio)
					->setCellValue('D'.$t,  number_format($utilidad_ejercicio,2,'.',''))
					;
					
			}
			
			if ($informe=='2'){
				while ($row_detalle_balance=mysqli_fetch_array($consulta)){
				$codigo_cuenta=$row_detalle_balance['codigo_cuenta'];
						$codigos_sri=mysqli_query($con,"SELECT * FROM plan_cuentas WHERE mid(ruc_empresa,1,10) = '". substr($ruc_empresa,0,10) ."' and codigo_cuenta='".$codigo_cuenta."'");  
						$row_codigos_sri=mysqli_fetch_array($codigos_sri);
						$codigo_sri=$row_codigos_sri['codigo_sri'];
				$valor =$row_detalle_balance['valor'];
				if ($valor!=0){
				if (substr($codigo_cuenta,0,1)>3 && substr($codigo_cuenta,0,1)<5){
				$valor=$valor*-1;
				}else{
				$valor=$valor;
				}
				$nivel =$row_detalle_balance['nivel'];
				if ($nivel==5){
						$nivel_cinco=$valor;	
						}else{
						$nivel_cinco="";	
						}
						
						if ($nivel==4){
						$nivel_cuatro=$valor;	
						}else{
						$nivel_cuatro="";	
						}
						
						if ($nivel==3){
						$nivel_tres=$valor;	
						}else{
						$nivel_tres="";	
						}
						
						if ($nivel==2){
						$nivel_dos=$valor;	
						}else{
						$nivel_dos="";	
						}
						
						if ($nivel==1){
						$nivel_uno=$valor;	
						}else{
						$nivel_uno="";	
						}
						
				$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A'.$i,  $row_detalle_balance['codigo_cuenta'])
				->setCellValue('B'.$i,  $codigo_sri)
				->setCellValue('C'.$i,  strtoupper($row_detalle_balance['nombre_cuenta']))
				->setCellValue('D'.$i,  $nivel_cinco)
				->setCellValue('E'.$i,  $nivel_cuatro)
				->setCellValue('F'.$i,  $nivel_tres)
				->setCellValue('G'.$i,  $nivel_dos)
				->setCellValue('H'.$i,  $nivel_uno)
				;
				$objPHPExcel->getActiveSheet()->getStyle('D'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('E'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('F'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('G'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('H'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('A'.$i)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
				$objPHPExcel->getActiveSheet()->getStyle('B'.$i)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

					$i++;
				}
			}
				$t=$i+1;
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('C'.$t,  $resultado_ejercicio)
					->setCellValue('D'.$t,  number_format($utilidad_ejercicio,2,'.',''))
					;
			
			}
			if ($informe=='3'){
			$suma_debe_cuenta=0;
			$suma_haber_cuenta=0;
			$suma_deudor_cuenta=0;
			$suma_acreedor_cuenta=0;
			
				while ($row_detalle_balance=mysqli_fetch_array($consulta)){
						$codigo_cuenta=$row_detalle_balance['codigo_cuenta'];
						$nombre_cuenta=$row_detalle_balance['nombre_cuenta'];
						$debe_cuenta=$row_detalle_balance['debe'];
						$haber_cuenta=$row_detalle_balance['haber'];
						$suma_debe_cuenta +=$debe_cuenta;
						$suma_haber_cuenta +=$haber_cuenta;
						$deudor_cuenta=$debe_cuenta>$haber_cuenta?$debe_cuenta-$haber_cuenta:0;
						$acreedor_cuenta=$haber_cuenta>$debe_cuenta?$haber_cuenta-$debe_cuenta:0;
						$suma_deudor_cuenta +=$deudor_cuenta;
						$suma_acreedor_cuenta +=$acreedor_cuenta;
						$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A'.$i,  $codigo_cuenta)
				->setCellValue('B'.$i,  $nombre_cuenta)
				->setCellValue('C'.$i,  $debe_cuenta)
				->setCellValue('D'.$i,  $haber_cuenta)
				->setCellValue('E'.$i,  $deudor_cuenta)
				->setCellValue('F'.$i,  $acreedor_cuenta)
				;
				$objPHPExcel->getActiveSheet()->getStyle('C'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('D'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('E'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
				$objPHPExcel->getActiveSheet()->getStyle('F'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
					$i++;
				}
				
				$t=$i+1;
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('B'.$t,  'Sumas')
					->setCellValue('C'.$t,  number_format($suma_debe_cuenta,2,'.',''))
					->setCellValue('D'.$t,  number_format($suma_haber_cuenta,2,'.',''))
					->setCellValue('E'.$t,  number_format($suma_deudor_cuenta,2,'.',''))
					->setCellValue('F'.$t,  number_format($suma_acreedor_cuenta,2,'.',''))
					;
					$t++;
			$objPHPExcel->getActiveSheet()->getStyle('C'.$t.':F'.$t)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
			$objPHPExcel->getActiveSheet()->getStyle('C'.$t.':F'.$t)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
			}
			
			if ($informe=='sri'){
				while ($row_detalle_balance=mysqli_fetch_array($consulta)){
					$codigo_sri =$row_detalle_balance['codigo_cuenta'];
					$valor =$row_detalle_balance['valor'];
				if ($valor!=0){
				$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A'.$i,  '')
				->setCellValue('B'.$i,  $codigo_sri)
				->setCellValue('C'.$i,  $valor)
				->setCellValue('D'.$i,  '')
				->setCellValue('E'.$i,  '')
				->setCellValue('F'.$i,  '')
				->setCellValue('G'.$i,  '')
				->setCellValue('H'.$i,  '')
				;
				$objPHPExcel->getActiveSheet()->getStyle('C'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
					$i++;
				}
				
				}
				$t=$i;
			}

			$t=$t+2;
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('C'.$t,  'Gerente General')
				->setCellValue('D'.$t,  'Contador')
				;
			
			$t=$t+1;
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('C'.$t,  $rep_legal)
				->setCellValue('D'.$t,  $nombre_contador)
				;
			
				$t=$t+1;
				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('C'.$t,  "Ced/pas:".$cedula_rep_legal)
					->setCellValue('D'.$t,  "RUC:".$ruc_contador)
					;
				
			$t=$t+3;
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A'.$t,  'Revisar: '.$errores)
				;
			
				$t=$t;
				$h=$t+5;
			$objPHPExcel->getActiveSheet()->mergeCells('A'.$t.':H'.$h);
			$objPHPExcel->getActiveSheet()->getStyle('A'.$i.':H'.$h)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_JUSTIFY);
			$objPHPExcel->getActiveSheet()->getStyle('A'.$i.':H'.$h)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
				
			
			for($i = 'A'; $i <= 'H'; $i++){
				$objPHPExcel->setActiveSheetIndex(0)			
					->getColumnDimension($i)->setAutoSize(TRUE);
			}
			
			// Se asigna el nombre a la hoja
			$objPHPExcel->getActiveSheet()->setTitle($tituloHoja);

			// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
			$objPHPExcel->setActiveSheetIndex(0);
			$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,5);

			// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename='.$titulolibro);
			header('Cache-Control: max-age=0');

			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');
			exit;
			
		}else{
			echo('No hay resultados para mostrar');
			exit;
		}

?>