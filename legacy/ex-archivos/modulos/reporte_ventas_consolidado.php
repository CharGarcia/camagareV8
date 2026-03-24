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
  <title>Consolidado ventas + NC</title>
<?php include("../paginas/menu_de_empresas.php");?>
  </head>
  <body>
 	
    <div class="container-fluid">
		<div class="panel panel-info">
		<div class="panel-heading">		
			<h4><i class='glyphicon glyphicon-list-alt'></i> Reporte de ventas y Notas de crédito consolidado de todas las sucursales</h4>
		</div>

		<div class="panel-body">
			<form class="form-horizontal" method ="POST" target="_blank" action="../excel/reporte_consolidado_ventas_excel.php">
			<input type="hidden" name="id_cliente" id="id_cliente">
			<input type="hidden" name="id_producto" id="id_producto">
					<div class="form-group">
					<div class="col-sm-6">
						<div class="input-group">
							<span class="input-group-addon"><b>Reporte</b></span>
								<select class="form-control input-sm" id="tipo_reporte" name="tipo_reporte" required>
								<option value="1" selected> Facturas</option>
								<option value="2" > Notas de crédito</option>
								</select>
						
							<span class="input-group-addon"><b>Desde</b></span>
							<input type="text" class="form-control input-sm text-center" name="fecha_desde" id="fecha_desde" value="<?php echo date("01-m-Y");?>">
						
							<span class="input-group-addon"><b>Hasta</b></span>
							<input type="text" class="form-control input-sm text-center" name="fecha_hasta" id="fecha_hasta" value="<?php echo date("d-m-Y");?>">
													
						</div>
					</div>
					<div class="col-sm-2">				
							<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="mostrar_reporte()"><span class="glyphicon glyphicon-search" ></span></button>
							<button type="submit" title="Descargar excel" class="btn btn-success btn-sm"><img src="../image/excel.ico" width="16" height="16"></button>
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

function buscar_cliente(){
			$("#nombre_cliente").autocomplete({
				source:'../ajax/clientes_autocompletar.php',
				minLength: 2,
				select: function(event, ui){
					event.preventDefault();
					$('#id_cliente').val(ui.item.id);
					$('#nombre_cliente').val(ui.item.nombre);
				}
			});
	$("#nombre_cliente" ).autocomplete("widget").addClass("fixedHeight");//para que aparezca la barra de desplazamiento en el buscar
		
		$("#nombre_cliente" ).on( "keydown", function( event ) {
		if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
		{
			$("#id_cliente" ).val("");
			$("#nombre_cliente" ).val("");
		}
		if (event.keyCode==$.ui.keyCode.DELETE){
			$("#id_cliente" ).val("");
			$("#nombre_cliente" ).val("");
		}
		});
 }
 
 
 function buscar_productos(){
	$("#nombre_producto").autocomplete({
		source: '../ajax/productos_autocompletar.php',
		minLength: 2,
		select: function(event, ui) {
			event.preventDefault();
			$('#id_producto').val(ui.item.id);
			$('#nombre_producto').val(ui.item.nombre);
			}
		});

	$("#nombre_producto" ).autocomplete("widget").addClass("fixedHeight");//para que aparezca la barra de desplazamiento en el buscar
		
		$("#nombre_producto" ).on( "keydown", function( event ) {
		if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
		{
			$("#id_producto" ).val("");
			$("#nombre_producto" ).val("");
		}
		if (event.keyCode==$.ui.keyCode.DELETE){
			$("#id_producto" ).val("");
			$("#nombre_producto" ).val("");
		}
		});
 }
 
 //generar informe
function mostrar_reporte(){
 var tipo_reporte=$("#tipo_reporte").val();
 var desde = $("#fecha_desde").val();
 var hasta = $("#fecha_hasta").val();

 
	 $.ajax({
			type: "POST",
			url: "../ajax/reporte_ventas_consolidado.php",
			data: "action="+tipo_reporte+"&desde="+desde+"&hasta="+hasta,
			 beforeSend: function(objeto){
				$('#loader').html('<img src="../image/ajax-loader.gif">');
			  },
			success: function(datos){
			$(".outer_div").html(datos);
			$("#loader").html('');
		  }
	});
}
 </script>