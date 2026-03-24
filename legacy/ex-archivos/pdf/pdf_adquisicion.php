<?php
include("../conexiones/conectalogin.php");
require('../pdf/funciones_pdf.php');
ini_set('date.timezone','America/Guayaquil');

//include("../core/db.php");
//$db = new db();
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario_actual = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
if (isset($_GET['action']) && isset($_GET['codigo_documento']) && $action=="adquisicion"){
$codigo_unico=$_GET['codigo_documento'];

$busca_encabezado=mysqli_query($con, "SELECT * FROM encabezado_compra as enc_com LEFT JOIN proveedores as pro ON pro.id_proveedor=enc_com.id_proveedor LEFT JOIN comprobantes_autorizados as com_aut ON com_aut.id_comprobante=enc_com.id_comprobante WHERE enc_com.codigo_documento = '".$codigo_unico."' ");
$row_encabezados = mysqli_fetch_array($busca_encabezado);
$fecha_emision = date("d-m-Y", strtotime($row_encabezados['fecha_compra']));
$numero_documento = $row_encabezados['numero_documento'];
$ruc_proveedor = $row_encabezados['ruc_proveedor'];
$aut_sri = $row_encabezados['aut_sri'];
$razon_social = $row_encabezados['razon_social'];
$nombre_comercial = $row_encabezados['nombre_comercial'];
$dir_proveedor = $row_encabezados['dir_proveedor'];
$id_comprobante = $row_encabezados['id_comprobante'];
$comprobante = $row_encabezados['comprobante'];
$total_compra = $row_encabezados['total_compra'];
$propina = $row_encabezados['propina'];
$otros_val = $row_encabezados['otros_val'];


$busca_empresa = mysqli_query($con,"SELECT * FROM empresas WHERE ruc = '".$ruc_empresa."' ");
$datos_empresa=mysqli_fetch_assoc($busca_empresa);
$nombre_empresa = $datos_empresa['nombre'];


$pdf = new funciones_pdf( 'P', 'mm', 'A4' );//P-L
//$pdf->AliasNbPages();
$pdf->AddPage();//es importante agregar esta linea para saber la pagina inicial
$pdf->SetFont('Arial','B',10);//esta tambien es importante
$prop = array('HeaderColor'=>array(213, 219, 219),'color1'=>array(253, 254, 254),'color2'=>array(253, 254, 254),'padding'=>2);

$pdf->SetXY(119, 8);
$pdf->Cell(85,42,'',1,1,'L');
$pdf->SetXY(120, 10);
$pdf->Cell(80,5,"RUC: ".utf8_decode(strtoupper($ruc_proveedor)),0,1,'L');
$pdf->SetXY(120, 15);
$pdf->Cell(80,5,utf8_decode(strtoupper($comprobante)),0,1,'L');
$pdf->SetXY(120, 20);
$pdf->Cell(80,5,"No: ".utf8_decode(strtoupper($numero_documento)),0,1,'L');
$pdf->SetXY(120, 25);
$pdf->Cell(80,5,utf8_decode("NÚMERO DE AUTORIZACIÓN"),0,1,'L');
$pdf->SetFont('Arial','',8);
$pdf->SetXY(120, 30);
$pdf->Cell(80,5,$aut_sri,0,1,'L');
$pdf->SetFont('Arial','',10);
$pdf->SetXY(120, 35);
$pdf->Cell(80,5,utf8_decode("FECHA Y HORA DE AUTORIZACIÓN"),0,1,'L');
$pdf->SetXY(120, 40);
$pdf->Cell(80,5,$fecha_emision,0,1,'L');
//$pdf->SetXY(120, 45);
//$pdf->Cell(80,5,"||||||||||||||||||||||||",0,1,'L');

//datos del proveedor
$pdf->SetXY(8, 34);
$pdf->Cell(110,16,'',1,1,'L');
$pdf->SetXY(10, 35);
$pdf->Cell(80,5,utf8_decode(strtoupper($razon_social)),0,1,'L');
$pdf->SetXY(10, 40);
$pdf->Cell(80,5,utf8_decode(strtolower($nombre_comercial)),0,1,'L');
$pdf->SetXY(10, 45);
$pdf->Cell(80,5,utf8_decode('Dirección: ').utf8_decode(strtolower($dir_proveedor)),0,1,'L');

//datos de la empresa esta
$pdf->SetXY(8, 53);
$pdf->Cell(195,13,'',1,1,'L');
$pdf->SetXY(10, 55);
$pdf->MultiCell(100, 5, utf8_decode("Razón Social: ").utf8_decode(strtoupper($nombre_empresa)),0,1);
$pdf->SetXY(120, 55);
$pdf->Cell(80,5,utf8_decode("Identificación: ").utf8_decode(strtoupper(substr($ruc_empresa,0,12)."1")),0,1,'L');
$pdf->SetXY(120, 60);
$pdf->Cell(80,5,utf8_decode("Fecha emisión: ").utf8_decode(strtoupper($fecha_emision)),0,1,'L');

//detalle de la factura
$pdf->Ln();
$pdf->AddCol(utf8_decode('codigo_producto'),20,utf8_decode('Código'),'L');
$pdf->AddCol(utf8_decode('cantidad'),20,utf8_decode('Cantidad'),'R');
$pdf->AddCol(utf8_decode('detalle_producto'),85,utf8_decode('Descripción'),'L');
$pdf->AddCol(utf8_decode('precio'),20,utf8_decode('Precio'),'R');
$pdf->AddCol(utf8_decode('descuento'),20,utf8_decode('Descuento'),'R');
$pdf->AddCol(utf8_decode('subtotal'),30,utf8_decode('Subtotal'),'R');
$pdf->Table($con, "SELECT * FROM cuerpo_compra WHERE codigo_documento = '".$codigo_unico."' ", $prop, 'cascada');

//detalle de totales
$prop = array('HeaderColor'=>array(213, 219, 219),'color1'=>array(253, 254, 254),'color2'=>array(253, 254, 254),'padding'=>2, 'align'=>'R');
$pdf->Ln();

$tarifa_iva = mysqli_query($con,"SELECT format(sum(round(cue_com.subtotal,2)),2) as subtotal, concat('Subtotal ',ti.tarifa) as tarifa FROM cuerpo_compra as cue_com INNER JOIN tarifa_iva as ti ON ti.codigo=cue_com.det_impuesto WHERE cue_com.codigo_documento='".$codigo_unico."' group by cue_com.det_impuesto ");

$ancho_blanco=123;
$ancho_tarifa=50;
$ancho_valor=20;
while ($row_tarifa=mysqli_fetch_array($tarifa_iva)){
$pdf->Cell($ancho_blanco, 5, '',0,0,'L');
$pdf->Cell($ancho_tarifa, 5, utf8_decode(strtoupper($row_tarifa['tarifa'])),1,0,'L');
$pdf->Cell($ancho_valor, 5, number_format($row_tarifa['subtotal'],2,'.',''),1,1,'R');
}

$suma_subtotal = mysqli_query($con,"SELECT format(sum(subtotal),2) as suma_subtotal, format(sum(descuento),2) as suma_descuento FROM cuerpo_compra WHERE codigo_documento='".$codigo_unico."' ");
$row_suma_subtotal=mysqli_fetch_array($suma_subtotal);
$total_sin_impuestos=$row_suma_subtotal['suma_subtotal'];
$total_descuentos=$row_suma_subtotal['suma_descuento'];

$pdf->Cell($ancho_blanco, 5, '',0,0,'L');
$pdf->Cell($ancho_tarifa, 5, utf8_decode('SUBTOTAL SIN IMPUESTOS'),1,0,'L');
$pdf->Cell($ancho_valor, 5, number_format($total_sin_impuestos,2,'.',''),1,1,'R');


$pdf->Cell($ancho_blanco, 5, '',0,0,'L');
$pdf->Cell($ancho_tarifa, 5, utf8_decode('TOTAL DESCUENTO'),1,0,'L');
$pdf->Cell($ancho_valor, 5, number_format($total_descuentos,2,'.',''),1,1,'R');

$suma_ice = mysqli_query($con,"SELECT format(sum(subtotal),2) as suma_ice FROM cuerpo_compra WHERE codigo_documento='".$codigo_unico."' and impuesto=3 ");
$row_suma_ice=mysqli_fetch_array($suma_ice);
$total_ice=$row_suma_ice['suma_ice'];
$pdf->Cell($ancho_blanco, 5, '',0,0,'L');
$pdf->Cell($ancho_tarifa, 5, utf8_decode('ICE'),1,0,'L');
$pdf->Cell($ancho_valor, 5, number_format($total_ice,2,'.',''),1,1,'R');

$suma_iva = mysqli_query($con,"SELECT format(sum(subtotal*0.12),2) as suma_iva FROM cuerpo_compra WHERE codigo_documento='".$codigo_unico."' and impuesto=2 and det_impuesto=2 ");
$row_suma_iva=mysqli_fetch_array($suma_iva);
$total_iva=$row_suma_iva['suma_iva'];
$pdf->Cell($ancho_blanco, 5, '',0,0,'L');
$pdf->Cell($ancho_tarifa, 5, utf8_decode('IVA 12%'),1,0,'L');
$pdf->Cell($ancho_valor, 5, number_format($total_iva,2,'.',''),1,1,'R');

$suma_botella = mysqli_query($con,"SELECT format(sum(subtotal),2) as suma_botella FROM cuerpo_compra WHERE codigo_documento='".$codigo_unico."' and impuesto=5 ");
$row_suma_botella=mysqli_fetch_array($suma_botella);
$total_botella=$row_suma_botella['suma_botella'];
$pdf->Cell($ancho_blanco, 5, '',0,0,'L');
$pdf->Cell($ancho_tarifa, 5, utf8_decode('IRBPNR'),1,0,'L');
$pdf->Cell($ancho_valor, 5, number_format($total_botella,2,'.',''),1,1,'R');

$pdf->Cell($ancho_blanco, 5, '',0,0,'L');
$pdf->Cell($ancho_tarifa, 5, utf8_decode('PROPINA'),1,0,'L');
$pdf->Cell($ancho_valor, 5, number_format($propina,2,'.',''),1,1,'R');

$pdf->Cell($ancho_blanco, 5, '',0,0,'L');
$pdf->Cell($ancho_tarifa, 5, utf8_decode('VALOR TOTAL'),1,0,'L');
$pdf->Cell($ancho_valor, 5, number_format($total_compra,2,'.',''),1,1,'R');

//detalle adicionales
$pdf->Ln();
$pdf->Cell(190,5,'Detalle adicionales',1,1,'L');
$pdf->AddCol(utf8_decode('adicional_concepto'),90,utf8_decode('Concepto'),'L');
$pdf->AddCol(utf8_decode('adicional_descripcion'),100,utf8_decode('Detalle'),'R');
$pdf->Table($con, "SELECT * FROM detalle_adicional_compra WHERE codigo_documento = '".$codigo_unico."' ", $prop, 'cascada');

//detalle formas de pago
$pdf->Ln();
$pdf->AddCol(utf8_decode('forma_pago'),130,utf8_decode('Forma de pago'),'L');
$pdf->AddCol(utf8_decode('total_pago'),20,utf8_decode('Valor'),'R');
$pdf->AddCol(utf8_decode('plazo_pago'),20,utf8_decode('Plazo'),'R');
$pdf->AddCol(utf8_decode('tiempo_pago'),20,utf8_decode('Tiempo'),'R');
$pdf->Table($con, "SELECT fp.nombre_pago as forma_pago, fpc.total_pago as total_pago,  fpc.plazo_pago as plazo_pago, fpc.tiempo_pago as tiempo_pago  FROM formas_pago_compras as fpc INNER JOIN formas_de_pago as fp ON fp.codigo_pago=fpc.forma_pago WHERE fpc.codigo_documento = '".$codigo_unico."' ", $prop, 'cascada');

$pdf->Output($comprobante." No. ".$numero_documento.".pdf","D");
unlink('../docs_temp/'.$ruc_empresa.'.jpg');
}

?>
