<?php
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];

			if (empty($_POST['sucursal_alumno_pc'])) {
           $errors[] = "Seleccione campus.";
		}else if (empty($_POST['paralelo_alumno_pc'])) {
           $errors[] = "Seleccione nivel";
		}else if (empty($_POST['periodo_alumno_pc'])) {
           $errors[] = "Seleccione periodo";
        } else if (!empty($_POST['sucursal_alumno_pc']) && !empty($_POST['paralelo_alumno_pc']) && !empty($_POST['periodo_alumno_pc']))
		{
			$sucursal_listado=mysqli_real_escape_string($con,(strip_tags($_POST["sucursal_alumno_pc"],ENT_QUOTES)));
			$paralelo_alumno_listado=mysqli_real_escape_string($con,(strip_tags($_POST["paralelo_alumno_pc"],ENT_QUOTES)));
			$periodo_alumno_pc=mysqli_real_escape_string($con,(strip_tags($_POST["periodo_alumno_pc"],ENT_QUOTES)));
			
			
			if ($_POST['paralelo_alumno_pc']=="TODOS"){
			$condiciones = "ho.id_horario = al.horario_alumno and al.ruc_empresa='".$ruc_empresa."' and al.sucursal_alumno = '".$sucursal_listado."' and al.estado_alumno='1' and al.id_cliente>0 and al.id_cliente= cl.id order by al.apellidos_alumno asc";
			}else{
			$condiciones = "ho.id_horario = al.horario_alumno and al.ruc_empresa='".$ruc_empresa."' and al.sucursal_alumno = '".$sucursal_listado."' and paralelo_alumno = '".$paralelo_alumno_listado."' and al.estado_alumno='1' and al.id_cliente>0 and al.id_cliente= cl.id order by al.apellidos_alumno asc";
			}

	$sql_alumnos=mysqli_query($con, "SELECT ho.nombre_horario as horario, al.id_cliente as id_cliente, al.id_alumno as id_alumno, cl.nombre as clientes, al.nombres_alumno as nombres, al.apellidos_alumno as apellidos FROM horarios_alumnos ho, alumnos al, clientes cl WHERE $condiciones");
	
	if($sql_alumnos->num_rows > 0 ){
	$numero=0;
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
								 ->setDescription("Cuentas por cobrar")
								 ->setKeywords("Cuentas por cobrar")
								 ->setCategory("Reporte excel");

			//para sacar el nombre de los titulos
				$sql_campus = "SELECT * FROM campus_alumnos where ruc_empresa = '".$ruc_empresa."' and id_campus='".$sucursal_listado."'";      
				$resultado_campus = mysqli_query($con,$sql_campus);
				$campus_info=mysqli_fetch_array($resultado_campus);
				$titulocampus= $campus_info['nombre_campus'];
			// nivel
				$sql_nivel = "SELECT * FROM nivel_alumnos where ruc_empresa = '".$ruc_empresa."' and id_nivel='".$paralelo_alumno_listado."'";      
				$resultado_nivel = mysqli_query($con,$sql_nivel);
				$nivel_info=mysqli_fetch_array($resultado_nivel);
				$titulonivel= $nivel_info['nombre_nivel'];
				
				// nivel
				$sql_periodo = "SELECT * FROM periodo_a_facturar where codigo_periodo='".$periodo_alumno_pc."'";      
				$resultado_periodo = mysqli_query($con,$sql_periodo);
				$periodo_info=mysqli_fetch_array($resultado_periodo);
				$tituloperiodo= $periodo_info['detalle_periodo'];

				$tituloReporte = "Reporte de valores por cobrar";
	
	$titulosColumnas = array('Alumno','Horario','Datos Factura','Concepto','Valor');
			
			//$objPHPExcel->setActiveSheetIndex(0)
			//			->mergeCells('A1:F1')
			//			->mergeCells('A2:F2')
			//			;
							
			// Se agregan los titulos del reporte
			$objPHPExcel->setActiveSheetIndex(0)
						->setCellValue('A1',  $tituloReporte)
						->setCellValue('A2', "Campus")
						->setCellValue('A3',  "Nivel")
						->setCellValue('A4',  "Período")
						->setCellValue('B2',  $titulocampus)
						->setCellValue('B3',  $titulonivel)
						->setCellValue('B4',  $tituloperiodo)
						->setCellValue('A5',  $titulosColumnas[0])
						->setCellValue('B5',  $titulosColumnas[1])
						->setCellValue('C5',  $titulosColumnas[2])
						->setCellValue('D5',  $titulosColumnas[3])
						->setCellValue('E5',  $titulosColumnas[4])
						;
	$i = 6;
	while ($row=mysqli_fetch_array($sql_alumnos)){
	$id_alumno=$row["id_alumno"];
	$nombres_alumnos= $row['apellidos'] ." ". $row['nombres'];
	$id_cliente=$row['id_cliente'];
	$clientes=$row['clientes'];
	$horario=$row['horario'];
	$numero = $numero + 1;

			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('A'.$i,  strtoupper($nombres_alumnos))
			->setCellValue('B'.$i,  $horario)
			->setCellValue('C'.$i,  strtoupper($clientes))
			;
			
	//para mostrar los valores
			$total_servicios=0;
			$d=$i;
			$sql_servicios=mysqli_query($con, "SELECT ps.nombre_producto as producto, dpf.precio_producto as precio, dpf.cant_producto as cantidad FROM detalle_por_facturar dpf, productos_servicios ps WHERE dpf.id_referencia= '".$id_alumno."' and dpf.ruc_empresa='".$ruc_empresa."' and dpf.id_producto=ps.id and cuando_facturar='".$periodo_alumno_pc."' ");
			while ($row_servicios=mysqli_fetch_array($sql_servicios)){
	        $nombre_producto = $row_servicios['producto'];
			$precio_producto = $row_servicios['precio'] * $row_servicios['cantidad'];
			$total_servicios+=$precio_producto;
			
			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('D'.$d, strtoupper($nombre_producto))
			->setCellValue('E'.$d, $precio_producto)
			;
			
			$d=$d+1;
			
			$objPHPExcel->setActiveSheetIndex(0)
			->setCellValue('D'.$d, "TOTAL POR COBRAR")
			->setCellValue('E'.$d, $total_servicios)
			;
						
			}
				
			
			$i=$d+1;
}
		
								
			for($i = 'A'; $i <= 'F'; $i++){
				$objPHPExcel->setActiveSheetIndex(0)			
					->getColumnDimension($i)->setAutoSize(TRUE);
			}
			
			// Se asigna el nombre a la hoja
			$objPHPExcel->getActiveSheet()->setTitle('Por cobrar');

			// Se activa la hoja para que sea la que se muestre cuando el archivo se abre
			$objPHPExcel->setActiveSheetIndex(0);
			// Inmovilizar paneles 
			//$objPHPExcel->getActiveSheet(0)->freezePane('A4');
			$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0,6);

			// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="ReporteAlumnosPorCobrar.xlsx"');
			header('Cache-Control: max-age=0');

			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
			$objWriter->save('php://output');
			exit;
			
		}else{
			echo('No hay resultados para mostrar');
		}
}else {
			$errors []= "Error desconocido.";
		}
	
?>