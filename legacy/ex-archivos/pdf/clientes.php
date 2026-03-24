<?php
/**
 * PDF - Listado de Clientes (legacy)
 * Se recomienda usar: /sistema/api/cliente.php?action=pdf_clientes (MVC + TCPDF)
 */
require_once("../conexiones/conectalogin.php");
require_once(__DIR__ . '/funciones_pdf.php');

$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

// Sin iden_comprador (puede no existir). Mapeo de tipo_id en PHP.
$chk_prov = @mysqli_query($con, "SHOW TABLES LIKE 'provincia'");
$chk_ciud = @mysqli_query($con, "SHOW TABLES LIKE 'ciudad'");
$chk_ven = @mysqli_query($con, "SHOW TABLES LIKE 'vendedores'");
$join_prov = ($chk_prov && mysqli_num_rows($chk_prov) > 0) ? "LEFT JOIN provincia pro ON pro.codigo=cli.provincia" : "";
$join_ciud = ($chk_ciud && mysqli_num_rows($chk_ciud) > 0) ? "LEFT JOIN ciudad ciu ON ciu.codigo=cli.ciudad" : "";
$join_ven = ($chk_ven && mysqli_num_rows($chk_ven) > 0) ? "LEFT JOIN vendedores ven ON ven.id_vendedor=cli.id_vendedor" : "";

$map_tipo = ['04'=>'RUC','05'=>'Cédula','06'=>'Pasaporte','07'=>'Consumidor final','08'=>'Identificación del exterior'];

$consulta = "SELECT cli.status as status, cli.tipo_id as tipo_id, cli.ruc as ruc, cli.nombre as nombre,
	cli.telefono as telefono, cli.email as email, cli.direccion as direccion, cli.plazo as plazo,
	" . ($chk_prov && mysqli_num_rows($chk_prov) ? "pro.nombre as provincia" : "'' as provincia") . ",
	" . ($chk_ciud && mysqli_num_rows($chk_ciud) ? "ciu.nombre as ciudad" : "'' as ciudad") . ",
	" . ($chk_ven && mysqli_num_rows($chk_ven) ? "ven.nombre as vendedor" : "'' as vendedor") . "
	FROM clientes as cli $join_prov $join_ciud $join_ven
	WHERE cli.ruc_empresa='" . mysqli_real_escape_string($con, $ruc_empresa) . "' ORDER BY cli.nombre ASC";
$resultado = mysqli_query($con, $consulta);

if (mysqli_num_rows($resultado) > 0) {
	$sql_empresa = "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "'";
	$resultado_empresa = mysqli_query($con, $sql_empresa);
	$empresa_info = mysqli_fetch_array($resultado_empresa);
	$nombre_empresa = $empresa_info['nombre'];

	$pdf = new funciones_pdf('L', 'mm', 'A4');
	$pdf->AliasNbPages();
	$pdf->AddPage();
	$pdf->SetFont('Arial', 'B', 14);
	$pdf->Cell(0, 6, utf8_decode($nombre_empresa), 0, 1, 'C');
	$pdf->SetFont('Arial', '', 10);
	$pdf->Cell(0, 5, utf8_decode('Listado de Clientes'), 0, 1, 'C');
	$pdf->Ln(3);

	$pdf->SetFont('Arial', 'B', 8);
	$pdf->SetWidths(array(18, 22, 45, 35, 25, 50, 15, 25, 25, 18, 30));
	$pdf->SetFillColor(74, 111, 165);
	$pdf->SetTextColor(255, 255, 255);
	$pdf->Row_tabla(array(
		utf8_decode('Tipo'), utf8_decode('Identificación'), 'Nombre', utf8_decode('Dirección'),
		utf8_decode('Teléfono'), 'Mail', 'Plazo', 'Provincia', 'Ciudad', 'Status', 'Vendedor'
	), true, [74, 111, 165], [255, 255, 255]);

	$pdf->SetFillColor(255, 255, 255);
	$pdf->SetTextColor(0, 0, 0);
	$pdf->SetFont('Arial', '', 7);

	while ($fila = mysqli_fetch_array($resultado)) {
		$tipo = $map_tipo[$fila['tipo_id'] ?? ''] ?? ($fila['tipo_id'] ?? '');
		$pdf->Row_tabla(array(
			utf8_decode($tipo),
			$fila['ruc'],
			utf8_decode(strtoupper($fila['nombre'])),
			utf8_decode(strtoupper($fila['direccion'] ?? '')),
			$fila['telefono'] ?? '',
			$fila['email'] ?? '',
			($fila['plazo'] ?? 0) . " Días",
			utf8_decode($fila['provincia'] ?? ''),
			utf8_decode($fila['ciudad'] ?? ''),
			($fila['status'] == 1 ? "Activo" : "Inactivo"),
			utf8_decode($fila['vendedor'] ?? '')
		));
	}

	$pdf->Output("Clientes.pdf", "D");
	exit;
} else {
	echo 'No hay resultados para mostrar';
}
