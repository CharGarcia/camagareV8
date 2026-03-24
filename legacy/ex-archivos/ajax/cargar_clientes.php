<?php
include("../conexiones/conectalogin.php");
require("../excel/lib/PHPExcel/PHPExcel/IOFactory.php");
require_once("../helpers/helpers.php");
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$con = conenta_login();
//$codigo_unico=codigo_unico(20);

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//boton de cargar archivo 
if ($action == 'cargar_clientes') {
	$nombre_archivo = $_FILES['archivo']['name'];
	$archivo_guardado = $_FILES['archivo']['tmp_name'];

	$directorio = '../docs_temp/'; //Declaramos un  variable con la ruta donde guardaremos los archivos
	$dir = opendir($directorio); //Abrimos el directorio de destino
	$target_path = $directorio . '/clientes.xlsx';

	$imageFileType = pathinfo($nombre_archivo, PATHINFO_EXTENSION);

	if ($imageFileType == "xlsx") {

		if (move_uploaded_file($archivo_guardado, $target_path)) {
			$objPHPExcel = PHPExcel_IOFactory::load('../docs_temp/clientes.xlsx');
			$objPHPExcel->setActiveSheetIndex(0);
			$numRows = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
		
			$guardado=array();
			$mensajes = array();
			for ($p = 2; $p <= $numRows; $p++) {
				$nombre_cliente = $objPHPExcel->getActiveSheet()->getCell('A' . $p)->getCalculatedValue();
				$tipo_id_cliente = $objPHPExcel->getActiveSheet()->getCell('B' . $p)->getCalculatedValue();
				$tipo_id_cliente = str_pad($tipo_id_cliente, 2, "0", STR_PAD_LEFT);
				$ruc_cedula = $objPHPExcel->getActiveSheet()->getCell('C' . $p)->getCalculatedValue();
				$correo = $objPHPExcel->getActiveSheet()->getCell('D' . $p)->getCalculatedValue();
				$direccion = $objPHPExcel->getActiveSheet()->getCell('E' . $p)->getCalculatedValue();
				$telefono = $objPHPExcel->getActiveSheet()->getCell('F' . $p)->getCalculatedValue();
				$plazo = $objPHPExcel->getActiveSheet()->getCell('G' . $p)->getCalculatedValue();

				if (!empty($ruc_cedula) && !empty($nombre_cliente) && !empty($tipo_id_cliente) && !empty($correo) && !empty($direccion) && !empty($telefono) && !empty($plazo)) {
					
					$sql_cliente_existente_con_esta_empresa = mysqli_query($con, "SELECT * FROM clientes WHERE ruc_empresa = '" . $ruc_empresa . "' and ruc = '" . $ruc_cedula . "' ");
					$row_count_existente_con_esta_empresa = mysqli_num_rows($sql_cliente_existente_con_esta_empresa);

					//$sql_cliente_existente_con_otra_empresa = mysqli_query($con, "SELECT * FROM clientes WHERE ruc_empresa != '" . $ruc_empresa . "' and ruc = '" . $ruc_cedula . "' ");
					//$row_count_existente_con_otra_empresa = mysqli_num_rows($sql_cliente_existente_con_otra_empresa);

					if ($row_count_existente_con_esta_empresa > 0) {
						$mensajes[] = "Cliente " . $nombre_cliente . " registrado anteriormente.";
					//} else	if ($row_count_existente_con_otra_empresa > 0) {
					} else{
						//$row_cliente_existente_con_otra_empresa = mysqli_fetch_array($sql_cliente_existente_con_otra_empresa);
						//$tipo_id_cliente = $row_cliente_existente_con_otra_empresa['tipo_id'];
						$query_guarda_cliente_con_otra_empresa = mysqli_query($con, "INSERT INTO clientes ( 
						ruc_empresa, 
						nombre, 
						tipo_id, 
						ruc, 
						telefono, 
						email, 
						direccion, 
						fecha_agregado, 
						plazo, 
						id_usuario, 
						provincia,
						ciudad) 
						VALUES ('" . $ruc_empresa . "',
						'" . strClean($nombre_cliente) . "',
						   '" . $tipo_id_cliente . "',
						    '" . $ruc_cedula . "',
							 '" . strClean($telefono) . "',
							  '" . strClean($correo) . "',
							   '" . strClean($direccion) . "',
								'" . date("Y-m-d H:i:s") . "',
								  '" . $plazo . "',
								  '" . $id_usuario . "',
								   '17',
								   '189')");

						if($query_guarda_cliente_con_otra_empresa){
							$guardado[]=1;
						}else{
							$mensajes[] = " Cliente " . $nombre_cliente . " no se guardó.";
						}
					/*
					}else if (($row_count_existente_con_esta_empresa + $row_count_existente_con_otra_empresa) == 0) {
						$tipo_id_cliente = strlen($ruc_cedula) == 13 ? "04" : "05";

						$query_guarda_cliente_nuevo = mysqli_query($con, "INSERT INTO clientes ( 
						ruc_empresa, 
						nombre, 
						tipo_id, 
						ruc, 
						telefono, 
						email, 
						direccion, 
						fecha_agregado, 
						plazo, 
						id_usuario, 
						provincia,
						ciudad) 
					VALUES ('" . $ruc_empresa . "',
						'" . strClean($nombre_cliente) . "',
						   '" . $tipo_id_cliente . "',
						    '" . $ruc_cedula . "',
							 '" . strClean($telefono) . "',
							  '" . strClean($correo) . "',
							   '" . strClean($direccion) . "',
								'" . date("Y-m-d H:i:s") . "',
								  '" . $plazo . "',
								  '" . $id_usuario . "',
								   '17',
								   '189')");

							   if($query_guarda_cliente_nuevo){
								$guardado[]=1;
							}else{
								$mensajes[] = " Cliente " . $nombre_cliente . " no se guardó.";
							}

					}

				*/
					}

				} else {
					$mensajes[] = " Cliente " . $nombre_cliente . " faltan datos del Cliente.";
				}
			}
			//setTimeout(function (){location.href ='../modulos/cargar_cliente.php'}, 2000);
			if (array_sum($guardado)>0) {
				unlink($target_path);
				echo "<script>
					$.notify('Los clientes han sido guardados.','success');
					</script>";
			} else {
				unlink($target_path);
				echo "<script>$.notify('No se guardaron los clientes.','error');
				</script>";
			}
		}
	}
	//aqui termina la carga de cliente
	if (count($mensajes)>0) {
		echo mensaje_error($mensajes);
		}
}

?>
