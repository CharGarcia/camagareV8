<?php
include("../conexiones/conectalogin.php");
include('../validadores/numero_letras.php');
require('../pdf/funciones_pdf.php');
ini_set('date.timezone', 'America/Guayaquil');
$con = conenta_login();
session_start();

$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$id_cotizacion = base64_decode($_GET['id']);
$encabezado_recibo = mysqli_query($con, "SELECT ec.id as id, ec.numero as numero, cl.nombre as cliente,
	ec.version as version, ec.contacto as contacto, ven.nombre as ejecutivo,
	ec.proyecto as proyecto, ec.status as status, ec.fecha as fecha, 
	ec.observaciones as observaciones, ec.presupuesto as presupuesto FROM encabezado_cotizacion_publicidad as ec 
	INNER JOIN clientes as cl ON cl.id=ec.id_cliente
	INNER JOIN vendedores as ven ON ven.id_vendedor=ec.ejecutivo WHERE ec.id = '" . $id_cotizacion . "' ");
$row_encabezado = mysqli_fetch_array($encabezado_recibo);
$cliente = $row_encabezado['cliente'];
$contacto = ucwords($row_encabezado['contacto']);
$ejecutivo = ucwords($row_encabezado['ejecutivo']);
$proyecto = strtoupper($row_encabezado['proyecto']);
$presupuesto = strtoupper($row_encabezado['presupuesto']);
$version = "V" . $row_encabezado['version'];
$observaciones = ucwords($row_encabezado['observaciones']);
$fecha = date('d-m-Y', strtotime($row_encabezado['fecha']));
$numero = "DBS-" . str_pad($row_encabezado['numero'], 3, '0', STR_PAD_LEFT) . "-" . date("Y", strtotime($row_encabezado['fecha']));

//para buscar la imagen
$logo = "../logos_empresas/logobecerra.jpg";
$piepagina = "../logos_empresas/piepaginabecerra.jpg";

$html_encabezado = '<h4></h4><p align="center">' . utf8_decode('COTIZACIÓN DE SERVICIOS') . '</p><h4><br>';

if ($action == "general") {
    $pdf = new funciones_pdf('P', 'mm'); //P-L
    $pdf->AliasNbPages();
    $pdf->AddPage(); //es importante agregar esta linea para saber la pagina inicial
    $pdf->SetFont('Arial', 'B', 20); //esta tambien es importante
    $pdf->SetX(20);
    $pdf->SetDrawColor(0, 0, 0); // Color de línea (Negro)
    $pdf->SetLineWidth(0.3); // Grosor de la línea
    $pdf->detalle_html($html_encabezado);

    dibujarDosLineas($pdf);
    $pdf->Ln();
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(13, 71, 161); //color azul
    $pdf->SetTextColor(255, 255, 255); //color blanco
    $pdf->Cell(120, 6, 'DATOS CLIENTE', 1, 0, 'L', true);
    $y_position = $pdf->GetY();

    $pdf->Cell(35, 6, 'PROYECTO', 1, 1, 'C', true);
    $pdf->Image($logo, 170, $y_position, 30, 30, 'jpg', '');

    $pdf->SetFont('Arial', 'B', 9);
    //datos cliente PROYECTO Y LOGO

    $pdf->SetFillColor(255, 255, 255); //sin color relleno
    $pdf->SetTextColor(33, 33, 33); //color negro
    $pdf->Cell(120, 5, 'Empresa: ' . utf8_decode($cliente), 1, 0, 'L', true);

    $pdf->SetX(130); //mueve el punto inicial al final de la fila de empresa
    $pdf->MultiCell(35, 5, utf8_decode($proyecto), 0, 'C', false);

    //linea vertical
    $pdf->Line(165, 30, 165, 45);

    $pdf->SetY(36);

    $pdf->Cell(120, 5, 'Contacto: ' . utf8_decode($contacto), 1, 1, 'L', true); //la linea llega hasta 121 y baja

    $pdf->Cell(120, 5, 'Ejecutivo: ' . utf8_decode($ejecutivo), 1, 0, 'L', true);
    $pdf->SetFillColor(13, 71, 161); //color azul
    $pdf->SetTextColor(255, 255, 255); //color blanco
    $pdf->Cell(35, 5, utf8_decode('Cotización No. '), 1, 1, 'C', true);
    $pdf->SetFillColor(255, 255, 255); //sin color relleno
    $pdf->SetTextColor(33, 33, 33); //color negro
    $pdf->Cell(120, 5, 'Fecha: ' . utf8_decode($fecha) . utf8_decode(' Versión cotización: ') . $version, 1, 0, 'L', true);
    $pdf->Cell(35, 5, $numero, 1, 1, 'C', true);
    $pdf->SetFillColor(255, 255, 255); //sin color relleno
    $pdf->SetTextColor(33, 33, 33); //color negro
    $pdf->Ln();
    dibujarDosLineas($pdf);
    $pdf->Ln();

    $pdf->Cell(190, 5, 'Presupuesto: ' . utf8_decode($presupuesto), 0, 1, 'L', true);

    $encabezado_detalle = 1;
    $sql_categorias = mysqli_query($con, "SELECT distinct cue.id_tipo as id_tipo, gru.nombre_grupo as nombre_grupo FROM cuerpo_cotizacion_publicidad as cue 
    INNER JOIN grupo_familiar_producto as gru ON gru.id_grupo=cue.id_tipo WHERE cue.id_encabezado_cotizacion = '" . $id_cotizacion . "' order by gru.nombre_grupo asc");
    while ($p = mysqli_fetch_assoc($sql_categorias)) {
        $id_tipo = $p['id_tipo'];
        $pdf->SetWidths(array(90));
        //$pdf->Row_tabla(array(utf8_decode($p['nombre_grupo'])), true, [13, 71, 161], [255, 255, 255]);
        $pdf->SetFillColor(13, 71, 161); //color azul
        $pdf->SetTextColor(255, 255, 255); //color blanco
        $pdf->Cell(90, 5, utf8_decode($p['nombre_grupo']), 1, 0, 'L', true);

        $pdf->SetX(100);
        if ($encabezado_detalle > 1) {
            //encabezados del detalle
            $pdf->SetFillColor(255, 255, 255); //color blanco
            $pdf->SetTextColor(255, 255, 255); //color blanco
            $pdf->Cell(20, 5, '', 1, 0, 'C', true);
            $pdf->Cell(20, 5, '', 1, 0, 'C', true);
            $pdf->Cell(20, 5, '', 1, 0, 'C', true);
            $pdf->Cell(20, 5, '', 1, 0, 'C', true);
            $pdf->Cell(20, 5, '', 1, 1, 'C', true);
        } else {
            $pdf->SetFillColor(13, 71, 161); //color azul
            $pdf->SetTextColor(255, 255, 255); //color blanco
            $pdf->Cell(20, 5, utf8_decode('Precio /día'), 1, 0, 'C', true);
            $pdf->Cell(20, 5, 'Ciudades', 1, 0, 'C', true);
            $pdf->Cell(20, 5, utf8_decode('Días'), 1, 0, 'C', true);
            $pdf->Cell(20, 5, 'Cantidad', 1, 0, 'C', true);
            $pdf->Cell(20, 5, 'A facturar', 1, 1, 'C', true);
        }

        //para mostrar los detalles de la cotizacion
        $detalle_cotizacion = mysqli_query($con, "SELECT * FROM cuerpo_cotizacion_publicidad as cue INNER JOIN 
encabezado_cotizacion_publicidad as enc ON enc.id=cue.id_encabezado_cotizacion 
INNER JOIN tarifa_iva as tar ON tar.id=enc.tipo_iva WHERE cue.id_encabezado_cotizacion = '" . $id_cotizacion . "' and cue.id_tipo ='" . $id_tipo . "'");
        while ($row_detalle = mysqli_fetch_assoc($detalle_cotizacion)) {
            $pdf->SetWidths(array(90, 20, 20, 20, 20, 20));
            $pdf->Row_tabla(array(
                utf8_decode(ucwords($row_detalle['descripcion'])),
                $row_detalle['precio'],
                $row_detalle['ciudades'],
                $row_detalle['dias'],
                $row_detalle['cantidad'],
                number_format($row_detalle['precio'] * $row_detalle['cantidad'], 2, '.', '')
            ));
        }
        $encabezado_detalle++;
    }
    //calcular subtotales

    $subtotal_cotizacion = 0;
    $suma_subtotal = 0;
    $total_iva = 0;
    $suma_iva = 0;
    $comision = 0;
    $suma_comision = 0;
    $suma_costo = 0;
    $valor_costo = 0;
    $total_final = 0;
    $subtotalYcomision = 0;
    $sumasubtotalYcomision = 0;
    $info_detalle_valores = mysqli_query($con, "SELECT * FROM cuerpo_cotizacion_publicidad as cue INNER JOIN 
	encabezado_cotizacion_publicidad as enc ON enc.id=cue.id_encabezado_cotizacion 
	INNER JOIN tarifa_iva as tar ON tar.id=enc.tipo_iva WHERE cue.id_encabezado_cotizacion = '" . $id_cotizacion . "' ");
    while ($detalle_cotizacion = mysqli_fetch_assoc($info_detalle_valores)) {
        $subtotal_cotizacion =  ($detalle_cotizacion['precio'] * $detalle_cotizacion['cantidad']);
        $comision = $subtotal_cotizacion * ($detalle_cotizacion['comision'] / 100);
        $subtotalYcomision = $subtotal_cotizacion + $comision;
        $sumasubtotalYcomision += $subtotalYcomision;
        $suma_comision += $comision;
        $valor_costo = $detalle_cotizacion['valor_costo'];
        $suma_costo += $valor_costo;
        $porcentaje_iva = $detalle_cotizacion['porcentaje_iva'] / 100;
        $total_iva =  ($subtotalYcomision) * $porcentaje_iva;
        $suma_iva += $total_iva;
        $suma_subtotal += $subtotal_cotizacion;
        $total_final += $subtotalYcomision + $total_iva;
    }


    // Observaciones con MultiCell
    $y_actual = $pdf->GetY(); // Guardamos la posición Y actual
    $pdf->MultiCell(130, 5, 'Observaciones: ' . utf8_decode($observaciones), 1, 'L', true);

    // Establecer colores para los subtotales
    $pdf->SetFillColor(13, 71, 161); // Azul
    $pdf->SetTextColor(255, 255, 255); // Blanco
    // Posicionar la celda de 'Subtotal' en la misma línea
    $pdf->SetXY(140, $y_actual); // Mover a la posición correcta (después de la MultiCell)
    $pdf->Cell(40, 5, 'Subtotal: ', 1, 0, 'R', true);
    $pdf->Cell(20, 5, number_format($suma_subtotal, 2, '.', ''), 1, 1, 'R', true);

    // Colocar los demás valores en la siguiente línea
    $pdf->SetX(140); // Mantener alineación para los valores de la derecha
    $pdf->Cell(40, 5, utf8_decode('Comisión de agencia: '), 1, 0, 'R', true);
    $pdf->Cell(20, 5, number_format($suma_comision, 2, '.', ''), 1, 1, 'R', true);

    $pdf->SetX(140);
    $pdf->Cell(40, 5, 'IVA: ', 1, 0, 'R', true);
    $pdf->Cell(20, 5, number_format($suma_iva, 2, '.', ''), 1, 1, 'R', true);

    $pdf->SetX(140);
    $pdf->Cell(40, 5, 'Total: ', 1, 0, 'R', true);
    $pdf->Cell(20, 5, number_format($total_final, 2, '.', ''), 1, 1, 'R', true);

    $pdf->Ln();
    $pdf->Ln();
    $y_position = $pdf->GetY();
    $pdf->Image($piepagina, 10, $y_position, 190, 50, 'jpg', '');

    $pdf->Output($numero . ".pdf", "D");


    //$pdf->Image('../docs_temp/' . $ruc_empresa . '.jpg', 20, 20, 30, 30, 'jpg', '')
}


function dibujarDosLineas($pdf)
{
    $y = $pdf->GetY(); // Obtener la posición actual
    $pdf->Line(10, $y, 200, $y); // Primera línea
    $pdf->Line(10, $y + 1, 200, $y + 1); // Segunda línea pegada (ajustada 1.5 px abajo)
}
