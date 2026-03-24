<?php
//$analisis_ventas = new analisis_ventas();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
include("../conexiones/conectalogin.php");
$con = conenta_login();

$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

if($action == 'analisis_ventasvscompras'){
$anio = $_GET['anio'];

//limpiar la tabla
$delete_tabla = mysqli_query($con, "DELETE FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."'");

for ($i=1; $i<=12; ++$i){
$meses_todos = mysqli_query($con, "INSERT INTO reportes_graficos VALUES (null, '".$ruc_empresa."', '".$anio."', '".$i."', '0', '0')");	
}
$subtotal_compras = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, cc.ruc_empresa, '".$anio."', month(ec.fecha_compra), round(sum(cc.subtotal),2),'0' FROM cuerpo_compra cc INNER JOIN encabezado_compra ec ON ec.codigo_documento = cc.codigo_documento WHERE cc.ruc_empresa='".$ruc_empresa."' and ec.ruc_empresa='".$ruc_empresa."' and year(ec.fecha_compra)='".$anio."' and ec.id_comprobante !=4 group by month(ec.fecha_compra)) ");

$subtotal_nc_compras = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, cc.ruc_empresa, '".$anio."', month(ec.fecha_compra), round(sum(cc.subtotal),2),'0' FROM cuerpo_compra cc INNER JOIN encabezado_compra ec ON ec.codigo_documento = cc.codigo_documento WHERE cc.ruc_empresa='".$ruc_empresa."' and ec.ruc_empresa='".$ruc_empresa."' and year(ec.fecha_compra)='".$anio."' and ec.id_comprobante =4 group by month(ec.fecha_compra)) ");

	//sacar los meses y los valores
$datos_procesados = array();

$todas_compras =array();
$sql_sumas = mysqli_query($con,"SELECT sum(valor_entrada-valor_salida) as total FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' and anio='".$anio."' group by mes");
foreach ($sql_sumas as $datos){
	$todas_compras[]= floatval(number_format($datos['total'],2,'.',''));
}

//limpiar la tabla
$delete_tabla = mysqli_query($con, "DELETE FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."'");

$subtotal_ventas = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, cf.ruc_empresa, '".$anio."', month(ef.fecha_factura), sum(cf.subtotal_factura-cf.descuento),'0' FROM cuerpo_factura cf INNER JOIN encabezado_factura ef ON ef.serie_factura = cf.serie_factura and ef.secuencial_factura = cf.secuencial_factura WHERE cf.ruc_empresa='".$ruc_empresa."' and ef.ruc_empresa='".$ruc_empresa."' and year(ef.fecha_factura)='".$anio."' group by month(ef.fecha_factura)) ");

$subtotal_nc_ventas = mysqli_query($con, "INSERT INTO reportes_graficos (id_reporte, ruc_empresa, anio, mes, valor_entrada, valor_salida ) 
	(SELECT null, cn.ruc_empresa, '".$anio."', month(en.fecha_nc),'0', sum(cn.subtotal_nc-cn.descuento) FROM cuerpo_nc cn INNER JOIN encabezado_nc en ON en.serie_nc = cn.serie_nc and en.secuencial_nc = cn.secuencial_nc WHERE cn.ruc_empresa='".$ruc_empresa."' and en.ruc_empresa='".$ruc_empresa."' and year(en.fecha_nc)='".$anio."' group by month(en.fecha_nc)) ");

	
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
$todas_ventas =array();
$sql_sumas = mysqli_query($con,"SELECT sum(valor_entrada-valor_salida) as total FROM reportes_graficos WHERE ruc_empresa = '".$ruc_empresa."' and anio='".$anio."' group by mes");
foreach ($sql_sumas as $datos){
	$todas_ventas[]= floatval(number_format($datos['total'],2,'.',''));
}

    $datos_procesados[] = array('meses'=> $todos_meses, 'sumas_compras'=> $todas_compras, 'sumas_ventas'=> $todas_ventas);
	header('Content-Type: application/json');
	echo json_encode($datos_procesados);		
}

?>
