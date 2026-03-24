<?php
session_start();
if(isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])){
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa =$_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

?>
<!DOCTYPE html>
<html lang="es">
  <head>
  <title>Retenciones faltantes</title>
<?php include("../paginas/menu_de_empresas.php");?>
  </head>
  <body>
  <?php
		include("../modal/enviar_documentos_mail.php");
		include("../modal/detalle_documento.php");
		?>
    <div class="container-fluid">
		<div class="panel panel-info">
		<div class="panel-heading">		
			<h4><i class='glyphicon glyphicon-list-alt'></i> Reporte de Retenciones faltantes en Ventas</h4>
		</div>

		<div class="panel-body">
			<form class="form-horizontal" method ="POST" action="">
					<div class="form-group">
					<div class="col-sm-4">
						<div class="input-group">
						
							<span class="input-group-addon"><b>Desde</b></span>
							<input type="text" class="form-control input-sm text-center" name="fecha_desde" id="fecha_desde" value="<?php echo date("01-m-Y");?>">
						
							<span class="input-group-addon"><b>Hasta</b></span>
							<input type="text" class="form-control input-sm text-center" name="fecha_hasta" id="fecha_hasta" value="<?php echo date("d-m-Y");?>">
						
						</div>
					</div>
					<div class="col-sm-2">				
							<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="mostrar_reporte()"><span class="glyphicon glyphicon-search" ></span></button>
							<span id="loader"></span>
					</div>
				</div>					
				</form>
			<div id="resultados"></div><!-- Carga los datos ajax -->
			<div class='outer_div'></div><!-- Carga los datos ajax -->
		</div>
		</div>
	</div>
<?php

}else{
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
exit;
}
?>
	
</body>
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css"> 
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
</html>
<script>
jQuery(function($){
     $("#fecha_desde").mask("99-99-9999");
	 $("#fecha_hasta").mask("99-99-9999");
});
$( function() {
	$("#fecha_desde").datepicker({
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

$( function() {
	$("#fecha_hasta").datepicker({
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



 //generar informe
function mostrar_reporte(){
 var desde = $("#fecha_desde").val();
 var hasta = $("#fecha_hasta").val();
 
	 $.ajax({
			type: "POST",
			url: "../ajax/reporte_ret_faltantes.php",
			data: "action=ret_faltantes&desde="+desde+"&hasta="+hasta,
			 beforeSend: function(objeto){
				$('#loader').html('<img src="../image/ajax-loader.gif">');
			  },
			success: function(datos){
			$(".outer_div").html(datos);
			$("#loader").html('');
		  }
	});
}

function solicitar_retencion(id) {
			var mail_receptor = $("#mail_cliente" + id).val();
			$("#id_documento").val(id);
			$("#mail_receptor").val(mail_receptor);
			$("#tipo_documento").val("solicitar_retencion");
		}

		//para enviar por mail
	$("#documento_mail").submit(function(event) {
		$('#enviar_mail').attr("disabled", true);
		$('#mensaje_mail').attr("hidden", true); // para mostrar el mensaje de dar clik para enviar y mas abajo lo desaparece
		var parametros = $(this).serialize();
		//var pagina = $("#pagina").val();
		$.ajax({
			type: "GET",
			url: "../documentos_mail/envia_mail.php?",
			data: parametros,
			beforeSend: function(objeto) {
				$("#resultados_ajax_mail").html(
					'<div class="progress"><div class="progress-bar progress-bar-primary progress-bar-striped active" role="progressbar" style="width:100%;">Enviando solicitud de retención por mail espere por favor...</div></div>');
			},
			success: function(datos) {
				$("#resultados_ajax_mail").html(datos);
				$('#enviar_mail').attr("disabled", false);
				$('#mensaje_mail').attr("hidden", false); // lo vuelve a mostrar el mensaje cuando ya hace todo el proceso
				//load(pagina);
			}
		});
		event.preventDefault();
	});

	function detalle_retencion_venta(id_ret) {
			$("#outer_divdet").fadeIn('slow');
			$.ajax({
				url: '../ajax/detalle_documento.php?action=detalle_retencion_ventas&id_ret=' + id_ret,
				beforeSend: function(objeto) {
					$('#outer_divdet').html('<img src="../image/ajax-loader.gif"> Cargando detalle de retención...');
				},
				success: function(data) {
					$(".outer_divdet").html(data).fadeIn('slow');
					$('#outer_divdet').html('');
				}
			})
		}
 </script>