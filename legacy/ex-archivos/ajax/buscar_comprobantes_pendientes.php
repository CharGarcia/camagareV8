<?php
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

switch ($action) {
	case 'buscar_comp':
		include("../core/db.php");
		$db = new db();

		if (isset($_GET['desde']) && isset($_GET['hasta']) && isset($_GET['documento'])) {
			$documento = $_GET['documento'];

			if ($db->connect()) {
				switch ($documento) {
					case "factura":
						$data = $db->select("SELECT ef.id_encabezado_factura as id, ef.fecha_factura as fecha, cl.nombre as cliente, ef.serie_factura as serie, 
										ef.secuencial_factura as secuencial, ef.total_factura as total
										 FROM encabezado_factura ef, clientes cl
										 WHERE ef.estado_sri= 'PENDIENTE' 
										 AND DATE_FORMAT(ef.fecha_factura, '%Y/%m/%d') BETWEEN " . $db->var2str(date("Y/m/d", strtotime($_GET['desde']))) . " 
										 AND " . $db->var2str(date("Y/m/d", strtotime($_GET['hasta']))) . "
										 AND ef.ruc_empresa = " . $db->var2str($ruc_empresa) . "
										 AND ef.id_cliente = cl.id;");
						$documento_buscado = "Factura";
						break;
					case "retencion":
						$data = $db->select("SELECT er.id_encabezado_retencion as id, er.fecha_emision as fecha, pr.razon_social as cliente, er.serie_retencion as serie, 
										er.secuencial_retencion as secuencial, er.total_retencion as total
										 FROM encabezado_retencion er, proveedores pr
										 WHERE er.estado_sri= 'PENDIENTE' 
										 AND DATE_FORMAT(er.fecha_emision, '%Y/%m/%d') BETWEEN " . $db->var2str(date("Y/m/d", strtotime($_GET['desde']))) . " 
										 AND " . $db->var2str(date("Y/m/d", strtotime($_GET['hasta']))) . "
										 AND er.ruc_empresa = " . $db->var2str($ruc_empresa) . "
										 AND er.id_proveedor = pr.id_proveedor;");
						$documento_buscado = "Retención";
						break;
					case "nc":
						$data = $db->select("SELECT enc.id_encabezado_nc as id, enc.fecha_nc as fecha, cl.nombre as cliente, enc.serie_nc as serie, 
										enc.secuencial_nc as secuencial, enc.total_nc as total
										 FROM encabezado_nc enc, clientes cl
										 WHERE enc.estado_sri= 'PENDIENTE' 
										 AND DATE_FORMAT(enc.fecha_nc, '%Y/%m/%d') BETWEEN " . $db->var2str(date("Y/m/d", strtotime($_GET['desde']))) . " 
										 AND " . $db->var2str(date("Y/m/d", strtotime($_GET['hasta']))) . "
										 AND enc.ruc_empresa = " . $db->var2str($ruc_empresa) . "
										 AND enc.id_cliente = cl.id;");
						$documento_buscado = "Nota de crédito";
						break;
					case "gr":
						$data = $db->select("SELECT egr.id_encabezado_gr as id, egr.fecha_gr as fecha, cl.nombre as cliente, egr.serie_gr as serie, 
										egr.secuencial_gr as secuencial, '' as total
										 FROM encabezado_gr egr, clientes cl
										 WHERE egr.estado_sri= 'PENDIENTE' 
										 AND DATE_FORMAT(egr.fecha_gr, '%Y/%m/%d') BETWEEN " . $db->var2str(date("Y/m/d", strtotime($_GET['desde']))) . " 
										 AND " . $db->var2str(date("Y/m/d", strtotime($_GET['hasta']))) . "
										 AND egr.ruc_empresa = " . $db->var2str($ruc_empresa) . "
										 AND egr.id_cliente = cl.id;");
						$documento_buscado = "Guía de remisión";
						break;
					case "nd":
						$documento_buscado = "Nota de débito";
						break;
					case "liquidacion":
						$data = $db->select("SELECT el.id_encabezado_liq as id, el.fecha_liquidacion as fecha, pr.razon_social as cliente, el.serie_liquidacion as serie, 
										el.secuencial_liquidacion as secuencial, el.total_liquidacion as total
										 FROM encabezado_liquidacion el, proveedores pr
										 WHERE el.estado_sri= 'PENDIENTE' 
										 AND DATE_FORMAT(el.fecha_liquidacion, '%Y/%m/%d') BETWEEN " . $db->var2str(date("Y/m/d", strtotime($_GET['desde']))) . " 
										 AND " . $db->var2str(date("Y/m/d", strtotime($_GET['hasta']))) . "
										 AND el.ruc_empresa = " . $db->var2str($ruc_empresa) . "
										 AND el.id_proveedor = pr.id_proveedor;");
						$documento_buscado = "Liquidación de compras / servicios";
				};
				$datos_procesados = array();
				foreach ($data as $value) {
					$datos_procesados[] = array(
						'id' => $value['id'],
						'fecha' => date("d/m/Y", strtotime($value['fecha'])),
						'cliente' => $value['cliente'],
						'num_doc' => $value['serie'] . '-' . str_pad($value['secuencial'], 9, '0', STR_PAD_LEFT),
						'documento' => $documento_buscado,
						'total' => '$' . $value['total'],
					);
				}

				$db->close();
			} else {
				echo "¡Imposible conectar con la base de datos!";
			}
			header('Content-Type: application/json');
			echo json_encode($datos_procesados);
		}

		break;

		/* case 'procesar_comp':
		if (isset($_POST['comp_select'])) {
			$comp_select =  explode(',', $_POST['comp_select']);
			$tipo_documento =  $_POST['documento'];
			if (is_array($comp_select)) {
				include("../facturacion_electronica/enviarComprobantesSri.php");
				include_once("../conexiones/conectalogin.php");
				$con = conenta_login();
				$enviarComprobantesSri = new enviarComprobantesSri();
				foreach ($comp_select as $comp) {
					switch ($tipo_documento) {
						case "1":
							$respuestaProceso = $enviarComprobantesSri->enviarFactura($con, $comp, 'online');
							echo $respuestaProceso;
							break;
						case "2":
							$respuestaProceso = $enviarComprobantesSri->EnviarRetencion($con, $comp, 'online');
							echo $respuestaProceso;
							break;
						case "3":
							$respuestaProceso = $enviarComprobantesSri->EnviarNc($con, $comp, 'online');
							echo $respuestaProceso;
							break;
						case "4":
							$respuestaProceso = $enviarComprobantesSri->EnviarGr($con, $comp, 'online');
							echo $respuestaProceso;
							break;
						case "6":
							$respuestaProceso = $enviarComprobantesSri->EnviarLc($con, $comp, 'online');
							echo $respuestaProceso;
							break;
					}
				}
			}
		}
		break; */
}
