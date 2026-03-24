<?php
include("../conexiones/conectalogin.php");
require('../pdf/funciones_pdf.php');

include("../core/db.php");
$db = new db();
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario_actual = $_SESSION['id_usuario'];

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if (isset($_GET['action']) && isset($_GET['codigo_unico']) && $action == "orden_mecanica") {
	$codigo_unico = $_GET['codigo_unico'];
	$datos_encabezados = mysqli_query($con, "SELECT * FROM encabezado_mecanica as enc_mec LEFT JOIN clientes as cli ON enc_mec.id_cliente=cli.id LEFT JOIN vehiculos as vei ON enc_mec.codigo_unico=vei.codigo_unico LEFT JOIN observaciones_mecanica as obs_mec ON enc_mec.codigo_unico=obs_mec.codigo_unico WHERE enc_mec.codigo_unico= '" . $codigo_unico . "' and enc_mec.ruc_empresa='" . $ruc_empresa . "' ");
	$row_encabezados = mysqli_fetch_assoc($datos_encabezados);
	$fecha_recepcion = date("d-m-Y", strtotime($row_encabezados['fecha_recepcion']));
	$hora_recepcion = date("H:i", strtotime($row_encabezados['hora_recepcion']));
	$fecha_salida = date("d-m-Y", strtotime($row_encabezados['fecha_entrega']));
	$hora_salida = date("H:i", strtotime($row_encabezados['hora_entrega']));
	$id_cliente = $row_encabezados['id_cliente'];
	$id_usuario = $row_encabezados['id_usuario'];
	$numero_orden = $row_encabezados['numero_orden'];
	$estado = $row_encabezados['estado'];

	//para buscar la imagen
	$busca_imagen = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$datos_imagen = mysqli_fetch_assoc($busca_imagen);
	//$imagen = $datos_imagen['logo_sucursal'];
	$imagen = "../logos_empresas/" . $datos_imagen['logo_sucursal'];
	copy('../logos_empresas/' . $imagen, '../docs_temp/' . $ruc_empresa . '.jpg');

	//para sacar el total de la factura
	$busca_detalle_factura = mysqli_query($con, "SELECT * FROM detalle_factura_mecanica WHERE codigo_unico = '" . $codigo_unico . "' and ruc_empresa='" . $ruc_empresa . "'");
	$sutotal_a_pagar = array();
	$iva = array();
	//$total_a_pagar=0;
	while ($detalle_a_facturar = mysqli_fetch_array($busca_detalle_factura)) {
		$id_detalle = $detalle_a_facturar['id_detalle'];
		$id_producto = $detalle_a_facturar['id_producto'];
		$cantidad = $detalle_a_facturar['cantidad'];
		$precio = $detalle_a_facturar['precio'];
		$descuento = $detalle_a_facturar['descuento'];

		//buscar productos
		$busca_nombre_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id = '" . $id_producto . "' ");
		$row_productos = mysqli_fetch_array($busca_nombre_producto);
		$nombre_producto = $row_productos['nombre_producto'];
		$tarifa_iva = $row_productos['tarifa_iva'];

		//buscar tipos iva
		$busca_tarifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $tarifa_iva . "' ");
		$row_tarifa = mysqli_fetch_array($busca_tarifa_iva);
		$nombre_tarifa = $row_tarifa['tarifa'];
		$porcentaje_iva = $row_tarifa['porcentaje_iva'];
		$sutotal_a_pagar[] = (($cantidad * $precio) - $descuento);
		$iva[] = (($cantidad * $precio) - $descuento) * ($porcentaje_iva / 100);
	}
	$total_a_pagar = array_sum($sutotal_a_pagar) + array_sum($iva);

	$total_a_pagar = number_format($total_a_pagar, 2, '.', '');
	//para sacar el total de la factura


	$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
	$datos_empresa = mysqli_fetch_assoc($busca_empresa);
	$nombre_empresa = $datos_empresa['nombre_comercial'];
	$html_encabezado = '<p align="center">' . $nombre_empresa . '</p><br>
				  <p align="center">ORDEN DE SERVICIO</p><br>
				  <p align="left">ORDEN No.: ' . $numero_orden . '</p> <p align="right"> 
				  Estado: ' . $estado . '</p><br>';

	$html_total_factura = '<p align="left">----------------------------------------------------------------------------------------------------------------------- Total a pagar: ' . $total_a_pagar . '</p><br>';


	$pdf = new funciones_pdf('P', 'mm', 'A4'); //P
	$pdf->AliasNbPages();
	//$imagen_optimizada = $pdf->imagen_optimizada($imagen, '100', '100');//$width=1500, $height=1800
	//imagejpeg($imagen_optimizada, '../docs_temp/'.$ruc_empresa.'.jpg');
	$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
	$pdf->SetFont('Arial'); //esta tambien es importante
	$pdf->detalle_html($html_encabezado);
	//$pdf->Image('../docs_temp/'.$ruc_empresa.'.jpg', 10, 10, 70, 0, 'jpg', '');
	$pdf->Image('../docs_temp/' . $ruc_empresa . '.jpg', 10, 3, 45, 27, 'jpg', '');

	$prop = array('HeaderColor' => array(213, 219, 219), 'color1' => array(253, 254, 254), 'color2' => array(253, 254, 254), 'padding' => 2);
	$pdf->AddCol(utf8_decode('datos_vehiculo'), 190, utf8_decode('Datos del vehículo'), 'L');
	$pdf->Table($con, "SELECT CONCAT('Placa: ',UPPER(Placa), ' Marca: ', UPPER(marca), ' Modelo: ', anio, ' Chasis: ', UPPER(chasis), ' Propietario: ', UPPER(propietario)) as datos_vehiculo FROM vehiculos WHERE codigo_unico='" . $codigo_unico . "'", $prop, 'una_fila');

	$pdf->AddCol(utf8_decode('datos_cliente'), 190, utf8_decode('Datos de facturación'), 'L');
	$pdf->Table($con, "SELECT CONCAT('Nombre: ',nombre, ' Telf: ', telefono, ' Dir: ', direccion, ' mail: ', email) as datos_cliente FROM clientes WHERE id='" . $id_cliente . "'", $prop, 'una_fila');

	$pdf->AddCol(utf8_decode('datos_usuario'), 190, utf8_decode('Datos de usuario'), 'L');
	$pdf->Table($con, "SELECT CONCAT('Nombre: ', nombre_usuario,' Contacto: ', contacto_usuario, ' email: ', correo_usuario) as datos_usuario FROM encabezado_mecanica WHERE codigo_unico='" . $codigo_unico . "'", $prop, 'una_fila');

	$pdf->AddCol(utf8_decode('datos_fechas'), 190, utf8_decode('Fecha de ingreso y entrega'), 'L');
	$pdf->Table($con, "SELECT CONCAT('Fecha entrada: ', DATE_FORMAT(fecha_recepcion,'%d-%m-%Y'),' Hora entrada: ', hora_recepcion, ' Fecha salida: ', DATE_FORMAT(fecha_entrega,'%d-%m-%Y'), ' Hora salida: ', hora_entrega) as datos_fechas FROM encabezado_mecanica WHERE codigo_unico='" . $codigo_unico . "'", $prop, 'una_fila');

	$pdf->AddCol(utf8_decode('datos_asesor'), 190, utf8_decode('Datos del asesor'), 'L');
	$pdf->Table($con, "SELECT CONCAT('Nombre: ', nombre,' Telf: ', telefono, ' Mail: ', mail) as datos_asesor FROM usuarios WHERE id='" . $id_usuario . "'", $prop, 'una_fila');

	$pdf->AddCol(utf8_decode('concepto_detalle'), 190, utf8_decode('Observaciones'), 'L');
	$pdf->Table($con, "SELECT CONCAT(concepto,': ', detalle) as concepto_detalle FROM observaciones_mecanica WHERE codigo_unico='" . $codigo_unico . "'", $prop, 'cascada');

	$pdf->Ln();
	$pdf->detalle_html('<p align="left">Detalle de servicios y productos</p><br>');
	$pdf->AddCol(utf8_decode('nombre_producto'), 110, utf8_decode('Descripción'), 'L');
	$pdf->AddCol(utf8_decode('cantidad'), 20, utf8_decode('Cant'), 'L');
	$pdf->AddCol(utf8_decode('precio'), 20, utf8_decode('Precio'), 'L');
	$pdf->AddCol(utf8_decode('descuento'), 20, utf8_decode('Desct'), 'L');
	$pdf->AddCol(utf8_decode('subtotal'), 20, utf8_decode('Subtotal'), 'L');
	$pdf->Table($con, "SELECT * FROM detalle_factura_mecanica as det_fac INNER JOIN productos_servicios as pro_ser ON det_fac.id_producto=pro_ser.id WHERE det_fac.codigo_unico='" . $codigo_unico . "'", $prop, 'cascada');
	$pdf->detalle_html($html_total_factura);


	$pdf->Ln();
	$pdf->AddCol(utf8_decode('proxima_cita'), 190, utf8_decode('Próximo chequeo y recomendaciones'), 'L');
	$pdf->Table($con, "SELECT CONCAT('Fecha: ', DATE_FORMAT(proximo_chequeo,'%d-%m-%Y'),' Recomendaciones: ', obs_prox_chequeo) as proxima_cita FROM encabezado_mecanica WHERE codigo_unico='" . $codigo_unico . "'", $prop, 'una_fila');


	$pdf->Ln();
	$pdf->Ln();
	$pdf->detalle_html('<p align="center"></p><hr>');
	$pdf->detalle_html('<p align="center">FIRMA DE PROPIETARIO O APODERADO</p><br>');

	$pdf->Output("Orden N. " . $numero_orden . ".pdf", "D");
	unlink('../docs_temp/' . $ruc_empresa . '.jpg');
}
