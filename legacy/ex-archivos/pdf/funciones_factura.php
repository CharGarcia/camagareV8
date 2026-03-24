<?php
require('../pdf/fpdf.php');
class PDF_FACTURA extends FPDF
{

// Compania
function datos_Empresa($logo, $nombre, $nick, $ruc, $direccion, $telefono, $mail)
{
	$x1 = 40; // columna
	$y1 = 10;  // fila
	//posicionamiento hacia abajo
	
	//para el logo, 25 es el tamanio del logo
	if (!empty($logo)){
	$this->Image($logo, $y1,10,30);
	}
	//para el nombre real
	if (!empty($nombre)){
	$this->SetXY( $x1, $y1 );
	$this->SetFont('Arial','B',12);
	$length = $this->GetStringWidth( $nombre );
	if ($length > 100){
	$this->MultiCell(120, 5, $nombre,0,1);
	$aumento_salto_pagina = 10;	
	}else{
	$this->Cell(120, 4, $nombre,0,0,'C');
	$aumento_salto_pagina  = 4;
	}
	}
	
	//tipo de letra y tamanio para todo lo de mas abajo
	$this->SetFont('Times','B',14);
	//para el nick
	if (!empty($nick)){
	$this->SetXY( $x1, $y1 + $aumento_salto_pagina  );
	$length = $this->GetStringWidth( $nick );
	if ($length > 100){
	$this->MultiCell( 100, 5, $nick,0,1);
	$aumento_salto_pagina = 20;	
	}else{
	$this->Cell(100, 4, $nick,0,0,'C');
	$aumento_salto_pagina  = 14;
	}
	}
	
	$this->SetFont('Times','',12);
	//para el ruc
	if (!empty($ruc)){
	$this->SetXY( $x1, $y1 + $aumento_salto_pagina );
	$length = $this->GetStringWidth( $ruc );
	//Información de la empresa
	$lignes = $this->sizeOfText( $ruc, $length) ;
	$this->Cell(100, 6, $ruc,0,0,'C');
	}
	//para la direccion
	if (!empty($direccion)){
	$this->SetXY( $x1, $y1 + $aumento_salto_pagina  + 6 );
	$length = $this->GetStringWidth( $direccion );
	if ($length > 100){
	$this->MultiCell( 100, 5, $direccion,0,1);
	$aumento_salto_pagina = 30;	
	}else{
	$this->Cell( 100, 4, $direccion,0,0,'C');
	$aumento_salto_pagina  = 20;
	}
	}
	
	//para los telefonos
	if (!empty($telefono)){
	$this->SetXY( $x1, $y1 + $aumento_salto_pagina + 5 );
	$length = $this->GetStringWidth( $telefono );
	//Información de la empresa
	$lignes = $this->sizeOfText( $telefono, $length) ;
	$this->Cell(100, 6, $telefono,0,0,'C');
	}
	//para el mail
	if (!empty($mail)){
	$this->SetXY( $x1, $y1 + $aumento_salto_pagina + 10 );
	$length = $this->GetStringWidth( $mail );
	//Información de la empresa
	$lignes = $this->sizeOfText( $mail, $length) ;
	$this->Cell(100, 6, $mail,0,0,'C');
	}
}

function datos_cliente($nombre,$ruc,$direccion,$telefono,$fechaemision,$guiaremision)
{
	$this->SetLineWidth(0.1);
    $this->SetFillColor(255,255,255);
    $this->RoundedRect(10, 50, 190, 35, 2.5, 'DF');
	
	$x1 = 12; // columna
	$y1 = 52;  // fila
	//posicionamiento hacia abajo
	
	
	//para el nombre del cliente
	if (!empty($nombre)){
	$this->SetXY( $x1, $y1 );
	$this->SetFont('Arial','',14);
	$this->Cell(50, 6, "Cliente: ",0,0,'L');
	$this->SetXY( $x1 + 20, $y1 );
	$this->SetFont('times','BI',14);
	$this->Cell(50, 6, $nombre,0,0,'L');
	}
	
	//para la direccion
	if (!empty($direccion)){
	$this->SetXY( $x1, $y1 + 7 );
	$this->SetFont('Arial','',14);
	$this->Cell(50, 6, "Dirección: ",0,0,'L');
	$this->SetXY( $x1 + 26, $y1 + 7 );
	$this->SetFont('times','BI',14);
	$this->Cell(50, 6, $direccion,0,0,'L');
	}
	
	//para el ruc
	if (!empty($ruc)){
	$this->SetXY( $x1, $y1 + 15 );
	$this->SetFont('Arial','',14);
	$this->Cell(50, 6, "RUC: ",0,0,'L');
	$this->SetXY( $x1 + 15, $y1 + 15 );
	$this->SetFont('times','BI',14);
	$this->Cell(50, 6, $ruc,0,0,'L');
	}
	
	//para la telefono
	if (!empty($telefono)){
	$this->SetXY( $x1 + 100, $y1 + 15 );
	$this->SetFont('Arial','',14);
	$this->Cell(50, 6, "Teléfono: ",0,0,'L');
	$this->SetXY( $x1 + 125, $y1 + 15 );
	$this->SetFont('times','BI',14);
	$this->Cell(50, 6, $telefono,0,0,'L');
	}
	
	//para la fecha de emision
	if (!empty($fechaemision)){
	$this->SetXY( $x1, $y1 + 23 );
	$this->SetFont('Arial','',14);
	$this->Cell(50, 6, "Fecha de emisión: ",0,0,'L');
	$this->SetXY( $x1 + 50, $y1 + 23 );
	$this->SetFont('times','BI',14);
	$this->Cell(50, 6, $fechaemision,0,0,'L');
	}
	
	//para la guia de remision
	if (!empty($guiaremision)){
	$this->SetXY( $x1 + 100, $y1 + 23 );
	$this->SetFont('Arial','',14);
	$this->Cell(50, 6, "Guía de remisión: ",0,0,'L');
	$this->SetXY( $x1 + 145, $y1 + 23 );
	$this->SetFont('times','BI',14);
	$this->Cell(50, 6, $guiaremision,0,0,'L');
	}
	
}

// etiqueta y numero de factura/estimado
function tipo_documento($documento)
{
	if ( !empty($documento)){
    $r1  = $this->w - 60;
    $r2  = $r1 + 50;
    $y1  = 7;
    $y2  = $y1 + 2;
    $mid = ($r1 + $r2 ) / 2;
    
    $tipo_documento  = $documento ;    
    $tamanio_letra = 12;
    $loop   = 0;
    
    while ( $loop == 0 )
    {
       $this->SetFont( "Arial", "B", $tamanio_letra );
       $sz = $this->GetStringWidth($tipo_documento);
       if ( ($r1+$sz) > $r2 )
          $tamanio_letra --;
       else
          $loop ++;
    }

    $this->SetLineWidth(0.1);
    $this->SetFillColor(255,255,255);
    $this->RoundedRect($r1, $y1, ($r2 - $r1), $y2, 2.5, 'DF');
    $this->SetXY( $r1+1, $y1+2);
    $this->Cell($r2-$r1 -1,5, $tipo_documento, 0, 0, "C" );
}
	}

function num_documento($serie, $numero)
{
	if ( !empty($serie) && !empty($numero)){
    $r1  = $this->w - 60;
    $r2  = $r1 + 50;
    $y1  = 16;
    $y2  = $y1 + 2;
    $mid = ($r1 + $r2 ) / 2;
    
    $serie_factura  = $serie ;
	$numero_factura  = $numero ;     
    $tamanio_letra_serie = 12;
	$tamanio_letra_numero = 20;
    $loop   = 0;
	$loop1   = 0;
    
    while ( $loop == 0 )
    {
       $this->SetFont( "times", "B", $tamanio_letra_serie );
       $sz = $this->GetStringWidth($serie_factura);
       if ( ($r1+$sz) > $r2 )
          $tamanio_letra_serie --;
       else
          $loop ++;
    }
	
	$this->SetLineWidth(0.1);
    $this->SetFillColor(255,255,255);
    $this->RoundedRect($r1, $y1, ($r2 - $r1), $y2, 2.5, 'DF');
    $this->SetXY( $r1+1, $y1+2);
    $this->Cell($r2-$r1 -1,5, $serie_factura, 0, 0, "C" );
	
	while ( $loop1 == 0 )
    {
       $this->SetFont( "Arial", "B", $tamanio_letra_numero );
       $sz = $this->GetStringWidth($numero_factura);
       if ( ($r1+$sz) > $r2 )
          $tamanio_letra_numero --;
       else
          $loop1 ++;
    }
	
    $this->SetTextColor(248,3,3);
	$this->SetXY( $r1+1, $y1+10);
	$this->Cell($r2-$r1 -1,5, $numero_factura, 0, 0, "C" );
	$this->SetTextColor(0,0,0);
}
	}
	
function aut_sri($numero_aut)
{
	if ( !empty($numero_aut)){
    $r1  = $this->w - 60;
    $r2  = $r1 + 50;
    $y1  = 34;
    $y2  = $y1 + 1;
    $mid = ($r1 + $r2 ) / 2;
    
    $etiqueta  = "Aut. SRI" ;
	$numero  = $numero_aut ;     
    $tamanio_letra_etiqueta = 12;
	$tamanio_letra_numero = 12;
    $loop   = 0;
	$loop1   = 0;
    
    while ( $loop == 0 )
    {
       $this->SetFont( "times", "B", $tamanio_letra_etiqueta );
       $sz = $this->GetStringWidth($etiqueta);
       if ( ($r1+$sz) > $r2 )
          $tamanio_letra_etiqueta --;
       else
          $loop ++;
    }
	
	$this->SetLineWidth(0.1);
    $this->SetFillColor(255,255,255);
    $this->RoundedRect($r1, $y1, ($r2 - $r1), $y2-23, 2.5, 'DF');
    $this->SetXY( $r1+1, $y1+2);
    $this->Cell($r2-$r1 -1,2, $etiqueta, 0, 0, "C" );
	
	while ( $loop1 == 0 )
    {
       $this->SetFont( "times", "", $tamanio_letra_numero );
       $sz = $this->GetStringWidth($numero);
       if ( ($r1+$sz) > $r2 )
          $tamanio_letra_numero --;
       else
          $loop1 ++;
    }
	
	$this->SetXY( $r1+1, $y1+8);
	$this->Cell($r2-$r1 -1,1, $numero, 0, 0, "C" );
	$this->SetTextColor(0,0,0);
}
	}

function observaciones($observaciones)
{
	$x1 = 10; // columna
	$y1 = 207;  // fila
	$this->Rect( $x1, $y1, 100, 15, "D");

	//imprenta
	if (!empty($observaciones)){
	$this->SetXY($x1+1, $y1+1 );
	$this->SetFont('Arial','',8);
	$this->MultiCell(90, 6, "Observaciones: " .strtoupper ($observaciones),0,1,'L');
	}
}
function valor_letras($letras)
{

	$x1 = 10; // columna
	$y1 = 222;  // fila
	$this->Rect( $x1, $y1, 100, 14, "D");

	//imprenta
	if (!empty($letras)){
	$this->SetXY($x1+0.5, $y1+1);
	$this->SetFont('Arial','',8);
	$this->MultiCell(90, 6, "Son: " . strtoupper($letras) ,0,1,'R',0);
	}
}
function formas_pago($formas_pago)
{
	$x1 = 10; // columna
	$y1 = 236;  // fila
	$this->Rect( $x1, $y1, 100, 14, "D");

	//formas de pago
	if (!empty($formas_pago)){
	$this->SetXY( $x1+0.5, $y1+1 );
	$this->SetFont('Arial','',10);
	$this->MultiCell(90, 6, $formas_pago,0,1,'R',0);
	}
}
function firmas($firmas)
{
	$x1 = 10; // columna
	$y1 = 250;  // fila
	$this->Rect( $x1, $y1, 100, 17, "D");

	//imprenta
	if (!empty($firmas)){
	$this->SetXY($x1, $y1+12);
	$this->SetFont('Arial','',8);
	$this->Cell(90, 6, $firmas ,0,1,'C',0,0);
	}
}
function datos_imprenta($imprenta,$validez,$numeracion)
{
	$x1 = 10; // columna
	$y1 = 267;  // fila
	if (!empty($imprenta)){
	$this->SetXY( $x1, $y1 );
	$this->SetFont('Arial','',8);
	$this->Cell(150, 6, strtoupper ($imprenta). $validez . $numeracion,0,0,'L',0);
	}
}


//para mostrar los datos detalle de la factura
function detalle_factura($con, $ruc_empresa, $serie, $secuencial){
	$r1  = 10; // desde donde empieza desde la izquierda hacia la derecha
    $y1  = 75; //desde que linea empieza para abajo
    $this->SetXY( $r1, $y1 );
	$this->SetFont('Arial','',10);
	$this->Ln(18);
$busca_facturas = mysqli_query($con,"SELECT * FROM cuerpo_factura WHERE ruc_empresa= '$ruc_empresa' and serie_factura='$serie' and secuencial_factura = '$secuencial'" );
//$count = mysqli_num_rows($busca_facturas);

while ($fila=mysqli_fetch_assoc($busca_facturas)){
    
	$codigo_producto = $fila['codigo_producto'];
    $detalle_factura = $fila['detalle_factura'];
    $cantidad_factura=$fila['cantidad_factura'];
	$valor_unitario_factura=$fila['valor_unitario_factura'];
	$descuento=$fila['descuento'];
	$subtotal_factura=$fila['subtotal_factura'];
	
    $this->Cell(20,5,$codigo_producto,0,0,'L',0);
    $this->Cell(80,5,utf8_decode($detalle_factura),0,0,'L',1);
	$this->Cell(15,5,$cantidad_factura,0,0,'R',0);
	$this->Cell(25,5,$valor_unitario_factura,0,0,'R',0);
	$this->Cell(20,5,$descuento,0,0,'R',0);
	$this->Cell(30,5,$subtotal_factura,0,0,'R',0);
	$this->Ln(5);
}
$this->Ln(-5);
	}

function estado_factura( $estado )
{
	if($estado == "ANULADA"){
	$this->SetFont('Arial','B',80);
	$this->SetTextColor(203,203,203);
	$this->Rotate(45,55,190);
	$this->Text(40,190,$estado);
	$this->Rotate(0);
	$this->SetTextColor(0,0,0);
	}
}
//para hacer los totales

// private variables
var $columnas;
var $format;
var $angle=0;

function agrega_titulos_detalle( $tab )
{
    global $columnas;
    $r1  = 10; // desde donde empieza desde la izquierda hacia la derecha
    $r2  = $this->w - ($r1 * 2) ;
    $y1  = 86; //desde que linea empieza para abajo
    $y2  = $this->h - 90 - $y1; // EL VALOR ES CUANTO LE TENGO QUE QUITAR PARA QUE EL CUADRO BAJE HASTA LA POSICION QUE QUIERO
    $this->SetXY( $r1, $y1 );
    $this->Rect( $r1, $y1, $r2, $y2, "D"); // RECTANGULO DE TODO EL DETALLE DE LA FACTURA
    $this->Line( $r1, $y1+6, $r1+$r2, $y1+6); // LINEA PARA DIVIDIR LOS ENCABEZADOS DEL DETALLE DEL DETALLE DE LA FACTURA, CODIGO, DETALLE...
    $colX = $r1;
    $columnas = $tab;
    while ( list( $lib, $pos ) = each ($tab) )
    {
        $this->SetXY( $colX, $y1+2 );
        $this->Cell( $pos, 1, $lib, 0, 0, "C");
        $colX += $pos;
        $this->Line( $colX, $y1, $colX, $y1+$y2);
    }
}

function agregaSubtotales($con, $ruc_empresa, $serie, $numero_factura ){
global $columnas;
    $r1  = 110; // desde donde empieza desde la izquierda hacia la derecha
    $r2  = 90; //$this->w - ($r1-20) ;
    $y1  = 207;
	$y2  = 60;
	$this->Rect( $r1, $y1, $r2, $y2, "D");
	$this->SetFont('Arial','',10);
	$this->SetXY( $r1, $y1-10); // desde aqui empieza los items
	
	//DETALLE SUBTOTALES Y VALORES
	$subtotal_general=0;
	$total_descuento=0;
	$sql=mysqli_query($con, "select * from cuerpo_factura where ruc_empresa = '$ruc_empresa' and serie_factura = '$serie' and secuencial_factura='$numero_factura' ");
	$this->Ln(5);
	while ($row=mysqli_fetch_array($sql)){
	$cantidad=$row['cantidad_factura'];
	$precio_venta=number_format($row['valor_unitario_factura'],4,'.','');
	$descuento=number_format($row['descuento'],2,'.','');
	$subtotal=number_format($cantidad*$precio_venta - $descuento ,2,'.','');
    $subtotal_general+=number_format($cantidad * $precio_venta - $descuento,2,'.','');//Sumador subtotal general
	$total_descuento+=number_format($descuento,2,'.','');//Sumador total descuento
	}
	$this->Ln(5);
	$this->Cell($r1+60, 5, "SUBTOTAL : " ,0,0,'R',0);
	$this->Cell(20, 5, number_format($subtotal_general,2,'.','') ,0,0,'R',0);
	//$this->Ln(-5);
//PARA MOSTRAR LOS NOMBRES DE CADA TARIFA DE IVA Y LOS VALORES DE CADA SUBTOTAL
	$subtotal_tarifa_iva=0;
	$muestra_tarifas=mysqli_query($con, "select ti.tarifa as tarifa, sum(cf.cantidad_factura * cf.valor_unitario_factura - descuento) as precio from cuerpo_factura cf, tarifa_iva ti where ti.codigo = cf.tarifa_iva and cf.ruc_empresa = '$ruc_empresa' and serie_factura = '$serie' and secuencial_factura='$numero_factura'  group by cf.tarifa_iva " );
	$this->Ln(5);

	while ($row=mysqli_fetch_assoc($muestra_tarifas)){
	$nombre_tarifa_iva=strtoupper($row["tarifa"]);
	$precio_tarifa_iva=$row['precio'];
	$subtotal_tarifa_iva= number_format($precio_tarifa_iva,2,'.','')  ;

	$this->Cell($r1+60, 5, "SUBTOTAL ".$nombre_tarifa_iva .": " ,0,0,'R',0);
	$this->Cell(20, 5, $subtotal_tarifa_iva ,0,0,'R',0);
	$this->Ln(5);
	}
	$this->Cell($r1+60, 5, "DESCUENTO : " ,0,0,'R',0);
	$this->Cell(20, 5, number_format($total_descuento,2,'.','') ,0,0,'R',0);
	//$this->Ln(-5);
//PARA MOSTRAR LOS IVAS
    $total_iva = 0;
	$subtotal_porcentaje_iva=0;
	$sql=mysqli_query($con, "select ti.tarifa as tarifa, (sum(cf.cantidad_factura * cf.valor_unitario_factura - descuento) * ti.tarifa /100)  as porcentaje from cuerpo_factura cf, tarifa_iva ti where ti.codigo = cf.tarifa_iva and cf.ruc_empresa = '$ruc_empresa' and serie_factura = '$serie' and secuencial_factura='$numero_factura' and ti.tarifa > 0 group by cf.tarifa_iva " );
	$this->Ln(5);
	while ($row=mysqli_fetch_array($sql)){
	$nombre_porcentaje_iva=strtoupper($row["tarifa"]);
	$porcentaje_iva=$row['porcentaje'];
	$subtotal_porcentaje_iva= $porcentaje_iva ;
	$total_iva+=$subtotal_porcentaje_iva;
	$this->Cell($r1+60, 5, "IVA ". $nombre_porcentaje_iva .": " ,0,0,'R',0);
	$this->Cell(20, 5,number_format($subtotal_porcentaje_iva,2,'.','') ,0,0,'R',0);
	$this->Ln(5);
	}	
	$this->Cell($r1+60, 5, "TOTAL : " ,0,0,'R',0);
	$this->Cell(20, 5, number_format($subtotal_general + $total_iva ,2,'.','') ,0,0,'R',0);
	$this->Ln(-5);
}

// me permite hacer los rectangulos
function RoundedRect($x, $y, $w, $h, $r, $style = '')
{
	$k = $this->k;
	$hp = $this->h;
	if($style=='F')
		$op='f';
	elseif($style=='FD' || $style=='DF')
		$op='B';
	else
		$op='S';
	$MyArc = 4/3 * (sqrt(2) - 1);
	$this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));
	$xc = $x+$w-$r ;
	$yc = $y+$r;
	$this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));

	$this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
	$xc = $x+$w-$r ;
	$yc = $y+$h-$r;
	$this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
	$this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
	$xc = $x+$r ;
	$yc = $y+$h-$r;
	$this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
	$this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
	$xc = $x+$r ;
	$yc = $y+$r;
	$this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
	$this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
	$this->_out($op);
}

function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
{
	$h = $this->h;
	$this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
						$x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
}

function Rotate($angle, $x=-1, $y=-1)
{
	if($x==-1)
		$x=$this->x;
	if($y==-1)
		$y=$this->y;
	if($this->angle!=0)
		$this->_out('Q');
	$this->angle=$angle;
	if($angle!=0)
	{
		$angle*=M_PI/180;
		$c=cos($angle);
		$s=sin($angle);
		$cx=$x*$this->k;
		$cy=($this->h-$y)*$this->k;
		$this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',$c,$s,-$s,$c,$cx,$cy,-$cx,-$cy));
	}
}


// public functions
function sizeOfText( $texte, $largeur )
{
	$index    = 0;
	$nb_lines = 0;
	$loop     = TRUE;
	while ( $loop )
	{
		$pos = strpos($texte, "\n");
		if (!$pos)
		{
			$loop  = FALSE;
			$ligne = $texte;
		}
		else
		{
			$ligne  = substr( $texte, $index, $pos);
			$texte = substr( $texte, $pos+1 );
		}
		$length = floor( $this->GetStringWidth( $ligne ) );
		$res = 1 + floor( $length / $largeur) ;
		$nb_lines += $res;
	}
	return $nb_lines;
}


}
?>
