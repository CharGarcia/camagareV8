<?php
include("../ajax/conciliacion_bancaria.php");
require('../pdf/funciones_pdf.php');
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
//if (isset($_POST['action']) && $action=="conciliacion_bancaria"){
$cuenta = $_POST['cuenta'];
$fecha_desde = $_POST['fecha_desde'];
$fecha_hasta = $_POST['fecha_hasta'];

$sql_cuenta = mysqli_fetch_array(mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa ='" . $ruc_empresa . "' and cue_ban.id_cuenta='" . $cuenta . "'"));
$nombre_cuenta = $sql_cuenta['cuenta_bancaria'];

$suma_creditos_saldo_inicial = saldo_inicial_creditos($con, $cuenta, $ruc_empresa, $fecha_desde);
$suma_debitos_saldo_inicial = saldo_inicial_debitos($con, $cuenta, $ruc_empresa, $fecha_desde);
$cheques_saldo_inicial = cheques_saldo_inicial($con, $cuenta, $ruc_empresa, $fecha_desde);
$saldo_inicial = $suma_creditos_saldo_inicial - $suma_debitos_saldo_inicial - $cheques_saldo_inicial;

$total_creditos = creditos_debitos($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta, 'INGRESO');
$total_debitos = creditos_debitos($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta, 'EGRESO');
$cheques_pagados = cheques_pagados($con, $cuenta, $ruc_empresa, $fecha_desde, $fecha_hasta);

$saldo_final = $saldo_inicial + $total_creditos - $total_debitos - $cheques_pagados;

//para buscar la imagen
$busca_imagen = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
$datos_imagen = mysqli_fetch_assoc($busca_imagen);
$imagen = "../logos_empresas/" . $datos_imagen['logo_sucursal'];

$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
$datos_empresa = mysqli_fetch_assoc($busca_empresa);
$nombre_empresa = $datos_empresa['nombre_comercial'];
$html_encabezado = '<p align="center">' . $nombre_empresa . '</p><br>
				  <p align="center">' . utf8_decode('CONCILIACIÓN BANCARIA') . '</p><br>';

$pdf = new funciones_pdf('P', 'mm', 'A4'); //P-L
$pdf->AliasNbPages();
$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 200, $height = 200);
imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
//$pdf->AliasNbPages();
$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
$pdf->SetFont('Arial', '', 10); //esta tambien es importante
$prop = array('HeaderColor' => array(213, 219, 219), 'color1' => array(253, 254, 254), 'color2' => array(253, 254, 254), 'padding' => 4);

$pdf->detalle_html($html_encabezado);
$pdf->Cell(50);
$pdf->Cell(140, 5, 'Cuenta:  ' . utf8_decode($nombre_cuenta), 1, 1, 'L');
$pdf->Cell(50);
$pdf->Cell(140, 5, 'Desde:  ' . date("d-m-Y", strtotime($fecha_desde)) . ' Hasta:  ' . date("d-m-Y", strtotime($fecha_hasta)), 1, 1, 'L');
$pdf->Cell(50);
$pdf->Cell(140, 5, 'Saldo inicial:  ' . number_format($saldo_inicial, 2, '.', ''), 1, 1, 'L');
$pdf->Cell(50);
$pdf->Cell(140, 5, utf8_decode('Créditos:  ') . number_format($total_creditos, 2, '.', ''), 1, 1, 'L');
$pdf->Cell(50);
$pdf->Cell(140, 5, utf8_decode('Débitos:  ') . number_format($total_debitos + $cheques_pagados, 2, '.', ''), 1, 1, 'L');
$pdf->Cell(50);
$pdf->Cell(140, 5, 'Saldo final:  ' . number_format($saldo_final, 2, '.', ''), 1, 1, 'L');

$pdf->Image('../docs_temp/' . $ruc_empresa . '.jpg', 20, 20, 30, 30, 'jpg', '');
$pdf->Ln();
$pdf->Cell(190, 5, utf8_decode('Detalle de créditos'), 1, 1, 'L');

