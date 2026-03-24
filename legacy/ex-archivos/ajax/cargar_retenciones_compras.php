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
if ($action == 'cargar_retenciones_compras') {
	$nombre_archivo = $_FILES['archivo']['name'];
	$archivo_guardado = $_FILES['archivo']['tmp_name'];
    unset($_SESSION['arrayEncabezado']);
    unset($_SESSION['arrayDetalle']);

	$directorio = '../docs_temp/'; //Declaramos un  variable con la ruta donde guardaremos los archivos
	$dir = opendir($directorio); //Abrimos el directorio de destino
	$target_path = $directorio . '/retenciones_compras.xlsx';

	$imageFileType = pathinfo($nombre_archivo, PATHINFO_EXTENSION);

	if ($imageFileType == "xlsx") {

		if (move_uploaded_file($archivo_guardado, $target_path)) {
			$objPHPExcel = PHPExcel_IOFactory::load('../docs_temp/retenciones_compras.xlsx');
			$objPHPExcel->setActiveSheetIndex(0);
			$numRows = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
			//para guardar los proveedores

			$guardado = array();
			$mensajes = array();
			for ($p = 2; $p <= $numRows; $p++) {
				$dia_ret = $objPHPExcel->getActiveSheet()->getCell('A' . $p)->getCalculatedValue();
				$mes_ret = $objPHPExcel->getActiveSheet()->getCell('B' . $p)->getCalculatedValue();
				$anio_ret = $objPHPExcel->getActiveSheet()->getCell('C' . $p)->getCalculatedValue();
                $dia_doc = $objPHPExcel->getActiveSheet()->getCell('D' . $p)->getCalculatedValue();
				$mes_doc = $objPHPExcel->getActiveSheet()->getCell('E' . $p)->getCalculatedValue();
				$anio_doc = $objPHPExcel->getActiveSheet()->getCell('F' . $p)->getCalculatedValue();
				$fecha_validar_ret = array('0' => str_pad($dia_ret, 2, "00", STR_PAD_LEFT), "1" => str_pad($mes_ret, 2, "00", STR_PAD_LEFT), "2" => str_pad($anio_ret, 4, "0000", STR_PAD_LEFT));
				$fecha_ret = date('Y-m-d', strtotime(str_pad($anio_ret, 4, "0000", STR_PAD_LEFT) . "-" . str_pad($mes_ret, 2, "00", STR_PAD_LEFT) . "-" . str_pad($dia_ret, 2, "00", STR_PAD_LEFT)));
				$fecha_validar_doc = array('0' => str_pad($dia_doc, 2, "00", STR_PAD_LEFT), "1" => str_pad($mes_doc, 2, "00", STR_PAD_LEFT), "2" => str_pad($anio_doc, 4, "0000", STR_PAD_LEFT));
				$fecha_doc = date('Y-m-d', strtotime(str_pad($anio_doc, 4, "0000", STR_PAD_LEFT) . "-" . str_pad($mes_doc, 2, "00", STR_PAD_LEFT) . "-" . str_pad($dia_doc, 2, "00", STR_PAD_LEFT)));
                $ruc_cedula = $objPHPExcel->getActiveSheet()->getCell('G' . $p)->getCalculatedValue();
				$codigo_comprobante = $objPHPExcel->getActiveSheet()->getCell('H' . $p)->getCalculatedValue();
				$numero_retencion = $objPHPExcel->getActiveSheet()->getCell('I' . $p)->getCalculatedValue();
                $numero_documento = $objPHPExcel->getActiveSheet()->getCell('J' . $p)->getCalculatedValue();
				$aut_sri = $objPHPExcel->getActiveSheet()->getCell('K' . $p)->getCalculatedValue();
				$tipo_ret = $objPHPExcel->getActiveSheet()->getCell('L' . $p)->getCalculatedValue();
                $base_imponible = $objPHPExcel->getActiveSheet()->getCell('M' . $p)->getCalculatedValue();
				$codigo_impuesto = $objPHPExcel->getActiveSheet()->getCell('N' . $p)->getCalculatedValue();
				$porcentaje = $objPHPExcel->getActiveSheet()->getCell('O' . $p)->getCalculatedValue();
				$valor_retenido = $objPHPExcel->getActiveSheet()->getCell('P' . $p)->getCalculatedValue();
                $codigo_unico= $aut_sri;
                $ejercicio_fiscal=str_pad($mes_ret, 2, "00", STR_PAD_LEFT)."/".str_pad($anio_ret, 4, "0000", STR_PAD_LEFT);
				if (
					!empty($dia_ret) &&
					!empty($mes_ret) &&
					!empty($anio_ret) &&
                    !empty($dia_doc) &&
					!empty($mes_doc) &&
					!empty($anio_doc) &&
					!empty($ruc_cedula) &&
					!empty($codigo_comprobante)
                    && !empty($numero_retencion)
					&& !empty($numero_documento)
					&& !empty($aut_sri)
					&& !empty($tipo_ret)
					&& !empty($codigo_impuesto)
                    && !empty($base_imponible)
				) {

                    $sql_ret_existente_con_esta_empresa = mysqli_query($con, "SELECT * FROM encabezado_retencion WHERE aut_sri = '" . $aut_sri . "'");
                    $row_count_existente_con_esta_empresa = mysqli_num_rows($sql_ret_existente_con_esta_empresa);

                    $sql_busca_proveedor = mysqli_query($con, "SELECT * FROM proveedores WHERE ruc_proveedor= '" . $ruc_cedula . "' and ruc_empresa='" . $ruc_empresa . "' ");
                    $row_proveedores = mysqli_fetch_array($sql_busca_proveedor);
                    $id_proveedor = $row_proveedores['id_proveedor'];
                    $contar_proveedores = mysqli_num_rows($sql_busca_proveedor);

					if (validar_fecha($fecha_validar_ret) == false) {
						$mensajes[] = "Fila " . $p . " corregir fecha de retención";
                    }else if (validar_fecha($fecha_validar_doc) == false) {
                            $mensajes[] = "Fila " . $p . " corregir fecha del documento retenido";
					} else if ($contar_proveedores == 0) {
							$mensajes[] = "Fila " . $p . " proveedor no registrado, debe registrar primero el proveedor";
						} else if(strlen($numero_retencion) != 17){
								$mensajes[] = "Fila " . $p . " el número de retención debe tener el formato 000-000-000000000";
							} else if (strlen($numero_documento) != 17) {
								$mensajes[] = "Fila " . $p . " el número del documento retenido debe tener el formato 000-000-000000000";
							} else if ($row_count_existente_con_esta_empresa > 0) {
									$mensajes[] = "Fila " . $p . " Retención " . $numero_documento . " registrada anteriormente.";
                                } else if ($base_imponible <= 0) {
									$mensajes[] = "Fila " . $p . " La base imponible debe ser mayor a 0.";
								} else {
                                    $almacena_encabezado  = almacena_encabezado($codigo_unico, $fecha_ret, $fecha_doc, $id_proveedor, $codigo_comprobante, $numero_retencion, $numero_documento, $aut_sri, $valor_retenido);
                                    $almacena_detalle = almacena_detalle($codigo_unico, $tipo_ret, $base_imponible, $codigo_impuesto, $porcentaje, $valor_retenido, $ejercicio_fiscal);
								}
				} else {
					$mensajes[] = "En fila " . $p . " faltan datos sobre el registro.";
				}
				
			}

        //para guardar a la base
if (count($_SESSION['arrayEncabezado'])>0){
       foreach ($_SESSION['arrayEncabezado'] as $encabezado){
        $guarda_encabezado_retencion=mysqli_query($con, "INSERT INTO encabezado_retencion VALUES (null, '".$ruc_empresa."','".$encabezado['id_proveedor']."','".substr($encabezado['numero_retencion'],0,7)."','".intval(substr($encabezado['numero_retencion'],8,9))."','".number_format($encabezado['total_ret'], 2, '.', '')."','".$encabezado['aut_sri']."','AUTORIZADO','".$encabezado['fecha_retencion']."','".$encabezado['fecha_factura']."','".$id_usuario."','".$encabezado['codigo_comprobante']."','".$encabezado['numero_factura']."','0','0','2','ENVIADO')");
        $lastid = mysqli_insert_id($con);

            foreach ($_SESSION['arrayDetalle'] as $detalle){
                if ($encabezado['id']== $detalle['id']){
                    $guarda_detalle_retencion=mysqli_query($con, "INSERT INTO cuerpo_retencion VALUES (null,'".substr($encabezado['numero_retencion'],0,7)."','".intval(substr($encabezado['numero_retencion'],8,9))."','".$ruc_empresa."','".$lastid."','".$detalle['ejercicio_fiscal']."','".$detalle['base_imponible']."','".$detalle['codigo_ret']."','".$detalle['tipo_ret']."','".$detalle['porcentaje_ret']."','".$detalle['valor_ret']."','".$detalle['tipo_ret']." CÓDIGO ".$detalle['codigo_ret']."')");
                }    
            }
            $guardado[]=1;
    }
    
}

			if (array_sum($guardado) > 0) {
				unlink($target_path);
                unset($_SESSION['arrayEncabezado']);
                unset($_SESSION['arrayDetalle']);
				echo "<script>
					$.notify('Las retenciones han sido guardadas.','success');
					</script>";
			} else {
				unlink($target_path);
				echo "<script>$.notify('No se guardaron los registros.','error');
				</script>";
			}
		}
	}
	//aqui termina la carga 
    if (count($mensajes)>0) {
    echo mensaje_error($mensajes);
    }
    
}




