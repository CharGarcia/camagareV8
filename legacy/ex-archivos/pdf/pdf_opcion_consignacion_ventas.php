<?php
include("../conexiones/conectalogin.php");
require('../pdf/funciones_pdf.php');

include("../core/db.php");
$db = new db();
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario_actual = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
if (isset($_GET['action']) && isset($_GET['codigo_unico']) && $action=="consignacion_ventas"){
$codigo_unico=$_GET['codigo_unico'];
$busca_encabezado=mysqli_query($con, "SELECT * FROM encabezado_consignacion as enc_con INNER JOIN clientes as cli ON enc_con.id_cli_pro=cli.id WHERE enc_con.codigo_unico = '".$codigo_unico."' ");
$row_encabezados = mysqli_fetch_array($busca_encabezado);
$fecha_emision = date("d-m-Y", strtotime($row_encabezados['fecha_consignacion']));
$numero_consignacion = $row_encabezados['numero_consignacion'];
$cliente = $row_encabezados['nombre'];
$observaciones = $row_encabezados['observaciones'];
$tipo = utf8_decode('DETALLE DE '.$row_encabezados['operacion']);
$factura = $row_encabezados['operacion'];

//para buscar la imagen
$busca_imagen = mysqli_query($con,"SELECT * FROM sucursales WHERE ruc_empresa = '".$ruc_empresa."' ");
$datos_imagen=mysqli_fetch_assoc($busca_imagen);
$imagen = $datos_imagen['logo_sucursal'];
copy('../logos_empresas/'.$imagen, '../docs_temp/'.$ruc_empresa.'.jpg');

$busca_empresa = mysqli_query($con,"SELECT * FROM empresas WHERE ruc = '".$ruc_empresa."' ");
$datos_empresa=mysqli_fetch_assoc($busca_empresa);
$nombre_empresa = $datos_empresa['nombre_comercial'];
$html_encabezado='<p align="center">'.$nombre_empresa.'</p><br>
				  <p align="center">'.$tipo.'</p><br>';

$pdf = new funciones_pdf( 'L', 'mm', 'A5' );//P
//$pdf->AliasNbPages();
$pdf->AddPage();//es importante agregar esta linea para saber la pagina inicial
$pdf->SetFont('Arial');//esta tambien es importante
$prop = array('HeaderColor'=>array(213, 219, 219),'color1'=>array(253, 254, 254),'color2'=>array(253, 254, 254),'padding'=>2);

$pdf->detalle_html($html_encabezado);
$pdf->Cell(50);
$pdf->Cell(140,5,'No. documento: 			001-001-'.$numero_consignacion,1,1,'L');
$pdf->Cell(50);
$pdf->Cell(140,5,utf8_decode('Fecha emisión: 			').$fecha_emision,1,1,'L');
$pdf->Cell(50);
$pdf->Cell(140,5,'Cliente/Receptor:			'.$cliente,1,1,'L');
if ($factura=='FACTURA'){
$pdf->Cell(50);
$pdf->Cell(140,5,'Factura:			'.$row_encabezados['serie_sucursal']."-".str_pad($row_encabezados['factura_venta'],9,"000000000",STR_PAD_LEFT),1,1,'L');
}

$pdf->Image('../docs_temp/'.$ruc_empresa.'.jpg', 10, 10, 30, 0, 'jpg', '');

$pdf->Ln();
$pdf->AddCol(utf8_decode('codigo_producto'),30,utf8_decode('Código'),'L');
$pdf->AddCol(utf8_decode('nombre_producto'),95,utf8_decode('Descripción'),'L');
$pdf->AddCol(utf8_decode('lote'),20,utf8_decode('Lote'),'L');
$pdf->AddCol(utf8_decode('cant_consignacion'),20,utf8_decode('Cant'),'L');
$pdf->AddCol('vencimiento',25,utf8_decode('Caducidad'),'L');
$pdf->Table($con, "SELECT codigo_producto, nombre_producto, lote, cant_consignacion, DATE_FORMAT(vencimiento, '%m-%Y') as vencimiento,'' FROM detalle_consignacion as det_con WHERE det_con.codigo_unico = '".$codigo_unico."' ", $prop, 'cascada');
$pdf->Ln();

$pdf->AddCol(utf8_decode('observaciones'),190,utf8_decode('Observaciones'),'L');
$pdf->Table($con, "SELECT * FROM encabezado_consignacion WHERE codigo_unico='".$codigo_unico."'", $prop, 'una_fila');

$pdf->Ln();
$pdf->detalle_html('<p align="center"></p><hr>');
$pdf->detalle_html('<p align="center">REALIZADO POR                                             RECIBIDO POR</p><br>');


$pdf->detalle_html('<br><br>');

$pdf->SetY(5);
$pdf->Cell(0,5,utf8_decode('Pág:').$pdf->PageNo(),0,0,'R');


$pdf->Output($row_encabezados['operacion']." CV N. ".$numero_consignacion.".pdf","D");
unlink('../docs_temp/'.$ruc_empresa.'.jpg');
}

?>
