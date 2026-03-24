<?php
require('../pdf/funciones_pdf.php');
require('../ajax/cuentas_por_cobrar.php');

$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario_actual = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
//if (isset($_POST['action']) && $action=="pdf_ventas_por_cobrar"){
$desde = "2018/01/01";
$hasta = $_POST['fecha_hasta'];
$id_cliente = $_POST['id_cliente'];
$vendedor = $_POST['vendedor'];
ini_set('date.timezone', 'America/Guayaquil');
$fecha_hoy = date_create(date("Y-m-d H:i:s"));

//para buscar la imagen
$busca_imagen = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
$datos_imagen = mysqli_fetch_assoc($busca_imagen);
$imagen = "../logos_empresas/" . $datos_imagen['logo_sucursal'];

$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . substr($ruc_empresa, 0, 10) . "001" . "' ");
$datos_empresa = mysqli_fetch_assoc($busca_empresa);
$nombre_empresa = $datos_empresa['nombre_comercial'];
$html_encabezado = '<p align="center">' . $nombre_empresa . '</p><br>
				  <p align="center">' . utf8_decode('DETALLE DE CUENTAS POR COBRAR') . '</p><br>';

$pdf = new funciones_pdf('P', 'mm', 'A4'); //P-L
$pdf->AliasNbPages();
$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
$pdf->SetFont('Arial', 'B', 11); //esta tambien es importante
$prop = array('HeaderColor' => array(213, 219, 219), 'color1' => array(253, 254, 254), 'color2' => array(253, 254, 254), 'padding' => 4);
$pdf->detalle_html($html_encabezado);
$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 200, $height = 200);
imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
$pdf->Image('../docs_temp/' . $ruc_empresa . '.jpg', 10, 10, 20, 20, 'jpg', '');
//$pdf->imageUniformToFill('../docs_temp/'.$ruc_empresa.'.jpg', 10, 10 ,80, 25, "B");//$alignment "B", "T", "L", "R", "C"
//para mostrar todos los clientes
//if (empty($id_cliente)){
resumen_por_cobrar($desde, $hasta, $id_cliente, $vendedor);
$busca_clientes = mysqli_query($con, "SELECT DISTINCT id_cli_pro as id, nombre_cli_pro as nombre  FROM saldo_porcobrar_porpagar WHERE ruc_empresa = '" . $ruc_empresa . "' order by nombre_cli_pro asc");
$busca_saldos_total = mysqli_query($con, "SELECT sum(total_factura - (total_nc + total_ing  + ing_tmp + total_ret)) as saldo_general FROM saldo_porcobrar_porpagar WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(fecha_documento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' ");
$row_saldo_total = mysqli_fetch_array($busca_saldos_total);
$suma_general = $row_saldo_total['saldo_general'];
if ($suma_general > 0) {
	$pdf->Cell(30);
	$pdf->Cell(160, 8, 'Total por cobrar por facturas de venta: ' . $suma_general . ' Al: ' . date("d-m-Y", strtotime($hasta)), 1, 1, 'C');
}
$pdf->SetFont('Arial', '', 11); //esta tambien es importante
while ($row_clientes = mysqli_fetch_array($busca_clientes)) {
	$ide_cliente = $row_clientes['id'];
	$nombre_cliente = $row_clientes['nombre'];
	$sql_suma_cliente = mysqli_query($con, "SELECT sum(total_factura - (total_nc + total_ing  + ing_tmp + total_ret)) as total_cliente FROM saldo_porcobrar_porpagar WHERE id_cli_pro = '" . $ide_cliente . "' and id_usuario='" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "'");
	$row_total_cliente = mysqli_fetch_array($sql_suma_cliente);
	$total_cliente = $row_total_cliente['total_cliente'];
	if ($total_cliente > 0) {
		$pdf->Ln();
		$pdf->Cell(190, 5, $nombre_cliente . " Saldo: " . $total_cliente, 1, 1, 'L');
		$pdf->AddCol('fecha_documento', 30, utf8_decode('Fecha emisión'), 'L');
		$pdf->AddCol('numero_documento', 40, utf8_decode('Factura'), 'L');
		$pdf->AddCol('total_factura', 20, utf8_decode('Total'), 'R');
		$pdf->AddCol('total_nc', 20, utf8_decode('NC'), 'R');
		$pdf->AddCol('total_ing', 20, utf8_decode('Abonos'), 'R');
		$pdf->AddCol('total_ret', 20, utf8_decode('Ret'), 'R');
		$pdf->AddCol('saldo', 20, utf8_decode('Saldo'), 'R');
		$pdf->AddCol('dias', 20, utf8_decode('Días'), 'C');
		$pdf->Table($con, "SELECT DATE_FORMAT(fecha_documento, '%d-%m-%Y')as fecha_documento, numero_documento, FORMAT((total_factura - (total_nc + total_ing  + ing_tmp + total_ret)),2) as saldo, FORMAT(total_factura,2) as total_factura, FORMAT(total_nc,2) as total_nc, FORMAT(total_ing + ing_tmp ,2) as total_ing, FORMAT(total_ret,2) as total_ret,  DATEDIFF(NOW(),fecha_documento) as dias FROM saldo_porcobrar_porpagar WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(fecha_documento, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and id_cli_pro='" . $ide_cliente . "' order by dias desc ", $prop, 'cascada');
	}
}


