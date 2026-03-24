<?php
//include("../conexiones/conectalogin.php");
include("../ajax/imprime_documento.php");
require_once('../helpers/helpers.php');

class enviar_documentos_sri
{
	public function envia_mail($id_documento, $tipo_documento, $mail_receptor)
	{
		$con = conenta_login();
		//session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];

		//datos de mi empresa
		$busca_datos_empresa = "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "' ";
		$result_empresa = $con->query($busca_datos_empresa);
		$datos_empresa = mysqli_fetch_array($result_empresa);
		$nombre_comercial = utf8_decode($datos_empresa['nombre_comercial']);
		$razon_social = utf8_decode($datos_empresa['nombre']);
		$fecha_hoy = date_create(date("Y-m-d H:i:s"));

		$servidor = "internet"; //aqui se cambia local o internet

		switch ($tipo_documento) {
			case "factura":
				$tabla = "encabezado_" . $tipo_documento;
				$where = "WHERE id_encabezado_" . $tipo_documento;
				break;
			case "retencion":
				$tabla = "encabezado_" . $tipo_documento;
				$where = "WHERE id_encabezado_" . $tipo_documento;
				break;
			case "nc":
				$tabla = "encabezado_" . $tipo_documento;
				$where = "WHERE id_encabezado_" . $tipo_documento;
				break;
			case "gr":
				$tabla = "encabezado_" . $tipo_documento;
				$where = "WHERE id_encabezado_" . $tipo_documento;
				break;
			case "liquidacion":
				$tabla = "encabezado_" . $tipo_documento;
				$where = "WHERE id_encabezado_liq";
				break;
			case "egreso":
				$tabla = "ingresos_egresos";
				$where = "WHERE id_ing_egr";
				break;
			case "ingreso":
				$tabla = "ingresos_egresos";
				$where = "WHERE id_ing_egr";
				break;
			case "cxc_individual":
				$tabla = "saldo_porcobrar_porpagar";
				$where = "WHERE id_saldo";
				break;
			case "cxc_todos":
				$tabla = "saldo_porcobrar_porpagar";
				$where = "WHERE id_cli_pro ";
				break;
			case "solicitar_retencion":
				$tabla = "encabezado_factura";
				$where = "WHERE id_encabezado_factura";
				break;
			case "quincena":
				$tabla = "detalle_quincena";
				$where = "WHERE id";
				break;
			case "rol_pagos":
				$tabla = "detalle_rolespago";
				$where = "WHERE id";
				break;
			case "aprobacion_compra":
				$tabla = "notificaciones_adquisiciones";
				$where = "WHERE id";
				break;
			case "respuesta_compra":
				$tabla = "notificaciones_adquisiciones";
				$where = "WHERE id";
				break;
			case "retorno_consignacion":
				$tabla = "encabezado_consignacion";
				$where = "WHERE id_consignacion";
				break;
			case "recibo_venta":
				$tabla = "encabezado_recibo";
				$where = "WHERE id_encabezado_recibo";
				break;
		}
		//consulta datos de los encabezados
		$busca_datos_encabezados = "SELECT * FROM $tabla $where = '" . $id_documento . "' ";
		$resultado_encabezado = $con->query($busca_datos_encabezados);
		$datos_encabezados = mysqli_fetch_array($resultado_encabezado);
		//para sacar las consultas de variables dependiendo de cada tipo de documento consulta en encabezado_
		switch ($tipo_documento) {
			case "factura":
				$serie = $datos_encabezados['serie_factura'];
				$aut_sri = $datos_encabezados['aut_sri'];
				$secuencial = $datos_encabezados['secuencial_factura'];
				$id_cliente = $datos_encabezados['id_cliente'];
				$tipo_comprobante = "FACTURA";
				$valor_total = $datos_encabezados['total_factura'];
				$correo_asunto = "Factura electrónica";
				break;
			case "retencion":
				$serie = $datos_encabezados['serie_retencion'];
				$aut_sri = $datos_encabezados['aut_sri'];
				$secuencial = $datos_encabezados['secuencial_retencion'];
				$id_proveedor = $datos_encabezados['id_proveedor'];
				$tipo_comprobante = "RETENCIÓN";
				$valor_total = $datos_encabezados['total_retencion'];
				$correo_asunto = "Retención electrónica";
				break;
			case "nc":
				$serie = $datos_encabezados['serie_nc'];
				$aut_sri = $datos_encabezados['aut_sri'];
				$secuencial = $datos_encabezados['secuencial_nc'];
				$id_cliente = $datos_encabezados['id_cliente'];
				$tipo_comprobante = "NOTA DE CRÉDITO";
				$valor_total = $datos_encabezados['total_nc'];
				$correo_asunto = "Nota de crédito electrónica";
				break;
			case "gr":
				$serie = $datos_encabezados['serie_gr'];
				$aut_sri = $datos_encabezados['aut_sri'];
				$secuencial = $datos_encabezados['secuencial_gr'];
				$id_cliente = $datos_encabezados['id_cliente'];
				$id_transportista = $datos_encabezados['id_transportista'];
				$tipo_comprobante = "GUÍA DE REMISIÓN";
				$valor_total = "0.00";
				$correo_asunto = "Guía de remisión electrónica";
				break;
			case "liquidacion":
				$serie = $datos_encabezados['serie_liquidacion'];
				$aut_sri = $datos_encabezados['aut_sri'];
				$secuencial = $datos_encabezados['secuencial_liquidacion'];
				$id_proveedor = $datos_encabezados['id_proveedor'];
				$tipo_comprobante = "LIQUIDACIÓN DE COMPRA DE BIENES Y PRESTACIÓN DE SERVICIOS";
				$valor_total = $datos_encabezados['total_liquidacion'];
				$correo_asunto = "Liquidación de compras electrónica";
				break;
			case "egreso":
				$serie = "";
				$secuencial = $datos_encabezados['numero_ing_egr'];
				$id_proveedor = $datos_encabezados['id_cli_pro'];
				$beneficiario = $datos_encabezados['nombre_ing_egr'];
				$tipo_comprobante = "EGRESO";
				$valor_total = $datos_encabezados['valor_ing_egr'];
				$correo_asunto = "Nuevo pago procesado";
				break;
			case "ingreso":
				$serie = "";
				$secuencial = $datos_encabezados['numero_ing_egr'];
				$id_proveedor = $datos_encabezados['id_cli_pro'];
				$beneficiario = $datos_encabezados['nombre_ing_egr'];
				$tipo_comprobante = "INGRESO";
				$valor_total = $datos_encabezados['valor_ing_egr'];
				$correo_asunto = "Nuevo pago recibido";
				break;
			case "cxc_individual": //para enviar el reporte de cxc individual de cada facturas del mismo cliente
				$serie = "";
				$secuencial = $datos_encabezados['numero_documento'];
				$id_cliente = $datos_encabezados['id_cli_pro'];
				$cliente = $datos_encabezados['nombre_cli_pro'];
				$tipo_comprobante = "FACTURA PENDIENTE DE PAGO";
				$valor_total = $datos_encabezados['total_factura'] - $datos_encabezados['total_nc'] - $datos_encabezados['total_ing'] - $datos_encabezados['ing_tmp'] - $datos_encabezados['total_ret'];
				$correo_asunto = "Factura de venta pendiente de pago";
				break;
			case "cxc_todos": //para enviar el reporte de cxc de todas las facturas del mismo cliente
				$serie = "";
				$secuencial = "DETALLE ADJUNTO";
				$id_cliente = $datos_encabezados['id_cli_pro'];
				$cliente = $datos_encabezados['nombre_cli_pro'];
				$tipo_comprobante = "FACTURAS PENDIENTES DE PAGO";
				$busca_suma_saldo = mysqli_query($con, "SELECT * FROM saldo_porcobrar_porpagar WHERE id_cli_pro = '" . $id_documento . "' and ruc_empresa='" . $ruc_empresa . "' and tipo='POR_COBRAR' group by id_documento");
				$suma_total = 0;
				while ($datos_suma = mysqli_fetch_array($busca_suma_saldo)) {
					$suma_total += $datos_suma['total_factura'] - $datos_suma['total_nc'] - $datos_suma['total_ing'] - $datos_suma['ing_tmp'] - $datos_suma['total_ret'];
				}
				$valor_total = $suma_total;
				$correo_asunto = "Facturas de venta pendientes de pago";
				break;
			case "solicitar_retencion": //para solicitar retencion a un cliente
				$serie = $datos_encabezados['serie_factura'];
				$secuencial = $datos_encabezados['secuencial_factura'];
				$id_cliente = $datos_encabezados['id_cliente'];
				$tipo_comprobante = "RETENCIÓN PENDIENTE";
				$valor_total = "";
				$correo_asunto = "Retenciones Pendientes";
				break;
			case "quincena": //para enviar la quincena por mail
				$id_empleado = $datos_encabezados['id_empleado'];
				$id_quincena = $datos_encabezados['id_quincena'];
				$busca_datos_empleado = mysqli_query($con, "SELECT * FROM empleados WHERE id = '" . $id_empleado . "' ");
				$row_empleado = mysqli_fetch_array($busca_datos_empleado);
				$empleado = $row_empleado['nombres_apellidos'];
				$cedula = $row_empleado['documento'];
				$busca_datos_quincena = mysqli_query($con, "SELECT * FROM quincenas WHERE id= '" . $id_quincena . "' ");
				$row_quincena = mysqli_fetch_array($busca_datos_quincena);
				$mes_ano = $row_quincena['mes_ano'];
				$serie = "";
				$secuencial = $mes_ano;
				$tipo_comprobante = "QUINCENA";
				$valor_total = $datos_encabezados['arecibir'];
				$correo_asunto = "Quincena-" . $mes_ano . " - " . $empleado;
				break;
			case "rol_pagos": //para enviar la rol de pagos por mail
				$id_empleado = $datos_encabezados['id_empleado'];
				$id_rol = $datos_encabezados['id_rol'];
				$busca_datos_empleado = mysqli_query($con, "SELECT * FROM empleados WHERE id = '" . $id_empleado . "' ");
				$row_empleado = mysqli_fetch_array($busca_datos_empleado);
				$cedula = $row_empleado['documento'];
				$empleado = $row_empleado['nombres_apellidos'];
				$busca_datos_roles = mysqli_query($con, "SELECT * FROM rolespago WHERE id= '" . $id_rol . "' ");
				$row_rol = mysqli_fetch_array($busca_datos_roles);
				$mes_ano = $row_rol['mes_ano'];
				$serie = "";
				$secuencial = $mes_ano;
				$tipo_comprobante = "ROL DE PAGOS";
				$valor_total = $datos_encabezados['a_recibir'];
				$correo_asunto = "Rol_pagos-" . $mes_ano . " - " . $empleado;
				break;
			case "aprobacion_compra": //para enviar la nota sobre la compra y su aprobacion
				$id_proveedor = $datos_encabezados['id_proveedor'];
				$codigo_documento = $datos_encabezados['codigo_documento'];
				$observacion = $datos_encabezados['observacion'];
				$id_receptor = $datos_encabezados['id_receptor'];
				$id_usuario = $datos_encabezados['id_usuario'];
				$busca_datos_proveedor = mysqli_query($con, "SELECT * FROM proveedores WHERE id_proveedor = '" . $id_proveedor . "' ");
				$row_proveedor = mysqli_fetch_array($busca_datos_proveedor);
				$proveedor = $row_proveedor['razon_social'];

				$busca_usuario_receptor = mysqli_query($con, "SELECT * FROM usuarios WHERE id = '" . $id_receptor . "' ");
				$row_usuario_receptor = mysqli_fetch_array($busca_usuario_receptor);
				$mail_receptor = $row_usuario_receptor['mail'];
				$nombre_receptor = $row_usuario_receptor['nombre'];

				$busca_datos_documento = mysqli_query($con, "SELECT * FROM encabezado_compra as enc 
				INNER JOIN comprobantes_autorizados as doc  
				ON doc.id_comprobante=enc.id_comprobante WHERE enc.codigo_documento = '" . $codigo_documento . "' ");
				$row_documentos = mysqli_fetch_array($busca_datos_documento);
				$tipo_comprobante = $row_documentos['comprobante'] . " " . $datos_encabezados['numero_documento'];

				$busca_usuario_emisor = mysqli_query($con, "SELECT * FROM usuarios WHERE id = '" . $id_usuario . "' ");
				$row_usuario_emisor = mysqli_fetch_array($busca_usuario_emisor);
				$mail_emisor = $row_usuario_emisor['mail'];
				$nombre_emisor = $row_usuario_emisor['nombre'];
				$secuencial = "Proveedor: " . $proveedor;
				$detalle_cuerpo = "<b>Observación:</b> " . strtoupper($observacion) . "<br>";
				$detalle_cuerpo .= "<b>Enviado por:</b> " . $nombre_emisor . " - " . $mail_emisor . "<br>";
				$detalle_cuerpo .= "<b>Nota:</b> Para dar una contestación a esta notificación, ir al menu/adquisiciones/compras/detalle";
				$busca_detalle = mysqli_query($con, "SELECT * FROM cuerpo_compra WHERE codigo_documento = '" . $codigo_documento . "' ");
				$detalle_cuerpo .= '<table border><tr><th>Detalle</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th></tr>';
				while ($detalle = mysqli_fetch_array($busca_detalle)) {
					$detalle_cuerpo .= '<tr><td>' . $detalle['detalle_producto'] . '</td>';
					$detalle_cuerpo .= '<td>' . number_format($detalle['cantidad'], 2, '.', '') . '</td>';
					$detalle_cuerpo .= '<td>' . number_format($detalle['precio'], 2, '.', '') . '</td>';
					$detalle_cuerpo .= '<td>' . number_format($detalle['subtotal'], 2, '.', '') . '</td>';
				}
				$detalle_cuerpo .= '</table><br>';

				$correo_asunto = "Revisar observación de adquisición. Proveedor " . $proveedor . " Documento " . $datos_encabezados['numero_documento'];
				$valor_total = 0;
				break;
			case "respuesta_compra": //para enviar la nota sobre la compra y su aprobacion de respuesta
				$id_proveedor = $datos_encabezados['id_proveedor'];
				$codigo_documento = $datos_encabezados['codigo_documento'];
				$observacion = $datos_encabezados['observacion'];
				$respuesta = $datos_encabezados['respuesta'];
				$id_receptor = $datos_encabezados['id_receptor'];
				$id_usuario = $datos_encabezados['id_usuario'];
				$busca_datos_proveedor = mysqli_query($con, "SELECT * FROM proveedores WHERE id_proveedor = '" . $id_proveedor . "' ");
				$row_proveedor = mysqli_fetch_array($busca_datos_proveedor);
				$proveedor = $row_proveedor['razon_social'];

				$busca_usuario_receptor = mysqli_query($con, "SELECT * FROM usuarios WHERE id = '" . $id_receptor . "' ");
				$row_usuario_receptor = mysqli_fetch_array($busca_usuario_receptor);
				$mail_receptor = $row_usuario_receptor['mail'];
				$nombre_receptor = $row_usuario_receptor['nombre'];

				$busca_datos_documento = mysqli_query($con, "SELECT * FROM encabezado_compra as enc 
					INNER JOIN comprobantes_autorizados as doc  
					ON doc.id_comprobante=enc.id_comprobante WHERE enc.codigo_documento = '" . $codigo_documento . "' ");
				$row_documentos = mysqli_fetch_array($busca_datos_documento);
				$tipo_comprobante = $row_documentos['comprobante'] . " " . $datos_encabezados['numero_documento'];

				$busca_usuario_emisor = mysqli_query($con, "SELECT * FROM usuarios WHERE id = '" . $id_usuario . "' ");
				$row_usuario_emisor = mysqli_fetch_array($busca_usuario_emisor);
				$mail_emisor = $row_usuario_emisor['mail'];
				$nombre_emisor = $row_usuario_emisor['nombre'];

				$secuencial = "Proveedor: " . $proveedor;
				$detalle_cuerpo = "<b>Observación:</b> " . strtoupper($observacion) . "<br>";
				$detalle_cuerpo .= "<b>Respuesta:</b> " . strtoupper($respuesta) . "<br>";
				$detalle_cuerpo .= "<b>Solicitado por:</b> " . $nombre_emisor . " - " . $mail_emisor . "<br>";
				$detalle_cuerpo .= "<b>Respuesta por:</b> " . $nombre_receptor . " - " . $mail_receptor . "<br>";
				$detalle_cuerpo .= "<b>Nota:</b> Para aprobar la respuesta, ir al menu/aprobaciones/aprobar adquisiciones/detalle";
				$busca_detalle = mysqli_query($con, "SELECT * FROM cuerpo_compra WHERE codigo_documento = '" . $codigo_documento . "' ");
				$detalle_cuerpo .= '<table border><tr><th>Detalle</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th></tr>';
				while ($detalle = mysqli_fetch_array($busca_detalle)) {
					$detalle_cuerpo .= '<tr><td>' . $detalle['detalle_producto'] . '</td>';
					$detalle_cuerpo .= '<td>' . number_format($detalle['cantidad'], 2, '.', '') . '</td>';
					$detalle_cuerpo .= '<td>' . number_format($detalle['precio'], 2, '.', '') . '</td>';
					$detalle_cuerpo .= '<td>' . number_format($detalle['subtotal'], 2, '.', '') . '</td>';
				}
				$detalle_cuerpo .= '</table><br>';

				$correo_asunto = "Revisar respuesta sobre la observacion del Proveedor " . $proveedor . " y documento " . $datos_encabezados['numero_documento'];
				$valor_total = 0;
				break;
			case "retorno_consignacion": //para enviar el retorno de la consignacion por mail
				$id_consignacion = $datos_encabezados['id_consignacion'];
				$busca_datos_consignacion = mysqli_query($con, "SELECT * FROM encabezado_consignacion as con INNER JOIN clientes as cli on cli.id=con.id_cli_pro WHERE con.id_consignacion= '" . $id_consignacion . "' ");
				$row_consignacion = mysqli_fetch_array($busca_datos_consignacion);
				$numero_consignacion = $row_consignacion['numero_consignacion'];
				$nombre_receptor = $row_consignacion['nombre'];
				$serie = "";
				$secuencial = $numero_consignacion;
				$tipo_comprobante = "RETORNO CONSIGNACIÓN";
				$valor_total = 0;
				$correo_asunto = "Retorno-" . $numero_consignacion . " " . $nombre_receptor;
				break;
			case "recibo_venta":
				$serie = $datos_encabezados['serie_recibo'];
				$aut_sri = "";
				$secuencial = $datos_encabezados['secuencial_recibo'];
				$id_cliente = $datos_encabezados['id_cliente'];
				$tipo_comprobante = "RECIBO DE VENTA";
				$valor_total = $datos_encabezados['total_recibo'];
				$correo_asunto = "Recibo de venta";
				break;
		}

		if ($serie == "") {
			$condicion_serie = "";
		} else {
			$condicion_serie = "and serie=" . $serie;
		}
		//datos de la sucursal
		$busca_datos_sucursales = "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' $condicion_serie";
		$result_sucursales = $con->query($busca_datos_sucursales);
		$datos_sucursal = mysqli_fetch_array($result_sucursales);
		$nombre_sucursal = $datos_sucursal['nombre_sucursal'];

		if ($tipo_documento == "factura" || $tipo_documento == "nc" || $tipo_documento == "solicitar_retencion" || $tipo_documento == "recibo_venta") {
			//para buscar datos del cliente
			$busca_datos_cliente = "SELECT * FROM clientes WHERE id = '" . $id_cliente . "' ";
			$resultado_cliente = $con->query($busca_datos_cliente);
			$datos_cliente = mysqli_fetch_array($resultado_cliente);
			$ruc_cliente = $datos_cliente['ruc'];
			$nombre_receptor = $datos_cliente['nombre'];
		}
		//datos del proveedor
		if ($tipo_documento == "retencion" || $tipo_documento == "liquidacion") {
			$busca_datos_proveedor = "SELECT * FROM proveedores WHERE id_proveedor= '" . $id_proveedor . "' ";
			$resultado_proveedor = $con->query($busca_datos_proveedor);
			$datos_proveedor = mysqli_fetch_array($resultado_proveedor);
			$ruc_proveedor = $datos_proveedor['ruc_proveedor'];
			$nombre_receptor = $datos_proveedor['razon_social'];
		}

		//datos del egreso
		if ($tipo_documento == "egreso") {
			$nombre_receptor = $beneficiario;
		}

		//datos del egreso
		if ($tipo_documento == "ingreso") {
			$nombre_receptor = $beneficiario;
		}

		//datos de la cuenta por cobrar
		if ($tipo_documento == "cxc_individual" || $tipo_documento == "cxc_todos") {
			$nombre_receptor = $cliente;
		}

		//datos del transportista
		if ($tipo_documento == "gr") {
			//busca el documento en base al ruc del chofer
			$busca_datos_transportista = "SELECT * FROM clientes WHERE id = '" . $id_transportista . "' ";
			$resultado_transportista = $con->query($busca_datos_transportista);
			$datos_transportista = mysqli_fetch_array($resultado_transportista);
			$ruc_transportista = $datos_transportista['ruc'];

			//buscar el nombre del cliente a quien fue enviada la gr
			$busca_datos_cliente = "SELECT * FROM clientes WHERE id = '" . $id_cliente . "' ";
			$resultado_cliente = $con->query($busca_datos_cliente);
			$datos_cliente = mysqli_fetch_array($resultado_cliente);
			//$ruc_cliente =$datos_cliente['ruc'];
			$nombre_receptor = $datos_cliente['nombre'];
		}

		if ($tipo_documento == "quincena" || $tipo_documento == "rol_pagos") {
			$nombre_receptor = $empleado;
		}

		if ($tipo_documento == "aprobacion_compra") {
			$nombre_receptor = $nombre_receptor;
		}


		$carpeta_ftp = new imprime_documentos();
		//para ver las direcciones de donde estan los documentos electronicos
		switch ($tipo_documento) {
			case "factura":
				$pdf_a_enviar = $carpeta_ftp->copia_documento_tmp($servidor, 'facturas_autorizadas', $ruc_empresa, $ruc_cliente, 'FAC', $serie, $secuencial, '.pdf');
				$xml_a_enviar = $carpeta_ftp->copia_documento_tmp($servidor, 'facturas_autorizadas', $ruc_empresa, $ruc_cliente, 'FAC', $serie, $secuencial, '.xml');
				$numero_factura = $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT);
				$pdf = "http://64.225.69.65:8000/facturas_autorizadas/" . $ruc_empresa . "/" . $ruc_cliente . "/FAC" . $numero_factura . ".pdf";
				$xml = "http://64.225.69.65:8000/facturas_autorizadas/" . $ruc_empresa . "/" . $ruc_cliente . "/FAC" . $numero_factura . ".xml";
				break;
			case "retencion":
				$pdf_a_enviar = $carpeta_ftp->copia_documento_tmp($servidor, 'retenciones_autorizadas', $ruc_empresa, $ruc_proveedor, 'CR', $serie, $secuencial, '.pdf');
				$xml_a_enviar = $carpeta_ftp->copia_documento_tmp($servidor, 'retenciones_autorizadas', $ruc_empresa, $ruc_proveedor, 'CR', $serie, $secuencial, '.xml');
				$numero_ret = $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT);
				$pdf = "http://64.225.69.65:8000/retenciones_autorizadas/" . $ruc_empresa . "/" . $ruc_proveedor . "/CR" . $numero_ret . ".pdf";
				$xml = "http://64.225.69.65:8000/retenciones_autorizadas/" . $ruc_empresa . "/" . $ruc_proveedor . "/CR" . $numero_ret . ".xml";
				break;
			case "nc":
				$pdf_a_enviar = $carpeta_ftp->copia_documento_tmp($servidor, 'nc_autorizadas', $ruc_empresa, $ruc_cliente, 'NC', $serie, $secuencial, '.pdf');
				$xml_a_enviar = $carpeta_ftp->copia_documento_tmp($servidor, 'nc_autorizadas', $ruc_empresa, $ruc_cliente, 'NC', $serie, $secuencial, '.xml');
				$numero_nc = $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT);
				$pdf = "http://64.225.69.65:8000/nc_autorizadas/" . $ruc_empresa . "/" . $ruc_cliente . "/NC" . $numero_nc . ".pdf";
				$xml = "http://64.225.69.65:8000/nc_autorizadas/" . $ruc_empresa . "/" . $ruc_cliente . "/NC" . $numero_nc . ".xml";
				break;
			case "gr":
				$pdf_a_enviar = $carpeta_ftp->copia_documento_tmp($servidor, 'guias_autorizadas', $ruc_empresa, $ruc_transportista, 'GR', $serie, $secuencial, '.pdf');
				$xml_a_enviar = $carpeta_ftp->copia_documento_tmp($servidor, 'guias_autorizadas', $ruc_empresa, $ruc_transportista, 'GR', $serie, $secuencial, '.xml');
				$numero_gr = $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT);
				$pdf = "http://64.225.69.65:8000/guias_autorizadas/" . $ruc_empresa . "/" . $ruc_transportista . "/GR" . $numero_gr . ".pdf";
				$xml = "http://64.225.69.65:8000/guias_autorizadas/" . $ruc_empresa . "/" . $ruc_transportista . "/GR" . $numero_gr . ".xml";
				break;
			case "liquidacion":
				$pdf_a_enviar = $carpeta_ftp->copia_documento_tmp($servidor, 'liquidaciones_autorizadas', $ruc_empresa, $ruc_proveedor, 'LIQ', $serie, $secuencial, '.pdf');
				$xml_a_enviar = $carpeta_ftp->copia_documento_tmp($servidor, 'liquidaciones_autorizadas', $ruc_empresa, $ruc_proveedor, 'LIQ', $serie, $secuencial, '.xml');
				$numero_lc = $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT);
				$pdf = "http://64.225.69.65:8000/liquidaciones_autorizadas/" . $ruc_empresa . "/" . $ruc_proveedor . "/LIQ" . $numero_lc . ".pdf";
				$xml = "http://64.225.69.65:8000/liquidaciones_autorizadas/" . $ruc_empresa . "/" . $ruc_proveedor . "/LIQ" . $numero_lc . ".xml";
				break;
			case "quincena":
				$id_quincena = encrypt_decrypt("encrypt", $id_documento);
				$path = encrypt_decrypt("encrypt", "/var/www/html/sistema/docs_temp/"); //para ver la carpeta donde se va a descargar el pdf 
				$tipo_descarga = encrypt_decrypt("encrypt", "F");
				$id_empresa = $_SESSION['id_empresa'];
				$ruc_empresa = $_SESSION['ruc_empresa'];
				$id_usuario = $_SESSION['id_usuario'];
				echo pdf_quincena_individual($con, $id_quincena, $tipo_descarga, $path, $id_empresa, $id_usuario, $ruc_empresa);
				$local_file = "/var/www/html/sistema/docs_temp/" . 'Quincena-' . $cedula . "-" . $empleado . '_' . $mes_ano . '.pdf';
				$pdf_a_enviar = $local_file;
				$xml_a_enviar = "";
				copy($local_file, $pdf_a_enviar);
				break;
			case "rol_pagos":
				$id_quincena = encrypt_decrypt("encrypt", $id_documento);
				$path = encrypt_decrypt("encrypt", "/var/www/html/sistema/docs_temp/"); //para ver la carpeta donde se va a descargar el pdf 
				$tipo_descarga = encrypt_decrypt("encrypt", "F");
				$id_empresa = $_SESSION['id_empresa'];
				$ruc_empresa = $_SESSION['ruc_empresa'];
				$id_usuario = $_SESSION['id_usuario'];
				echo pdf_rol_individual($con, $id_quincena, $tipo_descarga, $path, $id_empresa, $id_usuario, $ruc_empresa);
				$local_file = "/var/www/html/sistema/docs_temp/" . 'Rol_pagos-' . $cedula . "-" . $empleado . '_' . $mes_ano . '.pdf';
				$pdf_a_enviar = $local_file;
				$xml_a_enviar = "";
				copy($local_file, $pdf_a_enviar);
				break;
			case "retorno_consignacion":
				$id_consignacion = encrypt_decrypt("encrypt", $id_documento);
				$path = encrypt_decrypt("encrypt", "/var/www/html/sistema/docs_temp/"); //para ver la carpeta donde se va a descargar el pdf 
				$tipo_descarga = encrypt_decrypt("encrypt", "F");
				$id_empresa = $_SESSION['id_empresa'];
				$ruc_empresa = $_SESSION['ruc_empresa'];
				$id_usuario = $_SESSION['id_usuario'];
				echo pdf_retorno_consignacion_venta($con, $id_consignacion, $tipo_descarga, $path, $id_empresa, $id_usuario, $ruc_empresa);
				$local_file = "/var/www/html/sistema/docs_temp/" . 'Retorno CV N-' . $secuencial . '.pdf';
				$pdf_a_enviar = $local_file;
				$xml_a_enviar = "";
				copy($local_file, $pdf_a_enviar);
				break;
		}

		//para el detalle del cuerpo del mail
		switch ($tipo_documento) {
			case "factura":
				$linea_tres = "Esta es una notificación automática de un documento tributario electrónico emitido por " . strtoupper($nombre_comercial) . "<p>";
				$linea_cinco = "Nro de Comprobante: " . $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT) . "<p>";
				$linea_cinco .= "Clave de acceso: " . $aut_sri . "<p>";
				$linea_siete = "Los detalles generales del comprobante pueden ser consultados en el archivo pdf adjunto en este correo." . "<p>";
				//$linea_siete .= '<a href="' . $pdf . '" title="Descargar pdf"><button style="border: none; border-radius: 5px; padding:10px 20px; background-color: #E14535; color: white;">Descargar PDF</button></a> <a href="' . $xml . '" title="Descargar xml"><button style="border: none; border-radius: 5px; padding:10px 20px; background-color: #908D8D; color: white;">Descargar XML</button></a>';
				$linea_ocho = "Esta factura fue generada desde www.CaMaGaRe.com" . "<p>";
				break;
			case "retencion":
				$linea_tres = "Esta es una notificación automática de un documento tributario electrónico emitido por " . strtoupper($nombre_comercial) . "<p>";
				$linea_cinco = "Nro de Comprobante: " . $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT) . "<p>";
				$linea_cinco .= "Clave de acceso: " . $aut_sri . "<p>";
				$linea_siete = "Los detalles generales del comprobante pueden ser consultados en el archivo pdf adjunto en este correo." . "<p>";
				//$linea_siete .= '<a href="' . $pdf . '" title="Descargar pdf"><button style="border: none; border-radius: 5px; padding:10px 20px; background-color: #E14535; color: white;">Descargar PDF</button></a> <a href="' . $xml . '" title="Descargar xml"><button style="border: none; border-radius: 5px; padding:10px 20px; background-color: #908D8D; color: white;">Descargar XML</button></a>';
				$linea_ocho = "Esta retención fue generada desde www.CaMaGaRe.com" . "<p>";
				break;
			case "nc":
				$linea_tres = "Esta es una notificación automática de un documento tributario electrónico emitido por " . strtoupper($nombre_comercial) . "<p>";
				$linea_cinco = "Nro de Comprobante: " . $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT) . "<p>";
				$linea_cinco .= "Clave de acceso: " . $aut_sri . "<p>";
				$linea_siete = "Los detalles generales del comprobante pueden ser consultados en el archivo pdf adjunto en este correo." . "<p>";
				//$linea_siete .= '<a href="' . $pdf . '" title="Descargar pdf"><button style="border: none; border-radius: 5px; padding:10px 20px; background-color: #E14535; color: white;">Descargar PDF</button></a> <a href="' . $xml . '" title="Descargar xml"><button style="border: none; border-radius: 5px; padding:10px 20px; background-color: #908D8D; color: white;">Descargar XML</button></a>';
				$linea_ocho = "Esta nota de crédito fue generada desde www.CaMaGaRe.com" . "<p>";
				break;
			case "gr":
				$linea_tres = "Esta es una notificación automática de un documento tributario electrónico emitido por " . strtoupper($nombre_comercial) . "<p>";
				$linea_cinco = "Nro de Comprobante: " . $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT) . "<p>";
				$linea_cinco .= "Clave de acceso: " . $aut_sri . "<p>";
				$linea_siete = "Los detalles generales del comprobante pueden ser consultados en el archivo pdf adjunto en este correo." . "<p>";
				//$linea_siete .= '<a href="' . $pdf . '" title="Descargar pdf"><button style="border: none; border-radius: 5px; padding:10px 20px; background-color: #E14535; color: white;">Descargar PDF</button></a> <a href="' . $xml . '" title="Descargar xml"><button style="border: none; border-radius: 5px; padding:10px 20px; background-color: #908D8D; color: white;">Descargar XML</button></a>';
				$linea_ocho = "Esta guía de remisión fue generada desde www.CaMaGaRe.com" . "<p>";
				break;
			case "liquidacion":
				$linea_tres = "Esta es una notificación automática de un documento tributario electrónico emitido por " . strtoupper($nombre_comercial) . "<p>";
				$linea_cinco = "Nro de Comprobante: " . $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT) . "<p>";
				$linea_cinco .= "Clave de acceso: " . $aut_sri . "<p>";
				$linea_siete = "Los detalles generales del comprobante pueden ser consultados en el archivo pdf adjunto en este correo." . "<p>";
				//$linea_siete .= '<a href="' . $pdf . '" title="Descargar pdf"><button style="border: none; border-radius: 5px; padding:10px 20px; background-color: #E14535; color: white;">Descargar PDF</button></a> <a href="' . $xml . '" title="Descargar xml"><button style="border: none; border-radius: 5px; padding:10px 20px; background-color: #908D8D; color: white;">Descargar XML</button></a>';
				$linea_ocho = "Esta liquidación de compras fue generada desde www.CaMaGaRe.com" . "<p>";
				break;
			case "egreso":
				$busca_encabezado_ingreso = mysqli_query($con, "SELECT * FROM ingresos_egresos WHERE id_ing_egr = '" . $id_documento . "' ");
				$encabezado_ingresos = mysqli_fetch_array($busca_encabezado_ingreso);
				$codigo_unico = $encabezado_ingresos['codigo_documento'];
				$busca_detalle = mysqli_query($con, "SELECT * FROM detalle_ingresos_egresos WHERE codigo_documento = '" . $codigo_unico . "' ");
				$busca_pagos = mysqli_query($con, "SELECT * FROM formas_pagos_ing_egr as fpei INNER JOIN formas_de_pago as fp ON fpei.codigo_forma_pago=fp.codigo_pago WHERE fpei.codigo_documento = '" . $codigo_unico . "' and fp.aplica_a='EGRESO'");
				$linea_siete = '<table border><tr><th>Detalle del pago</th><th>Valor</th></tr>';
				while ($detalle = mysqli_fetch_array($busca_detalle)) {
					$linea_siete .= '<tr><td>' . $detalle['detalle_ing_egr'] . '</td>';
					$linea_siete .= '<td>' . number_format($detalle['valor_ing_egr'], 2, '.', '') . '</td>';
				}
				$linea_siete .= '</table><br>';
				$linea_siete .= '<table border><tr><th>Forma de pago</th><th>Valor</th></tr>';
				while ($detalle_pago = mysqli_fetch_array($busca_pagos)) {
					$linea_siete .= '<tr><td>' . $detalle_pago['nombre_pago'] . '</td>';
					$linea_siete .= '<td>' . number_format($detalle_pago['valor_forma_pago'], 2, '.', '') . '</td>';
				}
				$linea_siete .= '</table>';
				$linea_tres = "Esta es una notificación automática de un documento emitido por " . strtoupper($nombre_comercial) . "<p>";
				$linea_cinco = "Nro de Comprobante: " . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT) . "<p>";
				$linea_ocho = "Este comprobante de egreso fue generado desde www.CaMaGaRe.com" . "<p>";
				break;
			case "ingreso":
				$busca_encabezado_ingreso = mysqli_query($con, "SELECT * FROM ingresos_egresos WHERE id_ing_egr = '" . $id_documento . "' ");
				$encabezado_ingresos = mysqli_fetch_array($busca_encabezado_ingreso);
				$codigo_unico = $encabezado_ingresos['codigo_documento'];
				$busca_detalle = mysqli_query($con, "SELECT * FROM detalle_ingresos_egresos WHERE codigo_documento = '" . $codigo_unico . "' ");
				$busca_pagos = mysqli_query($con, "SELECT * FROM formas_pagos_ing_egr WHERE codigo_documento = '" . $codigo_unico . "' ");
				$linea_siete = '';
				while ($detalle = mysqli_fetch_array($busca_detalle)) {
					$tipo_documento = $detalle['tipo_ing_egr'];

					switch ($tipo_documento) {
						case "CCXRC":
							$id_documento = str_ireplace('RV', '', $detalle['codigo_documento_cv']);
							$busca_detalle_recibo = mysqli_query($con, "SELECT * FROM encabezado_recibo as enc INNER JOIN cuerpo_recibo as cue ON cue.id_encabezado_recibo=enc.id_encabezado_recibo WHERE enc.id_encabezado_recibo  = '" . $id_documento . "' ");
							$linea_siete .= '<table border><tr><th>Cantidad</th><th>Detalle</th><th>Adicionales</th><th>Valor</th><th>Nro Recibo</th></tr><tr>';
							while ($detalle_recibo = mysqli_fetch_array($busca_detalle_recibo)) {
								$linea_siete .= '<tr>';
								$linea_siete .= '<td>' . number_format($detalle_recibo['cantidad'], 2, '.', '') . '</td>';
								$linea_siete .= '<td>' . $detalle_recibo['nombre_producto'] . '</td>';
								$linea_siete .= '<td>' . $detalle_recibo['adicional'] . '</td>';
								$linea_siete .= '<td>' . number_format($detalle_recibo['subtotal'], 2, '.', '') . '</td>';
								$linea_siete .= '<td>' . $detalle_recibo['serie_recibo'] . '-' . $detalle_recibo['secuencial_recibo'] . '</td>';
								$linea_siete .= '</tr>';
							}
							$linea_siete .= '</tr></table>';

							//adicionales recibo
							$busca_detalle_adicional_recibo = mysqli_query($con, "SELECT * FROM detalle_adicional_recibo WHERE id_encabezado_recibo  = '" . $id_documento . "' ");
							$linea_siete .= '<table border><tr><th>Concepto</th><th>Detalle</th></tr><tr>';
							while ($detalle_adicional = mysqli_fetch_array($busca_detalle_adicional_recibo)) {
								$linea_siete .= '<tr>';
								$linea_siete .= '<td>' . $detalle_adicional['adicional_concepto'] . '</td>';
								$linea_siete .= '<td>' . $detalle_adicional['adicional_descripcion'] . '</td>';
								$linea_siete .= '</tr>';
							}
							$linea_siete .= '</tr></table><br>';

							break;
						case "CCXCC":
							$id_documento = $detalle['codigo_documento_cv'];
							$busca_detalle_factura = mysqli_query($con, "SELECT * FROM encabezado_factura as enc INNER JOIN cuerpo_factura as cue ON cue.serie_factura=enc.serie_factura and cue.secuencial_factura=enc.secuencial_factura and cue.ruc_empresa=enc.ruc_empresa WHERE enc.id_encabezado_factura  = '" . $id_documento . "' ");
							$linea_siete .= '<table border><tr><th>Cantidad</th><th>Detalle</th><th>Adicionales</th><th>Valor</th><th>Nro Factura</th></tr><tr>';
							while ($detalle_factura = mysqli_fetch_array($busca_detalle_factura)) {
								$linea_siete .= '<tr>';
								$linea_siete .= '<td>' . number_format($detalle_factura['cantidad_factura'], 2, '.', '') . '</td>';
								$linea_siete .= '<td>' . $detalle_factura['nombre_producto'] . '</td>';
								$linea_siete .= '<td>' . $detalle_factura['tarifa_bp'] . '</td>';
								$linea_siete .= '<td>' . number_format($detalle_factura['subtotal_factura'], 2, '.', '') . '</td>';
								$linea_siete .= '<td>' . 'Factura ' . $detalle_factura['serie_factura'] . '-' . $detalle_factura['secuencial_factura'] . '</td>';
								$linea_siete .= '</tr>';
							}
							$linea_siete .= '</tr></table>';

							//adicionales factura
							$busca_detalle_adicional_recibo = mysqli_query($con, "SELECT * FROM detalle_adicional_factura as det INNER JOIN encabezado_factura as enc ON det.serie_factura=enc.serie_factura and det.secuencial_factura=enc.secuencial_factura and det.ruc_empresa=enc.ruc_empresa WHERE enc.id_encabezado_factura  = '" . $id_documento . "' ");
							$linea_siete .= '<table border><tr><th>Concepto</th><th>Detalle</th></tr><tr>';
							while ($detalle_adicional = mysqli_fetch_array($busca_detalle_adicional_recibo)) {
								$linea_siete .= '<tr>';
								$linea_siete .= '<td>' . $detalle_adicional['adicional_concepto'] . '</td>';
								$linea_siete .= '<td>' . $detalle_adicional['adicional_descripcion'] . '</td>';
								$linea_siete .= '</tr>';
							}
							$linea_siete .= '</tr></table><br>';
							break;
						default:
							// ✅ Si no es ninguno de los dos casos, imprime estos datos básicos
							$linea_siete .= '<table border><tr><th>Detalle</th><th>Valor</th></tr><tr>';
							$linea_siete .= '<td>' . $detalle['detalle_ing_egr'] . '</td>';
							$linea_siete .= '<td>' . number_format($detalle['valor_ing_egr'], 2, '.', '') . '</td>';
							$linea_siete .= '</tr></table><br>';
							break;
					}
				}

				//formas de cobro
				$linea_siete .= '<table border><tr><th>Forma de cobro</th><th>Tipo</th><th>Valor</th></tr><tr>';
				while ($detalle_pago = mysqli_fetch_array($busca_pagos)) {
					if ($detalle_pago['id_cuenta'] > 0) {
						$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa ='" . $detalle_pago['ruc_empresa'] . "' and cue_ban.status ='1'");
						$row_cuenta = mysqli_fetch_array($cuentas);
						$linea_siete .= '<td>' . $row_cuenta['cuenta_bancaria'] . '</td>';
						$linea_siete .= '<td>' .
							($detalle_pago['detalle_pago'] == 'D' ? 'Depósito' : ($detalle_pago['detalle_pago'] == 'T' ? 'Transferencia' : ''))
							. '</td>';
					} else {
						$query_cobros_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE id = '" . $detalle_pago['codigo_forma_pago'] . "' order by descripcion asc");
						$row_cobros_pagos = mysqli_fetch_array($query_cobros_pagos);
						$linea_siete .= '<td>' . $row_cobros_pagos['descripcion'] . '</td>';
						$linea_siete .= '<td></td>';
					}

					$linea_siete .= '<td>' . number_format($detalle_pago['valor_forma_pago'], 2, '.', '') . '</td>';
				}
				$linea_siete .= '</tr></table><br>';

				//detalle observaciones
				$linea_siete .= "Observaciones: " . $encabezado_ingresos['detalle_adicional'];

				$linea_tres = "Documento emitido por " . strtoupper($nombre_comercial) . "<p>";
				$linea_cinco = "Nro de Comprobante: " . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT) . "<p>";
				$linea_ocho = "";
				break;
			case "cxc_individual":
				$busca_encabezado_cxc = mysqli_query($con, "SELECT * FROM saldo_porcobrar_porpagar WHERE id_saldo = '" . $id_documento . "' ");
				$linea_siete = '<table border><tr><th>Fecha</th><th>Factura</th><th>Total</th><th>NC</th><th>Abonos</th><th>Retenciones</th><th>Saldo</th><th>DÍas</th></tr>';
				while ($detalle = mysqli_fetch_array($busca_encabezado_cxc)) {
					$saldo = $detalle['total_factura'] - $detalle['total_nc'] - $detalle['total_ing'] - $detalle['ing_tmp'] - $detalle['total_ret'];
					$fecha_vencimiento = date_create($detalle['fecha_documento']);
					$diferencia_dias = date_diff($fecha_hoy, $fecha_vencimiento);
					$total_dias = $diferencia_dias->format('%a');
					$linea_siete .= '<tr><td>' . date("d-m-Y", strtotime($detalle['fecha_documento'])) . '</td>';
					$linea_siete .= '<td>' . $detalle['numero_documento'] . '</td>';
					$linea_siete .= '<td align="right">' . number_format($detalle['total_factura'], 2, '.', '') . '</td>';
					$linea_siete .= '<td align="right">' . number_format($detalle['total_nc'], 2, '.', '') . '</td>';
					$linea_siete .= '<td align="right">' . number_format($detalle['total_ing'] + $detalle['ing_tmp'], 2, '.', '') . '</td>';
					$linea_siete .= '<td align="right">' . number_format($detalle['total_ret'], 2, '.', '') . '</td>';
					$linea_siete .= '<td align="right">' . number_format($saldo, 2, '.', '') . '</td>';
					$linea_siete .= '<td>' . $total_dias . '</td>';
				}
				$linea_siete .= '</table><br>';
				$linea_tres = "Esta es una notificación de cobro de un documento emitido por " . strtoupper($nombre_comercial) . "<p>";
				$linea_cinco = "Nro de Comprobante: " . $secuencial . "<p>";
				$linea_ocho = "Este documento fue generado desde www.CaMaGaRe.com" . "<p>";
				break;

			case "cxc_todos":
				$busca_encabezado_cxc = mysqli_query($con, "SELECT * FROM saldo_porcobrar_porpagar WHERE id_cli_pro = '" . $id_documento . "' and ruc_empresa='" . $ruc_empresa . "' and tipo='POR_COBRAR' group by id_documento");
				$linea_siete = '<table border><tr><th>No.</th><th>Fecha</th><th>Factura</th><th>Total</th><th>NC</th><th>Abonos</th><th>Retenciones</th><th>Saldo</th><th>D�as</th></tr>';
				$numero = 1;
				while ($detalle = mysqli_fetch_array($busca_encabezado_cxc)) {
					$saldo = $detalle['total_factura'] - $detalle['total_nc'] - $detalle['total_ing'] - $detalle['ing_tmp'] - $detalle['total_ret'];
					$fecha_vencimiento = date_create($detalle['fecha_documento']);
					$diferencia_dias = date_diff($fecha_hoy, $fecha_vencimiento);
					$total_dias = $diferencia_dias->format('%a');
					$linea_siete .= '<tr><td>' . $numero . '</td>';
					$linea_siete .= '<td>' . date("d-m-Y", strtotime($detalle['fecha_documento'])) . '</td>';
					$linea_siete .= '<td>' . $detalle['numero_documento'] . '</td>';
					$linea_siete .= '<td align="right">' . number_format($detalle['total_factura'], 2, '.', '') . '</td>';
					$linea_siete .= '<td align="right">' . number_format($detalle['total_nc'], 2, '.', '') . '</td>';
					$linea_siete .= '<td align="right">' . number_format($detalle['total_ing'] + $detalle['ing_tmp'], 2, '.', '') . '</td>';
					$linea_siete .= '<td align="right">' . number_format($detalle['total_ret'], 2, '.', '') . '</td>';
					$linea_siete .= '<td align="right">' . number_format($saldo, 2, '.', '') . '</td>';
					$linea_siete .= '<td>' . $total_dias . '</td>';
					$numero = $numero + 1;
				}
				$linea_siete .= '</table><br>';
				$linea_tres = "Esta es una notificación de cobro emitido por " . strtoupper($nombre_comercial) . "<p>";
				$linea_cinco = "Nro de Comprobante: DETALLE ADJUNTO<p>";
				$linea_ocho = "Este documento fue generado desde www.CaMaGaRe.com" . "<p>";
				break;
			case "solicitar_retencion":
				$linea_tres = "Solicitamos nos emita la retención pendiente de la factura " . $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT) . " emitida por " . strtoupper($nombre_comercial) . "<p>";
				$linea_cinco = "" . "<p>";
				$linea_siete = "" . "<p>";
				$linea_ocho = "Esta solicitud fue generada desde www.CaMaGaRe.com" . "<p>";
				break;
			case "quincena":
				$linea_tres = "" . "<p>";
				$linea_cinco = utf8_decode("Mes-año:") . $secuencial . "<p>";
				$linea_siete = "Detalle de la quincena adjunto en PDF" . "<p>";
				$linea_ocho = "Enviado desde www.CaMaGaRe.com" . "<p>";
				break;
			case "rol_pagos":
				$linea_tres = "" . "<p>";
				$linea_cinco = utf8_decode("Mes-año:") . $secuencial . "<p>";
				$linea_siete = "Detalle del rol adjunto en PDF" . "<p>";
				$linea_ocho = "Enviado desde www.CaMaGaRe.com" . "<p>";
				break;
			case "aprobacion_compra":
				$linea_tres = "Tiene una observación en un documento de adquisiciones que requiere de su atención" . "<p>";
				$linea_cinco = utf8_decode($secuencial) . "<p>";
				$linea_siete = $detalle_cuerpo . "<p>";
				$linea_ocho = "Enviado desde www.CaMaGaRe.com" . "<p>";
				break;
			case "respuesta_compra":
				$linea_tres = "Tiene una respuesta en un documento de adquisiciones que requiere de su atención" . "<p>";
				$linea_cinco = utf8_decode($secuencial) . "<p>";
				$linea_siete = $detalle_cuerpo . "<p>";
				$linea_ocho = "Enviado desde www.CaMaGaRe.com" . "<p>";
				break;
			case "retorno_consignacion":
				$linea_tres = "" . "<p>";
				$linea_cinco = "Retorno CV N:" . $secuencial . "<p>";
				$linea_siete = "ADJUNTO DETALLE DE RETORNO DE CONSIGNACIÓN EN PDF" . "<p>";
				$linea_ocho = "Enviado desde www.CaMaGaRe.com" . "<p>";
				break;
			case "recibo_venta":
				$linea_cinco = '<table border cellspacing="0" cellpadding="4">';
				// TÍTULO
				$linea_cinco .= '<tr>';
				$linea_cinco .= '<th colspan="4" align="center">RECIBO DE VENTA</th>';
				$linea_cinco .= '</tr>';
				// NÚMERO DE RECIBO
				$linea_cinco .= '<tr>';
				$linea_cinco .= '<td colspan="4" align="center">';
				$linea_cinco .= 'No: ' . $serie . '-' . str_pad($secuencial, 9, "0", STR_PAD_LEFT);
				$linea_cinco .= '</td>';
				$linea_cinco .= '</tr>';

				// ESPACIO
				$linea_cinco .= '<tr><td colspan="4">&nbsp;</td></tr>';

				// ENCABEZADO DETALLES
				$linea_cinco .= '<tr>';
				$linea_cinco .= '<th>Cant</th>';
				$linea_cinco .= '<th>Detalle</th>';
				$linea_cinco .= '<th align="right">V/U</th>';
				$linea_cinco .= '<th align="right">Total</th>';
				$linea_cinco .= '</tr>';

				// DETALLES DE PRODUCTO
				$busca_encabezado_y_detalle_recibo = mysqli_query($con, "
					SELECT * FROM encabezado_recibo as enc 
					INNER JOIN cuerpo_recibo as cr 
						ON cr.id_encabezado_recibo = enc.id_encabezado_recibo 
					WHERE enc.id_encabezado_recibo = '" . $id_documento . "' 
				");

				while ($detalle = mysqli_fetch_array($busca_encabezado_y_detalle_recibo)) {
					$linea_cinco .= '<tr>';
					$linea_cinco .= '<td>' . number_format($detalle['cantidad'], 2, '.', '') . '</td>';
					$linea_cinco .= '<td>' . htmlspecialchars($detalle['nombre_producto'], ENT_QUOTES, 'UTF-8') . '</td>';
					$linea_cinco .= '<td align="right">' . number_format($detalle['valor_unitario'], 2, '.', '') . '</td>';
					$linea_cinco .= '<td align="right">' . number_format($detalle['subtotal'], 2, '.', '') . '</td>';
					$linea_cinco .= '</tr>';
				}

				// ESPACIO
				$linea_cinco .= '<tr><td colspan="4">&nbsp;</td></tr>';

				// TOTAL RECIBO
				$linea_cinco .= '<tr>';
				$linea_cinco .= '<th colspan="3" align="right">Total recibo</th>';
				$linea_cinco .= '<td align="right">' . number_format($valor_total, 2, '.', '') . '</td>';
				$linea_cinco .= '</tr>';

				// ESPACIO
				$linea_cinco .= '<tr><td colspan="4">&nbsp;</td></tr>';

				// ADICIONALES ENCABEZADO
				$busca_adicionales_recibo = mysqli_query($con, "
					SELECT * FROM detalle_adicional_recibo 
					WHERE id_encabezado_recibo = '" . $id_documento . "' 
				");

				if (mysqli_num_rows($busca_adicionales_recibo) > 0) {
					$linea_cinco .= '<tr>';
					$linea_cinco .= '<th colspan="4" align="left">Adicionales</th>';
					$linea_cinco .= '</tr>';

					// Encabezados adicionales
					$linea_cinco .= '<tr>';
					$linea_cinco .= '<th colspan="2">Concepto</th>';
					$linea_cinco .= '<th colspan="2">Detalle</th>';
					$linea_cinco .= '</tr>';

					while ($detalle = mysqli_fetch_array($busca_adicionales_recibo)) {
						$linea_cinco .= '<tr>';
						$linea_cinco .= '<td colspan="2">' . htmlspecialchars($detalle['adicional_concepto'], ENT_QUOTES, 'UTF-8') . '</td>';
						$linea_cinco .= '<td colspan="2">' . htmlspecialchars($detalle['adicional_descripcion'], ENT_QUOTES, 'UTF-8') . '</td>';
						$linea_cinco .= '</tr>';
					}
				}

				$linea_cinco .= '</table><br>';
				break;
		}

		$info_correo = datos_correo($ruc_empresa, $con);
		$correo_host = $info_correo['correo_host'];
		$correo_pass = $info_correo['correo_pass'];;
		$correo_port = $info_correo['correo_port'];
		$asunto_base = $info_correo['correo_asunto'];
		$correo_remitente = $info_correo['correo_remitente'];

		if (validarCorreo($mail_receptor)) {

			include("../documentos_mail/phpmailer.php");
			include("../documentos_mail/smtp.php");
			include("../documentos_mail/exception.php");
			$email_user = $correo_remitente;
			$email_password = $correo_pass;
			$the_subject = mb_convert_encoding($asunto_base, "ISO-8859-1", "UTF-8") . ' - ' . mb_convert_encoding($correo_asunto, "ISO-8859-1", "UTF-8");
			$address_to = explode(', ', $mail_receptor);
			$from_name = $nombre_sucursal;
			$phpmailer = new \PHPMailer\PHPMailer\PHPMailer();


			$phpmailer->SMTPDebug = 0;
			$phpmailer->IsSMTP(); // use SMTP
			$phpmailer->SMTPAuth = true;
			$phpmailer->Host = $correo_host;
			$phpmailer->Username = $email_user;
			$phpmailer->Password = $email_password;
			$phpmailer->Port = $correo_port;
			$phpmailer->SMTPSecure = 'STARTTLS';

			$phpmailer->setFrom($phpmailer->Username, $from_name);
			for ($i = 0; $i < count($address_to); $i++) {
				$phpmailer->AddAddress($address_to[$i]);
			}
			$phpmailer->Subject = $the_subject;
			if (isset($pdf_a_enviar)) {
				$phpmailer->addAttachment($pdf_a_enviar);
			}
			if (isset($xml_a_enviar)) {
				$phpmailer->addAttachment($xml_a_enviar);
			}
			$phpmailer->Body .= "Estimado(a),";
			$phpmailer->Body .= "<p>" . mb_convert_encoding(strtoupper($nombre_receptor), "ISO-8859-1", "UTF-8") . "</p>";
			$phpmailer->Body .= "<p>" . mb_convert_encoding($linea_tres, "ISO-8859-1", "UTF-8");
			$phpmailer->Body .= "<p>" . "Tipo de Comprobante: " . mb_convert_encoding($tipo_comprobante, "ISO-8859-1", "UTF-8") . "<p>";
			$phpmailer->Body .= "<p>" . $linea_cinco;
			$phpmailer->Body .= "<p>" . $valor_total > 0 ? "Valor Total: " . $valor_total . "<p>" : "";
			$phpmailer->Body .= "<p>" . mb_convert_encoding($linea_siete, "ISO-8859-1", "UTF-8");
			$phpmailer->Body .= "<p>" . mb_convert_encoding($linea_ocho, "ISO-8859-1", "UTF-8");
			$phpmailer->Body .= "<p>" . "Atentamente," . "<p>";
			$phpmailer->Body .= "<p>" . strtoupper($razon_social) . "</p>";
			$phpmailer->IsHTML(true);
			//actualizar estado mail en cada encabezado_

			if ($phpmailer->Send()) {
				$query_update = mysqli_query($con, "UPDATE $tabla SET estado_mail='ENVIADO' $where = '" . $id_documento . "'");
				if (!empty($pdf_a_enviar)) {
					unlink($pdf_a_enviar);
				}
				if (!empty($xml_a_enviar)) {
					unlink($xml_a_enviar);
				}
?>
				<div class="alert alert-success" role="alert">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong><span class="glyphicon glyphicon-ok"></strong> <?php echo "Correo enviado."; ?>
				</div>
			<?php
			} else {
			?>
				<div class="alert alert-danger" role="alert">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong><span class="glyphicon glyphicon-remove"></strong></strong> No se puede enviar, intente otra vez. <?php echo $phpmailer->ErrorInfo ?>
				</div>
			<?php
			}
		} else {
			unlink($pdf_a_enviar);
			unlink($xml_a_enviar);
			?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				<strong><span class="glyphicon glyphicon-remove"></strong></strong> El correo no es válido, ingrese otro correo e intente de nuevo.
			</div>
<?php
		}
	}
}
?>