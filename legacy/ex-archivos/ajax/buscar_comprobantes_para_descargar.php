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
			$id_cliente = $_GET['id_cliente'];
			if (empty($id_cliente)) {
				$condicion_cliente = "";
				$condicion_retencion = "";
				$condicion_nc = "";
				$condicion_guia = "";
			} else {
				$condicion_cliente = "and ef.id_cliente=" . $id_cliente;
				$condicion_retencion = "and er.id_proveedor=" . $id_cliente;
				$condicion_nc = "and enc.id_cliente=" . $id_cliente;
				$condicion_guia = "and egr.id_cliente=" . $id_cliente;
			}

			if ($db->connect()) {
				switch ($documento) {
					case "1":
						$data = $db->select("SELECT ef.id_encabezado_factura as id, ef.fecha_factura as fecha, cl.nombre as cliente, ef.serie_factura as serie, 
										ef.secuencial_factura as secuencial, ef.total_factura as total
										 FROM encabezado_factura ef, clientes cl
										 WHERE estado_sri ='AUTORIZADO' and fecha_factura BETWEEN " . $db->var2str(date("Y-m-d", strtotime($_GET['desde']))) . " 
										 AND " . $db->var2str(date("Y-m-d", strtotime($_GET['hasta']))) . "
										 AND ef.ruc_empresa = " . $db->var2str($ruc_empresa) . "
										 AND ef.id_cliente = cl.id $condicion_cliente;");
						$documento_buscado = "Factura";
						break;
					case "3":
						$data = $db->select("SELECT er.id_encabezado_retencion as id, er.fecha_emision as fecha, pr.razon_social as cliente, er.serie_retencion as serie, 
										er.secuencial_retencion as secuencial, er.total_retencion as total
										 FROM encabezado_retencion er, proveedores pr
										 WHERE estado_sri ='AUTORIZADO' and fecha_emision BETWEEN " . $db->var2str(date("Y-m-d", strtotime($_GET['desde']))) . " 
										 AND " . $db->var2str(date("Y-m-d", strtotime($_GET['hasta']))) . "
										 AND er.ruc_empresa = " . $db->var2str($ruc_empresa) . "
										 AND er.id_proveedor = pr.id_proveedor $condicion_retencion;");
						$documento_buscado = "Retención";
						break;
					case "5":
						$data = $db->select("SELECT enc.id_encabezado_nc as id, enc.fecha_nc as fecha, cl.nombre as cliente, enc.serie_nc as serie, 
										enc.secuencial_nc as secuencial, enc.total_nc as total
										 FROM encabezado_nc enc, clientes cl
										 WHERE estado_sri ='AUTORIZADO' and fecha_nc BETWEEN " . $db->var2str(date("Y-m-d", strtotime($_GET['desde']))) . " 
										 AND " . $db->var2str(date("Y-m-d", strtotime($_GET['hasta']))) . "
										 AND enc.ruc_empresa = " . $db->var2str($ruc_empresa) . "
										 AND enc.id_cliente = cl.id $condicion_nc;");
						$documento_buscado = "Nota de crédito";
						break;
					case "7":
						$data = $db->select("SELECT egr.id_encabezado_gr as id, egr.fecha_gr as fecha, cl.nombre as cliente, egr.serie_gr as serie, 
										egr.secuencial_gr as secuencial, '' as total
										 FROM encabezado_gr egr, clientes cl
										 WHERE estado_sri ='AUTORIZADO' and fecha_gr BETWEEN " . $db->var2str(date("Y-m-d", strtotime($_GET['desde']))) . " 
										 AND " . $db->var2str(date("Y-m-d", strtotime($_GET['hasta']))) . "
										 AND egr.ruc_empresa = " . $db->var2str($ruc_empresa) . "
										 AND egr.id_cliente = cl.id $condicion_guia;");
						$documento_buscado = "Guía de remisión";
						break;
					case "8":

						$documento_buscado = "Nota de débito";
						break;
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

	case 'procesar_comp':
		if (isset($_POST['comp_select'])) {
			$comp_select =  $_POST['comp_select'];
			$tipo_documento =  $_POST['documento'];
			$tipo_formato =  $_POST['tipo_formato'];
			switch ($tipo_documento) {
				case "1":
?>
					<a href="../ajax/imprime_documento.php?action=descargar_varios_documentos&documentos=<?php echo ($comp_select); ?>&tipo_documento=factura&tipo_formato=<?php echo ($tipo_formato); ?>" class='btn btn-success btn-md' title='Descargar' target="_blank"> <span class="glyphicon glyphicon-download"></span> Descargar</i> </a>
				<?php
					break;
				case "2":

					break;
				case "3":
				?>
					<a href="../ajax/imprime_documento.php?action=descargar_varios_documentos&documentos=<?php echo ($comp_select); ?>&tipo_documento=retencion&tipo_formato=<?php echo ($tipo_formato); ?>" class='btn btn-success btn-md' title='Descargar' target="_blank"><span class="glyphicon glyphicon-download"></span> Descargar</i> </a>
				<?php
					break;
				case "4":

					break;
				case "5":
				?>
					<a href="../ajax/imprime_documento.php?action=descargar_varios_documentos&documentos=<?php echo ($comp_select); ?>&tipo_documento=nc&tipo_formato=<?php echo ($tipo_formato); ?>" class='btn btn-success btn-md' title='Descargar' target="_blank"><span class="glyphicon glyphicon-download"></span> Descargar</i> </a>
				<?php
					break;
				case "6":

					break;
				case "7":
				?>
					<a href="../ajax/imprime_documento.php?action=descargar_varios_documentos&documentos=<?php echo ($comp_select); ?>&tipo_documento=gr&tipo_formato=<?php echo ($tipo_formato); ?>" class='btn btn-success btn-md' title='Descargar' target="_blank"><span class="glyphicon glyphicon-download"></span> Descargar</i> </a>
<?php

					break;
			}
		}
		break;
}
?>