<?php
include("../conexiones/conectalogin.php");
require('../pdf/funciones_pdf.php');
ini_set('date.timezone', 'America/Guayaquil');

//include("../core/db.php");
//$db = new db();
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario_actual = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if (isset($_GET['action']) && isset($_GET['codigo_unico']) && $action == "ingreso") {
	$codigo_unico = $_GET['codigo_unico'];

	$busca_encabezado = mysqli_query($con, "SELECT * FROM ingresos_egresos WHERE codigo_documento = '" . $codigo_unico . "' ");
	$row_encabezados = mysqli_fetch_array($busca_encabezado);
	$fecha_emision = date("d-m-Y", strtotime($row_encabezados['fecha_ing_egr']));
	$numero_ingreso = $row_encabezados['numero_ing_egr'];
	$cliente = $row_encabezados['nombre_ing_egr'];
	$observaciones = $row_encabezados['detalle_adicional'];
	$usuario = $row_encabezados['id_usuario'];
	$valor_ingreso = number_format($row_encabezados['valor_ing_egr'], 2, '.', '');
	$codigo_contable = $row_encabezados['codigo_contable'];

	$busca_codigo_contable = mysqli_query($con, "SELECT * FROM encabezado_diario WHERE id_diario = '" . $codigo_contable . "' and ruc_empresa='" . substr($ruc_empresa, 0, 10) . "001" . "' ");
	$row_codigo_contable = mysqli_fetch_array($busca_codigo_contable);
	$codigo_unico_contable = $row_codigo_contable['codigo_unico'];


	//PARA CONSULTAR EL ASIENTO CONTABLE
	$datos_asiento = mysqli_query($con, "SELECT plan.codigo_cuenta as codigo_cuenta, plan.nombre_cuenta as nombre_cuenta, det.debe as debe, det.haber as haber, det.detalle_item as detalle_item FROM plan_cuentas plan INNER JOIN detalle_diario_contable det ON plan.id_cuenta=det.id_cuenta WHERE plan.ruc_empresa='" . $ruc_empresa . "' and det.codigo_unico='" . $codigo_unico_contable . "' ");
	//para las sumas
	$datos_sumas = mysqli_query($con, "SELECT sum(debe) as debe, sum(haber) as haber FROM detalle_diario_contable WHERE codigo_unico='" . $codigo_unico_contable . "' ");
	$row_sumas = mysqli_fetch_assoc($datos_sumas);
	$suma_debe = $row_sumas['debe'];
	$suma_haber = $row_sumas['haber'];
	$html_sumas = '<p align="center">' . $suma_debe . '' . $suma_haber . '</p><br>';
	//hasta aqui el asiento contable

	//para buscar la imagen
	$busca_imagen = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$datos_imagen = mysqli_fetch_assoc($busca_imagen);
	$imagen = "../logos_empresas/" . $datos_imagen['logo_sucursal'];
	//copy('../logos_empresas/'.$imagen, '../docs_temp/'.$ruc_empresa.'.jpg');

	$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . substr($ruc_empresa, 0, 10) . "001" . "' ");
	$datos_empresa = mysqli_fetch_assoc($busca_empresa);
	$nombre_empresa = $datos_empresa['nombre_comercial'];
	$html_encabezado = '<p align="center">' . $nombre_empresa . '</p><br>
				  <p align="center">' . utf8_decode('COMPROBANTE DE INGRESO') . '</p><br>';

	$pdf = new funciones_pdf('P', 'mm', 'A4'); //P-L
	$pdf->AliasNbPages();
	$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 200, $height = 200);
	imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
	//$pdf->AliasNbPages();
	$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
	$pdf->SetFont('Arial', '', 10); //esta tambien es importante

	$pdf->detalle_html($html_encabezado);
	$pdf->Cell(50);
	$pdf->Cell(140, 5, 'No. Ingreso:  ' . $numero_ingreso, 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->Cell(140, 5, 'Total ingreso:  ' . $valor_ingreso, 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->Cell(140, 5, utf8_decode('Fecha emisión: 			') . $fecha_emision, 1, 1, 'L');
	$pdf->Cell(50);
	$pdf->Cell(140, 5, 'Recibo de:			' . utf8_decode($cliente), 1, 1, 'L');

	$registros_ing_egr = mysqli_query($con, "SELECT * FROM detalle_ingresos_egresos WHERE codigo_documento = '" . $codigo_unico . "' ");
	$pagos_ing_egr = mysqli_query($con, "SELECT * FROM formas_pagos_ing_egr WHERE codigo_documento = '" . $codigo_unico . "' ");

	$pdf->Image('../docs_temp/' . $ruc_empresa . '.jpg', 20, 20, 30, 30, 'jpg', '');
	$pdf->Ln();
	$pdf->Cell(190, 5, 'Detalle de documentos en el ingreso', 1, 1, 'L');
	$pdf->SetWidths(array(30, 140, 20));
	$pdf->Row_tabla(array(utf8_decode('Tipo'), utf8_decode('Detalle'), utf8_decode('Valor')));
	while ($detalle_ing_egr = mysqli_fetch_assoc($registros_ing_egr)) {
		$tipo_ing_egr = $detalle_ing_egr['tipo_ing_egr'];
		if (!is_numeric($tipo_ing_egr)) {
			$tipo_asiento = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE codigo='" . $tipo_ing_egr . "' ");
			$row_asiento = mysqli_fetch_assoc($tipo_asiento);
			$transaccion = $row_asiento['tipo_asiento'];
		} else {
			$tipo_pago = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE id='" . $tipo_ing_egr . "' and tipo_opcion ='1' ");
			$row_tipo_pago = mysqli_fetch_assoc($tipo_pago);
			$transaccion = $row_tipo_pago['descripcion'];
		}
		$valor_ing_egr = number_format($detalle_ing_egr['valor_ing_egr'], 2, '.', '');
		$detalle = $detalle_ing_egr['detalle_ing_egr'];
		$pdf->Row_tabla(array(utf8_decode($transaccion), utf8_decode($detalle), $valor_ing_egr));
	}

	$pdf->Ln();
	$pdf->Cell(190, 5, 'Detalle del cobro', 1, 1, 'L');
	$pdf->SetWidths(array(30, 140, 20));
	$pdf->Row_tabla(array(utf8_decode('Forma'), utf8_decode('Cuenta bancaria'), utf8_decode('Valor')));

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
					$tipo = 'Depósito';
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

		$pdf->Row_tabla(array(utf8_decode($forma_pago), utf8_decode($cuenta_bancaria), $valor_forma_pago));
	}

	$pdf->Ln();
	$pdf->MultiCell(190, 5, 'Observaciones:' . utf8_decode($observaciones), 1, 1);
	$pdf->Ln();
	//PARA MOSTRAR EL ASIENTO CONTABLE
	if ($codigo_contable > 0) {
		$pdf->Cell(190, 5, 'Asiento contable', 1, 1, 'L');
		$pdf->SetWidths(array(30, 50, 70, 20, 20));
		$pdf->Row_tabla(array(utf8_decode('Código'), 'Cuenta', 'Detalle', 'Debe', 'Haber'));
		while ($row_asiento = mysqli_fetch_assoc($datos_asiento)) {
			$pdf->Row_tabla(array($row_asiento['codigo_cuenta'], utf8_decode($row_asiento['nombre_cuenta']), utf8_decode($row_asiento['detalle_item']), $row_asiento['debe'], $row_asiento['haber']));
		}
		$pdf->Cell(150, 6, 'Sumas:', 2, 0, 'R');
		$pdf->Cell(20, 6, $suma_debe, 1, 0, 'R');
		$pdf->Cell(20, 6, $suma_haber, 1, 0, 'R');
		$pdf->Ln();
	}
	//HASTA AQUI SE MUESTRA EL ASIENTO
	$pdf->detalle_html('<p align="center"></p><hr>');
	$pdf->detalle_html('<p align="center">REALIZADO POR</p><br>');

	//$pdf->detalle_html('<br><br>');

	//$pdf->SetY(5);
	//$pdf->Cell(0, 5, utf8_decode('Pág:') . $pdf->PageNo(), 0, 0, 'R');


	$pdf->Output("INGRESO No. " . $numero_ingreso . ".pdf", "D");
	unlink('../docs_temp/' . $ruc_empresa . '.jpg');
}
