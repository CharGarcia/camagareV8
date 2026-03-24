<?php
//ini_set('display_errors', 1);
//error_reporting(E_ALL);
require "../facturacion_electronica/ProcesarComprobanteElectronico.php";
date_default_timezone_set('America/Guayaquil');

if (php_sapi_name() === 'cli') {
	$id_documento = isset($argv[1]) ? $argv[1] : null;
	$tipo_documento = isset($argv[2]) ? $argv[2] : null;
	$modo_envio = isset($argv[3]) ? $argv[3] : null;
} else {
	// Si los parámetros vienen por POST (desde una solicitud HTTP)
	$id_documento = isset($_POST['id_documento_sri']) ? $_POST['id_documento_sri'] : null;
	$tipo_documento = isset($_POST['tipo_documento_sri']) ? $_POST['tipo_documento_sri'] : null;
	$modo_envio = isset($_POST['modo_envio']) ? $_POST['modo_envio'] : null;
}


//para todos los documentos	
if (isset($id_documento) && isset($tipo_documento)) {
	include_once("../conexiones/conectalogin.php");
	include_once("../clases/lee_xml.php");
	$con = conenta_login();
	$envia_documento = new enviarComprobantesSri();

	switch ($tipo_documento) {
		case "factura":
			echo $envia_documento->EnviarFactura($con, $id_documento, $modo_envio);
			break;
		case "retencion":
			echo $envia_documento->EnviarRetencion($con, $id_documento, $modo_envio);
			break;
		case "nc":
			echo $envia_documento->EnviarNc($con, $id_documento, $modo_envio);
			break;
		case "gr":
			echo $envia_documento->EnviarGr($con, $id_documento, $modo_envio);
			break;
		/* case "nd":
			echo $envia_documento->EnviarNd($con, $id_documento, $modo_envio);
			break; */
		case "liquidacion":
			echo $envia_documento->EnviarLc($con, $id_documento, $modo_envio);
			break;
		case "proforma":
			echo $envia_documento->EnviarProforma($con, $id_documento, $modo_envio);
			break;
		case "compra":
			echo $envia_documento->EnviarFacturaCompra($con, $id_documento, $modo_envio);
			break;
	}
}


/**
 * Envia comprobantes a los servidores del SRI
 */
class enviarComprobantesSri
{

	public function config_app($con, $documento, $serie_sucursal, $ruc_empresa)
	{
		$configuracion_app = array();
		$servidor = "internet";
		if ($servidor == "internet") {
			$dir_firma = "/home/char/ftp_documentos/firma_digital/" . $this->confi_empresa($con, $ruc_empresa)['archivo_firma'];
			$dir_logo = "/home/char/ftp_documentos/logos_empresa/" . $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['logo_sucursal'];
			switch ($documento) {
				case "factura":
					$dir_documento = "/home/char/ftp_documentos/facturas_autorizadas/" . $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['ruc_empresa'] . "/";
					$configuracion_app = array('dir_firma' => $dir_firma, 'dir_logo' => $dir_logo, 'dir_documento' => $dir_documento);
					break;
				case "retencion":
					$dir_documento = "/home/char/ftp_documentos/retenciones_autorizadas/" . $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['ruc_empresa'] . "/";
					$configuracion_app = array('dir_firma' => $dir_firma, 'dir_logo' => $dir_logo, 'dir_documento' => $dir_documento);
					break;
				case "nc":
					$dir_documento = "/home/char/ftp_documentos/nc_autorizadas/" . $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['ruc_empresa'] . "/";
					$configuracion_app = array('dir_firma' => $dir_firma, 'dir_logo' => $dir_logo, 'dir_documento' => $dir_documento);
					break;
				case "gr":
					$dir_documento = "/home/char/ftp_documentos/guias_autorizadas/" . $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['ruc_empresa'] . "/";
					$configuracion_app = array('dir_firma' => $dir_firma, 'dir_logo' => $dir_logo, 'dir_documento' => $dir_documento);
					break;
				case "nd":
					$dir_documento = "/home/char/ftp_documentos/nd_autorizadas/" . $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['ruc_empresa'] . "/";
					$configuracion_app = array('dir_firma' => $dir_firma, 'dir_logo' => $dir_logo, 'dir_documento' => $dir_documento);
					break;
				case "liquidacion":
					$dir_documento = "/home/char/ftp_documentos/liquidaciones_autorizadas/" . $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['ruc_empresa'] . "/";
					$configuracion_app = array('dir_firma' => $dir_firma, 'dir_logo' => $dir_logo, 'dir_documento' => $dir_documento);
					break;
				case "proforma":
					$dir_documento = "/home/char/ftp_documentos/proformas_autorizadas/" . $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['ruc_empresa'] . "/";
					$configuracion_app = array('dir_firma' => $dir_firma, 'dir_logo' => $dir_logo, 'dir_documento' => $dir_documento);
					break;
				case "compra":
					$dir_documento = "/home/char/ftp_documentos/facturas_compras/" . $ruc_empresa . "/";
					$configuracion_app = array('dir_firma' => $dir_firma, 'dir_logo' => "/home/char/ftp_documentos/logos_empresa/notienelogo.jpg", 'dir_documento' => $dir_documento);
					break;
			}
		}
		$configApp = new \configAplicacion();
		$configApp->dirFirma = $configuracion_app['dir_firma'];
		$configApp->passFirma = $this->confi_empresa($con, $ruc_empresa)['pass_firma'];
		$configApp->dirAutorizados =  $configuracion_app['dir_documento'];
		$configApp->dirLogo = $configuracion_app['dir_logo'];
		return $configApp;
	}

	public function confi_mail($con, $modo_envio, $ruc_empresa)
	{
		//consultar informacion de sobre la configuracion del mail
		$configCorreo = new \configCorreo();
		$config = $this->confi_empresa($con, $ruc_empresa);
		$correo_asunto     = $config['correo_asunto'];
		$correo_host       = $config['correo_host'];
		$correo_pass       = $config['correo_pass'];
		$correo_port       = $config['correo_port'];
		$correo_remitente  = $config['correo_remitente'];
		$ssl_hab           = filter_var($config['ssl_hab'], FILTER_VALIDATE_BOOLEAN);
		$correo_empresa = $this->info_empresa($con, $ruc_empresa)['mail'];


		if ($modo_envio == 'offline') {
			$configCorreo->correoAsunto = "";
			$configCorreo->correoHost = "";
			$configCorreo->correoPass = "";
			$configCorreo->correoPort = "";
			$configCorreo->correoRemitente = "";
			$configCorreo->correoEmpresa = $correo_empresa;
			$configCorreo->sslHabilitado = false;
		}
		if ($modo_envio == 'online') {
			if (!empty($correo_asunto) && !empty($correo_host) && !empty($correo_pass) && !empty($correo_port) && !empty($correo_remitente)) {
				$configCorreo->correoAsunto = $correo_asunto;
				$configCorreo->correoHost = $correo_host;
				$configCorreo->correoPass = escapeshellarg($correo_pass);
				$configCorreo->correoPort = $correo_port;
				$configCorreo->correoRemitente = $correo_remitente;
				$configCorreo->correoEmpresa = $correo_empresa;
				$configCorreo->sslHabilitado = $ssl_hab;
			} else {
				$configCorreo->correoAsunto = $correo_asunto;
				$configCorreo->correoHost = "smtp.office365.com";
				$configCorreo->correoPass = "DOC2311*";
				$configCorreo->correoPort = "587";
				$configCorreo->correoRemitente = "documentos@camagare.com";
				$configCorreo->correoEmpresa = $correo_empresa;
				$configCorreo->sslHabilitado = false;
			}

			return $configCorreo;
		}
	}


	/* public function confi_empresa($con, $ruc_empresa)
	{
		//consultar informacion de la empresa
		$busca_confi_empresa = mysqli_query($con, "SELECT * FROM config_electronicos WHERE ruc_empresa = '" . $ruc_empresa . "' ");
		$datos = mysqli_fetch_array($busca_confi_empresa);
		return $datos;
	} */

	public function confi_empresa($con, $ruc_empresa)
	{
		// 1) Asegura que MySQL use UTF-8 completo (utf8mb4)
		mysqli_set_charset($con, 'utf8mb4');

		// 2) Escapa tu parámetro para evitar inyección (pero sin alterar los caracteres)
		$ruc = mysqli_real_escape_string($con, $ruc_empresa);

		// 3) Ejecuta la consulta y obtén el array asociativo
		$sql    = "SELECT * FROM config_electronicos WHERE ruc_empresa = '$ruc' LIMIT 1";
		$result = mysqli_query($con, $sql);
		if (! $result) {
			// manejo de error… opcionalmente lanzar excepción o devolver []
			return [];
		}

		$datos = mysqli_fetch_assoc($result) ?: [];
		mysqli_free_result($result);

		return $datos;
	}



	public function info_empresa($con, $ruc_empresa)
	{
		//consultar informacion de la empresa	
		$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
		$datos = mysqli_fetch_array($busca_empresa);
		return $datos;
	}

	public function info_sucursal($con, $serie_sucursal, $ruc_empresa)
	{
		//traer la informacion de la sucursal
		$busca_info_sucursal = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' and serie = '" . $serie_sucursal . "' ");
		$datos = mysqli_fetch_array($busca_info_sucursal);
		return $datos;
	}

	public function info_documento($con, $id_documento, $tipo)
	{
		switch ($tipo) {
			case "factura":
				$tabla = "encabezado_factura";
				$id_encabezado_documento = "id_encabezado_factura";
				break;
			case "retencion":
				$tabla = "encabezado_retencion";
				$id_encabezado_documento = "id_encabezado_retencion";
				break;
			case "nc":
				$tabla = "encabezado_nc";
				$id_encabezado_documento = "id_encabezado_nc";
				break;
			case "gr":
				$tabla = "encabezado_gr";
				$id_encabezado_documento = "id_encabezado_gr";
				break;
			case "nd":
				$tabla = "encabezado_nd";
				$id_encabezado_documento = "id_encabezado_nd";
				break;
			case "liquidacion":
				$tabla = "encabezado_liquidacion";
				$id_encabezado_documento = "id_encabezado_liq";
				break;
			case "proforma":
				$tabla = "encabezado_proforma";
				$id_encabezado_documento = "id_encabezado_proforma";
				break;
			case "compra":
				$tabla = "encabezado_compra";
				$id_encabezado_documento = "id_encabezado_compra";
				break;
		}
		$result_info_documento = mysqli_query($con, "SELECT * FROM $tabla WHERE $id_encabezado_documento = '" . $id_documento . "'");
		$datos = mysqli_fetch_array($result_info_documento);
		return $datos;
	}

	public function info_cliente($con, $id_cliente)
	{
		//traer informacion de la factura y cliente
		$busca_info_cliente = mysqli_query($con, "SELECT * FROM clientes WHERE id = '" . $id_cliente . "' ");
		$datos = mysqli_fetch_array($busca_info_cliente);
		return $datos;
	}

	public function info_proveedor($con, $id_proveedor)
	{
		$busca_info_proveedor = mysqli_query($con, "SELECT * FROM proveedores WHERE id_proveedor = '" . $id_proveedor . "' ");
		$datos = mysqli_fetch_array($busca_info_proveedor);
		return $datos;
	}

	public function confi_facturacion($con, $serie_sucursal, $ruc_empresa)
	{
		//traer informacion de la configuracion de la facturacion
		$confi_facturacion = mysqli_query($con, "SELECT * FROM configuracion_facturacion where ruc_empresa ='" . $ruc_empresa . "' and serie_sucursal ='" . $serie_sucursal . "' ");
		$datos = mysqli_fetch_array($confi_facturacion);
		return $datos;
	}



