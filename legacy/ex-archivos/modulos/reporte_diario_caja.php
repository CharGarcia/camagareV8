<?php
session_start();
if(isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])){
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa =$_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

?>
<!DOCTYPE html>
<html lang="en">
  <head>
  <meta charset="utf-8">
  <title>Diario Caja</title>
	<?php 
	include("../paginas/menu_de_empresas.php");
	include("../modal/entradas_salidas_caja.php");
	include("../modal/enviar_documentos_mail.php");
	ini_set('date.timezone','America/Guayaquil');
	$con = conenta_login();
	$busca_responsable = mysqli_query($con,"SELECT * FROM usuarios WHERE id='".$id_usuario."'");
	$row_responsable = mysqli_fetch_array($busca_responsable);
	$nombre_responsable = $row_responsable['nombre'];
	
	$busca_mail = mysqli_query($con,"SELECT * FROM empresas WHERE id='".$id_empresa."'");
	$row_mail = mysqli_fetch_array($busca_mail);
	$mail_empresa = $row_mail['mail'];
	?>
<style type="text/css">
 ul.ui-autocomplete {
    z-index: 1100;
}
</style>
  </head>
  <body>

<div class="container">  
    <div class="panel panel-success">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-search'></i> Reporte Diario de Caja</h4>		
		</div>
		<div class="panel-body">
			<div class="form-group row">
			<form class="form-horizontal" role="form" method ="POST" action="../pdf/pdf_diario_caja.php">
			<input type="hidden" value="<?php echo $mail_empresa;?>" id ="mail_empresa" >
				<label class="col-md-1 control-label text-right">Fecha</label>
				<div class="col-md-2">		
				<input type="text" class="form-control input-sm text-center" name="fecha_diario_caja" id="fecha_diario_caja" value="<?php echo date("d-m-Y");?>">
				</div>
				<label class="col-md-1 control-label text-right">Responsable</label>
				<div class="col-md-3">		
				<input type="text" class="form-control input-sm text-left" id="responsable_caja" name="responsable_caja" value="<?php echo $nombre_responsable;?>" >
				</div>
				<div class="col-md-4">
				<button type='button' class="btn btn-primary" onclick="muestra_diario_caja()" ><span class="glyphicon glyphicon-search" ></span> Mostrar</button>
				<button type='submit' class="btn btn-default">Pdf</button>
				<!--<button type='button' class="btn btn-info" onclick="enviar_caja_mail()" data-toggle="modal" data-target="#EnviarDocumentosMail"><span class="glyphicon glyphicon-envelope" ></span></button>-->
				</div>		
				<span id="loader" ></span>
			</div>
			</form>
			<form id="detalle_efectivo" method="POST">
			<div id="resultados"></div><!-- Carga los datos ajax -->
			<div class='outer_div'></div><!-- Carga los datos ajax -->
			</form>
		</div>
	</div>
 </div>

<?php
}else{
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
exit;
}
?>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css"> 
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
 </body>
</html>
<script>
jQuery(function($){
     $("#fecha_diario_caja").mask("99-99-9999");
});

$( function() {
$("#fecha_diario_caja").datepicker({
        dateFormat: "dd-mm-yy",
        firstDay: 1,
        dayNamesMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
        dayNamesShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
        monthNames: 
            ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio",
            "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
        monthNamesShort: 
            ["Ene", "Feb", "Mar", "Abr", "May", "Jun",
            "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"]
});
});


function muestra_diario_caja(){
	var fecha_caja = document.getElementById('fecha_diario_caja').value;
	$("#loader").fadeIn('slow');
	$.ajax({
		url:'../ajax/buscar_diario_caja.php?action=diario_caja&fecha_caja='+fecha_caja,
		 beforeSend: function(objeto){
		 $('#loader').html('<img src="../image/ajax-loader.gif">');
	  },
		success:function(data){
			$(".outer_div").html(data).fadeIn('slow');
			$('#loader').html('');
			calcula_efectivo('billete', '100');//para que me calcule automaticamente el total de efectivo
		}
	});
	
}	

