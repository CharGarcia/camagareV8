<?php
//$analisis_ventas = new analisis_ventas();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
include("../conexiones/conectalogin.php");
$con = conenta_login();
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

if($action == 'horas_pico'){
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$tipo = $_GET['tipo'];
//limpiar la tabla
$delete_tabla = mysqli_query($con, "DELETE FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."'");

if ($tipo=="1"){//diario
	$total_horas = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, ruc_empresa, '".date('d', strtotime($desde))."', hour(fecha_registro), sum(total_factura),'0' FROM encabezado_factura WHERE ruc_empresa='".$ruc_empresa."' and year(fecha_registro)='".date('Y', strtotime($desde))."' and month(fecha_registro)='".date('m', strtotime($desde))."' and day(fecha_registro)='".date('d', strtotime($desde))."' group by hour(fecha_registro)) ");
$sql_horas = mysqli_query($con,"SELECT * FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' group by mes ");
}

if ($tipo=="2"){//mensual
	$total_horas = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, ruc_empresa, '".date('m', strtotime($desde))."', hour(fecha_registro), sum(total_factura),'0' FROM encabezado_factura WHERE ruc_empresa='".$ruc_empresa."' and year(fecha_registro)='".date('Y', strtotime($desde))."' and month(fecha_registro)='".date('m', strtotime($desde))."' group by hour(fecha_registro)) ");
$sql_horas = mysqli_query($con,"SELECT * FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' group by mes ");
}

if ($tipo=="3"){//periodos
	$total_horas = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, ruc_empresa, '".date('d', strtotime($desde))."', hour(fecha_registro), sum(total_factura),'0' FROM encabezado_factura WHERE ruc_empresa='".$ruc_empresa."' and fecha_registro between '".date('Y-m-d', strtotime($desde))."' and '".date('Y-m-d', strtotime($hasta))."' group by hour(fecha_registro))");
$sql_horas = mysqli_query($con,"SELECT * FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' group by mes ");

}

	//sacar los horas y los valores
$datos_procesados = array();
$todas_horas=array();

foreach ($sql_horas as $datos){
	switch ($datos['mes']) {
		case "1":
			$horas='1 AM';
			break;
		case "2":
			$horas='2 AM';
			break;
		case "3":
			$horas='3 AM';
			break;
		case "4":
			$horas='4 AM';
			break;
		case "5":
			$horas='5 AM';
			break;
		case "6":
			$horas='6 AM';
			break;
		case "7":
			$horas='7 AM';
			break;
		case "8":
			$horas='8 AM';
			break;
		case "9":
			$horas='9 AM';
			break;
		case "10":
			$horas='10 AM';
			break;
		case "11":
			$horas='11 AM';
			break;
		case "12":
			$horas='12 PM';
			break;
		case "13":
			$horas='1 PM';
			break;
		case "14":
			$horas='2 PM';
			break;
		case "15":
			$horas='3 PM';
			break;
		case "16":
			$horas='4 PM';
			break;
		case "17":
			$horas='5 PM';
			break;
		case "18":
			$horas='6 PM';
			break;
		case "19":
			$horas='7 PM';
			break;
		case "20":
			$horas='8 PM';
			break;
		case "21":
			$horas='9 PM';
			break;
		case "22":
			$horas='10 PM';
			break;
		case "23":
			$horas='11 PM';
			break;
		case "24":
			$horas='12 AM';
			break;
			}
	$todas_horas[]= $horas;	
}

$todas_sumas =array();
$sql_sumas = mysqli_query($con,"SELECT sum(valor_entrada-valor_salida) as total FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' group by mes");
foreach ($sql_sumas as $datos){
	$todas_sumas[]= floatval(number_format($datos['total'],2,'.',''));
}
    $datos_procesados[] = array('horas'=> $todas_horas, 'sumas'=> $todas_sumas);
	header('Content-Type: application/json');
	echo json_encode($datos_procesados);		
}

?>
