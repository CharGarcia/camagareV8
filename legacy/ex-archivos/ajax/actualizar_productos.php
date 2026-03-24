<?php
include("../conexiones/conectalogin.php");
require("../excel/lib/PHPExcel/PHPExcel/IOFactory.php");
require_once("../helpers/helpers.php");
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$con = conenta_login();

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//boton de cargar archivo 
if ($action == 'actualizar_productos_servicios') {
	$nombre_archivo = $_FILES['archivo']['name'];
	$archivo_guardado = $_FILES['archivo']['tmp_name'];

	$directorio = '../docs_temp/'; //Declaramos un  variable con la ruta donde guardaremos los archivos
	$dir = opendir($directorio); //Abrimos el directorio de destino
	$target_path = $directorio . '/actualizar_productos.xlsx';

	$imageFileType = pathinfo($nombre_archivo, PATHINFO_EXTENSION);

	if ($imageFileType == "xlsx") {

		if (move_uploaded_file($archivo_guardado, $target_path)) {
			$objPHPExcel = PHPExcel_IOFactory::load('../docs_temp/actualizar_productos.xlsx');
			$objPHPExcel->setActiveSheetIndex(0);
			$numRows = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
			//para guardar los clientes

			$guardado = array();
			$mensajes = array();
			for ($p = 2; $p <= $numRows; $p++) {
				$codigo_producto=$objPHPExcel->getActiveSheet()->getCell('A'.$p)->getCalculatedValue();
				$auxiliar=$objPHPExcel->getActiveSheet()->getCell('B'.$p)->getCalculatedValue();
				$nombre_producto=$objPHPExcel->getActiveSheet()->getCell('C'.$p)->getCalculatedValue();
				$precio_producto=number_format($objPHPExcel->getActiveSheet()->getCell('D'.$p)->getCalculatedValue(),6,'.','');
				$tipo=$objPHPExcel->getActiveSheet()->getCell('E'.$p)->getCalculatedValue();
				$tarifa_iva=$objPHPExcel->getActiveSheet()->getCell('F'.$p)->getCalculatedValue();
				$unidad_medida=$objPHPExcel->getActiveSheet()->getCell('G'.$p)->getCalculatedValue();
		

				$busca_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE ruc_empresa= '".$ruc_empresa."' and codigo_producto = '".$codigo_producto."' ");
				//$row_producto = mysqli_fetch_array($busca_producto);
				//$producto_encontrado=$row_producto['codigo_producto'];
				$count_producto = mysqli_num_rows($busca_producto);
	
					if (empty($codigo_producto)) {
						$mensajes[] = "Fila " . $p . " No hay código de producto";
					} else if (empty($nombre_producto)){
						$mensajes[] = "Fila " . $p . " No hay nombre de producto";
					} else if (empty($precio_producto)){
						$mensajes[] = "Fila " . $p . " No hay precio de producto";
					} else if (empty($tipo)){
						$mensajes[] = "Fila " . $p . " No hay tipo de producto";
					} else if (empty($tarifa_iva)){
						$mensajes[] = "Fila " . $p . " No hay tarifa iva de producto";
					} else if (empty($unidad_medida)){
						$mensajes[] = "Fila " . $p . " No hay unidad de medida de producto";
					} else if ($count_producto==0){
						$mensajes[] = "Fila " . $p . " Producto no registrado en el sistema ";
					} else if (!is_numeric($precio_producto)){
						$mensajes[] = "Fila " . $p . " Ingrese un valor en precio ";
					}else{
						$update_producto =  mysqli_query($con, "UPDATE productos_servicios SET nombre_producto='".$nombre_producto."', codigo_auxiliar='".$auxiliar."', precio_producto='".$precio_producto."', tarifa_iva='".$tarifa_iva."', tipo_produccion='".$tipo."', id_unidad_medida ='".$unidad_medida."' WHERE ruc_empresa='".$ruc_empresa."' and codigo_producto='".$codigo_producto."' ");
						$guardado[] = 1;
					}	
										
			}
			if (array_sum($guardado) > 0) {
				unlink($target_path);
				echo "<script>
					$.notify('Los productso/servicios han sido actualizados.','success');
					</script>";
			} else {
				unlink($target_path);
				echo "<script>$.notify('No se actualizado los registros.','error');
				</script>";
			}
		}
	}
	//aqui termina la carga
	if (count($mensajes)>0) {
		echo mensaje_error($mensajes);
		}
}
?>