function calcula_efectivo(tipo, valor){
	if (tipo=='billete'){
	switch(valor) {
		    case "100":
			var resultado = $("#billeteCien").val();
			var nombre_input = document.getElementById("billeteCien").name;
			varificar (resultado, nombre_input);
			$('#resbilleteCien').val((resultado*100).toFixed(2));
			break;
		    case "50":
			var resultado = $("#billeteCincuenta").val();
			var nombre_input = document.getElementById("billeteCincuenta").name;
			varificar (resultado, nombre_input);
			$('#resbilleteCincuenta').val((resultado*50).toFixed(2));
			break;
			case "20":
			var resultado = $("#billeteVeinte").val();
			var nombre_input = document.getElementById("billeteVeinte").name;
			varificar (resultado, nombre_input);
			$('#resbilleteVeinte').val((resultado*20).toFixed(2));
			break;
			case "10":
			var resultado = $("#billeteDiez").val();
			var nombre_input = document.getElementById("billeteDiez").name;
			varificar (resultado, nombre_input);
			$('#resbilleteDiez').val((resultado*10).toFixed(2));
			break;
			case "5":
			var resultado = $("#billeteCinco").val();
			var nombre_input = document.getElementById("billeteCinco").name;
			varificar (resultado, nombre_input);
			$('#resbilleteCinco').val((resultado*5).toFixed(2));
			break;
			case "2":
			var resultado = $("#billeteDos").val();
			var nombre_input = document.getElementById("billeteDos").name;
			varificar (resultado, nombre_input);
			$('#resbilleteDos').val((resultado*2).toFixed(2));
			break;
			case "1":
			var resultado = $("#billeteUno").val();
			var nombre_input = document.getElementById("billeteUno").name;
			varificar (resultado, nombre_input);
			$('#resbilleteUno').val((resultado*1).toFixed(2));
			break;
		}
	}
	
	if (tipo=='moneda'){
	switch(valor) {
		    case "100":
			var resultado = $("#monedaCien").val();
			var nombre_input = document.getElementById("monedaCien").name;
			varificar (resultado, nombre_input);
			$('#resmonedaCien').val((resultado*1).toFixed(2));
			break;
		    case "50":
			var resultado = $("#monedaCincuenta").val();
			var nombre_input = document.getElementById("monedaCincuenta").name;
			varificar (resultado, nombre_input);
			$('#resmonedaCincuenta').val((resultado*0.50).toFixed(2));
			break;
			case "25":
			var resultado = $("#monedaVeinticinco").val();
			var nombre_input = document.getElementById("monedaVeinticinco").name;
			varificar (resultado, nombre_input);
			$('#resmonedaVeinticinco').val((resultado*0.25).toFixed(2));
			break;
			case "10":
			var resultado = $("#monedaDiez").val();
			var nombre_input = document.getElementById("monedaDiez").name;
			varificar (resultado, nombre_input);
			$('#resmonedaDiez').val((resultado*0.10).toFixed(2));
			break;
			case "5":
			var resultado = $("#monedaCinco").val();
			var nombre_input = document.getElementById("monedaCinco").name;
			varificar (resultado, nombre_input);
			$('#resmonedaCinco').val((resultado*0.05).toFixed(2));
			break;
			case "1":
			var resultado = $("#monedaUno").val();
			var nombre_input = document.getElementById("monedaUno").name;
			varificar (resultado, nombre_input);
			$('#resmonedaUno').val((resultado*0.01).toFixed(2));
			break;
		}
	}
//sumar todos los input
var b100= document.getElementById("resbilleteCien").value;
var b50= document.getElementById("resbilleteCincuenta").value;
var b20= document.getElementById("resbilleteVeinte").value;
var b10= document.getElementById("resbilleteDiez").value;
var b5= document.getElementById("resbilleteCinco").value;
var b2= document.getElementById("resbilleteDos").value;
var b1= document.getElementById("resbilleteUno").value;
var total_billetes = parseFloat(b100) + parseFloat(b50) + parseFloat(b20) + parseFloat(b10) + parseFloat(b5) + parseFloat(b2) + parseFloat(b1);

var m100= document.getElementById("resmonedaCien").value;
var m50= document.getElementById("resmonedaCincuenta").value;
var m25= document.getElementById("resmonedaVeinticinco").value;
var m10= document.getElementById("resmonedaDiez").value;
var m5= document.getElementById("resmonedaCinco").value;
var m1= document.getElementById("resmonedaUno").value;
var total_monedas = parseFloat(m100) + parseFloat(m50) + parseFloat(m25) + parseFloat(m10) + parseFloat(m5) + parseFloat(m1);

var total_general = parseFloat(total_billetes) + parseFloat(total_monedas);
$('#total_efectivo').val(total_general);
}	

 function varificar (resultado, nombre_input){
if (isNaN(resultado)){
		$.notify('El dato ingresado, no es un número','error');
		$("#"+nombre_input ).val("");
		return false;
		}
		if (Number(resultado) <0 ){
		$.notify('Ingrese cantidad mayor a cero','error');
		$("#"+nombre_input ).val("");
		return false;
		}
 }

