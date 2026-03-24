<?php
require('../pdf/fpdf.php');
class pdf_egreso_ingreso extends FPDF {
	
function datos_empresa($nombre_comercial_empresa,$ruc_empresa){
	$x1 = 40; // hacia la derecha
	$y1 = 10;  // hacia abajo
	//posicionamiento hacia abajo
	
	//para el logo, 25 es el tamanio del logo
	if (!empty($logo)){
	$this->Image($logo, $y1,10,10);
	}
	
	//para el nombre comercial
	if (!empty($nombre_comercial_empresa)){
	
	$this->SetFont('Arial','B',12);
	$length = $this->GetStringWidth( $nombre_comercial_empresa );
	$empieza_x=$length/2;
	
	$this->SetXY( $x1+45-$empieza_x, $y1 );
	if ($length > 115){//numero de caracteres de largo
	$this->MultiCell(100, 5, $nombre_comercial_empresa,0,'C',false);
	$y1 = 10;	
	}else{
	$this->Cell(100, 5, $nombre_comercial_empresa,0,'C',false);
	$y1 = 10;
	}
	}

	//para el ruc
	if (!empty($ruc_empresa)){
	$this->SetXY( $x1+36, $y1+10 );
	$length = $this->GetStringWidth( $ruc_empresa );
	$lignes = $this->sizeOfText( $ruc_empresa, $length) ;
	$this->Cell(100, 5, "RUC: ".$ruc_empresa,0,'C',false);
	}
}

// nombre del documento
function tipo_documento($documento){
	if ( !empty($documento)){
		$r1  = $this->w -70;//distancia del nombre comprobante egreso desde la parte derecha
		$r2  = $r1 + 60;
		$y1  = 6;
		$y2  = $y1 + 2;
		$mid = ($r1 + $r2 ) / 2;
		
		$tipo_documento  = $documento ;    
		$tamanio_letra = 10;
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
		$this->SetXY( $r1+2, $y1+2);
		$this->Cell($r2-$r1 -1,5, $tipo_documento, 0, 0, "C" );
	}
	}
	
function numero_documento($numero){
	if (!empty($numero)){
    $r1  = $this->w - 60;//distancia del nombre comprobante egreso desde la parte derecha
    $r2  = $r1 + 40;
    $y1  = 15; //distancia desde la parte superior hacia abajo
    $y2  = $y1-6;// distancia desde arriba hacia abajo la parte de abajo del cuadro 
    $mid = ($r1 + $r2 ) / 2;
    $loop   = 0;   
    while ( $loop == 0 )
    {
       $this->SetFont( "times", "B", 20 );
       $sz = $this->GetStringWidth($numero);
       if ( ($r1+$sz) > $r2 )
          $tamanio_letra_serie --;
       else
          $loop ++;
    }
	
	$this->SetLineWidth(0.1);
    $this->SetFillColor(255,255,255);
    $this->RoundedRect($r1, $y1, ($r2 - $r1), $y2, 2.5, 'DF');
    $this->SetXY( $r1+1, $y1+2);
    $this->Cell($r2-$r1 -1,5, $numero, 0, 0, "C" );

	}
	
}

function datos_encabezado($fecha_emision, $beneficiario_cliente, $tipo_documento, $valor, $detalle){
	$x1 = 20; // hacia la derecha
	$y1 = 40;  // hacia abajo
	//posicionamiento hacia abajo
	$this->Line( $x1-10, $y1-5, $x1+180, $y1-5);
	if ($tipo_documento=="egreso"){
	$emitido_recibido="Pagado a: ";
	}else{
	$emitido_recibido="Recibido de: ";	
	}
	
	$this->SetFont('Arial','',12);

	if (!empty($fecha_emision)){
	$this->SetXY( $x1, $y1 );
	$this->Cell(20, 5, "Fecha de emisión: ".$fecha_emision,0,'L',false);
	}
	
	if (!empty($beneficiario_cliente)){
	$this->SetXY( $x1, $y1+5 );
	$this->Cell(100, 5, $emitido_recibido.$beneficiario_cliente,0,'L',false);
	}
	
	if (!empty($detalle)){
	$this->SetXY( $x1, $y1+10 );
	$this->Cell(100, 5, "Detalle: ".$detalle,0,'L',false);
	}
	
	if (!empty($valor)){
	$this->SetXY( $x1+120, $y1 );
	$this->Cell(100, 5, "Total ".$tipo_documento." :$ ".$valor,0,'L',false);
	}

	$this->Line( $x1-10, $y1+20, $x1+180, $y1+20);
	$this->SetXY( $x1-5, $y1+21 );
	$this->Cell(100, 5, "Detalle del ".$tipo_documento,0,'L',false);
}

//agregar titulos al documento
function agrega_titulos_detalle( $tab ){
    global $columnas;
	$this->SetFont( "times", "B", 10 );
    $r1  = 10; // desde donde empieza desde la izquierda hacia la derecha
    $r2  = $this->w - ($r1 * 2) ;//ancho
    $y1  = 67;
    $y2  = $this->h - 90 - $y1; // altura, EL VALOR ES CUANTO LE TENGO QUE QUITAR PARA QUE EL CUADRO BAJE HASTA LA POSICION QUE QUIERO
    $this->SetXY( $r1, $y1 );
    //$this->Rect( $r1, $y1, $r2, $y2, "D"); // RECTANGULO DE TODO 
    $this->Line( $r1, $y1+5, $r1+$r2, $y1+5); // LINEA PARA DIVIDIR LOS ENCABEZADOS DEL DETALLE DEL DETALLE 
    $colX = $r1;
    $columnas = $tab;
    while ( list( $lib, $pos ) = each ($tab) )
    {
        $this->SetXY( $colX, $y1+2 );
        $this->Cell( $pos, 1, $lib, 0, 0, "C");
        $colX += $pos;
        //$this->Line( $colX, $y1, $colX, $y1+$y2);// lineas verticales
    }
}

function estado_documento( $estado )
{
	if($estado == "ANULADO"){
	$this->SetFont('Arial','B',80);
	$this->SetTextColor(203,203,203);
	$this->Rotate(45,55,190);
	$this->Text(40,190,$estado);
	$this->Rotate(0);
	$this->SetTextColor(0,0,0);
	}
}

//para mostrar los datos detalle de del ingreso o egreso
function detalle_documento($con, $ruc_empresa, $codigo_documento, $tipo_documento){
	$r1  = 10; // desde donde empieza desde la izquierda hacia la derecha
    $y1  = 55; //desde que linea empieza para abajo
    $this->SetXY( $r1, $y1 );
	$this->SetFont('Arial','',8);
	$this->Ln(18);
$busca_detalle_egreso_ingreso = mysqli_query($con,"SELECT * FROM detalle_ingresos_egresos WHERE codigo_documento= '".$codigo_documento."' and ruc_empresa='".$ruc_empresa."' and tipo_documento='".$tipo_documento."' " );
while ($fila=mysqli_fetch_array($busca_detalle_egreso_ingreso)){
	$beneficiario_cliente = $fila['beneficiario_cliente'];
    $detalle_ing_egr = $fila['detalle_ing_egr'];
    $tipo_ing_egr=$fila['tipo_ing_egr'];
	$valor_ing_egr=$fila['valor_ing_egr'];
		
    $this->Cell(90,5,$beneficiario_cliente,0,0,'L',0);
    $this->Cell(72,5,utf8_decode($detalle_ing_egr),0,0,'L',1);
	$this->Cell(10,5,$tipo_ing_egr,0,0,'R',0);
	$this->Cell(12,5,$valor_ing_egr,0,0,'R',0);
	$this->Ln(5);
}
$this->Ln(-5);
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




//para hacer los totales

// private variables
var $columnas;
var $format;
var $angle=0;



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