$busca_clientes = clientes_recibos($con, $hasta, $ruc_empresa, $id_cliente, $vendedor);
$registros_recibos = mysqli_num_rows($busca_clientes);
if ($registros_recibos > 0) {
	$saldo_total_recibos = saldo_total_recibos($con, $hasta, $ruc_empresa);
	if ($saldo_total_recibos > 0) {
		if (empty($id_cliente)) {
			$pdf->Ln();
			$pdf->Cell(190, 8, 'Total por cobrar por recibos de venta: ' . $saldo_total_recibos . ' Al: ' . date("d-m-Y", strtotime($hasta)), 1, 1, 'C');
		}
	}
	$pdf->SetFont('Arial', '', 10); //esta tambien es importante
	while ($row_clientes = mysqli_fetch_array($busca_clientes)) {
		$ide_cliente = $row_clientes['id'];
		$nombre_cliente = $row_clientes['nombre'];
		$plazo = $row_clientes['plazo'];

		$total_cliente = saldo_recibo_por_cliente($con, $ruc_empresa, $hasta, $ide_cliente);
		if ($total_cliente > 0) {
			$recibos_individuales = recibos_del_cliente($con, $ruc_empresa, $hasta, $ide_cliente);
			$pdf->Cell(190, 5, $nombre_cliente . " Saldo: " . $total_cliente, 1, 1, 'L');

			$total_saldo_recibos = 0;
			$pdf->SetWidths(array(30, 40, 20, 20, 20, 10, 50));
			$pdf->Row_tabla(array('Fecha', 'Recibo', 'Total', 'Abonos', 'Saldo', utf8_decode('Días'), 'Asesor'));
			while ($row_detalle = mysqli_fetch_assoc($recibos_individuales)) {

				$id_encabezado = $row_detalle['id_encabezado_recibo'];
				$id_documento = "RV" . $id_encabezado;
				$total_recibo = $row_detalle['total_recibo'];
				$fecha_documento = $row_detalle['fecha_recibo'];
				$fecha_vencimiento = date_create($fecha_documento);
				$diferencia_dias = date_diff($fecha_hoy, $fecha_vencimiento);
				$total_dias = $diferencia_dias->format('%a');

				$nombre_vendedor = vendedores_recibos($con, $id_encabezado);

				$suma_abonos_recibo = abonos_cliente_recibo($con, $ruc_empresa, $hasta, $id_documento);
				$saldo = $total_recibo - $suma_abonos_recibo;
				$total_saldo_recibos += $saldo;
				if ($saldo > 0) {
					$pdf->Row_tabla(array(
						date("d-m-Y", strtotime($fecha_documento)),
						$row_detalle['serie_recibo'] . '-' . str_pad($row_detalle['secuencial_recibo'], 9, "000000000", STR_PAD_LEFT),
						number_format($row_detalle['total_recibo'], 2, '.', ''),
						number_format($suma_abonos_recibo, 2, '.', ''),
						number_format($saldo, 2, '.', ''),
						$total_dias,
						$nombre_vendedor
					));
				}
			}
			$pdf->Ln();
		}
	}
}

$pdf->Footer();
$pdf->Output("Cuentas por cobrar al " . date("d-m-Y", strtotime($hasta)) . ".pdf", "D");
unlink('../docs_temp/' . $ruc_empresa . '.jpg');
//}
