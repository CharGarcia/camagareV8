<?php
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
if ($action="generar_qr" && isset($_GET['link_qr'])){
	$link_qr=$_GET['link_qr'];
	$qr=$_GET['qr'];
	//unlink ('temp/'.$qr.'.png');
	//Agregamos la libreria para genera códigos QR
	require "../qr/phpqrcode/qrlib.php";    
	//Declaramos una carpeta temporal para guardar la imagenes generadas
	$dir = 'temp/';
	
	//Si no existe la carpeta la creamos
	if (!file_exists($dir))
        mkdir($dir);
	
        //Declaramos la ruta y nombre del archivo a generar
	$filename = $dir.$qr.'.png';
 
        //Parametros de Condiguración
	
	$tamaño = 5; //Tamaño de Pixel
	$level = 'H'; //Precisión Baja
	$framSize = 3; //Tamaño en blanco
	$contenido = $link_qr; //Texto
	//$contenido = "http://camagare.com"; //Texto
	
	/*
	L = Baja
	M = Mediana
	Q = Alta
	H= Máxima
	
	Texto: ‘Codigos de Programacion’

	URL: ‘http://www.codigosdeprogramacion.com’

	Télefono: ‘tel:(049)123-456-789′

	SMS: »smsto:(049)012-345-678:Cuerpo de sms’

	Email: ‘mailto:micorreo@dominio.com?subject=Asunto&body=contenido’

	VCard: "BEGIN:VCARD
		N:carlos garcia
		TEL:0984614289
		EMAIL:carlosgarciarevelo@gmail.com
		END:VCARD"
	
	MECARD:N:carlos garcia;TEL:0984614289;EMAIL:carlosgarciarevelo@gmail.com

	WIFI: ‘WIFI:S:MISSID;T:WPA;P:PASSWORD;H:true;’
	*/
	
        //Enviamos los parametros a la Función para generar código QR 
	QRcode::png($contenido, $filename, $level, $tamaño, $framSize); 
	
        //Mostramos la imagen generada
	echo '<img src="'.$dir.basename($filename).'" /><hr/>'; 
}else{
header('Location: ../includes/logout.php');
exit;
}

?>