	//para enviar facturas
	public function EnviarFacturaCompra($con, $id_factura, $modo_envio)
	{
		$documento = 'compra';
		$ruc_empresa = $this->info_documento($con, $id_factura, $documento)['ruc_empresa'];
		include_once("../conexiones/conecta_ftp.php");
		$con_ftp = conecta_ftp();
		$carpeta_facturas_compras = '/ftp_documentos/facturas_compras/' . $ruc_empresa;
		ftp_mkdir($con_ftp, $carpeta_facturas_compras);
		ftp_chmod($con_ftp, 0777, $carpeta_facturas_compras);
		ftp_close($con_ftp);

		$serie_sucursal = ""; //$this->info_documento($con, $id_factura, "factura")['serie_factura'];
		$serie_factura = $this->info_documento($con, $id_factura, $documento)['numero_documento'];
		$numero_factura = $this->info_documento($con, $id_factura, $documento)['numero_documento'];
		$id_proveedor = $this->info_documento($con, $id_factura, $documento)['id_proveedor'];


		$factura = new factura();
		$factura->configAplicacion = $this->config_app($con, $documento, $serie_sucursal, $ruc_empresa);
		$factura->configCorreo = $this->confi_mail($con, $modo_envio, $ruc_empresa);
		$factura->ambiente = "2"; //[1,Prueba][2,Produccion] 
		$factura->tipoEmision = "1"; //[1,Emision Normal][2,Emision Por Indisponibilidad del sistema
		$factura->razonSocial = strtoupper(clear_cadena($this->info_proveedor($con, $id_proveedor)['razon_social'])); //[Razon Social]
		$factura->nombreComercial = strtoupper(clear_cadena($this->info_proveedor($con, $id_proveedor)['nombre_comercial']));  //[Nombre Comercial, si hay]*
		$factura->ruc = $this->info_proveedor($con, $id_proveedor)['ruc_proveedor']; //[Ruc]
		$factura->claveAcc = $this->info_documento($con, $id_factura, $documento)['aut_sri'];
		$factura->codDoc = "01"; //[01, Factura] [04, Nota Credito] [05, Nota Debito] [06, Guia Remision] [07, Retencion]
		$factura->establecimiento = substr($serie_factura, 0, 3); //[pto de emision ] **
		$factura->ptoEmision = substr($serie_factura, 4, 3); // [Numero Establecimiento SRI] 001-001
		$factura->secuencial = substr($numero_factura, -9); // [Secuencia desde 1 (9)]
		$factura->fechaEmision = date("d/m/Y", strtotime($this->info_documento($con, $id_factura, $documento)['fecha_compra'])); //[Fecha (dd/mm/yyyy)]
		$factura->dirMatriz = strtoupper($this->info_proveedor($con, $id_proveedor)['dir_proveedor']); //[Direccion de la Matriz ->SRI]
		$factura->dirEstablecimiento = strtoupper($this->info_proveedor($con, $id_proveedor)['dir_proveedor']); //[Direccion de Establecimiento ->SRI]
		$factura->contribuyenteEspecial = "";
		$factura->regimenRIMPE = "";
		$factura->regimenRIMPENP = "";
		$factura->agenteRetencion = "";

		$tipo_empresa = $this->info_proveedor($con, $id_proveedor)['tipo_empresa'];
		switch ($tipo_empresa) {
			case "01":
				$lleva_contabilidad = "NO";
				break;
			case "02" || "03" || "04" || "05":
				$lleva_contabilidad = "SI";
				break;
			default:
				$lleva_contabilidad = "NO";
		};

		switch ($this->info_documento($con, $id_factura, "compra")['deducible_en']) {
			case '04':
				$NumIdComprador = substr($ruc_empresa, 0, 10) . '001';
				break;
			case '05':
				$NumIdComprador = substr($ruc_empresa, 0, 10);
				break;
			default:
				$NumIdComprador = $ruc_empresa;
		}

		$factura->obligadoContabilidad = $lleva_contabilidad; // [SI]
		$factura->tipoIdentificacionComprador = $this->info_documento($con, $id_factura, "compra")['deducible_en']; //Info comprador [04, RUC][05,Cedula][06, Pasaporte][07, Consumidor final][08, Exterior][09, Placa]
		$factura->razonSocialComprador = strtoupper($this->info_empresa($con, $ruc_empresa)['nombre']); //Razon social o nombres y apellidos comprador
		$factura->identificacionComprador = $NumIdComprador;
		$factura->direccionComprador = strtoupper($this->info_empresa($con, $ruc_empresa)['direccion']); // direccion Comprador

		//suma subtotales
		$subtotales_generales = mysqli_query($con, "SELECT round(sum(det.subtotal),2) as subtotal_general, 
					round(sum(det.descuento),2) as descuento_general 
					FROM cuerpo_compra as det 
					INNER JOIN encabezado_compra as enc
					ON enc.codigo_documento=det.codigo_documento
					 WHERE enc.id_encabezado_compra = '" . $id_factura . "' ");
		$row_subtotales_generales = mysqli_fetch_array($subtotales_generales);
		$subtotal_general = $row_subtotales_generales['subtotal_general']; //Sumador subtotal general
		$total_descuento = $row_subtotales_generales['descuento_general']; //Sumador total descuento

		$factura->totalSinImpuestos = number_format($subtotal_general, 2, '.', ''); // Total sin aplicar impuestos
		$factura->totalDescuento = number_format($total_descuento, 2, '.', ''); // Total Dtos
		$factura->guiaRemision = ""; // guia de remision

		//consulta de la tabla de tarifa iva
		$codigo_impuesto_en_totales = "2"; //$totales_detalle_impuestos_ventas['codigo_impuesto'];	
		if ($codigo_impuesto_en_totales == "2") {
			$subtotales_factura = array();
			$subtotales_tarifa_iva = mysqli_query($con, "SELECT 
			round(sum((cf.cantidad * cf.precio - cf.descuento) * (ti.porcentaje_iva /100)),2) as total_iva, 
			ti.codigo as codigo_porcentaje, 
			round(sum(cf.subtotal),2) as subtotal_factura 
										FROM cuerpo_compra as cf INNER JOIN encabezado_compra as enc
										ON enc.codigo_documento=cf.codigo_documento
										INNER JOIN tarifa_iva as ti ON ti.codigo = cf.det_impuesto 
										WHERE enc.id_encabezado_compra = '" . $id_factura . "' group by cf.det_impuesto");
			foreach ($subtotales_tarifa_iva as $sub_tarifa_iva) {
				$totalImpuesto = new totalImpuesto();
				$totalImpuesto->codigo = "2"; //[2, IVA][3,ICE][5, IRBPNR]						
				$totalImpuesto->codigoPorcentaje = $sub_tarifa_iva['codigo_porcentaje']; // IVA -> [0, 0%][2, 12%][6, No objeto de impuesto][7, Exento de IVA] ICE->[Tabla 19]
				$totalImpuesto->baseImponible = number_format($sub_tarifa_iva['subtotal_factura'], 2, '.', ''); // Suma de los impuesto del mismo cod y % (0.00)										
				$totalImpuesto->valor = number_format($sub_tarifa_iva['total_iva'], 2, '.', '');; // Suma de los impuesto del mismo cod y % aplicado el % (0.00)
				$subtotales_factura[] = $totalImpuesto;
			}
		}

		$factura->totalConImpuesto = $subtotales_factura; //Agrega el impuesto a la factura NO TOCAR ESTA LINEA
		$factura->propina = number_format($this->info_documento($con, $id_factura, "compra")['propina']); // Propina 
		$factura->importeTotal = number_format($this->info_documento($con, $id_factura, "compra")['total_compra'], 2, '.', ''); // Total de Productos + impuestos
		$factura->moneda = "DOLAR"; //DOLAR

		//desde aqui detalle de la factura y productos
		$detalle_factura = mysqli_query($con, "SELECT * from cuerpo_compra as det
		INNER JOIN encabezado_compra as enc
		ON enc.codigo_documento=det.codigo_documento
		INNER JOIN tarifa_iva as tar ON tar.codigo=det.det_impuesto
		WHERE enc.id_encabezado_compra = '" . $id_factura . "' ");

		$detalle_factura_item = array();
		//$detallesAdicionales = array();
		foreach ($detalle_factura as $detalle_final) {
			$detalleFactura = new detalleFactura();
			$detalleFactura->codigoPrincipal = $detalle_final['codigo_producto']; // Codigo del Producto
			$detalleFactura->codigoAuxiliar = $detalle_final['codigo_producto']; // Opcional
			$detalleFactura->descripcion = ucwords(clear_cadena($detalle_final['detalle_producto'])); // Nombre del producto		
			$detalleFactura->cantidad = number_format($detalle_final['cantidad'], 2, '.', ''); // Cantidad
			$detalleFactura->precioUnitario = number_format($detalle_final['precio'], 4, '.', ''); // Valor unitario
			$detalleFactura->descuento = number_format($detalle_final['descuento'], 2, '.', ''); // Descuento u
			$detalleFactura->precioTotalSinImpuesto = number_format($detalle_final['cantidad'] * $detalle_final['precio'] - $detalle_final['descuento'], 2, '.', ''); // Valor sin impuesto

			$detAdicional = new detalleAdicional();
			$detAdicional->nombre = "infoAdicional";
			$detAdicional->valor = "";
			$detalleFactura->detalleAdicional = "";

			$impuesto = new impuesto(); // Impuesto del detalle
			$impuesto->codigo = "2"; //del impuesto, iva, ice, bp	
			$impuesto->codigoPorcentaje = $detalle_final['det_impuesto'];
			$impuesto->tarifa = $detalle_final['porcentaje_iva'];
			$impuesto->baseImponible = number_format($detalleFactura->precioTotalSinImpuesto, 2, '.', ''); // subtotal o base
			$impuesto->valor = number_format(($detalleFactura->precioTotalSinImpuesto * ($detalle_final['porcentaje_iva'] / 100)), 2, '.', ''); // valor o sea el 12 por ciento de la base
			$detalleFactura->impuestos = $impuesto;
			$detalle_factura_item[] = $detalleFactura;
		}

		$factura->detalles = $detalle_factura_item;
		//hasta aqui detalle de la factura

		// trae formas de pago
		$busca_fp_factura = mysqli_query($con, "SELECT * FROM formas_pago_compras as fp 
		INNER JOIN encabezado_compra as enc 
		ON enc.codigo_documento=fp.codigo_documento
		WHERE enc.id_encabezado_compra = '" . $id_factura . "' ");

		$detalle_pago = array();
		foreach ($busca_fp_factura as $info_fp_factura) {
			$pago = new pagos();
			$pago->formaPago = $info_fp_factura['forma_pago'];
			$pago->total = number_format($info_fp_factura['total_pago'], 2, '.', '');
			$pago->plazo = $this->info_proveedor($con, $id_proveedor)['plazo'];
			$pago->unidadTiempo = "Días";
			$detalle_pago[] = $pago;
		}
		$factura->pagos = $detalle_pago;

		//para mostrar la casilla de propina dependiendo si esta asignada o no
		$rubro = new rubro();
		$rubro->concepto = "OTROS";
		$rubro->total = number_format($this->info_documento($con, $id_factura, "compra")['otros_val'], 2, '.', '');
		$factura->otrosRubros = $rubro;

		//desde aqui detalle de adicionales de la factura
		$detalle_adicional_factura = mysqli_query($con, "SELECT * from detalle_adicional_compra
		as adi INNER JOIN encabezado_compra as enc
		ON enc.codigo_documento = adi.codigo_documento
		WHERE enc.id_encabezado_compra = '" . $id_factura . "' ");

		$camposAdicionales = array();
		foreach ($detalle_adicional_factura as $detalle_adicional) {
			$nombre_adicional = clear_cadena($detalle_adicional['adicional_concepto']);
			$descripcion_adicional = clear_cadena($detalle_adicional['adicional_descripcion']);
			if ($nombre_adicional != null && $descripcion_adicional != null) {
				$campoAdicional = new campoAdicional();
				$campoAdicional->nombre = $nombre_adicional;
				$campoAdicional->valor = $descripcion_adicional;
				$camposAdicionales[] = $campoAdicional;
			}
		}
		$factura->infoAdicional = $camposAdicionales;

		$procesar = $this->procesar_comprobante($con, $factura, 'compra', $modo_envio, $id_factura, $ruc_empresa);
		return $procesar;
	}


	//enviar liquidaciones al sri
	public function EnviarLc($con, $id_liquidacion, $modo_envio)
	{
		$documento = 'liquidacion';
		$ruc_empresa = $this->info_documento($con, $id_liquidacion, $documento)['ruc_empresa'];
		$serie_sucursal = $this->info_documento($con, $id_liquidacion, $documento)['serie_liquidacion'];
		$serie_liquidacion = $this->info_documento($con, $id_liquidacion, $documento)['serie_liquidacion'];
		$numero_liquidacion = $this->info_documento($con, $id_liquidacion, $documento)['secuencial_liquidacion'];
		$id_proveedor = $this->info_documento($con, $id_liquidacion, $documento)['id_proveedor'];

		$liquidacionCompra = new liquidacionCompra();
		$liquidacionCompra->configAplicacion = $this->config_app($con, $documento, $serie_sucursal, $ruc_empresa);
		$liquidacionCompra->configCorreo = $this->confi_mail($con, $modo_envio, $ruc_empresa);
		$liquidacionCompra->ambiente = $this->confi_empresa($con, $ruc_empresa)['tipo_ambiente']; //[1,Prueba][2,Produccion] 
		$liquidacionCompra->tipoEmision = $this->confi_empresa($con, $ruc_empresa)['tipo_emision']; //[1,Emision Normal][2,Emision Por Indisponibilidad del sistema
		$liquidacionCompra->razonSocial = strtoupper($this->info_empresa($con, $ruc_empresa)['nombre']); //[Razon Social]
		$liquidacionCompra->nombreComercial = strtoupper($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['nombre_sucursal']);  //[Nombre Comercial, si hay]*
		$liquidacionCompra->ruc = substr($ruc_empresa, 0, 10) . '001'; //[Ruc]
		$liquidacionCompra->codDoc = '03'; //[03 liq, 01, Factura] [04, Nota Credito] [05, Nota Debito] [06, Guia Remision] [07, Retencion]
		$liquidacionCompra->establecimiento = substr($serie_sucursal, 0, 3); //[pto de emision ] **
		$liquidacionCompra->fechaEmision = date("d/m/Y", strtotime($this->info_documento($con, $id_liquidacion, $documento)['fecha_liquidacion'])); //[Fecha (dd/mm/yyyy)]
		$liquidacionCompra->ptoEmision = substr($serie_sucursal, 4, 3); // [Numero Establecimiento SRI]
		$liquidacionCompra->secuencial = str_pad($numero_liquidacion, 9, "000000000", STR_PAD_LEFT); // [Secuencia desde 1 (9)]
		$liquidacionCompra->dirMatriz = strtoupper($this->info_empresa($con, $ruc_empresa)['direccion']); //[Direccion de la Matriz ->SRI]
		$liquidacionCompra->dirEstablecimiento = strtoupper($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['direccion_sucursal']); //[Direccion de Establecimiento ->SRI]
		$liquidacionCompra->contribuyenteEspecial = $this->confi_empresa($con, $ruc_empresa)['resol_cont'];
		$liquidacionCompra->regimenRIMPE = $this->confi_empresa($con, $ruc_empresa)['tipo_regimen'] == 2 ? "SI" : "";
		$liquidacionCompra->regimenRIMPENP = $this->confi_empresa($con, $ruc_empresa)['tipo_regimen'] == 3 ? "SI" : "";
		$liquidacionCompra->agenteRetencion = $this->confi_empresa($con, $ruc_empresa)['agente_ret'] > 0 ? $this->confi_empresa($con, $ruc_empresa)['agente_ret'] : "";

		$tipo_empresa = $this->info_empresa($con, $ruc_empresa)['tipo'];
		switch ($tipo_empresa) {
			case "01":
				$lleva_contabilidad = "NO";
				break;
			case "02" or "03" or "04" or "05":
				$lleva_contabilidad = "SI";
				break;
		};

		$liquidacionCompra->obligadoContabilidad = $lleva_contabilidad; // [SI]	
		$liquidacionCompra->tipoIdentificacionProveedor = $this->info_proveedor($con, $id_proveedor)['tipo_id_proveedor']; //Info proveedor [04, RUC][05,Cedula][06, Pasaporte][07, Consumidor final][08, Exterior][09, Placa]
		$liquidacionCompra->razonSocialProveedor = strtoupper($this->info_proveedor($con, $id_proveedor)['razon_social']); //Razon social o nombres y apellidos proveedor
		$liquidacionCompra->identificacionProveedor = $this->info_proveedor($con, $id_proveedor)['ruc_proveedor']; // Identificacion proveedor
		$liquidacionCompra->direccionProveedor = $this->info_proveedor($con, $id_proveedor)['dir_proveedor']; // direccion proveedor
		//suma subtotales
		$subtotales_generales = mysqli_query($con, "SELECT round(sum(subtotal-descuento),2) as subtotal_general, round(sum(descuento),2) as descuento_general from cuerpo_liquidacion where ruc_empresa = '" . $ruc_empresa . "' and serie_liquidacion ='" . $serie_liquidacion . "' and secuencial_liquidacion='" . $numero_liquidacion . "'");
		$row_subtotales_generales = mysqli_fetch_array($subtotales_generales);
		$subtotal_general = $row_subtotales_generales['subtotal_general']; //Sumador subtotal general
		$total_descuento = $row_subtotales_generales['descuento_general']; //Sumador total descuento

		$liquidacionCompra->totalSinImpuestos = number_format($subtotal_general, 2, '.', ''); // Total sin aplicar impuestos
		$liquidacionCompra->totalDescuento = number_format($total_descuento, 2, '.', ''); // Total Dtos

		//consulta de la tabla de tarifa iva
		$codigo_impuesto_en_totales = "2"; //$totales_detalle_impuestos_ventas['codigo_impuesto'];	
		if ($codigo_impuesto_en_totales == "2") {
			$subtotales_liquidacion = array();
			$subtotales_tarifa_iva = mysqli_query($con, "SELECT round(sum((cl.subtotal - cl.descuento) * ti.porcentaje_iva /100),4) as total_iva, ti.codigo as codigo_porcentaje, round(sum(cl.subtotal - cl.descuento),2) as subtotal_liquidacion FROM cuerpo_liquidacion as cl INNER JOIN tarifa_iva as ti ON ti.codigo = cl.tarifa_iva WHERE cl.ruc_empresa ='" . $ruc_empresa . "' and cl.serie_liquidacion='" . $serie_liquidacion . "' and cl.secuencial_liquidacion ='" . $numero_liquidacion . "' group by cl.tarifa_iva ");
			foreach ($subtotales_tarifa_iva as $sub_tarifa_iva) {
				$totalImpuesto = new totalImpuesto();
				$totalImpuesto->codigo = "2"; //[2, IVA][3,ICE][5, IRBPNR]						
				$totalImpuesto->codigoPorcentaje = $sub_tarifa_iva['codigo_porcentaje']; // IVA -> [0, 0%][2, 12%][6, No objeto de impuesto][7, Exento de IVA] ICE->[Tabla 19]
				$totalImpuesto->baseImponible = number_format($sub_tarifa_iva['subtotal_liquidacion'], 2, '.', ''); // Suma de los impuesto del mismo cod y % (0.00)										
				$totalImpuesto->valor = number_format($sub_tarifa_iva['total_iva'], 2, '.', '');; // Suma de los impuesto del mismo cod y % aplicado el % (0.00)
				$subtotales_liquidacion[] = $totalImpuesto;
			}
		}
		$liquidacionCompra->totalConImpuesto = $subtotales_liquidacion; //Agrega el impuesto a la liquidacion NO TOCAR ESTA LINEA
		$liquidacionCompra->importeTotal = number_format($this->info_documento($con, $id_liquidacion, "liquidacion")['total_liquidacion'], 2, '.', ''); // Total de Productos + impuestos
		$liquidacionCompra->moneda = $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['moneda_sucursal']; //DOLAR

		//desde aqui detalle de la liquidacion y productos
		$detalle_liquidacion = mysqli_query($con, "SELECT * from cuerpo_liquidacion as cue 
		INNER JOIN tarifa_iva as tar ON tar.codigo=cue.tarifa_iva 
		WHERE cue.ruc_empresa = '" . $ruc_empresa . "' and cue.serie_liquidacion ='" . $serie_liquidacion . "' 
		and cue.secuencial_liquidacion='" . $numero_liquidacion . "' ");
		$detalle_liquidacion_item = array();

		foreach ($detalle_liquidacion as $detalle_final) {
			$detalleLiquidacionCompra = new detalleLiquidacionCompra();
			$detalleLiquidacionCompra->codigoPrincipal = $detalle_final['codigo_producto']; // Codigo del Producto
			$detalleLiquidacionCompra->codigoAuxiliar = $detalle_final['codigo_producto']; // Opcional
			$detalleLiquidacionCompra->descripcion = ucwords($detalle_final['nombre_producto']); // Nombre del producto		
			$detalleLiquidacionCompra->cantidad = number_format($detalle_final['cantidad'], 2, '.', ''); // Cantidad
			$detalleLiquidacionCompra->precioUnitario = number_format($detalle_final['valor_unitario'], 2, '.', ''); // Valor unitario
			$detalleLiquidacionCompra->descuento = number_format($detalle_final['descuento'], 2, '.', ''); // Descuento u
			$detalleLiquidacionCompra->precioTotalSinImpuesto = number_format(($detalleLiquidacionCompra->cantidad * $detalleLiquidacionCompra->precioUnitario) - $detalle_final['descuento'], 2, '.', ''); // Valor sin impuesto

			$impuesto = new impuesto(); // Impuesto del detalle
			$impuesto->codigo = "2"; //del impuesto, iva, ice, bp	
			$impuesto->codigoPorcentaje = $detalle_final['tarifa_iva'];
			$impuesto->tarifa = $detalle_final['porcentaje_iva'];
			$impuesto->baseImponible = number_format($detalleLiquidacionCompra->precioTotalSinImpuesto, 2, '.', ''); // subtotal o base
			$impuesto->valor = number_format(($detalleLiquidacionCompra->precioTotalSinImpuesto * ($detalle_final['porcentaje_iva'] / 100)), 2, '.', ''); // valor o sea el 12 por ciento de la base
			$detalleLiquidacionCompra->impuestos = $impuesto;
			$detalle_liquidacion_item[] = $detalleLiquidacionCompra;
		}

		$liquidacionCompra->detalles = $detalle_liquidacion_item;
		//hasta aqui detalle de la liquidacion

		// trae formas de pago
		$busca_fp_liquidacion = mysqli_query($con, "SELECT * FROM formas_pago_liquidacion WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_liquidacion ='" . $serie_liquidacion . "' and secuencial_liquidacion='" . $numero_liquidacion . "'");

		$detalle_pago = array();
		foreach ($busca_fp_liquidacion as $info_fp_factura) {
			$pago = new pagos();
			$codigo_fp = $info_fp_factura['id_forma_pago'];
			$valor_pago = $info_fp_factura['valor_pago'];
			$pago->formaPago = $codigo_fp;
			$pago->total = number_format($valor_pago, 2, '.', '');
			$pago->plazo = $this->info_proveedor($con, $id_proveedor)['plazo'];
			$pago->unidadTiempo = "Días";
			$detalle_pago[] = $pago;
		}
		$liquidacionCompra->pagos = $detalle_pago;

		//desde aqui detalle de adicionales de la liquidacion
		$detalle_adicional_liquidacion = mysqli_query($con, "SELECT * from detalle_adicional_liquidacion WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_liquidacion ='" . $serie_liquidacion . "' and secuencial_liquidacion='" . $numero_liquidacion . "' ");

		$camposAdicionales = array();
		foreach ($detalle_adicional_liquidacion as $detalle_adicional) {
			$nombre_adicional = $detalle_adicional['adicional_concepto'];
			$descripcion_adicional = $detalle_adicional['adicional_descripcion'];
			if ($nombre_adicional != null && $descripcion_adicional != null) {
				$campoAdicional = new campoAdicional();
				$campoAdicional->nombre = $nombre_adicional;
				$campoAdicional->valor = $descripcion_adicional;
				$camposAdicionales[] = $campoAdicional;
			}
		}
		$liquidacionCompra->infoAdicional = $camposAdicionales;
		$procesar = $this->procesar_comprobante($con, $liquidacionCompra, 'liquidacion', $modo_envio, $id_liquidacion, $ruc_empresa);
		return $procesar;
		//}
	}

	//para enviar proformas
	public function EnviarProforma($con, $id_proforma, $modo_envio)
	{
		$documento = 'proforma';
		$ruc_empresa = $this->info_documento($con, $id_proforma, $documento)['ruc_empresa'];
		$serie_sucursal = $this->info_documento($con, $id_proforma, $documento)['serie_proforma'];
		$codigo_unico = $this->info_documento($con, $id_proforma, $documento)['codigo_unico'];
		$numero_proforma = $this->info_documento($con, $id_proforma, $documento)['secuencial_proforma'];
		$id_cliente = $this->info_documento($con, $id_proforma, $documento)['id_cliente'];

		$proforma = new proforma();
		$proforma->tipoEmision = "1";
		$proforma->numero = $serie_sucursal . "-" . str_pad($numero_proforma, 9, "000000000", STR_PAD_LEFT);
		$proforma->dirProformas = $this->config_app($con, $documento, $serie_sucursal, $ruc_empresa)->dirAutorizados;
		$proforma->dirLogo = $this->config_app($con, $documento, $serie_sucursal, $ruc_empresa)->dirLogo;
		$proforma->configCorreo = $this->confi_mail($con, $modo_envio, $ruc_empresa);
		$proforma->razonSocial = strtoupper($this->info_empresa($con, $ruc_empresa)['nombre']); //[Razon Social]
		$proforma->nombreComercial = strtoupper($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['nombre_sucursal']);  //[Nombre Comercial, si hay]*
		$proforma->ruc = substr($ruc_empresa, 0, 10) . '001'; //[Ruc]
		$proforma->fechaEmision = date("d/m/Y", strtotime($this->info_documento($con, $id_proforma, $documento)['fecha_proforma'])); //[Fecha (dd/mm/yyyy)]
		$proforma->dirMatriz = strtoupper($this->info_empresa($con, $ruc_empresa)['direccion']); //[Direccion de la Matriz ->SRI]
		$proforma->dirEstablecimiento = strtoupper($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['direccion_sucursal']); //[Direccion de Establecimiento ->SRI]
		$proforma->razonSocialComprador = strtoupper($this->info_cliente($con, $id_cliente)['nombre']); //Razon social o nombres y apellidos comprador
		$proforma->identificacionComprador = $this->info_cliente($con, $id_cliente)['ruc']; // Identificacion Comprador
		$proforma->direccionComprador = $this->info_cliente($con, $id_cliente)['direccion']; // Identificacion Comprador

		//suma subtotales
		$subtotales_sin_impuestos = mysqli_query($con, "SELECT round(sum(cp.subtotal-cp.descuento),2) as subtotal 
		FROM cuerpo_proforma as cp WHERE cp.ruc_empresa = '" . $ruc_empresa . "' 
		and cp.codigo_unico ='" . $codigo_unico . "' ");
		$row_subtotales_sin_impuestos = mysqli_fetch_array($subtotales_sin_impuestos);
		$subtotal_sin_impuestos = $row_subtotales_sin_impuestos['subtotal']; //Sumador subtotal general

		$subtotales_mayor_cero = mysqli_query($con, "SELECT round(sum(cp.subtotal-cp.descuento) ,2) as subtotal
		from cuerpo_proforma as cp INNER JOIN tarifa_iva as tar ON
		tar.codigo=cp.tarifa_iva where cp.ruc_empresa = '" . $ruc_empresa . "' 
		and cp.codigo_unico ='" . $codigo_unico . "' 
		and tar.porcentaje_iva > 0");
		$row_subtotales_mayor_cero = mysqli_fetch_array($subtotales_mayor_cero);
		$subtotal_mayor_cero = $row_subtotales_mayor_cero['subtotal'];

		$subtotales_cero = mysqli_query($con, "SELECT round(sum(cp.subtotal-cp.descuento),2) as subtotal
		from cuerpo_proforma as cp INNER JOIN tarifa_iva as tar ON
		tar.codigo=cp.tarifa_iva where cp.ruc_empresa = '" . $ruc_empresa . "' 
		and cp.codigo_unico ='" . $codigo_unico . "' 
		and tar.porcentaje_iva = 0");
		$row_subtotales_cero = mysqli_fetch_array($subtotales_cero);
		$subtotal_cero = $row_subtotales_cero['subtotal'];

		$subtotales_descuento = mysqli_query($con, "SELECT round(sum(cp.descuento),2) as descuento
		from cuerpo_proforma as cp INNER JOIN tarifa_iva as tar ON
		tar.codigo=cp.tarifa_iva where cp.ruc_empresa = '" . $ruc_empresa . "' 
		and cp.codigo_unico ='" . $codigo_unico . "' ");
		$row_subtotales_descuento = mysqli_fetch_array($subtotales_descuento);
		$subtotal_descuento = $row_subtotales_descuento['descuento'];

		$subtotales_iva = mysqli_query($con, "SELECT round(sum((cp.cantidad * cp.valor_unitario - cp.descuento) * (tar.porcentaje_iva /100)) ,2) as total_iva
		from cuerpo_proforma as cp INNER JOIN tarifa_iva as tar ON
		tar.codigo=cp.tarifa_iva where cp.ruc_empresa = '" . $ruc_empresa . "' 
		and cp.codigo_unico ='" . $codigo_unico . "' 
		and tar.porcentaje_iva > 0");
		$row_subtotales_iva = mysqli_fetch_array($subtotales_iva);
		$subtotal_iva = $row_subtotales_iva['total_iva'];

		$proforma->subTotal0 = number_format($subtotal_cero, 2, '.', '');
		$proforma->subTotal12 = number_format($subtotal_mayor_cero, 2, '.', '');
		$proforma->subTotalSinImpuesto = number_format($subtotal_sin_impuestos, 2, '.', '');
		$proforma->iva = number_format($subtotal_iva, 2, '.', '');
		$proforma->totalDescuento = number_format($subtotal_descuento, 2, '.', '');
		$proforma->importeTotal = number_format($this->info_documento($con, $id_proforma, "proforma")['total_proforma'], 2, '.', '');

		$detalle_proforma = mysqli_query($con, "SELECT * from cuerpo_proforma WHERE ruc_empresa = '" . $ruc_empresa . "' and codigo_unico ='" . $codigo_unico . "' ");
		$detalle_proforma_item = array();

		foreach ($detalle_proforma as $detalle_final) {
			$detalleProforma = new detalleProforma();
			$detalleProforma->codigo = $detalle_final['codigo_producto']; // Codigo del Producto
			$detalleProforma->descripcion = ucwords($detalle_final['nombre_producto']); // Nombre del producto
			$detalleProforma->cantidad = number_format($detalle_final['cantidad'], ($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['decimal_cant'] == '1') ? 0 : $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['decimal_cant'], '.', ''); // Cantidad
			$detalleProforma->precioUnitario = number_format($detalle_final['valor_unitario'], $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['decimal_doc'], '.', ''); // Valor unitario
			$detalleProforma->descuento = number_format($detalle_final['descuento'], 2, '.', ''); // Descuento u
			$detalleProforma->precioTotalSinImpuesto = number_format(($detalleProforma->cantidad * $detalleProforma->precioUnitario) - $detalle_final['descuento'], 2, '.', ''); // Valor sin impuesto  (cantidad * precioUnitario) - descuento
			$detalle_proforma_item[] = $detalleProforma;
		}

		$proforma->detalles = $detalle_proforma_item;

		$eliminar_adicional_proforma = mysqli_query($con, "DELETE from detalle_adicional_proforma WHERE ruc_empresa = '" . $ruc_empresa . "' and codigo_unico ='" . $codigo_unico . "' and adicional_concepto='Emisor'");

		$detalle_adicional_proforma = mysqli_query($con, "SELECT * from detalle_adicional_proforma WHERE ruc_empresa = '" . $ruc_empresa . "' and codigo_unico ='" . $codigo_unico . "' ");
		$camposAdicionales = array();
		foreach ($detalle_adicional_proforma as $detalle_adicional) {
			$nombre_adicional = $detalle_adicional['adicional_concepto'];
			$descripcion_adicional = $detalle_adicional['adicional_descripcion'];
			if ($nombre_adicional != null && $descripcion_adicional != null) {
				$campoAdicional = new campoAdicional();
				$campoAdicional->nombre = $nombre_adicional;
				$campoAdicional->valor = $descripcion_adicional;
				$camposAdicionales[] = $campoAdicional;
			}
		}

		$proforma->infoAdicional = $camposAdicionales;
		$procesar = $this->procesar_proforma($con, $proforma, 'proforma', $serie_sucursal, $numero_proforma, $id_proforma);
		return $procesar;
	}
	//hasta aqui la proforma

	//para enviar facturas
	public function EnviarFactura($con, $id_factura, $modo_envio)
	{
		$documento = 'factura';
		$ruc_empresa = $this->info_documento($con, $id_factura, $documento)['ruc_empresa'];
		$serie_sucursal = $this->info_documento($con, $id_factura, $documento)['serie_factura'];
		$serie_factura = $this->info_documento($con, $id_factura, $documento)['serie_factura'];
		$numero_factura = $this->info_documento($con, $id_factura, $documento)['secuencial_factura'];
		$id_cliente = $this->info_documento($con, $id_factura, $documento)['id_cliente'];

		$factura = new factura();
		$factura->configAplicacion = $this->config_app($con, $documento, $serie_sucursal, $ruc_empresa);
		$factura->configCorreo = $this->confi_mail($con, $modo_envio, $ruc_empresa);
		$factura->ambiente = $this->confi_empresa($con, $ruc_empresa)['tipo_ambiente']; //[1,Prueba][2,Produccion] 
		$factura->tipoEmision = $this->confi_empresa($con, $ruc_empresa)['tipo_emision']; //[1,Emision Normal][2,Emision Por Indisponibilidad del sistema
		$factura->razonSocial = strtoupper($this->info_empresa($con, $ruc_empresa)['nombre']); //[Razon Social]
		$factura->nombreComercial = strtoupper($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['nombre_sucursal']);  //[Nombre Comercial, si hay]*
		$factura->ruc = substr($ruc_empresa, 0, 10) . '001'; //[Ruc]
		$factura->codDoc = "01"; //[01, Factura] [04, Nota Credito] [05, Nota Debito] [06, Guia Remision] [07, Retencion]
		$factura->establecimiento = substr($serie_factura, 0, 3); //[pto de emision ] **
		$factura->ptoEmision = substr($serie_factura, 4, 3); // [Numero Establecimiento SRI]
		$factura->secuencial = str_pad($numero_factura, 9, "000000000", STR_PAD_LEFT); // [Secuencia desde 1 (9)]
		$factura->fechaEmision = date("d/m/Y", strtotime($this->info_documento($con, $id_factura, $documento)['fecha_factura'])); //[Fecha (dd/mm/yyyy)]
		$factura->dirMatriz = strtoupper($this->info_empresa($con, $ruc_empresa)['direccion']); //[Direccion de la Matriz ->SRI]
		$factura->dirEstablecimiento = strtoupper($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['direccion_sucursal']); //[Direccion de Establecimiento ->SRI]
		$factura->contribuyenteEspecial = $this->confi_empresa($con, $ruc_empresa)['resol_cont'];
		$factura->regimenRIMPE = $this->confi_empresa($con, $ruc_empresa)['tipo_regimen'] == 2 ? "SI" : "";
		$factura->regimenRIMPENP = $this->confi_empresa($con, $ruc_empresa)['tipo_regimen'] == 3 ? "SI" : "";
		$factura->agenteRetencion = $this->confi_empresa($con, $ruc_empresa)['agente_ret'] > 0 ? $this->confi_empresa($con, $ruc_empresa)['agente_ret'] : "";

		$tipo_empresa = $this->info_empresa($con, $ruc_empresa)['tipo'];
		switch ($tipo_empresa) {
			case "01":
				$lleva_contabilidad = "NO";
				break;
			case "02" || "03" || "04" || "05":
				$lleva_contabilidad = "SI";
				break;
		};

		$factura->obligadoContabilidad = $lleva_contabilidad; // [SI]
		$factura->tipoIdentificacionComprador = $this->info_cliente($con, $id_cliente)['tipo_id']; //Info comprador [04, RUC][05,Cedula][06, Pasaporte][07, Consumidor final][08, Exterior][09, Placa]
		$factura->razonSocialComprador = strtoupper($this->info_cliente($con, $id_cliente)['nombre']); //Razon social o nombres y apellidos comprador
		$factura->identificacionComprador = $this->info_cliente($con, $id_cliente)['ruc']; // Identificacion Comprador
		$factura->direccionComprador = $this->info_cliente($con, $id_cliente)['direccion']; // direccion Comprador

		//suma subtotales
		$subtotales_generales = mysqli_query($con, "SELECT round(sum(subtotal_factura-descuento),2) as subtotal_general, 
				round(sum(descuento),2) as descuento_general from cuerpo_factura where ruc_empresa = '" . $ruc_empresa . "' and serie_factura ='" . $serie_factura . "' and secuencial_factura='" . $numero_factura . "'");
		$row_subtotales_generales = mysqli_fetch_array($subtotales_generales);

		$subtotal_general = $row_subtotales_generales['subtotal_general']; //Sumador subtotal general
		$total_descuento = $row_subtotales_generales['descuento_general']; //Sumador total descuento

		$factura->totalSinImpuestos = number_format($subtotal_general, 2, '.', ''); // Total sin aplicar impuestos
		$factura->totalDescuento = number_format($total_descuento, 2, '.', ''); // Total Dtos
		$factura->guiaRemision = $this->info_documento($con, $id_factura, "factura")['guia_remision']; // guia de remision

		//consulta de la tabla de tarifa iva
		/* 	$codigo_impuesto_en_totales = "2";
		if ($codigo_impuesto_en_totales == "2") {
			$subtotales_factura = array();
			$subtotales_tarifa_iva = mysqli_query($con, "SELECT 
			round(sum((cf.cantidad_factura * cf.valor_unitario_factura - cf.descuento) * (ti.porcentaje_iva /100)),2) as total_iva, 
			ti.codigo as codigo_porcentaje, 
			round(sum(cf.subtotal_factura - cf.descuento),2) as subtotal_factura 
									FROM cuerpo_factura as cf 
									INNER JOIN tarifa_iva as ti ON ti.codigo = cf.tarifa_iva 
									WHERE cf.ruc_empresa ='" . $ruc_empresa . "' 
									and cf.serie_factura='" . $serie_factura . "' 
									and cf.secuencial_factura ='" . $numero_factura . "' 
									group by cf.tarifa_iva ");
			foreach ($subtotales_tarifa_iva as $sub_tarifa_iva) {
				$totalImpuesto = new totalImpuesto();
				$totalImpuesto->codigo = "2"; //[2, IVA][3,ICE][5, IRBPNR]						
				$totalImpuesto->codigoPorcentaje = $sub_tarifa_iva['codigo_porcentaje']; // IVA -> [0, 0%][2, 12%][6, No objeto de impuesto][7, Exento de IVA] ICE->[Tabla 19]
				$totalImpuesto->baseImponible = number_format($sub_tarifa_iva['subtotal_factura'], 2, '.', ''); // Suma de los impuesto del mismo cod y % (0.00)										
				$totalImpuesto->valor = number_format($sub_tarifa_iva['total_iva'], 2, '.', ''); // Suma de los impuesto del mismo cod y % aplicado el % (0.00)
				$subtotales_factura[] = $totalImpuesto;
			}
		} */

		$codigo_impuesto_en_totales = "2";

		if ($codigo_impuesto_en_totales == "2") {
			$subtotales_factura = array();

			// Obtener subtotal por tarifa
			$subtotales_tarifa_iva = mysqli_query($con, "
		SELECT 
			ti.codigo AS codigo_porcentaje,
			ROUND(SUM(cf.subtotal_factura - cf.descuento), 2) AS subtotal_factura,
			ti.porcentaje_iva
		FROM cuerpo_factura AS cf
		INNER JOIN tarifa_iva AS ti ON ti.codigo = cf.tarifa_iva
		WHERE 
			cf.ruc_empresa = '" . $ruc_empresa . "' 
			AND cf.serie_factura = '" . $serie_factura . "' 
			AND cf.secuencial_factura = '" . $numero_factura . "'
		GROUP BY cf.tarifa_iva
	");

			foreach ($subtotales_tarifa_iva as $sub_tarifa_iva) {
				$totalImpuesto = new totalImpuesto();
				$totalImpuesto->codigo = "2"; // [2, IVA][3,ICE][5, IRBPNR]
				$totalImpuesto->codigoPorcentaje = $sub_tarifa_iva['codigo_porcentaje'];

				// Base imponible (subtotal agrupado por tarifa)
				$baseImponible = $sub_tarifa_iva['subtotal_factura'];
				$totalImpuesto->baseImponible = number_format($baseImponible, 2, '.', '');

				// Valor del IVA calculado en PHP
				$porcentaje_iva = $sub_tarifa_iva['porcentaje_iva'];
				$valor_iva = round($baseImponible * ($porcentaje_iva / 100), 2);
				$totalImpuesto->valor = number_format($valor_iva, 2, '.', '');

				$subtotales_factura[] = $totalImpuesto;
			}
		}

		$factura->totalConImpuesto = $subtotales_factura; //Agrega el impuesto a la factura NO TOCAR ESTA LINEA
		$factura->propina = number_format($this->info_documento($con, $id_factura, "factura")['propina'], 2, '.', ''); // Propina 
		$factura->importeTotal = number_format($this->info_documento($con, $id_factura, "factura")['total_factura'], 2, '.', ''); // Total de Productos + impuestos
		$factura->moneda = $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['moneda_sucursal']; //DOLAR

		//desde aqui detalle de la factura y productos
		/* $detalle_factura = mysqli_query($con, "SELECT * from cuerpo_factura as cue INNER JOIN tarifa_iva as tar
		ON tar.codigo=cue.tarifa_iva WHERE cue.ruc_empresa = '" . $ruc_empresa . "' 
		and cue.serie_factura ='" . $serie_factura . "' and cue.secuencial_factura='" . $numero_factura . "' ");
		$detalle_factura_item = array();
		foreach ($detalle_factura as $detalle_final) {
			$detalle_producto = mysqli_query($con, "SELECT * from productos_servicios WHERE id ='" . $detalle_final['id_producto'] . "' ");
			$row_detalle_producto = mysqli_fetch_array($detalle_producto);
			$codigo_auxiliar = $row_detalle_producto['codigo_auxiliar'];

			$detalleFactura = new detalleFactura();
			$detalleFactura->codigoPrincipal = $detalle_final['codigo_producto']; // Codigo del Producto
			$detalleFactura->codigoAuxiliar = $codigo_auxiliar; // Opcional
			$detalleFactura->descripcion = ucwords($detalle_final['nombre_producto']); // Nombre del producto		
			$detalleFactura->cantidad = number_format($detalle_final['cantidad_factura'], ($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['decimal_cant'] == '1') ? 0 : $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['decimal_cant'], '.', ''); // Cantidad
			$detalleFactura->precioUnitario = number_format($detalle_final['valor_unitario_factura'], $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['decimal_doc'], '.', ''); // Valor unitario
			$detalleFactura->descuento = number_format($detalle_final['descuento'], 2, '.', ''); // Descuento u
			$detalleFactura->precioTotalSinImpuesto = number_format(($detalleFactura->cantidad * $detalleFactura->precioUnitario) - $detalle_final['descuento'], 2, '.', ''); // Valor sin impuesto

			$info_adicional = $detalle_final['tarifa_bp'] == '0' ? "" : $detalle_final['tarifa_bp'];
			if (!empty($info_adicional)) {
				$detAdicional = new detalleAdicional();
				$detAdicional->nombre = "infoAdicional";
				$detAdicional->valor = $detalle_final['tarifa_bp'] == '0' ? "" : $detalle_final['tarifa_bp'];
				$detalleFactura->detalleAdicional = $detAdicional;
			}

			$impuesto = new impuesto(); // Impuesto del detalle
			$impuesto->codigo = "2"; //del impuesto, iva, ice, bp	
			$impuesto->codigoPorcentaje = $detalle_final['tarifa_iva'];
			$impuesto->tarifa = $detalle_final['porcentaje_iva'];
			$impuesto->baseImponible = number_format($detalleFactura->precioTotalSinImpuesto, 2, '.', ''); // subtotal o base
			$impuesto->valor = number_format(($detalleFactura->precioTotalSinImpuesto * ($detalle_final['porcentaje_iva'] / 100)), 2, '.', ''); // valor o sea el 12 por ciento de la base
			$detalleFactura->impuestos = $impuesto;
			$detalle_factura_item[] = $detalleFactura;
		}

		$factura->detalles = $detalle_factura_item; */


		//desde aqui detalle de la factura y productos
		$detalle_factura = mysqli_query($con, "SELECT cue.*, tar.porcentaje_iva, tar.codigo AS codigo_tarifa_iva 
	FROM tarifa_iva AS tar 
	INNER JOIN cuerpo_factura AS cue ON tar.codigo = cue.tarifa_iva
	WHERE cue.ruc_empresa = '" . $ruc_empresa . "' 
	AND cue.serie_factura = '" . $serie_factura . "' 
	AND cue.secuencial_factura = '" . $numero_factura . "' ");

		$detalle_factura_item = array();

		// Solo una vez obtenemos los decimales para cantidad y precio
		$info_sucursal = $this->info_sucursal($con, $serie_sucursal, $ruc_empresa);
		$decimales_cant = ($info_sucursal['decimal_cant'] == '1') ? 0 : $info_sucursal['decimal_cant'];
		$decimales_precio = $info_sucursal['decimal_doc'];

		while ($detalle_final = mysqli_fetch_array($detalle_factura)) {
			// Buscar código auxiliar del producto
			$detalle_producto = mysqli_query($con, "SELECT codigo_auxiliar FROM productos_servicios WHERE id = '" . $detalle_final['id_producto'] . "'");
			$row_detalle_producto = mysqli_fetch_array($detalle_producto);
			$codigo_auxiliar = $row_detalle_producto['codigo_auxiliar'];

			// Calcular valores precisos
			$cantidad = (float)$detalle_final['cantidad_factura'];
			$precio_unitario = (float)$detalle_final['valor_unitario_factura'];
			$descuento = (float)$detalle_final['descuento'];
			$precio_total_sin_impuesto = round(($cantidad * $precio_unitario) - $descuento, 2);
			$porcentaje_iva = (float)$detalle_final['porcentaje_iva'];

			$detalleFactura = new detalleFactura();
			$detalleFactura->codigoPrincipal = $detalle_final['codigo_producto'];
			$detalleFactura->codigoAuxiliar = $codigo_auxiliar;
			$detalleFactura->descripcion = ucwords($detalle_final['nombre_producto']);
			$detalleFactura->cantidad = number_format($cantidad, $decimales_cant, '.', '');
			$detalleFactura->precioUnitario = number_format($precio_unitario, $decimales_precio, '.', '');
			$detalleFactura->descuento = number_format($descuento, 2, '.', '');
			$detalleFactura->precioTotalSinImpuesto = number_format($precio_total_sin_impuesto, 2, '.', '');

			// Si existe información adicional (ej. tarifa_bp)
			if (!empty($detalle_final['tarifa_bp']) && $detalle_final['tarifa_bp'] !== '0') {
				$detAdicional = new detalleAdicional();
				$detAdicional->nombre = "infoAdicional";
				$detAdicional->valor = $detalle_final['tarifa_bp'];
				$detalleFactura->detalleAdicional = $detAdicional;
			}

			// Impuesto por ítem
			$valor_iva = round($precio_total_sin_impuesto * ($porcentaje_iva / 100), 2);

			$impuesto = new impuesto();
			$impuesto->codigo = "2"; // IVA
			$impuesto->codigoPorcentaje = $detalle_final['codigo_tarifa_iva'];
			$impuesto->tarifa = $porcentaje_iva;
			$impuesto->baseImponible = number_format($precio_total_sin_impuesto, 2, '.', '');
			$impuesto->valor = number_format($valor_iva, 2, '.', '');

			$detalleFactura->impuestos = $impuesto;

			$detalle_factura_item[] = $detalleFactura;
		}

		$factura->detalles = $detalle_factura_item;
		//hasta aqui detalle de la factura

		// trae formas de pago
		$busca_fp_factura = mysqli_query($con, "SELECT * FROM formas_pago_ventas WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_factura ='" . $serie_factura . "' and secuencial_factura='" . $numero_factura . "'");
		$detalle_pago = array();
		foreach ($busca_fp_factura as $info_fp_factura) {
			$pago = new pagos();
			$codigo_fp = $info_fp_factura['id_forma_pago'];
			$valor_pago = $info_fp_factura['valor_pago'];
			$pago->formaPago = $codigo_fp;
			$pago->total = number_format($valor_pago, 2, '.', '');
			$pago->plazo = $this->info_cliente($con, $id_cliente)['plazo'];
			$pago->unidadTiempo = "Días";
			$detalle_pago[] = $pago;
		}
		$factura->pagos = $detalle_pago;

		//para mostrar la casilla de propina dependiendo si esta asignada o no
		$resultado_tasa = $this->confi_facturacion($con, $serie_sucursal, $ruc_empresa)['tasa_turistica'];
		if ($resultado_tasa == "SI") {
			$concepto_tasa = "TASA TURISTICA";
		} else {
			$concepto_tasa = "OTROS";
		}

		$rubro = new rubro();
		$rubro->concepto = $concepto_tasa;
		$rubro->total = number_format($this->info_documento($con, $id_factura, "factura")['tasa_turistica'], 2, '.', '');
		$factura->otrosRubros = $rubro;

		//desde aqui detalle de adicionales de la factura
		$detalle_adicional_factura = mysqli_query($con, "SELECT * from detalle_adicional_factura WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_factura ='" . $serie_factura . "' and secuencial_factura='" . $numero_factura . "' ");

		$camposAdicionales = array();
		foreach ($detalle_adicional_factura as $detalle_adicional) {
			$nombre_adicional = $detalle_adicional['adicional_concepto'];
			$descripcion_adicional = $detalle_adicional['adicional_descripcion'];
			if ($nombre_adicional != null && $descripcion_adicional != null) {
				$campoAdicional = new campoAdicional();
				$campoAdicional->nombre = $nombre_adicional;
				$campoAdicional->valor = $descripcion_adicional;
				$camposAdicionales[] = $campoAdicional;
			}
		}
		$factura->infoAdicional = $camposAdicionales;
		$procesar = $this->procesar_comprobante($con, $factura, 'factura', $modo_envio, $id_factura, $ruc_empresa);
		return $procesar;
	}
	//hasta aqui la factura

	public function EnviarRetencion($con, $id_retencion, $modo_envio)
	{
		$documento = 'retencion';
		$ruc_empresa = $this->info_documento($con, $id_retencion, $documento)['ruc_empresa'];
		$serie_sucursal = $this->info_documento($con, $id_retencion, $documento)['serie_retencion'];
		$numero_documento = $this->info_documento($con, $id_retencion, $documento)['secuencial_retencion'];
		$id_proveedor = $this->info_documento($con, $id_retencion, $documento)['id_proveedor'];

		$retencion = new \comprobanteRetencion();
		$retencion->configAplicacion = $this->config_app($con, $documento, $serie_sucursal, $ruc_empresa);
		$retencion->configCorreo = $this->confi_mail($con, $modo_envio, $ruc_empresa);
		$retencion->ambiente = $this->confi_empresa($con, $ruc_empresa)['tipo_ambiente']; //[1,Prueba][2,Produccion] 
		$retencion->tipoEmision = $this->confi_empresa($con, $ruc_empresa)['tipo_emision']; //[1,Emision Normal][2,Emision Por Indisponibilidad del sistema
		$retencion->razonSocial = strtoupper($this->info_empresa($con, $ruc_empresa)['nombre']); //[Razon Social]
		$retencion->nombreComercial = strtoupper($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['nombre_sucursal']);  //[Nombre Comercial, si hay]*
		$retencion->ruc = substr($ruc_empresa, 0, 10) . '001'; //[Ruc]
		$retencion->codDoc = "07";
		$retencion->establecimiento = substr($serie_sucursal, 0, 3); //[pto de emision ] **
		$retencion->ptoEmision = substr($serie_sucursal, 4, 3);
		$retencion->secuencial = str_pad($numero_documento, 9, "000000000", STR_PAD_LEFT); // [Secuencia desde 1 (9)];
		$retencion->fechaEmision = date("d/m/Y", strtotime($this->info_documento($con, $id_retencion, $documento)['fecha_emision'])); //[Fecha (dd/mm/yyyy)]
		$retencion->dirMatriz = strtoupper($this->info_empresa($con, $ruc_empresa)['direccion']);
		$retencion->dirEstablecimiento = strtoupper($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['direccion_sucursal']);
		$retencion->contribuyenteEspecial = $this->confi_empresa($con, $ruc_empresa)['resol_cont'];
		$retencion->regimenRIMPE = $this->confi_empresa($con, $ruc_empresa)['tipo_regimen'] == 2 ? "SI" : "";
		$retencion->regimenRIMPENP = $this->confi_empresa($con, $ruc_empresa)['tipo_regimen'] == 3 ? "SI" : "";
		$retencion->agenteRetencion = $this->confi_empresa($con, $ruc_empresa)['agente_ret'] > 0 ? $this->confi_empresa($con, $ruc_empresa)['agente_ret'] : "";

		$tipo_empresa = $this->info_empresa($con, $ruc_empresa)['tipo'];
		switch ($tipo_empresa) {
			case "01":
				$lleva_contabilidad = "NO";
				break;
			case "02" || "03" || "04" || "05":
				$lleva_contabilidad = "SI";
				break;
		};
		$retencion->obligadoContabilidad = $lleva_contabilidad;
		$retencion->tipoIdentificacionSujetoRetenido = $this->info_proveedor($con, $id_proveedor)['tipo_id_proveedor'];
		$retencion->razonSocialSujetoRetenido = strtoupper($this->info_proveedor($con, $id_proveedor)['razon_social']);
		$retencion->identificacionSujetoRetenido = $this->info_proveedor($con, $id_proveedor)['ruc_proveedor'];

		$detalle_retencion = mysqli_query($con, "SELECT * from cuerpo_retencion as cr
		INNER JOIN retenciones_sri as rs ON cr.id_retencion = rs.id_ret 
		WHERE cr.ruc_empresa = '" . $ruc_empresa . "' and cr.serie_retencion ='" . $serie_sucursal . "' and cr.secuencial_retencion='" . $numero_documento . "' ");
		$impuestoArray = array();
		foreach ($detalle_retencion as $detalle_retencion_compras) {
			$impuesto = $detalle_retencion_compras['impuesto'];
			// se saca de la tabla 19 del sri
			switch ($impuesto) {
				case "RENTA":
					$codigo_impuesto = "1";
					break;
				case "IVA":
					$codigo_impuesto = "2";
					break;
				case "ISD":
					$codigo_impuesto = "6";
					break;
			};

			$impuesto = new \impuestoComprobanteRetencion(); // Impuesto del detalle
			$impuesto->codigo = $codigo_impuesto;
			$impuesto->codigoRetencion = $detalle_retencion_compras['codigo_impuesto'];
			$impuesto->baseImponible = number_format($detalle_retencion_compras['base_imponible'], 2, '.', '');
			$impuesto->porcentajeRetener = $detalle_retencion_compras['porcentaje_retencion'];
			$impuesto->valorRetenido = number_format($detalle_retencion_compras['valor_retenido'], 2, '.', '');
			$impuesto->codDocSustento = $this->info_documento($con, $id_retencion, $documento)['tipo_comprobante']; //tipo de documento
			$impuesto->numDocSustento = str_replace("-", "", $this->info_documento($con, $id_retencion, $documento)['numero_comprobante']); //numero de factura a retener
			$impuesto->fechaEmisionDocSustento = date("d/m/Y", strtotime($this->info_documento($con, $id_retencion, $documento)['fecha_documento']));
			$impuestoArray[] = $impuesto;
		}

		$retencion->periodoFiscal = $detalle_retencion_compras['ejercicio_fiscal'];

		//version 2.0.0, verificar de donde se obtiene esta informacion
		//$retencion->tipoSujetoRetenido = $this->info_proveedor($con, $id_proveedor)['tipo_sujeto']; // ejemplo: "01"
		//$retencion->paisPago = $this->info_proveedor($con, $id_proveedor)['pais_pago']; // ejemplo: "ECUADOR"
		//$retencion->aplicaConvenio = $this->info_proveedor($con, $id_proveedor)['aplica_convenio']; // "SI" o "NO"
		//$retencion->pagExtSujRetNorLeg = $this->info_proveedor($con, $id_proveedor)['pag_ext_suj_ret_nor_leg']; // "SI" o "NO"
		//$retencion->tipoRegi = $this->info_proveedor($con, $id_proveedor)['tipo_regimen_fiscal']; // "01", "02", etc.
		//$retencion->denopago = $this->info_proveedor($con, $id_proveedor)['denominacion_pago']; // texto libre


		$retencion->impuestos = $impuestoArray;

		//desde aqui detalle de adicionales de la retencion   
		$detalle_adicional_retencion = mysqli_query($con, "SELECT * FROM detalle_adicional_retencion 
		WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_retencion ='" . $serie_sucursal . "' 
		and secuencial_retencion='" . $numero_documento . "' ");

		$camposAdicionales = array();
		foreach ($detalle_adicional_retencion as $detalle_adicional) {
			$nombre_adicional = $detalle_adicional['adicional_concepto'];
			$descripcion_adicional = $detalle_adicional['adicional_descripcion'];
			//if ($nombre_adicional !=null && $descripcion_adicional !=null){
			$campoAdicional = new campoAdicional();
			$campoAdicional->nombre = $nombre_adicional;
			$campoAdicional->valor = $descripcion_adicional;
			$camposAdicionales[] = $campoAdicional;
			//}
		}
		$retencion->infoAdicional = $camposAdicionales;
		$procesar = $this->procesar_comprobante($con, $retencion, 'retencion', $modo_envio, $id_retencion, $ruc_empresa);
		return $procesar;
		//}	
	}
	//hasta aqui la retencion

	public function EnviarNc($con, $id_nc, $modo_envio)
	{
		$documento = 'nc';
		$ruc_empresa = $this->info_documento($con, $id_nc, $documento)['ruc_empresa'];
		$serie_sucursal = $this->info_documento($con, $id_nc, $documento)['serie_nc'];
		$numero_documento = $this->info_documento($con, $id_nc, $documento)['secuencial_nc'];
		$id_cliente = $this->info_documento($con, $id_nc, $documento)['id_cliente'];

		$notaCredito = new notaCredito();
		$notaCredito->configAplicacion = $this->config_app($con, $documento, $serie_sucursal, $ruc_empresa);
		$notaCredito->configCorreo = $this->confi_mail($con, $modo_envio, $ruc_empresa);
		$notaCredito->ambiente =  $this->confi_empresa($con, $ruc_empresa)['tipo_ambiente']; //[1,Prueba][2,Produccion]
		$notaCredito->tipoEmision = $this->confi_empresa($con, $ruc_empresa)['tipo_emision']; //[1,Emision Normal][2,Emision Por Indisponibilidad del sistema
		$notaCredito->razonSocial =  strtoupper($this->info_empresa($con, $ruc_empresa)['nombre']); //[Razon Social]
		$notaCredito->nombreComercial = strtoupper($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['nombre_sucursal']);  //[Nombre Comercial, si hay]*
		$notaCredito->ruc = substr($ruc_empresa, 0, 10) . '001'; //[Ruc]
		$notaCredito->codDoc = "04"; //[01, Factura] [04, Nota Credito] [05, Nota Debito] [06, Guia Remision] [07, Guia de Retencion]
		$notaCredito->establecimiento = substr($serie_sucursal, 0, 3); //[pto de emision ] **
		$notaCredito->ptoEmision = substr($serie_sucursal, 4, 3);
		$notaCredito->secuencial = str_pad($numero_documento, 9, "000000000", STR_PAD_LEFT); // [Secuencia desde 1 (9)];
		$notaCredito->fechaEmision = date("d/m/Y", strtotime($this->info_documento($con, $id_nc, $documento)['fecha_nc'])); //[Fecha (dd/mm/yyyy)]
		$notaCredito->dirMatriz = strtoupper($this->info_empresa($con, $ruc_empresa)['direccion']);
		$notaCredito->dirEstablecimiento = strtoupper($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['direccion_sucursal']);
		$notaCredito->contribuyenteEspecial = $this->confi_empresa($con, $ruc_empresa)['resol_cont'];
		$notaCredito->regimenRIMPE = $this->confi_empresa($con, $ruc_empresa)['tipo_regimen'] == 2 ? "SI" : "";
		$notaCredito->regimenRIMPENP = $this->confi_empresa($con, $ruc_empresa)['tipo_regimen'] == 3 ? "SI" : "";
		$notaCredito->agenteRetencion = $this->confi_empresa($con, $ruc_empresa)['agente_ret'] > 0 ? $this->confi_empresa($con, $ruc_empresa)['agente_ret'] : "";

		$tipo_empresa = $this->info_empresa($con, $ruc_empresa)['tipo'];
		switch ($tipo_empresa) {
			case "01":
				$lleva_contabilidad = "NO";
				break;
			case "02" || "03" || "04" || "05":
				$lleva_contabilidad = "SI";
				break;
		};
		$notaCredito->obligadoContabilidad = $lleva_contabilidad; // [SI]
		$notaCredito->tipoIdentificacionComprador = $this->info_cliente($con, $id_cliente)['tipo_id']; //Info comprador [04, RUC][05,Cedula][06, Pasaporte][07, Consumidor final][08, Exterior][09, Placa]
		$notaCredito->razonSocialComprador = strtoupper($this->info_cliente($con, $id_cliente)['nombre']); //Razon social o nombres y apellidos comprador
		$notaCredito->rise = "";
		$notaCredito->identificacionComprador = $this->info_cliente($con, $id_cliente)['ruc']; // Identificacion Comprador
		$notaCredito->codDocModificado = "01";
		$documento_modificado = $this->info_documento($con, $id_nc, $documento)['factura_modificada'];
		$notaCredito->numDocModificado = $documento_modificado;
		//traer la fecha de la factura modificada
		$fecha_factura_modificada = $this->info_documento($con, $id_nc, $documento)['fecha_factura'];
		$notaCredito->fechaEmisionDocSustento = date("d/m/Y", strtotime($fecha_factura_modificada));
		//suma subtotales
		$subtotales_nc = mysqli_query($con, "SELECT ROUND(sum(subtotal_nc-descuento),2) as subtotal_nota, 
		ROUND(sum(descuento),2) as descuento_nc FROM cuerpo_nc 
		WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_nc ='" . $serie_sucursal . "' 
		and secuencial_nc='" . $numero_documento . "'");
		$row_subtotales_nc = mysqli_fetch_array($subtotales_nc);
		$notaCredito->totalSinImpuestos = number_format($row_subtotales_nc['subtotal_nota'], 2, '.', '');
		$notaCredito->valorModificacion = number_format($this->info_documento($con, $id_nc, $documento)['total_nc'], 2, '.', '');
		$notaCredito->moneda = $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['moneda_sucursal']; //DOLAR;

		//consulta de la tabla de impuestos de ventas
		$codigo_impuesto_en_totales = "2"; //$totales_detalle_impuestos_ventas['codigo_impuesto'];	
		if ($codigo_impuesto_en_totales == "2") {
			$totales_nc = array();
			$subtotales_tarifa_iva = mysqli_query($con, "SELECT 
			ROUND(sum((cnc.cantidad_nc * cnc.valor_unitario_nc - cnc.descuento) * (ti.porcentaje_iva /100)),2) as total_iva, 
			ti.codigo as codigo_porcentaje, 
			ROUND(sum(cnc.subtotal_nc - cnc.descuento),2) as subtotal_nc 
			FROM cuerpo_nc as cnc INNER JOIN tarifa_iva as ti ON ti.codigo = cnc.tarifa_iva
			WHERE cnc.ruc_empresa ='" . $ruc_empresa . "' 
			and cnc.serie_nc='" . $serie_sucursal . "' and cnc.secuencial_nc ='" . $numero_documento . "' group by cnc.tarifa_iva ");
			foreach ($subtotales_tarifa_iva as $totales_subtotal_tarifa) {
				$totalImpuesto = new totalImpuesto();
				$totalImpuesto->codigo = "2"; //[2, IVA][3,ICE][5, IRBPNR]
				$totalImpuesto->codigoPorcentaje = $totales_subtotal_tarifa['codigo_porcentaje']; // IVA -> [0, 0%][2, 12%][6, No objeto de impuesto][7, Exento de IVA] ICE->[Tabla 19]
				$totalImpuesto->baseImponible = number_format($totales_subtotal_tarifa['subtotal_nc'], 2, '.', ''); // Suma de los impuesto del mismo cod y % (0.00)										
				$totalImpuesto->valor = number_format($totales_subtotal_tarifa['total_iva'], 2, '.', ''); // Suma de los impuesto del mismo cod y % aplicado el % (0.00)
				$totales_nc[] = $totalImpuesto;
			}
		}

		$notaCredito->totalConImpuesto = $totales_nc;
		//desde aqui detalle de la nc y productos
		$detalle_nc = mysqli_query($con, "SELECT * from cuerpo_nc WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_nc ='" . $serie_sucursal . "' and secuencial_nc='" . $numero_documento . "' ");
		$detalle_nc_item = array();
		foreach ($detalle_nc as $detalle_final) {
			$detalle = new detalleNotaCredito();
			$detalle->codigoInterno = $detalle_final['codigo_producto']; // Codigo del Producto
			$detalle->codigoAdicional = $detalle_final['codigo_producto']; // Opcional
			$detalle->descripcion = ucwords($detalle_final['nombre_producto']); // Nombre del producto
			$detalle->cantidad = number_format($detalle_final['cantidad_nc'], ($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['decimal_cant'] == '1') ? 0 : $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['decimal_cant'], '.', ''); // Cantidad
			$detalle->precioUnitario = number_format($detalle_final['valor_unitario_nc'], $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['decimal_doc'], '.', ''); // Valor unitario; // Valor unitario
			$detalle->descuento = number_format($detalle_final['descuento'], 2, '.', ''); // Descuento u
			$detalle->precioTotalSinImpuesto = number_format($detalle_final['subtotal_nc'] - $detalle_final['descuento'], 2, '.', ''); // Valor sin impuesto
			$impuesto = new impuesto(); // Impuesto del detalle
			$impuesto->codigo = "2"; //del impuesto, iva, ice, bp	
			$impuesto->codigoPorcentaje = $detalle_final['tarifa_iva'];
			//para traer la tarifa de iva para el detalle de cada item de la nc
			$detalle_tarifa_iva = mysqli_query($con, "SELECT * from tarifa_iva WHERE codigo='" . $detalle_final['tarifa_iva'] . "' ");
			$row_detalle_tarifa_iva = mysqli_fetch_array($detalle_tarifa_iva);
			$impuesto->tarifa = $row_detalle_tarifa_iva['porcentaje_iva'];
			$impuesto->baseImponible = number_format($detalle_final['subtotal_nc'] - $detalle_final['descuento'], 2, '.', ''); // subtotal o base
			$impuesto->valor = number_format(($detalle_final['cantidad_nc'] * $detalle_final['valor_unitario_nc'] - $detalle_final['descuento']) * ($row_detalle_tarifa_iva['porcentaje_iva'] / 100), 2, '.', ''); // valor o sea el 12 por ciento de la base
			$detalle->impuestos = $impuesto;
			$detalle_nc_item[] = $detalle;
		}

		$notaCredito->detalles = $detalle_nc_item;
		//hasta aqui detalle de la nc
		$notaCredito->motivo = strtoupper($this->info_documento($con, $id_nc, $documento)['motivo']);

		//desde aqui detalle de adicionales de la nc
		$detalle_adicional_nc = mysqli_query($con, "SELECT * from detalle_adicional_nc WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_nc ='" . $serie_sucursal . "' and secuencial_nc='" . $numero_documento . "' ");

		$camposAdicionales = array();
		foreach ($detalle_adicional_nc as $detalle_adicional) {
			$nombre_adicional = $detalle_adicional['adicional_concepto'];
			$descripcion_adicional = $detalle_adicional['adicional_descripcion'];
			if ($nombre_adicional != null && $descripcion_adicional != null) {
				$campoAdicional = new campoAdicional();
				$campoAdicional->nombre = $nombre_adicional;
				$campoAdicional->valor = $descripcion_adicional;
				$camposAdicionales[] = $campoAdicional;
			}
		}
		$notaCredito->infoAdicional = $camposAdicionales;
		$procesar = $this->procesar_comprobante($con, $notaCredito, 'nc', $modo_envio, $id_nc, $ruc_empresa);
		return $procesar;
	}

	//para enviar gr
	public function EnviarGr($con, $id_gr, $modo_envio)
	{
		$documento = 'gr';
		$ruc_empresa = $this->info_documento($con, $id_gr, $documento)['ruc_empresa'];
		$serie_sucursal = $this->info_documento($con, $id_gr, $documento)['serie_gr'];
		$numero_documento = $this->info_documento($con, $id_gr, $documento)['secuencial_gr'];
		$id_transportista = $this->info_documento($con, $id_gr, $documento)['id_transportista'];
		$id_cliente = $this->info_documento($con, $id_gr, $documento)['id_cliente'];

		$guiaRemision = new guiaRemision();
		$guiaRemision->configAplicacion = $this->config_app($con, $documento, $serie_sucursal, $ruc_empresa);
		$guiaRemision->configCorreo = $this->confi_mail($con, $modo_envio, $ruc_empresa);
		$guiaRemision->ambiente =  $this->confi_empresa($con, $ruc_empresa)['tipo_ambiente']; //[1,Prueba][2,Produccion]
		$guiaRemision->tipoEmision = $this->confi_empresa($con, $ruc_empresa)['tipo_emision']; //[1,Emision Normal][2,Emision Por Indisponibilidad del sistema
		$guiaRemision->razonSocial =  strtoupper($this->info_empresa($con, $ruc_empresa)['nombre']); //[Razon Social]
		$guiaRemision->nombreComercial = strtoupper($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['nombre_sucursal']);  //[Nombre Comercial, si hay]*
		$guiaRemision->ruc = substr($ruc_empresa, 0, 10) . '001'; //[Ruc]
		$guiaRemision->codDoc = "06"; //[01, Factura] [04, Nota Credito] [05, Nota Debito] [06, Guia Remision] [07, Guia de Retencion]
		$guiaRemision->establecimiento = substr($serie_sucursal, 0, 3); //[pto de emision ] **
		$guiaRemision->ptoEmision = substr($serie_sucursal, 4, 3);
		$guiaRemision->secuencial = str_pad($numero_documento, 9, "000000000", STR_PAD_LEFT); // [Secuencia desde 1 (9)];
		$guiaRemision->fechaEmision = date("d/m/Y", strtotime($this->info_documento($con, $id_gr, $documento)['fecha_gr'])); //[Fecha (dd/mm/yyyy)]
		$guiaRemision->dirMatriz = strtoupper($this->info_empresa($con, $ruc_empresa)['direccion']);
		$guiaRemision->dirEstablecimiento = strtoupper($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['direccion_sucursal']);
		$guiaRemision->contribuyenteEspecial = $this->confi_empresa($con, $ruc_empresa)['resol_cont'];
		$guiaRemision->regimenRIMPE = $this->confi_empresa($con, $ruc_empresa)['tipo_regimen'] == 2 ? "SI" : "";
		$guiaRemision->regimenRIMPENP = $this->confi_empresa($con, $ruc_empresa)['tipo_regimen'] == 3 ? "SI" : "";
		$guiaRemision->agenteRetencion = $this->confi_empresa($con, $ruc_empresa)['agente_ret'] > 0 ? $this->confi_empresa($con, $ruc_empresa)['agente_ret'] : "";

		$tipo_empresa = $this->info_empresa($con, $ruc_empresa)['tipo'];

		switch ($tipo_empresa) {
			case "01":
				$lleva_contabilidad = "NO";
				break;
			case "02" or "03" or "04" or "05":
				$lleva_contabilidad = "SI";
				break;
		};
		$guiaRemision->obligadoContabilidad = $lleva_contabilidad; // [SI]
		$destinatarios = array();
		$destinatario = new destinatario();
		$destinatario->codEstabDestino = $this->info_documento($con, $id_gr, $documento)['cod_est_destino'];
		//trae el detalle de la guia de remision
		$busca_detalle_guia = mysqli_query($con, "SELECT * FROM cuerpo_gr WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_gr ='" . $serie_sucursal . "' and secuencial_gr='" . $numero_documento . "'");
		$detalles = array();
		foreach ($busca_detalle_guia as $info_detalle_guia) {
			$detalleGuiaRemision = new detalleGuiaRemision();

			$detalle_producto = mysqli_query($con, "SELECT * from productos_servicios WHERE id ='" . $info_detalle_guia['id_producto'] . "' ");
			$row_detalle_producto = mysqli_fetch_array($detalle_producto);
			$codigo_auxiliar = $row_detalle_producto['codigo_auxiliar'];

			$detalleGuiaRemision->cantidad = number_format($info_detalle_guia['cantidad_gr'], ($this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['decimal_cant'] == '1') ? 0 : $this->info_sucursal($con, $serie_sucursal, $ruc_empresa)['decimal_cant'], '.', ''); // Codigo del Producto
			$detalleGuiaRemision->codigoAdicional = strtoupper($codigo_auxiliar); // Opcional
			$detalleGuiaRemision->codigoInterno = strtoupper($info_detalle_guia['codigo_producto']); // codigo
			$detalleGuiaRemision->descripcion = strtoupper($info_detalle_guia['nombre_producto']); // Nombre del producto
			$detalles[] = $detalleGuiaRemision;
		}
		//HASTA AQUI EL DETALLE DE LA GUIA
		$destinatario->detalles = $detalles;
		$destinatario->dirDestinatario = strtoupper($this->info_documento($con, $id_gr, $documento)['destino']);
		$destinatario->docAduaneroUnico = $this->info_documento($con, $id_gr, $documento)['cod_aduanero'];
		$documento_modificado = $this->info_documento($con, $id_gr, $documento)['factura_aplica'];
		//modificar esto, debe existir la factura para poder cargar la info o sino se debe completar en el formulario
		if (!empty($documento_modificado)) {
			$destinatario->codDocSustento = "01";
			$destinatario->numDocSustento = $this->info_documento($con, $id_gr, $documento)['factura_aplica'];
			$datos_factura_modificada = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE ruc_empresa='" . $ruc_empresa . "' and serie_factura = '" . substr($documento_modificado, 0, 7) . "' and secuencial_factura='" . substr($documento_modificado, 8, 9) . "'");
			$row_datos_factura_modificada = mysqli_fetch_array($datos_factura_modificada);
			$destinatario->numAutDocSustento = $row_datos_factura_modificada['aut_sri'];
			$destinatario->fechaEmisionDocSustento = date("d/m/Y", strtotime($row_datos_factura_modificada['fecha_factura']));
		}
		$destinatario->identificacionDestinatario = $this->info_cliente($con, $id_cliente)['ruc'];
		$destinatario->motivoTraslado = strtoupper($this->info_documento($con, $id_gr, $documento)['motivo']);
		$destinatario->razonSocialDestinatario = strtoupper($this->info_cliente($con, $id_cliente)['nombre']);
		$destinatario->ruta = strtoupper($this->info_documento($con, $id_gr, $documento)['ruta']);
		$destinatarios[] = $destinatario;

		$guiaRemision->destinatarios = $destinatarios; //destinatario;
		$guiaRemision->dirPartida = strtoupper($this->info_documento($con, $id_gr, $documento)['origen']);
		$guiaRemision->fechaFinTransporte = date("d/m/Y", strtotime($this->info_documento($con, $id_gr, $documento)['fecha_llegada']));
		$guiaRemision->fechaIniTransporte = date("d/m/Y", strtotime($this->info_documento($con, $id_gr, $documento)['fecha_salida']));

		//desde aqui detalle de adicionales de la guia
		$detalle_adicional_guia = mysqli_query($con, "SELECT * from detalle_adicional_gr WHERE ruc_empresa = '" . $ruc_empresa . "' and serie_gr='" . $serie_sucursal . "' and secuencial_gr='" . $numero_documento . "' ");

		$camposAdicionales = array();
		foreach ($detalle_adicional_guia as $detalle_adicional) {
			$nombre_adicional = $detalle_adicional['adicional_concepto'];
			$descripcion_adicional = $detalle_adicional['adicional_descripcion'];
			if ($nombre_adicional != null && $descripcion_adicional != null) {
				$campoAdicional = new campoAdicional();
				$campoAdicional->nombre = $nombre_adicional;
				$campoAdicional->valor = $descripcion_adicional;
				$camposAdicionales[] = $campoAdicional;
			}
		}
		$guiaRemision->infoAdicional = $camposAdicionales;
		$guiaRemision->placa = strtoupper($this->info_documento($con, $id_gr, $documento)['placa']);
		$guiaRemision->razonSocialTransportista = strtoupper($this->info_cliente($con, $id_transportista)['nombre']); //Razon social o nombres y apellidos comprador
		$guiaRemision->rise = "";
		$guiaRemision->rucTransportista = $this->info_cliente($con, $id_transportista)['ruc']; // Identificacion Comprador
		$guiaRemision->tipoIdentificacionTransportista = $this->info_cliente($con, $id_transportista)['tipo_id']; //Info comprador [04, RUC][05,Cedula][06, Pasaporte][07, Consumidor final][08, Exterior][09, Placa]
		$procesar = $this->procesar_comprobante($con, $guiaRemision, 'gr', $modo_envio, $id_gr, $ruc_empresa);
		return $procesar;
	}

	public function procesar_comprobante($con, $documento_a_procesar, $documento, $modo_envio, $id_documento, $ruc_empresa)
	{

		$encabezado_tabla = "encabezado_" . $documento;
		switch ($documento) {
			case "factura":
				$id_encabezado_documento =  "id_encabezado_factura";
				$ruc_cliente_proveedor = "cli.ruc";
				$tabla_cliente_proveedor = "clientes as cli";
				$relacion_cliente_proveedor = "cli.id=enc.id_cliente";
				break;
			case "retencion":
				$id_encabezado_documento =  "id_encabezado_retencion";
				$ruc_cliente_proveedor = "pro.ruc_proveedor";
				$tabla_cliente_proveedor = "proveedores as pro";
				$relacion_cliente_proveedor = "pro.id_proveedor=enc.id_proveedor";
				break;
			case "liquidacion":
				$id_encabezado_documento =  "id_encabezado_liq";
				$ruc_cliente_proveedor = "";
				$tabla_cliente_proveedor = "";
				$relacion_cliente_proveedor = "";
				break;
			case "nc":
				$id_encabezado_documento =  "id_encabezado_nc";
				$ruc_cliente_proveedor = "cli.ruc";
				$tabla_cliente_proveedor = "clientes as cli";
				$relacion_cliente_proveedor = "cli.id=enc.id_cliente";
				break;
			case "gr":
				$id_encabezado_documento =  "id_encabezado_gr";
				$ruc_cliente_proveedor = "";
				$tabla_cliente_proveedor = "";
				$relacion_cliente_proveedor = "";
				break;
		}

		$procesarComprobanteElectronico = new ProcesarComprobanteElectronico();
		//para generar el pdf y xml sin enviar mail
		if ($modo_envio == 'offline') {
			$procesarComprobante = new generarXMLPDF();
			//$respuestaPdf = new procesarXMLResponse();
			$procesarComprobante->comprobante = $documento_a_procesar;
			$procesarComprobante->envioEmail = false; // false para que NO se envie el correo
			$respuesta = $procesarComprobanteElectronico->generarXMLPDF($procesarComprobante);
			//var_dump($documento_a_procesar);
			echo "<script>
				$.notify('Pdf y xml Generados con éxito','success');
				</script>";
			//var_dump($documento_a_procesar);
		}

		//para enviar a autorizar al sri y luego enviar el correo
		if ($modo_envio == 'online') {
			//actualizar la respuesta anterior del sri
			$sql_update_status = mysqli_query($con, "UPDATE respuestas_sri SET status='0' WHERE id_documento = '" . $id_documento . "' and documento = '" . $documento . "' ");
			$procesarComprobante = new procesarComprobante();
			$procesarComprobante->comprobante = $documento_a_procesar;
			$procesarComprobante->envioSRI = false; // false para que NO se envie directamente al sri y solo firme
			$respuesta_firma = $procesarComprobanteElectronico->procesarComprobante($procesarComprobante);
			if ($respuesta_firma->return->estadoComprobante == "FIRMADO") {
				$procesarComprobante->comprobante = $documento_a_procesar;
				$procesarComprobante->envioSRI = true; // true envia el comprobante ya firmado al sri  y false es para que me bote solo el pdf
				$respuesta_post_firma = $procesarComprobanteElectronico->procesarComprobante($procesarComprobante);

				if ($respuesta_post_firma) {
					$estado_sri = $respuesta_post_firma->return->estadoComprobante;
					$aut_sri = $respuesta_post_firma->return->claveAcceso;
					$numeroAutorizacion = $respuesta_post_firma->return->numeroAutorizacion;
					$comprobanteID = $respuesta_post_firma->return->comprobanteID;
					$mensajes = $respuesta_post_firma->return->mensajes;
					$error_sri = $respuesta_post_firma->return->mensajes->mensaje;
					$detalle_error_sri = $respuesta_post_firma->return->mensajes->informacionAdicional;
					$detalle_error_sri = $detalle_error_sri == "" ? "" : $detalle_error_sri;
					$fechaAutorizacion = date('Y/m/d H:i:s');

					//para PONER EN EL ESTADO DEL SRI COMO AUTORIZADA
					if ($estado_sri == "AUTORIZADO") {
						$actualiza_estado_autorizado = mysqli_query($con, "UPDATE $encabezado_tabla SET estado_sri='AUTORIZADO', ambiente = '" . $this->confi_empresa($con, $ruc_empresa)['tipo_ambiente'] . "', aut_sri='" . $aut_sri . "', estado_mail='ENVIADO' WHERE $id_encabezado_documento = '" . $id_documento . "'");
						$this->respuestas_sri($con, $ruc_empresa, $documento, $id_documento, "AUTORIZADO", $numeroAutorizacion, $aut_sri, $mensajes, $comprobanteID, $fechaAutorizacion);

						//para que me registre el documento en el cliente o proveedor como compras, retenciones de ventas, nc
						if (!empty($ruc_cliente_proveedor)) {
							$sql_ruc_cliente_proveedor = mysqli_query($con, "SELECT $ruc_cliente_proveedor as ruc_cliente_proveedor 
					FROM $encabezado_tabla as enc 
					INNER JOIN $tabla_cliente_proveedor ON $relacion_cliente_proveedor WHERE enc.aut_sri='" . $aut_sri . "'");
							$row_ruc_cliente_proveedor = mysqli_fetch_array($sql_ruc_cliente_proveedor);
							$ruc_cliente_proveedor = isset($row_ruc_cliente_proveedor['ruc_cliente_proveedor']) ? $row_ruc_cliente_proveedor['ruc_cliente_proveedor'] : "";
							if (!empty($ruc_cliente_proveedor)) {
								$rides_sri = new rides_sri();
								$archivo_xml = $rides_sri->lee_ride($aut_sri);
								$resultado = $rides_sri->lee_archivo_xml($archivo_xml, $ruc_cliente_proveedor, '1', $con);
							}
						}
					} else if ($estado_sri == "DEVUELTA" && $error_sri == "CLAVE ACCESO REGISTRADA") {
						$actualiza_estado_devuelto = mysqli_query($con, "UPDATE $encabezado_tabla SET estado_sri='AUTORIZADO', ambiente = '" . $this->confi_empresa($con, $ruc_empresa)['tipo_ambiente'] . "', aut_sri='" . $aut_sri . "', estado_mail='ENVIADO' WHERE $id_encabezado_documento = '" . $id_documento . "'");
						$this->respuestas_sri($con, $ruc_empresa, $documento, $id_documento, "AUTORIZADO", $numeroAutorizacion, $aut_sri, $mensajes, $comprobanteID, $fechaAutorizacion);
					} else if ($estado_sri == "DEVUELTA" && $error_sri == "ERROR SECUENCIAL REGISTRADO") {
						$actualiza_estado_devuelto = mysqli_query($con, "UPDATE $encabezado_tabla SET estado_sri='AUTORIZADO', ambiente = '" . $this->confi_empresa($con, $ruc_empresa)['tipo_ambiente'] . "', aut_sri='" . $aut_sri . "', estado_mail='ENVIADO' WHERE $id_encabezado_documento = '" . $id_documento . "'");
						$this->respuestas_sri($con, $ruc_empresa, $documento, $id_documento, "AUTORIZADO", $numeroAutorizacion, $aut_sri, $mensajes, $comprobanteID, $fechaAutorizacion);
					} else {
						$actualiza_estado_devuelto = mysqli_query($con, "UPDATE $encabezado_tabla SET estado_sri='PENDIENTE' WHERE $id_encabezado_documento = '" . $id_documento . "'");
						$this->respuestas_sri($con, $ruc_empresa, $documento, $id_documento, $estado_sri, '', '', $error_sri . " detalle:" . $detalle_error_sri, '', $fechaAutorizacion);
					}
				} else {
					//no hay una respuesta del sri
					$actualiza_estado_devuelto = mysqli_query($con, "UPDATE $encabezado_tabla SET estado_sri='PENDIENTE' WHERE $id_encabezado_documento = '" . $id_documento . "'");
					$this->respuestas_sri($con, $ruc_empresa, $documento, $id_documento, "NO AUTORIZADO", "0", "0", "NO HAY UNA RESPUESTA DEL SRI, ENVIAR NUEVAMENTE EN UNOS 2 MIN", "0", date('Y/m/d H:i:s'));
				}
			} else {
				$actualiza_estado_devuelto = mysqli_query($con, "UPDATE $encabezado_tabla SET estado_sri='PENDIENTE' WHERE $id_encabezado_documento = '" . $id_documento . "'");
				$this->respuestas_sri($con, $ruc_empresa, $documento, $id_documento, "DEVUELTA", "0", "0", "ERROR AL FIRMAR EL DOCUMENTO, VERIFICAR VALIDEZ O INTENTAR DE NUEVO", "0", date('Y/m/d H:i:s'));
			}
		}
		//return false;
	}

	//procesar proformas
	public function procesar_proforma($con, $documento_a_procesar, $documento, $serie_documento, $secuencial_documento, $id_documento)
	{
		$encabezado_tabla = "encabezado_" . $documento;
		$procesarComprobanteElectronico = new ProcesarComprobanteElectronico();
		$procesarProforma = new procesarProforma();
		$procesarProforma->proforma = $documento_a_procesar;
		$respuesta = $procesarComprobanteElectronico->procesarProforma($procesarProforma);

		$estado_proforma = $respuesta->return->estadoComprobante;
		$estado_correo = $respuesta->return->mensajes->mensaje;

		if ($estado_proforma == "CREADA") {
			//para comprobar el envio del correo
			if (empty($estado_correo)) {
				$actualiza_estado = mysqli_query($con, "UPDATE $encabezado_tabla SET estado_proforma='ENVIADA', estado_mail='ENVIADO' WHERE id_encabezado_proforma = '" . $id_documento . "' ");
				return "<div class='alert alert-success' role='alert'><span class='glyphicon glyphicon-ok'></span> " . strtoupper($documento) . " " . $serie_documento . "-" . str_pad($secuencial_documento, 9, "000000000", STR_PAD_LEFT) . ' ENVIADA A CORREO' . "</div><br>";
			} else {
				$actualiza_estado = mysqli_query($con, "UPDATE $encabezado_tabla SET estado_proforma='ENVIADA', estado_mail='PENDIENTE' WHERE id_encabezado_proforma = '" . $id_documento . "' ");
				return "<div class='alert alert-warning' role='alert'><span class='glyphicon glyphicon-circle'></span> " . strtoupper($documento) . " " . $serie_documento . "-" . str_pad($secuencial_documento, 9, "000000000", STR_PAD_LEFT) . ' CREADA. ' . $estado_correo . "</div><br>";
			}
		} else {
			return "<div class='alert alert-danger' role='alert'><span class='glyphicon glyphicon-ban-circle'></span> No se pudo crear la " . strtoupper($documento) . " " . $serie_documento . "-" . str_pad($secuencial_documento, 9, "000000000", STR_PAD_LEFT) . ", vuelva a intentarlo.</div>" . "<br>";
		}
	}

	public function respuestas_sri($con, $ruc_empresa, $documento, $id_documento, $estado_sri, $numeroAutorizacion, $aut_sri, $mensajes, $comprobanteID, $fechaAutorizacion)
	{
		$respuestas_sri = mysqli_query($con, "INSERT INTO respuestas_sri (ruc_empresa,
					documento,
					id_documento,
					estadoComprobante,
					numeroAutorizacion,
					claveAcceso,
					mensajes,
					comprobanteID,
					fechaAutorizacion)
			VALUES ('" . $ruc_empresa . "',
					'" . $documento . "',
					'" . $id_documento . "',
					'" . $estado_sri . "',
					'" . $numeroAutorizacion . "',
					'" . $aut_sri . "',
					'" . mysqli_real_escape_string($con, $mensajes) . "',
					'" . $comprobanteID . "',
					'" . $fechaAutorizacion . "')");
	}
}
