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
  <title>Informes contables</title>
<?php 
include("../paginas/menu_de_empresas.php");
date_default_timezone_set('America/Guayaquil');
?>
  </head>
  <body>
 	
    <div class="container">
		<div class="panel panel-info">
			<div class="panel-heading">		
				<h4><i class='glyphicon glyphicon-list-alt'></i> Informes Contables</h4>
			</div>
			<div class="panel-body">
				<form class="form-horizontal" role="form" >
			
			<input type="hidden" name="id_cuenta" id="id_cuenta">
					<div class="form-group">
						<div class="col-sm-3">
						<div class="input-group">
							<span class="input-group-addon"><b>Nombre</b></span>
								<select class="form-control input-sm" id="nombre_informe" name="nombre_informe" required>
								<option value="1" selected> Balance General</option>
								<option value="2" > Estado de Resultados</option>
								<option value="3" > Mayor General</option>
								<option value="4" > Balance de Comprobación</option>
								</select>
						</div>
						</div>
						<div class="col-sm-3">
						<div class="input-group">
							<span class="input-group-addon"><b>Cuenta</b></span>
							<input type="text" class="form-control input-sm" name="cuenta" id="cuenta" value="Todas" onkeyup='agregar_cuenta();' placeholder="Todos" autocomplete="off">
						</div>
						</div>
						
						<div class="col-sm-2">
						<div class="input-group">
							<span class="input-group-addon"><b>Desde</b></span>
							<input type="text" class="form-control input-sm text-center" name="fecha_desde" id="fecha_desde" value="<?php echo date("01-01-Y");?>">
						</div>
						</div>
						
						<div class="col-sm-2">
						<div class="input-group">
							<span class="input-group-addon"><b>Hasta</b></span>
							<input type="text" class="form-control input-sm text-center" name="fecha_hasta" id="fecha_hasta" value="<?php echo date("d-m-Y");?>">
						</div>
						</div>
								<div class="col-sm-2">
									<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="mostrar_informe()"><span class="glyphicon glyphicon-search" ></span></button>				
									<button type="submit" title="Descargar pdf" class="btn btn-default btn-sm">Pdf</button>					
									<button type="submit" title="Descargar excel" class="btn btn-success btn-sm"><img src="../image/excel.ico" width="20" height="18"></button>
								</div>
								<span id="loader"></span>
							
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


function agregar_cuenta(){
	$("#cuenta").autocomplete({
			source:'../ajax/cuentas_autocompletar.php',
			minLength: 2,
			select: function(event, ui){
				event.preventDefault();
				$('#id_cuenta').val(ui.item.id_cuenta);
				$('#cuenta').val(ui.item.nombre_cuenta);
			}
		});

		$("#cuenta" ).autocomplete("widget").addClass("fixedHeight");//para que aparezca la barra de desplazamiento en el buscar
		
		$("#cuenta" ).on( "keydown", function( event ) {
		if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
		{
			$("#id_cuenta" ).val("");
			$("#cuenta" ).val("");
		}
		if (event.keyCode==$.ui.keyCode.DELETE){
			$("#cuenta" ).val("");
			$("#id_cuenta" ).val("");
		}
		});
 }
 

$("#producto" ).on( "keydown", function( event ) {
		if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
		{
			$("#producto" ).val("");
		}
		if (event.keyCode==$.ui.keyCode.DELETE){
			$("#producto" ).val("");
		}
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
function mostrar_informe(){
 var nombre=$("#nombre_informe").val();
 var cuenta=$("#id_cuenta").val();
 var desde = $("#fecha_desde").val();
 var hasta = $("#fecha_hasta").val();
 
	 $.ajax({
			type: "POST",
			url: "../ajax/informes_contables.php",
			data: "action="+nombre+"&cuenta="+cuenta+"&desde="+desde+"&hasta="+hasta,
			 beforeSend: function(objeto){
				$("#resultados").html("Mensaje: Cargando...");
			  },
			success: function(datos){
			$(".outer_div").html(datos);
			$("#resultados").html('');
		  }
	});
}

</script>