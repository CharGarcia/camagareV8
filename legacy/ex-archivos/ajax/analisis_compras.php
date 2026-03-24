<?php
//$analisis_ventas = new analisis_ventas();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
include("../conexiones/conectalogin.php");
$con = conenta_login();

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'analisis_compras') {
	$anio = $_GET['anio'];

	//limpiar la tabla
	$delete_tabla = mysqli_query($con, "DELETE FROM reportes_graficos WHERE ruc_empresa = '" . $ruc_empresa . "'");

	$subtotal_compras = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, cc.ruc_empresa, '" . $anio . "', month(ec.fecha_compra), round(sum(cc.subtotal),2),'0' FROM cuerpo_compra cc INNER JOIN encabezado_compra ec ON ec.codigo_documento = cc.codigo_documento WHERE cc.ruc_empresa='" . $ruc_empresa . "' and ec.ruc_empresa='" . $ruc_empresa . "' and year(ec.fecha_compra)='" . $anio . "' and ec.id_comprobante !=4 group by month(ec.fecha_compra)) ");

	$subtotal_nc = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, cc.ruc_empresa, '" . $anio . "', month(ec.fecha_compra), round(sum(cc.subtotal),2),'0' FROM cuerpo_compra cc INNER JOIN encabezado_compra ec ON ec.codigo_documento = cc.codigo_documento WHERE cc.ruc_empresa='" . $ruc_empresa . "' and ec.ruc_empresa='" . $ruc_empresa . "' and year(ec.fecha_compra)='" . $anio . "' and ec.id_comprobante = 4 group by month(ec.fecha_compra)) ");

	//sacar los meses y los valores
	$datos_procesados = array();
	$todos_meses = array();
	$sql_meses = mysqli_query($con, "SELECT * FROM reportes_graficos WHERE ruc_empresa = '" . $ruc_empresa . "' and anio='" . $anio . "' group by mes ");
	foreach ($sql_meses as $datos) {
		switch ($datos['mes']) {
			case "1":
				$meses = 'Enero';
				break;
			case "2":
				$meses = 'Febrero';
				break;
			case "3":
				$meses = 'Marzo';
				break;
			case "4":
				$meses = 'Abril';
				break;
			case "5":
				$meses = 'Mayo';
				break;
			case "6":
				$meses = 'Junio';
				break;
			case "7":
				$meses = 'Julio';
				break;
			case "8":
				$meses = 'Agosto';
				break;
			case "9":
				$meses = 'Septiembre';
				break;
			case "10":
				$meses = 'Octubre';
				break;
			case "11":
				$meses = 'Noviembre';
				break;
			case "12":
				$meses = 'Diciembre';
				break;
		}
		$todos_meses[] = $meses;
	}

	$todas_sumas = array();
	$sql_sumas = mysqli_query($con, "SELECT round(sum(valor_entrada-valor_salida),2) as total FROM reportes_graficos WHERE ruc_empresa = '" . $ruc_empresa . "' and anio='" . $anio . "' group by mes");
	foreach ($sql_sumas as $datos) {
		$todas_sumas[] = floatval(number_format($datos['total'], 2, '.', ''));
	}
	$datos_procesados[] = array('meses' => $todos_meses, 'sumas' => $todas_sumas);
	header('Content-Type: application/json');
	echo json_encode($datos_procesados);
}