function almacena_encabezado($codigo_unico, $fecha_retencion, $fecha_factura, $id_proveedor, $codigo_comprobante, 
$numero_retencion, $numero_factura, $aut_sri, $valor_ret){
    $arrayEncabezado = array();
    $arrayDatos = array('id' => $codigo_unico,
     'fecha_retencion' => $fecha_retencion, 
     'fecha_factura' => $fecha_factura,
     'id_proveedor'=> $id_proveedor,
     'codigo_comprobante'=> $codigo_comprobante,
     'numero_retencion'=> $numero_retencion,
     'numero_factura'=> $numero_factura,
     'aut_sri'=> $aut_sri,
     'total_ret'=> $valor_ret);

    if (isset($_SESSION['arrayEncabezado'])) {
        $on = true;
        $arrayEncabezado = $_SESSION['arrayEncabezado'];
        for ($pr = 0; $pr < count($arrayEncabezado); $pr++) {
            if ($arrayEncabezado[$pr]['aut_sri'] == $aut_sri) {
                $arrayEncabezado[$pr]['total_ret'] += $valor_ret;
                $on = false;
            }
        }
        if ($on) {
            array_push($arrayEncabezado, $arrayDatos);
        }
        $_SESSION['arrayEncabezado'] = $arrayEncabezado;
    } else {
        array_push($arrayEncabezado, $arrayDatos);
        $_SESSION['arrayEncabezado'] = $arrayEncabezado;
    }
}

function almacena_detalle($codigo_unico, $tipo_ret, $base_imponible, $codigo_ret, $porcentaje_ret, $valor_ret, $ejercicio_fiscal){
    $arrayDetalle = array();
    $arrayDatos = array('id' => $codigo_unico,
     'tipo_ret' => $tipo_ret, 
     'base_imponible' => $base_imponible,
     'codigo_ret'=>$codigo_ret,
     'porcentaje_ret'=> $porcentaje_ret,
     'valor_ret'=>$valor_ret,
     'ejercicio_fiscal'=>$ejercicio_fiscal);

    if (isset($_SESSION['arrayDetalle'])) {
        $arrayDetalle = $_SESSION['arrayDetalle'];
            array_push($arrayDetalle, $arrayDatos);
        $_SESSION['arrayDetalle'] = $arrayDetalle;
    } else {
        array_push($arrayDetalle, $arrayDatos);
        $_SESSION['arrayDetalle'] = $arrayDetalle;
    }
}

?>