$datos_ingreso = mysqli_query($con, "SELECT date_format(for_pag.fecha_emision,'%d-%m-%Y') as fecha_emision, for_pag.numero_ing_egr as numero, ing_egr.nombre_ing_egr as nombre, round(for_pag.valor_forma_pago,2) as valor, for_pag.detalle_pago as tipo FROM formas_pagos_ing_egr as for_pag INNER JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento=for_pag.codigo_documento WHERE for_pag.id_cuenta='" . $cuenta . "' and for_pag.ruc_empresa ='" . $ruc_empresa . "' and for_pag.tipo_documento='INGRESO' and for_pag.fecha_emision between '" . date("Y-m-d", strtotime($fecha_desde)) . "' and '" . date("Y-m-d", strtotime($fecha_hasta)) . "' and for_pag.estado='OK' order by for_pag.fecha_emision asc ");
$pdf->SetWidths(array(30, 20, 100, 15, 25));
$pdf->Row_tabla(array(utf8_decode('Fecha'), 'Ingreso', 'Recibido de', 'Tipo', 'Valor'));
$total_ingresos = 0;
while ($row_datos = mysqli_fetch_assoc($datos_ingreso)) {
	$pdf->Row_tabla(array($row_datos['fecha_emision'], utf8_decode($row_datos['numero']), utf8_decode($row_datos['nombre']), $row_datos['tipo'], $row_datos['valor']));
	$total_ingresos += $row_datos['valor'];
}
$pdf->Cell(190, 5, utf8_decode('Total créditos: ') . $total_ingresos, 1, 1, 'L');

$pdf->Ln();
$pdf->Cell(190, 5, utf8_decode('Detalle de débitos'), 1, 1, 'L');

