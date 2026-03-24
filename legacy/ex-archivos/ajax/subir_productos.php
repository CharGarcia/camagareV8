<?php
include("../conexiones/conectalogin.php");
require "../excel/lib/PHPExcel/PHPExcel/IOFactory.php";
include("../helpers/helpers.php");
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$con = conenta_login();
//$codigo_unico=codigo_unico(20);

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//boton de cargar archivo 
if ($action == 'archivo_excel_productos') {
	$nombre_archivo = $_FILES['archivo']['name'];
	$archivo_guardado = $_FILES['archivo']['tmp_name'];

	$directorio = '../docs_temp/'; //Declaramos un  variable con la ruta donde guardaremos los archivos
	$dir = opendir($directorio); //Abrimos el directorio de destino
	$target_path = $directorio . '/productos.xlsx';

	$imageFileType = pathinfo($nombre_archivo, PATHINFO_EXTENSION);

	if ($imageFileType == "xlsx") {

		if (move_uploaded_file($archivo_guardado, $target_path)) {
			$objPHPExcel = PHPExcel_IOFactory::load('../docs_temp/productos.xlsx');
			$objPHPExcel->setActiveSheetIndex(0);
			$numRows = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
?>
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table table-hover">
						<tr class="info">
							<th>Código</th>
							<th>Auxiliar</th>
							<th>Nombre</th>
							<th>Precio sin iva</th>
							<th>Tipo</th>
							<th>Tarifa IVA</th>
							<th>Id unidad</th>
							<th>Observaciones</th>
						</tr>
						<?php
						$estado_registro = array();

						for ($i = 2; $i <= $numRows; $i++) {
							$codigo_producto = strClean($objPHPExcel->getActiveSheet()->getCell('A' . $i)->getCalculatedValue());
							$auxiliar = strClean($objPHPExcel->getActiveSheet()->getCell('B' . $i)->getCalculatedValue());
							$nombre_producto = strClean($objPHPExcel->getActiveSheet()->getCell('C' . $i)->getCalculatedValue());
							$precio_producto = number_format($objPHPExcel->getActiveSheet()->getCell('D' . $i)->getCalculatedValue(), 6, '.', '');
							$tipo = $objPHPExcel->getActiveSheet()->getCell('E' . $i)->getCalculatedValue();
							$tarifa_iva = $objPHPExcel->getActiveSheet()->getCell('F' . $i)->getCalculatedValue();
							$unidad_medida = $objPHPExcel->getActiveSheet()->getCell('G' . $i)->getCalculatedValue();


							//$fecha_vence=date('Y-m-d', strtotime($objPHPExcel->getActiveSheet()->getCell('F'.$i)->getCalculatedValue()));
							//comprobar si existe ese producto de esa empresa en el sistema
							$busca_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE ruc_empresa= '" . $ruc_empresa . "' and codigo_producto = '" . $codigo_producto . "' ");
							$row_producto = mysqli_fetch_array($busca_producto);
							$producto_encontrado = $row_producto['codigo_producto'];

							if (!empty($auxiliar)) {
								$busca_auxiliar = mysqli_query($con, "SELECT * FROM productos_servicios WHERE ruc_empresa = '" . $ruc_empresa . "' and codigo_auxiliar='" . $auxiliar . "' ");
								$count_auxiliar = mysqli_num_rows($busca_auxiliar);

								if ($count_auxiliar > 0) {
									$estado_registro[] = 1;
									$mensaje_cero = " Código auxiliar ya existe // ";
								} else {
									$estado_registro[] = 0;
								}
							} else {
								$estado_registro[] = 0;
							}


							if ($producto_encontrado == $codigo_producto) {
								$estado_registro[] = 1;
								$mensaje_uno = " Código ya existe // ";
							} else {
								$estado_registro[] = 0;
							}

							if ($tipo == "01" || $tipo == "02") {
								$estado_registro[] = 0;
							} else {
								$estado_registro[] = 1;
								$mensaje_dos = " El tipo solo puede ser 01 o 02 // ";
							}

							//comprobar si existe tarifa iva
							$busca_tarifa_iva = mysqli_query($con, "SELECT count(codigo) FROM tarifa_iva WHERE codigo='" . $tarifa_iva . "'");
							$total_tarifa = mysqli_num_rows($busca_tarifa_iva);

							if ($total_tarifa == 1) {
								$estado_registro[] = 0;
							} else {
								$estado_registro[] = 1;
								$mensaje_tres = " Comprobar tipo de tarifa 0= 0%, 2=12%, 6= no obj, 7=exento // ";
							}
							//comprobar si es numero la cantidad entrada

							if ($precio_producto < 0 || !is_numeric($precio_producto)) { //|| !is_numeric($cantidad_entrada)
								$estado_registro[] = 1;
								$mensaje_cuatro = " precio con error // ";
							} else {
								$estado_registro[] = 0;
							}

							//para comprobar si existen esas unidades de medidas
							$busca_medidas = mysqli_query($con, "SELECT count(id_medida) FROM unidad_medida WHERE id_medida='" . $unidad_medida . "'");
							$total_medidas = mysqli_num_rows($busca_medidas);

							if ($total_medidas == 1) {
								$estado_registro[] = 0;
							} else {
								$estado_registro[] = 1;
								$mensaje_cinco = " Comprobar medida 17=unidad // ";
							}


							$suma_estados = array_sum($estado_registro);

							if ($suma_estados > 0) {
								$estado_final = $mensaje_cero . $mensaje_uno . $mensaje_dos . $mensaje_tres . $mensaje_cuatro . $mensaje_cinco;
							} else {
								$estado_final = "";
							}


						?>
							<tr>
								<td><?php echo $codigo_producto; ?></td>
								<td><?php echo $auxiliar; ?></td>
								<td><?php echo $nombre_producto; ?></td>
								<td><?php echo $precio_producto; ?></td>
								<td><?php echo $tipo; ?></td>
								<td><?php echo $tarifa_iva; ?></td>
								<td><?php echo $unidad_medida; ?></td>
								<td><?php echo $estado_final; ?></td>
							</tr>
						<?php

							$datos_procesados[] = array('codigo_producto' => $codigo_producto, 'codigo_auxiliar' => $auxiliar, 'nombre_producto' => $nombre_producto, 'precio_producto' => $precio_producto, 'tipo' => $tipo, 'tarifa_iva' => $tarifa_iva, 'unidad_medida' => $unidad_medida);
						}

						if ($suma_estados == 0) {
							$total_registros = count($datos_procesados) - 1;
							ini_set('date.timezone', 'America/Guayaquil');
							$fecha_registro = date('Y-m-d H:i:s');
							$total_guardadas = array();
							for ($g = 0; $g <= $total_registros; $g++) {
								$guardar_registros = mysqli_query($con, "INSERT INTO productos_servicios VALUES (null,'" . $ruc_empresa . "','" . $datos_procesados[$g]['codigo_producto'] . "','" . $datos_procesados[$g]['nombre_producto'] . "','" . $datos_procesados[$g]['codigo_auxiliar'] . "','" . $datos_procesados[$g]['precio_producto'] . "','" . $datos_procesados[$g]['tipo'] . "','" . $datos_procesados[$g]['tarifa_iva'] . "','0','','" . $fecha_registro . "','" . $datos_procesados[$g]['unidad_medida'] . "','1','" . $id_usuario . "')");
								$total_guardadas[] = 1;
							}

							$suma_registros = array_sum($total_guardadas);

							if ($suma_registros > 0) {
								echo "<script>
						var total_registros = '$suma_registros';
						$.notify(total_registros+' producto(s) han sido guardados.','success');
						setTimeout(function (){location.href ='../modulos/cargar_productos.php'}, 2000);
						</script>";
							} else {
								echo "<script>$.notify('No se registró en productos.','error');
						</script>";
							}
						} else {
							echo "<script>$.notify($suma_estados+' registros con error, revisar en la columna oservaciones.','error');
						</script>";
						}


						?>
					</table>
				</div>
			</div>
	<?php


		} else {
			$errors[] = "El archivo no se pudo cargar.";
		}

		closedir($dir);
	} else {
		$errors[] = "El archivo $nombre_archivo no es de tipo excel. <br>";
	}
}

if (isset($errors)) {

	?>
	<div class="alert alert-danger" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>Error!</strong>
		<?php
		foreach ($errors as $error) {
			echo $error;
		}
		?>
	</div>
<?php
}
if (isset($messages)) {

?>
	<div class="alert alert-success" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>¡Bien hecho!</strong>
		<?php
		foreach ($messages as $message) {
			echo $message;
		}
		?>
	</div>
<?php
}
?>