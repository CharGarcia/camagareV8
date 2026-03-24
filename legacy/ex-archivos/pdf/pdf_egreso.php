<?php
include("../conexiones/conectalogin.php");
require('../pdf/funciones_pdf.php');
require('../helpers/helpers.php');
ini_set('date.timezone', 'America/Guayaquil');

//include("../core/db.php");
//$db = new db();
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario_actual = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if (isset($_GET['action']) && isset($_GET['codigo_documento']) && $action == "egreso") {
	$codigo_unico = $_GET['codigo_documento'];

	$busca_encabezado = mysqli_query($con, "SELECT * FROM ingresos_egresos WHERE codigo_documento = '" . $codigo_unico . "' ");
	$row_encabezados = mysqli_fetch_array($busca_encabezado);
	$fecha_emision = date("d-m-Y", strtotime($row_encabezados['fecha_ing_egr']));
	$numero_egreso = $row_encabezados['numero_ing_egr'];
	$proveedor = $row_encabezados['nombre_ing_egr'];
	$observaciones = $row_encabezados['detalle_adicional'];
	$usuario = $row_encabezados['id_usuario'];
	$valor_egreso = number_format($row_encabezados['valor_ing_egr'], 2, '.', '');
	$codigo_contable = array();

	$codigo_contable[] = $row_encabezados['codigo_contable'];

	$codigo_unico_contable = array();
	foreach ($codigo_contable as $codigo) {
		$busca_codigo_contable = mysqli_query($con, "SELECT * FROM encabezado_diario WHERE id_diario = '" . $codigo . "' and ruc_empresa='" . substr($ruc_empresa, 0, 10) . "001" . "'");
		$row_codigo_contable = mysqli_fetch_array($busca_codigo_contable);
		$codigo_unico_contable[] = $row_codigo_contable['codigo_unico'];
	}

	//para buscar la imagen
	$busca_imagen = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$datos_imagen = mysqli_fetch_assoc($busca_imagen);
	$imagen = "../logos_empresas/" . $datos_imagen['logo_sucursal'];

	$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . substr($ruc_empresa, 0, 10) . "001" . "' ");
	$datos_empresa = mysqli_fetch_assoc($busca_empresa);
	$nombre_empresa = $datos_empresa['nombre_comercial'];
	$html_encabezado = '<p align="center">' . $nombre_empresa . '</p><br>
				  <p align="center">' . utf8_decode('COMPROBANTE DE EGRESO') . '</p><br>';

	//PARA CONSULTAR EL ASIENTO CONTABLE
	//$datos_asiento=array();
	foreach ($codigo_unico_contable as $codigo_unico_detalle) {
		$datos_asiento[] = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, 
det.debe as debe, det.haber as haber, det.detalle_item as detalle_item FROM plan_cuentas plan 
INNER JOIN detalle_diario_contable det ON plan.id_cuenta=det.id_cuenta 
WHERE plan.ruc_empresa='" . $ruc_empresa . "' and det.codigo_unico='" . $codigo_unico_detalle . "' ");
	}

	$pdf = new funciones_pdf('P', 'mm', 'A4'); //P-L
	$pdf->AliasNbPages();
	$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 200, $height = 200);
	imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
	//$pdf->AliasNbPages();
	$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
	$pdf->SetFont('Arial', '', 10); //esta tambien es importante

	$pdf->detalle_html($html_encabezado);
	$pdf->Cell(50);
	$pdf->Cell(140, 5, 'No. Egreso:  ' . $numero_egreso, 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->Cell(140, 5, 'Total egreso:  ' . $valor_egreso, 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->Cell(140, 5, utf8_decode('Fecha emisión: 			') . $fecha_emision, 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->MultiCell(140, 5, 'Beneficiario:      ' . utf8_decode($proveedor), 1, 1);

	$registros_ing_egr = mysqli_query($con, "SELECT * FROM detalle_ingresos_egresos WHERE codigo_documento = '" . $codigo_unico . "' ");
	$pagos_ing_egr = mysqli_query($con, "SELECT * FROM formas_pagos_ing_egr WHERE codigo_documento = '" . $codigo_unico . "' ");

	$pdf->Image('../docs_temp/' . $ruc_empresa . '.jpg', 20, 20, 30, 30, 'jpg', '');

	$pdf->Ln();
	$pdf->Cell(190, 5, 'Detalle de documentos en el egreso', 1, 1, 'L');
	$pdf->SetWidths(array(40, 130, 20));
	$pdf->Row_tabla(array(utf8_decode('Tipo'), utf8_decode('Detalle'), utf8_decode('Valor')));
	while ($detalle_ing_egr = mysqli_fetch_assoc($registros_ing_egr)) {
		$tipo_ing_egr = $detalle_ing_egr['tipo_ing_egr'];
		if (!is_numeric($tipo_ing_egr)) {
			$tipo_asiento = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE codigo='" . $tipo_ing_egr . "' ");
			$row_asiento = mysqli_fetch_assoc($tipo_asiento);
			$transaccion = $row_asiento['tipo_asiento'];
		} else {
			$tipo_pago = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE id='" . $tipo_ing_egr . "' and tipo_opcion ='2' ");
			$row_tipo_pago = mysqli_fetch_assoc($tipo_pago);
			$transaccion = $row_tipo_pago['descripcion'];
		}
		$valor_ing_egr = number_format($detalle_ing_egr['valor_ing_egr'], 2, '.', '');
		$detalle = $detalle_ing_egr['detalle_ing_egr'];
		$pdf->Row_tabla(array(utf8_decode($transaccion), utf8_decode($detalle), $valor_ing_egr));
	}

	$pdf->Ln();
	$pdf->Cell(190, 5, 'Detalle del pago', 1, 1, 'L');
	$pdf->SetWidths(array(30, 90, 20, 30, 20));
	$pdf->Row_tabla(array(utf8_decode('Forma'), utf8_decode('Cuenta bancaria'), utf8_decode('N. Cheque'), utf8_decode('Fecha cobro'), utf8_decode('Valor')));

	while ($detalle_pagos = mysqli_fetch_assoc($pagos_ing_egr)) {
		$codigo_forma_pago = $detalle_pagos['codigo_forma_pago'];
		$id_cuenta = $detalle_pagos['id_cuenta'];

		if ($id_cuenta > 0) {
			$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.id_cuenta ='" . $id_cuenta . "'");
			$row_cuenta = mysqli_fetch_array($cuentas);
			$cuenta_bancaria = strtoupper($row_cuenta['cuenta_bancaria']);
			$forma_pago = $detalle_pagos['detalle_pago'];
			switch ($forma_pago) {
				case "D":
					$tipo = 'Débito';
					break;
				case "C":
					$tipo = 'Cheque';
					break;
				case "T":
					$tipo = 'Transferencia';
					break;
			}
			$forma_pago = $tipo;
		}

		if ($codigo_forma_pago > 0) {
			$opciones_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE id ='" . $codigo_forma_pago . "'");
			$row_opciones_pagos = mysqli_fetch_array($opciones_pagos);
			$forma_pago = strtoupper($row_opciones_pagos['descripcion']);
			$cuenta_bancaria = "";
		}

		$valor_forma_pago =  number_format($detalle_pagos['valor_forma_pago'], 2, '.', '');

		$pdf->Row_tabla(array(utf8_decode($forma_pago), utf8_decode($cuenta_bancaria), $detalle_pagos['cheque'], $detalle_pagos['cheque'] > 0 ? date('d-m-Y', strtotime($detalle_pagos['fecha_pago'])) : "", $valor_forma_pago));
	}

	$pdf->Ln();
	$pdf->MultiCell(190, 5, 'Observaciones:' . utf8_decode($observaciones), 1, 1);
	$pdf->Ln();

	//PARA MOSTRAR EL ASIENTO CONTABLE
	if (array_sum($codigo_contable) > 0) {
		$pdf->Cell(190, 5, 'Asiento contable', 1, 1, 'L');
		$pdf->SetWidths(array(30, 50, 70, 20, 20));
		$pdf->Row_tabla(array(utf8_decode('Código'), 'Cuenta', 'Detalle', 'Debe', 'Haber'));
		$suma_debe = 0;
		$suma_haber = 0;

		foreach ($datos_asiento as $resultado) {
			while ($row_asiento = mysqli_fetch_array($resultado)) {
				$pdf->Row_tabla(array($row_asiento['codigo_cuenta'], utf8_decode($row_asiento['nombre_cuenta']), utf8_decode($row_asiento['detalle_item']), $row_asiento['debe'], $row_asiento['haber']));
				$suma_debe += $row_asiento['debe'];
				$suma_haber += $row_asiento['haber'];
			}
		}
		$pdf->Cell(150, 6, 'Sumas:', 2, 0, 'R');
		$pdf->Cell(20, 6, number_format($suma_debe, 2, '.', ''), 1, 0, 'R');
		$pdf->Cell(20, 6, number_format($suma_haber, 2, '.', ''), 1, 0, 'R');
		$pdf->Ln();
	}
	//HASTA AQUI SE MUESTRA EL ASIENTO

	$pdf->detalle_html('<p align="center"></p><hr>'); //linea
	//$pdf->detalle_html('<br><br>');
	//$pdf->SetXY(10, 95);
	$pdf->Cell(190, 5, utf8_decode("REALIZADO POR                           APROBADO POR                                              RECIBÍ CONFORME"), 0, 1, 'L');
	$pdf->Ln();
	$pdf->Cell(190, 5, utf8_decode("Emisor: " . $nombre_empresa), 0, 1, 'L');
	$pdf->Cell(190, 5, utf8_decode("Receptor: " . $proveedor), 0, 1, 'L');

	//$pdf->SetY(5);
	//$pdf->Cell(0, 5, utf8_decode('Pág:') . $pdf->PageNo(), 0, 0, 'R');

	$pdf->Output("EGRESO No. " . $numero_egreso . ".pdf", "D");
	unlink('../docs_temp/' . $ruc_empresa . '.jpg');
}
