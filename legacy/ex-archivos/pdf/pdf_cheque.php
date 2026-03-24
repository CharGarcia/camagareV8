<?php
include("../conexiones/conectalogin.php");
require('../pdf/fpdf.php');
include('../validadores/numero_letras.php');
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
if (isset($_GET['action']) && isset($_GET['codigo_documento']) && $action=="cheque"){
$codigo_documento=$_GET['codigo_documento'];

$busca_cheque = mysqli_query($con,"SELECT * FROM formas_pagos_ing_egr as for_pag LEFT JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento = for_pag.codigo_documento WHERE for_pag.id_fp = '".$codigo_documento."' ");
$datos_cheque=mysqli_fetch_assoc($busca_cheque);
$nombre_beneficiario = $datos_cheque['nombre_ing_egr'];
$numero_cheque = $datos_cheque['cheque'];
$fecha_cheque = date("Y-m-d", strtotime($datos_cheque['fecha_pago']));
$valor_cheque = number_format($datos_cheque['valor_forma_pago'],2,'.','');

$busca_ciudad = mysqli_query($con,"SELECT * FROM empresas as emp INNER JOIN ciudad as ciu ON ciu.id=emp.cod_ciudad WHERE emp.ruc = '".$ruc_empresa."' ");
$datos_ciudad=mysqli_fetch_assoc($busca_ciudad);
$ciudad = strtoupper($datos_ciudad['nombre']);
$cantidad_letras = num_letras($valor_cheque);


//para buscar los datos
$busca_beneficiario = mysqli_query($con, "SELECT * FROM configurar_cheques WHERE ruc_empresa = '".$ruc_empresa."' and concepto='beneficiario'");
$row_beneficiario=mysqli_fetch_array($busca_beneficiario);

	$ben_ini_der_izq=$row_beneficiario["ini_der_izq"]==null?"16":$row_beneficiario["ini_der_izq"];
	$ben_ini_arr_aba=$row_beneficiario["ini_arr_aba"]==null?"15":$row_beneficiario["ini_arr_aba"];
	$ben_ini_anc_cell=$row_beneficiario["anc_cel"]==null?"107":$row_beneficiario["anc_cel"];
	$ben_ini_alt_cell=$row_beneficiario["alt_cel"]==null?"10":$row_beneficiario["alt_cel"];

$busca_cantidad_numeros = mysqli_query($con, "SELECT * FROM configurar_cheques WHERE ruc_empresa = '".$ruc_empresa."' and concepto='cantidad_numeros'");
$row_cantidad_numeros=mysqli_fetch_array($busca_cantidad_numeros);
	
	$canum_ini_der_izq=$row_cantidad_numeros["ini_der_izq"]==null?"120":$row_cantidad_numeros["ini_der_izq"];
	$canum_ini_arr_aba=$row_cantidad_numeros["ini_arr_aba"]==null?"15":$row_cantidad_numeros["ini_arr_aba"];
	$canum_ini_anc_cell=$row_cantidad_numeros["anc_cel"]==null?"50":$row_cantidad_numeros["anc_cel"];
	$canum_ini_alt_cell=$row_cantidad_numeros["alt_cel"]==null?"10":$row_cantidad_numeros["alt_cel"];

$busca_cantidad_letras = mysqli_query($con, "SELECT * FROM configurar_cheques WHERE ruc_empresa = '".$ruc_empresa."' and concepto='cantidad_letras'");
$row_cantidad_letras=mysqli_fetch_array($busca_cantidad_letras);

	$canle_ini_der_izq=$row_cantidad_letras["ini_der_izq"]==null?"16":$row_cantidad_letras["ini_der_izq"];
	$canle_ini_arr_aba=$row_cantidad_letras["ini_arr_aba"]==null?"25":$row_cantidad_letras["ini_arr_aba"];
	$canle_ini_anc_cell=$row_cantidad_letras["anc_cel"]==null?"135":$row_cantidad_letras["anc_cel"];
	$canle_ini_alt_cell=$row_cantidad_letras["alt_cel"]==null?"6":$row_cantidad_letras["alt_cel"];

$busca_ciudad_fecha = mysqli_query($con, "SELECT * FROM configurar_cheques WHERE ruc_empresa = '".$ruc_empresa."' and concepto='ciudad_fecha'");
$row_ciudad_fecha=mysqli_fetch_array($busca_ciudad_fecha);

	$ciufec_ini_der_izq=$row_ciudad_fecha["ini_der_izq"]==null?"12":$row_ciudad_fecha["ini_der_izq"];
	$ciufec_ini_arr_aba=$row_ciudad_fecha["ini_arr_aba"]==null?"35":$row_ciudad_fecha["ini_arr_aba"];
	$ciufec_ini_anc_cell=$row_ciudad_fecha["anc_cel"]==null?"120":$row_ciudad_fecha["anc_cel"];
	$ciufec_ini_alt_cell=$row_ciudad_fecha["alt_cel"]==null?"10":$row_ciudad_fecha["alt_cel"];


$pdf = new FPDF( 'P', 'mm', 'A4' );
$pdf->AliasNbPages();
$pdf->AddPage();//es importante agregar esta linea para saber la pagina inicial
$pdf->SetFont('Arial','B',10);//esta tambien es importante
$pdf->SetXY($ben_ini_der_izq, $ben_ini_arr_aba);
$pdf->Cell($ben_ini_anc_cell, $ben_ini_alt_cell, strtoupper(utf8_decode($nombre_beneficiario)),0,1,'L');
$pdf->SetXY($canum_ini_der_izq, $canum_ini_arr_aba);
$pdf->Cell($canum_ini_anc_cell, $canum_ini_alt_cell, $valor_cheque,0,1,'L');
$pdf->SetXY($canle_ini_der_izq, $canle_ini_arr_aba);
$pdf->MultiCell($canle_ini_anc_cell, $canle_ini_alt_cell, strtoupper(utf8_decode($cantidad_letras)),0,1);
$pdf->SetXY($ciufec_ini_der_izq, $ciufec_ini_arr_aba);
$pdf->Cell($ciufec_ini_anc_cell, $ciufec_ini_alt_cell, strtoupper(utf8_decode($ciudad)).", ".$fecha_cheque,0,1,'L');

//para pintar de blanco la numeracion de la pagina
//$pdf->Cell(180,5,'',1,1,'L');
$pdf->CoverPageNumber();

$pdf->Output("Cheque ".$numero_cheque.".pdf","D");
unlink('../docs_temp/'.$ruc_empresa.'.jpg');
}

?>
