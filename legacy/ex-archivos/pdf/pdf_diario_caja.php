<?php
include("../conexiones/conectalogin.php");
require('../pdf/funciones_pdf.php');

include("../core/db.php");
$db = new db();
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
	
if (isset($_POST['fecha_diario_caja']) && isset($_POST['responsable_caja'])){
	$fecha_caja = $_POST['fecha_diario_caja'];
	$responsable = $_POST['responsable_caja'];

$busca_empresa = mysqli_query($con,"SELECT * FROM empresas WHERE ruc = '".$ruc_empresa."' ");
$datos_empresa=mysqli_fetch_assoc($busca_empresa);
$nombre_comercial_empresa = $datos_empresa['nombre_comercial'];
$html_encabezado='<p align="center">'.$nombre_comercial_empresa.'</p><br>
				  <p align="center">REPORTE DIARIO DE CAJA</p><br>
				  <p align="left">Fecha: '.$fecha_caja.'</p><br>
				  <p align="left">Responsable: '.$responsable.'</p><br><hr>';

//para ver el detalle del cuerpo			
	//eliminar registros que tienen cero en entradas y cero en salidas
	$query_elimina_registros = mysqli_query($con, "DELETE FROM detalle_diario_caja WHERE ruc_empresa='".$ruc_empresa."' and entradas= '0.00' and salidas='0.00'"); 
	//total ventas de ese dia
	$verifica_ventas= mysqli_query($con, "SELECT * FROM detalle_diario_caja WHERE ruc_empresa='".$ruc_empresa."' and fecha_diario_caja='".date('Y-m-d',strtotime($fecha_caja))."' and tipo_registro='VENTAS'");
	$total_registros=mysqli_num_rows($verifica_ventas);
	$row_registros=mysqli_fetch_array($verifica_ventas);
	$id_registro_venta=$row_registros['id_diario_caja'];
	if($total_registros==0){	
	$query_guarda_entrada = mysqli_query($con, "INSERT INTO detalle_diario_caja (id_diario_caja, ruc_empresa, fecha_diario_caja, fecha_registro, entradas, salidas, id_usuario, tipo_registro, detalle, codigo_forma_pago) 
	SELECT null,'".$ruc_empresa."', '".date('Y-m-d',strtotime($fecha_caja))."', '".date('Y-m-d')."', sum(fpv.valor_pago), '0', '".$id_usuario."', 'VENTAS','Ventas diarias','0' FROM formas_pago_ventas fpv INNER JOIN encabezado_factura enc ON enc.serie_factura=fpv.serie_factura and enc.secuencial_factura=fpv.secuencial_factura WHERE fpv.ruc_empresa='".$ruc_empresa."' and enc.ruc_empresa='".$ruc_empresa."' and enc.fecha_factura='".date('Y-m-d',strtotime($fecha_caja))."' ");
	}else{
	$sql_ventas_dia= mysqli_query($con, "SELECT sum(fpv.valor_pago) as total_ventas_dia FROM formas_pago_ventas fpv INNER JOIN encabezado_factura enc ON enc.serie_factura=fpv.serie_factura and enc.secuencial_factura=fpv.secuencial_factura WHERE fpv.ruc_empresa='".$ruc_empresa."' and enc.ruc_empresa='".$ruc_empresa."' and enc.fecha_factura='".date('Y-m-d',strtotime($fecha_caja))."' ");
	$row_ventas_dia=mysqli_fetch_array($sql_ventas_dia);
	$ventas_dia=$row_ventas_dia['total_ventas_dia'];	
	$sql_update = mysqli_query($con,"UPDATE detalle_diario_caja SET entradas='".$ventas_dia."' WHERE id_diario_caja='".$id_registro_venta."'");
	}
	
	$sql_entradas_salidas_dia= mysqli_query($con, "SELECT sum(entradas) as entradas, sum(salidas) as salidas FROM detalle_diario_caja WHERE ruc_empresa='".$ruc_empresa."' and fecha_diario_caja='".date('Y-m-d',strtotime($fecha_caja))."' ");
	$row_entradas_salidas_dia=mysqli_fetch_array($sql_entradas_salidas_dia);
	$entradas_dia=$row_entradas_salidas_dia['entradas'];
	$salidas_dia=$row_entradas_salidas_dia['salidas'];
	
	$sql_fecha_mas_antigua= mysqli_query($con, "SELECT min(fecha_diario_caja) as fecha_inicial FROM detalle_diario_caja WHERE ruc_empresa='".$ruc_empresa."' ");
	$row_fecha_mas_antigua=mysqli_fetch_array($sql_fecha_mas_antigua);
	
	$fecha_inicial=$db->var2str(date('Y-m-d H:i:s', strtotime($row_fecha_mas_antigua['fecha_inicial'])));
	$fecha_final=$db->var2str(date('Y-m-d H:i:s', strtotime($fecha_caja."- 1 days")));

	$sql_saldo_inicial= mysqli_query($con, "SELECT sum(entradas-salidas) as saldo_inicial FROM detalle_diario_caja WHERE fecha_diario_caja BETWEEN ".$fecha_inicial." and ".$fecha_final." and ruc_empresa='".$ruc_empresa."'");
	$row_saldo_inicial=mysqli_fetch_array($sql_saldo_inicial);
	$saldo_inicial=$row_saldo_inicial['saldo_inicial'];
	
	//para ver solo las ventas en efectivo
	$ventas_efectivo_manual= mysqli_query($con, "SELECT sum(entradas-salidas) as efectivo_manual FROM detalle_diario_caja WHERE ruc_empresa='".$ruc_empresa."' and fecha_diario_caja='".date('Y-m-d',strtotime($fecha_caja))."' and tipo_registro='MANUAL' and codigo_forma_pago='01'");
	$row_ventas_efectivo_manual=mysqli_fetch_array($ventas_efectivo_manual);
	$total_ventas_efectivo_manual=$row_ventas_efectivo_manual['efectivo_manual'];
	
	$sql_ventas_efectivo= mysqli_query($con, "SELECT sum(fpv.valor_pago) as total_pago FROM formas_pago_ventas fpv INNER JOIN encabezado_factura enc ON enc.serie_factura=fpv.serie_factura and enc.secuencial_factura=fpv.secuencial_factura INNER JOIN formas_de_pago fp ON fp.codigo_pago=fpv.id_forma_pago and fp.aplica_a='VENTAS' WHERE fp.codigo_pago='01' and fpv.ruc_empresa='".$ruc_empresa."' and enc.ruc_empresa='".$ruc_empresa."' and enc.fecha_factura='".date('Y-m-d',strtotime($fecha_caja))."' ");//group by fpv.id_forma_pago
	$row_ventas_efectivo=mysqli_fetch_array($sql_ventas_efectivo);
	$total_ventas_efectivo=$row_ventas_efectivo['total_pago']+$total_ventas_efectivo_manual;
	
	
	$html_info_inicial='
				  <p align="left">Saldo inicial: '.number_format($saldo_inicial,2,'.','').'</p><br>
				  <p align="left">Entradas: '.number_format($entradas_dia,2,'.','').'</p><br>
				  <p align="left">Salidas: '.number_format($salidas_dia,2,'.','').'</p><br>
				  <p align="left">Saldo final: '.number_format($saldo_inicial+$entradas_dia-$salidas_dia,2,'.','').'</p><br><hr>';
							
//para buscar el detalle de efectivo

//para buscar la imagen
$busca_imagen = mysqli_query($con,"SELECT * FROM sucursales WHERE ruc_empresa = '".$ruc_empresa."' ");
$datos_imagen=mysqli_fetch_assoc($busca_imagen);
$imagen = $datos_imagen['logo_sucursal'];
copy('../logos_empresas/'.$imagen, '../docs_temp/'.$ruc_empresa.'.jpg');


$pdf = new funciones_pdf( 'P', 'mm', 'A4' );
$pdf->AddPage();//es importante agregar esta linea para saber la pagina inicial
$pdf->SetFont('Arial');//esta tambien es importante
$pdf->detalle_html($html_encabezado);
$pdf->detalle_html($html_info_inicial);
$pdf->Image('../docs_temp/'.$ruc_empresa.'.jpg', 15, 10, 30, 0, 'jpg', '');
$pdf->detalle_html('<p align="left">Detalle de ventas</p><br>');
$pdf->AddCol(utf8_decode('forma_pago'),140,'Forma pago','L');
$pdf->AddCol('valor',40,'Valor','R');
$prop = array('HeaderColor'=>array(156, 184, 243),'color1'=>array(253, 254, 254),'color2'=>array(213, 219, 219),'padding'=>2);
$pdf->Table($con, "SELECT fp.nombre_pago as forma_pago, sum(fpv.valor_pago) as valor FROM formas_pago_ventas fpv INNER JOIN encabezado_factura enc ON enc.serie_factura=fpv.serie_factura and enc.secuencial_factura=fpv.secuencial_factura INNER JOIN formas_de_pago fp ON fp.codigo_pago=fpv.id_forma_pago and fp.aplica_a='VENTAS' WHERE fpv.ruc_empresa='".$ruc_empresa."' and enc.ruc_empresa='".$ruc_empresa."' and enc.fecha_factura='".date('Y-m-d',strtotime($fecha_caja))."' group by fpv.id_forma_pago",$prop, 'cascada');
$pdf->detalle_html('<p align="left">Otras entradas</p><br>');
$pdf->AddCol('detalle',100,'Detalle','L');
$pdf->AddCol('forma_pago',40,'Forma pago','L');
$pdf->AddCol('valor',40,'Valor','R');
//$prop = array('HeaderColor'=>array(169, 223, 191),'color1'=>array(253, 254, 254),'color2'=>array(213, 219, 219),'padding'=>2);
$pdf->Table($con,"SELECT distinct ddc.detalle as detalle, fdp.nombre_pago as forma_pago, entradas as valor FROM detalle_diario_caja ddc, formas_de_pago fdp WHERE fdp.codigo_pago=ddc.codigo_forma_pago and ddc.ruc_empresa='".$ruc_empresa."' and ddc.fecha_diario_caja='".date('Y-m-d',strtotime($fecha_caja))."' and ddc.tipo_registro='MANUAL' and ddc.entradas > 0",$prop, 'cascada');
$pdf->detalle_html('<p align="left">Detalle de salidas</p><br>');
$pdf->AddCol('detalle',100,'Detalle','L');
$pdf->AddCol('forma_pago',40,'Forma pago','L');
$pdf->AddCol('valor',40,'Valor','R');
//$prop = array('HeaderColor'=>array(169, 223, 191),'color1'=>array(253, 254, 254),'color2'=>array(213, 219, 219),'padding'=>2);
$pdf->Table($con,"SELECT distinct ddc.detalle as detalle, fdp.nombre_pago as forma_pago, salidas as valor FROM detalle_diario_caja ddc, formas_de_pago fdp WHERE fdp.codigo_pago=ddc.codigo_forma_pago and ddc.ruc_empresa='".$ruc_empresa."' and ddc.fecha_diario_caja='".date('Y-m-d',strtotime($fecha_caja))."' and ddc.tipo_registro='MANUAL' and ddc.salidas > 0",$prop, 'cascada');
$pdf->detalle_html('<p align="left">Detalle de efectivo</p><br>');
$pdf->AddCol('tipo',60,'','L');
$pdf->AddCol('denominacion',40,utf8_decode('Denominación'),'C');
$pdf->AddCol('cantidad',40,'Cantidad','R');
$pdf->AddCol('total',40,'Total','R');
//$prop = array('HeaderColor'=>array(169, 223, 191),'color1'=>array(253, 254, 254),'color2'=>array(213, 219, 219),'padding'=>2);
$pdf->Table($con,"SELECT denominacion as tipo, valor_denominacion as denominacion, cantidad, if(denominacion = 'moneda', format(cantidad*(valor_denominacion/100),2), format(cantidad*valor_denominacion,2)) as total FROM detalle_efectivo WHERE ruc_empresa='".$ruc_empresa."' and fecha_detalle='".date('Y-m-d',strtotime($fecha_caja))."' ",$prop, 'cascada');
$pdf->detalle_html('<p align="right">-------------------------------------------------------------------------------------------------------------->Total efectivo: '.number_format($total_ventas_efectivo,2,'.','').'</p><br>');
$pdf->detalle_html('<p align="center"></p><br>');
$pdf->detalle_html('<p align="center"></p><hr>');
$pdf->detalle_html('<p align="left">Realizado por</p>        <p align="right">Aprobado por</p>');

$pdf->Output("Diario de caja ".$fecha_caja.".pdf","D");
unlink('../docs_temp/'.$ruc_empresa.'.jpg');
}

?>
