<?php
include("../conexiones/conectalogin.php");
require('../pdf/funciones_pdf.php');

//include("../core/db.php");
//$db = new db();
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario_actual = $_SESSION['id_usuario'];

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if (isset($_GET['action']) && isset($_GET['id_diario']) && $action == "diario_contable") {
	$id_diario = $_GET['id_diario'];
	$datos_encabezados = mysqli_query($con, "SELECT enc_dia.codigo_unico as codigo_unico, 
enc_dia.tipo as tipo, enc_dia.fecha_asiento as fecha_asiento, 
enc_dia.id_diario as numero_asiento, enc_dia.concepto_general as concepto_general, 
usu.nombre as nombre, enc_dia.estado as estado, enc_dia.fecha_registro as fecha_registro,
enc_dia.codigo_unico_bloque as codigo_unico_bloque 
FROM encabezado_diario as enc_dia 
INNER JOIN usuarios as usu ON enc_dia.id_usuario= usu.id 
WHERE enc_dia.id_diario = '" . $id_diario . "' ");
	$row_encabezados = mysqli_fetch_assoc($datos_encabezados);
	$fecha_asiento = date("d-m-Y", strtotime($row_encabezados['fecha_asiento']));
	$numero_asiento = $row_encabezados['numero_asiento'];
	$concepto_general = $row_encabezados['concepto_general'];
	$realizado_por = $row_encabezados['nombre'];
	$estado = $row_encabezados['estado'];
	$tipo = $row_encabezados['tipo'];
	$codigo_unico = $row_encabezados['codigo_unico'];
	$codigo_unico_bloque = $row_encabezados['codigo_unico_bloque'];
	$fecha_registro = date("d-m-Y", strtotime($row_encabezados['fecha_registro']));

	//para buscar la imagen
	$busca_imagen = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$datos_imagen = mysqli_fetch_assoc($busca_imagen);
	$imagen = "../logos_empresas/" . $datos_imagen['logo_sucursal'];

	$busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "' ");
	$datos_empresa = mysqli_fetch_assoc($busca_empresa);
	$nombre_empresa = $datos_empresa['nombre'];
	$html_encabezado = '<p align="center">' . $nombre_empresa . '</p><br>
				  <p align="center">ASIENTO CONTABLE</p><br>
				  <p align="center">Asiento No.: ' . $numero_asiento . '</p><br>
				  <p align="left">Fecha asiento: ' . $fecha_asiento . '  Fecha del registro: ' . $fecha_registro . ' Estado del registro: ' . $estado . '</p><br>
				  <p align="left">Tipo de registro: ' . $tipo . ' Realizado por: ' . $realizado_por . '</p><br>
				  <p align="left">Concepto general: ' . $concepto_general . '</p><br>';
	//para sacar el asiento

	$datos_asiento = mysqli_query($con, "SELECT * FROM detalle_diario_contable as det 
	INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det.id_cuenta 
	WHERE det.codigo_unico = '" . $codigo_unico . "' and det.codigo_unico_bloque = '" . $codigo_unico_bloque . "' and det.ruc_empresa='" . $ruc_empresa . "'");

	//para las sumas
	$datos_sumas = mysqli_query($con, "SELECT sum(debe) as debe, sum(haber) as haber FROM detalle_diario_contable 
	WHERE codigo_unico='" . $codigo_unico . "' and codigo_unico_bloque = '" . $codigo_unico_bloque . "' and ruc_empresa='" . $ruc_empresa . "'");
	$row_sumas = mysqli_fetch_assoc($datos_sumas);
	$suma_debe = $row_sumas['debe'];
	$suma_haber = $row_sumas['haber'];
	$html_sumas = '<p align="center">' . $suma_debe . '' . $suma_haber . '</p><br>';

	$pdf = new funciones_pdf('P', 'mm', 'A4'); //P
	$pdf->AliasNbPages();
	$imagen_optimizada = $pdf->imagen_optimizada($imagen, $width = 200, $height = 200);
	imagejpeg($imagen_optimizada, '../docs_temp/' . $ruc_empresa . '.jpg');
	$pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
	$pdf->SetFont('Arial', '', 9); //esta tambien es importante
	$pdf->detalle_html(utf8_decode($html_encabezado));
	$pdf->Image('../docs_temp/' . $ruc_empresa . '.jpg', 10, 10, 20, 20, 'jpg', '');
	$pdf->SetWidths(array(30, 50, 70, 20, 20));
	$pdf->Row_tabla(array(utf8_decode('Código'), 'Cuenta', 'Detalle', 'Debe', 'Haber'));
	while ($row_asiento = mysqli_fetch_assoc($datos_asiento)) {
		$pdf->Row_tabla(array($row_asiento['codigo_cuenta'], utf8_decode($row_asiento['nombre_cuenta']), utf8_decode($row_asiento['detalle_item']), $row_asiento['debe'], $row_asiento['haber']));
	}

	$pdf->Cell(150, 6, 'Sumas:', 2, 0, 'R');
	$pdf->Cell(20, 6, $suma_debe, 1, 0, 'R');
	$pdf->Cell(20, 6, $suma_haber, 1, 0, 'R');
	//$pdf->detalle_html('<br><br>');
	//$pdf->SetY(5);
	//$pdf->Cell(0,5,utf8_decode('Pág:').$pdf->PageNo(),0,0,'R');

	$pdf->Output("Diario general asiento N. " . $numero_asiento . ".pdf", "D");
	unlink('../docs_temp/' . $ruc_empresa . '.jpg');
}
