<?php
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
include("../conexiones/conectalogin.php");
$con = conenta_login();

$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

if($action == 'analisis_ventas'){
$anio = $_GET['anio'];
$mes = $_GET['mes'];
$dia = $_GET['dia'];
$desde = $_GET['desde'];
$hasta = $_GET['hasta'];
$tipo = $_GET['tipo'];
$fuente = $_GET['fuente'];

//limpiar la tabla
$delete_tabla = mysqli_query($con, "DELETE FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."'");

//para las facturas
if ($tipo=="1" && $fuente == "1"){//anual
$subtotal_ventas = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, cf.ruc_empresa, '".$anio."', month(ef.fecha_factura), sum(cf.subtotal_factura-cf.descuento),'0' FROM cuerpo_factura cf INNER JOIN encabezado_factura ef ON ef.serie_factura = cf.serie_factura and ef.secuencial_factura = cf.secuencial_factura WHERE cf.ruc_empresa='".$ruc_empresa."' and ef.ruc_empresa='".$ruc_empresa."' and year(ef.fecha_factura)='".$anio."' group by month(ef.fecha_factura)) ");
$subtotal_nc = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, cn.ruc_empresa, '".$anio."', month(en.fecha_nc),'0', sum(cn.subtotal_nc-cn.descuento) FROM cuerpo_nc cn INNER JOIN encabezado_nc en ON en.serie_nc = cn.serie_nc and en.secuencial_nc = cn.secuencial_nc WHERE cn.ruc_empresa='".$ruc_empresa."' and en.ruc_empresa='".$ruc_empresa."' and year(en.fecha_nc)='".$anio."' group by month(en.fecha_nc)) ");
$resultados = mysqli_query($con,"SELECT * FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' and anio='".$anio."' group by mes ");
}

if ($tipo=="2" && $fuente == "1"){//mensual
$subtotal_ventas = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, cf.ruc_empresa, '".$mes."', day(ef.fecha_factura), sum(cf.subtotal_factura-cf.descuento),'0' FROM cuerpo_factura cf INNER JOIN encabezado_factura ef ON ef.serie_factura = cf.serie_factura and ef.secuencial_factura = cf.secuencial_factura WHERE cf.ruc_empresa='".$ruc_empresa."' and ef.ruc_empresa='".$ruc_empresa."' and year(ef.fecha_factura)='".$anio."' and month(ef.fecha_factura)='".$mes."' group by day(ef.fecha_factura)) ");
$subtotal_nc = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, cn.ruc_empresa, '".$mes."', day(en.fecha_nc),'0', sum(cn.subtotal_nc-cn.descuento) FROM cuerpo_nc cn INNER JOIN encabezado_nc en ON en.serie_nc = cn.serie_nc and en.secuencial_nc = cn.secuencial_nc WHERE cn.ruc_empresa='".$ruc_empresa."' and en.ruc_empresa='".$ruc_empresa."' and year(en.fecha_nc)='".$anio."' and month(en.fecha_nc)='".$mes."' group by day(en.fecha_nc)) ");
$resultados = mysqli_query($con,"SELECT * FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' group by mes ");
}

if ($tipo=="3" && $fuente == "1"){//dia
$subtotal_ventas = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, cf.ruc_empresa, '".date('d', strtotime($dia))."', hour(ef.fecha_registro), sum(cf.subtotal_factura-cf.descuento),'0' FROM cuerpo_factura cf INNER JOIN encabezado_factura ef ON ef.serie_factura = cf.serie_factura and ef.secuencial_factura = cf.secuencial_factura WHERE cf.ruc_empresa='".$ruc_empresa."' and ef.ruc_empresa='".$ruc_empresa."' and year(ef.fecha_factura)='".date('Y', strtotime($dia))."' and month(ef.fecha_factura)='".date('m', strtotime($dia))."' and day(ef.fecha_factura)='".date('d', strtotime($dia))."' group by hour(ef.fecha_registro)) ");

$subtotal_nc = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, cn.ruc_empresa, '".date('d', strtotime($dia))."', day(en.fecha_nc),'0', sum(cn.subtotal_nc-cn.descuento) FROM cuerpo_nc cn INNER JOIN encabezado_nc en ON en.serie_nc = cn.serie_nc and en.secuencial_nc = cn.secuencial_nc WHERE cn.ruc_empresa='".$ruc_empresa."' and en.ruc_empresa='".$ruc_empresa."' and year(en.fecha_nc)='".date('Y', strtotime($dia))."' and month(en.fecha_nc)='".date('m', strtotime($dia))."' and day(en.fecha_nc)='".date('d', strtotime($dia))."' group by hour(en.fecha_registro)) ");
$resultados = mysqli_query($con,"SELECT * FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' group by mes ");
}

if ($tipo=="4" && $fuente == "1"){//periodos
$subtotal_ventas = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, cf.ruc_empresa, '".$anio."', month(ef.fecha_factura), sum(cf.subtotal_factura-cf.descuento),'0' FROM cuerpo_factura cf INNER JOIN encabezado_factura ef ON ef.serie_factura = cf.serie_factura and ef.secuencial_factura = cf.secuencial_factura WHERE cf.ruc_empresa='".$ruc_empresa."' and ef.ruc_empresa='".$ruc_empresa."' and DATE_FORMAT(ef.fecha_factura, '%Y/%m/%d') between '".date('Y/m/d', strtotime($desde))."' and '".date('Y/m/d', strtotime($hasta))."' group by month(ef.fecha_factura)) ");
$subtotal_nc = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, cn.ruc_empresa, '".$anio."', month(en.fecha_nc),'0', sum(cn.subtotal_nc-cn.descuento) FROM cuerpo_nc cn INNER JOIN encabezado_nc en ON en.serie_nc = cn.serie_nc and en.secuencial_nc = cn.secuencial_nc WHERE cn.ruc_empresa='".$ruc_empresa."' and en.ruc_empresa='".$ruc_empresa."' and DATE_FORMAT(en.fecha_nc, '%Y/%m/%d') between '".date('Y/m/d', strtotime($desde))."' and '".date('Y/m/d', strtotime($hasta))."' group by month(en.fecha_nc)) ");
$resultados = mysqli_query($con,"SELECT * FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' and anio='".$anio."' group by mes ");
}

