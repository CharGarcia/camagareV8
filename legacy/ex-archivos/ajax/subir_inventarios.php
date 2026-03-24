<?php
include("../conexiones/conectalogin.php");
require_once("../excel/lib/PHPExcel/PHPExcel/IOFactory.php");
include("../validadores/generador_codigo_unico.php");
require_once("../helpers/helpers.php");

session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

$con = conenta_login();
$codigo_unico = codigo_unico(20);

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//boton de cargar archivo 
if ($action == 'archivo_excel_inventarios') {
	ini_set('date.timezone', 'America/Guayaquil');
	$nombre_archivo = $_FILES['archivo']['name'];
	$archivo_guardado = $_FILES['archivo']['tmp_name'];

	$directorio = '../docs_temp/'; //Declaramos un  variable con la ruta donde guardaremos los archivos
	$dir = opendir($directorio); //Abrimos el directorio de destino
	$target_path = $directorio . $codigo_unico . '.xlsx';

	$imageFileType = pathinfo($nombre_archivo, PATHINFO_EXTENSION);

	if ($imageFileType == "xlsx") {

		if (move_uploaded_file($archivo_guardado, $target_path)) {
			$objPHPExcel = PHPExcel_IOFactory::load($target_path);
			$objPHPExcel->setActiveSheetIndex(0);
			$numRows = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
?>
			<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table table-hover">
						<tr class="info">
							<th>Código</th>
							<th>Producto</th>
							<th>Cantidad</th>
							<th>Tipo</th>
							<th>Bodega</th>
							<th>Vencimiento</th>
							<th>Lote</th>
							<th>Observación</th>
						</tr>
						<?php
						$estado_registro = array();
						for ($i = 2; $i <= $numRows; $i++) {
							$codigo_producto = strClean($objPHPExcel->getActiveSheet()->getCell('A' . $i)->getCalculatedValue());
							$busca_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE ruc_empresa= '" . $ruc_empresa . "' and codigo_producto = '" . $codigo_producto . "' ");
							$row_producto = mysqli_fetch_array($busca_producto);
							$id_producto = $row_producto['id'];

							if (!isset($id_producto)) {
								$estado_registro[] = "1";
								$mensaje1 = "Error en fila " . $i . " Producto no registrado.";
							} else {
								$estado_registro[] = "0";
								$mensaje1 = "";
							}

							$precio_producto = $row_producto['precio_producto'];
							$id_medida = $row_producto['id_unidad_medida'];
							$nombre_producto = $row_producto['nombre_producto'];

							$cantidad = $objPHPExcel->getActiveSheet()->getCell('C' . $i)->getCalculatedValue();
							$tipo = strClean(strtoupper($objPHPExcel->getActiveSheet()->getCell('D' . $i)->getCalculatedValue()));
							$dia = $objPHPExcel->getActiveSheet()->getCell('E' . $i)->getCalculatedValue();
							$mes = $objPHPExcel->getActiveSheet()->getCell('F' . $i)->getCalculatedValue();
							$anio = $objPHPExcel->getActiveSheet()->getCell('G' . $i)->getCalculatedValue();
							$fecha_vence_array = array('0' => str_pad($dia, 2, "00", STR_PAD_LEFT), "1" => str_pad($mes, 2, "00", STR_PAD_LEFT), "2" => str_pad($anio, 4, "0000", STR_PAD_LEFT));
							$referencia = strClean($objPHPExcel->getActiveSheet()->getCell('H' . $i)->getCalculatedValue());
							$id_bodega = $objPHPExcel->getActiveSheet()->getCell('I' . $i)->getCalculatedValue();
							$lote = $objPHPExcel->getActiveSheet()->getCell('J' . $i)->getCalculatedValue();
							$costo = $objPHPExcel->getActiveSheet()->getCell('K' . $i)->getCalculatedValue();


							//comprobar si existe la bodega
							$busca_bodega = mysqli_query($con, "SELECT count(id_bodega) as bodegas, nombre_bodega FROM bodega WHERE mid(ruc_empresa,1,10)= '" . substr($ruc_empresa, 0, 10) . "' and id_bodega = '" . $id_bodega . "' ");
							$row_bodega = mysqli_fetch_array($busca_bodega);
							$contar_bodega = $row_bodega['bodegas'];
							$nombre_bodega = $row_bodega['nombre_bodega'];

							if ($contar_bodega == 0) {
								$estado_registro[] = "1";
								$mensaje2 = "Error en fila " . $i . " La bodega no existe.";
								$nombre_bodega = $id_bodega;
							} else {
								$estado_registro[] = "0";
								$mensaje2 = "";
								$nombre_bodega = $nombre_bodega;
							}
							//comprobar si es numero la cantidad entrada

							if ($cantidad < 0 || !is_numeric($cantidad)) { //|| !is_numeric($cantidad_entrada)
								$estado_registro[] = "1";
								$mensaje3 = "Error en fila " . $i . " La cantidad no es un número.";
							} else {
								$estado_registro[] = "0";
								$mensaje3 = "";
							}

							//comprobar si tipo tiene la palabra entrada o salida
							if ($tipo == "ENTRADA" || $tipo == "SALIDA") {
								$estado_registro[] = "0";
								$mensaje4 = "";
							} else {
								$estado_registro[] = "1";
								$mensaje4 = "Error en fila " . $i . " El tipo debe ser ENTRADA O SALIDA.";
							}

							//comprobar si lote
							if ($lote == "") {
								$estado_registro[] = "1";
								$mensaje5 = "Error en fila " . $i . " Ingrese un lote.";
							} else {
								$estado_registro[] = "0";
								$mensaje5 = "";
							}
							//referencia
							if ($referencia == "") {
								$estado_registro[] = "1";
								$mensaje6 = "Error en fila " . $i . " Ingrese una referencia.";
							} else {
								$estado_registro[] = "0";
								$mensaje6 = "";
							}

							//comprobar si es fecha
							if ($tipo == "ENTRADA") {
								if (validar_fecha($fecha_vence_array) == false || comparar_fecha($fecha_vence_array) == false) {
									$estado_registro[] = "1";
									$mensaje7 = "Error en fila " . $i . " Ingrese una fecha de vencimiento correcta, que sea mayor a la fecha actual, formato dd-mm-aaaa.";
								} else {
									$estado_registro[] = "0";
									$mensaje7 = "";
								}
							} else {
								$mensaje7 = "";
							}

							//comprobar si es costo es valor

							if ($costo < 0 || !is_numeric($costo)) { //|| !is_numeric($cantidad_entrada)
								$estado_registro[] = "1";
								$mensaje8 = "Error en fila " . $i . " El valor de costo no es un número.";
							} else {
								$estado_registro[] = "0";
								$mensaje8 = "";
							}


							if (array_sum($estado_registro) == 0) {
								$estado_final = "";
							} else {
								$estado_final = $mensaje1 . $mensaje2  . $mensaje3  . $mensaje4  . $mensaje5  . $mensaje6  . $mensaje7 . $mensaje8;
							}

						?>
							<tr>
								<td><?php echo $codigo_producto; ?></td>
								<td><?php echo $nombre_producto; ?></td>
								<td><?php echo $cantidad; ?></td>
								<td><?php echo $tipo; ?></td>
								<td><?php echo $nombre_bodega; ?></td>
								<td><?php echo validar_fecha($fecha_vence_array) == true ? date('d-m-Y', strtotime(implode('-', $fecha_vence_array))) : "Fecha_incorrecta"; ?></td>
								<td><?php echo $lote; ?></td>
								<td><?php echo $estado_final; ?></td>
							</tr>
						<?php
						}


						if (array_sum($estado_registro) == 0) {
							//guardar a aprobaciones_inventario de invenartio el registro
							$fecha_registro = date("Y-m-d H:i:s");
							$guardar_registro = mysqli_query($con, "INSERT INTO aprobaciones_inventario VALUES (null,'" . $fecha_registro . "','','INVENTARIOS','" . $target_path . "','1','" . $id_usuario . "', '" . $ruc_empresa . "','','" . $referencia . "')");

							$datosMail = array(
								'receptor' => strtolower(datos_empresa($ruc_empresa, $con)['mail']),
								'template' => 'email_confirmar_carga_inventario',
								'empresa' => datos_empresa($ruc_empresa, $con)['nombre'],
								'emisor' => strtolower(datos_correo($ruc_empresa, $con)['correo_remitente']),
								'host' => datos_correo($ruc_empresa, $con)['correo_host'],
								'pass' => datos_correo($ruc_empresa, $con)['correo_pass'],
								'port' => datos_correo($ruc_empresa, $con)['correo_port'],
								'asunto' => 'Carga inventarios'
							);
							$sendEmail = sendEmail($datosMail);

							echo "<script>
					$.notify('El archivo se encuentra validado a la espera de su aprobación.','success');
					setTimeout(function (){location.href ='../modulos/cargar_inventario.php'}, 2000);
					</script>";
						} else {
							unlink($target_path);
							echo "<script>
					$.notify('Revisar los errores econtrados.','error');
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
?>