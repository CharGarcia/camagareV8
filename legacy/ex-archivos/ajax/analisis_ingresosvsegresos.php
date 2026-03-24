<?php
//$analisis_ventas = new analisis_ventas();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
include("../conexiones/conectalogin.php");
$con = conenta_login();

$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

if($action == 'analisis_ingresosvsegresos'){
$anio = $_GET['anio'];

//limpiar la tabla
$delete_tabla = mysqli_query($con, "DELETE FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."'");

for ($i=1; $i<=12; ++$i){
$meses_todos = mysqli_query($con, "INSERT INTO reportes_graficos VALUES (null, '".$ruc_empresa."', '".$anio."', '".$i."', '0', '0')");	
}
$ingresos = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, ruc_empresa, '".$anio."', month(fecha_ing_egr), round(sum(valor_ing_egr),2),'0' FROM ingresos_egresos WHERE ruc_empresa='".$ruc_empresa."' and year(fecha_ing_egr)='".$anio."' and tipo_ing_egr ='INGRESO' group by month(fecha_ing_egr)) ");

	//sacar los meses y los valores
$datos_procesados = array();

$todas_ingresos =array();
$sql_sumas = mysqli_query($con,"SELECT round(sum(valor_entrada-valor_salida),2) as total FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' and anio='".$anio."' group by mes");
foreach ($sql_sumas as $datos){
	$todas_ingresos[]= floatval(number_format($datos['total'],2,'.',''));
}

//limpiar la tabla
$delete_tabla = mysqli_query($con, "DELETE FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."'");

$egresos = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, ruc_empresa, '".$anio."', month(fecha_ing_egr), round(sum(valor_ing_egr),2),'0' FROM ingresos_egresos WHERE ruc_empresa='".$ruc_empresa."' and year(fecha_ing_egr)='".$anio."' and tipo_ing_egr ='EGRESO' group by month(fecha_ing_egr)) ");

	
for ($i=1; $i<=12; ++$i){
$meses_todos = mysqli_query($con, "INSERT INTO reportes_graficos VALUES (null, '".$ruc_empresa."', '".$anio."', '".$i."', '0', '0')");	
}
	
$todos_meses=array();
$sql_meses = mysqli_query($con,"SELECT * FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' and anio='".$anio."' group by mes ");
foreach ($sql_meses as $datos){
	switch ($datos['mes']) {
		case "1":
			$meses='Enero';
			break;
		case "2":
			$meses='Febrero';
			break;
		case "3":
			$meses='Marzo';
			break;
		case "4":
			$meses='Abril';
			break;
		case "5":
			$meses='Mayo';
			break;
		case "6":
			$meses='Junio';
			break;
		case "7":
			$meses='Julio';
			break;
		case "8":
			$meses='Agosto';
			break;
		case "9":
			$meses='Septiembre';
			break;
		case "10":
			$meses='Octubre';
			break;
		case "11":
			$meses='Noviembre';
			break;
		case "12":
			$meses='Diciembre';
			break;
			}
	$todos_meses[]= $meses;	
}
$todas_egresos =array();
$sql_sumas = mysqli_query($con,"SELECT round(sum(valor_entrada-valor_salida),2) as total FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' and anio='".$anio."' group by mes");
foreach ($sql_sumas as $datos){
	$todas_egresos[]= floatval(number_format($datos['total'],2,'.',''));
}

    $datos_procesados[] = array('meses'=> $todos_meses, 'sumas_ingresos'=> $todas_ingresos, 'sumas_egresos'=> $todas_egresos);
	header('Content-Type: application/json');
	echo json_encode($datos_procesados);		
}

?>
