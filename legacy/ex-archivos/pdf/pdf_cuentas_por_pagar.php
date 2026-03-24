<?php
require('../pdf/funciones_pdf.php');
//include("../conexiones/conectalogin.php");
require('../clases/egresos.php');
$genera_saldos = new egresos();
$con = conenta_login();
//session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario_actual = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
//if (isset($_POST['action']) && $action=="pdf_ventas_por_cobrar"){
$desde = "2018/01/01";
$hasta = $_POST['fecha_hasta'];
$id_proveedor = $_POST['id_proveedor'];
ini_set('date.timezone', 'America/Guayaquil');
$fecha_hoy = date_create(date("Y-m-d H:i:s"));

//para buscar la imagen
$busca_imagen = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
$datos_imagen = mysqli_fetch_assoc($busca_imagen);
$imagen = "../logos_empresas/" . $datos_imagen['logo_sucursal'];

$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . substr($ruc_empresa, 0, 10) . "001" . "' ");
$datos_empresa = mysqli_fetch_assoc($busca_empresa);
$nombre_empresa = $datos_empresa['nombre_comercial'];
$html_encabezado = '<p align="center">' . $nombre_empresa . '</p>
				  <p align="center">' . utf8_decode('DETALLE DE CUENTAS POR PAGAR-PROVEEDORES') . '</p><br>';

$pdf = new funciones_pdf('P', 'mm', 'A4'); //P-L
$pdf->AliasNbPages();
$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
$pdf->SetFont('Arial', 'B', 10); //esta tambien es importante
$prop = array('HeaderColor' => array(213, 219, 219), 'color1' => array(253, 254, 254), 'color2' => array(253, 254, 254), 'padding' => 4);
$pdf->detalle_html($html_encabezado);
$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 200, $height = 200);
imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
$pdf->Image('../docs_temp/' . $ruc_empresa . '.jpg', 10, 10, 20, 20, 'jpg', '');
//$pdf->imageUniformToFill('../docs_temp/'.$ruc_empresa.'.jpg', 10, 10 ,80, 25, "B");//$alignment "B", "T", "L", "R", "C"

