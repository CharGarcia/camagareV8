<?php
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

	switch ($action) {
		case 'buscar_comp':
		include("../core/db.php");
		$db = new db();

			if ( isset($_GET['desde']) && isset($_GET['hasta']) && isset($_GET['documento'])) {
				$documento=$_GET['documento'];

		if ($db->connect()) {	
				switch ($documento) {
					case "1":
					$data = $db->select("SELECT ef.id_encabezado_factura as id, ef.fecha_factura as fecha, cl.nombre as cliente, ef.serie_factura as serie, 
										ef.secuencial_factura as secuencial, cl.email as mail
										 FROM encabezado_factura ef, clientes cl
										 WHERE estado_mail= 'PENDIENTE' and estado_sri= 'AUTORIZADO'
										 AND fecha_factura BETWEEN ".$db->var2str(date("Y-m-d", strtotime($_GET['desde'])))." 
										 AND ".$db->var2str(date("Y-m-d", strtotime($_GET['hasta'])))."
										 AND ef.ruc_empresa = " . $db->var2str($ruc_empresa) . "
										 AND ef.id_cliente = cl.id;");
					$documento_buscado="Factura";
						break;
					case "2":
					$data = $db->select("SELECT er.id_encabezado_retencion as id, er.fecha_emision as fecha, pr.razon_social as cliente, er.serie_retencion as serie, 
										er.secuencial_retencion as secuencial, pr.mail_proveedor as mail
										 FROM encabezado_retencion er, proveedores pr
										 WHERE estado_mail= 'PENDIENTE' and estado_sri= 'AUTORIZADO'
										 AND fecha_emision BETWEEN ".$db->var2str(date("Y-m-d", strtotime($_GET['desde'])))." 
										 AND ".$db->var2str(date("Y-m-d", strtotime($_GET['hasta'])))."
										 AND er.ruc_empresa = " . $db->var2str($ruc_empresa) . "
										 AND er.id_proveedor = pr.id_proveedor;");
					$documento_buscado="Retención";
						break;
					case "3":
					$data = $db->select("SELECT enc.id_encabezado_nc as id, enc.fecha_nc as fecha, cl.nombre as cliente, enc.serie_nc as serie, 
										enc.secuencial_nc as secuencial, cl.email as mail
										 FROM encabezado_nc enc, clientes cl
										 WHERE estado_mail= 'PENDIENTE' and estado_sri= 'AUTORIZADO'
										 AND fecha_nc BETWEEN ".$db->var2str(date("Y-m-d", strtotime($_GET['desde'])))." 
										 AND ".$db->var2str(date("Y-m-d", strtotime($_GET['hasta'])))."
										 AND enc.ruc_empresa = " . $db->var2str($ruc_empresa) . "
										 AND enc.id_cliente = cl.id;");
						$documento_buscado="Nota de crédito";
						break;
					case "4":
					$data = $db->select("SELECT egr.id_encabezado_gr as id, egr.fecha_gr as fecha, cl.nombre as cliente, egr.serie_gr as serie, 
										egr.secuencial_gr as secuencial, cl.email as mail
										 FROM encabezado_gr egr, clientes cl
										 WHERE estado_mail= 'PENDIENTE' and estado_sri= 'AUTORIZADO'
										 AND fecha_gr BETWEEN ".$db->var2str(date("Y-m-d", strtotime($_GET['desde'])))." 
										 AND ".$db->var2str(date("Y-m-d", strtotime($_GET['hasta'])))."
										 AND egr.ruc_empresa = " . $db->var2str($ruc_empresa) . "
										 AND egr.id_cliente = cl.id;");
						$documento_buscado="Guía de remisión";
						break;
					case "5":

						$documento_buscado="Nota de débito";
						break;
				};
					$datos_procesados = array();
					foreach ($data as $value) {
						$datos_procesados[] = array(
							'id'=>$value['id'],
							'fecha'=> date("d/m/Y", strtotime($value['fecha'])),
							'cliente'=> $value['cliente'],
							'num_doc'=> $value['serie'] .'-'. str_pad($value['secuencial'], 9, '0', STR_PAD_LEFT),
							'documento'=> $documento_buscado,
							'mail'=> $value['mail'],
											  );
					}
					
					$db->close();
		}else{
					echo "¡Imposible conectar con la base de datos!";
				}
				header('Content-Type: application/json');
				echo json_encode($datos_procesados);
			}
			
			break;

		case 'procesar_comp':
		include("../core/db.php");
		$db = new db();
		
			if ( isset($_POST['mails_select']) ) 
			{
				$mails_select =  explode(',', $_POST['mails_select']);
				$tipo_documento =  $_POST['documento'];	
				switch ($tipo_documento) {
					case "1":
					$tipo_documento="factura";
					break;
					case "2":
					$tipo_documento="retencion";
					break;
					case "3":
					$tipo_documento="nc";
					break;
					case "4":
					$tipo_documento="gr";
					break;
					case "5":
					$tipo_documento="nd";
					break;
				}
				
				if (is_array($mails_select)) {
					include("../documentos_mail/envia_documentos_sri_por_mail.php");
					$enviar_documentos_mail = new enviar_documentos_sri();
					
					if ($db->connect()) {
										
					foreach ($mails_select as $id_comprobante) {
						if ($tipo_documento=="factura"){
							$mail = $db->select("SELECT cl.email FROM encabezado_factura ef, clientes cl WHERE ef.id_encabezado_factura='".$id_comprobante."' and ef.id_cliente=cl.id;");
							$mail_receptor=$mail[0]['email'];
							$respuestaProceso = $enviar_documentos_mail->envia_mail($id_comprobante, $tipo_documento, $mail_receptor);
							echo $respuestaProceso;
						}
						
						
						if ($tipo_documento=="retencion"){
							$mail = $db->select("SELECT pr.mail_proveedor FROM encabezado_retencion er, proveedores pr WHERE er.id_encabezado_retencion='".$id_comprobante."' and er.id_proveedor=pr.id_proveedor;");
							$mail_receptor=$mail[0]['mail_proveedor'];
							$respuestaProceso = $enviar_documentos_mail->envia_mail($id_comprobante, $tipo_documento, $mail_receptor);
							echo $respuestaProceso;
						}
						
						if ($tipo_documento=="nc"){
							$mail = $db->select("SELECT cl.email FROM encabezado_nc enc, clientes cl WHERE enc.id_encabezado_nc='".$id_comprobante."' and enc.id_cliente=cl.id;");
							$mail_receptor=$mail[0]['email'];
							$respuestaProceso = $enviar_documentos_mail->envia_mail($id_comprobante, $tipo_documento, $mail_receptor);
							echo $respuestaProceso;
						}
						
						if ($tipo_documento=="gr"){
							$mail = $db->select("SELECT cl.email FROM encabezado_gr egr, clientes cl WHERE egr.id_encabezado_gr='".$id_comprobante."' and egr.id_cliente=cl.id;");
							$mail_receptor=$mail[0]['email'];
							$respuestaProceso = $enviar_documentos_mail->envia_mail($id_comprobante, $tipo_documento, $mail_receptor);
							echo $respuestaProceso;
						}
						if ($tipo_documento=="nd"){
							$respuestaProceso = $enviar_documentos_mail->envia_mail($id_comprobante, $tipo_documento, $mail_receptor);
							echo $respuestaProceso;
						}
						
					}
					}
					
				}
				
			}
			break;
	}
	
?>