$datos_egreso = mysqli_query($con, "SELECT if(for_pag.cheque > 0, date_format(for_pag.fecha_entrega,'%d-%m-%Y'), date_format(for_pag.fecha_pago,'%d-%m-%Y')) as fecha_transaccion, 
for_pag.numero_ing_egr as numero, ing_egr.nombre_ing_egr as nombre, for_pag.valor_forma_pago as valor, 
for_pag.detalle_pago as tipo 
FROM formas_pagos_ing_egr as for_pag 
INNER JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento=for_pag.codigo_documento 
WHERE for_pag.id_cuenta='" . $cuenta . "' and for_pag.ruc_empresa ='" . $ruc_empresa . "' 
and for_pag.tipo_documento='EGRESO' and if(for_pag.cheque > 0, DATE_FORMAT(for_pag.fecha_entrega, '%Y/%m/%d'), DATE_FORMAT(for_pag.fecha_pago, '%Y/%m/%d')) 
between '" . date("Y/m/d", strtotime($fecha_desde)) . "' and '" . date("Y/m/d", strtotime($fecha_hasta)) . "' and for_pag.estado='OK' order by for_pag.fecha_emision asc ");
$pdf->SetWidths(array(30, 20, 100, 15, 25));
$pdf->Row_tabla(array(utf8_decode('Fecha'), 'Egreso', 'Pagado a', 'Tipo', 'Valor'));
$total_egresos = 0;
while ($row_datos = mysqli_fetch_assoc($datos_egreso)) {
	$pdf->Row_tabla(array(
		$row_datos['fecha_transaccion'],
		utf8_decode($row_datos['numero']),
		utf8_decode($row_datos['nombre']),
		$row_datos['tipo'],
		$row_datos['valor']
	));
	$total_egresos += $row_datos['valor'];
}
$pdf->Cell(190, 5, utf8_decode('Total débitos: ') . $total_egresos, 1, 1, 'L');

$pdf->Ln();
$pdf->Cell(190, 5, utf8_decode('Detalle de cheques emitidos'), 1, 1, 'L');
$datos_egreso = mysqli_query($con, "SELECT date_format(for_pag.fecha_emision,'%d-%m-%Y') as fecha_emision, for_pag.numero_ing_egr as numero, for_pag.cheque as cheque, ing_egr.nombre_ing_egr as nombre, for_pag.valor_forma_pago as valor, if(date_format(for_pag.fecha_entrega,'%d-%m-%Y') <= '" . date("d-m-Y", strtotime($fecha_hasta)) . "', for_pag.estado_pago, 'PENDIENTE') as estado FROM formas_pagos_ing_egr as for_pag INNER JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento=for_pag.codigo_documento WHERE for_pag.id_cuenta='" . $cuenta . "' and for_pag.ruc_empresa ='" . $ruc_empresa . "' and for_pag.tipo_documento='EGRESO' and for_pag.fecha_emision between '" . date("Y-m-d", strtotime($fecha_desde)) . "' and '" . date("Y-m-d", strtotime($fecha_hasta)) . "' and for_pag.estado='OK' and detalle_pago ='C' order by for_pag.fecha_emision asc ");
$pdf->SetWidths(array(30, 20, 20, 100, 20));
$pdf->Row_tabla(array(utf8_decode('Fecha emisión'), 'Egreso', 'Cheque', 'Beneficiario', 'Valor'));
$total_egresos = 0;
while ($row_datos = mysqli_fetch_assoc($datos_egreso)) {
	$pdf->Row_tabla(array($row_datos['fecha_emision'], utf8_decode($row_datos['numero']), utf8_decode($row_datos['cheque']), $row_datos['nombre'], $row_datos['valor']));
	$total_egresos += $row_datos['valor'];
}
$pdf->Cell(190, 5, utf8_decode('Total: ') . $total_egresos, 1, 1, 'L');

$pdf->Ln();
$pdf->Cell(190, 5, utf8_decode('Detalle de cheques pagados'), 1, 1, 'L');
$datos_egreso = mysqli_query($con, "SELECT date_format(for_pag.fecha_entrega,'%d-%m-%Y') as fecha_entrega, for_pag.numero_ing_egr as numero, for_pag.cheque as cheque, ing_egr.nombre_ing_egr as nombre, for_pag.valor_forma_pago as valor FROM formas_pagos_ing_egr as for_pag INNER JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento=for_pag.codigo_documento WHERE for_pag.id_cuenta='" . $cuenta . "' and for_pag.ruc_empresa ='" . $ruc_empresa . "' and for_pag.tipo_documento='EGRESO' and for_pag.fecha_entrega between '" . date("Y-m-d", strtotime($fecha_desde)) . "' and '" . date("Y-m-d", strtotime($fecha_hasta)) . "' and for_pag.estado='OK' and for_pag.detalle_pago ='C' and for_pag.estado_pago='PAGADO' order by for_pag.fecha_entrega asc");
$pdf->SetWidths(array(30, 20, 20, 100, 20));
$pdf->Row_tabla(array(utf8_decode('Fecha cobro'), 'Egreso', 'Cheque', 'Beneficiario', 'Valor'));
$total_egresos = 0;
while ($row_datos = mysqli_fetch_assoc($datos_egreso)) {
	$pdf->Row_tabla(array($row_datos['fecha_entrega'], utf8_decode($row_datos['numero']), utf8_decode($row_datos['cheque']), $row_datos['nombre'], $row_datos['valor']));
	$total_egresos += $row_datos['valor'];
}
$pdf->Cell(190, 5, utf8_decode('Total: ') . $total_egresos, 1, 1, 'L');

$pdf->Ln();
$pdf->detalle_html('<p align="center"></p><hr>');
$pdf->detalle_html(utf8_decode('<p align="center">REALIZADO POR                                         APROBADO POR</p>'));
$pdf->detalle_html('<br><br>');

$pdf->Output("CB " . $nombre_cuenta . " del " . date("d-m-Y", strtotime($fecha_desde))  . " al " . date("d-m-Y", strtotime($fecha_hasta)) . ".pdf", "D");
unlink('../docs_temp/' . $ruc_empresa . '.jpg');
//}