//gardar detalle de efectivo
$( "#detalle_efectivo" ).submit(function( event ){
  $('#guardar').attr("disabled", true);
	var parametros = $(this).serialize();  
	var fecha_caja = document.getElementById('fecha_diario_caja').value;
	
	var venta_efectivo_dia = document.getElementById('total_ventas_efectivo').value;
	var detalle_efectivo_dia = document.getElementById('total_efectivo').value;
	if (parseFloat(venta_efectivo_dia) == parseFloat(detalle_efectivo_dia)){
	 $.ajax({
			type: "POST",
			url: "../ajax/guardar_detalle_efectivo.php",
			data: parametros+"&fecha_caja="+fecha_caja+"&action=detalle_efectivo",
			 beforeSend: function(objeto){
				$("#resultados_ajax_efectivo").html("Mensaje: Guardando...");
			  },
			success: function(datos){
			$("#resultados_ajax_efectivo").html(datos);
			$('#guardar').attr("disabled", false);
			}
	});
	}else{
		$.notify('El valor de efectivo no es igual al reporte de ventas en efectivo.','error');
		$('#guardar').attr("disabled", false);
	}
  event.preventDefault();
});

//PASAR TIPO DE SALIDA O ENTRADA
 function tipo_registro(tipo){
	$('#tipo_registro').val(tipo);
 }

 //GUARDA ENTRADA O SALIDA
$( "#guardar_entrada_salida_caja" ).submit(function( event ){
  $('#guardar_datos').attr("disabled", true);
	var parametros = $(this).serialize();  
	var fecha_caja = document.getElementById('fecha_diario_caja').value;
	 $.ajax({
			type: "POST",
			url: "../ajax/guardar_detalle_efectivo.php",
			data: parametros+"&fecha_caja="+fecha_caja+"&action=entradas_salidas_caja",
			 beforeSend: function(objeto){
				$("#resultados_ajax_caja").html("Mensaje: Guardando...");
			  },
			success: function(datos){
			$("#resultados_ajax_caja").html(datos);
			$('#guardar_datos').attr("disabled", false);
			$("#detalle_entrada_salida").val("");
			$("#valor_entrada_salida").val("");
			muestra_diario_caja();
			}
	});
  event.preventDefault();
});
 
 function eliminar_registro(id){
		if (confirm("Realmente desea eliminar el registro?")){	
		$.ajax({
        type: "GET",
        url: "../ajax/guardar_detalle_efectivo.php",
        data: "id_registro="+id+"&action=eliminar_registro",
		 beforeSend: function(objeto){
			$("#resultados_ajax_caja").html("Mensaje: Cargando...");
		  },
        success: function(datos){
		$("#resultados_ajax_caja").html(datos);
		muestra_diario_caja();
		}
			});
		}
}


function enviar_caja_mail(){
			var mail_receptor = $("#mail_empresa").val();
			$("#mail_receptor").val(mail_receptor);
			$("#tipo_documento").val("caja");
	};

//para enviar por mail la factura
 $( "#documento_mail" ).submit(function( event ) {
 $('#enviar_mail').attr("disabled", true);
 $('#mensaje_mail').attr("hidden", true);// para mostrar el mensaje de dar clik para enviar y mas abajo lo desaparece
	var parametros = $(this).serialize();
	 $.ajax({
			type: "GET",
			url: "../documentos_mail/envia_mail.php?",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax_mail").html(	
				'<div class="progress"><div class="progress-bar progress-bar-primary progress-bar-striped active" role="progressbar" style="width:100%;">Enviando Factura por mail espere por favor...</div></div>');
			  },
			success: function(datos){
			$("#resultados_ajax_mail").html(datos);
			$('#enviar_mail').attr("disabled", false);
			$('#mensaje_mail').attr("hidden", false); // lo vuelve a mostrar el mensaje cuando ya hace todo el proceso
		  }
	});
  event.preventDefault();
});
	
</script>