<?php
	include('../validadores/numero_letras.php');
	require('../pdf/funciones_factura.php');
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	
	if (isset($_GET['id_factura_imprimir'])){
	$id_factura = $_GET['id_factura_imprimir'];
	$busca_facturas = mysqli_query($con,"SELECT * FROM encabezado_factura WHERE id_encabezado_factura= $id_factura" );
	}
//para imprimir la factura directo desde el facturador
	if (isset($_POST['serie_factura_imprimir']) && isset($_POST['secuencial_factura_imprimir'])){
			$serie_factura = $_POST['serie_factura_imprimir'];
			$secuencial_factura = $_POST['secuencial_factura_imprimir'];
	$busca_existentes = mysqli_query($con,"SELECT * FROM encabezado_factura WHERE serie_factura = '$serie_factura' and secuencial_factura= $secuencial_factura and ruc_empresa = '$ruc_empresa'" );
	$count = mysqli_num_rows($busca_existentes);
		 if ($count == 1){
			$busca_facturas = mysqli_query($con,"SELECT * FROM encabezado_factura WHERE serie_factura = '$serie_factura' and secuencial_factura= $secuencial_factura and ruc_empresa = '$ruc_empresa'" );
			}else{
			echo "<script>alert('No se encontró la factura en la base de datos.')</script>";
			echo "<script>window.close();</script>";
			exit;
			}
}
	
	$datos=mysqli_fetch_assoc($busca_facturas);	
	$serie = $datos['serie_factura'];
	$numero_factura = $datos['secuencial_factura'];
    $secuencial = str_pad($datos['secuencial_factura'],9,"000000000",STR_PAD_LEFT);
	$cliente = $datos['nombre_cliente_factura'];
	$ruc = $datos['ruc_cliente_factura'];
	$direccion = $datos['direccion_cliente_factura'];
	$fecha = date("d/m/Y", strtotime($datos['fecha_factura']));
	$guia = $datos['guia_remision'];
	$estado = $datos['estado_factura'];
	$observaciones = $datos['observaciones_factura'];
	$total = $datos['total_factura'];
	
	//datos del cliente
	$busca_telefono = mysqli_query($con,"SELECT * FROM clientes WHERE ruc= '$ruc' and nombre = '$cliente'" );
	$datos_cliente=mysqli_fetch_assoc($busca_telefono);
	$telefono = $datos_cliente['telefono'];
	
	
	
	// datos de la autorizacion
	$busca_autorizacion_sri = mysqli_query($con,"SELECT autsri.imprenta as imprenta, autsri.autorizacion_sri as autorizacion, autsri.emision_autorizacion as emision_autorizacion,
	autsri.vence_autorizacion as vence_autorizacion, autsri.del_autorizacion as del_autorizacion, autsri.al_autorizacion as al_autorizacion
	FROM autorizaciones_sri as autsri, sucursales as sucur WHERE autsri.codigo_documento= '01' and autsri.id_serie = sucur.id_sucursal and sucur.serie = '$serie' and autsri.ruc_empresa = '$ruc_empresa' and '$secuencial' between autsri.del_autorizacion and autsri.al_autorizacion " );
	$datos_autorizacion=mysqli_fetch_assoc($busca_autorizacion_sri);
	$pie_imprenta = $datos_autorizacion['imprenta'];
	$autorizacion_sri = $datos_autorizacion['autorizacion'];
	
	
	$validez = " Fecha Aut: ".date("d/m/Y", strtotime($datos_autorizacion['emision_autorizacion'])) . " Caducidad: ". date("d/m/Y", strtotime($datos_autorizacion['vence_autorizacion']));
	$numeracion = " Del " . $datos_autorizacion['del_autorizacion'] ." Al ". $datos_autorizacion['al_autorizacion'];

	
	//datos de la empresa
	$busca_empresa = mysqli_query($con,"SELECT * FROM empresas WHERE ruc = '$ruc_empresa' ");
	$datos_empresa=mysqli_fetch_assoc($busca_empresa);
	$nombre_empresa = $datos_empresa['nombre'];
	$nombre_comercial_empresa = $datos_empresa['nombre_comercial'];
	$direccion_empresa = $datos_empresa['direccion'];
	$telefono_empresa = $datos_empresa['telefono'];
	$mail_empresa = $datos_empresa['mail'];
	$logo_empresa = $datos_empresa['logo'];
	
	// tipo documento
	$factura="FACTURA";

	$letras = num_letras($total);
	
//detalle de funciones	
	
$pdf = new PDF_FACTURA( 'P', 'mm', 'A4' );
$pdf->AddPage();
$pdf->datos_Empresa($logo_empresa, $nombre_comercial_empresa,$nombre_empresa, $ruc_empresa,$direccion_empresa,$telefono_empresa,$mail_empresa);  
$pdf->tipo_documento($factura);
$pdf->num_documento($serie, $secuencial );
$pdf->aut_sri($autorizacion_sri);
$pdf->datos_cliente($cliente,$ruc,$direccion,$telefono,$fecha,$guia);
$pdf->estado_factura($estado);
$pdf->detalle_factura($con, $ruc_empresa, $serie, $secuencial);
$titulos_detalle=array( "CÓDIGO"   => 20, "DETALLE"  => 80, "CANT" => 20, "VAL/UNI"  => 20, "DESC" => 20, "SUBTOTAL" => 30);
$pdf->agrega_titulos_detalle($titulos_detalle);
$pdf->agregaSubtotales($con, $ruc_empresa, $serie, $numero_factura );
$pdf->observaciones($observaciones);
$pdf->valor_letras($letras);
$pdf->formas_pago("Formas de pago: Efectivo [ ] dinero electrónico [ ] tarjeta de crédito [ ] otros [ ]");
$pdf->firmas("Firma autorizada                   Cliente");
$pdf->datos_imprenta( $pie_imprenta,$validez,$numeracion);

if (isset($_GET['mail'])){
include("../validadores/codigo_aleatorio.php");
$codigo_aleatorio = numero_aleatorio();
$pdf->Output("../documentos_mail/".$codigo_aleatorio.".pdf","F");

if (empty($_GET['id_factura_imprimir'])) {
           $errors[] = "ID factura no encontrado";
		} elseif (!filter_var($_GET['mail_cliente'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "La dirección de correo electrónico no está en un formato de correo electrónico válida";
        }  else if (!empty($_GET['id_factura_imprimir']) && !empty($_GET['mail_cliente'])){

		// escaping, additionally removing everything that could be (html/javascript-) code
		$id_factura=mysqli_real_escape_string($con,(strip_tags($_GET["id_factura_imprimir"],ENT_QUOTES)));
		$mail_cliente=mysqli_real_escape_string($con,(strip_tags($_GET["mail_cliente"],ENT_QUOTES)));
		
		 //AQUI VA EL CODIGO PARA ENVIAR EL MAIL
		   $message = "El proveedor: " . $nombre_empresa ." le ha enviado una factura."; 
		   $subject = "Nueva Factura de: ". $nombre_comercial_empresa;

			$email_to = $mail_cliente;
			$email_from = 'info@camagare.com';
			$separator = md5(time());
			$eol = PHP_EOL;
			$filename = "../documentos_mail/".$codigo_aleatorio.".pdf";
			$pdfdoc = file_get_contents($filename);
			$attachment = chunk_split(base64_encode($pdfdoc));
			$headers  = "From: \"CaMaGaRe\"<" . $email_from . ">".$from.$eol;
			$headers .= "MIME-Version: 1.0".$eol; 
			$headers .= "Content-Type: multipart/mixed; boundary=\"".$separator."\"";
			$body = "--".$separator.$eol;
			$body .= "Content-Type: text/html; charset=\"utf-8\"".$eol;
			$body .= "Content-Transfer-Encoding: 8bit".$eol.$eol;
			$body .= $message.$eol;

			// adjunto
			$body .= "--".$separator.$eol;
			$body .= "Content-Type: application/octet-stream; name=\"".$filename."\"".$eol;
			$body .= "Content-Transfer-Encoding: base64".$eol;
			$body .= "Content-Disposition: attachment".$eol.$eol;
			$body .= $attachment.$eol;
			$body .= "--".$separator."--";

			$error_ocurred = mail($email_to, $subject, $body, $headers);
    	
		if($error_ocurred){
		$messages[] = "Documento enviado correctamente.";

		}else{
		$errors []= "No se pudo enviar la factura por correo, intente otra vez.";
		}
	
    	}else{
		$errors []= "Error desconocido.";
	   }
	   if (isset($errors)){
			
			?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Error!</strong> 
					<?php
						foreach ($errors as $error) {
								echo utf8_encode ($error);
							}
						?>
			</div>
			<?php
			}
			if (isset($messages)){
				
				?>
				<div class="alert alert-success" role="alert">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						<strong>Bien hecho!</strong>
						<?php
							foreach ($messages as $message) {
									echo utf8_encode ($message);
								}
							?>
				</div>
				<?php
			}
unlink("../documentos_mail/".$codigo_aleatorio.".pdf");
}else{
$pdf->Output("factura ".$serie."-". $numero_factura .".pdf","I");
}
?>
