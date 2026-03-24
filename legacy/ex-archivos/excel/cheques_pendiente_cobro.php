<?php
include("../ajax/cheques_pendientes.php");
	
	$con = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

if ($action=="generar_informe_excel"){
    $cuenta = $_POST['cuenta'];
	$fecha_hasta = $_POST['fecha_hasta'];

    $cuentas = mysqli_query($con,"SELECT concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.id_cuenta ='".$cuenta."'");
	$row_cuenta = mysqli_fetch_array($cuentas);
    $nombre_cuenta=$row_cuenta['cuenta_bancaria'];


			ini_set('date.timezone','America/Guayaquil');
			$fecha_hoy = date_create(date("Y-m-d H:i:s"));
			//if(mysqli_num_rows($resultado) > 0 ){			
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
								 ->setTitle("Cheques Pendientes")
								 ->setSubject("Cheques Pendientes")
								 ->setDescription("Cheques Pendientes")
								 ->setKeywords("Cheques Pendientes")
								 ->setCategory("Cheques Pendientes");

			//para sacar el nombre de la empresa
				$sql_empresa = mysqli_query($con,"SELECT * FROM empresas where ruc= '".$ruc_empresa."'");      
				$empresa_info=mysqli_fetch_array($sql_empresa);
				$tituloEmpresa= $empresa_info['nombre_comercial'];
				$tituloReporte = "Cheques pendientes de cobro de la cuenta ".$nombre_cuenta. " al ".date("d/m/Y", strtotime($fecha_hasta));
				

			$titulosColumnas = array('Fecha Emisión','Fecha en cheque','Estado','# Cheque','Beneficiario','Valor');
			
			$objPHPExcel->setActiveSheetIndex(0)
						->mergeCells('A1:F1')
						->mergeCells('A2:F2')
						;
			
			$i = 4;
			// Se agregan los titulos del reporte
			$objPHPExcel->setActiveSheetIndex(0)
						->setCellValue('A1', $tituloEmpresa)
						->setCellValue('A2',  $tituloReporte)
						;
			
			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A'.$i,  $titulosColumnas[0])
			->setCellValue('B'.$i,  $titulosColumnas[1])
			->setCellValue('C'.$i,  $titulosColumnas[2])
			->setCellValue('D'.$i,  $titulosColumnas[3])
			->setCellValue('E'.$i,  $titulosColumnas[4])
			->setCellValue('F'.$i,  $titulosColumnas[5])
			;	
			$i++;

            $sql_cheques_cobrados = cheques_pendientes_cobrados($con, $cuenta, $ruc_empresa, $fecha_hasta);
            $suma=0;
            while ($row_cheques=mysqli_fetch_array($sql_cheques_cobrados)){
                $fecha_emision=$row_cheques['fecha_emision'];
                $fecha_entrega="Pendiente de cobro a la fecha";
                $fecha_pago=$row_cheques['fecha_pago'];
                $nombre_egreso =  $row_cheques['nombre'];
                $numero_cheque=$row_cheques['cheque'];
                $valor=$row_cheques['valor'];
                $suma += $row_cheques['valor'];

                $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A'.$i,  date("d/m/Y", strtotime($fecha_emision)))
                ->setCellValue('B'.$i, date("d/m/Y", strtotime($fecha_pago)))
                ->setCellValue('C'.$i,  $fecha_entrega)
                ->setCellValue('D'.$i,  $numero_cheque)
                ->setCellValue('E'.$i,  $nombre_egreso)
                ->setCellValue('F'.$i,  $valor)
                ;
                $objPHPExcel->getActiveSheet()->getStyle('F'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
                $i++;	
                }



        $sql_cheques_por_cobrar = cheques_pendientes_estado_entregar($con, $cuenta, $ruc_empresa, $fecha_hasta);
		$suma_porcobrar=0;
		while ($row_cheques=mysqli_fetch_array($sql_cheques_por_cobrar)){
			$fecha_emision=$row_cheques['fecha_emision'];
			$fecha_entrega=$fecha_entrega="En oficina por entregar";
			$fecha_pago=$row_cheques['fecha_pago'];
			$nombre_egreso =  $row_cheques['nombre'];
			$numero_cheque=$row_cheques['cheque'];
			$valor=$row_cheques['valor'];
			$suma_porcobrar += $row_cheques['valor'];
			
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A'.$i,  date("d/m/Y", strtotime($fecha_emision)))
                ->setCellValue('B'.$i, date("d/m/Y", strtotime($fecha_pago)))
                ->setCellValue('C'.$i,  $fecha_entrega)
                ->setCellValue('D'.$i,  $numero_cheque)
                ->setCellValue('E'.$i,  $nombre_egreso)
                ->setCellValue('F'.$i,  $valor)
                ;
                $objPHPExcel->getActiveSheet()->getStyle('F'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
                $i++;
			}

            $sql_cheques_por_cobrar = cheques_pendientes_estado_por_cobrar($con, $cuenta, $ruc_empresa, $fecha_hasta);
		$suma_estadoporcobrar=0;
		while ($row_cheques=mysqli_fetch_array($sql_cheques_por_cobrar)){
			$fecha_emision=$row_cheques['fecha_emision'];
			$fecha_entrega="Por cobrar";
			$fecha_pago=$row_cheques['fecha_pago'];
			$nombre_egreso =  $row_cheques['nombre'];
			$numero_cheque=$row_cheques['cheque'];
			$valor=$row_cheques['valor'];
			$suma_estadoporcobrar += $row_cheques['valor'];
			$objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A'.$i,  date("d/m/Y", strtotime($fecha_emision)))
                ->setCellValue('B'.$i, date("d/m/Y", strtotime($fecha_pago)))
                ->setCellValue('C'.$i,  $fecha_entrega)
                ->setCellValue('D'.$i,  $numero_cheque)
                ->setCellValue('E'.$i,  $nombre_egreso)
                ->setCellValue('F'.$i,  $valor)
                ;
                $objPHPExcel->getActiveSheet()->getStyle('F'.$i)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
                $i++;
			}
			


			for($i = 'A'; $i <= 'I'; $i++){
				$objPHPExcel->setActiveSheetIndex(0)			
					->getColumnDimension($i)->setAutoSize(TRUE);
			}

			
			// Se asigna el nombre a la hoja
			$objPHPExcel->getActiveSheet()->setTitle('ChequesPendientesCobro');

			// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
			$objPHPExcel->setActiveSheetIndex(0);
			$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,5);

			// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="ChequesPendientesCobro.xlsx"');
			header('Cache-Control: max-age=0');

			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');
			exit;
		}else{
			echo('No es posible generar el archivo.');
		}
?>