//para los recibos
if ($tipo=="1" && $fuente == "2"){//anual
	$subtotal_ventas = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
		(SELECT null, er.ruc_empresa, '".$anio."', month(er.fecha_recibo), sum(cr.subtotal),'0' FROM cuerpo_recibo as cr INNER JOIN encabezado_recibo as er ON er.id_encabezado_recibo = cr.id_encabezado_recibo WHERE er.ruc_empresa='".$ruc_empresa."' and year(er.fecha_recibo)='".$anio."' group by month(er.fecha_recibo)) ");
	$resultados = mysqli_query($con,"SELECT * FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' and anio='".$anio."' group by mes ");
	}
	
	if ($tipo=="2" && $fuente == "2"){//mensual
	$subtotal_ventas = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
		(SELECT null, er.ruc_empresa, '".$mes."', day(er.fecha_recibo), sum(cr.subtotal),'0' FROM cuerpo_recibo as cr INNER JOIN encabezado_recibo as er ON er.id_encabezado_recibo = cr.id_encabezado_recibo WHERE er.ruc_empresa='".$ruc_empresa."' and year(er.fecha_recibo)='".$anio."' and month(er.fecha_recibo)='".$mes."' group by day(er.fecha_recibo)) ");
	$resultados = mysqli_query($con,"SELECT * FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' group by mes ");
	}
	
	if ($tipo=="3" && $fuente == "2"){//dia
	$subtotal_ventas = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
		(SELECT null, er.ruc_empresa, '".date('d', strtotime($dia))."', hour(er.fecha_recibo), sum(cr.subtotal),'0' FROM cuerpo_recibo as cr INNER JOIN encabezado_recibo as er ON er.id_encabezado_recibo = cr.id_encabezado_recibo WHERE er.ruc_empresa='".$ruc_empresa."' and year(er.fecha_recibo)='".date('Y', strtotime($dia))."' and month(er.fecha_recibo)='".date('m', strtotime($dia))."' and day(er.fecha_recibo)='".date('d', strtotime($dia))."' group by hour(er.fecha_recibo)) ");
	$resultados = mysqli_query($con,"SELECT * FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' group by mes ");
	}
	
	if ($tipo=="4" && $fuente == "2"){//periodos
	$subtotal_ventas = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
		(SELECT null, er.ruc_empresa, '".$anio."', month(er.fecha_recibo), sum(cr.subtotal),'0' FROM cuerpo_recibo as cr INNER JOIN encabezado_recibo as er ON er.id_encabezado_recibo = cr.id_encabezado_recibo WHERE er.ruc_empresa='".$ruc_empresa."' and DATE_FORMAT(er.fecha_recibo, '%Y/%m/%d') between '".date('Y/m/d', strtotime($desde))."' and '".date('Y/m/d', strtotime($hasta))."' group by month(er.fecha_recibo)) ");
	$resultados = mysqli_query($con,"SELECT * FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' and anio='".$anio."' group by mes ");
	}

	//sacar los meses y los valores
$datos_procesados = array();
$todos_meses=array();
foreach ($resultados as $datos){
	
	if ($tipo=='1' || $tipo=='4'){
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
	}

if ($tipo=='2'){
	$meses=$datos['mes'];
	}


if ($tipo=='3'){
	switch ($datos['mes']) {
		case "1":
			$meses='1 AM';
			break;
		case "2":
			$meses='2 AM';
			break;
		case "3":
			$meses='3 AM';
			break;
		case "4":
			$meses='4 AM';
			break;
		case "5":
			$meses='5 AM';
			break;
		case "6":
			$meses='6 AM';
			break;
		case "7":
			$meses='7 AM';
			break;
		case "8":
			$meses='8 AM';
			break;
		case "9":
			$meses='9 AM';
			break;
		case "10":
			$meses='10 AM';
			break;
		case "11":
			$meses='11 AM';
			break;
		case "12":
			$meses='12 PM';
			break;
		case "13":
			$meses='1 PM';
			break;
		case "14":
			$meses='2 PM';
			break;
		case "15":
			$meses='3 PM';
			break;
		case "16":
			$meses='4 PM';
			break;
		case "17":
			$meses='5 PM';
			break;
		case "18":
			$meses='6 PM';
			break;
		case "19":
			$meses='7 PM';
			break;
		case "20":
			$meses='8 PM';
			break;
		case "21":
			$meses='9 PM';
			break;
		case "22":
			$meses='10 PM';
			break;
		case "23":
			$meses='11 PM';
			break;
		case "24":
			$meses='12 AM';
			break;
			}
	}
	$todos_meses[]= $meses;	
}

$todas_sumas =array();
$sql_sumas = mysqli_query($con,"SELECT sum(valor_entrada-valor_salida) as total FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' group by mes");
foreach ($sql_sumas as $datos){
	$todas_sumas[]= floatval(number_format($datos['total'],2,'.',''));
}
    $datos_procesados[] = array('meses'=> $todos_meses, 'sumas'=> $todas_sumas);
	header('Content-Type: application/json');
	echo json_encode($datos_procesados);		
}

?>
