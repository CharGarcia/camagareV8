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
if ($action == 'cargar_proveedores') {
	$nombre_archivo = $_FILES['archivo']['name'];
	$archivo_guardado = $_FILES['archivo']['tmp_name'];

	$directorio = '../docs_temp/'; //Declaramos un  variable con la ruta donde guardaremos los archivos
	$dir = opendir($directorio); //Abrimos el directorio de destino
	$target_path = $directorio . '/proveedores.xlsx';

	$imageFileType = pathinfo($nombre_archivo, PATHINFO_EXTENSION);

	if ($imageFileType == "xlsx") {

		if (move_uploaded_file($archivo_guardado, $target_path)) {
			$objPHPExcel = PHPExcel_IOFactory::load('../docs_temp/proveedores.xlsx');
			$objPHPExcel->setActiveSheetIndex(0);
			$numRows = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
			//para guardar los proveedores

			$guardado=array();
			$mensajes = array();
			for ($p = 2; $p <= $numRows; $p++) {
				$ruc_cedula = $objPHPExcel->getActiveSheet()->getCell('C' . $p)->getCalculatedValue();
				$nombre_proveedor = $objPHPExcel->getActiveSheet()->getCell('A' . $p)->getCalculatedValue();
				$nombre_comercial = $objPHPExcel->getActiveSheet()->getCell('B' . $p)->getCalculatedValue();
				$correo = $objPHPExcel->getActiveSheet()->getCell('D' . $p)->getCalculatedValue();
				$direccion = $objPHPExcel->getActiveSheet()->getCell('E' . $p)->getCalculatedValue();
				$telefono = $objPHPExcel->getActiveSheet()->getCell('F' . $p)->getCalculatedValue();
				$plazo = $objPHPExcel->getActiveSheet()->getCell('G' . $p)->getCalculatedValue();

				if (!empty($ruc_cedula) && !empty($nombre_proveedor) && !empty($nombre_comercial) && !empty($correo) && !empty($direccion) && !empty($telefono) && !empty($plazo)) {
					
					$sql_proveedores_existente_con_esta_empresa = mysqli_query($con, "SELECT * FROM proveedores WHERE ruc_empresa = '" . $ruc_empresa . "' and ruc_proveedor = '" . $ruc_cedula . "' ");
					$row_count_existente_con_esta_empresa = mysqli_num_rows($sql_proveedores_existente_con_esta_empresa);

					$sql_proveedores_existente_con_otra_empresa = mysqli_query($con, "SELECT * FROM proveedores WHERE ruc_empresa != '" . $ruc_empresa . "' and ruc_proveedor = '" . $ruc_cedula . "' ");
					$row_count_existente_con_otra_empresa = mysqli_num_rows($sql_proveedores_existente_con_otra_empresa);

					if ($row_count_existente_con_esta_empresa > 0) {
						$mensajes[] = "Proveedor " . $nombre_proveedor . " registrado anteriormente.";
					} else	if ($row_count_existente_con_otra_empresa > 0) {
						$row_proveedores_existente_con_otra_empresa = mysqli_fetch_array($sql_proveedores_existente_con_otra_empresa);
						$tipo_id_proveedor = $row_proveedores_existente_con_otra_empresa['tipo_id_proveedor'];
						$tipo_empresa = $row_proveedores_existente_con_otra_empresa['tipo_empresa'];

						$query_guarda_proveedor_con_otra_empresa = mysqli_query($con, "INSERT INTO proveedores ( 
						razon_social, 
						nombre_comercial, 
						ruc_empresa, 
						tipo_id_proveedor, 
						ruc_proveedor, 
						mail_proveedor, 
						dir_proveedor, 
						telf_proveedor, 
						tipo_empresa, 
						fecha_agregado, 
						plazo, 
						unidad_tiempo, 
						relacionado) 
						VALUES ('" . strClean($nombre_proveedor) . "',
						 '" . strClean($nombre_comercial) . "',
						  '" . $ruc_empresa . "',
						   '" . $tipo_id_proveedor . "',
						    '" . $ruc_cedula . "',
							 '" . strClean($correo) . "',
							  '" . strClean($direccion) . "',
							   '" . strClean($telefono) . "',
							    '" . $tipo_empresa . "',
								'" . date("Y-m-d H:i:s") . "',
								  '" . $plazo . "',
								  'Días',
								   '1')");
						//$mensajes[] = " Proveedor " . $nombre_proveedor . " registrado ahora.";

						if($query_guarda_proveedor_con_otra_empresa){
							$guardado[]=1;
						}else{
							$mensajes[] = " Proveedor " . $nombre_proveedor . " no se guardó.";
						}
					
					}else if (($row_count_existente_con_esta_empresa + $row_count_existente_con_otra_empresa) == 0) {
						$tipo_id_proveedor = strlen($ruc_cedula) == 13 ? "04" : "05";
						if ($tipo_id_proveedor == "05") {
							$digito_verificador = substr($ruc_cedula, 2, 1);
							switch ($digito_verificador) {
								case "9":
									$tipo_empresa = "03";
									break;
								case "6":
									$tipo_empresa = "05";
									break;
								case "0":
									$tipo_empresa = "02";
									break;
								case "1":
									$tipo_empresa = "02";
									break;
								case "2":
									$tipo_empresa = "02";
									break;
								case "3":
									$tipo_empresa = "02";
									break;
								case "4":
									$tipo_empresa = "02";
									break;
								case "5":
									$tipo_empresa = "02";
									break;
								case "7":
									$tipo_empresa = "02";
									break;
								case "8":
									$tipo_empresa = "02";
									break;
							}
						} else {
							$tipo_empresa = "01";
						}

						$query_guarda_proveedor_nuevo = mysqli_query($con, "INSERT INTO proveedores ( 
					razon_social, 
					nombre_comercial, 
					ruc_empresa, 
					tipo_id_proveedor, 
					ruc_proveedor, 
					mail_proveedor, 
					dir_proveedor, 
					telf_proveedor, 
					tipo_empresa, 
					fecha_agregado, 
					plazo, 
					unidad_tiempo, 
					relacionado) 
					VALUES ('" . strClean($nombre_proveedor) . "',
					 '" . strClean($nombre_comercial) . "',
					  '" . $ruc_empresa . "',
					   '" . $tipo_id_proveedor . "',
						'" . $ruc_cedula . "',
						 '" . strClean($correo) . "',
						  '" . strClean($direccion) . "',
						   '" . strClean($telefono) . "',
							'" . $tipo_empresa . "',
							'" . date("Y-m-d H:i:s") . "',
							  '" . $plazo . "',
							  'Días',
							   '1')");

							   if($query_guarda_proveedor_nuevo){
								$guardado[]=1;
							}else{
								$mensajes[] = " Proveedor " . $nombre_proveedor . " no se guardó.";
							}

					}
				} else {
					$mensajes[] = " Proveedor " . $nombre_proveedor . " faltan datos del proveedor.";
				}
			}
			//setTimeout(function (){location.href ='../modulos/cargar_proveedores.php'}, 2000);
			if (array_sum($guardado)>0) {
				unlink($target_path);
				echo "<script>
					$.notify('Los proveedores han sido guardados.','success');
					</script>";
			} else {
				unlink($target_path);
				echo "<script>$.notify('No se guardaron los proveedores.','error');
				</script>";
			}
		}
	}
	//aqui termina la carga de proveedores
	if (count($mensajes)>0) {
		echo mensaje_error($mensajes);
		}
}

?>