//para mostrar todos los proveedores
if (empty($id_proveedor)) {
	$genera_saldos->saldos_por_pagar($con, $desde, $hasta);

	$busca_proveedores = mysqli_query($con, "SELECT DISTINCT sal.id_proveedor as id_proveedor, sal.razon_social as razon_social FROM saldos_compras_tmp as sal WHERE sal.ruc_empresa = '" . $ruc_empresa . "' order by sal.razon_social asc");

	$busca_saldos_total = mysqli_query($con, "SELECT sum(total_compra - (total_egresos + total_retencion + total_egresos_tmp)) as saldo_general FROM saldos_compras_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and fecha_compra between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "' and id_comprobante !=4");
	$row_saldo_total = mysqli_fetch_array($busca_saldos_total);
	$suma_general = number_format($row_saldo_total['saldo_general'], 2, '.', '');

	$busca_saldos_total_nc = mysqli_query($con, "SELECT sum(total_compra + (total_egresos + total_retencion + total_egresos_tmp)) as saldo_general FROM saldos_compras_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and fecha_compra between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "' and id_comprobante=4 ");
	$row_saldo_total_nc = mysqli_fetch_array($busca_saldos_total_nc);
	$suma_general_nc = number_format($row_saldo_total_nc['saldo_general'], 2, '.', '');

	//$pdf->Ln(10);
	$pdf->Cell(30);
	$pdf->Cell(160, 8, 'Total general por pagar-proveedores: ' . number_format($suma_general - $suma_general_nc, 2, '.', '') . ' Al: ' . date("d-m-Y", strtotime($hasta)), 1, 1, 'C');
	$pdf->SetFont('Arial', '', 10); //esta tambien es importante

	while ($row_proveedor = mysqli_fetch_array($busca_proveedores)) {
		$ide_proveedor = $row_proveedor['id_proveedor'];
		$nombre_proveedor = $row_proveedor['razon_social'];
		$sql_suma_proveedor = mysqli_query($con, "SELECT sum(total_compra - (total_egresos + total_retencion + total_egresos_tmp)) as total_proveedor FROM saldos_compras_tmp WHERE id_proveedor = '" . $ide_proveedor . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and id_comprobante !=4 ");
		$row_total_proveedor = mysqli_fetch_array($sql_suma_proveedor);
		$total_proveedor = $row_total_proveedor['total_proveedor'];

		$sql_suma_proveedor_nc = mysqli_query($con, "SELECT sum(total_compra + (total_egresos + total_retencion + total_egresos_tmp)) as total_proveedor FROM saldos_compras_tmp WHERE id_proveedor = '" . $ide_proveedor . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and id_comprobante =4 ");
		$row_total_proveedor_nc = mysqli_fetch_array($sql_suma_proveedor_nc);
		$total_proveedor_nc = $row_total_proveedor_nc['total_proveedor'];

		if (($total_proveedor - $total_proveedor_nc) != 0) {
			$pdf->Ln();
			$pdf->Cell(190, 5, utf8_decode($nombre_proveedor) . " Saldo: " . number_format($total_proveedor - $total_proveedor_nc, 2, '.', ''), 1, 1, 'L');

			$pdf->SetWidths(array(30, 40, 35, 20, 20, 15, 20, 10));
			$pdf->Row_tabla(array(utf8_decode('Fecha emisión'), utf8_decode('Documento'), utf8_decode('Número'), 'Total', 'Abonos', 'Ret', 'Saldo', utf8_decode('Días')));

			$detalle_porCobrar = mysqli_query($con, "SELECT DATE_FORMAT(sal_tmp.fecha_compra, '%d-%m-%Y')as fecha_documento, 
			comp.comprobante as nombre_documento, sal_tmp.numero_documento as numero_documento, 
			if(sal_tmp.id_comprobante = 4, round(-sal_tmp.total_compra,2), round(sal_tmp.total_compra,2)) as total, 
			if(sal_tmp.id_comprobante = 4, round(-sal_tmp.total_egresos,2), round(sal_tmp.total_egresos,2)) as abonos,
			if(sal_tmp.id_comprobante = 4, round(0,2), round(sal_tmp.total_retencion,2)) as retenciones,
			if(sal_tmp.id_comprobante =4,round(-sal_tmp.total_compra - sal_tmp.total_egresos,2), round(sal_tmp.total_compra - (sal_tmp.total_egresos + sal_tmp.total_retencion + sal_tmp.total_egresos_tmp),2)) as saldo,
			DATEDIFF(NOW(), sal_tmp.fecha_compra) as dias 
			FROM saldos_compras_tmp as sal_tmp 
			INNER JOIN comprobantes_autorizados as comp
			ON comp.id_comprobante=sal_tmp.id_comprobante
			WHERE mid(sal_tmp.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and sal_tmp.fecha_compra between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "' and sal_tmp.id_proveedor='" . $ide_proveedor . "' order by sal_tmp.fecha_compra asc ");
			while ($row_detalle = mysqli_fetch_assoc($detalle_porCobrar)) {
				$pdf->Row_tabla(array(
					$row_detalle['fecha_documento'],
					utf8_decode($row_detalle['nombre_documento']),
					$row_detalle['numero_documento'],
					$row_detalle['total'],
					$row_detalle['abonos'],
					$row_detalle['retenciones'],
					$row_detalle['saldo'],
					$row_detalle['dias']
				));
			}
		}
	}
}
//para mostrar por proveedor individual

if (!empty($id_proveedor)) { //si esta lleno proveedor
	echo $genera_saldos->saldos_por_pagar($con, $desde, $hasta);
	$busca_proveedor = mysqli_query($con, "SELECT * FROM proveedores WHERE id_proveedor='" . $id_proveedor . "'");
	$row_proveedors = mysqli_fetch_array($busca_proveedor);
	$nombre_proveedor = $row_proveedors['razon_social'];


	$busca_saldos_general = mysqli_query($con, "SELECT sum(total_compra - (total_egresos + total_retencion + total_egresos_tmp)) as saldo_general FROM saldos_compras_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and fecha_compra between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "' and id_proveedor='" . $id_proveedor . "' and id_comprobante !=4 group by id_proveedor ");
	$row_saldo_total = mysqli_fetch_array($busca_saldos_general);
	$suma_general = $row_saldo_total['saldo_general'];

	$busca_saldos_general_nc = mysqli_query($con, "SELECT sum(total_compra + (total_egresos + total_retencion + total_egresos_tmp)) as saldo_general FROM saldos_compras_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and fecha_compra between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "' and id_proveedor='" . $id_proveedor . "' and id_comprobante =4 group by id_proveedor ");
	$row_saldo_total_nc = mysqli_fetch_array($busca_saldos_general_nc);
	$suma_general_nc = $row_saldo_total_nc['saldo_general'];


	$pdf->Ln(10);
	$pdf->Cell(190, 8, utf8_decode($nombre_proveedor) . ' Saldo por pagar: ' . number_format($suma_general - $suma_general_nc, 2, '.', '') . ' Al: ' . date("d-m-Y", strtotime($hasta)), 1, 1, 'C');
	$pdf->SetFont('Arial', '', 10); //esta tambien es importante
	$busca_proveedores = mysqli_query($con, "SELECT * FROM proveedores WHERE id_proveedor='" . $id_proveedor . "'");
	while ($row_proveedor = mysqli_fetch_array($busca_proveedores)) {
		$ide_proveedor = $row_proveedor['id_proveedor'];

		$sql_suma_proveedor = mysqli_query($con, "SELECT sum(total_compra - (total_egresos + total_retencion + total_egresos_tmp)) as total_proveedor FROM saldos_compras_tmp WHERE id_proveedor = '" . $ide_proveedor . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and id_comprobante !=4");
		$row_total_cliente = mysqli_fetch_array($sql_suma_proveedor);
		$total_proveedor = number_format($row_total_cliente['total_proveedor'], 2, '.', '');

		$sql_suma_proveedor_nc = mysqli_query($con, "SELECT sum(total_compra + (total_egresos + total_retencion + total_egresos_tmp)) as total_proveedor FROM saldos_compras_tmp WHERE id_proveedor = '" . $ide_proveedor . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and id_comprobante =4");
		$row_total_cliente_nc = mysqli_fetch_array($sql_suma_proveedor_nc);
		$total_proveedor_nc = number_format($row_total_cliente_nc['total_proveedor'], 2, '.', '');

		if (($total_proveedor - $total_proveedor_nc) != 0) {
			$pdf->Ln();
			/*  $pdf->AddCol('fecha_documento',30,utf8_decode('Fecha emisión'),'L');
				$pdf->AddCol(utf8_decode('nombre_documento'),40,utf8_decode('Documento'),'L');
				$pdf->AddCol('numero_documento',35,utf8_decode('Número'),'L');
				$pdf->AddCol('total',20,utf8_decode('Total'),'R');
				$pdf->AddCol('abonos',20,utf8_decode('Abonos'),'R');
				$pdf->AddCol('retenciones',15,utf8_decode('Ret.'),'R');
				$pdf->AddCol('saldo',15,utf8_decode('Saldo'),'R');
				$pdf->AddCol('dias',15,utf8_decode('Días'),'C');
				$pdf->Table($con, "SELECT DATE_FORMAT(sal_tmp.fecha_compra, '%d-%m-%Y')as fecha_documento, 
				(SELECT comprobante FROM comprobantes_autorizados as com_aut WHERE com_aut.id_comprobante=sal_tmp.id_comprobante) as nombre_documento, sal_tmp.numero_documento, 
				if(sal_tmp.id_comprobante = 4, round(-sal_tmp.total_compra,2), round(sal_tmp.total_compra,2)) as total, 
				if(sal_tmp.id_comprobante = 4, round(-sal_tmp.total_egresos,2), round(sal_tmp.total_egresos,2)) as abonos,
				if(sal_tmp.id_comprobante = 4, round(0,2), round(sal_tmp.total_retencion,2)) as retenciones,
				if(sal_tmp.id_comprobante =4,round(-sal_tmp.total_compra - sal_tmp.total_egresos,2), round(sal_tmp.total_compra - (sal_tmp.total_egresos + sal_tmp.total_retencion + sal_tmp.total_egresos_tmp),2)) as saldo,
				DATEDIFF(NOW(), sal_tmp.fecha_compra) as dias FROM saldos_compras_tmp as sal_tmp WHERE mid(sal_tmp.ruc_empresa,1,10) = '".substr($ruc_empresa,0,10)."' and sal_tmp.fecha_compra between '".date("Y-m-d", strtotime($desde))."' and '".date("Y-m-d", strtotime($hasta))."' and sal_tmp.id_proveedor='".$ide_proveedor."' order by sal_tmp.fecha_compra asc ", $prop, 'cascada');
 */
			$pdf->SetWidths(array(30, 40, 35, 20, 20, 15, 20, 10));
			$pdf->Row_tabla(array(utf8_decode('Fecha emisión'), utf8_decode('Documento'), utf8_decode('Número'), 'Total', 'Abonos', 'Ret', 'Saldo', utf8_decode('Días')));

			$detalle_porCobrar = mysqli_query($con, "SELECT DATE_FORMAT(sal_tmp.fecha_compra, '%d-%m-%Y')as fecha_documento, 
			comp.comprobante as nombre_documento, sal_tmp.numero_documento as numero_documento, 
			if(sal_tmp.id_comprobante = 4, round(-sal_tmp.total_compra,2), round(sal_tmp.total_compra,2)) as total, 
			if(sal_tmp.id_comprobante = 4, round(-sal_tmp.total_egresos,2), round(sal_tmp.total_egresos,2)) as abonos,
			if(sal_tmp.id_comprobante = 4, round(0,2), round(sal_tmp.total_retencion,2)) as retenciones,
			if(sal_tmp.id_comprobante =4,round(-sal_tmp.total_compra - sal_tmp.total_egresos,2), round(sal_tmp.total_compra - (sal_tmp.total_egresos + sal_tmp.total_retencion + sal_tmp.total_egresos_tmp),2)) as saldo,
			DATEDIFF(NOW(), sal_tmp.fecha_compra) as dias 
			FROM saldos_compras_tmp as sal_tmp 
			INNER JOIN comprobantes_autorizados as comp
			ON comp.id_comprobante=sal_tmp.id_comprobante
			WHERE mid(sal_tmp.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and sal_tmp.fecha_compra between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "' and sal_tmp.id_proveedor='" . $ide_proveedor . "' order by sal_tmp.fecha_compra asc ");
			while ($row_detalle = mysqli_fetch_assoc($detalle_porCobrar)) {
				$pdf->Row_tabla(array(
					$row_detalle['fecha_documento'],
					utf8_decode($row_detalle['nombre_documento']),
					$row_detalle['numero_documento'],
					$row_detalle['total'],
					$row_detalle['abonos'],
					$row_detalle['retenciones'],
					$row_detalle['saldo'],
					$row_detalle['dias']
				));
			}
		}
	}
}
$pdf->Footer();
$pdf->Output("Cuentas por pagar al " . date("d-m-Y", strtotime($hasta)) . ".pdf", "D");
unlink('../docs_temp/' . $ruc_empresa . '.jpg');
//}
