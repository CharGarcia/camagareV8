<?php
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];

	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if ( isset($_GET['action']) && isset($_GET['id_diario']) && $action=="diario_contable"){
	$id_diario=$_GET['id_diario'];

	$datos_encabezados = mysqli_query($con,"SELECT enc_dia.codigo_unico as codigo_unico, 
	enc_dia.fecha_asiento as fecha_asiento, enc_dia.id_diario as numero_asiento, 
	enc_dia.concepto_general as concepto_general, usu.nombre as nombre, enc_dia.estado as estado, 
	enc_dia.fecha_registro as fecha_registro, enc_dia.tipo as tipo,
	enc_dia.codigo_unico_bloque as codigo_unico_bloque  
	FROM encabezado_diario as enc_dia 
	INNER JOIN usuarios as usu ON enc_dia.id_usuario= usu.id 
	WHERE enc_dia.id_diario = '".$id_diario."' ");
	$row_encabezados=mysqli_fetch_assoc($datos_encabezados);
	$fecha_asiento = "Fecha asiento: ".date("d-m-Y", strtotime($row_encabezados['fecha_asiento']));
	$numero_asiento = "Asiento No.: ".$row_encabezados['numero_asiento'];
	$concepto_general ="Concepto general: ". $row_encabezados['concepto_general'];
	$realizado_por = "Realizado por: ".$row_encabezados['nombre'];
	$estado = "Estado: ".$row_encabezados['estado'];
	$tipo = "Tipo asiento: ".$row_encabezados['tipo'];
	$codigo_unico = $row_encabezados['codigo_unico'];
	$codigo_unico_bloque = $row_encabezados['codigo_unico_bloque'];
	$fecha_registro = "Fecha registro: ".date("d-m-Y", strtotime($row_encabezados['fecha_registro']));
	
		if($datos_encabezados->num_rows > 0 ){			
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
								 ->setDescription("Diario General")
								 ->setKeywords("diario general")
								 ->setCategory("Diario General");

			//para sacar el nombre de la empresa
				$sql_empresa = "SELECT * FROM empresas where ruc= '".$ruc_empresa."'";      
				$resultado_empresa = mysqli_query($con,$sql_empresa);
				$empresa_info=mysqli_fetch_array($resultado_empresa);
				$tituloEmpresa= $empresa_info['nombre'];
			$tituloReporte = "Diario General";
			$titulosColumnas = array('Código','Cuentas','Debe','Haber','Detalle');
			
			$objPHPExcel->setActiveSheetIndex(0)
						->mergeCells('A1:E1')
						->mergeCells('A2:E2')
						->mergeCells('A3:B3')
						->mergeCells('C3:E3')
						->mergeCells('A4:B4')
						->mergeCells('C4:E4')
						->mergeCells('A5:E5')
						->mergeCells('A6:E6')
						->mergeCells('A7:E7')
						;
							
			// Se agregan los titulos del reporte
			$objPHPExcel->setActiveSheetIndex(0)
						->setCellValue('A1',  $tituloEmpresa)
						->setCellValue('A2',  $tituloReporte)
						->setCellValue('A3',  $numero_asiento)
						->setCellValue('C3',  $fecha_asiento)
						->setCellValue('A4',  $estado)
						->setCellValue('C4',  $fecha_registro)
						->setCellValue('A5',  $tipo)
						->setCellValue('A6',  $realizado_por)
						->setCellValue('A7',  $concepto_general)
						->setCellValue('A8',  $titulosColumnas[0])
						->setCellValue('B8',  $titulosColumnas[1])
						->setCellValue('C8',  $titulosColumnas[2])
						->setCellValue('D8',  $titulosColumnas[3])
						->setCellValue('E8',  $titulosColumnas[4])
						;	
			$i = 9;
			
			$datos_sumas = mysqli_query($con,"SELECT sum(debe) as debe, sum(haber) as haber FROM 
			detalle_diario_contable WHERE codigo_unico='".$codigo_unico."' and codigo_unico_bloque='".$codigo_unico_bloque."' and ruc_empresa='".$ruc_empresa."'");
			$row_sumas=mysqli_fetch_assoc($datos_sumas);
			$suma_debe = $row_sumas['debe'];	
			$suma_haber = $row_sumas['haber'];
			
			$datos_detalle=mysqli_query($con, "SELECT * FROM detalle_diario_contable as det 
			INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det.id_cuenta 
			WHERE det.codigo_unico = '". $codigo_unico ."' and det.codigo_unico_bloque='".$codigo_unico_bloque."' and det.ruc_empresa='".$ruc_empresa."'");
			
			while ($fila = $datos_detalle->fetch_array()) {

			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A'.$i,  $fila['codigo_cuenta'])
			->setCellValue('B'.$i,  strtoupper($fila['nombre_cuenta']))
			->setCellValue('C'.$i,  $fila['debe'])
			->setCellValue('D'.$i,  $fila['haber'])
			->setCellValue('E'.$i,  $fila['detalle_item'])
			;
				$i++;
			}
			$t=$i;
			$objPHPExcel->setActiveSheetIndex(0)
						->setCellValue('B'.$t,  'Sumas')
						->setCellValue('C'.$t,  $suma_debe)
						->setCellValue('D'.$t,  $suma_haber);
											
								
			for($i = 'A'; $i <= 'F'; $i++){
				$objPHPExcel->setActiveSheetIndex(0)			
					->getColumnDimension($i)->setAutoSize(TRUE);
			}
			
			// Se asigna el nombre a la hoja
			$objPHPExcel->getActiveSheet()->setTitle('AsientoContable');

			// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
			$objPHPExcel->setActiveSheetIndex(0);
			$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,9);//PARA SABER DESDE DONDE SE EMPIEZA EL INMOBILIZAR PANEL
			// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="DiarioGeneral.xlsx"');
			header('Cache-Control: max-age=0');

			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');
			exit;
			
		}else{
			echo('No hay resultados para mostrar');
		}
	}
	
?>